<?php
// Suppress PHP warnings and notices for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('receptionist');

$message = '';
$error = '';

// Handle payment verification
if ($_POST && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    
    if ($_POST['action'] == 'approve') {
        $conn->begin_transaction();
        
        try {
            // Get current user ID and verify it exists
            $current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            
            // Verify user exists in database
            if ($current_user_id) {
                $user_check = $conn->query("SELECT id FROM users WHERE id = $current_user_id");
                if ($user_check->num_rows == 0) {
                    $current_user_id = null;
                }
            }
            
            // Update booking status - ensure all fields are set correctly
            if ($current_user_id) {
                $update_query = "UPDATE bookings SET 
                                verification_status = 'verified',
                                payment_status = 'paid',
                                status = 'confirmed',
                                verified_by = ?,
                                verified_at = NOW()
                                WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ii", $current_user_id, $booking_id);
            } else {
                // If no valid user, set verified_by to NULL
                $update_query = "UPDATE bookings SET 
                                verification_status = 'verified',
                                payment_status = 'paid',
                                status = 'confirmed',
                                verified_by = NULL,
                                verified_at = NOW()
                                WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("i", $booking_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update booking status: " . $stmt->error);
            }
            
            // Log the booking activity
            $booking_query_for_log = "SELECT user_id FROM bookings WHERE id = ?";
            $log_stmt = $conn->prepare($booking_query_for_log);
            $log_stmt->bind_param("i", $booking_id);
            $log_stmt->execute();
            $log_result = $log_stmt->get_result();
            if ($log_row = $log_result->fetch_assoc()) {
                log_booking_activity(
                    $booking_id, 
                    $log_row['user_id'], 
                    'confirmed', 
                    'pending', 
                    'confirmed', 
                    'Payment verified and booking confirmed by receptionist', 
                    $current_user_id
                );
            }
            
            // Get booking details to check if it's a walk-in booking
            $booking_query = "SELECT b.*, 
                             COALESCE(r.name, 'Food Order') as room_name, 
                             COALESCE(r.room_number, 'N/A') as room_number, 
                             u.email, u.first_name, u.id as user_id,
                             b.created_at, b.screenshot_uploaded_at
                             FROM bookings b 
                             LEFT JOIN rooms r ON b.room_id = r.id 
                             JOIN users u ON b.user_id = u.id 
                             WHERE b.id = ?";
            $stmt = $conn->prepare($booking_query);
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();
            
            // Check if this is a walk-in booking (created and payment uploaded within 5 minutes)
            // Walk-in bookings are created by receptionist and payment is uploaded immediately
            $created_time = $booking['created_at'] ? strtotime($booking['created_at']) : 0;
            $uploaded_time = $booking['screenshot_uploaded_at'] ? strtotime($booking['screenshot_uploaded_at']) : 0;
            $time_diff = ($created_time && $uploaded_time) ? abs($uploaded_time - $created_time) : 0;
            $is_walkin = ($time_diff > 0 && $time_diff < 300); // Less than 5 minutes = walk-in
            
            // Only send email for online bookings, not walk-in bookings
            if (!$is_walkin) {
                send_payment_approval_email($booking_id);
                $message = 'Payment approved and confirmation email sent to guest!';
            } else {
                $message = 'Payment approved for walk-in booking!';
            }
            
            $conn->commit();
            
            // Set session variable to redirect to feedback after approval
            $_SESSION['payment_approved_booking'] = $booking_id;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to approve payment: ' . $e->getMessage();
        }
        
    } elseif ($_POST['action'] == 'reject') {
        $rejection_reason = sanitize_input($_POST['rejection_reason']);
        
        // Get current user ID and verify it exists
        $current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        
        // Verify user exists in database
        if ($current_user_id) {
            $user_check = $conn->query("SELECT id FROM users WHERE id = $current_user_id");
            if ($user_check->num_rows == 0) {
                $current_user_id = null;
            }
        }
        
        // Get booking details before rejection
        $booking_query = "SELECT b.*, 
                         COALESCE(r.name, 'Food Order') as room_name, 
                         COALESCE(r.room_number, 'N/A') as room_number, 
                         u.email, u.first_name, u.id as user_id
                         FROM bookings b 
                         LEFT JOIN rooms r ON b.room_id = r.id 
                         JOIN users u ON b.user_id = u.id 
                         WHERE b.id = ?";
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        
        if ($current_user_id) {
            $update_query = "UPDATE bookings SET 
                            verification_status = 'rejected',
                            status = 'cancelled',
                            verified_by = ?,
                            verified_at = NOW(),
                            rejection_reason = ?
                            WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("isi", $current_user_id, $rejection_reason, $booking_id);
        } else {
            $update_query = "UPDATE bookings SET 
                            verification_status = 'rejected',
                            status = 'cancelled',
                            verified_by = NULL,
                            verified_at = NOW(),
                            rejection_reason = ?
                            WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $rejection_reason, $booking_id);
        }
        
        if ($stmt->execute()) {
            // TODO: Send rejection email if needed
            $message = 'Payment rejected. Guest will be notified.';
        } else {
            $error = 'Failed to reject payment';
        }
    }
}

