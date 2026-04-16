<?php
/**
 * Notifications API
 * Handles notification operations (get, mark as read, delete)
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/services/NotificationService.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notificationService = new NotificationService($conn);

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_unread':
            $notifications = $notificationService->getUnread($user_id);
            $count = $notificationService->getUnreadCount($user_id);
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => $count
            ]);
            break;
            
        case 'get_all':
            $notifications = $notificationService->getAll($user_id);
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            break;
            
        case 'get_count':
            $count = $notificationService->getUnreadCount($user_id);
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;
            
        case 'mark_read':
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                $result = $notificationService->markAsRead($notification_id, $user_id);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Marked as read' : 'Failed to mark as read'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_all_read':
            $result = $notificationService->markAllAsRead($user_id);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'All notifications marked as read' : 'Failed to mark all as read'
            ]);
            break;
            
        case 'delete':
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                $result = $notificationService->delete($notification_id, $user_id);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Notification deleted' : 'Failed to delete notification'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Notification API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
