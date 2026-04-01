<?php
/**
 * Language Switcher API
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/language.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = $_POST['lang'] ?? 'en';
    
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
