<?php
/**
 * Admin Actions API
 * Handles admin-only operations like deleting bookings
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Admin only
require_auth_roles(['admin', 'super_admin'], '../login.php');

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'delete_booking':
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        if ($booking_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
            exit;
        }

        // Get booking info for logging
        $bq = $conn->prepare("SELECT booking_reference, status FROM bookings WHERE id = ?");
        $bq->bind_param("i", $booking_id);
        $bq->execute();
        $booking = $bq->get_result()->fetch_assoc();

        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }

        // Delete the booking (cascades to related tables via FK)
        $del = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $del->bind_param("i", $booking_id);

        if ($del->execute()) {
            error_log("Admin deleted booking #{$booking_id} ({$booking['booking_reference']}) by user {$_SESSION['user_id']}");
            echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $conn->error]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
