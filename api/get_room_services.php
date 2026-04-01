<?php
/**
 * API: Get Room Services
 * Returns all services/amenities for a specific room
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

// Get database connection
$database = new Database();
$conn = $database->connect();

// Get room ID from query parameter
$room_id = $_GET['room_id'] ?? null;

if (!$room_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Room ID is required'
    ]);
    exit;
}

// Get room services
$query = "SELECT 
            rs.service_name,
            rs.service_icon,
            rs.service_category,
            rs.is_included,
            rs.display_order
          FROM room_services rs
          WHERE rs.room_id = ?
          ORDER BY rs.display_order ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

$services = [];
$services_by_category = [];

while ($row = $result->fetch_assoc()) {
    $services[] = $row;
    
    // Group by category
    $category = $row['service_category'];
    if (!isset($services_by_category[$category])) {
        $services_by_category[$category] = [];
    }
    $services_by_category[$category][] = $row;
}

// Get room details
$room_query = "SELECT id, name, room_number, room_type, price, capacity, description 
               FROM rooms WHERE id = ?";
$room_stmt = $conn->prepare($room_query);
$room_stmt->bind_param("i", $room_id);
$room_stmt->execute();
$room_result = $room_stmt->get_result();
$room = $room_result->fetch_assoc();

echo json_encode([
    'success' => true,
    'room' => $room,
    'services' => $services,
    'services_by_category' => $services_by_category,
    'total_services' => count($services)
]);
