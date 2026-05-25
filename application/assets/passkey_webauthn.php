<?php

declare(strict_types=1);

/**
 * WebAuthn / Passkeys: Hilfsfunktionen und DB-Zugriff.
 */

$__passkeyAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_readable($__passkeyAutoload)) {
    require_once $__passkeyAutoload;
}

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithms;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

function passkey_vendor_ready(): bool
{
    return class_exists(AttestationObjectLoader::class);
}

function passkey_is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

/**
 * Relying Party ID (Hostname ohne Port). Optional in config.php: define('WEBAUTHN_RP_ID', 'beispiel.de');
 */
function passkey_get_rp_id(): string
{
    if (defined('WEBAUTHN_RP_ID') && is_string(WEBAUTHN_RP_ID) && WEBAUTHN_RP_ID !== '') {
        return WEBAUTHN_RP_ID;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return preg_replace('/:\d+$/', '', $host) ?: 'localhost';
}

function passkey_get_rp_entity(): PublicKeyCredentialRpEntity
{
    $name = defined('WEBAUTHN_RP_NAME') && is_string(WEBAUTHN_RP_NAME) && WEBAUTHN_RP_NAME !== ''
        ? WEBAUTHN_RP_NAME
        : 'Serohub';
    return PublicKeyCredentialRpEntity::create($name, passkey_get_rp_id());
}

function passkey_random_challenge(int $bytes = 32): string
{
    return random_bytes($bytes);
}

function passkey_attestation_support_manager(): AttestationStatementSupportManager
{
    $algManager = Manager::create()->add(ES256::create(), RS256::create());
    return new AttestationStatementSupportManager([
        PackedAttestationStatementSupport::create($algManager),
        AppleAttestationStatementSupport::create(),
    ]);
}

function passkey_ceremony_factory_for_attestation(): CeremonyStepManagerFactory
{
    $f = new CeremonyStepManagerFactory();
    $f->setAttestationStatementSupportManager(passkey_attestation_support_manager());
    return $f;
}

function passkey_public_key_loader(): PublicKeyCredentialLoader
{
    return PublicKeyCredentialLoader::create(
        AttestationObjectLoader::create(passkey_attestation_support_manager())
    );
}

function passkey_attestation_validator(): AuthenticatorAttestationResponseValidator
{
    $factory = passkey_ceremony_factory_for_attestation();
    return AuthenticatorAttestationResponseValidator::create(
        null,
        null,
        null,
        null,
        null,
        $factory->creationCeremony(null)
    );
}

function passkey_assertion_validator(): AuthenticatorAssertionResponseValidator
{
    $factory = new CeremonyStepManagerFactory();
    return AuthenticatorAssertionResponseValidator::create(
        null,
        null,
        null,
        null,
        null,
        $factory->requestCeremony(null)
    );
}

function passkey_ensure_webauthn_user_handle(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT webauthn_user_handle FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $existing = $row['webauthn_user_handle'] ?? null;
    if (is_string($existing) && $existing !== '') {
        return $existing;
    }
    $handle = random_bytes(32);
    $upd = $pdo->prepare('UPDATE users SET webauthn_user_handle = ? WHERE id = ?');
    $upd->execute([$handle, $userId]);
    return $handle;
}

function passkey_user_display_name(array $user): string
{
    $n = trim((string) ($user['vorname'] ?? '') . ' ' . (string) ($user['nachname'] ?? ''));
    if ($n !== '') {
        return $n;
    }
    return (string) ($user['email'] ?? 'Benutzer');
}

function passkey_load_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, email, vorname, nachname, company_id, customer_id, status, fehlversuche, gesperrt, gesperrt_bis,
                passwort_zuruecksetzen, onboarding_abgeschlossen
         FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

/** @return string|null Fehlermeldung oder null wenn Login erlaubt */
function passkey_account_login_error(PDO $pdo, array $user): ?string
{
    if (!empty($user['company_id'])) {
        $companyStmt = $pdo->prepare('SELECT id, name, status FROM companies WHERE id = ?');
        $companyStmt->execute([(int) $user['company_id']]);
        $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
        if ($company && $company['status'] === 'gesperrt') {
            return 'Die Firma "' . htmlspecialchars((string) $company['name']) . '" ist gesperrt. Sie können sich derzeit nicht anmelden.';
        }
    }
    if (!empty($user['customer_id'])) {
        $customerStmt = $pdo->prepare('SELECT id, name, status FROM customers WHERE id = ?');
        $customerStmt->execute([(int) $user['customer_id']]);
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
        if ($customer && $customer['status'] === 'gesperrt') {
            return 'Der Kunde "' . htmlspecialchars((string) $customer['name']) . '" ist gesperrt. Sie können sich derzeit nicht anmelden.';
        }
    }
    if (!empty($user['gesperrt']) && (int) $user['gesperrt'] === 1) {
        return 'Ihr Account wurde gesperrt. Bitte kontaktieren Sie den Administrator.';
    }
    if (!empty($user['gesperrt_bis']) && strtotime((string) $user['gesperrt_bis']) > time()) {
        return 'Ihr Account wurde aufgrund zu vieler fehlgeschlagener Login-Versuche gesperrt. Bitte versuchen Sie es später erneut.';
    }
    if (($user['status'] ?? '') === 'gesperrt') {
        return 'Ihr Account ist gesperrt. Bitte kontaktieren Sie den Administrator.';
    }
    return null;
}

/**
 * @return PublicKeyCredentialDescriptor[]
 */
function passkey_db_credential_descriptors_for_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT credential_data FROM user_passkeys WHERE user_id = ?');
    $stmt->execute([$userId]);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data = json_decode((string) $row['credential_data'], true, 512, JSON_THROW_ON_ERROR);
        $src = PublicKeyCredentialSource::createFromArray($data);
        $out[] = $src->getPublicKeyCredentialDescriptor();
    }
    return $out;
}

