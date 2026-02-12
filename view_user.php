<?php
// admin/view_user.php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

if (!isset($_GET['id'])) {
    redirect('manage_users.php');
}

$user_id = intval($_GET['id']);

// Get user details
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = "User not found!";
    redirect('manage_users.php');
}

// Get user activity
$u_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM documents WHERE uploaded_by = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$uploads_count = $u_stmt->get_result()->fetch_assoc()['count'];

$dl_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM downloads_log WHERE user_id = ?");
$dl_stmt->bind_param("i", $user_id);
$dl_stmt->execute();
$downloads_count = $dl_stmt->get_result()->fetch_assoc()['count'];

$dis_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM discussions WHERE user_id = ?");
$dis_stmt->bind_param("i", $user_id);
$dis_stmt->execute();
$discussions_count = $dis_stmt->get_result()->fetch_assoc()['count'];

// Get recent activity
$ru_stmt = $mysqli->prepare("SELECT * FROM documents WHERE uploaded_by = ? ORDER BY upload_date DESC LIMIT 5");
$ru_stmt->bind_param("i", $user_id);
$ru_stmt->execute();
$recent_uploads = $ru_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$rd_stmt = $mysqli->prepare("SELECT dl.*, d.title FROM downloads_log dl 
                                   JOIN documents d ON dl.document_id = d.id 
                                   WHERE dl.user_id = ? ORDER BY downloaded_at DESC LIMIT 10");
$rd_stmt->bind_param("i", $user_id);
$rd_stmt->execute();
$recent_downloads = $rd_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-detail-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 2rem;
            background-color: var(--light-blue);
            border-radius: 10px;
        }
        
        .user-avatar-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--accent);
        }
        
        .user-detail-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .user-activity-section {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            border-bottom: 1px solid var(--button-gray);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .user-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--button-gray);
        }
        
        .user-tab {
            padding: 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .user-tab.active {
            border-bottom-color: var(--accent);
            color: var(--accent);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--accent);
            text-decoration: none;
        }
        
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background-color: var(--light-blue);
            padding: 1rem;
            border-radius: 10px;
        }
        
        .info-card h4 {
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-content">
            <a href="manage_users.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            
            <div class="user-detail-header">
                <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                     alt="Profile" class="user-avatar-lg">
                <div>
                    <h1><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h1>
                    <p>
                        <span class="role-badge role-<?php echo $user['role']; ?>" style="font-size: 1rem;">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        <?php if($user['id'] == $_SESSION['user_id']): ?>
                            <span class="role-badge" style="background-color: #8b5cf6; font-size: 1rem;">Current User</span>
                        <?php endif; ?>
                    </p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-calendar"></i> Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- User Stats -->
            <div class="user-detail-stats">
                <div class="stat-card">
                    <i class="fas fa-upload"></i>
                    <h3><?php echo $uploads_count; ?></h3>
                    <p>Documents Uploaded</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-download"></i>
                    <h3><?php echo $downloads_count; ?></h3>
                    <p>Documents Downloaded</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-comment"></i>
                    <h3><?php echo $discussions_count; ?></h3>
                    <p>Discussion Posts</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-sign-in-alt"></i>
                    <h3><?php echo $user['last_login'] ? date('M d', strtotime($user['last_login'])) : 'Never'; ?></h3>
                    <p>Last Login</p>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="margin: 2rem 0;">
                <div class="action-buttons" style="justify-content: flex-start;">
                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                        <button onclick="toggleRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')" 
                                class="action-btn btn-warning">
                            <i class="fas fa-user-tag"></i>
                            <?php echo $user['role'] === 'admin' ? 'Demote to User' : 'Promote to Admin'; ?>
                        </button>
                        
                        <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                class="action-btn btn-danger">
                            <i class="fas fa-trash"></i> Delete User
                        </button>
                    <?php endif; ?>
                    
                    <a href="../profile.php?user=<?php echo $user['id']; ?>" class="action-btn btn-info">
                        <i class="fas fa-external-link-alt"></i> View Public Profile
                    </a>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="user-tabs">
                <div class="user-tab active" onclick="switchTab('info')">User Information</div>
                <div class="user-tab" onclick="switchTab('uploads')">Recent Uploads</div>
                <div class="user-tab" onclick="switchTab('downloads')">Download History</div>
                <div class="user-tab" onclick="switchTab('activity')">Activity Log</div>
            </div>
            
            <!-- Tab Contents -->
            <div id="infoTab" class="tab-content active">
                <div class="user-info-grid">
                    <div class="info-card">
                        <h4><i class="fas fa-user"></i> Account Information</h4>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['full_name'] ?? 'Not set'); ?></p>
                        <p><strong>Bio:</strong> <?php echo htmlspecialchars($user['bio'] ?? 'Not set'); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4><i class="fas fa-history"></i> Activity Information</h4>
                        <p><strong>Account Created:</strong> <?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></p>
                        <p><strong>Last Login:</strong> <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></p>
                        <p><strong>Last Logout:</strong> <?php echo $user['last_logout'] ? date('M d, Y H:i', strtotime($user['last_logout'])) : 'Not recorded'; ?></p>
                        <p><strong>Status:</strong> 
                            <?php 
                            $status = 'Active';
                            if (!empty($user['last_login'])) {
                                $last_login = strtotime($user['last_login']);
                                $thirty_days_ago = strtotime('-30 days');
                                $status = $last_login >= $thirty_days_ago ? 'Active' : 'Inactive';
                            }
                            ?>
                            <span class="status-<?php echo strtolower($status); ?>">
                                <?php echo $status; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div id="uploadsTab" class="tab-content">
                <div class="user-activity-section">
                    <h3><i class="fas fa-upload"></i> Recent Uploads (Last 5)</h3>
                    <?php if(empty($recent_uploads)): ?>
                        <p>No uploads found.</p>
                    <?php else: ?>
                        <?php foreach($recent_uploads as $upload): ?>
                        <div class="activity-item">
                            <div>
                                <strong><?php echo htmlspecialchars($upload['title']); ?></strong>
                                <p style="color: var(--gray); margin-top: 0.25rem;">
                                    <?php echo date('M d, Y H:i', strtotime($upload['upload_date'])); ?>
                                </p>
                            </div>
                            <div>
                                <span style="color: var(--accent);">
                                    <?php echo $upload['download_count']; ?> downloads
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <a href="manage_documents.php?user=<?php echo $user_id; ?>" class="btn-primary" style="margin-top: 1rem;">
                            View All Uploads
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div id="downloadsTab" class="tab-content">
                <div class="user-activity-section">
                    <h3><i class="fas fa-download"></i> Recent Downloads (Last 10)</h3>
                    <?php if(empty($recent_downloads)): ?>
                        <p>No downloads found.</p>
                    <?php else: ?>
                        <?php foreach($recent_downloads as $download): ?>
                        <div class="activity-item">
                            <div>
                                <strong><?php echo htmlspecialchars($download['title']); ?></strong>
                                <p style="color: var(--gray); margin-top: 0.25rem;">
                                    <?php echo date('M d, Y H:i', strtotime($download['downloaded_at'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div id="activityTab" class="tab-content">
                <div class="user-activity-section">
                    <h3><i class="fas fa-chart-line"></i> User Activity Summary</h3>
                    <div style="margin-top: 1rem;">
                        <p><strong>Total Activity Score:</strong> 
                            <?php echo ($uploads_count * 3) + ($downloads_count * 1) + ($discussions_count * 2); ?> points
                        </p>
                        <p><strong>Average Daily Activity:</strong> 
                            <?php 
                            $days_since_join = max(1, floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24)));
                            $avg_activity = ($uploads_count + $downloads_count + $discussions_count) / $days_since_join;
                            echo round($avg_activity, 2); ?> activities/day
                        </p>
                        <p><strong>Most Active Time:</strong> 
                            <?php
                            // You could implement more detailed activity tracking here
                            echo "All time"; 
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all tab buttons
        document.querySelectorAll('.user-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName + 'Tab').classList.add('active');
        
        // Activate clicked tab button
        event.target.classList.add('active');
    }
    
    function toggleRole(userId, currentRole) {
        const newRole = currentRole === 'admin' ? 'user' : 'admin';
        const action = currentRole === 'admin' ? 'demote' : 'promote';
        
        if (confirm(`Are you sure you want to ${action} this user to ${newRole}?`)) {
            window.location.href = `manage_users.php?action=toggle_role&id=${userId}`;
        }
    }
    
    function deleteUser(userId, username) {
        if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone!`)) {
            window.location.href = `manage_users.php?action=delete&id=${userId}`;
        }
    }
    </script>
</body>
</html>