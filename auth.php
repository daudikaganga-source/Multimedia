<?php
/**
 * Authentication and Authorization System
 * Manages user roles, permissions, and access control
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 * @return bool
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is regular user
 * @return bool
 */
function is_user() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

/**
 * Get current user ID
 * @return int|null
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * @return string|null
 */
function get_current_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Redirect user based on role
 * This function redirects users to appropriate pages based on their role
 */
function redirect_based_on_role() {
    if (!is_logged_in()) {
        return; // Not logged in, no redirect
    }
    
    $current_page = basename($_SERVER['PHP_SELF']);
    $role = get_current_user_role();
    
    // Define role-based access rules
    $admin_only_pages = [
        'dashboard.php',
        'manage_users.php',
        'manage_documents.php',
        'view_discussions.php',
        'notifications.php',
        'system_logs.php',
        'settings.php'
    ];
    
    $user_allowed_pages = [
        'index.php',
        'profile.php',
        'library.php',
        'discussions.php',
        'upload.php',
        'logout.php'
    ];
    
    // Check if trying to access admin page
    if (in_array($current_page, $admin_only_pages)) {
        if (!is_admin()) {
            // User trying to access admin page but not admin
            $_SESSION['error'] = "Access denied! Admin privileges required.";
            redirect('../index.php');
        }
    }
    
    // Additional security: Check if user is trying to access admin directory
    $is_in_admin_dir = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
    if ($is_in_admin_dir && !is_admin()) {
        $_SESSION['error'] = "Admin area access denied!";
        redirect('../index.php');
    }
}

/**
 * Require authentication - redirect to login if not logged in
 * @param string $redirect_url URL to redirect after login
 */
function require_auth($redirect_url = null) {
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $redirect_url ?: $_SERVER['REQUEST_URI'];
        $_SESSION['error'] = "Please login to access this page.";
        redirect('login.php');
    }
}

/**
 * Require admin role - redirect if not admin
 */
function require_admin() {
    if (!is_admin()) {
        $_SESSION['error'] = "Admin privileges required!";
        redirect('index.php');
    }
}

/**
 * Require user role - redirect if not user
 */
function require_user() {
    if (!is_user()) {
        $_SESSION['error'] = "User account required!";
        redirect('index.php');
    }
}

/**
 * Check if user can download files
 * @return bool
 */
function can_download() {
    return is_logged_in();
}

/**
 * Check if user can upload files
 * @return bool
 */
function can_upload() {
    return is_logged_in();
}

/**
 * Check if user can post discussions
 * @return bool
 */
function can_post_discussion() {
    return is_logged_in();
}

/**
 * Check if user can edit profile
 * @param int $user_id User ID to check
 * @return bool
 */
function can_edit_profile($user_id) {
    if (!is_logged_in()) {
        return false;
    }
    
    // Users can edit their own profile, admins can edit any profile
    return ($_SESSION['user_id'] == $user_id) || is_admin();
}

/**
 * Check if user can delete document
 * @param int $uploaded_by User ID who uploaded the document
 * @return bool
 */
function can_delete_document($uploaded_by) {
    if (!is_logged_in()) {
        return false;
    }
    
    // Users can delete their own uploads, admins can delete any
    return ($_SESSION['user_id'] == $uploaded_by) || is_admin();
}

/**
 * Check if user can delete discussion
 * @param int $user_id User ID who created the discussion
 * @return bool
 */
function can_delete_discussion($user_id) {
    if (!is_logged_in()) {
        return false;
    }
    
    // Users can delete their own discussions, admins can delete any
    return ($_SESSION['user_id'] == $user_id) || is_admin();
}

/**
 * Get user permissions array
 * @return array
 */
function get_user_permissions() {
    $permissions = [
        'can_view' => true, // Everyone can view
        'can_download' => can_download(),
        'can_upload' => can_upload(),
        'can_post_discussion' => can_post_discussion(),
        'can_edit_profile' => false,
        'can_delete_documents' => is_admin(),
        'can_delete_discussions' => is_admin(),
        'can_manage_users' => is_admin(),
        'can_view_admin_panel' => is_admin(),
        'can_view_notifications' => is_admin(),
        'can_view_system_logs' => is_admin(),
    ];
    
    return $permissions;
}

