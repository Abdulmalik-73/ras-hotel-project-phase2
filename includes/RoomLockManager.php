<?php
/**
 * Room Lock Manager - Handles room booking queue system
 * Prevents double booking and manages waiting queues automatically
 */

class RoomLockManager {
    private $conn;
    private $lock_timeout_minutes = 10; // Default timeout: 10 minutes
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Check room availability with queue information
     * 
     * @param int $room_id
     * @param string $check_in_date (Y-m-d format)
     * @param string $check_out_date (Y-m-d format)
     * @return array ['status' => string, 'queue_count' => int, 'message' => string]
     */
    public function checkRoomAvailability($room_id, $check_in_date, $check_out_date) {
        $stmt = $this->conn->prepare("CALL check_room_availability_with_queue(?, ?, ?, @status, @queue_count)");
        $stmt->bind_param("iss", $room_id, $check_in_date, $check_out_date);
        $stmt->execute();
        $stmt->close();
        
        // Get output parameters
        $result = $this->conn->query("SELECT @status as status, @queue_count as queue_count");
        $data = $result->fetch_assoc();
        
        $status = $data['status'];
        $queue_count = (int)$data['queue_count'];
        
        // Generate user-friendly message
        $message = $this->getStatusMessage($status, $queue_count);
        
        return [
            'status' => $status,
            'queue_count' => $queue_count,
            'message' => $message,
            'can_book' => in_array($status, ['available', 'in_process', 'waiting'])
        ];
    }
    
    /**
     * Acquire room lock (start booking process or join queue)
     * 
     * @param int $room_id
     * @param int $user_id
     * @param string $check_in_date
     * @param string $check_out_date
     * @return array ['success' => bool, 'lock_id' => int, 'status' => string, 'position' => int, 'message' => string]
     */
    public function acquireRoomLock($room_id, $user_id, $check_in_date, $check_out_date) {
        // Check if user already has a lock for this room
        $existing_lock = $this->getUserLockForRoom($user_id, $room_id, $check_in_date, $check_out_date);
        if ($existing_lock) {
            return [
                'success' => true,
                'lock_id' => $existing_lock['id'],
                'status' => $existing_lock['lock_status'],
                'position' => $existing_lock['queue_position'],
                'message' => 'You already have an active booking process for this room.',
                'existing' => true
            ];
        }
        
        $session_id = session_id();
        
        $stmt = $this->conn->prepare("CALL acquire_room_lock(?, ?, ?, ?, ?, ?, @lock_id, @lock_status, @queue_position)");
        $stmt->bind_param("iisssi", $room_id, $user_id, $session_id, $check_in_date, $check_out_date, $this->lock_timeout_minutes);
        $stmt->execute();
        $stmt->close();
        
        // Get output parameters
        $result = $this->conn->query("SELECT @lock_id as lock_id, @lock_status as lock_status, @queue_position as queue_position");
        $data = $result->fetch_assoc();
        
        $lock_id = (int)$data['lock_id'];
        $lock_status = $data['lock_status'];
        $queue_position = (int)$data['queue_position'];
        
        $message = $lock_status === 'in_process' 
            ? 'Room locked successfully. You have ' . $this->lock_timeout_minutes . ' minutes to complete booking.'
            : 'Room is currently being booked. You are in waiting queue at position #' . $queue_position;
        
        return [
            'success' => true,
            'lock_id' => $lock_id,
            'status' => $lock_status,
            'position' => $queue_position,
            'message' => $message,
            'expires_in_minutes' => $this->lock_timeout_minutes,
            'existing' => false
        ];
    }
    
