<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/RoomLockManager.php';

// Add cache-busting headers to prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Initialize Room Lock Manager
$lockManager = new RoomLockManager($conn);

// Clear error session if user is starting fresh (no POST and no error parameter)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['error'])) {
    // Only clear if user clicked "Choose Another Room" or navigated normally
    if (isset($_GET['clear_error'])) {
        unset($_SESSION['room_not_available_error']);
        unset($_SESSION['duplicate_booking_error']);
        unset($_SESSION['max_booking_error']);
    }
}

$selected_room_id = isset($_GET['room']) ? (int)$_GET['room'] : (isset($_GET['room_id']) ? (int)$_GET['room_id'] : null);
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
        // Create booking directly - overlap check is done in create_booking()
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
                // Booking failed - show error message
                
                // Check if this is a max booking limit error
                if (isset($result['error_code']) && $result['error_code'] === 'MAX_BOOKING_LIMIT' && isset($result['existing_bookings'])) {
                    $_SESSION['max_booking_error'] = [
                        'existing_bookings' => $result['existing_bookings'],
                        'booking_count' => $result['booking_count'],
                        'check_in_date' => $result['check_in_date'] ?? date('F j, Y', strtotime($check_in))
                    ];
                    $_SESSION['max_booking_error_time'] = time(); // Store timestamp
                    $error = 'MAX_BOOKING_LIMIT'; // Flag for display
                }
                // Check if this is a room not available error
                elseif (isset($result['error_code']) && $result['error_code'] === 'ROOM_NOT_AVAILABLE' && isset($result['blocking_booking'])) {
                    $blocking = $result['blocking_booking'];
                    $_SESSION['room_not_available_error'] = $blocking;
                    $error = 'ROOM_WAITING_STATE'; // Flag for display
                } 
                // Check if this is a duplicate booking error (legacy)
                elseif (isset($result['error_code']) && $result['error_code'] === 'DUPLICATE_BOOKING' && isset($result['existing_booking'])) {
                    $existing = $result['existing_booking'];
                    $_SESSION['duplicate_booking_error'] = $existing;
                    $error = 'OVERLAPPING_DATES'; // Flag for display
                } else {
                    $error = 'Booking failed. Please try again. Error: ' . $result['message'];
                }
            }
    }
}

$rooms = get_all_rooms();

