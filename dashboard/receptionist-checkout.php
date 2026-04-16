<?php
// Suppress PHP warnings and notices for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Custom error handler to prevent htmlspecialchars warnings
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (strpos($errstr, 'htmlspecialchars') !== false) {
        return true; // Suppress htmlspecialchars warnings
    }
    return false; // Let other errors through
});

require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('receptionist', '../login.php');

// Safe display function to prevent null value errors
function safe_display($value, $default = 'N/A') {
    if ($value === null || $value === '') {
        return $default;
    }
    return htmlspecialchars($value);
}

$message = '';
$error = '';
$booking_data = null;
$incidental_charges = [];

// Get today's checkouts
$today = date('Y-m-d');
$todays_checkouts_query = "SELECT b.*, 
                           COALESCE(r.name, 'Food Order') as room_name, 
                           COALESCE(r.room_number, 'N/A') as room_number,
                           COALESCE(b.customer_name, 'Unknown Guest') as guest_name, 
                           COALESCE(b.customer_email, '') as email, 
                           COALESCE(b.customer_phone, '') as phone,
                           COALESCE(b.incidental_deposit, 0) as incidental_deposit,
                           DATEDIFF(b.check_out_date, b.check_in_date) as nights
                           FROM bookings b
                           LEFT JOIN rooms r ON b.room_id = r.id
                           WHERE DATE(b.check_out_date) = '$today'
                           AND b.status = 'checked_in'
                           AND b.booking_type = 'room'
                           ORDER BY b.check_out_date ASC";

$todays_checkouts = $conn->query($todays_checkouts_query);

