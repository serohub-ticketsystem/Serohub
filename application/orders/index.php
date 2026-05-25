<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
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

// Kunden dürfen die Bestellungsseite nicht sehen
if ($userRole === 'Kunde') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Firmen und Kunden für Filter laden
$companies = [];
$customers = [];

if ($userRole === 'Admin' || $userRole === 'Techniker') {
    // Alle aktiven Firmen
    $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Alle aktiven Kunden mit Kundennummer und Firmenname
    $stmt = $pdo->query("SELECT c.id, c.name, c.kundennummer, c.email, c.company_id, comp.name as company_name FROM customers c LEFT JOIN companies comp ON c.company_id = comp.id WHERE c.status = 'aktiv' ORDER BY c.name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && $userCompanyId) {
    // Nur eigene Firma
    $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv'");
    $stmt->execute([$userCompanyId]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kunden der Firma und Kunden ohne Firma mit Kundennummer
    $stmt = $pdo->prepare("SELECT c.id, c.name, c.kundennummer, c.email, c.company_id, comp.name as company_name FROM customers c LEFT JOIN companies comp ON c.company_id = comp.id WHERE (c.company_id = ? OR c.company_id IS NULL) AND c.status = 'aktiv' ORDER BY c.name");
    $stmt->execute([$userCompanyId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
require_once dirname(__DIR__) . '/customers/helper/encryption.php';
foreach ($customers as &$c) {
    decrypt_customer_row($c);
    if (isset($c['company_name'])) $c['company_name'] = decrypt_from_db($c['company_name']);
}
unset($c);
if (!empty($companies)) {
    foreach ($companies as &$co) { decrypt_company_row($co); }
    unset($co);
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content" class="kalender-page relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-hidden">
  <main class="pt-4 pr-4 pb-4 pl-1 flex flex-col overflow-hidden">
    <nav class="mb-4 flex flex-shrink-0" aria-label="Breadcrumb">
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
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Bestellungen</span>
          </div>
        </li>
      </ol>
    </nav>
  <div class="relative col-span-full">
    <div class="relative service-toolbar-wrap flex-shrink-0">
      <div class="flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0">
        <div class="flex flex-col w-full space-y-3 lg:flex-1 lg:min-w-0 md:space-y-0 md:flex-row md:items-center md:gap-2">
          <form class="w-auto md:max-w-sm search-form-base shrink-0" id="search-form">
            <label for="search"
                   class="text-sm font-medium text-gray-900 sr-only dark:text-white">Suche</label>
            <div class="relative flex items-center" id="search-wrapper">
              <button type="button" id="search-toggle-btn" class="search-toggle-open flex items-center justify-center gap-0 rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 focus:outline-none transition-all duration-200 shrink-0 min-w-[2.5rem] text-xs font-medium py-2 px-2 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 dark:focus:ring-primary-500/30 dark:focus:border-primary-400" title="Suche öffnen">
                <svg aria-hidden="true" class="w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0 block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
              </button>
              <div class="search-field-container overflow-hidden transition-[max-width,opacity] duration-300 ease-out" style="width: 0; opacity: 0;" data-search-container>
                <div class="relative flex items-center search-field-inner">
                  <input type="search" id="search"
                         class="service-toolbar-search-input block w-full box-border h-[2.125rem] min-h-[2.125rem] max-h-[2.125rem] py-0 pl-3 pr-12 text-xs font-medium leading-[2.125rem] text-gray-900 rounded-xl border border-gray-200 bg-white/80 placeholder-gray-500 hover:bg-white hover:border-gray-300 focus:outline-none focus:border-primary-400 focus:bg-white transition-all duration-200 dark:bg-primary-700/80 dark:border-primary-320 dark:text-primary-200 dark:placeholder-primary-210 dark:hover:bg-primary-760 dark:hover:border-primary-300 dark:focus:border-primary-400 dark:focus:bg-primary-760"
                         placeholder="Suchen...">
                  <div class="absolute inset-y-0 right-0 flex items-center pr-1">
                    <button type="button" id="search-close-btn" class="search-close-btn flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 focus:outline-none transition-colors hidden" title="Suche schließen" aria-label="Suche schließen">
                      <svg class="search-close-icon search-icon w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                      </svg>
                      <svg class="search-close-icon x-icon w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </form>
          <div class="flex flex-wrap items-center gap-1.5 md:gap-2 flex-1 min-w-0">
          <div class="relative w-auto" id="status-filter-container">
            <button type="button" id="status-filter-button" class="status-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200">
              <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.583 8.445h.01M10.86 19.71l-6.573-6.63a.993.993 0 0 1 0-1.4l7.329-7.394A.98.98 0 0 1 12.31 4l5.734.007A1.968 1.968 0 0 1 20 5.983v5.5a.992.992 0 0 1-.316.727l-7.44 7.5a.974.974 0 0 1-1.384.001Z"/>
              </svg>
              <span id="status-filter-text" class="filter-btn-label whitespace-nowrap">Offen</span>
              <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div id="status-filter-menu" class="hidden absolute z-10 min-w-[12rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base shadow-card overflow-hidden" data-popper-placement="bottom">
              <div class="py-1 overflow-y-auto max-h-[20rem]">
                <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="__orders_pipeline__">Offen</button>
                <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="Bestellt">Bestellt</button>
                <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="Unterwegs">Unterwegs</button>
                <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="Beim Kunden">Beim Kunden</button>
                <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="Im Lager">Im Lager</button>
                <button type="button" class="status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="Angekommen">Angekommen</button>
              </div>
            </div>
            <input type="hidden" id="status-filter" value="__orders_pipeline__">
          </div>
          <?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') && !empty($customers)): ?>
          <div class="relative w-auto" id="customer-filter-container">
            <button type="button" id="customer-filter-button" class="customer-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200">
              <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
              </svg>
              <span id="customer-filter-text" class="filter-btn-label whitespace-nowrap">Alle Kunden</span>
              <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <input type="hidden" id="customer-filter" value="">
          </div>
          <?php endif; ?>
          <!-- Sortier-Dropdown mit Richtungs-Button (wie Service) -->
          <div class="inline-flex rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 overflow-hidden transition-all duration-200" role="group">
            <div class="relative flex-1 min-w-0" id="sort-dropdown-container">
              <button type="button" id="sort-dropdown-button" class="sort-filter-btn w-full flex items-center gap-2 pl-3 pr-2 py-2 text-xs font-medium text-gray-700 dark:text-primary-200 bg-transparent hover:bg-transparent border-0 border-r border-gray-200 dark:border-primary-320 rounded-none focus:outline-none transition-all duration-200">
                <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 20V10m0 10-3-3m3 3 3-3m5-13v10m0-10 3 3m-3-3-3 3"/>
                </svg>
                <span id="sort-dropdown-text" class="filter-btn-label whitespace-nowrap">Sortieren</span>
                <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
              <div id="sort-dropdown-menu" class="hidden absolute z-50 w-full min-w-[10rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base shadow-card overflow-hidden" data-popper-placement="bottom">
                <div class="py-1 divide-y divide-gray-200 dark:divide-primary-230">
                  <button type="button" class="sort-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="erstellt_datum">Erstellt</button>
                  <button type="button" class="sort-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="geaendert_datum">Geändert</button>
                  <button type="button" class="sort-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="beschreibung">Bestellung</button>
                  <button type="button" class="sort-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="device_name">Gerät</button>
                  <button type="button" class="sort-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="device_standort">Gerätestandort</button>
                  <button type="button" class="sort-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-sort="status">Status</button>
                </div>
              </div>
              <input type="hidden" id="sort-selection" value="">
            </div>
            <button type="button" id="sort-direction-button" class="flex items-center justify-center px-2.5 py-2 text-gray-500 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-760 border-0 rounded-none focus:outline-none transition-colors" title="Sortierrichtung ändern">
              <svg id="sort-direction-icon" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" aria-hidden="true">
                <path d="M4 6h6M4 10h6M4 14h6"/>
                <path id="sort-direction-path" d="M16 6V15M12 11l4 4 4-4"/>
              </svg>
            </button>
          </div>
          <div class="hidden md:inline-flex">
            <button type="button" id="viewToggleBtn" class="view-toggle filter-btn--default flex items-center justify-center p-2 rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:outline-none dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200" title="Ansicht wechseln (Tabellenansicht)">
              <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
              </svg>
            </button>
          </div>
          </div>
        </div>
        <div class="flex flex-col items-stretch justify-end flex-shrink-0 w-full pb-4 md:pb-0 md:w-auto md:flex-row md:items-center md:space-x-3">
          <a href="<?php echo BASE_URL; ?>orders/create.php"
                  class="flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480 hover:bg-primaryLight-440 dark:hover:bg-primary-440 focus:ring-4 focus:ring-primary-250 focus:outline-none">
            <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewbox="0 0 20 20"
                 xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Bestellung anlegen
          </a>
        </div>
      </div>
    </div>
      <div class="hidden flex-wrap items-center gap-x-4 gap-y-3 pt-3 pb-4 border-t border-gray-200 dark:border-primary-120">
        <div class="items-center hidden text-xs font-semibold tracking-wide uppercase text-gray-500 md:flex dark:text-primary-230">
          Status:
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
          <div class="flex items-center">
            <input id="offen" type="radio" value="__orders_pipeline__" checked name="status"
                   class="w-4 h-4 bg-white border-gray-300 text-primary-500 focus:ring-primary-500/40 dark:text-primary-420 dark:bg-primary-300 dark:border-primary-320">
            <label for="offen" class="ml-2 text-sm font-medium text-gray-700 dark:text-primary-220">
              Offen
            </label>
          </div>
          <div class="flex items-center">
            <input id="bestellt" type="radio" value="Bestellt" name="status"
                   class="w-4 h-4 bg-white border-gray-300 text-primary-500 focus:ring-primary-500/40 dark:text-primary-420 dark:bg-primary-300 dark:border-primary-320">
            <label for="bestellt" class="ml-2 text-sm font-medium text-gray-700 dark:text-primary-220">
              Bestellt
            </label>
          </div>
          <div class="flex items-center">
            <input id="unterwegs" type="radio" value="Unterwegs" name="status"
                   class="w-4 h-4 bg-white border-gray-300 text-primary-500 focus:ring-primary-500/40 dark:text-primary-420 dark:bg-primary-300 dark:border-primary-320">
            <label for="unterwegs" class="ml-2 text-sm font-medium text-gray-700 dark:text-primary-220">
              Unterwegs
            </label>
          </div>
          <div class="flex items-center">
            <input id="beim_kunden" type="radio" value="Beim Kunden" name="status"
                   class="w-4 h-4 bg-white border-gray-300 text-primary-500 focus:ring-primary-500/40 dark:text-primary-420 dark:bg-primary-300 dark:border-primary-320">
            <label for="beim_kunden" class="ml-2 text-sm font-medium text-gray-700 dark:text-primary-220">
              Beim Kunden
            </label>
          </div>
          <div class="flex items-center">
            <input id="im_lager" type="radio" value="Im Lager" name="status"
                   class="w-4 h-4 bg-white border-gray-300 text-primary-500 focus:ring-primary-500/40 dark:text-primary-420 dark:bg-primary-300 dark:border-primary-320">
            <label for="im_lager" class="ml-2 text-sm font-medium text-gray-700 dark:text-primary-220">
              Im Lager
            </label>
          </div>
          <div class="flex items-center">
            <input id="angekommen" type="radio" value="Angekommen" name="status"
                   class="w-4 h-4 bg-white border-gray-300 text-primary-500 focus:ring-primary-500/40 dark:text-primary-420 dark:bg-primary-300 dark:border-primary-320">
            <label for="angekommen" class="ml-2 text-sm font-medium text-gray-700 dark:text-primary-220">
              Angekommen
            </label>
          </div>
          <div class="flex items-center">
            <input id="all" type="radio" value="" name="status"
                   class="w-4 h-4 bg-white border-gray-300 text-primary-500 focus:ring-primary-500/40 dark:text-primary-420 dark:bg-primary-300 dark:border-primary-320">
            <label for="all" class="ml-2 text-sm font-medium text-gray-700 dark:text-primary-220">
              Alle
            </label>
          </div>
        </div>
      </div>

    <!-- Hinweis: nur neueste N Bestellungen (von Dashboard-Karte), mit Link "Alle anzeigen" -->
    <div id="orders-neu-hint" class="hidden mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
      <span id="orders-neu-hint-text"></span>
      <a href="#" id="orders-neu-hint-show-all" class="ml-2 font-medium underline hover:no-underline">Alle anzeigen</a>
    </div>

    <!-- Card-Ansicht -->
    <div id="cardView" class="hidden">
      <div id="cardViewContainer" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div class="col-span-full flex justify-center items-center py-8">
          <div role="status">
            <svg aria-hidden="true" class="w-8 h-8 text-neutral-tertiary animate-spin fill-brand" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
              <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
            </svg>
            <span class="sr-only">Loading...</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabellenansicht -->
    <div id="tableView" class="overflow-x-auto rounded-xl border border-gray-200 dark:border-primary-120">
      <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
        <thead class="bg-white text-xs uppercase text-gray-500 dark:bg-primary-100 dark:text-gray-400">
        <tr>
<th id="sort-beschreibung" data-sort="beschreibung" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-50 dark:hover:bg-primary-140">
  <div class="flex items-center">
    Bestellung
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-status" data-sort="status" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-50 dark:hover:bg-primary-140">
  <div class="flex items-center">
    Status
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-device_name" data-sort="device_name" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-50 dark:hover:bg-primary-140">
  <div class="flex items-center">
    Gerät
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-customer_name" data-sort="customer_name" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-50 dark:hover:bg-primary-140" <?php if ($userRole === 'Kunde' || $userRole === 'Firmen-User'): ?>style="display: none;"<?php endif; ?>>
  <div class="flex items-center">
    Kunde / Firma
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-erstellt_datum" data-sort="erstellt_datum" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-50 dark:hover:bg-primary-140">
  <div class="flex items-center">
    Erstellt
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
            <?php if ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin'): ?>
            <th scope="col" class="px-4 py-3 font-semibold">Aktionen</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody id="ordersList">
        <tr>
            <td colspan="<?php
                // Spalten: Bestellung, Status, Gerät, (Kunde/Firma?), Erstellt, (Aktionen?)
                $showCustomerCol = !($userRole === 'Kunde' || $userRole === 'Firmen-User');
                $showActionsCol = ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin');
                echo 4 + ($showCustomerCol ? 1 : 0) + ($showActionsCol ? 1 : 0);
            ?>" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">
                <div class="flex justify-center items-center">
                    <div role="status">
                        <svg aria-hidden="true" class="w-8 h-8 text-neutral-tertiary animate-spin fill-brand" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                            <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                        </svg>
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </td>
        </tr>
    </tbody>
</table>     
    </div>
  </div>
  </main>
</div>

<!-- Modal: Suchbereich Bestellungs-Suche (wonach gesucht wird) -->
<div id="orderSearchScopeModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4" aria-labelledby="order-search-scope-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity cursor-pointer" aria-hidden="true" id="orderSearchScopeModalOverlay"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg max-h-[calc(100vh-2rem)] flex flex-col relative z-10">
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden flex flex-col max-h-[calc(100vh-2rem)]">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 flex-shrink-0">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-primary-200" id="order-search-scope-modal-title">
                            Suchbereich für Bestellungs-Suche
                        </h3>
                        <button type="button" id="closeOrderSearchScopeModalBtn" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Leg fest, in welchen Feldern bei der Suche gesucht wird.</p>
                </div>
                <div class="flex-1 min-h-0 max-h-[min(60vh,28rem)] overflow-y-auto overflow-x-hidden border-t border-gray-200 dark:border-primary-120 px-4 py-4 custom-scrollbar">
                    <div id="order-search-scope-modal-container" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <template id="order-search-scope-modal-template">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600">
                                <input type="checkbox" class="order-search-scope-modal-cb h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800">
                                <span class="order-search-scope-modal-label text-sm text-gray-700 dark:text-gray-300"></span>
                            </label>
                        </template>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" id="order-search-scope-modal-all" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Alle auswählen</button>
                        <button type="button" id="order-search-scope-modal-none" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Keine</button>
                    </div>
                </div>
                <div class="px-4 pb-5 pt-2 sm:px-6 sm:pb-6 flex-shrink-0 border-t border-gray-200 dark:border-primary-120">
                    <button type="button" id="order-search-scope-modal-save" class="w-full inline-flex justify-center rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                        Übernehmen
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Kundenauswahl -->
<?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') && !empty($customers)): ?>
<div id="customerModal" tabindex="-1" class="hidden fixed inset-0 z-50 overflow-y-auto p-4" aria-labelledby="customer-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity" aria-hidden="true" id="customerModalOverlay"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg relative z-10">
            <div class="rounded-lg bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 text-left shadow-xl overflow-hidden dark:bg-gray-800">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="customer-modal-title">
                        Kunde auswählen
                    </h3>
                    <button type="button" id="closeCustomerModalBtn" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <!-- Suchfeld -->
                <div class="mb-4">
                    <input type="text" id="customerSearchInput" placeholder="Kunde suchen..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-250 focus:border-primary-250 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                
                <!-- Scrollbare Liste -->
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg dark:border-gray-600">
                    <div id="customersTableBody" class="divide-y divide-gray-200 dark:divide-gray-600">
                        <div class="customer-row hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer select-customer-row px-4 py-3" 
                             data-customer-id=""
                             data-customer-name=""
                             data-customer-search="">
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Alle Kunden</span>
                            </div>
                        </div>
                        <?php foreach ($customers as $customer): ?>
                        <div class="customer-row hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer select-customer-row px-4 py-3" 
                             data-customer-id="<?= (int)$customer['id'] ?>"
                             data-customer-name="<?= htmlspecialchars(strtolower($customer['name'] . ' ' . ($customer['kundennummer'] ?? '') . ' ' . ($customer['company_name'] ?? ''))) ?>"
                             data-customer-display-name="<?= htmlspecialchars($customer['name']) ?>"
                             data-customer-kundennummer="<?= htmlspecialchars($customer['kundennummer'] ?? '') ?>"
                             data-customer-company-name="<?= htmlspecialchars($customer['company_name'] ?? '') ?>">
                            <div class="flex items-center">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white block truncate">
                                            <?= htmlspecialchars($customer['name']) ?>
                                        </span>
                                        <?php if (($userRole === 'Admin' || $userRole === 'Techniker') && $customer['company_name']): ?>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                                            <?= htmlspecialchars($customer['company_name']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($customer['kundennummer']): ?>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block truncate">
                                        Knd-Nr.: <?= htmlspecialchars($customer['kundennummer']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="orderCompanyContextMenu" class="hidden fixed z-[80] min-w-[220px] rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-lg overflow-hidden">
    <button type="button" id="orderCompanyContextAssignBtn" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">
        Firma hinzufügen
    </button>
</div>

<div id="orderCompanyAssignModal" tabindex="-1" class="hidden fixed inset-0 z-[90] overflow-y-auto p-4" aria-modal="true" role="dialog">
    <div class="fixed inset-0 bg-black/50 dark:bg-black/70 transition-opacity" id="orderCompanyAssignModalOverlay"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg relative z-10">
            <div class="relative rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-3">Firma zur Bestellung zuweisen</h3>
            <p class="text-sm text-gray-500 dark:text-primary-240 mb-4">Wählen Sie die Firma aus, die nachträglich zur Bestellung hinzugefügt werden soll.</p>
            <div class="space-y-3 mb-5">
                <input type="text" id="orderCompanySearchInput" placeholder="Firma suchen..." class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent"/>
                <div id="orderCompanyList" class="max-h-72 overflow-y-auto rounded-base border border-gray-200 dark:border-primary-120 divide-y divide-gray-200 dark:divide-primary-120"></div>
            </div>
            <div class="flex flex-wrap gap-2 justify-end">
                <button type="button" id="orderCompanyAssignCancelBtn" class="px-4 py-2 text-sm font-medium rounded-base border border-gray-300 dark:border-primary-120 text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
                <button type="button" id="orderCompanyAssignSaveBtn" class="px-4 py-2 text-sm font-medium rounded-base bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480 hover:bg-primaryLight-440 dark:hover:bg-primary-440 disabled:opacity-50 disabled:cursor-not-allowed" disabled>Firma speichern</button>
            </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Kunden-Filter aktiv: analog zur Ticket-Ansicht */
html:not(.dark) #customer-filter-button.customer-filter-btn--active,
html:not(.dark) #status-filter-button.status-filter-btn--active {
    background-color: rgba(79, 70, 229, 0.12);
    border-color: #4f46e5;
    color: #312e81;
    font-weight: 700;
    box-shadow: none;
}
html:not(.dark) #customer-filter-button.customer-filter-btn--active .filter-btn-icon,
html:not(.dark) #customer-filter-button.customer-filter-btn--active .filter-btn-chevron,
html:not(.dark) #status-filter-button.status-filter-btn--active .filter-btn-icon,
html:not(.dark) #status-filter-button.status-filter-btn--active .filter-btn-chevron {
    color: #1e293b;
}
.dark #customer-filter-button.customer-filter-btn--active,
.dark #status-filter-button.status-filter-btn--active {
    background-color: #312e81;
    border-color: #4f46e5;
    color: #e5e7eb;
    font-weight: 700;
    box-shadow: none;
}
.dark #customer-filter-button.customer-filter-btn--active .filter-btn-icon,
.dark #customer-filter-button.customer-filter-btn--active .filter-btn-chevron,
.dark #status-filter-button.status-filter-btn--active .filter-btn-icon,
.dark #status-filter-button.status-filter-btn--active .filter-btn-chevron {
    color: #d1d5db;
}

/* Inaktive Toolbar-Buttons im Dark-Mode */
.dark #customer-filter-button.filter-btn--default,
.dark #status-filter-button.filter-btn--default,
.dark #search-toggle-btn.search-toggle-open,
.dark #search {
    background-color: #292a2d !important;
    border-color: #3a3d42 !important;
}
.dark #customer-filter-button.filter-btn--default:hover,
.dark #status-filter-button.filter-btn--default:hover,
.dark #search-toggle-btn.search-toggle-open:hover,
.dark #search:hover {
    background-color: #323438 !important;
    border-color: #4a4d52 !important;
}
.dark div[role="group"]:has(#sort-dropdown-container),
.dark #viewToggleBtn.view-toggle.filter-btn--default {
    background-color: #292a2d !important;
    border-color: #3a3d42 !important;
}
.dark div[role="group"]:has(#sort-dropdown-container):hover,
.dark #viewToggleBtn.view-toggle.filter-btn--default:hover {
    background-color: #323438 !important;
    border-color: #4a4d52 !important;
}
.dark #sort-dropdown-button .filter-btn-chevron {
    color: rgb(148 163 184) !important;
}
.dark .status-filter-btn.filter-btn--default .filter-btn-label,
.dark .customer-filter-btn.filter-btn--default .filter-btn-label,
.dark #sort-dropdown-button .filter-btn-label {
    color: #d1d5db !important;
}
.dark .status-filter-btn.filter-btn--default .filter-btn-icon,
.dark .customer-filter-btn.filter-btn--default .filter-btn-icon,
.dark #viewToggleBtn .filter-btn-icon,
.dark #search-toggle-btn.search-toggle-open svg {
    color: #aeb4bd !important;
}

