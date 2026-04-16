<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

$booking_id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

if (!$booking_id) {
    header('Location: manage-bookings.php');
    exit();
}

// Handle cancel booking action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel') {
    $cancel_reason = sanitize_input($_POST['cancel_reason'] ?? 'Cancelled by admin');
    
    // Get booking details for logging
    $booking_query = "SELECT user_id, status FROM bookings WHERE id = ?";
    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking_data = $stmt->get_result()->fetch_assoc();
    
    if ($booking_data && $booking_data['status'] != 'cancelled' && $booking_data['status'] != 'checked_out') {
        $old_status = $booking_data['status'];
        $update_query = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $booking_id);
        
        if ($stmt->execute()) {
            log_booking_activity($booking_id, $booking_data['user_id'], 'cancelled', $old_status, 'cancelled', "Booking cancelled by admin: $cancel_reason", $_SESSION['user_id']);
            $message = 'Booking cancelled successfully!';
        } else {
            $error = 'Failed to cancel booking: ' . $conn->error;
        }
    } else {
        $error = 'Cannot cancel this booking (already cancelled or checked out)';
    }
}

// Get booking details
$query = "SELECT b.*, 
          COALESCE(r.name, 'Food Order') as room_name, 
          COALESCE(r.room_number, 'N/A') as room_number,
          r.price as room_price,
          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
          u.email, u.phone, u.address
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: manage-bookings.php');
    exit();
}

// Calculate nights - handle null dates
$nights = 0;
if (!empty($booking['check_in_date']) && !empty($booking['check_out_date'])) {
    try {
        $check_in = new DateTime($booking['check_in_date']);
        $check_out = new DateTime($booking['check_out_date']);
        $nights = $check_in->diff($check_out)->days;
    } catch (Exception $e) {
        $nights = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Booking - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/print.css">
    <style>
        body { background: #f8f9fa; }
        .booking-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section-title { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        .info-row { padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-label { font-weight: 600; color: #666; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container py-4 single-page-print">
        <div class="booking-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h2><i class="fas fa-file-alt me-2"></i> Booking Details</h2>
                <div>
                    <button onclick="window.print()" class="btn btn-primary me-2">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="manage-bookings.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Close
                    </a>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Booking Information -->
            <div class="mb-4">
                <h4 class="section-title">Booking Information</h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Booking Reference</div>
                            <div><strong><?php echo $booking['booking_reference']; ?></strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Booking Date</div>
                            <div><?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status</div>
                            <div>
                                <?php
                                $badge_class = 'secondary';
                                switch ($booking['status']) {
                                    case 'confirmed': $badge_class = 'success'; break;
                                    case 'checked_in': $badge_class = 'primary'; break;
                                    case 'checked_out': $badge_class = 'info'; break;
                                    case 'cancelled': $badge_class = 'danger'; break;
                                }
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Payment Status</div>
                            <div>
                                <span class="badge bg-<?php echo $booking['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Verification Status</div>
                            <div>
                                <span class="badge bg-info">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['verification_status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="mb-4">
                <h4 class="section-title">Customer Information</h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Name</div>
                            <div><?php echo htmlspecialchars($booking['customer_name'] ?? ''); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div><?php echo htmlspecialchars($booking['email'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Phone</div>
                            <div><?php echo htmlspecialchars($booking['phone'] ?? ''); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address</div>
                            <div><?php echo htmlspecialchars($booking['address'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Room & Stay Information -->
            <div class="mb-4">
                <h4 class="section-title">Room & Stay Information</h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Room</div>
                            <div><?php echo htmlspecialchars($booking['room_name'] ?? ''); ?> (Room <?php echo htmlspecialchars($booking['room_number'] ?? ''); ?>)</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Check-in Date</div>
                            <div><?php echo !empty($booking['check_in_date']) ? date('F j, Y', strtotime($booking['check_in_date'])) : 'N/A'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Check-out Date</div>
                            <div><?php echo !empty($booking['check_out_date']) ? date('F j, Y', strtotime($booking['check_out_date'])) : 'N/A'; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Number of Nights</div>
                            <div><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Number of Customers</div>
                            <div><?php echo $booking['customers']; ?> customer<?php echo $booking['customers'] > 1 ? 's' : ''; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Room Rate per Night</div>
                            <div><?php echo format_currency($booking['room_price']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="mb-4">
                <h4 class="section-title">Payment Information</h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Total Amount</div>
                            <div><strong class="text-success fs-4"><?php echo format_currency($booking['total_price']); ?></strong></div>
                        </div>
                        <?php if ($booking['payment_method']): ?>
                        <div class="info-row">
                            <div class="info-label">Payment Method</div>
                            <div><?php echo htmlspecialchars($booking['payment_method'] ?? ''); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($booking['payment_reference']): ?>
                        <div class="info-row">
                            <div class="info-label">Payment Reference</div>
                            <div><code><?php echo htmlspecialchars($booking['payment_reference'] ?? ''); ?></code></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Special Requests -->
            <?php if ($booking['special_requests']): ?>
            <div class="mb-4">
                <h4 class="section-title">Special Requests</h4>
                <div class="p-3 bg-light rounded">
                    <?php echo nl2br(htmlspecialchars($booking['special_requests'] ?? '')); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="text-center mt-4 no-print">
                <a href="manage-bookings.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
                <a href="edit-booking.php?id=<?php echo $booking_id; ?>" class="btn btn-warning me-2">
                    <i class="fas fa-edit"></i> Edit Booking
                </a>
                <?php if ($booking['status'] != 'cancelled' && $booking['status'] != 'checked_out'): ?>
                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="fas fa-ban"></i> Cancel Booking
                </button>
                <?php endif; ?>
                <a href="manage-bookings.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Close
                </a>
            </div>
        </div>
    </div>
    
    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-ban me-2"></i> Cancel Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Are you sure you want to cancel booking <strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong>?
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                            <select name="cancel_reason" class="form-select" required>
                                <option value="">Select reason</option>
                                <option value="Customer request">Customer request</option>
                                <option value="Payment not received">Payment not received</option>
                                <option value="Room unavailable">Room unavailable</option>
                                <option value="Duplicate booking">Duplicate booking</option>
                                <option value="Administrative decision">Administrative decision</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <p class="text-muted small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            This action will change the booking status to "Cancelled". The customer will be notified.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban me-2"></i> Cancel Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
