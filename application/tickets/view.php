<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/service_log_helper.php';
requireLogin();

$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticketId) {
    header('Location: ' . BASE_URL . 'tickets/');
    exit;
}

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

service_log($pdo, $userId, 'ticket', $ticketId, 'viewed', null, null, null, 'Tickets: Ticket-Detail #' . $ticketId . ' aufgerufen');
$serviceMobileFullscreen = true;
$ticketViewMobileTopNav = true;
$navTicketViewDetailMobile = true;
$navMobileHideCompactCreateButton = true;
include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 <?php echo (!empty($serviceMobileFullscreen) || empty($dashboardKeepMobileTopNav)) ? '' : 'pt-12'; ?> lg:pt-0 flex flex-col min-h-0 app-mobile-no-root-overscroll">
  <main class="ticket-view-detail pt-4 flex flex-col flex-1 min-h-0 overflow-hidden">
  <div id="ticket-view-page-stack" class="flex flex-col flex-1 min-h-0 overflow-hidden">
  <div id="ticketMobileInfoBackdrop" class="ticket-mobile-info-backdrop lg:hidden" aria-hidden="true"></div>
<div id="ticket-view-outer-grid" class="grid grid-cols-12 grid-rows-[auto_1fr] gap-4 flex-1 min-h-0 bg-gray-50 dark:bg-primary-50">
  <div class="hidden md:flex col-span-full items-start justify-between sm:flex mx-4">
         <nav class="mb-2 sm:mb-0 flex flex-shrink-0" aria-label="Breadcrumb">
           <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
             <li class="inline-flex items-center">
               <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                 <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                   <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                 </svg>
                 Startseite
               </a>
             </li>
             <li>
               <div class="flex items-center">
                 <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                   <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                 </svg>
                 <a href="<?php echo htmlspecialchars(BASE_URL); ?>tickets/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2">Tickets</a>
               </div>
             </li>
             <li aria-current="page">
               <div class="flex items-center">
                 <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                   <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                 </svg>
                 <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2" id="breadcrumbTicketLabel">Ticket</span>
               </div>
             </li>
           </ol>
         </nav>
         <div class="mb-4 sm:mb-0 flex items-center gap-4 self-center">
          <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
            <div id="statusButtonGroup" class="hidden inline-flex rounded-lg shadow-sm gap-px" role="group">
              <button type="button" onclick="updateTicketField('status', 'Neu')" 
                      class="status-btn flex items-center px-4 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-l-lg">
                Neu
              </button>
              <button type="button" onclick="updateTicketField('status', 'In Bearbeitung')" 
                      class="status-btn flex items-center px-4 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500">
                In Bearbeitung
              </button>
              <button type="button" onclick="updateTicketField('status', 'Warteschlange')" 
                      class="status-btn flex items-center px-4 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-r-lg">
                Warteschlange
              </button>
            </div>
          <?php endif; ?>
          <div class="relative" id="more-options-dropdown-container">
            <button type="button" id="more-options-dropdown-button" class="inline-flex items-center justify-center gap-1.5 px-4 py-1.5 text-sm font-medium text-gray-900 dark:text-primary-200 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors" aria-expanded="false" aria-haspopup="true">
              Mehr Optionen
              <svg class="w-4 h-4 text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div id="more-options-dropdown-menu" class="hidden absolute right-0 z-50 mt-1 min-w-[10rem] bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-card overflow-hidden" role="menu">
              <div class="py-1">
                <button type="button" id="option-copy-ticket-number" class="hidden w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 10h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                  </svg>
                  Ticketnummer kopieren
                </button>
                <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                <button type="button" id="option-duplicate-ticket" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M9 8v3a1 1 0 0 1-1 1H5m11 4h2a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1h-7a1 1 0 0 0-1 1v1m4 3v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-7.13a1 1 0 0 1 .24-.65L7.7 8.35A1 1 0 0 1 8.46 8H13a1 1 0 0 1 1 1Z"/>
</svg>

                  Duplizieren
                </button>
                <button type="button" id="option-link-ticket" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path fill-rule="evenodd" d="M3 6a3 3 0 1 1 4 2.83v6.34a3.001 3.001 0 1 1-2 0V8.83A3.001 3.001 0 0 1 3 6Zm11.207-2.707a1 1 0 0 1 0 1.414L13.914 5H15a4 4 0 0 1 4 4v6.17a3.001 3.001 0 1 1-2 0V9a2 2 0 0 0-2-2h-1.086l.293.293a1 1 0 0 1-1.414 1.414l-2-2a1 1 0 0 1 0-1.414l2-2a1 1 0 0 1 1.414 0Z" clip-rule="evenodd"/>
</svg>

                  Zusammenführen
                </button>
                <button type="button" id="option-link-project" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/>
</svg>
                  <span id="option-link-project-text">Mit Projekt verknüpfen</span>
                </button>
                <button type="button" id="option-save-to-probleme" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v13H7a2 2 0 0 0-2 2Zm0 0a2 2 0 0 0 2 2h12M9 3v14m7 0v4"/>
                  </svg>
                  Dokumentieren
                </button>
                <button type="button" id="option-show-history" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-9-9 9 9 0 019 9z"/>
                  </svg>
                  History anzeigen
                </button>
                <?php endif; ?>
                <?php if ($userRole === 'Admin'): ?>
                <button type="button" id="option-edit-subject" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                  Betreff ändern
                </button>
                <?php endif; ?>
                <button type="button" id="option-toggle-pin" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M12.0001 20v-4M7.00012 4h9.99998M9.00012 5v5c0 .5523-.46939 1.0045-.94861 1.279-1.43433.8217-2.60135 3.245-2.25635 4.3653.07806.2535.35396.3557.61917.3557H17.5859c.2652 0 .5411-.1022.6192-.3557.3449-1.1204-.8221-3.5436-2.2564-4.3653-.4792-.2745-.9486-.7267-.9486-1.279V5c0-.55228-.4477-1-1-1h-4c-.55226 0-.99998.44772-.99998 1Z"/>
</svg>

                  <span id="option-toggle-pin-label">Anheften</span>
                </button>
                <div class="my-1 border-t border-gray-200 dark:border-primary-120"></div>
                <button type="button" id="option-export-pdf" class="option-export-pdf w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  Exportieren
                </button>
                <?php if ($userRole === 'Admin'): ?>
                <button type="button" id="option-delete-ticket" class="option-delete-ticket w-full text-left px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors flex items-center gap-2" role="menuitem">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                  Löschen
                </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
         </div>
        </div>
        
        <div id="ticket-view-chat-section" class="relative col-span-full min-h-0 flex flex-col px-4">
          <div class="flex-1 min-h-0 flex flex-col">
            <!-- 60/40 Split Layout -->
            <div id="service-view-chat-grid" class="grid grid-cols-1 lg:grid-cols-10 gap-4 lg:gap-3 flex-1 min-h-0">
        <!-- Chat-Bereich (60% / 6 Spalten) -->
        <div id="service-view-chat-column" class="service-chat-container lg:col-span-6 flex flex-col min-h-0 bg-white dark:bg-primary-50 rounded-xl shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
          <!-- Chat Header (kompakt) -->
          <div class="flex-shrink-0 hidden lg:flex items-center border-b border-gray-100 dark:border-primary-140 bg-white dark:bg-primary-100 px-4 py-3 shadow-sm min-h-[2.75rem]" id="chatTicketHeader">
            <div class="flex items-center justify-center h-full w-full">
              <p class="text-gray-500 dark:text-primary-210 text-sm">Lade Ticket...</p>
            </div>
          </div>
          
          <!-- Chat Messages Area -->
          <div class="service-chat-messages flex-1 overflow-y-auto overflow-x-hidden min-h-0 custom-scrollbar" id="chatTicketContent">
            <div class="flex flex-col items-center justify-center h-full min-h-[280px] text-center px-4">
              <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-primary-200/40 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-gray-400 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Lade Ticket</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-primary-240">Nachrichten werden geladen…</p>
            </div>
          </div>
          
          <!-- Chat Input Area -->
          <div class="flex-shrink-0 bg-white dark:bg-primary-100 border-t border-gray-100 dark:border-primary-140 px-3 py-2 lg:px-4 lg:py-3 max-lg:pb-[max(0.5rem,env(safe-area-inset-bottom,0px))]" id="chatInputArea" style="display: none;">
            <input type="hidden" id="message-type-select" value="nachricht">

            <!-- Mobil: Eingabezeile + ausklappbare Aktionen unterhalb -->
            <div class="flex w-full flex-col gap-1 lg:hidden">
              <div class="flex w-full items-center gap-2">
                <button type="button" id="chat-mobile-plus-btn" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-700 transition-transform active:scale-95 dark:border-primary-140 dark:bg-primary-120 dark:text-primary-200 focus:outline-none focus:ring-2 focus:ring-primary-250/40" aria-expanded="false" aria-haspopup="true" aria-controls="chatMobileAttachMenu" title="Mehr" aria-label="Anhänge und Aktionen">
                  <svg id="chat-mobile-plus-icon" class="h-6 w-6 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </button>
                <div class="flex min-w-0 flex-1 items-center gap-1.5 rounded-2xl border border-gray-200 bg-gray-50 px-2 py-0.5 min-h-9 dark:border-primary-140 dark:bg-primary-120/80 focus-within:border-primary-250 focus-within:ring-2 focus-within:ring-primary-250/30 dark:focus-within:border-primary-250">
                  <label for="chat-message-input" class="sr-only">Nachricht schreiben</label>
                  <textarea id="chat-message-input" rows="1" class="block w-full min-w-0 flex-1 resize-none border-0 bg-transparent px-1 py-1 text-sm leading-5 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-primary-200 dark:placeholder-primary-240" placeholder="Nachricht schreiben…" style="min-height: 36px; height: 36px; max-height: 96px;" aria-label="Nachricht schreiben" data-chat-min-height="36" data-chat-max-height="96"></textarea>
                </div>
                <button type="button" id="send-message-btn" class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-500 text-white transition-transform active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-250/50 dark:bg-primary-250" aria-label="Senden" aria-hidden="true">
                  <svg class="h-5 w-5 rotate-90 rtl:-rotate-90" fill="currentColor" viewBox="0 0 18 20"><path d="m17.914 18.594-8-18a1 1 0 0 0-1.828 0l-8 18a1 1 0 0 0 1.157 1.376L8 18.281V9a1 1 0 0 1 2 0v9.281l6.758 1.689a1 1 0 0 0 1.156-1.376Z"/></svg>
                </button>
              </div>
              <div id="chatMobileAttachMenu" role="menu" aria-hidden="true" class="chat-mobile-plus-menu hidden grid-cols-3 gap-2.5 rounded-2xl bg-white px-2 py-3 dark:bg-primary-100">
                <button type="button" role="menuitem" data-chat-mobile-action="file" class="chat-mobile-menu-item flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center text-xs font-medium text-gray-800 transition-transform active:scale-95 dark:text-primary-200">
                  <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-200" aria-hidden="true">
                      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </span>
                  <span class="min-w-0 leading-tight">Mediathek</span>
                </button>
                <button type="button" role="menuitem" data-chat-mobile-action="camera" class="chat-mobile-menu-item flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center text-xs font-medium text-gray-800 transition-transform active:scale-95 dark:text-primary-200">
                  <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200" aria-hidden="true">
                      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  </span>
                  <span class="min-w-0 leading-tight">Kamera</span>
                </button>
                <button type="button" role="menuitem" data-chat-mobile-action="video" class="chat-mobile-menu-item flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center text-xs font-medium text-gray-800 transition-transform active:scale-95 dark:text-primary-200">
                  <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200" aria-hidden="true">
                      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0 1 21 8.618v6.764a1 1 0 0 1-1.447.894L15 14m-9 4h8a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2z"/></svg>
                  </span>
                  <span class="min-w-0 leading-tight">Video</span>
                </button>
                <button type="button" role="menuitem" data-chat-mobile-action="nachricht" class="chat-mobile-menu-item flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center text-xs font-medium text-gray-800 transition-transform active:scale-95 dark:text-primary-200">
                  <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-700 dark:bg-primary-120 dark:text-primary-200" aria-hidden="true">
                      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8m-8 4h5m-8 6 1.675-3.35A1 1 0 0 1 7.57 16H19a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h.382a1 1 0 0 1 .894.553L7 20z"/></svg>
                  </span>
                  <span class="min-w-0 leading-tight">Nachricht</span>
                </button>
                <button type="button" role="menuitem" data-chat-mobile-action="aufgabe" class="chat-mobile-menu-item flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center text-xs font-medium text-gray-800 transition-transform active:scale-95 dark:text-primary-200">
                  <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200" aria-hidden="true">
                      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 1 1 0-18c1.052 0 2.062.18 3 .512M7 9.577l3.923 3.923 8.5-8.5M17 14v6m-3-3h6"/></svg>
                  </span>
                  <span class="min-w-0 leading-tight">Aufgabe</span>
                </button>
                <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                <button type="button" role="menuitem" data-chat-mobile-action="loesung" class="chat-mobile-menu-item flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center text-xs font-medium text-gray-800 transition-transform active:scale-95 dark:text-primary-200">
                  <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200" aria-hidden="true">
                      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 6 2 2 4-4m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/></svg>
                  </span>
                  <span class="min-w-0 leading-tight">Lösung</span>
                </button>
                <?php endif; ?>
                <button type="button" role="menuitem" data-chat-mobile-action="bestellung" class="chat-mobile-menu-item flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center text-xs font-medium text-gray-800 transition-transform active:scale-95 dark:text-primary-200">
                  <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200" aria-hidden="true">
                      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/></svg>
                  </span>
                  <span class="min-w-0 leading-tight">Bestellung</span>
                </button>
              </div>
            </div>

            <!-- Desktop: bisherige Leiste -->
            <div class="service-chat-input-bar hidden items-center gap-2 rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 transition-all duration-200 dark:border-primary-140 dark:bg-primary-120/80 focus-within:border-primary-250 focus-within:ring-2 focus-within:ring-primary-250/30 dark:focus-within:border-primary-250 lg:flex">
              <div class="relative flex flex-shrink-0 items-center">
                <div class="inline-flex gap-0.5 rounded-xl border border-gray-200 p-0.5 dark:border-primary-200" role="group">
                  <button type="button" data-message-type="nachricht" data-tooltip-target="tooltip-nachricht" class="message-type-btn inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-600 transition-colors hover:bg-white hover:text-primary-600 focus:outline-none dark:text-primary-220 dark:hover:bg-primary-100 dark:hover:text-primary-250" title="Nachricht">
                    <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-5l-5 5v-5Z"/></svg>
                  </button>
                  <div id="tooltip-nachricht" role="tooltip" class="tooltip invisible absolute bottom-full left-0 z-10 mb-1 inline-block rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white opacity-0 shadow-sm transition-opacity duration-300 dark:bg-primary-800">Nachricht</div>
                  <button type="button" data-message-type="aufgabe" data-tooltip-target="tooltip-aufgabe" class="message-type-btn inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-600 transition-colors hover:bg-white hover:text-primary-600 focus:outline-none dark:text-primary-220 dark:hover:bg-primary-100 dark:hover:text-primary-250" title="Aufgabe">
                    <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 1 1 0-18c1.052 0 2.062.18 3 .512M7 9.577l3.923 3.923 8.5-8.5M17 14v6m-3-3h6"/></svg>
                  </button>
                  <div id="tooltip-aufgabe" role="tooltip" class="tooltip invisible absolute bottom-full left-0 z-10 mb-1 inline-block rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white opacity-0 shadow-sm transition-opacity duration-300 dark:bg-primary-800">Aufgabe</div>
                  <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                  <button type="button" data-message-type="loesung" data-tooltip-target="tooltip-loesung" class="message-type-btn inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-600 transition-colors hover:bg-white hover:text-primary-600 focus:outline-none dark:text-primary-220 dark:hover:bg-primary-100 dark:hover:text-primary-250" title="Lösung">
                    <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 6 2 2 4-4m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/></svg>
                  </button>
                  <div id="tooltip-loesung" role="tooltip" class="tooltip invisible absolute bottom-full left-0 z-10 mb-1 inline-block rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white opacity-0 shadow-sm transition-opacity duration-300 dark:bg-primary-800">Lösung</div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="flex min-w-0 flex-1 items-center gap-2">
                <button type="button" id="attach-file-btn" class="inline-flex shrink-0 items-center justify-center rounded-xl p-2 text-gray-500 transition-colors hover:bg-gray-200/60 hover:text-primary-600 dark:text-primary-240 dark:hover:bg-primary-140 dark:hover:text-primary-250" title="Datei anhängen" aria-label="Datei anhängen">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                </button>
                <button type="button" id="open-order-modal-btn" class="inline-flex shrink-0 items-center justify-center rounded-xl p-2 text-gray-500 transition-colors hover:bg-gray-200/60 hover:text-primary-600 dark:text-primary-240 dark:hover:bg-primary-140 dark:hover:text-primary-250" title="Bestellung anlegen" aria-label="Bestellung anlegen">
                  <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/></svg>
                </button>
                <label for="chat-message-input-desktop" class="sr-only">Nachricht schreiben</label>
                <textarea id="chat-message-input-desktop" rows="1" class="min-w-0 flex-1 resize-none border-0 bg-transparent px-1 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-primary-200 dark:placeholder-primary-240" placeholder="Nachricht schreiben…" style="min-height: 40px; height: 40px; max-height: 120px;" aria-label="Nachricht schreiben"></textarea>
                <button type="button" id="send-message-btn-desktop" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-500 text-white shadow-sm transition-all hover:bg-primary-600 hover:shadow focus:outline-none focus:ring-2 focus:ring-primary-250/50 dark:bg-primary-250 dark:hover:bg-primary-260" aria-label="Senden">
                  <svg class="h-5 w-5 rotate-90 rtl:-rotate-90" fill="currentColor" viewBox="0 0 18 20"><path d="m17.914 18.594-8-18a1 1 0 0 0-1.828 0l-8 18a1 1 0 0 0 1.157 1.376L8 18.281V9a1 1 0 0 1 2 0v9.281l6.758 1.689a1 1 0 0 0 1.156-1.376Z"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Ticket-Infos: Desktop rechte Spalte; mobil als Sheet unter Top-Nav (öffnet per Titel in der Nav) -->
        <div id="ticketInfoPanelRoot" class="ticket-info-panel-root lg:col-span-4 lg:sticky lg:top-4 lg:self-stretch min-h-0 lg:overflow-hidden flex flex-col lg:flex max-lg:fixed max-lg:left-0 max-lg:right-0 max-lg:bottom-0 max-lg:top-[calc(env(safe-area-inset-top,0px)+3.5rem)] max-lg:z-[56] max-lg:bg-gray-50 dark:max-lg:bg-primary-50 max-lg:translate-x-full max-lg:transition-transform max-lg:duration-200 max-lg:ease-out max-lg:pointer-events-none max-lg:shadow-[-12px_0_40px_-12px_rgba(15,23,42,0.12)] dark:max-lg:shadow-[-12px_0_48px_-12px_rgba(0,0,0,0.45)]">
          <div class="lg:hidden flex flex-shrink-0 items-center justify-between gap-3 border-b border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 px-4 py-3">
            <h2 class="text-base font-semibold text-gray-900 dark:text-primary-200">Ticket-Infos</h2>
            <button type="button" id="ticketMobileInfoCloseBtn" class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-primary-500/30" aria-label="Infos schließen">Schließen</button>
          </div>
          <div id="rightColumnScrollContainer" class="flex-1 min-h-0 custom-scrollbar relative overflow-y-auto lg:overflow-y-auto lg:overflow-x-hidden">
            <div id="ticketInfoContent" class="space-y-4" style="padding-bottom: 8rem;">
              <div class="text-center text-gray-500 dark:text-gray-400">
                <i class="fas fa-spinner fa-spin mr-2"></i> Lade Ticket...
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
  </div>
  </main>
</div>

<!-- Kontextmenü: Übersichts-Cards (Rechtsklick = gleiche Aktionen wie im aufgeklappten Panel) -->
<div id="overviewCardCtxMenu" class="hidden fixed z-[200] py-1 min-w-[13rem] max-w-[min(100vw-1rem,22rem)] rounded-lg border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-lg overflow-hidden" role="menu" aria-hidden="true"></div>

<!-- Modal für Datei-Upload (Datei anhängen) -->
<div id="attachmentUploadModal" tabindex="-1" aria-hidden="true" aria-labelledby="attachment-modal-title" role="dialog" aria-modal="true" class="hidden fixed inset-0 z-50 overflow-y-auto p-4">
    <!-- Overlay: Klick schließt Modal -->
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity cursor-pointer" aria-hidden="true" id="attachmentModalOverlay" onclick="closeAttachmentModal()"></div>
    <!-- Zentrierung -->
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-2xl relative z-10">
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden p-4 sm:p-5">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200 dark:border-primary-120">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200" id="attachment-modal-title">Datei anhängen</h3>
                    <button type="button" onclick="closeAttachmentModal()" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <!-- Body -->
                <div class="mb-4">
                    <span class="block mb-2 text-sm font-medium text-gray-900 dark:text-primary-200">Datei hochladen</span>
                    <div class="flex justify-center items-center w-full">
                        <label for="dropzone-file" id="dropzone-label" class="flex flex-col justify-center items-center w-full h-64 bg-gray-50 dark:bg-primary-300/50 rounded-base border-2 border-dashed border-gray-300 dark:border-primary-320 cursor-pointer hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors hover:border-primary-250 dark:hover:border-primary-250">
                            <div class="flex flex-col justify-center items-center pt-5 pb-6">
                                <svg class="mb-3 w-10 h-10 text-gray-400 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                <p class="mb-2 text-sm text-gray-500 dark:text-primary-210"><span class="font-semibold">Klicken zum Hochladen</span> oder Datei hierher ziehen</p>
                                <p class="text-xs text-gray-500 dark:text-primary-240" id="file-info">Alle Dateitypen erlaubt</p>
                            </div>
                            <input id="dropzone-file" type="file" class="hidden" accept="*/*" multiple>
                        </label>
                    </div>
                    <div id="selected-files-list" class="mt-4 space-y-2"></div>
                </div>
                <!-- Footer -->
                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-primary-120">
                    <button type="button" onclick="closeAttachmentModal()" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-base hover:bg-gray-50 dark:hover:bg-primary-140 focus:ring-2 focus:ring-primary-250/30 transition-colors">
                        <svg class="mr-1.5 w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        Abbrechen
                    </button>
                    <button type="button" onclick="uploadAttachment()" id="upload-btn" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-primary-250 dark:bg-primary-280 hover:bg-primary-260 dark:hover:bg-primary-270 focus:ring-2 focus:ring-primary-250/30 rounded-base transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        Hochladen
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bearbeitungszeit-Modal (Termin-Übersicht, nur Techniker & Admin) -->
<div id="bearbeitungszeitModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="flex items-center justify-center min-h-full px-4 py-8">
        <div class="relative w-full max-w-md">
            <div class="relative p-4 bg-white rounded-lg shadow dark:bg-gray-800 sm:p-5">
                <div class="flex justify-between items-center pb-4 mb-4 border-b dark:border-gray-600">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Bearbeitungszeit</h3>
                    <button type="button" onclick="closeBearbeitungszeitModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                        <span class="sr-only">Schließen</span>
                    </button>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Wie lange haben Sie an der Bearbeitung gearbeitet?</p>
                <div class="flex flex-wrap gap-2 mb-4" id="bearbeitungszeitPresets">
                    <button type="button" data-min="15" class="bearbeitungszeit-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">15 Min</button>
                    <button type="button" data-min="30" class="bearbeitungszeit-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">30 Min</button>
                    <button type="button" data-min="45" class="bearbeitungszeit-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">45 Min</button>
                    <button type="button" data-min="60" class="bearbeitungszeit-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">1 h</button>
                    <button type="button" data-min="90" class="bearbeitungszeit-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">1,5 h</button>
                    <button type="button" data-min="120" class="bearbeitungszeit-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">2 h</button>
                    <button type="button" data-min="180" class="bearbeitungszeit-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">3 h</button>
                </div>
                <div class="mb-4">
                    <label for="bearbeitungszeitCustom" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Eigene Eingabe (Minuten)</label>
                    <input type="number" id="bearbeitungszeitCustom" min="0" step="1" placeholder="z.B. 25" 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeBearbeitungszeitModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Abbrechen</button>
                    <button type="button" id="bearbeitungszeitConfirmBtn" onclick="confirmBearbeitungszeit()" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">Übernehmen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Betreff ändern (nur Admin) -->
<div id="editSubjectModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-[55] overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity cursor-pointer" aria-hidden="true" onclick="closeEditSubjectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full p-4 pointer-events-none">
        <div class="relative w-full max-w-md pointer-events-auto bg-white dark:bg-primary-100 rounded-xl shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
            <div class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Betreff ändern</h3>
                    <button type="button" onclick="closeEditSubjectModal()" class="p-1.5 rounded-lg text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <label for="editSubjectInput" class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Betreff</label>
                <input type="text" id="editSubjectInput" class="w-full px-3 py-2 border border-gray-300 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-120 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-primary-240" placeholder="Betreff des Tickets" maxlength="500" />
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeEditSubjectModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">Abbrechen</button>
                    <button type="button" id="editSubjectSaveBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-250 dark:hover:bg-primary-260 rounded-lg transition-colors">Speichern</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Anforderer-Details -->
<div id="anfordererModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-50 overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity cursor-pointer" aria-hidden="true" onclick="closeAnfordererModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full p-4 pointer-events-none">
        <div id="anfordererModalContent" class="relative w-full max-w-md pointer-events-auto bg-white dark:bg-primary-100 rounded-xl shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
            <div class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Anforderer</h3>
                    <button type="button" onclick="closeAnfordererModal()" class="p-1.5 rounded-lg text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div id="anfordererModalBody" class="space-y-3 text-sm">
                    <!-- Wird per JS befüllt -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ticket-History -->
<!-- Modal nur für Termin bearbeiten (Erstellen erfolgt über Expand-Panel in der Card) -->
<div id="editAppointmentModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-[60] overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity" onclick="closeAddAppointmentModal()"></div>
    <div class="relative mx-auto max-w-md w-full bg-white dark:bg-primary-100 rounded-lg shadow-card border border-gray-200 dark:border-primary-120">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Termin bearbeiten</h3>
                <button type="button" onclick="closeAddAppointmentModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-primary-210">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="saveAppointment(event)" class="space-y-4">
                <input type="hidden" id="appointmentId" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Titel (optional)</label>
                    <input type="text" id="appointmentTitle" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250" placeholder="z.B. Wartung, Reparatur">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Start</label>
                        <input type="datetime-local" id="appointmentStart" required class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Ende (optional)</label>
                        <input type="datetime-local" id="appointmentEnd" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                    </div>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeAddAppointmentModal()" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">Abbrechen</button>
                    <button type="submit" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-250 dark:hover:bg-primary-260 rounded-lg transition-colors">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobil: Termin hinzufügen als Bottom-Sheet -->
<div id="addAppointmentMobileModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-[70] lg:hidden">
    <div class="absolute inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity" onclick="closeAddAppointmentMobileModal()"></div>
    <div id="addAppointmentMobileSheet" class="absolute inset-x-0 bottom-0 translate-y-full transition-transform duration-300 ease-out rounded-t-2xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-card">
        <div class="mx-auto mt-2 h-1.5 w-12 rounded-full bg-gray-300 dark:bg-primary-240"></div>
        <div class="px-4 pb-[max(1rem,env(safe-area-inset-bottom,0px))] pt-3">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Termin hinzufügen</h3>
                <button type="button" onclick="closeAddAppointmentMobileModal()" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="saveNewAppointmentFromMobileModal(event)" class="space-y-3">
                <div>
                    <label for="newAppointmentMobileTitle" class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Titel (optional)</label>
                    <input type="text" id="newAppointmentMobileTitle" placeholder="z.B. Wartung, Reparatur" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                </div>
                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label for="newAppointmentMobileStart" class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Start</label>
                        <input type="datetime-local" id="newAppointmentMobileStart" required class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                    </div>
                    <div>
                        <label for="newAppointmentMobileEnd" class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Ende (optional)</label>
                        <input type="datetime-local" id="newAppointmentMobileEnd" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" onclick="closeAddAppointmentMobileModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">Abbrechen</button>
                    <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-250 dark:hover:bg-primary-260 rounded-lg transition-colors">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="ticketHistoryModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-[65] overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity" onclick="closeTicketHistoryModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-3xl relative z-10">
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
                <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-primary-120">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Ticket-History</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-primary-210">Alle Log-Einträge zu diesem Ticket.</p>
                    </div>
                    <button type="button" onclick="closeTicketHistoryModal()" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div id="ticket-history-list" class="max-h-[70vh] overflow-y-auto custom-scrollbar divide-y divide-gray-200 dark:divide-primary-120 bg-gray-50 dark:bg-primary-50/40">
                    <div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade History...</div>
                </div>
                <div class="flex justify-end p-5 border-t border-gray-200 dark:border-primary-120">
                    <button type="button" onclick="closeTicketHistoryModal()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-base hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">Schließen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ticket-Verknüpfung / Zusammenführen -->
<div id="mergeTicketModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-[60] overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity" onclick="closeMergeTicketModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-2xl relative z-10">
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
                <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-primary-120">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Ticket verknüpfen / ersetzen</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-primary-210">Wähle den Ziel-Ticket. Nachrichten, Bestellungen, Anhänge und Aufgaben werden dorthin übertragen. Standardmäßig erscheinen nur Aufträge derselben Firma ohne die Status <span class="whitespace-nowrap">Geschlossen</span> oder Archiv. Über die Optionen darunter kannst du alle Firmen oder geschlossene Aufträge einblenden; die Suche filtert innerhalb der geladenen Liste.</p>
                    </div>
                    <button type="button" onclick="closeMergeTicketModal()" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="flex flex-col gap-2.5 rounded-lg border border-gray-200 dark:border-primary-120 bg-gray-50/80 dark:bg-primary-50/30 px-3 py-3">
                        <p class="text-xs font-medium text-gray-700 dark:text-primary-210">Liste erweitern</p>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-800 dark:text-primary-200">
                            <input type="checkbox" id="merge-filter-all-companies" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-primary-320 dark:bg-primary-300 w-4 h-4 shrink-0">
                            <span>Alle Firmen anzeigen</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-800 dark:text-primary-200">
                            <input type="checkbox" id="merge-filter-include-closed" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-primary-320 dark:bg-primary-300 w-4 h-4 shrink-0">
                            <span>Geschlossen und Archiv einblenden</span>
                        </label>
                    </div>
                    <div>
                        <label for="merge-ticket-search" class="block mb-2 text-sm font-medium text-gray-900 dark:text-primary-200">Ticket suchen</label>
                        <input id="merge-ticket-search" type="text" placeholder="z. B. SRV-20260220-0001 oder Betreff"
                               class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div id="merge-ticket-list" class="max-h-80 overflow-y-auto custom-scrollbar rounded-base border border-gray-200 dark:border-primary-120 divide-y divide-gray-200 dark:divide-primary-120 bg-gray-50 dark:bg-primary-50/40">
                        <div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade Tickets...</div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-5 border-t border-gray-200 dark:border-primary-120">
                    <button type="button" onclick="closeMergeTicketModal()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-base hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">Abbrechen</button>
                    <button type="button" id="merge-ticket-confirm-btn" onclick="confirmMergeTicket()" disabled class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-250 dark:bg-primary-280 rounded-base hover:bg-primary-260 dark:hover:bg-primary-270 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Übertragen und ersetzen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mit Projekt verknüpfen -->
<div id="linkProjectModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-[60] overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity" onclick="closeLinkProjectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-2xl relative z-10">
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
                <div class="flex justify-between items-center p-5 border-b border-gray-200 dark:border-primary-120">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Mit Projekt verknüpfen</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-primary-210">Es werden nur Projekte der im Ticket ausgewählten Firma angezeigt. Wähle ein Projekt zur Verknüpfung.</p>
                    </div>
                    <button type="button" onclick="closeLinkProjectModal()" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label for="link-project-search" class="block mb-2 text-sm font-medium text-gray-900 dark:text-primary-200">Projekt suchen</label>
                        <input id="link-project-search" type="text" placeholder="z. B. Bezeichnung oder Beschreibung"
                               class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div id="link-project-list" class="max-h-80 overflow-y-auto custom-scrollbar rounded-base border border-gray-200 dark:border-primary-120 divide-y divide-gray-200 dark:divide-primary-120 bg-gray-50 dark:bg-primary-50/40">
                        <div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade Projekte...</div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-5 border-t border-gray-200 dark:border-primary-120">
                    <button type="button" onclick="closeLinkProjectModal()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-base hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">Abbrechen</button>
                    <button type="button" id="link-project-confirm-btn" onclick="confirmLinkProject()" disabled class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-250 dark:bg-primary-280 rounded-base hover:bg-primary-260 dark:hover:bg-primary-270 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Verknüpfen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Verbrauchsmaterialien für Bestellung (wenn Ticket ein Gerät hat) -->
<div id="orderConsumablesModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-[60] overflow-y-auto p-4">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity" onclick="closeOrderConsumablesModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg relative z-10">
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
                <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-primary-120">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Auswählbare Verbrauchsmaterialien</h3>
                    </div>
                    <button type="button" onclick="closeOrderConsumablesModal()" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-5">
                    <input type="text" id="order-consumables-search" placeholder="Suchen oder Bezeichnung eingeben" class="w-full mb-3 px-3 py-2 text-sm border border-gray-300 dark:border-primary-320 rounded-base bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                    <label class="flex items-center gap-2.5 mb-3 cursor-pointer select-none">
                        <input type="checkbox" id="order-garantie-cb" class="w-4 h-4 rounded border-gray-300 dark:border-primary-320 text-primary-600 dark:text-primary-250 focus:ring-primary-500/40">
                        <span class="text-sm text-gray-700 dark:text-primary-210">Bestellung läuft über <span class="font-medium">Garantie</span></span>
                    </label>
                    <div id="order-consumables-list" class="max-h-64 overflow-y-auto custom-scrollbar rounded-base border border-gray-200 dark:border-primary-120 divide-y divide-gray-200 dark:divide-primary-120 bg-gray-50 dark:bg-primary-50/40">
                        <div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade Verbrauchsmaterialien...</div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2 p-5 border-t border-gray-200 dark:border-primary-120">
                    <button type="button" id="order-consumables-apply-btn" onclick="applyOrderConsumables()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-250 dark:bg-primary-280 rounded-base hover:bg-primary-260 dark:hover:bg-primary-270 transition-colors">Bestellen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ticketId = <?php echo $ticketId; ?>;
const ticketsApiUrl = '<?php echo BASE_URL; ?>tickets/api/tickets.php';
const appointmentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/appointments.php';
const commentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/comments.php';
const consumablesApiUrl = '<?php echo BASE_URL; ?>inventory/api/consumables.php';
const commentAttachmentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/comment_attachments.php';
const ticketAttachmentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/attachments.php';
const serviceBaseUrl = '<?php echo BASE_URL; ?>';
const customersApiUrl = '<?php echo BASE_URL; ?>customers/api/customers.php';
const devicesApiUrl = '<?php echo BASE_URL; ?>devices/api/devices.php';
const companiesApiUrl = '<?php echo BASE_URL; ?>companies/api/companies.php';
const todosApiUrl = '<?php echo BASE_URL; ?>todos/api/todos.php';
const projectsApiUrl = '<?php echo BASE_URL; ?>projects/api/projects.php';
const userRole = '<?php echo addslashes($userRole); ?>';
const isAdminOrTech = (userRole === 'Admin' || userRole === 'Techniker');
const canSetAssignee = isAdminOrTech; // Kunde/Firmen-User/Firmen-Admin: kein Bearbeiter setzen
const canSetPlannedDate = isAdminOrTech; // Kunde/Firmen-User/Firmen-Admin: kein "Geplant"-Termin
const canEditObservers = (userRole !== 'Kunde'); // Firmen-User/Firmen-Admin/Techniker/Admin: Beobachter bearbeiten
const userCompanyId = <?php echo $userCompanyId ? (int)$userCompanyId : 'null'; ?>;
const currentUserId = parseInt(<?php echo $userId; ?>);
let isObserverOnly = false;
let selectedChatTicket = null;
let openBearbeitungszeitAfterAttachmentClose = false;
let selectedCommentIdForAttachment = null;
let selectedFiles = [];
let companies = [];
let customers = [];
let devices = [];
let assignableUsers = [];
let selectedObserversEdit = [];
let mergeTicketCandidates = [];
let filteredMergeTicketCandidates = [];
let selectedMergeTargetTicketId = null;
let linkProjectCandidates = [];
let filteredLinkProjectCandidates = [];
let selectedLinkProjectId = null;