function passkey_db_find_source_by_credential_id(PDO $pdo, string $credentialIdBinary): ?PublicKeyCredentialSource
{
    $stmt = $pdo->prepare('SELECT credential_data, user_id FROM user_passkeys WHERE credential_id = ? LIMIT 1');
    $stmt->execute([$credentialIdBinary]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $data = json_decode((string) $row['credential_data'], true, 512, JSON_THROW_ON_ERROR);
    return PublicKeyCredentialSource::createFromArray($data);
}

function passkey_db_get_passkey_owner_user_id(PDO $pdo, string $credentialIdBinary): ?int
{
    $stmt = $pdo->prepare('SELECT user_id FROM user_passkeys WHERE credential_id = ? LIMIT 1');
    $stmt->execute([$credentialIdBinary]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return (int) $row['user_id'];
}

function passkey_db_save(PDO $pdo, int $userId, PublicKeyCredentialSource $source, ?string $label): void
{
    $json = json_encode($source->jsonSerialize(), JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare(
        'INSERT INTO user_passkeys (user_id, credential_id, credential_data, label) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $source->publicKeyCredentialId, $json, $label !== null && $label !== '' ? mb_substr($label, 0, 128) : null]);
}

function passkey_db_update_source(PDO $pdo, PublicKeyCredentialSource $source): void
{
    $json = json_encode($source->jsonSerialize(), JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare(
        'UPDATE user_passkeys SET credential_data = ? WHERE credential_id = ?'
    );
    $stmt->execute([$json, $source->publicKeyCredentialId]);
}

function passkey_db_count_for_user(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** @return list<array{id:int,label:?string,created_at:string}> */
function passkey_db_list(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, label, created_at FROM user_passkeys WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function passkey_db_delete(PDO $pdo, int $userId, int $passkeyId): bool
{
    $stmt = $pdo->prepare('DELETE FROM user_passkeys WHERE id = ? AND user_id = ?');
    $stmt->execute([$passkeyId, $userId]);
    return $stmt->rowCount() > 0;
}

function passkey_finalize_login_session(PDO $pdo, array $user, bool $rememberMe): void
{
    $uid = (int) $user['id'];
    $newStatus = 'aktiv';
    if (($user['status'] ?? '') === 'gesperrt' && empty($user['gesperrt'])) {
        $newStatus = 'aktiv';
    } elseif (($user['status'] ?? '') === 'aktiv') {
        $newStatus = 'aktiv';
    } else {
        $newStatus = (string) ($user['status'] ?? 'aktiv');
    }
    $upd = $pdo->prepare(
        'UPDATE users SET letzte_anmeldung = NOW(), fehlversuche = 0, gesperrt_bis = NULL, gesperrt = 0, status = ? WHERE id = ?'
    );
    $upd->execute([$newStatus, $uid]);

    $_SESSION['user_id'] = $uid;
    $_SESSION['email'] = (string) $user['email'];
    $_SESSION['session_login_method'] = 'passkey';
    if (function_exists('registerOrUpdateUserSession')) {
        registerOrUpdateUserSession();
    }
    if (function_exists('clearSessionsValidAfter')) {
        clearSessionsValidAfter($uid);
    }
    if ($rememberMe && function_exists('createRememberMeToken')) {
        createRememberMeToken($uid);
    }
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'easy_mode' LIMIT 1");
    $stmt->execute([$uid]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['easy_mode'] = ($r && $r['setting_value'] === '1') ? 1 : 0;
}

function passkey_redirect_after_login(PDO $pdo, array $user): string
{
    if (!empty($user['passwort_zuruecksetzen']) && (int) $user['passwort_zuruecksetzen'] === 1) {
        return '/webapp/passwort-aendern/';
    }
    $onboardingStmt = $pdo->prepare('SELECT onboarding_abgeschlossen FROM users WHERE id = ?');
    $onboardingStmt->execute([(int) $user['id']]);
    $onboardingUser = $onboardingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$onboardingUser || empty($onboardingUser['onboarding_abgeschlossen']) || (int) $onboardingUser['onboarding_abgeschlossen'] === 0) {
        return '/onboarding/';
    }
    if (isset($_SESSION['return_url']) && $_SESSION['return_url'] !== '') {
        $returnUrl = $_SESSION['return_url'];
        unset($_SESSION['return_url']);
        if (strpos($returnUrl, 'http://') !== 0 && strpos($returnUrl, 'https://') !== 0) {
            return $returnUrl;
        }
    }
    $uid = (int) $user['id'];
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'easy_mode'");
    $stmt->execute([$uid]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r && ($r['setting_value'] === '1' || $r['setting_value'] == 1)) {
        return '/easy/';
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|opera mini|windows phone|iemobile|webos)/i', $ua);
    if (!$isMobile) {
        return '/dashboard/';
    }

    $defaultMobilePath = '/tickets/';
    try {
        $mobileStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'mobile_start_page' LIMIT 1");
        $mobileStmt->execute([$uid]);
        $row = $mobileStmt->fetch(PDO::FETCH_ASSOC);

        $mode = 'fixed';
        $page = 'tickets';
        if ($row && is_string($row['setting_value'] ?? null) && $row['setting_value'] !== '') {
            $decoded = json_decode($row['setting_value'], true);
            if (is_array($decoded)) {
                $candidateMode = (string) ($decoded['mode'] ?? '');
                $candidatePage = (string) ($decoded['page'] ?? '');
                if (in_array($candidateMode, ['fixed', 'last'], true)) {
                    $mode = $candidateMode;
                }
                if (preg_match('/^[a-z0-9-]+$/i', $candidatePage)) {
                    $page = $candidatePage;
                }
            }
        }

        if ($mode === 'last') {
            $cookieName = 'mobile_last_path_user_' . $uid;
            $lastPath = rawurldecode((string) ($_COOKIE[$cookieName] ?? ''));
            if ($lastPath !== '' && str_starts_with($lastPath, '/')) {
                $isSafe = preg_match('#^/(dashboard|tickets|todos|inventory|service|knowledge|kalender|devices|orders|companies|customers|projects|notes)(/|$|\?)#', $lastPath) === 1;
                if ($isSafe) {
                    return $lastPath;
                }
            }
        }

        $allowedFixedPages = [
            'dashboard', 'tickets', 'todos', 'inventory', 'service', 'knowledge', 'kalender',
            'devices', 'orders', 'companies', 'customers', 'projects', 'notes'
        ];
        if (in_array($page, $allowedFixedPages, true)) {
            return '/' . $page . '/';
        }
    } catch (Throwable $e) {
        // Fallback
    }

    return $defaultMobilePath;
}

function passkey_build_registration_options(PDO $pdo, int $userId, array $userRow, ?string $fixedChallenge = null): PublicKeyCredentialCreationOptions
{
    $handle = passkey_ensure_webauthn_user_handle($pdo, $userId);
    $userEntity = PublicKeyCredentialUserEntity::create(
        (string) $userRow['email'],
        $handle,
        passkey_user_display_name($userRow)
    );
    $challenge = $fixedChallenge !== null && $fixedChallenge !== '' ? $fixedChallenge : passkey_random_challenge();
    $exclude = passkey_db_credential_descriptors_for_user($pdo, $userId);
    $selection = new AuthenticatorSelectionCriteria(
        AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
        AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED
    );
    return PublicKeyCredentialCreationOptions::create(
        passkey_get_rp_entity(),
        $userEntity,
        $challenge,
        [
            PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
        ],
        $selection,
        PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
        $exclude,
        120000
    );
}

function passkey_build_request_options(PDO $pdo, int $userId, ?string $fixedChallenge = null): PublicKeyCredentialRequestOptions
{
    $challenge = $fixedChallenge !== null && $fixedChallenge !== '' ? $fixedChallenge : passkey_random_challenge();
    $allow = passkey_db_credential_descriptors_for_user($pdo, $userId);
    return PublicKeyCredentialRequestOptions::create(
        $challenge,
        passkey_get_rp_id(),
        $allow,
        PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        120000
    );
}

function passkey_build_discoverable_request_options(?string $fixedChallenge = null): PublicKeyCredentialRequestOptions
{
    $challenge = $fixedChallenge !== null && $fixedChallenge !== '' ? $fixedChallenge : passkey_random_challenge();
    return PublicKeyCredentialRequestOptions::create(
        $challenge,
        passkey_get_rp_id(),
        [],
        PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        120000
    );
}
