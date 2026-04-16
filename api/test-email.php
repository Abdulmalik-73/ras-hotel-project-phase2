<?php
/**
 * Email Test Endpoint - for debugging only
 * Usage: /api/test-email.php?to=email@example.com
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/Mailer.php';

header('Content-Type: application/json');

// Simple auth check - only staff can use this
if (!is_logged_in() || !in_array($_SESSION['user_role'] ?? '', ['admin', 'manager', 'super_admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$to = $_GET['to'] ?? ($_SESSION['user_email'] ?? '');
if (empty($to)) {
    echo json_encode(['error' => 'Provide ?to=email@example.com']);
    exit;
}

// Diagnose config
$cfg = function($key, $default = '') {
    if (defined($key)) return constant($key);
    $v = getenv($key);
    return ($v !== false) ? $v : $default;
};

$brevoKey    = $cfg('BREVO_API_KEY');
$emailEnabled = $cfg('EMAIL_ENABLED', 'false');
$fromAddress  = $cfg('EMAIL_FROM_ADDRESS') ?: $cfg('EMAIL_USERNAME');

$diag = [
    'EMAIL_ENABLED'    => $emailEnabled,
    'BREVO_API_KEY'    => empty($brevoKey) ? 'NOT SET' : 'SET (len='.strlen($brevoKey).')',
    'EMAIL_FROM'       => $fromAddress ?: 'NOT SET',
    'EMAIL_USERNAME'   => $cfg('EMAIL_USERNAME') ?: 'NOT SET',
];

if (empty($brevoKey)) {
    echo json_encode(['error' => 'BREVO_API_KEY not set', 'diagnostics' => $diag]);
    exit;
}

// Send test email
$subject  = 'Test Email - Harar Ras Hotel';
$htmlBody = '<h2>Test Email</h2><p>This is a test email from Harar Ras Hotel system.</p><p>If you received this, email is working correctly!</p>';

$result = Mailer::send($to, 'Test Recipient', $subject, $htmlBody);

echo json_encode([
    'result'      => $result,
    'diagnostics' => $diag,
    'sent_to'     => $to,
]);
