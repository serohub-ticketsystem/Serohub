<?php
// Basis-URL für Links (bei Installation im Unterverzeichnis z. B. '/Softwareverteilung/webapp/' setzen)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Dark Mode: false = deaktiviert (immer Hellmodus), true = wieder aktivieren
if (!defined('DARK_MODE_ENABLED')) {
    define('DARK_MODE_ENABLED', false);
}


// Datenbankkonfiguration
define('DB_HOST', '####');
define('DB_NAME', '####');
define('DB_USER', '####');
define('DB_PASS', '####');
define('DB_CHARSET', 'utf8mb4');

// Optional: Eigenen Schlüssel für Verschlüsselung von Aufgaben/Ordnern setzen (32+ Zeichen empfohlen).
// Wenn nicht gesetzt, wird aus DB_PASS abgeleitet – für stärkere Sicherheit bitte setzen.
// define('ENCRYPTION_KEY', 'ihr-geheimer-schluessel-mind-32-zeichen');

// PDO-Verbindung
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// Session-Einstellungen (nur im Web-Kontext, nicht bei Cron/CLI)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Auf 1 setzen wenn HTTPS verwendet wird
    ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30); // 30 Tage Session-Gültigkeit
}

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// SITE_URL: Für Cron-Jobs (z.B. CalDAV-Sync) – absolute URL des Portals
// Unkommentieren und anpassen, falls Cron-Skripte ohne HTTP-Kontext laufen:
// define('SITE_URL', 'https://ihre-domain.de/');

// Web-Push (optional): Normalerweise in Administration → Web-Push (VAPID) konfigurieren (ohne Server-Shell).
// Nur bei Bedarf hier fest eintragen – diese Werte haben dann Vorrang vor der Datenbank:
// define('WEBPUSH_VAPID_PUBLIC_KEY', '');
// define('WEBPUSH_VAPID_PRIVATE_KEY', '');
// define('WEBPUSH_VAPID_SUBJECT', 'mailto:admin@ihre-domain.de');

// Hilfsfunktion zum Generieren eines Device-Fingerprints
function generateDeviceFingerprint() {
    $components = [];
    
    // User-Agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $components[] = $userAgent;
    
    // Accept-Language
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $components[] = $acceptLanguage;
    
    // Accept-Encoding
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    $components[] = $acceptEncoding;
    
    // Screen-Resolution (wird per JavaScript gesetzt, falls verfügbar)
    $screenResolution = $_COOKIE['screen_resolution'] ?? '';
    $components[] = $screenResolution;
    
    // Timezone (wird per JavaScript gesetzt, falls verfügbar)
    $timezone = $_COOKIE['timezone'] ?? '';
    $components[] = $timezone;
    
    // Fingerprint erstellen
    $fingerprint = hash('sha256', implode('|', $components));
    
    return $fingerprint;
}

