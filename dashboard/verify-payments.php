<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_roles(['admin', 'manager', 'receptionist'], '../login.php');

$action = $_GET['action'] ?? 'list';
$booking_id = $_GET['booking'] ?? 0;
$message = '';
$error = '';

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $booking_id) {
    $verification_action = $_POST['verification_action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
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
            // Send verification email to customer
            try {
                require_once '../includes/services/EmailService.php';
                $emailService = new EmailService($conn);
                
                // Get booking details for email
                $booking_query = "SELECT b.*, u.first_name, u.last_name, u.email, 
                                 COALESCE(r.name, 'Service Booking') as room_name,
                                 COALESCE(r.room_number, 'N/A') as room_number
                                 FROM bookings b
                                 LEFT JOIN rooms r ON b.room_id = r.id
                                 JOIN users u ON b.user_id = u.id
                                 WHERE b.id = ?";
                $booking_stmt = $conn->prepare($booking_query);
                $booking_stmt->bind_param("i", $booking_id);
                $booking_stmt->execute();
                $booking_details = $booking_stmt->get_result()->fetch_assoc();
                
                if ($booking_details) {
                    // Send payment verification email
                    $emailResult = $emailService->sendPaymentVerificationEmail($booking_details);
                    if ($emailResult['success']) {
                        $message = 'Payment approved successfully! Booking has been confirmed and verification email sent to customer.';
                    } else {
                        $message = 'Payment approved successfully! Booking has been confirmed. (Email notification failed: ' . $emailResult['message'] . ')';
                    }
                } else {
                    $message = 'Payment approved successfully! Booking has been confirmed.';
                }
            } catch (Exception $e) {
                $message = 'Payment approved successfully! Booking has been confirmed. (Email notification failed)';
                error_log("Payment verification email error: " . $e->getMessage());
            }
        } else {
            $error = 'Failed to approve payment. Please try again.';
        }
        
    } elseif ($verification_action == 'reject') {
        $rejection_reason = $_POST['rejection_reason'] ?? '';
        
        // Reject payment - reset to pending_payment so customer can resubmit
        $update_query = "UPDATE bookings SET 
                        verification_status = 'pending_payment', 
                        verified_by = ?, 
                        verified_at = NOW(),
                        rejection_reason = ?,
                        screenshot_path = NULL,
                        screenshot_uploaded_at = NULL
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("isi", $_SESSION['user_id'], $rejection_reason, $booking_id);
        
        if ($stmt->execute()) {
            $message = 'Payment rejected. Customer can resubmit payment.';
        } else {
            $error = 'Failed to reject payment. Please try again.';
        }
    }
}

// Get pending payments for verification
if ($action == 'list') {
    $query = "SELECT b.*, 
              COALESCE(r.name, 'Service Booking') as room_name,
              COALESCE(r.room_number, 'N/A') as room_number,
              u.first_name, u.last_name, u.email
              FROM bookings b
              LEFT JOIN rooms r ON b.room_id = r.id
              JOIN users u ON b.user_id = u.id
              WHERE b.verification_status = 'pending_verification'
              AND b.screenshot_path IS NOT NULL
              ORDER BY b.screenshot_uploaded_at ASC";
    
    $result = $conn->query($query);
    $pending_payments = $result->fetch_all(MYSQLI_ASSOC);
}

