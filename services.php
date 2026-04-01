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
    <title>Our Services - Harar Ras Hotel</title>
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
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="col-12 col-md text-center">
                    <h1 class="display-5 fw-bold mb-2">Our Services</h1>
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
                    Restaurant & Dining
                </h2>
                <p class="text-muted">Savor authentic Ethiopian and international cuisine</p>
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
                    Traditional Ethiopian Cuisine
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
                                        <span class="h6 text-muted fst-italic mb-0 small">Price varies</span>
                                    <?php endif; ?>
                                    <?php if (!is_logged_in()): ?>
                                    <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('restaurant')">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                    <?php else: ?>
                                    <a href="food-booking.php" class="btn btn-sm btn-gold">
                                        Order
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
                    International Buffet
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
                                        <span class="h6 text-muted fst-italic mb-0 small">Price varies</span>
                                    <?php endif; ?>
                                    <?php if (!is_logged_in()): ?>
                                    <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('restaurant')">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                    <?php else: ?>
                                    <a href="food-booking.php" class="btn btn-sm btn-gold">
                                        Order
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
                            Spa & Wellness
                        </h2>
                        <p class="text-muted small">Relax and rejuvenate with our premium spa services</p>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <img src="assets/images/food/ethiopian/food14.jpg" 
                                     class="card-img-top" alt="Spa Massage" style="height: 180px; object-fit: cover;" loading="lazy">
                                <div class="card-body">
                                    <h6 class="card-title">Spa Massage</h6>
                                    <p class="card-text text-muted small">Relaxing full body massage (60 minutes)</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-gold mb-0">ETB 1,300.00</span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="spa-booking.php" class="btn btn-sm btn-gold">Book</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <img src="assets/images/food/ethiopian/food16.jpg" 
                                     class="card-img-top" alt="Facial Treatment" style="height: 180px; object-fit: cover;" loading="lazy">
                                <div class="card-body">
                                    <h6 class="card-title">Facial Treatment</h6>
                                    <p class="card-text text-muted small">Rejuvenating facial with natural products</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-gold mb-0">ETB 800.00</span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="spa-booking.php" class="btn btn-sm btn-gold">Book</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <img src="assets/images/food/ethiopian/food17.jpg" 
                                     class="card-img-top" alt="Sauna" style="height: 180px; object-fit: cover;" loading="lazy">
                                <div class="card-body">
                                    <h6 class="card-title">Sauna & Steam Room</h6>
                                    <p class="card-text text-muted small">Detoxify and relax in our premium facilities</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-gold mb-0">ETB 500.00</span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="spa-booking.php" class="btn btn-sm btn-gold">Book</a>
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
                            Laundry Services
                        </h2>
                        <p class="text-muted small">Professional laundry and dry cleaning services</p>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <img src="assets/images/food/beverages/b4.jpg" 
                                     class="card-img-top" alt="Laundry Service" style="height: 180px; object-fit: cover;" loading="lazy">
                                <div class="card-body">
                                    <h6 class="card-title">Wash & Iron</h6>
                                    <p class="card-text text-muted small">Professional washing and ironing service</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-gold mb-0">ETB 250.00<small class="text-muted">/load</small></span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold">Book</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-spray-can fa-3x text-gold mb-2"></i>
                                    <h6 class="card-title">Dry Cleaning</h6>
                                    <p class="card-text text-muted small">Premium dry cleaning for delicate garments</p>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="h6 text-gold mb-0">ETB 400.00<small class="text-muted">/item</small></span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold">Book</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-3x text-gold mb-2"></i>
                                    <h6 class="card-title">Express Service</h6>
                                    <p class="card-text text-muted small">Same-day laundry service available</p>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="h6 text-gold mb-0">ETB 500.00<small class="text-muted">/load</small></span>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('service')">
                                            <i class="fas fa-lock"></i> Login
                                        </button>
                                        <?php else: ?>
                                        <a href="laundry-booking.php" class="btn btn-sm btn-gold">Book</a>
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
    
    <!-- Room Categories Section -->
    <section class="py-4" id="rooms">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="fw-bold" style="font-size: 1.5rem;">
                    <i class="fas fa-bed text-gold"></i>
                    Our Rooms
                </h2>
                <p class="text-muted" style="font-size: 0.9rem;">Choose from our range of comfortable accommodations</p>
            </div>
            
            <?php
            // Room categories data structure
            $room_categories = [
                [
                    'name' => 'Standard Single Room',
                    'roomRange' => '1–4',
                    'location' => 'G+3',
                    'capacity' => '1 guest',
                    'price' => 800,
                    'image' => 'assets/images/rooms/standard/room12.jpg',
                    'services' => ['Basic bed', 'Shared bathroom', 'WiFi'],
                    'description' => 'Affordable basic room with minimal services.',
                    'badge' => 'Basic',
                    'badge_color' => 'secondary'
                ],
                [
                    'name' => 'Standard Double Room',
                    'roomRange' => '5–9',
                    'location' => 'G+3',
                    'capacity' => '2 guests',
                    'price' => 1200,
                    'image' => 'assets/images/rooms/standard/room13.jpg',
                    'services' => ['Double bed', 'WiFi', 'Basic bathroom'],
                    'description' => 'Comfortable double room with essential amenities.',
                    'badge' => 'Basic',
                    'badge_color' => 'secondary'
                ],
                [
                    'name' => 'Deluxe Single Room',
                    'roomRange' => '10–13',
                    'location' => 'G+2',
                    'capacity' => '1 guest',
                    'price' => 1500,
                    'image' => 'assets/images/rooms/deluxe/room.jpg',
                    'services' => ['Larger bed', 'Private bathroom', 'TV', 'WiFi'],
                    'description' => 'More comfort with private facilities and entertainment.',
                    'badge' => 'Popular',
                    'badge_color' => 'info'
                ],
                [
                    'name' => 'Deluxe Double Room',
                    'roomRange' => '14–19',
                    'location' => 'G+2',
                    'capacity' => '2 guests',
                    'price' => 2000,
                    'image' => 'assets/images/rooms/deluxe/room2.jpg',
                    'services' => ['Double bed', 'Modern bathroom', 'TV', 'WiFi'],
                    'description' => 'Enhanced comfort with modern facilities and entertainment.',
                    'badge' => 'Popular',
                    'badge_color' => 'info'
                ],
                [
                    'name' => 'Double / King Room',
                    'roomRange' => '20–24',
                    'location' => 'G+1',
                    'capacity' => '2 guests',
                    'price' => 2500,
                    'image' => 'assets/images/rooms/deluxe/room3.jpg',
                    'services' => ['King-size bed', 'Spacious room', 'TV', 'WiFi'],
                    'description' => 'Spacious rooms with premium bedding and modern amenities.',
                    'badge' => 'Spacious',
                    'badge_color' => 'success'
                ],
                [
                    'name' => 'Suite Room',
                    'roomRange' => '25–29',
                    'location' => 'G+1',
                    'capacity' => '2 guests',
                    'price' => 3500,
                    'image' => 'assets/images/rooms/deluxe/room4.jpg',
                    'services' => ['Separate living area', 'Premium furniture', 'WiFi', 'TV'],
                    'description' => 'Premium suite with separate living area and luxury furniture.',
                    'badge' => 'Premium',
                    'badge_color' => 'warning'
                ],
                [
                    'name' => 'Family / Team Room',
                    'roomRange' => '30–33',
                    'location' => 'G+1',
                    'capacity' => '4 guests',
                    'price' => 4000,
                    'image' => 'assets/images/rooms/family/room27.jpg',
                    'services' => ['Multiple beds', 'Large space', 'Family setup', 'WiFi'],
                    'description' => 'Large family room with multiple beds and extra space.',
                    'badge' => 'Family',
                    'badge_color' => 'primary'
                ],
                [
                    'name' => 'Executive Room',
                    'roomRange' => '34–37',
                    'location' => 'G+2',
                    'capacity' => '2 guests',
                    'price' => 4500,
                    'image' => 'assets/images/rooms/deluxe/room5.jpg',
                    'services' => ['Premium interior', 'Work desk', 'Fast WiFi', 'TV'],
                    'description' => 'Premium experience with extra space and business facilities.',
                    'badge' => 'Business',
                    'badge_color' => 'dark'
                ],
                [
                    'name' => 'Presidential Suite',
                    'roomRange' => '38–39',
                    'location' => 'G+3',
                    'capacity' => '4 guests',
                    'price' => 8000,
                    'image' => 'assets/images/rooms/presidential/room35.jpg',
                    'services' => ['Luxury furniture', 'King beds', 'Multiple bathrooms', 'Minibar', 'WiFi', 'TV', 'Breakfast', 'Dinner'],
                    'description' => 'Top-tier luxury with premium services and full experience.',
                    'badge' => 'Luxury',
                    'badge_color' => 'danger'
                ]
            ];
            ?>
            
            <div class="row g-3 justify-content-center">
                <?php foreach ($room_categories as $room): ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-4">
                    <div class="card h-100 shadow-sm border" style="max-width: 380px; margin: 0 auto;">
                        <!-- Room Image -->
                        <div class="position-relative">
                            <img src="<?php echo $room['image']; ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($room['name']); ?>" 
                                 style="height: 180px; object-fit: cover;"
                                 loading="lazy">
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-<?php echo $room['badge_color']; ?>" style="font-size: 0.7rem;">
                                    <?php echo $room['badge']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Room Details -->
                        <div class="card-body p-3">
                            <!-- Room Name -->
                            <h5 class="card-title mb-2" style="font-size: 1.1rem; font-weight: 600;">
                                <?php echo htmlspecialchars($room['name']); ?>
                            </h5>
                            
                            <!-- Room Info -->
                            <div class="mb-2" style="font-size: 0.85rem;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <strong style="font-size: 0.8rem;">Room:</strong> 
                                        <span class="text-muted" style="font-size: 0.8rem;"><?php echo $room['roomRange']; ?></span>
                                    </div>
                                    <div class="col-6">
                                        <strong style="font-size: 0.8rem;">Type:</strong> 
                                        <span class="text-muted" style="font-size: 0.8rem;"><?php echo $room['badge']; ?></span>
                                    </div>
                                    <div class="col-6">
                                        <strong style="font-size: 0.8rem;">Capacity:</strong> 
                                        <span class="text-muted" style="font-size: 0.8rem;"><?php echo $room['capacity']; ?></span>
                                    </div>
                                    <div class="col-6">
                                        <strong style="font-size: 0.8rem;">Location:</strong> 
                                        <span class="text-muted" style="font-size: 0.8rem;"><?php echo $room['location']; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Price -->
                            <div class="mb-2">
                                <strong style="font-size: 0.8rem;">Price:</strong> 
                                <span class="text-gold fw-bold" style="font-size: 0.95rem;">ETB <?php echo number_format($room['price'], 2); ?></span>
                            </div>
                            
                            <!-- Services -->
                            <div class="mb-2">
                                <p class="mb-1 text-muted" style="font-size: 0.8rem;"><?php echo implode(', ', $room['services']); ?></p>
                            </div>
                            
                            <!-- Why This Price -->
                            <div class="mb-3 p-2 bg-light rounded" style="font-size: 0.75rem;">
                                <strong class="text-dark" style="font-size: 0.75rem;">
                                    <i class="fas fa-info-circle text-gold"></i> Why this price?
                                </strong>
                                <p class="mb-0 text-muted mt-1" style="font-size: 0.72rem; line-height: 1.3;">
                                    <?php echo $room['description']; ?>
                                </p>
                            </div>
                            
                            <!-- Action Buttons -->
                            <?php if (!is_logged_in()): ?>
                            <button class="btn btn-sm btn-outline-gold w-100" onclick="showLoginPrompt('room')" style="font-size: 0.7rem; padding: 0.3rem;">
                                <i class="fas fa-lock" style="font-size: 0.65rem;"></i> Login to Book
                            </button>
                            <?php else: ?>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-gold flex-fill" onclick="addToCart('<?php echo $room['name']; ?>', <?php echo $room['price']; ?>, 'room')" style="font-size: 0.7rem; padding: 0.3rem;">
                                    <i class="fas fa-cart-plus" style="font-size: 0.65rem;"></i> Add to Cart
                                </button>
                                <a href="booking.php" class="btn btn-sm btn-gold flex-fill" style="font-size: 0.7rem; padding: 0.3rem;">
                                    <i class="fas fa-calendar-check" style="font-size: 0.65rem;"></i> Book Now
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Value Progression Info -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-gold">
                        <div class="card-body p-3">
                            <h6 class="mb-2" style="font-size: 0.9rem;">
                                <i class="fas fa-chart-line text-gold"></i> Understanding Our Room Pricing
                            </h6>
                            <div class="row g-2" style="font-size: 0.75rem;">
                                <div class="col-12 col-md-3">
                                    <div class="text-center p-2 bg-light rounded">
                                        <i class="fas fa-arrow-up text-secondary mb-1"></i>
                                        <p class="mb-0 fw-bold">Basic → Standard</p>
                                        <p class="text-muted mb-0" style="font-size: 0.7rem;">Essential comfort</p>
                                    </div>
                                </div>
                                <div class="col-12 col-md-3">
                                    <div class="text-center p-2 bg-light rounded">
                                        <i class="fas fa-arrow-up text-info mb-1"></i>
                                        <p class="mb-0 fw-bold">Deluxe</p>
                                        <p class="text-muted mb-0" style="font-size: 0.7rem;">Private facilities + TV</p>
                                    </div>
                                </div>
                                <div class="col-12 col-md-3">
                                    <div class="text-center p-2 bg-light rounded">
                                        <i class="fas fa-arrow-up text-warning mb-1"></i>
                                        <p class="mb-0 fw-bold">Suite + Executive</p>
                                        <p class="text-muted mb-0" style="font-size: 0.7rem;">Extra space + premium</p>
                                    </div>
                                </div>
                                <div class="col-12 col-md-3">
                                    <div class="text-center p-2 bg-light rounded">
                                        <i class="fas fa-arrow-up text-danger mb-1"></i>
                                        <p class="mb-0 fw-bold">Presidential</p>
                                        <p class="text-muted mb-0" style="font-size: 0.7rem;">Full luxury + meals</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Additional Amenities -->
    <section class="py-4 bg-light" id="amenities">
        <div class="container">
            <h2 class="text-center mb-4">Hotel Amenities</h2>
            <div class="row g-3 text-center">
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-wifi fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1">Free WiFi</h6>
                        <p class="text-muted small mb-0">High-speed internet</p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-parking fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1">Free Parking</h6>
                        <p class="text-muted small mb-0">Secure parking</p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-swimming-pool fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1">Swimming Pool</h6>
                        <p class="text-muted small mb-0">Outdoor pool</p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-dumbbell fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1">Fitness Center</h6>
                        <p class="text-muted small mb-0">24/7 gym access</p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-coffee fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1">Coffee Shop</h6>
                        <p class="text-muted small mb-0">Fresh coffee daily</p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-briefcase fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1">Business Center</h6>
                        <p class="text-muted small mb-0">Meeting rooms</p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-concierge-bell fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1">24/7 Reception</h6>
                        <p class="text-muted small mb-0">Always here to help</p>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="p-2">
                        <i class="fas fa-shield-alt fa-2x text-gold mb-2"></i>
                        <h6 class="mb-1">Security</h6>
                        <p class="text-muted small mb-0">24/7 security</p>
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
                        <h5 id="modalTitle">Login Required</h5>
                        <p id="modalMessage" class="text-muted">Please sign in or create an account to proceed with your booking.</p>
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
                modalTitle.textContent = 'Login Required for Food Ordering';
                modalMessage.textContent = 'Please sign in or create an account to order food and reserve your table.';
                
                // Update redirect links for food booking
                loginLinks.forEach(link => {
                    link.href = 'login.php?redirect=food-booking';
                });
                registerLinks.forEach(link => {
                    link.href = 'register.php?redirect=food-booking';
                });
            } else if (type === 'room') {
                modalTitle.textContent = 'Login Required for Room Booking';
                modalMessage.textContent = 'Please sign in or create an account to book your room.';
                
                // Update redirect links for room booking
                loginLinks.forEach(link => {
                    link.href = 'login.php?redirect=booking';
                });
                registerLinks.forEach(link => {
                    link.href = 'register.php?redirect=booking';
                });
            } else if (type === 'service') {
                modalTitle.textContent = 'Login Required for Service Booking';
                modalMessage.textContent = 'Please sign in or create an account to book our hotel services.';
                
                // Update redirect links for room booking
                loginLinks.forEach(link => {
                    link.href = 'login.php?redirect=booking';
                });
                registerLinks.forEach(link => {
                    link.href = 'register.php?redirect=booking';
                });
            } else {
                modalTitle.textContent = 'Login Required';
                modalMessage.textContent = 'Please sign in or create an account to proceed with your booking.';
                
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
