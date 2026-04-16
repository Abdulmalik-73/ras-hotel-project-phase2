<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

// Handle AJAX requests for statistics
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    header('Content-Type: application/json');
    
    $room_stats_query = "SELECT 
                         COUNT(CASE WHEN r.status = 'active' AND b.id IS NULL THEN 1 END) as available_rooms,
                         COUNT(CASE WHEN r.status = 'occupied' OR b.id IS NOT NULL THEN 1 END) as occupied_rooms,
                         COUNT(CASE WHEN r.status = 'maintenance' THEN 1 END) as maintenance_rooms
                         FROM rooms r
                         LEFT JOIN bookings b ON r.id = b.room_id AND b.status = 'checked_in'";
    
    $room_stats = $conn->query($room_stats_query)->fetch_assoc();
    $total_rooms = $room_stats['available_rooms'] + $room_stats['occupied_rooms'];
    $occupancy_rate = $total_rooms > 0 ? round(($room_stats['occupied_rooms'] / $total_rooms) * 100, 1) : 0;
    
    echo json_encode([
        'available' => $room_stats['available_rooms'],
        'occupied' => $room_stats['occupied_rooms'],
        'maintenance' => $room_stats['maintenance_rooms'],
        'occupancy_rate' => $occupancy_rate
    ]);
    exit();
}

// Handle room actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            $room_id = (int)$_POST['room_id'];
            $status = sanitize_input($_POST['status']);
            
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
    header('Location: manager-rooms.php');
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
                     COUNT(CASE WHEN r.status = 'active' AND b.id IS NULL THEN 1 END) as available_rooms,
                     COUNT(CASE WHEN r.status = 'occupied' OR b.id IS NOT NULL THEN 1 END) as occupied_rooms,
                     COUNT(CASE WHEN r.status = 'maintenance' THEN 1 END) as maintenance_rooms
                     FROM rooms r
                     LEFT JOIN bookings b ON r.id = b.room_id AND b.status = 'checked_in'";

