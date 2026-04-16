-- =====================================================
-- HARAR RAS HOTEL - CLEAN DATABASE SETUP
-- =====================================================
-- Screenshot-only payment system (Chapa removed)
-- Database name: harar_ras_hotel
-- =====================================================

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS harar_ras_hotel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE harar_ras_hotel;

-- Set character set
SET NAMES utf8mb4;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- DROP EXISTING TABLES (Clean slate)
-- =====================================================

DROP TABLE IF EXISTS service_bookings;
DROP TABLE IF EXISTS food_order_items;
DROP TABLE IF EXISTS food_orders;
DROP TABLE IF EXISTS customer_feedback;
DROP TABLE IF EXISTS payment_method_instructions;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS food_menu;

-- =====================================================
-- CREATE CORE TABLES
-- =====================================================

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'receptionist', 'manager', 'admin', 'super_admin') DEFAULT 'customer',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms Table
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    room_type ENUM('standard', 'deluxe', 'suite', 'family', 'presidential') NOT NULL,
    description TEXT,
    capacity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    status ENUM('active', 'occupied', 'booked', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_type (room_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings Table (Screenshot Payment System)
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customer_name VARCHAR(200),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    room_id INT NULL,
    booking_reference VARCHAR(50) UNIQUE NOT NULL,
    check_in_date DATE NULL,
    check_out_date DATE NULL,
    customers INT NOT NULL DEFAULT 1,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(100) NULL COMMENT 'telebirr, cbe, abyssinia, cooperative',
    special_requests TEXT,
    
    -- Service booking integration
    booking_type ENUM('room', 'food_order', 'spa_service', 'laundry_service') DEFAULT 'room',
    verification_status ENUM('pending_payment', 'pending_verification', 'verified', 'rejected', 'expired') DEFAULT 'pending_payment',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    
    -- Screenshot Payment System
    screenshot_path VARCHAR(255) NULL COMMENT 'Path to uploaded payment screenshot',
    screenshot_uploaded_at TIMESTAMP NULL COMMENT 'When screenshot was uploaded',
    rejection_reason TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_booking_reference (booking_reference),
    INDEX idx_verification_status (verification_status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_screenshot_uploaded (screenshot_uploaded_at),
    
    -- Foreign keys
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Services Table
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('restaurant', 'spa', 'laundry', 'transport', 'tours', 'other') NOT NULL,
    description TEXT,
    price DECIMAL(10, 2),
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Food Menu Table
CREATE TABLE food_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('appetizer', 'main_course', 'dessert', 'beverage', 'traditional', 'international') NOT NULL,
    description TEXT,
    price DECIMAL(8, 2) NOT NULL,
    image VARCHAR(255),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_available (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Food Orders Table
CREATE TABLE food_orders (
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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Food Order Items Table
CREATE TABLE food_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES food_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service Bookings Table (Spa & Laundry)
CREATE TABLE service_bookings (
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
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_category (service_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Method Instructions Table
CREATE TABLE payment_method_instructions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_code VARCHAR(50) UNIQUE NOT NULL,
    method_name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    account_holder_name VARCHAR(100) NOT NULL,
    payment_instructions TEXT NOT NULL,
    verification_tips TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Feedback Table
CREATE TABLE customer_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    customer_id INT NOT NULL,
    overall_rating INT NOT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    service_quality INT NOT NULL CHECK (service_quality >= 1 AND service_quality <= 5),
    cleanliness INT NOT NULL CHECK (cleanliness >= 1 AND cleanliness <= 5),
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT PAYMENT METHODS (4 ONLY)
-- =====================================================

INSERT INTO payment_method_instructions 
(method_code, method_name, bank_name, account_number, account_holder_name, payment_instructions, verification_tips, display_order) 
VALUES
('telebirr', 'TeleBirr', 'Ethio Telecom', '0973409026', 'Harar Ras Hotel', 
'1. Open TeleBirr app\n2. Select Send Money\n3. Enter amount\n4. Enter recipient: 0973409026\n5. Add reference code\n6. Complete transaction\n7. Take screenshot\n8. Upload on payment page', 
'Screenshot must show exact amount, recipient number, reference code, and success status.', 1),

('cbe', 'CBE Mobile Banking', 'Commercial Bank of Ethiopia', '1000274236552', 'Harar Ras Hotel', 
'1. Open CBE Mobile app\n2. Login to account\n3. Select Transfer Money\n4. Enter amount\n5. Enter account: 1000274236552\n6. Add reference\n7. Complete transfer\n8. Take screenshot\n9. Upload on payment page', 
'Screenshot must show successful transfer with correct amount and account number.', 2),

('abyssinia', 'Abyssinia Bank', 'Abyssinia Bank', '244422382', 'Harar Ras Hotel',
'1. Login to Abyssinia Bank app\n2. Select Transfer/Payment\n3. Enter amount\n4. Enter account: 244422382\n5. Add reference\n6. Complete transaction\n7. Take screenshot\n8. Upload on payment page',
'Screenshot must show successful transaction with correct amount and account number.', 3),

('cooperative', 'Cooperative Bank of Oromia', 'Cooperative Bank of Oromia', '1000056621528', 'Harar Ras Hotel',
'1. Login to Cooperative Bank app\n2. Select Fund Transfer\n3. Enter amount\n4. Enter account: 1000056621528\n5. Add reference\n6. Complete transfer\n7. Take screenshot\n8. Upload on payment page',
'Screenshot must show successful transfer with correct amount and account number.', 4);

-- =====================================================
-- INSERT SAMPLE DATA
-- =====================================================

-- Insert Test User (password: password123)
INSERT INTO users (first_name, last_name, username, email, phone, password, role) VALUES
('Test', 'User', 'testuser', 'test@example.com', '0912345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('Admin', 'User', 'admin', 'admin@hotel.com', '0911111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert Sample Rooms
INSERT INTO rooms (name, room_number, room_type, description, capacity, price, status) VALUES
('Standard Room 101', '101', 'standard', 'Comfortable standard room with city view', 2, 1500.00, 'active'),
('Standard Room 102', '102', 'standard', 'Comfortable standard room with city view', 2, 1500.00, 'active'),
('Deluxe Room 201', '201', 'deluxe', 'Spacious deluxe room with premium amenities', 2, 2500.00, 'active'),
('Deluxe Room 202', '202', 'deluxe', 'Spacious deluxe room with premium amenities', 2, 2500.00, 'active'),
('Suite 301', '301', 'suite', 'Luxury suite with separate living area', 4, 4500.00, 'active'),
('Family Room 401', '401', 'family', 'Large family room with multiple beds', 5, 3500.00, 'active'),
('Presidential Suite 501', '501', 'presidential', 'Top floor presidential suite with panoramic views', 6, 8500.00, 'active');

-- Insert Sample Services
INSERT INTO services (name, category, description, price, status) VALUES
('Deep Tissue Massage', 'spa', 'Relaxing deep tissue massage therapy', 800.00, 'active'),
('Facial Treatment', 'spa', 'Rejuvenating facial treatment', 600.00, 'active'),
('Aromatherapy Session', 'spa', 'Calming aromatherapy session', 700.00, 'active'),
('Wash & Iron', 'laundry', 'Professional washing and ironing service', 150.00, 'active'),
('Dry Cleaning', 'laundry', 'Premium dry cleaning service', 300.00, 'active'),
('Express Laundry', 'laundry', 'Same-day laundry service', 200.00, 'active');

-- Insert Sample Food Menu
INSERT INTO food_menu (name, category, description, price, is_available) VALUES
('Doro Wat', 'traditional', 'Traditional Ethiopian chicken stew', 480.00, TRUE),
('Vegetarian Combo', 'traditional', 'Assorted vegetarian dishes on injera', 400.00, TRUE),
('Kitfo', 'traditional', 'Ethiopian steak tartare', 580.00, TRUE),
('Grilled Chicken', 'main_course', 'Grilled chicken with vegetables', 450.00, TRUE),
('Pasta Bolognese', 'main_course', 'Classic pasta with meat sauce', 380.00, TRUE),
('Ethiopian Coffee', 'beverage', 'Traditional coffee ceremony', 200.00, TRUE),
('Fresh Juice', 'beverage', 'Freshly squeezed fruit juice', 150.00, TRUE);

-- =====================================================
-- RE-ENABLE FOREIGN KEY CHECKS
-- =====================================================

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- SETUP COMPLETE
-- =====================================================

SELECT 'Database setup completed successfully!' as message,
       'Screenshot-only payment system ready' as payment_system,
       '4 payment methods configured' as payment_methods,
       'Ready for production use' as status;

-- =====================================================
-- PAYMENT SYSTEM SUMMARY
-- =====================================================
-- Supported Payment Methods:
-- 1. TeleBirr: 0973409026
-- 2. CBE Mobile Banking: 1000274236552
-- 3. Abyssinia Bank: 244422382
-- 4. Cooperative Bank of Oromia: 1000056621528
-- Account Holder: Harar Ras Hotel (all methods)
-- 
-- Payment Flow:
-- 1. Customer selects payment method
-- 2. Makes payment via mobile/bank
-- 3. Uploads screenshot (screenshot_path)
-- 4. Staff verifies payment
-- 5. Booking confirmed when approved
-- =====================================================