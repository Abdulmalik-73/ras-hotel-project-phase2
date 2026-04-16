<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php'); // Managers and admins can view reports

// Get date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'summary';

// Sanitize dates
$start_date = sanitize_input($start_date);
$end_date = sanitize_input($end_date);

// Get summary statistics
$summary_query = "
    SELECT 
        COUNT(CASE WHEN status IN ('confirmed', 'checked_in', 'checked_out') THEN 1 END) as total_bookings,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
        COUNT(CASE WHEN status = 'checked_in' THEN 1 END) as checked_in_bookings,
        COUNT(CASE WHEN status = 'checked_out' THEN 1 END) as completed_bookings,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings,
        SUM(CASE WHEN status IN ('confirmed', 'checked_in', 'checked_out') THEN total_price ELSE 0 END) as total_revenue,
        AVG(CASE WHEN status IN ('confirmed', 'checked_in', 'checked_out') THEN total_price ELSE NULL END) as avg_booking_value,
        SUM(CASE WHEN status IN ('confirmed', 'checked_in', 'checked_out') THEN customers ELSE 0 END) as total_customers
    FROM bookings 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
";

$summary_result = $conn->query($summary_query);
$summary = $summary_result->fetch_assoc();

// Get daily revenue breakdown
$daily_query = "
    SELECT 
        DATE(created_at) as booking_date,
        COUNT(CASE WHEN status IN ('confirmed', 'checked_in', 'checked_out') THEN 1 END) as bookings_count,
        SUM(CASE WHEN status IN ('confirmed', 'checked_in', 'checked_out') THEN total_price ELSE 0 END) as daily_revenue
    FROM bookings 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(created_at)
    ORDER BY booking_date DESC
    LIMIT 30
";

$daily_result = $conn->query($daily_query);

// Get room type performance
$room_performance_query = "
    SELECT 
        r.room_type,
        COUNT(b.id) as bookings_count,
        SUM(b.total_price) as revenue,
        AVG(b.total_price) as avg_price
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'
    AND b.status IN ('confirmed', 'checked_in', 'checked_out')
    GROUP BY r.room_type
    ORDER BY revenue DESC
";

$room_performance = $conn->query($room_performance_query);

// Get top customers
$top_customers_query = "
    SELECT 
        CONCAT(u.first_name, ' ', u.last_name) as customer_name,
        u.email,
        COUNT(b.id) as total_bookings,
        SUM(b.total_price) as total_spent
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'
    AND b.status IN ('confirmed', 'checked_in', 'checked_out')
    GROUP BY b.user_id
    ORDER BY total_spent DESC
    LIMIT 10
";

$top_customers = $conn->query($top_customers_query);

// Get payment method breakdown
$payment_methods_query = "
    SELECT 
        COALESCE(payment_method, 'Not Specified') as method,
        COUNT(*) as count,
        SUM(total_price) as revenue
    FROM bookings 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    AND status IN ('confirmed', 'checked_in', 'checked_out')
    GROUP BY payment_method
    ORDER BY revenue DESC
";

