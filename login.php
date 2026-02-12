<?php
require_once 'includes/config.php';

if (is_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_picture'] = $user['profile_picture'];
        
        // Update last login
        $upd_stmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $upd_stmt->bind_param('i', $user['id']);
        $upd_stmt->execute();
            
        redirect('index.php');
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Share Learn</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<?php include 'includes/header.php'; ?>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">

            <h2>Welcome Back</h2>
            <p>Login to access resources and discussions</p>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-primary">Login</button>
            </form>
            
            <p class="auth-link">Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>