// Hilfsfunktion zum Prüfen ob ein Gerät vertraut ist
function isDeviceTrusted($userId, $deviceFingerprint) {
    global $pdo;
    
    if (empty($deviceFingerprint)) {
        return false;
    }
    
    try {
        // Prüfen ob Gerät in den letzten 30 Tagen verwendet wurde
        $stmt = $pdo->prepare("
            SELECT id, device_name, last_used 
            FROM trusted_devices 
            WHERE user_id = ? AND device_fingerprint = ? AND last_used > DATE_SUB(NOW(), INTERVAL 30 DAY)
            LIMIT 1
        ");
        $stmt->execute([$userId, $deviceFingerprint]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($device) {
            // Letzte Verwendung aktualisieren
            $updateStmt = $pdo->prepare("UPDATE trusted_devices SET last_used = NOW() WHERE id = ?");
            $updateStmt->execute([$device['id']]);
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Trusted Device Check Error: " . $e->getMessage());
        return false;
    }
}

// Hilfsfunktion zum Speichern eines vertrauten Geräts
function saveTrustedDevice($userId, $deviceFingerprint, $deviceName = null) {
    global $pdo;
    
    if (empty($deviceFingerprint)) {
        return false;
    }
    
    try {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Prüfen ob Gerät bereits existiert
        $stmt = $pdo->prepare("SELECT id FROM trusted_devices WHERE user_id = ? AND device_fingerprint = ?");
        $stmt->execute([$userId, $deviceFingerprint]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Aktualisieren
            $updateStmt = $pdo->prepare("
                UPDATE trusted_devices 
                SET device_name = ?, user_agent = ?, ip_address = ?, last_used = NOW() 
                WHERE id = ?
            ");
            $updateStmt->execute([$deviceName, $userAgent, $ipAddress, $existing['id']]);
        } else {
            // Neues Gerät speichern
            $insertStmt = $pdo->prepare("
                INSERT INTO trusted_devices (user_id, device_fingerprint, device_name, user_agent, ip_address, last_used, created_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([$userId, $deviceFingerprint, $deviceName, $userAgent, $ipAddress]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Save Trusted Device Error: " . $e->getMessage());
        return false;
    }
}

// Angemeldet bleiben: Sichere Token-Funktionen für persistentes Login auf demselben Gerät
define('REMEMBER_ME_COOKIE_NAME', 'remember_me_token');
define('REMEMBER_ME_LIFETIME_DAYS', 30);
define('REMEMBER_ME_COOKIE_PATH', '/');

function createRememberMeToken($userId) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + (REMEMBER_ME_LIFETIME_DAYS * 24 * 60 * 60));
    $fingerprint = generateDeviceFingerprint();
    try {
        $stmt = $pdo->prepare("INSERT INTO remember_me_tokens (user_id, token_hash, device_fingerprint, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $tokenHash, $fingerprint, $expiresAt]);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(REMEMBER_ME_COOKIE_NAME, $token, time() + (REMEMBER_ME_LIFETIME_DAYS * 24 * 60 * 60), REMEMBER_ME_COOKIE_PATH, '', $secure, true);
        return true;
    } catch (PDOException $e) {
        error_log("Remember Me Create Error: " . $e->getMessage());
        return false;
    }
}

function validateRememberMeToken() {
    global $pdo;
    $token = $_COOKIE[REMEMBER_ME_COOKIE_NAME] ?? '';
    if (empty($token) || strlen($token) !== 64) return null;
    $tokenHash = hash('sha256', $token);
    try {
        $stmt = $pdo->prepare("SELECT id, user_id FROM remember_me_tokens WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stmt2 = $pdo->prepare("UPDATE remember_me_tokens SET expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
            $stmt2->execute([REMEMBER_ME_LIFETIME_DAYS, $row['id']]);
            return (int) $row['user_id'];
        }
    } catch (PDOException $e) {
        error_log("Remember Me Validate Error: " . $e->getMessage());
    }
    return null;
}

function deleteRememberMeToken() {
    global $pdo;
    $token = $_COOKIE[REMEMBER_ME_COOKIE_NAME] ?? '';
    if (!empty($token) && strlen($token) === 64) {
        $tokenHash = hash('sha256', $token);
        try {
            $stmt = $pdo->prepare("DELETE FROM remember_me_tokens WHERE token_hash = ?");
            $stmt->execute([$tokenHash]);
        } catch (PDOException $e) {}
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(REMEMBER_ME_COOKIE_NAME, '', time() - 3600, REMEMBER_ME_COOKIE_PATH, '', $secure, true);
}

// „Überall abmelden“-Flag zurücksetzen (bei neuem Login, damit Anmeldung wieder möglich ist)
function clearSessionsValidAfter($userId) {
    global $pdo;
    try {
        $pdo->prepare("DELETE FROM user_settings WHERE user_id = ? AND setting_key = 'sessions_valid_after'")->execute([(int) $userId]);
    } catch (PDOException $e) {
        // ignorieren
    }
}

function getRevokedSessionIds(int $userId): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'sessions_revoked_ids' LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['setting_value'])) {
            return [];
        }
        $decoded = json_decode((string) $row['setting_value'], true);
        if (!is_array($decoded)) {
            return [];
        }
        $clean = [];
        foreach ($decoded as $sid) {
            $sid = trim((string) $sid);
            if ($sid !== '') {
                $clean[] = $sid;
            }
        }
        return array_values(array_unique($clean));
    } catch (PDOException $e) {
        return [];
    }
}

function addRevokedSessionId(int $userId, string $sessionId): void {
    $sessionId = trim($sessionId);
    if ($sessionId === '') {
        return;
    }
    global $pdo;
    $current = getRevokedSessionIds($userId);
    $current[] = $sessionId;
    $current = array_values(array_unique($current));
    if (count($current) > 200) {
        $current = array_slice($current, -200);
    }
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value)
            VALUES (?, 'sessions_revoked_ids', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$userId, json_encode($current, JSON_UNESCAPED_SLASHES)]);
    } catch (PDOException $e) {
        // ignorieren
    }
}

// Aktive Session aus user_sessions entfernen (beim Abmelden)
function removeCurrentUserSession() {
    if (php_sapi_name() === 'cli') {
        return;
    }
    $sessionId = session_id();
    if (empty($sessionId)) {
        return;
    }
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    } catch (PDOException $e) {
        // Tabelle könnte fehlen
    }
}

