<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'food-booking.php';
    header('Location: login.php?redirect=food-booking');
    exit();
}

// Get selected food item from URL parameters
$selected_item = isset($_GET['item']) ? sanitize_input($_GET['item']) : '';
$selected_price = isset($_GET['price']) ? (float)$_GET['price'] : 0;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get food item from POST (passed as hidden field) or URL
    $selected_item = sanitize_input($_POST['selected_item'] ?? $selected_item);
    $selected_price = (float)($_POST['selected_price'] ?? $selected_price);
    
    $table_reservation = isset($_POST['table_reservation']) ? 1 : 0;
    $reservation_date = sanitize_input($_POST['reservation_date'] ?? '');
    $reservation_time = sanitize_input($_POST['reservation_time'] ?? '');
    $guests = (int)($_POST['guests'] ?? 1);
    $special_requests = sanitize_input($_POST['special_requests'] ?? '');
    
    // Validate that we have a food item
    if (empty($selected_item) || $selected_price <= 0) {
        $error = 'Please select a food item from the Services menu first.';
    } else {
        // Create single item order
        $order_items = [[
            'item' => $selected_item,
            'quantity' => 1,
            'price' => $selected_price,
            'total' => $selected_price
        ]];
        
        $total_price = $selected_price;
        
        // Create food order using the same structure as room bookings
        $order_reference = 'FO' . date('Ymd') . rand(1000, 9999);
        
        // Validate user_id exists
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            $error = 'Session error: User ID not found. Please log in again.';
            error_log("ERROR: user_id not in session. Session data: " . print_r($_SESSION, true));
        } else {
            $user_id = (int)$_SESSION['user_id'];
            
            // Verify user exists in database
            $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $user_check->bind_param("i", $user_id);
            $user_check->execute();
            $user_result = $user_check->get_result();
            
            if ($user_result->num_rows == 0) {
                $error = 'User account not found in database. Please log in again.';
                error_log("ERROR: User ID $user_id not found in users table");
            } else {
                // Insert into bookings table with food order type
                $query = "INSERT INTO bookings (user_id, booking_reference, customers, total_price, special_requests, status, booking_type, verification_status) 
                         VALUES (?, ?, ?, ?, ?, 'pending', 'food_order', 'pending_payment')";
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    $error = 'Database error: ' . $conn->error;
                    error_log("ERROR: Failed to prepare statement: " . $conn->error);
                } else {
                    $stmt->bind_param("isids", 
                        $user_id, 
                        $order_reference, 
                        $guests, 
                        $total_price, 
                        $special_requests
                    );
                    
                    if ($stmt->execute()) {
                        $booking_id = $conn->insert_id;
                        
                        // Insert food order details
                        $food_query = "INSERT INTO food_orders (booking_id, user_id, order_reference, total_price, 
                                      table_reservation, reservation_date, reservation_time, guests, special_requests, 
                                      status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                        
                        $food_stmt = $conn->prepare($food_query);
                        $food_stmt->bind_param("iisdisiss", 
                            $booking_id,
                            $user_id, 
                            $order_reference, 
                            $total_price, 
                            $table_reservation, 
                            $reservation_date, 
                            $reservation_time, 
                            $guests, 
                            $special_requests
                        );
                        
                        if ($food_stmt->execute()) {
                            $order_id = $conn->insert_id;
                            
                            // Insert order items
                            $item_query = "INSERT INTO food_order_items (order_id, item_name, quantity, price, total_price) VALUES (?, ?, ?, ?, ?)";
                            $item_stmt = $conn->prepare($item_query);
                            
                            foreach ($order_items as $item) {
                                $item_stmt->bind_param("isidd", $order_id, $item['item'], $item['quantity'], $item['price'], $item['total']);
                                $item_stmt->execute();
                            }
                            
                            // Generate payment reference and deadline (same as room bookings)
                            $payment_ref = 'HRH-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($booking_id . time()), 0, 6));
                            $deadline = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                            
                            // Update booking with payment details
                            $update_query = "UPDATE bookings SET payment_reference = ?, payment_deadline = ?, verification_status = 'pending_payment' WHERE id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bind_param("ssi", $payment_ref, $deadline, $booking_id);
                            $update_stmt->execute();
                            
                            // Log user activity for food order
                            log_user_activity($user_id, 'booking', 'Food order placed: ' . $order_reference . ' - Total: ETB ' . number_format($total_price, 2), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                            
                            $success = "Food order placed successfully! Please complete payment within 30 minutes to confirm your order.";
                            
                            // Store order reference in session for payment
                            $_SESSION['pending_food_order'] = $order_reference;
                            
                            // Debug: Log the redirect
                            error_log("Redirecting to payment-upload.php?booking=" . $booking_id . "&type=food");
                            
                            // Redirect to payment upload page (same as room bookings)
                            header('Location: payment-upload.php?booking=' . $booking_id . '&type=food');
                            exit();
                        } else {
                            $error = 'Failed to create food order details: ' . $food_stmt->error;
                            error_log("ERROR: Failed to insert food order: " . $food_stmt->error);
                        }
                    } else {
                        $error = 'Failed to create order: ' . $stmt->error;
                        error_log("ERROR: Failed to insert booking: " . $stmt->error);
                    }
                }
            }
        }
    }
}

