<?php
// admin/system_logs.php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Create logs table if it doesn't exist
$mysqli->query("CREATE TABLE IF NOT EXISTS system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    module VARCHAR(50),
    message TEXT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level (level),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)");

// Add some sample logs if table is empty (for demo)
$logs_count_result = $mysqli->query("SELECT COUNT(*) as count FROM system_logs");
$logs_count = $logs_count_result ? $logs_count_result->fetch_assoc()['count'] : 0;
if ($logs_count == 0) {
    $sample_logs = [
        ['info', 'auth', 'User admin logged in successfully', 1],
        ['info', 'upload', 'User test uploaded document: Mathematics Notes', 2],
        ['warning', 'auth', 'Failed login attempt for username: unknown', null],
        ['error', 'system', 'Failed to send email notification', null],
        ['info', 'download', 'User john downloaded file: Science Textbook', 3],
        ['info', 'registration', 'New user registered: mary123', 4],
        ['critical', 'database', 'Database connection timeout', null],
        ['info', 'profile', 'User admin updated profile picture', 1],
        ['warning', 'upload', 'User test attempted to upload invalid file type', 2],
        ['info', 'system', 'Daily backup completed successfully', null],
    ];
    
    $stmt = $mysqli->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address, user_agent) 
                           VALUES (?, ?, ?, ?, '127.0.0.1', 'Mozilla/5.0')");
    
    foreach ($sample_logs as $log) {
        $stmt->bind_param("sssi", $log[0], $log[1], $log[2], $log[3]);
        $stmt->execute();
    }
}

// Handle log clearing
if (isset($_GET['clear_logs'])) {
    $mysqli->query("DELETE FROM system_logs");
    $_SESSION['success'] = "All logs cleared successfully!";
    redirect('system_logs.php');
}

// Handle log export
if (isset($_GET['export'])) {
    export_logs();
}

