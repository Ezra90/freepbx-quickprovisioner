<?php
/**
 * HH Quick Provisioner - Installation Script
 *
 * This file contains FreePBX module lifecycle hooks for installation.
 * Refactored for procedural execution as required by FreePBX.
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db;
global $amp_conf;

// --- 1. Database Installation ---
try {
    // Create the main devices table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `quickprovisioner_devices` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `mac` VARCHAR(17) NOT NULL COMMENT 'Device MAC address',
        `model` VARCHAR(50) NOT NULL COMMENT 'Device model',
        `extension` VARCHAR(20) NOT NULL COMMENT 'Associated extension',
        `wallpaper` VARCHAR(255) DEFAULT NULL COMMENT 'Wallpaper filename',
        `wallpaper_mode` VARCHAR(20) DEFAULT 'crop' COMMENT 'Wallpaper display mode',
        `security_pin` VARCHAR(20) DEFAULT NULL COMMENT 'Device security PIN',
        `keys_json` TEXT DEFAULT NULL COMMENT 'JSON data for programmable keys',
        `contacts_json` TEXT DEFAULT NULL COMMENT 'JSON data for contacts',
        `custom_options_json` TEXT DEFAULT NULL COMMENT 'JSON data for custom options',
        `custom_template_override` TEXT DEFAULT NULL COMMENT 'Custom template override',
        `prov_username` VARCHAR(50) DEFAULT NULL COMMENT 'Provisioning auth username',
        `prov_password` VARCHAR(100) DEFAULT NULL COMMENT 'Provisioning auth password',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `mac` (`mac`),
        KEY `extension` (`extension`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quick Provisioner device configurations'";

    $db->query($sql);

    // Add provisioning auth columns if missing (Upgrade logic included here)
    try {
        $db->query("ALTER TABLE quickprovisioner_devices ADD COLUMN IF NOT EXISTS prov_username VARCHAR(50) DEFAULT NULL");
        $db->query("ALTER TABLE quickprovisioner_devices ADD COLUMN IF NOT EXISTS prov_password VARCHAR(100) DEFAULT NULL");
    } catch (Exception $e) {
        // Columns may already exist
    }

    if (class_exists('FreePBX')) {
        FreePBX::create()->Logger->log("Quick Provisioner: Database table checked/created successfully");
    }

} catch (Exception $e) {
    if (class_exists('FreePBX')) {
        FreePBX::create()->Logger->log("Quick Provisioner Install Error: " . $e->getMessage());
    }
    error_log("Quick Provisioner Install Error: " . $e->getMessage());
}

// --- 2. Directory & Permission Setup ---
$module_path = __DIR__;
$directories = [
    $module_path . '/assets',
    $module_path . '/assets/uploads',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Quick Provisioner: Failed to create directory: $dir");
            if (class_exists('FreePBX')) {
                FreePBX::create()->Logger->log("Quick Provisioner: Failed to create directory: $dir");
            }
            continue;
        }
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner: Created directory $dir");
        }
    }
}

// Ensure permissions are correct on uploads directory
// We rely on the process user (asterisk) owning the files it creates.
// Explicit chown is removed as it fails on RPi without root.
$uploads_dir = $module_path . '/assets/uploads';
if (is_dir($uploads_dir)) {
    @chmod($uploads_dir, 0775);

    // Create .htaccess for uploads directory to prevent direct execution
    $htaccess_path = $uploads_dir . '/.htaccess';
    if (!file_exists($htaccess_path)) {
        $htaccess_content = "# Quick Provisioner - Protect uploads directory\n";
        $htaccess_content .= "php_flag engine off\n";
        $htaccess_content .= "Options -ExecCGI -Indexes\n";
        $htaccess_content .= "AddType text/plain .php .php3 .phtml .pht\n";
        @file_put_contents($htaccess_path, $htaccess_content);
    }
}

if (class_exists('FreePBX')) {
    FreePBX::create()->Logger->log("Quick Provisioner: Module installation/check completed");
}
?>