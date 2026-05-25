<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Benutzerdaten für Sidebar abrufen
$userId = $_SESSION['user_id'] ?? null;
$userRole = '';
$userData = [];
try {
    if ($userId) {
        $stmt = $pdo->prepare("SELECT rolle, vorname, nachname, email, logopfad FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            $userRole = $userRow['rolle'] ?? '';
            $userData = $userRow;
        }
    }
} catch (PDOException $e) {
    $userRole = '';
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

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

// BASE_URL definieren falls nicht vorhanden
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
?>

<div id="main-content"
     class="relative w-full min-h-0 overflow-x-hidden lg:overflow-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 lg:h-screen app-mobile-no-root-overscroll">
      <main class="mx-4 mt-2 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 lg:flex lg:h-[calc(100vh-0.5rem)] lg:flex-col lg:overflow-hidden">
        <style>
          /* Desktop als Basis, damit es beim Laden nicht nach unten springt */
          #notificationsMobileSheet {
            position: static;
            height: 100%;
            min-height: 0;
            transform: none;
            opacity: 1;
            border: 0;
            border-radius: 0;
            background: transparent;
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            overflow: hidden;
          }
          #notificationsMobileSheetHeader {
            display: none;
          }
          @media (max-width: 1023px) {
            #notificationsMobileSheetHeader {
              display: flex;
            }
            #notificationsMobileSheet {
              position: fixed;
              left: 0;
              right: 0;
              bottom: 0;
              z-index: 30;
              display: flex;
              flex-direction: column;
              height: calc(100dvh - (env(safe-area-inset-top, 0px) + 3.5rem));
              background: rgba(255, 255, 255, 0.94);
              border-top-left-radius: 1.25rem;
              border-top-right-radius: 1.25rem;
              border: 1px solid rgba(229, 231, 235, 0.9);
              border-bottom: 0;
              backdrop-filter: blur(14px) saturate(140%);
              -webkit-backdrop-filter: blur(14px) saturate(140%);
              transform: none;
              opacity: 1;
              transition: none;
            }
            #notificationsMobileSheet.notifications-mobile-sheet-open {
              transform: none;
              opacity: 1;
            }
            #notificationsMobileSheet .notifications-mobile-sheet-scroll {
              min-height: 0;
              overflow-y: auto;
              -webkit-overflow-scrolling: touch;
            }
            #notificationsMobileSheet .notifications-desktop-breadcrumb {
              display: none;
            }
            #notificationsMobileSheetHeader {
              display: flex;
              align-items: center;
              justify-content: space-between;
              gap: 0.75rem;
              padding: 0.65rem 0.9rem 0.35rem;
              border-bottom: 1px solid rgba(229, 231, 235, 0.8);
            }
            #notificationsMobileDragHandle {
              width: 2.5rem;
              height: 0.3rem;
              border-radius: 9999px;
              background: rgba(107, 114, 128, 0.45);
              margin: 0 auto;
            }
            #notificationsMobileCloseBtn {
              display: inline-flex;
              align-items: center;
              justify-content: center;
              width: 2rem;
              height: 2rem;
              border-radius: 0.55rem;
              color: #374151;
            }
            .dark #notificationsMobileSheetHeader {
              border-color: rgba(51, 65, 85, 0.9);
            }
            .dark #notificationsMobileDragHandle {
              background: rgba(148, 163, 184, 0.45);
            }
            .dark #notificationsMobileCloseBtn {
              color: #cbd5e1;
            }
            .dark #notificationsMobileSheet {
              background: rgba(16, 23, 42, 0.94);
              border-color: rgba(51, 65, 85, 0.9);
            }
          }
          @media (min-width: 1024px) {
            html, body {
              overflow: hidden;
            }
            #settingsContainer {
              overflow-anchor: none;
            }
            #notificationsContainer {
              align-content: start;
            }
            .notifications-desktop-breadcrumb {
              overflow-anchor: none;
              contain: layout;
            }
          }
        </style>
        <div id="notificationsMobileSheet" class="notifications-mobile-sheet-scroll notifications-mobile-sheet-open lg:h-full">
        <div id="notificationsMobileSheetHeader">
          <div class="flex-1">
            <div id="notificationsMobileDragHandle"></div>
          </div>
          <button type="button" id="notificationsMobileCloseBtn" aria-label="Benachrichtigungen schließen">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="px-0 lg:h-full lg:min-h-0 lg:flex lg:flex-col lg:justify-start">
