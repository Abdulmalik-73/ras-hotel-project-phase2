<?php
/**
 * Payment Upload Page
 * Shows: Booking Summary → Chapa Online Payment → Screenshot Upload
 */
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$booking_param = $_GET['booking'] ?? 0;
if (!$booking_param) {
    header('Location: my-bookings.php');
    exit;
}

// Resolve booking by ID or reference
if (is_numeric($booking_param)) {
    $q = "SELECT b.*, COALESCE(r.name,'') as room_name,
                 COALESCE(r.room_number,'') as room_number,
                 u.email, u.first_name, u.last_name
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ? AND b.user_id = ?";
    $s = $conn->prepare($q);
    $s->bind_param("ii", $booking_param, $_SESSION['user_id']);
} else {
    $q = "SELECT b.*, COALESCE(r.name,'') as room_name,
                 COALESCE(r.room_number,'') as room_number,
                 u.email, u.first_name, u.last_name
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          WHERE b.booking_reference = ? AND b.user_id = ?";
    $s = $conn->prepare($q);
    $s->bind_param("si", $booking_param, $_SESSION['user_id']);
}
$s->execute();
$booking = $s->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: my-bookings.php?error=not_found');
    exit;
}

if ($booking['payment_status'] === 'paid') {
    header('Location: my-bookings.php?paid=1');
    exit;
}

$booking_id   = $booking['id'];
$booking_type = $booking['booking_type'] ?? 'room';

// ── Fetch type-specific extra details ────────────────────────────────────────
$food_order      = null;
$service_booking = null;

if ($booking_type === 'food_order') {
    $fq = $conn->prepare(
        "SELECT fo.*, GROUP_CONCAT(foi.item_name ORDER BY foi.id SEPARATOR ', ') as items_list
         FROM food_orders fo
         LEFT JOIN food_order_items foi ON foi.order_id = fo.id
         WHERE fo.booking_id = ? GROUP BY fo.id LIMIT 1"
    );
    $fq->bind_param("i", $booking_id);
    $fq->execute();
    $food_order = $fq->get_result()->fetch_assoc();

} elseif (in_array($booking_type, ['spa_service', 'laundry_service'])) {
    $sq = $conn->prepare(
        "SELECT * FROM service_bookings WHERE booking_id = ? LIMIT 1"
    );
    $sq->bind_param("i", $booking_id);
    $sq->execute();
    $service_booking = $sq->get_result()->fetch_assoc();
}

// ── Handle screenshot upload (POST) ─────────────────────────────────────────
$upload_success = false;
$upload_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_screenshot'])) {
    $payment_method = 'screenshot';

    if (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = "Please select a payment screenshot.";
    } else {
        $file     = $_FILES['payment_screenshot'];
        $allowed  = ['image/jpeg','image/jpg','image/png'];
        $max_size = 5 * 1024 * 1024;

        if (!in_array($file['type'], $allowed)) {
            $upload_error = "Only JPG and PNG images are allowed.";
        } elseif ($file['size'] > $max_size) {
            $upload_error = "File too large. Maximum 5MB.";
        } else {
            $dir = __DIR__ . '/uploads/payment_screenshots/';
            if (!file_exists($dir)) mkdir($dir, 0755, true);

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'pay_' . $booking['booking_reference'] . '_' . time() . '.' . $ext;
            $db_path  = 'uploads/payment_screenshots/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $ins = $conn->prepare(
                    "INSERT INTO payment_verifications
                     (booking_id, user_id, booking_reference, payment_method,
                      transaction_reference, amount, screenshot_path, status)
                     VALUES (?, ?, ?, ?, '', ?, ?, 'pending')"
                );
                $ins->bind_param("iissds",
                    $booking_id, $_SESSION['user_id'],
                    $booking['booking_reference'], $payment_method,
                    $booking['total_price'], $db_path
                );

                if ($ins->execute()) {
                    $upd = $conn->prepare(
                        "UPDATE bookings SET verification_status='pending_verification',
                         screenshot_path=?, screenshot_uploaded_at=NOW() WHERE id=?"
                    );
                    $upd->bind_param("si", $db_path, $booking_id);
                    $upd->execute();
                    $upload_success = true;
                } else {
                    @unlink($dir . $filename);
                    $upload_error = "Failed to save. Please try again.";
                }
            } else {
                $upload_error = "Upload failed. Please try again.";
            }
        }
    }
}