// Filter logs
$level_filter = $_GET['level'] ?? 'all';
$module_filter = $_GET['module'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT l.*, u.username 
          FROM system_logs l 
          LEFT JOIN users u ON l.user_id = u.id 
          WHERE 1=1";
$params = [];

if ($level_filter !== 'all') {
    $query .= " AND l.level = ?";
    $params[] = $level_filter;
}

if ($module_filter !== 'all') {
    $query .= " AND l.module = ?";
    $params[] = $module_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(l.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(l.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (l.message LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY l.created_at DESC";

// Get counts for filter badges
$total_logs = $mysqli->query("SELECT COUNT(*) as count FROM system_logs")->fetch_assoc()['count'];
$info_count = $mysqli->query("SELECT COUNT(*) as count FROM system_logs WHERE level = 'info'")->fetch_assoc()['count'];
$warning_count = $mysqli->query("SELECT COUNT(*) as count FROM system_logs WHERE level = 'warning'")->fetch_assoc()['count'];
$error_count = $mysqli->query("SELECT COUNT(*) as count FROM system_logs WHERE level = 'error'")->fetch_assoc()['count'];
$critical_count = $mysqli->query("SELECT COUNT(*) as count FROM system_logs WHERE level = 'critical'")->fetch_assoc()['count'];

// Get unique modules
$modules = $mysqli->query("SELECT DISTINCT module FROM system_logs WHERE module IS NOT NULL ORDER BY module")->fetch_all(MYSQLI_ASSOC);

// Pagination
$per_page = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$count_query = str_replace('SELECT l.*, u.username', 'SELECT COUNT(*) as count', $query);
$stmt = $mysqli->prepare($count_query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_res = $stmt->get_result();
$total_results = $count_res->fetch_assoc()['count'];
$total_pages = ceil($total_results / $per_page);

$query .= " LIMIT $per_page OFFSET $offset";

// Fetch logs
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs_res = $stmt->get_result();
$logs = $logs_res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--button-gray);
        }
        
        .logs-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card.info { border-left: 4px solid #3b82f6; }
        .stat-card.warning { border-left: 4px solid #f59e0b; }
        .stat-card.error { border-left: 4px solid #ef4444; }
        .stat-card.critical { border-left: 4px solid #dc2626; }
        
        .logs-filters {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--light-gray);
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--button-gray);
            border-radius: 5px;
            background-color: var(--dark-blue);
            color: var(--white);
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--light-blue);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .logs-table th,
        .logs-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--button-gray);
        }
        
        .logs-table th {
            background-color: var(--dark-blue);
            color: var(--accent);
            font-weight: bold;
        }
        
        .log-level {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .level-info { background-color: #3b82f6; color: white; }
        .level-warning { background-color: #f59e0b; color: white; }
        .level-error { background-color: #ef4444; color: white; }
        .level-critical { background-color: #dc2626; color: white; }
        
        .log-message {
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .log-details {
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .empty-logs {
            text-align: center;
            padding: 4rem;
            background-color: var(--light-blue);
            border-radius: 10px;
        }
        
        .empty-logs i {
            font-size: 4rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        .export-options {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .log-tooltip {
            position: relative;
            cursor: help;
        }
        
        .log-tooltip .tooltip-text {
            visibility: hidden;
            width: 300px;
            background-color: var(--dark-blue);
            color: var(--white);
            text-align: left;
            padding: 1rem;
            border-radius: 6px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            border: 1px solid var(--accent);
            white-space: normal;
        }
        
        .log-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        .log-date {
            white-space: nowrap;
        }
        
        .auto-refresh-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .logs-table {
                display: block;
                overflow-x: auto;
            }
            
            .logs-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="logs-header">
                <h1><i class="fas fa-clipboard-list"></i> System Logs</h1>
                <div class="header-actions">
                    <a href="?export=csv" class="btn-primary" style="margin-right: 0.5rem;">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>
                    <a href="?clear_logs" class="btn-danger" onclick="return confirm('Clear all system logs? This cannot be undone!')">
                        <i class="fas fa-trash"></i> Clear All Logs
                    </a>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Log Statistics -->
            <div class="logs-stats">
                <div class="stat-card info">
                    <h3><?php echo $info_count; ?></h3>
                    <p>Info Logs</p>
                </div>
                <div class="stat-card warning">
                    <h3><?php echo $warning_count; ?></h3>
                    <p>Warnings</p>
                </div>
                <div class="stat-card error">
                    <h3><?php echo $error_count; ?></h3>
                    <p>Errors</p>
                </div>
                <div class="stat-card critical">
                    <h3><?php echo $critical_count; ?></h3>
                    <p>Critical</p>
                </div>
            </div>
            
            <!-- Filter Form -->
            <form method="GET" action="" class="logs-filters">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="level"><i class="fas fa-filter"></i> Log Level</label>
                        <select id="level" name="level">
                            <option value="all" <?php echo $level_filter === 'all' ? 'selected' : ''; ?>>All Levels</option>
                            <option value="info" <?php echo $level_filter === 'info' ? 'selected' : ''; ?>>Info</option>
                            <option value="warning" <?php echo $level_filter === 'warning' ? 'selected' : ''; ?>>Warning</option>
                            <option value="error" <?php echo $level_filter === 'error' ? 'selected' : ''; ?>>Error</option>
                            <option value="critical" <?php echo $level_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="module"><i class="fas fa-cube"></i> Module</label>
                        <select id="module" name="module">
                            <option value="all" <?php echo $module_filter === 'all' ? 'selected' : ''; ?>>All Modules</option>
                            <?php foreach($modules as $module): ?>
                                <option value="<?php echo $module; ?>" <?php echo $module_filter === $module ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($module); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from"><i class="fas fa-calendar"></i> Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to"><i class="fas fa-calendar"></i> Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>
                
                <div class="filter-group">
                    <label for="search"><i class="fas fa-search"></i> Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search in log messages...">
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="system_logs.php" class="btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                    <div class="auto-refresh-toggle">
                        <input type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh(this.checked)">
                        <label for="autoRefresh">Auto-refresh (30s)</label>
                    </div>
                </div>
            </form>
            
            <!-- Logs Table -->
            <?php if(empty($logs)): ?>
                <div class="empty-logs">
                    <i class="fas fa-clipboard"></i>
                    <h3>No Logs Found</h3>
                    <p>No system logs match your current filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th width="80">Level</th>
                                <th width="120">Date & Time</th>
                                <th width="100">Module</th>
                                <th>Message</th>
                                <th width="120">User</th>
                                <th width="80">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td>
                                    <span class="log-level level-<?php echo $log['level']; ?>">
                                        <?php echo $log['level']; ?>
                                    </span>
                                </td>
                                <td class="log-date">
                                    <?php echo date('M d, H:i', strtotime($log['created_at'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['module']); ?></td>
                                <td class="log-tooltip">
                                    <div class="log-message">
                                        <?php echo htmlspecialchars(substr($log['message'], 0, 100)); ?>
                                        <?php if(strlen($log['message']) > 100): ?>...<?php endif; ?>
                                    </div>
                                    <div class="tooltip-text">
                                        <strong>Full Message:</strong><br>
                                        <?php echo htmlspecialchars($log['message']); ?>
                                        <?php if($log['ip_address']): ?>
                                            <br><br><strong>IP Address:</strong> <?php echo htmlspecialchars($log['ip_address']); ?>
                                        <?php endif; ?>
                                        <?php if($log['user_agent']): ?>
                                            <br><strong>User Agent:</strong> <?php echo htmlspecialchars($log['user_agent']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if($log['username']): ?>
                                        <?php echo htmlspecialchars($log['username']); ?>
                                    <?php else: ?>
                                        <span style="color: var(--gray);">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="action-btn btn-danger" 
                                            onclick="deleteLog(<?php echo $log['id']; ?>)"
                                            title="Delete this log">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&level=<?php echo $level_filter; ?>&module=<?php echo $module_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>"
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Export Options -->
            <div class="export-options">
                <h3>Export Options:</h3>
                <a href="?export=csv&level=<?php echo $level_filter; ?>&module=<?php echo $module_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" 
                   class="btn-primary">
                    <i class="fas fa-file-csv"></i> Export as CSV
                </a>
                <a href="?export=json&level=<?php echo $level_filter; ?>&module=<?php echo $module_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" 
                   class="btn-primary">
                    <i class="fas fa-file-code"></i> Export as JSON
                </a>
                <a href="javascript:void(0)" onclick="printLogs()" class="btn-primary">
                    <i class="fas fa-print"></i> Print Logs
                </a>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
    let autoRefreshInterval;
    
    function toggleAutoRefresh(enabled) {
        if (enabled) {
            autoRefreshInterval = setInterval(() => {
                if (!document.hidden) {
                    window.location.reload();
                }
            }, 30000); // 30 seconds
        } else {
            clearInterval(autoRefreshInterval);
        }
    }
    
    function deleteLog(logId) {
        if (confirm('Delete this log entry?')) {
            fetch('delete_log.php?id=' + logId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete log: ' + data.message);
                    }
                });
        }
    }
    
    function printLogs() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>System Logs Report - Share Learn</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f4f4f4; }
                    .level-info { background-color: #3b82f6; color: white; padding: 2px 6px; border-radius: 3px; }
                    .level-warning { background-color: #f59e0b; color: white; padding: 2px 6px; border-radius: 3px; }
                    .level-error { background-color: #ef4444; color: white; padding: 2px 6px; border-radius: 3px; }
                    .level-critical { background-color: #dc2626; color: white; padding: 2px 6px; border-radius: 3px; }
                </style>
            </head>
            <body>
                <h1>System Logs Report</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                <p>Total Logs: <?php echo $total_results; ?></p>
                <table>
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Date & Time</th>
                            <th>Module</th>
                            <th>Message</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td><span class="level-${<?php echo $log['level']; ?>}"><?php echo $log['level']; ?></span></td>
                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['module']); ?></td>
                            <td><?php echo htmlspecialchars($log['message']); ?></td>
                            <td><?php echo $log['username'] ? htmlspecialchars($log['username']) : 'System'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    // Initialize auto-refresh if checkbox was checked
    document.addEventListener('DOMContentLoaded', function() {
        const autoRefreshCheckbox = document.getElementById('autoRefresh');
        if (autoRefreshCheckbox && autoRefreshCheckbox.checked) {
            toggleAutoRefresh(true);
        }
    });
    </script>
</body>
</html>

<?php
// Export function
function export_logs() {
    global $mysqli, $level_filter, $module_filter, $date_from, $date_to, $search;
    
    // Build export query
    $query = "SELECT l.*, u.username 
              FROM system_logs l 
              LEFT JOIN users u ON l.user_id = u.id 
              WHERE 1=1";
    $params = [];
    
    if ($level_filter !== 'all') {
        $query .= " AND l.level = ?";
        $params[] = $level_filter;
    }
    
    if ($module_filter !== 'all') {
        $query .= " AND l.module = ?";
        $params[] = $module_filter;
    }
    
    if (!empty($date_from)) {
        $query .= " AND DATE(l.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(l.created_at) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $query .= " AND (l.message LIKE ? OR u.username LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY l.created_at DESC";
    
    $stmt = $mysqli->prepare($query);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $logs_res = $stmt->get_result();
    $logs = $logs_res->fetch_all(MYSQLI_ASSOC);
    
    if ($_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, ['ID', 'Timestamp', 'Level', 'Module', 'Message', 'User', 'IP Address', 'User Agent']);
        
        // Add data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['created_at'],
                $log['level'],
                $log['module'],
                $log['message'],
                $log['username'] ?: 'System',
                $log['ip_address'],
                $log['user_agent']
            ]);
        }
        
        fclose($output);
        exit;
        
    } elseif ($_GET['export'] === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.json"');
        
        echo json_encode($logs, JSON_PRETTY_PRINT);
        exit;
    }
}
?>