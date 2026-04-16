<?php
/**
 * Google OAuth 2.0 Service
 * Handles Google authentication flow
 */
class GoogleOAuthService {

    private $conn;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $authUrl   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $tokenUrl  = 'https://oauth2.googleapis.com/token';
    private $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function __construct($conn) {
        $this->conn         = $conn;
        $this->clientId     = defined('GOOGLE_CLIENT_ID')     ? GOOGLE_CLIENT_ID     : '';
        $this->clientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
        $this->redirectUri  = defined('GOOGLE_REDIRECT_URI')  ? GOOGLE_REDIRECT_URI  : '';
    }

    /**
     * Check if Google OAuth is properly configured
     */
    public function isConfigured() {
        // Also try reading directly from environment in case constants weren't defined
        if (empty($this->clientId))     $this->clientId     = getenv('GOOGLE_CLIENT_ID')     ?: '';
        if (empty($this->clientSecret)) $this->clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
        if (empty($this->redirectUri))  $this->redirectUri  = getenv('GOOGLE_REDIRECT_URI')  ?: '';

        return !empty($this->clientId)
            && !empty($this->clientSecret)
            && !empty($this->redirectUri)
            && strpos($this->clientId,     'your-') === false
            && strpos($this->clientSecret, 'your-') === false;
    }

    /**
     * Build the Google OAuth authorization URL
     */
    public function getAuthUrl($state = '') {
        $params = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ];
        if ($state !== '') {
            $params['state'] = $state;
        }
        return $this->authUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code) {
        $postData = [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ];

        $ch = curl_init($this->tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("GoogleOAuth getAccessToken failed. HTTP $httpCode");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Fetch user profile from Google
     */
    public function getUserInfo($accessToken) {
        $ch = curl_init($this->userInfoUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("GoogleOAuth getUserInfo failed. HTTP $httpCode");
            return null;
        }

        $data = json_decode($response, true);

        // Normalise field names (Google v3 uses 'sub' for ID)
        return [
            'id'         => $data['sub']            ?? '',
            'email'      => $data['email']           ?? '',
            'first_name' => $data['given_name']      ?? '',
            'last_name'  => $data['family_name']     ?? '',
            'name'       => $data['name']            ?? '',
            'picture'    => $data['picture']         ?? '',
            'verified'   => $data['email_verified']  ?? false,
        ];
    }

    /**
     * Create a new user or update an existing one after Google login
     */
    public function createOrUpdateUser($googleUser) {
        if (empty($googleUser['email'])) {
            return null;
        }

        $email      = $googleUser['email'];
        $firstName  = $googleUser['first_name'] ?: explode(' ', $googleUser['name'])[0];
        $lastName   = $googleUser['last_name']  ?: (explode(' ', $googleUser['name'])[1] ?? '');
        $googleId   = $googleUser['id'];

        // Check if user already exists
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // Update google_id if not set
            if (empty($existing['google_id'])) {
                $upd = $this->conn->prepare("UPDATE users SET google_id = ?, last_login = NOW() WHERE id = ?");
                $upd->bind_param("si", $googleId, $existing['id']);
                $upd->execute();
                $upd->close();
            } else {
                $upd = $this->conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $upd->bind_param("i", $existing['id']);
                $upd->execute();
                $upd->close();
            }
            return $existing;
        }

        // Create new customer account
        $username       = 'google_' . substr(md5($email), 0, 8);
        $passwordHash   = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $role           = 'customer';
        $status         = 'active';

        $ins = $this->conn->prepare(
            "INSERT INTO users (first_name, last_name, username, email, password, role, status, google_id, created_at, last_login)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $ins->bind_param("ssssssss", $firstName, $lastName, $username, $email, $passwordHash, $role, $status, $googleId);

        if (!$ins->execute()) {
            error_log("GoogleOAuth createOrUpdateUser insert failed: " . $ins->error);
            $ins->close();
            return null;
        }
        $newId = $this->conn->insert_id;
        $ins->close();

        // Return the newly created user
        $sel = $this->conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $sel->bind_param("i", $newId);
        $sel->execute();
        $user = $sel->get_result()->fetch_assoc();
        $sel->close();

        return $user;
    }
}
