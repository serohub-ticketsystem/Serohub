<?php
// config zuerst laden, damit session.*-ini-Werte (z. B. 30 Tage) vor session_start() greifen
require_once '../assets/config.php';
require_once '../assets/notifications.php';
require_once '../assets/totp.php';
require_once '../assets/passkey_webauthn.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
$loginPasskeyAvailable = passkey_vendor_ready() && passkey_is_https_request();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// „Angemeldet bleiben“: Session aus Cookie wiederherstellen (wichtig für direkten Aufruf von /login/)
if (!isset($_SESSION['user_id']) && function_exists('tryRestoreSessionFromRememberMe')) {
    tryRestoreSessionFromRememberMe();
}

// Funktion zur Erkennung mobiler Geräte
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|opera mini|windows phone|iemobile|webos)/i', $userAgent);
}

// Funktion zum Laden des Easy Mode Wertes in die Session
function loadEasyModeToSession($userId) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT setting_value 
            FROM user_settings 
            WHERE user_id = :user_id AND setting_key = 'easy_mode'
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $_SESSION['easy_mode'] = ($result && $result['setting_value'] === '1') ? 1 : 0;
    } catch (PDOException $e) {
        // Bei Fehler Standard-Wert setzen
        error_log("Easy Mode Load Error: " . $e->getMessage());
        $_SESSION['easy_mode'] = 0;
    }
}

