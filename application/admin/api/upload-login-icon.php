<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['rolle'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['icon'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Keine Datei gesendet']);
    exit;
}

$file = $_FILES['icon'];
$maxMb = 2;
$maxBytes = $maxMb * 1024 * 1024;
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
$exts = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg'
];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload-Fehler: ' . $file['error']]);
    exit;
}
if ($file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Max. ' . $maxMb . ' MB erlaubt']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : $file['type'];
if ($finfo) finfo_close($finfo);
if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nur JPG, PNG, WebP, GIF, SVG erlaubt']);
    exit;
}

$baseDir = dirname(__DIR__, 2) . '/uploads/login-icons/';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}
if (!is_writable($baseDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Upload-Ordner nicht beschreibbar']);
    exit;
}

$ext = $exts[$mime] ?? 'png';
$name = 'icon_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$path = $baseDir . $name;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datei konnte nicht gespeichert werden']);
    exit;
}

$relative = 'uploads/login-icons/' . $name;
echo json_encode([
    'success' => true,
    'path' => $relative,
    'name' => $name
]);
