<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-hotel text-gold"></i> Ras <span class="text-gold">Hotel</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><?php echo __('nav.home'); ?></a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo __('nav.services'); ?>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="servicesDropdown">
                        <li><a class="dropdown-item active" href="rooms.php" style="background-color: #f7931e; color: white;"><i class="fas fa-bed me-2"></i> <?php echo __('rooms.our_rooms'); ?></a></li>
                        <li><a class="dropdown-item" href="services.php#restaurant"><i class="fas fa-utensils me-2"></i> <?php echo __('services.restaurant'); ?></a></li>
                        <li><a class="dropdown-item" href="services.php#ethiopian-cuisine"><i class="fas fa-pepper-hot me-2"></i> <?php echo __('food.ethiopian_cuisine'); ?></a></li>
                        <li><a class="dropdown-item" href="services.php#international-buffet"><i class="fas fa-globe me-2"></i> <?php echo __('food.international_cuisine'); ?></a></li>
                        <li><a class="dropdown-item" href="services.php#spa"><i class="fas fa-spa me-2"></i> <?php echo __('services.spa'); ?></a></li>
                        <li><a class="dropdown-item" href="services.php#laundry"><i class="fas fa-tshirt me-2"></i> <?php echo __('services.laundry'); ?></a></li>
                        <li><a class="dropdown-item" href="services.php#amenities"><i class="fas fa-concierge-bell me-2"></i> <?php echo __('services.room_service'); ?></a></li>
                        <li><a class="dropdown-item" href="services.php#amenities"><i class="fas fa-hotel me-2"></i> Hotel Amenities</a></li>
                    </ul>
                </li>
                <?php if (!is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus"></i> <?php echo __('nav.register'); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (is_logged_in()): ?>
                    <?php if (in_array($_SESSION['user_role'], ['customer', 'guest'])): ?>
                    <li class="nav-item dropdown">
                        <a class="btn btn-gold btn-sm ms-2 dropdown-toggle" href="#" id="bookingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-calendar-check"></i> <?php echo __('nav.book_now'); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="bookingDropdown">
                            <li><a class="dropdown-item" href="rooms.php"><i class="fas fa-bed"></i> <?php echo __('booking.book_room'); ?></a></li>
                            <li><a class="dropdown-item" href="services.php#restaurant"><i class="fas fa-utensils"></i> <?php echo __('booking.order_food'); ?></a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item dropdown">
                        <a class="btn btn-gold btn-sm ms-2 dropdown-toggle" href="#" id="bookingDropdownCustomer" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?php echo __('rooms.login_to_book'); ?>">
                            <i class="fas fa-calendar-check"></i> <?php echo __('nav.book_now'); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="bookingDropdownGuest">
                            <li><a class="dropdown-item" href="rooms.php"><i class="fas fa-bed"></i> <?php echo __('booking.book_room'); ?></a></li>
                            <li><a class="dropdown-item" href="services.php#restaurant"><i class="fas fa-utensils"></i> <?php echo __('booking.order_food'); ?></a></li>
                        </ul>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="about.php"><?php echo __('nav.about'); ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php"><?php echo __('nav.contact'); ?></a>
                </li>
                
                <!-- Shopping Cart -->
                <li class="nav-item">
                    <a class="nav-link position-relative" href="cart.php" id="cartLink">
                        <i class="fas fa-shopping-cart"></i> <?php echo __('nav.cart'); ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-gold cart-badge" style="display: none;">
                            0
                        </span>
                    </a>
                </li>
                <?php if (is_logged_in()): ?>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard/admin.php">
                                <i class="fas fa-tachometer-alt"></i> Admin Panel
                            </a>
                        </li>
                    <?php elseif ($_SESSION['user_role'] === 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard/manager.php">
                                <i class="fas fa-chart-line"></i> Manager Dashboard
                            </a>
                        </li>
                    <?php elseif ($_SESSION['user_role'] === 'receptionist'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard/receptionist.php">
                                <i class="fas fa-concierge-bell"></i> Reception
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'receptionist'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="payment-verification.php">
                            <i class="fas fa-shield-alt"></i> Payment Verification
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (!in_array($_SESSION['user_role'], ['customer', 'guest'])): ?>
                    <!-- User Account Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle p-0" href="#" id="userAccountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-profile-icon">
                                <i class="fas fa-user-circle fa-2x text-gold"></i>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end user-dropdown" aria-labelledby="userAccountDropdown">
                            <!-- User Info Header -->
                            <li class="dropdown-header" style="padding: 0.3rem 0.5rem;">
                                <div class="text-center">
                                    <div class="fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                                    <div class="small text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
                                    <span class="badge bg-gold mt-1" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider" style="margin: 0.2rem 0;"></li>
                            
                            <!-- Profile Section -->
                            <li class="dropdown-header text-muted small fw-bold" style="padding: 0.25rem 0.8rem; font-size: 0.7rem;">
                                <i class="fas fa-user me-1" style="font-size: 0.7rem;"></i> <?php echo strtoupper(__('account.profile')); ?>
                            </li>
                            <li><a class="dropdown-item" href="profile.php" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-id-card me-2 text-primary" style="font-size: 0.8rem;"></i> <?php echo __('account.view_profile'); ?></a></li>
                            <li><a class="dropdown-item" href="profile.php?tab=photo" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-camera me-2 text-info" style="font-size: 0.8rem;"></i> <?php echo __('account.change_photo'); ?></a></li>
                            <li><a class="dropdown-item" href="profile.php?tab=info" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-edit me-2 text-success" style="font-size: 0.8rem;"></i> <?php echo __('account.update_info'); ?></a></li>
                            <li><hr class="dropdown-divider" style="margin: 0.2rem 0;"></li>
                            
                            <!-- Settings Section -->
                            <li class="dropdown-header text-muted small fw-bold" style="padding: 0.25rem 0.8rem; font-size: 0.7rem;">
                                <i class="fas fa-cog me-1" style="font-size: 0.7rem;"></i> <?php echo strtoupper(__('account.settings')); ?>
                            </li>
                            <li><a class="dropdown-item" href="settings.php?tab=password" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-key me-2 text-warning" style="font-size: 0.8rem;"></i> <?php echo __('account.change_password'); ?></a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=notifications" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-bell me-2 text-info" style="font-size: 0.8rem;"></i> <?php echo __('account.notifications'); ?></a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=privacy" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-shield-alt me-2 text-primary" style="font-size: 0.8rem;"></i> <?php echo __('account.privacy'); ?></a></li>
                            
                            <!-- Language Selector with Inline Expansion -->
                            <li>
                                <a class="dropdown-item language-toggle" href="#" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;" data-target="lang-menu-1">
                                    <i class="fas fa-language me-2 text-success"></i> <?php echo __('account.language'); ?> <i class="fas fa-chevron-down float-end mt-1"></i>
                                </a>
                                <div id="lang-menu-1" class="language-submenu" style="display: none; padding-left: 1.8rem; background-color: #f8f9fa;">
                                    <a class="dropdown-item" href="#" onclick="switchLanguage('en'); return false;">🇬🇧 <?php echo __('languages.en'); ?></a>
                                    <a class="dropdown-item" href="#" onclick="switchLanguage('am'); return false;">🇪🇹 <?php echo __('languages.am'); ?></a>
                                    <a class="dropdown-item" href="#" onclick="switchLanguage('om'); return false;">🇪🇹 <?php echo __('languages.om'); ?></a>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider" style="margin: 0.2rem 0;"></li>
                            
                            <li><a class="dropdown-item" href="logout.php" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-sign-out-alt me-2 text-danger" style="font-size: 0.8rem;"></i> <?php echo __('account.logout'); ?></a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <!-- User Account Dropdown for Customers -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle p-0" href="#" id="userAccountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-profile-icon">
                                <i class="fas fa-user-circle fa-2x text-gold"></i>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end user-dropdown" aria-labelledby="userAccountDropdown">
                            <!-- User Info Header -->
                            <li class="dropdown-header" style="padding: 0.3rem 0.5rem;">
                                <div class="text-center">
                                    <div class="fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                                    <div class="small text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
                                    <span class="badge bg-gold mt-1" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider" style="margin: 0.2rem 0;"></li>
                            
                            <!-- Profile Section -->
                            <li class="dropdown-header text-muted small fw-bold" style="padding: 0.25rem 0.8rem; font-size: 0.7rem;">
                                <i class="fas fa-user me-1" style="font-size: 0.7rem;"></i> <?php echo strtoupper(__('account.profile')); ?>
                            </li>
                            <li><a class="dropdown-item" href="profile.php" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-id-card me-2 text-primary" style="font-size: 0.8rem;"></i> <?php echo __('account.view_profile'); ?></a></li>
                            <li><a class="dropdown-item" href="profile.php?tab=photo" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-camera me-2 text-info" style="font-size: 0.8rem;"></i> <?php echo __('account.change_photo'); ?></a></li>
                            <li><a class="dropdown-item" href="profile.php?tab=info" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-edit me-2 text-success" style="font-size: 0.8rem;"></i> <?php echo __('account.update_info'); ?></a></li>
                            <li><a class="dropdown-item" href="my-bookings.php" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-calendar-check me-2 text-warning" style="font-size: 0.8rem;"></i> <?php echo __('account.my_bookings'); ?></a></li>
                            <li><hr class="dropdown-divider" style="margin: 0.2rem 0;"></li>
                            
                            <!-- Settings Section -->
                            <li class="dropdown-header text-muted small fw-bold" style="padding: 0.25rem 0.8rem; font-size: 0.7rem;">
                                <i class="fas fa-cog me-1" style="font-size: 0.7rem;"></i> <?php echo strtoupper(__('account.settings')); ?>
                            </li>
                            <li><a class="dropdown-item" href="settings.php?tab=password" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-key me-2 text-warning" style="font-size: 0.8rem;"></i> <?php echo __('account.change_password'); ?></a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=notifications" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-bell me-2 text-info" style="font-size: 0.8rem;"></i> <?php echo __('account.notifications'); ?></a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=privacy" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-shield-alt me-2 text-primary" style="font-size: 0.8rem;"></i> <?php echo __('account.privacy'); ?></a></li>
                            
                            <!-- Language Selector with Inline Expansion -->
                            <li>
                                <a class="dropdown-item language-toggle" href="#" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;" data-target="lang-menu-2">
                                    <i class="fas fa-language me-2 text-success"></i> <?php echo __('account.language'); ?> <i class="fas fa-chevron-down float-end mt-1"></i>
                                </a>
                                <div id="lang-menu-2" class="language-submenu" style="display: none; padding-left: 1.8rem; background-color: #f8f9fa;">
                                    <a class="dropdown-item" href="#" onclick="switchLanguage('en'); return false;">🇬🇧 <?php echo __('languages.en'); ?></a>
                                    <a class="dropdown-item" href="#" onclick="switchLanguage('am'); return false;">🇪🇹 <?php echo __('languages.am'); ?></a>
                                    <a class="dropdown-item" href="#" onclick="switchLanguage('om'); return false;">🇪🇹 <?php echo __('languages.om'); ?></a>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider" style="margin: 0.2rem 0;"></li>
                            
                            <li><a class="dropdown-item" href="logout.php" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-sign-out-alt me-2 text-danger" style="font-size: 0.8rem;"></i> <?php echo __('account.logout'); ?></a></li>
                        </ul>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>


<script>
// Toggle language submenu
document.addEventListener('DOMContentLoaded', function() {
    const languageToggles = document.querySelectorAll('.language-toggle');
    
    languageToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const targetId = this.getAttribute('data-target');
            const submenu = document.getElementById(targetId);
            const chevron = this.querySelector('.fa-chevron-down, .fa-chevron-up');
            
            if (submenu.style.display === 'none') {
                submenu.style.display = 'block';
                if (chevron) chevron.classList.replace('fa-chevron-down', 'fa-chevron-up');
            } else {
                submenu.style.display = 'none';
                if (chevron) chevron.classList.replace('fa-chevron-up', 'fa-chevron-down');
            }
        });
    });
});

function switchLanguage(lang) {
    <?php
    // Use SITE_URL from .env — already loaded by config.php
    // Fallback: build from server variables
    $siteUrl = defined('SITE_URL') ? SITE_URL : (getenv('SITE_URL') ?: '');
    if (empty($siteUrl)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Get project folder from __FILE__: .../final_project2/includes/navbar.php
        $docRoot  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
        $filePath = str_replace('\\', '/', dirname(__DIR__)); // project root
        $subPath  = str_replace($docRoot, '', $filePath);
        $siteUrl  = $protocol . '://' . $host . $subPath;
    }
    $siteUrl = rtrim($siteUrl, '/');
    $apiUrl  = $siteUrl . '/api/switch_language.php';
    ?>
    const apiUrl = '<?php echo htmlspecialchars($apiUrl, ENT_QUOTES); ?>';

    fetch(apiUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'lang=' + encodeURIComponent(lang)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            location.reload();
        }
    })
    .catch(() => location.reload());
}
</script>
