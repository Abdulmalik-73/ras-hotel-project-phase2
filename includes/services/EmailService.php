<?php
/**
 * Email Service Class
 * Handles all email notifications for Harar Ras Hotel
 * Uses PHPMailer for SMTP email sending
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $conn;
    private $mailer;
    private $enabled;

    // Helper to get config value from constant or env
    private static function cfg($key, $default = '') {
        if (defined($key)) return constant($key);
        $v = getenv($key);
        return ($v !== false) ? $v : $default;
    }
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $enabled_val = strtolower(self::cfg('EMAIL_ENABLED', 'false'));
        $this->enabled = ($enabled_val === 'true' || $enabled_val === '1');
        $this->initializeMailer();
    }
    
    /**
     * Initialize PHPMailer with SMTP configuration
     */
    private function initializeMailer() {
        // Use local PHPMailer files (no Composer needed)
        require_once __DIR__ . '/../phpmailer/Exception.php';
        require_once __DIR__ . '/../phpmailer/PHPMailer.php';
        require_once __DIR__ . '/../phpmailer/SMTP.php';
        
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host       = self::cfg('EMAIL_HOST', 'smtp.gmail.com');
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = self::cfg('EMAIL_USERNAME');
            $this->mailer->Password   = self::cfg('EMAIL_PASSWORD');
            $this->mailer->SMTPSecure = self::cfg('EMAIL_ENCRYPTION', 'tls') === 'ssl'
                                        ? PHPMailer::ENCRYPTION_SMTPS
                                        : PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = (int)(self::cfg('EMAIL_PORT', '587'));
            $this->mailer->CharSet    = 'UTF-8';
            
            // From address
            $this->mailer->setFrom(
                self::cfg('EMAIL_FROM_ADDRESS', self::cfg('EMAIL_USERNAME')),
                self::cfg('EMAIL_FROM_NAME', 'Harar Ras Hotel')
            );
        } catch (Exception $e) {
            error_log("Email initialization error: " . $e->getMessage());
        }
    }

    
    /**
     * Send room booking confirmation email
     */
    public function sendRoomBookingEmail($bookingId) {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'Email service is disabled'];
        }
        
        // Get booking details
        $query = "SELECT b.*, r.name as room_name, r.room_number, r.room_type,
                  u.first_name, u.last_name, u.email, u.phone
                  FROM bookings b
                  JOIN rooms r ON b.room_id = r.id
                  JOIN users u ON b.user_id = u.id
                  WHERE b.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Booking not found'];
        }
        
        $booking = $result->fetch_assoc();
        
        // Check if email already sent
        if ($booking['email_sent'] == 1) {
            return ['success' => false, 'message' => 'Email already sent'];
        }
        
        // Prepare email content
        $subject = "Booking Confirmation - " . $booking['booking_reference'];
        $htmlBody = $this->getRoomBookingTemplate($booking);
        
        return $this->sendEmail(
            $booking['email'],
            $booking['first_name'] . ' ' . $booking['last_name'],
            $subject,
            $htmlBody,
            'room_booking',
            $bookingId,
            $booking['user_id']
        );
    }

    
    /**
     * Send food order confirmation email
     */
    public function sendFoodOrderEmail($orderId) {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'Email service is disabled'];
        }
        
        // Get order details
        $query = "SELECT fo.*, u.first_name, u.last_name, u.email, u.phone,
                  b.booking_reference, b.total_price
                  FROM food_orders fo
                  JOIN users u ON fo.user_id = u.id
                  JOIN bookings b ON fo.booking_id = b.id
                  WHERE fo.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        $order = $result->fetch_assoc();
        
        // Check if email already sent
        if ($order['email_sent'] == 1) {
            return ['success' => false, 'message' => 'Email already sent'];
        }
        
        // Get order items
        $items_query = "SELECT * FROM food_order_items WHERE order_id = ?";
        $items_stmt = $this->conn->prepare($items_query);
        $items_stmt->bind_param("i", $orderId);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $order['items'] = $items_result->fetch_all(MYSQLI_ASSOC);
        
        // Prepare email content
        $subject = "Food Order Confirmation - " . $order['order_reference'];
        $htmlBody = $this->getFoodOrderTemplate($order);
        
        return $this->sendEmail(
            $order['email'],
            $order['first_name'] . ' ' . $order['last_name'],
            $subject,
            $htmlBody,
            'food_order',
            $orderId,
            $order['user_id']
        );
    }

    
    /**
     * Send payment verification email
     */
    public function sendPaymentVerificationEmail($booking) {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'Email service is disabled'];
        }
        
        // Prepare email content
        $subject = "Payment Verified - " . $booking['booking_reference'];
        $htmlBody = $this->getPaymentVerificationTemplate($booking);
        
        return $this->sendEmail(
            $booking['email'],
            $booking['first_name'] . ' ' . $booking['last_name'],
            $subject,
            $htmlBody,
            'payment_verification',
            $booking['id'],
            $booking['user_id']
        );
    }

    
    /**
     * Send spa/laundry service confirmation email
     */
    public function sendServiceEmail($serviceId, $serviceType = 'spa') {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'Email service is disabled'];
        }
        
        // Get service details
        $query = "SELECT sb.*, u.first_name, u.last_name, u.email, u.phone,
                  b.booking_reference
                  FROM service_bookings sb
                  JOIN users u ON sb.user_id = u.id
                  JOIN bookings b ON sb.booking_id = b.id
                  WHERE sb.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Service not found'];
        }
        
        $service = $result->fetch_assoc();
        
        // Check if email already sent
        if ($service['email_sent'] == 1) {
            return ['success' => false, 'message' => 'Email already sent'];
        }
        
        // Prepare email content
        $subject = ucfirst($serviceType) . " Service Confirmation - " . $service['booking_reference'];
        $htmlBody = $this->getServiceTemplate($service, $serviceType);
        
        $emailType = $serviceType === 'spa' ? 'spa_service' : 'laundry_service';
        
        return $this->sendEmail(
            $service['email'],
            $service['first_name'] . ' ' . $service['last_name'],
            $subject,
            $htmlBody,
            $emailType,
            $serviceId,
            $service['user_id']
        );
    }

    
    /**
     * Core email sending method
     */
    private function sendEmail($to, $toName, $subject, $htmlBody, $emailType, $referenceId, $userId) {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Set recipient
            $this->mailer->addAddress($to, $toName);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);
            
            // Send email
            $this->mailer->send();
            
            // Log success
            $this->logEmail($userId, $to, $emailType, $referenceId, $subject, 'sent', null);
            
            // Update email_sent flag
            $this->updateEmailSentFlag($emailType, $referenceId, true);
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            $errorMsg = $this->mailer->ErrorInfo;
            error_log("Email send error: " . $errorMsg);
            
            // Log failure
            $this->logEmail($userId, $to, $emailType, $referenceId, $subject, 'failed', $errorMsg);
            
            // Update email_sent flag with error
            $this->updateEmailSentFlag($emailType, $referenceId, false, $errorMsg);
            
            return ['success' => false, 'message' => 'Email failed: ' . $errorMsg];
        }
    }

    
    /**
     * Log email to database
     */
    private function logEmail($userId, $emailTo, $emailType, $referenceId, $subject, $status, $errorMessage) {
        $query = "INSERT INTO email_logs (user_id, email_to, email_type, reference_id, subject, status, error_message, sent_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("issssss", $userId, $emailTo, $emailType, $referenceId, $subject, $status, $errorMessage);
        $stmt->execute();
    }
    
    /**
     * Update email_sent flag in respective table
     */
    private function updateEmailSentFlag($emailType, $referenceId, $sent, $error = null) {
        $table = '';
        
        switch ($emailType) {
            case 'room_booking':
            case 'payment_verification':
                $table = 'bookings';
                break;
            case 'food_order':
                $table = 'food_orders';
                break;
            case 'spa_service':
            case 'laundry_service':
                $table = 'service_bookings';
                break;
            default:
                return;
        }
        
        $emailSent = $sent ? 1 : 0;
        $query = "UPDATE $table SET email_sent = ?, email_sent_at = NOW(), email_error = ? WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isi", $emailSent, $error, $referenceId);
        $stmt->execute();
    }

    
    /**
     * Get room booking email template
     */
    private function getRoomBookingTemplate($booking) {
        require_once __DIR__ . '/EmailTemplates.php';
        return EmailTemplates::getRoomBookingTemplate($booking);
    }
    
    /**
     * Get food order email template
     */
    private function getFoodOrderTemplate($order) {
        require_once __DIR__ . '/EmailTemplates.php';
        return EmailTemplates::getFoodOrderTemplate($order);
    }
    
    /**
     * Get payment verification email template
     */
    private function getPaymentVerificationTemplate($booking) {
        require_once __DIR__ . '/EmailTemplates.php';
        return EmailTemplates::getPaymentVerificationTemplate($booking);
    }
}
