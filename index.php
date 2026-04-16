<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

// Get rooms for gallery - limit to available unique images (22 room images)
$rooms_query = "SELECT id, name, room_number FROM rooms WHERE status = 'active' ORDER BY RAND() LIMIT 22";
$rooms_result = $conn->query($rooms_query);

// Get food items for gallery - limit to available unique images (24 food images)
$foods_query = "SELECT id, name, image FROM services WHERE category = 'restaurant' AND status = 'active' ORDER BY RAND() LIMIT 24";
$foods_result = $conn->query($foods_query);

// Room images array - Using your actual images
$room_images = [
    'assets/images/rooms/deluxe/room.jpg',
    'assets/images/rooms/deluxe/room2.jpg',
    'assets/images/rooms/deluxe/room3.jpg',
    'assets/images/rooms/deluxe/room4.jpg',
    'assets/images/rooms/deluxe/room5.jpg',
    'assets/images/rooms/deluxe/room6.jpg',
    'assets/images/rooms/deluxe/room7.jpg',
    'assets/images/rooms/deluxe/room8.jpg',
    'assets/images/rooms/deluxe/room9.jpg',
    'assets/images/rooms/deluxe/room10.jpg',
    'assets/images/rooms/standard/room12.jpg',
    'assets/images/rooms/standard/room13.jpg',
    'assets/images/rooms/standard/room14.jpg',
    'assets/images/rooms/standard/room15.jpg',
    'assets/images/rooms/standard/room16.jpg',
    'assets/images/rooms/suite/room21.jpg',
    'assets/images/rooms/suite/room22.jpg',
    'assets/images/rooms/suite/room23.jpg',
    'assets/images/rooms/family/room27.jpg',
    'assets/images/rooms/family/room28.jpg',
    'assets/images/rooms/presidential/room35.jpg',
    'assets/images/rooms/presidential/room36.jpg',
];