<div class="grid grid-cols-12 gap-x-4 gap-y-0 bg-gray-50 dark:bg-primary-50 lg:h-full lg:min-h-0 lg:overflow-hidden lg:content-start lg:items-start">
  <div class="col-span-full">
    <nav class="mb-4 hidden lg:flex notifications-desktop-breadcrumb" aria-label="Breadcrumb">
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
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Benachrichtigungen</span>
          </div>
        </li>
      </ol>
    </nav>
  </div>
  <div class="relative col-span-full lg:h-full lg:min-h-0 lg:overflow-hidden lg:self-start">
    <div class="lg:grid lg:grid-cols-12 lg:gap-4 lg:items-start lg:content-start lg:h-full lg:min-h-0">
      <aside class="hidden lg:col-span-3 lg:mx-0 lg:block lg:self-start lg:sticky lg:top-0">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
          <div class="flex items-center gap-3">
              <?php if ($isPresetAvatar && $presetColor): ?>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-gray-200 text-sm font-semibold text-white dark:border-gray-600" style="background-color: <?php echo htmlspecialchars($presetColor, ENT_QUOTES, 'UTF-8'); ?>;">
                  <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                </div>
              <?php elseif ($avatarPath !== ''): ?>
                <img src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profilbild" class="h-12 w-12 shrink-0 rounded-full border border-gray-200 object-cover dark:border-gray-600">
              <?php else: ?>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-primary-600 text-sm font-semibold text-white dark:border-gray-600">
                  <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                </div>
              <?php endif; ?>
            <div class="min-w-0">
              <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
              </p>
              <p class="truncate text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($userRole ?: 'Benutzer', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          </div>
        </div>
        <div class="mb-4 grid grid-cols-1 gap-1.5 border-b border-gray-200 pb-4 dark:border-gray-700">
          <a href="<?php echo BASE_URL; ?>account/index.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
            <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z" />
            </svg>
            Persönliche Daten
          </a>
          <a href="<?php echo htmlspecialchars($companyLink, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
            <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/>
            </svg>
            Meine Firma
          </a>
          <a href="<?php echo BASE_URL; ?>notifications/" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 px-4 py-3 text-left text-base font-semibold" style="background-color:#ede9fe !important;color:#5b21b6 !important;border-left-color:#7c3aed !important;">
            <svg class="me-2 h-5 w-5" style="color:#5b21b6 !important;" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
            </svg>
            Benachrichtigungen
          </a>
        </div>
        <ul class="-mb-px grid grid-cols-1 gap-1.5 text-base font-semibold">
              <li>
                <a href="<?php echo BASE_URL; ?>settings/index.php#praeferenzen" class="settings-tab-btn inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M20 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6h-2m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4"/>
                  </svg>
                  Präferenzen
                </a>
              </li>
              <li>
                <a href="<?php echo BASE_URL; ?>settings/index.php#benachrichtigungen" class="settings-tab-btn inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z" />
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                  </svg>
                  Benachrichtigungen
                </a>
              </li>
              <li>
                <a href="<?php echo BASE_URL; ?>settings/index.php#sicherheit" class="settings-tab-btn inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                  </svg>
                  Sicherheit
                </a>
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
     
      </aside>
    <div class="col-span-full lg:col-span-9 lg:h-full lg:min-h-0 lg:overflow-y-auto lg:pe-2 lg:pb-8" id="settingsContainer" tabindex="-1">
      <div id="notificationsContainer" class="space-y-3 pb-6">
        <div class="flex items-center justify-center py-8" role="status">
          <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-600 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
            <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
          </svg>
          <span class="sr-only">Loading...</span>
        </div>
      </div>
            
      <div id="loadMoreContainer" class="mt-6 text-center hidden">
        <button id="loadMoreBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
          Mehr laden
        </button>
      </div>
    </div>
    </div>
  </div>
