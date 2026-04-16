<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('super_admin', '../login.php');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$error = '';
$success = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $role = sanitize_input($_POST['role']);
    $phone = sanitize_input($_POST['phone'] ?? '');
    $auto_generate = isset($_POST['auto_generate']) ? 1 : 0;
    $password = $auto_generate ? bin2hex(random_bytes(8)) : sanitize_input($_POST['password']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($role)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (!in_array($role, ['receptionist', 'manager', 'admin', 'super_admin'])) {
        $error = 'Invalid role selected';
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Email already exists in the system';
        } else {
            // Generate username from email
            $username = explode('@', $email)[0];
            
            // Check if username exists
            $username_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $username_check->bind_param("s", $username);
            $username_check->execute();
            if ($username_check->get_result()->num_rows > 0) {
                $username = $username . rand(100, 999);
            }
            
            // Hash password
            $hashed_password = hash_password($password);
            
            // Create user
            $insert_query = "INSERT INTO users (first_name, last_name, username, email, phone, password, role, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssssss", $first_name, $last_name, $username, $email, $phone, $hashed_password, $role);
            
            if ($insert_stmt->execute()) {
                $success = 'User account created successfully! Email: ' . $email . ' | Password: ' . $password;
                
                // Send welcome email if checkbox is checked
                if (isset($_POST['send_email'])) {
                    send_account_creation_email($conn->insert_id);
                }
            } else {
                $error = 'Failed to create user: ' . $insert_stmt->error;
            }
        }
    }
}

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $update_user_id = (int)$_POST['user_id'];
    $new_status = sanitize_input($_POST['status']);
    
    if (in_array($new_status, ['active', 'inactive', 'suspended'])) {
        $update_query = "UPDATE users SET status = ? WHERE id = ? AND role != 'super_admin'";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $new_status, $update_user_id);
        
        if ($update_stmt->execute()) {
            $success = 'User status updated successfully';
        } else {
            $error = 'Failed to update user status';
        }
    }
}

// Get all users (excluding super admin)
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$query = "SELECT * FROM users WHERE role != 'super_admin'";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
$users = $users_result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            color: white;
            overflow-y: auto;
        }
        
        .sidebar-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-menu li {
            margin-bottom: 10px;
        }
        
        .nav-menu a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .nav-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
        }
        
        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .top-bar h1 {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c82333;
            color: white;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 6px;
            display: block;
            font-size: 13px;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #c53030;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }
        
        .table {
            margin: 0;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e2e8f0;
            color: #2d3748;
            font-weight: 600;
            padding: 15px;
        }
        
        .table tbody td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .role-badge.customer {
            background: #e7f3ff;
            color: #0066cc;
        }
        
        .role-badge.receptionist {
            background: #fff4e6;
            color: #cc6600;
        }
        
        .role-badge.manager {
            background: #f0e6ff;
            color: #6600cc;
        }
        
        .role-badge.admin {
            background: #ffe6e6;
            color: #cc0000;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            max-width: 450px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #718096;
        }
        
        .close-btn:hover {
            color: #2d3748;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-size: 13px;
            color: #2d3748;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                min-height: auto;
                position: relative;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table thead th,
            .table tbody td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-crown"></i> Super Admin</h2>
            <p>Hotel Management System</p>
        </div>
        
        <ul class="nav-menu">
            <li><a href="super-admin.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="super-admin-users.php" class="active"><i class="fas fa-users"></i> User Management</a></li>
            <li><a href="admin.php"><i class="fas fa-cog"></i> System Settings</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <button class="btn-primary" onclick="openCreateUserModal()">
                <i class="fas fa-user-plus"></i> Create New User
            </button>
        </div>
        
        <!-- Alerts -->
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
        
        <!-- Filters Section -->
        <div class="section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or email" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-control">
                        <option value="">All Roles</option>
                        <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="receptionist" <?php echo $role_filter === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                        <option value="manager" <?php echo $role_filter === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="section">
            <div class="section-header">
                <h2>All Users (<?php echo count($users); ?>)</h2>
            </div>
            
            <?php if (count($users) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                    <td>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php elseif ($user['status'] === 'inactive'): ?>
                                            <span class="badge badge-warning">Inactive</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="openStatusModal(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                    <p style="color: #718096;">No users found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New User</h2>
                <button class="close-btn" onclick="closeCreateUserModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="receptionist">Receptionist</option>
                        <option value="manager">Manager</option>
                        <option value="admin">System Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-control">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="autoGenerate" name="auto_generate" onchange="togglePasswordField()">
                    <label for="autoGenerate">Auto-generate password</label>
                </div>
                
                <div class="form-group" id="passwordField" style="display: none;">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="sendEmail" name="send_email" checked>
                    <label for="sendEmail">Send welcome email</label>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="closeCreateUserModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update User Status</h2>
                <button class="close-btn" onclick="closeStatusModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="user_id" id="statusUserId">
                
                <div class="form-group">
                    <label class="form-label">New Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" class="btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="closeStatusModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openCreateUserModal() {
            document.getElementById('createUserModal').classList.add('show');
        }
        
        function closeCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('show');
        }
        
        function openStatusModal(userId, currentStatus) {
            document.getElementById('statusUserId').value = userId;
            document.querySelector('#statusModal select[name="status"]').value = currentStatus;
            document.getElementById('statusModal').classList.add('show');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('show');
        }
        
        function togglePasswordField() {
            const autoGenerate = document.getElementById('autoGenerate').checked;
            const passwordField = document.getElementById('passwordField');
            passwordField.style.display = autoGenerate ? 'none' : 'block';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createUserModal');
            const statusModal = document.getElementById('statusModal');
            
            if (event.target === createModal) {
                createModal.classList.remove('show');
            }
            if (event.target === statusModal) {
                statusModal.classList.remove('show');
            }
        }
    </script>
</body>
</html>