// Get available food items from database
$food_query = "SELECT name, price, description FROM services WHERE category = 'restaurant' AND status = 'active' ORDER BY name";
$food_result = $conn->query($food_query);

$food_items = [
    'Ethiopian Cuisine' => [],
    'International Cuisine' => [],
    'Desserts' => [],
    'Beverages' => []
];

// Image counters for each category
$ethiopian_images = [];
$international_images = [];

// Pre-populate image arrays
for ($i = 1; $i <= 41; $i++) {
    $ethiopian_images[] = 'assets/images/food/ethiopian/food' . $i . '.jpg';
}
for ($i = 1; $i <= 10; $i++) {
    $international_images[] = 'assets/images/food/international/i' . $i . '.jpg';
}

// Shuffle arrays to randomize image assignment
shuffle($ethiopian_images);
shuffle($international_images);

$ethiopian_img_index = 0;
$international_img_index = 0;

// Smart beverage image mapping based on item name
function get_beverage_image($item_name) {
    $name_lower = strtolower($item_name);
    
    // Coffee items - use coffee-specific images
    if (stripos($name_lower, 'coffee') !== false) {
        return 'assets/images/food/beverages/b2.jpg'; // Coffee image
    }
    // Tea items
    elseif (stripos($name_lower, 'tea') !== false) {
        return 'assets/images/food/beverages/b8.jpg'; // Tea image
    }
    // Juice items
    elseif (stripos($name_lower, 'juice') !== false) {
        return 'assets/images/food/beverages/b3.jpg'; // Juice image
    }
    // Smoothie items
    elseif (stripos($name_lower, 'smoothie') !== false) {
        return 'assets/images/food/beverages/b4.jpg'; // Smoothie image
    }
    // Soft drinks / Soda
    elseif (stripos($name_lower, 'cola') !== false || 
            stripos($name_lower, 'pepsi') !== false || 
            stripos($name_lower, 'soda') !== false ||
            stripos($name_lower, 'soft drink') !== false) {
        return 'assets/images/food/beverages/b1.jpg'; // Soft drinks image
    }
    // Water
    elseif (stripos($name_lower, 'water') !== false) {
        return 'assets/images/food/beverages/b9.jpg'; // Water image
    }
    // Milk-based drinks
    elseif (stripos($name_lower, 'milk') !== false || 
            stripos($name_lower, 'shake') !== false) {
        return 'assets/images/food/beverages/b5.jpg'; // Milk drinks image
    }
    // Default beverage image
    else {
        return 'assets/images/food/beverages/b6.jpg';
    }
}

// Categorize food items
while ($row = $food_result->fetch_assoc()) {
    $item_data = [
        'price' => $row['price'],
        'description' => $row['description'],
        'image' => ''
    ];
    
    // Categorize based on name and assign unique images
    // Check for beverages FIRST (before Ethiopian check) to catch "Ethiopian Coffee Ceremony"
    if (stripos($row['name'], 'Juice') !== false || 
        stripos($row['name'], 'Smoothie') !== false || 
        stripos($row['name'], 'Coffee') !== false ||
        stripos($row['name'], 'Tea') !== false ||
        stripos($row['name'], 'Water') !== false ||
        stripos($row['name'], 'Soda') !== false ||
        stripos($row['name'], 'Cola') !== false) {
        $item_data['image'] = get_beverage_image($row['name']);
        $food_items['Beverages'][$row['name']] = $item_data;
    } elseif (stripos($row['name'], 'Ethiopian') !== false) {
        $item_data['image'] = $ethiopian_images[$ethiopian_img_index % count($ethiopian_images)];
        $ethiopian_img_index++;
        $food_items['Ethiopian Cuisine'][$row['name']] = $item_data;
    } elseif (stripos($row['name'], 'International') !== false || 
              in_array($row['name'], ['Grilled Steak', 'Pasta Carbonara', 'Grilled Salmon', 'Caesar Salad'])) {
        $item_data['image'] = $international_images[$international_img_index % count($international_images)];
        $international_img_index++;
        $food_items['International Cuisine'][$row['name']] = $item_data;
    } elseif (in_array($row['name'], ['Chocolate Lava Cake', 'Tiramisu'])) {
        $item_data['image'] = $international_images[$international_img_index % count($international_images)];
        $international_img_index++;
        $food_items['Desserts'][$row['name']] = $item_data;
    } else {
        // Default to International if not categorized
        $item_data['image'] = $international_images[$international_img_index % count($international_images)];
        $international_img_index++;
        $food_items['International Cuisine'][$row['name']] = $item_data;
    }
}

