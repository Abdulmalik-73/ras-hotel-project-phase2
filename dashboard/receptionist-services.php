<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('receptionist', '../login.php');

$message = '';
$error = '';
$booking_data = null;

// Handle service addition
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'search_guest') {
        $booking_reference = sanitize_input($_POST['booking_reference']);
        
        $search_query = "SELECT b.*, r.name as room_name, r.room_number,
                         CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone
                         FROM bookings b
                         JOIN rooms r ON b.room_id = r.id
                         JOIN users u ON b.user_id = u.id
                         WHERE b.booking_reference = '$booking_reference'
                         AND b.status = 'checked_in'";
        
        $result = $conn->query($search_query);
        if ($result && $result->num_rows > 0) {
            $booking_data = $result->fetch_assoc();
        } else {
            $error = 'Guest not found or not currently checked in';
        }
    } elseif ($_POST['action'] == 'add_service') {
        $booking_id = (int)$_POST['booking_id'];
        $service_name = sanitize_input($_POST['service_name']);
        $service_amount = (float)$_POST['service_amount'];
        $service_notes = sanitize_input($_POST['service_notes']);
        
        // Add service charge to booking total
        $update_query = "UPDATE bookings SET total_price = total_price + ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("di", $service_amount, $booking_id);
        
        if ($stmt->execute()) {
            // Log the service addition (you could create a separate services_log table)
            $booking_query = "SELECT user_id, booking_reference FROM bookings WHERE id = $booking_id";
            $booking_result = $conn->query($booking_query);
            if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                log_booking_activity($booking_id, $booking['user_id'], 'modified', 'checked_in', 'checked_in', "Service added: $service_name (" . format_currency($service_amount) . ")", $_SESSION['user_id']);
            }
            
            $message = "Service '$service_name' added successfully for " . format_currency($service_amount);
            $booking_data = null; // Reset form
        } else {
            $error = 'Failed to add service: ' . $stmt->error;
        }
    }
}