#search-form {
    transition: flex 0.3s ease-in-out, max-width 0.3s ease-in-out, margin-right 0.3s ease-in-out, width 0.3s ease-in-out;
    flex: 0 0 auto;
    margin-right: 0;
    width: auto;
}
#search-form.search-expanded {
    flex: 1 1 auto;
    max-width: 100%;
}
@media (min-width: 768px) {
    #search-form.search-expanded {
        max-width: min(50%, 22rem);
        margin-right: 0.5rem;
    }
}
@media (min-width: 1280px) {
    #search-form.search-expanded {
        max-width: min(40%, 20rem);
    }
}

/* Ansicht-Umschalter (wie Tickets) */
.view-toggle.view-toggle--active {
    background-color: #4f46e5;
    color: white;
    border-color: #4f46e5;
}
.view-toggle.view-toggle--active svg {
    color: white;
}
.dark .view-toggle.view-toggle--active {
    background-color: #312e81;
    border-color: #4f46e5;
    color: #e5e7eb;
}
.dark .view-toggle.view-toggle--active svg {
    color: #e5e7eb;
}

/* Suche aktiv: Hervorhebung wenn Suchbegriff eingegeben */
#search-wrapper.search-active input {
    border-color: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.18);
}
.dark #search-wrapper.search-active input {
    border-color: #60a5fa;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
}