// Check if screenshot already submitted
$already_submitted = ($booking['verification_status'] === 'pending_verification');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - <?php
        $titles = ['room'=>'Room Booking','food_order'=>'Food Order','spa_service'=>'Spa Service','laundry_service'=>'Laundry Service'];
        echo ($titles[$booking_type] ?? 'Payment') . ' — ' . htmlspecialchars($booking['booking_reference']);
    ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f4f6f9; }
        .page-wrap { max-width: 720px; margin: 0 auto; }
        .section-card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 1.5rem; }
        .section-header { border-radius: 14px 14px 0 0; padding: 1rem 1.4rem; font-weight: 600; font-size: 1rem; }
        .divider-or {
            display: flex; align-items: center; gap: 12px;
            color: #adb5bd; font-size: .85rem; margin: 1.5rem 0;
        }
        .divider-or::before, .divider-or::after {
            content: ''; flex: 1; height: 1px; background: #dee2e6;
        }
        /* Chapa button */
        .btn-chapa {
            background: linear-gradient(135deg, #1DBF73, #17a85f);
            color: #fff; border: none; border-radius: 10px;
            padding: 14px 24px; font-size: 1.05rem; font-weight: 600;
            width: 100%; transition: opacity .2s;
        }
        .btn-chapa:hover { opacity: .9; color: #fff; }
        .btn-chapa:disabled { opacity: .6; cursor: not-allowed; }
        /* Upload area */
        .upload-area {
            border: 2px dashed #0d6efd; border-radius: 12px;
            padding: 36px 20px; text-align: center;
            background: #f0f6ff; cursor: pointer; transition: all .25s;
        }
        .upload-area:hover, .upload-area.dragover { background: #dbeafe; border-color: #0056b3; }
        .preview-img { max-width: 100%; max-height: 240px; border-radius: 8px; margin-top: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.12); }
        /* Bank grid */
        .bank-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .bank-item { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px; padding: 12px 14px; }
        .bank-item h6 { margin-bottom: 3px; font-size: .9rem; }
        .bank-item p  { margin: 0; font-size: .82rem; color: #555; }
        @media(max-width:576px){ .bank-grid{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="py-5">
<div class="container">
<div class="page-wrap">

    <?php if ($upload_success): ?>
    <!-- ── SCREENSHOT SUCCESS ── -->
    <div class="section-card card text-center py-5">
        <div class="card-body">
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h4 class="text-success">Screenshot Submitted!</h4>
            <p class="text-muted">Our staff will verify your payment within 24 hours and notify you by email.</p>
            <a href="my-bookings.php" class="btn btn-primary mt-2">
                <i class="fas fa-list me-1"></i> View My Bookings
            </a>
        </div>
    </div>

    <?php else: ?>

    <!-- ══════════════════════════════════════════
         1. BOOKING SUMMARY
    ══════════════════════════════════════════ -->
    <?php
    // Labels and icons per type
    $type_config = [
        'room'            => ['icon'=>'fa-bed',        'color'=>'bg-primary',  'label'=>__('booking.booking_summary'),     'ref_label'=>__('my_bookings.booking_reference')],
        'food_order'      => ['icon'=>'fa-utensils',   'color'=>'bg-warning',  'label'=>__('my_bookings.title'),       'ref_label'=>__('my_bookings.booking_reference')],
        'spa_service'     => ['icon'=>'fa-spa',        'color'=>'bg-info',     'label'=>'Spa & Wellness',   'ref_label'=>__('my_bookings.booking_reference')],
        'laundry_service' => ['icon'=>'fa-tshirt',     'color'=>'bg-secondary','label'=>'Laundry Service',  'ref_label'=>__('my_bookings.booking_reference')],
    ];
    $tc = $type_config[$booking_type] ?? $type_config['room'];
    ?>
    <div class="section-card card">
        <div class="section-header <?php echo $tc['color']; ?> text-white">
            <i class="fas <?php echo $tc['icon']; ?> me-2"></i>
            <?php echo $tc['label']; ?>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-sm-6">
                    <p class="mb-2">
                        <strong><?php echo $tc['ref_label']; ?>:</strong><br>
                        <span class="text-primary fw-bold"><?php echo htmlspecialchars($booking['booking_reference']); ?></span>
                    </p>
                    <p class="mb-2">
                        <strong><?php echo __('common.customer'); ?>:</strong><br>
                        <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                    </p>

                    <?php if ($booking_type === 'room'): ?>
                        <p class="mb-0">
                            <strong><?php echo __('confirmation.room'); ?>:</strong><br>
                            <?php echo htmlspecialchars($booking['room_name']); ?>
                            <?php if (!empty($booking['room_number'])): ?>
                                (<?php echo htmlspecialchars($booking['room_number']); ?>)
                            <?php endif; ?>
                        </p>

                    <?php elseif ($booking_type === 'food_order' && $food_order): ?>
                        <p class="mb-0">
                            <strong><?php echo __('confirmation.items_ordered'); ?>:</strong><br>
                            <?php echo htmlspecialchars($food_order['items_list'] ?? 'Food Order'); ?>
                        </p>

                    <?php elseif ($booking_type === 'spa_service' && $service_booking): ?>
                        <p class="mb-0">
                            <strong><?php echo __('confirmation.service'); ?>:</strong><br>
                            <?php echo htmlspecialchars($service_booking['service_name']); ?>
                        </p>

                    <?php elseif ($booking_type === 'laundry_service' && $service_booking): ?>
                        <p class="mb-0">
                            <strong><?php echo __('confirmation.service'); ?>:</strong><br>
                            <?php echo htmlspecialchars($service_booking['service_name']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="col-sm-6 mt-3 mt-sm-0">
                    <?php if ($booking_type === 'room' && $booking['check_in_date']): ?>
                        <p class="mb-2">
                            <strong><?php echo __('my_bookings.check_in'); ?>:</strong><br>
                            <?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?>
                        </p>
                        <p class="mb-2">
                            <strong><?php echo __('my_bookings.check_out'); ?>:</strong><br>
                            <?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?>
                        </p>

                    <?php elseif ($booking_type === 'food_order' && $food_order): ?>
                        <?php if ($food_order['reservation_date']): ?>
                        <p class="mb-2">
                            <strong><?php echo __('confirmation.reservation_date'); ?>:</strong><br>
                            <?php echo date('M d, Y', strtotime($food_order['reservation_date'])); ?>
                        </p>
                        <p class="mb-2">
                            <strong><?php echo __('confirmation.reservation_time'); ?>:</strong><br>
                            <?php echo date('g:i A', strtotime($food_order['reservation_time'])); ?>
                        </p>
                        <?php endif; ?>
                        <p class="mb-2">
                            <strong><?php echo __('confirmation.guests'); ?>:</strong><br>
                            <?php echo $food_order['guests']; ?> person(s)
                        </p>

                    <?php elseif (in_array($booking_type, ['spa_service','laundry_service']) && $service_booking): ?>
                        <p class="mb-2">
                            <strong><?php echo __('confirmation.service_date'); ?>:</strong><br>
                            <?php echo date('M d, Y', strtotime($service_booking['service_date'])); ?>
                        </p>
                        <p class="mb-2">
                            <strong><?php echo __('confirmation.service_time'); ?>:</strong><br>
                            <?php echo date('g:i A', strtotime($service_booking['service_time'])); ?>
                        </p>
                    <?php endif; ?>

                    <p class="mb-0">
                        <strong><?php echo __('my_bookings.total_amount'); ?>:</strong><br>
                        <span class="fs-5 fw-bold text-success">
                            ETB <?php echo number_format($booking['total_price'], 2); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════
         2. CHAPA ONLINE PAYMENT
    ══════════════════════════════════════════ -->
    <div class="section-card card">
        <div class="section-header" style="background:#1DBF73; color:#fff;">
            <div><i class="fas fa-bolt me-2"></i> Pay Online with Chapa</div>
            <div style="margin-top:8px; font-size:0.85rem; font-weight:400; opacity:0.95;">
                <span style="margin-right:12px;">
                    <i class="fas fa-phone-alt me-1"></i>
                    <span onclick="copyPhone('0900112233', this)" 
                          style="cursor:pointer; text-decoration:underline; text-underline-offset:3px;"
                          title="Click to copy">0900112233</span>
                </span>
                <span style="opacity:0.7; margin-right:12px;">|</span>
                <span>
                    <i class="fas fa-phone-alt me-1"></i>
                    <span onclick="copyPhone('0900123456', this)"
                          style="cursor:pointer; text-decoration:underline; text-underline-offset:3px;"
                          title="Click to copy">0900123456</span>
                </span>
            </div>
        </div>
        <div class="card-body text-center py-4">
            <p class="text-muted mb-4">
                Pay instantly using <strong>Telebirr, CBE, Awash, Amole</strong> and more — powered by Chapa.
            </p>
            <button class="btn-chapa" id="chapaBtn" onclick="initChapa()">
                <i class="fas fa-credit-card me-2"></i>
                Pay ETB <?php echo number_format($booking['total_price'], 2); ?> with Chapa
            </button>
            <div id="chapaMsg" class="mt-3" style="display:none;"></div>
            <p class="text-muted small mt-3 mb-0">
                <i class="fas fa-lock me-1"></i> Secure payment powered by
                <strong>Chapa</strong> (Sandbox Mode)
            </p>
        </div>
    </div>

    <div class="text-center mt-2 mb-4">
        <a href="my-bookings.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> <?php echo __('my_bookings.back_to_home'); ?>
        </a>
    </div>

    <?php endif; ?>

</div><!-- /page-wrap -->
</div><!-- /container -->
</section>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function initChapa() {
    const btn = document.getElementById('chapaBtn');
    const msg = document.getElementById('chapaMsg');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing payment...';
    msg.style.display = 'none';

    const fd = new FormData();
    fd.append('booking_id', <?php echo $booking_id; ?>);

    fetch('api/chapa/initiate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.checkout_url) {
                msg.innerHTML = '<div class="alert alert-info"><i class="fas fa-external-link-alt me-1"></i> Redirecting to Chapa checkout...</div>';
                msg.style.display = 'block';
                setTimeout(() => { window.location.href = data.checkout_url; }, 800);
            } else {
                msg.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.message || 'Failed to initialize payment.') + '</div>';
                msg.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-credit-card me-2"></i> Pay ETB <?php echo number_format($booking['total_price'], 2); ?> with Chapa';
            }
        })
        .catch(() => {
            msg.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
            msg.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-credit-card me-2"></i> Pay ETB <?php echo number_format($booking['total_price'], 2); ?> with Chapa';
        });
}
</script>
</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Chapa initiation ─────────────────────────────────────────────────────────
function initChapa() {
    const btn = document.getElementById('chapaBtn');
    const msg = document.getElementById('chapaMsg');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing payment...';
    msg.style.display = 'none';

    const fd = new FormData();
    fd.append('booking_id', <?php echo $booking_id; ?>);

    fetch('api/chapa/initiate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.checkout_url) {
                msg.innerHTML = '<div class="alert alert-info"><i class="fas fa-external-link-alt me-1"></i> Redirecting to Chapa checkout...</div>';
                msg.style.display = 'block';
                setTimeout(() => { window.location.href = data.checkout_url; }, 800);
            } else {
                msg.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.message || 'Failed to initialize payment.') + '</div>';
                msg.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-credit-card me-2"></i> Pay ETB <?php echo number_format($booking['total_price'], 2); ?> with Chapa';
            }
        })
        .catch(() => {
            msg.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
            msg.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-credit-card me-2"></i> Pay ETB <?php echo number_format($booking['total_price'], 2); ?> with Chapa';
        });
}