// Funktion zur Bestimmung des Zielordners basierend auf Gerätetyp und Easy Mode
function getRedirectPath($userId = null) {
    // Prüfen ob Easy Mode aktiviert ist
    if ($userId) {
        try {
            global $pdo;
            $stmt = $pdo->prepare("
                SELECT setting_value 
                FROM user_settings 
                WHERE user_id = :user_id AND setting_key = 'easy_mode'
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Debug-Logging (kann später entfernt werden)
            if ($result) {
                error_log("Easy Mode Check for user $userId: setting_value = '" . $result['setting_value'] . "'");
            } else {
                error_log("Easy Mode Check for user $userId: No setting found");
            }
            
            // Prüfen ob Easy Mode aktiviert ist (Wert kann '1' oder 1 sein)
            if ($result && ($result['setting_value'] === '1' || $result['setting_value'] == 1)) {
                error_log("Easy Mode active for user $userId - redirecting to /easy/");
                return '/easy/';
            }
        } catch (PDOException $e) {
            // Bei Fehler Standard-Weiterleitung verwenden
            error_log("Easy Mode Check Error: " . $e->getMessage());
        }
    }
    
    if (!isMobileDevice()) {
        return '/dashboard/';
    }

    $defaultMobilePath = '/tickets/';
    if (!$userId) {
        return $defaultMobilePath;
    }

    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'mobile_start_page' LIMIT 1");
        $stmt->execute([(int) $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

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
            $cookieName = 'mobile_last_path_user_' . (int) $userId;
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

// Return-URL aus GET-Parameter speichern (falls vorhanden)
if (isset($_GET['return_url']) && !empty($_GET['return_url'])) {
    $_SESSION['return_url'] = urldecode($_GET['return_url']);
}

// Funktion zur Bestimmung der Weiterleitungs-URL nach Login
function getLoginRedirectPath($userId = null) {
    // Prüfen ob eine Return-URL in der Session gespeichert ist
    if (isset($_SESSION['return_url']) && !empty($_SESSION['return_url'])) {
        $returnUrl = $_SESSION['return_url'];
        // Return-URL aus Session entfernen (nur einmal verwenden)
        unset($_SESSION['return_url']);
        // Sicherheitsprüfung: Nur relative URLs erlauben (keine externen URLs)
        if (strpos($returnUrl, 'http://') !== 0 && strpos($returnUrl, 'https://') !== 0) {
            return $returnUrl;
        }
    }
    // Standard-Weiterleitung (mit User-ID für Easy Mode Prüfung)
    return getRedirectPath($userId);
}

// Wenn bereits eingeloggt, weiterleiten
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getLoginRedirectPath($_SESSION['user_id']));
    exit();
}

// 2FA-Pending Session abbrechen
if (isset($_GET['cancel_2fa']) && $_GET['cancel_2fa'] == '1') {
    unset($_SESSION['2fa_pending_user_id']);
    // Return-URL beibehalten falls vorhanden
    $returnUrlParam = isset($_SESSION['return_url']) ? '?return_url=' . urlencode($_SESSION['return_url']) : '';
    header('Location: /login/' . $returnUrlParam);
    exit();
}

$error = '';
$success = '';

// 2FA-Verifizierung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    $code = trim($_POST['twofa_code'] ?? '');
    
    if (empty($code)) {
        $error = 'Bitte gib den 2FA-Code ein.';
    } else {
        // Prüfen ob 2FA-Pending Session existiert
        if (!isset($_SESSION['2fa_pending_user_id'])) {
            $error = 'Ungültige Session. Bitte melde dich erneut an.';
            session_destroy();
        } else {
            $pendingUserId = $_SESSION['2fa_pending_user_id'];
            
            try {
                // 2FA-Secret aus Datenbank abrufen
                $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_secret'");
                $stmt->execute([$pendingUserId]);
                $setting = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$setting || empty($setting['setting_value'])) {
                    throw new Exception('2FA ist nicht konfiguriert. Bitte kontaktiere den Administrator.');
                }
                
                $secret = $setting['setting_value'];
                
                // Code validieren
                if (TOTP::verifyCode($secret, $code)) {
                    // Prüfen ob Gerät als vertraut markiert werden soll
                    $trustDevice = isset($_POST['trust_device']) && $_POST['trust_device'] === '1';
                    $deviceFingerprint = $_SESSION['device_fingerprint'] ?? generateDeviceFingerprint();
                    
                    // 2FA erfolgreich - vollständiger Login
                    $_SESSION['user_id'] = $pendingUserId;
                    $_SESSION['session_login_method'] = 'password_2fa';
                    $wantRemember = (!empty($_SESSION['remember_me_pending']) || (isset($_POST['remember_me']) && $_POST['remember_me'] === '1'));
                    if ($wantRemember && function_exists('createRememberMeToken')) {
                        createRememberMeToken($pendingUserId);
                    }
                    unset($_SESSION['remember_me_pending']);
                    
                    // Benutzerdaten für Weiterleitung abrufen
                    $stmt = $pdo->prepare("SELECT email, passwort_zuruecksetzen, onboarding_abgeschlossen FROM users WHERE id = ?");
                    $stmt->execute([$pendingUserId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $_SESSION['email'] = $user['email'];
                    
                    // Easy Mode in Session laden
                    loadEasyModeToSession($pendingUserId);
                    
                    // Gerät als vertraut speichern, falls gewünscht
                    if ($trustDevice && !empty($deviceFingerprint)) {
                        $deviceName = $_POST['device_name'] ?? null;
                        if (empty($deviceName)) {
                            // Automatischen Gerätenamen generieren
                            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                            if (preg_match('/(Windows|Mac|Linux|iPhone|iPad|Android)/i', $userAgent, $matches)) {
                                $os = $matches[1];
                                if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera)[\/\s](\d+\.\d+)/i', $userAgent, $browserMatches)) {
                                    $browser = $browserMatches[1];
                                    $deviceName = $os . ' - ' . $browser;
                                } else {
                                    $deviceName = $os . ' Browser';
                                }
                            } else {
                                $deviceName = 'Unbekanntes Gerät';
                            }
                        }
                        saveTrustedDevice($pendingUserId, $deviceFingerprint, $deviceName);
                    }
                    
                    // 2FA-Pending Session entfernen
                    unset($_SESSION['2fa_pending_user_id']);
                    unset($_SESSION['device_fingerprint']);
                    
                    if (function_exists('registerOrUpdateUserSession')) {
                        registerOrUpdateUserSession();
                    }
                    if (function_exists('clearSessionsValidAfter')) {
                        clearSessionsValidAfter($pendingUserId);
                    }
                    
                    // Prüfen ob Passwort zurückgesetzt werden muss
                    if ($user['passwort_zuruecksetzen'] == 1) {
                        header('Location: /webapp/passwort-aendern/');
                        exit();
                    }
                    
                    // Wenn Onboarding nicht abgeschlossen wurde, zum Onboarding weiterleiten
                    if (!isset($user['onboarding_abgeschlossen']) || $user['onboarding_abgeschlossen'] == 0) {
                        header('Location: /onboarding/');
                        exit();
                    }
                    
                    header('Location: ' . getLoginRedirectPath($pendingUserId));
                    exit();
                } else {
                    $error = 'Ungültiger 2FA-Code. Bitte versuche es erneut.';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
                error_log("2FA Verification Error: " . $e->getMessage());
            }
        }
    }
}

// Login-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    if (empty($email) || empty($password)) {
        $error = 'Bitte füllen Sie alle Felder aus.';
    } elseif (defined('DEMO_EMAIL') && defined('DEMO_MODE') && !DEMO_MODE && strcasecmp($email, DEMO_EMAIL) === 0) {
        $error = 'Dieser Account ist nur im Demo-Modus verfügbar.';
    } else {
        try {
            // Benutzer aus Datenbank laden (inkl. Sperrstatus)
            $stmt = $pdo->prepare("SELECT id, email, passwort, company_id, customer_id, vorname, nachname, rolle, status, fehlversuche, gesperrt, gesperrt_bis, letzte_anmeldung FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Prüfen ob Benutzer existiert
            if ($user) {
                // Prüfen ob Firma gesperrt ist (wenn Benutzer einer Firma zugeordnet ist)
                // Nur "gesperrt" blockieren, "inaktiv" ist erlaubt
                if ($user['company_id']) {
                    $companyStmt = $pdo->prepare("SELECT id, name, status FROM companies WHERE id = ?");
                    $companyStmt->execute([$user['company_id']]);
                    $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($company && $company['status'] === 'gesperrt') {
                        $error = 'Die Firma "' . htmlspecialchars($company['name']) . '" ist gesperrt. Sie können sich derzeit nicht anmelden. Bitte kontaktieren Sie den Administrator.';
                    }
                }
                
                // Prüfen ob Kunde gesperrt ist (wenn Benutzer einem Kunden zugeordnet ist)
                if (empty($error) && $user['customer_id']) {
                    $customerStmt = $pdo->prepare("SELECT id, name, status FROM customers WHERE id = ?");
                    $customerStmt->execute([$user['customer_id']]);
                    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($customer && $customer['status'] === 'gesperrt') {
                        $error = 'Der Kunde "' . htmlspecialchars($customer['name']) . '" ist gesperrt. Sie können sich derzeit nicht anmelden. Bitte kontaktieren Sie den Administrator.';
                    }
                }
                
                // Prüfen ob Account gesperrt ist (permanent) - nur wenn Firma nicht gesperrt ist
                if (empty($error) && $user['gesperrt'] == 1) {
                    $error = 'Ihr Account wurde gesperrt. Bitte kontaktieren Sie den Administrator.';
                }
                // Prüfen ob Account temporär gesperrt ist
                else if (empty($error) && $user['gesperrt_bis'] && strtotime($user['gesperrt_bis']) > time()) {
                    $error = 'Ihr Account wurde aufgrund zu vieler fehlgeschlagener Login-Versuche gesperrt. Bitte versuchen Sie es später erneut.';
                } 
                // Prüfen ob Account gesperrt ist
                // Nur "gesperrt" blockieren, "inaktiv" ist erlaubt
                else if (empty($error) && $user['status'] === 'gesperrt') {
                    $error = 'Ihr Account ist gesperrt. Bitte kontaktieren Sie den Administrator.';
                }
                // Passwort prüfen - nur wenn keine Fehler aufgetreten sind
                if (empty($error) && password_verify($password, $user['passwort'])) {
                    // Wenn Status 'gesperrt' war (temporär), auf 'aktiv' zurücksetzen
                    $newStatus = $user['status'];
                    // Nur wenn nicht permanent gesperrt (gesperrt = 0) und temporär gesperrt war
                    if ($user['status'] === 'gesperrt' && $user['gesperrt'] == 0) {
                        // Temporär gesperrt - Status auf 'aktiv' zurücksetzen
                        $newStatus = 'aktiv';
                    } elseif ($user['status'] === 'aktiv') {
                        // Bereits aktiv - Status beibehalten
                        $newStatus = 'aktiv';
                    }
                    // Wenn permanent gesperrt (gesperrt = 1), Status bleibt 'gesperrt'
                    
                    // Erster Login erkennen (letzte_anmeldung war noch nie gesetzt)
                    if (empty($user['letzte_anmeldung'])) {
                        $_SESSION['is_first_login'] = true;
                    }
                    
                    // Letzte Anmeldung aktualisieren, fehlgeschlagene Versuche zurücksetzen und Status prüfen
                    $updateStmt = $pdo->prepare("UPDATE users SET letzte_anmeldung = NOW(), fehlversuche = 0, gesperrt_bis = NULL, gesperrt = 0, status = ? WHERE id = ?");
                    $updateStmt->execute([$newStatus, $user['id']]);
                    
                    // Prüfen ob 2FA aktiviert ist
                    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_enabled'");
                    $stmt->execute([$user['id']]);
                    $twoFaSetting = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $twoFaEnabled = $twoFaSetting && $twoFaSetting['setting_value'] === '1';
                    
                    if ($twoFaEnabled) {
                        // Prüfen ob Gerät vertraut ist
                        $deviceFingerprint = generateDeviceFingerprint();
                        $isTrusted = isDeviceTrusted($user['id'], $deviceFingerprint);
                        
                        if ($isTrusted) {
                            // Gerät ist vertraut - 2FA überspringen
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['session_login_method'] = 'password_trusted_device';
                            if (function_exists('registerOrUpdateUserSession')) {
                                registerOrUpdateUserSession();
                            }
                            if (function_exists('clearSessionsValidAfter')) {
                                clearSessionsValidAfter($user['id']);
                            }
                            if (isset($_POST['remember_me']) && $_POST['remember_me'] === '1' && function_exists('createRememberMeToken')) {
                                createRememberMeToken($user['id']);
                            }
                            // Rolle wird nicht in Session gespeichert, sondern immer aus DB abgerufen
                            
                            // Easy Mode in Session laden
                            loadEasyModeToSession($user['id']);
                            
                            // Prüfen ob Onboarding abgeschlossen wurde
                            $onboardingStmt = $pdo->prepare("SELECT onboarding_abgeschlossen FROM users WHERE id = ?");
                            $onboardingStmt->execute([$user['id']]);
                            $onboardingUser = $onboardingStmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Prüfen ob Passwort zurückgesetzt werden muss
                            if ($user['passwort_zuruecksetzen'] == 1) {
                                header('Location: /webapp/passwort-aendern/');
                                exit();
                            }
                            
                            // Wenn Onboarding nicht abgeschlossen wurde, zum Onboarding weiterleiten
                            if (!$onboardingUser || !isset($onboardingUser['onboarding_abgeschlossen']) || $onboardingUser['onboarding_abgeschlossen'] == 0) {
                                header('Location: /onboarding/');
                                exit();
                            }
                            
                            header('Location: ' . getLoginRedirectPath($user['id']));
                            exit();
                        } else {
                            // 2FA aktiviert - Code abfragen
                            // Benutzer in 2FA-Pending Session setzen (noch nicht vollständig eingeloggt)
                            $_SESSION['2fa_pending_user_id'] = $user['id'];
                            $_SESSION['device_fingerprint'] = $deviceFingerprint; // Für spätere Speicherung
                            $_SESSION['remember_me_pending'] = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
                            // Erfolgreiche Passwort-Authentifizierung wird durch Anzeige des 2FA-Formulars signalisiert
                            $success = 'Bitte gib deinen 2FA-Code ein.';
                        }
                    } else {
                        // Keine 2FA - vollständiger Login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['session_login_method'] = 'password';
                        if (function_exists('registerOrUpdateUserSession')) {
                            registerOrUpdateUserSession();
                        }
                        if (function_exists('clearSessionsValidAfter')) {
                            clearSessionsValidAfter($user['id']);
                        }
                        if (isset($_POST['remember_me']) && $_POST['remember_me'] === '1' && function_exists('createRememberMeToken')) {
                            createRememberMeToken($user['id']);
                        }
                        // Rolle wird nicht in Session gespeichert, sondern immer aus DB abgerufen
                        
                        // Easy Mode in Session laden
                        loadEasyModeToSession($user['id']);
                        
                        // Prüfen ob Onboarding abgeschlossen wurde
                        $onboardingStmt = $pdo->prepare("SELECT onboarding_abgeschlossen FROM users WHERE id = ?");
                        $onboardingStmt->execute([$user['id']]);
                        $onboardingUser = $onboardingStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Prüfen ob Passwort zurückgesetzt werden muss
                        if ($user['passwort_zuruecksetzen'] == 1) {
                            // Weiterleitung zur Passwort-Änderungsseite
                            header('Location: /webapp/passwort-aendern/');
                            exit();
                        }
                        
                        // Wenn Onboarding nicht abgeschlossen wurde, zum Onboarding weiterleiten
                        if (!$onboardingUser || !isset($onboardingUser['onboarding_abgeschlossen']) || $onboardingUser['onboarding_abgeschlossen'] == 0) {
                            header('Location: /onboarding/');
                            exit();
                        }
                        
                        header('Location: ' . getLoginRedirectPath($user['id']));
                        exit();
                    }
                } else if (empty($error)) {
                    // Passwort falsch - fehlgeschlagene Versuche erhöhen (nur wenn keine anderen Fehler)
                    $failed_attempts = $user['fehlversuche'] + 1;
                    $gesperrt_bis = null;
                    $gesperrt = 0;
                    $status = $user['status']; // Status beibehalten, außer bei Sperrung
                    
                    // Nach 5 fehlgeschlagenen Versuchen für 30 Minuten sperren
                    if ($failed_attempts >= 5) {
                        $gesperrt_bis = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 Minuten
                        $status = 'gesperrt'; // Status auf 'gesperrt' setzen
                        $error = 'Zu viele fehlgeschlagene Login-Versuche. Ihr Account wurde für 30 Minuten gesperrt.';
                        
                        // Benachrichtigung für Account-Sperrung erstellen
                        $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
                        if (empty($userName)) {
                            $userName = $user['email'];
                        }
                        
                        // Benachrichtigung an den betroffenen Benutzer
                        createNotification(
                            $user['id'],
                            'account_gesperrt',
                            'Account gesperrt: ' . $userName,
                            'Ihr Account wurde aufgrund von 5 fehlgeschlagenen Login-Versuchen für 30 Minuten gesperrt. IP-Adresse: ' . $ip_address,
                            'kritisch',
                            'login/',
                            'user',
                            $user['id'],
                            true,
                            null // Kein created_by_user_id bei Login-Fehlern
                        );
                        
                        // Benachrichtigung an Admins und Techniker
                        createNotificationsForAction(
                            null, // Kein created_by_user_id
                            $user['company_id'],
                            'account_gesperrt',
                            'Account gesperrt: ' . $userName . ' (' . $user['email'] . ')',
                            'Der Account von "' . $userName . '" (' . $user['email'] . ') wurde aufgrund von 5 fehlgeschlagenen Login-Versuchen für 30 Minuten gesperrt. IP-Adresse: ' . $ip_address,
                            'hoch',
                            'admin/users.php',
                            'user',
                            $user['id']
                        );
                    } else {
                        $remaining = 5 - $failed_attempts;
                        $error = 'Ungültige E-Mail oder Passwort. Noch ' . $remaining . ' Versuch(e) verbleibend.';
                    }
                    
                    // Fehlgeschlagene Versuche und Status in Datenbank speichern
                    $updateStmt = $pdo->prepare("UPDATE users SET fehlversuche = ?, gesperrt_bis = ?, gesperrt = ?, status = ? WHERE id = ?");
                    $updateStmt->execute([$failed_attempts, $gesperrt_bis, $gesperrt, $status, $user['id']]);
                }
            } else {
                // Benutzer existiert nicht
                $error = 'Ungültige E-Mail oder Passwort.';
            }
        } catch (PDOException $e) {
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
            error_log("Login Error: " . $e->getMessage());
        }
    }
}
?>


        
      
    
   






