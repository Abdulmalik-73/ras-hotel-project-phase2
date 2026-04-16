<?php
/**
 * Initialize Superadmin Dashboard
 * 
 * This is a special administrative interface to create, manage, and access
 * the superadmin account. It provides multiple ways to ensure superadmin access.
 */

// Include database configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Security: Only allow access from localhost for initial setup
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($client_ip, $allowed_ips) && $client_ip !== '127.0.0.1') {
    // For production, you might want to restrict this further
    // die('Access denied. This page is only accessible from localhost.');
}

$message = '';
$error = '';
$superadmin_info = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_superadmin':
            $result = createSuperadmin($conn);
            if ($result['success']) {
                $message = $result['message'];
                $superadmin_info = $result;
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'reset_password':
            $result = resetSuperadminPassword($conn);
            if ($result['success']) {
                $message = $result['message'];
                $superadmin_info = $result;
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'check_status':
            $result = checkSuperadminStatus($conn);
            $superadmin_info = $result;
            break;
    }
}

// Function to create superadmin
function createSuperadmin($conn) {
    try {
        // Check if superadmin already exists
        $check_query = "SELECT id, email, first_name, last_name, created_at FROM users WHERE role = 'super_admin' LIMIT 1";
        $result = $conn->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            $existing = $result->fetch_assoc();
            return [
                'success' => true,
                'message' => 'Superadmin already exists! You can login with existing credentials.',
                'email' => $existing['email'],
                'name' => $existing['first_name'] . ' ' . $existing['last_name'],
                'created_at' => $existing['created_at'],
                'action' => 'exists'
            ];
        }
        
        // Create new superadmin account
        $password_hash = password_hash('123456', PASSWORD_DEFAULT);
        
        $insert_query = "INSERT INTO users (first_name, last_name, username, email, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_query);
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $first_name = 'Super';
        $last_name = 'Admin';
        $username = 'superadmin';
        $email = 'superadmin@gmail.com';
        $phone = '+251911000000';
        $role = 'super_admin';
        $status = 'active';
        
        $stmt->bind_param("ssssssss", $first_name, $last_name, $username, $email, $phone, $password_hash, $role, $status);
        
        if ($stmt->execute()) {
            $stmt->close();
            return [
                'success' => true,
                'message' => 'Superadmin account created successfully!',
                'email' => $email,
                'password' => '123456',
                'name' => $first_name . ' ' . $last_name,
                'action' => 'created'
            ];
        } else {
            throw new Exception("Failed to create superadmin: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'action' => 'error'
        ];
    }
}

// Function to reset superadmin password
function resetSuperadminPassword($conn) {
    try {
        $new_password = '123456';
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_query = "UPDATE users SET password = ?, updated_at = NOW() WHERE role = 'super_admin'";
        $stmt = $conn->prepare($update_query);
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $password_hash);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected_rows > 0) {
                return [
                    'success' => true,
                    'message' => 'Superadmin password reset successfully!',
                    'password' => $new_password,
                    'action' => 'reset'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No superadmin account found to reset.',
                    'action' => 'not_found'
                ];
            }
        } else {
            throw new Exception("Failed to reset password: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'action' => 'error'
        ];
    }
}

// Function to check superadmin status
function checkSuperadminStatus($conn) {
    try {
        $query = "SELECT id, first_name, last_name, username, email, phone, status, last_login, created_at FROM users WHERE role = 'super_admin'";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            return [
                'exists' => true,
                'data' => $admin,
                'message' => 'Superadmin account found and active.'
            ];
        } else {
            return [
                'exists' => false,
                'message' => 'No superadmin account found in the system.'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'exists' => false,
            'message' => 'Error checking status: ' . $e->getMessage()
        ];
    }
}

