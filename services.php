<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Function to get food and service images - use actual database images
function getServiceImage($dbImage = null) {
    // Use database image if available
    if (!empty($dbImage)) {
        // Check if it's a full URL or relative path
        if (filter_var($dbImage, FILTER_VALIDATE_URL) || file_exists($dbImage)) {
            return $dbImage;
        }
    }
    
    // Return placeholder if no image available
    return 'assets/images/food/international/i1.jpg';
}

$services_query = "SELECT * FROM services WHERE status = 'active' ORDER BY category, name";
$services_result = $conn->query($services_query);
$services = [];

while ($row = $services_result->fetch_assoc()) {
    $services[$row['category']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('services_page.title'); ?> - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-auto mb-3 mb-md-0">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> <?php echo __('services_page.back'); ?>
                    </a>
                </div>
                <div class="col-12 col-md text-center">
                    <h1 class="display-5 fw-bold mb-2"><?php echo __('services_page.title'); ?></h1>
                </div>
                <div class="col-12 col-md-auto d-none d-md-block">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- Restaurant & Dining Section -->
    <section class="py-4" id="restaurant">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="fw-bold">
                    <i class="fas fa-utensils text-gold"></i>
                    <?php echo __('services_page.restaurant_title'); ?>
                </h2>
                <p class="text-muted"><?php echo __('services_page.restaurant_sub'); ?></p>
            </div>
            
            <?php
            // Group restaurant items by subcategory
            $restaurant_items = $services['restaurant'] ?? [];
            $ethiopian_foods = [];
            $international_foods = [];
            
            foreach ($restaurant_items as $item) {
                $name = $item['name'];
                // Categorize based on name keywords
                if (stripos($name, 'Ethiopian') !== false) {
                    $ethiopian_foods[] = $item;
                } else {
                    $international_foods[] = $item;
                }
            }
            ?>
            
            <!-- Traditional Ethiopian Cuisine -->
            <?php if (!empty($ethiopian_foods)): ?>
            <div class="mb-5" id="ethiopian-cuisine">
                <h3 class="mb-3 text-center">
                    <i class="fas fa-drumstick-bite text-warning"></i>
                    <?php echo __('services_page.ethiopian_title'); ?>
                </h3>
                <div class="row g-3">
                    <?php foreach ($ethiopian_foods as $service): ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <div class="card h-100 shadow-sm">
                            <div class="position-relative">
                                <img src="<?php echo getServiceImage($service['image']); ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($service['name']); ?>" 
                                     style="height: 180px; object-fit: cover;"
                                     loading="lazy">
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-warning text-dark">Ethiopian</span>
                                </div>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h6>
                                <p class="card-text text-muted small flex-grow-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php if ($service['price'] > 0): ?>
                                        <span class="h6 text-gold mb-0"><?php echo format_currency($service['price']); ?></span>
                                    <?php else: ?>
                                        <span class="h6 text-muted fst-italic mb-0 small"><?php echo __('services_page.price_varies'); ?></span>
                                    <?php endif; ?>
                                    <?php if (!is_logged_in()): ?>
                                    <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('restaurant')">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                    <?php else: ?>
                                    <a href="food-booking.php?item=<?php echo urlencode($service['name']); ?>&price=<?php echo $service['price']; ?>" class="btn btn-sm btn-gold">
                                        <?php echo __('services_page.order'); ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- International Buffet -->
            <?php if (!empty($international_foods)): ?>
            <div class="mb-5" id="international-buffet">
                <h3 class="mb-3 text-center">
                    <i class="fas fa-globe text-primary"></i>
                    <?php echo __('services_page.international_title'); ?>
                </h3>
                <div class="row g-3">
                    <?php foreach ($international_foods as $service): ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <div class="card h-100 shadow-sm">
                            <div class="position-relative">
                                <img src="<?php echo getServiceImage($service['image']); ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($service['name']); ?>" 
                                     style="height: 180px; object-fit: cover;"
                                     loading="lazy">
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-primary">International</span>
                                </div>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h6>
                                <p class="card-text text-muted small flex-grow-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php if ($service['price'] > 0): ?>
                                        <span class="h6 text-gold mb-0"><?php echo format_currency($service['price']); ?></span>
                                    <?php else: ?>
                                        <span class="h6 text-muted fst-italic mb-0 small"><?php echo __('services_page.price_varies'); ?></span>
                                    <?php endif; ?>
                                    <?php if (!is_logged_in()): ?>
                                    <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('restaurant')">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                    <?php else: ?>
                                    <a href="food-booking.php?item=<?php echo urlencode($service['name']); ?>&price=<?php echo $service['price']; ?>" class="btn btn-sm btn-gold">
                                        <?php echo __('services_page.order'); ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Spa & Wellness and Laundry Services -->
    <section class="py-4 bg-light" id="spa">
        <div class="container">
            <div class="row g-4">
                <!-- Spa & Wellness -->
                <div class="col-12 col-lg-6">
                    <div class="text-center mb-3">
                        <h2 class="h3 mb-2">
                            <i class="fas fa-spa text-gold"></i>
                            <?php echo __('services_page.spa_title'); ?>
                        </h2>
                        <p class="text-muted small"><?php echo __('services_page.spa_sub'); ?></p>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height: 180px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-spa fa-5x text-white"></i>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('services_page.spa_massage'); ?></h6>
                                    <p class="card-text text-muted small"><?php echo __('services_page.spa_massage_desc'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-gold mb-0">ETB 1,300.00</span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold"><?php echo __('services_page.schedule'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height: 180px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <i class="fas fa-hand-sparkles fa-5x text-white"></i>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('services_page.facial'); ?></h6>
                                    <p class="card-text text-muted small"><?php echo __('services_page.facial_desc'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-gold mb-0">ETB 800.00</span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold"><?php echo __('services_page.schedule'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height: 180px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <i class="fas fa-hot-tub fa-5x text-white"></i>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('services_page.sauna'); ?></h6>
                                    <p class="card-text text-muted small"><?php echo __('services_page.sauna_desc'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-gold mb-0">ETB 500.00</span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold"><?php echo __('services_page.schedule'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Laundry Services -->
                <div class="col-12 col-lg-6" id="laundry">
                    <div class="text-center mb-3">
                        <h2 class="h3 mb-2">
                            <i class="fas fa-tshirt text-gold"></i>
                            <?php echo __('services_page.laundry_title'); ?>
                        </h2>
                        <p class="text-muted small"><?php echo __('services_page.laundry_sub'); ?></p>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height: 180px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                    <i class="fas fa-tshirt fa-5x text-white"></i>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('services_page.wash_iron'); ?></h6>
                                    <p class="card-text text-muted small"><?php echo __('services_page.wash_iron_desc'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-gold mb-0">ETB 250.00<small class="text-muted">/load</small></span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold"><?php echo __('services_page.request'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height: 180px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <i class="fas fa-spray-can fa-5x text-white"></i>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('services_page.dry_clean'); ?></h6>
                                    <p class="card-text text-muted small"><?php echo __('services_page.dry_clean_desc'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="h6 text-gold mb-0">ETB 400.00<small class="text-muted">/item</small></span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold"><?php echo __('services_page.request'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height: 180px; background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                                    <i class="fas fa-clock fa-5x text-white"></i>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('services_page.express'); ?></h6>
                                    <p class="card-text text-muted small"><?php echo __('services_page.express_desc'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="h6 text-gold mb-0">ETB 500.00<small class="text-muted">/load</small></span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold"><?php echo __('services_page.request'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <p class="text-muted small mb-0">
                            <i class="fas fa-check-circle text-success"></i> Free pickup & delivery<br>
                            <i class="fas fa-check-circle text-success"></i> 24-hour turnaround<br>
                            <i class="fas fa-check-circle text-success"></i> Eco-friendly products
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Additional Amenities -->
    <section class="py-4 bg-light" id="amenities">
        <div class="container">
            <h2 class="text-center mb-4"><?php echo __('services_page.amenities_title'); ?></h2>
            <div class="row g-3 text-center">
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-wifi fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1"><?php echo __('services_page.wifi'); ?></h6>
                        <p class="text-muted small mb-0"><?php echo __('services_page.wifi_desc'); ?></p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-parking fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1"><?php echo __('services_page.parking'); ?></h6>
                        <p class="text-muted small mb-0"><?php echo __('services_page.parking_desc'); ?></p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-swimming-pool fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1"><?php echo __('services_page.pool'); ?></h6>
                        <p class="text-muted small mb-0"><?php echo __('services_page.pool_desc'); ?></p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-dumbbell fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1"><?php echo __('services_page.gym'); ?></h6>
                        <p class="text-muted small mb-0"><?php echo __('services_page.gym_desc'); ?></p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-coffee fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1"><?php echo __('services_page.coffee'); ?></h6>
                        <p class="text-muted small mb-0"><?php echo __('services_page.coffee_desc'); ?></p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-briefcase fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1"><?php echo __('services_page.business'); ?></h6>
                        <p class="text-muted small mb-0"><?php echo __('services_page.business_desc'); ?></p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-concierge-bell fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1"><?php echo __('services_page.reception'); ?></h6>
                        <p class="text-muted small mb-0"><?php echo __('services_page.reception_desc'); ?></p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-shield-alt fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1"><?php echo __('services_page.security'); ?></h6>
                        <p class="text-muted small mb-0"><?php echo __('services_page.security_desc'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Login Prompt Modal -->
    <div class="modal fade" id="loginPromptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-gold text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-lock"></i> Authentication Required
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-shield-alt fa-4x text-gold mb-3"></i>
                        <h5 id="modalTitle"><?php echo __('services_page.login_required'); ?></h5>
                        <p id="modalMessage" class="text-muted"><?php echo __('services_page.login_to_proceed'); ?></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <i class="fas fa-sign-in-alt fa-2x text-primary mb-2"></i>
                                    <h6>Existing Customer</h6>
                                    <p class="small text-muted">Sign in to your account</p>
                                    <a href="login.php?redirect=services" class="btn btn-primary btn-sm">
                                        <i class="fas fa-sign-in-alt"></i> Sign In
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <i class="fas fa-user-plus fa-2x text-success mb-2"></i>
                                    <h6>New Customer</h6>
                                    <p class="small text-muted">Create a free account</p>
                                    <a href="register.php?redirect=services" class="btn btn-success btn-sm">
                                        <i class="fas fa-user-plus"></i> Register
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-check-circle text-success"></i> Secure booking process<br>
                            <i class="fas fa-check-circle text-success"></i> Track your orders and reservations<br>
                            <i class="fas fa-check-circle text-success"></i> Exclusive member benefits
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
        
        // Fix room layout on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure all room cards have equal height
            function equalizeCardHeights() {
                const cards = document.querySelectorAll('.card.h-100');
                let maxHeight = 0;
                
                // Reset heights
                cards.forEach(card => {
                    card.style.height = 'auto';
                });
                
                // Find max height
                cards.forEach(card => {
                    const height = card.offsetHeight;
                    if (height > maxHeight) {
                        maxHeight = height;
                    }
                });
                
                // Set all cards to max height
                cards.forEach(card => {
                    card.style.height = maxHeight + 'px';
                });
            }
            
            // Run on load and resize
            equalizeCardHeights();
            window.addEventListener('resize', equalizeCardHeights);
            
            // Fix any layout issues
            const rows = document.querySelectorAll('.row');
            rows.forEach(row => {
                row.style.display = 'flex';
                row.style.flexWrap = 'wrap';
            });
        });
        
        function showLoginPrompt(type) {
            const modal = new bootstrap.Modal(document.getElementById('loginPromptModal'));
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            
            // Update modal links based on type
            const loginLinks = document.querySelectorAll('#loginPromptModal a[href*="login.php"]');
            const registerLinks = document.querySelectorAll('#loginPromptModal a[href*="register.php"]');
            
            if (type === 'restaurant') {
                modalTitle.textContent = '<?php echo addslashes(__('services_page.login_required')); ?>';
                modalMessage.textContent = '<?php echo addslashes(__('services_page.login_to_proceed')); ?>';
                
                // Update redirect links for food booking
                loginLinks.forEach(link => {
                    link.href = 'login.php?redirect=food-booking';
                });
                registerLinks.forEach(link => {
                    link.href = 'register.php?redirect=food-booking';
                });
            } else if (type === 'room') {
                modalTitle.textContent = '<?php echo addslashes(__('services_page.login_required')); ?>';
                modalMessage.textContent = '<?php echo addslashes(__('services_page.login_to_proceed')); ?>';
                
                // Update redirect links for room booking
                loginLinks.forEach(link => {
                    link.href = 'login.php?redirect=booking';
                });
                registerLinks.forEach(link => {
                    link.href = 'register.php?redirect=booking';
                });
            } else if (type === 'service') {
                modalTitle.textContent = '<?php echo addslashes(__('services_page.login_required')); ?>';
                modalMessage.textContent = '<?php echo addslashes(__('services_page.login_to_proceed')); ?>';
                
                // Update redirect links for room booking
                loginLinks.forEach(link => {
                    link.href = 'login.php?redirect=booking';
                });
                registerLinks.forEach(link => {
                    link.href = 'register.php?redirect=booking';
                });
            } else {
                modalTitle.textContent = '<?php echo addslashes(__('services_page.login_required')); ?>';
                modalMessage.textContent = '<?php echo addslashes(__('services_page.login_to_proceed')); ?>';
                
                // Default to room booking
                loginLinks.forEach(link => {
                    link.href = 'login.php?redirect=booking';
                });
                registerLinks.forEach(link => {
                    link.href = 'register.php?redirect=booking';
                });
            }
            
            modal.show();
        }
        
        // Shopping Cart functionality
        let cart = JSON.parse(localStorage.getItem('hotelCart')) || [];
        
        function addToCart(itemId, itemName, price, type = 'room') {
            const item = {
                id: itemId,
                name: itemName,
                price: parseFloat(price),
                type: type,
                quantity: 1,
                addedAt: new Date().toISOString()
            };
            
            // For rooms, check if already in cart
            if (type === 'room') {
                const existingIndex = cart.findIndex(cartItem => cartItem.id === itemId && cartItem.type === 'room');
                if (existingIndex !== -1) {
                    showNotification('This room is already in your cart!', 'warning');
                    return;
                }
                item.roomNumber = itemId;
                item.roomName = itemName;
            } else {
                // For services, check if already exists and update quantity
                const existingIndex = cart.findIndex(cartItem => cartItem.id === itemId && cartItem.type === type);
                if (existingIndex !== -1) {
                    cart[existingIndex].quantity += 1;
                    localStorage.setItem('hotelCart', JSON.stringify(cart));
                    updateCartBadge();
                    showNotification(`${itemName} quantity updated in cart!`, 'success');
                    return;
                }
            }
            
            cart.push(item);
            localStorage.setItem('hotelCart', JSON.stringify(cart));
            updateCartBadge();
            showNotification(`${itemName} added to cart successfully!`, 'success');
        }
        
        function addServiceToCart(serviceName, price, category) {
            addToCart(serviceName.replace(/\s+/g, '_'), serviceName, price, 'service');
        }
        
        function updateCartBadge() {
            const badge = document.querySelector('.cart-badge');
            if (badge) {
                const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
                badge.textContent = totalItems;
                badge.style.display = totalItems > 0 ? 'inline-block' : 'none';
            }
        }
        
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'times-circle'}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 4000);
        }
        
        // Update cart badge on page load
        updateCartBadge();
    </script>
</body>
</html>
