<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

// Handle service actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $name = sanitize_input($_POST['name']);
            $category = sanitize_input($_POST['category']);
            $description = sanitize_input($_POST['description']);
            $price = (float)$_POST['price'];
            $status = sanitize_input($_POST['status']);
            
            $query = "INSERT INTO services (name, category, description, price, status) 
                      VALUES ('$name', '$category', '$description', $price, '$status')";
            
            if ($conn->query($query)) {
                $new_service_id = $conn->insert_id;
                set_message('success', 'Service added successfully! <a href="../services.php" target="_blank" class="alert-link">Test customer services page</a> | <a href="../food-booking.php" target="_blank" class="alert-link">Test food booking</a>');
                
                // Log the service addition for debugging
                error_log("New service added: ID=$new_service_id, Name=$name, Category=$category, Status=$status");
                
            } else {
                set_message('error', 'Failed to add service: ' . $conn->error);
                error_log("Failed to add service: " . $conn->error);
            }
            break;
            
        case 'update':
            $service_id = (int)$_POST['service_id'];
            $name = sanitize_input($_POST['name']);
            $category = sanitize_input($_POST['category']);
            $description = sanitize_input($_POST['description']);
            $price = (float)$_POST['price'];
            $status = sanitize_input($_POST['status']);
            
            $query = "UPDATE services SET name = '$name', category = '$category', 
                      description = '$description', price = $price, status = '$status',
                      updated_at = NOW() WHERE id = $service_id";
            
            if ($conn->query($query)) {
                set_message('success', 'Service updated successfully! <a href="../services.php" target="_blank" class="alert-link">Test customer services page</a>');
            } else {
                set_message('error', 'Failed to update service: ' . $conn->error);
            }
            break;
            
        case 'delete':
            $service_id = (int)$_POST['service_id'];
            
            $query = "DELETE FROM services WHERE id = $service_id";
            if ($conn->query($query)) {
                set_message('success', 'Service deleted successfully');
            } else {
                set_message('error', 'Failed to delete service');
            }
            break;
    }
    header('Location: manage-services.php');
    exit();
}

