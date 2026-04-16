<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/services/NotificationService.php';

// Require authentication
require_auth('login.php');

$user_id = $_SESSION['user_id'];
$notificationService = new NotificationService($conn);

// Get all notifications
$notifications = $notificationService->getAll($user_id, 100);
$unread_count = $notificationService->getUnreadCount($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .notification-card {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .notification-card.unread {
            background-color: #e3f2fd;
            border-left-color: #007bff;
        }
        .notification-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .notification-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.5rem;
        }
        .icon-success { background-color: #d4edda; color: #28a745; }
        .icon-danger { background-color: #f8d7da; color: #dc3545; }
        .icon-primary { background-color: #cfe2ff; color: #0d6efd; }
        .icon-warning { background-color: #fff3cd; color: #ffc107; }
        .icon-info { background-color: #d1ecf1; color: #0dcaf0; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-bell text-primary"></i> Notifications</h2>
                        <p class="text-muted mb-0">
                            <?php if ($unread_count > 0): ?>
                                You have <strong><?php echo $unread_count; ?></strong> unread notification<?php echo $unread_count > 1 ? 's' : ''; ?>
                            <?php else: ?>
                                All caught up! No unread notifications.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <?php if ($unread_count > 0): ?>
                        <button class="btn btn-outline-primary" onclick="markAllAsRead()">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </button>
                        <?php endif; ?>
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                    </div>
                </div>

                <div id="alertContainer"></div>

                <?php if (empty($notifications)): ?>
                <div class="card shadow text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h4>No Notifications Yet</h4>
                        <p class="text-muted">You'll see notifications here when you have updates about your bookings and payments.</p>
                        <a href="index.php" class="btn btn-primary mt-3">
                            <i class="fas fa-home"></i> Go to Homepage
                        </a>
                    </div>
                </div>
                <?php else: ?>

                <div class="list-group">
                    <?php foreach ($notifications as $notification): 
                        $icon_class = '';
                        $icon = '';
                        
                        switch ($notification['type']) {
                            case 'payment_verified':
                                $icon_class = 'icon-success';
                                $icon = 'fa-check-circle';
                                break;
                            case 'payment_rejected':
                                $icon_class = 'icon-danger';
                                $icon = 'fa-times-circle';
                                break;
                            case 'booking_confirmed':
                                $icon_class = 'icon-primary';
                                $icon = 'fa-calendar-check';
                                break;
                            case 'booking_cancelled':
                                $icon_class = 'icon-warning';
                                $icon = 'fa-calendar-times';
                                break;
                            default:
                                $icon_class = 'icon-info';
                                $icon = 'fa-bell';
                        }
                        
                        $time_ago = '';
                        $created = strtotime($notification['created_at']);
                        $now = time();
                        $diff = $now - $created;
                        
                        if ($diff < 60) {
                            $time_ago = 'Just now';
                        } elseif ($diff < 3600) {
                            $time_ago = floor($diff / 60) . ' minutes ago';
                        } elseif ($diff < 86400) {
                            $time_ago = floor($diff / 3600) . ' hours ago';
                        } elseif ($diff < 604800) {
                            $time_ago = floor($diff / 86400) . ' days ago';
                        } else {
                            $time_ago = date('M j, Y', $created);
                        }
                    ?>
                    <div class="list-group-item notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?> mb-3" 
                         id="notification-<?php echo $notification['id']; ?>">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="notification-icon <?php echo $icon_class; ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                        <p class="mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> <?php echo $time_ago; ?>
                                        </small>
                                    </div>
                                    <div class="btn-group">
                                        <?php if ($notification['link']): ?>
                                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" 
                                           class="btn btn-sm btn-outline-primary"
                                           onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!$notification['is_read']): ?>
                                        <button class="btn btn-sm btn-outline-success" 
                                                onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                            <i class="fas fa-check"></i> Mark Read
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAsRead(notificationId) {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mark_read&notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById('notification-' + notificationId);
                    if (card) {
                        card.classList.remove('unread');
                        const markReadBtn = card.querySelector('.btn-outline-success');
                        if (markReadBtn) {
                            markReadBtn.remove();
                        }
                    }
                    showAlert('Notification marked as read', 'success');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to mark notification as read', 'danger');
            });
        }

        function markAllAsRead() {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to mark all as read', 'danger');
            });
        }

        function deleteNotification(notificationId) {
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }

            fetch('api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete&notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById('notification-' + notificationId);
                    if (card) {
                        card.style.transition = 'opacity 0.3s';
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 300);
                    }
                    showAlert('Notification deleted', 'success');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to delete notification', 'danger');
            });
        }

        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.getElementById('alertContainer').innerHTML = alertHtml;
            
            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>
