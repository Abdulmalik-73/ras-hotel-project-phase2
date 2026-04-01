<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('admin');

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Get audit logs for this user
$audit_query = "SELECT * FROM admin_audit_logs WHERE target_user_id = ? ORDER BY timestamp DESC LIMIT 20";
$audit_stmt = $conn->prepare($audit_query);
$audit_stmt->bind_param("i", $user_id);
$audit_stmt->execute();
$audit_logs = $audit_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Admin Dashboard</title>
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
        .info-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .info-section h5 {
            color: #007bff;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #333;
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
                            <h2 class="d-inline"><i class="fas fa-user me-2"></i> User Details</h2>
                        </div>
                        <div>
                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i> Edit
                            </a>
                        </div>
                    </div>
                    
                    <!-- User Information -->
                    <div class="info-section">
                        <h5><i class="fas fa-user me-2"></i> Personal Information</h5>
                        <div class="info-row">
                            <span class="info-label">User ID:</span>
                            <span class="info-value">#<?php echo $user['id']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Account Information -->
                    <div class="info-section">
                        <h5><i class="fas fa-lock me-2"></i> Account Information</h5>
                        <div class="info-row">
                            <span class="info-label">Role:</span>
                            <span class="info-value">
                                <span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif ($user['status'] === 'inactive'): ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Suspended</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Created:</span>
                            <span class="info-value"><?php echo date('M j, Y H:i', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Last Updated:</span>
                            <span class="info-value"><?php echo date('M j, Y H:i', strtotime($user['updated_at'])); ?></span>
                        </div>
                    </div>
                    
                    <!-- Activity Log -->
                    <div class="info-section">
                        <h5><i class="fas fa-history me-2"></i> Activity Log</h5>
                        <?php if ($audit_logs && $audit_logs->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Action</th>
                                        <th>Changed Fields</th>
                                        <th>Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($log = $audit_logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y H:i', strtotime($log['timestamp'])); ?></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($log['action']); ?></span></td>
                                        <td>
                                            <?php 
                                            $fields = json_decode($log['changed_fields'], true);
                                            echo implode(', ', $fields ?? []);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $admin_query = "SELECT first_name, last_name FROM users WHERE id = ?";
                                            $admin_stmt = $conn->prepare($admin_query);
                                            $admin_stmt->bind_param("i", $log['admin_id']);
                                            $admin_stmt->execute();
                                            $admin = $admin_stmt->get_result()->fetch_assoc();
                                            echo $admin ? htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) : 'System';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">No activity log available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