// Remove empty categories
$food_items = array_filter($food_items, function($category) {
    return !empty($category);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Booking - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2) !important;
        }
        
        .card-img-top {
            transition: transform 0.3s ease;
        }
        
        .card:hover .card-img-top {
            transform: scale(1.05);
        }
        
        .food-item:checked ~ label {
            color: #f7931e;
            font-weight: bold;
        }
        
        .quantity-input:focus {
            border-color: #f7931e;
            box-shadow: 0 0 0 0.2rem rgba(247, 147, 30, 0.25);
        }
        
        .text-gold {
            color: #f7931e !important;
        }
        
        .btn-gold {
            background: linear-gradient(135deg, #f7931e 0%, #ff6b35 100%);
            border: none;
            color: white;
        }
        
        .btn-gold:hover {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <section class="py-5">
        <div class="container">
            <!-- Clear Page Identifier -->
            <div class="alert alert-success border-success mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="alert-heading mb-2">
                            <i class="fas fa-utensils"></i> <?php echo __('food.title'); ?>
                        </h4>
                        <p class="mb-0">
                            <strong>This is the FOOD BOOKING page.</strong> Select your food items and optionally reserve a table.
                            <br><small>Looking to book a room? <a href="booking.php" class="alert-link">Click here for Room Booking</a></small>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-2 mt-md-0">
                        <i class="fas fa-utensils fa-4x text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col">
                    <a href="services.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> <?php echo __('food.back_to_services'); ?>
                    </a>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header text-white" style="background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); padding: 1.5rem;">
                            <h3 class="mb-0 fw-bold" style="font-size: 1.75rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                                <i class="fas fa-utensils me-2"></i> <?php echo __('food.title'); ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="foodOrderForm">
                                <!-- Hidden fields for selected food item -->
                                <input type="hidden" name="selected_item" value="<?php echo htmlspecialchars($selected_item); ?>">
                                <input type="hidden" name="selected_price" value="<?php echo $selected_price; ?>">
                                
                                <!-- Order Summary Info -->
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Note:</strong> You have selected food items from the Services menu. 
                                    Please choose your reservation date and time below.
                                </div>
                                
                                <!-- Table Reservation -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-chair text-gold"></i> <?php echo __('food.table_reservation'); ?>
                                    </h5>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="table_reservation" 
                                               id="tableReservation" checked onchange="toggleReservationFields()">
                                        <label class="form-check-label" for="tableReservation">
                                            <?php echo __('food.reserve_table'); ?>
                                        </label>
                                    </div>
                                    
                                    <div id="reservationFields">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><?php echo __('food.reservation_date'); ?></label>
                                                <input type="date" name="reservation_date" class="form-control" 
                                                       min="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><?php echo __('food.reservation_time'); ?></label>
                                                <select name="reservation_time" class="form-select" required>
                                                    <option value="">Select time...</option>
                                                    <option value="12:00">12:00 PM</option>
                                                    <option value="12:30">12:30 PM</option>
                                                    <option value="13:00">1:00 PM</option>
                                                    <option value="13:30">1:30 PM</option>
                                                    <option value="14:00">2:00 PM</option>
                                                    <option value="18:00">6:00 PM</option>
                                                    <option value="18:30">6:30 PM</option>
                                                    <option value="19:00">7:00 PM</option>
                                                    <option value="19:30">7:30 PM</option>
                                                    <option value="20:00">8:00 PM</option>
                                                    <option value="20:30">8:30 PM</option>
                                                    <option value="21:00">9:00 PM</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><?php echo __('food.number_of_customers'); ?></label>
                                                <input type="number" name="customers" class="form-control" 
                                                       min="1" max="12" value="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Special Requests -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold"><?php echo __('booking.special_requests'); ?></label>
                                    <textarea name="special_requests" class="form-control" rows="3" 
                                              placeholder="<?php echo __('booking.special_requests'); ?>..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-gold btn-lg w-100">
                                    <i class="fas fa-check-circle"></i> <?php echo __('food.place_order'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 100px;">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><?php echo __('food.order_summary'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div id="orderSummary">
                                <?php if (!empty($selected_item)): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>Food Item Selected</strong>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                        <div>
                                            <strong><?php echo htmlspecialchars($selected_item); ?></strong>
                                            <br><small class="text-muted">Quantity: 1</small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-gold"><?php echo format_currency($selected_price); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <span class="text-gold fs-4"><?php echo format_currency($selected_price); ?></span>
                                </div>
                                <div class="text-muted small mt-3">
                                    <p class="mb-0"><i class="fas fa-info-circle"></i> Complete the reservation details below to place your order.</p>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>No Item Selected</strong>
                                    <p class="mb-0 mt-2 small">Please select a food item from the Services menu first.</p>
                                </div>
                                <?php endif; ?>
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
        // Toggle reservation fields
        function toggleReservationFields() {
            const checkbox = document.getElementById('tableReservation');
            const fields = document.getElementById('reservationFields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
            
            // Make fields required/optional based on checkbox
            const inputs = fields.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.required = checkbox.checked;
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show reservation fields by default since checkbox is checked
            toggleReservationFields();
        });
    </script>
</body>
</html>