<?php
// ajax.quickprovisioner.php - HH Quick Provisioner v2.2 - Backend API
function qp_is_local_network() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '::1') return true;
    if (preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip)) {
        return true;
    }
    return false;
}

if (!qp_is_local_network()) {
    die(json_encode(['status' => false, 'message' => 'Remote access denied. Admin UI is local network only.']));
}

if (!defined('FREEPBX_IS_AUTH') || !FREEPBX_IS_AUTH) {
    die(json_encode(['status' => false, 'message' => 'Unauthorized']));
}

global $db;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$action = $_REQUEST['action'] ?? '';
$response = ['status' => false, 'message' => 'Invalid action'];

// Helper functions for safe file operations
function qp_safe_write($filepath, $content) {
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        $old_umask = umask(0);
        if (!mkdir($dir, 0775, true)) {
            umask($old_umask);
            return ['status' => false, 'message' => 'Failed to create directory: ' . $dir];
        }
        umask($old_umask);
    }
    
    if (!is_writable($dir)) {
        return ['status' => false, 'message' => 'Directory not writable: ' . $dir];
    }
    
    if (file_put_contents($filepath, $content) === false) {
        return ['status' => false, 'message' => 'Failed to write file: ' . basename($filepath)];
    }
    chmod($filepath, 0664);
    return ['status' => true];
}

function qp_safe_delete($filepath) {
    if (!file_exists($filepath)) {
        return ['status' => false, 'message' => 'File not found: ' . basename($filepath)];
    }
    if (!unlink($filepath)) {
        return ['status' => false, 'message' => 'Failed to delete file: ' . basename($filepath)];
    }
    return ['status' => true];
}

function qp_safe_move_upload($tmp_file, $target) {
    $dir = dirname($target);
    if (!is_dir($dir)) {
        $old_umask = umask(0);
        if (!mkdir($dir, 0775, true)) {
            umask($old_umask);
            return ['status' => false, 'message' => 'Failed to create directory: ' . $dir];
        }
        umask($old_umask);
    }
    
    if (!is_writable($dir)) {
        return ['status' => false, 'message' => 'Directory not writable: ' . $dir];
    }
    
    if (!move_uploaded_file($tmp_file, $target)) {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
    chmod($target, 0664);
    return ['status' => true];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_REQUEST['csrf_token']) || $_REQUEST['csrf_token'] !== $_SESSION['qp_csrf'])) {
    $response['message'] = 'CSRF validation failed';
    echo json_encode($response);
    exit;
}

$templates_dir = __DIR__ . '/templates';  // New: Folder for model templates

