<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get fresh room data
$rooms = get_all_rooms();

// Return room count and basic info
$response = [
    'success' => true,
    'total_rooms' => count($rooms),
    'rooms' => array_map(function($room) {
        return [
            'id' => $room['id'],
            'name' => $room['name'],
            'room_number' => $room['room_number'],
            'price' => $room['price'],
            'status' => $room['status']
        ];
    }, $rooms),
    'timestamp' => time()
];

echo json_encode($response);
?>