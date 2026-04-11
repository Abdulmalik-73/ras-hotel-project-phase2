<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php?redirect=my-bookings');
    exit();
}

$booking_ref = isset($_GET['ref']) ? sanitize_input($_GET['ref']) : '';

if (empty($booking_ref)) {
    $_SESSION['error_message'] = 'No booking reference provided.';
    header('Location: my-bookings.php');
    exit();
}

// Debug: Log the booking reference being searched
error_log("Booking Details: Searching for booking reference: " . $booking_ref . " for user ID: " . $_SESSION['user_id']);

// Get booking details
$query = "SELECT b.*, 
          CASE 
              WHEN b.booking_type = 'spa_service' THEN 'Spa & Wellness'
              WHEN b.booking_type = 'laundry_service' THEN 'Laundry Service'
              WHEN b.booking_type = 'food_order' THEN 'Food Order'
              ELSE COALESCE(r.name, 'Room Booking')
          END as room_name,
          COALESCE(r.room_number, 'N/A') as room_number,
          COALESCE(r.price, 0) as room_price,
          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
          u.email, u.phone,
          fo.table_reservation, fo.reservation_date, fo.reservation_time, fo.guests as food_guests,
          sb.service_name, sb.service_date, sb.service_time, sb.quantity as service_quantity, sb.special_requests as service_requests
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          LEFT JOIN food_orders fo ON b.id = fo.booking_id
          LEFT JOIN service_bookings sb ON b.id = sb.booking_id
          WHERE b.booking_reference = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    // Database error - redirect with error message
    $_SESSION['error_message'] = 'Database error occurred. Please try again.';
    header('Location: my-bookings.php');
    exit();
}

$stmt->bind_param("si", $booking_ref, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    // Booking not found or doesn't belong to user
    $_SESSION['error_message'] = 'Booking not found or you do not have permission to view it.';
    header('Location: my-bookings.php');
    exit();
}

