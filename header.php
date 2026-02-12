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
