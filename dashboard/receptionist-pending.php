<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('receptionist');

$message = '';
$error = '';

// Handle booking actions
if ($_POST && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    
    if ($_POST['action'] == 'confirm') {
        // UNIFIED APPROVAL: pending → checked_in (not just confirmed)
        $query = "UPDATE bookings SET status = 'checked_in', actual_checkin_time = NOW(), checked_in_by = ? WHERE id = ? AND status = 'pending'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $_SESSION['user_id'], $booking_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Log the activity
            $booking_query = "SELECT user_id, booking_reference FROM bookings WHERE id = $booking_id";
            $booking_result = $conn->query($booking_query);
            if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                log_booking_activity($booking_id, $booking['user_id'], 'checked_in', 'pending', 'checked_in', 'Booking approved and checked in by receptionist', $_SESSION['user_id']);
            }
            $message = 'Booking approved and guest checked in successfully!';
        } else {
            $error = 'Failed to approve booking or booking already processed';
        }
    } elseif ($_POST['action'] == 'cancel') {
        $cancel_reason = sanitize_input($_POST['cancel_reason']);
        $query = "UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status = 'pending'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $booking_id);
        
        if ($stmt->execute()) {
            // Log the activity
            $booking_query = "SELECT user_id, booking_reference FROM bookings WHERE id = $booking_id";
            $booking_result = $conn->query($booking_query);
            if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                log_booking_activity($booking_id, $booking['user_id'], 'cancelled', 'pending', 'cancelled', "Booking cancelled by receptionist: $cancel_reason", $_SESSION['user_id']);
            }
            $message = 'Booking cancelled successfully!';
        } else {
            $error = 'Failed to cancel booking: ' . $stmt->error;
        }
    }
}

