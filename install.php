<?php
/**
 * Quick Provisioner Installation Script
 * Handles initial setup and configuration
 */

// Check if already installed
$config_file = dirname(__FILE__) . '/config.php';
if (file_exists($config_file) && filesize($config_file) > 100) {
    die("Quick Provisioner is already installed. Delete config.php to reinstall.");
}

// Create necessary directories
$dirs = array(
    'logs' => dirname(__FILE__) . '/logs',
    'cache' => dirname(__FILE__) . '/cache',
    'uploads' => dirname(__FILE__) . '/uploads',
    'data' => dirname(__FILE__) . '/data'
);

foreach ($dirs as $dir_name => $dir_path) {
    if (!is_dir($dir_path)) {
        if (!mkdir($dir_path, 0775, true)) {
            die("Failed to create $dir_name directory");
        }
    }
}

// Create config.php
$config_content = '<?php
// Quick Provisioner Configuration
// Generated on ' . date('Y-m-d H:i:s') . '

// Database Configuration
$config = array(
    "db_host" => "localhost",
    "db_user" => "root",
    "db_pass" => "",
    "db_name" => "quickprovisioner",
    "app_path" => "' . dirname(__FILE__) . '",
    "base_url" => "http://localhost/quickprovisioner"
);
?>';

if (!file_put_contents($config_file, $config_content)) {
    die("Failed to create config.php");
}

// Set proper permissions on config.php
chmod($config_file, 0644);

// Create .htaccess for logs directory
$htaccess_logs = dirname(__FILE__) . '/logs/.htaccess';
$htaccess_content = 'Deny from all' . PHP_EOL;
if (!file_put_contents($htaccess_logs, $htaccess_content)) {
    die("Failed to create logs/.htaccess");
}

// Create .htaccess for cache directory
$htaccess_cache = dirname(__FILE__) . '/cache/.htaccess';
if (!file_put_contents($htaccess_cache, $htaccess_content)) {
    die("Failed to create cache/.htaccess");
}

// Create .htaccess for uploads directory
$htaccess_uploads = dirname(__FILE__) . '/uploads/.htaccess';
$upload_htaccess = 'Deny from all' . PHP_EOL . 'AddType text/plain .*' . PHP_EOL;
if (!file_put_contents($htaccess_uploads, $upload_htaccess)) {
    die("Failed to create uploads/.htaccess");
}

// Set proper permissions on directories
chmod(dirname(__FILE__) . '/logs', 0775);
chmod(dirname(__FILE__) . '/cache', 0775);
chmod(dirname(__FILE__) . '/uploads', 0775);
chmod(dirname(__FILE__) . '/data', 0775);

// Create database tables
require_once($config_file);

$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Create tables
$sql_files = array(
    'devices' => "CREATE TABLE IF NOT EXISTS devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_name VARCHAR(255) NOT NULL,
        device_type VARCHAR(100),
        mac_address VARCHAR(17) UNIQUE,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'configurations' => "CREATE TABLE IF NOT EXISTS configurations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT,
        config_data LONGTEXT,
        version INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (device_id) REFERENCES devices(id)
    )",
    'provisioning_logs' => "CREATE TABLE IF NOT EXISTS provisioning_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT,
        log_message TEXT,
        status VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (device_id) REFERENCES devices(id)
    )"
);

foreach ($sql_files as $table => $sql) {
    if (!$conn->query($sql)) {
        die("Error creating $table table: " . $conn->error);
    }
}

// Create initial admin user
$admin_username = 'admin';
$admin_password = password_hash('admin123', PASSWORD_BCRYPT);

$sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE password=VALUES(password)";
$stmt = $conn->prepare($sql);
$role = 'admin';
$stmt->bind_param("sss", $admin_username, $admin_password, $role);

if (!$stmt->execute()) {
    die("Error creating admin user: " . $stmt->error);
}

// Create session directory
$session_dir = dirname(__FILE__) . '/sessions';
if (!is_dir($session_dir)) {
    if (!mkdir($session_dir, 0775, true)) {
        die("Failed to create sessions directory");
    }
}

// Set proper permissions on session directory
chmod($session_dir, 0775);

// Create index.php if it doesn't exist
$index_file = dirname(__FILE__) . '/index.php';
$index_content = '<?php
session_start();
require_once("config.php");
require_once("functions.php");

// Redirect to login if not authenticated
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Load main dashboard
require_once("dashboard.php");
?>';

if (!file_exists($index_file)) {
    if (!file_put_contents($index_file, $index_content)) {
        die("Failed to create index.php");
    }
    chmod($index_file, 0644);
}

// Create templates directory
$templates_dir = dirname(__FILE__) . '/templates';
if (!is_dir($templates_dir)) {
    if (!mkdir($templates_dir, 0775, true)) {
        die("Failed to create templates directory");
    }
}

// Set proper permissions on templates directory
chmod($templates_dir, 0775);

// Verify installations
$required_files = array(
    'config.php',
    'index.php'
);

$missing_files = array();
foreach ($required_files as $file) {
    $file_path = dirname(__FILE__) . '/' . $file;
    if (!file_exists($file_path)) {
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    die("Installation failed. Missing files: " . implode(", ", $missing_files));
}

// All done
echo "Installation completed successfully!";
echo "You can now access the Quick Provisioner at your configured base URL.";
echo "Default credentials - Username: admin, Password: admin123";
echo "Please change the password immediately after first login.";
?>