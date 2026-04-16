<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get redirect parameters
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$room_id = isset($_GET['room']) ? (int)$_GET['room'] : null;

if (is_logged_in()) {
    if ($redirect == 'booking') {
        $redirect_url = 'booking.php' . ($room_id ? '?room=' . $room_id : '');
        header("Location: $redirect_url");
    } elseif ($redirect == 'food-booking') {
        header("Location: food-booking.php");
    } else {
        header('Location: index.php');
    }
    exit();
}

$error = '';
$success = '';

// Check for OAuth errors
if (isset($_SESSION['oauth_error'])) {
    $error = $_SESSION['oauth_error'];
    unset($_SESSION['oauth_error']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (strlen($full_name) < 2) {
        $error = 'Full name must be at least 2 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = 'Password must contain at least one symbol (!@#$%^&*(),.?":{}|<>)';
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Email already exists. Please use a different email or login.';
        } else {
            // All public signups are customers only
            $hashed_password = hash_password($password);
            $role = 'customer';
            $status = 'active';
            
            // Split full name into first and last name
            $name_parts = explode(' ', trim($full_name), 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            // Generate unique username from email (part before @)
            $base_username = explode('@', $email)[0];
            $username = $base_username;
            $counter = 1;
            
            // Check if username exists and make it unique
            while (true) {
                $check_username_query = "SELECT id FROM users WHERE username = ?";
                $check_username_stmt = $conn->prepare($check_username_query);
                $check_username_stmt->bind_param("s", $username);
                $check_username_stmt->execute();
                $check_username_result = $check_username_stmt->get_result();
                
                if ($check_username_result->num_rows == 0) {
                    // Username is available
                    break;
                } else {
                    // Username exists, try with a number suffix
                    $username = $base_username . $counter;
                    $counter++;
                }
            }
            
            // Create customer account
            $query = "INSERT INTO users (first_name, last_name, username, email, phone, password, role, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssss", $first_name, $last_name, $username, $email, $phone, $hashed_password, $role, $status);
            
            if ($stmt->execute()) {
                // Get the newly created user ID
                $new_user_id = $stmt->insert_id;
                
                // Log user registration activity
                log_user_activity($new_user_id, 'registration', 'New customer account created', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Automatically log in the user after successful registration
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_name'] = trim($first_name . ' ' . $last_name);
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = $role;
                
                // Update last login timestamp
                $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("i", $new_user_id);
                $update_stmt->execute();
                
                // Log auto-login after registration
                log_user_activity($new_user_id, 'login', 'Auto-login after registration', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Redirect based on redirect parameter or to home page
                if ($redirect == 'booking') {
                    $redirect_url = 'booking.php' . ($room_id ? '?room=' . $room_id : '');
                    header("Location: $redirect_url");
                } elseif ($redirect == 'food-booking') {
                    header("Location: food-booking.php");
                } else {
                    header("Location: index.php?welcome=1");
                }
                exit();
            } else {
                $error = 'Registration failed. Please try again. Error: ' . $stmt->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-image: url('assets/images/hotel/exterior/hotel-main.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 16px 15px;
        }
        
        .signup-wrapper {
            width: 400px;
            max-width: 90%;
            position: relative;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 5px 10px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.35);
        }
        
        .back-button:hover {
            color: white;
            background: rgba(0, 0, 0, 0.55);
            transform: translateX(-3px);
        }
        
        .back-button i {
            margin-right: 6px;
            font-size: 13px;
        }
        
        .signup-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            padding: 18px 22px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .signup-header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .signup-header h1 {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 2px;
        }
        
        .signup-header p {
            color: #718096;
            font-size: 12px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 8px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 2px;
            display: block;
            font-size: 12px;
        }
        
        .form-control {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 0 10px;
            font-size: 13px;
            width: 100%;
            height: 38px;
            transition: all 0.2s ease;
            background: #f8fafc;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.12);
            outline: none;
            background: white;
        }
        
        .form-control.error {
            border-color: #e53e3e;
            background-color: #fed7d7;
        }
        
        .button-group {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        
        .btn-signup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0;
            height: 38px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
        }
        
        .btn-signup:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-cancel {
            background: white;
            color: #667eea;
            border: 1.5px solid #667eea;
            padding: 0;
            height: 38px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex: 1;
        }
        
        .btn-cancel:hover {
            background: #667eea;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
            text-decoration: none;
        }
        
        .btn-google {
            background: white;
            color: #757575;
            border: 1.5px solid #dadce0;
            padding: 0;
            height: 38px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-bottom: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        .btn-google:hover {
            background: #f8f9fa;
            color: #3c4043;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            text-decoration: none;
        }
        
        .btn-google-disabled {
            opacity: 0.6;
            cursor: not-allowed;
            position: relative;
        }
        
        .btn-google-disabled:hover {
            background: white;
            transform: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        .btn-google-disabled::after {
            content: "⚙️";
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
        }
        
        .alert {
            border-radius: 8px;
            padding: 6px 10px;
            margin-bottom: 8px;
            font-size: 12px;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 6px;
            font-size: 12px;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #c53030;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }
        
        .divider {
            text-align: center;
            margin: 8px 0 6px;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e2e8f0;
        }
        
        .divider span {
            background: white;
            padding: 0 10px;
            color: #a0aec0;
            font-size: 11px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .login-link {
            text-align: center;
            margin-top: 8px;
            font-size: 12px;
            color: #718096;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .field-error {
            color: #e53e3e;
            font-size: 10px;
            margin-top: 2px;
            display: block;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 12px 10px;
            }
            .signup-container {
                padding: 18px;
                border-radius: 14px;
            }
            
            .signup-header h1 {
                font-size: 22px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .back-button {
                font-size: 13px;
                padding: 6px 12px;
            }
            
            .form-group {
                margin-bottom: 9px;
            }
        }
        
        @media (max-width: 480px) {
            .signup-container {
                padding: 16px 14px;
                border-radius: 12px;
            }
            
            .signup-header h1 {
                font-size: 20px;
            }
            
            .signup-header p {
                font-size: 12px;
            }
            
            .form-control {
                font-size: 14px;
            }
            
            .btn-signup, .btn-cancel {
                padding: 10px 14px;
                font-size: 13px;
            }
        }
        
        /* Animation for form elements */
        .form-group {
            animation: slideUp 0.6s ease-out;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        .form-group:nth-child(5) { animation-delay: 0.5s; }
        .button-group { animation: slideUp 0.6s ease-out 0.6s both; }
        .divider { animation: slideUp 0.6s ease-out 0.65s both; }
        .login-link { animation: slideUp 0.6s ease-out 0.75s both; }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Focus states for accessibility */
        .back-button:focus,
        .form-control:focus,
        .btn-signup:focus,
        .btn-cancel:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="signup-wrapper">
        <div class="page-header">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
        </div>
        
        <div class="signup-container">
            <div class="signup-header">
                <h1>Create New Account</h1>
                <p>Join us today! Please fill in your details.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="signupForm">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required 
                           placeholder="Enter your full name"
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    <div id="fullname-error" class="field-error" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" id="email" class="form-control" required 
                           placeholder="Enter your email address"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <div id="email-error" class="field-error" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number (Optional)</label>
                    <input type="tel" name="phone" id="phone" class="form-control" 
                           placeholder="Enter your phone number (e.g., +251-911-234-567)"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <div class="password-input-wrapper" style="position: relative;">
                        <input type="password" name="password" id="password" class="form-control" required minlength="8"
                               placeholder="Enter strong password (min. 8 characters)">
                        <button type="button" class="btn-toggle-password" onclick="togglePassword('password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer;">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                    
                    <!-- Password Strength Indicator -->
                    <div id="password-strength" class="mt-2" style="display: none;">
                        <div class="password-requirements" style="font-size: 13px;">
                            <div class="requirement" id="req-lowercase">
                                <i class="fas fa-times text-danger"></i>
                                <span>A lowercase letter</span>
                            </div>
                            <div class="requirement" id="req-uppercase">
                                <i class="fas fa-times text-danger"></i>
                                <span>A capital (uppercase) letter</span>
                            </div>
                            <div class="requirement" id="req-number">
                                <i class="fas fa-times text-danger"></i>
                                <span>A number</span>
                            </div>
                            <div class="requirement" id="req-symbol">
                                <i class="fas fa-times text-danger"></i>
                                <span>A symbol</span>
                            </div>
                            <div class="requirement" id="req-length">
                                <i class="fas fa-times text-danger"></i>
                                <span>Minimum 8 characters</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <div class="password-input-wrapper" style="position: relative;">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8"
                               placeholder="Re-enter your password">
                        <button type="button" class="btn-toggle-password" onclick="togglePassword('confirm_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer;">
                            <i class="fas fa-eye" id="confirm_password-eye"></i>
                        </button>
                    </div>
                    <div id="password-match" class="mt-2" style="display: none; font-size: 13px;">
                        <div class="requirement">
                            <i class="fas fa-times text-danger"></i>
                            <span>Password confirmed</span>
                        </div>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-signup">Create Account</button>
                    <a href="index.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
            
            <!-- Google OAuth Button -->
            <div class="divider">
                <span>or</span>
            </div>
            
            <?php
            // Check if Google OAuth is configured for real functionality
            $oauth_configured = false;
            if (file_exists('includes/services/GoogleOAuthService.php')):
                require_once 'includes/services/GoogleOAuthService.php';
                $oauth_service = new GoogleOAuthService($conn);
                $oauth_configured = $oauth_service->isConfigured();
                
                if ($oauth_configured):
                    // Prepare state parameter for redirect
                    $state_params = [];
                    if ($redirect) {
                        $state_params['redirect'] = $redirect;
                        if ($room_id) {
                            $state_params['room'] = $room_id;
                        }
                    }
                    $state = base64_encode(http_build_query($state_params));
                    $google_auth_url = $oauth_service->getAuthUrl($state);
            ?>
            <a href="<?php echo htmlspecialchars($google_auth_url); ?>" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 24 24" style="margin-right: 8px; vertical-align: middle;">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </a>
            <?php else: ?>
            <a href="#" onclick="showGoogleSetupInfo(); return false;" class="btn-google btn-google-disabled">
                <svg width="18" height="18" viewBox="0 0 24 24" style="margin-right: 8px; vertical-align: middle; opacity: 0.5;">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </a>
            <?php endif; endif; ?>
            
            <div class="login-link">
                Already have an account? 
                <a href="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) . ($room_id ? '&room=' . $room_id : '') : ''; ?>">
                    Login
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength validator
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthIndicator = document.getElementById('password-strength');
        const matchIndicator = document.getElementById('password-match');
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }
        
        // Check password strength
        function checkPasswordStrength(password) {
            const requirements = {
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                number: /[0-9]/.test(password),
                symbol: /[!@#$%^&*(),.?":{}|<>]/.test(password),
                length: password.length >= 8
            };
            
            return requirements;
        }
        
        // Update requirement UI
        function updateRequirement(id, met) {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');
            
            if (met) {
                icon.classList.remove('fa-times', 'text-danger');
                icon.classList.add('fa-check', 'text-success');
                element.style.color = '#28a745';
            } else {
                icon.classList.remove('fa-check', 'text-success');
                icon.classList.add('fa-times', 'text-danger');
                element.style.color = '#dc3545';
            }
        }
        
        // Password input event
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            if (password.length > 0) {
                strengthIndicator.style.display = 'block';
                
                const requirements = checkPasswordStrength(password);
                
                updateRequirement('req-lowercase', requirements.lowercase);
                updateRequirement('req-uppercase', requirements.uppercase);
                updateRequirement('req-number', requirements.number);
                updateRequirement('req-symbol', requirements.symbol);
                updateRequirement('req-length', requirements.length);
                
                // Check if all requirements are met
                const allMet = Object.values(requirements).every(req => req);
                
                if (allMet) {
                    passwordInput.style.borderColor = '#28a745';
                } else {
                    passwordInput.style.borderColor = '#dc3545';
                }
            } else {
                strengthIndicator.style.display = 'none';
                passwordInput.style.borderColor = '';
            }
            
            // Check password match
            checkPasswordMatch();
        });
        
        // Confirm password input event
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length > 0) {
                matchIndicator.style.display = 'block';
                const matchElement = matchIndicator.querySelector('.requirement');
                const icon = matchElement.querySelector('i');
                
                if (password === confirmPassword && password.length > 0) {
                    icon.classList.remove('fa-times', 'text-danger');
                    icon.classList.add('fa-check', 'text-success');
                    matchElement.style.color = '#28a745';
                    confirmPasswordInput.style.borderColor = '#28a745';
                } else {
                    icon.classList.remove('fa-check', 'text-success');
                    icon.classList.add('fa-times', 'text-danger');
                    matchElement.style.color = '#dc3545';
                    confirmPasswordInput.style.borderColor = '#dc3545';
                }
            } else {
                matchIndicator.style.display = 'none';
                confirmPasswordInput.style.borderColor = '';
            }
        }
        
        // Form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const requirements = checkPasswordStrength(password);
            const allMet = Object.values(requirements).every(req => req);
            
            if (!allMet) {
                e.preventDefault();
                alert('Please ensure your password meets all requirements:\n- A lowercase letter\n- An uppercase letter\n- A number\n- A symbol\n- Minimum 8 characters');
                return false;
            }
            
            if (password !== confirmPasswordInput.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const fullNameInput = document.getElementById('full_name');
            const emailInput = document.getElementById('email');
            const passwordInput = document.querySelector('input[name="password"]');
            const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
            const fullNameError = document.getElementById('fullname-error');
            const emailError = document.getElementById('email-error');
            const signupForm = document.getElementById('signupForm');
            
            // Function to show error
            function showError(element, errorDiv, message) {
                element.classList.add('error');
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
            }
            
            // Function to clear error
            function clearError(element, errorDiv) {
                element.classList.remove('error');
                errorDiv.textContent = '';
                errorDiv.style.display = 'none';
            }
            
            // Clear errors when user types
            fullNameInput.addEventListener('input', function() {
                clearError(fullNameInput, fullNameError);
            });
            
            emailInput.addEventListener('input', function() {
                clearError(emailInput, emailError);
            });
            
            // Form submission validation
            signupForm.addEventListener('submit', function(e) {
                // Clear all previous errors
                clearError(fullNameInput, fullNameError);
                clearError(emailInput, emailError);
                
                const fullName = fullNameInput.value.trim();
                const email = emailInput.value.trim();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                let hasError = false;
                
                // Validate full name
                if (!fullName) {
                    showError(fullNameInput, fullNameError, 'Full name is required.');
                    hasError = true;
                } else if (fullName.length < 2) {
                    showError(fullNameInput, fullNameError, 'Full name must be at least 2 characters.');
                    hasError = true;
                } else if (!/^[a-zA-Z\s]+$/.test(fullName)) {
                    showError(fullNameInput, fullNameError, 'Full name can only contain letters and spaces.');
                    hasError = true;
                }
                
                // Validate email
                if (!email) {
                    showError(emailInput, emailError, 'Email address is required.');
                    hasError = true;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showError(emailInput, emailError, 'Please enter a valid email address.');
                    hasError = true;
                }
                
                // Validate password match
                if (password !== confirmPassword) {
                    // Show error on confirm password field
                    const confirmPasswordError = document.createElement('div');
                    confirmPasswordError.className = 'field-error';
                    confirmPasswordError.textContent = 'Passwords do not match.';
                    confirmPasswordInput.classList.add('error');
                    confirmPasswordInput.parentNode.appendChild(confirmPasswordError);
                    hasError = true;
                }
                
                // Validate password length
                if (password.length < 6) {
                    const passwordError = document.createElement('div');
                    passwordError.className = 'field-error';
                    passwordError.textContent = 'Password must be at least 6 characters.';
                    passwordInput.classList.add('error');
                    passwordInput.parentNode.appendChild(passwordError);
                    hasError = true;
                }
                
                if (hasError) {
                    e.preventDefault();
                    fullNameInput.focus();
                    return false;
                }
            });
        });
        
        function showGoogleSetupInfo() {
            alert('Google OAuth Setup Required:\n\n' +
                  '1. Go to https://console.developers.google.com/\n' +
                  '2. Create a new project\n' +
                  '3. Enable Google+ API\n' +
                  '4. Create OAuth 2.0 credentials\n' +
                  '5. Add redirect URI: ' + window.location.origin + '/oauth-callback.php\n' +
                  '6. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI in Render environment variables\n\n' +
                  'Contact your developer for assistance.');
        }
    </script>
</body>
</html>
