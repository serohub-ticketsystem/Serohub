<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/passkey_webauthn.php';
require_once dirname(__DIR__) . '/assets/user_profile_fields.php';
requireLogin();

user_profile_fields_ensure_columns($pdo);

$passkeyVendorReady = passkey_vendor_ready();
$passkeyHttpsReady = passkey_is_https_request();
$settingsPasskeyUi = $passkeyVendorReady && $passkeyHttpsReady;
$sectionParam = isset($_GET['section']) ? strtolower(trim((string) $_GET['section'])) : '';
$allowedSectionParams = ['preferences', 'notifications', 'security'];
$isMobileSectionView = in_array($sectionParam, $allowedSectionParams, true);
$mobileSectionLabels = [
    'preferences' => 'Präferenzen',
    'notifications' => 'Benachrichtigungen',
    'security' => 'Sicherheit',
];
$mobileSectionTitle = $mobileSectionLabels[$sectionParam] ?? 'Einstellungen';
$initialTabMap = [
    'preferences' => 'preferences',
    'notifications' => 'notifications',
    'security' => 'password',
];
$initialPanel = $initialTabMap[$sectionParam] ?? 'preferences';
if (!$isMobileSectionView) {
    $initialPanel = 'preferences';
}
$isInitialPreferences = $initialPanel === 'preferences';
$isInitialNotifications = $initialPanel === 'notifications';
$isInitialPassword = $initialPanel === 'password';
if ($isMobileSectionView) {
    $navMobileCompactTitle = $mobileSectionTitle;
    $navMobileCompactBackUrl = BASE_URL . 'settings/';
    $navMobileCompactBackLabel = 'Zurück zu Einstellungen';
}

// BASE_URL definieren falls nicht bereits definiert
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
$user = null;
$delegateCandidates = [];
$timezoneOptions = user_profile_fields_timezone_options();
$twoFaEnabled = false;
$trustedDevices = [];
$activeSessions = [];
$rememberMeActive = false;

try {
    $profileExtraSql = user_profile_fields_select_extra_sql($pdo);
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.vorname, u.nachname, u.telefonnummer, u.rolle, u.status, u.company_id, u.logopfad,
               c.name as company_name
               {$profileExtraSql}
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $delegateCandidates = user_profile_fields_delegate_candidates(
        $pdo,
        $userId,
        !empty($user['company_id']) ? (int) $user['company_id'] : null
    );
    $timezoneOptions = user_profile_fields_timezone_options();
    
    // Prüfen ob 2FA aktiviert ist
    $twoFaStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_enabled'");
    $twoFaStmt->execute([$userId]);
    $twoFaSetting = $twoFaStmt->fetch(PDO::FETCH_ASSOC);
    $twoFaEnabled = $twoFaSetting && $twoFaSetting['setting_value'] === '1';
    
    // Aktive Anmeldungen (Sessions) laden – nur wenn Tabelle existiert
    try {
        $sessionsStmt = $pdo->prepare("
            SELECT id, session_id, ip_address, user_agent, last_activity, created_at
            FROM user_sessions
            WHERE user_id = ?
            ORDER BY last_activity DESC
        ");
        $sessionsStmt->execute([$userId]);
        $activeSessionsRaw = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
        $currentSessionId = session_id();
        $dedupedSessions = [];
        foreach ($activeSessionsRaw as $sess) {
            $ua = $sess['user_agent'] ?? '';
            $sess['browser'] = 'Unbekannt';
            $sess['os'] = 'Unbekannt';
            $sess['device_type'] = preg_match('/(Android|iPhone|iPad|Mobile|Tablet)/i', $ua) ? 'mobile' : 'desktop';
            if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera|MSIE)[\/\s](\d+\.\d+)/i', $ua, $m)) {
                $sess['browser'] = $m[1];
            }
            if (preg_match('/(Windows NT|Windows|Mac OS X|Linux|iPhone|iPad|Android)/i', $ua, $m)) {
                $os = $m[1];
                if ($os === 'Windows NT') $os = 'Windows';
                if ($os === 'Mac OS X') $os = 'macOS';
                $sess['os'] = $os;
            }
            $sess['is_current'] = ($sess['session_id'] === $currentSessionId);
            // Gleiche Geräte können historisch mehrfach als Session erfasst sein.
            // Für die Anzeige führen wir solche Einträge zusammen und behalten den relevantesten Datensatz.
            $normalizedUa = strtolower(trim((string) $ua));
            $deviceFingerprint = !empty($normalizedUa)
                ? 'ua:' . md5($normalizedUa)
                : 'fallback:' . md5(strtolower(trim(($sess['os'] ?? '') . '|' . ($sess['browser'] ?? '') . '|' . ($sess['ip_address'] ?? ''))));
            $existing = $dedupedSessions[$deviceFingerprint] ?? null;
            if ($existing === null) {
                $dedupedSessions[$deviceFingerprint] = $sess;
                continue;
            }
            $existingTs = strtotime((string) ($existing['last_activity'] ?? '')) ?: 0;
            $newTs = strtotime((string) ($sess['last_activity'] ?? '')) ?: 0;
            $replace = false;
            // Aktuelle Sitzung hat immer Vorrang.
            if (!empty($sess['is_current']) && empty($existing['is_current'])) {
                $replace = true;
            } elseif ($newTs > $existingTs) {
                $replace = true;
            }
            if ($replace) {
                $sess['is_current'] = !empty($sess['is_current']) || !empty($existing['is_current']);
                $dedupedSessions[$deviceFingerprint] = $sess;
            } else {
                $dedupedSessions[$deviceFingerprint]['is_current'] = !empty($existing['is_current']) || !empty($sess['is_current']);
            }
        }
        $activeSessions = array_values($dedupedSessions);
    } catch (PDOException $e) {
        // Tabelle user_sessions existiert ggf. noch nicht
    }
    
    // Prüfen ob „Angemeldet bleiben“ auf diesem Gerät aktiv ist
    $rememberMeToken = $_COOKIE[REMEMBER_ME_COOKIE_NAME] ?? '';
    if (!empty($rememberMeToken) && strlen($rememberMeToken) === 64) {
        $tokenHash = hash('sha256', $rememberMeToken);
        $rmStmt = $pdo->prepare("SELECT id FROM remember_me_tokens WHERE user_id = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1");
        $rmStmt->execute([$userId, $tokenHash]);
        $rememberMeActive = (bool) $rmStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Vertraute Geräte laden, wenn 2FA aktiviert ist
    if ($twoFaEnabled) {
        $devicesStmt = $pdo->prepare("
            SELECT id, device_name, user_agent, ip_address, last_used, created_at
            FROM trusted_devices
            WHERE user_id = ?
            ORDER BY last_used DESC
            LIMIT 5
        ");
        $devicesStmt->execute([$userId]);
        $trustedDevices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Browser und OS für jedes Gerät extrahieren
        foreach ($trustedDevices as &$device) {
            $userAgent = $device['user_agent'] ?? '';
            $device['browser'] = 'Unbekannt';
            $device['os'] = 'Unbekannt';
            
            // Browser erkennen
            if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera|MSIE)[\/\s](\d+\.\d+)/i', $userAgent, $matches)) {
                $device['browser'] = $matches[1];
            }
            
            // OS erkennen
            if (preg_match('/(Windows NT|Windows|Mac OS X|Linux|iPhone|iPad|Android)/i', $userAgent, $matches)) {
                $os = $matches[1];
                if ($os === 'Windows NT') $os = 'Windows';
                if ($os === 'Mac OS X') $os = 'macOS';
                $device['os'] = $os;
            }
            
            // Falls kein Gerätename gesetzt, automatisch generieren
            if (empty($device['device_name'])) {
                if ($device['os'] !== 'Unbekannt' && $device['browser'] !== 'Unbekannt') {
                    $device['device_name'] = $device['os'] . ' - ' . $device['browser'];
                } else {
                    $device['device_name'] = 'Unbekanntes Gerät';
                }
            }
        }
        unset($device);
    }
    
    // Avatar-Pfad bestimmen (wird für Vorschau benötigt)
    $avatarPath = !empty($user['logopfad']) ? $user['logopfad'] : BASE_URL . 'assets/images/default-avatar.png';
    $isPresetAvatar = false;
    $presetColor = null;
    
    if (!empty($user['logopfad'])) {
        // Prüfen ob es ein Preset-Avatar ist (Format: preset:color:initials)
        if (str_starts_with($user['logopfad'], 'preset:')) {
            $isPresetAvatar = true;
            $parts = explode(':', $user['logopfad']);
            if (count($parts) >= 2) {
                $presetColor = $parts[1];
            }
            $avatarPath = ''; // Für Preset-Avatare wird kein Bild-Pfad verwendet
        } elseif (!str_starts_with($user['logopfad'], 'http') && !str_starts_with($user['logopfad'], '/')) {
            $avatarPath = BASE_URL . $user['logopfad'];
        } elseif (str_starts_with($user['logopfad'], '/')) {
            $avatarPath = $user['logopfad'];
        }
    }
    
    // Initialen für Preset-Avatar generieren
    $initials = '';
    if (!empty($user['vorname']) && !empty($user['nachname'])) {
        $initials = strtoupper(substr($user['vorname'], 0, 1) . substr($user['nachname'], 0, 1));
    } elseif (!empty($user['email'])) {
        $initials = strtoupper(substr($user['email'], 0, 1));
    } else {
        $initials = 'U';
    }

    // Vorgefertigte Avatar-Farben (wie Onboarding Schritt 3)
    $presetAvatars = [
        ['color' => '#3b82f6', 'name' => 'Blau'],
        ['color' => '#10b981', 'name' => 'Grün'],
        ['color' => '#f59e0b', 'name' => 'Orange'],
        ['color' => '#ef4444', 'name' => 'Rot'],
        ['color' => '#8b5cf6', 'name' => 'Lila'],
        ['color' => '#ec4899', 'name' => 'Rosa'],
        ['color' => '#06b6d4', 'name' => 'Cyan'],
        ['color' => '#6366f1', 'name' => 'Indigo'],
        ['color' => '#14b8a6', 'name' => 'Türkis'],
        ['color' => '#f97316', 'name' => 'Orange dunkel'],
        ['color' => '#84cc16', 'name' => 'Lime'],
        ['color' => '#0ea5e9', 'name' => 'Himmelblau'],
    ];
    $avatarColorPickerValue = '#3b82f6';
    if ($isPresetAvatar && !empty($presetColor)) {
        $avatarColorPickerValue = str_starts_with($presetColor, '#') ? $presetColor : '#' . $presetColor;
    }
} catch (PDOException $e) {
    error_log("Settings: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
<style>
@media (max-width: 1023.98px) {
  #preferences.mobile-preferences-cards > .mt-4.rounded-lg {
    margin-top: 0 !important;
    margin-bottom: 1.5rem !important;
    border-radius: 0.75rem !important;
  }

  #preferences.mobile-preferences-cards > .mt-4.rounded-lg:last-child {
    margin-bottom: 0 !important;
  }
}

