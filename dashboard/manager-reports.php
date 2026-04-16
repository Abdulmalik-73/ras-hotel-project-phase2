<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

// Get date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Sanitize dates
$start_date = sanitize_input($start_date);
$end_date = sanitize_input($end_date);

// Get occupancy rates by date
$occupancy_query = "
    SELECT 
        DATE(b.check_in_date) as date,
        COUNT(DISTINCT b.room_id) as occupied_rooms,
        (SELECT COUNT(*) FROM rooms WHERE status = 'active') as total_rooms,
        ROUND((COUNT(DISTINCT b.room_id) / (SELECT COUNT(*) FROM rooms WHERE status = 'active')) * 100, 1) as occupancy_rate
    FROM bookings b
    WHERE b.status IN ('confirmed', 'checked_in', 'checked_out')
    AND DATE(b.check_in_date) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(b.check_in_date)
    ORDER BY date DESC
    LIMIT 30
";

$occupancy_result = $conn->query($occupancy_query);

// Get revenue history
$revenue_query = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as bookings_count,
        SUM(total_price) as daily_revenue,
        AVG(total_price) as avg_booking_value
    FROM bookings 
    WHERE status IN ('confirmed', 'checked_in', 'checked_out')
    AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
";

$revenue_result = $conn->query($revenue_query);

// Get room type performance
$room_type_query = "
    SELECT 
        r.room_type,
        COUNT(b.id) as bookings_count,
        SUM(b.total_price) as total_revenue,
        AVG(b.total_price) as avg_price,
        ROUND(AVG(DATEDIFF(b.check_out_date, b.check_in_date)), 1) as avg_stay_duration
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.status IN ('confirmed', 'checked_in', 'checked_out')
    AND DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY r.room_type
    ORDER BY total_revenue DESC
";

$room_type_result = $conn->query($room_type_query);

// Get summary statistics
$summary_query = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(total_price) as total_revenue,
        AVG(total_price) as avg_booking_value,
        SUM(customers) as total_guests,
        AVG(DATEDIFF(check_out_date, check_in_date)) as avg_stay_duration
    FROM bookings 
    WHERE status IN ('confirmed', 'checked_in', 'checked_out')
    AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
";

$summary = $conn->query($summary_query)->fetch_assoc();

// Get current occupancy
$current_occupancy_query = "
    SELECT 
        COUNT(DISTINCT b.room_id) as occupied_rooms,
        (SELECT COUNT(*) FROM rooms WHERE status = 'active') as total_rooms
    FROM bookings b
    WHERE b.status = 'checked_in'
";

$current_occupancy = $conn->query($current_occupancy_query)->fetch_assoc();
$current_occupancy_rate = $current_occupancy['total_rooms'] > 0 ? 
    round(($current_occupancy['occupied_rooms'] / $current_occupancy['total_rooms']) * 100, 1) : 0;

// Get booking status distribution for pie chart
$booking_status_query = "
    SELECT 
        status,
        COUNT(*) as count
    FROM bookings
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY status
";

$booking_status_result = $conn->query($booking_status_query);
$booking_statuses = [];
while ($row = $booking_status_result->fetch_assoc()) {
    $booking_statuses[$row['status']] = $row['count'];
}

// Get daily revenue data for line chart
$daily_revenue_query = "
    SELECT 
        DATE(created_at) as date,
        SUM(total_price) as revenue
    FROM bookings
    WHERE status IN ('confirmed', 'checked_in', 'checked_out')
    AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";

