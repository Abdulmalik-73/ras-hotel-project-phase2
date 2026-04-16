<?php
// Suppress PHP warnings and notices for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Allow both admin and super_admin to access settings
require_auth_roles(['admin', 'super_admin'], '../login.php');

// Detect user role for sidebar - check both possible session variables
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
$is_super_admin = ($user_role === 'super_admin');

// Security: super_admin must use their own settings page
if ($is_super_admin) {
    header('Location: super-admin-settings.php');
    exit;
}

$dashboard_link = 'admin.php';
$dashboard_title = 'Admin Panel';

// Handle settings update
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_settings') {
        $updated_count = 0;
        $errors = [];
        
        foreach ($_POST as $key => $value) {
            if ($key != 'action' && strpos($key, 'setting_') === 0) {
                $setting_key = str_replace('setting_', '', $key);
                $setting_value = sanitize_input($value);
                
                // Validate email settings
                if ($setting_key == 'contact_email' && !filter_var($setting_value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email address';
                    continue;
                }
                
                // Update or insert setting
                $query = "INSERT INTO hotel_settings (setting_key, setting_value, updated_by) 
                          VALUES ('$setting_key', '$setting_value', {$_SESSION['user_id']})
                          ON DUPLICATE KEY UPDATE 
                          setting_value = '$setting_value', 
                          updated_by = {$_SESSION['user_id']},
                          updated_at = NOW()";
                
                if ($conn->query($query)) {
                    $updated_count++;
                } else {
                    $errors[] = "Failed to update $setting_key: " . $conn->error;
                }
            }
        }
        
        if ($updated_count > 0 && empty($errors)) {
            set_message('success', "Successfully updated $updated_count settings");
        } elseif (!empty($errors)) {
            set_message('error', 'Some settings could not be updated: ' . implode(', ', $errors));
        }
        
        header('Location: settings.php');
        exit();
    }
}

// Get current settings
$settings_query = "SELECT * FROM hotel_settings ORDER BY setting_key";
$settings_result = $conn->query($settings_query);

$settings = [];
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row;
    }
}

// Get system information
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => $conn->server_info,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'total_users' => 0,
    'total_rooms' => 0,
    'total_bookings' => 0,
    'total_services' => 0
];

// Get counts
$counts_queries = [
    'total_users' => "SELECT COUNT(*) as count FROM users",
    'total_rooms' => "SELECT COUNT(*) as count FROM rooms",
    'total_bookings' => "SELECT COUNT(*) as count FROM bookings",
    'total_services' => "SELECT COUNT(*) as count FROM services"
];