function getChatMessageInputEl() {
    var mobile = document.getElementById('chat-message-input');
    var desk = document.getElementById('chat-message-input-desktop');
    if (typeof window.matchMedia === 'function' && window.matchMedia('(min-width: 1024px)').matches) {
        return desk || mobile;
    }
    return mobile || desk;
}

function getBothChatMessageInputs() {
    return [document.getElementById('chat-message-input'), document.getElementById('chat-message-input-desktop')].filter(Boolean);
}

function getChatTextareaMinMax(ta) {
    var minH = 40;
    var maxH = 120;
    if (ta) {
        if (ta.getAttribute('data-chat-min-height')) {
            minH = parseInt(ta.getAttribute('data-chat-min-height'), 10) || 40;
        }
        if (ta.getAttribute('data-chat-max-height')) {
            maxH = parseInt(ta.getAttribute('data-chat-max-height'), 10) || 120;
        }
    }
    return { minH: minH, maxH: maxH };
}

function syncChatMessageInputsFrom(sourceEl) {
    if (!sourceEl) return;
    var val = sourceEl.value;
    getBothChatMessageInputs().forEach(function(el) {
        if (el !== sourceEl) el.value = val;
    });
    updateChatMobileCameraSendToggle();
}

function updateChatMobileCameraSendToggle() {
    var mobileTa = document.getElementById('chat-message-input');
    var send = document.getElementById('send-message-btn');
    if (!mobileTa || !send) return;
    var hasText = mobileTa.value.trim().length > 0;
    if (hasText) {
        send.classList.remove('hidden');
        send.classList.add('flex');
        send.setAttribute('aria-hidden', 'false');
    } else {
        send.classList.add('hidden');
        send.classList.remove('flex');
        send.setAttribute('aria-hidden', 'true');
    }
}

/** Mobile Ticket-Chat: Leiste an den unteren sichtbaren Rand (Visual Viewport), Abstand zur Tastatur reduzieren */
function syncTicketMobileChatInputLayout() {
    var vv = window.visualViewport;
    var inputArea = document.getElementById('chatInputArea');
    var content = document.getElementById('chatTicketContent');
    if (!inputArea || !document.body.classList.contains('ticket-view-mobile-shell') || !document.body.classList.contains('service-mobile-fullscreen')) {
        return;
    }
    if (window.innerWidth >= 1024) {
        inputArea.style.bottom = '';
        inputArea.classList.remove('ticket-chat-input-keyboard-open');
        if (content) content.style.removeProperty('padding-bottom');
        return;
    }
    if (inputArea.style.display === 'none') {
        inputArea.style.bottom = '';
        inputArea.classList.remove('ticket-chat-input-keyboard-open');
        if (content) content.style.removeProperty('padding-bottom');
        return;
    }
    if (vv) {
        var gap = Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop));
        if (gap > 0) {
            inputArea.style.bottom = gap + 'px';
        } else {
            inputArea.style.bottom = '';
        }
        inputArea.classList.toggle('ticket-chat-input-keyboard-open', gap > 56);
    }
    if (content) {
        var h = inputArea.getBoundingClientRect().height;
        if (h > 0) {
            content.style.setProperty('padding-bottom', Math.ceil(h + 24) + 'px', 'important');
        }
    }
}

function resetDropzoneFileInputAttrs() {
    var fi = document.getElementById('dropzone-file');
    if (!fi) return;
    fi.setAttribute('accept', '*/*');
    fi.removeAttribute('capture');
    fi.setAttribute('multiple', 'multiple');
}

function closeChatMobilePlusMenu() {
    var menu = document.getElementById('chatMobileAttachMenu');
    var plus = document.getElementById('chat-mobile-plus-btn');
    var icon = document.getElementById('chat-mobile-plus-icon');
    if (menu) {
        menu.classList.add('hidden');
        menu.classList.remove('grid');
        menu.setAttribute('aria-hidden', 'true');
    }
    if (plus) plus.setAttribute('aria-expanded', 'false');
    if (icon) icon.classList.remove('rotate-45');
    if (typeof syncTicketMobileChatInputLayout === 'function') {
        syncTicketMobileChatInputLayout();
    }
}

function openMobileAttachmentPicker(mode) {
    if (!selectedChatTicket) return;
    closeChatMobilePlusMenu();
    clearSelectedFile();

    // iOS: temporären Input direkt aus dem User-Tap heraus erzeugen.
    // Das reduziert zusätzliche Quell-Abfragen gegenüber einem bereits vorhandenen, versteckten Input im Modal.
    var picker = document.createElement('input');
    picker.type = 'file';
    picker.style.position = 'fixed';
    picker.style.left = '-9999px';
    picker.style.top = '0';

    if (mode === 'photo') {
        picker.accept = 'image/*,video/*';
        picker.multiple = false;
    } else if (mode === 'camera') {
        picker.accept = 'image/*';
        picker.setAttribute('capture', 'environment');
        picker.multiple = false;
    } else if (mode === 'video') {
        picker.accept = 'video/*';
        picker.setAttribute('capture', 'environment');
        picker.multiple = false;
    } else {
        picker.accept = '.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,application/*,text/*';
        picker.multiple = true;
    }

    picker.addEventListener('change', function() {
        if (!picker.files || picker.files.length === 0) {
            if (picker.parentNode) picker.parentNode.removeChild(picker);
            return;
        }
        handleFileSelect(picker.files);
        uploadAttachment();
        if (picker.parentNode) picker.parentNode.removeChild(picker);
    }, { once: true });

    document.body.appendChild(picker);
    picker.click();
}

function closeTicketHistoryModal() {
    const modal = document.getElementById('ticketHistoryModal');
    if (modal) modal.classList.add('hidden');
}

function openEditSubjectModal() {
    const modal = document.getElementById('editSubjectModal');
    const input = document.getElementById('editSubjectInput');
    if (!modal || !input) return;
    input.value = (selectedChatTicket && selectedChatTicket.titel) ? String(selectedChatTicket.titel) : '';
    modal.classList.remove('hidden');
    setTimeout(function() { input.focus(); }, 100);
}

function closeEditSubjectModal() {
    const modal = document.getElementById('editSubjectModal');
    if (modal) modal.classList.add('hidden');
}

function formatHistoryDate(value) {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return escapeHtml(String(value));
    return d.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

async function openTicketHistoryModal() {
    const modal = document.getElementById('ticketHistoryModal');
    const list = document.getElementById('ticket-history-list');
    if (!modal || !list) return;
    modal.classList.remove('hidden');
    list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade History...</div>';

    try {
        const response = await fetch(ticketsApiUrl + '?action=history&id=' + encodeURIComponent(ticketId));
        const data = await response.json();
        if (!data || !data.success || !Array.isArray(data.logs)) {
            throw new Error((data && data.error) ? data.error : 'History konnte nicht geladen werden');
        }
        if (!data.logs.length) {
            list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Keine Änderungen vorhanden</div>';
            return;
        }
        list.innerHTML = data.logs.map(function(row) {
            const action = escapeHtml(row.action || '');
            const userName = escapeHtml(row.user_name || 'Unbekannt');
            const created = formatHistoryDate(row.erstellt_datum || '');
            const beschreibung = row.beschreibung ? escapeHtml(row.beschreibung) : '';
            const fieldName = row.field_name ? escapeHtml(row.field_name) : '';
            const oldValue = row.old_value !== null && row.old_value !== undefined && row.old_value !== '' ? escapeHtml(String(row.old_value)) : '';
            const newValue = row.new_value !== null && row.new_value !== undefined && row.new_value !== '' ? escapeHtml(String(row.new_value)) : '';
            const changes = [];
            if (fieldName) changes.push('<span><strong>Feld:</strong> ' + fieldName + '</span>');
            if (oldValue) changes.push('<span><strong>Alt:</strong> ' + oldValue + '</span>');
            if (newValue) changes.push('<span><strong>Neu:</strong> ' + newValue + '</span>');

            return '' +
                '<div class="px-4 py-3 bg-white dark:bg-transparent">' +
                    '<div class="flex items-center justify-between gap-3">' +
                        '<div class="flex items-center gap-2 min-w-0">' +
                            '<span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-210">' + action + '</span>' +
                            '<p class="text-sm font-medium text-gray-900 dark:text-primary-200 truncate">' + userName + '</p>' +
                        '</div>' +
                        '<p class="text-xs text-gray-500 dark:text-primary-240 flex-shrink-0">' + created + '</p>' +
                    '</div>' +
                    (beschreibung ? '<p class="mt-1 text-sm text-gray-700 dark:text-primary-210 break-words">' + beschreibung + '</p>' : '') +
                    (changes.length ? '<p class="mt-1 text-xs text-gray-600 dark:text-primary-230 break-words">' + changes.join(' | ') + '</p>' : '') +
                '</div>';
        }).join('');
    } catch (e) {
        list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-red-600 dark:text-red-400">Fehler beim Laden der History</div>';
        if (typeof showToast === 'function') showToast('Fehler beim Laden der History', 'error');
    }
}

function closeMoreOptionsDropdown() {
    const menu = document.getElementById('more-options-dropdown-menu');
    const button = document.getElementById('more-options-dropdown-button');
    menu?.classList.add('hidden');
    button?.setAttribute('aria-expanded', 'false');
}

function updatePinOptionLabel() {
    const label = document.getElementById('option-toggle-pin-label');
    if (!label) return;
    const pinned = selectedChatTicket && (selectedChatTicket.is_pinned === 1 || selectedChatTicket.is_pinned === '1' || selectedChatTicket.is_pinned === true);
    label.textContent = pinned ? 'Loslösen' : 'Anheften';
}

async function copyToClipboard(text) {
    if (!text) return false;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch (e) {}
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'absolute';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    const ok = document.execCommand('copy');
    document.body.removeChild(ta);
    return ok;
}

function closeMergeTicketModal() {
    const modal = document.getElementById('mergeTicketModal');
    if (modal) modal.classList.add('hidden');
    selectedMergeTargetTicketId = null;
}

function mergeModalFilterSummaryForEmptyState() {
    const allCo = document.getElementById('merge-filter-all-companies');
    const incl = document.getElementById('merge-filter-include-closed');
    const allCompanies = !!(allCo && allCo.checked);
    const includeClosed = !!(incl && incl.checked);
    return (allCompanies ? 'alle Firmen' : 'gleiche Firma') + ', ' + (includeClosed ? 'inkl. Geschlossen/Archiv' : 'ohne Geschlossen/Archiv');
}

async function loadMergeTicketCandidates() {
    selectedMergeTargetTicketId = null;
    const list = document.getElementById('merge-ticket-list');
    const searchInput = document.getElementById('merge-ticket-search');
    if (list) {
        list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade Tickets...</div>';
    }
    try {
        let url = ticketsApiUrl + '?for_merge=1&merge_source_ticket_id=' + encodeURIComponent(String(ticketId));
        if (document.getElementById('merge-filter-all-companies')?.checked) {
            url += '&merge_all_companies=1';
        }
        if (document.getElementById('merge-filter-include-closed')?.checked) {
            url += '&merge_include_closed=1';
        }
        const response = await fetch(url);
        const data = await response.json();
        if (!data || !data.success || !Array.isArray(data.tickets)) {
            throw new Error((data && data.error) ? data.error : 'Tickets konnten nicht geladen werden');
        }
        mergeTicketCandidates = data.tickets;
        filterMergeTicketCandidates(searchInput ? (searchInput.value || '') : '');
    } catch (e) {
        if (list) {
            list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-red-600 dark:text-red-400">Fehler beim Laden der Tickets</div>';
        }
        mergeTicketCandidates = [];
        filteredMergeTicketCandidates = [];
        const confirmBtn = document.getElementById('merge-ticket-confirm-btn');
        if (confirmBtn) confirmBtn.disabled = true;
        if (typeof showToast === 'function') showToast('Fehler beim Laden der Tickets', 'error');
    }
}

function renderMergeTicketList() {
    const list = document.getElementById('merge-ticket-list');
    const confirmBtn = document.getElementById('merge-ticket-confirm-btn');
    if (!list) return;
    if (confirmBtn) confirmBtn.disabled = !selectedMergeTargetTicketId;

    if (!filteredMergeTicketCandidates.length) {
        const hint = escapeHtml(mergeModalFilterSummaryForEmptyState());
        list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Keine passenden Tickets (Filter: ' + hint + '). Suchbegriff anpassen oder Optionen ändern.</div>';
        return;
    }

    list.innerHTML = filteredMergeTicketCandidates.map(function(ticket) {
        const active = Number(selectedMergeTargetTicketId) === Number(ticket.id);
        const classes = active
            ? 'bg-primary-50 dark:bg-primary-900/20 border-primary-300 dark:border-primary-250 ring-2 ring-primary-300/40 dark:ring-primary-250/40'
            : 'bg-white dark:bg-transparent border-transparent hover:bg-gray-100 dark:hover:bg-primary-140';
        const ticketNum = escapeHtml(ticket.ticket_nummer || ('#' + ticket.id));
        const title = escapeHtml(ticket.titel || '(ohne Betreff)');
        const company = escapeHtml(ticket.company_name || '');
        const customer = escapeHtml(ticket.customer_name || '');
        const companyCustomer = customer ? (company ? (company + ' / ' + customer) : customer) : company;
        return '' +
            '<button type="button" class="merge-ticket-item w-full text-left p-3 border transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 ' + classes + '" data-ticket-id="' + ticket.id + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
                '<div class="flex items-start justify-between gap-2">' +
                    '<div class="min-w-0">' +
                        '<p class="text-sm font-semibold text-gray-900 dark:text-primary-200 truncate">' + title + '</p>' +
                        '<p class="text-xs text-gray-500 dark:text-primary-240 truncate">' + ticketNum + '</p>' +
                        (companyCustomer ? '<p class="text-xs text-gray-600 dark:text-primary-220 truncate mt-0.5">' + companyCustomer + '</p>' : '') +
                    '</div>' +
                    '<div class="flex items-center gap-2 flex-shrink-0">' +
                        '<span class="text-sm px-3 py-1 rounded-full ' + getStatusBadgeClass(ticket.status) + '">' + getStatusText(ticket.status) + '</span>' +
                        '<span class="w-5 h-5 inline-flex items-center justify-center rounded-full border ' + (active ? 'border-primary-500 bg-primary-500 text-white' : 'border-gray-300 dark:border-primary-320 text-transparent') + '">' +
                            '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>' +
                        '</span>' +
                    '</div>' +
                '</div>' +
            '</button>';
    }).join('');

    list.querySelectorAll('.merge-ticket-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            selectedMergeTargetTicketId = parseInt(this.getAttribute('data-ticket-id'), 10);
            renderMergeTicketList();
        });
    });
}

function filterMergeTicketCandidates(query) {
    const q = (query || '').trim().toLowerCase();
    filteredMergeTicketCandidates = mergeTicketCandidates.filter(function(ticket) {
        if (Number(ticket.id) === Number(ticketId)) return false;
        if (!q) return true;
        const haystack = [
            ticket.ticket_nummer || '',
            ticket.titel || '',
            ticket.company_name || '',
            ticket.customer_name || ''
        ].join(' ').toLowerCase();
        return haystack.includes(q);
    });
    renderMergeTicketList();
}

async function openMergeTicketModal() {
    const modal = document.getElementById('mergeTicketModal');
    const searchInput = document.getElementById('merge-ticket-search');
    const allCompaniesCb = document.getElementById('merge-filter-all-companies');
    const includeClosedCb = document.getElementById('merge-filter-include-closed');
    if (!modal || !searchInput) return;
    selectedMergeTargetTicketId = null;
    if (allCompaniesCb) allCompaniesCb.checked = false;
    if (includeClosedCb) includeClosedCb.checked = false;
    modal.classList.remove('hidden');
    searchInput.value = '';
    searchInput.focus();
    await loadMergeTicketCandidates();
}

async function confirmMergeTicket() {
    if (!selectedMergeTargetTicketId) {
        if (typeof showToast === 'function') showToast('Bitte zuerst einen Ziel-Ticket auswählen', 'error');
        return;
    }
    if (!confirm('Soll dieser Ticket wirklich in den ausgewählten Ticket übertragen und anschließend soft-gelöscht werden?')) {
        return;
    }
    try {
        const response = await fetch(ticketsApiUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                merge_into_ticket_id: selectedMergeTargetTicketId
            })
        });
        const data = await response.json();
        if (data && data.success) {
            closeMergeTicketModal();
            if (typeof showToast === 'function') showToast('Ticket erfolgreich übertragen', 'success');
            const redirectId = data.target_ticket_id ? parseInt(data.target_ticket_id, 10) : selectedMergeTargetTicketId;
            window.location.href = serviceBaseUrl + 'tickets/view.php?id=' + encodeURIComponent(redirectId);
            return;
        }
        if (typeof showToast === 'function') showToast((data && data.error) ? data.error : 'Übertragen fehlgeschlagen', 'error');
    } catch (e) {
        if (typeof showToast === 'function') showToast('Fehler beim Übertragen', 'error');
    }
}

function closeLinkProjectModal() {
    const modal = document.getElementById('linkProjectModal');
    if (modal) modal.classList.add('hidden');
    selectedLinkProjectId = null;
}

function renderLinkProjectList() {
    const list = document.getElementById('link-project-list');
    const confirmBtn = document.getElementById('link-project-confirm-btn');
    if (!list) return;
    if (confirmBtn) confirmBtn.disabled = !selectedLinkProjectId;

    if (!filteredLinkProjectCandidates.length) {
        list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Keine Projekte gefunden</div>';
        return;
    }

    list.innerHTML = filteredLinkProjectCandidates.map(function(project) {
        const active = Number(selectedLinkProjectId) === Number(project.id);
        const classes = active
            ? 'bg-primary-50 dark:bg-primary-900/20 border-primary-300 dark:border-primary-250 ring-2 ring-primary-300/40 dark:ring-primary-250/40'
            : 'bg-white dark:bg-transparent border-transparent hover:bg-gray-100 dark:hover:bg-primary-140';
        const bezeichnung = escapeHtml(project.bezeichnung || '(ohne Bezeichnung)');
        const company = escapeHtml(project.company_name || '');
        const status = escapeHtml(project.status || '');
        return '' +
            '<button type="button" class="link-project-item w-full text-left p-3 border transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 ' + classes + '" data-project-id="' + project.id + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
                '<div class="flex items-start justify-between gap-2">' +
                    '<div class="min-w-0">' +
                        '<p class="text-sm font-semibold text-gray-900 dark:text-primary-200 truncate">' + bezeichnung + '</p>' +
                        (company ? '<p class="text-xs text-gray-500 dark:text-primary-240 truncate">' + company + '</p>' : '') +
                    '</div>' +
                    '<div class="flex items-center gap-2 flex-shrink-0">' +
                        (status ? '<span class="text-xs px-2 py-1 rounded-full bg-gray-200 dark:bg-primary-200 text-gray-700 dark:text-primary-220">' + status + '</span>' : '') +
                        '<span class="w-5 h-5 inline-flex items-center justify-center rounded-full border ' + (active ? 'border-primary-500 bg-primary-500 text-white' : 'border-gray-300 dark:border-primary-320 text-transparent') + '">' +
                            '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>' +
                        '</span>' +
                    '</div>' +
                '</div>' +
            '</button>';
    }).join('');

    list.querySelectorAll('.link-project-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            selectedLinkProjectId = parseInt(this.getAttribute('data-project-id'), 10);
            renderLinkProjectList();
        });
    });
}

function filterLinkProjectCandidates(query) {
    const q = (query || '').trim().toLowerCase();
    filteredLinkProjectCandidates = linkProjectCandidates.filter(function(project) {
        if (!q) return true;
        const haystack = [
            project.bezeichnung || '',
            project.beschreibung || '',
            project.company_name || '',
            project.customer_name || '',
            project.status || ''
        ].join(' ').toLowerCase();
        return haystack.includes(q);
    });
    renderLinkProjectList();
}

async function openLinkProjectModal() {
    const modal = document.getElementById('linkProjectModal');
    const searchInput = document.getElementById('link-project-search');
    if (!modal || !searchInput) return;
    selectedLinkProjectId = null;
    modal.classList.remove('hidden');
    searchInput.value = '';
    searchInput.focus();

    const list = document.getElementById('link-project-list');
    const companyId = (selectedChatTicket && selectedChatTicket.company_id) ? parseInt(selectedChatTicket.company_id, 10) : null;
    if (!companyId) {
        if (list) {
            list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Dem Ticket ist keine Firma zugeordnet. Bitte ordnen Sie zuerst eine Firma zu, um Projekte dieser Firma anzuzeigen.</div>';
        }
        linkProjectCandidates = [];
        filteredLinkProjectCandidates = [];
        renderLinkProjectList();
        return;
    }

    if (list) {
        list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade Projekte der Firma...</div>';
    }
    try {
        const url = projectsApiUrl + '?company_id=' + encodeURIComponent(companyId);
        const response = await fetch(url);
        const data = await response.json();
        if (!data || !data.success || !Array.isArray(data.projects)) {
            throw new Error((data && data.error) ? data.error : 'Projekte konnten nicht geladen werden');
        }
        linkProjectCandidates = data.projects;
        filterLinkProjectCandidates('');
    } catch (e) {
        if (list) {
            list.innerHTML = '<div class="px-4 py-6 text-sm text-center text-red-600 dark:text-red-400">Fehler beim Laden der Projekte</div>';
        }
        if (typeof showToast === 'function') showToast('Fehler beim Laden der Projekte', 'error');
    }
}

async function confirmLinkProject() {
    if (!selectedLinkProjectId) {
        if (typeof showToast === 'function') showToast('Bitte zuerst ein Projekt auswählen', 'error');
        return;
    }
    try {
        const response = await fetch(projectsApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'link_ticket',
                project_id: selectedLinkProjectId,
                ticket_id: ticketId
            })
        });
        const data = await response.json();
        if (data && data.success) {
            closeLinkProjectModal();
            if (typeof showToast === 'function') showToast('Ticket mit Projekt verknüpft', 'success');
            window.location.reload();
            return;
        }
        if (typeof showToast === 'function') showToast((data && data.error) ? data.error : 'Verknüpfen fehlgeschlagen', 'error');
    } catch (e) {
        if (typeof showToast === 'function') showToast('Fehler beim Verknüpfen', 'error');
    }
}

async function unlinkProject() {
    if (!selectedChatTicket || !selectedChatTicket.id) {
        if (typeof showToast === 'function') showToast('Ticketdaten noch nicht geladen', 'error');
        return;
    }
    if (!selectedChatTicket.projects || !selectedChatTicket.projects.length) {
        if (typeof showToast === 'function') showToast('Kein Projekt zugeordnet', 'error');
        return;
    }
    if (!confirm('Möchten Sie das Projekt wirklich vom Ticket lösen?')) {
        return;
    }
    try {
        const response = await fetch(ticketsApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'unlink_project',
                ticket_id: selectedChatTicket.id
            })
        });
        const data = await response.json();
        if (data && data.success) {
            if (typeof showToast === 'function') showToast('Projekt erfolgreich gelöst', 'success');
            window.location.reload();
            return;
        }
        if (typeof showToast === 'function') showToast((data && data.error) ? data.error : 'Lösen fehlgeschlagen', 'error');
    } catch (e) {
        if (typeof showToast === 'function') showToast('Fehler beim Lösen', 'error');
    }
}

function updateLinkProjectButton() {
    const btn = document.getElementById('option-link-project');
    const textSpan = document.getElementById('option-link-project-text');
    if (!btn || !textSpan) return;
    
    const hasProject = selectedChatTicket && selectedChatTicket.projects && selectedChatTicket.projects.length > 0;
    if (hasProject) {
        textSpan.textContent = 'Loslösen';
    } else {
        textSpan.textContent = 'Mit Projekt verknüpfen';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadTicket();
    initOverviewCardContextMenu();
    initOverviewAccordionRowClick();

    (function initTicketMobileNavInfo() {
        const openBtn = document.getElementById('ticketNavOpenInfoBtn');
        const closeBtn = document.getElementById('ticketMobileInfoCloseBtn');
        const backdrop = document.getElementById('ticketMobileInfoBackdrop');
        if (openBtn) {
            openBtn.addEventListener('click', function() { openTicketMobileInfoPanel(); });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function() { closeTicketMobileInfoPanel(); });
        }
        if (backdrop) {
            backdrop.addEventListener('click', function() { closeTicketMobileInfoPanel(); });
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.body.classList.contains('ticket-mobile-info-open')) {
                closeTicketMobileInfoPanel();
            }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) closeTicketMobileInfoPanel();
        });
    })();

    (function initTicketMobileChatVisualViewport() {
        var vv = window.visualViewport;
        if (!vv || !document.body.classList.contains('ticket-view-mobile-shell')) return;
        function onVv() {
            syncTicketMobileChatInputLayout();
        }
        vv.addEventListener('resize', onVv);
        vv.addEventListener('scroll', onVv);
        window.addEventListener('resize', onVv);
        requestAnimationFrame(onVv);
    })();

    // Nachrichtentyp-Button-Auswahl Event Listener
    const messageTypeButtons = document.querySelectorAll('.message-type-btn');
    const messageTypeSelect = document.getElementById('message-type-select');
    const chatInputsAll = getBothChatMessageInputs();

    function updatePlaceholder(messageType) {
        const placeholders = {
            'nachricht': 'Nachricht schreiben...',
            'loesung': 'Lösung beschreiben...',
            'aufgabe': 'Aufgabe beschreiben...'
        };
        const p = placeholders[messageType] || placeholders['nachricht'];
        chatInputsAll.forEach(function(el) {
            if (el) el.placeholder = p;
        });
    }

    function updateActiveButton(activeButton) {
        messageTypeButtons.forEach(btn => {
            btn.classList.remove('bg-primary-500', 'text-white', 'dark:bg-primary-250', 'dark:text-white', 'shadow-sm');
            btn.classList.add('text-gray-600', 'dark:text-primary-220', 'hover:text-primary-600', 'dark:hover:text-primary-250', 'bg-transparent', 'hover:bg-white', 'dark:hover:bg-primary-100');
        });

        if (activeButton) {
            activeButton.classList.add('bg-primary-500', 'text-white', 'dark:bg-primary-250', 'dark:text-white', 'shadow-sm');
            activeButton.classList.remove('text-gray-600', 'dark:text-primary-220', 'hover:text-primary-600', 'dark:hover:text-primary-250', 'bg-transparent', 'hover:bg-white', 'dark:hover:bg-primary-100');
        }
    }

    function syncMobileMessageTypeMenuVisibility(messageType) {
        var menu = document.getElementById('chatMobileAttachMenu');
        if (!menu) return;
        var type = messageType || (messageTypeSelect ? messageTypeSelect.value : 'nachricht');
        var isTask = type === 'aufgabe';
        var isSolution = type === 'loesung';

        var resetItem = menu.querySelector('[data-chat-mobile-action="nachricht"]');
        var taskItem = menu.querySelector('[data-chat-mobile-action="aufgabe"]');
        var solutionItem = menu.querySelector('[data-chat-mobile-action="loesung"]');

        if (resetItem) resetItem.classList.toggle('hidden', !(isTask || isSolution));
        if (taskItem) taskItem.classList.toggle('hidden', isTask);
        if (solutionItem) solutionItem.classList.toggle('hidden', isSolution);
    }

    function setChatMessageType(messageType, shouldFocus) {
        var type = messageType || 'nachricht';
        if (messageTypeSelect) {
            messageTypeSelect.value = type;
        }
        var activeButton = document.querySelector('.message-type-btn[data-message-type="' + type + '"]');
        updateActiveButton(activeButton || null);
        updatePlaceholder(type);
        syncMobileMessageTypeMenuVisibility(type);
        if (shouldFocus) {
            var ta = getChatMessageInputEl();
            if (ta) ta.focus();
        }
    }

    messageTypeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const messageType = this.getAttribute('data-message-type');
            setChatMessageType(messageType, true);
        });
    });

    if (messageTypeButtons.length > 0) {
        const firstButton = Array.from(messageTypeButtons).find(btn => btn.getAttribute('data-message-type') === 'nachricht');
        if (firstButton) {
            setChatMessageType('nachricht', false);
        }
    }

    function resizeChatInputFor(ta) {
        if (!ta) return;
        var mm = getChatTextareaMinMax(ta);
        ta.style.height = 'auto';
        var newHeight = Math.min(Math.max(ta.scrollHeight, mm.minH), mm.maxH);
        ta.style.height = newHeight + 'px';
        syncTicketMobileChatInputLayout();
    }

    chatInputsAll.forEach(function(chatMessageInput) {
        if (!chatMessageInput) return;
        chatMessageInput.addEventListener('input', function() {
            syncChatMessageInputsFrom(this);
            resizeChatInputFor(this);
        });
        chatMessageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
        chatMessageInput.addEventListener('paste', function() {
            var self = this;
            setTimeout(function() { syncChatMessageInputsFrom(self); resizeChatInputFor(self); }, 0);
        });
        chatMessageInput.addEventListener('blur', function() {
            var mm = getChatTextareaMinMax(this);
            if (this.value.trim() === '') {
                this.style.height = 'auto';
                this.style.height = mm.minH + 'px';
                syncTicketMobileChatInputLayout();
            } else {
                resizeChatInputFor(this);
            }
        });
    });

    const sendMessageBtn = document.getElementById('send-message-btn');
    const sendMessageBtnDesktop = document.getElementById('send-message-btn-desktop');
    [sendMessageBtn, sendMessageBtnDesktop].filter(Boolean).forEach(function(btn) {
        btn.addEventListener('click', sendChatMessage);
    });

    const attachFileBtn = document.getElementById('attach-file-btn');
    
    // Bestellung-Button: Modal öffnen (Bestellung nur noch über Modal, nicht über Eingabefeld)
    const openOrderModalBtn = document.getElementById('open-order-modal-btn');
    if (openOrderModalBtn) {
        openOrderModalBtn.addEventListener('click', function() {
            if (!selectedChatTicket) return;
            openOrderConsumablesModal();
        });
    }

    // Anhang-Button Event Listener
    if (attachFileBtn) {
        attachFileBtn.addEventListener('click', function() {
            if (!selectedChatTicket) return;
            
            const messageInput = getChatMessageInputEl();
            const messageTypeSelect = document.getElementById('message-type-select');
            const message = messageInput ? messageInput.value.trim() : '';
            const nachrichtentyp = messageTypeSelect ? messageTypeSelect.value : 'nachricht';
            
            if (message) {
                fetch(commentsApiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ticket_id: selectedChatTicket.id,
                        kommentar: message,
                        nachrichtentyp: nachrichtentyp,
                        ist_intern: 0
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.comment_id) {
                        getBothChatMessageInputs().forEach(function(el) {
                            el.value = '';
                            var mm = getChatTextareaMinMax(el);
                            el.style.height = 'auto';
                            el.style.height = mm.minH + 'px';
                        });
                        updateChatMobileCameraSendToggle();
                        syncTicketMobileChatInputLayout();
                        if (nachrichtentyp === 'loesung' && isAdminOrTech) {
                            openBearbeitungszeitAfterAttachmentClose = true;
                        }
                        openAttachmentModal(data.comment_id);
                        if (nachrichtentyp === 'loesung' || nachrichtentyp === 'bestellung') {
                            loadTicket();
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast('Fehler beim Erstellen des Kommentars: ' + (data.error || 'Unbekannter Fehler'), 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Erstellen des Kommentars:', error);
                    if (typeof showToast === 'function') {
                        showToast('Fehler beim Erstellen des Kommentars', 'error');
                    }
                });
            } else {
                // Nur Anhang: Kein Kommentar vorab erstellen (vermeidet leere Nachricht), Modal direkt öffnen
                openAttachmentModal(null);
            }
        });
    }

    (function initChatMobilePlusMenu() {
        var plusBtn = document.getElementById('chat-mobile-plus-btn');
        var menu = document.getElementById('chatMobileAttachMenu');
        var icon = document.getElementById('chat-mobile-plus-icon');
        var swipeStartX = 0;
        var swipeStartY = 0;
        var swipeTracking = false;
        if (!plusBtn || !menu) return;
        plusBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var hidden = menu.classList.contains('hidden');
            if (hidden) {
                syncMobileMessageTypeMenuVisibility(messageTypeSelect ? messageTypeSelect.value : 'nachricht');
                menu.classList.remove('hidden');
                menu.classList.add('grid');
                menu.setAttribute('aria-hidden', 'false');
                plusBtn.setAttribute('aria-expanded', 'true');
                if (icon) icon.classList.add('rotate-45');
                syncTicketMobileChatInputLayout();
            } else {
                closeChatMobilePlusMenu();
            }
        });
        document.addEventListener('click', function(e) {
            if (menu.classList.contains('hidden')) return;
            if (menu.contains(e.target) || plusBtn.contains(e.target)) return;
            closeChatMobilePlusMenu();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
                closeChatMobilePlusMenu();
            }
        });
        menu.addEventListener('touchstart', function(e) {
            if (menu.classList.contains('hidden')) return;
            if (!e.touches || e.touches.length !== 1) return;
            swipeStartX = e.touches[0].clientX;
            swipeStartY = e.touches[0].clientY;
            swipeTracking = true;
        }, { passive: true });
        menu.addEventListener('touchmove', function(e) {
            if (!swipeTracking) return;
            if (!e.touches || e.touches.length !== 1) {
                swipeTracking = false;
                return;
            }
            var dx = e.touches[0].clientX - swipeStartX;
            var dy = e.touches[0].clientY - swipeStartY;
            if (Math.abs(dx) > 56 || dy < -24) {
                swipeTracking = false;
            }
        }, { passive: true });
        menu.addEventListener('touchend', function(e) {
            if (!swipeTracking) return;
            swipeTracking = false;
            if (!e.changedTouches || e.changedTouches.length !== 1) return;
            var dx = e.changedTouches[0].clientX - swipeStartX;
            var dy = e.changedTouches[0].clientY - swipeStartY;
            if (dy > 64 && Math.abs(dx) < 56 && dy > Math.abs(dx) * 1.2) {
                closeChatMobilePlusMenu();
            }
        }, { passive: true });
        menu.querySelectorAll('[data-chat-mobile-action]').forEach(function(item) {
            item.addEventListener('click', function() {
                if (!selectedChatTicket) return;
                var act = item.getAttribute('data-chat-mobile-action');
                if (act === 'file') {
                    openMobileAttachmentPicker('photo');
                } else if (act === 'camera') {
                    openMobileAttachmentPicker('camera');
                } else if (act === 'video') {
                    openMobileAttachmentPicker('video');
                } else if (act === 'nachricht') {
                    setChatMessageType('nachricht', false);
                    closeChatMobilePlusMenu();
                    var ta0 = getChatMessageInputEl();
                    if (ta0) setTimeout(function() { ta0.focus(); }, 50);
                } else if (act === 'aufgabe') {
                    setChatMessageType('aufgabe', false);
                    closeChatMobilePlusMenu();
                    var ta = getChatMessageInputEl();
                    if (ta) setTimeout(function() { ta.focus(); }, 50);
                } else if (act === 'loesung') {
                    setChatMessageType('loesung', false);
                    closeChatMobilePlusMenu();
                    var ta2 = getChatMessageInputEl();
                    if (ta2) setTimeout(function() { ta2.focus(); }, 50);
                } else if (act === 'bestellung') {
                    closeChatMobilePlusMenu();
                    openOrderConsumablesModal();
                }
            });
        });
    })();

    // Drag & Drop Event Listener für Attachment Modal
    const dropzoneLabel = document.getElementById('dropzone-label');
    const dropzoneFile = document.getElementById('dropzone-file');
    
    if (dropzoneLabel && dropzoneFile) {
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzoneLabel.addEventListener(eventName, preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzoneLabel.addEventListener(eventName, function() {
                dropzoneLabel.classList.add('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900');
                dropzoneLabel.classList.remove('border-gray-300', 'dark:border-gray-600');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzoneLabel.addEventListener(eventName, function() {
                dropzoneLabel.classList.remove('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900');
                dropzoneLabel.classList.add('border-gray-300', 'dark:border-gray-600');
            }, false);
        });
        
        dropzoneLabel.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            handleFileSelect(files);
        }, false);
        
        dropzoneFile.addEventListener('change', function(e) {
            handleFileSelect(e.target.files);
        }, false);
    }
    
    // Bearbeitungszeit-Modal: Presets und eigene Eingabe
    const presets = document.querySelectorAll('.bearbeitungszeit-preset');
    const customInput = document.getElementById('bearbeitungszeitCustom');
    presets.forEach(btn => {
        btn.addEventListener('click', function() {
            setBearbeitungszeitPresetActive(this);
            if (customInput) customInput.value = '';
        });
    });
    if (customInput) {
        function clearPresetSelection() {
            setBearbeitungszeitPresetActive(null);
        }
        customInput.addEventListener('input', clearPresetSelection);
        customInput.addEventListener('change', clearPresetSelection);
        customInput.addEventListener('focus', function() {
            if (this.value.trim() !== '') clearPresetSelection();
        });
    }
    document.getElementById('bearbeitungszeitModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeBearbeitungszeitModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const m = document.getElementById('bearbeitungszeitModal');
            if (m && !m.classList.contains('hidden')) { closeBearbeitungszeitModal(); e.preventDefault(); }
            const addAppointmentMobileModal = document.getElementById('addAppointmentMobileModal');
            if (addAppointmentMobileModal && !addAppointmentMobileModal.classList.contains('hidden')) { closeAddAppointmentMobileModal(); e.preventDefault(); }
            const historyModal = document.getElementById('ticketHistoryModal');
            if (historyModal && !historyModal.classList.contains('hidden')) { closeTicketHistoryModal(); e.preventDefault(); }
            const mergeModal = document.getElementById('mergeTicketModal');
            if (mergeModal && !mergeModal.classList.contains('hidden')) { closeMergeTicketModal(); e.preventDefault(); }
            const moreMenu = document.getElementById('more-options-dropdown-menu');
            if (moreMenu && !moreMenu.classList.contains('hidden')) {
                moreMenu.classList.add('hidden');
                const btn = document.getElementById('more-options-dropdown-button');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        }
    });
    document.getElementById('merge-ticket-search')?.addEventListener('input', function() {
        filterMergeTicketCandidates(this.value || '');
    });
    document.getElementById('merge-filter-all-companies')?.addEventListener('change', function() {
        const mergeModal = document.getElementById('mergeTicketModal');
        if (mergeModal && !mergeModal.classList.contains('hidden')) {
            loadMergeTicketCandidates();
        }
    });
    document.getElementById('merge-filter-include-closed')?.addEventListener('change', function() {
        const mergeModal = document.getElementById('mergeTicketModal');
        if (mergeModal && !mergeModal.classList.contains('hidden')) {
            loadMergeTicketCandidates();
        }
    });
    document.getElementById('link-project-search')?.addEventListener('input', function() {
        filterLinkProjectCandidates(this.value || '');
    });

    // Mehr Optionen Dropdown
    const moreOptionsBtn = document.getElementById('more-options-dropdown-button');
    const moreOptionsMenu = document.getElementById('more-options-dropdown-menu');
    if (moreOptionsBtn && moreOptionsMenu) {
        moreOptionsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = !moreOptionsMenu.classList.contains('hidden');
            moreOptionsMenu.classList.toggle('hidden', isOpen);
            moreOptionsBtn.setAttribute('aria-expanded', !isOpen);
        });
        document.addEventListener('click', function() {
            moreOptionsMenu.classList.add('hidden');
            moreOptionsBtn.setAttribute('aria-expanded', 'false');
        });
        moreOptionsMenu.addEventListener('click', function(e) { e.stopPropagation(); });
    }
    document.getElementById('option-export-pdf')?.addEventListener('click', function() {
        closeMoreOptionsDropdown();
        var iframe = document.createElement('iframe');
        iframe.setAttribute('style', 'position:absolute;width:0;height:0;border:0;visibility:hidden');
        iframe.src = '<?php echo BASE_URL; ?>tickets/export-pdf.php?id=' + ticketId + '&print=1';
        document.body.appendChild(iframe);
        setTimeout(function() { if (iframe.parentNode) iframe.parentNode.removeChild(iframe); }, 60000);
    });
    document.getElementById('option-copy-ticket-number')?.addEventListener('click', async function() {
        closeMoreOptionsDropdown();
        const ticketNummer = (selectedChatTicket && selectedChatTicket.ticket_nummer) ? String(selectedChatTicket.ticket_nummer).trim() : '';
        if (!ticketNummer) {
            if (typeof showToast === 'function') showToast('Keine Ticketnummer verfügbar', 'error');
            return;
        }
        const copied = await copyToClipboard(ticketNummer);
        if (typeof showToast === 'function') {
            showToast(copied ? 'Ticketnummer kopiert' : 'Kopieren fehlgeschlagen', copied ? 'success' : 'error');
        }
    });
    document.getElementById('option-duplicate-ticket')?.addEventListener('click', async function() {
        closeMoreOptionsDropdown();
        if (!selectedChatTicket) {
            if (typeof showToast === 'function') showToast('Ticketdaten noch nicht geladen', 'error');
            return;
        }
        const source = selectedChatTicket;
        const payload = {
            titel: ((source.titel || 'Ticket') + ' (Kopie)').trim(),
            beschreibung: source.beschreibung || '',
            prioritaet: source.prioritaet || 'normal',
            company_id: source.company_id ? parseInt(source.company_id, 10) : null,
            customer_id: source.customer_id ? parseInt(source.customer_id, 10) : null,
            device_id: source.device_id ? parseInt(source.device_id, 10) : null,
            zugewiesen_an: source.zugewiesen_an ? parseInt(source.zugewiesen_an, 10) : null,
            observer_ids: (source.observer_ids || '')
                .split(',')
                .map(function(v) { return parseInt(v.trim(), 10); })
                .filter(function(v) { return Number.isFinite(v) && v > 0; }),
            duplicate_from_ticket_id: source.id ? parseInt(source.id, 10) : null
        };
        try {
            const response = await fetch(ticketsApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (data && data.success && data.ticket_id) {
                if (typeof showToast === 'function') showToast('Ticket dupliziert', 'success');
                window.location.href = serviceBaseUrl + 'tickets/view.php?id=' + encodeURIComponent(data.ticket_id);
                return;
            }
            if (typeof showToast === 'function') showToast((data && data.error) ? data.error : 'Duplizieren fehlgeschlagen', 'error');
        } catch (e) {
            if (typeof showToast === 'function') showToast('Fehler beim Duplizieren', 'error');
        }
    });
    document.getElementById('option-link-ticket')?.addEventListener('click', async function() {
        closeMoreOptionsDropdown();
        openMergeTicketModal();
    });
    document.getElementById('option-link-project')?.addEventListener('click', function() {
        closeMoreOptionsDropdown();
        const hasProject = selectedChatTicket && selectedChatTicket.projects && selectedChatTicket.projects.length > 0;
        if (hasProject) {
            unlinkProject();
        } else {
            openLinkProjectModal();
        }
    });
    document.getElementById('option-save-to-probleme')?.addEventListener('click', async function() {
        closeMoreOptionsDropdown();
        if (!selectedChatTicket || !selectedChatTicket.id) {
            if (typeof showToast === 'function') showToast('Ticketdaten noch nicht geladen', 'error');
            return;
        }
        try {
            const response = await fetch('<?php echo BASE_URL; ?>knowledge/api/save-ticket-to-probleme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ticket_id: selectedChatTicket.id })
            });
            const data = await response.json();
            if (data && data.success) {
                if (typeof showToast === 'function') showToast(data.message || 'Ticket in Wissensdatenbank gespeichert', 'success');
                if (data.kb_url) {
                    window.open(data.kb_url, '_blank', 'noopener');
                }
            } else if (typeof showToast === 'function') {
                showToast((data && data.error) ? data.error : 'Speichern in Wissensdatenbank fehlgeschlagen', 'error');
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast('Fehler beim Speichern in Wissensdatenbank', 'error');
        }
    });
    document.getElementById('option-show-history')?.addEventListener('click', function() {
        closeMoreOptionsDropdown();
        openTicketHistoryModal();
    });
    document.getElementById('option-edit-subject')?.addEventListener('click', function() {
        closeMoreOptionsDropdown();
        openEditSubjectModal();
    });
    document.getElementById('option-toggle-pin')?.addEventListener('click', function() {
        closeMoreOptionsDropdown();
        if (!selectedChatTicket || !selectedChatTicket.id) {
            if (typeof showToast === 'function') showToast('Ticketdaten noch nicht geladen', 'error');
            return;
        }
        const currentlyPinned = selectedChatTicket.is_pinned === 1 || selectedChatTicket.is_pinned === '1' || selectedChatTicket.is_pinned === true;
        fetch(ticketsApiUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                pinned: currentlyPinned ? 0 : 1
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data && data.success) {
                    selectedChatTicket.is_pinned = data.pinned === 1 || data.pinned === '1' ? 1 : 0;
                    updatePinOptionLabel();
                    if (typeof showToast === 'function') showToast(selectedChatTicket.is_pinned ? 'Ticket angeheftet' : 'Anheftung entfernt', 'success');
                } else if (typeof showToast === 'function') {
                    showToast((data && data.error) ? data.error : 'Anheften fehlgeschlagen', 'error');
                }
            })
            .catch(() => {
                if (typeof showToast === 'function') showToast('Fehler beim Anheften', 'error');
            });
    });
    document.getElementById('option-delete-ticket')?.addEventListener('click', function() {
        closeMoreOptionsDropdown();
        if (!confirm('Möchten Sie diesen Ticket wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;
        fetch(ticketsApiUrl + '?id=' + ticketId, { method: 'DELETE' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (typeof showToast === 'function') showToast('Ticket gelöscht', 'success');
                    window.location.href = '<?php echo BASE_URL; ?>tickets/';
                } else {
                    if (typeof showToast === 'function') showToast(data.error || 'Löschen fehlgeschlagen', 'error');
                }
            })
            .catch(() => {
                if (typeof showToast === 'function') showToast('Fehler beim Löschen', 'error');
            });
    });
    document.getElementById('editSubjectSaveBtn')?.addEventListener('click', function() {
        const input = document.getElementById('editSubjectInput');
        if (!input) return;
        const newTitel = (input.value || '').trim();
        updateTicketField('titel', newTitel).then(function() {
            closeEditSubjectModal();
        }).catch(function() {});
    });
});

