<?php
require_once 'includes/config.php';

// Get redirect parameters
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$room_id = isset($_GET['room']) ? (int)$_GET['room'] : null;

// Redirect if already logged in
if (is_logged_in()) {
    if ($redirect == 'booking') {
        $redirect_url = 'booking.php' . ($room_id ? '?room=' . $room_id : '');
        header("Location: $redirect_url");
    } elseif ($redirect == 'food-booking') {
        header("Location: food-booking.php");
    } else {
        $role = get_user_role();
        switch($role) {
            case 'admin':
                header("Location: dashboard/admin.php");
                break;
            case 'manager':
                header("Location: dashboard/manager.php");
                break;
            case 'receptionist':
                header("Location: dashboard/receptionist.php");
                break;
            default:
                header("Location: index.php");
                break;
        }
    }
    exit();
}

$error = '';
$success = '';

// Check for password reset success
if (isset($_GET['reset']) && $_GET['reset'] == 'success') {
    $success = 'Password reset successful! You can now login with your new password.';
}

// Check for OAuth errors
if (isset($_SESSION['oauth_error'])) {
    $error = $_SESSION['oauth_error'];
    unset($_SESSION['oauth_error']);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_or_username = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email_or_username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Query database for user by email or username
        $query = "SELECT * FROM users WHERE (email = ? OR username = ?) AND status = 'active'";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("ss", $email_or_username, $email_or_username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verify password using professional method (supports bcrypt, MD5, and plain text)
                if (verify_user_password($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Update last login timestamp
                    $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("i", $user['id']);
                    $update_stmt->execute();
                    
                    // Log successful login activity
                    log_user_activity($user['id'], 'login', 'User logged in successfully', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                    
                    // Log successful login
                    error_log("Successful login for user: " . $user['email']);
                    
                    // Redirect based on role and redirect parameter
                    $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
                    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $base  = $proto . '://' . $host;

                    if ($redirect == 'booking') {
                        $rurl = 'booking.php' . ($room_id ? '?room=' . $room_id : '');
                        header("Location: $base/$rurl");
                    } elseif ($redirect == 'food-booking') {
                        header("Location: $base/food-booking.php");
                    } else {
                        switch($user['role']) {
                            case 'super_admin':
                                header("Location: $base/dashboard/super-admin.php");
                                break;
                            case 'admin':
                                header("Location: $base/dashboard/admin.php");
                                break;
                            case 'manager':
                                header("Location: $base/dashboard/manager.php");
                                break;
                            case 'receptionist':
                                header("Location: $base/dashboard/receptionist.php");
                                break;
                            default:
                                header("Location: $base/index.php");
                                break;
                        }
                    }
                    exit();
                } else {
                    $error = 'Invalid email/username or password';
                    error_log("Failed login attempt for: " . $email_or_username . " - Invalid password");
                }
            } else {
                $error = 'Invalid email/username or password';
                error_log("Failed login attempt for: " . $email_or_username . " - User not found or inactive");
            }
            $stmt->close();
        } else {
            $error = 'Database error. Please try again.';
            error_log("Database error in login: " . $conn->error);
        }
    }
}