// Auto-check status on page load
if (empty($_POST)) {
    $superadmin_info = checkSuperadminStatus($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialize Superadmin - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            padding-top: 30px;
            padding-bottom: 30px;
        }
        
        .admin-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border: none;
        }
        
        .card-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .card-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .status-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .status-card.success {
            border-color: #28a745;
            background: #f8fff9;
        }
        
        .status-card.warning {
            border-color: #ffc107;
            background: #fffdf5;
        }
        
        .status-card.danger {
            border-color: #dc3545;
            background: #fff5f5;
        }
        
        .btn-action {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .info-table {
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .info-table th {
            background: #e9ecef;
            font-weight: 600;
            padding: 12px 15px;
            border: none;
        }
        
        .info-table td {
            padding: 12px 15px;
            border: none;
            border-bottom: 1px solid #dee2e6;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert i {
            margin-right: 10px;
        }
        
        .credentials-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .credentials-box h5 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .credential-item {
            background: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 8px 0;
            border-left: 4px solid #ffc107;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateX(-3px);
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="../index.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="admin-card">
                    <div class="card-header text-center">
                        <h1><i class="fas fa-user-shield"></i> Initialize Superadmin</h1>
                        <p>Administrative Dashboard for Superadmin Account Management</p>
                    </div>
                    
                    <div class="card-body">
                        <!-- Status Messages -->
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Current Status -->
                        <div class="status-card <?php echo $superadmin_info['exists'] ?? false ? 'success' : 'warning'; ?>">
                            <h4>
                                <i class="fas fa-<?php echo $superadmin_info['exists'] ?? false ? 'check-circle text-success' : 'exclamation-triangle text-warning'; ?>"></i>
                                Current Status
                            </h4>
                            <p class="mb-3"><?php echo htmlspecialchars($superadmin_info['message'] ?? 'Checking status...'); ?></p>
                            
                            <?php if (isset($superadmin_info['exists']) && $superadmin_info['exists']): ?>
                                <div class="info-table">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <th width="150">Name:</th>
                                            <td><?php echo htmlspecialchars($superadmin_info['data']['first_name'] . ' ' . $superadmin_info['data']['last_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?php echo htmlspecialchars($superadmin_info['data']['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Username:</th>
                                            <td><?php echo htmlspecialchars($superadmin_info['data']['username']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td><span class="badge bg-success"><?php echo ucfirst($superadmin_info['data']['status']); ?></span></td>
                                        </tr>
                                        <tr>
                                            <th>Created:</th>
                                            <td><?php echo date('M j, Y g:i A', strtotime($superadmin_info['data']['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Last Login:</th>
                                            <td><?php echo $superadmin_info['data']['last_login'] ? date('M j, Y g:i A', strtotime($superadmin_info['data']['last_login'])) : 'Never'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Login Credentials Display -->
                        <?php if (isset($superadmin_info['action']) && in_array($superadmin_info['action'], ['created', 'reset'])): ?>
                            <div class="credentials-box">
                                <h5><i class="fas fa-key"></i> Login Credentials</h5>
                                <div class="credential-item">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($superadmin_info['email'] ?? 'superadmin@gmail.com'); ?>
                                </div>
                                <div class="credential-item">
                                    <strong>Password:</strong> <?php echo htmlspecialchars($superadmin_info['password'] ?? '123456'); ?>
                                </div>
                                <div class="mt-3">
                                    <small class="text-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>Important:</strong> Change the password immediately after first login for security!
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="action-grid">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="create_superadmin">
                                <button type="submit" class="btn btn-primary btn-action w-100">
                                    <i class="fas fa-plus-circle"></i>
                                    Create Superadmin
                                </button>
                                <small class="text-muted d-block mt-1">Creates a new superadmin account if none exists</small>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="reset_password">
                                <button type="submit" class="btn btn-warning btn-action w-100">
                                    <i class="fas fa-key"></i>
                                    Reset Password
                                </button>
                                <small class="text-muted d-block mt-1">Resets superadmin password to default (123456)</small>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="check_status">
                                <button type="submit" class="btn btn-success btn-action w-100">
                                    <i class="fas fa-sync-alt"></i>
                                    Refresh Status
                                </button>
                                <small class="text-muted d-block mt-1">Check current superadmin account status</small>
                            </form>
                            
                            <a href="../login.php" class="btn btn-primary btn-action w-100">
                                <i class="fas fa-sign-in-alt"></i>
                                Go to Login
                            </a>
                            <small class="text-muted d-block mt-1">Access the main login page</small>
                        </div>
                        
                        <!-- Quick Access Links -->
                        <div class="mt-4 pt-4 border-top">
                            <h5><i class="fas fa-external-link-alt"></i> Quick Access</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <a href="../login.php" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                        <i class="fas fa-sign-in-alt"></i> Main Login Page
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="../setup/init-superadmin.php" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                                        <i class="fas fa-cog"></i> Setup Script
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Notice -->
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-shield-alt"></i>
                            <strong>Security Notice:</strong> This page should only be accessible during initial setup or emergency recovery. 
                            Consider restricting access to this page in production environments.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh status every 30 seconds
        setTimeout(function() {
            if (!document.querySelector('.alert-success') && !document.querySelector('.alert-danger')) {
                document.querySelector('form[action*="check_status"] button').click();
            }
        }, 30000);
        
        // Confirmation for reset password
        document.querySelector('form[action*="reset_password"]').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to reset the superadmin password to default (123456)?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>