<?php
// Suppress PHP warnings and notices for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Only Super Admin can access this page
require_auth_role('super_admin', '../login.php');

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
        
        if (empty($errors)) {
            $success_message = "$updated_count settings updated successfully!";
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

// Get current settings
$settings = [];
try {
    $settings_result = $conn->query("SELECT * FROM hotel_settings ORDER BY setting_key");
    if ($settings_result) {
        while ($row = $settings_result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {}

// Default settings if not set
$default_settings = [
    'hotel_name' => 'Harar Ras Hotel',
    'hotel_address' => 'Jugol Street, Harar, Ethiopia',
    'contact_phone' => '+251-25-666-2828',
    'contact_email' => 'info@hararrashotel.com',
    'check_in_time' => '14:00',
    'check_out_time' => '12:00',
    'currency' => 'ETB',
    'tax_rate' => '15',
    'cancellation_policy' => '24 hours before check-in',
    'max_booking_days' => '365'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Settings - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%);
            min-height: 100vh;
            color: white;
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%);">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="../index.php">
                <i class="fas fa-crown text-warning"></i> 
                <span class="fw-bold">Harar Ras Hotel - Super Admin</span>
            </a>
            <div class="ms-auto">
                <span class="text-white me-3">
                    <i class="fas fa-user-crown"></i> Super Administrator
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
                        <i class="fas fa-crown"></i> Super Admin Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="super-admin.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="super-admin-users.php" class="nav-link">
                            <i class="fas fa-users me-2"></i> User Management
                        </a>
                        <a href="super-admin-settings.php" class="nav-link active">
                            <i class="fas fa-cog me-2"></i> System Settings
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
                            <a href="super-admin.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-cog me-2"></i> System Settings</h2>
                        </div>
                    </div>
                    
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-hotel me-2"></i> Hotel Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_settings">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Hotel Name</label>
                                        <input type="text" name="setting_hotel_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['hotel_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Contact Email</label>
                                        <input type="email" name="setting_contact_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Contact Phone</label>
                                        <input type="text" name="setting_contact_phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['contact_phone']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Currency</label>
                                        <select name="setting_currency" class="form-select" required>
                                            <option value="ETB" <?php echo $settings['currency'] == 'ETB' ? 'selected' : ''; ?>>Ethiopian Birr (ETB)</option>
                                            <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                            <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Hotel Address</label>
                                    <textarea name="setting_hotel_address" class="form-control" rows="2" required><?php echo htmlspecialchars($settings['hotel_address']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold">Check-in Time</label>
                                        <input type="time" name="setting_check_in_time" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['check_in_time']); ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold">Check-out Time</label>
                                        <input type="time" name="setting_check_out_time" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['check_out_time']); ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold">Tax Rate (%)</label>
                                        <input type="number" name="setting_tax_rate" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['tax_rate']); ?>" 
                                               min="0" max="100" step="0.01" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Cancellation Policy</label>
                                        <input type="text" name="setting_cancellation_policy" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['cancellation_policy']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Max Booking Days</label>
                                        <input type="number" name="setting_max_booking_days" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['max_booking_days']); ?>" 
                                               min="1" max="730" required>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        <i class="fas fa-save me-2"></i> Update Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>