foreach ($counts_queries as $key => $query) {
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $system_info[$key] = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Harar Ras Hotel'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: <?php echo $is_super_admin ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)'; ?>;
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
        .settings-section {
            border-left: 4px solid #667eea;
            padding-left: 1rem;
        }
        .system-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        .system-info-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'hotel'; ?>"></i> <?php echo $dashboard_title; ?>
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="<?php echo $dashboard_link; ?>" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        
                        <?php if ($is_super_admin): ?>
                            <a href="super-admin-users.php" class="nav-link">
                                <i class="fas fa-users me-2"></i> User Management
                            </a>
                        <?php else: ?>
                            <a href="manage-rooms.php" class="nav-link">
                                <i class="fas fa-bed me-2"></i> Manage Rooms
                            </a>
                            <a href="manage-bookings.php" class="nav-link">
                                <i class="fas fa-calendar-check me-2"></i> Manage Bookings
                            </a>
                            <a href="manage-services.php" class="nav-link">
                                <i class="fas fa-concierge-bell me-2"></i> Manage Services
                            </a>
                        <?php endif; ?>
                        
                        <a href="settings.php" class="nav-link active">
                            <i class="fas fa-cog me-2"></i> <?php echo $is_super_admin ? 'System Settings' : 'Settings'; ?>
                        </a>
                    </nav>
                    
                    <div class="mt-auto">
                        <div class="text-white-50 small">
                            Logged in as: <?php echo $_SESSION['user_name'] ?? 'Admin'; ?>
                        </div>
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
                            <a href="<?php echo $dashboard_link; ?>" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-cog me-2"></i> <?php echo $is_super_admin ? 'System Settings' : 'Hotel Settings'; ?></h2>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-2"></i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <?php display_message(); ?>
                    
                    <div class="row">
                        <!-- Hotel Information Settings -->
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-hotel me-2"></i> Hotel Information</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_settings">
                                        
                                        <div class="settings-section mb-4">
                                            <h6 class="text-primary mb-3">Basic Information</h6>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Hotel Name</label>
                                                    <input type="text" name="setting_hotel_name" class="form-control" 
                                                           value="<?php echo htmlspecialchars($settings['hotel_name']['setting_value'] ?? 'Harar Ras Hotel'); ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Currency Symbol</label>
                                                    <input type="text" name="setting_currency_symbol" class="form-control" 
                                                           value="<?php echo htmlspecialchars($settings['currency_symbol']['setting_value'] ?? 'ETB'); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Hotel Address</label>
                                                <textarea name="setting_hotel_address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['hotel_address']['setting_value'] ?? 'Jugol Street, Harar, Ethiopia'); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="settings-section mb-4">
                                            <h6 class="text-primary mb-3">Contact Information</h6>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Contact Email</label>
                                                    <input type="email" name="setting_contact_email" class="form-control" 
                                                           value="<?php echo htmlspecialchars($settings['contact_email']['setting_value'] ?? 'info@hararrashotel.com'); ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Contact Phone</label>
                                                    <input type="tel" name="setting_contact_phone" class="form-control" 
                                                           value="<?php echo htmlspecialchars($settings['contact_phone']['setting_value'] ?? '+251-25-666-0000'); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="settings-section mb-4">
                                            <h6 class="text-primary mb-3">Operational Settings</h6>
                                            
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Check-in Time</label>
                                                    <input type="time" name="setting_check_in_time" class="form-control" 
                                                           value="<?php echo htmlspecialchars($settings['check_in_time']['setting_value'] ?? '14:00'); ?>">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Check-out Time</label>
                                                    <input type="time" name="setting_check_out_time" class="form-control" 
                                                           value="<?php echo htmlspecialchars($settings['check_out_time']['setting_value'] ?? '12:00'); ?>">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Tax Rate (%)</label>
                                                    <input type="number" name="setting_tax_rate" class="form-control" step="0.01" min="0" max="100"
                                                           value="<?php echo htmlspecialchars($settings['tax_rate']['setting_value'] ?? '15'); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i> Save Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Information -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> System Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="system-info-item">
                                        <span><i class="fab fa-php me-2"></i> PHP Version</span>
                                        <span class="badge bg-info"><?php echo $system_info['php_version']; ?></span>
                                    </div>
                                    <div class="system-info-item">
                                        <span><i class="fas fa-database me-2"></i> MySQL Version</span>
                                        <span class="badge bg-info"><?php echo $system_info['mysql_version']; ?></span>
                                    </div>
                                    <div class="system-info-item">
                                        <span><i class="fas fa-server me-2"></i> Server</span>
                                        <span class="badge bg-secondary"><?php echo explode('/', $system_info['server_software'])[0]; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Database Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="system-info-item">
                                        <span><i class="fas fa-users me-2"></i> Total Users</span>
                                        <span class="badge bg-primary"><?php echo number_format($system_info['total_users']); ?></span>
                                    </div>
                                    <div class="system-info-item">
                                        <span><i class="fas fa-bed me-2"></i> Total Rooms</span>
                                        <span class="badge bg-success"><?php echo number_format($system_info['total_rooms']); ?></span>
                                    </div>
                                    <div class="system-info-item">
                                        <span><i class="fas fa-calendar-check me-2"></i> Total Bookings</span>
                                        <span class="badge bg-warning"><?php echo number_format($system_info['total_bookings']); ?></span>
                                    </div>
                                    <div class="system-info-item">
                                        <span><i class="fas fa-concierge-bell me-2"></i> Total Services</span>
                                        <span class="badge bg-info"><?php echo number_format($system_info['total_services']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-tools me-2"></i> Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-primary" onclick="clearCache()">
                                            <i class="fas fa-broom me-2"></i> Clear Cache
                                        </button>
                                        <button class="btn btn-outline-info" onclick="testEmail()">
                                            <i class="fas fa-envelope me-2"></i> Test Email
                                        </button>
                                        <button class="btn btn-outline-success" onclick="backupDatabase()">
                                            <i class="fas fa-download me-2"></i> Backup Database
                                        </button>
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearCache() {
            if (confirm('Are you sure you want to clear the cache?')) {
                alert('Cache clearing functionality would be implemented here.');
            }
        }
        
        function testEmail() {
            const email = document.querySelector('input[name="setting_contact_email"]').value;
            if (confirm(`Send a test email to ${email}?`)) {
                alert('Test email functionality would be implemented here.');
            }
        }
        
        function backupDatabase() {
            if (confirm('Create a database backup? This may take a few moments.')) {
                alert('Database backup functionality would be implemented here.');
            }
        }
        
        // Auto-save indication
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                this.style.borderColor = '#ffc107';
                setTimeout(() => {
                    this.style.borderColor = '';
                }, 2000);
            });
        });
    </script>
</body>
</html>