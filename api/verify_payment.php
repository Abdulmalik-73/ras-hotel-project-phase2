<?php
/**
 * Payment Verification API
 * Handles approve/reject actions for payment screenshots
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/services/NotificationService.php';
require_once '../includes/services/EmailService.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'receptionist', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get action and payment ID
$action = $_POST['action'] ?? '';
$payment_id = (int)($_POST['payment_id'] ?? 0);

if (empty($action) || $payment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Get payment details
    $query = "SELECT pv.*, u.email, u.first_name, u.last_name, b.booking_reference, b.total_price
              FROM payment_verifications pv
              JOIN users u ON pv.user_id = u.id
              JOIN bookings b ON pv.booking_id = b.id
              WHERE pv.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit();
    }
    
    // Check if already processed
    if ($payment['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Payment already processed']);
        exit();
    }
    
    // Initialize services
    $notificationService = new NotificationService($conn);
    $emailService = new EmailService();
    
    if ($action === 'approve') {
        // Approve payment
        $update_query = "UPDATE payment_verifications 
                        SET status = 'verified', 
                            verified_by = ?, 
                            verified_at = NOW() 
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $_SESSION['user_id'], $payment_id);
        
        if ($update_stmt->execute()) {
            // Update booking payment status
            $booking_update = "UPDATE bookings 
                              SET payment_status = 'paid', 
                                  verification_status = 'verified',
                                  verified_by = ?,
                                  verified_at = NOW()
                              WHERE id = ?";
            $booking_stmt = $conn->prepare($booking_update);
            $booking_stmt->bind_param("ii", $_SESSION['user_id'], $payment['booking_id']);
            $booking_stmt->execute();
            
            // Create notification
            $notificationService->notifyPaymentVerified(
                $payment['user_id'],
                $payment['booking_reference'],
                $payment['amount']
            );
            
            // Send email
            $emailService->sendPaymentVerifiedEmail(
                $payment['email'],
                $payment['first_name'] . ' ' . $payment['last_name'],
                $payment['booking_reference'],
                $payment['amount']
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment verified successfully',
                'status' => 'verified'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to verify payment']);
        }
        
    } elseif ($action === 'reject') {
        // Get rejection reason
        $reason = $_POST['reason'] ?? 'Invalid payment screenshot';
        
        // Reject payment
        $update_query = "UPDATE payment_verifications 
                        SET status = 'rejected', 
                            verified_by = ?, 
                            verified_at = NOW(),
                            rejection_reason = ?
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("isi", $_SESSION['user_id'], $reason, $payment_id);
        
        if ($update_stmt->execute()) {
            // Update booking status
            $booking_update = "UPDATE bookings 
                              SET verification_status = 'rejected'
                              WHERE id = ?";
            $booking_stmt = $conn->prepare($booking_update);
            $booking_stmt->bind_param("i", $payment['booking_id']);
            $booking_stmt->execute();
            
            // Create notification
            $notificationService->notifyPaymentRejected(
                $payment['user_id'],
                $payment['booking_reference'],
                $reason
            );
            
            // Send email
            $emailService->sendPaymentRejectedEmail(
                $payment['email'],
                $payment['first_name'] . ' ' . $payment['last_name'],
                $payment['booking_reference'],
                $reason
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment rejected',
                'status' => 'rejected'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject payment']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Payment verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