$payment_methods = $conn->query($payment_methods_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Harar Ras Hotel'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/print.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card .card-body {
            padding: 1.5rem;
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .chart-container {
            position: relative;
            height: 400px;
        }
    </style>
</head>
<body>
    <div class="container-fluid single-page-print">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-hotel"></i> Admin Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="admin.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="manage-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Manage Rooms
                        </a>
                        <a href="manage-bookings.php" class="nav-link">
                            <i class="fas fa-calendar-check me-2"></i> Manage Bookings
                        </a>
                        <a href="manage-services.php" class="nav-link">
                            <i class="fas fa-concierge-bell me-2"></i> Manage Services
                        </a>
                        <a href="reports.php" class="nav-link active">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                        <a href="settings.php" class="nav-link">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </nav>
                    
                    <div class="mt-auto">
                        <div class="text-white-50 small">
                            Logged in as: <?php echo $_SESSION['user_name'] ?? 'Admin'; ?>
                        </div>
                        <a href="../logout.php" class="nav-link text-white">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="admin.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-chart-bar me-2"></i> Reports & Analytics</h2>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i> Print Report
                            </button>
                            <button class="btn btn-success" onclick="exportToCSV()">
                                <i class="fas fa-download me-2"></i> Export CSV
                            </button>
                        </div>
                    </div>
                    
                    <!-- Date Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Report Type</label>
                                    <select name="report_type" class="form-select">
                                        <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary</option>
                                        <option value="detailed" <?php echo $report_type == 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-2"></i> Generate Report
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Summary Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check stat-icon"></i>
                                    <h3 class="mt-2 mb-1"><?php echo number_format($summary['total_bookings'] ?? 0); ?></h3>
                                    <p class="mb-0">Total Bookings</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-money-bill-wave stat-icon"></i>
                                    <h3 class="mt-2 mb-1"><?php echo formatCurrency($summary['total_revenue'] ?? 0); ?></h3>
                                    <p class="mb-0">Total Revenue</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line stat-icon"></i>
                                    <h3 class="mt-2 mb-1"><?php echo formatCurrency($summary['avg_booking_value'] ?? 0); ?></h3>
                                    <p class="mb-0">Avg Booking Value</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users stat-icon"></i>
                                    <h3 class="mt-2 mb-1"><?php echo number_format($summary['total_customers'] ?? 0); ?></h3>
                                    <p class="mb-0">Total Customers</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Daily Revenue Trend</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="revenueChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Booking Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="statusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Data Tables Row -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-bed me-2"></i> Room Type Performance</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Room Type</th>
                                                    <th>Bookings</th>
                                                    <th>Revenue</th>
                                                    <th>Avg Price</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($room_performance && $room_performance->num_rows > 0): ?>
                                                    <?php while ($room = $room_performance->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo ucfirst($room['room_type']); ?></td>
                                                            <td><?php echo $room['bookings_count']; ?></td>
                                                            <td><?php echo formatCurrency($room['revenue']); ?></td>
                                                            <td><?php echo formatCurrency($room['avg_price']); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">No data available</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i> Payment Methods</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Payment Method</th>
                                                    <th>Count</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($payment_methods && $payment_methods->num_rows > 0): ?>
                                                    <?php while ($method = $payment_methods->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($method['method']); ?></td>
                                                            <td><?php echo $method['count']; ?></td>
                                                            <td><?php echo formatCurrency($method['revenue']); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">No data available</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Customers -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-star me-2"></i> Top Customers</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th>Bookings</th>
                                                    <th>Total Spent</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($top_customers && $top_customers->num_rows > 0): ?>
                                                    <?php while ($customer = $top_customers->fetch_assoc()): ?>
                                                        <tr>
                                                            <td>
                                                                <div><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                                            </td>
                                                            <td><?php echo $customer['total_bookings']; ?></td>
                                                            <td><?php echo formatCurrency($customer['total_spent']); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">No data available</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Daily Breakdown</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Bookings</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($daily_result && $daily_result->num_rows > 0): ?>
                                                    <?php while ($daily = $daily_result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo date('M j, Y', strtotime($daily['booking_date'])); ?></td>
                                                            <td><?php echo $daily['bookings_count']; ?></td>
                                                            <td><?php echo formatCurrency($daily['daily_revenue']); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">No data available</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = {
            labels: [
                <?php 
                $daily_result->data_seek(0); // Reset pointer
                $dates = [];
                $revenues = [];
                while ($daily = $daily_result->fetch_assoc()) {
                    $dates[] = "'" . date('M j', strtotime($daily['booking_date'])) . "'";
                    $revenues[] = $daily['daily_revenue'] ?? 0;
                }
                echo implode(',', array_reverse($dates));
                ?>
            ],
            datasets: [{
                label: 'Daily Revenue (ETB)',
                data: [<?php echo implode(',', array_reverse($revenues)); ?>],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        };
        
        new Chart(revenueCtx, {
            type: 'line',
            data: revenueData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'ETB ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = {
            labels: ['Confirmed', 'Checked In', 'Completed', 'Cancelled'],
            datasets: [{
                data: [
                    <?php echo $summary['confirmed_bookings'] ?? 0; ?>,
                    <?php echo $summary['checked_in_bookings'] ?? 0; ?>,
                    <?php echo $summary['completed_bookings'] ?? 0; ?>,
                    <?php echo $summary['cancelled_bookings'] ?? 0; ?>
                ],
                backgroundColor: [
                    '#28a745',
                    '#17a2b8',
                    '#6f42c1',
                    '#dc3545'
                ],
                borderWidth: 0
            }]
        };
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Export to CSV function
        function exportToCSV() {
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Harar Ras Hotel - Booking Report\\n";
            csvContent += "Period: " + startDate + " to " + endDate + "\\n\\n";
            
            csvContent += "Summary Statistics\\n";
            csvContent += "Total Bookings,<?php echo $summary['total_bookings'] ?? 0; ?>\\n";
            csvContent += "Total Revenue,<?php echo $summary['total_revenue'] ?? 0; ?>\\n";
            csvContent += "Average Booking Value,<?php echo $summary['avg_booking_value'] ?? 0; ?>\\n";
            csvContent += "Total Customers,<?php echo $summary['total_customers'] ?? 0; ?>\\n\\n";
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "hotel_report_" + startDate + "_to_" + endDate + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>