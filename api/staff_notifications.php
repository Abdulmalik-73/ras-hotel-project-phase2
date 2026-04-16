<?php
/**
 * Staff Notifications API
 * Used by receptionist dashboard to get/mark new booking notifications
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Only staff can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['receptionist', 'admin', 'manager', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Get unread notifications ──────────────────────────────────────────────
    case 'get_unread':
        $result = $conn->query(
            "SELECT * FROM staff_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 50"
        );
        $notifications = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $count = count($notifications);

        echo json_encode([
            'success'       => true,
            'count'         => $count,
            'notifications' => $notifications,
        ]);
        break;

    // ── Mark single notification as read ─────────────────────────────────────
    case 'mark_read':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->prepare("UPDATE staff_notifications SET is_read=1, read_at=NOW() WHERE id=?")
                 ->bind_param("i", $id) && true;
            $stmt = $conn->prepare("UPDATE staff_notifications SET is_read=1, read_at=NOW() WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        }
        break;

    // ── Mark all as read ─────────────────────────────────────────────────────
    case 'mark_all_read':
        $conn->query("UPDATE staff_notifications SET is_read=1, read_at=NOW() WHERE is_read=0");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