function parseUserAgentDetails(string $userAgent): array {
    $browser = 'Unbekannt';
    $browserVersion = null;
    if (preg_match('/(Edg|Edge|OPR|Opera|Chrome|Firefox|Safari|MSIE|Trident)\/?\s?([0-9\.]+)/i', $userAgent, $m)) {
        $rawBrowser = $m[1];
        $browserVersion = $m[2] ?? null;
        if (strcasecmp($rawBrowser, 'Edg') === 0) {
            $browser = 'Edge';
        } elseif (strcasecmp($rawBrowser, 'OPR') === 0) {
            $browser = 'Opera';
        } elseif (strcasecmp($rawBrowser, 'Trident') === 0) {
            $browser = 'Internet Explorer';
        } else {
            $browser = $rawBrowser;
        }
    }

    $os = 'Unbekannt';
    if (preg_match('/(Windows NT|Windows|Mac OS X|Linux|Android|iPhone|iPad|iOS)/i', $userAgent, $m)) {
        $rawOs = $m[1];
        if ($rawOs === 'Windows NT') {
            $os = 'Windows';
        } elseif ($rawOs === 'Mac OS X') {
            $os = 'macOS';
        } else {
            $os = $rawOs;
        }
    }

    $deviceType = 'desktop';
    if (preg_match('/(iPad|Tablet)/i', $userAgent)) {
        $deviceType = 'tablet';
    } elseif (preg_match('/(Android|iPhone|iOS|Mobile)/i', $userAgent)) {
        $deviceType = 'mobile';
    } elseif (preg_match('/(bot|crawler|spider|curl|wget)/i', $userAgent)) {
        $deviceType = 'bot';
    }

    return [
        'browser_name' => $browser,
        'browser_version' => $browserVersion,
        'os_name' => $os,
        'device_type' => $deviceType,
    ];
}

// Aktive Session für Benutzer registrieren/aktualisieren (für Übersicht "Angemeldete Geräte")
function registerOrUpdateUserSession() {
    if (php_sapi_name() === 'cli') {
        return;
    }
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
        return;
    }
    $sessionId = session_id();
    if (empty($sessionId)) {
        return;
    }
    global $pdo;
    $userId = (int) $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $uaData = parseUserAgentDetails($userAgent);
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $secChUa = $_SERVER['HTTP_SEC_CH_UA'] ?? '';
    $secChUaPlatform = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '';
    $secChUaMobile = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
    $secChUaModel = $_SERVER['HTTP_SEC_CH_UA_MODEL'] ?? '';
    $remotePort = (int) ($_SERVER['REMOTE_PORT'] ?? 0);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0;
    $loginMethod = (string) ($_SESSION['session_login_method'] ?? 'session');
    unset($_SESSION['session_login_method']);
    $rememberMeUsed = !empty($_COOKIE[REMEMBER_ME_COOKIE_NAME]) ? 1 : 0;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_sessions (
                session_id, user_id, ip_address, user_agent, browser_name, browser_version, os_name, device_type,
                forwarded_for, accept_language, sec_ch_ua, sec_ch_ua_platform, sec_ch_ua_mobile, sec_ch_ua_model,
                remote_port, is_https, login_method, remember_me_used, last_activity, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                browser_name = VALUES(browser_name),
                browser_version = VALUES(browser_version),
                os_name = VALUES(os_name),
                device_type = VALUES(device_type),
                forwarded_for = VALUES(forwarded_for),
                accept_language = VALUES(accept_language),
                sec_ch_ua = VALUES(sec_ch_ua),
                sec_ch_ua_platform = VALUES(sec_ch_ua_platform),
                sec_ch_ua_mobile = VALUES(sec_ch_ua_mobile),
                sec_ch_ua_model = VALUES(sec_ch_ua_model),
                remote_port = VALUES(remote_port),
                is_https = VALUES(is_https),
                login_method = VALUES(login_method),
                remember_me_used = VALUES(remember_me_used),
                last_activity = NOW()
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            $ip,
            $userAgent,
            $uaData['browser_name'],
            $uaData['browser_version'],
            $uaData['os_name'],
            $uaData['device_type'],
            $forwardedFor,
            $acceptLanguage,
            $secChUa,
            $secChUaPlatform,
            $secChUaMobile,
            $secChUaModel,
            $remotePort > 0 ? $remotePort : null,
            $isHttps,
            $loginMethod,
            $rememberMeUsed
        ]);
        // Alte Einträge bereinigen (älter als Session-Lifetime 30 Tage)
        $pdo->exec("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    } catch (PDOException $e) {
        // Fallback für Instanzen ohne neue user_sessions-Spalten
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            try {
                $legacyStmt = $pdo->prepare("
                    INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, last_activity, created_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), last_activity = NOW()
                ");
                $legacyStmt->execute([$sessionId, $userId, $ip, $userAgent]);
                $pdo->exec("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                return;
            } catch (PDOException $legacyError) {
                error_log("User Sessions Legacy Fallback: " . $legacyError->getMessage());
            }
        }
        // Tabelle könnte noch nicht existieren
        if (strpos($e->getMessage(), 'user_sessions') !== false) {
            error_log("User Sessions: " . $e->getMessage());
        }
    }
}

