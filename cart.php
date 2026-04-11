<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Harar Ras Hotel</title>
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
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-4 fw-bold mb-3">Shopping Cart</h1>
                    <p class="lead text-muted">Review your selected items</p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- Cart Content -->
    <section class="py-5">
        <div class="container">
            <!-- Cart Items Container -->
            <div id="cartContainer">
                <!-- Empty Cart Message -->
                <div id="emptyCart" class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-5x text-muted mb-4"></i>
                    <h3 class="mb-3">Your cart is empty</h3>
                    <p class="text-muted mb-4">Add some rooms or services to get started!</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="rooms.php" class="btn btn-gold">
                            <i class="fas fa-bed me-2"></i>Browse Rooms
                        </a>
                        <a href="services.php" class="btn btn-outline-gold">
                            <i class="fas fa-concierge-bell me-2"></i>View Services
                        </a>
                    </div>
                </div>
                
                <!-- Cart Items List -->
                <div id="cartItems" style="display: none;">
                    <div class="row">
                        <div class="col-12">
                            <h4 class="mb-4"><i class="fas fa-shopping-cart me-2"></i>Your Selected Items</h4>
                            <div id="cartItemsList" class="row">
                                <!-- Cart items will be populated here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cart Summary -->
                    <div class="row mt-5">
                        <div class="col-lg-8">
                            <div class="d-flex gap-3">
                                <a href="rooms.php" class="btn btn-outline-gold">
                                    <i class="fas fa-plus me-2"></i>Add More Rooms
                                </a>
                                <a href="services.php" class="btn btn-outline-gold">
                                    <i class="fas fa-plus me-2"></i>Add Services
                                </a>
                                <button class="btn btn-outline-danger" onclick="clearCart()">
                                    <i class="fas fa-trash me-2"></i>Clear Cart
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Order Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="cartSubtotal">ETB 0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax (10%):</span>
                                        <span id="cartTax">ETB 0.00</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fw-bold fs-5">
                                        <span>Total:</span>
                                        <span id="cartTotal" class="text-gold">ETB 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
        
        // Load cart on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
            updateCartBadge();
        });
        
        async function loadCart() {
            const cartContainer = document.getElementById('cartContainer');
            const emptyCart = document.getElementById('emptyCart');
            const cartItems = document.getElementById('cartItems');
            const cartItemsList = document.getElementById('cartItemsList');
            
            if (cart.length === 0) {
                emptyCart.style.display = 'block';
                cartItems.style.display = 'none';
                return;
            }
            
            emptyCart.style.display = 'none';
            cartItems.style.display = 'block';
            
            let cartHTML = '';
            let subtotal = 0;
            
            // Get room details for each cart item
            for (let i = 0; i < cart.length; i++) {
                const item = cart[i];
                
                if (item.type === 'room') {
                    try {
                        // Fetch room details from database
                        const response = await fetch(`api/get_room_details.php?id=${item.id}`);
                        const roomData = await response.json();
                        
                        if (roomData.success) {
                            const room = roomData.room;
                            const itemTotal = room.price * (item.quantity || 1);
                            subtotal += itemTotal;
                            
                            // Determine badge based on room type
                            let badge = '';
                            let badgeClass = '';
                            switch (room.room_type.toLowerCase()) {
                                case 'presidential':
                                    badge = 'Presidential';
                                    badgeClass = 'bg-danger';
                                    break;
                                case 'executive':
                                    badge = 'Executive';
                                    badgeClass = 'bg-primary';
                                    break;
                                case 'suite':
                                    badge = 'Premium';
                                    badgeClass = 'bg-info';
                                    break;
                                case 'family':
                                    badge = 'Family';
                                    badgeClass = 'bg-success';
                                    break;
                                case 'deluxe':
                                    badge = 'Popular';
                                    badgeClass = 'bg-warning';
                                    break;
                            }
                            
                            // Default amenities based on room type
                            let amenities = [];
                            switch (room.room_type.toLowerCase()) {
                                case 'presidential':
                                    amenities = ['Master Bedroom', 'Private Dining Area', 'Butler Service', 'Panoramic City Views'];
                                    break;
                                case 'executive':
                                    amenities = ['King Size Bed', 'Executive Lounge Access', 'Premium Amenities', 'Work Desk'];
                                    break;
                                case 'suite':
                                    amenities = ['King Size Bed', 'Living Area', 'Premium WiFi', 'Mini Bar'];
                                    break;
                                case 'family':
                                    amenities = ['Multiple Beds', 'Free WiFi', 'Smart TV', 'Extra Space'];
                                    break;
                                case 'deluxe':
                                    amenities = ['Queen Bed', 'Premium WiFi', 'Smart TV', 'Work Desk'];
                                    break;
                                default:
                                    amenities = ['Free WiFi', 'Air Conditioning', 'Private Bathroom', 'Daily Housekeeping'];
                            }
                            
                            const roomImage = room.image || 'assets/images/rooms/standard/room12.jpg';
                            const isLoggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;
                            
                            cartHTML += `
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100 shadow-sm">
                                        <div class="position-relative">
                                            <img src="${roomImage}" class="card-img-top" alt="Room ${room.room_number}" style="height: 200px; object-fit: cover;">
                                            ${badge ? `<div class="position-absolute top-0 end-0 m-2"><span class="badge ${badgeClass}">${badge}</span></div>` : ''}
                                            <div class="position-absolute top-0 start-0 m-2">
                                                <span class="badge bg-success"><i class="fas fa-shopping-cart"></i> In Cart</span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title">${room.name}<br>Room Number: ${room.room_number}</h5>
                                            <p class="mb-2">
                                                <strong>Room Status:</strong> 
                                                <span class="text-success">Available <i class="fas fa-check-circle"></i></span>
                                            </p>
                                            
                                            <div class="mb-2 d-flex justify-content-between">
                                                <p class="mb-0">
                                                    <strong>Capacity:</strong> ${room.capacity} customer${room.capacity > 1 ? 's' : ''}
                                                </p>
                                                <p class="mb-0">
                                                    <strong>Type:</strong> ${room.room_type.charAt(0).toUpperCase() + room.room_type.slice(1)}
                                                </p>
                                            </div>
                                            
                                            ${room.description ? `<div class="mb-2"><p class="small text-muted">${room.description}</p></div>` : ''}
                                            
                                            <div class="mb-2">
                                                <p class="mb-1 small"><strong>Services:</strong></p>
                                                <div class="d-flex flex-wrap gap-1">
                                                    ${amenities.map(amenity => `<span class="badge bg-light text-dark border" style="font-size: 0.7rem;">${amenity}</span>`).join('')}
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <span class="h5 text-gold mb-0">ETB ${parseFloat(room.price).toFixed(2)}<small class="text-muted">/night</small></span>
                                                <div class="d-flex flex-column gap-2">
                                                    ${!isLoggedIn ? 
                                                        `<a href="login.php?redirect=booking&room=${room.id}" class="btn btn-sm btn-gold">
                                                            <i class="fas fa-sign-in-alt"></i> Login to Book
                                                        </a>` :
                                                        `<a href="booking.php?room=${room.id}" class="btn btn-sm btn-gold">
                                                            <i class="fas fa-calendar-check"></i> Book Now
                                                        </a>`
                                                    }
                                                    <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${i})">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    } catch (error) {
                        console.error('Error fetching room details:', error);
                        // Fallback to basic display
                        const itemTotal = item.price * (item.quantity || 1);
                        subtotal += itemTotal;
                        
                        cartHTML += `
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">${item.name}</h5>
                                        <p class="text-muted">Room details unavailable</p>
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <span class="h5 text-gold mb-0">ETB ${parseFloat(item.price).toFixed(2)}</span>
                                            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${i})">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    // Handle non-room items (services, food, etc.)
                    const itemTotal = item.price * (item.quantity || 1);
                    subtotal += itemTotal;
                    
                    cartHTML += `
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title">${item.name}</h5>
                                    <p class="text-muted">${item.type.charAt(0).toUpperCase() + item.type.slice(1)}</p>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="h5 text-gold mb-0">ETB ${parseFloat(item.price).toFixed(2)}</span>
                                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${i})">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }
            
            cartItemsList.innerHTML = cartHTML;
            updateCartSummary(subtotal);
        }
        
        function updateCartSummary(subtotal) {
            const tax = subtotal * 0.1;
            const total = subtotal + tax;
            
            document.getElementById('cartSubtotal').textContent = `ETB ${subtotal.toFixed(2)}`;
            document.getElementById('cartTax').textContent = `ETB ${tax.toFixed(2)}`;
            document.getElementById('cartTotal').textContent = `ETB ${total.toFixed(2)}`;
        }
        
        function removeFromCart(index) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                cart.splice(index, 1);
                localStorage.setItem('hotelCart', JSON.stringify(cart));
                loadCart();
                updateCartBadge();
                showNotification('Item removed from cart', 'success');
            }
        }
        
        function clearCart() {
            if (confirm('Are you sure you want to clear your entire cart?')) {
                cart = [];
                localStorage.setItem('hotelCart', JSON.stringify(cart));
                loadCart();
                updateCartBadge();
                showNotification('Cart cleared successfully', 'success');
            }
        }
        
        function updateCartBadge() {
            const badge = document.querySelector('.cart-badge');
            if (badge) {
                const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
                badge.textContent = totalItems;
                badge.style.display = totalItems > 0 ? 'inline-block' : 'none';
            }
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 4000);
        }
    </script>
</body>
</html>