<?php
$parts = ['Serohub', ''];
$loginTitle = trim($parts[0] . ' ' . $parts[1]) . ' – Anmeldung';
$loginFavicon = '../assets/images/Serohub_Icon.png';
$loginCards = [
    ['label' => 'E-Mail', 'value' => 'support@serohub.de', 'href' => 'mailto:support@serohub.de', 'icon_type' => 'fa', 'icon' => 'fas fa-envelope'],
    ['label' => 'Serohub', 'value' => 'Die All-in-One Plattform für IT-Dienstleister', 'href' => 'https://serohub.de', 'icon_type' => 'fa', 'icon' => 'fa-solid fa-s'],
    ['label' => 'Wissensdatenbank', 'value' => 'Hilfe & Anleitungen', 'href' => '../knowledge/', 'icon_type' => 'fa', 'icon' => 'fas fa-book']
];
$loginFooterLinks = [
    ['label' => 'Datenschutz', 'url' => 'https://serohub.de/datenschutz'],
    ['label' => 'Impressum', 'url' => 'https://serohub.de/impressum']
];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2', 'login_cards', 'login_footer_links')");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['setting_key'] === 'branding_logo' && !empty(trim($r['setting_value'] ?? ''))) {
            $loginFavicon = '../' . ltrim(trim($r['setting_value']), '/');
        }
        if ($r['setting_key'] === 'branding_name_part1' && $r['setting_value'] !== null) {
            $parts[0] = trim($r['setting_value']);
        }
        if ($r['setting_key'] === 'branding_name_part2' && $r['setting_value'] !== null) {
            $parts[1] = trim($r['setting_value']);
        }
        if ($r['setting_key'] === 'login_cards' && !empty(trim($r['setting_value'] ?? ''))) {
            $decoded = json_decode($r['setting_value'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $loginCards = $decoded;
            }
        }
        if ($r['setting_key'] === 'login_footer_links' && !empty(trim($r['setting_value'] ?? ''))) {
            $decoded = json_decode($r['setting_value'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $loginFooterLinks = $decoded;
            }
        }
    }
    $loginTitle = trim($parts[0] . ' ' . $parts[1]) . ' – Anmeldung';
} catch (PDOException $e) {
    $loginTitle = trim($parts[0] . ' ' . $parts[1]) . ' – Anmeldung';
}