#search-wrapper {
    display: flex;
    align-items: center;
    width: auto;
    position: relative;
}
#search-toggle-btn.search-toggle-open {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
    min-width: 2.5rem;
    box-sizing: border-box;
    transition: opacity 0.16s ease-out;
    z-index: 1;
}
#search-wrapper input#search,
#search-toggle-btn.search-toggle-open {
    border-color: #e5e7eb;
}
#search-wrapper input#search:hover,
#search-toggle-btn.search-toggle-open:hover {
    border-color: #d1d5db;
}
#search-wrapper input#search:focus,
#search-toggle-btn.search-toggle-open:focus {
    border-color: rgb(59 130 246);
}
#search-wrapper.search-expanded .search-toggle-open {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0;
    pointer-events: none;
}
.search-field-container {
    flex: 1;
    min-width: 0;
    transition: max-width 0.28s ease-out, opacity 0.28s ease-out, width 0.26s ease-in;
    max-width: 0;
    width: 0;
    opacity: 0;
}
#search-wrapper.search-closing .search-field-container {
    transition: width 0.3s ease-in, opacity 0.22s ease-in;
    max-width: none;
    opacity: 0;
}
#search-wrapper.search-expanded .search-field-container {
    max-width: 100%;
    width: auto;
    opacity: 1 !important;
    margin-left: 0;
}
#search-wrapper.search-expanded .search-close-btn {
    display: flex !important;
}
.customer-filter-btn .filter-btn-label,
.status-filter-btn .filter-btn-label {
    transition: opacity 0.18s ease-out, max-width 0.22s ease-out;
    overflow: hidden;
    display: inline-block;
    max-width: 16rem;
    white-space: nowrap;
}
.customer-filter-btn .filter-btn-chevron,
.status-filter-btn .filter-btn-chevron {
    transition: opacity 0.18s ease-out, max-width 0.18s ease-out;
    overflow: hidden;
    display: inline-block;
    max-width: 1.5rem;
}
.customer-filter-btn.filter-btn--default .filter-btn-label,
.customer-filter-btn.filter-btn--default .filter-btn-chevron,
.status-filter-btn.filter-btn--default .filter-btn-label,
.status-filter-btn.filter-btn--default .filter-btn-chevron {
    opacity: 0;
    max-width: 0;
    min-width: 0;
    padding-left: 0;
    padding-right: 0;
    margin: 0;
    visibility: hidden;
}
.customer-filter-btn,
.status-filter-btn {
    transition: padding-left 0.2s ease-out, padding-right 0.2s ease-out, min-width 0.2s ease-out;
    align-items: center;
}
.customer-filter-btn.filter-btn--default,
.status-filter-btn.filter-btn--default {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
    min-width: 2.5rem;
    justify-content: center;
    gap: 0;
}
#customer-filter-container,
#status-filter-container,
#sort-dropdown-container {
    flex-shrink: 0;
}

@media (max-width: 768px) {
    #search-wrapper.search-expanded .search-field-container {
        max-width: 100%;
    }
    #customer-filter-container,
    #status-filter-container {
        flex: 1 1 auto;
        min-width: 0;
    }
}
</style>

