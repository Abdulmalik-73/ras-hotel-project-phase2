<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

$action = $_GET['action'] ?? 'search';
$booking_data = null;
$refund_calculation = null;
$message = '';
$error = '';

// Handle booking search for refund
if ($_POST && $_POST['action'] == 'search_booking') {
    $booking_reference = sanitize_input($_POST['booking_reference'] ?? '');
    $guest_email = sanitize_input($_POST['guest_email'] ?? '');
    $guest_name = sanitize_input($_POST['guest_name'] ?? '');
    
    // Build dynamic query based on provided search criteria
    $where_conditions = [];
    $search_params = [];
    
    if (!empty($booking_reference)) {
        $where_conditions[] = "b.booking_reference = '$booking_reference'";
    }
    
    if (!empty($guest_email)) {
        $where_conditions[] = "u.email = '$guest_email'";
    }
    
    if (!empty($guest_name)) {
        $escaped_name = $conn->real_escape_string($guest_name);
        $where_conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE '%$escaped_name%' OR u.first_name LIKE '%$escaped_name%' OR u.last_name LIKE '%$escaped_name%')";
    }
    
    if (empty($where_conditions)) {
        $error = 'Please provide at least one search criteria (booking reference, email, or guest name)';
    } else {
        $search_query = "SELECT b.*, 
                         COALESCE(r.name, 'N/A') as room_name, 
                         COALESCE(r.room_number, 'N/A') as room_number,
                         CONCAT(u.first_name, ' ', u.last_name) as guest_name, 
                         u.email, 
                         COALESCE(u.phone, 'N/A') as phone
                         FROM bookings b
                         LEFT JOIN rooms r ON b.room_id = r.id
                         JOIN users u ON b.user_id = u.id
                         WHERE (" . implode(' OR ', $where_conditions) . ")
                         AND b.status IN ('confirmed', 'checked_in', 'checked_out')
                         ORDER BY b.created_at DESC";
        
        $result = $conn->query($search_query);
        if ($result && $result->num_rows > 0) {
            if ($result->num_rows == 1) {
                // Single result - proceed directly
                $booking_data = $result->fetch_assoc();
                $refund_calculation = calculateRefund($booking_data);
                $action = 'process_refund';
            } else {
                // Multiple results - show selection
                $search_results = [];
                while ($row = $result->fetch_assoc()) {
                    $search_results[] = $row;
                }
                $action = 'select_booking';
            }
        } else {
            $error = 'No eligible booking found with the provided search criteria';
        }
    }
}

// Handle booking selection from multiple results
if ($_POST && $_POST['action'] == 'select_booking' && !empty($_POST['selected_booking_id'])) {
    $selected_id = (int)$_POST['selected_booking_id'];
    
    $search_query = "SELECT b.*, 
                     COALESCE(r.name, 'N/A') as room_name, 
                     COALESCE(r.room_number, 'N/A') as room_number,
                     CONCAT(u.first_name, ' ', u.last_name) as guest_name, 
                     u.email, 
                     COALESCE(u.phone, 'N/A') as phone
                     FROM bookings b
                     LEFT JOIN rooms r ON b.room_id = r.id
                     JOIN users u ON b.user_id = u.id
                     WHERE b.id = $selected_id
                     AND b.status IN ('confirmed', 'checked_in', 'checked_out')";
    
    $result = $conn->query($search_query);
    if ($result && $result->num_rows > 0) {
        $booking_data = $result->fetch_assoc();
        $refund_calculation = calculateRefund($booking_data);
        $action = 'process_refund';
    } else {
        $error = 'Selected booking not found or not eligible for refund';
        $action = 'search';
    }
}

