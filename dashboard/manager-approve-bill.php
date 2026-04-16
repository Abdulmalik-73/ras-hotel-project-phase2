<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

$message = '';
$error = '';
$bills = [];

// Handle bill approval/rejection
if ($_POST && isset($_POST['action'])) {
    $bill_id = (int)$_POST['bill_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $query = "UPDATE bills SET status = 'approved', approved_by = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $_SESSION['user_id'], $bill_id);
        
        if ($stmt->execute()) {
            $message = 'Bill approved successfully!';
        } else {
            $error = 'Failed to approve bill: ' . $stmt->error;
        }
    } elseif ($action === 'reject') {
        $rejection_reason = sanitize_input($_POST['rejection_reason'] ?? '');
        $query = "UPDATE bills SET status = 'rejected', approved_by = ?, updated_at = NOW(), rejection_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isi", $_SESSION['user_id'], $rejection_reason, $bill_id);
        
        if ($stmt->execute()) {
            $message = 'Bill rejected successfully!';
        } else {
            $error = 'Failed to reject bill: ' . $stmt->error;
        }
    }
}

// Get pending bills for approval
$bills_query = "SELECT b.*, 
                b.bill_reference as bill_number,
                CONCAT(u_gen.first_name, ' ', u_gen.last_name) as generated_by_name,
                CONCAT(u_app.first_name, ' ', u_app.last_name) as approved_by_name,
                bk.booking_reference,
                bk.check_in_date,
                bk.check_out_date,
                CONCAT(u_cust.first_name, ' ', u_cust.last_name) as guest_name,
                r.room_number
                FROM bills b
                LEFT JOIN users u_gen ON b.generated_by = u_gen.id
                LEFT JOIN users u_app ON b.approved_by = u_app.id
                LEFT JOIN bookings bk ON b.booking_id = bk.id
                LEFT JOIN users u_cust ON b.customer_id = u_cust.id
                LEFT JOIN rooms r ON bk.room_id = r.id
                WHERE b.status IN ('sent_to_manager', 'approved', 'rejected')
                ORDER BY b.created_at DESC";
