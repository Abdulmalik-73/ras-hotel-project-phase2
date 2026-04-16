<?php
// Suppress warnings and notices in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

// Handle booking actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    
    switch ($action) {
        case 'confirm':
            // UNIFIED APPROVAL: pending → checked_in (not just confirmed)
            $query = "UPDATE bookings SET status = 'checked_in', actual_checkin_time = NOW(), checked_in_by = ? WHERE id = ? AND status = 'pending'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $_SESSION['user_id'], $booking_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Log the approval and check-in
                $booking_query = "SELECT b.*, r.name as room_name, r.room_number, 
                                 CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                                 u.email, u.phone
                                 FROM bookings b
                                 LEFT JOIN rooms r ON b.room_id = r.id
                                 JOIN users u ON b.user_id = u.id
                                 WHERE b.id = ?";
                $log_stmt = $conn->prepare($booking_query);
                $log_stmt->bind_param("i", $booking_id);
                $log_stmt->execute();
                $booking = $log_stmt->get_result()->fetch_assoc();
                
                if ($booking) {
                    // Create checkin record
                    $nights = (int)((strtotime($booking['check_out_date']) - strtotime($booking['check_in_date'])) / (60 * 60 * 24));
                    $confirmation_number = 'CHK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    
                    $checkin_insert = $conn->prepare("
                        INSERT INTO checkins (
                            customer_id, booking_id, hotel_name, hotel_location, 
                            check_in_date, check_out_date,
                            guest_full_name, guest_date_of_birth, guest_id_type, guest_id_number, 
                            guest_nationality, guest_home_address, guest_phone_number, guest_email_address,
                            room_type, room_number, nights_stay, number_of_guests, rate_per_night,
                            payment_type, amount_paid, balance_due, confirmation_number, 
                            additional_requests, checked_in_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $hotel_name = 'Harar Ras Hotel';
                    $hotel_location = 'Jugol Street, Harar, Ethiopia';
                    $guest_dob = '1990-01-01';
                    $id_type = $booking['id_type'] ?? 'national_id';
                    $id_number = $booking['id_number'] ?? 'N/A';
                    $nationality = 'Ethiopian';
                    $address = $booking['special_requests'] ?? 'N/A';
                    $payment_type = $booking['payment_method'] ?? 'cash';
                    $amount_paid = (float)$booking['total_price'];
                    $balance_due = 0.00;
                    $additional_requests = $booking['special_requests'] ?? '';
                    $number_of_guests = (int)$booking['customers'];
                    $rate_per_night = (float)$booking['total_price'];
                    $user_id = (int)$booking['user_id'];
                    $checked_in_by = (int)$_SESSION['user_id'];
                    
                    $guest_phone = $booking['phone'] ?? 'N/A';
                    $guest_email = $booking['email'] ?? 'noemail@example.com';
                    $guest_name = $booking['guest_name'] ?? 'Guest';
                    $room_name = $booking['room_name'] ?? 'Standard Room';
                    $room_number = $booking['room_number'] ?? 'N/A';
                    
                    $checkin_insert->bind_param(
                        "iissssssssssssssiidsddssi",
                        $user_id, $booking_id, $hotel_name, $hotel_location,
                        $booking['check_in_date'], $booking['check_out_date'],
                        $guest_name, $guest_dob, $id_type, $id_number,
                        $nationality, $address, $guest_phone, $guest_email,
                        $room_name, $room_number, $nights, $number_of_guests,
                        $rate_per_night, $payment_type, $amount_paid, $balance_due,
                        $confirmation_number, $additional_requests, $checked_in_by
                    );
                    
                    $checkin_insert->execute();
                }
                
                set_message('success', 'Booking approved and customer checked in successfully');
            } else {
                set_message('error', 'Failed to approve booking or booking already processed');
            }
            break;
            
        case 'cancel':
            $reason = sanitize_input($_POST['cancel_reason'] ?? '');
            $query = "UPDATE bookings SET status = 'cancelled' WHERE id = $booking_id";
            if ($conn->query($query)) {
                set_message('success', 'Booking cancelled successfully');
            } else {
                set_message('error', 'Failed to cancel booking');
            }
            break;
            
        case 'checkin':
            // Get booking details first
            $booking_query = "SELECT b.*, r.name as room_name, r.room_number, 
                             CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                             u.email, u.phone
                             FROM bookings b
                             LEFT JOIN rooms r ON b.room_id = r.id
                             JOIN users u ON b.user_id = u.id
                             WHERE b.id = $booking_id";
            $booking_result = $conn->query($booking_query);
            
            if ($booking_result && $booking_result->num_rows > 0) {
                $booking = $booking_result->fetch_assoc();
                
                // Update booking status
                $query = "UPDATE bookings SET status = 'checked_in', actual_checkin_time = NOW(), checked_in_by = {$_SESSION['user_id']} WHERE id = $booking_id";
                if ($conn->query($query)) {
                    // Create checkin record
                    $nights = (int)((strtotime($booking['check_out_date']) - strtotime($booking['check_in_date'])) / (60 * 60 * 24));
                    $confirmation_number = 'CHK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    
                    $checkin_insert = $conn->prepare("
                        INSERT INTO checkins (
                            customer_id, booking_id, hotel_name, hotel_location, 
                            check_in_date, check_out_date,
                            guest_full_name, guest_date_of_birth, guest_id_type, guest_id_number, 
                            guest_nationality, guest_home_address, guest_phone_number, guest_email_address,
                            room_type, room_number, nights_stay, number_of_guests, rate_per_night,
                            payment_type, amount_paid, balance_due, confirmation_number, 
                            additional_requests, checked_in_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    // Prepare all variables with proper defaults
                    $hotel_name = 'Harar Ras Hotel';
                    $hotel_location = 'Jugol Street, Harar, Ethiopia';
                    $guest_dob = '1990-01-01';
                    $id_type = $booking['id_type'] ?? 'national_id';
                    $id_number = $booking['id_number'] ?? 'N/A';
                    $nationality = 'Ethiopian';
                    $address = $booking['special_requests'] ?? 'N/A';
                    $payment_type = $booking['payment_method'] ?? 'cash';
                    $amount_paid = (float)$booking['total_price'];
                    $balance_due = 0.00;
                    $additional_requests = $booking['special_requests'] ?? '';
                    $number_of_guests = (int)$booking['customers'];
                    $rate_per_night = (float)$booking['total_price'];
                    $user_id = (int)$booking['user_id'];
                    $checked_in_by = (int)$_SESSION['user_id'];
                    
                    // Handle NULL phone and email with defaults
                    $guest_phone = $booking['phone'] ?? 'N/A';
                    $guest_email = $booking['email'] ?? 'noemail@example.com';
                    $guest_name = $booking['guest_name'] ?? 'Guest';
                    $room_name = $booking['room_name'] ?? 'Standard Room';
                    $room_number = $booking['room_number'] ?? 'N/A';
                    
                    // 25 parameters: i i s s s s s s s s s s s s s s i i d s d d s s i
                    $checkin_insert->bind_param(
                        "iissssssssssssssiidsddssi",
                        $user_id,              // 1 - i (integer)
                        $booking_id,           // 2 - i (integer)
                        $hotel_name,           // 3 - s (string)
                        $hotel_location,       // 4 - s (string)
                        $booking['check_in_date'],   // 5 - s (string/date)
                        $booking['check_out_date'],  // 6 - s (string/date)
                        $guest_name,           // 7 - s (string)
                        $guest_dob,            // 8 - s (string/date)
                        $id_type,              // 9 - s (string)
                        $id_number,            // 10 - s (string)
                        $nationality,          // 11 - s (string)
                        $address,              // 12 - s (string)
                        $guest_phone,          // 13 - s (string) - FIXED: Now has default
                        $guest_email,          // 14 - s (string) - FIXED: Now has default
                        $room_name,            // 15 - s (string) - FIXED: Now has default
                        $room_number,          // 16 - s (string) - FIXED: Now has default
                        $nights,               // 17 - i (integer)
                        $number_of_guests,     // 18 - i (integer)
                        $rate_per_night,       // 19 - d (decimal)
                        $payment_type,         // 20 - s (string)
                        $amount_paid,          // 21 - d (decimal)
                        $balance_due,          // 22 - d (decimal)
                        $confirmation_number,  // 23 - s (string)
                        $additional_requests,  // 24 - s (string)
                        $checked_in_by         // 25 - i (integer)
                    );
                    
                    if ($checkin_insert->execute()) {
                        set_message('success', 'Customer checked in successfully');
                    } else {
                        set_message('error', 'Failed to create check-in record: ' . $checkin_insert->error);
                    }
                } else {
                    set_message('error', 'Failed to check in guest: ' . $conn->error);
                }
            } else {
                set_message('error', 'Booking not found');
            }
            break;
            
        case 'checkout':
            // Ensure the checkins table has the necessary columns
            // Check if actual_checkout column exists
            $check_col = $conn->query("SHOW COLUMNS FROM checkins LIKE 'actual_checkout'");
            if ($check_col->num_rows == 0) {
                // Add actual_checkout column if it doesn't exist
                $conn->query("ALTER TABLE checkins ADD COLUMN actual_checkout TIMESTAMP NULL");
            }
            
            // Check if status column exists
            $check_status = $conn->query("SHOW COLUMNS FROM checkins LIKE 'status'");
            if ($check_status->num_rows == 0) {
                // Add status column if it doesn't exist
                $conn->query("ALTER TABLE checkins ADD COLUMN status ENUM('checked_in', 'checked_out') DEFAULT 'checked_in'");
            }
            
            $query = "UPDATE bookings SET status = 'checked_out', actual_checkout_time = NOW() WHERE id = $booking_id";
            if ($conn->query($query)) {
                // Update checkin record if it exists
                $checkout_query = "UPDATE checkins SET actual_checkout = NOW(), status = 'checked_out' WHERE booking_id = $booking_id";
                $conn->query($checkout_query);
                
                // Update room status to available
                $room_query = "UPDATE rooms r 
                              JOIN bookings b ON r.id = b.room_id 
                              SET r.status = 'active' 
                              WHERE b.id = $booking_id";
                $conn->query($room_query);
                
                set_message('success', 'Customer checked out successfully');
            } else {
                set_message('error', 'Failed to check out customer');
            }
            break;
            
        case 'delete':
            // Archive/Delete checked-out bookings
            // Only allow deletion of checked_out or cancelled bookings
            $check_query = "SELECT status FROM bookings WHERE id = $booking_id";
            $check_result = $conn->query($check_query);
            
            if ($check_result && $check_result->num_rows > 0) {
                $booking_status = $check_result->fetch_assoc()['status'];
                
                if (in_array($booking_status, ['checked_out', 'cancelled'])) {
                    // Instead of deleting, we'll mark it as archived
                    // First, check if archived column exists
                    $check_archived = $conn->query("SHOW COLUMNS FROM bookings LIKE 'archived'");
                    if ($check_archived->num_rows == 0) {
                        // Add archived column if it doesn't exist
                        $conn->query("ALTER TABLE bookings ADD COLUMN archived TINYINT(1) DEFAULT 0");
                    }
                    
                    // Mark as archived
                    $archive_query = "UPDATE bookings SET archived = 1 WHERE id = $booking_id";
                    if ($conn->query($archive_query)) {
                        set_message('success', 'Booking archived successfully');
                    } else {
                        set_message('error', 'Failed to archive booking');
                    }
                } else {
                    set_message('error', 'Only checked-out or cancelled bookings can be archived');
                }
            } else {
                set_message('error', 'Booking not found');
            }
            break;
    }
    header('Location: manager-bookings.php');
    exit();
}

// Get bookings with filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Check if archived column exists
$check_archived_col = $conn->query("SHOW COLUMNS FROM bookings LIKE 'archived'");
$has_archived_column = ($check_archived_col && $check_archived_col->num_rows > 0);

$where_conditions = [];
// Only show room bookings, not food orders
$where_conditions[] = "b.booking_type = 'room'";

// Exclude archived bookings (only if column exists)
if ($has_archived_column) {
    $where_conditions[] = "(b.archived IS NULL OR b.archived = 0)";
}

// By default, exclude checked_out bookings unless specifically filtered
if ($status_filter) {
    $where_conditions[] = "b.status = '" . sanitize_input($status_filter) . "'";
} else {
    // Show only active bookings (pending, confirmed, checked_in) by default
    $where_conditions[] = "b.status IN ('pending', 'confirmed', 'checked_in')";
}

if ($search) {
    $search_term = sanitize_input($search);
    $where_conditions[] = "(b.booking_reference LIKE '%$search_term%' OR CONCAT(u.first_name, ' ', u.last_name) LIKE '%$search_term%' OR u.email LIKE '%$search_term%')";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$bookings_query = "SELECT b.*, 
                   COALESCE(r.name, 'Food Order') as room_name, 
                   COALESCE(r.room_number, 'N/A') as room_number, 
                   CONCAT(u.first_name, ' ', u.last_name) as guest_name, 
                   u.email, u.phone,
                   b.screenshot_path,
                   b.screenshot_uploaded_at,
                   b.payment_method,
                   b.verification_status
                   FROM bookings b 
                   LEFT JOIN rooms r ON b.room_id = r.id 
                   JOIN users u ON b.user_id = u.id 
                   $where_clause
                   ORDER BY b.created_at DESC";

$bookings = $conn->query($bookings_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Manager Dashboard</title>
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
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .badge {
            font-size: 0.75em;
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
            <a href="manager-bookings.php" class="nav-link active">
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
            <div class="col-12">
                <div class="main-content-wrapper" id="mainContent">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manager.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-calendar-check me-2"></i> Manage Bookings</h2>
                        </div>
                    </div>
                    
                    <?php display_message(); ?>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="checked_in" <?php echo $status_filter == 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                        <option value="checked_out" <?php echo $status_filter == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search by booking ref, guest name, or email" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary d-block w-100">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Bookings Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Booking Ref</th>
                                            <th>Customer</th>
                                            <th>Room</th>
                                            <th>Dates</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($bookings && $bookings->num_rows > 0): ?>
                                            <?php while ($booking = $bookings->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong>
                                                        <br><small class="text-muted"><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($booking['email'] ?? 'N/A'); ?></small>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($booking['phone'] ?? 'N/A'); ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($booking['room_name']); ?></strong>
                                                        <br><small class="text-muted">Room <?php echo $booking['room_number']; ?></small>
                                                    </td>
                                                    <td>
                                                        <strong>In:</strong> <?php echo date('M j', strtotime($booking['check_in_date'])); ?>
                                                        <br><strong>Out:</strong> <?php echo date('M j', strtotime($booking['check_out_date'])); ?>
                                                        <br><small class="text-muted"><?php echo isset($booking['customers']) ? $booking['customers'] : '1'; ?> customers</small>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo formatCurrency($booking['total_price']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        // Show "Verified" if Chapa payment confirmed, otherwise show booking status
                                                        if ($booking['payment_status'] === 'paid' && $booking['verification_status'] === 'verified'):
                                                        ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check-circle me-1"></i> Verified
                                                            </span>
                                                        <?php else:
                                                            $status_badges = [
                                                                'pending'    => 'warning',
                                                                'confirmed'  => 'success',
                                                                'checked_in' => 'primary',
                                                                'checked_out'=> 'info',
                                                                'cancelled'  => 'danger'
                                                            ];
                                                            $badge_class = $status_badges[$booking['status']] ?? 'secondary';
                                                        ?>
                                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (in_array($booking['status'], ['cancelled', 'checked_out'])): ?>
                                                            <button class="btn btn-danger btn-sm"
                                                                onclick="deleteBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference']); ?>')">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-danger btn-sm"
                                                                onclick="cancelBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference']); ?>')">
                                                                <i class="fas fa-times"></i> Cancel
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No bookings found</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Screenshot Modal -->
    <div class="modal fade" id="screenshotModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Screenshot - <span id="screenshot_booking_ref"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="screenshot_image" src="" alt="Payment Screenshot" class="img-fluid rounded border">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="screenshot_download" href="" download class="btn btn-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmBooking(bookingId) {
            if (confirm('Confirm this booking?')) {
                submitAction('confirm', bookingId);
            }
        }
        
        function cancelBooking(bookingId, bookingRef) {
            const reason = prompt(`Cancel booking ${bookingRef}?\n\nPlease enter cancellation reason:`);
            if (reason !== null && reason.trim() !== '') {
                submitAction('cancel', bookingId, reason);
            }
        }
        
        function checkinGuest(bookingId) {
            if (confirm('Check in this customer?')) {
                submitAction('checkin', bookingId);
            }
        }
        
        function checkoutGuest(bookingId) {
            if (confirm('Check out this customer?')) {
                submitAction('checkout', bookingId);
            }
        }
        
        function deleteBooking(bookingId, bookingRef) {
            if (confirm(`Archive booking ${bookingRef}?\n\nThis will permanently remove it from the active bookings list. This action cannot be undone.`)) {
                submitAction('delete', bookingId);
            }
        }
        
        function submitAction(action, bookingId, reason = '') {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${action}">
                <input type="hidden" name="booking_id" value="${bookingId}">
                <input type="hidden" name="cancel_reason" value="${reason}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function showScreenshotModal(screenshotPath, bookingRef) {
            document.getElementById('screenshot_image').src = '../' + screenshotPath;
            document.getElementById('screenshot_booking_ref').textContent = bookingRef;
            document.getElementById('screenshot_download').href = '../' + screenshotPath;
            new bootstrap.Modal(document.getElementById('screenshotModal')).show();
        }
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