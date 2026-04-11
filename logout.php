<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Prevent caching
prevent_cache();

// Log logout activity before destroying session (non-blocking)
if (isset($_SESSION['user_id'])) {
    try {
        log_user_activity($_SESSION['user_id'], 'logout', 'User logged out', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
    } catch (Exception $e) {
        // Log error but don't stop logout process
        error_log("Logout activity logging failed: " . $e->getMessage());
    }
}

// Use secure logout function
secure_logout('login.php');
?>
