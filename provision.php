<?php
// provision.php - HH Quick Provisioner v2.2 - Dynamic Engine
include '/etc/freepbx.conf';

function qp_is_local_network() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '::1') return true;
    if (preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip)) {
        return true;
    }
    return false;
}

$mac = isset($_GET['mac']) ? strtoupper(preg_replace('/[^A-F0-9]/', '', $_GET['mac'])) : null;
if (!$mac || strlen($mac) < 12) {
    \FreePBX::create()->Logger->log("Invalid MAC attempt: " . ($mac ?? 'none'));
    die("Invalid or no MAC provided");
}

global $db;
$device = $db->getRow("SELECT * FROM quickprovisioner_devices WHERE mac=?", [$mac]);
if (!$device) {
    \FreePBX::create()->Logger->log("Device not found for MAC: $mac");
    http_response_code(404);
    die("Device not found");
}

// Check authentication for remote access
if (!qp_is_local_network()) {
    // Log warning if remote provisioning over HTTP
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        \FreePBX::create()->Logger->log("WARNING: Remote provisioning over HTTP (non-HTTPS) for MAC: $mac");
    }
    
    $prov_user = $device['prov_username'] ?? '';
    $prov_pass = $device['prov_password'] ?? '';
    
    // Always require credentials for remote access
    if (empty($prov_user) || empty($prov_pass)) {
        header('WWW-Authenticate: Basic realm="Phone Provisioning"');
        header('HTTP/1.0 401 Unauthorized');
        \FreePBX::create()->Logger->log("Remote provisioning denied - no credentials configured for MAC: $mac");
        die('Authentication required');
    }
    
    $auth_user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $auth_pass = $_SERVER['PHP_AUTH_PW'] ?? '';
    
    if ($auth_user !== $prov_user || $auth_pass !== $prov_pass) {
        header('WWW-Authenticate: Basic realm="Phone Provisioning"');
        header('HTTP/1.0 401 Unauthorized');
        \FreePBX::create()->Logger->log("Unauthorized provisioning attempt for MAC: $mac");
        die('Authentication required');
    }
}

$model = basename($device['model']); // Sanitize to prevent path traversal
$profile_path = __DIR__ . '/templates/' . $model . '.json';
if (!file_exists($profile_path)) {
    die("Template not found for model $model");
}

$profile_json = file_get_contents($profile_path);
$profile = json_decode($profile_json, true);

if ($profile === null) {
    die("Invalid template JSON for model $model");
}

$content_type = $profile['provisioning']['content_type'] ?? 'text/plain';
$filename_pattern = $profile['provisioning']['filename_pattern'] ?? '{mac}.cfg';
$filename = str_replace('{mac}', $mac, $filename_pattern);
header("Content-Type: $content_type");
header("Content-Disposition: attachment; filename=\"$filename\"");

$custom_options = json_decode($device['custom_options_json'], true) ?? [];
$ext = $device['extension'];
$userInfo = \FreePBX::Core()->getUser($ext);
$display_name = $userInfo['name'] ?? $ext;
$secret = \FreePBX::Core()->getDevice($ext)['secret'] ?? '';
$server_ip = $_SERVER['SERVER_ADDR'];
$server_port = \FreePBX::Sipsettings()->get('bindport') ?? '5060';

$wpUrl = "";
if (!empty($device['wallpaper'])) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    // Do not embed credentials in URL - device will authenticate via Basic Auth headers
    $wpUrl = "$protocol://$host/admin/modules/quickprovisioner/media.php?mac=$mac";
}

$lockEnable = !empty($device['security_pin']) ? 1 : 0;

$vars = [
    '{{mac}}' => $mac,
    '{{extension}}' => $ext,
    '{{password}}' => $secret,
    '{{display_name}}' => $display_name,
    '{{server_host}}' => $server_ip,
    '{{server_port}}' => $server_port,
    '{{wallpaper}}' => $wpUrl,
    '{{security_pin}}' => $device['security_pin'] ?? '',
    '{{lock_enable}}' => $lockEnable
];

foreach ($custom_options as $key => $value) {
    if ($value !== '') {
        $vars['{{' . $key . '}}'] = $value;
    }
}

$template = $device['custom_template_override'] ? $device['custom_template_override'] : $profile['provisioning']['template'] ?? '';

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
            $photo_url = "$protocol://$host/admin/modules/quickprovisioner/media.php?file=" . $c['photo'] . "&mac=$mac&w=100&h=100&mode=crop";
        }
        $item = str_replace('{{photo_url}}', $photo_url, $item);
        $builtLoop .= $item;
    }
    $template = str_replace($matches[0], $builtLoop, $template);
}

foreach ($vars as $k => $v) {
    $template = str_replace($k, $v, $template);
}

echo $template;
?>
