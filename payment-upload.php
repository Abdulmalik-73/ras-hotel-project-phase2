<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/RoomLockManager.php';

// Initialize Room Lock Manager
$lockManager = new RoomLockManager($conn);

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php?redirect=payment-upload');
    exit();
}

$booking_id = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
$error = '';
$success = '';
$feedback_success = '';
$feedback_error = '';

// Debug: Log page access
error_log("Payment upload page accessed with booking ID: " . $booking_id . " by user: " . ($_SESSION['user_id'] ?? 'not logged in'));

if (!$booking_id) {
    error_log("No booking ID provided, redirecting to index");
    header('Location: index.php');
    exit();
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    // Check if feedback already exists for this booking
    $check_feedback = "SELECT id FROM customer_feedback WHERE booking_id = ?";
    $check_stmt = $conn->prepare($check_feedback);
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();
    $existing_feedback = $check_stmt->get_result();
    
    if ($existing_feedback->num_rows > 0) {
        // Feedback already submitted
    } else {
        $overall_rating = isset($_POST['overall_rating']) ? (int)$_POST['overall_rating'] : 0;
        $service_quality = isset($_POST['service_quality']) ? (int)$_POST['service_quality'] : 0;
        $cleanliness = isset($_POST['cleanliness']) ? (int)$_POST['cleanliness'] : 0;
        $comments = sanitize_input($_POST['comments'] ?? '');
        $service_type = sanitize_input($_POST['service_type'] ?? '');
        $booking_type = sanitize_input($_POST['booking_type'] ?? 'room');
        
        // Validate ratings (must be between 1 and 5)
        if ($overall_rating >= 1 && $overall_rating <= 5 && 
            $service_quality >= 1 && $service_quality <= 5 && 
            $cleanliness >= 1 && $cleanliness <= 5) {
            
            // Insert feedback into database
            $feedback_query = "INSERT INTO customer_feedback (booking_id, customer_id, payment_id, overall_rating, service_quality, cleanliness, comments, booking_type, service_type, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $feedback_stmt = $conn->prepare($feedback_query);
            $feedback_stmt->bind_param("iiiiissss", $booking_id, $_SESSION['user_id'], $booking_id, $overall_rating, $service_quality, $cleanliness, $comments, $booking_type, $service_type);
            
            if ($feedback_stmt->execute()) {
                $feedback_success = 'Your response is submitted successfully! Thank you for your feedback.';
                
                // Log the feedback submission
                error_log("Feedback submitted - Booking ID: $booking_id, Overall: $overall_rating, Service: $service_quality, Cleanliness: $cleanliness");
            }
        }
    }
}

// Get booking details (including food orders)
$query = "SELECT b.*, 
          COALESCE(r.name, 'Food Order') as room_name, 
          COALESCE(r.room_number, 'N/A') as room_number, 
          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
          u.email as email,
          fo.order_reference as food_order_ref,
          fo.table_reservation,
          fo.reservation_date,
          fo.reservation_time,
          fo.guests as food_guests
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id 
          JOIN users u ON b.user_id = u.id 
          LEFT JOIN food_orders fo ON b.id = fo.booking_id
          WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: index.php');
    exit();
}

// Get food order items if this is a food order
$food_items = [];
if ($booking['booking_type'] == 'food_order') {
    $items_query = "SELECT * FROM food_order_items WHERE order_id = (SELECT id FROM food_orders WHERE booking_id = ?)";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param("i", $booking_id);
    $items_stmt->execute();
    $food_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get service booking details if this is a service booking
$service_details = null;
if (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])) {
    $service_query = "SELECT * FROM service_bookings WHERE booking_id = ?";
    $service_stmt = $conn->prepare($service_query);
    $service_stmt->bind_param("i", $booking_id);
    $service_stmt->execute();
    $service_details = $service_stmt->get_result()->fetch_assoc();
}

// Check if feedback already exists for this booking
$feedback_exists = false;
$check_feedback_query = "SELECT id FROM customer_feedback WHERE booking_id = ?";
$check_feedback_stmt = $conn->prepare($check_feedback_query);
$check_feedback_stmt->bind_param("i", $booking_id);
$check_feedback_stmt->execute();
$feedback_result = $check_feedback_stmt->get_result();
if ($feedback_result->num_rows > 0) {
    $feedback_exists = true;
}

// Generate payment reference if not exists
if (empty($booking['payment_reference'])) {
    $payment_ref = 'HRH-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($booking_id . time()), 0, 6));
    $deadline = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $update_query = "UPDATE bookings SET payment_reference = ?, payment_deadline = ?, verification_status = 'pending_payment' WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssi", $payment_ref, $deadline, $booking_id);
    $update_stmt->execute();
    
    $booking['payment_reference'] = $payment_ref;
    $booking['payment_deadline'] = $deadline;
    $booking['verification_status'] = 'pending_payment';
}

// Get payment method instructions
$payment_methods_query = "SELECT * FROM payment_method_instructions WHERE is_active = 1 ORDER BY display_order, method_name";
$payment_methods = $conn->query($payment_methods_query);

