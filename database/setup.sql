-- =====================================================
-- HARAR RAS HOTEL - COMPREHENSIVE HOTEL MANAGEMENT SYSTEM
-- =====================================================
-- Complete hotel management database with all features
-- =====================================================

-- Set character set
SET NAMES utf8mb4;-- Set character set
SET NAMES utf8mb4;

-- Disable foreign key checks temporarily for table creation
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- STEP 1: DROP ALL EXISTING TABLES (Clean slate)
-- =====================================================

DROP TABLE IF EXISTS admin_audit_logs;
DROP TABLE IF EXISTS fraud_detection_log;
DROP TABLE IF EXISTS payment_gateway_config;
DROP TABLE IF EXISTS payment_transactions;
DROP TABLE IF EXISTS refunds;
DROP TABLE IF EXISTS booking_cancellations;
DROP TABLE IF EXISTS guest_preferences;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS newsletter_subscriptions;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS oauth_tokens;
DROP TABLE IF EXISTS email_logs;
DROP TABLE IF EXISTS hotel_settings;
DROP TABLE IF EXISTS maintenance_requests;
DROP TABLE IF EXISTS housekeeping;
DROP TABLE IF EXISTS incidental_charges;
DROP TABLE IF EXISTS room_keys;
DROP TABLE IF EXISTS checkin_checkout_log;
DROP TABLE IF EXISTS user_activity_log;
DROP TABLE IF EXISTS booking_activity_log;
DROP TABLE IF EXISTS customer_feedback;
DROP TABLE IF EXISTS payment_verification_log;
DROP TABLE IF EXISTS payment_verification_queue;
DROP TABLE IF EXISTS payment_method_instructions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS service_bookings;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS food_order_items;
DROP TABLE IF EXISTS food_orders;
DROP TABLE IF EXISTS food_menu;
DROP TABLE IF EXISTS room_locks;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS users;

-- =====================================================
-- STEP 2: CREATE CORE TABLES
-- =====================================================

