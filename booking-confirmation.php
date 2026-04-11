<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$booking_data = null;
$booking_ref = isset($_GET['booking_ref']) ? sanitize_input($_GET['booking_ref']) : '';
if (empty($booking_ref)) {
    $booking_ref = isset($_GET['ref']) ? sanitize_input($_GET['ref']) : '';
}

if (empty($booking_ref)) {
    header('Location: index.php');
    exit();
}

// Get booking details
$booking_query = "SELECT b.*, 
                  CASE 
                      WHEN b.booking_type = 'spa_service' THEN 'Spa & Wellness'
                      WHEN b.booking_type = 'laundry_service' THEN 'Laundry Service'
                      WHEN b.booking_type = 'food_order' THEN 'Food Order'
                      ELSE COALESCE(r.name, 'Room Booking')
                  END as room_name,
                  COALESCE(r.room_number, 'N/A') as room_number, 
                  CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email,
                  cf.id as feedback_id,
                  sb.service_name, sb.service_date, sb.service_time, sb.quantity as service_quantity,
                  fo.table_reservation, fo.reservation_date, fo.reservation_time, fo.guests as food_guests
                  FROM bookings b 
                  LEFT JOIN rooms r ON b.room_id = r.id 
                  LEFT JOIN users u ON b.user_id = u.id 
                  LEFT JOIN customer_feedback cf ON b.id = cf.booking_id
                  LEFT JOIN service_bookings sb ON b.id = sb.booking_id
                  LEFT JOIN food_orders fo ON b.id = fo.booking_id
                  WHERE b.booking_reference = ?";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Try with different parameter name
    $booking_ref = isset($_GET['booking_ref']) ? sanitize_input($_GET['booking_ref']) : $booking_ref;
    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param("s", $booking_ref);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        header('Location: my-bookings.php?error=booking_not_found');
        exit();
    }
}

$booking_data = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .confirmation-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .confirmation-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 700px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .confirmation-header {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .confirmation-header i {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .confirmation-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .confirmation-content {
            padding: 40px;
        }
        
        .booking-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .booking-details h4 {
            color: #2ecc71;
            margin-bottom: 25px;
            font-size: 1.5rem;
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            padding: 10px 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #27ae60;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 8px 20px rgba(46, 204, 113, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(46, 204, 113, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid #2ecc71;
            color: #2ecc71;
            padding: 15px 40px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .btn-outline-primary:hover {
            background: #2ecc71;
            border-color: #2ecc71;
            transform: translateY(-2px);
        }
        
        .next-steps {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .next-steps h5 {
            color: #155724;
            margin-bottom: 15px;
        }
        
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .next-steps li {
            color: #155724;
            margin-bottom: 8px;
        }
        
        @media (max-width: 768px) {
            .confirmation-header {
                padding: 30px 20px;
            }
            
            .confirmation-header h1 {
                font-size: 2rem;
            }
            
            .confirmation-content {
                padding: 20px;
            }
            
            .detail-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="confirmation-header">
                <i class="fas fa-check-circle"></i>
                <h1>Booking Confirmed!</h1>
                <p class="mb-0">Thank you for choosing Harar Ras Hotel</p>
            </div>
            
            <div class="confirmation-content">
                <div class="booking-details">
                    <h4><i class="fas fa-file-alt me-2"></i>Booking Details</h4>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label">Booking Reference</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['booking_reference']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Guest Name</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['customer_name']); ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <?php if ($booking_data['booking_type'] == 'food_order'): ?>
                        <div class="detail-item">
                            <div class="detail-label">Order Type</div>
                            <div class="detail-value"><?php echo $booking_data['table_reservation'] ? 'Dine-in' : 'Takeaway'; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Table Reserved</div>
                            <div class="detail-value"><?php echo $booking_data['table_reservation'] ? 'Yes' : 'No'; ?></div>
                        </div>
                        <?php elseif (in_array($booking_data['booking_type'], ['spa_service', 'laundry_service'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Service Type</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['room_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Service Name</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['service_name'] ?? 'N/A'); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="detail-item">
                            <div class="detail-label">Room</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['room_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Room Number</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['room_number']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-row">
                        <?php if ($booking_data['booking_type'] == 'food_order'): ?>
                        <div class="detail-item">
                            <div class="detail-label">Reservation Date</div>
                            <div class="detail-value"><?php echo !empty($booking_data['reservation_date']) ? date('M j, Y', strtotime($booking_data['reservation_date'])) : 'N/A'; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Reservation Time</div>
                            <div class="detail-value"><?php echo !empty($booking_data['reservation_time']) ? date('g:i A', strtotime($booking_data['reservation_time'])) : 'N/A'; ?></div>
                        </div>
                        <?php elseif (in_array($booking_data['booking_type'], ['spa_service', 'laundry_service'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Service Date</div>
                            <div class="detail-value"><?php echo !empty($booking_data['service_date']) ? date('M j, Y', strtotime($booking_data['service_date'])) : 'To be scheduled'; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Service Time</div>
                            <div class="detail-value"><?php echo !empty($booking_data['service_time']) ? date('g:i A', strtotime($booking_data['service_time'])) : 'To be scheduled'; ?></div>
                        </div>
                        <?php else: ?>
                        <div class="detail-item">
                            <div class="detail-label">Check-in Date</div>
                            <div class="detail-value"><?php echo !empty($booking_data['check_in_date']) ? date('M j, Y', strtotime($booking_data['check_in_date'])) : 'N/A'; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Check-out Date</div>
                            <div class="detail-value"><?php echo !empty($booking_data['check_out_date']) ? date('M j, Y', strtotime($booking_data['check_out_date'])) : 'N/A'; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label">Total Amount</div>
                            <div class="detail-value" style="font-size: 1.3em;"><?php echo format_currency($booking_data['total_price']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Payment Status</div>
                            <div class="detail-value">
                                <span class="badge bg-success fs-6">
                                    <i class="fas fa-check me-1"></i>Paid
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="next-steps">
                    <h5><i class="fas fa-list-check me-2"></i>What's Next?</h5>
                    <ul>
                        <li>A confirmation email has been sent to <?php echo htmlspecialchars($booking_data['email']); ?></li>
                        <li>Please arrive at the hotel on your check-in date</li>
                        <li>Bring a valid ID for verification at check-in</li>
                        <li>Contact us if you need to make any changes to your booking</li>
                    </ul>
                    
                    <?php if (!$booking_data['feedback_id']): ?>
                    <div class="alert alert-info mt-3">
                        <h6><i class="fas fa-star me-2"></i>Share Your Experience</h6>
                        <p class="mb-2">Help us improve our service by sharing your feedback!</p>
                        <a href="customer-feedback.php?booking_ref=<?php echo urlencode($booking_data['booking_reference']); ?>&payment_id=<?php echo urlencode($booking_data['payment_reference'] ?? ''); ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-star me-1"></i> Give Feedback
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-check-circle me-2"></i>Thank you for your feedback! We appreciate your input.
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center">
                    <a href="index.php" class="btn btn-primary me-3">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                    <a href="my-bookings.php" class="btn btn-outline-primary">
                        <i class="fas fa-calendar me-2"></i>My Bookings
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>