<?php
/**
 * Notification Service
 * Handles creating, reading, and managing user notifications
 */

class NotificationService {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Create a new notification
     */
    public function create($user_id, $type, $title, $message, $link = null) {
        try {
            $query = "INSERT INTO notifications (user_id, type, title, message, link) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            return false;
        } catch (Exception $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notifications for a user
     */
    public function getUnread($user_id, $limit = 10) {
        try {
            $query = "SELECT * FROM notifications 
                      WHERE user_id = ? AND is_read = 0 
                      ORDER BY created_at DESC 
                      LIMIT ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Get unread notifications error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all notifications for a user
     */
    public function getAll($user_id, $limit = 50) {
        try {
            $query = "SELECT * FROM notifications 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC 
                      LIMIT ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Get all notifications error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM notifications 
                      WHERE user_id = ? AND is_read = 0";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return (int)$row['count'];
        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $query = "UPDATE notifications 
                      SET is_read = 1, read_at = NOW() 
                      WHERE id = ? AND user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $notification_id, $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id) {
        try {
            $query = "UPDATE notifications 
                      SET is_read = 1, read_at = NOW() 
                      WHERE user_id = ? AND is_read = 0";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Mark all as read error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a notification
     */
    public function delete($notification_id, $user_id) {
        try {
            $query = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $notification_id, $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Delete notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete old read notifications (cleanup)
     */
    public function deleteOldRead($user_id, $days = 30) {
        try {
            $query = "DELETE FROM notifications 
                      WHERE user_id = ? AND is_read = 1 
                      AND read_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $user_id, $days);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Delete old notifications error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create payment verification notification
     */
    public function notifyPaymentVerified($user_id, $booking_reference, $amount) {
        $title = "Payment Verified ✓";
        $message = "Your payment of ETB " . number_format($amount, 2) . " for booking {$booking_reference} has been verified successfully.";
        $link = "booking-details.php?ref=" . $booking_reference;
        
        return $this->create($user_id, 'payment_verified', $title, $message, $link);
    }
    
    /**
     * Create payment rejection notification
     */
    public function notifyPaymentRejected($user_id, $booking_reference, $reason) {
        $title = "Payment Rejected";
        $message = "Your payment for booking {$booking_reference} was rejected. Reason: {$reason}. Please upload a valid payment screenshot.";
        $link = "payment-upload.php?booking=" . $booking_reference;
        
        return $this->create($user_id, 'payment_rejected', $title, $message, $link);
    }
}
