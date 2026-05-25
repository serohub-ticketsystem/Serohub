<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/passkey_webauthn.php';

use Webauthn\AuthenticatorAssertionResponse;

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

if (isset($_SESSION['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bereits angemeldet.']);
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

$chB64 = $_SESSION['passkey_login_challenge_b64'] ?? '';
$challenge = is_string($chB64) && $chB64 !== '' ? base64_decode($chB64, true) : false;
$userId = (int) ($_SESSION['passkey_login_user_id'] ?? 0);
$mode = (string) ($_SESSION['passkey_login_mode'] ?? 'identified');
$remember = !empty($_SESSION['passkey_login_remember']);
if ($challenge === false || strlen($challenge) < 16) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Anmeldung abgelaufen. Bitte erneut mit Passkey starten.']);
    exit;
}
if ($mode === 'identified' && $userId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Anmeldung abgelaufen. Bitte erneut mit Passkey starten.']);
    exit;
}

try {
    $options = $mode === 'discoverable'
        ? passkey_build_discoverable_request_options($challenge)
        : passkey_build_request_options($pdo, $userId, $challenge);
    $pk = passkey_public_key_loader()->loadArray($data['credential']);
    $response = $pk->response;
    if (!$response instanceof AuthenticatorAssertionResponse) {
        throw new RuntimeException('Ungültige Antwort');
    }
    $credId = $pk->rawId;
    $ownerId = passkey_db_get_passkey_owner_user_id($pdo, $credId);
    if ($ownerId === null) {
        throw new RuntimeException('Credential mismatch');
    }
    if ($mode === 'identified' && $ownerId !== $userId) {
        throw new RuntimeException('Credential mismatch');
    }
    $userId = $ownerId;
    $source = passkey_db_find_source_by_credential_id($pdo, $credId);
    if ($source === null) {
        throw new RuntimeException('Credential nicht gefunden');
    }
    $stmt = $pdo->prepare('SELECT webauthn_user_handle FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $userHandle = is_array($row) && isset($row['webauthn_user_handle']) && is_string($row['webauthn_user_handle'])
        ? $row['webauthn_user_handle']
        : '';
    if ($userHandle === '') {
        throw new RuntimeException('Kein WebAuthn-Handle');
    }
    $updated = passkey_assertion_validator()->check($source, $response, $options, passkey_get_rp_id(), $userHandle);
    passkey_db_update_source($pdo, $updated);

    $stmt = $pdo->prepare(
        'SELECT id, email, vorname, nachname, company_id, customer_id, status, gesperrt, gesperrt_bis,
                passwort_zuruecksetzen, onboarding_abgeschlossen, letzte_anmeldung
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('Benutzer fehlt');
    }
    $err = passkey_account_login_error($pdo, $user);
    if ($err !== null) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => strip_tags($err)]);
        exit;
    }

    if (defined('DEMO_EMAIL') && defined('DEMO_MODE') && !DEMO_MODE && strcasecmp($user['email'], DEMO_EMAIL) === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Dieser Account ist nur im Demo-Modus verfügbar.']);
        exit;
    }

    if (empty($user['letzte_anmeldung'])) {
        $_SESSION['is_first_login'] = true;
    }
    passkey_finalize_login_session($pdo, $user, $remember);
    $redirect = passkey_redirect_after_login($pdo, $user);
    unset($_SESSION['passkey_login_challenge_b64'], $_SESSION['passkey_login_user_id'], $_SESSION['passkey_login_mode'], $_SESSION['passkey_login_remember']);

    echo json_encode(['success' => true, 'redirect' => $redirect], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('passkey-login-verify: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Passkey-Anmeldung fehlgeschlagen. Bitte erneut versuchen.']);
}
