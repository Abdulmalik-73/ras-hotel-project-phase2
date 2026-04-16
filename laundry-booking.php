<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php?redirect=laundry-booking');
    exit();
}

$error = '';
$success = '';

// Get laundry and spa services from database
$services_query = "SELECT DISTINCT id, name, price, description, category FROM services WHERE (category = 'laundry' OR category = 'spa') AND status = 'active' ORDER BY category, price";
$services_result = $conn->query($services_query);
$all_services = [];

if (!$services_result) {
    $error = 'Database error: ' . $conn->error;
} else {
    while ($row = $services_result->fetch_assoc()) {
        // Add unit based on service name and category
        $unit = 'per session'; // default
        if ($row['category'] == 'laundry') {
            $unit = 'per load';
            if (stripos($row['name'], 'dry cleaning') !== false) {
                $unit = 'per item';
            }
        }
        
        $all_services[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'unit' => $unit,
            'description' => $row['description'],
            'category' => $row['category']
        ];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_service'])) {
    $service_id = (int)$_POST['service_id'];
    $quantity = (int)$_POST['quantity'];
    $pickup_date = sanitize_input($_POST['pickup_date']);
    $pickup_time = sanitize_input($_POST['pickup_time']);
    $special_requests = sanitize_input($_POST['special_requests'] ?? '');
    
    // Validate inputs
    if (empty($service_id) || empty($quantity) || $quantity < 1 || empty($pickup_date) || empty($pickup_time)) {
        $error = 'Please fill in all required fields with valid values';
    } else {
        // Get service details
        $selected_service = null;
        foreach ($all_services as $service) {
            if ($service['id'] == $service_id) {
                $selected_service = $service;
                break;
            }
        }
        
        if (!$selected_service) {
            $error = 'Invalid service selected';
        } elseif ($selected_service['price'] <= 0) {
            $error = 'Invalid service price';
        } else {
            // Calculate total price
            $total_price = $selected_service['price'] * $quantity;
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Generate booking reference
                $booking_reference = ($selected_service['category'] == 'spa' ? 'SPA-' : 'LND-') . strtoupper(substr(md5(time() . $_SESSION['user_id']), 0, 8));
                
                // Create booking entry
                $booking_type = $selected_service['category'] == 'spa' ? 'spa_service' : 'laundry_service';
                $booking_query = "INSERT INTO bookings (user_id, booking_reference, customers, total_price, booking_type, status, payment_status, verification_status, created_at) 
                                 VALUES (?, ?, 1, ?, ?, 'pending', 'pending', 'pending_payment', NOW())";
                
                $booking_stmt = $conn->prepare($booking_query);
                $booking_stmt->bind_param("isds", $_SESSION['user_id'], $booking_reference, $total_price, $booking_type);
                $booking_stmt->execute();
                $booking_id = mysqli_insert_id($conn);
                
                // Create service booking entry
                $service_query = "INSERT INTO service_bookings (booking_id, user_id, service_category, service_name, service_price, quantity, total_price, service_date, service_time, special_requests, status, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                
                $service_stmt = $conn->prepare($service_query);
                // Fixed: Changed to 'iissdiisss' to match 10 parameters
                $service_stmt->bind_param("iissdidsss", 
                    $booking_id,                    // i - int
                    $_SESSION['user_id'],           // i - int
                    $selected_service['category'],  // s - string
                    $selected_service['name'],      // s - string
                    $selected_service['price'],     // d - double
                    $quantity,                      // i - int
                    $total_price,                   // d - double
                    $pickup_date,                   // s - string
                    $pickup_time,                   // s - string
                    $special_requests               // s - string
                );
                $service_stmt->execute();
                
                // Generate payment reference and deadline
                $payment_ref = 'HRH-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($booking_id . time()), 0, 6));
                $deadline = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                
                // Update booking with payment details
                $update_query = "UPDATE bookings SET payment_reference = ?, payment_deadline = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssi", $payment_ref, $deadline, $booking_id);
                $update_stmt->execute();
                
                // Log user activity
                $activity_desc = ($selected_service['category'] == 'spa' ? 'Spa' : 'Laundry') . ' service booked: ' . $selected_service['name'] . ' x' . $quantity . ' - ' . $booking_reference;
                log_user_activity($_SESSION['user_id'], 'booking', $activity_desc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Commit transaction
                $conn->commit();
                
                // Redirect to payment upload page
                $redirect_type = $selected_service['category'] == 'spa' ? 'spa' : 'laundry';
                header('Location: payment-upload.php?booking=' . $booking_id . '&type=' . $redirect_type);
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Booking failed. Please try again. Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Laundry Service - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0"><i class="fas fa-spa"></i> <i class="fas fa-tshirt"></i> Book Spa & Laundry Service</h4>
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
                        
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Select Service *</label>
                                <?php if (empty($all_services)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> No services are currently available. Please check back later.
                                </div>
                                <?php else: ?>
                                <?php foreach ($all_services as $service): ?>
                                <div class="form-check mb-3 p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="service_id" id="service<?php echo $service['id']; ?>" value="<?php echo $service['id']; ?>" required>
                                    <label class="form-check-label w-100" for="service<?php echo $service['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php if ($service['category'] == 'spa'): ?>
                                                    <i class="fas fa-spa text-purple"></i>
                                                    <?php else: ?>
                                                    <i class="fas fa-tshirt text-success"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($service['name']); ?>
                                                </h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($service['description']); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-warning">ETB <?php echo number_format($service['price'], 2); ?></strong>
                                                <br><small class="text-muted"><?php echo $service['unit']; ?></small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('confirmation.quantity'); ?> *</label>
                                <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                                <small class="text-muted">Number of sessions/loads/items</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold"><?php echo __('confirmation.service_date'); ?> *</label>
                                    <input type="date" name="pickup_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold"><?php echo __('confirmation.service_time'); ?> *</label>
                                    <input type="time" name="pickup_time" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('booking.special_requests'); ?> (Optional)</label>
                                <textarea name="special_requests" class="form-control" rows="3" placeholder="Any special care instructions, preferences, etc..."></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> <strong>Note:</strong> After booking, you'll be redirected to upload payment proof. Your payment screenshot will be uploaded successfully. Please wait while we verify your payment. Once it is confirmed, we will send a verification message to your email address.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="book_service" class="btn btn-warning btn-lg">
                                    <i class="fas fa-calendar-check"></i> <?php echo __('booking_auth.confirm_booking'); ?>
                                </button>
                                <a href="services.php#laundry" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> <?php echo __('food.back_to_services'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
