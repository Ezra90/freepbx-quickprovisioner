<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db;
global $amp_conf;

$logger = FreePBX::create()->Logger;
$logger->log('Starting HH Quick Provisioner uninstall', 'INFO');

// --- 1. Drop Database Table ---
try {
    $db->query("DROP TABLE IF EXISTS `quickprovisioner_devices`");
    $logger->log('Dropped quickprovisioner_devices table', 'INFO');
} catch(Exception $e) {
    $logger->log('Error dropping quickprovisioner_devices table: ' . $e->getMessage(), 'ERROR');
}

// --- 2. Remove uploads directory and contents ---
$uploads_dir = __DIR__ . '/assets/uploads';
if (is_dir($uploads_dir)) {
    try {
        // Remove all files in uploads directory
        $files = glob($uploads_dir . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $logger->log('Removed file: ' . basename($file), 'INFO');
                }
            }
        }
        
        // Remove .htaccess if exists
        $htaccess = $uploads_dir . '/.htaccess';
        if (file_exists($htaccess)) {
            unlink($htaccess);
            $logger->log('Removed uploads/.htaccess', 'INFO');
        }
        
        // Remove the uploads directory itself
        rmdir($uploads_dir);
        $logger->log('Removed uploads directory', 'INFO');
    } catch(Exception $e) {
        $logger->log('Error removing uploads directory: ' . $e->getMessage(), 'ERROR');
    }
}

// --- 3. Remove template files ---
$templates_dir = __DIR__ . '/templates';
if (is_dir($templates_dir)) {
    try {
        // Remove all JSON template files
        $templates = glob($templates_dir . '/*.json');
        if ($templates) {
            foreach ($templates as $template) {
                if (is_file($template)) {
                    unlink($template);
                    $logger->log('Removed template: ' . basename($template), 'INFO');
                }
            }
        }
        
        // Remove .htaccess if exists
        $htaccess = $templates_dir . '/.htaccess';
        if (file_exists($htaccess)) {
            unlink($htaccess);
            $logger->log('Removed templates/.htaccess', 'INFO');
        }
        
        // Remove the templates directory itself
        rmdir($templates_dir);
        $logger->log('Removed templates directory', 'INFO');
    } catch(Exception $e) {
        $logger->log('Error removing templates directory: ' . $e->getMessage(), 'ERROR');
    }
}

// --- 4. Remove assets directory if empty ---
$assets_dir = __DIR__ . '/assets';
if (is_dir($assets_dir)) {
    try {
        // Remove any remaining files in assets (like downloaded background images)
        $files = glob($assets_dir . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $logger->log('Removed asset file: ' . basename($file), 'INFO');
                }
            }
        }
        
        // Try to remove the assets directory if it's now empty
        $remaining = glob($assets_dir . '/*');
        if (empty($remaining)) {
            rmdir($assets_dir);
            $logger->log('Removed assets directory', 'INFO');
        } else {
            $logger->log('Assets directory not empty, keeping it', 'WARNING');
        }
    } catch(Exception $e) {
        $logger->log('Error removing assets directory: ' . $e->getMessage(), 'ERROR');
    }
}

// --- 5. Final ownership and permission cleanup ---
// Ensure any remaining files are properly owned (consistent with install.php pattern)
shell_exec("sudo chown -R asterisk:asterisk " . escapeshellarg(__DIR__));
$logger->log('Fixed ownership for remaining module files', 'INFO');

$logger->log('HH Quick Provisioner uninstall completed', 'INFO');
?>
