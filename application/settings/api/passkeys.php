<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/passkey_webauthn.php';

if (!passkey_vendor_ready()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Passkeys nicht verfügbar.']);
    exit;
}

requireLogin();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $list = passkey_db_list($pdo, $userId);
        echo json_encode(['success' => true, 'passkeys' => $list], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ungültige ID.']);
            exit;
        }
        if (!passkey_db_delete($pdo, $userId, $id)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Passkey nicht gefunden.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Passkey entfernt.'], JSON_THROW_ON_ERROR);
        exit;
    }
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt.']);
} catch (Throwable $e) {
    error_log('passkeys api: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Aktion fehlgeschlagen.']);
}
