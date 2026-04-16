<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

$booking_id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$booking_id) {
    header('Location: manage-bookings.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_id = (int)$_POST['room_id'];
    $check_in = sanitize_input($_POST['check_in_date']);
    $check_out = sanitize_input($_POST['check_out_date']);
    $customers = (int)$_POST['customers'];
    $status = sanitize_input($_POST['status']);
    $payment_status = sanitize_input($_POST['payment_status']);
    $special_requests = sanitize_input($_POST['special_requests']);
    
    // Calculate total price
    $room_query = "SELECT price FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($room_query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    
    $check_in_date = new DateTime($check_in);
    $check_out_date = new DateTime($check_out);
    $nights = $check_in_date->diff($check_out_date)->days;
    $total_price = $room['price'] * $nights;
    
    // Update booking
    $update_query = "UPDATE bookings SET 
                     room_id = ?, 
                     check_in_date = ?, 
                     check_out_date = ?, 
                     customers = ?, 
                     total_price = ?, 
                     status = ?, 
                     payment_status = ?, 
                     special_requests = ?
                     WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("issidsssi", $room_id, $check_in, $check_out, $customers, $total_price, $status, $payment_status, $special_requests, $booking_id);
    
    if ($stmt->execute()) {
        $success = 'Booking updated successfully!';
        // Refresh booking data
    } else {
        $error = 'Failed to update booking: ' . $conn->error;
    }
}

// Get booking details
$query = "SELECT b.*, 
          COALESCE(r.name, 'Food Order') as room_name,
          CONCAT(u.first_name, ' ', u.last_name) as guest_name,
          u.email
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: manage-bookings.php');
    exit();
}

// Get all rooms for dropdown
$rooms = get_all_rooms();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Booking - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel - Admin Dashboard
            </a>
            <div class="ms-auto">
                <a href="manage-bookings.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-edit me-2"></i> Edit Booking</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Booking Info -->
                        <div class="alert alert-info mb-4">
                            <strong>Booking Reference:</strong> <?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?><br>
                            <strong>Guest:</strong> <?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?> (<?php echo htmlspecialchars($booking['email'] ?? ''); ?>)
                        </div>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Room <span class="text-danger">*</span></label>
                                    <select name="room_id" class="form-select" required>
                                        <?php foreach ($rooms as $room): ?>
                                        <option value="<?php echo $room['id']; ?>" <?php echo $booking['room_id'] == $room['id'] ? 'selected' : ''; ?>>
                                            <?php echo $room['name']; ?> - Room <?php echo $room['room_number']; ?> (<?php echo format_currency($room['price']); ?>/night)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-in Date <span class="text-danger">*</span></label>
                                    <input type="date" name="check_in_date" class="form-control" value="<?php echo $booking['check_in_date']; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-out Date <span class="text-danger">*</span></label>
                                    <input type="date" name="check_out_date" class="form-control" value="<?php echo $booking['check_out_date']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Number of Guests <span class="text-danger">*</span></label>
                                    <input type="number" name="customers" class="form-control" min="1" value="<?php echo $booking['customers']; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Booking Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-select" required>
                                        <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $booking['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="checked_in" <?php echo $booking['status'] == 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                        <option value="checked_out" <?php echo $booking['status'] == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                        <option value="cancelled" <?php echo $booking['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Payment Status <span class="text-danger">*</span></label>
                                    <select name="payment_status" class="form-select" required>
                                        <option value="pending" <?php echo $booking['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?php echo $booking['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="refunded" <?php echo $booking['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Special Requests</label>
                                <textarea name="special_requests" class="form-control" rows="4"><?php echo htmlspecialchars($booking['special_requests'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="manage-bookings.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