@media (min-width: 1024px) {
  .settings-toggle-row-clickable {
    user-select: none;
    -webkit-user-select: none;
  }

  html, body {
    height: 100%;
    overflow: hidden !important;
  }

  #default-tab .settings-tab-btn {
    background-color: transparent !important;
    color: #374151 !important;
    border-left: 2px solid transparent !important;
  }

  #default-tab .settings-tab-btn:hover {
    background-color: #f3f4f6 !important;
    color: #111827 !important;
  }

  #default-tab .settings-tab-btn[aria-selected="true"] {
    background-color: #ede9fe !important;
    color: #5b21b6 !important;
    border-left-color: #7c3aed !important;
  }
}
</style>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden lg:overflow-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 lg:h-screen app-mobile-no-root-overscroll">
  <main class="mx-4 mt-2 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 lg:flex lg:h-[calc(100vh-0.5rem)] lg:flex-col lg:overflow-hidden">
    <nav class="mb-4 flex flex-shrink-0 hidden lg:flex" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
            <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
            </svg>
            Startseite
          </a>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Einstellungen</span>
          </div>
        </li>
      </ol>
    </nav>
    <div class="lg:flex-1 lg:min-h-0">
      <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50 lg:h-full lg:min-h-0">
        <!-- Settings Content -->
        <div class="col-span-full lg:min-h-0">
          <div class="<?php echo $isMobileSectionView ? 'hidden' : 'mb-4 grid'; ?> min-h-[calc(100svh-15rem)] grid-cols-1 gap-3.5 pt-0 lg:hidden">
            <a href="<?php echo BASE_URL; ?>settings/index.php?section=notifications" class="group flex min-h-[7.5rem] flex-1 items-center justify-between rounded-xl border border-gray-200/90 bg-white p-4 shadow-sm transition-colors duration-200 hover:border-primary-200 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-700/60 dark:hover:bg-gray-700/70">
              <div class="flex min-w-0 flex-1 items-center gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white ring-1 ring-primary-500/40 dark:bg-primary-500 dark:text-white dark:ring-primary-400/40">
                  <svg class="h-6 w-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                  </svg>
                </span>
                <div class="min-w-0">
                  <h2 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Benachrichtigungen</h2>
                  <p class="mt-1 text-sm leading-snug text-gray-500 dark:text-gray-400">Push, Desktop und In-App-Benachrichtigungen einstellen</p>
                </div>
              </div>
              <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-primary-600 dark:group-hover:text-primary-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
              </svg>
            </a>

            <a href="<?php echo BASE_URL; ?>settings/index.php?section=preferences" class="group flex min-h-[7.5rem] flex-1 items-center justify-between rounded-xl border border-gray-200/90 bg-white p-4 shadow-sm transition-colors duration-200 hover:border-primary-200 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-700/60 dark:hover:bg-gray-700/70">
              <div class="flex min-w-0 flex-1 items-center gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white ring-1 ring-primary-500/40 dark:bg-primary-500 dark:text-white dark:ring-primary-400/40">
                  <svg class="h-6 w-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z"/>
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
                  </svg>
                </span>
                <div class="min-w-0">
                  <h2 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Präferenzen</h2>
                  <p class="mt-1 text-sm leading-snug text-gray-500 dark:text-gray-400">Darstellung, Suchoptionen und persönliche App-Vorgaben</p>
                </div>
              </div>
              <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-primary-600 dark:group-hover:text-primary-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
              </svg>
            </a>

            <a href="<?php echo BASE_URL; ?>settings/index.php?section=security" class="group flex min-h-[7.5rem] flex-1 items-center justify-between rounded-xl border border-gray-200/90 bg-white p-4 shadow-sm transition-colors duration-200 hover:border-primary-200 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-700/60 dark:hover:bg-gray-700/70">
              <div class="flex min-w-0 flex-1 items-center gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white ring-1 ring-primary-500/40 dark:bg-primary-500 dark:text-white dark:ring-primary-400/40">
                  <svg class="h-6 w-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                  </svg>
                </span>
                <div class="min-w-0">
                  <h2 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Sicherheit</h2>
                  <p class="mt-1 text-sm leading-snug text-gray-500 dark:text-gray-400">Passwort, 2FA, Passkeys und aktive Geräte verwalten</p>
                </div>
              </div>
              <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-primary-600 dark:group-hover:text-primary-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
              </svg>
            </a>
            <div class="grid grid-cols-1 gap-3">
              <button
                type="button"
                data-reset-search-filters-btn
                class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-red-300 bg-white px-4 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-200 dark:border-red-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:focus:ring-red-900"
              >
                Systemfilter zurücksetzen
              </button>
              <button type="button" id="mobile-reset-all-settings-btn" data-reset-all-settings-btn class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-red-300 bg-white px-4 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-200 dark:border-red-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:focus:ring-red-900">
                <span id="mobile-reset-all-settings-btn-spinner" data-reset-all-spinner class="hidden" role="status" aria-hidden="true">
                  <svg aria-hidden="true" class="h-4 w-4 animate-spin text-red-300 fill-red-700 dark:text-red-900 dark:fill-red-300" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                    <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                  </svg>
                  <span class="sr-only">Loading...</span>
                </span>
                <span id="mobile-reset-all-settings-btn-label" data-reset-all-label>Alle Einstellungen auf Standard zurücksetzen</span>
              </button>
            </div>
          </div>

          <div class="lg:grid lg:grid-cols-12 lg:gap-4 lg:items-start lg:h-full lg:min-h-0">
          <div class="hidden rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 mb-4 lg:col-span-3 lg:mb-0 lg:block lg:self-start">
            <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
              <div class="flex items-center gap-3">
                <a href="<?php echo BASE_URL; ?>account/index.php" class="shrink-0 rounded-full focus:outline-none">
                  <?php if ($isPresetAvatar && $presetColor): ?>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 text-sm font-semibold text-white dark:border-gray-600" style="background-color: <?php echo htmlspecialchars($presetColor, ENT_QUOTES, 'UTF-8'); ?>;">
                      <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                  <?php else: ?>
                    <img src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profilbild" class="h-12 w-12 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                  <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>account/index.php" class="min-w-0 block w-full rounded-md p-1 -m-1 focus:outline-none">
                  <p class="truncate text-base font-semibold text-gray-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-300">
                    <?php echo htmlspecialchars(trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? '')) ?: ($user['email'] ?? 'Benutzer'), ENT_QUOTES, 'UTF-8'); ?>
                  </p>
                  <p class="truncate text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['rolle'] ?? 'Benutzer', ENT_QUOTES, 'UTF-8'); ?></p>
                </a>
              </div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-1.5 border-b border-gray-200 pb-4 dark:border-gray-700">
              <a href="<?php echo BASE_URL; ?>account/index.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z" />
                </svg>
                Persönliche Daten
              </a>
              <a href="<?php echo BASE_URL; ?>account/my-company.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/>
                </svg>
                Meine Firma
              </a>
              <a href="<?php echo BASE_URL; ?>notifications/" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
                </svg>
                Benachrichtigungen
              </a>
            </div>

            <ul class="-mb-px grid grid-cols-1 gap-1.5 text-base font-semibold" data-tabs-active-classes="!bg-primary-100 !text-primary-800 dark:!bg-primary-800/40 dark:!text-primary-200" data-tabs-inactive-classes="!bg-transparent !text-gray-700 hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white" id="default-tab" data-tabs-toggle="#default-tab-content" role="tablist">
              <li role="presentation">
                <button class="settings-tab-btn inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left <?php echo $isInitialPreferences ? '!border-primary-700 dark:!border-primary-300 !bg-primary-100 !text-primary-800 dark:!bg-primary-800/40 dark:!text-primary-200' : ''; ?>" id="preferences-tab" data-tabs-target="#preferences" data-tab-hash="praeferenzen" type="button" role="tab" aria-controls="preferences" aria-selected="<?php echo $isInitialPreferences ? 'true' : 'false'; ?>">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M20 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6h-2m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4"/>
                  </svg>
                  Präferenzen
                </button>
              </li>
              <li role="presentation">
                <button class="settings-tab-btn inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left <?php echo $isInitialNotifications ? '!border-primary-700 dark:!border-primary-300 !bg-primary-100 !text-primary-800 dark:!bg-primary-800/40 dark:!text-primary-200' : ''; ?>" id="notifications-tab" data-tabs-target="#notifications" data-tab-hash="benachrichtigungen" type="button" role="tab" aria-controls="notifications" aria-selected="<?php echo $isInitialNotifications ? 'true' : 'false'; ?>">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z" />
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                  </svg>
                  Benachrichtigungen
                </button>
              </li>
              <li role="presentation">
                <button class="settings-tab-btn inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left <?php echo $isInitialPassword ? '!border-primary-700 dark:!border-primary-300 !bg-primary-100 !text-primary-800 dark:!bg-primary-800/40 dark:!text-primary-200' : ''; ?>" id="password-tab" data-tabs-target="#password" data-tab-hash="sicherheit" type="button" role="tab" aria-controls="password" aria-selected="<?php echo $isInitialPassword ? 'true' : 'false'; ?>">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                  </svg>
                  Sicherheit
                </button>
              </li>
              <li>
                <button type="button" data-reset-all-settings-btn class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                  <span data-reset-all-spinner class="me-2 hidden" role="status" aria-hidden="true">
                    <svg aria-hidden="true" class="h-4 w-4 animate-spin text-gray-300 fill-gray-700 dark:text-gray-600 dark:fill-gray-300" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                      <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                    </svg>
                  </span>
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 9H8a5 5 0 0 0 0 10h9m4-10-4-4m4 4-4 4"/>
                  </svg>
                  <span data-reset-all-label>Einstellungen zurücksetzen</span>
                </button>
              </li>
            </ul>
            <ul class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
              <li>
                <a href="<?php echo BASE_URL; ?>logout.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left font-semibold text-red-700 transition-colors hover:bg-red-50 hover:text-red-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300">
                  <svg class="me-2 h-5 w-5 text-red-700 dark:text-red-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2" />
                  </svg>
                  Abmelden
                </a>
              </li>
            </ul>
          </div>

          <div id="default-tab-content" class="<?php echo $isMobileSectionView ? 'block' : 'hidden lg:block lg:col-span-9 lg:h-full lg:min-h-0 lg:overflow-y-auto lg:pb-20'; ?>">
            <!-- Präferenzen Tab (zuerst / Standard) -->
            <div class="<?php echo $isInitialPreferences ? '' : 'hidden '; ?>mobile-preferences-cards space-y-3 lg:pb-6" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
              
              <!-- Chat-Anzeige (Tickets) -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-col gap-4">
                  <div>
                    <h4 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Ticket-Chat: Name anzeigen</h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leg fest, welcher Name in der Chat-Ansicht der Tickets angezeigt wird</p>
                  </div>
                  <div class="grid w-full grid-cols-1 gap-3.5 lg:grid-cols-3" role="group" aria-label="Chat-Anzeige">
                    <button type="button" data-chat-display-value="anforderer" class="chat-display-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="chat-display-title block text-sm font-semibold text-gray-900 dark:text-gray-100">Anforderer</span>
                      </span>
                      <span class="chat-display-indicator ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                    <button type="button" data-chat-display-value="firma" class="chat-display-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="chat-display-title block text-sm font-semibold text-gray-900 dark:text-gray-100">Firma</span>
                      </span>
                      <span class="chat-display-indicator ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                    <button type="button" data-chat-display-value="kunde" class="chat-display-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="chat-display-title block text-sm font-semibold text-gray-900 dark:text-gray-100">Kunde</span>
                      </span>
                      <span class="chat-display-indicator ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Sidebar: ausgeklappt -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <h4 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Sidebar ausgeklappt</h4>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="sidebar-expanded-toggle" value="1" class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>
                <div id="sidebar-hover-expand-row" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between gap-3">
                    <div>
                      <h5 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Bei Hover erweitern</h5>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                      <input type="checkbox" id="sidebar-expand-on-hover-toggle" value="1" class="peer sr-only">
                      <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                    </label>
                  </div>
                </div>
              </div>

              <?php if (in_array($user['rolle'] ?? '', ['Admin', 'Techniker'], true)): ?>
              <!-- Aufgaben-Zähler in der Sidebar -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4">
                  <div>
                    <h4 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Aufgaben-Zähler in der Sidebar</h4>
                  </div>
                </div>
                <div class="grid w-full grid-cols-1 gap-3.5 lg:grid-cols-2" role="group" aria-label="Aufgaben-Zähler">
                    <button type="button" data-sidebar-todos-value="all" class="sidebar-todos-count-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="sidebar-todos-count-title block text-sm font-semibold text-gray-900 dark:text-gray-100">Alle (inkl. Ordner)</span>
                      </span>
                      <span class="sidebar-todos-count-indicator ms-4 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                    <button type="button" data-sidebar-todos-value="no_folder" class="sidebar-todos-count-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="sidebar-todos-count-title block text-sm font-semibold text-gray-900 dark:text-gray-100">Nur ohne Ordner</span>
                      </span>
                      <span class="sidebar-todos-count-indicator ms-4 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                </div>
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                  <div class="flex items-center justify-between gap-3 rounded-lg py-1.5">
                    <div>
                      <h5 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Ticket-Ordner</h5>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                      <input type="checkbox" id="ticket-tasks-require-folder-toggle" value="1" class="peer sr-only">
                      <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                    </label>
                  </div>
                </div>
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                  <div class="flex items-center justify-between gap-3 rounded-lg py-1.5">
                    <div>
                      <h5 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Projekt-Ordner</h5>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                      <input type="checkbox" id="project-tasks-require-folder-toggle" value="1" class="peer sr-only">
                      <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Tickets-Zähler in der Sidebar -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4">
                  <div>
                    <h4 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Tickets-Zähler in der Sidebar</h4>
                  </div>
                </div>
                <div class="grid w-full grid-cols-1 gap-3.5 lg:grid-cols-3" role="group" aria-label="Tickets-Zähler">
                    <button type="button" data-sidebar-tickets-value="all" class="sidebar-tickets-count-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="sidebar-tickets-count-title block text-sm font-semibold text-gray-900 dark:text-gray-100">Alle offenen</span>
                      </span>
                      <span class="sidebar-tickets-count-indicator ms-4 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                    <button type="button" data-sidebar-tickets-value="company" class="sidebar-tickets-count-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="sidebar-tickets-count-title block text-sm font-semibold text-gray-900 dark:text-gray-100">Ausgewählte Firma</span>
                      </span>
                      <span class="sidebar-tickets-count-indicator ms-4 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                    <button type="button" data-sidebar-tickets-value="filters" class="sidebar-tickets-count-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="sidebar-tickets-count-title block text-sm font-semibold text-gray-900 dark:text-gray-100">Aktive Filter</span>
                      </span>
                      <span class="sidebar-tickets-count-indicator ms-4 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                </div>
              </div>
              <?php endif; ?>

              <!-- Speed Dial: Einträge auswählen -->
              <?php
              $sdIsAdminOrTechniker = in_array($user['rolle'] ?? '', ['Admin', 'Techniker'], true);
              $sdIsNotKunde = ($user['rolle'] ?? '') !== 'Kunde';
              $sdCanSeeCustomers = in_array($user['rolle'] ?? '', ['Admin', 'Techniker', 'Firmen-Admin'], true);
              $sdCanSeeLink = !in_array($user['rolle'] ?? '', ['Firmen-User', 'Kunde'], true);
              ?>
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div id="speed-dial-header-row" class="mb-0 flex items-center justify-between gap-4">
                  <div class="min-w-0">
                    <h4 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Speed Dial (Schnell hinzufügen)</h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Standardmäßig ausgeblendet. Schalter aktivieren, um das Menü unten rechts anzuzeigen.</p>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="speed-dial-visible-toggle" value="1" class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>
                <div id="speed-dial-items-container" class="hidden grid grid-cols-2 gap-2 sm:grid-cols-2">
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="service">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Ticket</span>
                  </label>
                  <?php if ($sdCanSeeCustomers): ?>
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="kunde">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Kunde</span>
                  </label>
                  <?php endif; ?>
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="geraet">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Gerät</span>
                  </label>
                  <?php if ($sdIsAdminOrTechniker): ?>
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="firma">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Firma</span>
                  </label>
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="inventar">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Lager</span>
                  </label>
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="projekt">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Projekt</span>
                  </label>
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="aufgabe">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Aufgabe</span>
                  </label>
                  <?php endif; ?>
                  <?php if ($sdIsNotKunde): ?>
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="bestellung">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Bestellung</span>
                  </label>
                  <?php endif; ?>
                  <?php if ($sdCanSeeLink): ?>
                  <label class="inline-flex min-h-[2.5rem] cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <input type="checkbox" class="speed-dial-item-cb h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800" data-key="link">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Verknüpfung</span>
                  </label>
                  <?php endif; ?>
                </div>
              </div>

              <?php if (in_array($user['rolle'] ?? '', ['Admin', 'Techniker'], true)): ?>
              <a href="<?php echo BASE_URL; ?>settings/calendar-export.php" class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-6 shadow-sm focus:outline-none lg:hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:lg:hover:bg-gray-700/80">
                <div>
                  <h4 class="text-[1.05rem] font-semibold tracking-tight text-gray-900 dark:text-white">Kalenderexport</h4>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">ICS-Link, Export-Inhalte und CalDAV-Synchronisation mit Nextcloud oder anderen Servern einrichten</p>
                </div>
                <svg class="ms-4 h-5 w-5 flex-shrink-0 text-gray-400 transition-transform group-hover:translate-x-0.5 dark:text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                </svg>
              </a>
              <?php endif; ?>

              <!-- Easy Mode (Einfache Oberfläche) -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-4">
                  <h4 class="text-base font-semibold text-gray-900 dark:text-white">Einfache Oberfläche</h4>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="easy-mode-toggle" value="1" class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Aktiviere die einfache Oberfläche mit großen Karten für eine bessere Übersichtlichkeit</p>
              </div>

              <!-- Mobile Startseite -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div id="mobile-start-header-row" class="mb-4 flex items-center justify-between gap-4">
                  <h4 class="text-base font-semibold text-gray-900 dark:text-white">Feste Mobile Startseite</h4>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="mobile-start-enabled-toggle" value="1" class="peer sr-only" checked>
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>
                <div id="mobile-start-settings-container" class="space-y-4">
                  <div>
                    <select id="mobile-start-page-select" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
                      <option value="dashboard">Dashboard</option>
                      <option value="tickets">Tickets</option>
                      <option value="todos">Aufgaben</option>
                      <option value="inventory">Lager</option>
                      <option value="service">Service</option>
                      <option value="knowledge">Wissensdatenbank</option>
                      <option value="kalender">Kalender</option>
                      <option value="devices">Geräte</option>
                      <option value="orders">Bestellungen</option>
                      <option value="companies">Firmen</option>
                      <option value="customers">Kunden</option>
                      <option value="projects">Projekte</option>
                      <option value="notes">Notizen</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Profil: nur Weiterleitung (Stammdaten liegen unter Mein Konto) -->
            <div class="hidden" id="profile" role="tabpanel" aria-labelledby="profile-tab">
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Profil &amp; Stammdaten</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  Persönliche Daten (Name, Kontakt, Stellvertretung usw.) bearbeitest du unter <strong>Mein Konto</strong>.
                  Hier in den Einstellungen findest du nur Präferenzen, Benachrichtigungen und Sicherheit.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                  <a href="<?php echo BASE_URL; ?>account/" class="inline-flex items-center rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800 dark:bg-primary-600">
                    Zu Mein Konto
                  </a>
                  <a href="<?php echo BASE_URL; ?>settings/profil-einstellungen.php" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                    Technische Datenübersicht
                  </a>
                </div>
              </div>
            </div>

            <!-- Benachrichtigungen Tab -->
            <div class="<?php echo $isInitialNotifications ? '' : 'hidden '; ?>space-y-3" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
              
              <!-- E-Mail-Benachrichtigungen -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                <div class="flex items-center justify-between">
                  <div class="flex-1">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">E-Mail-Benachrichtigungen</h4>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="email-notifications-toggle" value="" class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                  <a href="<?php echo BASE_URL; ?>settings/email-preferences.php" class="flex items-center justify-between rounded-lg py-1.5">
                    <div class="min-w-0">
                      <h5 class="text-base font-semibold text-gray-900 dark:text-white">E-Mail-Präferenzen</h5>
                    </div>
                    <svg class="ms-3 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                    </svg>
                  </a>
                </div>
              </div>

              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                <div class="flex items-center justify-between">
                  <div class="flex-1">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">System-Benachrichtigungen</h4>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="system-notifications-toggle" value="" class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                  <div class="flex items-center justify-between rounded-lg py-1.5">
                    <div class="min-w-0">
                      <h5 class="text-base font-semibold text-gray-900 dark:text-white">Eigene ausblenden</h5>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                      <input type="checkbox" id="system-hide-own-toggle" value="" class="peer sr-only">
                      <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                    </label>
                  </div>
                </div>
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                  <div class="flex items-center justify-between rounded-lg py-1.5">
                    <div class="min-w-0">
                      <h5 class="text-base font-semibold text-gray-900 dark:text-white">Push-Benachrichtigungen</h5>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                      <input type="checkbox" id="system-push-toggle" value="" class="peer sr-only">
                      <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                    </label>
                  </div>
                </div>
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                  <div class="flex items-center justify-between rounded-lg py-1.5">
                    <h5 class="text-base font-semibold text-gray-900 dark:text-white">Desktop-Hinweise</h5>
                    <label class="relative inline-flex cursor-pointer items-center">
                      <input type="checkbox" id="desktopNotifEnabledToggle" class="sr-only peer" aria-describedby="desktop-notif-desc">
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600 peer-disabled:opacity-50 peer-disabled:cursor-not-allowed"></div>
                    </label>
                  </div>
                  <p id="desktopNotifPermissionText" class="mt-3 text-sm text-gray-700 dark:text-gray-300" role="status"></p>
                  <span class="sr-only" id="desktop-notif-desc">Desktop-Hinweise aktiv</span>
                </div>
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                  <a href="<?php echo BASE_URL; ?>settings/notification-settings.php" class="flex items-center justify-between rounded-lg py-1.5">
                    <div class="min-w-0">
                      <h5 class="text-base font-semibold text-gray-900 dark:text-white">Benachrichtigungs-Präferenzen</h5>
                    </div>
                    <svg class="ms-3 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                    </svg>
                  </a>
                </div>
              </div>

              <!-- Sounds (Aufgabe erledigt, Ticket geschlossen, neue Benachrichtigung) -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Töne</h4>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="sounds-enabled-toggle" value="1" class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>
              </div>

              <!-- Toast-Benachrichtigungen -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                <div class="flex flex-col gap-4">
                  <div>
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Toast-Benachrichtigungen</h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Wähle, wann Hinweise im System angezeigt werden</p>
                  </div>
                  <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-3" role="group" aria-label="Toast anzeigen">
                    <button type="button" data-toast-value="all" class="toast-display-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3.5 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">Alle anzeigen</span>
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Alle Hinweise werden eingeblendet</span>
                      </span>
                      <span class="toast-display-indicator ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                    <button type="button" data-toast-value="errors_only" class="toast-display-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3.5 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">Nur Fehler</span>
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Nur wichtige Fehlhinweise anzeigen</span>
                      </span>
                      <span class="toast-display-indicator ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                    <button type="button" data-toast-value="none" class="toast-display-btn inline-flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3.5 py-3 text-left transition-colors hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">Nicht anzeigen</span>
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Keine Toast-Hinweise anzeigen</span>
                      </span>
                      <span class="toast-display-indicator ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 text-transparent dark:border-gray-500">
                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/>
                        </svg>
                      </span>
                    </button>
                  </div>
                </div>
              </div>

            </div>

            <!-- Passwort Tab -->
            <div class="<?php echo $isInitialPassword ? '' : 'hidden '; ?>space-y-3" id="password" role="tabpanel" aria-labelledby="password-tab">
             
              
              <div class="grid grid-cols-2 gap-3">
                <a href="<?php echo BASE_URL; ?>settings/resetpasswort.php" class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/60">
                  <div class="min-w-0">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Passwort</h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ändern</p>
                  </div>
                  <svg class="ms-3 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                  </svg>
                </a>
                <a href="<?php echo BASE_URL; ?>settings/twofa.php" class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/60">
                  <div class="min-w-0">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Zwei-Faktor</h4>
                    <p class="mt-1 text-sm <?php echo $twoFaEnabled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                      <?php echo $twoFaEnabled ? 'Aktiviert' : 'Deaktiviert'; ?>
                    </p>
                  </div>
                  <svg class="ms-3 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                  </svg>
                </a>
              </div>

              <?php if (!empty($passkeyVendorReady)): ?>
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100" id="passkeys-settings-section">
                <?php if (!empty($settingsPasskeyUi)): ?>
                  <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                      <h4 class="text-base font-semibold text-gray-900 dark:text-white">Passkeys</h4>
                      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Melde dich parallel zum Passwort mit Face&nbsp;ID, Touch&nbsp;ID oder System-Passkey an. Nach dem Klick bestätigt dein Gerät die Registrierung.
                      </p>
                    </div>
                    <button type="button" id="passkey-add-btn" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-400">
                      Passkey hinzufügen
                    </button>
                  </div>
                  <div id="passkey-list-wrap" class="divide-y divide-gray-200 dark:divide-gray-700">
                    <p id="passkey-list-loading" class="p-4 text-sm text-gray-500 dark:text-gray-400">Lade Passkeys…</p>
                    <ul id="passkey-list" class="hidden divide-y divide-gray-200 dark:divide-gray-700"></ul>
                    <p id="passkey-list-empty" class="hidden p-4 text-sm text-gray-500 dark:text-gray-400">Noch kein Passkey hinterlegt.</p>
                  </div>
                <?php else: ?>
                  <h4 class="text-base font-semibold text-gray-900 dark:text-white">Passkeys</h4>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Passkeys sind nur mit einer sicheren HTTPS-Verbindung verfuegbar.
                  </p>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              
              <!-- Aktive Anmeldungen (angemeldete Geräte) -->
              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                <div class="mb-4">
                  <div class="flex items-center justify-between gap-3">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Aktive Anmeldungen</h4>
                    <button type="button" id="logout-everywhere-btn" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:ring-primary-400">
                      Überall abmelden
                    </button>
                  </div>
                  <?php if ($rememberMeActive): ?>
                    <div class="mt-3 rounded-lg bg-gray-100 p-3 dark:bg-gray-700/80">
                      <p class="text-sm text-gray-800 dark:text-gray-100">
                        <span class="font-medium">Angemeldet bleiben aktiv</span>
                        <span class="mx-1 text-gray-400 dark:text-gray-400">•</span>
                        <?php echo (int) REMEMBER_ME_LIFETIME_DAYS; ?> Tage
                      </p>
                    </div>
                  <?php endif; ?>
                </div>
                <?php if (empty($activeSessions)): ?>
                  <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-700/50">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Keine weiteren aktiven Anmeldungen erfasst. Nur diese Sitzung wird gezählt.</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Nach dem nächsten Seitenaufruf erscheint dieses Gerät hier.</p>
                  </div>
                <?php else: ?>
                  <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($activeSessions as $sess): ?>
                      <?php
                        $lastActive = new DateTime($sess['last_activity']);
                        $now = new DateTime();
                        $diff = $now->diff($lastActive);
                        $minutesAgo = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;
                        $isActive = $minutesAgo < 30;
                      ?>
                      <a href="<?php echo BASE_URL; ?>settings/session-details.php?id=<?php echo (int) ($sess['id'] ?? 0); ?>&sid=<?php echo urlencode((string) ($sess['session_id'] ?? '')); ?>" class="flex items-center justify-between py-3 transition-colors hover:bg-gray-50/70 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:hover:bg-gray-700/40 dark:focus:ring-primary-400">
                        <div class="flex items-center flex-1 min-w-0">
                          <div class="flex-shrink-0 mr-3">
                            <div class="h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-700/70 flex items-center justify-center">
                              <?php if (($sess['device_type'] ?? 'desktop') === 'mobile'): ?>
                                <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 4h8a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"></path>
                                </svg>
                              <?php else: ?>
                                <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"></path>
                                </svg>
                              <?php endif; ?>
                            </div>
                          </div>
                          <div class="flex-1 min-w-0">
                            <div class="flex items-center flex-wrap gap-2">
                              <p class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($sess['os']); ?> – <?php echo htmlspecialchars($sess['browser']); ?>
                              </p>
                              <?php if ($isActive): ?>
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">Aktiv</span>
                              <?php endif; ?>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                              <?php if (empty($sess['is_current'])): ?>
                                <span>IP: <?php echo htmlspecialchars($sess['ip_address']); ?></span>
                              <?php endif; ?>
                              <?php if (!empty($sess['is_current'])): ?>
                                <span>Dieses Gerät</span>
                              <?php endif; ?>
                              <span>
                                <?php
                                  if ($minutesAgo < 1) echo 'Gerade eben';
                                  elseif ($minutesAgo < 60) echo 'vor ' . $minutesAgo . ' Min.';
                                  elseif ($diff->h < 24) echo 'vor ' . $diff->h . ' Std.';
                                  elseif ($diff->days == 1) echo 'Gestern';
                                  else echo 'vor ' . $diff->days . ' Tagen';
                                ?>
                              </span>
                            </div>
                          </div>
                        </div>
                        <svg class="ms-3 h-5 w-5 shrink-0 text-gray-400 lg:hidden" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                        </svg>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
              
              <!-- Vertraute Geräte (nur wenn 2FA aktiviert) -->
              <?php if ($twoFaEnabled): ?>
              <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 max-lg:hidden">
                <div class="mb-4 flex items-center justify-between">
                  <div class="flex-1">
                    <div class="flex items-center mb-2">
                      <svg class="me-2 h-5 w-5 text-green-600 dark:text-green-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                      </svg>
                      <h4 class="text-base font-semibold text-gray-900 dark:text-white">Vertraute Geräte</h4>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Geräte, die du als vertraut markiert hast, benötigen 30 Tage lang keinen 2FA-Code beim Login.</p>
                  </div>
                  <a href="<?php echo BASE_URL; ?>settings/trusted-devices.php" class="ml-4 inline-flex items-center rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                    Alle anzeigen
                  </a>
                </div>
                
                <?php if (empty($trustedDevices)): ?>
                  <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-700/50">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      Du hast noch keine Geräte als vertraut markiert.
                    </p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                      Du kannst beim nächsten 2FA-Login ein Gerät als vertraut markieren.
                    </p>
                  </div>
                <?php else: ?>
                  <div class="space-y-3">
                    <?php foreach ($trustedDevices as $device): ?>
                      <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-700/50">
                        <div class="flex items-center flex-1 min-w-0">
                          <div class="flex-shrink-0 mr-3">
                            <div class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                              <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                              </svg>
                            </div>
                          </div>
                          <div class="flex-1 min-w-0">
                            <div class="flex items-center">
                              <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                <?php echo htmlspecialchars($device['device_name']); ?>
                              </p>
                              <?php 
                              $lastUsed = new DateTime($device['last_used']);
                              $now = new DateTime();
                              $diff = $now->diff($lastUsed);
                              $isRecent = $diff->days < 7;
                              if ($isRecent):
                              ?>
                                <span class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                  Aktiv
                                </span>
                              <?php endif; ?>
                            </div>
                            <div class="mt-1 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                              <span><?php echo htmlspecialchars($device['browser']); ?> auf <?php echo htmlspecialchars($device['os']); ?></span>
                              <span>•</span>
                              <span>
                                <?php 
                                if ($diff->days == 0) {
                                    if ($diff->h == 0) {
                                        echo 'vor ' . $diff->i . ' Min';
                                    } else {
                                        echo 'vor ' . $diff->h . ' Std';
                                    }
                                } elseif ($diff->days == 1) {
                                    echo 'Gestern';
                                } elseif ($diff->days < 7) {
                                    echo 'vor ' . $diff->days . ' Tagen';
                                } else {
                                    echo 'vor ' . $diff->days . ' Tagen';
                                }
                                ?>
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($trustedDevices) >= 5): ?>
                      <div class="text-center pt-2">
                        <a href="<?php echo BASE_URL; ?>settings/trusted-devices.php" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                          Alle vertrauten Geräte anzeigen →
                        </a>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>

          </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