/**
 * Log user activity
 * @param string $action Action performed
 * @param string $details Action details
 */
function log_activity($action, $details = '') {
    global $mysqli;
    
    if (!is_logged_in()) {
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $page_url = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    // Determine module from action
    $module = 'general';
    if (strpos($action, 'login') !== false) $module = 'auth';
    if (strpos($action, 'upload') !== false) $module = 'upload';
    if (strpos($action, 'download') !== false) $module = 'download';
    if (strpos($action, 'profile') !== false) $module = 'profile';
    if (strpos($action, 'discussion') !== false) $module = 'discussion';
    
    // Determine log level
    $level = 'info';
    if (strpos($action, 'failed') !== false) $level = 'warning';
    if (strpos($action, 'error') !== false) $level = 'error';
    if (strpos($action, 'security') !== false) $level = 'critical';
    
    $message = "User ID $user_id performed: $action";
    if ($details) {
        $message .= " - $details";
    }
    
    // Insert into system logs if table exists
    try {
        $stmt = $mysqli->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address, user_agent, page_url) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssisss', $level, $module, $message, $user_id, $ip_address, $user_agent, $page_url);
        $stmt->execute();
    } catch (Exception $e) {
        // Log table might not exist yet, ignore error
    }
}

/**
 * Update user last activity
 */
function update_last_activity() {
    global $mysqli;
    
    if (!is_logged_in()) {
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $mysqli->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
    } catch (Exception $e) {
        // Ignore error
    }
}

/**
 * Check if user session is valid
 * @return bool
 */
function validate_session() {
    if (!is_logged_in()) {
        return false;
    }
    
    global $mysqli;
    
    // Check if user still exists in database
    $stmt = $mysqli->prepare("SELECT id, status FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        // User no longer exists
        session_destroy();
        return false;
    }
    
    // Check if user is banned/suspended
    if (isset($user['status']) && $user['status'] === 'banned') {
        $_SESSION['error'] = "Your account has been suspended.";
        session_destroy();
        return false;
    }
    
    // Update last activity
    update_last_activity();
    
    return true;
}

/**
 * Generate CSRF token
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @return bool
 */
function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Secure form input
 * @param string $input Input to sanitize
 * @return string
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Check brute force attack prevention
 * @param string $username Username to check
 * @return bool True if too many attempts
 */
