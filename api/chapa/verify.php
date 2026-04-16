<?php
/**
 * Chapa Payment Verification
 * Called from chapa-return.php after redirect back from Chapa
 */
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$tx_ref = trim($_GET['tx_ref'] ?? $_POST['tx_ref'] ?? '');
if (empty($tx_ref)) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction reference']);
    exit;
}

// Verify with Chapa
$chapa_base = defined('CHAPA_BASE_URL') ? CHAPA_BASE_URL : (getenv('CHAPA_BASE_URL') ?: 'https://api.chapa.co/v1');
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : (getenv('CHAPA_SECRET_KEY') ?: '');

$ch = curl_init($chapa_base . '/transaction/verify/' . urlencode($tx_ref));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $chapa_key,
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    echo json_encode(['success' => false, 'message' => 'Verification connection error']);
    exit;
}

$result    = json_decode($response, true);
$pay_status = $result['data']['status'] ?? 'failed';
$json_resp  = json_encode($result);

// Update payments table
$stmt = $conn->prepare(
    "UPDATE payments SET status = ?, chapa_response = ?, updated_at = NOW() WHERE tx_ref = ? AND user_id = ?"
);
$db_status = ($pay_status === 'success') ? 'paid' : 'failed';
$stmt->bind_param("sssi", $db_status, $json_resp, $tx_ref, $_SESSION['user_id']);
$stmt->execute();

if ($pay_status === 'success') {
    // Get booking_id
    $row = $conn->prepare("SELECT booking_id FROM payments WHERE tx_ref = ? AND user_id = ?");
    $row->bind_param("si", $tx_ref, $_SESSION['user_id']);
    $row->execute();
    $payment = $row->get_result()->fetch_assoc();

    if ($payment) {
        $bid = $payment['booking_id'];
        $upd = $conn->prepare(
            "UPDATE bookings SET payment_status = 'paid', verification_status = 'verified', verified_at = NOW() WHERE id = ?"
        );
        $upd->bind_param("i", $bid);
        $upd->execute();

        // Send booking confirmation email
        try {
            require_once '../../includes/services/EmailService.php';
            $emailService = new EmailService($conn);
            $emailService->sendRoomBookingEmail($bid);
        } catch (Exception $e) {
            error_log("Verify email error: " . $e->getMessage());
        }

        echo json_encode([
            'success'    => true,
            'status'     => 'paid',
            'booking_id' => $bid,
            'amount'     => $result['data']['amount'] ?? 0,
            'currency'   => $result['data']['currency'] ?? 'ETB',
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment record not found']);
    }
} else {
    echo json_encode([
        'success' => false,
        'status'  => $pay_status,
        'message' => $result['message'] ?? 'Payment was not successful',
    ]);
}