function loadTicket(keepCompanyCustomerEditOpen) {
    return fetch(ticketsApiUrl + '?id=' + ticketId, { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.ticket) {
                selectedChatTicket = data.ticket;
                displayTicket(data.ticket, keepCompanyCustomerEditOpen);
                updatePinOptionLabel();
                updateLinkProjectButton();
                loadTicketComments(ticketId);
                loadAppointments();
                tryOpenAppointmentFromHash();
            } else {
                document.getElementById('ticketInfoContent').innerHTML = 
                    '<div class="text-red-500">Fehler beim Laden des Tickets</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            document.getElementById('ticketInfoContent').innerHTML = 
                '<div class="text-red-500">Fehler beim Laden des Tickets</div>';
        });
}

function getDeviceTypeIcon(typ, sizeClass) {
    sizeClass = sizeClass || 'w-4 h-4';
    const icons = {
        'drucker': '<svg class="' + sizeClass + ' text-gray-600 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>',
        'computer': '<svg class="' + sizeClass + ' text-gray-600 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
        'netzwerk': '<svg class="' + sizeClass + ' text-gray-600 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" /></svg>',
        'smartphone': '<svg class="' + sizeClass + ' text-gray-600 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
        'monitor': '<svg class="' + sizeClass + ' text-gray-600 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
        'divers': '<svg class="' + sizeClass + ' text-gray-600 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>'
    };
    return icons[typ] || '<svg class="' + sizeClass + ' text-gray-600 dark:text-primary-20 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>';
}

function syncTicketMobileNav(ticket) {
    if (!ticket) return;
    const tEl = document.getElementById('navTicketViewTitle');
    const nEl = document.getElementById('navTicketViewNumber');
    const bEl = document.getElementById('navTicketViewStatusBadge');
    if (tEl) tEl.textContent = ticket.titel || '(ohne Betreff)';
    if (nEl) nEl.textContent = ticket.ticket_nummer ? String(ticket.ticket_nummer) : '';
    if (bEl) {
        bEl.textContent = getStatusText(ticket.status);
        bEl.className = 'shrink-0 self-center max-w-[min(42vw,11rem)] truncate rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap lg:hidden -me-4 ' + getStatusBadgeClass(ticket.status);
    }
}

function openTicketMobileInfoPanel() {
    if (window.innerWidth >= 1024) return;
    document.body.classList.add('ticket-mobile-info-open');
    const btn = document.getElementById('ticketNavOpenInfoBtn');
    const backdrop = document.getElementById('ticketMobileInfoBackdrop');
    if (btn) btn.setAttribute('aria-expanded', 'true');
    if (backdrop) backdrop.setAttribute('aria-hidden', 'false');
}

function closeTicketMobileInfoPanel() {
    document.body.classList.remove('ticket-mobile-info-open');
    const btn = document.getElementById('ticketNavOpenInfoBtn');
    const backdrop = document.getElementById('ticketMobileInfoBackdrop');
    if (btn) btn.setAttribute('aria-expanded', 'false');
    if (backdrop) backdrop.setAttribute('aria-hidden', 'true');
}

