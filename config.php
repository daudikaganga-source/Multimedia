<?php
session_start();
ob_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sharelearn');
define('SITE_NAME', 'Share Learn');
define('SITE_URL', 'http://localhost/sharelearn');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/sharelearn/assets/uploads/');

// Allowed file types for upload
$allowed_file_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];

// Create MySQLi database connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $mysqli->set_charset("utf8mb4");
} catch(Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}
?>