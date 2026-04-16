<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('admin', '../login.php');

// Handle booking actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    
    switch ($action) {
        case 'update_status':
            $new_status = sanitize_input($_POST['status']);
            $query = "UPDATE bookings SET status = '$new_status' WHERE id = $booking_id";
            if ($conn->query($query)) {
                log_booking_activity($booking_id, $_SESSION['user_id'], 'modified', '', $new_status, 'Status updated by admin', $_SESSION['user_id']);
                set_message('success', 'Booking status updated successfully');
            } else {
                set_message('error', 'Failed to update booking status');
            }
            break;
            
        case 'cancel':
            $cancel_reason = sanitize_input($_POST['cancel_reason'] ?? 'Cancelled by admin');
            
            // Get booking details for logging
            $booking_query = "SELECT user_id, status FROM bookings WHERE id = $booking_id";
            $booking_result = $conn->query($booking_query);
            $booking_data = $booking_result->fetch_assoc();
            
            if ($booking_data && $booking_data['status'] != 'cancelled' && $booking_data['status'] != 'checked_out') {
                $old_status = $booking_data['status'];
                $query = "UPDATE bookings SET status = 'cancelled' WHERE id = $booking_id";
                
                if ($conn->query($query)) {
                    log_booking_activity($booking_id, $booking_data['user_id'], 'cancelled', $old_status, 'cancelled', "Booking cancelled by admin: $cancel_reason", $_SESSION['user_id']);
                    set_message('success', 'Booking cancelled successfully');
                } else {
                    set_message('error', 'Failed to cancel booking: ' . $conn->error);
                }
            } else {
                set_message('error', 'Cannot cancel this booking (already cancelled or checked out)');
            }
            break;
            
        case 'delete':
            $query = "DELETE FROM bookings WHERE id = $booking_id";
            if ($conn->query($query)) {
                set_message('success', 'Booking deleted successfully');
            } else {
                set_message('error', 'Failed to delete booking');
            }
            break;
    }
    header('Location: manage-bookings.php');
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
if ($status_filter) {
    $where_conditions[] = "b.status = '" . sanitize_input($status_filter) . "'";
}
if ($date_filter) {
    $where_conditions[] = "DATE(b.check_in_date) = '" . sanitize_input($date_filter) . "'";
}
if ($search) {
    $search_term = sanitize_input($search);
    $where_conditions[] = "(b.booking_reference LIKE '%$search_term%' OR CONCAT(u.first_name, ' ', u.last_name) LIKE '%$search_term%' OR u.email LIKE '%$search_term%')";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get bookings with pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$bookings_query = "SELECT b.*, r.name as room_name, r.room_number, 
                   CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone
                   FROM bookings b 
                   LEFT JOIN rooms r ON b.room_id = r.id 
                   JOIN users u ON b.user_id = u.id 
                   $where_clause
                   ORDER BY b.created_at DESC 
                   LIMIT $per_page OFFSET $offset";

$bookings = $conn->query($bookings_query)->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM bookings b JOIN users u ON b.user_id = u.id $where_clause";
$total_bookings = $conn->query($count_query)->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> Harar Ras Hotel - Admin Dashboard
            </a>
            <div class="ms-auto">
                
                <span class="text-white me-3">
                    <i class="fas fa-user-shield"></i> <?php echo $_SESSION['user_name']; ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-light sidebar py-4">
                <div class="list-group">
                    <a href="admin.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="manage-bookings.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-calendar-check"></i> Bookings
                    </a>
                    <a href="manage-rooms.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-bed"></i> Rooms
                    </a>
                    <a href="manage-services.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-concierge-bell"></i> Services
                    </a>
                    <a href="view-data.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-database"></i> View All Data
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <a href="admin.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                        </a>
                        <h2 class="d-inline"><i class="fas fa-calendar-check me-2"></i> Manage Bookings</h2>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookingModal">
                        <i class="fas fa-plus"></i> Add New Booking
                    </button>
                </div>
                
                <?php display_message(); ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="checked_in" <?php echo $status_filter == 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                    <option value="checked_out" <?php echo $status_filter == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Check-in Date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Reference, guest name, or email" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Guest</th>
                                        <th>Room</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Guests</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td><strong><?php echo $booking['booking_reference']; ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['email'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($booking['room_name']): ?>
                                                <?php echo htmlspecialchars($booking['room_name'] ?? ''); ?><br>
                                                <small class="text-muted">Room <?php echo htmlspecialchars($booking['room_number'] ?? ''); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Food Order</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo !empty($booking['check_in_date']) ? date('M j, Y', strtotime($booking['check_in_date'])) : 'N/A'; ?></td>
                                        <td><?php echo !empty($booking['check_out_date']) ? date('M j, Y', strtotime($booking['check_out_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $booking['customers']; ?></td>
                                        <td><?php echo format_currency($booking['total_price']); ?></td>
                                        <td>
                                            <?php
                                            // Show Verified if Chapa payment confirmed
                                            if ($booking['payment_status'] === 'paid' && $booking['verification_status'] === 'verified'):
                                            ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> Verified
                                                </span>
                                            <?php else:
                                                $badge_class = 'secondary';
                                                switch ($booking['status']) {
                                                    case 'confirmed':   $badge_class = 'success'; break;
                                                    case 'checked_in':  $badge_class = 'primary'; break;
                                                    case 'checked_out': $badge_class = 'info';    break;
                                                    case 'cancelled':   $badge_class = 'danger';  break;
                                                }
                                            ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewBooking(<?php echo $booking['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="editBooking(<?php echo $booking['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($booking['status'] != 'cancelled' && $booking['status'] != 'checked_out'): ?>
                                                <button class="btn btn-outline-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>, '<?php echo addslashes($booking['booking_reference']); ?>')" title="Cancel">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-dark" onclick="deleteBooking(<?php echo $booking['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Booking Modal -->
    <div class="modal fade" id="addBookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="add-booking.php">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Guest Email</label>
                                <input type="email" name="guest_email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Room</label>
                                <select name="room_id" class="form-select" required>
                                    <option value="">Select Room</option>
                                    <?php
                                    $rooms = get_all_rooms();
                                    foreach ($rooms as $room):
                                    ?>
                                    <option value="<?php echo $room['id']; ?>"><?php echo $room['name']; ?> - Room <?php echo $room['room_number']; ?> (<?php echo format_currency($room['price']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Check-in Date</label>
                                <input type="date" name="check_in" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Check-out Date</label>
                                <input type="date" name="check_out" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Number of Guests</label>
                                <input type="number" name="customers" class="form-control" min="1" value="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Special Requests</label>
                            <textarea name="special_requests" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-ban me-2"></i> Cancel Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" id="cancel_booking_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Are you sure you want to cancel booking <strong id="cancel_booking_ref"></strong>?
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                            <select name="cancel_reason" class="form-select" required>
                                <option value="">Select reason</option>
                                <option value="Customer request">Customer request</option>
                                <option value="Payment not received">Payment not received</option>
                                <option value="Room unavailable">Room unavailable</option>
                                <option value="Duplicate booking">Duplicate booking</option>
                                <option value="Administrative decision">Administrative decision</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <p class="text-muted small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            This action will change the booking status to "Cancelled". The customer will be notified.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban me-2"></i> Cancel Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function viewBooking(id) {
            window.open('view-booking.php?id=' + id, '_blank');
        }
        
        function editBooking(id) {
            window.location.href = 'edit-booking.php?id=' + id;
        }
        
        function cancelBooking(id, reference) {
            document.getElementById('cancel_booking_id').value = id;
            document.getElementById('cancel_booking_ref').textContent = reference;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }
        
        function deleteBooking(id) {
            if (confirm('Are you sure you want to permanently delete this booking? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="booking_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>