</div>
        </div>
        </div>
      </main>
  </div>

<script>
// baseUrl wird bereits in nav.php definiert
let currentOffset = 0;
let isLoading = false;
let hasMore = true;

function buildNotificationsListQuery(offset) {
    const params = new URLSearchParams();
    params.set('limit', '20');
    params.set('offset', String(offset));
    params.set('read_state', 'unread');
    params.set('sort', 'relevanz');
    params.set('sort_dir', 'asc');
    return params.toString();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Gerade eben';
    if (diffMins < 60) return `vor ${diffMins} Min.`;
    if (diffHours < 24) return `vor ${diffHours} Std.`;
    if (diffDays < 7) return `vor ${diffDays} Tag(en)`;
    
    return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function renderEmptyNotificationsState() {
    return `
        <div class="rounded-xl border border-dashed border-gray-300 bg-white/70 px-6 py-10 text-center dark:border-gray-600 dark:bg-gray-800/60 lg:border-0 lg:bg-transparent lg:dark:bg-transparent">
            <div class="mx-auto mb-4 flex items-center justify-center">
                <svg class="w-auto max-w-[24rem] lg:max-w-[30rem] h-64 lg:h-80 text-gray-800 dark:text-white" aria-hidden="true" width="433" height="559" viewBox="0 0 433 559" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M278 411H376.905H394C394 399.367 373.853 396.918 365.305 392.633C356.758 388.347 351.874 381 340.884 381C329.895 381 317.074 395.694 302.421 396.918C290.699 397.898 281.256 406.714 278 411Z" fill="#d6e2fb" fill-opacity="0.6"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M168.025 452.826L184.978 389.558L186.909 390.076L169.957 453.344L168.025 452.826Z" fill="#c8d8fa"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M168.025 452.826L184.978 389.558L186.909 390.076L169.957 453.344L168.025 452.826Z" fill="url(#paint0_linear_275_1035)"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M214.384 396.738L215.459 398.425L178.777 421.802L198.856 460.388L197.082 461.311L176.157 421.1L214.384 396.738Z" fill="#c8d8fa"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M214.384 396.738L215.459 398.425L178.777 421.802L198.856 460.388L197.082 461.311L176.157 421.1L214.384 396.738Z" fill="url(#paint1_linear_275_1035)"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M157.853 381.591L156.079 382.514L176.158 421.1L139.476 444.477L140.551 446.164L178.778 421.802L157.853 381.591Z" fill="#c8d8fa"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M157.853 381.591L156.079 382.514L176.158 421.1L139.476 444.477L140.551 446.164L178.778 421.802L157.853 381.591Z" fill="url(#paint2_linear_275_1035)"/>
                    <rect x="175.032" y="411.222" width="10" height="19" rx="2" transform="rotate(15 175.032 411.222)" fill="#2563eb"/>
                    <rect x="175.032" y="411.222" width="10" height="19" rx="2" transform="rotate(15 175.032 411.222)" fill="url(#paint3_linear_275_1035)"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M185.134 362.165L149.567 352.635L149.38 352.585L149.276 352.422C137.81 334.549 121.304 303.062 108.32 266.461C95.3374 229.866 86.0931 188.176 89.6644 150.043C94.1343 102.315 121.143 69.4602 157.325 51.3142C193.49 33.1765 238.816 29.7315 279.99 40.764C321.164 51.7965 358.694 77.4428 380.945 111.233C403.207 145.039 410.17 186.996 390.177 230.565C374.203 265.375 345.353 296.857 315.812 322.058C286.267 347.263 256.353 366.313 237.488 376.058L237.316 376.147L237.129 376.097L195.764 365.013L185.134 362.165Z" fill="#c8d8fa"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M221.78 34.7273C198.646 51.2191 176.94 80.4991 170.261 129.77C158.361 217.555 174.997 318.453 185.658 361.788L191.022 363.225C194.115 329.503 199.298 278.136 205.172 229.587C211.23 179.508 218.034 132.331 224.049 110.658C234.826 71.8267 247.187 49.0496 258.42 36.3452C246.255 34.5991 233.935 34.058 221.78 34.7273ZM218.074 34.9692C195.498 52.0571 174.797 81.4221 168.279 129.502C156.43 216.912 172.752 317.152 183.454 361.197L175.131 358.967C161.056 317.988 134.228 225.304 133.544 164.301C132.78 96.1295 164.505 55.5421 185.948 40.419C196.339 37.6117 207.126 35.7964 218.074 34.9692ZM180.606 41.9606C158.919 59.5359 130.821 99.831 131.544 164.323C132.225 225.043 158.609 316.716 172.804 358.344L162.559 355.598C153.367 338.076 140.638 305.164 129.848 269.65C118.722 233.031 109.7 193.782 108.721 165.908C107.173 121.867 116.647 89.043 137.906 63.0852C143.992 58.6151 150.495 54.6701 157.325 51.2493C164.738 47.5364 172.537 44.4408 180.606 41.9606ZM131.558 68.0687C108.814 87.1461 93.0367 114.373 89.7235 149.819C86.1641 187.898 95.4152 229.53 108.401 266.077C121.389 302.629 138.13 334.14 149.594 351.99L149.699 352.153L149.886 352.203L159.945 354.898C150.744 336.855 138.413 304.721 127.934 270.232C116.797 233.575 107.711 194.122 106.722 165.978C105.281 124.965 113.332 93.4253 131.558 68.0687ZM260.791 36.7011C249.655 48.834 237.02 71.3989 225.976 111.193C220.009 132.691 213.221 179.705 207.157 229.827C201.267 278.518 196.07 330.049 192.982 363.75L193.418 363.867C207.594 333.137 228.859 285.911 248.103 240.799C267.913 194.359 285.541 150.25 291.123 128.649C301.464 88.6318 301.797 62.7635 298.209 46.6907C292.224 44.3942 286.117 42.4057 279.927 40.7473C273.63 39.0598 267.235 37.7109 260.791 36.7011ZM300.441 47.5635C303.827 64.1807 303.151 90.0976 293.059 129.149C287.432 150.926 269.735 195.185 249.943 241.584C230.755 286.565 209.56 333.642 195.378 364.392L195.83 364.513L200.742 365.83C231.642 333.631 296.498 254.568 330.085 172.595C348.977 126.487 344.761 90.2199 332.914 64.3596C322.737 57.7482 311.82 52.0983 300.441 47.5635ZM336.003 66.4116C347.07 92.513 350.373 128.355 331.935 173.353C298.492 254.977 234.236 333.626 202.946 366.42L211.268 368.65C243.947 340.199 313.523 273.346 344.617 220.858C379.569 161.856 372.097 110.588 360.98 86.9526C353.408 79.4152 345.016 72.531 336.003 66.4116ZM364.869 90.959C374.978 116.915 379.386 166.088 346.338 221.877C315.388 274.122 246.703 340.321 213.595 369.274L222.393 371.631C239.114 361.052 266.594 338.913 293.695 313.553C321.64 287.403 349.077 257.923 363.863 234.273C388.498 194.869 396.281 160.139 388.989 125.217C386.573 120.389 383.852 115.692 380.853 111.144C376.166 104.035 370.802 97.2863 364.869 90.959ZM392.29 132.347C397.151 165.323 388.662 198.378 365.559 235.333C350.63 259.212 323.035 288.836 295.062 315.013C268.742 339.643 241.996 361.305 225.006 372.331L236.819 375.496L237.006 375.546L237.178 375.458C256.031 365.731 286.284 346.813 315.808 321.652C345.328 296.494 374.156 265.065 390.113 230.308C406.086 195.516 404.824 161.747 392.29 132.347Z" fill="url(#paint4_linear_275_1035)"/>
                    <path d="M149.336 352.573C179.995 353.968 209.986 362.004 237.235 376.125L234.864 378.144C227.673 384.265 222.514 392.428 220.07 401.549L151.489 383.173C153.933 374.052 153.546 364.403 150.38 355.506L149.336 352.573Z" fill="#c8d8fa"/>
                    <path d="M149.336 352.573C179.995 353.968 209.986 362.004 237.235 376.125L234.864 378.144C227.673 384.265 222.514 392.428 220.07 401.549L151.489 383.173C153.933 374.052 153.546 364.403 150.38 355.506L149.336 352.573Z" fill="url(#paint5_linear_275_1035)"/>
                    <rect width="44" height="69" transform="matrix(-0.965926 -0.258819 -0.258819 0.965926 232.294 467.976)" fill="#2563eb"/>
                    <rect width="44" height="69" transform="matrix(-0.965926 -0.258819 -0.258819 0.965926 232.294 467.976)" fill="url(#paint6_linear_275_1035)"/>
                    <rect x="112.52" y="435.883" width="80" height="69" transform="rotate(15 112.52 435.883)" fill="#2563eb"/>
                    <rect x="111.826" y="477.108" width="29" height="19" rx="2" transform="rotate(15 111.826 477.108)" fill="#d6e2fb"/>
                    <rect x="112.395" y="486.578" width="16" height="2" rx="1" transform="rotate(15 112.395 486.578)" fill="#2563eb"/>
                    <path d="M204.282 460.471L216.839 463.835L213.733 475.426C213.448 476.493 212.351 477.127 211.284 476.841L202.591 474.511C201.524 474.225 200.891 473.129 201.176 472.062L204.282 460.471Z" fill="#c8d8fa" fill-opacity="0.2"/>
                    <rect x="111.359" y="490.441" width="12" height="2" rx="1" transform="rotate(15 111.359 490.441)" fill="#2563eb"/>
                    <path d="M116.5 39.5H35.5H21.5C21.5 30 38 28 45 24.5C52 21 56 15 65 15C74 15 84.5 27 96.5 28C106.1 28.8 113.833 36 116.5 39.5Z" fill="#d6e2fb"/>
                    <defs>
                        <linearGradient id="paint0_linear_275_1035" x1="185.943" y1="389.817" x2="168.991" y2="453.085" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#9ab7f6"/>
                            <stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="paint1_linear_275_1035" x1="200.309" y1="392.967" x2="183.006" y2="457.54" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#9ab7f6"/>
                            <stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="paint2_linear_275_1035" x1="171.929" y1="385.362" x2="154.626" y2="449.935" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#9ab7f6"/>
                            <stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="paint3_linear_275_1035" x1="180.032" y1="403.222" x2="180.032" y2="430.222" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#111928"/>
                            <stop offset="1" stop-color="#111928" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="paint4_linear_275_1035" x1="258.704" y1="119.953" x2="193.352" y2="363.849" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#d6e2fb"/>
                            <stop offset="1" stop-color="#d6e2fb" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="paint5_linear_275_1035" x1="211.661" y1="295.768" x2="185.78" y2="392.361" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#2563eb"/>
                            <stop offset="1" stop-color="#2563eb" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="paint6_linear_275_1035" x1="22" y1="-28.5" x2="22" y2="69" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#111928"/>
                            <stop offset="1" stop-color="#111928" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Keine Benachrichtigungen</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sobald etwas Neues passiert, erscheint es hier.</p>
        </div>
    `;
}

function renderNotification(notification) {
    const link = notification.link ? (baseUrl + notification.link.replace(/^\//, '')) : '#';
    // Profilbild des Erstellers (inkl. preset:color:initials) – gleiches Prinzip wie in der Nav
    const creatorAvatar = notification.creator_avatar || '';
    const creatorName = notification.creator_name || 'Unbekannt';

    const getAvatarHtml = () => {
        if (creatorAvatar && typeof creatorAvatar === 'string' && creatorAvatar.startsWith('preset:')) {
            const parts = creatorAvatar.split(':');
            let color = (parts[1] || '#3b82f6');
            if (!color.startsWith('#')) color = '#' + color;
            const initials = (parts[2] || 'U').toUpperCase();
            return `
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold border border-gray-300 dark:border-gray-600"
                     style="background-color: ${escapeHtml(color)};"
                     title="${escapeHtml(creatorName)}">
                    ${escapeHtml(initials)}
                </div>
            `;
        }

        const fallbackUrl = `${baseUrl}assets/images/default-avatar.png`;
        const url = creatorAvatar
            ? (creatorAvatar.startsWith('http://') || creatorAvatar.startsWith('https://')
                ? creatorAvatar
                : (baseUrl + creatorAvatar.replace(/^\//, '')))
            : fallbackUrl;

        return `
            <img src="${escapeHtml(url)}"
                 alt="Avatar"
                 class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600"
                 onerror="this.onerror=null; this.src='${fallbackUrl}';">
        `;
    };
    
    return `
        <div class="notification-item relative bg-white rounded-lg shadow-sm border border-gray-200 p-3 ${notification.link ? 'cursor-pointer hover:border-primary-300' : ''}" style="background-color:#ffffff !important;opacity:1 !important;" data-id="${notification.id}" data-link="${notification.link ? escapeHtml(link) : ''}" onclick="openNotificationFromCard(event, this)">
            <button onclick="event.stopPropagation(); deleteNotification(${notification.id})" class="absolute top-2 right-2 p-1.5 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 rounded" title="Löschen">
                <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6 6 18"/>
                </svg>
                <span class="sr-only">Löschen</span>
            </button>
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    ${getAvatarHtml()}
                </div>
                <div class="flex-1 min-w-0">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                ${notification.titel}
                            </h3>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 whitespace-normal break-words">
                            ${notification.nachricht}
                        </p>
                    </div>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span class="whitespace-nowrap">${formatDate(notification.erstellt_datum)}</span>
                            <span>•</span>
                            <span class="whitespace-nowrap truncate" title="${creatorName}">${creatorName}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

async function openNotificationFromCard(event, cardElement) {
    if (!cardElement) return;
    const clickedInteractive = event.target.closest('button, a');
    if (clickedInteractive) return;
    const link = cardElement.dataset.link || '';
    const notificationId = parseInt(cardElement.dataset.id || '0', 10);
    if (!link) return;

    if (notificationId > 0) {
        try {
            await fetch(`${baseUrl}notifications/api/notifications.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: notificationId })
            });
        } catch (error) {
            console.error('Fehler beim automatischen Löschen:', error);
        } finally {
            window.location.href = link;
        }
    } else {
        window.location.href = link;
    }
}

