<?php
/**
 * API: Check Room Availability
 * Prevents double booking by checking room availability
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/services/RoomAvailabilityService.php';

// Get database connection
$database = new Database();
$conn = $database->connect();

// Initialize service
$availabilityService = new RoomAvailabilityService($conn);

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    $room_id = $data['room_id'] ?? null;
    $check_in_date = $data['check_in_date'] ?? null;
    $check_out_date = $data['check_out_date'] ?? null;
    $exclude_booking_id = $data['exclude_booking_id'] ?? null;
    
    // Validate required fields
    if (!$room_id || !$check_in_date || !$check_out_date) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: room_id, check_in_date, check_out_date'
        ]);
        exit;
    }
    
    // Check availability
    $result = $availabilityService->checkRoomAvailability(
        $room_id,
        $check_in_date,
        $check_out_date,
        $exclude_booking_id
    );
    
    echo json_encode([
        'success' => $result['available'],
        'available' => $result['available'],
        'message' => $result['reason'],
        'error_code' => $result['error_code'] ?? null,
        'data' => $result
    ]);
    
} elseif ($method === 'GET') {
    // Get available rooms for date range
    $check_in_date = $_GET['check_in_date'] ?? null;
    $check_out_date = $_GET['check_out_date'] ?? null;
    $room_type = $_GET['room_type'] ?? null;
    
    if (!$check_in_date || !$check_out_date) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: check_in_date, check_out_date'
        ]);
        exit;
    }
    
    // Get available rooms
    $rooms = $availabilityService->getAvailableRooms($check_in_date, $check_out_date, $room_type);
    
    echo json_encode([
        'success' => true,
        'count' => count($rooms),
        'rooms' => $rooms
    ]);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Use POST or GET.'
    ]);
}