const mobileSettingsSection = '<?php echo htmlspecialchars($sectionParam, ENT_QUOTES, 'UTF-8'); ?>';
document.addEventListener('DOMContentLoaded', function() {
    // Hash-basierte Tab-Navigation: #sicherheit, #praeferenzen, #profil, #benachrichtigungen
    var hashToTabId = {
        'sicherheit': 'password-tab',
        'praeferenzen': 'preferences-tab',
        'präferenzen': 'preferences-tab',
        'preferences': 'preferences-tab',
        'profil': 'profile-tab',
        'profile': 'profile-tab',
        'benachrichtigungen': 'notifications-tab',
        'notifications': 'notifications-tab'
    };
    function getHash() {
        var h = (window.location.hash || '').replace(/^#/, '').toLowerCase();
        return h;
    }
    function activateTabByHash() {
        var hash = getHash();
        if (!hash) return;
        if (hash === 'profil' || hash === 'profile') {
            window.location.href = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'account/';
            return;
        }
        var tabId = hashToTabId[hash];
        if (tabId) {
            var btn = document.getElementById(tabId);
            if (btn) {
                btn.click();
            }
        }
    }
    // Beim Laden: Hash auswerten und Tab aktivieren
    setTimeout(activateTabByHash, 50);
    if (mobileSettingsSection) {
        var sectionToTabId = {
            'preferences': 'preferences-tab',
            'notifications': 'notifications-tab',
            'security': 'password-tab'
        };
        var sectionTabId = sectionToTabId[mobileSettingsSection];
        if (sectionTabId) {
            var sectionBtn = document.getElementById(sectionTabId);
            if (sectionBtn) {
                setTimeout(function() {
                    sectionBtn.click();
                }, 60);
            }
        }
    }
    window.addEventListener('hashchange', activateTabByHash);
    // Beim Klick auf einen Tab: Hash setzen (direkt erreichbare Links)
    document.querySelectorAll('.settings-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var hash = this.getAttribute('data-tab-hash');
            if (hash && getHash() !== hash) {
                window.location.hash = hash;
            }
        });
    });

    function enableDesktopToggleRowClicks() {
        var toggleInputs = document.querySelectorAll('input[type="checkbox"].peer.sr-only[id]');
        toggleInputs.forEach(function(input) {
            var label = input.closest('label');
            var row = label ? label.parentElement : null;
            if (!row || row.dataset.desktopToggleRowBound === '1') return;
            if (!row.classList.contains('flex') || !row.classList.contains('justify-between')) return;

            row.dataset.desktopToggleRowBound = '1';
            row.classList.add('cursor-pointer', 'settings-toggle-row-clickable');
            row.addEventListener('click', function(event) {
                if (window.innerWidth < 1024) return;
                if (event.target.closest('label, input, button, a, select, textarea')) return;
                if (input.disabled) return;
                input.checked = !input.checked;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }
    enableDesktopToggleRowClicks();

    const profileForm = document.getElementById('profileForm');
    const profileImageInput = document.getElementById('profile_image');
    const profileImagePreview = document.getElementById('profile-image-preview');
    const profileImagePreviewImg = document.getElementById('profile-image-preview-img');
    const profileImagePreviewPreset = document.getElementById('profile-image-preview-preset');
    const settingsPresetColorInput = document.getElementById('settings-preset-color');
    const settingsColorPicker = document.getElementById('settings-avatar-color-picker');
    const settingsPresetBtns = document.querySelectorAll('.settings-preset-avatar-btn');
    const profileFormEl = document.getElementById('profileForm');
    const userInitialsFromServer = profileFormEl ? (profileFormEl.getAttribute('data-user-initials') || 'U') : 'U';

    function normalizeSettingsHex(c) {
        if (!c) return '';
        c = String(c).trim();
        if (c.charAt(0) !== '#') c = '#' + c;
        return c.toLowerCase();
    }

    function clearSettingsPresetButtonHighlights() {
        settingsPresetBtns.forEach(function(btn) {
            btn.classList.remove('border-primary-500', 'ring-2', 'ring-primary-500', 'ring-offset-2', 'dark:ring-offset-gray-800');
            btn.classList.add('border-gray-300', 'dark:border-gray-600');
        });
    }

    function highlightSettingsPresetButtonForColor(hex) {
        var n = normalizeSettingsHex(hex);
        settingsPresetBtns.forEach(function(btn) {
            var bc = normalizeSettingsHex(btn.getAttribute('data-color') || '');
            if (bc === n) {
                btn.classList.add('border-primary-500', 'ring-2', 'ring-primary-500', 'ring-offset-2', 'dark:ring-offset-gray-800');
                btn.classList.remove('border-gray-300', 'dark:border-gray-600');
            } else {
                btn.classList.remove('border-primary-500', 'ring-2', 'ring-primary-500', 'ring-offset-2', 'dark:ring-offset-gray-800');
                btn.classList.add('border-gray-300', 'dark:border-gray-600');
            }
        });
    }

    function applySettingsAvatarPresetPreview(color) {
        var hex = normalizeSettingsHex(color);
        if (!hex || !/^#[0-9a-f]{6}$/.test(hex)) return;
        var container = document.getElementById('profile-image-preview');
        if (!container) return;
        if (container.tagName === 'IMG') {
            var div = document.createElement('div');
            div.id = 'profile-image-preview';
            div.className = 'h-24 w-24 rounded-full border-2 border-gray-200 dark:border-gray-600 flex items-center justify-center text-white text-3xl font-bold';
            div.style.backgroundColor = hex;
            div.innerHTML = '<span>' + userInitialsFromServer + '</span><img id="profile-image-preview-img" src="" alt="Profilbild" class="hidden h-full w-full rounded-full object-cover">';
            container.parentNode.replaceChild(div, container);
        } else {
            container.style.backgroundColor = hex;
            var span = container.querySelector('span');
            if (span) span.textContent = userInitialsFromServer;
            var innerImg = container.querySelector('#profile-image-preview-img');
            if (innerImg) innerImg.classList.add('hidden');
        }
        if (settingsColorPicker) settingsColorPicker.value = hex;
    }

    settingsPresetBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var color = this.getAttribute('data-color');
            if (!color || !settingsPresetColorInput) return;
            settingsPresetColorInput.value = color;
            applySettingsAvatarPresetPreview(color);
            highlightSettingsPresetButtonForColor(color);
            if (profileImageInput) profileImageInput.value = '';
        });
    });

    if (settingsColorPicker && settingsPresetColorInput) {
        settingsColorPicker.addEventListener('input', function() {
            var v = this.value;
            settingsPresetColorInput.value = v;
            applySettingsAvatarPresetPreview(v);
            highlightSettingsPresetButtonForColor(v);
            if (profileImageInput) profileImageInput.value = '';
        });
    }

    if (settingsPresetColorInput && settingsPresetColorInput.value) {
        highlightSettingsPresetButtonForColor(settingsPresetColorInput.value);
    }

    // Profilbild-Vorschau beim Auswählen
    if (profileImageInput && profileImagePreview) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validierung
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!allowedTypes.includes(file.type)) {
                    if (typeof showToast === 'function') {
                        showToast('Ungültiger Dateityp. Nur Bilder sind erlaubt.', 'error');
                    } else {
                        alert('Ungültiger Dateityp. Nur Bilder sind erlaubt.');
                    }
                    e.target.value = '';
                    return;
                }
                
                if (file.size > maxSize) {
                    if (typeof showToast === 'function') {
                        showToast('Datei ist zu groß (max. 5MB)', 'error');
                    } else {
                        alert('Datei ist zu groß (max. 5MB)');
                    }
                    e.target.value = '';
                    return;
                }

                if (settingsPresetColorInput) settingsPresetColorInput.value = '';
                clearSettingsPresetButtonHighlights();

                // Vorschau anzeigen
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Wenn es ein DIV ist (Preset-Avatar), konvertiere zu IMG
                    if (profileImagePreview.tagName === 'DIV') {
                        const img = document.createElement('img');
                        img.id = 'profile-image-preview';
                        img.src = e.target.result;
                        img.alt = 'Profilbild';
                        img.className = 'h-24 w-24 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600';
                        profileImagePreview.parentNode.replaceChild(img, profileImagePreview);
                    } else {
                        profileImagePreview.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Formular absenden
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = profileForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Speichere...';
            
            try {
                // FormData für Datei-Upload
                const formData = new FormData();
                formData.append('vorname', document.getElementById('vorname').value.trim());
                formData.append('nachname', document.getElementById('nachname').value.trim());
                formData.append('email', document.getElementById('email').value.trim());
                formData.append('telefonnummer', document.getElementById('telefonnummer').value.trim());
                ['mobilnummer', 'anrede', 'position_funktion', 'abteilung', 'sprache', 'zeitzone', 'kontaktkanal', 'kontakt_messenger', 'erreichbarkeit', 'stellvertreter_user_id', 'stellvertreter_von', 'stellvertreter_bis'].forEach(function(fieldId) {
                    var el = document.getElementById(fieldId);
                    if (el) formData.append(fieldId, el.value.trim());
                });
                
                // Profilbild hinzufügen, falls ausgewählt (hat Vorrang vor Farb-Avatar)
                if (profileImageInput && profileImageInput.files.length > 0) {
                    formData.append('profile_image', profileImageInput.files[0]);
                } else if (settingsPresetColorInput) {
                    var pc = settingsPresetColorInput.value.trim();
                    if (pc && /^#?[0-9A-Fa-f]{6}$/.test(pc)) {
                        formData.append('avatar_type', 'preset');
                        formData.append('preset_color', pc.charAt(0) === '#' ? pc : '#' + pc);
                    }
                }

                // API Base URL
                const apiBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>';
                
                const response = await fetch(apiBaseUrl + 'settings/api/profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Aktualisiere Profilbild-Vorschau mit neuem Pfad
                    if (data.user && data.user.logopfad) {
                        const previewImg = document.getElementById('profile-image-preview-img');
                        const previewPreset = document.getElementById('profile-image-preview-preset');
                        const previewContainer = document.getElementById('profile-image-preview');
                        
                        if (data.user.logopfad.startsWith('preset:')) {
                            // Preset-Avatar
                            const parts = data.user.logopfad.split(':');
                            const color = parts[1] || '#3b82f6';
                            const initials = parts[2] || data.user.initials || 'U';
                            
                            if (previewContainer && previewContainer.tagName === 'IMG') {
                                // Erstelle DIV-Container für Preset
                                const container = document.createElement('div');
                                container.id = 'profile-image-preview';
                                container.className = 'h-24 w-24 rounded-full border-2 border-gray-200 dark:border-gray-600 flex items-center justify-center text-white text-3xl font-bold';
                                container.style.backgroundColor = color;
                                container.innerHTML = '<span>' + initials + '</span><img id="profile-image-preview-img" src="" alt="Profilbild" class="hidden h-full w-full rounded-full object-cover">';
                                previewContainer.parentNode.replaceChild(container, previewContainer);
                            } else if (previewContainer) {
                                previewContainer.style.backgroundColor = color;
                                const span = previewContainer.querySelector('span');
                                if (span) span.textContent = initials;
                                if (previewImg) previewImg.classList.add('hidden');
                            }
                        } else {
                            // Normales Bild
                            let newImagePath = data.user.logopfad;
                            if (!newImagePath.startsWith('http') && !newImagePath.startsWith('/')) {
                                newImagePath = apiBaseUrl + newImagePath;
                            } else if (newImagePath.startsWith('/')) {
                                newImagePath = newImagePath;
                            }
                            
                            if (previewContainer && previewContainer.tagName === 'DIV') {
                                // Erstelle IMG-Element
                                const img = document.createElement('img');
                                img.id = 'profile-image-preview';
                                img.src = newImagePath;
                                img.alt = 'Profilbild';
                                img.className = 'h-24 w-24 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600';
                                previewContainer.parentNode.replaceChild(img, previewContainer);
                            } else if (previewContainer) {
                                previewContainer.src = newImagePath;
                            }
                        }
                    }
                    
                    if (typeof showToast === 'function') {
                        showToast('Profil erfolgreich aktualisiert', 'success');
                    } else {
                        alert('Profil erfolgreich aktualisiert');
                    }
                    
                    // Seite nach kurzer Verzögerung neu laden, um alle Änderungen zu sehen
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    throw new Error(data.error || 'Fehler beim Speichern');
                }
            } catch (error) {
                console.error('Fehler:', error);
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Speichern der Einstellungen', 'error');
                } else {
                    alert(error.message || 'Fehler beim Speichern der Einstellungen');
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // Toast-Anzeige: Button-Gruppe – Wert laden und bei Klick speichern
    const toastDisplayBtns = document.querySelectorAll('.toast-display-btn');
    const toastDisplayContainer = toastDisplayBtns.length ? toastDisplayBtns[0].closest('[role="group"]') : null;
    const setToastBtnActive = function(value) {
        const isDark = document.documentElement.classList.contains('dark');
        toastDisplayBtns.forEach(function(btn) {
            const v = btn.getAttribute('data-toast-value');
            const indicator = btn.querySelector('.toast-display-indicator');
            const titleEl = btn.querySelector('span.block.text-sm');
            const descEl = btn.querySelector('span.block.text-xs');
            if (v === value) {
                btn.classList.add('!border-primary-600', 'dark:!border-primary-500');
                btn.classList.remove('border-gray-300', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                btn.style.backgroundColor = isDark ? '#334155' : '#e5e7eb';
                btn.style.color = isDark ? '#f8fafc' : '#111827';
                if (titleEl) {
                    titleEl.style.color = isDark ? '#f8fafc' : '#111827';
                }
                if (descEl) {
                    descEl.style.color = isDark ? '#cbd5e1' : '#4b5563';
                }
                if (indicator) {
                    indicator.classList.add('!border-white', '!bg-white');
                    indicator.classList.remove('border-gray-300', 'dark:border-gray-500', 'text-transparent');
                    indicator.style.color = isDark ? '#334155' : '#111827';
                }
            } else {
                btn.classList.remove('!border-primary-600', 'dark:!border-primary-500');
                btn.classList.add('border-gray-300', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                btn.style.backgroundColor = '';
                btn.style.color = '';
                if (titleEl) {
                    titleEl.style.color = '';
                }
                if (descEl) {
                    descEl.style.color = '';
                }
                if (indicator) {
                    indicator.classList.remove('!border-white', '!bg-white');
                    indicator.classList.add('border-gray-300', 'dark:border-gray-500', 'text-transparent');
                    indicator.style.color = '';
                }
            }
        });
    };
    if (toastDisplayContainer) {
        let toastDisplayPrev = 'errors_only';
        (async function() {
            try {
                const r = await fetch('<?php echo BASE_URL; ?>settings/api/toast.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } });
                if (r.ok) {
                    const d = await r.json();
                    if (d.success && d.toast_display) {
                        toastDisplayPrev = d.toast_display;
                        setToastBtnActive(d.toast_display);
                    }
                }
            } catch (err) { console.error('Toast-Einstellung laden:', err); }
        })();
        toastDisplayBtns.forEach(function(btn) {
            btn.addEventListener('click', async function() {
                const value = this.getAttribute('data-toast-value');
                const prev = toastDisplayPrev;
                setToastBtnActive(value);
                try {
                    const r = await fetch('<?php echo BASE_URL; ?>settings/api/toast.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ toast_display: value })
                    });
                    const d = await r.json();
                    if (r.ok && d.success) {
                        window.toastDisplaySetting = value;
                        toastDisplayPrev = value;
                        if (typeof showToast === 'function') showToast('Toast-Einstellung gespeichert', 'success');
                    } else {
                        setToastBtnActive(prev);
                        if (typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
                    }
                } catch (err) {
                    setToastBtnActive(prev);
                    if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
                }
            });
        });
    }

    // Überall abmelden: alle anderen Geräte abmelden
    (function() {
        const btn = document.getElementById('logout-everywhere-btn');
        if (!btn) return;
        btn.addEventListener('click', async function() {
            if (!confirm('Alle anderen Geräte werden abgemeldet. Du bleibst auf diesem Gerät angemeldet. Fortfahren?')) return;
            btn.disabled = true;
            const originalText = btn.textContent;
            btn.textContent = 'Wird ausgeführt…';
            try {
                const r = await fetch('<?php echo BASE_URL; ?>settings/api/logout-everywhere.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const d = await r.json();
                if (r.ok && d.success) {
                    if (typeof showToast === 'function') showToast(d.message || 'Alle anderen Geräte wurden abgemeldet.', 'success');
                    setTimeout(function() { window.location.reload(); }, 800);
                } else {
                    if (typeof showToast === 'function') showToast(d.message || 'Aktion fehlgeschlagen.', 'error');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (err) {
                if (typeof showToast === 'function') showToast('Fehler beim Abmelden auf anderen Geräten.', 'error');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    })();

    <?php if (!empty($settingsPasskeyUi)): ?>
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
        function parseCreationOptions(opts) {
            const out = Object.assign({}, opts);
            out.challenge = base64UrlToBuffer(opts.challenge);
            out.user = Object.assign({}, opts.user, { id: base64UrlToBuffer(opts.user.id) });
            if (opts.excludeCredentials && opts.excludeCredentials.length) {
                out.excludeCredentials = opts.excludeCredentials.map(function(c) {
                    const d = Object.assign({}, c);
                    d.id = base64UrlToBuffer(c.id);
                    return d;
                });
            }
            return out;
        }
        function attestationToServer(credential) {
            const r = credential.response;
            return {
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: bufferToBase64url(r.clientDataJSON),
                    attestationObject: bufferToBase64url(r.attestationObject),
                    transports: r.getTransports ? r.getTransports() : []
                }
            };
        }
        const listEl = document.getElementById('passkey-list');
        const loadingEl = document.getElementById('passkey-list-loading');
        const emptyEl = document.getElementById('passkey-list-empty');
        const addBtn = document.getElementById('passkey-add-btn');
        const apiList = '<?php echo BASE_URL; ?>settings/api/passkeys.php';
        const apiRegOpts = '<?php echo BASE_URL; ?>login/api/passkey-register-options.php';
        const apiRegVerify = '<?php echo BASE_URL; ?>login/api/passkey-register-verify.php';

        async function refreshPasskeyList() {
            if (!listEl) return;
            try {
                const r = await fetch(apiList, { credentials: 'same-origin' });
                const d = await r.json();
                if (loadingEl) loadingEl.classList.add('hidden');
                if (!d.success || !Array.isArray(d.passkeys)) {
                    if (emptyEl) { emptyEl.textContent = 'Passkeys konnten nicht geladen werden.'; emptyEl.classList.remove('hidden'); }
                    return;
                }
                listEl.innerHTML = '';
                if (d.passkeys.length === 0) {
                    listEl.classList.add('hidden');
                    if (emptyEl) emptyEl.classList.remove('hidden');
                    return;
                }
                if (emptyEl) emptyEl.classList.add('hidden');
                listEl.classList.remove('hidden');
                d.passkeys.forEach(function(pk) {
                    const li = document.createElement('li');
                    li.className = 'flex flex-wrap items-center justify-between gap-2 py-3';
                    const label = (pk.label && pk.label.trim()) ? pk.label : ('Passkey #' + pk.id);
                    const dt = pk.created_at ? new Date(pk.created_at.replace(' ', 'T')).toLocaleString('de-DE') : '';
                    li.innerHTML = '<div><span class="text-sm font-medium text-gray-900 dark:text-white">' +
                        label.replace(/</g, '&lt;') + '</span>' +
                        '<span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Hinzugefügt: ' + dt + '</span></div>';
                    const del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:ring-primary-400';
                    del.textContent = 'Entfernen';
                    del.addEventListener('click', async function() {
                        if (!confirm('Diesen Passkey wirklich entfernen? Du kannst ihn nicht mehr für die Anmeldung nutzen.')) return;
                        try {
                            const rr = await fetch(apiList + '?id=' + encodeURIComponent(pk.id), { method: 'DELETE', credentials: 'same-origin' });
                            const dd = await rr.json();
                            if (dd.success) {
                                if (typeof showToast === 'function') showToast('Passkey entfernt.', 'success');
                                refreshPasskeyList();
                            } else {
                                if (typeof showToast === 'function') showToast(dd.error || 'Fehler', 'error');
                            }
                        } catch (e) {
                            if (typeof showToast === 'function') showToast('Fehler beim Entfernen.', 'error');
                        }
                    });
                    li.appendChild(del);
                    listEl.appendChild(li);
                });
            } catch (e) {
                if (loadingEl) loadingEl.classList.add('hidden');
                if (emptyEl) { emptyEl.textContent = 'Passkeys konnten nicht geladen werden.'; emptyEl.classList.remove('hidden'); }
            }
        }

        if (listEl) refreshPasskeyList();

        if (addBtn && window.PublicKeyCredential) {
            addBtn.addEventListener('click', async function() {
                addBtn.disabled = true;
                const prev = addBtn.textContent;
                addBtn.textContent = 'Warte auf Gerät…';
                try {
                    const optRes = await fetch(apiRegOpts, { method: 'POST', credentials: 'same-origin' });
                    const optJson = await optRes.json();
                    if (!optJson.success) throw new Error(optJson.error || 'Start fehlgeschlagen');
                    const pubKey = parseCreationOptions(optJson.options);
                    const credential = await navigator.credentials.create({ publicKey: pubKey });
                    if (!credential) throw new Error('Abgebrochen');
                    const verifyRes = await fetch(apiRegVerify, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ credential: attestationToServer(credential) })
                    });
                    const verifyJson = await verifyRes.json();
                    if (!verifyJson.success) throw new Error(verifyJson.error || 'Speichern fehlgeschlagen');
                    if (typeof showToast === 'function') showToast('Passkey gespeichert.', 'success');
                    refreshPasskeyList();
                } catch (err) {
                    if (typeof showToast === 'function') showToast(err.message || 'Passkey konnte nicht hinzugefügt werden.', 'error');
                    else alert(err.message || 'Fehler');
                }
                addBtn.disabled = false;
                addBtn.textContent = prev;
            });
        } else if (addBtn) {
            addBtn.disabled = true;
            addBtn.title = 'Passkeys werden von diesem Browser nicht unterstützt.';
        }
    })();
    <?php endif; ?>

    // Töne an/aus: Toggle laden und speichern, localStorage für system-sounds.js setzen
    (function() {
        const soundsToggle = document.getElementById('sounds-enabled-toggle');
        if (!soundsToggle) return;
        fetch('<?php echo BASE_URL; ?>settings/api/sounds-enabled.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    soundsToggle.checked = d.enabled;
                    try { localStorage.setItem('sounds_enabled', d.enabled ? '1' : '0'); } catch (e) {}
                }
            })
            .catch(function() {});
        soundsToggle.addEventListener('change', function() {
            const enabled = this.checked;
            fetch('<?php echo BASE_URL; ?>settings/api/sounds-enabled.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: enabled })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    try { localStorage.setItem('sounds_enabled', enabled ? '1' : '0'); } catch (e) {}
                    if (typeof showToast === 'function') showToast(enabled ? 'Töne aktiviert' : 'Töne deaktiviert', 'success');
                } else {
                    soundsToggle.checked = !enabled;
                    if (typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
                }
            })
            .catch(function() {
                soundsToggle.checked = !enabled;
                if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
            });
        });
    })();

    // Chat-Anzeige (Tickets): Button-Gruppe – Wert laden und bei Klick speichern
    const chatDisplayBtns = document.querySelectorAll('.chat-display-btn');
    const chatDisplayContainer = chatDisplayBtns.length ? chatDisplayBtns[0].closest('[role="group"][aria-label="Chat-Anzeige"]') : null;
    const setChatDisplayBtnActive = function(value) {
        const isDark = document.documentElement.classList.contains('dark');
        chatDisplayBtns.forEach(function(btn) {
            const v = btn.getAttribute('data-chat-display-value');
            const indicator = btn.querySelector('.chat-display-indicator');
            const titleEl = btn.querySelector('.chat-display-title');
            if (v === value) {
                btn.classList.add('!border-primary-600', 'dark:!border-primary-500');
                btn.classList.remove('border-gray-300', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                btn.style.backgroundColor = isDark ? '#334155' : '#e5e7eb';
                btn.style.color = isDark ? '#f8fafc' : '#111827';
                if (titleEl) titleEl.style.color = isDark ? '#f8fafc' : '#111827';
                if (indicator) {
                    indicator.classList.add('!border-white', '!bg-white');
                    indicator.classList.remove('border-gray-300', 'dark:border-gray-500', 'text-transparent');
                    indicator.style.color = isDark ? '#334155' : '#111827';
                }
            } else {
                btn.classList.remove('!border-primary-600', 'dark:!border-primary-500');
                btn.classList.add('border-gray-300', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                btn.style.backgroundColor = '';
                btn.style.color = '';
                if (titleEl) titleEl.style.color = '';
                if (indicator) {
                    indicator.classList.remove('!border-white', '!bg-white');
                    indicator.classList.add('border-gray-300', 'dark:border-gray-500', 'text-transparent');
                    indicator.style.color = '';
                }
            }
        });
    };
    if (chatDisplayContainer) {
        let chatDisplayNamePrev = 'anforderer';
        (async function() {
            try {
                const r = await fetch('<?php echo BASE_URL; ?>settings/api/chat-display-name.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } });
                if (r.ok) {
                    const d = await r.json();
                    if (d.success && d.chat_display_name) {
                        chatDisplayNamePrev = d.chat_display_name;
                        setChatDisplayBtnActive(d.chat_display_name);
                    }
                }
            } catch (err) { console.error('Chat-Anzeige-Einstellung laden:', err); }
        })();
        chatDisplayBtns.forEach(function(btn) {
            btn.addEventListener('click', async function() {
                const value = this.getAttribute('data-chat-display-value');
                const prev = chatDisplayNamePrev;
                setChatDisplayBtnActive(value);
                try {
                    const r = await fetch('<?php echo BASE_URL; ?>settings/api/chat-display-name.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ chat_display_name: value })
                    });
                    const d = await r.json();
                    if (r.ok && d.success) {
                        chatDisplayNamePrev = value;
                        if (typeof showToast === 'function') showToast('Chat-Anzeige-Einstellung gespeichert', 'success');
                    } else {
                        setChatDisplayBtnActive(prev);
                        if (typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
                    }
                } catch (err) {
                    setChatDisplayBtnActive(prev);
                    if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
                }
            });
        });
    }

    // Sidebar ausgeklappt: Toggle – Wert laden und bei Änderung speichern
    const sidebarExpandedToggle = document.getElementById('sidebar-expanded-toggle');
    const sidebarHoverExpandRow = document.getElementById('sidebar-hover-expand-row');
    if (sidebarExpandedToggle) {
        let sidebarExpandedPrev = true;
        const applySidebarHoverRowVisibility = function(isExpanded) {
            if (!sidebarHoverExpandRow) return;
            if (isExpanded) {
                sidebarHoverExpandRow.classList.add('hidden');
            } else {
                sidebarHoverExpandRow.classList.remove('hidden');
            }
        };
        (async function() {
            try {
                const r = await fetch('<?php echo BASE_URL; ?>settings/api/sidebar-expanded.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } });
                if (r.ok) {
                    const d = await r.json();
                    if (d.success && typeof d.sidebar_expanded !== 'undefined') {
                        sidebarExpandedPrev = !!d.sidebar_expanded;
                        sidebarExpandedToggle.checked = !!d.sidebar_expanded;
                        applySidebarHoverRowVisibility(!!d.sidebar_expanded);
                    }
                }
            } catch (err) { console.error('Sidebar-Einstellung laden:', err); }
        })();

        sidebarExpandedToggle.addEventListener('change', async function() {
            const value = this.checked;
            const prev = sidebarExpandedPrev;
            applySidebarHoverRowVisibility(value);
            try {
                const r = await fetch('<?php echo BASE_URL; ?>settings/api/sidebar-expanded.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sidebar_expanded: value })
                });
                const d = await r.json();
                if (r.ok && d.success) {
                    sidebarExpandedPrev = value;
                    localStorage.setItem('sidebarExpanded', value ? 'true' : 'false');
                    if (typeof setSidebarExpanded === 'function') setSidebarExpanded(value);
                    if (typeof showToast === 'function') showToast('Sidebar-Einstellung gespeichert', 'success');
                } else {
                    sidebarExpandedToggle.checked = prev;
                    applySidebarHoverRowVisibility(prev);
                    if (typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
                }
            } catch (err) {
                sidebarExpandedToggle.checked = prev;
                applySidebarHoverRowVisibility(prev);
                if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
            }
        });
    }

    // Speed Dial: Checkboxen laden und speichern
    (function() {
        const checkboxes = document.querySelectorAll('.speed-dial-item-cb');
        const speedDialVisibleToggle = document.getElementById('speed-dial-visible-toggle');
        const speedDialItemsContainer = document.getElementById('speed-dial-items-container');
        const speedDialHeaderRow = document.getElementById('speed-dial-header-row');
        if (!checkboxes.length || !speedDialVisibleToggle || !speedDialItemsContainer || !speedDialHeaderRow) return;
        const baseUrl = '<?php echo BASE_URL; ?>';
        function applySpeedDialVisibility(isVisible) {
            speedDialItemsContainer.classList.toggle('hidden', !isVisible);
            speedDialHeaderRow.classList.toggle('mb-4', isVisible);
            speedDialHeaderRow.classList.toggle('mb-0', !isVisible);
            checkboxes.forEach(function(cb) {
                cb.disabled = !isVisible;
            });
        }
        fetch(baseUrl + 'settings/api/speed-dial.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && d.items) {
                    checkboxes.forEach(function(cb) {
                        const key = cb.dataset.key;
                        if (key && typeof d.items[key] !== 'undefined') cb.checked = !!d.items[key];
                    });
                }
                const isVisible = !!(d && d.success && d.visible === true);
                speedDialVisibleToggle.checked = isVisible;
                applySpeedDialVisibility(isVisible);
            })
            .catch(function() {});
        function saveSpeedDial() {
            const items = {};
            checkboxes.forEach(function(cb) {
                if (cb.dataset.key) items[cb.dataset.key] = cb.checked;
            });
            fetch(baseUrl + 'settings/api/speed-dial.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: items, visible: speedDialVisibleToggle.checked })
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success && typeof showToast === 'function') showToast('Speed-Dial-Einstellung gespeichert', 'success');
            }).catch(function() {
                if (typeof showToast === 'function') showToast('Speichern fehlgeschlagen', 'error');
            });
        }
        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', saveSpeedDial);
        });
        speedDialVisibleToggle.addEventListener('change', function() {
            applySpeedDialVisibility(this.checked);
            saveSpeedDial();
        });
    })();

    // Sidebar bei Hover erweitern: Toggle laden und speichern
    (function() {
        const toggle = document.getElementById('sidebar-expand-on-hover-toggle');
        if (!toggle) return;
        fetch('<?php echo BASE_URL; ?>settings/api/sidebar-expand-on-hover.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && typeof d.sidebar_expand_on_hover !== 'undefined') toggle.checked = d.sidebar_expand_on_hover;
            })
            .catch(function() {});
        toggle.addEventListener('change', function() {
            const value = this.checked;
            fetch('<?php echo BASE_URL; ?>settings/api/sidebar-expand-on-hover.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sidebar_expand_on_hover: value })
            })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success && typeof showToast === 'function') showToast('Einstellung gespeichert', 'success');
                    else if (!d.success && typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
                });
        });
    })();

    // Aufgaben-Zähler Sidebar: Button-Gruppe – Wert laden und bei Klick speichern
    const sidebarTodosCountBtns = document.querySelectorAll('.sidebar-todos-count-btn');
    const sidebarTodosCountContainer = sidebarTodosCountBtns.length ? sidebarTodosCountBtns[0].closest('[role="group"][aria-label="Aufgaben-Zähler"]') : null;
    const setSidebarTodosCountBtnActive = function(value) {
        const isDark = document.documentElement.classList.contains('dark');
        sidebarTodosCountBtns.forEach(function(btn) {
            const v = btn.getAttribute('data-sidebar-todos-value');
            const indicator = btn.querySelector('.sidebar-todos-count-indicator');
            const titleEl = btn.querySelector('.sidebar-todos-count-title');
            if (v === value) {
                btn.classList.add('!border-primary-600', 'dark:!border-primary-500');
                btn.classList.remove('border-gray-300', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                btn.style.backgroundColor = isDark ? '#334155' : '#e5e7eb';
                btn.style.color = isDark ? '#f8fafc' : '#111827';
                if (titleEl) titleEl.style.color = isDark ? '#f8fafc' : '#111827';
                if (indicator) {
                    indicator.classList.add('!border-white', '!bg-white');
                    indicator.classList.remove('border-gray-300', 'dark:border-gray-500', 'text-transparent');
                    indicator.style.color = isDark ? '#334155' : '#111827';
                }
            } else {
                btn.classList.remove('!border-primary-600', 'dark:!border-primary-500');
                btn.classList.add('border-gray-300', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                btn.style.backgroundColor = '';
                btn.style.color = '';
                if (titleEl) titleEl.style.color = '';
                if (indicator) {
                    indicator.classList.remove('!border-white', '!bg-white');
                    indicator.classList.add('border-gray-300', 'dark:border-gray-500', 'text-transparent');
                    indicator.style.color = '';
                }
            }
        });
    };
    if (sidebarTodosCountContainer) {
        let sidebarTodosCountPrev = 'all';
        (async function() {
            try {
                const r = await fetch('<?php echo BASE_URL; ?>settings/api/sidebar-todos-count.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } });
                if (r.ok) {
                    const d = await r.json();
                    if (d.success && d.sidebar_todos_count) {
                        sidebarTodosCountPrev = d.sidebar_todos_count;
                        setSidebarTodosCountBtnActive(d.sidebar_todos_count);
                    }
                }
            } catch (err) { console.error('Aufgaben-Zähler-Einstellung laden:', err); }
        })();
        sidebarTodosCountBtns.forEach(function(btn) {
            btn.addEventListener('click', async function() {
                const value = this.getAttribute('data-sidebar-todos-value');
                const prev = sidebarTodosCountPrev;
                setSidebarTodosCountBtnActive(value);
                try {
                    const r = await fetch('<?php echo BASE_URL; ?>settings/api/sidebar-todos-count.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ sidebar_todos_count: value })
                    });
                    const d = await r.json();
                    if (r.ok && d.success) {
                        sidebarTodosCountPrev = value;
                        if (typeof showToast === 'function') showToast('Aufgaben-Zähler-Einstellung gespeichert', 'success');
                    } else {
                        setSidebarTodosCountBtnActive(prev);
                        if (typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
                    }
                } catch (err) {
                    setSidebarTodosCountBtnActive(prev);
                    if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
                }
            });
        });
    }

    // Tickets-Zähler Sidebar: Button-Gruppe – Wert laden und bei Klick speichern
    const sidebarTicketsCountBtns = document.querySelectorAll('.sidebar-tickets-count-btn');
    const sidebarTicketsCountContainer = sidebarTicketsCountBtns.length ? sidebarTicketsCountBtns[0].closest('[role="group"][aria-label="Tickets-Zähler"]') : null;
    const setSidebarTicketsCountBtnActive = function(value) {
        const isDark = document.documentElement.classList.contains('dark');
        sidebarTicketsCountBtns.forEach(function(btn) {
            const v = btn.getAttribute('data-sidebar-tickets-value');
            const indicator = btn.querySelector('.sidebar-tickets-count-indicator');
            const titleEl = btn.querySelector('.sidebar-tickets-count-title');
            if (v === value) {
                btn.classList.add('!border-primary-600', 'dark:!border-primary-500');
                btn.classList.remove('border-gray-300', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                btn.style.backgroundColor = isDark ? '#334155' : '#e5e7eb';
                btn.style.color = isDark ? '#f8fafc' : '#111827';
                if (titleEl) titleEl.style.color = isDark ? '#f8fafc' : '#111827';
                if (indicator) {
                    indicator.classList.add('!border-white', '!bg-white');
                    indicator.classList.remove('border-gray-300', 'dark:border-gray-500', 'text-transparent');
                    indicator.style.color = isDark ? '#334155' : '#111827';
                }
            } else {
                btn.classList.remove('!border-primary-600', 'dark:!border-primary-500');
                btn.classList.add('border-gray-300', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                btn.style.backgroundColor = '';
                btn.style.color = '';
                if (titleEl) titleEl.style.color = '';
                if (indicator) {
                    indicator.classList.remove('!border-white', '!bg-white');
                    indicator.classList.add('border-gray-300', 'dark:border-gray-500', 'text-transparent');
                    indicator.style.color = '';
                }
            }
        });
    };
    if (sidebarTicketsCountContainer) {
        let sidebarTicketsCountPrev = 'company';
        (async function() {
            try {
                const r = await fetch('<?php echo BASE_URL; ?>settings/api/sidebar-tickets-count.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } });
                if (r.ok) {
                    const d = await r.json();
                    if (d.success && d.sidebar_tickets_count) {
                        sidebarTicketsCountPrev = d.sidebar_tickets_count;
                        setSidebarTicketsCountBtnActive(d.sidebar_tickets_count);
                    }
                }
            } catch (err) { console.error('Tickets-Zähler-Einstellung laden:', err); }
        })();
        sidebarTicketsCountBtns.forEach(function(btn) {
            btn.addEventListener('click', async function() {
                const value = this.getAttribute('data-sidebar-tickets-value');
                const prev = sidebarTicketsCountPrev;
                setSidebarTicketsCountBtnActive(value);
                try {
                    const r = await fetch('<?php echo BASE_URL; ?>settings/api/sidebar-tickets-count.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ sidebar_tickets_count: value })
                    });
                    const d = await r.json();
                    if (r.ok && d.success) {
                        sidebarTicketsCountPrev = value;
                        if (typeof showToast === 'function') showToast('Tickets-Zähler-Einstellung gespeichert', 'success');
                        if (typeof updateSidebarTicketsCount === 'function') {
                            updateSidebarTicketsCount();
                        } else {
                            fetch('<?php echo BASE_URL; ?>tickets/api/open-count.php', { headers: { 'Content-Type': 'application/json' } })
                                .then(function(res) { return res.json(); })
                                .then(function(od) {
                                    var nodes = document.querySelectorAll('.sidebar-open-tickets-count-badge');
                                    if (!nodes.length) return;
                                    var count = od.success ? (od.open_count || 0) : 0;
                                    var text = count > 99 ? '99' : String(count);
                                    nodes.forEach(function(el) {
                                        el.textContent = text;
                                        el.title = count + ' offene Tickets';
                                        el.classList.toggle('hidden', count <= 0);
                                    });
                                })
                                .catch(function() {});
                        }
                    } else {
                        setSidebarTicketsCountBtnActive(prev);
                        if (typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
                    }
                } catch (err) {
                    setSidebarTicketsCountBtnActive(prev);
                    if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
                }
            });
        });
    }

    // Aufgaben aus Tickets: Ordner „Ticketaufgaben“ erzwingen (Toggle)
    (function() {
        const toggle = document.getElementById('ticket-tasks-require-folder-toggle');
        if (!toggle) return;
        fetch('<?php echo BASE_URL; ?>settings/api/ticket-tasks-require-folder.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) toggle.checked = d.enabled;
            })
            .catch(function() {});
        toggle.addEventListener('change', function() {
            const enabled = this.checked;
            fetch('<?php echo BASE_URL; ?>settings/api/ticket-tasks-require-folder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: enabled })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && typeof showToast === 'function') showToast(enabled ? 'Aufgaben aus Tickets werden dem Ordner „Ticketaufgaben“ zugeordnet' : 'Einstellung deaktiviert', 'success');
                else if (!d.success && typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
            })
            .catch(function() {
                toggle.checked = !enabled;
                if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
            });
        });
    })();

    // Aufgaben aus Projekten: Ordner „Projektaufgaben“ erzwingen (Toggle)
    (function() {
        const toggle = document.getElementById('project-tasks-require-folder-toggle');
        if (!toggle) return;
        fetch('<?php echo BASE_URL; ?>settings/api/project-tasks-require-folder.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) toggle.checked = d.enabled;
            })
            .catch(function() {});
        toggle.addEventListener('change', function() {
            const enabled = this.checked;
            fetch('<?php echo BASE_URL; ?>settings/api/project-tasks-require-folder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: enabled })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && typeof showToast === 'function') showToast(enabled ? 'Aufgaben aus Projekten werden dem Ordner „Projektaufgaben“ zugeordnet' : 'Einstellung deaktiviert', 'success');
                else if (!d.success && typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
            })
            .catch(function() {
                toggle.checked = !enabled;
                if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
            });
        });
    })();

    // Such-/Systemfilter global zurücksetzen
    (function() {
        const buttons = Array.from(document.querySelectorAll('[data-reset-search-filters-btn]'));
        if (!buttons.length) return;
        const baseUrl = '<?php echo BASE_URL; ?>';
        const localStorageKeysToClear = [
            'selectedUserOption',
            'serviceIndexFilters',
            'ticketsStatusFilter',
            'ordersIndexFilters',
            'devicesIndexFilters',
            'todosIndexFilters',
            'companiesIndexFilters',
            'customersIndexFilters',
            'linksIndexFilters',
            'inventory_filters_state'
        ];
        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const confirmed = window.confirm('Alle systemweiten Filter (inkl. globalem Firmenfilter) wirklich auf Standard zurücksetzen?');
                if (!confirmed) return;
                buttons.forEach(function(b) { b.disabled = true; });
                fetch(baseUrl + 'settings/api/reset-search-filters.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                }).then(function(r) { return r.json(); }).then(function(d) {
                    if (d.success) {
                        localStorageKeysToClear.forEach(function(key) {
                            try {
                                localStorage.removeItem(key);
                            } catch (e) {}
                        });
                        if (typeof showToast === 'function') showToast('Alle Systemfilter wurden zurückgesetzt', 'success');
                        window.setTimeout(function() { window.location.reload(); }, 350);
                    } else {
                        buttons.forEach(function(b) { b.disabled = false; });
                        if (typeof showToast === 'function') showToast(d.error || 'Zurücksetzen fehlgeschlagen', 'error');
                    }
                }).catch(function() {
                    buttons.forEach(function(b) { b.disabled = false; });
                    if (typeof showToast === 'function') showToast('Zurücksetzen fehlgeschlagen', 'error');
                });
            });
        });
    })();

    // Ticket-Suche: Suchbereich (Checkboxen) laden und speichern
    (function() {
        const container = document.getElementById('ticket-search-scope-container');
        const template = document.getElementById('ticket-search-scope-template');
        const btnAll = document.getElementById('ticket-search-scope-all');
        const btnNone = document.getElementById('ticket-search-scope-none');
        if (!container || !template) return;
        const baseUrl = '<?php echo BASE_URL; ?>';
        let currentScope = [];
        let allKeys = {};

        function renderCheckboxes() {
            container.querySelectorAll('.ticket-search-scope-cb').forEach(function(el) { el.closest('label')?.remove(); });
            Object.keys(allKeys).forEach(function(key) {
                const label = template.content.cloneNode(true);
                const cb = label.querySelector('.ticket-search-scope-cb');
                const labelText = label.querySelector('.ticket-search-scope-label');
                cb.value = key;
                cb.dataset.key = key;
                labelText.textContent = allKeys[key];
                cb.checked = (currentScope[0] !== '_none') && (currentScope.length === 0 || currentScope.indexOf(key) !== -1);
                cb.addEventListener('change', saveScope);
                container.appendChild(label);
            });
        }

        function saveScope() {
            const checked = Array.from(container.querySelectorAll('.ticket-search-scope-cb:checked')).map(function(c) { return c.value; });
            currentScope = checked.length === Object.keys(allKeys).length ? [] : checked;
            fetch(baseUrl + 'settings/api/ticket-search-scope.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scope: checked.length === Object.keys(allKeys).length ? Object.keys(allKeys) : checked })
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success && typeof showToast === 'function') showToast('Suchbereich gespeichert', 'success');
            }).catch(function() {
                if (typeof showToast === 'function') showToast('Speichern fehlgeschlagen', 'error');
            });
        }

        fetch(baseUrl + 'settings/api/ticket-search-scope.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    currentScope = d.scope || [];
                    allKeys = d.all_keys || {};
                    renderCheckboxes();
                }
            })
            .catch(function(err) { console.error('Ticket-Suchbereich laden:', err); });

        if (btnAll) btnAll.addEventListener('click', function() {
            container.querySelectorAll('.ticket-search-scope-cb').forEach(function(c) { c.checked = true; });
            saveScope();
        });
        if (btnNone) btnNone.addEventListener('click', function() {
            container.querySelectorAll('.ticket-search-scope-cb').forEach(function(c) { c.checked = false; });
            currentScope = [];
            fetch(baseUrl + 'settings/api/ticket-search-scope.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scope: [] })
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success && typeof showToast === 'function') showToast('Suchbereich gespeichert', 'success');
            });
        });
    })();

    // Bestellungs-Suche: Suchbereich (Checkboxen) laden und speichern
    (function() {
        const container = document.getElementById('order-search-scope-container');
        const template = document.getElementById('order-search-scope-template');
        const btnAll = document.getElementById('order-search-scope-all');
        const btnNone = document.getElementById('order-search-scope-none');
        if (!container || !template) return;
        const baseUrl = '<?php echo BASE_URL; ?>';
        let currentScope = [];
        let allKeys = {};

        function renderCheckboxes() {
            container.querySelectorAll('.order-search-scope-cb').forEach(function(el) { el.closest('label')?.remove(); });
            Object.keys(allKeys).forEach(function(key) {
                const label = template.content.cloneNode(true);
                const cb = label.querySelector('.order-search-scope-cb');
                const labelText = label.querySelector('.order-search-scope-label');
                cb.value = key;
                cb.dataset.key = key;
                labelText.textContent = allKeys[key];
                cb.checked = (currentScope[0] !== '_none') && (currentScope.length === 0 || currentScope.indexOf(key) !== -1);
                cb.addEventListener('change', saveScope);
                container.appendChild(label);
            });
        }

        function saveScope() {
            const checked = Array.from(container.querySelectorAll('.order-search-scope-cb:checked')).map(function(c) { return c.value; });
            currentScope = checked.length === Object.keys(allKeys).length ? [] : checked;
            fetch(baseUrl + 'settings/api/order-search-scope.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scope: checked.length === Object.keys(allKeys).length ? Object.keys(allKeys) : checked })
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success && typeof showToast === 'function') showToast('Suchbereich gespeichert', 'success');
            }).catch(function() {
                if (typeof showToast === 'function') showToast('Speichern fehlgeschlagen', 'error');
            });
        }

        fetch(baseUrl + 'settings/api/order-search-scope.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    currentScope = d.scope || [];
                    allKeys = d.all_keys || {};
                    renderCheckboxes();
                }
            })
            .catch(function(err) { console.error('Bestellungs-Suchbereich laden:', err); });

        if (btnAll) btnAll.addEventListener('click', function() {
            container.querySelectorAll('.order-search-scope-cb').forEach(function(c) { c.checked = true; });
            saveScope();
        });
        if (btnNone) btnNone.addEventListener('click', function() {
            container.querySelectorAll('.order-search-scope-cb').forEach(function(c) { c.checked = false; });
            currentScope = [];
            fetch(baseUrl + 'settings/api/order-search-scope.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scope: [] })
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success && typeof showToast === 'function') showToast('Suchbereich gespeichert', 'success');
            });
        });
    })();

    // E-Mail-Benachrichtigungen Toggle Handler
    const emailToggle = document.getElementById('email-notifications-toggle');
    if (emailToggle) {
        // Aktuellen Status beim Laden abrufen
        (async function() {
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/email.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.email_enabled !== undefined) {
                        emailToggle.checked = data.email_enabled;
                    }
                }
            } catch (error) {
                console.error('Fehler beim Laden der E-Mail-Einstellungen:', error);
            }
        })();
        
        // Toggle-Änderung speichern
        emailToggle.addEventListener('change', async function(e) {
            const enabled = e.target.checked;
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ enabled: enabled })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast(`E-Mail-Benachrichtigungen ${enabled ? 'aktiviert' : 'deaktiviert'}`, 'success');
                    }
                } else {
                    throw new Error(data.error || 'Fehler beim Speichern');
                }
            } catch (error) {
                console.error('Fehler:', error);
                e.target.checked = !enabled; // Revert on error
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Aktualisieren der E-Mail-Einstellungen', 'error');
                }
            }
        });
    }

    function updateDesktopHintsUi() {
        const statusEl = document.getElementById('desktopNotifPermissionText');
        const toggle = document.getElementById('desktopNotifEnabledToggle');
        if (!statusEl || !toggle) return;

        if (typeof Notification === 'undefined') {
            statusEl.textContent = 'Dein Browser unterstützt keine Desktop-Benachrichtigungen.';
            toggle.disabled = true;
            return;
        }

        const host = typeof location !== 'undefined' ? location.hostname : '';
        const secure = typeof location !== 'undefined' && (location.protocol === 'https:' || host === 'localhost' || host === '127.0.0.1');
        if (!secure) {
            statusEl.textContent = 'Desktop-Hinweise sind nur über HTTPS oder auf localhost möglich.';
            toggle.disabled = true;
            return;
        }

        const perm = Notification.permission;
        if (perm === 'granted') {
            statusEl.textContent = 'Status: Erlaubnis erteilt. Du kannst den Schalter nutzen, um Hinweise ein- oder auszuschalten.';
            toggle.disabled = false;
            try {
                toggle.checked = localStorage.getItem('svDesktopNotifications') === '1';
            } catch (e) {
                toggle.checked = false;
            }
        } else if (perm === 'denied') {
            statusEl.textContent = 'Status: Abgelehnt. Ändere die Berechtigung in den Browser-Einstellungen für diese Website, um Hinweise zu erhalten.';
            toggle.disabled = true;
            toggle.checked = false;
        } else {
            statusEl.textContent = 'Status: Noch nicht festgelegt. Beim ersten Aktivieren fragt der Browser nach Erlaubnis.';
            toggle.disabled = false;
            try {
                toggle.checked = localStorage.getItem('svDesktopNotifications') === '1';
            } catch (e) {
                toggle.checked = false;
            }
        }
    }

    // System-Benachrichtigungen Toggle Handler
    const systemToggle = document.getElementById('system-notifications-toggle');
    const systemHideOwnToggle = document.getElementById('system-hide-own-toggle');
    const systemPushToggle = document.getElementById('system-push-toggle');
    const desktopNotifEnabledToggle = document.getElementById('desktopNotifEnabledToggle');
    if (systemToggle) {
        (async function() {
            try {
                const response = await fetch('<?php echo BASE_URL; ?>notifications/api/settings.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.system_notifications_enabled !== undefined) {
                        systemToggle.checked = !!data.system_notifications_enabled;
                    } else {
                        systemToggle.checked = true;
                    }
                    if (systemHideOwnToggle && data.success && data.hide_own_notifications !== undefined) {
                        systemHideOwnToggle.checked = !!data.hide_own_notifications;
                    }
                    if (systemPushToggle && data.success && data.push_notifications_enabled !== undefined) {
                        systemPushToggle.checked = !!data.push_notifications_enabled;
                    }
                }
            } catch (error) {
                console.error('Fehler beim Laden der System-Benachrichtigungen:', error);
                systemToggle.checked = true;
            }
            updateDesktopHintsUi();
        })();

        systemToggle.addEventListener('change', async function(e) {
            const enabled = e.target.checked;

            try {
                const response = await fetch('<?php echo BASE_URL; ?>notifications/api/settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ system_notifications_enabled: enabled })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast(`System-Benachrichtigungen ${enabled ? 'aktiviert' : 'deaktiviert'}`, 'success');
                    }
                } else {
                    throw new Error(data.error || 'Fehler beim Speichern');
                }
            } catch (error) {
                console.error('Fehler:', error);
                e.target.checked = !enabled;
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Aktualisieren der System-Benachrichtigungen', 'error');
                }
            }
        });
    }

    if (systemHideOwnToggle) {
        systemHideOwnToggle.addEventListener('change', async function(e) {
            const enabled = e.target.checked;

            try {
                const response = await fetch('<?php echo BASE_URL; ?>notifications/api/settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ hide_own_notifications: enabled })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast(`Eigene Benachrichtigungen ${enabled ? 'ausgeblendet' : 'wieder aktiviert'}`, 'success');
                    }
                } else {
                    throw new Error(data.error || 'Fehler beim Speichern');
                }
            } catch (error) {
                console.error('Fehler:', error);
                e.target.checked = !enabled;
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Aktualisieren der Einstellung', 'error');
                }
            }
        });
    }

    if (systemPushToggle) {
        systemPushToggle.addEventListener('change', async function(e) {
            const enabled = e.target.checked;

            try {
                const response = await fetch('<?php echo BASE_URL; ?>notifications/api/settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ push_notifications_enabled: enabled })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast(`Push-Benachrichtigungen ${enabled ? 'aktiviert' : 'deaktiviert'}`, 'success');
                    }
                } else {
                    throw new Error(data.error || 'Fehler beim Speichern');
                }
            } catch (error) {
                console.error('Fehler:', error);
                e.target.checked = !enabled;
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Aktualisieren der Push-Einstellung', 'error');
                }
            }
        });
    }

    if (desktopNotifEnabledToggle) {
        desktopNotifEnabledToggle.addEventListener('change', function() {
            const enabled = this.checked;
            if (enabled && typeof Notification !== 'undefined') {
                if (Notification.permission === 'default') {
                    Notification.requestPermission().then(function(permission) {
                        updateDesktopHintsUi();
                        if (permission !== 'granted') {
                            try { localStorage.setItem('svDesktopNotifications', '0'); } catch (e) {}
                            const t = document.getElementById('desktopNotifEnabledToggle');
                            if (t) t.checked = false;
                        } else {
                            try { localStorage.setItem('svDesktopNotifications', '1'); } catch (e) {}
                        }
                    });
                    return;
                }
                if (Notification.permission !== 'granted') {
                    this.checked = false;
                    try { localStorage.setItem('svDesktopNotifications', '0'); } catch (e) {}
                    updateDesktopHintsUi();
                    return;
                }
            }
            try {
                localStorage.setItem('svDesktopNotifications', enabled ? '1' : '0');
            } catch (e) {}
            updateDesktopHintsUi();
        });
    }

    // Easy Mode Toggle Handler
    const easyModeToggle = document.getElementById('easy-mode-toggle');
    if (easyModeToggle) {
        // Aktuellen Status beim Laden abrufen
        (async function() {
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/easy-mode.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.easy_mode !== undefined) {
                        easyModeToggle.checked = data.easy_mode;
                    }
                }
            } catch (error) {
                console.error('Fehler beim Laden der Easy Mode Einstellung:', error);
            }
        })();
        
        // Toggle-Änderung speichern
        easyModeToggle.addEventListener('change', async function(e) {
            const enabled = e.target.checked;
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/easy-mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ easy_mode: enabled })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast(`Einfache Oberfläche ${enabled ? 'aktiviert' : 'deaktiviert'}`, 'success');
                    }
                    // Nach kurzer Verzögerung zur entsprechenden Ansicht weiterleiten
                    setTimeout(() => {
                        if (enabled) {
                            window.location.href = '<?php echo BASE_URL; ?>easy/';
                        } else {
                            window.location.href = '<?php echo BASE_URL; ?>dashboard/';
                        }
                    }, 1000);
                } else {
                    throw new Error(data.error || 'Fehler beim Speichern');
                }
            } catch (error) {
                console.error('Fehler:', error);
                e.target.checked = !enabled; // Revert on error
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Aktualisieren der Einstellung', 'error');
                }
            }
        });
    }

    // Mobile Startseite Handler
    const mobilePageSelect = document.getElementById('mobile-start-page-select');
    const mobileStartEnabledToggle = document.getElementById('mobile-start-enabled-toggle');
    const mobileStartSettingsContainer = document.getElementById('mobile-start-settings-container');
    const mobileStartHeaderRow = document.getElementById('mobile-start-header-row');
    if (mobilePageSelect && mobileStartEnabledToggle && mobileStartSettingsContainer && mobileStartHeaderRow) {
        let mobileStartState = { mode: 'fixed', page: 'dashboard', enabled: true };
        const applyMobileStartVisibility = function(enabled) {
            mobileStartSettingsContainer.classList.toggle('hidden', !enabled);
            mobileStartHeaderRow.classList.toggle('mb-4', enabled);
            mobileStartHeaderRow.classList.toggle('mb-0', !enabled);
        };
        const applyMobileStartModeFromEnabled = function(enabled) {
            mobileStartState.mode = enabled ? 'fixed' : 'last';
            mobilePageSelect.disabled = !enabled;
        };
        const saveMobileStart = async function(nextState, previousState) {
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/mobile-start-page.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(nextState)
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Fehler beim Speichern');
                }
                mobileStartState = {
                    mode: data.mode || nextState.mode,
                    page: data.page || nextState.page,
                    enabled: typeof data.enabled === 'boolean' ? data.enabled : nextState.enabled
                };
                mobilePageSelect.value = mobileStartState.page;
                mobileStartEnabledToggle.checked = mobileStartState.enabled;
                applyMobileStartVisibility(mobileStartState.enabled);
                applyMobileStartModeFromEnabled(mobileStartState.enabled);
                if (typeof showToast === 'function') {
                    showToast('Mobile Startseite gespeichert', 'success');
                }
            } catch (error) {
                mobileStartState = previousState;
                mobilePageSelect.value = previousState.page;
                mobileStartEnabledToggle.checked = !!previousState.enabled;
                applyMobileStartVisibility(!!previousState.enabled);
                applyMobileStartModeFromEnabled(!!previousState.enabled);
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Speichern der mobilen Startseite', 'error');
                }
            }
        };

        (async function loadMobileStart() {
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/mobile-start-page.php', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                if (!response.ok) return;
                const data = await response.json();
                if (!data.success) return;
                mobileStartState.mode = data.mode || 'fixed';
                mobileStartState.page = data.page || 'dashboard';
                mobileStartState.enabled = typeof data.enabled === 'boolean' ? data.enabled : true;
                mobilePageSelect.value = mobileStartState.page;
                mobileStartEnabledToggle.checked = mobileStartState.enabled;
                applyMobileStartVisibility(mobileStartState.enabled);
                applyMobileStartModeFromEnabled(mobileStartState.enabled);
            } catch (error) {
                console.error('Fehler beim Laden der mobilen Startseite:', error);
            }
        })();

        mobilePageSelect.addEventListener('change', function() {
            const nextPage = this.value || 'dashboard';
            if (nextPage === mobileStartState.page) return;
            const previous = { mode: mobileStartState.mode, page: mobileStartState.page, enabled: mobileStartState.enabled };
            const next = { mode: mobileStartState.mode, page: nextPage, enabled: mobileStartState.enabled };
            mobileStartState = next;
            saveMobileStart(next, previous);
        });
        mobileStartEnabledToggle.addEventListener('change', function() {
            const nextEnabled = this.checked;
            const previous = { mode: mobileStartState.mode, page: mobileStartState.page, enabled: mobileStartState.enabled };
            const next = { mode: nextEnabled ? 'fixed' : 'last', page: mobileStartState.page, enabled: nextEnabled };
            mobileStartState = next;
            applyMobileStartVisibility(nextEnabled);
            applyMobileStartModeFromEnabled(nextEnabled);
            saveMobileStart(next, previous);
        });
    }

    var resetAllButtons = Array.from(document.querySelectorAll('[data-reset-all-settings-btn]'));
    if (resetAllButtons.length) {
        resetAllButtons.forEach(function(resetBtn) {
            resetBtn.addEventListener('click', async function() {
            var spinner = resetBtn.querySelector('[data-reset-all-spinner]');
            var label = resetBtn.querySelector('[data-reset-all-label]');
            var confirmed = window.confirm('Möchtest du wirklich alle Einstellungen auf Standard zurücksetzen?');
            if (!confirmed) return;

            resetAllButtons.forEach(function(btn) { btn.disabled = true; });
            if (spinner) spinner.classList.remove('hidden');
            if (label) label.classList.add('hidden');

            try {
                var response = await fetch('<?php echo BASE_URL; ?>settings/api/reset-all.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error((data && data.error) ? data.error : 'Zurücksetzen fehlgeschlagen');
                }

                try {
                    ['svDesktopNotifications', 'sounds_enabled', 'sidebarExpanded'].forEach(function(key) {
                        localStorage.removeItem(key);
                    });
                } catch (e) {}

                if (typeof showToast === 'function') {
                    showToast('Alle Einstellungen wurden auf Standard zurückgesetzt', 'success');
                }
                window.setTimeout(function() {
                    window.location.reload();
                }, 350);
            } catch (error) {
                resetAllButtons.forEach(function(btn) { btn.disabled = false; });
                if (spinner) spinner.classList.add('hidden');
                if (label) label.classList.remove('hidden');
                if (typeof showToast === 'function') {
                    showToast(error && error.message ? error.message : 'Zurücksetzen fehlgeschlagen', 'error');
                } else {
                    alert(error && error.message ? error.message : 'Zurücksetzen fehlgeschlagen');
                }
            }
        });
        });
    }
});

</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
