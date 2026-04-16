<?php
/**
 * Chapa Webhook Callback (callback_url)
 * Chapa POSTs here silently after payment — user never sees this
 * Always verify independently — never trust callback data alone
 */
require_once '../../includes/config.php';

// Read raw POST body
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Log every callback for debugging
$log_dir = __DIR__ . '/../../logs/';
if (!file_exists($log_dir)) mkdir($log_dir, 0755, true);
file_put_contents($log_dir . 'chapa_callback.log',
    date('Y-m-d H:i:s') . " | " . $raw . "\n",
    FILE_APPEND
);

if (!$data || empty($data['tx_ref'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$tx_ref = $data['tx_ref'];

$chapa_base = defined('CHAPA_BASE_URL') ? CHAPA_BASE_URL : (getenv('CHAPA_BASE_URL') ?: 'https://api.chapa.co/v1');
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : (getenv('CHAPA_SECRET_KEY') ?: '');

// ── Always verify with Chapa API (never trust callback data alone) ────────────
$ch = curl_init($chapa_base . '/transaction/verify/' . urlencode($tx_ref));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $chapa_key,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result     = json_decode($response, true);
$pay_status = $result['data']['status'] ?? 'failed';
$json_resp  = json_encode($result);

// Log verify result
file_put_contents($log_dir . 'chapa_callback.log',
    date('Y-m-d H:i:s') . " | VERIFY [$tx_ref] HTTP $http_code status=$pay_status\n",
    FILE_APPEND
);

$db_status = ($pay_status === 'success') ? 'paid' : 'failed';

// Update payments table
$stmt = $conn->prepare(
    "UPDATE payments SET status = ?, chapa_response = ?, updated_at = NOW() WHERE tx_ref = ?"
);
$stmt->bind_param("sss", $db_status, $json_resp, $tx_ref);
$stmt->execute();

if ($pay_status === 'success') {
    // Get booking_id
    $row = $conn->prepare("SELECT booking_id FROM payments WHERE tx_ref = ? LIMIT 1");
    $row->bind_param("s", $tx_ref);
    $row->execute();
    $payment_row = $row->get_result()->fetch_assoc();

    if ($payment_row) {
        $booking_id = (int)$payment_row['booking_id'];

        $upd = $conn->prepare(
            "UPDATE bookings
             SET payment_status      = 'paid',
                 verification_status = 'verified',
                 verified_at         = NOW()
             WHERE id = ?"
        );
        $upd->bind_param("i", $booking_id);
        $upd->execute();

        // Send booking confirmation email
        try {
            require_once '../../includes/services/EmailService.php';
            $emailService = new EmailService($conn);
            $emailResult  = $emailService->sendRoomBookingEmail($booking_id);
            file_put_contents($log_dir . 'chapa_callback.log',
                date('Y-m-d H:i:s') . " | EMAIL booking_id=$booking_id result=" . json_encode($emailResult) . "\n",
                FILE_APPEND
            );
        } catch (Exception $e) {
            error_log("Chapa callback email error: " . $e->getMessage());
        }
    }
}

http_response_code(200);
echo 'OK';
