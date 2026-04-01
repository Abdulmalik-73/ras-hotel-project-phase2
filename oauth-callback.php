<?php
/**
 * OAuth Callback Handler
 * Handles OAuth 2.0 callbacks from Google and GitHub
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/services/OAuthService.php';

$error = '';
$code = isset($_GET['code']) ? $_GET['code'] : '';
$state = isset($_GET['state']) ? $_GET['state'] : '';

// Validate state (CSRF protection)
if (empty($state)) {
    die('No state parameter received. Please try logging in again.');
}

if (!isset($_SESSION['oauth_state'])) {
    // Session expired or lost, redirect to login to start fresh
    header("Location: login.php?error=" . urlencode("Login session expired. Please try again."));
    exit();
}

if ($state !== $_SESSION['oauth_state']) {
    die('Invalid state parameter. Possible CSRF attack.');
}

// Get provider from session
$provider = isset($_SESSION['oauth_provider']) ? $_SESSION['oauth_provider'] : '';

// Validate provider
if (!in_array($provider, ['google', 'github'])) {
    die('Invalid OAuth provider');
}

// Validate authorization code
if (empty($code)) {
    die('No authorization code received');
}

// Check if we've already processed this code (prevent duplicate processing)
$code_hash = md5($code);
if (isset($_SESSION['last_oauth_code']) && $_SESSION['last_oauth_code'] === $code_hash) {
    // This code was already used, redirect to login with error
    header("Location: login.php?error=" . urlencode("This login session has expired. Please try again."));
    exit();
}

// Store this code hash to prevent reuse
$_SESSION['last_oauth_code'] = $code_hash;

try {
    $oauth = new OAuthService($conn);
    
    // Exchange code for access token
    $access_token = $oauth->getAccessToken($provider, $code);
    
    // Clear the authorization code from session to prevent reuse
    unset($_SESSION['oauth_state']);
    unset($_SESSION['oauth_provider']);
    
    // Get user information
    $user_data = $oauth->getUserInfo($provider, $access_token);
    
    // Find or create user
    $user = $oauth->findOrCreateUser($user_data);
    
    // IMPORTANT: OAuth login is only for customers, not staff
    if (in_array($user['role'], ['super_admin', 'admin', 'manager', 'receptionist'])) {
        // Clear OAuth session
        unset($_SESSION['oauth_state']);
        unset($_SESSION['oauth_provider']);
        unset($_SESSION['last_oauth_code']);
        
        // Redirect to login with error
        header("Location: login.php?error=" . urlencode("Staff members must use the regular login form. OAuth login is only available for customers."));
        exit();
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    
    // Update last login
    $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user['id']);
    $update_stmt->execute();
    
    // Log successful OAuth login
    log_user_activity($user['id'], 'login', 'User logged in via ' . ucfirst($provider), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
    
    // Clear OAuth session data
    unset($_SESSION['oauth_state']);
    unset($_SESSION['oauth_provider']);
    unset($_SESSION['last_oauth_code']);
    unset($_SESSION['oauth_redirect']);
    unset($_SESSION['oauth_room_id']);
    
    // Redirect based on stored redirect or user role
    $redirect = isset($_SESSION['oauth_redirect']) ? $_SESSION['oauth_redirect'] : '';
    $room_id = isset($_SESSION['oauth_room_id']) ? $_SESSION['oauth_room_id'] : null;
    
    if ($redirect == 'booking') {
        $redirect_url = 'booking.php' . ($room_id ? '?room=' . $room_id : '');
        header("Location: $redirect_url");
    } elseif ($redirect == 'food-booking') {
        header("Location: food-booking.php");
    } else {
        // Redirect based on role
        switch($user['role']) {
            case 'super_admin':
                header("Location: dashboard/super-admin.php");
                break;
            case 'admin':
                header("Location: dashboard/admin.php");
                break;
            case 'manager':
                header("Location: dashboard/manager.php");
                break;
            case 'receptionist':
                header("Location: dashboard/receptionist.php");
                break;
            default:
                header("Location: index.php?welcome=1");
                break;
        }
    }
    exit();
    
} catch (Exception $e) {
    // Log detailed error
    error_log("OAuth Error: " . $e->getMessage());
    error_log("OAuth Error Trace: " . $e->getTraceAsString());
    
    // Show detailed error in development mode
    if (getenv('APP_ENV') === 'development') {
        die('<h1>OAuth Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>');
    }
    
    header("Location: login.php?error=" . urlencode("Authentication failed: " . $e->getMessage()));
    exit();
}
