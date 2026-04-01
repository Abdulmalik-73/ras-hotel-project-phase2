<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'food-booking.php';
    header('Location: login.php?redirect=food-booking');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug: Log form submission
    error_log("Food order form submitted with data: " . print_r($_POST, true));
    
    $food_items = $_POST['food_items'] ?? [];
    $table_reservation = isset($_POST['table_reservation']) ? 1 : 0;
    $reservation_date = sanitize_input($_POST['reservation_date'] ?? '');
    $reservation_time = sanitize_input($_POST['reservation_time'] ?? '');
    $guests = (int)($_POST['guests'] ?? 1);
    $special_requests = sanitize_input($_POST['special_requests'] ?? '');
    
    // Debug: Log processed data
    error_log("Processed food items: " . print_r($food_items, true));
    
    // Process food items with quantities > 0
    $valid_items = [];
    
    // Get all food items from database for validation
    $all_food_query = "SELECT name, price FROM services WHERE category = 'restaurant' AND status = 'active'";
    $all_food_result = $conn->query($all_food_query);
    $food_prices = [];
    
    while ($row = $all_food_result->fetch_assoc()) {
        $food_prices[$row['name']] = $row['price'];
    }
    
    // Match selected items with their quantities from POST data
    if (!empty($food_items)) {
        foreach ($food_items as $item_name) {
            // Get quantity from individual field
            $quantity_field = 'quantity_' . str_replace(' ', '_', $item_name);
            $quantity = isset($_POST[$quantity_field]) ? (int)$_POST[$quantity_field] : 0;
            
            if ($quantity > 0 && isset($food_prices[$item_name])) {
                $valid_items[] = [
                    'name' => $item_name,
                    'quantity' => $quantity,
                    'price' => $food_prices[$item_name]
                ];
            }
        }
    }
    
    // Validate maximum 3 items
    if (count($valid_items) > 3) {
        $error = 'Maximum 3 different food items allowed per order. You selected ' . count($valid_items) . ' items.';
        error_log("Error: Too many items selected - " . count($valid_items));
    } elseif (empty($valid_items)) {
        $error = 'Please select at least one food item with quantity greater than 0';
        error_log("Error: No valid food items with quantities");
    } else {
        $total_price = 0;
        $order_items = [];
        
        // Calculate total price and prepare order items
        foreach ($valid_items as $item_data) {
            $item_name = $item_data['name'];
            $quantity = $item_data['quantity'];
            $price = $item_data['price'];
            
            $item_total = $price * $quantity;
            $total_price += $item_total;
            
            $order_items[] = [
                'item' => $item_name,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $item_total
            ];
        }
        
        if (empty($order_items)) {
            $error = 'Please specify quantities for selected items';
        } else {
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

// Categorize food items
while ($row = $food_result->fetch_assoc()) {
    $item_data = [
        'price' => $row['price'],
        'description' => $row['description']
    ];
    
    // Categorize based on name
    if (stripos($row['name'], 'Ethiopian') !== false) {
        $food_items['Ethiopian Cuisine'][$row['name']] = $item_data;
    } elseif (stripos($row['name'], 'International') !== false || 
              in_array($row['name'], ['Grilled Steak', 'Pasta Carbonara', 'Grilled Salmon', 'Caesar Salad'])) {
        $food_items['International Cuisine'][$row['name']] = $item_data;
    } elseif (in_array($row['name'], ['Chocolate Lava Cake', 'Tiramisu'])) {
        $food_items['Desserts'][$row['name']] = $item_data;
    } elseif (stripos($row['name'], 'Juice') !== false || 
              stripos($row['name'], 'Smoothie') !== false || 
              stripos($row['name'], 'Coffee') !== false) {
        $food_items['Beverages'][$row['name']] = $item_data;
    } else {
        // Default to International if not categorized
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
                            <i class="fas fa-utensils"></i> Food Ordering & Table Reservation
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
                        <i class="fas fa-arrow-left"></i> Back to Services
                    </a>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header text-white" style="background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); padding: 1.5rem;">
                            <h3 class="mb-0 fw-bold" style="font-size: 1.75rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                                <i class="fas fa-utensils me-2"></i> Food Order & Table Reservation
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
                                <!-- Food Selection -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-utensils text-gold"></i> Select Food Items
                                    </h5>
                                    
                                    <?php foreach ($food_items as $category => $items): ?>
                                    <div class="mb-4">
                                        <h6 class="text-gold"><?php echo $category; ?></h6>
                                        <div class="row">
                                            <?php foreach ($items as $item_name => $item_data): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-body">
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input food-item" type="checkbox" 
                                                                   name="food_items[]" value="<?php echo $item_name; ?>" 
                                                                   id="item_<?php echo str_replace(' ', '_', $item_name); ?>">
                                                            <label class="form-check-label fw-bold" for="item_<?php echo str_replace(' ', '_', $item_name); ?>">
                                                                <?php echo $item_name; ?>
                                                            </label>
                                                        </div>
                                                        <p class="small text-muted mb-2"><?php echo $item_data['description']; ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="h6 text-gold mb-0"><?php echo format_currency($item_data['price']); ?></span>
                                                            <div class="input-group" style="width: 100px;">
                                                                <span class="input-group-text">Qty</span>
                                                                <input type="number" class="form-control quantity-input" 
                                                                       name="quantity_<?php echo str_replace(' ', '_', $item_name); ?>" 
                                                                       min="0" max="10" value="0" 
                                                                       data-item="<?php echo $item_name; ?>"
                                                                       data-price="<?php echo $item_data['price']; ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Table Reservation -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-chair text-gold"></i> Table Reservation (Optional)
                                    </h5>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="table_reservation" 
                                               id="tableReservation" onchange="toggleReservationFields()">
                                        <label class="form-check-label" for="tableReservation">
                                            I want to reserve a table for dining
                                        </label>
                                    </div>
                                    
                                    <div id="reservationFields" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Reservation Date</label>
                                                <input type="date" name="reservation_date" class="form-control" 
                                                       min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Reservation Time</label>
                                                <select name="reservation_time" class="form-select">
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
                                                <label class="form-label">Number of Guests</label>
                                                <input type="number" name="guests" class="form-control" 
                                                       min="1" max="12" value="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Special Requests -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Special Requests</label>
                                    <textarea name="special_requests" class="form-control" rows="3" 
                                              placeholder="Any dietary restrictions, allergies, or special requests..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-gold btn-lg w-100">
                                    <i class="fas fa-check-circle"></i> Place Food Order
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 100px;">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div id="orderSummary">
                                <p class="text-muted text-center py-4">
                                    <i class="fas fa-info-circle"></i><br>
                                    Select food items to see pricing
                                </p>
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
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
    </script>
    <script>
        function toggleReservationFields() {
            const checkbox = document.getElementById('tableReservation');
            const fields = document.getElementById('reservationFields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }
        
        function updateOrderSummary() {
            const checkboxes = document.querySelectorAll('.food-item:checked');
            const quantities = document.querySelectorAll('.quantity-input');
            let totalPrice = 0;
            let orderItems = [];
            let itemCount = 0;
            
            checkboxes.forEach((checkbox, index) => {
                const quantityInput = checkbox.closest('.card-body').querySelector('.quantity-input');
                const quantity = parseInt(quantityInput.value) || 0;
                const price = parseFloat(quantityInput.dataset.price);
                
                if (quantity > 0) {
                    itemCount++;
                    const itemTotal = price * quantity;
                    totalPrice += itemTotal;
                    orderItems.push({
                        name: checkbox.value,
                        quantity: quantity,
                        price: price,
                        total: itemTotal
                    });
                }
            });
            
            let html = '';
            if (orderItems.length > 0) {
                // Show item count warning if more than 3
                if (itemCount > 3) {
                    html += '<div class="alert alert-warning alert-sm mb-3"><i class="fas fa-exclamation-triangle"></i> Maximum 3 different food items allowed. Please remove ' + (itemCount - 3) + ' item(s).</div>';
                } else {
                    html += '<div class="alert alert-info alert-sm mb-3"><i class="fas fa-info-circle"></i> ' + itemCount + ' of 3 items selected</div>';
                }
                
                html += '<div class="mb-3">';
                orderItems.forEach(item => {
                    html += `
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <strong>${item.name}</strong><br>
                                <small class="text-muted">${formatCurrency(item.price)} × ${item.quantity}</small>
                            </div>
                            <div class="text-end">
                                ${formatCurrency(item.total)}
                            </div>
                        </div>
                    `;
                });
                html += '</div><hr><div class="d-flex justify-content-between"><strong>Total:</strong> <span class="text-gold fs-4">' + formatCurrency(totalPrice) + '</span></div>';
            } else {
                html = '<p class="text-muted text-center py-4"><i class="fas fa-info-circle"></i><br>Select food items to see pricing<br><small>You can select up to 3 different items</small></p>';
            }
            
            document.getElementById('orderSummary').innerHTML = html;
            
            // Disable submit button if more than 3 items
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = itemCount > 3 || itemCount === 0;
            }
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.food-item');
            const quantities = document.querySelectorAll('.quantity-input');
            const form = document.getElementById('foodOrderForm');
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const quantityInput = this.closest('.card-body').querySelector('.quantity-input');
                    
                    // Count currently selected items
                    const selectedCount = document.querySelectorAll('.food-item:checked').length;
                    
                    if (this.checked) {
                        // Check if already at max
                        if (selectedCount > 3) {
                            this.checked = false;
                            alert('Maximum 3 different food items allowed per order. Please remove an item first.');
                            return;
                        }
                        
                        if (quantityInput.value == 0) {
                            quantityInput.value = 1;
                        }
                    } else {
                        quantityInput.value = 0;
                    }
                    updateOrderSummary();
                });
            });
            
            quantities.forEach(input => {
                input.addEventListener('change', function() {
                    const checkbox = this.closest('.card-body').querySelector('.food-item');
                    
                    if (this.value > 0) {
                        // Count currently selected items
                        const selectedCount = document.querySelectorAll('.food-item:checked').length;
                        
                        if (!checkbox.checked && selectedCount >= 3) {
                            this.value = 0;
                            alert('Maximum 3 different food items allowed per order. Please remove an item first.');
                            return;
                        }
                        
                        checkbox.checked = true;
                    } else {
                        checkbox.checked = false;
                    }
                    updateOrderSummary();
                });
            });
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                const selectedItems = document.querySelectorAll('.food-item:checked');
                let validItemCount = 0;
                
                selectedItems.forEach(checkbox => {
                    const quantityInput = checkbox.closest('.card-body').querySelector('.quantity-input');
                    if (parseInt(quantityInput.value) > 0) {
                        validItemCount++;
                    }
                });
                
                if (validItemCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one food item with quantity greater than 0');
                    return false;
                }
                
                if (validItemCount > 3) {
                    e.preventDefault();
                    alert('Maximum 3 different food items allowed per order. You have selected ' + validItemCount + ' items. Please remove ' + (validItemCount - 3) + ' item(s).');
                    return false;
                }
                
                return true;
            });
            
            // Initial update
            updateOrderSummary();
        });
    </script>
</body>
</html>