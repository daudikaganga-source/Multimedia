<?php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

// Get all settings
$stmt = $mysqli->query("SELECT setting_key, setting_value, setting_group, data_type, is_public FROM settings ORDER BY setting_group, setting_key");
$settings = $stmt->fetch_all(MYSQLI_ASSOC);

$export_data = [
    'export_date' => date('Y-m-d H:i:s'),
    'exported_by' => $_SESSION['username'],
    'settings' => $settings
];

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="sharelearn_settings_' . date('Y-m-d') . '.json"');
echo json_encode($export_data, JSON_PRETTY_PRINT);
?>