if (!$payment_methods) {
    error_log("Failed to fetch payment methods: " . $conn->error);
    $error = 'Unable to load payment methods. Please try again.';
    $payment_methods = [];
} else {
    $payment_methods = $payment_methods->fetch_all(MYSQLI_ASSOC);
    error_log("Loaded " . count($payment_methods) . " payment methods");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error) && !isset($_POST['submit_feedback'])) {
    $payment_method = sanitize_input($_POST['payment_method']);
    $transaction_id = sanitize_input($_POST['transaction_id']);
    
    // Validate transaction ID
    if (empty($transaction_id)) {
        $error = 'Please enter your transaction ID.';
    } elseif (!preg_match('/^[A-Za-z0-9\-]+$/', $transaction_id)) {
        $error = 'Invalid transaction ID format. Only letters, numbers, and hyphens are allowed.';
    } elseif (strlen($transaction_id) < 5) {
        $error = 'Transaction ID is too short. Please enter a valid transaction ID.';
    } else {
        // Automatically verify transaction with payment gateway API
        require_once 'includes/services/PaymentVerificationService.php';
        $verificationService = new PaymentVerificationService($conn);
        
        $verification_result = $verificationService->verifyTransaction(
            $transaction_id,
            $payment_method,
            $booking['total_price'],
            $booking['payment_reference']
        );
        
        // Determine verification status based on API response
        if ($verification_result['verified'] === true) {
            // Transaction verified successfully - auto-approve
            $verification_status = 'verified';
            $success = 'Payment verified successfully! Your booking is confirmed.';
        } elseif ($verification_result['verified'] === 'pending' || isset($verification_result['requires_manual_verification'])) {
            // Gateway not configured or needs manual check
            $verification_status = 'pending_verification';
            $success = 'Transaction ID submitted successfully! Your payment is being verified.';
        } else {
            // Verification failed
            $error = 'Transaction verification failed: ' . ($verification_result['message'] ?? 'Invalid transaction');
            
            // Still save the transaction ID for manual review
            $verification_status = 'pending_verification';
        }
        
        if (empty($error)) {
            // Update booking with transaction ID and verification status
            $update_query = "UPDATE bookings SET 
                            payment_method = ?, 
                            transaction_id = ?, 
                            screenshot_uploaded_at = NOW(), 
                            verification_status = ?,
                            verified_at = " . ($verification_status === 'verified' ? 'NOW()' : 'NULL') . ",
                            payment_status = " . ($verification_status === 'verified' ? "'paid'" : "'pending'") . "
                            WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssi", $payment_method, $transaction_id, $verification_status, $booking_id);
            
            if ($update_stmt->execute()) {
                // Update room status to "waiting" for room bookings
                if ($booking['booking_type'] == 'room' && !empty($booking['room_id'])) {
                    $room_status_query = "UPDATE rooms SET status = 'booked' WHERE id = ?";
                    $room_status_stmt = $conn->prepare($room_status_query);
                    $room_status_stmt->bind_param("i", $booking['room_id']);
                    $room_status_stmt->execute();
                    error_log("Room status updated to 'booked' for room ID: " . $booking['room_id']);
                }
                
                // Log the transaction ID submission with verification result
                $log_query = "INSERT INTO payment_verification_log 
                             (booking_id, payment_reference, action_type, performed_by, transaction_id, bank_method, verification_notes, ip_address, user_agent) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $log_stmt = $conn->prepare($log_query);
                $action_type = $verification_status === 'verified' ? 'verification_approved' : 'transaction_id_submitted';
                
                // Create human-readable verification notes
                if (isset($verification_result['verified']) && $verification_result['verified']) {
                    $verification_notes = "Payment automatically verified successfully.";
                } elseif (isset($verification_result['message'])) {
                    $verification_notes = $verification_result['message'];
                } else {
                    $verification_notes = "Transaction ID submitted. Awaiting manual verification.";
                }
                
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_stmt->bind_param("isissssss", $booking_id, $booking['payment_reference'], $action_type, $_SESSION['user_id'], $transaction_id, $payment_method, $verification_notes, $ip_address, $user_agent);
                $log_stmt->execute();
                
                // Add to verification queue only if not auto-verified
                if ($verification_status !== 'verified') {
                    $queue_query = "INSERT INTO payment_verification_queue 
                                   (booking_id, payment_reference, customer_name, room_name, total_amount, payment_method, transaction_id, uploaded_at, priority) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'normal')";
                    
                    $queue_stmt = $conn->prepare($queue_query);
                    $queue_stmt->bind_param("isssdss", $booking_id, $booking['payment_reference'], $booking['customer_name'], $booking['room_name'], $booking['total_price'], $payment_method, $transaction_id);
                    $queue_stmt->execute();
                } else {
                    // Payment verified - release room lock and promote next in queue
                    if (isset($_SESSION['room_lock_id'])) {
                        $lockManager->releaseRoomLock($_SESSION['room_lock_id'], 'completed');
                        unset($_SESSION['room_lock_id']);
                    }
                }
                
                // Refresh booking data
                $stmt->execute();
                $booking = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update booking. Please try again.';
            }
        }
    }
}

