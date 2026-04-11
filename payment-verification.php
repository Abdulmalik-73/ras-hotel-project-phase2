<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and has appropriate role
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$user_role = get_user_role();
if (!in_array($user_role, ['admin', 'manager', 'receptionist'])) {
    header('Location: index.php');
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$booking_id = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
$message = '';
$error = '';

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $booking_id) {
    $verification_action = $_POST['verification_action'];
    $admin_notes = sanitize_input($_POST['admin_notes']);
    
    if ($verification_action == 'approve') {
        // Approve payment
        $update_query = "UPDATE bookings SET 
                        verification_status = 'verified', 
                        verified_by = ?, 
                        verified_at = NOW(),
                        status = 'confirmed',
                        payment_status = 'paid'
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $_SESSION['user_id'], $booking_id);
        
        if ($stmt->execute()) {
            // Log the approval
            $log_query = "INSERT INTO payment_verification_log 
                         (booking_id, payment_reference, action_type, performed_by, verification_notes, ip_address, user_agent) 
                         VALUES (?, (SELECT payment_reference FROM bookings WHERE id = ?), 'verification_approved', ?, ?, ?, ?)";
            
            $log_stmt = $conn->prepare($log_query);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $log_stmt->bind_param("iiisss", $booking_id, $booking_id, $_SESSION['user_id'], $admin_notes, $ip_address, $user_agent);
            $log_stmt->execute();
            
            // Update verification queue
            $queue_update = "UPDATE payment_verification_queue SET status = 'completed' WHERE booking_id = ?";
            $queue_stmt = $conn->prepare($queue_update);
            $queue_stmt->bind_param("i", $booking_id);
            $queue_stmt->execute();
            
            // Send approval email to customer
            if (send_payment_approval_email($booking_id)) {
                $message = 'Payment approved successfully! Booking has been confirmed and customer has been notified via email.';
            } else {
                $message = 'Payment approved successfully! Booking has been confirmed. (Email notification failed - please contact customer manually)';
            }
        } else {
            $error = 'Failed to approve payment. Please try again.';
        }
        
    } elseif ($verification_action == 'reject') {
        $rejection_reason = sanitize_input($_POST['rejection_reason']);
        
        // Reject payment
        $update_query = "UPDATE bookings SET 
                        verification_status = 'rejected', 
                        verified_by = ?, 
                        verified_at = NOW(),
                        rejection_reason = ?
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("isi", $_SESSION['user_id'], $rejection_reason, $booking_id);
        
        if ($stmt->execute()) {
            // Log the rejection
            $log_query = "INSERT INTO payment_verification_log 
                         (booking_id, payment_reference, action_type, performed_by, verification_notes, ip_address, user_agent) 
                         VALUES (?, (SELECT payment_reference FROM bookings WHERE id = ?), 'verification_rejected', ?, ?, ?, ?)";
            
            $log_stmt = $conn->prepare($log_query);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $combined_notes = "Rejection reason: " . $rejection_reason . "\nAdmin notes: " . $admin_notes;
            $log_stmt->bind_param("iiisss", $booking_id, $booking_id, $_SESSION['user_id'], $combined_notes, $ip_address, $user_agent);
            $log_stmt->execute();
            
            // Update verification queue
            $queue_update = "UPDATE payment_verification_queue SET status = 'completed' WHERE booking_id = ?";
            $queue_stmt = $conn->prepare($queue_update);
            $queue_stmt->bind_param("i", $booking_id);
            $queue_stmt->execute();
            
            // Send rejection email to customer
            if (send_payment_rejection_email($booking_id, $rejection_reason)) {
                $message = 'Payment rejected. Customer has been notified via email to submit a new transaction ID.';
            } else {
                $message = 'Payment rejected. (Email notification failed - please contact customer manually)';
            }
        } else {
            $error = 'Failed to reject payment. Please try again.';
        }
    }
}

