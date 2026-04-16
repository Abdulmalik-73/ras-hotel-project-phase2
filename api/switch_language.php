<?php
/**
 * Language Switcher API
 * POST lang=en|am|om  →  sets $_SESSION['language']  →  returns JSON
 */

// Session MUST start before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

// ── Read lang param ───────────────────────────────────────────────────────────
$lang = '';
if (!empty($_POST['lang'])) {
    $lang = trim($_POST['lang']);
} else {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $data = json_decode($raw, true);
        if (!empty($data['lang'])) $lang = trim($data['lang']);
    }
}

// ── Validate ──────────────────────────────────────────────────────────────────
if (!in_array($lang, ['en', 'am', 'om'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid: ' . $lang]);
    exit;
}

// ── Set session ───────────────────────────────────────────────────────────────
$_SESSION['language'] = $lang;

// ── Save to DB (optional — keeps preference after logout/login) ───────────────
if (!empty($_SESSION['user_id'])) {
    try {
        require_once dirname(__DIR__) . '/includes/config.php';
        if (isset($conn)) {
            $uid  = (int)$_SESSION['user_id'];
            $stmt = $conn->prepare("UPDATE users SET preferred_language = ? WHERE id = ?");
            if ($stmt) { $stmt->bind_param("si", $lang, $uid); $stmt->execute(); }
        }
    } catch (Throwable $e) {
        error_log("switch_language DB: " . $e->getMessage());
    }
}

echo json_encode(['success' => true, 'language' => $lang]);