<script>
const ordersApiUrl = '<?php echo BASE_URL; ?>orders/api/orders.php';
const companiesApiUrl = '<?php echo BASE_URL; ?>companies/api/companies.php';
/** Listenfilter: alle Bestellungen außer „Angekommen“ (nicht identisch mit dem Statuswert „Neu“). */
const ORDERS_STATUS_PIPELINE = '__orders_pipeline__';
function ordersFilterCoercePipeline(v) {
    const s = (v != null && String(v).trim() !== '') ? String(v).trim() : '';
    if (s === '' || s === 'Offen' || s === ORDERS_STATUS_PIPELINE) return ORDERS_STATUS_PIPELINE;
    return s;
}
function ordersFilterButtonLabel(v) {
    return ordersFilterCoercePipeline(v) === ORDERS_STATUS_PIPELINE ? 'Offen' : (v && String(v).trim() !== '' ? String(v).trim() : 'Offen');
}
const userRole = '<?php echo addslashes($userRole); ?>';
const initialStatusFromHash = (function() {
    const hashToStatus = { 'offen': ORDERS_STATUS_PIPELINE, 'bestellt': 'Bestellt', 'unterwegs': 'Unterwegs', 'beim-kunden': 'Beim Kunden', 'im-lager': 'Im Lager', 'angekommen': 'Angekommen' };
    const hash = (window.location.hash || '').replace(/^#/, '').toLowerCase();
    return hashToStatus[hash] || '';
})();
const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
const showCustomer = <?php echo ($userRole !== 'Kunde' && $userRole !== 'Firmen-User') ? 'true' : 'false'; ?>;
const showActions = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') ? 'true' : 'false'; ?>;
const canAssignOrderCompany = (userRole === 'Admin' || userRole === 'Techniker');
let selectedCompanyId = null;
let allOrders = [];
let filteredOrders = [];
let sortColumn = null;
let sortDirection = 'desc';
let orderCompanyContextOrderId = null;
let orderCompanySelectedId = null;
let orderCompanySelectedName = '';
let orderCompanyListCache = [];

const ORDERS_FILTER_STORAGE_KEY = 'ordersIndexFilters';
let orderSearchScope = []; // Suchbereich aus user_settings; leer = alle Felder
/** Bei Aufruf von Dashboard-System-Card: nur die N neuesten Bestellungen mit diesem Status anzeigen (N aus ?neu=). */
let orderCardNeuLimit = null;

function getOrdersFiltersState() {
    const customerFilter = document.getElementById('customer-filter');
    const customerFilterText = document.getElementById('customer-filter-text');
    const statusFilterInput = document.getElementById('status-filter');
    const searchEl = document.getElementById('search');
    const sortSelection = document.getElementById('sort-selection');
    return {
        customer: customerFilter ? customerFilter.value : '',
        customerText: (customerFilterText && customerFilterText.textContent) ? customerFilterText.textContent.trim() : '',
        status: statusFilterInput ? ordersFilterCoercePipeline(statusFilterInput.value || '') : ORDERS_STATUS_PIPELINE,
        search: searchEl ? searchEl.value : '',
        sortColumn: sortColumn || (sortSelection ? sortSelection.value : '') || null,
        sortDirection: sortDirection || 'desc'
    };
}

function saveOrdersFiltersState() {
    try {
        const state = getOrdersFiltersState();
        localStorage.setItem(ORDERS_FILTER_STORAGE_KEY, JSON.stringify(state));
    } catch (e) {
        console.error('Fehler beim Speichern der Bestellungs-Filter', e);
    }
}

function restoreOrdersFiltersState() {
    try {
        const raw = localStorage.getItem(ORDERS_FILTER_STORAGE_KEY);
        const customerFilter = document.getElementById('customer-filter');
        const customerFilterText = document.getElementById('customer-filter-text');
        const statusFilterInput = document.getElementById('status-filter');
        const statusFilterText = document.getElementById('status-filter-text');
        const searchEl = document.getElementById('search');
        if (raw) {
            const state = JSON.parse(raw);
            if (state.customer !== undefined && customerFilter) customerFilter.value = state.customer || '';
            if (state.customerText !== undefined && customerFilterText) customerFilterText.textContent = state.customerText || 'Alle Kunden';
            if (state.status !== undefined) {
                let restoredStatus = state.status && state.status.trim() !== '' ? state.status : ORDERS_STATUS_PIPELINE;
                restoredStatus = ordersFilterCoercePipeline(restoredStatus);
                const radio = document.querySelector(`input[name="status"][value="${restoredStatus}"]`);
                if (radio) radio.checked = true;
                if (statusFilterInput) statusFilterInput.value = restoredStatus;
                if (statusFilterText) statusFilterText.textContent = ordersFilterButtonLabel(restoredStatus);
            }
            if (state.search !== undefined && searchEl) searchEl.value = state.search || '';
            if (state.sortColumn) {
                sortColumn = state.sortColumn;
                const sortSel = document.getElementById('sort-selection');
                if (sortSel) sortSel.value = state.sortColumn;
            }
            if (state.sortDirection === 'asc' || state.sortDirection === 'desc') sortDirection = state.sortDirection;
        }
        // Ohne gespeicherte Sortierung: Standard = Geändert, abwärts (neueste zuerst)
        if (!sortColumn) {
            sortColumn = 'geaendert_datum';
            sortDirection = 'desc';
            const sortSel = document.getElementById('sort-selection');
            if (sortSel) sortSel.value = 'geaendert_datum';
        }
    } catch (e) {
        console.error('Fehler beim Wiederherstellen der Bestellungs-Filter', e);
    }
}

function updateOrdersNeuHint() {
    const hintEl = document.getElementById('orders-neu-hint');
    const textEl = document.getElementById('orders-neu-hint-text');
    if (!hintEl || !textEl) return;
    if (orderCardNeuLimit != null) {
        const statusFilterInput = document.getElementById('status-filter');
        const statusFilter = statusFilterInput ? (statusFilterInput.value || '') : '';
        const cardStatuses = ['Angekommen', 'Beim Kunden', 'Im Lager'];
        if (statusFilter && cardStatuses.includes(statusFilter)) {
            hintEl.classList.remove('hidden');
            textEl.textContent = 'Es werden die ' + orderCardNeuLimit + ' neuesten Bestellungen mit diesem Status angezeigt (von der Dashboard-Karte).';
            return;
        }
    }
    hintEl.classList.add('hidden');
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

    // Gespeicherte Filter wiederherstellen (vor dem ersten Laden)
    restoreOrdersFiltersState();

    // Bestell-Suchbereich laden (User-Einstellung: in welchen Feldern gesucht wird)
    const orderScopeApiUrl = '<?php echo BASE_URL; ?>settings/api/order-search-scope.php';
    fetch(orderScopeApiUrl, { method: 'GET', headers: { 'Content-Type': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success && Array.isArray(d.scope)) {
                orderSearchScope = d.scope;
                if (typeof filterOrders === 'function') filterOrders();
            }
        })
        .catch(function() {});
    
    // Suchfeld Toggle: exakt wie Tickets
    const searchToggleBtn = document.getElementById('search-toggle-btn');
    const searchWrapper = document.getElementById('search-wrapper');
    const searchInput = document.getElementById('search');
    const searchFieldContainer = document.querySelector('#search-form .search-field-container');
    const searchForm = document.getElementById('search-form');
    const searchCloseBtn = document.getElementById('search-close-btn');
    const searchCloseIconSearch = searchCloseBtn ? searchCloseBtn.querySelector('.search-close-icon.search-icon') : null;
    const searchCloseIconX = searchCloseBtn ? searchCloseBtn.querySelector('.search-close-icon.x-icon') : null;

    function setCloseBtnIconToSearch() {
        if (searchCloseIconSearch) searchCloseIconSearch.classList.remove('hidden');
        if (searchCloseIconX) searchCloseIconX.classList.add('hidden');
    }
    function setCloseBtnIconToX() {
        if (searchCloseIconSearch) searchCloseIconSearch.classList.add('hidden');
        if (searchCloseIconX) searchCloseIconX.classList.remove('hidden');
    }

    if (searchToggleBtn && searchWrapper && searchFieldContainer && searchInput) {
        function collapseSearchField() {
            setCloseBtnIconToSearch();
            if (!searchInput.value.trim()) {
                setTimeout(() => {
                    if (!searchWrapper.classList.contains('search-expanded')) searchInput.blur();
                }, 260);
            }
            const startWidth = searchFieldContainer.offsetWidth;
            searchFieldContainer.style.width = startWidth + 'px';
            searchFieldContainer.style.maxWidth = 'none';
            searchWrapper.classList.add('search-closing');
            searchWrapper.classList.remove('search-expanded');
            if (searchForm) searchForm.classList.remove('search-expanded');
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    searchFieldContainer.style.width = '0';
                });
            });
            const onCloseDone = function(e) {
                if (e.propertyName !== 'width') return;
                searchFieldContainer.removeEventListener('transitionend', onCloseDone);
                searchWrapper.classList.remove('search-closing');
                searchFieldContainer.style.width = '';
                searchFieldContainer.style.maxWidth = '';
            };
            searchFieldContainer.addEventListener('transitionend', onCloseDone);
        }

        function expandSearchField() {
            searchWrapper.classList.add('search-expanded');
            if (searchForm) searchForm.classList.add('search-expanded');
            setCloseBtnIconToSearch();
            setTimeout(function() { searchInput.focus(); }, 150);
            const onExpandDone = function() {
                searchFieldContainer.removeEventListener('transitionend', onExpandDone);
                setCloseBtnIconToX();
            };
            searchFieldContainer.addEventListener('transitionend', onExpandDone);
        }

        function toggleSearchField() {
            const isExpanded = searchWrapper.classList.contains('search-expanded');
            if (isExpanded) collapseSearchField();
            else expandSearchField();
        }

        searchToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSearchField();
        });

        if (searchCloseBtn) {
            searchCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (searchWrapper.classList.contains('search-expanded')) {
                    searchInput.value = '';
                    if (typeof updateSearchActiveState === 'function') updateSearchActiveState();
                    filterOrders();
                    saveOrdersFiltersState();
                    collapseSearchField();
                }
            });
        }

        searchInput.addEventListener('blur', function() {
            setTimeout(() => {
                const activeElement = document.activeElement;
                if (!searchInput.value.trim() &&
                    activeElement !== searchToggleBtn &&
                    !activeElement.closest('#search-wrapper')) {
                    collapseSearchField();
                }
            }, 200);
        });
        searchInput.addEventListener('focus', function() {
            if (!searchWrapper.classList.contains('search-expanded')) expandSearchField();
        });

        searchWrapper.addEventListener('mousedown', function(e) {
            if (e.target.closest('#search-close-btn') ||
                e.target.closest('#search') ||
                e.target === searchInput) {
                e.stopPropagation();
            }
        });

        if (searchInput.value.trim()) {
            searchWrapper.classList.add('search-expanded');
            if (searchForm) searchForm.classList.add('search-expanded');
            setCloseBtnIconToX();
        }
    }

    let searchDebounceTimer = null;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            updateSearchActiveState();
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(function() {
                searchDebounceTimer = null;
                filterOrders();
                saveOrdersFiltersState();
            }, 350);
        });
    }
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (searchInput) searchInput.blur();
            filterOrders();
            saveOrdersFiltersState();
        });
    }
    
    // Kunde-Filter Button Event Listener (nur wenn vorhanden)
    const customerFilterButton = document.getElementById('customer-filter-button');
    if (customerFilterButton) {
        customerFilterButton.addEventListener('click', function() {
            const customerModal = document.getElementById('customerModal');
            if (customerModal) {
                customerModal.classList.remove('hidden');
                const searchInput = document.getElementById('customerSearchInput');
                if (searchInput) {
                    setTimeout(() => searchInput.focus(), 100);
                }
            }
        });
    }
    
    // Modal schließen
    const closeCustomerModalBtn = document.getElementById('closeCustomerModalBtn');
    const customerModalOverlay = document.getElementById('customerModalOverlay');
    const customerModal = document.getElementById('customerModal');
    
    function closeCustomerModal() {
        if (customerModal) {
            customerModal.classList.add('hidden');
        }
        const searchInput = document.getElementById('customerSearchInput');
        if (searchInput) {
            searchInput.value = '';
            filterCustomers('');
        }
    }
    
    if (closeCustomerModalBtn) {
        closeCustomerModalBtn.addEventListener('click', closeCustomerModal);
    }
    
    if (customerModalOverlay) {
        customerModalOverlay.addEventListener('click', closeCustomerModal);
    }

    function updateCustomerFilterButtonState() {
        const customerFilterButton = document.getElementById('customer-filter-button');
        const customerFilter = document.getElementById('customer-filter');
        if (!customerFilterButton || !customerFilter) return;
        if (customerFilter.value && customerFilter.value.trim() !== '') {
            customerFilterButton.classList.add('customer-filter-btn--active');
            customerFilterButton.classList.remove('filter-btn--default');
        } else {
            customerFilterButton.classList.remove('customer-filter-btn--active');
            customerFilterButton.classList.add('filter-btn--default');
        }
    }

    function getCurrentStatusFilterValue() {
        const statusFilterInput = document.getElementById('status-filter');
        return statusFilterInput ? ordersFilterCoercePipeline(statusFilterInput.value || '') : ORDERS_STATUS_PIPELINE;
    }

    function updateStatusFilterButtonState() {
        const statusBtn = document.getElementById('status-filter-button');
        const statusText = document.getElementById('status-filter-text');
        const statusFilterInput = document.getElementById('status-filter');
        if (!statusBtn || !statusText) return;
        const rawVal = statusFilterInput ? statusFilterInput.value : '';
        const currentStatus = getCurrentStatusFilterValue();
        if (statusFilterInput && currentStatus !== rawVal) {
            statusFilterInput.value = currentStatus;
        }
        statusText.textContent = ordersFilterButtonLabel(currentStatus);
        const isDefault = currentStatus === ORDERS_STATUS_PIPELINE;
        if (isDefault) {
            statusBtn.classList.add('filter-btn--default');
            statusBtn.classList.remove('status-filter-btn--active');
        } else {
            statusBtn.classList.remove('filter-btn--default');
            statusBtn.classList.add('status-filter-btn--active');
        }
    }

    function updateSearchActiveState() {
        const wrapper = document.getElementById('search-wrapper');
        const searchEl = document.getElementById('search');
        if (!wrapper || !searchEl) return;
        wrapper.classList.toggle('search-active', searchEl.value.trim() !== '');
    }

    // Popup: Suchbereich für Bestellungs-Suche
    (function() {
        const modal = document.getElementById('orderSearchScopeModal');
        const overlay = document.getElementById('orderSearchScopeModalOverlay');
        const openBtn = document.getElementById('order-search-scope-btn');
        const closeBtn = document.getElementById('closeOrderSearchScopeModalBtn');
        const container = document.getElementById('order-search-scope-modal-container');
        const template = document.getElementById('order-search-scope-modal-template');
        const btnAll = document.getElementById('order-search-scope-modal-all');
        const btnNone = document.getElementById('order-search-scope-modal-none');
        const btnSave = document.getElementById('order-search-scope-modal-save');
        let modalAllKeys = {};

        function openModal() {
            if (!modal) return;
            modal.classList.remove('hidden');
            fetch(orderScopeApiUrl, { method: 'GET', headers: { 'Content-Type': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        const modalScope = d.scope || [];
                        modalAllKeys = d.all_keys || {};
                        container.querySelectorAll('.order-search-scope-modal-cb').forEach(function(el) { el.closest('label')?.remove(); });
                        Object.keys(modalAllKeys).forEach(function(key) {
                            const label = template.content.cloneNode(true);
                            const cb = label.querySelector('.order-search-scope-modal-cb');
                            const labelText = label.querySelector('.order-search-scope-modal-label');
                            cb.value = key;
                            cb.dataset.key = key;
                            labelText.textContent = modalAllKeys[key];
                            cb.checked = (modalScope[0] !== '_none') && (modalScope.length === 0 || modalScope.indexOf(key) !== -1);
                            container.appendChild(label);
                        });
                    }
                })
                .catch(function() {});
        }

        function closeModal() {
            if (modal) modal.classList.add('hidden');
        }

        function saveModalScope() {
            const checked = Array.from(container.querySelectorAll('.order-search-scope-modal-cb:checked')).map(function(c) { return c.value; });
            const keysLen = Object.keys(modalAllKeys).length;
            const body = { scope: (keysLen && checked.length === keysLen) ? Object.keys(modalAllKeys) : checked };
            fetch(orderScopeApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success) {
                    orderSearchScope = d.scope || [];
                    if (typeof showToast === 'function') showToast('Suchbereich übernommen', 'success');
                    closeModal();
                    if (typeof filterOrders === 'function') filterOrders();
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
            container.querySelectorAll('.order-search-scope-modal-cb').forEach(function(c) { c.checked = true; });
        });
        if (btnNone) btnNone.addEventListener('click', function() {
            container.querySelectorAll('.order-search-scope-modal-cb').forEach(function(c) { c.checked = false; });
        });
    })();
    
    // ESC-Taste zum Schließen
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const orderScopeModal = document.getElementById('orderSearchScopeModal');
        if (orderScopeModal && !orderScopeModal.classList.contains('hidden')) {
            orderScopeModal.classList.add('hidden');
        } else if (customerModal && !customerModal.classList.contains('hidden')) {
            closeCustomerModal();
        }
    });
    
    // Sortier-Dropdown (wie Service)
    const statusFilterContainer = document.getElementById('status-filter-container');
    const statusFilterButton = document.getElementById('status-filter-button');
    const statusFilterMenu = document.getElementById('status-filter-menu');
    const statusFilterInput = document.getElementById('status-filter');
    if (statusFilterContainer && statusFilterButton && statusFilterMenu) {
        statusFilterButton.addEventListener('click', function(e) {
            e.stopPropagation();
            statusFilterMenu.classList.toggle('hidden');
        });
        statusFilterMenu.querySelectorAll('.status-option').forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                const statusValue = this.getAttribute('data-status') || '';
                if (statusFilterInput) {
                    statusFilterInput.value = ordersFilterCoercePipeline(statusValue || '');
                }
                const radio = document.querySelector(`input[name="status"][value="${statusValue}"]`);
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
                statusFilterMenu.classList.add('hidden');
                updateStatusFilterButtonState();
            });
        });
        document.addEventListener('click', function(e) {
            if (!statusFilterContainer.contains(e.target)) {
                statusFilterMenu.classList.add('hidden');
            }
        });
    }

    // Sortier-Dropdown (wie Service)
    const sortDropdownContainer = document.getElementById('sort-dropdown-container');
    const sortDropdownButton = document.getElementById('sort-dropdown-button');
    const sortDropdownMenu = document.getElementById('sort-dropdown-menu');
    const sortSelection = document.getElementById('sort-selection');
    if (sortDropdownButton && sortDropdownMenu && sortDropdownContainer) {
        sortDropdownButton.addEventListener('click', function(e) {
            e.stopPropagation();
            sortDropdownMenu.classList.toggle('hidden');
            if (customerModal) closeCustomerModal();
        });
        sortDropdownMenu.querySelectorAll('.sort-option').forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                const sortType = this.getAttribute('data-sort');
                sortColumn = sortType;
                sortDirection = 'desc';
                sortOrders(sortColumn, true, true);
                if (sortSelection) sortSelection.value = sortType;
                sortDropdownMenu.classList.add('hidden');
                saveOrdersFiltersState();
            });
        });
        document.addEventListener('click', function(e) {
            const sortDirectionBtn = document.getElementById('sort-direction-button');
            if (!sortDropdownContainer.contains(e.target) && e.target !== sortDirectionBtn && !sortDirectionBtn?.contains(e.target)) {
                sortDropdownMenu.classList.add('hidden');
            }
        });
    }
    
    document.getElementById('sort-direction-button').addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (sortColumn) {
            sortOrders(sortColumn, true, false);
            saveOrdersFiltersState();
        }
    });
    
    // Kundenauswahl im Modal
    document.addEventListener('click', (e) => {
        const row = e.target.closest('.select-customer-row');
        if (row && customerModal && !customerModal.classList.contains('hidden')) {
            const customerId = row.getAttribute('data-customer-id');
            const customerName = row.getAttribute('data-customer-display-name') || 'Alle Kunden';
            
            const customerFilter = document.getElementById('customer-filter');
            const customerFilterText = document.getElementById('customer-filter-text');
            
            if (customerFilter) {
                customerFilter.value = customerId || '';
            }
            
            if (customerFilterText) {
                customerFilterText.textContent = customerName;
            }
            
            closeCustomerModal();
            updateCustomerFilterButtonState();
            filterOrders();
            saveOrdersFiltersState();
        }
    });
    
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

    // Suchfunktion für Kunden
    function filterCustomers(searchTerm) {
        const customersTableBody = document.getElementById('customersTableBody');
        if (!customersTableBody) {
            return;
        }
        
        const rows = customersTableBody.querySelectorAll('.customer-row');
        const term = searchTerm;
        
        rows.forEach(row => {
            const customerName = row.getAttribute('data-customer-name') || '';
            if (normalizeCustomerSearchWs(term) === '' || customerSearchTextMatches(customerName, term)) {
                row.style.display = 'block';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    const customerSearchInput = document.getElementById('customerSearchInput');
    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', (e) => {
            filterCustomers(e.target.value);
        });
    }
    
    // Status Radio-Buttons Event Listener (bei Wechsel: "nur neue" von Dashboard-Karte zurücksetzen)
    document.querySelectorAll('input[name="status"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const statusFilterInput = document.getElementById('status-filter');
            if (statusFilterInput) statusFilterInput.value = ordersFilterCoercePipeline(this.value || '');
            orderCardNeuLimit = null;
            filterOrders();
            saveOrdersFiltersState();
            updateStatusFilterButtonState();
            updateOrdersNeuHint();
        });
    });
    
    // Sortierung Event Listener
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            sortOrders(column);
        });
    });
    
    // Event Listener für Firmenwechsel (aus Nav)
    window.addEventListener('companyChanged', function(e) {
        selectedCompanyId = e.detail.companyId;
        loadOrders();
    });
    
    // Gespeicherte Ansicht beim Laden anwenden
    const savedView = localStorage.getItem('ordersView') || 'table';
    if (savedView === 'card') {
        currentView = 'card';
    } else {
        currentView = 'table';
    }
    applyOrdersViewToDom();
    
    // Status-Filter aus URL-Hash (z.B. von Dashboard-Card-Link: /orders/#angekommen)
    if (initialStatusFromHash) {
        document.querySelectorAll('input[name="status"]').forEach(function(radio) {
            radio.checked = (radio.value === initialStatusFromHash);
        });
        const statusFilterInput = document.getElementById('status-filter');
        if (statusFilterInput) statusFilterInput.value = initialStatusFromHash;
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('neu')) {
            const n = parseInt(urlParams.get('neu'), 10);
            if (n > 0) orderCardNeuLimit = n;
        }
    }

    updateCustomerFilterButtonState();
    updateStatusFilterButtonState();
    updateSearchActiveState();
    saveOrdersFiltersState();
    loadOrders(function() {
        // Nur Sortierung anwenden, Richtung nicht umschalten (skipToggle = true)
        if (sortColumn) sortOrders(sortColumn, true, true);
    });

    // "Alle anzeigen" (Dashboard-Karte "nur neue" aufheben)
    const ordersNeuHintShowAll = document.getElementById('orders-neu-hint-show-all');
    if (ordersNeuHintShowAll) {
        ordersNeuHintShowAll.addEventListener('click', function(e) {
            e.preventDefault();
            orderCardNeuLimit = null;
            const url = new URL(window.location.href);
            url.searchParams.delete('neu');
            const newUrl = url.pathname + url.search + url.hash;
            window.history.replaceState({}, '', newUrl);
            filterOrders();
            updateOrdersNeuHint();
        });
    }

    const viewToggleBtn = document.getElementById('viewToggleBtn');
    if (viewToggleBtn) {
        viewToggleBtn.addEventListener('click', function() {
            const next = (currentView === 'table') ? 'card' : 'table';
            switchView(next);
        });
    }

    const orderCompanyContextAssignBtn = document.getElementById('orderCompanyContextAssignBtn');
    if (orderCompanyContextAssignBtn) {
        orderCompanyContextAssignBtn.addEventListener('click', function() {
            hideOrderCompanyContextMenu();
            openOrderCompanyAssignModal();
        });
    }
    const orderCompanyAssignCancelBtn = document.getElementById('orderCompanyAssignCancelBtn');
    if (orderCompanyAssignCancelBtn) {
        orderCompanyAssignCancelBtn.addEventListener('click', closeOrderCompanyAssignModal);
    }
    const orderCompanyAssignModalOverlay = document.getElementById('orderCompanyAssignModalOverlay');
    if (orderCompanyAssignModalOverlay) {
        orderCompanyAssignModalOverlay.addEventListener('click', closeOrderCompanyAssignModal);
    }
    const orderCompanyAssignSaveBtn = document.getElementById('orderCompanyAssignSaveBtn');
    if (orderCompanyAssignSaveBtn) {
        orderCompanyAssignSaveBtn.addEventListener('click', saveOrderCompanyAssignment);
    }
    const orderCompanySearchInput = document.getElementById('orderCompanySearchInput');
    if (orderCompanySearchInput) {
        orderCompanySearchInput.addEventListener('input', function() {
            renderOrderCompanyList(orderCompanySearchInput.value || '');
        });
    }

    document.addEventListener('click', function(e) {
        const menu = document.getElementById('orderCompanyContextMenu');
        if (!menu) return;
        if (!menu.contains(e.target)) hideOrderCompanyContextMenu();
    });
    document.addEventListener('contextmenu', function(e) {
        const menu = document.getElementById('orderCompanyContextMenu');
        if (!menu) return;
        const insideOrderCard = e.target.closest('#ordersList tr, #cardViewContainer .cursor-pointer');
        if (!insideOrderCard) hideOrderCompanyContextMenu();
    });
});

