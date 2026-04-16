<?php
// TEMPORARY - check env vars on Render
require_once 'includes/config.php';

echo '<pre>';
echo "EMAIL_ENABLED defined: " . (defined('EMAIL_ENABLED') ? EMAIL_ENABLED : 'NO') . "\n";
echo "EMAIL_ENABLED getenv: " . (getenv('EMAIL_ENABLED') ?: 'NO') . "\n";
echo "\n";
echo "BREVO_API_KEY defined: " . (defined('BREVO_API_KEY') ? 'YES (len='.strlen(BREVO_API_KEY).')' : 'NO') . "\n";
echo "BREVO_API_KEY getenv: " . (getenv('BREVO_API_KEY') ? 'YES (len='.strlen(getenv('BREVO_API_KEY')).')' : 'NO') . "\n";
echo "\n";
echo "EMAIL_FROM_ADDRESS defined: " . (defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : 'NO') . "\n";
echo "EMAIL_FROM_ADDRESS getenv: " . (getenv('EMAIL_FROM_ADDRESS') ?: 'NO') . "\n";
echo "\n";
echo "All defined constants:\n";
$consts = get_defined_constants(true)['user'];
foreach ($consts as $k => $v) {
    if (strpos($k, 'EMAIL') !== false || strpos($k, 'BREVO') !== false) {
        echo "  $k = " . (is_string($v) ? substr($v,0,50) : $v) . "\n";
    }
}
echo '</pre>';
echo '<br><a href="api/test-email.php?to=nureabdulmalik8@gmail.com">Test Email</a>';
