<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$booking_data = null;

// Get booking reference from URL or session
$booking_reference = isset($_GET['ref']) ? sanitize_input($_GET['ref']) : (isset($_SESSION['pending_booking']) ? $_SESSION['pending_booking'] : '');

if ($booking_reference) {
    // Get booking details
    $booking_data = get_booking_by_reference($booking_reference);
    if (!$booking_data) {
        $error = 'Booking not found';
    }
} else {
    $error = 'No booking reference provided';
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    $payment_method = sanitize_input($_POST['payment_method']);
    $transaction_id = sanitize_input($_POST['transaction_id']);
    $payment_notes = sanitize_input($_POST['payment_notes']);
    
    // Handle file upload
    $receipt_filename = '';
    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] == 0) {
        $upload_dir = 'uploads/payment_receipts/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION);
        $receipt_filename = $booking_reference . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $receipt_filename;
        
        if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $upload_path)) {
            // Insert payment record
            $query = "INSERT INTO payments (booking_id, payment_method, transaction_id, amount, 
                     receipt_filename, payment_notes, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 'pending_verification', NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issdss", $booking_data['id'], $payment_method, $transaction_id, 
                             $booking_data['total_price'], $receipt_filename, $payment_notes);
            
            if ($stmt->execute()) {
                // Update booking status to confirmed
                $conn->query("UPDATE bookings SET status = 'confirmed' WHERE id = " . $booking_data['id']);
                
                $success = 'Payment submitted successfully! Your booking is now confirmed.';
                unset($_SESSION['pending_booking']);
                
                // Redirect to payment-upload to show pending verification with feedback form
                header('Location: payment-upload.php?booking=' . $booking_data['id']);
                exit();
            } else {
                $error = 'Failed to record payment: ' . $stmt->error;
            }
        } else {
            $error = 'Failed to upload receipt file';
        }
    } else {
        $error = 'Please upload your payment receipt';
    }
}

