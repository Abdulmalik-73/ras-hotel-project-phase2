<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize_input($_POST['role']);
    $status = sanitize_input($_POST['status']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!in_array($role, ['customer', 'receptionist', 'manager', 'admin', 'super_admin'])) {
        $error = 'Invalid role selected';
    } else {
        // Check if email exists
        if (get_user_by_email($email)) {
            $error = 'Email already exists';
        } else {
            // Create user
            $hashed_password = hash_password($password);
            
            // Generate username from email (part before @)
            $username = strtolower(explode('@', $email)[0]);
            
            // Check if username exists, if so add a number
            $original_username = $username;
            $counter = 1;
            while (true) {
                $check_query = "SELECT id FROM users WHERE username = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows == 0) {
                    break; // Username is available
                }
                
                $username = $original_username . $counter;
                $counter++;
            }
            
            $query = "INSERT INTO users (first_name, last_name, username, email, phone, password, role, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssss", $first_name, $last_name, $username, $email, $phone, $hashed_password, $role, $status);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Log audit (only if admin_id is valid)
                if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                    $audit_query = "INSERT INTO admin_audit_logs (admin_id, action, target_user_id, changed_fields, new_values, ip_address) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                    $audit_stmt = $conn->prepare($audit_query);
                    $action = 'create';
                    $changed_fields = json_encode(['first_name', 'last_name', 'email', 'phone', 'role', 'status']);
                    $new_values = json_encode(['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'phone' => $phone, 'role' => $role, 'status' => $status]);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $audit_stmt->bind_param("isssss", $_SESSION['user_id'], $action, $user_id, $changed_fields, $new_values, $ip);
                    $audit_stmt->execute();
                }
                
                // Send welcome email
                send_account_creation_email($user_id);
                
                $message = 'User created successfully!';
                header('Location: manage-users.php?message=User created successfully');
                exit();
            } else {
                $error = 'Failed to create user: ' . $stmt->error;
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
    <title>Create User - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .navbar-admin {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .form-section h5 {
            color: #007bff;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-admin">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel - Admin Dashboard
            </a>
            <div class="ms-auto">
                
                <span class="text-white me-3">
                    <i class="fas fa-user-shield"></i> Admin
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-user-shield"></i> Admin Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="admin.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="manage-users.php" class="nav-link active">
                            <i class="fas fa-users me-2"></i> Manage Users
                        </a>
                        <a href="manage-bookings.php" class="nav-link">
                            <i class="fas fa-calendar-check me-2"></i> Bookings
                        </a>
                        <a href="manage-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Rooms
                        </a>
                        <a href="settings.php" class="nav-link">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </nav>
                    
                    <div class="mt-auto">
                        <a href="../logout.php" class="nav-link text-white">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manage-users.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Users
                            </a>
                            <h2 class="d-inline"><i class="fas fa-user-plus me-2"></i> Create New User</h2>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="row">
                        <!-- Personal Information -->
                        <div class="col-lg-6">
                            <div class="form-section">
                                <h5><i class="fas fa-user me-2"></i> Personal Information</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Information -->
                        <div class="col-lg-6">
                            <div class="form-section">
                                <h5><i class="fas fa-lock me-2"></i> Account Information</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Role *</label>
                                    <select name="role" class="form-select" required>
                                        <option value="">Select Role</option>
                                        <option value="customer">Customer</option>
                                        <option value="receptionist">Receptionist</option>
                                        <option value="manager">Manager</option>
                                        <option value="admin">Admin</option>
                                        <option value="super_admin">Super Admin</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" name="password" class="form-control" required minlength="6">
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Buttons -->
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Create User
                                </button>
                                <a href="manage-users.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