function check_brute_force($username) {
    global $mysqli;
    
    $now = time();
    $valid_attempts = $now - (2 * 60 * 60); // 2 hours
    
    try {
        $stmt = $mysqli->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                              WHERE username = ? AND time > ?");
        $stmt->bind_param('si', $username, $valid_attempts);
        $stmt->execute();
        $res = $stmt->get_result();
        $result = $res->fetch_assoc();
        
        return ($result['attempts'] >= 5); // 5 attempts in 2 hours
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Record login attempt
 * @param string $username Username attempted
 * @param bool $success Whether login was successful
 */
function record_login_attempt($username, $success) {
    global $mysqli;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $time = time();
    
    try {
        // Create table if not exists
        $mysqli->query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            success BOOLEAN DEFAULT FALSE,
            time INT,
            INDEX idx_username (username),
            INDEX idx_time (time)
        )");
        
        $stmt = $mysqli->prepare("INSERT INTO login_attempts (username, ip_address, user_agent, success, time) 
                              VALUES (?, ?, ?, ?, ?)");
        $success_int = $success ? 1 : 0;
        $stmt->bind_param('sssii', $username, $ip_address, $user_agent, $success_int, $time);
        $stmt->execute();
        
        // Log the attempt
        log_activity($success ? 'login_success' : 'login_failed', "Username: $username");
    } catch (Exception $e) {
        // Ignore error
    }
}

/**
 * Auto-logout inactive users
 */
function auto_logout_inactive() {
    if (!is_logged_in()) {
        return;
    }
    
    $inactive_timeout = 1800; // 30 minutes in seconds
    
    // Check last activity time from session
    $last_activity = $_SESSION['last_activity'] ?? 0;
    $current_time = time();
    
    if (($current_time - $last_activity) > $inactive_timeout) {
        // Logout user
        session_destroy();
        $_SESSION['info'] = "You have been logged out due to inactivity.";
        redirect('login.php');
    } else {
        // Update last activity time
        $_SESSION['last_activity'] = $current_time;
    }
}

/**
 * Initialize authentication system
 */
function init_auth() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session security parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerate every 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Validate session
    if (!validate_session()) {
        return;
    }
    
    // Auto-logout inactive users
    auto_logout_inactive();
    
    // Redirect based on role
    redirect_based_on_role();
}

/**
 * Login user
 * @param string $username Username
 * @param string $password Password
 * @return array Result array with success/error
 */
function login_user($username, $password) {
    global $mysqli;
    
    // Check brute force
    if (check_brute_force($username)) {
        record_login_attempt($username, false);
        return ['success' => false, 'message' => 'Too many failed login attempts. Try again later.'];
    }
    
    // Get user from database
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        record_login_attempt($username, false);
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Check if account is active
    if (isset($user['status']) && $user['status'] === 'banned') {
        return ['success' => false, 'message' => 'Your account has been suspended.'];
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        record_login_attempt($username, false);
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Successful login
    record_login_attempt($username, true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['profile_picture'] = $user['profile_picture'];
    $_SESSION['full_name'] = $user['full_name'] ?? '';
    $_SESSION['created'] = time();
    $_SESSION['last_activity'] = time();
    
    // Update last login time
    $stmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    
    // Log activity
    log_activity('login_success', "User: {$user['username']}");
    
    return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
}

/**
 * Logout user
 */
function logout_user() {
    if (is_logged_in()) {
        global $mysqli;
        
        // Update last logout time
        $stmt = $mysqli->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        
        // Log activity
        log_activity('logout', "User: {$_SESSION['username']}");
    }
    
    // Destroy session
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Register new user
 * @param array $user_data User data array
 * @return array Result array with success/error
 */
function register_user($user_data) {
    global $mysqli;
    
    // Validate required fields
    $required = ['username', 'email', 'password', 'confirm_password'];
    foreach ($required as $field) {
        if (empty($user_data[$field])) {
            return ['success' => false, 'message' => "$field is required"];
        }
    }
    
    // Validate email
    if (!filter_var($user_data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    // Check password match
    if ($user_data['password'] !== $user_data['confirm_password']) {
        return ['success' => false, 'message' => 'Passwords do not match'];
    }
    
    // Check password strength
    if (strlen($user_data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    
    // Check if username/email exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $user_data['username'], $user_data['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash password
    $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);
    
    // Set default role if not specified
    $role = $user_data['role'] ?? 'user';
    
    // Prevent self-assigning admin role (for security)
    if ($role === 'admin') {
        // Only allow admin registration if no admin exists yet
        $result = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $row = $result->fetch_assoc();
        $admin_count = $row['count'];
        if ($admin_count > 0) {
            $role = 'user';
        }
    }
    
    // Insert user
    $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, full_name, role) 
                          VALUES (?, ?, ?, ?, ?)");
    
    try {
        $full_name = $user_data['full_name'] ?? '';
        $stmt->bind_param('sssss', 
            $user_data['username'],
            $user_data['email'],
            $hashed_password,
            $full_name,
            $role
        );
        $stmt->execute();
        
        $user_id = $mysqli->insert_id;
        
        // Create notification for admin
        if ($role === 'user') {
            $notif_stmt = $mysqli->prepare("INSERT INTO notifications (type, message, related_id) 
                          VALUES ('new_user', ?, ?)");
            $notif_message = "New user registered: {$user_data['username']}";
            $notif_stmt->bind_param('si', $notif_message, $user_id);
            $notif_stmt->execute();
        }
        
        // Log activity
        log_activity('user_registered', "Username: {$user_data['username']}, Role: $role");
        
        return ['success' => true, 'message' => 'Registration successful', 'user_id' => $user_id];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

// Initialize authentication system
init_auth();
?>