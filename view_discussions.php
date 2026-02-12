<?php
// admin/view_discussions.php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Handle discussion actions
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $discussion_id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("DELETE FROM discussions WHERE id = ?");
    $stmt->bind_param("i", $discussion_id);
    $stmt->execute();
    $_SESSION['success'] = "Discussion deleted successfully!";
    redirect('view_discussions.php');
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_discussions'])) {
    $selected_discussions = $_POST['selected_discussions'];
    
    if ($_POST['bulk_action'] === 'delete') {
        $placeholders = rtrim(str_repeat('?,', count($selected_discussions)), ',');
        $stmt = $mysqli->prepare("DELETE FROM discussions WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($selected_discussions));
        $stmt->bind_param($types, ...$selected_discussions);
        $stmt->execute();
        $_SESSION['success'] = "Selected discussions deleted successfully!";
    }
    
    redirect('view_discussions.php');
}

// Search and filter
$search = $_GET['search'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Build query
$query = "SELECT d.*, u.username, u.profile_picture 
          FROM discussions d 
          JOIN users u ON d.user_id = u.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (d.topic LIKE ? OR d.message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($user_filter) && is_numeric($user_filter)) {
    $query .= " AND d.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(d.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(d.created_at) <= ?";
    $params[] = $date_to;
}

// Add sorting
$valid_sorts = ['created_at', 'topic', 'username'];
$valid_orders = ['ASC', 'DESC'];
$sort = in_array($sort, $valid_sorts) ? $sort : 'created_at';
$order = in_array(strtoupper($order), $valid_orders) ? strtoupper($order) : 'DESC';

$query .= " ORDER BY $sort $order";

// Get total count
$count_query = str_replace('SELECT d.*, u.username, u.profile_picture', 'SELECT COUNT(*) as count', $query);
$stmt = $mysqli->prepare('select COUNT(*) FROM discussions WHERE id=?');
$stmt->bind_param('s', $search);
$stmt->execute();
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_column();
$total_discussions = $result->fetch_assoc()[0];

// Pagination
$per_page = 20;
$page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_discussions / $per_page);

$query .= " LIMIT $per_page OFFSET $offset";

// Fetch discussions
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$discussions = $result->fetch_all(MYSQLI_ASSOC);

