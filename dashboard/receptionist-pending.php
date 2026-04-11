<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('receptionist', '../login.php');

$message = '';
$error = '';

// Handle booking actions
if ($_POST && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    
    if ($_POST['action'] == 'confirm') {
        // Get booking type first to determine how to handle it
        $type_query = "SELECT booking_type FROM bookings WHERE id = ? AND status = 'pending'";
        $type_stmt = $conn->prepare($type_query);
        $type_stmt->bind_param("i", $booking_id);
        $type_stmt->execute();
        $type_result = $type_stmt->get_result();
        
        if ($type_result->num_rows == 0) {
            $error = 'Booking not found or already processed';
        } else {
            $booking_type_data = $type_result->fetch_assoc();
            $booking_type = $booking_type_data['booking_type'];
            
            // For food orders and services, just mark as confirmed (not checked_in)
            if ($booking_type == 'food_order' || $booking_type == 'spa_service' || $booking_type == 'laundry_service') {
                $query = "UPDATE bookings SET status = 'confirmed', verified_at = NOW(), verified_by = ? WHERE id = ? AND status = 'pending'";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $_SESSION['user_id'], $booking_id);
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Log the activity
                    $booking_query = "SELECT user_id, booking_reference FROM bookings WHERE id = ?";
                    $log_stmt = $conn->prepare($booking_query);
                    $log_stmt->bind_param("i", $booking_id);
                    $log_stmt->execute();
                    $booking_result = $log_stmt->get_result();
                    
                    if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                        log_booking_activity($booking_id, $booking['user_id'], 'confirmed', 'pending', 'confirmed', 'Service confirmed by receptionist', $_SESSION['user_id']);
                    }
                    
                    // Different success messages for different service types
                    switch ($booking_type) {
                        case 'food_order':
                            $message = 'Food order confirmed successfully! Kitchen has been notified.';
                            break;
                        case 'spa_service':
                            $message = 'Spa & Wellness service confirmed successfully! Customer will be notified.';
                            break;
                        case 'laundry_service':
                            $message = 'Laundry service confirmed successfully! Service team has been notified.';
                            break;
                        default:
                            $message = 'Service confirmed successfully!';
                    }
                } else {
                    // Different error messages for different service types
                    switch ($booking_type) {
                        case 'food_order':
                            $error = 'Failed to confirm food order or order already processed';
                            break;
                        case 'spa_service':
                            $error = 'Failed to confirm spa service or service already processed';
                            break;
                        case 'laundry_service':
                            $error = 'Failed to confirm laundry service or service already processed';
                            break;
                        default:
                            $error = 'Failed to confirm service or service already processed';
                    }
                }
            } else {
                // For room bookings: pending → checked_in
                $query = "UPDATE bookings SET status = 'checked_in', actual_checkin_time = NOW(), checked_in_by = ? WHERE id = ? AND status = 'pending'";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $_SESSION['user_id'], $booking_id);
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Log the activity
                    $booking_query = "SELECT user_id, booking_reference FROM bookings WHERE id = ?";
                    $log_stmt = $conn->prepare($booking_query);
                    $log_stmt->bind_param("i", $booking_id);
                    $log_stmt->execute();
                    $booking_result = $log_stmt->get_result();
                    
                    if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                        log_booking_activity($booking_id, $booking['user_id'], 'checked_in', 'pending', 'checked_in', 'Booking approved and checked in by receptionist', $_SESSION['user_id']);
                    }
                    $message = 'Room booking approved and customer checked in successfully! Room is now occupied.';
                } else {
                    $error = 'Failed to approve room booking or booking already processed';
                }
            }
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
           sb.quantity as service_quantity,
           b.screenshot_path,
           b.screenshot_uploaded_at,
           b.payment_method,
           b.verification_status
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
                                        
                                        <!-- Payment Screenshot Section -->
                                        <?php if ($booking['screenshot_path']): ?>
                                        <div class="payment-screenshot mb-3">
                                            <h6 class="text-primary mb-2">
                                                <i class="fas fa-image me-1"></i> Payment Screenshot
                                                <?php if ($booking['payment_method']): ?>
                                                    <small class="text-muted">(<?php echo ucfirst($booking['payment_method']); ?>)</small>
                                                <?php endif; ?>
                                            </h6>
                                            <div class="screenshot-container" style="max-width: 200px;">
                                                <img src="../<?php echo htmlspecialchars($booking['screenshot_path']); ?>" 
                                                     alt="Payment Screenshot" 
                                                     class="img-fluid rounded border"
                                                     style="cursor: pointer; max-height: 150px; object-fit: cover;"
                                                     onclick="showScreenshotModal('<?php echo htmlspecialchars($booking['screenshot_path']); ?>', '<?php echo htmlspecialchars($booking['booking_reference']); ?>')">
                                            </div>
                                            <?php if ($booking['screenshot_uploaded_at']): ?>
                                                <small class="text-muted d-block mt-1">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Uploaded: <?php echo date('M j, Y g:i A', strtotime($booking['screenshot_uploaded_at'])); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($booking['verification_status']): ?>
                                                <span class="badge bg-<?php 
                                                    echo $booking['verification_status'] == 'verified' ? 'success' : 
                                                        ($booking['verification_status'] == 'pending_verification' ? 'warning' : 
                                                        ($booking['verification_status'] == 'rejected' ? 'danger' : 'secondary')); 
                                                ?> mt-1">
                                                    <?php echo ucfirst(str_replace('_', ' ', $booking['verification_status'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php elseif ($booking['verification_status'] == 'pending_payment'): ?>
                                        <div class="payment-status mb-3">
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i> Awaiting Payment Screenshot
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="d-flex gap-1">
                                            <?php if ($booking_type == 'room'): ?>
                                                <!-- For room bookings, redirect to detailed check-in form -->
                                                <a href="receptionist-checkin.php?booking_ref=<?php echo urlencode($booking['booking_reference']); ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-key"></i> Check-in
                                                </a>
                                            <?php else: ?>
                                                <!-- For other services, confirm directly -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            onclick="return confirmService('<?php echo $booking_type == 'food_order' ? 'food order' : ($booking_type == 'spa_service' ? 'spa service' : 'laundry service'); ?>')">
                                                        <i class="fas fa-check"></i> Confirm
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
    
    <!-- Booking Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title mb-0"><i class="fas fa-info-circle me-2"></i>Booking Details - <span id="details_booking_ref"></span></h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <!-- Customer & Booking Info in compact rows -->
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <div class="bg-light p-2 rounded">
                                <div class="row g-1">
                                    <div class="col-6"><small class="text-muted">Customer:</small><br><strong id="details_customer_name"></strong></div>
                                    <div class="col-6"><small class="text-muted">Email:</small><br><span id="details_email" class="small"></span></div>
                                    <div class="col-6"><small class="text-muted">Phone:</small><br><span id="details_phone"></span></div>
                                    <div class="col-6"><small class="text-muted">Type:</small><br><span id="details_booking_type"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Details -->
                    <div class="row g-2 mb-3">
                        <div class="col-6"><small class="text-muted">Total Amount:</small><br><span id="details_total_price" class="text-success fw-bold"></span></div>
                        <div class="col-6"><small class="text-muted">Status:</small><br><span id="details_status"></span></div>
                        <div class="col-6"><small class="text-muted">Created:</small><br><span id="details_created_at" class="small"></span></div>
                        <div class="col-6"><small class="text-muted">Payment:</small><br><span id="details_payment_status"></span></div>
                    </div>
                    
                    <!-- Service-specific details -->
                    <div id="service_details" class="mb-3"></div>
                    
                    <!-- Special Requests -->
                    <div class="mb-3">
                        <small class="text-muted">Special Requests:</small>
                        <div id="special_requests" class="bg-light p-2 rounded small"></div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="border-top pt-3">
                        <small class="text-muted"><i class="fas fa-credit-card me-1"></i>Payment Information:</small>
                        <div id="payment_info" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
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
            console.log('Fetching booking details for ID:', bookingId);
            
            // Show loading state
            const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
            document.getElementById('details_booking_ref').textContent = 'Loading...';
            detailsModal.show();
            
            // Fetch booking data from API
            fetch(`../api/get_booking_details.php?id=${bookingId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API Response:', data);
                    if (data.success) {
                        populateDetailsModal(data.booking);
                    } else {
                        alert('Error loading booking details: ' + data.message);
                        detailsModal.hide();
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Error loading booking details: ' + error.message);
                    detailsModal.hide();
                });
        }
        
        function populateDetailsModal(booking) {
            console.log('Populating modal with booking data:', booking);
            
            // Basic information
            document.getElementById('details_booking_ref').textContent = booking.booking_reference || 'N/A';
            document.getElementById('details_customer_name').textContent = (booking.first_name || '') + ' ' + (booking.last_name || '');
            document.getElementById('details_email').textContent = booking.email || 'N/A';
            document.getElementById('details_phone').textContent = booking.phone || 'N/A';
            document.getElementById('details_booking_type').textContent = booking.booking_type_display || 'N/A';
            document.getElementById('details_total_price').textContent = booking.total_price_formatted || 'N/A';
            document.getElementById('details_created_at').textContent = booking.created_at_formatted || 'N/A';
            document.getElementById('details_status').textContent = booking.status || 'N/A';
            document.getElementById('details_payment_status').textContent = booking.payment_status || 'N/A';
            
            // Service-specific details - compact format
            const serviceDetails = document.getElementById('service_details');
            if (booking.booking_type === 'room') {
                serviceDetails.innerHTML = `
                    <div class="bg-light p-2 rounded">
                        <small class="text-muted">Room Details:</small>
                        <div class="row g-1 mt-1">
                            <div class="col-6"><strong>Room:</strong> ${booking.room_name || 'N/A'} (${booking.room_number || 'N/A'})</div>
                            <div class="col-6"><strong>Guests:</strong> ${booking.customers || 'N/A'}</div>
                            <div class="col-6"><strong>Check-in:</strong> ${booking.check_in_date || 'N/A'}</div>
                            <div class="col-6"><strong>Check-out:</strong> ${booking.check_out_date || 'N/A'}</div>
                        </div>
                    </div>
                `;
            } else if (booking.booking_type === 'food_order') {
                serviceDetails.innerHTML = `
                    <div class="bg-light p-2 rounded">
                        <small class="text-muted">Food Order Details:</small>
                        <div class="row g-1 mt-1">
                            <div class="col-6"><strong>Table:</strong> ${booking.table_reservation ? 'Yes' : 'Takeaway'}</div>
                            <div class="col-6"><strong>Customers:</strong> ${booking.guests || 1}</div>
                            ${booking.reservation_date ? `
                            <div class="col-6"><strong>Date:</strong> ${booking.reservation_date}</div>
                            <div class="col-6"><strong>Time:</strong> ${booking.reservation_time || 'N/A'}</div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else if (booking.booking_type === 'spa_service' || booking.booking_type === 'laundry_service') {
                const serviceType = booking.booking_type === 'spa_service' ? 'Spa Service' : 'Laundry Service';
                serviceDetails.innerHTML = `
                    <div class="bg-light p-2 rounded">
                        <small class="text-muted">${serviceType} Details:</small>
                        <div class="row g-1 mt-1">
                            <div class="col-6"><strong>Service:</strong> ${booking.service_name || 'N/A'}</div>
                            <div class="col-6"><strong>Quantity:</strong> ${booking.service_quantity || 1}</div>
                            ${booking.service_date ? `
                            <div class="col-6"><strong>Date:</strong> ${booking.service_date}</div>
                            <div class="col-6"><strong>Time:</strong> ${booking.service_time || 'N/A'}</div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else {
                serviceDetails.innerHTML = '<small class="text-muted">No additional service details available.</small>';
            }
            
            // Special requests - compact
            const specialRequests = document.getElementById('special_requests');
            specialRequests.textContent = booking.special_requests || 'None';
            
            // Payment information - compact
            const paymentInfo = document.getElementById('payment_info');
            if (booking.screenshot_path) {
                paymentInfo.innerHTML = `
                    <div class="row g-2">
                        <div class="col-6">
                            <small><strong>Method:</strong> ${booking.payment_method_display || 'N/A'}</small>
                        </div>
                        <div class="col-6">
                            <small><strong>Status:</strong></small>
                            <span class="badge bg-${booking.verification_status_color || 'secondary'} ms-1">${booking.verification_status_display || 'Unknown'}</span>
                        </div>
                        <div class="col-12">
                            <small><strong>Screenshot:</strong></small><br>
                            <img src="../${booking.screenshot_path}" alt="Payment Screenshot" 
                                 class="img-fluid rounded border mt-1" style="max-width: 120px; cursor: pointer;"
                                 onclick="showScreenshotModal('${booking.screenshot_path}', '${booking.booking_reference}')">
                            <br><small class="text-muted">Uploaded: ${booking.screenshot_uploaded_at_formatted || 'N/A'}</small>
                        </div>
                    </div>
                `;
            } else {
                paymentInfo.innerHTML = `
                    <div class="alert alert-warning py-2 mb-0">
                        <small><i class="fas fa-exclamation-triangle"></i> No payment screenshot uploaded yet</small>
                    </div>
                `;
            }
        }
        
        function showScreenshotModal(screenshotPath, bookingRef) {
            document.getElementById('screenshot_image').src = '../' + screenshotPath;
            document.getElementById('screenshot_booking_ref').textContent = bookingRef;
            document.getElementById('screenshot_download').href = '../' + screenshotPath;
            new bootstrap.Modal(document.getElementById('screenshotModal')).show();
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuToggle = document.getElementById('menuToggle');
            
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('shifted');
            menuToggle.classList.toggle('shifted');
        }
        
        function confirmService(serviceType) {
            return confirm('Confirm this ' + serviceType + '?\n\nThe service provider will be notified and the booking will be processed.');
        }
    </script>
</body>
</html>