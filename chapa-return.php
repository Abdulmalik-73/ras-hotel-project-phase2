<?php
/**
 * Chapa Return Page
 * Chapa redirects user here after payment (return_url)
 * Verifies payment → updates DB → shows result
 */
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$tx_ref = trim($_GET['tx_ref'] ?? '');
if (empty($tx_ref)) {
    header('Location: my-bookings.php');
    exit;
}

// ── Step 1: Verify with Chapa API ────────────────────────────────────────────
$chapa_base = defined('CHAPA_BASE_URL') ? CHAPA_BASE_URL : (getenv('CHAPA_BASE_URL') ?: 'https://api.chapa.co/v1');
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : (getenv('CHAPA_SECRET_KEY') ?: '');
$verify_url = $chapa_base . '/transaction/verify/' . urlencode($tx_ref);
$ch = curl_init($verify_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $chapa_key,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

// Log for debugging
error_log("Chapa verify [$tx_ref] HTTP $http_code: $response");

$result     = json_decode($response, true);
$pay_status = $result['data']['status'] ?? 'failed';
$json_resp  = json_encode($result);
$booking_id = null;
$amount_paid = $result['data']['amount'] ?? 0;

// ── Step 2: Update payments table ────────────────────────────────────────────
$db_status = ($pay_status === 'success') ? 'paid' : 'failed';

$upd_pay = $conn->prepare(
    "UPDATE payments SET status = ?, chapa_response = ?, updated_at = NOW() WHERE tx_ref = ?"
);
$upd_pay->bind_param("sss", $db_status, $json_resp, $tx_ref);
$upd_pay->execute();

// ── Step 3: If success → update booking ──────────────────────────────────────
if ($pay_status === 'success') {
    $row = $conn->prepare("SELECT booking_id FROM payments WHERE tx_ref = ? LIMIT 1");
    $row->bind_param("s", $tx_ref);
    $row->execute();
    $payment_row = $row->get_result()->fetch_assoc();

    if ($payment_row) {
        $booking_id = (int)$payment_row['booking_id'];

        $upd_book = $conn->prepare(
            "UPDATE bookings
             SET payment_status      = 'paid',
                 verification_status = 'verified',
                 status              = 'confirmed',
                 verified_at         = NOW()
             WHERE id = ? AND user_id = ?"
        );
        $upd_book->bind_param("ii", $booking_id, $_SESSION['user_id']);
        $upd_book->execute();

        // ── Auto-set room to occupied if room booking ─────────────────────
        $btype_q = $conn->prepare("SELECT booking_type, room_id FROM bookings WHERE id = ?");
        $btype_q->bind_param("i", $booking_id);
        $btype_q->execute();
        $btype_row = $btype_q->get_result()->fetch_assoc();

        if ($btype_row && $btype_row['booking_type'] === 'room' && $btype_row['room_id']) {
            $room_upd = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
            $room_upd->bind_param("i", $btype_row['room_id']);
            $room_upd->execute();
        }

        // ── Create staff notification ─────────────────────────────────────
        try {
            // Fetch booking + customer details for notification
            $nq = $conn->prepare(
                "SELECT b.booking_reference, b.booking_type, b.total_price,
                        CONCAT(u.first_name,' ',u.last_name) as customer_name, u.email,
                        COALESCE(r.name,'') as room_name, COALESCE(r.room_number,'') as room_number,
                        fo.order_reference, fo.reservation_date, fo.reservation_time,
                        sb.service_name, sb.service_date, sb.service_time
                 FROM bookings b
                 JOIN users u ON b.user_id = u.id
                 LEFT JOIN rooms r ON b.room_id = r.id
                 LEFT JOIN food_orders fo ON fo.booking_id = b.id
                 LEFT JOIN service_bookings sb ON sb.booking_id = b.id
                 WHERE b.id = ?"
            );
            $nq->bind_param("i", $booking_id);
            $nq->execute();
            $nd = $nq->get_result()->fetch_assoc();

            if ($nd) {
                $btype   = $nd['booking_type'];
                $ref     = $nd['booking_reference'];
                $cname   = $nd['customer_name'];
                $email   = $nd['email'];
                $amount  = $nd['total_price'];

                // Build service detail string
                switch ($btype) {
                    case 'room':
                        $detail = $nd['room_name'] . ($nd['room_number'] ? ' #'.$nd['room_number'] : '');
                        break;
                    case 'food_order':
                        $detail = 'Order: ' . ($nd['order_reference'] ?? $ref);
                        if ($nd['reservation_date']) {
                            $detail .= ' | ' . date('M j, Y', strtotime($nd['reservation_date']));
                            $detail .= ' ' . date('g:i A', strtotime($nd['reservation_time']));
                        }
                        break;
                    case 'spa_service':
                        $detail = 'Spa: ' . ($nd['service_name'] ?? 'Spa & Wellness');
                        if ($nd['service_date']) {
                            $detail .= ' | ' . date('M j, Y', strtotime($nd['service_date']));
                            $detail .= ' ' . date('g:i A', strtotime($nd['service_time']));
                        }
                        break;
                    case 'laundry_service':
                        $detail = 'Laundry: ' . ($nd['service_name'] ?? 'Laundry Service');
                        if ($nd['service_date']) {
                            $detail .= ' | ' . date('M j, Y', strtotime($nd['service_date']));
                            $detail .= ' ' . date('g:i A', strtotime($nd['service_time']));
                        }
                        break;
                    default:
                        $detail = $ref;
                }

                $ins_n = $conn->prepare(
                    "INSERT INTO staff_notifications
                     (booking_id, booking_reference, booking_type, customer_name,
                      customer_email, amount, service_detail)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $ins_n->bind_param("issssds",
                    $booking_id, $ref, $btype, $cname, $email, $amount, $detail
                );
                $ins_n->execute();
            }
        } catch (Throwable $e) {
            error_log("Staff notification insert error: " . $e->getMessage());
        }

        // ── Send confirmation email ──────────────────────────────────────
        try {
            require_once __DIR__ . '/includes/Mailer.php';

            // Fetch booking + user + room
            $eq = $conn->prepare(
                "SELECT b.*, COALESCE(r.name,'') as room_name, COALESCE(r.room_number,'') as room_number,
                        u.first_name, u.last_name, u.email
                 FROM bookings b
                 LEFT JOIN rooms r ON b.room_id = r.id
                 JOIN users u ON b.user_id = u.id
                 WHERE b.id = ?"
            );
            $eq->bind_param("i", $booking_id);
            $eq->execute();
            $email_booking = $eq->get_result()->fetch_assoc();

            if ($email_booking && !empty($email_booking['email'])) {
                $btype = $email_booking['booking_type'] ?? 'room';
                $extra = [];

                // Fetch type-specific details for email
                if ($btype === 'food_order') {
                    $fq = $conn->prepare(
                        "SELECT fo.*, GROUP_CONCAT(foi.item_name ORDER BY foi.id SEPARATOR ', ') as items_list
                         FROM food_orders fo
                         LEFT JOIN food_order_items foi ON foi.order_id = fo.id
                         WHERE fo.booking_id = ? GROUP BY fo.id LIMIT 1"
                    );
                    $fq->bind_param("i", $booking_id);
                    $fq->execute();
                    $extra = $fq->get_result()->fetch_assoc() ?? [];

                } elseif (in_array($btype, ['spa_service', 'laundry_service'])) {
                    $sq = $conn->prepare(
                        "SELECT * FROM service_bookings WHERE booking_id = ? LIMIT 1"
                    );
                    $sq->bind_param("i", $booking_id);
                    $sq->execute();
                    $extra = $sq->get_result()->fetch_assoc() ?? [];
                }

                $subject  = 'Payment Confirmed – ' . $email_booking['booking_reference'] . ' | Harar Ras Hotel';
                $htmlBody = Mailer::paymentConfirmedHtml($email_booking, $tx_ref, $extra);
                $email_result = Mailer::send(
                    $email_booking['email'],
                    $email_booking['first_name'] . ' ' . $email_booking['last_name'],
                    $subject,
                    $htmlBody
                );
                error_log("Chapa email result: " . json_encode($email_result));
            }
        } catch (Throwable $e) {
            error_log("Chapa email error: " . $e->getMessage());
        }
    }
} else {
    // Try to get booking_id even on failure (for "Try Again" link)
    $row = $conn->prepare("SELECT booking_id FROM payments WHERE tx_ref = ? LIMIT 1");
    $row->bind_param("s", $tx_ref);
    $row->execute();
    $payment_row = $row->get_result()->fetch_assoc();
    if ($payment_row) $booking_id = (int)$payment_row['booking_id'];
}

// ── Step 4: Fetch full booking details for display ───────────────────────────
$booking = null;
$food_order = null;
if ($booking_id) {
    $bq = $conn->prepare(
        "SELECT b.*,
                COALESCE(r.name, '') as room_name,
                COALESCE(r.room_number, '') as room_number,
                u.first_name, u.last_name, u.email
         FROM bookings b
         LEFT JOIN rooms r ON b.room_id = r.id
         JOIN users u ON b.user_id = u.id
         WHERE b.id = ? AND b.user_id = ?"
    );
    $bq->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $bq->execute();
    $booking = $bq->get_result()->fetch_assoc();

    // If food order, fetch order details + items
    if ($booking && $booking['booking_type'] === 'food_order') {
        $fq = $conn->prepare(
            "SELECT fo.*, GROUP_CONCAT(foi.item_name ORDER BY foi.id SEPARATOR ', ') as items_list,
                    SUM(foi.quantity) as total_items
             FROM food_orders fo
             LEFT JOIN food_order_items foi ON foi.order_id = fo.id
             WHERE fo.booking_id = ?
             GROUP BY fo.id
             LIMIT 1"
        );
        $fq->bind_param("i", $booking_id);
        $fq->execute();
        $food_order = $fq->get_result()->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pay_status === 'success' ? 'Payment Successful' : 'Payment Failed'; ?> - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f4f6f9; }
        .result-card {
            max-width: 560px;
            margin: 60px auto;
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 18px 20px;
            margin: 20px 0;
            text-align: left;
        }
        .detail-grid .label { font-size: .78rem; color: #6c757d; margin-bottom: 2px; }
        .detail-grid .value { font-weight: 600; font-size: .95rem; word-break: break-all; }
        .btn-chapa-success {
            background: linear-gradient(135deg, #1DBF73, #17a85f);
            color: #fff; border: none; border-radius: 10px;
            padding: 13px; font-size: 1rem; font-weight: 600; width: 100%;
        }
        .btn-chapa-success:hover { opacity: .9; color: #fff; }
        .success-icon { animation: popIn .5s cubic-bezier(.175,.885,.32,1.275); }
        @keyframes popIn {
            0%   { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="result-card card">
        <div class="card-body p-4 p-md-5 text-center">

            <?php if ($pay_status === 'success'): ?>
            <!-- ══ SUCCESS ══ -->
            <div class="success-icon mb-3">
                <i class="fas fa-check-circle fa-5x" style="color:#1DBF73;"></i>
            </div>
            <h3 class="fw-bold mb-1" style="color:#1DBF73;">Payment Successful!</h3>
            <p class="text-muted mb-0">
                <?php if ($booking && $booking['booking_type'] === 'food_order'): ?>
                    Your payment has been verified and your food order is confirmed.
                <?php elseif ($booking && $booking['booking_type'] === 'spa_service'): ?>
                    Your payment has been verified and your spa service is confirmed.
                <?php elseif ($booking && $booking['booking_type'] === 'laundry_service'): ?>
                    Your payment has been verified and your laundry service is confirmed.
                <?php else: ?>
                    Your payment has been verified and your booking is confirmed.
                <?php endif; ?>
            </p>
            <?php if ($booking): ?>
            <div class="detail-grid">
                <?php
                $is_food   = $booking['booking_type'] === 'food_order';
                $is_spa    = $booking['booking_type'] === 'spa_service';
                $is_laundry = $booking['booking_type'] === 'laundry_service';
                $is_room   = $booking['booking_type'] === 'room';
                ?>

                <div>
                    <div class="label">
                        <?php echo $is_food ? 'Order Reference' : 'Booking Reference'; ?>
                    </div>
                    <div class="value"><?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                </div>

                <div>
                    <div class="label">Amount Paid</div>
                    <div class="value" style="color:#1DBF73;">
                        ETB <?php echo number_format($booking['total_price'], 2); ?>
                    </div>
                </div>

                <?php if ($is_room): ?>
                <div>
                    <div class="label">Room</div>
                    <div class="value">
                        <?php echo htmlspecialchars($booking['room_name']); ?>
                        <?php if (!empty($booking['room_number'])): ?>
                            <span class="text-muted fw-normal">(<?php echo htmlspecialchars($booking['room_number']); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($is_food): ?>
                <div>
                    <div class="label">Order Type</div>
                    <div class="value">
                        <i class="fas fa-utensils text-warning me-1"></i> Food Order
                    </div>
                </div>

                <?php elseif ($is_spa): ?>
                <div>
                    <div class="label">Service</div>
                    <div class="value">
                        <i class="fas fa-spa text-info me-1"></i> Spa & Wellness
                    </div>
                </div>

                <?php elseif ($is_laundry): ?>
                <div>
                    <div class="label">Service</div>
                    <div class="value">
                        <i class="fas fa-tshirt text-primary me-1"></i> Laundry Service
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($is_food && $food_order): ?>
                <div>
                    <div class="label">Order Reference</div>
                    <div class="value"><?php echo htmlspecialchars($food_order['order_reference']); ?></div>
                </div>

                <?php if ($food_order['items_list']): ?>
                <div style="grid-column: 1 / -1;">
                    <div class="label">Items Ordered</div>
                    <div class="value"><?php echo htmlspecialchars($food_order['items_list']); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($food_order['reservation_date']): ?>
                <div>
                    <div class="label">Reservation Date</div>
                    <div class="value"><?php echo date('M d, Y', strtotime($food_order['reservation_date'])); ?></div>
                </div>
                <div>
                    <div class="label">Guests</div>
                    <div class="value"><?php echo $food_order['guests']; ?> person(s)</div>
                </div>
                <?php endif; ?>

                <?php elseif ($is_room && $booking['check_in_date']): ?>
                <div>
                    <div class="label">Check-in</div>
                    <div class="value"><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></div>
                </div>
                <div>
                    <div class="label">Check-out</div>
                    <div class="value"><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></div>
                </div>
                <?php endif; ?>

                <div>
                    <div class="label">Customer</div>
                    <div class="value"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></div>
                </div>

                <div>
                    <div class="label">Payment Status</div>
                    <div class="value">
                        <span class="badge bg-success px-2 py-1">
                            <i class="fas fa-check-circle me-1"></i> Paid
                        </span>
                    </div>
                </div>

            </div>
            <?php endif; ?>

            <!-- What's Next -->
            <?php if ($booking): ?>
            <div style="background:#f0fdf4;border-radius:10px;padding:18px 20px;margin:20px 0;text-align:left;">
                <p class="fw-bold mb-2" style="color:#166534;">
                    <i class="fas fa-list-check me-2"></i> What's Next?
                </p>
                <ul style="margin:0;padding-left:18px;color:#555;font-size:14px;line-height:2;">
                    <li>A confirmation email has been sent to
                        <strong><?php echo htmlspecialchars($booking['email']); ?></strong>
                    </li>
                    <?php if ($booking['booking_type'] === 'room'): ?>
                    <li>Please arrive at the hotel on your check-in date</li>
                    <li>Bring a valid ID for verification at check-in</li>
                    <li>Contact us if you need to make any changes to your booking</li>
                    <?php elseif ($booking['booking_type'] === 'food_order'): ?>
                    <li>Your order is being prepared</li>
                    <li>You will be notified when it is ready</li>
                    <?php else: ?>
                    <li>Our team will contact you to confirm your service schedule</li>
                    <li>Bring your booking reference for verification</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="d-grid gap-2 mt-3">
                <button class="btn-chapa-success" onclick="location.href='my-bookings.php'">
                    <i class="fas fa-list me-2"></i> View My Bookings
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-1"></i> Back to Home
                </a>
            </div>

            <?php else: ?>
            <!-- ══ FAILED ══ -->
            <div class="mb-3">
                <i class="fas fa-times-circle fa-5x text-danger"></i>
            </div>
            <h3 class="fw-bold text-danger mb-1">Payment Not Completed</h3>
            <p class="text-muted">Your payment could not be verified. Please try again.</p>

            <div class="d-grid gap-2 mt-3">
                <?php if ($booking_id): ?>
                <a href="payment-upload.php?booking=<?php echo $booking_id; ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-redo me-1"></i> Try Again
                </a>
                <?php endif; ?>
                <a href="my-bookings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list me-1"></i> My Bookings
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
