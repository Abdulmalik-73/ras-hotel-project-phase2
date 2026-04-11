<?php
/**
 * Payment Submission API - Screenshot Upload
 * Handles payment screenshot uploads and updates booking status
 */

header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
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

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get form data
$booking_id = $_POST['booking_id'] ?? 0;
$payment_method = $_POST['payment_method'] ?? '';

// Validate inputs
if (!$booking_id || !$payment_method) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Validate payment method
$valid_methods = ['telebirr', 'cbe', 'abyssinia', 'cooperative'];
if (!in_array($payment_method, $valid_methods)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment method'
    ]);
    exit;
}

// Check if booking exists and belongs to user
$query = "SELECT * FROM bookings WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
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

// Check if payment already submitted
if ($booking['payment_status'] === 'paid' || $booking['verification_status'] === 'verified') {
    echo json_encode([
        'success' => false,
        'message' => 'Payment already completed for this booking'
    ]);
    exit;
}

// Handle file upload
if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Please upload a payment screenshot'
    ]);
    exit;
}

$file = $_FILES['screenshot'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Please upload JPG, PNG, or JPEG files only'
    ]);
    exit;
}

// Validate file size (2MB max)
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode([
        'success' => false,
        'message' => 'File size too large. Maximum 2MB allowed'
    ]);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/payments/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'payment_' . $booking_id . '_' . time() . '.' . $file_extension;
$file_path = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload file. Please try again'
    ]);
    exit;
}

// Update booking with payment information
$update_query = "UPDATE bookings SET 
                 payment_method = ?,
                 screenshot_path = ?,
                 verification_status = 'pending_verification',
                 screenshot_uploaded_at = NOW()
                 WHERE id = ?";

$update_stmt = $conn->prepare($update_query);
$relative_path = 'uploads/payments/' . $filename; // Store relative path
$update_stmt->bind_param("ssi", $payment_method, $relative_path, $booking_id);

if (!$update_stmt->execute()) {
    // Delete uploaded file if database update fails
    unlink($file_path);
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again'
    ]);
    exit;
}

// Log the payment submission
if (function_exists('log_booking_activity')) {
    log_booking_activity(
        $booking_id,
        $_SESSION['user_id'],
        'payment_submitted',
        $booking['verification_status'],
        'pending_verification',
        "Payment screenshot uploaded via $payment_method",
        $_SESSION['user_id']
    );
}

// Success response
echo json_encode([
    'success' => true,
    'message' => 'Payment submitted successfully',
    'data' => [
        'booking_id' => $booking_id,
        'payment_method' => $payment_method,
        'status' => 'pending_verification'
    ]
]);

$conn->close();
?>