// Food images array - Using your actual images
$food_images = [
    'assets/images/food/ethiopian/food1.jpg',
    'assets/images/food/ethiopian/food2.jpg',
    'assets/images/food/ethiopian/food3.jpg',
    'assets/images/food/ethiopian/food4.jpg',
    'assets/images/food/ethiopian/food5.jpg',
    'assets/images/food/ethiopian/food6.jpg',
    'assets/images/food/ethiopian/food7.jpg',
    'assets/images/food/ethiopian/food8.jpg',
    'assets/images/food/ethiopian/food10.jpg',
    'assets/images/food/ethiopian/food12.jpg',
    'assets/images/food/international/i1.jpg',
    'assets/images/food/international/i2.jpg',
    'assets/images/food/international/i3.jpg',
    'assets/images/food/international/i5.jpg',
    'assets/images/food/international/i6.jpg',
    'assets/images/food/international/i7.jpg',
    'assets/images/food/international/i8.jpg',
    'assets/images/food/international/i10.jpg',
    'assets/images/food/beverages/b1.jpg',
    'assets/images/food/beverages/b2.jpg',
    'assets/images/food/beverages/b3.jpg',
    'assets/images/food/beverages/b5.jpg',
    'assets/images/food/beverages/b7.jpg',
    'assets/images/food/beverages/b9.jpg',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harar Ras Hotel - Welcome</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Clean Room Images Grid - Pexels Style */
        .rooms-image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 40px;
        }
        
        .room-image-item {
            position: relative;
            width: 100%;
            height: 280px;
            overflow: hidden;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .room-image-item:hover {
            transform: scale(1.02);
        }
        
        .room-image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        /* Responsive Grid */
        @media (min-width: 1400px) {
            .rooms-image-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }
        
        @media (min-width: 1200px) and (max-width: 1399px) {
            .rooms-image-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }
        
        @media (min-width: 992px) and (max-width: 1199px) {
            .rooms-image-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (min-width: 768px) and (max-width: 991px) {
            .rooms-image-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
            }
            
            .room-image-item {
                height: 240px;
            }
        }
        
        @media (min-width: 576px) and (max-width: 767px) {
            .rooms-image-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .room-image-item {
                height: 220px;
            }
        }
        
        @media (max-width: 575px) {
            .rooms-image-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .room-image-item {
                height: 250px;
            }
        }
        
        /* Gallery Card Styles - Responsive Grid Layout */
        .gallery-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        
        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .gallery-card-image {
            width: 100%;
            height: 280px;
            overflow: hidden;
            background: #f5f5f5;
        }
        
        .gallery-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-card-body {
            padding: 15px;
        }
        
        .gallery-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--slate-dark);
            margin: 0 0 8px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .gallery-card-description {
            font-size: 0.85rem;
            line-height: 1.4;
            color: #6c757d;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .gallery-card-image {
                height: 240px;
            }
        }
        
        @media (max-width: 768px) {
            .gallery-card-image {
                height: 220px;
            }
            
            .gallery-card-title {
                font-size: 0.95rem;
            }
            
            .gallery-card-description {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .gallery-card-image {
                height: 200px;
            }
            
            .gallery-card-title {
                font-size: 0.9rem;
            }
            
            .gallery-card-description {
                font-size: 0.75rem;
            }
            
            .gallery-card-body {
                padding: 12px;
            }
        }
        
        /* Hero Section */
        .hero-section {
            padding: 80px 0;
            color: white;
            text-align: center;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            min-height: 80px;
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 30px;
            opacity: 0.9;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
            min-height: 40px;
        }
        
        /* Typing cursor effect */
        .typing::after {
            content: "|";
            animation: blink 1s infinite;
            margin-left: 2px;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .hero-subtitle {
                font-size: 1.2rem;
            }
        }
        
        /* Hero Section Buttons - Modern Semi-Transparent Style with Beautiful Hover */
        .btn-hero {
            background: rgba(255, 255, 255, 0.9);
            color: #1a1a1a;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-hero:hover::before {
            left: 100%;
        }
        
        .btn-hero:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.8);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-hero-primary {
            background: rgba(212, 175, 55, 0.9);
            color: #ffffff;
            border: 2px solid rgba(255, 215, 0, 0.4);
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-hero-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-hero-primary:hover::before {
            left: 100%;
        }
        
        .btn-hero-primary:hover {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.8);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 30px rgba(245, 87, 108, 0.4);
        }
        
        @media (max-width: 768px) {
            .btn-hero,
            .btn-hero-primary {
                padding: 12px 24px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Hero Section -->
    <section class="hero-section" style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('assets/images/hotel/exterior/hotel-main.png') center/cover no-repeat; min-height: 500px; display: flex; align-items: center;">
        <div class="container">
            <h1 class="hero-title" id="hero-title"></h1>
            <p class="hero-subtitle" id="hero-subtitle"></p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="rooms.php" class="btn btn-hero btn-lg">
                    <i class="fas fa-bed me-2"></i><?php echo __('home.view_all_rooms'); ?>
                </a>
                <a href="services.php" class="btn btn-hero-primary btn-lg">
                    <i class="fas fa-concierge-bell me-2"></i><?php echo __('home.our_services'); ?>
                </a>
            </div>
        </div>
    </section>
    
    <!-- Room Images Grid - 39 Rooms -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2"><?php echo __('home.discover_spaces'); ?></h2>
                <p class="text-muted mb-3"><?php echo __('home.explore_text'); ?></p>
                <p class="text-muted small"><?php echo __('home.experience_text'); ?></p>
            </div>
        </div>
        
        <div class="container-fluid px-4">
            <div class="rooms-image-grid">
                <?php
                // Display 40 images total: 39 rooms + 1 food image
                $all_rooms_query = "SELECT id, room_number, image FROM rooms ORDER BY CAST(room_number AS UNSIGNED) LIMIT 39";
                $all_rooms_result = $conn->query($all_rooms_query);
                
                // Images array with 39 room images + 1 food image = 40 total
                $default_images = [
                    // Room images (31 images)
                    'assets/images/rooms/deluxe/room.jpg',
                    'assets/images/rooms/deluxe/room2.jpg',
                    'assets/images/rooms/deluxe/room3.jpg',
                    'assets/images/rooms/deluxe/room4.jpg',
                    'assets/images/rooms/deluxe/room5.jpg',
                    'assets/images/rooms/deluxe/room6.jpg',
                    'assets/images/rooms/deluxe/room7.jpg',
                    'assets/images/rooms/deluxe/room8.jpg',
                    'assets/images/rooms/deluxe/room9.jpg',
                    'assets/images/rooms/deluxe/room10.jpg',
                    'assets/images/rooms/standard/room12.jpg',
                    'assets/images/rooms/standard/room13.jpg',
                    'assets/images/rooms/standard/room14.jpg',
                    'assets/images/rooms/standard/room15.jpg',
                    'assets/images/rooms/standard/room16.jpg',
                    'assets/images/rooms/suite/room21.jpg',
                    'assets/images/rooms/suite/room22.jpg',
                    'assets/images/rooms/suite/room23.jpg',
                    'assets/images/rooms/family/room27.jpg',
                    'assets/images/rooms/family/room28.jpg',
                    'assets/images/rooms/family/room29.jpg',
                    'assets/images/rooms/family/room30.jpg',
                    'assets/images/rooms/family/room31.jpg',
                    'assets/images/rooms/family/room32.jpg',
                    'assets/images/rooms/family/room33.jpg',
                    'assets/images/rooms/family/room34.jpg',
                    'assets/images/rooms/presidential/room35.jpg',
                    'assets/images/rooms/presidential/room36.jpg',
                    'assets/images/rooms/presidential/room37.jpg',
                    'assets/images/rooms/presidential/room38.jpg',
                    'assets/images/rooms/presidential/room39.jpg',
                    // Food images to fill remaining 9 slots (to make 40 total)
                    'assets/images/food/ethiopian/food1.jpg',
                    'assets/images/food/ethiopian/food3.jpg',
                    'assets/images/food/ethiopian/food4.jpg',
                    'assets/images/food/ethiopian/food5.jpg',
                    'assets/images/food/ethiopian/food6.jpg',
                    'assets/images/food/ethiopian/food7.jpg',
                    'assets/images/food/ethiopian/food8.jpg',
                    'assets/images/food/ethiopian/food10.jpg',
                    'assets/images/food/ethiopian/food12.jpg',
                ];
                
                $index = 0;
                $displayed = 0;
                
                // Display 39 rooms
                while ($room = $all_rooms_result->fetch_assoc()):
                    if ($displayed >= 39) break;
                    
                    $room_image = !empty($room['image']) ? $room['image'] : ($default_images[$index] ?? 'assets/images/rooms/deluxe/room.jpg');
                ?>
                
                <div class="room-image-item">
                    <img src="<?php echo htmlspecialchars($room_image); ?>" alt="Room <?php echo htmlspecialchars($room['room_number']); ?>">
                </div>
                
                <?php 
                    $index++;
                    $displayed++;
                endwhile;
                
                // Add 1 food image to make it 40 total
                ?>
                <div class="room-image-item">
                    <img src="assets/images/food/ethiopian/food1.jpg" alt="Ethiopian Cuisine">
                </div>
                <?php
                ?>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Multi-language continuous looping typing animation for hero section
        const translations = {
            en: <?php 
                $en_trans = load_translations('en');
                echo json_encode([
                    'title' => $en_trans['home']['welcome_title'],
                    'subtitle' => $en_trans['home']['welcome_subtitle']
                ]);
            ?>,
            am: <?php 
                $am_trans = load_translations('am');
                echo json_encode([
                    'title' => $am_trans['home']['welcome_title'],
                    'subtitle' => $am_trans['home']['welcome_subtitle']
                ]);
            ?>,
            om: <?php 
                $om_trans = load_translations('om');
                echo json_encode([
                    'title' => $om_trans['home']['welcome_title'],
                    'subtitle' => $om_trans['home']['welcome_subtitle']
                ]);
            ?>
        };
        
        // Get current language from session or default to English
        const currentLang = '<?php echo get_current_language(); ?>';
        
        // Sleep utility function
        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
        
        // Type text character by character
        async function typeText(element, text, speed) {
            element.classList.add('typing');
            for (let i = 0; i <= text.length; i++) {
                element.textContent = text.substring(0, i);
                await sleep(speed);
            }
            element.classList.remove('typing');
        }
        
        // Delete text character by character
        async function deleteText(element, speed = 30) {
            const text = element.textContent;
            for (let i = text.length; i >= 0; i--) {
                element.textContent = text.substring(0, i);
                await sleep(speed);
            }
        }
        
        // Continuous loop animation
        async function startHeroAnimation(lang) {
            const content = translations[lang] || translations.en;
            const titleEl = document.getElementById('hero-title');
            const subtitleEl = document.getElementById('hero-subtitle');
            
            while (true) {
                // Type title
                await typeText(titleEl, content.title, 50);
                await sleep(1000);
                
                // Type subtitle (only after title finishes)
                await typeText(subtitleEl, content.subtitle, 40);
                await sleep(3000);
                
                // Delete subtitle first
                await deleteText(subtitleEl);
                await sleep(500);
                
                // Delete title
                await deleteText(titleEl);
                await sleep(1500);
            }
        }
        
        // Start animation on page load
        window.addEventListener('DOMContentLoaded', function() {
            startHeroAnimation(currentLang);
        });
        
        // Language switching is handled by navbar.php's switchLanguage()
    </script>
</body>
</html>