function displayTicket(ticket, keepCompanyCustomerEditOpen) {
    // Observer-Only Modus: Kunde/Firmen-User/Firmen-Admin dürfen als reiner Beobachter nur ansehen
    const ticketCompanyId = ticket.company_id ? parseInt(ticket.company_id) : null;
    const ticketCreatorId = ticket.erstellt_von ? parseInt(ticket.erstellt_von) : null;
    const isObserver = (ticket.observer_ids || '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean)
        .includes(String(currentUserId));
    const isCreator = (ticketCreatorId && ticketCreatorId === currentUserId);
    // Wichtig: "nur Beobachter" soll Read-Only sein, auch wenn das Ticket zur eigenen Firma gehört.
    isObserverOnly = (isObserver && !isAdminOrTech && !isCreator && (userRole === 'Kunde' || userRole === 'Firmen-User' || userRole === 'Firmen-Admin'));

    // Breadcrumb-Label mit Ticketnummer aktualisieren
    const breadcrumbLabel = document.getElementById('breadcrumbTicketLabel');
    if (breadcrumbLabel) {
        const full = ticket.ticket_nummer && ticket.titel ? (ticket.ticket_nummer + ': ' + ticket.titel) : (ticket.ticket_nummer || ticket.titel || 'Ticket');
        breadcrumbLabel.textContent = full.length > 48 ? full.slice(0, 45) + '…' : full;
    }

    syncTicketMobileNav(ticket);

    // Chat Header aktualisieren (kompakt: Titel + Status, Anforderer & Nr. im Tooltip)
    const chatHeader = document.getElementById('chatTicketHeader');
    if (chatHeader) {
        chatHeader.innerHTML = `
            <div class="flex items-center justify-between w-full gap-3 min-h-0 overflow-hidden">
                <div class="flex-1 min-w-0 overflow-hidden">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white truncate">${escapeHtml(ticket.titel)}</h2>
                    ${ticket.ticket_nummer ? `<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">${escapeHtml(String(ticket.ticket_nummer))}</p>` : ''}
                </div>
                <span class="flex-shrink-0 px-3 py-1 text-sm font-semibold rounded-full whitespace-nowrap ${getStatusBadgeClass(ticket.status)}">${getStatusText(ticket.status)}</span>
            </div>
        `;
    }
    
    // Chat Input Area anzeigen
    const chatInputArea = document.getElementById('chatInputArea');
    if (chatInputArea) {
        // Auch als reiner Beobachter darf man Nachrichten/Aufgaben/Bestellungen/Anhänge erstellen
        chatInputArea.style.display = 'block';
        
        // Prüfen ob Ticket abgerechnet ist - dann Input deaktivieren
        const isAbgerechnet = ticket.abgerechnet === 1 || ticket.abgerechnet === '1';
        const inputEls = getBothChatMessageInputs();
        const sendBtn = document.getElementById('send-message-btn');
        const sendBtnDesk = document.getElementById('send-message-btn-desktop');
        const attachBtn = document.getElementById('attach-file-btn');
        const orderBtn = document.getElementById('open-order-modal-btn');
        const messageTypeBtns = document.querySelectorAll('.message-type-btn');
        const plusMobileBtn = document.getElementById('chat-mobile-plus-btn');
        const mobileMenuItems = document.querySelectorAll('.chat-mobile-menu-item');
        

        if (isAbgerechnet) {
            closeChatMobilePlusMenu();
            inputEls.forEach(function(inputEl) {
                if (inputEl) {
                    inputEl.disabled = true;
                    inputEl.placeholder = 'Zu abgerechneten Tickets können keine Kommentare mehr hinzugefügt werden';
                }
            });
            if (sendBtn) sendBtn.disabled = true;
            if (sendBtnDesk) sendBtnDesk.disabled = true;
            if (attachBtn) attachBtn.disabled = true;
            if (orderBtn) orderBtn.disabled = true;
            messageTypeBtns.forEach(btn => { btn.disabled = true; });
            if (plusMobileBtn) {
                plusMobileBtn.disabled = true;
                plusMobileBtn.classList.add('opacity-50', 'pointer-events-none');
            }
            mobileMenuItems.forEach(function(b) { b.disabled = true; });
        } else {
            inputEls.forEach(function(inputEl) {
                if (inputEl) {
                    inputEl.disabled = false;
                    inputEl.placeholder = 'Nachricht schreiben…';
                }
            });
            setTimeout(function() {
                var ta = getChatMessageInputEl();
                if (ta) ta.focus();
            }, 50);
            if (sendBtn) sendBtn.disabled = false;
            if (sendBtnDesk) sendBtnDesk.disabled = false;
            if (attachBtn) attachBtn.disabled = false;
            if (orderBtn) orderBtn.disabled = false;
            messageTypeBtns.forEach(btn => { btn.disabled = false; });
            if (plusMobileBtn) {
                plusMobileBtn.disabled = false;
                plusMobileBtn.classList.remove('opacity-50', 'pointer-events-none');
            }
            mobileMenuItems.forEach(function(b) { b.disabled = false; });
        }
        updateChatMobileCameraSendToggle();
        requestAnimationFrame(function() {
            syncTicketMobileChatInputLayout();
            requestAnimationFrame(syncTicketMobileChatInputLayout);
        });
    }
    
    // Daten für Bearbeitung laden
    loadEditData(ticket);
    
    const createdDate = new Date(ticket.erstellt_datum);
    const formattedDate = createdDate.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Zuletzt geändert-Datum formatieren
    let formattedGeaendertDate = '';
    if (ticket.geaendert_datum) {
        const geaendertDate = new Date(ticket.geaendert_datum);
        formattedGeaendertDate = geaendertDate.toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Aktueller Wert für Bearbeiter-Text
    const zugewiesenAnText = (ticket.zugewiesen_vorname || ticket.zugewiesen_nachname) 
        ? `${ticket.zugewiesen_vorname || ''} ${ticket.zugewiesen_nachname || ''}`.trim() 
        : '-- Kein Bearbeiter --';
    
    const anfordererName = [ticket.ersteller_vorname || '', ticket.ersteller_nachname || ''].filter(Boolean).join(' ').trim() || 'Unbekannt';
    
    // Status-Buttons im Header aktualisieren
    updateStatusButtonsInHeader(ticket.status);
    
    // Ansprechpartner-Texte für Übersicht
    let customerAnsprechpartner = '';
    if (ticket.customer_ansprechpartner_vorname || ticket.customer_ansprechpartner_nachname) {
        customerAnsprechpartner = ((ticket.customer_ansprechpartner_vorname || '') + ' ' + (ticket.customer_ansprechpartner_nachname || '')).trim();
    } else if (ticket.customer_ansprechpartner_manuell_name) {
        customerAnsprechpartner = ticket.customer_ansprechpartner_manuell_name;
    }
    let companyAnsprechpartner = '';
    if (ticket.company_ansprechpartner_vorname || ticket.company_ansprechpartner_nachname) {
        companyAnsprechpartner = ((ticket.company_ansprechpartner_vorname || '') + ' ' + (ticket.company_ansprechpartner_nachname || '')).trim();
    } else if (ticket.company_ansprechpartner_manuell_name) {
        companyAnsprechpartner = ticket.company_ansprechpartner_manuell_name;
    }
    
    document.getElementById('ticketInfoContent').innerHTML = `
        
        <!-- Firma, Kunde, Gerät & Projekt: je eigene Card mit Akkordeon -->
        ${ticket.company_name || ticket.customer_name || ticket.device_name || (ticket.projects && ticket.projects.length) ? `
        <div id="companyCustomerCompactContainer" class="space-y-3">
            <div id="overviewAccordionsRoot" class="overview-acc-root flex flex-col gap-3">
            ${ticket.company_name ? `
                <div id="overviewSectionFirma" data-overview-card="firma" class="overview-accordion-section rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-50 overflow-hidden">
                    <div class="overview-acc-header flex items-center gap-3 px-3.5 py-3 sm:px-4 min-w-0">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-primary-140 text-gray-600 dark:text-primary-210 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z" /></svg>
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-primary-200 truncate" title="${escapeHtml(ticket.company_name)}">${escapeHtml(ticket.company_name)}</span>
                        </div>
                        <button type="button" id="overview-acc-btn-firma" class="overview-acc-toggle flex items-center justify-center w-9 h-9 shrink-0 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-primary-140 dark:text-primary-200 dark:hover:bg-primary-130 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 transition-all duration-200" aria-expanded="false" aria-controls="overview-acc-panel-firma" title="Details ein- oder ausklappen"><svg class="overview-acc-chevron w-4 h-4 transition-transform duration-200 ease-out" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                    </div>
                    <div id="overview-acc-panel-firma" class="overview-acc-panel hidden px-4 pb-4 space-y-3 text-sm leading-relaxed border-t border-gray-100 dark:border-primary-120/40 pt-3 bg-gray-50/50 dark:bg-primary-120/10">
                        ${(ticket.company_adresse || ticket.company_plz || ticket.company_ort) ? `
                        <div class="flex items-start gap-2">
                            <span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-400 dark:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span>
                            ${(() => {
                                var _mapsUrl = googleMapsSearchUrlFromParts(ticket.company_adresse, ticket.company_plz, ticket.company_ort);
                                var _addrText = (ticket.company_adresse ? escapeHtml(ticket.company_adresse) : '') + (ticket.company_adresse && (ticket.company_plz || ticket.company_ort) ? ', ' : '') + (ticket.company_plz && ticket.company_ort ? escapeHtml(ticket.company_plz + ' ' + ticket.company_ort) : (ticket.company_plz ? escapeHtml(ticket.company_plz) : '') + (ticket.company_ort ? escapeHtml(ticket.company_ort) : ''));
                                if (_mapsUrl) return '<a href="' + _mapsUrl + '" target="_blank" rel="noopener noreferrer" class="text-gray-700 dark:text-primary-220 min-w-0 pt-1 hover:text-primary-600 dark:hover:text-primary-220 hover:underline" title="Adresse in Google Maps öffnen">' + _addrText + '</a>';
                                return '<span class="text-gray-700 dark:text-primary-220 min-w-0 pt-1">' + _addrText + '</span>';
                            })()}
                        </div>
                        ` : ''}
                        ${(ticket.company_email || ticket.company_telefon) ? `
                        <div class="flex flex-col gap-1.5">
                            ${ticket.company_email ? `<a href="mailto:${escapeHtml(ticket.company_email)}" class="flex items-center gap-2 text-primary-600 hover:text-primary-700 dark:text-primary-220 dark:hover:text-primary-220 hover:underline min-w-0"><span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-500 dark:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span><span class="truncate">${escapeHtml(ticket.company_email)}</span></a>` : ''}
                            ${ticket.company_telefon ? `<a href="tel:${escapeHtml(ticket.company_telefon)}" class="flex items-center gap-2 text-primary-600 hover:text-primary-700 dark:text-primary-220 dark:hover:text-primary-220 hover:underline min-w-0"><span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-500 dark:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></span><span class="truncate">${escapeHtml(ticket.company_telefon)}</span></a>` : ''}
                        </div>
                        ` : ''}
                        ${companyAnsprechpartner ? `
                        <div class="flex items-start gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-400 dark:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                            <div class="min-w-0 flex flex-col gap-1 pt-1">
                                <span class="text-gray-700 dark:text-primary-220 font-medium">${escapeHtml(companyAnsprechpartner)}</span>
                                ${(ticket.company_ansprechpartner_email || ticket.company_ansprechpartner_manuell_email) ? `<a href="mailto:${escapeHtml(ticket.company_ansprechpartner_email || ticket.company_ansprechpartner_manuell_email)}" class="text-primary-600 dark:text-primary-220 hover:underline text-base">${escapeHtml(ticket.company_ansprechpartner_email || ticket.company_ansprechpartner_manuell_email)}</a>` : ''}
                                ${(ticket.company_ansprechpartner_telefon || ticket.company_ansprechpartner_manuell_telefon) ? `<a href="tel:${escapeHtml(ticket.company_ansprechpartner_telefon || ticket.company_ansprechpartner_manuell_telefon)}" class="text-primary-600 dark:text-primary-220 hover:underline text-base">${escapeHtml(ticket.company_ansprechpartner_telefon || ticket.company_ansprechpartner_manuell_telefon)}</a>` : ''}
                            </div>
                        </div>
                        ` : ''}
                        <div class="overview-card-actions flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 pt-3 border-t border-gray-200/80 dark:border-primary-120/35">
                        ${ticket.company_id && (userRole === 'Admin' || userRole === 'Techniker') ? `<a href="<?php echo BASE_URL; ?>companies/detail.php?id=${ticket.company_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Firmendetails</a>` : ''}
                        ${ticket.company_id && userRole === 'Admin' ? `<a href="<?php echo BASE_URL; ?>companies/edit.php?id=${ticket.company_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Firma bearbeiten</a>` : ''}
                        ${!isObserverOnly && isAdminOrTech && ticket.company_id && (ticket.customer_name || ticketHasAssignableCustomers(ticket)) ? `<button type="button" class="overview-action-link" onclick="event.stopPropagation(); editCompanyCustomerSelection()">Kunde zuordnen</button>` : ''}
                        ${!isObserverOnly && ticket.company_id && ticket.customer_id && !ticket.device_name && (isAdminOrTech || userRole === 'Firmen-Admin') && ticketHasAssignableDevices(ticket) ? `<button type="button" class="overview-action-link" onclick="event.stopPropagation(); editCompanyCustomerSelection(false, true)">Gerät zuordnen</button>` : ''}
                        </div>
                    </div>
                </div>
            ` : ''}
            ${ticket.customer_name ? `
                <div id="compactCustomerRow" data-overview-card="kunde" class="overview-accordion-section rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-50 overflow-hidden">
                    <div class="overview-acc-header flex items-center gap-3 px-3.5 py-3 sm:px-4 min-w-0">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-primary-140 text-gray-600 dark:text-primary-210 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/></svg>
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-primary-200 truncate" id="compactCustomerNameText" title="${escapeHtml(ticket.customer_name)}">${escapeHtml(ticket.customer_name)}</span>
                        </div>
                        <div class="flex items-center gap-0.5 shrink-0">
                        ${!isObserverOnly ? `
                        <button type="button" id="customerCompactEditPencilBtn" onclick="event.stopPropagation(); editCompanyCustomerSelection();" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:text-primary-200 dark:hover:text-primary-220 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" title="Kunde zuordnen" aria-label="Kunde zuordnen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.304 4.844 2.852 2.852M7 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-4.5m2.409-9.91a2.017 2.017 0 0 1 0 2.853l-6.844 6.844L8 14l.713-3.565 6.844-6.844a2.015 2.015 0 0 1 2.852 0Z"/></svg>
                        </button>
                        <button type="button" id="customerCompactCancelEditBtn" style="display: none;" onclick="event.stopPropagation(); cancelCustomerEdit();" class="p-2 rounded-lg text-gray-400 hover:text-gray-700 dark:text-primary-200 dark:hover:text-primary-220 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" title="Abbrechen" aria-label="Bearbeitung abbrechen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        ` : ''}
                        </div>
                        <button type="button" id="overview-acc-btn-kunde" class="overview-acc-toggle flex items-center justify-center w-9 h-9 shrink-0 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-primary-140 dark:text-primary-200 dark:hover:bg-primary-130 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 transition-all duration-200" aria-expanded="false" aria-controls="overview-acc-panel-kunde" title="Details ein- oder ausklappen"><svg class="overview-acc-chevron w-4 h-4 transition-transform duration-200 ease-out" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                    </div>
                    <div id="overview-acc-panel-kunde" class="overview-acc-panel hidden px-4 pb-4 space-y-3 text-sm leading-relaxed border-t border-gray-100 dark:border-primary-120/40 pt-3 bg-gray-50/50 dark:bg-primary-120/10">
                        ${(ticket.customer_adresse || ticket.customer_plz || ticket.customer_ort) ? `
                        <div class="flex items-start gap-2">
                            <span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-400 dark:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span>
                            ${(() => {
                                var _mapsUrl = googleMapsSearchUrlFromParts(ticket.customer_adresse, ticket.customer_plz, ticket.customer_ort);
                                var _addrText = (ticket.customer_adresse ? escapeHtml(ticket.customer_adresse) : '') + (ticket.customer_adresse && (ticket.customer_plz || ticket.customer_ort) ? ', ' : '') + (ticket.customer_plz && ticket.customer_ort ? escapeHtml(ticket.customer_plz + ' ' + ticket.customer_ort) : (ticket.customer_plz ? escapeHtml(ticket.customer_plz) : '') + (ticket.customer_ort ? escapeHtml(ticket.customer_ort) : ''));
                                if (_mapsUrl) return '<a href="' + _mapsUrl + '" target="_blank" rel="noopener noreferrer" class="text-gray-700 dark:text-primary-220 min-w-0 pt-1 hover:text-primary-600 dark:hover:text-primary-220 hover:underline" title="Adresse in Google Maps öffnen">' + _addrText + '</a>';
                                return '<span class="text-gray-700 dark:text-primary-220 min-w-0 pt-1">' + _addrText + '</span>';
                            })()}
                        </div>
                        ` : ''}
                        ${(ticket.customer_email || ticket.customer_telefon) ? `
                        <div class="flex flex-col gap-1.5">
                            ${ticket.customer_email ? `<a href="mailto:${escapeHtml(ticket.customer_email)}" class="flex items-center gap-2 text-primary-600 hover:text-primary-700 dark:text-primary-220 dark:hover:text-primary-220 hover:underline min-w-0"><span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-500 dark:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span><span class="truncate">${escapeHtml(ticket.customer_email)}</span></a>` : ''}
                            ${ticket.customer_telefon ? `<a href="tel:${escapeHtml(ticket.customer_telefon)}" class="flex items-center gap-2 text-primary-600 hover:text-primary-700 dark:text-primary-220 dark:hover:text-primary-220 hover:underline min-w-0"><span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-500 dark:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></span><span class="truncate">${escapeHtml(ticket.customer_telefon)}</span></a>` : ''}
                        </div>
                        ` : ''}
                        ${customerAnsprechpartner ? `
                        <div class="flex items-start gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-400 dark:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                            <div class="min-w-0 flex flex-col gap-1 pt-1">
                                <span class="text-gray-700 dark:text-primary-220 font-medium">${escapeHtml(customerAnsprechpartner)}</span>
                                ${(ticket.customer_ansprechpartner_email || ticket.customer_ansprechpartner_manuell_email) ? `<a href="mailto:${escapeHtml(ticket.customer_ansprechpartner_email || ticket.customer_ansprechpartner_manuell_email)}" class="text-primary-600 dark:text-primary-220 hover:underline text-base">${escapeHtml(ticket.customer_ansprechpartner_email || ticket.customer_ansprechpartner_manuell_email)}</a>` : ''}
                                ${(ticket.customer_ansprechpartner_telefon || ticket.customer_ansprechpartner_manuell_telefon) ? `<a href="tel:${escapeHtml(ticket.customer_ansprechpartner_telefon || ticket.customer_ansprechpartner_manuell_telefon)}" class="text-primary-600 dark:text-primary-220 hover:underline text-base">${escapeHtml(ticket.customer_ansprechpartner_telefon || ticket.customer_ansprechpartner_manuell_telefon)}</a>` : ''}
                            </div>
                        </div>
                        ` : ''}
                        <div class="overview-card-actions flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 pt-3 border-t border-gray-200/80 dark:border-primary-120/35">
                        ${ticket.customer_id && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? `<a href="<?php echo BASE_URL; ?>customers/detail.php?id=${ticket.customer_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Kundendetails</a>` : ''}
                        ${ticket.customer_id && (userRole === 'Admin' || userRole === 'Firmen-Admin') ? `<a href="<?php echo BASE_URL; ?>customers/edit.php?id=${ticket.customer_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Kunde bearbeiten</a>` : ''}
                        ${!isObserverOnly && isAdminOrTech ? `<button type="button" class="overview-action-link" onclick="event.stopPropagation(); editCompanyCustomerSelection()">Kunde zuordnen</button>` : ''}
                        ${!isObserverOnly && isAdminOrTech && ticket.customer_id ? `<button type="button" class="overview-action-link text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" onclick="event.stopPropagation(); removeCustomerFromTicket()">Kunde vom Auftrag entfernen</button>` : ''}
                        ${!isObserverOnly && ticket.customer_id && !ticket.device_name && (isAdminOrTech || userRole === 'Firmen-Admin') && ticketHasAssignableDevices(ticket) ? `<button type="button" class="overview-action-link" onclick="event.stopPropagation(); editCompanyCustomerSelection(false, true)">Gerät zuordnen</button>` : ''}
                        </div>
                    </div>
                </div>
            ` : ''}
            ${ticket.device_name ? `
                <div id="compactDeviceRow" data-overview-card="geraet" class="overview-accordion-section rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-50 overflow-hidden">
                    <div class="overview-acc-header flex items-center gap-3 px-3.5 py-3 sm:px-4 min-w-0">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-primary-140 text-gray-600 dark:text-primary-210 shrink-0 [&_svg]:w-4 [&_svg]:h-4">${getDeviceTypeIcon(ticket.device_typ || '', 'w-4 h-4')}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-primary-200 truncate" id="compactDeviceNameText" title="${[ticket.device_name, ticket.device_beschreibung].filter(Boolean).join(' – ')}">${escapeHtml(ticket.device_name)}${ticket.device_beschreibung ? '<span class="text-gray-600 dark:text-primary-210 font-normal"> (' + escapeHtml(ticket.device_beschreibung) + ')</span>' : ''}</span>
                        </div>
                        <div class="flex items-center gap-0.5 shrink-0">
                        ${!isObserverOnly ? `
                        <button type="button" id="deviceCompactEditPencilBtn" onclick="event.stopPropagation(); editCompanyCustomerSelection(false, true);" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:text-primary-200 dark:hover:text-primary-220 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" title="Gerät zuordnen" aria-label="Gerät zuordnen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.304 4.844 2.852 2.852M7 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-4.5m2.409-9.91a2.017 2.017 0 0 1 0 2.853l-6.844 6.844L8 14l.713-3.565 6.844-6.844a2.015 2.015 0 0 1 2.852 0Z"/></svg>
                        </button>
                        <button type="button" id="deviceCompactCancelEditBtn" style="display: none;" onclick="event.stopPropagation(); cancelDeviceEdit();" class="p-2 rounded-lg text-gray-400 hover:text-gray-700 dark:text-primary-200 dark:hover:text-primary-220 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" title="Abbrechen" aria-label="Bearbeitung abbrechen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        ` : ''}
                        </div>
                        <button type="button" id="overview-acc-btn-geraet" class="overview-acc-toggle flex items-center justify-center w-9 h-9 shrink-0 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-primary-140 dark:text-primary-200 dark:hover:bg-primary-130 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 transition-all duration-200" aria-expanded="false" aria-controls="overview-acc-panel-geraet" title="Details ein- oder ausklappen"><svg class="overview-acc-chevron w-4 h-4 transition-transform duration-200 ease-out" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                    </div>
                    <div id="overview-acc-panel-geraet" class="overview-acc-panel hidden px-4 pb-4 space-y-3 text-sm leading-relaxed border-t border-gray-100 dark:border-primary-120/40 pt-3 bg-gray-50/50 dark:bg-primary-120/10">
                        ${(ticket.device_hersteller || ticket.device_modell || ticket.device_seriennummer) ? `
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-2 text-slate-700 dark:text-primary-220 rounded-lg border border-slate-100/90 dark:border-primary-120/25 bg-white/65 dark:bg-primary-100/12 px-3 py-2.5">
                            ${ticket.device_hersteller ? `<div class="flex items-center gap-2 truncate"><span class="text-gray-500 dark:text-white shrink-0 text-sm">Hersteller</span><span class="truncate">${escapeHtml(ticket.device_hersteller)}</span></div>` : ''}
                            ${ticket.device_modell ? `<div class="flex items-center gap-2 truncate"><span class="text-gray-500 dark:text-white shrink-0 text-sm">Modell</span><span class="truncate">${escapeHtml(ticket.device_modell)}</span></div>` : ''}
                            ${ticket.device_seriennummer ? `<div class="flex items-center gap-2 truncate sm:col-span-2"><span class="text-gray-500 dark:text-white shrink-0 text-sm">Seriennummer</span><span class="truncate font-mono text-sm">${escapeHtml(ticket.device_seriennummer)}</span></div>` : ''}
                        </div>
                        ` : ''}
                        ${(ticket.device_mac_adresse || ticket.device_ip_adresse || ticket.device_betriebssystem) ? `
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-2 text-slate-700 dark:text-primary-220 rounded-lg border border-slate-100/90 dark:border-primary-120/25 bg-white/65 dark:bg-primary-100/12 px-3 py-2.5">
                            ${ticket.device_mac_adresse ? `<div class="flex items-center gap-2 truncate"><span class="text-gray-500 dark:text-white shrink-0 text-sm">MAC</span><span class="truncate font-mono text-sm">${escapeHtml(ticket.device_mac_adresse)}</span></div>` : ''}
                            ${ticket.device_ip_adresse ? `<div class="flex items-center gap-2 truncate"><span class="text-gray-500 dark:text-white shrink-0 text-sm">IP</span><span class="truncate font-mono text-sm">${escapeHtml(ticket.device_ip_adresse)}</span></div>` : ''}
                            ${ticket.device_betriebssystem ? `<div class="flex items-center gap-2 truncate sm:col-span-2"><span class="text-gray-500 dark:text-white shrink-0 text-sm">OS</span><span class="truncate text-sm">${escapeHtml(ticket.device_betriebssystem)}</span></div>` : ''}
                        </div>
                        ` : ''}
                        ${ticket.device_beschreibung ? `
                        <p class="text-gray-600 dark:text-primary-220 text-sm leading-relaxed border-t border-gray-200 dark:border-gray-700 pt-3">${escapeHtml(ticket.device_beschreibung)}</p>
                        ` : ''}
                        <div class="overview-card-actions flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 pt-3 border-t border-gray-200/80 dark:border-primary-120/35">
                        ${ticket.device_id && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? `<a href="<?php echo BASE_URL; ?>devices/detail.php?id=${ticket.device_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Gerätedetails</a>` : ''}
                        ${ticket.device_id && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? `<a href="<?php echo BASE_URL; ?>devices/edit.php?id=${ticket.device_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Gerät bearbeiten</a>` : ''}
                        ${!isObserverOnly && (isAdminOrTech || userRole === 'Firmen-Admin') ? `<button type="button" class="overview-action-link" onclick="event.stopPropagation(); editCompanyCustomerSelection(false, true)">Gerät zuordnen</button>` : ''}
                        ${!isObserverOnly && ticket.device_id && (isAdminOrTech || userRole === 'Firmen-Admin') ? `<button type="button" class="overview-action-link text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" onclick="event.stopPropagation(); removeDeviceFromTicket()">Gerät vom Auftrag entfernen</button>` : ''}
                        </div>
                    </div>
                </div>
            ` : ''}
            ${(ticket.projects && ticket.projects.length) ? (function() {
                const p = ticket.projects[0];
                return `
                <div id="compactProjectRow" data-overview-card="projekt" class="overview-accordion-section rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-50 overflow-hidden">
                    <div class="overview-acc-header flex items-center gap-3 px-3.5 py-3 sm:px-4 min-w-0">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-primary-140 text-gray-600 dark:text-primary-210 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/></svg>
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-primary-200 truncate" id="compactProjectNameText" title="${escapeHtml(p.bezeichnung || 'Projekt')}">${escapeHtml(p.bezeichnung || 'Projekt')}</span>
                        </div>
                        <button type="button" id="overview-acc-btn-projekt" class="overview-acc-toggle flex items-center justify-center w-9 h-9 shrink-0 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-primary-140 dark:text-primary-200 dark:hover:bg-primary-130 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 transition-all duration-200" aria-expanded="false" aria-controls="overview-acc-panel-projekt" title="Details ein- oder ausklappen"><svg class="overview-acc-chevron w-4 h-4 transition-transform duration-200 ease-out" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                    </div>
                    <div id="overview-acc-panel-projekt" class="overview-acc-panel hidden px-4 pb-4 space-y-3 text-sm leading-relaxed border-t border-gray-100 dark:border-primary-120/40 pt-3 bg-gray-50/50 dark:bg-primary-120/10">
                        ${p.project_nummer ? `<div class="flex items-start gap-2"><span class="w-8 h-8 shrink-0" aria-hidden="true"></span><p class="text-gray-700 dark:text-primary-200 min-w-0 m-0 pt-1">Projektnummer: <span class="font-medium">${escapeHtml(p.project_nummer)}</span></p></div>` : ''}
                        ${p.status ? `<div class="flex items-start gap-2"><span class="w-8 h-8 shrink-0" aria-hidden="true"></span><p class="text-gray-700 dark:text-primary-200 min-w-0 m-0 pt-1">Status: <span class="font-medium">${escapeHtml(p.status)}</span></p></div>` : ''}
                        ${(p.beauftragter_vorname || p.beauftragter_nachname) ? `<div class="flex items-start gap-2"><span class="w-8 h-8 shrink-0" aria-hidden="true"></span><p class="text-gray-700 dark:text-primary-200 min-w-0 m-0 pt-1">Beauftragter: <span class="font-medium">${escapeHtml([p.beauftragter_vorname, p.beauftragter_nachname].filter(Boolean).join(' '))}</span></p></div>` : ''}
                        <div class="flex items-start gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span class="flex items-center justify-center w-8 h-8 shrink-0 text-gray-500 dark:text-primary-240"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></span>
                            <span class="text-sm text-gray-600 dark:text-primary-220 min-w-0 pt-1">Ein diesem Projekt zugeordneter Ticket kann nicht abgerechnet werden.</span>
                        </div>
                        <div class="overview-card-actions flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 pt-3 border-t border-gray-200/80 dark:border-primary-120/35">
                        <a href="${serviceBaseUrl}projects/view.php?id=${p.id}" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Projekt bearbeiten</a>
                        <a href="${serviceBaseUrl}projects/" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Projektliste</a>
                        </div>
                    </div>
                </div>
                `;
            })() : ''}
            </div>
        </div>
        ` : ''}
        
        <!-- Edit-Panels: Standard im Pool, per JS unter die jeweilige Akkordeon-Zeile -->
        <div id="editPanelsPool" class="sr-only" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0" aria-hidden="true">
        <div id="companySelectContainer" style="display: none;" class="edit-card edit-card-company border-0 border-t border-gray-100 dark:border-primary-120/40 bg-gray-50/50 dark:bg-primary-120/10 overflow-hidden transition-all duration-300 ease-out">
            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-primary-120 border-b border-gray-100 dark:border-primary-140">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-200/30 text-primary-600 dark:text-primary-250 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <span class="font-semibold text-gray-900 dark:text-primary-220 block">Firma</span>
                        <span class="text-xs text-gray-500 dark:text-primary-240">Wählen Sie eine Firma</span>
                    </div>
                </div>
                <button type="button" onclick="cancelCompanyEdit()" class="flex items-center gap-1.5 px-2.5 py-1.5 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-primary-140 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Abbrechen
                </button>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <span id="companySelectedTextEdit" class="text-sm font-medium text-primary-600 dark:text-primary-250">${ticket.company_name ? escapeHtml(ticket.company_name) : '-- Keine Firma --'}</span>
                    ${!isAdminOrTech && userCompanyId ? `
                        <button type="button" onclick="setMyCompanyOnTicket()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Meine Firma setzen
                        </button>
                    ` : ''}
                </div>
                ${isAdminOrTech ? `
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="companySearchEdit" placeholder="Firma suchen..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div class="max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/80 sticky top-0">
                            <tr><th class="px-3 py-2">Name</th></tr>
                        </thead>
                        <tbody id="companyTableBodyEdit" class="bg-white dark:bg-gray-800">
                            <tr><td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Lade Firmen...</td></tr>
                        </tbody>
                    </table>
                </div>
                ` : `
                <p class="text-sm text-gray-600 dark:text-gray-400">${userCompanyId ? 'Du kannst nur deine eigene Firma setzen.' : 'Keine Firma in deinem Benutzerkonto hinterlegt.'}</p>
                `}
                <div class="overview-card-actions flex flex-wrap items-center gap-x-3 gap-y-1 pt-2 mt-1 border-t border-gray-200 dark:border-primary-120/35">
                    <button type="button" class="overview-action-link" onclick="cancelCompanyEdit()">Auswahl abbrechen</button>
                    <button type="button" class="overview-action-link" onclick="openOverviewAccordionPanel('firma')">Firmendetails anzeigen</button>
                    ${ticket.company_id && (userRole === 'Admin' || userRole === 'Techniker') ? `<a href="<?php echo BASE_URL; ?>companies/detail.php?id=${ticket.company_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link">Firmendetails</a>` : ''}
                    ${ticket.company_id && userRole === 'Admin' ? `<a href="<?php echo BASE_URL; ?>companies/edit.php?id=${ticket.company_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link">Firma bearbeiten</a>` : ''}
                </div>
            </div>
        </div>
        
        <!-- Kunde Edit-Card (Admin/Techniker/Firmen-Admin) -->
        ${(userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? `
        <div id="customerSelectContainer" style="display: none;" class="edit-card edit-card-customer border-0 border-t border-gray-100 dark:border-primary-120/40 bg-gray-50/50 dark:bg-primary-120/10 overflow-hidden transition-all duration-300 ease-out">
            <div class="customer-edit-panel-inner p-3 sm:p-4 space-y-3">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="customerSearchEdit" placeholder="Kunde suchen..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div class="max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                    <table class="w-full text-sm text-left table-fixed">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/80 sticky top-0">
                            <tr><th class="px-3 py-2 w-2/5">Kunde</th><th class="px-3 py-2 w-3/5">Adresse</th></tr>
                        </thead>
                        <tbody id="customerTableBodyEdit" class="bg-white dark:bg-gray-800">
                            <tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Lade Kunden...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="overview-card-actions flex flex-wrap items-center gap-x-3 gap-y-1 pt-2 mt-1 border-t border-gray-200 dark:border-primary-120/35">
                    ${isAdminOrTech ? `<button type="button" class="overview-action-link text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" onclick="removeCustomerFromTicket()">Kunde vom Auftrag entfernen</button>` : ''}
                    <button type="button" class="overview-action-link" onclick="openOverviewAccordionPanel('firma')">Firmendetails anzeigen</button>
                    ${ticket.customer_id && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? `<a href="<?php echo BASE_URL; ?>customers/detail.php?id=${ticket.customer_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link">Kundendetails</a>` : ''}
                    ${ticket.customer_id && (userRole === 'Admin' || userRole === 'Firmen-Admin') ? `<a href="<?php echo BASE_URL; ?>customers/edit.php?id=${ticket.customer_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link">Kunde bearbeiten</a>` : ''}
                </div>
            </div>
        </div>
        ` : ''}
        
        <!-- Gerät Edit-Card (Admin/Techniker/Firmen-Admin) -->
        ${(userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? `
        <div id="deviceSelectContainer" style="display: none;" class="edit-card edit-card-device border-0 border-t border-gray-100 dark:border-primary-120/40 bg-gray-50/50 dark:bg-primary-120/10 overflow-hidden transition-all duration-300 ease-out">
            <div class="device-edit-panel-inner p-3 sm:p-4 space-y-3">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="deviceSearchEdit" placeholder="Gerät suchen..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div class="max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/80 sticky top-0">
                            <tr>
                                <th class="px-2 py-1.5">Name</th>
                                <th class="px-2 py-1.5">Gerät</th>
                                <th class="px-2 py-1.5">Info</th>
                            </tr>
                        </thead>
                        <tbody id="deviceTableBodyEdit" class="bg-white dark:bg-gray-800">
                            <tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-gray-400">Lade Geräte...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="overview-card-actions flex flex-wrap items-center gap-x-3 gap-y-1 pt-2 mt-1 border-t border-gray-200 dark:border-primary-120/35">
                    ${!isObserverOnly && ticket.device_id && (isAdminOrTech || userRole === 'Firmen-Admin') ? `<button type="button" class="overview-action-link text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" onclick="removeDeviceFromTicket()">Gerät vom Auftrag entfernen</button>` : ''}
                    <button type="button" class="overview-action-link" onclick="openOverviewAccordionPanel('kunde')">Kundendetails anzeigen</button>
                    ${ticket.device_id && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? `<a href="<?php echo BASE_URL; ?>devices/detail.php?id=${ticket.device_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link">Gerätedetails</a>` : ''}
                    ${ticket.device_id && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? `<a href="<?php echo BASE_URL; ?>devices/edit.php?id=${ticket.device_id}" target="_blank" rel="noopener noreferrer" class="overview-action-link">Gerät bearbeiten</a>` : ''}
                </div>
            </div>
        </div>
        ` : ''}
        </div>
        
        <!-- Kompakte Anforderer, Bearbeiter & Beobachter Übersicht (Layout wie Zeit mit Termin hinzufügen) -->
        <div id="assigneeCompactContainer" class="mt-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-4 pt-4 ">
            <div class="flex-1 space-y-1.5">
                <div class="flex items-center gap-2">
                    <button type="button" data-action="toggle-anforderer" class="flex items-center gap-2 min-w-0 text-left group hover:opacity-80 transition-opacity">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Anforderer:</span>
                        <span class="text-sm font-medium text-primary-600 dark:text-primary-250 group-hover:text-primary-700 dark:group-hover:text-primary-200 truncate max-w-[12rem] sm:max-w-none">${escapeHtml(anfordererName)}</span>
                    </button>
                </div>
                ${(() => {
                    const name = anfordererName;
                    const email = (ticket.ersteller_email || '').trim();
                    const logopfad = ticket.ersteller_logopfad || '';
                    const initials = (ticket.ersteller_vorname ? ticket.ersteller_vorname.substring(0, 1) : '') + (ticket.ersteller_nachname ? ticket.ersteller_nachname.substring(0, 1) : '') || '?';
                    let avatarHtml = '';
                    if (logopfad && String(logopfad).startsWith('preset:')) {
                        const parts = String(logopfad).split(':');
                        let color = parts[1] || '#6b7280';
                        if (!color.startsWith('#')) color = '#' + color;
                        const ini = parts[2] || initials.toUpperCase();
                        avatarHtml = '<div class="w-12 h-12 rounded-full flex items-center justify-center text-white text-base font-semibold shrink-0" style="background-color:' + String(color).replace(/"/g, '&quot;') + '">' + escapeHtml(ini) + '</div>';
                    } else if (logopfad && String(logopfad) !== '') {
                        const imgUrl = (String(logopfad).startsWith('http') ? logopfad : '<?php echo BASE_URL; ?>' + String(logopfad).replace(/^\//, ''));
                        avatarHtml = '<div class="shrink-0 relative"><img src="' + escapeHtml(imgUrl) + '" alt="" class="w-12 h-12 rounded-full object-cover" onerror="this.style.display=\'none\';if(this.nextElementSibling)this.nextElementSibling.style.display=\'flex\'"><div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-primary-200/40 flex items-center justify-center text-gray-600 dark:text-primary-220 text-base font-semibold" style="display:none">' + escapeHtml(initials.toUpperCase()) + '</div></div>';
                    } else {
                        avatarHtml = '<div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-primary-200/40 flex items-center justify-center text-gray-600 dark:text-primary-220 text-base font-semibold shrink-0">' + escapeHtml(initials.toUpperCase()) + '</div>';
                    }
                    const emailHtml = email ? '<a href="mailto:' + escapeHtml(email) + '" class="text-primary-600 dark:text-primary-250 hover:underline break-all">' + escapeHtml(email) + '</a>' : '<span class="text-gray-500 dark:text-primary-240">– Keine E-Mail hinterlegt</span>';
                    return '<div id="anfordererExpandPanel" class="anforderer-expand-panel mt-1 overflow-hidden transition-[max-height,opacity] duration-300 ease-out"><div class="flex items-start gap-4 pt-2 pb-1 border-t border-gray-100 dark:border-primary-120"><div class="shrink-0">' + avatarHtml + '</div><div class="min-w-0 flex-1 space-y-1"><p class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(name) + '</p><p class="text-sm text-gray-600 dark:text-primary-220">E-Mail: ' + emailHtml + '</p></div></div></div>';
                })()}
                ${ticket.zugewiesen_an && (canSetAssignee && !isObserverOnly) ? `
                <div class="flex items-center gap-2 justify-between">
                    <div class="flex items-center gap-2 min-w-0">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/>
</svg>

                        <span class="text-sm text-gray-600 dark:text-gray-400">Bearbeiter:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(zugewiesenAnText)}</span>
                    </div>
                    <div class="flex items-center gap-0.5 shrink-0">
                        <button type="button" data-action="edit-assignee" class="p-1 rounded text-gray-400 hover:text-primary-600 dark:hover:text-primary-250 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" title="Bearbeiter ändern">
                            <svg class="assignee-edit-pencil w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="square" stroke-linejoin="round" stroke-width="2" d="M7 19H5a1 1 0 0 1-1-1v-1a3 3 0 0 1 3-3h1m4-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm7.441 1.559a1.907 1.907 0 0 1 0 2.698l-6.069 6.069L10 19l.674-3.372 6.07-6.07a1.907 1.907 0 0 1 2.697 0Z"/>
</svg>

                            
                            <svg class="assignee-edit-close hidden w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                        <button type="button" data-action="clear-assignee" onclick="window.clearAssigneeEdit &amp;&amp; window.clearAssigneeEdit()" class="p-1 rounded text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Bearbeiter entfernen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12h4M4 18v-1a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Zm8-10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
</svg>

                        </button>
                    </div>
                </div>
                ` : ticket.zugewiesen_an ? `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/>
</svg>

                    <span class="text-sm text-gray-600 dark:text-gray-400">Bearbeiter:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(zugewiesenAnText)}</span>
                </div>
                ` : ''}
                ${ticket.observer_names && (canEditObservers && !isObserverOnly) ? `
                <div class="flex items-center gap-2 justify-between">
                    <div class="flex items-center gap-2 min-w-0">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Beobachter:</span>
                        <span id="observerCompactText" class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(ticket.observer_names)}</span>
                    </div>
                    <div class="flex items-center gap-0.5 shrink-0">
                        <button type="button" data-action="edit-observers" class="p-1 rounded text-gray-400 hover:text-primary-600 dark:hover:text-primary-250 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" title="Beobachter ändern">
                            <svg class="observer-edit-pencil w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="square" stroke-linejoin="round" stroke-width="2" d="M7 19H5a1 1 0 0 1-1-1v-1a3 3 0 0 1 3-3h1m4-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm7.441 1.559a1.907 1.907 0 0 1 0 2.698l-6.069 6.069L10 19l.674-3.372 6.07-6.07a1.907 1.907 0 0 1 2.697 0Z"/>
</svg>


                           
                           
                            <svg class="observer-edit-close hidden w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                        <button type="button" data-action="clear-observers" onclick="window.clearObserversFromCompact &amp;&amp; window.clearObserversFromCompact()" class="p-1 rounded text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Beobachter entfernen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
   <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12h4M4 18v-1a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Zm8-10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
</svg>

                        </button>
                    </div>
                </div>
                ` : ticket.observer_names ? `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Beobachter:</span>
                    <span id="observerCompactText" class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(ticket.observer_names)}</span>
                </div>
                ` : ''}
            </div>
            ${(() => {
                const showAssigneeBtn = (!isObserverOnly && canSetAssignee && !ticket.zugewiesen_an);
                const showObserverBtn = (!isObserverOnly && canEditObservers && !ticket.observer_names);
                /* Block nur weglassen wenn Nutzer weder Bearbeiter noch Beobachter bearbeiten darf */
                if (!canSetAssignee && !canEditObservers) return '';
                const linkClass = 'inline-flex items-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-250 hover:text-primary-700 dark:hover:text-primary-200 hover:underline transition-colors';
                return `
                <div class="mt-2 space-y-1">
                    ${showAssigneeBtn ? `
                    <div>
                        <button type="button" onclick="editAssigneeSelection()" class="${linkClass}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12h4m-2 2v-4M4 18v-1a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Zm8-10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
</svg>

                            Bearbeiter hinzufügen
                        </button>
                    </div>
                    ` : ''}
                </div>
                ${canSetAssignee ? `
                <div id="assigneeExpandPanel" class="assignee-expand-panel mt-1 overflow-hidden transition-[max-height,opacity] duration-300 ease-out">
                    <span id="assigneeSelectedTextEdit" class="sr-only">${escapeHtml(zugewiesenAnText)}</span>
                    <input type="text" id="assigneeSearchEdit" placeholder="Suchen…" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 mb-2">
                    <div class="max-h-52 overflow-y-auto custom-scrollbar rounded-lg border border-gray-200 dark:border-primary-120 mb-2">
                        <table class="w-full text-sm text-left">
                            <tbody id="assigneeTableBodyEdit" class="bg-white dark:bg-primary-100">
                                <tr><td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Lade Bearbeiter…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
                ${showObserverBtn ? `
                <div class="mt-1">
                    <button type="button" id="addObserverBtn" onclick="editObserverSelection()" class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-250 hover:text-primary-700 dark:hover:text-primary-200 hover:underline transition-colors">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12h4m-2 2v-4M4 18v-1a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Zm8-10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
</svg>

                        Beobachter hinzufügen
                    </button>
                </div>
                ` : ''}
                ${canEditObservers ? `
                <div id="observerExpandPanel" class="observer-expand-panel mt-1 overflow-hidden transition-[max-height,opacity] duration-300 ease-out pb-1">
                    <input type="text" id="observerSearchEdit" placeholder="Suchen…" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 mb-2">
                    <div class="max-h-52 overflow-y-auto custom-scrollbar rounded-lg border border-gray-200 dark:border-primary-120 mb-2">
                        <table class="w-full text-sm text-left">
                            <tbody id="observerTableBodyEdit" class="bg-white dark:bg-primary-100">
                                <tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Lade Beobachter…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center gap-3 pb-3">
                        <button type="button" data-action="clear-observers-edit" onclick="window.clearObserversEdit &amp;&amp; window.clearObserversEdit()" class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            Alle entfernen
                        </button>
                        <button type="button" data-action="save-observers" onclick="window.saveObserversEdit &amp;&amp; window.saveObserversEdit()" class="inline-flex items-center gap-1.5 px-3 py-1.5  text-sm font-medium text-white bg-green-600 hover:bg-green-700 dark:bg-green-600 dark:hover:bg-green-700 rounded-lg transition-colors">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Auswahl speichern
                        </button>
                    </div>
                </div>
                ` : ''}
            `;
            })()}
        </div>
        
        <!-- Kompakte Termine & Daten Card -->
        <div class="mt-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex-1 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1.5">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Erstellt:</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">${formattedDate}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Zuletzt geändert:</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">${formattedGeaendertDate || formattedDate}</span>
                        </div>
                    </div>
                    ${ticket.geplant_datum ? `
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Geplant:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">${formatDateTimeRange(ticket.geplant_datum, ticket.geplant_datum_ende)}</span>
                    </div>
                    ` : ''}
                    ${ticket.faellig_datum ? `
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Fällig:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">${formatDateTimeRange(ticket.faellig_datum, ticket.faellig_datum_ende)}</span>
                    </div>
                    ` : ''}
                    ${ticket.abgeschlossen_datum ? `
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 6 2 2 4-4m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/>
</svg>

                        <span class="text-sm text-gray-600 dark:text-gray-400">Abgeschlossen:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">${formatDateTime(ticket.abgeschlossen_datum)}</span>
                    </div>
                    ` : ''}
                    ${ticket.abgerechnet === 1 || ticket.abgerechnet === '1' ? `
                    <div class="flex items-center gap-2 flex-wrap">
                        <svg class="w-4 h-4 text-green-500 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span class="text-sm text-green-600 dark:text-green-400 font-medium">Abgerechnet${(ticket.company_hat_wartungsvertrag == 1 || ticket.company_hat_wartungsvertrag === '1') ? ' <span class="text-green-500 dark:text-green-500 font-normal">(wegen Wartungsvertrag)</span>' : ''}</span>
                    </div>
                    ` : ''}
                    ${(isAdminOrTech && ticket.status === 'Geschlossen' && !(ticket.abgerechnet === 1 || ticket.abgerechnet === '1') && !(ticket.projects && ticket.projects.length) && !(ticket.company_hat_wartungsvertrag == 1 || ticket.company_hat_wartungsvertrag === '1')) ? `
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" onclick="markAsBilled()" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 dark:bg-green-600 dark:hover:bg-green-700 rounded-lg transition-colors">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Rechnung schreiben
                        </button>
                    </div>
                    ` : ''}
                    ${isAdminOrTech && (ticket.status === 'Geschlossen' || ticket.status === 'Archiv') ? (
                        ticket.bearbeitungszeit_minuten != null && ticket.bearbeitungszeit_minuten > 0
                            ? `
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Bearbeitungszeit:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">${formatBearbeitungszeit(ticket.bearbeitungszeit_minuten)}</span>
                    </div>
                    `
                            : `
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="openBearbeitungszeitModalForTermin()" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-250 dark:hover:text-primary-200 transition-colors bg-primary-50 dark:bg-primary-900/20 hover:bg-primary-100 dark:hover:bg-primary-900/30 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Bearbeitungszeit erfassen
                        </button>
                    </div>
                    `
                    ) : ''}
                    <!-- Termine Liste -->
                    <div id="appointmentsList" class="mt-3 space-y-2">
                        <!-- Termine werden hier dynamisch geladen -->
                    </div>
                    ${(!isObserverOnly && selectedChatTicket && selectedChatTicket.status !== 'Geschlossen' && selectedChatTicket.status !== 'Archiv' && !(selectedChatTicket.abgerechnet === 1 || selectedChatTicket.abgerechnet === '1')) ? `
                    <div class="mt-3">
                        <button type="button" onclick="toggleAppointmentAddPanel()" id="appointmentAddBtn" class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-250 dark:hover:text-primary-200 transition-colors">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path fill="currentColor" d="M4 9.05H3v2h1v-2Zm16 2h1v-2h-1v2ZM10 14a1 1 0 1 0 0 2v-2Zm4 2a1 1 0 1 0 0-2v2Zm-3 1a1 1 0 1 0 2 0h-2Zm2-4a1 1 0 1 0-2 0h2Zm-2-5.95a1 1 0 1 0 2 0h-2Zm2-3a1 1 0 1 0-2 0h2Zm-7 3a1 1 0 0 0 2 0H6Zm2-3a1 1 0 1 0-2 0h2Zm8 3a1 1 0 1 0 2 0h-2Zm2-3a1 1 0 1 0-2 0h2Zm-13 3h14v-2H5v2Zm14 0v12h2v-12h-2Zm0 12H5v2h14v-2Zm-14 0v-12H3v12h2Zm0 0H3a2 2 0 0 0 2 2v-2Zm14 0v2a2 2 0 0 0 2-2h-2Zm0-12h2a2 2 0 0 0-2-2v2Zm-14-2a2 2 0 0 0-2 2h2v-2Zm-1 6h16v-2H4v2ZM10 16h4v-2h-4v2Zm3 1v-4h-2v4h2Zm0-9.95v-3h-2v3h2Zm-5 0v-3H6v3h2Zm10 0v-3h-2v3h2Z"/>
</svg>

                            Termin hinzufügen
                        </button>
                        <div id="appointmentAddExpandPanel" class="appointment-add-expand-panel mt-2 overflow-hidden transition-[max-height,opacity] duration-300 ease-out">
                            <form onsubmit="saveNewAppointmentFromPanel(event)" class="space-y-3 pt-2 border-t border-gray-100 dark:border-primary-120">
                                <div>
                                    <label for="newAppointmentTitle" class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Titel (optional)</label>
                                    <input type="text" id="newAppointmentTitle" placeholder="z.B. Wartung, Reparatur" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="newAppointmentStart" class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Start</label>
                                        <input type="datetime-local" id="newAppointmentStart" required class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                                    </div>
                                    <div>
                                        <label for="newAppointmentEnd" class="block text-sm font-medium text-gray-700 dark:text-primary-220 mb-1">Ende (optional)</label>
                                        <input type="datetime-local" id="newAppointmentEnd" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-140 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 justify-start">
                                    <button type="button" onclick="toggleAppointmentAddPanel()" class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:underline">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        Abbrechen
                                    </button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 dark:bg-green-600 dark:hover:bg-green-700 rounded-lg transition-colors">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        Speichern
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>
        
        <!-- Kompakte Anhänge Card (Klick öffnet/schließt Liste) -->
        <div id="attachmentsCard" class="mt-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4" style="display: none;">
            <div role="button" tabindex="0" onclick="toggleAttachmentsCollapse()" onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); toggleAttachmentsCollapse(); }" class="flex items-center justify-between cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors -m-2 p-2 rounded-lg">
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Anhänge:</span>
                        <span id="attachmentsCountText" class="text-sm font-medium text-gray-900 dark:text-white">Lade...</span>
                    </div>
                </div>
            </div>
            <!-- Collapse Row für Anhänge -->
            <div id="attachmentsCollapse" class="hidden mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                <div id="attachmentsList" class="space-y-2">
                    <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-2">Lade Anhänge...</div>
                </div>
            </div>
        </div>
    `;
    
    bindAssigneeObserverButtons();
    // Event Listener für Details-Elemente setzen, um Daten zu laden
    setupEditCards(ticket);
    
    // Kompakte Cards anzeigen/verstecken basierend auf Auswahl
    setTimeout(() => {
        if (keepCompanyCustomerEditOpen) {
            checkAndShowCompactCards(ticket, true);
            editCompanyCustomerSelection(true);
        } else {
            checkAndShowCompactCards(ticket);
        }
        // Anhänge laden
        loadTicketAttachments(ticketId);
    }, 100);
}

function getStatusBadgeClass(status) {
    const statusColors = {
        'Neu': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'In Bearbeitung': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'Warteschlange': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        'Geplant': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'Bestellung offen': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        'Geschlossen': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        'Archiv': 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
    };
    return statusColors[status] || statusColors['Neu'];
}

function getStatusText(status) {
    const statusText = {
        'Neu': 'Neu',
        'In Bearbeitung': 'In Bearbeitung',
        'Warteschlange': 'Warteschlange',
        'Geplant': 'Geplant',
        'Bestellung offen': 'Bestellung offen',
        'Geschlossen': 'Geschlossen',
        'Archiv': 'Archiv'
    };
    return statusText[status] || status;
}


function loadTicketComments(ticketId) {
    const chatTicketContent = document.getElementById('chatTicketContent');
    
    if (!chatTicketContent) {
        console.error('chatTicketContent Element nicht gefunden');
        return;
    }
    
    chatTicketContent.innerHTML = '<div class="flex flex-col items-center justify-center h-full min-h-[280px] text-center px-4"><div class="w-12 h-12 rounded-xl bg-gray-100 dark:bg-primary-200/40 flex items-center justify-center mb-3"><svg class="animate-spin w-6 h-6 text-primary-500 dark:text-primary-250" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></div><p class="text-sm font-medium text-gray-900 dark:text-primary-200">Lade Nachrichten</p><p class="mt-1 text-sm text-gray-500 dark:text-primary-240">Bitte warten…</p></div>';
    
    // Parallel Ticket-Anhänge und Kommentare laden
    Promise.all([
        fetch(ticketAttachmentsApiUrl + '?ticket_id=' + ticketId)
            .then(response => response.json())
            .then(data => data.success && data.attachments ? data.attachments : [])
            .catch(error => {
                console.error('Fehler beim Laden der Ticket-Anhänge:', error);
                return [];
            }),
        fetch(commentsApiUrl + '?ticket_id=' + ticketId)
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP ${response.status}: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => data.success ? (data.comments || []) : [])
            .catch(error => {
                console.error('Fehler beim Laden der Kommentare:', error);
                return [];
            })
    ])
        .then(([ticketAttachments, comments]) => {
            // Ticket-Anhänge als Kommentar-ähnliche Einträge hinzufügen
            if (ticketAttachments && ticketAttachments.length > 0) {
                ticketAttachments.forEach(attachment => {
                    const attachmentComment = {
                        id: 'ticket_attachment_' + attachment.id,
                        ticket_id: ticketId,
                        user_id: attachment.erstellt_von || selectedChatTicket?.erstellt_von || null,
                        kommentar: '[Dateianhang]',
                        nachrichtentyp: 'nachricht',
                        ist_intern: 0,
                        erstellt_datum: attachment.erstellt_datum || new Date().toISOString(),
                        vorname: selectedChatTicket?.ersteller_vorname || '',
                        nachname: selectedChatTicket?.ersteller_nachname || '',
                        email: selectedChatTicket?.ersteller_email || '',
                        logopfad: selectedChatTicket?.ersteller_logopfad || '',
                        attachments: [attachment],
                        is_ticket_attachment: true
                    };
                    comments.unshift(attachmentComment);
                });
            }
            
            if (selectedChatTicket && selectedChatTicket.beschreibung) {
                const beschreibungUserId = selectedChatTicket.erstellt_von ? parseInt(selectedChatTicket.erstellt_von) : null;
                const beschreibungMessage = {
                    id: 'description',
                    ticket_id: ticketId,
                    user_id: beschreibungUserId,
                    kommentar: selectedChatTicket.beschreibung,
                    nachrichtentyp: 'nachricht',
                    ist_intern: 0,
                    erstellt_datum: selectedChatTicket.erstellt_datum || new Date().toISOString(),
                    vorname: selectedChatTicket.ersteller_vorname || '',
                    nachname: selectedChatTicket.ersteller_nachname || '',
                    email: selectedChatTicket.ersteller_email || '',
                    logopfad: selectedChatTicket.ersteller_logopfad || ''
                };
                comments.unshift(beschreibungMessage);
            }
            
            displayChatMessages(comments);
            
            // Ungelesene Nachrichten / Hervorhebung zurücksetzen, nachdem Kommentare geladen wurden (serverseitig bereits entfernt)
            if (selectedChatTicket) {
                selectedChatTicket.unread_comments_count = 0;
                selectedChatTicket.unread_reminder = 0;
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kommentare:', error);
            const errorMessage = error.message || 'Unbekannter Fehler';
            chatTicketContent.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-sm text-red-500">Fehler beim Laden der Nachrichten: ' + errorMessage + '</p></div>';
        });
}

function formatMessageWithLinks(text) {
    if (!text) return '';
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    const parts = text.split(urlRegex);
    let result = parts.map(part => {
        const urlCheckRegex = /^https?:\/\/[^\s]+$/;
        if (urlCheckRegex.test(part)) {
            return `<a href="${escapeHtml(part)}" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-250 hover:underline dark:hover:text-primary-200 transition-colors">${escapeHtml(part)}</a>`;
        } else {
            return escapeHtml(part);
        }
    }).join('');
    result = result.replace(/\n/g, '<br>');
    result = result.replace(/\[(Bild\s+\d+(?::\s*[^\]]+)?)\]/g, '<span class="inline-flex items-center rounded-md bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200 px-2 py-0.5 font-medium">[Extrahiert: $1]</span>');
    return result;
}

function getOrderStatusBadge(status) {
    const neuHtml = '<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Neu</span>';
    const badges = {
        'Neu': neuHtml,
        'Offen': neuHtml,
        'Bestellt': '<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Bestellt</span>',
        'Unterwegs': '<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Unterwegs</span>',
        'Beim Kunden': '<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">Beim Kunden</span>',
        'Im Lager': '<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Im Lager</span>',
        'Angekommen': '<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Angekommen</span>'
    };
    const s = status === 'Offen' ? 'Neu' : status;
    return badges[s] || neuHtml;
}

function getRelativeDate(date) {
    const now = new Date();
    const messageDate = new Date(date);
    
    // Auf Tagesebene normalisieren (Zeit ignorieren)
    const nowDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const msgDate = new Date(messageDate.getFullYear(), messageDate.getMonth(), messageDate.getDate());
    
    // Wenn heute, kein Datum anzeigen
    if (nowDate.getTime() === msgDate.getTime()) {
        return '';
    }
    
    // Differenz in Tagen berechnen
    const diffTime = nowDate.getTime() - msgDate.getTime();
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) {
        return 'gestern';
    } else if (diffDays === 2) {
        return 'vor zwei Tagen';
    } else if (diffDays === 3) {
        return 'vor drei Tagen';
    } else if (diffDays <= 7) {
        return `vor ${diffDays} Tagen`;
    } else if (diffDays <= 14) {
        return 'vor einer Woche';
    } else if (diffDays <= 21) {
        return 'vor zwei Wochen';
    } else if (diffDays <= 30) {
        return 'vor drei Wochen';
    } else if (diffDays <= 60) {
        return 'vor einem Monat';
    } else if (diffDays <= 90) {
        return 'vor zwei Monaten';
    } else if (diffDays <= 180) {
        return 'vor drei Monaten';
    } else if (diffDays <= 365) {
        const months = Math.floor(diffDays / 30);
        return `vor ${months} Monaten`;
    } else {
        const years = Math.floor(diffDays / 365);
        return years === 1 ? 'vor einem Jahr' : `vor ${years} Jahren`;
    }
}

function getDaySeparatorLabel(date) {
    const d = new Date(date);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const msgDay = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const diffDays = Math.floor((today - msgDay) / (1000 * 60 * 60 * 24));
    if (diffDays === 0) return 'Heute';
    if (diffDays === 1) return 'Gestern';
    if (diffDays >= 2 && diffDays < 7) return d.toLocaleDateString('de-DE', { weekday: 'long' });
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function displayChatMessages(comments) {
    const chatTicketContent = document.getElementById('chatTicketContent');
    
    if (comments.length === 0) {
        chatTicketContent.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full min-h-[280px] text-center px-4">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-primary-200/40 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-gray-400 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 12.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <p class="text-base font-semibold text-gray-900 dark:text-primary-200">Noch keine Nachrichten</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-primary-240">Schreiben Sie die erste Nachricht …</p>
            </div>
        `;
        return;
    }
    
    chatTicketContent.innerHTML = '<div class="space-y-1 pb-2">' + comments.map((comment, index) => {
        const isDescription = comment.id === 'description';
        const commentUserId = comment.user_id !== null && comment.user_id !== undefined ? parseInt(comment.user_id) : null;
        const currentUserIdInt = parseInt(currentUserId);
        const isCurrentUser = commentUserId !== null && commentUserId === currentUserIdInt;
        const userName = [comment.vorname, comment.nachname].filter(Boolean).join(' ').trim() || 'Unbekannt';
        const commentDate = new Date(comment.erstellt_datum);
        const timeDisplay = commentDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        const relativeDate = getRelativeDate(comment.erstellt_datum);
        const nachrichtentyp = comment.nachrichtentyp || 'nachricht';
        
        const dayKey = commentDate.toISOString().slice(0, 10);
        const prevDayKey = index > 0 ? (new Date(comments[index - 1].erstellt_datum)).toISOString().slice(0, 10) : null;
        const showDaySeparator = index === 0 || dayKey !== prevDayKey;
        const daySeparatorHtml = showDaySeparator ? '<div class="flex justify-center mt-6 mb-10"><span class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-500/90 dark:bg-gray-600/90 text-white shadow-sm">' + escapeHtml(getDaySeparatorLabel(comment.erstellt_datum)) + '</span></div>' : '';
        
        let showAvatarAndName = true;
        if (index > 0) {
            const prevComment = comments[index - 1];
            const prevUserId = prevComment.user_id !== null && prevComment.user_id !== undefined ? parseInt(prevComment.user_id) : null;
            const commentUserId = comment.user_id !== null && comment.user_id !== undefined ? parseInt(comment.user_id) : null;
            const currentUserIdInt = parseInt(currentUserId);
            
            const sameUser = prevUserId === commentUserId;
            const prevIsCurrentUser = prevUserId !== null && prevUserId === currentUserIdInt;
            const commentIsCurrentUser = commentUserId !== null && commentUserId === currentUserIdInt;
            
            if (sameUser && prevIsCurrentUser === commentIsCurrentUser) {
                const prevCommentDate = new Date(prevComment.erstellt_datum);
                const timeDiff = Math.abs(commentDate - prevCommentDate) / 1000 / 60;
                if (timeDiff < 5) {
                    showAvatarAndName = false;
                }
            }
        }
        
        let avatarHtml = '';
        const logopfad = comment.logopfad || '';
        const userInitials = (comment.vorname ? comment.vorname.substring(0, 1) : '') + (comment.nachname ? comment.nachname.substring(0, 1) : '') || 'U';
        
        if (logopfad && logopfad.startsWith('preset:')) {
            const presetParts = logopfad.split(':');
            let presetColor = presetParts[1] || '#6b7280';
            if (!presetColor.startsWith('#')) {
                presetColor = '#' + presetColor;
            }
            const presetInitials = presetParts[2] || userInitials.toUpperCase();
            avatarHtml = `
                <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold shrink-0" style="background-color: ${escapeHtml(presetColor)};">
                    ${escapeHtml(presetInitials)}
                </div>
            `;
        } else if (logopfad && logopfad !== '') {
            const imageUrl = logopfad.startsWith('http://') || logopfad.startsWith('https://') 
                ? logopfad 
                : '<?php echo BASE_URL; ?>' + logopfad.replace(/^\//, '');
            avatarHtml = `
                <img src="${escapeHtml(imageUrl)}" class="h-8 w-8 rounded-full object-cover shrink-0" alt="${escapeHtml(userName)}" onerror="this.outerHTML='<div class=\\'h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold bg-gray-400 dark:bg-gray-500 shrink-0\\'>${escapeHtml(userInitials.toUpperCase())}</div>'">
            `;
        } else {
            avatarHtml = `
                <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold bg-gray-400 dark:bg-gray-500 shrink-0">
                    ${escapeHtml(userInitials.toUpperCase())}
                </div>
            `;
        }
        avatarHtml = '<span class="shrink-0 cursor-default" title="' + escapeHtml(userName) + '">' + avatarHtml.replace(/\s+/g, ' ').trim() + '</span>';
        
        const hasAttachments = comment.attachments && comment.attachments.length > 0;
        const isDateianhangOnly = comment.kommentar === '[Dateianhang]' && hasAttachments;
        const isAttachmentMessage = (hasAttachments && nachrichtentyp === 'nachricht') || isDateianhangOnly;
        
        let messageBgClass = '';
        let messageBorderClass = '';
        const messageRoundedClass = 'rounded-2xl';
        let displayName = userName;
        let isTodoCompleted = false;
        let todoId = null;
        
        switch(nachrichtentyp) {
            case 'loesung':
                todoId = null;
                if (isCurrentUser) {
                    messageBgClass = 'bg-green-100 dark:bg-green-900/80 text-gray-900 dark:text-gray-100';
                } else {
                    messageBgClass = 'bg-green-200 dark:bg-green-800/80 text-gray-900 dark:text-gray-100';
                }
                break;
            case 'aufgabe':
                todoId = comment.todo_id || null;
                const todoStatus = comment.todo_status || 'offen';
                isTodoCompleted = todoStatus === 'erledigt';
                if (isCurrentUser) {
                    messageBgClass = 'bg-blue-100 dark:bg-blue-900/80 text-gray-900 dark:text-gray-100';
                } else {
                    messageBgClass = 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-gray-100';
                }
                break;
            case 'bestellung':
                if (isCurrentUser) {
                    messageBgClass = 'bg-blue-100 dark:bg-blue-900/80 text-gray-900 dark:text-gray-100';
                } else {
                    messageBgClass = 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-gray-100';
                }
                break;
            default:
                if (isAttachmentMessage) {
                    messageBgClass = 'bg-blue-100 dark:bg-blue-900/80 text-gray-900 dark:text-gray-100';
                    messageBorderClass = 'border border-blue-200 dark:border-blue-800';
                } else if (isCurrentUser) {
                    messageBgClass = 'bg-blue-100 dark:bg-blue-900/80 text-gray-900 dark:text-gray-100';
                } else {
                    messageBgClass = 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-gray-100';
                }
                break;
        }
        
        let attachmentsHtml = '';
        if (hasAttachments) {
            attachmentsHtml = comment.attachments.map(attachment => {
                const fileUrl = '<?php echo BASE_URL; ?>' + (attachment.dateipfad || '').replace(/^\//, '');
                const fileName = attachment.dateiname || 'Unbekannte Datei';
                
                if (isAttachmentMessage || isDateianhangOnly) {
                    const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(fileName);
                    const isPdf = /\.pdf$/i.test(fileName);
                    const isText = /\.(txt|md|log|json|xml|html|css|js|ts|php|py|java|cpp|c|h)$/i.test(fileName);
                    
                    if (isImage) {
                        return `
                            <div class="my-1.5">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white max-w-[12rem] sm:max-w-[16rem] truncate">${escapeHtml(fileName)}</span>
                                    <div class="flex gap-2 shrink-0">
                                        <button onclick="window.open('${escapeHtml(fileUrl)}', '_blank')" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600" title="Vorschau">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                        <a href="${escapeHtml(fileUrl)}" target="_blank" download class="inline-flex items-center justify-center rounded-lg p-2 text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600" title="Herunterladen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 15v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2m-8 1V4m0 12-4-4m4 4 4-4"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="group relative flex items-center justify-center overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 w-full max-w-[75vw] sm:max-w-[420px] max-h-[300px]">
                                    <a href="${escapeHtml(fileUrl)}" target="_blank" class="absolute inset-0 bg-gray-900/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-lg flex items-center justify-center z-10 hover:opacity-100 cursor-pointer" title="Vorschau öffnen">
                                        <span class="inline-flex items-center justify-center rounded-full h-10 w-10 bg-white/30 pointer-events-none">
                                            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </span>
                                    </a>
                                    <img src="${escapeHtml(fileUrl)}" class="max-w-full max-h-[300px] w-auto h-auto object-contain pointer-events-none" alt="${escapeHtml(fileName)}" onerror="this.style.display='none'">
                                </div>
                            </div>
                        `;
                    } else if (isPdf) {
                        return `
                            <div class="my-1.5">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(fileName)}</span>
                                    <div class="flex gap-2">
                                        <button onclick="window.open('${escapeHtml(fileUrl)}', '_blank')" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600" title="Vorschau">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                        <a href="${escapeHtml(fileUrl)}" target="_blank" download class="inline-flex items-center justify-center rounded-lg p-2 text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600" title="Herunterladen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 15v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2m-8 1V4m0 12-4-4m4 4 4-4"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="w-full h-[300px] overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">
                                    <iframe src="${escapeHtml(fileUrl)}#toolbar=0&navpanes=0&scrollbar=0" class="w-full h-full border-0" title="${escapeHtml(fileName)}"></iframe>
                                </div>
                            </div>
                        `;
                    } else if (isText) {
                        return `
                            <div class="my-1.5">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(fileName)}</span>
                                    <div class="flex gap-2">
                                        <button onclick="window.open('${escapeHtml(fileUrl)}', '_blank')" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600" title="Vorschau">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                        <a href="${escapeHtml(fileUrl)}" target="_blank" download class="inline-flex items-center justify-center rounded-lg p-2 text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600" title="Herunterladen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 15v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2m-8 1V4m0 12-4-4m4 4 4-4"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="w-full h-[300px] overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                                    <iframe src="${escapeHtml(fileUrl)}" class="w-full h-full border-0" title="${escapeHtml(fileName)}"></iframe>
                                </div>
                            </div>
                        `;
                    } else {
                        const fileExtension = fileName.split('.').pop().toUpperCase();
                        const fileIcon = getFileIcon(fileExtension);
                        const fileSize = formatFileSize(attachment.dateigroesse || 0);
                        return `
                            <div class="my-1.5">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(fileName)}</span>
                                    <div class="flex gap-2">
                                        <button onclick="window.open('${escapeHtml(fileUrl)}', '_blank')" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600" title="Vorschau">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                        <a href="${escapeHtml(fileUrl)}" target="_blank" download class="inline-flex items-center justify-center rounded-lg p-2 text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600" title="Herunterladen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 15v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2m-8 1V4m0 12-4-4m4 4 4-4"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="w-full h-[300px] overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 flex items-center justify-center p-4">
                                    <div class="text-center">
                                        ${fileIcon}
                                        <p class="text-sm font-medium text-gray-900 dark:text-white mt-2">${escapeHtml(fileName)}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${fileSize} • ${escapeHtml(fileExtension)}</p>
                                        <a href="${escapeHtml(fileUrl)}" target="_blank" class="inline-flex items-center justify-center rounded-lg px-4 py-2 mt-3 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 15v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2m-8 1V4m0 12-4-4m4 4 4-4"/>
                                            </svg>
                                            Datei öffnen
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(fileName);
                    if (isImage) {
                        return `
                            <div class="group relative my-2.5 flex items-center justify-center overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 w-full max-w-[75vw] sm:max-w-[320px] max-h-[240px]">
                                <a href="${escapeHtml(fileUrl)}" target="_blank" class="absolute inset-0 flex h-full w-full items-center justify-center rounded-lg bg-gray-900/50 opacity-0 transition-opacity duration-300 group-hover:opacity-100 hover:opacity-100 cursor-pointer z-10" title="Vorschau öffnen">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/30 pointer-events-none">
                                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </span>
                                </a>
                                <img src="${escapeHtml(fileUrl)}" class="max-w-full max-h-[240px] w-auto h-auto object-contain pointer-events-none" alt="${escapeHtml(fileName)}" onerror="this.style.display='none'">
                            </div>
                        `;
                    } else {
                        const fileSize = formatFileSize(attachment.dateigroesse || 0);
                        const fileExtension = fileName.split('.').pop().toUpperCase();
                        const fileIcon = getFileIcon(fileExtension);
                        return `
                            <div class="flex items-start my-2.5 bg-gray-200 dark:bg-gray-600 rounded-lg p-2">
                                <div class="me-1.5 flex-1">
                                    <span class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white pb-2">
                                        ${fileIcon}
                                        ${escapeHtml(fileName)}
                                    </span>
                                    <span class="flex text-xs font-normal text-gray-700 dark:text-gray-300 gap-2">
                                        ${fileSize}
                                        <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="self-center" width="3" height="4" viewBox="0 0 3 4" fill="none">
                                            <circle cx="1.5" cy="2" r="1.5" fill="#6B7280"/>
                                        </svg>
                                        ${escapeHtml(fileExtension)}
                                    </span>
                                </div>
                                <div class="inline-flex self-center items-center">
                                    <a href="${escapeHtml(fileUrl)}" target="_blank" class="text-gray-900 dark:text-white bg-gray-200 dark:bg-gray-600 border border-transparent hover:bg-gray-300 dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-700 font-medium leading-5 rounded-lg p-2 focus:outline-none" type="button" title="Herunterladen">
                                        <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V4M7 14H5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-2m-1-5-4 5-4-5m9 8h.01"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        `;
                    }
                }
            }).join('');
        }
        
        let messageContent = '';
        
        if (nachrichtentyp === 'aufgabe') {
            messageContent = `
                <div class="rounded-2xl ${messageBgClass} text-sm break-words shadow-md inline-block max-w-[92%] sm:max-w-[85%]">
                    <div class="px-3 py-2.5 flex gap-2.5 items-start">
                        ${(isAdminOrTech || isCurrentUser) ? `
                        <label class="relative flex-shrink-0 mt-0.5 cursor-pointer block w-4 h-4" title="${todoId ? 'Als erledigt markieren' : 'Keine Aufgabe verknüpft'}">
                            <input type="checkbox" id="task-check-${comment.id}" class="sr-only peer"
                                   ${isTodoCompleted ? 'checked' : ''}
                                   onchange="toggleTask(${comment.id}, ${todoId != null && todoId !== '' ? todoId : 'null'}, this.checked)"
                                   ${!todoId ? 'disabled' : ''}>
                            <span class="absolute inset-0 w-4 h-4 rounded-full border-2 border-gray-300 dark:border-gray-500 peer-checked:bg-primary-600 peer-checked:border-primary-600 dark:peer-checked:bg-primary-500 dark:peer-checked:border-primary-500 peer-disabled:opacity-50 peer-disabled:cursor-not-allowed"></span>
                            <svg class="absolute inset-0 m-auto w-2.5 h-2.5 text-white opacity-0 peer-checked:opacity-100 pointer-events-none" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </label>
                        ` : ''}
                        <div class="min-w-0 flex-1">
                            <span class="${isTodoCompleted ? 'line-through opacity-70 text-gray-600 dark:text-gray-400' : ''}">${formatMessageWithLinks(comment.kommentar || '')}</span>
                            <span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">${timeDisplay}</span>
                        </div>
                    </div>
                </div>
            `;
        } else if (isAttachmentMessage || isDateianhangOnly) {
            if (!hasAttachments || attachmentsHtml === '') {
                return '';
            }
            messageContent = `
                <div class="rounded-2xl ${messageBgClass} ${messageBorderClass} p-3 text-sm break-words shadow-md inline-block max-w-[92%] sm:max-w-[85%]">
                    ${attachmentsHtml}
                    <span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">${timeDisplay}</span>
                </div>
            `;
        } else if (nachrichtentyp === 'bestellung') {
            const orderId = comment.order_id || null;
                const orderStatus = comment.order_status || 'Neu';
            const invCidRaw = comment.order_inventar_consumable_id;
            const invCid = (invCidRaw != null && invCidRaw !== '') ? parseInt(String(invCidRaw), 10) : 0;
            const useLagerLink = isAdminOrTech && invCid > 0 && orderStatus === 'Im Lager';
            const bestellungDetailUrl = orderId
                ? (useLagerLink
                    ? '<?php echo BASE_URL; ?>inventory/detail.php?id=' + invCid
                    : '<?php echo BASE_URL; ?>orders/detail.php?id=' + orderId)
                : '';
            const bestellungOpenTitle = orderId ? (useLagerLink ? 'Lager-Artikel öffnen' : 'Bestellung öffnen') : '';
            const commentText = comment.kommentar && comment.kommentar !== '[Dateianhang]'
                ? formatMessageWithLinks(comment.kommentar)
                : '';
            messageContent = `
                <div class="rounded-2xl ${messageBgClass} ${messageBorderClass} p-0 text-sm overflow-hidden border-l-4 border-l-gray-500 dark:border-l-gray-500 shadow-md inline-block max-w-[92%] sm:max-w-[85%] ${orderId ? 'cursor-pointer hover:opacity-95' : ''}" ${orderId ? `onclick="window.location.href='${escapeHtml(bestellungDetailUrl)}'"` : ''} role="${orderId ? 'button' : 'none'}" title="${orderId ? escapeHtml(bestellungOpenTitle) : ''}">
                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200/70 dark:border-gray-600/70">
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/>
                        </svg>
                        <span class="font-medium text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wide">Bestellung</span>
                        ${(comment.order_garantie == 1 || comment.order_garantie === true) ? '<span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-900 dark:bg-amber-900/35 dark:text-amber-100 border border-amber-200 dark:border-amber-800">Garantie</span>' : ''}
                        ${orderId ? `<span class="ml-auto flex-shrink-0">${getOrderStatusBadge(orderStatus)}</span>` : ''}
                    </div>
                    <div class="p-3 break-words">
                        ${commentText ? `${commentText} <span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">${timeDisplay}</span>` : `<span class="text-[10px] text-gray-500 dark:text-gray-400 opacity-70">${timeDisplay}</span>`}
                    </div>
                </div>
            `;
        } else {
            if (comment.kommentar === '[Dateianhang]' && !hasAttachments) {
                return '';
            }
            const showText = !isDateianhangOnly && comment.kommentar && comment.kommentar !== '[Dateianhang]';
            const messageText = isDescription ? formatMessageWithLinks(comment.kommentar) : (showText ? formatMessageWithLinks(comment.kommentar) : '');
            const timeSpan = '<span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">' + timeDisplay + '</span>';
            messageContent = `
                <div class="rounded-2xl ${messageRoundedClass} ${messageBgClass} ${messageBorderClass} p-3 text-sm break-words shadow-md inline-block max-w-[92%] sm:max-w-[85%]">
                    ${messageText}${timeSpan}
                    ${nachrichtentyp === 'loesung' ? '' : ''}
                    ${attachmentsHtml && !isDateianhangOnly && !isAttachmentMessage ? '<div class="mt-2">' + attachmentsHtml + '</div>' : ''}
                </div>
            `;
        }
        
        if (!messageContent || messageContent.trim() === '') {
            return '';
        }
        
        if (isDescription) {
            const descLogopfad = comment.logopfad || '';
            const descInitials = (comment.vorname ? comment.vorname.substring(0, 1) : '') + (comment.nachname ? comment.nachname.substring(0, 1) : '') || 'U';
            let descAvatarHtml = '';
            
            if (descLogopfad && descLogopfad.startsWith('preset:')) {
                const presetParts = descLogopfad.split(':');
                let presetColor = presetParts[1] || '#6b7280';
                if (!presetColor.startsWith('#')) {
                    presetColor = '#' + presetColor;
                }
                const presetInitials = presetParts[2] || descInitials.toUpperCase();
                descAvatarHtml = `
                    <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold shrink-0" style="background-color: ${escapeHtml(presetColor)};">
                        ${escapeHtml(presetInitials)}
                    </div>
                `;
            } else if (descLogopfad && descLogopfad !== '') {
                const imageUrl = descLogopfad.startsWith('http://') || descLogopfad.startsWith('https://') 
                    ? descLogopfad 
                    : '<?php echo BASE_URL; ?>' + descLogopfad.replace(/^\//, '');
                descAvatarHtml = `
                    <img src="${escapeHtml(imageUrl)}" class="h-8 w-8 rounded-full object-cover shrink-0" alt="${escapeHtml(userName)}" onerror="this.outerHTML='<div class=\\'h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold bg-gray-400 dark:bg-gray-500 shrink-0\\'>${escapeHtml(descInitials.toUpperCase())}</div>'">
                `;
            } else {
                descAvatarHtml = `
                    <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold bg-gray-400 dark:bg-gray-500 shrink-0">
                        ${escapeHtml(descInitials.toUpperCase())}
                    </div>
                `;
            }
            descAvatarHtml = '<span class="shrink-0 cursor-default" title="' + escapeHtml(userName) + '">' + descAvatarHtml.replace(/\s+/g, ' ').trim() + '</span>';
            
            // Beschreibung rechts anzeigen, wenn der aktuelle Benutzer der Ersteller ist
            if (isCurrentUser) {
                return daySeparatorHtml + `
<div class="chat-row chat-row-sent flex items-start gap-1.5 w-full flex-row-reverse">
                    ${descAvatarHtml}
                    <div class="text-right min-w-0 flex-1">
                            <div class="rounded-2xl bg-blue-100 dark:bg-blue-900/80 text-gray-900 dark:text-gray-100 p-3 text-sm break-words shadow-md inline-block max-w-[92%] sm:max-w-[85%]">
                                ${formatMessageWithLinks(comment.kommentar)} <span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">${timeDisplay}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                return daySeparatorHtml + `
                    <div class="chat-row chat-row-received flex items-start gap-2 w-full">
                        ${descAvatarHtml}
                        <div class="min-w-0 flex-1">
                            <div class="rounded-2xl bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-gray-100 p-3 text-sm break-words shadow-md inline-block max-w-[92%] sm:max-w-[85%]">
                                ${formatMessageWithLinks(comment.kommentar)} <span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">${timeDisplay}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
        }
        
        if (isCurrentUser) {
            return daySeparatorHtml + `
                <div class="chat-row chat-row-sent flex items-start gap-1.5 w-full flex-row-reverse">
                    ${showAvatarAndName ? avatarHtml : '<div class="w-8"></div>'}
                    <div class="text-right min-w-0 flex-1">
                        ${messageContent}
                    </div>
                </div>
            `;
        } else {
            return daySeparatorHtml + `
                <div class="chat-row chat-row-received flex items-start gap-1.5 w-full">
                    ${showAvatarAndName ? avatarHtml : '<div class="w-8"></div>'}
                    <div class="min-w-0 flex-1">
                        ${messageContent}
                    </div>
                </div>
            `;
        }
    }).join('') + '</div>';
    
    setTimeout(() => {
        chatTicketContent.scrollTop = chatTicketContent.scrollHeight;
    }, 100);
}