// Check for success message from registration
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = 'Registration successful! Please login with your credentials.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Harar Ras Hotel</title>
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
            padding: 15px;
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 430px;
            position: relative;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.35);
        }
        
        .back-button:hover {
            color: white;
            background: rgba(0, 0, 0, 0.55);
            transform: translateX(-3px);
        }
        
        .back-button i {
            margin-right: 8px;
            font-size: 12px;
        }
        
        .login-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            padding: 28px 32px;
            width: 100%;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 6px;
        }
        
        .login-header p {
            color: #718096;
            font-size: 14px;
            margin: 0;
        }
        
        .form-floating {
            margin-bottom: 14px;
            position: relative;
        }
        
        .form-floating input {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 14px 6px 14px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8fafc;
            height: 52px;
        }
        
        .form-floating input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
            background: white;
        }
        
        .form-floating label {
            color: #718096;
            font-weight: 500;
            padding: 14px;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0aec0;
            transition: color 0.3s ease;
            z-index: 10;
            padding: 4px;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
        
        .forgot-password {
            text-align: right;
            margin-bottom: 16px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .forgot-password a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }
        
        .btn-signin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-signin:active {
            transform: translateY(0);
        }
        
        .btn-signin.loading {
            pointer-events: none;
        }
        
        .btn-signin .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        

        
        .divider {
            text-align: center;
            margin: 12px 0;
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
            padding: 0 15px;
            color: #a0aec0;
            font-size: 13px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .btn-create {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-create:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
        }

        .footer-text {
            text-align: center;
            margin-top: 14px;
            color: #a0aec0;
            font-size: 13px;
        }
        
        .alert {
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 16px;
            font-size: 13px;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #c53030;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }
        
        .alert-info {
            background: #bee3f8;
            color: #2b6cb0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                padding: 20px;
                border-radius: 14px;
            }
            
            .login-header h1 {
                font-size: 22px;
            }
            
            .form-floating input {
                font-size: 14px; /* Prevent zoom on iOS */
            }
            
            .back-button {
                font-size: 13px;
                padding: 5px 8px;
            }
            
            .form-floating {
                margin-bottom: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 18px 16px;
                border-radius: 12px;
            }
            
            .login-header h1 {
                font-size: 20px;
            }
            
            .login-header p {
                font-size: 13px;
            }
            
            .btn-signin, .btn-create {
                padding: 11px;
                font-size: 14px;
            }
        }
        
        /* Animation for form elements */
        .form-floating {
            animation: slideUp 0.6s ease-out;
        }
        
        .form-floating:nth-child(1) { animation-delay: 0.1s; }
        .form-floating:nth-child(2) { animation-delay: 0.2s; }
        .btn-signin { animation: slideUp 0.6s ease-out 0.3s both; }
        .divider { animation: slideUp 0.6s ease-out 0.4s both; }
        .btn-create { animation: slideUp 0.6s ease-out 0.5s both; }
        
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
        .form-floating input:focus,
        .btn-signin:focus,
        .btn-create:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 11px;
            border: 2px solid #dadce0;
            border-radius: 10px;
            background: white;
            color: #3c4043;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 4px;
        }

        .btn-google:hover {
            background: #f8f9fa;
            border-color: #c0c0c0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            color: #3c4043;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
        
        <div class="login-container">
            <div class="login-header">
                <h1>Login to Your Account</h1>
                <p>Welcome back! Please enter your details.</p>
            </div>
            
            <?php if ($redirect == 'booking'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Room Booking in Progress</strong><br>
                    Please login to continue with your room reservation.
                </div>
            </div>
            <?php elseif ($redirect == 'food-booking'): ?>
            <div class="alert alert-info">
                <i class="fas fa-utensils"></i>
                <div>
                    <strong>Food Ordering in Progress</strong><br>
                    Please login to continue with your food order.
                </div>
            </div>
            <?php endif; ?>
            
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
            
            <form method="POST" action="" id="loginForm" novalidate>
                <div class="form-floating">
                    <input type="text" 
                           name="email" 
                           id="email" 
                           class="form-control" 
                           placeholder="example@hararrashotel.com"
                           required
                           autocomplete="email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <label for="email">Email</label>
                </div>
                
                <div class="form-floating password-wrapper">
                    <input type="password" 
                           name="password" 
                           id="password" 
                           class="form-control" 
                           placeholder="Password"
                           required
                           autocomplete="current-password">
                    <label for="password">Password</label>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
                
                <div class="forgot-password">
                    <a href="forgot-password.php">
                        Forgot Password?
                    </a>
                </div>
                
                <button type="submit" class="btn-signin" id="signinBtn">
                    <div class="spinner" id="spinner"></div>
                    <span id="btnText">Login</span>
                </button>
            </form>
            
            <?php
            // Google OAuth button
            if (file_exists('includes/services/GoogleOAuthService.php')):
                require_once 'includes/services/GoogleOAuthService.php';
                $oauth_service = new GoogleOAuthService($conn);
                if ($oauth_service->isConfigured()):
                    $state_params = [];
                    if ($redirect) {
                        $state_params['redirect'] = $redirect;
                        if ($room_id) $state_params['room'] = $room_id;
                    }
                    $state = base64_encode(http_build_query($state_params));
                    $google_auth_url = $oauth_service->getAuthUrl($state);
            ?>
            <div class="divider"><span>or</span></div>
            <a href="<?php echo htmlspecialchars($google_auth_url); ?>" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 24 24" style="margin-right:8px;vertical-align:middle;">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </a>
            <?php endif; endif; ?>

            <div class="divider">
                <span>or</span>
            </div>
            
            <a href="register.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) . ($room_id ? '&room=' . $room_id : '') : ''; ?>" 
               class="btn-create">
                Create New Account
            </a>
            
            <div class="footer-text">
                Don't have an account? Click "Create New Account" above
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle eye icon
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Form submission with loading state
            const loginForm = document.getElementById('loginForm');
            const signinBtn = document.getElementById('signinBtn');
            const spinner = document.getElementById('spinner');
            const btnText = document.getElementById('btnText');
            
            loginForm.addEventListener('submit', function(e) {
                // Show loading state
                signinBtn.classList.add('loading');
                spinner.style.display = 'inline-block';
                btnText.textContent = 'Signing In...';
                signinBtn.disabled = true;
                
                // Basic client-side validation
                const emailOrUsername = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                
                if (!emailOrUsername || !password) {
                    e.preventDefault();
                    resetButton();
                    showError('Please fill in all fields');
                    return false;
                }
            });
            
            function resetButton() {
                signinBtn.classList.remove('loading');
                spinner.style.display = 'none';
                btnText.textContent = 'Login';
                signinBtn.disabled = false;
            }
            
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            function showError(message) {
                // Remove existing error alerts
                const existingAlerts = document.querySelectorAll('.alert-danger');
                existingAlerts.forEach(alert => alert.remove());
                
                // Create new error alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger';
                alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
                
                // Insert before the form
                const form = document.getElementById('loginForm');
                form.parentNode.insertBefore(alertDiv, form);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
            
            // Auto-focus email field if empty
            const emailInput = document.getElementById('email');
            if (!emailInput.value) {
                emailInput.focus();
            }
            
            // Remove success message after 5 seconds
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    setTimeout(() => successAlert.remove(), 300);
                }, 5000);
            }
        });
        
        function showForgotPassword() {
            alert('To reset your password, please contact hotel administration at:\n\nEmail: admin@hararrashotel.com\nPhone: +251-25-666-0000\n\nOr visit the hotel reception desk.');
        }
        

        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>