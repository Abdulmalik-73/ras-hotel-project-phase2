<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    
    // Get user info before deletion
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        // Prevent deleting super admin or yourself
        if ($user['role'] === 'super_admin') {
            header('Location: manage-users.php?error=Cannot delete Super Admin account');
            exit();
        }
        
        if ($user_id === $_SESSION['user_id']) {
            header('Location: manage-users.php?error=Cannot delete your own account');
            exit();
        }
        
        // Log audit BEFORE deletion (only if admin_id is valid)
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            $audit_query = "INSERT INTO admin_audit_logs (admin_id, action, target_user_id, changed_fields, old_values, new_values, ip_address) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $audit_stmt = $conn->prepare($audit_query);
            $action = 'permanent_delete';
            $changed_fields = json_encode(['user_deleted']);
            $old_values = json_encode([
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'role' => $user['role']
            ]);
            $new_values = json_encode(['deleted' => true]);
            $ip = $_SERVER['REMOTE_ADDR'];
            $types = "i" . "s" . "i" . "s" . "s" . "s" . "s";
            $audit_stmt->bind_param($types, $_SESSION['user_id'], $action, $user_id, $changed_fields, $old_values, $new_values, $ip);
            $audit_stmt->execute();
        }
        
        // PERMANENT DELETE - Remove user from database
        // Note: Foreign keys with ON DELETE CASCADE will automatically delete related records
        $delete_query = "DELETE FROM users WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute()) {
            header('Location: manage-users.php?message=User permanently deleted successfully');
        } else {
            header('Location: manage-users.php?error=Failed to delete user: ' . $conn->error);
        }
    } else {
        header('Location: manage-users.php?error=User not found');
    }
} else {
    header('Location: manage-users.php');
}
exit();
?>