// Get all users for filter dropdown
$users = $mysqli->query("SELECT id, username FROM users ORDER BY username")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_discussions_count = $mysqli->query("SELECT COUNT(*) as count FROM discussions")->fetch_assoc()['count'];
$today_discussions = $mysqli->query("SELECT COUNT(*) as count FROM discussions WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$top_users = $mysqli->query("SELECT u.username, COUNT(d.id) as count 
                          FROM discussions d 
                          JOIN users u ON d.user_id = u.id 
                          GROUP BY d.user_id 
                          ORDER BY count DESC 
                          LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Discussions - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .discussions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--button-gray);
        }
        
        .discussions-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card i {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        
        .filters-bar {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--light-gray);
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--button-gray);
            border-radius: 5px;
            background-color: var(--dark-blue);
            color: var(--white);
        }
        
        .bulk-actions {
            background-color: var(--dark-blue);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .discussion-card {
            background-color: var(--light-blue);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.3s;
        }
        
        .discussion-card:hover {
            transform: translateY(-3px);
        }
        
        .discussion-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .discussion-topic {
            color: var(--accent);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .discussion-meta {
            color: var(--gray);
            font-size: 0.875rem;
            display: flex;
            gap: 1rem;
        }
        
        .discussion-message {
            margin: 1rem 0;
            padding: 1rem;
            background-color: var(--dark-blue);
            border-radius: 5px;
            line-height: 1.6;
        }
        
        .discussion-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--button-gray);
        }
        
        .view-btn {
            background-color: var(--button-gray);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .delete-btn {
            background-color: #dc2626;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
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
        
        .top-users {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .top-users h3 {
            color: var(--accent);
            margin-bottom: 1rem;
        }
        
        .user-rank {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem;
            border-bottom: 1px solid var(--button-gray);
        }
        
        .user-rank:last-child {
            border-bottom: none;
        }
        
        .rank-number {
            display: inline-block;
            width: 24px;
            height: 24px;
            background-color: var(--accent);
            color: var(--dark-blue);
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        
        .discussion-checkbox {
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .discussion-header {
                flex-direction: column;
                text-align: center;
            }
            
            .discussion-meta {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>  
        <main class="admin-content">
            <div class="discussions-header">
                <h1><i class="fas fa-comments"></i> View Discussions</h1>
                <div class="header-actions">
                    <a href="../discussions.php" class="btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Live Discussions
                    </a>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="discussions-stats">
                <div class="stat-card">
                    <i class="fas fa-comment-dots"></i>
                    <h3><?php echo $total_discussions_count; ?></h3>
                    <p>Total Discussions</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-day"></i>
                    <h3><?php echo $today_discussions; ?></h3>
                    <p>Discussions Today</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo count($users); ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            
            <!-- Top Users -->
            <div class="top-users">
                <h3><i class="fas fa-trophy"></i> Top Discussion Contributors</h3>
                <?php foreach($top_users as $index => $user): ?>
                <div class="user-rank">
                    <div>
                        <span class="rank-number"><?php echo $index + 1; ?></span>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <span><?php echo $user['count']; ?> posts</span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Filters -->
            <form method="GET" action="" class="filters-bar">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="search"><i class="fas fa-search"></i> Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search discussions...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="user"><i class="fas fa-user"></i> User</label>
                        <select id="user" name="user">
                            <option value="">All Users</option>
                            <?php foreach($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from"><i class="fas fa-calendar"></i> Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to"><i class="fas fa-calendar"></i> Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort"><i class="fas fa-sort"></i> Sort By</label>
                        <select id="sort" name="sort">
                            <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date</option>
                            <option value="topic" <?php echo $sort === 'topic' ? 'selected' : ''; ?>>Topic</option>
                            <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>User</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="order"><i class="fas fa-sort-amount-down"></i> Order</label>
                        <select id="order" name="order">
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="view_discussions.php" class="btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                </div>
            </form>
            
            <!-- Bulk Actions -->
            <?php if($total_discussions > 0): ?>
            <form method="POST" action="" class="bulk-actions">
                <select name="bulk_action" required style="padding: 0.5rem;">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button type="submit" class="btn-primary" onclick="return confirm('Apply bulk action to selected discussions?')">
                    <i class="fas fa-play"></i> Apply
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Discussions List -->
            <?php if(empty($discussions)): ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3>No Discussions Found</h3>
                    <p>No discussions match your current filters.</p>
                </div>
            <?php else: ?>
                <?php foreach($discussions as $discussion): ?>
                <div class="discussion-card">
                    <div class="discussion-header">
                        <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($discussion['profile_picture']); ?>" 
                             alt="Profile" class="user-avatar">
                        <div style="flex: 1;">
                            <div class="discussion-topic">
                                <?php echo htmlspecialchars($discussion['topic']); ?>
                            </div>
                            <div class="discussion-meta">
                                <span>
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($discussion['username']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('M d, Y H:i', strtotime($discussion['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <input type="checkbox" name="selected_discussions[]" 
                               value="<?php echo $discussion['id']; ?>"
                               class="discussion-checkbox">
                    </div>
                    
                    <div class="discussion-message">
                        <?php echo nl2br(htmlspecialchars($discussion['message'])); ?>
                    </div>
                    
                    <div class="discussion-actions">
                        <a href="../discussions.php#discussion-<?php echo $discussion['id']; ?>" 
                           class="view-btn" target="_blank">
                            <i class="fas fa-external-link-alt"></i> View in Forum
                        </a>
                        <button type="button" class="delete-btn" 
                                onclick="deleteDiscussion(<?php echo $discussion['id']; ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>"
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
    function deleteDiscussion(discussionId) {
        if (confirm('Delete this discussion? This action cannot be undone!')) {
            window.location.href = 'view_discussions.php?delete=' + discussionId + '&<?php echo http_build_query($_GET); ?>';
        }
    }
    
    // Select all checkboxes
    function selectAllDiscussions(checked) {
        const checkboxes = document.querySelectorAll('.discussion-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
        });
    }
    
    // Add select all functionality
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'btn-secondary';
        selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
        selectAllBtn.onclick = () => selectAllDiscussions(true);
        
        const selectNoneBtn = document.createElement('button');
        selectNoneBtn.type = 'button';
        selectNoneBtn.className = 'btn-secondary';
        selectNoneBtn.innerHTML = '<i class="fas fa-square"></i> Select None';
        selectNoneBtn.onclick = () => selectAllDiscussions(false);
        
        const bulkActions = document.querySelector('.bulk-actions');
        if (bulkActions) {
            bulkActions.insertBefore(selectNoneBtn, bulkActions.firstChild);
            bulkActions.insertBefore(selectAllBtn, bulkActions.firstChild);
        }
    });
    </script>
</body>
</html>