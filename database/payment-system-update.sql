-- =====================================================
-- PAYMENT SYSTEM UPDATE - Screenshot Upload Only
-- =====================================================
-- This updates the database to support the new screenshot-only payment system
-- Removes Chapa integration and adds screenshot upload fields
-- =====================================================

USE harar_ras_hotel;

-- Add new columns for screenshot payment system
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS screenshot_path VARCHAR(255) NULL COMMENT 'Path to uploaded payment screenshot',
ADD COLUMN IF NOT EXISTS screenshot_uploaded_at TIMESTAMP NULL COMMENT 'When screenshot was uploaded';

-- Update payment_method column to support new methods
ALTER TABLE bookings 
MODIFY COLUMN payment_method VARCHAR(100) NULL COMMENT 'telebirr, cbe, abyssinia, cooperative';

-- Update verification_status enum to include new statuses
ALTER TABLE bookings 
MODIFY COLUMN verification_status ENUM('pending_payment', 'pending_verification', 'verified', 'rejected', 'expired') DEFAULT 'pending_payment';

-- Create uploads directory structure (this will be handled by PHP)
-- uploads/payments/ - for payment screenshots

-- Update existing bookings with pending payments to use new system
UPDATE bookings 
SET verification_status = 'pending_payment' 
WHERE payment_status = 'pending' AND verification_status IS NULL;

-- =====================================================
-- PAYMENT VERIFICATION DASHBOARD UPDATES
-- =====================================================

-- Update payment_verification_queue table to include screenshot path
ALTER TABLE payment_verification_queue 
ADD COLUMN IF NOT EXISTS screenshot_path VARCHAR(255) NULL COMMENT 'Path to payment screenshot';

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Add indexes for new columns
CREATE INDEX IF NOT EXISTS idx_bookings_screenshot_uploaded ON bookings(screenshot_uploaded_at);
CREATE INDEX IF NOT EXISTS idx_bookings_payment_method ON bookings(payment_method);

-- =====================================================
-- SAMPLE DATA UPDATE
-- =====================================================

-- Update payment method instructions to match new system
UPDATE payment_method_instructions 
SET method_name = 'TeleBirr', 
    account_number = '0973409026',
    payment_instructions = 'Transfer the amount to TeleBirr number 0973409026 and upload the screenshot'
WHERE method_code = 'telebirr';

UPDATE payment_method_instructions 
SET method_name = 'CBE Mobile Banking',
    account_number = '1000274236552',
    payment_instructions = 'Transfer to CBE account 1000274236552 and upload the screenshot'
WHERE method_code = 'cbe_mobile';

UPDATE payment_method_instructions 
SET method_name = 'Abyssinia Bank',
    account_number = '244422382',
    payment_instructions = 'Transfer to Abyssinia Bank account 244422382 and upload the screenshot'
WHERE method_code = 'abyssinia_bank';

-- Add Cooperative Bank of Oromia if not exists
INSERT IGNORE INTO payment_method_instructions 
(method_code, method_name, bank_name, account_number, account_holder_name, payment_instructions, verification_tips, display_order)
VALUES 
('cooperative', 'Cooperative Bank of Oromia', 'Cooperative Bank of Oromia', '1000056621528', 'Harar Ras Hotel', 
'Transfer to Cooperative Bank account 1000056621528 and upload the screenshot',
'Ensure screenshot shows successful transfer with correct amount and reference', 4);

-- =====================================================
-- CLEANUP OLD CHAPA DATA
-- =====================================================

-- Remove Chapa-specific data (optional - uncomment if you want to clean up)
-- UPDATE bookings SET transaction_id = NULL WHERE payment_method = 'chapa';

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Check updated schema
DESCRIBE bookings;

-- Check payment methods
SELECT method_code, method_name, account_number FROM payment_method_instructions ORDER BY display_order;

-- Check bookings with new payment system
SELECT id, booking_reference, payment_method, verification_status, screenshot_path, screenshot_uploaded_at 
FROM bookings 
WHERE screenshot_uploaded_at IS NOT NULL 
ORDER BY screenshot_uploaded_at DESC 
LIMIT 10;

-- =====================================================
-- SETUP COMPLETE
-- =====================================================
-- The database is now ready for the new screenshot-only payment system
-- Supported payment methods: telebirr, cbe, abyssinia, cooperative
-- All payments require screenshot upload for verification
-- =====================================================