// ── Screenshot upload area ───────────────────────────────────────────────────
const uploadArea       = document.getElementById('uploadArea');
const fileInput        = document.getElementById('fileInput');
const previewContainer = document.getElementById('previewContainer');

if (uploadArea) {
    uploadArea.addEventListener('click', () => fileInput.click());
    uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
    uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
    uploadArea.addEventListener('drop', e => {
        e.preventDefault(); uploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; previewFile(e.dataTransfer.files[0]); }
    });
    fileInput.addEventListener('change', e => { if (e.target.files.length) previewFile(e.target.files[0]); });
}

function previewFile(file) {
    if (!file?.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
        previewContainer.innerHTML = `
            <div class="text-center mt-3">
                <img src="${e.target.result}" class="preview-img" alt="Preview">
                <p class="mt-2 text-success small"><i class="fas fa-check-circle"></i> ${file.name}</p>
            </div>`;
    };
    reader.readAsDataURL(file);
}

function copyPhone(number, el) {
    navigator.clipboard.writeText(number).then(function() {
        var orig = el.textContent;
        el.textContent = 'Copied!';
        el.style.fontWeight = 'bold';
        setTimeout(function() {
            el.textContent = orig;
            el.style.fontWeight = '';
        }, 1500);
    }).catch(function() {
        // Fallback for older browsers
        var ta = document.createElement('textarea');
        ta.value = number;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        var orig = el.textContent;
        el.textContent = 'Copied!';
        setTimeout(function() { el.textContent = orig; }, 1500);
    });
}
</script>
</body>
</html>
