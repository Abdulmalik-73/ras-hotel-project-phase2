<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('super_admin', '../login.php');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get dashboard statistics
$stats = [];

// Total users
$total_users_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'super_admin'");
$stats['total_users'] = $total_users_result->fetch_assoc()['count'];

// Users by role
$role_stats = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE role != 'super_admin' GROUP BY role");
$stats['by_role'] = [];
while ($row = $role_stats->fetch_assoc()) {
    $stats['by_role'][$row['role']] = $row['count'];
}

// Active users
$active_users_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role != 'super_admin'");
$stats['active_users'] = $active_users_result->fetch_assoc()['count'];

// Inactive users
$inactive_users_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'inactive' AND role != 'super_admin'");
$stats['inactive_users'] = $inactive_users_result->fetch_assoc()['count'];

// Recent users
$recent_users = $conn->query("SELECT id, first_name, last_name, email, role, status, created_at FROM users WHERE role != 'super_admin' ORDER BY created_at DESC LIMIT 5");
$stats['recent_users'] = $recent_users->fetch_all(MYSQLI_ASSOC);

// Total bookings
$total_bookings_result = $conn->query("SELECT COUNT(*) as count FROM bookings");
$stats['total_bookings'] = $total_bookings_result->fetch_assoc()['count'];

// Total revenue
$total_revenue_result = $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE payment_status = 'paid'");
$revenue_row = $total_revenue_result->fetch_assoc();
$stats['total_revenue'] = $revenue_row['total'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Harar Ras Hotel</title>
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
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info p {
            margin: 0;
            font-size: 14px;
            color: #718096;
        }
        
        .user-info strong {
            display: block;
            color: #2d3748;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
        }
        
        .stat-card.users {
            border-left-color: #667eea;
        }
        
        .stat-card.active {
            border-left-color: #28a745;
        }
        
        .stat-card.inactive {
            border-left-color: #ffc107;
        }
        
        .stat-card.revenue {
            border-left-color: #17a2b8;
        }
        
        .stat-card.bookings {
            border-left-color: #e83e8c;
        }
        
        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
        }
        
        .stat-icon {
            float: right;
            font-size: 40px;
            opacity: 0.1;
            color: #667eea;
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
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
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
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
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
            <li><a href="super-admin.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="super-admin-users.php"><i class="fas fa-users"></i> User Management</a></li>
            <li><a href="super-admin-settings.php"><i class="fas fa-cog"></i> System Settings</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1><i class="fas fa-tachometer-alt"></i> Super Admin Dashboard</h1>
            <div class="user-menu">
                <div class="user-info">
                    <p>Welcome back,</p>
                    <strong><?php echo htmlspecialchars($user_name); ?></strong>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label">Active Users</div>
                <div class="stat-value"><?php echo $stats['active_users']; ?></div>
            </div>
            
            <div class="stat-card inactive">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-label">Inactive Users</div>
                <div class="stat-value"><?php echo $stats['inactive_users']; ?></div>
            </div>
            
            <div class="stat-card bookings">
                <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
            </div>
            
            <div class="stat-card revenue">
                <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">ETB <?php echo number_format($stats['total_revenue'], 0); ?></div>
            </div>
        </div>
        
        <!-- Users by Role -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-chart-pie"></i> Users by Role</h2>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="role-badge customer">Customer</span></td>
                                <td><strong><?php echo $stats['by_role']['customer'] ?? 0; ?></strong></td>
                            </tr>
                            <tr>
                                <td><span class="role-badge receptionist">Receptionist</span></td>
                                <td><strong><?php echo $stats['by_role']['receptionist'] ?? 0; ?></strong></td>
                            </tr>
                            <tr>
                                <td><span class="role-badge manager">Manager</span></td>
                                <td><strong><?php echo $stats['by_role']['manager'] ?? 0; ?></strong></td>
                            </tr>
                            <tr>
                                <td><span class="role-badge admin">Admin</span></td>
                                <td><strong><?php echo $stats['by_role']['admin'] ?? 0; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-user-plus"></i> Recent Users</h2>
                <a href="super-admin-users.php" class="btn-primary">
                    <i class="fas fa-arrow-right"></i> View All Users
                </a>
            </div>
            
            <?php if (count($stats['recent_users']) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_users'] as $user): ?>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No users found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