async function loadNotifications(append = false) {
    if (isLoading || (!hasMore && append)) return;

    if (!append) {
        currentOffset = 0;
        hasMore = true;
    }

    isLoading = true;
    const container = document.getElementById('notificationsContainer');
    const scrollContainer = document.getElementById('settingsContainer');
    const mobileSheet = document.getElementById('notificationsMobileSheet');
    const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
    const shouldRestoreScroll = !append;
    const previousDesktopScrollTop = shouldRestoreScroll && isDesktop && scrollContainer ? scrollContainer.scrollTop : null;
    const previousMobileScrollTop = shouldRestoreScroll && !isDesktop && mobileSheet ? mobileSheet.scrollTop : null;
    const previousWindowScrollTop = shouldRestoreScroll ? window.scrollY : null;

    if (!append) {
        container.innerHTML = `
            <div class="flex items-center justify-center py-8" role="status">
                <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-600 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                    <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                </svg>
                <span class="sr-only">Loading...</span>
            </div>
        `;
    }

    try {
        const qs = buildNotificationsListQuery(currentOffset);
        const response = await fetch(`${baseUrl}notifications/api/notifications.php?${qs}`);
        const data = await response.json();

        if (data.success) {
            if (!append) {
                container.innerHTML = '';
            }

            if (data.notifications.length === 0 && !append) {
                container.innerHTML = renderEmptyNotificationsState();
            } else {
                data.notifications.forEach(notification => {
                    container.insertAdjacentHTML('beforeend', renderNotification(notification));
                });
            }

            hasMore = data.notifications.length === 20;
            currentOffset += data.notifications.length;

            if (hasMore) {
                document.getElementById('loadMoreContainer').classList.remove('hidden');
            } else {
                document.getElementById('loadMoreContainer').classList.add('hidden');
            }
        } else {
            container.innerHTML = '<div class="text-center py-8 text-red-500">Fehler beim Laden der Benachrichtigungen.</div>';
        }
    } catch (error) {
        console.error('Fehler:', error);
        container.innerHTML = '<div class="text-center py-8 text-red-500">Fehler beim Laden der Benachrichtigungen.</div>';
    } finally {
        isLoading = false;
        if (shouldRestoreScroll) {
            requestAnimationFrame(() => {
                if (isDesktop && scrollContainer && previousDesktopScrollTop !== null) {
                    scrollContainer.scrollTop = previousDesktopScrollTop;
                }
                if (!isDesktop && mobileSheet && previousMobileScrollTop !== null) {
                    mobileSheet.scrollTop = previousMobileScrollTop;
                }
                if (previousWindowScrollTop !== null && Math.abs(window.scrollY - previousWindowScrollTop) > 1) {
                    window.scrollTo({ top: previousWindowScrollTop, left: 0, behavior: 'auto' });
                }
            });
        }
    }
}

