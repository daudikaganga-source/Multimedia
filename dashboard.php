<?php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../ogin.php');
}

// Fetch stats
$users_count = $mysqli->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$documents_count = $mysqli->query("SELECT COUNT(*) as count FROM documents")->fetch_assoc()['count'];
$downloads_count = $mysqli->query("SELECT COUNT(*) as count FROM downloads_log")->fetch_assoc()['count'];
$discussions_count = $mysqli->query("SELECT COUNT(*) as count FROM discussions")->fetch_assoc()['count'];

// Fetch recent activities
$recent_users = $mysqli->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$recent_uploads = $mysqli->query("SELECT d.*, u.username FROM documents d 
                               JOIN users u ON d.uploaded_by = u.id 
                               ORDER BY upload_date DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$recent_downloads = $mysqli->query("SELECT dl.*, u.username, d.title FROM downloads_log dl
                                 JOIN users u ON dl.user_id = u.id
                                 JOIN documents d ON dl.document_id = d.id
                                 ORDER BY downloaded_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Fetch notifications
$notifications = $mysqli->query("SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Share Learn</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <h2>Admin Panel</h2>
            <nav class="admin-nav">
                <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="manage_documents.php"><i class="fas fa-file-alt"></i> Manage Documents</a>
                <a href="view_discussions.php"><i class="fas fa-comments"></i> View Discussions</a>
                <a href="notifications.php" class="notification-bell">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if(count($notifications) > 0): ?>
                        <span class="notification-badge"><?php echo count($notifications); ?></span>
                    <?php endif; ?>
                </a>
                <a href="../index.php"><i class="fas fa-home"></i> Back to Site</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <h1>Admin Dashboard</h1>
            
            <!-- Stats Cards -->
            <div class="admin-stats">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $users_count; ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-file-alt"></i>
                    <h3><?php echo $documents_count; ?></h3>
                    <p>Documents</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-download"></i>
                    <h3><?php echo $downloads_count; ?></h3>
                    <p>Total Downloads</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-comments"></i>
                    <h3><?php echo $discussions_count; ?></h3>
                    <p>Discussions</p>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="admin-sections">
                <section class="admin-section">
                    <h2><i class="fas fa-bell"></i> Recent Notifications</h2>
                    <div class="notifications-list">
                        <?php foreach($notifications as $notification): ?>
                        <div class="notification-item">
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></small>
                            <button onclick="markAsRead(<?php echo $notification['id']; ?>)">Mark Read</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                
                <section class="admin-section">
                    <h2><i class="fas fa-history"></i> Recent Downloads</h2>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Document</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_downloads as $download): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($download['username']); ?></td>
                                <td><?php echo htmlspecialchars($download['title']); ?></td>
                                <td><?php echo date('M d, H:i', strtotime($download['downloaded_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </main>
    </div>
    
    <script>
    function markAsRead(notificationId) {
        fetch(`api/admin.php?action=mark_notification_read&id=${notificationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
    }
    
    // Auto-refresh notifications every 30 seconds
    setInterval(() => {
        fetch(`api/admin.php?action=get_notification_count`)
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
    }, 30000);
    </script>
</body>
</html>