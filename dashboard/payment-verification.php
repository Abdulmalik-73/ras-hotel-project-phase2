<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check authentication and authorization
require_auth_roles(['admin', 'receptionist', 'manager'], '../login.php');

$user_role = $_SESSION['user_role'] ?? '';

// Role-based back link and sidebar style
$back_link = 'admin.php';
$back_label = 'Admin Dashboard';
$sidebar_gradient = 'linear-gradient(135deg, #007bff 0%, #0056b3 100%)';
$sidebar_title = '<i class="fas fa-user-shield"></i> Admin Panel';

if ($user_role === 'receptionist') {
    $back_link = 'receptionist.php';
    $back_label = 'Reception Dashboard';
    $sidebar_gradient = 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)';
    $sidebar_title = '<i class="fas fa-concierge-bell"></i> Reception Panel';
} elseif ($user_role === 'manager') {
    $back_link = 'manager.php';
    $back_label = 'Manager Dashboard';
    $sidebar_gradient = 'linear-gradient(135deg, #34495e 0%, #2c3e50 100%)';
    $sidebar_title = '<i class="fas fa-user-tie"></i> Manager Panel';
}

// Get filter status
$filter_status = $_GET['status'] ?? 'pending';
$allowed_statuses = ['pending', 'verified', 'rejected', 'all'];
if (!in_array($filter_status, $allowed_statuses)) {
    $filter_status = 'pending';
}

// Build query based on filter
$where_clause = $filter_status === 'all' ? '' : "WHERE pv.status = ?";