// Check if payment deadline has passed
$deadline_passed = false;
if ($booking['payment_deadline'] && strtotime($booking['payment_deadline']) < time()) {
    $deadline_passed = true;
    if ($booking['verification_status'] == 'pending_payment') {
        // Auto-expire the booking and release lock
        $expire_query = "UPDATE bookings SET verification_status = 'expired' WHERE id = ?";
        $expire_stmt = $conn->prepare($expire_query);
        $expire_stmt->bind_param("i", $booking_id);
        $expire_stmt->execute();
        $booking['verification_status'] = 'expired';
        
        // Release room lock
        if (isset($_SESSION['room_lock_id'])) {
            $lockManager->releaseRoomLock($_SESSION['room_lock_id'], 'expired');
            unset($_SESSION['room_lock_id']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Upload - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payment-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        
        .payment-method-card {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .payment-method-card:hover {
            border-color: #007bff;
            background-color: #f0f8ff;
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.2);
        }
        
        .payment-method-card.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
            box-shadow: 0 4px 12px rgba(0,123,255,0.3);
        }
        
        .payment-instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .payment-instructions h6 {
            color: #856404;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .payment-instructions p {
            margin-bottom: 10px;
            color: #333;
        }
        
        .payment-instructions strong {
            color: #856404;
            font-weight: 600;
        }
        
        .instructions-steps {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ffeaa7;
        }
        
        .instructions-steps p {
            padding-left: 10px;
            margin-bottom: 8px;
        }
        
        .verification-tips {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .verification-tips h6 {
            color: #0c5460;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .verification-tips p {
            color: #0c5460;
            margin-bottom: 0;
        }
        
        .deadline-warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .star-rating-container {
            display: flex;
            gap: 8px;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .star {
            cursor: pointer;
            color: #ddd;
            transition: all 0.2s ease;
            user-select: none;
        }
        
        .star:hover {
            color: #ffc107;
            transform: scale(1.15);
        }
        
        .star.active {
            color: #ffc107;
        }
        
        .star-rating-container.rated {
            animation: pulse 0.3s ease;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .rating-text {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
            min-height: 20px;
        }
        
        .status-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
        
        .countdown {
            font-weight: bold;
            color: #dc3545;
        }
        
        /* Feedback Form Responsive Styles */
        @media (max-width: 768px) {
            .feedback-form-wrapper {
                padding: 0 10px !important;
            }
            .feedback-form-container {
                padding: 15px !important;
            }
            .star-rating {
                font-size: 28px !important;
                gap: 6px !important;
            }
            .rating-group {
                padding: 10px !important;
            }
        }
        
        @media (max-width: 480px) {
            .star-rating {
                font-size: 24px !important;
                gap: 4px !important;
            }
        }
        
        /* Star button hover effect */
        .star-btn:hover {
            transform: scale(1.2) !important;
        }
        
        .star-btn:active {
            transform: scale(1.1) !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="my-bookings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to My Bookings
                    </a>
                </div>
                <div class="col text-center">
                    <h2 class="mb-0">Payment Upload</h2>
                    <p class="text-muted mb-0">Submit your transaction ID for verification</p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-credit-card"></i> Payment Upload
                            </h4>
                        </div>
                        <div class="card-body">
                            <!-- Booking Details -->
                            <div class="payment-card">
                                <h5><i class="fas fa-info-circle text-primary"></i> 
                                    <?php 
                                    if ($booking['booking_type'] == 'food_order') {
                                        echo 'Food Order Details';
                                    } elseif ($booking['booking_type'] == 'spa_service') {
                                        echo 'Spa & Wellness Service Details';
                                    } elseif ($booking['booking_type'] == 'laundry_service') {
                                        echo 'Laundry Service Details';
                                    } else {
                                        echo 'Booking Details';
                                    }
                                    ?>
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <?php if ($booking['booking_type'] == 'food_order'): ?>
                                            <p><strong>Food Type:</strong> 
                                                <?php 
                                                if (!empty($food_items)) {
                                                    $food_names = array_map(function($item) {
                                                        return $item['item_name'];
                                                    }, $food_items);
                                                    echo htmlspecialchars(implode(', ', $food_names));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </p>
                                            <p><strong>Guests:</strong> <?php echo $booking['food_guests']; ?></p>
                                            <?php if ($booking['table_reservation']): ?>
                                                <p><strong>Table Reserved:</strong> Yes</p>
                                                <p><strong>Date:</strong> <?php echo $booking['reservation_date'] ? format_date($booking['reservation_date']) : 'Not specified'; ?></p>
                                                <p><strong>Time:</strong> <?php echo $booking['reservation_time'] ?: 'Not specified'; ?></p>
                                            <?php else: ?>
                                                <p><strong>Table Reserved:</strong> No (Takeaway)</p>
                                            <?php endif; ?>
                                        <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                            <p><strong>Recreation Area:</strong> Spa & Wellness</p>
                                            <p><strong>Check-in:</strong> N/A</p>
                                            <p><strong>Check-out:</strong> N/A</p>
                                            <p><strong>Guests:</strong> 1</p>
                                        <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                            <p><strong>Our Service:</strong> Laundry Service</p>
                                            <p><strong>Check-in:</strong> N/A</p>
                                            <p><strong>Check-out:</strong> N/A</p>
                                            <p><strong>Guests:</strong> 1</p>
                                        <?php else: ?>
                                            <p><strong>Room:</strong> <?php echo $booking['room_name']; ?> (<?php echo $booking['room_number']; ?>)</p>
                                            <p><strong>Check-in:</strong> <?php echo format_date($booking['check_in_date']); ?></p>
                                            <p><strong>Check-out:</strong> <?php echo format_date($booking['check_out_date']); ?></p>
                                            <p><strong>Guests:</strong> <?php echo $booking['customers']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Total Amount:</strong> <span class="h5 text-success"><?php echo format_currency($booking['total_price']); ?></span></p>
                                        <p><strong>Payment Reference:</strong> <code><?php echo $booking['payment_reference']; ?></code></p>
                                        <p><strong>Status:</strong> 
                                            <?php
                                            $status_class = 'secondary';
                                            $status_text = ucfirst(str_replace('_', ' ', $booking['verification_status']));
                                            
                                            switch($booking['verification_status']) {
                                                case 'pending_payment':
                                                    $status_class = 'warning';
                                                    break;
                                                case 'pending_verification':
                                                    $status_class = 'info';
                                                    break;
                                                case 'verified':
                                                    $status_class = 'success';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'danger';
                                                    break;
                                                case 'expired':
                                                    $status_class = 'dark';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?> status-badge"><?php echo $status_text; ?></span>
                                        </p>
                                        <?php if ($booking['booking_type'] == 'food_order' && !empty($food_items)): ?>
                                            <div class="mt-3">
                                                <strong>Ordered Items:</strong>
                                                <ul class="small mt-2">
                                                    <?php foreach ($food_items as $item): ?>
                                                        <li><?php echo $item['item_name']; ?> × <?php echo $item['quantity']; ?> - <?php echo format_currency($item['total_price']); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php elseif ($booking['booking_type'] == 'spa_service' && $service_details): ?>
                                            <div class="mt-3">
                                                <strong>Spa Service Details:</strong>
                                                <ul class="small mt-2">
                                                    <li><strong>Service:</strong> <?php echo htmlspecialchars($service_details['service_name']); ?></li>
                                                    <li><strong>Date:</strong> <?php echo date('M j, Y', strtotime($service_details['service_date'])); ?></li>
                                                    <li><strong>Time:</strong> <?php echo date('h:i A', strtotime($service_details['service_time'])); ?></li>
                                                    <?php if ($service_details['special_requests']): ?>
                                                    <li><strong>Special Requests:</strong> <?php echo htmlspecialchars($service_details['special_requests']); ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php elseif ($booking['booking_type'] == 'laundry_service' && $service_details): ?>
                                            <div class="mt-3">
                                                <strong>Laundry Service Details:</strong>
                                                <ul class="small mt-2">
                                                    <li><strong>Service:</strong> <?php echo htmlspecialchars($service_details['service_name']); ?></li>
                                                    <li><strong>Quantity:</strong> <?php echo $service_details['quantity']; ?> <?php echo $service_details['quantity'] > 1 ? 'items' : 'item'; ?></li>
                                                    <li><strong>Pickup Date:</strong> <?php echo date('M j, Y', strtotime($service_details['service_date'])); ?></li>
                                                    <li><strong>Pickup Time:</strong> <?php echo date('h:i A', strtotime($service_details['service_time'])); ?></li>
                                                    <?php if ($service_details['special_requests']): ?>
                                                    <li><strong>Special Instructions:</strong> <?php echo htmlspecialchars($service_details['special_requests']); ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Deadline Warning -->
                            <?php if ($booking['payment_deadline'] && !$deadline_passed && $booking['verification_status'] == 'pending_payment'): ?>
                            <div class="deadline-warning">
                                <h6><i class="fas fa-clock text-danger"></i> Payment Deadline</h6>
                                <p class="mb-2">You must submit your transaction ID before: <strong><?php echo date('F j, Y g:i A', strtotime($booking['payment_deadline'])); ?></strong></p>
                                <p class="mb-0">Time remaining: <span class="countdown" id="countdown"></span></p>
                            </div>
                            <?php elseif ($deadline_passed): ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle"></i> Payment Deadline Expired</h6>
                                <p class="mb-0">The payment deadline for this booking has passed. Please contact our support team for assistance.</p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Status Messages -->
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                                    <h5 style="color: #155724; margin-bottom: 10px;">
                                        <i class="fas fa-check-circle"></i> Your transaction ID was submitted successfully! Please wait for verification.
                                    </h5>
                                    <p class="mb-0" style="font-size: 14px; color: #155724;">
                                        <strong>Note:</strong> After API integration, the system will automatically verify your payment without manual approval.
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Payment Status Display -->
                            <?php if ($booking['verification_status'] == 'pending_verification'): ?>
                            <div style="background: #e7f3ff; border: 2px solid #0c5460; border-radius: 10px; padding: 30px; margin: 20px 0;">
                                <h4 style="color: #0c5460; margin-bottom: 15px; font-weight: bold;">
                                    <i class="fas fa-clock"></i> Payment Verification Pending
                                </h4>
                                <p style="color: #0c5460; font-size: 16px; margin-bottom: 20px;">
                                    <strong>We will send the payment verification message after receptionist approves your payment</strong>
                                </p>
                                
                                <div style="background: white; padding: 20px; border-radius: 8px; margin: 15px 0;">
                                    <?php if ($booking['booking_type'] == 'food_order'): ?>
                                        <p style="margin: 8px 0;"><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Food Type:</strong> 
                                            <?php 
                                            if (!empty($food_items)) {
                                                $food_names = array_map(function($item) {
                                                    return $item['item_name'];
                                                }, $food_items);
                                                echo htmlspecialchars(implode(', ', $food_names));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </p>
                                        <p style="margin: 8px 0;"><strong>Total Paid:</strong> <?php echo format_currency($booking['total_price']); ?></p>
                                    <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                        <p style="margin: 8px 0;"><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Recreation Area:</strong> Spa & Wellness</p>
                                        <p style="margin: 8px 0;"><strong>Total Paid:</strong> <?php echo format_currency($booking['total_price']); ?></p>
                                    <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                        <p style="margin: 8px 0;"><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Our Service:</strong> Laundry Service</p>
                                        <p style="margin: 8px 0;"><strong>Total Paid:</strong> <?php echo format_currency($booking['total_price']); ?></p>
                                    <?php else: ?>
                                        <p style="margin: 8px 0;"><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Room:</strong> <?php echo $booking['room_name']; ?> - Room <?php echo $booking['room_number']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Total Paid:</strong> <?php echo format_currency($booking['total_price']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <p style="color: #0c5460; font-size: 14px; margin-top: 15px;">
                                    Once approved, you will receive a confirmation email at <strong><?php echo $booking['email']; ?></strong>
                                </p>
                                
                                <?php if ($booking['payment_screenshot']): ?>
                                <p class="mt-3">
                                    <strong>Transaction ID:</strong> <code><?php echo htmlspecialchars($booking['transaction_id']); ?></code>
                                </p>
                                <?php endif; ?>
                                
                                <!-- Customer Feedback Form -->
                                <?php if (!$feedback_exists): ?>
                                <div class="feedback-form-wrapper" style="max-width: 500px; margin: 20px auto; padding: 0 15px;">
                                    <div class="feedback-form-container" style="background: #ffffff; border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                        <div style="text-align: center; margin-bottom: 15px;">
                                            <h5 style="margin: 0 0 5px 0; font-size: 18px; color: #333; font-weight: 600;">
                                                <i class="fas fa-star" style="color: #ffc107;"></i> Share Your Feedback
                                            </h5>
                                            <p style="margin: 0; font-size: 13px; color: #666;">Help us improve your experience</p>
                                        </div>
                                    
                                    <?php if ($feedback_success): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="padding: 10px 15px; font-size: 13px; margin-bottom: 15px; border-radius: 8px;">
                                        <i class="fas fa-check-circle"></i> <?php echo $feedback_success; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!$feedback_success): ?>
                                    <form method="POST" action="" id="feedbackForm" onsubmit="return validateFeedbackForm()">
                                        <input type="hidden" name="submit_feedback" value="1">
                                        <input type="hidden" name="booking_ref" value="<?php echo htmlspecialchars($booking['booking_reference']); ?>">
                                        <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($booking['payment_reference']); ?>">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                        <input type="hidden" name="service_type" value="<?php echo $service_details ? htmlspecialchars($service_details['service_name']) : ''; ?>">
                                        <input type="hidden" name="booking_type" value="<?php echo $booking['booking_type']; ?>">
                                        
                                        <!-- Overall Rating -->
                                        <div class="rating-group" style="margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #333;">
                                                Overall Experience <span style="color: #dc3545;">*</span>
                                            </label>
                                            <div class="star-rating" id="overall_rating_stars" style="display: flex; gap: 8px; font-size: 32px; margin-bottom: 5px; justify-content: center;">
                                                <span class="star-btn" onclick="rateStar('overall_rating', 1)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('overall_rating', 2)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('overall_rating', 3)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('overall_rating', 4)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('overall_rating', 5)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                            </div>
                                            <div id="overall_rating_label" style="text-align: center; font-size: 12px; color: #666; min-height: 18px; font-weight: 500;">Click to rate</div>
                                            <input type="hidden" name="overall_rating" id="overall_rating" value="0">
                                        </div>
                                        
                                        <!-- Service Quality Rating -->
                                        <div class="rating-group" style="margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #333;">
                                                Service Quality <span style="color: #dc3545;">*</span>
                                            </label>
                                            <div class="star-rating" id="service_quality_stars" style="display: flex; gap: 8px; font-size: 32px; margin-bottom: 5px; justify-content: center;">
                                                <span class="star-btn" onclick="rateStar('service_quality', 1)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('service_quality', 2)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('service_quality', 3)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('service_quality', 4)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('service_quality', 5)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                            </div>
                                            <div id="service_quality_label" style="text-align: center; font-size: 12px; color: #666; min-height: 18px; font-weight: 500;">Click to rate</div>
                                            <input type="hidden" name="service_quality" id="service_quality" value="0">
                                        </div>
                                        
                                        <!-- Cleanliness Rating -->
                                        <div class="rating-group" style="margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #333;">
                                                Cleanliness <span style="color: #dc3545;">*</span>
                                            </label>
                                            <div class="star-rating" id="cleanliness_stars" style="display: flex; gap: 8px; font-size: 32px; margin-bottom: 5px; justify-content: center;">
                                                <span class="star-btn" onclick="rateStar('cleanliness', 1)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('cleanliness', 2)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('cleanliness', 3)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('cleanliness', 4)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                                <span class="star-btn" onclick="rateStar('cleanliness', 5)" style="cursor: pointer; color: #ddd; transition: all 0.2s; user-select: none;">★</span>
                                            </div>
                                            <div id="cleanliness_label" style="text-align: center; font-size: 12px; color: #666; min-height: 18px; font-weight: 500;">Click to rate</div>
                                            <input type="hidden" name="cleanliness" id="cleanliness" value="0">
                                        </div>
                                        
                                        <!-- Comments -->
                                        <div style="margin-bottom: 15px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #333;">
                                                Comments <span style="font-weight: 400; color: #666;">(Optional)</span>
                                            </label>
                                            <textarea name="comments" 
                                                      style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 13px; resize: vertical; transition: border-color 0.2s;" 
                                                      rows="3" 
                                                      placeholder="Share your thoughts with us..."
                                                      onfocus="this.style.borderColor='#0d6efd'"
                                                      onblur="this.style.borderColor='#ddd'"></textarea>
                                        </div>
                                        
                                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                                            <button type="submit" 
                                                    class="btn btn-primary" 
                                                    style="flex: 1; padding: 12px; font-size: 14px; font-weight: 600; border-radius: 8px; transition: all 0.2s;"
                                                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(13,110,253,0.3)'"
                                                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                                <i class="fas fa-paper-plane"></i> Submit Feedback
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary" 
                                                    onclick="skipFeedback()" 
                                                    style="flex: 1; padding: 12px; font-size: 14px; font-weight: 600; border-radius: 8px; transition: all 0.2s;"
                                                    onmouseover="this.style.transform='translateY(-2px)'"
                                                    onmouseout="this.style.transform='translateY(0)'">
                                                <i class="fas fa-forward"></i> Skip
                                            </button>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                    <div class="text-center" style="padding: 15px 0;">
                                        <a href="index.php" class="btn btn-primary" style="padding: 10px 30px; border-radius: 8px;">
                                            <i class="fas fa-home"></i> Return to Home
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div style="max-width: 500px; margin: 20px auto; padding: 0 15px;">
                                    <div style="background: #d4edda; border: 2px solid #c3e6cb; border-radius: 12px; padding: 20px; text-align: center;">
                                        <h5 style="margin: 0 0 8px 0; color: #155724; font-size: 16px; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> Feedback Submitted
                                        </h5>
                                        <p style="margin: 0; color: #155724; font-size: 13px;">Thank you for sharing your experience with us!</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="text-center mt-4">
                                    <a href="index.php" class="btn btn-dark">
                                        <i class="fas fa-arrow-left"></i> BACK TO DASHBOARD
                                    </a>
                                </div>
                            </div>
                            <?php elseif ($booking['verification_status'] == 'verified'): ?>
                            <div class="alert alert-success">
                                <h6><i class="fas fa-check-circle"></i> Payment Verified</h6>
                                <p class="mb-2">Your payment has been successfully verified and your booking is confirmed!</p>
                                <p class="mb-0"><strong>Verified:</strong> <?php echo date('F j, Y g:i A', strtotime($booking['verified_at'])); ?></p>
                                <div class="text-center mt-3">
                                    <a href="customer-feedback.php?booking_ref=<?php echo urlencode($booking['booking_reference']); ?>&payment_id=<?php echo urlencode($booking['payment_reference']); ?>" class="btn btn-primary">
                                        <i class="fas fa-star me-2"></i> Share Your Feedback
                                    </a>
                                    <a href="booking-confirmation.php?booking_ref=<?php echo urlencode($booking['booking_reference']); ?>" class="btn btn-outline-primary ms-2">
                                        <i class="fas fa-forward me-2"></i> Skip to Confirmation
                                    </a>
                                </div>
                            </div>
                            <?php elseif ($booking['verification_status'] == 'rejected'): ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-times-circle"></i> Payment Rejected</h6>
                                <p class="mb-2">Your transaction ID was rejected. Please submit a new transaction ID with the correct information.</p>
                                <?php if ($booking['rejection_reason']): ?>
                                <p class="mb-0"><strong>Reason:</strong> <?php echo htmlspecialchars($booking['rejection_reason']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Payment Upload Form -->
                            <?php if (in_array($booking['verification_status'], ['pending_payment', 'rejected']) && !$deadline_passed): ?>
                            
                            <!-- Chapa Online Payment Option -->
                            <div class="mb-4">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="fas fa-credit-card"></i> Pay Online with Chapa</h5>
                                    </div>
                                    <div class="card-body text-center py-4">
                                        <p class="mb-3">Pay securely using Mobile Money, Bank Transfer, or Card</p>
                                        <button type="button" id="chapaPayBtn" class="btn btn-success btn-lg">
                                            <i class="fas fa-lock"></i> Pay Now - <?php echo format_currency($booking['total_price']); ?>
                                        </button>
                                        <p class="text-muted mt-2 mb-0"><small><i class="fas fa-shield-alt"></i> Secure payment powered by Chapa</small></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center my-4">
                                <p class="text-muted">— OR —</p>
                                <h6>Pay Manually and Upload Transaction ID</h6>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" id="paymentForm">
                                <!-- Payment Method Selection -->
                                <div class="mb-4">
                                    <h5 style="margin-bottom: 20px;"><i class="fas fa-university text-primary"></i> Select Payment Method</h5>
                                    <div class="row" id="paymentMethodsContainer">
                                        <?php foreach ($payment_methods as $method): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="payment-method-card" data-method-code="<?php echo $method['method_code']; ?>" style="cursor: pointer; border: 2px solid #ddd; border-radius: 10px; padding: 20px; transition: all 0.3s; background: white; text-align: center;">
                                                <i class="fas fa-university" style="font-size: 32px; color: #007bff; margin-bottom: 15px; display: block;"></i>
                                                <h6 style="margin-bottom: 8px; font-weight: 600; font-size: 15px;"><?php echo htmlspecialchars($method['method_name']); ?></h6>
                                                <small style="color: #666; display: block; margin-bottom: 5px;"><?php echo htmlspecialchars($method['bank_name']); ?></small>
                                                <?php if ($method['mobile_number']): ?>
                                                <small style="color: #007bff; display: block; margin-top: 8px;">
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($method['mobile_number']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                            <input type="radio" name="payment_method" value="<?php echo $method['method_code']; ?>" id="method_<?php echo $method['method_code']; ?>" style="display: none;">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Payment Instructions -->
                                <div id="paymentInstructions" style="display: none; margin-top: 20px; padding: 25px; background: #f8f9fa; border-radius: 10px; border: 2px solid #007bff;">
                                    <h5 style="color: #007bff; margin-bottom: 20px;"><i class="fas fa-info-circle"></i> Payment Details & Instructions</h5>
                                    <div id="instructionsContent"></div>
                                </div>
                                
                                <!-- Transaction ID Input -->
                                <div class="mb-4">
                                    <h5><i class="fas fa-receipt text-primary"></i> Enter Transaction ID</h5>
                                    <div class="form-group">
                                        <label for="transactionId" class="form-label">Transaction ID <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               name="transaction_id" 
                                               id="transactionId" 
                                               class="form-control form-control-lg" 
                                               placeholder="e.g., TXN123456789ETH or CBE-2024-001234567" 
                                               required
                                               pattern="[A-Za-z0-9\-]+"
                                               title="Transaction ID should contain only letters, numbers, and hyphens">
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> Enter the transaction ID from your payment confirmation
                                        </div>
                                    </div>
                                    
                                    <!-- Verification Tips -->
                                    <div class="verification-tips mt-3">
                                        <h6><i class="fas fa-lightbulb"></i> Verification Tips</h6>
                                        <p class="mb-0">Ensure your transaction ID is correct and matches your payment confirmation.</p>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                        <i class="fas fa-check-circle"></i> Submit Transaction ID
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                            
                            <!-- Help Section -->
                            <div class="mt-5">
                                <h5><i class="fas fa-question-circle text-primary"></i> Need Help?</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Payment Issues</h6>
                                        <p>If you're having trouble with payment, please contact our support team:</p>
                                        <p><i class="fas fa-phone"></i> +251-911-123-456<br>
                                           <i class="fas fa-envelope"></i> support@hararrashotel.com</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Transaction ID Requirements</h6>
                                        <ul class="small">
                                            <li>Must be the exact transaction ID from your payment confirmation</li>
                                            <li>Common formats: TXN123456789ETH, CBE-2024-001234567, TB-ETH-20240315-123456</li>
                                            <li>Include payment reference: <code><?php echo $booking['payment_reference']; ?></code> when making payment</li>
                                            <li>Transaction must match the exact amount (<?php echo format_currency($booking['total_price']); ?>)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment method instructions data
        var paymentMethods = <?php echo json_encode($payment_methods); ?>;
        
        // Attach click handlers to payment cards
        document.addEventListener('DOMContentLoaded', function() {
            var cards = document.querySelectorAll('.payment-method-card');
            cards.forEach(function(card) {
                card.addEventListener('click', function() {
                    var methodCode = this.getAttribute('data-method-code');
                    selectPaymentMethod(methodCode);
                });
            });
        });
        
        // Select payment method function
        function selectPaymentMethod(methodCode) {
            // Remove selected class from all cards
            var cards = document.querySelectorAll('.payment-method-card');
            cards.forEach(function(card) {
                card.classList.remove('selected');
                card.style.borderColor = '#ddd';
                card.style.backgroundColor = 'white';
                card.style.boxShadow = 'none';
                card.style.transform = 'none';
            });
            
            // Add selected class to clicked card
            var selectedCard = document.querySelector('.payment-method-card[data-method-code="' + methodCode + '"]');
            if (selectedCard) {
                selectedCard.classList.add('selected');
                selectedCard.style.borderColor = '#007bff';
                selectedCard.style.backgroundColor = '#e3f2fd';
                selectedCard.style.boxShadow = '0 6px 16px rgba(0,123,255,0.4)';
                selectedCard.style.transform = 'scale(1.02)';
            }
            
            // Check the radio button
            var radio = document.getElementById('method_' + methodCode);
            if (radio) {
                radio.checked = true;
            }
            
            // Show payment instructions
            showPaymentInstructions(methodCode);
        }
        
        function showPaymentInstructions(methodCode) {
            var method = paymentMethods.find(function(m) { return m.method_code === methodCode; });
            
            if (!method) return;
            
            var instructionsDiv = document.getElementById('paymentInstructions');
            var contentDiv = document.getElementById('instructionsContent');
            
            var amount = '<?php echo $booking['total_price']; ?>';
            var reference = '<?php echo $booking['payment_reference']; ?>';
            
            var instructions = method.payment_instructions.replace(/{AMOUNT}/g, amount).replace(/{REFERENCE}/g, reference);
            var steps = instructions.split('\n').filter(function(s) { return s.trim(); }).map(function(s) { 
                return '<p style="margin: 8px 0; padding-left: 20px; line-height: 1.6;">• ' + s.trim() + '</p>'; 
            }).join('');
            
            // Check if this is a mobile payment method (TeleBirr)
            var isMobilePayment = method.method_code === 'telebirr';
            
            // Build account details section based on payment type
            var accountDetailsHtml = '';
            if (isMobilePayment) {
                // For TeleBirr - show Phone Number and Username
                accountDetailsHtml = '<table style="width: 100%; border-collapse: collapse;">' +
                    '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404; width: 40%;">Phone Number:</td><td style="padding: 8px 0; font-size: 18px; font-weight: bold; color: #000;">' + method.account_number + '</td></tr>' +
                    '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404;">Username:</td><td style="padding: 8px 0; color: #000; font-weight: 500;">' + method.account_holder_name + '</td></tr>' +
                    '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404;">Amount to Pay:</td><td style="padding: 8px 0; font-size: 20px; font-weight: bold; color: #28a745;">ETB ' + amount + '</td></tr>' +
                    '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404;">Reference Code:</td><td style="padding: 8px 0; color: #000; font-family: monospace; font-weight: bold;">' + reference + '</td></tr>' +
                    '</table>';
            } else {
                // For Bank accounts - show Account Number, Account Holder, and Mobile Number
                accountDetailsHtml = '<table style="width: 100%; border-collapse: collapse;">' +
                    '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404; width: 40%;">Account Number:</td><td style="padding: 8px 0; font-size: 18px; font-weight: bold; color: #000;">' + method.account_number + '</td></tr>' +
                    '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404;">Account Holder:</td><td style="padding: 8px 0; color: #000; font-weight: 500;">' + method.account_holder_name + '</td></tr>' +
                    (method.mobile_number ? '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404;">Mobile Number:</td><td style="padding: 8px 0; color: #000; font-weight: 500;"><i class="fas fa-phone"></i> ' + method.mobile_number + '</td></tr>' : '') +
                    '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404;">Amount to Pay:</td><td style="padding: 8px 0; font-size: 20px; font-weight: bold; color: #28a745;">ETB ' + amount + '</td></tr>' +
                    '<tr><td style="padding: 8px 0; font-weight: 600; color: #856404;">Reference Code:</td><td style="padding: 8px 0; color: #000; font-family: monospace; font-weight: bold;">' + reference + '</td></tr>' +
                    '</table>';
            }
            
            var detailsTitle = isMobilePayment ? 'Mobile Payment Details' : 'Bank Account Details';
            
            var html = '<div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">' +
                '<h5 style="color: #007bff; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #007bff; padding-bottom: 10px;">' +
                '<i class="fas fa-building"></i> ' + method.method_name + ' - ' + method.bank_name +
                '</h5>' +
                '<div style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 5px solid #ffc107; margin-bottom: 20px;">' +
                '<h6 style="color: #856404; margin-bottom: 15px; font-size: 15px;"><i class="fas fa-info-circle"></i> ' + detailsTitle + '</h6>' +
                accountDetailsHtml +
                '</div>' +
                '<div style="margin-top: 20px;">' +
                '<h6 style="color: #333; margin-bottom: 15px; font-size: 15px;"><i class="fas fa-list-ol"></i> Step-by-Step Payment Instructions:</h6>' +
                '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">' + steps + '</div>' +
                '</div>' +
                '</div>' +
                '<div style="background: #d1ecf1; padding: 20px; border-radius: 10px; border-left: 5px solid #17a2b8;">' +
                '<h6 style="color: #0c5460; margin-bottom: 12px; font-size: 15px;"><i class="fas fa-lightbulb"></i> Important Verification Tips</h6>' +
                '<p style="margin: 0; color: #0c5460; line-height: 1.6;">' + method.verification_tips + '</p>' +
                '</div>';
            
            contentDiv.innerHTML = html;
            instructionsDiv.style.display = 'block';
            
            // Smooth scroll to instructions
            setTimeout(function() {
                instructionsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }
        
        // Transaction ID validation
        const transactionInput = document.getElementById('transactionId');
        
        if (transactionInput) {
            transactionInput.addEventListener('input', function() {
                checkFormValidity();
            });
        }
        
        function checkFormValidity() {
            const methodSelected = document.querySelector('input[name="payment_method"]:checked');
            const transactionId = transactionInput ? transactionInput.value.trim() : '';
            const submitBtn = document.getElementById('submitBtn');
            
            if (submitBtn) {
                submitBtn.disabled = !(methodSelected && transactionId.length >= 5);
            }
        }
        
        // Countdown timer
        <?php if ($booking['payment_deadline'] && !$deadline_passed && $booking['verification_status'] == 'pending_payment'): ?>
        const deadline = new Date('<?php echo date('c', strtotime($booking['payment_deadline'])); ?>').getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = deadline - now;
            
            if (distance > 0) {
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('countdown').innerHTML = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else {
                document.getElementById('countdown').innerHTML = 'EXPIRED';
                location.reload(); // Reload to show expired status
            }
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
        
        
        // Star rating function - inline onclick
        function rateStar(field, rating) {
            document.getElementById(field).value = rating;
            
            var labels = {1: '⭐ Poor', 2: '⭐⭐ Fair', 3: '⭐⭐⭐ Good', 4: '⭐⭐⭐⭐ Very Good', 5: '⭐⭐⭐⭐⭐ Excellent'};
            document.getElementById(field + '_label').textContent = labels[rating];
            document.getElementById(field + '_label').style.color = '#28a745';
            
            var stars = document.getElementById(field + '_stars').getElementsByTagName('span');
            for (var i = 0; i < stars.length; i++) {
                stars[i].style.color = (i < rating) ? '#ffc107' : '#ddd';
            }
        }
        
        function validateFeedbackForm() {
            var overall = parseInt(document.getElementById('overall_rating').value) || 0;
            var service = parseInt(document.getElementById('service_quality').value) || 0;
            var cleanliness = parseInt(document.getElementById('cleanliness').value) || 0;
            
            if (overall === 0) { alert('⭐ Please rate your overall experience'); return false; }
            if (service === 0) { alert('⭐ Please rate the service quality'); return false; }
            if (cleanliness === 0) { alert('⭐ Please rate the cleanliness'); return false; }
            
            return true;
        }
        
        function skipFeedback() {
            if (confirm('Are you sure you want to skip feedback? You can provide it later.')) {
                window.location.href = 'index.php';
            }
        }
        
        // Chapa Payment Integration
        document.getElementById('chapaPayBtn')?.addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Call API to initialize payment
            fetch('api/chapa/initialize.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    booking_id: <?php echo $booking_id; ?>
                })
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Chapa API Response:', data); // Debug log
                
                if (data.success && data.checkout_url) {
                    // Redirect to Chapa checkout page
                    window.location.href = data.checkout_url;
                } else {
                    // Show error message
                    const errorMsg = data.message || 'Failed to initialize payment. Please try manual payment.';
                    alert(errorMsg);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Chapa Error:', error);
                alert('Connection error. Please check:\n1. Database is running\n2. Chapa keys are configured\n3. Try manual payment instead');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html>