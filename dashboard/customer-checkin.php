<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('receptionist', '../login.php');

$message = '';
$error = '';
$success = false;
$checkin_id = null;

// Generate unique confirmation number
function generateConfirmationNumber() {
    return 'CHK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_checkin'])) {
    try {
        // Sanitize and validate input
        $hotel_name = sanitize_input($_POST['hotel_name']);
        $hotel_location = sanitize_input($_POST['hotel_location']);
        $check_in_date = sanitize_input($_POST['check_in_date']);
        $check_out_date = sanitize_input($_POST['check_out_date']);
        
        $guest_full_name = sanitize_input($_POST['guest_full_name']);
        $guest_date_of_birth = sanitize_input($_POST['guest_date_of_birth']);
        $guest_id_type = sanitize_input($_POST['guest_id_type']);
        $guest_id_number = sanitize_input($_POST['guest_id_number']);
        $guest_nationality = sanitize_input($_POST['guest_nationality']);
        $guest_home_address = sanitize_input($_POST['guest_home_address']);
        $guest_phone_number = sanitize_input($_POST['guest_phone_number']);
        $guest_email_address = sanitize_input($_POST['guest_email_address']);
        
        $room_type = sanitize_input($_POST['room_type']);
        $room_number = sanitize_input($_POST['room_number']);
        $nights_stay = (int)$_POST['nights_stay'];
        $number_of_guests = (int)$_POST['number_of_guests'];
        $rate_per_night = (float)$_POST['rate_per_night'];
        
        $payment_type = sanitize_input($_POST['payment_type']);
        $amount_paid = (float)$_POST['amount_paid']; // Keep as float/decimal
        $balance_due = (float)$_POST['balance_due']; // Keep as float/decimal
        $additional_requests = sanitize_input($_POST['additional_requests']);
        
        $confirmation_number = generateConfirmationNumber();
        
        // Validate required fields
        if (empty($guest_full_name) || empty($check_in_date) || empty($check_out_date)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate dates
        if (strtotime($check_out_date) <= strtotime($check_in_date)) {
            throw new Exception('Check-out date must be after check-in date.');
        }
        
        // Check if customer exists by email
        $customer_id = null;
        $customer_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'customer'");
        $customer_check->bind_param("s", $guest_email_address);
        $customer_check->execute();
        $result = $customer_check->get_result();
        
        if ($result->num_rows > 0) {
            $customer_id = $result->fetch_assoc()['id'];
        } else {
            // Create new customer
            $password = password_hash('temp123', PASSWORD_DEFAULT);
            $create_customer = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'customer', 'active', NOW())");
            
            $name_parts = explode(' ', $guest_full_name, 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            $create_customer->bind_param("sssss", $first_name, $last_name, $guest_email_address, $guest_phone_number, $password);
            $create_customer->execute();
            $customer_id = $conn->insert_id;
        }
        
        // Insert check-in record - FIXED VERSION
        $insert_checkin = $conn->prepare("
            INSERT INTO checkins (
                customer_id, hotel_name, hotel_location, check_in_date, check_out_date,
                guest_full_name, guest_date_of_birth, guest_id_type, guest_id_number, guest_nationality,
                guest_home_address, guest_phone_number, guest_email_address,
                room_type, room_number, nights_stay, number_of_guests, rate_per_night,
                payment_type, amount_paid, balance_due, confirmation_number, additional_requests,
                checked_in_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // EXACTLY 24 parameters - let me count them:
        // 1=customer_id, 2=hotel_name, 3=hotel_location, 4=check_in_date, 5=check_out_date,
        // 6=guest_full_name, 7=guest_date_of_birth, 8=guest_id_type, 9=guest_id_number, 10=guest_nationality,
        // 11=guest_home_address, 12=guest_phone_number, 13=guest_email_address,
        // 14=room_type, 15=room_number, 16=nights_stay, 17=number_of_guests, 18=rate_per_night,
        // 19=payment_type, 20=amount_paid, 21=balance_due, 22=confirmation_number, 23=additional_requests,
        // 24=checked_in_by
        
        // CORRECTED: 24 parameters with matching 24-character type string
        // Type string: i=integer, s=string, d=decimal/float
        $insert_checkin->bind_param(
            "issssssssssssssssiiddssi",
            $customer_id,           // 1 - i (integer)
            $hotel_name,            // 2 - s (string)
            $hotel_location,        // 3 - s (string)
            $check_in_date,         // 4 - s (string/date)
            $check_out_date,        // 5 - s (string/date)
            $guest_full_name,       // 6 - s (string)
            $guest_date_of_birth,   // 7 - s (string/date)
            $guest_id_type,         // 8 - s (string)
            $guest_id_number,       // 9 - s (string)
            $guest_nationality,     // 10 - s (string)
            $guest_home_address,    // 11 - s (string)
            $guest_phone_number,    // 12 - s (string)
            $guest_email_address,   // 13 - s (string)
            $room_type,             // 14 - s (string)
            $room_number,           // 15 - s (string)
            $nights_stay,           // 16 - i (integer)
            $number_of_guests,      // 17 - i (integer)
            $rate_per_night,        // 18 - d (decimal)
            $payment_type,          // 19 - s (string)
            $amount_paid,           // 20 - d (decimal)
            $balance_due,           // 21 - d (decimal)
            $confirmation_number,   // 22 - s (string)
            $additional_requests,   // 23 - s (string)
            $_SESSION['user_id']    // 24 - i (integer)
        );
        
        if ($insert_checkin->execute()) {
            $checkin_id = $conn->insert_id;
            $success = true;
            $message = "Customer check-in completed successfully! Confirmation Number: " . $confirmation_number;
        } else {
            throw new Exception('Failed to save check-in data: ' . $insert_checkin->error);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get available room types
$room_types_query = "SELECT DISTINCT name FROM rooms WHERE status = 'active' ORDER BY name";
$room_types_result = $conn->query($room_types_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Check-In Form - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .form-section {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            margin-bottom: 1.5rem;
        }
        .form-section h5 {
            background: #007bff;
            color: white;
            margin: 0;
            padding: 0.75rem 1rem;
            font-weight: 600;
        }
        .form-section .card-body {
            padding: 1.25rem;
        }
        .required {
            color: #dc3545;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .container-fluid { max-width: none !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-gold"></i> Harar Ras Hotel - Customer Check-In
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="receptionist.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <span class="navbar-text me-3">
                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-concierge-bell"></i> Reception Menu</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="receptionist.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt"></i> Dashboard Overview
                        </a>
                        <a href="customer-checkin.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-user-plus"></i> Customer Check-In
                        </a>
                        <a href="receptionist-checkout.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-minus-circle"></i> Process Check-out
                        </a>
                        <a href="receptionist-rooms.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-bed"></i> Manage Rooms
                        </a>
                        <a href="receptionist-services.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-utensils"></i> Manage Foods & Services
                        </a>
                        <a href="../generate_bill.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-invoice-dollar"></i> Generate Bill
                        </a>
                        </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <?php if ($success && $checkin_id): ?>
                <!-- Success Message and Redirect -->
                <div class="alert alert-success no-print">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    <div class="mt-3">
                        <a href="checkin-details.php?id=<?php echo $checkin_id; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Check-In Details
                        </a>
                        <a href="customer-checkin.php" class="btn btn-secondary">
                            <i class="fas fa-plus"></i> New Check-In
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger no-print">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-user-plus"></i> Hotel Check-In Form
                        </h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="checkinForm">
                            <!-- Hotel Information Section -->
                            <div class="form-section card">
                                <h5><i class="fas fa-hotel"></i> Hotel Information</h5>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="hotel_name" class="form-label">Hotel Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="hotel_name" name="hotel_name" 
                                                   value="Harar Ras Hotel" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="hotel_location" class="form-label">Location <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="hotel_location" name="hotel_location" 
                                                   value="Jugol Street, Harar, Ethiopia" required>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label for="check_in_date" class="form-label">Check-In Date <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="check_in_date" name="check_in_date" 
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="check_out_date" class="form-label">Check-Out Date <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="check_out_date" name="check_out_date" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Information Section -->
                            <div class="form-section card">
                                <h5><i class="fas fa-user"></i> Customer Information</h5>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="guest_full_name" class="form-label">Full Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="guest_full_name" name="guest_full_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="guest_date_of_birth" class="form-label">Date of Birth <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="guest_date_of_birth" name="guest_date_of_birth" required>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <label for="guest_id_type" class="form-label">ID Type <span class="required">*</span></label>
                                            <select class="form-select" id="guest_id_type" name="guest_id_type" required>
                                                <option value="">Select ID Type</option>
                                                <option value="passport">Passport</option>
                                                <option value="drivers_license">Driver's License</option>
                                                <option value="national_id">National ID</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="guest_id_number" class="form-label">ID Number <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="guest_id_number" name="guest_id_number" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="guest_nationality" class="form-label">Nationality <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="guest_nationality" name="guest_nationality" required>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <label for="guest_home_address" class="form-label">Home Address <span class="required">*</span></label>
                                            <textarea class="form-control" id="guest_home_address" name="guest_home_address" rows="2" required></textarea>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label for="guest_phone_number" class="form-label">Phone Number <span class="required">*</span></label>
                                            <input type="tel" class="form-control" id="guest_phone_number" name="guest_phone_number" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="guest_email_address" class="form-label">Email Address <span class="required">*</span></label>
                                            <input type="email" class="form-control" id="guest_email_address" name="guest_email_address" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stay Details Section -->
                            <div class="form-section card">
                                <h5><i class="fas fa-bed"></i> Stay Details</h5>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label for="room_type" class="form-label">Room Type <span class="required">*</span></label>
                                            <select class="form-select" id="room_type" name="room_type" required>
                                                <option value="">Select Room Type</option>
                                                <?php while ($room_type = $room_types_result->fetch_assoc()): ?>
                                                <option value="<?php echo htmlspecialchars($room_type['name']); ?>">
                                                    <?php echo htmlspecialchars($room_type['name']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="room_number" class="form-label">Room Number</label>
                                            <input type="text" class="form-control" id="room_number" name="room_number">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="nights_stay" class="form-label">Nights Stay <span class="required">*</span></label>
                                            <input type="number" class="form-control" id="nights_stay" name="nights_stay" min="1" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="number_of_guests" class="form-label">Number of Guests <span class="required">*</span></label>
                                            <input type="number" class="form-control" id="number_of_guests" name="number_of_guests" min="1" value="1" required>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label for="rate_per_night" class="form-label">Rate per Night (ETB) <span class="required">*</span></label>
                                            <input type="number" class="form-control" id="rate_per_night" name="rate_per_night" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Details Section -->
                            <div class="form-section card">
                                <h5><i class="fas fa-credit-card"></i> Payment Details</h5>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label for="payment_type" class="form-label">Payment Type <span class="required">*</span></label>
                                            <select class="form-select" id="payment_type" name="payment_type" required>
                                                <option value="">Select Payment Type</option>
                                                <option value="cash">Cash</option>
                                                <option value="credit_card">Credit Card</option>
                                                <option value="debit_card">Debit Card</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="mobile_payment">Mobile Payment</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="amount_paid" class="form-label">Amount Paid (ETB) <span class="required">*</span></label>
                                            <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" min="0" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="balance_due" class="form-label">Balance Due (ETB)</label>
                                            <input type="number" class="form-control" id="balance_due" name="balance_due" step="0.01" min="0" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Requests Section -->
                            <div class="form-section card">
                                <h5><i class="fas fa-comment"></i> Additional Requests</h5>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <label for="additional_requests" class="form-label">Additional Requests</label>
                                            <textarea class="form-control" id="additional_requests" name="additional_requests" rows="3" 
                                                      placeholder="Any special requests or notes..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center no-print">
                                <button type="submit" name="submit_checkin" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle"></i> Complete Check-In
                                </button>
                                <a href="receptionist.php" class="btn btn-secondary btn-lg ms-3">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate nights stay when dates change
        document.getElementById('check_in_date').addEventListener('change', calculateNights);
        document.getElementById('check_out_date').addEventListener('change', calculateNights);
        
        function calculateNights() {
            const checkinDate = new Date(document.getElementById('check_in_date').value);
            const checkoutDate = new Date(document.getElementById('check_out_date').value);
            
            if (checkinDate && checkoutDate && checkoutDate > checkinDate) {
                const timeDiff = checkoutDate.getTime() - checkinDate.getTime();
                const nights = Math.ceil(timeDiff / (1000 * 3600 * 24));
                document.getElementById('nights_stay').value = nights;
                calculateTotal();
            }
        }
        
        // Auto-calculate balance due
        document.getElementById('rate_per_night').addEventListener('input', calculateTotal);
        document.getElementById('nights_stay').addEventListener('input', calculateTotal);
        document.getElementById('amount_paid').addEventListener('input', calculateTotal);
        
        function calculateTotal() {
            const ratePerNight = parseFloat(document.getElementById('rate_per_night').value) || 0;
            const nights = parseInt(document.getElementById('nights_stay').value) || 0;
            const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
            
            const totalAmount = ratePerNight * nights;
            const balanceDue = Math.max(0, totalAmount - amountPaid);
            
            document.getElementById('balance_due').value = balanceDue.toFixed(2);
        }
        
        // Form validation
        document.getElementById('checkinForm').addEventListener('submit', function(e) {
            const checkinDate = new Date(document.getElementById('check_in_date').value);
            const checkoutDate = new Date(document.getElementById('check_out_date').value);
            
            if (checkoutDate <= checkinDate) {
                e.preventDefault();
                alert('Check-out date must be after check-in date.');
                return false;
            }
            
            // Confirm submission
            if (!confirm('Are you sure you want to complete this check-in?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>