<?php
/**
 * Payment Transaction ID Upload
 * New payment system using transaction ID verification instead of screenshots
 */

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/services/PaymentGatewayService.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php?redirect=payment-transaction');
    exit();
}

$booking_id = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
$error = '';
$success = '';
$loading = false;

if (!$booking_id) {
    header('Location: index.php');
    exit();
}

// Get booking details
$query = "SELECT b.*, 
          COALESCE(r.name, 'Food Order') as room_name, 
          COALESCE(r.room_number, 'N/A') as room_number, 
          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
          u.email as email
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id 
          JOIN users u ON b.user_id = u.id 
          WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: index.php');
    exit();
}

// Check if booking is eligible for payment
if (!in_array($booking['verification_status'], ['pending_payment', 'rejected'])) {
    $error = 'This booking is not eligible for payment submission.';
}

// Initialize payment gateway service
$paymentService = new PaymentGatewayService($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    $transaction_id = trim(sanitize_input($_POST['transaction_id']));
    $payment_method = sanitize_input($_POST['payment_method']);
    
    if (empty($transaction_id)) {
        $error = 'Please enter a transaction ID.';
    } else {
        $loading = true;
        
        // Verify transaction
        $verification_result = $paymentService->verifyTransaction(
            $transaction_id, 
            $booking['total_price'], 
            $booking_id
        );
        
        if ($verification_result['success']) {
            // Update booking with transaction details
            $verified = $verification_result['verified'] ?? false;
            $new_status = $verified ? 'verified' : 'pending_verification';
            
            $update_query = "UPDATE bookings SET 
                            transaction_id = ?,
                            payment_method = ?,
                            transaction_verified = ?,
                            transaction_amount = ?,
                            transaction_date = NOW(),
                            payment_gateway = ?,
                            verification_status = ?,
                            screenshot_uploaded_at = NOW()
                            WHERE id = ?";
            
            $stmt = $conn->prepare($update_query);
            $gateway = $verification_result['gateway'] ?? 'manual';
            $stmt->bind_param("ssiissi", $transaction_id, $payment_method, $verified, 
                             $booking['total_price'], $gateway, $new_status, $booking_id);
            
            if ($stmt->execute()) {
                if ($verified) {
                    $success = 'Payment verified successfully! Your booking is now confirmed.';
                } else {
                    $success = 'Transaction ID received successfully! Your payment will be verified manually within 30 minutes.';
                }
                
                // Refresh booking data
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
                $stmt->execute();
                $booking = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update booking. Please try again.';
            }
        } else {
            $error = $verification_result['message'];
        }
        
        $loading = false;
    }
}

// Get available payment methods
$payment_methods_query = "SELECT * FROM payment_method_instructions WHERE is_active = 1 ORDER BY display_order";
$payment_methods = $conn->query($payment_methods_query)->fetch_all(MYSQLI_ASSOC);