    /**
     * Release room lock (cancel booking or complete payment)
     * 
     * @param int $lock_id
     * @param string $reason ('cancelled', 'completed', 'expired')
     * @return bool
     */
    public function releaseRoomLock($lock_id, $reason = 'cancelled') {
        $stmt = $this->conn->prepare("CALL release_room_lock(?, ?)");
        $stmt->bind_param("is", $lock_id, $reason);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get user's current lock for a room
     * 
     * @param int $user_id
     * @param int $room_id
     * @param string $check_in_date
     * @param string $check_out_date
     * @return array|null
     */
    public function getUserLockForRoom($user_id, $room_id, $check_in_date, $check_out_date) {
        $query = "SELECT * FROM room_locks 
                  WHERE user_id = ? 
                  AND room_id = ? 
                  AND check_in_date = ?
                  AND check_out_date = ?
                  AND expires_at > NOW()
                  ORDER BY created_at DESC
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiss", $user_id, $room_id, $check_in_date, $check_out_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get queue position for a lock
     * 
     * @param int $lock_id
     * @return int
     */
    public function getQueuePosition($lock_id) {
        $query = "SELECT queue_position FROM room_locks WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $lock_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        return $data ? (int)$data['queue_position'] : 0;
    }
    
    /**
     * Extend lock expiration time
     * 
     * @param int $lock_id
     * @param int $additional_minutes
     * @return bool
     */
    public function extendLockExpiration($lock_id, $additional_minutes = 5) {
        $query = "UPDATE room_locks 
                  SET expires_at = DATE_ADD(expires_at, INTERVAL ? MINUTE)
                  WHERE id = ? AND expires_at > NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $additional_minutes, $lock_id);
        return $stmt->execute();
    }
    
    /**
     * Get all available rooms with their current status
     * 
     * @param string $check_in_date
     * @param string $check_out_date
     * @return array
     */
    public function getAvailableRoomsWithStatus($check_in_date, $check_out_date) {
        $query = "SELECT 
                    r.id,
                    r.name,
                    r.room_number,
                    r.room_type,
                    r.price,
                    r.capacity,
                    r.description,
                    r.image,
                    r.manual_status,
                    CASE
                        WHEN r.manual_status = 'maintenance' THEN 'maintenance'
                        WHEN r.manual_status = 'inactive' THEN 'inactive'
                        WHEN EXISTS (
                            SELECT 1 FROM bookings b
                            WHERE b.room_id = r.id
                            AND b.status IN ('confirmed', 'checked_in')
                            AND (
                                (b.check_in_date <= ? AND b.check_out_date > ?)
                                OR (b.check_in_date < ? AND b.check_out_date >= ?)
                                OR (b.check_in_date >= ? AND b.check_out_date <= ?)
                            )
                        ) THEN 'occupied'
                        WHEN EXISTS (
                            SELECT 1 FROM room_locks rl
                            WHERE rl.room_id = r.id
                            AND rl.lock_status = 'in_process'
                            AND rl.expires_at > NOW()
                            AND (
                                (rl.check_in_date <= ? AND rl.check_out_date > ?)
                                OR (rl.check_in_date < ? AND rl.check_out_date >= ?)
                                OR (rl.check_in_date >= ? AND rl.check_out_date <= ?)
                            )
                        ) THEN 'in_process'
                        ELSE 'available'
                    END as display_status,
                    (SELECT COUNT(*) FROM room_locks rl 
                     WHERE rl.room_id = r.id 
                     AND rl.lock_status = 'waiting' 
                     AND rl.expires_at > NOW()) as waiting_count
                  FROM rooms r
                  WHERE r.manual_status = 'active'
                  ORDER BY r.room_number ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssssssssssss", 
            $check_in_date, $check_in_date, $check_out_date, $check_out_date, $check_in_date, $check_out_date,
            $check_in_date, $check_in_date, $check_out_date, $check_out_date, $check_in_date, $check_out_date
        );
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Cleanup expired locks manually
     * 
     * @return int Number of locks cleaned up
     */
    public function cleanupExpiredLocks() {
        $this->conn->query("CALL cleanup_expired_locks()");
        return $this->conn->affected_rows;
    }
    
    /**
     * Get user-friendly status message
     * 
     * @param string $status
     * @param int $queue_count
     * @return string
     */
    private function getStatusMessage($status, $queue_count) {
        switch ($status) {
            case 'available':
                return 'Room is available for booking';
            case 'in_process':
                $msg = 'Room is currently being booked by another user';
                if ($queue_count > 0) {
                    $msg .= '. ' . $queue_count . ' user(s) in waiting queue';
                }
                return $msg;
            case 'occupied':
                return 'Room is occupied for selected dates';
            case 'maintenance':
                return 'Room is under maintenance';
            case 'inactive':
                return 'Room is currently inactive';
            case 'waiting':
                return 'Room has users in waiting queue';
            default:
                return 'Room status unknown';
        }
    }
}
?>
