<?php
/**
 * Mailer Helper
 * Wraps PHPMailer for Harar Ras Hotel
 * No Composer needed — uses local PHPMailer source files
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

class Mailer {

    /**
     * Send an email using Gmail SMTP (settings from .env)
     *
     * @param string $toEmail    Recipient email
     * @param string $toName     Recipient name
     * @param string $subject    Email subject
     * @param string $htmlBody   HTML email body
     * @return array ['success' => bool, 'message' => string]
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody): array {
        // Check if email is enabled — check PHP constant first, then getenv()
        $emailEnabled = defined('EMAIL_ENABLED') ? EMAIL_ENABLED : (getenv('EMAIL_ENABLED') ?: 'false');
        if (strtolower(trim($emailEnabled)) !== 'true') {
            error_log("Mailer: EMAIL_ENABLED is not true, skipping email to $toEmail");
            return ['success' => false, 'message' => 'Email service is disabled'];
        }

        // Helper: get constant or env var
        $cfg = function(string $key, string $default = '') {
            if (defined($key)) return constant($key);
            $v = getenv($key);
            return ($v !== false) ? $v : $default;
        };

        $fromAddress = $cfg('EMAIL_FROM_ADDRESS') ?: $cfg('EMAIL_USERNAME');
        $fromName    = $cfg('EMAIL_FROM_NAME', 'Harar Ras Hotel');

        // Try Brevo HTTP API first (works on Render - no SMTP port blocking)
        $brevoKey = $cfg('BREVO_API_KEY');
        error_log("Mailer::send to=$toEmail | BREVO=" . (empty($brevoKey) ? 'MISSING' : 'SET('.strlen($brevoKey).')') . " | FROM=$fromAddress | ENABLED=$emailEnabled");
        if (!empty($brevoKey)) {
            return self::sendViaBrevo($brevoKey, $toEmail, $toName, $subject, $htmlBody, $fromAddress, $fromName);
        }

        // Fallback: SMTP via PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg('EMAIL_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg('EMAIL_USERNAME');
            $mail->Password   = $cfg('EMAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)($cfg('EMAIL_PORT', '587'));
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 15;
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            $mail->send();
            error_log("Mailer: Email sent to $toEmail via SMTP");
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            $err = $mail->ErrorInfo;
            error_log("Mailer SMTP ERROR to $toEmail: $err");
            return ['success' => false, 'message' => $err];
        }
    }

    /**
     * Send via Brevo (Sendinblue) HTTP API — works on Render (no SMTP port blocking)
     */
    private static function sendViaBrevo(string $apiKey, string $toEmail, string $toName, string $subject, string $htmlBody, string $fromEmail, string $fromName): array {
        $payload = json_encode([
            'sender'     => ['name' => $fromName, 'email' => $fromEmail],
            'to'         => [['email' => $toEmail, 'name' => $toName]],
            'subject'    => $subject,
            'htmlContent'=> $htmlBody,
        ]);

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            error_log("Mailer: Email sent to $toEmail via Brevo");
            return ['success' => true, 'message' => 'Email sent via Brevo'];
        }

        error_log("Mailer Brevo ERROR to $toEmail: HTTP $httpCode — $response");
        return ['success' => false, 'message' => "Brevo error HTTP $httpCode: $response"];
    }

    /**
     * Build a branded HTML email wrapper
     */
    public static function wrap(string $content): string {
        $cfg = function(string $key, string $default = '') {
            if (defined($key)) return constant($key);
            $v = getenv($key);
            return ($v !== false) ? $v : $default;
        };
        $hotel   = $cfg('HOTEL_NAME', 'Harar Ras Hotel');
        $phone   = $cfg('HOTEL_PHONE', '+251-25-666-0000');
        $email   = $cfg('HOTEL_SUPPORT_EMAIL', 'info@hararrashotel.com');
        $address = $cfg('HOTEL_ADDRESS', 'Harar, Ethiopia');
        $year    = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#1DBF73,#17a85f);padding:28px 32px;text-align:center;">
          <h1 style="color:#fff;margin:0;font-size:24px;font-weight:700;">🏨 $hotel</h1>
          <p style="color:rgba(255,255,255,.85);margin:6px 0 0;font-size:13px;">Your Comfort, Our Priority</p>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:36px 32px;">
          $content
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f8f9fa;padding:24px 32px;text-align:center;border-top:1px solid #e9ecef;">
          <p style="margin:0 0 6px;font-size:13px;color:#6c757d;font-weight:600;">$hotel</p>
          <p style="margin:0 0 4px;font-size:12px;color:#6c757d;">📍 $address</p>
          <p style="margin:0 0 4px;font-size:12px;color:#6c757d;">📞 $phone &nbsp;|&nbsp; ✉️ $email</p>
          <p style="margin:14px 0 0;font-size:11px;color:#adb5bd;">© $year $hotel. All rights reserved.</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    // ── Pre-built templates ────────────────────────────────────────────────────

    /**
     * Payment confirmed email (Chapa or screenshot verified)
     * @param array  $booking   Booking row (with user fields)
     * @param string $tx_ref    Transaction reference
     * @param array  $extra     Extra details: food_order row or service_booking row
     */
    public static function paymentConfirmedHtml(array $booking, string $tx_ref = '', array $extra = []): string {
        $name   = htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']);
        $ref    = htmlspecialchars($booking['booking_reference']);
        $amount = number_format($booking['total_price'], 2);
        $type   = $booking['booking_type'] ?? 'room';
        $date   = date('F j, Y g:i A');

        $extraRows = '';
        $nextSteps = '';

        if ($type === 'room') {
            $typeLabel  = 'Room Booking';
            $typeIcon   = '🛏️';
            $roomName   = htmlspecialchars($booking['room_name'] ?? '');
            $roomNumber = htmlspecialchars($booking['room_number'] ?? '');
            $roomDisplay = $roomName . ($roomNumber ? " (Room $roomNumber)" : '');
            $extraRows .= self::detailRow('Room', $roomDisplay);
            if (!empty($booking['check_in_date'])) {
                $extraRows .= self::detailRow('Check-in',  date('M d, Y', strtotime($booking['check_in_date'])));
                $extraRows .= self::detailRow('Check-out', date('M d, Y', strtotime($booking['check_out_date'])));
            }
            $nextSteps = '
                <li>Please arrive at the hotel on your check-in date</li>
                <li>Bring a valid ID for verification at check-in</li>
                <li>Contact us if you need to make any changes to your booking</li>';

        } elseif ($type === 'food_order') {
            $typeLabel = 'Food Order';
            $typeIcon  = '🍽️';

            // Items ordered
            $items = $extra['items_list'] ?? ($extra['order_reference'] ?? '');
            if ($items) {
                $extraRows .= self::detailRow('Items Ordered', htmlspecialchars($items));
            }
            // Order reference
            if (!empty($extra['order_reference'])) {
                $extraRows .= self::detailRow('Order Reference', htmlspecialchars($extra['order_reference']));
            }
            // Reservation date & time
            if (!empty($extra['reservation_date'])) {
                $extraRows .= self::detailRow('Reservation Date', date('M d, Y', strtotime($extra['reservation_date'])));
            }
            if (!empty($extra['reservation_time'])) {
                $extraRows .= self::detailRow('Reservation Time', date('g:i A', strtotime($extra['reservation_time'])));
            }
            // Guests
            if (!empty($extra['guests'])) {
                $extraRows .= self::detailRow('Guests', $extra['guests'] . ' person(s)');
            }
            // Special requests
            if (!empty($extra['special_requests'])) {
                $extraRows .= self::detailRow('Special Requests', htmlspecialchars($extra['special_requests']));
            }

            $nextSteps = '
                <li>Your order is being prepared by our kitchen team</li>
                <li>You will be notified when your order is ready</li>
                <li>Contact us if you have any special requests</li>';

        } elseif ($type === 'spa_service') {
            $typeLabel = 'Spa & Wellness';
            $typeIcon  = '💆';

            if (!empty($extra['service_name'])) {
                $extraRows .= self::detailRow('Service', htmlspecialchars($extra['service_name']));
            }
            if (!empty($extra['service_date'])) {
                $extraRows .= self::detailRow('Service Date', date('M d, Y', strtotime($extra['service_date'])));
            }
            if (!empty($extra['service_time'])) {
                $extraRows .= self::detailRow('Service Time', date('g:i A', strtotime($extra['service_time'])));
            }
            if (!empty($extra['special_requests'])) {
                $extraRows .= self::detailRow('Special Requests', htmlspecialchars($extra['special_requests']));
            }

            $nextSteps = '
                <li>Please arrive 10 minutes before your scheduled time</li>
                <li>Bring your booking reference for verification</li>
                <li>Contact us if you need to reschedule</li>';

        } else {
            // laundry_service
            $typeLabel = 'Laundry Service';
            $typeIcon  = '👕';

            if (!empty($extra['service_name'])) {
                $extraRows .= self::detailRow('Service', htmlspecialchars($extra['service_name']));
            }
            if (!empty($extra['service_date'])) {
                $extraRows .= self::detailRow('Collection Date', date('M d, Y', strtotime($extra['service_date'])));
            }
            if (!empty($extra['service_time'])) {
                $extraRows .= self::detailRow('Collection Time', date('g:i A', strtotime($extra['service_time'])));
            }
            if (!empty($extra['special_requests'])) {
                $extraRows .= self::detailRow('Special Requests', htmlspecialchars($extra['special_requests']));
            }

            $nextSteps = '
                <li>Your laundry will be collected at the scheduled time</li>
                <li>Items will be returned clean and neatly folded</li>
                <li>Contact us if you need to make any changes</li>';
        }

        $txRow = $tx_ref
            ? self::detailRow('Transaction Ref', '<span style="font-size:12px;color:#6c757d;">' . htmlspecialchars($tx_ref) . '</span>')
            : '';

        $content = <<<HTML
<p style="font-size:16px;color:#333;margin:0 0 6px;">Dear <strong>$name</strong>,</p>
<p style="color:#555;margin:0 0 24px;">Your payment has been <strong style="color:#1DBF73;">verified successfully</strong> and your $typeLabel is confirmed. $typeIcon</p>

<!-- Status badge -->
<div style="text-align:center;margin-bottom:24px;">
  <span style="display:inline-block;background:#1DBF73;color:#fff;padding:8px 24px;border-radius:50px;font-weight:700;font-size:14px;">
    ✅ PAYMENT CONFIRMED
  </span>
</div>

<!-- Details table -->
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;border-radius:10px;margin-bottom:24px;">
  {$extraRows}
  {$txRow}
  <tr>
    <td style="padding:12px 16px;border-bottom:1px solid #e9ecef;color:#6c757d;font-size:13px;">Booking Reference</td>
    <td style="padding:12px 16px;border-bottom:1px solid #e9ecef;font-weight:700;color:#0d6efd;">$ref</td>
  </tr>
  <tr>
    <td style="padding:12px 16px;border-bottom:1px solid #e9ecef;color:#6c757d;font-size:13px;">Verified On</td>
    <td style="padding:12px 16px;border-bottom:1px solid #e9ecef;font-weight:600;">$date</td>
  </tr>
  <tr>
    <td style="padding:12px 16px;color:#6c757d;font-size:13px;">Amount Paid</td>
    <td style="padding:12px 16px;font-weight:700;font-size:18px;color:#1DBF73;">ETB $amount</td>
  </tr>
</table>

<!-- What's next -->
<div style="background:#f0fdf4;border-left:4px solid #1DBF73;border-radius:6px;padding:16px 20px;margin-bottom:24px;">
  <p style="margin:0 0 10px;font-weight:700;color:#166534;">📋 What's Next?</p>
  <ul style="margin:0;padding-left:18px;color:#555;font-size:14px;line-height:1.8;">
    $nextSteps
  </ul>
</div>

<p style="color:#555;font-size:14px;margin:0;">Thank you for choosing <strong>Harar Ras Hotel</strong>. We look forward to serving you!</p>
<p style="color:#555;font-size:14px;margin:16px 0 0;">Best regards,<br><strong>Harar Ras Hotel Team</strong></p>
HTML;

        return self::wrap($content);
    }

    private static function detailRow(string $label, string $value): string {
        return <<<HTML
  <tr>
    <td style="padding:12px 16px;border-bottom:1px solid #e9ecef;color:#6c757d;font-size:13px;">$label</td>
    <td style="padding:12px 16px;border-bottom:1px solid #e9ecef;font-weight:600;">$value</td>
  </tr>
HTML;
    }
}