function loadOrders(onDone) {
    let url = ordersApiUrl;
    const params = new URLSearchParams();
    
    if (selectedCompanyId) {
        params.append('company_id', selectedCompanyId);
    }
    
    if (params.toString()) {
        url += '?' + params.toString();
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allOrders = data.orders;
                filterOrders();
                if (typeof onDone === 'function') onDone();
            } else {
                console.error('Fehler beim Laden der Bestellungen:', data.error);
                showError('Fehler beim Laden der Bestellungen');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            showError('Fehler beim Laden der Bestellungen');
        });
}

function filterOrders() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const customerFilter = document.getElementById('customer-filter');
    const customerFilterValue = customerFilter ? customerFilter.value : '';
    const statusFilterInput = document.getElementById('status-filter');
    const statusFilter = statusFilterInput ? ordersFilterCoercePipeline(statusFilterInput.value || '') : ORDERS_STATUS_PIPELINE;
    
    filteredOrders = allOrders.filter(order => {
        // Suchfilter (nur in Feldern laut orderSearchScope; leer = alle, _none = keine)
        if (searchTerm) {
            const scopeNone = orderSearchScope && orderSearchScope.length === 1 && orderSearchScope[0] === '_none';
            if (scopeNone) {
                return false;
            }
            const allowedKeys = ['bestellnummer', 'beschreibung', 'customer_name', 'company_name', 'device_name', 'device_standort', 'device_hersteller', 'device_modell', 'device_seriennummer', 'tracking_nummer', 'ticket_nummer'];
            const useKeys = (!orderSearchScope || orderSearchScope.length === 0) ? allowedKeys : orderSearchScope.filter(k => k !== '_none' && allowedKeys.includes(k));
            if (useKeys.length === 0) {
                return false;
            }
            const parts = useKeys.map(k => (order[k] != null && order[k] !== '') ? String(order[k]) : '').filter(Boolean);
            const searchableText = parts.join(' ').toLowerCase();
            if (!searchableText.includes(searchTerm)) {
                return false;
            }
        }
        
        // Kunde-Filter
        if (customerFilterValue) {
            const orderCustomerId = order.customer_id ? order.customer_id.toString() : '';
            if (orderCustomerId !== customerFilterValue) {
                return false;
            }
        }
        
        // Status-Filter: Pipeline = alles außer Angekommen; sonst exakter Status oder Alle
        if (statusFilter === ORDERS_STATUS_PIPELINE || statusFilter === 'Offen') {
            if (order.status === 'Angekommen') {
                return false;
            }
        } else if (statusFilter) {
            if (order.status !== statusFilter) {
                return false;
            }
        }
        
        return true;
    });
    
    // Von Dashboard-System-Card: nur die N neuesten mit diesem Status anzeigen (nach Geändert-Datum)
    const cardStatuses = ['Angekommen', 'Beim Kunden', 'Im Lager'];
    if (orderCardNeuLimit != null && statusFilter && cardStatuses.includes(statusFilter)) {
        filteredOrders = [...filteredOrders].sort((a, b) => {
            const da = a.geaendert_datum || a.erstellt_datum || '';
            const db = b.geaendert_datum || b.erstellt_datum || '';
            return db.localeCompare(da);
        }).slice(0, orderCardNeuLimit);
    }
    
    // Sortierung anwenden, Richtung beibehalten (skipToggle = true)
    if (sortColumn) {
        sortOrders(sortColumn, false, true);
    }
    
    updateOrdersNeuHint();
    
    const savedView = localStorage.getItem('ordersView') || 'table';
    currentView = savedView;
    applyOrdersViewToDom();
    
    if (currentView === 'table') {
        displayOrdersTable(filteredOrders);
    } else {
        displayOrdersCards(filteredOrders);
    }
}

