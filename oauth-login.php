<?php
/**
 * OAuth Login Initiator
 * Redirects user to OAuth provider for authentication
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/services/OAuthService.php';

$provider = isset($_GET['provider']) ? $_GET['provider'] : '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$room_id = isset($_GET['room']) ? (int)$_GET['room'] : null;

// Validate provider
if (!in_array($provider, ['google', 'github'])) {
    header('Location: login.php?error=' . urlencode('Invalid OAuth provider'));
    exit();
}

// Store redirect information in session
if ($redirect) {
    $_SESSION['oauth_redirect'] = $redirect;
}
if ($room_id) {
    $_SESSION['oauth_room_id'] = $room_id;
}

try {
    $oauth = new OAuthService($conn);
    $auth_url = $oauth->getAuthorizationUrl($provider);
    
    // Redirect to OAuth provider
    header('Location: ' . $auth_url);
    exit();
    
} catch (Exception $e) {
    error_log("OAuth Initiation Error: " . $e->getMessage());
    
    // Show user-friendly error page
    $provider_name = ucfirst($provider);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>OAuth Not Configured</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .error-container {
                background: white;
                border-radius: 16px;
                padding: 40px;
                max-width: 600px;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
                text-align: center;
            }
            .error-icon {
                font-size: 64px;
                color: #f59e0b;
                margin-bottom: 20px;
            }
            h1 {
                color: #2d3748;
                font-size: 24px;
                margin-bottom: 15px;
            }
            p {
                color: #718096;
                margin-bottom: 20px;
                line-height: 1.6;
            }
            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                padding: 12px 30px;
                border-radius: 8px;
                color: white;
                text-decoration: none;
                display: inline-block;
                margin-top: 10px;
            }
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            }
            .setup-steps {
                background: #f7fafc;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                text-align: left;
            }
            .setup-steps h3 {
                font-size: 16px;
                color: #2d3748;
                margin-bottom: 15px;
            }
            .setup-steps ol {
                margin: 0;
                padding-left: 20px;
            }
            .setup-steps li {
                color: #4a5568;
                margin-bottom: 8px;
                font-size: 14px;
            }
            code {
                background: #edf2f7;
                padding: 2px 6px;
                border-radius: 4px;
                color: #e53e3e;
                font-size: 13px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <i class="fas fa-exclamation-triangle error-icon"></i>
            <h1><?php echo $provider_name; ?> Login Not Configured</h1>
            <p>The "Continue with <?php echo $provider_name; ?>" feature is not yet set up on this system.</p>
            
            <div class="setup-steps">
                <h3><i class="fas fa-tools"></i> For Administrators:</h3>
                <ol>
                    <li>Create a <?php echo $provider_name; ?> OAuth application</li>
                    <li>Get your Client ID and Client Secret</li>
                    <li>Update the <code>.env</code> file with your credentials</li>
                    <li>See <code>OAUTH_SETUP_GUIDE.md</code> for detailed instructions</li>
                </ol>
            </div>
            
            <p><strong>For now, please use the regular login form.</strong></p>
            
            <a href="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) . ($room_id ? '&room=' . $room_id : '') : ''; ?>" class="btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
