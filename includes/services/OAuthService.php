<?php
/**
 * OAuth Service for Google and GitHub Authentication
 * Handles OAuth 2.0 flow for third-party authentication
 */

class OAuthService {
    private $conn;
    private $config;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $this->config = [
            'google' => [
                'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
                'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
                'redirect_uri' => getenv('SITE_URL') . '/oauth-callback.php',
                'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
                'scope' => 'email profile'
            ],
            'github' => [
                'client_id' => getenv('GITHUB_CLIENT_ID') ?: '',
                'client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
                'redirect_uri' => getenv('SITE_URL') . '/oauth-callback.php',
                'auth_url' => 'https://github.com/login/oauth/authorize',
                'token_url' => 'https://github.com/login/oauth/access_token',
                'user_info_url' => 'https://api.github.com/user',
                'scope' => 'user:email'
            ]
        ];
    }
    
    /**
     * Get authorization URL for OAuth provider
     */
    public function getAuthorizationUrl($provider, $state = null) {
        if (!isset($this->config[$provider])) {
            throw new Exception("Invalid OAuth provider: $provider");
        }
        
        $config = $this->config[$provider];
        
        if (empty($config['client_id']) || $config['client_id'] === 'your-google-client-id.apps.googleusercontent.com' || 
            $config['client_id'] === 'your-github-client-id' || strpos($config['client_id'], 'your-') === 0) {
            throw new Exception("OAuth not configured for $provider. Please set up OAuth credentials in .env file.");
        }
        
        // Generate state token for CSRF protection
        if ($state === null) {
            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth_state'] = $state;
            $_SESSION['oauth_provider'] = $provider;
        }
        
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'scope' => $config['scope'],
            'response_type' => 'code',
            'state' => $state
        ];
        
        return $config['auth_url'] . '?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($provider, $code) {
        if (!isset($this->config[$provider])) {
            throw new Exception("Invalid OAuth provider: $provider");
        }
        
        $config = $this->config[$provider];
        
        $params = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init($config['token_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Log detailed error information
        if ($http_code !== 200) {
            error_log("OAuth Token Error - HTTP Code: $http_code");
            error_log("OAuth Token Error - Response: $response");
            error_log("OAuth Token Error - Redirect URI: " . $config['redirect_uri']);
            
            // Parse error response
            $error_data = json_decode($response, true);
            $error_message = "Failed to get access token from $provider (HTTP $http_code)";
            
            if (isset($error_data['error_description'])) {
                $error_message .= ": " . $error_data['error_description'];
            } elseif (isset($error_data['error'])) {
                $error_message .= ": " . $error_data['error'];
            }
            
            throw new Exception($error_message);
        }
        
        if ($curl_error) {
            throw new Exception("CURL Error: $curl_error");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            throw new Exception("No access token in response from $provider");
        }
        
        return $data['access_token'];
    }
    
    /**
     * Get user information from OAuth provider
     */
    public function getUserInfo($provider, $access_token) {
        if (!isset($this->config[$provider])) {
            throw new Exception("Invalid OAuth provider: $provider");
        }
        
        $config = $this->config[$provider];
        
        $ch = curl_init($config['user_info_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
            'User-Agent: Harar-Ras-Hotel'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Failed to get user info from $provider");
        }
        
        $data = json_decode($response, true);
        
        // Normalize user data across providers
        return $this->normalizeUserData($provider, $data);
    }
    
    /**
     * Normalize user data from different OAuth providers
     */
    private function normalizeUserData($provider, $data) {
        $normalized = [
            'provider' => $provider,
            'provider_id' => null,
            'email' => null,
            'name' => null,
            'first_name' => null,
            'last_name' => null,
            'avatar' => null
        ];
        
        switch ($provider) {
            case 'google':
                $normalized['provider_id'] = $data['id'] ?? null;
                $normalized['email'] = $data['email'] ?? null;
                $normalized['name'] = $data['name'] ?? null;
                $normalized['first_name'] = $data['given_name'] ?? '';
                $normalized['last_name'] = $data['family_name'] ?? '';
                $normalized['avatar'] = $data['picture'] ?? null;
                break;
                
            case 'github':
                $normalized['provider_id'] = $data['id'] ?? null;
                $normalized['email'] = $data['email'] ?? $this->getGitHubEmail($data['access_token'] ?? '');
                $normalized['name'] = $data['name'] ?? $data['login'] ?? null;
                
                // Split name into first and last
                if ($normalized['name']) {
                    $name_parts = explode(' ', trim($normalized['name']), 2);
                    $normalized['first_name'] = $name_parts[0];
                    $normalized['last_name'] = $name_parts[1] ?? '';
                }
                
                $normalized['avatar'] = $data['avatar_url'] ?? null;
                break;
        }
        
        return $normalized;
    }
    
    /**
     * Get GitHub user's primary email (GitHub may not return email in user info)
     */
    private function getGitHubEmail($access_token) {
        $ch = curl_init('https://api.github.com/user/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
            'User-Agent: Harar-Ras-Hotel'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $emails = json_decode($response, true);
        
        if (is_array($emails)) {
            foreach ($emails as $email) {
                if ($email['primary'] && $email['verified']) {
                    return $email['email'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find or create user from OAuth data
     */
    public function findOrCreateUser($userData) {
        if (empty($userData['email'])) {
            throw new Exception("Email is required from OAuth provider");
        }
        
        // Check if user exists by email
        $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $userData['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // User exists, update OAuth info
            $user = $result->fetch_assoc();
            $this->updateOAuthInfo($user['id'], $userData);
            return $user;
        } else {
            // Create new user
            return $this->createUserFromOAuth($userData);
        }
    }
    
    /**
     * Create new user from OAuth data
     */
    private function createUserFromOAuth($userData) {
        // Generate unique username
        $base_username = explode('@', $userData['email'])[0];
        $username = $base_username;
        $counter = 1;
        
        while (true) {
            $check_query = "SELECT id FROM users WHERE username = ?";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                break;
            }
            
            $username = $base_username . $counter;
            $counter++;
        }
        
        // Create user with random password (they'll use OAuth to login)
        $random_password = bin2hex(random_bytes(16));
        $hashed_password = password_hash($random_password, PASSWORD_BCRYPT);
        
        $first_name = $userData['first_name'] ?: 'User';
        $last_name = $userData['last_name'] ?: '';
        $email = $userData['email'];
        // OAuth users are ALWAYS created as customers, never as staff
        $role = 'customer';
        $status = 'active';
        
        $query = "INSERT INTO users (first_name, last_name, username, email, password, profile_photo, role, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssssssss", $first_name, $last_name, $username, $email, $hashed_password, $userData['avatar'], $role, $status);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create user: " . $stmt->error);
        }
        
        $user_id = $stmt->insert_id;
        
        // Store OAuth info
        $this->updateOAuthInfo($user_id, $userData);
        
        // Log registration
        log_user_activity($user_id, 'registration', 'New account created via ' . $userData['provider'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Return user data
        return [
            'id' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'profile_photo' => $userData['avatar']
        ];
    }
    
    /**
     * Update OAuth information for user
     */
    private function updateOAuthInfo($user_id, $userData) {
        // Check if OAuth record exists
        $check_query = "SELECT id FROM oauth_tokens WHERE user_id = ? AND provider = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bind_param("is", $user_id, $userData['provider']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $query = "UPDATE oauth_tokens SET provider_user_id = ?, updated_at = NOW() WHERE user_id = ? AND provider = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("sis", $userData['provider_id'], $user_id, $userData['provider']);
        } else {
            // Insert new record
            $query = "INSERT INTO oauth_tokens (user_id, provider, provider_user_id, created_at, updated_at) 
                      VALUES (?, ?, ?, NOW(), NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iss", $user_id, $userData['provider'], $userData['provider_id']);
        }
        
        $stmt->execute();
    }
}