// Get services with filters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
if ($category_filter) {
    $where_conditions[] = "category = '" . sanitize_input($category_filter) . "'";
}
if ($status_filter) {
    $where_conditions[] = "status = '" . sanitize_input($status_filter) . "'";
}
if ($search) {
    $search_term = sanitize_input($search);
    $where_conditions[] = "(name LIKE '%$search_term%' OR description LIKE '%$search_term%')";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "SELECT * FROM services $where_clause ORDER BY category, name";
$services = $conn->query($query) ?: null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Harar Ras Hotel'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
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
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .badge {
            font-size: 0.75em;
        }
        .service-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2em;
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
                        <i class="fas fa-hotel"></i> Admin Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="admin.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="manage-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Manage Rooms
                        </a>
                        <a href="manage-bookings.php" class="nav-link">
                            <i class="fas fa-calendar-check me-2"></i> Manage Bookings
                        </a>
                        <a href="manage-services.php" class="nav-link active">
                            <i class="fas fa-concierge-bell me-2"></i> Manage Services
                        </a>
                        <a href="settings.php" class="nav-link">
                            <i class="fas fa-cog me-2"></i> Settings
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
                            <a href="admin.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-concierge-bell me-2"></i> Manage Services</h2>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                            <i class="fas fa-plus me-2"></i> Add New Service
                        </button>
                    </div>
                    
                    <?php display_message(); ?>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select">
                                        <option value="">All Categories</option>
                                        <option value="restaurant" <?php echo $category_filter == 'restaurant' ? 'selected' : ''; ?>>Restaurant</option>
                                        <option value="spa" <?php echo $category_filter == 'spa' ? 'selected' : ''; ?>>Spa</option>
                                        <option value="laundry" <?php echo $category_filter == 'laundry' ? 'selected' : ''; ?>>Laundry</option>
                                        <option value="transport" <?php echo $category_filter == 'transport' ? 'selected' : ''; ?>>Transport</option>
                                        <option value="tours" <?php echo $category_filter == 'tours' ? 'selected' : ''; ?>>Tours</option>
                                        <option value="other" <?php echo $category_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search services..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-outline-primary d-block w-100">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Services Grid -->
                    <div class="row">
                        <?php if ($services && $services->num_rows > 0): ?>
                            <?php 
                            $service_index = 0;
                            while ($service = $services->fetch_assoc()): 
                                // Map service images based on category
                                $category = $service['category'];
                                $service_images = [
                                    'restaurant' => [
                                        'assets/images/food/ethiopian/food1.jpg',
                                        'assets/images/food/ethiopian/food3.jpg',
                                        'assets/images/food/ethiopian/food5.jpg',
                                        'assets/images/food/international/i1.jpg',
                                        'assets/images/food/international/i3.jpg',
                                        'assets/images/food/beverages/b1.jpg'
                                    ],
                                    'spa' => [
                                        'assets/images/rooms/deluxe/room.jpg',
                                        'assets/images/rooms/deluxe/room2.jpg',
                                        'assets/images/rooms/presidential/room35.jpg'
                                    ],
                                    'laundry' => [
                                        'assets/images/rooms/standard/room12.jpg',
                                        'assets/images/rooms/standard/room13.jpg'
                                    ],
                                    'transport' => [
                                        'assets/images/hotel/exterior/hotel-main.png',
                                        'assets/images/hotel/exterior/hotel-image.png'
                                    ],
                                    'tours' => [
                                        'assets/images/banners/baner.jpg',
                                        'assets/images/banners/download.jpg'
                                    ],
                                    'other' => [
                                        'assets/images/rooms/family/room27.jpg',
                                        'assets/images/rooms/suite/room21.jpg'
                                    ]
                                ];
                                
                                $category_images = $service_images[$category] ?? $service_images['other'];
                                $service_image = '../' . $category_images[$service_index % count($category_images)];
                                $service_index++;
                            ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <img src="<?php echo $service_image; ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?php echo htmlspecialchars($service['name']); ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h5>
                                            <span class="badge bg-<?php echo $service['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($service['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="badge" style="background: <?php 
                                                $colors = [
                                                    'restaurant' => '#e74c3c',
                                                    'spa' => '#9b59b6',
                                                    'laundry' => '#3498db',
                                                    'transport' => '#f39c12',
                                                    'tours' => '#27ae60',
                                                    'other' => '#95a5a6'
                                                ];
                                                echo $colors[$service['category']] ?? '#95a5a6';
                                            ?>">
                                                <i class="fas fa-<?php 
                                                    $icons = [
                                                        'restaurant' => 'utensils',
                                                        'spa' => 'spa',
                                                        'laundry' => 'tshirt',
                                                        'transport' => 'car',
                                                        'tours' => 'map-marked-alt',
                                                        'other' => 'concierge-bell'
                                                    ];
                                                    echo $icons[$service['category']] ?? 'concierge-bell';
                                                ?> me-1"></i>
                                                <?php echo ucfirst($service['category']); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($service['description']): ?>
                                        <p class="card-text text-muted small mb-3">
                                            <?php echo htmlspecialchars(substr($service['description'], 0, 100)); ?>
                                            <?php if (strlen($service['description']) > 100): ?>...<?php endif; ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if ($service['price'] > 0): ?>
                                                    <strong class="text-success"><?php echo formatCurrency($service['price']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic small">Complimentary</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editService(<?php echo htmlspecialchars(json_encode($service)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteService(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-concierge-bell fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No services found</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Service</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Service Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="restaurant">Restaurant</option>
                                    <option value="spa">Spa</option>
                                    <option value="laundry">Laundry</option>
                                    <option value="transport">Transport</option>
                                    <option value="tours">Tours</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Describe the service..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price (ETB)</label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Service Modal -->
    <div class="modal fade" id="editServiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Service</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="service_id" id="edit_service_id">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Service Name</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" id="edit_category" class="form-select" required>
                                    <option value="restaurant">Restaurant</option>
                                    <option value="spa">Spa</option>
                                    <option value="laundry">Laundry</option>
                                    <option value="transport">Transport</option>
                                    <option value="tours">Tours</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price (ETB)</label>
                                <input type="number" name="price" id="edit_price" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editService(service) {
            document.getElementById('edit_service_id').value = service.id;
            document.getElementById('edit_name').value = service.name;
            document.getElementById('edit_category').value = service.category;
            document.getElementById('edit_description').value = service.description || '';
            document.getElementById('edit_price').value = service.price;
            document.getElementById('edit_status').value = service.status;
            
            new bootstrap.Modal(document.getElementById('editServiceModal')).show();
        }
        
        function deleteService(serviceId, serviceName) {
            if (confirm(`Are you sure you want to delete service "${serviceName}"?\n\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="service_id" value="${serviceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>