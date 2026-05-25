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
    echo json_encode(['success' => false, 'error' => 'Passkeys sind auf diesem Server nicht verfügbar.']);
    exit;
}

if (!passkey_is_https_request()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Passkeys erfordern HTTPS.']);
    exit;
}

requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, email, vorname, nachname FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden.']);
        exit;
    }
    $options = passkey_build_registration_options($pdo, $userId, $user);
    $_SESSION['passkey_register_challenge_b64'] = base64_encode($options->challenge);
    echo json_encode(['success' => true, 'options' => $options->jsonSerialize()], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('passkey-register-options: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Passkey-Registrierung konnte nicht gestartet werden.']);
}
