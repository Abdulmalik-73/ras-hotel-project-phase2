<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/RoomLockManager.php';

// Initialize Room Lock Manager
$lockManager = new RoomLockManager($conn);

$selected_room_id = isset($_GET['room']) ? (int)$_GET['room'] : null;
$selected_room = null;

if ($selected_room_id) {
    $selected_room = get_room_by_id($selected_room_id);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug: Log form submission
    error_log("Booking form submitted by user: " . ($_SESSION['user_id'] ?? 'not logged in'));
    error_log("Session user_id type: " . gettype($_SESSION['user_id'] ?? null));
    error_log("Session user_id value: " . var_export($_SESSION['user_id'] ?? null, true));
    error_log("POST data: " . print_r($_POST, true));
    
    if (!is_logged_in()) {
        error_log("User not logged in, storing booking data and redirecting");
        $_SESSION['booking_data'] = $_POST;
        header('Location: login.php');
        exit();
    }
    
    $room_id = (int)$_POST['room_id'];
    $check_in = sanitize_input($_POST['check_in']);
    $check_out = sanitize_input($_POST['check_out']);
    $customers = (int)$_POST['customers'];
    $special_requests = sanitize_input($_POST['special_requests']);
    
    $room = get_room_by_id($room_id);
    
    if (!$room) {
        $error = 'Invalid room selected';
    } else {
        // Acquire room lock before creating booking
        $lock_result = $lockManager->acquireRoomLock($room_id, $_SESSION['user_id'], $check_in, $check_out);
        
        if (!$lock_result['success']) {
            $error = 'Unable to lock room for booking: ' . $lock_result['message'];
        } elseif ($lock_result['status'] === 'waiting') {
            // User is in waiting queue
            $_SESSION['room_lock_id'] = $lock_result['lock_id'];
            $_SESSION['waiting_room'] = $room_id;
            set_message('info', 'This room is currently being booked by another user. You are in the waiting queue at position #' . $lock_result['position'] . '. You will be notified when it\'s your turn.');
            header('Location: my-bookings.php?waiting=1');
            exit();
        } else {
            // Lock acquired successfully, proceed with booking
            $_SESSION['room_lock_id'] = $lock_result['lock_id'];
            
            $nights = calculate_nights($check_in, $check_out);
            $total_price = $room['price'] * $nights;
            
            $booking_data = [
                'user_id' => $_SESSION['user_id'],
                'room_id' => $room_id,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'customers' => $customers,
                'total_price' => $total_price,
                'special_requests' => $special_requests
            ];
            
            error_log("Booking data being passed to create_booking: " . print_r($booking_data, true));
            
            $result = create_booking($booking_data);
            
            if ($result['success']) {
                // Debug: Log successful booking creation
                error_log("Booking created successfully with ID: " . $result['booking_id']);
                
                // Generate payment reference and set deadline
                $payment_ref = 'HRH-' . str_pad($result['booking_id'], 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($result['booking_id'] . time()), 0, 6));
                $deadline = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                
                // Update booking with payment verification fields
                $update_query = "UPDATE bookings SET 
                                payment_reference = ?, 
                                payment_deadline = ?, 
                                verification_status = 'pending_payment' 
                                WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssi", $payment_ref, $deadline, $result['booking_id']);
                
                if ($update_stmt->execute()) {
                    error_log("Payment reference updated successfully: " . $payment_ref);
                } else {
                    error_log("Failed to update payment reference: " . $update_stmt->error);
                }
                
                // Log user activity for booking
                log_user_activity($_SESSION['user_id'], 'booking', 'Room booking created: ' . $result['booking_reference'] . ' - Room ID: ' . $room_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Log booking activity
                log_booking_activity($result['booking_id'], $_SESSION['user_id'], 'created', '', 'pending', 'Booking created by customer - awaiting payment', $_SESSION['user_id']);
                
                // Store booking reference in session for payment
                $_SESSION['pending_booking'] = $result['booking_reference'];
                $_SESSION['current_booking_id'] = $result['booking_id'];
                set_message('success', 'Booking created successfully! Please submit your transaction ID to confirm your reservation.');
                
                // Debug: Log redirect
                error_log("Redirecting to: payment-upload.php?booking=" . $result['booking_id']);
                
                // Redirect to payment upload page
                header('Location: payment-upload.php?booking=' . $result['booking_id']);
                exit();
            } else {
                // Release lock if booking failed
                $lockManager->releaseRoomLock($lock_result['lock_id'], 'booking_failed');
                
                // Check if this is a duplicate booking error
                if (isset($result['error_code']) && $result['error_code'] === 'DUPLICATE_BOOKING' && isset($result['existing_booking'])) {
                    $existing = $result['existing_booking'];
                    $_SESSION['duplicate_booking_error'] = $existing;
                    $error = 'DUPLICATE_BOOKING'; // Flag for display
                } else {
                    $error = 'Booking failed. Please try again. Error: ' . $result['message'];
                }
            }
        }
    }
}

