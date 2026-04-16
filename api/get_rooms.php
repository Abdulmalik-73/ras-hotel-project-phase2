<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once '../includes/config.php';
require_once '../includes/functions.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

try {
    // Always fetch fresh from DB
    $rooms = get_all_rooms($limit);
    error_log("api/get_rooms.php returned " . count($rooms) . " rooms");
    
    echo json_encode([
        'success'   => true,
        'data'      => $rooms,
        'timestamp' => time(),
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching rooms: ' . $e->getMessage()
    ]);
}
?>