// Get gateway configurations for display
$gateways_query = "SELECT * FROM payment_gateway_config WHERE is_active = TRUE ORDER BY gateway_name";
$gateways = $conn->query($gateways_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payment-card {
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .gateway-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .gateway-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0,123,255,0.2);
            transform: translateY(-2px);
        }
        
        .gateway-card.selected {
            border-color: #007bff;
            background: #e3f2fd;
            box-shadow: 0 4px 12px rgba(0,123,255,0.3);
        }
        
        .transaction-input {
            font-size: 1.1rem;
            padding: 15px;
            border: 2px solid #ced4da;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
        }
        
        .transaction-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .verification-tips {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border: 2px solid #17a2b8;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .status-verified {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .status-pending {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .gateway-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-test {
            background: #ffc107;
            color: #000;
        }
        
        .badge-live {
            background: #28a745;
            color: #fff;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Loading Overlay -->
    <?php if ($loading): ?>
    <div class="loading-overlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h4>Verifying Transaction...</h4>
            <p class="text-muted">Please wait while we verify your payment</p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Page Header -->
    <section class="py-4 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="my-bookings.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="col text-center">
                    <h2 class="mb-0"><i class="fas fa-shield-alt"></i> Secure Payment Verification</h2>
                    <p class="mb-0">Enter your transaction ID for instant verification</p>
                </div>
                <div class="col-auto"></div>
            </div>
        </div>
    </section>
    
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Booking Details -->
                    <div class="payment-card">
                        <h5><i class="fas fa-info-circle text-primary"></i> Booking Details</h5>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p><strong>Reference:</strong> <code><?php echo $booking['booking_reference']; ?></code></p>
                                <p><strong>Room/Service:</strong> <?php echo $booking['room_name']; ?></p>
                                <p><strong>Customer:</strong> <?php echo $booking['customer_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total Amount:</strong> <span class="h4 text-success"><?php echo format_currency($booking['total_price']); ?></span></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $booking['verification_status'] == 'verified' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $booking['verification_status'])); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <strong>Success!</strong> <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Payment Verified Status -->
                    <?php if ($booking['transaction_verified']): ?>
                    <div class="status-verified">
                        <h4><i class="fas fa-check-circle"></i> Payment Verified!</h4>
                        <p class="mb-2">Your payment has been successfully verified.</p>
                        <p class="mb-0"><strong>Transaction ID:</strong> <code><?php echo $booking['transaction_id']; ?></code></p>
                        <p class="mb-0"><strong>Gateway:</strong> <?php echo strtoupper($booking['payment_gateway']); ?></p>
                        <div class="mt-3">
                            <a href="my-bookings.php" class="btn btn-success">
                                <i class="fas fa-list"></i> View My Bookings
                            </a>
                        </div>
                    </div>
                    <?php elseif ($booking['verification_status'] == 'pending_verification' && $booking['transaction_id']): ?>
                    <div class="status-pending">
                        <h4><i class="fas fa-clock"></i> Verification Pending</h4>
                        <p class="mb-2">Your transaction is being verified. This usually takes up to 30 minutes.</p>
                        <p class="mb-0"><strong>Transaction ID:</strong> <code><?php echo $booking['transaction_id']; ?></code></p>
                        <p class="mb-0">You will receive an email notification once verification is complete.</p>
                    </div>
                    <?php else: ?>
                    
                    <!-- Payment Form -->
                    <div class="card shadow-lg">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="fas fa-credit-card"></i> Enter Transaction Details</h4>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" id="paymentForm">
                                <!-- Payment Method Selection -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-university"></i> Select Payment Method
                                    </label>
                                    <div class="row">
                                        <?php foreach ($gateways as $gateway): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="gateway-card" onclick="selectGateway('<?php echo $gateway['gateway_name']; ?>')">
                                                <input type="radio" name="payment_method" value="<?php echo $gateway['gateway_name']; ?>" 
                                                       id="gateway_<?php echo $gateway['gateway_name']; ?>" class="d-none" required>
                                                <h6 class="mb-2">
                                                    <?php echo strtoupper(str_replace('_', ' ', $gateway['gateway_name'])); ?>
                                                    <?php if ($gateway['is_test_mode']): ?>
                                                        <span class="gateway-badge badge-test">TEST MODE</span>
                                                    <?php else: ?>
                                                        <span class="gateway-badge badge-live">LIVE</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="text-muted small mb-0">
                                                    Transaction ID format: <code><?php echo $gateway['transaction_prefix']; ?>XXXXXXXXX</code>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Transaction ID Input -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-hashtag"></i> Transaction ID *
                                    </label>
                                    <input type="text" 
                                           name="transaction_id" 
                                           id="transactionId"
                                           class="form-control transaction-input" 
                                           placeholder="Enter your transaction ID (e.g., TB1234567890)"
                                           required
                                           autocomplete="off">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> Enter the transaction ID from your payment confirmation
                                    </small>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check-circle"></i> Verify Payment
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Verification Tips -->
                            <div class="verification-tips mt-4">
                                <h6><i class="fas fa-lightbulb"></i> How to Find Your Transaction ID:</h6>
                                <ul class="mb-0">
                                    <li><strong>Telebirr:</strong> Check your SMS or app notification after payment</li>
                                    <li><strong>CBE Birr:</strong> Look for the reference number in your transaction history</li>
                                    <li><strong>Bank Transfer:</strong> Use the transaction reference from your receipt</li>
                                    <li><strong>Mobile Money:</strong> Check the confirmation message</li>
                                </ul>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> 
                                    Make sure you complete the payment first before entering the transaction ID.
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectGateway(gatewayName) {
            // Remove selected class from all cards
            document.querySelectorAll('.gateway-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById('gateway_' + gatewayName).checked = true;
        }
        
        // Form submission with loading
        document.getElementById('paymentForm').addEventListener('submit', function() {
            // Show loading overlay
            const loadingHtml = `
                <div class="loading-overlay">
                    <div class="loading-content">
                        <div class="spinner"></div>
                        <h4>Verifying Transaction...</h4>
                        <p class="text-muted">Please wait while we verify your payment</p>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', loadingHtml);
        });
    </script>
</body>
</html>
