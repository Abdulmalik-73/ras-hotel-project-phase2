<?php
/**
 * Admin Dashboard - Protected Page
 * Requires: Admin role authentication
 */

// Start session and load configuration
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require authentication with admin or super_admin role and prevent caching
require_auth_roles(['admin', 'super_admin'], '../login.php');

// Detect user role for proper navigation
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
$is_super_admin = ($user_role === 'super_admin');
$back_link = $is_super_admin ? 'super-admin.php' : 'admin.php';
$back_title = $is_super_admin ? 'Super Admin Dashboard' : 'Admin Dashboard';

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND payment_status = 'paid') as confirmed_bookings,
    (SELECT COUNT(*) FROM bookings WHERE status = 'checked_in') as checked_in,
    (SELECT COUNT(*) FROM users WHERE role = 'guest') as total_guests,
    (SELECT COUNT(*) FROM rooms WHERE status = 'active') as active_rooms,
    (SELECT SUM(total_price) FROM bookings WHERE status IN ('confirmed', 'checked_in', 'checked_out') AND MONTH(created_at) = MONTH(CURDATE())) as monthly_revenue,
    (SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'active') as active_customers,
    (SELECT COUNT(*) FROM users WHERE role = 'receptionist' AND status = 'active') as active_receptionists,
    (SELECT COUNT(*) FROM users WHERE role = 'manager' AND status = 'active') as active_managers";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get recent bookings — only confirmed/checked-in (pending removed, Chapa handles auto-confirm)
$recent_bookings_query = "SELECT b.*, 
                          COALESCE(r.name, 'Food Order') as room_name,
                          r.room_number,
                          CONCAT(u.first_name, ' ', u.last_name) as guest_name 
                          FROM bookings b 
                          LEFT JOIN rooms r ON b.room_id = r.id 
                          JOIN users u ON b.user_id = u.id 
                          WHERE b.booking_type = 'room'
                          AND b.status IN ('confirmed', 'checked_in', 'checked_out')
                          ORDER BY b.created_at DESC LIMIT 10";