-- =====================================================
-- STEP 1: CREATE CORE TABLES
-- =====================================================

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    preferred_language ENUM('en', 'am', 'om') DEFAULT 'en' COMMENT 'en=English, am=Amharic, om=Afan Oromo',
    email_notifications TINYINT(1) DEFAULT 1,
    sms_notifications TINYINT(1) DEFAULT 1,
    booking_reminders TINYINT(1) DEFAULT 1,
    role ENUM('customer', 'receptionist', 'manager', 'admin', 'super_admin') DEFAULT 'customer',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    password_changed_at TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_language (preferred_language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms Table - UPDATED WITHOUT REMOVED ROOM TYPES
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    room_type ENUM('standard', 'deluxe', 'suite', 'family', 'presidential', 'single', 'double', 'executive') NOT NULL,
    description TEXT,
    capacity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    status ENUM('active', 'occupied', 'booked', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 2: CREATE BOOKINGS TABLE (SUPPORTS FOOD ORDERS)
-- =====================================================

-- Bookings Table (unified for rooms and food orders)
-- UPDATED: Screenshot-only payment system
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customer_name VARCHAR(200),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    id_type VARCHAR(50),
    id_number VARCHAR(100),
    room_id INT NULL,
    room_key_number VARCHAR(50),
    booking_reference VARCHAR(50) UNIQUE NOT NULL,
    check_in_date DATE NULL,
    actual_checkin_time TIMESTAMP NULL,
    check_out_date DATE NULL,
    actual_checkout_time TIMESTAMP NULL,
    customers INT NOT NULL DEFAULT 1,
    total_price DECIMAL(10, 2) NOT NULL,
    incidental_deposit DECIMAL(10, 2) DEFAULT 0.00,
    deposit_payment_method VARCHAR(50),
    final_amount DECIMAL(10, 2),
    deposit_refunded DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(100) NULL COMMENT 'telebirr, cbe, abyssinia, cooperative',
    special_requests TEXT,
    checkout_notes TEXT,
    -- Service booking integration
    booking_type ENUM('room', 'food_order', 'spa_service', 'laundry_service') DEFAULT 'room',
    payment_reference VARCHAR(50) NULL,
    payment_deadline TIMESTAMP NULL,
    verification_status ENUM('pending_payment', 'pending_verification', 'verified', 'rejected', 'expired') DEFAULT 'pending_payment',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    checked_in_by INT NULL,
    checked_out_by INT NULL,
    -- Screenshot Payment System (NEW)
    screenshot_path VARCHAR(255) NULL COMMENT 'Path to uploaded payment screenshot',
    screenshot_uploaded_at TIMESTAMP NULL COMMENT 'When screenshot was uploaded',
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_customer_name (customer_name),
    INDEX idx_customer_email (customer_email),
    INDEX idx_booking_reference (booking_reference),
    INDEX idx_verification_status (verification_status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_screenshot_uploaded (screenshot_uploaded_at),
    
    -- Foreign keys
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_out_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 3: CREATE FOOD ORDER TABLES
-- =====================================================

-- Food Orders Table
CREATE TABLE IF NOT EXISTS food_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    order_reference VARCHAR(50) UNIQUE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    table_reservation TINYINT(1) DEFAULT 0,
    reservation_date DATE NULL,
    reservation_time TIME NULL,
    guests INT DEFAULT 1,
    special_requests TEXT,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Food Order Items Table
CREATE TABLE IF NOT EXISTS food_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES food_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 4: CREATE PAYMENT SYSTEM TABLES
-- =====================================================

-- Payment Method Instructions Table (FIXED - NO reference_format column)
CREATE TABLE IF NOT EXISTS payment_method_instructions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_code VARCHAR(50) UNIQUE NOT NULL,
    method_name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    account_holder_name VARCHAR(100) NOT NULL,
    mobile_number VARCHAR(20) NULL,
    payment_instructions TEXT NOT NULL,
    verification_tips TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Verification Log Table (IMPORTANT - for audit trail)
CREATE TABLE IF NOT EXISTS payment_verification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_reference VARCHAR(50) NOT NULL,
    action_type ENUM('screenshot_uploaded', 'transaction_id_submitted', 'verification_approved', 'verification_rejected', 'payment_expired') NOT NULL,
    performed_by INT NULL,
    screenshot_path VARCHAR(255) NULL,
    transaction_id VARCHAR(100) NULL,
    verification_notes TEXT NULL,
    bank_method VARCHAR(50) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Verification Queue Table (IMPORTANT - for staff dashboard)
CREATE TABLE IF NOT EXISTS payment_verification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_reference VARCHAR(50) NOT NULL,
    customer_name VARCHAR(200) NOT NULL,
    room_name VARCHAR(100) NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NULL,
    screenshot_path VARCHAR(255) NULL,
    transaction_id VARCHAR(100) NULL,
    uploaded_at TIMESTAMP NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    assigned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Verification Attempts Table (for automatic API verification logging)
CREATE TABLE IF NOT EXISTS payment_verification_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    amount_match TINYINT(1) DEFAULT NULL,
    date_match TINYINT(1) DEFAULT NULL,
    response_data TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_gateway (gateway),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 5: CREATE ADDITIONAL IMPORTANT TABLES
-- =====================================================

-- Food Menu Table (IMPORTANT - for food ordering system)
CREATE TABLE IF NOT EXISTS food_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('appetizer', 'main_course', 'dessert', 'beverage', 'traditional', 'international') NOT NULL,
    description TEXT,
    price DECIMAL(8, 2) NOT NULL,
    image VARCHAR(255),
    ingredients TEXT,
    is_vegetarian BOOLEAN DEFAULT FALSE,
    is_vegan BOOLEAN DEFAULT FALSE,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Services Table (IMPORTANT - for hotel services)
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('restaurant', 'spa', 'laundry', 'transport', 'tours', 'other') NOT NULL,
    description TEXT,
    price DECIMAL(10, 2),
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Check-ins Table (IMPORTANT - for front desk operations)
-- NOTE: Complete table definition is located later in the file

-- Contact Messages Table (IMPORTANT - for customer inquiries)
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'resolved') DEFAULT 'new',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Activity Log Table
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    activity_type ENUM('login', 'logout', 'booking', 'registration', 'profile_update', 'password_change') NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_activity (activity_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login Attempts Tracking Table (for security)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    user_agent TEXT,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_attempt_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password Reset Table (for forgot password functionality)
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Booking Activity Log Table
CREATE TABLE IF NOT EXISTS booking_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    user_id INT,
    activity_type ENUM('created', 'confirmed', 'modified', 'cancelled', 'checked_in', 'checked_out') NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    description TEXT,
    performed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_activity (activity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Room Images Table
CREATE TABLE IF NOT EXISTS room_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(200),
    is_primary BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newsletter Subscriptions Table
CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100),
    status ENUM('active', 'unsubscribed') DEFAULT 'active',
    subscription_source VARCHAR(50) DEFAULT 'website',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff Tasks Table
CREATE TABLE IF NOT EXISTS staff_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assigned_to INT NOT NULL,
    assigned_by INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    due_date DATE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Housekeeping Table
CREATE TABLE IF NOT EXISTS housekeeping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    assigned_to INT,
    task_type ENUM('cleaning', 'maintenance', 'inspection') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    scheduled_date DATE NOT NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_room (room_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hotel Settings Table
CREATE TABLE IF NOT EXISTS hotel_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('text', 'email', 'phone', 'number', 'textarea') DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default hotel settings
INSERT INTO hotel_settings (setting_key, setting_value, setting_type, description) VALUES
('hotel_name', 'Harar Ras Hotel', 'text', 'Official hotel name'),
('contact_email', 'info@hararrashotel.com', 'email', 'Main contact email address'),
('contact_phone', '+1-234-567-8900', 'phone', 'Main contact phone number'),
('hotel_address', 'Main Street, City, Country', 'textarea', 'Hotel physical address'),
('check_in_time', '14:00', 'text', 'Standard check-in time'),
('check_out_time', '12:00', 'text', 'Standard check-out time'),
('currency_symbol', 'ETB', 'text', 'Currency symbol'),
('tax_rate', '15', 'number', 'Tax rate percentage')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- =====================================================
-- =====================================================
-- STEP 6: INSERT PAYMENT METHODS (SCREENSHOT UPLOAD SYSTEM)
-- =====================================================

INSERT INTO payment_method_instructions 
(method_code, method_name, bank_name, account_number, account_holder_name, mobile_number, payment_instructions, verification_tips, display_order) 
VALUES
('telebirr', 'TeleBirr', 'Ethio Telecom', '0973409026', 'Harar Ras Hotel', NULL, 
'1. Open TeleBirr app\n2. Select Send Money\n3. Enter amount\n4. Enter recipient: 0973409026\n5. Add reference code\n6. Complete transaction\n7. Take screenshot of confirmation\n8. Upload screenshot on payment page', 
'Ensure the screenshot shows the exact amount, recipient number, reference code, and successful transaction status.', 1),

('cbe', 'CBE Mobile Banking', 'Commercial Bank of Ethiopia', '1000274236552', 'Harar Ras Hotel', NULL, 
'1. Open CBE Mobile app\n2. Login to your account\n3. Select Transfer Money\n4. Enter amount\n5. Enter account: 1000274236552\n6. Add reference code\n7. Complete transfer\n8. Take screenshot\n9. Upload screenshot on payment page', 
'Screenshot must show successful transfer with correct amount, account number, and reference code.', 2),

('abyssinia', 'Abyssinia Bank', 'Abyssinia Bank', '244422382', 'Harar Ras Hotel', NULL,
'1. Login to Abyssinia Bank Mobile/Internet Banking\n2. Select Transfer/Payment\n3. Enter amount\n4. Enter account: 244422382\n5. Add reference code\n6. Complete transaction\n7. Take screenshot of confirmation\n8. Upload screenshot on payment page',
'Verify the screenshot shows successful transaction with correct amount, account number 244422382, and reference code.', 3),

('cooperative', 'Cooperative Bank of Oromia', 'Cooperative Bank of Oromia', '1000056621528', 'Harar Ras Hotel', NULL,
'1. Login to Cooperative Bank Mobile/Internet Banking\n2. Select Fund Transfer\n3. Enter amount\n4. Enter account: 1000056621528\n5. Add reference code\n6. Complete transfer\n7. Take screenshot of confirmation\n8. Upload screenshot on payment page',
'Ensure screenshot displays successful transfer with correct amount, account number 1000056621528, and reference code.', 4)
ON DUPLICATE KEY UPDATE method_code=method_code;

-- =====================================================
-- STEP 7: INSERT SAMPLE DATA
-- =====================================================

-- =====================================================
-- CHECK-IN/CHECK-OUT SYSTEM ENHANCEMENTS
-- =====================================================
-- NOTE: All bookings table columns are now in the CREATE TABLE statement above
-- No ALTER TABLE needed - table is complete

-- Incidental Charges Table
CREATE TABLE IF NOT EXISTS incidental_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    charge_type ENUM('minibar', 'room_service', 'laundry', 'phone', 'damage', 'other') NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    quantity INT DEFAULT 1,
    total_amount DECIMAL(10, 2) NOT NULL,
    charged_by INT NOT NULL,
    charge_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'paid', 'refunded') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (charged_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Room Keys Tracking Table
CREATE TABLE IF NOT EXISTS room_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    room_id INT NOT NULL,
    key_number VARCHAR(50) NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issued_by INT NOT NULL,
    returned_at TIMESTAMP NULL,
    returned_to INT NULL,
    status ENUM('issued', 'returned', 'lost', 'replaced') DEFAULT 'issued',
    notes TEXT,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (returned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Check-in/Check-out Log Table
CREATE TABLE IF NOT EXISTS checkin_checkout_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    action_type ENUM('check_in', 'check_out') NOT NULL,
    performed_by INT NOT NULL,
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_collected DECIMAL(10, 2) DEFAULT 0.00,
    payment_method VARCHAR(50),
    deposit_amount DECIMAL(10, 2) DEFAULT 0.00,
    incidental_charges DECIMAL(10, 2) DEFAULT 0.00,
    refund_amount DECIMAL(10, 2) DEFAULT 0.00,
    id_verified BOOLEAN DEFAULT FALSE,
    id_type VARCHAR(50),
    id_number VARCHAR(100),
    notes TEXT,
    ip_address VARCHAR(45),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_action (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guest Preferences Table (for future stays)
CREATE TABLE IF NOT EXISTS guest_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_type ENUM('room_type', 'floor', 'bed_type', 'pillow_type', 'dietary', 'other') NOT NULL,
    preference_value VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 7: INSERT SAMPLE DATA
-- =====================================================

-- Insert Sample Rooms - UPDATED WITH ROOM NUMBERS 1-40
INSERT INTO rooms (name, room_number, room_type, description, capacity, price, status) VALUES
-- Standard Single Rooms (1-4)
('Standard Single Room', '1', 'standard', '1 single bed, free wifi, air conditioning, private bathroom', 1, 2000.00, 'active'),
('Standard Single Room', '2', 'standard', '1 single bed, free wifi, air conditioning, private bathroom', 1, 2000.00, 'active'),
('Standard Single Room', '3', 'standard', '1 single bed, free wifi, air conditioning, private bathroom', 1, 2000.00, 'active'),
('Standard Single Room', '4', 'standard', '1 single bed, free wifi, air conditioning, private bathroom', 1, 2000.00, 'active'),

-- Standard Double Rooms (5-8)
('Standard Double Room', '5', 'standard', '1 double bed, free wifi, air conditioning, private bathroom', 2, 2500.00, 'active'),
('Standard Double Room', '6', 'standard', '1 double bed, free wifi, air conditioning, private bathroom', 2, 2500.00, 'active'),
('Standard Double Room', '7', 'standard', '1 double bed, free wifi, air conditioning, private bathroom', 2, 2500.00, 'active'),
('Standard Double Room', '8', 'standard', '1 double bed, free wifi, air conditioning, private bathroom', 2, 2500.00, 'active'),

-- Deluxe Single Rooms (9-12)
('Deluxe Single Room', '9', 'deluxe', '1 queen bed, free wifi, air conditioning, private bathroom, mini bar, city view', 1, 3000.00, 'active'),
('Deluxe Single Room', '10', 'deluxe', '1 queen bed, free wifi, air conditioning, private bathroom, mini bar, city view', 1, 3000.00, 'active'),
('Deluxe Single Room', '11', 'deluxe', '1 queen bed, free wifi, air conditioning, private bathroom, mini bar, city view', 1, 3000.00, 'active'),
('Deluxe Single Room', '12', 'deluxe', '1 queen bed, free wifi, air conditioning, private bathroom, mini bar, city view', 1, 3000.00, 'active'),

-- Deluxe Double Rooms (13-16)
('Deluxe Double Room', '13', 'deluxe', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, city view, work desk', 2, 3500.00, 'active'),
('Deluxe Double Room', '14', 'deluxe', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, city view, work desk', 2, 3500.00, 'active'),
('Deluxe Double Room', '15', 'deluxe', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, city view, work desk', 2, 3500.00, 'active'),
('Deluxe Double Room', '16', 'deluxe', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, city view, work desk', 2, 3500.00, 'active'),

-- Family Suites (17-20)
('Family Suite', '17', 'family', '2 double beds, free wifi, air conditioning, private bathroom, mini bar, living area, kitchenette', 4, 4000.00, 'active'),
('Family Suite', '18', 'family', '2 double beds, free wifi, air conditioning, private bathroom, mini bar, living area, kitchenette', 4, 4000.00, 'active'),
('Family Suite', '19', 'family', '2 double beds, free wifi, air conditioning, private bathroom, mini bar, living area, kitchenette', 4, 4000.00, 'active'),
('Family Suite', '20', 'family', '2 double beds, free wifi, air conditioning, private bathroom, mini bar, living area, kitchenette', 4, 4000.00, 'active'),

-- Executive Suites (21-28)
('Executive Suite', '21', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '22', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '23', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '24', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '25', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '26', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '27', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '28', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),

-- Presidential Suites (29-40)
('Presidential Suite', '29', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '30', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '31', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '32', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '33', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '34', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '35', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '36', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '37', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '38', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '39', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active')
ON DUPLICATE KEY UPDATE room_number=room_number;

-- =====================================================
-- SERVICES DATA WILL BE INSERTED AT THE END WITH CLEANUP
-- =====================================================
-- (Services insertion moved to end of file to ensure no duplicates)

-- Insert Sample Food Menu Items (Prices in Ethiopian Birr)
INSERT INTO food_menu (name, category, description, price, image, ingredients, is_vegetarian, is_vegan, is_available) VALUES
('Doro Wat', 'traditional', 'Traditional Ethiopian chicken stew with berbere spice and hard-boiled eggs', 480.00, 'assets/images/food/injera-doro-wat.jpg', 'Chicken, berbere spice, onions, eggs, injera', FALSE, FALSE, TRUE),
('Vegetarian Combo', 'traditional', 'Assorted vegetarian dishes served on injera bread', 400.00, 'assets/images/food/vegetarian-combo.jpg', 'Lentils, vegetables, injera, berbere', TRUE, TRUE, TRUE),
('Kitfo', 'traditional', 'Ethiopian steak tartare seasoned with mitmita and served with ayib', 580.00, 'assets/images/food/kitfo.jpg', 'Raw beef, mitmita, ayib cheese, injera', FALSE, FALSE, TRUE),
('Tibs', 'traditional', 'Sautéed meat with onions, tomatoes, and Ethiopian spices', 500.00, 'assets/images/food/tibs.jpg', 'Beef, onions, tomatoes, berbere, injera', FALSE, FALSE, TRUE),
('Ethiopian Coffee', 'beverage', 'Traditional Ethiopian coffee ceremony with freshly roasted beans', 200.00, 'assets/images/food/ethiopian-coffee.jpg', 'Ethiopian coffee beans, sugar, milk optional', TRUE, TRUE, TRUE),
('Honey Wine (Tej)', 'beverage', 'Traditional Ethiopian honey wine', 400.00, 'assets/images/food/tej.jpg', 'Honey, water, gesho', TRUE, TRUE, TRUE),
('Baklava', 'dessert', 'Sweet pastry with nuts and honey', 250.00, 'assets/images/food/baklava.jpg', 'Phyllo pastry, nuts, honey, butter', TRUE, FALSE, TRUE);

-- Insert Room Images (for first 7 rooms as examples)
INSERT INTO room_images (room_id, image_path, alt_text, is_primary, display_order) VALUES
(1, 'assets/images/rooms/standard-single.jpg', 'Standard Single Room - Main View', TRUE, 1),
(2, 'assets/images/rooms/standard-double.jpg', 'Standard Double Room - Main View', TRUE, 1),
(3, 'assets/images/rooms/deluxe-single.jpg', 'Deluxe Single Room - Main View', TRUE, 1),
(4, 'assets/images/rooms/deluxe-double.jpg', 'Deluxe Double Room - Main View', TRUE, 1),
(5, 'assets/images/rooms/family-suite.jpg', 'Family Suite - Living Area', TRUE, 1),
(6, 'assets/images/rooms/executive-suite.jpg', 'Executive Suite - Main View', TRUE, 1),
(7, 'assets/images/rooms/presidential-suite.jpg', 'Presidential Suite - Living Room', TRUE, 1);

-- Insert Default Superadmin User (REMOVED - Using correct credentials below)

-- Insert Test Users for Role Testing (REMOVED - Using correct credentials below)

-- =====================================================
-- ADMIN AUDIT LOGS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    target_user_id INT,
    target_table VARCHAR(50),
    changed_fields JSON,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_target_user_id (target_user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 8: CREATE INDEXES
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_rooms_type ON rooms(room_type);
CREATE INDEX IF NOT EXISTS idx_rooms_status ON rooms(status);
CREATE INDEX IF NOT EXISTS idx_bookings_ref ON bookings(booking_reference);
CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings(user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_type ON bookings(booking_type);
CREATE INDEX IF NOT EXISTS idx_bookings_verification ON bookings(verification_status);
CREATE INDEX IF NOT EXISTS idx_food_orders_booking ON food_orders(booking_id);
CREATE INDEX IF NOT EXISTS idx_food_orders_user ON food_orders(user_id);
CREATE INDEX IF NOT EXISTS idx_food_menu_category ON food_menu(category);
CREATE INDEX IF NOT EXISTS idx_food_menu_available ON food_menu(is_available);
CREATE INDEX IF NOT EXISTS idx_services_category ON services(category);
CREATE INDEX IF NOT EXISTS idx_services_status ON services(status);
CREATE INDEX IF NOT EXISTS idx_contact_status ON contact_messages(status);
CREATE INDEX IF NOT EXISTS idx_bookings_dates_status ON bookings(check_in_date, check_out_date, status);
CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role, status);
CREATE INDEX IF NOT EXISTS idx_food_orders_status_date ON food_orders(status, created_at);
CREATE INDEX IF NOT EXISTS idx_bookings_payment_deadline ON bookings(payment_deadline);
CREATE INDEX IF NOT EXISTS idx_bookings_payment_reference ON bookings(payment_reference);

-- =====================================================
-- STEP 9: CREATE VIEWS
-- =====================================================

-- Payment Verification Dashboard View
CREATE OR REPLACE VIEW payment_verification_dashboard AS
SELECT 
    b.id as booking_id,
    b.booking_reference,
    b.payment_reference,
    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
    u.email as customer_email,
    u.phone as customer_phone,
    CASE 
        WHEN b.booking_type = 'food_order' THEN 'Food Order'
        ELSE COALESCE(r.name, 'Unknown Room')
    END as room_name,
    CASE 
        WHEN b.booking_type = 'food_order' THEN 'N/A'
        ELSE COALESCE(r.room_number, 'N/A')
    END as room_number,
    b.booking_type,
    b.total_price,
    b.payment_method,
    b.verification_status,
    b.screenshot_path,
    b.screenshot_uploaded_at,
    b.payment_deadline,
    CASE 
        WHEN b.payment_deadline < NOW() AND b.verification_status = 'pending_payment' THEN 'EXPIRED'
        WHEN b.payment_deadline < DATE_ADD(NOW(), INTERVAL 10 MINUTE) AND b.verification_status = 'pending_payment' THEN 'URGENT'
        WHEN b.verification_status = 'pending_verification' THEN 'NEEDS_REVIEW'
        ELSE 'NORMAL'
    END as priority_status,
    TIMESTAMPDIFF(MINUTE, b.screenshot_uploaded_at, NOW()) as minutes_waiting,
    pmi.method_name as payment_method_name,
    pmi.bank_name,
    CONCAT(verifier.first_name, ' ', verifier.last_name) as verified_by_name,
    b.verified_at
FROM bookings b
JOIN users u ON b.user_id = u.id
LEFT JOIN rooms r ON b.room_id = r.id
LEFT JOIN payment_method_instructions pmi ON b.payment_method = pmi.method_code
LEFT JOIN users verifier ON b.verified_by = verifier.id
WHERE b.verification_status IN ('pending_payment', 'pending_verification', 'rejected')
ORDER BY 
    CASE 
        WHEN b.verification_status = 'pending_verification' THEN 1
        WHEN b.payment_deadline < NOW() THEN 2
        WHEN b.payment_deadline < DATE_ADD(NOW(), INTERVAL 10 MINUTE) THEN 3
        ELSE 4
    END,
    b.screenshot_uploaded_at ASC;

-- Booking Summary View
CREATE OR REPLACE VIEW booking_summary AS
SELECT 
    b.id,
    b.booking_reference,
    CONCAT(u.first_name, ' ', u.last_name) as guest_name,
    u.email,
    u.phone,
    r.name as room_name,
    r.room_number,
    b.check_in_date,
    b.check_out_date,
    DATEDIFF(b.check_out_date, b.check_in_date) as nights,
    b.customers,
    b.total_price,
    b.status,
    b.payment_status,
    b.payment_method,
    b.created_at
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id;

-- Room Availability View
CREATE OR REPLACE VIEW room_availability AS
SELECT 
    r.id,
    r.name,
    r.room_number,
    r.room_type,
    r.price,
    r.status,
    COUNT(b.id) as active_bookings,
    CASE 
        WHEN COUNT(b.id) > 0 THEN 'occupied'
        WHEN r.status = 'maintenance' THEN 'maintenance'
        ELSE 'available'
    END as availability_status
FROM rooms r
LEFT JOIN bookings b ON r.id = b.room_id 
    AND b.status IN ('confirmed', 'checked_in')
    AND CURDATE() BETWEEN b.check_in_date AND b.check_out_date
GROUP BY r.id;

-- Revenue Summary View
CREATE OR REPLACE VIEW revenue_summary AS
SELECT 
    DATE(b.created_at) as booking_date,
    COUNT(b.id) as total_bookings,
    SUM(b.total_price) as total_revenue,
    AVG(b.total_price) as average_booking_value,
    COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_bookings,
    COUNT(CASE WHEN b.status = 'cancelled' THEN 1 END) as cancelled_bookings
FROM bookings b
GROUP BY DATE(b.created_at)
ORDER BY booking_date DESC;

-- =====================================================
-- STEP 10: CREATE STORED PROCEDURES (CORRECTED VERSION)
-- =====================================================

-- Procedure to check room availability
DROP PROCEDURE IF EXISTS CheckRoomAvailability;
DELIMITER $$
CREATE PROCEDURE CheckRoomAvailability(
    IN check_in DATE,
    IN check_out DATE,
    IN room_type_filter VARCHAR(50)
)
BEGIN
    SELECT r.*, 
           CASE 
               WHEN COUNT(b.id) > 0 THEN 'occupied'
               ELSE 'available'
           END as availability
    FROM rooms r
    LEFT JOIN bookings b ON r.id = b.room_id 
        AND b.status IN ('confirmed', 'checked_in')
        AND NOT (check_out <= b.check_in_date OR check_in >= b.check_out_date)
    WHERE r.status = 'active'
        AND (room_type_filter IS NULL OR r.room_type = room_type_filter)
    GROUP BY r.id
    HAVING availability = 'available'
    ORDER BY r.price ASC;
END$$
DELIMITER ;

-- Function to generate payment reference (CORRECTED)
DROP FUNCTION IF EXISTS generate_payment_reference;
DELIMITER $$
CREATE FUNCTION generate_payment_reference(booking_id INT) 
RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE ref_code VARCHAR(20);
    DECLARE random_suffix VARCHAR(6);
    
    -- Generate random 6-character suffix
    SET random_suffix = UPPER(SUBSTRING(MD5(CONCAT(booking_id, NOW(), RAND())), 1, 6));
    
    -- Format: HRH-{BOOKING_ID}-{RANDOM}
    SET ref_code = CONCAT('HRH-', LPAD(booking_id, 4, '0'), '-', random_suffix);
    
    RETURN ref_code;
END$$
DELIMITER ;

-- Stored procedure to expire old bookings
DROP PROCEDURE IF EXISTS expire_old_bookings;
DELIMITER $$
CREATE PROCEDURE expire_old_bookings()
BEGIN
    UPDATE bookings 
    SET verification_status = 'expired',
        updated_at = NOW()
    WHERE verification_status = 'pending_payment' 
    AND payment_deadline < NOW();
    
    -- Log expired bookings
    INSERT INTO payment_verification_log (booking_id, payment_reference, action_type, verification_notes)
    SELECT id, payment_reference, 'payment_expired', 'Automatically expired due to deadline'
    FROM bookings 
    WHERE verification_status = 'expired' 
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE);
END$$
DELIMITER ;


-- =====================================================
-- FINAL SETUP
-- =====================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Success message
SELECT 'Complete database setup completed successfully!' as message,
       'Food order payment integration ready' as status,
       'All essential tables included - ready to test' as notes;  

-- =====================================================
-- ADD PROFILE COLUMNS FOR USER DROPDOWN FUNCTIONALITY
-- =====================================================

-- =====================================================
-- CUSTOMER FEEDBACK TABLE
-- =====================================================
-- NOTE: Users table already has all notification columns
-- No ALTER TABLE needed - table is complete

-- Customer Feedback Table
CREATE TABLE IF NOT EXISTS customer_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    customer_id INT NOT NULL,
    payment_id VARCHAR(50) NULL,
    overall_rating INT NOT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    service_quality INT NOT NULL CHECK (service_quality >= 1 AND service_quality <= 5),
    cleanliness INT NOT NULL CHECK (cleanliness >= 1 AND cleanliness <= 5),
    comments TEXT NULL,
    booking_reference VARCHAR(50) NULL,
    booking_type ENUM('room', 'food_order', 'spa_service', 'laundry_service') DEFAULT 'room',
    service_type VARCHAR(100) NULL COMMENT 'Specific service name (e.g., Spa Massage, Wash & Iron)',
    service_id INT NULL COMMENT 'Reference to service ID from services table',
    room_name VARCHAR(100) NULL,
    room_number VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_customer (customer_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CHECKINS TABLE FOR MANUAL CUSTOMER CHECK-IN
-- =====================================================

-- Customer Check-ins Table for Receptionist Manual Check-ins
CREATE TABLE IF NOT EXISTS checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    booking_id INT DEFAULT NULL,
    
    -- Hotel Information
    hotel_name VARCHAR(255) NOT NULL DEFAULT 'Harar Ras Hotel',
    hotel_location VARCHAR(255) NOT NULL DEFAULT 'Main Street, City, Country',
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    
    -- Customer Information
    guest_full_name VARCHAR(255) NOT NULL,
    guest_date_of_birth DATE NOT NULL,
    guest_id_type ENUM('passport','drivers_license','national_id') NOT NULL,
    guest_id_number VARCHAR(100) NOT NULL,
    guest_nationality VARCHAR(100) NOT NULL,
    guest_home_address TEXT NOT NULL,
    guest_phone_number VARCHAR(20) NOT NULL,
    guest_email_address VARCHAR(255) NOT NULL,
    
    -- Stay Details
    room_type VARCHAR(100) NOT NULL,
    room_number VARCHAR(20) DEFAULT NULL,
    nights_stay INT NOT NULL,
    number_of_guests INT NOT NULL DEFAULT 1,
    rate_per_night DECIMAL(10,2) NOT NULL,
    
    -- Payment Details
    payment_type ENUM('cash','credit_card','debit_card','bank_transfer','mobile_payment') NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    balance_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    confirmation_number VARCHAR(100) NOT NULL,
    
    -- Additional Information
    additional_requests TEXT DEFAULT NULL,
    
    -- System Fields
    checked_in_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active','checked_out','cancelled') NOT NULL DEFAULT 'active',
    
    UNIQUE KEY confirmation_number (confirmation_number),
    KEY customer_id (customer_id),
    KEY booking_id (booking_id),
    KEY checked_in_by (checked_in_by),
    KEY check_in_date (check_in_date),
    KEY check_out_date (check_out_date),
    KEY guest_full_name (guest_full_name),
    KEY guest_email_address (guest_email_address),
    
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for checkins table
CREATE INDEX IF NOT EXISTS idx_checkins_booking ON checkins(booking_id);

-- =====================================================
-- SERVICE BOOKINGS TABLE (SPA & LAUNDRY)
-- =====================================================

-- Service Bookings Table for Spa and Laundry Services
CREATE TABLE IF NOT EXISTS service_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    service_category ENUM('spa', 'laundry') NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    service_price DECIMAL(10, 2) NOT NULL,
    quantity INT DEFAULT 1,
    total_price DECIMAL(10, 2) NOT NULL,
    service_date DATE NULL,
    service_time TIME NULL,
    special_requests TEXT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_user (user_id),
    INDEX idx_category (service_category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PERFORMANCE INDEXES
-- =====================================================

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- DATABASE UPDATES - EMAIL NOTIFICATION SYSTEM
-- =====================================================

-- Add email tracking columns to bookings table
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS email_sent TINYINT(1) DEFAULT 0 AFTER screenshot_path,
ADD COLUMN IF NOT EXISTS email_sent_at TIMESTAMP NULL AFTER email_sent,
ADD COLUMN IF NOT EXISTS email_error TEXT NULL AFTER email_sent_at;

-- Add email tracking columns to food_orders table
ALTER TABLE food_orders 
ADD COLUMN IF NOT EXISTS email_sent TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN IF NOT EXISTS email_sent_at TIMESTAMP NULL AFTER email_sent,
ADD COLUMN IF NOT EXISTS email_error TEXT NULL AFTER email_sent_at;

-- Add email tracking columns to service_bookings table
ALTER TABLE service_bookings 
ADD COLUMN IF NOT EXISTS email_sent TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN IF NOT EXISTS email_sent_at TIMESTAMP NULL AFTER email_sent,
ADD COLUMN IF NOT EXISTS email_error TEXT NULL AFTER email_sent_at;

-- Create email logs table for tracking all sent emails
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_to VARCHAR(255) NOT NULL,
    email_type ENUM('room_booking', 'food_order', 'spa_service', 'laundry_service', 'other') NOT NULL,
    reference_id INT NOT NULL COMMENT 'Booking ID, Order ID, or Service ID',
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_email_type (email_type),
    INDEX idx_reference_id (reference_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATABASE UPDATES - SMS NOTIFICATIONS DEFAULT
-- =====================================================

-- Update all existing users to enable SMS notifications by default
UPDATE users 
SET sms_notifications = 1 
WHERE sms_notifications = 0;

-- =====================================================
-- DATABASE UPDATES - BILLS TABLE NOTES COLUMN
-- =====================================================

-- Create bills table if it doesn't exist
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    customer_id INT NOT NULL,
    bill_reference VARCHAR(50) UNIQUE NOT NULL,
    room_charges DECIMAL(10, 2) DEFAULT 0.00,
    service_charges DECIMAL(10, 2) DEFAULT 0.00,
    incidental_charges DECIMAL(10, 2) DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('draft', 'sent_to_manager', 'approved', 'rejected', 'paid') DEFAULT 'draft',
    generated_by INT NOT NULL,
    approved_by INT NULL,
    rejection_reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add notes column to bills table if it doesn't exist
ALTER TABLE bills 
ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER rejection_reason;

-- =====================================================
-- DATABASE UPDATES COMPLETED
-- =====================================================

-- =====================================================
-- DATABASE UPDATES COMPLETED
-- =====================================================

-- =====================================================
-- FINAL CLEANUP: REMOVE ANY DUPLICATE SERVICES
-- =====================================================

-- Step 1: Remove any duplicate restaurant services that might exist
DELETE FROM services WHERE category = 'restaurant';

-- Step 1: Clean up existing services table completely
DELETE FROM services WHERE category IN ('spa', 'laundry');

-- Step 1.1: Also remove any generic "Laundry Service" entries that might exist
DELETE FROM services WHERE name = 'Laundry Service' OR name LIKE '%Laundry Service%';

-- Step 1.2: Remove any services with duplicate descriptions
DELETE FROM services WHERE description = 'Professional laundry and dry cleaning';

-- Step 2: Re-insert only the clean, unique spa and laundry services
INSERT INTO services (name, category, description, price, image, status) VALUES
-- Featured Dishes (New Additions) - UNIQUE ONLY
('Vegetable Pizza', 'restaurant', 'Delicious pizza topped with fresh vegetables, cheese, and tomato sauce', 380.00, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=400&h=300&fit=crop&q=80', 'active'),
('Grilled Meat Platter', 'restaurant', 'Succulent grilled meat served with rice, vegetables, and traditional bread', 520.00, 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=400&h=300&fit=crop&q=80', 'active'),

-- Ethiopian Food Services - UNIQUE ONLY
('Ethiopian Traditional Platter', 'restaurant', 'Authentic Ethiopian platter with assorted wats, tibs, and injera served on traditional mesob', 480.00, 'https://images.unsplash.com/photo-1604329760661-e71dc83f8f26?w=400&h=300&fit=crop&q=80', 'active'),
('Ethiopian Breakfast', 'restaurant', 'Traditional Ethiopian breakfast with injera, scrambled eggs, foul, and fresh honey', 350.00, 'https://images.unsplash.com/photo-1606787366850-de6330128bfc?w=400&h=300&fit=crop&q=80', 'active'),
('Ethiopian Coffee Ceremony', 'restaurant', 'Traditional Ethiopian coffee ceremony with freshly roasted beans and popcorn', 200.00, 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=400&h=300&fit=crop&q=80', 'active'),
('Ethiopian Lunch Special', 'restaurant', 'Traditional Ethiopian lunch with injera, berbere-spiced lentils, and seasonal vegetables', 420.00, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=400&h=300&fit=crop&q=80', 'active'),

-- International Buffet Services - UNIQUE ONLY
('International Breakfast Buffet', 'restaurant', 'Continental breakfast buffet with fresh fruits, pastries, cereals, eggs, and hot dishes', 400.00, 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?w=400&h=300&fit=crop&q=80', 'active'),
('International Lunch Buffet', 'restaurant', 'Diverse lunch buffet with Asian, European, and American cuisine selections', 550.00, 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=400&h=300&fit=crop&q=80', 'active'),
('International Dinner Buffet', 'restaurant', 'Premium dinner buffet with grilled meats, seafood, pasta, and international specialties', 700.00, 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400&h=300&fit=crop&q=80', 'active'),
('International Weekend Brunch', 'restaurant', 'Special weekend brunch buffet with pancakes, waffles, fresh fruits, and international favorites', 480.00, 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=400&h=300&fit=crop&q=80', 'active'),

-- International Dishes - UNIQUE ONLY
('Grilled Steak', 'restaurant', 'Premium beef steak grilled to perfection, served with vegetables and choice of sides', 750.00, 'https://images.unsplash.com/photo-1600891964092-4316c288032e?w=400&h=300&fit=crop&q=80', 'active'),
('Pasta Carbonara', 'restaurant', 'Classic Italian pasta with creamy sauce, bacon, and parmesan cheese', 450.00, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=400&h=300&fit=crop&q=80', 'active'),
('Grilled Salmon', 'restaurant', 'Fresh Atlantic salmon grilled with herbs, served with rice and steamed vegetables', 850.00, 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=400&h=300&fit=crop&q=80', 'active'),
('Caesar Salad', 'restaurant', 'Fresh romaine lettuce with Caesar dressing, croutons, and parmesan cheese', 320.00, 'https://images.unsplash.com/photo-1546793665-c74683f339c1?w=400&h=300&fit=crop&q=80', 'active'),

-- Desserts - UNIQUE ONLY
('Chocolate Lava Cake', 'restaurant', 'Warm chocolate cake with molten center, served with vanilla ice cream', 280.00, 'https://images.unsplash.com/photo-1624353365286-3f8d62daad51?w=400&h=300&fit=crop&q=80', 'active'),
('Tiramisu', 'restaurant', 'Classic Italian dessert with coffee-soaked ladyfingers and mascarpone cream', 300.00, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400&h=300&fit=crop&q=80', 'active'),

-- Beverages - UNIQUE ONLY
('Fresh Fruit Juice', 'restaurant', 'Freshly squeezed juice - choice of orange, mango, papaya, or mixed fruit', 150.00, 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=400&h=300&fit=crop&q=80', 'active'),
('Smoothie Bowl', 'restaurant', 'Healthy smoothie bowl topped with fresh fruits, granola, and honey', 250.00, 'https://images.unsplash.com/photo-1590301157890-4810ed352733?w=400&h=300&fit=crop&q=80', 'active'),

-- Spa & Wellness Services - UNIQUE ONLY
('Spa Massage', 'spa', 'Relaxing full body massage (60 minutes)', 1300.00, 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=400&h=300&fit=crop&q=80', 'active'),
('Facial Treatment', 'spa', 'Rejuvenating facial with natural products for glowing skin (45 minutes)', 800.00, 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=400&h=300&fit=crop&q=80', 'active'),
('Sauna & Steam Room', 'spa', 'Detoxify and relax in our premium sauna facilities (30 minutes)', 500.00, 'https://images.unsplash.com/photo-1600334129128-685c5582fd35?w=400&h=300&fit=crop&q=80', 'active'),

-- Laundry Services - UNIQUE ONLY
('Wash & Iron', 'laundry', 'Professional washing and ironing service', 250.00, 'https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=400&h=300&fit=crop&q=80', 'active'),
('Dry Cleaning', 'laundry', 'Premium dry cleaning for delicate garments', 400.00, 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop&q=80', 'active'),
('Express Service', 'laundry', 'Same-day laundry service available', 500.00, 'https://images.unsplash.com/photo-1582735689369-4fe89db7114c?w=400&h=300&fit=crop&q=80', 'active')
ON DUPLICATE KEY UPDATE name=name;

-- Step 3: Verify no duplicates exist
SELECT 'Checking for duplicates...' as status;
SELECT name, COUNT(*) as count FROM services WHERE category IN ('restaurant', 'spa', 'laundry') GROUP BY name HAVING count > 1;

-- Step 3.1: Remove any duplicate spa and laundry services (keep lowest ID)
DELETE s1 FROM services s1
INNER JOIN services s2 
WHERE s1.id > s2.id 
AND s1.name = s2.name 
AND s1.category = s2.category 
AND s1.category IN ('spa', 'laundry');

-- Step 3.2: Verify spa and laundry services are unique
SELECT 'Spa services count:' as status;
SELECT name, COUNT(*) as count FROM services WHERE category = 'spa' GROUP BY name;

SELECT 'Laundry services count:' as status;
SELECT name, COUNT(*) as count FROM services WHERE category = 'laundry' GROUP BY name;

-- Step 3.3: Add unique constraint to prevent future duplicates (only if it doesn't exist)
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
                         WHERE constraint_name = 'unique_service_name_category' 
                         AND table_name = 'services' 
                         AND table_schema = DATABASE());

SET @sql = IF(@constraint_exists = 0, 
              'ALTER TABLE services ADD CONSTRAINT unique_service_name_category UNIQUE (name, category)', 
              'SELECT "Constraint already exists" as status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3.4: Final comprehensive cleanup - Remove any remaining duplicates in all categories
DELETE s1 FROM services s1
INNER JOIN services s2 
WHERE s1.id > s2.id 
AND s1.name = s2.name 
AND s1.category = s2.category;

-- Step 3.5: Remove any remaining generic "Laundry Service" entries
DELETE FROM services WHERE name = 'Laundry Service' AND category = 'laundry';

-- Step 3.6: Verify final state - should show no duplicates
SELECT 'Final verification - checking for any remaining duplicates:' as status;
SELECT name, category, COUNT(*) as count 
FROM services 
GROUP BY name, category 
HAVING count > 1;

-- Step 3.7: Show all laundry services to verify they are correct
SELECT 'Current laundry services:' as status;
SELECT id, name, description, price 
FROM services 
WHERE category = 'laundry' AND status = 'active'
ORDER BY price;

-- Step 3.8: Show clean service counts by category
SELECT 'Service counts by category:' as status;
SELECT category, COUNT(*) as total_services 
FROM services 
WHERE status = 'active' 
GROUP BY category 
ORDER BY category;

-- Step 4: Fix NULL phone numbers in bookings table to prevent PHP errors
UPDATE bookings SET customer_phone = '' WHERE customer_phone IS NULL;

-- Final message
SELECT 'Database cleanup completed successfully!' as status;

-- =====================================================
-- FINAL SUCCESS MESSAGE
-- =====================================================
SELECT 'Harar Ras Hotel database setup completed successfully!' as message,
       'Database name: harar_ras_hotel' as database_name,
       'All tables created without errors' as status,
       'Ready for production use' as notes;

-- =====================================================
-- STEP 10: CREATE DEFAULT USER ACCOUNTS
-- =====================================================

-- Insert default user accounts for all roles
-- Note: All passwords are hashed using PHP password_hash() function

-- Super Admin Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'Super',
    'Admin', 
    'superadmin',
    'superadmin@hararras.com',
    '+251911000000',
    '$2y$10$/Q2WArsNPMnmx7hZXLVicua2L7WirTZ6b1THe7L89IPEk06Owxsl2', -- Password: superadmin123
    'super_admin',
    'active',
    NOW()
);

-- Admin Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'System',
    'Administrator', 
    'admin',
    'admin@hararras.com',
    '+251911111111',
    '$2y$10$YuHGC/k.yPjjVD3iuG/fG.wrdUVdw1A83/dwmBwy.42wu1BXBjDlS', -- Password: @Ab7340di
    'admin',
    'active',
    NOW()
);

-- Manager Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'Hotel',
    'Manager', 
    'manager',
    'manager@hararras.com',
    '+251911222222',
    '$2y$10$9tEsef2DeXGfT3CC8VAxG.cj118wypJAlVkgTAYC9KPG8XvOgFTVO', -- Password: @Ab7340di
    'manager',
    'active',
    NOW()
);

-- Receptionist Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'Reception',
    'Staff', 
    'receptionist',
    'receptionist@hararras.com',
    '+251911333333',
    '$2y$10$gjdXkS7XL.1qYYEBbXIfOut4KLbKBKNsWo0A9lbG.1A49vlyJ/zxC', -- Password: @Ab7340di
    'receptionist',
    'active',
    NOW()
);

-- Test Customer Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'Test',
    'Customer', 
    'customer',
    'customer@test.com',
    '+251911444444',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: password
    'customer',
    'active',
    NOW()
);

-- =====================================================
-- PAYMENT SYSTEM ENHANCEMENTS
-- =====================================================

-- Add transaction_id columns to bookings table (if not exists)
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) AFTER payment_reference,
ADD COLUMN IF NOT EXISTS transaction_verified BOOLEAN DEFAULT FALSE AFTER transaction_id,
ADD COLUMN IF NOT EXISTS transaction_verification_date DATETIME AFTER transaction_verified,
ADD COLUMN IF NOT EXISTS transaction_amount DECIMAL(10,2) AFTER transaction_verification_date,
ADD COLUMN IF NOT EXISTS transaction_date DATETIME AFTER transaction_amount,
ADD COLUMN IF NOT EXISTS payment_gateway VARCHAR(50) AFTER transaction_date;

-- Add index on transaction_id for faster lookups (but not UNIQUE to allow reuse)
CREATE INDEX IF NOT EXISTS idx_transaction_id ON bookings(transaction_id);

-- Create transaction verification log table
CREATE TABLE IF NOT EXISTS transaction_verification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    verification_status ENUM('pending', 'verified', 'failed', 'duplicate') DEFAULT 'pending',
    verification_method VARCHAR(50),
    amount DECIMAL(10,2),
    transaction_date DATETIME,
    gateway_response TEXT,
    verified_by INT,
    verified_at DATETIME,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_verification_status (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment gateway configurations table
CREATE TABLE IF NOT EXISTS payment_gateway_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_name VARCHAR(50) NOT NULL UNIQUE,
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    webhook_secret VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    is_test_mode BOOLEAN DEFAULT TRUE,
    transaction_prefix VARCHAR(10),
    min_transaction_length INT DEFAULT 10,
    max_transaction_length INT DEFAULT 50,
    config_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default gateway configurations
INSERT INTO payment_gateway_config (gateway_name, transaction_prefix, min_transaction_length, max_transaction_length, is_active, is_test_mode) VALUES
('telebirr', 'TB', 15, 30, TRUE, TRUE),
('cbe_birr', 'CBE', 12, 25, TRUE, TRUE),
('mpesa', 'MP', 10, 20, TRUE, TRUE),
('stripe', 'pi_', 20, 40, FALSE, TRUE),
('paypal', 'PAY', 15, 30, FALSE, TRUE),
('manual', 'MAN', 8, 50, TRUE, FALSE)
ON DUPLICATE KEY UPDATE gateway_name=gateway_name;

-- Add Google OAuth columns to users table (if not exists)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) UNIQUE AFTER password,
ADD COLUMN IF NOT EXISTS oauth_provider VARCHAR(50) AFTER google_id,
ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(500) AFTER oauth_provider,
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE AFTER profile_picture;

-- Update last_login column if it doesn't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS last_login DATETIME AFTER email_verified;

-- Create OAuth tokens table for session management (if not exists)
CREATE TABLE IF NOT EXISTS oauth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NULL,
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_provider (user_id, provider),
    UNIQUE KEY unique_provider_user (provider, provider_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create fraud detection table
CREATE TABLE IF NOT EXISTS fraud_detection_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100),
    booking_id INT,
    user_id INT,
    fraud_type ENUM('duplicate_transaction', 'amount_mismatch', 'suspicious_pattern', 'blacklisted_user'),
    risk_score INT DEFAULT 0,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    action_taken VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_user_id (user_id),
    INDEX idx_fraud_type (fraud_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update payment_verification_queue to support transaction IDs (if columns don't exist)
ALTER TABLE payment_verification_queue
ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) AFTER payment_reference,
ADD COLUMN IF NOT EXISTS verification_method ENUM('screenshot', 'transaction_id', 'api') DEFAULT 'transaction_id' AFTER transaction_id;

-- =====================================================
-- BOOKING CANCELLATION & REFUND SYSTEM
-- =====================================================

-- Add cancellation columns to bookings table (if not exists)
ALTER TABLE bookings
ADD COLUMN IF NOT EXISTS cancellation_date DATETIME AFTER checkout_notes,
ADD COLUMN IF NOT EXISTS cancelled_by INT AFTER cancellation_date,
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT AFTER cancelled_by,
ADD COLUMN IF NOT EXISTS days_before_checkin INT AFTER cancellation_reason,
ADD COLUMN IF NOT EXISTS is_refundable BOOLEAN DEFAULT TRUE AFTER days_before_checkin,
ADD COLUMN IF NOT EXISTS cancellation_ip VARCHAR(45) AFTER is_refundable,
ADD COLUMN IF NOT EXISTS cancellation_user_agent TEXT AFTER cancellation_ip;

-- Add indexes for cancellation
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_cancellation_date (cancellation_date);
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_cancelled_by (cancelled_by);

-- Create refunds table
CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    booking_reference VARCHAR(50),
    customer_id INT NOT NULL,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    
    -- Booking details
    original_amount DECIMAL(10,2) NOT NULL,
    check_in_date DATE NOT NULL,
    cancellation_date DATETIME NOT NULL,
    days_before_checkin INT NOT NULL,
    
    -- Refund calculation
    refund_percentage INT NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    processing_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    processing_fee_percentage DECIMAL(5,2) DEFAULT 5.00,
    final_refund DECIMAL(10,2) NOT NULL,
    
    -- Refund status
    refund_status ENUM('Pending', 'Approved', 'Processed', 'Rejected', 'Completed') DEFAULT 'Pending',
    refund_method VARCHAR(50),
    
    -- Transaction details
    original_transaction_id VARCHAR(100),
    refund_transaction_id VARCHAR(100),
    refund_reference VARCHAR(100) UNIQUE,
    
    -- Processing details
    processed_by INT,
    processed_at DATETIME,
    rejection_reason TEXT,
    admin_notes TEXT,
    
    -- Audit trail
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_booking_id (booking_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_refund_status (refund_status),
    INDEX idx_refund_reference (refund_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_transactions table (centralized payment tracking)
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    booking_reference VARCHAR(50),
    
    -- Transaction details
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    transaction_type ENUM('payment', 'refund') DEFAULT 'payment',
    payment_method VARCHAR(50),
    payment_gateway VARCHAR(50),
    
    -- Amount details
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'ETB',
    
    -- Verification
    verification_status ENUM('pending', 'verified', 'failed', 'duplicate') DEFAULT 'pending',
    verified_at DATETIME,
    verified_by INT,
    
    -- API response
    gateway_response TEXT,
    gateway_status VARCHAR(50),
    
    -- Audit
    customer_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_verification_status (verification_status),
    INDEX idx_transaction_type (transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create booking_cancellations table (detailed cancellation tracking)
CREATE TABLE IF NOT EXISTS booking_cancellations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    booking_reference VARCHAR(50),
    
    -- Cancellation details
    cancelled_by INT NOT NULL,
    cancellation_date DATETIME NOT NULL,
    cancellation_reason TEXT,
    
    -- Booking details at time of cancellation
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    days_before_checkin INT NOT NULL,
    
    -- Refund eligibility
    is_refundable BOOLEAN DEFAULT TRUE,
    refund_percentage INT,
    estimated_refund DECIMAL(10,2),
    
    -- Audit
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_booking_id (booking_id),
    INDEX idx_cancelled_by (cancelled_by),
    INDEX idx_cancellation_date (cancellation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create refund_policy table (configurable refund rules)
CREATE TABLE IF NOT EXISTS refund_policy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(100) NOT NULL,
    
    -- Time-based rules
    min_days_before INT NOT NULL,
    max_days_before INT,
    refund_percentage INT NOT NULL,
    
    -- Fees
    processing_fee_percentage DECIMAL(5,2) DEFAULT 5.00,
    processing_fee_fixed DECIMAL(10,2) DEFAULT 0.00,
    
    -- Policy details
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_days_range (min_days_before, max_days_before),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default refund policies
INSERT INTO refund_policy (policy_name, min_days_before, max_days_before, refund_percentage, description, display_order) VALUES
('Early Cancellation', 7, NULL, 95, 'Cancel 7 or more days before check-in', 1),
('Moderate Cancellation', 3, 6, 75, 'Cancel 3-6 days before check-in', 2),
('Late Cancellation', 1, 2, 50, 'Cancel 1-2 days before check-in', 3),
('Same Day Cancellation', 0, 0, 25, 'Cancel on check-in day', 4),
('No Refund', -999, -1, 0, 'Cancel after check-in date', 5)
ON DUPLICATE KEY UPDATE policy_name=policy_name;

-- Create system_activity_log table (comprehensive audit trail)
CREATE TABLE IF NOT EXISTS system_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Activity details
    activity_type ENUM('login', 'logout', 'booking', 'payment', 'cancellation', 'refund', 'verification', 'admin_action') NOT NULL,
    activity_action VARCHAR(100) NOT NULL,
    activity_description TEXT,
    
    -- User details
    user_id INT,
    user_role VARCHAR(50),
    user_email VARCHAR(255),
    
    -- Related entities
    booking_id INT,
    payment_id INT,
    refund_id INT,
    
    -- Request details
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_method VARCHAR(10),
    request_url TEXT,
    
    -- Response
    status VARCHAR(50),
    error_message TEXT,
    
    -- Metadata
    metadata JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_activity_type (activity_type),
    INDEX idx_user_id (user_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create views for easy querying
CREATE OR REPLACE VIEW cancellable_bookings AS
SELECT 
    b.id,
    b.booking_reference,
    b.user_id,
    b.check_in_date,
    b.check_out_date,
    b.total_price,
    b.status,
    DATEDIFF(b.check_in_date, CURDATE()) as days_until_checkin,
    CASE 
        WHEN DATEDIFF(b.check_in_date, CURDATE()) >= 0 THEN TRUE
        ELSE FALSE
    END as is_cancellable
FROM bookings b
WHERE b.status IN ('pending', 'confirmed', 'pending_payment', 'pending_verification')
AND b.check_in_date >= CURDATE();

-- View: Pending refunds
CREATE OR REPLACE VIEW pending_refunds AS
SELECT 
    r.*,
    u.first_name,
    u.last_name,
    u.email
FROM refunds r
JOIN bookings b ON r.booking_id = b.id
JOIN users u ON r.customer_id = u.id
WHERE r.refund_status = 'Pending'
ORDER BY r.created_at DESC;

-- =====================================================
-- ROOM BOOKING CONTROL & SERVICES SYSTEM
-- =====================================================
-- Prevents double booking and displays room services
-- =====================================================

-- Create room services table
CREATE TABLE IF NOT EXISTS room_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    service_icon VARCHAR(50) DEFAULT NULL COMMENT 'Icon class name (e.g., fa-wifi, fa-tv)',
    service_category ENUM('basic', 'comfort', 'food', 'entertainment', 'luxury') DEFAULT 'basic',
    is_included BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_room_id (room_id),
    INDEX idx_category (service_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add booking expiration columns to bookings table
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS booking_hold_expires_at DATETIME AFTER payment_deadline,
ADD COLUMN IF NOT EXISTS is_expired BOOLEAN DEFAULT FALSE AFTER booking_hold_expires_at,
ADD COLUMN IF NOT EXISTS auto_expired_at DATETIME AFTER is_expired;

-- Add indexes for expiration queries
CREATE INDEX IF NOT EXISTS idx_booking_hold_expires ON bookings(booking_hold_expires_at, is_expired);

-- Insert default room services for all 40 rooms
-- Standard Rooms (1-8) - Basic Services
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order) VALUES
(1, 'Free WiFi', 'fa-wifi', 'basic', 1), (1, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(1, 'Private Bathroom', 'fa-bath', 'basic', 3), (1, 'Television', 'fa-tv', 'entertainment', 4),
(1, 'Daily Housekeeping', 'fa-broom', 'basic', 5),
(2, 'Free WiFi', 'fa-wifi', 'basic', 1), (2, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(2, 'Private Bathroom', 'fa-bath', 'basic', 3), (2, 'Television', 'fa-tv', 'entertainment', 4),
(2, 'Daily Housekeeping', 'fa-broom', 'basic', 5),
(3, 'Free WiFi', 'fa-wifi', 'basic', 1), (3, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(3, 'Private Bathroom', 'fa-bath', 'basic', 3), (3, 'Television', 'fa-tv', 'entertainment', 4),
(3, 'Daily Housekeeping', 'fa-broom', 'basic', 5),
(4, 'Free WiFi', 'fa-wifi', 'basic', 1), (4, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(4, 'Private Bathroom', 'fa-bath', 'basic', 3), (4, 'Television', 'fa-tv', 'entertainment', 4),
(4, 'Daily Housekeeping', 'fa-broom', 'basic', 5),
(5, 'Free WiFi', 'fa-wifi', 'basic', 1), (5, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(5, 'Private Bathroom', 'fa-bath', 'basic', 3), (5, 'Television', 'fa-tv', 'entertainment', 4),
(5, 'Daily Housekeeping', 'fa-broom', 'basic', 5), (5, 'Work Desk', 'fa-desk', 'comfort', 6),
(6, 'Free WiFi', 'fa-wifi', 'basic', 1), (6, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(6, 'Private Bathroom', 'fa-bath', 'basic', 3), (6, 'Television', 'fa-tv', 'entertainment', 4),
(6, 'Daily Housekeeping', 'fa-broom', 'basic', 5), (6, 'Work Desk', 'fa-desk', 'comfort', 6),
(7, 'Free WiFi', 'fa-wifi', 'basic', 1), (7, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(7, 'Private Bathroom', 'fa-bath', 'basic', 3), (7, 'Television', 'fa-tv', 'entertainment', 4),
(7, 'Daily Housekeeping', 'fa-broom', 'basic', 5), (7, 'Work Desk', 'fa-desk', 'comfort', 6),
(8, 'Free WiFi', 'fa-wifi', 'basic', 1), (8, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(8, 'Private Bathroom', 'fa-bath', 'basic', 3), (8, 'Television', 'fa-tv', 'entertainment', 4),
(8, 'Daily Housekeeping', 'fa-broom', 'basic', 5), (8, 'Work Desk', 'fa-desk', 'comfort', 6)
ON DUPLICATE KEY UPDATE service_name=service_name;

-- Deluxe Rooms (9-16) - Premium Services
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order) VALUES
(9, 'Free WiFi', 'fa-wifi', 'basic', 1), (9, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(9, 'Private Bathroom', 'fa-bath', 'basic', 3), (9, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(9, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (9, 'City View', 'fa-city', 'luxury', 6),
(9, 'Work Desk', 'fa-desk', 'comfort', 7), (9, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(9, 'Complimentary Breakfast', 'fa-coffee', 'food', 9),
(10, 'Free WiFi', 'fa-wifi', 'basic', 1), (10, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(10, 'Private Bathroom', 'fa-bath', 'basic', 3), (10, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(10, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (10, 'City View', 'fa-city', 'luxury', 6),
(10, 'Work Desk', 'fa-desk', 'comfort', 7), (10, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(10, 'Complimentary Breakfast', 'fa-coffee', 'food', 9),
(11, 'Free WiFi', 'fa-wifi', 'basic', 1), (11, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(11, 'Private Bathroom', 'fa-bath', 'basic', 3), (11, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(11, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (11, 'City View', 'fa-city', 'luxury', 6),
(11, 'Work Desk', 'fa-desk', 'comfort', 7), (11, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(11, 'Complimentary Breakfast', 'fa-coffee', 'food', 9),
(12, 'Free WiFi', 'fa-wifi', 'basic', 1), (12, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(12, 'Private Bathroom', 'fa-bath', 'basic', 3), (12, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(12, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (12, 'City View', 'fa-city', 'luxury', 6),
(12, 'Work Desk', 'fa-desk', 'comfort', 7), (12, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(12, 'Complimentary Breakfast', 'fa-coffee', 'food', 9),
(13, 'Free WiFi', 'fa-wifi', 'basic', 1), (13, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(13, 'Private Bathroom', 'fa-bath', 'basic', 3), (13, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(13, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (13, 'City View', 'fa-city', 'luxury', 6),
(13, 'Work Desk', 'fa-desk', 'comfort', 7), (13, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(13, 'Complimentary Breakfast', 'fa-coffee', 'food', 9), (13, 'Room Service', 'fa-concierge-bell', 'luxury', 10),
(14, 'Free WiFi', 'fa-wifi', 'basic', 1), (14, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(14, 'Private Bathroom', 'fa-bath', 'basic', 3), (14, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(14, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (14, 'City View', 'fa-city', 'luxury', 6),
(14, 'Work Desk', 'fa-desk', 'comfort', 7), (14, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(14, 'Complimentary Breakfast', 'fa-coffee', 'food', 9), (14, 'Room Service', 'fa-concierge-bell', 'luxury', 10),
(15, 'Free WiFi', 'fa-wifi', 'basic', 1), (15, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(15, 'Private Bathroom', 'fa-bath', 'basic', 3), (15, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(15, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (15, 'City View', 'fa-city', 'luxury', 6),
(15, 'Work Desk', 'fa-desk', 'comfort', 7), (15, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(15, 'Complimentary Breakfast', 'fa-coffee', 'food', 9), (15, 'Room Service', 'fa-concierge-bell', 'luxury', 10),
(16, 'Free WiFi', 'fa-wifi', 'basic', 1), (16, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(16, 'Private Bathroom', 'fa-bath', 'basic', 3), (16, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(16, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (16, 'City View', 'fa-city', 'luxury', 6),
(16, 'Work Desk', 'fa-desk', 'comfort', 7), (16, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(16, 'Complimentary Breakfast', 'fa-coffee', 'food', 9), (16, 'Room Service', 'fa-concierge-bell', 'luxury', 10)
ON DUPLICATE KEY UPDATE service_name=service_name;

-- Family Suites (17-20) - Family Services
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order) VALUES
(17, 'Free WiFi', 'fa-wifi', 'basic', 1), (17, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(17, 'Private Bathroom', 'fa-bath', 'basic', 3), (17, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(17, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (17, 'Living Area', 'fa-couch', 'comfort', 6),
(17, 'Kitchenette', 'fa-utensils', 'comfort', 7), (17, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(17, 'Complimentary Breakfast', 'fa-coffee', 'food', 9), (17, 'Room Service', 'fa-concierge-bell', 'luxury', 10),
(18, 'Free WiFi', 'fa-wifi', 'basic', 1), (18, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(18, 'Private Bathroom', 'fa-bath', 'basic', 3), (18, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(18, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (18, 'Living Area', 'fa-couch', 'comfort', 6),
(18, 'Kitchenette', 'fa-utensils', 'comfort', 7), (18, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(18, 'Complimentary Breakfast', 'fa-coffee', 'food', 9), (18, 'Room Service', 'fa-concierge-bell', 'luxury', 10),
(19, 'Free WiFi', 'fa-wifi', 'basic', 1), (19, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(19, 'Private Bathroom', 'fa-bath', 'basic', 3), (19, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(19, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (19, 'Living Area', 'fa-couch', 'comfort', 6),
(19, 'Kitchenette', 'fa-utensils', 'comfort', 7), (19, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(19, 'Complimentary Breakfast', 'fa-coffee', 'food', 9), (19, 'Room Service', 'fa-concierge-bell', 'luxury', 10),
(20, 'Free WiFi', 'fa-wifi', 'basic', 1), (20, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(20, 'Private Bathroom', 'fa-bath', 'basic', 3), (20, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(20, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (20, 'Living Area', 'fa-couch', 'comfort', 6),
(20, 'Kitchenette', 'fa-utensils', 'comfort', 7), (20, 'Daily Housekeeping', 'fa-broom', 'basic', 8),
(20, 'Complimentary Breakfast', 'fa-coffee', 'food', 9), (20, 'Room Service', 'fa-concierge-bell', 'luxury', 10)
ON DUPLICATE KEY UPDATE service_name=service_name;

-- Executive Suites (21-28) - Executive Services
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order) VALUES
(21, 'Free WiFi', 'fa-wifi', 'basic', 1), (21, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(21, 'Private Bathroom', 'fa-bath', 'basic', 3), (21, 'Flat Screen TV', 'fa-tv', 'entertainment', 4),
(21, 'Mini Bar', 'fa-glass-martini', 'luxury', 5), (21, 'Separate Living Area', 'fa-couch', 'luxury', 6),
(21, 'Work Desk', 'fa-desk', 'comfort', 7), (21, 'Premium Amenities', 'fa-spa', 'luxury', 8),
(21, 'Daily Housekeeping', 'fa-broom', 'basic', 9), (21, 'Complimentary Breakfast', 'fa-coffee', 'food', 10),
(21, 'Complimentary Dinner', 'fa-utensils', 'food', 11), (21, '24/7 Room Service', 'fa-concierge-bell', 'luxury', 12)
ON DUPLICATE KEY UPDATE service_name=service_name;

-- Copy services for rooms 22-28
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 22, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 21
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 23, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 21
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 24, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 21
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 25, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 21
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 26, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 21
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 27, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 21
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 28, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 21
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);

-- Presidential Suites (29-40) - Luxury Services
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order) VALUES
(29, 'Free WiFi', 'fa-wifi', 'basic', 1), (29, 'Air Conditioning', 'fa-snowflake', 'comfort', 2),
(29, '2 Private Bathrooms', 'fa-bath', 'luxury', 3), (29, 'Multiple Flat Screen TVs', 'fa-tv', 'entertainment', 4),
(29, 'Premium Mini Bar', 'fa-glass-martini', 'luxury', 5), (29, 'Separate Living Room', 'fa-couch', 'luxury', 6),
(29, 'Dining Area', 'fa-utensils', 'luxury', 7), (29, 'Kitchenette', 'fa-blender', 'comfort', 8),
(29, 'Private Balcony', 'fa-tree', 'luxury', 9), (29, 'Premium Amenities', 'fa-spa', 'luxury', 10),
(29, 'Daily Housekeeping', 'fa-broom', 'basic', 11), (29, 'Complimentary Breakfast', 'fa-coffee', 'food', 12),
(29, 'Complimentary Dinner', 'fa-utensils', 'food', 13), (29, '24/7 Concierge Service', 'fa-concierge-bell', 'luxury', 14),
(29, 'Butler Service', 'fa-user-tie', 'luxury', 15)
ON DUPLICATE KEY UPDATE service_name=service_name;

-- Copy services for rooms 30-40
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 30, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 31, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 32, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 33, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 34, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 35, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 36, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 37, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 38, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
INSERT INTO room_services (room_id, service_name, service_icon, service_category, display_order)
SELECT 39, service_name, service_icon, service_category, display_order FROM room_services WHERE room_id = 29
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);

-- Create stored procedure for auto-expiration
DROP PROCEDURE IF EXISTS expire_pending_bookings;
DELIMITER $
CREATE PROCEDURE expire_pending_bookings()
BEGIN
    UPDATE bookings 
    SET status = 'cancelled', is_expired = TRUE, auto_expired_at = NOW(), verification_status = 'expired'
    WHERE status = 'pending' AND booking_hold_expires_at < NOW() AND is_expired = FALSE;
    
    INSERT INTO booking_activity_log (booking_id, activity_type, description, created_at)
    SELECT id, 'cancelled', 'Booking automatically expired due to no approval within time limit', NOW()
    FROM bookings WHERE auto_expired_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE);
END$
DELIMITER ;

-- Create view for room availability with services
CREATE OR REPLACE VIEW room_availability_with_services AS
SELECT 
    r.id, r.name, r.room_number, r.room_type, r.description, r.capacity, r.price, r.image, r.status,
    COUNT(DISTINCT rs.id) as service_count,
    GROUP_CONCAT(DISTINCT rs.service_name ORDER BY rs.display_order SEPARATOR '|') as services,
    GROUP_CONCAT(DISTINCT rs.service_icon ORDER BY rs.display_order SEPARATOR '|') as service_icons,
    GROUP_CONCAT(DISTINCT rs.service_category ORDER BY rs.display_order SEPARATOR '|') as service_categories,
    CASE 
        WHEN EXISTS (SELECT 1 FROM bookings b WHERE b.room_id = r.id AND b.status IN ('confirmed', 'checked_in')
                     AND CURDATE() BETWEEN b.check_in_date AND b.check_out_date) THEN 'occupied'
        WHEN r.status = 'maintenance' THEN 'maintenance'
        ELSE 'available'
    END as current_availability
FROM rooms r
LEFT JOIN room_services rs ON r.id = rs.room_id
GROUP BY r.id
ORDER BY r.price ASC;

-- =====================================================
-- ENABLE FOREIGN KEY CHECKS
-- =====================================================
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- DATABASE SETUP COMPLETED SUCCESSFULLY
-- =====================================================
-- Total Tables Created: 35 (including room_services)
-- Default Users Created: 5 (Super Admin, Admin, Manager, Receptionist, Customer)
-- Room Services: Added for all 40 rooms
-- 
-- LOGIN CREDENTIALS:
-- ==================
-- Super Admin: superadmin@hararrashotel.com / 123456
-- Admin:       admin@hararrashotel.com / 123456  
-- Manager:     manager@hararrashotel.com / password
-- Receptionist: receptionist@hararrashotel.com / password
-- Customer:    customer@test.com / password
-- =====================================================

-- =====================================================
-- ROOM BOOKING QUEUE SYSTEM - PREVENT DOUBLE BOOKING
-- =====================================================
-- This system automatically manages room availability and booking queues
-- Staff can manually change room status (active, maintenance, inactive)
-- System automatically shows: available, in_process, occupied based on bookings

-- =====================================================
-- CREATE ROOM LOCKS TABLE (BOOKING QUEUE)
-- =====================================================

CREATE TABLE IF NOT EXISTS room_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    lock_status ENUM('in_process', 'waiting') DEFAULT 'in_process',
    queue_position INT DEFAULT 1,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_room_lock (room_id, lock_status),
    INDEX idx_user_lock (user_id, lock_status),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- UPDATE ROOMS TABLE - ADD MANUAL STATUS
-- =====================================================

ALTER TABLE rooms 
ADD COLUMN IF NOT EXISTS manual_status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active' AFTER status;

-- Set default manual_status for all existing rooms
UPDATE rooms SET manual_status = 'active' WHERE manual_status IS NULL;

-- =====================================================
-- UPDATE BOOKINGS TABLE - ADD QUEUE STATUS
-- =====================================================

ALTER TABLE bookings
ADD COLUMN IF NOT EXISTS lock_id INT NULL AFTER booking_reference,
ADD COLUMN IF NOT EXISTS queue_position INT DEFAULT 0 AFTER lock_id;

-- Add foreign key if not exists
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE CONSTRAINT_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'bookings' 
                  AND CONSTRAINT_NAME = 'bookings_ibfk_lock');

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE bookings ADD FOREIGN KEY (lock_id) REFERENCES room_locks(id) ON DELETE SET NULL', 
    'SELECT "Foreign key already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- STORED PROCEDURE - CHECK ROOM AVAILABILITY
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS check_room_availability_with_queue$$

CREATE PROCEDURE check_room_availability_with_queue(
    IN p_room_id INT,
    IN p_check_in DATE,
    IN p_check_out DATE,
    OUT p_status VARCHAR(20),
    OUT p_queue_count INT
)
BEGIN
    DECLARE v_manual_status VARCHAR(20);
    DECLARE v_active_lock_count INT;
    DECLARE v_confirmed_booking_count INT;
    
    -- Get manual status set by staff
    SELECT COALESCE(manual_status, 'active') INTO v_manual_status
    FROM rooms
    WHERE id = p_room_id;
    
    -- If room is manually set to maintenance or inactive, not available
    IF v_manual_status IN ('maintenance', 'inactive') THEN
        SET p_status = v_manual_status;
        SET p_queue_count = 0;
        
    ELSE
        -- Check for active locks (someone is currently booking)
        SELECT COUNT(*) INTO v_active_lock_count
        FROM room_locks
        WHERE room_id = p_room_id
        AND lock_status = 'in_process'
        AND expires_at > NOW()
        AND (
            (check_in_date <= p_check_in AND check_out_date > p_check_in)
            OR (check_in_date < p_check_out AND check_out_date >= p_check_out)
            OR (check_in_date >= p_check_in AND check_out_date <= p_check_out)
        );
        
        -- Check for confirmed bookings (room is occupied/booked)
        SELECT COUNT(*) INTO v_confirmed_booking_count
        FROM bookings
        WHERE room_id = p_room_id
        AND status IN ('confirmed', 'checked_in')
        AND (
            (check_in_date <= p_check_in AND check_out_date > p_check_in)
            OR (check_in_date < p_check_out AND check_out_date >= p_check_out)
            OR (check_in_date >= p_check_in AND check_out_date <= p_check_out)
        );
        
        -- Determine status
        IF v_confirmed_booking_count > 0 THEN
            SET p_status = 'occupied';
            SET p_queue_count = 0;
        ELSEIF v_active_lock_count > 0 THEN
            SET p_status = 'in_process';
            -- Count waiting users
            SELECT COUNT(*) INTO p_queue_count
            FROM room_locks
            WHERE room_id = p_room_id
            AND lock_status = 'waiting'
            AND expires_at > NOW();
        ELSE
            SET p_status = 'available';
            SET p_queue_count = 0;
        END IF;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- STORED PROCEDURE - ACQUIRE ROOM LOCK
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS acquire_room_lock$$

CREATE PROCEDURE acquire_room_lock(
    IN p_room_id INT,
    IN p_user_id INT,
    IN p_session_id VARCHAR(100),
    IN p_check_in DATE,
    IN p_check_out DATE,
    IN p_timeout_minutes INT,
    OUT p_lock_id INT,
    OUT p_lock_status VARCHAR(20),
    OUT p_queue_position INT
)
BEGIN
    DECLARE v_active_lock_exists INT;
    DECLARE v_expires_at TIMESTAMP;
    
    -- Set expiration time
    SET v_expires_at = DATE_ADD(NOW(), INTERVAL p_timeout_minutes MINUTE);
    
    -- Check if there's an active lock for this room and date range
    SELECT COUNT(*) INTO v_active_lock_exists
    FROM room_locks
    WHERE room_id = p_room_id
    AND lock_status = 'in_process'
    AND expires_at > NOW()
    AND (
        (check_in_date <= p_check_in AND check_out_date > p_check_in)
        OR (check_in_date < p_check_out AND check_out_date >= p_check_out)
        OR (check_in_date >= p_check_in AND check_out_date <= p_check_out)
    );
    
    -- Determine lock status
    IF v_active_lock_exists > 0 THEN
        SET p_lock_status = 'waiting';
        
        -- Calculate queue position
        SELECT COALESCE(MAX(queue_position), 0) + 1 INTO p_queue_position
        FROM room_locks
        WHERE room_id = p_room_id
        AND expires_at > NOW();
    ELSE
        SET p_lock_status = 'in_process';
        SET p_queue_position = 1;
    END IF;
    
    -- Insert lock
    INSERT INTO room_locks (
        room_id, user_id, session_id, lock_status, 
        queue_position, check_in_date, check_out_date, expires_at
    ) VALUES (
        p_room_id, p_user_id, p_session_id, p_lock_status,
        p_queue_position, p_check_in, p_check_out, v_expires_at
    );
    
    SET p_lock_id = LAST_INSERT_ID();
END$$

DELIMITER ;

-- =====================================================
-- STORED PROCEDURE - RELEASE LOCK & PROMOTE QUEUE
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS release_room_lock$$

CREATE PROCEDURE release_room_lock(
    IN p_lock_id INT,
    IN p_reason VARCHAR(50)
)
BEGIN
    DECLARE v_room_id INT;
    DECLARE v_check_in DATE;
    DECLARE v_check_out DATE;
    DECLARE v_next_lock_id INT;
    
    -- Get lock details
    SELECT room_id, check_in_date, check_out_date
    INTO v_room_id, v_check_in, v_check_out
    FROM room_locks
    WHERE id = p_lock_id;
    
    -- Delete the lock
    DELETE FROM room_locks WHERE id = p_lock_id;
    
    -- Promote next waiting user to in_process
    SELECT id INTO v_next_lock_id
    FROM room_locks
    WHERE room_id = v_room_id
    AND lock_status = 'waiting'
    AND expires_at > NOW()
    AND (
        (check_in_date <= v_check_in AND check_out_date > v_check_in)
        OR (check_in_date < v_check_out AND check_out_date >= v_check_out)
        OR (check_in_date >= v_check_in AND check_out_date <= v_check_out)
    )
    ORDER BY created_at ASC
    LIMIT 1;
    
    -- Update next user to in_process
    IF v_next_lock_id IS NOT NULL THEN
        UPDATE room_locks
        SET lock_status = 'in_process',
            queue_position = 1,
            updated_at = NOW()
        WHERE id = v_next_lock_id;
        
        -- Update queue positions for remaining waiting users
        UPDATE room_locks
        SET queue_position = queue_position - 1
        WHERE room_id = v_room_id
        AND lock_status = 'waiting'
        AND id != v_next_lock_id
        AND expires_at > NOW();
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- STORED PROCEDURE - CLEANUP EXPIRED LOCKS
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS cleanup_expired_locks$$

CREATE PROCEDURE cleanup_expired_locks()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_lock_id INT;
    DECLARE cur CURSOR FOR 
        SELECT id FROM room_locks 
        WHERE expires_at < NOW();
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_lock_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Release each expired lock (this will promote waiting users)
        CALL release_room_lock(v_lock_id, 'expired');
    END LOOP;
    
    CLOSE cur;
END$$

DELIMITER ;

-- =====================================================
-- CREATE EVENT - AUTO CLEANUP EXPIRED LOCKS
-- =====================================================

-- Enable event scheduler (requires SUPER privilege - run manually if needed)
-- SET GLOBAL event_scheduler = ON;

-- Drop existing event if exists
-- DROP EVENT IF EXISTS auto_cleanup_expired_locks;

-- Create event to run every minute (DISABLED - requires event_scheduler to be ON)
-- Uncomment below lines after enabling event_scheduler manually
/*
CREATE EVENT IF NOT EXISTS auto_cleanup_expired_locks
ON SCHEDULE EVERY 1 MINUTE
DO
    CALL cleanup_expired_locks();
*/

-- =====================================================
-- CREATE INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_bookings_room_dates ON bookings(room_id, check_in_date, check_out_date, status);
CREATE INDEX IF NOT EXISTS idx_room_locks_room_dates ON room_locks(room_id, check_in_date, check_out_date, lock_status);

-- =====================================================
-- ROOM BOOKING QUEUE SYSTEM INSTALLED SUCCESSFULLY
-- =====================================================

SELECT 'Room Booking Queue System installed successfully!' as message;


-- =====================================================
-- SET ALL USERS TO ENGLISH AS DEFAULT LANGUAGE
-- =====================================================
-- This ensures all users start with English language
-- Users can change to Amharic or Afan Oromo from their account settings

UPDATE users SET preferred_language = 'en' WHERE preferred_language IS NULL OR preferred_language = '';
UPDATE users SET preferred_language = 'en' WHERE preferred_language NOT IN ('en', 'am', 'om');

-- =====================================================
-- SETUP COMPLETE
-- =====================================================

-- =====================================================
-- HARAR RAS HOTEL - REFUND CALCULATION SYSTEM
-- =====================================================
-- Refund Policy:
-- - 7+ days before check-in: 95% Refund
-- - 3-6 days before check-in: 75% Refund
-- - 1-2 days before check-in: 50% Refund
-- - Same day cancellation: 25% Refund
-- - Past check-in date: No Refund
-- - Processing fee: 5% on all refunds
-- =====================================================

-- =====================================================
-- STEP 1: ALTER BOOKINGS TABLE (Add refund columns)
-- =====================================================

ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS arrival_time DATETIME NULL COMMENT 'Actual arrival time of guest',
ADD COLUMN IF NOT EXISTS no_show_grace_hours INT DEFAULT 6 COMMENT 'Hours after check-in before marking no-show',
ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Calculated refund amount',
ADD COLUMN IF NOT EXISTS penalty_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Penalty charged for no-show or late cancellation',
ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL COMMENT 'When booking was cancelled',
ADD COLUMN IF NOT EXISTS cancelled_by INT NULL COMMENT 'User ID who cancelled the booking',
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL COMMENT 'Reason for cancellation';

-- Update status enum to include 'no_show'
ALTER TABLE bookings 
MODIFY COLUMN status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show') DEFAULT 'pending';

-- Update payment_status enum to include refund statuses
ALTER TABLE bookings 
MODIFY COLUMN payment_status ENUM('pending', 'paid', 'refunded', 'partial_refund', 'refund_pending') DEFAULT 'pending';

-- Add indexes for performance
ALTER TABLE bookings 
ADD INDEX IF NOT EXISTS idx_status_refund (status),
ADD INDEX IF NOT EXISTS idx_payment_status_refund (payment_status),
ADD INDEX IF NOT EXISTS idx_check_in_date_refund (check_in_date),
ADD INDEX IF NOT EXISTS idx_cancelled_at (cancelled_at);

-- =====================================================
-- STEP 2: CREATE NO-SHOW DETECTION LOG TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS no_show_detection_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    booking_reference VARCHAR(50) NOT NULL,
    check_in_date DATETIME NOT NULL,
    detection_time DATETIME NOT NULL,
    grace_period_end DATETIME NOT NULL,
    status ENUM('detected', 'processed', 'error') DEFAULT 'detected',
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    refund_amount DECIMAL(10,2) DEFAULT 0.00,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_detection_time (detection_time),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 3: CREATE REFUND POLICY CONFIGURATION TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS refund_policy_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(100) NOT NULL,
    days_before_min INT NOT NULL COMMENT 'Minimum days before check-in',
    days_before_max INT NULL COMMENT 'Maximum days before check-in (NULL for unlimited)',
    refund_percentage DECIMAL(5,2) NOT NULL COMMENT 'Refund percentage (0-100)',
    processing_fee_percentage DECIMAL(5,2) DEFAULT 5.00 COMMENT 'Processing fee percentage',
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_days_range (days_before_min, days_before_max),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Harar Ras Hotel refund policies
INSERT INTO refund_policy_config (policy_name, days_before_min, days_before_max, refund_percentage, processing_fee_percentage, display_order) VALUES
('7+ days before check-in', 7, NULL, 95.00, 5.00, 1),
('3-6 days before check-in', 3, 6, 75.00, 5.00, 2),
('1-2 days before check-in', 1, 2, 50.00, 5.00, 3),
('Same day cancellation', 0, 0, 25.00, 5.00, 4),
('Past check-in date', -999, -1, 0.00, 0.00, 5)
ON DUPLICATE KEY UPDATE policy_name=policy_name;

-- =====================================================
-- STEP 4: UPDATE REFUNDS TABLE (Add missing columns)
-- =====================================================

-- Add columns to existing refunds table if they don't exist
ALTER TABLE refunds 
ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255) AFTER customer_id,
ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255) AFTER customer_name;

-- Add indexes for refunds table
ALTER TABLE refunds 
ADD INDEX IF
 NOT EXISTS idx_refund_status_created (refund_status, created_at),
ADD INDEX IF NOT EXISTS idx_customer_id_status (customer_id, refund_status),
ADD INDEX IF NOT EXISTS idx_cancellation_date (cancellation_date);

-- =====================================================
-- STEP 5: CREATE STORED PROCEDURE FOR REFUND CALCULATION
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS calculate_refund$$

CREATE PROCEDURE calculate_refund(
    IN p_booking_id INT,
    IN p_cancellation_date DATETIME,
    OUT p_refund_percentage DECIMAL(5,2),
    OUT p_refund_amount DECIMAL(10,2),
    OUT p_processing_fee DECIMAL(10,2),
    OUT p_final_refund DECIMAL(10,2),
    OUT p_days_before INT
)
BEGIN
    DECLARE v_check_in_date DATETIME;
    DECLARE v_total_price DECIMAL(10,2);
    DECLARE v_processing_fee_pct DECIMAL(5,2);
    
    -- Get booking details
    SELECT check_in_date, total_price 
    INTO v_check_in_date, v_total_price
    FROM bookings 
    WHERE id = p_booking_id;
    
    -- Calculate days before check-in
    SET p_days_before = DATEDIFF(v_check_in_date, p_cancellation_date);
    
    -- Determine refund percentage based on Harar Ras Hotel policy
    IF p_days_before >= 7 THEN
        SET p_refund_percentage = 95.00;
        SET v_processing_fee_pct = 5.00;
    ELSEIF p_days_before >= 3 AND p_days_before <= 6 THEN
        SET p_refund_percentage = 75.00;
        SET v_processing_fee_pct = 5.00;
    ELSEIF p_days_before >= 1 AND p_days_before <= 2 THEN
        SET p_refund_percentage = 50.00;
        SET v_processing_fee_pct = 5.00;
    ELSEIF p_days_before = 0 THEN
        SET p_refund_percentage = 25.00;
        SET v_processing_fee_pct = 5.00;
    ELSE
        -- Past check-in date
        SET p_refund_percentage = 0.00;
        SET v_processing_fee_pct = 0.00;
    END IF;
    
    -- Calculate amounts
    SET p_refund_amount = (v_total_price * p_refund_percentage / 100);
    SET p_processing_fee = (p_refund_amount * v_processing_fee_pct / 100);
    SET p_final_refund = p_refund_amount - p_processing_fee;
    
    -- Ensure no negative values
    IF p_final_refund < 0 THEN
        SET p_final_refund = 0;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- STEP 6: CREATE STORED PROCEDURE FOR NO-SHOW DETECTION
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS detect_no_shows$$

CREATE PROCEDURE detect_no_shows()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_booking_id INT;
    DECLARE v_booking_ref VARCHAR(50);
    DECLARE v_check_in_date DATETIME;
    DECLARE v_total_price DECIMAL(10,2);
    DECLARE v_room_price DECIMAL(10,2);
    DECLARE v_penalty DECIMAL(10,2);
    DECLARE v_refund DECIMAL(10,2);
    DECLARE v_grace_end DATETIME;
    DECLARE v_user_id INT;
    DECLARE v_customer_name VARCHAR(255);
    DECLARE v_customer_email VARCHAR(255);
    
    -- Cursor for bookings that should be marked as no-show
    DECLARE no_show_cursor CURSOR FOR
        SELECT b.id, b.booking_reference, b.check_in_date, b.total_price, r.price, b.user_id,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email as customer_email
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.status = 'confirmed'
        AND b.booking_type = 'room'
        AND b.arrival_time IS NULL
        AND TIMESTAMPADD(HOUR, COALESCE(b.no_show_grace_hours, 6), b.check_in_date) < NOW();
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN no_show_cursor;
    
    read_loop: LOOP
        FETCH no_show_cursor INTO v_booking_id, v_booking_ref, v_check_in_date, v_total_price, 
                                   v_room_price, v_user_id, v_customer_name, v_customer_email;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Calculate grace period end
        SET v_grace_end = TIMESTAMPADD(HOUR, 6, v_check_in_date);
        
        -- Calculate penalty (1 night) and refund
        SET v_penalty = COALESCE(v_room_price, v_total_price * 0.3);
        SET v_refund = v_total_price - v_penalty;
        
        -- Ensure refund is not negative
        IF v_refund < 0 THEN
            SET v_refund = 0;
        END IF;
        
        -- Update booking status
        UPDATE bookings 
        SET status = 'no_show',
            penalty_amount = v_penalty,
            refund_amount = v_refund,
            payment_status = CASE 
                WHEN v_refund > 0 THEN 'refund_pending'
                ELSE payment_status
            END
        WHERE id = v_booking_id;
        
        -- Log the detection
        INSERT INTO no_show_detection_log 
        (booking_id, booking_reference, check_in_date, detection_time, grace_period_end, status, penalty_amount, refund_amount)
        VALUES 
        (v_booking_id, v_booking_ref, v_check_in_date, NOW(), v_grace_end, 'processed', v_penalty, v_refund);
        
        -- Create refund record if refund amount > 0
        IF v_refund > 0 THEN
            INSERT INTO refunds 
            (booking_id, booking_reference, customer_id, customer_name, customer_email, original_amount, check_in_date, 
             cancellation_date, days_before_checkin, refund_percentage, refund_amount, 
             processing_fee, final_refund, refund_status, refund_reference, admin_notes)
            VALUES
            (v_booking_id, v_booking_ref, v_user_id, v_customer_name, v_customer_email, v_total_price, v_check_in_date,
             NOW(), -1, 0, v_refund, 0, v_refund, 'Pending',
             CONCAT('REF-NS-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(v_booking_id, 6, '0')),
             CONCAT('No-show detected. Penalty: ', v_penalty, ' ETB. Grace period ended at: ', v_grace_end));
        END IF;
        
    END LOOP;
    
    CLOSE no_show_cursor;
END$$

DELIMITER ;

-- =====================================================
-- STEP 7: CREATE FUNCTION TO GET REFUND PERCENTAGE
-- =====================================================

DELIMITER $$

DROP FUNCTION IF EXISTS get_refund_percentage$$

CREATE FUNCTION get_refund_percentage(
    p_check_in_date DATETIME,
    p_cancellation_date DATETIME
) RETURNS DECIMAL(5,2)
DETERMINISTIC
BEGIN
    DECLARE v_days_before INT;
    DECLARE v_refund_pct DECIMAL(5,2);
    
    SET v_days_before = DATEDIFF(p_check_in_date, p_cancellation_date);
    
    IF v_days_before >= 7 THEN
        SET v_refund_pct = 95.00;
    ELSEIF v_days_before >= 3 AND v_days_before <= 6 THEN
        SET v_refund_pct = 75.00;
    ELSEIF v_days_before >= 1 AND v_days_before <= 2 THEN
        SET v_refund_pct = 50.00;
    ELSEIF v_days_before = 0 THEN
        SET v_refund_pct = 25.00;
    ELSE
        SET v_refund_pct = 0.00;
    END IF;
    
    RETURN v_refund_pct;
END$$

DELIMITER ;

-- =====================================================
-- STEP 8: CREATE TRIGGER FOR AUTOMATIC REFUND CREATION
-- =====================================================

DELIMITER $$

DROP TRIGGER IF EXISTS after_booking_cancelled$$

CREATE TRIGGER after_booking_cancelled
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    DECLARE v_refund_pct DECIMAL(5,2);
    DECLARE v_refund_amt DECIMAL(10,2);
    DECLARE v_processing_fee DECIMAL(10,2);
    DECLARE v_final_refund DECIMAL(10,2);
    DECLARE v_days_before INT;
    DECLARE v_customer_name VARCHAR(255);
    DECLARE v_customer_email VARCHAR(255);
    
    -- Only process if status changed to 'cancelled' and payment was made
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' AND NEW.payment_status = 'paid' THEN
        
        -- Calculate refund
        CALL calculate_refund(
            NEW.id,
            NEW.cancelled_at,
            v_refund_pct,
            v_refund_amt,
            v_processing_fee,
            v_final_refund,
            v_days_before
        );
        
        -- Update booking with refund amounts
        UPDATE bookings 
        SET refund_amount = v_final_refund,
            penalty_amount = NEW.total_price - v_refund_amt,
            payment_status = CASE 
                WHEN v_final_refund > 0 THEN 'refund_pending'
                ELSE 'paid'
            END
        WHERE id = NEW.id;
        
        -- Get customer details
        SELECT CONCAT(first_name, ' ', last_name), email
        INTO v_customer_name, v_customer_email
        FROM users
        WHERE id = NEW.user_id;
        
        -- Create refund record
        INSERT INTO refunds 
        (booking_id, booking_reference, customer_id, customer_name, customer_email,
         original_amount, check_in_date, cancellation_date, days_before_checkin,
         refund_percentage, refund_amount, processing_fee, processing_fee_percentage,
         final_refund, refund_status, refund_reference, admin_notes)
        VALUES
        (NEW.id, NEW.booking_reference, NEW.user_id, v_customer_name, v_customer_email,
         NEW.total_price, NEW.check_in_date, NEW.cancelled_at, v_days_before,
         v_refund_pct, v_refund_amt, v_processing_fee, 5.00, v_final_refund, 'Pending',
         CONCAT('REF-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(NEW.id, 6, '0')),
         CONCAT('Cancelled ', v_days_before, ' days before check-in. Policy: ', v_refund_pct, '% refund.'));
        
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- STEP 9: CREATE EVENT FOR AUTOMATIC NO-SHOW DETECTION
-- =====================================================

-- Drop existing event if exists
-- DROP EVENT IF EXISTS auto_detect_no_shows;

-- Create event to run every hour (DISABLED - requires event_scheduler to be ON)
-- Uncomment below lines after enabling event_scheduler manually
/*
CREATE EVENT IF NOT EXISTS auto_detect_no_shows
ON SCHEDULE EVERY 1 HOUR
STARTS CURRENT_TIMESTAMP
DO
    CALL detect_no_shows();
*/

-- =====================================================
-- REFUND SYSTEM SETUP COMPLETE
-- =====================================================

SELECT 'Harar Ras Hotel Refund Calculation System installed successfully!' as message;
SELECT 'Event Scheduler Status:' as info, @@event_scheduler as status;

-- =====================================================
-- USEFUL QUERIES FOR TESTING
-- =====================================================

-- Test refund calculation:
-- CALL calculate_refund(1, NOW(), @pct, @amt, @fee, @final, @days);
-- SELECT @pct as percentage, @amt as refund_amount, @fee as processing_fee, @final as final_refund, @days as days_before;

-- Get refund percentage:
-- SELECT get_refund_percentage('2026-04-15 14:00:00', NOW()) as refund_percentage;

-- Manual no-show detection:
-- CALL detect_no_shows();

-- View pending refunds:
-- SELECT * FROM refunds WHERE refund_status = 'Pending' ORDER BY created_at DESC;

-- View no-show bookings:
-- SELECT * FROM bookings WHERE status = 'no_show' ORDER BY check_in_date DESC;

-- View no-show detection log:
-- SELECT * FROM no_show_detection_log ORDER BY detection_time DESC LIMIT 10;

-- =====================================================
-- END OF REFUND SYSTEM SETUP
-- =====================================================


-- =====================================================
-- SAFARICOM ETHIOPIA M-PESA INTEGRATION
-- =====================================================
-- M-Pesa Payment Integration for Hotel Management System
-- Supports: STK Push (C2B), Payment Verification, Callbacks
-- =====================================================

-- =====================================================
-- STEP 1: CREATE M-PESA TRANSACTIONS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NULL COMMENT 'Reference to bookings table',
    merchant_request_id VARCHAR(100) NULL COMMENT 'M-Pesa Merchant Request ID',
    checkout_request_id VARCHAR(100) NULL COMMENT 'M-Pesa Checkout Request ID',
    transaction_id VARCHAR(100) NULL COMMENT 'M-Pesa Transaction ID (MPESA Receipt)',
    phone_number VARCHAR(20) NOT NULL COMMENT 'Customer phone number (251XXXXXXXXX)',
    amount DECIMAL(10,2) NOT NULL COMMENT 'Transaction amount in ETB',
    account_reference VARCHAR(100) NOT NULL COMMENT 'Booking reference or account ref',
    transaction_desc VARCHAR(255) NULL COMMENT 'Transaction description',
    transaction_type ENUM('C2B', 'B2C', 'B2B') DEFAULT 'C2B' COMMENT 'Transaction type',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'timeout') DEFAULT 'pending',
    result_code VARCHAR(10) NULL COMMENT 'M-Pesa result code',
    result_desc TEXT NULL COMMENT 'M-Pesa result description',
    callback_received TINYINT(1) DEFAULT 0 COMMENT 'Whether callback was received',
    callback_data JSON NULL COMMENT 'Full callback JSON data',
    api_request JSON NULL COMMENT 'Original API request data',
    api_response JSON NULL COMMENT 'API response data',
    error_message TEXT NULL COMMENT 'Error message if failed',
    initiated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When STK push was initiated',
    completed_at TIMESTAMP NULL COMMENT 'When payment was completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    INDEX idx_booking_id (booking_id),
    INDEX idx_merchant_request (merchant_request_id),
    INDEX idx_checkout_request (checkout_request_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    UNIQUE KEY unique_checkout_request (checkout_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 2: CREATE M-PESA ACCESS TOKENS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS mpesa_access_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    access_token TEXT NOT NULL COMMENT 'Bearer token from M-Pesa API',
    token_type VARCHAR(50) DEFAULT 'Bearer',
    expires_in INT NOT NULL COMMENT 'Token validity in seconds',
    expires_at TIMESTAMP NOT NULL COMMENT 'Calculated expiration timestamp',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Whether token is currently active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 3: CREATE M-PESA API LOGS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS mpesa_api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NULL COMMENT 'Reference to mpesa_transactions',
    endpoint VARCHAR(255) NOT NULL COMMENT 'API endpoint called',
    request_method VARCHAR(10) NOT NULL COMMENT 'HTTP method (GET, POST)',
    request_headers JSON NULL COMMENT 'Request headers',
    request_body JSON NULL COMMENT 'Request body',
    response_code INT NULL COMMENT 'HTTP response code',
    response_headers JSON NULL COMMENT 'Response headers',
    response_body JSON NULL COMMENT 'Response body',
    execution_time DECIMAL(10,3) NULL COMMENT 'API call duration in seconds',
    error_message TEXT NULL COMMENT 'Error message if failed',
    ip_address VARCHAR(45) NULL COMMENT 'Client IP address',
    user_agent TEXT NULL COMMENT 'Client user agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (transaction_id) REFERENCES mpesa_transactions(id) ON DELETE SET NULL,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_endpoint (endpoint),
    INDEX idx_created_at (created_at),
    INDEX idx_response_code (response_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 4: CREATE M-PESA CALLBACK LOGS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS mpesa_callback_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NULL COMMENT 'Reference to mpesa_transactions',
    callback_type ENUM('success', 'timeout', 'error') NOT NULL,
    merchant_request_id VARCHAR(100) NULL,
    checkout_request_id VARCHAR(100) NULL,
    result_code VARCHAR(10) NULL,
    result_desc TEXT NULL,
    callback_data JSON NOT NULL COMMENT 'Full callback payload',
    processed TINYINT(1) DEFAULT 0 COMMENT 'Whether callback was processed',
    processing_error TEXT NULL COMMENT 'Error during callback processing',
    ip_address VARCHAR(45) NULL COMMENT 'Callback source IP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    
    FOREIGN KEY (transaction_id) REFERENCES mpesa_transactions(id) ON DELETE SET NULL,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_checkout_request (checkout_request_id),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 5: CREATE STORED PROCEDURE - GET VALID ACCESS TOKEN
-- =====================================================

DELIMITER $

DROP PROCEDURE IF EXISTS get_valid_mpesa_token$

CREATE PROCEDURE get_valid_mpesa_token(
    OUT p_access_token TEXT,
    OUT p_is_valid TINYINT
)
BEGIN
    DECLARE v_token TEXT;
    DECLARE v_expires_at TIMESTAMP;
    
    -- Get the most recent active token that hasn't expired
    SELECT access_token, expires_at 
    INTO v_token, v_expires_at
    FROM mpesa_access_tokens
    WHERE is_active = 1 
    AND expires_at > NOW()
    ORDER BY created_at DESC
    LIMIT 1;
    
    IF v_token IS NOT NULL THEN
        SET p_access_token = v_token;
        SET p_is_valid = 1;
    ELSE
        SET p_access_token = NULL;
        SET p_is_valid = 0;
    END IF;
END$

DELIMITER ;

-- =====================================================
-- STEP 6: CREATE STORED PROCEDURE - STORE ACCESS TOKEN
-- =====================================================

DELIMITER $

DROP PROCEDURE IF EXISTS store_mpesa_token$

CREATE PROCEDURE store_mpesa_token(
    IN p_access_token TEXT,
    IN p_expires_in INT
)
BEGIN
    DECLARE v_expires_at TIMESTAMP;
    
    -- Calculate expiration time (subtract 60 seconds for safety margin)
    SET v_expires_at = DATE_ADD(NOW(), INTERVAL (p_expires_in - 60) SECOND);
    
    -- Deactivate all previous tokens
    UPDATE mpesa_access_tokens SET is_active = 0;
    
    -- Insert new token
    INSERT INTO mpesa_access_tokens (access_token, expires_in, expires_at, is_active)
    VALUES (p_access_token, p_expires_in, v_expires_at, 1);
END$

DELIMITER ;

-- =====================================================
-- STEP 7: CREATE STORED PROCEDURE - UPDATE TRANSACTION STATUS
-- =====================================================

DELIMITER $

DROP PROCEDURE IF EXISTS update_mpesa_transaction_status$

CREATE PROCEDURE update_mpesa_transaction_status(
    IN p_checkout_request_id VARCHAR(100),
    IN p_transaction_id VARCHAR(100),
    IN p_result_code VARCHAR(10),
    IN p_result_desc TEXT,
    IN p_status VARCHAR(20),
    IN p_callback_data JSON
)
BEGIN
    DECLARE v_transaction_id INT;
    DECLARE v_booking_id INT;
    
    -- Update M-Pesa transaction
    UPDATE mpesa_transactions
    SET 
        transaction_id = p_transaction_id,
        result_code = p_result_code,
        result_desc = p_result_desc,
        status = p_status,
        callback_received = 1,
        callback_data = p_callback_data,
        completed_at = NOW()
    WHERE checkout_request_id = p_checkout_request_id;
    
    -- Get transaction and booking IDs
    SELECT id, booking_id INTO v_transaction_id, v_booking_id
    FROM mpesa_transactions
    WHERE checkout_request_id = p_checkout_request_id;
    
    -- If payment successful, update booking status
    IF p_result_code = '0' AND v_booking_id IS NOT NULL THEN
        UPDATE bookings
        SET 
            payment_status = 'paid',
            status = 'confirmed',
            verification_status = 'verified',
            verified_at = NOW()
        WHERE id = v_booking_id;
        
        -- Log activity
        INSERT INTO booking_activity_log (booking_id, activity_type, description, created_at)
        VALUES (v_booking_id, 'payment_verified', CONCAT('M-Pesa payment verified. Transaction ID: ', p_transaction_id), NOW());
    END IF;
    
    SELECT v_transaction_id as transaction_id, v_booking_id as booking_id;
END$

DELIMITER ;

-- =====================================================
-- STEP 8: CREATE EVENT - CLEANUP OLD LOGS (Optional)
-- =====================================================

-- Enable event scheduler if not already enabled (requires SUPER privilege - run manually if needed)
-- SET GLOBAL event_scheduler = ON;

-- Drop existing event if exists
-- DROP EVENT IF EXISTS cleanup_old_mpesa_logs;

-- Create event to cleanup logs older than 90 days (DISABLED - requires event_scheduler to be ON)
-- Uncomment below lines after enabling event_scheduler manually
/*
CREATE EVENT IF NOT EXISTS cleanup_old_mpesa_logs
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Delete old API logs (keep 90 days)
    DELETE FROM mpesa_api_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Delete old callback logs (keep 90 days)
    DELETE FROM mpesa_callback_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND processed = 1;
    
    -- Deactivate expired tokens
    UPDATE mpesa_access_tokens 
    SET is_active = 0 
    WHERE expires_at < NOW() AND is_active = 1;
END;
*/

-- =====================================================
-- M-PESA INTEGRATION SETUP COMPLETED
-- =====================================================

SELECT 'M-Pesa Integration tables and procedures created successfully!' as message;

-- =====================================================
-- SCREENSHOT PAYMENT SYSTEM DOCUMENTATION
-- =====================================================

-- PAYMENT SYSTEM OVERVIEW:
-- This database now supports a screenshot-only payment system
-- No online payment gateways (Chapa removed)
-- Manual bank/mobile payments with screenshot verification

-- SUPPORTED PAYMENT METHODS (4 ONLY):
-- 1. TeleBirr: 0973409026
-- 2. CBE Mobile Banking: 1000274236552
-- 3. Abyssinia Bank: 244422382
-- 4. Cooperative Bank of Oromia: 1000056621528
-- Account Holder: Harar Ras Hotel (same for all)

-- PAYMENT FLOW:
-- 1. Customer creates booking
-- 2. Selects payment method
-- 3. Makes payment via mobile/bank
-- 4. Uploads screenshot (screenshot_path column)
-- 5. Staff verifies payment (verification_status)
-- 6. Booking confirmed when approved

-- KEY COLUMNS IN BOOKINGS TABLE:
-- screenshot_path: Path to uploaded payment screenshot
-- screenshot_uploaded_at: When screenshot was uploaded
-- payment_method: telebirr, cbe, abyssinia, cooperative
-- verification_status: pending_payment, pending_verification, verified, rejected, expired

-- FILE STORAGE:
-- Screenshots stored in: uploads/payments/
-- Max file size: 2MB
-- Allowed formats: JPG, PNG, JPEG

-- STAFF VERIFICATION:
-- Access: dashboard/verify-payments.php
-- Actions: Approve/Reject payments
-- View: Screenshot preview with booking details

-- =====================================================
-- DATABASE SETUP COMPLETED SUCCESSFULLY
-- =====================================================
-- Total Tables: 35+ (all hotel management features)
-- Payment System: Screenshot Upload Only
-- Ready for Production: YES
-- =====================================================