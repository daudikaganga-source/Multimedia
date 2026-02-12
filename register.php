<?php
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = $_POST['full_name'];
    $role = $_POST['role'] ?? 'user';
    
    // Validation
    $errors = [];
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters!";
    }
    
    // Check if user exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Username or email already exists!";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $username, $email, $hashed_password, $full_name, $role);
        if ($stmt->execute()) {
            $user_id = $mysqli->insert_id;
            // Create notification for admin if user registered
            if ($role === 'user') {
                $notif_stmt = $mysqli->prepare("INSERT INTO notifications (type, message, related_id) VALUES ('new_user', ?, ?)");
                $notif_message = "New user registered: $username";
                $notif_stmt->bind_param('si', $notif_message, $user_id);
                $notif_stmt->execute();
            }
            
            $_SESSION['success'] = "Registration successful! Please login.";
            redirect('login.php');
        } else {
            $errors[] = "Registration failed! Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Share Learn</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">

<?php include 'includes/header.php'?>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Create Account</h2>
            <p>Join Share Learn community today</p>
            
            <?php if(!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user-circle"></i> Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <label for="role"><i class="fas fa-user-tag"></i> Role</label>
                    <select id="role" name="role">
                        <option value="user">Student/Learner</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary">Register</button>
            </form>
            
            <p class="auth-link">Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>