$recent_bookings = $conn->query($recent_bookings_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Image Flex */
        .image-flex-container {
            position: relative;
            aspect-ratio: 16 / 5;
            overflow: hidden;
        }
        .image-flex-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }
        .image-flex-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            display: block;
        }
        .image-flex-slide.active {
            opacity: 1;
        }
        
        /* Responsive Image Flex */
        @media (max-width: 768px) {
            .image-flex-container {
                aspect-ratio: 16 / 9;
            }
            .overlay h1 {
                font-size: 1.75rem !important;
            }
            .overlay p {
                font-size: 1rem !important;
            }
        }
        
        /* Responsive Navbar */
        .navbar-brand {
            font-size: 1rem !important;
        }
        .navbar .btn-sm {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
        
        /* Sidebar Responsive */
        .sidebar {
            padding: 1rem !important;
        }
        .list-group-item {
            font-size: 0.9rem;
            padding: 0.75rem 1rem;
        }
        
        @media (max-width: 768px) {
            .sidebar-col {
                position: fixed;
                left: -100%;
                top: 0;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s;
                width: 280px;
                background: #f8f9fa;
                overflow-y: auto;
            }
            .sidebar-col.show {
                left: 0;
                box-shadow: 2px 0 10px rgba(0,0,0,0.3);
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1040;
            }
            .sidebar-overlay.show {
                display: block;
            }
        }
        
        /* Floating Menu Button */
        .floating-menu-btn {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1060;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            align-items: center;
            justify-content: center;
        }
        
        .floating-menu-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }
        
        .floating-menu-btn.active {
            background: linear-gradient(135deg, #ffc107, #ff9800);
        }
        
        @media (max-width: 768px) {
            .floating-menu-btn {
                display: flex;
            }
        }
        
        /* Responsive Cards */
        .card {
            margin-bottom: 1rem;
        }
        .card .card-body {
            padding: 1rem;
        }
        
        @media (max-width: 576px) {
            .card .card-body {
                padding: 0.75rem;
            }
            .card h2 {
                font-size: 1.5rem;
            }
            .card h6 {
                font-size: 0.875rem;
            }
        }
        
        /* Responsive Table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table th, .table td {
            font-size: 0.875rem;
            white-space: nowrap;
        }
        
        @media (max-width: 576px) {
            .table th, .table td {
                font-size: 0.75rem;
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
        <div class="container-fluid">
            <button class="btn btn-outline-light btn-sm me-2 d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> <span class="text-white d-none d-sm-inline">Harar Ras Hotel</span><span class="text-white d-sm-none">Ras Hotel</span>
            </a>
            <div class="ms-auto d-flex align-items-center">
                
                <span class="text-white me-2 d-none d-md-inline">
                    <i class="fas fa-user-shield"></i> Admin
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Floating Menu Button -->
    <button class="floating-menu-btn" id="floatingMenuBtn" title="Menu">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-light sidebar py-4 sidebar-col" id="sidebar">
                <div class="list-group">
                    <a href="admin.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="manage-users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                    <a href="manage-bookings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-check"></i> Bookings
                    </a>
                    <a href="manage-rooms.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-bed"></i> Rooms
                    </a>
                    <a href="manage-services.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-concierge-bell"></i> Services
                    </a>
                    <a href="view-data.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-database"></i> View All Data
                    </a>
                    <a href="<?php echo $is_super_admin ? 'super-admin-settings.php' : 'settings.php'; ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 py-3 py-md-4">
                <!-- Image Flex Hero Section -->
                <div class="image-flex-container mb-3 mb-md-4" style="border-radius: 15px; overflow: hidden; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                    <div class="image-flex-slide active">
                        <img src="../assets/images/hotel/exterior/hotel-main.png" alt="Hotel Exterior" loading="eager">
                    </div>
                    <div class="image-flex-slide">
                        <img src="../assets/images/rooms/deluxe/room.jpg" alt="Deluxe Room" loading="lazy">
                    </div>
                    <div class="image-flex-slide">
                        <img src="../assets/images/rooms/presidential/room35.jpg" alt="Presidential Suite" loading="lazy">
                    </div>
                    <div class="image-flex-slide">
                        <img src="../assets/images/food/ethiopian/food1.jpg" alt="Ethiopian Cuisine" loading="lazy">
                    </div>
                    <div class="image-flex-slide">
                        <img src="../assets/images/rooms/suite/room21.jpg" alt="Suite Room" loading="lazy">
                    </div>
                    <div class="overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(220,53,69,0.4), rgba(176,42,55,0.4)); display: flex; align-items: center; justify-content: center; z-index: 1;">
                        <div class="text-center text-white">
                            <h1 class="display-4 fw-bold mb-2">Welcome to Admin Dashboard</h1>
                            <p class="lead mb-0">Manage your hotel operations efficiently</p>
                        </div>
                    </div>
                </div>
                
                <h2 class="mb-3 mb-md-4 h4 h-md-3">Admin Dashboard</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-3 mb-md-4 g-2 g-md-3">
                    <div class="col-6 col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title small">Confirmed Bookings</h6>
                                        <h2 class="mb-0 h3"><?php echo $stats['confirmed_bookings']; ?></h2>
                                    </div>
                                    <i class="fas fa-calendar-check fa-2x fa-md-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title small">Checked In</h6>
                                        <h2 class="mb-0 h3"><?php echo $stats['checked_in']; ?></h2>
                                    </div>
                                    <i class="fas fa-door-open fa-2x fa-md-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title small">Total Guests</h6>
                                        <h2 class="mb-0 h3"><?php echo $stats['total_guests']; ?></h2>
                                    </div>
                                    <i class="fas fa-users fa-2x fa-md-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, var(--primary-gold), var(--dark-gold));">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title small">Monthly Revenue</h6>
                                        <h2 class="mb-0 h5 h-md-3"><?php echo format_currency($stats['monthly_revenue'] ?? 0); ?></h2>
                                    </div>
                                    <i class="fas fa-dollar-sign fa-2x fa-md-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Statistics -->
                <div class="row mb-3 mb-md-4 g-2 g-md-3">
                    <div class="col-6 col-md-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title small">Active Customers</h6>
                                        <h2 class="mb-0 h3"><?php echo $stats['active_customers']; ?></h2>
                                    </div>
                                    <i class="fas fa-user-tie fa-2x fa-md-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title small">Active Receptionists</h6>
                                        <h2 class="mb-0 h3"><?php echo $stats['active_receptionists']; ?></h2>
                                    </div>
                                    <i class="fas fa-headset fa-2x fa-md-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, #28a745, #20c997);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title small">Active Managers</h6>
                                        <h2 class="mb-0 h3"><?php echo $stats['active_managers']; ?></h2>
                                    </div>
                                    <i class="fas fa-user-secret fa-2x fa-md-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-white bg-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title small">Active Rooms</h6>
                                        <h2 class="mb-0 h3"><?php echo $stats['active_rooms']; ?></h2>
                                    </div>
                                    <i class="fas fa-door-open fa-2x fa-md-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Bookings -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 h6 h-md-5">Recent Bookings</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Guest</th>
                                        <th>Room</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td><strong><?php echo $booking['booking_reference']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($booking['room_name']); 
                                            if (!empty($booking['room_number'])) {
                                                echo ' <span class="text-muted">(No: ' . htmlspecialchars($booking['room_number']) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?></td>
                                        <td><?php echo format_currency($booking['total_price']); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = 'secondary';
                                            if ($booking['status'] == 'confirmed') $badge_class = 'success';
                                            if ($booking['status'] == 'checked_in') $badge_class = 'primary';
                                            if ($booking['status'] == 'cancelled') $badge_class = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                            <a href="view-booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="confirmDelete(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
        
        // Image Flex Animation
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.image-flex-slide');
            let currentSlide = 0;
            let slideCount = 0;
            const maxSlides = 10; // Stop after 10 transitions (2 full cycles)
            
            function nextSlide() {
                if (slideCount >= maxSlides) {
                    return; // Stop animation
                }
                
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
                slideCount++;
                
                if (slideCount < maxSlides) {
                    setTimeout(nextSlide, 2000); // Change image every 2 seconds
                }
            }
            
            // Start animation after 2 seconds
            setTimeout(nextSlide, 2000);
            
            // Mobile Sidebar Toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const floatingMenuBtn = document.getElementById('floatingMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
                if (floatingMenuBtn) {
                    floatingMenuBtn.classList.toggle('active');
                    const icon = floatingMenuBtn.querySelector('i');
                    if (sidebar.classList.contains('show')) {
                        icon.className = 'fas fa-times';
                    } else {
                        icon.className = 'fas fa-bars';
                    }
                }
            }
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
            
            if (floatingMenuBtn) {
                floatingMenuBtn.addEventListener('click', toggleSidebar);
            }
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    if (floatingMenuBtn) {
                        floatingMenuBtn.classList.remove('active');
                        floatingMenuBtn.querySelector('i').className = 'fas fa-bars';
                    }
                });
            }
        });
    </script>

    <!-- Delete Booking Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete booking <strong id="deleteRef"></strong>?</p>
                    <p class="text-danger small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let deleteBookingId = null;

    function confirmDelete(id, ref) {
        deleteBookingId = id;
        document.getElementById('deleteRef').textContent = ref;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!deleteBookingId) return;
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Deleting...';

        const fd = new FormData();
        fd.append('action', 'delete_booking');
        fd.append('booking_id', deleteBookingId);

        fetch('admin-actions.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                if (data.success) {
                    // Remove the row from the table
                    document.querySelectorAll('#recentBookingsTable tr').forEach(row => {
                        if (row.dataset.id == deleteBookingId) row.remove();
                    });
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete booking'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-trash me-1"></i> Delete';
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-trash me-1"></i> Delete';
            });
    });
    </script>
</body>
</html>
