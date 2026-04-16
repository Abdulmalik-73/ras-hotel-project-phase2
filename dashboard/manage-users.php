<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

$message = '';
$error = '';
$role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = sanitize_input($_POST['bulk_action']);
    $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    
    if (!empty($user_ids) && is_array($user_ids)) {
        $ids_str = implode(',', array_map('intval', $user_ids));
        
        if ($bulk_action === 'activate') {
            $update_query = "UPDATE users SET status = 'active' WHERE id IN ($ids_str)";
            if ($conn->query($update_query)) {
                $message = 'Selected users activated successfully!';
            } else {
                $error = 'Failed to activate users';
            }
        } elseif ($bulk_action === 'deactivate') {
            $update_query = "UPDATE users SET status = 'inactive' WHERE id IN ($ids_str)";
            if ($conn->query($update_query)) {
                $message = 'Selected users deactivated successfully!';
            } else {
                $error = 'Failed to deactivate users';
            }
        }
    } else {
        $error = 'Please select at least one user';
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Build query for export
    $where_clauses = [];
    if ($role_filter && in_array($role_filter, ['customer', 'receptionist', 'manager', 'admin', 'super_admin'])) {
        $where_clauses[] = "role = '$role_filter'";
    }
    if ($search) {
        $where_clauses[] = "(first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
    }
    
    $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    $export_query = "SELECT id, first_name, last_name, email, phone, role, status, created_at, updated_at FROM users $where ORDER BY created_at DESC";
    $export_result = $conn->query($export_query);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Status', 'Created', 'Updated']);
    
    // Write data rows
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['role'],
            $row['status'],
            $row['created_at'],
            $row['updated_at']
        ]);
    }
    
    fclose($output);
    exit();
}