// Create payments table if it doesn't exist
$create_payments_table = "CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method VARCHAR(100) NOT NULL,
    transaction_id VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    receipt_filename VARCHAR(255),
    payment_notes TEXT,
    status ENUM('pending_verification', 'verified', 'rejected') DEFAULT 'pending_verification',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_payments_table);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payment-header {
            background: linear-gradient(135deg, #d4af37, #f4d03f);
            color: #333;
            padding: 40px 0;
        }
        .bank-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .bank-option:hover {
            border-color: #d4af37;
            background: #fefefe;
        }
        .bank-option.selected {
            border-color: #d4af37;
            background: #fff8dc;
        }
        .bank-logo {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #d4af37;
        }
        .upload-area {
            border: 2px dashed #d4af37;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #fefefe;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background: #fff8dc;
        }
        .upload-area.dragover {
            border-color: #b8941f;
            background: #fff8dc;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="payment-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="booking.php" class="btn btn-outline-dark">
                        <i class="fas fa-arrow-left"></i> Back to Booking
                    </a>
                </div>
                <div class="col text-center">
                    <h1><i class="fas fa-credit-card"></i> Complete Your Payment</h1>
                    <p class="lead">Secure payment for your Harar Ras Hotel booking</p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <h4><i class="fas fa-check-circle"></i> Payment Successful!</h4>
                <p><?php echo $success; ?></p>
                <div class="mt-3">
                    <a href="customer-feedback.php?booking_ref=<?php echo urlencode($booking_reference); ?>&payment_id=<?php echo urlencode($booking_data['payment_reference'] ?? ''); ?>" class="btn btn-primary me-2">
                        <i class="fas fa-star"></i> Share Your Feedback
                    </a>
                    <a href="booking-confirmation.php?ref=<?php echo $booking_reference; ?>" class="btn btn-success">
                        <i class="fas fa-file-alt"></i> View Booking Confirmation
                    </a>
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>
            </div>
        <?php elseif ($booking_data): ?>
            <div class="row">
                <!-- Booking Summary -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-receipt"></i> Booking Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><td><strong>Booking Ref:</strong></td><td><?php echo $booking_data['booking_reference']; ?></td></tr>
                                <tr><td><strong>Room:</strong></td><td><?php echo $booking_data['room_name']; ?></td></tr>
                                <tr><td><strong>Customer:</strong></td><td><?php echo $booking_data['first_name'] . ' ' . $booking_data['last_name']; ?></td></tr>
                                <tr><td><strong>Check-in:</strong></td><td><?php echo date('M j, Y', strtotime($booking_data['check_in_date'])); ?></td></tr>
                                <tr><td><strong>Check-out:</strong></td><td><?php echo date('M j, Y', strtotime($booking_data['check_out_date'])); ?></td></tr>
                                <tr><td><strong>Customers:</strong></td><td><?php echo $booking_data['customers']; ?></td></tr>
                                <tr class="table-warning"><td><strong>Total Amount:</strong></td><td><strong><?php echo format_currency($booking_data['total_price']); ?></strong></td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5><i class="fas fa-university"></i> Select Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <!-- Ethiopian Banks -->
                                <div class="mb-4">
                                    <h6 class="mb-3">Choose Your Bank:</h6>
                                    
                                    <div class="bank-option" onclick="selectBank('telebirr')">
                                        <div class="d-flex align-items-center">
                                            <div class="bank-logo me-3">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">TeleBirr</h6>
                                                <small class="text-muted">Mobile Money - Ethio Telecom</small>
                                                <div class="mt-1"><small><strong>Account:</strong> 0911-123-456</small></div>
                                            </div>
                                        </div>
                                        <input type="radio" name="payment_method" value="TeleBirr" class="d-none">
                                    </div>

                                    <div class="bank-option" onclick="selectBank('cbe')">
                                        <div class="d-flex align-items-center">
                                            <div class="bank-logo me-3">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Commercial Bank of Ethiopia (CBE)</h6>
                                                <small class="text-muted">CBE Mobile Banking</small>
                                                <div class="mt-1"><small><strong>Account:</strong> 1000-1234-5678-90</small></div>
                                            </div>
                                        </div>
                                        <input type="radio" name="payment_method" value="CBE Mobile Banking" class="d-none">
                                    </div>

                                    <div class="bank-option" onclick="selectBank('awash')">
                                        <div class="d-flex align-items-center">
                                            <div class="bank-logo me-3">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Awash Bank</h6>
                                                <small class="text-muted">Awash Mobile Banking</small>
                                                <div class="mt-1"><small><strong>Account:</strong> 2000-9876-5432-10</small></div>
                                            </div>
                                        </div>
                                        <input type="radio" name="payment_method" value="Awash Bank Mobile" class="d-none">
                                    </div>

                                    <div class="bank-option" onclick="selectBank('abyssinia')">
                                        <div class="d-flex align-items-center">
                                            <div class="bank-logo me-3">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Bank of Abyssinia</h6>
                                                <small class="text-muted">Abay Mobile Banking</small>
                                                <div class="mt-1"><small><strong>Account:</strong> 3000-1111-2222-33</small></div>
                                            </div>
                                        </div>
                                        <input type="radio" name="payment_method" value="Bank of Abyssinia Mobile" class="d-none">
                                    </div>

                                    <div class="bank-option" onclick="selectBank('dashen')">
                                        <div class="d-flex align-items-center">
                                            <div class="bank-logo me-3">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Dashen Bank</h6>
                                                <small class="text-muted">Dashen Mobile Banking</small>
                                                <div class="mt-1"><small><strong>Account:</strong> 4000-5555-6666-77</small></div>
                                            </div>
                                        </div>
                                        <input type="radio" name="payment_method" value="Dashen Bank Mobile" class="d-none">
                                    </div>
                                </div>

                                <!-- Transaction Details -->
                                <div class="mb-4">
                                    <h6 class="mb-3">Payment Details:</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Transaction ID / Reference Number:</label>
                                            <input type="text" name="transaction_id" class="form-control" 
                                                   placeholder="Enter transaction ID from your bank" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Payment Notes (Optional):</label>
                                            <input type="text" name="payment_notes" class="form-control" 
                                                   placeholder="Any additional notes">
                                        </div>
                                    </div>
                                </div>

                                <!-- Receipt Upload -->
                                <div class="mb-4">
                                    <h6 class="mb-3">Upload Payment Receipt:</h6>
                                    <div class="upload-area" onclick="document.getElementById('receipt-file').click()">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <h6>Click to Upload Receipt</h6>
                                        <p class="text-muted mb-0">Supported formats: JPG, PNG, WebP (Max 5MB)</p>
                                        <input type="file" id="receipt-file" name="payment_receipt" 
                                               accept="image/jpeg,image/jpg,image/png,image/webp" style="display: none;" required>
                                    </div>
                                    <div id="file-info" class="mt-2"></div>
                                </div>

                                <!-- Payment Instructions -->
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Payment Instructions:</h6>
                                    <ol class="mb-0">
                                        <li>Select your preferred bank above</li>
                                        <li>Transfer <strong><?php echo format_currency($booking_data['total_price']); ?></strong> to the provided account</li>
                                        <li>Enter the transaction ID/reference number</li>
                                        <li>Optionally upload a screenshot or photo of your payment receipt</li>
                                        <li>Click "Submit Payment" to complete your booking</li>
                                    </ol>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="booking.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Booking
                                    </a>
                                    <button type="submit" name="submit_payment" class="btn btn-success btn-lg">
                                        <i class="fas fa-check"></i> Submit Payment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectBank(bankType) {
            // Remove selected class from all options
            document.querySelectorAll('.bank-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }

        // File upload handling
        document.getElementById('receipt-file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileInfo = document.getElementById('file-info');
            
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                fileInfo.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-file"></i> Selected: ${file.name} (${fileSize} MB)
                    </div>
                `;
            }
        });

        // Drag and drop functionality
        const uploadArea = document.querySelector('.upload-area');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('receipt-file').files = files;
                document.getElementById('receipt-file').dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>