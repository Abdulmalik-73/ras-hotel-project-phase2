<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user has permission
if (!is_logged_in() || !in_array($_SESSION['user_role'], ['receptionist', 'manager', 'admin'])) {
    $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    header("Location: $proto://{$_SERVER['HTTP_HOST']}/login.php");
    exit();
}

$error = '';
$success = '';
$booking_data = null;

// Get search parameters from URL or POST
$search_type = isset($_POST['search_type']) ? sanitize_input($_POST['search_type']) : 'booking_ref';
$search_value = isset($_GET['booking_ref']) ? sanitize_input($_GET['booking_ref']) : (isset($_POST['search_value']) ? sanitize_input($_POST['search_value']) : '');
$booking_results = [];

if ($search_value) {
    // Build query based on search type
    $base_query = "SELECT b.*, r.name as room_name, r.room_number, r.price,
                   CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone,
                   DATEDIFF(b.check_out_date, b.check_in_date) as nights
                   FROM bookings b
                   JOIN rooms r ON b.room_id = r.id
                   JOIN users u ON b.user_id = u.id
                   WHERE b.status IN ('checked_in', 'checked_out')";
    
    if ($search_type == 'booking_ref') {
        $query = $base_query . " AND b.booking_reference = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $search_value);
    } elseif ($search_type == 'email') {
        $query = $base_query . " AND u.email LIKE ?";
        $stmt = $conn->prepare($query);
        $search_param = "%{$search_value}%";
        $stmt->bind_param("s", $search_param);
    } elseif ($search_type == 'username') {
        $query = $base_query . " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
        $stmt = $conn->prepare($query);
        $search_param = "%{$search_value}%";
        $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Calculate bill details for each booking
        $room_charges = $row['price'] * $row['nights'];
        $service_charges = $room_charges * 0.10; // 10% service charge
        $tax_amount = $room_charges * 0.15; // 15% VAT
        
        $row['room_charges'] = $room_charges;
        $row['service_charges'] = $service_charges;
        $row['tax_amount'] = $tax_amount;
        
        $booking_results[] = $row;
    }
    
    if (empty($booking_results)) {
        $error = 'No bookings found for the search criteria or bookings not eligible for billing';
    }
}

// Handle booking selection
$booking_data = null;
if (isset($_POST['select_booking'])) {
    $selected_booking_id = (int)$_POST['selected_booking_id'];
    
    // Re-fetch the booking data from database
    $query = "SELECT b.*, r.name as room_name, r.room_number, r.price,
              CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone,
              DATEDIFF(b.check_out_date, b.check_in_date) as nights
              FROM bookings b
              JOIN rooms r ON b.room_id = r.id
              JOIN users u ON b.user_id = u.id
              WHERE b.id = ? AND b.status IN ('checked_in', 'checked_out')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selected_booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking_data = $result->fetch_assoc();
        
        // Calculate bill details
        $room_charges = $booking_data['price'] * $booking_data['nights'];
        $service_charges = $room_charges * 0.10; // 10% service charge
        $tax_amount = $room_charges * 0.15; // 15% VAT
        
        $booking_data['room_charges'] = $room_charges;
        $booking_data['service_charges'] = $service_charges;
        $booking_data['tax_amount'] = $tax_amount;
    }
}

