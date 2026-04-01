<?php
/**
 * Room Availability Service
 * Prevents double booking and manages room holds
 * ONE PLACE for all room availability logic
 */

class RoomAvailabilityService {
    private $conn;
    private $hold_duration_minutes = 30; // Default: 30 minutes hold time
    
    public function __construct($db_connection, $hold_duration = 30) {
        $this->conn = $db_connection;
        $this->hold_duration_minutes = $hold_duration;
    }
    
    /**
     * MAIN FUNCTION: Check if room is available for booking
     * This is the ONE function used everywhere
     * 
     * @param int $room_id - Room ID to check
     * @param string $check_in_date - Check-in date (Y-m-d format)
     * @param string $check_out_date - Check-out date (Y-m-d format)
     * @param int $exclude_booking_id - Exclude this booking ID (for updates)
     * @return array - Availability status and details
     */
    public function checkRoomAvailability($room_id, $check_in_date, $check_out_date, $exclude_booking_id = null) {
        // Step 1: Validate dates
        $validation = $this->validateDates($check_in_date, $check_out_date);
        if (!$validation['valid']) {
            return [
                'available' => false,
                'reason' => $validation['message'],
                'error_code' => 'INVALID_DATES'
            ];
        }
        
        // Step 2: Check if room exists and is active
        $room = $this->getRoomDetails($room_id);
        if (!$room) {
            return [
                'available' => false,
                'reason' => 'Room not found',
                'error_code' => 'ROOM_NOT_FOUND'
            ];
        }
        
        if ($room['status'] === 'maintenance' || $room['status'] === 'inactive') {
            return [
                'available' => false,
                'reason' => 'Room is currently unavailable (under maintenance)',
                'error_code' => 'ROOM_MAINTENANCE'
            ];
        }
        
        // Step 3: Expire old pending bookings first
        $this->expirePendingBookings();
        
        // Step 4: Check for overlapping bookings
        $overlapping = $this->getOverlappingBookings($room_id, $check_in_date, $check_out_date, $exclude_booking_id);
        
        if (!empty($overlapping)) {
            $blocking_booking = $overlapping[0];
            
            // Determine the reason
            if ($blocking_booking['status'] === 'pending') {
                return [
                    'available' => false,
                    'reason' => 'This room is currently on hold (waiting for approval). Please choose another room or try again later.',
                    'error_code' => 'ROOM_ON_HOLD',
                    'blocking_booking' => [
                        'booking_reference' => $blocking_booking['booking_reference'],
                        'status' => $blocking_booking['status'],
                        'check_in' => $blocking_booking['check_in_date'],
                        'check_out' => $blocking_booking['check_out_date'],
                        'expires_at' => $blocking_booking['booking_hold_expires_at']
                    ]
                ];
            } else {
                return [
                    'available' => false,
                    'reason' => 'This room is already booked for the selected dates. Please choose different dates or another room.',
                    'error_code' => 'ROOM_BOOKED',
                    'blocking_booking' => [
                        'booking_reference' => $blocking_booking['booking_reference'],
                        'status' => $blocking_booking['status'],
                        'check_in' => $blocking_booking['check_in_date'],
                        'check_out' => $blocking_booking['check_out_date']
                    ]
                ];
            }
        }
        
        // Step 5: Room is available!
        return [
            'available' => true,
            'reason' => 'Room is available for booking',
            'room' => $room,
            'hold_duration_minutes' => $this->hold_duration_minutes
        ];
    }
    
