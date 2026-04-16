<?php
/**
 * Email Test Endpoint
 * Usage: /api/test-email.php?to=email@example.com
 */
require_once '../includes/config.php';
require_once '../includes/Mailer.php';

header('Content-Type: application/json');

$to   = $_GET['to'] ?? 'nureabdulmalik8@gmail.com';
$name = $_GET['name'] ?? 'Test User';

$subject  = 'Test Email - Harar Ras Hotel';
$htmlBody = Mailer::wrap('<h2 style="color:#2ecc71;">Test Email ✅</h2><p>If you received this, Brevo SMTP is working correctly on Render!</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>');

$result = Mailer::send($to, $name, $subject, $htmlBody);

echo json_encode([
    'result'      => $result,
    'sent_to'     => $to,
    'method'      => defined('BREVO_API_KEY') && BREVO_API_KEY ? 'Brevo HTTP API' : 'SMTP',
    'brevo_key'   => defined('BREVO_API_KEY') ? 'SET (len='.strlen(BREVO_API_KEY).')' : (getenv('BREVO_API_KEY') ? 'SET via getenv' : 'MISSING'),
    'email_enabled' => defined('EMAIL_ENABLED') ? EMAIL_ENABLED : getenv('EMAIL_ENABLED'),
    'from'        => defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : getenv('EMAIL_FROM_ADDRESS'),
]);