// Build query
$where_clauses = [];
if ($role_filter && in_array($role_filter, ['customer', 'receptionist', 'manager', 'admin', 'super_admin'])) {
    $where_clauses[] = "role = '$role_filter'";
}
if ($search) {
    $where_clauses[] = "(first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}

$where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM users $where";
$count_result = $conn->query($count_query);
$total = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Get users with OAuth information (oauth_tokens table may not exist)
$users_query = "SELECT u.*,
                CASE WHEN u.google_id IS NOT NULL THEN 'Google' ELSE NULL END as oauth_method
                FROM users u
                $where 
                ORDER BY u.created_at DESC 
                LIMIT $offset, $per_page";
$users = $conn->query($users_query);

// Get statistics
$stats = ['total_customers'=>0,'total_receptionists'=>0,'total_managers'=>0,'total_admins'=>0,'active_users'=>0];
$stats_result = $conn->query("SELECT 
                COUNT(CASE WHEN role = 'customer' THEN 1 END) as total_customers,
                COUNT(CASE WHEN role = 'receptionist' THEN 1 END) as total_receptionists,
                COUNT(CASE WHEN role = 'manager' THEN 1 END) as total_managers,
                COUNT(CASE WHEN role = 'admin' THEN 1 END) as total_admins,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users
                FROM users");
if ($stats_result) $stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
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
        .stat-card {
            border-radius: 10px;
            padding: 1.5rem;
            color: white;
            text-align: center;
            margin-bottom: 1rem;
        }
        .stat-card.blue { background: linear-gradient(135deg, #007bff, #0056b3); }
        .stat-card.green { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.orange { background: linear-gradient(135deg, #fd7e14, #ff6c00); }
        .stat-card.purple { background: linear-gradient(135deg, #6f42c1, #5a32a3); }
        .stat-card h4 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .badge-customer { background-color: #17a2b8; }
        .badge-receptionist { background-color: #ffc107; }
        .badge-manager { background-color: #fd7e14; }
        .badge-admin { background-color: #dc3545; }
        .badge-super_admin { background-color: #6f42c1; color: white; }
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
                        <a href="manage-bookings.php" class="nav-link">
                            <i class="fas fa-calendar-check me-2"></i> Bookings
                        </a>
                        <a href="manage-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Rooms
                        </a>
                        <a href="manage-services.php" class="nav-link">
                            <i class="fas fa-concierge-bell me-2"></i> Services
                        </a>
                        <a href="manage-users.php" class="nav-link active">
                            <i class="fas fa-users me-2"></i> Manage Users
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
                            <a href="admin.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-users me-2"></i> Manage Users</h2>
                        </div>
                        <a href="create-user.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Create New User
                        </a>
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
                    
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card blue">
                                <h4><?php echo $stats['total_customers']; ?></h4>
                                <p>Total Customers</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card orange">
                                <h4><?php echo $stats['total_receptionists']; ?></h4>
                                <p>Receptionists</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card green">
                                <h4><?php echo $stats['total_managers']; ?></h4>
                                <p>Managers</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card purple">
                                <h4><?php echo $stats['total_admins']; ?></h4>
                                <p>Admins</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="role" class="form-select">
                                        <option value="">All Roles</option>
                                        <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customers</option>
                                        <option value="receptionist" <?php echo $role_filter === 'receptionist' ? 'selected' : ''; ?>>Receptionists</option>
                                        <option value="manager" <?php echo $role_filter === 'manager' ? 'selected' : ''; ?>>Managers</option>
                                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                        <option value="super_admin" <?php echo $role_filter === 'super_admin' ? 'selected' : ''; ?>>Super Admins</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-2"></i> Search
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <a href="manage-users.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-redo me-2"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Bulk Actions -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <form method="POST" id="bulkActionForm" class="row g-2">
                                        <div class="col-auto">
                                            <select name="bulk_action" class="form-select form-select-sm">
                                                <option value="">Select Action</option>
                                                <option value="activate">Activate Selected</option>
                                                <option value="deactivate">Deactivate Selected</option>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-warning" id="bulkActionBtn" disabled>
                                                <i class="fas fa-cogs me-2"></i> Apply
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="?export=csv&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-download me-2"></i> Export to CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Users (<?php echo $total; ?> total)</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($users && $users->num_rows > 0): ?>
                            <form method="POST" id="usersForm">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>ID</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Role</th>
                                            <th>Auth Method</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($user = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="form-check-input user-checkbox">
                                            </td>
                                            <td><strong>#<?php echo $user['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['oauth_method']): ?>
                                                    <span class="badge bg-danger" title="OAuth User">
                                                        <i class="fab fa-google me-1"></i><?php echo $user['oauth_method']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary" title="Email/Password">
                                                        <i class="fas fa-key me-1"></i>Password
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($user['status'] === 'inactive'): ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Suspended</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            </form>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users found</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bulk action checkbox handling
        const selectAllCheckbox = document.getElementById('selectAll');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const bulkActionBtn = document.getElementById('bulkActionBtn');
        const bulkActionForm = document.getElementById('bulkActionForm');
        
        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkActionButton();
            });
        }
        
        // Update button state when individual checkboxes change
        userCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkActionButton();
                // Update select all checkbox state
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = Array.from(userCheckboxes).every(cb => cb.checked);
                }
            });
        });
        
        function updateBulkActionButton() {
            const checkedCount = Array.from(userCheckboxes).filter(cb => cb.checked).length;
            bulkActionBtn.disabled = checkedCount === 0;
        }
        
        // Bulk action form submission
        if (bulkActionForm) {
            bulkActionForm.addEventListener('submit', function(e) {
                const action = this.querySelector('select[name="bulk_action"]').value;
                if (!action) {
                    e.preventDefault();
                    alert('Please select an action');
                    return;
                }
                
                const checkedCount = Array.from(userCheckboxes).filter(cb => cb.checked).length;
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one user');
                    return;
                }
                
                if (!confirm(`Are you sure you want to ${action} ${checkedCount} user(s)?`)) {
                    e.preventDefault();
                }
            });
        }
        
        function deleteUser(userId) {
            console.log('deleteUser called with userId:', userId);
            
            if (confirm('⚠️ PERMANENT DELETION WARNING ⚠️\n\nAre you sure you want to PERMANENTLY DELETE this user?\n\nThis will:\n• Delete the user account permanently\n• Delete all their bookings and data\n• This action CANNOT be undone\n\nClick OK to permanently delete, or Cancel to keep the user.')) {
                console.log('User confirmed deletion');
                
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete-user.php';
                form.innerHTML = '<input type="hidden" name="user_id" value="' + userId + '">';
                document.body.appendChild(form);
                
                console.log('Form created and appended, submitting...');
                console.log('Form action:', form.action);
                console.log('Form method:', form.method);
                console.log('Form data:', new FormData(form));
                
                form.submit();
            } else {
                console.log('User cancelled deletion');
            }
        }
    </script>
</body>
</html>
