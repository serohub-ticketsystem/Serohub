<?php
/**
 * Kalender-Export & CalDAV-Synchronisation
 * Nur für Techniker und Admin zugänglich.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Nur Techniker und Admin haben Zugriff
$userId = $_SESSION['user_id'] ?? null;
$userRole = '';
$userData = [];
try {
    $stmt = $pdo->prepare("SELECT rolle, vorname, nachname, email, logopfad, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $row['rolle'] ?? '';
    $userData = is_array($row) ? $row : [];
} catch (PDOException $e) {
    $userRole = '';
    $userData = [];
}

if (!in_array($userRole, ['Admin', 'Techniker'], true)) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'settings/');
    exit;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$displayName = trim((string)($userData['vorname'] ?? '') . ' ' . (string)($userData['nachname'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($userData['email'] ?? 'Benutzer');
}
$initials = 'U';
if (!empty($userData['vorname']) || !empty($userData['nachname'])) {
    $initials = strtoupper(substr((string)($userData['vorname'] ?? ''), 0, 1) . substr((string)($userData['nachname'] ?? ''), 0, 1));
} elseif (!empty($userData['email'])) {
    $initials = strtoupper(substr((string)$userData['email'], 0, 1));
}
$avatarPath = !empty($userData['logopfad']) ? (string)$userData['logopfad'] : '';
$isPresetAvatar = false;
$presetColor = null;
if ($avatarPath !== '') {
    if (str_starts_with($avatarPath, 'preset:')) {
        $isPresetAvatar = true;
        $parts = explode(':', $avatarPath);
        if (isset($parts[1]) && $parts[1] !== '') {
            $presetColor = str_starts_with($parts[1], '#') ? $parts[1] : '#' . $parts[1];
        }
    } elseif (!str_starts_with($avatarPath, 'http') && !str_starts_with($avatarPath, '/')) {
        $avatarPath = BASE_URL . ltrim($avatarPath, '/');
    }
}
$companyLink = BASE_URL . 'account/my-company.php';

$apiBase = rtrim(BASE_URL, '/') . '/kalender/api';

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 lg:h-screen lg:overflow-hidden app-mobile-no-root-overscroll">
  <main class="mx-4 mt-2 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 lg:h-full">
    <div>
      <div class="mb-4">
        <nav class="mb-4 hidden flex-shrink-0 lg:flex" aria-label="Breadcrumb">
          <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
            <li class="inline-flex items-center">
              <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                  <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                </svg>
                Startseite
              </a>
            </li>
            <li aria-current="page">
              <div class="flex items-center">
                <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                </svg>
                <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Einstellungen</span>
              </div>
            </li>
          </ol>
        </nav>
      </div>

      <div class="lg:grid lg:grid-cols-12 lg:gap-4 lg:items-start lg:h-full lg:min-h-0">
        <aside class="lg:col-span-3 lg:self-start">
          <div class="hidden rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 mb-4 lg:mb-0 lg:block lg:self-start">
            <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
              <div class="flex items-center gap-3">
                <a href="<?php echo BASE_URL; ?>account/index.php" class="shrink-0 rounded-full focus:outline-none">
                  <?php if ($isPresetAvatar && $presetColor): ?>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 text-sm font-semibold text-white dark:border-gray-600" style="background-color: <?php echo htmlspecialchars($presetColor, ENT_QUOTES, 'UTF-8'); ?>;">
                      <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                  <?php elseif ($avatarPath !== ''): ?>
                    <img src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profilbild" class="h-12 w-12 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                  <?php else: ?>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-primary-600 text-sm font-semibold text-white dark:border-gray-600">
                      <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                  <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>account/index.php" class="min-w-0 block w-full rounded-md p-1 -m-1 focus:outline-none">
                  <p class="truncate text-base font-semibold text-gray-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-300"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></p>
                  <p class="truncate text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($userRole ?: 'Benutzer', ENT_QUOTES, 'UTF-8'); ?></p>
                </a>
              </div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-1.5 border-b border-gray-200 pb-4 dark:border-gray-700">
              <a href="<?php echo BASE_URL; ?>account/index.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                <svg class="me-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z"/></svg>
                Persönliche Daten
              </a>
              <a href="<?php echo htmlspecialchars($companyLink, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/>
                </svg>
                Meine Firma
              </a>
              <a href="<?php echo BASE_URL; ?>settings/index.php#benachrichtigungen" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
                </svg>
                Benachrichtigungen
              </a>
            </div>

            <ul class="-mb-px m-0 list-none grid grid-cols-1 gap-1.5 p-0 text-base font-semibold">
              <li><a href="<?php echo BASE_URL; ?>settings/index.php#praeferenzen" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 px-4 py-3 text-left" style="background-color:#ede9fe;color:#5b21b6;border-left-color:#7c3aed;"><svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M20 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6h-2m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4"/></svg>Präferenzen</a></li>
              <li><a href="<?php echo BASE_URL; ?>settings/index.php#benachrichtigungen" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>Benachrichtigungen</a></li>
              <li><a href="<?php echo BASE_URL; ?>settings/index.php#sicherheit" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>Sicherheit</a></li>
              <li><button type="button" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 9H8a5 5 0 0 0 0 10h9m4-10-4-4m4 4-4 4"/></svg>Einstellungen zurücksetzen</button></li>
            </ul>
            <ul class="mt-4 m-0 list-none border-t border-gray-200 pt-4 p-0 dark:border-gray-700">
              <li>
                <a href="<?php echo BASE_URL; ?>logout.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left font-semibold text-red-700 transition-colors hover:bg-red-50 hover:text-red-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300">
                  <svg class="me-2 h-5 w-5 text-red-700 dark:text-red-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2"/>
                  </svg>
                  Abmelden
                </a>
              </li>
            </ul>
          </div>
        </aside>

        <div class="mx-4 lg:mx-0 lg:col-span-9 space-y-8 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto lg:pe-1 lg:pb-28">
        <!-- 1. ICS / Webcal-Abo (zusammengehörig) -->
        <div class="rounded-xl border-2 border-amber-200 dark:border-amber-800 bg-gradient-to-br from-amber-50/50 to-white dark:from-amber-950/20 dark:to-gray-800 overflow-hidden">
          <div class="px-6 py-4 border-b border-amber-200/60 dark:border-amber-800/60 bg-amber-50/80 dark:bg-amber-900/20">
            <div class="flex items-center gap-3">
              <span class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
              </span>
              <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">ICS / Webcal-Abo</h2>
                <p class="text-sm text-amber-800/80 dark:text-amber-200/80">Link für Outlook, Google Calendar oder Apple Calendar</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <section class="rounded-lg border border-amber-200/60 dark:border-amber-800/60 bg-white dark:bg-gray-800/50 p-5">
              <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Export-Link</h3>
              <div id="export-link-container">
                <div id="export-link-empty" class="text-center py-5">
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Noch kein Link erstellt</p>
                  <button type="button" id="generate-export-link" class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 transition-colors">Link erstellen</button>
                </div>
                <div id="export-link-active" class="hidden space-y-3">
                  <div class="flex flex-wrap gap-2 mb-2">
                    <label for="export-link-https" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-gray-200 dark:border-gray-600 cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-900/20 hover:border-amber-300 dark:hover:border-amber-700 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50 dark:has-[:checked]:bg-amber-900/30 dark:has-[:checked]:border-amber-500 transition-colors">
                      <input type="radio" id="export-link-https" name="export-link-type" value="https" class="rounded border-gray-400 text-amber-600 focus:ring-amber-500 focus:ring-2" checked>
                      <span class="text-sm font-medium">https</span>
                    </label>
                    <label for="export-link-webcal" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-gray-200 dark:border-gray-600 cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-900/20 hover:border-amber-300 dark:hover:border-amber-700 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50 dark:has-[:checked]:bg-amber-900/30 dark:has-[:checked]:border-amber-500 transition-colors">
                      <input type="radio" id="export-link-webcal" name="export-link-type" value="webcal" class="rounded border-gray-400 text-amber-600 focus:ring-amber-500 focus:ring-2">
                      <span class="text-sm font-medium">webcal (Auto-Sync für Outlook/Apple/Google)</span>
                    </label>
                  </div>
                  <div class="flex flex-wrap gap-2 items-center">
                    <input type="text" id="export-link-url" readonly class="flex-1 min-w-0 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono">
                    <button type="button" id="copy-export-link" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shrink-0" title="Kopieren">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                    <button type="button" id="open-webcal-link" class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 transition-colors shrink-0" title="Kalender-App öffnen">Abonnieren</button>
                  </div>
                  <div class="flex justify-between text-sm">
                    <button type="button" id="regenerate-export-link" class="text-gray-500 hover:text-amber-600 dark:hover:text-amber-400">Neuen Link erstellen</button>
                    <button type="button" id="delete-export-link" class="text-red-500 hover:text-red-700 dark:hover:text-red-400">Link deaktivieren</button>
                  </div>
                </div>
              </div>
            </section>
            <section class="rounded-lg border border-amber-200/60 dark:border-amber-800/60 bg-white dark:bg-gray-800/50 p-5">
              <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Export-Inhalte für ICS / Webcal</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Welche Termine sollen über den Link sichtbar sein?</p>
              <div id="export-sources-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-amber-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="export-src-my_calendar" class="export-src-ics rounded border-gray-400 text-amber-600 focus:ring-amber-500" data-key="my_calendar">
                  <span class="text-sm">Eigener Kalender</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-amber-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="export-src-vacation" class="export-src-ics rounded border-gray-400 text-amber-600 focus:ring-amber-500" data-key="vacation">
                  <span class="text-sm">Eigener Urlaub</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-amber-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="export-src-invitations" class="export-src-ics rounded border-gray-400 text-amber-600 focus:ring-amber-500" data-key="invitations">
                  <span class="text-sm">Termin-Einladungen</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-amber-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="export-src-service_tickets" class="export-src-ics rounded border-gray-400 text-amber-600 focus:ring-amber-500" data-key="service_tickets">
                  <span class="text-sm">Tickets</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-amber-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="export-src-todos" class="export-src-ics rounded border-gray-400 text-amber-600 focus:ring-amber-500" data-key="todos">
                  <span class="text-sm">Aufgaben (Todos)</span>
                </label>
              </div>
              <button type="button" id="save-ics-sources-btn" class="mt-4 px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 transition-colors">ICS-Inhalte speichern</button>
            </section>
          </div>
        </div>

        <!-- 2. CalDAV-Synchronisation (zusammengehörig) -->
        <div class="rounded-xl border-2 border-emerald-200 dark:border-emerald-800 bg-gradient-to-br from-emerald-50/50 to-white dark:from-emerald-950/20 dark:to-gray-800 overflow-hidden">
          <div class="px-6 py-4 border-b border-emerald-200/60 dark:border-emerald-800/60 bg-emerald-50/80 dark:bg-emerald-900/20">
            <div class="flex items-center gap-3">
              <span class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              </span>
              <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">CalDAV-Synchronisation</h2>
                <p class="text-sm text-emerald-800/80 dark:text-emerald-200/80">Termine zu Nextcloud oder anderem CalDAV-Server übertragen</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <section class="rounded-lg border border-emerald-200/60 dark:border-emerald-800/60 bg-white dark:bg-gray-800/50 p-5">
              <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Standard Export-Inhalte für CalDAV</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Voreinstellung für neue Syncs. Pro Sync kann individuell überschrieben werden.</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-4">
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-emerald-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="caldav-default-my_calendar" class="export-src-caldav rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="my_calendar">
                  <span class="text-sm">Eigener Kalender</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-emerald-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="caldav-default-vacation" class="export-src-caldav rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="vacation">
                  <span class="text-sm">Eigener Urlaub</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-emerald-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="caldav-default-invitations" class="export-src-caldav rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="invitations">
                  <span class="text-sm">Termin-Einladungen</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-emerald-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="caldav-default-service_tickets" class="export-src-caldav rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="service_tickets">
                  <span class="text-sm">Tickets</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-emerald-50/50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                  <input type="checkbox" id="caldav-default-todos" class="export-src-caldav rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="todos">
                  <span class="text-sm">Aufgaben (Todos)</span>
                </label>
              </div>
              <button type="button" id="save-caldav-default-btn" class="px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600 transition-colors">Standard speichern</button>
            </section>
            <section class="rounded-lg border border-emerald-200/60 dark:border-emerald-800/60 bg-white dark:bg-gray-800/50 p-5">
              <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sync-Verbindungen</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Mehrere Syncs möglich. Bei jedem Server Ziel-Kalender und Export-Inhalte wählen.</p>
          
          <div id="caldav-servers-hint" class="mb-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 hidden">
            <p class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-1">CalDAV-Server</p>
            <div id="caldav-servers-list" class="text-xs space-y-1"></div>
          </div>
          
          <div id="caldav-sync-list" class="space-y-2 mb-4"></div>
          
          <div id="caldav-sync-form" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700/30 p-4 space-y-4">
            <input type="hidden" id="caldav-sync-id" value="">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bezeichnung (optional)</label>
                <input type="text" id="caldav-sync-name" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="z.B. Nextcloud Arbeit">
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CalDAV-Server</label>
                <select id="caldav-sync-server" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500">
                  <option value="">– Bitte wählen –</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Benutzername</label>
                <input type="text" id="caldav-sync-username" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Nextcloud-Login">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Passwort</label>
                <input type="password" id="caldav-sync-password" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="••••••••">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Leer = nicht ändern (Bearbeiten) bzw. von bestehendem Sync übernehmen (neuer Sync für gleichen Server)</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kalendername</label>
                <input type="text" id="caldav-sync-calendar" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500" placeholder="Personal" value="Personal">
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Export-Inhalte für diesen Sync</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Welche Termine sollen in diesen CalDAV-Kalender übertragen werden?</p>
                <div class="flex flex-wrap gap-3">
                  <label class="flex items-center gap-2 p-2 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50">
                    <input type="checkbox" id="sync-src-my_calendar" class="sync-src rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="my_calendar">
                    <span class="text-sm">Eigener Kalender</span>
                  </label>
                  <label class="flex items-center gap-2 p-2 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50">
                    <input type="checkbox" id="sync-src-vacation" class="sync-src rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="vacation">
                    <span class="text-sm">Urlaub</span>
                  </label>
                  <label class="flex items-center gap-2 p-2 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50">
                    <input type="checkbox" id="sync-src-invitations" class="sync-src rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="invitations">
                    <span class="text-sm">Einladungen</span>
                  </label>
                  <label class="flex items-center gap-2 p-2 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50">
                    <input type="checkbox" id="sync-src-service_tickets" class="sync-src rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="service_tickets">
                    <span class="text-sm">Tickets</span>
                  </label>
                  <label class="flex items-center gap-2 p-2 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50">
                    <input type="checkbox" id="sync-src-todos" class="sync-src rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" data-key="todos">
                    <span class="text-sm">Todos</span>
                  </label>
                </div>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" id="caldav-sync-save-btn" class="px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600 transition-colors">Speichern</button>
              <button type="button" id="caldav-sync-cancel-btn" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors hidden">Abbrechen</button>
              <button type="button" id="caldav-test-btn" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">Verbindung testen</button>
              <span id="caldav-test-result" class="text-sm hidden"></span>
            </div>
          </div>
          <div class="flex items-center gap-2 mt-4">
            <button type="button" id="caldav-add-btn" class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline font-medium">+ Neuen Sync hinzufügen</button>
            <button type="button" id="caldav-push-btn" class="ml-auto px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600 transition-colors">Alle synchronisieren</button>
          </div>
          <div id="caldav-push-result" class="mt-2 text-sm hidden"></div>
            </section>
          </div>
        </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
(function() {
  var apiBase = '<?php echo $apiBase; ?>';
  var baseUrl = '<?php echo rtrim(BASE_URL, '/'); ?>' || '/';
  
  function getExportUrl(token, useWebcal) {
    var url = (window.location.origin + (baseUrl !== '/' ? baseUrl : '') + '/kalender/api/export-ics.php?token=' + token).replace(/([^:]\/)\/+/g, '$1');
    if (useWebcal) url = 'webcal://' + url.replace(/^https?:\/\//, '');
    return url;
  }
  
  var currentExportToken = null;
  
  function updateExportLinkDisplay() {
    if (!currentExportToken) return;
    var useWebcal = document.querySelector('input[name="export-link-type"]:checked')?.value === 'webcal';
    document.getElementById('export-link-url').value = getExportUrl(currentExportToken, useWebcal);
  }
  
  function loadExportLink() {
    fetch(apiBase + '/calendar-token.php').then(function(r) { return r.json(); }).then(function(data) {
      if (data.success && data.token) {
        currentExportToken = data.token;
        document.getElementById('export-link-empty').classList.add('hidden');
        document.getElementById('export-link-active').classList.remove('hidden');
        updateExportLinkDisplay();
      } else {
        currentExportToken = null;
        document.getElementById('export-link-empty').classList.remove('hidden');
        document.getElementById('export-link-active').classList.add('hidden');
      }
    }).catch(function() {
      document.getElementById('export-link-empty').classList.remove('hidden');
      document.getElementById('export-link-active').classList.add('hidden');
    });
  }
  
  document.getElementById('generate-export-link').addEventListener('click', function() {
    var btn = document.getElementById('generate-export-link');
    btn.disabled = true;
    fetch(apiBase + '/calendar-token.php', { method: 'POST' }).then(function(r) { return r.json(); }).then(function(data) {
      if (data.success && data.token) {
        currentExportToken = data.token;
        document.getElementById('export-link-empty').classList.add('hidden');
        document.getElementById('export-link-active').classList.remove('hidden');
        updateExportLinkDisplay();
      }
    }).catch(function() {}).finally(function() { btn.disabled = false; });
  });
  document.getElementById('regenerate-export-link').addEventListener('click', function() {
    if (!confirm('Bisheriger Link wird ungültig. Fortfahren?')) return;
    fetch(apiBase + '/calendar-token.php', { method: 'POST' }).then(function(r) { return r.json(); }).then(function(data) {
      if (data.success && data.token) loadExportLink();
    });
  });
  document.getElementById('delete-export-link').addEventListener('click', function() {
    if (!confirm('Export-Link wirklich deaktivieren?')) return;
    fetch(apiBase + '/calendar-token.php', { method: 'DELETE' }).then(function(r) { return r.json(); }).then(function(data) {
      if (data.success) {
        currentExportToken = null;
        document.getElementById('export-link-empty').classList.remove('hidden');
        document.getElementById('export-link-active').classList.add('hidden');
      }
    });
  });
  document.getElementById('copy-export-link').addEventListener('click', function() {
    var urlInput = document.getElementById('export-link-url');
    urlInput.select();
    navigator.clipboard && navigator.clipboard.writeText(urlInput.value).catch(function() {});
    if (typeof showToast === 'function') showToast('Link kopiert', 'success');
  });
  document.querySelectorAll('.export-link-type').forEach(function(r) {
    r.addEventListener('change', updateExportLinkDisplay);
    r.addEventListener('click', function() { setTimeout(updateExportLinkDisplay, 10); });
  });
  document.getElementById('open-webcal-link').addEventListener('click', function() {
    if (!currentExportToken) return;
    var url = getExportUrl(currentExportToken, true);
    if (url && url.indexOf('webcal://') === 0) {
      window.location.href = url;
    }
  });
  
  var caldavDefaultSources = { my_calendar: true, vacation: true, invitations: true, service_tickets: true, todos: true };
  
  function loadExportSources() {
    fetch(apiBase + '/calendar-export-sources.php').then(function(r) { return r.json(); }).then(function(data) {
      if (data.success) {
        var ics = data.ics_sources || data.sources || {};
        var caldav = data.caldav_sources || ics;
        caldavDefaultSources = caldav;
        ['my_calendar', 'vacation', 'invitations', 'service_tickets', 'todos'].forEach(function(key) {
          var cb = document.getElementById('export-src-' + key);
          if (cb) cb.checked = !!ics[key];
          var cbCaldav = document.getElementById('caldav-default-' + key);
          if (cbCaldav) cbCaldav.checked = !!caldav[key];
        });
      }
    }).catch(function() {});
  }
  
  document.getElementById('save-ics-sources-btn').addEventListener('click', function() {
    var sources = {};
    document.querySelectorAll('.export-src-ics').forEach(function(cb) {
      sources[cb.getAttribute('data-key')] = cb.checked;
    });
    fetch(apiBase + '/calendar-export-sources.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ ics_sources: sources })
    }).then(function(r) { return r.json(); }).then(function(data) {
      if (data.success && typeof showToast === 'function') showToast('ICS-Inhalte gespeichert', 'success');
    });
  });
  
  document.getElementById('save-caldav-default-btn').addEventListener('click', function() {
    var sources = {};
    document.querySelectorAll('.export-src-caldav').forEach(function(cb) {
      sources[cb.getAttribute('data-key')] = cb.checked;
    });
    fetch(apiBase + '/calendar-export-sources.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ caldav_sources: sources })
    }).then(function(r) { return r.json(); }).then(function(data) {
      if (data.success && typeof showToast === 'function') showToast('CalDAV-Standard gespeichert', 'success');
      caldavDefaultSources = data.caldav_sources || sources;
    });
  });
  
  function escapeHtml(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  
  function loadCaldavServers() {
    fetch(apiBase + '/caldav-servers-list.php').then(function(r) { return r.json(); }).then(function(data) {
      var container = document.getElementById('caldav-servers-list');
      var hint = document.getElementById('caldav-servers-hint');
      if (!container || !hint) return;
      if (data.success && data.servers && data.servers.length > 0) {
        hint.classList.remove('hidden');
        container.innerHTML = data.servers.map(function(s) {
          return '<div class="flex items-start gap-2"><span class="font-medium text-blue-700 dark:text-blue-300">' + escapeHtml(s.name || '') + '</span>' + (s.beschreibung ? '<span class="text-blue-600 dark:text-blue-400">– ' + escapeHtml(s.beschreibung) + '</span>' : '') + '</div>';
        }).join('');
      } else hint.classList.add('hidden');
    }).catch(function() { document.getElementById('caldav-servers-hint').classList.add('hidden'); });
  }
  
  var serverCredentials = {};
  
  function loadCaldavSyncConfig() {
    fetch(apiBase + '/caldav-sync-settings.php').then(function(r) { return r.json(); }).then(function(data) {
      var serverSelect = document.getElementById('caldav-sync-server');
      var listEl = document.getElementById('caldav-sync-list');
      if (!serverSelect || !listEl) return;
      serverCredentials = data.serverCredentials || {};
      serverSelect.innerHTML = '<option value="">– Bitte wählen –</option>';
      if (data.servers && data.servers.length > 0) {
        data.servers.forEach(function(s) {
          var opt = document.createElement('option');
          opt.value = s.id;
          opt.textContent = s.name;
          serverSelect.appendChild(opt);
        });
      }
      var configs = data.configs || [];
      listEl.innerHTML = '';
      configs.forEach(function(c) {
        var label = c.sync_name || (c.server_name + ' / ' + (c.caldav_username || '') + ' / ' + (c.calendar_name || 'Personal'));
        var statusText = c.last_sync ? (c.last_sync + ' – ' + (c.last_sync_message || '')) : 'Noch nie synchronisiert';
        var card = document.createElement('div');
        card.className = 'flex items-center justify-between gap-2 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600';
        card.innerHTML = '<div class="min-w-0 flex-1"><div class="flex items-center gap-2 flex-wrap"><span class="font-medium text-gray-900 dark:text-white truncate">' + escapeHtml(label) + '</span><label class="relative inline-flex items-center cursor-pointer shrink-0"><input type="checkbox" class="caldav-toggle sr-only peer" data-id="' + c.id + '" ' + (c.is_active == 1 ? 'checked' : '') + '><div class="w-9 h-5 bg-gray-200 rounded-full peer dark:bg-gray-600 peer-checked:bg-emerald-600 peer-checked:after:translate-x-full after:content-[\'\'] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all"></div></label></div><p class="text-xs text-gray-500 dark:text-gray-400 mt-1">' + escapeHtml(statusText) + '</p></div><div class="flex items-center gap-1 shrink-0"><button type="button" class="caldav-edit px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-500 hover:bg-gray-100 dark:hover:bg-gray-600" data-id="' + c.id + '" title="Bearbeiten">Bearbeiten</button><button type="button" class="caldav-sync-one px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700" data-id="' + c.id + '" title="Jetzt syncen">Sync</button><button type="button" class="caldav-delete px-2 py-1 text-xs rounded border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20" data-id="' + c.id + '" title="Löschen">Löschen</button></div>';
        listEl.appendChild(card);
      });
      
      listEl.querySelectorAll('.caldav-toggle').forEach(function(t) {
        t.addEventListener('change', function() {
          fetch(apiBase + '/caldav-sync-settings.php', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ id: t.getAttribute('data-id'), is_active: t.checked }) }).then(function() { loadCaldavSyncConfig(); }).catch(function() {});
        });
      });
      listEl.querySelectorAll('.caldav-edit').forEach(function(b) {
        b.addEventListener('click', function() {
          var id = b.getAttribute('data-id');
          var c = configs.find(function(x) { return String(x.id) === id; });
          if (!c) return;
          document.getElementById('caldav-sync-id').value = c.id;
          document.getElementById('caldav-sync-name').value = c.sync_name || '';
          document.getElementById('caldav-sync-server').value = c.caldav_server_id;
          document.getElementById('caldav-sync-username').value = c.caldav_username || '';
          document.getElementById('caldav-sync-password').value = '';
          document.getElementById('caldav-sync-calendar').value = c.calendar_name || 'Personal';
          var sources = { my_calendar: true, vacation: true, invitations: true, service_tickets: true, todos: true };
          if (c.export_sources) { try { var d = JSON.parse(c.export_sources); if (d) sources = d; } catch(e) {} }
          ['my_calendar', 'vacation', 'invitations', 'service_tickets', 'todos'].forEach(function(k) {
            var cb = document.getElementById('sync-src-' + k);
            if (cb) cb.checked = !!sources[k];
          });
          document.getElementById('caldav-sync-cancel-btn').classList.remove('hidden');
        });
      });
      listEl.querySelectorAll('.caldav-sync-one').forEach(function(b) {
        b.addEventListener('click', function() {
          var id = b.getAttribute('data-id');
          var resultEl = document.getElementById('caldav-push-result');
          resultEl.classList.remove('hidden');
          resultEl.textContent = 'Synchronisiere...';
          fetch(apiBase + '/caldav-sync-settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'sync', sync_id: parseInt(id, 10) }) }).then(function(r) { return r.json(); }).then(function(data) {
            resultEl.textContent = data.success ? (data.message || 'Fertig') : (data.message || 'Fehler');
          }).catch(function() { resultEl.textContent = 'Fehler'; }).finally(function() { loadCaldavSyncConfig(); });
        });
      });
      listEl.querySelectorAll('.caldav-delete').forEach(function(b) {
        b.addEventListener('click', function() {
          if (!confirm('Diesen CalDAV-Sync wirklich löschen?')) return;
          var id = b.getAttribute('data-id');
          fetch(apiBase + '/caldav-sync-settings.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ id: parseInt(id, 10) }) }).then(function(r) { return r.json(); }).then(function(data) { if (data.success) loadCaldavSyncConfig(); });
        });
      });
      
      document.getElementById('caldav-sync-id').value = '';
      document.getElementById('caldav-sync-name').value = '';
      document.getElementById('caldav-sync-username').value = '';
      document.getElementById('caldav-sync-password').value = '';
      document.getElementById('caldav-sync-calendar').value = 'Personal';
      ['my_calendar', 'vacation', 'invitations', 'service_tickets', 'todos'].forEach(function(k) {
        var cb = document.getElementById('sync-src-' + k);
        if (cb) cb.checked = !!caldavDefaultSources[k];
      });
      document.getElementById('caldav-sync-cancel-btn').classList.add('hidden');
    }).catch(function() {});
  }
  
  document.getElementById('caldav-add-btn').addEventListener('click', function() {
    document.getElementById('caldav-sync-id').value = '';
    document.getElementById('caldav-sync-name').value = '';
    document.getElementById('caldav-sync-server').value = '';
    document.getElementById('caldav-sync-username').value = '';
    document.getElementById('caldav-sync-password').value = '';
    document.getElementById('caldav-sync-calendar').value = 'Personal';
    ['my_calendar', 'vacation', 'invitations', 'service_tickets', 'todos'].forEach(function(k) {
      var cb = document.getElementById('sync-src-' + k);
      if (cb) cb.checked = !!caldavDefaultSources[k];
    });
    document.getElementById('caldav-sync-cancel-btn').classList.add('hidden');
  });
  
  document.getElementById('caldav-sync-server').addEventListener('change', function() {
    var sid = this.value;
    if (sid && serverCredentials[sid] && serverCredentials[sid].caldav_username) {
      document.getElementById('caldav-sync-username').value = serverCredentials[sid].caldav_username;
    }
  });
  
  document.getElementById('caldav-sync-cancel-btn').addEventListener('click', function() {
    document.getElementById('caldav-sync-id').value = '';
    document.getElementById('caldav-sync-name').value = '';
    document.getElementById('caldav-sync-server').value = '';
    document.getElementById('caldav-sync-username').value = '';
    document.getElementById('caldav-sync-password').value = '';
    document.getElementById('caldav-sync-calendar').value = 'Personal';
    ['my_calendar', 'vacation', 'invitations', 'service_tickets', 'todos'].forEach(function(k) {
      var cb = document.getElementById('sync-src-' + k);
      if (cb) cb.checked = !!caldavDefaultSources[k];
    });
    document.getElementById('caldav-sync-cancel-btn').classList.add('hidden');
  });
  
  document.getElementById('caldav-sync-save-btn').addEventListener('click', function() {
    var id = document.getElementById('caldav-sync-id').value;
    var serverId = document.getElementById('caldav-sync-server').value;
    var username = document.getElementById('caldav-sync-username').value.trim();
    var password = document.getElementById('caldav-sync-password').value;
    var calendar = document.getElementById('caldav-sync-calendar').value.trim() || 'Personal';
    var name = document.getElementById('caldav-sync-name').value.trim() || null;
    var exportSources = {};
    ['my_calendar', 'vacation', 'invitations', 'service_tickets', 'todos'].forEach(function(k) {
      var cb = document.getElementById('sync-src-' + k);
      exportSources[k] = cb ? cb.checked : true;
    });
    
    if (!serverId || !username) {
      if (typeof showToast === 'function') showToast('Server und Benutzername erforderlich', 'error');
      return;
    }
    if (!id && !password && (!serverCredentials[parseInt(serverId,10)] || serverCredentials[parseInt(serverId,10)].caldav_username !== username)) {
      if (typeof showToast === 'function') showToast('Passwort erforderlich beim ersten Sync für diesen Server', 'error');
      return;
    }
    
    var payload = { caldav_server_id: parseInt(serverId, 10), caldav_username: username, calendar_name: calendar, sync_name: name, export_sources: exportSources, is_active: true };
    if (id) payload.id = parseInt(id, 10);
    if (password) payload.caldav_password = password;
    
    fetch(apiBase + '/caldav-sync-settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(payload) }).then(function(r) { return r.json(); }).then(function(data) {
      if (data.success) {
        if (typeof showToast === 'function') showToast('CalDAV-Sync gespeichert', 'success');
        loadCaldavSyncConfig();
        document.getElementById('caldav-sync-id').value = '';
        document.getElementById('caldav-sync-password').value = '';
      } else {
        if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error');
      }
    }).catch(function() { if (typeof showToast === 'function') showToast('Fehler', 'error'); });
  });
  
  document.getElementById('caldav-test-btn').addEventListener('click', function() {
    var serverId = document.getElementById('caldav-sync-server').value;
    var username = document.getElementById('caldav-sync-username').value.trim();
    var password = document.getElementById('caldav-sync-password').value;
    var calendar = document.getElementById('caldav-sync-calendar').value.trim() || 'Personal';
    var syncId = document.getElementById('caldav-sync-id').value;
    var resultEl = document.getElementById('caldav-test-result');
    resultEl.classList.add('hidden');
    
    if (!serverId || !username) {
      if (typeof showToast === 'function') showToast('Server und Benutzername erforderlich', 'error');
      return;
    }
    
    fetch(apiBase + '/caldav-sync-settings.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ action: 'test', caldav_server_id: parseInt(serverId, 10), caldav_username: username, caldav_password: password, calendar_name: calendar, sync_id: syncId ? parseInt(syncId, 10) : null })
    }).then(function(r) { return r.json(); }).then(function(data) {
      resultEl.classList.remove('hidden');
      resultEl.textContent = data.message || (data.error || '');
      resultEl.className = 'text-sm ' + (data.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400');
    });
  });
  
  var caldavPushBtn = document.getElementById('caldav-push-btn');
  caldavPushBtn.addEventListener('click', function() {
    var resultEl = document.getElementById('caldav-push-result');
    resultEl.classList.remove('hidden');
    resultEl.textContent = 'Synchronisiere...';
    caldavPushBtn.disabled = true;
    fetch(apiBase + '/caldav-sync-settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'sync' }) }).then(function(r) { return r.json(); }).then(function(data) {
      resultEl.textContent = data.success ? (data.message || 'Fertig') : (data.message || 'Fehler');
    }).catch(function() { resultEl.textContent = 'Fehler'; }).finally(function() { caldavPushBtn.disabled = false; loadCaldavSyncConfig(); });
  });
  
  loadExportLink();
  loadExportSources();
  loadCaldavServers();
  loadCaldavSyncConfig();
})();
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
