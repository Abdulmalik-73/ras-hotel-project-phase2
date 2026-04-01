<?php
/**
 * Google OAuth Service
 * Handles Google Sign-In authentication
 */

class GoogleOAuthService {
    private $conn;
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        
        // Load from environment or config
        $this->client_id = getenv('GOOGLE_CLIENT_ID') ?: '';
        $this->client_secret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
        $this->redirect_uri = getenv('GOOGLE_REDIRECT_URI') ?: $this->getBaseUrl() . '/oauth-callback.php';
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host;
    }
    
    /**
     * Get Google OAuth URL
     */
    public function getAuthUrl($state = null) {
        if (!$state) {
            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth_state'] = $state;
        }
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code) {
        $token_url = 'https://oauth2.googleapis.com/token';
        
        $params = [
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    /**
     * Get user info from Google
     */
    public function getUserInfo($access_token) {
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    /**
     * Handle OAuth callback and create/login user
     */
    public function handleCallback($code, $state) {
        // Verify state to prevent CSRF
        if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $state) {
            return [
                'success' => false,
                'message' => 'Invalid state parameter. Please try again.'
            ];
        }
        
        // Exchange code for access token
        $token_data = $this->getAccessToken($code);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            return [
                'success' => false,
                'message' => 'Failed to obtain access token from Google.'
            ];
        }
        
        // Get user info
        $user_info = $this->getUserInfo($token_data['access_token']);
        
        if (!$user_info || !isset($user_info['email'])) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve user information from Google.'
            ];
        }
        
        // Check if user exists
        $user = $this->findUserByGoogleId($user_info['id']);
        
        if (!$user) {
            // Check if email already exists
            $user = $this->findUserByEmail($user_info['email']);
            
            if ($user) {
                // Link Google account to existing user
                $this->linkGoogleAccount($user['id'], $user_info);
            } else {
                // Create new user
                $user = $this->createUserFromGoogle($user_info);
            }
        }
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Failed to create or retrieve user account.'
            ];
        }
        
        // Store OAuth tokens
        $this->storeOAuthTokens($user['id'], $token_data);
        
        // Update last login
        $this->updateLastLogin($user['id']);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['logged_in'] = true;
        
        return [
            'success' => true,
            'message' => 'Successfully logged in with Google!',
            'user' => $user
        ];
    }
    
    /**
     * Find user by Google ID
     */
    private function findUserByGoogleId($google_id) {
        $query = "SELECT * FROM users WHERE google_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $google_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Find user by email
     */
    private function findUserByEmail($email) {
        $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Create new user from Google data
     */
    private function createUserFromGoogle($user_info) {
        $email = $user_info['email'];
        $google_id = $user_info['id'];
        $first_name = $user_info['given_name'] ?? '';
        $last_name = $user_info['family_name'] ?? '';
        $profile_picture = $user_info['picture'] ?? '';
        $email_verified = $user_info['verified_email'] ?? false;
        
        $query = "INSERT INTO users 
                  (email, first_name, last_name, google_id, oauth_provider, 
                   profile_picture, email_verified, role, created_at) 
                  VALUES (?, ?, ?, ?, 'google', ?, ?, 'customer', NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sssssi", $email, $first_name, $last_name, $google_id, 
                         $profile_picture, $email_verified);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            return $this->findUserByGoogleId($google_id);
        }
        
        return null;
    }
    
    /**
     * Link Google account to existing user
     */
    private function linkGoogleAccount($user_id, $user_info) {
        $google_id = $user_info['id'];
        $profile_picture = $user_info['picture'] ?? '';
        $email_verified = $user_info['verified_email'] ?? false;
        
        $query = "UPDATE users 
                  SET google_id = ?, oauth_provider = 'google', 
                      profile_picture = ?, email_verified = ? 
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssii", $google_id, $profile_picture, $email_verified, $user_id);
        $stmt->execute();
    }
    
    /**
     * Store OAuth tokens
     */
    private function storeOAuthTokens($user_id, $token_data) {
        $access_token = $token_data['access_token'];
        $refresh_token = $token_data['refresh_token'] ?? null;
        $expires_in = $token_data['expires_in'] ?? 3600;
        $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        
        // Check if token exists
        $check_query = "SELECT id FROM oauth_tokens WHERE user_id = ? AND provider = 'google'";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->num_rows > 0;
        
        if ($exists) {
            // Update existing token
            $query = "UPDATE oauth_tokens 
                      SET access_token = ?, refresh_token = ?, token_expires_at = ?, updated_at = NOW() 
                      WHERE user_id = ? AND provider = 'google'";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("sssi", $access_token, $refresh_token, $expires_at, $user_id);
        } else {
            // Insert new token
            $query = "INSERT INTO oauth_tokens 
                      (user_id, provider, access_token, refresh_token, token_expires_at) 
                      VALUES (?, 'google', ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("isss", $user_id, $access_token, $refresh_token, $expires_at);
        }
        
        $stmt->execute();
    }
    
    /**
     * Update last login timestamp
     */
    private function updateLastLogin($user_id) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    /**
     * Check if Google OAuth is configured
     */
    public function isConfigured() {
        return !empty($this->client_id) && !empty($this->client_secret);
    }
}