// Get specific booking for verification
if ($action == 'verify' && $booking_id) {
    $query = "SELECT b.*, 
              COALESCE(r.name, 'Service Booking') as room_name,
              COALESCE(r.room_number, 'N/A') as room_number,
              u.first_name, u.last_name, u.email, u.phone
              FROM bookings b
              LEFT JOIN rooms r ON b.room_id = r.id
              JOIN users u ON b.user_id = u.id
              WHERE b.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        header('Location: verify-payments.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .screenshot-preview {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .payment-method-badge {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }
        .booking-card {
            transition: transform 0.2s ease;
        }
        .booking-card:hover {
            transform: translateY(-2px);
        }
        .verification-actions {
            position: sticky;
            top: 20px;
        }
        .sidebar {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h5 class="mb-4"><i class="fas fa-shield-alt"></i> Staff Panel</h5>
                <div class="nav flex-column">
                    <button onclick="history.back()" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 py-4">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($action == 'list'): ?>
                    <!-- Payment Verification List -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-clock"></i> Pending Payment Verifications</h2>
                        <span class="badge bg-warning fs-6"><?php echo count($pending_payments); ?> Pending</span>
                    </div>
                    
                    <?php if (empty($pending_payments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">All Caught Up!</h4>
                            <p class="text-muted">No pending payment verifications at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($pending_payments as $payment): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card booking-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h5 class="card-title"><?php echo $payment['booking_reference']; ?></h5>
                                                <span class="badge bg-info payment-method-badge">
                                                    <?php echo ucfirst($payment['payment_method']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-sm-6">
                                                    <p class="mb-2"><strong>Customer:</strong><br><?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?></p>
                                                    <p class="mb-2"><strong>Email:</strong><br><?php echo $payment['email']; ?></p>
                                                </div>
                                                <div class="col-sm-6">
                                                    <p class="mb-2"><strong>Amount:</strong><br><span class="text-success fw-bold"><?php echo number_format($payment['total_price'], 2); ?> ETB</span></p>
                                                    <p class="mb-2"><strong>Submitted:</strong><br><?php echo date('M d, Y g:i A', strtotime($payment['screenshot_uploaded_at'])); ?></p>
                                                </div>
                                            </div>
                                            
                                            <?php if ($payment['booking_type'] === 'room'): ?>
                                                <p class="mb-3"><strong>Room:</strong> <?php echo $payment['room_name'] . ' (' . $payment['room_number'] . ')'; ?></p>
                                            <?php else: ?>
                                                <p class="mb-3"><strong>Service:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['booking_type'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="d-grid">
                                                <a href="verify-payments.php?action=verify&booking=<?php echo $payment['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-eye"></i> Review Payment
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($action == 'verify' && $booking): ?>
                    <!-- Individual Payment Verification -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-search"></i> Payment Verification</h2>
                        <a href="verify-payments.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                    
                    <div class="row">
                        <!-- Booking Details -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Booking Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <p><strong>Reference:</strong><br><?php echo $booking['booking_reference']; ?></p>
                                            <p><strong>Customer:</strong><br><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></p>
                                            <p><strong>Email:</strong><br><?php echo $booking['email']; ?></p>
                                            <?php if ($booking['phone']): ?>
                                                <p><strong>Phone:</strong><br><?php echo $booking['phone']; ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-sm-6">
                                            <?php if ($booking['booking_type'] === 'room'): ?>
                                                <p><strong>Room:</strong><br><?php echo $booking['room_name'] . ' (' . $booking['room_number'] . ')'; ?></p>
                                                <?php if ($booking['check_in_date']): ?>
                                                    <p><strong>Check-in:</strong><br><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></p>
                                                    <p><strong>Check-out:</strong><br><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p><strong>Service:</strong><br><?php echo ucfirst(str_replace('_', ' ', $booking['booking_type'])); ?></p>
                                            <?php endif; ?>
                                            <p><strong>Total Amount:</strong><br><span class="h5 text-success"><?php echo number_format($booking['total_price'], 2); ?> ETB</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Information -->
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Payment Information</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Payment Method:</strong> <span class="badge bg-info"><?php echo ucfirst($booking['payment_method']); ?></span></p>
                                    <p><strong>Submitted:</strong> <?php echo date('M d, Y g:i A', strtotime($booking['screenshot_uploaded_at'])); ?></p>
                                    <p><strong>Status:</strong> <span class="badge bg-warning">Pending Verification</span></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Screenshot & Actions -->
                        <div class="col-lg-6">
                            <!-- Payment Screenshot -->
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Payment Screenshot</h5>
                                </div>
                                <div class="card-body text-center">
                                    <?php if ($booking['screenshot_path']): ?>
                                        <img src="../<?php echo $booking['screenshot_path']; ?>" 
                                             alt="Payment Screenshot" 
                                             class="screenshot-preview"
                                             data-bs-toggle="modal" 
                                             data-bs-target="#screenshotModal">
                                        <p class="mt-2 text-muted small">Click to view full size</p>
                                    <?php else: ?>
                                        <p class="text-muted">No screenshot uploaded</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Verification Actions -->
                            <div class="card verification-actions">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0">Verification Actions</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Admin Notes (Optional)</label>
                                            <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add any notes about this verification..."></textarea>
                                        </div>
                                        
                                        <div class="mb-3" id="rejectionReason" style="display: none;">
                                            <label class="form-label">Rejection Reason</label>
                                            <textarea name="rejection_reason" class="form-control" rows="2" placeholder="Explain why this payment is being rejected..."></textarea>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="verification_action" value="approve" class="btn btn-success btn-lg">
                                                <i class="fas fa-check"></i> Approve Payment
                                            </button>
                                            <button type="button" class="btn btn-danger btn-lg" onclick="showRejectionForm()">
                                                <i class="fas fa-times"></i> Reject Payment
                                            </button>
                                            <button type="submit" name="verification_action" value="reject" class="btn btn-outline-danger" id="confirmReject" style="display: none;">
                                                <i class="fas fa-times"></i> Confirm Rejection
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Screenshot Modal -->
                    <div class="modal fade" id="screenshotModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Payment Screenshot</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <?php if ($booking['screenshot_path']): ?>
                                        <img src="../<?php echo $booking['screenshot_path']; ?>" 
                                             alt="Payment Screenshot" 
                                             class="img-fluid">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showRejectionForm() {
            document.getElementById('rejectionReason').style.display = 'block';
            document.getElementById('confirmReject').style.display = 'block';
        }
    </script>
</body>
</html>