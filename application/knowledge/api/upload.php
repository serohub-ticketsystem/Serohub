<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST erlaubt']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Zugriff verweigert']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

$pageId = isset($_POST['page_id']) ? trim($_POST['page_id']) : null;
if (!$pageId) {
    echo json_encode(['success' => false, 'error' => 'page_id fehlt']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$pageId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Seite nicht gefunden']);
    exit;
}

$allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedDocs = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'text/csv'];
$allowed = array_merge($allowedImages, $allowedDocs);
$maxSize = 15 * 1024 * 1024; // 15 MB

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Keine Datei oder Upload-Fehler']);
    exit;
}

$file = $_FILES['file'];
$mime = mime_content_type($file['tmp_name']) ?: $file['type'];
if (!in_array($mime, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Dateityp nicht erlaubt']);
    exit;
}
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Datei zu groß (max. 15 MB)']);
    exit;
}

$baseDir = dirname(__DIR__, 2) . '/uploads/knowledge';
if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);
$subDir = $baseDir . '/' . substr($pageId, 0, 2);
if (!is_dir($subDir)) mkdir($subDir, 0755, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin';
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name'], $ext));
$name = $safeName . ($ext ? '.' . $ext : '');
$unique = substr(bin2hex(random_bytes(8)), 0, 16) . '_' . $name;
$relPath = 'knowledge/' . substr($pageId, 0, 2) . '/' . $unique;
$absPath = $baseDir . '/' . substr($pageId, 0, 2) . '/' . $unique;

if (!move_uploaded_file($file['tmp_name'], $absPath)) {
    echo json_encode(['success' => false, 'error' => 'Speichern fehlgeschlagen']);
    exit;
}

try {
    $baseUrl = rtrim(BASE_URL, '/');
    $stmt = $pdo->prepare("INSERT INTO kb_attachments (page_id, file_path, file_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$pageId, $relPath, $file['name'], $mime, (int) $file['size'], $userId]);
} catch (PDOException $e) {
    @unlink($absPath);
    error_log('KB upload insert: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

$url = $baseUrl . '/uploads/' . $relPath;
$isImage = in_array($mime, $allowedImages);
echo json_encode([
    'success' => true,
    'url' => $url,
    'path' => $relPath,
    'file_name' => $file['name'],
    'mime_type' => $mime,
    'is_image' => $isImage
]);
