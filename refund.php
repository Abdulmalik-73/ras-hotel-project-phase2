<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user has permission
if (!is_logged_in() || !in_array($_SESSION['user_role'], ['receptionist', 'manager', 'admin'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$booking_data = null;
$refund_calculation = null;

// Handle booking search
if (isset($_POST['search_booking']) && (!empty($_POST['booking_reference']) || !empty($_POST['customer_email']) || !empty($_POST['customer_name']))) {
    $booking_ref = sanitize_input($_POST['booking_reference'] ?? '');
    $customer_email = sanitize_input($_POST['customer_email'] ?? '');
    $customer_name = sanitize_input($_POST['customer_name'] ?? '');
    
    // Build dynamic query based on provided search criteria
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    if (!empty($booking_ref)) {
        $where_conditions[] = "b.booking_reference = ?";
        $params[] = $booking_ref;
        $param_types .= 's';
    }
    
    if (!empty($customer_email)) {
        $where_conditions[] = "u.email = ?";
        $params[] = $customer_email;
        $param_types .= 's';
    }
    
    if (!empty($customer_name)) {
        $where_conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $search_name = "%$customer_name%";
        $params[] = $search_name;
        $params[] = $search_name;
        $params[] = $search_name;
        $param_types .= 'sss';
    }
    
    if (empty($where_conditions)) {
        $error = 'Please provide at least one search criteria';
    } else {
        $query = "SELECT b.*, r.name as room_name, u.first_name, u.last_name, u.email, u.phone 
                  FROM bookings b 
                  JOIN rooms r ON b.room_id = r.id 
                  JOIN users u ON b.user_id = u.id 
                  WHERE (" . implode(' OR ', $where_conditions) . ") AND b.status IN ('confirmed', 'pending')
                  ORDER BY b.created_at DESC";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            if ($result->num_rows == 1) {
                // Single result - proceed directly
                $booking_data = $result->fetch_assoc();
                $refund_calculation = calculateRefund($booking_data);
            } else {
                // Multiple results - show selection
                $search_results = [];
                while ($row = $result->fetch_assoc()) {
                    $search_results[] = $row;
                }
            }
        } else {
            $error = 'No eligible booking found with the provided search criteria';
        }
    }
}

// Handle booking selection from multiple results
if (isset($_POST['select_booking']) && !empty($_POST['selected_booking_id'])) {
    $selected_id = (int)$_POST['selected_booking_id'];
    
    $query = "SELECT b.*, r.name as room_name, u.first_name, u.last_name, u.email, u.phone 
              FROM bookings b 
              JOIN rooms r ON b.room_id = r.id 
              JOIN users u ON b.user_id = u.id 
              WHERE b.id = ? AND b.status IN ('confirmed', 'pending')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selected_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking_data = $result->fetch_assoc();
        $refund_calculation = calculateRefund($booking_data);
    } else {
        $error = 'Selected booking not found or not eligible for refund';
    }
}

// Handle refund processing
if (isset($_POST['process_refund']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $refund_amount = (float)$_POST['refund_amount'];
    $refund_reason = sanitize_input($_POST['refund_reason']);
    $refund_method = sanitize_input($_POST['refund_method']);
    $admin_notes = sanitize_input($_POST['admin_notes']);
    
    // Update booking status to cancelled
    $query = "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    
    if ($stmt->execute()) {
        // Insert refund record
        $refund_ref = 'REF' . date('Ymd') . rand(1000, 9999);
        $insert_query = "INSERT INTO refunds (booking_id, refund_reference, original_amount, refund_amount, 
                        refund_reason, refund_method, admin_notes, processed_by, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'processed', NOW())";
        
        $stmt2 = $conn->prepare($insert_query);
        $stmt2->bind_param("issdsssi", $booking_id, $refund_ref, $booking_data['total_price'], 
                          $refund_amount, $refund_reason, $refund_method, $admin_notes, $_SESSION['user_id']);
        
        if ($stmt2->execute()) {
            $success = "Refund processed successfully! Reference: $refund_ref";
            $booking_data = null; // Clear form
        } else {
            $error = 'Failed to record refund: ' . $stmt2->error;
        }
    } else {
        $error = 'Failed to cancel booking: ' . $stmt->error;
    }
}

