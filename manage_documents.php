<?php
// admin/manage_documents.php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Handle document actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $doc_id = intval($_GET['id']);
    
    switch ($_GET['action']) {
        case 'delete':
            // Get document info
            $stmt = $mysqli->prepare("SELECT file_path FROM documents WHERE id = ?");
            $stmt->bind_param("i", $doc_id);
            $stmt->execute();
            $document = $stmt->get_result()->fetch_assoc();
            
            if ($document) {
                // Delete file
                $file_path = '../assets/uploads/documents/' . $document['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Delete from database
                $del_stmt = $mysqli->prepare("DELETE FROM documents WHERE id = ?");
                $del_stmt->bind_param("i", $doc_id);
                $del_stmt->execute();
                
                // Also delete download logs
                $log_stmt = $mysqli->prepare("DELETE FROM downloads_log WHERE document_id = ?");
                $log_stmt->bind_param("i", $doc_id);
                $log_stmt->execute();
                
                $_SESSION['success'] = "Document deleted successfully!";
            }
            break;
            
        case 'feature':
            $feat_stmt = $mysqli->prepare("UPDATE documents SET featured = 1 WHERE id = ?");
            $feat_stmt->bind_param("i", $doc_id);
            $feat_stmt->execute();
            $_SESSION['success'] = "Document marked as featured!";
            break;
            
        case 'unfeature':
            $unfeat_stmt = $mysqli->prepare("UPDATE documents SET featured = 0 WHERE id = ?");
            $unfeat_stmt->bind_param("i", $doc_id);
            $unfeat_stmt->execute();
            $_SESSION['success'] = "Document unfeatured!";
            break;
    }
    
    redirect('manage_documents.php');
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_documents'])) {
    $selected_documents = $_POST['selected_documents'];
    
    switch ($_POST['bulk_action']) {
        case 'delete':
            foreach ($selected_documents as $doc_id) {
                $doc_id = intval($doc_id);
                
                // Get file path
                $stmt = $mysqli->prepare("SELECT file_path FROM documents WHERE id = ?");
                $stmt->bind_param("i", $doc_id);
                $stmt->execute();
                $document = $stmt->get_result()->fetch_assoc();
                
                if ($document) {
                    // Delete file
                    $file_path = '../assets/uploads/documents/' . $document['file_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                // Delete from database
                $del_stmt = $mysqli->prepare("DELETE FROM documents WHERE id = ?");
                $del_stmt->bind_param("i", $doc_id);
                $del_stmt->execute();
            }
            $_SESSION['success'] = "Selected documents deleted successfully!";
            break;
            
        case 'feature':
            $placeholders = rtrim(str_repeat('?,', count($selected_documents)), ',');
            $upd_stmt = $mysqli->prepare("UPDATE documents SET featured = 1 WHERE id IN ($placeholders)");
            $types = str_repeat('i', count($selected_documents));
            $upd_stmt->bind_param($types, ...$selected_documents);
            $upd_stmt->execute();
            $_SESSION['success'] = "Selected documents featured!";
            break;
            
        case 'unfeature':
            $placeholders = rtrim(str_repeat('?,', count($selected_documents)), ',');
            $upd_stmt = $mysqli->prepare("UPDATE documents SET featured = 0 WHERE id IN ($placeholders)");
            $types = str_repeat('i', count($selected_documents));
            $upd_stmt->bind_param($types, ...$selected_documents);
            $upd_stmt->execute();
            $_SESSION['success'] = "Selected documents unfeatured!";
            break;
    }
    
    redirect('manage_documents.php');
}

// Search and filter
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'upload_date';
$description = $_GET['description'] ??'' && [''];
$order = $_GET['order'] ?? 'DESC';
$featured_count = $_GET['user'] ??'' && ['documents']??'' && ['date']??'' && ['featured']??'';
// Build query
$query = "SELECT d.*, u.username, u.profile_picture 
          FROM documents d 
          JOIN users u ON d.uploaded_by = u.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (d.title LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter) && $category_filter !== 'all') {
    $query .= " AND d.category = ?";
    $params[] = $category_filter;
}

if (!empty($user_filter) && is_numeric($user_filter)) {
    $query .= " AND d.uploaded_by = ?";
    $params[] = $user_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(d.upload_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(d.upload_date) <= ?";
    $params[] = $date_to;
}

// Add sorting
$valid_sorts = ['upload_date', 'title', 'download_count', 'file_size'];
$valid_orders = ['ASC', 'DESC'];
$sort = in_array($sort, $valid_sorts) ? $sort : 'upload_date';
$order = in_array(strtoupper($order), $valid_orders) ? strtoupper($order) : 'DESC';

$query .= " ORDER BY $sort $order";

// Get total count
$count_query = str_replace('SELECT d.*, u.username, u.profile_picture', 'SELECT COUNT(*) as count', $query);
$stmt = $mysqli->prepare($count_query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_documents = $stmt->get_result()->fetch_assoc()['count'];

$total_download_res = $mysqli->query("SELECT SUM(download_count) as count FROM documents");
$total_download = $total_download_res ? $total_download_res->fetch_assoc()['count'] : 0;
$total_size_res = $mysqli->query("SELECT SUM(file_size) as count FROM documents");
$total_size = $total_size_res ? $total_size_res->fetch_assoc()['count'] : 0;
$featured_count_res = $mysqli->query("SELECT COUNT(*) as count FROM documents WHERE id = 1");
$featured_count = $featured_count_res ? $featured_count_res->fetch_assoc()['count'] : 0;
// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_documents / $per_page);

$query .= " LIMIT $per_page OFFSET $offset";

// Fetch documents
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all categories for filter dropdown
$categories = $mysqli->query("SELECT DISTINCT category FROM documents WHERE category IS NOT NULL ORDER BY category")->fetch_all(MYSQLI_ASSOC);

// Get all users for filter dropdown
$users = $mysqli->query("SELECT id, username FROM users ORDER BY username")->fetch_all(MYSQLI_ASSOC);

// This part above used $total_documents, which is correct.
// We'll reset $today_uploads with proper MySQLi
$today_uploads_res = $mysqli->query("SELECT COUNT(*) as count FROM documents WHERE DATE(upload_date) = CURDATE()");
$today_uploads = $today_uploads_res ? $today_uploads_res->fetch_assoc()['count'] : 0;

// Format total size
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } else {
        return '0 bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Documents - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .documents-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--button-gray);
        }
        
        .documents-stats {
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
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .document-card {
            background-color: var(--light-blue);
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .document-card:hover {
            transform: translateY(-5px);
        }
        
        .document-card.featured {
            border: 2px solid var(--accent);
        }
        
        .document-header {
            padding: 1rem;
            background-color: var(--dark-blue);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .document-icon {
            font-size: 1.5rem;
            color: var(--accent);
        }
        
        .document-title {
            flex: 1;
            font-weight: bold;
            color: var(--white);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .document-featured {
            background-color: var(--accent);
            color: var(--dark-blue);
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .document-body {
            padding: 1rem;
        }
        
        .document-description {
            color: var(--gray);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            display: -webkit-box;
    
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .document-meta {
            display: flex;
            justify-content: space-between;
            color: var(--gray);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .document-uploader {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .uploader-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .document-actions {
            display: flex;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--button-gray);
        }
        
        .action-btn {
            flex: 1;
            padding: 0.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            font-size: 0.875rem;
        }
        
        .view-btn {
            background-color: var(--button-gray);
            color: var(--white);
            text-decoration: none;
        }
        
        .feature-btn {
            background-color: #f59e0b;
            color: white;
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
        
        .category-stats {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .category-stats h3 {
            color: var(--accent);
            margin-bottom: 1rem;
        }
        
        .category-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            border-bottom: 1px solid var(--button-gray);
        }
        
        .category-item:last-child {
            border-bottom: none;
        }
        
        .document-checkbox {
            margin-right: 0.5rem;
        }
        
        .file-type-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .documents-grid {
                grid-template-columns: 1fr;
            }
            
            .document-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="documents-header">
                <h1><i class="fas fa-file-alt"></i> Manage Documents</h1>
                <div class="header-actions">
                    <a href="../library.php" class="btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Public Library
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
            <div class="documents-stats">
                <div class="stat-card">
                    <i class="fas fa-file-alt"></i>
                    <h3><?php echo $total_documents; ?></h3>
                    <p>Total Documents</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-download"></i>
                    <h3><?php echo $total_download; ?></h3>
                    <p>Total Downloads</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-hdd"></i>
                    <h3><?php echo format_file_size($total_size); ?></h3>
                    <p>Total Size</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star"></i>
                    <h3><?php echo $featured_count; ?></h3>
                    <p>Featured Documents</p>
                </div>
            </div>
            
            <!-- Category Statistics -->
            <?php 
            $category_stats = $mysqli->query("SELECT category, COUNT(*) as count, SUM(download_count) as downloads 
                                           FROM documents 
                                           WHERE category IS NOT NULL 
                                           GROUP BY category 
                                           ORDER BY count DESC 
                                           LIMIT 10")->fetch_all(MYSQLI_ASSOC);
            ?>
            <?php if(!empty($category_stats)): ?>
            <div class="category-stats">
                <h3><i class="fas fa-chart-pie"></i> Top Categories</h3>
                <?php foreach($category_stats as $stat): ?>
                <div class="category-item">
                    <span><?php echo htmlspecialchars($stat['category']); ?></span>
                    <span><?php echo $stat['count']; ?> files (<?php echo $stat['downloads']; ?> downloads)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <form method="GET" action="" class="filters-bar">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="search"><i class="fas fa-search"></i> Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search documents...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="category"><i class="fas fa-folder"></i> Category</label>
                        <select id="category" name="category">
                            <option value="all">All Categories</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category; ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="user"><i class="fas fa-user"></i> Uploader</label>
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
                            <option value="upload_date" <?php echo $sort === 'upload_date' ? 'selected' : ''; ?>>Date</option>
                            <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                            <option value="download_count" <?php echo $sort === 'download_count' ? 'selected' : ''; ?>>Downloads</option>
                            <option value="file_size" <?php echo $sort === 'file_size' ? 'selected' : ''; ?>>Size</option>
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
                    <a href="manage_documents.php" class="btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                </div>
            </form>
            
            <!-- Bulk Actions -->
            <?php if($total_documents > 0): ?>
            <form method="POST" action="" class="bulk-actions">
                <select name="bulk_action" required style="padding: 0.5rem;">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete Selected</option>
                    <option value="feature">Mark as Featured</option>
                    <option value="unfeature">Remove Featured</option>
                </select>
                <button type="submit" class="btn-primary" onclick="return confirm('Apply bulk action to selected documents?')">
                    <i class="fas fa-play"></i> Apply
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Documents Grid -->
            <?php if(empty($documents)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Documents Found</h3>
                    <p>No documents match your current filters.</p>
                </div>
            <?php else: ?>
                <div class="documents-grid">
                    <?php foreach($documents as $doc): ?>
                    <div class="document-card <?php echo $doc['featured'] ? 'featured' : ''; ?>">
                        <div class="document-header">
                            <div class="document-icon">
                                <?php 
                                $file_type = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                                switch($file_type) {
                                    case 'pdf':
                                        echo '<i class="fas fa-file-pdf"></i>';
                                        break;
                                    case 'doc':
                                    case 'docx':
                                        echo '<i class="fas fa-file-word"></i>';
                                        break;
                                    case 'ppt':
                                    case 'pptx':
                                        echo '<i class="fas fa-file-powerpoint"></i>';
                                        break;
                                    case 'txt':
                                        echo '<i class="fas fa-file-alt"></i>';
                                        break;
                                    default:
                                        echo '<i class="fas fa-file"></i>';
                                }
                                ?>
                            </div>
                            <div class="document-title" title="<?php echo htmlspecialchars($doc['title']); ?>">
                                <?php echo htmlspecialchars(substr($doc['title'], 0, 30)); ?>
                                <?php if(strlen($doc['title']) > 30): ?>...<?php endif; ?>
                            </div>
                            <?php if($doc['featured']): ?>
                                <div class="document-featured">Featured</div>
                            <?php endif; ?>
                            <input type="checkbox" name="selected_documents[]" 
                                   value="<?php echo $doc['id']; ?>"
                                   class="document-checkbox">
                        </div>
                        
                        <div class="document-body">
                            <div class="document-description">
                                <?php echo htmlspecialchars(substr($doc['description'], 0, 100)); ?>
                                <?php if(strlen($doc['description']) > 100): ?>...<?php endif; ?>
                            </div>
                            
                            <div class="document-meta">
                                <span>
                                    <i class="fas fa-download"></i> 
                                    <?php echo $doc['download_count']; ?>
                                </span>
                                <span>
                                    <i class="fas fa-weight"></i> 
                                    <?php echo format_file_size($doc['file_size']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                </span>
                            </div>
                            
                            <div class="document-uploader">
                                <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($doc['profile_picture']); ?>" 
                                     alt="Profile" class="uploader-avatar">
                                <span><?php echo htmlspecialchars($doc['username']); ?></span>
                            </div>
                            
                            <div class="document-actions">
                                <a href="../assets/uploads/documents/<?php echo $doc['file_path']; ?>" 
                                   class="action-btn view-btn" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if($doc['featured']): ?>
                                    <button type="button" class="action-btn feature-btn" 
                                            onclick="toggleFeatured(<?php echo $doc['id']; ?>, false)">
                                        <i class="fas fa-star"></i> Unfeature
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="action-btn feature-btn" 
                                            onclick="toggleFeatured(<?php echo $doc['id']; ?>, true)">
                                        <i class="fas fa-star"></i> Feature
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="action-btn delete-btn" 
                                        onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&user=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>"
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    

    <script>
    function deleteDocument(docId) {
        if (confirm('Delete this document? This action cannot be undone!')) {
            window.location.href = 'manage_documents.php?action=delete&id=' + docId + '&<?php echo http_build_query($_GET); ?>';
        }
    }
    
    function toggleFeatured(docId, featured) {
        const action = featured ? 'feature' : 'unfeature';
        window.location.href = 'manage_documents.php?action=' + action + '&id=' + docId + '&<?php echo http_build_query($_GET); ?>';
    }
    
    // Select all checkboxes
    function selectAllDocuments(checked) {
        const checkboxes = document.querySelectorAll('.document-checkbox');
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
        selectAllBtn.onclick = () => selectAllDocuments(true);
        
        const selectNoneBtn = document.createElement('button');
        selectNoneBtn.type = 'button';
        selectNoneBtn.className = 'btn-secondary';
        selectNoneBtn.innerHTML = '<i class="fas fa-square"></i> Select None';
        selectNoneBtn.onclick = () => selectAllDocuments(false);
        
        const bulkActions = document.querySelector('.bulk-actions');
        if (bulkActions) {
            bulkActions.insertBefore(selectNoneBtn, bulkActions.firstChild);
            bulkActions.insertBefore(selectAllBtn, bulkActions.firstChild);
        }
    });
    </script>
</body>
</html>