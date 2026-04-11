<?php
/**
 * Payment Gateway Service
 * Handles transaction ID verification across multiple payment gateways
 */

class PaymentGatewayService {
    private $conn;
    private $config;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->loadGatewayConfigs();
    }
    
    /**
     * Load payment gateway configurations from database
     */
    private function loadGatewayConfigs() {
        $query = "SELECT * FROM payment_gateway_config WHERE is_active = TRUE";
        $result = $this->conn->query($query);
        
        $this->config = [];
        while ($row = $result->fetch_assoc()) {
            $this->config[$row['gateway_name']] = $row;
        }
    }
    
    /**
     * Verify transaction ID
     * @param string $transaction_id
     * @param float $expected_amount
     * @param int $booking_id
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function verifyTransaction($transaction_id, $expected_amount, $booking_id) {
        // Sanitize input
        $transaction_id = trim($transaction_id);
        
        // Step 1: Check if transaction ID already exists (fraud prevention)
        if ($this->isDuplicateTransaction($transaction_id, $booking_id)) {
            $this->logFraud($transaction_id, $booking_id, 'duplicate_transaction');
            return [
                'success' => false,
                'message' => 'This transaction ID has already been used. Please contact support if this is an error.',
                'error_code' => 'DUPLICATE_TRANSACTION'
            ];
        }
        
        // Step 2: Detect gateway from transaction ID format
        $gateway = $this->detectGateway($transaction_id);
        
        if (!$gateway) {
            return [
                'success' => false,
                'message' => 'Invalid transaction ID format. Please check and try again.',
                'error_code' => 'INVALID_FORMAT'
            ];
        }
        
        // Step 3: Validate format
        if (!$this->validateFormat($transaction_id, $gateway)) {
            return [
                'success' => false,
                'message' => 'Transaction ID format is invalid for ' . strtoupper($gateway) . '. Please verify and try again.',
                'error_code' => 'FORMAT_VALIDATION_FAILED'
            ];
        }
        
        // Step 4: Verify with gateway API (if available)
        $verification_result = $this->verifyWithGatewayAPI($transaction_id, $gateway, $expected_amount);
        
        // Step 5: Log verification attempt
        $this->logVerificationAttempt($booking_id, $transaction_id, $verification_result, $gateway);
        
        return $verification_result;
    }
    
    /**
     * Check if transaction ID is duplicate
     */
    private function isDuplicateTransaction($transaction_id, $current_booking_id) {
        $query = "SELECT id, booking_id FROM bookings 
                  WHERE transaction_id = ? AND id != ? AND transaction_verified = TRUE";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $transaction_id, $current_booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * Detect payment gateway from transaction ID
     */
    private function detectGateway($transaction_id) {
        foreach ($this->config as $gateway_name => $config) {
            $prefix = $config['transaction_prefix'];
            if (stripos($transaction_id, $prefix) === 0) {
                return $gateway_name;
            }
        }
        
        // Default to manual verification if no prefix matches
        return 'manual';
    }
    
    /**
     * Validate transaction ID format
     */
    private function validateFormat($transaction_id, $gateway) {
        if (!isset($this->config[$gateway])) {
            return false;
        }
        
        $config = $this->config[$gateway];
        $length = strlen($transaction_id);
        
        // Check length
        if ($length < $config['min_transaction_length'] || $length > $config['max_transaction_length']) {
            return false;
        }
        
        // Check prefix
        if ($config['transaction_prefix'] && stripos($transaction_id, $config['transaction_prefix']) !== 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify with gateway API
     */
    private function verifyWithGatewayAPI($transaction_id, $gateway, $expected_amount) {
        $config = $this->config[$gateway] ?? null;
        
        if (!$config || !$config['api_key']) {
            // No API configured - use manual verification
            return $this->manualVerification($transaction_id, $gateway, $expected_amount);
        }
        
        // Call specific gateway API
        switch ($gateway) {
            case 'telebirr':
                return $this->verifyTelebirr($transaction_id, $expected_amount, $config);
            
            case 'cbe_birr':
                return $this->verifyCBEBirr($transaction_id, $expected_amount, $config);
            
            case 'stripe':
                return $this->verifyStripe($transaction_id, $expected_amount, $config);
            
            case 'paypal':
                return $this->verifyPayPal($transaction_id, $expected_amount, $config);
            
            default:
                return $this->manualVerification($transaction_id, $gateway, $expected_amount);
        }
    }
    
    /**
     * Manual verification (when API not available)
     */
    private function manualVerification($transaction_id, $gateway, $expected_amount) {
        return [
            'success' => true,
            'message' => 'Transaction ID received. Payment will be verified manually by our staff within 30 minutes.',
            'verified' => false,
            'requires_manual_review' => true,
            'gateway' => $gateway,
            'transaction_id' => $transaction_id,
            'expected_amount' => $expected_amount
        ];
    }
    
    /**
     * Verify Telebirr transaction
     */
    private function verifyTelebirr($transaction_id, $expected_amount, $config) {
        // Telebirr API integration
        // This is a placeholder - implement actual Telebirr API call
        
        if ($config['is_test_mode']) {
            // Test mode - simulate verification
            return [
                'success' => true,
                'message' => 'Payment verified successfully via Telebirr (Test Mode)',
                'verified' => true,
                'gateway' => 'telebirr',
                'transaction_id' => $transaction_id,
                'amount' => $expected_amount,
                'transaction_date' => date('Y-m-d H:i:s')
            ];
        }
        
        // Production API call would go here
        return $this->manualVerification($transaction_id, 'telebirr', $expected_amount);
    }
    
    /**
     * Verify CBE Birr transaction
     */
    private function verifyCBEBirr($transaction_id, $expected_amount, $config) {
        // CBE Birr API integration placeholder
        
        if ($config['is_test_mode']) {
            return [
                'success' => true,
                'message' => 'Payment verified successfully via CBE Birr (Test Mode)',
                'verified' => true,
                'gateway' => 'cbe_birr',
                'transaction_id' => $transaction_id,
                'amount' => $expected_amount,
                'transaction_date' => date('Y-m-d H:i:s')
            ];
        }
        
        return $this->manualVerification($transaction_id, 'cbe_birr', $expected_amount);
    }
    
    /**
     * Verify Stripe payment
     */
    private function verifyStripe($transaction_id, $expected_amount, $config) {
        if (!$config['api_key']) {
            return $this->manualVerification($transaction_id, 'stripe', $expected_amount);
        }
        
        try {
            // Stripe API call
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_intents/" . $transaction_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $config['api_key']
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                $data = json_decode($response, true);
                
                if ($data['status'] == 'succeeded') {
                    $amount_received = $data['amount'] / 100; // Stripe uses cents
                    
                    if (abs($amount_received - $expected_amount) < 0.01) {
                        return [
                            'success' => true,
                            'message' => 'Payment verified successfully via Stripe',
                            'verified' => true,
                            'gateway' => 'stripe',
                            'transaction_id' => $transaction_id,
                            'amount' => $amount_received,
                            'transaction_date' => date('Y-m-d H:i:s', $data['created'])
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Amount mismatch. Expected: ' . $expected_amount . ', Received: ' . $amount_received,
                            'error_code' => 'AMOUNT_MISMATCH'
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Stripe verification error: " . $e->getMessage());
        }
        
        return $this->manualVerification($transaction_id, 'stripe', $expected_amount);
    }
    
    /**
     * Verify PayPal transaction
     */
    private function verifyPayPal($transaction_id, $expected_amount, $config) {
        // PayPal API integration placeholder
        return $this->manualVerification($transaction_id, 'paypal', $expected_amount);
    }
    
    /**
     * Log verification attempt
     */
    private function logVerificationAttempt($booking_id, $transaction_id, $result, $gateway) {
        $status = $result['verified'] ?? false ? 'verified' : 'pending';
        if (!$result['success']) {
            $status = 'failed';
        }
        
        $query = "INSERT INTO transaction_verification_log 
                  (booking_id, transaction_id, verification_status, verification_method, 
                   amount, gateway_response, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $amount = $result['amount'] ?? null;
        $gateway_response = json_encode($result);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param("isssdsss", $booking_id, $transaction_id, $status, $gateway, 
                         $amount, $gateway_response, $ip, $user_agent);
        $stmt->execute();
    }
    
    /**
     * Log fraud attempt
     */
    private function logFraud($transaction_id, $booking_id, $fraud_type) {
        $query = "INSERT INTO fraud_detection_log 
                  (transaction_id, booking_id, user_id, fraud_type, risk_score, 
                   ip_address, user_agent, action_taken) 
                  VALUES (?, ?, ?, ?, 100, ?, ?, 'blocked')";
        
        $stmt = $this->conn->prepare($query);
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param("siisss", $transaction_id, $booking_id, $user_id, 
                         $fraud_type, $ip, $user_agent);
        $stmt->execute();
    }
}