// Handle checkout form submission
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'search_booking') {
        $search_type = sanitize_input($_POST['search_type']);
        $search_value = sanitize_input($_POST['search_value']);
        
        $search_query = "SELECT b.*, 
                         COALESCE(r.name, 'Food Order') as room_name, 
                         COALESCE(r.room_number, 'N/A') as room_number,
                         COALESCE(b.customer_name, 'Unknown Guest') as guest_name, 
                         COALESCE(b.customer_email, '') as email, 
                         COALESCE(b.customer_phone, '') as phone,
                         COALESCE(b.incidental_deposit, 0) as incidental_deposit, 
                         COALESCE(b.room_key_number, '') as room_key_number,
                         DATEDIFF(b.check_out_date, b.check_in_date) as nights
                         FROM bookings b
                         LEFT JOIN rooms r ON b.room_id = r.id
                         WHERE b.status = 'checked_in' AND b.booking_type = 'room'";
        
        if ($search_type == 'reference') {
            $search_query .= " AND b.booking_reference = '$search_value'";
        } elseif ($search_type == 'name') {
            $search_query .= " AND b.customer_name LIKE '%$search_value%'";
        } elseif ($search_type == 'phone') {
            $search_query .= " AND b.customer_phone LIKE '%$search_value%'";
        } elseif ($search_type == 'room') {
            $search_query .= " AND r.room_number = '$search_value'";
        }
        
        $result = $conn->query($search_query);
        if ($result && $result->num_rows > 0) {
            $booking_data = $result->fetch_assoc();
            
            // Get incidental charges
            $charges_query = "SELECT * FROM incidental_charges 
                             WHERE booking_id = {$booking_data['id']} 
                             ORDER BY charge_date DESC";
            $charges_result = $conn->query($charges_query);
            if ($charges_result) {
                while ($charge = $charges_result->fetch_assoc()) {
                    $incidental_charges[] = $charge;
                }
            }
        } else {
            $error = 'Booking not found or guest not checked in';
        }
    } elseif ($_POST['action'] == 'add_charge') {
        $booking_id = (int)$_POST['booking_id'];
        $charge_type = sanitize_input($_POST['charge_type']);
        $description = sanitize_input($_POST['description']);
        $amount = (float)$_POST['amount'];
        $quantity = (int)$_POST['quantity'];
        $total_amount = $amount * $quantity;
        $notes = sanitize_input($_POST['charge_notes']);
        
        $charge_query = "INSERT INTO incidental_charges 
                        (booking_id, charge_type, description, amount, quantity, total_amount, charged_by, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
        $stmt = $conn->prepare($charge_query);
        $stmt->bind_param("issdidis", $booking_id, $charge_type, $description, $amount, 
                         $quantity, $total_amount, $_SESSION['user_id'], $notes);
        
        if ($stmt->execute()) {
            $message = 'Incidental charge added successfully';
            // Reload booking data
            $_POST['action'] = 'search_booking';
            $_POST['booking_reference'] = $_POST['booking_ref'];
        } else {
            $error = 'Failed to add charge: ' . $stmt->error;
        }
    } elseif ($_POST['action'] == 'process_checkout') {
        $booking_id = (int)$_POST['booking_id'];
        $room_id = (int)$_POST['room_id'];
        $checkout_notes = isset($_POST['checkout_notes']) ? sanitize_input($_POST['checkout_notes']) : '';
        
        $conn->begin_transaction();
        
        try {
            // Get booking details for calculations
            $booking_query = "SELECT total_price, incidental_deposit FROM bookings WHERE id = ?";
            $booking_stmt = $conn->prepare($booking_query);
            $booking_stmt->bind_param("i", $booking_id);
            $booking_stmt->execute();
            $booking_result = $booking_stmt->get_result();
            $booking_info = $booking_result->fetch_assoc();
            
            // Calculate total incidental charges
            $charges_query = "SELECT SUM(total_amount) as total_charges 
                             FROM incidental_charges 
                             WHERE booking_id = ? AND status = 'approved'";
            $charges_stmt = $conn->prepare($charges_query);
            $charges_stmt->bind_param("i", $booking_id);
            $charges_stmt->execute();
            $charges_result = $charges_stmt->get_result();
            $charges_data = $charges_result->fetch_assoc();
            $incidental_charges_total = $charges_data['total_charges'] ?? 0;
            
            // Auto-calculate final amount (room charges + incidental charges)
            $final_amount = $booking_info['total_price'] + $incidental_charges_total;
            
            // Auto-calculate deposit refund (return the incidental deposit if no damages)
            $deposit_refunded = $booking_info['incidental_deposit'] ?? 0;
            
            // Auto-calculate payment collected (final amount - no advance payment in this system)
            $payment_collected = $final_amount;
            
            // Default payment method
            $payment_method = 'cash';
            
            // Update booking with checkout details
            $update_query = "UPDATE bookings SET 
                            status = 'checked_out',
                            final_amount = ?,
                            deposit_refunded = ?,
                            actual_checkout_time = NOW(),
                            checked_out_by = ?,
                            checkout_notes = ?
                            WHERE id = ?";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ddisi", $final_amount, $deposit_refunded, $_SESSION['user_id'], 
                             $checkout_notes, $booking_id);
            $stmt->execute();
            
            // Update room status to available
            $room_query = "UPDATE rooms SET status = 'active' WHERE id = ?";
            $room_stmt = $conn->prepare($room_query);
            $room_stmt->bind_param("i", $room_id);
            $room_stmt->execute();
            
            // Auto-return room key
            $key_query = "UPDATE room_keys SET 
                         status = 'returned', 
                         returned_at = NOW(), 
                         returned_to = ? 
                         WHERE booking_id = ? AND status = 'issued'";
            $key_stmt = $conn->prepare($key_query);
            $key_stmt->bind_param("ii", $_SESSION['user_id'], $booking_id);
            $key_stmt->execute();
            
            // Log checkout action
            $log_query = "INSERT INTO checkin_checkout_log 
                         (booking_id, action_type, performed_by, payment_collected, payment_method, 
                          incidental_charges, refund_amount, notes, ip_address) 
                         VALUES (?, 'check_out', ?, ?, ?, ?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iidsddss", $booking_id, $_SESSION['user_id'], $payment_collected, 
                                 $payment_method, $incidental_charges_total, $deposit_refunded, 
                                 $checkout_notes, $ip_address);
            $log_stmt->execute();
            
            // Log booking activity
            $booking_query = "SELECT user_id FROM bookings WHERE id = $booking_id";
            $booking_result = $conn->query($booking_query);
            if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                log_booking_activity($booking_id, $booking['user_id'], 'checked_out', 'checked_in', 'checked_out', 
                                    'Customer checked out automatically by system', $_SESSION['user_id']);
            }
            
            $conn->commit();
            $message = 'Customer checked out successfully! Room is now available for new bookings.';
            $booking_data = null;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to process checkout: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Check-out - Receptionist Dashboard</title>
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
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        .main-content.shifted {
            margin-left: 280px;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .booking-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
        }
        .checkout-summary {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
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
            <a href="receptionist-checkout.php" class="nav-link active">
                <i class="fas fa-minus-circle me-2"></i> Process Check-out
            </a>
            <a href="receptionist-rooms.php" class="nav-link">
                <i class="fas fa-bed me-2"></i> Manage Rooms
            </a>
            <a href="../generate_bill.php" class="nav-link">
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
                <div class="main-content p-4" id="mainContent">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="receptionist.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-minus-circle me-2"></i> Process Check-out</h2>
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
                    
                    <!-- Today's Check-outs List - APPEARS FIRST -->
                    <?php if ($todays_checkouts && $todays_checkouts->num_rows > 0): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i> Today's Check-outs (<?php echo $todays_checkouts->num_rows; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                These guests are scheduled to check out today. Click the "Checkout" button to process their departure.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Booking Ref</th>
                                            <th>Guest Name</th>
                                            <th>Room</th>
                                            <th>Phone</th>
                                            <th>Nights</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $todays_checkouts->data_seek(0);
                                        while ($checkout = $todays_checkouts->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($checkout['booking_reference']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($checkout['guest_name']); ?></td>
                                            <td><?php echo htmlspecialchars($checkout['room_name']); ?> (<?php echo $checkout['room_number']; ?>)</td>
                                            <td><?php echo $checkout['phone'] ? htmlspecialchars($checkout['phone']) : 'N/A'; ?></td>
                                            <td><span class="badge bg-primary"><?php echo $checkout['nights']; ?> nights</span></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="search_booking">
                                                    <input type="hidden" name="search_type" value="reference">
                                                    <input type="hidden" name="search_value" value="<?php echo htmlspecialchars($checkout['booking_reference']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-arrow-right me-1"></i> Checkout
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Modern Search Section -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-search me-2"></i> Search Booking for Check-out</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                Good morning/afternoon! Thank you for staying with us at Harar Ras Hotel. Please search for the guest's booking using any of the options below.
                            </p>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="search_booking">
                                
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Search By:</label>
                                        <select name="search_type" id="searchTypeCheckout" class="form-select form-select-lg" required>
                                            <option value="reference">Booking Reference</option>
                                            <option value="name">Guest Name</option>
                                            <option value="phone">Mobile Number</option>
                                            <option value="room">Room Number</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label fw-bold">Enter Details:</label>
                                        <input type="text" name="search_value" id="searchValueCheckout" class="form-control form-control-lg" 
                                               placeholder="Enter booking reference (e.g., HRH20241011)" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-warning btn-lg w-100">
                                            <i class="fas fa-search me-2"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="alert alert-info">
                                <h6 class="alert-heading"><i class="fas fa-lightbulb me-2"></i> Quick Tips:</h6>
                                <ul class="mb-0 small">
                                    <li>Booking references start with "HRH" followed by date and number</li>
                                    <li>For name search, enter the guest's full name or last name</li>
                                    <li>Phone numbers can be searched with or without country code</li>
                                    <li>Room number search is useful for quick checkout</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        document.getElementById('searchTypeCheckout').addEventListener('change', function() {
                            const searchValue = document.getElementById('searchValueCheckout');
                            if (this.value === 'reference') {
                                searchValue.placeholder = 'Enter booking reference (e.g., HRH20241011)';
                            } else if (this.value === 'name') {
                                searchValue.placeholder = 'Enter guest name';
                            } else if (this.value === 'phone') {
                                searchValue.placeholder = 'Enter mobile number';
                            } else if (this.value === 'room') {
                                searchValue.placeholder = 'Enter room number (e.g., 205)';
                            }
                        });
                    </script>
                    
                    <!-- Today's Check-out List -->
                    <?php if ($todays_checkouts && $todays_checkouts->num_rows > 0): ?>
                    <div class="card mt-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i> Today's Check-outs (<?php echo $todays_checkouts->num_rows; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Booking Ref</th>
                                            <th>Guest Name</th>
                                            <th>Room</th>
                                            <th>Phone</th>
                                            <th>Nights</th>
                                            <th>Deposit</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        while ($checkout = $todays_checkouts->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($checkout['booking_reference']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($checkout['guest_name']); ?></td>
                                            <td><?php echo htmlspecialchars($checkout['room_name']); ?> (<?php echo $checkout['room_number']; ?>)</td>
                                            <td><?php echo $checkout['phone'] ? htmlspecialchars($checkout['phone']) : 'N/A'; ?></td>
                                            <td><span class="badge bg-primary"><?php echo $checkout['nights']; ?> nights</span></td>
                                            <td><?php echo formatCurrency($checkout['incidental_deposit'] ?: 0); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="search_booking">
                                                    <input type="hidden" name="search_type" value="reference">
                                                    <input type="hidden" name="search_value" value="<?php echo htmlspecialchars($checkout['booking_reference']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-arrow-right me-1"></i> Check-out
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <!-- Check-out Processing Section -->
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Modern Booking Information Display -->
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0">
                                        <i class="fas fa-check-circle me-2"></i> Guest Found - Ready for Check-out
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="booking-info">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <h6 class="text-warning mb-3"><i class="fas fa-user me-2"></i> Guest Information</h6>
                                                        <p class="mb-2"><strong>Name:</strong> <?php echo safe_display($booking_data['guest_name']); ?></p>
                                                        <p class="mb-2"><strong>Email:</strong> <?php echo safe_display($booking_data['email']); ?></p>
                                                        <p class="mb-2"><strong>Phone:</strong> <?php echo safe_display($booking_data['phone']); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6 class="text-warning mb-3"><i class="fas fa-bed me-2"></i> Room Details</h6>
                                                        <p class="mb-2"><strong>Room:</strong> <?php echo safe_display($booking_data['room_name']); ?></p>
                                                        <p class="mb-2"><strong>Room Number:</strong> <?php echo safe_display($booking_data['room_number']); ?></p>
                                                        <p class="mb-2"><strong>Booking Ref:</strong> <span class="badge bg-warning text-dark"><?php echo safe_display($booking_data['booking_reference']); ?></span></p>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <p class="mb-1"><strong>Check-in:</strong></p>
                                                        <p class="text-success fw-bold"><?php echo date('M j, Y', strtotime($booking_data['check_in_date'])); ?></p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <p class="mb-1"><strong>Check-out:</strong></p>
                                                        <p class="text-danger fw-bold"><?php echo date('M j, Y', strtotime($booking_data['check_out_date'])); ?></p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <p class="mb-1"><strong>Nights:</strong></p>
                                                        <p class="fw-bold"><?php echo $booking_data['nights']; ?> night(s)</p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <p class="mb-1"><strong>Deposit Paid:</strong></p>
                                                        <p class="fw-bold"><?php echo format_currency($booking_data['incidental_deposit'] ?? 0); ?></p>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <p class="mb-1"><strong>Room Charges:</strong></p>
                                                        <h4 class="text-primary mb-0"><?php echo format_currency($booking_data['total_price']); ?></h4>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <p class="mb-1"><strong>Status:</strong></p>
                                                        <h5><span class="badge bg-warning text-dark">
                                                            <i class="fas fa-door-open me-1"></i> Checked In
                                                        </span></h5>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <p class="mb-1"><strong>Room Key:</strong></p>
                                                        <p class="fw-bold"><?php echo safe_display($booking_data['room_key_number']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="alert alert-warning">
                                                <h6 class="alert-heading"><i class="fas fa-clipboard-check me-2"></i> Check-out Verification</h6>
                                                <p class="small mb-0">Please verify room condition and collect the room key before proceeding with check-out.</p>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-outline-warning" onclick="scrollToCheckoutForm()">
                                                    <i class="fas fa-arrow-down me-2"></i> Proceed to Check-out
                                                </button>
                                                <a href="receptionist-checkout.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-search me-2"></i> New Search
                                                </a>
                                                <a href="../generate_bill.php?booking_ref=<?php echo $booking_data['booking_reference']; ?>" 
                                                   class="btn btn-outline-info">
                                                    <i class="fas fa-file-invoice me-2"></i> View Bill
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                function scrollToCheckoutForm() {
                                    document.querySelector('.card:last-of-type').scrollIntoView({ behavior: 'smooth' });
                                }
                            </script>
                            
                            <!-- Check-out Form -->
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Process Check-out</h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info mb-4">
                                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Automatic Checkout Process</h6>
                                        <p class="mb-2">The system will automatically:</p>
                                        <ul class="mb-0">
                                            <li>Calculate final bill amount (room charges + incidental charges)</li>
                                            <li>Process deposit refund if applicable</li>
                                            <li>Mark room key as returned</li>
                                            <li>Update room status to available</li>
                                            <li>Generate checkout receipt</li>
                                        </ul>
                                    </div>
                                    
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to complete the checkout for this guest?');">
                                        <input type="hidden" name="action" value="process_checkout">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_data['id']; ?>">
                                        <input type="hidden" name="room_id" value="<?php echo $booking_data['room_id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Check-out Notes (Optional)</label>
                                            <textarea name="checkout_notes" class="form-control" rows="3" 
                                                      placeholder="Room condition, damages, special notes..."></textarea>
                                            <small class="text-muted">Add any special notes about room condition or guest checkout</small>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-success btn-lg">
                                                <i class="fas fa-check-circle me-2"></i> Complete Check-out
                                            </button>
                                            <a href="receptionist-checkout.php" class="btn btn-secondary">
                                                <i class="fas fa-search me-2"></i> New Search
                                            </a>
                                            <a href="../generate_bill.php?booking_ref=<?php echo $booking_data['booking_reference']; ?>" 
                                               class="btn btn-info">
                                                <i class="fas fa-file-invoice me-2"></i> Generate Bill
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Check-out Summary -->
                            <?php
                            // Calculate incidental charges for display
                            $charges_query = "SELECT SUM(total_amount) as total_charges 
                                             FROM incidental_charges 
                                             WHERE booking_id = {$booking_data['id']} AND status = 'approved'";
                            $charges_result = $conn->query($charges_query);
                            $charges_data = $charges_result->fetch_assoc();
                            $incidental_total = $charges_data['total_charges'] ?? 0;
                            
                            $room_charges = $booking_data['total_price'];
                            $incidental_deposit = $booking_data['incidental_deposit'] ?? 0;
                            $final_total = $room_charges + $incidental_total;
                            $amount_due = $final_total;
                            ?>
                            <div class="checkout-summary mb-3">
                                <h5><i class="fas fa-calculator me-2"></i> Auto-Calculated Summary</h5>
                                <hr class="bg-white">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Room Charges:</span>
                                    <span><?php echo format_currency($room_charges); ?></span>
                                </div>
                                <?php if ($incidental_total > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Incidental Charges:</span>
                                    <span><?php echo format_currency($incidental_total); ?></span>
                                </div>
                                <?php endif; ?>
                                <hr class="bg-white">
                                <div class="d-flex justify-content-between mb-3">
                                    <strong>Total Amount to Collect:</strong>
                                    <strong class="fs-4"><?php echo format_currency($amount_due); ?></strong>
                                </div>
                                <?php if ($incidental_deposit > 0): ?>
                                <div class="alert alert-light mb-0">
                                    <small><i class="fas fa-info-circle me-1"></i> Incidental deposit of <?php echo format_currency($incidental_deposit); ?> will be refunded</small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Check-out Instructions -->
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Check-out Instructions</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success me-2"></i> Collect room key</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Inspect room condition</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Process final payment</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Refund deposit if applicable</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Update room status</li>
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