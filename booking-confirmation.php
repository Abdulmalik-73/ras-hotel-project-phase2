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
                  COALESCE(r.name, '') as room_name,
                  COALESCE(r.room_number, '') as room_number, 
                  CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email,
                  cf.id as feedback_id,
                  sb.service_name, sb.service_date, sb.service_time, sb.quantity as service_quantity,
                  fo.order_reference, fo.table_reservation, fo.reservation_date, 
                  fo.reservation_time, fo.guests as food_guests, fo.special_requests as food_special,
                  fo.id as food_order_id
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
$booking_type = $booking_data['booking_type'] ?? 'room';

// Fetch food order items if food order
$food_items_list = '';
if ($booking_type === 'food_order' && !empty($booking_data['food_order_id'])) {
    $fi = $conn->prepare(
        "SELECT item_name, quantity, price, total_price 
         FROM food_order_items WHERE order_id = ? ORDER BY id"
    );
    $fi->bind_param("i", $booking_data['food_order_id']);
    $fi->execute();
    $fi_result = $fi->get_result();
    $items = [];
    while ($row = $fi_result->fetch_assoc()) {
        $items[] = htmlspecialchars($row['item_name']) . ' x' . $row['quantity'] . ' (ETB ' . number_format($row['price'], 2) . ')';
    }
    $food_items_list = implode('<br>', $items);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        $titles = [
            'room'            => 'Booking Confirmed',
            'food_order'      => 'Order Confirmed',
            'spa_service'     => 'Spa & Wellness Confirmed',
            'laundry_service' => 'Laundry Service Confirmed',
        ];
        echo ($titles[$booking_type] ?? 'Confirmed');
    ?> - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
        }
        .confirmation-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }
        .confirmation-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            overflow: hidden;
            max-width: 560px;
            width: 100%;
            animation: slideUp 0.4s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .confirmation-header {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 20px 24px;
            text-align: center;
        }
        .confirmation-header i {
            font-size: 2.2rem;
            margin-bottom: 8px;
            display: block;
        }
        .confirmation-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .confirmation-header p { font-size: 0.82rem; margin: 0; opacity: 0.9; }
        .confirmation-content { padding: 16px 20px; }
        .booking-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .booking-details h4 {
            color: #2ecc71;
            margin-bottom: 12px;
            font-size: 1rem;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 16px;
            margin-bottom: 6px;
        }
        .detail-item { padding: 4px 0; }
        .detail-label {
            font-weight: 600;
            color: #555;
            font-size: 0.75rem;
            margin-bottom: 2px;
        }
        .detail-value {
            color: #27ae60;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            border: none;
            padding: 9px 22px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.2s;
        }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-outline-primary {
            border: 2px solid #2ecc71;
            color: #2ecc71;
            padding: 9px 22px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.2s;
        }
        .btn-outline-primary:hover { background: #2ecc71; border-color: #2ecc71; color: white; }
        .next-steps {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 14px;
        }
        .next-steps h5 { color: #155724; margin-bottom: 8px; font-size: 0.9rem; }
        .next-steps ul { margin: 0; padding-left: 18px; }
        .next-steps li { color: #155724; margin-bottom: 4px; font-size: 0.82rem; }
        .alert { padding: 10px 14px; font-size: 0.82rem; border-radius: 8px; }
        .alert h6 { font-size: 0.85rem; margin-bottom: 4px; }
        .alert p { margin-bottom: 6px; }
        @media (max-width: 480px) {
            .confirmation-header { padding: 16px; }
            .confirmation-content { padding: 12px 14px; }
            .detail-row { grid-template-columns: 1fr; gap: 4px; }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="confirmation-header">
                <i class="fas fa-check-circle"></i>
                <?php
                $headers = [
                    'room'            => ['title' => __('confirmation.room_confirmed'),    'sub' => __('confirmation.room_sub')],
                    'food_order'      => ['title' => __('confirmation.food_confirmed'),    'sub' => __('confirmation.food_sub')],
                    'spa_service'     => ['title' => __('confirmation.spa_confirmed'),     'sub' => __('confirmation.spa_sub')],
                    'laundry_service' => ['title' => __('confirmation.laundry_confirmed'), 'sub' => __('confirmation.laundry_sub')],
                ];
                $h = $headers[$booking_type] ?? $headers['room'];
                ?>
                <h1><?php echo $h['title']; ?></h1>
                <p class="mb-0"><?php echo $h['sub']; ?> — <?php echo __('confirmation.thank_you'); ?></p>
            </div>
            
            <div class="confirmation-content">
                <div class="booking-details">
                    <?php
                    $detail_icons = [
                        'room'            => 'fa-bed',
                        'food_order'      => 'fa-utensils',
                        'spa_service'     => 'fa-spa',
                        'laundry_service' => 'fa-tshirt',
                    ];
                    $detail_titles = [
                        'room'            => __('confirmation.room_details'),
                        'food_order'      => __('confirmation.food_details'),
                        'spa_service'     => __('confirmation.spa_details'),
                        'laundry_service' => __('confirmation.laundry_details'),
                    ];
                    ?>
                    <h4>
                        <i class="fas <?php echo $detail_icons[$booking_type] ?? 'fa-file-alt'; ?> me-2"></i>
                        <?php echo $detail_titles[$booking_type] ?? 'Booking Details'; ?>
                    </h4>

                    <!-- Reference & Customer — same for all types -->
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label">
                                <?php
                                if ($booking_type === 'food_order') echo __('confirmation.order_reference');
                                elseif (in_array($booking_type, ['spa_service','laundry_service'])) echo __('confirmation.service_reference');
                                else echo __('confirmation.booking_reference');
                                ?>
                            </div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['booking_reference']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.customer_name'); ?></div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['customer_name']); ?></div>
                        </div>
                    </div>

                    <?php if ($booking_type === 'food_order'): ?>
                    <?php if ($food_items_list): ?>
                    <div class="detail-row">
                        <div class="detail-item" style="grid-column:1/-1;">
                            <div class="detail-label"><?php echo __('confirmation.items_ordered'); ?></div>
                            <div class="detail-value" style="font-size:1rem;"><?php echo $food_items_list; ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.order_type'); ?></div>
                            <div class="detail-value"><?php echo $booking_data['table_reservation'] ? __('confirmation.dine_in') : __('confirmation.takeaway'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.table_reserved'); ?></div>
                            <div class="detail-value"><?php echo $booking_data['table_reservation'] ? __('confirmation.yes') : __('confirmation.no'); ?></div>
                        </div>
                    </div>
                    <?php if (!empty($booking_data['reservation_date'])): ?>
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.reservation_date'); ?></div>
                            <div class="detail-value"><?php echo date('M j, Y', strtotime($booking_data['reservation_date'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.reservation_time'); ?></div>
                            <div class="detail-value"><?php echo date('g:i A', strtotime($booking_data['reservation_time'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.guests'); ?></div>
                            <div class="detail-value"><?php echo $booking_data['food_guests']; ?> <?php echo __('confirmation.persons'); ?></div>
                        </div>
                        <?php if (!empty($booking_data['food_special']) && $booking_data['food_special'] !== 'no'): ?>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.special_requests'); ?></div>
                            <div class="detail-value" style="font-size:1rem;"><?php echo htmlspecialchars($booking_data['food_special']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php elseif (in_array($booking_type, ['spa_service', 'laundry_service'])): ?>
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.service'); ?></div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['service_name'] ?? __('confirmation.na')); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.service_type'); ?></div>
                            <div class="detail-value"><?php echo $booking_type === 'spa_service' ? __('confirmation.spa_wellness') : __('confirmation.laundry_service'); ?></div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo $booking_type === 'laundry_service' ? __('confirmation.collection_date') : __('confirmation.service_date'); ?></div>
                            <div class="detail-value"><?php echo !empty($booking_data['service_date']) ? date('M j, Y', strtotime($booking_data['service_date'])) : __('confirmation.to_be_scheduled'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo $booking_type === 'laundry_service' ? __('confirmation.collection_time') : __('confirmation.service_time'); ?></div>
                            <div class="detail-value"><?php echo !empty($booking_data['service_time']) ? date('g:i A', strtotime($booking_data['service_time'])) : __('confirmation.to_be_confirmed'); ?></div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.quantity'); ?></div>
                            <div class="detail-value"><?php echo ($booking_data['service_quantity'] ?? 1) . ' ' . __('confirmation.sessions'); ?></div>
                        </div>
                    </div>

                    <?php else: ?>
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.room'); ?></div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['room_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.room_number'); ?></div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking_data['room_number'] ?: __('confirmation.na')); ?></div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.check_in_date'); ?></div>
                            <div class="detail-value"><?php echo !empty($booking_data['check_in_date']) ? date('M j, Y', strtotime($booking_data['check_in_date'])) : __('confirmation.na'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.check_out_date'); ?></div>
                            <div class="detail-value"><?php echo !empty($booking_data['check_out_date']) ? date('M j, Y', strtotime($booking_data['check_out_date'])) : __('confirmation.na'); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.total_amount'); ?></div>
                            <div class="detail-value"><?php echo format_currency($booking_data['total_price']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo __('confirmation.payment_status'); ?></div>
                            <div class="detail-value">
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i><?php echo __('confirmation.paid'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="next-steps">
                    <h5><i class="fas fa-list-check me-2"></i><?php echo __('confirmation.whats_next'); ?></h5>
                    <ul>
                        <li><?php echo __('confirmation.email_sent'); ?> <?php echo htmlspecialchars($booking_data['email']); ?></li>
                        <?php if ($booking_type === 'food_order'): ?>
                        <li><?php echo __('confirmation.kitchen_preparing'); ?></li>
                        <li><?php echo __('confirmation.will_notify'); ?></li>
                        <li><?php echo __('confirmation.contact_special'); ?></li>
                        <?php elseif ($booking_type === 'spa_service'): ?>
                        <li><?php echo __('confirmation.arrive_early'); ?></li>
                        <li><?php echo __('confirmation.bring_reference'); ?></li>
                        <li><?php echo __('confirmation.contact_reschedule'); ?></li>
                        <?php elseif ($booking_type === 'laundry_service'): ?>
                        <li><?php echo __('confirmation.laundry_collected'); ?></li>
                        <li><?php echo __('confirmation.items_returned'); ?></li>
                        <li><?php echo __('confirmation.contact_changes'); ?></li>
                        <?php else: ?>
                        <li><?php echo __('confirmation.arrive_checkin'); ?></li>
                        <li><?php echo __('confirmation.bring_id'); ?></li>
                        <li><?php echo __('confirmation.contact_booking'); ?></li>
                        <?php endif; ?>
                    </ul>
                    
                    <?php if (!$booking_data['feedback_id']): ?>
                    <div class="alert alert-info mt-3">
                        <h6><i class="fas fa-star me-2"></i><?php echo __('confirmation.share_experience'); ?></h6>
                        <p class="mb-2"><?php echo __('confirmation.feedback_help'); ?></p>
                        <a href="customer-feedback.php?booking_ref=<?php echo urlencode($booking_data['booking_reference']); ?>&payment_id=<?php echo urlencode($booking_data['payment_reference'] ?? ''); ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-star me-1"></i> <?php echo __('confirmation.give_feedback'); ?>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-check-circle me-2"></i><?php echo __('confirmation.feedback_thanks'); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center">
                    <a href="index.php" class="btn btn-primary me-3">
                        <i class="fas fa-home me-2"></i><?php echo __('confirmation.back_to_home'); ?>
                    </a>
                    <a href="my-bookings.php" class="btn btn-outline-primary">
                        <i class="fas fa-calendar me-2"></i><?php echo __('confirmation.my_bookings'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>