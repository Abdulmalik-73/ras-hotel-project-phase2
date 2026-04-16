<?php
/**
 * Chapa Payment Gateway Service
 * Handles payment initialization, verification, and webhooks for Chapa
 */

class ChapaPaymentService {
    private $secret_key;
    private $public_key;
    private $test_mode;
    private $callback_url;
    private $return_url;
    private $base_url;
    
    public function __construct() {
        // Try getenv first, then fall back to defined constants
        $this->secret_key = getenv('CHAPA_SECRET_KEY') ?: (defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '');
        $this->public_key = getenv('CHAPA_PUBLIC_KEY') ?: (defined('CHAPA_PUBLIC_KEY') ? CHAPA_PUBLIC_KEY : '');
        $this->test_mode = getenv('CHAPA_TEST_MODE') === 'true' || (defined('CHAPA_TEST_MODE') && CHAPA_TEST_MODE === 'true');
        $this->callback_url = getenv('CHAPA_CALLBACK_URL') ?: (defined('CHAPA_CALLBACK_URL') ? CHAPA_CALLBACK_URL : '');
        $this->return_url = getenv('CHAPA_RETURN_URL') ?: (defined('CHAPA_RETURN_URL') ? CHAPA_RETURN_URL : '');
        
        // Set base URL based on test mode
        $this->base_url = $this->test_mode 
            ? 'https://api.chapa.co/v1' 
            : 'https://api.chapa.co/v1';
    }
    
    /**
     * Initialize payment and get checkout URL
     * 
     * @param array $payment_data Payment information
     * @return array Result with checkout_url or error
     */
    public function initializePayment($payment_data) {
        if (empty($this->secret_key)) {
            return [
                'success' => false,
                'message' => 'Chapa is not configured. Please contact administrator.'
            ];
        }
        
        // Generate unique transaction reference
        $tx_ref = 'HRH-' . time() . '-' . uniqid();
        
        // Prepare payment data
        $data = [
            'amount' => $payment_data['amount'],
            'currency' => 'ETB',
            'email' => $payment_data['email'],
            'first_name' => $payment_data['first_name'],
            'last_name' => $payment_data['last_name'],
            'phone_number' => $payment_data['phone'] ?? '',
            'tx_ref' => $tx_ref,
            'callback_url' => $this->callback_url,
            'return_url' => $this->return_url . '?tx_ref=' . $tx_ref,
            'customization' => [
                'title' => 'Harar Ras Hotel',
                'description' => $payment_data['description'] ?? 'Hotel Booking Payment'
            ]
        ];
        
        // Add optional metadata
        if (isset($payment_data['booking_id'])) {
            $data['customization']['booking_id'] = $payment_data['booking_id'];
        }
        
        // Make API request
        $response = $this->makeRequest('POST', '/transaction/initialize', $data);
        
        if ($response['success'] && isset($response['data']['checkout_url'])) {
            return [
                'success' => true,
                'checkout_url' => $response['data']['checkout_url'],
                'tx_ref' => $tx_ref,
                'message' => 'Payment initialized successfully'
            ];
        }
        
        return [
            'success' => false,
            'message' => $response['message'] ?? 'Failed to initialize payment'
        ];
    }
    
    /**
     * Verify payment status
     * 
     * @param string $tx_ref Transaction reference
     * @return array Verification result
     */
    public function verifyPayment($tx_ref) {
        if (empty($this->secret_key)) {
            return [
                'success' => false,
                'verified' => false,
                'message' => 'Chapa is not configured'
            ];
        }
        
        $response = $this->makeRequest('GET', '/transaction/verify/' . $tx_ref);
        
        if ($response['success'] && isset($response['data'])) {
            $data = $response['data'];
            
            // Check if payment is successful
            if ($data['status'] === 'success') {
                return [
                    'success' => true,
                    'verified' => true,
                    'transaction_id' => $data['reference'] ?? $tx_ref,
                    'amount' => $data['amount'] ?? 0,
                    'currency' => $data['currency'] ?? 'ETB',
                    'payment_method' => $data['payment_method'] ?? 'chapa',
                    'message' => 'Payment verified successfully',
                    'data' => $data
                ];
            } elseif ($data['status'] === 'pending') {
                return [
                    'success' => true,
                    'verified' => 'pending',
                    'message' => 'Payment is pending'
                ];
            } else {
                return [
                    'success' => false,
                    'verified' => false,
                    'message' => 'Payment failed or cancelled'
                ];
            }
        }
        
        return [
            'success' => false,
            'verified' => false,
            'message' => $response['message'] ?? 'Failed to verify payment'
        ];
    }
    
    /**
     * Make HTTP request to Chapa API
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response
     */
    private function makeRequest($method, $endpoint, $data = []) {
        $url = $this->base_url . $endpoint;
        
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->secret_key,
            'Content-Type: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->test_mode);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 second connection timeout
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            error_log("Chapa API Error: " . $error);
            return [
                'success' => false,
                'message' => 'Connection error: ' . $error
            ];
        }
        
        $result = json_decode($response, true);
        
        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'data' => $result['data'] ?? $result,
                'message' => $result['message'] ?? 'Success'
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['message'] ?? 'API request failed',
            'data' => $result
        ];
    }
    
    /**
     * Get public key for frontend
     * 
     * @return string Public key
     */
    public function getPublicKey() {
        return $this->public_key;
    }
    
    /**
     * Check if Chapa is configured
     * 
     * @return bool
     */
    public function isConfigured() {
        return !empty($this->secret_key) && !empty($this->public_key);
    }
}