function sortOrders(column, updateUI = true, skipToggle = false) {
    if (!skipToggle) {
        if (sortColumn === column) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = column;
            sortDirection = 'desc';
        }
    }
    
    filteredOrders.sort((a, b) => {
        let aValue, bValue;
        const col = sortColumn || column;
        
        switch(col) {
            case 'bestellnummer':
                aValue = (a.bestellnummer || '').toLowerCase();
                bValue = (b.bestellnummer || '').toLowerCase();
                break;
            case 'beschreibung':
                aValue = (a.beschreibung || '').toLowerCase();
                bValue = (b.beschreibung || '').toLowerCase();
                break;
            case 'status':
                aValue = (a.status || '').toLowerCase();
                bValue = (b.status || '').toLowerCase();
                break;
            case 'customer_name':
                aValue = (a.customer_name || '').toLowerCase();
                bValue = (b.customer_name || '').toLowerCase();
                break;
            case 'company_name':
                aValue = (a.company_name || '').toLowerCase();
                bValue = (b.company_name || '').toLowerCase();
                break;
            case 'device_name':
                aValue = (a.device_name || '').toLowerCase();
                bValue = (b.device_name || '').toLowerCase();
                break;
            case 'device_standort':
                aValue = (a.device_standort || '').toLowerCase();
                bValue = (b.device_standort || '').toLowerCase();
                break;
            case 'erstellt_datum':
                aValue = new Date(a.erstellt_datum || 0);
                bValue = new Date(b.erstellt_datum || 0);
                break;
            case 'geaendert_datum':
                aValue = new Date(a.geaendert_datum || 0);
                bValue = new Date(b.geaendert_datum || 0);
                break;
            case 'erstellt_von_vorname':
                aValue = ((a.erstellt_von_vorname || '') + ' ' + (a.erstellt_von_nachname || '')).trim().toLowerCase();
                bValue = ((b.erstellt_von_vorname || '') + ' ' + (b.erstellt_von_nachname || '')).trim().toLowerCase();
                break;
            default:
                return 0;
        }
        
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
        if (currentView === 'table') {
            displayOrdersTable(filteredOrders);
        } else {
            displayOrdersCards(filteredOrders);
        }
    }
}