function sendChatMessage() {
    if (!selectedChatTicket) return;
    
    // Prüfen ob Ticket abgerechnet ist
    if (selectedChatTicket.abgerechnet === 1 || selectedChatTicket.abgerechnet === '1') {
        if (typeof showToast === 'function') {
            showToast('Zu abgerechneten Tickets können keine Kommentare mehr hinzugefügt werden', 'error');
        }
        return;
    }
    
    const messageInput = getChatMessageInputEl();
    if (!messageInput) return;
    const messageTypeSelect = document.getElementById('message-type-select');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    const nachrichtentyp = messageTypeSelect ? messageTypeSelect.value : 'nachricht';
    if (nachrichtentyp === 'bestellung') {
        if (typeof showToast === 'function') showToast('Bestellungen bitte über den Bestellung-Button anlegen', 'info');
        return;
    }
    
    const sendBtns = [document.getElementById('send-message-btn'), document.getElementById('send-message-btn-desktop')].filter(Boolean);
    sendBtns.forEach(function(b) { b.disabled = true; });
    
    fetch(commentsApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ticket_id: selectedChatTicket.id,
            kommentar: message,
            nachrichtentyp: nachrichtentyp,
            ist_intern: 0
        })
    })
    .then(response => response.json())
    .then(data => {
        try {
            if (data && data.success) {
                getBothChatMessageInputs().forEach(function(el) {
                    el.value = '';
                    var mm = getChatTextareaMinMax(el);
                    el.style.height = 'auto';
                    el.style.height = mm.minH + 'px';
                });
                updateChatMobileCameraSendToggle();
                syncTicketMobileChatInputLayout();
                loadTicketComments(selectedChatTicket.id);
                if (nachrichtentyp === 'loesung' && isAdminOrTech) {
                    openBearbeitungszeitModalForTermin();
                }
                if (nachrichtentyp === 'loesung' || nachrichtentyp === 'bestellung') {
                    try {
                        loadTicket();
                    } catch (uiError) {
                        console.error('Fehler beim Ticket-Reload nach Senden:', uiError);
                    }
                    if (nachrichtentyp === 'loesung' && typeof playTicketClosedSound === 'function') {
                        playTicketClosedSound();
                    }
                }
                if (typeof showToast === 'function') {
                    showToast('Nachricht erfolgreich gesendet', 'success');
                }
                if (nachrichtentyp !== 'nachricht') {
                    setChatMessageType('nachricht', false);
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Senden der Nachricht: ' + ((data && data.error) || 'Unbekannter Fehler'), 'error');
                }
            }
        } catch (uiError) {
            console.error('UI-Fehler nach erfolgreichem Senden:', uiError);
            if (data && data.success && typeof showToast === 'function') {
                showToast('Nachricht erfolgreich gesendet', 'success');
            } else if (typeof showToast === 'function') {
                showToast('Fehler beim Senden der Nachricht: ' + ((data && data.error) || 'Unbekannter Fehler'), 'error');
            }
        } finally {
            sendBtns.forEach(function(b) { b.disabled = false; });
        }
    })
    .catch(error => {
        console.error('Fehler beim Senden der Nachricht:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Senden der Nachricht', 'error');
        }
        sendBtns.forEach(function(b) { b.disabled = false; });
    });
}

function toggleTask(commentId, todoId, isCompleted) {
    if (!todoId || todoId === 'null') {
        if (typeof showToast === 'function') {
            showToast('Todo-ID nicht gefunden', 'error');
        }
        return;
    }
    
    const newStatus = isCompleted ? 'erledigt' : 'offen';
    
    fetch('<?php echo BASE_URL; ?>todos/api/todos.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            todo_id: todoId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (selectedChatTicket) {
                loadTicketComments(selectedChatTicket.id);
            }
            if (isCompleted && typeof playTaskCompletedSound === 'function') {
                playTaskCompletedSound();
            }
            if (typeof showToast === 'function') {
                showToast(isCompleted ? 'Aufgabe als erledigt markiert' : 'Aufgabe wieder geöffnet', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Aktualisieren der Aufgabe: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
            const checkbox = document.querySelector(`input[onchange*="${commentId}"]`);
            if (checkbox) {
                checkbox.checked = !isCompleted;
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Aktualisieren der Aufgabe:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Aktualisieren der Aufgabe', 'error');
        }
        const checkbox = document.querySelector(`input[onchange*="${commentId}"]`);
        if (checkbox) {
            checkbox.checked = !isCompleted;
        }
    });
}

// === Modal Verbrauchsmaterialien für Bestellung (Ticket mit Gerät) ===
let orderConsumablesModalData = [];

function openOrderConsumablesModal() {
    const modal = document.getElementById('orderConsumablesModal');
    const listEl = document.getElementById('order-consumables-list');
    if (!modal || !listEl) return;
    const searchInput = document.getElementById('order-consumables-search');
    if (searchInput) {
        searchInput.value = '';
        searchInput.oninput = null;
        searchInput.onkeydown = function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyOrderConsumables();
            }
        };
    }
    listEl.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade Verbrauchsmaterialien...</div>';
    document.getElementById('order-consumables-apply-btn').disabled = true;
    modal.classList.remove('hidden');
    if (selectedChatTicket && selectedChatTicket.device_id) {
        fetch(consumablesApiUrl + '?action=by_device&device_id=' + encodeURIComponent(selectedChatTicket.device_id))
            .then(response => response.json())
            .then(data => {
                orderConsumablesModalData = (data.success && data.consumables) ? data.consumables : [];
                if (orderConsumablesModalData.length === 0) {
                    listEl.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Keine Verbrauchsmaterialien diesem Gerät zugeordnet.</div>';
                } else {
                    listEl.innerHTML = orderConsumablesModalData.map(c => {
                        const searchText = [c.bezeichnung, c.artikelnummer, c.beschreibung].filter(Boolean).join(' ').toLowerCase();
                        const lager = c.lagerbestand != null ? Number(c.lagerbestand) : 0;
                        const lagerBadge = lager > 0 ? '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300 shrink-0">' + lager + ' auf Lager</span>' : '';
                        const artNr = (c.artikelnummer && c.artikelnummer.trim()) ? '<span class="text-xs text-gray-500 dark:text-primary-210 tabular-nums shrink-0 w-24 text-right">' + escapeHtml(c.artikelnummer) + '</span>' : '<span class="shrink-0 w-24 text-right"></span>';
                        return '<label class="order-consumable-row flex items-center gap-4 px-4 py-3 hover:bg-gray-100 dark:hover:bg-primary-140 cursor-pointer border-b border-gray-100 dark:border-primary-120 last:border-b-0" data-search-text="' + escapeHtml(searchText) + '"><input type="checkbox" class="order-consumable-cb w-5 h-5 rounded-md border-2 border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-primary-600 dark:text-primary-250 focus:ring-2 focus:ring-primary-500/40 focus:ring-offset-0 shrink-0 cursor-pointer" value="' + (c.id || '') + '"><span class="flex-1 min-w-0 text-sm font-medium text-gray-900 dark:text-primary-200 truncate" title="' + escapeHtml(c.bezeichnung || '') + '">' + escapeHtml(c.bezeichnung || '') + '</span>' + artNr + lagerBadge + '</label>';
                    }).join('');
                    const searchInput = document.getElementById('order-consumables-search');
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.oninput = function filterOrderConsumables() {
                            const q = (this.value || '').trim().toLowerCase();
                            listEl.querySelectorAll('.order-consumable-row').forEach(row => {
                                const text = (row.getAttribute('data-search-text') || '').toLowerCase();
                                row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
                            });
                        };
                    }
                }
                document.getElementById('order-consumables-apply-btn').disabled = false;
            })
            .catch(() => {
                listEl.innerHTML = '<div class="px-4 py-6 text-sm text-center text-red-500 dark:text-red-400">Fehler beim Laden.</div>';
                document.getElementById('order-consumables-apply-btn').disabled = false;
            });
    } else {
        listEl.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Kein Gerät hinterlegt.</div>';
        document.getElementById('order-consumables-apply-btn').disabled = false;
    }
}

function closeOrderConsumablesModal() {
    const modal = document.getElementById('orderConsumablesModal');
    if (modal) modal.classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('orderConsumablesModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeOrderConsumablesModal();
        }
    }
});

function applyOrderConsumables() {
    if (!selectedChatTicket) return;
    const searchInput = document.getElementById('order-consumables-search');
    const text = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
    const checked = document.querySelectorAll('#order-consumables-list .order-consumable-cb:checked');
    const selected = [];
    checked.forEach(cb => {
        const id = (cb.getAttribute('value') || '').trim();
        const c = orderConsumablesModalData.find(x => String(x.id) === id);
        if (c && c.bezeichnung) selected.push(c);
    });
    if (!text && selected.length === 0) {
        if (typeof showToast === 'function') showToast('Bitte Bezeichnung eingeben oder Artikel auswählen', 'info');
        return;
    }
    const applyBtn = document.getElementById('order-consumables-apply-btn');
    if (applyBtn) applyBtn.disabled = true;
    var items = [];
    if (text) items.push({ kommentar: text, consumable_id: null });
    selected.forEach(function(c) { items.push({ kommentar: c.bezeichnung || '', consumable_id: c.id || null }); });
    var completed = 0;
    var errors = 0;
    function doNext() {
        if (completed + errors >= items.length) {
            if (searchInput) {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            closeOrderConsumablesModal();
            if (applyBtn) applyBtn.disabled = false;
            loadTicketComments(selectedChatTicket.id);
            loadTicket();
            if (typeof showToast === 'function') {
                if (errors === 0) showToast(items.length === 1 ? '1 Bestellung angelegt' : items.length + ' Bestellungen angelegt', 'success');
                else if (completed > 0) showToast(completed + ' Bestellung(en) angelegt, ' + errors + ' Fehler', 'warning');
                else showToast('Fehler beim Anlegen der Bestellungen', 'error');
            }
            return;
        }
        var item = items[completed + errors];
        var garantieCb = document.getElementById('order-garantie-cb');
        var orderGarantieFlag = garantieCb ? !!garantieCb.checked : false;
        fetch(commentsApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: selectedChatTicket.id,
                kommentar: item.kommentar,
                nachrichtentyp: 'bestellung',
                ist_intern: 0,
                consumable_id: item.consumable_id,
                garantie: orderGarantieFlag
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) completed++; else errors++;
            doNext();
        })
        .catch(function() { errors++; doNext(); });
    }
    doNext();
}

function manualOrderEntry() {
    closeOrderConsumablesModal();
    const messageInput = getChatMessageInputEl();
    if (messageInput && messageInput.focus) messageInput.focus();
}

function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function getFileIcon(extension) {
    const ext = (extension || '').toLowerCase();
    if (ext === 'pdf') {
        return `
            <svg fill="none" aria-hidden="true" class="w-5 h-5 shrink-0" viewBox="0 0 20 21">
                <g clip-path="url(#clip0_3173_1381)">
                    <path fill="#E2E5E7" d="M5.024.5c-.688 0-1.25.563-1.25 1.25v17.5c0 .688.562 1.25 1.25 1.25h12.5c.687 0 1.25-.563 1.25-1.25V5.5l-5-5h-8.75z"/>
                    <path fill="#B0B7BD" d="M15.024 5.5h3.75l-5-5v3.75c0 .688.562 1.25 1.25 1.25z"/>
                    <path fill="#CAD1D8" d="M18.774 9.25l-3.75-3.75h3.75v3.75z"/>
                    <path fill="#F15642" d="M16.274 16.75a.627.627 0 01-.625.625H1.899a.627.627 0 01-.625-.625V10.5c0-.344.281-.625.625-.625h13.75c.344 0 .625.281.625.625v6.25z"/>
                    <path fill="#fff" d="M3.998 12.342c0-.165.13-.345.34-.345h1.154c.65 0 1.235.435 1.235 1.269 0 .79-.585 1.23-1.235 1.23h-.834v.66c0 .22-.14.344-.32.344a.337.337 0 01-.34-.344v-2.814zm.66.284v1.245h.834c.335 0 .6-.295.6-.605 0-.35-.265-.64-.6-.64h-.834zM7.706 15.5c-.165 0-.345-.09-.345-.31v-2.838c0-.18.18-.31.345-.31H8.85c2.284 0 2.234 3.458.045 3.458h-1.19zm.315-2.848v2.239h.83c1.349 0 1.409-2.24 0-2.24h-.83zM11.894 13.486h1.274c.18 0 .36.18.36.355 0 .165-.18.3-.36.3h-1.274v1.049c0 .175-.124.31-.3.31-.22 0-.354-.135-.354-.31v-2.839c0-.18.135-.31.355-.31h1.754c.22 0 .35.13.35.31 0 .16-.13.34-.35.34h-1.455v.795z"/>
                    <path fill="#CAD1D8" d="M15.649 17.375H3.774V18h11.875a.627.627 0 00.625-.625v-.625a.627.627 0 01-.625.625z"/>
                </g>
                <defs>
                    <clipPath id="clip0_3173_1381">
                        <path fill="#fff" d="M0 0h20v20H0z" transform="translate(0 .5)"/>
                    </clipPath>
                </defs>
            </svg>
        `;
    }
    return `
        <svg class="w-5 h-5 shrink-0 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
    `;
}

var attachmentModalEscapeHandler = null;

function openAttachmentModal(commentId) {
    selectedCommentIdForAttachment = commentId;
    const modal = document.getElementById('attachmentUploadModal');
    if (modal) {
        modal.classList.remove('hidden');
        resetDropzoneFileInputAttrs();
        const fileInput = document.getElementById('dropzone-file');
        if (fileInput) {
            fileInput.value = '';
        }
        clearSelectedFile();
        attachmentModalEscapeHandler = function(e) {
            if (e.key === 'Escape') {
                closeAttachmentModal();
                document.removeEventListener('keydown', attachmentModalEscapeHandler);
                attachmentModalEscapeHandler = null;
            }
        };
        document.addEventListener('keydown', attachmentModalEscapeHandler);
    }
}

