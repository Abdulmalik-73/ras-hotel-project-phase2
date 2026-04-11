<?php
session_start();

// Add debugging at the very top
file_put_contents('delete_debug.log', date('Y-m-d H:i:s') . " - Delete script accessed\n", FILE_APPEND);
file_put_contents('delete_debug.log', "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents('delete_debug.log', "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);

require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    error_log("Delete user request received for user ID: " . $user_id);
    
    if ($user_id <= 0) {
        error_log("Invalid user ID: " . $user_id);
        header('Location: manage-users.php?error=Invalid user ID');
        exit();
    }
    
    // Get user info before deletion
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        error_log("User not found: " . $user_id);
        header('Location: manage-users.php?error=User not found');
        exit();
    }
    
    error_log("Found user: " . $user['email'] . " (Role: " . $user['role'] . ")");
    
    // Prevent deleting super admin or yourself
    if ($user['role'] === 'super_admin') {
        error_log("Attempted to delete super admin");
        header('Location: manage-users.php?error=Cannot delete Super Admin account');
        exit();
    }
    
    if ($user_id === $_SESSION['user_id']) {
        error_log("Attempted to delete own account");
        header('Location: manage-users.php?error=Cannot delete your own account');
        exit();
    }
    
    // Try to log audit (but don't fail if it doesn't work)
    try {
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            $audit_query = "INSERT INTO admin_audit_logs (admin_id, action, target_user_id, changed_fields, old_values, new_values, ip_address) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $audit_stmt = $conn->prepare($audit_query);
            if ($audit_stmt) {
                $action = 'permanent_delete';
                $changed_fields = json_encode(['user_deleted']);
                $old_values = json_encode([
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'role' => $user['role']
                ]);
                $new_values = json_encode(['deleted' => true]);
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $audit_stmt->bind_param("isissss", $_SESSION['user_id'], $action, $user_id, $changed_fields, $old_values, $new_values, $ip);
                $audit_stmt->execute();
                error_log("Audit log created successfully");
            }
        }
    } catch (Exception $e) {
        error_log("Audit log failed (continuing with deletion): " . $e->getMessage());
    }
    
    // PERMANENT DELETE - Remove user from database
    // First, manually handle SET NULL foreign keys to avoid issues
    error_log("Attempting to delete user ID: " . $user_id);
    
    // Start transaction for safe deletion
    $conn->begin_transaction();
    
    try {
        // Update records that have SET NULL foreign keys
        $conn->query("UPDATE bookings SET verified_by = NULL WHERE verified_by = $user_id");
        $conn->query("UPDATE bookings SET checked_in_by = NULL WHERE checked_in_by = $user_id");
        $conn->query("UPDATE bookings SET checked_out_by = NULL WHERE checked_out_by = $user_id");
        $conn->query("UPDATE user_activity_log SET user_id = NULL WHERE user_id = $user_id");
        $conn->query("UPDATE booking_activity_log SET user_id = NULL WHERE user_id = $user_id");
        $conn->query("UPDATE booking_activity_log SET performed_by = NULL WHERE performed_by = $user_id");
        $conn->query("UPDATE admin_audit_logs SET target_user_id = NULL WHERE target_user_id = $user_id");
        
        error_log("Updated SET NULL foreign keys");
        
        // Now delete the user (CASCADE will handle the rest)
        $delete_query = "DELETE FROM users WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        
        if (!$delete_stmt) {
            throw new Exception("Failed to prepare delete statement: " . $conn->error);
        }
        
        $delete_stmt->bind_param("i", $user_id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception("Failed to execute delete: " . $delete_stmt->error);
        }
        
        $affected = $delete_stmt->affected_rows;
        error_log("Delete executed successfully. Affected rows: " . $affected);
        
        if ($affected <= 0) {
            throw new Exception("No rows affected - user may not exist");
        }
        
        // Commit transaction
        $conn->commit();
        error_log("User deleted successfully: " . $user['email']);
        header('Location: manage-users.php?message=User permanently deleted successfully');
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Delete failed: " . $e->getMessage());
        header('Location: manage-users.php?error=Failed to delete user: ' . urlencode($e->getMessage()));
    }
} else {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    header('Location: manage-users.php');
}
exit();
?>
