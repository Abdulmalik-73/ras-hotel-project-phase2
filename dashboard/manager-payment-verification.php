<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

$message = '';
$error = '';

// Handle payment review/approval
if ($_POST && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    
    if ($_POST['action'] == 'review') {
        // Get booking details for review
        $review_query = "SELECT b.*, 
                         COALESCE(r.name, 'Food Order') as room_name, 
                         COALESCE(r.room_number, 'N/A') as room_number, 
                         CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                         u.email, u.phone
                         FROM bookings b 
                         LEFT JOIN rooms r ON b.room_id = r.id 
                         JOIN users u ON b.user_id = u.id 
                         WHERE b.id = ? AND b.booking_type = 'food_order'";
        
        $stmt = $conn->prepare($review_query);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            // Store in session for modal display
            $_SESSION['review_booking'] = $booking;
        } else {
            $error = 'Food order not found or is not a food order';
        }
    }
    
    elseif ($_POST['action'] == 'approve_review') {
        $current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        
        // Verify user exists
        if ($current_user_id) {
            $user_check = $conn->query("SELECT id FROM users WHERE id = $current_user_id");
            if ($user_check->num_rows == 0) {
                $current_user_id = null;
            }
        }
        
        // Update booking status
        if ($current_user_id) {
            $update_query = "UPDATE bookings SET 
                            verification_status = 'verified',
                            payment_status = 'paid',
                            status = 'confirmed',
                            verified_by = ?,
                            verified_at = NOW()
                            WHERE id = ? AND booking_type = 'food_order'";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ii", $current_user_id, $booking_id);
        } else {
            $update_query = "UPDATE bookings SET 
                            verification_status = 'verified',
                            payment_status = 'paid',
                            status = 'confirmed',
                            verified_by = NULL,
                            verified_at = NOW()
                            WHERE id = ? AND booking_type = 'food_order'";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("i", $booking_id);
        }
        
        if ($stmt->execute()) {
            $message = 'Food order payment approved successfully!';
            unset($_SESSION['review_booking']);
        } else {
            $error = 'Failed to approve payment: ' . $stmt->error;
        }
    }
    
    elseif ($_POST['action'] == 'reject_review') {
        $rejection_reason = sanitize_input($_POST['rejection_reason'] ?? '');
        $current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        
        // Verify user exists
        if ($current_user_id) {
            $user_check = $conn->query("SELECT id FROM users WHERE id = $current_user_id");
            if ($user_check->num_rows == 0) {
                $current_user_id = null;
            }
        }
        
        if ($current_user_id) {
            $update_query = "UPDATE bookings SET 
                            verification_status = 'rejected',
                            status = 'cancelled',
                            verified_by = ?,
                            verified_at = NOW(),
                            rejection_reason = ?
                            WHERE id = ? AND booking_type = 'food_order'";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("isi", $current_user_id, $rejection_reason, $booking_id);
        } else {
            $update_query = "UPDATE bookings SET 
                            verification_status = 'rejected',
                            status = 'cancelled',
                            verified_by = NULL,
                            verified_at = NOW(),
                            rejection_reason = ?
                            WHERE id = ? AND booking_type = 'food_order'";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $rejection_reason, $booking_id);
        }
        
        if ($stmt->execute()) {
            $message = 'Food order payment rejected successfully!';
            unset($_SESSION['review_booking']);
        } else {
            $error = 'Failed to reject payment: ' . $stmt->error;
        }
    }
}