// Get food order items if this is a food order
$food_items = [];
if ($booking['booking_type'] == 'food_order') {
    $items_query = "SELECT foi.* FROM food_order_items foi
                    JOIN food_orders fo ON foi.order_id = fo.id
                    WHERE fo.booking_id = (SELECT id FROM bookings WHERE booking_reference = ?)";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param("s", $booking_ref);
    $items_stmt->execute();
    $food_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php if ($booking['booking_type'] == 'food_order'): ?>
            Food Order Details - Harar Ras Hotel
        <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
            Spa Service Details - Harar Ras Hotel
        <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
            Laundry Service Details - Harar Ras Hotel
        <?php else: ?>
            Room Booking Details - Harar Ras Hotel
        <?php endif; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/print.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Print-only header - completely hidden on screen -->
    <div class="print-header" style="display: none;">
        <h1>Harar Ras Hotel</h1>
        <p>Jugol Street, Harar, Ethiopia | Phone: +251-25-666-2828</p>
        <p><strong>
            <?php if ($booking['booking_type'] == 'food_order'): ?>
                Food Order Information Document
            <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                Spa Service Information Document
            <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                Laundry Service Information Document
            <?php else: ?>
                Room Information Document
            <?php endif; ?>
        </strong></p>
        <p>Printed: <?php echo date('n/j/Y, g:i A'); ?> | Type: <?php echo $booking['booking_type']; ?></p>
    </div>
    
    <div class="no-print">
        <!-- Completely minimal header - only for screen, hidden in print -->
        <div class="d-none">
            <!-- Hidden header - no visible interface -->
        </div>
    </div>
    
    <section class="py-4">
        <div class="container">
            
            <div class="card shadow">
                <div class="card-header bg-gold text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">
                        <?php if ($booking['booking_type'] == 'food_order'): ?>
                            <i class="fas fa-utensils"></i> Food Order Details
                        <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                            <i class="fas fa-spa"></i> Spa Service Details
                        <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                            <i class="fas fa-tshirt"></i> Laundry Service Details
                        <?php else: ?>
                            <i class="fas fa-bed"></i> Room Booking Details
                        <?php endif; ?>
                    </h3>
                    <!-- Print button in top-right of card header -->
                    <div class="no-print">
                        <button onclick="window.print()" class="btn btn-light btn-sm">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Service/Order Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="text-gold mb-3">
                                <?php if ($booking['booking_type'] == 'food_order'): ?>
                                    Order Information
                                <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                    Service Information
                                <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                    Service Information
                                <?php else: ?>
                                    Room Information
                                <?php endif; ?>
                            </h5>
                            <table class="table table-sm booking-details-table">
                                <tr>
                                    <td><strong>
                                        <?php if ($booking['booking_type'] == 'food_order'): ?>
                                            Order Reference:
                                        <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                            Service Reference:
                                        <?php else: ?>
                                            Room Reference:
                                        <?php endif; ?>
                                    </strong></td>
                                    <td><?php echo htmlspecialchars($booking['booking_reference']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        $status_text = ucfirst(str_replace('_', ' ', $booking['verification_status']));
                                        switch($booking['verification_status']) {
                                            case 'pending_payment': $status_class = 'warning'; break;
                                            case 'pending_verification': $status_class = 'info'; break;
                                            case 'verified': $status_class = 'success'; break;
                                            case 'rejected': $status_class = 'danger'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>
                                        <?php if ($booking['booking_type'] == 'food_order'): ?>
                                            Ordered On:
                                        <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                            Booked On:
                                        <?php else: ?>
                                            Booked On:
                                        <?php endif; ?>
                                    </strong></td>
                                    <td><?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="text-gold mb-3">Customer Information</h5>
                            <table class="table table-sm booking-details-table">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['phone'] ?? ''); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Service/Room Details -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-gold mb-3">
                                <?php if ($booking['booking_type'] == 'food_order'): ?>
                                    Order Details
                                <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                    Service Details
                                <?php else: ?>
                                    Room Details
                                <?php endif; ?>
                            </h5>
                            <table class="table table-sm booking-details-table">
                                <?php if ($booking['booking_type'] == 'food_order'): ?>
                                    <tr>
                                        <td><strong>Order Type:</strong></td>
                                        <td><?php echo $booking['table_reservation'] ? 'Dine-in (Table Reserved)' : 'Takeaway'; ?></td>
                                    </tr>
                                    <?php if ($booking['table_reservation']): ?>
                                    <tr>
                                        <td><strong>Reservation Date:</strong></td>
                                        <td><?php echo $booking['reservation_date'] ? date('F j, Y', strtotime($booking['reservation_date'])) : 'Not specified'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reservation Time:</strong></td>
                                        <td><?php echo $booking['reservation_time'] ? date('g:i A', strtotime($booking['reservation_time'])) : 'Not specified'; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong>Number of Guests:</strong></td>
                                        <td><?php echo $booking['food_guests']; ?></td>
                                    </tr>
                                <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                    <tr>
                                        <td><strong>Service Category:</strong></td>
                                        <td>Spa & Wellness</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Service Name:</strong></td>
                                        <td><?php echo htmlspecialchars($booking['service_name'] ?? 'Spa Service'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Service Date:</strong></td>
                                        <td><?php echo !empty($booking['service_date']) ? date('F j, Y', strtotime($booking['service_date'])) : 'To be scheduled'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Service Time:</strong></td>
                                        <td><?php echo !empty($booking['service_time']) ? date('g:i A', strtotime($booking['service_time'])) : 'To be scheduled'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sessions:</strong></td>
                                        <td><?php echo $booking['service_quantity'] ?? 1; ?> session(s)</td>
                                    </tr>
                                <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                    <tr>
                                        <td><strong>Service Category:</strong></td>
                                        <td>Laundry Service</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Service Name:</strong></td>
                                        <td><?php echo htmlspecialchars($booking['service_name'] ?? 'Laundry Service'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Service Date:</strong></td>
                                        <td><?php echo !empty($booking['service_date']) ? date('F j, Y', strtotime($booking['service_date'])) : 'To be scheduled'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Service Time:</strong></td>
                                        <td><?php echo !empty($booking['service_time']) ? date('g:i A', strtotime($booking['service_time'])) : 'To be scheduled'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Items:</strong></td>
                                        <td><?php echo $booking['service_quantity'] ?? 1; ?> item(s)</td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td><strong>Room Type:</strong></td>
                                        <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Room Number:</strong></td>
                                        <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Check-in Date:</strong></td>
                                        <td><?php echo date('F j, Y', strtotime($booking['check_in_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Check-out Date:</strong></td>
                                        <td><?php echo date('F j, Y', strtotime($booking['check_out_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Number of Guests:</strong></td>
                                        <td><?php echo $booking['customers']; ?></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Food Items (if food order) -->
                    <?php if ($booking['booking_type'] == 'food_order' && !empty($food_items)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-gold mb-3">Ordered Items</h5>
                            <table class="table table-bordered food-items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($food_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end"><?php echo format_currency($item['price']); ?></td>
                                        <td class="text-end"><?php echo format_currency($item['total_price']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Special Requests -->
                    <?php if ($booking['special_requests']): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-gold mb-3">Special Requests</h5>
                            <div class="alert alert-info" style="margin: 2px 0; padding: 3px;">
                                <?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Payment Information -->
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <table class="table table-sm booking-details-table">
                                <tr>
                                    <td><strong>Total Amount:</strong></td>
                                    <td class="text-end"><h4 class="text-success mb-0"><?php echo format_currency($booking['total_price']); ?></h4></td>
                                </tr>
                                <tr>
                                    <td><strong>Payment Status:</strong></td>
                                    <td class="text-end">
                                        <span class="badge bg-<?php echo $booking['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($booking['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Back button only -->
            <div class="text-center mt-4 no-print">
                <a href="my-bookings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to My Bookings
                </a>
            </div>
        </div>
    </section>
    
    <div class="no-print">
        <?php include 'includes/footer.php'; ?>
    </div>
    
    <!-- Print-only footer -->
    <div class="print-footer" style="display: none;">
        <p>Harar Ras Hotel - For inquiries: support@hararrashotel.com - This is an official document.</p>
    </div>
    
    <!-- Cancel Booking Modal -->
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
                                <td><strong>Reference:</strong></td>
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
    <script>
        let currentBookingRef = '';
        
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
                    window.location.href = 'my-bookings.php';
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
    </script>
</body>
</html>