function closeAttachmentModal() {
    const modal = document.getElementById('attachmentUploadModal');
    if (modal) {
        modal.classList.add('hidden');
    }
    resetDropzoneFileInputAttrs();
    if (attachmentModalEscapeHandler) {
        document.removeEventListener('keydown', attachmentModalEscapeHandler);
        attachmentModalEscapeHandler = null;
    }
    selectedCommentIdForAttachment = null;
    clearSelectedFile();
    if (openBearbeitungszeitAfterAttachmentClose && isAdminOrTech) {
        openBearbeitungszeitAfterAttachmentClose = false;
        openBearbeitungszeitModalForTermin();
    } else {
        openBearbeitungszeitAfterAttachmentClose = false;
    }
}

function clearSelectedFile() {
    const fileInput = document.getElementById('dropzone-file');
    const filesList = document.getElementById('selected-files-list');
    const uploadBtn = document.getElementById('upload-btn');
    const dropzoneLabel = document.getElementById('dropzone-label');
    
    if (fileInput) fileInput.value = '';
    selectedFiles = [];
    if (filesList) filesList.innerHTML = '';
    if (uploadBtn) uploadBtn.disabled = true;
    if (dropzoneLabel) {
        dropzoneLabel.classList.remove('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900');
        dropzoneLabel.classList.add('border-gray-300', 'dark:border-gray-600');
    }
}

function removeFileFromList(index) {
    selectedFiles.splice(index, 1);
    updateFilesList();
}

function updateFilesList() {
    const filesList = document.getElementById('selected-files-list');
    const uploadBtn = document.getElementById('upload-btn');
    const dropzoneLabel = document.getElementById('dropzone-label');
    
    if (!filesList) return;
    
    if (selectedFiles.length === 0) {
        filesList.innerHTML = '';
        if (uploadBtn) uploadBtn.disabled = true;
        if (dropzoneLabel) {
            dropzoneLabel.classList.remove('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900');
            dropzoneLabel.classList.add('border-gray-300', 'dark:border-gray-600');
        }
        return;
    }
    
    filesList.innerHTML = `
        <div class="grid grid-cols-2 gap-1.5">
            ${selectedFiles.map((file, index) => `
                <div class="flex items-center gap-1.5 p-1.5 bg-gray-100 dark:bg-gray-700 rounded">
                    <svg class="w-3.5 h-3.5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <span class="text-xs font-medium text-gray-900 dark:text-white block truncate" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400">${formatFileSize(file.size)}</span>
                    </div>
                    <button type="button" onclick="removeFileFromList(${index})" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 flex-shrink-0 p-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `).join('')}
        </div>
    `;
    
    if (uploadBtn) {
        uploadBtn.disabled = false;
        uploadBtn.textContent = selectedFiles.length > 1 ? `${selectedFiles.length} Dateien hochladen` : 'Hochladen';
    }
    if (dropzoneLabel) {
        dropzoneLabel.classList.remove('border-gray-300', 'dark:border-gray-600');
        dropzoneLabel.classList.add('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900');
    }
}

function handleFileSelect(files) {
    if (!files || files.length === 0) return;
    
    Array.from(files).forEach(file => {
        const exists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
        if (!exists) {
            selectedFiles.push(file);
        }
    });
    
    updateFilesList();
}

function uploadAttachment() {
    if (!selectedChatTicket) {
        if (typeof showToast === 'function') {
            showToast('Kein Ticket ausgewählt', 'error');
        }
        return;
    }
    
    if (selectedFiles.length === 0) {
        const fileInput = document.getElementById('dropzone-file');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            if (typeof showToast === 'function') {
                showToast('Bitte wählen Sie mindestens eine Datei aus', 'error');
            }
            return;
        }
        handleFileSelect(fileInput.files);
    }
    
    if (selectedFiles.length === 0) {
        if (typeof showToast === 'function') {
            showToast('Bitte wählen Sie mindestens eine Datei aus', 'error');
        }
        return;
    }
    
    const uploadBtn = document.getElementById('upload-btn');
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Wird hochgeladen...';
    }
    
    // Beim Hochladen eines Anhangs immer 'nachricht' verwenden, nicht den ausgewählten Typ
    const nachrichtentyp = 'nachricht';

    const totalBytes = selectedFiles.reduce((sum, file) => sum + (file && file.size ? file.size : 0), 0);
    const loadedByFile = new Array(selectedFiles.length).fill(0);
    const updateProgressToast = function() {
        const loadedBytes = loadedByFile.reduce((sum, value) => sum + value, 0);
        const progressPercent = totalBytes > 0 ? (loadedBytes / totalBytes) * 100 : 0;
        if (typeof updateUploadProgressToast === 'function') {
            updateUploadProgressToast(progressPercent, selectedFiles.length > 1 ? 'Dateien werden hochgeladen...' : 'Datei wird hochgeladen...');
        }
    };

    if (typeof showUploadProgressToast === 'function') {
        showUploadProgressToast(
            selectedFiles.length > 1 ? 'Dateien werden hochgeladen...' : 'Datei wird hochgeladen...',
            0
        );
    }

    const uploadPromises = selectedFiles.map((file, index) => {
        return fetch(commentsApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: selectedChatTicket.id,
                kommentar: '[Dateianhang]',
                nachrichtentyp: nachrichtentyp,
                ist_intern: 0
            })
        })
        .then(response => response.json())
        .then(commentData => {
            if (!commentData.success || !commentData.comment_id) {
                throw new Error('Fehler beim Erstellen des Kommentars: ' + (commentData.error || 'Unbekannter Fehler'));
            }
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('comment_id', commentData.comment_id);

            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', commentAttachmentsApiUrl, true);

                xhr.upload.onprogress = function(event) {
                    if (!event.lengthComputable) return;
                    loadedByFile[index] = event.loaded;
                    updateProgressToast();
                };

                xhr.onreadystatechange = function() {
                    if (xhr.readyState !== 4) return;
                    if (xhr.status < 200 || xhr.status >= 300) {
                        reject(new Error('Fehler beim Hochladen von ' + file.name + ' (HTTP ' + xhr.status + ')'));
                        return;
                    }

                    try {
                        const attachmentData = JSON.parse(xhr.responseText || '{}');
                        if (!attachmentData.success) {
                            reject(new Error('Fehler beim Hochladen von ' + file.name + ': ' + (attachmentData.error || 'Unbekannter Fehler')));
                            return;
                        }
                        loadedByFile[index] = file && file.size ? file.size : loadedByFile[index];
                        updateProgressToast();
                        resolve({ success: true, fileName: file.name });
                    } catch (parseError) {
                        reject(new Error('Ungültige Server-Antwort beim Hochladen von ' + file.name));
                    }
                };

                xhr.onerror = function() {
                    reject(new Error('Netzwerkfehler beim Hochladen von ' + file.name));
                };

                xhr.send(formData);
            });
        });
    });
    
    Promise.all(uploadPromises)
    .then(results => {
        if (typeof updateUploadProgressToast === 'function') {
            updateUploadProgressToast(100, 'Upload abgeschlossen');
        }
        if (typeof hideUploadProgressToast === 'function') {
            window.setTimeout(hideUploadProgressToast, 320);
        }
        closeAttachmentModal();
        if (selectedChatTicket) {
            loadTicketComments(selectedChatTicket.id);
            // Wenn Lösung oder Bestellung hochgeladen wurde, Ticket neu laden um Status zu aktualisieren
            if (nachrichtentyp === 'loesung' || nachrichtentyp === 'bestellung') {
                loadTicket();
                if (nachrichtentyp === 'loesung' && typeof playTicketClosedSound === 'function') {
                    playTicketClosedSound();
                }
            }
        }
        const successCount = results.filter(r => r.success).length;
        if (typeof showToast === 'function') {
            showToast(successCount > 1 ? `${successCount} Dateien erfolgreich hochgeladen` : 'Datei erfolgreich hochgeladen', 'success');
        }
    })
    .catch(error => {
        console.error('Fehler beim Hochladen:', error);
        if (typeof hideUploadProgressToast === 'function') {
            hideUploadProgressToast();
        }
        if (typeof showToast === 'function') {
            showToast('Fehler beim Hochladen: ' + error.message, 'error');
        }
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.textContent = selectedFiles.length > 1 ? `${selectedFiles.length} Dateien hochladen` : 'Hochladen';
        }
    });
}

function formatDateTime(dateTimeString) {
    if (!dateTimeString) return '-';
    const date = new Date(dateTimeString);
    return date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/** Format: "19.01.2026, 09:20" oder "19.01.2026, 09:20 - 09:30" (gleicher Tag) bzw. "19.01.2026, 09:20 - 22.01.2026, 17:00" (mehrere Tage). omitYear=true: nur TT.MM. (z. B. in Termin-Übersicht) */
function formatDateTimeRange(startString, endString, omitYear) {
    if (!startString) return '-';
    const start = new Date(startString);
    const dateOpts = omitYear ? { day: '2-digit', month: '2-digit' } : { day: '2-digit', month: '2-digit', year: 'numeric' };
    const dateStr = start.toLocaleDateString('de-DE', dateOpts);
    const timeOpts = { hour: '2-digit', minute: '2-digit' };
    const startTime = start.toLocaleTimeString('de-DE', timeOpts);
    if (!endString) {
        const endDefault = new Date(start.getTime());
        endDefault.setHours(endDefault.getHours() + 1);
        return dateStr + ', ' + startTime + ' - ' + endDefault.toLocaleTimeString('de-DE', timeOpts);
    }
    if (endString === startString) return dateStr + ', ' + startTime;
    const end = new Date(endString);
    const endTime = end.toLocaleTimeString('de-DE', timeOpts);
    const sameDay = start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth() && start.getDate() === end.getDate();
    if (sameDay) return dateStr + ', ' + startTime + ' - ' + endTime;
    const endDateStr = end.toLocaleDateString('de-DE', dateOpts);
    return dateStr + ', ' + startTime + ' – ' + endDateStr + ', ' + endTime;
}

/** Formatiert Datum/Zeit für datetime-local (YYYY-MM-DDTHH:mm) in lokaler Zeit – vermeidet Zeitzonen-Verschiebung */
function formatDateTimeLocal(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const h = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day}T${h}:${min}`;
}

function formatBearbeitungszeit(minuten) {
    if (minuten == null || minuten < 0) return '-';
    const m = parseInt(minuten, 10);
    if (m < 60) return m + ' Min';
    const h = Math.floor(m / 60);
    const rest = m % 60;
    return rest ? h + ' h ' + rest + ' Min' : h + ' h';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/** Baut eine Google-Maps-Such-URL aus Straße, PLZ und Ort (für klickbare Adressen). */
function googleMapsSearchUrlFromParts(adresse, plz, ort) {
    var parts = [];
    var a = adresse != null && String(adresse).trim() ? String(adresse).trim() : '';
    if (a) parts.push(a);
    var p = plz != null && String(plz).trim() ? String(plz).trim() : '';
    var o = ort != null && String(ort).trim() ? String(ort).trim() : '';
    var cityLine = (p && o) ? (p + ' ' + o) : (p || o);
    if (cityLine) parts.push(cityLine);
    if (!parts.length) return '';
    return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(parts.join(', '));
}

function capitalizeFirst(text) {
    if (!text) return '';
    const s = String(text);
    return s.charAt(0).toUpperCase() + s.slice(1);
}

// Funktionen für die Bearbeitung
function loadEditData(ticket) {
    // Kunden laden wenn Firma vorhanden
    if (ticket.company_id) {
        // Firmen-User/Kunde dürfen den Kunden nicht ändern -> Kundenliste nicht laden
        if (userRole !== 'Kunde' && userRole !== 'Firmen-User') {
            loadCustomersForEdit(ticket.company_id);
        }
        loadDevicesForEdit(ticket.company_id, ticket.customer_id);
    }
    
    // Bearbeiter laden
    loadAssignableUsers();
}

function setupEditCards(ticket) {
    // Suchfunktionen
    const customerSearchEdit = document.getElementById('customerSearchEdit');
    if (customerSearchEdit) {
        customerSearchEdit.addEventListener('input', function() {
            filterTable('customerTableBodyEdit', this.value.toLowerCase(), 'customer-row-edit');
        });
    }
    
    const deviceSearchEdit = document.getElementById('deviceSearchEdit');
    if (deviceSearchEdit) {
        deviceSearchEdit.addEventListener('input', function() {
            filterTable('deviceTableBodyEdit', this.value.toLowerCase(), 'device-row-edit');
        });
    }
    
    const assigneeSearchEdit = document.getElementById('assigneeSearchEdit');
    if (assigneeSearchEdit) {
        assigneeSearchEdit.addEventListener('input', function() {
            filterTable('assigneeTableBodyEdit', this.value.toLowerCase(), 'assignee-row-edit');
        });
    }
    
    const observerSearchEdit = document.getElementById('observerSearchEdit');
    if (observerSearchEdit) {
        observerSearchEdit.addEventListener('input', function() {
            filterTable('observerTableBodyEdit', this.value.toLowerCase(), 'observer-row-edit');
        });
    }
}

function loadCustomersForEdit(companyId) {
    const customerTableBodyEdit = document.getElementById('customerTableBodyEdit');
    if (!customerTableBodyEdit) return;
    
    customerTableBodyEdit.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Lade Kunden...</td></tr>';
    
    fetch(customersApiUrl + '?company_id=' + companyId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customers && data.customers.length > 0) {
                const sortedCustomers = data.customers.slice().sort((a, b) => {
                    const nameA = ((a && a.name) || '').trim();
                    const nameB = ((b && b.name) || '').trim();
                    return nameA.localeCompare(nameB, 'de', { sensitivity: 'base' });
                });

                customerTableBodyEdit.innerHTML = sortedCustomers.map(customer => {
                    var name = (customer.name || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                    const customerName = escapeHtml(customer.name || '');
                    const kdnr = customer.kundennummer ? escapeHtml(customer.kundennummer) : '';
                    const str = customer.adresse ? escapeHtml(customer.adresse) : '';
                    const plzOrt = [customer.plz, customer.ort].filter(Boolean).join(' ').trim();
                    const plzOrtEsc = plzOrt ? escapeHtml(plzOrt) : '';
                    const adresseCell = (str || plzOrtEsc)
                        ? `<div class="text-xs text-gray-500 dark:text-primary-210">${str ? `<div>${str}</div>` : ''}${plzOrtEsc ? `<div>${plzOrtEsc}</div>` : ''}</div>`
                        : '–';
                    return `
                        <tr class="customer-row-edit border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" 
                            data-id="${customer.id}" 
                            data-name="${name}"
                            onclick="selectCustomerEdit(this)">
                            <td class="px-3 py-2 text-gray-900 dark:text-white align-top">
                                <div class="font-medium">${customerName}</div>
                                ${kdnr ? `<div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${kdnr}</div>` : ''}
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400 text-xs align-top">${adresseCell}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                customerTableBodyEdit.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Keine Kunden verfügbar</td></tr>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kunden:', error);
            customerTableBodyEdit.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-red-500">Fehler beim Laden</td></tr>';
        });
}

/** Kundenzeile in der Übersicht anzeigen/aktualisieren (sofort nach Kundenauswahl sichtbar) */
function updateCompactBarCustomer(customerName) {
    var name = (customerName || '').trim();
    var row = document.getElementById('compactCustomerRow');
    var textEl = document.getElementById('compactCustomerNameText');
    if (row && textEl) {
        textEl.textContent = name || '--';
        row.style.display = name ? '' : 'none';
        return;
    }
    var root = document.getElementById('overviewAccordionsRoot');
    if (!root || !name) return;
    var firmaSec = document.getElementById('overviewSectionFirma');
    var cid = (typeof selectedChatTicket !== 'undefined' && selectedChatTicket && selectedChatTicket.customer_id)
        ? parseInt(selectedChatTicket.customer_id, 10) : 0;
    var editHtml = !isObserverOnly
        ? '<button type="button" id="customerCompactEditPencilBtn" onclick="event.stopPropagation(); editCompanyCustomerSelection();" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:text-primary-200 dark:hover:text-primary-220 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" title="Kunde zuordnen" aria-label="Kunde zuordnen"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.304 4.844 2.852 2.852M7 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-4.5m2.409-9.91a2.017 2.017 0 0 1 0 2.853l-6.844 6.844L8 14l.713-3.565 6.844-6.844a2.015 2.015 0 0 1 2.852 0Z"/></svg></button>' +
          '<button type="button" id="customerCompactCancelEditBtn" style="display: none;" onclick="event.stopPropagation(); cancelCustomerEdit();" class="p-2 rounded-lg text-gray-400 hover:text-gray-700 dark:text-primary-200 dark:hover:text-primary-220 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" title="Abbrechen" aria-label="Bearbeitung abbrechen"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>'
        : '';
    var actionsHtml = editHtml ? '<div class="flex items-center gap-0.5 shrink-0">' + editHtml + '</div>' : '';
    var html = '<div id="compactCustomerRow" data-overview-card="kunde" class="overview-accordion-section rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-50 overflow-hidden">' +
        '<div class="overview-acc-header flex items-center gap-3 px-3.5 py-3 sm:px-4 min-w-0">' +
        '<div class="flex items-center gap-3 min-w-0 flex-1">' +
        '<span class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-primary-140 text-gray-600 dark:text-primary-210 shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/></svg></span>' +
        '<span class="text-sm font-semibold text-gray-900 dark:text-primary-200 truncate" id="compactCustomerNameText" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</span>' +
        '</div>' +
        actionsHtml +
        '<button type="button" id="overview-acc-btn-kunde" class="overview-acc-toggle flex items-center justify-center w-9 h-9 shrink-0 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-primary-140 dark:text-primary-200 dark:hover:bg-primary-130 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 transition-all duration-200" aria-expanded="false" aria-controls="overview-acc-panel-kunde" title="Details ein- oder ausklappen"><svg class="overview-acc-chevron w-4 h-4 transition-transform duration-200 ease-out" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>' +
        '</div>' +
        '<div id="overview-acc-panel-kunde" class="overview-acc-panel hidden px-4 pb-4 space-y-3 text-sm leading-relaxed border-t border-gray-100 dark:border-primary-120/40 pt-3 bg-gray-50/50 dark:bg-primary-120/10"><div class="flex items-start gap-2 rounded-lg bg-white/80 dark:bg-primary-100/15 px-3 py-2.5 border border-gray-100 dark:border-primary-120/30"><span class="w-8 h-8 shrink-0" aria-hidden="true"></span><p class="text-gray-600 dark:text-primary-220 text-sm m-0 pt-1 min-w-0">Details werden beim nächsten Laden des Auftrags ergänzt.</p></div>' +
        '<div class="overview-card-actions flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 pt-3 border-t border-gray-200/80 dark:border-primary-120/35">' +
        (cid > 0 && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') ? '<a href="' + serviceBaseUrl + 'customers/detail.php?id=' + cid + '" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Kundendetails</a>' : '') +
        (cid > 0 && (userRole === 'Admin' || userRole === 'Firmen-Admin') ? '<a href="' + serviceBaseUrl + 'customers/edit.php?id=' + cid + '" target="_blank" rel="noopener noreferrer" class="overview-action-link" onclick="event.stopPropagation()">Kunde bearbeiten</a>' : '') +
        (!isObserverOnly && isAdminOrTech ? '<button type="button" class="overview-action-link" onclick="event.stopPropagation(); editCompanyCustomerSelection()">Kunde zuordnen</button>' : '') +
        (!isObserverOnly && isAdminOrTech && cid > 0 ? '<button type="button" class="overview-action-link text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" onclick="event.stopPropagation(); removeCustomerFromTicket()">Kunde vom Auftrag entfernen</button>' : '') +
        (!isObserverOnly && cid > 0 && (!selectedChatTicket || !selectedChatTicket.device_name) && (isAdminOrTech || userRole === 'Firmen-Admin') && ticketHasAssignableDevices(selectedChatTicket) ? '<button type="button" class="overview-action-link" onclick="event.stopPropagation(); editCompanyCustomerSelection(false, true)">Gerät zuordnen</button>' : '') +
        '</div></div></div>';
    if (firmaSec) {
        firmaSec.insertAdjacentHTML('afterend', html);
    } else {
        root.insertAdjacentHTML('afterbegin', html);
    }
}

function loadDevicesForEdit(companyId, customerId) {
    const deviceTableBodyEdit = document.getElementById('deviceTableBodyEdit');
    if (!deviceTableBodyEdit) return Promise.resolve(0);
    
    deviceTableBodyEdit.innerHTML = '<tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-gray-400">Lade Geräte...</td></tr>';
    
    const params = [];
    if (customerId) {
        params.push('customer_id=' + encodeURIComponent(customerId));
    } else if (companyId) {
        params.push('company_id=' + encodeURIComponent(companyId));
    } else {
        deviceTableBodyEdit.innerHTML = '<tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-gray-400">Bitte zuerst Kunde auswählen</td></tr>';
        return Promise.resolve(0);
    }
    
    // Wie in create.php: nur aktive Geräte, außer für Firmen-Admin/Admin/Techniker
    const onlyActive = !(userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin');
    if (onlyActive) {
        params.push('only_active=1');
    }
    
    const url = devicesApiUrl + (params.length ? ('?' + params.join('&')) : '');
    
    return fetch(url)
        .then(response => response.json())
        .then(data => {
            const deviceList = (data.success && data.devices) ? data.devices : [];
            if (deviceList.length > 0) {
                deviceTableBodyEdit.innerHTML = deviceList.map(device => {
                    const deviceType = capitalizeFirst(device.typ || '');
                    const makerModel = [device.hersteller, device.modell].filter(Boolean).join(' / ') || '-';
                    const serial = device.seriennummer || '-';
                    const location = device.beschreibung || '-';
                    const deviceUser = [device.user_vorname, device.user_nachname].filter(Boolean).join(' ').trim() || '-';
                    return `
                        <tr class="device-row-edit border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" 
                            data-id="${device.id}" 
                            data-name="${escapeHtml(device.name)}"
                            onclick="selectDeviceEdit(${device.id}, '${escapeHtml(device.name)}')">
                            <td class="px-2 py-1.5 text-gray-900 dark:text-white">
                                <div class="font-semibold">${escapeHtml(device.name || '-')}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(deviceType || '-')}</div>
                            </td>
                            <td class="px-2 py-1.5 text-gray-900 dark:text-white">
                                <div>${escapeHtml(makerModel)}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(serial)}</div>
                            </td>
                            <td class="px-2 py-1.5 text-gray-900 dark:text-white">
                                <div>${escapeHtml(location)}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(deviceUser)}</div>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                deviceTableBodyEdit.innerHTML = '<tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-gray-400">Keine Geräte verfügbar</td></tr>';
            }
            return deviceList.length;
        })
        .catch(error => {
            console.error('Fehler beim Laden der Geräte:', error);
            deviceTableBodyEdit.innerHTML = '<tr><td colspan="3" class="px-2 py-1.5 text-center text-red-500">Fehler beim Laden</td></tr>';
            return 0;
        });
}

function loadAssignableUsers() {
    const assigneeTableBodyEdit = document.getElementById('assigneeTableBodyEdit');
    if (!assigneeTableBodyEdit) return;
    
    assigneeTableBodyEdit.innerHTML = '<tr><td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Lade Bearbeiter…</td></tr>';
    
    fetch(todosApiUrl + '?action=assignable_users&roles=Admin,Techniker')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users) {
                const users = data.users.filter(user => user.rolle === 'Admin' || user.rolle === 'Techniker');
                if (users.length > 0) {
                    assigneeTableBodyEdit.innerHTML = users.map(user => {
                        const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim() || user.email;
                        const nameAttr = escapeHtml(fullName).replace(/"/g, '&quot;');
                        return `
                            <tr class="assignee-row-edit border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" 
                                data-id="${user.id}" 
                                data-name="${nameAttr}"
                                onclick="selectAssigneeEdit(${user.id}, this.getAttribute('data-name'))">
                                <td class="px-3 py-2 text-gray-900 dark:text-white">${escapeHtml(fullName)}</td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    assigneeTableBodyEdit.innerHTML = '<tr><td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Keine Bearbeiter verfügbar</td></tr>';
                }
            } else {
                assigneeTableBodyEdit.innerHTML = '<tr><td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Keine Bearbeiter verfügbar</td></tr>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Bearbeiter:', error);
            assigneeTableBodyEdit.innerHTML = '<tr><td colspan="1" class="px-3 py-2 text-center text-red-500">Fehler beim Laden</td></tr>';
        });
}

function selectCustomerEdit(rowOrId, customerNameParam) {
    var customerId, customerName;
    if (rowOrId && typeof rowOrId.getAttribute === 'function') {
        customerId = parseInt(rowOrId.getAttribute('data-id'), 10);
        customerName = (rowOrId.getAttribute('data-name') || '').trim();
    } else {
        customerId = rowOrId;
        customerName = (customerNameParam || '').trim();
    }
    if (!customerId) return;
    var oldCustomerId = selectedChatTicket ? selectedChatTicket.customer_id : null;
    var customerChanged = (oldCustomerId !== null && oldCustomerId !== customerId);
    var clearDevicePromise = customerChanged ? updateTicketField('device_id', null) : Promise.resolve();
    updateTicketField('customer_id', customerId).then(function() {
        return clearDevicePromise;
    }).then(() => {
        if (selectedChatTicket) {
            selectedChatTicket.customer_id = customerId;
            selectedChatTicket.customer_name = customerName;
            if (customerChanged) {
                selectedChatTicket.device_id = null;
                selectedChatTicket.device_name = null;
            }
        }
        if (customerChanged) {
            var compactDeviceRow = document.getElementById('compactDeviceRow');
            var compactDeviceNameText = document.getElementById('compactDeviceNameText');
            if (compactDeviceRow) compactDeviceRow.style.display = 'none';
            if (compactDeviceNameText) compactDeviceNameText.textContent = '';
        }
        if (selectedChatTicket && selectedChatTicket.company_id) {
            loadDevicesForEdit(selectedChatTicket.company_id, customerId);
        }
        // Kunde sofort in der Compact-Übersicht anzeigen (Zeile einfügen oder aktualisieren)
        updateCompactBarCustomer(customerName);
        // Compact-Leiste sichtbar machen, falls sie während Bearbeiten ausgeblendet war
        var compactContainer = document.getElementById('companyCustomerCompactContainer');
        if (compactContainer && selectedChatTicket) {
            compactContainer.style.display = 'block';
        }
        // Immer: Kunden-Card schließen (Animation) und danach Gerät-Card oder Kompakt-Ansicht anzeigen
        const customerContainer = document.getElementById('customerSelectContainer');
        const deviceContainer = document.getElementById('deviceSelectContainer');
        animateEditCardClose(customerContainer, function() {
            setCustomerCompactEditUi(false);
            if (customerContainer) {
                customerContainer.style.display = 'none';
                customerContainer.classList.remove('is-closing');
            }
            // Nächster Schritt: Gerät-Card nur anzeigen, wenn der Kunde Geräte hat
            if (deviceContainer && selectedChatTicket && selectedChatTicket.company_id) {
                var promise = loadDevicesForEdit(selectedChatTicket.company_id, selectedChatTicket.customer_id);
                if (promise && typeof promise.then === 'function') {
                    promise.then(function(deviceCount) {
                        if (deviceCount > 0) {
                            closeAllOverviewAccordionPanels();
                            mountDeviceEditUnderGeraetRow();
                            deviceContainer.style.display = 'block';
                            deviceContainer.classList.remove('is-closing');
                            requestAnimationFrame(function() {
                                deviceContainer.classList.add('is-visible');
                                deviceContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                setDeviceCompactEditUi(true);
                            });
                        } else {
                            setDeviceCompactEditUi(false);
                            deviceContainer.style.display = 'none';
                            returnEditPanelToPool('deviceSelectContainer');
                            returnEditPanelToPool('customerSelectContainer');
                            setCustomerCompactEditUi(false);
                            var compactContainer = document.getElementById('companyCustomerCompactContainer');
                            if (compactContainer && selectedChatTicket) {
                                compactContainer.style.display = 'block';
                                compactContainer.classList.add('compact-enter');
                            }
                        }
                    });
                } else {
                    closeAllOverviewAccordionPanels();
                    mountDeviceEditUnderGeraetRow();
                    deviceContainer.style.display = 'block';
                    deviceContainer.classList.remove('is-closing');
                    requestAnimationFrame(function() {
                        deviceContainer.classList.add('is-visible');
                        deviceContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        setDeviceCompactEditUi(true);
                    });
                }
            } else {
                var compactContainer = document.getElementById('companyCustomerCompactContainer');
                if (compactContainer && selectedChatTicket) {
                    compactContainer.style.display = 'block';
                    compactContainer.classList.add('compact-enter');
                }
            }
        });
    }).catch(function(err) {
        console.error('Kundenauswahl fehlgeschlagen:', err);
        if (typeof showToast === 'function') showToast('Kunde konnte nicht gesetzt werden.', 'error');
    });
}

function selectDeviceEdit(deviceId, deviceName) {
    updateTicketField('device_id', deviceId).then(() => {
        if (selectedChatTicket) {
            selectedChatTicket.device_id = deviceId;
            selectedChatTicket.device_name = deviceName;
        }
        const compactDeviceNameText = document.getElementById('compactDeviceNameText');
        const compactDeviceRow = document.getElementById('compactDeviceRow');
        if (compactDeviceNameText) {
            compactDeviceNameText.textContent = deviceName;
        }
        if (compactDeviceRow) {
            compactDeviceRow.style.display = '';
        }
        const deviceContainer = document.getElementById('deviceSelectContainer');
        const needFullReload = !compactDeviceRow || !compactDeviceNameText;
        animateEditCardClose(deviceContainer, function() {
            if (needFullReload) {
                loadTicket();
            } else {
                const compactContainer = document.getElementById('companyCustomerCompactContainer');
                if (compactContainer && selectedChatTicket) {
                    compactContainer.style.display = 'block';
                    compactContainer.classList.add('compact-enter');
                }
                cancelDeviceEdit();
            }
        });
    });
}

function selectAssigneeEdit(userId, userName) {
    updateTicketField('zugewiesen_an', userId).then(() => {
        // Kompakte Card anzeigen und Edit-Card verstecken (ruft automatisch loadTicket() auf)
        cancelAssigneeEdit();
    });
}

function clearAssigneeEdit() {
    if (!canSetAssignee) return;
    updateTicketField('zugewiesen_an', null).then(() => {
        const assigneePanel = document.getElementById('assigneeExpandPanel');
        const observerPanel = document.getElementById('observerExpandPanel');
        if (assigneePanel) assigneePanel.classList.remove('is-expanded');
        if (observerPanel) observerPanel.classList.remove('is-expanded');
        if (selectedChatTicket) loadTicket();
    });
}

function updateTicketField(field, value) {
    if (isObserverOnly) {
        if (typeof showToast === 'function') {
            showToast('Nur Ansicht: Als Beobachter kannst du nichts bearbeiten.', 'error');
        }
        return Promise.reject('observer_only');
    }
    
    // Prüfen ob Ticket abgerechnet ist - dann keine Updates mehr erlaubt (außer abgerechnet-Feld und bearbeitungszeit_minuten)
    if (selectedChatTicket && (selectedChatTicket.abgerechnet === 1 || selectedChatTicket.abgerechnet === '1') && field !== 'abgerechnet' && field !== 'bearbeitungszeit_minuten') {
        if (typeof showToast === 'function') {
            showToast('Abgerechnete Tickets können nicht mehr geändert werden', 'error');
        }
        return Promise.reject('ticket_abgerechnet');
    }
    return fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ticket_id: ticketId,
            [field]: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (field === 'status' && value === 'Geschlossen' && typeof playTicketClosedSound === 'function') {
                playTicketClosedSound();
            }
            if (field === 'status') {
                // Status-Buttons aktualisieren
                updateStatusButtons(value);
                // Chat-Header: nur Status-Badge aktualisieren (kompaktes Layout beibehalten)
                if (selectedChatTicket) {
                    selectedChatTicket.status = value;
                    const chatHeader = document.getElementById('chatTicketHeader');
                    if (chatHeader) {
                        const statusBadge = chatHeader.querySelector('span[class*="rounded-full"]');
                        if (statusBadge) {
                            statusBadge.className = 'px-3 py-1 text-sm font-semibold rounded-full ' + getStatusBadgeClass(value);
                            statusBadge.textContent = getStatusText(value);
                        }
                    }
                    syncTicketMobileNav(selectedChatTicket);
                }
            }
            if (field === 'titel' && selectedChatTicket) {
                selectedChatTicket.titel = value;
                const breadcrumbLabel = document.getElementById('breadcrumbTicketLabel');
                if (breadcrumbLabel) {
                    const full = selectedChatTicket.ticket_nummer && value ? (selectedChatTicket.ticket_nummer + ': ' + value) : (selectedChatTicket.ticket_nummer || value || 'Ticket');
                    breadcrumbLabel.textContent = full.length > 48 ? full.slice(0, 45) + '…' : full;
                }
                const chatHeader = document.getElementById('chatTicketHeader');
                if (chatHeader) {
                    const h2 = chatHeader.querySelector('h2');
                    if (h2) h2.textContent = value || '(ohne Betreff)';
                }
                syncTicketMobileNav(selectedChatTicket);
            }
            if (typeof showToast === 'function') {
                showToast('Erfolgreich aktualisiert', 'success');
            }
            return Promise.resolve();
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
            return Promise.reject(data.error);
        }
    })
    .catch(error => {
        console.error('Fehler beim Aktualisieren:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Aktualisieren', 'error');
        }
        return Promise.reject(error);
    });
}