$daily_revenue_result = $conn->query($daily_revenue_query);
$daily_revenues = [];
$revenue_dates = [];
while ($row = $daily_revenue_result->fetch_assoc()) {
    $revenue_dates[] = date('M j', strtotime($row['date']));
    $daily_revenues[] = (float)$row['revenue'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/print.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .navbar-manager {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%) !important;
        }
        .navbar-manager .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
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
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card .card-body {
            padding: 1.5rem;
            text-align: center;
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
            margin-bottom: 1rem;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .progress {
            height: 8px;
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

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-user-tie"></i> Manager Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="manager.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Overview
                        </a>
                        <a href="manager-bookings.php" class="nav-link">
                            <i class="fas fa-calendar-check me-2"></i> Manage Bookings
                        </a>
                        <a href="manager-approve-bill.php" class="nav-link">
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
                        <a href="manager-reports.php" class="nav-link active">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                    </nav>
                    
                    <div class="mt-auto">
                        <a href="../logout.php" class="nav-link text-white">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4 single-page-print">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="manager.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-chart-bar me-2"></i> Reports & Analytics</h2>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i> Print
                            </button>
                            <button class="btn btn-success" onclick="exportData()">
                                <i class="fas fa-download me-2"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <!-- Date Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-4">
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
                                <div class="card-body">
                                    <i class="fas fa-calendar-check stat-icon"></i>
                                    <div class="stat-number"><?php echo number_format($summary['total_bookings'] ?? 0); ?></div>
                                    <p class="mb-0">Total Bookings</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <i class="fas fa-money-bill-wave stat-icon"></i>
                                    <div class="stat-number"><?php echo formatCurrency($summary['total_revenue'] ?? 0); ?></div>
                                    <p class="mb-0">Total Revenue</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <i class="fas fa-users stat-icon"></i>
                                    <div class="stat-number"><?php echo number_format($summary['total_guests'] ?? 0); ?></div>
                                    <p class="mb-0">Total Guests</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <i class="fas fa-bed stat-icon"></i>
                                    <div class="stat-number"><?php echo $current_occupancy_rate; ?>%</div>
                                    <p class="mb-0">Current Occupancy</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Section -->
                    <div class="row mb-4">
                        <!-- Daily Revenue Trend Chart -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Daily Revenue Trend</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueChart" height="80"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Booking Status Pie Chart -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-pie-chart me-2"></i> Booking Status</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="statusChart" height="80"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reports Tables -->
                    <div class="row">
                        <!-- Occupancy Rates -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Daily Occupancy Rates</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Occupied</th>
                                                    <th>Rate</th>
                                                    <th>Progress</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($occupancy_result && $occupancy_result->num_rows > 0): ?>
                                                    <?php while ($occupancy = $occupancy_result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo date('M j', strtotime($occupancy['date'])); ?></td>
                                                            <td><?php echo $occupancy['occupied_rooms']; ?>/<?php echo $occupancy['total_rooms']; ?></td>
                                                            <td><?php echo $occupancy['occupancy_rate']; ?>%</td>
                                                            <td>
                                                                <div class="progress">
                                                                    <div class="progress-bar bg-success" style="width: <?php echo $occupancy['occupancy_rate']; ?>%"></div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">No occupancy data available</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Revenue History -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i> Daily Revenue History</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Bookings</th>
                                                    <th>Revenue</th>
                                                    <th>Avg Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($revenue_result && $revenue_result->num_rows > 0): ?>
                                                    <?php while ($revenue = $revenue_result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo date('M j', strtotime($revenue['date'])); ?></td>
                                                            <td><?php echo $revenue['bookings_count']; ?></td>
                                                            <td><?php echo formatCurrency($revenue['daily_revenue']); ?></td>
                                                            <td><?php echo formatCurrency($revenue['avg_booking_value']); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">No revenue data available</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Room Type Performance -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bed me-2"></i> Room Type Performance</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Room Type</th>
                                            <th>Bookings</th>
                                            <th>Total Revenue</th>
                                            <th>Avg Price</th>
                                            <th>Avg Stay (Days)</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($room_type_result && $room_type_result->num_rows > 0): ?>
                                            <?php 
                                            $max_revenue = 0;
                                            $room_types = [];
                                            while ($room_type = $room_type_result->fetch_assoc()) {
                                                $room_types[] = $room_type;
                                                if ($room_type['total_revenue'] > $max_revenue) {
                                                    $max_revenue = $room_type['total_revenue'];
                                                }
                                            }
                                            ?>
                                            <?php foreach ($room_types as $room_type): ?>
                                                <tr>
                                                    <td><strong><?php echo ucfirst($room_type['room_type']); ?></strong></td>
                                                    <td><?php echo $room_type['bookings_count']; ?></td>
                                                    <td><?php echo formatCurrency($room_type['total_revenue']); ?></td>
                                                    <td><?php echo formatCurrency($room_type['avg_price']); ?></td>
                                                    <td><?php echo $room_type['avg_stay_duration']; ?></td>
                                                    <td>
                                                        <?php $performance = $max_revenue > 0 ? ($room_type['total_revenue'] / $max_revenue) * 100 : 0; ?>
                                                        <div class="progress">
                                                            <div class="progress-bar bg-primary" style="width: <?php echo $performance; ?>%"></div>
                                                        </div>
                                                        <small class="text-muted"><?php echo round($performance, 1); ?>%</small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No room type data available</td>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Daily Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenue_dates); ?>,
                datasets: [{
                    label: 'Daily Revenue (ETB)',
                    data: <?php echo json_encode($daily_revenues); ?>,
                    borderColor: '#8e44ad',
                    backgroundColor: 'rgba(142, 68, 173, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#8e44ad',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
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
        
        // Booking Status Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Confirmed', 'Checked In', 'Completed', 'Cancelled', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $booking_statuses['confirmed'] ?? 0; ?>,
                        <?php echo $booking_statuses['checked_in'] ?? 0; ?>,
                        <?php echo $booking_statuses['checked_out'] ?? 0; ?>,
                        <?php echo $booking_statuses['cancelled'] ?? 0; ?>,
                        <?php echo $booking_statuses['pending'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#27ae60',
                        '#3498db',
                        '#9b59b6',
                        '#e74c3c',
                        '#f39c12'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        function exportData() {
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Harar Ras Hotel - Manager Report\\n";
            csvContent += "Period: " + startDate + " to " + endDate + "\\n\\n";
            
            csvContent += "Summary Statistics\\n";
            csvContent += "Total Bookings,<?php echo $summary['total_bookings'] ?? 0; ?>\\n";
            csvContent += "Total Revenue,<?php echo $summary['total_revenue'] ?? 0; ?>\\n";
            csvContent += "Total Guests,<?php echo $summary['total_guests'] ?? 0; ?>\\n";
            csvContent += "Current Occupancy Rate,<?php echo $current_occupancy_rate; ?>%\\n\\n";
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "manager_report_" + startDate + "_to_" + endDate + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>