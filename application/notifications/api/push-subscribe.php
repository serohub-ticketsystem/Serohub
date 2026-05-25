<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/push_notifications.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

if (!webpush_is_configured()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Push ist auf dem Server nicht konfiguriert']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

webpush_ensure_table();

if ($method === 'DELETE') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    $endpoint = isset($data['endpoint']) ? (string) $data['endpoint'] : '';
    if ($endpoint === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'endpoint fehlt']);
        exit;
    }
    try {
        $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_sha256 = ?');
        $stmt->execute([$userId, hash('sha256', $endpoint)]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
        error_log('push-subscribe DELETE: ' . $e->getMessage());
    }
    exit;
}

if ($method !== 'POST' && $method !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige JSON-Daten']);
    exit;
}

$endpoint = isset($data['endpoint']) ? trim((string) $data['endpoint']) : '';
$keys = isset($data['keys']) && is_array($data['keys']) ? $data['keys'] : [];
$p256dh = isset($keys['p256dh']) ? (string) $keys['p256dh'] : '';
$auth = isset($keys['auth']) ? (string) $keys['auth'] : '';
$contentEncoding = isset($data['contentEncoding']) ? (string) $data['contentEncoding'] : 'aesgcm';

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'endpoint, keys.p256dh und keys.auth sind erforderlich']);
    exit;
}

if ($contentEncoding !== 'aesgcm' && $contentEncoding !== 'aes128gcm') {
    $contentEncoding = 'aesgcm';
}

$endpointSha = hash('sha256', $endpoint);
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512) : null;

try {
    $stmt = $pdo->prepare('
        INSERT INTO push_subscriptions
            (user_id, endpoint, endpoint_sha256, p256dh, auth_secret, content_encoding, user_agent, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            p256dh = VALUES(p256dh),
            auth_secret = VALUES(auth_secret),
            content_encoding = VALUES(content_encoding),
            user_agent = VALUES(user_agent),
            updated_at = NOW()
    ');
    $stmt->execute([
        $userId,
        $endpoint,
        $endpointSha,
        $p256dh,
        $auth,
        $contentEncoding,
        $userAgent,
    ]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Speichern fehlgeschlagen']);
    error_log('push-subscribe POST: ' . $e->getMessage());
}
