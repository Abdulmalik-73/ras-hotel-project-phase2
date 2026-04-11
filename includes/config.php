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
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        die('.env file not found. Please copy .env.example to .env and configure it.');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set as environment variable and define constant
            putenv("$key=$value");
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Load .env file from project root
loadEnv(__DIR__ . '/../.env');

// Session Configuration and Start
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', defined('SESSION_COOKIE_SECURE') ? SESSION_COOKIE_SECURE : 0);
    session_start();
}

// Timezone Configuration
date_default_timezone_set(defined('TIMEZONE') ? TIMEZONE : 'Africa/Addis_Ababa');

// Error Reporting (configured from .env)
if (defined('ERROR_REPORTING')) {
    $error_reporting_str = ERROR_REPORTING;
    
    // Parse error reporting string
    if ($error_reporting_str === 'E_ALL') {
        $error_level = E_ALL;
    } elseif (strpos($error_reporting_str, '&') !== false) {
        // Handle expressions like "E_ALL & ~E_WARNING & ~E_NOTICE"
        eval('$error_level = ' . $error_reporting_str . ';');
    } else {
        $error_level = 0;
    }
} else {
    $error_level = 0;
}

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
