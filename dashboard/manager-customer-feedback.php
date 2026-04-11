<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a manager
if (!is_logged_in() || !in_array($_SESSION['role'], ['manager', 'admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Handle delete feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_feedback'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    
    $delete_query = "DELETE FROM customer_feedback WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $feedback_id);
    
    if ($stmt->execute()) {
        $success = 'Feedback deleted successfully!';
    } else {
        $error = 'Failed to delete feedback: ' . $conn->error;
    }
}

// Handle edit feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_feedback'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $overall_rating = (int)$_POST['overall_rating'];
    $service_quality = (int)$_POST['service_quality'];
    $cleanliness = (int)$_POST['cleanliness'];
    $comments = sanitize_input($_POST['comments']);
    
    $update_query = "UPDATE customer_feedback 
                     SET overall_rating = ?, 
                         service_quality = ?, 
                         cleanliness = ?, 
                         comments = ?
                     WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iiisi", $overall_rating, $service_quality, $cleanliness, $comments, $feedback_id);
    
    if ($stmt->execute()) {
        $success = 'Feedback updated successfully!';
    } else {
        $error = 'Failed to update feedback: ' . $conn->error;
    }
}

// Handle feedback response
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['respond_feedback'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $admin_response = sanitize_input($_POST['admin_response']);
    $status = sanitize_input($_POST['status']);
    
    $update_query = "UPDATE customer_feedback 
                     SET admin_response = ?, 
                         status = ?, 
                         responded_by = ?, 
                         responded_at = NOW() 
                     WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssii", $admin_response, $status, $_SESSION['user_id'], $feedback_id);
    
    if ($stmt->execute()) {
        $success = 'Feedback response saved successfully!';
    } else {
        $error = 'Failed to save response: ' . $conn->error;
    }
}

// Get filter parameters
$filter_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$filter_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query
$query = "SELECT cf.*, 
          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
          u.email as customer_email,
          b.booking_reference,
          b.booking_type,
          CONCAT(resp.first_name, ' ', resp.last_name) as responder_name
          FROM customer_feedback cf
          JOIN users u ON cf.customer_id = u.id
          JOIN bookings b ON cf.booking_id = b.id
          LEFT JOIN users resp ON cf.responded_by = resp.id
          WHERE 1=1";

$params = [];
$types = '';

if ($filter_rating > 0) {
    $query .= " AND cf.overall_rating = ?";
    $params[] = $filter_rating;
    $types .= 'i';
}

if ($filter_status) {
    $query .= " AND cf.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_type) {
    $query .= " AND cf.booking_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($search) {
    $query .= " AND (b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR cf.comments LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$query .= " ORDER BY cf.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$feedbacks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_feedback,
                AVG(overall_rating) as avg_overall,
                AVG(service_quality) as avg_service,
                AVG(cleanliness) as avg_cleanliness,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN overall_rating >= 4 THEN 1 ELSE 0 END) as positive_count
                FROM customer_feedback";
$stats = $conn->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .feedback-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
        }
        .star-display {
            color: #ffc107;
            font-size: 18px;
        }
        .star-empty {
            color: #ddd;
        }
        .response-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <!-- Manager Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #d4a574 0%, #c9963d 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="manager.php">
                <i class="fas fa-star"></i> Customer Feedback Management
            </a>
            <div class="ms-auto d-flex align-items-center">
                <a href="manager.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <span class="text-white me-3">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    <span class="badge bg-light text-dark ms-2">Manager</span>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="list-group">
                    <a href="manager.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="manager-bookings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-check"></i> Bookings
                    </a>
                    <a href="manager-customer-feedback.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-star"></i> Customer Feedback
                    </a>
                    <a href="manager-reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <h2 class="mb-4"><i class="fas fa-star text-warning"></i> Customer Feedback Management</h2>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card bg-primary text-white">
                            <h3><?php echo $stats['total_feedback']; ?></h3>
                            <p class="mb-0">Total Feedback</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-success text-white">
                            <h3><?php echo number_format($stats['avg_overall'], 1); ?> <i class="fas fa-star"></i></h3>
                            <p class="mb-0">Average Rating</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-warning text-white">
                            <h3><?php echo $stats['pending_count']; ?></h3>
                            <p class="mb-0">Pending Response</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-info text-white">
                            <h3><?php echo $stats['positive_count']; ?></h3>
                            <p class="mb-0">Positive Reviews (4-5★)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Rating</label>
                                <select name="rating" class="form-select">
                                    <option value="">All Ratings</option>
                                    <option value="5" <?php echo $filter_rating == 5 ? 'selected' : ''; ?>>5 Stars</option>
                                    <option value="4" <?php echo $filter_rating == 4 ? 'selected' : ''; ?>>4 Stars</option>
                                    <option value="3" <?php echo $filter_rating == 3 ? 'selected' : ''; ?>>3 Stars</option>
                                    <option value="2" <?php echo $filter_rating == 2 ? 'selected' : ''; ?>>2 Stars</option>
                                    <option value="1" <?php echo $filter_rating == 1 ? 'selected' : ''; ?>>1 Star</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="reviewed" <?php echo $filter_status == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                    <option value="published" <?php echo $filter_status == 'published' ? 'selected' : ''; ?>>Published</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="room" <?php echo $filter_type == 'room' ? 'selected' : ''; ?>>Room Booking</option>
                                    <option value="food_order" <?php echo $filter_type == 'food_order' ? 'selected' : ''; ?>>Food Order</option>
                                    <option value="spa_service" <?php echo $filter_type == 'spa_service' ? 'selected' : ''; ?>>Spa Service</option>
                                    <option value="laundry_service" <?php echo $filter_type == 'laundry_service' ? 'selected' : ''; ?>>Laundry Service</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Booking ref, customer..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="manager-customer-feedback.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Feedback List -->
                <?php if (empty($feedbacks)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No feedback found matching your criteria.
                </div>
                <?php else: ?>
                <?php foreach ($feedbacks as $feedback): ?>
                <div class="feedback-card">
                    <div class="row">
                        <div class="col-md-8">
                            <h5><?php echo htmlspecialchars($feedback['customer_name']); ?></h5>
                            <p class="text-muted mb-2">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($feedback['customer_email']); ?> |
                                <i class="fas fa-calendar"></i> <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                            </p>
                            <p class="mb-2">
                                <strong>Booking:</strong> <?php echo $feedback['booking_reference']; ?> 
                                <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $feedback['booking_type'])); ?></span>
                                <?php if ($feedback['service_type']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($feedback['service_type']); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge bg-<?php echo $feedback['status'] == 'pending' ? 'warning' : ($feedback['status'] == 'reviewed' ? 'info' : 'success'); ?>">
                                <?php echo ucfirst($feedback['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Overall Rating:</strong><br>
                            <span class="star-display">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $feedback['overall_rating'] ? '' : 'star-empty'; ?>"></i>
                                <?php endfor; ?>
                            </span>
                            (<?php echo $feedback['overall_rating']; ?>/5)
                        </div>
                        <div class="col-md-4">
                            <strong>Service Quality:</strong><br>
                            <span class="star-display">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $feedback['service_quality'] ? '' : 'star-empty'; ?>"></i>
                                <?php endfor; ?>
                            </span>
                            (<?php echo $feedback['service_quality']; ?>/5)
                        </div>
                        <div class="col-md-4">
                            <strong>Cleanliness:</strong><br>
                            <span class="star-display">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $feedback['cleanliness'] ? '' : 'star-empty'; ?>"></i>
                                <?php endfor; ?>
                            </span>
                            (<?php echo $feedback['cleanliness']; ?>/5)
                        </div>
                    </div>
                    
                    <?php if ($feedback['comments']): ?>
                    <div class="mb-3">
                        <strong>Comments:</strong>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($feedback['comments'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($feedback['admin_response']): ?>
                    <div class="alert alert-light">
                        <strong><i class="fas fa-reply"></i> Response:</strong>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></p>
                        <small class="text-muted">
                            Responded by <?php echo htmlspecialchars($feedback['responder_name']); ?> 
                            on <?php echo date('M j, Y g:i A', strtotime($feedback['responded_at'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Response Form -->
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#response<?php echo $feedback['id']; ?>">
                            <i class="fas fa-reply"></i> <?php echo $feedback['admin_response'] ? 'Update Response' : 'Add Response'; ?>
                        </button>
                        <button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#edit<?php echo $feedback['id']; ?>">
                            <i class="fas fa-edit"></i> Edit Feedback
                        </button>
                        <button class="btn btn-sm btn-danger" type="button" onclick="deleteFeedback(<?php echo $feedback['id']; ?>, '<?php echo htmlspecialchars($feedback['booking_reference']); ?>')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                    
                    <!-- Edit Feedback Form -->
                    <div class="collapse" id="edit<?php echo $feedback['id']; ?>">
                        <div class="response-form mt-3">
                            <h6><i class="fas fa-edit"></i> Edit Feedback</h6>
                            <form method="POST">
                                <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Overall Rating</label>
                                        <select name="overall_rating" class="form-select" required>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $feedback['overall_rating'] == $i ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Service Quality</label>
                                        <select name="service_quality" class="form-select" required>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $feedback['service_quality'] == $i ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Cleanliness</label>
                                        <select name="cleanliness" class="form-select" required>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $feedback['cleanliness'] == $i ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Comments</label>
                                    <textarea name="comments" class="form-control" rows="3"><?php echo htmlspecialchars($feedback['comments'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" name="edit_feedback" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Update Feedback
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#edit<?php echo $feedback['id']; ?>">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="collapse <?php echo !$feedback['admin_response'] && $feedback['status'] == 'pending' ? 'show' : ''; ?>" id="response<?php echo $feedback['id']; ?>">
                        <div class="response-form">
                            <form method="POST">
                                <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Your Response</label>
                                    <textarea name="admin_response" class="form-control" rows="3" required><?php echo htmlspecialchars($feedback['admin_response'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="pending" <?php echo $feedback['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="reviewed" <?php echo $feedback['status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="published" <?php echo $feedback['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                    </select>
                                </div>
                                <button type="submit" name="respond_feedback" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Response
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <!-- Hidden form for delete -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="feedback_id" id="deleteFeedbackId">
        <input type="hidden" name="delete_feedback" value="1">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteFeedback(feedbackId, bookingRef) {
            if (confirm('Are you sure you want to delete this feedback for booking ' + bookingRef + '?\n\nThis action cannot be undone!')) {
                document.getElementById('deleteFeedbackId').value = feedbackId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
