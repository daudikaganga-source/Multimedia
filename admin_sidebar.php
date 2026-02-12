<?php
// admin/includes/admin_sidebar.php
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-cogs"></i> Admin Panel</h2>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
    </div>
    
    <nav class="admin-nav">
        <a href="../admin/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="../admin/manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage_users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Manage Users
        </a>
        <a href="../admin/manage_documents.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage_documents.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Manage Documents
        </a>
        <a href="../admin/view_discussions.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'view_discussions.php' ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i> View Discussions
        </a>
        <a href="../admin/notifications.php" class="notification-bell <?php echo basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i> Notifications
            <?php 
            $stmt = $mysqli->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
            $stmt->execute();
            $stmt->bind_result($unread_count);
            $stmt->fetch();
            $stmt->close();
            if ($unread_count > 0): ?>
                <span class="notification-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="../admin/system_logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'system_logs.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> System Logs
        </a>
        <a href="../admin/settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-sliders-h"></i> Settings
        </a>
        
        <div class="sidebar-divider"></div>
        
        <a href="../index.php" class="back-to-site">
            <i class="fas fa-home"></i> Back to Site
        </a>
        <a href="../profile.php" class="profile-link">
            <i class="fas fa-user-circle"></i> My Profile
        </a>
        <a href="../logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <p><i class="fas fa-clock"></i> Last login: 
            <?php 
            $stmt = $mysqli->prepare("SELECT last_login FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->bind_result($last_login);
            $stmt->fetch();
            $stmt->close();
            echo $last_login ? date('M d, H:i', strtotime($last_login)) : 'Never';
            ?>
        </p>
    </div>
</aside>