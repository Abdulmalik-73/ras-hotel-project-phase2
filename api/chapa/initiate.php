<?php
/**
 * Chapa Payment Initiation
 * Called via AJAX from payment-upload.php
 */
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
    exit;
}

// Get booking + user details
$stmt = $conn->prepare(
    "SELECT b.*, u.email, u.first_name, u.last_name
     FROM bookings b
     JOIN users u ON b.user_id = u.id
     WHERE b.id = ? AND b.user_id = ?"
);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

if ($booking['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'Payment already completed']);
    exit;
}

// Generate unique tx_ref
$tx_ref = 'HRH-' . $booking['booking_reference'] . '-' . time();

// Store in payments table
$ins = $conn->prepare(
    "INSERT INTO payments (user_id, booking_id, email, amount, tx_ref, status, created_at)
     VALUES (?, ?, ?, ?, ?, 'pending', NOW())
     ON DUPLICATE KEY UPDATE tx_ref = VALUES(tx_ref), status = 'pending', created_at = NOW()"
);
$ins->bind_param(
    "iisds",
    $_SESSION['user_id'],
    $booking_id,
    $booking['email'],
    $booking['total_price'],
    $tx_ref
);
$ins->execute();

// Build Chapa payload
$payload = [
    'amount'        => (string)$booking['total_price'],
    'currency'      => 'ETB',
    'email'         => $booking['email'],
    'first_name'    => $booking['first_name'],
    'last_name'     => $booking['last_name'],
    'tx_ref'        => $tx_ref,
    'callback_url'  => getenv('CHAPA_CALLBACK_URL'),
    'return_url'    => getenv('CHAPA_RETURN_URL') . '?tx_ref=' . urlencode($tx_ref),
    'customization' => [
        'title'       => 'Harar Ras Hotel',
        'description' => 'Payment for booking ' . $booking['booking_reference'],
    ],
];

// Call Chapa initialize API
$ch = curl_init(getenv('CHAPA_BASE_URL') . '/transaction/initialize');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . getenv('CHAPA_SECRET_KEY'),
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false, // XAMPP sandbox
]);

$response     = curl_exec($ch);
$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error   = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log("Chapa cURL error: $curl_error");
    echo json_encode(['success' => false, 'message' => 'Connection error. Please try again.']);
    exit;
}

$result = json_decode($response, true);

if ($http_code === 200 && isset($result['data']['checkout_url'])) {
    // Save tx_ref to booking
    $upd = $conn->prepare("UPDATE bookings SET payment_reference = ? WHERE id = ?");
    $upd->bind_param("si", $tx_ref, $booking_id);
    $upd->execute();

    // Save full Chapa response
    $json_resp = json_encode($result);
    $upd2 = $conn->prepare("UPDATE payments SET chapa_response = ? WHERE tx_ref = ?");
    $upd2->bind_param("ss", $json_resp, $tx_ref);
    $upd2->execute();

    echo json_encode([
        'success'      => true,
        'checkout_url' => $result['data']['checkout_url'],
        'tx_ref'       => $tx_ref,
    ]);
} else {
    $msg = $result['message'] ?? 'Failed to initialize payment';
    error_log("Chapa init failed ($http_code): $response");
    echo json_encode(['success' => false, 'message' => $msg]);
}