async function markAsRead(notificationId) {
    try {
        const response = await fetch(`${baseUrl}notifications/api/notifications.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: notificationId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const item = document.querySelector(`[data-id="${notificationId}"]`);
            if (item) {
                item.classList.add('opacity-60');
                // Finde den Button "Als gelesen markieren" sicherer
                const buttons = item.querySelectorAll('.text-xs button');
                buttons.forEach(btn => {
                    if (btn.textContent.includes('Als gelesen markieren') || btn.textContent.includes('Gelesen')) {
                        btn.textContent = 'Gelesen';
                    }
                });
                const dot = item.querySelector('.w-2.h-2.bg-blue-600');
                if (dot) {
                    dot.remove();
                }
            }
            
            if (typeof showToast === 'function') {
                showToast('Benachrichtigung als gelesen markiert', 'success');
            }
            
            currentOffset = 0;
            hasMore = true;
            loadNotifications(false);
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Markieren als gelesen', 'error');
            } else {
                alert('Fehler beim Markieren als gelesen');
            }
        }
    } catch (error) {
        console.error('Fehler:', error);
        alert('Fehler beim Markieren als gelesen');
    }
}

async function deleteNotification(notificationId) {
    try {
        const response = await fetch(`${baseUrl}notifications/api/notifications.php`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: notificationId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Benachrichtigung entfernt', 'success');
            }
            currentOffset = 0;
            hasMore = true;
            loadNotifications(false);
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Löschen', 'error');
            } else {
                alert('Fehler beim Löschen');
            }
        }
    } catch (error) {
        console.error('Fehler:', error);
        alert('Fehler beim Löschen');
    }
}

async function deleteAllNotifications() {
    if (!confirm('Möchten Sie wirklich alle Benachrichtigungen löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;
    
    try {
        const response = await fetch(`${baseUrl}notifications/api/notifications.php`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ delete_all: true })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Alle Benachrichtigungen erfolgreich gelöscht', 'success');
            }
            currentOffset = 0;
            hasMore = true;
            loadNotifications(false);
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Löschen aller Benachrichtigungen', 'error');
            } else {
                alert('Fehler beim Löschen aller Benachrichtigungen');
            }
        }
    } catch (error) {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Löschen aller Benachrichtigungen', 'error');
        } else {
            alert('Fehler beim Löschen aller Benachrichtigungen');
        }
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual';
    }

    const mobileSheet = document.getElementById('notificationsMobileSheet');
    const mobileCloseBtn = document.getElementById('notificationsMobileCloseBtn');
    const isMobileSheet = !!(mobileSheet && window.matchMedia('(max-width: 1023px)').matches);
    let touchStartY = null;
    let touchCurrentY = null;
    let touchStartTime = 0;

    function closeMobileSheet() {
        if (!isMobileSheet || !mobileSheet) return;
        mobileSheet.classList.remove('notifications-mobile-sheet-open');
        const goBack = function() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = `${baseUrl}dashboard/`;
            }
        };
        window.setTimeout(goBack, 220);
    }

    if (mobileCloseBtn) {
        mobileCloseBtn.addEventListener('click', closeMobileSheet);
    }
    if (isMobileSheet && mobileSheet) {
        mobileSheet.addEventListener('touchstart', function(e) {
            if (!e.touches || e.touches.length !== 1) return;
            const target = e.target;
            if (target && target.closest && target.closest('#notificationsContainer') && mobileSheet.scrollTop > 0) return;
            touchStartY = e.touches[0].clientY;
            touchCurrentY = touchStartY;
            touchStartTime = Date.now();
        }, { passive: true });

        mobileSheet.addEventListener('touchmove', function(e) {
            if (touchStartY === null || !e.touches || e.touches.length !== 1) return;
            touchCurrentY = e.touches[0].clientY;
        }, { passive: true });

        mobileSheet.addEventListener('touchend', function() {
            if (touchStartY === null || touchCurrentY === null) return;
            const deltaY = touchCurrentY - touchStartY;
            const elapsed = Date.now() - touchStartTime;
            const fastSwipeDown = deltaY > 70 && elapsed < 280;
            const longSwipeDown = deltaY > 120;
            if (mobileSheet.scrollTop <= 0 && (fastSwipeDown || longSwipeDown)) {
                closeMobileSheet();
            }
            touchStartY = null;
            touchCurrentY = null;
            touchStartTime = 0;
        }, { passive: true });
    }

    const resetAllButtons = Array.from(document.querySelectorAll('[data-reset-all-settings-btn]'));
    if (resetAllButtons.length) {
        resetAllButtons.forEach(function(resetBtn) {
            resetBtn.addEventListener('click', async function() {
                const spinner = resetBtn.querySelector('[data-reset-all-spinner]');
                const label = resetBtn.querySelector('[data-reset-all-label]');
                const confirmed = window.confirm('Möchtest du wirklich alle Einstellungen auf Standard zurücksetzen?');
                if (!confirmed) return;

                resetAllButtons.forEach(function(btn) { btn.disabled = true; });
                if (spinner) spinner.classList.remove('hidden');
                if (label) label.classList.add('hidden');

                try {
                    const response = await fetch(`${baseUrl}settings/api/reset-all.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error((data && data.error) ? data.error : 'Zuruecksetzen fehlgeschlagen');
                    }

                    try {
                        ['svDesktopNotifications', 'sounds_enabled', 'sidebarExpanded'].forEach(function(key) {
                            localStorage.removeItem(key);
                        });
                    } catch (e) {}

                    if (typeof showToast === 'function') {
                        showToast('Alle Einstellungen wurden auf Standard zurueckgesetzt', 'success');
                    }
                    window.setTimeout(function() {
                        window.location.reload();
                    }, 350);
                } catch (error) {
                    resetAllButtons.forEach(function(btn) { btn.disabled = false; });
                    if (spinner) spinner.classList.add('hidden');
                    if (label) label.classList.remove('hidden');
                    if (typeof showToast === 'function') {
                        showToast(error && error.message ? error.message : 'Zuruecksetzen fehlgeschlagen', 'error');
                    } else {
                        alert(error && error.message ? error.message : 'Zuruecksetzen fehlgeschlagen');
                    }
                }
            });
        });
    }

    loadNotifications(false);
    
    document.getElementById('loadMoreBtn').addEventListener('click', () => loadNotifications(true));
    
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