$rooms = get_all_rooms();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Now - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Top Guidance Banner for Non-Authenticated Users -->
    <?php if (!is_logged_in()): ?>
    <div class="alert alert-warning alert-dismissible fade show m-0 border-0 rounded-0" role="alert">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="alert-heading mb-2">
                        <i class="fas fa-exclamation-triangle"></i> Account Required to Book
                    </h5>
                    <p class="mb-0">
                        <strong>To proceed with booking, you must first create an account or sign in.</strong>
                        This ensures secure booking and allows you to manage your reservations.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="register.php?redirect=booking" class="btn btn-success btn-sm me-2">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                    <a href="login.php?redirect=booking" class="btn btn-primary btn-sm">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Clear Page Identifier for Logged-in Users -->
    <div class="alert alert-info border-info m-0 border-0 rounded-0">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="alert-heading mb-2">
                        <i class="fas fa-bed"></i> Room Booking
                    </h5>
                    <p class="mb-0">
                        <strong>This is the ROOM BOOKING page.</strong> Select your room and dates below.
                        <br><small>Looking to order food? <a href="food-booking.php" class="alert-link">Click here for Food Ordering</a></small>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <i class="fas fa-bed fa-3x text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <section class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
            
            <?php if (!is_logged_in()): ?>
            <!-- Step-by-Step Guidance Section -->
            <div class="row justify-content-center mb-5">
                <div class="col-lg-10">
                    <div class="card border-danger shadow-lg">
                        <div class="card-header bg-danger text-white text-center">
                            <h3 class="mb-0">
                                <i class="fas fa-shield-alt"></i> Authentication Required
                            </h3>
                            <p class="mb-0 mt-2">Follow these simple steps to complete your booking</p>
                        </div>
                        <div class="card-body p-4">
                            <!-- Step-by-Step Instructions -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-danger mb-3">
                                        <i class="fas fa-list-ol"></i> How to Complete Your Booking:
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-danger mb-2">1</div>
                                                <h6 class="text-danger">Create Account or Sign In</h6>
                                                <p class="small text-muted mb-0">Choose one of the options below to authenticate</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-warning mb-2">2</div>
                                                <h6 class="text-warning">Return to Booking</h6>
                                                <p class="small text-muted mb-0">You'll be automatically redirected back here</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-success mb-2">3</div>
                                                <h6 class="text-success">Complete Booking</h6>
                                                <p class="small text-muted mb-0">Fill out the form and confirm your reservation</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Authentication Options -->
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user-plus fa-3x mb-3"></i>
                                            <h5>New Customer?</h5>
                                            <p class="mb-3">Create a free account in just 2 minutes</p>
                                            <ul class="list-unstyled text-start mb-3">
                                                <li><i class="fas fa-check me-2"></i> Secure booking process</li>
                                                <li><i class="fas fa-check me-2"></i> Track your reservations</li>
                                                <li><i class="fas fa-check me-2"></i> Special member offers</li>
                                                <li><i class="fas fa-check me-2"></i> Booking history</li>
                                            </ul>
                                            <a href="register.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-user-plus"></i> Create Account Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-sign-in-alt fa-3x mb-3"></i>
                                            <h5>Existing Customer?</h5>
                                            <p class="mb-3">Sign in to your account to continue</p>
                                            <ul class="list-unstyled text-start mb-3">
                                                <li><i class="fas fa-check me-2"></i> Access your profile</li>
                                                <li><i class="fas fa-check me-2"></i> View booking history</li>
                                                <li><i class="fas fa-check me-2"></i> Manage reservations</li>
                                                <li><i class="fas fa-check me-2"></i> Quick checkout</li>
                                            </ul>
                                            <a href="login.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-sign-in-alt"></i> Sign In Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Why Account Required -->
                            <div class="alert alert-info mt-4">
                                <h6 class="alert-heading">
                                    <i class="fas fa-info-circle"></i> Why do I need an account?
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="mb-0">
                                            <li>Secure payment processing</li>
                                            <li>Booking confirmation emails</li>
                                            <li>Ability to modify/cancel reservations</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="mb-0">
                                            <li>Customer support assistance</li>
                                            <li>Loyalty program benefits</li>
                                            <li>Personalized service preferences</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm <?php echo !is_logged_in() ? 'opacity-25' : ''; ?>">
                        <div class="card-header text-white" style="background: <?php echo !is_logged_in() ? '#6c757d' : 'linear-gradient(135deg, #1e88e5 0%, #1565c0 100%)'; ?>; padding: 1.5rem;">
                            <h3 class="mb-0 fw-bold" style="font-size: 1.75rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                                <i class="fas fa-calendar-check me-2"></i> Book Your Stay
                                <?php if (!is_logged_in()): ?>
                                <span class="badge bg-danger ms-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-lock"></i> LOCKED - Authentication Required
                                </span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($error === 'DUPLICATE_BOOKING' && isset($_SESSION['duplicate_booking_error'])): ?>
                                <?php $existing = $_SESSION['duplicate_booking_error']; ?>
                                <div class="alert alert-danger" role="alert" style="position: sticky; top: 20px; z-index: 1000; animation: none !important;">
                                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Booking Not Allowed</h5>
                                    <p class="mb-2"><strong>You already have an active booking for overlapping dates.</strong></p>
                                    <p class="mb-3">Only one room can be booked per account at a time.</p>
                                    <hr>
                                    <h6 class="mb-3">Your Existing Booking:</h6>
                                    <ul class="mb-3">
                                        <li><strong>Room:</strong> <?php echo htmlspecialchars($existing['room_name']); ?> (Room <?php echo htmlspecialchars($existing['room_number']); ?>)</li>
                                        <li><strong>Check-in Date:</strong> <?php echo htmlspecialchars($existing['check_in_date']); ?></li>
                                        <li><strong>Booking Reference:</strong> <?php echo htmlspecialchars($existing['reference']); ?></li>
                                        <li><strong>Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($existing['status']); ?></span></li>
                                    </ul>
                                    <div class="d-flex gap-2">
                                        <a href="my-bookings.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-list"></i> View My Bookings
                                        </a>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.parentElement.style.display='none'">
                                            <i class="fas fa-times"></i> Dismiss
                                        </button>
                                    </div>
                                </div>
                            <?php elseif ($error): ?>
                                <div class="alert alert-danger" role="alert" style="animation: none !important;">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!is_logged_in()): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">
                                    <i class="fas fa-exclamation-triangle"></i> Booking Form Disabled
                                </h5>
                                <p class="mb-3">
                                    <strong>This booking form is currently disabled because you are not signed in.</strong>
                                </p>
                                <p class="mb-3">
                                    To enable this form and proceed with your booking, you must:
                                </p>
                                <ol class="mb-3">
                                    <li><strong>Create a new account</strong> (recommended for new customers)</li>
                                    <li><strong>Sign in to your existing account</strong> (if you already have one)</li>
                                </ol>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="register.php?redirect=booking" class="btn btn-success">
                                        <i class="fas fa-user-plus"></i> Create Account First
                                    </a>
                                    <a href="login.php?redirect=booking" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Sign In First
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="bookingForm" <?php echo !is_logged_in() ? 'style="pointer-events: none;"' : ''; ?>>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Select Room *</label>
                                    <select name="room_id" id="roomSelect" class="form-select" required style="font-size: 14px;">
                                        <option value="">Choose a room...</option>
                                        
                                        <optgroup label="Standard Single Room - ETB 2,000.00/night">
                                            <option value="1" data-price="2000" data-capacity="1">Standard Single Room Number 1 - ETB 2,000.00/night</option>
                                            <option value="2" data-price="2000" data-capacity="1">Standard Single Room Number 2 - ETB 2,000.00/night</option>
                                            <option value="3" data-price="2000" data-capacity="1">Standard Single Room Number 3 - ETB 2,000.00/night</option>
                                            <option value="4" data-price="2000" data-capacity="1">Standard Single Room Number 4 - ETB 2,000.00/night</option>
                                        </optgroup>
                                        
                                        <optgroup label="Standard Double Room - ETB 2,500.00/night">
                                            <option value="5" data-price="2500" data-capacity="2">Standard Double Room Number 5 - ETB 2,500.00/night</option>
                                            <option value="6" data-price="2500" data-capacity="2">Standard Double Room Number 6 - ETB 2,500.00/night</option>
                                            <option value="7" data-price="2500" data-capacity="2">Standard Double Room Number 7 - ETB 2,500.00/night</option>
                                            <option value="8" data-price="2500" data-capacity="2">Standard Double Room Number 8 - ETB 2,500.00/night</option>
                                        </optgroup>
                                        
                                        <optgroup label="Deluxe Single Room - ETB 3,000.00/night">
                                            <option value="9" data-price="3000" data-capacity="1">Deluxe Single Room Number 9 - ETB 3,000.00/night</option>
                                            <option value="10" data-price="3000" data-capacity="1">Deluxe Single Room Number 10 - ETB 3,000.00/night</option>
                                            <option value="11" data-price="3000" data-capacity="1">Deluxe Single Room Number 11 - ETB 3,000.00/night</option>
                                            <option value="12" data-price="3000" data-capacity="1">Deluxe Single Room Number 12 - ETB 3,000.00/night</option>
                                        </optgroup>
                                        
                                        <optgroup label="Deluxe Double Room - ETB 3,500.00/night">
                                            <option value="13" data-price="3500" data-capacity="2">Deluxe Double Room Number 13 - ETB 3,500.00/night</option>
                                            <option value="14" data-price="3500" data-capacity="2">Deluxe Double Room Number 14 - ETB 3,500.00/night</option>
                                            <option value="15" data-price="3500" data-capacity="2">Deluxe Double Room Number 15 - ETB 3,500.00/night</option>
                                            <option value="16" data-price="3500" data-capacity="2">Deluxe Double Room Number 16 - ETB 3,500.00/night</option>
                                        </optgroup>
                                        
                                        <optgroup label="Double (King Size) - ETB 3,000.00/night">
                                            <option value="17" data-price="3000" data-capacity="2">Double (King Size) Room Number 17 - ETB 3,000.00/night</option>
                                            <option value="18" data-price="3000" data-capacity="2">Double (King Size) Room Number 18 - ETB 3,000.00/night</option>
                                            <option value="19" data-price="3000" data-capacity="2">Double (King Size) Room Number 19 - ETB 3,000.00/night</option>
                                            <option value="20" data-price="3000" data-capacity="2">Double (King Size) Room Number 20 - ETB 3,000.00/night</option>
                                        </optgroup>
                                        
                                        <optgroup label="Suite Room - ETB 4,000.00/night">
                                            <option value="21" data-price="4000" data-capacity="3">Suite Room Number 21 - ETB 4,000.00/night</option>
                                            <option value="22" data-price="4000" data-capacity="3">Suite Room Number 22 - ETB 4,000.00/night</option>
                                            <option value="23" data-price="4000" data-capacity="3">Suite Room Number 23 - ETB 4,000.00/night</option>
                                            <option value="24" data-price="4000" data-capacity="3">Suite Room Number 24 - ETB 4,000.00/night</option>
                                            <option value="25" data-price="4000" data-capacity="3">Suite Room Number 25 - ETB 4,000.00/night</option>
                                            <option value="26" data-price="4000" data-capacity="3">Suite Room Number 26 - ETB 4,000.00/night</option>
                                            <option value="27" data-price="4000" data-capacity="3">Suite Room Number 27 - ETB 4,000.00/night</option>
                                            <option value="28" data-price="4000" data-capacity="3">Suite Room Number 28 - ETB 4,000.00/night</option>
                                        </optgroup>
                                        
                                        <optgroup label="Family (Team Bed) - ETB 4,000.00/night">
                                            <option value="29" data-price="4000" data-capacity="4">Family (Team Bed) Room Number 29 - ETB 4,000.00/night</option>
                                            <option value="30" data-price="4000" data-capacity="4">Family (Team Bed) Room Number 30 - ETB 4,000.00/night</option>
                                            <option value="31" data-price="4000" data-capacity="4">Family (Team Bed) Room Number 31 - ETB 4,000.00/night</option>
                                            <option value="32" data-price="4000" data-capacity="4">Family (Team Bed) Room Number 32 - ETB 4,000.00/night</option>
                                        </optgroup>
                                        
                                        <optgroup label="Executive Suite - ETB 6,000.00/night">
                                            <option value="33" data-price="6000" data-capacity="3">Executive Suite Room Number 33 - ETB 6,000.00/night</option>
                                            <option value="34" data-price="6000" data-capacity="3">Executive Suite Room Number 34 - ETB 6,000.00/night</option>
                                            <option value="35" data-price="6000" data-capacity="3">Executive Suite Room Number 35 - ETB 6,000.00/night</option>
                                            <option value="36" data-price="6000" data-capacity="3">Executive Suite Room Number 36 - ETB 6,000.00/night</option>
                                            <option value="37" data-price="6000" data-capacity="3">Executive Suite Room Number 37 - ETB 6,000.00/night</option>
                                        </optgroup>
                                        
                                        <optgroup label="Presidential Suite - ETB 8,000.00/night">
                                            <option value="38" data-price="8000" data-capacity="4">Presidential Suite Room Number 38 - ETB 8,000.00/night</option>
                                            <option value="39" data-price="8000" data-capacity="4">Presidential Suite Room Number 39 - ETB 8,000.00/night</option>
                                        </optgroup>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Check-in Date *</label>
                                        <input type="date" name="check_in" id="checkIn" class="form-control" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Check-out Date *</label>
                                        <input type="date" name="check_out" id="checkOut" class="form-control" 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Number of Customers *</label>
                                    <input type="number" name="customers" id="customers" class="form-control" 
                                           min="1" max="10" value="1" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Special Requests</label>
                                    <textarea name="special_requests" class="form-control" rows="3" 
                                              placeholder="Any special requirements or requests..."></textarea>
                                </div>
                                
                                <?php if (!is_logged_in()): ?>
                                <div class="alert alert-warning mb-4">
                                    <h6 class="alert-heading">
                                        <i class="fas fa-exclamation-triangle"></i> Cannot Proceed Without Authentication
                                    </h6>
                                    <p class="mb-2">You must be signed in to complete your booking.</p>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="register.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-user-plus"></i> Create Account
                                        </a>
                                        <a href="login.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-sign-in-alt"></i> Sign In
                                        </a>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-lg w-100" disabled>
                                    <i class="fas fa-lock"></i> BOOKING DISABLED - Please Sign In or Create Account Above
                                </button>
                                <?php else: ?>
                                <button type="submit" class="btn btn-gold btn-lg w-100">
                                    <i class="fas fa-check-circle"></i> Confirm Booking
                                </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 100px;">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Booking Summary</h5>
                        </div>
                        <div class="card-body">
                            <div id="bookingSummary">
                                <p class="text-muted text-center py-4">
                                    <i class="fas fa-info-circle"></i><br>
                                    Select room and dates to see pricing
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
    </script>
    <script>
        $(document).ready(function() {
            function updateSummary() {
                const roomSelect = $('#roomSelect');
                const checkIn = $('#checkIn').val();
                const checkOut = $('#checkOut').val();
                const customers = $('#customers').val();
                
                if (roomSelect.val() && checkIn && checkOut) {
                    const selectedOption = roomSelect.find(':selected');
                    const roomName = selectedOption.text().split(' - ')[0];
                    const pricePerNight = parseFloat(selectedOption.data('price'));
                    const maxCapacity = parseInt(selectedOption.data('capacity'));
                    
                    const date1 = new Date(checkIn);
                    const date2 = new Date(checkOut);
                    const nights = Math.ceil((date2 - date1) / (1000 * 60 * 60 * 24));
                    
                    if (nights > 0) {
                        const totalPrice = pricePerNight * nights;
                        
                        let html = `
                            <div class="mb-3">
                                <strong>Room:</strong><br>
                                <span class="text-muted">${roomName}</span>
                            </div>
                            <div class="mb-3">
                                <strong>Check-in:</strong><br>
                                <span class="text-muted">${new Date(checkIn).toLocaleDateString()}</span>
                            </div>
                            <div class="mb-3">
                                <strong>Check-out:</strong><br>
                                <span class="text-muted">${new Date(checkOut).toLocaleDateString()}</span>
                            </div>
                            <div class="mb-3">
                                <strong>Customers:</strong><br>
                                <span class="text-muted">${customers} Customer(s)</span>
                            </div>
                            <hr>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>${formatCurrency(pricePerNight)} × ${nights} night(s)</span>
                                    <span>${formatCurrency(totalPrice)}</span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total:</strong>
                                <strong class="text-gold fs-4">${formatCurrency(totalPrice)}</strong>
                            </div>
                        `;
                        
                        if (parseInt(customers) > maxCapacity) {
                            html += `<div class="alert alert-warning mt-3 mb-0">
                                <small><i class="fas fa-exclamation-triangle"></i> This room has a maximum capacity of ${maxCapacity} customers.</small>
                            </div>`;
                        }
                        
                        $('#bookingSummary').html(html);
                    }
                }
            }
            
            $('#roomSelect, #checkIn, #checkOut, #customers').on('change', updateSummary);
            
            // Set minimum checkout date based on checkin
            $('#checkIn').on('change', function() {
                const checkInDate = new Date($(this).val());
                checkInDate.setDate(checkInDate.getDate() + 1);
                $('#checkOut').attr('min', checkInDate.toISOString().split('T')[0]);
            });
            
            // Initial update if room is pre-selected
            <?php if ($selected_room): ?>
            updateSummary();
            <?php endif; ?>
            
            // Handle form submission with timeout
            $('#bookingForm').on('submit', function(e) {
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                
                // Show processing state
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
                
                // Set a timeout to prevent infinite processing
                const timeout = setTimeout(function() {
                    submitBtn.prop('disabled', false).html(originalText);
                    alert('Request is taking too long. Please try again.');
                }, 30000); // 30 seconds timeout
                
                // Clear timeout if form submits successfully
                $(this).on('submit', function() {
                    clearTimeout(timeout);
                });
            });
        });
    </script>
</body>
</html>
