<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = __('contact.error_fields');
    } elseif (!validate_email($email)) {
        $error = __('contact.error_email');
    } else {
        // Save contact message to database
        $query = "INSERT INTO contact_messages (name, email, phone, subject, message, created_at) 
                  VALUES ('$name', '$email', '$phone', '$subject', '$message', NOW())";
        
        if ($conn->query($query)) {
            $success = 'Thank you for contacting us! We will get back to you soon.';
            
            // Optional: Send email notification to admin
            $admin_email = ADMIN_EMAIL;
            $email_subject = "New Contact Message: " . $subject;
            $email_body = "
                <h3>New Contact Message from Website</h3>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Phone:</strong> $phone</p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Message:</strong></p>
                <p>$message</p>
                <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
            ";
            
            send_email($admin_email, $email_subject, $email_body);
        } else {
            $error = __('contact.error_send');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('contact.title'); ?> - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> <?php echo __('contact.back_to_home'); ?>
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-4 fw-bold mb-3"><?php echo __('contact.title'); ?></h1>
                    <p class="lead text-muted"><?php echo __('contact.subtitle'); ?></p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    

    
    <!-- Contact Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Contact Form -->
                <div class="col-lg-7 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="mb-4"><?php echo __('contact.send_message'); ?></h3>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('contact.your_name'); ?> *</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('contact.email_address'); ?> *</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('contact.phone_number'); ?></label>
                                        <input type="tel" name="phone" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('contact.subject'); ?> *</label>
                                        <input type="text" name="subject" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('contact.message'); ?> *</label>
                                    <textarea name="message" class="form-control" rows="5" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-gold">
                                    <i class="fas fa-paper-plane"></i> <?php echo __('contact.send_btn'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                


                <!-- Contact Information -->
                <div class="col-lg-5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h4 class="mb-4"><?php echo __('contact.contact_info'); ?></h4>
                            
                            <div class="mb-4">
                                <div class="d-flex mb-3">
                                    <div class="text-gold me-3">
                                        <i class="fas fa-map-marker-alt fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo __('contact.address'); ?></h6>
                                        <p class="text-muted mb-0">Harar, Ethiopia<br>Near Jugol Walls</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex mb-3">
                                    <div class="text-gold me-3">
                                        <i class="fas fa-phone fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo __('contact.phone'); ?></h6>
                                        <p class="text-muted mb-0">+251 25 666 1234</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex mb-3">
                                    <div class="text-gold me-3">
                                        <i class="fas fa-envelope fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo __('contact.email'); ?></h6>
                                        <p class="text-muted mb-0">info@hararrashotel.com</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex">
                                    <div class="text-gold me-3">
                                        <i class="fas fa-clock fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo __('contact.reception_hours'); ?></h6>
                                        <p class="text-muted mb-0"><?php echo __('contact.available_24_7'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            


                            <h6 class="mb-3"><?php echo __('contact.follow_us'); ?></h6>
                            <div class="social-links">
                                <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="mb-3"><?php echo __('contact.quick_booking'); ?></h5>
                            <p class="text-muted"><?php echo __('contact.ready_to_book'); ?></p>
                            <a href="booking.php" class="btn btn-gold w-100">
                                <i class="fas fa-calendar-check"></i> Book Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Map Section (Optional) -->
    <section class="py-5 bg-light">
        <div class="container">
            <h3 class="text-center mb-4"><?php echo __('contact.find_us'); ?></h3>
            <div class="ratio ratio-21x9">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3940.0!2d42.1!3d9.3!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zOcKwMTgnMDAuMCJOIDQywrAwNicwMC4wIkU!5e0!3m2!1sen!2set!4v1234567890" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>
        </div>
    </section>
    


    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
    </script>
</body>
</html>