function updateStatusButtonsInHeader(activeStatus) {
    const statusButtonGroup = document.getElementById('statusButtonGroup');
    if (statusButtonGroup) {
        statusButtonGroup.classList.remove('hidden');
        const statusButtons = statusButtonGroup.querySelectorAll('.status-btn');
        
        // Prüfen ob Ticket abgerechnet ist - dann Buttons deaktivieren
        const isAbgerechnet = selectedChatTicket && (selectedChatTicket.abgerechnet === 1 || selectedChatTicket.abgerechnet === '1');
        const activeClasses = ['bg-primary-820', 'text-white', 'border-primary-700', 'dark:bg-primary-800', 'dark:border-primary-820', 'dark:text-primary-840'];
        const inactiveClasses = ['bg-gray-50', 'text-gray-900', 'border-gray-300', 'hover:bg-gray-100', 'dark:bg-primary-700', 'dark:border-primary-720', 'dark:text-primary-210', 'dark:hover:bg-primary-760'];
        statusButtons.forEach(btn => {
            if (isAbgerechnet) {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            const btnStatus = btn.textContent.trim();
            if (btnStatus === activeStatus) {
                btn.classList.remove(...inactiveClasses);
                btn.classList.add(...activeClasses);
            } else {
                btn.classList.remove(...activeClasses);
                btn.classList.add(...inactiveClasses);
            }
        });
    }
}

function updateStatusButtons(activeStatus) {
    updateStatusButtonsInHeader(activeStatus);
}

function getBearbeitungszeitValue() {
    const custom = document.getElementById('bearbeitungszeitCustom');
    if (custom && custom.value !== '') {
        const raw = String(custom.value).trim();
        if (raw !== '') {
            const n = custom.valueAsNumber;
            if (typeof n === 'number' && !Number.isNaN(n) && n >= 0) return Math.floor(n);
            const p = parseInt(raw, 10);
            if (!Number.isNaN(p) && p >= 0) return p;
        }
    }
    const sel = document.querySelector('.bearbeitungszeit-preset[data-selected="1"]');
    return sel ? parseInt(sel.getAttribute('data-min'), 10) : null;
}

function setBearbeitungszeitPresetActive(btn) {
    document.querySelectorAll('.bearbeitungszeit-preset').forEach(b => {
        b.removeAttribute('data-selected');
        b.classList.remove('bg-primary-600', 'text-white');
        b.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    });
    if (btn) {
        btn.setAttribute('data-selected', '1');
        btn.classList.add('bg-primary-600', 'text-white');
        btn.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    }
}

function openBearbeitungszeitModalForTermin() {
    const modal = document.getElementById('bearbeitungszeitModal');
    if (!modal) {
        return false;
    }
    setBearbeitungszeitPresetActive(null);
    const cu = document.getElementById('bearbeitungszeitCustom');
    if (cu) cu.value = '';
    modal.classList.remove('hidden');
    return true;
}

function closeBearbeitungszeitModal() {
    document.getElementById('bearbeitungszeitModal').classList.add('hidden');
}

function toggleAnfordererSelection() {
    const panel = document.getElementById('anfordererExpandPanel');
    const assigneePanel = document.getElementById('assigneeExpandPanel');
    const observerPanel = document.getElementById('observerExpandPanel');
    if (assigneePanel) {
        assigneePanel.classList.remove('is-expanded');
        updateAssigneeEditButtonIcon(false);
    }
    if (observerPanel) {
        observerPanel.classList.remove('is-expanded');
        updateObserverEditButtonIcon(false);
    }
    if (panel) {
        const expanded = panel.classList.contains('is-expanded');
        if (expanded) {
            panel.classList.remove('is-expanded');
        } else {
            panel.classList.add('is-expanded');
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

function openAnfordererModal() {
    const modal = document.getElementById('anfordererModal');
    const body = document.getElementById('anfordererModalBody');
    if (!modal || !body || !selectedChatTicket) return;
    const t = selectedChatTicket;
    const name = [t.ersteller_vorname || '', t.ersteller_nachname || ''].filter(Boolean).join(' ').trim() || 'Unbekannt';
    const email = t.ersteller_email || '';
    const logopfad = t.ersteller_logopfad || '';
    const initials = (t.ersteller_vorname ? t.ersteller_vorname.substring(0, 1) : '') + (t.ersteller_nachname ? t.ersteller_nachname.substring(0, 1) : '') || '?';
    let avatarHtml = '';
    if (logopfad && logopfad.startsWith('preset:')) {
        const parts = logopfad.split(':');
        let color = parts[1] || '#6b7280';
        if (!color.startsWith('#')) color = '#' + color;
        const ini = parts[2] || initials.toUpperCase();
        avatarHtml = '<div class="w-14 h-14 rounded-full flex items-center justify-center text-white text-lg font-semibold shrink-0" style="background-color:' + (color.replace(/"/g, '&quot;')) + '">' + escapeHtml(ini) + '</div>';
    } else if (logopfad && logopfad !== '') {
        const imgUrl = (logopfad.startsWith('http') ? logopfad : '<?php echo BASE_URL; ?>' + logopfad.replace(/^\//, ''));
        avatarHtml = '<div class="shrink-0 relative"><img src="' + escapeHtml(imgUrl) + '" alt="" class="w-14 h-14 rounded-full object-cover" onerror="this.style.display=\'none\';if(this.nextElementSibling)this.nextElementSibling.style.display=\'flex\'"><div class="w-14 h-14 rounded-full bg-gray-200 dark:bg-primary-200/40 flex items-center justify-center text-gray-600 dark:text-primary-220 text-lg font-semibold" style="display:none">' + escapeHtml(initials.toUpperCase()) + '</div></div>';
    } else {
        avatarHtml = '<div class="w-14 h-14 rounded-full bg-gray-200 dark:bg-primary-200/40 flex items-center justify-center text-gray-600 dark:text-primary-220 text-lg font-semibold shrink-0">' + escapeHtml(initials.toUpperCase()) + '</div>';
    }
    body.innerHTML = '<div class="flex items-start gap-4">' +
        avatarHtml +
        '<div class="min-w-0 flex-1 space-y-2">' +
        '<p class="font-semibold text-gray-900 dark:text-primary-200 text-base">' + escapeHtml(name) + '</p>' +
        (email ? '<p class="text-gray-600 dark:text-primary-220"><a href="mailto:' + escapeHtml(email) + '" class="text-primary-600 dark:text-primary-250 hover:underline">' + escapeHtml(email) + '</a></p>' : '') +
        '</div></div>';
    modal.classList.remove('hidden');
}

function closeAnfordererModal() {
    const modal = document.getElementById('anfordererModal');
    if (modal) modal.classList.add('hidden');
}

function confirmBearbeitungszeit() {
    const min = getBearbeitungszeitValue();
    const minutes = min != null ? min : null;
    closeBearbeitungszeitModal();
    if (!selectedChatTicket) return;
    const payload = { ticket_id: selectedChatTicket.id, bearbeitungszeit_minuten: minutes };
    fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (selectedChatTicket) selectedChatTicket.bearbeitungszeit_minuten = minutes;
            loadTicket();
            if (typeof showToast === 'function') showToast('Bearbeitungszeit gespeichert', 'success');
        } else {
            if (typeof showToast === 'function') showToast('Fehler: ' + (data.error || 'Unbekannt'), 'error');
        }
    })
    .catch(err => {
        console.error(err);
        if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
    });
}

function editDatesSelection() {
    if (isObserverOnly) return;
    if (!canSetPlannedDate) {
        // Nicht-Admin/Techniker: nur Fällig bearbeiten
        const faelligContainer = document.getElementById('faelligDatumContainer');
        if (faelligContainer) faelligContainer.style.display = 'block';
        return;
    }
    // Öffnet beide Cards wenn beide Daten vorhanden sind
    const faelligContainer = document.getElementById('faelligDatumContainer');
    const geplantContainer = document.getElementById('geplantDatumContainer');
    
    if (faelligContainer) {
        faelligContainer.style.display = 'block';
    }
    
    if (geplantContainer) {
        geplantContainer.style.display = 'block';
    }
}

function addFaelligDatum() {
    if (isObserverOnly) return;
    const faelligContainer = document.getElementById('faelligDatumContainer');
    if (faelligContainer) {
        faelligContainer.style.display = 'block';
        // Fälligkeits-Datum-Feld fokussieren
        setTimeout(() => {
            const faelligInput = document.getElementById('editFaelligDatum');
            if (faelligInput) {
                faelligInput.focus();
                faelligInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    }
}

function addGeplantDatum() {
    if (!canSetPlannedDate) return;
    const geplantContainer = document.getElementById('geplantDatumContainer');
    if (geplantContainer) {
        geplantContainer.style.display = 'block';
        // Geplant-Datum-Feld fokussieren
        setTimeout(() => {
            const geplantInput = document.getElementById('editGeplantDatum');
            if (geplantInput) {
                geplantInput.focus();
                geplantInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    }
}

function closeFaelligEdit() {
    const faelligContainer = document.getElementById('faelligDatumContainer');
    if (faelligContainer) {
        faelligContainer.style.display = 'none';
    }
    loadTicket();
}

function closeGeplantEdit() {
    if (!canSetPlannedDate) return;
    const geplantContainer = document.getElementById('geplantDatumContainer');
    if (geplantContainer) {
        geplantContainer.style.display = 'none';
    }
    loadTicket();
}

function updateFaelligDatum() {
    const faelligDatum = document.getElementById('editFaelligDatum').value;
    const faelligDatumEndeEl = document.getElementById('editFaelligDatumEnde');
    const faelligDatumEnde = faelligDatumEndeEl ? faelligDatumEndeEl.value : '';
    
    const updateData = {
        ticket_id: ticketId
    };
    
    if (faelligDatum) {
        updateData.faellig_datum = faelligDatum;
    } else {
        updateData.faellig_datum = null;
    }
    updateData.faellig_datum_ende = (faelligDatumEnde && faelligDatumEnde.trim() !== '') ? faelligDatumEnde : null;
    
    fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updateData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Fälligkeitsdatum erfolgreich aktualisiert', 'success');
            }
            loadTicket();
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Aktualisieren des Fälligkeitsdatums:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Aktualisieren des Fälligkeitsdatums', 'error');
        }
    });
}

function markAsBilled() {
    if (!ticketId) return;
    
    fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ticket_id: ticketId,
            abgerechnet: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Ticket als abgerechnet markiert', 'success');
            }
            loadTicket();
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Markieren als abgerechnet: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Markieren als abgerechnet:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Markieren als abgerechnet', 'error');
        }
    });
}

function updateGeplantDatum() {
    if (!canSetPlannedDate) return;
    const geplantDatum = document.getElementById('editGeplantDatum').value;
    const geplantDatumEndeEl = document.getElementById('editGeplantDatumEnde');
    const geplantDatumEnde = geplantDatumEndeEl ? geplantDatumEndeEl.value : '';
    
    const updateData = {
        ticket_id: ticketId
    };
    
    if (geplantDatum) {
        updateData.geplant_datum = geplantDatum;
    } else {
        updateData.geplant_datum = null;
    }
    updateData.geplant_datum_ende = (geplantDatumEnde && geplantDatumEnde.trim() !== '') ? geplantDatumEnde : null;
    
    fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updateData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Geplantes Datum erfolgreich aktualisiert', 'success');
            }
            loadTicket();
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Aktualisieren des geplanten Datums:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Aktualisieren des geplanten Datums', 'error');
        }
    });
}

function filterTable(tableBodyId, searchTerm, rowClass) {
    const tableBody = document.getElementById(tableBodyId);
    if (!tableBody) return;
    
    const rows = tableBody.querySelectorAll('.' + rowClass);
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function closeOverviewAccordionPanel(slug) {
    var panel = document.getElementById('overview-acc-panel-' + slug);
    var btn = document.getElementById('overview-acc-btn-' + slug);
    if (panel) panel.classList.add('hidden');
    if (btn) btn.setAttribute('aria-expanded', 'false');
}

function closeAllOverviewAccordionPanels() {
    ['firma', 'kunde', 'geraet', 'projekt'].forEach(closeOverviewAccordionPanel);
}

/** Kunden-Edit schließen ohne Ticket neu zu laden (z. B. wenn stattdessen die Übersicht geöffnet wird) */
function dismissCustomerEditUi() {
    setCustomerCompactEditUi(false);
    var customerContainer = document.getElementById('customerSelectContainer');
    if (customerContainer) {
        customerContainer.style.display = 'none';
        customerContainer.classList.remove('is-visible', 'is-closing');
    }
    returnEditPanelToPool('customerSelectContainer');
}

function dismissDeviceEditUi() {
    setDeviceCompactEditUi(false);
    var deviceContainer = document.getElementById('deviceSelectContainer');
    if (deviceContainer) {
        deviceContainer.style.display = 'none';
        deviceContainer.classList.remove('is-visible', 'is-closing');
    }
    returnEditPanelToPool('deviceSelectContainer');
}

function toggleOverviewAccordion(slug, ev) {
    if (ev) {
        ev.preventDefault();
        ev.stopPropagation();
    }
    var panel = document.getElementById('overview-acc-panel-' + slug);
    var btn = document.getElementById('overview-acc-btn-' + slug);
    if (!panel || !btn) return;
    var opening = panel.classList.contains('hidden');
    if (opening) {
        dismissCustomerEditUi();
        dismissDeviceEditUi();
        panel.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
    } else {
        panel.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
    }
}

/** Übersichts-Panel aufklappen (schließt offene Edits). */
function openOverviewAccordionPanel(slug) {
    var panel = document.getElementById('overview-acc-panel-' + slug);
    var btn = document.getElementById('overview-acc-btn-' + slug);
    if (!panel || !btn || !panel.classList.contains('hidden')) return;
    dismissCustomerEditUi();
    dismissDeviceEditUi();
    panel.classList.remove('hidden');
    btn.setAttribute('aria-expanded', 'true');
}

function removeCustomerFromTicket() {
    if (isObserverOnly || !isAdminOrTech) return;
    if (!selectedChatTicket || !selectedChatTicket.customer_id) return;
    if (!window.confirm('Kunde wirklich von diesem Auftrag entfernen? (Zugeordnetes Gerät wird ebenfalls entfernt.)')) return;
    updateTicketField('customer_id', null).then(function() {
        return updateTicketField('device_id', null);
    }).then(function() {
        loadTicket();
    }).catch(function() {});
}

function removeDeviceFromTicket() {
    if (isObserverOnly) return;
    if (!selectedChatTicket || !selectedChatTicket.device_id) return;
    if (!(isAdminOrTech || userRole === 'Firmen-Admin')) return;
    if (!window.confirm('Gerät wirklich von diesem Auftrag entfernen?')) return;
    updateTicketField('device_id', null).then(function() {
        loadTicket();
    }).catch(function() {});
}

var overviewCardCtxMenuDocCleanup = null;

/** SVG-Markup für Kontextmenü-Icons (Heroicons-Style, currentColor) */
var OVERVIEW_CTX_MENU_ICONS = {
    building: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21"/></svg>',
    external: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>',
    pencil: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
    users: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>',
    chip: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z"/></svg>',
    swap: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>',
    trash: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.222-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>',
    user: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>',
    device: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>',
    folder: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>',
    list: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 6.75h12M8.25 12h12m-12 4.5h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 17.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>',
    dot: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>'
};

function overviewCtxMenuIconWrap(iconKey, opts) {
    opts = opts || {};
    var wrap = document.createElement('span');
    wrap.className = 'overview-ctx-menu-icon inline-flex shrink-0';
    if (opts.danger) {
        wrap.className += ' text-red-500 dark:text-red-400';
    } else if (opts.link) {
        wrap.className += ' text-primary-600 dark:text-primary-300';
    } else {
        wrap.className += ' text-gray-500 dark:text-primary-210';
    }
    wrap.setAttribute('aria-hidden', 'true');
    wrap.innerHTML = OVERVIEW_CTX_MENU_ICONS[iconKey] || OVERVIEW_CTX_MENU_ICONS.dot;
    return wrap;
}

/** Kunden der Firma auswählbar (wie customers.php?company_id=); fehlender Zähler = ältere API, dann anzeigen. */
function ticketHasAssignableCustomers(t) {
    if (!t || !t.company_id) return false;
    var c = t.company_customers_count;
    if (c === undefined || c === null) return true;
    return parseInt(String(c), 10) > 0;
}
/** Geräte des Kunden auswählbar (wie devices.php?customer_id=); fehlender Zähler = ältere API, dann anzeigen. */
function ticketHasAssignableDevices(t) {
    if (!t || !t.customer_id) return false;
    var d = t.customer_devices_count;
    if (d === undefined || d === null) return true;
    return parseInt(String(d), 10) > 0;
}

function hideOverviewCardContextMenu() {
    var el = document.getElementById('overviewCardCtxMenu');
    if (!el) return;
    el.classList.add('hidden');
    el.innerHTML = '';
    el.setAttribute('aria-hidden', 'true');
    if (overviewCardCtxMenuDocCleanup) {
        overviewCardCtxMenuDocCleanup();
        overviewCardCtxMenuDocCleanup = null;
    }
}

function buildOverviewCardContextMenuItems(kind) {
    var items = [];
    var t = selectedChatTicket;
    if (!t) return items;

    var companyId = t.company_id ? parseInt(String(t.company_id), 10) : 0;
    var customerId = t.customer_id ? parseInt(String(t.customer_id), 10) : 0;
    var deviceId = t.device_id ? parseInt(String(t.device_id), 10) : 0;
    var hasCustomerName = !!(t.customer_name && String(t.customer_name).trim());
    var hasDeviceName = !!(t.device_name && String(t.device_name).trim());

    if (kind === 'firma') {
        if (companyId && (userRole === 'Admin' || userRole === 'Techniker')) {
            items.push({ type: 'link', label: 'Firmendetails', href: serviceBaseUrl + 'companies/detail.php?id=' + companyId, icon: 'building' });
        }
        if (companyId && userRole === 'Admin') {
            items.push({ type: 'link', label: 'Firma bearbeiten', href: serviceBaseUrl + 'companies/edit.php?id=' + companyId, icon: 'pencil' });
        }
        if (!isObserverOnly && isAdminOrTech && companyId && (hasCustomerName || ticketHasAssignableCustomers(t))) {
            items.push({ type: 'btn', label: 'Kunde zuordnen', action: 'editCompanyCustomer', icon: 'users' });
        }
        if (!isObserverOnly && companyId && customerId && !hasDeviceName && (isAdminOrTech || userRole === 'Firmen-Admin') && ticketHasAssignableDevices(t)) {
            items.push({ type: 'btn', label: 'Gerät zuordnen', action: 'editDeviceAssign', icon: 'chip' });
        }
    } else if (kind === 'kunde') {
        if (customerId && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin')) {
            items.push({ type: 'link', label: 'Kundendetails', href: serviceBaseUrl + 'customers/detail.php?id=' + customerId, icon: 'user' });
        }
        if (customerId && (userRole === 'Admin' || userRole === 'Firmen-Admin')) {
            items.push({ type: 'link', label: 'Kunde bearbeiten', href: serviceBaseUrl + 'customers/edit.php?id=' + customerId, icon: 'pencil' });
        }
        if (!isObserverOnly && isAdminOrTech) {
            items.push({ type: 'btn', label: 'Kunde zuordnen', action: 'editCompanyCustomer', icon: 'swap' });
        }
        if (!isObserverOnly && isAdminOrTech && customerId) {
            items.push({ type: 'btn', label: 'Kunde vom Auftrag entfernen', action: 'removeCustomer', danger: true, icon: 'trash' });
        }
        if (!isObserverOnly && customerId && !hasDeviceName && (isAdminOrTech || userRole === 'Firmen-Admin') && ticketHasAssignableDevices(t)) {
            items.push({ type: 'btn', label: 'Gerät zuordnen', action: 'editDeviceAssign', icon: 'chip' });
        }
    } else if (kind === 'geraet') {
        if (deviceId && (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin')) {
            items.push({ type: 'link', label: 'Gerätedetails', href: serviceBaseUrl + 'devices/detail.php?id=' + deviceId, icon: 'device' });
            items.push({ type: 'link', label: 'Gerät bearbeiten', href: serviceBaseUrl + 'devices/edit.php?id=' + deviceId, icon: 'pencil' });
        }
        if (!isObserverOnly && (isAdminOrTech || userRole === 'Firmen-Admin')) {
            items.push({ type: 'btn', label: 'Gerät zuordnen', action: 'editDeviceAssign', icon: 'swap' });
        }
        if (!isObserverOnly && deviceId && (isAdminOrTech || userRole === 'Firmen-Admin')) {
            items.push({ type: 'btn', label: 'Gerät vom Auftrag entfernen', action: 'removeDevice', danger: true, icon: 'trash' });
        }
    } else if (kind === 'projekt') {
        var proj = t.projects && t.projects[0];
        if (proj && proj.id) {
            var pid = parseInt(String(proj.id), 10);
            if (pid) {
                items.push({ type: 'link', label: 'Projekt bearbeiten', href: serviceBaseUrl + 'projects/view.php?id=' + pid, icon: 'folder' });
                items.push({ type: 'link', label: 'Projektliste', href: serviceBaseUrl + 'projects/', icon: 'list' });
            }
        }
    }
    return items;
}

function runOverviewCardCtxAction(action) {
    if (action === 'editCompanyCustomer') {
        editCompanyCustomerSelection();
    } else if (action === 'editDeviceAssign') {
        editCompanyCustomerSelection(false, true);
    } else if (action === 'removeCustomer') {
        removeCustomerFromTicket();
    } else if (action === 'removeDevice') {
        removeDeviceFromTicket();
    }
}

function showOverviewCardContextMenuFromItems(e, items) {
    hideOverviewCardContextMenu();
    var menu = document.getElementById('overviewCardCtxMenu');
    if (!menu || !items.length) return;

    items.forEach(function(item) {
        var iconKey = item.icon || 'dot';
        if (item.type === 'link') {
            var a = document.createElement('a');
            a.href = item.href;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.setAttribute('role', 'menuitem');
            a.className = 'overview-ctx-menu-item flex items-center gap-2.5 px-3 py-2 text-sm text-primary-600 dark:text-primary-220 hover:bg-gray-100 dark:hover:bg-primary-140';
            a.appendChild(overviewCtxMenuIconWrap(iconKey, { link: true }));
            var aLabel = document.createElement('span');
            aLabel.className = 'min-w-0 truncate';
            aLabel.textContent = item.label;
            a.appendChild(aLabel);
            a.addEventListener('click', hideOverviewCardContextMenu);
            menu.appendChild(a);
        } else if (item.type === 'btn') {
            var b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('role', 'menuitem');
            var baseCls = 'overview-ctx-menu-item flex items-center gap-2.5 w-full text-left px-3 py-2 text-sm ';
            b.className = baseCls + (item.danger
                ? 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/25'
                : 'text-gray-800 dark:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140');
            b.appendChild(overviewCtxMenuIconWrap(iconKey, { danger: !!item.danger }));
            var bLabel = document.createElement('span');
            bLabel.className = 'min-w-0 truncate';
            bLabel.textContent = item.label;
            b.appendChild(bLabel);
            (function(act) {
                b.addEventListener('click', function() {
                    hideOverviewCardContextMenu();
                    runOverviewCardCtxAction(act);
                });
            })(item.action);
            menu.appendChild(b);
        }
    });

    menu.classList.remove('hidden');
    menu.setAttribute('aria-hidden', 'false');

    var x = e.clientX;
    var y = e.clientY;
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';

    requestAnimationFrame(function() {
        var rect = menu.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var pad = 8;
        var nx = x;
        var ny = y;
        if (rect.right > vw - pad) nx = Math.max(pad, vw - rect.width - pad);
        if (rect.bottom > vh - pad) ny = Math.max(pad, vh - rect.height - pad);
        if (nx !== x || ny !== y) {
            menu.style.left = nx + 'px';
            menu.style.top = ny + 'px';
        }
    });

    function onDoc(ev) {
        if (!menu.contains(ev.target)) hideOverviewCardContextMenu();
    }
    function onKey(ev) {
        if (ev.key === 'Escape') hideOverviewCardContextMenu();
    }
    function onScroll() {
        hideOverviewCardContextMenu();
    }

    setTimeout(function() {
        document.addEventListener('mousedown', onDoc, true);
        document.addEventListener('keydown', onKey, true);
        window.addEventListener('scroll', onScroll, true);
    }, 0);

    overviewCardCtxMenuDocCleanup = function() {
        document.removeEventListener('mousedown', onDoc, true);
        document.removeEventListener('keydown', onKey, true);
        window.removeEventListener('scroll', onScroll, true);
    };
}

function initOverviewAccordionRowClick() {
    var host = document.getElementById('ticketInfoContent');
    if (!host || host.dataset.overviewAccRowClickBound === '1') return;
    host.dataset.overviewAccRowClickBound = '1';
    host.addEventListener('click', function(e) {
        var root = document.getElementById('overviewAccordionsRoot');
        if (!root || !root.contains(e.target)) return;
        var section = e.target.closest('.overview-accordion-section');
        if (!section || !root.contains(section)) return;
        if (e.target.closest('.overview-acc-panel')) return;
        if (e.target.closest('.edit-card')) return;
        if (e.target.closest('a[href]')) return;
        var btn = e.target.closest('button');
        if (btn && !btn.classList.contains('overview-acc-toggle')) return;
        var slug = section.getAttribute('data-overview-card');
        if (!slug) return;
        toggleOverviewAccordion(slug, e);
    });
}

function initOverviewCardContextMenu() {
    var host = document.getElementById('ticketInfoContent');
    if (!host || host.dataset.overviewCardCtxBound === '1') return;
    host.dataset.overviewCardCtxBound = '1';
    host.addEventListener('contextmenu', function(e) {
        var root = document.getElementById('overviewAccordionsRoot');
        if (!root || !root.contains(e.target)) return;
        var section = e.target.closest('.overview-accordion-section');
        if (!section || !root.contains(section)) return;
        var cardKind = section.getAttribute('data-overview-card');
        if (!cardKind) return;
        var ctxItems = buildOverviewCardContextMenuItems(cardKind);
        if (!ctxItems.length) return;
        e.preventDefault();
        e.stopPropagation();
        showOverviewCardContextMenuFromItems(e, ctxItems);
    });
}

function toggleAttachmentsCollapse() {
    const collapse = document.getElementById('attachmentsCollapse');
    if (!collapse) return;
    if (collapse.classList.contains('hidden')) {
        collapse.classList.remove('hidden');
    } else {
        collapse.classList.add('hidden');
    }
}

// Termine-Funktionen
function loadAppointments() {
    const appointmentsList = document.getElementById('appointmentsList');
    if (!appointmentsList) return;
    
    fetch(appointmentsApiUrl + '?ticket_id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.appointments) {
                displayAppointments(data.appointments);
            } else {
                appointmentsList.innerHTML = '';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Termine:', error);
            appointmentsList.innerHTML = '';
        });
}

function displayAppointments(appointments) {
    const appointmentsList = document.getElementById('appointmentsList');
    if (!appointmentsList) return;
    
    let html = '';
    
    if (appointments.length > 0) {
        appointments.forEach(appointment => {
            const iconColor = appointment.typ === 'geplant' ? 'text-blue-500 dark:text-blue-400' : 'text-orange-500 dark:text-orange-400';
            const typLabel = appointment.typ === 'geplant' ? 'Geplant' : 'Fällig';
            const title = appointment.titel ? escapeHtml(appointment.titel) : typLabel;
            const dateRange = formatDateTimeRange(appointment.start_datum, appointment.ende_datum, true);
            
            html += `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 ${iconColor} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">${title}</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">${dateRange}</span>
                    ${!isObserverOnly && selectedChatTicket && selectedChatTicket.status !== 'Geschlossen' && selectedChatTicket.status !== 'Archiv' ? `
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" onclick="openEditAppointmentModal(${appointment.id})" class="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" title="Termin bearbeiten">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m11.5 11.5 2.071 1.994M4 10h5m11 0h-1.5M12 7V4M7 7V4m10 3V4m-7 13H8v-2l5.227-5.292a1.46 1.46 0 0 1 2.065 2.065L10 17Zm-5 3h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/>
</svg>

                        </button>
                        <button type="button" onclick="deleteAppointment(${appointment.id})" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" title="Termin löschen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
        });
    }
    
    appointmentsList.innerHTML = html;
}

/** Deep-Link von Ticketliste (?…#open-appointment): nach Laden Infobereich öffnen + Terminfelder */
function tryOpenAppointmentFromHash() {
    if (location.hash !== '#open-appointment') return;
    history.replaceState(null, '', location.pathname + location.search);
    openAppointmentAddPanelFromMenu(true);
}

function isMobileAppointmentSheetMode() {
    return typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 1023px)').matches;
}

function openAddAppointmentMobileModal() {
    var modal = document.getElementById('addAppointmentMobileModal');
    var sheet = document.getElementById('addAppointmentMobileSheet');
    if (!modal || !sheet) return;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    requestAnimationFrame(function() {
        sheet.classList.remove('translate-y-full');
    });
    window.setTimeout(function() {
        var first = document.getElementById('newAppointmentMobileTitle');
        if (first) first.focus();
    }, 180);
}

function closeAddAppointmentMobileModal() {
    var modal = document.getElementById('addAppointmentMobileModal');
    var sheet = document.getElementById('addAppointmentMobileSheet');
    if (!modal || !sheet) return;
    sheet.classList.add('translate-y-full');
    window.setTimeout(function() {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        var t = document.getElementById('newAppointmentMobileTitle');
        var s = document.getElementById('newAppointmentMobileStart');
        var e = document.getElementById('newAppointmentMobileEnd');
        if (t) t.value = '';
        if (s) s.value = '';
        if (e) e.value = '';
    }, 220);
}

/** Mehr Optionen / Deep-Link → Termin hinzufügen: mobil als Bottom-Sheet, desktop als Inline-Expand */
function openAppointmentAddPanelFromMenu(fromDeepLink) {
    if (isMobileAppointmentSheetMode()) {
        openAddAppointmentMobileModal();
        return;
    }
    const panel = document.getElementById('appointmentAddExpandPanel');
    if (!panel) {
        if (typeof showToast === 'function') showToast('Terminbereich ist noch nicht geladen', 'error');
        return;
    }
    if (!panel.classList.contains('is-expanded')) {
        panel.classList.add('is-expanded');
    }
    var delay = fromDeepLink ? 180 : 100;
    setTimeout(function() {
        const btn = document.getElementById('appointmentAddBtn');
        if (btn) btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        const first = document.getElementById('newAppointmentTitle');
        if (first) first.focus();
    }, delay);
}

function toggleAppointmentAddPanel() {
    if (isMobileAppointmentSheetMode()) {
        openAddAppointmentMobileModal();
        return;
    }
    const panel = document.getElementById('appointmentAddExpandPanel');
    if (!panel) return;
    if (panel.classList.contains('is-expanded')) {
        panel.classList.remove('is-expanded');
        const t = document.getElementById('newAppointmentTitle');
        const s = document.getElementById('newAppointmentStart');
        const e = document.getElementById('newAppointmentEnd');
        if (t) t.value = '';
        if (s) s.value = '';
        if (e) e.value = '';
    } else {
        panel.classList.add('is-expanded');
        setTimeout(function() {
            const first = document.getElementById('newAppointmentTitle');
            if (first) first.focus();
        }, 100);
    }
}

function saveNewAppointmentData(titleVal, startVal, endVal, afterSuccess) {
    if (isObserverOnly) return;
    if (selectedChatTicket && (selectedChatTicket.status === 'Geschlossen' || selectedChatTicket.status === 'Archiv' || selectedChatTicket.abgerechnet === 1 || selectedChatTicket.abgerechnet === '1')) {
        if (typeof showToast === 'function') showToast('Zu diesem Ticket kann kein Termin mehr hinzugefügt werden.', 'error');
        return;
    }
    if (!startVal) {
        if (typeof showToast === 'function') showToast('Bitte Startdatum auswählen', 'error');
        return;
    }
    fetch(appointmentsApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ticket_id: ticketId,
            titel: titleVal,
            typ: 'geplant',
            start_datum: startVal,
            ende_datum: endVal || null
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data && data.success) {
            if (typeof showToast === 'function') showToast('Termin erfolgreich hinzugefügt', 'success');
            if (typeof afterSuccess === 'function') afterSuccess();
            loadAppointments();
            // Status sofort in der Anzeige auf "Geplant" setzen (Backend hat Ticket bereits aktualisiert)
            if (selectedChatTicket) {
                selectedChatTicket.status = 'Geplant';
                const chatHeader = document.getElementById('chatTicketHeader');
                if (chatHeader) {
                    const statusBadge = chatHeader.querySelector('span[class*="rounded-full"]');
                    if (statusBadge) {
                        statusBadge.className = 'flex-shrink-0 px-3 py-1 text-sm font-semibold rounded-full whitespace-nowrap ' + getStatusBadgeClass('Geplant');
                        statusBadge.textContent = getStatusText('Geplant');
                    }
                }
            }
            loadTicket(); // Ticket komplett neu laden (frischer Status, keine gecachte Antwort)
        } else {
            if (typeof showToast === 'function') showToast((data && data.error) ? data.error : 'Fehler beim Speichern', 'error');
        }
    })
    .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Speichern des Termins', 'error');
    });
}

function saveNewAppointmentFromPanel(event) {
    event.preventDefault();
    const title = document.getElementById('newAppointmentTitle');
    const start = document.getElementById('newAppointmentStart');
    const end = document.getElementById('newAppointmentEnd');
    const titleVal = title && title.value ? title.value.trim() : null;
    const startVal = start && start.value ? start.value : '';
    const endVal = end && end.value ? end.value.trim() : null;
    saveNewAppointmentData(titleVal, startVal, endVal, function() {
        toggleAppointmentAddPanel();
    });
}

function saveNewAppointmentFromMobileModal(event) {
    event.preventDefault();
    const title = document.getElementById('newAppointmentMobileTitle');
    const start = document.getElementById('newAppointmentMobileStart');
    const end = document.getElementById('newAppointmentMobileEnd');
    const titleVal = title && title.value ? title.value.trim() : null;
    const startVal = start && start.value ? start.value : '';
    const endVal = end && end.value ? end.value.trim() : null;
    saveNewAppointmentData(titleVal, startVal, endVal, function() {
        closeAddAppointmentMobileModal();
    });
}

function openEditAppointmentModal(appointmentId) {
    if (isObserverOnly) return;
    if (selectedChatTicket && (selectedChatTicket.status === 'Geschlossen' || selectedChatTicket.status === 'Archiv')) {
        if (typeof showToast === 'function') {
            showToast('Termine von geschlossenen Tickets können nicht mehr bearbeitet werden.', 'error');
        }
        return;
    }
    const modal = document.getElementById('editAppointmentModal');
    if (!modal) return;
    fetch(appointmentsApiUrl + '?ticket_id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.appointments) {
                const appointment = data.appointments.find(a => a.id == appointmentId);
                if (appointment) {
                    document.getElementById('appointmentId').value = appointment.id;
                    document.getElementById('appointmentTitle').value = appointment.titel || '';
                    document.getElementById('appointmentStart').value = formatDateTimeLocal(appointment.start_datum);
                    document.getElementById('appointmentEnd').value = appointment.ende_datum ? formatDateTimeLocal(appointment.ende_datum) : '';
                    modal.classList.remove('hidden');
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden des Termins:', error);
            if (typeof showToast === 'function') showToast('Fehler beim Laden des Termins', 'error');
        });
}

function closeAddAppointmentModal() {
    const modal = document.getElementById('editAppointmentModal');
    if (modal) {
        modal.classList.add('hidden');
        document.getElementById('appointmentId').value = '';
        document.getElementById('appointmentTitle').value = '';
        document.getElementById('appointmentStart').value = '';
        document.getElementById('appointmentEnd').value = '';
    }
}

function saveAppointment(event) {
    event.preventDefault();
    if (isObserverOnly) return;
    if (selectedChatTicket && (selectedChatTicket.status === 'Geschlossen' || selectedChatTicket.status === 'Archiv')) {
        if (typeof showToast === 'function') showToast('Zu geschlossenen Tickets können keine Termine geändert werden.', 'error');
        return;
    }
    const appointmentId = document.getElementById('appointmentId').value;
    const title = document.getElementById('appointmentTitle').value.trim();
    const start = document.getElementById('appointmentStart').value;
    const end = document.getElementById('appointmentEnd').value.trim();
    if (!start) {
        if (typeof showToast === 'function') showToast('Bitte Startdatum auswählen', 'error');
        return;
    }
    const data = {
        id: parseInt(appointmentId),
        titel: title || null,
        typ: 'geplant',
        start_datum: start,
        ende_datum: end || null
    };
    fetch(appointmentsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data && data.success) {
            if (typeof showToast === 'function') showToast('Termin erfolgreich aktualisiert', 'success');
            closeAddAppointmentModal();
            loadAppointments();
            loadTicket(); // Status ggf. auf "Neu" wenn letzter Termin in der Vergangenheit
        } else {
            if (typeof showToast === 'function') showToast((data && data.error) ? data.error : 'Fehler beim Speichern', 'error');
        }
    })
    .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Speichern des Termins', 'error');
    });
}

function deleteAppointment(appointmentId) {
    if (isObserverOnly) return;
    if (!confirm('Möchten Sie diesen Termin wirklich löschen?')) return;
    
    fetch(appointmentsApiUrl + '?id=' + appointmentId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Termin erfolgreich gelöscht', 'success');
            }
            loadAppointments();
            loadTicket(); // Ticket neu laden (Status ggf. auf "Neu" gesetzt, wenn kein Termin mehr da ist)
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Löschen des Termins:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Löschen des Termins', 'error');
        }
    });
}