// Get verification queue data with error handling
if ($action == 'list') {
    // Try to use the view first, fall back to direct query if it fails
    $queue_query = "SELECT * FROM payment_verification_dashboard ORDER BY 
                   CASE 
                       WHEN verification_status = 'pending_verification' THEN 1
                       WHEN payment_deadline < NOW() THEN 2
                       WHEN payment_deadline < DATE_ADD(NOW(), INTERVAL 10 MINUTE) THEN 3
                       ELSE 4
                   END,
                   screenshot_uploaded_at ASC";
    
    $queue_result = $conn->query($queue_query);
    
    // If view doesn't exist or fails, use direct query
    if (!$queue_result) {
        $queue_query = "SELECT 
                        b.id as booking_id,
                        b.booking_reference,
                        b.verification_status,
                        b.total_price,
                        b.payment_method,
                        b.screenshot_uploaded_at,
                        b.payment_deadline,
                        CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                        u.email as customer_email,
                        u.phone as customer_phone,
                        COALESCE(r.name, 'Food Order') as room_name,
                        COALESCE(r.room_number, 'N/A') as room_number,
                        CASE 
                            WHEN b.verification_status = 'pending_verification' THEN 'NEEDS_REVIEW'
                            WHEN b.payment_deadline < NOW() AND b.verification_status = 'pending_payment' THEN 'EXPIRED'
                            WHEN b.payment_deadline < DATE_ADD(NOW(), INTERVAL 10 MINUTE) AND b.verification_status = 'pending_payment' THEN 'URGENT'
                            ELSE 'NORMAL'
                        END as priority_status
                        FROM bookings b
                        LEFT JOIN users u ON b.user_id = u.id
                        LEFT JOIN rooms r ON b.room_id = r.id
                        WHERE b.verification_status IN ('pending_payment', 'pending_verification', 'rejected')
                        ORDER BY 
                        CASE 
                            WHEN b.verification_status = 'pending_verification' THEN 1
                            WHEN b.payment_deadline < NOW() THEN 2
                            WHEN b.payment_deadline < DATE_ADD(NOW(), INTERVAL 10 MINUTE) THEN 3
                            ELSE 4
                        END,
                        b.screenshot_uploaded_at ASC";
        
        $queue_result = $conn->query($queue_query);
    }
    
    if ($queue_result) {
        $verification_queue = $queue_result->fetch_all(MYSQLI_ASSOC);
    } else {
        $verification_queue = [];
        $error = "Error loading verification queue: " . $conn->error;
    }
}

// Get specific booking for review
if ($action == 'review' && $booking_id) {
    $booking_query = "SELECT b.*, 
                     COALESCE(r.name, 'Food Order') as room_name, 
                     COALESCE(r.room_number, 'N/A') as room_number,
                     CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                     u.email as customer_email, u.phone as customer_phone,
                     'N/A' as method_name,
                     'N/A' as bank_name,
                     'N/A' as account_number,
                     'N/A' as verification_tips,
                     CONCAT(verifier.first_name, ' ', verifier.last_name) as verified_by_name
                     FROM bookings b
                     JOIN users u ON b.user_id = u.id
                     LEFT JOIN rooms r ON b.room_id = r.id
                     LEFT JOIN users verifier ON b.verified_by = verifier.id
                     WHERE b.id = ?";
    
    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        header('Location: payment-verification.php');
        exit();
    }
    
    // Get verification log for this booking
    $log_query = "SELECT pvl.*, CONCAT(u.first_name, ' ', u.last_name) as performed_by_name
                  FROM payment_verification_log pvl
                  LEFT JOIN users u ON pvl.performed_by = u.id
                  WHERE pvl.booking_id = ?
                  ORDER BY pvl.created_at DESC";
    
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("i", $booking_id);
    $log_stmt->execute();
    $verification_log = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get statistics with better error handling
$stats_query = "SELECT 
                COUNT(*) as total_pending,
                COUNT(CASE WHEN verification_status = 'pending_verification' THEN 1 END) as needs_review,
                COUNT(CASE WHEN payment_deadline < NOW() AND verification_status = 'pending_payment' THEN 1 END) as expired,
                COUNT(CASE WHEN payment_deadline < DATE_ADD(NOW(), INTERVAL 10 MINUTE) AND verification_status = 'pending_payment' THEN 1 END) as urgent
                FROM bookings 
                WHERE verification_status IN ('pending_payment', 'pending_verification', 'rejected')";