// Get payment verifications
try {
    $query = "SELECT 
                pv.*,
                CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                u.email as customer_email,
                u.phone as customer_phone,
                b.booking_reference,
                b.check_in_date,
                b.check_out_date,
                b.total_price as booking_total,
                COALESCE(r.name, 'Service Booking') as room_name,
                COALESCE(r.room_number, 'N/A') as room_number,
                CONCAT(v.first_name, ' ', v.last_name) as verified_by_name
              FROM payment_verifications pv
              JOIN users u ON pv.user_id = u.id
              JOIN bookings b ON pv.booking_id = b.id
              LEFT JOIN rooms r ON b.room_id = r.id
              LEFT JOIN users v ON pv.verified_by = v.id
              " . ($filter_status === 'all' ? '' : "WHERE pv.status = '$filter_status'") . "
              ORDER BY pv.created_at DESC";
    $payments = $conn->query($query);

    $counts_result = $conn->query("SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM payment_verifications");
    $counts = $counts_result ? $counts_result->fetch_assoc() : [];
} catch (Exception $e) {
    $payments = null;
    $counts = [];
}
$counts = array_merge(['pending_count'=>0,'verified_count'=>0,'rejected_count'=>0,'total_count'=>0], $counts ?? []);
// Build sidebar nav links per role
function sidebar_links($role, $active = 'payment-verification') {
    $links = [];
    if ($role === 'admin') {
        $links = [
            ['admin.php',              'fa-tachometer-alt', 'Dashboard'],
            ['manage-users.php',       'fa-users',          'Manage Users'],
            ['manage-bookings.php',    'fa-calendar-check', 'Bookings'],
            ['manage-rooms.php',       'fa-bed',            'Rooms'],
            ['manage-services.php',    'fa-concierge-bell', 'Services'],
            ['view-data.php',          'fa-database',       'View All Data'],
            ['settings.php',           'fa-cog',            'Settings'],
            ['payment-verification.php','fa-shield-alt',    'Payment Verification'],
        ];
    } elseif ($role === 'receptionist') {
        $links = [
            ['receptionist.php',           'fa-tachometer-alt',       'Dashboard Overview'],
            ['customer-checkin.php',        'fa-user-plus',            'Customer Check-In'],
            ['receptionist-checkout.php',   'fa-minus-circle',         'Process Check-out'],
            ['receptionist-rooms.php',      'fa-bed',                  'Manage Rooms'],
            ['receptionist-services.php',   'fa-utensils',             'Manage Foods & Services'],
            ['payment-verification.php',    'fa-shield-alt',           'Payment Verification'],
        ];
    } elseif ($role === 'manager') {
        $links = [
            ['manager.php',              'fa-tachometer-alt', 'Overview'],
            ['manager-bookings.php',     'fa-calendar-check', 'Manage Bookings'],
            ['manager-approve-bill.php', 'fa-check-circle',   'Approve Bill'],
            ['manager-feedback.php',     'fa-star',           'Customer Feedback'],
            ['manager-refund.php',       'fa-undo-alt',       'Refund Management'],
            ['manager-rooms.php',        'fa-bed',            'Room Management'],
            ['manager-staff.php',        'fa-users',          'Staff Management'],
            ['manager-reports.php',      'fa-chart-bar',      'Reports'],
            ['payment-verification.php', 'fa-shield-alt',     'Payment Verification'],
        ];
    }
    return $links;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #f4f6f9; }

        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            padding: 1.5rem 0;
            background: <?php echo $sidebar_gradient; ?>;
            position: sticky;
            top: 0;
        }
        .sidebar-title {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            padding: 0 1.25rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 0.75rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 0.5rem 1.25rem;
            border-radius: 0;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }
        .sidebar .nav-link i { width: 20px; }

        /* Payment screenshot thumbnail */
        .payment-screenshot {
            max-width: 80px;
            max-height: 80px;
            cursor: pointer;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            transition: transform 0.2s, border-color 0.2s;
            object-fit: cover;
        }
        .payment-screenshot:hover {
            transform: scale(1.08);
            border-color: #0d6efd;
        }

        /* Filter tabs */
        .filter-tabs .nav-link { color: #6c757d; }
        .filter-tabs .nav-link.active { color: #0d6efd; font-weight: 600; }

        /* Mobile sidebar */
        @media (max-width: 767px) {
            .sidebar-col {
                position: fixed;
                left: -100%;
                top: 0;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s;
                width: 260px;
                overflow-y: auto;
            }
            .sidebar-col.show { left: 0; box-shadow: 2px 0 10px rgba(0,0,0,0.3); }
            .sidebar-overlay {
                display: none;
                position: fixed; inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1040;
            }
            .sidebar-overlay.show { display: block; }
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-dark" style="background: <?php echo $sidebar_gradient; ?>;">
        <div class="container-fluid">
            <button class="btn btn-outline-light btn-sm me-2 d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel
            </a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="text-white d-none d-md-inline small">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? ucfirst($user_role)); ?>
                    <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($user_role); ?></span>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Mobile sidebar overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar-col" id="sidebar">
                <div class="sidebar">
                    <div class="sidebar-title"><?php echo $sidebar_title; ?></div>
                    <nav class="nav flex-column">
                        <?php foreach (sidebar_links($user_role) as $link): ?>
                        <a href="<?php echo $link[0]; ?>"
                           class="nav-link <?php echo ($link[0] === 'payment-verification.php') ? 'active' : ''; ?>">
                            <i class="fas <?php echo $link[1]; ?> me-2"></i>
                            <?php echo $link[2]; ?>
                            <?php if ($link[0] === 'payment-verification.php' && ($counts['pending_count'] ?? 0) > 0): ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo $counts['pending_count']; ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                        <a href="../logout.php" class="nav-link mt-3">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1"><i class="fas fa-shield-alt text-success"></i> Payment Verification</h4>
                        <p class="text-muted mb-0 small">Review and verify customer payment screenshots</p>
                    </div>
                    <a href="<?php echo $back_link; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> <?php echo $back_label; ?>
                    </a>
                </div>

                <!-- Filter Tabs -->
                <ul class="nav nav-tabs filter-tabs mb-4">
                    <?php
                    $tabs = [
                        'pending'  => ['label' => 'Pending',  'icon' => 'fa-clock',        'badge' => 'bg-warning text-dark', 'count' => $counts['pending_count']],
                        'verified' => ['label' => 'Verified', 'icon' => 'fa-check-circle',  'badge' => 'bg-success',           'count' => $counts['verified_count']],
                        'rejected' => ['label' => 'Rejected', 'icon' => 'fa-times-circle',  'badge' => 'bg-danger',            'count' => $counts['rejected_count']],
                        'all'      => ['label' => 'All',      'icon' => 'fa-list',          'badge' => 'bg-secondary',         'count' => $counts['total_count']],
                    ];
                    foreach ($tabs as $key => $tab):
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter_status === $key ? 'active' : ''; ?>" href="?status=<?php echo $key; ?>">
                            <i class="fas <?php echo $tab['icon']; ?>"></i> <?php echo $tab['label']; ?>
                            <span class="badge <?php echo $tab['badge']; ?> ms-1"><?php echo $tab['count']; ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Alert container for AJAX messages -->
                <div id="alertContainer"></div>

                <!-- Payments Table -->
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <?php if ($payments && $payments->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Customer</th>
                                        <th>Booking Ref</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Screenshot</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($payment = $payments->fetch_assoc()): ?>
                                    <tr id="payment-row-<?php echo $payment['id']; ?>">
                                        <td><strong>#<?php echo $payment['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong><br>
                                            <small class="text-muted">
                                                <i class="fas fa-envelope fa-xs"></i> <?php echo htmlspecialchars($payment['customer_email']); ?><br>
                                                <?php if ($payment['customer_phone']): ?>
                                                <i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars($payment['customer_phone']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="text-primary fw-bold"><?php echo htmlspecialchars($payment['booking_reference']); ?></span><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['room_name']); ?>
                                                <?php if ($payment['room_number'] !== 'N/A'): ?> #<?php echo htmlspecialchars($payment['room_number']); ?><?php endif; ?>
                                            </small>
                                        </td>
                                        <td><strong class="text-success">ETB <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-info text-dark"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                                            <?php if ($payment['transaction_reference']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($payment['transaction_reference']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <img src="../<?php echo htmlspecialchars($payment['screenshot_path']); ?>"
                                                 class="payment-screenshot"
                                                 alt="Screenshot"
                                                 onclick="viewScreenshot('../<?php echo htmlspecialchars($payment['screenshot_path']); ?>')">
                                        </td>
                                        <td>
                                            <?php
                                            $sc = ['pending'=>'warning','verified'=>'success','rejected'=>'danger'];
                                            $si = ['pending'=>'fa-clock','verified'=>'fa-check-circle','rejected'=>'fa-times-circle'];
                                            $s = $payment['status'];
                                            ?>
                                            <span class="badge bg-<?php echo $sc[$s]; ?> px-2 py-1">
                                                <i class="fas <?php echo $si[$s]; ?>"></i> <?php echo ucfirst($s); ?>
                                            </span>
                                            <?php if ($payment['verified_at']): ?>
                                            <br><small class="text-muted">
                                                By <?php echo htmlspecialchars($payment['verified_by_name']); ?><br>
                                                <?php echo date('M j, Y g:i A', strtotime($payment['verified_at'])); ?>
                                            </small>
                                            <?php endif; ?>
                                            <?php if ($payment['rejection_reason']): ?>
                                            <br><small class="text-danger"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($payment['rejection_reason']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($payment['created_at'])); ?><br>
                                            <?php echo date('g:i A', strtotime($payment['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($payment['status'] === 'pending'): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <button class="btn btn-success btn-sm" onclick="showApproveModal(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['customer_name']); ?>', '<?php echo htmlspecialchars($payment['booking_reference']); ?>')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['customer_name']); ?>')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted small">Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No <?php echo $filter_status === 'all' ? '' : $filter_status; ?> payments found</h5>
                            <p class="text-muted">There are no payment verifications to display.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- /main content -->
        </div>
    </div>

    <!-- Screenshot Viewer Modal -->
    <div class="modal fade" id="screenshotModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-image"></i> Payment Screenshot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalScreenshot" src="" class="img-fluid rounded" alt="Payment Screenshot">
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-check-circle fa-3x text-success"></i>
                    </div>
                    <p>You are about to <strong>approve</strong> the payment for:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-user text-muted me-2"></i> Customer: <strong id="approveCustomerName"></strong></li>
                        <li><i class="fas fa-hashtag text-muted me-2"></i> Booking: <strong id="approveBookingRef"></strong></li>
                    </ul>
                    <p class="text-muted small mb-0">This will mark the payment as verified and notify the customer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApproveBtn">
                        <i class="fas fa-check"></i> Yes, Approve
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-times-circle fa-3x text-danger"></i>
                    </div>
                    <p>Rejecting payment for: <strong id="rejectCustomerName"></strong></p>
                    <label class="form-label">Reason for rejection: <span class="text-danger">*</span></label>
                    <textarea id="rejectionReason" class="form-control" rows="3"
                              placeholder="e.g., Screenshot is unclear, wrong amount, invalid transaction..."></textarea>
                    <small class="text-muted">The customer will be notified with this reason.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmReject()">
                        <i class="fas fa-times"></i> Reject Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPaymentId = null;

        // Mobile sidebar toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        });
        document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });

        function viewScreenshot(path) {
            document.getElementById('modalScreenshot').src = path;
            new bootstrap.Modal(document.getElementById('screenshotModal')).show();
        }

        function showApproveModal(paymentId, customerName, bookingRef) {
            currentPaymentId = paymentId;
            document.getElementById('approveCustomerName').textContent = customerName;
            document.getElementById('approveBookingRef').textContent = bookingRef;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }

        document.getElementById('confirmApproveBtn').addEventListener('click', () => {
            bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
            processPayment(currentPaymentId, 'approve');
        });

        function showRejectModal(paymentId, customerName) {
            currentPaymentId = paymentId;
            document.getElementById('rejectCustomerName').textContent = customerName;
            document.getElementById('rejectionReason').value = '';
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        function confirmReject() {
            const reason = document.getElementById('rejectionReason').value.trim();
            if (!reason) {
                document.getElementById('rejectionReason').classList.add('is-invalid');
                return;
            }
            document.getElementById('rejectionReason').classList.remove('is-invalid');
            bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
            processPayment(currentPaymentId, 'reject', reason);
        }

        function processPayment(paymentId, action, reason = '') {
            const btn = document.querySelector(`#payment-row-${paymentId} .btn-${action === 'approve' ? 'success' : 'danger'}`);
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

            const formData = new FormData();
            formData.append('payment_id', paymentId);
            formData.append('action', action);
            if (reason) formData.append('reason', reason);

            fetch('../api/verify_payment.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(data.message || 'An error occurred.', 'danger');
                        if (btn) { btn.disabled = false; btn.innerHTML = action === 'approve' ? '<i class="fas fa-check"></i> Approve' : '<i class="fas fa-times"></i> Reject'; }
                    }
                })
                .catch(() => {
                    showAlert('Network error. Please try again.', 'danger');
                    if (btn) { btn.disabled = false; }
                });
        }

        function showAlert(message, type) {
            const icons = { success: 'fa-check-circle', danger: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle' };
            document.getElementById('alertContainer').innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas ${icons[type] || 'fa-info-circle'}"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
            setTimeout(() => {
                document.querySelector('.alert')?.remove();
            }, 5000);
        }
    </script>
</body>
</html>
