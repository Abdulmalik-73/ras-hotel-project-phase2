<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('receptionist', '../login.php');

$checkin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$checkin_id) {
    header('Location: receptionist.php');
    exit();
}

// Get check-in details
$query = "
    SELECT c.*, 
           u.first_name as receptionist_first_name, 
           u.last_name as receptionist_last_name,
           cust.first_name as customer_first_name,
           cust.last_name as customer_last_name
    FROM checkins c
    LEFT JOIN users u ON c.checked_in_by = u.id
    LEFT JOIN users cust ON c.customer_id = cust.id
    WHERE c.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $checkin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: receptionist.php');
    exit();
}

$checkin = $result->fetch_assoc();

// Calculate total amount
$total_amount = $checkin['rate_per_night'] * $checkin['nights_stay'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-In Details - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/print.css">
    <style>
        .detail-section {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            margin-bottom: 1.5rem;
        }
        .detail-section h6 {
            background: #28a745;
            color: white;
            margin: 0;
            padding: 0.75rem 1rem;
            font-weight: 600;
        }
        .detail-row {
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .container { max-width: none !important; }
            body { font-size: 12px; }
            .card { border: 1px solid #000; }
            .detail-section h6 { background: #000 !important; }
        }
        .confirmation-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            background: #d4edda;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <!-- Print Header (only visible when printing) -->
    <div class="print-only text-center mb-4">
        <h2>HARAR RAS HOTEL</h2>
        <p>Jugol Street, Harar, Ethiopia</p>
        <p>Phone: +251-25-666-0000 | Email: info@hararrashotel.com</p>
        <hr>
        <h3>CUSTOMER CHECK-IN FORM</h3>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-success no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-gold"></i> Harar Ras Hotel - Check-In Details
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="receptionist.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn btn-outline-light btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Check-In Form
                </button>
                <span class="navbar-text me-3 ms-3">
                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4 single-page-print">
        <div class="row">
            <div class="col-12">
                <!-- Success Alert -->
                <div class="alert alert-success no-print">
                    <i class="fas fa-check-circle"></i> 
                    <strong>Check-In Completed Successfully!</strong>
                    <div class="mt-2">
                        <div class="confirmation-number">
                            Confirmation Number: <?php echo htmlspecialchars($checkin['confirmation_number']); ?>
                        </div>
                    </div>
                </div>

                <!-- Check-In Details Card -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-file-alt"></i> Customer Check-In Details
                            <span class="float-end">
                                <small>ID: #<?php echo $checkin['id']; ?></small>
                            </span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Hotel Information -->
                        <div class="detail-section card">
                            <h6><i class="fas fa-hotel"></i> Hotel Information</h6>
                            <div class="card-body">
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Hotel Name:</div>
                                    <div class="col-md-9"><?php echo htmlspecialchars($checkin['hotel_name']); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Location:</div>
                                    <div class="col-md-9"><?php echo htmlspecialchars($checkin['hotel_location']); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Check-In Date:</div>
                                    <div class="col-md-3"><?php echo date('F j, Y', strtotime($checkin['check_in_date'])); ?></div>
                                    <div class="col-md-3 detail-label">Check-Out Date:</div>
                                    <div class="col-md-3"><?php echo date('F j, Y', strtotime($checkin['check_out_date'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Guest Information -->
                        <div class="detail-section card">
                            <h6><i class="fas fa-user"></i> Customer Information</h6>
                            <div class="card-body">
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Full Name:</div>
                                    <div class="col-md-9"><strong><?php echo htmlspecialchars($checkin['guest_full_name']); ?></strong></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Date of Birth:</div>
                                    <div class="col-md-9"><?php echo date('F j, Y', strtotime($checkin['guest_date_of_birth'])); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">ID Type:</div>
                                    <div class="col-md-3"><?php echo ucwords(str_replace('_', ' ', $checkin['guest_id_type'])); ?></div>
                                    <div class="col-md-3 detail-label">ID Number:</div>
                                    <div class="col-md-3"><?php echo htmlspecialchars($checkin['guest_id_number']); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Nationality:</div>
                                    <div class="col-md-9"><?php echo htmlspecialchars($checkin['guest_nationality']); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Home Address:</div>
                                    <div class="col-md-9"><?php echo nl2br(htmlspecialchars($checkin['guest_home_address'])); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Phone Number:</div>
                                    <div class="col-md-3"><?php echo htmlspecialchars($checkin['guest_phone_number']); ?></div>
                                    <div class="col-md-3 detail-label">Email Address:</div>
                                    <div class="col-md-3"><?php echo htmlspecialchars($checkin['guest_email_address']); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Stay Details -->
                        <div class="detail-section card">
                            <h6><i class="fas fa-bed"></i> Stay Details</h6>
                            <div class="card-body">
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Room Type:</div>
                                    <div class="col-md-3"><?php echo htmlspecialchars($checkin['room_type']); ?></div>
                                    <div class="col-md-3 detail-label">Room Number:</div>
                                    <div class="col-md-3"><?php echo htmlspecialchars($checkin['room_number'] ?: 'Not assigned'); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Nights Stay:</div>
                                    <div class="col-md-3"><?php echo $checkin['nights_stay']; ?> night(s)</div>
                                    <div class="col-md-3 detail-label">Number of Guests:</div>
                                    <div class="col-md-3"><?php echo $checkin['number_of_guests']; ?> guest(s)</div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Rate per Night:</div>
                                    <div class="col-md-9"><?php echo number_format($checkin['rate_per_night'], 2); ?> ETB</div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Details -->
                        <div class="detail-section card">
                            <h6><i class="fas fa-credit-card"></i> Payment Details</h6>
                            <div class="card-body">
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Payment Type:</div>
                                    <div class="col-md-9"><?php echo ucwords(str_replace('_', ' ', $checkin['payment_type'])); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Total Amount:</div>
                                    <div class="col-md-3"><strong><?php echo number_format($total_amount, 2); ?> ETB</strong></div>
                                    <div class="col-md-3 detail-label">Amount Paid:</div>
                                    <div class="col-md-3"><strong><?php echo number_format($checkin['amount_paid'], 2); ?> ETB</strong></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Balance Due:</div>
                                    <div class="col-md-3">
                                        <strong class="<?php echo $checkin['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo number_format($checkin['balance_due'], 2); ?> ETB
                                        </strong>
                                    </div>
                                    <div class="col-md-3 detail-label">Confirmation Number:</div>
                                    <div class="col-md-3">
                                        <strong class="text-success"><?php echo htmlspecialchars($checkin['confirmation_number']); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Requests -->
                        <?php if (!empty($checkin['additional_requests'])): ?>
                        <div class="detail-section card">
                            <h6><i class="fas fa-comment"></i> Additional Requests</h6>
                            <div class="card-body">
                                <div class="row detail-row">
                                    <div class="col-12">
                                        <?php echo nl2br(htmlspecialchars($checkin['additional_requests'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- System Information -->
                        <div class="detail-section card">
                            <h6><i class="fas fa-info-circle"></i> System Information</h6>
                            <div class="card-body">
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Checked In By:</div>
                                    <div class="col-md-3">
                                        <?php echo htmlspecialchars($checkin['receptionist_first_name'] . ' ' . $checkin['receptionist_last_name']); ?>
                                    </div>
                                    <div class="col-md-3 detail-label">Check-In Time:</div>
                                    <div class="col-md-3"><?php echo date('F j, Y g:i A', strtotime($checkin['created_at'])); ?></div>
                                </div>
                                <div class="row detail-row">
                                    <div class="col-md-3 detail-label">Status:</div>
                                    <div class="col-md-9">
                                        <span class="badge bg-success"><?php echo ucfirst($checkin['status']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="text-center mt-4 no-print">
                            <button class="btn btn-primary btn-lg" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Check-In Form
                            </button>
                            <a href="customer-checkin.php" class="btn btn-success btn-lg ms-3">
                                <i class="fas fa-plus"></i> New Check-In
                            </a>
                            <a href="receptionist.php" class="btn btn-secondary btn-lg ms-3">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Footer (only visible when printing) -->
    <div class="print-only mt-4">
        <hr>
        <div class="row">
            <div class="col-6">
                <p><strong>Customer Signature:</strong></p>
                <div style="border-bottom: 1px solid #000; height: 50px; margin-top: 20px;"></div>
                <small>Date: _______________</small>
            </div>
            <div class="col-6">
                <p><strong>Receptionist Signature:</strong></p>
                <div style="border-bottom: 1px solid #000; height: 50px; margin-top: 20px;"></div>
                <small>Date: <?php echo date('F j, Y'); ?></small>
            </div>
        </div>
        <div class="text-center mt-4">
            <small>Thank you for choosing Harar Ras Hotel. We hope you enjoy your stay!</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-print functionality (optional)
        function autoPrint() {
            if (confirm('Would you like to print the check-in form now?')) {
                window.print();
            }
        }
        
        // Uncomment the line below to auto-prompt for printing when page loads
        // window.onload = autoPrint;
    </script>
</body>
</html>