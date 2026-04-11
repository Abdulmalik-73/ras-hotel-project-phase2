<?php
/**
 * Language Switcher API
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/language.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle both JSON and form data
    $lang = '';
    
    if (isset($_POST['lang'])) {
        $lang = $_POST['lang'];
    } else {
        // Try to get from JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['lang'])) {
            $lang = $input['lang'];
        }
    }
    
    // Validate language
    if (!in_array($lang, ['en', 'am', 'om'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid language']);
        exit;
    }
    
    // Set language
    set_language($lang);
    
    echo json_encode([
        'success' => true,
        'message' => 'Language changed successfully',
        'language' => $lang
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
