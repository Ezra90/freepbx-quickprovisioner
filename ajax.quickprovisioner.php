<?php
// ajax.quickprovisioner.php - HH Quick Provisioner v2.1 - Backend API
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
        if (!mkdir($dir, 0775, true)) {
            return ['status' => false, 'message' => 'Failed to create directory: ' . $dir];
        }
    }
    if (file_put_contents($filepath, $content) === false) {
        return ['status' => false, 'message' => 'Failed to write file: ' . basename($filepath)];
    }
    chmod($filepath, 0644);
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
        if (!mkdir($dir, 0775, true)) {
            return ['status' => false, 'message' => 'Failed to create directory: ' . $dir];
        }
    }
    if (!move_uploaded_file($tmp_file, $target)) {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
    chmod($target, 0644);
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
        if (!$id || !is_numeric($id)) { $response['message'] = 'Invalid ID'; break; }
        $row = $db->getRow("SELECT * FROM quickprovisioner_devices WHERE id=?", [(int)$id]);
        $response = ['status' => true, 'data' => $row ?: null];
        break;

    case 'list_devices':
        $rows = $db->query("SELECT * FROM quickprovisioner_devices ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $response = ['status' => true, 'devices' => $rows];
        break;

    case 'delete_device':
        $id = $_REQUEST['id'] ?? null;
        if (!$id || !is_numeric($id)) { $response['message'] = 'Invalid ID'; break; }
        $db->query("DELETE FROM quickprovisioner_devices WHERE id=?", [(int)$id]);
        \FreePBX::create()->Logger->log("Device deleted: ID=$id");
        $response = ['status' => true];
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
        $custom_options = json_decode($device['custom_options_json'], true) ?? [];
        $template = $device['custom_template_override'] ? $device['custom_template_override'] : $profile['provisioning']['template'] ?? '';
        $ext = $device['extension'];
        $userInfo = \FreePBX::Core()->getUser($ext);
        $display_name = $userInfo['name'] ?? $ext;
        $secret = \FreePBX::Core()->getDevice($ext)['secret'] ?? '';
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
        $result = qp_safe_move_upload($_FILES['file']['tmp_name'], $target);
        if ($result['status']) {
            \FreePBX::create()->Logger->log("Asset uploaded: $filename");
            $response = ['status' => true, 'url' => $filename];
        } else {
            $response['message'] = $result['message'];
        }
        break;

    case 'delete_asset':
        $filename = basename($_POST['filename'] ?? '');
        $path = __DIR__ . '/assets/uploads/' . $filename;
        $result = qp_safe_delete($path);
        if ($result['status']) {
            \FreePBX::create()->Logger->log("Asset deleted: $filename");
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
            $response['message'] = 'Invalid JSON or no model field: ' . json_last_error_msg();
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
        if (!$ext) { $response['message'] = 'No extension'; break; }
        $secret = \FreePBX::Core()->getDevice($ext)['secret'] ?? '';
        if ($secret) {
            $response = ['status' => true, 'secret' => $secret];
        } else {
            $response['message'] = 'Secret not found';
        }
        break;
}

echo json_encode($response);
?>
