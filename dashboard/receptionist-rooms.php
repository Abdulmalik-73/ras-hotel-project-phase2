<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('receptionist', '../login.php');

// Handle room actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            $room_id = (int)$_POST['room_id'];
            $status = sanitize_input($_POST['status']);
            
            // Update room status directly
            $query = "UPDATE rooms SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $status, $room_id);
            
            if ($stmt->execute()) {
                set_message('success', "Room status updated to " . ucfirst($status) . " successfully!");
            } else {
                set_message('error', 'Failed to update room status: ' . $conn->error);
            }
            break;
            
        case 'update_price':
            $room_id = (int)$_POST['room_id'];
            $price = (float)$_POST['price'];
            
            $query = "UPDATE rooms SET price = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("di", $price, $room_id);
            
            if ($stmt->execute()) {
                set_message('success', "Room price updated to " . format_currency($price) . " successfully!");
            } else {
                set_message('error', 'Failed to update room price: ' . $conn->error);
            }
            break;
    }
    header('Location: receptionist-rooms.php');
    exit();
}

// Get rooms with current booking status
$rooms_query = "SELECT r.*, 
                CASE 
                    WHEN r.status = 'occupied' THEN 'occupied'
                    WHEN r.status = 'booked' THEN 'booked'
                    WHEN b.id IS NOT NULL THEN 'occupied'
                    WHEN r.status = 'maintenance' THEN 'maintenance'
                    WHEN r.status = 'inactive' THEN 'inactive'
                    ELSE 'available'
                END as availability_status,
                b.booking_reference,
                CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                b.check_out_date
                FROM rooms r
                LEFT JOIN bookings b ON r.id = b.room_id 
                    AND b.status = 'checked_in'
                LEFT JOIN users u ON b.user_id = u.id
                ORDER BY CAST(r.room_number AS UNSIGNED)";

$rooms = $conn->query($rooms_query);

// Get room statistics
$room_stats_query = "SELECT 
                     COUNT(*) as total_rooms,
                     COUNT(CASE WHEN r.status = 'active' AND b.id IS NULL THEN 1 END) as available_rooms,
                     COUNT(CASE WHEN r.status = 'occupied' OR (r.status = 'active' AND b.id IS NOT NULL) THEN 1 END) as occupied_rooms,
                     COUNT(CASE WHEN r.status = 'maintenance' THEN 1 END) as maintenance_rooms,
                     COUNT(CASE WHEN r.status = 'inactive' THEN 1 END) as inactive_rooms
                     FROM rooms r
                     LEFT JOIN bookings b ON r.id = b.room_id AND b.status = 'checked_in'";

