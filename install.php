<?php
/**
 * HH Quick Provisioner - Installation Script
 * 
 * This file contains FreePBX module lifecycle hooks for installation,
 * uninstallation, enabling, and disabling the Quick Provisioner module.
 * 
 * @package HH Quick Provisioner
 * @version 2.1.0
 * @license GPLv3
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/**
 * Install hook - Creates database tables during initial installation
 * Called when module is first installed
 * 
 * @global object $db Database connection object
 * @return bool True on success, false on failure
 */
function install() {
    global $db;
    
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
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `mac` (`mac`),
            KEY `extension` (`extension`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quick Provisioner device configurations'";
        
        $db->query($sql);
        
        // Log successful table creation
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner: Database table created successfully");
        }
        
        return true;
        
    } catch (Exception $e) {
        // Log error if logging is available
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner Install Error: " . $e->getMessage());
        }
        error_log("Quick Provisioner Install Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Uninstall hook - Drops database tables during module removal
 * Called when module is being uninstalled
 * 
 * @global object $db Database connection object
 * @return bool True on success, false on failure
 */
function uninstall() {
    global $db;
    
    try {
        // Drop the devices table
        $sql = "DROP TABLE IF EXISTS `quickprovisioner_devices`";
        $db->query($sql);
        
        // Log successful table removal
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner: Database table removed successfully");
        }
        
        return true;
        
    } catch (Exception $e) {
        // Log error if logging is available
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner Uninstall Error: " . $e->getMessage());
        }
        error_log("Quick Provisioner Uninstall Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Post-installation hook - Sets up directories and permissions
 * Called after module installation is complete
 * 
 * @return bool True on success, false on failure
 */
function install_module() {
    $module_path = __DIR__;
    $success = true;
    
    try {
        // Define directories that need to be created
        $directories = [
            $module_path . '/assets',
            $module_path . '/assets/uploads',
        ];
        
        // Create necessary directories
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    error_log("Quick Provisioner: Failed to create directory: $dir");
                    $success = false;
                    continue;
                }
                
                // Set proper ownership to asterisk:asterisk using PHP's chown
                // Fallback to exec if chown() fails (permission issue)
                if (!@chown($dir, 'asterisk')) {
                    // Try using exec as fallback
                    exec("chown asterisk:asterisk " . escapeshellarg($dir) . " 2>&1", $output, $return_code);
                    if ($return_code !== 0 && class_exists('FreePBX')) {
                        FreePBX::create()->Logger->log("Quick Provisioner: Warning - Could not set ownership on $dir");
                    }
                } else {
                    @chgrp($dir, 'asterisk');
                }
                
                if (class_exists('FreePBX')) {
                    FreePBX::create()->Logger->log("Quick Provisioner: Created directory $dir");
                }
            }
        }
        
        // Recursively set permissions on module directory
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($module_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            // Validate path is within module directory to prevent directory traversal
            $realPath = realpath($item->getPathname());
            $realModulePath = realpath($module_path);
            if ($realPath === false || strpos($realPath, $realModulePath) !== 0) {
                continue;
            }
            
            if ($item->isDir()) {
                @chmod($item->getPathname(), 0755);
                if (!@chown($item->getPathname(), 'asterisk')) {
                    @exec("chown asterisk:asterisk " . escapeshellarg($item->getPathname()) . " 2>&1");
                } else {
                    @chgrp($item->getPathname(), 'asterisk');
                }
            } else {
                @chmod($item->getPathname(), 0644);
                if (!@chown($item->getPathname(), 'asterisk')) {
                    @exec("chown asterisk:asterisk " . escapeshellarg($item->getPathname()) . " 2>&1");
                } else {
                    @chgrp($item->getPathname(), 'asterisk');
                }
            }
        }
        
        // Ensure uploads directory has write permissions for web server
        $uploads_dir = $module_path . '/assets/uploads';
        if (is_dir($uploads_dir)) {
            if (!@chmod($uploads_dir, 0775)) {
                exec("chmod 775 " . escapeshellarg($uploads_dir) . " 2>&1", $output, $return_code);
                if ($return_code !== 0 && class_exists('FreePBX')) {
                    FreePBX::create()->Logger->log("Quick Provisioner: Warning - Could not set permissions on $uploads_dir");
                }
            }
        }
        
        // Create .htaccess for uploads directory to prevent direct execution
        $htaccess_path = $uploads_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "# Quick Provisioner - Protect uploads directory\n";
            $htaccess_content .= "php_flag engine off\n";
            $htaccess_content .= "Options -ExecCGI -Indexes\n";
            $htaccess_content .= "AddType text/plain .php .php3 .phtml .pht\n";
            if (@file_put_contents($htaccess_path, $htaccess_content)) {
                @chmod($htaccess_path, 0644);
                if (!@chown($htaccess_path, 'asterisk')) {
                    @exec("chown asterisk:asterisk " . escapeshellarg($htaccess_path) . " 2>&1");
                } else {
                    @chgrp($htaccess_path, 'asterisk');
                }
            }
        }
        
        if (class_exists('FreePBX')) {
            if ($success) {
                FreePBX::create()->Logger->log("Quick Provisioner: Module installation completed successfully");
            } else {
                FreePBX::create()->Logger->log("Quick Provisioner: Module installation completed with warnings");
            }
        }
        
        return $success;
        
    } catch (Exception $e) {
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner Install Module Error: " . $e->getMessage());
        }
        error_log("Quick Provisioner Install Module Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Pre-uninstallation hook - Cleans up module files and directories
 * Called before module is uninstalled
 * 
 * @return bool True on success, false on failure
 */
function uninstall_module() {
    $module_path = __DIR__;
    
    try {
        // Clean up uploaded assets
        $uploads_dir = $module_path . '/assets/uploads';
        
        if (is_dir($uploads_dir)) {
            // Remove all files in uploads directory
            $files = glob($uploads_dir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            
            // Remove the .htaccess file
            $htaccess_path = $uploads_dir . '/.htaccess';
            if (file_exists($htaccess_path)) {
                @unlink($htaccess_path);
            }
            
            // Remove the uploads directory
            @rmdir($uploads_dir);
        }
        
        // Remove the assets directory if empty
        $assets_dir = $module_path . '/assets';
        if (is_dir($assets_dir)) {
            @rmdir($assets_dir);
        }
        
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner: Module cleanup completed successfully");
        }
        
        return true;
        
    } catch (Exception $e) {
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner Uninstall Module Error: " . $e->getMessage());
        }
        error_log("Quick Provisioner Uninstall Module Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Module upgrade hook - Handles version upgrades
 * Called when module is being upgraded to a new version
 * 
 * @param string $oldversion Previous version number
 * @param string $newversion New version number
 * @return bool True on success, false on failure
 */
function upgrade($oldversion, $newversion) {
    try {
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner: Upgrading from $oldversion to $newversion");
        }
        
        // Run install_module to ensure directories and permissions are correct
        install_module();
        
        return true;
        
    } catch (Exception $e) {
        if (class_exists('FreePBX')) {
            FreePBX::create()->Logger->log("Quick Provisioner Upgrade Error: " . $e->getMessage());
        }
        error_log("Quick Provisioner Upgrade Error: " . $e->getMessage());
        return false;
    }
}