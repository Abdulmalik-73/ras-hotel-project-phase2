<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

// Handle delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_activity_logs'])) {
        // Delete selected activity logs
        $ids = $_POST['activity_ids'] ?? [];
        if (!empty($ids)) {
            $ids_str = implode(',', array_map('intval', $ids));
            $delete_query = "DELETE FROM user_activity_log WHERE id IN ($ids_str)";
            if ($conn->query($delete_query)) {
                $success_message = count($ids) . ' activity log(s) deleted successfully!';
            } else {
                $error_message = 'Failed to delete activity logs: ' . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_booking_activity_logs'])) {
        // Delete selected booking activity logs
        $ids = $_POST['booking_activity_ids'] ?? [];
        if (!empty($ids)) {
            $ids_str = implode(',', array_map('intval', $ids));
            $delete_query = "DELETE FROM booking_activity_log WHERE id IN ($ids_str)";
            if ($conn->query($delete_query)) {
                $success_message = count($ids) . ' booking activity log(s) deleted successfully!';
            } else {
                $error_message = 'Failed to delete booking activity logs: ' . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_users'])) {
        // Delete selected users
        $ids = $_POST['user_ids'] ?? [];
        if (!empty($ids)) {
            // Prevent deleting super admin
            $ids_str = implode(',', array_map('intval', $ids));
            $check_query = "SELECT COUNT(*) as count FROM users WHERE id IN ($ids_str) AND role = 'super_admin'";
            $check_result = $conn->query($check_query);
            $has_super_admin = $check_result->fetch_assoc()['count'] > 0;
            
            if ($has_super_admin) {
                $error_message = 'Cannot delete Super Admin accounts!';
            } else {
                // Start transaction
                $conn->begin_transaction();
                try {
                    // Update foreign key references
                    $conn->query("UPDATE bookings SET verified_by = NULL WHERE verified_by IN ($ids_str)");
                    $conn->query("UPDATE bookings SET checked_in_by = NULL WHERE checked_in_by IN ($ids_str)");
                    $conn->query("UPDATE bookings SET checked_out_by = NULL WHERE checked_out_by IN ($ids_str)");
                    
                    // Delete users
                    $delete_query = "DELETE FROM users WHERE id IN ($ids_str)";
                    if ($conn->query($delete_query)) {
                        $conn->commit();
                        $success_message = count($ids) . ' user(s) deleted successfully!';
                    } else {
                        throw new Exception($conn->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = 'Failed to delete users: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['clear_all_activity'])) {
        // Clear all activity logs
        $delete_query = "DELETE FROM user_activity_log";
        if ($conn->query($delete_query)) {
            $success_message = 'All activity logs cleared successfully!';
        } else {
            $error_message = 'Failed to clear activity logs: ' . $conn->error;
        }
    } elseif (isset($_POST['clear_all_booking_activity'])) {
        // Clear all booking activity logs
        $delete_query = "DELETE FROM booking_activity_log";
        if ($conn->query($delete_query)) {
            $success_message = 'All booking activity logs cleared successfully!';
        } else {
            $error_message = 'Failed to clear booking activity logs: ' . $conn->error;
        }
    } elseif (isset($_POST['delete_old_activity'])) {
        // Delete activity logs older than 30 days
        $delete_query = "DELETE FROM user_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        if ($conn->query($delete_query)) {
            $affected = $conn->affected_rows;
            $success_message = $affected . ' old activity log(s) deleted successfully!';
        } else {
            $error_message = 'Failed to delete old activity logs: ' . $conn->error;
        }
    } elseif (isset($_POST['delete_old_booking_activity'])) {
        // Delete booking activity logs older than 30 days
        $delete_query = "DELETE FROM booking_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        if ($conn->query($delete_query)) {
            $affected = $conn->affected_rows;
            $success_message = $affected . ' old booking activity log(s) deleted successfully!';
        } else {
            $error_message = 'Failed to delete old booking activity logs: ' . $conn->error;
        }
    } elseif (isset($_POST['delete_inactive_users'])) {
        // Delete inactive users (not super_admin)
        $delete_query = "DELETE FROM users WHERE status = 'inactive' AND role != 'super_admin'";
        if ($conn->query($delete_query)) {
            $affected = $conn->affected_rows;
            $success_message = $affected . ' inactive user(s) deleted successfully!';
        } else {
            $error_message = 'Failed to delete inactive users: ' . $conn->error;
        }
    } elseif (isset($_POST['delete_booking'])) {
        // Delete a single booking
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        if ($booking_id > 0) {
            // Start transaction
            $conn->begin_transaction();
            try {
                // Delete related records first
                $conn->query("DELETE FROM booking_activity_log WHERE booking_id = $booking_id");
                $conn->query("DELETE FROM checkins WHERE booking_id = $booking_id");
                $conn->query("DELETE FROM food_orders WHERE booking_id = $booking_id");
                $conn->query("DELETE FROM service_bookings WHERE booking_id = $booking_id");
                
                // Delete the booking
                $delete_query = "DELETE FROM bookings WHERE id = $booking_id";
                if ($conn->query($delete_query)) {
                    $conn->commit();
                    $success_message = 'Booking deleted successfully!';
                } else {
                    throw new Exception($conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Failed to delete booking: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_bookings'])) {
        // Delete multiple bookings
        $ids = $_POST['booking_ids'] ?? [];
        if (!empty($ids)) {
            $ids_str = implode(',', array_map('intval', $ids));
            
            // Start transaction
            $conn->begin_transaction();
            try {
                // Delete related records first
                $conn->query("DELETE FROM booking_activity_log WHERE booking_id IN ($ids_str)");
                $conn->query("DELETE FROM checkins WHERE booking_id IN ($ids_str)");
                $conn->query("DELETE FROM food_orders WHERE booking_id IN ($ids_str)");
                $conn->query("DELETE FROM service_bookings WHERE booking_id IN ($ids_str)");
                
                // Delete bookings
                $delete_query = "DELETE FROM bookings WHERE id IN ($ids_str)";
                if ($conn->query($delete_query)) {
                    $conn->commit();
                    $success_message = count($ids) . ' booking(s) deleted successfully!';
                } else {
                    throw new Exception($conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Failed to delete bookings: ' . $e->getMessage();
            }
        }
    }
}

// Get data type from URL parameter
$data_type = isset($_GET['type']) ? $_GET['type'] : 'bookings';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$data = [];
$total_records = 0;
$title = '';

switch ($data_type) {
    case 'bookings':
        $title = 'All Bookings Data';
        $query = "SELECT b.*, r.name as room_name, r.room_number, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email, u.phone 
                  FROM bookings b 
                  JOIN rooms r ON b.room_id = r.id 
                  JOIN users u ON b.user_id = u.id 
                  ORDER BY b.created_at DESC 
                  LIMIT $limit OFFSET $offset";
        $count_query = "SELECT COUNT(*) as total FROM bookings";
        break;
        
    case 'contacts':
        $title = 'Contact Messages';
        $query = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $count_query = "SELECT COUNT(*) as total FROM contact_messages";
        break;
        
    case 'users':
        $title = 'User Registrations';
        $query = "SELECT id, first_name, last_name, email, phone, role, status, created_at 
                  FROM users ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $count_query = "SELECT COUNT(*) as total FROM users";
        break;
        
    case 'user_activity':
        $title = 'User Activity Log';
        $query = "SELECT ua.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email 
                  FROM user_activity_log ua 
                  LEFT JOIN users u ON ua.user_id = u.id 
                  ORDER BY ua.created_at DESC LIMIT $limit OFFSET $offset";
        $count_query = "SELECT COUNT(*) as total FROM user_activity_log";
        break;
        
    case 'booking_activity':
        $title = 'Booking Activity Log';
        $query = "SELECT ba.*, b.booking_reference, CONCAT(u.first_name, ' ', u.last_name) as user_name 
                  FROM booking_activity_log ba 
                  JOIN bookings b ON ba.booking_id = b.id 
                  LEFT JOIN users u ON ba.user_id = u.id 
                  ORDER BY ba.created_at DESC LIMIT $limit OFFSET $offset";
        $count_query = "SELECT COUNT(*) as total FROM booking_activity_log";
        break;
        
    default:
        $data_type = 'bookings';
        $title = 'All Bookings Data';
}

$result = $conn->query($query);
if ($result) {
    $data = $result->fetch_all(MYSQLI_ASSOC);
}

$count_result = $conn->query($count_query);
if ($count_result) {
    $total_records = $count_result->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Harar Ras Hotel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel - Data Viewer
            </a>
            <div class="ms-auto">
                <a href="admin.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                
                <span class="text-white me-3">
                    <i class="fas fa-user-shield"></i> <?php echo $_SESSION['user_name']; ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-database"></i> Data Categories</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="?type=bookings" class="list-group-item list-group-item-action <?php echo $data_type == 'bookings' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-check"></i> Bookings Data
                        </a>
                        <a href="?type=contacts" class="list-group-item list-group-item-action <?php echo $data_type == 'contacts' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope"></i> Contact Messages
                        </a>
                        <a href="?type=users" class="list-group-item list-group-item-action <?php echo $data_type == 'users' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> User Registrations
                        </a>
                        <a href="?type=user_activity" class="list-group-item list-group-item-action <?php echo $data_type == 'user_activity' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i> User Activity Log
                        </a>
                        <a href="?type=booking_activity" class="list-group-item list-group-item-action <?php echo $data_type == 'booking_activity' ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-list"></i> Booking Activity Log
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <!-- Success/Error Messages -->
                <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><?php echo $title; ?></h4>
                        <div>
                            <span class="badge bg-primary me-2"><?php echo $total_records; ?> Total Records</span>
                            <?php if ($data_type == 'user_activity'): ?>
                            <button type="button" class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#deleteOldModal">
                                <i class="fas fa-clock"></i> Delete Old Logs
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#clearAllModal">
                                <i class="fas fa-trash-alt"></i> Clear All
                            </button>
                            <?php elseif ($data_type == 'booking_activity'): ?>
                            <button type="button" class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#deleteOldBookingModal">
                                <i class="fas fa-clock"></i> Delete Old Logs
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#clearAllBookingModal">
                                <i class="fas fa-trash-alt"></i> Clear All
                            </button>
                            <?php elseif ($data_type == 'users'): ?>
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#deleteInactiveUsersModal">
                                <i class="fas fa-user-slash"></i> Delete Inactive Users
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($data)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No data found</h5>
                            <p class="text-muted">No records available for this category.</p>
                        </div>
                        <?php else: ?>
                        <?php if ($data_type == 'user_activity'): ?>
                        <form method="POST" id="activityForm">
                            <div class="mb-3">
                                <button type="submit" name="delete_activity_logs" class="btn btn-sm btn-danger" id="deleteSelectedBtn" disabled>
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <span class="text-muted ms-2" id="selectedCount">0 selected</span>
                            </div>
                        <?php elseif ($data_type == 'booking_activity'): ?>
                        <form method="POST" id="bookingActivityForm">
                            <div class="mb-3">
                                <button type="submit" name="delete_booking_activity_logs" class="btn btn-sm btn-danger" id="deleteSelectedBookingBtn" disabled>
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <span class="text-muted ms-2" id="selectedBookingCount">0 selected</span>
                            </div>
                        <?php elseif ($data_type == 'users'): ?>
                        <form method="POST" id="usersForm">
                            <div class="mb-3">
                                <button type="submit" name="delete_users" class="btn btn-sm btn-danger" id="deleteSelectedUsersBtn" disabled>
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <span class="text-muted ms-2" id="selectedUsersCount">0 selected</span>
                            </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <?php if ($data_type == 'bookings'): ?>
                                        <th>ID</th>
                                        <th>Reference</th>
                                        <th>Customer</th>
                                        <th>Room</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Customers</th>
                                        <th>Total Price</th>
                                        <th>Special Requests</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                        <?php elseif ($data_type == 'contacts'): ?>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <?php elseif ($data_type == 'users'): ?>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllUsers" class="form-check-input">
                                        </th>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <?php elseif ($data_type == 'user_activity'): ?>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Activity</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                        <th>Created</th>
                                        <?php elseif ($data_type == 'booking_activity'): ?>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllBooking" class="form-check-input">
                                        </th>
                                        <th>ID</th>
                                        <th>Booking Ref</th>
                                        <th>User</th>
                                        <th>Activity</th>
                                        <th>Status Change</th>
                                        <th>Description</th>
                                        <th>Created</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                    <tr>
                                        <?php if ($data_type == 'bookings'): ?>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><strong><?php echo $row['booking_reference']; ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['customer_name'] ?? ''); ?><br>
                                            <small class="text-muted"><?php echo $row['email'] ?? ''; ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($row['room_name'] ?? ''); 
                                            if (!empty($row['room_number'])) {
                                                echo ' <span class="text-muted">(No: ' . htmlspecialchars($row['room_number']) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($row['check_in_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($row['check_out_date'])); ?></td>
                                        <td><?php echo $row['number_of_guests']; ?></td>
                                        <td><?php echo format_currency($row['total_price']); ?></td>
                                        <td>
                                            <?php if (!empty($row['special_requests'])): ?>
                                            <span class="badge bg-info" title="<?php echo htmlspecialchars($row['special_requests']); ?>">
                                                Has Requests
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['status'] == 'confirmed' ? 'success' : ($row['status'] == 'pending' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y H:i', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">
                                                <input type="hidden" name="delete_booking" value="1">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Booking">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                        
                                        <?php elseif ($data_type == 'contacts'): ?>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['subject'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge bg-primary" title="<?php echo htmlspecialchars($row['message'] ?? ''); ?>">
                                                View Message
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['status'] == 'new' ? 'danger' : 'success'; ?>">
                                                <?php echo ucfirst($row['status'] ?? 'new'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y H:i', strtotime($row['created_at'])); ?></td>
                                        
                                        <?php elseif ($data_type == 'users'): ?>
                                        <td>
                                            <input type="checkbox" name="user_ids[]" value="<?php echo $row['id']; ?>" class="form-check-input user-checkbox" <?php echo $row['role'] == 'super_admin' ? 'disabled title="Cannot delete Super Admin"' : ''; ?>>
                                        </td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($row['role'] ?? 'customer'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($row['status'] ?? 'inactive'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y H:i', strtotime($row['created_at'])); ?></td>
                                        
                                        <?php elseif ($data_type == 'user_activity'): ?>
                                        <td>
                                            <input type="checkbox" name="activity_ids[]" value="<?php echo $row['id']; ?>" class="form-check-input activity-checkbox">
                                        </td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td>
                                            <?php if (!empty($row['user_name'])): ?>
                                            <?php echo htmlspecialchars($row['user_name']); ?><br>
                                            <small class="text-muted"><?php echo $row['email'] ?? ''; ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">Unknown User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($row['activity_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td><small><?php echo $row['ip_address']; ?></small></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($row['created_at'])); ?></td>
                                        
                                        <?php elseif ($data_type == 'booking_activity'): ?>
                                        <td>
                                            <input type="checkbox" name="booking_activity_ids[]" value="<?php echo $row['id']; ?>" class="form-check-input booking-activity-checkbox">
                                        </td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><strong><?php echo $row['booking_reference']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo ucfirst($row['activity_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['old_status'] && $row['new_status']): ?>
                                            <small><?php echo $row['old_status']; ?> → <?php echo $row['new_status']; ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($row['created_at'])); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($data_type == 'user_activity' || $data_type == 'booking_activity' || $data_type == 'users'): ?>
                        </form>
                        <?php endif; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Data pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?type=<?php echo $data_type; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?php echo $data_type; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?type=<?php echo $data_type; ?>&page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Old Logs Modal -->
    <div class="modal fade" id="deleteOldModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-clock me-2"></i> Delete Old Activity Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This will delete all activity logs older than 30 days.
                        </div>
                        <p>Are you sure you want to delete old activity logs?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_old_activity" class="btn btn-warning">
                            <i class="fas fa-trash me-2"></i> Delete Old Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Clear All Logs Modal -->
    <div class="modal fade" id="clearAllModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Clear All Activity Logs</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>WARNING:</strong> This will permanently delete ALL activity logs!
                        </div>
                        <p>This action cannot be undone. Are you sure you want to clear all activity logs?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="clear_all_activity" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i> Clear All Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Old Booking Activity Logs Modal -->
    <div class="modal fade" id="deleteOldBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-clock me-2"></i> Delete Old Booking Activity Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This will delete all booking activity logs older than 30 days.
                        </div>
                        <p>Are you sure you want to delete old booking activity logs?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_old_booking_activity" class="btn btn-warning">
                            <i class="fas fa-trash me-2"></i> Delete Old Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Clear All Booking Activity Logs Modal -->
    <div class="modal fade" id="clearAllBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Clear All Booking Activity Logs</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>WARNING:</strong> This will permanently delete ALL booking activity logs!
                        </div>
                        <p>This action cannot be undone. Are you sure you want to clear all booking activity logs?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="clear_all_booking_activity" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i> Clear All Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Inactive Users Modal -->
    <div class="modal fade" id="deleteInactiveUsersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-user-slash me-2"></i> Delete Inactive Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This will delete all users with "inactive" status (excluding Super Admins).
                        </div>
                        <p>Are you sure you want to delete all inactive users?</p>
                        <p class="text-muted small"><i class="fas fa-info-circle me-1"></i> Super Admin accounts will not be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_inactive_users" class="btn btn-warning">
                            <i class="fas fa-trash me-2"></i> Delete Inactive Users
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Checkbox selection for activity logs
        const selectAll = document.getElementById('selectAll');
        const activityCheckboxes = document.querySelectorAll('.activity-checkbox');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
        const selectedCount = document.getElementById('selectedCount');
        const activityForm = document.getElementById('activityForm');
        
        if (selectAll && activityCheckboxes.length > 0) {
            // Select all functionality
            selectAll.addEventListener('change', function() {
                activityCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateDeleteButton();
            });
            
            // Individual checkbox change
            activityCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateDeleteButton();
                    // Update select all state
                    selectAll.checked = Array.from(activityCheckboxes).every(cb => cb.checked);
                });
            });
            
            // Update delete button state
            function updateDeleteButton() {
                const checkedCount = Array.from(activityCheckboxes).filter(cb => cb.checked).length;
                deleteSelectedBtn.disabled = checkedCount === 0;
                selectedCount.textContent = checkedCount + ' selected';
            }
            
            // Confirm before deleting
            if (activityForm) {
                activityForm.addEventListener('submit', function(e) {
                    if (e.submitter && e.submitter.name === 'delete_activity_logs') {
                        const checkedCount = Array.from(activityCheckboxes).filter(cb => cb.checked).length;
                        if (!confirm('Are you sure you want to delete ' + checkedCount + ' activity log(s)?')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        }
        
        // Checkbox selection for booking activity logs
        const selectAllBooking = document.getElementById('selectAllBooking');
        const bookingActivityCheckboxes = document.querySelectorAll('.booking-activity-checkbox');
        const deleteSelectedBookingBtn = document.getElementById('deleteSelectedBookingBtn');
        const selectedBookingCount = document.getElementById('selectedBookingCount');
        const bookingActivityForm = document.getElementById('bookingActivityForm');
        
        if (selectAllBooking && bookingActivityCheckboxes.length > 0) {
            // Select all functionality
            selectAllBooking.addEventListener('change', function() {
                bookingActivityCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBookingDeleteButton();
            });
            
            // Individual checkbox change
            bookingActivityCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateBookingDeleteButton();
                    // Update select all state
                    selectAllBooking.checked = Array.from(bookingActivityCheckboxes).every(cb => cb.checked);
                });
            });
            
            // Update delete button state
            function updateBookingDeleteButton() {
                const checkedCount = Array.from(bookingActivityCheckboxes).filter(cb => cb.checked).length;
                deleteSelectedBookingBtn.disabled = checkedCount === 0;
                selectedBookingCount.textContent = checkedCount + ' selected';
            }
            
            // Confirm before deleting
            if (bookingActivityForm) {
                bookingActivityForm.addEventListener('submit', function(e) {
                    if (e.submitter && e.submitter.name === 'delete_booking_activity_logs') {
                        const checkedCount = Array.from(bookingActivityCheckboxes).filter(cb => cb.checked).length;
                        if (!confirm('Are you sure you want to delete ' + checkedCount + ' booking activity log(s)?')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        }
        
        // Checkbox selection for users
        const selectAllUsers = document.getElementById('selectAllUsers');
        const userCheckboxes = document.querySelectorAll('.user-checkbox:not([disabled])');
        const deleteSelectedUsersBtn = document.getElementById('deleteSelectedUsersBtn');
        const selectedUsersCount = document.getElementById('selectedUsersCount');
        const usersForm = document.getElementById('usersForm');
        
        if (selectAllUsers && userCheckboxes.length > 0) {
            // Select all functionality
            selectAllUsers.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateUsersDeleteButton();
            });
            
            // Individual checkbox change
            userCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateUsersDeleteButton();
                    // Update select all state
                    selectAllUsers.checked = Array.from(userCheckboxes).every(cb => cb.checked);
                });
            });
            
            // Update delete button state
            function updateUsersDeleteButton() {
                const checkedCount = Array.from(userCheckboxes).filter(cb => cb.checked).length;
                deleteSelectedUsersBtn.disabled = checkedCount === 0;
                selectedUsersCount.textContent = checkedCount + ' selected';
            }
            
            // Confirm before deleting
            if (usersForm) {
                usersForm.addEventListener('submit', function(e) {
                    if (e.submitter && e.submitter.name === 'delete_users') {
                        const checkedCount = Array.from(userCheckboxes).filter(cb => cb.checked).length;
                        if (!confirm('⚠️ WARNING ⚠️\n\nAre you sure you want to PERMANENTLY DELETE ' + checkedCount + ' user(s)?\n\nThis will also delete:\n• All their bookings\n• All their activity logs\n• All related data\n\nThis action CANNOT be undone!')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>