/**
 * Stellt die PHP-Session aus einem gültigen Remember-Me-Cookie wieder her (z. B. nach Browser-Neustart).
 * Wird von requireLogin() und der Login-Startseite genutzt – dort landen Nutzer oft direkt, ohne vorher
 * eine geschützte Seite aufzurufen.
 */
function tryRestoreSessionFromRememberMe() {
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    $restoredUserId = validateRememberMeToken();
    if (!$restoredUserId) {
        return false;
    }
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, email, status, gesperrt, gesperrt_bis FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$restoredUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (defined('DEMO_EMAIL') && defined('DEMO_MODE') && !DEMO_MODE && strcasecmp($user['email'], DEMO_EMAIL) === 0) {
            return false;
        }
        if ($user && $user['status'] === 'aktiv' && (empty($user['gesperrt']) || $user['gesperrt'] == 0) &&
            (empty($user['gesperrt_bis']) || strtotime($user['gesperrt_bis']) <= time())) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['session_login_method'] = 'remember_me';
            $stmt2 = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'easy_mode' LIMIT 1");
            $stmt2->execute([$restoredUserId]);
            $r = $stmt2->fetch(PDO::FETCH_ASSOC);
            $_SESSION['easy_mode'] = ($r && $r['setting_value'] === '1') ? 1 : 0;
            registerOrUpdateUserSession();
            clearSessionsValidAfter($restoredUserId);
            return true;
        }
    } catch (PDOException $e) {
        error_log("Remember Me Restore Error: " . $e->getMessage());
    }
    return false;
}

