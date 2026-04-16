<?php
/**
 * Main Configuration File - Professional Setup
 * Loads environment variables, starts session, and includes core files
 */

// Suppress PHP warnings and notices for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/**
 * Load environment variables from .env file
 * Falls back to system environment variables (for Render/production)
 */
function loadEnv($path) {
    // If .env file exists, load it (local development)
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim($value, " \t\n\r\"'");
                putenv("$key=$value");
                if (!defined($key)) define($key, $value);
            }
        }
        // Also pick up any system env vars not present in the .env file
        // (handles Render/production env vars that override or supplement .env)
    }

    // Always define constants from system environment variables
    // (covers Render dashboard vars and any keys missing from .env)
    $keys = [
        'DB_HOST','DB_PORT','DB_USER','DB_PASS','DB_NAME',
        'SITE_URL','SITE_NAME','ADMIN_EMAIL',
        'SUPER_ADMIN_EMAIL','SUPER_ADMIN_PASSWORD',
        'SUPER_ADMIN_FIRST_NAME','SUPER_ADMIN_LAST_NAME',
        'CURRENCY_SYMBOL','CURRENCY_CODE','CURRENCY_NAME',
        'SESSION_COOKIE_SECURE','TIMEZONE','APP_ENV',
        'DISPLAY_ERRORS','ERROR_REPORTING',
        'EMAIL_ENABLED','EMAIL_HOST','EMAIL_PORT',
        'EMAIL_USERNAME','EMAIL_PASSWORD',
        'EMAIL_FROM_ADDRESS','EMAIL_FROM_NAME','EMAIL_ENCRYPTION',
        'HOTEL_NAME','HOTEL_SUPPORT_EMAIL','HOTEL_PHONE',
        'HOTEL_ADDRESS','HOTEL_WEBSITE_URL',
        'CHAPA_PUBLIC_KEY','CHAPA_SECRET_KEY','CHAPA_ENCRYPTION_KEY',
        'CHAPA_BASE_URL','CHAPA_CALLBACK_URL','CHAPA_RETURN_URL',
        'GOOGLE_CLIENT_ID','GOOGLE_CLIENT_SECRET','GOOGLE_REDIRECT_URI',
        'BREVO_API_KEY',
    ];
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && !defined($key)) {
            define($key, $value);
        }
    }
}

// Load .env file from project root
loadEnv(__DIR__ . '/../.env');

// Ensure critical constants always have fallback values
if (!defined('SITE_NAME'))       define('SITE_NAME', 'Harar Ras Hotel');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'ETB');
if (!defined('CURRENCY_CODE'))   define('CURRENCY_CODE', 'ETB');
if (!defined('ADMIN_EMAIL'))     define('ADMIN_EMAIL', 'info@hararrashotel.com');
if (!defined('TIMEZONE'))        define('TIMEZONE', 'Africa/Addis_Ababa');

// Session Configuration and Start
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_samesite', 'Lax');

    // Detect HTTPS correctly on Render (sits behind Cloudflare/proxy)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    ini_set('session.cookie_secure', $is_https ? 1 : 0);

    session_start();
}
// Timezone Configuration
date_default_timezone_set(defined('TIMEZONE') ? TIMEZONE : 'Africa/Addis_Ababa');

// Error Reporting (configured from .env)
// Safely parse error reporting level without using eval()
function parseErrorReporting($str) {
    $str = trim((string)$str);
    if ($str === '' || $str === '0') return 0;

    $map = [
        'E_ALL'        => E_ALL,
        'E_ERROR'      => E_ERROR,
        'E_WARNING'    => E_WARNING,
        'E_NOTICE'     => E_NOTICE,
        'E_DEPRECATED' => E_DEPRECATED,
        'E_STRICT'     => E_STRICT,
        'E_PARSE'      => E_PARSE,
    ];

    // Replace named constants with their integer values
    foreach ($map as $name => $val) {
        $str = str_replace($name, (string)$val, $str);
    }

    // Only allow digits, spaces, &, |, ~, ^ — no arbitrary code
    if (!preg_match('/^[\d\s&|~^()]+$/', $str)) {
        return 0;
    }

    // Evaluate the safe arithmetic expression
    $result = 0;
    // Split by | first, then handle & and ~
    $parts = preg_split('/\|/', $str);
    foreach ($parts as $part) {
        $part = trim($part);
        $andParts = preg_split('/&/', $part);
        $andVal = null;
        foreach ($andParts as $ap) {
            $ap = trim($ap);
            $negate = false;
            if (strpos($ap, '~') !== false) {
                $negate = true;
                $ap = str_replace('~', '', $ap);
            }
            $ap = trim($ap);
            $num = is_numeric($ap) ? (int)$ap : 0;
            if ($negate) $num = ~$num;
            $andVal = ($andVal === null) ? $num : ($andVal & $num);
        }
        $result |= (int)$andVal;
    }
    return $result;
}

$error_level = defined('ERROR_REPORTING') ? parseErrorReporting(ERROR_REPORTING) : 0;

error_reporting($error_level);
ini_set('display_errors', defined('DISPLAY_ERRORS') ? DISPLAY_ERRORS : 0);

// Include database connection
require_once __DIR__ . '/../config/database.php';

// Include authentication functions
require_once __DIR__ . '/auth.php';

// Include helper functions
require_once __DIR__ . '/functions.php';

// Include language system
require_once __DIR__ . '/language.php';

