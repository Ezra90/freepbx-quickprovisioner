<?php
// ajax.quickprovisioner.php - HH Quick Provisioner v2.1 - Backend API
// Start session only if not already started (FreePBX may have already started it)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('FREEPBX_IS_AUTH') || !FREEPBX_IS_AUTH) {
    die(json_encode(['status' => false, 'message' => 'Unauthorized']));
}

global $db;
$action = $_REQUEST['action'] ?? '';
$response = ['status' => false, 'message' => 'Invalid action'];

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
        
        // Validate wallpaper filename - only allow safe characters
        if (!empty($wallpaper)) {
            $wallpaper = basename($wallpaper);
            if (!preg_match('/^asset_[a-zA-Z0-9]+\.(jpg|jpeg|png|gif)$/i', $wallpaper)) {
                $response['message'] = 'Invalid wallpaper filename format';
                break;
            }
            // Verify file exists in uploads directory and prevent path traversal
            $wallpaper_path = realpath(__DIR__ . '/assets/uploads/' . $wallpaper);
            $uploads_dir = realpath(__DIR__ . '/assets/uploads');
            if (!$wallpaper_path || !$uploads_dir || strpos($wallpaper_path, $uploads_dir) !== 0 || !file_exists($wallpaper_path)) {
                $response['message'] = 'Wallpaper file does not exist or invalid path';
                break;
            }
        }
        
        $wallpaper_mode = $form['wallpaper_mode'] ?? 'crop';
        // Validate wallpaper_mode
        if (!in_array($wallpaper_mode, ['crop', 'fit'])) {
            $response['message'] = 'Invalid wallpaper mode';
            break;
        }
        
        $security_pin = $form['security_pin'] ?? '';
        // Validate security pin if provided
        if (!empty($security_pin) && !preg_match('/^[0-9]{1,15}$/', $security_pin)) {
            $response['message'] = 'Invalid security PIN - must be 1-15 digits';
            break;
        }

        $id = $form['deviceId'] ?? null;
        if ($id) {
            $sql = "UPDATE quickprovisioner_devices SET mac=?, model=?, extension=?, wallpaper=?, wallpaper_mode=?, security_pin=?, keys_json=?, contacts_json=?, custom_options_json=?, custom_template_override=? WHERE id=?";
            $params = [$form['mac'], $form['model'], $form['extension'], $wallpaper, $wallpaper_mode, $security_pin, $keys_json, $contacts_json, $custom_options_json, $custom_template_override, $id];
        } else {
            $sql = "INSERT INTO quickprovisioner_devices (mac, model, extension, wallpaper, wallpaper_mode, security_pin, keys_json, contacts_json, custom_options_json, custom_template_override) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$form['mac'], $form['model'], $form['extension'], $wallpaper, $wallpaper_mode, $security_pin, $keys_json, $contacts_json, $custom_options_json, $custom_template_override];
        }
        $db->query($sql, $params);
        \FreePBX::create()->Logger->log("Device saved: MAC=" . $form['mac']);
        $response = ['status' => true];
        break;

    case 'get_device':
        $id = $_REQUEST['id'] ?? null;
        if (!$id) { $response['message'] = 'No ID'; break; }
        $row = $db->getRow("SELECT * FROM quickprovisioner_devices WHERE id=?", [$id]);
        $response = ['status' => true, 'data' => $row ?: null];
        break;

    case 'list_devices':
        $rows = $db->query("SELECT * FROM quickprovisioner_devices ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $response = ['status' => true, 'devices' => $rows];
        break;

    case 'delete_device':
        $id = $_POST['id'] ?? null;
        if (!$id) { $response['message'] = 'No ID'; break; }
        $db->query("DELETE FROM quickprovisioner_devices WHERE id=?", [$id]);
        \FreePBX::create()->Logger->log("Device deleted: ID=" . $id);
        $response = ['status' => true];
        break;

    case 'preview_config':
        $id = $_REQUEST['id'] ?? null;
        if (!$id) { $response['message'] = 'No ID'; break; }
        $device = $db->getRow("SELECT * FROM quickprovisioner_devices WHERE id=?", [$id]);
        if (!$device) { $response['message'] = 'Device not found'; break; }
        $model = $device['model'];
        $profile_path = $templates_dir . '/' . $model . '.json';
        if (!file_exists($profile_path)) { $response['message'] = 'Profile not found'; break; }
        $profile_json = file_get_contents($profile_path);
        $profile = json_decode($profile_json, true);
        $custom_options = json_decode($device['custom_options_json'], true) ?? [];
        $template = $device['custom_template_override'] ? $device['custom_template_override'] : $profile['provisioning']['template'] ?? '';
        $ext = $device['extension'];
        $userInfo = \FreePBX::Core()->getUser($ext);
        $display_name = $userInfo['name'] ?? $ext;
        $deviceInfo = \FreePBX::Core()->getDevice($ext);
        $secret = isset($deviceInfo['secret']) ? $deviceInfo['secret'] : '';
        $server_ip = $_SERVER['SERVER_ADDR'];
        $server_port = \FreePBX::Sipsettings()->get('bindport') ?? '5060';
        $wpUrl = "";
        if (!empty($device['wallpaper'])) {
            $protocol = (isset($_SERVER['HTTPS']) ? "https" : "http");
            $auth = $ext . ":" . $secret . "@";
            $host = $_SERVER['HTTP_HOST'];
            $wpUrl = "$protocol://$auth$host/admin/modules/quickprovisioner/media.php?mac=" . strtoupper(preg_replace('/[^A-F0-9]/', '', $device['mac']));
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
                    $protocol = (isset($_SERVER['HTTPS']) ? "https" : "http");
                    $auth = $ext . ":" . $secret . "@";
                    $host = $_SERVER['HTTP_HOST'];
                    $photo_url = "$protocol://$auth$host/admin/modules/quickprovisioner/media.php?file=" . $c['photo'] . "&mac=" . $vars['{{mac}}'] . "&w=100&h=100&mode=crop";
                }
                $item = str_replace('{{photo_url}}', $photo_url, $item);
                $builtLoop .= $item;
            }
            $template = str_replace($matches[0], $builtLoop, $template);
        }
        foreach ($vars as $k => $v) {
            $template = str_replace($k, $v, $template);
        }
        $response = ['status' => true, 'config' => $template];
        break;

    case 'upload_file':
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            // Map error codes to user-friendly messages without exposing internals
            $error_msg = 'Upload failed';
            if (isset($_FILES['file']['error'])) {
                switch ($_FILES['file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_msg = 'File too large';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_msg = 'Upload incomplete';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_msg = 'No file selected';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                    case UPLOAD_ERR_EXTENSION:
                        $error_msg = 'Server configuration error';
                        break;
                }
            }
            $response['message'] = $error_msg;
            break;
        }
        if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
            $response['message'] = 'File too large (max 5MB)';
            break;
        }
        // Validate MIME type
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file']['tmp_name']);
        if (!in_array($mime, $allowed_mimes)) {
            $response['message'] = 'Invalid file type';
            break;
        }
        // Additional validation: verify it's actually a valid image
        $imageInfo = @getimagesize($_FILES['file']['tmp_name']);
        if ($imageInfo === false) {
            $response['message'] = 'File is not a valid image';
            break;
        }
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        // Validate extension
        if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
            $response['message'] = 'Invalid file extension';
            break;
        }
        $filename = uniqid('asset_') . '.' . strtolower($ext);
        $target = __DIR__ . '/assets/uploads/' . $filename;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        // Use PHP functions instead of shell_exec
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            @chmod($target, 0644);
            // Note: chown may fail if not running as root, but that's acceptable
            @chown($target, 'asterisk');
            @chgrp($target, 'asterisk');
            \FreePBX::create()->Logger->log("Asset uploaded: $filename");
            $response = ['status' => true, 'url' => $filename];
        } else {
            $response['message'] = 'Move failed: Check server permissions/logs';
        }
        break;

    case 'delete_asset':
        $filename = basename($_POST['filename'] ?? '');
        // Validate filename - must match upload pattern
        if (!preg_match('/^asset_[a-zA-Z0-9]+\.(jpg|jpeg|png|gif)$/i', $filename)) {
            $response['message'] = 'Invalid filename format';
            break;
        }
        // Use realpath to prevent directory traversal
        $path = realpath(__DIR__ . '/assets/uploads/' . $filename);
        $uploads_dir = realpath(__DIR__ . '/assets/uploads');
        if (!$path || !$uploads_dir || strpos($path, $uploads_dir) !== 0) {
            $response['message'] = 'Invalid file path';
            break;
        }
        if (file_exists($path) && unlink($path)) {
            \FreePBX::create()->Logger->log("Asset deleted: $filename");
            $response = ['status' => true];
        } else {
            $response['message'] = 'File not found or delete failed';
        }
        break;

    case 'get_driver':
        $model = $_REQUEST['model'] ?? '';
        if (!$model) { $response['message'] = 'No model'; break; }
        // Validate model name - only allow safe characters
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $model)) {
            $response['message'] = 'Invalid model name';
            break;
        }
        // Use realpath to prevent directory traversal
        $path = $templates_dir . '/' . $model . '.json';
        $real_path = realpath($path);
        $real_templates_dir = realpath($templates_dir);
        if (!$real_path || !$real_templates_dir || strpos($real_path, $real_templates_dir) !== 0) {
            $response['message'] = 'Template not found or invalid path';
            break;
        }
        if (!file_exists($real_path)) { $response['message'] = 'Template not found'; break; }
        $json = file_get_contents($real_path);
        $response = ['status' => true, 'json' => $json];
        break;

    case 'import_driver':
        $json = $_POST['json'] ?? '';
        $data = json_decode($json, true);
        if (!$data || empty($data['model'])) { $response['message'] = 'Invalid JSON or no model'; break; }
        // Validate model name - only allow safe characters
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['model'])) {
            $response['message'] = 'Invalid model name';
            break;
        }
        // Ensure path stays within templates directory
        $path = $templates_dir . '/' . $data['model'] . '.json';
        $real_templates_dir = realpath($templates_dir);
        if (!$real_templates_dir) {
            $response['message'] = 'Templates directory not accessible';
            break;
        }
        // Use PHP functions instead of shell_exec
        if (file_put_contents($path, $json, LOCK_EX) !== false) {
            // Verify the file is actually in the templates directory after creation
            $real_path = realpath($path);
            if (!$real_path || strpos($real_path, $real_templates_dir) !== 0) {
                @unlink($path);
                $response['message'] = 'Security error: Invalid path';
                break;
            }
            @chmod($path, 0644);
            // Note: chown may fail if not running as root, but that's acceptable
            @chown($path, 'asterisk');
            @chgrp($path, 'asterisk');
            $response = ['status' => true];
        } else {
            $response['message'] = 'Write failed';
        }
        break;

    case 'delete_driver':
        $model = $_POST['model'] ?? '';
        if (!$model) { $response['message'] = 'No model'; break; }
        // Validate model name - only allow safe characters
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $model)) {
            $response['message'] = 'Invalid model name';
            break;
        }
        // Use realpath to prevent directory traversal
        $path = $templates_dir . '/' . $model . '.json';
        $real_path = realpath($path);
        $real_templates_dir = realpath($templates_dir);
        if (!$real_path || !$real_templates_dir || strpos($real_path, $real_templates_dir) !== 0) {
            $response['message'] = 'Template not found or invalid path';
            break;
        }
        if (file_exists($real_path) && unlink($real_path)) {
            $response = ['status' => true];
        } else {
            $response['message'] = 'Delete failed';
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
        if (!$ext) { $response['message'] = 'No extension'; break; }
        $deviceInfo = \FreePBX::Core()->getDevice($ext);
        $secret = isset($deviceInfo['secret']) ? $deviceInfo['secret'] : '';
        if ($secret) {
            $response = ['status' => true, 'secret' => $secret];
        } else {
            $response['message'] = 'Secret not found';
        }
        break;
}

echo json_encode($response);
?>
