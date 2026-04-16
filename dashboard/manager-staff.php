<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

// Handle staff actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $first_name = sanitize_input($_POST['first_name']);
            $last_name = sanitize_input($_POST['last_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $password = hash_password($_POST['password']);
            $role = sanitize_input($_POST['role']);
            $status = sanitize_input($_POST['status']);
            
            // Generate username from email (part before @)
            $username = strtolower(explode('@', $email)[0]);
            
            // Make username unique if it already exists
            $base_username = $username;
            $counter = 1;
            $check_username_query = "SELECT id FROM users WHERE username = ?";
            $check_stmt = $conn->prepare($check_username_query);
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            while ($check_result->num_rows > 0) {
                $username = $base_username . $counter;
                $counter++;
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
            }
            
            // Check if email already exists
            $check_query = "SELECT id FROM users WHERE email = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                set_message('error', 'Email already exists');
            } else {
                $query = "INSERT INTO users (first_name, last_name, username, email, phone, password, role, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssssss", $first_name, $last_name, $username, $email, $phone, $password, $role, $status);
                
                if ($stmt->execute()) {
                    set_message('success', 'Staff member added successfully with username: ' . $username);
                } else {
                    set_message('error', 'Failed to add staff member: ' . $stmt->error);
                }
            }
            break;
            
        case 'update':
            $user_id = (int)$_POST['user_id'];
            $first_name = sanitize_input($_POST['first_name']);
            $last_name = sanitize_input($_POST['last_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $role = sanitize_input($_POST['role']);
            $status = sanitize_input($_POST['status']);
            
            // Check if email already exists for other users
            $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("si", $email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                set_message('error', 'Email already exists');
            } else {
                if (!empty($_POST['password'])) {
                    // Update with password
                    $password = hash_password($_POST['password']);
                    $query = "UPDATE users SET first_name = ?, last_name = ?, 
                              email = ?, phone = ?, role = ?, status = ?, password = ? 
                              WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sssssssi", $first_name, $last_name, $email, $phone, $role, $status, $password, $user_id);
                } else {
                    // Update without password
                    $query = "UPDATE users SET first_name = ?, last_name = ?, 
                              email = ?, phone = ?, role = ?, status = ? 
                              WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssssi", $first_name, $last_name, $email, $phone, $role, $status, $user_id);
                }
                
                if ($stmt->execute()) {
                    set_message('success', 'Staff member updated successfully');
                } else {
                    set_message('error', 'Failed to update staff member: ' . $stmt->error);
                }
            }
            break;
            
        case 'delete':
            $user_id = (int)$_POST['user_id'];
            
            // Don't allow deleting the current user
            if ($user_id == $_SESSION['user_id']) {
                set_message('error', 'Cannot delete your own account');
            } else {
                $query = "DELETE FROM users WHERE id = $user_id AND role IN ('receptionist', 'manager')";
                if ($conn->query($query)) {
                    set_message('success', 'Staff member deleted successfully');
                } else {
                    set_message('error', 'Failed to delete staff member');
                }
            }
            break;
    }
    header('Location: manager-staff.php');
    exit();
}