function updateSortIcons() {
    document.querySelectorAll('[data-sort] .sort-icon').forEach(icon => {
        icon.style.display = 'none';
    });
    
    if (sortColumn) {
        const th = document.querySelector(`[data-sort="${sortColumn}"]`);
        if (th) {
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                icon.style.display = 'block';
                if (sortDirection === 'asc') {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>';
                } else {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';
                }
            }
        }
    }
    
    // Sortier-Dropdown-Text und Richtungs-Button (wie Service)
    const sortDropdownText = document.getElementById('sort-dropdown-text');
    if (sortDropdownText && sortColumn) {
        const sortLabels = { 'erstellt_datum': 'Erstellt', 'geaendert_datum': 'Geändert', 'beschreibung': 'Bestellung', 'status': 'Status', 'device_name': 'Gerät', 'device_standort': 'Gerätestandort' };
        sortDropdownText.textContent = sortLabels[sortColumn] || 'Sortieren';
    } else if (sortDropdownText) {
        sortDropdownText.textContent = 'Sortieren';
    }
    
    const sortDirectionButton = document.getElementById('sort-direction-button');
    const sortDirectionPath = document.getElementById('sort-direction-path');
    if (sortDirectionButton && sortDirectionPath) {
        if (sortColumn) {
            sortDirectionButton.disabled = false;
            sortDirectionButton.classList.remove('opacity-50', 'cursor-not-allowed');
            sortDirectionButton.classList.add('cursor-pointer');
            if (sortDirection === 'asc') {
                sortDirectionPath.setAttribute('d', 'M16 18V9M12 13l4-4 4 4');
            } else {
                sortDirectionPath.setAttribute('d', 'M16 6V15M12 11l4 4 4-4');
            }
        } else {
            sortDirectionButton.disabled = true;
            sortDirectionButton.classList.add('opacity-50', 'cursor-not-allowed');
            sortDirectionButton.classList.remove('cursor-pointer');
        }
    }
    
    document.querySelectorAll('.sort-option').forEach(option => {
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

function showError(message) {
    const tbody = document.getElementById('ordersList');
    const colspan = 4 + (showCustomer ? 1 : 0) + (showActions ? 1 : 0);
    
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-4 py-4 text-center text-red-500">${message}</td></tr>`;
    }
}

// Gespeicherte Ansicht aus localStorage laden oder Standard (Tabelle)
let currentView = localStorage.getItem('ordersView') || 'table';

function applyOrdersViewToDom() {
    const view = currentView || 'table';
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    const viewToggleBtn = document.getElementById('viewToggleBtn');
    const viewTitlesMap = { table: 'Tabellenansicht', card: 'Kartenansicht' };
    if (tableView && cardView) {
        if (view === 'table') {
            tableView.classList.remove('hidden');
            cardView.classList.add('hidden');
        } else {
            tableView.classList.add('hidden');
            cardView.classList.remove('hidden');
        }
    }
    if (viewToggleBtn) {
        viewToggleBtn.title = 'Ansicht wechseln (' + (viewTitlesMap[view] || view) + ')';
        viewToggleBtn.classList.remove('view-toggle--active');
    }
}

function switchView(view) {
    if (typeof view === 'undefined') return;
    
    currentView = view;
    localStorage.setItem('ordersView', view);
    
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    
    if (!tableView || !cardView) {
        console.error('View-Elemente nicht gefunden');
        return;
    }
    
    applyOrdersViewToDom();
    
    if (filteredOrders && filteredOrders.length >= 0) {
        if (view === 'table') {
            displayOrdersTable(filteredOrders);
        } else {
            displayOrdersCards(filteredOrders);
        }
    }
}

function getStatusProgress(status) {
    const statusOrder = ['Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager', 'Angekommen'];
    const normalized = !status ? 'Neu' : (status === 'Offen' ? 'Neu' : status);
    const currentIndex = statusOrder.indexOf(normalized);
    return currentIndex >= 0 ? ((currentIndex + 1) / statusOrder.length) * 100 : 0;
}

function displayOrdersCards(orders) {
    const cardContainer = document.getElementById('cardViewContainer');
    const baseUrl = '<?php echo BASE_URL; ?>';
    
    if (!cardContainer) return;
    
    if (orders.length === 0) {
        cardContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">Keine Bestellungen gefunden</div>';
        return;
    }
    
    cardContainer.innerHTML = orders.map(order => {
        const hasDevice = order.device_id || order.device_name;
        const deviceDetails = [];
        if (order.device_standort) deviceDetails.push('Standort: ' + order.device_standort);
        if (order.device_hersteller) deviceDetails.push('Hersteller: ' + order.device_hersteller);
        if (order.device_modell) deviceDetails.push('Modell: ' + order.device_modell);
        if (order.device_seriennummer) deviceDetails.push('S/N: ' + order.device_seriennummer);
        const deviceCardHtml = hasDevice ? `
            <div class="rounded-base border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 p-3 mt-3" onclick="event.stopPropagation()">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 bg-white dark:bg-gray-600 rounded-base">
                        <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(order.device_name || 'Gerät')}</p>
                        ${deviceDetails.length ? '<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">' + escapeHtml(deviceDetails.join(' · ')) + '</p>' : ''}
                        ${order.device_id ? `<a href="${baseUrl}devices/detail.php?id=${order.device_id}" class="text-xs text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 hover:underline mt-0.5 block" onclick="event.stopPropagation()">Zum Gerät</a>` : ''}
                    </div>
                </div>
            </div>
        ` : '';
        
        const hasCustomer = (showCustomer && (order.customer_id || order.customer_name)) || (showCompany && (order.company_id || order.company_name));
        const customerCardHtml = hasCustomer ? `
            <div class="rounded-base border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 p-3 mt-3" onclick="event.stopPropagation()">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 bg-white dark:bg-gray-600 rounded-base">
                        <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(showCustomer && order.customer_name ? order.customer_name : (showCompany && order.company_name ? order.company_name : 'Kunde / Firma'))}</p>
                        ${(showCustomer && order.customer_id) || (showCompany && order.company_id) ? `<span class="text-xs text-primaryLight-250 dark:text-primary-250">${showCustomer && order.customer_id ? `<a href="${baseUrl}customers/detail.php?id=${order.customer_id}" onclick="event.stopPropagation()" class="hover:underline">Zum Kunden</a>` : ''}${(showCustomer && order.customer_id) && (showCompany && order.company_id) ? ' · ' : ''}${showCompany && order.company_id ? `<a href="${baseUrl}companies/detail.php?id=${order.company_id}" onclick="event.stopPropagation()" class="hover:underline">Zur Firma</a>` : ''}</span>` : ''}
                    </div>
                </div>
            </div>
        ` : '';
        
        return `
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow cursor-pointer" onclick="window.location.href='<?php echo BASE_URL; ?>orders/detail.php?id=${order.id}'" oncontextmenu="showOrderCompanyContextMenu(event, ${order.id}, ${order.company_id ? order.company_id : 'null'}, '${escapeHtml(order.company_name || '')}')">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1 flex flex-wrap items-center gap-2">
                                <a href="<?php echo BASE_URL; ?>orders/detail.php?id=${order.id}" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 hover:underline">
                                    ${escapeHtml(order.bestellnummer || 'Bestellung #' + order.id)}
                                </a>
                                ${(order.garantie == 1 || order.garantie === true) ? '<span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-900 dark:bg-amber-900/35 dark:text-amber-100 border border-amber-200 dark:border-amber-800">Garantie</span>' : ''}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">${formatDate(order.erstellt_datum)}</p>
                        </div>
                        ${showActions ? `
                        <div class="flex items-center space-x-2 flex-shrink-0" onclick="event.stopPropagation()">
                            <a href="<?php echo BASE_URL; ?>orders/detail.php?id=${order.id}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="Details">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </a>
                            <a href="<?php echo BASE_URL; ?>orders/edit.php?id=${order.id}" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260" title="Bearbeiten">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        </div>
                        ` : ''}
                    </div>
                    
                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-3 line-clamp-2">
                        ${escapeHtml(((order.beschreibung || '-').replace(/^Bestellung aus (Serviceauftrag|Ticket) #\d+:\s*/i, '').trim() || '-').substring(0, 100))}${((order.beschreibung || '').replace(/^Bestellung aus (Serviceauftrag|Ticket) #\d+:\s*/i, '').trim() || '').length > 100 ? '...' : ''}
                    </p>
                    ${(function(){ const parts = []; if (order.ticket_id) parts.push('<a href="<?php echo BASE_URL; ?>tickets/view.php?id=' + order.ticket_id + '" class="inline-flex items-center gap-0.5 text-gray-400 dark:text-gray-500 opacity-60 hover:opacity-100 hover:text-primaryLight-250 dark:hover:text-primary-250" title="Referenz: Ticket #' + (order.ticket_nummer || order.ticket_id) + '" onclick="event.stopPropagation()"><svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg><span>#' + (order.ticket_nummer || order.ticket_id) + '</span></a>'); const bd = order.bestellung_durch; const bdLabel = bd === 'intern' ? 'Wir' : (bd === 'firma' ? (order.company_name || 'Firma') : (bd === 'kunde' || bd === 'kunde_firma' ? (order.customer_name || 'Kunde') : (bd === 'lagersystem' ? 'Lagersystem' : ''))); if (bdLabel) parts.push('Bestellt durch ' + escapeHtml(bdLabel)); return parts.length ? '<p class="text-xs text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1.5 flex-wrap">' + parts.join('<span class="text-gray-400">·</span>') + '</p>' : ''; })()}
                    
                    ${deviceCardHtml}
                    ${customerCardHtml}
                    
                    <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 pt-4 mt-3 border-t border-gray-200 dark:border-gray-700">
                        <div></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function displayOrdersTable(orders) {
    const tbody = document.getElementById('ordersList');
    const baseUrl = '<?php echo BASE_URL; ?>';
    const colspan = 4 + (showCustomer ? 1 : 0) + (showActions ? 1 : 0);
    
    if (!tbody) return;
    
    if (orders.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Keine Bestellungen gefunden</td></tr>`;
        return;
    }
    
    tbody.innerHTML = orders.map(order => {
        const statusBadge = getStatusBadge(order.status || 'Neu');
        
        // Bestellnummer und Beschreibung: Prefix „Bestellung aus Serviceauftrag|Ticket #XX:“ entfernen
        const bestellnummer = order.bestellnummer || '-';
        const beschreibungRaw = order.beschreibung || '-';
        const beschreibung = beschreibungRaw.replace(/^Bestellung aus (Serviceauftrag|Ticket) #\d+:\s*/i, '').trim() || '-';
        const ticketRef = order.ticket_id && (order.ticket_nummer || order.ticket_id) ? `<a href="<?php echo BASE_URL; ?>tickets/view.php?id=${order.ticket_id}" class="inline-flex items-center gap-0.5 text-gray-400 dark:text-gray-500 opacity-60 hover:opacity-100 hover:text-primaryLight-250 dark:hover:text-primary-250" title="Referenz: Ticket #${order.ticket_nummer || order.ticket_id}" onclick="event.stopPropagation()"><svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg><span>#${order.ticket_nummer || order.ticket_id}</span></a>` : '';
        const bestelltDurchLabel = (function() {
            const v = order.bestellung_durch;
            if (v === 'intern') return 'Wir';
            if (v === 'firma') return order.company_name || 'Firma';
            if (v === 'kunde' || v === 'kunde_firma') return order.customer_name || 'Kunde';
            if (v === 'lagersystem') return 'Lagersystem';
            return '';
        })();
        const bestelltDurchHtml = bestelltDurchLabel ? `<span class="text-xs text-gray-500 dark:text-gray-400"> · Bestellt durch ${escapeHtml(bestelltDurchLabel)}</span>` : '';
        const garantieTableBadge = (order.garantie == 1 || order.garantie === true) ? '<span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-amber-100 text-amber-900 dark:bg-amber-900/35 dark:text-amber-100 border border-amber-200 dark:border-amber-800 align-middle">Garantie</span>' : '';
        const bestellungCell = `
            <td class="px-4 py-3 max-w-xs">
                <div class="flex flex-col">
                    <span class="text-gray-900 dark:text-white font-medium text-base truncate block" title="${escapeHtml(beschreibung)}">${escapeHtml(beschreibung)}</span>
                    <span class="inline-flex flex-wrap items-center gap-x-1 mt-0.5">
                    <a href="<?php echo BASE_URL; ?>orders/detail.php?id=${order.id}" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 text-xs hover:underline">
                        ${escapeHtml(bestellnummer)}
                    </a>${garantieTableBadge}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 inline-flex items-center gap-1 mt-0.5">${ticketRef}${bestelltDurchHtml}</span>
                </div>
            </td>
        `;
        
        // Gerät: Hersteller + Modell nebeneinander, unten Standort
        let deviceCell = '';
        if (order.device_id || order.device_name || order.device_standort || order.device_hersteller || order.device_modell || order.device_seriennummer) {
            const herstellerModell = [order.device_hersteller, order.device_modell].filter(Boolean).join(' ');
            deviceCell = `
                <td class="px-4 py-3 max-w-xs">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate block" title="${escapeHtml(herstellerModell || '-')}">${escapeHtml(herstellerModell || '-')}</span>
                        ${order.device_standort ? `<span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate block" title="${escapeHtml(order.device_standort)}">${escapeHtml(order.device_standort)}</span>` : ''}
                    </div>
                </td>
            `;
        } else {
            deviceCell = '<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">-</td>';
        }
        
        // Kunde/Firma kombinieren: Kunde oben, Firma unten, oder nur Firma wenn kein Kunde
        let companyCustomerCell = '';
        if (showCustomer) {
            const customerName = order.customer_name || '';
            const companyName = order.company_name || '';
            
            if (customerName) {
                // Beide vorhanden: Kunde oben, Firma unten
                companyCustomerCell = `
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="text-sm text-gray-900 dark:text-white font-medium">${escapeHtml(customerName)}</span>
                            ${companyName ? `<span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${escapeHtml(companyName)}</span>` : ''}
                        </div>
                    </td>
                `;
            } else if (companyName) {
                // Nur Firma vorhanden
                companyCustomerCell = `
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        ${escapeHtml(companyName)}
                    </td>
                `;
            } else {
                companyCustomerCell = `<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">-</td>`;
            }
        }
        
        // Erstellt am und von kombinieren: Erstellt am oben, Erstellt von unten
        const erstelltDatum = formatDate(order.erstellt_datum);
        const erstelltVon = escapeHtml((order.erstellt_von_vorname || '') + ' ' + (order.erstellt_von_nachname || '')).trim() || '-';
        const erstelltCell = `
            <td class="px-4 py-3 whitespace-nowrap">
                <div class="flex flex-col">
                    <span class="text-sm text-gray-900 dark:text-white">${erstelltDatum}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${erstelltVon}</span>
                </div>
            </td>
        `;
        
        return `
            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" onclick="window.location.href='<?php echo BASE_URL; ?>orders/detail.php?id=${order.id}'" oncontextmenu="showOrderCompanyContextMenu(event, ${order.id}, ${order.company_id ? order.company_id : 'null'}, '${escapeHtml(order.company_name || '')}')">
                ${bestellungCell}
                <td class="px-4 py-3">
                    ${statusBadge}
                </td>
                ${deviceCell}
                ${companyCustomerCell}
                ${erstelltCell}
                ${showActions ? `<td class="px-4 py-3" onclick="event.stopPropagation()">
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo BASE_URL; ?>orders/detail.php?id=${order.id}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="Details anzeigen">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        <a href="<?php echo BASE_URL; ?>orders/edit.php?id=${order.id}" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260" title="Bearbeiten">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                    </div>
                </td>` : ''}
            </tr>
        `;
    }).join('');
}

function getStatusBadge(status) {
    const s = status === 'Offen' ? 'Neu' : status;
    const neuBadge = '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Neu</span>';
    const badges = {
        'Neu': neuBadge,
        'Offen': neuBadge,
        'Bestellt': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Bestellt</span>',
        'Unterwegs': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Unterwegs</span>',
        'Beim Kunden': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">Beim Kunden</span>',
        'Im Lager': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Im Lager</span>',
        'Angekommen': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Angekommen</span>'
    };
    return badges[s] || neuBadge;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showOrderCompanyContextMenu(event, orderId, companyId) {
    if (!canAssignOrderCompany) return true;
    event.preventDefault();
    event.stopPropagation();
    orderCompanyContextOrderId = Number(orderId);
    const menu = document.getElementById('orderCompanyContextMenu');
    const assignBtn = document.getElementById('orderCompanyContextAssignBtn');
    if (!menu || !assignBtn || !orderCompanyContextOrderId) return false;
    assignBtn.textContent = companyId ? 'Firma ändern' : 'Firma hinzufügen';
    menu.classList.remove('hidden');
    let left = event.clientX;
    let top = event.clientY;
    const viewportPadding = 8;
    const rect = menu.getBoundingClientRect();
    if (rect.right > window.innerWidth - viewportPadding) {
        left = Math.max(viewportPadding, left - (rect.right - window.innerWidth + viewportPadding));
    }
    if (rect.bottom > window.innerHeight - viewportPadding) {
        top = Math.max(viewportPadding, top - (rect.bottom - window.innerHeight + viewportPadding));
    }
    if (rect.left < viewportPadding) left = viewportPadding;
    if (rect.top < viewportPadding) top = viewportPadding;
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
    return false;
}

function hideOrderCompanyContextMenu() {
    const menu = document.getElementById('orderCompanyContextMenu');
    if (menu) menu.classList.add('hidden');
}

function openOrderCompanyAssignModal() {
    if (!canAssignOrderCompany || !orderCompanyContextOrderId) return;
    const modal = document.getElementById('orderCompanyAssignModal');
    const listEl = document.getElementById('orderCompanyList');
    const searchEl = document.getElementById('orderCompanySearchInput');
    const saveBtn = document.getElementById('orderCompanyAssignSaveBtn');
    if (!modal || !listEl || !searchEl || !saveBtn) return;

    orderCompanySelectedId = null;
    orderCompanySelectedName = '';
    saveBtn.disabled = true;
    searchEl.value = '';
    listEl.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500 dark:text-primary-210">Lade Firmen...</div>';
    modal.classList.remove('hidden');

    fetch(companiesApiUrl)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.companies)) {
                throw new Error(data.error || 'Firmen konnten nicht geladen werden');
            }
            orderCompanyListCache = data.companies;
            renderOrderCompanyList('');
        })
        .catch(() => {
            listEl.innerHTML = '<div class="px-4 py-3 text-sm text-red-500">Firmen konnten nicht geladen werden.</div>';
        });
}

