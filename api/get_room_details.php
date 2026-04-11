<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Room ID required']);
    exit;
}

$room_id = (int)$_GET['id'];

$query = "SELECT * FROM rooms WHERE id = ? AND status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($room = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'room' => $room
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Room not found or not available'
    ]);
}
?>