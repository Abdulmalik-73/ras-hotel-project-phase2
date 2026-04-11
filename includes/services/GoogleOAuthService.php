<?php
/**
 * Google OAuth Service for Harar Ras Hotel
 * Handles Google OAuth authentication flow
 */

class GoogleOAuthService {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        // Try multiple methods to get environment variables
        $this->client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : (getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? ''));
        $this->client_secret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : (getenv('GOOGLE_CLIENT_SECRET') ?: ($_ENV['GOOGLE_CLIENT_SECRET'] ?? ''));
        $this->redirect_uri = defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : (getenv('GOOGLE_REDIRECT_URI') ?: ($_ENV['GOOGLE_REDIRECT_URI'] ?? ''));
    }
    
    /**
     * Get Google OAuth authorization URL
     */
    public function getAuthUrl($state = null) {
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code) {
        $url = 'https://oauth2.googleapis.com/token';
        
        $data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
            'code' => $code
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Get user info from Google API
     */
    public function getUserInfo($access_token) {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $access_token;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Create or update user from Google OAuth data
     */
    public function createOrUpdateUser($google_user) {
        try {
            // Store values in variables for bind_param (required in PHP 8.1+)
            $google_id = $google_user['id'];
            $given_name = $google_user['given_name'] ?? '';
            $family_name = $google_user['family_name'] ?? '';
            $email = $google_user['email'];
            $picture = $google_user['picture'] ?? '';
            
            // Check if user exists by Google ID
            $check_query = "SELECT * FROM users WHERE google_id = ?";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bind_param("s", $google_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // User exists, update their info
                $user = $result->fetch_assoc();
                
                $update_query = "UPDATE users SET 
                                first_name = ?, 
                                last_name = ?, 
                                email = ?, 
                                profile_picture = ?, 
                                email_verified = 1,
                                last_login = NOW() 
                                WHERE google_id = ?";
                
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bind_param("sssss", $given_name, $family_name, $email, $picture, $google_id);
                $update_stmt->execute();
                
                return $user;
            } else {
                // Check if user exists by email
                $email_check_query = "SELECT * FROM users WHERE email = ?";
                $email_check_stmt = $this->conn->prepare($email_check_query);
                $email_check_stmt->bind_param("s", $email);
                $email_check_stmt->execute();
                $email_result = $email_check_stmt->get_result();
                
                if ($email_result->num_rows > 0) {
                    // User exists with same email, link Google account
                    $user = $email_result->fetch_assoc();
                    
                    $link_query = "UPDATE users SET 
                                  google_id = ?, 
                                  oauth_provider = 'google',
                                  profile_picture = ?, 
                                  email_verified = 1,
                                  last_login = NOW() 
                                  WHERE email = ?";
                    
                    $link_stmt = $this->conn->prepare($link_query);
                    $link_stmt->bind_param("sss", $google_id, $picture, $email);
                    $link_stmt->execute();
                    
                    return $user;
                } else {
                    // Create new user (variables already declared at top of function)
                    // Generate unique username from email
                    $base_username = explode('@', $email)[0];
                    $username = $base_username;
                    $counter = 1;
                    
                    while (true) {
                        $check_username_query = "SELECT id FROM users WHERE username = ?";
                        $check_username_stmt = $this->conn->prepare($check_username_query);
                        $check_username_stmt->bind_param("s", $username);
                        $check_username_stmt->execute();
                        $check_username_result = $check_username_stmt->get_result();
                        
                        if ($check_username_result->num_rows == 0) {
                            break;
                        } else {
                            $username = $base_username . $counter;
                            $counter++;
                        }
                    }
                    
                    $insert_query = "INSERT INTO users (
                                    first_name, last_name, username, email, 
                                    google_id, oauth_provider, profile_picture, 
                                    role, status, email_verified, created_at, last_login
                                    ) VALUES (?, ?, ?, ?, ?, 'google', ?, 'customer', 'active', 1, NOW(), NOW())";
                    
                    $insert_stmt = $this->conn->prepare($insert_query);
                    $insert_stmt->bind_param("ssssss", $given_name, $family_name, $username, $email, $google_id, $picture);
                    
                    if ($insert_stmt->execute()) {
                        $new_user_id = $insert_stmt->insert_id;
                        
                        // Get the newly created user
                        $get_user_query = "SELECT * FROM users WHERE id = ?";
                        $get_user_stmt = $this->conn->prepare($get_user_query);
                        $get_user_stmt->bind_param("i", $new_user_id);
                        $get_user_stmt->execute();
                        $new_user = $get_user_stmt->get_result()->fetch_assoc();
                        
                        // Log user registration activity
                        if (function_exists('log_user_activity')) {
                            log_user_activity($new_user_id, 'registration', 'New customer account created via Google OAuth', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                        }
                        
                        return $new_user;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Google OAuth error: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Check if Google OAuth is configured
     */
    public function isConfigured() {
        // Check if we have real credentials (not demo ones)
        $has_real_credentials = !empty($this->client_id) && 
                               !empty($this->client_secret) && 
                               !empty($this->redirect_uri) &&
                               $this->client_id !== 'demo-client-id' &&
                               $this->client_secret !== 'demo-client-secret';
        
        return $has_real_credentials;
    }
}