// Get pending bookings (all types: room, food, spa, laundry)
$pending_bookings = $conn->query("
    SELECT b.*, 
           COALESCE(r.name, 'N/A') as room_name, 
           COALESCE(r.room_number, 'N/A') as room_number, 
           u.first_name, u.last_name, u.email, u.phone,
           fo.order_reference as food_order_ref,
           fo.table_reservation,
           fo.reservation_date as food_date,
           fo.reservation_time as food_time,
           sb.service_name,
           sb.service_category,
           sb.service_date,
           sb.service_time,
           sb.quantity as service_quantity
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_id = r.id 
    LEFT JOIN users u ON b.user_id = u.id 
    LEFT JOIN food_orders fo ON b.id = fo.booking_id
    LEFT JOIN service_bookings sb ON b.id = sb.booking_id
    WHERE b.status = 'pending' 
    ORDER BY b.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Bookings - Receptionist Dashboard</title>
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
            position: fixed;
            left: -280px;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            transition: left 0.3s ease;
            z-index: 1050;
            overflow-y: auto;
            padding-top: 70px;
        }
        .sidebar.show {
            left: 0;
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
        .main-content-wrapper {
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        .main-content-wrapper.shifted {
            margin-left: 280px;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .booking-card {
            border-left: 4px solid #17a2b8;
            margin-bottom: 1rem;
        }
        .booking-card.urgent {
            border-left-color: #dc3545;
        }
        .booking-card.today {
            border-left-color: #ffc107;
        }
        .menu-toggle {
            position: fixed;
            top: 70px;
            left: 10px;
            z-index: 1060;
            background: #007bff;
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
            background: #0056b3;
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

    <!-- Hamburger Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h4 class="text-white">
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
            <a href="verify-payments.php" class="nav-link">
                <i class="fas fa-check-circle me-2"></i> Verify Payments
            </a>
            <a href="receptionist-pending.php" class="nav-link active">
                <i class="fas fa-calendar-check me-2"></i> Pending Bookings
            </a>
            <a href="receptionist-rooms.php" class="nav-link">
                <i class="fas fa-bed me-2"></i> Manage Rooms
            </a>
            <a href="receptionist-services.php" class="nav-link">
                <i class="fas fa-utensils me-2"></i> Manage Foods & Services
            </a>
            <a href="../generate_bill.php" class="nav-link" target="_blank">
                <i class="fas fa-file-invoice-dollar me-2"></i> Generate Bill
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
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="receptionist.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-calendar-check me-2"></i> Pending Bookings</h2>
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
                    
                    <?php if ($pending_bookings->num_rows > 0): ?>
                        <div class="row">
                            <?php while ($booking = $pending_bookings->fetch_assoc()): 
                                // Determine booking type and set appropriate variables
                                $booking_type = $booking['booking_type'];
                                $display_room = '';
                                $display_nights = '';
                                $check_in_date = null;
                                
                                // Set display values based on booking type
                                if ($booking_type == 'room') {
                                    $display_room = htmlspecialchars($booking['room_name'] . ' (' . $booking['room_number'] . ')');
                                    if (!empty($booking['check_in_date']) && !empty($booking['check_out_date'])) {
                                        $check_in = new DateTime($booking['check_in_date']);
                                        $check_out = new DateTime($booking['check_out_date']);
                                        $nights = $check_in->diff($check_out)->days;
                                        $display_nights = $nights . ' night' . ($nights != 1 ? 's' : '');
                                        $check_in_date = $check_in;
                                    }
                                } elseif ($booking_type == 'food_order') {
                                    $display_room = '<span class="badge bg-success">Food Order</span>';
                                    if (!empty($booking['food_time'])) {
                                        $display_nights = 'Order Time: ' . date('h:i A', strtotime($booking['food_time']));
                                    } else {
                                        $display_nights = 'Takeaway';
                                    }
                                    if (!empty($booking['food_date'])) {
                                        $check_in_date = new DateTime($booking['food_date']);
                                    }
                                } elseif ($booking_type == 'spa_service') {
                                    $display_room = '<span class="badge bg-info">Spa Service</span>';
                                    if (!empty($booking['service_name'])) {
                                        $display_room .= '<br><small>' . htmlspecialchars($booking['service_name']) . '</small>';
                                    }
                                    if (!empty($booking['service_time'])) {
                                        $display_nights = 'Recreation Time: ' . date('h:i A', strtotime($booking['service_time']));
                                    }
                                    if (!empty($booking['service_date'])) {
                                        $check_in_date = new DateTime($booking['service_date']);
                                    }
                                } elseif ($booking_type == 'laundry_service') {
                                    $display_room = '<span class="badge bg-warning text-dark">Laundry Service</span>';
                                    if (!empty($booking['service_name'])) {
                                        $display_room .= '<br><small>' . htmlspecialchars($booking['service_name']) . '</small>';
                                    }
                                    if (!empty($booking['service_quantity'])) {
                                        $display_nights = 'Service Day: ' . $booking['service_quantity'] . ' item' . ($booking['service_quantity'] != 1 ? 's' : '');
                                    }
                                    if (!empty($booking['service_date'])) {
                                        $check_in_date = new DateTime($booking['service_date']);
                                    }
                                }
                                
                                // Calculate urgency
                                $today = new DateTime();
                                $is_today = false;
                                $is_overdue = false;
                                $days_until = 0;
                                
                                if ($check_in_date) {
                                    $days_until = $today->diff($check_in_date)->days;
                                    $is_today = $check_in_date->format('Y-m-d') == $today->format('Y-m-d');
                                    $is_overdue = $check_in_date < $today;
                                }
                                
                                $card_class = 'booking-card';
                                if ($is_overdue) $card_class .= ' urgent';
                                elseif ($is_today) $card_class .= ' today';
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card <?php echo $card_class; ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($booking['booking_reference']); ?></h6>
                                        <?php if ($is_overdue): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php elseif ($is_today): ?>
                                            <span class="badge bg-warning">Today</span>
                                        <?php elseif ($check_in_date): ?>
                                            <span class="badge bg-info"><?php echo $days_until; ?> days</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <i class="fas fa-<?php echo $booking_type == 'room' ? 'bed' : ($booking_type == 'food_order' ? 'utensils' : ($booking_type == 'spa_service' ? 'spa' : 'tshirt')); ?> me-1"></i> 
                                                <?php echo $display_room; ?><br>
                                                <?php if ($check_in_date): ?>
                                                <i class="fas fa-calendar me-1"></i> <?php echo $check_in_date->format('M j, Y'); ?><br>
                                                <?php endif; ?>
                                                <?php if ($display_nights): ?>
                                                <i class="fas fa-clock me-1"></i> <?php echo $display_nights; ?><br>
                                                <?php endif; ?>
                                                <?php if ($booking_type == 'room'): ?>
                                                <i class="fas fa-users me-1"></i> <?php echo $booking['customers']; ?> guest<?php echo $booking['customers'] != 1 ? 's' : ''; ?><br>
                                                <?php endif; ?>
                                                <i class="fas fa-money-bill me-1"></i> <?php echo format_currency($booking['total_price']); ?>
                                            </small>
                                        </p>
                                        <div class="d-flex gap-1">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="confirm">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm" 
                                                        onclick="return confirm('Confirm this booking?')">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                            </form>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="showCancelModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference']); ?>')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="showBookingDetails(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-eye"></i> Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Pending Bookings</h5>
                                <p class="text-muted">All bookings have been processed or there are no new reservations.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" id="cancel_booking_id">
                        
                        <p>Are you sure you want to cancel booking <strong id="cancel_booking_ref"></strong>?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Cancellation Reason</label>
                            <select name="cancel_reason" class="form-select" required>
                                <option value="">Select reason</option>
                                <option value="Guest request">Guest request</option>
                                <option value="Payment not received">Payment not received</option>
                                <option value="Room unavailable">Room unavailable</option>
                                <option value="Overbooking">Overbooking</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Cancel Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showCancelModal(bookingId, bookingRef) {
            document.getElementById('cancel_booking_id').value = bookingId;
            document.getElementById('cancel_booking_ref').textContent = bookingRef;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }
        
        function showBookingDetails(bookingId) {
            // This could be expanded to show a detailed modal
            alert('Booking details feature can be expanded here');
        }
        
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