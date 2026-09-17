<?php
// includes/header.php
require_once __DIR__ . '/auth.php';
$base = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookBridge - Peer-to-Peer Textbook Exchange</title>
    <meta name="theme-color" content="#6366f1">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo $base; ?>assets/images/favicon.png" type="image/png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link href="<?php echo $base; ?>assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>" rel="stylesheet">
    <!-- Theme Restore Script (In head to prevent flash) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <!-- Toggle Theme JS helper -->
    <script>
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeToggleIcon(newTheme);
        }

        function updateThemeToggleIcon(theme) {
            const icon = document.getElementById('themeToggleIcon');
            if (icon) {
                if (theme === 'dark') {
                    icon.className = 'bi bi-sun';
                } else {
                    icon.className = 'bi bi-moon-stars';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const activeTheme = document.documentElement.getAttribute('data-theme') || 'light';
            updateThemeToggleIcon(activeTheme);
        });
    </script>
</head>
<body>
    <div id="preloader" class="preloader">
        <div class="preloader-dot"></div>
    </div>
    <script>
        // Ensure preloader is removed once resources load or after a short timeout
        (function() {
            function hidePreloader() {
                var p = document.getElementById('preloader');
                if (!p) return;
                p.classList.add('preloader-hidden');
                setTimeout(function() {
                    if (p && p.parentNode) p.parentNode.removeChild(p);
                }, 700);
            }

            // Prefer window.load to wait for images/CSS; fallback after 2.5s
            window.addEventListener('load', hidePreloader);
            setTimeout(hidePreloader, 2500);
        })();
    </script>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-glass">
        <div class="container">
            <a class="navbar-brand brand-font d-flex align-items-center" href="<?php echo $base; ?>index.php">
                <i class="bi bi-book-half me-2 text-gradient" style="font-size: 1.5rem;"></i>
                <span class="text-gradient">BookBridge</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarText">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php
                    $current_page = basename($_SERVER['PHP_SELF']);
                    $is_in_user_dir = (strpos($_SERVER['PHP_SELF'], '/user/') !== false);
                    $is_in_admin_dir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
                    $is_landing = ($current_page == 'index.php' && !$is_in_user_dir && !$is_in_admin_dir);
                    
                    if ($is_in_user_dir): 
                    ?>
                        <!-- Student Portal Navbar Links -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base; ?>index.php">
                                <i class="bi bi-shop me-1"></i>Browse Books
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>user/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'listings.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>user/listings.php">
                                <i class="bi bi-book-half me-1"></i>My Listings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'transactions.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>user/transactions.php">
                                <i class="bi bi-arrow-left-right me-1"></i>My Trades
                                <span id="navbar-req-badge" class="badge rounded-pill bg-danger d-none">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'messages.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>user/messages.php">
                                <i class="bi bi-chat-text me-1"></i>Inbox
                                <span id="navbar-msg-badge" class="badge rounded-pill bg-danger d-none">0</span>
                            </a>
                        </li>
                    
                    <?php elseif ($is_in_admin_dir): ?>
                        <!-- Admin Portal Navbar Links -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base; ?>index.php">
                                <i class="bi bi-shop me-1"></i>Browse Books
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/users.php">
                                <i class="bi bi-people me-1"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'listings.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/listings.php">
                                <i class="bi bi-book-half me-1"></i>Listings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/reports.php">
                                <i class="bi bi-flag me-1"></i>Reports
                            </a>
                        </li>

                    <?php else: ?>
                        <!-- Landing Page / Public Navbar Links -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($is_landing) ? 'active' : ''; ?>" href="<?php echo $base; ?>index.php#home">Home</a>
                        </li>
                        <?php if ($is_landing): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="#features">Features</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#categories">Categories</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#how-it-works">How It Works</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#testimonials">Testimonials</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#contact">Contact</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base; ?>index.php#features">Features</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base; ?>index.php#how-it-works">How It Works</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item ms-lg-2">
                                <a class="nav-link btn btn-outline-primary btn-sm text-primary py-1 px-3 d-inline-flex align-items-center" href="<?php echo $base; ?>user/dashboard.php" style="border-radius: 20px; font-size: 0.85rem; border: 1.5px solid var(--accent-primary) !important; color: var(--accent-primary) !important; background: transparent;">
                                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (isAdmin()): ?>
                            <li class="nav-item ms-lg-2 border-start border-secondary ps-2 ms-2">
                                <a class="nav-link btn btn-outline-warning btn-sm text-warning py-1 px-3 d-inline-flex align-items-center" href="<?php echo $base; ?>admin/dashboard.php" style="border-radius: 20px; font-size: 0.85rem; border: 1.5px solid var(--warning-color) !important; color: var(--warning-color) !important; background: transparent;">
                                    <i class="bi bi-shield-lock me-1"></i>Admin Panel
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <!-- Theme Toggle Switch -->
                    <button class="theme-toggle-btn me-3" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Toggle Theme">
                        <i class="bi bi-moon-stars" id="themeToggleIcon"></i>
                    </button>

                    <?php if (isLoggedIn()): ?>
                        <div class="dropdown">
                            <a class="d-flex align-items-center text-decoration-none dropdown-toggle text-light" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (!empty($_SESSION['user_image'])): ?>
                                    <img src="<?php echo $base . sanitize($_SESSION['user_image']); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2 text-white" style="width: 32px; height: 32px; font-weight: 600;">
                                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span><?php echo sanitize($_SESSION['user_name']); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark bg-card border-border shadow" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?php echo $base; ?>user/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item <?php echo ($current_page == 'dashboard.php' && $is_in_user_dir) ? 'active' : ''; ?>" href="<?php echo $base; ?>user/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item <?php echo ($current_page == 'listings.php' && $is_in_user_dir) ? 'active' : ''; ?>" href="<?php echo $base; ?>user/listings.php"><i class="bi bi-book-half me-2"></i>My Listings</a></li>
                                <li><a class="dropdown-item <?php echo ($current_page == 'transactions.php' && $is_in_user_dir) ? 'active' : ''; ?>" href="<?php echo $base; ?>user/transactions.php"><i class="bi bi-arrow-left-right me-2"></i>My Trades</a></li>
                                <li><a class="dropdown-item <?php echo ($current_page == 'messages.php' && $is_in_user_dir) ? 'active' : ''; ?>" href="<?php echo $base; ?>user/messages.php"><i class="bi bi-chat-text me-2"></i>Inbox</a></li>
                                <?php if (isAdmin()): ?>
                                    <li><hr class="dropdown-divider border-border"></li>
                                    <li><a class="dropdown-item text-warning <?php echo ($is_in_admin_dir) ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/dashboard.php"><i class="bi bi-shield-lock me-2"></i>Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider border-border"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo $base; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo $base; ?>login.php" class="btn btn-view-details me-2">Login</a>
                        <a href="<?php echo $base; ?>register.php" class="btn btn-primary-gradient">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <div class="container my-4 flex-grow-1 fade-in">
        <?php displayAlert(); ?>

        <?php if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false): ?>
            <!-- Admin Portal Sub-Navbar -->
            <div class="admin-sub-nav-wrapper mb-4 p-2 rounded-4 shadow-sm border border-border" style="background-color: var(--bg-card);">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <span class="badge bg-warning text-dark fw-extrabold px-3 py-2 rounded-pill"><i class="bi bi-shield-fill-check me-1"></i>ADMIN PORTAL</span>
                        <div class="nav nav-pills gap-1">
                            <a class="nav-link py-1 px-3 d-flex align-items-center <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                            <a class="nav-link py-1 px-3 d-flex align-items-center <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/users.php">
                                <i class="bi bi-people me-1"></i>Users
                            </a>
                            <a class="nav-link py-1 px-3 d-flex align-items-center <?php echo (basename($_SERVER['PHP_SELF']) == 'listings.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/listings.php">
                                <i class="bi bi-book-half me-1"></i>Listings
                            </a>
                            <a class="nav-link py-1 px-3 d-flex align-items-center <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>" href="<?php echo $base; ?>admin/reports.php">
                                <i class="bi bi-flag me-1"></i>Reports
                            </a>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo $base; ?>index.php" class="btn btn-view-details py-1 px-3">
                            <i class="bi bi-arrow-left me-1"></i>Back to Shop
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
