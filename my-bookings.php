<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php?redirect=my-bookings');
    exit();
}

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
          fo.guests as food_guests
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id 
          LEFT JOIN users verifier ON b.verified_by = verifier.id
          LEFT JOIN payment_method_instructions pmi ON b.payment_method = pmi.method_code
          LEFT JOIN food_orders fo ON b.id = fo.booking_id
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
                                        <small class="text-muted">Room Number</small>
                                        <div class="fw-bold">N/A</div>
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
                                        <small class="text-muted">Check-in</small>
                                        <div>N/A</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Check-out</small>
                                        <div>N/A</div>
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
                                            echo 'Room Guests';
                                        } else {
                                            echo 'Room Guests';
                                        }
                                        ?>
                                    </small>
                                    <div>
                                        <?php if ($booking['booking_type'] == 'food_order'): ?>
                                            <?php echo $booking['table_reservation'] ? 'Dine-in' : 'Takeaway'; ?>
                                        <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                            1 guest(s)
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
                                
                                <?php if (in_array($booking['current_verification_status'] ?? $booking['status'], ['pending', 'confirmed'])): ?>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        function viewBookingDetails(reference) {
            window.location.href = 'booking-details.php?ref=' + reference;
        }
        
        function cancelBooking(reference) {
            if (confirm('Are you sure you want to cancel booking ' + reference + '?\n\nThis action cannot be undone and may incur cancellation fees.')) {
                alert('Cancellation feature to be implemented');
            }
        }
        
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
    </script>
</body>
</html>