// Harar Ras Hotel Cancellation Policy Function
function calculateRefund($booking) {
    $check_in_date = new DateTime($booking['check_in']);
    $current_date = new DateTime();
    $days_until_checkin = $current_date->diff($check_in_date)->days;
    
    $original_amount = $booking['total_price'];
    $refund_percentage = 0;
    $policy_applied = '';
    
    // Harar Ras Hotel Cancellation Policy (Based on Ethiopian hotel standards)
    if ($days_until_checkin >= 7) {
        // 7+ days before check-in: Full refund minus processing fee
        $refund_percentage = 95; // 5% processing fee
        $policy_applied = 'Full Refund (7+ days advance)';
    } elseif ($days_until_checkin >= 3) {
        // 3-6 days before check-in: 75% refund
        $refund_percentage = 75;
        $policy_applied = 'Partial Refund (3-6 days advance)';
    } elseif ($days_until_checkin >= 1) {
        // 1-2 days before check-in: 50% refund
        $refund_percentage = 50;
        $policy_applied = 'Partial Refund (1-2 days advance)';
    } elseif ($days_until_checkin == 0) {
        // Same day cancellation: 25% refund
        $refund_percentage = 25;
        $policy_applied = 'Same Day Cancellation';
    } else {
        // Past check-in date: No refund
        $refund_percentage = 0;
        $policy_applied = 'No Refund (Past check-in date)';
    }
    
    $refund_amount = ($original_amount * $refund_percentage) / 100;
    $deduction = $original_amount - $refund_amount;
    
    return [
        'original_amount' => $original_amount,
        'refund_percentage' => $refund_percentage,
        'refund_amount' => $refund_amount,
        'deduction' => $deduction,
        'policy_applied' => $policy_applied,
        'days_until_checkin' => $days_until_checkin
    ];
}

