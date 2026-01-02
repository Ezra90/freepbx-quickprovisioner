<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db;
global $amp_conf;

$logger = FreePBX::create()->Logger;
$logger->log('Starting HH Quick Provisioner install', 'INFO');

// --- 1. Devices Table ---
$db->query("CREATE TABLE IF NOT EXISTS `quickprovisioner_devices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `mac` VARCHAR(17) NOT NULL,
    `model` VARCHAR(50) NOT NULL,
    `extension` VARCHAR(20) NOT NULL,
    `wallpaper` VARCHAR(255),
    `wallpaper_mode` VARCHAR(20) DEFAULT 'crop',
    `security_pin` VARCHAR(10),
    `keys_json` LONGTEXT,
    `contacts_json` LONGTEXT,
    `custom_options_json` JSON,
    `custom_template_override` TEXT,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mac_idx` (`mac`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Safe upgrades
try { $db->query("ALTER TABLE quickprovisioner_devices ADD COLUMN wallpaper VARCHAR(255)"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE quickprovisioner_devices ADD COLUMN wallpaper_mode VARCHAR(20) DEFAULT 'crop'"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE quickprovisioner_devices ADD COLUMN security_pin VARCHAR(10)"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE quickprovisioner_devices ADD COLUMN contacts_json LONGTEXT"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE quickprovisioner_devices ADD COLUMN custom_options_json JSON"); } catch(Exception $e) {}
try { $db->query("ALTER TABLE quickprovisioner_devices ADD COLUMN custom_template_override TEXT"); } catch(Exception $e) {}

// Drop old profiles table if exists
try { $db->query("DROP TABLE IF EXISTS `quickprovisioner_profiles`"); } catch(Exception $e) {}

// --- 2. Asset & Templates Directories ---
$assets_dir     = __DIR__ . '/assets';
$uploads_dir    = $assets_dir . '/uploads';
$templates_dir  = __DIR__ . '/templates';

// Create directories with proper permissions early
if (!is_dir($assets_dir))    { mkdir($assets_dir,    0775, true); }
if (!is_dir($uploads_dir))   { mkdir($uploads_dir,   0775, true); }
if (!is_dir($templates_dir)) { mkdir($templates_dir, 0775, true); }

// Set ownership and permissions using PHP functions
// Note: chown/chgrp may fail if not running as appropriate user, but that's acceptable
@chown($assets_dir, 'asterisk');
@chgrp($assets_dir, 'asterisk');
@chown($uploads_dir, 'asterisk');
@chgrp($uploads_dir, 'asterisk');
@chown($templates_dir, 'asterisk');
@chgrp($templates_dir, 'asterisk');

// Create .htaccess files
$htaccess_content = "Deny from all";

$uploads_htaccess = $uploads_dir . '/.htaccess';
if (!file_exists($uploads_htaccess)) {
    file_put_contents($uploads_htaccess, $htaccess_content);
    @chmod($uploads_htaccess, 0644);
    @chown($uploads_htaccess, 'asterisk');
    @chgrp($uploads_htaccess, 'asterisk');
    $logger->log('Created uploads/.htaccess', 'INFO');
}

$templates_htaccess = $templates_dir . '/.htaccess';
if (!file_exists($templates_htaccess)) {
    file_put_contents($templates_htaccess, $htaccess_content);
    @chmod($templates_htaccess, 0644);
    @chown($templates_htaccess, 'asterisk');
    @chgrp($templates_htaccess, 'asterisk');
    $logger->log('Created templates/.htaccess', 'INFO');
}

// --- 3. Helper: Key Generation ---
function generateKeys($count, $layoutType, $params) {
    $keys = [];
    $startX = $params['startX'];
    $startY = $params['startY'];
    $perPage = $params['maxPerPage'] ?? 10;

    if ($layoutType === 'dual_column') {
        $half = ceil($perPage / 2);
        $stepY = $params['stepY'];
        $rightOffset = $params['rightOffset'];

        for ($i = 0; $i < $count; $i++) {
            $page = floor($i / $perPage) + 1;
            $idxOnPage = $i % $perPage;
            $isRight = ($idxOnPage >= $half);
            $idxInCol = $isRight ? $idxOnPage - $half : $idxOnPage;

            $keys[] = [
                'index' => $i + 1,
                'x' => $isRight ? $rightOffset : $startX,
                'y' => $startY + ($idxInCol * $stepY),
                'label_align' => $isRight ? 'right' : 'left',
                'page' => $page,
                'info' => 'Programmable key'
            ];
        }
    } elseif ($layoutType === 'grid') {
        $cols = $params['cols'] ?? 5;
        $stepX = $params['stepX'] ?? 150;
        $stepY = $params['stepY'] ?? 80;

        for ($i = 0; $i < $count; $i++) {
            $page = floor($i / $perPage) + 1;
            $idxOnPage = $i % $perPage;
            $row = floor($idxOnPage / $cols);
            $col = $idxOnPage % $cols;

            $keys[] = [
                'index' => $i + 1,
                'x' => $startX + ($col * $stepX),
                'y' => $startY + ($row * $stepY),
                'label_align' => 'center',
                'page' => $page,
                'info' => 'Grid key'
            ];
        }
    }
    return $keys;
}

// --- 4. Helper: Install Profile ---
function installProfile($data) {
    global $templates_dir, $logger;
    $model = $data['model'];
    $path = $templates_dir . '/' . $model . '.json';

    // For remote image, download directly
    if (!empty($data['visual_editor']['remote_image_url'])) {
        $url = $data['visual_editor']['remote_image_url'];
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $localName = preg_replace('/[^a-zA-Z0-9_-]/', '', $model) . '.' . $ext;
        $localPath = __DIR__ . '/assets/' . $localName;

        if (!file_exists($localPath)) {
            $opts = ["http" => ["header" => "User-Agent: HHPro\r\n"], "ssl" => ["verify_peer" => false]];
            $img = @file_get_contents($url, false, stream_context_create($opts));
            if ($img) {
                if (file_put_contents($localPath, $img, LOCK_EX) !== false) {
                    @chmod($localPath, 0644);
                    @chown($localPath, 'asterisk');
                    @chgrp($localPath, 'asterisk');
                    $data['visual_editor']['background_image_url'] = 'assets/' . $localName;
                    $logger->log("Downloaded background for $model", 'INFO');
                } else {
                    $logger->log("Failed to write background for $model", 'WARNING');
                }
            } else {
                $logger->log("Failed to download background for $model", 'WARNING');
            }
        } else {
            $data['visual_editor']['background_image_url'] = 'assets/' . $localName;
        }
    }

    $json = json_encode($data);
    if (file_put_contents($path, $json, LOCK_EX) !== false) {
        @chmod($path, 0644);
        @chown($path, 'asterisk');
        @chgrp($path, 'asterisk');
        $logger->log("Installed template: $model", 'INFO');
    } else {
        $logger->log("Failed to write template: $model", 'ERROR');
    }
}

// --- 5. Default Profiles (only if templates folder is empty) ---
$existing_files = glob($templates_dir . '/*.json');
if (empty($existing_files)) {
    $logger->log('No templates found — installing defaults', 'INFO');

    $yealinkTpl = "#!version:1.0.0.1\naccount.1.enable = 1\naccount.1.label = {{display_name}}\naccount.1.display_name = {{display_name}}\naccount.1.auth_name = {{extension}}\naccount.1.user_name = {{extension}}\naccount.1.password = {{password}}\naccount.1.sip_server.1.address = {{server_host}}\naccount.1.sip_server.1.port = {{server_port}}\nphone_setting.backgrounds = {{wallpaper}}\n{{line_keys_loop}}\nlinekey.{{index}}.type = {{type}}\nlinekey.{{index}}.value = {{value}}\nlinekey.{{index}}.label = {{label}}\n{{/line_keys_loop}}\n{{contacts_loop}}\nremote_phonebook.data.1.url = {{contacts_url_example}}\n{{/contacts_loop}}\n# Enable the phone lock feature\n{{if phone_setting.keyboard_lock}}phone_setting.keyboard_lock = {{phone_setting.keyboard_lock}}\n{{/if}}\n# Set lock type: 0-Lock all keys; 1-Lock all except dial pad\n{{if phone_setting.keyboard_lock.type}}phone_setting.keyboard_lock.type = {{phone_setting.keyboard_lock.type}}\n{{/if}}\n# Define the unlock PIN (up to 15 characters)\n{{if phone_setting.keyboard_lock.password}}phone_setting.keyboard_lock.password = {{phone_setting.keyboard_lock.password}}\n{{/if}}\n# Idle time before auto-locking (in seconds)\n{{if phone_setting.keyboard_lock.timeout}}phone_setting.keyboard_lock.timeout = {{phone_setting.keyboard_lock.timeout}}\n{{/if}}\n# Hide the \"Menu\" softkey entirely to prevent tampering\n{{if features.enhanced_dss_keys.enable}}features.enhanced_dss_keys.enable = {{features.enhanced_dss_keys.enable}}\n{{/if}}\n{{if softkey.1.enable}}softkey.1.enable = {{softkey.1.enable}}\n{{/if}}\n{{if softkey.1.label}}softkey.1.label = {{softkey.1.label}}\n{{/if}}\n# Change default admin password for web/phone interface\n{{if static.security.admin_password}}static.security.admin_password = {{static.security.admin_password}}\n{{/if}}\n# Disable the web interface for total lockdown (requires factory reset to re-enable)\n{{if static.network.web_server.enable}}static.network.web_server.enable = {{static.network.web_server.enable}}\n{{/if}}\n# Disable Zero Touch (prevents kids from accidentally triggering provisioning at boot)\n{{if static.zero_touch.enable}}static.zero_touch.enable = {{static.zero_touch.enable}}\n{{/if}}\n# Set a custom wallpaper (URL must be accessible by the phone)\n{{if phone_setting.backgrounds}}phone_setting.backgrounds = {{phone_setting.backgrounds}}\n{{/if}}\n# Enable and configure a screensaver\n{{if screensaver.enable}}screensaver.enable = {{screensaver.enable}}\n{{/if}}\n{{if screensaver.wait_time}}screensaver.wait_time = {{screensaver.wait_time}}\n{{/if}}\n{{if screensaver.type}}screensaver.type = {{screensaver.type}}\n{{/if}}\n{{if screensaver.upload_url}}screensaver.upload_url = {{screensaver.upload_url}}\n{{/if}}\n# Set the phone's display name (appears on screen)\n{{if account.1.display_name}}account.1.display_name = {{account.1.display_name}}\n{{/if}}\n# Enable Hotline / Hot Dialing for Account 1\n{{if account.1.hotline_number}}account.1.hotline_number = {{account.1.hotline_number}}\n{{/if}}\n{{if account.1.hotline_delay}}account.1.hotline_delay = {{account.1.hotline_delay}}\n{{/if}}\n# Reduce wait time after last digit is pressed before dialing automatically\n{{if phone_setting.inter_digit_timer}}phone_setting.inter_digit_timer = {{phone_setting.inter_digit_timer}}\n{{/if}}\n";

    $configurable_options = [
        ['name' => 'phone_setting.keyboard_lock', 'type' => 'bool', 'default' => 1, 'label' => 'Enable Phone Lock', 'description' => 'Enable the phone lock feature'],
        ['name' => 'phone_setting.keyboard_lock.type', 'type' => 'select', 'default' => 1, 'label' => 'Lock Type', 'description' => 'Set lock type: 0-Lock all keys; 1-Lock all except dial pad', 'options' => ['0' => 'Lock all keys', '1' => 'Lock all except dial pad']],
        ['name' => 'phone_setting.keyboard_lock.password', 'type' => 'text', 'default' => '1234', 'label' => 'Unlock PIN', 'description' => 'Define the unlock PIN (up to 15 characters)'],
        ['name' => 'phone_setting.keyboard_lock.timeout', 'type' => 'number', 'default' => 60, 'label' => 'Auto-Lock Timeout', 'description' => 'Idle time before auto-locking (in seconds)', 'min' => 0, 'max' => 3600],
        ['name' => 'features.enhanced_dss_keys.enable', 'type' => 'bool', 'default' => 1, 'label' => 'Enable Enhanced DSS Keys', 'description' => ''],
        ['name' => 'softkey.1.enable', 'type' => 'bool', 'default' => 0, 'label' => 'Enable Menu Softkey', 'description' => 'Hide the "Menu" softkey entirely to prevent tampering'],
        ['name' => 'softkey.1.label', 'type' => 'text', 'default' => 'Menu', 'label' => 'Menu Softkey Label', 'description' => ''],
        ['name' => 'static.security.admin_password', 'type' => 'text', 'default' => 'NewAdminPass2026', 'label' => 'Admin Password', 'description' => 'Change default admin password for web/phone interface'],
        ['name' => 'static.network.web_server.enable', 'type' => 'bool', 'default' => 0, 'label' => 'Enable Web Interface', 'description' => 'Disable the web interface for total lockdown (requires factory reset to re-enable)'],
        ['name' => 'static.zero_touch.enable', 'type' => 'bool', 'default' => 0, 'label' => 'Enable Zero Touch', 'description' => 'Disable Zero Touch (prevents kids from accidentally triggering provisioning at boot)'],
        ['name' => 'phone_setting.backgrounds', 'type' => 'text', 'default' => '192.168.1.50', 'label' => 'Custom Wallpaper URL', 'description' => 'Set a custom wallpaper (URL must be accessible by the phone)'],
        ['name' => 'screensaver.enable', 'type' => 'bool', 'default' => 1, 'label' => 'Enable Screensaver', 'description' => 'Enable and configure a screensaver'],
        ['name' => 'screensaver.wait_time', 'type' => 'number', 'default' => 300, 'label' => 'Screensaver Wait Time', 'description' => '', 'min' => 0, 'max' => 3600],
        ['name' => 'screensaver.type', 'type' => 'number', 'default' => 1, 'label' => 'Screensaver Type', 'description' => ''],
        ['name' => 'screensaver.upload_url', 'type' => 'text', 'default' => '192.168.1.50', 'label' => 'Screensaver Upload URL', 'description' => ''],
        ['name' => 'account.1.display_name', 'type' => 'text', 'default' => 'Home Office', 'label' => 'Display Name', 'description' => 'Set the phone\'s display name (appears on screen)'],
        ['name' => 'account.1.hotline_number', 'type' => 'text', 'default' => '[PhoneNumber]', 'label' => 'Hotline Number', 'description' => 'Enable Hotline / Hot Dialing for Account 1'],
        ['name' => 'account.1.hotline_delay', 'type' => 'number', 'default' => 0, 'label' => 'Hotline Delay', 'description' => 'Delay before dialing (0 = immediate, 4 = wait 4 seconds)', 'min' => 0, 'max' => 10],
        ['name' => 'phone_setting.inter_digit_timer', 'type' => 'number', 'default' => 2, 'label' => 'Inter Digit Timer', 'description' => 'Reduce wait time after last digit is pressed before dialing automatically', 'min' => 1, 'max' => 10]
    ];

    $t54wKeys = generateKeys(27, 'dual_column', ['startX' => 20, 'startY' => 100, 'stepY' => 40, 'rightOffset' => 430, 'maxPerPage' => 10]);

    installProfile([
        "manufacturer" => "Yealink",
        "model" => "T54W",
        "display_name" => "Yealink T54W",
        "max_line_keys" => 27,
        "button_layout" => "dual_column",
        "svg_fallback" => true,
        "notes" => "Wallpaper: 480x272 JPG/PNG recommended.",
        "options" => ["provisioning_server" => "http://yourserver/qp"],
        "configurable_options" => $configurable_options,
        "visual_editor" => [
            "screen_width" => 480,
            "screen_height" => 272,
            "remote_image_url" => "https://www.yealink.com/wp-content/uploads/2023/06/SIP-T54W-1.png",
            "schematic" => ["chassis_width" => 650, "chassis_height" => 550, "screen_x" => 152, "screen_y" => 48, "screen_width" => 345, "screen_height" => 195],
            "keys" => $t54wKeys
        ],
        "provisioning" => [
            "content_type" => "text/plain",
            "filename_pattern" => "{mac}.cfg",
            "type_mapping" => ["line" => "15", "speed_dial" => "13", "blf" => "16"],
            "template" => $yealinkTpl
        ]
    ]);

    $t48gKeys = generateKeys(29, 'grid', ['startX' => 50, 'startY' => 100, 'cols' => 3, 'stepX' => 200, 'stepY' => 80, 'maxPerPage' => 10]);
    installProfile([
        "manufacturer" => "Yealink",
        "model" => "T48G",
        "display_name" => "Yealink T48G",
        "max_line_keys" => 29,
        "button_layout" => "grid",
        "svg_fallback" => true,
        "notes" => "Wallpaper: 800x480 JPG/PNG recommended. Touch screen model.",
        "options" => ["provisioning_server" => "http://yourserver/qp"],
        "configurable_options" => $configurable_options,
        "visual_editor" => [
            "screen_width" => 800,
            "screen_height" => 480,
            "remote_image_url" => "https://www.yealink.com/website-service/attachment/product/image/20220505/20220505081634628a8fbe2554cb0a0fe603f82981fdd.png",
            "schematic" => ["chassis_width" => 800, "chassis_height" => 600, "screen_x" => 100, "screen_y" => 50, "screen_width" => 600, "screen_height" => 360],
            "keys" => $t48gKeys
        ],
        "provisioning" => [
            "content_type" => "text/plain",
            "filename_pattern" => "{mac}.cfg",
            "type_mapping" => ["line" => "15", "speed_dial" => "13", "blf" => "16"],
            "template" => $yealinkTpl
        ]
    ]);
}

// Final permission sweep - set permissions recursively using PHP
// Note: This is a best-effort approach, may not work in all environments
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if ($item->isDir()) {
        @chmod($item->getPathname(), 0775);
        @chown($item->getPathname(), 'asterisk');
        @chgrp($item->getPathname(), 'asterisk');
    } else {
        @chmod($item->getPathname(), 0664);
        @chown($item->getPathname(), 'asterisk');
        @chgrp($item->getPathname(), 'asterisk');
    }
}

if ($logger) {
    $logger->log('HH Quick Provisioner install completed', 'INFO');
}
?>