// Handle refund processing
if ($_POST && $_POST['action'] == 'process_refund') {
    $booking_id = (int)$_POST['booking_id'];
    $refund_amount = (float)$_POST['refund_amount'];
    $refund_method = sanitize_input($_POST['refund_method']);
    $refund_reason = sanitize_input($_POST['refund_reason']);
    $admin_notes = sanitize_input($_POST['admin_notes']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking status to cancelled and payment status to refunded
        $query = "UPDATE bookings 
                  SET status = 'cancelled', 
                      payment_status = 'refunded',
                      checkout_notes = CONCAT(COALESCE(checkout_notes, ''), '\nRefund processed: ', ?)
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $refund_note = "Refund: " . format_currency($refund_amount) . " via " . $refund_method . ". Reason: " . $refund_reason;
        $stmt->bind_param("si", $refund_note, $booking_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update booking: " . $stmt->error);
        }
        
        // Log the refund in booking activity
        $activity_query = "INSERT INTO booking_activity_log 
                          (booking_id, user_id, activity_type, old_status, new_status, description, performed_by) 
                          VALUES (?, ?, 'cancelled', ?, 'refunded', ?, ?)";
        $activity_stmt = $conn->prepare($activity_query);
        
        // Get booking user_id first
        $user_query = "SELECT user_id, status FROM bookings WHERE id = ?";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param("i", $booking_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $booking_user_id = $user_data['user_id'];
        $old_status = $user_data['status'];
        
        $activity_description = "Refund Amount: " . format_currency($refund_amount) . 
                         "\nMethod: " . $refund_method . 
                         "\nReason: " . $refund_reason . 
                         "\nAdmin Notes: " . $admin_notes;
        
        $activity_stmt->bind_param("iissi", $booking_id, $booking_user_id, $old_status, $activity_description, $_SESSION['user_id']);
        
        if (!$activity_stmt->execute()) {
            throw new Exception("Failed to log activity: " . $activity_stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Fetch updated booking data to show refund confirmation
        $refetch_query = "SELECT b.*, 
                         COALESCE(r.name, 'N/A') as room_name, 
                         COALESCE(r.room_number, 'N/A') as room_number,
                         CONCAT(u.first_name, ' ', u.last_name) as guest_name, 
                         u.email, 
                         COALESCE(u.phone, 'N/A') as phone
                         FROM bookings b
                         LEFT JOIN rooms r ON b.room_id = r.id
                         JOIN users u ON b.user_id = u.id
                         WHERE b.id = ?";
        $refetch_stmt = $conn->prepare($refetch_query);
        $refetch_stmt->bind_param("i", $booking_id);
        $refetch_stmt->execute();
        $booking_data = $refetch_stmt->get_result()->fetch_assoc();
        
        // Recalculate refund (will show 0% since it's already cancelled)
        $refund_calculation = calculateRefund($booking_data);
        
        // Override refund calculation with actual processed values
        $refund_calculation['refund_amount'] = $refund_amount;
        $refund_calculation['refund_method'] = $refund_method;
        $refund_calculation['refund_reason'] = $refund_reason;
        $refund_calculation['admin_notes'] = $admin_notes;
        
        $message = "Refund processed successfully. Amount: " . format_currency($refund_amount);
        $action = 'refund_complete'; // Show refund completion page
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error = 'Failed to process refund: ' . $e->getMessage();
    }
}

// Harar Ras Hotel Cancellation Policy Function
function calculateRefund($booking) {
    $check_in_date = new DateTime($booking['check_in_date']);
    $current_date = new DateTime();
    
    // Calculate days until check-in (can be negative if past check-in)
    $interval = $current_date->diff($check_in_date);
    $days_until_checkin = $interval->days;
    
    // If check-in date is in the past, make it negative
    if ($current_date > $check_in_date) {
        $days_until_checkin = -$days_until_checkin;
    }
    
    $refund_amount = 0;
    $refund_percentage = 0;
    $policy_applied = '';
    
    // Harar Ras Hotel Cancellation Policy (Based on Ethiopian hotel standards)
    if ($days_until_checkin >= 7) {
        // 7+ days before check-in: Full refund minus processing fee
        $refund_percentage = 95;
        $refund_amount = $booking['total_price'] * 0.95;
        $policy_applied = 'Full Refund (7+ days advance)';
    } elseif ($days_until_checkin >= 3) {
        // 3-6 days before check-in: 75% refund
        $refund_percentage = 75;
        $refund_amount = $booking['total_price'] * 0.75;
        $policy_applied = 'Partial Refund (3-6 days advance)';
    } elseif ($days_until_checkin >= 1) {
        // 1-2 days before check-in: 50% refund
        $refund_percentage = 50;
        $refund_amount = $booking['total_price'] * 0.50;
        $policy_applied = 'Partial Refund (1-2 days advance)';
    } elseif ($days_until_checkin == 0) {
        // Same day cancellation: 25% refund
        $refund_percentage = 25;
        $refund_amount = $booking['total_price'] * 0.25;
        $policy_applied = 'Same Day Cancellation';
    } else {
        // Past check-in date: No refund
        $refund_percentage = 0;
        $refund_amount = 0;
        $policy_applied = 'Past Check-in Date';
    }
    
    return [
        'refund_amount' => $refund_amount,
        'refund_percentage' => $refund_percentage,
        'policy_applied' => $policy_applied,
        'days_until_checkin' => $days_until_checkin,
        'processing_fee' => $booking['total_price'] * 0.05
    ];
}

// Get recent refunds
$recent_refunds_query = "SELECT b.booking_reference, CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                         b.total_price, b.created_at as refund_date
                         FROM bookings b
                         JOIN users u ON b.user_id = u.id
                         WHERE b.status = 'cancelled'
                         ORDER BY b.created_at DESC
                         LIMIT 10";
$recent_refunds_result = $conn->query($recent_refunds_query);
$recent_refunds = $recent_refunds_result ? $recent_refunds_result : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Management - Manager Dashboard</title>
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
            min-height: 100vh;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
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
        .policy-header {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .search-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .refund-schedule {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .refund-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .refund-95 { border-left: 4px solid #28a745; }
        .refund-75 { border-left: 4px solid #ffc107; }
        .refund-50 { border-left: 4px solid #fd7e14; }
        .refund-25 { border-left: 4px solid #dc3545; }
        .refund-0 { border-left: 4px solid #6c757d; }
        .booking-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .refund-calculation {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
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

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
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
                            <i class="fas fa-file-invoice-dollar me-2"></i> Approve Bill
                        </a>
                        <a href="manager-feedback.php" class="nav-link">
                            <i class="fas fa-star me-2"></i> Customer Feedback
                        </a>
                        <a href="manager-refund.php" class="nav-link active">
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
                            <a href="manager.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-undo-alt me-2"></i> Refund Management</h2>
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
                    
                    <!-- Cancellation Policy -->
                    <div class="policy-header">
                        <h4><i class="fas fa-info-circle me-2"></i> Harar Ras Hotel Cancellation Policy</h4>
                        <p class="mb-0">Refund schedule based on cancellation timing</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="refund-schedule">
                                <h5>Refund Schedule:</h5>
                                <div class="refund-item refund-95">
                                    <div>
                                        <strong>7+ days before check-in:</strong>
                                        <br><small class="text-muted">Full refund minus processing fee</small>
                                    </div>
                                    <span class="badge bg-success fs-6">95% Refund</span>
                                </div>
                                <div class="refund-item refund-75">
                                    <div>
                                        <strong>3-6 days before check-in:</strong>
                                        <br><small class="text-muted">Partial refund</small>
                                    </div>
                                    <span class="badge bg-warning fs-6">75% Refund</span>
                                </div>
                                <div class="refund-item refund-50">
                                    <div>
                                        <strong>1-2 days before check-in:</strong>
                                        <br><small class="text-muted">Partial refund</small>
                                    </div>
                                    <span class="badge bg-warning fs-6">50% Refund</span>
                                </div>
                                <div class="refund-item refund-25">
                                    <div>
                                        <strong>Same day cancellation:</strong>
                                        <br><small class="text-muted">Minimal refund</small>
                                    </div>
                                    <span class="badge bg-danger fs-6">25% Refund</span>
                                </div>
                                <div class="refund-item refund-0">
                                    <div>
                                        <strong>Past check-in date:</strong>
                                        <br><small class="text-muted">No refund available</small>
                                    </div>
                                    <span class="badge bg-secondary fs-6">No Refund</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Important Notes:</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success me-2"></i> All refunds are subject to a 5% processing fee</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Refunds will be processed within 5-7 business days</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Refunds will be made to the original payment method</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Special circumstances may be considered by management</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Group bookings may have different cancellation terms</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($action == 'search'): ?>
                    <!-- Search Booking Section -->
                    <div class="search-header">
                        <h4><i class="fas fa-search me-2"></i> Search Booking for Refund</h4>
                        <p class="mb-0">Enter booking reference, guest email, or guest name to process refund</p>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="search_booking">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Booking Reference</label>
                                        <input type="text" name="booking_reference" class="form-control" 
                                               placeholder="e.g., HRH20241011"
                                               value="<?php echo isset($_POST['booking_reference']) ? htmlspecialchars($_POST['booking_reference']) : ''; ?>">
                                        <small class="text-muted">Exact booking reference</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Guest Email</label>
                                        <input type="email" name="guest_email" class="form-control" 
                                               placeholder="guest@example.com"
                                               value="<?php echo isset($_POST['guest_email']) ? htmlspecialchars($_POST['guest_email']) : ''; ?>">
                                        <small class="text-muted">Guest's email address</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Guest Name</label>
                                        <input type="text" name="guest_name" class="form-control" 
                                               placeholder="John Doe"
                                               value="<?php echo isset($_POST['guest_name']) ? htmlspecialchars($_POST['guest_name']) : ''; ?>">
                                        <small class="text-muted">First name, last name, or full name</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Search Options:</strong> You can search using any one or combination of the above fields. 
                                            The system will find bookings that match any of the provided criteria.
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-search me-2"></i> Search Booking
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($action == 'select_booking' && isset($search_results)): ?>
                    <!-- Multiple Search Results -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i> Multiple Bookings Found (<?php echo count($search_results); ?> results)</h5>
                            <p class="mb-0">Please select the booking you want to process for refund</p>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="select_booking">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Select</th>
                                                <th>Booking Ref</th>
                                                <th>Guest Name</th>
                                                <th>Email</th>
                                                <th>Room</th>
                                                <th>Check-in</th>
                                                <th>Total Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($search_results as $booking): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="selected_booking_id" 
                                                               value="<?php echo $booking['id']; ?>" id="booking_<?php echo $booking['id']; ?>" required>
                                                        <label class="form-check-label" for="booking_<?php echo $booking['id']; ?>"></label>
                                                    </div>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?></strong></td>
                                                <td><?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($booking['email'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($booking['room_name'] ?? ''); ?> (<?php echo $booking['room_number'] ?? ''; ?>)</td>
                                                <td><?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></td>
                                                <td><?php echo format_currency($booking['total_price']); ?></td>
                                                <td><span class="badge bg-info"><?php echo ucfirst($booking['status'] ?? ''); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-arrow-right me-2"></i> Process Selected Booking
                                    </button>
                                    <a href="manager-refund.php" class="btn btn-secondary">
                                        <i class="fas fa-search me-2"></i> New Search
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($action == 'process_refund' && $booking_data): ?>
                    <!-- Refund Processing Section -->
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Booking Information -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Booking Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="booking-info">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Guest:</strong> <?php echo htmlspecialchars($booking_data['guest_name'] ?? ''); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking_data['email'] ?? ''); ?></p>
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking_data['phone'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Booking Ref:</strong> <?php echo htmlspecialchars($booking_data['booking_reference'] ?? ''); ?></p>
                                                <p><strong>Room:</strong> <?php echo htmlspecialchars($booking_data['room_name'] ?? ''); ?> (<?php echo $booking_data['room_number'] ?? ''; ?>)</p>
                                                <p><strong>Status:</strong> <span class="badge bg-info"><?php echo ucfirst($booking_data['status'] ?? ''); ?></span></p>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p><strong>Check-in:</strong><br><?php echo date('M j, Y', strtotime($booking_data['check_in_date'])); ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>Check-out:</strong><br><?php echo date('M j, Y', strtotime($booking_data['check_out_date'])); ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>Total Paid:</strong><br><?php echo format_currency($booking_data['total_price']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Refund Processing Form -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-undo-alt me-2"></i> Process Refund</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="process_refund">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_data['id']; ?>">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Refund Amount (ETB)</label>
                                                <input type="number" name="refund_amount" class="form-control" 
                                                       step="0.01" value="<?php echo $refund_calculation['refund_amount']; ?>" required>
                                                <small class="text-muted">Calculated based on cancellation policy</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Refund Method</label>
                                                <select name="refund_method" class="form-select" required>
                                                    <option value="">Select Method</option>
                                                    <option value="original_payment">Original Payment Method</option>
                                                    <option value="bank_transfer">Bank Transfer</option>
                                                    <option value="cash">Cash</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Refund Reason</label>
                                            <select name="refund_reason" class="form-select" required>
                                                <option value="">Select Reason</option>
                                                <option value="guest_cancellation">Guest Cancellation</option>
                                                <option value="hotel_cancellation">Hotel Cancellation</option>
                                                <option value="overbooking">Overbooking</option>
                                                <option value="emergency">Emergency</option>
                                                <option value="medical_reasons">Medical Reasons</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Admin Notes</label>
                                            <textarea name="admin_notes" class="form-control" rows="3" 
                                                      placeholder="Additional notes about the refund..."></textarea>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check me-2"></i> Process Refund
                                            </button>
                                            <a href="manager-refund.php" class="btn btn-secondary">
                                                <i class="fas fa-search me-2"></i> New Search
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Refund Calculation -->
                            <div class="refund-calculation" id="refundCalculation">
                                <h5>Refund Calculation</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Original Amount:</span>
                                    <span><?php echo format_currency($booking_data['total_price']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Policy Applied:</span>
                                    <span><?php echo $refund_calculation['policy_applied']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Refund Percentage:</span>
                                    <span><?php echo $refund_calculation['refund_percentage']; ?>%</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Processing Fee:</span>
                                    <span>-<?php echo format_currency($refund_calculation['processing_fee']); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Refund Amount:</strong>
                                    <strong><?php echo format_currency($refund_calculation['refund_amount']); ?></strong>
                                </div>
                                <small class="text-light mt-2 d-block">
                                    Days until check-in: <?php echo $refund_calculation['days_until_checkin']; ?>
                                </small>
                                
                                <!-- Print Button -->
                                <button type="button" class="btn btn-outline-light btn-sm w-100 mt-3" onclick="printRefundInfo()">
                                    <i class="fas fa-print me-2"></i> Print Refund Information
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($action == 'refund_complete' && $booking_data): ?>
                    <!-- Refund Completion Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <h5 class="alert-heading"><i class="fas fa-check-circle me-2"></i> Refund Processed Successfully!</h5>
                                <p class="mb-0"><?php echo $message; ?></p>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Booking Information -->
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i> Refund Completed</h5>
                                </div>
                                <div class="card-body">
                                    <div class="booking-info">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Guest:</strong> <?php echo htmlspecialchars($booking_data['guest_name'] ?? ''); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking_data['email'] ?? ''); ?></p>
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking_data['phone'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Booking Ref:</strong> <?php echo htmlspecialchars($booking_data['booking_reference'] ?? ''); ?></p>
                                                <p><strong>Room:</strong> <?php echo htmlspecialchars($booking_data['room_name'] ?? ''); ?> (<?php echo $booking_data['room_number'] ?? ''; ?>)</p>
                                                <p><strong>Status:</strong> <span class="badge bg-danger">Cancelled - Refunded</span></p>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-4">
                                                <p><strong>Check-in:</strong><br><?php echo $booking_data['check_in_date'] ? date('M j, Y', strtotime($booking_data['check_in_date'])) : 'N/A'; ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>Check-out:</strong><br><?php echo $booking_data['check_out_date'] ? date('M j, Y', strtotime($booking_data['check_out_date'])) : 'N/A'; ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>Original Amount:</strong><br><?php echo format_currency($booking_data['total_price']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Refund Details -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Refund Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Refund Amount</label>
                                            <div class="h4 text-success"><?php echo format_currency($refund_calculation['refund_amount']); ?></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Refund Method</label>
                                            <div class="h6"><?php echo ucfirst(str_replace('_', ' ', $refund_calculation['refund_method'] ?? 'N/A')); ?></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Refund Reason</label>
                                            <div><?php echo ucfirst(str_replace('_', ' ', $refund_calculation['refund_reason'] ?? 'N/A')); ?></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Processed By</label>
                                            <div><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></div>
                                        </div>
                                        <?php if (!empty($refund_calculation['admin_notes'])): ?>
                                        <div class="col-12 mb-3">
                                            <label class="form-label text-muted">Admin Notes</label>
                                            <div class="p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($refund_calculation['admin_notes'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mt-4">
                                        <button type="button" class="btn btn-success" onclick="printRefundReceipt()">
                                            <i class="fas fa-print me-2"></i> Print Refund Receipt
                                        </button>
                                        <a href="manager-refund.php" class="btn btn-secondary">
                                            <i class="fas fa-search me-2"></i> Process Another Refund
                                        </a>
                                        <a href="manager.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Refund Summary -->
                            <div class="refund-calculation">
                                <h5>Refund Summary</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Original Amount:</span>
                                    <span><?php echo format_currency($booking_data['total_price']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Refund Amount:</span>
                                    <span class="text-success"><?php echo format_currency($refund_calculation['refund_amount']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Refund Method:</span>
                                    <span><?php echo ucfirst(str_replace('_', ' ', $refund_calculation['refund_method'] ?? 'N/A')); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Status:</strong>
                                    <strong class="text-success">Completed</strong>
                                </div>
                                <small class="text-light mt-2 d-block">
                                    Processed on: <?php echo date('M j, Y g:i A'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Refunds -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Refunds</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_refunds && $recent_refunds->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Booking Ref</th>
                                                <th>Guest</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($refund = $recent_refunds->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($refund['booking_reference'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($refund['guest_name'] ?? ''); ?></td>
                                                    <td><?php echo format_currency($refund['total_price']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($refund['refund_date'])); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No recent refunds found</p>
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
        // Highlight selected booking row
        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[name="selected_booking_id"]');
            radioButtons.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    // Remove highlight from all rows
                    document.querySelectorAll('tbody tr').forEach(function(row) {
                        row.classList.remove('table-primary');
                    });
                    
                    // Highlight selected row
                    if (this.checked) {
                        this.closest('tr').classList.add('table-primary');
                    }
                });
            });
        });
        
        // Print refund information — compact single page
        function printRefundInfo() {
            const printWindow = window.open('', '_blank', 'width=700,height=500');
            const bookingInfo = `
                <html>
                <head>
                    <title>Refund Receipt - <?php echo $booking_data['booking_reference'] ?? ''; ?></title>
                    <style>
                        * { margin:0; padding:0; box-sizing:border-box; }
                        body {
                            font-family: Arial, sans-serif;
                            font-size: 11px;
                            line-height: 1.4;
                            padding: 14px 18px;
                            color: #222;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 2px solid #333;
                            padding-bottom: 8px;
                            margin-bottom: 10px;
                        }
                        .header h1 { font-size: 18px; color: #333; margin-bottom: 2px; }
                        .header p  { font-size: 11px; color: #555; margin: 1px 0; }
                        .refund-box {
                            background: #28a745;
                            color: white;
                            padding: 8px 12px;
                            border-radius: 4px;
                            text-align: center;
                            margin: 8px 0;
                        }
                        .refund-box .label { font-size: 10px; letter-spacing: 1px; text-transform: uppercase; }
                        .refund-box .amount { font-size: 22px; font-weight: bold; line-height: 1.2; }
                        .section { margin-bottom: 8px; }
                        .section h2 {
                            font-size: 11px;
                            font-weight: bold;
                            background: #f0f0f0;
                            padding: 4px 8px;
                            border-left: 3px solid #28a745;
                            margin-bottom: 4px;
                            text-transform: uppercase;
                            letter-spacing: .5px;
                        }
                        .info-row {
                            display: flex;
                            justify-content: space-between;
                            padding: 3px 4px;
                            border-bottom: 1px solid #f0f0f0;
                        }
                        .info-label { font-weight: bold; color: #555; flex: 0 0 42%; }
                        .info-value { flex: 0 0 58%; text-align: right; }
                        .notes-box {
                            margin-top: 6px;
                            padding: 6px 8px;
                            background: #f8f9fa;
                            border-radius: 3px;
                            font-size: 10px;
                        }
                        .footer {
                            margin-top: 10px;
                            padding-top: 6px;
                            border-top: 1px solid #ddd;
                            text-align: center;
                            color: #777;
                            font-size: 10px;
                        }
                        @media print {
                            body { padding: 10px 14px; }
                            @page { margin: 0.6cm; size: A4; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Harar Ras Hotel</h1>
                        <p><strong>Refund Receipt</strong></p>
                        <p>Generated: ${new Date().toLocaleString()}</p>
                    </div>

                    <div class="refund-box">
                        <div class="label">Refund Amount</div>
                        <div class="amount"><?php echo format_currency($refund_calculation['refund_amount']); ?></div>
                    </div>

                    <div class="section">
                        <h2>Booking Information</h2>
                        <div class="info-row">
                            <span class="info-label">Booking Reference:</span>
                            <span class="info-value"><?php echo $booking_data['booking_reference'] ?? ''; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Guest Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking_data['guest_name'] ?? ''); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking_data['email'] ?? ''); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking_data['phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Room:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking_data['room_name'] ?? ''); ?> (<?php echo $booking_data['room_number'] ?? ''; ?>)</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Check-in:</span>
                            <span class="info-value"><?php echo $booking_data['check_in_date'] ? date('M j, Y', strtotime($booking_data['check_in_date'])) : 'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Check-out:</span>
                            <span class="info-value"><?php echo $booking_data['check_out_date'] ? date('M j, Y', strtotime($booking_data['check_out_date'])) : 'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Original Amount:</span>
                            <span class="info-value"><?php echo format_currency($booking_data['total_price']); ?></span>
                        </div>
                    </div>

                    <div class="section">
                        <h2>Refund Details</h2>
                        <div class="info-row">
                            <span class="info-label">Refund Method:</span>
                            <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $refund_calculation['refund_method'] ?? 'N/A')); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Refund Reason:</span>
                            <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $refund_calculation['refund_reason'] ?? 'N/A')); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Processed By:</span>
                            <span class="info-value"><?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Processed On:</span>
                            <span class="info-value"><?php echo date('M j, Y g:i A'); ?></span>
                        </div>
                        <?php if (!empty($refund_calculation['admin_notes'])): ?>
                        <div class="notes-box">
                            <strong>Admin Notes:</strong> <?php echo nl2br(htmlspecialchars($refund_calculation['admin_notes'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="footer">
                        <strong>Harar Ras Hotel</strong> &nbsp;|&nbsp; Official Refund Receipt &nbsp;|&nbsp; support@hararrashotel.com
                    </div>
                </body>
                </html>
            `;

            printWindow.document.write(bookingInfo);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(function() { printWindow.print(); }, 250);
        }

        // Print refund receipt (after refund is processed)
        function printRefundReceipt() {
            printRefundInfo();
        }
    </script>
</body>
</html>
