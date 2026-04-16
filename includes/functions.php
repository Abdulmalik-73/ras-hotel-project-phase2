<?php
// Clean version of functions.php without any duplicates or errors

// Include authentication functions
require_once __DIR__ . '/auth.php';

// Security Functions
function sanitize_input($data) {
    global $conn;
    // Handle null values to prevent PHP 8.1+ deprecation warning
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Safe htmlspecialchars function to prevent PHP 8.1+ deprecation warnings
function safe_html($value, $default = '') {
    if ($value === null || $value === '') {
        return htmlspecialchars($default);
    }
    return htmlspecialchars($value);
}

function validate_username($username) {
    // Username can only contain letters and spaces, minimum 2 characters
    return preg_match('/^[a-zA-Z\s]{2,}$/', $username);
}

function validate_staff_username($username, $role) {
    // Staff roles must use hararrashotel
    if (in_array($role, ['receptionist', 'manager', 'admin'])) {
        return $username === 'hararrashotel';
    }
    // Customer role uses regular username validation and cannot use hararrashotel
    if ($role === 'customer') {
        return $username !== 'hararrashotel' && validate_username($username);
    }
    return false;
}

function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// Authentication Functions



// User Functions
function get_user_by_id($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = $conn->query($query);
    return $result->fetch_assoc();
}

function get_user_by_email($email) {
    global $conn;
    $email = sanitize_input($email);
    $query = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($query);
    return $result->fetch_assoc();
}

// Password Functions
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Room Functions
function get_all_rooms($limit = null) {
    global $conn;
    
    // Ensure we have a fresh connection
    if (!$conn || $conn->connect_error) {
        error_log("Database connection error in get_all_rooms: " . ($conn->connect_error ?? 'No connection'));
        return [];
    }
    
    // Fetch ALL rooms (including occupied) so prices are always current
    // Status filtering is done at display level
    $query = "SELECT * FROM rooms ORDER BY CAST(room_number AS UNSIGNED) ASC";
    if ($limit) {
        $query .= " LIMIT " . (int)$limit;
    }
    
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("Query error in get_all_rooms: " . $conn->error);
        return [];
    }
    
    $rooms = $result->fetch_all(MYSQLI_ASSOC);
    error_log("get_all_rooms returned " . count($rooms) . " rooms");
    
    return $rooms;
}

function get_room_by_id($room_id) {
    global $conn;
    $room_id = (int)$room_id;
    $query = "SELECT * FROM rooms WHERE id = $room_id";
    $result = $conn->query($query);
    return $result->fetch_assoc();
}

