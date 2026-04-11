<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$booking_id = $_GET['booking'] ?? 0;

if (!$booking_id) {
    header('Location: my-bookings.php');
    exit;
}

// Get booking details
$query = "SELECT b.*, 
          COALESCE(r.name, 'Service Booking') as room_name,
          COALESCE(r.room_number, 'N/A') as room_number,
          u.email, u.first_name, u.last_name
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: my-bookings.php?error=booking_not_found');
    exit;
}

// Check if payment is already completed
if ($booking['payment_status'] === 'paid') {
    header('Location: booking-confirmation.php?booking=' . $booking_id);
    exit;
}

$page_title = "Payment Submission - " . $booking['booking_reference'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <section class="py-5 bg-light">
        <div class="container">
            <!-- Booking Summary -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-receipt"></i> Booking Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Booking Reference:</strong><br><?php echo $booking['booking_reference']; ?></p>
                                    <p><strong>Customer:</strong><br><?php echo $booking['customer_name']; ?></p>
                                    <?php if ($booking['booking_type'] === 'room'): ?>
                                        <p><strong>Room:</strong><br><?php echo $booking['room_name'] . ' (' . $booking['room_number'] . ')'; ?></p>
                                    <?php else: ?>
                                        <p><strong>Service:</strong><br><?php echo ucfirst(str_replace('_', ' ', $booking['booking_type'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($booking['check_in_date']): ?>
                                        <p><strong>Check-in:</strong><br><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></p>
                                        <p><strong>Check-out:</strong><br><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Total Amount:</strong><br><span class="h5 text-success"><?php echo number_format($booking['total_price'], 2); ?> ETB</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Component -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body p-4">
                            <?php include 'includes/payment-component.php'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Information -->
            <?php if ($booking['verification_status'] === 'pending_verification'): ?>
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8">
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle"></i> Payment Submitted Successfully</h6>
                        <p class="mb-2">Your payment screenshot has been uploaded successfully. Please wait while we verify your payment. Once it is confirmed, we will send a verification message to your email: <strong><?php echo htmlspecialchars($booking['email'] ?? 'your registered email'); ?></strong></p>
                        <p class="mb-0">You will receive a verification message at your email address once your payment is verified (usually within 30 minutes).</p>
                        <?php if ($booking['screenshot_uploaded_at']): ?>
                            <small class="text-muted">Submitted: <?php echo date('M d, Y g:i A', strtotime($booking['screenshot_uploaded_at'])); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8 text-center">
                    <a href="my-bookings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to My Bookings
                    </a>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>