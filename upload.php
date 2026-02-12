<?php
require_once 'includes/config.php';

if (!is_logged_in()) {
    $_SESSION['redirect_url'] = 'upload.php';
    redirect('register.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    
    // File upload handling
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document'];
        $file_name = basename($file['name']);
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check file type
        global $allowed_file_types;
        if (!in_array($file_type, array_keys($allowed_file_types))) {
            $error = "File type not allowed. Allowed types: " . implode(', ', array_keys($allowed_file_types));
        } elseif ($file_size > 50 * 1024 * 1024) { // 50MB limit
            $error = "File size too large. Maximum size is 50MB.";
        } else {
            // Generate unique filename
            $new_filename = uniqid() . '_' . time() . '.' . $file_type;
            $upload_path = UPLOAD_PATH . 'documents/' . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Save to database
                $stmt = $mysqli->prepare("INSERT INTO documents (title, description, file_path, file_type, file_size, uploaded_by, category) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssiss', $title, $description, $new_filename, $file_type, $file_size, $_SESSION['user_id'], $category);
                $stmt->execute();
                $doc_id = $mysqli->insert_id;
                
                // Create notification for admin
                $notif_stmt = $mysqli->prepare("INSERT INTO notifications (type, message, related_id) VALUES ('upload', ?, ?)");
                $notif_message = "User {$_SESSION['username']} uploaded: $title";
                $notif_stmt->bind_param('si', $notif_message, $doc_id);
                $notif_stmt->execute();
                
                $success = "File uploaded successfully!";
            } else {
                $error = "Failed to upload file.";
            }
        }
    } else {
        $error = "Please select a file to upload.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload - Share Learn</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <section class="upload-section">
        <div class="container">
            <h1>Upload Document</h1>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
                <div class="form-group">
                    <label for="title"><i class="fas fa-heading"></i> Document Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="category"><i class="fas fa-folder"></i> Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Science">Science</option>
                        <option value="Literature">Literature</option>
                        <option value="Programming">Programming</option>
                        <option value="History">History</option>
                        <option value="Business">Business</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Description</label>
                    <textarea id="description" name="description" rows="4" required></textarea>
                </div>
                
                <div class="file-upload-area">
                    <input type="file" id="fileInput" name="document" accept=".pdf,.doc,.docx,.txt,.ppt,.pptx" >
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Drag & Drop or Click to Upload</h3>
                    <p>Supported files: PDF, DOC, DOCX, TXT, PPT, PPTX (Max 50MB)</p>
                    <div id="filePreview"></div>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
            </form>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
</body>
</html>