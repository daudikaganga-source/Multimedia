<?php
require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Learn - E-Learning Platform</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">Share Learn</a>
            <div class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="library.php"><i class="fas fa-book"></i> Library</a>
                <a href="discussions.php"><i class="fas fa-comments"></i> Discussions</a>
                
                <?php if(is_logged_in()): ?>
                    <a href="upload.php"><i class="fas fa-upload"></i> Upload</a>
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <?php if(is_admin()): ?>
                        <a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <a href="login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="register.php" class="register-btn"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to Share Learn</h1>
            <p>Share knowledge, learn together, grow faster. Access thousands of educational resources.</p>
            <div class="hero-buttons">
                <a href="library.php" class="btn-primary">
                    <i class="fas fa-search"></i> Browse Notes
                </a>
                <?php if(!is_logged_in()): ?>
                    <a href="register.php" class="btn-secondary">
                        <i class="fas fa-user-plus"></i> Join Now
                    </a>
                <?php else: ?>
                    <a href="upload.php" class="btn-secondary">
                        <i class="fas fa-upload"></i> Share Notes
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <h2 class="section-title">Why Choose Share Learn?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-book-open"></i>
                    <h3>Extensive Library</h3>
                    <p>Access thousands of notes, books, and resources across various subjects.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-users"></i>
                    <h3>Community Learning</h3>
                    <p>Join discussions, ask questions, and learn together with peers.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Mobile Friendly</h3>
                    <p>Access resources from any device, anywhere, anytime.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- WhatsApp Float Button -->
    <a href="https://wa.me/255758851705" class="whatsapp-float" target="_blank">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Share Learn</h3>
                <p>Empowering learners through shared knowledge and community collaboration.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-telegram"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Contact Info</h3>
                <p><i class="fas fa-map-marker-alt"></i> Bibi Titi Mohamed, Dar-es-Salaam</p>
                <p><i class="fas fa-phone"></i> +255 758 851 705</p>
                <p><i class="fas fa-envelope"></i> info@sharelearn.com</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="library.php">Library</a>
                <a href="discussions.php">Discussions</a>
                <a href="upload.php">Upload Notes</a>
                <a href="login.php">Login</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Share Learn. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>