// AUTO-FIX DATABASE: Create payments, payment_verifications and notifications tables
try {
    $conn->query("CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `booking_id` INT(11) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `tx_ref` VARCHAR(100) NOT NULL,
        `status` ENUM('pending','paid','failed') DEFAULT 'pending',
        `chapa_response` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tx_ref` (`tx_ref`),
        KEY `user_id` (`user_id`),
        KEY `booking_id` (`booking_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `payment_verifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `booking_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `booking_reference` VARCHAR(50) NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `transaction_reference` VARCHAR(100) DEFAULT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `screenshot_path` VARCHAR(255) NOT NULL,
        `status` ENUM('pending','verified','rejected') DEFAULT 'pending',
        `verified_by` INT(11) DEFAULT NULL,
        `verified_at` DATETIME DEFAULT NULL,
        `rejection_reason` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `booking_id` (`booking_id`),
        KEY `user_id` (`user_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `type` VARCHAR(50) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `link` VARCHAR(255) DEFAULT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `read_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `is_read` (`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Staff notifications table (for receptionist/admin/manager)
    $conn->query("CREATE TABLE IF NOT EXISTS `staff_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `booking_id` INT(11) NOT NULL,
        `booking_reference` VARCHAR(50) NOT NULL,
        `booking_type` VARCHAR(30) NOT NULL,
        `customer_name` VARCHAR(200) NOT NULL,
        `customer_email` VARCHAR(255) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `service_detail` TEXT DEFAULT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `read_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `booking_id` (`booking_id`),
        KEY `is_read` (`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) { /* silently ignore */ }

// AUTO-FIX DATABASE: Create checkins table if it doesn't exist
try {
    // Check if checkins table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'checkins'");
    
    if ($check_table && $check_table->num_rows == 0) {
        // Create checkins table automatically
        $create_checkins_sql = "
        CREATE TABLE `checkins` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `customer_id` int(11) DEFAULT NULL,
          `booking_id` int(11) DEFAULT NULL,
          `hotel_name` varchar(255) NOT NULL DEFAULT 'Ras Hotel',
          `hotel_location` varchar(255) NOT NULL DEFAULT 'Main Street, City, Country',
          `check_in_date` date NOT NULL,
          `check_out_date` date NOT NULL,
          `guest_full_name` varchar(255) NOT NULL,
          `guest_date_of_birth` date NOT NULL,
          `guest_id_type` enum('passport','drivers_license','national_id') NOT NULL,
          `guest_id_number` varchar(100) NOT NULL,
          `guest_nationality` varchar(100) NOT NULL,
          `guest_home_address` text NOT NULL,
          `guest_phone_number` varchar(20) NOT NULL,
          `guest_email_address` varchar(255) NOT NULL,
          `room_type` varchar(100) NOT NULL,
          `room_number` varchar(20) DEFAULT NULL,
          `nights_stay` int(11) NOT NULL,
          `number_of_guests` int(11) NOT NULL DEFAULT 1,
          `rate_per_night` decimal(10,2) NOT NULL,
          `payment_type` enum('cash','credit_card','debit_card','bank_transfer','mobile_payment') NOT NULL,
          `amount_paid` decimal(10,2) NOT NULL,
          `balance_due` decimal(10,2) NOT NULL DEFAULT 0.00,
          `confirmation_number` varchar(100) NOT NULL,
          `additional_requests` text DEFAULT NULL,
          `checked_in_by` int(11) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `status` enum('active','checked_out','cancelled') NOT NULL DEFAULT 'active',
          PRIMARY KEY (`id`),
          UNIQUE KEY `confirmation_number` (`confirmation_number`),
          KEY `customer_id` (`customer_id`),
          KEY `booking_id` (`booking_id`),
          KEY `checked_in_by` (`checked_in_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $conn->query($create_checkins_sql);
        
        // Create indexes for better performance (only if columns exist)
        $conn->query("CREATE INDEX IF NOT EXISTS idx_checkins_guest_name ON checkins(guest_full_name)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_checkins_guest_email ON checkins(guest_email_address)");
        // Note: check_in_date and check_out_date indexes are created inline in table definition
    }
} catch (Exception $e) {
    // Silently ignore errors to prevent breaking the site
}

// AUTO-FIX DATABASE: Add preferred_language column to users table if it doesn't exist
try {
    // Check if preferred_language column exists
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'preferred_language'");
    
    if ($check_column && $check_column->num_rows == 0) {
        // Add preferred_language column
        $add_column_sql = "ALTER TABLE users ADD COLUMN preferred_language VARCHAR(5) DEFAULT 'en' AFTER email";
        $conn->query($add_column_sql);
    }
} catch (Exception $e) {
    // Silently ignore errors to prevent breaking the site
}

// AUTO-FIX DATABASE: Add Google OAuth columns to users table if they don't exist
try {
    $check_google = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if ($check_google && $check_google->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER password");
    }
    $check_oauth = $conn->query("SHOW COLUMNS FROM users LIKE 'oauth_provider'");
    if ($check_oauth && $check_oauth->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(50) DEFAULT NULL AFTER google_id");
    }
} catch (Exception $e) {
    // Silently ignore
}

// AUTO-FIX DATABASE: Create services table if it doesn't exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS `services` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `category` VARCHAR(100) NOT NULL DEFAULT 'other',
        `description` TEXT DEFAULT NULL,
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) { /* silently ignore */ }

// AUTO-FIX DATABASE: Create hotel_settings table if it doesn't exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS `hotel_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT DEFAULT NULL,
        `updated_by` INT(11) DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) { /* silently ignore */ }

// SAFE: Only create superadmin if it doesn't exist
// This prevents duplicate insertion errors
ensure_superadmin_exists();

// Clean up expired account locks
clear_expired_locks();

// Currency formatting function
function formatCurrency($amount) {
    return number_format($amount, 2) . ' ' . CURRENCY_SYMBOL;
}

// Function to format price for display
function displayPrice($price) {
    return formatCurrency($price);
}
?>
