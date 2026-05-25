<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
$userRole = null;
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userRole = $user['rolle'];
    }
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Nur Admin und Techniker können auf Admin-Seite zugreifen
if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
  <main>
    <div class="px-4">
      <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
        <!-- Header -->
        <div class="col-span-full mx-4 mt-4">
          <div class="mb-4">
          <nav class="mb-4 flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
                    <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                    </svg>
                    Startseite
                  </a>
                </li>
                <li>
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Übersicht</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Administration</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Verwaltung und Konfiguration des Systems</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Admin-Funktionen -->
        <div class="col-span-full mx-4">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            
            <!-- Erscheinungsbild (nur Admin) -->
            <?php if ($userRole === 'Admin'): ?>
            <a href="<?php echo BASE_URL; ?>admin/branding.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-pink-100 rounded-lg dark:bg-pink-900/20 group-hover:bg-pink-200 dark:group-hover:bg-pink-900/40 transition-colors">
                  <svg class="w-8 h-8 text-pink-600 dark:text-pink-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 0 1-4-4V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v12a4 4 0 0 1-4 4Zm0 0h12a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 0 1 2.828 0l2.829 2.829a2 2 0 0 1 0 2.828l-8.486 8.485M7 17h.01"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Erscheinungsbild</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Logo, Name und Farben anpassen</p>
            </a>
            <?php endif; ?>

            <!-- Ankündigungen verwalten (für Admin UND Techniker) -->
            <a href="<?php echo BASE_URL; ?>admin/announcements.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-yellow-100 rounded-lg dark:bg-yellow-900/20 group-hover:bg-yellow-200 dark:group-hover:bg-yellow-900/40 transition-colors">
                  <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm11-4a1 1 0 1 0-2 0v4a1 1 0 0 0 .293.707l3 3a1 1 0 0 0 1.414-1.414L13 11.586V8Z" clip-rule="evenodd"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Ankündigungen</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Systemweite Ankündigungen verwalten</p>
            </a>

            <!-- Nur für Admin -->
            <?php if ($userRole === 'Admin'): ?>
            <!-- Benutzerverwaltung -->
            <a href="<?php echo BASE_URL; ?>admin/users.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-primary-100 rounded-lg dark:bg-primary-900/20 group-hover:bg-primary-200 dark:group-hover:bg-primary-900/40 transition-colors">
                  <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Benutzerverwaltung</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Benutzer anzeigen, bearbeiten und verwalten</p>
            </a>

            <!-- Neuen Benutzer erstellen -->
            <a href="<?php echo BASE_URL; ?>admin/user_create.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-green-100 rounded-lg dark:bg-green-900/20 group-hover:bg-green-200 dark:group-hover:bg-green-900/40 transition-colors">
                  <svg class="w-8 h-8 text-green-600 dark:text-green-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M12 2a1 1 0 0 1 1 1v8h8a1 1 0 1 1 0 2h-8v8a1 1 0 1 1-2 0v-8H3a1 1 0 1 1 0-2h8V3a1 1 0 0 1 1-1Z" clip-rule="evenodd"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Neuer Benutzer</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Einen neuen Benutzer im System anlegen</p>
            </a>

            <!-- Easy Mode Cards Einstellungen -->
            <a href="<?php echo BASE_URL; ?>admin/cards-settings.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-primary-100 dark:border-primary-120 dark:hover:border-primary-360 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-primary-250/20 rounded-lg dark:bg-primary-250/30 group-hover:bg-primary-250/30 dark:group-hover:bg-primary-250/40 transition-colors">
                  <svg class="w-8 h-8 text-primary-250 dark:text-primary-250" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-2">Easy Mode Cards</h3>
              <p class="text-sm text-gray-600 dark:text-primary-210">Konfigurieren Sie, welche Cards im Easy Mode angezeigt werden</p>
            </a>

            

            <!-- CalDAV-Server -->
            <a href="<?php echo BASE_URL; ?>admin/caldav-servers.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-indigo-100 rounded-lg dark:bg-indigo-900/20 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/40 transition-colors">
                  <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V8.5a1 1 0 0 0-.293-.707l-4.5-4.5A1 1 0 0 0 14.5 3H5a1 1 0 0 0-1 1Z"/>
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">CalDAV-Server</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">CalDAV/Nextcloud-Server für Kalender-Export verwalten</p>
            </a>

            <!-- E-Mail-Einstellungen -->
            <a href="<?php echo BASE_URL; ?>admin/email-settings.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-teal-100 rounded-lg dark:bg-teal-900/20 group-hover:bg-teal-200 dark:group-hover:bg-teal-900/40 transition-colors">
                  <svg class="w-8 h-8 text-teal-600 dark:text-teal-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">E-Mail-Versand</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">SMTP-Konfiguration und Test-E-Mails</p>
            </a>

            <!-- Server & PHP-Konfiguration -->
            <a href="<?php echo BASE_URL; ?>admin/server-config.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-sky-100 rounded-lg dark:bg-sky-900/20 group-hover:bg-sky-200 dark:group-hover:bg-sky-900/40 transition-colors">
                  <svg class="w-8 h-8 text-sky-600 dark:text-sky-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h4m-6-4h8m-9-6h10M6 7h12M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Server &amp; PHP-Konfiguration</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">AllowOverride-Hinweise, .user.ini und .htaccess im System verwalten</p>
            </a>

            <!-- Mail-Log (ausgehend) -->
            <a href="<?php echo BASE_URL; ?>admin/mail-log.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-emerald-100 rounded-lg dark:bg-emerald-900/20 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-900/40 transition-colors">
                  <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Mail-Log</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Protokoll ausgehender E-Mails (Empfänger, Zeit, Kategorie)</p>
            </a>

            <!-- E-Mail-Empfang -->
            <a href="<?php echo BASE_URL; ?>admin/email-receive.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-cyan-100 rounded-lg dark:bg-cyan-900/20 group-hover:bg-cyan-200 dark:group-hover:bg-cyan-900/40 transition-colors">
                  <svg class="w-8 h-8 text-cyan-600 dark:text-cyan-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">E-Mail-Empfang</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">IMAP/POP3-Konfiguration und E-Mail-Abruf</p>
            </a>

            <!-- Web-Push (VAPID) -->
            <a href="<?php echo BASE_URL; ?>admin/push-settings.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-violet-100 rounded-lg dark:bg-violet-900/20 group-hover:bg-violet-200 dark:group-hover:bg-violet-900/40 transition-colors">
                  <svg class="w-8 h-8 text-violet-600 dark:text-violet-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6.002 6.002 0 0 0-4-5.659V5a2 2 0 1 0-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Web-Push (VAPID)</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Push-Benachrichtigungen für Smartphones – Schlüssel ohne Server-Shell</p>
            </a>

            <!-- Anrufe einstellen -->
            <a href="<?php echo BASE_URL; ?>admin/calls-settings.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-purple-100 rounded-lg dark:bg-purple-900/20 group-hover:bg-purple-200 dark:group-hover:bg-purple-900/40 transition-colors">
                  <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 0 1 2-2h3.28a1 1 0 0 1 .948.684l1.498 4.493a1 1 0 0 1-.502 1.21l-2.257 1.13a11.042 11.042 0 0 0 5.516 5.516l1.13-2.257a1 1 0 0 1 1.21-.502l4.493 1.498a1 1 0 0 1 .684.949V19a2 2 0 0 1-2 2h-1C9.716 21 3 14.284 3 6V5Z"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Anrufe einstellen</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">SIP-Konfiguration für Anrufe</p>
            </a>

            <!-- E-Mail-Vorlagen -->
            <a href="<?php echo BASE_URL; ?>admin/email-templates.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-orange-100 rounded-lg dark:bg-orange-900/20 group-hover:bg-orange-200 dark:group-hover:bg-orange-900/40 transition-colors">
                  <svg class="w-8 h-8 text-orange-600 dark:text-orange-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">E-Mail-Vorlagen</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Erstelle und verwalte E-Mail-Vorlagen mit Variablen</p>
            </a>

            <!-- Logs -->
            <a href="<?php echo BASE_URL; ?>admin/logs.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-indigo-100 rounded-lg dark:bg-indigo-900/20 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/40 transition-colors">
                  <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Logs</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">System-Änderungen und Aktivitäten einsehen</p>
            </a>

            <!-- Datenbank sichern & wiederherstellen -->
            <a href="<?php echo BASE_URL; ?>admin/database-backup.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-slate-100 rounded-lg dark:bg-slate-900/20 group-hover:bg-slate-200 dark:group-hover:bg-slate-900/40 transition-colors">
                  <svg class="w-8 h-8 text-slate-600 dark:text-slate-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Datenbank sichern</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">SQL-Export und -Import für Systemumzug (Hinweise zur Verschlüsselung)</p>
            </a>

            <!-- Gelöschte Tickets -->
            <a href="<?php echo BASE_URL; ?>admin/deleted-tickets.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-red-100 rounded-lg dark:bg-red-900/20 group-hover:bg-red-200 dark:group-hover:bg-red-900/40 transition-colors">
                  <svg class="w-8 h-8 text-red-600 dark:text-red-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14M10 11v6m4-6v6M6 7l1 12a1 1 0 0 0 1 .9h8a1 1 0 0 0 1-.9L18 7M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Gelöschte Tickets</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Soft-gelöschte Tickets wiederherstellen oder endgültig löschen</p>
            </a>

            <!-- Gelöschte Projekte -->
            <a href="<?php echo BASE_URL; ?>admin/deleted-projects.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-orange-100 rounded-lg dark:bg-orange-900/20 group-hover:bg-orange-200 dark:group-hover:bg-orange-900/40 transition-colors">
                  <svg class="w-8 h-8 text-orange-600 dark:text-orange-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v13H5Z"/>
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19v1a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-1"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Gelöschte Projekte</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Soft-gelöschte Projekte wiederherstellen oder endgültig löschen</p>
            </a>

            <!-- Gelöschte Wissensdatenbank-Seiten -->
            <a href="<?php echo BASE_URL; ?>admin/deleted-pages.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-amber-100 rounded-lg dark:bg-amber-900/20 group-hover:bg-amber-200 dark:group-hover:bg-amber-900/40 transition-colors">
                  <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Gelöschte Seiten (Wissensdatenbank)</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Soft-gelöschte Seiten wiederherstellen oder endgültig löschen</p>
            </a>

            <!-- Gelöschte Verbrauchsmaterialien -->
            <a href="<?php echo BASE_URL; ?>admin/deleted-consumables.php" class="group block p-6 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-primary-300 dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-600 transition-all">
              <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-emerald-100 rounded-lg dark:bg-emerald-900/20 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-900/40 transition-colors">
                  <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Zm0 4h14M9 14h6"/>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Gelöschte Verbrauchsmaterialien</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">Soft-gelöschte Artikel aus dem Lager wiederherstellen</p>
            </a>
            <?php endif; ?>

          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