// Get pending food order payments
$pending_food_orders = $conn->query("
    SELECT b.*, 
           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
           u.email, u.phone,
           fo.order_reference,
           fo.guests,
           fo.table_reservation,
           fo.reservation_date,
           fo.reservation_time
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    LEFT JOIN food_orders fo ON b.id = fo.booking_id
    WHERE b.verification_status = 'pending_verification' 
    AND b.booking_type = 'food_order'
    ORDER BY b.screenshot_uploaded_at DESC
");

// Get statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN verification_status = 'pending_verification' AND booking_type = 'food_order' THEN 1 END) as needs_review,
                COUNT(CASE WHEN verification_status = 'verified' AND booking_type = 'food_order' THEN 1 END) as approved,
                COUNT(CASE WHEN verification_status = 'rejected' AND booking_type = 'food_order' THEN 1 END) as rejected
                FROM bookings";
$stats = $conn->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stat-card h3 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .stat-card p {
            margin: 0;
            font-size: 0.9em;
        }
        .payment-screenshot {
            max-width: 300px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
        }
        .food-order-card {
            border-left: 4px solid #ff9800;
            transition: all 0.3s;
        }
        .food-order-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> 
                <span class="text-white fw-bold">Harar Ras Hotel - Manager</span>
            </a>
            <div class="ms-auto">
                <a href="manager.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-user-tie"></i> Manager Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="manager.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="manager-bookings.php" class="nav-link">
                            <i class="fas fa-calendar-check me-2"></i> Manage Bookings
                        </a>
                        <button onclick="history.back()" class="nav-link" style="border: none; background: transparent;">
                            <i class="fas fa-arrow-left me-2"></i> Back
                        </button>
                        <a href="manager-feedback.php" class="nav-link">
                            <i class="fas fa-star me-2"></i> Customer Feedback
                        </a>
                        <a href="manager-refund.php" class="nav-link">
                            <i class="fas fa-undo me-2"></i> Refund Management
                        </a>
                        <a href="manager-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Room Management
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-check-circle me-2"></i> Payment Verification Dashboard</h2>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    
                    <p class="text-muted">Review and verify customer payment transaction IDs for food orders</p>
                    
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h3><?php echo $stats['needs_review'] ?? 0; ?></h3>
                                <p>Needs Review</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h3><?php echo $stats['approved'] ?? 0; ?></h3>
                                <p>Approved</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h3><?php echo $stats['rejected'] ?? 0; ?></h3>
                                <p>Rejected</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Pending Food Orders -->
                    <h4 class="mb-3"><i class="fas fa-utensils me-2"></i> Food Order Payments Pending Review</h4>
                    
                    <?php if ($pending_food_orders && $pending_food_orders->num_rows > 0): ?>
                        <?php while ($booking = $pending_food_orders->fetch_assoc()): ?>
                        <div class="card food-order-card mb-4">
                            <div class="card-header bg-light">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="mb-0">
                                            <i class="fas fa-utensils text-warning"></i> 
                                            <?php echo htmlspecialchars($booking['order_reference'] ?? $booking['booking_reference']); ?>
                                        </h5>
                                        <small class="text-muted">
                                            Uploaded: <?php echo date('M j, Y g:i A', strtotime($booking['screenshot_uploaded_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-hourglass-half"></i> Pending Verification
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-user me-2"></i> Customer Information</h6>
                                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></p>
                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($booking['email'] ?? ''); ?></p>
                                        <p class="mb-3"><strong>Phone:</strong> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?></p>
                                        
                                        <h6><i class="fas fa-utensils me-2"></i> Order Details</h6>
                                        <p class="mb-1"><strong>Guests:</strong> <?php echo $booking['guests'] ?? 1; ?></p>
                                        <p class="mb-1"><strong>Table Reservation:</strong> <?php echo $booking['table_reservation'] ? 'Yes' : 'No'; ?></p>
                                        <?php if ($booking['table_reservation']): ?>
                                            <p class="mb-1"><strong>Date:</strong> <?php echo $booking['reservation_date'] ? date('M j, Y', strtotime($booking['reservation_date'])) : 'N/A'; ?></p>
                                            <p class="mb-3"><strong>Time:</strong> <?php echo $booking['reservation_time'] ?? 'N/A'; ?></p>
                                        <?php endif; ?>
                                        
                                        <h6><i class="fas fa-money-bill me-2"></i> Payment Information</h6>
                                        <p class="mb-1"><strong>Amount:</strong> <span class="text-success fw-bold"><?php echo format_currency($booking['total_price']); ?></span></p>
                                        <p class="mb-1"><strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'] ?? '')); ?></p>
                                        <p class="mb-0"><strong>Reference:</strong> <code><?php echo $booking['payment_reference']; ?></code></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-receipt me-2"></i> Transaction ID</h6>
                                        <?php if ($booking['transaction_id']): ?>
                                            <div class="alert alert-info mb-2">
                                                <strong>Transaction ID:</strong><br>
                                                <code class="fs-6"><?php echo htmlspecialchars($booking['transaction_id']); ?></code>
                                            </div>
                                            <p class="text-muted small">Submitted: <?php echo date('M j, Y g:i A', strtotime($booking['screenshot_uploaded_at'])); ?></p>
                                        <?php else: ?>
                                            <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> No transaction ID submitted</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $booking['id']; ?>">
                                        <i class="fas fa-eye me-2"></i> Review
                                    </button>
                                    <a href="manager.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Back
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Review Modal -->
                        <div class="modal fade" id="reviewModal<?php echo $booking['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">
                                            <i class="fas fa-eye me-2"></i> Review Food Order Payment
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <h6>Order Reference</h6>
                                                <p class="fw-bold"><?php echo htmlspecialchars($booking['order_reference'] ?? $booking['booking_reference']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Amount</h6>
                                                <p class="fw-bold text-success"><?php echo format_currency($booking['total_price']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <h6>Customer Name</h6>
                                                <p><?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Payment Method</h6>
                                                <p><?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'] ?? '')); ?></p>
                                            </div>
                                        </div>
                                        
                                        <h6>Transaction ID</h6>
                                        <?php if ($booking['transaction_id']): ?>
                                            <div class="alert alert-info mb-3">
                                                <strong>Transaction ID:</strong><br>
                                                <code class="fs-5"><?php echo htmlspecialchars($booking['transaction_id']); ?></code>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-danger">No transaction ID provided</p>
                                        <?php endif; ?>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> 
                                            <strong>Verify the following:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Amount matches: <?php echo format_currency($booking['total_price']); ?></li>
                                                <li>Payment method is correct: <?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'] ?? '')); ?></li>
                                                <li>Transaction ID is valid</li>
                                                <li>Screenshot is clear and readable</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="approve_review">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <button type="submit" class="btn btn-success" onclick="return confirm('Approve this payment?')">
                                                <i class="fas fa-check me-2"></i> Approve Payment
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $booking['id']; ?>" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-2"></i> Reject Payment
                                        </button>
                                        
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-2"></i> Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?php echo $booking['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title">
                                            <i class="fas fa-times me-2"></i> Reject Payment
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="reject_review">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Rejection Reason</label>
                                                <textarea name="rejection_reason" class="form-control" rows="4" placeholder="Explain why this payment is being rejected..." required></textarea>
                                                <small class="text-muted">Examples: Incorrect amount, Blurry screenshot, Wrong payment method, etc.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this payment?')">
                                                <i class="fas fa-times me-2"></i> Reject Payment
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info text-center py-5">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h5>No Pending Food Order Payments</h5>
                            <p class="mb-0">All food order payments have been reviewed!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
