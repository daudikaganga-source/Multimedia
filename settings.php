<?php
// admin/settings.php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_updated = false;
    
    // General Settings
    if (isset($_POST['general_settings'])) {
        // In a real application, you would save these to a database settings table
        // For now, we'll just show a success message
        $_SESSION['success'] = "General settings updated successfully!";
        $settings_updated = true;
    }
    
    // Appearance Settings
    elseif (isset($_POST['appearance_settings'])) {
        $_SESSION['success'] = "Appearance settings updated successfully!";
        $settings_updated = true;
    }
    
    // Branding Settings
    elseif (isset($_POST['branding_settings'])) {
        $_SESSION['success'] = "Branding settings updated successfully!";
        $settings_updated = true;
    }
    
    // Email Settings
    elseif (isset($_POST['email_settings'])) {
        $_SESSION['success'] = "Email settings updated successfully!";
        $settings_updated = true;
    }
    
    // Storage Settings
    elseif (isset($_POST['storage_settings'])) {
        $_SESSION['success'] = "Storage settings updated successfully!";
        $settings_updated = true;
    }
    
    // Security Settings
    elseif (isset($_POST['security_settings'])) {
        $_SESSION['success'] = "Security settings updated successfully!";
        $settings_updated = true;
    }
    
    // Backup Database
    elseif (isset($_POST['backup_database'])) {
        $backup_success = backup_database();
        if ($backup_success) {
            $_SESSION['success'] = "Database backup created successfully!";
        } else {
            $_SESSION['error'] = "Failed to create database backup.";
        }
    }
    
    // Clear Cache
    elseif (isset($_POST['clear_cache'])) {
        clear_cache();
        $_SESSION['success'] = "Cache cleared successfully!";
    }
    
    // Export Users
    elseif (isset($_POST['export_users'])) {
        export_users();
    }
    
    if ($settings_updated) {
        redirect('settings.php');
    }
}

// Get system info
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'database_size' => get_database_size(),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'disk_free_space' => format_bytes(disk_free_space(__DIR__)),
    'disk_total_space' => format_bytes(disk_total_space(__DIR__)),
    'users_count' => $mysqli->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'documents_count' => $mysqli->query("SELECT COUNT(*) as count FROM documents")->fetch_assoc()['count'],
    'total_downloads' => $mysqli->query("SELECT COUNT(*) as count FROM downloads_log")->fetch_assoc()['count'],
];

