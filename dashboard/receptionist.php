<?php
// Suppress PHP warnings and notices for production
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('receptionist', '../login.php');

$message = '';
$error = '';

// Handle AJAX requests for check-in/check-out actions
if ($_POST && isset($_POST['action'])) {
    // Clear any output buffers to prevent HTML before JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Prevent caching of AJAX responses
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    try {
    
    switch ($_POST['action']) {
        case 'check_booking_type':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            $query = "SELECT booking_type, 
                             CASE 
                                 WHEN booking_type = 'food_order' THEN 'Food Order'
                                 WHEN booking_type = 'spa_service' THEN 'Spa & Wellness Service'
                                 WHEN booking_type = 'laundry_service' THEN 'Laundry Service'
                                 ELSE 'Room Booking'
                             END as service_name
                      FROM bookings WHERE booking_reference = ? AND status = 'pending'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $booking_ref);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $booking = $result->fetch_assoc();
                echo json_encode([
                    'success' => true, 
                    'booking_type' => $booking['booking_type'],
                    'service_name' => $booking['service_name']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Booking not found or already processed']);
            }
            exit();
            
        case 'checkin':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            $query = "UPDATE bookings SET status = 'checked_in' WHERE booking_reference = '$booking_ref' AND status = 'confirmed'";
            if ($conn->query($query)) {
                // Log the check-in
                $booking_query = "SELECT id, user_id FROM bookings WHERE booking_reference = '$booking_ref'";
                $booking_result = $conn->query($booking_query);
                if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                    log_booking_activity($booking['id'], $booking['user_id'], 'checked_in', 'confirmed', 'checked_in', 'Customer checked in by receptionist', $_SESSION['user_id']);
                }
                echo json_encode(['success' => true, 'message' => 'Customer checked in successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to check in customer']);
            }
            exit();
            
        case 'checkout':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            $query = "UPDATE bookings SET status = 'checked_out' WHERE booking_reference = '$booking_ref' AND status = 'checked_in'";
            if ($conn->query($query)) {
                // Update room status to available
                $room_query = "UPDATE rooms r 
                              JOIN bookings b ON r.id = b.room_id 
                              SET r.status = 'active' 
                              WHERE b.booking_reference = '$booking_ref'";
                $conn->query($room_query);
                
                // Log the check-out
                $booking_query = "SELECT id, user_id FROM bookings WHERE booking_reference = '$booking_ref'";
                $booking_result = $conn->query($booking_query);
                if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                    log_booking_activity($booking['id'], $booking['user_id'], 'checked_out', 'checked_in', 'checked_out', 'Customer checked out by receptionist', $_SESSION['user_id']);
                }
                echo json_encode(['success' => true, 'message' => 'Customer checked out successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to check out customer']);
            }
            exit();
            
        case 'confirm_booking':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            
            // Get booking type first to determine how to handle it
            $type_query = "SELECT booking_type FROM bookings WHERE booking_reference = ? AND status = 'pending'";
            $type_stmt = $conn->prepare($type_query);
            $type_stmt->bind_param("s", $booking_ref);
            $type_stmt->execute();
            $type_result = $type_stmt->get_result();
            
            if ($type_result->num_rows == 0) {
                echo json_encode(['success' => false, 'message' => 'Failed to approve booking or booking already processed']);
                exit();
            }
            
            $booking_type_data = $type_result->fetch_assoc();
            $booking_type = $booking_type_data['booking_type'];
            
            // For food orders and services, just mark as confirmed (not checked_in)
            if ($booking_type == 'food_order' || $booking_type == 'spa_service' || $booking_type == 'laundry_service') {
                $query = "UPDATE bookings SET status = 'confirmed', verified_at = NOW(), verified_by = ? WHERE booking_reference = ? AND status = 'pending'";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("is", $_SESSION['user_id'], $booking_ref);
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Get booking details for logging
                    $booking_query = "SELECT b.id, b.user_id FROM bookings b WHERE b.booking_reference = ?";
                    $log_stmt = $conn->prepare($booking_query);
                    $log_stmt->bind_param("s", $booking_ref);
                    $log_stmt->execute();
                    $booking = $log_stmt->get_result()->fetch_assoc();
                    
                    if ($booking) {
                        log_booking_activity($booking['id'], $booking['user_id'], 'confirmed', 'pending', 'confirmed', 'Order confirmed by receptionist', $_SESSION['user_id']);
                    }
                    
                    // Different success messages for different service types
                    $success_message = '';
                    switch ($booking_type) {
                        case 'food_order':
                            $success_message = 'Food order confirmed successfully! Kitchen has been notified.';
                            break;
                        case 'spa_service':
                            $success_message = 'Spa & Wellness service confirmed successfully! Customer will be notified.';
                            break;
                        case 'laundry_service':
                            $success_message = 'Laundry service confirmed successfully! Service team has been notified.';
                            break;
                        default:
                            $success_message = 'Service confirmed successfully!';
                    }
                    
                    // Add timestamp for debugging
                    error_log("Receptionist confirmation - Type: $booking_type, Message: $success_message");
                    
                    echo json_encode(['success' => true, 'message' => $success_message]);
                } else {
                    // Different error messages for different service types
                    $error_message = '';
                    switch ($booking_type) {
                        case 'food_order':
                            $error_message = 'Failed to confirm food order or order already processed';
                            break;
                        case 'spa_service':
                            $error_message = 'Failed to confirm spa service or service already processed';
                            break;
                        case 'laundry_service':
                            $error_message = 'Failed to confirm laundry service or service already processed';
                            break;
                        default:
                            $error_message = 'Failed to confirm service or service already processed';
                    }
                    
                    echo json_encode(['success' => false, 'message' => $error_message]);
                }
                exit();
            }
            
            // For room bookings: pending → checked_in
            $query = "UPDATE bookings SET status = 'checked_in', actual_checkin_time = NOW(), checked_in_by = ? WHERE booking_reference = ? AND status = 'pending'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("is", $_SESSION['user_id'], $booking_ref);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Get booking details for checkin record
                $booking_query = "SELECT b.*, r.name as room_name, r.room_number, 
                                 CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                                 u.email, u.phone
                                 FROM bookings b
                                 LEFT JOIN rooms r ON b.room_id = r.id
                                 JOIN users u ON b.user_id = u.id
                                 WHERE b.booking_reference = ?";
                $log_stmt = $conn->prepare($booking_query);
                $log_stmt->bind_param("s", $booking_ref);
                $log_stmt->execute();
                $booking = $log_stmt->get_result()->fetch_assoc();
                
                if ($booking) {
                    // Update room status to 'occupied' when receptionist approves
                    if (!empty($booking['room_id'])) {
                        $room_occupied_query = "UPDATE rooms SET status = 'occupied' WHERE id = ?";
                        $room_occupied_stmt = $conn->prepare($room_occupied_query);
                        $room_occupied_stmt->bind_param("i", $booking['room_id']);
                        $room_occupied_stmt->execute();
                        error_log("Room status updated to 'occupied' after receptionist approval for room ID: " . $booking['room_id']);
                    }
                    
                    // Log the activity
                    log_booking_activity($booking['id'], $booking['user_id'], 'checked_in', 'pending', 'checked_in', 'Booking approved and checked in by receptionist', $_SESSION['user_id']);
                    
                    // Create checkin record only for room bookings
                    $nights = (int)((strtotime($booking['check_out_date']) - strtotime($booking['check_in_date'])) / (60 * 60 * 24));
                    $confirmation_number = 'CHK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    
                    $checkin_insert = $conn->prepare("
                        INSERT INTO checkins (
                            customer_id, booking_id, hotel_name, hotel_location, 
                            check_in_date, check_out_date,
                            guest_full_name, guest_date_of_birth, guest_id_type, guest_id_number, 
                            guest_nationality, guest_home_address, guest_phone_number, guest_email_address,
                            room_type, room_number, nights_stay, number_of_guests, rate_per_night,
                            payment_type, amount_paid, balance_due, confirmation_number, 
                            additional_requests, checked_in_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $hotel_name = 'Harar Ras Hotel';
                    $hotel_location = 'Jugol Street, Harar, Ethiopia';
                    $guest_dob = '1990-01-01';
                    $id_type = $booking['id_type'] ?? 'national_id';
                    $id_number = $booking['id_number'] ?? 'N/A';
                    $nationality = 'Ethiopian';
                    $address = $booking['special_requests'] ?? 'N/A';
                    $payment_type = $booking['payment_method'] ?? 'cash';
                    $amount_paid = (float)$booking['total_price'];
                    $balance_due = 0.00;
                    $additional_requests = $booking['special_requests'] ?? '';
                    $number_of_guests = (int)$booking['customers'];
                    $rate_per_night = (float)$booking['total_price'];
                    $user_id = (int)$booking['user_id'];
                    $checked_in_by = (int)$_SESSION['user_id'];
                    
                    $customer_phone = $booking['phone'] ?? 'N/A';
                    $customer_email = $booking['email'] ?? 'noemail@example.com';
                    $customer_name = $booking['customer_name'] ?? 'Customer';
                    $room_name = $booking['room_name'] ?? 'Standard Room';
                    $room_number = $booking['room_number'] ?? 'N/A';
                    
                    $checkin_insert->bind_param(
                        "iissssssssssssssiidsddssi",
                        $user_id, $booking['id'], $hotel_name, $hotel_location,
                        $booking['check_in_date'], $booking['check_out_date'],
                        $customer_name, $guest_dob, $id_type, $id_number,
                        $nationality, $address, $customer_phone, $customer_email,
                        $room_name, $room_number, $nights, $number_of_guests,
                        $rate_per_night, $payment_type, $amount_paid, $balance_due,
                        $confirmation_number, $additional_requests, $checked_in_by
                    );
                    
                    $checkin_insert->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Room booking approved and customer checked in successfully! Room is now occupied.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve room booking or booking already processed']);
            }
            exit();
            
        case 'cancel_booking':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            
            // Get booking type first to determine appropriate message
            $type_query = "SELECT booking_type FROM bookings WHERE booking_reference = ? AND status = 'pending'";
            $type_stmt = $conn->prepare($type_query);
            $type_stmt->bind_param("s", $booking_ref);
            $type_stmt->execute();
            $type_result = $type_stmt->get_result();
            
            if ($type_result->num_rows == 0) {
                echo json_encode(['success' => false, 'message' => 'Booking not found or already processed']);
                exit();
            }
            
            $booking_type_data = $type_result->fetch_assoc();
            $booking_type = $booking_type_data['booking_type'];
            
            $query = "UPDATE bookings SET status = 'cancelled' WHERE booking_reference = ? AND status = 'pending'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $booking_ref);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Log the cancellation
                $booking_query = "SELECT id, user_id FROM bookings WHERE booking_reference = ?";
                $log_stmt = $conn->prepare($booking_query);
                $log_stmt->bind_param("s", $booking_ref);
                $log_stmt->execute();
                $booking_result = $log_stmt->get_result();
                
                if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                    log_booking_activity($booking['id'], $booking['user_id'], 'cancelled', 'pending', 'cancelled', 'Booking cancelled by receptionist', $_SESSION['user_id']);
                }
                
                // Different success messages for different service types
                $success_message = '';
                switch ($booking_type) {
                    case 'room':
                        $success_message = 'Room booking cancelled successfully. Room is now available for other guests.';
                        break;
                    case 'food_order':
                        $success_message = 'Food order cancelled successfully. Kitchen has been notified.';
                        break;
                    case 'spa_service':
                        $success_message = 'Spa & Wellness service cancelled successfully. Time slot is now available.';
                        break;
                    case 'laundry_service':
                        $success_message = 'Laundry service cancelled successfully. Service team has been notified.';
                        break;
                    default:
                        $success_message = 'Booking cancelled successfully.';
                }
                
                echo json_encode(['success' => true, 'message' => $success_message]);
            } else {
                // Different error messages for different service types
                $error_message = '';
                switch ($booking_type) {
                    case 'room':
                        $error_message = 'Failed to cancel room booking';
                        break;
                    case 'food_order':
                        $error_message = 'Failed to cancel food order';
                        break;
                    case 'spa_service':
                        $error_message = 'Failed to cancel spa service';
                        break;
                    case 'laundry_service':
                        $error_message = 'Failed to cancel laundry service';
                        break;
                    default:
                        $error_message = 'Failed to cancel booking';
                }
                
                echo json_encode(['success' => false, 'message' => $error_message]);
            }
            exit();
    }
    
    } catch (Exception $e) {
        // Clean output buffer and send error as JSON
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
    
    // Clean output buffer and send final response
    $output = ob_get_clean();
    echo $output;
    exit();
}