// Get available services
$services = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY category, name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Services - Receptionist Dashboard</title>
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
        .booking-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        .service-card {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .service-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        .service-card.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
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
                        <a href="receptionist-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Manage Rooms
                        </a>
                        <a href="receptionist-services.php" class="nav-link active">
                            <i class="fas fa-utensils me-2"></i> Manage Foods & Services
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
                            <h2 class="d-inline"><i class="fas fa-concierge-bell me-2"></i> Guest Services</h2>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$booking_data): ?>
                    <!-- Search Guest Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-search me-2"></i> Search Guest for Service</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="search_guest">
                                <div class="row">
                                    <div class="col-md-8">
                                        <label class="form-label">Booking Reference</label>
                                        <input type="text" name="booking_reference" class="form-control form-control-lg" 
                                               placeholder="Enter booking reference of checked-in guest" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-success btn-lg w-100">
                                            <i class="fas fa-search me-2"></i> Search Guest
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Available Foods & Services Display -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i> Available Foods & Services</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($services->num_rows > 0): ?>
                                <?php 
                                $services_by_category = [];
                                
                                // Group services by category
                                $services->data_seek(0); // Reset pointer
                                while ($service = $services->fetch_assoc()) {
                                    $services_by_category[$service['category']][] = $service;
                                }
                                
                                foreach ($services_by_category as $category => $category_services):
                                ?>
                                <div class="mb-4">
                                    <h5 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-<?php echo $category == 'restaurant' ? 'utensils' : ($category == 'spa' ? 'spa' : 'concierge-bell'); ?> me-2"></i>
                                        <?php echo ucwords(str_replace('_', ' ', $category)); ?>
                                    </h5>
                                    <div class="row">
                                        <?php foreach ($category_services as $service): ?>
                                        <div class="col-md-6 col-lg-3 mb-3">
                                            <div class="card h-100">
                                                <?php if ($service['image']): ?>
                                                <img src="<?php echo htmlspecialchars($service['image']); ?>" 
                                                     class="card-img-top" 
                                                     alt="<?php echo htmlspecialchars($service['name']); ?>"
                                                     style="height: 150px; object-fit: cover;">
                                                <?php endif; ?>
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h6>
                                                    <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($service['description'], 0, 60)) . (strlen($service['description']) > 60 ? '...' : ''); ?></p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <strong class="text-success"><?php echo format_currency($service['price']); ?></strong>
                                                        <span class="badge bg-<?php echo $category == 'restaurant' ? 'warning' : ($category == 'spa' ? 'info' : 'secondary'); ?>">
                                                            <?php echo ucfirst($category); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No services available at the moment.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Service Addition Section -->
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Guest Information -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-user me-2"></i> Guest Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="booking-info">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Guest:</strong> <?php echo htmlspecialchars($booking_data['guest_name']); ?></p>
                                                <p><strong>Room:</strong> <?php echo htmlspecialchars($booking_data['room_name'] . ' (' . $booking_data['room_number'] . ')'); ?></p>
                                                <p><strong>Booking Ref:</strong> <?php echo htmlspecialchars($booking_data['booking_reference']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Check-in:</strong> <?php echo date('M j, Y', strtotime($booking_data['check_in_date'])); ?></p>
                                                <p><strong>Check-out:</strong> <?php echo date('M j, Y', strtotime($booking_data['check_out_date'])); ?></p>
                                                <p><strong>Current Total:</strong> <?php echo format_currency($booking_data['total_price']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Available Services -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-list me-2"></i> Available Foods & Services</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($services->num_rows > 0): ?>
                                        <?php 
                                        $current_category = '';
                                        $services_by_category = [];
                                        
                                        // Group services by category
                                        $services->data_seek(0); // Reset pointer
                                        while ($service = $services->fetch_assoc()) {
                                            $services_by_category[$service['category']][] = $service;
                                        }
                                        
                                        foreach ($services_by_category as $category => $category_services):
                                        ?>
                                        <div class="mb-4">
                                            <h5 class="text-primary border-bottom pb-2 mb-3">
                                                <i class="fas fa-<?php echo $category == 'restaurant' ? 'utensils' : ($category == 'spa' ? 'spa' : 'concierge-bell'); ?> me-2"></i>
                                                <?php echo ucwords(str_replace('_', ' ', $category)); ?>
                                            </h5>
                                            <div class="row">
                                                <?php foreach ($category_services as $service): ?>
                                                <div class="col-md-6 col-lg-4 mb-3">
                                                    <div class="card service-card h-100" onclick="selectService('<?php echo htmlspecialchars($service['name']); ?>', <?php echo $service['price']; ?>)">
                                                        <?php if ($service['image']): ?>
                                                        <img src="<?php echo htmlspecialchars($service['image']); ?>" 
                                                             class="card-img-top" 
                                                             alt="<?php echo htmlspecialchars($service['name']); ?>"
                                                             style="height: 150px; object-fit: cover;">
                                                        <?php endif; ?>
                                                        <div class="card-body">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h6>
                                                            <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($service['description'], 0, 80)) . (strlen($service['description']) > 80 ? '...' : ''); ?></p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <strong class="text-success"><?php echo format_currency($service['price']); ?></strong>
                                                                <span class="badge bg-<?php echo $category == 'restaurant' ? 'warning' : ($category == 'spa' ? 'info' : 'secondary'); ?>">
                                                                    <?php echo ucfirst($category); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted">No services available at the moment.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Add Service Form -->
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Add Service Charge</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="add_service">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_data['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Service Name</label>
                                            <input type="text" name="service_name" id="service_name" class="form-control" 
                                                   placeholder="Enter service name" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Amount (ETB)</label>
                                            <input type="number" name="service_amount" id="service_amount" class="form-control" 
                                                   step="0.01" min="0" placeholder="0.00" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Notes</label>
                                            <textarea name="service_notes" class="form-control" rows="3" 
                                                      placeholder="Additional notes about the service..."></textarea>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i> Add Service
                                            </button>
                                            <a href="receptionist-services.php" class="btn btn-secondary">
                                                <i class="fas fa-search me-2"></i> New Search
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Service Instructions -->
                            <div class="card mt-3">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Instructions</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled small">
                                        <li><i class="fas fa-check text-success me-2"></i> Click on a service to auto-fill</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Or enter custom service details</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Amount will be added to guest's bill</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Service will appear on final invoice</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectService(name, price) {
            // Remove previous selections
            document.querySelectorAll('.service-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Mark current selection
            event.currentTarget.classList.add('selected');
            
            // Fill form
            document.getElementById('service_name').value = name;
            document.getElementById('service_amount').value = price.toFixed(2);
        }
    </script>
</body>
</html>