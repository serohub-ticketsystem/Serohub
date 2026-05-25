<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/service_log_helper.php';
require_once dirname(__DIR__) . '/customers/helper/encryption.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
requireLogin();

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

// Firmen und Kunden für Filter laden
$customers = [];

if ($userRole === 'Admin' || $userRole === 'Techniker') {
    // Alle aktiven Kunden mit Kundennummer, Firmenname und Adresse (wie Kundenauswahl in create.php)
    $stmt = $pdo->query("SELECT c.id, c.name, c.kundennummer, c.email, c.adresse, c.plz, c.ort, c.company_id, comp.name as company_name FROM customers c LEFT JOIN companies comp ON c.company_id = comp.id WHERE c.status = 'aktiv' ORDER BY c.name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
    // Kunden der Firma und Kunden ohne Firma mit Kundennummer und Adresse
    $stmt = $pdo->prepare("SELECT c.id, c.name, c.kundennummer, c.email, c.adresse, c.plz, c.ort, c.company_id, comp.name as company_name FROM customers c LEFT JOIN companies comp ON c.company_id = comp.id WHERE (c.company_id = ? OR c.company_id IS NULL) AND c.status = 'aktiv' ORDER BY c.name");
    $stmt->execute([$userCompanyId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
foreach ($customers as &$c) {
    decrypt_customer_row($c);
    if (isset($c['company_name'])) $c['company_name'] = decrypt_from_db($c['company_name']);
}
unset($c);

// Bearbeiter für Filter (Admin, Techniker) – nur anzeigen für Admin/Techniker
$assignees = [];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    try {
        $stmt = $pdo->query("SELECT id, vorname, nachname, email, rolle FROM users WHERE status = 'aktiv' AND rolle IN ('Admin', 'Techniker') ORDER BY nachname, vorname");
        $assignees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der Bearbeiter: " . $e->getMessage());
        $assignees = [];
    }
}

// Firmen für erweiterte Filter (Desktop)
$companiesForFilter = [];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    try {
        $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
        $companiesForFilter = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($companiesForFilter as &$compRow) {
            if (isset($compRow['name'])) {
                $compRow['name'] = decrypt_from_db($compRow['name']);
            }
        }
        unset($compRow);
    } catch (PDOException $e) {
        error_log('Fehler beim Laden der Firmen für erweiterte Filter: ' . $e->getMessage());
        $companiesForFilter = [];
    }
} elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv' LIMIT 1");
        $stmt->execute([$userCompanyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (isset($row['name'])) {
                $row['name'] = decrypt_from_db($row['name']);
            }
            $companiesForFilter = [$row];
        }
    } catch (PDOException $e) {
        $companiesForFilter = [];
    }
}

service_log($pdo, $userId, 'sonstiges', 0, 'viewed', null, null, null, 'Tickets: Übersicht aufgerufen');
include dirname(__DIR__) . '/assets/frontend/head.php';
$navMobileShowIntegratedFilter = true;
$navMobileCompactTitle = 'Alle Tickets';
$navMobileTicketsSearchToggle = true;
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 max-lg:pt-[calc(env(safe-area-inset-top,0px)+3.5rem+1rem)] lg:pt-0 overflow-hidden max-lg:overflow-visible service-main-content app-mobile-no-root-overscroll">
  <main class="mx-4 mt-2 flex flex-col overflow-hidden min-h-0 max-lg:overflow-visible max-lg:min-h-0 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 service-main">
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
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Tickets</span>
          </div>
        </li>
      </ol>
    </nav>
  <div class="relative col-span-full max-lg:flex max-lg:flex-1 max-lg:min-h-0 max-lg:flex-col service-content-outer">
    <div class="max-lg:flex max-lg:flex-1 max-lg:min-h-0 max-lg:flex-col service-views-wrap">
    <div class="relative hidden lg:block service-toolbar-wrap flex-shrink-0">
      <div class="flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0">
        <div class="flex flex-col w-full space-y-3 lg:flex-1 lg:min-w-0 md:space-y-0 md:flex-row md:items-center md:gap-2">
          <!-- Kompakte Filter-Leiste -->
          <div class="flex flex-wrap items-center gap-1.5 md:gap-2 flex-1 min-w-0">
            <!-- Status-Filter Dropdown -->
            <div class="relative w-auto" id="status-filter-container">
              <button type="button" id="status-filter-button" class="status-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200" title="Status">
                <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                  <path d="M15.583 8.445h.01M10.86 19.71l-6.573-6.63a.993.993 0 0 1 0-1.4l7.329-7.394A.98.98 0 0 1 12.31 4l5.734.007A1.968 1.968 0 0 1 20 5.983v5.5a.992.992 0 0 1-.316.727l-7.44 7.5a.974.974 0 0 1-1.384.001Z"/>
                </svg>
                <span id="status-filter-text" class="filter-btn-label whitespace-nowrap">Offen</span>
                <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
              <div id="status-filter-menu" class="service-filter-dropdown-shadow hidden absolute z-10 min-w-[12rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base overflow-hidden" data-popper-placement="bottom">
                <div class="py-1 overflow-y-auto max-h-[20rem]">
                  <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="offen_combined">
                    Offen
                  </button>
                  <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="neu">
                    Neu
                  </button>
                  <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="in_bearbeitung">
                    In Bearbeitung
                  </button>
                  <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="warteschlange">
                    Wartend
                  </button>
                  <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="bestellung_offen">
                    Bestellung offen
                  </button>
                  <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="geschlossen">
                    Geschlossen
                  </button>
                  <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="archiv">
                    Archiv
                  </button>
                </div>
              </div>
              <input type="hidden" id="status-filter" value="offen_combined">
            </div>
            <?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') && !empty($customers)): ?>
            <div class="relative w-auto" id="customer-filter-container">
              <button type="button" id="customer-filter-button" class="customer-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200" title="Kunde">
                <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
</svg>

                <span id="customer-filter-text" class="filter-btn-label whitespace-nowrap">Alle Kunden</span>
                <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                
              </button>
              <input type="hidden" id="customer-filter" value="">
            </div>
            <?php endif; ?>
            <?php if (($userRole === 'Admin' || $userRole === 'Techniker') && !empty($assignees)): ?>
            <div class="relative w-auto" id="assignee-filter-container">
              <button type="button" id="assignee-filter-button" class="assignee-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200" title="Bearbeiter">
                <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/>
                </svg>
                <span id="assignee-filter-text" class="filter-btn-label whitespace-nowrap">Alle Bearbeiter</span>
                <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
              <div id="assignee-filter-menu" class="service-filter-dropdown-shadow hidden absolute z-10 min-w-[12rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base overflow-hidden" data-popper-placement="bottom">
                <div class="py-1 overflow-y-auto max-h-[20rem]">
                  <button type="button" class="assignee-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-assignee-id="" data-assignee-display-name="Alle Bearbeiter">Alle Bearbeiter</button>
                  <?php
                  $userIdInt = (int)$userId;
                  foreach ($assignees as $assignee):
                    $aid = (int)($assignee['id'] ?? 0);
                    if ($aid === $userIdInt) {
                      ?><button type="button" class="assignee-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-assignee-id="<?= $aid ?>" data-assignee-display-name="Mir zugewiesen">Mir zugewiesen</button><?php
                      break;
                    }
                  endforeach;
                  foreach ($assignees as $assignee):
                    $aid = (int)($assignee['id'] ?? 0);
                    if ($aid === $userIdInt) continue;
                    $assigneeName = trim(($assignee['vorname'] ?? '') . ' ' . ($assignee['nachname'] ?? ''));
                  ?>
                  <button type="button" class="assignee-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-assignee-id="<?= $aid ?>" data-assignee-display-name="<?= htmlspecialchars($assigneeName) ?>"><?= htmlspecialchars($assigneeName) ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
              <input type="hidden" id="assignee-filter" value="">
            </div>
            <?php endif; ?>
            <!-- Anzeige: Ansicht + Sortierung (kombiniertes Dropdown, nur Desktop) -->
            <div class="hidden md:block relative shrink-0" id="display-dropdown-container">
              <button type="button" id="display-dropdown-button" class="display-dropdown-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200" aria-haspopup="true" aria-expanded="false" aria-controls="display-dropdown-menu">
                <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="filter-btn-label whitespace-nowrap">Anzeige</span>
                <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
              <div id="display-dropdown-menu" class="service-filter-dropdown-shadow hidden absolute z-50 right-0 mt-1 w-72 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-xl overflow-hidden" role="menu" aria-labelledby="display-dropdown-button">
                <div class="px-3 pt-3 pb-3 border-b border-gray-100 dark:border-primary-120/60">
                  <div class="grid grid-cols-3 gap-1 p-1 rounded-lg bg-gray-100/80 dark:bg-primary-200/40" role="group" aria-label="Ansicht">
                    <button type="button" class="display-view-option flex flex-col items-center justify-center gap-1 px-2 py-2 rounded-md text-xs font-medium text-gray-500 dark:text-primary-210 hover:text-gray-900 dark:hover:text-primary-100 transition-colors" data-view="table" title="Tabellenansicht">
                      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                      <span>Tabelle</span>
                    </button>
                    <button type="button" class="display-view-option flex flex-col items-center justify-center gap-1 px-2 py-2 rounded-md text-xs font-medium text-gray-500 dark:text-primary-210 hover:text-gray-900 dark:hover:text-primary-100 transition-colors" data-view="cards" title="Kartenansicht">
                      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                      <span>Karten</span>
                    </button>
                    <button type="button" class="display-view-option flex flex-col items-center justify-center gap-1 px-2 py-2 rounded-md text-xs font-medium text-gray-500 dark:text-primary-210 hover:text-gray-900 dark:hover:text-primary-100 transition-colors" data-view="chat" title="Chat-Ansicht">
                      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                      <span>Chat</span>
                    </button>
                  </div>
                </div>
                <div class="px-3 py-3 border-b border-gray-100 dark:border-primary-120/60">
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 shrink-0 text-sm text-gray-700 dark:text-primary-200">
                      <svg class="w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                      <span>Sortierung</span>
                    </div>
                    <div class="relative min-w-0 flex-1 max-w-[11.5rem]" id="sort-dropdown-container">
                      <button type="button" id="sort-dropdown-button" class="sort-filter-btn w-full flex items-center justify-between gap-1 pl-2.5 pr-2 py-1.5 text-xs font-medium text-gray-700 dark:text-primary-200 rounded-lg border border-gray-200 dark:border-primary-320 bg-white dark:bg-primary-700/80 hover:bg-gray-50 dark:hover:bg-primary-760 focus:outline-none transition-colors">
                        <span id="sort-dropdown-text" class="truncate">Geändert</span>
                        <svg class="w-3.5 h-3.5 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                      </button>
                      <div id="sort-dropdown-menu" class="service-filter-dropdown-shadow hidden absolute z-[60] min-w-[10rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base overflow-hidden right-0" data-popper-placement="bottom">
                        <div class="py-1 divide-y divide-gray-100 dark:divide-primary-120/60 overflow-y-auto max-h-[12rem]">
                          <button type="button" class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="erstellt_datum">Erstellt</button>
                          <button type="button" class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="geaendert_datum">Geändert</button>
                          <button type="button" class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="naechster_termin">Nächster Termin</button>
                        </div>
                      </div>
                      <input type="hidden" id="sort-selection" value="">
                    </div>
                  </div>
                </div>
                <div class="px-3 py-3">
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 shrink-0 text-sm text-gray-700 dark:text-primary-200">
                      <svg class="w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0v4m0-4l-4 4m4-4l4 4"/></svg>
                      <span>Richtung</span>
                    </div>
                    <div class="grid grid-cols-2 gap-1 p-1 rounded-lg bg-gray-100/80 dark:bg-primary-200/40 min-w-0 flex-1 max-w-[13rem]" role="group" aria-label="Sortierrichtung">
                      <button type="button" class="display-sort-dir-option px-2 py-1.5 rounded-md text-[11px] font-medium leading-tight text-gray-500 dark:text-primary-210 hover:text-gray-900 dark:hover:text-primary-100 transition-colors text-center" data-direction="desc" title="Absteigend sortieren">
                        <span id="sort-dir-label-desc">Neueste zuerst</span>
                      </button>
                      <button type="button" class="display-sort-dir-option px-2 py-1.5 rounded-md text-[11px] font-medium leading-tight text-gray-500 dark:text-primary-210 hover:text-gray-900 dark:hover:text-primary-100 transition-colors text-center" data-direction="asc" title="Aufsteigend sortieren">
                        <span id="sort-dir-label-asc">Älteste zuerst</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Erweiterte Filter (nur Desktop) -->
            <div class="hidden md:inline-flex items-center gap-1.5">
              <button type="button" id="advancedFilterBtn" class="advanced-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200" title="Erweiterte Filter" aria-label="Erweiterte Filter">
                <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/>
                </svg>
                <span class="filter-btn-label whitespace-nowrap">Filter</span>
              </button>
            </div>
          </div>
        </div>
        <div class="flex flex-col items-stretch justify-end flex-shrink-0 w-full pb-4 md:pb-0 md:w-auto md:flex-row md:items-center md:justify-end">
          <form class="w-full md:w-auto search-form-base shrink-0" id="search-form" role="search">
            <label for="search" class="sr-only">Tickets durchsuchen</label>
            <div class="relative w-full min-w-[14rem] md:min-w-[18rem] md:max-w-[26rem]" id="search-wrapper">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-500 dark:text-primary-210" aria-hidden="true">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
              </span>
              <input type="search" id="search"
                     class="service-toolbar-search-input block w-full box-border h-10 min-h-10 max-h-10 py-0 pl-10 pr-4 text-sm font-medium leading-10 text-gray-900 rounded-xl border border-gray-200 bg-white/80 placeholder-gray-500 hover:bg-white hover:border-gray-300 focus:outline-none focus:border-primary-400 focus:bg-white transition-all duration-200 dark:bg-primary-700/80 dark:border-primary-320 dark:text-primary-200 dark:placeholder-primary-210 dark:hover:bg-primary-760 dark:hover:border-primary-300 dark:focus:border-primary-400 dark:focus:bg-primary-760"
                     placeholder="Tickets durchsuchen …" autocomplete="off">
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- Toolbar-Ende (hidden lg:block) – Mobile: Filter in Top-Nav (nav.php), kein zweites Toolbar-Band -->

    <!-- Mobile: Kompakte Ticket-Liste (nur sichtbar auf Mobile), scrollt mit der Seite -->
    <div id="mobileTicketsWrap" class="lg:hidden mobile-tickets-fullheight flex flex-col">
      <div id="tickets-mobile-dashboard" class="lg:hidden sticky top-0 z-[12] w-full min-w-0 pt-0">
        <div id="tickets-mobile-search-anim" class="tickets-mobile-search-anim w-full min-w-0" aria-hidden="true">
          <div class="tickets-mobile-search-anim__measure min-h-0 w-full min-w-0 overflow-hidden px-0.5 py-0">
            <div id="tickets-mobile-search-inner" class="tickets-mobile-search-inner w-full min-w-0 pb-2">
              <label for="tickets-mobile-search" class="sr-only">Tickets durchsuchen</label>
              <div class="relative mt-0 flex w-full min-w-0 items-center rounded-2xl bg-white pl-3 pr-1 shadow-[0_1px_3px_rgba(15,23,42,0.06)] ring-1 ring-inset ring-gray-200/90 transition-[box-shadow,ring-color] focus-within:ring-2 focus-within:ring-primary-500/25 dark:bg-primary-100 dark:ring-primary-120/70 dark:shadow-[0_1px_3px_rgba(0,0,0,0.2)] dark:focus-within:ring-primary-400/30">
                <span class="pointer-events-none flex h-9 w-9 shrink-0 items-center justify-center text-gray-400 dark:text-primary-300" aria-hidden="true">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="text" id="tickets-mobile-search" enterkeyhint="search" inputmode="search" autocomplete="off" class="min-w-0 w-full flex-1 basis-0 border-0 bg-transparent py-2.5 pr-3 text-[0.9375rem] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-primary-100 dark:placeholder-primary-240" placeholder="Tickets durchsuchen …">
              </div>
            </div>
          </div>
        </div>
      </div>
      <div id="mobileTicketsList" class="space-y-3 w-full max-w-full" aria-busy="true"></div>
    </div>

    <!-- Tabellenansicht -->
    <div id="tableView" class="max-lg:hidden overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-primary-120 dark:bg-primary-100">
      <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
        <thead class="bg-white text-xs uppercase text-gray-500 dark:bg-primary-100 dark:text-gray-400 border-b border-gray-100 dark:border-primary-120/60">
        <tr>
<th id="sort-titel" data-sort="titel" class="px-3 py-3 font-semibold ">
  <div class="flex items-center">
    Auftrag
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-erstellt_von" data-sort="erstellt_von" class="px-3 py-3 font-semibold ">
  <div class="flex items-center">
    Anforderer
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-device_name" data-sort="device_name" class="px-3 py-3 font-semibold">
  <div class="flex items-center">
    Gerät
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-customer_name" data-sort="customer_name" class="px-3 py-3 font-semibold  " <?php if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin'): ?>style="display: none;"<?php endif; ?>>
  <div class="flex items-center">
    Kunde
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-status" data-sort="status" class="px-3 py-3 font-semibold">
  <div class="flex items-center">
    Status
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-naechster_termin" data-sort="naechster_termin" class="px-3 py-3 font-semibold ">
  <div class="flex items-center">
    Nächster Termin
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-zugewiesen_an" data-sort="zugewiesen_an" class="w-12 px-1 py-3 text-center" <?php if ($userRole !== 'Admin' && $userRole !== 'Techniker'): ?>style="display: none;"<?php endif; ?> title="Bearbeiter" aria-label="Bearbeiter"></th>
        </tr>
    </thead>
    <tbody id="ticketsList" class="divide-y divide-gray-100 dark:divide-primary-120/60" aria-busy="true"></tbody>
</table>     
    </div>
    
    <!-- Card-Ansicht -->
    <div id="cardsView" class="hidden max-lg:hidden">
      <div id="ticketCards" class="grid grid-cols-1 gap-3 w-full max-w-full" aria-busy="true"></div>
    </div>

    <!-- Chat-Ansicht (auf Mobile ausgeblendet, dort wird mobileTicketsWrap genutzt) – Styling wie view.php -->
    <div id="chatView" class="hidden max-lg:hidden rounded-xl overflow-hidden border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-card service-chat-container service-chat-view-panel" style="height: calc(100vh - 20rem);">
      <div class="flex h-full">
        <!-- Ticketliste (Sidebar) -->
        <div class="flex flex-col w-full md:max-w-80 border-e border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100" style="height: 100%;">
          <div class="overflow-y-auto custom-scrollbar flex-1 min-h-0" id="chatTicketListWrap">
            <ul id="chatTicketList" aria-busy="true"></ul>
          </div>
        </div>
        
        <!-- Hauptbereich mit Chat (auf Mobile ausgeblendet) -->
        <div class="relative flex-1 flex flex-col min-h-0 bg-white dark:bg-primary-50 h-full overflow-hidden hidden md:flex">
          <!-- Chat Header (wie view.php) -->
          <div class="flex-shrink-0 flex items-center border-b border-gray-100 dark:border-primary-140 bg-white dark:bg-primary-100 px-4 py-3 shadow-sm min-h-[2.75rem]" id="chatTicketHeader">
            <div class="flex items-center justify-center h-full w-full">
              <p class="text-gray-500 dark:text-primary-210 text-sm">Wählen Sie einen Ticket aus</p>
            </div>
          </div>
          
          <!-- Chat Messages Area (wie view.php: service-chat-messages) -->
          <div class="service-chat-messages flex-1 overflow-y-auto overflow-x-hidden min-h-0 custom-scrollbar space-y-1 pb-2" id="chatTicketContent">
            <div class="flex flex-col items-center justify-center h-full min-h-[280px] text-center px-4">
              <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-primary-200/40 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-gray-400 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Kein Ticket ausgewählt</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-primary-240">Wählen Sie einen Ticket aus der Liste aus, um Nachrichten anzuzeigen.</p>
            </div>
          </div>
          
          <!-- Chat Input Area (wie view.php: service-chat-input-bar) -->
          <div class="flex-shrink-0 bg-white dark:bg-primary-100 border-t border-gray-100 dark:border-primary-140 px-4 py-3" id="chatInputArea" style="display: none;">
            <div class="service-chat-input-bar flex items-center gap-2 rounded-2xl bg-gray-50 dark:bg-primary-120/80 border border-gray-200 dark:border-primary-140 px-3 py-2 focus-within:ring-2 focus-within:ring-primary-250/30 focus-within:border-primary-250 dark:focus-within:border-primary-250 transition-all duration-200">
              <!-- Nachrichtentyp-Auswahl (nur Nachricht, Aufgabe, Lösung – wie view.php) -->
              <div class="flex-shrink-0 relative flex items-center">
                <div class="inline-flex rounded-xl border border-gray-200 dark:border-primary-200 p-0.5 gap-0.5" role="group">
                  <!-- Nachricht -->
                  <button type="button" data-message-type="nachricht" data-tooltip-target="tooltip-nachricht" class="message-type-btn inline-flex items-center justify-center text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 bg-transparent hover:bg-white dark:hover:bg-primary-100 rounded-lg text-sm h-8 w-8 focus:outline-none transition-colors" title="Nachricht">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-5l-5 5v-5Z"/></svg>
                  </button>
                  <div id="tooltip-nachricht" role="tooltip" class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-white transition-opacity duration-300 bg-gray-900 dark:bg-primary-800 rounded-lg shadow-sm opacity-0 tooltip bottom-full left-0 mb-1">Nachricht</div>
                  <!-- Aufgabe -->
                  <button type="button" data-message-type="aufgabe" data-tooltip-target="tooltip-aufgabe" class="message-type-btn inline-flex items-center justify-center text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 bg-transparent hover:bg-white dark:hover:bg-primary-100 rounded-lg text-sm h-8 w-8 focus:outline-none transition-colors" title="Aufgabe">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 1 1 0-18c1.052 0 2.062.18 3 .512M7 9.577l3.923 3.923 8.5-8.5M17 14v6m-3-3h6"/></svg>
                  </button>
                  <div id="tooltip-aufgabe" role="tooltip" class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-white transition-opacity duration-300 bg-gray-900 dark:bg-primary-800 rounded-lg shadow-sm opacity-0 tooltip bottom-full left-0 mb-1">Aufgabe</div>
                  <!-- Lösung (nur Admin/Techniker, rechts in der Gruppe, anderes Icon wie view.php) -->
                  <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                  <button type="button" data-message-type="loesung" data-tooltip-target="tooltip-loesung" class="message-type-btn inline-flex items-center justify-center text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 bg-transparent hover:bg-white dark:hover:bg-primary-100 rounded-lg text-sm h-8 w-8 focus:outline-none transition-colors" title="Lösung">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 6 2 2 4-4m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/></svg>
                  </button>
                  <div id="tooltip-loesung" role="tooltip" class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-white transition-opacity duration-300 bg-gray-900 dark:bg-primary-800 rounded-lg shadow-sm opacity-0 tooltip bottom-full left-0 mb-1">Lösung</div>
                  <?php endif; ?>
                </div>
                <input type="hidden" id="message-type-select" value="nachricht">
              </div>
              <!-- Eingabefeld: Anhang, Bestellung (Modal), Textarea, Senden – Bestellung nicht in Button-Gruppe -->
              <div class="flex-1 flex items-center gap-2 min-w-0">
                <button type="button" id="attach-file-btn" class="flex-shrink-0 inline-flex items-center justify-center rounded-xl p-2 text-gray-500 dark:text-primary-240 hover:text-primary-600 dark:hover:text-primary-250 hover:bg-gray-200/60 dark:hover:bg-primary-140 transition-colors" title="Datei anhängen" aria-label="Datei anhängen">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                </button>
                <button type="button" id="open-order-modal-btn" class="flex-shrink-0 inline-flex items-center justify-center rounded-xl p-2 text-gray-500 dark:text-primary-240 hover:text-primary-600 dark:hover:text-primary-250 hover:bg-gray-200/60 dark:hover:bg-primary-140 transition-colors" title="Bestellung anlegen" aria-label="Bestellung anlegen">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/></svg>
                </button>
                <label for="chat-message-input" class="sr-only">Nachricht schreiben</label>
                <textarea id="chat-message-input" rows="1" class="flex-1 min-w-0 resize-none border-0 bg-transparent px-1 py-2 text-sm text-gray-900 dark:text-primary-200 placeholder-gray-400 dark:placeholder-primary-240 focus:ring-0 focus:outline-none transition-none block w-full overflow-hidden self-center" placeholder="Nachricht schreiben…" style="min-height: 40px; height: 40px; max-height: 120px;" aria-label="Nachricht schreiben"></textarea>
                <button type="button" id="send-message-btn" class="flex-shrink-0 inline-flex items-center justify-center rounded-xl w-9 h-9 text-white bg-primary-500 hover:bg-primary-600 dark:bg-primary-250 dark:hover:bg-primary-260 shadow-sm hover:shadow transition-all focus:outline-none focus:ring-2 focus:ring-primary-250/50" aria-label="Senden">
                  <svg class="h-5 w-5 rotate-90 rtl:-rotate-90" fill="currentColor" viewBox="0 0 18 20"><path d="m17.914 18.594-8-18a1 1 0 0 0-1.828 0l-8 18a1 1 0 0 0 1.157 1.376L8 18.281V9a1 1 0 0 1 2 0v9.281l6.758 1.689a1 1 0 0 0 1.156-1.376Z"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
    </div>
  </div>
</div>
        </div>

<style>
/* Mobile Filter: Panel/Backdrop beginnen unter #main-nav (3.5rem + Safe Area); geschlossen unsichtbar */
@media (max-width: 1023px) {
  #mobileFilterSheet[aria-hidden="true"] {
    visibility: hidden !important;
    pointer-events: none !important;
  }
  #mobileFilterSheet[aria-hidden="false"] {
    visibility: visible !important;
  }
  #mobileFilterSheetPanel {
    max-height: 0;
    transition: max-height 0.32s ease-out;
    /* Rand: unten etwas kräftiger, seitlich einheitlich (passt zur Rundung) */
    border-left-color: rgb(209 213 219);
    border-right-color: rgb(209 213 219);
    border-bottom-color: rgb(203 213 225);
    /* Glas wie Footer-Pill; extra Außenlinie unten für klare Kante */
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255),
      inset 0 3px 14px -3px rgb(255 255 255 / 0.55),
      inset 0 -1px 0 0 rgb(15 23 42 / 0.07),
      0 4px 24px rgb(15 23 42 / 0.09),
      0 1px 0 0 rgb(15 23 42 / 0.1);
  }
  #mobileFilterSheetPanel.mobile-filter-sheet-open {
    max-height: min(85vh, 28rem);
  }
  #navMobileFilterToggleBtn[aria-expanded="true"] .nav-mobile-filter-chevron {
    transform: rotate(180deg);
  }
  /*
   * Dark: kein sichtbarer Rand links/rechts/oben; Abschluss wie Footer-Glas
   * (dieselben Inset-/Drop-Schatten wie app-mobile-footer-bubble dark:shadow-[…]).
   */
  .dark #mobileFilterSheetPanel {
    background-color: rgb(5 5 5 / 0.48) !important;
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.26),
      inset 0 3px 16px -3px rgb(255 255 255 / 0.1),
      inset 0 -1px 0 0 rgb(0 0 0 / 0.55),
      0 4px 30px rgb(0 0 0 / 0.52),
      0 18px 44px rgb(0 0 0 / 0.35);
  }
  /* Blöcke: einheitlicher Rhythmus */
  #mobileFilterSheetScroll .mobile-filter-sheet-row {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }
  /* Light: weiße Label-Schrift auf dunkler Pille (lesbar auf hellem Glas) */
  html:not(.dark) #mobileFilterSheetScroll .mobile-filter-sheet-row > label {
    color: #ffffff;
    background-color: #000000;
    padding: 0.35rem 0.65rem;
    border-radius: 0.5rem;
    display: inline-block;
    width: fit-content;
    max-width: 100%;
  }
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-row > label {
    color: #ffffff;
    background-color: transparent;
    padding: 0;
    display: inline-block;
    width: fit-content;
    max-width: 100%;
  }
  /* Felder: stärker gerundet, Höhe wie Touch-Pills, leichter Glas-Innenrand */
  #mobileFilterSheetScroll .mobile-filter-sheet-field {
    width: 100%;
    min-height: 3.25rem;
    box-sizing: border-box;
    border-radius: 1.25rem;
    border-width: 1px;
    border-style: solid;
    border-color: rgb(229 229 229);
    background-color: #f6f6f6;
    padding: 0.75rem 1.125rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    font-weight: 500;
    color: rgb(17 24 39);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.65),
      0 1px 2px rgb(15 23 42 / 0.04),
      0 4px 14px rgb(15 23 42 / 0.05);
  }
  #mobileFilterSheetScroll select.mobile-filter-sheet-field {
    appearance: none;
    -webkit-appearance: none;
    padding-right: 2.75rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1.125rem 1.125rem;
  }
  #mobileFilterSheetScroll .mobile-filter-sheet-field:focus {
    outline: none;
    border-color: rgb(191 219 254);
    box-shadow:
      0 0 0 2px rgb(59 130 246 / 0.22),
      inset 0 1px 0 0 rgb(255 255 255 / 0.65),
      0 1px 2px rgb(15 23 42 / 0.04),
      0 4px 14px rgb(15 23 42 / 0.05);
  }
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-field {
    border-color: transparent;
    border-width: 0;
    background-color: #121212;
    color: rgb(245 245 245);
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.05),
      0 2px 8px rgb(0 0 0 / 0.45),
      0 1px 0 0 rgb(255 255 255 / 0.03);
  }
  .dark #mobileFilterSheetScroll select.mobile-filter-sheet-field {
    background-color: #121212;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a1a1aa'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  }
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-field:focus {
    border-color: transparent;
    box-shadow:
      0 0 0 2px rgb(59 130 246 / 0.45),
      inset 0 1px 0 0 rgb(255 255 255 / 0.06),
      0 2px 10px rgb(0 0 0 / 0.5);
  }
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-field svg {
    color: rgb(161 161 170);
  }
}
</style>
<!-- Mobile Filter-Sheet: unter Top-Nav (h-14 + Safe Area), klappt nach unten auf -->
<div id="mobileFilterSheet" class="lg:hidden fixed inset-0 z-[68] pointer-events-none" aria-hidden="true">
  <!-- Leichtes Abdunkeln (~5 %), Klick = schließen -->
  <div id="mobileFilterSheetBackdrop" class="fixed left-0 right-0 bottom-0 z-[68] bg-black/[0.05] opacity-0 transition-opacity duration-300 pointer-events-auto cursor-pointer dark:bg-black/22 dark:backdrop-blur-[3px]" style="top: calc(env(safe-area-inset-top, 0px) + 3.5rem); pointer-events: none;"></div>
  <div id="mobileFilterSheetPanel" class="fixed inset-x-0 z-[69] flex w-full flex-col min-h-0 overflow-hidden rounded-b-[1.75rem] border border-t-0 border-gray-200 bg-white/88 backdrop-blur-2xl backdrop-saturate-200 dark:border-0 dark:bg-transparent pointer-events-auto" style="top: calc(env(safe-area-inset-top, 0px) + 3.5rem);" role="dialog" aria-modal="true" aria-label="Ticketfilter">
    <div id="mobileFilterSheetScroll" class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden space-y-5 px-4 pb-2 pt-4 custom-scrollbar sm:px-5">
      <?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') && !empty($customers)): ?>
      <div class="mobile-filter-sheet-row">
        <label for="mobile-sheet-customer-btn" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Kunde</label>
        <button type="button" id="mobile-sheet-customer-btn" class="mobile-filter-sheet-field flex w-full items-center justify-between gap-3 text-left"><span id="mobile-sheet-customer-label" class="min-w-0 truncate">Alle Kunden</span><svg class="h-4 w-4 shrink-0 text-gray-500 dark:text-primary-220" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
      </div>
      <?php endif; ?>
      <?php if (($userRole === 'Admin' || $userRole === 'Techniker') && !empty($assignees)):
          $mobileAssigneeUserId = (int)$userId;
      ?>
      <div class="mobile-filter-sheet-row">
        <label for="mobile-sheet-assignee-select" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Bearbeiter</label>
        <select id="mobile-sheet-assignee-select" class="mobile-filter-sheet-field">
          <option value="">Alle Bearbeiter</option>
          <?php
          foreach ($assignees as $assignee) {
              $aid = (int)($assignee['id'] ?? 0);
              if ($aid === $mobileAssigneeUserId) {
                  echo '<option value="' . (int)$aid . '">Mir zugewiesen</option>';
                  break;
              }
          }
          foreach ($assignees as $assignee) {
              $aid = (int)($assignee['id'] ?? 0);
              if ($aid === $mobileAssigneeUserId) {
                  continue;
              }
              $assigneeName = trim(($assignee['vorname'] ?? '') . ' ' . ($assignee['nachname'] ?? ''));
              echo '<option value="' . (int)$aid . '">' . htmlspecialchars($assigneeName) . '</option>';
          }
          ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="mobile-filter-sheet-row">
        <label for="mobile-sheet-status-select" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Status</label>
        <select id="mobile-sheet-status-select" class="mobile-filter-sheet-field">
          <option value="offen_combined">Offen</option>
          <option value="neu">Neu</option>
          <option value="in_bearbeitung">In Bearbeitung</option>
          <option value="warteschlange">Wartend</option>
          <option value="bestellung_offen">Bestellung offen</option>
          <option value="geschlossen">Geschlossen</option>
          <option value="archiv">Archiv</option>
        </select>
      </div>
    </div>
    <div id="mobileFilterSheetHandle" class="flex flex-shrink-0 cursor-grab active:cursor-grabbing touch-none bg-transparent px-4 pt-5 pb-[env(safe-area-inset-bottom,0px)]" aria-label="Zum Schließen nach oben ziehen">
      <div class="flex w-full justify-center" aria-hidden="true">
        <div class="h-1.5 w-11 shrink-0 rounded-full bg-gray-300/90 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] dark:bg-white/30 dark:shadow-[inset_0_-1px_0_rgba(0,0,0,0.25)]"></div>
      </div>
    </div>
  </div>
</div>

      </main>
  </div>

<!-- Modal: Erweiterte Filter (Stil wie Kundenauswahl, nur Desktop) -->
<div id="advancedFilterModal" class="advanced-filter-modal-root hidden fixed inset-0 z-[72] lg:z-[55] overflow-hidden" tabindex="-1" aria-hidden="true" aria-labelledby="advanced-filter-modal-title" role="dialog" aria-modal="true">
    <div id="advancedFilterModalOverlay" class="advanced-filter-modal-overlay fixed inset-0 bg-gray-900/60 dark:bg-black/70 backdrop-blur-sm transition-opacity cursor-pointer" aria-hidden="true"></div>
    <div class="advanced-filter-modal-outer fixed inset-0 flex items-center justify-center p-4 min-h-0 pointer-events-none">
        <div class="advanced-filter-modal-shell w-full max-w-3xl pointer-events-auto relative z-10 flex flex-col min-h-0">
            <div class="advanced-filter-modal-panel relative rounded-2xl shadow-xl border border-gray-200/80 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden flex flex-col min-h-0 max-h-[calc(100vh-2rem)] w-full">
                <div class="flex items-center justify-between gap-3 px-6 py-6 shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-primary-200 dark:text-primary-400">
                            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/>
                            </svg>
                        </span>
                        <h3 class="text-base font-semibold leading-none text-gray-900 dark:text-primary-200" id="advanced-filter-modal-title">Erweiterte Filter</h3>
                    </div>
                    <button type="button" id="advancedFilterModalCloseBtn" class="flex-shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex flex-col flex-1 min-h-0 px-6 pb-5">
                    <div class="shrink-0 rounded-xl border border-gray-200 bg-white dark:border-primary-120 dark:bg-primary-300/30 overflow-hidden mb-4">
                        <div class="border-b border-gray-200 bg-gray-50/95 px-4 py-2 dark:border-primary-120 dark:bg-primary-200/35">
                            <p class="text-xs font-semibold leading-4 text-gray-700 dark:text-primary-200">Vorschau</p>
                        </div>
                        <pre id="advancedFilterSqlPreview" class="advanced-filter-modal-preview text-xs font-mono text-gray-800 dark:text-primary-210 px-4 py-3 overflow-x-auto whitespace-pre-wrap break-words m-0">-- Keine Bedingungen</pre>
                    </div>
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-primary-120 dark:bg-primary-300/30 min-h-[10rem] max-h-[min(52vh,24rem)]">
                        <div class="border-b border-gray-200 bg-gray-50/95 px-4 py-2.5 dark:border-primary-120 dark:bg-primary-200/35 shrink-0">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold leading-4 text-gray-700 dark:text-primary-200">Bedingung aufbauen</p>
                                <button type="button" id="advancedFilterClearAllBtn" class="shrink-0 text-xs font-medium text-gray-500 dark:text-primary-240 hover:text-gray-700 dark:hover:text-primary-200 rounded-lg px-2 py-1 hover:bg-gray-100/80 dark:hover:bg-primary-140 transition-colors">Zurücksetzen</button>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-primary-240 mt-0.5">Ein Klick auf eine Kachel legt eine Regel an</p>
                        </div>
                        <div id="advFilterQuickAdd" class="adv-filter-quick-add-grid shrink-0 px-3 py-3 border-b border-gray-100 dark:border-primary-120/80">
                            <!-- Schnell hinzufügen per JS -->
                        </div>
                        <div id="advancedFilterRulesContainer" class="advanced-filter-modal-scroll flex-1 min-h-0 overflow-y-auto overflow-x-hidden custom-scrollbar px-3 py-3 space-y-0">
                            <!-- Regeln per JS -->
                        </div>
                    </div>
                    <div id="advancedFilterAddRuleFooter" class="flex flex-wrap items-center gap-2 pt-3 mt-3 shrink-0 hidden">
                        <button type="button" id="advancedFilterAddRuleBtn" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-600 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Weitere Bedingung
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2 pt-4 shrink-0">
                        <button type="button" id="advancedFilterCancelBtn" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-xl hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">Abbrechen</button>
                        <button type="button" id="advancedFilterApplyBtn" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-250 dark:bg-primary-280 hover:bg-primary-260 dark:hover:bg-primary-270 rounded-xl transition-colors">Filter anwenden</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
body.advanced-filter-modal-open { overflow: hidden; }
.advanced-filter-modal-field:focus { outline: none; }
.advanced-filter-modal-scroll { scrollbar-width: thin; scrollbar-color: rgba(148, 163, 184, 0.5) transparent; overscroll-behavior: contain; }
.dark .advanced-filter-modal-scroll { scrollbar-color: rgba(100, 116, 139, 0.5) transparent; }
.advanced-filter-modal-scroll::-webkit-scrollbar { width: 6px; }
.advanced-filter-modal-scroll::-webkit-scrollbar-track { background: transparent; }
.advanced-filter-modal-scroll::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 3px; }
.dark .advanced-filter-modal-scroll::-webkit-scrollbar-thumb { background: rgba(100, 116, 139, 0.5); }
.advanced-filter-modal-preview { background: transparent; border: 0; }
.adv-filter-suggestions-portal { overscroll-behavior: contain; }
.adv-filter-suggestions-portal .adv-filter-suggestion { line-height: 1.35; }
.adv-filter-quick-add-grid {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 0.5rem;
    overflow-x: auto;
    overflow-y: hidden;
    overscroll-behavior-x: contain;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}
.adv-filter-quick-chip {
    flex: 1 1 0;
    min-width: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 0.4rem; min-height: 4.25rem; padding: 0.625rem 0.35rem;
    border-radius: 0.75rem; border: 1px solid rgb(229 231 235);
    background: linear-gradient(180deg, rgb(255 255 255) 0%, rgb(249 250 251) 100%);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    color: rgb(55 65 81); text-align: center; cursor: pointer;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s, transform 0.15s, color 0.15s;
}
.adv-filter-quick-chip:hover {
    border-color: rgb(165 180 252);
    background: linear-gradient(180deg, rgb(238 242 255) 0%, rgb(224 231 255) 100%);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.14);
    transform: translateY(-1px);
    color: rgb(67 56 202);
}
.adv-filter-quick-chip:focus-visible {
    outline: 2px solid rgb(99 102 241); outline-offset: 2px;
}
.adv-filter-quick-chip-icon {
    display: flex; align-items: center; justify-content: center;
    width: 2rem; height: 2rem; border-radius: 0.625rem;
    background: rgb(238 242 255); color: rgb(79 70 229);
    transition: background-color 0.2s, color 0.2s;
}
.adv-filter-quick-chip:hover .adv-filter-quick-chip-icon {
    background: rgb(79 70 229); color: white;
}
.adv-filter-quick-chip-label {
    font-size: 0.6875rem; font-weight: 600; line-height: 1.2;
    letter-spacing: 0.01em;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dark .adv-filter-quick-chip {
    border-color: rgb(58 61 66);
    background: linear-gradient(180deg, rgb(45 46 50) 0%, rgb(35 36 40) 100%);
    color: rgb(209 213 219);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}
.dark .adv-filter-quick-chip:hover {
    border-color: rgb(99 102 241);
    background: linear-gradient(180deg, rgb(49 46 129 / 0.45) 0%, rgb(41 42 46) 100%);
    color: rgb(199 210 254);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
}
.dark .adv-filter-quick-chip-icon {
    background: rgb(49 46 129 / 0.5); color: rgb(165 180 252);
}
.dark .adv-filter-quick-chip:hover .adv-filter-quick-chip-icon {
    background: rgb(79 70 229); color: white;
}
.adv-filter-connector { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0 0.15rem; }
.adv-filter-connector-line { flex: 1; height: 1px; background: rgb(229 231 235); }
.dark .adv-filter-connector-line { background: rgb(58 61 66); }
.adv-filter-join-toggle {
    padding: 0.25rem 0.65rem; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.04em;
    text-transform: uppercase; border-radius: 0.5rem; color: rgb(107 114 128);
    transition: background-color 0.15s, color 0.15s;
}
.adv-filter-join-toggle:hover { color: rgb(55 65 81); background: rgb(243 244 246); }
.adv-filter-join-toggle--active {
    background: rgb(79 70 229); color: white;
}
.dark .adv-filter-join-toggle { color: rgb(156 163 175); }
.dark .adv-filter-join-toggle:hover { background: rgb(50 52 56); color: rgb(229 231 235); }
.dark .adv-filter-join-toggle--active { background: rgb(79 70 229); color: white; }
.adv-filter-rule-card {
    border-radius: 0.75rem; border: 1px solid rgb(229 231 235);
    background: rgb(249 250 251); padding: 0.875rem 1rem;
}
.dark .adv-filter-rule-card {
    border-color: rgb(58 61 66); background: rgb(35 36 40 / 0.65);
}
.adv-filter-rule-label { font-size: 0.65rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.05em; color: rgb(107 114 128); }
.dark .adv-filter-rule-label { color: rgb(156 163 175); }
.adv-filter-op-segment { max-width: 100%; }
.adv-filter-op-segment-track {
    --adv-op-gap: 5px;
    position: relative; display: inline-flex; align-items: stretch; max-width: 100%;
    padding: var(--adv-op-gap);
    border-radius: 9999px;
    border: 1px solid rgb(209 213 219); background: #fff;
    touch-action: none; user-select: none; -webkit-user-select: none; cursor: grab;
    box-sizing: border-box;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
}
.adv-filter-op-segment-track:active { cursor: grabbing; }
.dark .adv-filter-op-segment-track {
    border-color: rgb(58 61 66); background: rgb(41 42 46);
}
.adv-filter-op-segment-thumb {
    position: absolute;
    top: var(--adv-op-gap);
    left: 0;
    height: calc(100% - 2 * var(--adv-op-gap));
    z-index: 0;
    margin: 0; border-radius: 9999px; background: #ede9fe; pointer-events: none;
    box-shadow: inset 0 0 0 1px rgb(124 58 237 / 0.12);
    box-sizing: border-box;
    transition: left 0.22s cubic-bezier(0.32, 0.72, 0, 1), width 0.22s cubic-bezier(0.32, 0.72, 0, 1);
    will-change: left, width;
}
.dark .adv-filter-op-segment-thumb {
    background: rgb(79 70 229);
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.25);
}
.adv-filter-op-segment-item {
    position: relative; z-index: 1; flex: 0 0 auto; align-self: stretch;
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0 0.7rem; line-height: 1.375rem; font-size: 0.8125rem; font-weight: 600;
    border: none; background: transparent; color: rgb(107 114 128);
    white-space: nowrap; border-radius: 9999px; cursor: inherit;
    transition: color 0.15s ease;
}
.adv-filter-op-segment-item[aria-selected="true"] { color: #5b21b6; font-weight: 700; }
.dark .adv-filter-op-segment-item { color: rgb(156 163 175); }
.dark .adv-filter-op-segment-item[aria-selected="true"] { color: #fff; font-weight: 700; }
.adv-filter-chip {
    padding: 0.35rem 0.7rem; font-size: 0.75rem; font-weight: 500; border-radius: 9999px;
    border: 1px solid rgb(229 231 235); background: white; color: rgb(55 65 81);
    transition: all 0.15s;
}
.adv-filter-chip:hover { border-color: rgb(165 180 252); background: rgb(238 242 255); }
.adv-filter-chip--active {
    border-color: rgb(79 70 229); background: rgb(79 70 229); color: white;
}
.dark .adv-filter-chip { border-color: rgb(58 61 66); background: rgb(41 42 46); color: rgb(209 213 219); }
.dark .adv-filter-chip:hover { border-color: rgb(99 102 241); background: rgb(49 46 129 / 0.35); }
.dark .adv-filter-chip--active { border-color: rgb(99 102 241); background: rgb(79 70 229); color: white; }
.adv-filter-op-extra--active { color: rgb(79 70 229) !important; font-weight: 700; text-decoration: underline; }
.dark .adv-filter-op-extra--active { color: rgb(165 180 252) !important; }
.adv-filter-field-op-row {
    align-items: stretch;
    gap: 0.5rem;
}
.adv-filter-field-dropdown { min-width: 9.5rem; }
.adv-filter-field-btn .filter-btn-label {
    flex: 1 1 auto; min-width: 0; text-align: left;
}
.adv-filter-field-op-row .adv-filter-op-group {
    flex: 1 1 auto; padding: 0; border: none; background: transparent;
    display: flex; align-items: center;
}
.dark .adv-filter-field-op-row .adv-filter-op-group {
    border: none; background: transparent;
}
.adv-filter-op-select {
    font-size: 0.8125rem; font-weight: 600; border-radius: 0.5rem;
    border: 1px solid rgb(209 213 219); background: white;
    padding: 0.4rem 1.75rem 0.4rem 0.65rem; color: rgb(17 24 39);
}
.dark .adv-filter-op-select {
    border-color: rgb(58 61 66); background: rgb(41 42 46); color: rgb(243 244 246);
}
.adv-filter-value-input {
    width: 100%; font-size: 0.8125rem; border-radius: 0.625rem;
    border: 1px solid rgb(209 213 219); background: white;
    padding: 0.5rem 0.75rem; color: rgb(17 24 39);
}
.dark .adv-filter-value-input {
    border-color: rgb(58 61 66); background: rgb(41 42 46); color: rgb(243 244 246);
}
.adv-filter-value-input:focus { outline: none; box-shadow: 0 0 0 2px rgb(99 102 241 / 0.35); border-color: rgb(99 102 241); }
</style>

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
                    <input type="text" id="order-consumables-search" placeholder="Suchen oder Bezeichnung eingeben" class="w-full mb-3 px-3 py-2 text-sm border border-gray-300 dark:border-primary-320 rounded-base bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250" autocomplete="off">
                    <div id="order-consumables-list" class="max-h-64 overflow-y-auto custom-scrollbar rounded-base border border-gray-200 dark:border-primary-120 divide-y divide-gray-200 dark:divide-primary-120 bg-gray-50 dark:bg-primary-50/40">
                        <div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Lade Verbrauchsmaterialien...</div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2 p-5 border-t border-gray-200 dark:border-primary-120">
                    <button type="button" onclick="manualOrderEntry()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-200 dark:border-primary-320 rounded-base hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">Manuell eintragen</button>
                    <button type="button" id="order-consumables-apply-btn" onclick="applyOrderConsumables()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-250 dark:bg-primary-280 rounded-base hover:bg-primary-260 dark:hover:bg-primary-270 transition-colors">Bestellen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Kundenauswahl (Stil wie Ordner-Modal bei Todos) -->
<?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') && !empty($customers)): ?>
<div id="customerModal" class="customer-modal-root hidden fixed inset-0 z-[72] lg:z-50 overflow-hidden" role="dialog" aria-modal="true" aria-label="Kunde auswählen">
    <div id="customerModalOverlay" class="customer-modal-overlay fixed left-0 right-0 bottom-0 top-[calc(env(safe-area-inset-top,0px)+3.5rem)] lg:top-0 max-lg:bg-black/[0.05] max-lg:dark:bg-black/22 max-lg:dark:backdrop-blur-[3px] lg:bg-gray-900/60 lg:dark:bg-black/70 lg:backdrop-blur-sm transition-opacity cursor-pointer" aria-hidden="true"></div>
    <div class="customer-modal-outer fixed left-0 right-0 bottom-0 top-[calc(env(safe-area-inset-top,0px)+3.5rem)] lg:inset-0 flex flex-col lg:items-center lg:justify-center p-0 lg:p-4 min-h-0 pointer-events-none">
        <div class="customer-modal-shell w-full lg:max-w-2xl pointer-events-auto relative z-10 flex flex-col min-h-0">
            <div class="customer-modal-panel relative max-lg:rounded-b-[1.75rem] max-lg:border max-lg:border-t-0 max-lg:border-gray-200 max-lg:bg-white/88 max-lg:backdrop-blur-2xl max-lg:backdrop-saturate-200 max-lg:dark:border-0 max-lg:dark:bg-transparent bg-white dark:bg-primary-100 lg:rounded-2xl shadow-none lg:shadow-xl border-0 lg:border lg:border-gray-200/80 dark:lg:border-primary-120 overflow-hidden flex flex-col min-h-0 max-h-[min(92vh,34rem)] lg:max-h-[calc(100vh-2rem)] w-full">
                <div id="customerModalSheetScroll" class="relative flex-1 min-h-0 flex flex-col overflow-hidden lg:min-h-0">
                <div id="customerModalSheetInnerScroll" class="flex-1 min-h-0 flex flex-col overflow-x-hidden custom-scrollbar max-lg:overflow-hidden lg:overflow-visible lg:min-h-0">
                <!-- Header nur Desktop (Mobil: kompakt wie Filter-Sheet) -->
                <div class="hidden lg:flex items-center justify-between gap-3 px-6 pt-6 pb-0 shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-primary-200 dark:text-primary-400 max-lg:bg-gray-100 max-lg:dark:bg-neutral-900">
                            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
</svg>

                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold leading-tight text-gray-900 dark:text-primary-200">Kunde auswählen</h3>
                        </div>
                    </div>
                    <button type="button" id="closeCustomerModalBtn" class="flex-shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex flex-col flex-1 min-h-0 px-4 pb-2 pt-4 sm:px-5 lg:px-6 lg:pt-6 lg:pb-5 lg:flex-1">
                    <input type="text" id="customerSearchInput" placeholder="Kunde suchen…"
                           class="customer-modal-input w-full px-4 py-2 rounded-xl max-lg:min-h-[3.25rem] max-lg:rounded-[1.25rem] max-lg:border max-lg:border-gray-200 max-lg:bg-[#f6f6f6] max-lg:px-[1.125rem] max-lg:py-3 max-lg:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.65),0_1px_2px_rgba(15,23,42,0.04),0_4px_14px_rgba(15,23,42,0.05)] max-lg:dark:border-0 max-lg:dark:bg-[#161616] max-lg:dark:text-neutral-200 max-lg:dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.04),0_2px_10px_rgba(0,0,0,0.5)] max-lg:focus:ring-0 max-lg:focus:ring-offset-0 max-lg:dark:focus:ring-0 lg:border lg:border-gray-300 dark:lg:border-primary-320 lg:bg-gray-50 dark:lg:bg-primary-300/50 text-gray-900 dark:text-primary-200 placeholder-gray-400 dark:placeholder-primary-250 max-lg:dark:placeholder-neutral-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 lg:focus:border-primary-500 dark:focus:ring-primary-400/30 dark:lg:focus:border-primary-400 transition-colors mb-3 lg:mb-4 shrink-0">
                    <div class="customer-modal-list-viewport relative min-h-0 flex-1 flex flex-col max-lg:overflow-hidden max-lg:rounded-[1.25rem]">
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-primary-120 dark:bg-primary-300/30 max-lg:rounded-[1.25rem] lg:bg-white">
                        <div class="customer-modal-col-header grid shrink-0 grid-cols-[minmax(0,1fr)_minmax(0,45%)] items-start gap-x-3 border-b border-gray-200 bg-gray-50/95 px-4 py-2 dark:border-primary-120 dark:bg-primary-200/35 sm:grid-cols-[minmax(0,1fr)_minmax(0,40%)]">
                            <div class="min-w-0 text-left text-xs font-semibold leading-4 text-gray-700 dark:text-primary-200">Kunde</div>
                            <div class="customer-modal-address-col min-w-0 text-left text-xs font-semibold leading-4 text-gray-700 dark:text-primary-200">Adresse</div>
                        </div>
                        <div class="relative flex min-h-0 flex-1 flex-col">
                            <div class="customer-modal-list-top-fade pointer-events-none absolute inset-x-0 top-0 z-[2] h-11 max-lg:h-14 opacity-0 transition-opacity duration-200" aria-hidden="true"></div>
                            <div id="customersTableBody" class="customer-modal-scroll min-h-0 flex-1 overflow-y-auto overflow-x-hidden divide-y divide-gray-100 dark:divide-primary-200 dark:max-lg:divide-white/10 lg:max-h-[min(50vh,24rem)] lg:min-h-0">
                        <div class="customer-row select-customer-row px-4 py-3 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-primary-140" data-customer-id="" data-customer-name="" data-customer-display-name="Alle Kunden">
                            <div class="grid w-full grid-cols-[minmax(0,1fr)_minmax(0,45%)] items-start gap-x-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,40%)]">
                                <span class="min-w-0 text-sm font-medium leading-5 text-gray-900 dark:text-primary-200">Alle Kunden</span>
                                <div class="customer-modal-address-col min-w-0" aria-hidden="true"></div>
                            </div>
                        </div>
                        <?php foreach ($customers as $customer): ?>
                        <?php
                            $addrStr = isset($customer['adresse']) ? trim((string)$customer['adresse']) : '';
                            $plzOrt = trim(implode(' ', array_filter([$customer['plz'] ?? '', $customer['ort'] ?? ''])));
                            $hasAddr = $addrStr !== '' || $plzOrt !== '';
                            $searchBlob = strtolower(trim(
                                ($customer['name'] ?? '') . ' ' .
                                ($customer['kundennummer'] ?? '') . ' ' .
                                ($customer['company_name'] ?? '') . ' ' .
                                $addrStr . ' ' .
                                ($customer['plz'] ?? '') . ' ' .
                                ($customer['ort'] ?? '')
                            ));
                        ?>
                        <div class="customer-row select-customer-row px-4 py-3 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-primary-140"
                             data-customer-id="<?= (int)$customer['id'] ?>"
                             data-customer-name="<?= htmlspecialchars($searchBlob) ?>"
                             data-customer-display-name="<?= htmlspecialchars($customer['name']) ?>"
                             data-customer-kundennummer="<?= htmlspecialchars($customer['kundennummer'] ?? '') ?>"
                             data-customer-company-name="<?= htmlspecialchars($customer['company_name'] ?? '') ?>"
                             data-company-id="<?= $customer['company_id'] ? (int)$customer['company_id'] : '' ?>">
                            <div class="grid w-full grid-cols-[minmax(0,1fr)_minmax(0,45%)] items-start gap-x-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,40%)]">
                                <div class="min-w-0">
                                    <span class="block truncate text-sm font-medium leading-5 text-gray-900 dark:text-primary-200"><?= htmlspecialchars($customer['name']) ?></span>
                                    <?php
                                    $showCompanyRow = ($userRole === 'Admin' || $userRole === 'Techniker') && !empty($customer['company_name']);
                                    $hasKdnrRow = !empty($customer['kundennummer']);
                                    ?>
                                    <?php if ($showCompanyRow || $hasKdnrRow): ?>
                                    <div class="mt-0.5 flex min-w-0 flex-wrap items-baseline gap-x-2 text-xs text-gray-500 dark:text-primary-210">
                                        <?php if ($showCompanyRow): ?>
                                        <span class="customer-modal-company-badge min-w-0 truncate"><?= htmlspecialchars($customer['company_name']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($showCompanyRow && $hasKdnrRow): ?>
                                        <span class="customer-modal-company-badge shrink-0 text-gray-300 dark:text-primary-320" aria-hidden="true">·</span>
                                        <?php endif; ?>
                                        <?php if ($hasKdnrRow): ?>
                                        <span class="tabular-nums"><?= htmlspecialchars($customer['kundennummer']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="customer-modal-address-col min-w-0 text-left text-xs font-normal leading-5 text-gray-500 dark:text-primary-210">
                                    <?php if ($hasAddr): ?>
                                        <?php if ($addrStr !== ''): ?><div class="break-words"><?= htmlspecialchars($addrStr) ?></div><?php endif; ?>
                                        <?php if ($plzOrt !== ''): ?><div class="mt-0.5"><?= htmlspecialchars($plzOrt) ?></div><?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 dark:text-primary-240">–</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                            </div>
                            <div class="customer-modal-list-bottom-fade pointer-events-none absolute inset-x-0 bottom-0 z-[2] h-11 max-lg:h-14 opacity-0 transition-opacity duration-200" aria-hidden="true"></div>
                        </div>
                    </div>
                    </div>
                </div>
                </div>
                </div>
                <div id="customerModalSheetHandle" class="flex lg:hidden flex-shrink-0 cursor-grab active:cursor-grabbing touch-none bg-transparent px-4 pt-5 pb-[env(safe-area-inset-bottom,0px)]" aria-label="Zum Schließen nach oben ziehen">
                    <div class="flex w-full justify-center" aria-hidden="true">
                        <div class="h-1.5 w-11 shrink-0 rounded-full bg-gray-300/90 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] dark:bg-white/30 dark:shadow-[inset_0_-1px_0_rgba(0,0,0,0.25)]"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.customer-modal-input:focus { outline: none; }
.customer-modal-scroll { scrollbar-width: thin; scrollbar-color: rgba(148, 163, 184, 0.5) transparent; overscroll-behavior: contain; }
body.customer-modal-open { overflow: hidden; }
.dark .customer-modal-scroll { scrollbar-color: rgba(100, 116, 139, 0.5) transparent; }
.customer-modal-scroll::-webkit-scrollbar { width: 6px; }
.customer-modal-scroll::-webkit-scrollbar-track { background: transparent; }
.customer-modal-scroll::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 3px; }
.dark .customer-modal-scroll::-webkit-scrollbar-thumb { background: rgba(100, 116, 139, 0.5); }
/* Kundenlisten-Fade: Farbe an Listen-/Sheet-Hintergrund (--page-bg aus head.php, Theme primary) */
#customerModal {
  /* Desktop: Liste lg:bg-white */
  --cm-list-fade-surface: #ffffff;
}
.dark #customerModal {
  /* Desktop: dark:lg:bg-primary-300/30 über Karte primary-100 → color-mix(#020617 30%, #101011) */
  --cm-list-fade-surface: color-mix(in srgb, #020617 30%, #101011);
}
@media (max-width: 1023px) {
  html:not(.dark) #customerModal {
    /* Mobil: Sheet max-lg:bg-white/88, leicht an Seitenhintergrund (--page-bg) */
    --cm-list-fade-surface: color-mix(in srgb, #ffffff 88%, var(--page-bg, #f7fafc) 12%);
  }
  .dark #customerModal {
    /* Mobil: zwischen „zu dunkel“ (Glas) und „zu hell“ — primary-100 + Seitengrund */
    --cm-list-fade-surface: color-mix(in srgb, #101011 58%, var(--page-bg, #090909) 42%);
  }
}
/* Schatten oben/unten im Kundenlisten-Feld, sichtbar nur per JS wenn scrollbar */
.customer-modal-list-top-fade {
  background: linear-gradient(
    to bottom,
    color-mix(in srgb, var(--cm-list-fade-surface, #fff) 88%, transparent) 0%,
    transparent 70%
  );
  box-shadow: 0 8px 20px -12px color-mix(in srgb, var(--cm-list-fade-surface, #fff) 14%, transparent);
}
.customer-modal-list-bottom-fade {
  background: linear-gradient(
    to top,
    color-mix(in srgb, var(--cm-list-fade-surface, #fff) 88%, transparent) 0%,
    transparent 70%
  );
  box-shadow: 0 -8px 20px -12px color-mix(in srgb, var(--cm-list-fade-surface, #fff) 14%, transparent);
}
.customer-modal-list-top-fade.customer-modal-scroll-fade-visible,
.customer-modal-list-bottom-fade.customer-modal-scroll-fade-visible {
  opacity: 1;
}
.dark .customer-modal-list-top-fade {
  box-shadow: 0 8px 22px -12px color-mix(in srgb, var(--page-bg, #090909) 18%, transparent);
}
.dark .customer-modal-list-bottom-fade {
  box-shadow: 0 -8px 22px -12px color-mix(in srgb, var(--page-bg, #090909) 18%, transparent);
}
@media (max-width: 1023px) {
  .customer-modal-list-top-fade {
    background: linear-gradient(
      to bottom,
      color-mix(in srgb, var(--cm-list-fade-surface, #fff) 90%, transparent) 0%,
      color-mix(in srgb, var(--cm-list-fade-surface, #fff) 32%, transparent) 44%,
      transparent 100%
    );
    box-shadow: 0 10px 24px -14px color-mix(in srgb, var(--cm-list-fade-surface, #fff) 12%, transparent);
  }
  .customer-modal-list-bottom-fade {
    background: linear-gradient(
      to top,
      color-mix(in srgb, var(--cm-list-fade-surface, #fff) 90%, transparent) 0%,
      color-mix(in srgb, var(--cm-list-fade-surface, #fff) 32%, transparent) 44%,
      transparent 100%
    );
    box-shadow: 0 -10px 24px -14px color-mix(in srgb, var(--cm-list-fade-surface, #fff) 12%, transparent);
  }
  .dark .customer-modal-list-top-fade {
    box-shadow: 0 10px 26px -14px color-mix(in srgb, var(--page-bg, #090909) 14%, transparent);
  }
  .dark .customer-modal-list-bottom-fade {
    box-shadow: 0 -10px 26px -14px color-mix(in srgb, var(--page-bg, #090909) 14%, transparent);
  }
}
/* Mobil: Kundenauswahl 1:1 wie #mobileFilterSheetPanel / Griff / Overlay */
@media (max-width: 1023px) {
  .customer-modal-panel {
    transform: translateY(-100%);
    transition: transform 0.32s cubic-bezier(0.32, 0.72, 0, 1);
    will-change: transform;
    border-left-color: rgb(209 213 219);
    border-right-color: rgb(209 213 219);
    border-bottom-color: rgb(203 213 225);
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255),
      inset 0 3px 14px -3px rgb(255 255 255 / 0.55),
      inset 0 -1px 0 0 rgb(15 23 42 / 0.07),
      0 4px 24px rgb(15 23 42 / 0.09),
      0 1px 0 0 rgb(15 23 42 / 0.1);
  }
  #customerModal.customer-modal-visible .customer-modal-panel {
    transform: translateY(0);
  }
  /* Light mobil: gleicher Frosted-Glas-Look wie #mobileFilterSheetPanel */
  html:not(.dark) #customerModal .customer-modal-panel {
    background-color: rgb(255 255 255 / 0.88) !important;
    -webkit-backdrop-filter: blur(40px) saturate(2);
    backdrop-filter: blur(40px) saturate(2);
  }
  .dark #customerModal .customer-modal-panel {
    background-color: rgb(5 5 5 / 0.48) !important;
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.26),
      inset 0 3px 16px -3px rgb(255 255 255 / 0.1),
      inset 0 -1px 0 0 rgb(0 0 0 / 0.55),
      0 4px 30px rgb(0 0 0 / 0.52),
      0 18px 44px rgb(0 0 0 / 0.35);
  }
  .dark #customerSearchInput:focus {
    border-color: rgb(255 255 255 / 0.1);
    box-shadow:
      0 0 0 1px rgb(255 255 255 / 0.1),
      inset 0 1px 0 0 rgb(255 255 255 / 0.04),
      0 2px 14px rgb(0 0 0 / 0.55);
  }
  html:not(.dark) #customerSearchInput:focus {
    border-color: rgb(191 219 254);
    box-shadow:
      0 0 0 2px rgb(59 130 246 / 0.22),
      inset 0 1px 0 0 rgb(255 255 255 / 0.65),
      0 1px 2px rgb(15 23 42 / 0.04),
      0 4px 14px rgb(15 23 42 / 0.05);
  }
}
@media (min-width: 1024px) {
  .customer-modal-panel {
    transform: none !important;
    transition: none !important;
    will-change: auto;
  }
}
</style>
<?php endif; ?>
<style>
/* Chat-Ansicht (nur Desktop): Nur Chat-Liste und Chat-Nachrichten scrollen, ganze Seite nicht. */
@media (min-width: 1024px) {
    /* Seitenscroll komplett sperren – html + body */
    html.service-chat-view-active {
        overflow: hidden !important;
        height: 100% !important;
    }
    body.service-chat-view-active {
        overflow: hidden !important;
        height: 100vh !important;
        max-height: 100vh !important;
    }
    body.service-chat-view-active #main-content.service-main-content {
        height: 100vh;
        max-height: 100vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    body.service-chat-view-active #main-content.service-main-content main.service-main {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    body.service-chat-view-active .service-content-outer {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    body.service-chat-view-active .service-views-wrap {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    /* Toolbar (Filter, Ansicht-Umschalter, Suche) nicht schrumpfen */
    body.service-chat-view-active .service-toolbar-wrap {
        flex-shrink: 0;
    }
    body.service-chat-view-active #chatView.service-chat-view-panel {
        flex: 1;
        min-height: 0;
        height: auto !important;
        margin-bottom: 5rem;
    }
}

/* Chat-Bereich: gleicher Look wie view.php */
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
#chatView #chatTicketHeader {
    flex-shrink: 0;
}
#chatView #chatTicketHeader span.rounded-full {
    flex-shrink: 0;
    white-space: nowrap;
    overflow: visible;
}
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
.service-chat-messages .chat-row-sent {
    margin-right: -0.75rem;
}

/* Aktive Filter: gleiche Farben wie Input-Feld wenn aktiv (White-Mode) */
html:not(.dark) #customer-filter-button.customer-filter-btn--active,
html:not(.dark) #assignee-filter-button.assignee-filter-btn--active,
html:not(.dark) #status-filter-button.status-filter-btn--active,
html:not(.dark) #advancedFilterBtn.advanced-filter-btn--active {
    background-color: rgba(79, 70, 229, 0.12);
    border-color: #4f46e5;
    color: #312e81;
    font-weight: 700;
    box-shadow: none;
}
html:not(.dark) #customer-filter-button.customer-filter-btn--active .filter-btn-icon,
html:not(.dark) #customer-filter-button.customer-filter-btn--active .filter-btn-chevron,
html:not(.dark) #assignee-filter-button.assignee-filter-btn--active .filter-btn-icon,
html:not(.dark) #assignee-filter-button.assignee-filter-btn--active .filter-btn-chevron,
html:not(.dark) #status-filter-button.status-filter-btn--active .filter-btn-icon,
html:not(.dark) #status-filter-button.status-filter-btn--active .filter-btn-chevron,
html:not(.dark) #advancedFilterBtn.advanced-filter-btn--active .filter-btn-icon,
html:not(.dark) #advancedFilterBtn.advanced-filter-btn--active .filter-btn-label {
    color: #1e293b;
}
.dark #customer-filter-button.customer-filter-btn--active,
.dark #assignee-filter-button.assignee-filter-btn--active,
.dark #status-filter-button.status-filter-btn--active,
.dark #advancedFilterBtn.advanced-filter-btn--active {
    background-color: #312e81;
    border-color: #4f46e5;
    color: #e5e7eb;
    font-weight: 700;
    box-shadow: none;
}
.dark #customer-filter-button.customer-filter-btn--active .filter-btn-icon,
.dark #customer-filter-button.customer-filter-btn--active .filter-btn-chevron,
.dark #assignee-filter-button.assignee-filter-btn--active .filter-btn-icon,
.dark #assignee-filter-button.assignee-filter-btn--active .filter-btn-chevron,
.dark #status-filter-button.status-filter-btn--active .filter-btn-icon,
.dark #status-filter-button.status-filter-btn--active .filter-btn-chevron,
.dark #advancedFilterBtn.advanced-filter-btn--active .filter-btn-icon,
.dark #advancedFilterBtn.advanced-filter-btn--active .filter-btn-label {
    color: #d1d5db;
}

/* Inaktive Filter-Buttons im Dark-Mode: gleiche Hintergrundfarbe wie Dropdown-Menü */
.dark #customer-filter-button.filter-btn--default,
.dark #assignee-filter-button.filter-btn--default,
.dark #status-filter-button.filter-btn--default,
.dark #display-dropdown-button.display-dropdown-btn.filter-btn--default,
.dark #advancedFilterBtn.advanced-filter-btn.filter-btn--default {
    background-color: #292a2d !important;
    border-color: #3a3d42 !important;
}
.dark #customer-filter-button.filter-btn--default:hover,
.dark #assignee-filter-button.filter-btn--default:hover,
.dark #status-filter-button.filter-btn--default:hover,
.dark #display-dropdown-button.display-dropdown-btn.filter-btn--default:hover,
.dark #advancedFilterBtn.advanced-filter-btn.filter-btn--default:hover {
    background-color: #323438 !important;
    border-color: #4a4d52 !important;
}
/* Tickets: Schrift im Dark-Mode etwas weicher statt grellweiß */
.dark .status-filter-btn.filter-btn--default .filter-btn-label,
.dark .customer-filter-btn.filter-btn--default .filter-btn-label,
.dark .assignee-filter-btn.filter-btn--default .filter-btn-label,
.dark #display-dropdown-button .filter-btn-label {
    color: #d1d5db !important;
}
.dark .status-filter-btn.filter-btn--default .filter-btn-icon,
.dark .customer-filter-btn.filter-btn--default .filter-btn-icon,
.dark .assignee-filter-btn.filter-btn--default .filter-btn-icon,
.dark #display-dropdown-button .filter-btn-icon,
.dark #advancedFilterBtn .filter-btn-icon,
.dark #advancedFilterBtn .filter-btn-label {
    color: #aeb4bd !important;
}
.dark #ticketsList .text-gray-900.dark\:text-white {
    color: #d1d5db !important;
}

/* Ticket-Nummer unter Listen-Titel: für IT sichtbar, beim Scannen zurückhaltend */
.ticket-nummer-meta {
    font-size: 0.625rem;
    font-weight: 400;
    line-height: 1.25;
    color: #888888;
}
.dark .ticket-nummer-meta {
    color: #9a9a9a;
}

/* Tickets: Skeletons im Dark-Mode an Tabellenpalette angleichen */
.dark .ticket-skeleton-mobile .dark\:bg-primary-140,
.dark .ticket-skeleton-card .dark\:bg-primary-140,
.dark .ticket-skeleton-row .dark\:bg-primary-140,
.dark .ticket-skeleton-chat-list .dark\:bg-primary-140 {
    background-color: #3a3d42 !important;
}
.dark .ticket-skeleton-mobile .dark\:bg-primary-120,
.dark .ticket-skeleton-card .dark\:bg-primary-120,
.dark .ticket-skeleton-row .dark\:bg-primary-120,
.dark .ticket-skeleton-chat-list .dark\:bg-primary-120 {
    background-color: #323438 !important;
}

/* Filter-Dropdowns: Schatten wie globaler Firmenfilter (nav #dropdownUserName) */
.service-filter-dropdown-shadow {
    box-shadow: 0 10px 24px -4px rgb(0 0 0 / 0.12), 0 4px 8px -2px rgb(0 0 0 / 0.06);
}
.dark .service-filter-dropdown-shadow {
    box-shadow: 0 10px 24px -4px rgb(0 0 0 / 0.45), 0 4px 8px -2px rgb(0 0 0 / 0.25);
}

/* Filter-Dropdowns: gleiche Hoverfarbe wie Tabellen-Hover */
.dark #status-filter-menu .status-option:hover,
.dark #assignee-filter-menu .assignee-option:hover,
.dark #sort-dropdown-menu .sort-option:hover,
.dark #display-dropdown-menu .sort-option:hover,
.dark #customer-filter-menu .customer-option:hover {
    background-color: #323438 !important;
}

/* Anzeige-Dropdown: aktive Ansicht im Segment */
.display-view-option--active {
    background-color: #fff;
    color: #111827;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.dark .display-view-option--active {
    background-color: #292a2d;
    color: #f3f4f6;
    border-color: #4a4d52;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}
.display-view-option--active svg,
.display-sort-dir-option.display-view-option--active {
    color: inherit;
}
.display-sort-dir-option:disabled {
    pointer-events: none;
}

/* Rechtsklick-Menue (Kontextmenue): gleiche Hoverfarbe wie Tabelle */
.dark #ticketContextMenu button:hover,
.dark #ticketCtxGoToSubmenu button:hover,
.dark #ticketCtxStatusSubmenu button:hover,
.dark #ticketCtxAssignSubmenu button:hover,
.dark #ticketCtxGoToTrigger:hover,
.dark #ticketCtxStatusTrigger:hover,
.dark #ticketCtxAssignTrigger:hover {
    background-color: #323438 !important;
}

/* Rechtsklick-Menue: Text- und Borderfarben an dunkle Tabelle angleichen */
.dark #ticketContextMenu,
.dark #ticketCtxGoToSubmenu,
.dark #ticketCtxStatusSubmenu,
.dark #ticketCtxAssignSubmenu {
    border-color: #3a3d42 !important;
}
.dark #ticketContextMenu button,
.dark #ticketCtxGoToSubmenu button,
.dark #ticketCtxStatusSubmenu button,
.dark #ticketCtxAssignSubmenu button,
.dark #ticketCtxGoToTrigger,
.dark #ticketCtxStatusTrigger,
.dark #ticketCtxAssignTrigger {
    color: #d1d5db !important;
}
.dark #ticketContextMenu svg,
.dark #ticketCtxGoToSubmenu svg,
.dark #ticketCtxStatusSubmenu svg,
.dark #ticketCtxAssignSubmenu svg,
.dark #ticketCtxGoToTrigger svg,
.dark #ticketCtxStatusTrigger svg,
.dark #ticketCtxAssignTrigger svg {
    color: #aeb4bd !important;
}
.dark #ticketContextMenu .border-t,
.dark #ticketContextMenu #ticketCtxRechnungBearbeitungszeitDivider {
    border-color: #3a3d42 !important;
}

/* Rechtsklick-Ziel hervorheben (wie Hover) solange Kontextmenü offen ist */
#ticketsList tr.ticket-context-active {
    background-color: rgb(243 244 246) !important;
}
.dark #ticketsList tr.ticket-context-active {
    background-color: #323438 !important;
}

/* Mobil-Ticketsuche (Nav): gleiches Verhalten wie Lager (#inv-mobile-dashboard) */
.tickets-mobile-search-anim {
    display: grid;
    grid-template-rows: 0fr;
    width: 100%;
    min-width: 0;
    transition: grid-template-rows 0.38s cubic-bezier(0.4, 0, 0.2, 1);
}
#tickets-mobile-dashboard.tickets-mobile-search-panel-open .tickets-mobile-search-anim {
    grid-template-rows: 1fr;
}
#tickets-mobile-dashboard:not(.tickets-mobile-search-panel-open) .tickets-mobile-search-anim {
    pointer-events: none;
}
#tickets-mobile-dashboard.tickets-mobile-search-panel-open .tickets-mobile-search-anim__measure {
    overflow: visible;
}
@media (prefers-reduced-motion: reduce) {
    .tickets-mobile-search-anim {
        transition-duration: 0.01ms;
    }
}

/* Suchfeld: Browser-eigenes Lösch-X (bei type="search") ausblenden – Desktop + Mobile */
#search-wrapper input#search::-webkit-search-cancel-button,
#search-wrapper input#search::-webkit-search-decoration {
    -webkit-appearance: none;
    appearance: none;
}
#search-wrapper input#search[type="search"]::-ms-clear {
    display: none;
}
/* Suchfeld: kein äußerer Schatten/Ring */
#search-wrapper input#search:focus,
#search-wrapper input#search:focus-visible {
    outline: none;
    box-shadow: none;
}
/* Fokus + Suchbegriff: blauer Rand und Hintergrund (ohne Ring/Schatten) */
html:not(.dark) #search-wrapper input#search:focus,
html:not(.dark) #search-wrapper input#search:focus-visible,
html:not(.dark) #search-wrapper.search-active input#search {
    border-color: #4f46e5;
    background-color: rgba(79, 70, 229, 0.12);
    color: #312e81;
    font-weight: 700;
    box-shadow: none;
}
html:not(.dark) #search-wrapper input#search::placeholder {
    color: #6b7280;
}
html:not(.dark) #search-wrapper input#search:focus::placeholder,
html:not(.dark) #search-wrapper.search-active input#search::placeholder {
    color: #475569;
    opacity: 0.9;
}
.dark #search-wrapper input#search:focus,
.dark #search-wrapper input#search:focus-visible,
.dark #search-wrapper.search-active input#search {
    border-color: #4f46e5;
    background-color: #312e81;
    color: #e5e7eb;
    font-weight: 700;
    box-shadow: none;
}
.dark #search-wrapper input#search:focus::placeholder,
.dark #search-wrapper.search-active input#search::placeholder {
    color: rgba(229, 231, 235, 0.8);
}

/* Suchfeld: dauerhaft sichtbar in der Toolbar */
#search-form {
    flex: 0 0 auto;
    width: 100%;
}

#search-wrapper {
    width: 100%;
}

#search-wrapper input#search {
    border-color: #e5e7eb;
}
#search-wrapper input#search:hover {
    border-color: #d1d5db;
}
#search-wrapper input#search:focus {
    border-color: rgb(59 130 246);
}

/* Filter-Buttons: Icon + Text wie „Anzeige“ (Label immer sichtbar) */
.customer-filter-btn .filter-btn-label,
.assignee-filter-btn .filter-btn-label,
.status-filter-btn .filter-btn-label,
.advanced-filter-btn .filter-btn-label {
    display: inline-block;
    max-width: 16rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.customer-filter-btn,
.assignee-filter-btn,
.status-filter-btn,
.advanced-filter-btn {
    align-items: center;
}

/* Filter-Container sollen sich natürlich anpassen */
#customer-filter-container,
#assignee-filter-container,
#status-filter-container,
#display-dropdown-container {
    flex-shrink: 0;
}

/* Responsive: Auf mobilen Geräten volle Breite */
@media (max-width: 768px) {
    /* Auf mobilen Geräten: Filter-Buttons können volle Breite nutzen wenn nötig */
    #customer-filter-container,
    #assignee-filter-container,
    #status-filter-container {
        flex: 1 1 auto;
        min-width: 0;
    }
    
    #customer-filter-button,
    #assignee-filter-button,
    #status-filter-button {
        width: 100%;
        justify-content: space-between;
    }
}
/* Mobile: Fußleisten-Abstand kommt von body.app-mobile-bottom-nav #main-content */
@media (max-width: 1023px) {
    #mobileTicketsWrap.mobile-tickets-fullheight {
        display: flex !important;
        flex-direction: column;
    }
    #mobileTicketsList {
        overflow: visible;
        min-height: 0;
    }
    #mobileTicketsWrap {
        overflow-x: hidden;
        overscroll-behavior-x: none;
    }
    /* Wisch-Aktionen (Mobil): wie Todos — Stacking + kein horizontales Seiten-Gummiband */
    .ticket-mobile-item {
        isolation: isolate;
    }
    .ticket-swipe-track {
        -webkit-tap-highlight-color: transparent;
        touch-action: pan-y;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
    }
    #mobileTicketsList .ticket-swipe-track {
        border-radius: inherit;
        overflow: hidden;
    }
    #mobileTicketsList .ticket-swipe-actions-layer {
        border-radius: inherit;
        overflow: hidden;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
    }
    .ticket-mobile-item--swipe-revealed .ticket-swipe-actions-layer {
        opacity: 1;
        pointer-events: auto;
    }
    /* Touch: kein „klebendes“ Hover, kein blauer Tap-Highlight auf Ticket-Karten */
    .ticket-mobile-compact-card {
        -webkit-tap-highlight-color: transparent;
    }
    .ticket-mobile-compact-card .ticket-mobile-card-status > span {
        font-size: 0.625rem;
        line-height: 1rem;
        padding: 0.125rem 0.375rem;
        border-radius: 9999px;
    }
    .ticket-mobile-compact-card .ticket-mobile-card-prio > span {
        font-size: 0.625rem;
        line-height: 1rem;
        padding: 0.125rem 0.35rem;
        border-radius: 9999px;
    }
    @media (hover: hover) and (pointer: fine) {
        .ticket-mobile-compact-card:hover {
            background-color: rgb(249 250 251);
        }
        .dark .ticket-mobile-compact-card:hover {
            background-color: rgb(30 41 59 / 0.45);
        }
    }
    /* Geräte-Inset: feste Farben aus Theme – dynamische Tailwind-Klassen im JS-String werden vom CDN oft nicht mitgeneriert, daher wirkte die Fläche im Dark Mode hell (bg-gray-50). */
    .ticket-mobile-compact-card .ticket-mobile-device-inset {
        border-width: 1px;
        border-style: solid;
        background-color: rgba(249, 250, 251, 0.9);
        border-color: rgb(229 231 235 / 0.65);
    }
    .dark .ticket-mobile-compact-card .ticket-mobile-device-inset {
        /* Neutral (kein Blau-Stich wie primary-140): etwas heller als die Karte (primary-100) */
        background-color: <?php echo htmlspecialchars($primaryColors[120] ?? '#1b1b1c', ENT_QUOTES, 'UTF-8'); ?>;
        border-color: rgba(255, 255, 255, 0.09);
    }
}
</style>

<!-- Modal: Suchbereich Ticket-Suche (wonach gesucht wird) -->
<div id="ticketSearchScopeModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4" aria-labelledby="ticket-search-scope-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity cursor-pointer" aria-hidden="true" id="ticketSearchScopeModalOverlay"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg max-h-[calc(100vh-2rem)] flex flex-col relative z-10">
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden flex flex-col max-h-[calc(100vh-2rem)]">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 flex-shrink-0">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-primary-200" id="ticket-search-scope-modal-title">
                            Suchbereich für Ticket-Suche
                        </h3>
                        <button type="button" id="closeTicketSearchScopeModalBtn" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Leg fest, in welchen Feldern bei der Suche gesucht wird. Gilt auch in den Einstellungen.</p>
                </div>
                <div class="flex-1 min-h-0 max-h-[min(60vh,28rem)] overflow-y-auto overflow-x-hidden border-t border-gray-200 dark:border-primary-120 px-4 py-4 custom-scrollbar">
                    <div id="ticket-search-scope-modal-container" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <template id="ticket-search-scope-modal-template">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600">
                                <input type="checkbox" class="ticket-search-scope-modal-cb h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800">
                                <span class="ticket-search-scope-modal-label text-sm text-gray-700 dark:text-gray-300"></span>
                            </label>
                        </template>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" id="ticket-search-scope-modal-all" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Alle auswählen</button>
                        <button type="button" id="ticket-search-scope-modal-none" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Keine</button>
                    </div>
                </div>
                <div class="px-4 pb-5 pt-2 sm:px-6 sm:pb-6 flex-shrink-0 border-t border-gray-200 dark:border-primary-120">
                    <button type="button" id="ticket-search-scope-modal-save" class="w-full inline-flex justify-center rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                        Übernehmen
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kontextmenü für Tickets (Rechtsklick) -->
<div id="ticketContextBackdrop" class="hidden fixed inset-0 z-40" aria-hidden="true"></div>
<div id="ticketContextMenu" class="hidden fixed z-50 min-w-[200px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg">
    <!-- Im neuen Tab öffnen -->
    <button type="button" data-ticket-ctx="open-new-tab" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        <span>Im neuen Tab öffnen</span>
    </button>
    <button type="button" id="ticketCtxTermin" data-ticket-ctx="termin" class="hidden w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <span>Termin festlegen</span>
    </button>
    <button type="button" id="ticketCtxReadUnreadBtn" data-ticket-ctx="mark-unread" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        <span id="ticketCtxReadUnreadLabel">Als ungelesen markieren</span>
    </button>
    <!-- Detail ansicht (nur in Chat-Ansicht) -->
    <button type="button" data-ticket-ctx="detail-view" id="ticketCtxDetailView" class="hidden w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 11h2v5m-2 0h4m-2.592-8.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        <span>Detail ansicht</span>
    </button>
    <div class="border-t border-gray-200 dark:border-primary-120 my-1"></div>
    <!-- Gehe zu -->
    <div id="ticketCtxGoToSection" class="relative">
        <div id="ticketCtxGoToTrigger" class="px-3 py-2 text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 cursor-default">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            <span>Gehe zu</span>
            <svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </div>
        <div id="ticketCtxGoToSubmenu" class="hidden absolute left-full top-0 ml-0.5 py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg z-10 whitespace-nowrap">
            <!-- Optionen werden dynamisch eingefügt -->
        </div>
    </div>
  
    <!-- Status ändern -->
    <div id="ticketCtxStatusSection" class="relative">
        <div id="ticketCtxStatusTrigger" class="px-3 py-2 text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 cursor-default">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.583 8.445h.01M10.86 19.71l-6.573-6.63a.993.993 0 0 1 0-1.4l7.329-7.394A.98.98 0 0 1 12.31 4l5.734.007A1.968 1.968 0 0 1 20 5.983v5.5a.992.992 0 0 1-.316.727l-7.44 7.5a.974.974 0 0 1-1.384.001Z"/></svg>
            <span>Status ändern</span>
            <svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </div>
        <div id="ticketCtxStatusSubmenu" class="hidden absolute left-full top-0 ml-0.5 min-w-[160px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg z-10">
            <button type="button" data-ticket-ctx="status" data-status="Neu" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">Neu</button>
            <button type="button" data-ticket-ctx="status" data-status="In Bearbeitung" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">In Bearbeitung</button>
            <button type="button" data-ticket-ctx="status" data-status="Warteschlange" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">Warteschlange</button>
        </div>
    </div>
    <!-- Bearbeiter hinzufügen -->
    <div id="ticketCtxAssignSection" class="relative">
        <div id="ticketCtxAssignTrigger" class="px-3 py-2 text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 cursor-default">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/></svg>
            <span>Bearbeiter hinzufügen</span>
            <svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </div>
        <div id="ticketCtxAssignSubmenu" class="hidden absolute left-full top-0 ml-0.5 min-w-[160px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg max-h-[50vh] overflow-y-auto z-10">
            <!-- Benutzer dynamisch eingefügt -->
        </div>
    </div>
    <!-- Anheften -->
    <button type="button" data-ticket-ctx="pin" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M12.0001 20v-4M7.00012 4h9.99998M9.00012 5v5c0 .5523-.46939 1.0045-.94861 1.279-1.43433.8217-2.60135 3.245-2.25635 4.3653.07806.2535.35396.3557.61917.3557H17.5859c.2652 0 .5411-.1022.6192-.3557.3449-1.1204-.8221-3.5436-2.2564-4.3653-.4792-.2745-.9486-.7267-.9486-1.279V5c0-.55228-.4477-1-1-1h-4c-.55226 0-.99998.44772-.99998 1Z"/></svg>
        <span id="ticketCtxPinText">Anheften</span>
    </button>
    <div id="ticketCtxRechnungBearbeitungszeitDivider" class="hidden border-t border-gray-200 dark:border-primary-120 my-1" role="separator" aria-hidden="true"></div>
    <!-- Rechnung schreiben (nur für Admins) -->
    <button type="button" data-ticket-ctx="rechnung" id="ticketCtxRechnung" class="hidden w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        <span>Rechnung schreiben</span>
    </button>
    <!-- Bearbeitungszeit hinzufügen (nur für geschlossene Tickets ohne Bearbeitungszeit) -->
    <button type="button" data-ticket-ctx="bearbeitungszeit" id="ticketCtxBearbeitungszeit" class="hidden w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span>Bearbeitungszeit hinzufügen</span>
    </button>
    <!-- Bearbeitungszeit nur Anzeige (Geschlossen/Archiv, wenn bereits erfasst) -->
    <div id="ticketCtxBearbeitungszeitInfo" class="hidden w-full px-3 py-2 text-left text-sm text-gray-600 dark:text-primary-240 flex items-center gap-2 cursor-default select-none" aria-live="polite">
        <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span id="ticketCtxBearbeitungszeitInfoText"></span>
    </div>
</div>

<!-- Bearbeitungszeit-Modal (Stil wie Kundenauswahl-Modal) -->
<div id="bearbeitungszeitModal" class="hidden fixed inset-0 z-50 overflow-hidden p-4" aria-labelledby="bearbeitungszeit-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/70 backdrop-blur-sm transition-opacity cursor-pointer" aria-hidden="true" id="bearbeitungszeitModalOverlay" onclick="closeBearbeitungszeitModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-md flex flex-col relative z-10">
            <div class="relative bg-white dark:bg-primary-100 rounded-2xl shadow-xl border border-gray-200/80 dark:border-primary-120 overflow-hidden flex flex-col max-h-[calc(100vh-2rem)]">
                <!-- Header -->
                <div class="flex items-start justify-between gap-4 px-6 pt-6 pb-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-gray-100 dark:bg-primary-200 flex items-center justify-center text-gray-600 dark:text-primary-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200" id="bearbeitungszeit-modal-title">Bearbeitungszeit</h3>
                            <p class="text-sm text-gray-500 dark:text-primary-240 mt-0.5">Wie lange haben Sie an der Bearbeitung gearbeitet?</p>
                        </div>
                    </div>
                    <button type="button" id="closeBearbeitungszeitModalBtn" onclick="closeBearbeitungszeitModal()" class="flex-shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex flex-col flex-1 min-h-0 px-6 pb-6">
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
                               class="w-full px-4 py-2 rounded-xl border border-gray-300 dark:border-primary-320 bg-gray-50 dark:bg-primary-300/50 text-gray-900 dark:text-primary-200 placeholder-gray-400 dark:placeholder-primary-250 focus:ring-2 focus:ring-primary-500/25 focus:border-primary-500 dark:focus:ring-primary-400/30 dark:focus:border-primary-400 transition-colors">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeBearbeitungszeitModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Abbrechen</button>
                        <button type="button" id="bearbeitungszeitConfirmBtn" onclick="confirmBearbeitungszeit()" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">Übernehmen</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schnell-Termin (Übersicht: Kontextmenü / Wisch-Aktion, ohne view.php) — Desktop zentriert, Mobil als Sheet von unten; z über appMobileFooter (z-60) -->
<div id="terminQuickModal" class="hidden fixed inset-0 z-[73] overflow-hidden" aria-labelledby="termin-quick-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/70 backdrop-blur-sm transition-opacity cursor-pointer" aria-hidden="true" id="terminQuickModalOverlay"></div>
    <div class="fixed inset-0 flex items-end lg:items-center justify-center min-h-full min-w-full p-0 lg:p-4 pointer-events-none">
        <div id="terminQuickSheet" class="pointer-events-auto w-full max-w-lg lg:max-w-md flex flex-col relative z-10 min-h-0 max-lg:max-h-[min(88dvh,calc(100dvh-4.5rem))] lg:max-h-[calc(100vh-2rem)]">
            <div class="relative bg-white dark:bg-primary-100 rounded-t-2xl lg:rounded-2xl shadow-xl border border-gray-200/80 dark:border-primary-120 border-b-0 lg:border-b overflow-hidden flex flex-col min-h-0 max-h-full">
                <div id="terminQuickSheetDragZone" class="max-lg:touch-none shrink-0 cursor-grab active:cursor-grabbing">
                    <div class="mx-auto mt-2 h-1.5 w-12 shrink-0 rounded-full bg-gray-300 dark:bg-primary-240 lg:hidden" aria-hidden="true"></div>
                    <div class="flex items-start justify-between gap-4 px-6 pt-3 lg:pt-6 pb-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-gray-100 dark:bg-primary-900/30 flex items-center justify-center text-gray-600 dark:text-primary-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200" id="termin-quick-modal-title">Termin festlegen</h3>
                                <p class="text-sm text-gray-500 dark:text-primary-240 mt-0.5 max-lg:hidden">Zeit wählen, Enter speichert.</p>
                                <p class="text-xs text-gray-500 dark:text-primary-240 mt-0.5 lg:hidden">Nach unten wischen zum Schließen</p>
                            </div>
                        </div>
                        <button type="button" id="terminQuickModalCloseBtn" class="flex-shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <form id="terminQuickForm" class="flex flex-col flex-1 min-h-0">
                    <div id="terminQuickScrollArea" class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-6 pt-1 pb-3 space-y-4">
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Schnellauswahl Startzeit">
                            <button type="button" data-termin-preset="nextHour" class="termin-quick-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-100 text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140">Nächste volle Stunde</button>
                            <button type="button" data-termin-preset="in1h" class="termin-quick-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-100 text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140">In 1 Std</button>
                            <button type="button" data-termin-preset="today17" class="termin-quick-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-100 text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140">Heute 17:00</button>
                            <button type="button" data-termin-preset="tomorrow9" class="termin-quick-preset px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-100 text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140">Morgen 9:00</button>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label for="terminQuickStart" class="block text-sm font-medium text-gray-700 dark:text-primary-200 mb-1">Start <span class="text-red-500">*</span></label>
                                <input type="datetime-local" id="terminQuickStart" required class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500">
                            </div>
                            <div>
                                <label for="terminQuickEnd" class="block text-sm font-medium text-gray-700 dark:text-primary-200 mb-1">Ende <span class="text-gray-400 font-normal">(optional)</span></label>
                                <input type="datetime-local" id="terminQuickEnd" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500">
                            </div>
                            <div>
                                <label for="terminQuickTitle" class="block text-sm font-medium text-gray-700 dark:text-primary-200 mb-1">Titel <span class="text-gray-400 font-normal">(optional)</span></label>
                                <input type="text" id="terminQuickTitle" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-100 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500" placeholder="z. B. Rückruf, Vor-Ort" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="shrink-0 border-t border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 px-6 py-3 max-lg:pb-[max(0.75rem,calc(env(safe-area-inset-bottom,0px)+0.25rem))] lg:pb-4">
                        <div class="flex justify-end gap-2">
                            <button type="button" id="terminQuickCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Abbrechen</button>
                            <button type="submit" id="terminQuickSaveBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">Speichern</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<style>
body.bearbeitungszeit-modal-open { overflow: hidden; }
body.termin-quick-modal-open { overflow: hidden; }
/* Mobil: Sheet von unten */
@media (max-width: 1023px) {
    #terminQuickSheet {
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
    }
    #terminQuickModal.termin-quick-sheet-open #terminQuickSheet {
        transform: translateY(0);
    }
}
@media (min-width: 1024px) {
    #terminQuickSheet {
        transform: none !important;
        transition: none;
    }
}
/* Datepicker-Felder im Schnell-Termin: niemals über den Sheet-Rand hinausragen */
#terminQuickForm input[type="datetime-local"],
#terminQuickForm input[type="text"] {
    inline-size: 100%;
    max-inline-size: 100%;
    min-inline-size: 0;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
#terminQuickScrollArea,
#terminQuickScrollArea > .space-y-3,
#terminQuickScrollArea > .space-y-3 > div {
    min-width: 0;
}
/* Fußleiste liegt bei gleichem z-index über dem Inhalt — während Schnell-Termin ausblenden */
@media (max-width: 1023px) {
    body.termin-quick-modal-open #appMobileFooterRoot {
        visibility: hidden !important;
        pointer-events: none !important;
    }
}
</style>

<script>
const ticketsApiUrl = '<?php echo BASE_URL; ?>tickets/api/tickets.php';
const appointmentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/appointments.php';
const commentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/comments.php';
const consumablesApiUrl = '<?php echo BASE_URL; ?>inventory/api/consumables.php';
const commentAttachmentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/comment_attachments.php';
const ticketAttachmentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/attachments.php';
const userRole = '<?php echo addslashes($userRole); ?>';
const isAdminOrTech = (userRole === 'Admin' || userRole === 'Techniker');
const canFilterAnforderer = (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin');
const currentUserId = parseInt(<?php echo $userId; ?>);
const assigneesData = <?php echo json_encode($assignees ?? []); ?>;
const customersFilterData = <?php echo json_encode($customers ?? []); ?>;
const companiesFilterData = <?php echo json_encode($companiesForFilter ?? []); ?>;
let selectedCompanyId = null;
let advancedFilterRules = [];
let advancedFilterRulesDraft = [];
let allTickets = [];
let filteredTickets = [];
let ticketsLoadedOnce = false; // Erster API-Load abgeschlossen (für Mobile Lade-Anzeige)
let loadTicketsRequestSeq = 0; // Nur die neueste Tickets-API-Antwort darf UI/Fehler setzen (parallele Loads)
let ticketsLoadingSkeletonTimer = null; // Verzögertes Einblenden der Skeletons, um Flackern bei schnellen Responses zu vermeiden
const ticketsLoadingSkeletonDelayMs = 300;
let currentView = 'chat'; // 'table', 'cards' oder 'chat' – Standard: Chat-Ansicht
let sortColumn = 'geaendert_datum'; // Standard: Zuletzt geändert
let sortDirection = 'desc'; // 'asc' oder 'desc' - Standard: Neueste zuerst
let selectedChatTicket = null;
let isLoadingComments = false; // Flag um mehrfache gleichzeitige Aufrufe zu verhindern
let selectedCommentIdForAttachment = null;
let chatDisplayName = 'anforderer'; // 'anforderer', 'firma', 'kunde' - aus user_settings
let ticketSearchScope = []; // Suchbereich aus user_settings; leer = alle Felder
/** Kontextmenü Tickets (Rechtsklick): muss global sein, damit hideTicketContextMenu() dieselbe Referenz wie showTicketContextMenu nutzt. */
let ticketContextTicket = null;
let ticketContextTargetRow = null;
let ticketContextIgnoreOutsideCloseUntil = 0;
function clearTicketContextTargetHighlight() {
    if (ticketContextTargetRow) {
        ticketContextTargetRow.classList.remove('ticket-context-active');
        ticketContextTargetRow = null;
    }
}

const FILTER_STORAGE_KEY = 'serviceIndexFilters';
const ticketsOpenCountUrl = '<?php echo BASE_URL; ?>tickets/api/open-count.php';
const ticketsSidebarFiltersSyncUrl = '<?php echo BASE_URL; ?>settings/api/sidebar-tickets-filters.php';
const ticketsCreateUrl = '<?php echo BASE_URL; ?>tickets/create.php';
const ticketsEmptyIllustrationUrl = '<?php echo BASE_URL; ?>assets/images/tickets-empty-illustration.svg';
const HASH_FILTERS = ['zugewiesen', 'ohne-bearbeitungszeit', 'geschlossen'];
var sidebarTicketsFiltersSyncTimer = null;
const TICKET_LIST_SCROLL_STORAGE_KEY = 'ticketsIndexScrollY';
var ticketsMobileSearchOpenedAt = 0;
var TICKETS_MOBILE_SEARCH_AUTOCLOSE_GUARD_MS = 450;
var ticketsIgnoreNavSearchClickUntil = 0;
var ticketsMobileSearchFocusTimer = 0;
var ticketListScrollRestoreRequested = false;
var ticketListScrollRestored = false;

function getTicketListCurrentScrollY() {
    return window.pageYOffset || window.scrollY || document.documentElement.scrollTop || 0;
}

function saveTicketListScrollPosition() {
    try {
        sessionStorage.setItem(TICKET_LIST_SCROLL_STORAGE_KEY, String(Math.max(0, Math.round(getTicketListCurrentScrollY()))));
    } catch (e) {}
}

function restoreTicketListScrollPosition(force) {
    if (!force && ticketListScrollRestored) return;
    var raw = null;
    try {
        raw = sessionStorage.getItem(TICKET_LIST_SCROLL_STORAGE_KEY);
    } catch (e) {
        return;
    }
    if (raw === null || raw === '') return;
    var target = parseInt(raw, 10);
    if (!Number.isFinite(target) || target < 0) {
        try { sessionStorage.removeItem(TICKET_LIST_SCROLL_STORAGE_KEY); } catch (e2) {}
        return;
    }
    var attempts = 0;
    var maxAttempts = 42;
    function applyRestore() {
        var maxScroll = Math.max(
            0,
            (document.documentElement ? document.documentElement.scrollHeight : 0) - window.innerHeight,
            (document.body ? document.body.scrollHeight : 0) - window.innerHeight
        );
        var desired = Math.min(target, maxScroll);
        window.scrollTo(0, desired);
        var current = getTicketListCurrentScrollY();
        if (Math.abs(current - desired) <= 2 || attempts >= maxAttempts) {
            ticketListScrollRestored = true;
            try { sessionStorage.removeItem(TICKET_LIST_SCROLL_STORAGE_KEY); } catch (e3) {}
            return;
        }
        attempts += 1;
        window.requestAnimationFrame(applyRestore);
    }
    window.requestAnimationFrame(applyRestore);
}

function requestTicketListScrollRestore(force) {
    if (!force && ticketListScrollRestored) return;
    if (ticketListScrollRestoreRequested && !force) return;
    ticketListScrollRestoreRequested = true;
    window.requestAnimationFrame(function() {
        ticketListScrollRestoreRequested = false;
        restoreTicketListScrollPosition(!!force);
    });
}

function navigateToTicketDetail(url) {
    if (!url) return;
    saveTicketListScrollPosition();
    window.location.href = url;
}

/** Mobile-Ansicht: gleicher Breakpoint wie Tailwind max-lg (1024px), per Media Query statt innerWidth (stabiler auf Handys). */
function isMobileView() {
    return window.matchMedia('(max-width: 1023px)').matches;
}

/** Mobil-Ticketsuche (Nav): gleiche Logik wie Lager (inv-*). */
function ticketsMobileSearchIsEmpty() {
    var m = document.getElementById('tickets-mobile-search');
    var d = document.getElementById('search');
    var mv = m ? (m.value || '').trim() : '';
    var dv = d ? (d.value || '').trim() : '';
    return !mv && !dv;
}
function ticketsCloseMobileSearchIfEmpty() {
    var dash = document.getElementById('tickets-mobile-dashboard');
    if (!dash || !dash.classList.contains('tickets-mobile-search-panel-open')) return;
    if (ticketsMobileSearchOpenedAt && (Date.now() - ticketsMobileSearchOpenedAt) < TICKETS_MOBILE_SEARCH_AUTOCLOSE_GUARD_MS) return;
    if (!ticketsMobileSearchIsEmpty()) return;
    ticketsSetMobileSearchPanelOpen(false, false);
}
function ticketsSetMobileSearchPanelOpen(open, focusInput) {
    var dash = document.getElementById('tickets-mobile-dashboard');
    var anim = document.getElementById('tickets-mobile-search-anim');
    var btn = document.getElementById('navMobileTicketsSearchBtn');
    if (!dash) return;
    if (typeof focusInput === 'undefined') focusInput = !!open;
    if (open) {
        ticketsMobileSearchOpenedAt = Date.now();
        dash.classList.add('tickets-mobile-search-panel-open');
        if (anim) anim.setAttribute('aria-hidden', 'false');
        if (btn) btn.setAttribute('aria-expanded', 'true');
        if (focusInput) {
            var mInp = document.getElementById('tickets-mobile-search');
            if (mInp) {
                if (ticketsMobileSearchFocusTimer) window.clearTimeout(ticketsMobileSearchFocusTimer);
                try {
                    void dash.offsetHeight;
                    void mInp.offsetHeight;
                    try { mInp.focus({ preventScroll: true }); } catch (eFocusNow) { try { mInp.focus(); } catch (e2Now) {} }
                    ticketsMobileSearchFocusTimer = window.setTimeout(function() {
                        ticketsMobileSearchFocusTimer = 0;
                        if (typeof mInp.setSelectionRange === 'function') {
                            var len = (mInp.value && mInp.value.length) ? mInp.value.length : 0;
                            try { mInp.setSelectionRange(len, len); } catch (eSel) {}
                        }
                    }, 120);
                } catch (e) {
                    try { mInp.focus(); } catch (e2) {}
                }
            }
        }
    } else {
        ticketsMobileSearchOpenedAt = 0;
        if (ticketsMobileSearchFocusTimer) {
            window.clearTimeout(ticketsMobileSearchFocusTimer);
            ticketsMobileSearchFocusTimer = 0;
        }
        dash.classList.remove('tickets-mobile-search-panel-open');
        if (anim) anim.setAttribute('aria-hidden', 'true');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        var mBlur = document.getElementById('tickets-mobile-search');
        if (mBlur && document.activeElement === mBlur) {
            try { mBlur.blur(); } catch (eB) {}
        }
    }
}
function ticketsEnsureMobileSearchPanelIfQuery() {
    var dash = document.getElementById('tickets-mobile-dashboard');
    var desk = document.getElementById('search');
    var btn = document.getElementById('navMobileTicketsSearchBtn');
    if (!dash || !btn) return;
    if (typeof window.matchMedia === 'function' && !window.matchMedia('(max-width: 1023px)').matches) return;
    if (desk && (desk.value || '').trim()) {
        ticketsSetMobileSearchPanelOpen(true, false);
    }
}
function syncTicketsMobileSearchFieldMirrors() {
    var desk = document.getElementById('search');
    var mob = document.getElementById('tickets-mobile-search');
    if (!desk || !mob) return;
    if (document.activeElement === mob) return;
    mob.value = desk.value || '';
}

/** Kompakt-Nav-Titel: „Alle Tickets“ ohne aktive Filter, sonst „Tickets“. */
function updateTicketsMobileNavTitle() {
    var el = document.getElementById('navMobileCompactTitle');
    if (!el) return;
    var customerEl = document.getElementById('customer-filter');
    var assigneeEl = document.getElementById('assignee-filter');
    var statusEl = document.getElementById('status-filter');
    var searchEl = document.getElementById('search');
    var customerOn = !!(customerEl && customerEl.value);
    var assigneeOn = !!(assigneeEl && assigneeEl.value);
    var statusVal = statusEl ? (statusEl.value || 'offen_combined') : 'offen_combined';
    var searchOn = !!(searchEl && searchEl.value.trim());
    var companyOn = isAdminOrTech && typeof selectedCompanyId !== 'undefined' && !!selectedCompanyId;
    var hash = (window.location.hash || '').replace(/^#/, '');
    var hashActive = hash === 'angeheftet' || (typeof HASH_FILTERS !== 'undefined' && HASH_FILTERS.indexOf(hash) !== -1);
    var advOn = Array.isArray(advancedFilterRules) && advancedFilterRules.some(function(r) {
        return r && r.field && r.operator && (r.operator === 'empty' || r.operator === 'not_empty' || (r.value !== '' && r.value != null));
    });
    var hasFilter = customerOn || assigneeOn || statusVal !== 'offen_combined' || searchOn || companyOn || hashActive || advOn;
    if (!hasFilter) {
        el.textContent = 'Alle Tickets';
        return;
    }
    var n = Array.isArray(filteredTickets) ? filteredTickets.length : 0;
    el.textContent = n === 1 ? '1 Ticket' : (n + ' Tickets');
}

function getFiltersState() {
    const hash = (window.location.hash || '').replace(/^#/, '');
    const customerFilter = document.getElementById('customer-filter');
    const customerFilterText = document.getElementById('customer-filter-text');
    const assigneeFilter = document.getElementById('assignee-filter');
    const assigneeFilterText = document.getElementById('assignee-filter-text');
    const statusFilterInput = document.getElementById('status-filter');
    const searchEl = document.getElementById('search');
    const sortSelection = document.getElementById('sort-selection');
    let effectiveHash = HASH_FILTERS.includes(hash) ? hash : '';
    if (!effectiveHash && assigneeFilter && assigneeFilter.value === String(currentUserId)) {
        effectiveHash = 'zugewiesen';
    }
    return {
        hash: effectiveHash,
        customer: customerFilter ? customerFilter.value : '',
        customerText: (customerFilterText && customerFilterText.textContent) ? customerFilterText.textContent.trim() : '',
        assignee: assigneeFilter ? assigneeFilter.value : '',
        assigneeText: (assigneeFilterText && assigneeFilterText.textContent) ? assigneeFilterText.textContent.trim() : '',
        status: statusFilterInput ? statusFilterInput.value : 'offen_combined',
        search: searchEl ? searchEl.value : '',
        sortColumn: sortColumn || (sortSelection ? sortSelection.value : ''),
        sortDirection: sortDirection || 'desc',
        advancedFilters: Array.isArray(advancedFilterRules) ? advancedFilterRules : []
    };
}

function saveFiltersState() {
    try {
        const state = getFiltersState();
        localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(state));
        // Hash nicht in der URL anzeigen – bei Bedarf aus der URL entfernen
        if (HASH_FILTERS.some(h => (window.location.hash || '').replace(/^#/, '') === h)) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
        if (typeof syncSidebarTicketsFilters === 'function') syncSidebarTicketsFilters();
    } catch (e) {
        console.error('Fehler beim Speichern der Filter', e);
    }
}

/** Filter-Snapshot für Sidebar-Zähler (Modus „Aktive Filter“) an den Server senden. */
function syncSidebarTicketsFilters() {
    if (!ticketsSidebarFiltersSyncUrl) return;
    clearTimeout(sidebarTicketsFiltersSyncTimer);
    sidebarTicketsFiltersSyncTimer = setTimeout(function() {
        var state = typeof getFiltersState === 'function' ? getFiltersState() : {};
        var hash = (window.location.hash || '').replace(/^#/, '');
        var status = state.status || 'offen_combined';
        if (hash === 'ohne-bearbeitungszeit') status = 'ohne_bearbeitungszeit';
        else if (hash === 'geschlossen') status = 'geschlossen';
        else if (hash === 'angeheftet') status = 'angeheftet';
        var payload = {
            status: status,
            customer: state.customer || '',
            assignee: state.assignee || '',
            company_id: (typeof selectedCompanyId !== 'undefined' && selectedCompanyId) ? selectedCompanyId : null
        };
        fetch(ticketsSidebarFiltersSyncUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function() {
            if (typeof updateSidebarTicketsCount === 'function') updateSidebarTicketsCount();
        }).catch(function() {});
    }, 300);
}

/** Sidebar-Zähler für Tickets nach API-Abfrage aktualisieren. */
function updateSidebarTicketsCount() {
    if (!ticketsOpenCountUrl) return;
    fetch(ticketsOpenCountUrl, { headers: { 'Content-Type': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var nodes = document.querySelectorAll('.sidebar-open-tickets-count-badge');
            if (!nodes.length) return;
            var count = data.success ? (data.open_count || 0) : 0;
            var text = count > 99 ? '99' : String(count);
            var title = count + ' offene Tickets';
            nodes.forEach(function(el) {
                el.textContent = text;
                el.title = title;
                el.classList.toggle('hidden', count <= 0);
            });
        })
        .catch(function() {});
}
window.updateSidebarTicketsCount = updateSidebarTicketsCount;

function syncMobileSheetCustomerLabel() {
    var src = document.getElementById('customer-filter-text');
    var ml = document.getElementById('mobile-sheet-customer-label');
    if (!ml || !src) return;
    ml.textContent = src.textContent.trim() || 'Alle Kunden';
}

function updateCustomerFilterButtonState() {
    const customerFilterButton = document.getElementById('customer-filter-button');
    const customerFilter = document.getElementById('customer-filter');
    if (customerFilterButton && customerFilter) {
        const hasValue = customerFilter.value && customerFilter.value.trim() !== '';
        if (hasValue) {
            customerFilterButton.classList.add('customer-filter-btn--active');
            customerFilterButton.classList.remove('filter-btn--default');
        } else {
            customerFilterButton.classList.remove('customer-filter-btn--active');
            customerFilterButton.classList.add('filter-btn--default');
        }
    }
    syncMobileSheetCustomerLabel();
}
function updateAssigneeFilterButtonState() {
    const assigneeFilterButton = document.getElementById('assignee-filter-button');
    const assigneeFilter = document.getElementById('assignee-filter');
    if (!assigneeFilterButton || !assigneeFilter) return;
    const hasValue = assigneeFilter.value && assigneeFilter.value.trim() !== '';
    if (hasValue) {
        assigneeFilterButton.classList.add('assignee-filter-btn--active');
        assigneeFilterButton.classList.remove('filter-btn--default');
    } else {
        assigneeFilterButton.classList.remove('assignee-filter-btn--active');
        assigneeFilterButton.classList.add('filter-btn--default');
    }
}
function updateStatusFilterButtonState() {
    const statusFilterButton = document.getElementById('status-filter-button');
    const statusFilterInput = document.getElementById('status-filter');
    if (!statusFilterButton || !statusFilterInput) return;
    const isDefault = statusFilterInput.value === 'offen_combined';
    if (isDefault) {
        statusFilterButton.classList.add('filter-btn--default');
        statusFilterButton.classList.remove('status-filter-btn--active');
    } else {
        statusFilterButton.classList.remove('filter-btn--default');
        statusFilterButton.classList.add('status-filter-btn--active');
    }
}
function restoreFiltersState() {
    try {
        const raw = localStorage.getItem(FILTER_STORAGE_KEY);
        if (!raw) return;
        const state = JSON.parse(raw);
        const customerFilter = document.getElementById('customer-filter');
        const customerFilterText = document.getElementById('customer-filter-text');
        const assigneeFilter = document.getElementById('assignee-filter');
        const assigneeFilterText = document.getElementById('assignee-filter-text');
        const searchEl = document.getElementById('search');
        if (state.customer !== undefined && customerFilter) {
            customerFilter.value = state.customer || '';
        }
        if (state.customerText !== undefined && customerFilterText) {
            customerFilterText.textContent = state.customerText || 'Alle Kunden';
        }
        if (state.assignee !== undefined && assigneeFilter) {
            assigneeFilter.value = state.assignee || '';
        }
        if (state.assigneeText !== undefined && assigneeFilterText) {
            assigneeFilterText.textContent = state.assigneeText || 'Alle Bearbeiter';
        }
        if (assigneeFilter && assigneeFilterText && assigneeFilter.value === String(currentUserId)) {
            assigneeFilterText.textContent = 'Mir zugewiesen';
        }
        // Status-Filter bleibt in ticketsStatusFilter (wird weiter unten wiederhergestellt)
        if (state.search !== undefined && searchEl) {
            searchEl.value = state.search || '';
        }
        if (state.sortColumn) {
            // Alte Sortierwerte auf die neue Termin-Sortierung abbilden
            sortColumn = (state.sortColumn === 'faellig_datum' || state.sortColumn === 'geplant_datum')
                ? 'naechster_termin'
                : state.sortColumn;
            const sortSel = document.getElementById('sort-selection');
            if (sortSel) sortSel.value = sortColumn;
        }
        if (state.sortDirection === 'asc' || state.sortDirection === 'desc') {
            sortDirection = state.sortDirection;
        }
        if (Array.isArray(state.advancedFilters)) {
            advancedFilterRules = normalizeAdvancedFilterRules(state.advancedFilters.filter(function(r) {
                return r && typeof r.field === 'string' && r.field !== '';
            })).filter(advFilterRuleIsActive);
        }
        // Hash nicht in URL zurückschreiben – der aktuelle Link (z. B. von einer Dashboard-Card) hat Vorrang und wird im nächsten Block ausgewertet
        updateCustomerFilterButtonState();
        updateAssigneeFilterButtonState();
        updateAdvancedFilterButtonState();
    } catch (e) {
        console.error('Fehler beim Wiederherstellen der Filter', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Firmenfilter aus localStorage oder Session
    const savedSelection = localStorage.getItem('selectedUserOption');
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id) : null;
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }

    // Gespeicherte Filter (inkl. #-Filter) wiederherstellen
    restoreFiltersState();
    if (typeof ticketsEnsureMobileSearchPanelIfQuery === 'function') ticketsEnsureMobileSearchPanelIfQuery();
    if (typeof syncTicketsMobileSearchFieldMirrors === 'function') syncTicketsMobileSearchFieldMirrors();
    if (typeof updateSearchActiveState === 'function') updateSearchActiveState();

    // URL-Hash von Dashboard-Card: Filter explizit setzen, damit der Card-Link immer greift (Vorrang vor gespeichertem Zustand)
    const initialHash = (window.location.hash || '').replace(/^#/, '');
    const statusFilterInputEl = document.getElementById('status-filter');
    const statusFilterTextEl = document.getElementById('status-filter-text');
    const assigneeEl = document.getElementById('assignee-filter');
    const assigneeTextEl = document.getElementById('assignee-filter-text');
    const assigneeBtnEl = document.getElementById('assignee-filter-button');
    function applyFilterFromHash(hashValue) {
        const sInput = document.getElementById('status-filter');
        const sText = document.getElementById('status-filter-text');
        const aEl = document.getElementById('assignee-filter');
        const aText = document.getElementById('assignee-filter-text');
        const aBtn = document.getElementById('assignee-filter-button');
        if (hashValue === 'zugewiesen') {
            if (aEl) { aEl.value = String(currentUserId); if (aText) aText.textContent = 'Mir zugewiesen'; }
            if (aBtn) aBtn.classList.add('assignee-filter-btn--active');
            if (sInput) sInput.value = 'offen_combined';
            if (sText) sText.textContent = 'Offen';
            try { localStorage.setItem('ticketsStatusFilter', 'offen_combined'); } catch (e) {}
        } else if (hashValue === 'ohne-bearbeitungszeit') {
            if (aEl) { aEl.value = ''; }
            if (aText) aText.textContent = 'Alle Bearbeiter';
            if (aBtn) aBtn.classList.remove('assignee-filter-btn--active');
            if (sInput) sInput.value = 'ohne_bearbeitungszeit';
            if (sText) sText.textContent = 'Ohne Bearbeitungszeit';
            try { localStorage.setItem('ticketsStatusFilter', 'ohne_bearbeitungszeit'); } catch (e) {}
        } else if (hashValue === 'geschlossen') {
            if (aEl) { aEl.value = ''; }
            if (aText) aText.textContent = 'Alle Bearbeiter';
            if (aBtn) aBtn.classList.remove('assignee-filter-btn--active');
            if (sInput) sInput.value = 'geschlossen';
            if (sText) sText.textContent = 'Geschlossen';
            try { localStorage.setItem('ticketsStatusFilter', 'geschlossen'); } catch (e) {}
        }
        updateAssigneeFilterButtonState();
        updateStatusFilterButtonState();
        if (typeof filterTickets === 'function') filterTickets();
        if (typeof saveFiltersState === 'function') saveFiltersState();
    }

    if (HASH_FILTERS.includes(initialHash)) {
        applyFilterFromHash(initialHash);
        window.history.replaceState(null, '', window.location.pathname + window.location.search);
    }

    window.addEventListener('hashchange', function() {
        const h = (window.location.hash || '').replace(/^#/, '');
        if (HASH_FILTERS.includes(h)) {
            applyFilterFromHash(h);
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    });

    // Aktuellen Filterzustand (inkl. Hash) einmal speichern, damit #-Filter beim nächsten Besuch erhalten bleiben
    saveFiltersState();

    // Filter-Sichtbarkeit beim initialen Laden prüfen
    // (wird später nochmal aufgerufen, aber hier für schnelleres Feedback)
    setTimeout(updateCustomerFilterVisibility, 100);
    
    // Gespeicherte Ansicht aus localStorage laden (Standard: Chat). Auf Mobile immer Chat-Liste (WhatsApp-Style).
    const savedView = localStorage.getItem('ticketsView');
    if (savedView === 'table' || savedView === 'cards' || savedView === 'chat') {
        currentView = savedView;
    }
    if (window.innerWidth <= 768) {
        currentView = 'chat';
    }
    
    initAdvancedFilterModal();
    
    // Chat-Anzeige + Suchbereich: bewusst verzögert (Idle / kurzer Timeout), damit der erste
    // tickets.php-Request nicht mit diesen kleinen APIs um Bandbreite konkurriert (schwaches Netz).
    (function scheduleTicketPageSettingsFetches() {
        const run = function() {
            fetch('<?php echo BASE_URL; ?>settings/api/chat-display-name.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.chat_display_name) {
                        chatDisplayName = data.chat_display_name;
                        if (currentView === 'chat' && filteredTickets.length > 0) {
                            displayChatView(filteredTickets);
                        }
                    }
                })
                .catch(function() {});
            fetch('<?php echo BASE_URL; ?>settings/api/ticket-search-scope.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && Array.isArray(data.scope)) {
                        ticketSearchScope = data.scope;
                        var searchEl = document.getElementById('search');
                        if (searchEl && searchEl.value.trim()) loadTickets();
                    }
                })
                .catch(function() {});
        };
        if (window.requestIdleCallback) {
            window.requestIdleCallback(run, { timeout: 1200 });
        } else {
            setTimeout(run, 150);
        }
    })();

    // Popup: Suchbereich für Ticket-Suche (gleiche Einstellung wie unter Einstellungen)
    (function() {
        const modal = document.getElementById('ticketSearchScopeModal');
        const overlay = document.getElementById('ticketSearchScopeModalOverlay');
        const openBtn = document.getElementById('ticket-search-scope-btn');
        const closeBtn = document.getElementById('closeTicketSearchScopeModalBtn');
        const container = document.getElementById('ticket-search-scope-modal-container');
        const template = document.getElementById('ticket-search-scope-modal-template');
        const btnAll = document.getElementById('ticket-search-scope-modal-all');
        const btnNone = document.getElementById('ticket-search-scope-modal-none');
        const btnSave = document.getElementById('ticket-search-scope-modal-save');
        const scopeApiUrl = '<?php echo BASE_URL; ?>settings/api/ticket-search-scope.php';
        let modalScope = [];
        let modalAllKeys = {};

        function openModal() {
            if (!modal) return;
            modal.classList.remove('hidden');
            fetch(scopeApiUrl, { method: 'GET', headers: { 'Content-Type': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        modalScope = d.scope || [];
                        modalAllKeys = d.all_keys || {};
                        renderModalCheckboxes();
                    }
                })
                .catch(function() {});
        }

        function renderModalCheckboxes() {
            if (!container || !template) return;
            container.querySelectorAll('.ticket-search-scope-modal-cb').forEach(function(el) {
                var lb = el.closest('label');
                if (lb) lb.remove();
            });
            Object.keys(modalAllKeys).forEach(function(key) {
                const label = template.content.cloneNode(true);
                const cb = label.querySelector('.ticket-search-scope-modal-cb');
                const labelText = label.querySelector('.ticket-search-scope-modal-label');
                cb.value = key;
                cb.dataset.key = key;
                labelText.textContent = modalAllKeys[key];
                cb.checked = (modalScope[0] !== '_none') && (modalScope.length === 0 || modalScope.indexOf(key) !== -1);
                container.appendChild(label);
            });
        }

        function closeModal() {
            if (modal) modal.classList.add('hidden');
        }

        function saveModalScope() {
            const checked = Array.from(container.querySelectorAll('.ticket-search-scope-modal-cb:checked')).map(function(c) { return c.value; });
            const body = { scope: checked.length === Object.keys(modalAllKeys).length ? Object.keys(modalAllKeys) : checked };
            fetch(scopeApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success) {
                    ticketSearchScope = d.scope || [];
                    if (typeof showToast === 'function') showToast('Suchbereich übernommen', 'success');
                    closeModal();
                    var searchEl = document.getElementById('search');
                    if (searchEl && searchEl.value.trim()) loadTickets();
                } else if (typeof showToast === 'function') showToast(d.error || 'Speichern fehlgeschlagen', 'error');
            }).catch(function() {
                if (typeof showToast === 'function') showToast('Speichern fehlgeschlagen', 'error');
            });
        }

        if (openBtn) openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (overlay) overlay.addEventListener('click', closeModal);
        if (btnSave) btnSave.addEventListener('click', saveModalScope);
        if (btnAll) btnAll.addEventListener('click', function() {
            container.querySelectorAll('.ticket-search-scope-modal-cb').forEach(function(c) { c.checked = true; });
        });
        if (btnNone) btnNone.addEventListener('click', function() {
            container.querySelectorAll('.ticket-search-scope-modal-cb').forEach(function(c) { c.checked = false; });
        });
    })();

    // Ansicht wiederherstellen
    switchView(currentView, false);
    if (isMobileView()) {
        var wrap = document.getElementById('mobileTicketsWrap');
        if (wrap) {
            wrap.classList.remove('hidden');
            wrap.style.display = '';
            wrap.style.visibility = 'visible';
        }
        displayTickets(filteredTickets);
    }
    
    // Chat-Ansicht Höhe dynamisch anpassen (nur wenn kein Flex-Layout aktiv – bei service-chat-view-active scrollt nur der Chat-Inhalt)
    function adjustChatViewHeight() {
        if (document.body.classList.contains('service-chat-view-active')) return;
        const chatView = document.getElementById('chatView');
        if (chatView && !chatView.classList.contains('hidden')) {
            const rect = chatView.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const topOffset = rect.top;
            const availableHeight = viewportHeight - topOffset - 20; // 20px padding
            chatView.style.height = availableHeight + 'px';
        }
    }
    
    // Höhe bei Resize anpassen
    window.addEventListener('resize', adjustChatViewHeight);
    var lastMobileState = isMobileView();
    window.addEventListener('resize', function() {
        var isMobile = isMobileView();
        if (isMobile !== lastMobileState) {
            lastMobileState = isMobile;
            displayTickets(filteredTickets);
            if (isMobile) closeMobileFilterSheet();
        }
    });
    
    // Höhe beim ersten Laden anpassen, wenn Chat-Ansicht aktiv ist
    if (currentView === 'chat') {
        setTimeout(adjustChatViewHeight, 100);
    }
    
    const searchInput = document.getElementById('search');
    const searchForm = document.getElementById('search-form');

    // Suche: bei Änderung mit Debounce Tickets neu laden (API-Suche mit Suchbereich), dann Filter anwenden
    let searchDebounceTimer = null;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            updateSearchActiveState();
            if (typeof syncTicketsMobileSearchFieldMirrors === 'function') syncTicketsMobileSearchFieldMirrors();
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            var delayMs = 350;
            searchDebounceTimer = setTimeout(function() {
                searchDebounceTimer = null;
                loadTickets();
                saveFiltersState();
            }, delayMs);
        });
    }
    // Form-Submit verhindern (z. B. Enter im Suchfeld), damit keine Seiten-Reload; Suche nur per JS
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (searchInput) searchInput.blur();
            loadTickets();
            saveFiltersState();
        });
    }

    var ticketsMobileSearchInput = document.getElementById('tickets-mobile-search');
    if (ticketsMobileSearchInput && searchInput) {
        ticketsMobileSearchInput.addEventListener('input', function() {
            searchInput.value = ticketsMobileSearchInput.value;
            updateSearchActiveState();
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(function() {
                searchDebounceTimer = null;
                loadTickets();
                saveFiltersState();
            }, 350);
        });
        ticketsMobileSearchInput.addEventListener('blur', function() {
            window.requestAnimationFrame(function() {
                ticketsCloseMobileSearchIfEmpty();
            });
        });
    }
    var navTicketsSearchBtn = document.getElementById('navMobileTicketsSearchBtn');
    var ticketsMobileDash = document.getElementById('tickets-mobile-dashboard');
    if (navTicketsSearchBtn && ticketsMobileDash) {
        function ticketsToggleMobileSearchBar() {
            var isOpen = ticketsMobileDash.classList.contains('tickets-mobile-search-panel-open');
            if (!isOpen) {
                ticketsSetMobileSearchPanelOpen(true, true);
                return;
            }
            if (!ticketsMobileSearchIsEmpty()) {
                var mInp = document.getElementById('tickets-mobile-search');
                if (mInp) {
                    try { mInp.focus({ preventScroll: true }); } catch (e) { try { mInp.focus(); } catch (e2) {} }
                }
                return;
            }
            ticketsSetMobileSearchPanelOpen(false, false);
        }
        navTicketsSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (Date.now() < ticketsIgnoreNavSearchClickUntil) return;
            ticketsToggleMobileSearchBar();
        });
    }
    (function ticketsBindMobileSearchCloseOnScroll() {
        /* Scroll-basiertes Auto-Close deaktiviert: führte auf einigen Geräten zu sofortigem Schließen nach Pull-Open. */
    })();

    // Mobile: Filter-Sheet (Toggle in Top-Nav navMobileFilterToggleBtn)
    const navMobileFilterToggleBtn = document.getElementById('navMobileFilterToggleBtn');
    const navMobileFilterTitleEl = document.querySelector('[data-nav-mobile-filter-title]');
    const mobileFilterSheet = document.getElementById('mobileFilterSheet');
    const mobileFilterSheetBackdrop = document.getElementById('mobileFilterSheetBackdrop');
    const mobileFilterSheetPanel = document.getElementById('mobileFilterSheetPanel');
    const mainNavEl = document.getElementById('main-nav');
    var mobileFilterSheetCloseAnimCleanup = null;
    var mobileFilterSheetClosingAnimated = false;
    function finishCloseMobileFilterSheet() {
        if (mobileFilterSheetCloseAnimCleanup) {
            mobileFilterSheetCloseAnimCleanup();
            mobileFilterSheetCloseAnimCleanup = null;
        }
        mobileFilterSheetClosingAnimated = false;
        if (!mobileFilterSheet || !mobileFilterSheetPanel) return;
        if (mainNavEl) mainNavEl.classList.remove('main-nav-mobile-filter-open');
        mobileFilterSheet.setAttribute('aria-hidden', 'true');
        if (mobileFilterSheetBackdrop) {
            mobileFilterSheetBackdrop.style.pointerEvents = 'none';
            mobileFilterSheetBackdrop.classList.remove('opacity-100');
            mobileFilterSheetBackdrop.style.transition = '';
        }
        mobileFilterSheetPanel.classList.remove('mobile-filter-sheet-open');
        mobileFilterSheetPanel.style.transform = '';
        mobileFilterSheetPanel.style.transition = '';
        if (navMobileFilterToggleBtn) {
            navMobileFilterToggleBtn.setAttribute('aria-expanded', 'false');
            var lc = navMobileFilterToggleBtn.getAttribute('data-filter-label-closed');
            if (lc) {
                navMobileFilterToggleBtn.setAttribute('aria-label', lc);
                navMobileFilterToggleBtn.title = lc;
            }
        }
        if (navMobileFilterTitleEl) navMobileFilterTitleEl.setAttribute('aria-expanded', 'false');
    }
    function openMobileFilterSheet() {
        if (!mobileFilterSheet || !mobileFilterSheetPanel) return;
        if (mobileFilterSheetCloseAnimCleanup) {
            mobileFilterSheetCloseAnimCleanup();
            mobileFilterSheetCloseAnimCleanup = null;
        }
        mobileFilterSheetClosingAnimated = false;
        /* Root bleibt pointer-events-none – sonst blockiert inset-0 (z-68) die Top-Nav; nur Backdrop/Panel fangen Klicks. */
        if (mainNavEl) mainNavEl.classList.add('main-nav-mobile-filter-open');
        mobileFilterSheet.setAttribute('aria-hidden', 'false');
        mobileFilterSheetBackdrop.style.pointerEvents = 'auto';
        mobileFilterSheetBackdrop.style.transition = '';
        mobileFilterSheetBackdrop.classList.add('opacity-100');
        mobileFilterSheetPanel.classList.add('mobile-filter-sheet-open');
        mobileFilterSheetPanel.style.transform = '';
        mobileFilterSheetPanel.style.transition = '';
        if (navMobileFilterToggleBtn) {
            navMobileFilterToggleBtn.setAttribute('aria-expanded', 'true');
            var lo = navMobileFilterToggleBtn.getAttribute('data-filter-label-open');
            if (lo) {
                navMobileFilterToggleBtn.setAttribute('aria-label', lo);
                navMobileFilterToggleBtn.title = lo;
            }
        }
        if (navMobileFilterTitleEl) navMobileFilterTitleEl.setAttribute('aria-expanded', 'true');
        var customerLabel = document.getElementById('customer-filter-text');
        var ml = document.getElementById('mobile-sheet-customer-label');
        if (ml && customerLabel) ml.textContent = customerLabel.textContent.trim() || 'Alle Kunden';
        var assigneeInput = document.getElementById('assignee-filter');
        var assigneeSelect = document.getElementById('mobile-sheet-assignee-select');
        if (assigneeSelect && assigneeInput) {
            var av = assigneeInput.value || '';
            assigneeSelect.value = av;
            if (av && !Array.from(assigneeSelect.options).some(function(o) { return String(o.value) === String(av); })) {
                assigneeSelect.value = '';
            }
        }
        var statusInput = document.getElementById('status-filter');
        var statusSelect = document.getElementById('mobile-sheet-status-select');
        if (statusSelect && statusInput) statusSelect.value = statusInput.value || 'offen_combined';
    }
    /**
     * @param {boolean} [animated] – true: Panel nach oben wegwischen (Nav-Pfeil/Titel), false/omit: sofort
     */
    function closeMobileFilterSheet(animated) {
        if (!mobileFilterSheet || !mobileFilterSheetPanel || !mobileFilterSheetBackdrop) return;
        if (mobileFilterSheet.getAttribute('aria-hidden') === 'true') return;
        if (!animated) {
            finishCloseMobileFilterSheet();
            return;
        }
        if (mobileFilterSheetClosingAnimated) return;
        mobileFilterSheetClosingAnimated = true;
        mobileFilterSheetBackdrop.style.pointerEvents = 'none';
        mobileFilterSheetBackdrop.style.transition = 'opacity 0.28s ease-out';
        mobileFilterSheetBackdrop.classList.remove('opacity-100');
        mobileFilterSheetPanel.style.transition = 'transform 0.32s cubic-bezier(0.32, 0.72, 0, 1)';
        mobileFilterSheetPanel.style.transform = 'translateY(-100%)';
        var done = false;
        function onTransitionEnd(e) {
            if (done) return;
            if (e && e.target !== mobileFilterSheetPanel) return;
            if (e && e.propertyName && e.propertyName !== 'transform') return;
            done = true;
            if (mobileFilterSheetCloseAnimCleanup) {
                mobileFilterSheetCloseAnimCleanup();
                mobileFilterSheetCloseAnimCleanup = null;
            }
            finishCloseMobileFilterSheet();
        }
        var fallbackMs = 380;
        var tid = setTimeout(function() { onTransitionEnd(null); }, fallbackMs);
        mobileFilterSheetCloseAnimCleanup = function() {
            mobileFilterSheetPanel.removeEventListener('transitionend', onTransitionEnd);
            clearTimeout(tid);
        };
        mobileFilterSheetPanel.addEventListener('transitionend', onTransitionEnd);
    }
    (function() {
        var handle = document.getElementById('mobileFilterSheetHandle');
        var scrollEl = document.getElementById('mobileFilterSheetScroll');
        var panel = document.getElementById('mobileFilterSheetPanel');
        if (!panel) return;

        function resetPanelTransform() {
            panel.style.transition = '';
            panel.style.transform = '';
        }

        /**
         * Nur nach oben wischen: Panel mit negativem translateY unter die Nav (hoeherer z-index) schieben und schließen.
         * requireScrollTopZero: Inhaltsliste – nicht mit Scrollen kollidieren.
         */
        function bindVerticalDismiss(el, opts) {
            opts = opts || {};
            var requireScrollTopZero = !!opts.requireScrollTopZero;
            var startY = 0;
            var startTime = 0;
            var currentY = 0;
            var active = false;
            var scrollBlocked = false;

            el.addEventListener('touchstart', function(e) {
                if (!e.touches || e.touches.length !== 1) return;
                startY = e.touches[0].clientY;
                startTime = Date.now();
                currentY = startY;
                active = true;
                scrollBlocked = requireScrollTopZero && scrollEl && scrollEl.scrollTop > 0;
                panel.style.transition = 'none';
            }, { passive: true });

            el.addEventListener('touchmove', function(e) {
                if (!active || !e.touches || e.touches.length === 0) return;
                currentY = e.touches[0].clientY;
                var dy = currentY - startY;
                if (requireScrollTopZero) {
                    if (scrollEl && scrollEl.scrollTop > 0) {
                        scrollBlocked = true;
                        panel.style.transform = '';
                        return;
                    }
                    if (scrollBlocked) return;
                }
                if (dy >= 0) return;
                e.preventDefault();
                panel.style.transform = 'translateY(' + dy + 'px)';
            }, { passive: false });

            el.addEventListener('touchend', function(e) {
                if (!active) return;
                active = false;
                if (requireScrollTopZero && scrollBlocked) {
                    resetPanelTransform();
                    return;
                }
                var endY = e.changedTouches && e.changedTouches.length ? e.changedTouches[0].clientY : currentY;
                var dy = endY - startY;
                var dt = Date.now() - startTime;
                var velocity = dt > 0 ? dy / dt : 0;
                var closeUp = dy < -80 || velocity < -0.45;
                if (closeUp) {
                    closeMobileFilterSheet();
                } else {
                    resetPanelTransform();
                }
            }, { passive: true });

            el.addEventListener('touchcancel', function() {
                active = false;
                scrollBlocked = false;
                resetPanelTransform();
            }, { passive: true });
        }

        if (handle) bindVerticalDismiss(handle);
        if (scrollEl) bindVerticalDismiss(scrollEl, { requireScrollTopZero: true });
    })();
    function toggleMobileFilterSheetFromNav(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (mobileFilterSheet && mobileFilterSheet.getAttribute('aria-hidden') === 'false') closeMobileFilterSheet(true);
        else openMobileFilterSheet();
    }
    if (navMobileFilterToggleBtn) navMobileFilterToggleBtn.addEventListener('click', toggleMobileFilterSheetFromNav);
    if (navMobileFilterTitleEl) {
        navMobileFilterTitleEl.addEventListener('click', toggleMobileFilterSheetFromNav);
        navMobileFilterTitleEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                toggleMobileFilterSheetFromNav(e);
            }
        });
    }
    if (mobileFilterSheetBackdrop) mobileFilterSheetBackdrop.addEventListener('click', closeMobileFilterSheet);
    (function bindMobileFilterPullDownOpen() {
        var wrap = document.getElementById('mobileTicketsWrap');
        if (!wrap) return;
        var mq = window.matchMedia('(max-width: 1023px)');
        var THRESH = 76;
        var TOP_TOLERANCE = 20;
        var navMobileSearchBtn = document.getElementById('navMobileTicketsSearchBtn');
        var startY = 0;
        var startX = 0;
        var tracking = false;
        var startAtTop = false;
        var pullReady = false;
        /* Mobil scrollt #main-content (fixe Nav), nicht window — sonst ist „oben“ immer true und jede Wischbewegung öffnet den Filter. */
        function pageScrollTop() {
            var mc = document.getElementById('main-content');
            if (mc) return mc.scrollTop;
            return window.pageYOffset || document.documentElement.scrollTop || 0;
        }
        function isAtTop() {
            var wrapTop = 0;
            try { wrapTop = wrap ? (wrap.scrollTop || 0) : 0; } catch (e) {}
            return pageScrollTop() <= TOP_TOLERANCE && wrapTop <= TOP_TOLERANCE;
        }
        function sheetIsOpen() {
            return mobileFilterSheet && mobileFilterSheet.getAttribute('aria-hidden') === 'false';
        }
        function clearNavSearchPullPreview() {
            if (!navMobileSearchBtn) return;
            navMobileSearchBtn.style.transform = '';
            navMobileSearchBtn.style.boxShadow = '';
            navMobileSearchBtn.style.transition = '';
            var icon = navMobileSearchBtn.querySelector('svg');
            if (icon) {
                icon.style.transform = '';
                icon.style.opacity = '';
            }
        }
        function setNavSearchPullPreview(dy) {
            return;
        }
        function triggerPullToSearchOpen() {
            ticketsIgnoreNavSearchClickUntil = Date.now() + 500;
            ticketsSetMobileSearchPanelOpen(true, true);
        }
        wrap.addEventListener('touchstart', function(e) {
            if (!mq.matches || sheetIsOpen()) return;
            if (!e.touches || e.touches.length !== 1) return;
            tracking = true;
            startAtTop = isAtTop();
            pullReady = false;
            startY = e.touches[0].clientY;
            startX = e.touches[0].clientX;
            clearNavSearchPullPreview();
        }, { passive: true });
        wrap.addEventListener('touchmove', function(e) {
            if (!tracking || !startAtTop) return;
            if (!isAtTop()) {
                startAtTop = false;
                clearNavSearchPullPreview();
                return;
            }
            if (!e.touches || e.touches.length === 0) return;
            var dy = e.touches[0].clientY - startY;
            var dx = e.touches[0].clientX - startX;
            if (dy <= 0 || Math.abs(dx) * 1.25 >= Math.abs(dy)) {
                clearNavSearchPullPreview();
                return;
            }
            setNavSearchPullPreview(dy);
            if (dy >= THRESH) pullReady = true;
        }, { passive: true });
        wrap.addEventListener('touchend', function(e) {
            if (!tracking) return;
            tracking = false;
            var dash = document.getElementById('tickets-mobile-dashboard');
            var panelOpen = !!(dash && dash.classList.contains('tickets-mobile-search-panel-open'));
            var tClose = e.changedTouches && e.changedTouches[0];
            if (panelOpen && ticketsMobileSearchIsEmpty() && tClose) {
                var dyClose = tClose.clientY - startY;
                var dxClose = tClose.clientX - startX;
                if (dyClose <= -THRESH && Math.abs(dxClose) * 1.25 < Math.abs(dyClose)) {
                    ticketsSetMobileSearchPanelOpen(false, false);
                    clearNavSearchPullPreview();
                    return;
                }
            }
            if (!startAtTop || sheetIsOpen()) {
                clearNavSearchPullPreview();
                return;
            }
            if (!isAtTop()) {
                clearNavSearchPullPreview();
                return;
            }
            var t = e.changedTouches && e.changedTouches[0];
            if (!t) {
                clearNavSearchPullPreview();
                return;
            }
            var dy = t.clientY - startY;
            var dx = t.clientX - startX;
            var shouldOpen = pullReady || dy >= THRESH;
            if (!shouldOpen || Math.abs(dx) * 1.25 >= Math.abs(dy)) {
                clearNavSearchPullPreview();
                return;
            }
            triggerPullToSearchOpen();
        }, { passive: true });
        wrap.addEventListener('touchcancel', function() {
            tracking = false;
            pullReady = false;
            clearNavSearchPullPreview();
        }, { passive: true });

        /* Fallback für Geräte, bei denen touchmove/touchend im Wrapper nicht zuverlässig feuert. */
        var fbTracking = false;
        var fbStartAtTop = false;
        var fbStartY = 0;
        var fbStartX = 0;
        var fbPullReady = false;
        document.addEventListener('touchstart', function(e) {
            if (!mq.matches || sheetIsOpen()) return;
            if (!e.touches || e.touches.length !== 1) return;
            if (!wrap.contains(e.target)) return;
            fbTracking = true;
            fbStartAtTop = isAtTop();
            fbPullReady = false;
            fbStartY = e.touches[0].clientY;
            fbStartX = e.touches[0].clientX;
        }, { passive: true, capture: true });
        document.addEventListener('touchmove', function(e) {
            if (!fbTracking || !fbStartAtTop) return;
            if (!mq.matches || sheetIsOpen()) return;
            if (!wrap.contains(e.target)) return;
            if (!e.touches || e.touches.length === 0) return;
            var dy = e.touches[0].clientY - fbStartY;
            var dx = e.touches[0].clientX - fbStartX;
            if (dy > 0 && Math.abs(dx) * 1.25 < Math.abs(dy) && dy >= THRESH) fbPullReady = true;
        }, { passive: true, capture: true });
        document.addEventListener('touchend', function(e) {
            if (!fbTracking) return;
            fbTracking = false;
            if (!mq.matches || sheetIsOpen() || !fbStartAtTop) return;
            var t = e.changedTouches && e.changedTouches[0];
            if (!t) return;
            var dy = t.clientY - fbStartY;
            var dx = t.clientX - fbStartX;
            var shouldOpen = fbPullReady || dy >= THRESH;
            if (!shouldOpen || Math.abs(dx) * 1.25 >= Math.abs(dy)) return;
            triggerPullToSearchOpen();
        }, { passive: true, capture: true });
    })();
    (function bindMobileFilterSwipeUpClose() {
        var mq = window.matchMedia('(max-width: 1023px)');
        var THRESH = 76;
        var sheetScroll = document.getElementById('mobileFilterSheetScroll');
        var startY = 0;
        var startX = 0;
        var tracking = false;
        var startedInSheetScroll = false;
        function sheetIsOpen() {
            return mobileFilterSheet && mobileFilterSheet.getAttribute('aria-hidden') === 'false';
        }
        document.addEventListener('touchstart', function(e) {
            if (!mq.matches || !sheetIsOpen()) return;
            if (!e.touches || e.touches.length !== 1) return;
            tracking = true;
            startY = e.touches[0].clientY;
            startX = e.touches[0].clientX;
            startedInSheetScroll = !!(sheetScroll && sheetScroll.contains(e.target));
        }, { passive: true, capture: true });
        document.addEventListener('touchend', function(e) {
            if (!tracking) return;
            tracking = false;
            if (!mq.matches || !sheetIsOpen()) return;
            var t = e.changedTouches && e.changedTouches[0];
            if (!t) return;
            var dy = t.clientY - startY;
            var dx = t.clientX - startX;
            if (dy > -THRESH) return;
            if (Math.abs(dx) * 1.25 >= Math.abs(dy)) return;
            if (startedInSheetScroll && sheetScroll && sheetScroll.scrollTop > 2) return;
            closeMobileFilterSheet(true);
        }, { passive: true, capture: true });
        document.addEventListener('touchcancel', function() {
            tracking = false;
        }, { passive: true, capture: true });
    })();
    var mobileSheetCustomerBtn = document.getElementById('mobile-sheet-customer-btn');
    var mobileSheetAssigneeSelect = document.getElementById('mobile-sheet-assignee-select');
    var mobileSheetStatusSelect = document.getElementById('mobile-sheet-status-select');
    if (mobileSheetCustomerBtn) mobileSheetCustomerBtn.addEventListener('click', function() { document.getElementById('customer-filter-button').click(); });
    if (mobileSheetAssigneeSelect) {
        mobileSheetAssigneeSelect.addEventListener('change', function() {
            var assigneeValue = this.value;
            var assigneeFilterInput = document.getElementById('assignee-filter');
            var assigneeFilterText = document.getElementById('assignee-filter-text');
            var opt = this.options[this.selectedIndex];
            var label = (opt && opt.textContent) ? opt.textContent.trim() : 'Alle Bearbeiter';
            if (assigneeFilterInput) assigneeFilterInput.value = assigneeValue;
            if (assigneeFilterText) assigneeFilterText.textContent = label || 'Alle Bearbeiter';
            if (typeof updateAssigneeFilterButtonState === 'function') updateAssigneeFilterButtonState();
            var curHash = (window.location.hash || '').replace(/^#/, '');
            if (typeof HASH_FILTERS !== 'undefined' && HASH_FILTERS.indexOf(curHash) !== -1) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search);
            }
            if (typeof loadTickets === 'function') loadTickets();
            if (typeof saveFiltersState === 'function') saveFiltersState();
        });
    }
    if (mobileSheetStatusSelect) {
        mobileSheetStatusSelect.addEventListener('change', function() {
            var statusValue = this.value;
            var statusFilterInput = document.getElementById('status-filter');
            var statusFilterText = document.getElementById('status-filter-text');
            var statusLabels = { 'offen_combined': 'Offen', 'neu': 'Neu', 'in_bearbeitung': 'In Bearbeitung', 'warteschlange': 'Wartend', 'bestellung_offen': 'Bestellung offen', 'geschlossen': 'Geschlossen', 'archiv': 'Archiv', 'ohne_bearbeitungszeit': 'Ohne Bearbeitungszeit' };
            if (statusFilterInput) statusFilterInput.value = statusValue;
            if (statusFilterText) statusFilterText.textContent = statusLabels[statusValue] || statusValue;
            try { localStorage.setItem('ticketsStatusFilter', statusValue); } catch (e) {}
            var curHash = (window.location.hash || '').replace(/^#/, '');
            if (typeof HASH_FILTERS !== 'undefined' && HASH_FILTERS.indexOf(curHash) !== -1) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search);
            }
            if (typeof loadTickets === 'function') loadTickets();
            if (typeof saveFiltersState === 'function') saveFiltersState();
        });
    }
    
    // Nachrichtentyp-Button-Auswahl Event Listener
    const messageTypeButtons = document.querySelectorAll('.message-type-btn');
    const messageTypeSelect = document.getElementById('message-type-select');
    
    function updateActiveButton(activeButton) {
        // Entferne aktive Klasse von allen Buttons
        messageTypeButtons.forEach(btn => {
            btn.classList.remove('bg-primary-250', 'text-white', 'border-primary-250', 'z-10', 'dark:bg-primary-420', 'dark:text-primary-480', 'dark:border-primary-420');
            btn.classList.add('text-gray-700', 'dark:text-primary-200', 'bg-white', 'dark:bg-primary-300', 'border-gray-300', 'dark:border-primary-320', 'hover:bg-gray-50', 'dark:hover:bg-primary-140');
        });
        
        // Füge aktive Klasse zum aktiven Button hinzu
        if (activeButton) {
            activeButton.classList.add('bg-primary-250', 'text-white', 'border-primary-250', 'z-10', 'dark:bg-primary-420', 'dark:text-primary-480', 'dark:border-primary-420');
            activeButton.classList.remove('text-gray-700', 'dark:text-primary-200', 'bg-white', 'dark:bg-primary-300', 'border-gray-300', 'dark:border-primary-320', 'hover:bg-gray-50', 'dark:hover:bg-primary-140');
        }
    }
    
    messageTypeButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Aktualisiere hidden input
            const messageType = this.getAttribute('data-message-type');
            if (messageTypeSelect) {
                messageTypeSelect.value = messageType;
            }
            // Aktualisiere aktiven Button
            updateActiveButton(this);
            // Bei Bestellung und Ticket mit Gerät: Modal für Verbrauchsmaterialien öffnen
            if (messageType === 'bestellung' && selectedChatTicket && selectedChatTicket.device_id) {
                openOrderConsumablesModal();
            }
        });
    });
    
    // Initialisiere aktiven Button (erster Button = "nachricht")
    if (messageTypeButtons.length > 0) {
        const firstButton = Array.from(messageTypeButtons).find(btn => btn.getAttribute('data-message-type') === 'nachricht');
        if (firstButton) {
            updateActiveButton(firstButton);
        }
    }
    
    // Nachricht senden Event Listener
    const sendMessageBtn = document.getElementById('send-message-btn');
    const chatMessageInput = document.getElementById('chat-message-input');
    const attachFileBtn = document.getElementById('attach-file-btn');
    
    if (sendMessageBtn && chatMessageInput) {
        sendMessageBtn.addEventListener('click', sendChatMessage);
        chatMessageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
        
        // Erweitere Textarea beim Klick/Focus nach oben
        chatMessageInput.addEventListener('focus', function() {
            if (this.rows === 1) {
                this.rows = 4;
            }
        });
        
        // Optional: Verkleinere wieder beim Blur, wenn leer
        chatMessageInput.addEventListener('blur', function() {
            if (this.value.trim() === '' && this.rows > 1) {
                this.rows = 1;
            }
        });
    }
    
    // Bestellung-Button (öffnet Modal – wie view.php, nicht in Nachrichtentyp-Gruppe)
    const openOrderModalBtn = document.getElementById('open-order-modal-btn');
    if (openOrderModalBtn) {
        openOrderModalBtn.addEventListener('click', function() {
            if (selectedChatTicket && selectedChatTicket.device_id) {
                openOrderConsumablesModal();
            } else {
                if (typeof showToast === 'function') showToast('Bitte wählen Sie einen Ticket mit zugeordnetem Gerät.', 'info');
            }
        });
    }
    
    // Anhang-Button Event Listener (öffnet Modal und erstellt Kommentar wenn nötig)
    if (attachFileBtn) {
        attachFileBtn.addEventListener('click', function() {
            if (!selectedChatTicket) return;
            
            const messageInput = document.getElementById('chat-message-input');
            const messageTypeSelect = document.getElementById('message-type-select');
            const message = messageInput.value.trim();
            const nachrichtentyp = messageTypeSelect ? messageTypeSelect.value : 'nachricht';
            
            // Wenn bereits Text vorhanden ist, erstelle Kommentar und öffne Modal
            if (message) {
                fetch(commentsApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
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
                        // Nachricht leeren
                        if (messageInput) {
                            messageInput.value = '';
                        }
                        // Modal öffnen mit der neuen Kommentar-ID
                        openAttachmentModal(data.comment_id);
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
                // Nur Datei-Upload: Kein Kommentar vorab erstellen (vermeidet leere Nachricht), Modal direkt öffnen
                openAttachmentModal(null);
            }
        });
    }
    
    // Drag & Drop Event Listener für Attachment Modal
    const dropzoneLabel = document.getElementById('dropzone-label');
    const dropzoneFile = document.getElementById('dropzone-file');
    
    if (dropzoneLabel && dropzoneFile) {
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Drag & Drop Events
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
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                // DataTransfer FileList in FileList umwandeln
                const dataTransfer = new DataTransfer();
                Array.from(files).forEach(file => dataTransfer.items.add(file));
                dropzoneFile.files = dataTransfer.files;
                handleFileSelect(files);
            }
        }, false);
        
        // File Input Change Event
        dropzoneFile.addEventListener('change', function(e) {
            if (e.target.files && e.target.files.length > 0) {
                handleFileSelect(e.target.files);
            }
        });
    }
    
    // Suche aktiv: Hervorhebung wenn Suchbegriff eingegeben
    function updateSearchActiveState() {
        const wrapper = document.getElementById('search-wrapper');
        const searchEl = document.getElementById('search');
        if (!wrapper || !searchEl) return;
        wrapper.classList.toggle('search-active', searchEl.value.trim() !== '');
    }

    function isCustomerModalMobileLayout() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }
    /** @returns {number|null} Gesetzte Firmen-ID aus der Nav, oder null wenn „Alle Firmen“. */
    function getNavSelectedCompanyId() {
        try {
            var raw = localStorage.getItem('selectedUserOption');
            if (!raw) return null;
            var data = JSON.parse(raw);
            var id = data.id;
            if (id === '0' || id === 0 || id === '' || id === undefined || id === null) return null;
            var n = parseInt(id, 10);
            return isNaN(n) ? null : n;
        } catch (e) {
            return null;
        }
    }
    /** Firmen-Badges nur anzeigen, wenn in der Nav keine einzelne Firma gewählt ist (sonst redundant). */
    function updateCustomerModalCompanyBadgeVisibility() {
        var navCid = getNavSelectedCompanyId();
        var hide = navCid !== null;
        document.querySelectorAll('#customerModal .customer-modal-company-badge').forEach(function(el) {
            el.classList.toggle('hidden', hide);
        });
    }
    function updateCustomerModalScrollFades() {
        var modal = document.getElementById('customerModal');
        if (!modal || modal.classList.contains('hidden')) return;
        var list = document.getElementById('customersTableBody');
        var fadeTop = modal.querySelector('.customer-modal-list-top-fade');
        var fadeBottom = modal.querySelector('.customer-modal-list-bottom-fade');
        var eps = 4;
        if (list) {
            var moreBelow = list.scrollHeight - list.scrollTop - list.clientHeight > eps;
            var moreAbove = list.scrollTop > eps;
            if (fadeBottom) fadeBottom.classList.toggle('customer-modal-scroll-fade-visible', moreBelow);
            if (fadeTop) fadeTop.classList.toggle('customer-modal-scroll-fade-visible', moreAbove);
        } else {
            if (fadeBottom) fadeBottom.classList.remove('customer-modal-scroll-fade-visible');
            if (fadeTop) fadeTop.classList.remove('customer-modal-scroll-fade-visible');
        }
    }
    (function bindCustomerModalScrollFades() {
        var list = document.getElementById('customersTableBody');
        if (list) list.addEventListener('scroll', updateCustomerModalScrollFades, { passive: true });
        window.addEventListener('resize', updateCustomerModalScrollFades, { passive: true });
    })();
    function forceCloseCustomerModalInstant() {
        var m = document.getElementById('customerModal');
        if (m) {
            m.classList.add('hidden');
            m.classList.remove('customer-modal-visible');
        }
        document.body.classList.remove('customer-modal-open');
        document.removeEventListener('keydown', handleCustomerModalEscape);
        var searchInput = document.getElementById('customerSearchInput');
        if (searchInput) searchInput.value = '';
        updateCustomerListForSelectedCompany();
    }
    function openCustomerModalFromUI() {
        var customerModal = document.getElementById('customerModal');
        if (!customerModal) return;
        var custPanel = customerModal.querySelector('.customer-modal-panel');
        if (custPanel) {
            custPanel.style.transition = '';
            custPanel.style.transform = '';
        }
        customerModal.classList.remove('hidden');
        customerModal.classList.remove('customer-modal-visible');
        document.body.classList.add('customer-modal-open');
        document.addEventListener('keydown', handleCustomerModalEscape);
        updateCustomerListForSelectedCompany();
        updateCustomerModalCompanyBadgeVisibility();
        sortCustomerRowsAlphabetically();
        var searchInput = document.getElementById('customerSearchInput');
        if (searchInput) setTimeout(function() { searchInput.focus(); }, 100);
        if (isCustomerModalMobileLayout()) {
            void customerModal.offsetWidth;
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    customerModal.classList.add('customer-modal-visible');
                    updateCustomerModalScrollFades();
                    setTimeout(updateCustomerModalScrollFades, 360);
                });
            });
        } else {
            customerModal.classList.add('customer-modal-visible');
            requestAnimationFrame(function() {
                updateCustomerModalScrollFades();
            });
        }
    }

    (function bindCustomerModalSheetSwipe() {
        var handle = document.getElementById('customerModalSheetHandle');
        var panel = document.querySelector('#customerModal .customer-modal-panel');
        if (!panel || !handle) return;

        function resetPanelTransform() {
            panel.style.transition = '';
            panel.style.transform = '';
        }

        /* Nur Griff: Wischen in der Kundenliste darf nicht preventDefault auslösen (sonst kein Scroll). */
        function bindHandleVerticalDismiss(el) {
            var startY = 0;
            var startTime = 0;
            var currentY = 0;
            var active = false;

            el.addEventListener('touchstart', function(e) {
                if (!window.matchMedia('(max-width: 1023px)').matches) return;
                if (!e.touches || e.touches.length !== 1) return;
                startY = e.touches[0].clientY;
                startTime = Date.now();
                currentY = startY;
                active = true;
                panel.style.transition = 'none';
            }, { passive: true });

            el.addEventListener('touchmove', function(e) {
                if (!active || !e.touches || e.touches.length === 0) return;
                currentY = e.touches[0].clientY;
                var dy = currentY - startY;
                if (dy >= 0) return;
                e.preventDefault();
                panel.style.transform = 'translateY(' + dy + 'px)';
            }, { passive: false });

            el.addEventListener('touchend', function(e) {
                if (!active) return;
                active = false;
                var endY = e.changedTouches && e.changedTouches.length ? e.changedTouches[0].clientY : currentY;
                var dy = endY - startY;
                var dt = Date.now() - startTime;
                var velocity = dt > 0 ? dy / dt : 0;
                var closeUp = dy < -80 || velocity < -0.45;
                if (closeUp) {
                    resetPanelTransform();
                    forceCloseCustomerModalInstant();
                } else {
                    resetPanelTransform();
                }
            }, { passive: true });

            el.addEventListener('touchcancel', function() {
                active = false;
                resetPanelTransform();
            }, { passive: true });
        }

        bindHandleVerticalDismiss(handle);
    })();

    // Kunde-Filter Button Event Listener (nur wenn vorhanden)
    const customerFilterButton = document.getElementById('customer-filter-button');
    if (customerFilterButton) {
        customerFilterButton.addEventListener('click', function() {
            const assigneeMenu = document.getElementById('assignee-filter-menu');
            if (assigneeMenu) assigneeMenu.classList.add('hidden');
            const statusFilterMenu = document.getElementById('status-filter-menu');
            if (statusFilterMenu) statusFilterMenu.classList.add('hidden');
            openCustomerModalFromUI();
        });
    }
    
    function closeDisplayDropdown() {
        closeFilterDropdownPortal(document.getElementById('display-dropdown-menu'), document.getElementById('display-dropdown-container'));
        const displayBtn = document.getElementById('display-dropdown-button');
        if (displayBtn) displayBtn.setAttribute('aria-expanded', 'false');
    }

    // Anzeige-Dropdown (Ansicht + Sortierung)
    const displayDropdownContainer = document.getElementById('display-dropdown-container');
    const displayDropdownButton = document.getElementById('display-dropdown-button');
    const displayDropdownMenu = document.getElementById('display-dropdown-menu');
    if (displayDropdownButton && displayDropdownMenu && displayDropdownContainer) {
        function positionDisplayDropdown() {
            positionFilterDropdown(displayDropdownMenu, displayDropdownButton, { minWidth: 288, alignRight: true });
        }
        displayDropdownButton.addEventListener('click', function(e) {
            e.stopPropagation();
            forceCloseCustomerModalInstant();
            closeFilterDropdownPortal(document.getElementById('status-filter-menu'), document.getElementById('status-filter-container'));
            closeFilterDropdownPortal(document.getElementById('assignee-filter-menu'), document.getElementById('assignee-filter-container'));
            const isHidden = displayDropdownMenu.classList.contains('hidden');
            if (isHidden) {
                updateDisplayViewSegments();
                updateDisplaySortDirectionSegments();
                openFilterDropdownAsPortal(displayDropdownMenu, displayDropdownButton, { minWidth: 288, alignRight: true });
                displayDropdownButton.setAttribute('aria-expanded', 'true');
            } else {
                closeDisplayDropdown();
            }
        });
        document.querySelectorAll('.display-view-option').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const view = this.getAttribute('data-view');
                if (view) switchView(view);
            });
        });
        document.addEventListener('click', function(e) {
            const sortMenu = document.getElementById('sort-dropdown-menu');
            const sortContainer = document.getElementById('sort-dropdown-container');
            if (displayDropdownMenu.classList.contains('hidden')) return;
            if (displayDropdownContainer.contains(e.target)) return;
            if (displayDropdownMenu.contains(e.target)) return;
            if (sortMenu && !sortMenu.classList.contains('hidden') && sortMenu.contains(e.target)) return;
            if (sortContainer && sortContainer.contains(e.target)) return;
            closeDisplayDropdown();
            closeFilterDropdownPortal(sortMenu, sortContainer);
        });
        initDisplaySortDirectionSegments();
        window.addEventListener('scroll', positionDisplayDropdown, true);
        window.addEventListener('resize', positionDisplayDropdown);
    }

    // Sortier-Dropdown Event Listener (innerhalb Anzeige-Menü)
    const sortDropdownContainer = document.getElementById('sort-dropdown-container');
    // Status-Labels Mapping (global verfügbar)
    const statusLabels = {
        'offen_combined': 'Offen',
        'neu': 'Neu',
        'in_bearbeitung': 'In Bearbeitung',
        'warteschlange': 'Wartend',
        'bestellung_offen': 'Bestellung offen',
        'geschlossen': 'Geschlossen',
        'archiv': 'Archiv',
        'ohne_bearbeitungszeit': 'Ohne Bearbeitungszeit'
    };
    
    const sortDropdownButton = document.getElementById('sort-dropdown-button');
    const sortDropdownMenu = document.getElementById('sort-dropdown-menu');
    const sortDropdownText = document.getElementById('sort-dropdown-text');
    const sortSelection = document.getElementById('sort-selection');
    
    if (sortDropdownButton && sortDropdownMenu && sortDropdownContainer) {
        // Dropdown öffnen/schließen
        function positionSortDropdown() {
            positionFilterDropdown(sortDropdownMenu, sortDropdownButton);
        }
        sortDropdownButton.addEventListener('click', function(e) {
            e.stopPropagation();
            forceCloseCustomerModalInstant();
            closeFilterDropdownPortal(document.getElementById('status-filter-menu'), document.getElementById('status-filter-container'));
            closeFilterDropdownPortal(document.getElementById('assignee-filter-menu'), document.getElementById('assignee-filter-container'));
            const isHidden = sortDropdownMenu.classList.contains('hidden');
            if (isHidden) {
                openFilterDropdownAsPortal(sortDropdownMenu, sortDropdownButton, { minWidth: 160 });
            } else {
                closeFilterDropdownPortal(sortDropdownMenu, sortDropdownContainer);
            }
        });
        
        // Sortier-Optionen auswählen
        const sortOptions = sortDropdownMenu.querySelectorAll('.sort-option');
        sortOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                const sortType = this.getAttribute('data-sort');
                
                // Sortierung anwenden (Richtung bleibt bestehen)
                if (sortColumn === sortType) {
                    // Wenn bereits nach dieser Spalte sortiert, Richtung bleibt gleich
                    // (Richtung wird nur über den separaten Button geändert)
                } else {
                    // Neue Spalte, Standard-Richtung verwenden
                    sortColumn = sortType;
                    sortDirection = (sortType === 'naechster_termin') ? 'asc' : 'desc';
                }
                
                // Dropdown soll NICHT die Richtung toggeln
                sortTickets(sortColumn, true, false);
                
                // UI aktualisieren
                if (sortSelection) {
                    sortSelection.value = sortType;
                }
                closeFilterDropdownPortal(sortDropdownMenu, sortDropdownContainer);
                saveFiltersState();
            });
        });
        
        // Dropdown schließen beim Klick außerhalb (Menü kann in body sein)
        document.addEventListener('click', function(e) {
            if (isClickOutsideDropdown(sortDropdownContainer, sortDropdownMenu, e.target)) {
                closeFilterDropdownPortal(sortDropdownMenu, sortDropdownContainer);
            }
        });
        window.addEventListener('scroll', positionSortDropdown, true);
        window.addEventListener('resize', positionSortDropdown);
    }

    // Gemeinsame Positionierung für Filter-Dropdowns: fixed + Portal (an body), damit sie nie abgeschnitten werden
    function positionFilterDropdown(menuEl, buttonEl, options) {
        if (!menuEl || !buttonEl || menuEl.classList.contains('hidden')) return;
        const rect = buttonEl.getBoundingClientRect();
        const vh = window.innerHeight;
        const vw = window.innerWidth;
        const gap = 4;
        const maxMenuH = 320;
        const spaceBelow = vh - rect.bottom - gap;
        const spaceAbove = rect.top - gap;
        const openAbove = spaceBelow < maxMenuH && spaceAbove > spaceBelow;
        menuEl.style.position = 'fixed';
        menuEl.style.marginTop = '0';
        menuEl.style.marginBottom = '0';
        const minW = (options && options.minWidth) ? options.minWidth : 0;
        const menuW = Math.max(rect.width, minW);
        menuEl.style.width = menuW + 'px';
        menuEl.style.minWidth = '';
        menuEl.style.maxWidth = '';
        let left = (options && options.alignRight) ? (rect.right - menuW) : rect.left;
        if (left + menuW > vw) left = vw - menuW;
        if (left < 0) left = 0;
        menuEl.style.left = left + 'px';
        if (openAbove) {
            menuEl.style.bottom = (vh - rect.top + gap) + 'px';
            menuEl.style.top = 'auto';
            menuEl.style.maxHeight = Math.min(maxMenuH, spaceAbove) + 'px';
        } else {
            menuEl.style.top = (rect.bottom + gap) + 'px';
            menuEl.style.bottom = 'auto';
            menuEl.style.maxHeight = Math.min(maxMenuH, spaceBelow) + 'px';
        }
    }
    function openFilterDropdownAsPortal(menuEl, buttonEl, options) {
        if (!menuEl || !buttonEl) return;
        if (!menuEl._dropdownRestore) {
            menuEl._dropdownRestore = { parent: menuEl.parentNode, nextSibling: menuEl.nextSibling };
            document.body.appendChild(menuEl);
        }
        menuEl.classList.remove('hidden');
        setTimeout(() => positionFilterDropdown(menuEl, buttonEl, options), 10);
    }
    function closeFilterDropdownPortal(menuEl, containerEl) {
        if (!menuEl) return;
        menuEl.classList.add('hidden');
        if (menuEl._dropdownRestore) {
            const { parent, nextSibling } = menuEl._dropdownRestore;
            if (parent) {
                if (nextSibling) parent.insertBefore(menuEl, nextSibling);
                else parent.appendChild(menuEl);
            }
            menuEl._dropdownRestore = null;
        }
    }
    function isClickOutsideDropdown(containerEl, menuEl, target) {
        return containerEl && !containerEl.contains(target) && menuEl && !menuEl.contains(target);
    }

    // Status-Filter Dropdown
    const statusFilterButton = document.getElementById('status-filter-button');
    const statusFilterMenu = document.getElementById('status-filter-menu');
    const statusFilterText = document.getElementById('status-filter-text');
    const statusFilterInput = document.getElementById('status-filter');
    const statusFilterContainer = document.getElementById('status-filter-container');
    
    if (statusFilterButton && statusFilterMenu && statusFilterContainer) {
        function positionStatusDropdown() {
            positionFilterDropdown(statusFilterMenu, statusFilterButton);
        }
        
        // Dropdown öffnen/schließen (Portal an body); nur eines gleichzeitig offen
        statusFilterButton.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = statusFilterMenu.classList.contains('hidden');
            forceCloseCustomerModalInstant();
            closeFilterDropdownPortal(document.getElementById('assignee-filter-menu'), document.getElementById('assignee-filter-container'));
            closeFilterDropdownPortal(document.getElementById('sort-dropdown-menu'), document.getElementById('sort-dropdown-container'));
            closeDisplayDropdown();
            if (isHidden) {
                openFilterDropdownAsPortal(statusFilterMenu, statusFilterButton);
            } else {
                closeFilterDropdownPortal(statusFilterMenu, statusFilterContainer);
            }
        });
        
        window.addEventListener('scroll', positionStatusDropdown, true);
        window.addEventListener('resize', positionStatusDropdown);
        
        // Status-Optionen auswählen
        const statusOptions = statusFilterMenu.querySelectorAll('.status-option');
        statusOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                const statusValue = this.getAttribute('data-status');
                const statusLabel = statusLabels[statusValue] || statusValue;
                
                // Status setzen
                if (statusFilterInput) {
                    statusFilterInput.value = statusValue;
                }
                if (statusFilterText) {
                    statusFilterText.textContent = statusLabel;
                }
                
                closeFilterDropdownPortal(statusFilterMenu, statusFilterContainer);
                
                // #-Filter entfernen, damit der gewählte Status-Filter greift
                const curHash = (window.location.hash || '').replace(/^#/, '');
                if (HASH_FILTERS.includes(curHash)) {
                    window.history.replaceState(null, '', window.location.pathname + window.location.search);
                }
                
                // Status in localStorage speichern
                localStorage.setItem('ticketsStatusFilter', statusValue);
                
                // Tickets neu laden und filtern
                loadTickets();
                saveFiltersState();
                updateStatusFilterButtonState();
            });
        });
        
        // Dropdown schließen beim Klick außerhalb (Menü kann in body sein)
        document.addEventListener('click', function(e) {
            if (isClickOutsideDropdown(statusFilterContainer, statusFilterMenu, e.target)) {
                closeFilterDropdownPortal(statusFilterMenu, statusFilterContainer);
            }
        });
    }

    // Funktion zum Prüfen, ob die ausgewählte Firma Kunden hat
    function updateCustomerFilterVisibility() {
        const customerFilterContainer = document.getElementById('customer-filter-container');
        if (!customerFilterContainer) {
            return;
        }
        
        // Aktuelle Firmenauswahl aus localStorage lesen
        const savedSelection = localStorage.getItem('selectedUserOption');
        let currentCompanyId = null;
        
        if (savedSelection) {
            try {
                const data = JSON.parse(savedSelection);
                if (data.id && data.id !== '0') {
                    currentCompanyId = parseInt(data.id);
                }
            } catch (e) {
                console.error('Fehler beim Lesen der Firmenauswahl', e);
            }
        }
        
        // Wenn keine Firma ausgewählt ist, Filter anzeigen (alle Kunden)
        if (!currentCompanyId) {
            customerFilterContainer.style.display = '';
            return;
        }
        
        // Prüfen, ob die Firma Kunden hat
        const customerRows = document.querySelectorAll('.select-customer-row');
        let hasCustomers = false;
        
        customerRows.forEach(row => {
            const customerId = row.getAttribute('data-customer-id');
            // "Alle Kunden" Option ignorieren
            if (!customerId || customerId === '') {
                return;
            }
            
            const customerCompanyId = row.getAttribute('data-company-id');
            if (customerCompanyId && parseInt(customerCompanyId) === currentCompanyId) {
                hasCustomers = true;
            }
        });
        
        // Filter nur anzeigen, wenn die Firma Kunden hat
        if (hasCustomers) {
            customerFilterContainer.style.display = '';
        } else {
            customerFilterContainer.style.display = 'none';
            // Filter zurücksetzen, wenn versteckt
            const customerFilter = document.getElementById('customer-filter');
            const customerFilterText = document.getElementById('customer-filter-text');
            if (customerFilter) {
                customerFilter.value = '';
            }
            if (customerFilterText) {
                customerFilterText.textContent = 'Alle Kunden';
            }
            updateCustomerFilterButtonState();
        }
    }
    
    // Funktion zum Aktualisieren der Kundenliste basierend auf ausgewählter Firma
    function updateCustomerListForSelectedCompany() {
        // Aktuelle Firmenauswahl aus localStorage lesen
        const savedSelection = localStorage.getItem('selectedUserOption');
        let currentCompanyId = null;
        
        if (savedSelection) {
            try {
                const data = JSON.parse(savedSelection);
                if (data.id && data.id !== '0') {
                    currentCompanyId = parseInt(data.id);
                }
            } catch (e) {
                console.error('Fehler beim Lesen der Firmenauswahl', e);
            }
        }
        
        // Alle Kunden-Zeilen durchgehen
        const customerRows = document.querySelectorAll('.select-customer-row');
        customerRows.forEach(row => {
            // "Alle Kunden" Option immer anzeigen
            const customerId = row.getAttribute('data-customer-id');
            if (!customerId || customerId === '') {
                row.style.display = '';
                return;
            }
            
            // Wenn keine Firma ausgewählt ist, alle Kunden anzeigen
            if (!currentCompanyId) {
                row.style.display = '';
                return;
            }
            
            // Kunde zur ausgewählten Firma gehört?
            const customerCompanyId = row.getAttribute('data-company-id');
            if (customerCompanyId && parseInt(customerCompanyId) === currentCompanyId) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        updateCustomerModalScrollFades();
    }

    // Kunden im Modal alphabetisch sortieren ("Alle Kunden" bleibt immer oben)
    function sortCustomerRowsAlphabetically() {
        const container = document.getElementById('customersTableBody');
        if (!container) return;

        const rows = Array.from(container.querySelectorAll('.select-customer-row'));
        if (rows.length <= 1) {
            requestAnimationFrame(updateCustomerModalScrollFades);
            return;
        }

        const allCustomersRow = rows.find(row => {
            const id = row.getAttribute('data-customer-id');
            return !id || id === '';
        });
        const normalRows = rows.filter(row => row !== allCustomersRow);

        normalRows.sort((a, b) => {
            const aName = (a.getAttribute('data-customer-display-name') || a.getAttribute('data-customer-name') || '').trim();
            const bName = (b.getAttribute('data-customer-display-name') || b.getAttribute('data-customer-name') || '').trim();
            return aName.localeCompare(bName, 'de', { sensitivity: 'base', numeric: true });
        });

        if (allCustomersRow) container.appendChild(allCustomersRow);
        normalRows.forEach(row => container.appendChild(row));
        requestAnimationFrame(updateCustomerModalScrollFades);
    }
    
    function normalizeCustomerSearchWs(s) {
        return String(s).replace(/\s+/g, ' ').trim();
    }
    function customerSearchTextMatches(hay, needle) {
        const h = normalizeCustomerSearchWs(String(hay).toLowerCase());
        const n = normalizeCustomerSearchWs(String(needle).toLowerCase());
        if (!n) return true;
        if (h.includes(n)) return true;
        const hNo = h.replace(/\s+/g, '');
        const nNo = n.replace(/\s+/g, '');
        if (nNo.length >= 2 && hNo.includes(nNo)) return true;
        return false;
    }

    // Kunden-Suche Event Listener
    const customerSearchInput = document.getElementById('customerSearchInput');
    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', function() {
            const searchTerm = this.value;
            const customerRows = document.querySelectorAll('.select-customer-row');
            
            // Aktuelle Firmenauswahl aus localStorage lesen
            const savedSelection = localStorage.getItem('selectedUserOption');
            let currentCompanyId = null;
            
            if (savedSelection) {
                try {
                    const data = JSON.parse(savedSelection);
                    if (data.id && data.id !== '0') {
                        currentCompanyId = parseInt(data.id);
                    }
                } catch (e) {
                    console.error('Fehler beim Lesen der Firmenauswahl', e);
                }
            }
            
            customerRows.forEach(row => {
                const customerSearch = row.getAttribute('data-customer-name') || '';
                const customerId = row.getAttribute('data-customer-id');
                
                // Suchfilter anwenden (Leerzeichen normalisiert, „1zu1“ findet „1 zu 1“)
                const matchesSearch = customerSearchTextMatches(customerSearch, searchTerm);
                
                // Firmenfilter anwenden (außer bei "Alle Kunden")
                let matchesCompany = true;
                if (currentCompanyId && customerId && customerId !== '') {
                    const customerCompanyId = row.getAttribute('data-company-id');
                    matchesCompany = customerCompanyId && parseInt(customerCompanyId) === currentCompanyId;
                }
                
                // "Alle Kunden" Option immer anzeigen, wenn Suche passt
                if (!customerId || customerId === '') {
                    row.style.display = matchesSearch ? '' : 'none';
                } else {
                    // Beide Filter müssen passen
                    row.style.display = (matchesSearch && matchesCompany) ? '' : 'none';
                }
            });
            sortCustomerRowsAlphabetically();
            updateCustomerModalScrollFades();
        });
    }
    
    // Kunde aus Modal auswählen
    document.querySelectorAll('.select-customer-row').forEach(row => {
        row.addEventListener('click', function() {
            const customerId = this.getAttribute('data-customer-id') || '';
            const customerName = this.getAttribute('data-customer-display-name') || 'Alle Kunden';
            
            // Filter setzen
            const customerFilter = document.getElementById('customer-filter');
            const customerFilterText = document.getElementById('customer-filter-text');
            
            if (customerFilter) {
                customerFilter.value = customerId || '';
            }
            
            if (customerFilterText) {
                customerFilterText.textContent = customerName;
            }
            
            updateCustomerFilterButtonState();
            // Modal schließen
            closeCustomerModal();
            
            // Filter anwenden
            filterTickets();
            saveFiltersState();
        });
    });
    
    // Modal schließen Button
    const closeCustomerModalBtn = document.getElementById('closeCustomerModalBtn');
    if (closeCustomerModalBtn) {
        closeCustomerModalBtn.addEventListener('click', closeCustomerModal);
    }
    
    // Modal schließen bei Klick auf Overlay
    const customerModalOverlay = document.getElementById('customerModalOverlay');
    if (customerModalOverlay) {
        customerModalOverlay.addEventListener('click', closeCustomerModal);
    }
    
    // Modal schließen mit ESC-Taste
    function handleCustomerModalEscape(e) {
        if (e.key === 'Escape') {
            const customerModal = document.getElementById('customerModal');
            if (customerModal && !customerModal.classList.contains('hidden')) {
                closeCustomerModal();
                document.removeEventListener('keydown', handleCustomerModalEscape);
            }
        }
    }
    
    function closeCustomerModal() {
        document.removeEventListener('keydown', handleCustomerModalEscape);
        var customerModal = document.getElementById('customerModal');
        if (!customerModal || customerModal.classList.contains('hidden')) {
            forceCloseCustomerModalInstant();
            return;
        }
        if (!isCustomerModalMobileLayout()) {
            forceCloseCustomerModalInstant();
            return;
        }
        var panel = customerModal.querySelector('.customer-modal-panel');
        if (panel) {
            panel.style.transition = '';
            panel.style.transform = '';
        }
        customerModal.classList.remove('customer-modal-visible');
        var done = false;
        function finish() {
            if (done) return;
            done = true;
            if (panel) panel.removeEventListener('transitionend', onEnd);
            forceCloseCustomerModalInstant();
        }
        function onEnd(e) {
            if (!e || e.target !== panel || e.propertyName !== 'transform') return;
            finish();
        }
        if (panel) {
            panel.addEventListener('transitionend', onEnd);
            setTimeout(finish, 400);
        } else {
            finish();
        }
    }

    // Bearbeiter-Filter Dropdown (wie Status)
    const assigneeFilterButton = document.getElementById('assignee-filter-button');
    const assigneeFilterMenu = document.getElementById('assignee-filter-menu');
    const assigneeFilterContainer = document.getElementById('assignee-filter-container');
    if (assigneeFilterButton && assigneeFilterMenu && assigneeFilterContainer) {
        function positionAssigneeDropdown() {
            positionFilterDropdown(assigneeFilterMenu, assigneeFilterButton);
        }
        assigneeFilterButton.addEventListener('click', function(e) {
            e.stopPropagation();
            forceCloseCustomerModalInstant();
            closeFilterDropdownPortal(document.getElementById('status-filter-menu'), document.getElementById('status-filter-container'));
            closeFilterDropdownPortal(document.getElementById('sort-dropdown-menu'), document.getElementById('sort-dropdown-container'));
            closeDisplayDropdown();
            const isHidden = assigneeFilterMenu.classList.contains('hidden');
            if (isHidden) {
                openFilterDropdownAsPortal(assigneeFilterMenu, assigneeFilterButton);
            } else {
                closeFilterDropdownPortal(assigneeFilterMenu, assigneeFilterContainer);
            }
        });
        window.addEventListener('scroll', positionAssigneeDropdown, true);
        window.addEventListener('resize', positionAssigneeDropdown);
        document.querySelectorAll('.assignee-option').forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                const assigneeId = this.getAttribute('data-assignee-id') || '';
                const assigneeName = this.getAttribute('data-assignee-display-name') || 'Alle Bearbeiter';
                const assigneeFilter = document.getElementById('assignee-filter');
                const assigneeFilterText = document.getElementById('assignee-filter-text');
                if (assigneeFilter) assigneeFilter.value = assigneeId;
                if (assigneeFilterText) assigneeFilterText.textContent = assigneeName;
                updateAssigneeFilterButtonState();
                closeFilterDropdownPortal(assigneeFilterMenu, assigneeFilterContainer);
                saveFiltersState();
                loadTickets();
            });
        });
        document.addEventListener('click', function(e) {
            if (isClickOutsideDropdown(assigneeFilterContainer, assigneeFilterMenu, e.target)) {
                closeFilterDropdownPortal(assigneeFilterMenu, assigneeFilterContainer);
            }
        });
    }

    // Dropdowns mitziehen, wenn sich die Toolbar/Filter verschieben (z. B. durch Umbrechen oder Suchfeld-Animation)
    function repositionAllFilterDropdowns() {
        positionFilterDropdown(document.getElementById('status-filter-menu'), document.getElementById('status-filter-button'));
        positionFilterDropdown(document.getElementById('assignee-filter-menu'), document.getElementById('assignee-filter-button'));
        positionFilterDropdown(document.getElementById('display-dropdown-menu'), document.getElementById('display-dropdown-button'), { minWidth: 288, alignRight: true });
        positionFilterDropdown(document.getElementById('sort-dropdown-menu'), document.getElementById('sort-dropdown-button'), { minWidth: 160 });
    }
    const toolbarWrap = document.querySelector('.service-toolbar-wrap');
    if (toolbarWrap) {
        const dropdownResizeObserver = new ResizeObserver(function() {
            repositionAllFilterDropdowns();
        });
        dropdownResizeObserver.observe(toolbarWrap);
    }
    var searchFormEl = document.getElementById('search-form');
    if (searchFormEl) {
        var searchFormResizeObserver = new ResizeObserver(function() {
            repositionAllFilterDropdowns();
        });
        searchFormResizeObserver.observe(searchFormEl);
    }
    
    // Status-Filter aus localStorage wiederherstellen; bei URL-Hash #ohne-bearbeitungszeit Anzeige anpassen
    const savedStatusFilter = localStorage.getItem('ticketsStatusFilter');
    const statusFilterInputRestore = document.getElementById('status-filter');
    const statusFilterTextRestore = document.getElementById('status-filter-text');
    const hashForStatus = (window.location.hash || '').replace(/^#/, '');
    const statusFromHash = (hashForStatus === 'ohne-bearbeitungszeit') ? 'ohne_bearbeitungszeit' : (hashForStatus === 'geschlossen' ? 'geschlossen' : null);
    const urlParamsTicketStatus = new URLSearchParams(window.location.search);
    const ticketStatusFromQuery = urlParamsTicketStatus.get('ticket_status');
    let effectiveStatus = statusFromHash || savedStatusFilter;
    if (ticketStatusFromQuery !== null) {
        effectiveStatus = ticketStatusFromQuery;
        try { localStorage.setItem('ticketsStatusFilter', ticketStatusFromQuery); } catch (e) {}
        urlParamsTicketStatus.delete('ticket_status');
        const qsTicketLeft = urlParamsTicketStatus.toString();
        window.history.replaceState(null, '', window.location.pathname + (qsTicketLeft ? '?' + qsTicketLeft : '') + window.location.hash);
    }
    if (effectiveStatus && statusFilterInputRestore && statusFilterTextRestore) {
        statusFilterInputRestore.value = effectiveStatus;
        statusFilterTextRestore.textContent = statusLabels[effectiveStatus] || effectiveStatus;
    }
    const mobileSheetStatusSync = document.getElementById('mobile-sheet-status-select');
    if (mobileSheetStatusSync && effectiveStatus) {
        for (let mi = 0; mi < mobileSheetStatusSync.options.length; mi++) {
            if (mobileSheetStatusSync.options[mi].value === effectiveStatus) {
                mobileSheetStatusSync.selectedIndex = mi;
                break;
            }
        }
    }
    updateStatusFilterButtonState();
    
    // Sortierung Event Listener für alle sortierbaren Spalten
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            sortTickets(column);
        });
    });
    
    // Filter-Sichtbarkeit beim Laden prüfen
    updateCustomerFilterVisibility();
    updateCustomerFilterButtonState();
    updateAssigneeFilterButtonState();
    updateSearchActiveState();
    
    // Event Listener für Firmenwechsel (aus Nav)
    window.addEventListener('companyChanged', function(e) {
        selectedCompanyId = e.detail.companyId;
        // Filter-Sichtbarkeit aktualisieren
        updateCustomerFilterVisibility();
        // Kundenliste aktualisieren, falls Modal geöffnet ist
        const customerModal = document.getElementById('customerModal');
        if (customerModal && !customerModal.classList.contains('hidden')) {
            updateCustomerListForSelectedCompany();
        }
        // Kundenfilter zurücksetzen, wenn Firma gewechselt wird
        const customerFilter = document.getElementById('customer-filter');
        const customerFilterText = document.getElementById('customer-filter-text');
        if (customerFilter) {
            customerFilter.value = '';
        }
        if (customerFilterText) {
            customerFilterText.textContent = 'Alle Kunden';
        }
        updateCustomerFilterButtonState();
        if (typeof saveFiltersState === 'function') saveFiltersState();
        else if (typeof syncSidebarTicketsFilters === 'function') syncSidebarTicketsFilters();
        loadTickets();
    });
    
    // Event Listener für localStorage-Änderungen (wenn Firma in anderem Tab geändert wird)
    window.addEventListener('storage', function(e) {
        if (e.key === 'selectedUserOption') {
            // Firmenauswahl aktualisieren
            const savedSelection = e.newValue;
            if (savedSelection) {
                try {
                    const data = JSON.parse(savedSelection);
                    selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id) : null;
                    // Filter-Sichtbarkeit aktualisieren
                    updateCustomerFilterVisibility();
                    // Kundenliste aktualisieren, falls Modal geöffnet ist
                    const customerModal = document.getElementById('customerModal');
                    if (customerModal && !customerModal.classList.contains('hidden')) {
                        updateCustomerListForSelectedCompany();
                        updateCustomerModalCompanyBadgeVisibility();
                    }
                    // Kundenfilter zurücksetzen
                    const customerFilter = document.getElementById('customer-filter');
                    const customerFilterText = document.getElementById('customer-filter-text');
                    if (customerFilter) {
                        customerFilter.value = '';
                    }
                    if (customerFilterText) {
                        customerFilterText.textContent = 'Alle Kunden';
                    }
                    updateCustomerFilterButtonState();
                    loadTickets();
                } catch (e) {
                    console.error('Fehler beim Lesen der Firmenauswahl', e);
                }
            }
        }
    });
    
    // Context Menu für Tickets (Rechtsklick) – State: ticketContextTicket, clearTicketContextTargetHighlight (global)
    function closeTicketContextMenuIfOutside(target) {
        const menu = document.getElementById('ticketContextMenu');
        if (!menu || menu.classList.contains('hidden')) return;
        // Verhindert sofortiges Schließen direkt nach dem Öffnen
        // (z. B. bei macOS/ctrl+click Event-Reihenfolge).
        if (Date.now() < ticketContextIgnoreOutsideCloseUntil) return;
        if (!menu.contains(target)) {
            hideTicketContextMenu();
        }
    }

    // Rechtsklick auf Ticket: Kontextmenü zeigen
    document.addEventListener('contextmenu', function(e) {
        const ticketItem = e.target.closest('[data-ticket-id], [data-ticket-view-url]');
        if (ticketItem) {
            e.preventDefault();
            e.stopPropagation();
            const ticketId = ticketItem.getAttribute('data-ticket-id') || (ticketItem.getAttribute('data-ticket-view-url') ? ticketItem.getAttribute('data-ticket-view-url').split('id=')[1] : null);
            if (ticketId) {
                const ticket = allTickets.find(t => t.id == ticketId);
                if (ticket) {
                    clearTicketContextTargetHighlight();
                    ticketContextTargetRow = ticketItem.closest('tr');
                    if (ticketContextTargetRow) {
                        ticketContextTargetRow.classList.add('ticket-context-active');
                    }
                    ticketContextIgnoreOutsideCloseUntil = Date.now() + 250;
                    showTicketContextMenu(e.clientX, e.clientY, ticket);
                }
            }
        } else {
            // Rechtsklick außerhalb schließt ein offenes Menü
            hideTicketContextMenu();
        }
    });
    
    // Context Menu schließen bei Klick außerhalb (robust trotz stopPropagation in anderen Handlern)
    document.addEventListener('pointerdown', function(e) {
        closeTicketContextMenuIfOutside(e.target);
    }, true);
    document.addEventListener('click', function(e) {
        closeTicketContextMenuIfOutside(e.target);
    }, true);
    
    // Context Menu Event Handler
    (function() {
        var ticketCtxMenuEl = document.getElementById('ticketContextMenu');
        if (ticketCtxMenuEl) ticketCtxMenuEl.addEventListener('click', handleTicketContextMenuClick);
        var ticketCtxBackdropEl = document.getElementById('ticketContextBackdrop');
        if (ticketCtxBackdropEl) {
            ticketCtxBackdropEl.addEventListener('click', function() {
                hideTicketContextMenu();
            });
            ticketCtxBackdropEl.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                hideTicketContextMenu();
            });
        }
    })();
    
    function openTicketSubmenu(submenuEl, anchorEl) {
        if (!submenuEl || !anchorEl) return;
        submenuEl.classList.remove('hidden');
        requestAnimationFrame(function() {
            positionTicketContextSubmenu(submenuEl, anchorEl);
        });
    }

    // Submenu Trigger für Status
    const statusTrigger = document.getElementById('ticketCtxStatusTrigger');
    const statusSection = document.getElementById('ticketCtxStatusSection');
    const statusSubmenu = document.getElementById('ticketCtxStatusSubmenu');
    if (statusTrigger && statusSubmenu && statusSection) {
        statusTrigger.addEventListener('mouseenter', function() {
            openTicketSubmenu(statusSubmenu, statusSection);
        });
        statusTrigger.addEventListener('mouseleave', function() {
            setTimeout(() => {
                if (!statusSubmenu.matches(':hover')) {
                    statusSubmenu.classList.add('hidden');
                }
            }, 200);
        });
        statusSubmenu.addEventListener('mouseleave', function() {
            statusSubmenu.classList.add('hidden');
        });
    }
    
    // Submenu Trigger für Gehe zu
    const goToTrigger = document.getElementById('ticketCtxGoToTrigger');
    const goToSection = document.getElementById('ticketCtxGoToSection');
    const goToSubmenu = document.getElementById('ticketCtxGoToSubmenu');
    if (goToTrigger && goToSubmenu && goToSection) {
        goToTrigger.addEventListener('mouseenter', function() {
            if (ticketContextTicket) {
                loadGoToOptionsForContextMenu(ticketContextTicket);
            }
            openTicketSubmenu(goToSubmenu, goToSection);
        });
        goToTrigger.addEventListener('mouseleave', function() {
            setTimeout(() => {
                if (!goToSubmenu.matches(':hover')) {
                    goToSubmenu.classList.add('hidden');
                }
            }, 200);
        });
        goToSubmenu.addEventListener('mouseleave', function() {
            goToSubmenu.classList.add('hidden');
        });
    }
    
    // Submenu Trigger für Bearbeiter
    const assignTrigger = document.getElementById('ticketCtxAssignTrigger');
    const assignSection = document.getElementById('ticketCtxAssignSection');
    const assignSubmenu = document.getElementById('ticketCtxAssignSubmenu');
    if (assignTrigger && assignSubmenu && assignSection) {
        assignTrigger.addEventListener('mouseenter', function() {
            if (ticketContextTicket) {
                loadAssignableUsersForContextMenu(ticketContextTicket);
            }
            openTicketSubmenu(assignSubmenu, assignSection);
        });
        assignTrigger.addEventListener('mouseleave', function() {
            setTimeout(() => {
                if (!assignSubmenu.matches(':hover')) {
                    assignSubmenu.classList.add('hidden');
                }
            }, 200);
        });
        assignSubmenu.addEventListener('mouseleave', function() {
            assignSubmenu.classList.add('hidden');
        });
    }
    
    // Bearbeitungszeit-Modal: Presets und eigene Eingabe
    const bearbeitungszeitPresets = document.querySelectorAll('.bearbeitungszeit-preset');
    const bearbeitungszeitCustomInput = document.getElementById('bearbeitungszeitCustom');
    bearbeitungszeitPresets.forEach(btn => {
        btn.addEventListener('click', function() {
            setBearbeitungszeitPresetActive(this);
            if (bearbeitungszeitCustomInput) bearbeitungszeitCustomInput.value = '';
        });
    });
    if (bearbeitungszeitCustomInput) {
        function clearBearbeitungszeitPresetSelection() {
            setBearbeitungszeitPresetActive(null);
        }
        bearbeitungszeitCustomInput.addEventListener('input', clearBearbeitungszeitPresetSelection);
        bearbeitungszeitCustomInput.addEventListener('change', clearBearbeitungszeitPresetSelection);
        bearbeitungszeitCustomInput.addEventListener('focus', function() {
            if (this.value.trim() !== '') clearBearbeitungszeitPresetSelection();
        });
    }
    // Bearbeitungszeit-Modal: Overlay und Close-Button
    const closeBearbeitungszeitModalBtn = document.getElementById('closeBearbeitungszeitModalBtn');
    if (closeBearbeitungszeitModalBtn) {
        closeBearbeitungszeitModalBtn.addEventListener('click', closeBearbeitungszeitModal);
    }
    const bearbeitungszeitModalOverlay = document.getElementById('bearbeitungszeitModalOverlay');
    if (bearbeitungszeitModalOverlay) {
        bearbeitungszeitModalOverlay.addEventListener('click', closeBearbeitungszeitModal);
    }
    
    const terminQuickModalOverlay = document.getElementById('terminQuickModalOverlay');
    if (terminQuickModalOverlay) {
        terminQuickModalOverlay.addEventListener('click', closeTerminQuickModal);
    }
    const terminQuickModalCloseBtn = document.getElementById('terminQuickModalCloseBtn');
    if (terminQuickModalCloseBtn) {
        terminQuickModalCloseBtn.addEventListener('click', closeTerminQuickModal);
    }
    const terminQuickCancelBtn = document.getElementById('terminQuickCancelBtn');
    if (terminQuickCancelBtn) {
        terminQuickCancelBtn.addEventListener('click', closeTerminQuickModal);
    }
    (function bindTerminQuickSheetPullToClose() {
        var zone = document.getElementById('terminQuickSheetDragZone');
        var sheet = document.getElementById('terminQuickSheet');
        if (!zone || !sheet) return;
        var startY = 0;
        var dragging = false;
        function isMobileSheet() {
            return typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 1023px)').matches;
        }
        function modalOpen() {
            var m = document.getElementById('terminQuickModal');
            return m && !m.classList.contains('hidden');
        }
        zone.addEventListener('touchstart', function(e) {
            if (!isMobileSheet() || !modalOpen() || e.touches.length !== 1) return;
            startY = e.touches[0].clientY;
            dragging = true;
        }, { passive: true });
        zone.addEventListener('touchmove', function(e) {
            if (!dragging || !isMobileSheet() || e.touches.length !== 1) return;
            var dy = e.touches[0].clientY - startY;
            if (dy > 0) {
                sheet.style.transform = 'translateY(' + dy + 'px)';
                e.preventDefault();
            } else {
                sheet.style.transform = '';
            }
        }, { passive: false });
        function endDrag(e) {
            if (!dragging) return;
            dragging = false;
            if (!isMobileSheet()) return;
            var t = e.changedTouches && e.changedTouches[0];
            var endY = t ? t.clientY : startY;
            var dy = endY - startY;
            if (dy > 72) {
                closeTerminQuickModal();
            } else {
                sheet.style.transform = '';
            }
        }
        zone.addEventListener('touchend', endDrag, { passive: true });
        zone.addEventListener('touchcancel', endDrag, { passive: true });
        var scrollArea = document.getElementById('terminQuickScrollArea');
        if (scrollArea) {
            var sStartY = 0;
            var sDragging = false;
            scrollArea.addEventListener('touchstart', function(e) {
                if (!isMobileSheet() || !modalOpen() || scrollArea.scrollTop > 0 || e.touches.length !== 1) return;
                if (e.target.closest('input, textarea, button, select, label')) return;
                sStartY = e.touches[0].clientY;
                sDragging = true;
            }, { passive: true });
            scrollArea.addEventListener('touchmove', function(e) {
                if (!sDragging || !isMobileSheet() || !modalOpen()) return;
                if (scrollArea.scrollTop > 0) {
                    sDragging = false;
                    sheet.style.transform = '';
                    return;
                }
                if (e.touches.length !== 1) return;
                var dy = e.touches[0].clientY - sStartY;
                if (dy > 0) {
                    sheet.style.transform = 'translateY(' + dy + 'px)';
                    e.preventDefault();
                } else {
                    sheet.style.transform = '';
                }
            }, { passive: false });
            function endScrollDrag(e) {
                if (!sDragging) return;
                sDragging = false;
                if (!isMobileSheet()) return;
                var t = e.changedTouches && e.changedTouches[0];
                var endY = t ? t.clientY : sStartY;
                var dy = endY - sStartY;
                if (dy > 72) {
                    closeTerminQuickModal();
                } else {
                    sheet.style.transform = '';
                }
            }
            scrollArea.addEventListener('touchend', endScrollDrag, { passive: true });
            scrollArea.addEventListener('touchcancel', endScrollDrag, { passive: true });
        }
    })();
    const terminQuickForm = document.getElementById('terminQuickForm');
    if (terminQuickForm) {
        terminQuickForm.addEventListener('submit', function(ev) {
            ev.preventDefault();
            submitTerminQuick();
        });
    }
    document.querySelectorAll('.termin-quick-preset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const preset = btn.getAttribute('data-termin-preset');
            if (preset) applyTerminQuickPreset(preset);
        });
    });
    
    scheduleInitialTicketsLoad();
});

window.addEventListener('pageshow', function() {
    requestTicketListScrollRestore(true);
});

/**
 * Ersten Ticket-Daten-Request erst nach dem ersten Paint starten: Oberfläche (Nav, Toolbar, Skeletons)
 * erscheint sofort sichtbar, der schwere fetch blockiert das Rendering nicht am Ende eines langen DOMContentLoaded.
 */
function scheduleInitialTicketsLoad() {
    if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(function() {
            window.requestAnimationFrame(function() {
                loadTickets();
            });
        });
    } else {
        setTimeout(function() { loadTickets(); }, 0);
    }
}

function clearTicketsLoadingSkeletonTimer() {
    if (ticketsLoadingSkeletonTimer) {
        clearTimeout(ticketsLoadingSkeletonTimer);
        ticketsLoadingSkeletonTimer = null;
    }
}

function scheduleTicketsLoadingSkeletons(requestSeq) {
    clearTicketsLoadingSkeletonTimer();
    ticketsLoadingSkeletonTimer = setTimeout(function() {
        ticketsLoadingSkeletonTimer = null;
        if (requestSeq !== loadTicketsRequestSeq) return;
        setTicketsLoadingSkeletons();
    }, ticketsLoadingSkeletonDelayMs);
}

function loadTickets() {
    const requestSeq = ++loadTicketsRequestSeq;
    scheduleTicketsLoadingSkeletons(requestSeq);
    let url = ticketsApiUrl;
    const params = new URLSearchParams();
    
    // Firmenfilter hat Priorität (aus Nav)
    if (selectedCompanyId) {
        params.append('company_id', selectedCompanyId);
    }

    // Text-Suche: Suchbereich aus user_settings (ticketSearchScope). "_none" = keine Suche.
    // Wenn Scope noch leer ist (API lädt async), nur "search" senden – tickets.php wendet dann alle Felder an (wie ohne search_scope).
    var searchEl = document.getElementById('search');
    var searchTerm = searchEl ? searchEl.value.trim() : '';
    const scopeNone = ticketSearchScope && ticketSearchScope.length === 1 && ticketSearchScope[0] === '_none';
    if (searchTerm && !scopeNone) {
        params.append('search', searchTerm);
        if (ticketSearchScope && ticketSearchScope.length > 0) {
            params.append('search_scope', ticketSearchScope.join(','));
        }
    }
    
    if (params.toString()) {
        url += '?' + params.toString();
    }

    const hasActiveSearch = !!(searchTerm && !scopeNone);

    const fetchJsonWithRetry = (requestUrl, options = {}) => {
        const timeoutMs = options.timeoutMs || 12000;
        const maxRetries = options.maxRetries || 2;

        function shouldRetry(err, attempt) {
            if (attempt >= maxRetries) return false;
            const msg = String((err && err.message) || '');
            if (msg.includes('Leere Antwort') || msg.includes('Ungueltige JSON-Antwort') || msg.includes('Timeout')) return true;
            if (msg.includes('HTTP 429') || msg.includes('HTTP 502') || msg.includes('HTTP 503') || msg.includes('HTTP 504')) return true;
            if ((err && err.name) === 'AbortError') return true;
            if ((err && err.name) === 'TypeError') return true; // Netzwerkfehler / Verbindung unterbrochen
            return false;
        }

        function runAttempt(attempt) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

            let fetchPromise;
            try {
                fetchPromise = fetch(requestUrl, { signal: controller.signal, priority: 'high', cache: 'no-store' });
            } catch (e) {
                fetchPromise = fetch(requestUrl, { signal: controller.signal, cache: 'no-store' });
            }
            return fetchPromise
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ' beim Laden der Tickets');
                    }
                    return response.text();
                })
                .then(text => {
                    const payload = (text || '').trim();
                    if (!payload) {
                        throw new Error('Leere Antwort vom Tickets-API');
                    }
                    try {
                        return JSON.parse(payload);
                    } catch (e) {
                        console.error('Ungueltige JSON-Antwort von Tickets-API:', payload.slice(0, 300));
                        throw new Error('Ungueltige JSON-Antwort vom Tickets-API');
                    }
                })
                .catch(err => {
                    clearTimeout(timeoutId);
                    if ((err && err.name) === 'AbortError') {
                        err = new Error('Timeout beim Laden der Tickets');
                    }
                    if (!shouldRetry(err, attempt)) {
                        throw err;
                    }
                    const delayMs = 500 * Math.pow(2, attempt);
                    return new Promise(resolve => setTimeout(resolve, delayMs)).then(() => runAttempt(attempt + 1));
                });
        }

        return runAttempt(0);
    };

    fetchJsonWithRetry(url, { timeoutMs: hasActiveSearch ? 20000 : 12000, maxRetries: 2 })
        .then(data => {
            if (requestSeq !== loadTicketsRequestSeq) return;
            clearTicketsLoadingSkeletonTimer();
            if (data && data.success) {
                ticketsLoadedOnce = true;
                allTickets = Array.isArray(data.tickets) ? data.tickets : [];
                advFilterDeviceSuggestionsCache = null;
                advFilterAnfordererSuggestionsCache = null;
                filterTickets();
                if (typeof updateSidebarTicketsCount === 'function') updateSidebarTicketsCount();
            } else {
                console.error('Fehler beim Laden der Tickets:', (data && data.error) || 'Unbekannter Fehler');
                showError('Fehler beim Laden der Tickets');
            }
        })
        .catch(error => {
            if (requestSeq !== loadTicketsRequestSeq) return;
            clearTicketsLoadingSkeletonTimer();
            console.error('Fehler:', error);
            showError(
                'Fehler beim Laden der Tickets (Netzwerk/API). <a href="#" onclick="window.location.reload(); return false;" class="underline font-medium">Erneut versuchen</a>',
                true
            );
        });
}

function getAdvFilterFieldDefs() {
    var defs = [];
    if (isAdminOrTech) {
        defs.push({ key: 'company', label: 'Firma', type: 'autocomplete', suggestKey: 'company', ticketKey: 'company_name' });
    }
    defs.push(
        { key: 'customer', label: 'Kunde', type: 'autocomplete', suggestKey: 'customer', ticketKey: 'customer_name' }
    );
    if (canFilterAnforderer) {
        defs.push({ key: 'anforderer', label: 'Anforderer', type: 'anforderer', suggestKey: 'anforderer' });
    }
    defs.push(
        { key: 'status', label: 'Status', type: 'status', ticketKey: 'status' },
        { key: 'zugewiesen_an', label: 'Bearbeiter', type: 'assignee', ticketKey: 'zugewiesen_an' },
        { key: 'ticket_nummer', label: 'Ticket-Nr.', type: 'text', ticketKey: 'ticket_nummer' },
        { key: 'titel', label: 'Titel', type: 'text', ticketKey: 'titel' },
        { key: 'beschreibung', label: 'Beschreibung', type: 'text', ticketKey: 'beschreibung' },
        { key: 'device', label: 'Gerät', type: 'autocomplete', suggestKey: 'device', ticketKey: 'device_name' }
    );
    return defs;
}
const ADV_FILTER_FIELD_DEFS = getAdvFilterFieldDefs();

function advFilterIsFieldAllowed(fieldKey) {
    if (!fieldKey || fieldKey === 'prioritaet') return false;
    if (fieldKey === 'company' && !isAdminOrTech) return false;
    if (fieldKey === 'anforderer' && !canFilterAnforderer) return false;
    return ADV_FILTER_FIELD_DEFS.some(function(f) { return f.key === fieldKey; });
}

const ADV_FILTER_OPERATORS = {
    eq: 'ist gleich',
    ne: 'ist nicht gleich',
    contains: 'enthält',
    not_contains: 'enthält nicht',
    empty: 'ist leer',
    not_empty: 'ist nicht leer'
};
const ADV_FILTER_STATUS_VALUES = ['Neu', 'In Bearbeitung', 'Bestellung offen', 'Warteschlange', 'Geplant', 'Geschlossen', 'Archiv'];
/** Gerät-Filter: alle relevanten Ticket-Felder (inkl. Standort in beschreibung) */
const ADV_FILTER_DEVICE_TICKET_KEYS = [
    'device_name', 'device_typ', 'device_hersteller', 'device_modell',
    'device_seriennummer', 'device_mac_adresse', 'device_ip_adresse',
    'device_betriebssystem', 'device_beschreibung'
];
var advFilterDeviceSuggestionsCache = null;
var advFilterAnfordererSuggestionsCache = null;
const ADV_FILTER_OP_TOGGLE_KEYS = ['eq', 'ne', 'contains', 'not_contains'];
const ADV_FILTER_OP_TOGGLE_LABELS = { eq: 'ist gleich', ne: 'ist nicht gleich', contains: 'enthält', not_contains: 'enthält nicht' };
const ADV_FILTER_QUICK_ADD_FIELDS = [
    { key: 'status', label: 'Status', adminOnly: false },
    { key: 'customer', label: 'Kunde', adminOnly: false },
    { key: 'anforderer', label: 'Anforderer', anfordererOnly: true },
    { key: 'company', label: 'Firma', adminOnly: true },
    { key: 'device', label: 'Gerät', adminOnly: false },
    { key: 'beschreibung', label: 'Beschreibung', adminOnly: false },
    { key: 'zugewiesen_an', label: 'Bearbeiter', adminOnly: false }
];

function getAdvFilterFieldDef(fieldKey) {
    return ADV_FILTER_FIELD_DEFS.find(function(f) { return f.key === fieldKey; }) || null;
}

function getAdvFilterOperatorsForField(fieldKey) {
    var def = getAdvFilterFieldDef(fieldKey);
    if (!def) return ['eq', 'ne'];
    if (def.type === 'status' || def.type === 'assignee') {
        return ['eq', 'ne'];
    }
    if (def.type === 'anforderer') {
        return ['eq', 'ne', 'contains', 'not_contains'];
    }
    if (def.type === 'autocomplete') {
        return ['eq', 'ne', 'contains', 'not_contains'];
    }
    if (def.type === 'text') {
        return ['eq', 'ne', 'contains', 'not_contains'];
    }
    return ['eq', 'ne'];
}

function advFilterSanitizeRuleOperator(rule) {
    if (!rule || !rule.field) return rule;
    var ops = getAdvFilterOperatorsForField(rule.field);
    if (ops.indexOf(rule.operator) === -1) {
        rule.operator = ops[0];
        rule.value = '';
    }
    return rule;
}

function normalizeAdvancedFilterRule(rule) {
    if (!rule) return rule;
    var r = {
        field: rule.field,
        operator: rule.operator || 'eq',
        value: rule.value != null ? rule.value : '',
        join: rule.join === 'or' ? 'or' : 'and'
    };
    if (r.field === 'company_id' || r.field === 'company_name') {
        if (r.field === 'company_id' && r.value !== '' && /^\d+$/.test(String(r.value).trim())) {
            var c = (companiesFilterData || []).find(function(x) { return String(x.id) === String(r.value).trim(); });
            r.value = c ? (c.name || '') : r.value;
        }
        r.field = 'company';
    }
    if (r.field === 'customer_id' || r.field === 'customer_name') {
        if (r.field === 'customer_id' && r.value !== '' && /^\d+$/.test(String(r.value).trim())) {
            var cu = (customersFilterData || []).find(function(x) { return String(x.id) === String(r.value).trim(); });
            r.value = cu ? (cu.name || '') : r.value;
        }
        r.field = 'customer';
    }
    if (r.field === 'device_name') {
        r.field = 'device';
    }
    return advFilterSanitizeRuleOperator(r);
}

function normalizeAdvancedFilterRules(rules) {
    return (rules || []).map(normalizeAdvancedFilterRule).filter(function(r) {
        return advFilterIsFieldAllowed(r.field);
    });
}

function getAdvFilterDeviceSuggestionItems() {
    if (advFilterDeviceSuggestionsCache) return advFilterDeviceSuggestionsCache;
    var seen = {};
    var items = [];
    (allTickets || []).forEach(function(t) {
        ADV_FILTER_DEVICE_TICKET_KEYS.forEach(function(k) {
            var v = (t[k] != null && t[k] !== '') ? String(t[k]).trim() : '';
            if (v && !seen[v]) {
                seen[v] = true;
                items.push(v);
            }
        });
    });
    items.sort(function(a, b) { return a.localeCompare(b, 'de'); });
    advFilterDeviceSuggestionsCache = items;
    return items;
}

function advFilterGetAnfordererDisplayName(ticket) {
    if (!ticket) return '';
    var name = [ticket.ersteller_vorname, ticket.ersteller_nachname].filter(Boolean).join(' ').trim();
    if (name) return name;
    if (ticket.ersteller_email) return String(ticket.ersteller_email).trim();
    return '';
}

function getAdvFilterAnfordererSuggestionItems() {
    if (advFilterAnfordererSuggestionsCache) return advFilterAnfordererSuggestionsCache;
    var seen = {};
    var items = [];
    (allTickets || []).forEach(function(t) {
        var n = advFilterGetAnfordererDisplayName(t);
        if (n && !seen[n]) {
            seen[n] = true;
            items.push(n);
        }
    });
    items.sort(function(a, b) { return a.localeCompare(b, 'de'); });
    advFilterAnfordererSuggestionsCache = items;
    return items;
}

function getAdvFilterAutocompleteItems(suggestKey) {
    if (suggestKey === 'anforderer') {
        return getAdvFilterAnfordererSuggestionItems();
    }
    if (suggestKey === 'company') {
        var names = (companiesFilterData || []).map(function(c) { return (c.name || '').trim(); }).filter(Boolean);
        return names.filter(function(n, i, arr) { return arr.indexOf(n) === i; }).sort(function(a, b) { return a.localeCompare(b, 'de'); });
    }
    if (suggestKey === 'customer') {
        var cnames = (customersFilterData || []).map(function(c) { return (c.name || '').trim(); }).filter(Boolean);
        return cnames.filter(function(n, i, arr) { return arr.indexOf(n) === i; }).sort(function(a, b) { return a.localeCompare(b, 'de'); });
    }
    if (suggestKey === 'device') {
        return getAdvFilterDeviceSuggestionItems();
    }
    return [];
}

function advFilterGetDeviceFieldValues(ticket) {
    return ADV_FILTER_DEVICE_TICKET_KEYS.map(function(k) {
        return advFilterTrimLower(ticket[k]);
    }).filter(Boolean);
}

function getAdvFilterDisplayValue(fieldKey, value) {
    if (value === '' || value == null) return '';
    var def = getAdvFilterFieldDef(fieldKey);
    if (!def) return String(value);
    if (def.type === 'assignee') {
        if (value === '0' || value === 0) return '(keiner)';
        var u = (assigneesData || []).find(function(x) { return String(x.id) === String(value); });
        return u ? [u.vorname, u.nachname].filter(Boolean).join(' ').trim() : String(value);
    }
    return String(value);
}

function advFilterTrimLower(val) {
    return String(val == null ? '' : val).trim().toLowerCase();
}

function advFilterIsEmptyRaw(raw) {
    return raw == null || raw === '' || (typeof raw === 'string' && raw.trim() === '');
}

function ticketMatchesAdvancedFilterRule(ticket, rule) {
    var def = getAdvFilterFieldDef(rule.field);
    if (!def || !rule.operator) return true;
    var op = rule.operator;

    if (def.type === 'anforderer') {
        var anfDisplay = advFilterTrimLower(advFilterGetAnfordererDisplayName(ticket));
        var anfEmail = advFilterTrimLower(ticket.ersteller_email || '');
        var anfSearchable = anfDisplay || anfEmail;
        var anfFilterVal = advFilterTrimLower(rule.value);
        if (op === 'eq') return anfDisplay === anfFilterVal;
        if (op === 'ne') return anfDisplay !== anfFilterVal;
        if (op === 'contains') return anfFilterVal === '' ? true : anfSearchable.indexOf(anfFilterVal) !== -1;
        if (op === 'not_contains') return anfFilterVal === '' ? true : anfSearchable.indexOf(anfFilterVal) === -1;
        return true;
    }

    if (def.suggestKey === 'device') {
        var parts = advFilterGetDeviceFieldValues(ticket);
        var searchable = parts.join(' ');
        var hasDevice = !!(ticket.device_id) || parts.length > 0;
        var filterVal = advFilterTrimLower(rule.value);
        if (op === 'empty') return !hasDevice;
        if (op === 'not_empty') return hasDevice;
        if (op === 'eq') return parts.some(function(p) { return p === filterVal; });
        if (op === 'ne') return !parts.some(function(p) { return p === filterVal; });
        if (op === 'contains') return filterVal === '' ? true : searchable.indexOf(filterVal) !== -1;
        if (op === 'not_contains') return filterVal === '' ? true : searchable.indexOf(filterVal) === -1;
        return true;
    }

    var raw = ticket[def.ticketKey];
    var isEmpty = advFilterIsEmptyRaw(raw);
    if (op === 'empty') return isEmpty;
    if (op === 'not_empty') return !isEmpty;

    var ticketVal;
    var filterVal;
    if (def.type === 'status') {
        ticketVal = advFilterTrimLower(raw);
        filterVal = advFilterTrimLower(rule.value);
    } else if (def.type === 'assignee') {
        ticketVal = raw == null || raw === '' ? '0' : String(raw).trim();
        filterVal = rule.value == null || rule.value === '' ? '0' : String(rule.value).trim();
    } else if (def.type === 'text' || def.type === 'autocomplete') {
        ticketVal = advFilterTrimLower(raw);
        filterVal = advFilterTrimLower(rule.value);
    } else {
        ticketVal = String(raw == null ? '' : raw).trim();
        filterVal = String(rule.value == null ? '' : rule.value).trim();
    }

    if (op === 'eq') return ticketVal === filterVal;
    if (op === 'ne') return ticketVal !== filterVal;
    if (op === 'contains') return filterVal === '' ? true : ticketVal.indexOf(filterVal) !== -1;
    if (op === 'not_contains') return filterVal === '' ? true : ticketVal.indexOf(filterVal) === -1;
    return true;
}

function advFilterRuleIsActive(rule) {
    if (!rule || !rule.field || !rule.operator) return false;
    if (rule.operator === 'empty' || rule.operator === 'not_empty') return true;
    return rule.value !== '' && rule.value != null;
}

function advFilterEvaluateRule(ticket, rule) {
    if (!advFilterRuleIsActive(rule)) return true;
    return ticketMatchesAdvancedFilterRule(ticket, rule);
}

/** Regeln in Blöcke: einzelne Bedingung (UND) oder ODER-Gruppe. */
function buildAdvancedFilterBlocks(rules) {
    var active = (rules || []).filter(advFilterRuleIsActive);
    if (!active.length) return [];
    var blocks = [];
    active.forEach(function(rule, i) {
        var join = i === 0 ? 'and' : (rule.join === 'or' ? 'or' : 'and');
        if (i === 0) {
            blocks.push({ mode: 'single', rules: [rule] });
            return;
        }
        if (join === 'or') {
            var last = blocks[blocks.length - 1];
            if (last && last.mode === 'or') {
                last.rules.push(rule);
            } else if (last && last.mode === 'single' && last.rules.length === 1) {
                blocks[blocks.length - 1] = { mode: 'or', rules: [last.rules[0], rule] };
            } else {
                blocks.push({ mode: 'or', rules: [rule] });
            }
        } else {
            blocks.push({ mode: 'single', rules: [rule] });
        }
    });
    return blocks;
}

function ticketMatchesAdvancedFilters(ticket) {
    if (!Array.isArray(advancedFilterRules) || advancedFilterRules.length === 0) return true;
    var blocks = buildAdvancedFilterBlocks(advancedFilterRules);
    if (!blocks.length) return true;
    return blocks.every(function(block) {
        if (block.mode === 'or') {
            return block.rules.some(function(rule) { return advFilterEvaluateRule(ticket, rule); });
        }
        return block.rules.every(function(rule) { return advFilterEvaluateRule(ticket, rule); });
    });
}

function formatAdvFilterClauseText(rule) {
    var def = getAdvFilterFieldDef(rule.field);
    var label = def ? def.label : rule.field;
    var opLabel = ADV_FILTER_OPERATORS[rule.operator] || rule.operator;
    var valDisplay = '';
    if (rule.operator !== 'empty' && rule.operator !== 'not_empty') {
        valDisplay = " '" + String(getAdvFilterDisplayValue(rule.field, rule.value)).replace(/'/g, "''") + "'";
    }
    return label + ' ' + opLabel + valDisplay;
}

function buildAdvancedFilterSqlPreview(rules) {
    var blocks = buildAdvancedFilterBlocks(rules || []);
    if (!blocks.length) return '-- Keine Bedingungen';
    var parts = [];
    blocks.forEach(function(block) {
        if (block.mode === 'or') {
            var inner = block.rules.map(formatAdvFilterClauseText).join(' OR ');
            parts.push('(' + inner + ')');
        } else {
            block.rules.forEach(function(rule) {
                parts.push(formatAdvFilterClauseText(rule));
            });
        }
    });
    return 'WHERE ' + parts.join('\n  AND ');
}

function updateAdvancedFilterButtonState() {
    var btn = document.getElementById('advancedFilterBtn');
    if (!btn) return;
    var active = Array.isArray(advancedFilterRules) && advancedFilterRules.some(function(r) {
        return r && r.field && r.operator && (
            r.operator === 'empty' || r.operator === 'not_empty' ||
            (r.value !== '' && r.value != null)
        );
    });
    if (active) {
        btn.classList.add('advanced-filter-btn--active');
        btn.classList.remove('filter-btn--default');
        btn.title = 'Erweiterte Filter (aktiv)';
    } else {
        btn.classList.remove('advanced-filter-btn--active');
        btn.classList.add('filter-btn--default');
        btn.title = 'Erweiterte Filter';
    }
}

function updateAdvancedFilterSqlPreviewFromDraft() {
    var el = document.getElementById('advancedFilterSqlPreview');
    if (el) el.textContent = buildAdvancedFilterSqlPreview(advancedFilterRulesDraft);
}

/** SVG-Pfade wie Sidebar / globale Suche (sidebar_nav_content.php, nav.php typeConfig) */
const ADV_FILTER_QUICK_ADD_SIDEBAR_ICON_D = {
    status: 'M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z',
    customer: 'M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
    anforderer: 'M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z',
    company: 'M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z',
    device: 'M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z',
    beschreibung: 'M5 19V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v13H7a2 2 0 0 0-2 2Zm0 0a2 2 0 0 0 2 2h12M9 3v14m7 0v4',
    zugewiesen_an: 'M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z'
};

function getAdvFilterQuickAddIconSvg(fieldKey) {
    var d = ADV_FILTER_QUICK_ADD_SIDEBAR_ICON_D[fieldKey] || 'M12 4v16m8-8H4';
    return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="' + d + '"/></svg>';
}

function renderAdvFilterQuickAddBar() {
    var bar = document.getElementById('advFilterQuickAdd');
    if (!bar) return;
    var html = '';
    ADV_FILTER_QUICK_ADD_FIELDS.forEach(function(item) {
        if (item.adminOnly && !isAdminOrTech) return;
        if (item.anfordererOnly && !canFilterAnforderer) return;
        if (item.key === 'zugewiesen_an') {
            var assigneeList = Array.isArray(assigneesData) ? assigneesData : [];
            if (!assigneeList.length) return;
        }
        if (!advFilterIsFieldAllowed(item.key)) return;
        html += '<button type="button" class="adv-filter-quick-chip" data-adv-quick-field="' + escapeHtml(item.key) + '" title="' + escapeHtml(item.label) + ' hinzufügen">' +
            '<span class="adv-filter-quick-chip-icon" aria-hidden="true">' + getAdvFilterQuickAddIconSvg(item.key) + '</span>' +
            '<span class="adv-filter-quick-chip-label">' + escapeHtml(item.label) + '</span></button>';
    });
    bar.innerHTML = html || '<span class="text-xs text-center text-gray-400 dark:text-primary-240 py-2 w-full">Keine Felder verfügbar</span>';
}

function buildAdvFilterJoinConnectorHtml(rule) {
    var isOr = rule.join === 'or';
    return '<div class="adv-filter-connector" data-adv-connector="1">' +
        '<span class="adv-filter-connector-line" aria-hidden="true"></span>' +
        '<div class="flex items-center gap-0.5 rounded-lg border border-gray-200 dark:border-primary-320 bg-white dark:bg-primary-300/50 p-0.5">' +
        '<button type="button" class="adv-filter-join-toggle' + (isOr ? '' : ' adv-filter-join-toggle--active') + '" data-join="and">Und</button>' +
        '<button type="button" class="adv-filter-join-toggle' + (isOr ? ' adv-filter-join-toggle--active' : '') + '" data-join="or">Oder</button>' +
        '</div>' +
        '<span class="adv-filter-connector-line" aria-hidden="true"></span>' +
        '</div>';
}

function buildAdvFilterAddMoreConnectorHtml() {
    return '<div class="adv-filter-connector adv-filter-connector--add-more" data-adv-connector-add="1">' +
        '<span class="adv-filter-connector-line" aria-hidden="true"></span>' +
        '<div class="flex items-center gap-0.5 rounded-lg border border-gray-200 dark:border-primary-320 bg-white dark:bg-primary-300/50 p-0.5">' +
        '<button type="button" class="adv-filter-join-toggle whitespace-nowrap" data-adv-add-more="1">Weitere Bedingung</button></div>' +
        '<span class="adv-filter-connector-line" aria-hidden="true"></span>' +
        '</div>';
}

function advFilterGetOpSegmentGap(track) {
    if (!track) return 4;
    var gap = parseFloat(getComputedStyle(track).getPropertyValue('--adv-op-gap'));
    if (!isNaN(gap) && gap > 0) return gap;
    return parseFloat(getComputedStyle(track).paddingTop) || 4;
}

function advFilterLayoutOpSegmentThumbToButton(track, thumb, btn, animate) {
    if (!track || !thumb || !btn) return null;
    var trackRect = track.getBoundingClientRect();
    var btnRect = btn.getBoundingClientRect();
    var left = btnRect.left - trackRect.left;
    var width = btnRect.width;
    thumb.style.top = '';
    thumb.style.height = '';
    function applyPos(withAnim) {
        if (withAnim) {
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    thumb.style.transition = 'left 0.22s cubic-bezier(0.32, 0.72, 0, 1), width 0.22s cubic-bezier(0.32, 0.72, 0, 1)';
                    thumb.style.left = left + 'px';
                    thumb.style.width = width + 'px';
                });
            });
        } else {
            thumb.style.transition = 'none';
            thumb.style.left = left + 'px';
            thumb.style.width = width + 'px';
        }
    }
    applyPos(animate);
    return btn.getAttribute('data-op');
}

function advFilterPositionOpSegmentThumb(segment) {
    if (!segment) return;
    var track = segment.querySelector('.adv-filter-op-segment-track');
    var thumb = segment.querySelector('.adv-filter-op-segment-thumb');
    var active = segment.querySelector('.adv-filter-op-segment-item[aria-selected="true"]');
    if (!track || !thumb || !active) return;
    advFilterLayoutOpSegmentThumbToButton(track, thumb, active, false);
}

function advFilterSetOpSegmentSelection(segment, op) {
    if (!segment) return;
    segment.querySelectorAll('.adv-filter-op-segment-item').forEach(function(btn) {
        var selected = btn.getAttribute('data-op') === op;
        btn.setAttribute('aria-selected', selected ? 'true' : 'false');
        btn.classList.toggle('adv-filter-op-segment-item--active', selected);
        btn.tabIndex = selected ? 0 : -1;
    });
}

function advFilterRefreshRuleValueRow(ruleIndex) {
    var card = document.querySelector('.adv-filter-rule[data-rule-index="' + ruleIndex + '"]');
    if (!card || !advancedFilterRulesDraft[ruleIndex]) return;
    var valueRow = card.querySelector('.adv-filter-value-row');
    if (!valueRow) return;
    valueRow.innerHTML = buildAdvFilterValueControlHtml(advancedFilterRulesDraft[ruleIndex], ruleIndex);
}

function advFilterSelectOpSegmentUi(segment, op) {
    if (!segment) return;
    advFilterSetOpSegmentSelection(segment, op);
    advFilterPositionOpSegmentThumb(segment);
}

function advFilterChangeRuleOperator(ruleIndex, op, reRenderRules) {
    if (!advancedFilterRulesDraft[ruleIndex]) return;
    syncAdvancedFilterDraftFromDom();
    advancedFilterRulesDraft[ruleIndex].operator = op;
    if (reRenderRules) renderAdvancedFilterRules();
    else {
        advFilterRefreshRuleValueRow(ruleIndex);
        updateAdvancedFilterSqlPreviewFromDraft();
    }
}

function advFilterOpSegmentHitFromX(track, clientX) {
    var items = Array.prototype.slice.call(track.querySelectorAll('.adv-filter-op-segment-item'));
    if (!items.length) return null;
    var trackRect = track.getBoundingClientRect();
    var x = clientX - trackRect.left;
    var best = items[0];
    var bestDist = Infinity;
    items.forEach(function(btn) {
        var r = btn.getBoundingClientRect();
        var cx = r.left + r.width / 2 - trackRect.left;
        var d = Math.abs(cx - x);
        if (d < bestDist) {
            bestDist = d;
            best = btn;
        }
    });
    return { op: best.getAttribute('data-op'), btn: best };
}

function advFilterSnapOpSegmentThumbToTarget(segment, targetBtn, animate) {
    var track = segment.querySelector('.adv-filter-op-segment-track');
    var thumb = segment.querySelector('.adv-filter-op-segment-thumb');
    if (!track || !thumb || !targetBtn) return null;
    return advFilterLayoutOpSegmentThumbToButton(track, thumb, targetBtn, animate);
}

function advFilterSnapOpSegmentThumb(segment, clientX, animate) {
    var track = segment.querySelector('.adv-filter-op-segment-track');
    if (!track) return null;
    var hit = advFilterOpSegmentHitFromX(track, clientX);
    if (!hit) return null;
    return advFilterSnapOpSegmentThumbToTarget(segment, hit.btn, animate);
}

/** Pill folgt der Maus frei (ohne Einrasten auf Segmente). */
function advFilterDragOpSegmentThumbFree(segment, clientX, dragState) {
    var track = segment.querySelector('.adv-filter-op-segment-track');
    var thumb = segment.querySelector('.adv-filter-op-segment-thumb');
    if (!track || !thumb || !dragState) return null;
    var trackRect = track.getBoundingClientRect();
    var gap = advFilterGetOpSegmentGap(track);
    var minLeft = gap;
    var maxLeft = Math.max(minLeft, track.clientWidth - dragState.thumbWidth - gap);
    var left = clientX - trackRect.left - dragState.grabOffsetX;
    left = Math.max(minLeft, Math.min(left, maxLeft));
    thumb.style.transition = 'none';
    thumb.style.top = '';
    thumb.style.height = '';
    thumb.style.left = left + 'px';
    thumb.style.width = dragState.thumbWidth + 'px';
    var hit = advFilterOpSegmentHitFromX(track, clientX);
    return hit ? hit.op : null;
}

function advFilterInitOpSegmentDrag(segment) {
    if (!segment || segment._advOpSegmentDragInit) return;
    segment._advOpSegmentDragInit = true;
    var track = segment.querySelector('.adv-filter-op-segment-track');
    if (!track) return;
    var dragState = null;

    track.addEventListener('pointerdown', function(e) {
        if (e.button !== 0) return;
        var thumb = segment.querySelector('.adv-filter-op-segment-thumb');
        if (!thumb) return;
        var thumbRect = thumb.getBoundingClientRect();
        var trackRect = track.getBoundingClientRect();
        var item = e.target.closest('.adv-filter-op-segment-item');
        dragState = {
            pointerId: e.pointerId,
            startX: e.clientX,
            moved: false,
            grabOffsetX: e.clientX - thumbRect.left,
            thumbWidth: thumbRect.width,
            op: item ? item.getAttribute('data-op') : null
        };
        thumb.style.transition = 'none';
        track.setPointerCapture(e.pointerId);
        e.preventDefault();
    });

    track.addEventListener('pointermove', function(e) {
        if (!dragState || dragState.pointerId !== e.pointerId) return;
        if (Math.abs(e.clientX - dragState.startX) > 3) dragState.moved = true;
        dragState.op = advFilterDragOpSegmentThumbFree(segment, e.clientX, dragState);
    });

    function endDrag(e) {
        if (!dragState || dragState.pointerId !== e.pointerId) return;
        try { track.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }
        var hit = advFilterOpSegmentHitFromX(track, e.clientX);
        var op = (dragState.moved && dragState.op) ? dragState.op : (
            hit ? hit.op : (dragState.op || 'eq')
        );
        if (!dragState.moved && e.target.closest('.adv-filter-op-segment-item')) {
            op = e.target.closest('.adv-filter-op-segment-item').getAttribute('data-op') || op;
        }
        var ruleIndex = parseInt(segment.getAttribute('data-adv-op-segment'), 10);
        var prevBtn = segment.querySelector('.adv-filter-op-segment-item[aria-selected="true"]');
        var prevOp = prevBtn ? prevBtn.getAttribute('data-op') : 'eq';
        var targetBtn = hit ? hit.btn : null;
        if (!targetBtn) {
            var items = track.querySelectorAll('.adv-filter-op-segment-item');
            items.forEach(function(btn) {
                if (btn.getAttribute('data-op') === op) targetBtn = btn;
            });
        }
        advFilterSetOpSegmentSelection(segment, op);
        advFilterSnapOpSegmentThumbToTarget(segment, targetBtn, !!dragState.moved);
        if (op !== prevOp && advancedFilterRulesDraft[ruleIndex]) {
            syncAdvancedFilterDraftFromDom();
            advancedFilterRulesDraft[ruleIndex].operator = op;
            advFilterRefreshRuleValueRow(ruleIndex);
            updateAdvancedFilterSqlPreviewFromDraft();
        } else {
            updateAdvancedFilterSqlPreviewFromDraft();
        }
        dragState = null;
    }

    track.addEventListener('pointerup', endDrag);
    track.addEventListener('pointercancel', function(e) {
        if (!dragState || dragState.pointerId !== e.pointerId) return;
        dragState = null;
        advFilterPositionOpSegmentThumb(segment);
    });
}

function advFilterInitAllOpSegments() {
    document.querySelectorAll('.adv-filter-op-segment').forEach(function(segment) {
        var active = segment.querySelector('.adv-filter-op-segment-item[aria-selected="true"]');
        var op = active ? active.getAttribute('data-op') : 'eq';
        advFilterSelectOpSegmentUi(segment, op);
        advFilterInitOpSegmentDrag(segment);
    });
}

function buildAdvFilterOperatorHtml(rule, index) {
    var opKeys = getAdvFilterOperatorsForField(rule.field);
    var op = rule.operator || 'eq';
    var mainOps = ADV_FILTER_OP_TOGGLE_KEYS.filter(function(k) { return opKeys.indexOf(k) !== -1; });
    var extraOps = opKeys.filter(function(k) { return ['empty', 'not_empty'].indexOf(k) !== -1; });
    if (!mainOps.length && !extraOps.length) return '';
    var segmentHtml = '';
    if (mainOps.length) {
        var activeInMain = mainOps.indexOf(op) !== -1 ? op : mainOps[0];
        var items = mainOps.map(function(k) {
            var active = activeInMain === k;
            var label = ADV_FILTER_OP_TOGGLE_LABELS[k] || k;
            return '<button type="button" class="adv-filter-op-segment-item' + (active ? ' adv-filter-op-segment-item--active' : '') + '" data-op="' + k + '" role="tab" aria-selected="' + (active ? 'true' : 'false') + '" tabindex="' + (active ? '0' : '-1') + '" title="' + escapeHtml(label) + '">' + escapeHtml(label) + '</button>';
        }).join('');
        segmentHtml = '<div class="adv-filter-op-segment" data-adv-op-segment="' + index + '">' +
            '<div class="adv-filter-op-segment-track" role="tablist" aria-label="Vergleich">' +
            '<div class="adv-filter-op-segment-thumb" aria-hidden="true"></div>' + items + '</div></div>';
    }
    var extras = extraOps.map(function(k) {
        var active = op === k;
        return '<button type="button" class="adv-filter-op-extra text-[11px] font-medium' + (active ? ' adv-filter-op-extra--active' : ' text-gray-400 dark:text-primary-240 hover:text-gray-600 dark:hover:text-primary-200') + '" data-adv-op-extra="' + index + '" data-op="' + k + '">' + escapeHtml(ADV_FILTER_OPERATORS[k]) + '</button>';
    }).join('');
    return '<div class="adv-filter-op-group flex flex-wrap items-center gap-1.5 min-w-0" data-adv-op-wrap="' + index + '">' + segmentHtml +
        (extras ? '<span class="text-gray-300 dark:text-primary-320 mx-0.5 shrink-0" aria-hidden="true">·</span>' + extras : '') + '</div>';
}

function buildAdvFilterValueControlHtml(rule, index) {
    var def = getAdvFilterFieldDef(rule.field);
    var op = rule.operator || 'eq';
    if (op === 'empty' || op === 'not_empty') {
        return '<p class="text-xs text-gray-500 dark:text-primary-240 italic py-1">Kein Wert nötig</p>';
    }
    var val = rule.value != null ? String(rule.value) : '';
    if (!def) {
        return '<input type="text" data-adv-value="' + index + '" class="adv-filter-value adv-filter-value-input" placeholder="Wert eingeben…" value="' + escapeHtml(val) + '">';
    }
    if (def.type === 'status' && (op === 'eq' || op === 'ne')) {
        var chips = ADV_FILTER_STATUS_VALUES.map(function(s) {
            var active = val === s;
            return '<button type="button" data-adv-status-chip="' + index + '" data-adv-status-value="' + escapeHtml(s) + '" class="adv-filter-chip' + (active ? ' adv-filter-chip--active' : '') + '">' + escapeHtml(s) + '</button>';
        }).join('');
        return '<div class="adv-filter-status-chips flex flex-wrap gap-1.5" data-adv-value-wrap="' + index + '">' + chips +
            '<input type="hidden" data-adv-value="' + index + '" class="adv-filter-value" value="' + escapeHtml(val) + '"></div>';
    }
    if (def.type === 'anforderer' || def.type === 'autocomplete') {
        var placeholder = def.suggestKey === 'company' ? 'Firma tippen…'
            : (def.suggestKey === 'customer' ? 'Kunde tippen…'
            : (def.suggestKey === 'anforderer' ? 'Anforderer tippen…' : 'Hersteller, Modell, SN…'));
        return '<div class="relative w-full" data-adv-ac-wrap="' + index + '">' +
            '<input type="text" autocomplete="off" data-adv-value="' + index + '" class="adv-filter-value adv-filter-value-input" placeholder="' + placeholder + '" value="' + escapeHtml(val) + '">' +
            '</div>';
    }
    if (def.type === 'assignee') {
        var optsA = '<option value="0"' + (val === '0' ? ' selected' : '') + '>(keiner)</option>';
        (assigneesData || []).forEach(function(u) {
            var name = [u.vorname, u.nachname].filter(Boolean).join(' ').trim();
            optsA += '<option value="' + escapeHtml(String(u.id)) + '"' + (String(u.id) === val ? ' selected' : '') + '>' + escapeHtml(name) + '</option>';
        });
        return '<select data-adv-value="' + index + '" class="adv-filter-value adv-filter-value-input w-full">' + optsA + '</select>';
    }
    if (def.type === 'status') {
        var optsS = '<option value="">Status wählen…</option>';
        ADV_FILTER_STATUS_VALUES.forEach(function(s) {
            optsS += '<option value="' + escapeHtml(s) + '"' + (val === s ? ' selected' : '') + '>' + escapeHtml(s) + '</option>';
        });
        return '<select data-adv-value="' + index + '" class="adv-filter-value adv-filter-value-input w-full">' + optsS + '</select>';
    }
    var textPlaceholder = rule.field === 'beschreibung' ? 'Text in der Beschreibung…'
        : (rule.field === 'titel' ? 'Titel tippen…' : (rule.field === 'ticket_nummer' ? 'Ticket-Nr. tippen…' : 'Wert eingeben…'));
    return '<input type="text" data-adv-value="' + index + '" class="adv-filter-value adv-filter-value-input" placeholder="' + textPlaceholder + '" value="' + escapeHtml(val) + '">';
}

function buildAdvFilterFieldDropdownHtml(rule, index) {
    var current = rule.field;
    if (!current || !advFilterIsFieldAllowed(current)) {
        current = ADV_FILTER_FIELD_DEFS[0] ? ADV_FILTER_FIELD_DEFS[0].key : 'status';
    }
    var def = getAdvFilterFieldDef(current);
    var label = def ? def.label : 'Feld';
    var options = ADV_FILTER_FIELD_DEFS.map(function(f) {
        var active = f.key === current;
        return '<button type="button" class="adv-filter-field-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors' +
            (active ? ' bg-gray-50 dark:bg-primary-140 font-medium' : '') + '" data-field="' + escapeHtml(f.key) + '">' + escapeHtml(f.label) + '</button>';
    }).join('');
    return '<div class="adv-filter-field-dropdown relative shrink-0" data-adv-field-wrap="' + index + '">' +
        '<button type="button" class="adv-filter-field-btn filter-btn--default w-full flex items-center gap-2 pl-3 pr-2 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:outline-none dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200" aria-haspopup="listbox" aria-expanded="false">' +
        '<svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>' +
        '<span class="adv-filter-field-label filter-btn-label truncate">' + escapeHtml(label) + '</span>' +
        '<svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>' +
        '</button>' +
        '<div class="adv-filter-field-menu service-filter-dropdown-shadow hidden absolute z-[82] left-0 top-full mt-1 min-w-full bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base overflow-hidden" role="listbox">' +
        '<div class="py-1 divide-y divide-gray-200 dark:divide-primary-230 overflow-y-auto max-h-[14rem] custom-scrollbar">' + options + '</div></div>' +
        '<input type="hidden" data-adv-field="' + index + '" class="adv-filter-field" value="' + escapeHtml(current) + '">' +
        '</div>';
}

function advFilterGetFieldDropdownWrap(menuEl) {
    if (!menuEl) return null;
    var wrapIdx = menuEl.getAttribute('data-adv-field-wrap-index');
    if (wrapIdx != null && wrapIdx !== '') {
        return document.querySelector('[data-adv-field-wrap="' + wrapIdx + '"]');
    }
    return menuEl.closest('[data-adv-field-wrap]');
}

function advFilterPositionFieldDropdownMenu(menuEl, buttonEl) {
    if (!menuEl || !buttonEl || menuEl.classList.contains('hidden')) return;
    var rect = buttonEl.getBoundingClientRect();
    var vh = window.innerHeight;
    var vw = window.innerWidth;
    var gap = 4;
    var maxMenuH = 280;
    var spaceBelow = vh - rect.bottom - gap;
    var spaceAbove = rect.top - gap;
    var openAbove = spaceBelow < maxMenuH && spaceAbove > spaceBelow;
    menuEl.style.position = 'fixed';
    menuEl.style.marginTop = '0';
    menuEl.style.marginBottom = '0';
    var menuW = Math.max(rect.width, 160);
    menuEl.style.width = menuW + 'px';
    menuEl.style.minWidth = menuW + 'px';
    menuEl.style.maxWidth = '';
    var left = rect.left;
    if (left + menuW > vw) left = Math.max(0, vw - menuW);
    menuEl.style.left = left + 'px';
    if (openAbove) {
        menuEl.style.bottom = (vh - rect.top + gap) + 'px';
        menuEl.style.top = 'auto';
        menuEl.style.maxHeight = Math.min(maxMenuH, spaceAbove) + 'px';
    } else {
        menuEl.style.top = (rect.bottom + gap) + 'px';
        menuEl.style.bottom = 'auto';
        menuEl.style.maxHeight = Math.min(maxMenuH, spaceBelow) + 'px';
    }
}

function advFilterOpenFieldDropdown(menuEl, buttonEl, wrapEl) {
    if (!menuEl || !buttonEl) return;
    if (wrapEl) {
        menuEl.setAttribute('data-adv-field-wrap-index', wrapEl.getAttribute('data-adv-field-wrap') || '');
        var rowEl = wrapEl.closest('.adv-filter-rule');
        if (rowEl) menuEl.setAttribute('data-adv-rule-index', rowEl.getAttribute('data-rule-index') || '');
    }
    if (!menuEl._dropdownRestore) {
        menuEl._dropdownRestore = { parent: menuEl.parentNode, nextSibling: menuEl.nextSibling };
        document.body.appendChild(menuEl);
    }
    menuEl.classList.remove('hidden');
    buttonEl.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(function() {
        advFilterPositionFieldDropdownMenu(menuEl, buttonEl);
    });
}

function advFilterCloseFieldDropdown(menuEl, wrapEl) {
    if (!menuEl) return;
    menuEl.classList.add('hidden');
    var btn = wrapEl ? wrapEl.querySelector('.adv-filter-field-btn') : null;
    if (btn) btn.setAttribute('aria-expanded', 'false');
    if (menuEl._dropdownRestore) {
        var restore = menuEl._dropdownRestore;
        if (restore.parent) {
            if (restore.nextSibling) restore.parent.insertBefore(menuEl, restore.nextSibling);
            else restore.parent.appendChild(menuEl);
        }
        menuEl._dropdownRestore = null;
    }
}

function advFilterCloseAllFieldDropdowns() {
    document.querySelectorAll('.adv-filter-field-menu').forEach(function(menu) {
        advFilterCloseFieldDropdown(menu, advFilterGetFieldDropdownWrap(menu));
    });
}

function advFilterApplyFieldChange(ruleIndex, fieldKey) {
    if (!advancedFilterRulesDraft[ruleIndex]) return;
    advancedFilterRulesDraft[ruleIndex].field = fieldKey;
    advFilterSanitizeRuleOperator(advancedFilterRulesDraft[ruleIndex]);
    advancedFilterRulesDraft[ruleIndex].value = '';
    renderAdvancedFilterRules();
}

function initAdvFilterFieldDropdowns(root) {
    root = root || document.getElementById('advancedFilterRulesContainer');
    if (!root) return;
    root.querySelectorAll('[data-adv-field-wrap]').forEach(function(wrap) {
        if (wrap._advFieldDropdownInit) return;
        wrap._advFieldDropdownInit = true;
        var btn = wrap.querySelector('.adv-filter-field-btn');
        var menu = wrap.querySelector('.adv-filter-field-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var isHidden = menu.classList.contains('hidden');
            advFilterCloseAllFieldDropdowns();
            if (isHidden) advFilterOpenFieldDropdown(menu, btn, wrap);
            else advFilterCloseFieldDropdown(menu, wrap);
        });
        menu.addEventListener('click', function(e) {
            var opt = e.target.closest('.adv-filter-field-option');
            if (!opt) return;
            e.stopPropagation();
            e.preventDefault();
            var fieldKey = opt.getAttribute('data-field');
            if (!fieldKey) return;
            var ruleIdx = parseInt(menu.getAttribute('data-adv-rule-index'), 10);
            if (isNaN(ruleIdx)) {
                var rowEl = wrap.closest('.adv-filter-rule');
                ruleIdx = rowEl ? parseInt(rowEl.getAttribute('data-rule-index'), 10) : parseInt(wrap.getAttribute('data-adv-field-wrap'), 10);
            }
            advFilterCloseFieldDropdown(menu, wrap);
            advFilterApplyFieldChange(ruleIdx, fieldKey);
        });
    });
    if (!window._advFilterFieldDropdownRepositionBound) {
        window._advFilterFieldDropdownRepositionBound = true;
        function repositionOpenAdvFieldMenus() {
            document.querySelectorAll('.adv-filter-field-menu:not(.hidden)').forEach(function(menu) {
                var wrapEl = advFilterGetFieldDropdownWrap(menu);
                var btnEl = wrapEl && wrapEl.querySelector('.adv-filter-field-btn');
                if (btnEl) advFilterPositionFieldDropdownMenu(menu, btnEl);
            });
        }
        window.addEventListener('scroll', repositionOpenAdvFieldMenus, true);
        window.addEventListener('resize', repositionOpenAdvFieldMenus);
    }
}

function buildAdvFilterRuleCardHtml(rule, index) {
    return '<div class="adv-filter-rule adv-filter-rule-card mb-2" data-rule-index="' + index + '">' +
        '<div class="flex items-start gap-2">' +
        '<div class="flex-1 min-w-0 space-y-2.5">' +
        '<div class="flex flex-wrap items-center justify-between gap-2">' +
        '<span class="adv-filter-rule-label">Bedingung ' + (index + 1) + '</span>' +
        '<button type="button" data-adv-remove="' + index + '" class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-md" title="Entfernen" aria-label="Bedingung entfernen">' +
        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>' +
        '<div class="adv-filter-field-op-row flex flex-wrap items-center gap-2">' +
        buildAdvFilterFieldDropdownHtml(rule, index) +
        buildAdvFilterOperatorHtml(rule, index) +
        '</div>' +
        '<div class="adv-filter-value-row w-full">' + buildAdvFilterValueControlHtml(rule, index) + '</div>' +
        '</div></div></div>';
}

function renderAdvancedFilterRules() {
    advFilterHideSuggestionsPortal();
    renderAdvFilterQuickAddBar();
    var container = document.getElementById('advancedFilterRulesContainer');
    if (!container) return;
    advancedFilterRulesDraft = normalizeAdvancedFilterRules(advancedFilterRulesDraft);
    var html = '';
    if (!advancedFilterRulesDraft.length) {
        html = '<p class="text-sm text-center text-gray-500 dark:text-primary-240 py-8 px-3">Wähle oben eine Kachel, um deine erste Bedingung hinzuzufügen.</p>';
    } else {
        advancedFilterRulesDraft.forEach(function(rule, index) {
            if (index > 0) {
                html += buildAdvFilterJoinConnectorHtml(rule);
            }
            html += buildAdvFilterRuleCardHtml(rule, index);
        });
        html += buildAdvFilterAddMoreConnectorHtml();
    }
    container.innerHTML = html;
    var addRuleFooter = document.getElementById('advancedFilterAddRuleFooter');
    if (addRuleFooter) {
        addRuleFooter.classList.toggle('hidden', advancedFilterRulesDraft.length > 0);
    }
    updateAdvancedFilterSqlPreviewFromDraft();
    requestAnimationFrame(function() {
        advFilterInitAllOpSegments();
        initAdvFilterFieldDropdowns(container);
    });
}

function advFilterQuickAddField(fieldKey) {
    if (!advFilterIsFieldAllowed(fieldKey)) return;
    syncAdvancedFilterDraftFromDom();
    advancedFilterRulesDraft.push({
        field: fieldKey,
        operator: 'eq',
        value: '',
        join: advancedFilterRulesDraft.length ? 'and' : 'and'
    });
    renderAdvancedFilterRules();
    var container = document.getElementById('advancedFilterRulesContainer');
    if (!container) return;
    var lastCard = container.querySelector('.adv-filter-rule-card:last-of-type');
    if (!lastCard) return;
    if (fieldKey === 'status') {
        var chip = lastCard.querySelector('[data-adv-status-chip]');
        if (chip) chip.focus();
    } else {
        var inp = lastCard.querySelector('.adv-filter-value-input, select.adv-filter-value');
        if (inp) inp.focus();
    }
}

var advFilterSuggestionsActiveInput = null;

function advFilterGetSuggestionsPortal() {
    var el = document.getElementById('advFilterSuggestionsPortal');
    if (!el) {
        el = document.createElement('div');
        el.id = 'advFilterSuggestionsPortal';
        el.className = 'adv-filter-suggestions-portal hidden fixed z-[90] bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-xl shadow-lg overflow-y-auto custom-scrollbar py-1';
        el.setAttribute('role', 'listbox');
        document.body.appendChild(el);
    }
    return el;
}

function advFilterHideSuggestionsPortal() {
    var portal = document.getElementById('advFilterSuggestionsPortal');
    if (portal) {
        portal.classList.add('hidden');
        portal.innerHTML = '';
        portal.style.top = '';
        portal.style.bottom = '';
        portal.style.left = '';
        portal.style.width = '';
        portal.style.minWidth = '';
        portal.style.maxWidth = '';
        portal.style.maxHeight = '';
    }
    advFilterSuggestionsActiveInput = null;
}

function advFilterPositionSuggestionsPortal(input) {
    var portal = advFilterGetSuggestionsPortal();
    var rect = input.getBoundingClientRect();
    var maxH = 192;
    var gap = 4;
    var spaceBelow = window.innerHeight - rect.bottom - gap;
    var spaceAbove = rect.top - gap;
    portal.style.left = Math.max(8, rect.left) + 'px';
    portal.style.width = rect.width + 'px';
    portal.style.minWidth = rect.width + 'px';
    portal.style.maxWidth = Math.max(rect.width, 280) + 'px';
    if (spaceBelow < 100 && spaceAbove > spaceBelow) {
        portal.style.top = 'auto';
        portal.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
        portal.style.maxHeight = Math.min(maxH, spaceAbove) + 'px';
    } else {
        portal.style.bottom = 'auto';
        portal.style.top = (rect.bottom + gap) + 'px';
        portal.style.maxHeight = Math.min(maxH, spaceBelow) + 'px';
    }
}

function advFilterRenderSuggestions(input, items) {
    if (!input) {
        advFilterHideSuggestionsPortal();
        return;
    }
    var value = input.value.trim().toLowerCase();
    if (!value) {
        advFilterHideSuggestionsPortal();
        return;
    }
    var filtered = items.filter(function(item) {
        var low = item.toLowerCase();
        return low.indexOf(value) !== -1 && low !== value;
    }).slice(0, 10);
    if (!filtered.length) {
        advFilterHideSuggestionsPortal();
        return;
    }
    var portal = advFilterGetSuggestionsPortal();
    advFilterSuggestionsActiveInput = input;
    portal.innerHTML = filtered.map(function(item) {
        return '<div class="adv-filter-suggestion px-3 py-2.5 hover:bg-gray-100 dark:hover:bg-primary-140 cursor-pointer text-sm text-gray-900 dark:text-primary-200" data-suggestion-value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</div>';
    }).join('');
    advFilterPositionSuggestionsPortal(input);
    portal.classList.remove('hidden');
}

function advFilterRefreshAutocompleteForInput(input) {
    if (!input) return;
    var row = input.closest('.adv-filter-rule');
    if (!row) return;
    var fieldEl = row.querySelector('.adv-filter-field');
    if (!fieldEl) return;
    var def = getAdvFilterFieldDef(fieldEl.value);
    if (!def || (def.type !== 'autocomplete' && def.type !== 'anforderer')) return;
    advFilterRenderSuggestions(input, getAdvFilterAutocompleteItems(def.suggestKey));
}

function syncAdvancedFilterDraftFromDom() {
    var container = document.getElementById('advancedFilterRulesContainer');
    if (!container) return;
    var rules = [];
    container.querySelectorAll('.adv-filter-rule').forEach(function(row, idx) {
        var join = 'and';
        if (idx > 0) {
            var conn = row.previousElementSibling;
            if (conn && conn.classList.contains('adv-filter-connector')) {
                var activeJoin = conn.querySelector('.adv-filter-join-toggle--active');
                join = activeJoin && activeJoin.getAttribute('data-join') === 'or' ? 'or' : 'and';
            }
        }
        var fieldEl = row.querySelector('.adv-filter-field');
        if (!fieldEl) return;
        var operator = 'eq';
        var opSegmentItem = row.querySelector('.adv-filter-op-segment-item[aria-selected="true"]');
        var opExtra = row.querySelector('.adv-filter-op-extra--active');
        if (opExtra) operator = opExtra.getAttribute('data-op') || 'eq';
        else if (opSegmentItem) operator = opSegmentItem.getAttribute('data-op') || 'eq';
        var valEl = row.querySelector('.adv-filter-value');
        rules.push({
            field: fieldEl.value,
            operator: operator,
            value: valEl ? valEl.value : '',
            join: join
        });
    });
    advancedFilterRulesDraft = rules;
}

function openAdvancedFilterModal() {
    advFilterDeviceSuggestionsCache = null;
    advFilterAnfordererSuggestionsCache = null;
    advancedFilterRulesDraft = normalizeAdvancedFilterRules(JSON.parse(JSON.stringify(advancedFilterRules || [])));
    renderAdvancedFilterRules();
    var modal = document.getElementById('advancedFilterModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('advanced-filter-modal-open');
    }
}

function closeAdvancedFilterModal() {
    advFilterHideSuggestionsPortal();
    advFilterCloseAllFieldDropdowns();
    var modal = document.getElementById('advancedFilterModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('advanced-filter-modal-open');
    }
}

function applyAdvancedFiltersFromModal() {
    syncAdvancedFilterDraftFromDom();
    advancedFilterRules = normalizeAdvancedFilterRules(JSON.parse(JSON.stringify(advancedFilterRulesDraft.filter(function(r) {
        if (!r || !r.field || !r.operator) return false;
        if (r.operator === 'empty' || r.operator === 'not_empty') return true;
        return r.value !== '' && r.value != null;
    }))));
    updateAdvancedFilterButtonState();
    closeAdvancedFilterModal();
    filterTickets();
    saveFiltersState();
}

function initAdvancedFilterModal() {
    var openBtn = document.getElementById('advancedFilterBtn');
    var overlay = document.getElementById('advancedFilterModalOverlay');
    var closeBtn = document.getElementById('advancedFilterModalCloseBtn');
    var cancelBtn = document.getElementById('advancedFilterCancelBtn');
    var applyBtn = document.getElementById('advancedFilterApplyBtn');
    var addBtn = document.getElementById('advancedFilterAddRuleBtn');
    var clearBtn = document.getElementById('advancedFilterClearAllBtn');
    var container = document.getElementById('advancedFilterRulesContainer');
    if (openBtn) openBtn.addEventListener('click', openAdvancedFilterModal);
    if (overlay) overlay.addEventListener('click', closeAdvancedFilterModal);
    if (closeBtn) closeBtn.addEventListener('click', closeAdvancedFilterModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeAdvancedFilterModal);
    if (applyBtn) applyBtn.addEventListener('click', applyAdvancedFiltersFromModal);
    if (addBtn) addBtn.addEventListener('click', function() {
        advFilterQuickAddField('status');
    });
    if (clearBtn) clearBtn.addEventListener('click', function() {
        advancedFilterRulesDraft = [];
        renderAdvancedFilterRules();
    });
    var quickBar = document.getElementById('advFilterQuickAdd');
    if (quickBar) {
        quickBar.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-adv-quick-field]');
            if (btn) advFilterQuickAddField(btn.getAttribute('data-adv-quick-field'));
        });
    }
    if (container) {
        if (!window._advFilterFieldDropdownOutsideClickBound) {
            window._advFilterFieldDropdownOutsideClickBound = true;
            document.addEventListener('click', function(e) {
                var modal = document.getElementById('advancedFilterModal');
                if (!modal || modal.classList.contains('hidden')) return;
                var openMenu = document.querySelector('.adv-filter-field-menu:not(.hidden)');
                if (!openMenu) return;
                var wrap = advFilterGetFieldDropdownWrap(openMenu);
                if (!wrap) return;
                if (!wrap.contains(e.target) && !openMenu.contains(e.target)) {
                    advFilterCloseFieldDropdown(openMenu, wrap);
                }
            });
        }
        container.addEventListener('click', function(e) {
            if (e.target.closest('.adv-filter-field-option')) return;
            var fieldBtn = e.target.closest('.adv-filter-field-btn');
            if (fieldBtn) return;
            var addMoreBtn = e.target.closest('[data-adv-add-more]');
            if (addMoreBtn) {
                advFilterQuickAddField('status');
                return;
            }
            var joinBtn = e.target.closest('.adv-filter-join-toggle');
            if (joinBtn) {
                var conn = joinBtn.closest('.adv-filter-connector');
                if (conn) {
                    conn.querySelectorAll('.adv-filter-join-toggle').forEach(function(b) {
                        b.classList.remove('adv-filter-join-toggle--active');
                    });
                    joinBtn.classList.add('adv-filter-join-toggle--active');
                }
                syncAdvancedFilterDraftFromDom();
                updateAdvancedFilterSqlPreviewFromDraft();
                return;
            }
            var opExtra = e.target.closest('[data-adv-op-extra]');
            if (opExtra) {
                syncAdvancedFilterDraftFromDom();
                var idxEx = parseInt(opExtra.getAttribute('data-adv-op-extra'), 10);
                if (advancedFilterRulesDraft[idxEx]) {
                    advancedFilterRulesDraft[idxEx].operator = opExtra.getAttribute('data-op') || 'empty';
                    advFilterRefreshRuleValueRow(idxEx);
                    updateAdvancedFilterSqlPreviewFromDraft();
                }
                return;
            }
            var statusChip = e.target.closest('[data-adv-status-chip]');
            if (statusChip) {
                var rowChip = statusChip.closest('.adv-filter-rule');
                var val = statusChip.getAttribute('data-adv-status-value') || '';
                if (rowChip) {
                    rowChip.querySelectorAll('[data-adv-status-chip]').forEach(function(c) {
                        c.classList.toggle('adv-filter-chip--active', c === statusChip);
                    });
                    var hidden = rowChip.querySelector('input.adv-filter-value');
                    if (hidden) hidden.value = val;
                }
                syncAdvancedFilterDraftFromDom();
                updateAdvancedFilterSqlPreviewFromDraft();
                return;
            }
            var rm = e.target.closest('[data-adv-remove]');
            if (!rm) return;
            syncAdvancedFilterDraftFromDom();
            var ri = parseInt(rm.getAttribute('data-adv-remove'), 10);
            advancedFilterRulesDraft.splice(ri, 1);
            renderAdvancedFilterRules();
        });
        container.addEventListener('change', function(e) {
            var t = e.target;
            var row = t.closest ? t.closest('.adv-filter-rule') : null;
            if (!row) return;
            if (t.classList.contains('adv-filter-value')) {
                syncAdvancedFilterDraftFromDom();
                updateAdvancedFilterSqlPreviewFromDraft();
            }
        });
        container.addEventListener('input', function(e) {
            var input = e.target;
            if (!input || !input.classList || !input.classList.contains('adv-filter-value') || input.tagName !== 'INPUT') return;
            advFilterRefreshAutocompleteForInput(input);
            syncAdvancedFilterDraftFromDom();
            updateAdvancedFilterSqlPreviewFromDraft();
        });
        container.addEventListener('focusin', function(e) {
            var input = e.target;
            if (!input || !input.classList || !input.classList.contains('adv-filter-value') || input.tagName !== 'INPUT') return;
            advFilterRefreshAutocompleteForInput(input);
        });
        container.addEventListener('focusout', function(e) {
            var input = e.target;
            if (!input || !input.classList || !input.classList.contains('adv-filter-value') || input.tagName !== 'INPUT') return;
            setTimeout(function() {
                var portal = document.getElementById('advFilterSuggestionsPortal');
                if (portal && portal.contains(document.activeElement)) return;
                if (advFilterSuggestionsActiveInput === input && document.activeElement !== input) {
                    advFilterHideSuggestionsPortal();
                }
            }, 200);
        });
        container.addEventListener('scroll', function() {
            if (advFilterSuggestionsActiveInput) {
                advFilterPositionSuggestionsPortal(advFilterSuggestionsActiveInput);
            }
        }, { passive: true });
        var advFilterPortal = advFilterGetSuggestionsPortal();
        advFilterPortal.addEventListener('mousedown', function(e) {
            var sug = e.target.closest('[data-suggestion-value]');
            if (!sug || !advFilterSuggestionsActiveInput) return;
            e.preventDefault();
            advFilterSuggestionsActiveInput.value = sug.getAttribute('data-suggestion-value') || '';
            advFilterHideSuggestionsPortal();
            syncAdvancedFilterDraftFromDom();
            updateAdvancedFilterSqlPreviewFromDraft();
        });
    }
    if (!window._advFilterSuggestionsResizeBound) {
        window._advFilterSuggestionsResizeBound = true;
        window.addEventListener('resize', function() {
            if (advFilterSuggestionsActiveInput) {
                advFilterPositionSuggestionsPortal(advFilterSuggestionsActiveInput);
            }
            document.querySelectorAll('.adv-filter-op-segment').forEach(advFilterPositionOpSegmentThumb);
        });
    }
    updateAdvancedFilterButtonState();
}

function filterTickets() {
    var searchEl = document.getElementById('search');
    var searchTerm = (searchEl && searchEl.value) ? searchEl.value.toLowerCase() : '';
    if (!Array.isArray(allTickets)) allTickets = [];
    const customerFilter = document.getElementById('customer-filter');
    const customerFilterValue = customerFilter ? customerFilter.value : '';
    const assigneeFilter = document.getElementById('assignee-filter');
    const assigneeFilterValue = assigneeFilter ? assigneeFilter.value : '';
    // URL-Hash von Dashboard-Card hat Priorität (Filter wird nicht in der Status-Leiste angezeigt)
    const hash = (window.location.hash || '').replace(/^#/, '');
    const statusFromHash = hash === 'ohne-bearbeitungszeit'
        ? 'ohne_bearbeitungszeit'
        : (hash === 'geschlossen' ? 'geschlossen' : (hash === 'angeheftet' ? 'angeheftet' : ''));
    const statusFilterInput = document.getElementById('status-filter');
    const statusFilter = statusFromHash || (statusFilterInput ? statusFilterInput.value : 'offen_combined');
    
    filteredTickets = allTickets.filter(ticket => {
        // Suchfilter: wird serverseitig erledigt (loadTickets mit search/search_scope);
        // bei leerem Suchbereich wird nicht nach Text gefiltert.

        // Kunde-Filter
        if (customerFilterValue) {
            const ticketCustomerId = ticket.customer_id ? ticket.customer_id.toString() : '';
            if (ticketCustomerId !== customerFilterValue) {
                return false;
            }
        }

        // Bearbeiter-Filter
        if (assigneeFilterValue) {
            const ticketAssigneeId = ticket.zugewiesen_an != null ? ticket.zugewiesen_an.toString() : '';
            if (ticketAssigneeId !== assigneeFilterValue) {
                return false;
            }
        }
        
        // Status-Filter
        if (statusFilter) {
            if (statusFilter === 'offen_combined') {
                // "Offen" kombiniert: Neu + In Bearbeitung + Bestellung offen + Warteschlange + Geplant
                const openStatuses = ['Neu', 'In Bearbeitung', 'Bestellung offen', 'Warteschlange', 'Geplant'];
                if (!openStatuses.includes(ticket.status)) {
                    return false;
                }
            } else if (statusFilter === 'warteschlange') {
                // "Wartend": Warteschlange + Geplant
                const waitingStatuses = ['Warteschlange', 'Geplant'];
                if (!waitingStatuses.includes(ticket.status)) {
                    return false;
                }
            } else if (statusFilter === 'ohne_bearbeitungszeit') {
                // Geschlossene Tickets ohne Bearbeitungszeit
                if (ticket.status !== 'Geschlossen') {
                    return false;
                }
                const bt = ticket.bearbeitungszeit_minuten;
                if (bt != null && bt !== '' && parseInt(bt, 10) > 0) {
                    return false;
                }
            } else if (statusFilter === 'angeheftet') {
                // Nur angeheftete Tickets
                const pinned = ticket.is_pinned === 1 || ticket.is_pinned === '1' || ticket.is_pinned === true;
                if (!pinned) {
                    return false;
                }
            } else {
                // Mapping von Radio-Button-Werten zu Datenbank-Status-Werten
                let mappedStatus = statusFilter;
                if (statusFilter === 'neu') {
                    mappedStatus = 'Neu';
                } else if (statusFilter === 'in_bearbeitung') {
                    mappedStatus = 'In Bearbeitung';
                } else if (statusFilter === 'warteschlange') {
                    mappedStatus = 'Warteschlange';
                } else if (statusFilter === 'bestellung_offen') {
                    mappedStatus = 'Bestellung offen';
                } else if (statusFilter === 'geschlossen') {
                    mappedStatus = 'Geschlossen';
                } else if (statusFilter === 'archiv') {
                    mappedStatus = 'Archiv';
                } else if (statusFilter === 'geplant') {
                    mappedStatus = 'Geplant';
                }
                
                if (ticket.status !== mappedStatus) {
                    return false;
                }
            }
        }

        // Erweiterte Filter (Desktop, clientseitig)
        if (!ticketMatchesAdvancedFilters(ticket)) {
            return false;
        }
        
        return true;
    });
    
    // Sortierung anwenden (Standard: geaendert_datum asc)
    if (sortColumn) {
        // Initiale Sortierung ohne Richtungs-Toggle
        sortTickets(sortColumn, true, false); // true = UI-Aktualisierung für initiale Sortierung
    } else {
        // Falls keine Sortierung aktiv, Button deaktivieren
        updateSortIcons();
    }
    
    displayTickets(filteredTickets);
    if (typeof updateTicketsMobileNavTitle === 'function') updateTicketsMobileNavTitle();
    if (typeof updateSidebarTicketsCount === 'function') updateSidebarTicketsCount();
}

function sortTickets(column, updateUI = true, toggleDirection = true) {
    if (toggleDirection) {
        // Sortierrichtung umschalten, wenn bereits nach dieser Spalte sortiert wird
        if (sortColumn === column) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = column;
                // Standard: absteigend (neueste zuerst)
                sortDirection = (column === 'naechster_termin') ? 'asc' : 'desc';
        }
    } else {
        // Nur sortieren (keine Richtungs-Änderung)
        if (column && sortColumn !== column) {
            sortColumn = column;
        }
    }
    
    filteredTickets.sort((a, b) => {
        // Angeheftete Tickets immer oben (user-bezogen), unabhängig von Sortierspalte
        const aPinned = a.is_pinned === 1 || a.is_pinned === '1' || a.is_pinned === true;
        const bPinned = b.is_pinned === 1 || b.is_pinned === '1' || b.is_pinned === true;
        if (aPinned !== bPinned) {
            return aPinned ? -1 : 1;
        }

        let aValue, bValue;
        let aMissing = false, bMissing = false;
        
        // Werte für Sortierung extrahieren
        switch(column) {
            case 'ticket_nummer':
                aValue = (a.ticket_nummer || '').toLowerCase();
                bValue = (b.ticket_nummer || '').toLowerCase();
                break;
            case 'titel':
                aValue = (a.titel || '').toLowerCase();
                bValue = (b.titel || '').toLowerCase();
                break;
            case 'company_name':
                aValue = (a.company_name || '').toLowerCase();
                bValue = (b.company_name || '').toLowerCase();
                break;
            case 'customer_name':
                aValue = (a.customer_name || '').toLowerCase();
                bValue = (b.customer_name || '').toLowerCase();
                break;
            case 'device_name':
                aValue = (a.device_name || '').toLowerCase();
                bValue = (b.device_name || '').toLowerCase();
                break;
            case 'status':
                aValue = (a.status || '').toLowerCase();
                bValue = (b.status || '').toLowerCase();
                break;
            case 'erstellt_von':
                const aErsteller = [(a.ersteller_vorname || ''), (a.ersteller_nachname || '')].filter(Boolean).join(' ').trim().toLowerCase();
                const bErsteller = [(b.ersteller_vorname || ''), (b.ersteller_nachname || '')].filter(Boolean).join(' ').trim().toLowerCase();
                aValue = aErsteller || 'zzz';
                bValue = bErsteller || 'zzz';
                break;
            case 'zugewiesen_an':
                const aZugewiesen = [(a.zugewiesen_vorname || ''), (a.zugewiesen_nachname || '')].filter(Boolean).join(' ').trim().toLowerCase();
                const bZugewiesen = [(b.zugewiesen_vorname || ''), (b.zugewiesen_nachname || '')].filter(Boolean).join(' ').trim().toLowerCase();
                aValue = aZugewiesen || 'zzz';
                bValue = bZugewiesen || 'zzz';
                break;
            case 'naechster_termin':
                aMissing = !(a.naechster_termin && a.naechster_termin.start_datum);
                bMissing = !(b.naechster_termin && b.naechster_termin.start_datum);
                aValue = aMissing ? null : new Date(a.naechster_termin.start_datum);
                bValue = bMissing ? null : new Date(b.naechster_termin.start_datum);
                break;
            case 'erstellt_datum':
                aValue = new Date(a.erstellt_datum || 0);
                bValue = new Date(b.erstellt_datum || 0);
                break;
            case 'geaendert_datum':
                // Falls geaendert_datum NULL oder leer ist, verwende erstellt_datum (wie in SQL COALESCE)
                aValue = (a.geaendert_datum && a.geaendert_datum !== '0000-00-00 00:00:00') 
                    ? new Date(a.geaendert_datum) 
                    : (a.erstellt_datum ? new Date(a.erstellt_datum) : new Date(0));
                bValue = (b.geaendert_datum && b.geaendert_datum !== '0000-00-00 00:00:00') 
                    ? new Date(b.geaendert_datum) 
                    : (b.erstellt_datum ? new Date(b.erstellt_datum) : new Date(0));
                break;
            default:
                return 0;
        }
        
        // Leere Termine immer nach unten, unabhaengig von der Sortierrichtung
        if (column === 'naechster_termin' && aMissing !== bMissing) {
            return aMissing ? 1 : -1;
        }
        
        // Vergleich
        let comparison = 0;
        if (aValue < bValue) {
            comparison = -1;
        } else if (aValue > bValue) {
            comparison = 1;
        }
        
        return sortDirection === 'asc' ? comparison : -comparison;
    });
    
    if (updateUI) {
        updateSortIcons();
        displayTickets(filteredTickets);
        // Sortier-Text im Dropdown aktualisieren
        const sortDropdownText = document.getElementById('sort-dropdown-text');
        if (sortDropdownText && sortColumn) {
            const sortLabels = {
                'erstellt_datum': 'Erstellt',
                'geaendert_datum': 'Geändert',
                'naechster_termin': 'Nächster Termin'
            };
            const label = sortLabels[sortColumn] || 'Sortieren';
            const directionText = sortDirection === 'asc' ? '' : '';
            sortDropdownText.textContent = label + directionText;
        }
    }
}

function updateSortIcons() {
    // Alle Sortier-Icons zurücksetzen
    document.querySelectorAll('[data-sort] .sort-icon').forEach(icon => {
        icon.style.display = 'none';
    });
    
    // Aktuelles Sortier-Icon anzeigen
    if (sortColumn) {
        const th = document.querySelector(`[data-sort="${sortColumn}"]`);
        if (th) {
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                icon.style.display = 'block';
                // Richtung des Icons ändern
                if (sortDirection === 'asc') {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>';
                } else {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';
                }
            }
        }
    }
    
    updateDisplaySortDirectionSegments();

    // Sortier-Optionen im Dropdown: ausgewählte Option gut lesbar (Light: hellblau + dunkelblau, Dark: heller Text auf dunklem Grund)
    const sortOptions = document.querySelectorAll('.sort-option');
    sortOptions.forEach(option => {
        const sortType = option.getAttribute('data-sort');
        if (sortType === sortColumn) {
            option.classList.add('font-medium', 'bg-blue-50', 'text-blue-800', 'dark:bg-primary-800', 'dark:text-primary-200');
            option.classList.remove('text-gray-700');
        } else {
            option.classList.remove('font-medium', 'bg-blue-50', 'text-blue-800', 'dark:bg-primary-800', 'dark:text-primary-200');
            option.classList.add('text-gray-700', 'dark:text-primary-200');
        }
    });
}

/** Spalten: Auftrag, Anforderer, Gerät, [Kunde], Status, Termin, [Bearbeiter] */
function getTicketsTableColspan() {
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') ? 'true' : 'false'; ?>;
    const showZugewiesen = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
    return 5 + (showCompany ? 1 : 0) + (showZugewiesen ? 1 : 0);
}

/** Skeleton-UI beim Laden / Neuladen der Ticket-Liste (alle Ansichten) */
function setTicketsLoadingSkeletons() {
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') ? 'true' : 'false'; ?>;
    const showZugewiesen = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;

    const mobile = document.getElementById('mobileTicketsList');
    if (mobile) {
        let m = '';
        for (let i = 0; i < 7; i++) {
            m += '<div class="ticket-skeleton-mobile animate-pulse rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-3">' +
                '<div class="flex gap-3">' +
                '<div class="h-12 w-12 shrink-0 rounded-lg bg-gray-200 dark:bg-primary-140"></div>' +
                '<div class="min-w-0 flex-1 space-y-2">' +
                '<div class="h-4 rounded-md bg-gray-200 dark:bg-primary-140 max-w-[88%]"></div>' +
                '<div class="h-3 rounded-md bg-gray-100 dark:bg-primary-120 max-w-[55%]"></div>' +
                '<div class="h-3 rounded-md bg-gray-100 dark:bg-primary-120 max-w-[70%]"></div>' +
                '</div></div></div>';
        }
        mobile.setAttribute('aria-busy', 'true');
        mobile.innerHTML = m;
    }

    const tbody = document.getElementById('ticketsList');
    if (tbody) {
        let rows = '';
        for (let r = 0; r < 8; r++) {
            rows += '<tr class="ticket-skeleton-row animate-pulse">' +
                '<td class="px-3 py-4"><div class="h-4 rounded-md bg-gray-200 dark:bg-primary-140 max-w-[90%] mb-2"></div><div class="h-3 rounded-md bg-gray-100 dark:bg-primary-120 w-24"></div></td>' +
                '<td class="px-3 py-3"><div class="h-4 rounded-md bg-gray-200 dark:bg-primary-140 w-28"></div></td>' +
                '<td class="px-3 py-3"><div class="h-4 rounded-md bg-gray-200 dark:bg-primary-140 w-32"></div></td>' +
                (showCompany ? '<td class="px-3 py-3"><div class="h-4 rounded-md bg-gray-200 dark:bg-primary-140 w-36"></div></td>' : '') +
                '<td class="px-3 py-3"><div class="h-6 rounded-full bg-gray-200 dark:bg-primary-140 w-20"></div></td>' +
                '<td class="px-3 py-3"><div class="h-4 rounded-md bg-gray-200 dark:bg-primary-140 w-28"></div></td>' +
                (showZugewiesen ? '<td class="px-1 py-3 text-center"><div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-primary-140 mx-auto"></div></td>' : '') +
                '</tr>';
        }
        tbody.setAttribute('aria-busy', 'true');
        tbody.innerHTML = rows;
    }

    const cards = document.getElementById('ticketCards');
    if (cards) {
        let c = '';
        for (let i = 0; i < 6; i++) {
            c += '<div class="ticket-skeleton-card animate-pulse rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">' +
                '<div class="flex justify-between gap-3 mb-3">' +
                '<div class="h-5 rounded-md bg-gray-200 dark:bg-primary-140 flex-1 max-w-[70%]"></div>' +
                '<div class="h-6 w-20 rounded-full bg-gray-200 dark:bg-primary-140 shrink-0"></div></div>' +
                '<div class="space-y-2 mb-3">' +
                '<div class="h-3 rounded bg-gray-100 dark:bg-primary-120 w-full"></div>' +
                '<div class="h-3 rounded bg-gray-100 dark:bg-primary-120 w-[85%]"></div></div>' +
                '<div class="flex flex-wrap gap-2">' +
                '<div class="h-8 w-24 rounded-lg bg-gray-200 dark:bg-primary-140"></div>' +
                '<div class="h-8 w-28 rounded-lg bg-gray-100 dark:bg-primary-120"></div></div></div>';
        }
        cards.setAttribute('aria-busy', 'true');
        cards.innerHTML = c;
    }

    const chatList = document.getElementById('chatTicketList');
    if (chatList) {
        let ch = '';
        for (let i = 0; i < 9; i++) {
            ch += '<li class="flex items-start gap-3 px-4 py-3 animate-pulse border-b border-gray-100 dark:border-primary-140/60">' +
                '<div class="h-10 w-10 shrink-0 rounded-full bg-gray-200 dark:bg-primary-140"></div>' +
                '<div class="min-w-0 flex-1 space-y-2 pt-0.5">' +
                '<div class="flex justify-between gap-2">' +
                '<div class="h-4 rounded-md bg-gray-200 dark:bg-primary-140 flex-1 max-w-[65%]"></div>' +
                '<div class="h-3 w-10 rounded bg-gray-100 dark:bg-primary-120 shrink-0"></div></div>' +
                '<div class="h-3 rounded bg-gray-100 dark:bg-primary-120 w-full"></div>' +
                '<div class="h-3 rounded bg-gray-100 dark:bg-primary-120 w-[80%]"></div></div></li>';
        }
        chatList.setAttribute('aria-busy', 'true');
        chatList.classList.add('ticket-skeleton-chat-list');
        chatList.innerHTML = ch;
    }
}

function showError(message, allowHtml = false) {
    const tbody = document.getElementById('ticketsList');
    const cardsContainer = document.getElementById('ticketCards');
    const mobileTicketsList = document.getElementById('mobileTicketsList');
    const chatTicketList = document.getElementById('chatTicketList');
    const colspan = getTicketsTableColspan();
    const safeMessage = allowHtml ? String(message || '') : escapeHtml(String(message || ''));
    
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-4 py-4 text-center text-red-500">${safeMessage}</td></tr>`;
    }
    if (cardsContainer) {
        cardsContainer.innerHTML = `<div class="col-span-full text-center text-red-500 py-8">${safeMessage}</div>`;
    }
    if (mobileTicketsList) {
        mobileTicketsList.innerHTML = `<div class="text-center text-red-500 py-8">${safeMessage}</div>`;
    }
    if (chatTicketList) {
        chatTicketList.innerHTML = `<li class="px-4 py-4 text-center text-red-500 dark:text-red-400 text-sm">${safeMessage}</li>`;
    }
}

function updateDisplayViewSegments() {
    document.querySelectorAll('.display-view-option').forEach(function(btn) {
        const isActive = btn.getAttribute('data-view') === currentView;
        btn.classList.toggle('display-view-option--active', isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function updateDisplaySortDirectionSegments() {
    const labels = sortColumn === 'naechster_termin'
        ? { desc: 'Späteste zuerst', asc: 'Früheste zuerst' }
        : { desc: 'Neueste zuerst', asc: 'Älteste zuerst' };
    const descEl = document.getElementById('sort-dir-label-desc');
    const ascEl = document.getElementById('sort-dir-label-asc');
    if (descEl) descEl.textContent = labels.desc;
    if (ascEl) ascEl.textContent = labels.asc;
    document.querySelectorAll('.display-sort-dir-option').forEach(function(btn) {
        const dir = btn.getAttribute('data-direction');
        const isActive = !!sortColumn && dir === sortDirection;
        btn.classList.toggle('display-view-option--active', isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        btn.disabled = !sortColumn;
        btn.classList.toggle('opacity-50', !sortColumn);
    });
}

function initDisplaySortDirectionSegments() {
    document.querySelectorAll('.display-sort-dir-option').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dir = this.getAttribute('data-direction');
            if (!sortColumn || (dir !== 'asc' && dir !== 'desc') || sortDirection === dir) return;
            sortDirection = dir;
            sortTickets(sortColumn, true, false);
            saveFiltersState();
        });
    });
}

function switchView(view, saveToStorage = true) {
    currentView = view;
    if (saveToStorage) {
        localStorage.setItem('ticketsView', view);
    }
    
    // Alle Ansichten verstecken
    document.getElementById('tableView').classList.add('hidden');
    document.getElementById('cardsView').classList.add('hidden');
    document.getElementById('chatView').classList.add('hidden');
    
    updateDisplayViewSegments();
    
    // Aktuelle Ansicht anzeigen
    var htmlEl = document.documentElement;
    if (view === 'table') {
        document.getElementById('tableView').classList.remove('hidden');
        document.body.classList.remove('service-chat-view-active');
        htmlEl.classList.remove('service-chat-view-active');
    } else if (view === 'cards') {
        document.getElementById('cardsView').classList.remove('hidden');
        document.body.classList.remove('service-chat-view-active');
        htmlEl.classList.remove('service-chat-view-active');
    } else if (view === 'chat') {
        document.getElementById('chatView').classList.remove('hidden');
        document.body.classList.add('service-chat-view-active');
        htmlEl.classList.add('service-chat-view-active');
    } else {
        document.body.classList.remove('service-chat-view-active');
        htmlEl.classList.remove('service-chat-view-active');
    }
    
    displayTickets(filteredTickets);
}

function displayTickets(tickets) {
    if (!Array.isArray(tickets)) tickets = [];
    const isMobile = isMobileView();
    if (isMobile) {
        var container = document.getElementById('mobileTicketsList');
        if (container) {
            try {
                displayMobileCompactTickets(tickets);
            } catch (err) {
                console.error('displayMobileCompactTickets:', err);
                container.innerHTML = '<div class="text-center text-red-500 py-8">Fehler beim Anzeigen der Tickets.</div>';
            }
        }
        var wrap = document.getElementById('mobileTicketsWrap');
        if (wrap) {
            wrap.classList.remove('hidden');
            wrap.style.display = '';
            wrap.style.visibility = 'visible';
        }
        var tv = document.getElementById('tableView');
        var cv = document.getElementById('cardsView');
        var chv = document.getElementById('chatView');
        if (tv) tv.classList.add('hidden');
        if (cv) cv.classList.add('hidden');
        if (chv) chv.classList.add('hidden');
        requestTicketListScrollRestore(false);
        return;
    }
    var mwrap = document.getElementById('mobileTicketsWrap');
    if (mwrap) mwrap.classList.add('hidden');
    if (currentView === 'table') {
        displayTableView(tickets);
    } else if (currentView === 'cards') {
        displayCardsView(tickets);
    } else if (currentView === 'chat') {
        displayChatView(tickets);
    }
    requestTicketListScrollRestore(false);
}

/** SVG-Icon für Gerätetyp (mobil, kompakt) – gleiche Typ-Schlüssel wie devices/detail.php */
function getTicketMobileDeviceTypeIconHtml(typ) {
    var t = (typ || '').toString().toLowerCase().trim();
    var c = 'w-4 h-4 shrink-0 text-gray-600 dark:text-primary-200';
    var icons = {
        drucker: '<svg class="' + c + '" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>',
        computer: '<svg class="' + c + '" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        netzwerk: '<svg class="' + c + '" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>',
        smartphone: '<svg class="' + c + '" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
        monitor: '<svg class="' + c + '" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        divers: '<svg class="' + c + '" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'
    };
    return icons[t] || icons.divers;
}

function ticketsHasUserAppliedListFilters() {
    var customerEl = document.getElementById('customer-filter');
    var assigneeEl = document.getElementById('assignee-filter');
    var statusEl = document.getElementById('status-filter');
    var searchEl = document.getElementById('search');
    var mobileSearchEl = document.getElementById('tickets-mobile-search');
    var customerOn = !!(customerEl && customerEl.value);
    var assigneeOn = !!(assigneeEl && assigneeEl.value);
    var statusVal = statusEl ? (statusEl.value || 'offen_combined') : 'offen_combined';
    var searchOn = !!((searchEl && searchEl.value.trim()) || (mobileSearchEl && mobileSearchEl.value.trim()));
    // Firmenfilter in der Nav zählt nur für Admin/Techniker als aktiver Listenfilter
    var companyOn = isAdminOrTech && typeof selectedCompanyId !== 'undefined' && !!selectedCompanyId;
    var hash = (window.location.hash || '').replace(/^#/, '');
    var hashActive = hash === 'angeheftet' || (typeof HASH_FILTERS !== 'undefined' && HASH_FILTERS.indexOf(hash) !== -1);
    var advOn = Array.isArray(advancedFilterRules) && advancedFilterRules.some(function(r) {
        return r && r.field && r.operator && (r.operator === 'empty' || r.operator === 'not_empty' || (r.value !== '' && r.value != null));
    });
    return customerOn || assigneeOn || statusVal !== 'offen_combined' || searchOn || companyOn || hashActive || advOn;
}

function ticketsEmptyStateIsFiltered() {
    // Tickets vorhanden, aber aktuelle Ansicht/Filter blendet alle aus
    if (Array.isArray(allTickets) && allTickets.length > 0 && Array.isArray(filteredTickets) && filteredTickets.length === 0) {
        return true;
    }
    return ticketsHasUserAppliedListFilters();
}

function ticketsResetListFilters() {
    var searchEl = document.getElementById('search');
    var mobileSearchEl = document.getElementById('tickets-mobile-search');
    if (searchEl) searchEl.value = '';
    if (mobileSearchEl) mobileSearchEl.value = '';
    var statusInput = document.getElementById('status-filter');
    var statusText = document.getElementById('status-filter-text');
    if (statusInput) statusInput.value = 'offen_combined';
    if (statusText) statusText.textContent = 'Offen';
    try { localStorage.setItem('ticketsStatusFilter', 'offen_combined'); } catch (e) {}
    var customerFilter = document.getElementById('customer-filter');
    var customerFilterText = document.getElementById('customer-filter-text');
    if (customerFilter) customerFilter.value = '';
    if (customerFilterText) customerFilterText.textContent = 'Alle Kunden';
    var assigneeFilter = document.getElementById('assignee-filter');
    var assigneeFilterText = document.getElementById('assignee-filter-text');
    if (assigneeFilter) assigneeFilter.value = '';
    if (assigneeFilterText) assigneeFilterText.textContent = 'Alle Bearbeiter';
    advancedFilterRules = [];
    advancedFilterRulesDraft = [];
    if (typeof updateStatusFilterButtonState === 'function') updateStatusFilterButtonState();
    if (typeof updateCustomerFilterButtonState === 'function') updateCustomerFilterButtonState();
    if (typeof updateAssigneeFilterButtonState === 'function') updateAssigneeFilterButtonState();
    if (typeof updateAdvancedFilterButtonState === 'function') updateAdvancedFilterButtonState();
    if (typeof syncTicketsMobileSearchFieldMirrors === 'function') syncTicketsMobileSearchFieldMirrors();
    if (typeof updateSearchActiveState === 'function') updateSearchActiveState();
    if (typeof saveFiltersState === 'function') saveFiltersState();
    if (window.location.hash) {
        window.history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    if (typeof loadTickets === 'function') loadTickets();
    else if (typeof filterTickets === 'function') filterTickets();
}

function renderTicketsEmptyState(opts) {
    opts = opts || {};
    var variant = opts.variant || 'default';
    var filtered = ticketsEmptyStateIsFiltered();
    var title = filtered ? 'Keine passenden Tickets' : 'Sie haben noch kein Ticket erstellt';
    var description = filtered
        ? 'Für die aktuellen Filter gibt es keine Treffer. Passen Sie die Auswahl an oder setzen Sie die Filter zurück.'
        : 'Erstellen Sie jetzt Ihr erstes Ticket – beschreiben Sie Ihr Anliegen, wir kümmern uns darum.';
    var titleClass = variant === 'compact'
        ? 'text-lg font-semibold text-gray-900 dark:text-primary-100'
        : 'text-xl font-semibold text-gray-900 dark:text-primary-100 sm:text-2xl';
    var descClass = variant === 'compact'
        ? 'mt-2 max-w-sm text-base leading-relaxed text-gray-500 dark:text-primary-210'
        : 'mt-2 max-w-md text-base leading-relaxed text-gray-500 dark:text-primary-210 sm:text-lg';
    var imgClass = variant === 'compact'
        ? 'mx-auto w-auto max-w-[12rem] h-28'
        : 'mx-auto w-auto max-w-[16rem] h-40';
    var wrapClass = variant === 'compact'
        ? 'tickets-empty-state flex flex-col items-center justify-center px-3 py-6 text-center'
        : 'tickets-empty-state flex flex-col items-center justify-center px-4 py-10 text-center';
    var ctaHtml = filtered
        ? '<button type="button" onclick="ticketsResetListFilters()" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 dark:border-primary-320 dark:bg-primary-700/80 dark:text-primary-100 dark:hover:bg-primary-760">Filter zurücksetzen</button>'
        : '<a href="' + ticketsCreateUrl + '" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 dark:bg-primary-500 dark:hover:bg-primary-400"><svg class="h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>Ticket erstellen</a>';
    return ''
        + '<div class="' + wrapClass + '">'
        + '<div class="mb-4 flex items-center justify-center">'
        + '<img src="' + ticketsEmptyIllustrationUrl + '" alt="" class="' + imgClass + '" width="556" height="421" aria-hidden="true" loading="lazy" decoding="async">'
        + '</div>'
        + '<h3 class="' + titleClass + '">' + title + '</h3>'
        + '<p class="' + descClass + '">' + description + '</p>'
        + '<div class="mt-5">' + ctaHtml + '</div>'
        + '</div>';
}

function displayMobileCompactTickets(tickets) {
    var container = document.getElementById('mobileTicketsList');
    if (!container) {
        return;
    }
    if (!Array.isArray(tickets)) tickets = [];
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') ? 'true' : 'false'; ?>;
    const showZugewiesen = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
    if (tickets.length === 0) {
        // Vor dem ersten API-Load Lade-Anzeige (Skeleton) beibehalten
        if (!ticketsLoadedOnce) return;
        container.removeAttribute('aria-busy');
        container.innerHTML = renderTicketsEmptyState({ variant: 'default' });
        return;
    }
    container.removeAttribute('aria-busy');
    container.innerHTML = tickets.map(function(ticket) {
        const statusBadge = getStatusBadge(ticket.status);
        let customerFirma = '';
        const customerName = ticket.customer_name || '';
        const companyName = ticket.company_name || '';
        if (!showCompany) {
            customerFirma = customerName ? escapeHtml(customerName) : '–';
        } else {
            if (customerName && companyName) customerFirma = escapeHtml(customerName) + ' · ' + escapeHtml(companyName);
            else if (companyName) customerFirma = escapeHtml(companyName);
            else if (customerName) customerFirma = escapeHtml(customerName);
            else customerFirma = '–';
        }
        const hersteller = (ticket.device_hersteller || '').trim();
        const modell = (ticket.device_modell || '').trim();
        const typRaw = (ticket.device_typ || '').trim();
        const deviceNameRaw = (ticket.device_name || '').trim();
        const standortRaw = (ticket.device_beschreibung || '').trim();
        const deviceName = deviceNameRaw ? escapeHtml(deviceNameRaw) : '';
        let hwLine = '';
        if (hersteller && modell) hwLine = escapeHtml(hersteller) + ' · ' + escapeHtml(modell);
        else if (hersteller) hwLine = escapeHtml(hersteller);
        else if (modell) hwLine = escapeHtml(modell);
        const standortEsc = standortRaw ? escapeHtml(standortRaw) : '';
        const deviceTipParts = [];
        if (deviceNameRaw) deviceTipParts.push(deviceNameRaw);
        if (hersteller) deviceTipParts.push(hersteller);
        if (modell) deviceTipParts.push(modell);
        if (typRaw) deviceTipParts.push(typRaw);
        if (standortRaw) deviceTipParts.push('Standort: ' + standortRaw);
        const deviceTooltip = escapeHtml(deviceTipParts.join(' · '));
        let anfordererShort = [ticket.ersteller_vorname, ticket.ersteller_nachname].filter(Boolean).join(' ').trim();
        anfordererShort = anfordererShort ? escapeHtml(anfordererShort) : '';
        const prio = (ticket.prioritaet || 'normal').toLowerCase();
        let prioBadge = '';
        if (prio === 'hoch' || prio === 'kritisch') {
            prioBadge = '<div class="ticket-mobile-card-prio shrink-0">' + getPrioritaetBadge(prio) + '</div>';
        }
        const viewUrl = '<?php echo addslashes(BASE_URL); ?>tickets/view.php?id=' + ticket.id;
        const unreadN = parseInt(ticket.unread_comments_count, 10) || 0;
        const unreadRem = ticket.unread_reminder === 1 || ticket.unread_reminder === '1';
        let unread = '';
        if (unreadN > 0) {
            unread = '<span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 text-[10px] font-bold leading-none text-white bg-red-600 rounded-full">' + (unreadN > 99 ? '99+' : unreadN) + '</span>';
        } else if (unreadRem) {
            unread = '<span class="inline-flex w-2 h-2 min-w-[0.5rem] shrink-0 rounded-full bg-red-600" title="Hervorgehoben" aria-hidden="true"></span>';
        }
        let deviceBlockInner = '';
        if (deviceName) {
            deviceBlockInner += '<div class="text-[11px] font-semibold text-gray-900 dark:text-primary-200 leading-tight line-clamp-2">' + deviceName + '</div>';
        }
        if (hwLine) {
            const subCls = deviceName
                ? 'text-[10px] text-gray-600 dark:text-primary-220 leading-snug mt-0.5 line-clamp-2'
                : 'text-[11px] font-semibold text-gray-900 dark:text-primary-200 leading-tight line-clamp-2';
            deviceBlockInner += '<div class="' + subCls + '">' + hwLine + '</div>';
        }
        if (standortEsc) {
            deviceBlockInner += '<div class="flex items-start gap-1 mt-0.5 pt-0.5 border-t border-gray-200/70 dark:border-primary-120/35 text-[10px] text-gray-600 dark:text-primary-210 leading-snug line-clamp-2">' +
                '<svg class="w-3 h-3 shrink-0 mt-0.5 text-gray-500 dark:text-primary-250" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>' +
                '<span class="min-w-0">' + standortEsc + '</span></div>';
        }
        if (!deviceBlockInner && typRaw) {
            deviceBlockInner = '<div class="text-[11px] font-semibold text-gray-900 dark:text-primary-200 leading-tight">' + escapeHtml(capitalizeFirst(typRaw)) + '</div>';
        }
        const showDeviceRow = !!deviceBlockInner;
        const typeIconHtml = getTicketMobileDeviceTypeIconHtml(typRaw);
        const deviceRow = showDeviceRow
            ? '<div class="ticket-mobile-device-inset flex min-w-0 items-start gap-2 rounded-lg px-2 py-1"' + (deviceTooltip ? ' title="' + deviceTooltip + '"' : '') + '>' +
                '<div class="flex w-8 shrink-0 justify-center pt-0.5 text-gray-600 dark:text-primary-220" aria-hidden="true">' + typeIconHtml + '</div>' +
                '<div class="min-w-0 flex-1">' + deviceBlockInner + '</div></div>'
            : '';
        const footerAnf = anfordererShort
            ? '<span class="text-[10px] font-medium text-gray-700 dark:text-primary-240 truncate max-w-[52%] text-right" title="' + anfordererShort + '">' + anfordererShort + '</span>'
            : '<span class="text-[10px] text-gray-400 dark:text-primary-250 shrink-0">–</span>';
        const ticketNrClasses = 'ticket-nummer-meta truncate min-w-0';
        const zugRaw = ticket.zugewiesen_an;
        let zugAttr = '0';
        if (zugRaw != null && zugRaw !== '' && String(zugRaw) !== '0') {
            const zn = parseInt(zugRaw, 10);
            if (!isNaN(zn) && zn > 0) zugAttr = String(zn);
        }
        const compIdStr = (ticket.company_id != null && ticket.company_id !== '') ? String(ticket.company_id) : '';
        const showStatusSwipe = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
        const showTerminSwipe = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
        const firstSwipeCol = showTerminSwipe
            ? '<button type="button" class="ticket-swipe-action flex flex-1 h-full min-w-0 items-center justify-center bg-sky-600 hover:bg-sky-700 text-white dark:bg-sky-700 dark:hover:bg-sky-600 rounded-l-xl border-0 p-0" data-swipe-act="termin" onclick="event.stopPropagation(); ticketSwipeGoToTermin(' + ticket.id + ');" aria-label="Termin hinzufügen" title="Termin hinzufügen">' +
              '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
              '</button>'
            : '<button type="button" class="ticket-swipe-action flex flex-1 h-full min-w-0 items-center justify-center bg-sky-600 hover:bg-sky-700 text-white dark:bg-sky-700 dark:hover:bg-sky-600 rounded-l-xl border-0 p-0" data-swipe-act="pin" onclick="event.stopPropagation(); ticketSwipePin(' + ticket.id + ');" aria-label="Anheften" title="Anheften">' +
              '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>' +
              '</button>';
        const assignSwipeCol = showZugewiesen
            ? '<label class="ticket-swipe-action relative flex flex-1 h-full min-w-0 cursor-pointer touch-manipulation items-center justify-center bg-violet-600 hover:bg-violet-700 text-white dark:bg-violet-700 dark:hover:bg-violet-600 border-0 p-0 m-0" data-swipe-act="assign" onclick="event.stopPropagation();" aria-label="Bearbeiter" title="Bearbeiter">' +
              '<select class="ticket-swipe-assign-select absolute inset-0 z-10 h-full w-full min-h-[2.75rem] cursor-pointer border-0 p-0 m-0 bg-transparent text-base opacity-[0.04] text-gray-900" style="-webkit-appearance:none;appearance:none" data-ticket-id="' + ticket.id + '" data-company-id="' + escapeHtml(compIdStr) + '" autocomplete="off">' +
              '<option value="">Laden…</option></select>' +
              '<span class="pointer-events-none relative z-0 flex h-full w-full items-center justify-center">' +
              '<svg class="w-6 h-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/></svg></span></label>'
            : '';
        const statusSwipeCol = showStatusSwipe
            ? '<div class="flex h-full w-14 shrink-0">' +
              '<label class="ticket-swipe-action relative flex h-full w-full cursor-pointer touch-manipulation items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white dark:bg-indigo-700 dark:hover:bg-indigo-600 rounded-r-xl border-0 p-0 m-0" data-swipe-act="status" onclick="event.stopPropagation();" aria-label="Status ändern" title="Status ändern">' +
              '<select class="ticket-swipe-status-select absolute inset-0 z-10 h-full w-full min-h-[2.75rem] cursor-pointer border-0 p-0 m-0 bg-transparent text-base opacity-[0.04] text-gray-900" style="-webkit-appearance:none;appearance:none" data-ticket-id="' + ticket.id + '" autocomplete="off">' +
              '<option value="">Laden…</option></select>' +
              '<span class="pointer-events-none relative z-0 flex h-full w-full items-center justify-center">' +
              '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.583 8.445h.01M10.86 19.71l-6.573-6.63a.993.993 0 0 1 0-1.4l7.329-7.394A.98.98 0 0 1 12.31 4l5.734.007A1.968 1.968 0 0 1 20 5.983v5.5a.992.992 0 0 1-.316.727l-7.44 7.5a.974.974 0 0 1-1.384.001Z"/></svg></span></label></div>'
            : '';
        return `
        <div class="ticket-mobile-item relative overflow-hidden rounded-xl border border-gray-200/90 dark:border-primary-120 max-lg:touch-manipulation" data-ticket-id="${ticket.id}" data-zugewiesen-an="${zugAttr}">
            <div class="ticket-swipe-actions-layer absolute inset-0 z-0 flex flex-row lg:hidden opacity-0 pointer-events-none transition-opacity duration-150" aria-hidden="true">
                <div class="flex h-full min-h-0 w-[7rem] shrink-0">
                    ${firstSwipeCol}
                    ${assignSwipeCol}
                </div>
                <div class="min-w-0 flex-1" aria-hidden="true"></div>
                ${statusSwipeCol}
            </div>
            <div class="ticket-swipe-track ticket-mobile-compact-card relative z-[1] block w-full min-w-0 text-left bg-white dark:bg-primary-100 cursor-pointer overflow-hidden transition-colors outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/35 dark:focus-visible:ring-primary-400/40 active:scale-[0.99]" data-swipe-x="0" style="transform:translateZ(0) translateX(0)" role="link" tabindex="0" data-ticket-view-url="${escapeHtml(viewUrl)}" onclick="ticketSwipeTrackClick(${ticket.id}, event)">
          <div class="px-2.5 pt-1.5 pb-2 flex flex-col gap-2 min-w-0">
            <div class="flex items-start gap-2 min-w-0">
              <p class="text-[15px] font-semibold leading-snug text-gray-900 dark:text-primary-200 line-clamp-2 min-w-0 flex-1">${escapeHtml(ticket.titel)}</p>
              <div class="flex shrink-0 items-center justify-end gap-1 min-w-0 flex-wrap">${prioBadge}${unread}<div class="ticket-mobile-card-status">${statusBadge}</div></div>
            </div>
            <p class="text-[11px] font-medium text-gray-800 dark:text-primary-200 leading-relaxed truncate min-w-0" title="${customerFirma}">${customerFirma}</p>
            ${deviceRow}
            <div class="flex items-center justify-between gap-2 pt-1.5 mt-0.5 border-t border-gray-100 dark:border-primary-120/60 min-w-0">
              <span class="${ticketNrClasses}">${escapeHtml(ticket.ticket_nummer)}</span>
              ${footerAnf}
            </div>
          </div>
            </div>
        </div>`;
    }).join('');
}

function displayTableView(tickets) {
    const tbody = document.getElementById('ticketsList');
    if (!tbody) return;
    if (!ticketsLoadedOnce && (!Array.isArray(tickets) || tickets.length === 0)) {
        return;
    }
    tbody.removeAttribute('aria-busy');
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') ? 'true' : 'false'; ?>;
    const showZugewiesen = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
    
    // Spaltenanzahl berechnen:
    // - Auftrag (immer)
    // - Von (immer)
    // - Gerät (immer)
    // - Kunde/Firma (wenn showCompany)
    // - Status (immer)
    // - Zugewiesen (wenn showZugewiesen)
    // - Fällig/Geplant (immer)
    const colspan = 5 + (showCompany ? 1 : 0) + (showZugewiesen ? 1 : 0);
    
    if (tickets.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-4 py-8">${renderTicketsEmptyState({ variant: 'default' })}</td></tr>`;
        return;
    }
    
    tbody.innerHTML = tickets.map(ticket => {
        const statusBadge = getStatusBadge(ticket.status);
        const isPinned = ticket.is_pinned === 1 || ticket.is_pinned === '1' || ticket.is_pinned === true;
        
        // Ersteller (Von)
        let erstellerName = '-';
        if (ticket.ersteller_vorname || ticket.ersteller_nachname) {
            erstellerName = [ticket.ersteller_vorname, ticket.ersteller_nachname].filter(Boolean).join(' ').trim();
        }

        // Gerät (immer anzeigen)
        const deviceName = ticket.device_name || '-';
        const deviceType = ticket.device_typ ? capitalizeFirst(ticket.device_typ) : '';
        const deviceCell = `
            <td class="px-3 py-3 whitespace-nowrap">
                <div class="flex flex-col">
                    <span class="text-sm text-gray-900 dark:text-white font-medium">${escapeHtml(deviceName)}</span>
                    ${deviceType ? `<span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(deviceType)}</span>` : ''}
                </div>
            </td>
        `;
        
        // Firma/Kunde kombinieren: Kunde oben, Firma unten, oder nur Firma wenn kein Kunde
        let companyCustomerCell = '';
        if (showCompany) {
            const customerName = ticket.customer_name || '';
            const companyName = ticket.company_name || '';
            
            if (customerName) {
                // Beide vorhanden: Kunde oben, Firma unten
                companyCustomerCell = `
                    <td class="px-3 py-3 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="text-sm text-gray-900 dark:text-white font-medium">${escapeHtml(customerName)}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(companyName)}</span>
                        </div>
                    </td>
                `;
            } else if (companyName) {
                // Nur Firma vorhanden
                companyCustomerCell = `
                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        ${escapeHtml(companyName)}
                    </td>
                `;
            } else {
                companyCustomerCell = `<td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">-</td>`;
            }
        }
        
        // Bearbeiter-Avatar (nur für Admin/Techniker): rund, rechts neben Termin; bei keinem Bearbeiter kein Platzhalter
        let zugewiesenCell = '';
        if (showZugewiesen) {
            const hasAssignee = ticket.zugewiesen_an != null && (ticket.zugewiesen_vorname || ticket.zugewiesen_nachname || ticket.zugewiesen_logopfad);
            const assigneeName = [ticket.zugewiesen_vorname, ticket.zugewiesen_nachname].filter(Boolean).join(' ').trim();
            const avatarHtml = hasAssignee
                ? renderUserAvatarHtml(ticket.zugewiesen_logopfad || null, assigneeName || null, 'w-8 h-8 rounded-full object-cover')
                : '';
            zugewiesenCell = `<td class="w-12 px-1 py-3 align-middle text-center">${avatarHtml}</td>`;
        }
        
        // Nächsten Termin: immer anzeigen (Datum, darunter Titel + (+N)); gerade = hervorheben, kein weiterer = rot
        let naechsterTerminHtml = '-';
        let terminGerade = false;
        let terminLetzterKeineFolgenden = false;
        
        if (ticket.naechster_termin && ticket.naechster_termin.start_datum) {
            const terminDate = formatDateTimeRange(ticket.naechster_termin.start_datum, ticket.naechster_termin.ende_datum);
            const startDt = new Date(ticket.naechster_termin.start_datum);
            const endDt = new Date(ticket.naechster_termin.ende_datum || ticket.naechster_termin.start_datum);
            const now = new Date();
            
            terminGerade = (now >= startDt && now <= endDt);
            const anzahlZukuenftige = ticket.anzahl_zukuenftige_termine || 0;
            terminLetzterKeineFolgenden = (anzahlZukuenftige === 0) && ticket.status !== 'Geschlossen' && ticket.status !== 'Archiv';
            
            const terminTitel = ticket.naechster_termin.titel || '';
            const nochFolgen = Math.max(0, anzahlZukuenftige - 1);
            const folgenText = nochFolgen > 0 ? ` (+${nochFolgen})` : '';
            
            let terminClass = 'text-gray-900 dark:text-white';
            if (terminLetzterKeineFolgenden) terminClass = 'text-red-600 dark:text-red-400 font-medium';
            else if (terminGerade) terminClass = 'text-primary-600 dark:text-primary-400 font-medium';
            
            naechsterTerminHtml = `
                <div class="flex flex-col">
                    <span class="text-sm ${terminClass}">${escapeHtml(terminDate)}</span>
                    ${terminTitel || nochFolgen > 0 ? `<span class="text-xs mt-0.5 ${terminLetzterKeineFolgenden ? 'text-red-600 dark:text-red-400' : (terminGerade ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400')}">${escapeHtml(terminTitel)}${folgenText}</span>` : ''}
                </div>
            `;
        }
        
        // Projektnummer (falls Ticket einem Projekt zugeordnet ist) fuer Anzeige vor Ticketnummer
        let projektNummer = '';
        if (ticket.projects && Array.isArray(ticket.projects) && ticket.projects.length > 0) {
            projektNummer = ticket.projects[0].project_nummer || ticket.projects[0].nummer || '';
        } else if (ticket.projects && typeof ticket.projects === 'object') {
            projektNummer = ticket.projects.project_nummer || ticket.projects.nummer || '';
        }
        const ticketNummerAnzeige = projektNummer
            ? `${projektNummer} · ${ticket.ticket_nummer || ''}`
            : (ticket.ticket_nummer || '');
        
        const unreadCount = ticket.unread_comments_count || 0;
        const unreadRem = ticket.unread_reminder === 1 || ticket.unread_reminder === '1';
        const pinIconHtml = isPinned ? `
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" title="Angeheftet">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M6 3a1 1 0 00-.894 1.447L6.382 7H5a1 1 0 000 2h2.028l1.69 4.472a1 1 0 001.864 0L12.272 9H15a1 1 0 100-2h-1.382l1.276-2.553A1 1 0 0013 3H6z"/>
                </svg>
            </span>
        ` : '';
        
        const trClass = 'dark:bg-primary-100 hover:bg-gray-100 dark:hover:bg-[#323438] cursor-pointer';
        const viewUrl = '<?php echo addslashes(BASE_URL); ?>tickets/view.php?id=' + ticket.id;
        return `
            <tr class="${trClass}" data-ticket-id="${ticket.id}" data-ticket-view-url="${escapeHtml(viewUrl)}" onclick="navigateToTicketDetail(this.getAttribute('data-ticket-view-url'))">
                <td class="px-3 py-4 max-w-xs">
                    <div class="flex items-center gap-2">
                        <div class="flex flex-col flex-1 min-w-0">
                            <span class="text-gray-900 dark:text-white font-medium text-base truncate block" title="${escapeHtml(ticket.titel)}">
                                <span class="inline-flex items-center gap-1.5">
                                    ${pinIconHtml}
                                    <span class="truncate">${escapeHtml(ticket.titel)}</span>
                                </span>
                            </span>
                            <span class="ticket-nummer-meta block mt-0.5">${escapeHtml(ticketNummerAnzeige)}</span>
                        </div>
                        ${unreadCount > 0 ? `
                        <span class="flex-shrink-0 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full" title="${unreadCount} ungelesene Nachricht${unreadCount > 1 ? 'en' : ''}">
                            ${unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                        ` : (unreadRem ? `
                        <span class="flex-shrink-0 inline-flex w-2.5 h-2.5 rounded-full bg-red-600" title="Hervorgehoben" aria-hidden="true"></span>
                        ` : '')}
                    </div>
                </td>
                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    ${escapeHtml(erstellerName)}
                </td>
                ${deviceCell}
                ${companyCustomerCell}
                <td class="px-3 py-3">
                    ${statusBadge}
                </td>
                <td class="px-3 py-3 whitespace-nowrap text-sm">
                    ${naechsterTerminHtml}
                </td>
                ${zugewiesenCell}
            </tr>
        `;
    }).join('');
}

function displayCardsView(tickets) {
    const cardsContainer = document.getElementById('ticketCards');
    if (!cardsContainer) return;
    if (!ticketsLoadedOnce && (!Array.isArray(tickets) || tickets.length === 0)) {
        return;
    }
    cardsContainer.removeAttribute('aria-busy');
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') ? 'true' : 'false'; ?>;
    const showZugewiesen = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
    
    if (tickets.length === 0) {
        cardsContainer.innerHTML = '<div class="col-span-full">' + renderTicketsEmptyState({ variant: 'default' }) + '</div>';
        return;
    }
    
    cardsContainer.innerHTML = tickets.map(ticket => {
        const statusBadge = getStatusBadge(ticket.status);
        const isPinned = ticket.is_pinned === 1 || ticket.is_pinned === '1' || ticket.is_pinned === true;
        const unreadCount = ticket.unread_comments_count || 0;
        const unreadRem = ticket.unread_reminder === 1 || ticket.unread_reminder === '1';
        const observerText = ticket.observer_names || '-';

        const erstellerName = [ticket.ersteller_vorname || '', ticket.ersteller_nachname || ''].filter(Boolean).join(' ').trim() || '-';
        const zugewiesenText = [ticket.zugewiesen_vorname || '', ticket.zugewiesen_nachname || ''].filter(Boolean).join(' ').trim() || '-';
        const customerName = ticket.customer_name || '';
        const companyName = ticket.company_name || '';
        const deviceName = ticket.device_name || '-';
        const deviceType = ticket.device_typ ? capitalizeFirst(ticket.device_typ) : '';
            const manufacturerModel = [ticket.device_hersteller, ticket.device_modell].filter(Boolean).join(' / ');
        const serial = ticket.device_seriennummer || '-';
        const prioritaet = ticket.prioritaet ? capitalizeFirst(ticket.prioritaet) : '-';
        const deviceBeschreibung = ticket.device_beschreibung || '';
        const companyEmail = ticket.company_email || '';
        const companyTelefon = ticket.company_telefon || '';
        const customerEmail = ticket.customer_ansprechpartner_email || '';
        const customerTelefon = ticket.customer_ansprechpartner_telefon || '';
        const customerAnsprechpartner = [ticket.customer_ansprechpartner_vorname || '', ticket.customer_ansprechpartner_nachname || ''].filter(Boolean).join(' ').trim()
            || ticket.customer_ansprechpartner_manuell_name || '';

                    let companyAdresse = '';
                    if (ticket.company_adresse || ticket.company_plz || ticket.company_ort) {
            companyAdresse = [ticket.company_adresse || '', [ticket.company_plz || '', ticket.company_ort || ''].filter(Boolean).join(' ')].filter(Boolean).join(', ');
        }
        let customerAdresse = '';
        if (ticket.customer_adresse || ticket.customer_plz || ticket.customer_ort) {
            customerAdresse = [ticket.customer_adresse || '', [ticket.customer_plz || '', ticket.customer_ort || ''].filter(Boolean).join(' ')].filter(Boolean).join(', ');
        }
        const adresseAnzeige = customerAdresse || companyAdresse || '-';
        const beschreibungKurz = (ticket.beschreibung || '').trim();

        let companyCustomerText = '';
        if (!showCompany) {
            companyCustomerText = customerName || '-';
        } else if (customerName && companyName) {
            companyCustomerText = `${customerName} · ${companyName}`;
        } else {
            companyCustomerText = customerName || companyName || '-';
        }

        let projektNummer = '';
        if (ticket.projects && Array.isArray(ticket.projects) && ticket.projects.length > 0) {
            projektNummer = ticket.projects[0].project_nummer || ticket.projects[0].nummer || '';
        } else if (ticket.projects && typeof ticket.projects === 'object') {
            projektNummer = ticket.projects.project_nummer || ticket.projects.nummer || '';
        }
        const ticketNummerAnzeige = projektNummer ? `${projektNummer} · ${ticket.ticket_nummer || ''}` : (ticket.ticket_nummer || '');

        const createdDate = new Date(ticket.erstellt_datum || Date.now());
        const formattedCreatedDate = createdDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        let formattedGeaendertDate = '-';
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
        const isTicketClosed = ticket.status === 'Geschlossen' || ticket.status === 'Archiv';
        let bearbeitungsdauerText = '-';
        if (isTicketClosed && ticket.erstellt_datum && ticket.geaendert_datum) {
            const start = new Date(ticket.erstellt_datum);
            const ende = new Date(ticket.geaendert_datum);
            const diffMs = Math.max(0, ende - start);
            const totalMin = Math.floor(diffMs / 60000);
            const tage = Math.floor(totalMin / (60 * 24));
            const stunden = Math.floor((totalMin % (60 * 24)) / 60);
            const minuten = totalMin % 60;
            if (tage > 0) {
                bearbeitungsdauerText = `${tage}T ${stunden}h`;
            } else if (stunden > 0) {
                bearbeitungsdauerText = `${stunden}h ${minuten}m`;
            } else {
                bearbeitungsdauerText = `${minuten}m`;
            }
        }
        
        let naechsterTerminText = '-';
        let terminTitel = '';
        let terminFolgenText = '';
        let isTerminOverdue = false;
        let terminGerade = false;
        if (ticket.naechster_termin && ticket.naechster_termin.start_datum) {
            naechsterTerminText = formatDateTimeRange(ticket.naechster_termin.start_datum, ticket.naechster_termin.ende_datum);
            terminTitel = ticket.naechster_termin.titel || '';
            const anzahlZukuenftige = ticket.anzahl_zukuenftige_termine || 0;
            const nochFolgen = Math.max(0, anzahlZukuenftige - 1);
            terminFolgenText = nochFolgen > 0 ? ` (+${nochFolgen})` : '';
            const startDt = new Date(ticket.naechster_termin.start_datum);
            const endDt = new Date(ticket.naechster_termin.ende_datum || ticket.naechster_termin.start_datum);
            const now = new Date();
            terminGerade = (now >= startDt && now <= endDt);
            isTerminOverdue = (anzahlZukuenftige === 0) && ticket.status !== 'Geschlossen' && ticket.status !== 'Archiv';
        }
        const terminTextClass = isTerminOverdue ? 'text-red-600 dark:text-red-400 font-medium' : (terminGerade ? 'text-primary-250 dark:text-primary-280 font-medium' : 'text-gray-900 dark:text-primary-200');

        let logoUrl = null;
        if (ticket.company_logo) {
            logoUrl = (ticket.company_logo.startsWith('http://') || ticket.company_logo.startsWith('https://'))
                ? ticket.company_logo
                : '<?php echo BASE_URL; ?>' + ticket.company_logo.replace(/^\//, '');
        }
        const fallbackInitials = (companyName || erstellerName || '?').substring(0, 2).toUpperCase();

        const viewUrl = '<?php echo addslashes(BASE_URL); ?>tickets/view.php?id=' + ticket.id;
        const cardClass = 'w-full bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 hover:shadow-xs hover:bg-gray-50 dark:hover:bg-primary-140 dark:hover:border-primary-140 transition-all duration-200 cursor-pointer relative overflow-hidden';

        return `
            <div class="${cardClass}" data-ticket-id="${ticket.id}" data-ticket-view-url="${escapeHtml(viewUrl)}" onclick="navigateToTicketDetail(this.getAttribute('data-ticket-view-url'))">
                <div class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            ${logoUrl
                                ? `<img class="h-9 w-9 rounded-base object-cover flex-shrink-0 border border-gray-200 dark:border-primary-120" src="${escapeHtml(logoUrl)}" alt="${escapeHtml(companyName || 'Firma')}">`
                                : `<div class="h-9 w-9 rounded-base flex items-center justify-center text-white text-xs font-bold bg-primary-250 dark:bg-primary-420 flex-shrink-0">${escapeHtml(fallbackInitials)}</div>`
                            }
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-900 dark:text-primary-200 truncate">${escapeHtml(erstellerName)}</div>
                                <div class="text-xs text-gray-500 dark:text-primary-210 truncate" title="${escapeHtml(companyCustomerText)}">${escapeHtml(companyCustomerText)}</div>
                        </div>
                    </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            ${statusBadge}
                            ${unreadCount > 0 ? `<span class="inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 text-xs font-bold text-white bg-primary-1080 rounded-full" title="${unreadCount} ungelesen">${unreadCount > 99 ? '99+' : unreadCount}</span>` : (unreadRem ? `<span class="inline-flex w-2.5 h-2.5 rounded-full bg-red-600" title="Hervorgehoben" aria-hidden="true"></span>` : '')}
                        </div>
                    </div>

                    <div class="mb-3">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200 truncate inline-flex items-center gap-1.5 max-w-full">
                            ${isPinned ? `<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 flex-shrink-0" title="Angeheftet"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M6 3a1 1 0 00-.894 1.447L6.382 7H5a1 1 0 000 2h2.028l1.69 4.472a1 1 0 001.864 0L12.272 9H15a1 1 0 100-2h-1.382l1.276-2.553A1 1 0 0013 3H6z"/></svg></span>` : ''}
                            <span class="truncate">${escapeHtml(ticket.titel || '-')}</span>
                        </h3>
                        <div class="mt-1 text-xs text-gray-500 dark:text-primary-210"></div>
                        </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3 text-xs">
                        <div class="rounded-md bg-gray-100 dark:bg-primary-760 px-2.5 py-2 truncate" title="${escapeHtml(ticketNummerAnzeige)}">
                            <span class="ticket-nummer-meta">${escapeHtml(ticketNummerAnzeige)}</span>
                    </div>
                        <div class="rounded-md bg-gray-100 dark:bg-primary-760 px-2.5 py-2 text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(formattedCreatedDate)}">
                            <span class="font-medium">Erstellt:</span> ${escapeHtml(formattedCreatedDate)}
                        </div>
                        <div class="rounded-md bg-gray-100 dark:bg-primary-760 px-2.5 py-2 text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(isTicketClosed ? bearbeitungsdauerText : formattedGeaendertDate)}">
                            <span class="font-medium">${isTicketClosed ? 'Bearbeitungsdauer:' : 'Zuletzt geändert:'}</span> ${escapeHtml(isTicketClosed ? bearbeitungsdauerText : formattedGeaendertDate)}
                    </div>
                </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="rounded-lg border border-gray-200/80 dark:border-primary-230/80 bg-gray-50/60 dark:bg-primary-760/30 p-3 space-y-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-210">Objekt</div>
                            <div class="flex items-start gap-2 text-sm">
                                <svg class="w-4 h-4 mt-0.5 text-primary-250 dark:text-primary-280 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1-1-1 1 .75-3M4 4h16v11H4z"/></svg>
                                <div class="min-w-0">
                                    <div class="text-gray-900 dark:text-primary-200 truncate">${escapeHtml(deviceName)}</div>
                                    ${deviceType || manufacturerModel ? `<div class="text-xs text-gray-500 dark:text-primary-210 truncate">${escapeHtml([deviceType, manufacturerModel].filter(Boolean).join(' · '))}</div>` : ''}
                                </div>
                                </div>
                            <div class="flex items-start gap-2 text-sm">
                                <svg class="w-4 h-4 mt-0.5 text-primary-250 dark:text-primary-280 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M15 9h.01"/></svg>
                                <div class="min-w-0">
                                    <div class="text-gray-900 dark:text-primary-200 truncate">${escapeHtml(companyCustomerText)}</div>
                                    <div class="text-xs text-gray-500 dark:text-primary-210 truncate">SN: ${escapeHtml(serial)}</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200/80 dark:border-primary-230/80 bg-gray-50/60 dark:bg-primary-760/30 p-3 space-y-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-210">Planung</div>
                            <div class="flex items-start gap-2 text-sm">
                                <svg class="w-4 h-4 mt-0.5 ${isTerminOverdue ? 'text-red-500 dark:text-red-400' : 'text-primary-250 dark:text-primary-280'} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <div class="min-w-0">
                                    <div class="${terminTextClass}">${escapeHtml(naechsterTerminText)}</div>
                                    ${terminTitel || terminFolgenText ? `<div class="text-xs text-gray-500 dark:text-primary-210 truncate">${escapeHtml(terminTitel)}${terminFolgenText}</div>` : ''}
                            </div>
                        </div>
                            <div class="flex items-start gap-2 text-sm">
                                <svg class="w-4 h-4 mt-0.5 text-primary-250 dark:text-primary-280 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <div class="min-w-0">
                                    <div class="text-gray-900 dark:text-primary-200 truncate">Geändert: ${escapeHtml(formattedGeaendertDate)}</div>
                    </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 rounded-lg border border-gray-200/80 dark:border-primary-230/80 bg-white/70 dark:bg-primary-800/20 p-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-210 mb-2">Details</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                            <div class="text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(adresseAnzeige)}"><span class="font-medium">Adresse:</span> ${escapeHtml(adresseAnzeige)}</div>
                            <div class="text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(companyEmail || customerEmail || '-')}"><span class="font-medium">E-Mail:</span> ${escapeHtml(companyEmail || customerEmail || '-')}</div>
                            <div class="text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(companyTelefon || customerTelefon || '-')}"><span class="font-medium">Telefon:</span> ${escapeHtml(companyTelefon || customerTelefon || '-')}</div>
                            <div class="text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(customerAnsprechpartner || '-')}"><span class="font-medium">Ansprechpartner:</span> ${escapeHtml(customerAnsprechpartner || '-')}</div>
                            <div class="text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(prioritaet)}"><span class="font-medium">Priorität:</span> ${escapeHtml(prioritaet)}</div>
                            <div class="text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(serial)}"><span class="font-medium">Seriennummer:</span> ${escapeHtml(serial)}</div>
                            <div class="md:col-span-2 text-gray-700 dark:text-primary-220 truncate" title="${escapeHtml(observerText)}"><span class="font-medium">Beobachter:</span> ${escapeHtml(observerText)}</div>
                            <div class="md:col-span-2 text-gray-700 dark:text-primary-220 ${deviceBeschreibung ? '' : 'truncate'}" title="${escapeHtml(deviceBeschreibung || '-')}"><span class="font-medium">Geräte-Notiz:</span> ${escapeHtml(deviceBeschreibung || '-')}</div>
                            ${beschreibungKurz ? `<div class="md:col-span-2 text-gray-700 dark:text-primary-220 line-clamp-2" title="${escapeHtml(beschreibungKurz)}"><span class="font-medium">Beschreibung:</span> ${escapeHtml(beschreibungKurz)}</div>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function getStatusBadge(status) {
    const badges = {
        'Neu': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Neu</span>',
        'In Bearbeitung': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">In Bearbeitung</span>',
        'Warteschlange': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">Warteschlange</span>',
        'Geplant': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Geplant</span>',
        'Bestellung offen': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Bestellung offen</span>',
        'Geschlossen': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Geschlossen</span>',
        'Archiv': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">Archiv</span>'
    };
    return badges[status] || badges['Neu'] || '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">' + (status || '') + '</span>';
}

// Funktion für Bestellungs-Status-Badge
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

function getPrioritaetBadge(prioritaet) {
    const badges = {
        'niedrig': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Niedrig</span>',
        'normal': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Normal</span>',
        'hoch': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">Hoch</span>',
        'kritisch': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Kritisch</span>'
    };
    return badges[prioritaet] || badges['normal'];
}

function displayChatView(tickets) {
    const chatTicketList = document.getElementById('chatTicketList');
    if (!chatTicketList) return;
    if (!ticketsLoadedOnce && (!Array.isArray(tickets) || tickets.length === 0)) {
        return;
    }
    chatTicketList.removeAttribute('aria-busy');
    chatTicketList.classList.remove('ticket-skeleton-chat-list');
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
    const useProfileAvatar = (userRole === 'Kunde' || userRole === 'Firmen-User' || userRole === 'Firmen-Admin');
    
    if (tickets.length === 0) {
        chatTicketList.innerHTML = '<li class="px-2 py-2">' + renderTicketsEmptyState({ variant: 'compact' }) + '</li>';
        return;
    }
    
    // Ticket-Liste in Sidebar rendern - im Chat-Stil
    chatTicketList.innerHTML = tickets.map(ticket => {
        const createdDate = new Date(ticket.erstellt_datum);
        const now = new Date();
        const diffTime = Math.abs(now - createdDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        let timeDisplay = '';
        if (diffDays === 1) {
            timeDisplay = createdDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        } else if (diffDays === 2) {
            timeDisplay = 'gestern';
        } else if (diffDays <= 7) {
            timeDisplay = `${diffDays - 1}d`;
        } else if (diffDays <= 30) {
            timeDisplay = `${Math.floor((diffDays - 1) / 7)}w`;
        } else {
            timeDisplay = createdDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }
        
        const isSelected = selectedChatTicket && selectedChatTicket.id === ticket.id;
        const statusColor = getStatusColor(ticket.status);
        const prioritaetColor = getPrioritaetColor(ticket.prioritaet);
        
        // Beschreibung für Vorschau
        let previewText = ticket.beschreibung || 'Keine Beschreibung';
        if (previewText.length > 50) {
            previewText = previewText.substring(0, 50) + '...';
        }
        
        // Status-Text für Vorschau
        const statusText = getStatusText(ticket.status);
        
        // Name des Anforderers (Ersteller des Tickets)
        let anfordererName = 'Unbekannt';
        if (ticket.ersteller_vorname || ticket.ersteller_nachname) {
            anfordererName = [ticket.ersteller_vorname, ticket.ersteller_nachname].filter(Boolean).join(' ').trim();
        }
        
        // Angezeigter Name gemäß user_setting chat_display_name
        let displayName = anfordererName;
        if (chatDisplayName === 'firma' && ticket.company_name) {
            displayName = ticket.company_name;
        } else if (chatDisplayName === 'kunde' && ticket.customer_name) {
            displayName = ticket.customer_name;
        } else if (chatDisplayName === 'kunde' && !ticket.customer_name) {
            displayName = ticket.company_name || anfordererName;
        } else if (chatDisplayName === 'firma' && !ticket.company_name) {
            displayName = anfordererName;
        }

        // Logo/Avatar in Chat-Liste
        let avatarHtml = '';
        if (useProfileAvatar) {
            avatarHtml = renderUserAvatarHtml(ticket.ersteller_logopfad || null, anfordererName, 'h-8 w-8 rounded-full');
        } else {
            let logoUrl = null;
            if (ticket.company_logo) {
                if (ticket.company_logo.startsWith('http://') || ticket.company_logo.startsWith('https://')) {
                    logoUrl = ticket.company_logo;
                } else {
                    logoUrl = '<?php echo BASE_URL; ?>' + ticket.company_logo.replace(/^\//, '');
                }
            }
            const fallbackInitials = (ticket.company_name || anfordererName).substring(0, 2).toUpperCase();
            avatarHtml = logoUrl
                ? `<img class="h-8 w-8 rounded-full object-cover cursor-pointer" src="${escapeHtml(logoUrl)}" alt="${escapeHtml(anfordererName)}">`
                : `<div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold cursor-pointer" style="background: linear-gradient(135deg, ${prioritaetColor}, ${statusColor});">${escapeHtml(fallbackInitials)}</div>`;
        }
        
        // Tooltip-Text für Logo (Firma und Kunde)
        let tooltipText = '';
        if (ticket.company_name && ticket.customer_name) {
            tooltipText = `${escapeHtml(ticket.company_name)} - ${escapeHtml(ticket.customer_name)}`;
        } else if (ticket.company_name) {
            tooltipText = escapeHtml(ticket.company_name);
        } else if (ticket.customer_name) {
            tooltipText = escapeHtml(ticket.customer_name);
        }
        if (useProfileAvatar) {
            tooltipText = '';
        }
        
        const viewUrl = '<?php echo addslashes(BASE_URL); ?>tickets/view.php?id=' + ticket.id;
        return `
            <li class="flex items-start justify-between px-4 py-2 hover:cursor-pointer hover:bg-gray-50 dark:hover:bg-primary-140 rounded-base transition-colors ${isSelected ? 'bg-gray-50 dark:bg-primary-140 border-l-2 border-primary-250 dark:border-primary-280' : ''} md:border-l-0" data-ticket-id="${ticket.id}" data-view-url="${escapeHtml(viewUrl)}" onclick="if(window.innerWidth<=768){navigateToTicketDetail(this.getAttribute('data-view-url'));}else{selectChatTicket(${ticket.id});}">
              <div class="flex items-center gap-3">
                <div class="relative shrink-0 group">
                  ${avatarHtml}
                  ${tooltipText ? `
                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 pointer-events-none whitespace-nowrap z-50 dark:bg-gray-700">
                      ${tooltipText}
                      <div class="absolute top-full left-1/2 transform -translate-x-1/2 -mt-1">
                        <div class="border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></div>
                      </div>
                    </div>
                  ` : ''}
                  <span class="absolute start-6 top-0 h-3.5 w-3.5 rounded-full border-2 border-white dark:border-gray-800 ${statusColor === '#10b981' ? 'bg-green-400' : statusColor === '#ef4444' ? 'bg-red-500' : statusColor === '#f59e0b' ? 'bg-yellow-400' : statusColor === '#3b82f6' ? 'bg-blue-400' : statusColor === '#f97316' ? 'bg-orange-400' : statusColor === '#a855f7' ? 'bg-purple-400' : 'bg-gray-400'}" title="${getStatusText(ticket.status)}"></span>
                </div>
                <div class="leading-1.5 flex w-full flex-col">
                  <div class="flex items-center gap-2">
                    <span class="text-base font-medium text-gray-900 dark:text-primary-200">${escapeHtml(displayName)}</span>
                    ${(ticket.unread_comments_count || 0) > 0 ? `
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full" title="${ticket.unread_comments_count} ungelesene Nachricht${ticket.unread_comments_count > 1 ? 'en' : ''}">
                      ${ticket.unread_comments_count > 99 ? '99+' : ticket.unread_comments_count}
                    </span>
                    ` : ((ticket.unread_reminder === 1 || ticket.unread_reminder === '1') ? `
                    <span class="inline-flex w-2 h-2 shrink-0 rounded-full bg-red-600" title="Hervorgehoben" aria-hidden="true"></span>
                    ` : '')}
                  </div>
                  <p class="max-w-52 truncate text-sm font-normal ${ticket.status === 'Neu' ? 'text-primary-250 dark:text-primary-280' : 'text-gray-500 dark:text-primary-210'}">
                    ${escapeHtml(ticket.titel)}
                  </p>
                </div>
              </div>
              <div class="shrink-0 flex flex-col items-end gap-1">
                <span class="text-xs text-gray-500 dark:text-primary-210">${timeDisplay}</span>
              </div>
            </li>
        `;
    }).join('');
    
    // Wenn ein Ticket ausgewählt ist, Details anzeigen (NUR wenn nicht bereits geladen wird)
    if (selectedChatTicket && !isLoadingComments) {
        const ticket = tickets.find(t => t.id === selectedChatTicket.id);
        if (ticket) {
            // Ticket-Daten aktualisieren (inkl. unread_comments_count)
            selectedChatTicket = { ...selectedChatTicket, ...ticket };
            // Nur Header aktualisieren, nicht die Kommentare neu laden
            updateChatTicketHeader(selectedChatTicket);
        } else {
            // Ausgewähltes Ticket ist nicht mehr in der gefilterten Liste
            selectedChatTicket = null;
            displayChatTicketDetails(null);
        }
    }
}

function getStatusColor(status) {
    const colors = {
        'Neu': '#f59e0b',
        'In Bearbeitung': '#3b82f6',
        'Warteschlange': '#f97316',
        'Geplant': '#10b981',
        'Bestellung offen': '#a855f7',
        'Geschlossen': '#6b7280',
        'Archiv': '#475569'
    };
    return colors[status] || '#6b7280';
}

function getPrioritaetColor(prioritaet) {
    const colors = {
        'niedrig': '#6b7280',
        'normal': '#3b82f6',
        'hoch': '#f97316',
        'kritisch': '#ef4444'
    };
    return colors[prioritaet] || '#3b82f6';
}

function getStatusText(status) {
    const texts = {
        'Neu': 'Neu',
        'In Bearbeitung': 'In Bearbeitung',
        'Warteschlange': 'Warteschlange',
        'Geplant': 'Geplant',
        'Bestellung offen': 'Bestellung offen',
        'Geschlossen': 'Geschlossen',
        'Archiv': 'Archiv'
    };
    return texts[status] || status || 'Unbekannt';
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

function selectChatTicket(ticketId) {
    const ticket = filteredTickets.find(t => t.id === ticketId);
    if (ticket) {
        selectedChatTicket = ticket;
        displayChatTicketDetails(ticket);
        
        // Visuelle Hervorhebung aktualisieren
        document.querySelectorAll('[data-ticket-id]').forEach(el => {
            el.classList.remove('bg-gray-50', 'dark:bg-primary-140', 'border-l-2', 'border-primary-250', 'dark:border-primary-280');
        });
        const selectedEl = document.querySelector(`[data-ticket-id="${ticketId}"]`);
        if (selectedEl) {
            selectedEl.classList.add('bg-gray-50', 'dark:bg-primary-140', 'border-l-2', 'border-primary-250', 'dark:border-primary-280');
        }
    }
}

// Funktion zum Aktualisieren nur des Headers (ohne Kommentare neu zu laden)
function updateChatTicketHeader(ticket) {
    const chatTicketHeader = document.getElementById('chatTicketHeader');
    if (!chatTicketHeader || !ticket) return;
    
    const useProfileAvatar = (userRole === 'Kunde' || userRole === 'Firmen-User' || userRole === 'Firmen-Admin');
    const logoPath = (!useProfileAvatar && ticket.company_logo) ? (ticket.company_logo.startsWith('http') ? ticket.company_logo : '<?php echo BASE_URL; ?>' + ticket.company_logo) : null;
    const companyName = ticket.company_name || '';
    const customerName = ticket.customer_name || '';
    const statusBadge = getStatusBadge(ticket.status);
    const hideCompanyCustomerLine = (userRole === 'Kunde' || userRole === 'Firmen-User');
    const ticketNumberDisplay = ticket.ticket_nummer ? `Ticket #${ticket.ticket_nummer}` : `Ticket #${ticket.id}`;
    
    chatTicketHeader.innerHTML = `
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="flex-shrink-0">
                    ${useProfileAvatar
                        ? renderUserAvatarHtml(ticket.ersteller_logopfad || null, [ticket.ersteller_vorname, ticket.ersteller_nachname].filter(Boolean).join(' ').trim(), 'w-12 h-12 rounded-lg')
                        : (logoPath ? `<img src="${escapeHtml(logoPath)}" alt="${escapeHtml(ticket.company_name || '')}" class="w-12 h-12 object-contain rounded-lg border border-gray-200 dark:border-gray-700">` : '')
                    }
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 truncate">${escapeHtml(ticket.titel)}</h3>
                    ${hideCompanyCustomerLine ? `
                    <p class="ticket-nummer-meta mt-0.5">
                        ${escapeHtml(ticketNumberDisplay)}
                    </p>` : `
                    <p class="text-sm text-gray-500 dark:text-primary-210">
                        ${escapeHtml(companyName)} | ${escapeHtml(customerName)}
                    </p>`}
                </div>
            </div>
            <div class="flex-shrink-0 flex flex-col items-end gap-1">
                <div class="flex items-center gap-2">
                    ${statusBadge}
                    <a href="<?php echo BASE_URL; ?>tickets/view.php?id=${ticket.id}" 
                       class="inline-flex cursor-pointer justify-center rounded-lg p-1.5 text-primary-700 hover:bg-primary-50 dark:text-white dark:hover:bg-primary-900 dark:hover:text-primary-300" title="Details öffnen">
                        <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm9.408-5.5a1 1 0 1 0 0 2h.01a1 1 0 1 0 0-2h-.01ZM10 10a1 1 0 1 0 0 2h1v3h-1a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2h-1v-4a1 1 0 0 0-1-1h-2Z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Details anzeigen</span>
                    </a>
                </div>
                ${hideCompanyCustomerLine ? '' : `<p class="ticket-nummer-meta text-right">${escapeHtml(ticket.ticket_nummer || '')}</p>`}
            </div>
        </div>
    `;
}

function displayChatTicketDetails(ticket) {
    const chatTicketHeader = document.getElementById('chatTicketHeader');
    const chatTicketContent = document.getElementById('chatTicketContent');
    const chatInputArea = document.getElementById('chatInputArea');
    
    if (!chatTicketHeader || !chatTicketContent || !chatInputArea) {
        console.error('Chat-Elemente nicht gefunden');
        return;
    }
    
    if (!ticket) {
        chatTicketHeader.innerHTML = `
            <div class="flex items-center justify-center h-full w-full">
                <p class="text-gray-500 dark:text-primary-210 text-sm">Wählen Sie einen Ticket aus</p>
            </div>
        `;
        chatTicketContent.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full min-h-[280px] text-center px-4">
              <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-primary-200/40 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-gray-400 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Kein Ticket ausgewählt</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-primary-240">Wählen Sie einen Ticket aus der Liste aus, um Nachrichten anzuzeigen.</p>
            </div>
        `;
        chatInputArea.style.display = 'none';
        return;
    }
    
    // Firmenlogo-URL / Profilbild
    const useProfileAvatar = (userRole === 'Kunde' || userRole === 'Firmen-User' || userRole === 'Firmen-Admin');
    const logoPath = (!useProfileAvatar && ticket.company_logo) ? (ticket.company_logo.startsWith('http') ? ticket.company_logo : '<?php echo BASE_URL; ?>' + ticket.company_logo) : null;
    const companyName = ticket.company_name || '';
    const customerName = ticket.customer_name || '';
    const statusBadge = getStatusBadge(ticket.status);
    
    const hideCompanyCustomerLine = (userRole === 'Kunde' || userRole === 'Firmen-User');
    const ticketNumberDisplay = ticket.ticket_nummer ? `Ticket #${ticket.ticket_nummer}` : `Ticket #${ticket.id}`;
    
    // Header mit Firmenlogo, Titel, Firma|Kunde (oder Ticketnummer), Status und Ticketnummer
    chatTicketHeader.innerHTML = `
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="flex-shrink-0">
                    ${useProfileAvatar
                        ? renderUserAvatarHtml(ticket.ersteller_logopfad || null, [ticket.ersteller_vorname, ticket.ersteller_nachname].filter(Boolean).join(' ').trim(), 'w-12 h-12 rounded-lg')
                        : (logoPath ? `<img src="${escapeHtml(logoPath)}" alt="${escapeHtml(ticket.company_name || '')}" class="w-12 h-12 object-contain rounded-lg border border-gray-200 dark:border-gray-700">` : '')
                    }
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 truncate">${escapeHtml(ticket.titel)}</h3>
                    ${hideCompanyCustomerLine ? `
                    <p class="ticket-nummer-meta mt-0.5">
                        ${escapeHtml(ticketNumberDisplay)}
                    </p>` : `
                    <p class="text-sm text-gray-500 dark:text-primary-210">
                        ${escapeHtml(companyName)} | ${escapeHtml(customerName)}
                    </p>`}
                </div>
            </div>
            <div class="flex-shrink-0 flex flex-col items-end gap-1">
                <div class="flex items-center gap-2">
                    ${statusBadge}
                    <a href="<?php echo BASE_URL; ?>tickets/view.php?id=${ticket.id}" 
                       class="inline-flex cursor-pointer justify-center rounded-lg p-1.5 text-primary-700 hover:bg-primary-50 dark:text-white dark:hover:bg-primary-900 dark:hover:text-primary-300" title="Details öffnen">
                        <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm9.408-5.5a1 1 0 1 0 0 2h.01a1 1 0 1 0 0-2h-.01ZM10 10a1 1 0 1 0 0 2h1v3h-1a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2h-1v-4a1 1 0 0 0-1-1h-2Z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Details anzeigen</span>
                    </a>
                </div>
                ${hideCompanyCustomerLine ? '' : `<p class="ticket-nummer-meta text-right">${escapeHtml(ticket.ticket_nummer || '')}</p>`}
            </div>
        </div>
    `;
    
    // Eingabefeld anzeigen
    chatInputArea.style.display = 'block';
    
    // Prüfen ob Ticket abgerechnet ist - dann Input deaktivieren
    const isAbgerechnet = ticket.abgerechnet === 1 || ticket.abgerechnet === '1';
    const inputEl = document.getElementById('chat-message-input');
    const sendBtn = document.getElementById('send-message-btn');
    const attachBtn = document.getElementById('attach-file-btn');
    const orderBtn = document.getElementById('open-order-modal-btn');
    const messageTypeBtns = document.querySelectorAll('.message-type-btn');
    
    if (isAbgerechnet) {
        if (inputEl) {
            inputEl.disabled = true;
            inputEl.placeholder = 'Zu abgerechneten Tickets können keine Kommentare mehr hinzugefügt werden';
        }
        if (sendBtn) sendBtn.disabled = true;
        if (attachBtn) attachBtn.disabled = true;
        if (orderBtn) orderBtn.disabled = true;
        messageTypeBtns.forEach(btn => btn.disabled = true);
    } else {
        if (inputEl) {
            inputEl.disabled = false;
            inputEl.placeholder = 'Nachricht schreiben…';
        }
        if (sendBtn) sendBtn.disabled = false;
        if (attachBtn) attachBtn.disabled = false;
        if (orderBtn) orderBtn.disabled = false;
        messageTypeBtns.forEach(btn => btn.disabled = false);
    }
    
    // Kommentare laden
    loadTicketComments(ticket.id);
}

function loadTicketComments(ticketId) {
    // Verhindere mehrfache gleichzeitige Aufrufe
    if (isLoadingComments) {
        return;
    }
    
    const chatTicketContent = document.getElementById('chatTicketContent');
    
    if (!chatTicketContent) {
        console.error('chatTicketContent Element nicht gefunden');
        return;
    }
    
    // Prüfen ob commentsApiUrl definiert ist
    if (typeof commentsApiUrl === 'undefined') {
        console.error('commentsApiUrl ist nicht definiert');
        chatTicketContent.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-sm text-red-500">Fehler: API-URL nicht definiert</p></div>';
        return;
    }
    
    isLoadingComments = true;
    chatTicketContent.innerHTML = '<div class="flex flex-col items-center justify-center h-full min-h-[280px] text-center px-4"><div class="w-12 h-12 rounded-xl bg-gray-100 dark:bg-primary-200/40 flex items-center justify-center mb-3"><svg class="animate-spin w-6 h-6 text-primary-500 dark:text-primary-250" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></div><p class="text-sm font-medium text-gray-900 dark:text-primary-200">Lade Nachrichten</p><p class="mt-1 text-sm text-gray-500 dark:text-primary-240">Bitte warten…</p></div>';
    
    const url = commentsApiUrl + '?ticket_id=' + ticketId;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('API Fehler Response:', text);
                    throw new Error(`HTTP ${response.status}: ${text}`);
                });
            }
            // Prüfen ob Content-Type JSON ist
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('API Antwort ist nicht JSON:', text);
                    throw new Error('API Antwort ist nicht im JSON-Format');
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data) {
                console.error('API Antwort ist leer');
                chatTicketContent.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-sm text-red-500">Fehler: Leere API-Antwort</p></div>';
                return;
            }
            if (data.success) {
                // Parallel Ticket-Anhänge und Kommentare laden
                Promise.all([
                    fetch(ticketAttachmentsApiUrl + '?ticket_id=' + ticketId)
                        .then(response => response.json())
                        .then(data => data.success && data.attachments ? data.attachments : [])
                        .catch(error => {
                            console.error('Fehler beim Laden der Ticket-Anhänge:', error);
                            return [];
                        }),
                    Promise.resolve(data.comments || [])
                ])
                .then(([ticketAttachments, comments]) => {
                    // Ticket-Anhänge als Kommentar-ähnliche Einträge hinzufügen
                    if (ticketAttachments && ticketAttachments.length > 0) {
                        ticketAttachments.forEach(attachment => {
                            const attachmentComment = {
                                id: 'ticket_attachment_' + attachment.id,
                                ticket_id: ticketId,
                                user_id: attachment.erstellt_von || (selectedChatTicket && selectedChatTicket.erstellt_von) || null,
                                kommentar: '[Dateianhang]',
                                nachrichtentyp: 'nachricht',
                                ist_intern: 0,
                                erstellt_datum: attachment.erstellt_datum || new Date().toISOString(),
                                vorname: (selectedChatTicket && selectedChatTicket.ersteller_vorname) || '',
                                nachname: (selectedChatTicket && selectedChatTicket.ersteller_nachname) || '',
                                email: (selectedChatTicket && selectedChatTicket.ersteller_email) || '',
                                logopfad: (selectedChatTicket && selectedChatTicket.ersteller_logopfad) || '',
                                attachments: [attachment],
                                is_ticket_attachment: true
                            };
                            comments.unshift(attachmentComment);
                        });
                    }
                    
                    // Beschreibung als erste Nachricht hinzufügen, falls vorhanden
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
                    
                    try {
                        displayChatMessages(comments);
                        
                        // Ungelesene Nachrichten im ausgewählten Ticket auf 0 setzen und Chat-Liste aktualisieren
                        if (currentView === 'chat' && selectedChatTicket) {
                            // Ungelesene Nachrichten im Ticket-Objekt auf 0 setzen
                            selectedChatTicket.unread_comments_count = 0;
                            selectedChatTicket.unread_reminder = 0;
                            
                            // Ticket in allTickets und filteredTickets aktualisieren
                            const ticketIndex = allTickets.findIndex(t => t.id === selectedChatTicket.id);
                            if (ticketIndex !== -1) {
                                allTickets[ticketIndex].unread_comments_count = 0;
                                allTickets[ticketIndex].unread_reminder = 0;
                            }
                            const filteredIndex = filteredTickets.findIndex(t => t.id === selectedChatTicket.id);
                            if (filteredIndex !== -1) {
                                filteredTickets[filteredIndex].unread_comments_count = 0;
                                filteredTickets[filteredIndex].unread_reminder = 0;
                            }
                        }
                        
                        isLoadingComments = false; // Flag zurücksetzen nach erfolgreicher Anzeige
                    } catch (error) {
                        console.error('Fehler beim Anzeigen der Kommentare:', error);
                        chatTicketContent.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-sm text-red-500">Fehler beim Anzeigen der Nachrichten</p></div>';
                        isLoadingComments = false;
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Laden der Anhänge:', error);
                    isLoadingComments = false;
                });
            } else {
                const errorMessage = data.error || 'Unbekannter Fehler';
                console.error('API Fehler:', errorMessage);
                chatTicketContent.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-sm text-red-500">Fehler beim Laden der Nachrichten: ' + errorMessage + '</p></div>';
                isLoadingComments = false;
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kommentare:', error);
            const errorMessage = error.message || 'Unbekannter Fehler';
            chatTicketContent.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-sm text-red-500">Fehler beim Laden der Nachrichten: ' + errorMessage + '</p></div>';
            isLoadingComments = false;
        });
    
    // Chat-Liste und andere Ansichten aktualisieren (nachdem Flag zurückgesetzt wurde)
    if (currentView === 'chat' && selectedChatTicket) {
        // Kurze Verzögerung, um sicherzustellen, dass alles aktualisiert ist
        setTimeout(() => {
            if (currentView === 'table') {
                displayTableView(filteredTickets);
            } else if (currentView === 'cards') {
                displayCardsView(filteredTickets);
            } else if (currentView === 'chat') {
                displayChatView(filteredTickets);
            }
            
            // Header aktualisieren, falls vorhanden
            if (selectedChatTicket) {
                updateChatTicketHeader(selectedChatTicket);
            }
        }, 100);
    }
}

// Funktion zum Erkennen und Formatieren von Links in Nachrichten
function formatMessageWithLinks(text) {
    if (!text) return '';
    
    // URL-Regex: Erkennt https:// und http:// URLs
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    
    // Text in Teile aufteilen (URLs und Nicht-URLs)
    const parts = text.split(urlRegex);
    
    // Jeden Teil verarbeiten
    let result = parts.map(part => {
        // Prüfen ob es eine URL ist (mit neuem Regex-Objekt, da test() den State ändert)
        const urlCheckRegex = /^https?:\/\/[^\s]+$/;
        if (urlCheckRegex.test(part)) {
            // URL als anklickbaren Link rendern (blau)
            return `<a href="${escapeHtml(part)}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:underline">${escapeHtml(part)}</a>`;
        } else {
            // Normaler Text escapen
            return escapeHtml(part);
        }
    }).join('');
    
    // Zeilenumbrüche (\n) in <br> Tags umwandeln
    result = result.replace(/\n/g, '<br>');

    // Bild-Marker farblich hervorheben
    result = result.replace(/\[(Bild\s+\d+(?::\s*[^\]]+)?)\]/g, '<span class="inline-flex items-center rounded-md bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200 px-2 py-0.5 font-medium">[Extrahiert: $1]</span>');
    
    return result;
}

/** Format: "19.01.2026, 09:20" oder "19.01.2026, 09:20 - 09:30" (ohne Ende: +1 Std. wie Kalender) */
function formatDateTimeRange(startString, endString) {
    if (!startString) return '-';
    const start = new Date(startString);
    const dateStr = start.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
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
    const endDateStr = end.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    return dateStr + ', ' + startTime + ' – ' + endDateStr + ', ' + endTime;
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
    
    if (!chatTicketContent) {
        return;
    }
    
    if (comments.length === 0) {
        chatTicketContent.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full min-h-[280px] text-center px-4">
              <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-primary-200/40 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-gray-400 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <p class="text-base font-semibold text-gray-900 dark:text-primary-200">Noch keine Nachrichten</p>
              <p class="mt-1 text-sm text-gray-500 dark:text-primary-240">Schreiben Sie die erste Nachricht …</p>
            </div>
        `;
        return;
    }
    
    try {
        const html = comments.map((comment, index) => {
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
        
        // Prüfen, ob vorherige Nachricht vom selben User ist (innerhalb von 5 Minuten)
        let showAvatarAndName = true;
        if (index > 0) {
            const prevComment = comments[index - 1];
            const prevUserId = prevComment.user_id !== null && prevComment.user_id !== undefined ? parseInt(prevComment.user_id) : null;
            const commentUserId = comment.user_id !== null && comment.user_id !== undefined ? parseInt(comment.user_id) : null;
            const currentUserIdInt = parseInt(currentUserId);
            
            const sameUser = prevUserId === commentUserId;
            const prevIsCurrentUser = prevUserId !== null && prevUserId === currentUserIdInt;
            const commentIsCurrentUser = commentUserId !== null && commentUserId === currentUserIdInt;
            
            // Nur wenn gleicher User und beide auf derselben Seite (currentUser oder nicht)
            if (sameUser && prevIsCurrentUser === commentIsCurrentUser) {
                const prevCommentDate = new Date(prevComment.erstellt_datum);
                const timeDiff = Math.abs(commentDate - prevCommentDate) / 1000 / 60; // Differenz in Minuten
                
                // Weniger als 5 Minuten Unterschied - unabhängig vom Nachrichtentyp
                if (timeDiff < 5) {
                    showAvatarAndName = false;
                }
            }
        }
        
        // Avatar-Bild bestimmen - aus DB oder Preset oder Initialen
        let avatarHtml = '';
        const logopfad = comment.logopfad || '';
        const userInitials = (comment.vorname ? comment.vorname.substring(0, 1) : '') + (comment.nachname ? comment.nachname.substring(0, 1) : '') || 'U';
        
        if (logopfad && logopfad.startsWith('preset:')) {
            // Preset-Avatar: Format preset:{color}:{initials}
            const presetParts = logopfad.split(':');
            let presetColor = presetParts[1] || '#6b7280';
            // Sicherstellen, dass die Farbe ein # hat
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
            // Profilbild aus DB
            const imageUrl = logopfad.startsWith('http://') || logopfad.startsWith('https://') 
                ? logopfad 
                : '<?php echo BASE_URL; ?>' + logopfad.replace(/^\//, '');
            avatarHtml = `
                <img src="${escapeHtml(imageUrl)}" class="h-8 w-8 rounded-full object-cover shrink-0" alt="${escapeHtml(userName)}" onerror="this.outerHTML='<div class=\\'h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold bg-gray-400 dark:bg-gray-500 shrink-0\\'>${escapeHtml(userInitials.toUpperCase())}</div>'">
            `;
        } else {
            // Fallback: Initialen
            avatarHtml = `
                <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold bg-gray-400 dark:bg-gray-500 shrink-0">
                    ${escapeHtml(userInitials.toUpperCase())}
                </div>
            `;
        }
        
        // Anhänge prüfen
        const hasAttachments = comment.attachments && comment.attachments.length > 0;
        const isDateianhangOnly = comment.kommentar === '[Dateianhang]' && hasAttachments;
        const isAttachmentMessage = (hasAttachments && nachrichtentyp === 'nachricht') || isDateianhangOnly;
        
        // Styling basierend auf Nachrichtentyp (wie view.php)
        let messageBgClass = '';
        let messageBorderClass = '';
        const messageRoundedClass = 'rounded-2xl';
        let displayName = userName;
        let isTodoCompleted = false;
        let todoId = null;
        
        // Dropdown-Menü-ID eindeutig machen
        const dropdownId = `dropdown-${comment.id}`;
        const dropdownButtonId = `dropdown-btn-${comment.id}`;
        
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
            default: // nachricht
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
        
        // Anhänge für Nachricht vorbereiten
        let attachmentsHtml = '';
        if (hasAttachments) {
            attachmentsHtml = comment.attachments.map(attachment => {
                const fileUrl = '<?php echo BASE_URL; ?>' + (attachment.dateipfad || '').replace(/^\//, '');
                const fileName = attachment.dateiname || 'Unbekannte Datei';
                
                if (isAttachmentMessage || isDateianhangOnly) {
                    // Attachment-Format mit Vorschau für alle Dateitypen
                    const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(fileName);
                    const isPdf = /\.pdf$/i.test(fileName);
                    const isText = /\.(txt|md|log|json|xml|html|css|js|ts|php|py|java|cpp|c|h)$/i.test(fileName);
                    
                    if (isImage) {
                        // Bild-Vorschau mit Dateiname und Buttons, volle Breite
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
                        // PDF-Vorschau mit Dateiname und Buttons, volle Breite
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
                        // Text-Datei Vorschau mit Dateiname und Buttons
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
                        // Andere Dateien mit generischer Vorschau
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
                    // Normale Anzeige für andere Nachrichtentypen
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
        
        // Dropdown-Menü generieren (dynamisch basierend auf Kontext)
        const getDropdownMenuItems = () => {
            let items = [];
            if (nachrichtentyp === 'loesung' && !isCurrentUser) {
                items.push(`<li><a href="#" onclick="acceptSolution(${comment.id}); return false;" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"><svg class="me-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Lösung akzeptieren</a></li>`);
                items.push(`<li><a href="#" onclick="rejectSolution(${comment.id}); return false;" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"><svg class="me-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>Ablehnen</a></li>`);
            }
            if (nachrichtentyp === 'bestellung' && userRole !== 'Kunde') {
                items.push(`<li><a href="#" onclick="trackOrder(${comment.id}); return false;" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"><svg class="me-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>Bestellung verfolgen</a></li>`);
            }
            items.push(`<li><a href="#" onclick="openAttachmentModal(${comment.id}); return false;" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"><svg class="me-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M14.502 7.046h-2.5v-.928a2.122 2.122 0 0 0-1.199-1.954 1.827 1.827 0 0 0-1.984.311L3.71 8.965a2.2 2.2 0 0 0 0 3.24L8.82 16.7a1.829 1.829 0 0 0 1.985.31 2.121 2.121 0 0 0 1.199-1.959v-.928h1a2.025 2.025 0 0 1 1.999 2.047V19a1 1 0 0 0 1.275.961 6.59 6.59 0 0 0 4.662-7.22 6.593 6.593 0 0 0-6.437-5.695Z"/></svg>Antworten</a></li>`);
            items.push(`<li><a href="#" onclick="copyMessage('${escapeHtml(comment.kommentar || '').replace(/'/g, "\\'")}'); return false;" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"><svg class="me-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M18 3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1V9a4 4 0 0 0-4-4h-3a1.99 1.99 0 0 0-1 .267V5a2 2 0 0 1 2-2h7Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M8 7.054V11H4.2a2 2 0 0 1 .281-.432l2.46-2.87A2 2 0 0 1 8 7.054ZM10 7v4a2 2 0 0 1-2 2H4v6a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3Z" clip-rule="evenodd"/></svg>Kopieren</a></li>`);
            if (isCurrentUser) {
                items.push(`<li><a href="#" onclick="deleteComment(${comment.id}); return false;" class="inline-flex w-full items-center rounded-md px-3 py-2 text-red-600 hover:bg-gray-100 dark:hover:bg-gray-600"><svg class="me-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M8.586 2.586A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4a2 2 0 0 1 .586-1.414ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z" clip-rule="evenodd"/></svg>Löschen</a></li>`);
            }
            return items.join('');
        };
        
        // Nachrichteninhalt zusammenstellen
        let messageContent = '';
        
        if (nachrichtentyp === 'aufgabe') {
            // Aufgabe: wie view.php – einfache Blase mit Checkbox, Text und Zeit
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
            // Attachment: Einfaches Format - nur Attachments anzeigen, kein Text
            // Wenn keine Attachments vorhanden sind, Nachricht nicht anzeigen (wird später geladen)
            if (!hasAttachments || attachmentsHtml === '') {
                return ''; // Leere Nachricht nicht anzeigen
            }
            messageContent = `
                <div class="rounded-2xl ${messageBgClass} ${messageBorderClass} p-3 text-sm break-words shadow-md inline-block max-w-[92%] sm:max-w-[85%]">
                    ${attachmentsHtml}
                    <span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">${timeDisplay}</span>
                </div>
            `;
        } else if (nachrichtentyp === 'bestellung') {
            // Bestellung: Klick öffnet Detailseite, keine Bestellnummer-Anzeige
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
            // Normale Nachricht oder Solution
            // Wenn nur "[Dateianhang]" und keine Attachments, Nachricht nicht anzeigen
            if (comment.kommentar === '[Dateianhang]' && !hasAttachments) {
                return ''; // Leere Nachricht nicht anzeigen
            }
            // Wenn Beschreibung, dann wie normale Nachricht ohne Label
            // Wenn nur "[Dateianhang]" und Attachments vorhanden, Text nicht anzeigen
            const showText = !isDateianhangOnly && comment.kommentar && comment.kommentar !== '[Dateianhang]';
            const messageText = isDescription ? formatMessageWithLinks(comment.kommentar) : (showText ? formatMessageWithLinks(comment.kommentar) : '');
            const timeSpan = '<span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">' + timeDisplay + '</span>';
            messageContent = `
                <div class="rounded-2xl ${messageRoundedClass} ${messageBgClass} ${messageBorderClass} p-3 text-sm break-words shadow-md inline-block max-w-[92%] sm:max-w-[85%]">
                    ${messageText}${timeSpan}
                    ${attachmentsHtml && !isDateianhangOnly && !isAttachmentMessage ? '<div class="mt-2">' + attachmentsHtml + '</div>' : ''}
                </div>
            `;
        }
        
        // Wenn messageContent leer ist, Nachricht nicht anzeigen
        if (!messageContent || messageContent.trim() === '') {
            return '';
        }
        
        // Beschreibung wie normale Nachricht rendern (Seite abhängig vom Ersteller)
        if (isDescription) {
            // Avatar für Beschreibung bestimmen
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
            
            const descBubbleClass = isCurrentUser ? 'bg-blue-100 dark:bg-blue-900/80 text-gray-900 dark:text-gray-100' : 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-gray-100';
            const descBubble = `<div class="rounded-2xl ${descBubbleClass} p-3 text-sm break-words shadow-md inline-block max-w-[92%] sm:max-w-[85%]">${formatMessageWithLinks(comment.kommentar)} <span class="ml-2 text-[10px] text-gray-500 dark:text-gray-400 opacity-70 align-baseline">${timeDisplay}</span></div>`;
            
            if (isCurrentUser) {
                return daySeparatorHtml + `
                    <div class="chat-row chat-row-sent flex items-start gap-1.5 w-full flex-row-reverse">
                        ${showAvatarAndName ? descAvatarHtml : '<div class="w-8"></div>'}
                        <div class="text-right min-w-0 flex-1">
                            ${descBubble}
                        </div>
                    </div>
                `;
            }
            return daySeparatorHtml + `
                <div class="chat-row chat-row-received flex items-start gap-2 w-full">
                    ${showAvatarAndName ? descAvatarHtml : '<div class="w-8"></div>'}
                    <div class="min-w-0 flex-1">
                        ${descBubble}
                    </div>
                </div>
            `;
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
        }).join('');
        
        chatTicketContent.innerHTML = '<div class="space-y-1 pb-2">' + html + '</div>';
    } catch (error) {
        console.error('Fehler beim Rendern der Kommentare:', error);
        if (chatTicketContent) {
            chatTicketContent.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-sm text-red-500">Fehler beim Rendern der Nachrichten: ' + escapeHtml(error.message) + '</p></div>';
        }
        return;
    }
    
    // Nach unten scrollen
    setTimeout(() => {
        chatTicketContent.scrollTop = chatTicketContent.scrollHeight;
        
        // Dropdown-Menüs initialisieren
        document.querySelectorAll('[data-dropdown-toggle]').forEach(button => {
            const targetId = button.getAttribute('data-dropdown-toggle');
            const dropdown = document.getElementById(targetId);
            if (dropdown && !button.hasAttribute('data-dropdown-initialized')) {
                button.setAttribute('data-dropdown-initialized', 'true');
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    // Alle anderen Dropdowns schließen
                    document.querySelectorAll('[id^="dropdown-"]').forEach(d => {
                        if (d.id !== targetId) {
                            d.classList.add('hidden');
                        }
                    });
                    dropdown.classList.toggle('hidden');
                });
            }
        });
        
        // Dropdown schließen beim Klick außerhalb
        document.addEventListener('click', function(e) {
            if (!e.target.closest('[data-dropdown-toggle]') && !e.target.closest('[id^="dropdown-"]')) {
                document.querySelectorAll('[id^="dropdown-"]').forEach(d => {
                    d.classList.add('hidden');
                });
            }
        });
    }, 100);
}

// === Modal Verbrauchsmaterialien für Bestellung (Ticket mit Gerät) ===
let orderConsumablesModalData = [];

function openOrderConsumablesModal() {
    const modal = document.getElementById('orderConsumablesModal');
    const listEl = document.getElementById('order-consumables-list');
    if (!modal || !listEl) return;
    const searchInput = document.getElementById('order-consumables-search');
    if (searchInput) { searchInput.value = ''; searchInput.oninput = null; }
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
                    const searchEl = document.getElementById('order-consumables-search');
                    if (searchEl) {
                        searchEl.value = '';
                        searchEl.oninput = function filterOrderConsumables() {
                            const q = (this.value || '').trim().toLowerCase();
                            listEl.querySelectorAll('.order-consumable-row').forEach(row => {
                                const text = (row.getAttribute('data-search-text') || '').toLowerCase();
                                row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
                            });
                        };
                    }
                }
                var searchFocusEl = document.getElementById('order-consumables-search');
                if (searchFocusEl) setTimeout(function() { searchFocusEl.focus(); }, 80);
                document.getElementById('order-consumables-apply-btn').disabled = false;
            })
            .catch(() => {
                listEl.innerHTML = '<div class="px-4 py-6 text-sm text-center text-red-500 dark:text-red-400">Fehler beim Laden.</div>';
                document.getElementById('order-consumables-apply-btn').disabled = false;
            });
    } else {
        listEl.innerHTML = '<div class="px-4 py-6 text-sm text-center text-gray-500 dark:text-primary-210">Kein Gerät hinterlegt.</div>';
        document.getElementById('order-consumables-apply-btn').disabled = false;
        const searchEl = document.getElementById('order-consumables-search');
        if (searchEl) setTimeout(function() { searchEl.focus(); }, 80);
    }
}

function closeOrderConsumablesModal() {
    const modal = document.getElementById('orderConsumablesModal');
    if (modal) modal.classList.add('hidden');
}

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
            if (selectedChatTicket) {
                loadTicketComments(selectedChatTicket.id);
                loadTickets();
            }
            if (typeof showToast === 'function') {
                if (errors === 0) showToast(items.length === 1 ? '1 Bestellung angelegt' : items.length + ' Bestellungen angelegt', 'success');
                else if (completed > 0) showToast(completed + ' Bestellung(en) angelegt, ' + errors + ' Fehler', 'warning');
                else showToast('Fehler beim Anlegen der Bestellungen', 'error');
            }
            return;
        }
        var item = items[completed + errors];
        fetch(commentsApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: selectedChatTicket.id,
                kommentar: item.kommentar,
                nachrichtentyp: 'bestellung',
                ist_intern: 0,
                consumable_id: item.consumable_id
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
    const messageInput = document.getElementById('chat-message-input');
    if (messageInput && messageInput.focus) messageInput.focus();
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
    
    const messageInput = document.getElementById('chat-message-input');
    const messageTypeSelect = document.getElementById('message-type-select');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    // Button deaktivieren
    const sendBtn = document.getElementById('send-message-btn');
    sendBtn.disabled = true;
    
    const nachrichtentyp = messageTypeSelect ? messageTypeSelect.value : 'nachricht';
    
    fetch(commentsApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            ticket_id: selectedChatTicket.id,
            kommentar: message,
            nachrichtentyp: nachrichtentyp,
            ist_intern: 0
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            // Kommentare neu laden
            loadTicketComments(selectedChatTicket.id);
            if (typeof showToast === 'function') {
                showToast('Nachricht erfolgreich gesendet', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Senden der Nachricht: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler beim Senden der Nachricht: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
        sendBtn.disabled = false;
    })
    .catch(error => {
        console.error('Fehler beim Senden der Nachricht:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Senden der Nachricht', 'error');
        } else {
            alert('Fehler beim Senden der Nachricht');
        }
        sendBtn.disabled = false;
    });
}

function acceptSolution(commentId) {
    // TODO: Implementierung für Lösung akzeptieren
    if (typeof showToast === 'function') {
        showToast('Lösung akzeptiert', 'success');
    } else {
        alert('Lösung akzeptiert');
    }
}

function rejectSolution(commentId) {
    // TODO: Implementierung für Lösung ablehnen
    if (typeof showToast === 'function') {
        showToast('Lösung abgelehnt', 'info');
    } else {
        alert('Lösung abgelehnt');
    }
}

function toggleTask(commentId, todoId, isCompleted) {
    if (!todoId || todoId === 'null') {
        if (typeof showToast === 'function') {
            showToast('Todo-ID nicht gefunden', 'error');
        } else {
            alert('Todo-ID nicht gefunden');
        }
        return;
    }
    
    const newStatus = isCompleted ? 'erledigt' : 'offen';
    
    fetch('<?php echo BASE_URL; ?>todos/api/todos.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            todo_id: todoId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Kommentare neu laden, um den Status zu aktualisieren
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
            } else {
                alert('Fehler beim Aktualisieren der Aufgabe: ' + (data.error || 'Unbekannter Fehler'));
            }
            // Checkbox zurücksetzen bei Fehler
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
        } else {
            alert('Fehler beim Aktualisieren der Aufgabe');
        }
        // Checkbox zurücksetzen bei Fehler
        const checkbox = document.querySelector(`input[onchange*="${commentId}"]`);
        if (checkbox) {
            checkbox.checked = !isCompleted;
        }
    });
}

function completeTask(commentId) {
    // Legacy-Funktion - weiterleiten zu toggleTask
    toggleTask(commentId, null, true);
}

function trackOrder(commentId) {
    // TODO: Implementierung für Bestellung verfolgen
    if (typeof showToast === 'function') {
        showToast('Bestellung wird verfolgt', 'info');
    } else {
        alert('Bestellung wird verfolgt');
    }
}

function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function copyMessage(text) {
    navigator.clipboard.writeText(text).then(() => {
        if (typeof showToast === 'function') {
            showToast('Nachricht kopiert', 'success');
        }
    }).catch(err => {
        console.error('Fehler beim Kopieren:', err);
    });
}

function deleteComment(commentId) {
    if (confirm('Möchten Sie diese Nachricht wirklich löschen?')) {
        // TODO: API-Aufruf zum Löschen implementieren
        if (typeof showToast === 'function') {
            showToast('Löschfunktion noch nicht implementiert', 'info');
        }
    }
}

function getFileIcon(extension) {
    const ext = (extension || '').toLowerCase();
    // PDF Icon
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
    // Standard-Dokument-Icon für andere Dateitypen
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
        const fileInput = document.getElementById('dropzone-file');
        if (fileInput) {
            fileInput.value = '';
        }
        clearSelectedFile();
        // ESC schließt Modal
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
    if (attachmentModalEscapeHandler) {
        document.removeEventListener('keydown', attachmentModalEscapeHandler);
        attachmentModalEscapeHandler = null;
    }
    selectedCommentIdForAttachment = null;
    clearSelectedFile();
}

let selectedFiles = [];

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

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
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
    
    // Dateien zur Liste hinzufügen
    Array.from(files).forEach(file => {
        // Prüfen ob Datei bereits vorhanden
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
        // Falls selectedFiles leer ist, aber fileInput Dateien hat
        handleFileSelect(fileInput.files);
    }
    
    if (selectedFiles.length === 0) {
        if (typeof showToast === 'function') {
            showToast('Bitte wählen Sie mindestens eine Datei aus', 'error');
        }
        return;
    }
    
    // Upload-Button deaktivieren während des Uploads
    const uploadBtn = document.getElementById('upload-btn');
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Wird hochgeladen...';
    }
    
    // Anhänge immer als normale Nachricht, nicht als Bestellung/Aufgabe/Lösung
    const nachrichtentyp = 'nachricht';
    
    // Für jede Datei einen separaten Kommentar erstellen und dann die Datei hochladen
    const uploadPromises = selectedFiles.map((file, index) => {
        // Erst Kommentar erstellen
        return fetch(commentsApiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
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
            
            // Dann Datei zu diesem Kommentar hochladen
    const formData = new FormData();
    formData.append('file', file);
            formData.append('comment_id', commentData.comment_id);
    
            return fetch(commentAttachmentsApiUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
            .then(attachmentData => {
                if (!attachmentData.success) {
                    throw new Error('Fehler beim Hochladen von ' + file.name + ': ' + (attachmentData.error || 'Unbekannter Fehler'));
                }
                return { success: true, fileName: file.name };
            });
        });
    });
    
    // Alle Uploads parallel ausführen
    Promise.all(uploadPromises)
    .then(results => {
            closeAttachmentModal();
            // Kommentare neu laden
            if (selectedChatTicket) {
                loadTicketComments(selectedChatTicket.id);
            }
        const successCount = results.filter(r => r.success).length;
            if (typeof showToast === 'function') {
            showToast(successCount > 1 ? `${successCount} Dateien erfolgreich hochgeladen` : 'Datei erfolgreich hochgeladen', 'success');
        }
    })
    .catch(error => {
        console.error('Fehler beim Hochladen:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Hochladen: ' + error.message, 'error');
        } else {
            alert('Fehler beim Hochladen: ' + error.message);
        }
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.textContent = selectedFiles.length > 1 ? `${selectedFiles.length} Dateien hochladen` : 'Hochladen';
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function capitalizeFirst(text) {
    if (!text) return '';
    const s = String(text);
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function getDeviceTypeIcon(typ, iconClass) {
    iconClass = iconClass || 'w-5 h-5 text-primary-250 dark:text-primary-280';
    const icons = {
        'drucker': `<svg class="${iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>`,
        'computer': `<svg class="${iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
        'netzwerk': `<svg class="${iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>`,
        'smartphone': `<svg class="${iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>`,
        'monitor': `<svg class="${iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
        'divers': `<svg class="${iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>`
    };
    const fallback = `<svg class="${iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17 9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/></svg>`;
    return icons[typ] || fallback;
}

function getUserAvatarUrl(logopfad) {
    if (!logopfad) return null;
    if (String(logopfad).startsWith('preset:')) return null;
    if (String(logopfad).startsWith('http://') || String(logopfad).startsWith('https://')) return String(logopfad);
    return '<?php echo BASE_URL; ?>' + String(logopfad).replace(/^\//, '');
}

function renderUserAvatarHtml(logopfad, fullName, sizeClasses = 'h-8 w-8 rounded-full') {
    const initials = (fullName || 'U').substring(0, 2).toUpperCase();
    if (logopfad && String(logopfad).startsWith('preset:')) {
        const parts = String(logopfad).split(':');
        const presetColor = parts[1] || '#6b7280';
        const presetInitials = parts[2] || initials;
        return `<div class="${sizeClasses} flex items-center justify-center text-white text-xs font-semibold" style="background:${escapeHtml(presetColor)};">${escapeHtml(presetInitials)}</div>`;
    }
    const url = getUserAvatarUrl(logopfad);
    if (url) {
        return `<img class="${sizeClasses} object-cover" src="${escapeHtml(url)}" alt="${escapeHtml(fullName || 'Profilbild')}">`;
    }
    return `<div class="${sizeClasses} flex items-center justify-center text-white text-xs font-semibold" style="background:#6b7280;">${escapeHtml(initials)}</div>`;
}

// Context Menu Funktionen für Tickets
function formatBearbeitungszeitMinuten(minuten) {
    if (minuten == null || minuten === '' || isNaN(minuten)) return '–';
    const m = parseInt(minuten, 10);
    if (m < 1) return '–';
    if (m < 60) return m + ' Min';
    const h = Math.floor(m / 60);
    const rest = m % 60;
    return rest ? h + ' h ' + rest + ' Min' : h + ' h';
}

function positionTicketContextSubmenu(submenuEl, anchorEl) {
    if (!submenuEl || !anchorEl) return;
    const viewportPadding = 8;
    submenuEl.style.left = '100%';
    submenuEl.style.right = 'auto';
    submenuEl.style.marginLeft = '2px';
    submenuEl.style.marginRight = '0';
    submenuEl.style.top = '0px';
    submenuEl.style.maxWidth = '';
    submenuEl.style.maxHeight = Math.max(140, window.innerHeight - (viewportPadding * 2)) + 'px';
    submenuEl.style.overflowY = 'auto';

    let rect = submenuEl.getBoundingClientRect();
    if (rect.right > window.innerWidth - viewportPadding) {
        submenuEl.style.left = 'auto';
        submenuEl.style.right = '100%';
        submenuEl.style.marginLeft = '0';
        submenuEl.style.marginRight = '2px';
        rect = submenuEl.getBoundingClientRect();
    }

    const anchorRect = anchorEl.getBoundingClientRect();
    let topOffset = 0;
    if (rect.bottom > window.innerHeight - viewportPadding) {
        topOffset -= (rect.bottom - (window.innerHeight - viewportPadding));
    }
    if (anchorRect.top + topOffset < viewportPadding) {
        topOffset += (viewportPadding - (anchorRect.top + topOffset));
    }
    submenuEl.style.top = Math.round(topOffset) + 'px';

    rect = submenuEl.getBoundingClientRect();
    if (rect.left < viewportPadding) {
        submenuEl.style.maxWidth = Math.max(180, window.innerWidth - (viewportPadding * 2)) + 'px';
        if (submenuEl.style.right === '100%') {
            submenuEl.style.right = '0';
        } else {
            submenuEl.style.left = '0';
        }
    }
}

function showTicketContextMenu(clientX, clientY, ticket) {
    ticketContextTicket = ticket;
    const menu = document.getElementById('ticketContextMenu');
    const backdrop = document.getElementById('ticketContextBackdrop');
    if (!menu) return;
    
    menu.classList.remove('hidden');
    if (backdrop) backdrop.classList.remove('hidden');
    // Menü innerhalb des Viewports halten (ohne alte Position zu verwenden)
    const viewportPadding = 8;
    menu.style.left = clientX + 'px';
    menu.style.top = clientY + 'px';
    const rect = menu.getBoundingClientRect();
    const mainContent = document.getElementById('main-content');
    const desktopSidebarOffset = (window.matchMedia('(min-width: 1024px)').matches && mainContent)
        ? Math.max(0, Math.round(mainContent.getBoundingClientRect().left))
        : 0;
    const minLeft = Math.max(viewportPadding, desktopSidebarOffset + viewportPadding);
    const maxLeft = Math.max(minLeft, window.innerWidth - rect.width - viewportPadding);
    const maxTop = Math.max(viewportPadding, window.innerHeight - rect.height - viewportPadding);
    const left = Math.min(Math.max(clientX, minLeft), maxLeft);
    const top = Math.min(Math.max(clientY, viewportPadding), maxTop);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
    
    // Menü-Items basierend auf Ticket-Status und Berechtigung anzeigen/verstecken
    const isAdmin = userRole === 'Admin';
    const isGeschlossen = ticket.status === 'Geschlossen' || ticket.status === 'Archiv';
    const isAbgerechnet = ticket.abgerechnet === 1 || ticket.abgerechnet === '1';
    const hasProjects = ticket.projects && ticket.projects.length > 0;
    const hasWartungsvertrag = ticket.company_hat_wartungsvertrag == 1 || ticket.company_hat_wartungsvertrag === '1';
    
    // Termin festlegen (kein Kunde; nicht geschlossen/archiv; nicht abgerechnet)
    const terminBtn = document.getElementById('ticketCtxTermin');
    if (terminBtn) {
        const canAddTermin = userRole !== 'Kunde' && !isGeschlossen && !isAbgerechnet;
        if (canAddTermin) {
            terminBtn.classList.remove('hidden');
        } else {
            terminBtn.classList.add('hidden');
        }
    }
    
    // Rechnung schreiben (nur für Admins, wenn geschlossen, nicht abgerechnet, keine Projekte, kein Wartungsvertrag)
    const rechnungBtn = document.getElementById('ticketCtxRechnung');
    if (rechnungBtn) {
        if (isAdmin && isGeschlossen && !isAbgerechnet && !hasProjects && !hasWartungsvertrag) {
            rechnungBtn.classList.remove('hidden');
        } else {
            rechnungBtn.classList.add('hidden');
        }
    }
    
    // Bearbeitungszeit: hinzufügen (Admin/Techniker, geschlossen/archiv, noch nicht gesetzt) bzw. Anzeige wenn gesetzt
    const hasBearbeitungszeit = ticket.bearbeitungszeit_minuten != null && ticket.bearbeitungszeit_minuten !== '' && parseInt(ticket.bearbeitungszeit_minuten, 10) > 0;
    const bearbeitungszeitBtn = document.getElementById('ticketCtxBearbeitungszeit');
    if (bearbeitungszeitBtn) {
        if (isAdminOrTech && isGeschlossen && !hasBearbeitungszeit) {
            bearbeitungszeitBtn.classList.remove('hidden');
        } else {
            bearbeitungszeitBtn.classList.add('hidden');
        }
    }
    const bearbeitungszeitInfo = document.getElementById('ticketCtxBearbeitungszeitInfo');
    const bearbeitungszeitInfoText = document.getElementById('ticketCtxBearbeitungszeitInfoText');
    if (bearbeitungszeitInfo && bearbeitungszeitInfoText) {
        if (isGeschlossen && hasBearbeitungszeit) {
            bearbeitungszeitInfoText.textContent = 'Bearbeitungszeit: ' + formatBearbeitungszeitMinuten(ticket.bearbeitungszeit_minuten);
            bearbeitungszeitInfo.classList.remove('hidden');
        } else {
            bearbeitungszeitInfo.classList.add('hidden');
            bearbeitungszeitInfoText.textContent = '';
        }
    }
    const bottomDivider = document.getElementById('ticketCtxRechnungBearbeitungszeitDivider');
    if (bottomDivider) {
        const showRechnung = rechnungBtn && !rechnungBtn.classList.contains('hidden');
        const showBearbBtn = bearbeitungszeitBtn && !bearbeitungszeitBtn.classList.contains('hidden');
        const showBearbInfo = bearbeitungszeitInfo && !bearbeitungszeitInfo.classList.contains('hidden');
        if (showRechnung || showBearbBtn || showBearbInfo) {
            bottomDivider.classList.remove('hidden');
        } else {
            bottomDivider.classList.add('hidden');
        }
    }
    
    // Pin-Text aktualisieren
    const pinText = document.getElementById('ticketCtxPinText');
    if (pinText) {
        const isPinned = ticket.is_pinned === 1 || ticket.is_pinned === '1' || ticket.is_pinned === true;
        pinText.textContent = isPinned ? 'Loslösen' : 'Anheften';
    }
    
    const readUnreadBtn = document.getElementById('ticketCtxReadUnreadBtn');
    const readUnreadLabel = document.getElementById('ticketCtxReadUnreadLabel');
    if (readUnreadBtn && readUnreadLabel) {
        const hasReminder = ticket.unread_reminder === 1 || ticket.unread_reminder === '1' || ticket.unread_reminder === true;
        const unreadN = parseInt(ticket.unread_comments_count, 10) || 0;
        const showAsRead = hasReminder || unreadN > 0;
        if (showAsRead) {
            readUnreadBtn.setAttribute('data-ticket-ctx', 'clear-unread-reminder');
            readUnreadLabel.textContent = 'Als gelesen markieren';
        } else {
            readUnreadBtn.setAttribute('data-ticket-ctx', 'mark-unread');
            readUnreadLabel.textContent = 'Als ungelesen markieren';
        }
    }
    
    // Detail ansicht (nur in Chat-Ansicht)
    const detailViewBtn = document.getElementById('ticketCtxDetailView');
    if (detailViewBtn) {
        if (currentView === 'chat') {
            detailViewBtn.classList.remove('hidden');
        } else {
            detailViewBtn.classList.add('hidden');
        }
    }
    
    // Status ändern und Bearbeiter hinzufügen (nicht für Firmen-User, Firmen-Admin und Kunde)
    const isFirmenUser = userRole === 'Firmen-User';
    const isFirmenAdmin = userRole === 'Firmen-Admin';
    const isKunde = userRole === 'Kunde';
    const hideStatusAndAssign = isFirmenUser || isFirmenAdmin || isKunde;
    
    const statusSection = document.getElementById('ticketCtxStatusSection');
    if (statusSection) {
        if (hideStatusAndAssign) {
            statusSection.classList.add('hidden');
        } else {
            statusSection.classList.remove('hidden');
        }
    }
    
    const assignSection = document.getElementById('ticketCtxAssignSection');
    if (assignSection) {
        if (hideStatusAndAssign) {
            assignSection.classList.add('hidden');
        } else {
            assignSection.classList.remove('hidden');
        }
    }
    
    // "Gehe zu" Sektion initial anzeigen (wird später ausgeblendet, wenn keine Optionen verfügbar sind)
    const goToSection = document.getElementById('ticketCtxGoToSection');
    if (goToSection) {
        goToSection.classList.remove('hidden');
    }
    
    // Gehe zu Optionen vorladen
    setTimeout(() => {
        loadGoToOptionsForContextMenu(ticket);
        if (!hideStatusAndAssign) {
            loadAssignableUsersForContextMenu(ticket);
            updateStatusSubmenuHighlight(ticket);
        }
    }, 100);
}

function updateStatusSubmenuHighlight(ticket) {
    if (!ticket || !ticket.status) return;
    
    const statusSubmenu = document.getElementById('ticketCtxStatusSubmenu');
    if (!statusSubmenu) return;
    
    const statusButtons = statusSubmenu.querySelectorAll('[data-ticket-ctx="status"]');
    const currentStatus = ticket.status;
    
    statusButtons.forEach(button => {
        const buttonStatus = button.getAttribute('data-status');
        const isCurrentStatus = buttonStatus === currentStatus;
        
        // Entferne alle Highlight-Klassen
        button.classList.remove('font-medium', 'bg-blue-50', 'text-blue-800', 'dark:bg-primary-800', 'dark:text-primary-200');
        button.classList.add('text-gray-700', 'dark:text-primary-200');
        
        // Füge Highlight-Klassen hinzu, wenn es der aktuelle Status ist
        if (isCurrentStatus) {
            button.classList.remove('text-gray-700', 'dark:text-primary-200');
            button.classList.add('font-medium', 'bg-blue-50', 'text-blue-800', 'dark:bg-primary-800', 'dark:text-primary-200');
        }
    });
}

function hideTicketContextMenu() {
    ticketContextTicket = null;
    clearTicketContextTargetHighlight();
    const menu = document.getElementById('ticketContextMenu');
    const backdrop = document.getElementById('ticketContextBackdrop');
    const statusSubmenu = document.getElementById('ticketCtxStatusSubmenu');
    const goToSubmenu = document.getElementById('ticketCtxGoToSubmenu');
    const assignSubmenu = document.getElementById('ticketCtxAssignSubmenu');
    if (menu) menu.classList.add('hidden');
    if (backdrop) backdrop.classList.add('hidden');
    if (statusSubmenu) statusSubmenu.classList.add('hidden');
    if (goToSubmenu) goToSubmenu.classList.add('hidden');
    if (assignSubmenu) assignSubmenu.classList.add('hidden');
}

function handleTicketContextMenuClick(e) {
    const btn = e.target.closest('[data-ticket-ctx]');
    if (!btn || !ticketContextTicket) return;
    const action = btn.dataset.ticketCtx;
    const ticketId = parseInt(ticketContextTicket.id);
    
    if (action === 'status') {
        const newStatus = btn.dataset.status;
        changeTicketStatus(ticketId, newStatus);
    } else if (action === 'rechnung') {
        markTicketAsBilled(ticketId);
    } else if (action === 'bearbeitungszeit') {
        openBearbeitungszeitModalForTicket(ticketId);
    } else if (action === 'termin') {
        openTerminModalForTicket(ticketId);
    } else if (action === 'open-new-tab') {
        const viewUrl = '<?php echo BASE_URL; ?>tickets/view.php?id=' + ticketId;
        window.open(viewUrl, '_blank');
    } else if (action === 'detail-view') {
        openTicketDetailView(ticketId);
    } else if (action === 'go-to') {
        const targetType = btn.dataset.targetType;
        navigateToTarget(ticketContextTicket, targetType, btn);
    } else if (action === 'assign') {
        const userId = parseInt(btn.dataset.userId) || 0;
        assignTicketToUser(ticketId, userId === 0 ? null : userId);
    } else if (action === 'pin') {
        toggleTicketPin(ticketId);
    } else if (action === 'mark-unread') {
        markTicketCommentsUnread(ticketId);
    } else if (action === 'clear-unread-reminder') {
        clearTicketUnreadReminder(ticketId);
    }
    hideTicketContextMenu();
}

function markTicketCommentsUnread(ticketId) {
    fetch(commentsApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_unread', ticket_id: ticketId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = typeof data.unread_comments_count === 'number'
                    ? data.unread_comments_count
                    : parseInt(data.unread_comments_count, 10) || 0;
                const reminder = data.unread_reminder === 1 || data.unread_reminder === true || data.unread_reminder === '1';
                const idStr = String(ticketId);
                if (Array.isArray(allTickets)) {
                    allTickets.forEach(t => {
                        if (t && String(t.id) === idStr) {
                            t.unread_comments_count = count;
                            t.unread_reminder = reminder ? 1 : 0;
                        }
                    });
                }
                if (Array.isArray(filteredTickets)) {
                    filteredTickets.forEach(t => {
                        if (t && String(t.id) === idStr) {
                            t.unread_comments_count = count;
                            t.unread_reminder = reminder ? 1 : 0;
                        }
                    });
                }
                if (selectedChatTicket && String(selectedChatTicket.id) === idStr) {
                    selectedChatTicket.unread_comments_count = count;
                    selectedChatTicket.unread_reminder = reminder ? 1 : 0;
                }
                filterTickets();
                if (typeof showToast === 'function') {
                    if (reminder) {
                        showToast('Als ungelesen markiert.', 'success');
                    } else {
                        showToast('Als ungelesen konnte nicht gespeichert werden. Bitte Datenbank-Migration 116 ausführen.', 'warning');
                    }
                }
            } else if (typeof showToast === 'function') {
                showToast('Konnte nicht als ungelesen markieren: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .catch(error => {
            console.error('markTicketCommentsUnread:', error);
            if (typeof showToast === 'function') {
                showToast('Als ungelesen markieren ist fehlgeschlagen. Bitte erneut versuchen.', 'error');
            }
        });
}

function clearTicketUnreadReminder(ticketId) {
    fetch(commentsApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'clear_unread_reminder', ticket_id: ticketId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = typeof data.unread_comments_count === 'number'
                    ? data.unread_comments_count
                    : parseInt(data.unread_comments_count, 10) || 0;
                const idStr = String(ticketId);
                if (Array.isArray(allTickets)) {
                    allTickets.forEach(t => {
                        if (t && String(t.id) === idStr) {
                            t.unread_comments_count = count;
                            t.unread_reminder = 0;
                        }
                    });
                }
                if (Array.isArray(filteredTickets)) {
                    filteredTickets.forEach(t => {
                        if (t && String(t.id) === idStr) {
                            t.unread_comments_count = count;
                            t.unread_reminder = 0;
                        }
                    });
                }
                if (selectedChatTicket && String(selectedChatTicket.id) === idStr) {
                    selectedChatTicket.unread_comments_count = count;
                    selectedChatTicket.unread_reminder = 0;
                }
                filterTickets();
                if (typeof showToast === 'function') {
                    showToast('Als gelesen markiert.', 'success');
                }
            } else if (typeof showToast === 'function') {
                showToast('Konnte nicht als gelesen markieren: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .catch(error => {
            console.error('clearTicketUnreadReminder:', error);
            if (typeof showToast === 'function') {
                showToast('Als gelesen markieren ist fehlgeschlagen. Bitte erneut versuchen.', 'error');
            }
        });
}

function changeTicketStatus(ticketId, newStatus) {
    fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            ticket_id: ticketId,
            status: newStatus
        })
    })
    .then(function (response) {
        if (!response.ok) {
            return response.text().then(function (t) {
                try {
                    return JSON.parse(t);
                } catch (e) {
                    return { success: false, error: t || ('HTTP ' + response.status) };
                }
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Ticket in allTickets aktualisieren
            const ticket = allTickets.find(t => t.id == ticketId);
            if (ticket) {
                ticket.status = newStatus;
            }
            loadTickets();
            if (typeof showToast === 'function') {
                showToast('Status erfolgreich geändert', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Ändern des Status:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Ändern des Status', 'error');
        }
    });
}

function markTicketAsBilled(ticketId) {
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
            const ticket = allTickets.find(t => t.id == ticketId);
            if (ticket) {
                ticket.abgerechnet = 1;
            }
            loadTickets();
            if (typeof showToast === 'function') {
                showToast('Ticket als abgerechnet markiert', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
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

// Variable für das aktuelle Ticket in der Bearbeitungszeit-Modal
let bearbeitungszeitModalTicketId = null;
let terminQuickModalTicketId = null;

function handleBearbeitungszeitModalEscape(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('bearbeitungszeitModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeBearbeitungszeitModal();
            e.preventDefault();
        }
    }
}

function openBearbeitungszeitModalForTicket(ticketId) {
    // Sicherstellen, dass ticketId ein Integer ist
    bearbeitungszeitModalTicketId = parseInt(ticketId);
    if (!bearbeitungszeitModalTicketId || isNaN(bearbeitungszeitModalTicketId)) {
        console.error('Ungültige Ticket-ID:', ticketId);
        if (typeof showToast === 'function') {
            showToast('Fehler: Ungültige Ticket-ID', 'error');
        }
        return;
    }
    setBearbeitungszeitPresetActive(null);
    const customInput = document.getElementById('bearbeitungszeitCustom');
    if (customInput) customInput.value = '';
    const modal = document.getElementById('bearbeitungszeitModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.classList.add('bearbeitungszeit-modal-open');
        document.addEventListener('keydown', handleBearbeitungszeitModalEscape);
    }
}

function closeBearbeitungszeitModal() {
    document.removeEventListener('keydown', handleBearbeitungszeitModalEscape);
    document.body.classList.remove('bearbeitungszeit-modal-open');
    bearbeitungszeitModalTicketId = null;
    const modal = document.getElementById('bearbeitungszeitModal');
    if (modal) {
        modal.classList.add('hidden');
    }
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

function confirmBearbeitungszeit() {
    const min = getBearbeitungszeitValue();
    const minutes = min != null ? min : null;
    
    if (minutes === null) {
        if (typeof showToast === 'function') {
            showToast('Bitte wählen Sie eine Bearbeitungszeit aus oder geben Sie eine ein', 'error');
        }
        return;
    }
    
    if (!bearbeitungszeitModalTicketId) {
        console.error('bearbeitungszeitModalTicketId ist null oder undefined');
        if (typeof showToast === 'function') {
            showToast('Fehler: Kein Ticket ausgewählt', 'error');
        }
        return;
    }
    
    const ticketId = parseInt(bearbeitungszeitModalTicketId);
    if (!ticketId || isNaN(ticketId)) {
        console.error('Ungültige Ticket-ID:', bearbeitungszeitModalTicketId);
        if (typeof showToast === 'function') {
            showToast('Fehler: Ungültige Ticket-ID', 'error');
        }
        return;
    }
    
    
    // Ticket-ID in lokale Variable speichern, bevor Modal geschlossen wird
    const savedTicketId = ticketId;
    
    closeBearbeitungszeitModal();
    
    const payload = { ticket_id: savedTicketId, bearbeitungszeit_minuten: minutes };
    fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Ticket in allTickets aktualisieren
            const ticket = allTickets.find(t => t.id == savedTicketId);
            if (ticket) {
                ticket.bearbeitungszeit_minuten = minutes;
            }
            // Tickets neu laden, um die Änderung zu reflektieren
            loadTickets();
            if (typeof showToast === 'function') {
                showToast('Bearbeitungszeit gespeichert', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannt'), 'error');
            }
        }
    })
    .catch(err => {
        console.error(err);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Speichern', 'error');
        }
    });
}

function formatDateTimeLocalFromDate(d) {
    if (!d || isNaN(d.getTime())) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const h = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return y + '-' + m + '-' + day + 'T' + h + ':' + min;
}

function getDefaultTerminQuickStart() {
    const d = new Date();
    d.setMinutes(0, 0, 0);
    d.setHours(d.getHours() + 1);
    return formatDateTimeLocalFromDate(d);
}

function addOneHourToDateTimeLocal(startLocal) {
    if (!startLocal) return '';
    const d = new Date(startLocal);
    if (isNaN(d.getTime())) return '';
    d.setHours(d.getHours() + 1);
    return formatDateTimeLocalFromDate(d);
}

function setTerminQuickEndFromStart() {
    const startEl = document.getElementById('terminQuickStart');
    const endEl = document.getElementById('terminQuickEnd');
    if (startEl && endEl && startEl.value) {
        endEl.value = addOneHourToDateTimeLocal(startEl.value);
    }
}

function applyTerminQuickPreset(preset) {
    const startEl = document.getElementById('terminQuickStart');
    const endEl = document.getElementById('terminQuickEnd');
    if (!startEl) return;
    const now = new Date();
    let d = new Date();
    if (preset === 'nextHour') {
        d = new Date();
        d.setMinutes(0, 0, 0);
        d.setHours(d.getHours() + 1);
    } else if (preset === 'in1h') {
        d = new Date(now.getTime() + 60 * 60 * 1000);
    } else if (preset === 'today17') {
        d = new Date(now);
        d.setHours(17, 0, 0, 0);
        if (d <= now) {
            d.setDate(d.getDate() + 1);
            d.setHours(17, 0, 0, 0);
        }
    } else if (preset === 'tomorrow9') {
        d = new Date(now);
        d.setDate(d.getDate() + 1);
        d.setHours(9, 0, 0, 0);
    }
    startEl.value = formatDateTimeLocalFromDate(d);
    setTerminQuickEndFromStart();
}

function handleTerminQuickModalEscape(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('terminQuickModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeTerminQuickModal();
            e.preventDefault();
        }
    }
}

function closeTerminQuickModal() {
    document.removeEventListener('keydown', handleTerminQuickModalEscape);
    document.body.classList.remove('termin-quick-modal-open');
    const modal = document.getElementById('terminQuickModal');
    const sheet = document.getElementById('terminQuickSheet');
    if (sheet) sheet.style.transform = '';
    if (!modal) {
        terminQuickModalTicketId = null;
        return;
    }
    modal.classList.remove('termin-quick-sheet-open');
    var isMobile = typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 1023px)').matches;
    var finish = function() {
        modal.classList.add('hidden');
        terminQuickModalTicketId = null;
    };
    if (isMobile) {
        window.setTimeout(finish, 280);
    } else {
        finish();
    }
}

function openTerminModalForTicket(ticketId) {
    var tid = parseInt(ticketId, 10);
    if (!tid || isNaN(tid)) {
        if (typeof showToast === 'function') {
            showToast('Fehler: Ungültige Ticket-ID', 'error');
        }
        return;
    }
    var ticket = Array.isArray(allTickets) ? allTickets.find(function(t) { return t.id == tid; }) : null;
    if (!ticket) {
        if (typeof showToast === 'function') {
            showToast('Ticket nicht in der aktuellen Liste.', 'error');
        }
        return;
    }
    if (ticket.status === 'Geschlossen' || ticket.status === 'Archiv' || ticket.abgerechnet === 1 || ticket.abgerechnet === '1') {
        if (typeof showToast === 'function') {
            showToast('Zu diesem Ticket kann kein Termin mehr hinzugefügt werden.', 'error');
        }
        return;
    }
    terminQuickModalTicketId = tid;
    const startEl = document.getElementById('terminQuickStart');
    const endEl = document.getElementById('terminQuickEnd');
    const titleEl = document.getElementById('terminQuickTitle');
    if (startEl) startEl.value = getDefaultTerminQuickStart();
    setTerminQuickEndFromStart();
    if (titleEl) titleEl.value = '';
    const modal = document.getElementById('terminQuickModal');
    const sheet = document.getElementById('terminQuickSheet');
    if (sheet) sheet.style.transform = '';
    if (modal) {
        modal.classList.remove('termin-quick-sheet-open');
        modal.classList.remove('hidden');
        document.body.classList.add('termin-quick-modal-open');
        document.addEventListener('keydown', handleTerminQuickModalEscape);
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                modal.classList.add('termin-quick-sheet-open');
            });
        });
        var isMobile = typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 1023px)').matches;
        window.setTimeout(function() {
            if (startEl) startEl.focus();
        }, isMobile ? 320 : 50);
    }
}

function submitTerminQuick() {
    if (!terminQuickModalTicketId) {
        if (typeof showToast === 'function') {
            showToast('Fehler: Kein Ticket ausgewählt', 'error');
        }
        return;
    }
    var ticket = Array.isArray(allTickets) ? allTickets.find(function(t) { return t.id == terminQuickModalTicketId; }) : null;
    if (ticket && (ticket.status === 'Geschlossen' || ticket.status === 'Archiv' || ticket.abgerechnet === 1 || ticket.abgerechnet === '1')) {
        if (typeof showToast === 'function') {
            showToast('Zu diesem Ticket kann kein Termin mehr hinzugefügt werden.', 'error');
        }
        closeTerminQuickModal();
        return;
    }
    const startEl = document.getElementById('terminQuickStart');
    const endEl = document.getElementById('terminQuickEnd');
    const titleEl = document.getElementById('terminQuickTitle');
    const startVal = startEl && startEl.value ? startEl.value.trim() : '';
    let endVal = endEl && endEl.value ? endEl.value.trim() : '';
    if (!endVal && startVal) {
        endVal = addOneHourToDateTimeLocal(startVal);
    }
    const titleVal = titleEl && titleEl.value ? titleEl.value.trim() : '';
    if (!startVal) {
        if (typeof showToast === 'function') {
            showToast('Bitte Startzeit auswählen', 'error');
        }
        return;
    }
    const ticketId = terminQuickModalTicketId;
    closeTerminQuickModal();
    fetch(appointmentsApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ticket_id: ticketId,
            titel: titleVal || null,
            typ: 'geplant',
            start_datum: startVal,
            ende_datum: endVal
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data && data.success) {
            const t = allTickets.find(function(x) { return x.id == ticketId; });
            if (t) {
                t.status = 'Geplant';
            }
            loadTickets();
            if (typeof showToast === 'function') {
                showToast('Termin gespeichert', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast((data && data.error) ? data.error : 'Termin konnte nicht gespeichert werden', 'error');
            }
        }
    })
    .catch(function() {
        if (typeof showToast === 'function') {
            showToast('Termin konnte nicht gespeichert werden', 'error');
        }
    });
}

function openTicketDetailView(ticketId) {
    // Öffne das Ticket in der Detail-Ansicht (view.php)
    const viewUrl = '<?php echo BASE_URL; ?>tickets/view.php?id=' + ticketId;
    navigateToTicketDetail(viewUrl);
}

function assignTicketToUser(ticketId, userId) {
    var clearAssign = (userId === null || userId === undefined || userId === '' || userId === 0 || userId === '0');
    /* Wie Todos: JSON null für „kein Bearbeiter“ (array_key_exists in PHP); 0 bleibt API-seitig ebenfalls gültig */
    var zugPayload = clearAssign ? null : userId;
    fetch(ticketsApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            ticket_id: ticketId,
            zugewiesen_an: zugPayload
        })
    })
    .then(function (response) {
        if (!response.ok) {
            return response.text().then(function (t) {
                try {
                    return JSON.parse(t);
                } catch (e) {
                    return { success: false, error: t || ('HTTP ' + response.status) };
                }
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const ticket = allTickets.find(t => t.id == ticketId);
            if (ticket) {
                ticket.zugewiesen_an = clearAssign ? null : zugPayload;
            }
            loadTickets();
            if (typeof showToast === 'function') {
                showToast(clearAssign ? 'Bearbeiter entfernt' : 'Bearbeiter erfolgreich zugewiesen', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Zuweisen des Bearbeiters:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Zuweisen des Bearbeiters', 'error');
        }
    });
}

function toggleTicketPin(ticketId) {
    const ticket = allTickets.find(t => t.id == ticketId);
    if (!ticket) return;
    const currentlyPinned = ticket.is_pinned === 1 || ticket.is_pinned === '1' || ticket.is_pinned === true;
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
        if (data.success) {
            if (ticket) {
                ticket.is_pinned = data.pinned === 1 || data.pinned === '1' ? 1 : 0;
            }
            loadTickets();
            if (typeof showToast === 'function') {
                showToast(ticket.is_pinned ? 'Ticket angeheftet' : 'Anheftung entfernt', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Anheften:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Anheften', 'error');
        }
    });
}

/** Mobil: horizontales Wischen auf Kompakt-Karten (Sky / Violett / Indigo) — Anheften, Bearbeiter, Status */
var TICKET_SWIPE_W_LEFT = 112;
var TICKET_SWIPE_W_RIGHT = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 56 : 0; ?>;
var TICKET_SWIPE_SNAP_EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';
var TICKET_SWIPE_SNAP_MS = 340;

function ticketSwipeSetTranslate(track, x, animate) {
    if (!track) return;
    var nx = Math.max(-TICKET_SWIPE_W_RIGHT, Math.min(TICKET_SWIPE_W_LEFT, x));
    track.dataset.swipeX = String(nx);
    if (animate) {
        track.style.transition = 'transform ' + TICKET_SWIPE_SNAP_MS + 'ms ' + TICKET_SWIPE_SNAP_EASE;
        track.style.willChange = 'transform';
    } else {
        track.style.transition = 'none';
        track.style.willChange = 'transform';
    }
    track.style.transform = 'translateZ(0) translateX(' + nx + 'px)';
    var item = track.closest('.ticket-mobile-item');
    if (item) {
        var revealed = Math.abs(nx) > 0.01;
        item.classList.toggle('ticket-mobile-item--swipe-revealed', revealed);
        var layer = item.querySelector('.ticket-swipe-actions-layer');
        if (layer) layer.setAttribute('aria-hidden', revealed ? 'false' : 'true');
    }
    if (animate) {
        window.clearTimeout(track._ticketSwipeSnapT);
        track._ticketSwipeSnapT = window.setTimeout(function() {
            track.style.willChange = '';
            track._ticketSwipeSnapT = null;
        }, TICKET_SWIPE_SNAP_MS + 80);
    }
}

function ticketSwipeResetAllTracks(exceptTrack) {
    document.querySelectorAll('#mobileTicketsList .ticket-swipe-track').forEach(function(tr) {
        if (exceptTrack && tr === exceptTrack) return;
        if (parseFloat(tr.dataset.swipeX || '0') !== 0) ticketSwipeSetTranslate(tr, 0, true);
    });
}

function ticketSwipeTrackClick(ticketId, ev) {
    if (typeof window.__ticketSwipeBlockClickUntil === 'number' && Date.now() < window.__ticketSwipeBlockClickUntil) return;
    if (ev.target.closest('a[href]')) return;
    if (ev.target.closest('button')) return;
    if (ev.target.closest('input')) return;
    if (ev.target.closest('select')) return;
    var track = ev.currentTarget;
    var off = parseFloat(track.dataset.swipeX || '0') || 0;
    if (off !== 0) {
        ticketSwipeResetAllTracks(null);
        return;
    }
    var url = track.getAttribute('data-ticket-view-url');
    if (url) navigateToTicketDetail(url);
}

function ticketSwipePin(ticketId) {
    ticketSwipeResetAllTracks(null);
    toggleTicketPin(ticketId);
}

/** Mobil-Liste: Wischfläche „Termin“ → Schnell-Termin-Modal auf der Übersicht (kein view.php) */
function ticketSwipeGoToTermin(ticketId) {
    ticketSwipeResetAllTracks(null);
    openTerminModalForTicket(ticketId);
}

/** Nur die drei Status wie im Kontextmenü „Status ändern“ */
var TICKET_SWIPE_STATUS_OPTIONS = ['Neu', 'In Bearbeitung', 'Warteschlange'];

function fillTicketSwipeStatusSelectOptions(sel) {
    if (!sel || !sel.classList.contains('ticket-swipe-status-select')) return;
    var tid = parseInt(sel.dataset.ticketId, 10);
    if (!tid) return;
    var ticket = Array.isArray(allTickets) ? allTickets.find(function(t) { return t.id == tid; }) : null;
    var cur = ticket && ticket.status ? String(ticket.status) : '';
    sel.innerHTML = '';
    var ph = document.createElement('option');
    ph.value = '';
    ph.textContent = 'Status wählen';
    sel.appendChild(ph);
    TICKET_SWIPE_STATUS_OPTIONS.forEach(function(st) {
        var o = document.createElement('option');
        o.value = st;
        o.textContent = st;
        sel.appendChild(o);
    });
    if (cur && TICKET_SWIPE_STATUS_OPTIONS.indexOf(cur) >= 0) {
        sel.value = cur;
    } else {
        sel.value = '';
    }
}

function ticketSwipePrefillStatusSelect(el) {
    if (!el || !el.classList.contains('ticket-swipe-status-select')) return;
    var o0 = el.options[0];
    var looksReady = o0 && o0.textContent !== 'Laden…' && el.options.length >= 1;
    if (looksReady) {
        var tid = parseInt(el.dataset.ticketId, 10);
        var ticket = Array.isArray(allTickets) ? allTickets.find(function(t) { return t.id == tid; }) : null;
        var want = ticket && ticket.status ? String(ticket.status) : '';
        if (want && TICKET_SWIPE_STATUS_OPTIONS.indexOf(want) >= 0) {
            if (el.value !== want) el.value = want;
        } else {
            if (el.value !== '') el.value = '';
        }
        return;
    }
    fillTicketSwipeStatusSelectOptions(el);
}

var _ticketSwipeStatusLastApply = { t: 0, tid: 0, status: '' };

function ticketSwipeApplyStatusFromSelect(sel) {
    if (!sel || !sel.isConnected) return;
    var tid = parseInt(sel.dataset.ticketId, 10);
    if (!tid) return;
    var o0 = sel.options[0];
    if (o0 && o0.textContent === 'Laden…') return;
    var ticket = Array.isArray(allTickets) ? allTickets.find(function(t) { return t.id == tid; }) : null;
    if (!ticket) return;
    var newSt = String(sel.value || '');
    var curSt = ticket.status ? String(ticket.status) : '';
    if (!newSt || newSt === curSt) {
        ticketSwipeResetAllTracks(null);
        return;
    }
    var now = Date.now();
    if (_ticketSwipeStatusLastApply.tid === tid && _ticketSwipeStatusLastApply.status === newSt && now - _ticketSwipeStatusLastApply.t < 700) {
        return;
    }
    _ticketSwipeStatusLastApply = { t: now, tid: tid, status: newSt };
    ticketSwipeResetAllTracks(null);
    changeTicketStatus(tid, newSt);
}

function ticketSwipeCurrentAssigneeIdFromTicket(ticket) {
    if (!ticket) return 0;
    var z = ticket.zugewiesen_an;
    if (z == null || z === '' || z === false) return 0;
    if (String(z) === '0') return 0;
    var n = Number(z);
    if (isNaN(n) || n <= 0) return 0;
    return n;
}

/**
 * Soll-Zuweisung für Abgleich mit dem Select: zuerst Ticket-Objekt (API-/Speicherstand),
 * bei 0 aber data-zugewiesen-an der Karte — sonst bleibt nach veraltetem allTickets-Eintrag
 * „Zurücksetzen“ ohne PUT hängen (curUid und wantUid beide 0 obwohl die Karte noch einen Bearbeiter zeigt).
 */
function ticketSwipeResolveCurrentAssigneeId(sel, ticket) {
    var fromTicket = ticketSwipeCurrentAssigneeIdFromTicket(ticket);
    var fromDom = 0;
    var li = sel && sel.closest ? sel.closest('.ticket-mobile-item') : null;
    if (li && li.hasAttribute('data-zugewiesen-an')) {
        var raw = li.getAttribute('data-zugewiesen-an');
        if (raw != null && raw !== '' && String(raw) !== '0') {
            var dn = parseInt(raw, 10);
            if (!isNaN(dn) && dn > 0) fromDom = dn;
        }
    }
    return fromTicket > 0 ? fromTicket : fromDom;
}

function ticketSwipeWantAssignSelectValue(ticket) {
    var id = ticketSwipeCurrentAssigneeIdFromTicket(ticket);
    /* "0" statt "" — sonst feuert iOS oft kein change beim Wechsel auf „Nicht zugewiesen“ (vgl. Todo-Kommentar) */
    return id === 0 ? '0' : String(id);
}

function fillTicketSwipeAssignSelectOptions(sel) {
    if (!sel || !sel.classList.contains('ticket-swipe-assign-select')) return;
    var tid = parseInt(sel.dataset.ticketId, 10);
    if (!tid) return;
    var users = [];
    if (typeof assigneesData !== 'undefined' && assigneesData !== null) {
        if (Array.isArray(assigneesData)) users = assigneesData;
        else if (typeof assigneesData === 'object') users = Object.values(assigneesData);
    }
    var ticket = Array.isArray(allTickets) ? allTickets.find(function(t) { return t.id == tid; }) : null;
    var want = ticketSwipeWantAssignSelectValue(ticket);
    sel.innerHTML = '';
    var opt0 = document.createElement('option');
    opt0.value = '0';
    opt0.textContent = 'Nicht zugewiesen';
    sel.appendChild(opt0);
    users.forEach(function(user) {
        if (user && (user.id || user.ID)) {
            var uid = user.id || user.ID;
            var opt = document.createElement('option');
            opt.value = String(uid);
            opt.textContent = [user.vorname, user.nachname].filter(Boolean).join(' ').trim() || user.email || ('ID ' + uid);
            sel.appendChild(opt);
        }
    });
    if (want && want !== '0') {
        var hasWant = false;
        for (var oi = 0; oi < sel.options.length; oi++) {
            if (sel.options[oi].value === want) { hasWant = true; break; }
        }
        if (!hasWant && ticket) {
            var optMiss = document.createElement('option');
            optMiss.value = want;
            var label = [ticket.zugewiesen_vorname, ticket.zugewiesen_nachname].filter(Boolean).join(' ').trim();
            optMiss.textContent = label || ('Bearbeiter #' + want);
            sel.appendChild(optMiss);
        }
    }
    sel.value = want;
}

function ticketSwipePrefillAssignSelect(el) {
    if (!el || !el.classList.contains('ticket-swipe-assign-select')) return;
    var o0 = el.options[0];
    var looksReady = o0 && o0.textContent !== 'Laden…' && (el.options.length >= 1);
    if (looksReady) {
        var tid = parseInt(el.dataset.ticketId, 10);
        var ticket = Array.isArray(allTickets) ? allTickets.find(function(t) { return t.id == tid; }) : null;
        var want = ticketSwipeWantAssignSelectValue(ticket);
        if (el.value !== want) el.value = want;
        return;
    }
    fillTicketSwipeAssignSelectOptions(el);
}

var _ticketSwipeAssignLastApply = { t: 0, tid: 0, uid: -1 };

function ticketSwipeApplyAssignFromSelect(sel) {
    if (!sel) return;
    if (!sel.isConnected) return;
    var tid = parseInt(sel.dataset.ticketId, 10);
    if (!tid) return;
    var o0 = sel.options[0];
    if (o0 && o0.textContent === 'Laden…') return;
    var ticket = Array.isArray(allTickets) ? allTickets.find(function(t) { return t.id == tid; }) : null;
    if (!ticket) return;
    var rawVal = sel.value;
    var curUid = (rawVal === '' || rawVal === null || rawVal === undefined || rawVal === '0') ? 0 : parseInt(rawVal, 10);
    if (isNaN(curUid)) curUid = 0;
    var wantUid = ticketSwipeResolveCurrentAssigneeId(sel, ticket);
    if (curUid === wantUid) {
        ticketSwipeResetAllTracks(null);
        return;
    }
    var now = Date.now();
    if (_ticketSwipeAssignLastApply.tid === tid && _ticketSwipeAssignLastApply.uid === curUid && now - _ticketSwipeAssignLastApply.t < 700) {
        return;
    }
    _ticketSwipeAssignLastApply = { t: now, tid: tid, uid: curUid };
    ticketSwipeResetAllTracks(null);
    assignTicketToUser(tid, curUid === 0 ? null : curUid);
}

(function initTicketMobileSwipeGestures() {
    var swipeState = null;

    function isMobileSwipe() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function onTouchStart(e) {
        if (!isMobileSwipe()) return;
        var track = e.target.closest('.ticket-swipe-track');
        if (!track || !track.classList.contains('ticket-mobile-compact-card')) return;
        var list = document.getElementById('mobileTicketsList');
        if (!list || !list.contains(track)) return;
        if (e.target.closest('.ticket-swipe-action')) return;
        if (e.target.closest('a[href], button, input, select, label')) return;
        var t = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]);
        if (!t) return;
        ticketSwipeResetAllTracks(track);
        swipeState = {
            track: track,
            startX: t.clientX,
            startY: t.clientY,
            startOff: parseFloat(track.dataset.swipeX || '0') || 0,
            moved: false
        };
    }

    function onTouchMove(e) {
        if (!swipeState || !swipeState.track) return;
        var t = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]);
        if (!t) return;
        var dx = t.clientX - swipeState.startX;
        var dy = t.clientY - swipeState.startY;
        if (!swipeState.moved && Math.abs(dx) < 10 && Math.abs(dy) < 10) return;
        if (!swipeState.moved && Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 12) {
            swipeState = null;
            return;
        }
        if (Math.abs(dx) >= 10 || swipeState.moved) {
            if (!swipeState.moved) {
                if (Math.abs(dx) > Math.abs(dy)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            } else {
                e.preventDefault();
                e.stopPropagation();
            }
            swipeState.moved = true;
            var nx = swipeState.startOff + dx;
            if (nx > TICKET_SWIPE_W_LEFT) nx = TICKET_SWIPE_W_LEFT + (nx - TICKET_SWIPE_W_LEFT) * 0.12;
            if (nx < -TICKET_SWIPE_W_RIGHT) nx = -TICKET_SWIPE_W_RIGHT + (nx + TICKET_SWIPE_W_RIGHT) * 0.12;
            ticketSwipeSetTranslate(swipeState.track, nx, false);
        }
    }

    function onTouchEnd() {
        if (!swipeState || !swipeState.track) {
            swipeState = null;
            return;
        }
        var tr = swipeState.track;
        var off = parseFloat(tr.dataset.swipeX || '0') || 0;
        var nx = 0;
        if (off < -TICKET_SWIPE_W_RIGHT / 2) nx = -TICKET_SWIPE_W_RIGHT;
        else if (off > TICKET_SWIPE_W_LEFT / 2) nx = TICKET_SWIPE_W_LEFT;
        else nx = 0;
        tr.style.willChange = '';
        ticketSwipeSetTranslate(tr, nx, true);
        if (swipeState.moved) window.__ticketSwipeBlockClickUntil = Date.now() + 320;
        swipeState = null;
    }

    document.addEventListener('touchstart', onTouchStart, { capture: true, passive: true });
    document.addEventListener('touchmove', onTouchMove, { capture: true, passive: false });
    document.addEventListener('touchend', onTouchEnd, { capture: true, passive: true });
    document.addEventListener('touchcancel', onTouchEnd, { capture: true, passive: true });
})();

document.addEventListener('focusin', function(e) {
    if (!e.target || !e.target.classList) return;
    if (e.target.classList.contains('ticket-swipe-assign-select')) {
        ticketSwipePrefillAssignSelect(e.target);
    } else if (e.target.classList.contains('ticket-swipe-status-select')) {
        ticketSwipePrefillStatusSelect(e.target);
    }
});

document.addEventListener('change', function(e) {
    if (!e.target || !e.target.classList) return;
    if (e.target.classList.contains('ticket-swipe-assign-select')) {
        ticketSwipeApplyAssignFromSelect(e.target);
    } else if (e.target.classList.contains('ticket-swipe-status-select')) {
        ticketSwipeApplyStatusFromSelect(e.target);
    }
});

/* Mobil: Native Picker liefert oft kein change beim Zurücksetzen — wie bei Todos blur nachziehen */
document.addEventListener('blur', function(e) {
    if (!e.target || !e.target.classList || !e.target.classList.contains('ticket-swipe-assign-select')) return;
    window.setTimeout(function() {
        if (!e.target.isConnected) return;
        ticketSwipeApplyAssignFromSelect(e.target);
    }, 0);
}, true);

document.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    var tr = e.target && e.target.closest && e.target.closest('.ticket-swipe-track.ticket-mobile-compact-card');
    if (!tr || e.target !== tr) return;
    if (parseFloat(tr.dataset.swipeX || '0') !== 0) return;
    e.preventDefault();
    var url = tr.getAttribute('data-ticket-view-url');
    if (url) navigateToTicketDetail(url);
});

function loadGoToOptionsForContextMenu(ticket) {
    const submenu = document.getElementById('ticketCtxGoToSubmenu');
    if (!submenu) {
        console.error('FEHLER: ticketCtxGoToSubmenu nicht gefunden!');
        return;
    }
    
    if (!ticket) {
        console.error('FEHLER: Kein Ticket übergeben');
        submenu.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Kein Ticket verfügbar</div>';
        return;
    }
    
    // Prüfe ob Status ändern und Bearbeiter für diese Rolle ausgeblendet werden sollen
    const isFirmenUser = userRole === 'Firmen-User';
    const isFirmenAdmin = userRole === 'Firmen-Admin';
    const isKunde = userRole === 'Kunde';
    const hideStatusAndAssign = isFirmenUser || isFirmenAdmin || isKunde;
    
    let html = '';
    
    // Firma (nicht für Firmen-User, Firmen-Admin und Kunde)
    if (!hideStatusAndAssign && ticket.company_id && ticket.company_id !== null && ticket.company_id !== '' && ticket.company_id !== 0) {
        const companyName = ticket.company_name || 'Firma';
        html += `<button type="button" data-ticket-ctx="go-to" data-target-type="company" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>
            <span>${escapeHtml(companyName)}</span>
        </button>`;
    }
    
    // Kunde
    if (ticket.customer_id && ticket.customer_id !== null && ticket.customer_id !== '' && ticket.customer_id !== 0) {
        const customerName = ticket.customer_name || 'Kunde';
        html += `<button type="button" data-ticket-ctx="go-to" data-target-type="customer" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/></svg>
            <span>${escapeHtml(customerName)}</span>
        </button>`;
    }
    
    // Gerät
    if (ticket.device_id && ticket.device_id !== null && ticket.device_id !== '' && ticket.device_id !== 0) {
        const deviceName = ticket.device_name || 'Gerät';
        html += `<button type="button" data-ticket-ctx="go-to" data-target-type="device" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"/></svg>
            <span>${escapeHtml(deviceName)}</span>
        </button>`;
    }
    
    // Projekt - verschiedene Möglichkeiten prüfen (nicht für Firmen-User, Firmen-Admin und Kunde)
    let projectsFound = false;
    
    if (!hideStatusAndAssign) {
        // Möglichkeit 1: ticket.projects als Array
        if (ticket.projects && Array.isArray(ticket.projects) && ticket.projects.length > 0) {
            projectsFound = true;
            ticket.projects.forEach((project, index) => {
                const projectName = project.bezeichnung || project.name || project.project_nummer || project.nummer || 'Projekt';
                const projectId = project.id || project.project_id;
                if (projectId) {
                    html += `<button type="button" data-ticket-ctx="go-to" data-target-type="project" data-project-id="${projectId}" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/></svg>
                        <span>${escapeHtml(projectName)}</span>
                    </button>`;
                }
            });
        }
        
        // Möglichkeit 2: ticket.projects als Objekt (nicht Array)
        if (!projectsFound && ticket.projects && typeof ticket.projects === 'object' && !Array.isArray(ticket.projects)) {
            projectsFound = true;
            const project = ticket.projects;
            const projectName = project.bezeichnung || project.name || project.project_nummer || project.nummer || 'Projekt';
            const projectId = project.id || project.project_id;
            if (projectId) {
                html += `<button type="button" data-ticket-ctx="go-to" data-target-type="project" data-project-id="${projectId}" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/></svg>
                    <span>${escapeHtml(projectName)}</span>
                </button>`;
            }
        }
    }
    
    // Anforderer (nicht für Firmen-User, Firmen-Admin und Kunde)
    if (!hideStatusAndAssign) {
        const anfordererId = ticket.anforderer_id || ticket.erstellt_von;
        if (anfordererId && anfordererId !== null && anfordererId !== '' && anfordererId !== 0) {
            const anfordererName = (ticket.ersteller_vorname && ticket.ersteller_nachname) 
                ? `${ticket.ersteller_vorname} ${ticket.ersteller_nachname}`.trim()
                : (ticket.ersteller_email || 'Anforderer');
            html += `<button type="button" data-ticket-ctx="go-to" data-target-type="anforderer" data-anforderer-id="${anfordererId}" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>${escapeHtml(anfordererName)}</span>
            </button>`;
        }
    }
    
    
    // "Gehe zu" Sektion ausblenden, wenn keine Optionen verfügbar sind
    const goToSection = document.getElementById('ticketCtxGoToSection');
    
    if (html === '') {
        if (goToSection) {
            goToSection.classList.add('hidden');
        }
        submenu.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Keine Optionen verfügbar</div>';
    } else {
        if (goToSection) {
            goToSection.classList.remove('hidden');
        }
        submenu.innerHTML = html;
    }
    if (!submenu.classList.contains('hidden') && goToSection) {
        requestAnimationFrame(function() {
            positionTicketContextSubmenu(submenu, goToSection);
        });
    }
}

function navigateToTarget(ticket, targetType, btn) {
    if (!ticket) return;
    
    const baseUrl = '<?php echo BASE_URL; ?>';
    let url = '';
    
    switch(targetType) {
        case 'company':
            if (ticket.company_id) {
                url = baseUrl + 'companies/detail.php?id=' + ticket.company_id;
            }
            break;
        case 'customer':
            if (ticket.customer_id) {
                url = baseUrl + 'customers/detail.php?id=' + ticket.customer_id;
            }
            break;
        case 'device':
            if (ticket.device_id) {
                url = baseUrl + 'devices/detail.php?id=' + ticket.device_id;
            }
            break;
        case 'project':
            const projectId = btn ? btn.getAttribute('data-project-id') : (ticket.projects && ticket.projects.length > 0 ? (ticket.projects[0].id || ticket.projects[0].project_id) : null);
            if (projectId) {
                url = baseUrl + 'projects/view.php?id=' + projectId;
            }
            break;
        case 'anforderer':
            const anfordererId = btn ? btn.getAttribute('data-anforderer-id') : (ticket.anforderer_id || ticket.erstellt_von);
            if (anfordererId) {
                url = baseUrl + 'users/view.php?id=' + anfordererId;
            }
            break;
    }
    
    if (url) {
        window.location.href = url;
    } else {
        if (typeof showToast === 'function') {
            showToast('Ziel nicht verfügbar', 'error');
        }
    }
}

function loadAssignableUsersForContextMenu(ticket) {
    const submenu = document.getElementById('ticketCtxAssignSubmenu');
    if (!submenu) {
        console.error('ticketCtxAssignSubmenu nicht gefunden');
        return;
    }
    
    // Prüfe ob assigneesData verfügbar ist
    let users = [];
    if (typeof assigneesData !== 'undefined' && assigneesData !== null) {
        if (Array.isArray(assigneesData)) {
            users = assigneesData;
        } else if (typeof assigneesData === 'object') {
            // Falls es ein Objekt ist, versuche es zu konvertieren
            users = Object.values(assigneesData);
        }
    }
    
    // Prüfe ob Ticket einen Bearbeiter hat
    const hasAssignee = ticket && ticket.zugewiesen_an && ticket.zugewiesen_an !== '' && ticket.zugewiesen_an !== '0' && parseInt(ticket.zugewiesen_an) !== 0;
    
    if (users.length > 0) {
        let html = '';
        // Nur "Kein Bearbeiter" anzeigen, wenn Ticket bereits einen Bearbeiter hat
        if (hasAssignee) {
            html = '<button type="button" data-ticket-ctx="assign" data-user-id="0" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">Bearbeiter entfernen</button>';
        }
        users.forEach(user => {
            if (user && (user.id || user.ID)) {
                const userId = user.id || user.ID;
                const userName = [user.vorname, user.nachname].filter(Boolean).join(' ').trim() || user.email || 'Unbekannt';
                const isAssigned = ticket && ticket.zugewiesen_an && (parseInt(ticket.zugewiesen_an) === parseInt(userId));
                html += `<button type="button" data-ticket-ctx="assign" data-user-id="${userId}" class="w-full px-3 py-2 text-left text-sm ${isAssigned ? 'font-medium bg-blue-50 text-blue-800 dark:bg-primary-800 dark:text-primary-200' : 'text-gray-700 dark:text-primary-200'} hover:bg-gray-50 dark:hover:bg-primary-140 flex items-center gap-2">${escapeHtml(userName)}</button>`;
            }
        });
        submenu.innerHTML = html;
    } else {
        // Versuche die Benutzer direkt aus dem Filter zu holen, falls verfügbar
        const assigneeFilterMenu = document.getElementById('assignee-filter-menu');
        if (assigneeFilterMenu) {
            const assigneeOptions = assigneeFilterMenu.querySelectorAll('.assignee-option[data-assignee-id]');
            if (assigneeOptions.length > 0) {
                let html = '';
                // Nur "Kein Bearbeiter" anzeigen, wenn Ticket bereits einen Bearbeiter hat
                if (hasAssignee) {
                    html = '<button type="button" data-ticket-ctx="assign" data-user-id="0" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">Bearbeiter entfernen</button>';
                }
                assigneeOptions.forEach(option => {
                    const userId = option.getAttribute('data-assignee-id');
                    if (userId && userId !== '') {
                        const userName = option.textContent.trim();
                        const isAssigned = ticket && ticket.zugewiesen_an && (parseInt(ticket.zugewiesen_an) === parseInt(userId));
                        html += `<button type="button" data-ticket-ctx="assign" data-user-id="${userId}" class="w-full px-3 py-2 text-left text-sm ${isAssigned ? 'font-medium bg-blue-50 text-blue-800 dark:bg-primary-800 dark:text-primary-200' : 'text-gray-700 dark:text-primary-200'} hover:bg-gray-50 dark:hover:bg-primary-140 flex items-center gap-2">${escapeHtml(userName)}</button>`;
                    }
                });
                submenu.innerHTML = html;
                const assignSection = document.getElementById('ticketCtxAssignSection');
                if (!submenu.classList.contains('hidden') && assignSection) {
                    requestAnimationFrame(function() {
                        positionTicketContextSubmenu(submenu, assignSection);
                    });
                }
                return;
            }
        }
        submenu.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Keine Bearbeiter verfügbar</div>';
    }
    const assignSection = document.getElementById('ticketCtxAssignSection');
    if (!submenu.classList.contains('hidden') && assignSection) {
        requestAnimationFrame(function() {
            positionTicketContextSubmenu(submenu, assignSection);
        });
    }
}
</script>

<style>
/* Custom Scrollbar für Chat und Sidebar */
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
</style>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
