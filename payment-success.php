<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Get booking ID from URL
$booking_id = $_GET['booking'] ?? 0;

if (!$booking_id) {
    header('Location: my-bookings.php');
    exit();
}

// Get booking details
$query = "SELECT b.*, r.name as room_name, r.room_number, r.room_type,
          pmi.method_name, pmi.bank_name, u.email, u.first_name, u.last_name
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id
          LEFT JOIN payment_method_instructions pmi ON b.payment_method = pmi.method_code
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ? AND b.user_id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Database error: ' . $conn->error);
}
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking_data = $stmt->get_result()->fetch_assoc();

if (!$booking_data) {
    header('Location: my-bookings.php');
    exit();
}

// Check if payment was actually submitted
if ($booking_data['verification_status'] !== 'pending_verification') {
    header('Location: payment-upload.php?booking=' . $booking_id);
    exit();
}

$success_message = '';
$error_message = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $overall_rating = (int)($_POST['overall_rating'] ?? 0);
    $service_quality = (int)($_POST['service_quality'] ?? 0);
    $cleanliness = (int)($_POST['cleanliness'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');
    
    // Validate ratings
    if ($overall_rating >= 1 && $overall_rating <= 5 && 
        $service_quality >= 1 && $service_quality <= 5 && 
        $cleanliness >= 1 && $cleanliness <= 5) {
        
        // Check if feedback already exists
        $check_query = "SELECT id FROM customer_feedback WHERE booking_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $existing_feedback = $check_stmt->get_result()->fetch_assoc();
        
        if (!$existing_feedback) {
            // Insert feedback
            $feedback_query = "INSERT INTO customer_feedback 
                              (booking_id, customer_id, overall_rating, service_quality, cleanliness, comments) 
                              VALUES (?, ?, ?, ?, ?, ?)";
            $feedback_stmt = $conn->prepare($feedback_query);
            $feedback_stmt->bind_param("iiiiss", $booking_id, $_SESSION['user_id'], 
                                     $overall_rating, $service_quality, $cleanliness, $comments);
            
            if ($feedback_stmt->execute()) {
                $success_message = 'Thank you for your feedback! Your review has been submitted successfully.';
            } else {
                $error_message = 'Error submitting feedback. Please try again.';
            }
        } else {
            $error_message = 'You have already submitted feedback for this booking.';
        }
    } else {
        $error_message = 'Please provide valid ratings (1-5 stars) for all categories.';
    }
}

// Check if feedback already submitted
$feedback_query = "SELECT * FROM customer_feedback WHERE booking_id = ?";
$feedback_stmt = $conn->prepare($feedback_query);
$feedback_stmt->bind_param("i", $booking_id);
$feedback_stmt->execute();
$existing_feedback = $feedback_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --gold: #D4AF37;
            --dark-gold: #B8941F;
        }
        
        .text-gold { color: var(--gold) !important; }
        .bg-gold { background-color: var(--gold) !important; }
        .btn-gold { 
            background-color: var(--gold); 
            border-color: var(--gold); 
            color: white;
        }
        .btn-gold:hover { 
            background-color: var(--dark-gold); 
            border-color: var(--dark-gold); 
            color: white;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            animation: scaleIn 0.6s ease;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        
        .booking-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .star-rating {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }
        
        .star {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .star:hover,
        .star.active {
            color: var(--gold);
        }
        
        .feedback-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: #e9ecef;
            color: #6c757d;
        }
        
        .step.completed {
            background: var(--gold);
            color: white;
        }
        
        .step.current {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <section class="py-5">
        <div class="container">
            <!-- Back Button -->
            <div class="row">
                <div class="col-12 mb-3">
                    <button onclick="history.back()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
            </div>
            
            <!-- Success Header -->
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <div class="success-icon mb-3">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="h2 mb-3">Payment Submitted Successfully!</h1>
                    <div class="alert alert-success border-success mb-4">
                        <p class="lead mb-0">Your payment screenshot has been uploaded successfully. Please wait while we verify your payment. Once it is confirmed, we will send a verification message to your email: <strong><?php echo htmlspecialchars($booking_data['email'] ?? 'your registered email'); ?></strong></p>
                    </div>
                </div>
            </div>
            
            <!-- Step Indicator -->
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8">
                    <div class="step-indicator">
                        <div class="step completed">
                            <i class="fas fa-credit-card"></i>
                            <span>Payment Submitted</span>
                        </div>
                        <div class="step current">
                            <i class="fas fa-clock"></i>
                            <span>Verification (30 min)</span>
                        </div>
                        <div class="step">
                            <i class="fas fa-check"></i>
                            <span>Confirmed</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Booking Summary -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="booking-summary">
                        <h5 class="mb-3"><i class="fas fa-receipt text-gold"></i> Booking Summary</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Booking Reference:</strong><br><?php echo htmlspecialchars($booking_data['booking_reference']); ?></p>
                                <?php if ($booking_data['booking_type'] === 'room'): ?>
                                    <p><strong>Room:</strong><br><?php echo htmlspecialchars($booking_data['room_name']); ?> (<?php echo htmlspecialchars($booking_data['room_number']); ?>)</p>
                                    <p><strong>Check-in:</strong><br><?php echo date('M d, Y', strtotime($booking_data['check_in_date'])); ?></p>
                                    <p><strong>Check-out:</strong><br><?php echo date('M d, Y', strtotime($booking_data['check_out_date'])); ?></p>
                                <?php else: ?>
                                    <p><strong>Service:</strong><br><?php echo ucfirst(str_replace('_', ' ', $booking_data['booking_type'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Payment Method:</strong><br><?php echo htmlspecialchars($booking_data['method_name'] ?? ucfirst($booking_data['payment_method'])); ?></p>
                                <p><strong>Total Amount:</strong><br><span class="h5 text-success"><?php echo number_format($booking_data['total_price'], 2); ?> ETB</span></p>
                                <p><strong>Status:</strong><br><span class="badge bg-warning">Pending Verification</span></p>
                                <p><strong>Submitted:</strong><br><?php echo date('M d, Y g:i A', strtotime($booking_data['screenshot_uploaded_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Feedback Section -->
            <?php if (!$existing_feedback): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="feedback-section">
                        <h5 class="mb-3"><i class="fas fa-star text-gold"></i> Share Your Experience</h5>
                        <p class="text-muted mb-4">While we verify your payment, we'd love to hear about your booking experience so far!</p>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="feedbackForm">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Overall Experience</label>
                                    <div class="star-rating" data-rating="overall_rating">
                                        <i class="fas fa-star star" data-value="1"></i>
                                        <i class="fas fa-star star" data-value="2"></i>
                                        <i class="fas fa-star star" data-value="3"></i>
                                        <i class="fas fa-star star" data-value="4"></i>
                                        <i class="fas fa-star star" data-value="5"></i>
                                    </div>
                                    <input type="hidden" name="overall_rating" id="overall_rating" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Service Quality</label>
                                    <div class="star-rating" data-rating="service_quality">
                                        <i class="fas fa-star star" data-value="1"></i>
                                        <i class="fas fa-star star" data-value="2"></i>
                                        <i class="fas fa-star star" data-value="3"></i>
                                        <i class="fas fa-star star" data-value="4"></i>
                                        <i class="fas fa-star star" data-value="5"></i>
                                    </div>
                                    <input type="hidden" name="service_quality" id="service_quality" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Booking Process</label>
                                    <div class="star-rating" data-rating="cleanliness">
                                        <i class="fas fa-star star" data-value="1"></i>
                                        <i class="fas fa-star star" data-value="2"></i>
                                        <i class="fas fa-star star" data-value="3"></i>
                                        <i class="fas fa-star star" data-value="4"></i>
                                        <i class="fas fa-star star" data-value="5"></i>
                                    </div>
                                    <input type="hidden" name="cleanliness" id="cleanliness" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="comments" class="form-label">Additional Comments (Optional)</label>
                                <textarea class="form-control" id="comments" name="comments" rows="3" 
                                         placeholder="Tell us about your booking experience..."></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" name="submit_feedback" class="btn btn-gold btn-lg">
                                    <i class="fas fa-paper-plane"></i> Submit Feedback
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="feedback-section text-center">
                        <i class="fas fa-heart text-danger mb-3" style="font-size: 2rem;"></i>
                        <h5>Thank You for Your Feedback!</h5>
                        <p class="text-muted">You have already submitted feedback for this booking.</p>
                        <div class="mt-3">
                            <strong>Your Rating:</strong>
                            <div class="d-inline-flex align-items-center ms-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $existing_feedback['overall_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-2">(<?php echo $existing_feedback['overall_rating']; ?>/5)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Next Steps -->
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> What's Next?</h6>
                        <ul class="mb-0">
                            <li>Our staff will verify your payment within 30 minutes</li>
                            <li>You'll receive a verification message at your email address once verified</li>
                            <li>Check your booking status in "My Bookings"</li>
                            <li>Contact us if you have any questions</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8 text-center">
                    <a href="my-bookings.php" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-list"></i> View My Bookings
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg me-3">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                    <button onclick="history.back()" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </button>
                </div>
            </div>
        </div>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Star rating functionality
        document.querySelectorAll('.star-rating').forEach(rating => {
            const stars = rating.querySelectorAll('.star');
            const inputName = rating.dataset.rating;
            const hiddenInput = document.getElementById(inputName);
            
            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    const value = index + 1;
                    hiddenInput.value = value;
                    
                    // Update visual state
                    stars.forEach((s, i) => {
                        if (i < value) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
                
                star.addEventListener('mouseenter', () => {
                    stars.forEach((s, i) => {
                        if (i <= index) {
                            s.style.color = 'var(--gold)';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });
            
            rating.addEventListener('mouseleave', () => {
                const currentValue = parseInt(hiddenInput.value) || 0;
                stars.forEach((s, i) => {
                    if (i < currentValue) {
                        s.style.color = 'var(--gold)';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        });
        
        // Form validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const ratings = ['overall_rating', 'service_quality', 'cleanliness'];
            let valid = true;
            
            ratings.forEach(rating => {
                const input = document.getElementById(rating);
                if (!input.value || input.value < 1 || input.value > 5) {
                    valid = false;
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please provide ratings for all categories (1-5 stars).');
            }
        });
    </script>
</body>
</html>