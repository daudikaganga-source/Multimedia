<?php
require_once 'includes/config.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Get user data
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $bio = $_POST['bio'];
    $email = $_POST['email'];
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_type, $allowed_image_types)) {
            $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_type;
            $upload_path = UPLOAD_PATH . 'profiles/' . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old profile picture if not default
                if ($user['profile_picture'] !== 'default.png') {
                    $old_file = UPLOAD_PATH . 'profiles/' . $user['profile_picture'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                $user['profile_picture'] = $new_filename;
                $_SESSION['profile_picture'] = $new_filename;
            }
        }
    }
    
    // Update user data
    $stmt = $mysqli->prepare("UPDATE users SET full_name = ?, bio = ?, email = ?, profile_picture = ? WHERE id = ?");
    $stmt->bind_param('ssssi', $full_name, $bio, $email, $user['profile_picture'], $_SESSION['user_id']);
    if ($stmt->execute()) {
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Share Learn</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <section class="profile-section">
        <div class="container">
            <div class="profile-header">
                <img src="assets/uploads/profiles/<?php echo $user['profile_picture']; ?>" 
                     alt="Profile" class="profile-picture">
                <h1><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h1>
                <p>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="profile-content">
                <form method="POST" action="" enctype="multipart/form-data" class="profile-form">
                    <div class="form-group">
                        <label for="profile_picture"><i class="fas fa-camera"></i> Profile Picture</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bio"><i class="fas fa-edit"></i> Bio</label>
                        <textarea id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
                
                <div class="profile-stats">
                    <h3>Your Activity</h3>
                    <div class="stats-grid">
                        <?php
                        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM documents WHERE uploaded_by = ?");
                        $stmt->bind_param('i', $_SESSION['user_id']);
                        $stmt->execute();
                        $uploads = $stmt->get_result()->fetch_assoc()['count'];
                        
                        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM downloads_log WHERE user_id = ?");
                        $stmt->bind_param('i', $_SESSION['user_id']);
                        $stmt->execute();
                        $downloads = $stmt->get_result()->fetch_assoc()['count'];
                        
                        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM discussions WHERE user_id = ?");
                        $stmt->bind_param('i', $_SESSION['user_id']);
                        $stmt->execute();
                        $discussions = $stmt->get_result()->fetch_assoc()['count'];
                        ?>
                        <div class="stat-item">
                            <i class="fas fa-upload"></i>
                            <span><?php echo $uploads; ?> Uploads</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-download"></i>
                            <span><?php echo $downloads; ?> Downloads</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-comment"></i>
                            <span><?php echo $discussions; ?> Discussions</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
</body>
</html>