switch ($action) {
    // === DEVICE ACTIONS ===
    case 'save_device':
        parse_str($_POST['data'] ?? '', $form);
        if (empty($form['mac']) || strlen($form['mac']) < 12) {
            $response['message'] = 'Invalid MAC';
            break;
        }
        $keys_json = $_POST['keys_json'] ?? '[]';
        $contacts_json = $_POST['contacts_json'] ?? '[]';
        $custom_options_json = json_encode($form['custom_options'] ?? []);
        $custom_template_override = $form['custom_template_override'] ?? '';
        $wallpaper = $form['wallpaper'] ?? '';
        $wallpaper_mode = $form['wallpaper_mode'] ?? 'crop';
        $security_pin = $form['security_pin'] ?? '';
        $prov_username = $form['prov_username'] ?? '';
        $prov_password = $form['prov_password'] ?? '';
        $custom_sip_secret = $form['custom_sip_secret'] ?? null;
        // Allow empty string to clear the custom secret
        if ($custom_sip_secret === '') {
            $custom_sip_secret = null;
        }

        $id = $form['deviceId'] ?? null;
        if ($id) {
            $sql = "UPDATE quickprovisioner_devices SET mac=?, model=?, extension=?, wallpaper=?, wallpaper_mode=?, security_pin=?, keys_json=?, contacts_json=?, custom_options_json=?, custom_template_override=?, prov_username=?, prov_password=?, custom_sip_secret=? WHERE id=?";
            $params = [$form['mac'], $form['model'], $form['extension'], $wallpaper, $wallpaper_mode, $security_pin, $keys_json, $contacts_json, $custom_options_json, $custom_template_override, $prov_username, $prov_password, $custom_sip_secret, $id];
        } else {
            $sql = "INSERT INTO quickprovisioner_devices (mac, model, extension, wallpaper, wallpaper_mode, security_pin, keys_json, contacts_json, custom_options_json, custom_template_override, prov_username, prov_password, custom_sip_secret) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$form['mac'], $form['model'], $form['extension'], $wallpaper, $wallpaper_mode, $security_pin, $keys_json, $contacts_json, $custom_options_json, $custom_template_override, $prov_username, $prov_password, $custom_sip_secret];
        }
        $db->query($sql, $params);
        \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Device saved: MAC=" . $form['mac']);
        $response = ['status' => true];
        break;

    case 'get_device':
        $id = $_REQUEST['id'] ?? null;
        if (!$id || !is_numeric($id)) { $response['message'] = 'Invalid ID'; break; }
        $row = $db->getRow("SELECT * FROM quickprovisioner_devices WHERE id=?", [(int)$id]);
        $response = ['status' => true, 'data' => $row ?: null];
        break;

    case 'list_devices':
        $rows = $db->query("SELECT * FROM quickprovisioner_devices ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $response = ['status' => true, 'devices' => $rows];
        break;

    case 'list_devices_with_secrets':
        $rows = $db->query("SELECT * FROM quickprovisioner_devices ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $devices = [];
        foreach ($rows as $row) {
            $ext = $row['extension'];
            $secret = '';
            $secretSource = '';

            // Check if custom secret is set
            if (!empty($row['custom_sip_secret'])) {
                $secret = $row['custom_sip_secret'];
                $secretSource = 'Custom';
            } else {
                // Try to fetch secret from FreePBX
                try {
                    $device = \FreePBX::Core()->getDevice($ext);
                    if ($device && is_array($device) && isset($device['secret'])) {
                        $secret = $device['secret'];
                        $secretSource = 'FreePBX';
                    } else {
                        error_log("Quick Provisioner: Secret not found for extension $ext");
                    }
                } catch (Exception $e) {
                    error_log("Quick Provisioner: Error fetching secret for extension $ext - " . $e->getMessage());
                }
            }

            $devices[] = [
                'id' => $row['id'],
                'mac' => $row['mac'],
                'extension' => $row['extension'],
                'model' => $row['model'],
                'secret' => $secret,
                'secret_source' => $secretSource
            ];
        }
        $response = ['status' => true, 'devices' => $devices];
        break;

    case 'delete_device':
        $id = $_REQUEST['id'] ?? null;
        if (!$id || !is_numeric($id)) { $response['message'] = 'Invalid ID'; break; }
        $stmt = $db->query("DELETE FROM quickprovisioner_devices WHERE id=?", [(int)$id]);
        if ($stmt->rowCount() > 0) {
            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Device deleted: ID=$id");
            $response = ['status' => true];
        } else {
            $response['message'] = 'Device not found';
        }
        break;

    case 'preview_config':
        $id = $_REQUEST['id'] ?? null;
        if (!$id || !is_numeric($id)) { $response['message'] = 'Invalid ID'; break; }
        $device = $db->getRow("SELECT * FROM quickprovisioner_devices WHERE id=?", [(int)$id]);
        if (!$device) { $response['message'] = 'Device not found'; break; }
        $model = basename($device['model']); // Sanitize to prevent path traversal
        $profile_path = $templates_dir . '/' . $model . '.json';
        if (!file_exists($profile_path)) { $response['message'] = 'Profile not found'; break; }
        $profile_json = file_get_contents($profile_path);
        $profile = json_decode($profile_json, true);
        if ($profile === null) {
            $response['message'] = 'Invalid template JSON for model ' . $model;
            break;
        }
        $template = $device['custom_template_override'] ? $device['custom_template_override'] : $profile['provisioning']['template'] ?? '';
        $ext = $device['extension'];

        // Fetch user info and secret with error handling
        $display_name = $ext;
        $secret = '';

        // Use custom secret if available
        if (!empty($device['custom_sip_secret'])) {
            $secret = $device['custom_sip_secret'];
        } else {
            // Otherwise fetch from FreePBX
            try {
                $deviceInfo = \FreePBX::Core()->getDevice($ext);
                if ($deviceInfo && is_array($deviceInfo) && isset($deviceInfo['secret'])) {
                    $secret = $deviceInfo['secret'];
                } else {
                    error_log("Quick Provisioner: Secret not found for extension $ext during config preview");
                }
            } catch (Exception $e) {
                error_log("Quick Provisioner: Error fetching FreePBX data for extension $ext - " . $e->getMessage());
            }
        }

        // Fetch display name
        try {
            $userInfo = \FreePBX::Core()->getUser($ext);
            if ($userInfo && is_array($userInfo) && isset($userInfo['name'])) {
                $display_name = $userInfo['name'];
            }
        } catch (Exception $e) {
            error_log("Quick Provisioner: Error fetching user info for extension $ext - " . $e->getMessage());
        }

        $server_ip = $_SERVER['SERVER_ADDR'];
        $server_port = \FreePBX::Sipsettings()->get('bindport') ?? '5060';
        $wpUrl = "";
        if (!empty($device['wallpaper'])) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            // Do not embed credentials in URL - device will authenticate via Basic Auth headers
            $wpUrl = "$protocol://$host/admin/modules/quickprovisioner/media.php?mac=" . strtoupper(preg_replace('/[^A-F0-9]/', '', $device['mac']));
        }
        $vars = [
            '{{mac}}' => strtoupper(preg_replace('/[^A-F0-9]/', '', $device['mac'])),
            '{{extension}}' => $ext,
            '{{password}}' => $secret,
            '{{display_name}}' => $display_name,
            '{{server_host}}' => $server_ip,
            '{{server_port}}' => $server_port,
            '{{wallpaper}}' => $wpUrl,
            '{{security_pin}}' => $device['security_pin'] ?? ''
        ];
        foreach ($custom_options as $key => $value) {
            if ($value !== '') {
                $vars['{{' . $key . '}}'] = htmlspecialchars($value);
            }
        }
        $template = preg_replace_callback('/{{if (.*?)}}(.*?){{\/if}}/s', function($m) use ($vars) {
            $var = trim($m[1]);
            $content = $m[2];
            if (isset($vars['{{' . $var . '}}']) && $vars['{{' . $var . '}}']) {
                return $content;
            }
            return '';
        }, $template);
        if (preg_match('/{{line_keys_loop}}(.*?){{\/line_keys_loop}}/s', $template, $matches)) {
            $loopContent = $matches[1];
            $keys = json_decode($device['keys_json'], true) ?? [];
            $builtLoop = '';
            usort($keys, function($a, $b) { return $a['index'] - $b['index']; });
            foreach ($keys as $k) {
                $item = $loopContent;
                $rawType = $k['type'] ?? 'line';
                $mappedType = $profile['provisioning']['type_mapping'][$rawType] ?? $rawType;
                $item = str_replace('{{index}}', $k['index'], $item);
                $item = str_replace('{{type}}', $mappedType, $item);
                foreach ($k as $keyName => $keyValue) {
                    if ($keyName == 'index' || $keyName == 'type') continue;
                    $item = str_replace('{{' . $keyName . '}}', htmlspecialchars($keyValue), $item);
                }
                $item = preg_replace('/{{[a-z_]+}}/', '', $item);
                $builtLoop .= $item;
            }
            $template = str_replace($matches[0], $builtLoop, $template);
        }
        if (preg_match('/{{contacts_loop}}(.*?){{\/contacts_loop}}/s', $template, $matches)) {
            $loopContent = $matches[1];
            $contacts = json_decode($device['contacts_json'], true) ?? [];
            $builtLoop = '';
            foreach ($contacts as $idx => $c) {
                $item = $loopContent;
                $item = str_replace('{{index}}', $idx + 1, $item);
                $item = str_replace('{{name}}', htmlspecialchars($c['name']), $item);
                $item = str_replace('{{number}}', htmlspecialchars($c['number']), $item);
                $item = str_replace('{{custom_label}}', htmlspecialchars($c['custom_label']), $item);
                $photo_url = "";
                if (!empty($c['photo'])) {
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    // Do not embed credentials in URL - device will authenticate via Basic Auth headers
                    $photo_url = "$protocol://$host/admin/modules/quickprovisioner/media.php?file=" . $c['photo'] . "&mac=" . $vars['{{mac}}'] . "&w=100&h=100&mode=crop";
                }
                $item = str_replace('{{photo_url}}', $photo_url, $item);
                $builtLoop .= $item;
            }
            $template = str_replace($matches[0], $builtLoop, $template);
        }
        
        // Generic array repeater handler for digitmap and other custom repeaters
        if (preg_match_all('/{{([a-z_]+)_loop}}(.*?){{\/\1_loop}}/s', $template, $allMatches, PREG_SET_ORDER)) {
            foreach ($allMatches as $match) {
                $loopName = $match[1];
                // Skip already processed loops
                if ($loopName === 'line_keys' || $loopName === 'contacts') {
                    continue;
                }
                
                $loopContent = $match[2];
                $loopData = [];
                
                // Check if template defines the array data source
                if (isset($profile['provisioning'][$loopName . '_data'])) {
                    $loopData = $profile['provisioning'][$loopName . '_data'];
                }
                // Or check if device has custom data for this loop
                else if (!empty($device['custom_options_json'])) {
                    $customOptionsDecoded = json_decode($device['custom_options_json'], true) ?? [];
                    if (isset($customOptionsDecoded[$loopName . '_data'])) {
                        $loopData = json_decode($customOptionsDecoded[$loopName . '_data'], true) ?? [];
                    }
                }
                
                $builtLoop = '';
                foreach ($loopData as $idx => $item_data) {
                    $item = $loopContent;
                    // Replace {{index}} with 1-based index
                    $item = str_replace('{{index}}', $idx + 1, $item);
                    // Replace any other variables from the data item
                    if (is_array($item_data)) {
                        foreach ($item_data as $key => $value) {
                            $item = str_replace('{{' . $key . '}}', htmlspecialchars($value), $item);
                        }
                    } else {
                        // If item is a scalar, replace {{value}}
                        $item = str_replace('{{value}}', htmlspecialchars($item_data), $item);
                    }
                    // Clean up any remaining unreplaced variables
                    $item = preg_replace('/{{[a-z_]+}}/', '', $item);
                    $builtLoop .= $item;
                }
                $template = str_replace($match[0], $builtLoop, $template);
            }
        }
        
        foreach ($vars as $k => $v) {
            $template = str_replace($k, $v, $template);
        }
        $response = ['status' => true, 'config' => $template];
        break;

    case 'upload_file':
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'Upload error: ' . $_FILES['file']['error'];
            break;
        }
        if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
            $response['message'] = 'File too large (max 5MB)';
            break;
        }
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file']['tmp_name']);
        if (!in_array($mime, $allowed_mimes)) {
            $response['message'] = 'Invalid file type';
            break;
        }
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            $response['message'] = 'Invalid file extension';
            break;
        }
        $filename = uniqid('asset_') . '.' . $ext;
        $target = __DIR__ . '/assets/uploads/' . $filename;
        
        // Auto-resize image if dimensions are provided
        $resize_width = isset($_POST['resize_width']) ? intval($_POST['resize_width']) : 0;
        $resize_height = isset($_POST['resize_height']) ? intval($_POST['resize_height']) : 0;
        
        if ($resize_width > 0 && $resize_height > 0 && function_exists('imagecreatefromjpeg')) {
            // Load source image
            $source = null;
            switch ($mime) {
                case 'image/jpeg':
                    $source = @imagecreatefromjpeg($_FILES['file']['tmp_name']);
                    break;
                case 'image/png':
                    $source = @imagecreatefrompng($_FILES['file']['tmp_name']);
                    break;
                case 'image/gif':
                    $source = @imagecreatefromgif($_FILES['file']['tmp_name']);
                    break;
            }
            
            if ($source) {
                $src_width = imagesx($source);
                $src_height = imagesy($source);
                
                // Create destination image
                $dest = imagecreatetruecolor($resize_width, $resize_height);
                
                // Preserve transparency for PNG/GIF
                if ($mime === 'image/png' || $mime === 'image/gif') {
                    imagealphablending($dest, false);
                    imagesavealpha($dest, true);
                    $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
                    imagefilledrectangle($dest, 0, 0, $resize_width, $resize_height, $transparent);
                }
                
                // Resize with resampling for better quality
                imagecopyresampled($dest, $source, 0, 0, 0, 0, $resize_width, $resize_height, $src_width, $src_height);
                
                // Save resized image
                $save_result = false;
                switch ($ext) {
                    case 'jpg':
                    case 'jpeg':
                        $save_result = imagejpeg($dest, $target, 90);
                        break;
                    case 'png':
                        $save_result = imagepng($dest, $target, 9);
                        break;
                    case 'gif':
                        $save_result = imagegif($dest, $target);
                        break;
                }
                
                imagedestroy($source);
                imagedestroy($dest);
                
                if ($save_result) {
                    chmod($target, 0664);
                    \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Asset uploaded and resized: $filename");
                    $response = ['status' => true, 'url' => $filename];
                } else {
                    $response['message'] = 'Failed to save resized image';
                }
            } else {
                // Fallback to regular upload if image processing fails
                $result = qp_safe_move_upload($_FILES['file']['tmp_name'], $target);
                if ($result['status']) {
                    \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Asset uploaded (resize failed, using original): $filename");
                    $response = ['status' => true, 'url' => $filename];
                } else {
                    $response['message'] = $result['message'];
                }
            }
        } else {
            // No resize requested or GD not available
            $result = qp_safe_move_upload($_FILES['file']['tmp_name'], $target);
            if ($result['status']) {
                \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Asset uploaded: $filename");
                $response = ['status' => true, 'url' => $filename];
            } else {
                $response['message'] = $result['message'];
            }
        }
        break;

    case 'list_assets':
        $uploads_dir = __DIR__ . '/assets/uploads';
        $files = [];
        if (is_dir($uploads_dir)) {
            foreach (scandir($uploads_dir) as $item) {
                if ($item === '.' || $item === '..' || $item === '.htaccess') continue;
                $filepath = $uploads_dir . '/' . $item;
                if (is_file($filepath)) {
                    $files[] = ['filename' => $item, 'size' => filesize($filepath)];
                }
            }
        }
        $response = ['status' => true, 'files' => $files];
        break;

    case 'delete_asset':
        $filename = basename($_POST['filename'] ?? '');
        $path = __DIR__ . '/assets/uploads/' . $filename;
        $result = qp_safe_delete($path);
        if ($result['status']) {
            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Asset deleted: $filename");
            $response = ['status' => true];
        } else {
            $response['message'] = $result['message'];
        }
        break;

    case 'get_driver':
        $model = basename($_REQUEST['model'] ?? ''); // Sanitize to prevent path traversal
        if (!$model) { $response['message'] = 'No model'; break; }
        $path = $templates_dir . '/' . $model . '.json';
        if (!file_exists($path)) { $response['message'] = 'Template not found'; break; }
        $json = file_get_contents($path);
        $response = ['status' => true, 'json' => $json];
        break;

    case 'import_driver':
        $json = $_POST['json'] ?? '';
        $data = json_decode($json, true);
        if ($data === null || json_last_error() !== JSON_ERROR_NONE || empty($data['model'])) {
            $response['message'] = 'Invalid JSON or missing model field';
            break;
        }
        $model = basename($data['model']); // Sanitize to prevent path traversal
        $path = $templates_dir . '/' . $model . '.json';
        $result = qp_safe_write($path, $json);
        if ($result['status']) {
            $response = ['status' => true];
        } else {
            $response['message'] = $result['message'];
        }
        break;

    case 'delete_driver':
        $model = basename($_POST['model'] ?? ''); // Sanitize to prevent path traversal
        if (!$model) { $response['message'] = 'No model'; break; }
        $path = $templates_dir . '/' . $model . '.json';
        $result = qp_safe_delete($path);
        if ($result['status']) {
            $response = ['status' => true];
        } else {
            $response['message'] = $result['message'];
        }
        break;

    case 'list_drivers':
        $files = glob($templates_dir . '/*.json');
        $list = [];
        foreach ($files as $file) {
            $model = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);
            $list[] = ['model' => $model, 'display_name' => $data['display_name'] ?? $model];
        }
        $response = ['status' => true, 'list' => $list];
        break;

    case 'get_sip_secret':
        $ext = $_REQUEST['extension'] ?? null;
        if (!$ext) {
            $response['message'] = 'No extension provided';
            break;
        }
        try {
            $device = \FreePBX::Core()->getDevice($ext);
            $secret = '';
            if ($device && is_array($device) && isset($device['secret'])) {
                $secret = $device['secret'];
            }
            if ($secret) {
                $response = ['status' => true, 'secret' => $secret];
            } else {
                $response['message'] = "Secret not found for extension $ext. Extension may not exist in FreePBX.";
                error_log("Quick Provisioner: Secret not found for extension $ext");
            }
        } catch (Exception $e) {
            $response['message'] = "Error fetching secret: " . $e->getMessage();
            error_log("Quick Provisioner: Error fetching secret for extension $ext - " . $e->getMessage());
        }
        break;

    // === UPDATE MANAGEMENT ACTIONS ===
    case 'check_updates':
        $module_dir = __DIR__;

        // Get current commit hash
        $current_commit = trim(shell_exec("cd " . escapeshellarg($module_dir) . " && git rev-parse HEAD 2>&1"));
        if (empty($current_commit) || strlen($current_commit) !== 40) {
            $response['message'] = 'Failed to get current commit: ' . $current_commit;
            break;
        }

        // Get current version from module.xml
        $module_xml_path = $module_dir . '/module.xml';
        $current_version = '2.1.0'; // Default
        if (file_exists($module_xml_path)) {
            $xml_content = @file_get_contents($module_xml_path);
            if ($xml_content && preg_match('/<version>(.*?)<\/version>/', $xml_content, $matches)) {
                $current_version = $matches[1];
            }
        }

        // Set SSH key for git operations if it exists
        $ssh_key_path = '/home/hhvoip/.ssh/id_github';
        if (file_exists($ssh_key_path) && is_readable($ssh_key_path)) {
            putenv('GIT_SSH_COMMAND=ssh -i ' . $ssh_key_path . ' -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new');
            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Quick Provisioner: Using SSH key: $ssh_key_path");
        } else {
            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Quick Provisioner: SSH key not found at $ssh_key_path, proceeding without custom key");
        }

        // Fetch from origin
        $fetch_output = shell_exec("cd " . escapeshellarg($module_dir) . " && git fetch origin main 2>&1");

        // Get remote commit hash
        $remote_commit = trim(shell_exec("cd " . escapeshellarg($module_dir) . " && git rev-parse origin/main 2>&1"));
        if (empty($remote_commit) || strlen($remote_commit) !== 40) {
            $response['message'] = 'Failed to get remote commit. Fetch output: ' . $fetch_output;
            break;
        }

        // Check if updates are available
        $has_updates = ($current_commit !== $remote_commit);

        $response = [
            'status' => true,
            'current_commit' => $current_commit,
            'current_version' => $current_version,
            'remote_commit' => $remote_commit,
            'has_updates' => $has_updates
        ];
        break;

    case 'get_changelog':
        $module_dir = __DIR__;
        $current_commit = $_POST['current_commit'] ?? '';
        $remote_commit = $_POST['remote_commit'] ?? '';

        if (empty($current_commit) || empty($remote_commit)) {
            $response['message'] = 'Missing commit parameters';
            break;
        }

        // Set SSH key for git operations if it exists
        $ssh_key_path = '/home/hhvoip/.ssh/id_github';
        if (file_exists($ssh_key_path) && is_readable($ssh_key_path)) {
            putenv('GIT_SSH_COMMAND=ssh -i ' . $ssh_key_path . ' -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new');
            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Quick Provisioner: Using SSH key: $ssh_key_path");
        } else {
            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Quick Provisioner: SSH key not found at $ssh_key_path, proceeding without custom key");
        }

        // Get list of commits between current and remote
        $log_cmd = sprintf(
            "cd %s && git log %s..%s --pretty=format:'%%H||%%s||%%an||%%ai' 2>&1",
            escapeshellarg($module_dir),
            escapeshellarg($current_commit),
            escapeshellarg($remote_commit)
        );
        $log_output = shell_exec($log_cmd);

        $commits = [];
        if (!empty($log_output)) {
            $lines = explode("\n", trim($log_output));
            foreach ($lines as $line) {
                if (empty($line)) continue;
                $parts = explode('||', $line);
                if (count($parts) >= 4) {
                    $commits[] = [
                        'hash' => $parts[0],
                        'message' => $parts[1],
                        'author' => $parts[2],
                        'date' => $parts[3]
                    ];
                }
            }
        }

        $response = [
            'status' => true,
            'commits' => $commits
        ];
        break;

    case 'perform_update':
        $module_dir = __DIR__;

        // Get current commit before update
        $old_commit = trim(shell_exec("cd " . escapeshellarg($module_dir) . " && git rev-parse HEAD 2>&1"));

        // Set SSH key for git operations if it exists
        $ssh_key_path = '/home/hhvoip/.ssh/id_github';
        if (file_exists($ssh_key_path) && is_readable($ssh_key_path)) {
            putenv('GIT_SSH_COMMAND=ssh -i ' . $ssh_key_path . ' -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new');
            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Quick Provisioner: Using SSH key: $ssh_key_path");
        } else {
            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Quick Provisioner: SSH key not found at $ssh_key_path, proceeding without custom key");
        }

        // Perform git pull
        $pull_output = shell_exec("cd " . escapeshellarg($module_dir) . " && git pull origin main 2>&1");

        // Check if pull was successful
        if (strpos($pull_output, 'Already up to date') !== false || strpos($pull_output, 'Fast-forward') !== false || strpos($pull_output, 'Updating') !== false) {
            // Get new commit hash
            $new_commit = trim(shell_exec("cd " . escapeshellarg($module_dir) . " && git rev-parse HEAD 2>&1"));

            // Get new version from module.xml
            $module_xml_path = $module_dir . '/module.xml';
            $new_version = null;
            if (file_exists($module_xml_path)) {
                $xml_content = @file_get_contents($module_xml_path);
                if ($xml_content && preg_match('/<version>(.*?)<\/version>/', $xml_content, $matches)) {
                    $new_version = $matches[1];
                }
            }

            // Note: Permission/ownership changes require elevated privileges.
            // If needed, run 'fwconsole chown' manually or use the qp-update script with sudo.

            \FreePBX::create()->Logger->log(FPBX_LOG_INFO, "Module updated: $old_commit -> $new_commit");

            $response = [
                'status' => true,
                'old_commit' => $old_commit,
                'new_commit' => $new_commit,
                'new_version' => $new_version,
                'message' => 'Update completed successfully. Please refresh the page to see changes.'
            ];
        } else {
            $response['message'] = 'Git pull failed: ' . $pull_output;
        }
        break;

    case 'restart_pbx':
        $restart_type = isset($_POST['type']) ? $_POST['type'] : 'reload';

        if (!in_array($restart_type, ['reload', 'restart'])) {
            $response = ['status' => false, 'message' => 'Invalid restart type'];
            break;
        }

        // Use explicit whitelist approach for security
        if ($restart_type === 'reload') {
            $command = 'fwconsole reload';
        } else {
            $command = 'fwconsole restart';
        }

        $output = [];
        $return_var = 0;
        exec($command . ' 2>&1', $output, $return_var);

        if ($return_var === 0) {
            $response = [
                'status' => true,
                'message' => 'PBX ' . $restart_type . ' completed successfully',
                'output' => implode("\n", $output)
            ];
        } else {
            $response = [
                'status' => false,
                'message' => 'PBX ' . $restart_type . ' failed',
                'output' => implode("\n", $output)
            ];
        }
        break;
}

echo json_encode($response);
?>
