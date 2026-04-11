<?php
/**
 * My Bookings Page - Protected
 * Requires: User authentication
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Require authentication and prevent caching
require_auth('login.php');

// Get user's bookings with payment verification status (including food orders)
$user_id = $_SESSION['user_id'];
$query = "SELECT b.*, 
          CASE 
              WHEN b.booking_type = 'spa_service' THEN 'Spa & Wellness'
              WHEN b.booking_type = 'laundry_service' THEN 'Laundry Service'
              WHEN b.booking_type = 'food_order' THEN 'Food Order'
              ELSE COALESCE(r.name, 'Room Booking')
          END as room_name,
          COALESCE(r.room_number, 'N/A') as room_number, 
          COALESCE(r.image, '') as room_image,
          CASE 
              WHEN b.verification_status = 'pending_payment' AND b.payment_deadline < NOW() THEN 'expired'
              ELSE b.verification_status
          END as current_verification_status,
          CONCAT(verifier.first_name, ' ', verifier.last_name) as verified_by_name,
          pmi.method_name as payment_method_name,
          pmi.bank_name,
          fo.table_reservation,
          fo.reservation_date,
          fo.reservation_time,
          fo.guests as food_guests,
          sb.service_name,
          sb.service_date,
          sb.service_time,
          sb.quantity as service_quantity
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id 
          LEFT JOIN users verifier ON b.verified_by = verifier.id
          LEFT JOIN payment_method_instructions pmi ON b.payment_method = pmi.method_code
          LEFT JOIN food_orders fo ON b.id = fo.booking_id
          LEFT JOIN service_bookings sb ON b.id = sb.booking_id
          WHERE b.user_id = ? 
          ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-5 fw-bold mb-3">My Bookings</h1>
                    <p class="lead text-muted">View and manage your hotel reservations</p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- Bookings Section -->
    <section class="py-5">
        <div class="container">
            <!-- Error Message Display -->
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']); // Clear the message after displaying
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (empty($bookings)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                <h3>No Orders or Bookings Found</h3>
                <p class="text-muted mb-4">You haven't made any room bookings or food orders yet. Start exploring!</p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="rooms.php" class="btn btn-gold btn-lg">
                        <i class="fas fa-bed"></i> Browse Rooms
                    </a>
                    <a href="food-booking.php" class="btn btn-outline-gold btn-lg">
                        <i class="fas fa-utensils"></i> Order Food
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($bookings as $booking): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <?php if ($booking['booking_type'] == 'food_order'): ?>
                                    <i class="fas fa-utensils text-gold"></i>
                                    Food Order
                                <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                    <i class="fas fa-spa text-gold"></i>
                                    Spa & Wellness
                                <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                    <i class="fas fa-tshirt text-gold"></i>
                                    Laundry Service
                                <?php else: ?>
                                    <i class="fas fa-bed text-gold"></i>
                                    <?php echo htmlspecialchars($booking['room_name']); ?>
                                <?php endif; ?>
                            </h5>
                            <?php
                            $status_class = 'secondary';
                            $status_text = ucfirst(str_replace('_', ' ', $booking['current_verification_status'] ?? $booking['status']));
                            
                            switch($booking['current_verification_status'] ?? $booking['status']) {
                                case 'pending':
                                case 'pending_payment':
                                    $status_class = 'warning';
                                    break;
                                case 'pending_verification':
                                    $status_class = 'info';
                                    break;
                                case 'verified':
                                case 'confirmed':
                                    $status_class = 'success';
                                    break;
                                case 'rejected':
                                case 'cancelled':
                                    $status_class = 'danger';
                                    break;
                                case 'expired':
                                    $status_class = 'dark';
                                    break;
                                case 'checked_in':
                                    $status_class = 'primary';
                                    break;
                                case 'checked_out':
                                    $status_class = 'secondary';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <?php 
                                        if ($booking['booking_type'] == 'food_order') {
                                            echo 'Order Reference';
                                        } elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])) {
                                            echo 'Service Reference';
                                        } else {
                                            echo 'Booking Reference';
                                        }
                                        ?>
                                    </small>
                                    <div class="fw-bold"><?php echo $booking['booking_reference']; ?></div>
                                </div>
                                <div class="col-6">
                                    <?php if ($booking['booking_type'] == 'food_order'): ?>
                                        <small class="text-muted">Table Reserved</small>
                                        <div class="fw-bold"><?php echo $booking['table_reservation'] ? 'Yes' : 'No (Takeaway)'; ?></div>
                                    <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                        <small class="text-muted">Service Name</small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($booking['service_name'] ?? 'Service'); ?></div>
                                    <?php else: ?>
                                        <small class="text-muted">Room Number</small>
                                        <div class="fw-bold"><?php echo $booking['room_number']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <?php if ($booking['booking_type'] == 'food_order'): ?>
                                    <div class="col-6">
                                        <small class="text-muted">Guests</small>
                                        <div><?php echo $booking['food_guests']; ?> guest(s)</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Reservation</small>
                                        <div>
                                            <?php if ($booking['table_reservation'] && $booking['reservation_date']): ?>
                                                <?php echo date('M j, Y', strtotime($booking['reservation_date'])); ?>
                                                <?php if ($booking['reservation_time']): ?>
                                                    <br><small><?php echo date('g:i A', strtotime($booking['reservation_time'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Not specified
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                    <div class="col-6">
                                        <small class="text-muted">Service Date</small>
                                        <div><?php echo !empty($booking['service_date']) ? date('M j, Y', strtotime($booking['service_date'])) : 'N/A'; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Service Time</small>
                                        <div><?php echo !empty($booking['service_time']) ? date('g:i A', strtotime($booking['service_time'])) : 'N/A'; ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-6">
                                        <small class="text-muted">Check-in</small>
                                        <div><?php echo $booking['check_in_date'] ? date('M j, Y', strtotime($booking['check_in_date'])) : 'N/A'; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Check-out</small>
                                        <div><?php echo $booking['check_out_date'] ? date('M j, Y', strtotime($booking['check_out_date'])) : 'N/A'; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <?php 
                                        if ($booking['booking_type'] == 'food_order') {
                                            echo 'Order Type';
                                        } elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])) {
                                            echo 'Quantity';
                                        } else {
                                            echo 'Room Guests';
                                        }
                                        ?>
                                    </small>
                                    <div>
                                        <?php if ($booking['booking_type'] == 'food_order'): ?>
                                            <?php echo $booking['table_reservation'] ? 'Dine-in' : 'Takeaway'; ?>
                                        <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                            <?php echo $booking['service_quantity'] ?? 1; ?> session(s)
                                        <?php else: ?>
                                            <?php echo $booking['customers']; ?> guest(s)
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Total Amount</small>
                                    <div class="fw-bold text-success"><?php echo format_currency($booking['total_price']); ?></div>
                                </div>
                            </div>
                            
                            <?php if ($booking['special_requests']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Special Requests</small>
                                <div class="small"><?php echo htmlspecialchars($booking['special_requests']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if (in_array($booking['current_verification_status'] ?? $booking['status'], ['pending_payment', 'rejected'])): ?>
                                <a href="payment-upload.php?booking=<?php echo $booking['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-upload"></i> Upload Payment
                                </a>
                                <?php endif; ?>
                                
                                <?php if (($booking['current_verification_status'] ?? $booking['status']) == 'verified'): ?>
                                <a href="booking-confirmation.php?ref=<?php echo $booking['booking_reference']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-alt"></i> View Confirmation
                                </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-primary btn-sm" onclick="viewBookingDetails('<?php echo $booking['booking_reference']; ?>')">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                
                                <button class="btn btn-outline-secondary btn-sm" onclick="printBooking(<?php echo htmlspecialchars(json_encode($booking)); ?>)">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                
                                <?php 
                                $booking_status = $booking['current_verification_status'] ?? $booking['status'];
                                $can_cancel = in_array($booking_status, ['pending', 'confirmed', 'verified', 'pending_payment', 'pending_verification']) && 
                                              $booking['booking_type'] != 'food_order' &&
                                              (!empty($booking['check_in_date']) && strtotime($booking['check_in_date']) > time());
                                
                                if ($can_cancel): 
                                ?>
                                <button class="btn btn-outline-danger btn-sm" onclick="cancelBooking('<?php echo $booking['booking_reference']; ?>')">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <small>
                                <i class="fas fa-clock"></i>
                                Booked on <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Cancellation Modal -->
    <div class="modal fade" id="cancelBookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Cancel Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="refundCalculation" style="display:none;">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Cancellation Policy</h6>
                            <p class="mb-0">Refunds are calculated based on how many days before check-in you cancel:</p>
                            <ul class="mb-0 mt-2">
                                <li>7+ days: 95% refund</li>
                                <li>3-6 days: 75% refund</li>
                                <li>1-2 days: 50% refund</li>
                                <li>Same day: 25% refund</li>
                            </ul>
                            <small class="text-muted">* All refunds subject to 5% processing fee</small>
                        </div>
                        
                        <h6>Refund Calculation:</h6>
                        <table class="table table-bordered">
                            <tr>
                                <td><strong>Booking Reference:</strong></td>
                                <td id="cancel_booking_ref"></td>
                            </tr>
                            <tr>
                                <td><strong>Room/Service:</strong></td>
                                <td id="cancel_room_name"></td>
                            </tr>
                            <tr>
                                <td><strong>Check-in Date:</strong></td>
                                <td id="cancel_checkin_date"></td>
                            </tr>
                            <tr>
                                <td><strong>Days Before Check-in:</strong></td>
                                <td><span id="cancel_days_before" class="badge bg-info"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Total Paid:</strong></td>
                                <td>ETB <span id="cancel_total_amount"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Refund Percentage:</strong></td>
                                <td><span id="cancel_refund_percentage" class="badge bg-success"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Refund Amount:</strong></td>
                                <td>ETB <span id="cancel_refund_amount"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Processing Fee (5%):</strong></td>
                                <td>ETB <span id="cancel_processing_fee"></span></td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>Final Refund:</strong></td>
                                <td><strong>ETB <span id="cancel_final_refund"></span></strong></td>
                            </tr>
                        </table>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i> Refunds will be processed within 5-7 business days to your original payment method.
                        </div>
                    </div>
                    
                    <div id="cancelError" class="alert alert-danger" style="display:none;"></div>
                    <div id="cancelLoading" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Calculating refund...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelBtn" onclick="confirmCancellation()">
                        <i class="fas fa-check"></i> Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        let currentBookingRef = '';
        
        function viewBookingDetails(reference) {
            window.location.href = 'booking-details.php?ref=' + reference;
        }
        
        function cancelBooking(reference) {
            currentBookingRef = reference;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('cancelBookingModal'));
            modal.show();
            
            // Show loading
            document.getElementById('cancelLoading').style.display = 'block';
            document.getElementById('refundCalculation').style.display = 'none';
            document.getElementById('cancelError').style.display = 'none';
            document.getElementById('confirmCancelBtn').style.display = 'inline-block';
            
            // Calculate refund
            fetch('api/cancel_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    booking_reference: reference,
                    action: 'calculate'
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('cancelLoading').style.display = 'none';
                
                if (data.success) {
                    // Display refund calculation
                    document.getElementById('cancel_booking_ref').textContent = data.data.booking_reference;
                    document.getElementById('cancel_room_name').textContent = data.data.room_name;
                    document.getElementById('cancel_checkin_date').textContent = data.data.check_in_date;
                    document.getElementById('cancel_days_before').textContent = data.data.days_before_checkin + ' days';
                    document.getElementById('cancel_total_amount').textContent = data.data.total_amount;
                    document.getElementById('cancel_refund_percentage').textContent = data.data.refund_percentage + '%';
                    document.getElementById('cancel_refund_amount').textContent = data.data.refund_amount;
                    document.getElementById('cancel_processing_fee').textContent = data.data.processing_fee;
                    document.getElementById('cancel_final_refund').textContent = data.data.final_refund;
                    
                    document.getElementById('refundCalculation').style.display = 'block';
                } else {
                    document.getElementById('cancelError').textContent = data.error;
                    document.getElementById('cancelError').style.display = 'block';
                    document.getElementById('confirmCancelBtn').style.display = 'none';
                }
            })
            .catch(error => {
                document.getElementById('cancelLoading').style.display = 'none';
                document.getElementById('cancelError').textContent = 'Failed to calculate refund. Please try again.';
                document.getElementById('cancelError').style.display = 'block';
            });
        }
        
        function confirmCancellation() {
            if (!confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
                return;
            }
            
            document.getElementById('confirmCancelBtn').disabled = true;
            document.getElementById('confirmCancelBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            fetch('api/cancel_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    booking_reference: currentBookingRef,
                    action: 'confirm'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                    document.getElementById('confirmCancelBtn').disabled = false;
                    document.getElementById('confirmCancelBtn').innerHTML = '<i class="fas fa-check"></i> Confirm Cancellation';
                }
            })
            .catch(error => {
                alert('Failed to cancel booking. Please try again.');
                document.getElementById('confirmCancelBtn').disabled = false;
                document.getElementById('confirmCancelBtn').innerHTML = '<i class="fas fa-check"></i> Confirm Cancellation';
            });
        }
        
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
        
        // Print booking information
        function printBooking(booking) {
            const printWindow = window.open('', '_blank');
            
            // Determine booking type display
            let bookingTypeIcon = '<i class="fas fa-bed"></i>';
            let bookingTypeText = 'Room Booking';
            
            if (booking.booking_type === 'food_order') {
                bookingTypeIcon = '<i class="fas fa-utensils"></i>';
                bookingTypeText = 'Food Order';
            } else if (booking.booking_type === 'spa_service') {
                bookingTypeIcon = '<i class="fas fa-spa"></i>';
                bookingTypeText = 'Spa & Wellness Service';
            } else if (booking.booking_type === 'laundry_service') {
                bookingTypeIcon = '<i class="fas fa-tshirt"></i>';
                bookingTypeText = 'Laundry Service';
            }
            
            // Format dates
            const checkInDate = booking.check_in_date ? new Date(booking.check_in_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
            const checkOutDate = booking.check_out_date ? new Date(booking.check_out_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
            const createdDate = new Date(booking.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            
            // Status badge color
            let statusColor = '#6c757d';
            const status = booking.current_verification_status || booking.status;
            
            switch(status) {
                case 'pending':
                case 'pending_payment':
                    statusColor = '#ffc107';
                    break;
                case 'pending_verification':
                    statusColor = '#0dcaf0';
                    break;
                case 'verified':
                case 'confirmed':
                    statusColor = '#198754';
                    break;
                case 'rejected':
                case 'cancelled':
                    statusColor = '#dc3545';
                    break;
                case 'checked_in':
                    statusColor = '#0d6efd';
                    break;
            }
            
            const printContent = `
                <html>
                <head>
                    <title>Booking Information - ${booking.booking_reference}</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            padding: 20px;
                            max-width: 800px;
                            margin: 0 auto;
                            color: #333;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 3px solid #d4af37;
                            padding-bottom: 20px;
                            margin-bottom: 30px;
                        }
                        .header h1 {
                            margin: 0;
                            color: #d4af37;
                            font-size: 2em;
                        }
                        .header p {
                            margin: 5px 0;
                            color: #666;
                        }
                        .booking-type {
                            background: #f8f9fa;
                            padding: 15px;
                            border-radius: 5px;
                            text-align: center;
                            margin-bottom: 20px;
                            font-size: 1.2em;
                            font-weight: bold;
                        }
                        .status-badge {
                            display: inline-block;
                            padding: 5px 15px;
                            border-radius: 20px;
                            color: white;
                            background: ${statusColor};
                            font-size: 0.9em;
                            margin-left: 10px;
                        }
                        .section {
                            margin-bottom: 25px;
                        }
                        .section h2 {
                            background: #f8f9fa;
                            padding: 10px 15px;
                            border-left: 4px solid #d4af37;
                            margin-bottom: 15px;
                            font-size: 1.1em;
                        }
                        .info-grid {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 15px;
                        }
                        .info-item {
                            padding: 10px;
                            border-bottom: 1px solid #eee;
                        }
                        .info-label {
                            font-weight: bold;
                            color: #555;
                            font-size: 0.9em;
                            display: block;
                            margin-bottom: 5px;
                        }
                        .info-value {
                            color: #333;
                            font-size: 1em;
                        }
                        .price-box {
                            background: #d4af37;
                            color: white;
                            padding: 20px;
                            border-radius: 5px;
                            text-align: center;
                            margin: 20px 0;
                        }
                        .price-box .amount {
                            font-size: 2em;
                            font-weight: bold;
                        }
                        .footer {
                            margin-top: 50px;
                            padding-top: 20px;
                            border-top: 2px solid #eee;
                            text-align: center;
                            color: #666;
                            font-size: 0.9em;
                        }
                        @media print {
                            body { padding: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Harar Ras Hotel</h1>
                        <p>Booking Information Document</p>
                        <p>Printed: ${new Date().toLocaleString()}</p>
                    </div>
                    
                    <div class="booking-type">
                        ${bookingTypeText}
                        <span class="status-badge">${status.replace(/_/g, ' ').toUpperCase()}</span>
                    </div>
                    
                    <div class="section">
                        <h2>Booking Details</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Booking Reference</span>
                                <span class="info-value">${booking.booking_reference}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Booking Date</span>
                                <span class="info-value">${createdDate}</span>
                            </div>
                            ${booking.booking_type !== 'food_order' ? `
                            <div class="info-item">
                                <span class="info-label">Room</span>
                                <span class="info-value">${booking.room_name} (${booking.room_number})</span>
                            </div>
                            ` : ''}
                            <div class="info-item">
                                <span class="info-label">Number of Guests</span>
                                <span class="info-value">${booking.customers || booking.food_guests || 1}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${booking.booking_type !== 'food_order' ? `
                    <div class="section">
                        <h2>Stay Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Check-in Date</span>
                                <span class="info-value">${checkInDate}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Check-out Date</span>
                                <span class="info-value">${checkOutDate}</span>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${booking.table_reservation ? `
                    <div class="section">
                        <h2>Reservation Details</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Reservation Date</span>
                                <span class="info-value">${new Date(booking.reservation_date).toLocaleDateString()}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Reservation Time</span>
                                <span class="info-value">${booking.reservation_time}</span>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${booking.payment_method_name ? `
                    <div class="section">
                        <h2>Payment Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Payment Method</span>
                                <span class="info-value">${booking.payment_method_name}</span>
                            </div>
                            ${booking.bank_name ? `
                            <div class="info-item">
                                <span class="info-label">Bank</span>
                                <span class="info-value">${booking.bank_name}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}
                    
                    <div class="price-box">
                        <div style="font-size: 1.2em; margin-bottom: 10px;">Total Amount</div>
                        <div class="amount">ETB ${parseFloat(booking.total_price).toFixed(2)}</div>
                    </div>
                    
                    ${booking.special_requests ? `
                    <div class="section">
                        <h2>Special Requests</h2>
                        <p style="padding: 10px; background: #f8f9fa; border-radius: 5px;">${booking.special_requests}</p>
                    </div>
                    ` : ''}
                    
                    <div class="footer">
                        <p><strong>Harar Ras Hotel</strong></p>
                        <p>For inquiries, please contact: support@hararrashotel.com</p>
                        <p>This is an official booking document. Please keep it for your records.</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for content to load then print
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    </script>
</body>
</html>