<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Ras Hotel</title>
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
                        <i class="fas fa-arrow-left"></i> <?php echo __('about.back_to_home'); ?>
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-4 fw-bold mb-3"><?php echo __('about.title'); ?></h1>
                    <p class="lead text-muted"><?php echo __('about.subtitle'); ?></p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- About Content -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center mb-5">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="assets/images/hotel/exterior/hotel-main.png" alt="Ras Hotel" class="img-fluid rounded shadow">
                </div>
                <div class="col-lg-6">
                    <h2 class="mb-4"><?php echo __('about.our_story'); ?></h2>
                    <p class="lead"><?php echo __('about.story_text1'); ?></p>
                    <p><?php echo __('about.story_text2'); ?></p>
                    <p>Every corner of our hotel tells a story, from the traditional Ethiopian architecture to the carefully curated art pieces that adorn our walls. We take pride in preserving the cultural essence of Harar while providing world-class amenities and services.</p>
                </div>
            </div>
            
            <!-- Mission & Vision -->
            <div class="row mb-5">
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="text-gold mb-3">
                                <i class="fas fa-bullseye fa-3x"></i>
                            </div>
                            <h3 class="mb-3"><?php echo __('about.our_mission'); ?></h3>
                            <p><?php echo __('about.mission_text'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="text-gold mb-3">
                                <i class="fas fa-eye fa-3x"></i>
                            </div>
                            <h3 class="mb-3"><?php echo __('about.our_vision'); ?></h3>
                            <p><?php echo __('about.vision_text'); ?></p>
                            <p>To be recognized as the leading hotel in Harar and a model of sustainable tourism that preserves cultural heritage while contributing to the economic development of our community. We envision a future where every guest becomes an ambassador of Ethiopian hospitality.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Values -->
            <div class="text-center mb-2">
                <h2 class="mb-4"><?php echo __('about.core_values'); ?></h2>
                <div class="row">
                    <div class="col-md-3 col-6 mb-4">
                        <div class="p-3">
                            <i class="fas fa-heart fa-3x text-gold mb-3"></i>
                            <h5><?php echo __('about.hospitality'); ?></h5>
                            <p class="text-muted"><?php echo __('about.hospitality_sub'); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-4">
                        <div class="p-3">
                            <i class="fas fa-star fa-3x text-gold mb-3"></i>
                            <h5><?php echo __('about.excellence'); ?></h5>
                            <p class="text-muted"><?php echo __('about.excellence_sub'); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-4">
                        <div class="p-3">
                            <i class="fas fa-handshake fa-3x text-gold mb-3"></i>
                            <h5><?php echo __('about.integrity'); ?></h5>
                            <p class="text-muted"><?php echo __('about.integrity_sub'); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-4">
                        <div class="p-3">
                            <i class="fas fa-leaf fa-3x text-gold mb-3"></i>
                            <h5><?php echo __('about.sustainability'); ?></h5>
                            <p class="text-muted"><?php echo __('about.sustainability_sub'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Why Choose Us -->
            <div class="bg-light rounded p-3 mb-5">
                <h2 class="text-center mb-4"><?php echo __('about.why_choose'); ?></h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <i class="fas fa-check-circle text-gold me-3 mt-1"></i>
                            <div>
                                <h5>Prime Location</h5>
                                <p class="text-muted">Steps away from Harar's historic Jugol walls and cultural attractions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <i class="fas fa-check-circle text-gold me-3 mt-1"></i>
                            <div>
                                <h5>Authentic Experience</h5>
                                <p class="text-muted">Traditional Ethiopian hospitality with modern amenities</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <i class="fas fa-check-circle text-gold me-3 mt-1"></i>
                            <div>
                                <h5>Expert Staff</h5>
                                <p class="text-muted">Multilingual team dedicated to your comfort</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <i class="fas fa-check-circle text-gold me-3 mt-1"></i>
                            <div>
                                <h5>Local Cuisine</h5>
                                <p class="text-muted">Authentic Ethiopian dishes and international options</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Call to Action -->
            <div class="text-center">
                <h3 class="mb-4"><?php echo __('about.ready_experience'); ?></h3>
                <a href="booking.php" class="btn btn-gold btn-lg"><?php echo __('about.book_stay'); ?></a>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // Cart functionality
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
        
        function updateCartBadge() {
            const badge = document.querySelector('.cart-badge');
            if (badge) {
                const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
                badge.textContent = totalItems;
                badge.style.display = totalItems > 0 ? 'inline-block' : 'none';
            }
            
            // Also update floating cart badge
            updateFloatingCartBadge();
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
        document.addEventListener('DOMContentLoaded', function() {
            updateCartBadge();
            
            // Add floating cart button
            addFloatingCartButton();
        });
        
        function addFloatingCartButton() {
            // Only add on mobile devices
            if (window.innerWidth <= 768) {
                const floatingCart = document.createElement('button');
                floatingCart.className = 'floating-cart-icon';
                floatingCart.innerHTML = `
                    <i class="fas fa-shopping-cart"></i>
                    <span class="badge floating-cart-badge" style="display: none;">0</span>
                `;
                floatingCart.onclick = () => window.location.href = 'cart.php';
                document.body.appendChild(floatingCart);
                
                // Update floating cart badge
                updateFloatingCartBadge();
            }
        }
        
        function updateFloatingCartBadge() {
            const floatingBadge = document.querySelector('.floating-cart-badge');
            if (floatingBadge) {
                const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
                floatingBadge.textContent = totalItems;
                floatingBadge.style.display = totalItems > 0 ? 'flex' : 'none';
            }
        }
        
        function addCartSuccessAnimation() {
            // Override the original addToCart function to add animation
            const originalAddToCart = window.addToCart;
            window.addToCart = function(itemId, itemName, price, type = 'room') {
                // Call original function
                originalAddToCart.call(this, itemId, itemName, price, type);
                
                // Add success animation to the button that was clicked
                const clickedButton = event.target.closest('.cart-overlay-btn');
                if (clickedButton) {
                    clickedButton.classList.add('cart-success-animation');
                    setTimeout(() => {
                        clickedButton.classList.remove('cart-success-animation');
                    }, 600);
                }
                
                // Update floating cart badge
                updateFloatingCartBadge();
            };
        }
        
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
    </script>
</body>
</html>