// Handle bill generation and sending to manager
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_bill'])) {
    $selected_booking_id = (int)$_POST['selected_booking_id'];
    $additional_charges = (float)$_POST['additional_charges'];
    $discount_amount = (float)$_POST['discount_amount'];
    $notes = sanitize_input($_POST['notes']);
    
    // Get the selected booking data
    $query = "SELECT b.*, r.name as room_name, r.room_number, r.price,
              CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone,
              DATEDIFF(b.check_out_date, b.check_in_date) as nights
              FROM bookings b
              JOIN rooms r ON b.room_id = r.id
              JOIN users u ON b.user_id = u.id
              WHERE b.id = ? AND b.status IN ('checked_in', 'checked_out')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selected_booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $selected_booking = $result->fetch_assoc();
        
        // Recalculate totals
        $room_charges = $selected_booking['price'] * $selected_booking['nights'];
        $service_charges = ($room_charges * 0.10) + $additional_charges;
        $tax_amount = $room_charges * 0.15;
        $total_amount = $room_charges + $service_charges + $tax_amount - $discount_amount;
        
        // Generate unique bill reference
        $bill_number = 'BILL' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Insert bill with sent_to_manager status (this sends it to manager)
        // Updated to match actual bills table structure
        $insert_query = "INSERT INTO bills (
            booking_id, customer_id, bill_reference, room_charges, service_charges, 
            incidental_charges, tax_amount, total_amount, status, generated_by, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent_to_manager', ?, ?)";
        
        $stmt = $conn->prepare($insert_query);
        // Parameters: booking_id, customer_id, bill_reference, room_charges, service_charges, incidental_charges, tax_amount, total_amount, generated_by, notes
        // Types: i (int), i (int), s (string), d (decimal), d, d, d, d, i (int), s (string) = 10 params
        $incidental_charges = $service_charges; // Use service_charges as incidental_charges
        $service_charges_zero = 0.00; // Create variable for literal value
        
        $stmt->bind_param("iisdddddis", 
            $selected_booking['id'],              // i - booking_id
            $selected_booking['user_id'],         // i - customer_id
            $bill_number,                         // s - bill_reference
            $room_charges,                        // d - room_charges
            $service_charges_zero,                // d - service_charges (set to 0, using incidental instead)
            $incidental_charges,                  // d - incidental_charges (additional charges)
            $tax_amount,                          // d - tax_amount
            $total_amount,                        // d - total_amount
            $_SESSION['user_id'],                 // i - generated_by
            $notes                                // s - notes
        );
        
        if ($stmt->execute()) {
            $success = "Bill #{$bill_number} generated successfully and sent to manager for approval!";
            $booking_data = null; // Clear form
            $booking_results = [];
            $search_value = '';
        } else {
            $error = 'Failed to generate bill: ' . $stmt->error;
        }
    } else {
        $error = 'Selected booking not found or not eligible for billing';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Bill - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel - Generate Bill
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard/receptionist.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$booking_data): ?>
            <!-- Booking Search Form -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <h3 class="mb-0">GENERATE BILL</h3>
                        </div>
                        <div class="card-body p-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            
                            <form method="POST" action="">
                                <!-- Search Type -->
                                <div class="mb-4">
                                    <label class="form-label text-white fw-bold">Search Type:</label>
                                    <select name="search_type" class="form-control form-control-lg" style="background: rgba(255,255,255,0.9);" required>
                                        <option value="booking_ref" <?php echo $search_type == 'booking_ref' ? 'selected' : ''; ?>>Booking Reference</option>
                                        <option value="email" <?php echo $search_type == 'email' ? 'selected' : ''; ?>>Guest Email</option>
                                        <option value="username" <?php echo $search_type == 'username' ? 'selected' : ''; ?>>Guest Name</option>
                                    </select>
                                </div>

                                <!-- Search Value -->
                                <div class="mb-4">
                                    <label class="form-label text-white fw-bold">Search Details:</label>
                                    <input type="text" name="search_value" class="form-control form-control-lg" 
                                           style="background: rgba(255,255,255,0.9);"
                                           placeholder="Enter booking reference, email, or guest name" 
                                           value="<?php echo htmlspecialchars($search_value); ?>" required>
                                </div>

                                <!-- Search Button -->
                                <div class="text-center">
                                    <button type="submit" class="btn btn-light btn-lg px-5">
                                        <i class="fas fa-search me-2"></i> Find Booking
                                    </button>
                                </div>
                            </form>

                            <?php if (!empty($booking_results)): ?>
                                <!-- Booking Selection -->
                                <div class="mt-4">
                                    <h5 class="text-white mb-3">Select Booking:</h5>
                                    <?php foreach ($booking_results as $booking): ?>
                                    <div class="card mb-2" style="background: rgba(255,255,255,0.95);">
                                        <div class="card-body p-3">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <h6 class="mb-1 text-primary"><?php echo htmlspecialchars($booking['booking_reference']); ?></h6>
                                                    <p class="mb-1"><strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong></p>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($booking['room_name']); ?> - Room <?php echo htmlspecialchars($booking['room_number']); ?> | 
                                                        <?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?> - <?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="select_booking" value="1">
                                                        <input type="hidden" name="selected_booking_id" value="<?php echo $booking['id']; ?>">
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-check me-1"></i> Select
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($booking_data): ?>
            <!-- Bill Generation Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Generate Bill & Send to Manager</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="selected_booking_id" value="<?php echo $booking_data['id']; ?>">
                                
                                <!-- Guest Information -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i> Guest Information</h6>
                                        <div class="bg-light p-3 rounded">
                                            <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($booking_data['guest_name']); ?></p>
                                            <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($booking_data['email']); ?></p>
                                            <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($booking_data['phone'] ?? 'Not provided'); ?></p>
                                            <p class="mb-0"><strong>Booking Ref:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($booking_data['booking_reference']); ?></span></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-bed me-2"></i> Stay Details</h6>
                                        <div class="bg-light p-3 rounded">
                                            <p class="mb-2"><strong>Room:</strong> <?php echo htmlspecialchars($booking_data['room_name']); ?></p>
                                            <p class="mb-2"><strong>Room Number:</strong> <?php echo htmlspecialchars($booking_data['room_number']); ?></p>
                                            <p class="mb-2"><strong>Check-in:</strong> <?php echo date('M j, Y', strtotime($booking_data['check_in_date'])); ?></p>
                                            <p class="mb-2"><strong>Check-out:</strong> <?php echo date('M j, Y', strtotime($booking_data['check_out_date'])); ?></p>
                                            <p class="mb-0"><strong>Nights:</strong> <?php echo $booking_data['nights']; ?> night(s)</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Billing Details -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-calculator me-2"></i> Additional Charges</h6>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Additional Service Charges:</label>
                                            <input type="number" name="additional_charges" class="form-control" 
                                                   step="0.01" value="0" id="additionalCharges">
                                            <small class="text-muted">Mini-bar, laundry, room service, etc.</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Discount Amount:</label>
                                            <input type="number" name="discount_amount" class="form-control" 
                                                   step="0.01" value="0" id="discountAmount">
                                            <small class="text-muted">Member discount, promotional offers</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-sticky-note me-2"></i> Notes & Comments</h6>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Bill Notes:</label>
                                            <textarea name="notes" class="form-control" rows="5" 
                                                      placeholder="Add any special notes, additional services, or comments for the manager..."></textarea>
                                            <small class="text-muted">These notes will be visible to the manager during approval</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Bill Process</h6>
                                    <p class="mb-0">
                                        When you generate this bill, it will be sent to the manager with <strong>"Pending"</strong> status. 
                                        The manager will review and either approve or reject the bill before it becomes final.
                                    </p>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="generate_bill.php" class="btn btn-secondary">
                                        <i class="fas fa-search me-2"></i> New Search
                                    </a>
                                    <button type="submit" name="generate_bill" class="btn btn-success btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i> Generate & Send to Manager
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Bill Preview -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-eye me-2"></i> Bill Preview</h6>
                        </div>
                        <div class="card-body">
                            <div id="billPreview">
                                <table class="table table-sm">
                                    <tr>
                                        <td>Room Charges:</td>
                                        <td class="text-end fw-bold" id="roomCharges"><?php echo format_currency($booking_data['room_charges']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Service Charges (10%):</td>
                                        <td class="text-end" id="serviceCharges"><?php echo format_currency($booking_data['service_charges']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>VAT (15%):</td>
                                        <td class="text-end" id="taxAmount"><?php echo format_currency($booking_data['tax_amount']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Additional Charges:</td>
                                        <td class="text-end" id="additionalChargesDisplay">ETB 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Discount:</td>
                                        <td class="text-end text-success" id="discountDisplay">- ETB 0.00</td>
                                    </tr>
                                    <tr class="border-top">
                                        <td><strong>Total Amount:</strong></td>
                                        <td class="text-end"><strong class="text-success fs-5" id="totalAmount"><?php echo format_currency($booking_data['room_charges'] + $booking_data['service_charges'] + $booking_data['tax_amount']); ?></strong></td>
                                    </tr>
                                </table>
                                
                                <div class="mt-3 p-2 bg-warning bg-opacity-25 rounded">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <strong>Status:</strong> Will be sent as "Pending" for manager approval
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time bill calculation
        function updateBillPreview() {
            const roomCharges = <?php echo $booking_data ? $booking_data['room_charges'] : 0; ?>;
            const baseServiceCharges = <?php echo $booking_data ? $booking_data['service_charges'] : 0; ?>;
            const taxAmount = <?php echo $booking_data ? $booking_data['tax_amount'] : 0; ?>;
            const additionalCharges = parseFloat(document.getElementById('additionalCharges').value) || 0;
            const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
            
            const totalServiceCharges = baseServiceCharges + additionalCharges;
            const total = roomCharges + totalServiceCharges + taxAmount - discountAmount;
            
            document.getElementById('serviceCharges').textContent = 'ETB ' + totalServiceCharges.toFixed(2);
            document.getElementById('additionalChargesDisplay').textContent = 'ETB ' + additionalCharges.toFixed(2);
            document.getElementById('discountDisplay').textContent = '- ETB ' + discountAmount.toFixed(2);
            document.getElementById('totalAmount').textContent = 'ETB ' + total.toFixed(2);
        }
        
        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const additionalCharges = document.getElementById('additionalCharges');
            const discountAmount = document.getElementById('discountAmount');
            
            if (additionalCharges) {
                additionalCharges.addEventListener('input', updateBillPreview);
                discountAmount.addEventListener('input', updateBillPreview);
            }
        });
    </script>
</body>
</html>