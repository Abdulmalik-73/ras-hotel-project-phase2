<?php
/**
 * User Profile Page - Protected
 * Requires: User authentication
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Require authentication and prevent caching
require_auth('login.php');

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$action = $_GET['action'] ?? 'view';

// Fetch user data from database
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = 'uploads/profiles/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $file_path)) {
                // Delete old photo if exists
                if ($user['profile_photo'] && file_exists($user['profile_photo'])) {
                    unlink($user['profile_photo']);
                }
                
                // Update database
                $update_query = "UPDATE users SET profile_photo = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $file_path, $user_id);
                
                if ($stmt->execute()) {
                    $message = 'Profile photo updated successfully!';
                    $user['profile_photo'] = $file_path;
                } else {
                    $error = 'Failed to update profile photo in database';
                }
            } else {
                $error = 'Failed to upload file';
            }
        } else {
            $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed';
        }
    }
}

// Handle profile information update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_info'])) {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address'] ?? '');
    
    $update_query = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $address, $user_id);
    
    if ($stmt->execute()) {
        $message = 'Profile information updated successfully!';
        // Refresh user data
        $stmt = $conn->prepare($user_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $error = 'Failed to update profile information';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <section class="py-5">
        <div class="container">
            <!-- Back Button -->
            <div class="row mb-3">
                <div class="col-12">
                    <button onclick="goBack()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> <?php echo __('profile_page.back'); ?>
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <?php if (($user['profile_photo'] ?? '') && file_exists($user['profile_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_photo'] ?? ''); ?>" alt="Profile" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-8x text-gold mb-3"></i>
                            <?php endif; ?>
                            <h5><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                            <span class="badge bg-gold"><?php echo ucfirst($user['role'] ?? 'customer'); ?></span>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="profile.php?action=view" class="list-group-item list-group-item-action <?php echo $action == 'view' ? 'active' : ''; ?>">
                                <i class="fas fa-user me-2"></i> <?php echo __('profile_page.view_profile'); ?>
                            </a>
                            <a href="profile.php?action=photo" class="list-group-item list-group-item-action <?php echo $action == 'photo' ? 'active' : ''; ?>">
                                <i class="fas fa-camera me-2"></i> <?php echo __('profile_page.change_photo'); ?>
                            </a>
                            <a href="profile.php?action=edit" class="list-group-item list-group-item-action <?php echo $action == 'edit' ? 'active' : ''; ?>">
                                <i class="fas fa-edit me-2"></i> <?php echo __('profile_page.update_info'); ?>
                            </a>
                            <a href="settings.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog me-2"></i> <?php echo __('account.settings'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($action == 'view'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i> <?php echo __('profile_page.profile_info'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong><?php echo __('profile_page.first_name'); ?>:</strong>
                                    <p><?php echo htmlspecialchars($user['first_name'] ?? ''); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <strong><?php echo __('profile_page.last_name'); ?>:</strong>
                                    <p><?php echo htmlspecialchars($user['last_name'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong><?php echo __('profile_page.email'); ?>:</strong>
                                    <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <strong><?php echo __('profile_page.phone'); ?>:</strong>
                                    <p><?php echo htmlspecialchars($user['phone'] ?? __('profile_page.not_provided')); ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <strong><?php echo __('profile_page.address'); ?>:</strong>
                                    <p><?php echo htmlspecialchars($user['address'] ?? __('profile_page.not_provided')); ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong><?php echo __('profile_page.role'); ?>:</strong>
                                    <p><span class="badge bg-gold"><?php echo ucfirst($user['role'] ?? 'customer'); ?></span></p>
                                </div>
                                <div class="col-md-6">
                                    <strong><?php echo __('profile_page.member_since'); ?>:</strong>
                                    <p><?php echo date('F j, Y', strtotime($user['created_at'] ?? 'now')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif ($action == 'photo'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-camera me-2"></i> <?php echo __('profile_page.change_profile_photo'); ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="text-center mb-4">
                                    <?php if (($user['profile_photo'] ?? '') && file_exists($user['profile_photo'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_photo'] ?? ''); ?>" alt="Current Photo" class="rounded-circle mb-3" style="width: 200px; height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-10x text-gold mb-3"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('profile_page.select_photo'); ?></label>
                                    <input type="file" name="profile_photo" class="form-control" accept="image/*" required>
                                    <small class="text-muted"><?php echo __('profile_page.photo_formats'); ?></small>
                                </div>
                                <button type="submit" name="upload_photo" class="btn btn-gold">
                                    <i class="fas fa-upload me-2"></i> <?php echo __('profile_page.upload_photo'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action == 'edit'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-edit me-2"></i> <?php echo __('profile_page.edit_profile'); ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('profile_page.first_name'); ?> *</label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('profile_page.last_name'); ?> *</label>
                                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('profile_page.email_readonly'); ?></label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('profile_page.phone'); ?></label>
                                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('profile_page.address'); ?></label>
                                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" name="update_info" class="btn btn-gold">
                                    <i class="fas fa-save me-2"></i> <?php echo __('profile_page.save_changes'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function goBack() {
            // Always redirect to the main page where user can access their profile dropdown
            <?php if (isset($_SESSION['user_role'])): ?>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    window.location.href = 'dashboard/admin.php';
                <?php elseif ($_SESSION['user_role'] === 'manager'): ?>
                    window.location.href = 'dashboard/manager.php';
                <?php elseif ($_SESSION['user_role'] === 'receptionist'): ?>
                    window.location.href = 'dashboard/receptionist.php';
                <?php else: ?>
                    window.location.href = 'index.php';
                <?php endif; ?>
            <?php else: ?>
                window.location.href = 'index.php';
            <?php endif; ?>
        }
    </script>
</body>
</html>
