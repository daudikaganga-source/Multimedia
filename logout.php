<?php
session_start();

if (isset($_SESSION['user_id'])) {
    require_once 'includes/config.php';
    
    // Update last logout time
    $stmt = $mysqli->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
}

// Destroy all session data
$_SESSION = array();
session_destroy();

// Redirect to home page
header("Location: index.php");
exit;
?>