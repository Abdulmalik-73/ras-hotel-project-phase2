<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Add cache-busting headers to ensure fresh data
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$rooms = get_all_rooms();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('rooms.our_rooms'); ?> - Harar Ras Hotel</title>
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
                        <i class="fas fa-arrow-left"></i> <?php echo __('rooms.back_to_home'); ?>
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-4 fw-bold mb-0"><?php echo __('rooms.our_rooms_suites'); ?></h1>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- Rooms Section -->
    <section class="py-3">
        <div class="container-fluid">
            <div class="container">
                <div class="text-center mb-4">
                    <h2 class="mb-3">
                        <i class="fas fa-bed text-gold"></i>
                        <?php echo __('rooms.our_rooms'); ?>
                    </h2>
                </div>
                
                <?php
            // Get room statuses from database
            $room_status_query = "SELECT r.room_number, r.status,
                                  CASE 
                                      WHEN r.status = 'occupied' THEN 'occupied'
                                      WHEN r.status = 'booked' THEN 'occupied'
                                      WHEN b.id IS NOT NULL THEN 'occupied'
                                      WHEN r.status = 'maintenance' THEN 'maintenance'
                                      WHEN r.status = 'inactive' THEN 'inactive'
                                      ELSE 'available'
                                  END as display_status
                                  FROM rooms r 
                                  LEFT JOIN bookings b ON r.id = b.room_id 
                                      AND b.status IN ('confirmed', 'checked_in') 
                                      AND CURDATE() BETWEEN b.check_in_date AND b.check_out_date
                                  ORDER BY CAST(r.room_number AS UNSIGNED)";
            $status_result = $conn->query($room_status_query);
            $room_statuses = [];
            while ($row = $status_result->fetch_assoc()) {
                $room_statuses[$row['room_number']] = $row['display_status'];
            }
            
            // Room status display configuration
            $status_config = [
                'available' => ['text' => __('rooms.available'), 'icon' => '✅', 'class' => 'text-success', 'bookable' => true],
                'occupied' => ['text' => __('rooms.occupied'), 'icon' => '🔴', 'class' => 'text-danger', 'bookable' => false],
                'maintenance' => ['text' => __('rooms.maintenance'), 'icon' => '🔧', 'class' => 'text-warning', 'bookable' => false],
                'inactive' => ['text' => __('rooms.not_available'), 'icon' => '⚫', 'class' => 'text-secondary', 'bookable' => false]
            ];
            
            // Define room types with their ranges and details - DYNAMIC PRICES FROM DATABASE
            $room_types = [];
            
            // Get actual room data from database
            $rooms_data = get_all_rooms();
            $rooms_by_number = [];
            foreach ($rooms_data as $room) {
                $rooms_by_number[$room['room_number']] = $room;
            }
            
            // Define room type configurations (structure only - prices come from DB)
            $room_type_configs = [
                [
                    'name' => 'Standard Single Room',
                    'start' => 1,
                    'end' => 4,
                    'capacity' => 1,
                    'location' => 'G+3',
                    'description' => 'Cozy single room with modern amenities, perfect for solo travelers',
                    'amenities' => ['1 Single Bed', 'Free WiFi', 'Air Conditioning', 'Private Bathroom'],
                    'images' => [
                        'assets/images/rooms/standard/room12.jpg',
                        'assets/images/rooms/standard/room13.jpg',
                        'assets/images/rooms/standard/room14.jpg',
                        'assets/images/rooms/standard/room15.jpg'
                    ],
                    'badge' => '',
                    'badge_class' => ''
                ],
                [
                    'name' => 'Standard Double Room',
                    'start' => 5,
                    'end' => 8,
                    'capacity' => 2,
                    'location' => 'G+3',
                    'description' => 'Comfortable double room with basic amenities for couples or friends',
                    'amenities' => ['1 Double Bed', 'Free WiFi', 'Air Conditioning', 'Mini Fridge'],
                    'images' => [
                        'assets/images/rooms/standard/room16.jpg',
                        'assets/images/rooms/standard/room17.jpg',
                        'assets/images/rooms/standard/room18.jpg',
                        'assets/images/rooms/standard/room19.jpg'
                    ],
                    'badge' => '',
                    'badge_class' => ''
                ],
                [
                    'name' => 'Deluxe Single Room',
                    'start' => 9,
                    'end' => 12,
                    'capacity' => 1,
                    'location' => 'G+2',
                    'description' => 'Spacious single room with premium amenities and city views',
                    'amenities' => ['1 Queen Bed', 'Premium WiFi', 'Smart TV', 'Work Desk'],
                    'images' => [
                        'assets/images/rooms/deluxe/room.jpg',
                        'assets/images/rooms/deluxe/room2.jpg',
                        'assets/images/rooms/deluxe/room3.jpg',
                        'assets/images/rooms/deluxe/room4.jpg'
                    ],
                    'badge' => 'Popular',
                    'badge_class' => 'bg-warning'
                ],
                [
                    'name' => 'Deluxe Double Room',
                    'start' => 13,
                    'end' => 16,
                    'capacity' => 2,
                    'location' => 'G+2',
                    'description' => 'Premium double room with elegant furnishings and city views',
                    'amenities' => ['1 Double Bed', 'Premium WiFi', 'Smart TV', 'Balcony'],
                    'images' => [
                        'assets/images/rooms/deluxe/room5.jpg',
                        'assets/images/rooms/deluxe/room6.jpg',
                        'assets/images/rooms/deluxe/room7.jpg',
                        'assets/images/rooms/deluxe/room8.jpg'
                    ],
                    'badge' => '',
                    'badge_class' => ''
                ],
                [
                    'name' => 'Double (King Size)',
                    'start' => 17,
                    'end' => 20,
                    'capacity' => 2,
                    'location' => 'G+1',
                    'description' => 'Luxurious room with king-size bed and modern amenities',
                    'amenities' => ['1 King Size Bed', 'Premium WiFi', 'Smart TV', 'Mini Bar'],
                    'images' => [
                        'assets/images/rooms/deluxe/room9.jpg',
                        'assets/images/rooms/deluxe/room10.jpg',
                        'assets/images/rooms/standard/room20.jpg',
                        'assets/images/rooms/suite/room21.jpg'
                    ],
                    'badge' => '',
                    'badge_class' => ''
                ],
                [
                    'name' => 'Suite Room',
                    'start' => 21,
                    'end' => 28,
                    'capacity' => 2,
                    'location' => 'G+1',
                    'description' => 'Spacious suite with separate living area and premium amenities',
                    'amenities' => ['King Size Bed', 'Living Area', 'Premium WiFi', 'Mini Bar'],
                    'images' => [
                        'assets/images/rooms/suite/room22.jpg',
                        'assets/images/rooms/suite/room23.jpg',
                        'assets/images/rooms/suite/room24.jpg',
                        'assets/images/rooms/suite/room25.jpg',
                        'assets/images/rooms/family/room27.jpg',
                        'assets/images/rooms/family/room28.jpg',
                        'assets/images/rooms/family/room29.jpg',
                        'assets/images/rooms/family/room30.jpg'
                    ],
                    'badge' => 'Premium',
                    'badge_class' => 'bg-info'
                ],
                [
                    'name' => 'Family (Team Bed)',
                    'start' => 29,
                    'end' => 32,
                    'capacity' => 4,
                    'location' => 'G+1',
                    'description' => 'Perfect for families with multiple beds and spacious layout',
                    'amenities' => ['Multiple Beds', 'Free WiFi', 'Smart TV', 'Extra Space'],
                    'images' => [
                        'assets/images/rooms/family/room31.jpg',
                        'assets/images/rooms/family/room32.jpg',
                        'assets/images/rooms/family/room33.jpg',
                        'assets/images/rooms/family/room34.jpg'
                    ],
                    'badge' => 'Family',
                    'badge_class' => 'bg-success'
                ],
                [
                    'name' => 'Executive Suite',
                    'start' => 33,
                    'end' => 37,
                    'capacity' => 2,
                    'location' => 'G+2',
                    'description' => 'Luxurious suite with separate bedroom, living room, and executive amenities',
                    'amenities' => ['King Size Bed', 'Separate Living Room', 'Executive Lounge Access', 'Premium Amenities'],
                    'images' => [
                        'assets/images/rooms/presidential/room35.jpg',
                        'assets/images/rooms/presidential/room36.jpg',
                        'assets/images/rooms/presidential/room37.jpg',
                        'assets/images/rooms/presidential/room38.jpg',
                        'assets/images/rooms/presidential/room39.jpg'
                    ],
                    'badge' => 'Executive',
                    'badge_class' => 'bg-primary'
                ],
                [
                    'name' => 'Presidential Suite',
                    'start' => 38,
                    'end' => 39,
                    'capacity' => 4,
                    'location' => 'G+3',
                    'description' => 'The ultimate luxury experience with panoramic views and exclusive services',
                    'amenities' => ['Master Bedroom + Customer Room', 'Private Dining Area', 'Butler Service', 'Panoramic City Views'],
                    'images' => [
                        'assets/images/rooms/suite/room21.jpg',
                        'assets/images/rooms/deluxe/room.jpg'
                    ],
                    'badge' => 'Presidential',
                    'badge_class' => 'bg-danger'
                ]
            ];
            
            // Merge database prices with room type configurations
            foreach ($room_type_configs as &$room_type) {
                // Find any active room in this range to get the price
                $room_type['price'] = 2000; // Default fallback
                for ($rn = $room_type['start']; $rn <= $room_type['end']; $rn++) {
                    if (isset($rooms_by_number[$rn]) && !empty($rooms_by_number[$rn]['price'])) {
                        $room_type['price'] = $rooms_by_number[$rn]['price'];
                        break;
                    }
                }
            }
            unset($room_type); // Break reference
            
            // Use the merged configurations
            $room_types = $room_type_configs;
            
            // Generate all 39 rooms
            echo '<div class="row">';
            foreach ($room_types as $room_type) {
                $image_index = 0;
                for ($room_num = $room_type['start']; $room_num <= $room_type['end']; $room_num++) {
                    // Get unique image for this room
                    $room_image = $room_type['images'][$image_index % count($room_type['images'])];
                    $image_index++;
                    
                    $border_class = $room_type['badge'] ? 'border-' . str_replace('bg-', '', $room_type['badge_class']) : '';
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm <?php echo $border_class; ?>">
                            <div class="position-relative">
                                <img src="<?php echo $room_image; ?>" 
                                     class="card-img-top" alt="Room <?php echo $room_num; ?>" 
                                     style="height: 200px; object-fit: cover;">
                                <?php if ($room_type['badge']): ?>
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge <?php echo $room_type['badge_class']; ?>"><?php echo $room_type['badge']; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $room_type['name']; ?><br>Room Number: <?php echo $room_num; ?></h5>
                                <?php 
                                $room_status = $room_statuses[$room_num] ?? 'available';
                                $status_info = $status_config[$room_status];
                                ?>
                                <p class="mb-2">
                                    <strong><?php echo __('rooms.room_status'); ?>:</strong> 
                                    <span class="<?php echo $status_info['class']; ?>">
                                        <?php echo $status_info['text']; ?> <?php echo $status_info['icon']; ?>
                                    </span>
                                </p>
                                
                                <!-- Capacity and Location -->
                                <div class="mb-2 d-flex justify-content-between">
                                    <p class="mb-0">
                                        <strong>Capacity:</strong> <?php echo $room_type['capacity']; ?> customer<?php echo $room_type['capacity'] > 1 ? 's' : ''; ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Location:</strong> <?php echo $room_type['location']; ?>
                                    </p>
                                </div>
                                
                                <!-- Services Section -->
                                <div class="mb-2">
                                    <p class="mb-1 small"><strong><?php echo __('rooms.services'); ?>:</strong></p>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($room_type['amenities'] as $amenity): ?>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;"><?php echo $amenity; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <?php
                                    // Use individual room price from DB, fall back to type price
                                    $individual_price = isset($rooms_by_number[$room_num]['price'])
                                        ? (float)$rooms_by_number[$room_num]['price']
                                        : (float)$room_type['price'];
                                    ?>
                                    <span class="h5 text-gold mb-0">ETB <?php echo number_format($individual_price, 2); ?><small class="text-muted">/night</small></span>
                                    <div class="d-flex flex-column gap-2">
                                        <?php if ($status_info['bookable']): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="addToCart(<?php echo $room_num; ?>, '<?php echo addslashes($room_type['name']); ?>', <?php echo $individual_price; ?>)">
                                            <i class="fas fa-shopping-cart"></i> <?php echo __('rooms.add_to_cart'); ?>
                                        </button>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('room')">
                                            <i class="fas fa-lock"></i> <?php echo __('nav.login'); ?>
                                        </button>
                                        <?php else: ?>
                                        <a href="booking.php?room=<?php echo $room_num; ?>" class="btn btn-sm btn-gold">
                                            <i class="fas fa-calendar-check"></i> <?php echo __('rooms.book_now'); ?>
                                        </a>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            <i class="fas fa-ban"></i> <?php echo __('rooms.not_available'); ?>
                                        </button>
                                        <small class="text-muted text-center">
                                            <?php 
                                            if ($room_status == 'occupied') {
                                                echo 'Currently occupied by customer';
                                            } elseif ($room_status == 'maintenance') {
                                                echo 'Room under maintenance';
                                            } elseif ($room_status == 'inactive') {
                                                echo 'Room temporarily closed';
                                            }
                                            ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
            echo '</div>';
            
            // Add dynamic rooms section for rooms added by admin (room numbers > 39)
            $dynamic_rooms = [];
            foreach ($rooms_data as $room) {
                $room_num = (int)$room['room_number'];
                if ($room_num > 39) {
                    $dynamic_rooms[] = $room;
                }
            }
            
            // Sort dynamic rooms by room number
            usort($dynamic_rooms, function($a, $b) {
                return (int)$a['room_number'] - (int)$b['room_number'];
            });
            
            if (!empty($dynamic_rooms)) {
                echo '<div class="row">';
                
                foreach ($dynamic_rooms as $room) {
                    $room_num = $room['room_number'];
                    $room_status = $room_statuses[$room_num] ?? 'available';
                    $status_info = $status_config[$room_status];
                    
                    // Use room image from database or default
                    $room_image = $room['image'] ? $room['image'] : 'assets/images/rooms/standard/room12.jpg';
                    if (!file_exists($room_image)) {
                        $room_image = 'assets/images/rooms/standard/room12.jpg';
                    }
                    
                    // Determine badge based on room type
                    $badge = '';
                    $badge_class = '';
                    switch (strtolower($room['room_type'])) {
                        case 'presidential':
                            $badge = 'Presidential';
                            $badge_class = 'bg-danger';
                            break;
                        case 'executive':
                            $badge = 'Executive';
                            $badge_class = 'bg-primary';
                            break;
                        case 'suite':
                            $badge = 'Premium';
                            $badge_class = 'bg-info';
                            break;
                        case 'family':
                            $badge = 'Family';
                            $badge_class = 'bg-success';
                            break;
                        case 'deluxe':
                            $badge = 'Popular';
                            $badge_class = 'bg-warning';
                            break;
                    }
                    
                    $border_class = $badge ? 'border-' . str_replace('bg-', '', $badge_class) : '';
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm <?php echo $border_class; ?>">
                            <div class="position-relative">
                                <img src="<?php echo $room_image; ?>" 
                                     class="card-img-top" alt="Room <?php echo $room_num; ?>" 
                                     style="height: 200px; object-fit: cover;">
                                <?php if ($badge): ?>
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $badge; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($room['name']); ?><br>Room Number: <?php echo $room_num; ?></h5>
                                <p class="mb-2">
                                    <strong><?php echo __('rooms.room_status'); ?>:</strong> 
                                    <span class="<?php echo $status_info['class']; ?>">
                                        <?php echo $status_info['text']; ?> <?php echo $status_info['icon']; ?>
                                    </span>
                                </p>
                                
                                <!-- Capacity and Location -->
                                <div class="mb-2 d-flex justify-content-between">
                                    <p class="mb-0">
                                        <strong>Capacity:</strong> <?php echo $room['capacity']; ?> customer<?php echo $room['capacity'] > 1 ? 's' : ''; ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Type:</strong> <?php echo ucfirst($room['room_type']); ?>
                                    </p>
                                </div>
                                
                                <!-- Description -->
                                <?php if ($room['description']): ?>
                                <div class="mb-2">
                                    <p class="small text-muted"><?php echo htmlspecialchars($room['description']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Services Section -->
                                <div class="mb-2">
                                    <p class="mb-1 small"><strong><?php echo __('rooms.services'); ?>:</strong></p>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php 
                                        // Default amenities based on room type
                                        $default_amenities = [];
                                        switch (strtolower($room['room_type'])) {
                                            case 'presidential':
                                                $default_amenities = ['Master Bedroom', 'Private Dining Area', 'Butler Service', 'Panoramic City Views'];
                                                break;
                                            case 'executive':
                                                $default_amenities = ['King Size Bed', 'Executive Lounge Access', 'Premium Amenities', 'Work Desk'];
                                                break;
                                            case 'suite':
                                                $default_amenities = ['King Size Bed', 'Living Area', 'Premium WiFi', 'Mini Bar'];
                                                break;
                                            case 'family':
                                                $default_amenities = ['Multiple Beds', 'Free WiFi', 'Smart TV', 'Extra Space'];
                                                break;
                                            case 'deluxe':
                                                $default_amenities = ['Queen Bed', 'Premium WiFi', 'Smart TV', 'Work Desk'];
                                                break;
                                            default:
                                                $default_amenities = ['Free WiFi', 'Air Conditioning', 'Private Bathroom', 'Daily Housekeeping'];
                                        }
                                        
                                        foreach ($default_amenities as $amenity): ?>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;"><?php echo $amenity; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="h5 text-gold mb-0">ETB <?php echo number_format($room['price'], 2); ?><small class="text-muted">/night</small></span>
                                    <div class="d-flex flex-column gap-2">
                                        <?php if ($status_info['bookable']): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="addToCart(<?php echo $room['id']; ?>, '<?php echo addslashes($room['name']); ?>', <?php echo $room['price']; ?>)">
                                            <i class="fas fa-shopping-cart"></i> <?php echo __('rooms.add_to_cart'); ?>
                                        </button>
                                        <?php if (!is_logged_in()): ?>
                                        <button class="btn btn-sm btn-outline-gold" onclick="showLoginPrompt('room')">
                                            <i class="fas fa-lock"></i> <?php echo __('nav.login'); ?>
                                        </button>
                                        <?php else: ?>
                                        <a href="booking.php?room=<?php echo $room['id']; ?>" class="btn btn-sm btn-gold">
                                            <i class="fas fa-calendar-check"></i> <?php echo __('rooms.book_now'); ?>
                                        </a>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            <i class="fas fa-ban"></i> <?php echo __('rooms.not_available'); ?>
                                        </button>
                                        <small class="text-muted text-center">
                                            <?php 
                                            if ($room_status == 'occupied') {
                                                echo 'Currently occupied by customer';
                                            } elseif ($room_status == 'maintenance') {
                                                echo 'Room under maintenance';
                                            } elseif ($room_status == 'inactive') {
                                                echo 'Room temporarily closed';
                                            }
                                            ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                echo '</div>';
            }
            ?>
            
            <div class="text-center mt-4">
                <p class="text-muted mb-3"><?php echo __('rooms.all_rooms_include'); ?></p>
                <?php if (!is_logged_in()): ?>
                <button class="btn btn-gold btn-lg" onclick="showLoginPrompt('room')">
                    <i class="fas fa-lock"></i> <?php echo __('rooms.login_to_book'); ?>
                </button>
                <?php else: ?>
                <a href="booking.php" class="btn btn-gold btn-lg"><?php echo __('rooms.view_all_book'); ?></a>
                <?php endif; ?>
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
        
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
        
        // Update all currency displays to use ETB
        function updateCurrencyDisplay() {
            // Update any existing currency displays on the page
            const currencyElements = document.querySelectorAll('[data-currency]');
            currencyElements.forEach(element => {
                const amount = parseFloat(element.dataset.currency);
                element.textContent = formatCurrency(amount);
            });
        }
        
        // Fix room layout on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartBadge();
            
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
    </script>
    <script>
        // Room image fallback function
        function getRoomImageFallback(roomType) {
            const roomImages = {
                'single': 'assets/images/rooms/standard/room12.jpg',
                'double': 'assets/images/rooms/standard/room13.jpg',
                'suite': 'assets/images/rooms/suite/room21.jpg',
                'deluxe': 'assets/images/rooms/deluxe/room.jpg',
                'presidential': 'assets/images/rooms/presidential/room35.jpg'
            };
            return roomImages[roomType] || roomImages['single'];
        }
        
        // Apply fallback images on page load
        $(document).ready(function() {
            $('.room-image').each(function() {
                const img = this;
                const roomType = $(this).closest('.room-card').find('.badge').text().toLowerCase();
                
                // Test if image loads, if not use fallback
                const testImg = new Image();
                testImg.onload = function() {
                    // Image loaded successfully
                };
                testImg.onerror = function() {
                    img.src = getRoomImageFallback(roomType);
                };
                testImg.src = img.src;
            });
        });
        
        // Function to show login prompt for booking
        function showBookingLoginPrompt(roomId) {
            // Create a more prominent modal-style alert
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade show" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle"></i> Authentication Required
                                </h5>
                            </div>
                            <div class="modal-body text-center">
                                <div class="mb-4">
                                    <i class="fas fa-user-lock fa-4x text-warning mb-3"></i>
                                    <h5>Account Required to Book This Room</h5>
                                    <p class="text-muted">You must create an account or sign in to proceed with booking.</p>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="card bg-success text-white">
                                            <div class="card-body">
                                                <i class="fas fa-user-plus fa-2x mb-2"></i>
                                                <h6>New Customer</h6>
                                                <p class="small mb-2">Create free account</p>
                                                <button class="btn btn-light btn-sm" onclick="window.location.href='register.php?redirect=booking&room=${roomId}'">
                                                    Create Account
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body">
                                                <i class="fas fa-sign-in-alt fa-2x mb-2"></i>
                                                <h6>Existing Customer</h6>
                                                <p class="small mb-2">Sign in to account</p>
                                                <button class="btn btn-light btn-sm" onclick="window.location.href='login.php?redirect=booking&room=${roomId}'">
                                                    Sign In
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
    </script>
</body>
</html>
