<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

// Get statistics for dashboard
$stats = ['total_bookings'=>0,'todays_bookings'=>0,'monthly_revenue'=>0,'total_rooms'=>0,'occupied_rooms'=>0];
try {
    $stats_result = $conn->query("SELECT 
        (SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed', 'checked_in')) as total_bookings,
        (SELECT COUNT(*) FROM bookings WHERE DATE(check_in_date) = CURDATE() AND status IN ('confirmed', 'checked_in')) as todays_bookings,
        (SELECT SUM(total_price) FROM bookings WHERE MONTH(created_at) = MONTH(CURDATE()) AND status IN ('confirmed', 'checked_in', 'checked_out')) as monthly_revenue,
        (SELECT COUNT(*) FROM rooms WHERE status = 'active') as total_rooms,
        (SELECT COUNT(DISTINCT room_id) FROM bookings WHERE status = 'checked_in') as occupied_rooms");
    if ($stats_result) $stats = array_merge($stats, $stats_result->fetch_assoc() ?? []);
} catch (Exception $e) {}

$occupancy_rate = ($stats['total_rooms'] ?? 0) > 0 ? round((($stats['occupied_rooms'] ?? 0) / $stats['total_rooms']) * 100, 1) : 0;

$recent_bookings = null;
try {
    $recent_bookings = $conn->query("SELECT b.*, COALESCE(r.name,'Food Order') as room_name, COALESCE(r.room_number,'N/A') as room_number, CONCAT(u.first_name,' ',u.last_name) as guest_name FROM bookings b LEFT JOIN rooms r ON b.room_id=r.id JOIN users u ON b.user_id=u.id WHERE b.status IN ('confirmed','checked_in') AND b.booking_type='room' ORDER BY b.created_at DESC LIMIT 10");
} catch (Exception $e) {}

$staff_count = 0;
try {
    $r = $conn->query("SELECT COUNT(*) as c FROM users WHERE role IN ('receptionist','manager')");
    if ($r) $staff_count = $r->fetch_assoc()['c'] ?? 0;
} catch (Exception $e) {}

$status_data = [];
try {
    $sr = $conn->query("SELECT status, COUNT(*) as count FROM bookings WHERE booking_type='room' GROUP BY status");
    if ($sr) while ($row = $sr->fetch_assoc()) $status_data[$row['status']] = $row['count'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Harar Ras Hotel'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
        /* Responsive Navbar */
        .navbar-manager {
            background: linear-gradient(135deg, #d4a574 0%, #c9963d 100%) !important;
            padding: 0.75rem 1rem;
        }
        .navbar-manager .navbar-brand {
            color: #2c3e50 !important;
            font-weight: bold;
            font-size: 1rem;
        }
        .navbar-manager .btn-outline-light {
            color: #fff !important;
            background: #2c3e50 !important;
            border-color: #2c3e50 !important;
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
        .navbar-manager .btn-outline-light:hover {
            background: #1a252f !important;
            border-color: #1a252f !important;
            color: white !important;
        }
        .navbar-manager .user-info {
            color: #2c3e50 !important;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        /* Responsive Sidebar */
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
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
            background: rgba(212, 165, 116, 0.3);
        }
        .sidebar .nav-link i {
            width: 18px;
            font-size: 0.85rem;
        }
        .menu-toggle {
            position: fixed;
            top: 70px;
            left: 10px;
            z-index: 1060;
            background: #34495e;
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
            background: #2c3e50;
        }
        .main-content-wrapper {
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        .main-content-wrapper.shifted {
            margin-left: 280px;
        }
        
        /* Main Content */
        .main-content {
            background: #ecf0f1;
            min-height: 100vh;
            padding: 1rem !important;
        }
        
        /* Responsive Image Flex */
        @media (max-width: 768px) {
            .image-flex-container {
                aspect-ratio: 16 / 9;
            }
            .overlay h1 {
                font-size: 1.5rem !important;
            }
            .overlay p {
                font-size: 0.9rem !important;
            }
        }
        
        @media (max-width: 576px) {
            .overlay h1 {
                font-size: 1.25rem !important;
            }
            .overlay p {
                font-size: 0.8rem !important;
            }
        }
        
        /* Cards */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            border-radius: 10px;
        }
        
        /* Responsive Stat Cards */
        .stat-card {
            border-radius: 15px;
            padding: 1.25rem;
            color: white;
            text-align: center;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .stat-card h3 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        .stat-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .stat-card {
                padding: 1rem;
                min-height: 100px;
            }
            .stat-card h3 {
                font-size: 1.5rem;
            }
            .stat-card p {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card {
                padding: 0.75rem;
                min-height: 90px;
            }
            .stat-card h3 {
                font-size: 1.25rem;
            }
            .stat-card p {
                font-size: 0.75rem;
            }
        }
        
        .stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-card.green { background: linear-gradient(135deg, #27ae60, #229954); }
        .stat-card.orange { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-card.cyan { background: linear-gradient(135deg, #17a2b8, #138496); }
        
        /* Responsive Table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
            font-size: 0.875rem;
            padding: 0.75rem;
        }
        .table td {
            font-size: 0.875rem;
            padding: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .table th, .table td {
                font-size: 0.8rem;
                padding: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .table th, .table td {
                font-size: 0.75rem;
                padding: 0.4rem;
            }
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
        
        /* Room Type Cards */
        .room-type-card {
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        .room-type-card:hover {
            transform: translateY(-5px);
        }
        .room-type-card img {
            height: 180px;
            object-fit: cover;
            width: 100%;
        }
        
        @media (max-width: 768px) {
            .room-type-card img {
                height: 150px;
            }
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        @media (max-width: 992px) {
            .chart-container {
                height: 250px;
            }
        }
        
        /* Sidebar Responsive */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            .sidebar h4 {
                font-size: 1.1rem;
            }
        }
        
        /* Hide sidebar on mobile, show toggle */
        @media (max-width: 767px) {
            .sidebar-col {
                position: fixed;
                left: -100%;
                top: 0;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s;
                width: 280px;
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
            background: linear-gradient(135deg, #34495e, #2c3e50);
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
            background: linear-gradient(135deg, #d4a574, #c9963d);
        }
        
        @media (max-width: 768px) {
            .floating-menu-btn {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-manager">
        <div class="container-fluid">
            <button class="btn btn-outline-light btn-sm me-2 d-md-none" id="sidebarToggle" style="background: #2c3e50; border-color: #2c3e50; color: white;">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel" style="color: #2c3e50;"></i> 
                <span style="color: #2c3e50; font-weight: bold;" class="d-none d-sm-inline">Harar Ras Hotel</span>
                <span style="color: #2c3e50; font-weight: bold;" class="d-sm-none">Ras Hotel</span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="user-info me-2 d-none d-md-inline">
                    <i class="fas fa-user-tie"></i> Manager
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
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
            <a href="manager.php" class="nav-link active">
                <i class="fas fa-tachometer-alt me-2"></i> Overview
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
            <a href="manager-rooms.php" class="nav-link">
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
            <div class="col-12 p-0">
                <div class="main-content-wrapper" id="mainContent">
                <div class="main-content">
                    <!-- Image Flex Hero Section -->
                    <div class="image-flex-container mb-3 mb-md-4" style="border-radius: 15px; overflow: hidden; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <div class="image-flex-slide active">
                            <img src="../assets/images/hotel/exterior/hotel-image.png" alt="Hotel Exterior" loading="eager">
                        </div>
                        <div class="image-flex-slide">
                            <img src="../assets/images/rooms/family/room27.jpg" alt="Family Room" loading="lazy">
                        </div>
                        <div class="image-flex-slide">
                            <img src="../assets/images/food/international/i1.jpg" alt="International Cuisine" loading="lazy">
                        </div>
                        <div class="image-flex-slide">
                            <img src="../assets/images/rooms/standard/room12.jpg" alt="Standard Room" loading="lazy">
                        </div>
                        <div class="image-flex-slide">
                            <img src="../assets/images/food/beverages/b1.jpg" alt="Beverages" loading="lazy">
                        </div>
                        <div class="overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(220,53,69,0.4), rgba(176,42,55,0.4)); display: flex; align-items: center; justify-content: center; z-index: 1;">
                            <div class="text-center text-white">
                                <h1 class="display-4 fw-bold mb-2">Manager Dashboard</h1>
                                <p class="lead mb-0">Oversee operations and drive excellence</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3 mb-md-4">
                        <h2 class="h4 h-md-3"><i class="fas fa-tachometer-alt me-2"></i> <span class="d-none d-sm-inline">Manager </span>Dashboard</h2>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i> <span class="d-none d-sm-inline">Refresh</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-3 mb-md-4 g-2 g-md-3">
                        <div class="col-6 col-md-3 mb-2 mb-md-3">
                            <div class="stat-card blue">
                                <h3><?php echo number_format($stats['total_bookings'] ?? 0); ?></h3>
                                <p><i class="fas fa-calendar-check me-1"></i><span class="d-none d-sm-inline">Total </span>Bookings</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2 mb-md-3">
                            <div class="stat-card green">
                                <h3><?php echo number_format($stats['todays_bookings'] ?? 0); ?></h3>
                                <p><i class="fas fa-calendar-day me-1"></i><span class="d-none d-sm-inline">Today's </span>Bookings</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2 mb-md-3">
                            <div class="stat-card orange">
                                <h3 class="small-text"><?php echo format_currency($stats['monthly_revenue'] ?? 0); ?></h3>
                                <p><i class="fas fa-money-bill-wave me-1"></i><span class="d-none d-sm-inline">Monthly </span>Revenue</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2 mb-md-3">
                            <div class="stat-card cyan">
                                <h3><?php echo $occupancy_rate; ?>%</h3>
                                <p><i class="fas fa-bed me-1"></i>Occupancy<span class="d-none d-sm-inline"> Rate</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Bookings -->
                    <div class="row mb-3 mb-md-4 g-2 g-md-3">
                        <div class="col-lg-8 mb-3 mb-lg-0">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0 h6 h-md-5"><i class="fas fa-list me-2"></i> Recent Bookings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Booking Ref</th>
                                                    <th>Guest</th>
                                                    <th>Room</th>
                                                    <th>Check-in</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                                                    <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($booking['booking_reference']); ?></td>
                                                            <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                                                            <td>
                                                                <?php echo htmlspecialchars($booking['room_name']); ?>
                                                                <br><small class="text-muted">Room <?php echo $booking['room_number']; ?></small>
                                                            </td>
                                                            <td><?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></td>
                                                            <td>
                                                                <?php
                                                                $status_badges = [
                                                                    'pending' => 'warning',
                                                                    'confirmed' => 'success',
                                                                    'checked_in' => 'primary',
                                                                    'checked_out' => 'info',
                                                                    'cancelled' => 'danger'
                                                                ];
                                                                $badge_class = $status_badges[$booking['status']] ?? 'secondary';
                                                                ?>
                                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                                    <?php echo ucfirst($booking['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="manager-bookings.php?view=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">
                                                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                                            <p class="text-muted">No recent bookings found</p>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0 h6 h-md-5"><i class="fas fa-chart-pie me-2"></i> Booking Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="bookingStatusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hotel Room Types -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0 h6 h-md-5"><i class="fas fa-bed me-2"></i> Hotel Room Types</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 g-md-3">
                                <div class="col-md-4 mb-3">
                                    <div class="card room-type-card">
                                        <img src="../assets/images/rooms/deluxe/room.jpg" alt="Deluxe Room">
                                        <div class="card-body">
                                            <h5 class="card-title">Deluxe Room</h5>
                                            <p class="text-muted mb-2">Capacity: 2, Price: ETB 4,000/night</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card room-type-card">
                                        <img src="../assets/images/rooms/standard/room12.jpg" alt="Standard Room">
                                        <div class="card-body">
                                            <h5 class="card-title">Standard Room</h5>
                                            <p class="text-muted mb-2">Capacity: 2, Price: ETB 4,000/night</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card room-type-card">
                                        <img src="../assets/images/rooms/suite/room21.jpg" alt="Suite">
                                        <div class="card-body">
                                            <h5 class="card-title">Suite</h5>
                                            <p class="text-muted mb-2">Capacity: 1, Price: ETB 6,000/night</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4 text-muted">
                        <small>© 2026 Harar Ras Hotel. All Rights Reserved.</small>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
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
            
            // Get booking status data - already loaded in PHP above
            
            
            // Create pie chart
            const canvas = document.getElementById('bookingStatusChart');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                const bookingStatusChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Confirmed', 'Pending', 'Cancelled', 'Checked In', 'Checked Out', 'Refunded'],
                        datasets: [{
                            data: [
                                <?php echo $status_data['confirmed'] ?? 0; ?>,
                                <?php echo $status_data['pending'] ?? 0; ?>,
                                <?php echo $status_data['cancelled'] ?? 0; ?>,
                                <?php echo $status_data['checked_in'] ?? 0; ?>,
                                <?php echo $status_data['checked_out'] ?? 0; ?>,
                                <?php echo $status_data['refunded'] ?? 0; ?>
                            ],
                            backgroundColor: [
                                '#5DADE2',
                                '#F39C12',
                                '#E74C3C',
                                '#27AE60',
                                '#95A5A6',
                                '#9B59B6'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        if (total > 0) {
                                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                                            label += ' (' + percentage + '%)';
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                console.error('Canvas element not found');
            }
            
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