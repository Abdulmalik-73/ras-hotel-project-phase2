<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

$success = '';
$error = '';

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

// Get filter parameters
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';
$sort_order = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'newest';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($rating_filter > 0) {
    $where_conditions[] = "cf.overall_rating = ?";
    $params[] = $rating_filter;
    $param_types .= 'i';
}

if ($date_filter) {
    $where_conditions[] = "DATE(cf.created_at) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Sort order
$order_clause = '';
switch ($sort_order) {
    case 'oldest':
        $order_clause = 'ORDER BY cf.created_at ASC';
        break;
    case 'rating_high':
        $order_clause = 'ORDER BY cf.overall_rating DESC, cf.created_at DESC';
        break;
    case 'rating_low':
        $order_clause = 'ORDER BY cf.overall_rating ASC, cf.created_at DESC';
        break;
    default:
        $order_clause = 'ORDER BY cf.created_at DESC';
}

// Get feedback with booking and customer details
$feedback_query = "SELECT cf.*, 
                   b.booking_reference, b.booking_type, b.total_price,
                   COALESCE(r.name, 'Food Order') as room_name,
                   COALESCE(r.room_number, 'N/A') as room_number,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email
                   FROM customer_feedback cf
                   JOIN bookings b ON cf.booking_id = b.id
                   LEFT JOIN rooms r ON b.room_id = r.id
                   LEFT JOIN users u ON cf.customer_id = u.id
                   $where_clause
                   $order_clause";

if (!empty($params)) {
    $stmt = $conn->prepare($feedback_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $feedback_result = $stmt->get_result();
} else {
    $feedback_result = $conn->query($feedback_query);
}

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_feedback,
                COALESCE(AVG(overall_rating), 0) as avg_overall,
                COALESCE(AVG(service_quality), 0) as avg_service,
                COALESCE(AVG(cleanliness), 0) as avg_cleanliness,
                COUNT(CASE WHEN overall_rating = 5 THEN 1 END) as five_star,
                COUNT(CASE WHEN overall_rating = 4 THEN 1 END) as four_star,
                COUNT(CASE WHEN overall_rating = 3 THEN 1 END) as three_star,
                COUNT(CASE WHEN overall_rating = 2 THEN 1 END) as two_star,
                COUNT(CASE WHEN overall_rating = 1 THEN 1 END) as one_star
                FROM customer_feedback";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_feedback' => 0,
    'avg_overall' => 0,
    'avg_service' => 0,
    'avg_cleanliness' => 0,
    'five_star' => 0,
    'four_star' => 0,
    'three_star' => 0,
    'two_star' => 0,
    'one_star' => 0
];

// Ensure all values are numeric
$stats['total_feedback'] = (int)($stats['total_feedback'] ?? 0);
$stats['avg_overall'] = (float)($stats['avg_overall'] ?? 0);
$stats['avg_service'] = (float)($stats['avg_service'] ?? 0);
$stats['avg_cleanliness'] = (float)($stats['avg_cleanliness'] ?? 0);
$stats['five_star'] = (int)($stats['five_star'] ?? 0);
$stats['four_star'] = (int)($stats['four_star'] ?? 0);
$stats['three_star'] = (int)($stats['three_star'] ?? 0);
$stats['two_star'] = (int)($stats['two_star'] ?? 0);
$stats['one_star'] = (int)($stats['one_star'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            transition: left 0.3s ease;
            z-index: 1050;
            overflow-y: auto;
            padding-top: 70px;
        }
        .sidebar.show {
            left: 0;
        }
        .sidebar h4 {
            font-size: 1rem;
            margin-bottom: 0.5rem !important;
            padding: 0 1rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 0.4rem 1rem;
            margin: 0.1rem 0.5rem;
            border-radius: 0.3rem;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
        }
        .sidebar .nav-link i {
            width: 18px;
            font-size: 0.85rem;
        }
        .menu-toggle {
            position: fixed;
            top: 70px;
            left: 10px;
            z-index: 1060;
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-size: 1.2rem;
            transition: left 0.3s ease;
        }
        .menu-toggle.shifted {
            left: 290px;
        }
        .menu-toggle:hover {
            background: #20c997;
        }
        .main-content-wrapper {
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        .main-content-wrapper.shifted {
            margin-left: 280px;
        }
        .rating-stars {
            color: #ffc107;
        }
        .feedback-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s;
        }
        .feedback-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .rating-breakdown {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .stat-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel - Manager
            </a>
            <div class="ms-auto">
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Hamburger Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h4 class="text-white">
            <i class="fas fa-user-tie"></i> Manager Panel
        </h4>
        
        <nav class="nav flex-column">
            <a href="manager.php" class="nav-link">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a href="manager-bookings.php" class="nav-link">
                <i class="fas fa-calendar-check me-2"></i> Manage Bookings
            </a>
            <a href="manager-rooms.php" class="nav-link">
                <i class="fas fa-bed me-2"></i> Manage Rooms
            </a>
            <a href="manager-staff.php" class="nav-link">
                <i class="fas fa-users me-2"></i> Staff Management
            </a>
            <a href="manager-approve-bill.php" class="nav-link">
                <i class="fas fa-file-invoice-dollar me-2"></i> Approve Bill
            </a>
            <a href="manager-feedback.php" class="nav-link active">
                <i class="fas fa-star me-2"></i> Customer Feedback
            </a>
            <a href="manager-refund.php" class="nav-link">
                <i class="fas fa-undo me-2"></i> Process Refunds
            </a>
            <a href="manager-reports.php" class="nav-link">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
            <a href="../logout.php" class="nav-link mt-3">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="main-content-wrapper" id="mainContent">
                    <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-star me-2"></i> Customer Feedback</h2>
                        <a href="manager.php" class="btn btn-outline-success">
                            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                        </a>
                    </div>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <h3 class="mb-1"><?php echo $stats['total_feedback']; ?></h3>
                                    <p class="mb-0">Total Reviews</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <h3 class="mb-1"><?php echo number_format($stats['avg_overall'], 1); ?></h3>
                                    <p class="mb-0">Average Rating</p>
                                    <div class="rating-stars">
                                        <?php 
                                        $avg_rating = round($stats['avg_overall']);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $avg_rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <h3 class="mb-1"><?php echo number_format($stats['avg_service'], 1); ?></h3>
                                    <p class="mb-0">Service Quality</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <h3 class="mb-1"><?php echo number_format($stats['avg_cleanliness'], 1); ?></h3>
                                    <p class="mb-0">Cleanliness</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rating Breakdown -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Rating Distribution</h5>
                        </div>
                        <div class="card-body rating-breakdown">
                            <div class="row">
                                <?php 
                                $ratings = [5 => $stats['five_star'], 4 => $stats['four_star'], 3 => $stats['three_star'], 2 => $stats['two_star'], 1 => $stats['one_star']];
                                foreach ($ratings as $rating => $count): 
                                    $percentage = $stats['total_feedback'] > 0 ? ($count / $stats['total_feedback']) * 100 : 0;
                                ?>
                                <div class="col-md-2 text-center">
                                    <div class="mb-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <h4 class="mb-1"><?php echo $count; ?></h4>
                                    <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Filter by Rating</label>
                                    <select name="rating" class="form-select">
                                        <option value="0">All Ratings</option>
                                        <option value="5" <?php echo $rating_filter == 5 ? 'selected' : ''; ?>>5 Stars</option>
                                        <option value="4" <?php echo $rating_filter == 4 ? 'selected' : ''; ?>>4 Stars</option>
                                        <option value="3" <?php echo $rating_filter == 3 ? 'selected' : ''; ?>>3 Stars</option>
                                        <option value="2" <?php echo $rating_filter == 2 ? 'selected' : ''; ?>>2 Stars</option>
                                        <option value="1" <?php echo $rating_filter == 1 ? 'selected' : ''; ?>>1 Star</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Filter by Date</label>
                                    <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Sort Order</label>
                                    <select name="sort" class="form-select">
                                        <option value="newest" <?php echo $sort_order == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="oldest" <?php echo $sort_order == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                        <option value="rating_high" <?php echo $sort_order == 'rating_high' ? 'selected' : ''; ?>>Highest Rating</option>
                                        <option value="rating_low" <?php echo $sort_order == 'rating_low' ? 'selected' : ''; ?>>Lowest Rating</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="manager-feedback.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Feedback List -->
                    <?php if ($feedback_result->num_rows > 0): ?>
                        <?php while ($feedback = $feedback_result->fetch_assoc()): ?>
                        <div class="card feedback-card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($feedback['customer_name']); ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($feedback['customer_email']); ?>)</small>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Booking:</strong> <?php echo $feedback['booking_reference']; ?>
                                            <?php if ($feedback['booking_type'] == 'room'): ?>
                                                - <?php echo $feedback['room_name']; ?> (Room <?php echo $feedback['room_number']; ?>)
                                            <?php else: ?>
                                                - Food Order
                                            <?php endif; ?>
                                            <span class="text-success ms-2"><?php echo format_currency($feedback['total_price']); ?></span>
                                        </div>
                                        
                                        <?php if ($feedback['comments']): ?>
                                        <div class="mb-2">
                                            <strong>Comments:</strong>
                                            <p class="mb-0 mt-1 p-2 bg-light rounded"><?php echo nl2br(htmlspecialchars($feedback['comments'])); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="text-end">
                                            <div class="mb-2">
                                                <strong>Overall Experience</strong>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $feedback['overall_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-1"><?php echo $feedback['overall_rating']; ?>/5</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <strong>Service Quality</strong>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $feedback['service_quality'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-1"><?php echo $feedback['service_quality']; ?>/5</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <strong>Cleanliness</strong>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $feedback['cleanliness'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-1"><?php echo $feedback['cleanliness']; ?>/5</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#edit<?php echo $feedback['id']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger" type="button" onclick="deleteFeedback(<?php echo $feedback['id']; ?>, '<?php echo htmlspecialchars($feedback['booking_reference']); ?>')">
                                                    <i class="fas fa-trash"></i> Clear
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Edit Form (Collapsed) - Full Width Below -->
                                <div class="collapse mt-3" id="edit<?php echo $feedback['id']; ?>">
                                    <div class="card card-body bg-light">
                                        <h6><i class="fas fa-edit"></i> Edit Feedback</h6>
                                        <form method="POST">
                                            <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                            <div class="row mb-2">
                                                <div class="col-md-4">
                                                    <label class="form-label small">Overall Rating</label>
                                                    <select name="overall_rating" class="form-select form-select-sm" required>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo $feedback['overall_rating'] == $i ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">Service Quality</label>
                                                    <select name="service_quality" class="form-select form-select-sm" required>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo $feedback['service_quality'] == $i ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">Cleanliness</label>
                                                    <select name="cleanliness" class="form-select form-select-sm" required>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo $feedback['cleanliness'] == $i ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small">Comments</label>
                                                <textarea name="comments" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($feedback['comments'] ?? ''); ?></textarea>
                                            </div>
                                            <button type="submit" name="edit_feedback" class="btn btn-sm btn-success">
                                                <i class="fas fa-save"></i> Save Changes
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="collapse" data-bs-target="#edit<?php echo $feedback['id']; ?>">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                            <h5>No Customer Feedback Found</h5>
                            <p class="mb-0">
                                <?php if ($rating_filter || $date_filter): ?>
                                    No feedback matches your current filters. Try adjusting the filters above.
                                <?php else: ?>
                                    No customer feedback has been submitted yet. Feedback will appear here after customers complete their bookings.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hidden form for delete -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="feedback_id" id="deleteFeedbackId">
        <input type="hidden" name="delete_feedback" value="1">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuToggle = document.getElementById('menuToggle');
            
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('shifted');
            menuToggle.classList.toggle('shifted');
        }
        
        function deleteFeedback(feedbackId, bookingRef) {
            if (confirm('Are you sure you want to delete this feedback for booking ' + bookingRef + '?\n\nThis action cannot be undone!')) {
                document.getElementById('deleteFeedbackId').value = feedbackId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>