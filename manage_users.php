<?php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    
    switch ($_GET['action']) {
        case 'delete':
            // Check if user is trying to delete themselves
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['error'] = "You cannot delete your own account!";
                break;
            }
            
            // Get user info for notification
            $stmt = $mysqli->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if ($user) {
                // Delete user's documents first
                $doc_stmt = $mysqli->prepare("SELECT file_path FROM documents WHERE uploaded_by = ?");
                $doc_stmt->bind_param("i", $user_id);
                $doc_stmt->execute();
                $user_docs = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                foreach ($user_docs as $doc) {
                    $file_path = '../assets/uploads/documents/' . $doc['file_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                // Delete user's profile picture if not default
                $profile_stmt = $mysqli->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $profile_stmt->bind_param("i", $user_id);
                $profile_stmt->execute();
                $profile = $profile_stmt->get_result()->fetch_assoc();
                
                if ($profile['profile_picture'] !== 'default.png') {
                    $profile_path = '../assets/uploads/profiles/' . $profile['profile_picture'];
                    if (file_exists($profile_path)) {
                        unlink($profile_path);
                    }
                }
                
                // Delete user from database
                $del_stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
                $del_stmt->bind_param("i", $user_id);
                $del_stmt->execute();
                
                // Also delete related data
                $dis_stmt = $mysqli->prepare("DELETE FROM discussions WHERE user_id = ?");
                $dis_stmt->bind_param("i", $user_id);
                $dis_stmt->execute();
                
                $dl_stmt = $mysqli->prepare("DELETE FROM downloads_log WHERE user_id = ?");
                $dl_stmt->bind_param("i", $user_id);
                $dl_stmt->execute();
                
                $_SESSION['success'] = "User '{$user['username']}' deleted successfully!";
            }
            break;
            
        case 'toggle_role':
            // Get current role
            $stmt = $mysqli->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if ($user) {
                $new_role = $user['role'] === 'admin' ? 'user' : 'admin';
                $upd_stmt = $mysqli->prepare("UPDATE users SET role = ? WHERE id = ?");
                $upd_stmt->bind_param("si", $new_role, $user_id);
                $upd_stmt->execute();
                
                // Create notification
                $notif_stmt = $mysqli->prepare("INSERT INTO notifications (type, message, related_id) VALUES ('info', ?, ?)");
                $notif_msg = "User role changed to {$new_role}";
                $notif_stmt->bind_param("si", $notif_msg, $user_id);
                $notif_stmt->execute();
                    
                $_SESSION['success'] = "User role updated to {$new_role}!";
            }
            break;
            
            // Add ban functionality - you can create a 'status' column in users table
            $ban_stmt = $mysqli->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
            $ban_stmt->bind_param("i", $user_id);
            $ban_stmt->execute();
            $_SESSION['success'] = "User banned successfully!";
            break;
            
        case 'unban':
            $unban_stmt = $mysqli->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $unban_stmt->bind_param("i", $user_id);
            $unban_stmt->execute();
            $_SESSION['success'] = "User unbanned successfully!";
            break;
    }
    
    redirect('manage_users.php');
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_users'])) {
    $selected_users = $_POST['selected_users'];
    
    switch ($_POST['bulk_action']) {
        case 'delete':
            foreach ($selected_users as $user_id) {
                $user_id = intval($user_id);
                // Don't allow deleting own account via bulk action
                if ($user_id != $_SESSION['user_id']) {
                    $del_stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
                    $del_stmt->bind_param("i", $user_id);
                    $del_stmt->execute();
                }
            }
            $_SESSION['success'] = "Selected users deleted successfully!";
            break;
            
        case 'make_admin':
            foreach ($selected_users as $user_id) {
                $upd_stmt = $mysqli->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                $user_id_int = intval($user_id);
                $upd_stmt->bind_param("i", $user_id_int);
                $upd_stmt->execute();
            }
            $_SESSION['success'] = "Selected users promoted to admin!";
            break;
            
        case 'make_user':
            foreach ($selected_users as $user_id) {
                // Don't allow demoting yourself
                if ($user_id != $_SESSION['user_id']) {
                    $upd_stmt = $mysqli->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                    $user_id_int = intval($user_id);
                    $upd_stmt->bind_param("i", $user_id_int);
                    $upd_stmt->execute();
                }
            }
            $_SESSION['success'] = "Selected users demoted to regular user!";
            break;
    }
    
    redirect('manage_users.php');
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter) && $role_filter !== 'all') {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

// Add sorting
$valid_sorts = ['username', 'email', 'role', 'created_at', 'last_login'];
$valid_orders = ['ASC', 'DESC'];
$sort = in_array($sort, $valid_sorts) ? $sort : 'created_at';
$order = in_array(strtoupper($order), $valid_orders) ? strtoupper($order) : 'DESC';

$query .= " ORDER BY $sort $order";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as count FROM (" . str_replace('SELECT *', 'SELECT id', $query) . ") as count_table";
$count_stmt = $mysqli->prepare($count_query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['count'];

// Pagination
$per_page = 20;
$total_pages = ceil($total_users / $per_page);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$query .= " LIMIT $per_page OFFSET $offset";

// Fetch users
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user stats
$total_admins = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$total_active = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];
$new_today = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--light-blue);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--button-gray);
        }
        
        .admin-table th {
            background-color: var(--dark-blue);
            color: var(--accent);
            font-weight: bold;
        }
        
        .admin-table tr:hover {
            background-color: rgba(100, 255, 218, 0.1);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: bold;
        }
        
        .role-admin {
            background-color: #10b981;
            color: white;
        }
        
        .role-user {
            background-color: #3b82f6;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
            transition: opacity 0.3s;
        }
        
        .action-btn:hover {
            opacity: 0.8;
        }
        
        .btn-danger {
            background-color: #dc2626;
            color: white;
        }
        
        .btn-warning {
            background-color: #f59e0b;
            color: white;
        }
        
        .btn-info {
            background-color: #06b6d4;
            color: white;
        }
        
        .btn-success {
            background-color: #10b981;
            color: white;
        }
        
        .filters-bar {
            background-color: var(--light-blue);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .bulk-actions {
            background-color: var(--dark-blue);
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .page-link {
            padding: 0.5rem 1rem;
            background-color: var(--light-blue);
            color: var(--white);
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .page-link:hover,
        .page-link.active {
            background-color: var(--accent);
            color: var(--dark-blue);
        }
        
        .status-active {
            color: #10b981;
        }
        
        .status-inactive {
            color: #ef4444;
        }
        
        .view-user-btn {
            background-color: var(--button-gray);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .select-all-checkbox {
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .admin-table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-content">
            <h1><i class="fas fa-users"></i> Manage Users</h1>
            
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <p><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- User Statistics -->
            <div class="user-stats">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-shield"></i>
                    <h3><?php echo $total_admins; ?></h3>
                    <p>Administrators</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-check"></i>
                    <h3><?php echo $total_active; ?></h3>
                    <p>Active Users (30 days)</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-plus"></i>
                    <h3><?php echo $new_today; ?></h3>
                    <p>New Today</p>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="filters-bar">
                <form method="GET" action="" class="filter-form">
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1;">
                            <input type="text" name="search" placeholder="Search users..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="width: 100%;">
                        </div>
                        <div>
                            <select name="role" style="padding: 0.8rem;">
                                <option value="all">All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                            </select>
                        </div>
                        <div>
                            <select name="sort" style="padding: 0.8rem;">
                                <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Sort by Date</option>
                                <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Sort by Username</option>
                                <option value="last_login" <?php echo $sort === 'last_login' ? 'selected' : ''; ?>>Sort by Last Login</option>
                            </select>
                        </div>
                        <div>
                            <select name="order" style="padding: 0.8rem;">
                                <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="manage_users.php" class="btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <form method="POST" action="" id="bulkActionForm">
                <div class="bulk-actions">
                    <input type="checkbox" id="selectAll" class="select-all-checkbox">
                    <select name="bulk_action" style="padding: 0.5rem;" required>
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete Selected</option>
                        <option value="make_admin">Make Admin</option>
                        <option value="make_user">Make Regular User</option>
                    </select>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-play"></i> Apply
                    </button>
                    <span id="selectedCount">0 users selected</span>
                </div>
                
                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th width="50">Select</th>
                                <th width="60">Avatar</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($users)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 2rem;">
                                        No users found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_users[]" 
                                               value="<?php echo $user['id']; ?>"
                                               class="user-checkbox"
                                               <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    </td>
                                    <td>
                                        <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                             alt="Avatar" class="user-avatar">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="role-badge" style="background-color: #8b5cf6;">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = 'Active';
                                        if (!empty($user['last_login'])) {
                                            $last_login = strtotime($user['last_login']);
                                            $thirty_days_ago = strtotime('-30 days');
                                            $status = $last_login >= $thirty_days_ago ? 'Active' : 'Inactive';
                                        }
                                        ?>
                                        <span class="status-<?php echo strtolower($status); ?>">
                                            <i class="fas fa-circle"></i> <?php echo $status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_user.php?id=<?php echo $user['id']; ?>" 
                                               class="view-user-btn">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" 
                                                        onclick="toggleRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')"
                                                        class="action-btn btn-warning">
                                                    <i class="fas fa-user-tag"></i>
                                                    <?php echo $user['role'] === 'admin' ? 'Demote' : 'Promote'; ?>
                                                </button>
                                                
                                                <button type="button" 
                                                        onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                        class="action-btn btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="role-badge" style="background-color: #6b7280;">Current</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>"
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
    // Bulk selection
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.user-checkbox:not(:disabled)');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Update selected count
    function updateSelectedCount() {
        const selected = document.querySelectorAll('.user-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = selected + ' users selected';
    }
    
    // Add event listeners to checkboxes
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // User actions
    function deleteUser(userId, username) {
        if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone!`)) {
            window.location.href = `manage_users.php?action=delete&id=${userId}`;
        }
    }
    
    function toggleRole(userId, currentRole) {
        const newRole = currentRole === 'admin' ? 'user' : 'admin';
        const action = currentRole === 'admin' ? 'demote' : 'promote';
        
        if (confirm(`Are you sure you want to ${action} this user to ${newRole}?`)) {
            window.location.href = `manage_users.php?action=toggle_role&id=${userId}`;
        }
    }
    
    // Bulk form submission confirmation
    document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
        const action = this.bulk_action.value;
        const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
        
        if (!action) {
            e.preventDefault();
            alert('Please select a bulk action');
            return;
        }
        
        if (selectedCount === 0) {
            e.preventDefault();
            alert('Please select at least one user');
            return;
        }
        
        let message = `Are you sure you want to ${action.replace('_', ' ')} ${selectedCount} user(s)?`;
        if (action === 'delete') {
            message += '\nThis action cannot be undone!';
        }
        
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
    
    // Auto-refresh every 5 minutes to see new users
    setTimeout(() => {
        if (!document.hidden) {
            window.location.reload();
        }
    }, 300000); // 5 minutes
    </script>
</body>
</html>