function login_sanitize_svg($svg) {
    if ($svg === null || $svg === '') return '';
    $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);
    $svg = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg);
    $svg = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $svg);
    return trim($svg);
}

function login_floating_input(string $id, string $label, array $opts = []): void
{
    $name = $opts['name'] ?? $id;
    $type = $opts['type'] ?? 'text';
    $value = htmlspecialchars((string) ($opts['value'] ?? ''), ENT_QUOTES, 'UTF-8');
    $required = !empty($opts['required']) ? ' required' : '';
    $labelText = htmlspecialchars($label . (!empty($opts['required']) ? ' ' : ''), ENT_QUOTES, 'UTF-8');
    $inputClass = 'login-floating-input block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-white rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-blue-600';
    $labelClass = 'login-floating-label';
    $extraAttrs = '';
    if (!empty($opts['autocomplete'])) {
        $extraAttrs .= ' autocomplete="' . htmlspecialchars((string) $opts['autocomplete'], ENT_QUOTES, 'UTF-8') . '"';
    }
    ?>
<div class="relative">
    <input type="<?php echo htmlspecialchars($type); ?>" id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo $value; ?>" class="<?php echo $inputClass; ?>" placeholder=" "<?php echo $required . $extraAttrs; ?>>
    <label for="<?php echo htmlspecialchars($id); ?>" class="<?php echo $labelClass; ?>"><?php echo $labelText; ?></label>
</div>
    <?php
}
?>
<html>
<!DOCTYPE html>
<head>
    <meta charset='UTF-8'>
    <meta name='robots' content='noindex, nofollow'>
    <meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no'>
    <title><?php echo htmlspecialchars($loginTitle); ?></title>
    <link rel='stylesheet' href='https://fonts.googleapis.com/css?family=Open+Sans'>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'>
    <link rel='icon' type='image/x-icon' href='<?php echo htmlspecialchars($loginFavicon); ?>'>
    <style>
        html, body, h1, h2, h3, h4, h5 {
            font-family: 'Open Sans', sans-serif;
        }

    </style>