function loadTicketAttachments(ticketId) {
    let allAttachments = [];
    
    // Promise für Ticket-Anhänge
    const ticketAttachmentsPromise = fetch(ticketAttachmentsApiUrl + '?ticket_id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.attachments) {
                // Ticket-Anhänge hinzufügen
                data.attachments.forEach(attachment => {
                    allAttachments.push({
                        ...attachment,
                        comment_date: attachment.erstellt_datum,
                        comment_user: 'Ticket-Erstellung',
                        is_ticket_attachment: true
                    });
                });
            }
            return data;
        })
        .catch(error => {
            console.error('Fehler beim Laden der Ticket-Anhänge:', error);
            return { success: false };
        });
    
    // Promise für Kommentar-Anhänge
    const commentAttachmentsPromise = fetch(commentsApiUrl + '?ticket_id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.comments) {
                // Alle Anhänge aus allen Kommentaren sammeln
                data.comments.forEach(comment => {
                    if (comment.attachments && comment.attachments.length > 0) {
                        comment.attachments.forEach(attachment => {
                            allAttachments.push({
                                ...attachment,
                                comment_date: comment.erstellt_datum,
                                comment_user: `${comment.vorname || ''} ${comment.nachname || ''}`.trim() || 'Unbekannt',
                                is_ticket_attachment: false
                            });
                        });
                    }
                });
            }
            return data;
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kommentar-Anhänge:', error);
            return { success: false };
        });
    
    // Beide Promises parallel ausführen
    Promise.all([ticketAttachmentsPromise, commentAttachmentsPromise])
        .then(() => {
            // Anhänge nach Datum sortieren (neueste zuerst)
            allAttachments.sort((a, b) => {
                const dateA = new Date(a.comment_date || a.erstellt_datum);
                const dateB = new Date(b.comment_date || b.erstellt_datum);
                return dateB - dateA;
            });
            
            // Anhänge Card anzeigen/verstecken
            const attachmentsCard = document.getElementById('attachmentsCard');
            if (attachmentsCard) {
                if (allAttachments.length > 0) {
                    attachmentsCard.style.display = 'block';
                } else {
                    attachmentsCard.style.display = 'none';
                    return; // Keine Anhänge, Card verstecken und Funktion beenden
                }
            }
            
            // Anhänge Count aktualisieren
            const countText = document.getElementById('attachmentsCountText');
            if (countText) {
                countText.textContent = `${allAttachments.length} Datei(en)`;
            }
            
            // Anhänge Liste rendern
            const attachmentsList = document.getElementById('attachmentsList');
            if (attachmentsList) {
                attachmentsList.innerHTML = allAttachments.map((attachment, index) => {
                        const fileUrl = '<?php echo BASE_URL; ?>' + (attachment.dateipfad || '').replace(/^\//, '');
                        const fileName = attachment.dateiname || 'Unbekannte Datei';
                        const fileSize = formatFileSize(attachment.dateigroesse || 0);
                        const fileExtension = fileName.split('.').pop().toUpperCase();
                        const commentDate = new Date(attachment.comment_date || attachment.erstellt_datum);
                        const formattedDate = commentDate.toLocaleDateString('de-DE', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        return `
                            <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate" title="${escapeHtml(fileName)}">${escapeHtml(fileName)}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">${fileSize} • ${escapeHtml(fileExtension)} • ${escapeHtml(attachment.comment_user)} • ${formattedDate}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2 ml-2">
                                    <a href="${escapeHtml(fileUrl)}" target="_blank" class="p-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white" title="Öffnen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                    <a href="${escapeHtml(fileUrl)}" download class="p-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white" title="Herunterladen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        `;
                    }).join('');
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Anhänge:', error);
            const attachmentsCard = document.getElementById('attachmentsCard');
            if (attachmentsCard) {
                attachmentsCard.style.display = 'none';
            }
        });
}

function returnEditPanelToPool(panelId) {
    var el = typeof panelId === 'string' ? document.getElementById(panelId) : panelId;
    var pool = document.getElementById('editPanelsPool');
    if (el && pool && el.parentNode !== pool) {
        pool.appendChild(el);
    }
}

function setCustomerCompactEditUi(editing) {
    var pencil = document.getElementById('customerCompactEditPencilBtn');
    var cancel = document.getElementById('customerCompactCancelEditBtn');
    if (pencil && cancel) {
        pencil.style.display = editing ? 'none' : '';
        cancel.style.display = editing ? '' : 'none';
    }
}

function setDeviceCompactEditUi(editing) {
    var pencil = document.getElementById('deviceCompactEditPencilBtn');
    var cancel = document.getElementById('deviceCompactCancelEditBtn');
    if (pencil && cancel) {
        pencil.style.display = editing ? 'none' : '';
        cancel.style.display = editing ? '' : 'none';
    }
}

function mountCustomerEditUnderKundeRow() {
    var c = document.getElementById('customerSelectContainer');
    if (!c) return;
    var row = document.getElementById('compactCustomerRow');
    var panel = document.getElementById('overview-acc-panel-kunde');
    var root = document.getElementById('overviewAccordionsRoot');
    if (row && panel && row.contains(panel)) {
        row.insertBefore(c, panel);
        return;
    }
    var firma = document.getElementById('overviewSectionFirma');
    if (firma && root && firma.parentNode === root && firma.nextSibling) {
        root.insertBefore(c, firma.nextSibling);
        return;
    }
    if (root) {
        if (root.firstChild) {
            root.insertBefore(c, root.firstChild);
        } else {
            root.appendChild(c);
        }
    }
}

function mountDeviceEditUnderGeraetRow() {
    var c = document.getElementById('deviceSelectContainer');
    if (!c) return;
    var row = document.getElementById('compactDeviceRow');
    var panel = document.getElementById('overview-acc-panel-geraet');
    var root = document.getElementById('overviewAccordionsRoot');
    if (row && panel && row.contains(panel)) {
        row.insertBefore(c, panel);
        return;
    }
    var customerRow = document.getElementById('compactCustomerRow');
    if (customerRow && root && customerRow.parentNode === root) {
        if (customerRow.nextSibling) {
            root.insertBefore(c, customerRow.nextSibling);
        } else {
            root.appendChild(c);
        }
        return;
    }
    var firma = document.getElementById('overviewSectionFirma');
    if (firma && root && firma.parentNode === root && firma.nextSibling) {
        root.insertBefore(c, firma.nextSibling);
        return;
    }
    if (root) {
        root.appendChild(c);
    }
}

/** Geräteliste laden und Gerät-Edit einblenden (Firma muss gesetzt sein). */
function openDeviceEditForTicket() {
    var deviceContainer = document.getElementById('deviceSelectContainer');
    var compactContainer = document.getElementById('companyCustomerCompactContainer');
    if (!deviceContainer || !selectedChatTicket || !selectedChatTicket.company_id) return;
    closeAllOverviewAccordionPanels();
    mountDeviceEditUnderGeraetRow();
    var devPromise = loadDevicesForEdit(selectedChatTicket.company_id, selectedChatTicket.customer_id);
    if (devPromise && typeof devPromise.then === 'function') {
        devPromise.then(function(deviceCount) {
            if (deviceCount > 0) {
                closeAllOverviewAccordionPanels();
                deviceContainer.style.display = 'block';
                requestAnimationFrame(function() {
                    deviceContainer.classList.add('is-visible');
                    deviceContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    setDeviceCompactEditUi(true);
                });
            } else {
                setDeviceCompactEditUi(false);
                deviceContainer.style.display = 'none';
                returnEditPanelToPool('deviceSelectContainer');
                if (compactContainer && selectedChatTicket) compactContainer.style.display = 'block';
            }
        });
    } else {
        deviceContainer.style.display = 'block';
        requestAnimationFrame(function() {
            deviceContainer.classList.add('is-visible');
            deviceContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setDeviceCompactEditUi(true);
        });
    }
}

function editCompanyCustomerSelection(skipCompanyCard, openDeviceEditOnly) {
    if (isObserverOnly) return;
    const compactContainer = document.getElementById('companyCustomerCompactContainer');
    const companyContainer = document.getElementById('companySelectContainer');
    const customerContainer = document.getElementById('customerSelectContainer');
    const deviceContainer = document.getElementById('deviceSelectContainer');
    
    if (compactContainer) {
        compactContainer.classList.remove('compact-enter');
    }
    
    if (compactContainer && selectedChatTicket?.company_id) {
        compactContainer.style.display = 'block';
    }

    [companyContainer, customerContainer, deviceContainer].forEach(function(el) {
        if (el) {
            el.classList.remove('is-visible', 'is-closing');
        }
    });

    var hasCompany = !!(selectedChatTicket && selectedChatTicket.company_id);
    if (companyContainer) {
        companyContainer.style.display = 'none';
    }
    if (!isAdminOrTech) {
        if (customerContainer) customerContainer.style.display = 'none';
        if (!hasCompany) {
            returnEditPanelToPool('deviceSelectContainer');
            returnEditPanelToPool('customerSelectContainer');
            return;
        }
        openDeviceEditForTicket();
        return;
    }
    if (!hasCompany) {
        setCustomerCompactEditUi(false);
        returnEditPanelToPool('customerSelectContainer');
        returnEditPanelToPool('deviceSelectContainer');
        return;
    }
    if (openDeviceEditOnly) {
        setCustomerCompactEditUi(false);
        if (customerContainer) customerContainer.style.display = 'none';
        returnEditPanelToPool('customerSelectContainer');
        if (deviceContainer) deviceContainer.style.display = 'none';
        openDeviceEditForTicket();
        return;
    }
    if (customerContainer) {
        setDeviceCompactEditUi(false);
        closeAllOverviewAccordionPanels();
        mountCustomerEditUnderKundeRow();
        customerContainer.style.display = 'block';
        loadCustomersForEdit(selectedChatTicket.company_id);
        requestAnimationFrame(function() {
            customerContainer.classList.add('is-visible');
            customerContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setCustomerCompactEditUi(true);
        });
    }
    if (deviceContainer) {
        deviceContainer.style.display = 'none';
    }
}

function cancelCompanyEdit() {
    const companyContainer = document.getElementById('companySelectContainer');
    const compactContainer = document.getElementById('companyCustomerCompactContainer');
    if (companyContainer) {
        companyContainer.style.display = 'none';
        companyContainer.classList.remove('is-visible', 'is-closing');
    }
    returnEditPanelToPool('companySelectContainer');
    if (compactContainer && selectedChatTicket) {
        compactContainer.style.display = 'block';
    }
}

function loadCompaniesForEdit() {
    const companyTableBodyEdit = document.getElementById('companyTableBodyEdit');
    const companySearchEdit = document.getElementById('companySearchEdit');
    if (!companyTableBodyEdit) return;

    companyTableBodyEdit.innerHTML = '<tr><td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Lade Firmen...</td></tr>';
    fetch(companiesApiUrl)
        .then(r => r.json())
        .then(data => {
            if (data.success && Array.isArray(data.companies)) {
                companies = data.companies;
                renderCompaniesForEdit(companies);
                if (companySearchEdit) {
                    companySearchEdit.oninput = () => {
                        const term = companySearchEdit.value.toLowerCase();
                        const filtered = companies.filter(c => (c.name || '').toLowerCase().includes(term));
                        renderCompaniesForEdit(filtered);
                    };
                }
            } else {
                companyTableBodyEdit.innerHTML = '<tr><td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Keine Firmen verfügbar</td></tr>';
            }
        })
        .catch(err => {
            console.error('Fehler beim Laden der Firmen:', err);
            companyTableBodyEdit.innerHTML = '<tr><td colspan="1" class="px-3 py-2 text-center text-red-500">Fehler beim Laden</td></tr>';
        });
}

function renderCompaniesForEdit(list) {
    const companyTableBodyEdit = document.getElementById('companyTableBodyEdit');
    if (!companyTableBodyEdit) return;
    if (!list || list.length === 0) {
        companyTableBodyEdit.innerHTML = '<tr><td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Keine Firmen gefunden</td></tr>';
        return;
    }
    companyTableBodyEdit.innerHTML = list.map(company => `
        <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
            onclick="selectCompanyEdit(${company.id}, '${escapeHtml(company.name || '')}')">
            <td class="px-3 py-2 text-gray-900 dark:text-white">${escapeHtml(company.name || '')}</td>
        </tr>
    `).join('');
}

function animateEditCardClose(editCardEl, onDone) {
    if (!editCardEl || typeof onDone !== 'function') {
        if (typeof onDone === 'function') onDone();
        return;
    }
    editCardEl.classList.remove('is-visible');
    editCardEl.classList.add('is-closing');
    setTimeout(function() {
        editCardEl.classList.remove('is-closing');
        onDone();
    }, 210);
}

function selectCompanyEdit(companyId, companyName) {
    const companySelectedTextEdit = document.getElementById('companySelectedTextEdit');
    if (companySelectedTextEdit) {
        companySelectedTextEdit.textContent = companyName || '--';
    }
    updateTicketField('company_id', companyId).then(() => {
        const companyContainer = document.getElementById('companySelectContainer');
        const compactContainer = document.getElementById('companyCustomerCompactContainer');
        animateEditCardClose(companyContainer, function() {
            returnEditPanelToPool('companySelectContainer');
            if (compactContainer) {
                compactContainer.style.display = 'block';
                compactContainer.classList.add('compact-enter');
            }
            loadTicket(true);
        });
    });
}

function setMyCompanyOnTicket() {
    if (isObserverOnly) return;
    if (!userCompanyId) return;
    updateTicketField('company_id', userCompanyId).then(() => {
        loadTicket();
    });
}

function bindAssigneeObserverButtons() {
    const container = document.getElementById('assigneeCompactContainer');
    if (!container) return;
    var buttons = container.querySelectorAll('[data-action]');
    for (var i = 0; i < buttons.length; i++) {
        var el = buttons[i];
        var action = el.getAttribute('data-action');
        if (!action) continue;
        /* edit-assignee und edit-observers werden per Event-Delegation auf ticketInfoContent behandelt */
        if (action === 'edit-assignee' || action === 'edit-observers') continue;
        var fn = null;
        switch (action) {
            case 'clear-assignee': fn = clearAssigneeEdit; break;
            case 'clear-observers': fn = clearObserversFromCompact; break;
            case 'clear-observers-edit': fn = clearObserversEdit; break;
            case 'save-observers': fn = saveObserversEdit; break;
        }
        if (typeof fn === 'function') {
            var handler = function(f) { return function(e) { e.preventDefault(); e.stopPropagation(); f(); }; }(fn);
            el._assigneeObserverClick = handler;
            el.addEventListener('click', handler);
        }
    }
}

function updateAssigneeEditButtonIcon(expanded) {
    const btn = document.querySelector('[data-action="edit-assignee"]');
    if (!btn) return;
    const pencil = btn.querySelector('.assignee-edit-pencil');
    const closeIcon = btn.querySelector('.assignee-edit-close');
    if (pencil && closeIcon) {
        if (expanded) {
            pencil.classList.add('hidden');
            closeIcon.classList.remove('hidden');
        } else {
            pencil.classList.remove('hidden');
            closeIcon.classList.add('hidden');
        }
    }
}

function editAssigneeSelection() {
    if (isObserverOnly) return;
    if (!canSetAssignee) return;
    const assigneePanel = document.getElementById('assigneeExpandPanel');
    const observerPanel = document.getElementById('observerExpandPanel');
    if (observerPanel) {
        observerPanel.classList.remove('is-expanded');
        updateObserverEditButtonIcon(false);
    }
    if (assigneePanel) {
        if (assigneePanel.classList.contains('is-expanded')) {
            assigneePanel.classList.remove('is-expanded');
            updateAssigneeEditButtonIcon(false);
            return;
        }
        assigneePanel.classList.add('is-expanded');
        updateAssigneeEditButtonIcon(true);
        assigneePanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        loadAssignableUsers();
    }
}

function updateObserverEditButtonIcon(expanded) {
    const btn = document.querySelector('[data-action="edit-observers"]');
    if (!btn) return;
    const pencil = btn.querySelector('.observer-edit-pencil');
    const closeIcon = btn.querySelector('.observer-edit-close');
    if (pencil && closeIcon) {
        if (expanded) {
            pencil.classList.add('hidden');
            closeIcon.classList.remove('hidden');
        } else {
            pencil.classList.remove('hidden');
            closeIcon.classList.add('hidden');
        }
    }
}

function editObserverSelection() {
    if (isObserverOnly) return;
    if (!canEditObservers) return;
    const assigneePanel = document.getElementById('assigneeExpandPanel');
    const observerPanel = document.getElementById('observerExpandPanel');
    if (assigneePanel) {
        assigneePanel.classList.remove('is-expanded');
        updateAssigneeEditButtonIcon(false);
    }
    if (observerPanel) {
        if (observerPanel.classList.contains('is-expanded')) {
            observerPanel.classList.remove('is-expanded');
            updateObserverEditButtonIcon(false);
            return;
        }
        observerPanel.classList.add('is-expanded');
        observerPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        updateObserverEditButtonIcon(true);
        loadObserversForEdit();
    }
}

function cancelCustomerEdit() {
    dismissCustomerEditUi();
    returnEditPanelToPool('deviceSelectContainer');
    const compactContainer = document.getElementById('companyCustomerCompactContainer');
    if (compactContainer && selectedChatTicket) {
        compactContainer.style.display = 'block';
    }
    loadTicket();
}

function cancelDeviceEdit() {
    dismissDeviceEditUi();
    const compactContainer = document.getElementById('companyCustomerCompactContainer');
    if (compactContainer && selectedChatTicket) {
        compactContainer.style.display = 'block';
    }
    loadTicket();
}

function cancelAssigneeEdit() {
    const assigneePanel = document.getElementById('assigneeExpandPanel');
    const observerPanel = document.getElementById('observerExpandPanel');
    if (assigneePanel) assigneePanel.classList.remove('is-expanded');
    if (observerPanel) observerPanel.classList.remove('is-expanded');
    if (selectedChatTicket) loadTicket();
}

function loadObserversForEdit() {
    const observerTableBodyEdit = document.getElementById('observerTableBodyEdit');
    if (!observerTableBodyEdit) return;
    if (!canEditObservers) return;
    
    // Aktuelle Beobachter-IDs laden
    if (selectedChatTicket && selectedChatTicket.observer_ids) {
        selectedObserversEdit = selectedChatTicket.observer_ids.split(',').filter(id => id.trim() !== '');
    } else {
        selectedObserversEdit = [];
    }
    
    observerTableBodyEdit.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Lade Beobachter…</td></tr>';

    const companyId = (selectedChatTicket && selectedChatTicket.company_id) ? parseInt(selectedChatTicket.company_id) : (userCompanyId ? parseInt(userCompanyId) : null);
    if (!companyId) {
        observerTableBodyEdit.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Keine Firma gesetzt</td></tr>';
        return;
    }
    
    // Nur User aus der selben Firma wie der aktuelle User / Ticket-Firma
    fetch(ticketsApiUrl + '?action=company_users&company_id=' + companyId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users && data.users.length > 0) {
                // Eigener Benutzer aus der Liste filtern
                const filteredUsers = data.users.filter(user => parseInt(user.id) !== currentUserId);
                observerTableBodyEdit.innerHTML = filteredUsers.map(user => {
                    const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim() || user.email;
                    const isSelected = selectedObserversEdit.includes(user.id.toString());
                    const nameAttr = escapeHtml(fullName).replace(/"/g, '&quot;');
                    return `
                        <tr class="observer-row-edit border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" 
                            data-id="${user.id}" 
                            data-name="${nameAttr}"
                            onclick="toggleObserverEdit(${user.id}, this.getAttribute('data-name'))">
                            <td class="px-3 py-2 text-center w-8">
                                <input type="checkbox" class="observer-checkbox-edit rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" 
                                       data-user-id="${user.id}" ${isSelected ? 'checked' : ''} 
                                       onclick="event.stopPropagation(); toggleObserverEdit(${user.id}, this.closest('tr').getAttribute('data-name'))">
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(fullName)}</div>
                            </td>
                        </tr>
                    `;
                }).join('');
                updateObserversTextEdit();
            } else {
                observerTableBodyEdit.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Keine Beobachter verfügbar</td></tr>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Beobachter:', error);
            observerTableBodyEdit.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-red-500">Fehler beim Laden</td></tr>';
        });
}

function toggleObserverEdit(userId, userName) {
    if (!canEditObservers) return;
    const userIdStr = userId.toString();
    const index = selectedObserversEdit.indexOf(userIdStr);
    
    if (index > -1) {
        selectedObserversEdit.splice(index, 1);
    } else {
        selectedObserversEdit.push(userIdStr);
    }
    
    // Checkbox aktualisieren
    const checkbox = document.querySelector(`.observer-checkbox-edit[data-user-id="${userId}"]`);
    if (checkbox) {
        checkbox.checked = selectedObserversEdit.includes(userIdStr);
    }
    
    updateObserversTextEdit();
}

function toggleAllObserversEdit(checkbox) {
    if (!canEditObservers) return;
    const checkboxes = document.querySelectorAll('.observer-checkbox-edit');
    selectedObserversEdit = [];
    
    if (checkbox.checked) {
        checkboxes.forEach(cb => {
            const userId = cb.getAttribute('data-user-id');
            selectedObserversEdit.push(userId);
            cb.checked = true;
        });
    } else {
        checkboxes.forEach(cb => {
            cb.checked = false;
        });
    }
    
    updateObserversTextEdit();
}

function updateObserversTextEdit() {
    const textElement = document.getElementById('observerSelectedTextEdit');
    if (!textElement) return;
    
    if (selectedObserversEdit.length === 0) {
        textElement.textContent = 'Keine Beobachter';
        return;
    }
    
    const selectedNames = [];
    document.querySelectorAll('.observer-checkbox-edit:checked').forEach(checkbox => {
        const row = checkbox.closest('.observer-row-edit');
        if (row) {
            const name = row.getAttribute('data-name');
            if (name) selectedNames.push(name);
        }
    });
    
    if (selectedNames.length === 0) {
        textElement.textContent = `${selectedObserversEdit.length} Beobachter ausgewählt`;
    } else if (selectedNames.length <= 2) {
        textElement.textContent = selectedNames.join(', ');
    } else {
        textElement.textContent = `${selectedNames.slice(0, 2).join(', ')} und ${selectedNames.length - 2} weitere`;
    }
}

function clearObserversEdit() {
    if (!canEditObservers) return;
    selectedObserversEdit = [];
    document.querySelectorAll('.observer-checkbox-edit').forEach(cb => {
        cb.checked = false;
    });
    const selectAllCheckbox = document.getElementById('selectAllObserversEdit');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
    updateObserversTextEdit();
}

function clearObserversFromCompact() {
    if (!canEditObservers) return;
    selectedObserversEdit = [];
    saveObserversEdit();
}

function saveObserversEdit() {
    if (!canEditObservers) return;
    const observerIds = selectedObserversEdit.map(id => parseInt(id));
    
    fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ticket_id: ticketId,
            observer_ids: observerIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Beobachter erfolgreich aktualisiert', 'success');
            }
            // UI ohne Reload aktualisieren
            if (selectedChatTicket) {
                selectedChatTicket.observer_ids = observerIds.filter(v => Number.isFinite(v) && v > 0).join(',');
            }
            const selectedNames = [];
            document.querySelectorAll('.observer-checkbox-edit:checked').forEach(cb => {
                const row = cb.closest('.observer-row-edit');
                const name = row ? row.getAttribute('data-name') : '';
                if (name) selectedNames.push(name);
            });
            const compactTextEl = document.getElementById('observerCompactText');
            const addBtn = document.getElementById('addObserverBtn');
            const removeBtn = document.getElementById('removeObserverBtn');
            if (compactTextEl) {
                compactTextEl.textContent = selectedNames.length ? selectedNames.join(', ') : '';
            }
            // Buttons anpassen
            if (addBtn) {
                addBtn.style.display = selectedNames.length ? 'none' : '';
            }
            if (removeBtn) {
                removeBtn.style.display = selectedNames.length ? '' : 'none';
            }
            // Edit-Card schließen
            cancelObserverEdit();
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Aktualisieren der Beobachter:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Aktualisieren', 'error');
        }
    });
}

function cancelObserverEdit() {
    const assigneePanel = document.getElementById('assigneeExpandPanel');
    const observerPanel = document.getElementById('observerExpandPanel');
    if (assigneePanel) assigneePanel.classList.remove('is-expanded');
    if (observerPanel) observerPanel.classList.remove('is-expanded');
    if (selectedChatTicket) loadTicket();
}

(function assigneeObserverGlobals() {
    window.editAssigneeSelection = editAssigneeSelection;
    window.clearAssigneeEdit = clearAssigneeEdit;
    window.editObserverSelection = editObserverSelection;
    window.clearObserversFromCompact = clearObserversFromCompact;
    window.clearObserversEdit = clearObserversEdit;
    window.saveObserversEdit = saveObserversEdit;
})();

/* Event-Delegation: Edit-Stift-Buttons funktionieren auch nach dynamischem innerHTML-Ersatz */
(function initAssigneeObserverDelegation() {
    const container = document.getElementById('ticketInfoContent');
    if (!container) return;
    container.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="edit-assignee"], [data-action="edit-observers"], [data-action="toggle-anforderer"]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const action = btn.getAttribute('data-action');
        if (action === 'edit-assignee') editAssigneeSelection();
        else if (action === 'edit-observers') editObserverSelection();
        else if (action === 'toggle-anforderer') toggleAnfordererSelection();
    });
})();

function checkAndShowCompactCards(ticket, skipCompanyCustomerDevice) {
    const compactCompanyCustomer = document.getElementById('companyCustomerCompactContainer');
    const customerContainer = document.getElementById('customerSelectContainer');
    const deviceContainer = document.getElementById('deviceSelectContainer');
    const compactAssignee = document.getElementById('assigneeCompactContainer');
    const assigneePanel = document.getElementById('assigneeExpandPanel');
    const observerPanel = document.getElementById('observerExpandPanel');
    
    // Firma/Kunde/Gerät/Projekt (überspringen wenn Bearbeitungsansicht offen bleiben soll)
    if (!skipCompanyCustomerDevice && (ticket.company_name || ticket.customer_name || ticket.device_name || (ticket.projects && ticket.projects.length))) {
        if (compactCompanyCustomer) {
            compactCompanyCustomer.style.display = 'block';
        }
        if (customerContainer) {
            customerContainer.style.display = 'none';
        }
        if (deviceContainer) {
            deviceContainer.style.display = 'none';
        }
    }
    
    // Bearbeiter & Beobachter - Kompakte Card sichtbar, Expand-Panels eingeklappt
    if (compactAssignee) {
        compactAssignee.style.display = 'block';
    }
    const anfordererPanel = document.getElementById('anfordererExpandPanel');
    if (anfordererPanel) anfordererPanel.classList.remove('is-expanded');
    if (assigneePanel) {
        assigneePanel.classList.remove('is-expanded');
        updateAssigneeEditButtonIcon(false);
    }
    if (observerPanel) {
        observerPanel.classList.remove('is-expanded');
        updateObserverEditButtonIcon(false);
    }
    
    // Datumsfelder
    const faelligContainer = document.getElementById('faelligDatumContainer');
    const geplantContainer = document.getElementById('geplantDatumContainer');
    if (faelligContainer) {
        faelligContainer.style.display = 'none';
    }
    if (geplantContainer) {
        geplantContainer.style.display = 'none';
    }
}
</script>

<style>
/* Mobil: Ticket-Infos als Sheet unter Top-Nav (öffnet per Titelzeile in der Nav) */
@media (max-width: 1023px) {
  body.ticket-view-mobile-shell.ticket-mobile-info-open #ticketInfoPanelRoot {
    transform: translateX(0) !important;
    pointer-events: auto !important;
  }
  body.ticket-view-mobile-shell .ticket-mobile-info-backdrop {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    top: calc(env(safe-area-inset-top, 0px) + 3.5rem);
    z-index: 55;
    background: rgba(15, 23, 42, 0.28);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
  }
  .dark body.ticket-view-mobile-shell .ticket-mobile-info-backdrop {
    background: rgba(0, 0, 0, 0.45);
  }
  body.ticket-view-mobile-shell.ticket-mobile-info-open .ticket-mobile-info-backdrop {
    opacity: 1;
    pointer-events: auto;
  }
  body.ticket-view-mobile-shell.ticket-mobile-info-open {
    overflow: hidden !important;
  }
}
/* Chat-Grid: Zeile füllt verfügbare Höhe (auch < lg, sonst kollabiert die Zeile auf auto) */
#service-view-chat-grid { grid-template-rows: minmax(0, 1fr); }

/* Ticket-Detail: volle Viewport-Höhe bis zum Chat (Tablet + Desktop; Flex-Kette bricht sonst oft ab) */
@media (max-width: 1023px) {
  body.service-mobile-fullscreen main.ticket-view-detail #ticket-view-page-stack {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    display: flex !important;
    flex-direction: column !important;
  }
  body.service-mobile-fullscreen main.ticket-view-detail #ticket-view-outer-grid {
    flex: 1 1 0% !important;
    min-height: 0 !important;
  }
  body.service-mobile-fullscreen #ticket-view-chat-section {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    display: flex !important;
    flex-direction: column !important;
  }
  body.service-mobile-fullscreen #ticket-view-chat-section > .flex-1 {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    display: flex !important;
    flex-direction: column !important;
  }
  body.service-mobile-fullscreen #service-view-chat-grid {
    display: flex !important;
    flex-direction: column !important;
    flex: 1 1 0% !important;
    min-height: 0 !important;
    height: auto !important;
    gap: 0 !important;
  }
  body.service-mobile-fullscreen #service-view-chat-column {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    height: auto !important;
    display: flex !important;
    flex-direction: column !important;
  }
  body.service-mobile-fullscreen #service-view-chat-column #chatTicketContent {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
  }
  body.service-mobile-fullscreen #service-view-chat-column #chatInputArea {
    flex-shrink: 0 !important;
  }
}
/* Mobil (< md): Breadcrumb ausgeblendet – nur eine Grid-Zeile, sonst bleibt eine leere 1fr-Zeile unten */
@media (max-width: 767px) {
  body.service-mobile-fullscreen main.ticket-view-detail #ticket-view-outer-grid {
    grid-template-rows: minmax(0, 1fr) !important;
    height: 100% !important;
    max-height: 100% !important;
    min-height: 0 !important;
    overflow: hidden !important;
  }
  body.service-mobile-fullscreen #ticket-view-chat-section {
    height: 100% !important;
    max-height: 100% !important;
    min-height: 0 !important;
    align-self: stretch !important;
  }
  body.service-mobile-fullscreen #ticket-view-chat-section > .flex-1 {
    min-height: 0 !important;
    overflow: hidden !important;
  }
  body.service-mobile-fullscreen #service-view-chat-grid {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow: hidden !important;
  }
  body.service-mobile-fullscreen #service-view-chat-column {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow: hidden !important;
  }
}
@media (min-width: 1024px) {
  body.service-mobile-fullscreen main.ticket-view-detail #ticket-view-page-stack {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
  }
  body.service-mobile-fullscreen main.ticket-view-detail #ticket-view-outer-grid {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow: hidden !important;
  }
  body.service-mobile-fullscreen #ticket-view-chat-section {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    height: 100% !important;
    max-height: 100% !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
  }
  body.service-mobile-fullscreen #ticket-view-chat-section > .flex-1 {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
  }
  body.service-mobile-fullscreen #service-view-chat-grid {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    height: 100% !important;
    max-height: 100% !important;
    overflow: hidden !important;
    align-content: stretch !important;
  }
  body.service-mobile-fullscreen #service-view-chat-column {
    min-height: 0 !important;
    height: 100% !important;
    max-height: 100% !important;
    align-self: stretch !important;
    overflow: hidden !important;
  }
}
/* Rechte Spalte: Scroll aktivieren */
@media (min-width: 1024px) {
  #rightColumnScrollContainer {
    min-height: 0;
    max-height: calc(100vh - 5rem);
    overflow-y: scroll !important;
    overflow-x: hidden;
  }
}
/* Custom Scrollbar für Chat und Ticket-Infos */
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.5);
    border-radius: 4px;
    transition: background 0.2s ease;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.7);
}

/* Dark Mode Scrollbar */
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(75, 85, 99, 0.5);
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(75, 85, 99, 0.7);
}

/* Firefox Scrollbar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.dark .custom-scrollbar {
    scrollbar-color: rgba(75, 85, 99, 0.5) transparent;
}

/* Anforderer/Bearbeiter/Beobachter: Inline-Expand-Panels */
.anforderer-expand-panel,
.assignee-expand-panel,
.observer-expand-panel {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
    transition: max-height 0.35s ease-out, opacity 0.25s ease-out;
}
.anforderer-expand-panel.is-expanded {
    max-height: 200px;
}
.assignee-expand-panel.is-expanded,
.observer-expand-panel.is-expanded {
    max-height: 420px;
    opacity: 1;
    pointer-events: auto;
}

.appointment-add-expand-panel {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
    transition: max-height 0.35s ease-out, opacity 0.25s ease-out;
}
.appointment-add-expand-panel.is-expanded {
    max-height: 320px;
    opacity: 1;
    pointer-events: auto;
}

/* Firma/Kunde/Gerät/Projekt: Übersichts-Card mit Akkordeons */
#overviewAccordionsRoot .overview-acc-header {
    cursor: pointer;
}
#overviewAccordionsRoot .overview-acc-toggle {
    cursor: pointer;
}
#overviewAccordionsRoot,
#overviewAccordionsRoot.overview-acc-root {
    margin-top: 0 !important;
}
.overview-acc-toggle[aria-expanded="true"] .overview-acc-chevron {
    transform: rotate(180deg);
}
.overview-acc-header {
    transition: background-color 0.2s ease;
}
.overview-acc-header:hover {
    background: linear-gradient(90deg, rgba(248, 250, 252, 0.65) 0%, rgba(255, 255, 255, 0) 55%);
}
.dark .overview-acc-header:hover {
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0) 55%);
}
.overview-accordion-section {
    transition: background-color 0.2s ease;
}
/* Schnellaktionen unter Übersichts- und Edit-Cards */
button.overview-action-link,
a.overview-action-link {
    font-size: 0.75rem;
    line-height: 1.25rem;
    font-weight: 500;
    color: rgb(37 99 235);
    background: transparent;
    border: none;
    padding: 0;
    cursor: pointer;
    text-decoration: none;
    text-align: left;
}
.dark button.overview-action-link,
.dark a.overview-action-link {
    color: rgb(186 200 255);
}
button.overview-action-link:hover,
a.overview-action-link:hover {
    text-decoration: underline;
}
/* Kontextmenü Übersichts-Cards */
#overviewCardCtxMenu .overview-ctx-menu-item {
    text-decoration: none;
}
#overviewCardCtxMenu .overview-ctx-menu-icon svg {
    display: block;
}
#overviewCardCtxMenu a.overview-ctx-menu-item:hover {
    text-decoration: underline;
}
.overview-acc-panel:not(.hidden) {
    animation: overview-acc-open 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes overview-acc-open {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Edit-Cards (Firma/Kunde/Gerät): nacheinander einblenden, Schließ-Animation */
.edit-card {
    opacity: 0;
    transform: none;
    max-height: 600px;
    will-change: opacity, max-height;
    transition: opacity 0.2s ease, max-height 0.24s ease, margin 0.2s ease, padding 0.2s ease;
}
.edit-card.edit-card-company { transition-delay: 0s; }
.edit-card.edit-card-customer { transition-delay: 0s; }
.edit-card.edit-card-device { transition-delay: 0s; }
.edit-card.is-visible {
    opacity: 1;
    transform: none;
}
.edit-card.is-visible.edit-card-company { transition-delay: 0s; }
.edit-card.is-visible.edit-card-customer { transition-delay: 0s; }
.edit-card.is-visible.edit-card-device { transition-delay: 0s; }
.edit-card.is-closing {
    max-height: 0 !important;
    opacity: 0;
    transform: none;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    overflow: hidden;
    border-width: 0;
    transition-delay: 0s;
    transition-duration: 0.2s;
}

/* Kunden/Geraete Edit-Cards: schlicht & modern */
#customerSelectContainer,
#deviceSelectContainer {
    background: linear-gradient(180deg, rgba(255, 255, 255, 1) 0%, rgba(248, 250, 252, 1) 100%);
    border-color: rgba(229, 231, 235, 1);
}
.dark #customerSelectContainer,
.dark #deviceSelectContainer {
    background: linear-gradient(180deg, rgba(17, 24, 39, 0.92) 0%, rgba(15, 23, 42, 0.98) 100%);
    border-color: rgba(148, 163, 184, 0.22);
}
/* Kompakt-Übersicht nach Auswahl einblenden */
#companyCustomerCompactContainer.compact-enter {
    animation: none;
}

/* Mobile Ticket: Chat-Eingabe am unteren Bildschirmrand, Höhe folgt Visual Viewport (Tastatur) */
@media (max-width: 1023px) {
  body.service-mobile-fullscreen.ticket-view-mobile-shell #chatInputArea {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    max-width: 100vw;
    box-sizing: border-box;
    z-index: 44;
    margin: 0;
    border-radius: 0;
    border-left: none;
    border-right: none;
    box-shadow: 0 -4px 24px -8px rgba(15, 23, 42, 0.08);
  }
  .dark body.service-mobile-fullscreen.ticket-view-mobile-shell #chatInputArea {
    box-shadow: 0 -4px 28px -8px rgba(0, 0, 0, 0.35);
  }
  body.service-mobile-fullscreen.ticket-view-mobile-shell #chatInputArea.ticket-chat-input-keyboard-open {
    padding-bottom: 0.5rem !important;
  }
}

/* Chat-Bereich: moderner Look */
.service-chat-container {
    border-radius: 0.75rem;
}
.service-chat-messages {
    padding: 1rem 1.25rem;
    background: linear-gradient(180deg, rgba(249, 250, 251, 0.6) 0%, rgba(243, 244, 246, 0.4) 100%);
}
.dark .service-chat-messages {
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.25) 0%, rgba(15, 23, 42, 0.2) 100%);
}
.service-chat-input-bar:focus-within {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.dark .service-chat-input-bar:focus-within {
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.25);
}
#chatTicketHeader {
    flex-shrink: 0;
}
/* Status-Badge im Header: nicht abschneiden */
#chatTicketHeader span.rounded-full {
    flex-shrink: 0;
    white-space: nowrap;
    overflow: visible;
}

/* Link-Farbe in eigenen Sprechblasen (blau) */
.service-chat-messages .chat-row-sent a {
    color: rgb(29 78 216);
    text-decoration: underline;
}
.service-chat-messages .chat-row-sent a:hover {
    color: rgb(30 64 175);
}
.dark .service-chat-messages .chat-row-sent a {
    color: rgb(147 197 253);
}
.dark .service-chat-messages .chat-row-sent a:hover {
    color: rgb(191 219 254);
}
/* Eigene Nachrichten näher an den rechten Rand (kein störender Abstand) */
.service-chat-messages .chat-row-sent {
    margin-right: -0.75rem;
}

</style>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
