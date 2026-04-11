<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get fresh room data
$all_rooms = get_all_rooms();

$response = [
    'success' => true,
    'total_rooms' => count($all_rooms),
    'html_options' => '',
    'timestamp' => time()
];

if (empty($all_rooms)) {
    $response['html_options'] = '<option disabled>No active rooms found in database</option>';
} else {
    $rooms_by_type = [];
    
    foreach ($all_rooms as $room) {
        $rooms_by_type[$room['name']][] = $room;
    }
    
    // Generate HTML options
    $html = '';
    foreach ($rooms_by_type as $room_type_name => $rooms_in_type) {
        $first_room = $rooms_in_type[0];
        $price_formatted = number_format($first_room['price'], 2);
        
        $html .= '<optgroup label="' . htmlspecialchars($room_type_name) . ' - ETB ' . $price_formatted . '/night">';
        
        foreach ($rooms_in_type as $room) {
            $html .= '<option value="' . $room['id'] . '" ';
            $html .= 'data-price="' . $room['price'] . '" ';
            $html .= 'data-capacity="' . $room['capacity'] . '">';
            $html .= htmlspecialchars($room['name']) . ' Number ' . $room['room_number'] . ' - ETB ' . number_format($room['price'], 2) . '/night';
            $html .= '</option>';
        }
        
        $html .= '</optgroup>';
    }
    
    $response['html_options'] = $html;
}

echo json_encode($response);
?>