$stats_result = $conn->query($stats_query);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
} else {
    // Default values if query fails
    $stats = [
        'total_pending' => 0,
        'needs_review' => 0,
        'expired' => 0,
        'urgent' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .verification-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .verification-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .priority-urgent {
            border-left: 4px solid #dc3545;
        }
        
        .priority-needs-review {
            border-left: 4px solid #007bff;
        }
        
        .priority-expired {
            border-left: 4px solid #6c757d;
        }
        
        .screenshot-preview {
            max-width: 100%;
            max-height: 400px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .verification-actions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .log-entry {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        
        .log-entry.approved {
            border-left-color: #28a745;
        }
        
        .log-entry.rejected {
            border-left-color: #dc3545;
        }
        
        .log-entry.expired {
            border-left-color: #6c757d;
        }
        
        .stats-card {
            background: #007bff;
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 1;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Staff Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt"></i> Payment Verification System
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo ucfirst($user_role); ?></span>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Page Header -->
    <section class="py-3 bg-light">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="dashboard/admin.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                <div class="col text-center">
                    <h2 class="mb-0">Payment Verification System</h2>
                    <p class="text-muted mb-0">Review and verify customer transaction IDs</p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <section class="py-4">
        <div class="container-fluid">
            <?php if ($action == 'list'): ?>
            <!-- Dashboard View -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-shield-alt text-primary"></i> Payment Verification Dashboard</h2>
                            <p class="text-muted">Review and verify customer transaction IDs</p>
                        </div>
                        <div>
                            <a href="dashboard/admin.php" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                            </a>
                            <button onclick="location.reload()" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-card">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['needs_review']; ?></div>
                            <div class="stat-label">Needs Review</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['urgent']; ?></div>
                            <div class="stat-label">Urgent</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['expired']; ?></div>
                            <div class="stat-label">Expired</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total_pending']; ?></div>
                            <div class="stat-label">Total Pending</div>
                        </div>
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
            
            <!-- Verification Queue -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Payment Verification Queue</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($verification_queue)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>All Caught Up!</h5>
                        <p class="text-muted">No payments pending verification at the moment.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($verification_queue as $item): ?>
                    <div class="verification-card <?php 
                        echo $item['priority_status'] == 'NEEDS_REVIEW' ? 'priority-needs-review' : 
                            ($item['priority_status'] == 'URGENT' ? 'priority-urgent' : 
                            ($item['priority_status'] == 'EXPIRED' ? 'priority-expired' : '')); 
                    ?>">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['customer_name'] ?? 'Unknown'); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['customer_email'] ?? 'N/A'); ?></small>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></small>
                                </div>
                                <div class="col-md-2">
                                    <strong><?php echo htmlspecialchars($item['room_name'] ?? 'N/A'); ?></strong>
                                    <br><small class="text-muted">Room <?php echo htmlspecialchars($item['room_number'] ?? 'N/A'); ?></small>
                                </div>
                                <div class="col-md-2">
                                    <strong><?php echo format_currency($item['total_price']); ?></strong>
                                    <br><small class="text-muted"><?php echo $item['payment_method_name'] ? ucfirst($item['payment_method_name']) : 'N/A'; ?></small>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Uploaded:</small>
                                    <br><?php echo $item['screenshot_uploaded_at'] ? date('M j, g:i A', strtotime($item['screenshot_uploaded_at'])) : 'Not uploaded'; ?>
                                    <?php if (isset($item['minutes_waiting']) && $item['minutes_waiting'] > 0): ?>
                                    <br><small class="text-warning"><?php echo $item['minutes_waiting']; ?> min ago</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2">
                                    <?php
                                    $status_class = 'secondary';
                                    $status_text = ucfirst(str_replace('_', ' ', $item['verification_status']));
                                    
                                    switch($item['verification_status']) {
                                        case 'pending_payment':
                                            $status_class = 'warning';
                                            break;
                                        case 'pending_verification':
                                            $status_class = 'info';
                                            break;
                                        case 'rejected':
                                            $status_class = 'danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    <br><small class="text-muted"><?php echo $item['priority_status']; ?></small>
                                </div>
                                <div class="col-md-1">
                                    <?php if ($item['verification_status'] == 'pending_verification'): ?>
                                    <a href="payment-verification.php?action=review&booking=<?php echo $item['booking_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                    <?php else: ?>
                                    <a href="payment-verification.php?action=review&booking=<?php echo $item['booking_id']; ?>" 
                                       class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-info"></i> View
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($action == 'review' && $booking): ?>
            <!-- Review View -->
            <div class="row mb-3">
                <div class="col-12">
                    <a href="payment-verification.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Queue
                    </a>
                    <h2 class="d-inline ms-3"><i class="fas fa-search text-primary"></i> Payment Review</h2>
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
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Booking Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle"></i> <?php echo $booking['booking_type'] === 'food_order' ? 'Food Order Details' : 'Booking Details'; ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong><?php echo $booking['booking_type'] === 'food_order' ? 'Order Reference:' : 'Booking Reference:'; ?></strong> <?php echo $booking['booking_reference']; ?></p>
                                    <p><strong>Customer:</strong> <?php echo $booking['customer_name']; ?></p>
                                    <p><strong>Email:</strong> <?php echo $booking['customer_email']; ?></p>
                                    <p><strong>Phone:</strong> <?php echo $booking['customer_phone']; ?></p>
                                    <?php if ($booking['booking_type'] !== 'food_order'): ?>
                                    <p><strong>Room:</strong> <?php echo $booking['room_name']; ?> (<?php echo $booking['room_number']; ?>)</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($booking['booking_type'] === 'food_order'): ?>
                                    <p><strong>Order Type:</strong> Food Order</p>
                                    <p><strong>Order Date:</strong> <?php echo $booking['created_at'] ? date('M j, Y g:i A', strtotime($booking['created_at'])) : 'N/A'; ?></p>
                                    <?php else: ?>
                                    <p><strong>Check-in:</strong> <?php echo $booking['check_in_date'] ? format_date($booking['check_in_date']) : 'N/A'; ?></p>
                                    <p><strong>Check-out:</strong> <?php echo $booking['check_out_date'] ? format_date($booking['check_out_date']) : 'N/A'; ?></p>
                                    <?php endif; ?>
                                    <p><strong>Customers/Quantity:</strong> <?php echo $booking['customers']; ?></p>
                                    <p><strong>Total Amount:</strong> <span class="h5 text-success"><?php echo format_currency($booking['total_price']); ?></span></p>
                                    <p><strong>Payment Reference:</strong> <code><?php echo $booking['payment_reference']; ?></code></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Transaction ID -->
                    <?php if ($booking['transaction_id']): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-receipt"></i> Transaction ID</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h6 class="mb-2">Payment Transaction ID:</h6>
                                <code class="fs-5"><?php echo htmlspecialchars($booking['transaction_id']); ?></code>
                            </div>
                            <p class="mb-0">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Submitted: <?php echo date('F j, Y g:i A', strtotime($booking['screenshot_uploaded_at'])); ?>
                                </small>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Verification Log -->
                    <?php if (!empty($verification_log)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Verification History</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($verification_log as $log): ?>
                            <div class="log-entry <?php echo str_replace('verification_', '', $log['action_type']); ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php
                                            $action_icons = [
                                                'screenshot_uploaded' => 'fas fa-upload text-info',
                                                'verification_approved' => 'fas fa-check-circle text-success',
                                                'verification_rejected' => 'fas fa-times-circle text-danger',
                                                'payment_expired' => 'fas fa-clock text-secondary'
                                            ];
                                            ?>
                                            <i class="<?php echo $action_icons[$log['action_type']] ?? 'fas fa-info-circle'; ?>"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $log['action_type'])); ?>
                                        </h6>
                                        <?php if ($log['verification_notes']): ?>
                                        <p class="mb-1">
                                            <?php 
                                            // Check if notes are JSON and decode them
                                            $notes = $log['verification_notes'];
                                            $decoded = json_decode($notes, true);
                                            
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                // It's JSON, format it nicely
                                                if (isset($decoded['verified']) && $decoded['verified']) {
                                                    echo "Payment automatically verified successfully.";
                                                } elseif (isset($decoded['message'])) {
                                                    // Simplify technical error messages
                                                    $message = $decoded['message'];
                                                    if (strpos($message, 'CURL Error') !== false || strpos($message, 'Could not resolve host') !== false) {
                                                        echo "Automatic verification unavailable. Requires manual verification.";
                                                    } else {
                                                        echo htmlspecialchars($message);
                                                    }
                                                } elseif (isset($decoded['requires_manual_verification']) && $decoded['requires_manual_verification']) {
                                                    echo "Automatic verification unavailable. Requires manual verification.";
                                                } else {
                                                    echo "Verification pending. Requires manual review.";
                                                }
                                            } else {
                                                // It's plain text - check for technical errors and simplify
                                                if (strpos($notes, 'CURL Error') !== false || 
                                                    strpos($notes, 'Could not resolve host') !== false ||
                                                    strpos($notes, 'api.ethiotelecom.et') !== false ||
                                                    strpos($notes, 'Connection failed') !== false ||
                                                    strpos($notes, 'timeout') !== false) {
                                                    echo "Automatic verification unavailable. Requires manual verification.";
                                                } elseif (strpos($notes, 'Verification failed') !== false) {
                                                    echo "Automatic verification failed. Requires manual verification.";
                                                } else {
                                                    // Display normal notes as is
                                                    echo nl2br(htmlspecialchars($notes));
                                                }
                                            }
                                            ?>
                                        </p>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?php echo $log['performed_by_name'] ? 'by ' . $log['performed_by_name'] : 'System'; ?>
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M j, g:i A', strtotime($log['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-lg-4">
                    <!-- Payment Method Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-university"></i> Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Method:</strong> <?php echo $booking['method_name']; ?></p>
                            <p><strong>Bank:</strong> <?php echo $booking['bank_name']; ?></p>
                            <p><strong>Account:</strong> <?php echo $booking['account_number']; ?></p>
                            
                            <?php if ($booking['verification_tips']): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-lightbulb"></i> Verification Tips</h6>
                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($booking['verification_tips'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Verification Actions -->
                    <?php if ($booking['verification_status'] == 'pending_verification'): ?>
                    <div class="verification-actions">
                        <h5><i class="fas fa-gavel"></i> Verification Decision</h5>
                        
                        <form method="POST" id="verificationForm">
                            <div class="mb-3">
                                <label class="form-label">Admin Notes</label>
                                <textarea name="admin_notes" class="form-control" rows="3" 
                                         placeholder="Add notes about your verification decision..."></textarea>
                            </div>
                            
                            <div id="rejectionReason" class="mb-3" style="display: none;">
                                <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                                <select name="rejection_reason" id="rejectionReasonSelect" class="form-select" onchange="handleRejectionReasonChange()">
                                    <option value="">Select reason...</option>
                                    <option value="Incorrect amount">Incorrect amount</option>
                                    <option value="Missing payment reference">Missing payment reference</option>
                                    <option value="Wrong account/recipient">Wrong account/recipient</option>
                                    <option value="Screenshot unclear/unreadable">Screenshot unclear/unreadable</option>
                                    <option value="Transaction not successful">Transaction not successful</option>
                                    <option value="Duplicate/fake screenshot">Duplicate/fake screenshot</option>
                                    <option value="Other">Other (specify in notes)</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success btn-lg" onclick="submitVerification('approve')">
                                    <i class="fas fa-check"></i> Approve Payment
                                </button>
                                <button type="button" class="btn btn-danger btn-lg" onclick="submitVerification('reject')">
                                    <i class="fas fa-times"></i> Reject Payment
                                </button>
                            </div>
                            
                            <input type="hidden" name="verification_action" id="verificationAction">
                        </form>
                    </div>
                    <?php elseif ($booking['verification_status'] == 'verified'): ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle"></i> Payment Verified</h6>
                        <p class="mb-1">This payment has been approved and the booking is confirmed.</p>
                        <small>Verified by: <?php echo $booking['verified_by_name']; ?></small><br>
                        <small>Date: <?php echo date('F j, Y g:i A', strtotime($booking['verified_at'])); ?></small>
                    </div>
                    <?php elseif ($booking['verification_status'] == 'rejected'): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times-circle"></i> Payment Rejected</h6>
                        <p class="mb-1">This payment was rejected.</p>
                        <?php if ($booking['rejection_reason']): ?>
                        <p class="mb-1"><strong>Reason:</strong> <?php echo htmlspecialchars($booking['rejection_reason']); ?></p>
                        <?php endif; ?>
                        <small>Rejected by: <?php echo $booking['verified_by_name']; ?></small><br>
                        <small>Date: <?php echo date('F j, Y g:i A', strtotime($booking['verified_at'])); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Proof</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Payment Proof" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }
        
        function submitVerification(action) {
            const form = document.getElementById('verificationForm');
            const actionInput = document.getElementById('verificationAction');
            const rejectionReason = document.getElementById('rejectionReason');
            const rejectionSelect = document.querySelector('select[name="rejection_reason"]');
            
            if (action === 'reject') {
                // Show rejection reason dropdown first
                rejectionReason.style.display = 'block';
                rejectionSelect.required = true;
                
                // Check if reason is already selected
                if (!rejectionSelect.value) {
                    // If not selected, don't submit yet - let user select
                    return;
                }
                
                // If reason is selected, confirm and submit
                if (confirm('Are you sure you want to reject this payment?')) {
                    actionInput.value = action;
                    form.submit();
                }
            } else {
                // For approve action
                rejectionReason.style.display = 'none';
                rejectionSelect.required = false;
                
                if (confirm('Are you sure you want to approve this payment?')) {
                    actionInput.value = action;
                    form.submit();
                }
            }
        }
        
        function handleRejectionReasonChange() {
            const rejectionSelect = document.getElementById('rejectionReasonSelect');
            if (rejectionSelect.value) {
                // Automatically trigger submit when reason is selected
                submitVerification('reject');
            }
        }
        
        // Auto-refresh every 30 seconds on list view
        <?php if ($action == 'list'): ?>
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>