<?php
// admin/notifications.php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param('i', $notification_id);
    $stmt->execute();
    $_SESSION['success'] = "Notification marked as read!";
    redirect('notifications.php');
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $mysqli->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    $_SESSION['success'] = "All notifications marked as read!";
    redirect('notifications.php');
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param('i', $notification_id);
    $stmt->execute();
    $_SESSION['success'] = "Notification deleted!";
    redirect('notifications.php');
}

// Clear all notifications
if (isset($_GET['clear_all'])) {
    $mysqli->query("DELETE FROM notifications");
    $_SESSION['success'] = "All notifications cleared!";
    redirect('notifications.php');
}

// Filter notifications
$filter = $_GET['filter'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

// Build query
$query = "SELECT n.* FROM notifications n WHERE 1=1";
$params = [];

if ($filter === 'unread') {
    $query .= " AND n.is_read = 0";
} elseif ($filter === 'read') {
    $query .= " AND n.is_read = 1";
}

if ($type_filter !== 'all') {
    $query .= " AND n.type = ?";
    $params[] = $type_filter;
}

$query .= " ORDER BY n.created_at DESC";

// Get notifications count for filter tabs
$all_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
$unread_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'];
$read_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 1")->fetch_assoc()['count'];

// Get type counts
$new_user_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE type = 'new_user'")->fetch_assoc()['count'];
$upload_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE type = 'upload'")->fetch_assoc()['count'];
$download_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE type = 'download'")->fetch_assoc()['count'];

// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$count_query = str_replace('SELECT n.*', 'SELECT COUNT(*) as count', $query);
$stmt = $mysqli->prepare($count_query);
if (count($params) > 0) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$total_notifications = $result->fetch_assoc()['count'];
$total_pages = ceil($total_notifications / $per_page);

$query .= " LIMIT $per_page OFFSET $offset";

// Fetch notifications
$stmt = $mysqli->prepare($query);
if (count($params) > 0) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

// Get related data for notifications
foreach ($notifications as &$notification) {
    switch ($notification['type']) {
        case 'new_user':
            $user_stmt = $mysqli->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
            $user_stmt->bind_param('i', $notification['related_id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $notification['related_data'] = $user_result->fetch_assoc();
            break;
            
        case 'upload':
            $doc_stmt = $mysqli->prepare("SELECT d.title, u.username FROM documents d 
                                      JOIN users u ON d.uploaded_by = u.id 
                                      WHERE d.id = ?");
            $doc_stmt->bind_param('i', $notification['related_id']);
            $doc_stmt->execute();
            $doc_result = $doc_stmt->get_result();
            $notification['related_data'] = $doc_result->fetch_assoc();
            break;
            
        case 'download':
            $dl_stmt = $mysqli->prepare("SELECT d.title, u.username FROM downloads_log dl
                                  JOIN documents d ON dl.document_id = d.id
                                  JOIN users u ON dl.user_id = u.id
                                  WHERE dl.id = ?");
            $dl_stmt->bind_param('i', $notification['related_id']);
            $dl_stmt->execute();
            $dl_result = $dl_stmt->get_result();
            $notification['related_data'] = $dl_result->fetch_assoc();
            break;
    }
}
unset($notification); // Break reference
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--button-gray);
        }
        
        .notification-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1.5rem;
            border: 2px solid var(--button-gray);
            background: none;
            color: var(--white);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background-color: var(--accent);
            border-color: var(--accent);
            color: var(--dark-blue);
        }
        
        .filter-btn .count {
            background-color: var(--light-blue);
            color: var(--white);
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
            margin-left: 0.5rem;
            font-size: 0.8rem;
        }
        
        .filter-btn.active .count {
            background-color: var(--dark-blue);
        }
        
        .notifications-grid {
            display: grid;
            gap: 1rem;
        }
        
        .notification-card {
            background-color: var(--light-blue);
            border-radius: 10px;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            border-left: 4px solid transparent;
            transition: transform 0.3s;
        }
        
        .notification-card:hover {
            transform: translateX(5px);
        }
        
        .notification-card.unread {
            border-left-color: var(--accent);
            background-color: rgba(100, 255, 218, 0.1);
        }
        
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .notification-icon.user {
            background-color: #3b82f6;
        }
        
        .notification-icon.upload {
            background-color: #10b981;
        }
        
        .notification-icon.download {
            background-color: #f59e0b;
        }
        
        .notification-icon.info {
            background-color: #8b5cf6;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-meta {
            color: var(--gray);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .mark-read-btn {
            background-color: var(--button-gray);
            color: var(--white);
        }
        
        .delete-btn {
            background-color: #dc2626;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem;
            background-color: var(--light-blue);
            border-radius: 10px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        .notification-preview {
            background-color: var(--dark-blue);
            padding: 1rem;
            border-radius: 5px;
            margin-top: 0.5rem;
        }
        
        .preview-user {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .preview-user img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
        }
        
        .bulk-actions {
            background-color: var(--dark-blue);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .notification-badge {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            background-color: var(--accent);
            color: var(--dark-blue);
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 0.5rem;
        }
        
        .time-ago {
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .notification-filters {
                justify-content: center;
            }
            
            .notification-card {
                flex-direction: column;
            }
            
            .notification-actions {
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="notification-header">
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                <div class="header-actions">
                    <?php if($unread_count > 0): ?>
                        <a href="?mark_all_read" class="btn-primary" style="margin-right: 0.5rem;">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </a>
                    <?php endif; ?>
                    <?php if($total_notifications > 0): ?>
                        <a href="?clear_all" class="btn-danger" onclick="return confirm('Clear all notifications?')">
                            <i class="fas fa-trash"></i> Clear All
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filter Tabs -->
            <div class="notification-filters">
                <a href="?filter=all&type=<?php echo $type_filter; ?>" 
                   class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All Notifications
                    <span class="count"><?php echo $all_count; ?></span>
                </a>
                <a href="?filter=unread&type=<?php echo $type_filter; ?>" 
                   class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                    Unread
                    <span class="count"><?php echo $unread_count; ?></span>
                </a>
                <a href="?filter=read&type=<?php echo $type_filter; ?>" 
                   class="filter-btn <?php echo $filter === 'read' ? 'active' : ''; ?>">
                    Read
                    <span class="count"><?php echo $read_count; ?></span>
                </a>
            </div>
            
            <!-- Type Filters -->
            <div class="notification-filters">
                <a href="?filter=<?php echo $filter; ?>&type=all" 
                   class="filter-btn <?php echo $type_filter === 'all' ? 'active' : ''; ?>">
                    All Types
                </a>
                <a href="?filter=<?php echo $filter; ?>&type=new_user" 
                   class="filter-btn <?php echo $type_filter === 'new_user' ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus"></i> New Users
                    <span class="count"><?php echo $new_user_count; ?></span>
                </a>
                <a href="?filter=<?php echo $filter; ?>&type=upload" 
                   class="filter-btn <?php echo $type_filter === 'upload' ? 'active' : ''; ?>">
                    <i class="fas fa-upload"></i> Uploads
                    <span class="count"><?php echo $upload_count; ?></span>
                </a>
                <a href="?filter=<?php echo $filter; ?>&type=download" 
                   class="filter-btn <?php echo $type_filter === 'download' ? 'active' : ''; ?>">
                    <i class="fas fa-download"></i> Downloads
                    <span class="count"><?php echo $download_count; ?></span>
                </a>
            </div>
            
            <!-- Bulk Actions Form -->
            <?php if($total_notifications > 0): ?>
            <form method="POST" action="notifications.php" class="bulk-actions">
                <select name="bulk_action" required style="padding: 0.5rem;">
                    <option value="">Bulk Actions</option>
                    <option value="mark_read">Mark as Read</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-play"></i> Apply
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Notifications List -->
            <div class="notifications-grid">
                <?php if(empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You're all caught up! No new notifications.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($notifications as $notification): ?>
                    <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-icon <?php echo $notification['type']; ?>">
                            <?php switch($notification['type']): 
                                case 'new_user': ?>
                                    <i class="fas fa-user-plus"></i>
                                    <?php break; ?>
                                <?php case 'upload': ?>
                                    <i class="fas fa-upload"></i>
                                    <?php break; ?>
                                <?php case 'download': ?>
                                    <i class="fas fa-download"></i>
                                    <?php break; ?>
                                <?php default: ?>
                                    <i class="fas fa-info-circle"></i>
                            <?php endswitch; ?>
                        </div>
                        
                        <div class="notification-content">
                            <h4><?php echo htmlspecialchars($notification['message']); ?></h4>
                            
                            <!-- Preview of related data -->
                            <?php if(isset($notification['related_data'])): ?>
                            <div class="notification-preview">
                                <?php if($notification['type'] === 'new_user'): ?>
                                    <div class="preview-user">
                                        <?php if(isset($notification['related_data']['profile_picture'])): ?>
                                        <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($notification['related_data']['profile_picture']); ?>" 
                                             alt="Profile">
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($notification['related_data']['username']); ?></span>
                                    </div>
                                <?php elseif($notification['type'] === 'upload' || $notification['type'] === 'download'): ?>
                                    <p>
                                        <strong>Document:</strong> <?php echo htmlspecialchars($notification['related_data']['title']); ?><br>
                                        <?php if(isset($notification['related_data']['username'])): ?>
                                        <strong>User:</strong> <?php echo htmlspecialchars($notification['related_data']['username']); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="notification-meta">
                                <span class="time-ago">
                                    <?php echo time_ago($notification['created_at']); ?>
                                </span>
                                <div class="notification-actions">
                                    <?php if(!$notification['is_read']): ?>
                                        <a href="?mark_read=<?php echo $notification['id']; ?>&filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&page=<?php echo $page; ?>" 
                                           class="action-btn mark-read-btn">
                                            Mark Read
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $notification['id']; ?>&filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&page=<?php echo $page; ?>" 
                                       class="action-btn delete-btn"
                                       onclick="return confirm('Delete this notification?')">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>"
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="../assets/js/script.js"></script>
    <script>
    // Auto-refresh notifications every 30 seconds
    setTimeout(() => {
        if (!document.hidden) {
            window.location.reload();
        }
    }, 30000);
    </script>
</body>
</html>

<?php
// Helper function to display time ago
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>