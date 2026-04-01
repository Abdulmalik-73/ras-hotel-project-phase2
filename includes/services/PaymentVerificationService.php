<?php
/**
 * Payment Verification Service
 * Automatically verifies transaction IDs using payment gateway APIs
 * Supports: Telebirr, CBE Birr, M-Pesa, and other Ethiopian payment gateways
 */

class PaymentVerificationService {
    private $conn;
    private $config;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $this->config = [
            'telebirr' => [
                'api_url' => getenv('TELEBIRR_API_URL') ?: 'https://api.ethiotelecom.et/payment/v1/verify',
                'merchant_id' => getenv('TELEBIRR_MERCHANT_ID') ?: '',
                'api_key' => getenv('TELEBIRR_API_KEY') ?: '',
                'api_secret' => getenv('TELEBIRR_API_SECRET') ?: ''
            ],
            'cbe_birr' => [
                'api_url' => getenv('CBE_BIRR_API_URL') ?: 'https://api.cbe.com.et/birr/v1/verify',
                'merchant_code' => getenv('CBE_MERCHANT_CODE') ?: '',
                'api_key' => getenv('CBE_API_KEY') ?: '',
                'api_secret' => getenv('CBE_API_SECRET') ?: ''
            ],
            'mpesa' => [
                'api_url' => getenv('MPESA_API_URL') ?: 'https://api.safaricom.et/mpesa/v1/query',
                'business_code' => getenv('MPESA_BUSINESS_CODE') ?: '',
                'consumer_key' => getenv('MPESA_CONSUMER_KEY') ?: '',
                'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: ''
            ],
            'amole' => [
                'api_url' => getenv('AMOLE_API_URL') ?: 'https://api.amole.et/v1/verify',
                'merchant_id' => getenv('AMOLE_MERCHANT_ID') ?: '',
                'api_key' => getenv('AMOLE_API_KEY') ?: ''
            ],
            'hellocash' => [
                'api_url' => getenv('HELLOCASH_API_URL') ?: 'https://api.hellocash.net/v1/verify',
                'merchant_id' => getenv('HELLOCASH_MERCHANT_ID') ?: '',
                'api_key' => getenv('HELLOCASH_API_KEY') ?: ''
            ]
        ];
    }
    
    /**
     * Verify transaction ID automatically
     * @param string $transaction_id - Transaction ID from customer
     * @param string $payment_method - Payment method (telebirr, cbe_birr, mpesa, etc.)
     * @param float $expected_amount - Expected payment amount
     * @param string $payment_reference - Booking payment reference
     * @return array - Verification result
     */
    public function verifyTransaction($transaction_id, $payment_method, $expected_amount, $payment_reference) {
        // Detect payment gateway from transaction ID format
        $gateway = $this->detectGateway($transaction_id, $payment_method);
        
        if (!$gateway) {
            return [
                'success' => false,
                'verified' => false,
                'message' => 'Unable to detect payment gateway from transaction ID',
                'error_code' => 'GATEWAY_NOT_DETECTED'
            ];
        }
        
        // Check if gateway is configured
        if (!$this->isGatewayConfigured($gateway)) {
            // Fallback to manual verification
            return [
                'success' => true,
                'verified' => 'pending',
                'message' => 'Payment gateway not configured. Manual verification required.',
                'gateway' => $gateway,
                'requires_manual_verification' => true
            ];
        }
        
        // Verify transaction with gateway API
        try {
            $result = $this->verifyWithGateway($gateway, $transaction_id, $expected_amount, $payment_reference);
            
            // Log verification attempt
            $this->logVerification($transaction_id, $gateway, $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Payment Verification Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'verified' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
                'error_code' => 'VERIFICATION_ERROR',
                'requires_manual_verification' => true
            ];
        }
    }
    
    /**
     * Detect payment gateway from transaction ID format
     */
    private function detectGateway($transaction_id, $payment_method) {
        // Telebirr format: TB-ETH-YYYYMMDD-XXXXXX or TXN followed by numbers
        if (preg_match('/^(TB|TXN|TELEBIRR)/i', $transaction_id)) {
            return 'telebirr';
        }
        
        // CBE Birr format: CBE-YYYY-XXXXXX or starts with CBE
        if (preg_match('/^CBE/i', $transaction_id)) {
            return 'cbe_birr';
        }
        
        // M-Pesa format: Usually starts with MP or contains MPESA
        if (preg_match('/^(MP|MPESA)/i', $transaction_id)) {
            return 'mpesa';
        }
        
        // Amole format: Usually starts with AM or AMOLE
        if (preg_match('/^(AM|AMOLE)/i', $transaction_id)) {
            return 'amole';
        }
        
        // HelloCash format: Usually starts with HC or HELLO
        if (preg_match('/^(HC|HELLO)/i', $transaction_id)) {
            return 'hellocash';
        }
        
        // Fallback to payment method
        $method_lower = strtolower($payment_method);
        if (strpos($method_lower, 'telebirr') !== false) return 'telebirr';
        if (strpos($method_lower, 'cbe') !== false) return 'cbe_birr';
        if (strpos($method_lower, 'mpesa') !== false) return 'mpesa';
        if (strpos($method_lower, 'amole') !== false) return 'amole';
        if (strpos($method_lower, 'hello') !== false) return 'hellocash';
        
        return null;
    }
    
    /**
     * Check if gateway is configured
     */
    private function isGatewayConfigured($gateway) {
        if (!isset($this->config[$gateway])) {
            return false;
        }
        
        $config = $this->config[$gateway];
        
        // Check if required credentials are set
        foreach ($config as $key => $value) {
            if ($key !== 'api_url' && empty($value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verify transaction with specific gateway
     */
    private function verifyWithGateway($gateway, $transaction_id, $expected_amount, $payment_reference) {
        switch ($gateway) {
            case 'telebirr':
                return $this->verifyTelebirr($transaction_id, $expected_amount, $payment_reference);
            
            case 'cbe_birr':
                return $this->verifyCBEBirr($transaction_id, $expected_amount, $payment_reference);
            
            case 'mpesa':
                return $this->verifyMPesa($transaction_id, $expected_amount, $payment_reference);
            
            case 'amole':
                return $this->verifyAmole($transaction_id, $expected_amount, $payment_reference);
            
            case 'hellocash':
                return $this->verifyHelloCash($transaction_id, $expected_amount, $payment_reference);
            
            default:
                throw new Exception("Unsupported gateway: $gateway");
        }
    }
    
    /**
     * Verify Telebirr transaction
     */
    private function verifyTelebirr($transaction_id, $expected_amount, $payment_reference) {
        $config = $this->config['telebirr'];
        
        $payload = [
            'merchantId' => $config['merchant_id'],
            'transactionId' => $transaction_id,
            'referenceNumber' => $payment_reference
        ];
        
        // Generate signature
        $signature = $this->generateTelebirrSignature($payload, $config['api_secret']);
        
        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $config['api_key'],
            'X-Signature: ' . $signature
        ];
        
        $response = $this->makeApiRequest($config['api_url'], $payload, $headers);
        
        return $this->parseTelebirrResponse($response, $expected_amount);
    }
    
    /**
     * Verify CBE Birr transaction
     */
    private function verifyCBEBirr($transaction_id, $expected_amount, $payment_reference) {
        $config = $this->config['cbe_birr'];
        
        $payload = [
            'merchantCode' => $config['merchant_code'],
            'transactionReference' => $transaction_id,
            'orderReference' => $payment_reference
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getCBEAccessToken($config)
        ];
        
        $response = $this->makeApiRequest($config['api_url'], $payload, $headers);
        
        return $this->parseCBEResponse($response, $expected_amount);
    }
    
    /**
     * Verify M-Pesa transaction
     */
    private function verifyMPesa($transaction_id, $expected_amount, $payment_reference) {
        $config = $this->config['mpesa'];
        
        $payload = [
            'BusinessShortCode' => $config['business_code'],
            'TransactionID' => $transaction_id,
            'BillRefNumber' => $payment_reference
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getMPesaAccessToken($config)
        ];
        
        $response = $this->makeApiRequest($config['api_url'], $payload, $headers);
        
        return $this->parseMPesaResponse($response, $expected_amount);
    }
    
    /**
     * Verify Amole transaction
     */
    private function verifyAmole($transaction_id, $expected_amount, $payment_reference) {
        $config = $this->config['amole'];
        
        $payload = [
            'merchantId' => $config['merchant_id'],
            'transactionId' => $transaction_id,
            'reference' => $payment_reference
        ];
        
        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $config['api_key']
        ];
        
        $response = $this->makeApiRequest($config['api_url'], $payload, $headers);
        
        return $this->parseAmoleResponse($response, $expected_amount);
    }
    
    /**
     * Verify HelloCash transaction
     */
    private function verifyHelloCash($transaction_id, $expected_amount, $payment_reference) {
        $config = $this->config['hellocash'];
        
        $payload = [
            'merchantId' => $config['merchant_id'],
            'transactionId' => $transaction_id,
            'invoiceId' => $payment_reference
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: ApiKey ' . $config['api_key']
        ];
        
        $response = $this->makeApiRequest($config['api_url'], $payload, $headers);
        
        return $this->parseHelloCashResponse($response, $expected_amount);
    }
    
    /**
     * Make API request to payment gateway
     */
    private function makeApiRequest($url, $payload, $headers) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("CURL Error: $curl_error");
        }
        
        if ($http_code !== 200) {
            throw new Exception("API returned HTTP $http_code");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Parse Telebirr API response
     */
    private function parseTelebirrResponse($response, $expected_amount) {
        if (!isset($response['status'])) {
            return [
                'success' => false,
                'verified' => false,
                'message' => 'Invalid response from Telebirr API'
            ];
        }
        
        $verified = ($response['status'] === 'SUCCESS' || $response['status'] === 'COMPLETED');
        $amount_match = false;
        $date_match = true;
        
        if (isset($response['amount'])) {
            $amount_match = (abs($response['amount'] - $expected_amount) < 0.01);
        }
        
        if (isset($response['transactionDate'])) {
            $transaction_date = strtotime($response['transactionDate']);
            $current_date = time();
            $date_diff_days = abs(($current_date - $transaction_date) / 86400);
            $date_match = ($date_diff_days <= 7); // Transaction within last 7 days
        }
        
        return [
            'success' => true,
            'verified' => $verified && $amount_match && $date_match,
            'gateway' => 'telebirr',
            'transaction_status' => $response['status'] ?? 'UNKNOWN',
            'amount' => $response['amount'] ?? null,
            'amount_match' => $amount_match,
            'transaction_date' => $response['transactionDate'] ?? null,
            'date_match' => $date_match,
            'message' => $verified && $amount_match && $date_match 
                ? 'Transaction verified successfully' 
                : 'Transaction verification failed',
            'raw_response' => $response
        ];
    }
    
    /**
     * Parse CBE Birr API response
     */
    private function parseCBEResponse($response, $expected_amount) {
        $verified = isset($response['transactionStatus']) && 
                    ($response['transactionStatus'] === 'SUCCESS' || $response['transactionStatus'] === 'COMPLETED');
        
        $amount_match = false;
        if (isset($response['transactionAmount'])) {
            $amount_match = (abs($response['transactionAmount'] - $expected_amount) < 0.01);
        }
        
        $date_match = true;
        if (isset($response['transactionDateTime'])) {
            $transaction_date = strtotime($response['transactionDateTime']);
            $current_date = time();
            $date_diff_days = abs(($current_date - $transaction_date) / 86400);
            $date_match = ($date_diff_days <= 7);
        }
        
        return [
            'success' => true,
            'verified' => $verified && $amount_match && $date_match,
            'gateway' => 'cbe_birr',
            'transaction_status' => $response['transactionStatus'] ?? 'UNKNOWN',
            'amount' => $response['transactionAmount'] ?? null,
            'amount_match' => $amount_match,
            'transaction_date' => $response['transactionDateTime'] ?? null,
            'date_match' => $date_match,
            'message' => $verified && $amount_match && $date_match 
                ? 'Transaction verified successfully' 
                : 'Transaction verification failed',
            'raw_response' => $response
        ];
    }
    
    /**
     * Parse M-Pesa API response
     */
    private function parseMPesaResponse($response, $expected_amount) {
        $verified = isset($response['ResultCode']) && $response['ResultCode'] === '0';
        
        $amount_match = false;
        if (isset($response['TransAmount'])) {
            $amount_match = (abs($response['TransAmount'] - $expected_amount) < 0.01);
        }
        
        $date_match = true;
        if (isset($response['TransactionDate'])) {
            $transaction_date = strtotime($response['TransactionDate']);
            $current_date = time();
            $date_diff_days = abs(($current_date - $transaction_date) / 86400);
            $date_match = ($date_diff_days <= 7);
        }
        
        return [
            'success' => true,
            'verified' => $verified && $amount_match && $date_match,
            'gateway' => 'mpesa',
            'transaction_status' => $response['ResultDesc'] ?? 'UNKNOWN',
            'amount' => $response['TransAmount'] ?? null,
            'amount_match' => $amount_match,
            'transaction_date' => $response['TransactionDate'] ?? null,
            'date_match' => $date_match,
            'message' => $verified && $amount_match && $date_match 
                ? 'Transaction verified successfully' 
                : 'Transaction verification failed',
            'raw_response' => $response
        ];
    }
    
    /**
     * Parse Amole API response
     */
    private function parseAmoleResponse($response, $expected_amount) {
        $verified = isset($response['success']) && $response['success'] === true;
        
        $amount_match = false;
        if (isset($response['data']['amount'])) {
            $amount_match = (abs($response['data']['amount'] - $expected_amount) < 0.01);
        }
        
        $date_match = true;
        if (isset($response['data']['timestamp'])) {
            $transaction_date = strtotime($response['data']['timestamp']);
            $current_date = time();
            $date_diff_days = abs(($current_date - $transaction_date) / 86400);
            $date_match = ($date_diff_days <= 7);
        }
        
        return [
            'success' => true,
            'verified' => $verified && $amount_match && $date_match,
            'gateway' => 'amole',
            'transaction_status' => $response['data']['status'] ?? 'UNKNOWN',
            'amount' => $response['data']['amount'] ?? null,
            'amount_match' => $amount_match,
            'transaction_date' => $response['data']['timestamp'] ?? null,
            'date_match' => $date_match,
            'message' => $verified && $amount_match && $date_match 
                ? 'Transaction verified successfully' 
                : 'Transaction verification failed',
            'raw_response' => $response
        ];
    }
    
    /**
     * Parse HelloCash API response
     */
    private function parseHelloCashResponse($response, $expected_amount) {
        $verified = isset($response['status']) && $response['status'] === 'VERIFIED';
        
        $amount_match = false;
        if (isset($response['amount'])) {
            $amount_match = (abs($response['amount'] - $expected_amount) < 0.01);
        }
        
        $date_match = true;
        if (isset($response['date'])) {
            $transaction_date = strtotime($response['date']);
            $current_date = time();
            $date_diff_days = abs(($current_date - $transaction_date) / 86400);
            $date_match = ($date_diff_days <= 7);
        }
        
        return [
            'success' => true,
            'verified' => $verified && $amount_match && $date_match,
            'gateway' => 'hellocash',
            'transaction_status' => $response['status'] ?? 'UNKNOWN',
            'amount' => $response['amount'] ?? null,
            'amount_match' => $amount_match,
            'transaction_date' => $response['date'] ?? null,
            'date_match' => $date_match,
            'message' => $verified && $amount_match && $date_match 
                ? 'Transaction verified successfully' 
                : 'Transaction verification failed',
            'raw_response' => $response
        ];
    }
    
    /**
     * Generate Telebirr signature
     */
    private function generateTelebirrSignature($payload, $secret) {
        $string_to_sign = json_encode($payload) . $secret;
        return hash('sha256', $string_to_sign);
    }
    
    /**
     * Get CBE access token
     */
    private function getCBEAccessToken($config) {
        // Implement OAuth token retrieval for CBE
        // This is a placeholder - actual implementation depends on CBE API
        return base64_encode($config['api_key'] . ':' . $config['api_secret']);
    }
    
    /**
     * Get M-Pesa access token
     */
    private function getMPesaAccessToken($config) {
        // Implement OAuth token retrieval for M-Pesa
        // This is a placeholder - actual implementation depends on M-Pesa API
        return base64_encode($config['consumer_key'] . ':' . $config['consumer_secret']);
    }
    
    /**
     * Log verification attempt
     */
    private function logVerification($transaction_id, $gateway, $result) {
        $query = "INSERT INTO payment_verification_attempts 
                  (transaction_id, gateway, verified, amount_match, date_match, response_data, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $verified = $result['verified'] ? 1 : 0;
        $amount_match = isset($result['amount_match']) ? ($result['amount_match'] ? 1 : 0) : null;
        $date_match = isset($result['date_match']) ? ($result['date_match'] ? 1 : 0) : null;
        $response_json = json_encode($result);
        
        $stmt->bind_param("ssiiss", $transaction_id, $gateway, $verified, $amount_match, $date_match, $response_json);
        $stmt->execute();
    }
}
