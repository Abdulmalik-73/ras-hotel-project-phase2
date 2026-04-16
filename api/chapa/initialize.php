<?php
/**
 * Chapa Payment Initialization API
 * Creates a payment session and returns checkout URL
 */

header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error handling
try {
    require_once '../../includes/config.php';
    require_once '../../includes/functions.php';
    require_once '../../includes/services/ChapaPaymentService.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error: ' . $e->getMessage()
    ]);
    exit;
}

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to continue'
    ]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$booking_id = $input['booking_id'] ?? 0;

if (!$booking_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

// Get booking details
$query = "SELECT b.*, 
          COALESCE(r.name, 'Food Order') as room_name,
          u.email, u.first_name, u.last_name, u.phone
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error . '. Please make sure database is set up.'
    ]);
    exit;
}

$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found'
    ]);
    exit;
}

// Check if already paid
if ($booking['payment_status'] === 'paid') {
    echo json_encode([
        'success' => false,
        'message' => 'This booking has already been paid'
    ]);
    exit;
}

// Initialize Chapa service
$chapa = new ChapaPaymentService();

if (!$chapa->isConfigured()) {
    echo json_encode([
        'success' => false,
        'message' => 'Chapa payment gateway is not configured. Please use manual payment method.'
    ]);
    exit;
}

// Prepare payment data
$payment_data = [
    'amount' => $booking['total_price'],
    'email' => $booking['email'],
    'first_name' => $booking['first_name'],
    'last_name' => $booking['last_name'],
    'phone' => $booking['phone'] ?? '',
    'booking_id' => $booking_id,
    'description' => 'Booking #' . $booking['booking_reference'] . ' - ' . $booking['room_name']
];

// Initialize payment
$result = $chapa->initializePayment($payment_data);

// Log the result for debugging
error_log("Chapa initialization result: " . json_encode($result));

if ($result['success']) {
    // Store transaction reference in database
    $tx_ref = $result['tx_ref'];
    $update_query = "UPDATE bookings SET 
                     payment_method = 'chapa',
                     transaction_id = ?,
                     verification_status = 'pending_verification'
                     WHERE id = ?";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $tx_ref, $booking_id);
    $update_stmt->execute();
    
    // Log the payment initialization
    error_log("Chapa payment initialized for booking #$booking_id, tx_ref: $tx_ref");
    
    echo json_encode([
        'success' => true,
        'checkout_url' => $result['checkout_url'],
        'tx_ref' => $tx_ref,
        'message' => 'Redirecting to payment page...'
    ]);
} else {
    echo json_encode($result);
}