</head>
<style>
    body {
        margin: 0;
        font-family: "Segoe UI", sans-serif;
        background-color: #fdfdfd;
        color: #333;
        display: flex;
        flex-direction: column;
          height: 100%;
  width: 100%;
  overflow: hidden; /* Verhindert Scrollen */
    }

    .login-container {
        display: flex;
        flex: 1;
    }

    .error-message {
    width: 100%;
    background-color: #ffe0e0;
    color: #d8000c;
    border: 1px solid #d8000c;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}


    .login-box {
        flex: 1;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 40px;
        overflow: hidden;
        background:
            radial-gradient(circle at 18% 22%, rgba(59, 130, 246, 0.1) 0%, transparent 42%),
            radial-gradient(circle at 82% 78%, rgba(147, 197, 253, 0.12) 0%, transparent 40%),
            radial-gradient(circle at 55% 50%, rgba(219, 234, 254, 0.2) 0%, transparent 55%),
            linear-gradient(145deg, #ffffff 0%, #ffffff 50%, #f8fafc 78%, #eff6ff 100%);
        border-right: 1px solid rgba(59, 130, 246, 0.1);
    }

    .login-box::before,
    .login-box::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        pointer-events: none;
    }

    .login-box::before {
        width: 22rem;
        height: 22rem;
        top: -4rem;
        left: -3rem;
        background: rgba(96, 165, 250, 0.14);
        filter: blur(70px);
    }

    .login-box::after {
        width: 18rem;
        height: 18rem;
        right: -2rem;
        bottom: -3rem;
        background: rgba(191, 219, 254, 0.18);
        filter: blur(65px);
    }

    .login-box > * {
        position: relative;
        z-index: 1;
    }

    .login-box img {
        width: 100px;
        margin-bottom: 20px;
    }

    .login-box h3 {
        margin-bottom: 30px;
        color: #111;
        font-size: 1.5rem;
        font-weight: 600;
        line-height: 1.35;
    }

    .login-form {
        width: 100%;
        max-width: 30rem;
    }

    .login-form-fields {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin-bottom: 1rem;
    }

    .login-form-fields > .relative {
        position: relative;
    }

    .login-floating-label {
        position: absolute;
        left: 0.625rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.875rem;
        line-height: 1;
        color: #6b7280;
        background: transparent;
        padding: 0 0.25rem;
        pointer-events: none;
        z-index: 1;
        transition: top 0.2s ease, transform 0.2s ease, color 0.2s ease, font-size 0.2s ease, background-color 0.2s ease;
    }

    /* Label sitzt auf der Oberkante und überdeckt die Border-Linie */
    .login-floating-input:focus + .login-floating-label,
    .login-floating-input.has-value + .login-floating-label,
    .login-floating-input:not(:placeholder-shown) + .login-floating-label {
        top: 0;
        transform: translateY(-50%);
        font-size: 0.75rem;
        color: #2563eb;
        background-color: #fff;
    }

    @media (max-width: 600px) {
        .login-hide-on-small {
            display: none !important;
        }
    }

    .login-box input[type="submit"] {
        background-color: #111;
        color: #fff;
        border: none;
        padding: 12px;
        border-radius: 5px;
        width: 100%;
        cursor: pointer;
    }

    .info-section {
        flex: 1;
        background-color: #1a1a1a;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 60px 40px;
    }

    .info-card {
        display: flex;
        align-items: center;
        background-color: #2e2e2e;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
    }

    .info-card .info-card-icon {
        display: flex;
        align-items: center;
        margin-right: 20px;
        flex-shrink: 0;
        color: #fff;
    }
    .info-card .info-card-icon i {
        font-size: 24px;
    }
    .info-card .info-card-icon-img {
        width: 24px;
        height: 24px;
        object-fit: contain;
    }
    .info-card .info-card-icon-svg {
        display: inline-flex;
        width: 24px;
        height: 24px;
    }
    .info-card .info-card-icon-svg svg {
        width: 100%;
        height: 100%;
        fill: currentColor;
    }

    .info-card .info-text {
        line-height: 1.4;
    }

    .info-card .info-text .label {
        font-weight: bold;
        color: #fff;
    }

    .info-card .info-text .value {
        color: #ccc;
    }
     .footer {
        text-align: center;
        font-size: 12px;
        color: #999;
        padding: 10px;
        background: #f0f0f0;
    }

    .footer a {
        color: #999;
        text-decoration: none;
        margin: 0 8px;
    }

    .footer a:hover {
        text-decoration: underline;
    }

    .demo-banner {
        width: 100%;
        max-width: 30rem;
        margin-bottom: 1.25rem;
        padding: 1rem 1.125rem;
        font-size: 0.9375rem;
        line-height: 1.45;
        color: #1e40af;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.5rem;
    }

    .demo-banner__title {
        margin: 0 0 0.625rem;
        font-weight: 600;
        font-size: 0.9375rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .demo-banner__title svg {
        flex-shrink: 0;
        width: 1.125rem;
        height: 1.125rem;
        color: #3b82f6;
    }

    .demo-banner__list {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .demo-banner__item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: #334155;
    }

    .demo-banner__item-label {
        font-weight: 500;
        color: #64748b;
        min-width: 4.5rem;
    }

    .demo-banner__item-value {
        background: #fff;
        border: 1px solid #bfdbfe;
        border-radius: 0.25rem;
        padding: 0.125rem 0.5rem;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.8125rem;
        color: #1e3a5f;
        user-select: all;
        letter-spacing: 0.02em;
    }

    .demo-banner__fill-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        margin-top: 0.75rem;
        width: 100%;
        padding: 0.5rem 1rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #1e40af;
        background: #fff;
        border: 1px solid #93c5fd;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: background 0.2s, color 0.2s, border-color 0.2s;
    }

    .demo-banner__fill-btn:hover {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .demo-banner__fill-btn svg {
        width: 0.875rem;
        height: 0.875rem;
    }
</style>

<body>
    <div class="login-container">
        <!-- LOGIN -->
        <div class="login-box">
            <img src="<?php echo htmlspecialchars($loginFavicon); ?>" alt="Logo">
            <?php if (isset($_SESSION['2fa_pending_user_id'])): ?>
                <!-- 2FA-Verifizierung -->
                <h3 style="text-align: center;">2FA-Verifizierung</h3>
                <p style="text-align: center; margin-bottom: 20px; color: #666; font-size: 14px;">
                    Bitte gib den Code aus deiner Authenticator-App ein.
                </p>
                <form method="POST" action="">
                    <input type="hidden" id="twofa_code" name="twofa_code" required>
                    <div id="twofa_code_inputs" style="display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:8px; margin:0 auto 20px; width:100%; max-width:340px;">
                        <input type="text" class="twofa-code-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" style="text-align:center; font-size:20px; width:100%; padding:12px; border:1px solid #ccc; border-radius:5px; background-color:#f9f9f9;" autofocus>
                        <input type="text" class="twofa-code-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" style="text-align:center; font-size:20px; width:100%; padding:12px; border:1px solid #ccc; border-radius:5px; background-color:#f9f9f9;">
                        <input type="text" class="twofa-code-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" style="text-align:center; font-size:20px; width:100%; padding:12px; border:1px solid #ccc; border-radius:5px; background-color:#f9f9f9;">
                        <input type="text" class="twofa-code-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" style="text-align:center; font-size:20px; width:100%; padding:12px; border:1px solid #ccc; border-radius:5px; background-color:#f9f9f9;">
                        <input type="text" class="twofa-code-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" style="text-align:center; font-size:20px; width:100%; padding:12px; border:1px solid #ccc; border-radius:5px; background-color:#f9f9f9;">
                        <input type="text" class="twofa-code-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" style="text-align:center; font-size:20px; width:100%; padding:12px; border:1px solid #ccc; border-radius:5px; background-color:#f9f9f9;">
                    </div>
                    
                    <!-- Vertrautes Gerät Option -->
                    <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 14px; color: #333; margin-bottom: 10px;">
                            <input 
                                type="checkbox" 
                                name="trust_device" 
                                value="1" 
                                id="trust_device_checkbox"
                                style="margin-right: 10px; margin-top: 2px; width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb;"
                            >
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #111; margin-bottom: 4px;">
                                    <svg style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    Dieses Gerät als vertraut markieren
                                </div>
                                <div style="font-size: 12px; color: #666; line-height: 1.4;">
                                    Du musst 30 Tage lang keinen 2FA-Code auf diesem Gerät eingeben
                                </div>
                            </div>
                        </label>
                        <div id="device_name_container" style="margin-top: 12px; display: none; transition: all 0.3s ease;">
                            <input 
                                type="text" 
                                name="device_name" 
                                id="device_name_input"
                                placeholder="z.B. Mein Laptop, Arbeits-PC..." 
                                style="width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 13px; background-color: #fff; transition: border-color 0.3s ease;"
                                maxlength="255"
                            >
                            <div style="font-size: 11px; color: #6c757d; margin-top: 6px; padding-left: 4px;">
                                Optional: Gib diesem Gerät einen Namen, um es leichter wiederzufinden
                            </div>
                        </div>
                    </div>
                    
                    <label style="display: flex; align-items: center; margin-bottom: 15px; cursor: pointer; font-size: 14px; color: #333;">
                        <input type="checkbox" name="remember_me" value="1" style="margin-right: 8px; width: 18px; height: 18px; accent-color: #111;">
                        Angemeldet bleiben (30 Tage auf diesem Gerät)
                    </label>
                    
                    <input type="submit" name="verify_2fa" value="Verifizieren" style="margin-top: 0; width: 100%; padding: 12px; background-color: #111; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: 500; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#333'" onmouseout="this.style.backgroundColor='#111'">
                    <a href="?cancel_2fa=1" style="display: block; text-align: center; margin-top: 12px; color: #666; text-decoration: none; font-size: 14px; transition: color 0.3s ease;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#666'">
                        Abbrechen und neu anmelden
                    </a>
                </form>
                
                <script>
                // Gerätename-Feld ein/ausblenden basierend auf Checkbox
                document.addEventListener('DOMContentLoaded', function() {
                    const checkbox = document.getElementById('trust_device_checkbox');
                    const container = document.getElementById('device_name_container');
                    const input = document.getElementById('device_name_input');
                    
                    if (checkbox && container) {
                        checkbox.addEventListener('change', function() {
                            if (this.checked) {
                                container.style.display = 'block';
                                setTimeout(function() {
                                    container.style.opacity = '1';
                                }, 10);
                                if (input) {
                                    setTimeout(function() {
                                        input.focus();
                                    }, 200);
                                }
                            } else {
                                container.style.opacity = '0';
                                setTimeout(function() {
                                    container.style.display = 'none';
                                    if (input) input.value = '';
                                }, 300);
                            }
                        });
                    }
                });
                </script>
            <?php else: ?>
                <!-- Normales Login -->
                <h3 style="text-align: center;">Anmelden bei Serohub</h3>

                <?php if (defined('DEMO_MODE') && DEMO_MODE): ?>
                <div class="demo-banner" role="status">
                    <p class="demo-banner__title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        Demo-Zugang
                    </p>
                    <ul class="demo-banner__list">
                        <li class="demo-banner__item">
                            <span class="demo-banner__item-label">E-Mail</span>
                            <code class="demo-banner__item-value"><?php echo htmlspecialchars(DEMO_EMAIL); ?></code>
                        </li>
                        <li class="demo-banner__item">
                            <span class="demo-banner__item-label">Passwort</span>
                            <code class="demo-banner__item-value"><?php echo htmlspecialchars(DEMO_PASSWORD); ?></code>
                        </li>
                    </ul>
                    <button type="button" class="demo-banner__fill-btn" onclick="demoFill()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <polyline points="10 17 15 12 10 7"/>
                            <line x1="15" y1="12" x2="3" y2="12"/>
                        </svg>
                        Felder automatisch ausfüllen
                    </button>
                </div>
                <script>
                function demoFill() {
                    var emailInput = document.getElementById('username');
                    var passInput = document.getElementById('password');
                    if (emailInput) {
                        emailInput.value = <?php echo json_encode(DEMO_EMAIL); ?>;
                        emailInput.dispatchEvent(new Event('input', {bubbles: true}));
                    }
                    if (passInput) {
                        passInput.value = <?php echo json_encode(DEMO_PASSWORD); ?>;
                        passInput.dispatchEvent(new Event('input', {bubbles: true}));
                    }
                }
                </script>
                <?php endif; ?>

                <form class="login-form" method="POST" action="">
                    <div class="login-form-fields">
                        <?php login_floating_input('username', 'E-Mail-Adresse', [
                            'type' => 'email',
                            'name' => 'username',
                            'required' => true,
                            'autocomplete' => 'email',
                            'value' => $_POST['username'] ?? '',
                        ]); ?>
                        <?php login_floating_input('password', 'Passwort', [
                            'type' => 'password',
                            'required' => true,
                            'autocomplete' => 'current-password',
                        ]); ?>
                    </div>
                    <label style="display: flex; align-items: center; margin-bottom: 15px; cursor: pointer; font-size: 14px; color: #333;">
                        <input type="checkbox" name="remember_me" value="1" id="login_remember_me" style="margin-right: 8px; width: 18px; height: 18px; accent-color: #111;">
                        Angemeldet bleiben (30 Tage auf diesem Gerät)
                    </label>
                    <input type="submit" name="login" value="Login">
                    <?php if (!empty($loginPasskeyAvailable)): ?>
                    <div style="margin: 18px 0; text-align: center; color: #888; font-size: 13px;">oder</div>
                    <button type="button" id="passkey-login-btn" style="width: 100%; padding: 12px; background-color: #fff; color: #111; border: 2px solid #111; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600;">
                        Mit Passkey anmelden (Face&nbsp;ID / Touch&nbsp;ID)
                    </button>
                    <p style="margin-top: 10px; font-size: 12px; color: #666; text-align: center; line-height: 1.4;">
                        Wenn ein Passkey auf diesem Gerät vorhanden ist, kannst du dich direkt ohne E-Mail und Passwort anmelden.
                    </p>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="error-message" style="background-color: #d4edda; color: #155724; border-color: #c3e6cb;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
    
        </div>

        <!-- INFO CARDS (dynamisch aus Admin > Erscheinungsbild > Login-Karten) -->
        <?php if (!empty($loginCards)): ?>
        <div class="info-section login-hide-on-small">
            <?php foreach ($loginCards as $card):
                $iconType = $card['icon_type'] ?? 'fa';
                $iconVal = $card['icon'] ?? ($iconType === 'fa' ? 'fas fa-link' : '');
            ?>
            <a class="info-card" style="text-decoration: none;" href="<?php echo htmlspecialchars($card['href'] ?? '#'); ?>">
                <span class="info-card-icon">
                <?php if ($iconType === 'fa'): ?>
                    <i class="<?php echo htmlspecialchars($iconVal); ?>"></i>
                <?php elseif ($iconType === 'image' && $iconVal !== ''): ?>
                    <img src="../<?php echo htmlspecialchars(ltrim($iconVal, '/')); ?>" alt="" class="info-card-icon-img">
                <?php elseif ($iconType === 'svg' && $iconVal !== ''): ?>
                    <span class="info-card-icon-svg"><?php echo login_sanitize_svg($iconVal); ?></span>
                <?php else: ?>
                    <i class="fas fa-link"></i>
                <?php endif; ?>
                </span>
                <div class="info-text">
                    <div class="label"><?php echo htmlspecialchars($card['label'] ?? ''); ?></div>
                    <div class="value"><?php echo htmlspecialchars($card['value'] ?? ''); ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <!-- FOOTER (dynamisch aus Admin > Erscheinungsbild > Login-Karten) -->
    <?php if (!empty($loginFooterLinks)): ?>
    <div class="footer">
        <?php
        $segments = [];
        foreach ($loginFooterLinks as $fl) {
            $label = trim($fl['label'] ?? '');
            $url = trim($fl['url'] ?? '');
            if ($label !== '' || $url !== '') {
                $segments[] = $url !== '' ? '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label ?: $url) . '</a>' : htmlspecialchars($label);
            }
        }
        echo implode(' | ', $segments);
        ?>
    </div>
    <?php endif; ?>


        <script>
        (function initLoginFloatingInputs() {
            function syncFloatingInput(input) {
                input.classList.toggle('has-value', (input.value || '').trim().length > 0);
            }
            document.querySelectorAll('.login-floating-input').forEach(function(input) {
                syncFloatingInput(input);
                input.addEventListener('input', function() { syncFloatingInput(input); });
                input.addEventListener('change', function() { syncFloatingInput(input); });
                input.addEventListener('blur', function() { syncFloatingInput(input); });
            });
            window.setTimeout(function() {
                document.querySelectorAll('.login-floating-input').forEach(syncFloatingInput);
            }, 100);
            window.setTimeout(function() {
                document.querySelectorAll('.login-floating-input').forEach(syncFloatingInput);
            }, 500);
        })();

        // Device-Fingerprinting: Screen-Resolution und Timezone in Cookie speichern
        (function() {
            // Screen-Resolution
            if (screen.width && screen.height) {
                const resolution = screen.width + 'x' + screen.height;
                document.cookie = 'screen_resolution=' + resolution + '; path=/; max-age=2592000'; // 30 Tage
            }
            
            // Timezone
            try {
                const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                document.cookie = 'timezone=' + timezone + '; path=/; max-age=2592000'; // 30 Tage
            } catch (e) {
                // Fallback für ältere Browser
                const offset = new Date().getTimezoneOffset();
                document.cookie = 'timezone=' + offset + '; path=/; max-age=2592000';
            }
        })();
        
        // 2FA-Code: 6 einzelne Felder + verstecktes Gesamtfeld
        const twofaInput = document.getElementById('twofa_code');
        const twofaDigits = Array.from(document.querySelectorAll('.twofa-code-digit'));
        if (twofaInput && twofaDigits.length === 6) {
            const updateTwofaValue = function() {
                twofaInput.value = twofaDigits.map(function(input) { return input.value.trim(); }).join('');
            };

            twofaDigits.forEach(function(input, index) {
                input.addEventListener('input', function(e) {
                    const cleaned = (e.target.value || '').replace(/[^0-9]/g, '');
                    e.target.value = cleaned ? cleaned.charAt(cleaned.length - 1) : '';
                    if (e.target.value && index < twofaDigits.length - 1) {
                        twofaDigits[index + 1].focus();
                    }
                    updateTwofaValue();
                    if (index === twofaDigits.length - 1 && twofaInput.value.length === 6) {
                        const verifyBtn = document.querySelector('input[name="verify_2fa"]');
                        if (verifyBtn) {
                            verifyBtn.click();
                        }
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !input.value && index > 0) {
                        twofaDigits[index - 1].focus();
                    }
                });

                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasted = (e.clipboardData.getData('text') || '').replace(/[^0-9]/g, '').slice(0, 6);
                    if (!pasted) return;
                    for (let i = 0; i < twofaDigits.length; i++) {
                        twofaDigits[i].value = pasted[i] || '';
                    }
                    updateTwofaValue();
                    twofaDigits[Math.min(pasted.length, 5)].focus();
                });
            });
        } else if (twofaInput) {
            twofaInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
        }
        
        // Enter-Taste für schnelles Absenden
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                const loginBtn = document.querySelector('input[name="login"]');
                const verifyBtn = document.querySelector('input[name="verify_2fa"]');
                if (loginBtn) loginBtn.click();
                if (verifyBtn) verifyBtn.click();
            }
        });

        <?php if (!empty($loginPasskeyAvailable)): ?>
        (function() {
            function base64UrlToBuffer(s) {
                const pad = s.length % 4 === 0 ? '' : '='.repeat(4 - (s.length % 4));
                const base64 = s.replace(/-/g, '+').replace(/_/g, '/') + pad;
                const str = atob(base64);
                const buf = new Uint8Array(str.length);
                for (let i = 0; i < str.length; i++) buf[i] = str.charCodeAt(i);
                return buf;
            }
            function bufferToBase64url(buffer) {
                const bytes = new Uint8Array(buffer);
                let binary = '';
                for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
                return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
            }
            function parseRequestOptions(opts) {
                const out = Object.assign({}, opts);
                out.challenge = base64UrlToBuffer(opts.challenge);
                if (opts.allowCredentials && opts.allowCredentials.length) {
                    out.allowCredentials = opts.allowCredentials.map(function(c) {
                        const d = Object.assign({}, c);
                        d.id = typeof c.id === 'string' ? base64UrlToBuffer(c.id) : c.id;
                        return d;
                    });
                }
                return out;
            }
            function credentialToServer(credential) {
                const r = credential.response;
                const body = {
                    id: credential.id,
                    rawId: bufferToBase64url(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: bufferToBase64url(r.clientDataJSON),
                        authenticatorData: bufferToBase64url(r.authenticatorData),
                        signature: bufferToBase64url(r.signature)
                    }
                };
                if (r.userHandle && r.userHandle.byteLength) {
                    body.response.userHandle = bufferToBase64url(r.userHandle);
                }
                return body;
            }
            var btn = document.getElementById('passkey-login-btn');
            if (!btn || !window.PublicKeyCredential) return;
            btn.addEventListener('click', async function() {
                var emailEl = document.getElementById('username');
                var email = emailEl ? (emailEl.value || '').trim() : '';
                var rememberEl = document.getElementById('login_remember_me');
                var remember = rememberEl && rememberEl.checked;
                btn.disabled = true;
                var oldText = btn.textContent;
                btn.textContent = 'Warte auf Gerät…';
                try {
                    var optRes = await fetch('api/passkey-login-options.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: email || undefined, remember_me: remember })
                    });
                    var optJson = await optRes.json();
                    if (!optJson.success) {
                        throw new Error(optJson.error || 'Passkey-Start fehlgeschlagen');
                    }
                    var pubKey = parseRequestOptions(optJson.options);
                    var credential = await navigator.credentials.get({ publicKey: pubKey });
                    if (!credential) throw new Error('Abgebrochen');
                    var verifyRes = await fetch('api/passkey-login-verify.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ credential: credentialToServer(credential) })
                    });
                    var verifyJson = await verifyRes.json();
                    if (!verifyJson.success) {
                        throw new Error(verifyJson.error || 'Anmeldung fehlgeschlagen');
                    }
                    window.location.href = verifyJson.redirect;
                } catch (err) {
                    alert(err.message || 'Passkey-Anmeldung fehlgeschlagen.');
                    btn.disabled = false;
                    btn.textContent = oldText;
                }
            });
        })();
        <?php endif; ?>
    </script>


    </body>
</html>
