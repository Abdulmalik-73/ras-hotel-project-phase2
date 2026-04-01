<?php
/**
 * Room Lock API - Handle room booking queue operations
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/RoomLockManager.php';

// Initialize Room Lock Manager
$lockManager = new RoomLockManager($conn);

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Response array
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    switch ($action) {
        
        // Check room availability
        case 'check_availability':
            $room_id = (int)($_GET['room_id'] ?? 0);
            $check_in = $_GET['check_in'] ?? '';
            $check_out = $_GET['check_out'] ?? '';
            
            if (!$room_id || !$check_in || !$check_out) {
                throw new Exception('Missing required parameters');
            }
            
            $availability = $lockManager->checkRoomAvailability($room_id, $check_in, $check_out);
            
            $response['success'] = true;
            $response['data'] = $availability;
            $response['message'] = $availability['message'];
            break;
        
        // Acquire room lock (start booking or join queue)
        case 'acquire_lock':
            if (!is_logged_in()) {
                throw new Exception('Please login to book a room');
            }
            
            $room_id = (int)($_POST['room_id'] ?? 0);
            $check_in = $_POST['check_in'] ?? '';
            $check_out = $_POST['check_out'] ?? '';
            $user_id = $_SESSION['user_id'];
            
            if (!$room_id || !$check_in || !$check_out) {
                throw new Exception('Missing required parameters');
            }
            
            $lock_result = $lockManager->acquireRoomLock($room_id, $user_id, $check_in, $check_out);
            
            $response['success'] = $lock_result['success'];
            $response['data'] = $lock_result;
            $response['message'] = $lock_result['message'];
            break;
        
        // Release room lock (cancel booking)
        case 'release_lock':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            
            $lock_id = (int)($_POST['lock_id'] ?? 0);
            $reason = $_POST['reason'] ?? 'cancelled';
            
            if (!$lock_id) {
                throw new Exception('Missing lock ID');
            }
            
            $result = $lockManager->releaseRoomLock($lock_id, $reason);
            
            $response['success'] = $result;
            $response['message'] = $result ? 'Booking cancelled successfully' : 'Failed to cancel booking';
            break;
        
        // Get queue position
        case 'get_queue_position':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            
            $lock_id = (int)($_GET['lock_id'] ?? 0);
            
            if (!$lock_id) {
                throw new Exception('Missing lock ID');
            }
            
            $position = $lockManager->getQueuePosition($lock_id);
            
            $response['success'] = true;
            $response['data'] = ['position' => $position];
            $response['message'] = $position === 1 ? 'You can now proceed with booking' : 'You are at position #' . $position . ' in queue';
            break;
        
        // Get available rooms with status
        case 'get_available_rooms':
            $check_in = $_GET['check_in'] ?? date('Y-m-d');
            $check_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
            
            $rooms = $lockManager->getAvailableRoomsWithStatus($check_in, $check_out);
            
            $response['success'] = true;
            $response['data'] = $rooms;
            $response['message'] = count($rooms) . ' rooms found';
            break;
        
        // Extend lock expiration
        case 'extend_lock':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            
            $lock_id = (int)($_POST['lock_id'] ?? 0);
            $additional_minutes = (int)($_POST['minutes'] ?? 5);
            
            if (!$lock_id) {
                throw new Exception('Missing lock ID');
            }
            
            $result = $lockManager->extendLockExpiration($lock_id, $additional_minutes);
            
            $response['success'] = $result;
            $response['message'] = $result ? 'Lock extended by ' . $additional_minutes . ' minutes' : 'Failed to extend lock';
            break;
        
        // Cleanup expired locks (admin only)
        case 'cleanup_expired':
            if (!is_logged_in() || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
                throw new Exception('Unauthorized');
            }
            
            $count = $lockManager->cleanupExpiredLocks();
            
            $response['success'] = true;
            $response['data'] = ['cleaned_count' => $count];
            $response['message'] = $count . ' expired locks cleaned up';
            break;
        
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
