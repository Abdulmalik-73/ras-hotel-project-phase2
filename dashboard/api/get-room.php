<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('Room ID required');
}

$room_id = (int)$_GET['id'];

$query = "SELECT * FROM rooms WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($room = $result->fetch_assoc()) {
    header('Content-Type: application/json');
    echo json_encode($room);
} else {
    http_response_code(404);
    exit('Room not found');
}
?>