// Get today's check-ins and check-outs
$today = date('Y-m-d');

// Today's Check-ins (confirmed bookings scheduled for today OR already checked in today) - ALL TYPES
$checkins = $conn->query("
    SELECT b.*, 
           COALESCE(r.name, '') as room_name, 
           COALESCE(r.room_number, '') as room_number, 
           COALESCE(r.price, 0) as price,
           CONCAT(u.first_name, ' ', u.last_name) as customer_name,
           u.email, u.phone,
           DATEDIFF(b.check_out_date, b.check_in_date) as nights,
           b.payment_status, b.payment_method, b.total_price,
           COALESCE(b.customers, 1) as num_customers,
           b.verification_status, b.status as booking_status,
           fo.order_reference as food_order_ref,
           fo.reservation_date as food_date,
           fo.reservation_time as food_time,
           sb.service_name,
           sb.service_category,
           sb.service_date,
           sb.service_time
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.user_id = u.id 
    LEFT JOIN food_orders fo ON b.id = fo.booking_id
    LEFT JOIN service_bookings sb ON b.id = sb.booking_id
    WHERE (
        (DATE(b.check_in_date) = '$today' AND b.status IN ('confirmed', 'checked_in'))
        OR 
        (DATE(b.actual_checkin_time) = '$today' AND b.status = 'checked_in')
        OR
        (DATE(fo.reservation_date) = '$today' AND b.status = 'confirmed')
        OR
        (DATE(sb.service_date) = '$today' AND b.status = 'confirmed')
    )
    ORDER BY b.created_at DESC
");

// Debug: Get all bookings for today regardless of status
$debug_query = $conn->query("
    SELECT b.id, b.booking_reference, b.check_in_date, b.status, b.verification_status, b.payment_status, b.booking_type,
           CONCAT(u.first_name, ' ', u.last_name) as guest_name
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    WHERE (DATE(b.check_in_date) = '$today' OR DATE(b.created_at) = '$today')
    ORDER BY b.created_at DESC
");

$debug_bookings = [];
if ($debug_query) {
    while ($row = $debug_query->fetch_assoc()) {
        $debug_bookings[] = $row;
    }
}

// Today's Check-outs (checked-in guests scheduled to leave today)
$checkouts = $conn->query("
    SELECT b.*, 
           COALESCE(r.name, 'Food Order') as room_name, 
           COALESCE(r.room_number, 'N/A') as room_number,
           b.customer_name as guest_name, b.customer_email as email, b.customer_phone as phone,
           DATEDIFF(b.check_out_date, b.check_in_date) as nights,
           b.payment_status, b.total_price, b.incidental_deposit,
           b.actual_checkin_time
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_id = r.id 
    WHERE b.check_out_date = '$today' AND b.status = 'checked_in'
    ORDER BY b.actual_checkin_time DESC
");

// Get pending bookings (awaiting confirmation) - all types
$pending_bookings = $conn->query("
    SELECT b.*, 
           COALESCE(r.name, '') as room_name, 
           COALESCE(r.room_number, '') as room_number, 
           COALESCE(r.price, 0) as price,
           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
           u.email, u.phone,
           DATEDIFF(b.check_out_date, b.check_in_date) as nights,
           b.payment_status,
           fo.order_reference as food_order_ref,
           sb.service_name,
           sb.service_category
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.user_id = u.id 
    LEFT JOIN food_orders fo ON b.id = fo.booking_id
    LEFT JOIN service_bookings sb ON b.id = sb.booking_id
    WHERE b.status = 'pending'
    ORDER BY b.created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Service Type Badge Styling */
        .service-type-badge {
            display: inline-block;
            max-width: 250px;
            white-space: normal !important;
            line-height: 1.3;
            padding: 0.5rem 0.75rem;
            text-align: center;
            min-height: 60px;
        }
        
        .service-type-badge strong {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }
        
        .service-type-badge small {
            display: block;
            font-size: 0.75rem;
            line-height: 1.3;
            white-space: normal !important;
            word-wrap: break-word;
        }
        
        /* Make table cells more compact */
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-gold"></i> Harar Ras Hotel - Reception
            </a>
            <div class="navbar-nav ms-auto">
                
                <span class="navbar-text me-3">
                    <i class="fas fa-user-tie"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Receptionist)
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-concierge-bell"></i> Reception Menu</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="receptionist.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tachometer-alt"></i> Dashboard Overview
                        </a>
                        <a href="customer-checkin.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-plus"></i> Customer Check-In
                        </a>
                        <a href="receptionist-checkout.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-minus-circle"></i> Process Check-out
                        </a>
                        <a href="receptionist-pending.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-check"></i> Pending Bookings
                        </a>
                        <a href="receptionist-rooms.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-bed"></i> Manage Rooms
                        </a>
                        <a href="receptionist-services.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-utensils"></i> Manage Foods & Services
                        </a>
                        <a href="../generate_bill.php" class="list-group-item list-group-item-action" target="_blank">
                            <i class="fas fa-file-invoice-dollar"></i> Generate Bill
                        </a>
                        </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                
                <!-- Success Message Display -->
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                    echo htmlspecialchars($_SESSION['success_message']); 
                    unset($_SESSION['success_message']); // Clear the message after displaying
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php elseif (isset($_GET['checkin_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4">
                    <i class="fas fa-check-circle me-2"></i>
                    Check-in completed successfully! The customer has been checked in and the room is now occupied.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Today's Check-ins -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-sign-in-alt"></i> Today's Check-ins (<?php echo date('F j, Y'); ?>)
                            <span class="badge bg-light text-success float-end"><?php echo $checkins->num_rows; ?> Customer(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($checkins->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Service Type</th>
                                        <th>Nights</th>
                                        <th>Total Amount</th>
                                        <th>Payment Status</th>
                                        <th>Check-in Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $checkins->fetch_assoc()): 
                                        // Determine service type display
                                        $booking_type = $booking['booking_type'];
                                        $service_display = '';
                                        $service_badge_class = 'bg-secondary';
                                        
                                        if ($booking_type == 'room') {
                                            $room_name = htmlspecialchars($booking['room_name']);
                                            $room_number = trim($booking['room_number']);
                                            
                                            // Show room name and number
                                            if (!empty($room_number) && $room_number != 'N/A' && $room_number != '0') {
                                                $service_display = '<strong style="color: white;">Room</strong><br><span style="font-size: 13px; color: white;">' . $room_name . '</span><br><span style="font-weight: 700; color: white; font-size: 16px;">#' . htmlspecialchars($room_number) . '</span>';
                                            } else {
                                                $service_display = '<strong style="color: white;">Room</strong><br><small style="color: white;">' . $room_name . '</small>';
                                            }
                                            $service_badge_class = 'bg-primary';
                                        } elseif ($booking_type == 'food_order') {
                                            // Get food items for this order
                                            $food_items_query = "SELECT GROUP_CONCAT(item_name SEPARATOR ', ') as items 
                                                                FROM food_order_items 
                                                                WHERE order_id = (SELECT id FROM food_orders WHERE booking_id = {$booking['id']} LIMIT 1)";
                                            $food_result = $conn->query($food_items_query);
                                            $food_data = $food_result ? $food_result->fetch_assoc() : null;
                                            $items_text = $food_data['items'] ?? 'Food items';
                                            // Show all items without truncation
                                            $service_display = '<strong>Food Order</strong><br><small style="white-space: normal; line-height: 1.4;">' . htmlspecialchars($items_text) . '</small>';
                                            $service_badge_class = 'bg-success';
                                        } elseif ($booking_type == 'spa_service') {
                                            $service_display = '<strong>Spa & Wellness</strong><br><small>' . htmlspecialchars($booking['service_name'] ?? 'Spa service') . '</small>';
                                            $service_badge_class = 'bg-info';
                                        } elseif ($booking_type == 'laundry_service') {
                                            $service_display = '<strong>Laundry Service</strong><br><small>' . htmlspecialchars($booking['service_name'] ?? 'Laundry') . '</small>';
                                            $service_badge_class = 'bg-warning text-dark';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email'] ?? ''); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge service-type-badge <?php echo $service_badge_class; ?>">
                                                <?php echo $service_display; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $booking['nights'] ? $booking['nights'] . ' night(s)' : 'N/A'; ?></td>
                                        <td>
                                            <strong><?php echo format_currency($booking['total_price']); ?></strong>
                                            <?php if ($booking_type == 'room' && $booking['price'] > 0): ?>
                                            <br><small class="text-muted"><?php echo format_currency($booking['price']); ?>/night</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $payment_status = $booking['payment_status'] ?? 'pending';
                                            $verification_status = $booking['verification_status'] ?? 'pending';
                                            
                                            // Determine badge based on both payment_status and verification_status
                                            if ($payment_status == 'paid' || $verification_status == 'verified') {
                                                $badge_class = 'success';
                                                $status_text = '✅ Prepaid';
                                            } elseif ($verification_status == 'pending_verification') {
                                                $badge_class = 'info';
                                                $status_text = '⏳ Verifying';
                                            } else {
                                                $badge_class = 'warning';
                                                $status_text = '⏳ Payment Due';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                            <?php if ($booking['payment_method']): ?>
                                            <br><small class="text-muted"><?php echo ucfirst($booking['payment_method']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] == 'checked_in'): ?>
                                                <span class="badge bg-success" style="font-size: 0.9rem; padding: 0.5rem;">
                                                    <i class="fas fa-check-circle"></i> 
                                                    <?php echo $booking_type == 'room' ? 'Checked In' : 'Completed'; ?>
                                                </span>
                                                <?php if (!empty($booking['actual_checkin_time'])): ?>
                                                <br><small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($booking['actual_checkin_time'])); ?>
                                                </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($booking_type == 'room'): ?>
                                                    <a href="receptionist-checkin.php?booking_ref=<?php echo urlencode($booking['booking_reference']); ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-key"></i> Check In
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-info" style="font-size: 0.9rem; padding: 0.5rem;">
                                                        <i class="fas fa-clock"></i> Ready
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No check-ins scheduled for today.
                            
                            <?php if (!empty($debug_bookings)): ?>
                            <hr>
                            <strong>Debug Info - Bookings for today (<?php echo $today; ?>):</strong>
                            <ul class="mt-2 mb-0">
                                <?php foreach ($debug_bookings as $db): ?>
                                <li>
                                    <strong><?php echo $db['booking_reference']; ?></strong> - <?php echo $db['guest_name']; ?><br>
                                    <small>
                                        Status: <span class="badge bg-secondary"><?php echo $db['status']; ?></span>
                                        Verification: <span class="badge bg-secondary"><?php echo $db['verification_status']; ?></span>
                                        Payment: <span class="badge bg-secondary"><?php echo $db['payment_status']; ?></span>
                                    </small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <small class="text-muted">
                                <strong>Note:</strong> Bookings only appear in check-ins when Status=confirmed AND Verification=verified
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Today's Check-outs -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-sign-out-alt"></i> Today's Check-outs
                            <span class="badge bg-dark float-end"><?php echo $checkouts->num_rows; ?> Customer(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($checkouts->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Room</th>
                                        <th>Stay Duration</th>
                                        <th>Total Amount</th>
                                        <th>Deposit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $checkouts->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-warning"><?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email'] ?? ''); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo htmlspecialchars($booking['room_name'] ?? ''); ?><br>
                                                Room <?php echo htmlspecialchars($booking['room_number'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $booking['nights']; ?> night(s)<br>
                                            <small class="text-muted">
                                                <?php echo $booking['check_in_date'] ? date('M j', strtotime($booking['check_in_date'])) : 'N/A'; ?> - 
                                                <?php echo $booking['check_out_date'] ? date('M j', strtotime($booking['check_out_date'])) : 'N/A'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo format_currency($booking['total_price']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo format_currency($booking['incidental_deposit'] ?? 0); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="receptionist-checkout.php" class="btn btn-warning btn-sm">
                                                <i class="fas fa-receipt"></i> Check Out
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No check-outs scheduled for today.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Bookings - Separate sections for different service types -->
                
                <!-- Pending Room Bookings -->
                <?php 
                // Get pending room bookings
                $pending_rooms = $conn->query("
                    SELECT b.*, 
                           r.name as room_name, 
                           r.room_number, 
                           r.price,
                           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                           u.email, u.phone,
                           DATEDIFF(b.check_out_date, b.check_in_date) as nights,
                           b.payment_status, b.verification_status
                    FROM bookings b 
                    JOIN rooms r ON b.room_id = r.id 
                    JOIN users u ON b.user_id = u.id 
                    WHERE b.status = 'pending' AND b.booking_type = 'room'
                    ORDER BY b.created_at DESC
                ");
                
                // Get pending food orders
                $pending_food = $conn->query("
                    SELECT b.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                           u.email, u.phone,
                           fo.reservation_date, fo.reservation_time,
                           b.payment_status, b.verification_status
                    FROM bookings b 
                    JOIN users u ON b.user_id = u.id 
                    JOIN food_orders fo ON b.id = fo.booking_id
                    WHERE b.status = 'pending' AND b.booking_type = 'food_order'
                    ORDER BY b.created_at DESC
                ");
                
                // Get pending spa services
                $pending_spa = $conn->query("
                    SELECT b.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                           u.email, u.phone,
                           sb.service_name, sb.service_date, sb.service_time,
                           b.payment_status, b.verification_status
                    FROM bookings b 
                    JOIN users u ON b.user_id = u.id 
                    JOIN service_bookings sb ON b.id = sb.booking_id
                    WHERE b.status = 'pending' AND b.booking_type = 'spa_service'
                    ORDER BY b.created_at DESC
                ");
                
                // Get pending laundry services
                $pending_laundry = $conn->query("
                    SELECT b.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                           u.email, u.phone,
                           sb.service_name, sb.service_date, sb.service_time,
                           b.payment_status, b.verification_status
                    FROM bookings b 
                    JOIN users u ON b.user_id = u.id 
                    JOIN service_bookings sb ON b.id = sb.booking_id
                    WHERE b.status = 'pending' AND b.booking_type = 'laundry_service'
                    ORDER BY b.created_at DESC
                ");
                ?>
                
                <!-- Pending Room Bookings -->
                <?php if ($pending_rooms->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-bed"></i> Pending Room Bookings (Awaiting Confirmation)
                            <span class="badge bg-light text-primary float-end"><?php echo $pending_rooms->num_rows; ?> Booking(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Room Type</th>
                                        <th>Check-in Date</th>
                                        <th>Nights</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $pending_rooms->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($booking['booking_reference']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email']); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary service-type-badge">
                                                <strong>Room</strong><br>
                                                <small><?php echo htmlspecialchars($booking['room_name']); ?></small><br>
                                                <strong>#<?php echo htmlspecialchars($booking['room_number']); ?></strong>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></td>
                                        <td><?php echo $booking['nights']; ?> night(s)</td>
                                        <td><strong><?php echo format_currency($booking['total_price']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $verification_status = $booking['verification_status'];
                                            if ($verification_status == 'verified') {
                                                echo '<span class="badge bg-success">Verified</span>';
                                            } elseif ($verification_status == 'pending_verification') {
                                                echo '<span class="badge bg-info">Verifying</span>';
                                            } else {
                                                echo '<span class="badge bg-warning">Pending</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="receptionist-checkin.php?booking_ref=<?php echo urlencode($booking['booking_reference']); ?>" class="btn btn-success">
                                                    <i class="fas fa-key"></i> Check-in
                                                </a>
                                                <button class="btn btn-danger" onclick="cancelBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Pending Food Orders -->
                <?php if ($pending_food->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-utensils"></i> Pending Food Orders (Awaiting Confirmation)
                            <span class="badge bg-light text-success float-end"><?php echo $pending_food->num_rows; ?> Order(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order Ref</th>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Food Items</th>
                                        <th>Reservation Date</th>
                                        <th>Reservation Time</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $pending_food->fetch_assoc()): 
                                        // Get food items for this order
                                        $food_items_query = "SELECT GROUP_CONCAT(item_name SEPARATOR ', ') as items 
                                                            FROM food_order_items 
                                                            WHERE order_id = (SELECT id FROM food_orders WHERE booking_id = {$booking['id']} LIMIT 1)";
                                        $food_result = $conn->query($food_items_query);
                                        $food_data = $food_result ? $food_result->fetch_assoc() : null;
                                        $items_text = $food_data['items'] ?? 'Food items';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong class="text-success"><?php echo htmlspecialchars($booking['booking_reference']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email']); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success service-type-badge">
                                                <strong>Food Order</strong><br>
                                                <small style="white-space: normal; line-height: 1.4;"><?php echo htmlspecialchars($items_text); ?></small>
                                            </span>
                                        </td>
                                        <td><?php echo $booking['reservation_date'] ? date('M j, Y', strtotime($booking['reservation_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $booking['reservation_time'] ? date('g:i A', strtotime($booking['reservation_time'])) : 'N/A'; ?></td>
                                        <td><strong><?php echo format_currency($booking['total_price']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $verification_status = $booking['verification_status'];
                                            if ($verification_status == 'verified') {
                                                echo '<span class="badge bg-success">Verified</span>';
                                            } elseif ($verification_status == 'pending_verification') {
                                                echo '<span class="badge bg-info">Verifying</span>';
                                            } else {
                                                echo '<span class="badge bg-warning">Pending</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success" onclick="confirmBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                                <button class="btn btn-danger" onclick="cancelBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Pending Spa & Wellness Services -->
                <?php if ($pending_spa->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-spa"></i> Pending Spa & Wellness Services (Awaiting Confirmation)
                            <span class="badge bg-light text-info float-end"><?php echo $pending_spa->num_rows; ?> Service(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Service Ref</th>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Service Type</th>
                                        <th>Service Date</th>
                                        <th>Service Time</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $pending_spa->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-info"><?php echo htmlspecialchars($booking['booking_reference']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email']); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info service-type-badge">
                                                <strong>Spa & Wellness</strong><br>
                                                <small><?php echo htmlspecialchars($booking['service_name']); ?></small>
                                            </span>
                                        </td>
                                        <td><?php echo $booking['service_date'] ? date('M j, Y', strtotime($booking['service_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $booking['service_time'] ? date('g:i A', strtotime($booking['service_time'])) : 'N/A'; ?></td>
                                        <td><strong><?php echo format_currency($booking['total_price']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $verification_status = $booking['verification_status'];
                                            if ($verification_status == 'verified') {
                                                echo '<span class="badge bg-success">Verified</span>';
                                            } elseif ($verification_status == 'pending_verification') {
                                                echo '<span class="badge bg-info">Verifying</span>';
                                            } else {
                                                echo '<span class="badge bg-warning">Pending</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success" onclick="confirmBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                                <button class="btn btn-danger" onclick="cancelBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Pending Laundry Services -->
                <?php if ($pending_laundry->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-tshirt"></i> Pending Laundry Services (Awaiting Confirmation)
                            <span class="badge bg-dark text-warning float-end"><?php echo $pending_laundry->num_rows; ?> Service(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Service Ref</th>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Service Type</th>
                                        <th>Service Date</th>
                                        <th>Service Time</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $pending_laundry->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-warning"><?php echo htmlspecialchars($booking['booking_reference']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email']); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark service-type-badge">
                                                <strong>Laundry Service</strong><br>
                                                <small><?php echo htmlspecialchars($booking['service_name']); ?></small>
                                            </span>
                                        </td>
                                        <td><?php echo $booking['service_date'] ? date('M j, Y', strtotime($booking['service_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $booking['service_time'] ? date('g:i A', strtotime($booking['service_time'])) : 'N/A'; ?></td>
                                        <td><strong><?php echo format_currency($booking['total_price']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $verification_status = $booking['verification_status'];
                                            if ($verification_status == 'verified') {
                                                echo '<span class="badge bg-success">Verified</span>';
                                            } elseif ($verification_status == 'pending_verification') {
                                                echo '<span class="badge bg-info">Verifying</span>';
                                            } else {
                                                echo '<span class="badge bg-warning">Pending</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success" onclick="confirmBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                                <button class="btn btn-danger" onclick="cancelBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Show message if no pending bookings -->
                <?php if ($pending_rooms->num_rows == 0 && $pending_food->num_rows == 0 && $pending_spa->num_rows == 0 && $pending_laundry->num_rows == 0): ?>
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> Pending Bookings & Services
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No pending bookings or services at the moment.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modals -->
    <!-- Room Booking Confirmation Modal -->
    <div class="modal fade" id="roomBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-bed me-2"></i>Room Booking Confirmation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-bed fa-3x text-primary mb-3"></i>
                        <h6>Room Booking Detected</h6>
                        <p class="text-muted">This is a room booking that requires detailed check-in process.</p>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Step:</strong> You will be redirected to the detailed check-in form where you can collect customer information, payment, and issue room keys.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="proceedToCheckin">
                        <i class="fas fa-arrow-right me-2"></i>Proceed to Check-in Form
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Service Booking Confirmation Modal -->
    <div class="modal fade" id="serviceBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>Service Confirmation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-concierge-bell fa-3x text-success mb-3"></i>
                        <h6 id="serviceTitle">Confirm Service</h6>
                        <p class="text-muted" id="serviceDescription">Are you sure you want to confirm this service booking?</p>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Once confirmed, the service provider will be notified and the booking cannot be easily cancelled.
                    </div>
                    <div class="booking-details bg-light p-3 rounded">
                        <strong>Booking Reference:</strong> <span id="modalBookingRef" class="text-primary"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="confirmServiceBtn">
                        <i class="fas fa-check me-2"></i>Confirm Service
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Cancel Booking
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                        <h6>Cancel Booking Confirmation</h6>
                        <p class="text-muted">Are you sure you want to cancel this booking?</p>
                    </div>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. The customer will be notified of the cancellation.
                    </div>
                    <div class="booking-details bg-light p-3 rounded">
                        <strong>Booking Reference:</strong> <span id="cancelBookingRef" class="text-danger"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-arrow-left me-2"></i>Keep Booking
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmCancelBtn">
                        <i class="fas fa-trash me-2"></i>Cancel Booking
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Check-in Modal -->
    <div class="modal fade" id="checkinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-sign-in-alt me-2"></i>Customer Check-in
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                        <h6>Check-in Customer</h6>
                        <p class="text-muted">Confirm check-in for this customer?</p>
                    </div>
                    <div class="booking-details bg-light p-3 rounded">
                        <strong>Booking Reference:</strong> <span id="checkinBookingRef" class="text-success"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="confirmCheckinBtn">
                        <i class="fas fa-check me-2"></i>Check-in Customer
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Check-out Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-sign-out-alt me-2"></i>Customer Check-out
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-user-minus fa-3x text-warning mb-3"></i>
                        <h6>Check-out Customer</h6>
                        <p class="text-muted">Confirm check-out for this customer?</p>
                    </div>
                    <div class="booking-details bg-light p-3 rounded">
                        <strong>Booking Reference:</strong> <span id="checkoutBookingRef" class="text-warning"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-warning" id="confirmCheckoutBtn">
                        <i class="fas fa-check me-2"></i>Check-out Customer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables for modal handling
        let currentBookingRef = '';
        let currentServiceData = {};
        
        function checkIn(ref) {
            currentBookingRef = ref;
            document.getElementById('checkinBookingRef').textContent = ref;
            new bootstrap.Modal(document.getElementById('checkinModal')).show();
        }
        
        // Handle check-in confirmation
        document.getElementById('confirmCheckinBtn').addEventListener('click', function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('checkinModal'));
            modal.hide();
            
            fetch('receptionist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=checkin&booking_ref=' + currentBookingRef
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    location.reload();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error: ' + error, 'danger');
            });
        });
        
        function checkOut(ref) {
            currentBookingRef = ref;
            document.getElementById('checkoutBookingRef').textContent = ref;
            new bootstrap.Modal(document.getElementById('checkoutModal')).show();
        }
        
        // Handle check-out confirmation
        document.getElementById('confirmCheckoutBtn').addEventListener('click', function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
            modal.hide();
            
            fetch('receptionist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=checkout&booking_ref=' + currentBookingRef
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    location.reload();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error: ' + error, 'danger');
            });
        });
        
        function confirmBooking(ref) {
            // First check if this is a room booking that needs detailed check-in
            const timestamp = new Date().getTime();
            fetch('receptionist.php?t=' + timestamp, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                },
                body: 'action=check_booking_type&booking_ref=' + ref
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentBookingRef = ref;
                    currentServiceData = data;
                    
                    if (data.booking_type === 'room') {
                        // Show room booking modal
                        new bootstrap.Modal(document.getElementById('roomBookingModal')).show();
                    } else {
                        // Show service booking modal
                        document.getElementById('serviceTitle').textContent = 'Confirm ' + data.service_name;
                        document.getElementById('serviceDescription').textContent = 'Are you sure you want to confirm this ' + data.service_name.toLowerCase() + '?';
                        document.getElementById('modalBookingRef').textContent = ref;
                        new bootstrap.Modal(document.getElementById('serviceBookingModal')).show();
                    }
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showAlert('Error: ' + error, 'danger');
            });
        }
        
        // Handle room booking confirmation (redirect to check-in form)
        document.getElementById('proceedToCheckin').addEventListener('click', function() {
            window.location.href = 'receptionist-checkin.php?booking_ref=' + currentBookingRef;
        });
        
        // Handle service booking confirmation
        document.getElementById('confirmServiceBtn').addEventListener('click', function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('serviceBookingModal'));
            modal.hide();
            confirmNonRoomBooking(currentBookingRef);
        });
        
        function confirmNonRoomBooking(ref) {
            // Handle confirmation for non-room bookings (food, spa, laundry)
            const timestamp = new Date().getTime();
            fetch('receptionist.php?t=' + timestamp, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                },
                body: 'action=confirm_booking&booking_ref=' + ref
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showAlert(data.message, 'success');
                    location.reload();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showAlert('Error: ' + error, 'danger');
            });
        }
        
        function cancelBooking(ref) {
            currentBookingRef = ref;
            document.getElementById('cancelBookingRef').textContent = ref;
            new bootstrap.Modal(document.getElementById('cancelBookingModal')).show();
        }
        
        // Handle cancel booking confirmation
        document.getElementById('confirmCancelBtn').addEventListener('click', function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('cancelBookingModal'));
            modal.hide();
            
            fetch('receptionist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=cancel_booking&booking_ref=' + currentBookingRef
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    location.reload();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error: ' + error, 'danger');
            });
        });
        
        // Helper function to show alerts
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at the top of the main content
            const mainContent = document.querySelector('.col-md-9.col-lg-10');
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>