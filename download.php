<?php
require_once 'includes/config.php';

if (!is_logged_in()) {
    $_SESSION['redirect_url'] = 'download.php';
    redirect('login.php');
    exit; 
}

if (!isset($_GET['id'])) {
    die('Document ID required');
}

$doc_id = intval($_GET['id']);

// Get document info
$stmt = $mysqli->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->bind_param('i', $doc_id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();

if (!$document) {
    die('Document not found');
}

// Build full file path
$file_path = UPLOAD_PATH . 'documents/' . $document['file_path'];

// Security check: prevent path traversal
if (!file_exists($file_path) || !is_file($file_path)) {
    die('File not found on server');
}

// Log download
$stmt = $mysqli->prepare("INSERT INTO downloads_log (user_id, document_id) VALUES (?, ?)");
$stmt->bind_param('ii', $_SESSION['user_id'], $doc_id);
$stmt->execute();

// Update download count
$stmt = $mysqli->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id = ?");
$stmt->bind_param('i', $doc_id);
$stmt->execute();

// Create notification for admin
$stmt = $mysqli->prepare("INSERT INTO notifications (type, message, related_id) VALUES ('download', ?, ?)");
$notif_message = "User {$_SESSION['username']} downloaded: {$document['title']}";
$stmt->bind_param('si', $notif_message, $doc_id);
$stmt->execute();

// Determine file name and extension
$file_extension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
$download_filename = $document['title'] . '.' . $file_extension;

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $download_filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Clear output buffer
ob_clean();
flush();

// Stream file to user
readfile($file_path);
exit;
?>