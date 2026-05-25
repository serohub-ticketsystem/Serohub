<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$currentSessionId = (string) session_id();
$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body)) {
    $body = [];
}

$targetId = isset($body['id']) ? (int) $body['id'] : 0;
$targetSid = trim((string) ($body['sid'] ?? ''));
if ($targetId <= 0 && $targetSid === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiges Gerät']);
    exit;
}

try {
    if ($targetId > 0) {
        $checkStmt = $pdo->prepare("SELECT session_id FROM user_sessions WHERE id = ? AND user_id = ? LIMIT 1");
        $checkStmt->execute([$targetId, $userId]);
    } else {
        $checkStmt = $pdo->prepare("SELECT session_id FROM user_sessions WHERE session_id = ? AND user_id = ? LIMIT 1");
        $checkStmt->execute([$targetSid, $userId]);
    }
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Gerät nicht gefunden']);
        exit;
    }
    $rowSid = (string) ($row['session_id'] ?? '');
    if ($rowSid === $currentSessionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aktuelles Gerät kann hier nicht abgemeldet werden']);
        exit;
    }

    if (function_exists('addRevokedSessionId')) {
        addRevokedSessionId($userId, $rowSid);
    }

    $del = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id = ? LIMIT 1");
    $del->execute([$userId, $rowSid]);
    if ($del->rowCount() < 1) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gerät konnte nicht abgemeldet werden']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Gerät wurde abgemeldet']);
} catch (Throwable $e) {
    error_log('Logout device API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Aktion fehlgeschlagen']);
}