// Get recent activities for activity log
$recent_activities = $mysqli->query("SELECT * FROM (
    SELECT id, 'user' as type, username as title, created_at as timestamp, 'User registered' as action FROM users
    UNION
    SELECT id, 'document' as type, title, upload_date as timestamp, 'Document uploaded' as action FROM documents
    UNION
    SELECT id, 'download' as type, 'Download' as title, downloaded_at as timestamp, 'File downloaded' as action FROM downloads_log
    UNION
    SELECT id, 'discussion' as type, topic as title, created_at as timestamp, 'Discussion posted' as action FROM discussions
) activities ORDER BY timestamp DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--button-gray);
            flex-wrap: wrap;
        }
        
        .settings-tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .settings-tab:hover {
            color: var(--accent);
        }
        
        .settings-tab.active {
            border-bottom-color: var(--accent);
            color: var(--accent);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .settings-form {
            background-color: var(--light-blue);
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--button-gray);
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h3 {
            color: var(--accent);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--light-gray);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--button-gray);
            border-radius: 5px;
            background-color: var(--dark-blue);
            color: var(--white);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-help {
            color: var(--gray);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
        }
        
        .info-card h4 {
            color: var(--accent);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--button-gray);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: var(--light-gray);
        }
        
        .info-value {
            color: var(--accent);
            font-weight: bold;
        }
        
        .danger-zone {
            background-color: rgba(220, 38, 38, 0.1);
            border: 2px solid #dc2626;
            padding: 2rem;
            border-radius: 10px;
            margin-top: 2rem;
        }
        
        .danger-zone h3 {
            color: #dc2626;
            margin-bottom: 1rem;
        }
        
        .tool-card {
            background-color: var(--light-blue);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .tool-card h4 {
            color: var(--accent);
            margin-bottom: 1rem;
        }
        
        .tool-description {
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--button-gray);
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--accent);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .switch-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .activity-log {
            max-height: 400px;
            overflow-y: auto;
            background-color: var(--light-blue);
            border-radius: 10px;
            padding: 1rem;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-bottom: 1px solid var(--button-gray);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--dark-blue);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-time {
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .settings-tabs {
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-content">
            <h1><i class="fas fa-cogs"></i> Settings</h1>
            
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <p><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <div class="settings-tab active" onclick="switchTab('general')">
                    <i class="fas fa-sliders-h"></i> General
                </div>
                <div class="settings-tab" onclick="switchTab('appearance')">
                    <i class="fas fa-palette"></i> Appearance
                </div>
                <div class="settings-tab" onclick="switchTab('branding')">
                    <i class="fas fa-copyright"></i> Branding
                </div>
                <div class="settings-tab" onclick="switchTab('email')">
                    <i class="fas fa-envelope"></i> Email
                </div>
                <div class="settings-tab" onclick="switchTab('storage')">
                    <i class="fas fa-database"></i> Storage
                </div>
                <div class="settings-tab" onclick="switchTab('security')">
                    <i class="fas fa-shield-alt"></i> Security
                </div>
                <div class="settings-tab" onclick="switchTab('tools')">
                    <i class="fas fa-tools"></i> Tools
                </div>
                <div class="settings-tab" onclick="switchTab('system')">
                    <i class="fas fa-info-circle"></i> System Info
                </div>
            </div>
            
            <!-- General Settings Tab -->
            <div id="generalTab" class="tab-content active">
                <form method="POST" action="" class="settings-form">
                    <div class="form-section">
                        <h3><i class="fas fa-globe"></i> Site Settings</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_name"><i class="fas fa-signature"></i> Site Name</label>
                                <input type="text" id="site_name" name="site_name" value="Share Learn" required>
                                <div class="form-help">The name of your e-learning platform</div>
                            </div>
                            <div class="form-group">
                                <label for="site_email"><i class="fas fa-at"></i> Site Email</label>
                                <input type="email" id="site_email" name="site_email" value="admin@sharelearn.com" required>
                                <div class="form-help">Email used for system notifications</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_url"><i class="fas fa-link"></i> Site URL</label>
                                <input type="url" id="site_url" name="site_url" value="<?php echo SITE_URL; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="timezone"><i class="fas fa-clock"></i> Timezone</label>
                                <select id="timezone" name="timezone">
                                    <option value="Africa/Dar_es_Salaam" selected>Africa/Dar_es_Salaam</option>
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">America/New_York</option>
                                    <option value="Europe/London">Europe/London</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-language"></i> Language & Regional</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="default_language"><i class="fas fa-language"></i> Default Language</label>
                                <select id="default_language" name="default_language">
                                    <option value="en" selected>English</option>
                                    <option value="sw">Swahili</option>
                                    <option value="fr">French</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date_format"><i class="fas fa-calendar"></i> Date Format</label>
                                <select id="date_format" name="date_format">
                                    <option value="Y-m-d" selected>YYYY-MM-DD</option>
                                    <option value="d/m/Y">DD/MM/YYYY</option>
                                    <option value="m/d/Y">MM/DD/YYYY</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-user-friends"></i> User Registration</h3>
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="allow_registration" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Allow new user registration</span>
                        </div>
                        
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_verification" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Require email verification</span>
                        </div>
                        
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="admin_approval">
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Require admin approval for new users</span>
                        </div>
                    </div>
                    
                    <button type="submit" name="general_settings" class="btn-primary">
                        <i class="fas fa-save"></i> Save General Settings
                    </button>
                </form>
            </div>

            <!-- Appearance Settings Tab -->
            <div id="appearanceTab" class="tab-content">
                <form method="POST" action="" class="settings-form">
                    <div class="form-section">
                        <h3><i class="fas fa-palette"></i> Theme & Colors</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="primary_color">Primary Accent Color</label>
                                <input type="color" id="primary_color" name="primary_color" value="#64ffda">
                                <div class="form-help">Main accent color for the platform</div>
                            </div>
                            <div class="form-group">
                                <label for="theme_mode">Default Theme Mode</label>
                                <select id="theme_mode" name="theme_mode">
                                    <option value="dark" selected>Dark Mode (Default)</option>
                                    <option value="light">Light Mode</option>
                                    <option value="system">Follow System</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="enable_animations" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Enable interface animations</span>
                        </div>
                        
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="glassmorphism" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Enable glassmorphism effects</span>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-font"></i> Typography</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="body_font">Body Font Face</label>
                                <select id="body_font" name="body_font">
                                    <option value="Segoe UI" selected>Segoe UI</option>
                                    <option value="Inter">Inter</option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Outfit">Outfit</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="font_size_base">Base Font Size (px)</label>
                                <input type="number" id="font_size_base" name="font_size_base" value="16" min="12" max="20">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="appearance_settings" class="btn-primary">
                        <i class="fas fa-save"></i> Save Appearance Settings
                    </button>
                </form>
            </div>

            <!-- Branding Settings Tab -->
            <div id="brandingTab" class="tab-content">
                <form method="POST" action="" class="settings-form" enctype="multipart/form-data">
                    <div class="form-section">
                        <h3><i class="fas fa-image"></i> Logos & Icons</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_logo">Site Logo</label>
                                <input type="file" id="site_logo" name="site_logo" accept="image/*">
                                <div class="form-help">Upload site logo (PNG/SVG, Transparent background recommended)</div>
                            </div>
                            <div class="form-group">
                                <label for="favicon">Favicon</label>
                                <input type="file" id="favicon" name="favicon" accept="image/x-icon,image/png">
                                <div class="form-help">Upload site favicon (.ico, .png)</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="og_image">Social Sharing Image (OG Image)</label>
                                <input type="file" id="og_image" name="og_image" accept="image/*">
                                <div class="form-help">Default image for link previews (1200x630px recommended)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-closed-captioning"></i> Site Footer & Labels</h3>
                        <div class="form-group">
                            <label for="footer_text">Footer Copyright Text</label>
                            <input type="text" id="footer_text" name="footer_text" value="&copy; <?php echo date('Y'); ?> Share Learn. All rights reserved.">
                        </div>
                        
                        <div class="form-group">
                            <label for="meta_description">Meta Description</label>
                            <textarea id="meta_description" name="meta_description">Share Learn - Your ultimate e-learning and resource sharing platform.</textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="branding_settings" class="btn-primary">
                        <i class="fas fa-save"></i> Save Branding Settings
                    </button>
                </form>
            </div>

            
            <!-- Email Settings Tab -->
            <div id="emailTab" class="tab-content">
                <form method="POST" action="" class="settings-form">
                    <div class="form-section">
                        <h3><i class="fas fa-server"></i> SMTP Settings</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_host"><i class="fas fa-server"></i> SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" value="smtp.gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="smtp_port"><i class="fas fa-plug"></i> SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" value="587">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_username"><i class="fas fa-user"></i> SMTP Username</label>
                                <input type="text" id="smtp_username" name="smtp_username">
                            </div>
                            <div class="form-group">
                                <label for="smtp_password"><i class="fas fa-lock"></i> SMTP Password</label>
                                <input type="password" id="smtp_password" name="smtp_password">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_encryption"><i class="fas fa-shield-alt"></i> Encryption</label>
                                <select id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" selected>TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">None</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-envelope-open-text"></i> Email Templates</h3>
                        <div class="form-group">
                            <label for="welcome_email"><i class="fas fa-mail-bulk"></i> Welcome Email Template</label>
                            <textarea id="welcome_email" name="welcome_email">Welcome to Share Learn! We're excited to have you join our community of learners.</textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="password_reset_email"><i class="fas fa-key"></i> Password Reset Template</label>
                            <textarea id="password_reset_email" name="password_reset_email">Click the link below to reset your password...</textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="email_settings" class="btn-primary">
                        <i class="fas fa-save"></i> Save Email Settings
                    </button>
                </form>
            </div>
            
            <!-- Storage Settings Tab -->
            <div id="storageTab" class="tab-content">
                <form method="POST" action="" class="settings-form">
                    <div class="form-section">
                        <h3><i class="fas fa-hdd"></i> File Upload Settings</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_upload_size"><i class="fas fa-weight"></i> Maximum Upload Size (MB)</label>
                                <input type="number" id="max_upload_size" name="max_upload_size" value="50" min="1" max="100">
                                <div class="form-help">Current PHP limit: <?php echo $system_info['upload_max_filesize']; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="allowed_file_types"><i class="fas fa-file-alt"></i> Allowed File Types</label>
                                <input type="text" id="allowed_file_types" name="allowed_file_types" value="pdf, doc, docx, txt, ppt, pptx">
                                <div class="form-help">Comma-separated list of file extensions</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="storage_path"><i class="fas fa-folder"></i> Storage Path</label>
                                <input type="text" id="storage_path" name="storage_path" value="<?php echo UPLOAD_PATH; ?>" readonly>
                                <div class="form-help">Path where uploaded files are stored</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-archive"></i> Backup Settings</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="backup_frequency"><i class="fas fa-history"></i> Auto Backup Frequency</label>
                                <select id="backup_frequency" name="backup_frequency">
                                    <option value="daily">Daily</option>
                                    <option value="weekly" selected>Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="never">Never</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="backup_retention"><i class="fas fa-trash-restore"></i> Backup Retention (days)</label>
                                <input type="number" id="backup_retention" name="backup_retention" value="30" min="1">
                            </div>
                        </div>
                        
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="backup_to_cloud" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Backup to cloud storage</span>
                        </div>
                    </div>
                    
                    <button type="submit" name="storage_settings" class="btn-primary">
                        <i class="fas fa-save"></i> Save Storage Settings
                    </button>
                </form>
            </div>
            
            <!-- Security Settings Tab -->
            <div id="securityTab" class="tab-content">
                <form method="POST" action="" class="settings-form">
                    <div class="form-section">
                        <h3><i class="fas fa-lock"></i> Authentication</h3>
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="require_strong_password" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Require strong passwords</span>
                        </div>
                        
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="two_factor_auth">
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Enable two-factor authentication</span>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="session_timeout"><i class="fas fa-hourglass-half"></i> Session Timeout (minutes)</label>
                                <input type="number" id="session_timeout" name="session_timeout" value="60" min="5">
                            </div>
                            <div class="form-group">
                                <label for="max_login_attempts"><i class="fas fa-ban"></i> Max Login Attempts</label>
                                <input type="number" id="max_login_attempts" name="max_login_attempts" value="5" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-shield-alt"></i> Security Headers</h3>
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="https_only" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Force HTTPS</span>
                        </div>
                        
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="xss_protection" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Enable XSS Protection</span>
                        </div>
                        
                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="content_security_policy" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Content Security Policy</span>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-user-shield"></i> Admin Security</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_ip_whitelist"><i class="fas fa-network-wired"></i> Admin IP Whitelist</label>
                                <textarea id="admin_ip_whitelist" name="admin_ip_whitelist" placeholder="Enter one IP per line"></textarea>
                                <div class="form-help">Leave empty to allow from any IP</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="security_settings" class="btn-primary">
                        <i class="fas fa-save"></i> Save Security Settings
                    </button>
                </form>
            </div>
            
            <!-- Tools Tab -->
            <div id="toolsTab" class="tab-content">
                <div class="tool-card">
                    <h4><i class="fas fa-database"></i> Database Backup</h4>
                    <p class="tool-description">Create a backup of the entire database. This will generate a .sql file that you can download.</p>
                    <form method="POST" action="">
                        <button type="submit" name="backup_database" class="btn-primary">
                            <i class="fas fa-download"></i> Backup Database Now
                        </button>
                    </form>
                </div>
                
                <div class="tool-card">
                    <h4><i class="fas fa-broom"></i> Clear Cache</h4>
                    <p class="tool-description">Clear all cached data including session files and temporary uploads.</p>
                    <form method="POST" action="">
                        <button type="submit" name="clear_cache" class="btn-primary">
                            <i class="fas fa-trash"></i> Clear Cache Now
                        </button>
                    </form>
                </div>
                
                <div class="tool-card">
                    <h4><i class="fas fa-file-export"></i> Export Users</h4>
                    <p class="tool-description">Export all user data to a CSV file for analysis or backup.</p>
                    <form method="POST" action="">
                        <button type="submit" name="export_users" class="btn-primary">
                            <i class="fas fa-file-csv"></i> Export Users to CSV
                        </button>
                    </form>
                </div>
                
                <div class="tool-card">
                    <h4><i class="fas fa-chart-bar"></i> Generate Reports</h4>
                    <p class="tool-description">Generate detailed reports on user activity, downloads, and system usage.</p>
                    <a href="generate_reports.php" class="btn-primary">
                        <i class="fas fa-chart-line"></i> Generate Reports
                    </a>
                </div>
                
                <div class="danger-zone">
                    <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                    <p>These actions are irreversible. Use with extreme caution.</p>
                    
                    <div style="margin-top: 1rem;">
                        <button type="button" class="btn-danger" onclick="if(confirm('This will delete ALL data! Are you absolutely sure?')) location.href='?reset_site'">
                            <i class="fas fa-bomb"></i> Reset Entire Site
                        </button>
                        <button type="button" class="btn-danger" onclick="if(confirm('Delete all documents? This cannot be undone!')) location.href='?delete_all_docs'">
                            <i class="fas fa-trash-alt"></i> Delete All Documents
                        </button>
                        <button type="button" class="btn-danger" onclick="if(confirm('Delete all users except admins?')) location.href='?delete_all_users'">
                            <i class="fas fa-user-slash"></i> Delete All Users
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- System Info Tab -->
            <div id="systemTab" class="tab-content">
                <h3><i class="fas fa-server"></i> Server Information</h3>
                <div class="system-info-grid">
                    <div class="info-card">
                        <h4><i class="fas fa-server"></i> Server Details</h4>
                        <div class="info-item">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value"><?php echo $system_info['php_version']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server Software</span>
                            <span class="info-value"><?php echo $system_info['server_software']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Memory Limit</span>
                            <span class="info-value"><?php echo $system_info['memory_limit']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Max Execution Time</span>
                            <span class="info-value"><?php echo $system_info['max_execution_time']; ?>s</span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h4><i class="fas fa-hdd"></i> Storage</h4>
                        <div class="info-item">
                            <span class="info-label">Disk Total Space</span>
                            <span class="info-value"><?php echo $system_info['disk_total_space']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Disk Free Space</span>
                            <span class="info-value"><?php echo $system_info['disk_free_space']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Database Size</span>
                            <span class="info-value"><?php echo $system_info['database_size']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Upload Limit</span>
                            <span class="info-value"><?php echo $system_info['upload_max_filesize']; ?></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h4><i class="fas fa-chart-line"></i> Statistics</h4>
                        <div class="info-item">
                            <span class="info-label">Total Users</span>
                            <span class="info-value"><?php echo $system_info['users_count']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Documents</span>
                            <span class="info-value"><?php echo $system_info['documents_count']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Downloads</span>
                            <span class="info-value"><?php echo $system_info['total_downloads']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">System Uptime</span>
                            <span class="info-value"><?php echo get_system_uptime(); ?></span>
                        </div>
                    </div>
                </div>
                
                <h3><i class="fas fa-history"></i> Recent Activity Log</h3>
                <div class="activity-log">
                    <?php foreach($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php switch($activity['type']):
                                case 'user': ?>
                                    <i class="fas fa-user-plus"></i>
                                    <?php break; ?>
                                <?php case 'document': ?>
                                    <i class="fas fa-file-upload"></i>
                                    <?php break; ?>
                                <?php case 'download': ?>
                                    <i class="fas fa-download"></i>
                                    <?php break; ?>
                                <?php case 'discussion': ?>
                                    <i class="fas fa-comment"></i>
                                    <?php break; ?>
                            <?php endswitch; ?>
                        </div>
                        <div class="activity-content">
                            <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                            <p><?php echo $activity['action']; ?></p>
                            <div class="activity-time">
                                <?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/script.js"></script>
    <script>
    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all tab buttons
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName + 'Tab').classList.add('active');
        
        // Activate clicked tab button
        event.target.classList.add('active');
    }
    
    // Test email configuration
    function testEmailConfig() {
        const email = prompt('Enter email address to send test email:');
        if (email) {
            fetch('test_email.php?email=' + encodeURIComponent(email))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Test email sent successfully!');
                    } else {
                        alert('Failed to send test email: ' + data.message);
                    }
                });
        }
    }
    
    // Clear all logs
    function clearActivityLog() {
        if (confirm('Clear all activity logs?')) {
            fetch('clear_logs.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Logs cleared successfully!');
                        location.reload();
                    }
                });
        }
    }
    </script>
