<?php
/**
 * Multi-Language System — English, Amharic, Afan Oromo
 * Fixed: translations always reload from session on every request.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ── Get current language from session (set by switch_language API) ────────────
function get_current_language() {
    global $conn;

    // Session is the single source of truth
    if (!empty($_SESSION['language']) && in_array($_SESSION['language'], ['en','am','om'])) {
        return $_SESSION['language'];
    }

    // Logged-in user DB preference (first visit or after login)
    if (!empty($_SESSION['user_id']) && isset($conn)) {
        $uid  = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT preferred_language FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $row  = $stmt->get_result()->fetch_assoc();
            $lang = $row['preferred_language'] ?? 'en';
            if (!in_array($lang, ['en','am','om'])) $lang = 'en';
            $_SESSION['language'] = $lang;
            return $lang;
        }
    }

    $_SESSION['language'] = 'en';
    return 'en';
}

// ── Set language (called by switch_language API) ──────────────────────────────
function set_language($lang) {
    global $conn;
    if (!in_array($lang, ['en','am','om'])) $lang = 'en';
    $_SESSION['language'] = $lang;

    if (!empty($_SESSION['user_id']) && isset($conn)) {
        $uid  = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE users SET preferred_language = ? WHERE id = ?");
        if ($stmt) { $stmt->bind_param("si", $lang, $uid); $stmt->execute(); }
    }
    return true;
}

// ── Load translation array for the current language ───────────────────────────
function load_translations($lang = null) {
    if ($lang === null) $lang = get_current_language();
    $file = __DIR__ . "/../languages/{$lang}.php";
    if (file_exists($file)) return include $file;
    return include __DIR__ . "/../languages/en.php";
}

// ── __() — translate a dot-notation key ──────────────────────────────────────
// IMPORTANT: Always reset to null at the top of language.php so every new
// HTTP request loads fresh translations matching the current session language.
$GLOBALS['_lang_cache'] = null;

function __($key, $default = null) {
    if ($GLOBALS['_lang_cache'] === null) {
        $GLOBALS['_lang_cache'] = load_translations();
    }
    $keys  = explode('.', $key);
    $value = $GLOBALS['_lang_cache'];
    foreach ($keys as $k) {
        if (is_array($value) && array_key_exists($k, $value)) {
            $value = $value[$k];
        } else {
            return $default ?? $key;
        }
    }
    return is_string($value) ? $value : ($default ?? $key);
}

function __p($key, $params = [], $default = null) {
    $text = __($key, $default);
    foreach ($params as $pk => $pv) $text = str_replace("{{$pk}}", $pv, $text);
    return $text;
}

// Initialise
$current_lang = get_current_language();
