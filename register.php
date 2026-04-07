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
            padding: 20px 15px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.85) 0%, rgba(118, 75, 162, 0.85) 100%);
            z-index: -1;
        }
        
        .signup-wrapper {
            width: 100%;
            max-width: 450px;
            position: relative;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
        }
        
        .back-button:hover {
            color: white;
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-3px);
        }
        
        .back-button i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .signup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            padding: 20px 25px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .signup-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .signup-header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .signup-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }
        
        .signup-header p {
            color: #718096;
            font-size: 13px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
            display: block;
            font-size: 12px;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 9px 11px;
            font-size: 13px;
            width: 100%;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            margin-top: 15px;
        }
        
        .btn-signup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-cancel {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-cancel:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
            text-decoration: none;
        }
        
        .alert {
            border-radius: 8px;
            padding: 8px;
            margin-bottom: 10px;
            font-size: 12px;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 6px;
            font-size: 13px;
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
            margin: 10px 0;
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
            padding: 0 12px;
            color: #a0aec0;
            font-size: 12px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .btn-oauth {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid #e2e8f0;
            background: white;
            width: 100%;
        }
        
        .btn-oauth:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
            text-decoration: none;
        }
        
        .btn-google {
            color: #3c4043;
        }
        
        .btn-google:hover {
            border-color: #4285F4;
            background: #f8f9fa;
        }
        
        .login-link {
            text-align: center;
            margin-top: 12px;
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
            margin-top: 3px;
            display: block;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 15px 10px;
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
        .btn-oauth { animation: slideUp 0.6s ease-out 0.7s both; }
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
                    <small class="text-muted" style="font-size: 11px;">Optional - for booking notifications</small>
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
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <a href="oauth-login.php?provider=google<?php echo $redirect ? '&redirect=' . urlencode($redirect) . ($room_id ? '&room=' . $room_id : '') : ''; ?>" class="btn-oauth btn-google">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.64 9.20443C17.64 8.56625 17.5827 7.95262 17.4764 7.36353H9V10.8449H13.8436C13.635 11.9699 13.0009 12.9231 12.0477 13.5613V15.8194H14.9564C16.6582 14.2526 17.64 11.9453 17.64 9.20443Z" fill="#4285F4"/>
                    <path d="M8.99976 18C11.4298 18 13.467 17.1941 14.9561 15.8195L12.0475 13.5613C11.2416 14.1013 10.2107 14.4204 8.99976 14.4204C6.65567 14.4204 4.67158 12.8372 3.96385 10.71H0.957031V13.0418C2.43794 15.9831 5.48158 18 8.99976 18Z" fill="#34A853"/>
                    <path d="M3.96409 10.7098C3.78409 10.1698 3.68182 9.59301 3.68182 8.99983C3.68182 8.40665 3.78409 7.82983 3.96409 7.28983V4.95801H0.957273C0.347727 6.17301 0 7.54755 0 8.99983C0 10.4521 0.347727 11.8266 0.957273 13.0416L3.96409 10.7098Z" fill="#FBBC05"/>
                    <path d="M8.99976 3.57955C10.3211 3.57955 11.5075 4.03364 12.4402 4.92545L15.0216 2.34409C13.4629 0.891818 11.4257 0 8.99976 0C5.48158 0 2.43794 2.01682 0.957031 4.95818L3.96385 7.29C4.67158 5.16273 6.65567 3.57955 8.99976 3.57955Z" fill="#EA4335"/>
                </svg>
                <span>Continue with Google</span>
            </a>
            
            <div class="divider">
                <span>or</span>
            </div>
            
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
    </script>
</body>
</html>
