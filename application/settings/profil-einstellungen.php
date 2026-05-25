<?php
/**
 * Übersicht: alle zum Profil gehörenden Daten (Benutzerfelder + user_settings).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/user_profile_fields.php';
requireLogin();

user_profile_fields_ensure_columns($pdo);

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

/**
 * @param mixed $decoded
 * @param array<string, mixed> $out
 */
function profil_einstellungen_flatten(mixed $decoded, string $prefix, array &$out, callable $pathIsSensitive): void
{
    if (!is_array($decoded)) {
        $out[$prefix] = $decoded;
        return;
    }
    foreach ($decoded as $k => $v) {
        $segment = is_string($k) || is_int($k) ? (string) $k : '';
        $path = $prefix === '' ? $segment : $prefix . '.' . $segment;
        if ($pathIsSensitive($path)) {
            $out[$path] = '*** (verborgen)';
            continue;
        }
        if (is_array($v)) {
            profil_einstellungen_flatten($v, $path, $out, $pathIsSensitive);
        } else {
            $out[$path] = $v;
        }
    }
}

$pathIsSensitive = static function (string $path): bool {
    $lower = strtolower($path);
    if (str_contains($lower, 'password') || str_contains($lower, 'passwort')) {
        return true;
    }
    if (str_contains($lower, 'secret')) {
        return true;
    }
    if (str_contains($lower, 'token')) {
        return true;
    }
    if (str_contains($lower, 'api_key') || str_contains($lower, 'apikey')) {
        return true;
    }
    return false;
};

$userProfile = [];
$userSettingsRows = [];
$dbError = null;
$avatarPath = BASE_URL . 'assets/images/default-avatar.png';
$isPresetAvatar = false;
$presetColor = null;
$initials = 'U';
$fullName = 'Benutzer';
$companyName = '';
$companyLogoPath = BASE_URL . 'assets/images/default-avatar.png';

