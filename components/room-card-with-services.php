<?php
/**
 * Room Card Component with Services Display
 * Shows room details, price, and included services
 * Handles booking availability check
 */

// This component expects these variables:
// $room - array with room details
// $check_in_date - optional check-in date
// $check_out_date - optional check-out date
// $show_booking_button - boolean (default: true)

$show_booking_button = $show_booking_button ?? true;
$room_id = $room['id'];
$room_name = htmlspecialchars($room['name']);
$room_number = htmlspecialchars($room['room_number']);
$room_type = htmlspecialchars($room['room_type']);
$room_price = number_format($room['price'], 2);
$room_capacity = $room['capacity'];
$room_description = htmlspecialchars($room['description'] ?? '');
$room_image = $room['image'] ?? 'assets/images/rooms/default-room.jpg';

// Get services for this room
$services_query = "SELECT service_name, service_icon, service_category 
                   FROM room_services 
                   WHERE room_id = ? 
                   ORDER BY display_order ASC";
$services_stmt = $conn->prepare($services_query);
$services_stmt->bind_param("i", $room_id);
$services_stmt->execute();
$services_result = $services_stmt->get_result();
$services = $services_result->fetch_all(MYSQLI_ASSOC);
?>

<div class="room-card" data-room-id="<?php echo $room_id; ?>">
    <div class="room-card-image">
        <img src="<?php echo $room_image; ?>" alt="<?php echo $room_name; ?>" loading="lazy">
        <div class="room-type-badge"><?php echo ucfirst($room_type); ?></div>
    </div>
    
    <div class="room-card-content">
        <div class="room-card-header">
            <h3 class="room-name"><?php echo $room_name; ?></h3>
            <span class="room-number">Room #<?php echo $room_number; ?></span>
        </div>
        
        <p class="room-description"><?php echo $room_description; ?></p>
        
        <div class="room-capacity">
            <i class="fas fa-users"></i>
            <span>Up to <?php echo $room_capacity; ?> guest<?php echo $room_capacity > 1 ? 's' : ''; ?></span>
        </div>
        
        <!-- Services Section -->
        <div class="room-services">
            <h4 class="services-title">
                <i class="fas fa-check-circle"></i> Included Services
            </h4>
            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                    <div class="service-item" title="<?php echo htmlspecialchars($service['service_name']); ?>">
                        <i class="fas <?php echo htmlspecialchars($service['service_icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($service['service_name']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="room-card-footer">
            <div class="room-price">
                <span class="price-label">Price per night</span>
                <span class="price-amount">ETB <?php echo $room_price; ?></span>
            </div>
            
            <?php if ($show_booking_button): ?>
                <button 
                    class="btn btn-primary book-room-btn" 
                    data-room-id="<?php echo $room_id; ?>"
                    data-room-name="<?php echo $room_name; ?>"
                    data-room-price="<?php echo $room['price']; ?>"
                    <?php if (isset($check_in_date) && isset($check_out_date)): ?>
                        data-check-in="<?php echo $check_in_date; ?>"
                        data-check-out="<?php echo $check_out_date; ?>"
                    <?php endif; ?>
                >
                    <i class="fas fa-calendar-check"></i> Book Now
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Availability Status (will be updated via JavaScript) -->
        <div class="availability-status" id="availability-status-<?php echo $room_id; ?>" style="display: none;">
            <div class="alert"></div>
        </div>
    </div>
</div>

<style>
.room-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    margin-bottom: 20px;
}

.room-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.room-card-image {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.room-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.room-type-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(0,0,0,0.7);
    color: #fff;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.room-card-content {
    padding: 20px;
}

.room-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.room-name {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.room-number {
    background: #f0f0f0;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    color: #666;
}

.room-description {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 15px;
}

.room-capacity {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
}

.room-capacity i {
    color: #007bff;
}

/* Services Section */
.room-services {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.services-title {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.services-title i {
    color: #28a745;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.service-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #555;
}

.service-item i {
    color: #007bff;
    font-size: 14px;
    width: 16px;
}

/* Footer */
.room-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.room-price {
    display: flex;
    flex-direction: column;
}

.price-label {
    font-size: 12px;
    color: #666;
}

.price-amount {
    font-size: 24px;
    font-weight: 700;
    color: #007bff;
}

.book-room-btn {
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.book-room-btn:hover {
    transform: scale(1.05);
}

.book-room-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

/* Availability Status */
.availability-status {
    margin-top: 15px;
}

.availability-status .alert {
    padding: 10px 15px;
    border-radius: 6px;
    font-size: 13px;
}

.availability-status .alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.availability-status .alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.availability-status .alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/* Responsive */
@media (max-width: 768px) {
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .room-card-footer {
        flex-direction: column;
        gap: 15px;
    }
    
    .book-room-btn {
        width: 100%;
    }
}
</style>

<script>
// Check room availability when dates are selected
document.addEventListener('DOMContentLoaded', function() {
    const bookButtons = document.querySelectorAll('.book-room-btn');
    
    bookButtons.forEach(button => {
        button.addEventListener('click', function() {
            const roomId = this.dataset.roomId;
            const roomName = this.dataset.roomName;
            const roomPrice = this.dataset.roomPrice;
            const checkIn = this.dataset.checkIn;
            const checkOut = this.dataset.checkOut;
            
            if (checkIn && checkOut) {
                // Check availability first
                checkRoomAvailability(roomId, checkIn, checkOut, roomName, roomPrice);
            } else {
                // Redirect to booking page to select dates
                window.location.href = `booking.php?room_id=${roomId}`;
            }
        });
    });
});

function checkRoomAvailability(roomId, checkIn, checkOut, roomName, roomPrice) {
    const statusDiv = document.getElementById(`availability-status-${roomId}`);
    const button = document.querySelector(`[data-room-id="${roomId}"]`);
    
    // Show loading
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    
    // Make API call
    fetch('api/check_room_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            room_id: roomId,
            check_in_date: checkIn,
            check_out_date: checkOut
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.available) {
            // Room is available - proceed to booking
            window.location.href = `booking.php?room_id=${roomId}&check_in=${checkIn}&check_out=${checkOut}`;
        } else {
            // Room is not available - show message
            statusDiv.style.display = 'block';
            statusDiv.querySelector('.alert').className = 'alert alert-danger';
            statusDiv.querySelector('.alert').innerHTML = `
                <i class="fas fa-exclamation-circle"></i> ${data.message}
            `;
            
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-calendar-check"></i> Book Now';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.style.display = 'block';
        statusDiv.querySelector('.alert').className = 'alert alert-danger';
        statusDiv.querySelector('.alert').textContent = 'Error checking availability. Please try again.';
        
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-calendar-check"></i> Book Now';
    });
}
</script>