    /**
     * Create booking with automatic hold/expiration
     */
    public function createBookingWithHold($booking_data) {
        // Check availability first
        $availability = $this->checkRoomAvailability(
            $booking_data['room_id'],
            $booking_data['check_in_date'],
            $booking_data['check_out_date']
        );
        
        if (!$availability['available']) {
            return [
                'success' => false,
                'message' => $availability['reason'],
                'error_code' => $availability['error_code']
            ];
        }
        
        // Calculate hold expiration time
        $hold_expires_at = date('Y-m-d H:i:s', strtotime("+{$this->hold_duration_minutes} minutes"));
        
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Insert booking with hold
            $query = "INSERT INTO bookings (
                        user_id, customer_name, customer_email, customer_phone,
                        room_id, booking_reference, check_in_date, check_out_date,
                        customers, total_price, status, payment_status,
                        booking_hold_expires_at, special_requests, created_at
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("isssisssidss",
                $booking_data['user_id'],
                $booking_data['customer_name'],
                $booking_data['customer_email'],
                $booking_data['customer_phone'],
                $booking_data['room_id'],
                $booking_data['booking_reference'],
                $booking_data['check_in_date'],
                $booking_data['check_out_date'],
                $booking_data['customers'],
                $booking_data['total_price'],
                $hold_expires_at,
                $booking_data['special_requests'] ?? ''
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create booking');
            }
            
            $booking_id = $stmt->insert_id;
            
            // Log activity
            $this->logBookingActivity($booking_id, 'created', 'Booking created with hold until ' . $hold_expires_at);
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Booking created successfully. Please complete payment within ' . $this->hold_duration_minutes . ' minutes.',
                'booking_id' => $booking_id,
                'booking_reference' => $booking_data['booking_reference'],
                'hold_expires_at' => $hold_expires_at,
                'hold_duration_minutes' => $this->hold_duration_minutes
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to create booking: ' . $e->getMessage(),
                'error_code' => 'BOOKING_CREATION_FAILED'
            ];
        }
    }
    
    /**
     * Validate check-in and check-out dates
     */
    private function validateDates($check_in_date, $check_out_date) {
        $check_in = strtotime($check_in_date);
        $check_out = strtotime($check_out_date);
        $today = strtotime(date('Y-m-d'));
        
        if (!$check_in || !$check_out) {
            return [
                'valid' => false,
                'message' => 'Invalid date format. Please use YYYY-MM-DD format.'
            ];
        }
        
        if ($check_in < $today) {
            return [
                'valid' => false,
                'message' => 'Check-in date cannot be in the past.'
            ];
        }
        
        if ($check_out <= $check_in) {
            return [
                'valid' => false,
                'message' => 'Check-out date must be after check-in date.'
            ];
        }
        
        $nights = ($check_out - $check_in) / (60 * 60 * 24);
        if ($nights > 365) {
            return [
                'valid' => false,
                'message' => 'Maximum booking duration is 365 days.'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Get room details
     */
    private function getRoomDetails($room_id) {
        $query = "SELECT * FROM rooms WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get overlapping bookings (CRITICAL FUNCTION)
     * This prevents double booking
     */
    private function getOverlappingBookings($room_id, $check_in_date, $check_out_date, $exclude_booking_id = null) {
        $query = "SELECT 
                    id, booking_reference, status, check_in_date, check_out_date,
                    booking_hold_expires_at, is_expired
                  FROM bookings
                  WHERE room_id = ?
                  AND status IN ('pending', 'confirmed', 'checked_in', 'pending_payment', 'pending_verification', 'verified')
                  AND is_expired = FALSE
                  AND NOT (check_out_date <= ? OR check_in_date >= ?)";
        
        if ($exclude_booking_id) {
            $query .= " AND id != ?";
        }
        
        $query .= " ORDER BY created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        
        if ($exclude_booking_id) {
            $stmt->bind_param("issi", $room_id, $check_in_date, $check_out_date, $exclude_booking_id);
        } else {
            $stmt->bind_param("iss", $room_id, $check_in_date, $check_out_date);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Expire pending bookings that have passed their hold time
     */
    public function expirePendingBookings() {
        $query = "UPDATE bookings 
                  SET 
                    status = 'cancelled',
                    is_expired = TRUE,
                    auto_expired_at = NOW()
                  WHERE 
                    status = 'pending'
                    AND booking_hold_expires_at < NOW()
                    AND is_expired = FALSE";
        
        $this->conn->query($query);
        
        return $this->conn->affected_rows;
    }
    
    /**
     * Get room services
     */
    public function getRoomServices($room_id) {
        $query = "SELECT 
                    service_name, service_icon, service_category, display_order
                  FROM room_services
                  WHERE room_id = ?
                  ORDER BY display_order ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get available rooms for date range
     */
    public function getAvailableRooms($check_in_date, $check_out_date, $room_type = null) {
        // Expire old bookings first
        $this->expirePendingBookings();
        
        $query = "SELECT 
                    r.*,
                    COUNT(DISTINCT rs.id) as service_count,
                    GROUP_CONCAT(DISTINCT rs.service_name ORDER BY rs.display_order SEPARATOR '|') as services,
                    GROUP_CONCAT(DISTINCT rs.service_icon ORDER BY rs.display_order SEPARATOR '|') as service_icons
                  FROM rooms r
                  LEFT JOIN room_services rs ON r.id = rs.room_id
                  WHERE r.status = 'active'
                  AND r.id NOT IN (
                      SELECT DISTINCT room_id 
                      FROM bookings 
                      WHERE status IN ('pending', 'confirmed', 'checked_in', 'pending_payment', 'pending_verification', 'verified')
                      AND is_expired = FALSE
                      AND NOT (check_out_date <= ? OR check_in_date >= ?)
                  )";
        
        if ($room_type) {
            $query .= " AND r.room_type = ?";
        }
        
        $query .= " GROUP BY r.id ORDER BY r.price ASC";
        
        $stmt = $this->conn->prepare($query);
        
        if ($room_type) {
            $stmt->bind_param("sss", $check_in_date, $check_out_date, $room_type);
        } else {
            $stmt->bind_param("ss", $check_in_date, $check_out_date);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rooms = [];
        while ($row = $result->fetch_assoc()) {
            // Parse services
            if ($row['services']) {
                $row['services_array'] = explode('|', $row['services']);
                $row['service_icons_array'] = explode('|', $row['service_icons']);
            } else {
                $row['services_array'] = [];
                $row['service_icons_array'] = [];
            }
            $rooms[] = $row;
        }
        
        return $rooms;
    }
    
    /**
     * Approve booking (releases hold, confirms booking)
     */
    public function approveBooking($booking_id, $approved_by) {
        $query = "UPDATE bookings 
                  SET 
                    status = 'confirmed',
                    verification_status = 'verified',
                    verified_by = ?,
                    verified_at = NOW(),
                    booking_hold_expires_at = NULL
                  WHERE id = ? AND status = 'pending'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $approved_by, $booking_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $this->logBookingActivity($booking_id, 'confirmed', 'Booking approved and confirmed');
            return ['success' => true, 'message' => 'Booking approved successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to approve booking'];
    }
    
    /**
     * Reject booking (releases room)
     */
    public function rejectBooking($booking_id, $rejected_by, $reason) {
        $query = "UPDATE bookings 
                  SET 
                    status = 'cancelled',
                    verification_status = 'rejected',
                    verified_by = ?,
                    verified_at = NOW(),
                    rejection_reason = ?,
                    booking_hold_expires_at = NULL
                  WHERE id = ? AND status = 'pending'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isi", $rejected_by, $reason, $booking_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $this->logBookingActivity($booking_id, 'cancelled', 'Booking rejected: ' . $reason);
            return ['success' => true, 'message' => 'Booking rejected successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to reject booking'];
    }
    
    /**
     * Log booking activity
     */
    private function logBookingActivity($booking_id, $activity_type, $description) {
        $query = "INSERT INTO booking_activity_log 
                  (booking_id, activity_type, description, created_at) 
                  VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iss", $booking_id, $activity_type, $description);
        $stmt->execute();
    }
    
    /**
     * Get booking hold status
     */
    public function getBookingHoldStatus($booking_id) {
        $query = "SELECT 
                    id, booking_reference, status, booking_hold_expires_at, is_expired,
                    TIMESTAMPDIFF(MINUTE, NOW(), booking_hold_expires_at) as minutes_remaining
                  FROM bookings
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $booking = $result->fetch_assoc();
        
        if (!$booking) {
            return null;
        }
        
        $booking['is_hold_active'] = $booking['status'] === 'pending' && 
                                      $booking['minutes_remaining'] > 0 && 
                                      !$booking['is_expired'];
        
        return $booking;
    }
}
