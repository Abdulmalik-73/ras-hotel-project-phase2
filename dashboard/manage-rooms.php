<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

// Handle room actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $name = sanitize_input($_POST['name']);
            $room_number = sanitize_input($_POST['room_number']);
            $room_type = sanitize_input($_POST['room_type']);
            $description = sanitize_input($_POST['description']);
            $capacity = (int)$_POST['capacity'];
            $price = (float)$_POST['price'];
            $status = sanitize_input($_POST['status']);
            
            // Handle image upload
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $upload_dir = '../assets/images/rooms/';
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'room_' . $room_number . '_' . time() . '.' . $file_extension;
                $image_path = 'assets/images/rooms/' . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    // Image uploaded successfully
                } else {
                    $image_path = '';
                }
            }
            
            $query = "INSERT INTO rooms (name, room_number, room_type, description, capacity, price, image, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssidss", $name, $room_number, $room_type, $description, $capacity, $price, $image_path, $status);
            
            if ($stmt->execute()) {
                $new_room_id = $conn->insert_id;
                set_message('success', 'Room added successfully!');
                
                // Log the room addition for debugging
                error_log("New room added: ID=$new_room_id, Name=$name, Number=$room_number, Status=$status");
                
                // Force a redirect to clear any potential caching
                header('Location: manage-rooms.php?added=1');
                exit();
                
            } else {
                set_message('error', 'Failed to add room: ' . $stmt->error);
                error_log("Failed to add room: " . $stmt->error);
            }
            break;
            
        case 'update':
            $room_id = (int)$_POST['room_id'];
            $name = sanitize_input($_POST['name']);
            $room_number = sanitize_input($_POST['room_number']);
            $room_type = sanitize_input($_POST['room_type']);
            $description = sanitize_input($_POST['description']);
            $capacity = (int)$_POST['capacity'];
            $price = (float)$_POST['price'];
            $status = sanitize_input($_POST['status']);
            
            // Handle image upload
            $image_update = '';
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $upload_dir = '../assets/images/rooms/';
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'room_' . $room_number . '_' . time() . '.' . $file_extension;
                $image_path = 'assets/images/rooms/' . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image_update = ", image = ?";
                }
            }
            
            if ($image_update) {
                $query = "UPDATE rooms SET name = ?, room_number = ?, room_type = ?, 
                          description = ?, capacity = ?, price = ?, status = ? $image_update 
                          WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssidsi", $name, $room_number, $room_type, $description, $capacity, $price, $status, $image_path, $room_id);
            } else {
                $query = "UPDATE rooms SET name = ?, room_number = ?, room_type = ?, 
                          description = ?, capacity = ?, price = ?, status = ? 
                          WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssidsi", $name, $room_number, $room_type, $description, $capacity, $price, $status, $room_id);
            }
            
            if ($stmt->execute()) {
                set_message('success', 'Room updated successfully!');
                header('Location: manage-rooms.php?updated=1');
                exit();
            } else {
                set_message('error', 'Failed to update room: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $room_id = (int)$_POST['room_id'];
            
            // Check if room has active bookings
            $check_query = "SELECT COUNT(*) as count FROM bookings WHERE room_id = $room_id AND status IN ('confirmed', 'checked_in')";
            $check_result = $conn->query($check_query);
            $active_bookings = $check_result->fetch_assoc()['count'];
            
            if ($active_bookings > 0) {
                set_message('error', 'Cannot delete room with active bookings');
            } else {
                $query = "DELETE FROM rooms WHERE id = $room_id";
                if ($conn->query($query)) {
                    set_message('success', 'Room deleted successfully');
                } else {
                    set_message('error', 'Failed to delete room');
                }
            }
            break;
    }
    header('Location: manage-rooms.php');
    exit();
}

// Get rooms with filters
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$room_number_filter = $_GET['room_number'] ?? '';

$where_conditions = [];

// Default: Show only rooms 1, 2, 3 if no room number filter is applied
if ($room_number_filter) {
    $where_conditions[] = "room_number = '" . sanitize_input($room_number_filter) . "'";
} elseif (!isset($_GET['type']) && !isset($_GET['status']) && !isset($_GET['search'])) {
    // Only apply default filter if no other filters are active
    $where_conditions[] = "room_number IN ('1', '2', '3')";
}