$bills_result = $conn->query($bills_query);
if ($bills_result) {
    while ($row = $bills_result->fetch_assoc()) {
        $bills[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Bills - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-manager {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%) !important;
        }
        .navbar-manager .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            transition: left 0.3s ease;
            z-index: 1050;
            overflow-y: auto;
            padding-top: 70px;
        }
        .sidebar.show {
            left: 0;
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            display: none;
        }
        .sidebar-overlay.show {
            display: block;
        }
        .sidebar h4 {
            font-size: 1rem;
            margin-bottom: 0.5rem !important;
            padding: 0 1rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 0.4rem 1rem;
            margin: 0.1rem 0.5rem;
            border-radius: 0.3rem;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
        }
        .sidebar .nav-link i {
            width: 18px;
            font-size: 0.85rem;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .menu-toggle {
            position: fixed;
            top: 70px;
            left: 10px;
            z-index: 1060;
            background: #8e44ad;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-size: 1.2rem;
            transition: left 0.3s ease;
        }
        .menu-toggle.shifted {
            left: 290px;
        }
        .menu-toggle:hover {
            background: #9b59b6;
        }
        .main-content-wrapper {
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        .main-content-wrapper.shifted {
            margin-left: 280px;
        }
        .bill-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        .bill-card.pending {
            border-left-color: #ffc107;
        }
        .bill-card.approved {
            border-left-color: #28a745;
        }
        .bill-card.rejected {
            border-left-color: #dc3545;
        }
        .bill-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-manager">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> 
                <span class="text-white fw-bold">Harar Ras Hotel - Manager Dashboard</span>
            </a>
            <div class="ms-auto">
                
                <span class="text-white me-3">
                    <i class="fas fa-user-tie"></i> Manager
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Hamburger Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h4 class="text-white">
            <i class="fas fa-user-tie"></i> Manager Panel
        </h4>
        
        <nav class="nav flex-column">
            <a href="manager.php" class="nav-link">
                <i class="fas fa-tachometer-alt me-2"></i> Overview
            </a>
            <a href="manager-bookings.php" class="nav-link">
                <i class="fas fa-calendar-check me-2"></i> Manage Bookings
            </a>
            <a href="manager-approve-bill.php" class="nav-link active">
                <i class="fas fa-check-circle me-2"></i> Approve Bill
            </a>
            <a href="manager-feedback.php" class="nav-link">
                <i class="fas fa-star me-2"></i> Customer Feedback
            </a>
            <a href="manager-refund.php" class="nav-link">
                <i class="fas fa-undo-alt me-2"></i> Refund Management
            </a>
            <a href="manager-rooms.php" class="nav-link">
                <i class="fas fa-bed me-2"></i> Room Management
            </a>
            <a href="manager-staff.php" class="nav-link">
                <i class="fas fa-users me-2"></i> Staff Management
            </a>
            <a href="manager-reports.php" class="nav-link">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
            <a href="../logout.php" class="nav-link mt-3">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-12">
                <div class="main-content-wrapper" id="mainContent">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manager.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-check-circle me-2"></i> Approve Bills</h2>
                        </div>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i> Refresh
                        </button>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Bills Summary -->
                    <div class="row mb-4">
                        <?php
                        $pending_count = count(array_filter($bills, function($bill) { return $bill['status'] == 'sent_to_manager'; }));
                        $approved_count = count(array_filter($bills, function($bill) { return $bill['status'] == 'approved'; }));
                        $rejected_count = count(array_filter($bills, function($bill) { return $bill['status'] == 'rejected'; }));
                        ?>
                        <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800); color: white;">
                                <div class="card-body">
                                    <h3><?php echo $pending_count; ?></h3>
                                    <p class="mb-0"><i class="fas fa-clock me-2"></i>Pending Approval</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white;">
                                <div class="card-body">
                                    <h3><?php echo $approved_count; ?></h3>
                                    <p class="mb-0"><i class="fas fa-check me-2"></i>Approved</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white;">
                                <div class="card-body">
                                    <h3><?php echo $rejected_count; ?></h3>
                                    <p class="mb-0"><i class="fas fa-times me-2"></i>Rejected</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bills List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i> Bills for Approval</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bills)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No bills found. Bills will appear here when generated by reception staff.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($bills as $bill): ?>
                                <div class="card bill-card <?php echo $bill['status']; ?> mb-3">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="card-title">
                                                    Bill #<?php echo htmlspecialchars($bill['bill_number'] ?? 'N/A'); ?>
                                                    <span class="badge bg-<?php echo $bill['status'] == 'sent_to_manager' ? 'warning' : ($bill['status'] == 'approved' ? 'success' : 'danger'); ?> ms-2">
                                                        <?php echo $bill['status'] == 'sent_to_manager' ? 'Pending' : ucfirst($bill['status']); ?>
                                                    </span>
                                                </h6>
                                                <div class="bill-details">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p class="mb-1"><strong>Guest:</strong> <?php echo htmlspecialchars($bill['guest_name'] ?? 'N/A'); ?></p>
                                                            <p class="mb-1"><strong>Room:</strong> <?php echo htmlspecialchars($bill['room_number'] ?? 'N/A'); ?></p>
                                                            <p class="mb-1"><strong>Booking Ref:</strong> <?php echo htmlspecialchars($bill['booking_reference'] ?? 'N/A'); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p class="mb-1"><strong>Check-in:</strong> <?php echo !empty($bill['check_in_date']) ? date('M j, Y', strtotime($bill['check_in_date'])) : 'N/A'; ?></p>
                                                            <p class="mb-1"><strong>Check-out:</strong> <?php echo !empty($bill['check_out_date']) ? date('M j, Y', strtotime($bill['check_out_date'])) : 'N/A'; ?></p>
                                                            <p class="mb-1"><strong>Total:</strong> <span class="text-success fw-bold"><?php echo format_currency($bill['total_amount']); ?></span></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    Generated by: <?php echo htmlspecialchars($bill['generated_by_name'] ?? 'Unknown'); ?> 
                                                    on <?php echo !empty($bill['created_at']) ? date('M j, Y g:i A', strtotime($bill['created_at'])) : 'N/A'; ?>
                                                    <?php if (!empty($bill['approved_at'])): ?>
                                                        <br>
                                                        <?php echo ucfirst($bill['status']); ?> by: <?php echo htmlspecialchars($bill['approved_by_name'] ?? 'Unknown'); ?> 
                                                        on <?php echo date('M j, Y g:i A', strtotime($bill['approved_at'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                                <?php if ($bill['status'] == 'rejected' && $bill['rejection_reason']): ?>
                                                    <div class="alert alert-danger mt-2 mb-0">
                                                        <small><strong>Rejection Reason:</strong> <?php echo htmlspecialchars($bill['rejection_reason']); ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <?php if ($bill['status'] == 'sent_to_manager'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm mb-2">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                    </form>
                                                    <br>
                                                    <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $bill['id']; ?>)">
                                                        <i class="fas fa-times me-1"></i> Reject
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-<?php echo $bill['status'] == 'approved' ? 'success' : 'danger'; ?> fs-6">
                                                        <i class="fas fa-<?php echo $bill['status'] == 'approved' ? 'check' : 'times'; ?> me-1"></i>
                                                        <?php echo ucfirst($bill['status']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" name="bill_id" id="rejectBillId">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection:</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required 
                                      placeholder="Please provide a reason for rejecting this bill..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i> Reject Bill
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showRejectModal(billId) {
            document.getElementById('rejectBillId').value = billId;
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 300);
                    }
                }, 5000);
            });
        });
    </script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuToggle = document.getElementById('menuToggle');
            
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('shifted');
            menuToggle.classList.toggle('shifted');
        }
    </script>
</body>
</html>