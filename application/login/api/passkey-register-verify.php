<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/passkey_webauthn.php';

use Webauthn\AuthenticatorAttestationResponse;

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

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data) || !isset($data['credential']) || !is_array($data['credential'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage.']);
    exit;
}

$chB64 = $_SESSION['passkey_register_challenge_b64'] ?? '';
$challenge = is_string($chB64) && $chB64 !== '' ? base64_decode($chB64, true) : false;
if ($challenge === false || strlen($challenge) < 16) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Registrierung abgelaufen. Bitte erneut starten.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, email, vorname, nachname FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('Benutzer nicht gefunden');
    }
    $options = passkey_build_registration_options($pdo, $userId, $user, $challenge);
    $pk = passkey_public_key_loader()->loadArray($data['credential']);
    $response = $pk->response;
    if (!$response instanceof AuthenticatorAttestationResponse) {
        throw new RuntimeException('Ungültige Antwort');
    }
    $source = passkey_attestation_validator()->check($response, $options, passkey_get_rp_id());
    $label = isset($data['label']) && is_string($data['label']) ? $data['label'] : null;
    passkey_db_save($pdo, $userId, $source, $label);
    unset($_SESSION['passkey_register_challenge_b64']);
    echo json_encode(['success' => true, 'message' => 'Passkey wurde gespeichert.'], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('passkey-register-verify: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Passkey konnte nicht bestätigt werden. Bitte versuche es erneut.']);
}
