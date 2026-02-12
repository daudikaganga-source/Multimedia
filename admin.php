<?php
require_once 'includes/config.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'mark_notification_read':
        $id = intval($_GET['id']);
        $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;
        
    case 'get_notification_count':
        $count = $mysqli->query("SELECT COUNT(*) FROM notifications WHERE is_read = ?");
        echo json_encode(['count' => $count]);
        break;
        
    case 'delete_document':
        $id = intval($_GET['id']);
        
        // Get file path first
        $stmt = $mysqli->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doc = $result->fetch_assoc();
        
        if ($doc) {
            // Delete file
            $file_path = '../assets/uploads/documents/' . $doc['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete from database
            $del_stmt = $mysqli->prepare("DELETE FROM documents WHERE id = ?");
            $del_stmt->bind_param('i', $id);
            $del_stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Document not found']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>