function get_available_rooms($check_in, $check_out) {
    global $conn;
    $check_in = sanitize_input($check_in);
    $check_out = sanitize_input($check_out);
    
    $query = "SELECT r.* FROM rooms r 
              WHERE r.status = 'active' 
              AND r.id NOT IN (
                  SELECT room_id FROM bookings 
                  WHERE status IN ('confirmed', 'checked_in')
                  AND (
                      (check_in_date <= '$check_in' AND check_out_date > '$check_in')
                      OR (check_in_date < '$check_out' AND check_out_date >= '$check_out')
                      OR (check_in_date >= '$check_in' AND check_out_date <= '$check_out')
                  )
              )
              ORDER BY r.price ASC";
    
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Booking Functions
function create_booking($data) {
    global $conn;
    
    $user_id = (int)$data['user_id'];
    $room_id = (int)$data['room_id'];
    $check_in_date = sanitize_input($data['check_in']);
    $check_out_date = sanitize_input($data['check_out']);
    $customers = (int)$data['customers'];
    $total_price = (float)$data['total_price'];
    $special_requests = isset($data['special_requests']) ? sanitize_input($data['special_requests']) : '';
    $booking_ref = 'HRH' . date('Ymd') . rand(1000, 9999);
    
    // Debug logging
    error_log("create_booking called with user_id: $user_id, room_id: $room_id");
    
    // Check if user_id is valid
    if ($user_id <= 0) {
        error_log("Invalid user_id: $user_id");
        return ['success' => false, 'message' => 'Invalid user ID (user_id must be greater than 0)'];
    }
    
    // ============================================
    // RESTRICTION: Maximum 3 bookings per user per day (same check-in date)
    // ============================================
    $same_day_booking_check = $conn->prepare("
        SELECT b.id, b.booking_reference, b.check_in_date, b.check_out_date, b.status, r.name as room_name, r.room_number
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        WHERE b.user_id = ? 
        AND b.booking_type = 'room'
        AND b.status IN ('pending', 'confirmed', 'checked_in')
        AND DATE(b.check_in_date) = DATE(?)
    ");
    
    if (!$same_day_booking_check) {
        error_log("Failed to prepare same day booking check: " . $conn->error);
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $same_day_booking_check->bind_param("is", $user_id, $check_in_date);
    $same_day_booking_check->execute();
    $same_day_result = $same_day_booking_check->get_result();
    
    // Check if user has reached the maximum of 3 bookings for this specific day
    if ($same_day_result->num_rows >= 3) {
        $existing_bookings = [];
        while ($row = $same_day_result->fetch_assoc()) {
            $existing_bookings[] = [
                'reference' => $row['booking_reference'],
                'room_name' => $row['room_name'],
                'room_number' => $row['room_number'],
                'check_in_date' => date('F j, Y', strtotime($row['check_in_date'])),
                'check_out_date' => date('F j, Y', strtotime($row['check_out_date'])),
                'status' => ucfirst($row['status'])
            ];
        }
        
        error_log("User $user_id has reached maximum booking limit for " . $check_in_date . " (3 bookings per day)");
        
        return [
            'success' => false,
            'message' => "You have reached the maximum booking limit for this date. You can have up to 3 bookings per day.",
            'existing_bookings' => $existing_bookings,
            'booking_count' => count($existing_bookings),
            'check_in_date' => date('F j, Y', strtotime($check_in_date)),
            'error_code' => 'MAX_BOOKING_LIMIT'
        ];
    }
    
    // ============================================
    // CHECK FOR OVERLAPPING BOOKINGS (PREVENT DOUBLE BOOKING)
    // ============================================
    $overlap_check = $conn->prepare("
        SELECT b.id, b.booking_reference, b.status, b.check_in_date, b.check_out_date, r.name as room_name, r.room_number
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        WHERE b.room_id = ?
        AND b.status IN ('pending', 'confirmed', 'checked_in')
        AND NOT (b.check_out_date <= ? OR b.check_in_date >= ?)
    ");
    
    if (!$overlap_check) {
        error_log("Failed to prepare overlap check: " . $conn->error);
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $overlap_check->bind_param("iss", $room_id, $check_in_date, $check_out_date);
    $overlap_check->execute();
    $overlap_result = $overlap_check->get_result();
    
    if ($overlap_result->num_rows > 0) {
        $blocking_booking = $overlap_result->fetch_assoc();
        
        error_log("Room $room_id is already booked for dates $check_in_date to $check_out_date. Blocking booking: " . $blocking_booking['booking_reference']);
        
        $status_message = $blocking_booking['status'] === 'pending' 
            ? 'This room is currently on hold (waiting for approval) for the selected dates.' 
            : 'This room is already booked for the selected dates.';
        
        return [
            'success' => false,
            'message' => $status_message . ' Please choose different dates or another room.',
            'error_code' => 'ROOM_NOT_AVAILABLE',
            'blocking_booking' => [
                'reference' => $blocking_booking['booking_reference'],
                'status' => ucfirst($blocking_booking['status']),
                'room_name' => $blocking_booking['room_name'],
                'room_number' => $blocking_booking['room_number'],
                'check_in' => date('F j, Y', strtotime($blocking_booking['check_in_date'])),
                'check_out' => date('F j, Y', strtotime($blocking_booking['check_out_date']))
            ]
        ];
    }
    
    // ============================================
    // All checks passed - proceed with booking
    // Note: We allow up to 3 bookings per day (same check-in date)
    // Room must not have overlapping bookings in pending/confirmed/checked_in status
    // ============================================
    
    // Validate user exists
    $user_check = $conn->prepare("SELECT id, email FROM users WHERE id = ?");
    if (!$user_check) {
        error_log("Failed to prepare user check query: " . $conn->error);
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $user_check->bind_param("i", $user_id);
    $user_check->execute();
    $user_result = $user_check->get_result();
    
    error_log("User check result rows: " . $user_result->num_rows);
    
    if ($user_result->num_rows == 0) {
        // Get total users in database for debugging
        $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
        error_log("User ID $user_id not found in database. Total users in database: $total_users");
        return ['success' => false, 'message' => 'User account not found. Please log in again.'];
    }
    
    $user_data = $user_result->fetch_assoc();
    error_log("User found: " . $user_data['email']);
    
    // Validate room exists and is active
    $room_check = $conn->prepare("SELECT id, name FROM rooms WHERE id = ? AND status = 'active'");
    if (!$room_check) {
        error_log("Failed to prepare room check query: " . $conn->error);
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $room_check->bind_param("i", $room_id);
    $room_check->execute();
    $room_result = $room_check->get_result();
    
    error_log("Room check result rows: " . $room_result->num_rows);
    
    if ($room_result->num_rows == 0) {
        // Get total rooms in database for debugging
        $total_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms")->fetch_assoc()['count'];
        error_log("Room ID $room_id not found or inactive. Total rooms in database: $total_rooms");
        return ['success' => false, 'message' => 'Room not found or is unavailable. Please select a different room.'];
    }
    
    $room_data = $room_result->fetch_assoc();
    error_log("Room found: " . $room_data['name']);
    
    // Generate unique booking reference
    do {
        $booking_ref = 'HRH' . date('Ymd') . rand(1000, 9999);
        $ref_check = $conn->prepare("SELECT id FROM bookings WHERE booking_reference = ?");
        $ref_check->bind_param("s", $booking_ref);
        $ref_check->execute();
        $ref_exists = $ref_check->get_result()->num_rows > 0;
    } while ($ref_exists);
    
    // Insert booking with correct column names and schema
    $query = "INSERT INTO bookings (user_id, room_id, booking_reference, check_in_date, check_out_date, 
              customers, total_price, special_requests, status, booking_type, verification_status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'room', 'pending_payment')";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare insert query: " . $conn->error);
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $stmt->bind_param("iisssids", $user_id, $room_id, $booking_ref, $check_in_date, $check_out_date, $customers, $total_price, $special_requests);
    
    if ($stmt->execute()) {
        error_log("Booking created successfully with ID: " . $conn->insert_id);
        return [
            'success' => true,
            'booking_id' => $conn->insert_id,
            'booking_reference' => $booking_ref
        ];
    } else {
        error_log("Failed to execute insert: " . $stmt->error);
        return ['success' => false, 'message' => $stmt->error];
    }
}

function get_user_bookings($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    
    $query = "SELECT b.*, r.name as room_name, r.image as room_image 
              FROM bookings b 
              JOIN rooms r ON b.room_id = r.id 
              WHERE b.user_id = $user_id 
              ORDER BY b.created_at DESC";
    
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function get_booking_by_reference($reference) {
    global $conn;
    $reference = sanitize_input($reference);
    
    $query = "SELECT b.*, r.name as room_name, r.image as room_image, 
              u.first_name, u.last_name, u.email, u.phone 
              FROM bookings b 
              JOIN rooms r ON b.room_id = r.id 
              JOIN users u ON b.user_id = u.id 
              WHERE b.booking_reference = '$reference'";
    
    $result = $conn->query($query);
    return $result->fetch_assoc();
}

// Utility Functions
function format_date($date) {
    if (!$date || empty($date)) {
        return 'N/A';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return 'N/A';
    }
    return date('F j, Y', $timestamp);
}

function format_currency($amount) {
    // Handle null or empty values to prevent PHP 8.1+ deprecation warning
    if ($amount === null || $amount === '') {
        $amount = 0;
    }
    return 'ETB ' . number_format((float)$amount, 2);
}

function calculate_nights($check_in, $check_out) {
    if (!$check_in || !$check_out || empty($check_in) || empty($check_out)) {
        return 0;
    }
    try {
        $date1 = new DateTime($check_in);
        $date2 = new DateTime($check_out);
        $interval = $date1->diff($date2);
        return $interval->days;
    } catch (Exception $e) {
        return 0;
    }
}

// Alert Messages
function set_message($type, $message) {
    $_SESSION['message'] = [
        'type' => $type,
        'text' => $message
    ];
}

function display_message() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message']['type'];
        $text = $_SESSION['message']['text'];
        
        $alert_class = 'alert-info';
        if ($type == 'success') $alert_class = 'alert-success';
        if ($type == 'error') $alert_class = 'alert-danger';
        if ($type == 'warning') $alert_class = 'alert-warning';
        
        echo "<div class='alert $alert_class alert-dismissible fade show' role='alert'>
                $text
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        
        unset($_SESSION['message']);
    }
}

// Activity Logging Functions
function log_user_activity($user_id, $activity_type, $description = '', $ip_address = '', $user_agent = '') {
    global $conn;
    
    // Validate and sanitize inputs
    $user_id = (int)$user_id;
    
    // Check if user_id is valid
    if ($user_id <= 0) {
        error_log("Invalid user_id for activity logging: " . $user_id);
        return false;
    }
    
    // Verify user exists before logging
    $check_query = "SELECT id FROM users WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt) {
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $check_stmt->close();
        
        if ($result->num_rows === 0) {
            error_log("User ID $user_id does not exist in users table");
            return false;
        }
    }
    
    $activity_type = sanitize_input($activity_type);
    $description = sanitize_input($description);
    $ip_address = $ip_address ?: ($_SERVER['REMOTE_ADDR'] ?? '');
    $user_agent = $user_agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    // Use prepared statement to prevent SQL injection
    $query = "INSERT INTO user_activity_log (user_id, activity_type, description, ip_address, user_agent, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $activity_type, $description, $ip_address, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}

function log_booking_activity($booking_id, $user_id, $activity_type, $old_status = '', $new_status = '', $description = '', $performed_by = null) {
    global $conn;
    
    // Validate and sanitize inputs
    $booking_id = (int)$booking_id;
    $user_id = (int)$user_id;
    $activity_type = sanitize_input($activity_type);
    $old_status = sanitize_input($old_status);
    $new_status = sanitize_input($new_status);
    $description = sanitize_input($description);
    $performed_by = $performed_by ? (int)$performed_by : $user_id;
    
    // Use prepared statement to prevent SQL injection
    $query = "INSERT INTO booking_activity_log (booking_id, user_id, activity_type, old_status, new_status, description, performed_by, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("iissssi", $booking_id, $user_id, $activity_type, $old_status, $new_status, $description, $performed_by);
        $stmt->execute();
        $stmt->close();
    }
}

function save_newsletter_subscription($email, $name = '') {
    global $conn;
    $email = sanitize_input($email);
    $name = sanitize_input($name);
    
    $query = "INSERT INTO newsletter_subscriptions (email, name, created_at) 
              VALUES ('$email', '$name', NOW()) 
              ON DUPLICATE KEY UPDATE 
              name = VALUES(name), 
              status = 'active', 
              updated_at = NOW()";
    
    return $conn->query($query);
}

// Email Functions
function send_email($to, $subject, $message) {
    $headers = "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

function send_booking_confirmation($booking_id) {
    global $conn;
    
    $query = "SELECT b.*, r.name as room_name, u.email, u.first_name 
              FROM bookings b 
              JOIN rooms r ON b.room_id = r.id 
              JOIN users u ON b.user_id = u.id 
              WHERE b.id = $booking_id";
    
    $result = $conn->query($query);
    $booking = $result->fetch_assoc();
    
    if ($booking) {
        $subject = "Booking Confirmation - " . $booking['booking_reference'];
        $message = "
            <h2>Booking Confirmation</h2>
            <p>Dear {$booking['first_name']},</p>
            <p>Your booking has been confirmed!</p>
            <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
            <p><strong>Room:</strong> {$booking['room_name']}</p>
            <p><strong>Check-in:</strong> " . format_date($booking['check_in']) . "</p>
            <p><strong>Check-out:</strong> " . format_date($booking['check_out']) . "</p>
            <p><strong>Total Price:</strong> " . format_currency($booking['total_price']) . "</p>
            <p>We look forward to welcoming you!</p>
            <p>Best regards,<br>Harar Ras Hotel Team</p>
        ";
        
        return send_email($booking['email'], $subject, $message);
    }
    
    return false;
}

function send_payment_approval_email($booking_id) {
    global $conn;
    
    $query = "SELECT b.*, 
              COALESCE(r.name, 'Food Order') as room_name, 
              COALESCE(r.room_number, 'N/A') as room_number,
              u.email, u.first_name, u.last_name,
              CONCAT(verifier.first_name, ' ', verifier.last_name) as verified_by_name
              FROM bookings b 
              LEFT JOIN rooms r ON b.room_id = r.id 
              JOIN users u ON b.user_id = u.id 
              LEFT JOIN users verifier ON b.verified_by = verifier.id
              WHERE b.id = $booking_id";
    
    $result = $conn->query($query);
    $booking = $result->fetch_assoc();
    
    if ($booking && $booking['email']) {
        // Format dates safely
        $check_in_date = $booking['check_in_date'] ? date('F j, Y', strtotime($booking['check_in_date'])) : 'N/A';
        $check_out_date = $booking['check_out_date'] ? date('F j, Y', strtotime($booking['check_out_date'])) : 'N/A';
        $total_amount = format_currency($booking['total_price']);
        
        $subject = "Payment Approved - Booking Confirmed - " . $booking['booking_reference'];
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .booking-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .detail-label { font-weight: bold; color: #555; }
                .detail-value { color: #333; }
                .success-badge { background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
                .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Payment Approved!</h1>
                    <p>Your booking has been confirmed</p>
                </div>
                <div class='content'>
                    <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
                    
                    <div class='success-badge'>
                        ✓ Payment Verified & Approved
                    </div>
                    
                    <p><strong>Your payment process for booking {$booking['room_name']} Room Number {$booking['room_number']} is approved. So you can take your room key from our receptionist room.</strong></p>
                    
                    <p>Great news! Your payment has been successfully verified and approved by our team. Your booking is now confirmed!</p>
                    
                    <div class='booking-details'>
                        <h3 style='margin-top: 0; color: #667eea;'>Booking Details</h3>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Booking Reference:</span>
                            <span class='detail-value'><strong>{$booking['booking_reference']}</strong></span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Room:</span>
                            <span class='detail-value'>{$booking['room_name']} (Room {$booking['room_number']})</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Check-in Date:</span>
                            <span class='detail-value'>$check_in_date</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Check-out Date:</span>
                            <span class='detail-value'>$check_out_date</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Number of Guests:</span>
                            <span class='detail-value'>{$booking['customers']} guest(s)</span>
                        </div>
                        
                        <div class='detail-row' style='border-bottom: none;'>
                            <span class='detail-label'>Total Amount Paid:</span>
                            <span class='detail-value'><strong style='color: #28a745; font-size: 18px;'>$total_amount</strong></span>
                        </div>
                    </div>
                    
                    <h3>What's Next?</h3>
                    <ul>
                        <li>✓ Your booking is confirmed and secured</li>
                        <li>✓ Please arrive at the hotel on your check-in date</li>
                        <li>✓ Bring a valid ID for verification</li>
                        <li>✓ Check-in time: 2:00 PM | Check-out time: 12:00 PM</li>
                    </ul>
                    
                    <p><strong>Important:</strong> Please keep this booking reference number for your records: <code style='background: #f0f0f0; padding: 5px 10px; border-radius: 3px;'>{$booking['booking_reference']}</code></p>
                    
                    <p>If you have any questions or need to make changes to your booking, please contact us:</p>
                    <ul>
                        <li>📧 Email: info@hararrashotel.com</li>
                        <li>📞 Phone: +251-25-666-0000</li>
                    </ul>
                    
                    <p>We look forward to welcoming you to Harar Ras Hotel!</p>
                    
                    <p>Best regards,<br>
                    <strong>Harar Ras Hotel Team</strong><br>
                    Jugol Street, Harar, Ethiopia</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Harar Ras Hotel. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return send_email($booking['email'], $subject, $message);
    }
    
    return false;
}

function send_payment_rejection_email($booking_id, $rejection_reason) {
    global $conn;
    
    $query = "SELECT b.*, r.name as room_name, r.room_number,
              u.email, u.first_name, u.last_name
              FROM bookings b 
              JOIN rooms r ON b.room_id = r.id 
              JOIN users u ON b.user_id = u.id 
              WHERE b.id = $booking_id";
    
    $result = $conn->query($query);
    $booking = $result->fetch_assoc();
    
    if ($booking && $booking['email']) {
        $subject = "Payment Verification Required - " . $booking['booking_reference'];
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .booking-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545; }
                .warning-badge { background: #dc3545; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Payment Verification Issue</h1>
                    <p>Action required for your booking</p>
                </div>
                <div class='content'>
                    <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
                    
                    <div class='warning-badge'>
                        Payment Verification Failed
                    </div>
                    
                    <p>We were unable to verify your transaction ID for the following reason:</p>
                    
                    <div class='booking-details'>
                        <p><strong>Reason:</strong> {$rejection_reason}</p>
                        <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
                        <p><strong>Amount:</strong> " . format_currency($booking['total_price']) . "</p>
                    </div>
                    
                    <h3>What You Need to Do:</h3>
                    <ol>
                        <li>Please submit a new, valid transaction ID</li>
                        <li>Ensure the transaction ID shows:
                            <ul>
                                <li>Transaction amount matching your booking</li>
                                <li>Payment reference number</li>
                                <li>Successful transaction status</li>
                                <li>Date and time of transaction</li>
                            </ul>
                        </li>
                        <li>Submit the new screenshot through your booking page</li>
                    </ol>
                    
                    <p style='text-align: center;'>
                        <a href='http://localhost/rashotel/my-bookings.php' class='button'>Submit New Transaction ID</a>
                    </p>
                    
                    <p><strong>Important:</strong> Your booking will remain pending until payment is verified. Please submit a new transaction ID as soon as possible.</p>
                    
                    <p>If you need assistance or have questions, please contact us:</p>
                    <ul>
                        <li>📧 Email: info@hararrashotel.com</li>
                        <li>📞 Phone: +251-25-666-0000</li>
                    </ul>
                    
                    <p>Best regards,<br>
                    <strong>Harar Ras Hotel Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Harar Ras Hotel. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return send_email($booking['email'], $subject, $message);
    }
    
    return false;
}

// User Account Email Notifications
function send_account_creation_email($user_id) {
    global $conn;
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user && $user['email']) {
        $subject = "Welcome to Harar Ras Hotel - Your Account Created";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .account-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .detail-label { font-weight: bold; color: #555; }
                .detail-value { color: #333; font-family: monospace; }
                .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Harar Ras Hotel!</h1>
                    <p>Your account has been created</p>
                </div>
                <div class='content'>
                    <p>Dear {$user['first_name']} {$user['last_name']},</p>
                    
                    <p>Your account has been successfully created by our administrator. Below are your account details:</p>
                    
                    <div class='account-details'>
                        <h3 style='margin-top: 0; color: #667eea;'>Account Information</h3>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Email:</span>
                            <span class='detail-value'>{$user['email']}</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Role:</span>
                            <span class='detail-value'>" . ucfirst($user['role']) . "</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Status:</span>
                            <span class='detail-value'>" . ucfirst($user['status']) . "</span>
                        </div>
                        
                        <div class='detail-row' style='border-bottom: none;'>
                            <span class='detail-label'>Account Created:</span>
                            <span class='detail-value'>" . date('M j, Y H:i', strtotime($user['created_at'])) . "</span>
                        </div>
                    </div>
                    
                    <h3>Next Steps:</h3>
                    <ul>
                        <li>Log in to your account using your email and the password provided by the administrator</li>
                        <li>Update your profile information if needed</li>
                        <li>Contact support if you have any questions</li>
                    </ul>
                    
                    <p><strong>Important:</strong> Please keep your login credentials secure and do not share them with anyone.</p>
                    
                    <p>If you did not request this account or have any questions, please contact us:</p>
                    <ul>
                        <li>📧 Email: info@hararrashotel.com</li>
                        <li>📞 Phone: +251-25-666-0000</li>
                    </ul>
                    
                    <p>Best regards,<br>
                    <strong>Harar Ras Hotel Team</strong><br>
                    Jugol Street, Harar, Ethiopia</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Harar Ras Hotel. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return send_email($user['email'], $subject, $message);
    }
    
    return false;
}

function send_account_update_email($user_id, $changed_fields) {
    global $conn;
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user && $user['email']) {
        $subject = "Your Harar Ras Hotel Account Has Been Updated";
        
        $fields_list = implode(', ', array_map(function($field) {
            return ucfirst(str_replace('_', ' ', $field));
        }, $changed_fields));
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .update-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Account Update Notification</h1>
                    <p>Your account information has been updated</p>
                </div>
                <div class='content'>
                    <p>Dear {$user['first_name']} {$user['last_name']},</p>
                    
                    <p>Your account information has been updated by our administrator.</p>
                    
                    <div class='update-details'>
                        <h3 style='margin-top: 0; color: #667eea;'>Updated Fields</h3>
                        <p><strong>$fields_list</strong></p>
                        <p>Updated on: " . date('M j, Y H:i', strtotime($user['updated_at'])) . "</p>
                    </div>
                    
                    <p>If you did not authorize these changes or have any questions, please contact us immediately:</p>
                    <ul>
                        <li>📧 Email: info@hararrashotel.com</li>
                        <li>📞 Phone: +251-25-666-0000</li>
                    </ul>
                    
                    <p>Best regards,<br>
                    <strong>Harar Ras Hotel Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Harar Ras Hotel. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return send_email($user['email'], $subject, $message);
    }
    
    return false;
}

// Role-based access control functions


function get_user_permissions($role) {
    $permissions = [];
    
    switch ($role) {
        case 'admin':
            $permissions = [
                'manage_users', 'manage_bookings', 'manage_rooms', 
                'view_reports', 'system_settings', 'manage_staff'
            ];
            break;
        case 'manager':
            $permissions = [
                'manage_bookings', 'manage_rooms', 'view_reports', 
                'manage_staff', 'approve_bookings'
            ];
            break;
        case 'receptionist':
            $permissions = [
                'view_bookings', 'checkin_checkout', 'guest_services', 
                'confirm_bookings'
            ];
            break;
        case 'customer':
        case 'guest':
            $permissions = [
                'make_bookings', 'view_own_bookings', 'order_food'
            ];
            break;
    }
    
    return $permissions;
}

function has_permission($permission) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'];
    $permissions = get_user_permissions($user_role);
    
    return in_array($permission, $permissions);
}

// Super Admin and Authentication Functions
function create_super_admin_account($email, $password, $first_name = 'Super', $last_name = 'Admin') {
    global $conn;
    
    // Check if super admin already exists
    $check_query = "SELECT id FROM users WHERE role = 'super_admin'";
    $check_result = $conn->query($check_query);
    
    if ($check_result->num_rows > 0) {
        return ['success' => false, 'message' => 'Super Admin account already exists'];
    }
    
    $username = 'superadmin';
    $hashed_password = hash_password($password);
    $role = 'super_admin';
    $status = 'active';
    
    $query = "INSERT INTO users (first_name, last_name, username, email, password, role, status, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssss", $first_name, $last_name, $username, $email, $hashed_password, $role, $status);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Super Admin account created successfully', 'user_id' => $conn->insert_id];
    } else {
        return ['success' => false, 'message' => 'Failed to create Super Admin: ' . $stmt->error];
    }
}

function get_user_by_email_or_username($email_or_username) {
    global $conn;
    
    $query = "SELECT * FROM users WHERE email = ? OR username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email_or_username, $email_or_username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function log_login_attempt($email, $ip_address, $success = false, $user_agent = '') {
    global $conn;
    
    $query = "INSERT INTO login_attempts (email, ip_address, success, user_agent, attempt_time) 
              VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssis", $email, $ip_address, $success, $user_agent);
    
    return $stmt->execute();
}

function check_login_attempts($email, $ip_address, $max_attempts = 5, $time_window = 900) {
    global $conn;
    
    // Check failed attempts in the last 15 minutes (900 seconds)
    $query = "SELECT COUNT(*) as count FROM login_attempts 
              WHERE email = ? AND ip_address = ? AND success = 0 
              AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $email, $ip_address, $time_window);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] < $max_attempts;
}


function require_password_change($user_id) {
    global $conn;
    
    $query = "SELECT password_changed_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // If password_changed_at is NULL, password has never been changed
    return $result['password_changed_at'] === null;
}

function update_password($user_id, $new_password) {
    global $conn;
    
    $hashed_password = hash_password($new_password);
    
    $query = "UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    return $stmt->execute();
}

function send_staff_welcome_email($user_id, $password) {
    global $conn;
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user && $user['email']) {
        $subject = "Welcome to Harar Ras Hotel - Your Staff Account";
        $login_url = "http://localhost/rashotel/login.php";
        $year = date('Y');
        $role = ucfirst($user['role']);
        $first_name = htmlspecialchars($user['first_name']);
        $last_name = htmlspecialchars($user['last_name']);
        $email = htmlspecialchars($user['email']);
        
        $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .credentials { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
        .credential-row { padding: 10px 0; border-bottom: 1px solid #eee; }
        .credential-label { font-weight: bold; color: #555; display: inline-block; width: 100px; }
        .credential-value { font-family: monospace; color: #333; }
        .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
        .warning { background: #fffaf0; border-left: 4px solid #ed8936; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .warning ul { margin: 10px 0; padding-left: 20px; }
        .warning li { margin: 5px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Welcome to Harar Ras Hotel!</h1>
            <p>Your staff account has been created</p>
        </div>
        <div class='content'>
            <p>Dear $first_name $last_name,</p>
            
            <p>Your staff account has been successfully created by the Super Admin. Below are your login credentials:</p>
            
            <div class='credentials'>
                <h3 style='margin-top: 0; color: #667eea;'>Login Credentials</h3>
                
                <div class='credential-row'>
                    <span class='credential-label'>Email:</span>
                    <span class='credential-value'>$email</span>
                </div>
                
                <div class='credential-row'>
                    <span class='credential-label'>Password:</span>
                    <span class='credential-value'>$password</span>
                </div>
                
                <div class='credential-row'>
                    <span class='credential-label'>Role:</span>
                    <span class='credential-value'>$role</span>
                </div>
            </div>
            
            <div class='warning'>
                <strong>⚠️ Important:</strong>
                <ul>
                    <li>You will be required to change your password on first login</li>
                    <li>Keep your credentials secure and do not share them</li>
                    <li>Use the email and password above to log in</li>
                </ul>
            </div>
            
            <p style='text-align: center;'>
                <a href='$login_url' class='button'>Log In Now</a>
            </p>
            
            <p>If you have any questions or need assistance, please contact the Super Admin or hotel management.</p>
            
            <p>Best regards,<br>
            <strong>Harar Ras Hotel Management System</strong></p>
        </div>
        <div class='footer'>
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; $year Harar Ras Hotel. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";
        
        return send_email($user['email'], $subject, $message);
    }
    
    return false;
}