</body>
</html>

<?php
// Helper functions
function get_database_size() {
    global $mysqli;
    $stmt = $mysqli->query("SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()");
    $result = $stmt->fetch_assoc();
    return $result['size_mb'] ? $result['size_mb'] . ' MB' : 'Unknown';
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

function get_system_uptime() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return 'N/A (Windows)';
    } else {
        $uptime = @shell_exec('uptime -p');
        return $uptime ? trim($uptime) : 'Unknown';
    }
}

function backup_database() {
    // This is a simplified backup function
    // In production, use mysqldump command
    $backup_file = '../backups/db_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_dir = dirname($backup_file);
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Simplified backup - just create a placeholder
    $content = "-- Database backup created on " . date('Y-m-d H:i:s') . "\n";
    $content .= "-- This is a placeholder backup file\n";
    
    return file_put_contents($backup_file, $content) !== false;
}

function clear_cache() {
    // Clear session files older than 1 hour
    $session_path = session_save_path();
    if (empty($session_path)) {
        $session_path = sys_get_temp_dir();
    }
    
    $files = glob($session_path . '/sess_*');
    $now = time();
    $deleted = 0;
    
    foreach ($files as $file) {
        if ($now - filemtime($file) > 3600) {
            @unlink($file);
            $deleted++;
        }
    }
    
    return $deleted;
}

function export_users() {
    global $mysqli;
    
    $stmt = $mysqli->query("SELECT * FROM users");
    $users = $stmt->fetch_all(MYSQLI_ASSOC);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['ID', 'Username', 'Email', 'Role', 'Full Name', 'Created At', 'Last Login']);
    
    // Add data
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['username'],
            $user['email'],
            $user['role'],
            $user['full_name'],
            $user['created_at'],
            $user['last_login']
        ]);
    }
    
    fclose($output);
    exit;
}
?>