// Get pending payment verifications
$pending_payments = $conn->query("
    SELECT b.*, 
           COALESCE(r.name, 'Food Order') as room_name, 
           COALESCE(r.room_number, 'N/A') as room_number,
           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
           u.email, u.phone,
           DATEDIFF(b.check_out_date, b.check_in_date) as nights
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.user_id = u.id 
    WHERE b.verification_status = 'pending_verification'
    ORDER BY b.screenshot_uploaded_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payments - Receptionist Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .payment-screenshot {
            max-width: 300px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel - Receptionist
            </a>
            <div class="ms-auto">
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-concierge-bell"></i> Reception Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="receptionist.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="receptionist-checkin.php" class="nav-link">
                            <i class="fas fa-plus-circle me-2"></i> New Check-in
                        </a>
                        <a href="receptionist-checkout.php" class="nav-link">
                            <i class="fas fa-minus-circle me-2"></i> Process Check-out
                        </a>
                        <a href="verify-payments.php" class="nav-link active">
                            <i class="fas fa-check-circle me-2"></i> Verify Payments
                        </a>
                        <a href="receptionist-pending.php" class="nav-link">
                            <i class="fas fa-calendar-check me-2"></i> Pending Bookings
                        </a>
                        <a href="receptionist-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Manage Rooms
                        </a>
                    </nav>
                </div>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <h2 class="mb-4"><i class="fas fa-check-circle me-2"></i> Payment Verification</h2>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        
                        <?php if (isset($_SESSION['payment_approved_booking'])): ?>
                        <script>
                            // Auto-redirect to feedback after payment approval
                            setTimeout(function() {
                                const bookingId = <?php echo $_SESSION['payment_approved_booking']; ?>;
                                // Get booking reference for redirect
                                fetch('api/get_booking_reference.php?id=' + bookingId)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.booking_reference) {
                                            window.open('../customer-feedback.php?booking_ref=' + encodeURIComponent(data.booking_reference) + '&payment_id=' + encodeURIComponent(data.payment_reference || ''), '_blank');
                                        }
                                    })
                                    .catch(error => console.log('Could not auto-open feedback form'));
                            }, 2000);
                        </script>
                        <?php unset($_SESSION['payment_approved_booking']); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($pending_payments->num_rows > 0): ?>
                        <?php while ($booking = $pending_payments->fetch_assoc()): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0">Booking: <?php echo $booking['booking_reference']; ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Guest Information</h6>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email'] ?? ''); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?></p>
                                        
                                        <h6 class="mt-3">Booking Details</h6>
                                        <?php if ($booking['booking_type'] == 'room'): ?>
                                        <p><strong>Room:</strong> <?php echo htmlspecialchars($booking['room_name'] ?? ''); ?> (Room <?php echo htmlspecialchars($booking['room_number'] ?? ''); ?>)</p>
                                        <p><strong>Check-in:</strong> <?php echo ($booking['check_in_date'] ? date('M j, Y', strtotime($booking['check_in_date'])) : 'N/A'); ?></p>
                                        <p><strong>Check-out:</strong> <?php echo ($booking['check_out_date'] ? date('M j, Y', strtotime($booking['check_out_date'])) : 'N/A'); ?></p>
                                        <p><strong>Nights:</strong> <?php echo $booking['nights'] ?? 'N/A'; ?></p>
                                        <?php elseif ($booking['booking_type'] == 'food_order'): ?>
                                        <p><strong>Service Type:</strong> <span class="badge bg-success">Food Order</span></p>
                                        <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                        <p><strong>Service Type:</strong> <span class="badge bg-info">Spa & Wellness</span></p>
                                        <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                        <p><strong>Service Type:</strong> <span class="badge bg-warning text-dark">Laundry Service</span></p>
                                        <?php else: ?>
                                        <p><strong>Service Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['booking_type'] ?? 'Service')); ?></p>
                                        <?php endif; ?>
                                        <p><strong>Total Amount:</strong> <span class="text-success fw-bold"><?php echo format_currency($booking['total_price']); ?></span></p>
                                        <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'] ?? '')); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Transaction ID</h6>
                                        <?php if ($booking['transaction_id']): ?>
                                            <div class="alert alert-info mb-2">
                                                <strong>Transaction ID:</strong><br>
                                                <code class="fs-6"><?php echo htmlspecialchars($booking['transaction_id']); ?></code>
                                            </div>
                                            <p class="text-muted small">Submitted: <?php echo date('M j, Y g:i A', strtotime($booking['screenshot_uploaded_at'])); ?></p>
                                        <?php else: ?>
                                            <p class="text-danger">No transaction ID submitted</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Approve this payment?')">
                                            <i class="fas fa-check me-2"></i> Payment Approved
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $booking['id']; ?>">
                                        <i class="fas fa-times me-2"></i> Reject Payment
                                    </button>
                                    
                                    <a href="receptionist.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Back
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?php echo $booking['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reject Payment</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Rejection Reason</label>
                                                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Reject Payment</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No pending payment verifications.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