$room_stats = $conn->query($room_stats_query)->fetch_assoc();
$total_rooms = $room_stats['available_rooms'] + $room_stats['occupied_rooms'];
$occupancy_rate = $total_rooms > 0 ? round(($room_stats['occupied_rooms'] / $total_rooms) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-manager {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%) !important;
        }
        .navbar-manager .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            transition: left 0.3s ease;
            z-index: 1050;
            overflow-y: auto;
            padding-top: 70px;
        }
        .sidebar.show {
            left: 0;
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            display: none;
        }
        .sidebar-overlay.show {
            display: block;
        }
        .sidebar h4 {
            font-size: 1rem;
            margin-bottom: 0.5rem !important;
            padding: 0 1rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 0.4rem 1rem;
            margin: 0.1rem 0.5rem;
            border-radius: 0.3rem;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
        }
        .sidebar .nav-link i {
            width: 18px;
            font-size: 0.85rem;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .menu-toggle {
            position: fixed;
            top: 70px;
            left: 10px;
            z-index: 1060;
            background: #8e44ad;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-size: 1.2rem;
            transition: left 0.3s ease;
        }
        .menu-toggle.shifted {
            left: 290px;
        }
        .menu-toggle:hover {
            background: #9b59b6;
        }
        .main-content-wrapper {
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        .main-content-wrapper.shifted {
            margin-left: 280px;
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
    <nav class="navbar navbar-expand-lg navbar-manager">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> 
                <span class="text-white fw-bold">Harar Ras Hotel - Manager Dashboard</span>
            </a>
            <div class="ms-auto">
                
                <span class="text-white me-3">
                    <i class="fas fa-user-tie"></i> Manager
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Hamburger Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h4 class="text-white">
            <i class="fas fa-user-tie"></i> Manager Panel
        </h4>
        
        <nav class="nav flex-column">
            <a href="manager.php" class="nav-link">
                <i class="fas fa-tachometer-alt me-2"></i> Overview
            </a>
            <a href="manager-bookings.php" class="nav-link">
                <i class="fas fa-calendar-check me-2"></i> Manage Bookings
            </a>
            <a href="manager-approve-bill.php" class="nav-link">
                <i class="fas fa-check-circle me-2"></i> Approve Bill
            </a>
            <a href="manager-feedback.php" class="nav-link">
                <i class="fas fa-star me-2"></i> Customer Feedback
            </a>
            <a href="manager-refund.php" class="nav-link">
                <i class="fas fa-undo-alt me-2"></i> Refund Management
            </a>
            <a href="manager-rooms.php" class="nav-link active">
                <i class="fas fa-bed me-2"></i> Room Management
            </a>
            <a href="manager-staff.php" class="nav-link">
                <i class="fas fa-users me-2"></i> Staff Management
            </a>
            <a href="manager-reports.php" class="nav-link">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
            <a href="../logout.php" class="nav-link mt-3">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-12">
                <div class="main-content-wrapper" id="mainContent">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manager.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-bed me-2"></i> Room Management</h2>
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
                                <h4><?php echo $room_stats['available_rooms']; ?></h4>
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
                                        <?php
                                        // Map room images based on room number for unique images
                                        $room_number = (int)$room['room_number'];
                                        $room_type = $room['room_type'] ?? 'standard';
                                        
                                        // Assign unique images based on room number ranges
                                        if ($room_number >= 1 && $room_number <= 4) {
                                            $room_images = ['assets/images/rooms/standard/room12.jpg', 'assets/images/rooms/standard/room13.jpg', 'assets/images/rooms/standard/room14.jpg', 'assets/images/rooms/standard/room15.jpg'];
                                            $room_image = '../' . $room_images[($room_number - 1) % 4];
                                        } elseif ($room_number >= 5 && $room_number <= 8) {
                                            $room_images = ['assets/images/rooms/standard/room16.jpg', 'assets/images/rooms/standard/room17.jpg', 'assets/images/rooms/standard/room18.jpg', 'assets/images/rooms/standard/room19.jpg'];
                                            $room_image = '../' . $room_images[($room_number - 5) % 4];
                                        } elseif ($room_number >= 9 && $room_number <= 12) {
                                            $room_images = ['assets/images/rooms/deluxe/room.jpg', 'assets/images/rooms/deluxe/room2.jpg', 'assets/images/rooms/deluxe/room3.jpg', 'assets/images/rooms/deluxe/room4.jpg'];
                                            $room_image = '../' . $room_images[($room_number - 9) % 4];
                                        } elseif ($room_number >= 13 && $room_number <= 16) {
                                            $room_images = ['assets/images/rooms/deluxe/room5.jpg', 'assets/images/rooms/deluxe/room6.jpg', 'assets/images/rooms/deluxe/room7.jpg', 'assets/images/rooms/deluxe/room8.jpg'];
                                            $room_image = '../' . $room_images[($room_number - 13) % 4];
                                        } elseif ($room_number >= 17 && $room_number <= 20) {
                                            $room_images = ['assets/images/rooms/deluxe/room9.jpg', 'assets/images/rooms/deluxe/room10.jpg', 'assets/images/rooms/standard/room20.jpg', 'assets/images/rooms/suite/room21.jpg'];
                                            $room_image = '../' . $room_images[($room_number - 17) % 4];
                                        } elseif ($room_number >= 21 && $room_number <= 28) {
                                            $room_images = ['assets/images/rooms/suite/room22.jpg', 'assets/images/rooms/suite/room23.jpg', 'assets/images/rooms/suite/room24.jpg', 'assets/images/rooms/suite/room25.jpg', 'assets/images/rooms/family/room27.jpg', 'assets/images/rooms/family/room28.jpg', 'assets/images/rooms/family/room29.jpg', 'assets/images/rooms/family/room30.jpg'];
                                            $room_image = '../' . $room_images[($room_number - 21) % 8];
                                        } elseif ($room_number >= 29 && $room_number <= 32) {
                                            $room_images = ['assets/images/rooms/family/room31.jpg', 'assets/images/rooms/family/room32.jpg', 'assets/images/rooms/family/room33.jpg', 'assets/images/rooms/family/room34.jpg'];
                                            $room_image = '../' . $room_images[($room_number - 29) % 4];
                                        } elseif ($room_number >= 33 && $room_number <= 39) {
                                            $room_images = ['assets/images/rooms/presidential/room35.jpg', 'assets/images/rooms/presidential/room36.jpg', 'assets/images/rooms/presidential/room37.jpg', 'assets/images/rooms/presidential/room38.jpg', 'assets/images/rooms/presidential/room39.jpg', 'assets/images/rooms/suite/room21.jpg', 'assets/images/rooms/deluxe/room.jpg'];
                                            $room_image = '../' . $room_images[($room_number - 33) % 7];
                                        } else {
                                            // Default fallback
                                            $room_image = '../assets/images/rooms/standard/room12.jpg';
                                        }
                                        ?>
                                        <img src="<?php echo $room_image; ?>" class="card-img-top" alt="Room <?php echo htmlspecialchars($room['room_number']); ?>" style="height: 200px; object-fit: cover;">
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
                                                        <strong>Checkout:</strong> <?php echo $room['check_out_date'] ? date('M j, Y', strtotime($room['check_out_date'])) : 'N/A'; ?>
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
                
                // Use AJAX to update without page reload
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('room_id', roomId);
                formData.append('status', status);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Re-enable select
                    selectElement.disabled = false;
                    selectElement.style.opacity = '1';
                    
                    // Update statistics cards
                    updateStatistics();
                    
                    // Show success notification
                    showStatusNotification(`Room status successfully changed to ${statusText}!`, 'success');
                })
                .catch(error => {
                    selectElement.disabled = false;
                    selectElement.style.opacity = '1';
                    showStatusNotification('Error updating room status. Please try again.', 'danger');
                    console.error('Error:', error);
                });
            } else {
                // Reset the select if cancelled
                event.target.value = event.target.options[0].value;
            }
        }
        
        function updateStatistics() {
            // Fetch updated statistics via AJAX
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    // Update Available count
                    const availableCard = document.querySelector('.stat-card.green h4');
                    if (availableCard) {
                        const oldValue = availableCard.textContent;
                        if (oldValue !== data.available.toString()) {
                            availableCard.style.transition = 'all 0.3s ease';
                            availableCard.style.transform = 'scale(1.1)';
                            
                            setTimeout(() => {
                                availableCard.textContent = data.available;
                                availableCard.style.transform = 'scale(1)';
                            }, 150);
                        }
                    }
                    
                    // Update Occupied count
                    const occupiedCard = document.querySelector('.stat-card.red h4');
                    if (occupiedCard) {
                        const oldValue = occupiedCard.textContent;
                        if (oldValue !== data.occupied.toString()) {
                            occupiedCard.style.transition = 'all 0.3s ease';
                            occupiedCard.style.transform = 'scale(1.1)';
                            
                            setTimeout(() => {
                                occupiedCard.textContent = data.occupied;
                                occupiedCard.style.transform = 'scale(1)';
                            }, 150);
                        }
                    }
                    
                    // Update Maintenance count
                    const maintenanceCard = document.querySelector('.stat-card.yellow h4');
                    if (maintenanceCard) {
                        const oldValue = maintenanceCard.textContent;
                        if (oldValue !== data.maintenance.toString()) {
                            maintenanceCard.style.transition = 'all 0.3s ease';
                            maintenanceCard.style.transform = 'scale(1.1)';
                            
                            setTimeout(() => {
                                maintenanceCard.textContent = data.maintenance;
                                maintenanceCard.style.transform = 'scale(1)';
                            }, 150);
                        }
                    }
                    
                    // Update Occupancy Rate
                    const occupancyCard = document.querySelector('.stat-card.blue h4');
                    if (occupancyCard) {
                        const oldValue = occupancyCard.textContent;
                        const newValue = data.occupancy_rate + '%';
                        if (oldValue !== newValue) {
                            occupancyCard.style.transition = 'all 0.3s ease';
                            occupancyCard.style.transform = 'scale(1.1)';
                            
                            setTimeout(() => {
                                occupancyCard.textContent = newValue;
                                occupancyCard.style.transform = 'scale(1)';
                            }, 150);
                        }
                    }
                })
                .catch(error => console.error('Error updating statistics:', error));
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
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuToggle = document.getElementById('menuToggle');
            
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('shifted');
            menuToggle.classList.toggle('shifted');
        }
    </script>
</body>
</html>