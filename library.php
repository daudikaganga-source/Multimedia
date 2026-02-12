<?php
require_once 'includes/config.php';

if (!is_logged_in()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
}

// Fetch documents
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$query = "SELECT d.*, u.username as uploader FROM documents d 
          JOIN users u ON d.uploaded_by = u.id WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (d.title LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND d.category = ?";
    $params[] = $category;
}

$query .= " ORDER BY d.upload_date DESC";

$stmt = $mysqli->prepare($query);
if (count($params) > 0) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$documents = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library - Share Learn</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <section class="library-header">
        <div class="container">
            <h1>Library</h1>
            <p>Browse and download educational resources</p>
            
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search documents..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="Mathematics">Mathematics</option>
                    <option value="Science">Science</option>
                    <option value="Literature">Literature</option>
                    <option value="Programming">Programming</option>
                </select>
                <button class="btn-primary" onclick="searchDocuments()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </div>
    </section>
    
    <section class="documents-section">
        <div class="container">
            <div class="documents-grid">
                <?php foreach($documents as $doc): ?>
                <div class="document-card">
                    <div class="document-info">
                        <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($doc['description'], 0, 100)); ?>...</p>
                        <div class="document-meta">
                            <span><i class="fas fa-user"></i> <?php echo $doc['uploader']; ?></span>
                            <span><i class="fas fa-download"></i> <?php echo $doc['download_count']; ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></span>
                        </div>
                        <div class="document-actions">
                            <a href="download.php?id=<?php echo $doc['id']; ?>" class="btn-secondary">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <?php if(is_admin()): ?>
                            <button class="btn-danger" onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
    <script >
    function deleteDocument(docId) {
        if (confirm('Delete this document? This action cannot be undone!')) {
            window.location.href = 'manage_documents.php?action=delete&id=' + docId + '&<?php echo http_build_query($_GET); ?>';
        }
    }
    
    function searchDocuments() {
        const search = document.getElementById('searchInput').value;
        const category = document.getElementById('categoryFilter').value;
        let url = 'library.php?';
        
        if (search) url += `search=${encodeURIComponent(search)}&`;
        if (category) url += `category=${encodeURIComponent(category)}`;
        
        window.location.href = url;
    }
    
    function deleteDocument(docId) {
        if (confirm('Are you sure you want to delete this document?')) {
            fetch(`api/admin.php?action=delete_document&id=${docId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
        }
    }
    </script>
</body>
</html>