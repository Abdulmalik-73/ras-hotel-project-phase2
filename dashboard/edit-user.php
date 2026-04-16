<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Get user
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: manage-users.php?error=User not found');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $password = $_POST['password'] ?? '';
    $role = sanitize_input($_POST['role']);
    $status = sanitize_input($_POST['status']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($role)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email address';
    } elseif ($email !== $user['email'] && get_user_by_email($email)) {
        $error = 'Email already exists';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Track changes
        $changed_fields = [];
        $old_values = [];
        $new_values = [];
        
        if ($first_name !== $user['first_name']) {
            $changed_fields[] = 'first_name';
            $old_values['first_name'] = $user['first_name'];
            $new_values['first_name'] = $first_name;
        }
        if ($last_name !== $user['last_name']) {
            $changed_fields[] = 'last_name';
            $old_values['last_name'] = $user['last_name'];
            $new_values['last_name'] = $last_name;
        }
        if ($email !== $user['email']) {
            $changed_fields[] = 'email';
            $old_values['email'] = $user['email'];
            $new_values['email'] = $email;
        }
        if ($phone !== $user['phone']) {
            $changed_fields[] = 'phone';
            $old_values['phone'] = $user['phone'];
            $new_values['phone'] = $phone;
        }
        if ($role !== $user['role']) {
            $changed_fields[] = 'role';
            $old_values['role'] = $user['role'];
            $new_values['role'] = $role;
        }
        if ($status !== $user['status']) {
            $changed_fields[] = 'status';
            $old_values['status'] = $user['status'];
            $new_values['status'] = $status;
        }
        if (!empty($password)) {
            $changed_fields[] = 'password';
            $old_values['password'] = '***';
            $new_values['password'] = '***';
        }
        
        // Update user
        if (!empty($password)) {
            $hashed_password = hash_password($password);
            $update_query = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, password=?, role=?, status=? WHERE id=?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssssssi", $first_name, $last_name, $email, $phone, $hashed_password, $role, $status, $user_id);
        } else {
            $update_query = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, status=? WHERE id=?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ssssssi", $first_name, $last_name, $email, $phone, $role, $status, $user_id);
        }
        
        if ($update_stmt->execute()) {
            // Log audit (only if admin_id is valid)
            if (!empty($changed_fields) && isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                $audit_query = "INSERT INTO admin_audit_logs (admin_id, action, target_user_id, changed_fields, old_values, new_values, ip_address) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)";
                $audit_stmt = $conn->prepare($audit_query);
                $action = 'update';
                $changed_json = json_encode($changed_fields);
                $old_json = json_encode($old_values);
                $new_json = json_encode($new_values);
                $ip = $_SERVER['REMOTE_ADDR'];
                $types = "i" . "s" . "i" . "s" . "s" . "s" . "s";
                $audit_stmt->bind_param($types, $_SESSION['user_id'], $action, $user_id, $changed_json, $old_json, $new_json, $ip);
                $audit_stmt->execute();
                
                // Send email notification if email, password, or status changed
                if (in_array('email', $changed_fields) || in_array('password', $changed_fields) || in_array('status', $changed_fields)) {
                    send_account_update_email($user_id, $changed_fields);
                }
            }
            
            $message = 'User updated successfully!';
            header('Location: manage-users.php?message=User updated successfully');
            exit();
        } else {
            $error = 'Failed to update user: ' . $update_stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Dashboard</title>
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
                            <h2 class="d-inline"><i class="fas fa-user-edit me-2"></i> Edit User</h2>
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
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
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
                                        <option value="customer" <?php echo $user['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                        <option value="receptionist" <?php echo $user['role'] === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                                        <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="super_admin" <?php echo $user['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password (leave blank to keep current)</label>
                                    <input type="password" name="password" class="form-control" minlength="6">
                                    <small class="text-muted">Minimum 6 characters if changing</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Buttons -->
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Update User
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