if ($type_filter) {
    $where_conditions[] = "room_type = '" . sanitize_input($type_filter) . "'";
}
if ($status_filter) {
    $where_conditions[] = "status = '" . sanitize_input($status_filter) . "'";
}
if ($search) {
    $search_term = sanitize_input($search);
    $where_conditions[] = "(name LIKE '%$search_term%' OR room_number LIKE '%$search_term%' OR description LIKE '%$search_term%')";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$rooms_query = "SELECT r.*, 
                (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id AND b.status IN ('confirmed', 'checked_in') AND CURDATE() BETWEEN b.check_in_date AND b.check_out_date) as is_occupied
                FROM rooms r 
                $where_clause 
                ORDER BY CAST(r.room_number AS UNSIGNED)";

$rooms = $conn->query($rooms_query)->fetch_all(MYSQLI_ASSOC);

// Get room types for filter
$room_types = ['standard', 'deluxe', 'suite', 'family', 'presidential', 'single', 'double', 'executive'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel - Admin Dashboard
            </a>
            <div class="ms-auto">
                
                <span class="text-white me-3">
                    <i class="fas fa-user-shield"></i> <?php echo $_SESSION['user_name']; ?>
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
            <div class="col-md-3 col-lg-2 bg-light sidebar py-4">
                <div class="list-group">
                    <a href="admin.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="manage-bookings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-check"></i> Bookings
                    </a>
                    <a href="manage-rooms.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-bed"></i> Rooms
                    </a>
                    <a href="manage-services.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-concierge-bell"></i> Services
                    </a>
                    <a href="view-data.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-database"></i> View All Data
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <a href="admin.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                        </a>
                        <h2 class="d-inline"><i class="fas fa-bed me-2"></i> Manage Rooms</h2>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                        <i class="fas fa-plus"></i> Add New Room
                    </button>
                </div>
                
                <?php display_message(); ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Room Number</label>
                                <select name="room_number" class="form-select">
                                    <option value="">All Rooms</option>
                                    <?php for ($i = 1; $i <= 39; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $room_number_filter == $i ? 'selected' : ''; ?>>
                                        Room <?php echo $i; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Room Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <?php foreach ($room_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $type_filter == $type ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($type); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="maintenance" <?php echo $status_filter == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Room name, number, or description" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Rooms Grid -->
                <div class="row">
                    <?php foreach ($rooms as $room): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <?php 
                            // Map room images based on room number for unique images
                            $room_number = (int)$room['room_number'];
                            $room_image = '';
                            
                            if ($room['image']) {
                                // Use database image if available
                                $room_image = '../' . $room['image'];
                            } else {
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
                            }
                            ?>
                            <img src="<?php echo $room_image; ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?php echo htmlspecialchars($room['name']); ?>">
                            
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title"><?php echo htmlspecialchars($room['name']); ?></h5>
                                    <span class="badge bg-<?php echo $room['status'] == 'active' ? 'success' : ($room['status'] == 'maintenance' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($room['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="row text-sm mb-3">
                                    <div class="col-6">
                                        <strong>Room:</strong> <?php echo $room['room_number']; ?>
                                    </div>
                                    <div class="col-6">
                                        <strong>Type:</strong> <?php echo ucfirst($room['room_type']); ?>
                                    </div>
                                    <div class="col-6">
                                        <strong>Capacity:</strong> <?php echo $room['capacity']; ?> guests
                                    </div>
                                    <div class="col-6">
                                        <strong>Price:</strong> <?php echo format_currency($room['price']); ?>
                                    </div>
                                </div>
                                
                                <?php if ($room['description']): ?>
                                <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($room['description'], 0, 100)); ?>...</p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if ($room['is_occupied']): ?>
                                        <span class="badge bg-danger">Occupied</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">Available</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="editRoom(<?php echo $room['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteRoom(<?php echo $room['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Room Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Room Number</label>
                                <input type="text" name="room_number" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Room Type</label>
                                <select name="room_type" class="form-select" required>
                                    <?php foreach ($room_types as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo ucfirst($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Capacity</label>
                                <input type="number" name="capacity" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Price (ETB)</label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Room Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Room Modal -->
    <div class="modal fade" id="editRoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="room_id" id="edit_room_id">
                    <div class="modal-body" id="edit-room-form">
                        <!-- Form will be loaded here via JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function editRoom(id) {
            // Load room data and populate edit form
            $.get('api/get-room.php?id=' + id, function(room) {
                $('#edit_room_id').val(room.id);
                
                const formHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Room Name</label>
                            <input type="text" name="name" class="form-control" value="${room.name}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room Number</label>
                            <input type="text" name="room_number" class="form-control" value="${room.room_number}" required>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Room Type</label>
                            <select name="room_type" class="form-select" required>
                                <?php foreach ($room_types as $type): ?>
                                <option value="<?php echo $type; ?>" ${room.room_type === '<?php echo $type; ?>' ? 'selected' : ''}><?php echo ucfirst($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" class="form-control" min="1" value="${room.capacity}" required>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Price (ETB)</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" value="${room.price}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" ${room.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${room.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                <option value="maintenance" ${room.status === 'maintenance' ? 'selected' : ''}>Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3">${room.description || ''}</textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Room Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        ${room.image ? `<small class="text-muted">Current image: ${room.image}</small>` : ''}
                    </div>
                `;
                
                $('#edit-room-form').html(formHtml);
                $('#editRoomModal').modal('show');
            });
        }
        
        function deleteRoom(id) {
            if (confirm('Are you sure you want to delete this room?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="room_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>