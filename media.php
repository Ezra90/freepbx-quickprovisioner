<?php
// media.php - HH Quick Provisioner v2.0.0 - Secure Resizer
include '/etc/freepbx.conf';

$authorized = false;

if (isset($_SESSION['AMP_user']) && is_object($_SESSION['AMP_user'])) {
    $authorized = true;
}

$mac = $_GET['mac'] ?? null;
if (!$authorized && isset($_SERVER['PHP_AUTH_USER'])) {
    global $db;
    $ext = $_SERVER['PHP_AUTH_USER'];
    $pass = $_SERVER['PHP_AUTH_PW'] ?? '';
    $device = $db->getRow("SELECT * FROM quickprovisioner_devices WHERE extension=?", [$ext]);
    if ($device) {
        $deviceInfo = \FreePBX::Core()->getDevice($ext);
        $secret = isset($deviceInfo['secret']) ? $deviceInfo['secret'] : '';
        if ($pass === $secret) {
            $authorized = true;
        }
    }
}

if (!$authorized && $mac) {
    global $db;
    $count = $db->getOne("SELECT COUNT(*) FROM quickprovisioner_devices WHERE mac=?", [$mac]);
    if ($count > 0) {
        $authorized = true;
    }
}

if (!$authorized) {
    \FreePBX::create()->Logger->log("Unauthorized media access attempt: ext=" . ($_SERVER['PHP_AUTH_USER'] ?? 'none') . ", mac=" . ($mac ?? 'none'));
    header('WWW-Authenticate: Basic realm="Provisioner"');
    header('HTTP/1.0 401 Unauthorized');
    die('Access Denied');
}

$file = $_GET['file'] ?? '';
$req_w = (int)($_GET['w'] ?? 0);
$req_h = (int)($_GET['h'] ?? 0);
$mode = $_GET['mode'] ?? 'crop';

$path = __DIR__ . '/assets/uploads/' . basename($file);
if (!file_exists($path) || empty($file)) {
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    exit;
}

if ($mac && ($req_w == 0 || $req_h == 0)) {
    global $db;
    $device = $db->getRow("SELECT model FROM quickprovisioner_devices WHERE mac=?", [$mac]);
    if ($device) {
        $profile_path = __DIR__ . '/templates/' . $device['model'] . '.json';
        if (file_exists($profile_path)) {
            $profile = json_decode(file_get_contents($profile_path), true);
            $sch = $profile['visual_editor']['schematic'] ?? null;
            if ($sch) {
                if ($req_w == 0) $req_w = $sch['screen_width'] ?? 800;
                if ($req_h == 0) $req_h = $sch['screen_height'] ?? 480;
            }
        }
    }
}

if ($req_w == 0) $req_w = 800;
if ($req_h == 0) $req_h = 480;

$info = getimagesize($path);
if (!$info) die("Invalid image");
list($orig_w, $orig_h, $type) = $info;

switch ($type) {
    case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($path); break;
    case IMAGETYPE_PNG: $src = imagecreatefrompng($path); break;
    case IMAGETYPE_GIF: $src = imagecreatefromgif($path); break;
    default: die("Unsupported format");
}

$dst = imagecreatetruecolor($req_w, $req_h);

if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $trans);
} else {
    $black = imagecolorallocate($dst, 0, 0, 0);
    imagefill($dst, 0, 0, $black);
}

$src_ratio = $orig_w / $orig_h;
$dst_ratio = $req_w / $req_h;

if ($mode === 'crop') {
    if ($src_ratio > $dst_ratio) {
        $nh = $req_h;
        $nw = $req_h * $src_ratio;
    } else {
        $nw = $req_w;
        $nh = $req_w / $src_ratio;
    }
} else {
    if ($src_ratio > $dst_ratio) {
        $nw = $req_w;
        $nh = $req_w / $src_ratio;
    } else {
        $nh = $req_h;
        $nw = $req_h * $src_ratio;
    }
}

$x = ($req_w - $nw) / 2;
$y = ($req_h - $nh) / 2;

imagecopyresampled($dst, $src, $x, $y, 0, 0, $nw, $nh, $orig_w, $orig_h);

if ($type == IMAGETYPE_PNG) {
    header('Content-Type: image/png');
    imagepng($dst);
} elseif ($type == IMAGETYPE_GIF) {
    header('Content-Type: image/gif');
    imagegif($dst);
} else {
    header('Content-Type: image/jpeg');
    imagejpeg($dst, null, 90);
}

imagedestroy($src);
imagedestroy($dst);
?>