// Create refunds table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    refund_reference VARCHAR(50) UNIQUE NOT NULL,
    original_amount DECIMAL(10,2) NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    refund_reason TEXT,
    refund_method VARCHAR(50),
    admin_notes TEXT,
    processed_by INT NOT NULL,
    status ENUM('pending', 'processed', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_table);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Management - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-hotel text-gold"></i> Harar Ras Hotel - Refund Management
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard/receptionist.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a class="nav-link" href="dashboard/manager.php">
                    <i class="fas fa-tachometer-alt"></i> Manager Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Cancellation Policy Information -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Harar Ras Hotel Cancellation Policy</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Refund Schedule:</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span><strong>7+ days before check-in:</strong></span>
                                <span class="badge bg-success">95% Refund</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><strong>3-6 days before check-in:</strong></span>
                                <span class="badge bg-warning">75% Refund</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><strong>1-2 days before check-in:</strong></span>
                                <span class="badge bg-warning">50% Refund</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><strong>Same day cancellation:</strong></span>
                                <span class="badge bg-danger">25% Refund</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><strong>Past check-in date:</strong></span>
                                <span class="badge bg-dark">No Refund</span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Important Notes:</h6>
                        <ul>
                            <li>All refunds are subject to a 5% processing fee</li>
                            <li>Refunds will be processed within 5-7 business days</li>
                            <li>Refunds will be made to the original payment method</li>
                            <li>Special circumstances may be considered by management</li>
                            <li>Group bookings may have different cancellation terms</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Booking Section -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-search"></i> Search Booking for Refund</h5>
                <small>Enter booking reference, customer email, or customer name to process refund</small>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Booking Reference:</label>
                            <input type="text" name="booking_reference" class="form-control" 
                                   placeholder="e.g., HRH20240101"
                                   value="<?php echo isset($_POST['booking_reference']) ? htmlspecialchars($_POST['booking_reference']) : ''; ?>">
                            <small class="text-muted">Exact booking reference</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Customer Email:</label>
                            <input type="email" name="customer_email" class="form-control" 
                                   placeholder="customer@example.com"
                                   value="<?php echo isset($_POST['customer_email']) ? htmlspecialchars($_POST['customer_email']) : ''; ?>">
                            <small class="text-muted">Customer's email address</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Customer Name:</label>
                            <input type="text" name="customer_name" class="form-control" 
                                   placeholder="John Doe"
                                   value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''; ?>">
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
                            <button type="submit" name="search_booking" class="btn btn-primary btn-lg">
                                <i class="fas fa-search"></i> Search Booking
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($search_results) && !empty($search_results)): ?>
        <!-- Multiple Search Results -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-list"></i> Multiple Bookings Found (<?php echo count($search_results); ?> results)</h5>
                <small>Please select the booking you want to process for refund</small>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Booking Ref</th>
                                    <th>Customer Name</th>
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
                                    <td><strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['email']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['check_in'])); ?></td>
                                    <td><?php echo format_currency($booking['total_price']); ?></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($booking['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="refund.php" class="btn btn-secondary">
                            <i class="fas fa-search"></i> New Search
                        </a>
                        <button type="submit" name="select_booking" class="btn btn-primary">
                            <i class="fas fa-arrow-right"></i> Process Selected Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($booking_data && $refund_calculation): ?>
        <!-- Booking Details and Refund Calculation -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Refund Processing</h5>
                    </div>
                    <div class="card-body">
                        <!-- Booking Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-primary">Booking Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Reference:</strong></td><td><?php echo htmlspecialchars($booking_data['booking_reference']); ?></td></tr>
                                    <tr><td><strong>Customer:</strong></td><td><?php echo htmlspecialchars($booking_data['first_name'] . ' ' . $booking_data['last_name']); ?></td></tr>
                                    <tr><td><strong>Room:</strong></td><td><?php echo htmlspecialchars($booking_data['room_name']); ?></td></tr>
                                    <tr><td><strong>Check-in:</strong></td><td><?php echo date('M j, Y', strtotime($booking_data['check_in'])); ?></td></tr>
                                    <tr><td><strong>Check-out:</strong></td><td><?php echo date('M j, Y', strtotime($booking_data['check_out'])); ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">Contact Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($booking_data['email']); ?></td></tr>
                                    <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($booking_data['phone']); ?></td></tr>
                                    <tr><td><strong>Customers:</strong></td><td><?php echo $booking_data['customers']; ?></td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-info"><?php echo ucfirst($booking_data['status']); ?></span></td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Refund Form -->
                        <form method="POST" action="">
                            <input type="hidden" name="booking_id" value="<?php echo $booking_data['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Refund Amount:</label>
                                        <input type="number" name="refund_amount" class="form-control" 
                                               step="0.01" value="<?php echo $refund_calculation['refund_amount']; ?>" required>
                                        <small class="text-muted">Calculated based on cancellation policy</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Refund Method:</label>
                                        <select name="refund_method" class="form-select" required>
                                            <option value="">Select Refund Method</option>
                                            <option value="original_payment">Original Payment Method</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="cash">Cash</option>
                                            <option value="credit_note">Hotel Credit Note</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Refund Reason:</label>
                                        <select name="refund_reason" class="form-select" required>
                                            <option value="">Select Reason</option>
                                            <option value="customer_cancellation">Customer Cancellation</option>
                                            <option value="hotel_cancellation">Hotel Cancellation</option>
                                            <option value="emergency">Emergency</option>
                                            <option value="medical_reasons">Medical Reasons</option>
                                            <option value="travel_restrictions">Travel Restrictions</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Admin Notes:</label>
                                        <textarea name="admin_notes" class="form-control" rows="3" 
                                                  placeholder="Additional notes or comments"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="refund.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> New Search
                                </a>
                                <button type="submit" name="process_refund" class="btn btn-success btn-lg">
                                    <i class="fas fa-check"></i> Process Refund
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Refund Calculation Preview -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-calculator"></i> Refund Calculation</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Original Amount:</strong></td>
                                <td class="text-end"><?php echo format_currency($refund_calculation['original_amount']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Policy Applied:</strong></td>
                                <td class="text-end">
                                    <small class="badge bg-info"><?php echo $refund_calculation['policy_applied']; ?></small>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Refund Percentage:</strong></td>
                                <td class="text-end"><?php echo $refund_calculation['refund_percentage']; ?>%</td>
                            </tr>
                            <tr>
                                <td><strong>Days Until Check-in:</strong></td>
                                <td class="text-end"><?php echo $refund_calculation['days_until_checkin']; ?> days</td>
                            </tr>
                            <tr class="border-top">
                                <td><strong>Refund Amount:</strong></td>
                                <td class="text-end text-success"><strong><?php echo format_currency($refund_calculation['refund_amount']); ?></strong></td>
                            </tr>
                            <tr>
                                <td><strong>Deduction:</strong></td>
                                <td class="text-end text-danger"><?php echo format_currency($refund_calculation['deduction']); ?></td>
                            </tr>
                        </table>
                        
                        <?php if ($refund_calculation['days_until_checkin'] < 1): ?>
                        <div class="alert alert-warning mt-3">
                            <small><i class="fas fa-exclamation-triangle"></i> 
                            This booking is past the check-in date. Refund may require management approval.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Refunds -->
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Refunds</h5>
            </div>
            <div class="card-body">
                <?php
                $recent_refunds = $conn->query("SELECT r.*, b.booking_reference, u.first_name, u.last_name 
                                               FROM refunds r 
                                               JOIN bookings b ON r.booking_id = b.id 
                                               JOIN users u ON b.user_id = u.id 
                                               ORDER BY r.created_at DESC LIMIT 10");
                ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Refund Ref</th>
                                <th>Booking Ref</th>
                                <th>Customer</th>
                                <th>Original Amount</th>
                                <th>Refund Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($refund = $recent_refunds->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($refund['refund_reference']); ?></td>
                                <td><?php echo htmlspecialchars($refund['booking_reference']); ?></td>
                                <td><?php echo htmlspecialchars($refund['first_name'] . ' ' . $refund['last_name']); ?></td>
                                <td><?php echo format_currency($refund['original_amount']); ?></td>
                                <td><?php echo format_currency($refund['refund_amount']); ?></td>
                                <td><span class="badge bg-success"><?php echo ucfirst($refund['status']); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($refund['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
    </script>
</body>
</html>