$room_stats = $conn->query($room_stats_query)->fetch_assoc();
$available_rooms = $room_stats['available_rooms'];
$total_active = $room_stats['total_rooms'] - $room_stats['maintenance_rooms'] - $room_stats['inactive_rooms'];
$occupancy_rate = $total_active > 0 ? round(($room_stats['occupied_rooms'] / $total_active) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - Receptionist Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-receptionist {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
        }
        .navbar-receptionist .navbar-brand {
            color: white !important;
            font-weight: bold;
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
        .room-card {
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .room-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .room-available {
            border-left: 4px solid #28a745;
        }
        .room-occupied {
            border-left: 4px solid #dc3545;
        }
        .room-maintenance {
            border-left: 4px solid #ffc107;
        }
        .room-inactive {
            border-left: 4px solid #6c757d;
        }
        .stat-card {
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
            text-align: center;
        }
        .stat-card h4 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-card.green { background: linear-gradient(135deg, #27ae60, #229954); }
        .stat-card.red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-card.yellow { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        
        /* Room Status Dropdown Scrollable */
        .room-status-dropdown {
            max-height: 200px;
            overflow-y: auto;
        }
        
        /* Enable hover and click on all dropdown options */
        .room-status-dropdown option {
            cursor: pointer;
            background-color: white;
            color: #333;
            padding: 8px;
        }
        
        .room-status-dropdown option:hover {
            background-color: #e7f3ff;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-receptionist">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> 
                <span class="text-white fw-bold">Harar Ras Hotel - Receptionist Dashboard</span>
            </a>
            <div class="ms-auto">
                
                <span class="text-white me-3">
                    <i class="fas fa-user-tie"></i> Receptionist
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
                        <i class="fas fa-concierge-bell"></i> Reception Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="receptionist.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard Overview
                        </a>
                        <a href="receptionist-checkin.php" class="nav-link">
                            <i class="fas fa-plus-circle me-2"></i> New Check-in
                        </a>
                        <a href="receptionist-checkout.php" class="nav-link">
                            <i class="fas fa-minus-circle me-2"></i> Process Check-out
                        </a>
                        <a href="receptionist-rooms.php" class="nav-link active">
                            <i class="fas fa-bed me-2"></i> Manage Rooms
                        </a>
                        <a href="../generate_bill.php" class="nav-link">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Generate Bill
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
                            <a href="receptionist.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-bed me-2"></i> Manage Rooms</h2>
                        </div>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i> Refresh Status
                        </button>
                    </div>
                    
                    <!-- Status Change Notifications -->
                    <div id="statusNotification" class="alert alert-dismissible fade" style="display: none;" role="alert">
                        <span id="notificationMessage"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    
                    <?php display_message(); ?>
                    
                    <!-- Room Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card green">
                                <h4><?php echo $available_rooms; ?></h4>
                                <p><i class="fas fa-check-circle me-2"></i>Available</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card red">
                                <h4><?php echo $room_stats['occupied_rooms']; ?></h4>
                                <p><i class="fas fa-user me-2"></i>Occupied</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card yellow">
                                <h4><?php echo $room_stats['maintenance_rooms']; ?></h4>
                                <p><i class="fas fa-tools me-2"></i>Maintenance</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card blue">
                                <h4><?php echo $occupancy_rate; ?>%</h4>
                                <p><i class="fas fa-chart-pie me-2"></i>Occupancy</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rooms Grid -->
                    <div class="row">
                        <?php if ($rooms && $rooms->num_rows > 0): ?>
                            <?php while ($room = $rooms->fetch_assoc()): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card room-card room-<?php echo $room['availability_status']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="card-title mb-1">Room <?php echo htmlspecialchars($room['room_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h5>
                                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($room['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <small class="text-muted"><?php echo ucfirst($room['room_type'] ?? 'standard'); ?> • <?php echo $room['capacity'] ?? 1; ?> guests</small>
                                                </div>
                                                <div class="text-end">
                                                    <?php
                                                    $status_badges = [
                                                        'available' => ['success', 'Available', 'fas fa-check-circle'],
                                                        'occupied' => ['danger', 'Occupied', 'fas fa-user'],
                                                        'maintenance' => ['warning', 'Maintenance', 'fas fa-tools'],
                                                        'inactive' => ['secondary', 'Inactive', 'fas fa-pause-circle']
                                                    ];
                                                    $badge_info = $status_badges[$room['availability_status']] ?? ['secondary', 'Unknown', 'fas fa-question-circle'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_info[0]; ?>">
                                                        <i class="<?php echo $badge_info[2]; ?> me-1"></i><?php echo $badge_info[1]; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($room['availability_status'] == 'occupied' && !empty($room['guest_name'])): ?>
                                                <div class="alert alert-info py-2 mb-3">
                                                    <small>
                                                        <strong>Guest:</strong> <?php echo htmlspecialchars($room['guest_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?><br>
                                                        <strong>Checkout:</strong> <?php echo ($room['check_out_date'] ? date('M j, Y', strtotime($room['check_out_date'])) : 'N/A'); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <strong class="text-success"><?php echo format_currency($room['price'] ?? 0); ?></strong>
                                                    <small class="text-muted">/night</small>
                                                </div>
                                                <button class="btn btn-outline-primary btn-sm" onclick="updatePrice(<?php echo $room['id']; ?>, <?php echo $room['price']; ?>)">
                                                    <i class="fas fa-edit"></i> Price
                                                </button>
                                            </div>
                                            
                                            <div class="flex-grow-1">
                                                <label class="form-label small text-muted mb-1">Room Status:</label>
                                                <?php 
                                                // Determine occupancy status
                                                $occupancy_status = '';
                                                if ($room['availability_status'] == 'occupied') {
                                                    $occupancy_status = 'occupied';
                                                } else {
                                                    // Check if room has a confirmed booking
                                                    $booking_check = $conn->query("SELECT id FROM bookings WHERE room_id = " . $room['id'] . " AND status = 'confirmed' AND check_in_date > CURDATE()");
                                                    if ($booking_check && $booking_check->num_rows > 0) {
                                                        $occupancy_status = 'booked';
                                                    }
                                                }
                                                ?>
                                                <select class="form-select form-select-sm room-status-dropdown" onchange="updateRoomStatus(<?php echo $room['id']; ?>, this.value)">
                                                    <option value="occupied" <?php echo $occupancy_status == 'occupied' ? 'selected' : ''; ?>>🔴 Occupied</option>
                                                    <option value="booked" <?php echo $occupancy_status == 'booked' ? 'selected' : ''; ?>>🟠 Booked</option>
                                                    <option value="active" <?php echo $room['status'] == 'active' && $occupancy_status == '' ? 'selected' : ''; ?>>🟢 Active</option>
                                                    <option value="maintenance" <?php echo $room['status'] == 'maintenance' ? 'selected' : ''; ?>>🟡 Maintenance</option>
                                                    <option value="inactive" <?php echo $room['status'] == 'inactive' ? 'selected' : ''; ?>>⚫ Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No rooms found</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateRoomStatus(roomId, status) {
            const statusText = status.charAt(0).toUpperCase() + status.slice(1);
            const statusEmojis = {
                'occupied': '🔴',
                'booked': '🟠',
                'active': '🟢',
                'maintenance': '🟡', 
                'inactive': '⚫'
            };
            
            const statusBadges = {
                'occupied': { class: 'danger', text: 'Occupied', icon: 'fas fa-user' },
                'booked': { class: 'warning', text: 'Booked', icon: 'fas fa-calendar-check' },
                'active': { class: 'success', text: 'Available', icon: 'fas fa-check-circle' },
                'maintenance': { class: 'warning', text: 'Maintenance', icon: 'fas fa-tools' },
                'inactive': { class: 'secondary', text: 'Inactive', icon: 'fas fa-pause-circle' }
            };
            
            if (confirm(`Are you sure you want to change this room status to "${statusEmojis[status]} ${statusText}"?`)) {
                // Update badge immediately
                const roomCard = event.target.closest('.room-card');
                const badge = roomCard.querySelector('.badge');
                const badgeInfo = statusBadges[status];
                
                badge.className = `badge bg-${badgeInfo.class}`;
                badge.innerHTML = `<i class="${badgeInfo.icon} me-1"></i>${badgeInfo.text}`;
                
                // Show loading state
                const selectElement = event.target;
                selectElement.disabled = true;
                selectElement.style.opacity = '0.6';
                
                // Show immediate feedback
                showStatusNotification(`Updating room status to ${statusText}...`, 'info');
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="room_id" value="${roomId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else {
                // Reset the select if cancelled
                location.reload();
            }
        }
        
        function updatePrice(roomId, currentPrice) {
            const newPrice = prompt(`Update room price:\n\nCurrent price: ETB ${currentPrice}\n\nEnter new price:`, currentPrice);
            if (newPrice !== null && !isNaN(newPrice) && parseFloat(newPrice) > 0) {
                if (confirm(`Change room price from ETB ${currentPrice} to ETB ${newPrice}?`)) {
                    showStatusNotification(`Updating room price to ETB ${newPrice}...`, 'info');
                    
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="room_id" value="${roomId}">
                        <input type="hidden" name="price" value="${newPrice}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
        
        function showStatusNotification(message, type = 'success') {
            const notification = document.getElementById('statusNotification');
            const messageSpan = document.getElementById('notificationMessage');
            
            // Set message and type
            messageSpan.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'info' ? 'info-circle' : 'exclamation-circle'} me-2"></i>${message}`;
            
            // Set alert class
            notification.className = `alert alert-${type === 'info' ? 'primary' : type} alert-dismissible fade show`;
            notification.style.display = 'block';
            
            // Auto-hide after 4 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.style.display = 'none';
                    notification.style.opacity = '1';
                }, 300);
            }, 4000);
        }
        
        // Show success notification if status was updated
        document.addEventListener('DOMContentLoaded', function() {
            // Check for success message in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const status = urlParams.get('status');
            const room = urlParams.get('room');
            
            if (success === '1' && status && room) {
                const statusText = status.charAt(0).toUpperCase() + status.slice(1);
                showStatusNotification(`Room ${room} status successfully changed to ${statusText}!`, 'success');
                
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            // Auto-hide success/error messages after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(#statusNotification)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 300);
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>