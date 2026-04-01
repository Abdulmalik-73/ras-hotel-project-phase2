<?php
/**
 * Refund Service
 * Centralized refund calculation and management
 * ONE PLACE for all refund logic
 */

class RefundService {
    private $conn;
    
    // Refund policy configuration (can be loaded from database)
    private $refundPolicies = [
        ['min_days' => 7, 'max_days' => null, 'percentage' => 95, 'name' => 'Early Cancellation'],
        ['min_days' => 3, 'max_days' => 6, 'percentage' => 75, 'name' => 'Moderate Cancellation'],
        ['min_days' => 1, 'max_days' => 2, 'percentage' => 50, 'name' => 'Late Cancellation'],
        ['min_days' => 0, 'max_days' => 0, 'percentage' => 25, 'name' => 'Same Day Cancellation'],
        ['min_days' => -999, 'max_days' => -1, 'percentage' => 0, 'name' => 'No Refund']
    ];
    
    private $processingFeePercentage = 5.0; // 5% processing fee
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->loadRefundPolicies();
    }
    
    /**
     * Load refund policies from database
     */
    private function loadRefundPolicies() {
        $query = "SELECT * FROM refund_policy WHERE is_active = TRUE ORDER BY display_order";
        $result = $this->conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $this->refundPolicies = [];
            while ($row = $result->fetch_assoc()) {
                $this->refundPolicies[] = [
                    'min_days' => $row['min_days_before'],
                    'max_days' => $row['max_days_before'],
                    'percentage' => $row['refund_percentage'],
                    'name' => $row['policy_name'],
                    'processing_fee' => $row['processing_fee_percentage']
                ];
            }
        }
    }
    
    /**
     * MAIN FUNCTION: Calculate refund amount
     * This is the ONE function used everywhere
     * 
     * @param string $checkInDate - Check-in date (Y-m-d format)
     * @param string $cancelDate - Cancellation date (Y-m-d H:i:s format)
     * @param float $totalAmount - Total booking amount
     * @return array - Complete refund calculation details
     */
    public function calculateRefund($checkInDate, $cancelDate, $totalAmount) {
        // Calculate days before check-in
        $checkIn = new DateTime($checkInDate);
        $cancel = new DateTime($cancelDate);
        $daysBeforeCheckin = $checkIn->diff($cancel)->days;
        
        // If cancellation is after check-in, days will be negative
        if ($cancel > $checkIn) {
            $daysBeforeCheckin = -$daysBeforeCheckin;
        }
        
        // Get refund percentage based on policy
        $refundPercentage = $this->getRefundPercentage($daysBeforeCheckin);
        
        // Calculate refund amount
        $refundAmount = $totalAmount * ($refundPercentage / 100);
        
        // Calculate processing fee
        $processingFee = $refundAmount * ($this->processingFeePercentage / 100);
        
        // Calculate final refund
        $finalRefund = $refundAmount - $processingFee;
        
        // Determine refund status
        $refundStatus = $this->getRefundStatus($refundPercentage, $daysBeforeCheckin);
        
        // Get policy name
        $policyName = $this->getPolicyName($daysBeforeCheckin);
        
        return [
            'days_before_checkin' => $daysBeforeCheckin,
            'refund_percentage' => $refundPercentage,
            'refund_amount' => round($refundAmount, 2),
            'processing_fee' => round($processingFee, 2),
            'processing_fee_percentage' => $this->processingFeePercentage,
            'final_refund' => round($finalRefund, 2),
            'refund_status' => $refundStatus,
            'policy_name' => $policyName,
            'is_refundable' => $refundPercentage > 0,
            'original_amount' => $totalAmount,
            'check_in_date' => $checkInDate,
            'cancellation_date' => $cancelDate
        ];
    }
    
    /**
     * Get refund percentage based on days before check-in
     */
    private function getRefundPercentage($daysBeforeCheckin) {
        foreach ($this->refundPolicies as $policy) {
            $minDays = $policy['min_days'];
            $maxDays = $policy['max_days'];
            
            if ($maxDays === null) {
                // No upper limit (e.g., 7+ days)
                if ($daysBeforeCheckin >= $minDays) {
                    return $policy['percentage'];
                }
            } else {
                // Range (e.g., 3-6 days)
                if ($daysBeforeCheckin >= $minDays && $daysBeforeCheckin <= $maxDays) {
                    return $policy['percentage'];
                }
            }
        }
        
        return 0; // No refund by default
    }
    
    /**
     * Get policy name based on days before check-in
     */
    private function getPolicyName($daysBeforeCheckin) {
        foreach ($this->refundPolicies as $policy) {
            $minDays = $policy['min_days'];
            $maxDays = $policy['max_days'];
            
            if ($maxDays === null) {
                if ($daysBeforeCheckin >= $minDays) {
                    return $policy['name'];
                }
            } else {
                if ($daysBeforeCheckin >= $minDays && $daysBeforeCheckin <= $maxDays) {
                    return $policy['name'];
                }
            }
        }
        
        return 'No Refund Policy';
    }
    
    /**
     * Get refund status text
     */
    private function getRefundStatus($refundPercentage, $daysBeforeCheckin) {
        if ($refundPercentage == 0) {
            if ($daysBeforeCheckin < 0) {
                return 'Not Eligible - Past Check-in Date';
            }
            return 'Not Eligible - No Refund Policy';
        }
        
        return 'Pending';
    }
    
    /**
     * Process booking cancellation and create refund
     * 
     * @param int $bookingId
     * @param int $userId
     * @param string $reason
     * @return array - Result with success status and refund details
     */
    public function cancelBookingAndCreateRefund($bookingId, $userId, $reason = '') {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Get booking details
            $booking = $this->getBookingDetails($bookingId, $userId);
            
            if (!$booking) {
                throw new Exception('Booking not found or access denied');
            }
            
            // Check if booking can be cancelled
            if (!$this->canCancelBooking($booking)) {
                throw new Exception('This booking cannot be cancelled');
            }
            
            // Calculate refund
            $cancellationDate = date('Y-m-d H:i:s');
            $refundCalculation = $this->calculateRefund(
                $booking['check_in_date'],
                $cancellationDate,
                $booking['total_price']
            );
            
            // Update booking status
            $this->updateBookingToCancelled($bookingId, $userId, $cancellationDate, $reason, $refundCalculation);
            
            // Create refund record (if eligible)
            $refundId = null;
            if ($refundCalculation['is_refundable']) {
                $refundId = $this->createRefundRecord($booking, $refundCalculation, $reason);
            }
            
            // Log cancellation
            $this->logCancellation($booking, $userId, $refundCalculation, $reason);
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'refund_calculation' => $refundCalculation,
                'refund_id' => $refundId,
                'booking_id' => $bookingId
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get booking details
     */
    private function getBookingDetails($bookingId, $userId) {
        $query = "SELECT b.*, 
                  CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                  u.email as customer_email
                  FROM bookings b
                  JOIN users u ON b.user_id = u.id
                  WHERE b.id = ? AND b.user_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $bookingId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Check if booking can be cancelled
     */
    private function canCancelBooking($booking) {
        // Check status
        $cancellableStatuses = ['pending', 'confirmed', 'pending_payment', 'pending_verification', 'verified'];
        if (!in_array($booking['status'], $cancellableStatuses)) {
            return false;
        }
        
        // Already cancelled
        if ($booking['status'] == 'cancelled') {
            return false;
        }
        
        // Can cancel even after check-in date (but with 0% refund)
        return true;
    }
    
    /**
     * Update booking to cancelled status
     */
    private function updateBookingToCancelled($bookingId, $userId, $cancellationDate, $reason, $refundCalc) {
        $query = "UPDATE bookings SET 
                  status = 'cancelled',
                  cancellation_date = ?,
                  cancelled_by = ?,
                  cancellation_reason = ?,
                  days_before_checkin = ?,
                  is_refundable = ?,
                  cancellation_ip = ?,
                  cancellation_user_agent = ?
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isRefundable = $refundCalc['is_refundable'] ? 1 : 0;
        
        $stmt->bind_param("sisssssi", 
            $cancellationDate, 
            $userId, 
            $reason, 
            $refundCalc['days_before_checkin'],
            $isRefundable,
            $ip,
            $userAgent,
            $bookingId
        );
        
        $stmt->execute();
    }
    
    /**
     * Create refund record
     */
    private function createRefundRecord($booking, $refundCalc, $reason) {
        $refundReference = 'REF-' . $booking['booking_reference'] . '-' . date('YmdHis');
        
        $query = "INSERT INTO refunds (
                    booking_id, booking_reference, customer_id, customer_name, customer_email,
                    original_amount, check_in_date, cancellation_date, days_before_checkin,
                    refund_percentage, refund_amount, processing_fee, processing_fee_percentage,
                    final_refund, refund_status, refund_reference, original_transaction_id
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isississiddddssss",
            $booking['id'],
            $booking['booking_reference'],
            $booking['user_id'],
            $booking['customer_name'],
            $booking['customer_email'],
            $refundCalc['original_amount'],
            $refundCalc['check_in_date'],
            $refundCalc['cancellation_date'],
            $refundCalc['days_before_checkin'],
            $refundCalc['refund_percentage'],
            $refundCalc['refund_amount'],
            $refundCalc['processing_fee'],
            $refundCalc['processing_fee_percentage'],
            $refundCalc['final_refund'],
            $refundCalc['refund_status'],
            $refundReference,
            $booking['transaction_id'] ?? null
        );
        
        $stmt->execute();
        return $stmt->insert_id;
    }
    
    /**
     * Log cancellation to booking_cancellations table
     */
    private function logCancellation($booking, $userId, $refundCalc, $reason) {
        $query = "INSERT INTO booking_cancellations (
                    booking_id, booking_reference, cancelled_by, cancellation_date,
                    cancellation_reason, check_in_date, check_out_date, total_amount,
                    days_before_checkin, is_refundable, refund_percentage, estimated_refund,
                    ip_address, user_agent
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isRefundable = $refundCalc['is_refundable'] ? 1 : 0;
        
        $stmt->bind_param("isissssdiiddss",
            $booking['id'],
            $booking['booking_reference'],
            $userId,
            $refundCalc['cancellation_date'],
            $reason,
            $booking['check_in_date'],
            $booking['check_out_date'],
            $refundCalc['original_amount'],
            $refundCalc['days_before_checkin'],
            $isRefundable,
            $refundCalc['refund_percentage'],
            $refundCalc['final_refund'],
            $ip,
            $userAgent
        );
        
        $stmt->execute();
    }
    
    /**
     * Get refund details by booking ID
     */
    public function getRefundByBookingId($bookingId) {
        $query = "SELECT * FROM refunds WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Approve refund (admin action)
     */
    public function approveRefund($refundId, $adminId, $notes = '') {
        $query = "UPDATE refunds SET 
                  refund_status = 'Approved',
                  processed_by = ?,
                  processed_at = NOW(),
                  admin_notes = ?
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isi", $adminId, $notes, $refundId);
        
        return $stmt->execute();
    }
    
    /**
     * Reject refund (admin action)
     */
    public function rejectRefund($refundId, $adminId, $reason, $notes = '') {
        $query = "UPDATE refunds SET 
                  refund_status = 'Rejected',
                  processed_by = ?,
                  processed_at = NOW(),
                  rejection_reason = ?,
                  admin_notes = ?
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("issi", $adminId, $reason, $notes, $refundId);
        
        return $stmt->execute();
    }
    
    /**
     * Mark refund as completed
     */
    public function completeRefund($refundId, $refundTransactionId, $refundMethod) {
        $query = "UPDATE refunds SET 
                  refund_status = 'Completed',
                  refund_transaction_id = ?,
                  refund_method = ?
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $refundTransactionId, $refundMethod, $refundId);
        
        return $stmt->execute();
    }
    
    /**
     * Get all pending refunds (for admin)
     */
    public function getPendingRefunds() {
        $query = "SELECT * FROM pending_refunds ORDER BY created_at DESC";
        $result = $this->conn->query($query);
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get refund policies (for display)
     */
    public function getRefundPolicies() {
        return $this->refundPolicies;
    }
}