// Add timestamp for debugging
$page_load_time = date('Y-m-d H:i:s');
error_log("Booking page loaded at $page_load_time with " . count($rooms) . " rooms");
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
                        <strong><?php echo __('booking_auth.room_booking_page'); ?></strong> <?php echo __('booking_auth.select_room_dates'); ?>
                        <br><small>Looking to order food? <a href="food-booking.php" class="alert-link"><?php echo __('booking_auth.food_link'); ?></a></small>
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
                        <i class="fas fa-arrow-left"></i> <?php echo __('booking_auth.back_to_home'); ?>
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
                                <i class="fas fa-shield-alt"></i> <?php echo __('booking_auth.auth_required'); ?>
                            </h3>
                            <p class="mb-0 mt-2">Follow these simple steps to complete your booking</p>
                        </div>
                        <div class="card-body p-4">
                            <!-- Step-by-Step Instructions -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-danger mb-3">
                                        <i class="fas fa-list-ol"></i> <?php echo __('booking_auth.how_to_book'); ?>
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-danger mb-2">1</div>
                                                <h6 class="text-danger"><?php echo __('booking_auth.step1_title'); ?></h6>
                                                <p class="small text-muted mb-0">Choose one of the options below to authenticate</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-warning mb-2">2</div>
                                                <h6 class="text-warning"><?php echo __('booking_auth.step2_title'); ?></h6>
                                                <p class="small text-muted mb-0">You'll be automatically redirected back here</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-success mb-2">3</div>
                                                <h6 class="text-success"><?php echo __('booking_auth.step3_title'); ?></h6>
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
                                            <h5><?php echo __('booking_auth.new_customer'); ?></h5>
                                            <p class="mb-3">Create a free account in just 2 minutes</p>
                                            <ul class="list-unstyled text-start mb-3">
                                                <li><i class="fas fa-check me-2"></i> Secure booking process</li>
                                                <li><i class="fas fa-check me-2"></i> Track your reservations</li>
                                                <li><i class="fas fa-check me-2"></i> Special member offers</li>
                                                <li><i class="fas fa-check me-2"></i> Booking history</li>
                                            </ul>
                                            <a href="register.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-user-plus"></i> <?php echo __('booking_auth.create_account_now'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-sign-in-alt fa-3x mb-3"></i>
                                            <h5><?php echo __('booking_auth.existing_customer'); ?></h5>
                                            <p class="mb-3">Sign in to your account to continue</p>
                                            <ul class="list-unstyled text-start mb-3">
                                                <li><i class="fas fa-check me-2"></i> Access your profile</li>
                                                <li><i class="fas fa-check me-2"></i> View booking history</li>
                                                <li><i class="fas fa-check me-2"></i> Manage reservations</li>
                                                <li><i class="fas fa-check me-2"></i> Quick checkout</li>
                                            </ul>
                                            <a href="login.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-sign-in-alt"></i> <?php echo __('booking_auth.sign_in_now'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Why Account Required -->
                            <div class="alert alert-info mt-4">
                                <h6 class="alert-heading">
                                    <i class="fas fa-info-circle"></i> <?php echo __('booking_auth.why_account'); ?>
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
                                <i class="fas fa-calendar-check me-2"></i> <?php echo __('booking.title'); ?>
                                <?php if (!is_logged_in()): ?>
                                <span class="badge bg-danger ms-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-lock"></i> LOCKED - Authentication Required
                                </span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($error === 'MAX_BOOKING_LIMIT' && isset($_SESSION['max_booking_error'])): ?>
                                <?php 
                                $error_data = $_SESSION['max_booking_error']; 
                                $error_timestamp = $_SESSION['max_booking_error_time'] ?? time();
                                $time_elapsed = time() - $error_timestamp;
                                $min_display_time = 180; // 3 minutes in seconds
                                ?>
                                <div class="alert alert-danger" role="alert" id="maxBookingError" 
                                     style="position: sticky; top: 20px; z-index: 1000; animation: none !important;"
                                     data-timestamp="<?php echo $error_timestamp; ?>"
                                     data-min-time="<?php echo $min_display_time; ?>">
                                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Maximum Booking Limit Reached for This Date</h5>
                                    <p class="mb-2"><strong>You have reached the maximum booking limit for <?php echo $error_data['check_in_date'] ?? 'this date'; ?>!</strong></p>
                                    <p class="mb-3">You can have up to <strong>3 bookings per day</strong> (same check-in date). You currently have <strong><?php echo $error_data['booking_count']; ?> bookings</strong> for this date.</p>
                                    <hr>
                                    <h6 class="mb-3">Your Existing Bookings for <?php echo $error_data['check_in_date'] ?? 'This Date'; ?>:</h6>
                                    <div class="row">
                                        <?php foreach ($error_data['existing_bookings'] as $index => $booking): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-warning">
                                                <div class="card-body">
                                                    <h6 class="card-title">Booking #<?php echo $index + 1; ?></h6>
                                                    <ul class="list-unstyled small mb-0">
                                                        <li><strong>Room:</strong> <?php echo htmlspecialchars($booking['room_name']); ?></li>
                                                        <li><strong>Room #:</strong> <?php echo htmlspecialchars($booking['room_number']); ?></li>
                                                        <li><strong>Check-in:</strong> <?php echo htmlspecialchars($booking['check_in_date']); ?></li>
                                                        <li><strong>Check-out:</strong> <?php echo htmlspecialchars($booking['check_out_date']); ?></li>
                                                        <li><strong>Reference:</strong> <?php echo htmlspecialchars($booking['reference']); ?></li>
                                                        <li><strong>Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($booking['status']); ?></span></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <hr>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> <strong>To make a new booking for this date:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Cancel one of your existing bookings for this date, or</li>
                                            <li>Choose a different check-in date</li>
                                        </ul>
                                        <p class="mb-0 mt-2"><strong>Note:</strong> You can book up to 3 rooms per day. You can book different dates without any limit.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <a href="my-bookings.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-list"></i> View My Bookings
                                        </a>
                                        <button type="button" class="btn btn-secondary btn-sm" id="dismissErrorBtn" disabled>
                                            <i class="fas fa-times"></i> Dismiss (<span id="countdown"><?php echo max(0, $min_display_time - $time_elapsed); ?></span>s)
                                        </button>
                                    </div>
                                </div>
                                <script>
                                    // Countdown timer for dismiss button
                                    (function() {
                                        const errorDiv = document.getElementById('maxBookingError');
                                        const dismissBtn = document.getElementById('dismissErrorBtn');
                                        const countdownSpan = document.getElementById('countdown');
                                        
                                        const timestamp = parseInt(errorDiv.dataset.timestamp);
                                        const minTime = parseInt(errorDiv.dataset.minTime);
                                        const currentTime = Math.floor(Date.now() / 1000);
                                        let timeElapsed = currentTime - timestamp;
                                        let remainingTime = Math.max(0, minTime - timeElapsed);
                                        
                                        // Update countdown every second
                                        const interval = setInterval(function() {
                                            remainingTime--;
                                            countdownSpan.textContent = remainingTime;
                                            
                                            if (remainingTime <= 0) {
                                                clearInterval(interval);
                                                dismissBtn.disabled = false;
                                                dismissBtn.innerHTML = '<i class="fas fa-times"></i> Dismiss';
                                                dismissBtn.onclick = function() {
                                                    // Clear session error via AJAX
                                                    fetch('api/clear_booking_error.php', {
                                                        method: 'POST'
                                                    }).then(() => {
                                                        errorDiv.style.display = 'none';
                                                    });
                                                };
                                            }
                                        }, 1000);
                                        
                                        // Prevent page refresh from resetting timer
                                        window.addEventListener('beforeunload', function() {
                                            // Timer continues server-side
                                        });
                                    })();
                                </script>
                            <?php elseif ($error === 'ROOM_WAITING_STATE' && isset($_SESSION['room_not_available_error'])): ?>
                                <?php $blocking = $_SESSION['room_not_available_error']; ?>
                                <div class="alert alert-warning" role="alert" id="roomWaitingAlert" style="position: sticky; top: 20px; z-index: 1000; animation: none !important;">
                                    <h5 class="alert-heading"><i class="fas fa-clock"></i> Room Under Waiting State</h5>
                                    <p class="mb-2"><strong>The Room is Under Waiting State, please book another Room!</strong></p>
                                    <p class="mb-3">This room has a pending booking that is awaiting receptionist approval.</p>
                                    <hr>
                                    <h6 class="mb-3">Blocking Booking Details:</h6>
                                    <ul class="mb-3">
                                        <li><strong>Room:</strong> <?php echo htmlspecialchars($blocking['room_name']); ?> (Room <?php echo htmlspecialchars($blocking['room_number']); ?>)</li>
                                        <li><strong>Check-in:</strong> <?php echo htmlspecialchars($blocking['check_in']); ?></li>
                                        <li><strong>Check-out:</strong> <?php echo htmlspecialchars($blocking['check_out']); ?></li>
                                        <li><strong>Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($blocking['status']); ?></span></li>
                                    </ul>
                                    <div class="d-flex gap-2">
                                        <a href="booking.php?clear_error=1" class="btn btn-primary btn-sm">
                                            <i class="fas fa-search"></i> Choose Another Room
                                        </a>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('roomWaitingAlert').style.display='none';">
                                            <i class="fas fa-times"></i> Dismiss
                                        </button>
                                    </div>
                                </div>
                            <?php elseif ($error === 'OVERLAPPING_DATES' && isset($_SESSION['duplicate_booking_error'])): ?>
                                <?php $existing = $_SESSION['duplicate_booking_error']; ?>
                                <div class="alert alert-danger" role="alert" style="position: sticky; top: 20px; z-index: 1000; animation: none !important;">
                                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Booking Not Allowed</h5>
                                    <p class="mb-2"><strong>You already have an active booking for overlapping dates.</strong></p>
                                    <p class="mb-3">Please choose different dates or cancel your existing booking.</p>
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
                                    <label class="form-label fw-bold"><?php echo __('booking.select_room'); ?> *</label>
                                    <select name="room_id" id="roomSelect" class="form-select" required style="font-size: 14px;" data-timestamp="<?php echo time(); ?>">
                                        <option value=""><?php echo __('booking.select_room'); ?>...</option>
                                        
                                        <?php
                                        // Get all rooms from database and group by type
                                        // Force fresh data by clearing any potential caching
                                        $all_rooms = get_all_rooms();
                                        
                                        // Debug: Show total rooms found
                                        if (empty($all_rooms)) {
                                            echo '<option disabled>No active rooms found in database</option>';
                                        } else {
                                            $rooms_by_type = [];
                                            
                                            foreach ($all_rooms as $room) {
                                                $rooms_by_type[$room['name']][] = $room;
                                            }
                                            
                                            // Display rooms grouped by type
                                            foreach ($rooms_by_type as $room_type_name => $rooms_in_type):
                                                $first_room = $rooms_in_type[0];
                                                $price_formatted = number_format($first_room['price'], 2);
                                            ?>
                                            <optgroup label="<?php echo htmlspecialchars($room_type_name); ?> - ETB <?php echo $price_formatted; ?><?php echo __('booking_auth.per_night'); ?>">
                                                <?php foreach ($rooms_in_type as $room): ?>
                                                <option value="<?php echo $room['id']; ?>" 
                                                        data-price="<?php echo $room['price']; ?>" 
                                                        data-capacity="<?php echo $room['capacity']; ?>">
                                                    <?php echo htmlspecialchars($room['name']); ?> Number <?php echo $room['room_number']; ?> - ETB <?php echo number_format($room['price'], 2); ?><?php echo __('booking_auth.per_night'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php 
                                            endforeach;
                                        }
                                        ?>
                                    </select>
                                    <!-- Debug info for admin users -->
                                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                    <small class="text-muted">
                                        Found <?php echo count($all_rooms); ?> active rooms in database. 
                                        Page loaded: <?php echo $page_load_time; ?>
                                        <?php 
                                        if (!empty($all_rooms)) {
                                            $latest_room = end($all_rooms);
                                            echo " | Latest room: " . $latest_room['name'] . " (#" . $latest_room['room_number'] . ")";
                                        }
                                        ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold"><?php echo __('booking.check_in'); ?> *</label>
                                        <input type="date" name="check_in" id="checkIn" class="form-control" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold"><?php echo __('booking.check_out'); ?> *</label>
                                        <input type="date" name="check_out" id="checkOut" class="form-control" 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold"><?php echo __('booking.guests'); ?> *</label>
                                    <input type="number" name="customers" id="customers" class="form-control" 
                                           min="1" max="10" value="1" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold"><?php echo __('booking.special_requests'); ?></label>
                                    <textarea name="special_requests" class="form-control" rows="3" 
                                              placeholder="<?php echo __('booking.special_requests'); ?>..."></textarea>
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
                                    <i class="fas fa-check-circle"></i> <?php echo __('booking.confirm_booking'); ?>
                                </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 100px;">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><?php echo __('booking.booking_summary'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div id="bookingSummary">
                                <p class="text-muted text-center py-4">
                                    <i class="fas fa-info-circle"></i><br>
                                    <?php echo __('booking.select_room'); ?> <?php echo __('booking_auth.and_dates'); ?>
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
        
        // Add cache-busting for room data
        $(document).ready(function() {
            // Force refresh of room dropdown if needed
            if (window.location.search.includes('refresh_rooms=1')) {
                location.reload(true);
            }
        });
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
                                    <span>${formatCurrency(pricePerNight)} × ${nights} <?php echo __('booking_auth.nights'); ?></span>
                                    <span>${formatCurrency(totalPrice)}</span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong><?php echo __('booking_auth.total'); ?>:</strong>
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
            // Auto-select the room in dropdown
            $('#roomSelect').val(<?php echo $selected_room_id; ?>);
            updateSummary();
            <?php endif; ?>
            
            // Handle form submission
            $('#bookingForm').on('submit', function(e) {
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php echo __('booking_auth.processing'); ?>');
            });
        });
    </script>
</body>
</html>
