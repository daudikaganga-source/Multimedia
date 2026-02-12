<?php
require_once 'includes/config.php';

if (!is_logged_in()) {
    $_SESSION['redirect_url'] = 'discussions.php';
    redirect('register.php');
}

// Fetch discussions
$stmt = $mysqli->query("SELECT d.*, u.username, u.profile_picture FROM discussions d 
                     JOIN users u ON d.user_id = u.id 
                     ORDER BY d.created_at DESC");
$result = $stmt;
$discussions = $result->fetch_all(MYSQLI_ASSOC);

// Post new discussion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic']) && isset($_POST['message'])) {
    $topic = $_POST['topic'];
    $message = $_POST['message'];
    
    $stmt = $mysqli->prepare("INSERT INTO discussions (user_id, topic, message) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $_SESSION['user_id'], $topic, $message);
    if ($stmt->execute()) {
        $success = "Discussion posted successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussions - Share Learn</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <section class="discussions-section">
        <div class="container">
            <h1>Community Discussions</h1>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- New Discussion Form -->
            <div class="new-discussion-form">
                <h2><i class="fas fa-plus-circle"></i> Start New Discussion</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <input type="text" name="topic" placeholder="Discussion Topic" required>
                    </div>
                    <div class="form-group">
                        <textarea name="message" placeholder="Your message..." rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Post Discussion
                    </button>
                </form>
            </div>
            
            <!-- Discussions List -->
            <div class="discussions-list">
                <?php foreach($discussions as $discussion): ?>
                <div class="discussion-card">
                    <div class="discussion-header">
                        <img src="assets/uploads/profiles/<?php echo $discussion['profile_picture']; ?>" 
                             alt="Profile" class="discussion-profile">
                        <div>
                            <h3><?php echo htmlspecialchars($discussion['topic']); ?></h3>
                            <p class="discussion-meta">
                                By <?php echo htmlspecialchars($discussion['username']); ?> 
                                on <?php echo date('M d, Y H:i', strtotime($discussion['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="discussion-body">
                        <p><?php echo nl2br(htmlspecialchars($discussion['message'])); ?></p>
                    </div>
                    <?php if(is_admin()): ?>
                    <div class="discussion-actions">
                        <button class="btn-danger" onclick="deleteDiscussion(<?php echo $discussion['id']; ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
    <script>
    function deleteDiscussion(discussionId) {
        if (confirm('Are you sure you want to delete this discussion?')) {
            fetch(`api/admin.php?action=delete_discussion&id=${discussionId}`)
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