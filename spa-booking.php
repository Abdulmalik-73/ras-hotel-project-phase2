<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php?redirect=spa-booking');
    exit();
}

$error = '';
$success = '';

// Get spa services from database
$spa_services_query = "SELECT DISTINCT id, name, price, description FROM services WHERE category = 'spa' AND status = 'active' ORDER BY price";
$spa_services_result = $conn->query($spa_services_query);
$spa_services = [];

// Get spa services from database
$spa_services_query = "SELECT DISTINCT id, name, price, description FROM services WHERE category = 'spa' AND status = 'active' ORDER BY price";
$spa_services_result = $conn->query($spa_services_query);
$spa_services = [];

if (!$spa_services_result) {
    $error = 'Database error: ' . $conn->error;
} else {
    while ($row = $spa_services_result->fetch_assoc()) {
        // Add duration based on service name
        $duration = '60 minutes'; // default
        if (stripos($row['name'], 'facial') !== false) {
            $duration = '45 minutes';
        } elseif (stripos($row['name'], 'sauna') !== false) {
            $duration = '30 minutes';
        }
        
        $spa_services[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'duration' => $duration,
            'description' => $row['description']
        ];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_service'])) {
    $service_id = (int)$_POST['service_id'];
    $service_date = sanitize_input($_POST['service_date']);
    $service_time = sanitize_input($_POST['service_time']);
    $special_requests = sanitize_input($_POST['special_requests'] ?? '');
    
    // Validate inputs
    if (empty($service_id) || empty($service_date) || empty($service_time)) {
        $error = 'Please fill in all required fields';
    } else {
        // Get service details
        $selected_service = null;
        foreach ($spa_services as $service) {
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
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Generate booking reference
                $booking_reference = 'SPA-' . strtoupper(substr(md5(time() . $_SESSION['user_id']), 0, 8));
                
                // Create booking entry
                $booking_query = "INSERT INTO bookings (user_id, booking_reference, customers, total_price, booking_type, status, payment_status, verification_status, created_at) 
                                 VALUES (?, ?, 1, ?, 'spa_service', 'pending', 'pending', 'pending_payment', NOW())";
                
                $booking_stmt = $conn->prepare($booking_query);
                $booking_stmt->bind_param("isd", $_SESSION['user_id'], $booking_reference, $selected_service['price']);
                $booking_stmt->execute();
                $booking_id = mysqli_insert_id($conn);
                
                // Create service booking entry
                $service_query = "INSERT INTO service_bookings (booking_id, user_id, service_category, service_name, service_price, quantity, total_price, service_date, service_time, special_requests, status, created_at) 
                                 VALUES (?, ?, 'spa', ?, ?, 1, ?, ?, ?, ?, 'pending', NOW())";
                
                $service_stmt = $conn->prepare($service_query);
                // Fixed: Changed to 'iisddsss' to match 8 parameters (i=int, s=string, d=double)
                $service_stmt->bind_param("iisddsss", 
                    $booking_id,                    // i - int
                    $_SESSION['user_id'],           // i - int
                    $selected_service['name'],      // s - string
                    $selected_service['price'],     // d - double
                    $selected_service['price'],     // d - double
                    $service_date,                  // s - string
                    $service_time,                  // s - string
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
                log_user_activity($_SESSION['user_id'], 'booking', 'Spa service booked: ' . $selected_service['name'] . ' - ' . $booking_reference, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Commit transaction
                $conn->commit();
                
                // Redirect to payment upload page
                header('Location: payment-upload.php?booking=' . $booking_id . '&type=spa');
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
    <title>Book Spa Service - Harar Ras Hotel</title>
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
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-spa"></i> Book Spa & Wellness Service</h4>
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
                                <?php if (empty($spa_services)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> No spa services are currently available. Please check back later.
                                </div>
                                <?php else: ?>
                                <?php foreach ($spa_services as $service): ?>
                                <div class="form-check mb-3 p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="service_id" id="service<?php echo $service['id']; ?>" value="<?php echo $service['id']; ?>" required>
                                    <label class="form-check-label w-100" for="service<?php echo $service['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($service['name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($service['description']); ?> (<?php echo $service['duration']; ?>)</small>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-primary">ETB <?php echo number_format($service['price'], 2); ?></strong>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold"><?php echo __('confirmation.service_date'); ?> *</label>
                                    <input type="date" name="service_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold"><?php echo __('confirmation.service_time'); ?> *</label>
                                    <input type="time" name="service_time" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('booking.special_requests'); ?> (Optional)</label>
                                <textarea name="special_requests" class="form-control" rows="3" placeholder="Any special requirements or preferences..."></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> <strong>Note:</strong> After booking, you'll be redirected to select your payment method and submit your transaction ID. Your payment screenshot will be uploaded successfully. Please wait while we verify your payment. Once it is confirmed, we will send a verification message to your email address.
                                <p class="mb-0 mt-2"><small><strong>Future Update:</strong> After API integration, payments will be verified automatically without manual approval.</small></p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="book_service" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check-circle"></i> <?php echo __('booking_auth.confirm_booking'); ?>
                                </button>
                                <a href="services.php#spa" class="btn btn-outline-secondary">
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
