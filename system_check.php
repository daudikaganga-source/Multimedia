<?php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$results = [
    'database' => false,
    'disk_space' => false,
    'php_version' => false,
    'uploads' => false,
    'errors' => []
];

// Check database connection
try {
    $stmt = $mysqli->query("SELECT 1");
    $results['database'] = true;
} catch (Exception $e) {
    $results['errors'][] = 'Database connection failed: ' . $e->getMessage();
}

// Check disk space
$free_space = disk_free_space(__DIR__);
$total_space = disk_total_space(__DIR__);
if ($free_space && $total_space) {
    $free_percent = round(($free_space / $total_space) * 100, 2);
    $results['disk_space'] = $free_percent . '% free';
    
    if ($free_percent < 10) {
        $results['errors'][] = 'Low disk space: ' . $free_percent . '% remaining';
    }
}

// Check PHP version
$php_version = PHP_VERSION;
$results['php_version'] = $php_version;
if (version_compare($php_version, '7.4.0', '<')) {
    $results['errors'][] = 'PHP version ' . $php_version . ' is outdated. Consider upgrading to 7.4+';
}

// Check uploads directory
$uploads_dir = '../assets/uploads';
if (is_dir($uploads_dir) && is_writable($uploads_dir)) {
    $results['uploads'] = true;
} else {
    $results['errors'][] = 'Uploads directory not writable';
}

// Check required PHP extensions
$required_extensions = ['pdo_mysql', 'mbstring', 'gd', 'zip'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $results['errors'][] = 'PHP extension missing: ' . $ext;
    }
}

echo json_encode($results);
?>