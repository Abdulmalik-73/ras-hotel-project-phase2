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
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-4 fw-bold mb-3">About Ras Hotel</h1>
                    <p class="lead text-muted">Where Ethiopian Hospitality Meets Modern Luxury</p>
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
                    <h2 class="mb-4">Our Story</h2>
                    <p class="lead">Ras Hotel stands as a beacon of Ethiopian hospitality in the historic city of Harar, a UNESCO World Heritage Site.</p>
                    <p>For over 15 years, we have been welcoming guests from around the world, offering them a unique blend of traditional Ethiopian warmth and modern comfort. Our hotel is strategically located near the ancient walled city of Jugol, allowing our guests to immerse themselves in the rich cultural heritage of Harar.</p>
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
                            <h3 class="mb-3">Our Mission</h3>
                            <p>To provide exceptional hospitality experiences that celebrate Ethiopian culture while meeting international standards of comfort and service. We strive to be the preferred choice for travelers seeking authentic cultural immersion combined with modern luxury.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="text-gold mb-3">
                                <i class="fas fa-eye fa-3x"></i>
                            </div>
                            <h3 class="mb-3">Our Vision</h3>
                            <p>To be recognized as the leading hotel in Harar and a model of sustainable tourism that preserves cultural heritage while contributing to the economic development of our community. We envision a future where every guest becomes an ambassador of Ethiopian hospitality.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Values -->
            <div class="text-center mb-5">
                <h2 class="mb-4">Our Core Values</h2>
                <div class="row">
                    <div class="col-md-3 col-6 mb-4">
                        <div class="p-3">
                            <i class="fas fa-heart fa-3x text-gold mb-3"></i>
                            <h5>Hospitality</h5>
                            <p class="text-muted">Warm Ethiopian welcome</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-4">
                        <div class="p-3">
                            <i class="fas fa-star fa-3x text-gold mb-3"></i>
                            <h5>Excellence</h5>
                            <p class="text-muted">Highest quality standards</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-4">
                        <div class="p-3">
                            <i class="fas fa-handshake fa-3x text-gold mb-3"></i>
                            <h5>Integrity</h5>
                            <p class="text-muted">Honest and transparent</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-4">
                        <div class="p-3">
                            <i class="fas fa-leaf fa-3x text-gold mb-3"></i>
                            <h5>Sustainability</h5>
                            <p class="text-muted">Eco-friendly practices</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Discover Our Spaces -->
            <div class="mb-5 discover-spaces-gallery">
                <div class="text-center mb-5">
                    <h2 class="display-5 fw-bold mb-3">Discover Our Spaces</h2>
                    <p class="lead text-muted">Explore our beautiful rooms and delicious cuisine</p>
                </div>
                
                <!-- Quick List of Spaces -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="text-gold mb-3"><i class="fas fa-bed me-2"></i>Our Rooms</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><span class="badge bg-gold me-2">01</span> Mountain View Suite</li>
                            <li class="mb-2"><span class="badge bg-gold me-2">02</span> Modern Executive Suite</li>
                            <li class="mb-2"><span class="badge bg-gold me-2">03</span> Luxury Family Suite</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-gold mb-3"><i class="fas fa-utensils me-2"></i>Our Cuisine</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><span class="badge bg-gold me-2">04</span> Gourmet Pasta</li>
                            <li class="mb-2"><span class="badge bg-gold me-2">05</span> Fresh Garden Salad</li>
                            <li class="mb-2"><span class="badge bg-gold me-2">06</span> Sunset Dining Experience</li>
                        </ul>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Room 1 - Mountain View Suite -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden">
                            <div class="position-relative">
                                <img src="assets/images/rooms/deluxe/room.jpg" 
                                     alt="Mountain View Suite" class="card-img-top" style="height: 250px; object-fit: cover;">
                                <div class="position-absolute top-0 start-0 bg-gold text-white px-3 py-2 m-3 rounded-pill shadow">
                                    <span class="fw-bold fs-5">01</span>
                                </div>
                                <div class="position-absolute bottom-0 start-0 end-0 bg-gradient-dark text-white p-3">
                                    <h6 class="mb-0 fw-bold">Mountain View Suite</h6>
                                </div>
                                <!-- Prominent Add to Cart Button -->
                                <div class="position-absolute top-50 start-50 translate-middle">
                                <button class="btn btn-gold btn-lg shadow-lg cart-overlay-btn" onclick="addToCart(1, 'Mountain View Suite', 450, 'room')" style="opacity: 0;">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <h5 class="card-title fw-bold text-gold">01. Mountain View Suite</h5>
                                <p class="card-text text-muted">Breathtaking mountain views with outdoor terrace, luxury bedding, and panoramic windows for the ultimate scenic experience.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-gold fw-bold">From $450/night</span>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-gold btn-sm" onclick="addToCart(1, 'Mountain View Suite', 450, 'room')" title="Add to Cart">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <a href="rooms.php" class="btn btn-outline-gold btn-sm">View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Room 2 - Modern Executive Suite -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden">
                            <div class="position-relative">
                                <img src="assets/images/rooms/suite/room21.jpg" 
                                     alt="Modern Executive Suite" class="card-img-top" style="height: 250px; object-fit: cover;">
                                <div class="position-absolute top-0 start-0 bg-gold text-white px-3 py-2 m-3 rounded-pill shadow">
                                    <span class="fw-bold fs-5">02</span>
                                </div>
                                <div class="position-absolute bottom-0 start-0 end-0 bg-gradient-dark text-white p-3">
                                    <h6 class="mb-0 fw-bold">Modern Executive Suite</h6>
                                </div>
                                <!-- Prominent Add to Cart Button -->
                                <div class="position-absolute top-50 start-50 translate-middle">
                                <button class="btn btn-gold btn-lg shadow-lg cart-overlay-btn" onclick="addToCart(2, 'Modern Executive Suite', 350, 'room')" style="opacity: 0;">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <h5 class="card-title fw-bold text-gold">02. Modern Executive Suite</h5>
                                <p class="card-text text-muted">Contemporary design with floor-to-ceiling windows, work area, and premium amenities for business travelers.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-gold fw-bold">From $350/night</span>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-gold btn-sm" onclick="addToCart(2, 'Modern Executive Suite', 350, 'room')" title="Add to Cart">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <a href="rooms.php" class="btn btn-outline-gold btn-sm">View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Room 3 - Luxury Family Suite -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden">
                            <div class="position-relative">
                                <img src="assets/images/rooms/family/room34.jpg" 
                                     alt="Luxury Family Suite" class="card-img-top" style="height: 250px; object-fit: cover;">
                                <div class="position-absolute top-0 start-0 bg-gold text-white px-3 py-2 m-3 rounded-pill shadow">
                                    <span class="fw-bold fs-5">03</span>
                                </div>
                                <div class="position-absolute bottom-0 start-0 end-0 bg-gradient-dark text-white p-3">
                                    <h6 class="mb-0 fw-bold">Luxury Family Suite</h6>
                                </div>
                                <!-- Prominent Add to Cart Button -->
                                <div class="position-absolute top-50 start-50 translate-middle">
                                <button class="btn btn-gold btn-lg shadow-lg cart-overlay-btn" onclick="addToCart(3, 'Luxury Family Suite', 280, 'room')" style="opacity: 0;">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <h5 class="card-title fw-bold text-gold">03. Luxury Family Suite</h5>
                                <p class="card-text text-muted">Spacious family accommodation with separate living area, multiple beds, and child-friendly amenities.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-gold fw-bold">From $280/night</span>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-gold btn-sm" onclick="addToCart(3, 'Luxury Family Suite', 280, 'room')" title="Add to Cart">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <a href="rooms.php" class="btn btn-outline-gold btn-sm">View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cuisine 4 - Gourmet Pasta -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden">
                            <div class="position-relative">
                                <img src="assets/images/food/international/i5.jpg" 
                                     alt="Gourmet Pasta" class="card-img-top" style="height: 250px; object-fit: cover;">
                                <div class="position-absolute top-0 start-0 bg-gold text-white px-3 py-2 m-3 rounded-pill shadow">
                                    <span class="fw-bold fs-5">04</span>
                                </div>
                                <div class="position-absolute bottom-0 start-0 end-0 bg-gradient-dark text-white p-3">
                                    <h6 class="mb-0 fw-bold">Gourmet Pasta</h6>
                                </div>
                                <!-- Prominent Add to Cart Button -->
                                <div class="position-absolute top-50 start-50 translate-middle">
                                <button class="btn btn-gold btn-lg shadow-lg cart-overlay-btn" onclick="addToCart('gourmet_pasta', 'Gourmet Pasta', 28, 'service')" style="opacity: 0;">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <h5 class="card-title fw-bold text-gold">04. Gourmet Pasta</h5>
                                <p class="card-text text-muted">Handcrafted pasta dishes with authentic Italian flavors, fresh ingredients, and traditional cooking methods.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-gold fw-bold">From $28/meal</span>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-gold btn-sm" onclick="addToCart('gourmet_pasta', 'Gourmet Pasta', 28, 'service')" title="Add to Cart">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <a href="food-booking.php" class="btn btn-outline-gold btn-sm">Order Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cuisine 5 - Fresh Garden Salad -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden">
                            <div class="position-relative">
                                <img src="assets/images/food/international/i10.jpg" 
                                     alt="Fresh Garden Salad" class="card-img-top" style="height: 250px; object-fit: cover;">
                                <div class="position-absolute top-0 start-0 bg-gold text-white px-3 py-2 m-3 rounded-pill shadow">
                                    <span class="fw-bold fs-5">05</span>
                                </div>
                                <div class="position-absolute bottom-0 start-0 end-0 bg-gradient-dark text-white p-3">
                                    <h6 class="mb-0 fw-bold">Fresh Garden Salad</h6>
                                </div>
                                <!-- Prominent Add to Cart Button -->
                                <div class="position-absolute top-50 start-50 translate-middle">
                                <button class="btn btn-gold btn-lg shadow-lg cart-overlay-btn" onclick="addToCart('fresh_garden_salad', 'Fresh Garden Salad', 22, 'service')" style="opacity: 0;">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <h5 class="card-title fw-bold text-gold">05. Fresh Garden Salad</h5>
                                <p class="card-text text-muted">Organic vegetables and greens from our garden, served with house-made dressings and seasonal toppings.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-gold fw-bold">From $22/meal</span>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-gold btn-sm" onclick="addToCart('fresh_garden_salad', 'Fresh Garden Salad', 22, 'service')" title="Add to Cart">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <a href="food-booking.php" class="btn btn-outline-gold btn-sm">Order Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cuisine 6 - Sunset Dining -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden">
                            <div class="position-relative">
                                <img src="assets/images/food/ethiopian/food12.jpg" 
                                     alt="Sunset Dining Experience" class="card-img-top" style="height: 250px; object-fit: cover;">
                                <div class="position-absolute top-0 start-0 bg-gold text-white px-3 py-2 m-3 rounded-pill shadow">
                                    <span class="fw-bold fs-5">06</span>
                                </div>
                                <div class="position-absolute bottom-0 start-0 end-0 bg-gradient-dark text-white p-3">
                                    <h6 class="mb-0 fw-bold">Sunset Dining Experience</h6>
                                </div>
                                <!-- Prominent Add to Cart Button -->
                                <div class="position-absolute top-50 start-50 translate-middle">
                                <button class="btn btn-gold btn-lg shadow-lg cart-overlay-btn" onclick="addToCart('sunset_dining', 'Sunset Dining Experience', 65, 'service')" style="opacity: 0;">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <h5 class="card-title fw-bold text-gold">06. Sunset Dining Experience</h5>
                                <p class="card-text text-muted">Romantic outdoor dining with breathtaking sunset views, featuring a curated menu of local and international specialties.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-gold fw-bold">From $65/person</span>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-gold btn-sm" onclick="addToCart('sunset_dining', 'Sunset Dining Experience', 65, 'service')" title="Add to Cart">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <a href="food-booking.php" class="btn btn-outline-gold btn-sm">Reserve Table</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- View All Button -->
                <div class="text-center mt-5">
                    <a href="rooms.php" class="btn btn-gold btn-lg me-3">
                        <i class="fas fa-bed me-2"></i>View All Rooms
                    </a>
                    <a href="food-booking.php" class="btn btn-outline-gold btn-lg">
                        <i class="fas fa-utensils me-2"></i>Explore Dining
                    </a>
                </div>
            </div>
            
            <!-- Why Choose Us -->
            <div class="bg-light rounded p-5 mb-5">
                <h2 class="text-center mb-4">Why Choose Ras Hotel?</h2>
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
                <h3 class="mb-4">Ready to Experience Ethiopian Hospitality?</h3>
                <a href="booking.php" class="btn btn-gold btn-lg">Book Your Stay</a>
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
