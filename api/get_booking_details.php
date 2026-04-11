<?php
/**
 * Get Booking Details API
 * Returns comprehensive booking information for staff dashboards
 */

header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
    require_once '../includes/auth.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error: ' . $e->getMessage()
    ]);
    exit;
}

// Check if user is logged in and has appropriate role
if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

$user_role = get_user_role();
if (!in_array($user_role, ['admin', 'manager', 'receptionist'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient permissions'
    ]);
    exit;
}

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get booking ID
$booking_id = $_GET['id'] ?? 0;

if (!$booking_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

// Get comprehensive booking details
$query = "SELECT b.*, 
          COALESCE(r.name, 'N/A') as room_name, 
          COALESCE(r.room_number, 'N/A') as room_number, 
          u.first_name, u.last_name, u.email, u.phone,
          fo.order_reference as food_order_ref,
          fo.table_reservation,
          fo.reservation_date as food_date,
          fo.reservation_time as food_time,
          fo.guests as food_guests,
          sb.service_name,
          sb.service_category,
          sb.service_date,
          sb.service_time,
          sb.quantity as service_quantity,
          pmi.method_name as payment_method_name,
          pmi.bank_name
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id 
          LEFT JOIN users u ON b.user_id = u.id 
          LEFT JOIN food_orders fo ON b.id = fo.booking_id
          LEFT JOIN service_bookings sb ON b.id = sb.booking_id
          LEFT JOIN payment_method_instructions pmi ON b.payment_method = pmi.method_code
          WHERE b.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found'
    ]);
    exit;
}

// Format the booking data for display
$booking_type_display = [
    'room' => 'Room Booking',
    'food_order' => 'Food Order',
    'spa_service' => 'Spa Service',
    'laundry_service' => 'Laundry Service'
];

$verification_status_colors = [
    'pending_payment' => 'warning',
    'pending_verification' => 'info',
    'verified' => 'success',
    'rejected' => 'danger',
    'expired' => 'secondary'
];

$verification_status_display = [
    'pending_payment' => 'Awaiting Payment',
    'pending_verification' => 'Pending Verification',
    'verified' => 'Verified',
    'rejected' => 'Rejected',
    'expired' => 'Expired'
];

// Format dates
$created_at_formatted = date('M j, Y g:i A', strtotime($booking['created_at']));
$screenshot_uploaded_at_formatted = $booking['screenshot_uploaded_at'] ? 
    date('M j, Y g:i A', strtotime($booking['screenshot_uploaded_at'])) : null;

// Format check-in/out dates for room bookings
$check_in_date = $booking['check_in_date'] ? date('M j, Y', strtotime($booking['check_in_date'])) : null;
$check_out_date = $booking['check_out_date'] ? date('M j, Y', strtotime($booking['check_out_date'])) : null;

// Format reservation date/time for food orders
$reservation_date = $booking['food_date'] ? date('M j, Y', strtotime($booking['food_date'])) : null;
$reservation_time = $booking['food_time'] ? date('g:i A', strtotime($booking['food_time'])) : null;

// Format service date/time for spa/laundry
$service_date = $booking['service_date'] ? date('M j, Y', strtotime($booking['service_date'])) : null;
$service_time = $booking['service_time'] ? date('g:i A', strtotime($booking['service_time'])) : null;

// Prepare response data
$response_data = [
    'success' => true,
    'booking' => [
        'id' => $booking['id'],
        'booking_reference' => $booking['booking_reference'],
        'first_name' => $booking['first_name'],
        'last_name' => $booking['last_name'],
        'email' => $booking['email'],
        'phone' => $booking['phone'],
        'booking_type' => $booking['booking_type'],
        'booking_type_display' => $booking_type_display[$booking['booking_type']] ?? 'Unknown',
        'total_price' => $booking['total_price'],
        'total_price_formatted' => number_format($booking['total_price'], 2) . ' ETB',
        'status' => ucfirst($booking['status']),
        'payment_status' => ucfirst($booking['payment_status']),
        'verification_status' => $booking['verification_status'],
        'verification_status_display' => $verification_status_display[$booking['verification_status']] ?? 'Unknown',
        'verification_status_color' => $verification_status_colors[$booking['verification_status']] ?? 'secondary',
        'created_at_formatted' => $created_at_formatted,
        'special_requests' => $booking['special_requests'],
        
        // Room booking specific
        'room_name' => $booking['room_name'],
        'room_number' => $booking['room_number'],
        'customers' => $booking['customers'],
        'check_in_date' => $check_in_date,
        'check_out_date' => $check_out_date,
        
        // Food order specific
        'table_reservation' => $booking['table_reservation'],
        'guests' => $booking['food_guests'],
        'reservation_date' => $reservation_date,
        'reservation_time' => $reservation_time,
        
        // Service specific
        'service_name' => $booking['service_name'],
        'service_category' => $booking['service_category'],
        'service_quantity' => $booking['service_quantity'],
        'service_date' => $service_date,
        'service_time' => $service_time,
        
        // Payment information
        'payment_method' => $booking['payment_method'],
        'payment_method_display' => $booking['payment_method_name'] ?? ucfirst($booking['payment_method']),
        'screenshot_path' => $booking['screenshot_path'],
        'screenshot_uploaded_at_formatted' => $screenshot_uploaded_at_formatted
    ]
];

echo json_encode($response_data);

$conn->close();
?>