try {
    $profileExtraSql = user_profile_fields_select_extra_sql($pdo);
    $stmt = $pdo->prepare('
        SELECT u.id, u.email, u.vorname, u.nachname, u.telefonnummer, u.rolle, u.status, u.company_id, u.customer_id, u.logopfad,
               passwort_zuruecksetzen, onboarding_abgeschlossen, letztes_pw_change, letzte_anmeldung,
               fehlversuche, gesperrt, gesperrt_bis, erstellt_datum, geaendert_datum,
               calendar_token,
               c.name AS company_name,
               c.logo AS company_logo
               ' . $profileExtraSql . '
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    $userProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (array_key_exists('calendar_token', $userProfile)) {
        $userProfile['calendar_token'] = !empty($userProfile['calendar_token'])
            ? '*** (Kalender-Export-Token vorhanden, nicht angezeigt)'
            : null;
    }

    if (!empty($userProfile['vorname']) && !empty($userProfile['nachname'])) {
        $initials = strtoupper(substr((string) $userProfile['vorname'], 0, 1) . substr((string) $userProfile['nachname'], 0, 1));
    } elseif (!empty($userProfile['email'])) {
        $initials = strtoupper(substr((string) $userProfile['email'], 0, 1));
    }
    $fullName = trim((string) (($userProfile['vorname'] ?? '') . ' ' . ($userProfile['nachname'] ?? '')));
    if ($fullName === '') {
        $fullName = (string) ($userProfile['email'] ?? 'Benutzer');
    }
    $companyName = (string) ($userProfile['company_name'] ?? '');
    $companyLogoPath = getLogoUrl((string) ($userProfile['company_logo'] ?? ''));

    if (!empty($userProfile['logopfad'])) {
        $rawLogoPath = (string) $userProfile['logopfad'];
        if (str_starts_with($rawLogoPath, 'preset:')) {
            $isPresetAvatar = true;
            $parts = explode(':', $rawLogoPath);
            if (count($parts) >= 2 && $parts[1] !== '') {
                $presetColor = str_starts_with($parts[1], '#') ? $parts[1] : '#' . $parts[1];
            }
            $avatarPath = '';
        } elseif (!str_starts_with($rawLogoPath, 'http') && !str_starts_with($rawLogoPath, '/')) {
            $avatarPath = BASE_URL . $rawLogoPath;
        } else {
            $avatarPath = $rawLogoPath;
        }
    }

    $sStmt = $pdo->prepare('
        SELECT id, setting_key, setting_value, erstellt_datum, geaendert_datum
        FROM user_settings
        WHERE user_id = ?
        ORDER BY setting_key ASC, id ASC
    ');
    $sStmt->execute([$userId]);
    $userSettingsRows = $sStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('profil-einstellungen: ' . $e->getMessage());
    $dbError = 'Die Daten konnten nicht geladen werden.';
}

/** @return list<array{path: string, value: string}> */
function profil_einstellungen_expand_row(string $settingKey, ?string $rawValue, callable $pathIsSensitive): array
{
    if ($settingKey === '2fa_secret') {
        return [['path' => $settingKey, 'value' => '*** (Zwei-Faktor-Geheimnis, nicht angezeigt)']];
    }

    $trimmed = trim((string) $rawValue);
    if ($trimmed === '') {
        return [['path' => $settingKey, 'value' => '(leer)']];
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [['path' => $settingKey, 'value' => $trimmed]];
    }

    $flat = [];
    profil_einstellungen_flatten($decoded, $settingKey, $flat, $pathIsSensitive);
    ksort($flat, SORT_NATURAL);
    if ($flat === []) {
        return [['path' => $settingKey, 'value' => '(leeres JSON-Objekt oder -Array)']];
    }
    $lines = [];
    foreach ($flat as $path => $val) {
        if (is_bool($val)) {
            $lines[] = ['path' => $path, 'value' => $val ? 'true' : 'false'];
        } elseif ($val === null) {
            $lines[] = ['path' => $path, 'value' => 'null'];
        } elseif (is_scalar($val)) {
            $lines[] = ['path' => $path, 'value' => (string) $val];
        } else {
            $lines[] = ['path' => $path, 'value' => json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }
    }
    return $lines;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 lg:h-full app-mobile-no-root-overscroll">
  <main>
    <div class="px-4">
      <div class="mx-4 mt-4 mb-4">
        <nav class="mb-4 flex" aria-label="Breadcrumb">
          <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
            <li class="inline-flex items-center">
              <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
                <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5"/>
                </svg>
                Startseite
              </a>
            </li>
            <li>
              <div class="flex items-center">
                <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                </svg>
                <a href="<?php echo BASE_URL; ?>account/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Mein Konto (Profil bearbeiten)</a>
              </div>
            </li>
            <li>
              <div class="flex items-center">
                <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                </svg>
                <a href="<?php echo BASE_URL; ?>settings/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Einstellungen</a>
              </div>
            </li>
            <li aria-current="page">
              <div class="flex items-center">
                <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                </svg>
                <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Profil &amp; alle Einstellungen</span>
              </div>
            </li>
          </ol>
        </nav>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Profil &amp; alle Einstellungen</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Vollständige Übersicht deiner Kontodaten und jeder in der Datenbank gespeicherten Benutzereinstellung (Schlüssel und Wert).</p>
          </div>
          <a href="<?php echo BASE_URL; ?>account/" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800 dark:bg-primary-600">
            Profil bearbeiten
          </a>
          <a href="<?php echo BASE_URL; ?>settings/" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
            Einstellungen
          </a>
        </div>
      </div>

      <?php if ($dbError): ?>
        <div class="mx-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
          <?php echo htmlspecialchars($dbError); ?>
        </div>
      <?php else: ?>

      <div class="mx-4 mb-8 lg:grid lg:grid-cols-12 lg:gap-4 lg:items-start">
        <aside class="hidden h-full w-80 shrink-0 overflow-y-auto border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-3 lg:block lg:rounded-lg">
          <div class="mb-3 flex w-full items-center justify-between rounded-lg bg-white p-2 dark:bg-gray-800">
            <div class="flex w-full items-center justify-between">
              <div class="flex items-center">
                <img src="<?php echo htmlspecialchars($companyLogoPath); ?>" class="mr-3 h-8 w-8 rounded-md" alt="Company logo" />
                <div class="text-left">
                  <div class="mb-0.5 font-semibold leading-none text-gray-900 dark:text-white"><?php echo htmlspecialchars($fullName); ?></div>
                  <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($companyName); ?></div>
                </div>
              </div>
            </div>
          </div>

          <ul class="space-y-2">
            <li>
              <a href="<?php echo BASE_URL; ?>settings/profil-einstellungen.php" class="group flex items-center rounded-lg bg-primary-100 p-2 text-base font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-200">
                <svg class="h-6 w-6 text-primary-700 dark:text-primary-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z" />
                </svg>
                <span class="ml-3">Persönliche Daten</span>
              </a>
            </li>
            <li>
              <a href="<?php echo BASE_URL; ?>settings/resetpasswort.php" class="group flex items-center rounded-lg p-2 text-base font-medium text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                <svg class="h-6 w-6 text-gray-400 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h6l2 4m-8-4v8m0-8V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v9h2m8 0H9m4 0h2m4 0h2v-4m0 0h-5m3.5 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm-10 0a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z" />
                </svg>
                <span class="ml-3">Passwort ändern</span>
              </a>
            </li>
            <li>
              <a href="<?php echo BASE_URL; ?>knowledge/" class="group flex items-center rounded-lg p-2 text-base font-medium text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                <svg class="h-6 w-6 text-gray-400 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-width="2" d="M11.083 5.104c.35-.8 1.485-.8 1.834 0l1.752 4.022a1 1 0 0 0 .84.597l4.463.342c.9.069 1.255 1.2.556 1.771l-3.33 2.723a1 1 0 0 0-.337 1.016l1.03 4.119c.214.858-.71 1.552-1.474 1.106l-3.913-2.281a1 1 0 0 0-1.008 0L7.583 20.8c-.764.446-1.688-.248-1.474-1.106l1.03-4.119A1 1 0 0 0 6.8 14.56l-3.33-2.723c-.698-.571-.342-1.702.557-1.771l4.462-.342a1 1 0 0 0 .84-.597l1.753-4.022Z" />
                </svg>
                <span class="ml-3 flex-1 whitespace-nowrap">Wissensdatenbank</span>
              </a>
            </li>
            <li>
              <a href="<?php echo BASE_URL; ?>onboarding/" class="group flex items-center rounded-lg p-2 text-base font-medium text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                <svg class="h-6 w-6 text-gray-400 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                </svg>
                <span class="ml-3 flex-1 whitespace-nowrap">Einführung</span>
              </a>
            </li>
            <li>
              <a href="<?php echo BASE_URL; ?>notifications/" class="group flex items-center rounded-lg p-2 text-base font-medium text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                <svg class="h-6 w-6 text-gray-400 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z" />
                  <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span class="ml-3 flex-1 whitespace-nowrap">Benachrichtigungen</span>
              </a>
            </li>
          </ul>
          <ul class="mt-5 space-y-2 border-t border-gray-100 pt-5 dark:border-gray-700">
            <li>
              <a href="<?php echo BASE_URL; ?>settings/" class="group flex items-center rounded-lg p-2 text-base font-medium text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                <svg class="h-6 w-6 text-gray-400 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z" />
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                </svg>
                <span class="ml-3 flex-1 whitespace-nowrap">Einstellungen</span>
              </a>
            </li>
            <li>
              <a href="<?php echo BASE_URL; ?>logout.php" class="group flex items-center rounded-lg p-2 text-base font-medium text-red-600 hover:bg-red-100 dark:text-red-500 dark:hover:bg-gray-700">
                <svg class="h-6 w-6 flex-shrink-0 text-red-600 transition duration-75 dark:text-red-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2" />
                </svg>
                <span class="ml-3 flex-1 whitespace-nowrap">Abmelden</span>
              </a>
            </li>
          </ul>
        </aside>

        <div class="space-y-6 lg:col-span-9">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Kontodaten</h2>
          <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Felder aus der Benutzertabelle (ohne Passwort-Hash).</p>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
              <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-400">
                <tr>
                  <th class="px-3 py-2 font-medium">Feld</th>
                  <th class="px-3 py-2 font-medium">Wert</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                <?php
                $profileLabels = user_profile_fields_labels();
                $profileLabels = array_merge([
                    'email' => 'E-Mail',
                    'vorname' => 'Vorname',
                    'nachname' => 'Nachname',
                    'telefonnummer' => 'Telefon (Festnetz)',
                ], $profileLabels);
                foreach ($userProfile as $col => $val):
                    if ($col === 'passwort') {
                        continue;
                    }
                    $displayVal = $val === null || $val === '' ? '(leer)' : user_profile_fields_format_display($col, $val);
                    if ($col === 'stellvertreter_user_id' && $val) {
                        $svStmt = $pdo->prepare('SELECT vorname, nachname, email FROM users WHERE id = ? LIMIT 1');
                        $svStmt->execute([(int) $val]);
                        $sv = $svStmt->fetch(PDO::FETCH_ASSOC);
                        if ($sv) {
                            $svName = trim(($sv['vorname'] ?? '') . ' ' . ($sv['nachname'] ?? ''));
                            $displayVal = ($svName !== '' ? $svName : $sv['email']) . ' (ID ' . (int) $val . ')';
                        }
                    }
                ?>
                  <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                    <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                      <span class="font-medium text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($profileLabels[$col] ?? $col); ?></span>
                      <span class="mt-0.5 block font-mono text-[10px] text-gray-400"><?php echo htmlspecialchars($col); ?></span>
                    </td>
                    <td class="px-3 py-2 break-all text-xs"><?php echo htmlspecialchars($displayVal); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Benutzereinstellungen (<code class="text-sm">user_settings</code>)</h2>
          <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Jeder Datensatz mit technischem Schlüssel; JSON-Werte werden in einzelne Zeilen aufgelöst. Sensible Schlüssel (z.&nbsp;B. Geheimnisse, Token, Passwörter in JSON) werden nicht im Klartext angezeigt.
          </p>

          <?php if (count($userSettingsRows) === 0): ?>
            <p class="text-sm text-gray-600 dark:text-gray-400">Es sind noch keine Einträge in <span class="font-mono">user_settings</span> für dein Konto gespeichert.</p>
          <?php else: ?>
            <div class="space-y-8">
              <?php foreach ($userSettingsRows as $row): ?>
                <?php
                $key = $row['setting_key'] ?? '';
                $expanded = profil_einstellungen_expand_row($key, $row['setting_value'] ?? null, $pathIsSensitive);
                ?>
                <section class="rounded-lg border border-gray-100 dark:border-gray-600/80 overflow-hidden">
                  <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-700/40">
                    <h3 class="font-mono text-sm font-semibold text-primary-700 dark:text-primary-300"><?php echo htmlspecialchars($key); ?></h3>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      <?php if (!empty($row['erstellt_datum'])): ?>
                        <span>Erstellt: <?php echo htmlspecialchars($row['erstellt_datum']); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($row['geaendert_datum'])): ?>
                        <span class="ms-2">Geändert: <?php echo htmlspecialchars($row['geaendert_datum']); ?></span>
                      <?php endif; ?>
                      <span class="ms-2">ID <?php echo (int) ($row['id'] ?? 0); ?></span>
                    </div>
                  </div>
                  <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                      <thead class="border-b border-gray-100 text-xs uppercase text-gray-500 dark:border-gray-600 dark:text-gray-400">
                        <tr>
                          <th class="px-4 py-2 font-medium">Pfad / Schlüssel</th>
                          <th class="px-4 py-2 font-medium">Wert</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100 dark:divide-gray-600">
                        <?php foreach ($expanded as $line): ?>
                          <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20">
                            <td class="px-4 py-2 align-top font-mono text-xs text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($line['path']); ?></td>
                            <td class="px-4 py-2 align-top break-all font-mono text-xs text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($line['value']); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </section>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