function closeOrderCompanyAssignModal() {
    const modal = document.getElementById('orderCompanyAssignModal');
    if (modal) modal.classList.add('hidden');
}

function renderOrderCompanyList(searchTerm) {
    const listEl = document.getElementById('orderCompanyList');
    const saveBtn = document.getElementById('orderCompanyAssignSaveBtn');
    if (!listEl || !saveBtn) return;
    const term = (searchTerm || '').toLowerCase().trim();
    const entries = orderCompanyListCache.filter(c => String(c.name || '').toLowerCase().includes(term));

    if (entries.length === 0) {
        listEl.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500 dark:text-primary-210">Keine passende Firma gefunden.</div>';
        return;
    }

    listEl.innerHTML = entries.map(c => {
        const selected = Number(orderCompanySelectedId) === Number(c.id);
        return '<button type="button" class="w-full text-left px-4 py-2.5 text-sm transition-colors ' + (selected ? 'bg-primaryLight-140 dark:bg-primary-140 text-primaryLight-580 dark:text-primary-580' : 'text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140') + '" data-company-id="' + escapeHtml(String(c.id)) + '">' + escapeHtml(String(c.name || 'Ohne Namen')) + '</button>';
    }).join('');

    listEl.querySelectorAll('button[data-company-id]').forEach(btn => {
        btn.addEventListener('click', function() {
            orderCompanySelectedId = Number(btn.getAttribute('data-company-id'));
            orderCompanySelectedName = (btn.textContent || '').trim();
            saveBtn.disabled = false;
            renderOrderCompanyList(term);
        });
    });
}

function saveOrderCompanyAssignment() {
    if (!orderCompanyContextOrderId || !orderCompanySelectedId) return;
    const saveBtn = document.getElementById('orderCompanyAssignSaveBtn');
    if (saveBtn) saveBtn.disabled = true;

    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: orderCompanyContextOrderId, company_id: orderCompanySelectedId })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Fehler beim Speichern');
        }
        closeOrderCompanyAssignModal();
        if (typeof showToast === 'function') {
            showToast('Firma wurde zugewiesen: ' + (orderCompanySelectedName || 'Unbekannt'), 'success');
        }
        loadOrders(function() {
            if (sortColumn) sortOrders(sortColumn, true, true);
        });
    })
    .catch((err) => {
        if (saveBtn) saveBtn.disabled = false;
        if (typeof showToast === 'function') showToast(err.message || 'Fehler beim Speichern', 'error');
    });
}

function deleteOrder(orderId) {
    if (!confirm('Möchten Sie diese Bestellung wirklich löschen?')) {
        return;
    }
    
    fetch(ordersApiUrl + '?id=' + orderId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadOrders();
            if (typeof showToast === 'function') {
                showToast('Bestellung erfolgreich gelöscht', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Löschen der Bestellung', 'error');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