// Login-Prüfung Funktion
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        tryRestoreSessionFromRememberMe();
    }
    
    if (!isset($_SESSION['user_id'])) {
        // Ursprünglich aufgerufene URL speichern für Weiterleitung nach Login
        // REQUEST_URI enthält bereits den Query-String, falls vorhanden
        $returnUrl = $_SERVER['REQUEST_URI'] ?? '';
        // URL-encodieren für GET-Parameter
        $returnUrl = urlencode($returnUrl);
        header('Location: ' . (defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/') . 'login/?return_url=' . $returnUrl);
        exit();
    }

    // Prüfen ob diese Session explizit als "abgemeldet" markiert wurde
    $activeSid = session_id();
    if (!empty($activeSid)) {
        $revoked = getRevokedSessionIds((int) $_SESSION['user_id']);
        if (!empty($revoked) && in_array($activeSid, $revoked, true)) {
            $params = session_get_cookie_params();
            $cookiePath = (string) ($params['path'] ?? '/');
            $cookieDomain = (string) ($params['domain'] ?? '');
            $cookieSecure = !empty($params['secure']);
            $cookieHttpOnly = !empty($params['httponly']);
            removeCurrentUserSession();
            $_SESSION = [];
            session_destroy();
            setcookie(session_name(), '', time() - 3600, $cookiePath, $cookieDomain, $cookieSecure, $cookieHttpOnly);
            if ($cookiePath !== '/') {
                setcookie(session_name(), '', time() - 3600, '/', $cookieDomain, $cookieSecure, $cookieHttpOnly);
            }
            $returnUrl = $_SERVER['REQUEST_URI'] ?? '';
            $returnUrl = urlencode($returnUrl);
            header('Location: ' . (defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/') . 'login/?return_url=' . $returnUrl);
            exit();
        }
    }
    
    // Prüfen ob Onboarding abgeschlossen wurde und ob Passwort zurückgesetzt werden muss
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT onboarding_abgeschlossen, passwort_zuruecksetzen FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $currentPath = $_SERVER['REQUEST_URI'] ?? '';
            
            // Prüfen ob Passwort zurückgesetzt werden muss
            if (isset($user['passwort_zuruecksetzen']) && $user['passwort_zuruecksetzen'] == 1) {
                // Weiterleiten zur Passwort-Reset-Seite, außer Benutzer ist bereits dort
                if (strpos($currentPath, '/settings/resetpasswort.php') === false && 
                    strpos($currentPath, '/login/') === false) {
                    header('Location: /settings/resetpasswort.php');
                    exit();
                }
            }
            
            // Wenn Onboarding nicht abgeschlossen wurde, zum Onboarding weiterleiten
            // Außer der Benutzer ist bereits auf einer Onboarding-Seite oder muss Passwort zurücksetzen
            if (isset($user['onboarding_abgeschlossen']) && $user['onboarding_abgeschlossen'] == 0) {
                // Prüfen ob Benutzer bereits auf Onboarding-Seite ist
                if (strpos($currentPath, '/onboarding/') === false && strpos($currentPath, '/login/') === false) {
                    header('Location: /onboarding/');
                    exit();
                }
            }
        }
    } catch (PDOException $e) {
        // Bei Fehler weitermachen, um Login nicht zu blockieren
        error_log("User-Check Error: " . $e->getMessage());
    }
    // Aktive Session für Übersicht "Angemeldete Geräte" registrieren/aktualisieren
    registerOrUpdateUserSession();
    // Prüfen ob „Überall abmelden“ andere Sessions invalidiert hat (nur diese behalten)
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'sessions_valid_after' LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['setting_value'])) {
            $validAfter = (string) $row['setting_value'];
            $stmt2 = $pdo->prepare("SELECT created_at FROM user_sessions WHERE session_id = ? LIMIT 1");
            $stmt2->execute([session_id()]);
            $srow = $stmt2->fetch(PDO::FETCH_ASSOC);
            $sessionCreatedAt = is_array($srow) ? (string) ($srow['created_at'] ?? '') : '';
            if ($sessionCreatedAt !== '' && $sessionCreatedAt < $validAfter) {
                $sid = session_id();
                $params = session_get_cookie_params(); // vor session_destroy() lesen
                $cookiePath = (string) ($params['path'] ?? '/');
                $cookieDomain = (string) ($params['domain'] ?? '');
                $cookieSecure = !empty($params['secure']);
                $cookieHttpOnly = !empty($params['httponly']);
                removeCurrentUserSession();
                $_SESSION = [];
                session_destroy();
                // Session-Cookie mit exakt denselben Parametern löschen (Pfad/Domain), sonst bleibt es im Browser
                if (!empty($sid)) {
                    setcookie(session_name(), '', time() - 3600, $cookiePath, $cookieDomain, $cookieSecure, $cookieHttpOnly);
                    if ($cookiePath !== '/') {
                        setcookie(session_name(), '', time() - 3600, '/', $cookieDomain, $cookieSecure, $cookieHttpOnly);
                    }
                }
                $returnUrl = $_SERVER['REQUEST_URI'] ?? '';
                $returnUrl = urlencode($returnUrl);
                header('Location: ' . (defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/') . 'login/?return_url=' . $returnUrl);
                exit();
            }
        }
    } catch (PDOException $e) {
        // Tabellen fehlen oder Fehler – ignorieren
    }
}

/**
 * Titel und Favicon für Fehlerseiten mit Platzhaltern (z. B. 403.html) – wie assets/frontend/head.php.
 *
 * @return array{pageTitle: string, faviconUrl: string}
 */
