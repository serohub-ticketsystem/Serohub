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
$body = json_decode($raw ?: '[]', true);
$email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : '';

try {
    $mode = 'discoverable';
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
            exit;
        }
        $user = passkey_load_user_by_email($pdo, $email);
        $generic = ['success' => false, 'error' => 'Passkey-Anmeldung ist für diese E-Mail nicht möglich.'];
        if (!$user) {
            http_response_code(400);
            echo json_encode($generic);
            exit;
        }
        $err = passkey_account_login_error($pdo, $user);
        if ($err !== null) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => strip_tags($err)]);
            exit;
        }
        if (passkey_db_count_for_user($pdo, (int) $user['id']) < 1) {
            http_response_code(400);
            echo json_encode($generic);
            exit;
        }
        $options = passkey_build_request_options($pdo, (int) $user['id']);
        $_SESSION['passkey_login_user_id'] = (int) $user['id'];
        $mode = 'identified';
    } else {
        $options = passkey_build_discoverable_request_options();
        unset($_SESSION['passkey_login_user_id']);
    }
    $_SESSION['passkey_login_challenge_b64'] = base64_encode($options->challenge);
    $_SESSION['passkey_login_mode'] = $mode;
    $_SESSION['passkey_login_remember'] = !empty($body['remember_me']);
    echo json_encode(['success' => true, 'options' => $options->jsonSerialize()], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('passkey-login-options: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Anmeldung konnte nicht gestartet werden.']);
}
