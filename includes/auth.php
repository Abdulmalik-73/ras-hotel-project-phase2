<?php
/**
 * Professional Authentication and Authorization System
 * Handles login checks, role-based access control, and session management
 */

// Prevent direct access
if (!isset($conn)) {
    die('Database connection not available. Please include config.php first.');
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user's role
 */
function get_user_role() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

/**
 * Require user to be logged in (redirect to login if not)
 */
function require_login($redirect_url = 'login.php') {
    if (!is_logged_in()) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Check if user has specific role
 */
function check_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    
    // Check both possible session variables for role
    $user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    
    // Super admin has access to everything
    if ($user_role === 'super_admin') {
        return true;
    }
    
    // Admin has access to everything except super admin functions
    if ($user_role === 'admin' && $required_role !== 'super_admin') {
        return true;
    }
    
    // Check specific role permissions
    switch ($required_role) {
        case 'super_admin':
            return $user_role === 'super_admin';
        case 'admin':
            return $user_role === 'admin';
        case 'manager':
            return in_array($user_role, ['admin', 'manager']);
        case 'receptionist':
            return in_array($user_role, ['admin', 'manager', 'receptionist']);
        case 'customer':
            return in_array($user_role, ['admin', 'manager', 'receptionist', 'customer', 'guest']);
        default:
            return false;
    }
}

/**
 * Require specific role (redirect if user doesn't have permission)
 */
function require_role($required_role, $redirect_url = '../login.php') {
    if (!check_role($required_role)) {
        // Use absolute URL to avoid relative redirect failures on Render proxy
        $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        header("Location: $proto://$host/login.php");
        exit();
    }
}

/**
 * SAFE SUPERADMIN CREATION - Only creates if doesn't exist
 */
function ensure_superadmin_exists() {
    global $conn;
    
    try {
        // Check if superadmin already exists
        $check_query = "SELECT id FROM users WHERE username = 'superadmin' OR role = 'super_admin' LIMIT 1";
        $result = $conn->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            // Superadmin already exists, do nothing
            return true;
        }
        
        // Create superadmin account
        $first_name = 'Super';
        $last_name = 'Admin';
        $username = 'superadmin';
        $email = 'superadmin@hararras.com';
        $password = password_hash('superadmin123', PASSWORD_DEFAULT); // Use bcrypt
        $role = 'super_admin';
        $status = 'active';
        
        $insert_query = "INSERT INTO users (first_name, last_name, username, email, password, role, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("sssssss", $first_name, $last_name, $username, $email, $password, $role, $status);
        
        if ($stmt->execute()) {
            error_log("Superadmin account created successfully");
            return true;
        } else {
            error_log("Failed to create superadmin: " . $stmt->error);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error in ensure_superadmin_exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Login Security: Check if account is locked
 */
function is_account_locked($email) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT lock_until FROM users WHERE email = ?");
        if (!$stmt) return false;
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error checking account lock: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear expired account locks
 */
function clear_expired_locks() {
    global $conn;
    
    try {
        // Check if lock_until column exists before using it
        $check = $conn->query("SHOW COLUMNS FROM users LIKE 'lock_until'");
        if (!$check || $check->num_rows == 0) {
            return; // Column doesn't exist, skip
        }
        
        $current_time = date('Y-m-d H:i:s');
        $query = "UPDATE users SET failed_attempts = 0, lock_until = NULL 
                 WHERE lock_until IS NOT NULL AND lock_until <= ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $current_time);
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log("Error clearing expired locks: " . $e->getMessage());
    }
}

/**
 * Update last login timestamp
 */
function update_last_login($user_id) {
    global $conn;
    
    try {
        $query = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            return $stmt->execute();
        }
        return false;
    } catch (Exception $e) {
        error_log("Error updating last login: " . $e->getMessage());
        return false;
    }
}

/**
 * Professional password verification
 * Supports both bcrypt (new) and MD5 (legacy) for backward compatibility
 */
function verify_user_password($input_password, $stored_password) {
    // First try bcrypt (recommended)
    if (password_verify($input_password, $stored_password)) {
        return true;
    }
    
    // Fallback to MD5 for legacy passwords
    if (md5($input_password) === $stored_password) {
        return true;
    }
    
    // Fallback to plain text (very old systems)
    if ($input_password === $stored_password) {
        return true;
    }
    
    return false;
}

/**
 * Hash password using bcrypt (modern method)
 */
function hash_user_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Prevent browser caching for secure pages
 */
function prevent_cache() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
}

/**
 * Secure logout function
 */
function secure_logout($redirect_to = 'login.php') {
    // Prevent caching
    prevent_cache();
    
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
    
    // Redirect to login
    header("Location: $redirect_to");
    exit();
}

/**
 * Require authentication and prevent caching
 * Use this at the top of all protected pages
 */
function require_auth($redirect_url = '../login.php') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    prevent_cache();

    if (!is_logged_in()) {
        // Build absolute URL to avoid relative redirect failures on Render
        $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $abs   = $proto . '://' . $host . '/login.php';
        header("Location: $abs");
        exit();
    }
}

/**
 * Require authentication with role check
 */
function require_auth_role($required_role, $redirect_url = '../login.php') {
    require_auth($redirect_url);
    require_role($required_role, $redirect_url);
}

/**
 * Require authentication with multiple role check
 */
function require_auth_roles($allowed_roles, $redirect_url = '../login.php') {
    require_auth($redirect_url);

    $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $abs   = $proto . '://' . $host . '/login.php';

    if (!is_logged_in()) {
        header("Location: $abs");
        exit();
    }

    $user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    if (!in_array($user_role, $allowed_roles)) {
        header("Location: $abs");
        exit();
    }
}