// Get staff members (managers and receptionists only)
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = ["role IN ('manager', 'receptionist')"];
if ($role_filter) {
    $where_conditions[] = "role = '" . sanitize_input($role_filter) . "'";
}
if ($status_filter) {
    $where_conditions[] = "status = '" . sanitize_input($status_filter) . "'";
}
if ($search) {
    $search_term = sanitize_input($search);
    $where_conditions[] = "(first_name LIKE '%$search_term%' OR last_name LIKE '%$search_term%' OR email LIKE '%$search_term%')";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$staff_query = "SELECT * FROM users $where_clause ORDER BY role, created_at DESC";
$staff = $conn->query($staff_query);

// Get staff statistics
$staff_stats_query = "SELECT 
                      COUNT(*) as total_staff,
                      COUNT(CASE WHEN role = 'manager' THEN 1 END) as managers,
                      COUNT(CASE WHEN role = 'receptionist' THEN 1 END) as receptionists,
                      COUNT(CASE WHEN status = 'active' THEN 1 END) as active_staff
                      FROM users 
                      WHERE role IN ('manager', 'receptionist')";

$staff_stats = $conn->query($staff_stats_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-manager {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%) !important;
        }
        .navbar-manager .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
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
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .badge {
            font-size: 0.75em;
        }
        .stat-card {
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
            text-align: center;
        }
        .stat-card h4 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-card.purple { background: linear-gradient(135deg, #8e44ad, #9b59b6); }
        .stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-card.green { background: linear-gradient(135deg, #27ae60, #229954); }
        .stat-card.orange { background: linear-gradient(135deg, #f39c12, #e67e22); }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-manager">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> 
                <span class="text-white fw-bold">Harar Ras Hotel - Manager Dashboard</span>
            </a>
            <div class="ms-auto">
                
                <span class="text-white me-3">
                    <i class="fas fa-user-tie"></i> Manager
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
                        <i class="fas fa-user-tie"></i> Manager Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="manager.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Overview
                        </a>
                        <a href="manager-bookings.php" class="nav-link">
                            <i class="fas fa-calendar-check me-2"></i> Manage Bookings
                        </a>
                        <a href="manager-approve-bill.php" class="nav-link">
                            <i class="fas fa-check-circle me-2"></i> Approve Bill
                        </a>
                        <a href="manager-feedback.php" class="nav-link">
                            <i class="fas fa-star me-2"></i> Customer Feedback
                        </a>
                        <a href="manager-refund.php" class="nav-link">
                            <i class="fas fa-undo-alt me-2"></i> Refund Management
                        </a>
                        <a href="manager-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Room Management
                        </a>
                        <a href="manager-staff.php" class="nav-link active">
                            <i class="fas fa-users me-2"></i> Staff Management
                        </a>
                        <a href="manager-reports.php" class="nav-link">
                            <i class="fas fa-chart-bar me-2"></i> Reports
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
                            <a href="manager.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-users me-2"></i> Staff Management</h2>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class="fas fa-plus me-2"></i> Add Staff Member
                        </button>
                    </div>
                    
                    <?php display_message(); ?>
                    
                    <!-- Staff Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card purple">
                                <h4><?php echo $staff_stats['total_staff']; ?></h4>
                                <p><i class="fas fa-users me-2"></i>Total Staff</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card blue">
                                <h4><?php echo $staff_stats['managers']; ?></h4>
                                <p><i class="fas fa-user-tie me-2"></i>Managers</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card green">
                                <h4><?php echo $staff_stats['receptionists']; ?></h4>
                                <p><i class="fas fa-user-check me-2"></i>Receptionists</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card orange">
                                <h4><?php echo $staff_stats['active_staff']; ?></h4>
                                <p><i class="fas fa-user-clock me-2"></i>Active</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-select">
                                        <option value="">All Roles</option>
                                        <option value="manager" <?php echo $role_filter == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="receptionist" <?php echo $role_filter == 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-outline-primary d-block w-100">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Staff Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($staff && $staff->num_rows > 0): ?>
                                            <?php while ($member = $staff->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $member['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                    <td><?php echo $member['phone'] ? htmlspecialchars($member['phone']) : 'N/A'; ?></td>
                                                    <td>
                                                        <?php
                                                        $role_badges = [
                                                            'manager' => 'primary',
                                                            'receptionist' => 'info'
                                                        ];
                                                        $badge_class = $role_badges[$member['role']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                                            <?php echo ucfirst($member['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_badges = [
                                                            'active' => 'success',
                                                            'inactive' => 'secondary',
                                                            'suspended' => 'danger'
                                                        ];
                                                        $status_badge = $status_badges[$member['status']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_badge; ?>">
                                                            <?php echo ucfirst($member['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($member['created_at'])); ?></td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editStaff(<?php echo htmlspecialchars(json_encode($member)); ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <?php if ($member['id'] != $_SESSION['user_id']): ?>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteStaff(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No staff members found</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Staff Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="">Select Role</option>
                                    <option value="manager">Manager</option>
                                    <option value="receptionist">Receptionist</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Staff Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Staff Modal -->
    <div class="modal fade" id="editStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Staff Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" id="edit_phone" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <select name="role" id="edit_role" class="form-select" required>
                                    <option value="manager">Manager</option>
                                    <option value="receptionist">Receptionist</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Staff Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStaff(staff) {
            document.getElementById('edit_user_id').value = staff.id;
            document.getElementById('edit_first_name').value = staff.first_name;
            document.getElementById('edit_last_name').value = staff.last_name;
            document.getElementById('edit_email').value = staff.email;
            document.getElementById('edit_phone').value = staff.phone || '';
            document.getElementById('edit_role').value = staff.role;
            document.getElementById('edit_status').value = staff.status;
            
            new bootstrap.Modal(document.getElementById('editStaffModal')).show();
        }
        
        function deleteStaff(staffId, staffName) {
            if (confirm(`Are you sure you want to delete staff member "${staffName}"?\n\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${staffId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>