function resolveErrorPageBranding(int $httpCode): array {
    $headFavicon = 'assets/images/Serohub_Icon.png';
    $headNamePart1 = 'Serohub';
    $headNamePart2 = '';
    global $pdo;
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2')");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ($r['setting_key'] === 'branding_logo' && !empty(trim($r['setting_value'] ?? ''))) {
                    $headFavicon = trim($r['setting_value']);
                }
                if ($r['setting_key'] === 'branding_name_part1' && $r['setting_value'] !== null) {
                    $headNamePart1 = trim($r['setting_value']);
                }
                if ($r['setting_key'] === 'branding_name_part2' && $r['setting_value'] !== null) {
                    $headNamePart2 = trim($r['setting_value']);
                }
            }
        } catch (PDOException $e) {
            // Defaults wie in head.php
        }
    }
    $headAppName = trim($headNamePart1 . ' ' . $headNamePart2);
    if ($headAppName === '') {
        $headAppName = 'Serohub';
    }
    $pageTitle = (string) $httpCode . ' | ' . $headAppName;
    if (preg_match('#^https?://#i', $headFavicon)) {
        $faviconUrl = $headFavicon;
    } else {
        $faviconUrl = BASE_URL . ltrim($headFavicon, '/');
    }
    return ['pageTitle' => $pageTitle, 'faviconUrl' => $faviconUrl];
}

/**
 * Liefert eine Fehlerseite aus errors/. 403.html nutzt Platzhalter {{PAGE_TITLE}}, {{FAVICON_URL}};
 * 404.html und 500.html sind statisch mit eigenem Titel/Favicon.
 *
 * @param string $templateBasename Dateiname unter errors/, z. B. "404.html"
 * @param int $httpCode HTTP-Statuscode
 * @param string $fallbackPlainText Kurztext, falls die Template-Datei fehlt
 */
function renderBrandedErrorPage(string $templateBasename, int $httpCode, string $fallbackPlainText): void {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: text/html; charset=utf-8');
    }

    $safeName = basename($templateBasename);
    if ($safeName === '' || !preg_match('/\.(html|php)$/i', $safeName)) {
        $safeName = '403.html';
    }
    $file = dirname(__DIR__) . '/errors/' . $safeName;
    if (!is_readable($file)) {
        $b = resolveErrorPageBranding($httpCode);
        $t = htmlspecialchars($b['pageTitle'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $f = htmlspecialchars($b['faviconUrl'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $msg = htmlspecialchars($fallbackPlainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $t . '</title><link rel="icon" href="' . $f . '"></head><body style="font-family:system-ui,sans-serif;padding:2rem;background:#f9fafb;color:#111827;">' . $msg . '</body></html>';
        exit;
    }
    if (preg_match('/\.php$/i', $safeName)) {
        include $file;
        exit;
    }

    $html = file_get_contents($file);
    if (strpos($html, '{{PAGE_TITLE}}') !== false) {
        $b = resolveErrorPageBranding($httpCode);
        $html = str_replace(
            ['{{PAGE_TITLE}}', '{{FAVICON_URL}}'],
            [htmlspecialchars($b['pageTitle'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), htmlspecialchars($b['faviconUrl'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
            $html
        );
    }
    echo $html;
    exit;
}

/**
 * Liefert den konfigurierten Dateinamen für die gewünschte Fehlerseite (system_settings),
 * oder den angegebenen Standardwert.
 *
 * Erlaubt Werte wie:
 * - "404.html"
 * - "my-custom-404.php"
 * - "/errors/404.php" (wird auf Dateinamen reduziert)
 */
function resolveConfiguredErrorTemplate(int $httpCode, string $defaultTemplateBasename): string {
    $safeDefault = basename($defaultTemplateBasename);
    if ($safeDefault === '' || !preg_match('/\.(html|php)$/i', $safeDefault)) {
        $safeDefault = '403.html';
    }

    $settingKeyMap = [
        403 => 'error_page_403',
        404 => 'error_page_404',
        500 => 'error_page_500'
    ];
    $settingKey = $settingKeyMap[$httpCode] ?? null;
    if ($settingKey === null) {
        return $safeDefault;
    }

    global $pdo;
    if (!isset($pdo)) {
        return $safeDefault;
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$settingKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $candidate = trim((string) ($row['setting_value'] ?? ''));
        if ($candidate === '') {
            return $safeDefault;
        }
        $safeCandidate = basename($candidate);
        if ($safeCandidate === '' || !preg_match('/\.(html|php)$/i', $safeCandidate)) {
            return $safeDefault;
        }
        return $safeCandidate;
    } catch (PDOException $e) {
        return $safeDefault;
    }
}

/**
 * Zeigt die Fehlerseite „Keine Berechtigung“ (errors/403.html).
 */
function showPermissionDeniedPage(): void {
    $template = resolveConfiguredErrorTemplate(403, '403.html');
    renderBrandedErrorPage($template, 403, 'Sie haben keine Berechtigung für diese Seite.');
}

