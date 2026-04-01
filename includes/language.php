<?php
/**
 * Multi-Language System
 * Supports: English, Amharic (አማርኛ), Afan Oromo
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get current language from session or user preference
function get_current_language() {
    global $conn;
    
    // Check if language is set in session
    if (isset($_SESSION['language']) && !empty($_SESSION['language'])) {
        return $_SESSION['language'];
    }
    
    // Check if user is logged in and has language preference
    if (isset($_SESSION['user_id']) && isset($conn)) {
        $user_id = $_SESSION['user_id'];
        $query = "SELECT preferred_language FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $lang = $row['preferred_language'];
            // If user's preference is null or empty, default to English
            if (empty($lang) || !in_array($lang, ['en', 'am', 'om'])) {
                $lang = 'en';
            }
            $_SESSION['language'] = $lang;
            return $lang;
        }
    }
    
    // Default to English for guests and when no preference is set
    $_SESSION['language'] = 'en';
    return 'en';
}

// Set language
function set_language($lang) {
    global $conn;
    
    // Validate language
    if (!in_array($lang, ['en', 'am', 'om'])) {
        $lang = 'en';
    }
    
    // Set in session
    $_SESSION['language'] = $lang;
    
    // Update user preference if logged in
    if (isset($_SESSION['user_id']) && isset($conn)) {
        $user_id = $_SESSION['user_id'];
        $query = "UPDATE users SET preferred_language = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $lang, $user_id);
        $stmt->execute();
    }
}

// Load translations
function load_translations($lang = null) {
    if ($lang === null) {
        $lang = get_current_language();
    }
    
    $file = __DIR__ . "/../languages/{$lang}.php";
    
    if (file_exists($file)) {
        return include $file;
    }
    
    // Fallback to English
    return include __DIR__ . "/../languages/en.php";
}

// Get translation
function __($key, $default = null) {
    static $translations = null;
    
    if ($translations === null) {
        $translations = load_translations();
    }
    
    // Support nested keys like 'nav.home'
    $keys = explode('.', $key);
    $value = $translations;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default ?? $key;
        }
    }
    
    return $value;
}

// Get translation with parameters
function __p($key, $params = [], $default = null) {
    $text = __($key, $default);
    
    foreach ($params as $param_key => $param_value) {
        $text = str_replace("{{$param_key}}", $param_value, $text);
    }
    
    return $text;
}

// Initialize language
$current_lang = get_current_language();
?>
