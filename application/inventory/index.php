<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/inventory_permissions.php';
requireLogin();

$userId = $_SESSION['user_id'];
inventory_permissions_ensure_columns($pdo);
$invUser = inventory_permissions_load_user($pdo, (int)$userId);
if (!$invUser) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}
$userRole = $invUser['rolle'];

if (!inventory_user_can_view_from_row($invUser)) {
    showPermissionDeniedPage();
}

$canEditConsumables = inventory_user_can_full_edit($userRole);
$canAdjustInventoryStock = inventory_user_can_adjust_from_row($invUser);

include dirname(__DIR__) . '/assets/frontend/head.php';
$navMobileShowIntegratedFilter = true;
$navMobileCompactTitle = 'Alle Artikel';
$navMobileInventorySearchToggle = true;
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="kalender-page relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 max-lg:pt-[calc(env(safe-area-inset-top,0px)+3.5rem+1rem)] lg:pt-0 overflow-hidden max-lg:overflow-visible service-main-content app-mobile-no-root-overscroll">
  <main class="mx-4 mt-2 flex flex-col overflow-hidden min-h-0 max-lg:overflow-visible max-lg:min-h-0 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 service-main">
    <nav class="mb-4 flex-shrink-0 hidden lg:flex" aria-label="Breadcrumb">
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
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Lager</span>
          </div>
        </li>
      </ol>
    </nav>
    <div class="relative col-span-full">
      <div class="relative">
        <div class="hidden lg:block w-full">
        <div class="flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0">
          <div class="flex flex-col w-full space-y-3 lg:flex-1 lg:min-w-0 md:space-y-0 md:flex-row md:items-center md:gap-2">
            <form class="w-auto md:max-w-sm search-form-base shrink-0" id="inv-search-form">
              <label for="inv-search" class="text-sm font-medium text-gray-900 sr-only dark:text-white">Suche</label>
              <div class="relative flex items-center" id="inv-search-wrapper">
                <button type="button" id="inv-search-toggle-btn" class="search-toggle-open flex items-center justify-center gap-0 rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 focus:outline-none transition-all duration-200 shrink-0 min-w-[2.5rem] text-xs font-medium py-2 px-2 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 dark:focus:ring-primary-500/30 dark:focus:border-primary-400" title="Suche öffnen">
                  <svg aria-hidden="true" class="w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0 block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                </button>
                <div class="search-field-container overflow-hidden transition-[max-width,opacity] duration-300 ease-out" style="width: 0; opacity: 0;" data-search-container>
                  <div class="relative flex items-center search-field-inner">
                    <input type="search" id="inv-search"
                           class="block w-full p-2 pl-3 pr-12 text-sm text-gray-900 rounded-xl border border-gray-200 bg-white/80 placeholder-gray-500 hover:bg-white hover:border-gray-300 focus:ring-0 focus:outline-none focus:border-primary-400 focus:bg-white transition-all duration-200 dark:bg-primary-700/80 dark:border-primary-320 dark:text-primary-200 dark:placeholder-primary-210 dark:hover:bg-primary-760 dark:hover:border-primary-300 dark:focus:border-primary-400 dark:focus:bg-primary-760 dark:focus:ring-0 dark:focus:outline-none"
                           placeholder="Suchen...">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-1">
                      <button type="button" id="inv-search-close-btn" class="search-close-btn flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 focus:outline-none transition-colors hidden" title="Suche schließen" aria-label="Suche schließen">
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
            <div class="relative w-auto" id="inv-status-filter-container">
              <button type="button" id="inv-status-filter-button" class="inv-status-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200">
                <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.583 8.445h.01M10.86 19.71l-6.573-6.63a.993.993 0 0 1 0-1.4l7.329-7.394A.98.98 0 0 1 12.31 4l5.734.007A1.968 1.968 0 0 1 20 5.983v5.5a.992.992 0 0 1-.316.727l-7.44 7.5a.974.974 0 0 1-1.384.001Z"/>
                </svg>
                <span id="inv-status-filter-text" class="filter-btn-label whitespace-nowrap">Alle Status</span>
                <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
              <div id="inv-status-filter-menu" class="hidden absolute z-10 min-w-[12rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base shadow-card overflow-hidden" data-popper-placement="bottom">
                <div class="py-1 overflow-y-auto max-h-[20rem]" id="inv-status-filter-menu-inner">
                  <button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="" data-status-name="Alle Status">Alle Status</button>
                  <button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="leer" data-status-name="Leer">Leer</button>
                  <button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="bestand_vorhanden" data-status-name="Bestand vorhanden">Bestand vorhanden</button>
                  <button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="bestellung_angekommen" data-status-name="Bestellung angekommen">Bestellung angekommen</button>
                  <button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="muss_nachbestellen" data-status-name="Muss nachbestellt werden">Muss nachbestellt werden</button>
                  <button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="nachbestellt" data-status-name="Nachbestellt">Nachbestellt</button>
                </div>
              </div>
              <input type="hidden" id="inv-status-filter" value="">
            </div>
            <div class="flex flex-wrap items-center gap-1.5 md:gap-2 flex-1 min-w-0">
              <div class="relative w-auto" id="inv-category-filter-container">
                <button type="button" id="inv-category-filter-button" class="inv-category-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200">
                  <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.005 11.19V12l6.998 4.042L19 12v-.81M5 16.15v.81L11.997 21l6.998-4.042v-.81M12.003 3 5.005 7.042l6.998 4.042L19 7.042 12.003 3Z"/>
</svg>

                  <span id="inv-category-filter-text" class="filter-btn-label whitespace-nowrap">Alle Kategorien</span>
                  <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <div id="inv-category-filter-menu" class="hidden absolute z-10 min-w-[12rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base shadow-card overflow-hidden" data-popper-placement="bottom">
                  <div class="py-1 overflow-y-auto max-h-[20rem]" id="inv-category-filter-menu-inner">
                    <button type="button" class="inv-category-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-category-id="" data-category-name="Alle Kategorien">Alle Kategorien</button>
                  </div>
                </div>
                <input type="hidden" id="inv-category-filter" value="">
              </div>
              <div class="relative w-auto" id="inv-manufacturer-filter-container">
                <button type="button" id="inv-manufacturer-filter-button" class="inv-manufacturer-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200">
                  <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"/>
</svg>

                  <span id="inv-manufacturer-filter-text" class="filter-btn-label whitespace-nowrap">Alle Hersteller</span>
                  <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <div id="inv-manufacturer-filter-menu" class="hidden absolute z-10 min-w-[12rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base shadow-card overflow-hidden" data-popper-placement="bottom">
                  <div class="py-1 overflow-y-auto max-h-[20rem]" id="inv-manufacturer-filter-menu-inner">
                    <button type="button" class="inv-manufacturer-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-manufacturer="" data-manufacturer-name="Alle Hersteller">Alle Hersteller</button>
                  </div>
                </div>
                <input type="hidden" id="inv-manufacturer-filter" value="">
              </div>
              <div class="relative w-auto hidden" id="inv-model-filter-container">
                <button type="button" id="inv-model-filter-button" class="inv-model-filter-btn filter-btn--default flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 bg-white/80 hover:bg-white hover:border-gray-300 hover:shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-400 dark:bg-primary-700/80 dark:border-primary-320 dark:hover:bg-primary-760 dark:hover:border-primary-300 transition-all duration-200">
                  <svg class="filter-btn-icon w-4 h-4 text-gray-500 dark:text-primary-210 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M9.98189 4.50602c1.24881-.67469 2.78741-.67469 4.03621 0l3.9638 2.14148c.3634.19632.6862.44109.9612.72273l-6.9288 3.60207L5.20654 7.225c.2403-.22108.51215-.41573.81157-.5775l3.96378-2.14148ZM4.16678 8.84364C4.05757 9.18783 4 9.5493 4 9.91844v4.28296c0 1.3494.7693 2.5963 2.01811 3.2709l3.96378 2.1415c.32051.1732.66011.3019 1.00901.3862v-7.4L4.16678 8.84364ZM13.009 20c.3489-.0843.6886-.213 1.0091-.3862l3.9638-2.1415C19.2307 16.7977 20 15.5508 20 14.2014V9.91844c0-.30001-.038-.59496-.1109-.87967L13.009 12.6155V20Z"/>
                  </svg>
                  <span id="inv-model-filter-text" class="filter-btn-label whitespace-nowrap">Alle Modelle</span>
                  <svg class="filter-btn-chevron w-4 h-4 text-gray-400 dark:text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <div id="inv-model-filter-menu" class="hidden absolute z-10 min-w-[12rem] mt-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-base shadow-card overflow-hidden" data-popper-placement="bottom">
                  <div class="py-1 overflow-y-auto max-h-[20rem]" id="inv-model-filter-menu-inner">
                    <button type="button" class="inv-model-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-model="" data-model-name="Alle Modelle">Alle Modelle</button>
                  </div>
                </div>
                <input type="hidden" id="inv-model-filter" value="">
              </div>
            </div>
          </div>
          <div class="flex flex-col items-stretch justify-end flex-shrink-0 w-full pb-4 md:pb-0 md:w-auto md:flex-row md:items-center md:space-x-3">
            <?php if ($canAdjustInventoryStock): ?>
            <a href="<?php echo BASE_URL; ?>inventory/tablet.php" class="flex items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140 focus:ring-4 focus:ring-primary-500/30 focus:outline-none" title="Tablet-Ansicht Einlagern/Auslagern">
              <svg class="h-4 w-4 " fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 18h2M5.875 3h12.25c.483 0 .875.448.875 1v16c0 .552-.392 1-.875 1H5.875C5.392 21 5 20.552 5 20V4c0-.552.392-1 .875-1Z"/>
</svg>

            </a>
            <?php endif; ?>
            <?php if ($canEditConsumables): ?>
            <button type="button" id="inv-shelves-btn" class="flex items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140 focus:ring-4 focus:ring-primary-500/30 focus:outline-none" title="Regale verwalten">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v14M9 5v14M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>
</svg>

            
            </button>
            <a href="<?php echo BASE_URL; ?>inventory/create.php" class="flex items-center dark:bg-primary-420 dark:hover:bg-primary-440 justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
              <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
              </svg>
              Verbrauchsmaterial anlegen
            </a>
            <?php endif; ?>
          </div>
        </div>
        </div>

    <div id="invMobileScrollWrap" class="inv-mobile-scroll-wrap flex flex-col flex-1 min-h-0 w-full overflow-x-hidden max-lg:min-h-0">
    <!-- Mobil: Suche einblendbar (Animation), sticky unter der Nav; kein -mx-4 — vermeidet horizontales Abschneiden -->
    <div id="inv-mobile-dashboard" class="lg:hidden sticky top-0 z-[12] w-full min-w-0 pt-0">
      <div id="inv-mobile-search-anim" class="inv-mobile-search-anim w-full min-w-0" aria-hidden="true">
        <!-- geschlossen: overflow hidden (0fr); geöffnet: overflow visible — iOS zeigt sonst oft keine Tastatur bei Fokus in „beschnittenem“ Bereich -->
        <div class="inv-mobile-search-anim__measure min-h-0 w-full min-w-0 overflow-hidden px-0.5 py-0">
          <div id="inv-mobile-search-inner" class="inv-mobile-search-inner w-full min-w-0 pb-2">
            <label for="inv-mobile-search" class="sr-only">Lager durchsuchen</label>
            <div class="relative mt-0 flex w-full min-w-0 items-center rounded-2xl bg-white pl-3 pr-1 shadow-[0_1px_3px_rgba(15,23,42,0.06)] ring-1 ring-inset ring-gray-200/90 transition-[box-shadow,ring-color] focus-within:ring-2 focus-within:ring-primary-500/25 dark:bg-primary-100 dark:ring-primary-120/70 dark:shadow-[0_1px_3px_rgba(0,0,0,0.2)] dark:focus-within:ring-primary-400/30">
              <span class="pointer-events-none flex h-9 w-9 shrink-0 items-center justify-center text-gray-400 dark:text-primary-300" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              </span>
              <input type="text" id="inv-mobile-search" enterkeyhint="search" inputmode="search" autocomplete="off" class="min-w-0 w-full flex-1 basis-0 border-0 bg-transparent py-2.5 pr-3 text-[0.9375rem] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-primary-100 dark:placeholder-primary-240" placeholder="Name, EAN, Firma …">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Hinweis: automatisch per Scan angelegte Artikel (Desktop; mobil: Hinweis auf dem Dashboard) -->
    <div id="inv-scan-review-banner" class="hidden max-lg:hidden mb-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-700/50 dark:bg-amber-950/30 dark:text-amber-100 px-4 py-3 shadow-sm" role="status">
      <div class="flex gap-3 items-start">
        <span class="flex-shrink-0 mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-200/80 text-amber-900 dark:bg-amber-800/80 dark:text-amber-100" aria-hidden="true">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </span>
        <div class="min-w-0 flex-1 text-sm leading-relaxed">
          <p class="font-semibold text-amber-950 dark:text-amber-50">Automatisch angelegte Artikel prüfen</p>
          <p class="mt-1 text-amber-900/90 dark:text-amber-100/90" id="inv-scan-review-body"></p>
        </div>
      </div>
    </div>

    <!-- Tabellenansicht Desktop -->
    <div id="inv-tableView" class="hidden lg:block overflow-x-auto rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-card overflow-hidden">
      <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-primary-900 dark:text-gray-400">
          <tr>
            <th class="px-3 py-3 font-semibold cursor-default hover:bg-gray-100 dark:hover:bg-primary-960 rounded-tl-xl">Artikel</th>
            <th class="px-3 py-3 font-semibold cursor-default hover:bg-gray-100 dark:hover:bg-primary-960">EAN</th>
            <th class="px-3 py-3 font-semibold cursor-default hover:bg-gray-100 dark:hover:bg-primary-960">Kategorien</th>
            <th class="px-3 py-3 font-semibold cursor-default hover:bg-gray-100 dark:hover:bg-primary-960">Firma</th>
            <th class="px-3 py-3 font-semibold cursor-default hover:bg-gray-100 dark:hover:bg-primary-960">Gerätemodelle</th>
            <th class="px-3 py-3 font-semibold cursor-default hover:bg-gray-100 dark:hover:bg-primary-960">Lager</th>
            <th class="px-3 py-3 font-semibold cursor-default hover:bg-gray-100 dark:hover:bg-primary-960 rounded-tr-xl text-center w-28">Aktion</th>
          </tr>
        </thead>
        <tbody id="consumablesList"></tbody>
      </table>
    </div>

    <!-- Kartenansicht Mobil (Handy) -->
    <div id="inv-mobileCards" class="lg:hidden w-full pb-[calc(5.5rem+env(safe-area-inset-bottom,0px))]" aria-live="polite">
      <div id="consumablesMobileList" class="flex flex-col gap-3"></div>
    </div>
    </div>
      </div>
    </div>
<style>
/* Mobile Filter Lager: wie Tickets – Panel unter Top-Nav */
@media (max-width: 1023px) {
  #mobileFilterSheet[aria-hidden="true"] {
    visibility: hidden !important;
    pointer-events: none !important;
  }
  #mobileFilterSheet[aria-hidden="false"] {
    visibility: visible !important;
  }
  /*
   * Scroll-Chaining: Vertikaler Wisch (z. B. Panel schließen) darf nicht #main-content
   * unter dem Overlay scrollen — gleiches Muster wie Aufgaben (Todos).
   */
  #mobileFilterSheet[aria-hidden="false"] #mobileFilterSheetBackdrop {
    touch-action: none;
  }
  #mobileFilterSheet[aria-hidden="false"] #mobileFilterSheetHandle {
    touch-action: none;
  }
  #mobileFilterSheetScroll {
    overscroll-behavior-y: contain;
  }
  #mobileFilterSheetPanel {
    max-height: 0;
    transition: max-height 0.32s ease-out;
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
  #mobileFilterSheetPanel.mobile-filter-sheet-open {
    max-height: min(85vh, 32rem);
    overscroll-behavior-y: contain;
  }
  #navMobileFilterToggleBtn[aria-expanded="true"] .nav-mobile-filter-chevron {
    transform: rotate(180deg);
  }
  .dark #mobileFilterSheetPanel {
    background-color: rgb(5 5 5 / 0.48) !important;
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.26),
      inset 0 3px 16px -3px rgb(255 255 255 / 0.1),
      inset 0 -1px 0 0 rgb(0 0 0 / 0.55),
      0 4px 30px rgb(0 0 0 / 0.52),
      0 18px 44px rgb(0 0 0 / 0.35);
  }
  #mobileFilterSheetScroll .mobile-filter-sheet-row {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }
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
  /*
   * Hintergrund nicht mitscrollen — gleiche Spezifität wie nav.php (#main-content overflow-y: auto !important),
   * sonst gewinnt die Nav-Regel und der Scroll-Lock greift nicht.
   */
  body.app-mobile-bottom-nav:not(.app-mobile-dashboard-shell):not(.service-mobile-fullscreen).inv-mobile-filter-sheet-open #main-content {
    overflow-y: hidden !important;
    overscroll-behavior: none;
  }
}
</style>
<div id="mobileFilterSheet" class="lg:hidden fixed inset-0 z-[68] pointer-events-none" aria-hidden="true">
  <div id="mobileFilterSheetBackdrop" class="fixed left-0 right-0 bottom-0 z-[68] bg-black/[0.05] opacity-0 transition-opacity duration-300 pointer-events-auto cursor-pointer dark:bg-black/22 dark:backdrop-blur-[3px]" style="top: calc(env(safe-area-inset-top, 0px) + 3.5rem); pointer-events: none;"></div>
  <div id="mobileFilterSheetPanel" class="fixed inset-x-0 z-[69] flex w-full flex-col min-h-0 overflow-hidden rounded-b-[1.75rem] border border-t-0 border-gray-200 bg-white/88 backdrop-blur-2xl backdrop-saturate-200 dark:border-0 dark:bg-transparent pointer-events-auto" style="top: calc(env(safe-area-inset-top, 0px) + 3.5rem);" role="dialog" aria-modal="true" aria-label="Lagerfilter">
    <div id="mobileFilterSheetScroll" class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden space-y-5 px-4 pb-2 pt-4 custom-scrollbar sm:px-5">
      <div class="mobile-filter-sheet-row">
        <label for="inv-mobile-status-select" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Lagerstatus</label>
        <select id="inv-mobile-status-select" class="mobile-filter-sheet-field">
          <option value="">Alle Status</option>
          <option value="leer">Leer</option>
          <option value="bestand_vorhanden">Bestand vorhanden</option>
          <option value="bestellung_angekommen">Bestellung angekommen</option>
          <option value="muss_nachbestellen">Muss nachbestellt werden</option>
          <option value="nachbestellt">Nachbestellt</option>
        </select>
      </div>
      <div class="mobile-filter-sheet-row">
        <label for="inv-mobile-category-select" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Kategorie</label>
        <select id="inv-mobile-category-select" class="mobile-filter-sheet-field">
          <option value="">Alle Kategorien</option>
        </select>
      </div>
      <div class="mobile-filter-sheet-row">
        <label for="inv-mobile-manufacturer-select" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Hersteller</label>
        <select id="inv-mobile-manufacturer-select" class="mobile-filter-sheet-field">
          <option value="">Alle Hersteller</option>
        </select>
      </div>
      <div class="mobile-filter-sheet-row hidden" id="inv-mobile-model-row">
        <label for="inv-mobile-model-select" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Modell</label>
        <select id="inv-mobile-model-select" class="mobile-filter-sheet-field">
          <option value="">Alle Modelle</option>
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

<!-- Context-Menü für Lager-Tabelle -->
<div id="inv-context-menu" class="hidden fixed z-[9999] bg-white dark:bg-primary-800 border border-gray-200 dark:border-primary-600 rounded-lg shadow-xl py-1 min-w-[200px]">
  <button type="button" id="inv-context-open-new-tab" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary-700 flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
    </svg>
    Im neuen Tab öffnen
  </button>
  <div class="border-t border-gray-200 dark:border-primary-600 my-1"></div>
  <div class="inv-context-stock-block">
    <button type="button" id="inv-context-mehrere-einlagern" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary-700 flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Einlagern
    </button>
    <div id="inv-context-qty-slot-einlagern" class="inv-context-qty-slot">
      <div id="inv-context-quantity-row" class="hidden px-4 py-2 flex items-center gap-2 flex-wrap" onclick="event.stopPropagation()">
        <label for="inv-context-quantity" class="text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">Anzahl</label>
        <input type="number" id="inv-context-quantity" min="1" value="1" class="w-14 px-2 py-1.5 text-sm text-center border border-gray-300 dark:border-primary-600 rounded-lg bg-white dark:bg-primary-800 text-gray-900 dark:text-gray-100 focus:outline-none">
        <button type="button" id="inv-context-quantity-ok" class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 dark:bg-primary-420 dark:hover:bg-primary-440 focus:outline-none">
          <svg id="inv-context-qty-ok-icon-einlagern" class="w-3.5 h-3.5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          <svg id="inv-context-qty-ok-icon-auslagern" class="w-3.5 h-3.5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
          <span id="inv-context-quantity-ok-text">Übernehmen</span>
        </button>
      </div>
    </div>
  </div>
  <div class="inv-context-stock-block">
    <button type="button" id="inv-context-mehrere-auslagern" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary-700 flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
      Auslagern
    </button>
    <div id="inv-context-qty-slot-auslagern" class="inv-context-qty-slot"></div>
  </div>
  <div class="border-t border-gray-200 dark:border-primary-600 my-1"></div>
  <button type="button" id="inv-context-nachbestellen" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary-700 flex items-center gap-2">
    <svg class="w-4 h-4 shrink-0 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10V6a3 3 0 0 1 3-3v0a3 3 0 0 1 3 3v4m3-2 .917 11.923A1 1 0 0 1 17.92 21H6.08a1 1 0 0 1-.997-1.077L6 8h12Z"/>
    </svg>
    Nachbestellen
  </button>
  
  <?php if ($canEditConsumables): ?>
  <div class="border-t border-gray-200 dark:border-primary-600 my-1"></div>
  <button type="button" id="inv-context-bearbeiten" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary-700 flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
    Bearbeiten
  </button>
  <button type="button" id="inv-context-loeschen" class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-primary-700 flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14m-9 3v8m4-8v8M10 3h4a1 1 0 0 1 1 1v3H9V4a1 1 0 0 1 1-1ZM6 7h12v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7Z"/>
</svg>

    Artikel löschen
  </button>
  <?php endif; ?>
</div>

<style>
/* Context-Menü für Lager-Tabelle */
#inv-context-menu {
    pointer-events: auto;
    position: fixed;
    display: block;
}

#inv-context-menu.hidden {
    display: none !important;
}

#inv-context-quantity-row.hidden {
    display: none !important;
}

#inv-context-menu .inv-context-item-hidden {
    display: none !important;
}

#inv-context-menu .inv-context-item-disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

#inv-context-menu button {
    transition: background-color 0.15s ease;
    cursor: pointer;
}

#inv-context-menu button:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.dark #inv-context-menu button:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

#inv-context-menu button:active {
    background-color: rgba(0, 0, 0, 0.1);
}

.dark #inv-context-menu button:active {
    background-color: rgba(255, 255, 255, 0.15);
}

/* Lager-Tabelle: keine vertikalen Linien zwischen Spalten */
#inv-tableView table td,
#inv-tableView table th {
    border-left: none !important;
    border-right: none !important;
}

/* Lager Mobil: Karten – Tap + Wisch (wie Aufgaben) */
#inv-mobileCards .inv-mobile-item {
    isolation: isolate;
    -webkit-tap-highlight-color: transparent;
}
#inv-mobileCards .inv-swipe-track {
    -webkit-tap-highlight-color: transparent;
    touch-action: pan-y;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
}
#consumablesMobileList .inv-swipe-track {
    border-radius: inherit;
    overflow: hidden;
}
#consumablesMobileList .inv-swipe-actions-layer {
    border-radius: inherit;
    overflow: hidden;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
}
.inv-mobile-item--swipe-revealed .inv-swipe-actions-layer {
    opacity: 1;
    pointer-events: auto;
}
@media (max-width: 1023px) {
    #invMobileScrollWrap {
        overflow-x: hidden;
        overscroll-behavior-x: none;
    }
}
/* Mobil-Lagersuche: Höhen-Animation (0fr/1fr), kein Layout-Sprung; Inhalt nicht seitlich abschneiden */
.inv-mobile-search-anim {
    display: grid;
    grid-template-rows: 0fr;
    width: 100%;
    min-width: 0;
    transition: grid-template-rows 0.38s cubic-bezier(0.4, 0, 0.2, 1);
}
#inv-mobile-dashboard.inv-mobile-search-panel-open .inv-mobile-search-anim {
    grid-template-rows: 1fr;
}
/* Kein opacity/transform auf dem Feld-Container: sonst blockiert iOS Fokus/Tastatur (User-Gesture + „sichtbares“ Input) */
#inv-mobile-dashboard:not(.inv-mobile-search-panel-open) .inv-mobile-search-anim {
    pointer-events: none;
}
#inv-mobile-dashboard.inv-mobile-search-panel-open .inv-mobile-search-anim__measure {
    overflow: visible;
}
@media (prefers-reduced-motion: reduce) {
    .inv-mobile-search-anim {
        transition-duration: 0.01ms;
    }
}

/* ===== Suche & Filter 1:1 wie Service index ===== */
#inv-search-wrapper input#inv-search::-webkit-search-cancel-button,
#inv-search-wrapper input#inv-search::-webkit-search-decoration {
    -webkit-appearance: none;
    appearance: none;
}
#inv-search-wrapper input#inv-search[type="search"]::-ms-clear { display: none; }
#inv-search-wrapper input#inv-search:focus,
#inv-search-wrapper input#inv-search:focus-visible { outline: none; box-shadow: none; }
html:not(.dark) #inv-search-wrapper input#inv-search:focus,
html:not(.dark) #inv-search-wrapper input#inv-search:focus-visible,
html:not(.dark) #inv-search-wrapper.search-active input {
    border-color: #3b82f6;
    background-color: rgba(59, 130, 246, 0.12);
    color: #1e293b;
    font-weight: 700;
}
html:not(.dark) #inv-search-wrapper input#inv-search::placeholder { color: #6b7280; }
html:not(.dark) #inv-search-wrapper input#inv-search:focus::placeholder,
html:not(.dark) #inv-search-wrapper.search-active input::placeholder { color: #475569; opacity: 0.9; }
.dark #inv-search-wrapper input#inv-search:focus,
.dark #inv-search-wrapper input#inv-search:focus-visible,
.dark #inv-search-wrapper.search-active input {
    border-color: #3b82f6;
    background-color: #10204a;
    color: #e5e7eb;
    font-weight: 700;
}
.dark #inv-search-wrapper input#inv-search:focus::placeholder,
.dark #inv-search-wrapper.search-active input::placeholder { color: rgba(229, 231, 235, 0.8); }
#inv-search-form { transition: flex 0.3s ease-in-out, max-width 0.3s ease-in-out, margin-right 0.3s ease-in-out, width 0.3s ease-in-out; flex: 0 0 auto; margin-right: 0; width: auto; }
#inv-search-form.search-expanded { flex: 1 1 auto; max-width: 100%; }
@media (min-width: 768px) { #inv-search-form.search-expanded { max-width: min(50%, 22rem); margin-right: 0.5rem; } }
@media (min-width: 1280px) { #inv-search-form.search-expanded { max-width: min(40%, 20rem); } }
#inv-search-wrapper { display: flex; align-items: center; width: auto; position: relative; }
#inv-search-toggle-btn.search-toggle-open {
    padding-left: 0.5rem; padding-right: 0.5rem; min-width: 2.5rem; box-sizing: border-box;
    transition: opacity 0.16s ease-out; z-index: 1;
}
#inv-search-wrapper input#inv-search,
#inv-search-toggle-btn.search-toggle-open { border-color: #e5e7eb; }
#inv-search-wrapper input#inv-search:hover,
#inv-search-toggle-btn.search-toggle-open:hover { border-color: #d1d5db; }
#inv-search-wrapper input#inv-search:focus,
#inv-search-toggle-btn.search-toggle-open:focus { border-color: rgb(59 130 246); }
#inv-search-wrapper.search-expanded .search-toggle-open {
    position: absolute; left: 0; top: 50%; transform: translateY(-50%);
    opacity: 0; pointer-events: none;
}
#inv-search-wrapper .search-field-container {
    flex: 1; min-width: 0;
    transition: max-width 0.28s ease-out, opacity 0.28s ease-out, width 0.26s ease-in;
    max-width: 0; width: 0; opacity: 0;
}
#inv-search-wrapper.search-closing .search-field-container {
    transition: width 0.3s ease-in, opacity 0.22s ease-in;
    max-width: none; opacity: 0;
}
#inv-search-wrapper.search-expanded .search-field-container {
    max-width: 100%; width: auto; opacity: 1 !important; margin-left: 0;
}
#inv-search-wrapper.search-expanded .search-close-btn { display: flex !important; }
.dark #inv-search-toggle-btn.search-toggle-open,
.dark #inv-category-filter-button.filter-btn--default {
    background-color: rgb(16 16 17) !important;
    border-color: rgb(27 27 28) !important;
}
.dark #inv-search-toggle-btn.search-toggle-open:hover,
.dark #inv-category-filter-button.filter-btn--default:hover {
    background-color: rgb(16 16 17) !important;
    border-color: rgb(75 85 99) !important;
}
.inv-category-filter-btn .filter-btn-label,
.inv-category-filter-btn .filter-btn-chevron { transition: opacity 0.18s ease-out, max-width 0.22s ease-out; overflow: hidden; display: inline-block; max-width: 16rem; white-space: nowrap; }
.inv-category-filter-btn .filter-btn-chevron { max-width: 1.5rem; }
.inv-category-filter-btn.filter-btn--default .filter-btn-label,
.inv-category-filter-btn.filter-btn--default .filter-btn-chevron { opacity: 0; max-width: 0; min-width: 0; padding-left: 0; padding-right: 0; margin: 0; visibility: hidden; }
.inv-category-filter-btn { transition: padding-left 0.2s ease-out, padding-right 0.2s ease-out, min-width 0.2s ease-out; }
.inv-category-filter-btn.filter-btn--default { padding-left: 0.5rem; padding-right: 0.5rem; min-width: 2.5rem; justify-content: center; align-items: center; gap: 0; }
.inv-category-filter-btn .filter-btn-icon { display: block; }
html:not(.dark) #inv-category-filter-button.inv-category-filter-btn--active {
    background-color: rgba(59, 130, 246, 0.12);
    border-color: #3b82f6;
    color: #1e293b;
    font-weight: 700;
    box-shadow: none;
}
html:not(.dark) #inv-category-filter-button.inv-category-filter-btn--active .filter-btn-icon,
html:not(.dark) #inv-category-filter-button.inv-category-filter-btn--active .filter-btn-chevron { color: #1e293b; }
.dark #inv-category-filter-button.inv-category-filter-btn--active {
    background-color: #10204a;
    border-color: #3b82f6;
    color: #e5e7eb;
    font-weight: 700;
    box-shadow: none;
}
.dark #inv-category-filter-button.inv-category-filter-btn--active .filter-btn-icon,
.dark #inv-category-filter-button.inv-category-filter-btn--active .filter-btn-chevron { color: #e5e7eb; }
#inv-category-filter-container { flex-shrink: 0; }

.inv-manufacturer-filter-btn .filter-btn-label,
.inv-manufacturer-filter-btn .filter-btn-chevron,
.inv-model-filter-btn .filter-btn-label,
.inv-model-filter-btn .filter-btn-chevron,
.inv-status-filter-btn .filter-btn-label,
.inv-status-filter-btn .filter-btn-chevron { transition: opacity 0.18s ease-out, max-width 0.22s ease-out; overflow: hidden; display: inline-block; max-width: 16rem; white-space: nowrap; }
.inv-manufacturer-filter-btn .filter-btn-chevron,
.inv-model-filter-btn .filter-btn-chevron,
.inv-status-filter-btn .filter-btn-chevron { max-width: 1.5rem; }
.inv-manufacturer-filter-btn.filter-btn--default .filter-btn-label,
.inv-manufacturer-filter-btn.filter-btn--default .filter-btn-chevron,
.inv-model-filter-btn.filter-btn--default .filter-btn-label,
.inv-model-filter-btn.filter-btn--default .filter-btn-chevron,
.inv-status-filter-btn.filter-btn--default .filter-btn-label,
.inv-status-filter-btn.filter-btn--default .filter-btn-chevron { opacity: 0; max-width: 0; min-width: 0; padding-left: 0; padding-right: 0; margin: 0; visibility: hidden; }
.inv-manufacturer-filter-btn,
.inv-model-filter-btn,
.inv-status-filter-btn { transition: padding-left 0.2s ease-out, padding-right 0.2s ease-out, min-width 0.2s ease-out; }
.inv-manufacturer-filter-btn.filter-btn--default,
.inv-model-filter-btn.filter-btn--default,
.inv-status-filter-btn.filter-btn--default { padding-left: 0.5rem; padding-right: 0.5rem; min-width: 2.5rem; justify-content: center; align-items: center; gap: 0; }
.inv-manufacturer-filter-btn .filter-btn-icon,
.inv-model-filter-btn .filter-btn-icon,
.inv-status-filter-btn .filter-btn-icon { display: block; }
html:not(.dark) #inv-manufacturer-filter-button.inv-manufacturer-filter-btn--active,
html:not(.dark) #inv-model-filter-button.inv-model-filter-btn--active,
html:not(.dark) #inv-status-filter-button.inv-status-filter-btn--active {
    background-color: rgba(59, 130, 246, 0.12);
    border-color: #3b82f6;
    color: #1e293b;
    font-weight: 700;
    box-shadow: none;
}
html:not(.dark) #inv-manufacturer-filter-button.inv-manufacturer-filter-btn--active .filter-btn-icon,
html:not(.dark) #inv-manufacturer-filter-button.inv-manufacturer-filter-btn--active .filter-btn-chevron,
html:not(.dark) #inv-model-filter-button.inv-model-filter-btn--active .filter-btn-icon,
html:not(.dark) #inv-model-filter-button.inv-model-filter-btn--active .filter-btn-chevron,
html:not(.dark) #inv-status-filter-button.inv-status-filter-btn--active .filter-btn-icon,
html:not(.dark) #inv-status-filter-button.inv-status-filter-btn--active .filter-btn-chevron { color: #1e293b; }
.dark #inv-manufacturer-filter-button.inv-manufacturer-filter-btn--active,
.dark #inv-model-filter-button.inv-model-filter-btn--active,
.dark #inv-status-filter-button.inv-status-filter-btn--active {
    background-color: #10204a;
    border-color: #3b82f6;
    color: #e5e7eb;
    font-weight: 700;
    box-shadow: none;
}
.dark #inv-manufacturer-filter-button.inv-manufacturer-filter-btn--active .filter-btn-icon,
.dark #inv-manufacturer-filter-button.inv-manufacturer-filter-btn--active .filter-btn-chevron,
.dark #inv-model-filter-button.inv-model-filter-btn--active .filter-btn-icon,
.dark #inv-model-filter-button.inv-model-filter-btn--active .filter-btn-chevron,
.dark #inv-status-filter-button.inv-status-filter-btn--active .filter-btn-icon,
.dark #inv-status-filter-button.inv-status-filter-btn--active .filter-btn-chevron { color: #e5e7eb; }
#inv-manufacturer-filter-container,
#inv-model-filter-container,
#inv-status-filter-container { flex-shrink: 0; }

/* Hersteller/Modell in Original-Schreibweise */
#consumableModal .consumable-hersteller,
#consumableModal .consumable-modell,
#consumableModal .inv-dm-suggestions,
#consumableModal .inv-dm-suggestion-item {
    text-transform: none !important;
}
</style>
<!-- Modal Verbrauchsmaterial anlegen/bearbeiten -->
<div id="consumableModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/70 backdrop-blur-sm transition-opacity" id="consumableModalOverlay"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-2xl bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 relative z-10 overflow-hidden" style="text-transform: none;">
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-5 border-b border-gray-200 dark:border-gray-600">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-600 dark:text-primary-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m0 0l8-4 8 4m0-6v12l-8 4m8-4l8-4m-8 4l-8-4" /></svg>
                    </div>
                    <div>
                        <h3 id="consumableModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Verbrauchsmaterial anlegen</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Stammdaten, Bestand und Gerätemodelle</p>
                    </div>
                </div>
            </div>
            <form id="consumableForm" onsubmit="saveConsumable(event)" class="p-6">
                <input type="hidden" id="consumableId" value="">
                <div class="space-y-6">
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 rounded bg-primary-500"></span>
                            Stammdaten
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label for="consumableBezeichnung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Bezeichnung *</label>
                                <input type="text" id="consumableBezeichnung" required class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors" placeholder="z.B. Toner schwarz">
                            </div>
                            <div>
                                <label for="consumableArtikelnummer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Artikelnummer</label>
                                <input type="text" id="consumableArtikelnummer" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors" placeholder="z.B. abc123">
                            </div>
                            <div>
                                <label for="consumableEan" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">EAN-Nummer</label>
                                <input type="text" id="consumableEan" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors" placeholder="z.B. 4006381333931">
                            </div>
                            <div>
                                <label for="consumableBeschreibung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Beschreibung</label>
                                <input type="text" id="consumableBeschreibung" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors" placeholder="Optional">
                            </div>
                            <div class="sm:col-span-2">
                                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Firmen</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Mehrfachauswahl möglich. Ohne Auswahl ist der Artikel keiner Firma zugeordnet.</p>
                                <div id="consumableCompaniesContainer" class="flex flex-wrap gap-x-4 gap-y-2"></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 rounded bg-primary-500"></span>
                            Kategorien
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Mehrfachauswahl möglich. Optional können Sie neue Kategorien anlegen.</p>
                        <div id="consumableCategoriesContainer" class="flex flex-wrap gap-x-4 gap-y-2 mb-3"></div>
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="text" id="newCategoryName" class="flex-1 min-w-[140px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Neue Kategorie">
                            <button type="button" onclick="addCategory()" class="px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 border border-primary-300 dark:border-primary-600 rounded-xl hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors">Hinzufügen</button>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 rounded bg-primary-500"></span>
                            Bestand
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="consumableLagerbestand" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Auf Lager</label>
                                <input type="number" id="consumableLagerbestand" min="0" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors" placeholder="0">
                            </div>
                            <div>
                                <label for="consumableMindestbestand" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Mindestbestand</label>
                                <input type="number" id="consumableMindestbestand" min="0" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors" placeholder="Optional">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="consumableAutoNachbestellen" class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Automatisch nachbestellen (bei Unterschreitung vom Mindestbestand)</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                            <div>
                                <label for="consumableShelf" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Regal</label>
                                <select id="consumableShelf" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors">
                                    <option value="">— Kein Regal —</option>
                                </select>
                            </div>
                            <div>
                                <label for="consumableSpalte" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Spalte</label>
                                <input type="number" id="consumableSpalte" min="1" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors" placeholder="z.B. 1">
                            </div>
                            <div>
                                <label for="consumableFach" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Fach</label>
                                <input type="number" id="consumableFach" min="1" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors" placeholder="z.B. 3">
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                            <span class="w-1 h-4 rounded bg-primary-500"></span>
                            Gerätemodelle
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Alle Geräte mit Hersteller + Modell erhalten dieses Material in der Geräte-Detailansicht. Optional.</p>
                        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 mb-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-600">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300 shrink-0">Gerätemodell-Vorlagen</span>
                            <select id="invDmPresetSelect" title="Gespeicherte Liste wählen" class="flex-1 min-w-[10rem] px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></select>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" id="invDmPresetApplyBtn" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-primary-300 dark:border-primary-500 text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/40">Übernehmen</button>
                                <button type="button" id="invDmPresetSaveBtn" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">Als Vorlage speichern</button>
                                <button type="button" id="invDmPresetDeleteBtn" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20">Löschen</button>
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-3 -mt-1">Nur in diesem Browser (z.&nbsp;B. eine gemeinsame Druckerliste).</p>
                        <div id="deviceModelsContainer" class="space-y-2"></div>
                        <button type="button" onclick="addDeviceModelRow()" class="mt-3 inline-flex items-center text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Gerätemodell hinzufügen
                        </button>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200 dark:border-gray-600">
                    <button type="button" onclick="closeConsumableModal()" class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        Abbrechen
                    </button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-600 rounded-xl hover:bg-primary-700 dark:hover:bg-primary-500 shadow-sm focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Regale verwalten -->
<div id="shelvesModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/70 backdrop-blur-sm transition-opacity" id="shelvesModalOverlay"></div>
    <div class="fixed inset-0 flex items-center justify-center min-h-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 relative z-10 overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-5 border-b border-gray-200 dark:border-gray-600">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-600 dark:text-primary-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Regale verwalten</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Regale für Lagerorte (z.B. Regal A, Spalte 1, Fach 3)</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Vorhandene Regale</span>
                    <button type="button" id="shelvesAddBtn" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-primary-600 dark:text-primary-400 border border-primary-300 dark:border-primary-600 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/30">+ Regal anlegen</button>
                </div>
                <ul id="shelvesList" class="space-y-2 max-h-64 overflow-y-auto">
                    <li class="text-sm text-gray-500 dark:text-gray-400 py-2">Lade Regale…</li>
                </ul>
                <div id="shelfFormContainer" class="hidden mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <input type="hidden" id="shelfEditId" value="">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label for="shelfName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Regal-Name *</label>
                            <input type="text" id="shelfName" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm" placeholder="z.B. A oder Regal 1">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="shelfBeschreibung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Beschreibung</label>
                            <input type="text" id="shelfBeschreibung" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm" placeholder="Optional">
                        </div>
                        <div>
                            <label for="shelfSpaltenAnzahl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Spalten (für 3D-Ansicht)</label>
                            <input type="number" id="shelfSpaltenAnzahl" min="1" max="20" value="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                        </div>
                        <div>
                            <label for="shelfFaecherAnzahl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fächer (für 3D-Ansicht)</label>
                            <input type="number" id="shelfFaecherAnzahl" min="1" max="20" value="6" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                        </div>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <button type="button" id="shelfFormSave" class="px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">Speichern</button>
                        <button type="button" id="shelfFormCancel" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Abbrechen</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/inventory-device-model-presets.js"></script>
<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/inventory-device-model-auto-row.js"></script>
<script>
const consumablesApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'inventory/api/consumables.php';
const shelvesApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'inventory/api/shelves.php';
const devicesApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'devices/api/devices.php';
const companiesApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'companies/api/companies.php';
const canEditConsumables = <?php echo $canEditConsumables ? 'true' : 'false'; ?>;
const canAdjustInventoryStock = <?php echo $canAdjustInventoryStock ? 'true' : 'false'; ?>;

// Vorschlagslisten für Hersteller/Modell (wie bei Gerät anlegen)
let invManufacturers = [];
let invModels = [];
// Kategorien für Mehrfachauswahl
let invCategories = [];
// Firmen für Zuordnung (id -> { id, name })
let invCompanies = [];

function loadInvCategories() {
    return fetch(consumablesApiUrl + '?action=get_categories')
        .then(r => r.json())
        .then(d => {
            if (d.success) invCategories = d.categories || [];
            return invCategories;
        })
        .catch(() => { invCategories = []; return []; });
}

function loadInvCompanies() {
    return fetch(companiesApiUrl)
        .then(r => r.json())
        .then(d => {
            if (d.success && Array.isArray(d.companies)) invCompanies = d.companies; else invCompanies = [];
            var selected = typeof getCompanyIdsFromForm === 'function' ? getCompanyIdsFromForm() : [];
            renderCompanyCheckboxes(selected);
            return invCompanies;
        })
        .catch(() => { invCompanies = []; return []; });
}

function renderCompanyCheckboxes(selectedIds) {
    const container = document.getElementById('consumableCompaniesContainer');
    if (!container) return;
    const ids = (selectedIds || []).map(function(x) { return Number(x); });
    container.innerHTML = (invCompanies || []).map(function(co) {
        const id = Number(co.id);
        const checked = ids.indexOf(id) >= 0 ? ' checked' : '';
        return '<label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" class="consumable-company-cb rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" value="' + escapeHtml(String(co.id)) + '"' + checked + '><span class="text-sm text-gray-700 dark:text-gray-300">' + escapeHtml(co.name || '') + '</span></label>';
    }).join('');
}

function getCompanyIdsFromForm() {
    const checkboxes = document.querySelectorAll('#consumableCompaniesContainer .consumable-company-cb:checked');
    return Array.from(checkboxes).map(function(cb) { return parseInt(cb.value, 10); }).filter(function(x) { return !isNaN(x) && x > 0; });
}

function renderCategoryCheckboxes(selectedIds) {
    const container = document.getElementById('consumableCategoriesContainer');
    if (!container) return;
    const ids = selectedIds || [];
    container.innerHTML = invCategories.map(function(cat) {
        const checked = ids.indexOf(Number(cat.id)) >= 0 ? ' checked' : '';
        return '<label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" class="consumable-category-cb rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" value="' + cat.id + '"' + checked + '><span class="text-sm text-gray-700 dark:text-gray-300">' + escapeHtml(cat.name) + '</span></label>';
    }).join('');
}

function getCategoryIdsFromForm() {
    const checkboxes = document.querySelectorAll('#consumableCategoriesContainer .consumable-category-cb:checked');
    return Array.from(checkboxes).map(function(cb) { return parseInt(cb.value, 10); });
}

function addCategory() {
    const input = document.getElementById('newCategoryName');
    const name = (input && input.value || '').trim();
    if (!name) {
        if (typeof showToast === 'function') showToast('Bitte Kategoriename eingeben.', 'error');
        return;
    }
    fetch(consumablesApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_category', name: name })
    })
        .then(r => r.json())
        .then(function(d) {
            if (d.success) {
                invCategories.push({ id: d.id, name: d.name });
                renderCategoryCheckboxes(getCategoryIdsFromForm());
                if (input) input.value = '';
                if (typeof showToast === 'function') showToast('Kategorie angelegt.', 'success');
            } else {
                if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
            }
        })
        .catch(function() { if (typeof showToast === 'function') showToast('Fehler beim Anlegen.', 'error'); });
}

async function loadInvManufacturers() {
    try {
        const r = await fetch(devicesApiUrl + '?action=get_manufacturers');
        const d = await r.json();
        if (d.success) invManufacturers = d.manufacturers || [];
    } catch (e) { console.error('Hersteller laden:', e); }
}

async function loadInvModels(manufacturer) {
    try {
        const url = manufacturer
            ? devicesApiUrl + '?action=get_models&manufacturer=' + encodeURIComponent(manufacturer)
            : devicesApiUrl + '?action=get_models';
        const r = await fetch(url);
        const d = await r.json();
        if (d.success) invModels = d.models || [];
    } catch (e) { console.error('Modelle laden:', e); }
}

let invConsumablesList = [];
let invConsumablesLoadedOnce = false;
let invLoadingSkeletonTimer = null;
const invLoadingSkeletonDelayMs = 300;

const INVENTORY_FILTER_STORAGE_KEY = 'inventory_filters_state';

function fillInvMobileCategorySelect() {
    var sel = document.getElementById('inv-mobile-category-select');
    if (!sel) return;
    var cur = (document.getElementById('inv-category-filter') && document.getElementById('inv-category-filter').value) || '';
    sel.innerHTML = '<option value="">Alle Kategorien</option>' + invCategories.map(function(cat) {
        return '<option value="' + String(cat.id) + '">' + escapeHtml(cat.name || '') + '</option>';
    }).join('');
    sel.value = cur;
    if (String(sel.value) !== String(cur) && cur) sel.value = '';
}

function fillInvMobileManufacturerSelect() {
    var sel = document.getElementById('inv-mobile-manufacturer-select');
    if (!sel) return;
    var cur = (document.getElementById('inv-manufacturer-filter') && document.getElementById('inv-manufacturer-filter').value) || '';
    var manufacturers = [];
    invConsumablesList.forEach(function(c) {
        (c.device_models || []).forEach(function(dm) {
            var h = (dm.hersteller || '').trim();
            if (h && manufacturers.indexOf(h) === -1) manufacturers.push(h);
        });
    });
    manufacturers.sort();
    sel.innerHTML = '<option value="">Alle Hersteller</option>' + manufacturers.map(function(m) {
        return '<option value="' + escapeHtml(m) + '">' + escapeHtml(m) + '</option>';
    }).join('');
    sel.value = cur;
}

function fillInvMobileModelSelect() {
    var sel = document.getElementById('inv-mobile-model-select');
    if (!sel) return;
    var cur = (document.getElementById('inv-model-filter') && document.getElementById('inv-model-filter').value) || '';
    var selectedManufacturer = (document.getElementById('inv-manufacturer-filter') && document.getElementById('inv-manufacturer-filter').value || '').trim();
    var models = [];
    invConsumablesList.forEach(function(c) {
        (c.device_models || []).forEach(function(dm) {
            var h = (dm.hersteller || '').trim();
            var m = (dm.modell || '').trim();
            if (selectedManufacturer && h !== selectedManufacturer) return;
            if (m && models.indexOf(m) === -1) models.push(m);
        });
    });
    models.sort();
    sel.innerHTML = '<option value="">Alle Modelle</option>' + models.map(function(m) {
        return '<option value="' + escapeHtml(m) + '">' + escapeHtml(m) + '</option>';
    }).join('');
    sel.value = cur;
}

function updateInvMobileModelRowVisibility() {
    var row = document.getElementById('inv-mobile-model-row');
    var mfr = document.getElementById('inv-manufacturer-filter');
    if (!row) return;
    if (mfr && (mfr.value || '').trim()) row.classList.remove('hidden');
    else row.classList.add('hidden');
}

function syncInvMobileFilterFromDesktop() {
    var st = document.getElementById('inv-status-filter');
    var stSel = document.getElementById('inv-mobile-status-select');
    if (st && stSel) stSel.value = st.value || '';
    fillInvMobileCategorySelect();
    fillInvMobileManufacturerSelect();
    fillInvMobileModelSelect();
    updateInvMobileModelRowVisibility();
    var catH = document.getElementById('inv-category-filter');
    var catSel = document.getElementById('inv-mobile-category-select');
    if (catH && catSel) catSel.value = catH.value || '';
    var mfrH = document.getElementById('inv-manufacturer-filter');
    var mfrSel = document.getElementById('inv-mobile-manufacturer-select');
    if (mfrH && mfrSel) mfrSel.value = mfrH.value || '';
    var modH = document.getElementById('inv-model-filter');
    var modSel = document.getElementById('inv-mobile-model-select');
    if (modH && modSel) modSel.value = modH.value || '';
}

function saveInventoryFiltersState() {
    try {
        const state = {
            search: (document.getElementById('inv-search') && document.getElementById('inv-search').value || '').trim(),
            category: (document.getElementById('inv-category-filter') && document.getElementById('inv-category-filter').value || '').trim(),
            manufacturer: (document.getElementById('inv-manufacturer-filter') && document.getElementById('inv-manufacturer-filter').value || '').trim(),
            model: (document.getElementById('inv-model-filter') && document.getElementById('inv-model-filter').value || '').trim(),
            status: (document.getElementById('inv-status-filter') && document.getElementById('inv-status-filter').value || '').trim()
        };
        localStorage.setItem(INVENTORY_FILTER_STORAGE_KEY, JSON.stringify(state));
    } catch (e) {
        console.error('Fehler beim Speichern der Lager-Filter', e);
    }
}

function getConsumableStockStatuses(c) {
    const lager = c.lagerbestand != null ? Number(c.lagerbestand) : 0;
    const mindest = c.mindestbestand != null ? Number(c.mindestbestand) : null;
    // „Nachbestellt“ nur bei verknüpfter Bestellung, die noch nicht „Im Lager“ oder „Angekommen“ (Zugestellt) ist (siehe API has_open_order)
    const hasOpenOrder = c.has_open_order === 1 || c.has_open_order === true || c.has_open_order === '1';
    const pendingStockin = c.pending_stockin_after_delivery === 1 || c.pending_stockin_after_delivery === true || c.pending_stockin_after_delivery === '1';
    const statuses = [];
    if (hasOpenOrder) statuses.push('nachbestellt');
    if (pendingStockin) statuses.push('bestellung_angekommen');
    if (lager <= 0) statuses.push('leer');
    if (mindest != null && lager < mindest && statuses.indexOf('nachbestellt') === -1 && !pendingStockin) {
        statuses.push('muss_nachbestellen');
    }
    if (statuses.length === 0) statuses.push('bestand_vorhanden');
    return statuses;
}

function restoreInventoryFiltersState() {
    try {
        const raw = localStorage.getItem(INVENTORY_FILTER_STORAGE_KEY);
        if (!raw) return;
        const state = JSON.parse(raw);
        const searchEl = document.getElementById('inv-search');
        const categoryEl = document.getElementById('inv-category-filter');
        const categoryTextEl = document.getElementById('inv-category-filter-text');
        const manufacturerEl = document.getElementById('inv-manufacturer-filter');
        const manufacturerTextEl = document.getElementById('inv-manufacturer-filter-text');
        const modelEl = document.getElementById('inv-model-filter');
        const modelTextEl = document.getElementById('inv-model-filter-text');
        const modelContainer = document.getElementById('inv-model-filter-container');
        const statusEl = document.getElementById('inv-status-filter');
        const statusTextEl = document.getElementById('inv-status-filter-text');
        
        if (state.search !== undefined && searchEl) {
            searchEl.value = state.search || '';
            if (state.search && state.search.trim()) {
                const searchWrapper = document.getElementById('inv-search-wrapper');
                const searchForm = document.getElementById('inv-search-form');
                const searchCloseBtn = document.getElementById('inv-search-close-btn');
                const searchCloseIconSearch = searchCloseBtn ? searchCloseBtn.querySelector('.search-close-icon.search-icon') : null;
                const searchCloseIconX = searchCloseBtn ? searchCloseBtn.querySelector('.search-close-icon.x-icon') : null;
                if (searchWrapper) {
                    searchWrapper.classList.add('search-expanded', 'search-active');
                    searchWrapper.classList.remove('search-closing');
                }
                if (searchForm) searchForm.classList.add('search-expanded');
                // Icon auf X setzen (wie im Service)
                if (searchCloseIconSearch) searchCloseIconSearch.classList.add('hidden');
                if (searchCloseIconX) searchCloseIconX.classList.remove('hidden');
                if (searchCloseBtn) searchCloseBtn.classList.remove('hidden');
            }
        }
        if (state.category !== undefined && categoryEl) {
            categoryEl.value = state.category || '';
            if (state.category && categoryTextEl) {
                // Kategoriename aus der Liste holen
                const category = invCategories.find(function(c) { return String(c.id) === state.category; });
                if (category) categoryTextEl.textContent = category.name;
            } else if (categoryTextEl) {
                categoryTextEl.textContent = 'Alle Kategorien';
            }
        }
        if (state.manufacturer !== undefined && manufacturerEl) {
            manufacturerEl.value = state.manufacturer || '';
            if (state.manufacturer && manufacturerTextEl) {
                manufacturerTextEl.textContent = state.manufacturer;
            } else if (manufacturerTextEl) {
                manufacturerTextEl.textContent = 'Alle Hersteller';
            }
            // Modell-Filter anzeigen/verstecken
            if (state.manufacturer && modelContainer) {
                modelContainer.classList.remove('hidden');
            } else if (modelContainer) {
                modelContainer.classList.add('hidden');
            }
        }
        if (state.model !== undefined && modelEl) {
            modelEl.value = state.model || '';
            if (state.model && modelTextEl) {
                modelTextEl.textContent = state.model;
            } else if (modelTextEl) {
                modelTextEl.textContent = 'Alle Modelle';
            }
        }
        if (state.status !== undefined && statusEl) {
            statusEl.value = state.status || '';
            if (statusTextEl) {
                var statusMap = {
                    'leer': 'Leer',
                    'bestand_vorhanden': 'Bestand vorhanden',
                    'bestellung_angekommen': 'Bestellung angekommen',
                    'muss_nachbestellen': 'Muss nachbestellt werden',
                    'nachbestellt': 'Nachbestellt',
                    'nachbestellen': 'Nachbestellt',
                    'unter_meldebestand': 'Nachbestellt'
                };
                statusTextEl.textContent = statusMap[state.status] || 'Alle Status';
            }
        }
        var invMobileSearchRestore = document.getElementById('inv-mobile-search');
        var invSearchRestore = document.getElementById('inv-search');
        if (invMobileSearchRestore && invSearchRestore) {
            invMobileSearchRestore.value = invSearchRestore.value || '';
        }
    } catch (e) {
        console.error('Fehler beim Wiederherstellen der Lager-Filter', e);
    }
}

function getFilteredConsumables() {
    const search = (document.getElementById('inv-search') && document.getElementById('inv-search').value || '').toLowerCase().trim();
    const categoryId = document.getElementById('inv-category-filter') ? (document.getElementById('inv-category-filter').value || '') : '';
    const manufacturer = document.getElementById('inv-manufacturer-filter') ? (document.getElementById('inv-manufacturer-filter').value || '') : '';
    const model = document.getElementById('inv-model-filter') ? (document.getElementById('inv-model-filter').value || '') : '';
    const stockStatus = document.getElementById('inv-status-filter') ? (document.getElementById('inv-status-filter').value || '') : '';
    let list = invConsumablesList;
    if (search) {
        list = list.filter(function(c) {
            const text = (c.bezeichnung || '') + ' ' + (c.artikelnummer || '') + ' ' + (c.ean || '') + ' ' + (c.beschreibung || '') + ' ' + (c.categories || []).map(function(cat) { return cat.name; }).join(' ') + ' ' + (c.device_models || []).map(function(dm) { return (dm.hersteller || '') + ' ' + (dm.modell || ''); }).join(' ');
            return text.toLowerCase().indexOf(search) >= 0;
        });
    }
    if (categoryId) {
        const cid = parseInt(categoryId, 10);
        list = list.filter(function(c) {
            return (c.categories || []).some(function(cat) { return cat.id === cid; });
        });
    }
    if (manufacturer) {
        list = list.filter(function(c) {
            return (c.device_models || []).some(function(dm) { return (dm.hersteller || '').trim() === manufacturer; });
        });
    }
    if (model) {
        list = list.filter(function(c) {
            return (c.device_models || []).some(function(dm) { return (dm.modell || '').trim() === model; });
        });
    }
    if (stockStatus) {
        list = list.filter(function(c) {
            return getConsumableStockStatuses(c).indexOf(stockStatus) !== -1;
        });
    }
    return list;
}

/** Nur die farbigen Bestands-Status-Badges (ohne Stückzahl). */
function buildInvLagerStatusBadgesHtml(c, opts) {
    opts = opts || {};
    var stockStatuses = getConsumableStockStatuses(c);
    stockStatuses = stockStatuses.filter(function(s) {
        return s !== 'leer' && s !== 'bestand_vorhanden';
    });
    if (opts.omitTrivialStockBadges) {
        stockStatuses = stockStatuses.filter(function(s) {
            return s !== 'leer' && s !== 'bestand_vorhanden';
        });
    }
    var html = stockStatuses.map(function(stockStatus) {
        if (stockStatus === 'nachbestellt') {
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Nachbestellt</span>';
        }
        if (stockStatus === 'bestellung_angekommen') {
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-violet-100 text-violet-900 dark:bg-violet-900/45 dark:text-violet-200">Bestellung angekommen</span>';
        }
        if (stockStatus === 'muss_nachbestellen') {
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-orange-100 text-orange-900 dark:bg-orange-900/45 dark:text-orange-200">Muss nachbestellt werden</span>';
        }
        return '';
    }).join('');
    return html;
}

/** Mobile: Mengen-Rahmen-Badge (nur Zahl, optional / Meldebestand). */
function buildInvQtyOutlineBadgeHtml(c) {
    const lager = c.lagerbestand != null ? Number(c.lagerbestand) : 0;
    const mindest = c.mindestbestand != null ? Number(c.mindestbestand) : null;
    var autoNachbestellenAktiv = c.auto_nachbestellen === 1 || c.auto_nachbestellen === true || c.auto_nachbestellen === '1';
    var qtyLabel = escapeHtml(String(lager));
    if (mindest != null) {
        qtyLabel += '<span class="mx-0.5 text-gray-400 dark:text-gray-500 font-normal">/</span>' + escapeHtml(String(mindest));
    }
    var borderClass = autoNachbestellenAktiv
        ? 'border-emerald-400/55 dark:border-emerald-500/40'
        : 'border-gray-300 dark:border-primary-200';
    var autoHint = autoNachbestellenAktiv
        ? '<span class="ml-1 inline-flex shrink-0 text-emerald-600/70 dark:text-emerald-400/60" aria-hidden="true">'
            + '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>'
            + '</span>'
        : '';
    var titleAttr = autoNachbestellenAktiv ? ' title="Automatische Nachbestellung aktiv"' : '';
    var ariaLabel = autoNachbestellenAktiv ? ' aria-label="Bestand, automatische Nachbestellung aktiv"' : '';
    return '<span class="inline-flex items-center rounded-md border ' + borderClass + ' bg-transparent px-2 py-1 text-sm font-semibold text-gray-800 dark:text-primary-100 tabular-nums"' + titleAttr + ariaLabel + '>' + qtyLabel + autoHint + '</span>';
}

/** Mobil: Status-Badges links neben dem Mengen-Rahmen-Badge (rechtsbündige Gruppe). */
function buildInvMobileLagerTopRightHtml(c) {
    var qty = buildInvQtyOutlineBadgeHtml(c);
    var st = buildInvLagerStatusBadgesHtml(c, { omitTrivialStockBadges: true });
    var statusPart = st
        ? '<div class="inv-mobile-lager-status flex flex-wrap gap-1 items-center min-w-0 leading-none justify-end">' + st + '</div>'
        : '';
    return '<div class="flex items-center gap-3 flex-wrap justify-end">' + statusPart + qty + '</div>';
}

/** Lagerort: erste Zeile „Regal …“, darunter „Spalte …, Fach …“ (mit <br>). */
function buildInvLagerortDisplayHtml(c) {
    if (!c) return '';
    var name = (c.shelf_name != null && String(c.shelf_name).trim() !== '') ? String(c.shelf_name).trim() : '';
    var sp = c.spalte;
    var fa = c.fach;
    var hasSp = sp != null && sp !== '' && !isNaN(Number(sp));
    var hasFa = fa != null && fa !== '' && !isNaN(Number(fa));
    var parts2 = [];
    if (hasSp) parts2.push('Spalte ' + escapeHtml(String(Number(sp))));
    if (hasFa) parts2.push('Fach ' + escapeHtml(String(Number(fa))));
    var line2 = parts2.join(', ');
    var line2Wrapped = line2 ? '<span class="text-xs text-gray-500 dark:text-gray-400 leading-snug">' + line2 + '</span>' : '';
    if (!name && !line2) return '';
    if (!name) return line2Wrapped;
    if (!line2) return 'Regal ' + escapeHtml(name);
    return 'Regal ' + escapeHtml(name) + '<br>' + line2Wrapped;
}

/** HTML nur für die Lager-Spalte (gleiche Logik wie in renderInvTable).
 * @param {object} c Consumable
 * @param {{omitLagerort?: boolean, omitTrivialStockBadges?: boolean}} opts Mobile-Tabellen-Zelle: Regal ausblenden; ohne triviale Badges (nur Desktop-Tabelle). */
function buildInvLagerCellHtml(c, opts) {
    opts = opts || {};
    const omitLagerort = !!opts.omitLagerort;
    const omitTrivialStockBadges = !!opts.omitTrivialStockBadges;
    const qtyBadgeHtml = buildInvQtyOutlineBadgeHtml(c);
    const lagerStatusHtml = buildInvLagerStatusBadgesHtml(c, { omitTrivialStockBadges: omitTrivialStockBadges });
    const lagerortHtml = !omitLagerort ? buildInvLagerortDisplayHtml(c) : '';
    return '<div class="flex flex-col gap-1"><div class="flex items-center gap-3 flex-wrap">' + lagerStatusHtml + qtyBadgeHtml + '</div>' + (lagerortHtml ? '<span class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 block leading-snug">' + lagerortHtml + '</span>' : '') + '</div>';
}

/** Nach Bestandsänderung: Datenmodell + nur Lager-Zelle; volles Neuladen nur bei neuem Scan-Artikel oder Filterbruch. */
function refreshInvStockAfterAdjust(consumableId, data, delta) {
    if (data && data.neu_angelegt) {
        loadConsumables();
        return;
    }
    var idStr = String(consumableId);
    var c = null;
    for (var i = 0; i < invConsumablesList.length; i++) {
        if (String(invConsumablesList[i].id) === idStr) {
            c = invConsumablesList[i];
            break;
        }
    }
    if (!c) {
        loadConsumables();
        return;
    }
    var statusFilterEl = document.getElementById('inv-status-filter');
    var filterVal = statusFilterEl ? (statusFilterEl.value || '').trim() : '';
    var beforeMatch = filterVal ? (getConsumableStockStatuses(c).indexOf(filterVal) !== -1) : true;

    c.lagerbestand = data.lagerbestand;
    if (data.has_open_order !== undefined && data.has_open_order !== null) {
        c.has_open_order = data.has_open_order ? 1 : 0;
    }
    if (data.pending_stockin_after_delivery !== undefined && data.pending_stockin_after_delivery !== null) {
        c.pending_stockin_after_delivery = data.pending_stockin_after_delivery;
    } else if (delta > 0) {
        c.pending_stockin_after_delivery = 0;
    }

    var afterMatch = filterVal ? (getConsumableStockStatuses(c).indexOf(filterVal) !== -1) : true;
    if (filterVal && beforeMatch !== afterMatch) {
        applyInvFilter();
        return;
    }

    var rows = document.querySelectorAll('.inv-consumable-row[data-consumable-id="' + idStr + '"]');
    if (!rows || !rows.length) {
        applyInvFilter();
        return;
    }
    var html = buildInvLagerCellHtml(c);
    var htmlMobileTopRight = buildInvMobileLagerTopRightHtml(c);
    rows.forEach(function(row) {
        if (row.classList.contains('inv-mobile-card')) {
            var q = row.querySelector('.inv-lager-cell');
            if (q) q.innerHTML = htmlMobileTopRight;
        } else {
            var cell = row.querySelector('.inv-lager-cell');
            if (cell) cell.innerHTML = html;
        }
    });
}

/** Ein Verbrauchsmaterial vom Server holen (z. B. nach Nachbestellung) und Liste + Ansicht aktualisieren. */
function invRefreshConsumableInListById(consumableId) {
    if (!consumableId) return Promise.resolve();
    return fetch(consumablesApiUrl + '?id=' + encodeURIComponent(String(consumableId)))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.consumable) return;
            var u = data.consumable;
            var idStr = String(u.id);
            for (var i = 0; i < invConsumablesList.length; i++) {
                if (String(invConsumablesList[i].id) === idStr) {
                    invConsumablesList[i] = u;
                    applyInvFilter();
                    return;
                }
            }
        })
        .catch(function() {});
}

function renderInvTable(list) {
    const tbody = document.getElementById('consumablesList');
    if (!tbody) return;
    if (!invConsumablesLoadedOnce && (!Array.isArray(list) || list.length === 0)) return;
    const baseUrlJs = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>');
    const colspan = 9;
    if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">' + (invConsumablesList.length === 0 ? 'Noch keine Verbrauchsmaterialien angelegt.' : 'Keine Einträge passen zu den Filtern.') + '</td></tr>';
        return;
    }
    tbody.innerHTML = list.map(function(c) {
        const detailUrl = baseUrlJs + 'inventory/detail.php?id=' + c.id;
        const deviceModels = c.device_models || [];
        let modelsHtml = '–';
        if (deviceModels.length > 0) {
            // Prüfe ob es mehrere Geräte sind und alle den gleichen Hersteller haben
            // Normalisiere Hersteller (trim, lowercase für Vergleich)
            const herstellers = deviceModels.map(function(dm) { 
                return (dm.hersteller || '').trim().toLowerCase(); 
            }).filter(Boolean);
            const uniqueHerstellers = [...new Set(herstellers)];
            // Nur zusammenfassen wenn: mindestens 2 Geräte mit Hersteller UND alle haben denselben Hersteller
            const allSameHersteller = herstellers.length >= 2 && uniqueHerstellers.length === 1;
            
            if (allSameHersteller) {
                // Hersteller nur einmal anzeigen, alle Modelle darunter
                // Verwende den originalen Hersteller (nicht lowercase) vom ersten Gerät mit Hersteller
                const firstWithHersteller = deviceModels.find(function(dm) { 
                    return (dm.hersteller || '').trim() !== ''; 
                });
                const hersteller = firstWithHersteller ? (firstWithHersteller.hersteller || '').trim() : '';
                const modells = deviceModels.map(function(dm) { return (dm.modell || '').trim(); }).filter(Boolean);
                if (modells.length > 0) {
                    modelsHtml = '<div class="flex flex-col"><span class="text-gray-700 dark:text-gray-300 font-medium">' + escapeHtml(hersteller) + '</span><span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">' + modells.map(function(m) { return escapeHtml(m); }).join(', ') + '</span></div>';
                } else {
                    modelsHtml = escapeHtml(hersteller);
                }
            } else {
                // Normale Anzeige: Hersteller + Modell für jedes Gerät
                modelsHtml = deviceModels.map(function(dm) {
                    const h = (dm.hersteller || '').trim();
                    const m = (dm.modell || '').trim();
                    if (h && m) return escapeHtml(h + ' ' + m);
                    if (h) return escapeHtml(h);
                    if (m) return escapeHtml(m);
                    return '';
                }).filter(Boolean).join(', ') || '–';
            }
        }
        const cats = (c.categories || []).map(function(cat) { return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300">' + escapeHtml(cat.name) + '</span>'; }).join(' ') || '–';
        const lagerCellHtml = buildInvLagerCellHtml(c);
        const needsScanReview = invConsumableNeedsScanReview(c);
        const scanReviewBadge = needsScanReview
            ? '<span class="inline-flex items-center mt-1.5 px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/50 dark:text-amber-200 border border-amber-200/80 dark:border-amber-700/60">Scan: Daten prüfen</span>'
            : '';
        const artikelCell = '<div class="flex flex-col"><span class="text-base font-medium text-gray-900 dark:text-white truncate">' + escapeHtml(c.bezeichnung || '') + '</span><span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">' + escapeHtml(c.artikelnummer || '–') + '</span>' + scanReviewBadge + '</div>';
        const trClass = 'inv-consumable-row bg-white dark:bg-primary-50 hover:bg-gray-50 dark:hover:bg-primary-940 transition-colors cursor-pointer' + (needsScanReview ? ' border-l-4 border-l-amber-400 dark:border-l-amber-500' : '');
        const ean = (c.ean || '').trim();
        const artikelnummer = (c.artikelnummer || '').trim();
        const code = ean || artikelnummer;
        const companyIdsList = Array.isArray(c.company_ids) ? c.company_ids.map(function(x) { return parseInt(x, 10); }).filter(function(x) { return !isNaN(x) && x > 0; }) : (c.company_id ? [parseInt(c.company_id, 10)] : []);
        const companyNames = companyIdsList.map(function(cid) {
            const co = invCompanies.find(function(x) { return Number(x.id) === Number(cid); });
            return co ? co.name : null;
        }).filter(Boolean);
        const companyCell = companyNames.length
            ? companyNames.map(function(n) { return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200">' + escapeHtml(n) + '</span>'; }).join(' ')
            : '–';
        const actionCell = (typeof canAdjustInventoryStock !== 'undefined' && canAdjustInventoryStock)
            ? ('<div class="flex items-center justify-center gap-2 py-2">' +
            '<button type="button" class="inv-stock-adjust inline-flex items-center justify-center w-9 h-9 rounded-full border border-red-200 bg-red-50 text-red-700 lg:hover:bg-red-100 dark:border-red-700/60 dark:bg-red-900/25 dark:text-red-300 dark:lg:hover:bg-red-900/40 max-lg:active:scale-[0.93] max-lg:active:shadow-inner max-lg:transition-transform max-lg:duration-150 max-lg:active:duration-75" data-delta="-1" title="Auslagern"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg></button>' +
            '<button type="button" class="inv-stock-adjust inline-flex items-center justify-center w-9 h-9 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 lg:hover:bg-emerald-100 dark:border-emerald-700/60 dark:bg-emerald-900/25 dark:text-emerald-300 dark:lg:hover:bg-emerald-900/40 max-lg:active:scale-[0.93] max-lg:active:shadow-inner max-lg:transition-transform max-lg:duration-150 max-lg:active:duration-75" data-delta="1" title="Einlagern"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></button>' +
            '</div>')
            : '<span class="text-gray-400 dark:text-primary-300 text-xs">–</span>';
        return '<tr class="' + trClass + '" data-detail-url="' + escapeHtml(detailUrl) + '" data-consumable-id="' + escapeHtml(String(c.id)) + '" data-ean="' + escapeHtml(ean) + '" data-artikelnummer="' + escapeHtml(artikelnummer) + '" data-code="' + escapeHtml(code) + '" data-scan-auto-review="' + (needsScanReview ? '1' : '0') + '" onclick="window.location.href=this.getAttribute(\'data-detail-url\')" oncontextmenu="event.preventDefault(); if(typeof showInvContextMenu === \'function\') { showInvContextMenu(event, this); } return false;"><td class="px-3 py-4 max-w-xs">' + artikelCell + '</td><td class="px-3 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400 font-mono text-xs">' + escapeHtml(c.ean || '–') + '</td><td class="px-3 py-3"><div class="flex flex-wrap gap-1">' + cats + '</div></td><td class="px-3 py-3 text-gray-600 dark:text-gray-400">' + companyCell + '</td><td class="px-3 py-3 text-gray-600 dark:text-gray-400">' + modelsHtml + '</td><td class="px-3 py-3 inv-lager-cell">' + lagerCellHtml + '</td><td class="px-3 py-3">' + actionCell + '</td></tr>';
    }).join('');
}

/** Kartenliste Mobil: kompakt – Bestand, Art.-Nr./EAN, schnelle Ein-/Auslagerung (Details per Tipp). */
function renderInvMobileCards(list) {
    const container = document.getElementById('consumablesMobileList');
    if (!container) return;
    if (!invConsumablesLoadedOnce && (!Array.isArray(list) || list.length === 0)) return;
    const baseUrlJs = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>');
    const emptyMsg = invConsumablesList.length === 0 ? 'Noch keine Verbrauchsmaterialien angelegt.' : 'Keine Einträge passen zu den Filtern.';
    if (list.length === 0) {
        container.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 dark:border-primary-120 bg-white/80 dark:bg-primary-100/60 px-4 py-10 text-center text-sm text-gray-500 dark:text-primary-210">' + escapeHtml(emptyMsg) + '</div>';
        return;
    }
    container.innerHTML = list.map(function(c) {
        const detailUrl = baseUrlJs + 'inventory/detail.php?id=' + c.id;
        const mobileLagerTopRightHtml = buildInvMobileLagerTopRightHtml(c);
        const footerActionsClass = 'inv-mobile-footer-actions flex items-center justify-between gap-1.5 min-w-0 py-2';
        const needsScanReview = invConsumableNeedsScanReview(c);
        const ean = (c.ean || '').trim();
        const artikelnummer = (c.artikelnummer || '').trim();
        const code = ean || artikelnummer;
        const artNrDisp = artikelnummer || '–';
        const eanDisp = ean || '–';
        const metaPlain = artNrDisp + ' · ' + eanDisp;
        const metaLine = escapeHtml(artNrDisp) + ' <span class="text-gray-400 dark:text-primary-300" aria-hidden="true">·</span> ' + escapeHtml(eanDisp);
        const metaTitleAttr = metaPlain.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        const ortHtml = buildInvLagerortDisplayHtml(c);
        const scanHint = needsScanReview ? '<span class="shrink-0 rounded px-1 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">Scan</span>' : '';
        const actionCell = (typeof canAdjustInventoryStock !== 'undefined' && canAdjustInventoryStock)
            ? ('<div class="flex items-center gap-1 shrink-0">' +
            '<button type="button" class="inv-stock-adjust inline-flex items-center justify-center w-9 h-9 rounded-full border border-red-200 bg-red-50 text-red-700 lg:hover:bg-red-100 dark:border-red-700/60 dark:bg-red-900/25 dark:text-red-300 dark:lg:hover:bg-red-900/40 max-lg:active:scale-[0.93] max-lg:active:shadow-inner max-lg:transition-transform max-lg:duration-150 max-lg:active:duration-75" data-delta="-1" title="Auslagern"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg></button>' +
            '<button type="button" class="inv-stock-adjust inline-flex items-center justify-center w-9 h-9 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 lg:hover:bg-emerald-100 dark:border-emerald-700/60 dark:bg-emerald-900/25 dark:text-emerald-300 dark:lg:hover:bg-emerald-900/40 max-lg:active:scale-[0.93] max-lg:active:shadow-inner max-lg:transition-transform max-lg:duration-150 max-lg:active:duration-75" data-delta="1" title="Einlagern"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></button>' +
            '</div>')
            : '';
        const editHref = baseUrlJs + 'inventory/edit.php?id=' + encodeURIComponent(String(c.id));
        const dataAttrs = ' data-detail-url="' + escapeHtml(detailUrl) + '" data-consumable-id="' + escapeHtml(String(c.id)) + '" data-ean="' + escapeHtml(ean) + '" data-artikelnummer="' + escapeHtml(artikelnummer) + '" data-code="' + escapeHtml(code) + '" data-scan-auto-review="' + (needsScanReview ? '1' : '0') + '"';
        const itemClass = 'inv-mobile-item relative overflow-hidden rounded-xl border border-gray-200 dark:border-primary-120 touch-manipulation' + (needsScanReview ? ' ring-2 ring-amber-400/80 dark:ring-amber-500/70' : '');
        const swipeLayerHtml = (typeof canEditConsumables !== 'undefined' && canEditConsumables)
            ? ('<div class="inv-swipe-actions-layer absolute inset-0 z-0 flex flex-row lg:hidden opacity-0 pointer-events-none transition-opacity duration-150" aria-hidden="true">' +
            '<div class="flex h-full min-h-0 w-[7rem] shrink-0">' +
            '<a href="' + escapeHtml(editHref) + '" class="inv-swipe-action relative flex flex-1 h-full min-w-0 items-center justify-center bg-sky-600 hover:bg-sky-700 text-white dark:bg-sky-700 dark:hover:bg-sky-600 rounded-l-xl border-0 p-0" onclick="event.stopPropagation(); if(typeof invSwipeResetAllTracks===\'function\') invSwipeResetAllTracks(null);" title="Bearbeiten" aria-label="Bearbeiten">' +
            '<svg class="w-6 h-6 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>' +
            '<button type="button" class="inv-swipe-action relative flex flex-1 h-full min-w-0 items-center justify-center bg-violet-600 hover:bg-violet-700 text-white dark:bg-violet-700 dark:hover:bg-violet-600 border-0 p-0" onclick="event.stopPropagation(); if(typeof invSwipeNachbestellenConsumable===\'function\') invSwipeNachbestellenConsumable(' + c.id + ');" title="Nachbestellen" aria-label="Nachbestellen">' +
            '<svg class="w-6 h-6 pointer-events-none text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10V6a3 3 0 0 1 3-3v0a3 3 0 0 1 3 3v4m3-2 .917 11.923A1 1 0 0 1 17.92 21H6.08a1 1 0 0 1-.997-1.077L6 8h12Z"/></svg></button>' +
            '</div>' +
            '<div class="min-w-0 flex-1" aria-hidden="true"></div>' +
            '<div class="flex h-full w-14 shrink-0">' +
            '<button type="button" class="inv-swipe-action flex h-full w-full items-center justify-center bg-red-600 hover:bg-red-700 text-white rounded-r-xl border-0 p-0" onclick="event.stopPropagation(); if(typeof invSwipeDeleteConsumable===\'function\') invSwipeDeleteConsumable(' + c.id + ');" title="Löschen" aria-label="Löschen">' +
            '<svg class="w-6 h-6 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-1 14H6L5 7h14zM10 11v6m4-6v6M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3M4 7h16"/></svg></button>' +
            '</div>' +
            '</div>')
            : '';
        return '<div class="' + itemClass + '">' +
            swipeLayerHtml +
            '<div class="inv-swipe-track inv-consumable-row inv-mobile-card relative z-[1] w-full min-w-0 bg-white dark:bg-primary-100 text-left overflow-hidden cursor-pointer"' + dataAttrs + ' data-swipe-x="0" style="transform:translateZ(0) translateX(0)" onclick="invSwipeOnTrackClick(event)" oncontextmenu="event.preventDefault(); if(typeof showInvContextMenu === \'function\') { showInvContextMenu(event, this); } return false;">' +
            '<div class="px-2 pt-1.5 pb-1.5">' +
            '<div class="flex gap-1.5 items-start">' +
            '<div class="min-w-0 flex-1">' +
            '<h3 class="text-[15px] font-semibold text-gray-900 dark:text-primary-100 leading-tight line-clamp-2">' + escapeHtml(c.bezeichnung || '') + '</h3>' +
            '<p class="mt-px text-[10px] text-gray-600 dark:text-primary-220 truncate"' + (metaTitleAttr ? ' title="' + metaTitleAttr + '"' : '') + '>' + metaLine + '</p>' +
            '</div>' +
            '<div class="inv-lager-cell shrink-0 max-w-[min(100%,20rem)] text-right self-start">' + mobileLagerTopRightHtml + '</div>' +
            '</div>' +
            '<div class="mt-1 border-t border-gray-100 dark:border-primary-120/70 pt-2">' +
            '<div class="' + footerActionsClass + '">' +
            '<span class="min-w-0 flex-1 text-sm font-semibold text-gray-800 dark:text-primary-100 leading-snug">' + ortHtml + '</span>' +
            '<div class="flex items-center gap-1 min-w-0">' + scanHint + actionCell + '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
    }).join('');
}

function invConsumableNeedsScanReview(c) {
    if (!c) return false;
    var v = c.scan_auto_review;
    return v === 1 || v === true || v === '1';
}

function updateInvScanReviewBanner() {
    var banner = document.getElementById('inv-scan-review-banner');
    var bodyEl = document.getElementById('inv-scan-review-body');
    if (!banner || !bodyEl) return;
    if (!invConsumablesList) invConsumablesList = [];
    var n = 0;
    for (var i = 0; i < invConsumablesList.length; i++) {
        if (invConsumableNeedsScanReview(invConsumablesList[i])) n++;
    }
    if (n > 0) {
        bodyEl.innerHTML = 'Im Lager befinden sich <strong>' + n + '</strong> Artikel, die automatisch per EAN-Scan angelegt wurden und unter <strong>Bearbeiten</strong> geprüft und ergänzt werden sollten (z.&nbsp;B. Bezeichnung, Firma, Kategorien, Lagerort). Die betreffenden Einträge sind in der Liste amber markiert und mit einem Hinweis beim Artikelnamen versehen.';
        banner.classList.remove('hidden');
    } else {
        bodyEl.textContent = '';
        banner.classList.add('hidden');
    }
}

function invAnyInventoryFilterActive() {
    var s = document.getElementById('inv-search');
    if (s && (s.value || '').trim()) return true;
    var st = document.getElementById('inv-status-filter');
    if (st && (st.value || '').trim()) return true;
    var c = document.getElementById('inv-category-filter');
    if (c && (c.value || '').trim()) return true;
    var mf = document.getElementById('inv-manufacturer-filter');
    if (mf && (mf.value || '').trim()) return true;
    var mo = document.getElementById('inv-model-filter');
    if (mo && (mo.value || '').trim()) return true;
    return false;
}

function updateInvNavMobileCompactTitle() {
    var el = document.getElementById('navMobileCompactTitle');
    if (!el) return;
    if (invAnyInventoryFilterActive()) {
        el.textContent = String(getFilteredConsumables().length) + ' Artikel';
    } else {
        el.textContent = 'Alle Artikel';
    }
}

function invStatusFilterLabel(status) {
    if (!status) return 'Alle Status';
    var map = {
        'leer': 'Leer',
        'bestand_vorhanden': 'Bestand vorhanden',
        'bestellung_angekommen': 'Bestellung angekommen',
        'muss_nachbestellen': 'Muss nachbestellt werden',
        'nachbestellt': 'Nachbestellt'
    };
    return map[status] || 'Alle Status';
}

function setInvStatusFilterAndApply(status) {
    var h = document.getElementById('inv-status-filter');
    var t = document.getElementById('inv-status-filter-text');
    var m = document.getElementById('inv-mobile-status-select');
    if (h) h.value = status || '';
    if (t) t.textContent = invStatusFilterLabel(status || '');
    if (m) m.value = status || '';
    if (typeof updateInvStatusFilterButtonState === 'function') updateInvStatusFilterButtonState();
    saveInventoryFiltersState();
    applyInvFilter();
}

function applyInvFilter() {
    var filtered = getFilteredConsumables();
    renderInvTable(filtered);
    renderInvMobileCards(filtered);
    updateInvScanReviewBanner();
    syncInvSearchFieldMirrors();
    updateInvNavMobileCompactTitle();
    invEnsureMobileSearchPanelIfQuery();
}

/** Mobil: Kein Suchbegriff aktiv? Mobil + Desktop prüfen — solange irgendwo Text steht, Auto-Schließen (Scroll/Blur) unterlassen. */
var invMobileSearchOpenedAt = 0;
var INV_MOBILE_SEARCH_AUTOCLOSE_GUARD_MS = 450;
var invIgnoreNavSearchClickUntil = 0;
var invMobileSearchFocusTimer = 0;
function invMobileSearchIsEmpty() {
    var m = document.getElementById('inv-mobile-search');
    var d = document.getElementById('inv-search');
    var mv = m ? (m.value || '').trim() : '';
    var dv = d ? (d.value || '').trim() : '';
    return !mv && !dv;
}

/** Mobil: Offenes Suchpanel schließen, wenn leer (Scroll, iOS „Fertig“/Blur). */
function invCloseMobileSearchIfEmpty() {
    var dash = document.getElementById('inv-mobile-dashboard');
    if (!dash || !dash.classList.contains('inv-mobile-search-panel-open')) return;
    if (invMobileSearchOpenedAt && (Date.now() - invMobileSearchOpenedAt) < INV_MOBILE_SEARCH_AUTOCLOSE_GUARD_MS) return;
    if (!invMobileSearchIsEmpty()) return;
    invSetMobileSearchPanelOpen(false, false);
}

/** Mobil: Suchpanel öffnen/schließen (CSS-Animation); bei focusInput Tastatur/Fokus auf #inv-mobile-search. */
function invSetMobileSearchPanelOpen(open, focusInput) {
    var dash = document.getElementById('inv-mobile-dashboard');
    var anim = document.getElementById('inv-mobile-search-anim');
    var btn = document.getElementById('navMobileInvSearchBtn');
    if (!dash) return;
    if (typeof focusInput === 'undefined') focusInput = !!open;
    if (open) {
        invMobileSearchOpenedAt = Date.now();
        dash.classList.add('inv-mobile-search-panel-open');
        if (anim) anim.setAttribute('aria-hidden', 'false');
        if (btn) btn.setAttribute('aria-expanded', 'true');
        if (focusInput) {
            var mInp = document.getElementById('inv-mobile-search');
            if (mInp) {
                if (invMobileSearchFocusTimer) window.clearTimeout(invMobileSearchFocusTimer);
                /* Kein readonly: iOS öffnet bei readonly oft keine Tastatur. Panel + Feld einmess (0fr→1fr), dann Fokus im selben Handler wie click. */
                try {
                    void dash.offsetHeight;
                    void mInp.offsetHeight;
                    try { mInp.focus({ preventScroll: true }); } catch (eFocusNow) { try { mInp.focus(); } catch (e2Now) {} }
                    invMobileSearchFocusTimer = window.setTimeout(function() {
                        invMobileSearchFocusTimer = 0;
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
        invMobileSearchOpenedAt = 0;
        if (invMobileSearchFocusTimer) {
            window.clearTimeout(invMobileSearchFocusTimer);
            invMobileSearchFocusTimer = 0;
        }
        dash.classList.remove('inv-mobile-search-panel-open');
        if (anim) anim.setAttribute('aria-hidden', 'true');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        var mBlur = document.getElementById('inv-mobile-search');
        if (mBlur && document.activeElement === mBlur) {
            try { mBlur.blur(); } catch (eB) {}
        }
    }
}

/** Mobil: Suchzeile einblenden, wenn ein Suchbegriff aktiv ist (z. B. nach Wiederherstellen der Filter). */
function invEnsureMobileSearchPanelIfQuery() {
    var dash = document.getElementById('inv-mobile-dashboard');
    var desk = document.getElementById('inv-search');
    var btn = document.getElementById('navMobileInvSearchBtn');
    if (!dash || !btn) return;
    if (typeof window.matchMedia === 'function' && !window.matchMedia('(max-width: 1023px)').matches) return;
    if (desk && (desk.value || '').trim()) {
        invSetMobileSearchPanelOpen(true, false);
    }
}

function syncInvSearchFieldMirrors() {
    var desk = document.getElementById('inv-search');
    var mob = document.getElementById('inv-mobile-search');
    if (!desk || !mob) return;
    if (document.activeElement === mob) return;
    mob.value = desk.value || '';
}

/** Skeleton beim Laden (gleiche Struktur wie initiales PHP-Markup). */
var INV_SKELETON_TABLE_ROWS = 6;
var INV_SKELETON_MOBILE_CARDS = 5;

function invGetTableSkeletonTbodyHtml() {
    var p = 'animate-pulse rounded-md bg-gray-200/90 dark:bg-primary-900/90';
    var rows = '';
    for (var r = 0; r < INV_SKELETON_TABLE_ROWS; r++) {
        rows += '<tr class="inv-skeleton-row pointer-events-none select-none" aria-hidden="true">' +
            '<td class="px-3 py-4 max-w-xs"><div class="h-5 max-w-[14rem] w-[72%] ' + p + '"></div><div class="mt-1.5 h-3 w-24 ' + p + '"></div></td>' +
            '<td class="px-3 py-3"><div class="h-3.5 w-28 font-mono ' + p + '"></div></td>' +
            '<td class="px-3 py-3"><div class="flex flex-wrap gap-1"><div class="h-5 w-14 ' + p + '"></div><div class="h-5 w-16 ' + p + '"></div></div></td>' +
            '<td class="px-3 py-3"><div class="h-5 w-28 ' + p + '"></div></td>' +
            '<td class="px-3 py-3"><div class="h-5 w-9 ' + p + '"></div></td>' +
            '<td class="px-3 py-3"><div class="h-5 w-8 ' + p + '"></div></td>' +
            '<td class="px-3 py-3"><div class="h-3.5 w-40 ' + p + '"></div></td>' +
            '<td class="px-3 py-3 inv-lager-cell"><div class="flex max-w-[220px] flex-col gap-1.5"><div class="flex flex-wrap items-center gap-2"><div class="h-5 w-12 ' + p + '"></div><div class="h-4 w-[4.5rem] ' + p + '"></div></div><div class="h-3 w-36 ' + p + '"></div><div class="h-3.5 w-32 ' + p + '"></div></div></td>' +
            '<td class="px-3 py-3"><div class="flex items-center justify-center gap-2 py-2"><div class="h-9 w-9 shrink-0 rounded-full ' + p + '"></div><div class="h-9 w-9 shrink-0 rounded-full ' + p + '"></div></div></td>' +
            '</tr>';
    }
    return rows;
}

function invGetMobileSkeletonHtml() {
    var p = 'animate-pulse rounded-md bg-gray-200/90 dark:bg-primary-900/90';
    var cards = '';
    for (var i = 0; i < INV_SKELETON_MOBILE_CARDS; i++) {
        cards += '<div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 px-2 pt-1.5 pb-1.5 pointer-events-none select-none" aria-hidden="true">' +
            '<div class="flex gap-1.5 items-start">' +
            '<div class="min-w-0 flex-1 space-y-1.5">' +
            '<div class="h-[18px] w-[88%] max-w-[16rem] ' + p + '"></div>' +
            '<div class="h-2.5 w-3/4 max-w-[12rem] ' + p + '"></div>' +
            '</div>' +
            '<div class="flex shrink-0 flex-col items-end gap-1">' +
            '<div class="h-4 w-10 ' + p + '"></div>' +
            '<div class="h-7 w-[4.5rem] rounded-md ' + p + '"></div>' +
            '</div>' +
            '</div>' +
            '<div class="mt-1 border-t border-gray-100 dark:border-primary-120/70 pt-2">' +
            '<div class="flex min-w-0 items-center justify-between gap-1.5 py-2">' +
            '<div class="h-3.5 min-w-0 max-w-[65%] flex-1 ' + p + '"></div>' +
            '<div class="flex shrink-0 gap-1">' +
            '<div class="h-9 w-9 rounded-full ' + p + '"></div>' +
            '<div class="h-9 w-9 rounded-full ' + p + '"></div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
    }
    return cards;
}

function clearInvLoadingSkeletonTimer() {
    if (invLoadingSkeletonTimer) {
        clearTimeout(invLoadingSkeletonTimer);
        invLoadingSkeletonTimer = null;
    }
}

function scheduleInvLoadingSkeletons() {
    clearInvLoadingSkeletonTimer();
    invLoadingSkeletonTimer = setTimeout(function() {
        invLoadingSkeletonTimer = null;
        const tbody = document.getElementById('consumablesList');
        const mobileList = document.getElementById('consumablesMobileList');
        if (tbody) tbody.innerHTML = invGetTableSkeletonTbodyHtml();
        if (mobileList) mobileList.innerHTML = invGetMobileSkeletonHtml();
    }, invLoadingSkeletonDelayMs);
}

function loadConsumables() {
    const tbody = document.getElementById('consumablesList');
    const mobileList = document.getElementById('consumablesMobileList');
    const colspan = 9;
    scheduleInvLoadingSkeletons();
    fetch(consumablesApiUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            clearInvLoadingSkeletonTimer();
            if (!data.success) {
                invConsumablesList = [];
                updateInvScanReviewBanner();
                if (tbody) tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="px-4 py-4 text-center text-red-500 dark:text-red-400">Fehler beim Laden.</td></tr>';
                if (mobileList) mobileList.innerHTML = '<div class="rounded-2xl border border-red-200 dark:border-red-800/60 bg-red-50/80 dark:bg-red-950/30 px-5 py-12 text-center text-sm text-red-600 dark:text-red-300">Fehler beim Laden.</div>';
                return;
            }
            invConsumablesList = data.consumables || [];
            invConsumablesLoadedOnce = true;
            return loadInvCategories();
        })
        .then(function() {
            fillInvCategoryFilterMenu();
            fillInvManufacturerFilterMenu();
            fillInvModelFilterMenu();
            fillInvStatusFilterMenu();
            // Gespeicherte Filter wiederherstellen
            restoreInventoryFiltersState();
            syncInvMobileFilterFromDesktop();
            updateInvCategoryFilterButtonState();
            updateInvManufacturerFilterButtonState();
            updateInvModelFilterButtonState();
            updateInvStatusFilterButtonState();
            // Modell-Filter nur anzeigen, wenn ein Hersteller ausgewählt ist
            var invManufacturerFilterInput = document.getElementById('inv-manufacturer-filter');
            var invModelFilterContainer = document.getElementById('inv-model-filter-container');
            if (invManufacturerFilterInput && invModelFilterContainer) {
                var selectedManufacturer = (invManufacturerFilterInput.value || '').trim();
                if (selectedManufacturer) {
                    invModelFilterContainer.classList.remove('hidden');
                    fillInvModelFilterMenu(); // Modell-Menü mit gefilterten Modellen aktualisieren
                } else {
                    invModelFilterContainer.classList.add('hidden');
                }
            }
            // Suche visuell wiederherstellen (nach dem Laden der Daten)
            var searchInput = document.getElementById('inv-search');
            var searchWrapper = document.getElementById('inv-search-wrapper');
            var searchForm = document.getElementById('inv-search-form');
            var searchCloseBtn = document.getElementById('inv-search-close-btn');
            if (searchInput && searchWrapper && (searchInput.value || '').trim()) {
                searchWrapper.classList.add('search-expanded', 'search-active');
                if (searchForm) searchForm.classList.add('search-expanded');
                if (searchCloseBtn) {
                    var searchCloseIconSearch = searchCloseBtn.querySelector('.search-close-icon.search-icon');
                    var searchCloseIconX = searchCloseBtn.querySelector('.search-close-icon.x-icon');
                    if (searchCloseIconSearch) searchCloseIconSearch.classList.add('hidden');
                    if (searchCloseIconX) searchCloseIconX.classList.remove('hidden');
                    searchCloseBtn.classList.remove('hidden');
                }
            }
            var pageParamsInv = new URLSearchParams(window.location.search);
            var invStatusFromUrl = pageParamsInv.get('inv_status');
            var editIdFromUrl = pageParamsInv.get('edit');
            if (invStatusFromUrl !== null) {
                setInvStatusFilterAndApply(invStatusFromUrl);
                pageParamsInv.delete('inv_status');
                var invNewSearch = pageParamsInv.toString();
                history.replaceState({}, '', window.location.pathname + (invNewSearch ? '?' + invNewSearch : '') + window.location.hash);
            } else {
                applyInvFilter();
            }
            if (editIdFromUrl) {
                window.location.href = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'inventory/edit.php?id=' + editIdFromUrl;
            }
        })
        .catch(function() {
            clearInvLoadingSkeletonTimer();
            invConsumablesList = [];
            updateInvScanReviewBanner();
            if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-4 text-center text-red-500 dark:text-red-400">Fehler beim Laden.</td></tr>';
            if (mobileList) mobileList.innerHTML = '<div class="rounded-2xl border border-red-200 dark:border-red-800/60 bg-red-50/80 dark:bg-red-950/30 px-5 py-12 text-center text-sm text-red-600 dark:text-red-300">Fehler beim Laden.</div>';
        });
}

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function fillInvCategoryFilterMenu() {
    const inner = document.getElementById('inv-category-filter-menu-inner');
    if (!inner) return;
    inner.innerHTML = '<button type="button" class="inv-category-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-category-id="" data-category-name="Alle Kategorien">Alle Kategorien</button>' +
        invCategories.map(function(cat) {
            return '<button type="button" class="inv-category-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-category-id="' + (cat.id || '') + '" data-category-name="' + escapeHtml(cat.name || '') + '">' + escapeHtml(cat.name || '') + '</button>';
        }).join('');
    fillInvMobileCategorySelect();
}

function fillInvManufacturerFilterMenu() {
    const inner = document.getElementById('inv-manufacturer-filter-menu-inner');
    if (!inner) return;
    const manufacturers = [];
    invConsumablesList.forEach(function(c) {
        (c.device_models || []).forEach(function(dm) {
            const h = (dm.hersteller || '').trim();
            if (h && manufacturers.indexOf(h) === -1) manufacturers.push(h);
        });
    });
    manufacturers.sort();
    inner.innerHTML = '<button type="button" class="inv-manufacturer-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-manufacturer="" data-manufacturer-name="Alle Hersteller">Alle Hersteller</button>' +
        manufacturers.map(function(m) {
            return '<button type="button" class="inv-manufacturer-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-manufacturer="' + escapeHtml(m) + '" data-manufacturer-name="' + escapeHtml(m) + '">' + escapeHtml(m) + '</button>';
        }).join('');
    fillInvMobileManufacturerSelect();
    updateInvMobileModelRowVisibility();
}

function fillInvModelFilterMenu() {
    const inner = document.getElementById('inv-model-filter-menu-inner');
    if (!inner) return;
    const selectedManufacturer = document.getElementById('inv-manufacturer-filter') ? (document.getElementById('inv-manufacturer-filter').value || '').trim() : '';
    const models = [];
    invConsumablesList.forEach(function(c) {
        (c.device_models || []).forEach(function(dm) {
            const h = (dm.hersteller || '').trim();
            const m = (dm.modell || '').trim();
            // Wenn ein Hersteller ausgewählt ist, nur Modelle dieses Herstellers anzeigen
            if (selectedManufacturer && h !== selectedManufacturer) return;
            if (m && models.indexOf(m) === -1) models.push(m);
        });
    });
    models.sort();
    inner.innerHTML = '<button type="button" class="inv-model-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-model="" data-model-name="Alle Modelle">Alle Modelle</button>' +
        models.map(function(m) {
            return '<button type="button" class="inv-model-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-model="' + escapeHtml(m) + '" data-model-name="' + escapeHtml(m) + '">' + escapeHtml(m) + '</button>';
        }).join('');
    fillInvMobileModelSelect();
}

function fillInvStatusFilterMenu() {
    const inner = document.getElementById('inv-status-filter-menu-inner');
    if (!inner) return;
    inner.innerHTML =
        '<button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="" data-status-name="Alle Status">Alle Status</button>' +
        '<button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="leer" data-status-name="Leer">Leer</button>' +
        '<button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="bestand_vorhanden" data-status-name="Bestand vorhanden">Bestand vorhanden</button>' +
        '<button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="bestellung_angekommen" data-status-name="Bestellung angekommen">Bestellung angekommen</button>' +
        '<button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="muss_nachbestellen" data-status-name="Muss nachbestellt werden">Muss nachbestellt werden</button>' +
        '<button type="button" class="inv-status-option w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors" data-status="nachbestellt" data-status-name="Nachbestellt">Nachbestellt</button>';
}

function updateInvCategoryFilterButtonState() {
    const btn = document.getElementById('inv-category-filter-button');
    const hidden = document.getElementById('inv-category-filter');
    if (!btn || !hidden) return;
    const hasSelection = (hidden.value || '').trim() !== '';
    btn.classList.toggle('filter-btn--default', !hasSelection);
    btn.classList.toggle('inv-category-filter-btn--active', hasSelection);
}

function updateInvManufacturerFilterButtonState() {
    const btn = document.getElementById('inv-manufacturer-filter-button');
    const hidden = document.getElementById('inv-manufacturer-filter');
    if (!btn || !hidden) return;
    const hasSelection = (hidden.value || '').trim() !== '';
    btn.classList.toggle('filter-btn--default', !hasSelection);
    btn.classList.toggle('inv-manufacturer-filter-btn--active', hasSelection);
}

function updateInvModelFilterButtonState() {
    const btn = document.getElementById('inv-model-filter-button');
    const hidden = document.getElementById('inv-model-filter');
    if (!btn || !hidden) return;
    const hasSelection = (hidden.value || '').trim() !== '';
    btn.classList.toggle('filter-btn--default', !hasSelection);
    btn.classList.toggle('inv-model-filter-btn--active', hasSelection);
}

function updateInvStatusFilterButtonState() {
    const btn = document.getElementById('inv-status-filter-button');
    const hidden = document.getElementById('inv-status-filter');
    if (!btn || !hidden) return;
    const hasSelection = (hidden.value || '').trim() !== '';
    btn.classList.toggle('filter-btn--default', !hasSelection);
    btn.classList.toggle('inv-status-filter-btn--active', hasSelection);
}

let deviceModelRowIndex = 0;
function addDeviceModelRow(hersteller, modell) {
    const container = document.getElementById('deviceModelsContainer');
    if (!container) return;
    const id = 'dm-' + (deviceModelRowIndex++);
    const row = document.createElement('div');
    row.className = 'flex gap-2 items-center';
    row.dataset.rowId = id;
    row.innerHTML =
        '<div class="relative flex-1">' +
        '<input type="text" placeholder="Hersteller" autocomplete="off" class="consumable-hersteller w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" value="' + escapeHtml(hersteller || '') + '">' +
        '<div class="inv-dm-suggestions hidden absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto" data-dm-type="hersteller"></div>' +
        '</div>' +
        '<div class="relative flex-1">' +
        '<input type="text" placeholder="Modell" autocomplete="off" class="consumable-modell w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" value="' + escapeHtml(modell || '') + '">' +
        '<div class="inv-dm-suggestions hidden absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto" data-dm-type="modell"></div>' +
        '</div>' +
        '<button type="button" onclick="this.closest(\'[data-row-id]\').remove()" class="p-2 text-gray-500 hover:text-red-600 dark:hover:text-red-400 flex-shrink-0" title="Entfernen">×</button>';
    container.appendChild(row);
}

function showInvSuggestions(inputEl, items, type) {
    const wrapper = inputEl.closest('.relative');
    if (!wrapper) return;
    const suggestionsDiv = wrapper.querySelector('.inv-dm-suggestions[data-dm-type="' + type + '"]');
    if (!suggestionsDiv) return;
    const value = (inputEl.value || '').toLowerCase().trim();
    const filtered = items.filter(function(item) {
        return item && item.toLowerCase().includes(value) && item.toLowerCase() !== value;
    });
    if (filtered.length === 0 || value.length === 0) {
        suggestionsDiv.classList.add('hidden');
        suggestionsDiv.innerHTML = '';
        return;
    }
    suggestionsDiv.innerHTML = filtered.slice(0, 12).map(function(item) {
        return '<div class="inv-dm-suggestion-item px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-gray-900 dark:text-white" data-value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</div>';
    }).join('');
    suggestionsDiv.classList.remove('hidden');
}

function hideAllInvSuggestions() {
    document.querySelectorAll('#deviceModelsContainer .inv-dm-suggestions').forEach(function(el) {
        el.classList.add('hidden');
    });
}

function setupInvDeviceModelAutocomplete() {
    const container = document.getElementById('deviceModelsContainer');
    if (!container) return;
    container.addEventListener('input', function(e) {
        const el = e.target;
        if (el.classList.contains('consumable-hersteller')) {
            showInvSuggestions(el, invManufacturers, 'hersteller');
        } else if (el.classList.contains('consumable-modell')) {
            showInvSuggestions(el, invModels, 'modell');
        }
    });
    container.addEventListener('focus', function(e) {
        const el = e.target;
        if (el.classList.contains('consumable-hersteller') && (el.value || '').trim()) {
            showInvSuggestions(el, invManufacturers, 'hersteller');
        } else if (el.classList.contains('consumable-modell') && (el.value || '').trim()) {
            showInvSuggestions(el, invModels, 'modell');
        }
    }, true);
    container.addEventListener('blur', function(e) {
        const el = e.target;
        if (el.classList.contains('consumable-hersteller') || el.classList.contains('consumable-modell')) {
            setTimeout(hideAllInvSuggestions, 200);
        }
    }, true);
    container.addEventListener('click', function(e) {
        const item = e.target.closest('.inv-dm-suggestion-item');
        if (!item) return;
        e.preventDefault();
        const value = item.getAttribute('data-value');
        const wrapper = item.closest('.relative');
        if (!wrapper || !value) return;
        const type = item.closest('.inv-dm-suggestions').getAttribute('data-dm-type');
        const input = wrapper.querySelector('.consumable-' + type);
        if (input) {
            input.value = value;
            hideAllInvSuggestions();
            try { input.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
        }
    });
}


let invShelves = [];
function loadInvShelves() {
    return fetch(shelvesApiUrl)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            invShelves = (d.success && d.shelves) ? d.shelves : [];
            return invShelves;
        })
        .catch(function() { invShelves = []; return []; });
}

function fillConsumableShelfSelect(selectedId) {
    const sel = document.getElementById('consumableShelf');
    if (!sel) return;
    const id = selectedId ? parseInt(selectedId, 10) : null;
    sel.innerHTML = '<option value="">— Kein Regal —</option>' + invShelves.map(function(s) {
        return '<option value="' + s.id + '"' + (s.id === id ? ' selected' : '') + '>' + escapeHtml(s.name) + '</option>';
    }).join('');
}

function openConsumableModal(consumable) {
    document.getElementById('consumableModalTitle').textContent = consumable ? 'Verbrauchsmaterial bearbeiten' : 'Verbrauchsmaterial anlegen';
    document.getElementById('consumableId').value = consumable ? consumable.id : '';
    document.getElementById('consumableBezeichnung').value = consumable ? (consumable.bezeichnung || '') : '';
    document.getElementById('consumableArtikelnummer').value = consumable ? (consumable.artikelnummer || '') : '';
    document.getElementById('consumableEan').value = consumable ? (consumable.ean || '') : '';
    document.getElementById('consumableBeschreibung').value = consumable ? (consumable.beschreibung || '') : '';
    var companyIdsForForm = [];
    if (consumable) {
        if (Array.isArray(consumable.company_ids) && consumable.company_ids.length) {
            companyIdsForForm = consumable.company_ids.map(function(x) { return parseInt(x, 10); }).filter(function(x) { return !isNaN(x) && x > 0; });
        } else if (consumable.company_id) {
            var singleCid = parseInt(consumable.company_id, 10);
            if (!isNaN(singleCid) && singleCid > 0) companyIdsForForm = [singleCid];
        }
    }
    renderCompanyCheckboxes(companyIdsForForm);
    document.getElementById('consumableMindestbestand').value = consumable && consumable.mindestbestand != null ? consumable.mindestbestand : '';
    document.getElementById('consumableAutoNachbestellen').checked = consumable ? (consumable.auto_nachbestellen === 1 || consumable.auto_nachbestellen === true) : false;
    document.getElementById('consumableLagerbestand').value = consumable && consumable.lagerbestand != null ? consumable.lagerbestand : '0';
    document.getElementById('consumableSpalte').value = consumable && consumable.spalte != null && consumable.spalte !== '' ? consumable.spalte : '';
    document.getElementById('consumableFach').value = consumable && consumable.fach != null && consumable.fach !== '' ? consumable.fach : '';
    loadInvShelves().then(function() {
        fillConsumableShelfSelect(consumable && consumable.shelf_id ? consumable.shelf_id : null);
    });
    const container = document.getElementById('deviceModelsContainer');
    container.innerHTML = '';
    if (consumable && consumable.device_models && consumable.device_models.length) {
        consumable.device_models.forEach(dm => addDeviceModelRow(dm.hersteller || '', dm.modell || ''));
    } else {
        addDeviceModelRow('', '');
    }
    if (window.InvDeviceModelAutoRow) InvDeviceModelAutoRow.ensure(container, addDeviceModelRow);
    document.getElementById('newCategoryName').value = '';
    loadInvCategories().then(function() {
        renderCategoryCheckboxes(consumable && consumable.category_ids ? consumable.category_ids : []);
    });
    document.getElementById('consumableModal').classList.remove('hidden');
    loadInvManufacturers().then(function() {});
    loadInvModels().then(function() {});
}

function closeConsumableModal() {
    document.getElementById('consumableModal').classList.add('hidden');
}

function getDeviceModelsFromForm() {
    const rows = document.querySelectorAll('#deviceModelsContainer [data-row-id]');
    const out = [];
    rows.forEach(row => {
        const h = (row.querySelector('.consumable-hersteller') || {}).value || '';
        const m = (row.querySelector('.consumable-modell') || {}).value || '';
        if (h.trim() || m.trim()) out.push({ hersteller: h.trim(), modell: m.trim() });
    });
    return out;
}

function saveConsumable(e) {
    e.preventDefault();
    const id = document.getElementById('consumableId').value;
    const payload = {
        bezeichnung: document.getElementById('consumableBezeichnung').value.trim(),
        artikelnummer: document.getElementById('consumableArtikelnummer').value.trim() || null,
        ean: document.getElementById('consumableEan').value.trim() || null,
        beschreibung: document.getElementById('consumableBeschreibung').value.trim() || null,
        mindestbestand: document.getElementById('consumableMindestbestand').value === '' ? null : parseInt(document.getElementById('consumableMindestbestand').value, 10),
        auto_nachbestellen: document.getElementById('consumableAutoNachbestellen').checked,
        lagerbestand: parseInt(document.getElementById('consumableLagerbestand').value, 10) || 0,
        shelf_id: document.getElementById('consumableShelf').value === '' ? null : parseInt(document.getElementById('consumableShelf').value, 10),
        company_ids: getCompanyIdsFromForm(),
        spalte: document.getElementById('consumableSpalte').value === '' ? null : parseInt(document.getElementById('consumableSpalte').value, 10),
        fach: document.getElementById('consumableFach').value === '' ? null : parseInt(document.getElementById('consumableFach').value, 10),
        category_ids: getCategoryIdsFromForm(),
        device_models: getDeviceModelsFromForm()
    };
    if (id) payload.id = parseInt(id, 10);
    const method = id ? 'PUT' : 'POST';
    fetch(consumablesApiUrl, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') showToast(id ? 'Gespeichert.' : 'Angelegt.', 'success');
                closeConsumableModal();
                loadConsumables();
            } else {
                if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error');
            }
        })
        .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error'); });
}

function editConsumable(id) {
    const baseUrlJs = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>');
    window.location.href = baseUrlJs + 'inventory/edit.php?id=' + id;
}

function deleteConsumable(id, name) {
    if (!confirm('Verbrauchsmaterial „' + name + '“ wirklich löschen?')) return;
    fetch(consumablesApiUrl + '?id=' + id, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') showToast('Gelöscht.', 'success');
                loadConsumables();
            } else {
                if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error');
            }
        })
        .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Löschen', 'error'); });
}

document.getElementById('consumableModalOverlay').addEventListener('click', closeConsumableModal);

function openShelvesModal() {
    document.getElementById('shelvesModal').classList.remove('hidden');
    document.getElementById('shelfFormContainer').classList.add('hidden');
    document.getElementById('shelfEditId').value = '';
    document.getElementById('shelfName').value = '';
    document.getElementById('shelfBeschreibung').value = '';
    document.getElementById('shelfSpaltenAnzahl').value = '5';
    document.getElementById('shelfFaecherAnzahl').value = '6';
    loadShelvesList();
}
function closeShelvesModal() {
    document.getElementById('shelvesModal').classList.add('hidden');
}
function loadShelvesList() {
    const ul = document.getElementById('shelvesList');
    ul.innerHTML = '<li class="text-sm text-gray-500 dark:text-gray-400 py-2">Lade Regale…</li>';
    fetch(shelvesApiUrl)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success || !d.shelves || d.shelves.length === 0) {
                ul.innerHTML = '<li class="text-sm text-gray-500 dark:text-gray-400 py-2">Noch keine Regale angelegt. Klicken Sie auf „Regal anlegen“.</li>';
                return;
            }
            invShelves = d.shelves;
            ul.innerHTML = d.shelves.map(function(s) {
                var safeName = (s.name || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                return '<li class="flex items-center justify-between gap-3 py-2 px-3 rounded-lg bg-gray-50 dark:bg-primary-900/30 border border-gray-200 dark:border-primary-120">' +
                    '<div class="min-w-0 flex-1"><span class="font-medium text-gray-900 dark:text-white">' + escapeHtml(s.name) + '</span>' +
                    (s.beschreibung ? '<span class="block text-xs text-gray-500 dark:text-gray-400 truncate">' + escapeHtml(s.beschreibung) + '</span>' : '') + '</div>' +
                    '<span class="flex gap-1 flex-shrink-0"><button type="button" onclick="editShelf(' + s.id + ')" class="text-primary-600 dark:text-primary-400 hover:underline text-sm">Bearbeiten</button>' +
                    '<button type="button" onclick="deleteShelf(' + s.id + ', \'' + safeName + '\')" class="text-red-600 dark:text-red-400 hover:underline text-sm">Löschen</button></span></li>';
            }).join('');
        })
        .catch(function() {
            ul.innerHTML = '<li class="text-sm text-red-500 dark:text-red-400 py-2">Fehler beim Laden.</li>';
        });
}
function editShelf(id) {
    const s = invShelves.find(function(x) { return x.id === id; });
    if (!s) return;
    document.getElementById('shelfEditId').value = s.id;
    document.getElementById('shelfName').value = s.name || '';
    document.getElementById('shelfBeschreibung').value = s.beschreibung || '';
    document.getElementById('shelfSpaltenAnzahl').value = (s.spalten_anzahl != null ? s.spalten_anzahl : 5);
    document.getElementById('shelfFaecherAnzahl').value = (s.faecher_anzahl != null ? s.faecher_anzahl : 6);
    document.getElementById('shelfFormContainer').classList.remove('hidden');
}
function deleteShelf(id, name) {
    if (!confirm('Regal „' + name + '“ wirklich löschen? Verbrauchsmaterialien behalten keinen Lagerort mehr.')) return;
    fetch(shelvesApiUrl + '?id=' + id, { method: 'DELETE' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                if (typeof showToast === 'function') showToast('Regal gelöscht.', 'success');
                loadShelvesList();
                loadInvShelves().then(function() { fillConsumableShelfSelect(null); });
            } else {
                if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
            }
        })
        .catch(function() { if (typeof showToast === 'function') showToast('Fehler beim Löschen', 'error'); });
}
function saveShelfFromForm() {
    const editId = document.getElementById('shelfEditId').value;
    const name = (document.getElementById('shelfName').value || '').trim();
    if (!name) {
        if (typeof showToast === 'function') showToast('Bitte Regal-Name eingeben.', 'error');
        return;
    }
    const beschreibung = (document.getElementById('shelfBeschreibung').value || '').trim() || null;
    const spaltenAnzahl = Math.max(1, Math.min(20, parseInt(document.getElementById('shelfSpaltenAnzahl').value, 10) || 5));
    const faecherAnzahl = Math.max(1, Math.min(20, parseInt(document.getElementById('shelfFaecherAnzahl').value, 10) || 6));
    const payload = { name: name, beschreibung: beschreibung, sort_order: 0, spalten_anzahl: spaltenAnzahl, faecher_anzahl: faecherAnzahl };
    const method = editId ? 'PUT' : 'POST';
    if (editId) payload.id = parseInt(editId, 10);
    fetch(shelvesApiUrl, { method: method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                if (typeof showToast === 'function') showToast(editId ? 'Regal gespeichert.' : 'Regal angelegt.', 'success');
                document.getElementById('shelfFormContainer').classList.add('hidden');
                document.getElementById('shelfEditId').value = '';
                document.getElementById('shelfName').value = '';
                document.getElementById('shelfBeschreibung').value = '';
                loadShelvesList();
                loadInvShelves().then(function() { fillConsumableShelfSelect(null); });
            } else {
                if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
            }
        })
        .catch(function() { if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error'); });
}

// Globale Funktion für Context-Menü (muss vor DOMContentLoaded sein)
window.showInvContextMenu = function(event, row) {
    event.preventDefault();
    event.stopPropagation();
    var invContextMenu = document.getElementById('inv-context-menu');
    if (!invContextMenu) return;
    
    // Speichere die aktuelle Zeile in einem globalen Objekt
    if (!window.invContextData) window.invContextData = {};
    window.invContextData.currentRow = row;
    
    invContextMenu.classList.remove('hidden');
    var x = event.clientX;
    var y = event.clientY;
    invContextMenu.style.left = x + 'px';
    invContextMenu.style.top = y + 'px';
    
    // Menü innerhalb des Viewports halten + Mengenfeld fokussieren
    setTimeout(function() {
        var rect = invContextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            invContextMenu.style.left = (window.innerWidth - rect.width - 10) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            invContextMenu.style.top = (window.innerHeight - rect.height - 10) + 'px';
        }
        if (rect.left < 0) {
            invContextMenu.style.left = '10px';
        }
        if (rect.top < 0) {
            invContextMenu.style.top = '10px';
        }
        var qtyRow = document.getElementById('inv-context-quantity-row');
        var slotEinlagern = document.getElementById('inv-context-qty-slot-einlagern');
        if (qtyRow) {
            qtyRow.classList.add('hidden');
            if (slotEinlagern && qtyRow.parentNode !== slotEinlagern) slotEinlagern.appendChild(qtyRow);
        }
        var btnEinlagern = document.getElementById('inv-context-mehrere-einlagern');
        var btnAuslagern = document.getElementById('inv-context-mehrere-auslagern');
        if (btnEinlagern) btnEinlagern.classList.remove('inv-context-item-hidden');
        if (btnAuslagern) btnAuslagern.classList.remove('inv-context-item-hidden');
        var btnNachbestellen = document.getElementById('inv-context-nachbestellen');
        if (btnNachbestellen) {
            btnNachbestellen.classList.remove('inv-context-item-disabled');
            btnNachbestellen.removeAttribute('aria-disabled');
        }
        var cid = row.getAttribute('data-consumable-id');
        if (cid) {
            var ordersApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'orders/api/orders.php';
            fetch(ordersApiUrl + '?consumable_id=' + encodeURIComponent(cid))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.has_open && btnNachbestellen) {
                        btnNachbestellen.classList.add('inv-context-item-disabled');
                        btnNachbestellen.setAttribute('aria-disabled', 'true');
                    }
                })
                .catch(function() {});
        }
    }, 0);
};

/** Mobil Lager: Wischgesten (analog Aufgaben) */
var INV_MOBILE_SWIPE_W_LEFT = 112;
var INV_MOBILE_SWIPE_W_RIGHT = 56;
var INV_MOBILE_SWIPE_SNAP_EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';
var INV_MOBILE_SWIPE_SNAP_MS = 340;

function invSwipeSetTranslate(track, x, animate) {
    if (!track) return;
    var nx = Math.max(-INV_MOBILE_SWIPE_W_RIGHT, Math.min(INV_MOBILE_SWIPE_W_LEFT, x));
    track.dataset.swipeX = String(nx);
    if (animate) {
        track.style.transition = 'transform ' + INV_MOBILE_SWIPE_SNAP_MS + 'ms ' + INV_MOBILE_SWIPE_SNAP_EASE;
        track.style.willChange = 'transform';
    } else {
        track.style.transition = 'none';
        track.style.willChange = 'transform';
    }
    track.style.transform = 'translateZ(0) translateX(' + nx + 'px)';
    var item = track.closest('.inv-mobile-item');
    if (item) {
        var revealed = Math.abs(nx) > 0.01;
        item.classList.toggle('inv-mobile-item--swipe-revealed', revealed);
        var layer = item.querySelector('.inv-swipe-actions-layer');
        if (layer) layer.setAttribute('aria-hidden', revealed ? 'false' : 'true');
    }
    if (animate) {
        window.clearTimeout(track._invSwipeSnapT);
        track._invSwipeSnapT = window.setTimeout(function() {
            track.style.willChange = '';
            track._invSwipeSnapT = null;
        }, INV_MOBILE_SWIPE_SNAP_MS + 80);
    }
}

function invSwipeResetAllTracks(exceptTrack) {
    document.querySelectorAll('#consumablesMobileList .inv-swipe-track').forEach(function(tr) {
        if (exceptTrack && tr === exceptTrack) return;
        if (parseFloat(tr.dataset.swipeX || '0') !== 0) invSwipeSetTranslate(tr, 0, true);
    });
}

function invSwipeOnTrackClick(ev) {
    if (typeof window.__invSwipeBlockClickUntil === 'number' && Date.now() < window.__invSwipeBlockClickUntil) return;
    if (ev.target.closest('a[href]')) return;
    if (ev.target.closest('button')) return;
    if (ev.target.closest('input')) return;
    var track = ev.currentTarget;
    var off = parseFloat(track.dataset.swipeX || '0') || 0;
    if (off !== 0) {
        invSwipeResetAllTracks(null);
        return;
    }
    var url = track.getAttribute('data-detail-url');
    if (url) window.location.href = url;
}

(function initInvMobileSwipeGestures() {
    var swipeState = null;
    function isMobileSwipe() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }
    function onTouchStart(e) {
        if (!isMobileSwipe()) return;
        var track = e.target.closest('.inv-swipe-track');
        if (!track || !document.getElementById('consumablesMobileList') || !document.getElementById('consumablesMobileList').contains(track)) return;
        if (e.target.closest('.inv-swipe-action')) return;
        if (e.target.closest('a[href], button, input, label')) return;
        var t = e.changedTouches[0];
        invSwipeResetAllTracks(track);
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
        var t = e.changedTouches[0];
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
            if (nx > INV_MOBILE_SWIPE_W_LEFT) nx = INV_MOBILE_SWIPE_W_LEFT + (nx - INV_MOBILE_SWIPE_W_LEFT) * 0.12;
            if (nx < -INV_MOBILE_SWIPE_W_RIGHT) nx = -INV_MOBILE_SWIPE_W_RIGHT + (nx + INV_MOBILE_SWIPE_W_RIGHT) * 0.12;
            invSwipeSetTranslate(swipeState.track, nx, false);
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
        if (off < -INV_MOBILE_SWIPE_W_RIGHT / 2) nx = -INV_MOBILE_SWIPE_W_RIGHT;
        else if (off > INV_MOBILE_SWIPE_W_LEFT / 2) nx = INV_MOBILE_SWIPE_W_LEFT;
        else nx = 0;
        tr.style.willChange = '';
        invSwipeSetTranslate(tr, nx, true);
        if (swipeState.moved) window.__invSwipeBlockClickUntil = Date.now() + 320;
        swipeState = null;
    }
    document.addEventListener('touchstart', onTouchStart, { capture: true, passive: true });
    document.addEventListener('touchmove', onTouchMove, { capture: true, passive: false });
    document.addEventListener('touchend', onTouchEnd, { capture: true, passive: true });
    document.addEventListener('touchcancel', onTouchEnd, { capture: true, passive: true });
})();

document.addEventListener('DOMContentLoaded', function() {
    var invSearchDebounceTimer = null;
    var invSearchSaveTimer = null;
    var navInvSearchBtn = document.getElementById('navMobileInvSearchBtn');
    var invMobileDash = document.getElementById('inv-mobile-dashboard');
    if (navInvSearchBtn && invMobileDash) {
        function invToggleMobileSearchBar() {
            var isOpen = invMobileDash.classList.contains('inv-mobile-search-panel-open');
            if (!isOpen) {
                invSetMobileSearchPanelOpen(true, true);
                return;
            }
            /* Offen + Suchbegriff: nicht schließen (wie bei Scroll/Blur); Fokus ins Feld für weitere Eingabe */
            if (!invMobileSearchIsEmpty()) {
                var mInp = document.getElementById('inv-mobile-search');
                if (mInp) {
                    try { mInp.focus({ preventScroll: true }); } catch (e) { try { mInp.focus(); } catch (e2) {} }
                }
                return;
            }
            invSetMobileSearchPanelOpen(false, false);
        }
        /*
         * Nur click (kein touchend + preventDefault): preventDefault auf touchend kann auf iOS die Tastatur-Freigabe für programmatisches focus() stören.
         * Der aus Touch resultierende click gilt weiterhin als User-Gesture; Fokus erfolgt synchron in invSetMobileSearchPanelOpen.
         */
        navInvSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (Date.now() < invIgnoreNavSearchClickUntil) return;
            invToggleMobileSearchBar();
        });
    }
    loadInvCompanies().then(function() { loadConsumables(); });
    setupInvDeviceModelAutocomplete();
    if (window.InvDeviceModelPresets) {
        InvDeviceModelPresets.bindUi({
            selectId: 'invDmPresetSelect',
            applyBtnId: 'invDmPresetApplyBtn',
            saveBtnId: 'invDmPresetSaveBtn',
            deleteBtnId: 'invDmPresetDeleteBtn',
            getModels: getDeviceModelsFromForm,
            applyModels: function (models) {
                var container = document.getElementById('deviceModelsContainer');
                if (!container) return;
                container.innerHTML = '';
                var list = InvDeviceModelPresets.normalizeModels(models);
                if (list.length === 0) {
                    addDeviceModelRow('', '');
                } else {
                    list.forEach(function (dm) {
                        addDeviceModelRow(dm.hersteller || '', dm.modell || '');
                    });
                }
                if (window.InvDeviceModelAutoRow) InvDeviceModelAutoRow.ensure(container, addDeviceModelRow);
            }
        });
    }
    if (window.InvDeviceModelAutoRow) {
        InvDeviceModelAutoRow.bind('deviceModelsContainer', addDeviceModelRow);
    }
    var invMobileScrollWrap = document.getElementById('invMobileScrollWrap');
    if (invMobileScrollWrap) {
        /** Haptik in derselben User-Aktion wie die Buchung (wie Aufgaben-Checkbox), nicht nur bei touchstart. */
        var invHapticLastMs = 0;
        function invFireStockHaptic() {
            var now = Date.now();
            if (now - invHapticLastMs < 120) return;
            invHapticLastMs = now;
            if (typeof window.hapticLightTap === 'function') {
                window.hapticLightTap();
            } else {
                try {
                    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
                        navigator.vibrate(40);
                    }
                } catch (err) { /* noop */ }
            }
        }
        invMobileScrollWrap.addEventListener('click', function(e) {
            var btn = e.target.closest('.inv-stock-adjust');
            if (!btn) return;
            invFireStockHaptic();
            e.preventDefault();
            e.stopPropagation();
            var row = btn.closest('.inv-consumable-row');
            if (!row) return;
            var delta = parseInt(btn.getAttribute('data-delta') || '0', 10);
            if (!delta) return;
            adjustStock(delta, row);
        }, true);
    }

    var invMobileSearchInput = document.getElementById('inv-mobile-search');
    if (invMobileSearchInput) {
        invMobileSearchInput.addEventListener('input', function() {
            var desk = document.getElementById('inv-search');
            if (desk) desk.value = invMobileSearchInput.value;
            if (invSearchDebounceTimer) clearTimeout(invSearchDebounceTimer);
            invSearchDebounceTimer = setTimeout(function() {
                invSearchDebounceTimer = null;
                applyInvFilter();
            }, 300);
            if (invSearchSaveTimer) clearTimeout(invSearchSaveTimer);
            invSearchSaveTimer = setTimeout(function() {
                invSearchSaveTimer = null;
                saveInventoryFiltersState();
            }, 500);
        });
        /* iOS „Fertig“ / Entfokussieren: bei leerem Feld Panel schließen */
        invMobileSearchInput.addEventListener('blur', function() {
            window.requestAnimationFrame(function() {
                invCloseMobileSearchIfEmpty();
            });
        });
    }
    (function invBindMobileSearchCloseOnScroll() {
        /* Scroll-basiertes Auto-Close deaktiviert: führte auf einigen Geräten zu sofortigem Schließen nach Pull-Open. */
    })();

    var invMobileShelvesBtn = document.getElementById('inv-mobile-shelves-btn');
    if (invMobileShelvesBtn && typeof openShelvesModal === 'function') {
        invMobileShelvesBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openShelvesModal();
        });
    }

    var searchToggleBtn = document.getElementById('inv-search-toggle-btn');
    var searchWrapper = document.getElementById('inv-search-wrapper');
    var searchInput = document.getElementById('inv-search');
    var searchFieldContainer = searchWrapper ? searchWrapper.querySelector('.search-field-container') : null;
    var searchForm = document.getElementById('inv-search-form');
    var searchCloseBtn = document.getElementById('inv-search-close-btn');
    var searchCloseIconSearch = searchCloseBtn ? searchCloseBtn.querySelector('.search-close-icon.search-icon') : null;
    var searchCloseIconX = searchCloseBtn ? searchCloseBtn.querySelector('.search-close-icon.x-icon') : null;

    function setInvCloseBtnIconToSearch() {
        if (searchCloseIconSearch) searchCloseIconSearch.classList.remove('hidden');
        if (searchCloseIconX) searchCloseIconX.classList.add('hidden');
    }
    function setInvCloseBtnIconToX() {
        if (searchCloseIconSearch) searchCloseIconSearch.classList.add('hidden');
        if (searchCloseIconX) searchCloseIconX.classList.remove('hidden');
    }
    function updateInvSearchActiveState() {
        if (searchWrapper && searchInput) searchWrapper.classList.toggle('search-active', (searchInput.value || '').trim().length > 0);
    }

    if (searchToggleBtn && searchWrapper && searchFieldContainer) {
        function collapseInvSearchField() {
            setInvCloseBtnIconToSearch();
            if (searchInput && !(searchInput.value || '').trim()) {
                setTimeout(function() {
                    if (!searchWrapper.classList.contains('search-expanded')) searchInput.blur();
                }, 260);
            }
            var startWidth = searchFieldContainer.offsetWidth;
            searchFieldContainer.style.width = startWidth + 'px';
            searchFieldContainer.style.maxWidth = 'none';
            searchWrapper.classList.add('search-closing');
            searchWrapper.classList.remove('search-expanded');
            if (searchForm) searchForm.classList.remove('search-expanded');
            requestAnimationFrame(function() {
                requestAnimationFrame(function() { searchFieldContainer.style.width = '0'; });
            });
            var onCloseDone = function(e) {
                if (e.propertyName !== 'width') return;
                searchFieldContainer.removeEventListener('transitionend', onCloseDone);
                searchWrapper.classList.remove('search-closing');
                searchFieldContainer.style.width = '';
                searchFieldContainer.style.maxWidth = '';
            };
            searchFieldContainer.addEventListener('transitionend', onCloseDone);
        }
        function expandInvSearchField() {
            searchWrapper.classList.add('search-expanded');
            if (searchForm) searchForm.classList.add('search-expanded');
            setInvCloseBtnIconToSearch();
            setTimeout(function() { if (searchInput) searchInput.focus(); }, 150);
            var onExpandDone = function() {
                searchFieldContainer.removeEventListener('transitionend', onExpandDone);
                setInvCloseBtnIconToX();
            };
            searchFieldContainer.addEventListener('transitionend', onExpandDone);
        }
        searchToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (searchWrapper.classList.contains('search-expanded')) collapseInvSearchField();
            else expandInvSearchField();
        });
        if (searchCloseBtn) {
            searchCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (searchWrapper.classList.contains('search-expanded')) {
                    if (searchInput) {
                        searchInput.value = '';
                        var msClr = document.getElementById('inv-mobile-search');
                        if (msClr) msClr.value = '';
                        // search-active Klasse explizit entfernen
                        if (searchWrapper) searchWrapper.classList.remove('search-active');
                        updateInvSearchActiveState();
                        saveInventoryFiltersState();
                        applyInvFilter();
                    }
                    collapseInvSearchField();
                }
            });
        }
        if (searchInput) {
            searchInput.addEventListener('blur', function() {
                setTimeout(function() {
                    var active = document.activeElement;
                    if (!(searchInput.value || '').trim() && active !== searchToggleBtn && !(active && active.closest && active.closest('#inv-search-wrapper'))) {
                        collapseInvSearchField();
                    }
                }, 200);
            });
        }
        // Suche beim Laden wiederherstellen (wenn Wert vorhanden)
        if (searchInput && (searchInput.value || '').trim()) {
            searchWrapper.classList.add('search-expanded');
            if (searchForm) searchForm.classList.add('search-expanded');
            setInvCloseBtnIconToX();
        }
        updateInvSearchActiveState();
    }
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            updateInvSearchActiveState();
            var msSync = document.getElementById('inv-mobile-search');
            if (msSync && document.activeElement !== msSync) msSync.value = searchInput.value || '';
            if (invSearchDebounceTimer) clearTimeout(invSearchDebounceTimer);
            invSearchDebounceTimer = setTimeout(function() {
                invSearchDebounceTimer = null;
                applyInvFilter();
            }, 300);
            // Filter-Status speichern (mit Debounce)
            if (invSearchSaveTimer) clearTimeout(invSearchSaveTimer);
            invSearchSaveTimer = setTimeout(function() {
                invSearchSaveTimer = null;
                saveInventoryFiltersState();
            }, 500);
        });
    }
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (searchInput) searchInput.blur();
            applyInvFilter();
        });
    }

    function positionFilterDropdown(menuEl, buttonEl) {
        if (!menuEl || !buttonEl || menuEl.classList.contains('hidden')) return;
        var rect = buttonEl.getBoundingClientRect();
        var vh = window.innerHeight;
        var vw = window.innerWidth;
        var gap = 4;
        var maxMenuH = 320;
        var spaceBelow = vh - rect.bottom - gap;
        var spaceAbove = rect.top - gap;
        var openAbove = spaceBelow < maxMenuH && spaceAbove > spaceBelow;
        menuEl.style.position = 'fixed';
        menuEl.style.marginTop = '0';
        menuEl.style.marginBottom = '0';
        var menuW = rect.width;
        menuEl.style.width = menuW + 'px';
        menuEl.style.minWidth = '';
        menuEl.style.maxWidth = '';
        var left = rect.left;
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
    function openFilterDropdownAsPortal(menuEl, buttonEl) {
        if (!menuEl || !buttonEl) return;
        if (!menuEl._dropdownRestore) {
            menuEl._dropdownRestore = { parent: menuEl.parentNode, nextSibling: menuEl.nextSibling };
            document.body.appendChild(menuEl);
        }
        menuEl.classList.remove('hidden');
        setTimeout(function() { positionFilterDropdown(menuEl, buttonEl); }, 10);
    }
    function closeFilterDropdownPortal(menuEl, containerEl) {
        if (!menuEl) return;
        menuEl.classList.add('hidden');
        if (menuEl._dropdownRestore) {
            var parent = menuEl._dropdownRestore.parent;
            var nextSibling = menuEl._dropdownRestore.nextSibling;
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

    var invCategoryFilterButton = document.getElementById('inv-category-filter-button');
    var invCategoryFilterMenu = document.getElementById('inv-category-filter-menu');
    var invCategoryFilterText = document.getElementById('inv-category-filter-text');
    var invCategoryFilterInput = document.getElementById('inv-category-filter');
    var invCategoryFilterContainer = document.getElementById('inv-category-filter-container');

    var invManufacturerFilterButton = document.getElementById('inv-manufacturer-filter-button');
    var invManufacturerFilterMenu = document.getElementById('inv-manufacturer-filter-menu');
    var invManufacturerFilterText = document.getElementById('inv-manufacturer-filter-text');
    var invManufacturerFilterInput = document.getElementById('inv-manufacturer-filter');
    var invManufacturerFilterContainer = document.getElementById('inv-manufacturer-filter-container');

    var invModelFilterButton = document.getElementById('inv-model-filter-button');
    var invModelFilterMenu = document.getElementById('inv-model-filter-menu');
    var invModelFilterText = document.getElementById('inv-model-filter-text');
    var invModelFilterInput = document.getElementById('inv-model-filter');
    var invModelFilterContainer = document.getElementById('inv-model-filter-container');

    var invStatusFilterButton = document.getElementById('inv-status-filter-button');
    var invStatusFilterMenu = document.getElementById('inv-status-filter-menu');
    var invStatusFilterText = document.getElementById('inv-status-filter-text');
    var invStatusFilterInput = document.getElementById('inv-status-filter');
    var invStatusFilterContainer = document.getElementById('inv-status-filter-container');

    function closeAllInvFilterDropdowns(exceptMenu) {
        if (invCategoryFilterMenu && invCategoryFilterMenu !== exceptMenu && !invCategoryFilterMenu.classList.contains('hidden')) {
            closeFilterDropdownPortal(invCategoryFilterMenu, invCategoryFilterContainer);
        }
        if (invManufacturerFilterMenu && invManufacturerFilterMenu !== exceptMenu && !invManufacturerFilterMenu.classList.contains('hidden')) {
            closeFilterDropdownPortal(invManufacturerFilterMenu, invManufacturerFilterContainer);
        }
        if (invModelFilterMenu && invModelFilterMenu !== exceptMenu && !invModelFilterMenu.classList.contains('hidden')) {
            closeFilterDropdownPortal(invModelFilterMenu, invModelFilterContainer);
        }
        if (invStatusFilterMenu && invStatusFilterMenu !== exceptMenu && !invStatusFilterMenu.classList.contains('hidden')) {
            closeFilterDropdownPortal(invStatusFilterMenu, invStatusFilterContainer);
        }
    }

    if (invCategoryFilterButton && invCategoryFilterMenu && invCategoryFilterContainer) {
        function positionInvCategoryDropdown() {
            positionFilterDropdown(invCategoryFilterMenu, invCategoryFilterButton);
        }
        invCategoryFilterButton.addEventListener('click', function(e) {
            e.stopPropagation();
            var isHidden = invCategoryFilterMenu.classList.contains('hidden');
            if (isHidden) {
                closeAllInvFilterDropdowns(invCategoryFilterMenu);
                openFilterDropdownAsPortal(invCategoryFilterMenu, invCategoryFilterButton);
            } else {
                closeFilterDropdownPortal(invCategoryFilterMenu, invCategoryFilterContainer);
            }
        });
        window.addEventListener('scroll', positionInvCategoryDropdown, true);
        window.addEventListener('resize', positionInvCategoryDropdown);
        document.getElementById('inv-category-filter-menu-inner').addEventListener('click', function(e) {
            var opt = e.target.closest('.inv-category-option');
            if (!opt) return;
            e.stopPropagation();
            var id = opt.getAttribute('data-category-id') || '';
            var name = opt.getAttribute('data-category-name') || 'Alle Kategorien';
            if (invCategoryFilterInput) invCategoryFilterInput.value = id;
            if (invCategoryFilterText) invCategoryFilterText.textContent = name;
            closeFilterDropdownPortal(invCategoryFilterMenu, invCategoryFilterContainer);
            updateInvCategoryFilterButtonState();
            saveInventoryFiltersState();
            applyInvFilter();
            syncInvMobileFilterFromDesktop();
        });
        document.addEventListener('click', function(e) {
            if (isClickOutsideDropdown(invCategoryFilterContainer, invCategoryFilterMenu, e.target)) {
                closeFilterDropdownPortal(invCategoryFilterMenu, invCategoryFilterContainer);
            }
        });
    }

    if (invManufacturerFilterButton && invManufacturerFilterMenu && invManufacturerFilterContainer) {
        function positionInvManufacturerDropdown() {
            positionFilterDropdown(invManufacturerFilterMenu, invManufacturerFilterButton);
        }
        invManufacturerFilterButton.addEventListener('click', function(e) {
            e.stopPropagation();
            var isHidden = invManufacturerFilterMenu.classList.contains('hidden');
            if (isHidden) {
                closeAllInvFilterDropdowns(invManufacturerFilterMenu);
                openFilterDropdownAsPortal(invManufacturerFilterMenu, invManufacturerFilterButton);
            } else {
                closeFilterDropdownPortal(invManufacturerFilterMenu, invManufacturerFilterContainer);
            }
        });
        window.addEventListener('scroll', positionInvManufacturerDropdown, true);
        window.addEventListener('resize', positionInvManufacturerDropdown);
        document.getElementById('inv-manufacturer-filter-menu-inner').addEventListener('click', function(e) {
            var opt = e.target.closest('.inv-manufacturer-option');
            if (!opt) return;
            e.stopPropagation();
            var manufacturer = opt.getAttribute('data-manufacturer') || '';
            var name = opt.getAttribute('data-manufacturer-name') || 'Alle Hersteller';
            if (invManufacturerFilterInput) invManufacturerFilterInput.value = manufacturer;
            if (invManufacturerFilterText) invManufacturerFilterText.textContent = name;
            closeFilterDropdownPortal(invManufacturerFilterMenu, invManufacturerFilterContainer);
            updateInvManufacturerFilterButtonState();
            
            var invModelFilterContainer = document.getElementById('inv-model-filter-container');
            // Modell-Filter anzeigen/verstecken basierend auf Hersteller-Auswahl
            if (manufacturer) {
                // Hersteller ausgewählt: Modell-Filter anzeigen
                if (invModelFilterContainer) invModelFilterContainer.classList.remove('hidden');
                // Modell-Filter-Menü aktualisieren, da sich die verfügbaren Modelle geändert haben
                fillInvModelFilterMenu();
            } else {
                // Hersteller zurückgesetzt: Modell-Filter verstecken und zurücksetzen
                if (invModelFilterContainer) invModelFilterContainer.classList.add('hidden');
                var invModelFilterInput = document.getElementById('inv-model-filter');
                var invModelFilterText = document.getElementById('inv-model-filter-text');
                if (invModelFilterInput) invModelFilterInput.value = '';
                if (invModelFilterText) invModelFilterText.textContent = 'Alle Modelle';
                updateInvModelFilterButtonState();
            }
            saveInventoryFiltersState();
            applyInvFilter();
            syncInvMobileFilterFromDesktop();
        });
        document.addEventListener('click', function(e) {
            if (isClickOutsideDropdown(invManufacturerFilterContainer, invManufacturerFilterMenu, e.target)) {
                closeFilterDropdownPortal(invManufacturerFilterMenu, invManufacturerFilterContainer);
            }
        });
    }

    if (invModelFilterButton && invModelFilterMenu && invModelFilterContainer) {
        function positionInvModelDropdown() {
            positionFilterDropdown(invModelFilterMenu, invModelFilterButton);
        }
        invModelFilterButton.addEventListener('click', function(e) {
            e.stopPropagation();
            var isHidden = invModelFilterMenu.classList.contains('hidden');
            if (isHidden) {
                closeAllInvFilterDropdowns(invModelFilterMenu);
                openFilterDropdownAsPortal(invModelFilterMenu, invModelFilterButton);
            } else {
                closeFilterDropdownPortal(invModelFilterMenu, invModelFilterContainer);
            }
        });
        window.addEventListener('scroll', positionInvModelDropdown, true);
        window.addEventListener('resize', positionInvModelDropdown);
        document.getElementById('inv-model-filter-menu-inner').addEventListener('click', function(e) {
            var opt = e.target.closest('.inv-model-option');
            if (!opt) return;
            e.stopPropagation();
            var model = opt.getAttribute('data-model') || '';
            var name = opt.getAttribute('data-model-name') || 'Alle Modelle';
            if (invModelFilterInput) invModelFilterInput.value = model;
            if (invModelFilterText) invModelFilterText.textContent = name;
            closeFilterDropdownPortal(invModelFilterMenu, invModelFilterContainer);
            updateInvModelFilterButtonState();
            saveInventoryFiltersState();
            applyInvFilter();
            syncInvMobileFilterFromDesktop();
        });
        document.addEventListener('click', function(e) {
            if (isClickOutsideDropdown(invModelFilterContainer, invModelFilterMenu, e.target)) {
                closeFilterDropdownPortal(invModelFilterMenu, invModelFilterContainer);
            }
        });
    }

    if (invStatusFilterButton && invStatusFilterMenu && invStatusFilterContainer) {
        function positionInvStatusDropdown() {
            positionFilterDropdown(invStatusFilterMenu, invStatusFilterButton);
        }
        invStatusFilterButton.addEventListener('click', function(e) {
            e.stopPropagation();
            var isHidden = invStatusFilterMenu.classList.contains('hidden');
            if (isHidden) {
                closeAllInvFilterDropdowns(invStatusFilterMenu);
                openFilterDropdownAsPortal(invStatusFilterMenu, invStatusFilterButton);
            } else {
                closeFilterDropdownPortal(invStatusFilterMenu, invStatusFilterContainer);
            }
        });
        window.addEventListener('scroll', positionInvStatusDropdown, true);
        window.addEventListener('resize', positionInvStatusDropdown);
        document.getElementById('inv-status-filter-menu-inner').addEventListener('click', function(e) {
            var opt = e.target.closest('.inv-status-option');
            if (!opt) return;
            e.stopPropagation();
            var status = opt.getAttribute('data-status') || '';
            var name = opt.getAttribute('data-status-name') || 'Alle Status';
            if (invStatusFilterInput) invStatusFilterInput.value = status;
            if (invStatusFilterText) invStatusFilterText.textContent = name;
            closeFilterDropdownPortal(invStatusFilterMenu, invStatusFilterContainer);
            updateInvStatusFilterButtonState();
            saveInventoryFiltersState();
            applyInvFilter();
            syncInvMobileFilterFromDesktop();
        });
        document.addEventListener('click', function(e) {
            if (isClickOutsideDropdown(invStatusFilterContainer, invStatusFilterMenu, e.target)) {
                closeFilterDropdownPortal(invStatusFilterMenu, invStatusFilterContainer);
            }
        });
    }

    var invMobileStatusSel = document.getElementById('inv-mobile-status-select');
    if (invMobileStatusSel) {
        invMobileStatusSel.addEventListener('change', function() {
            var v = invMobileStatusSel.value || '';
            var h = document.getElementById('inv-status-filter');
            var t = document.getElementById('inv-status-filter-text');
            if (h) h.value = v;
            if (t) t.textContent = invStatusFilterLabel(v);
            updateInvStatusFilterButtonState();
            saveInventoryFiltersState();
            applyInvFilter();
        });
    }
    var invMobileCategorySel = document.getElementById('inv-mobile-category-select');
    if (invMobileCategorySel) {
        invMobileCategorySel.addEventListener('change', function() {
            var v = invMobileCategorySel.value || '';
            var invCategoryFilterInput = document.getElementById('inv-category-filter');
            var invCategoryFilterText = document.getElementById('inv-category-filter-text');
            if (invCategoryFilterInput) invCategoryFilterInput.value = v;
            var name = 'Alle Kategorien';
            if (v) {
                var cat = invCategories.find(function(c) { return String(c.id) === String(v); });
                if (cat) name = cat.name;
            }
            if (invCategoryFilterText) invCategoryFilterText.textContent = name;
            updateInvCategoryFilterButtonState();
            saveInventoryFiltersState();
            applyInvFilter();
        });
    }
    var invMobileManufacturerSel = document.getElementById('inv-mobile-manufacturer-select');
    if (invMobileManufacturerSel) {
        invMobileManufacturerSel.addEventListener('change', function() {
            var manufacturer = invMobileManufacturerSel.value || '';
            var invManufacturerFilterInput = document.getElementById('inv-manufacturer-filter');
            var invManufacturerFilterText = document.getElementById('inv-manufacturer-filter-text');
            var invModelFilterContainer = document.getElementById('inv-model-filter-container');
            if (invManufacturerFilterInput) invManufacturerFilterInput.value = manufacturer;
            if (invManufacturerFilterText) invManufacturerFilterText.textContent = manufacturer ? manufacturer : 'Alle Hersteller';
            if (manufacturer) {
                if (invModelFilterContainer) invModelFilterContainer.classList.remove('hidden');
                fillInvModelFilterMenu();
            } else {
                if (invModelFilterContainer) invModelFilterContainer.classList.add('hidden');
                var invModelFilterInput = document.getElementById('inv-model-filter');
                var invModelFilterText = document.getElementById('inv-model-filter-text');
                if (invModelFilterInput) invModelFilterInput.value = '';
                if (invModelFilterText) invModelFilterText.textContent = 'Alle Modelle';
                updateInvModelFilterButtonState();
            }
            fillInvMobileModelSelect();
            updateInvMobileModelRowVisibility();
            updateInvManufacturerFilterButtonState();
            saveInventoryFiltersState();
            applyInvFilter();
        });
    }
    var invMobileModelSel = document.getElementById('inv-mobile-model-select');
    if (invMobileModelSel) {
        invMobileModelSel.addEventListener('change', function() {
            var model = invMobileModelSel.value || '';
            var invModelFilterInput = document.getElementById('inv-model-filter');
            var invModelFilterText = document.getElementById('inv-model-filter-text');
            if (invModelFilterInput) invModelFilterInput.value = model;
            if (invModelFilterText) invModelFilterText.textContent = model ? model : 'Alle Modelle';
            updateInvModelFilterButtonState();
            saveInventoryFiltersState();
            applyInvFilter();
        });
    }

    var navMobileFilterToggleBtn = document.getElementById('navMobileFilterToggleBtn');
    var navMobileFilterTitleEl = document.querySelector('[data-nav-mobile-filter-title]');
    var mobileFilterSheet = document.getElementById('mobileFilterSheet');
    var mobileFilterSheetBackdrop = document.getElementById('mobileFilterSheetBackdrop');
    var mobileFilterSheetPanel = document.getElementById('mobileFilterSheetPanel');
    var mainNavEl = document.getElementById('main-nav');
    var mobileFilterSheetCloseAnimCleanup = null;
    var mobileFilterSheetClosingAnimated = false;
    var invMobileFilterSavedMainScrollY = 0;
    function lockInvMobileFilterBackgroundScroll() {
        var mc = document.getElementById('main-content');
        invMobileFilterSavedMainScrollY = mc ? mc.scrollTop : 0;
        document.body.classList.add('inv-mobile-filter-sheet-open');
    }
    function unlockInvMobileFilterBackgroundScroll() {
        document.body.classList.remove('inv-mobile-filter-sheet-open');
        var mc = document.getElementById('main-content');
        if (mc) {
            mc.scrollTop = invMobileFilterSavedMainScrollY;
        }
    }
    function finishCloseInvMobileFilterSheet() {
        unlockInvMobileFilterBackgroundScroll();
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
    function openInvMobileFilterSheet() {
        if (!mobileFilterSheet || !mobileFilterSheetPanel || !mobileFilterSheetBackdrop) return;
        if (mobileFilterSheetCloseAnimCleanup) {
            mobileFilterSheetCloseAnimCleanup();
            mobileFilterSheetCloseAnimCleanup = null;
        }
        mobileFilterSheetClosingAnimated = false;
        syncInvMobileFilterFromDesktop();
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
        lockInvMobileFilterBackgroundScroll();
    }
    function closeInvMobileFilterSheet(animated) {
        if (!mobileFilterSheet || !mobileFilterSheetPanel || !mobileFilterSheetBackdrop) return;
        if (mobileFilterSheet.getAttribute('aria-hidden') === 'true') return;
        if (!animated) {
            finishCloseInvMobileFilterSheet();
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
            finishCloseInvMobileFilterSheet();
        }
        var fallbackMs = 380;
        var tid = setTimeout(function() { onTransitionEnd(null); }, fallbackMs);
        mobileFilterSheetCloseAnimCleanup = function() {
            mobileFilterSheetPanel.removeEventListener('transitionend', onTransitionEnd);
            clearTimeout(tid);
        };
        mobileFilterSheetPanel.addEventListener('transitionend', onTransitionEnd);
    }
    (function bindInvMobileFilterSheetSwipe() {
        var handle = document.getElementById('mobileFilterSheetHandle');
        var scrollEl = document.getElementById('mobileFilterSheetScroll');
        var panel = document.getElementById('mobileFilterSheetPanel');
        if (!panel) return;
        function resetPanelTransform() {
            panel.style.transition = '';
            panel.style.transform = '';
        }
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
                    closeInvMobileFilterSheet(true);
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
        var backdropEl = document.getElementById('mobileFilterSheetBackdrop');
        if (handle) bindVerticalDismiss(handle);
        if (scrollEl) bindVerticalDismiss(scrollEl, { requireScrollTopZero: true });
        if (backdropEl) bindVerticalDismiss(backdropEl);
    })();
    function toggleInvMobileFilterSheetFromNav(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (mobileFilterSheet && mobileFilterSheet.getAttribute('aria-hidden') === 'false') closeInvMobileFilterSheet(true);
        else openInvMobileFilterSheet();
    }
    if (navMobileFilterToggleBtn) navMobileFilterToggleBtn.addEventListener('click', toggleInvMobileFilterSheetFromNav);
    if (navMobileFilterTitleEl) {
        navMobileFilterTitleEl.addEventListener('click', toggleInvMobileFilterSheetFromNav);
        navMobileFilterTitleEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                toggleInvMobileFilterSheetFromNav(e);
            }
        });
    }
    if (mobileFilterSheetBackdrop) mobileFilterSheetBackdrop.addEventListener('click', closeInvMobileFilterSheet);

    (function bindInvMobileFilterPullDownOpen() {
        var wrap = document.getElementById('invMobileScrollWrap');
        if (!wrap) return;
        var mq = window.matchMedia('(max-width: 1023px)');
        var THRESH = 76;
        var TOP_TOLERANCE = 20;
        var navMobileSearchBtn = document.getElementById('navMobileInvSearchBtn');
        var startY = 0;
        var startX = 0;
        var tracking = false;
        var startAtTop = false;
        var pullReady = false;
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
            invIgnoreNavSearchClickUntil = Date.now() + 500;
            invSetMobileSearchPanelOpen(true, true);
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
            var dash = document.getElementById('inv-mobile-dashboard');
            var panelOpen = !!(dash && dash.classList.contains('inv-mobile-search-panel-open'));
            var tClose = e.changedTouches && e.changedTouches[0];
            if (panelOpen && invMobileSearchIsEmpty() && tClose) {
                var dyClose = tClose.clientY - startY;
                var dxClose = tClose.clientX - startX;
                if (dyClose <= -THRESH && Math.abs(dxClose) * 1.25 < Math.abs(dyClose)) {
                    invSetMobileSearchPanelOpen(false, false);
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
    (function bindInvMobileFilterSwipeUpClose() {
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
            closeInvMobileFilterSheet(true);
        }, { passive: true, capture: true });
        document.addEventListener('touchcancel', function() {
            tracking = false;
        }, { passive: true, capture: true });
    })();

    var shelvesBtn = document.getElementById('inv-shelves-btn');
    if (shelvesBtn) shelvesBtn.addEventListener('click', openShelvesModal);
    var shelvesModalOverlay = document.getElementById('shelvesModalOverlay');
    if (shelvesModalOverlay) shelvesModalOverlay.addEventListener('click', closeShelvesModal);
    var shelvesAddBtn = document.getElementById('shelvesAddBtn');
    if (shelvesAddBtn) {
        shelvesAddBtn.addEventListener('click', function() {
            document.getElementById('shelfEditId').value = '';
            document.getElementById('shelfName').value = '';
            document.getElementById('shelfBeschreibung').value = '';
            document.getElementById('shelfFormContainer').classList.remove('hidden');
        });
    }
    var shelfFormSave = document.getElementById('shelfFormSave');
    if (shelfFormSave) shelfFormSave.addEventListener('click', saveShelfFromForm);
    var shelfFormCancel = document.getElementById('shelfFormCancel');
    if (shelfFormCancel) {
        shelfFormCancel.addEventListener('click', function() {
            document.getElementById('shelfFormContainer').classList.add('hidden');
        });
    }

    // Context-Menü für Lager-Tabelle
    var invContextMenu = document.getElementById('inv-context-menu');
    var invContextOpenNewTab = document.getElementById('inv-context-open-new-tab');
    var invContextNachbestellen = document.getElementById('inv-context-nachbestellen');

    function getCurrentContextRow() {
        return window.invContextData && window.invContextData.currentRow ? window.invContextData.currentRow : null;
    }

    function hideInvContextMenu() {
        if (invContextMenu) invContextMenu.classList.add('hidden');
        var qtyRow = document.getElementById('inv-context-quantity-row');
        if (qtyRow) qtyRow.classList.add('hidden');
        var btnEinlagern = document.getElementById('inv-context-mehrere-einlagern');
        var btnAuslagern = document.getElementById('inv-context-mehrere-auslagern');
        if (btnEinlagern) btnEinlagern.classList.remove('inv-context-item-hidden');
        if (btnAuslagern) btnAuslagern.classList.remove('inv-context-item-hidden');
        if (window.invContextData) {
            window.invContextData.currentRow = null;
            window.invContextData.pendingQtyAction = null;
        }
    }

    // Context-Menü schließen bei Klick außerhalb
    document.addEventListener('click', function(e) {
        if (invContextMenu && !invContextMenu.contains(e.target)) {
            hideInvContextMenu();
        }
    });

    // Context-Menü schließen bei ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideInvContextMenu();
        }
    });

    // Im neuen Tab öffnen
    if (invContextOpenNewTab) {
        invContextOpenNewTab.addEventListener('click', function() {
            var row = getCurrentContextRow();
            if (row) {
                var url = row.getAttribute('data-detail-url');
                if (url) {
                    window.open(url, '_blank');
                }
            }
            hideInvContextMenu();
        });
    }

    // Nachbestellen (nur wenn keine offene Bestellung für diesen Artikel)
    if (invContextNachbestellen) {
        invContextNachbestellen.addEventListener('click', function() {
            if (invContextNachbestellen.classList.contains('inv-context-item-disabled')) return;
            var row = getCurrentContextRow();
            if (row) {
                createOrder(row);
            }
            hideInvContextMenu();
        });
    }

    // Menge aus Kontextmenü lesen
    function getInvContextQuantity() {
        var el = document.getElementById('inv-context-quantity');
        if (!el) return 0;
        var n = parseInt(el.value.trim(), 10);
        return (n > 0) ? n : 0;
    }

    // Mengenfeld unter dem gewählten Eintrag anzeigen; gewählten Punkt ausblenden, Button anpassen
    function showInvContextQuantityRow(action) {
        var row = getCurrentContextRow();
        if (!row) return;
        if (!window.invContextData) window.invContextData = {};
        window.invContextData.pendingQtyAction = action;
        var btnEinlagern = document.getElementById('inv-context-mehrere-einlagern');
        var btnAuslagern = document.getElementById('inv-context-mehrere-auslagern');
        if (action === 'einlagern') {
            if (btnEinlagern) btnEinlagern.classList.add('inv-context-item-hidden');
            if (btnAuslagern) btnAuslagern.classList.remove('inv-context-item-hidden');
        } else {
            if (btnAuslagern) btnAuslagern.classList.add('inv-context-item-hidden');
            if (btnEinlagern) btnEinlagern.classList.remove('inv-context-item-hidden');
        }
        var okText = document.getElementById('inv-context-quantity-ok-text');
        var okIconEin = document.getElementById('inv-context-qty-ok-icon-einlagern');
        var okIconAus = document.getElementById('inv-context-qty-ok-icon-auslagern');
        if (okText) okText.textContent = action === 'einlagern' ? 'Einlagern' : 'Auslagern';
        if (okIconEin) {
            if (action === 'einlagern') { okIconEin.classList.remove('hidden'); okIconEin.classList.add('inline-block'); }
            else { okIconEin.classList.add('hidden'); okIconEin.classList.remove('inline-block'); }
        }
        if (okIconAus) {
            if (action === 'auslagern') { okIconAus.classList.remove('hidden'); okIconAus.classList.add('inline-block'); }
            else { okIconAus.classList.add('hidden'); okIconAus.classList.remove('inline-block'); }
        }
        var qtyRow = document.getElementById('inv-context-quantity-row');
        var slot = document.getElementById(action === 'einlagern' ? 'inv-context-qty-slot-einlagern' : 'inv-context-qty-slot-auslagern');
        if (qtyRow && slot && qtyRow.parentNode !== slot) {
            slot.appendChild(qtyRow);
        }
        if (qtyRow) qtyRow.classList.remove('hidden');
        var qtyInput = document.getElementById('inv-context-quantity');
        if (qtyInput) {
            qtyInput.value = '1';
            qtyInput.focus();
            qtyInput.select();
        }
    }

    function applyInvContextQuantity() {
        var row = getCurrentContextRow();
        var action = window.invContextData && window.invContextData.pendingQtyAction;
        if (!row || !action) return;
        var n = getInvContextQuantity();
        if (!n) {
            if (typeof showToast === 'function') showToast('Bitte eine gültige Anzahl eingeben (mind. 1)', 'warning');
            return;
        }
        if (typeof window.hapticLightTap === 'function') {
            window.hapticLightTap();
        } else {
            try {
                if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
                    navigator.vibrate(40);
                }
            } catch (err) { /* noop */ }
        }
        adjustStock(action === 'einlagern' ? n : -n, row);
        hideInvContextMenu();
    }

    var invContextQuantityOk = document.getElementById('inv-context-quantity-ok');
    if (invContextQuantityOk) invContextQuantityOk.addEventListener('click', function() { applyInvContextQuantity(); });
    var invContextQuantityInput = document.getElementById('inv-context-quantity');
    if (invContextQuantityInput) {
        invContextQuantityInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); applyInvContextQuantity(); }
        });
    }

    // Mehrere einlagern: erst Mengenfeld anzeigen, Bestätigung per OK/Enter
    var invContextMehrereEinlagern = document.getElementById('inv-context-mehrere-einlagern');
    if (invContextMehrereEinlagern) {
        invContextMehrereEinlagern.addEventListener('click', function() {
            showInvContextQuantityRow('einlagern');
        });
    }

    // Mehrere auslagern: erst Mengenfeld anzeigen, Bestätigung per OK/Enter
    var invContextMehrereAuslagern = document.getElementById('inv-context-mehrere-auslagern');
    if (invContextMehrereAuslagern) {
        invContextMehrereAuslagern.addEventListener('click', function() {
            showInvContextQuantityRow('auslagern');
        });
    }

    // Bearbeiten
    var invContextBearbeiten = document.getElementById('inv-context-bearbeiten');
    if (invContextBearbeiten) {
        invContextBearbeiten.addEventListener('click', function() {
            var row = getCurrentContextRow();
            if (row) {
                var id = row.getAttribute('data-consumable-id');
                if (id) {
                    var url = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'inventory/edit.php?id=' + encodeURIComponent(id);
                    window.location.href = url;
                }
            }
            hideInvContextMenu();
        });
    }

    // Löschen (Soft-Delete)
    var invContextLoeschen = document.getElementById('inv-context-loeschen');
    if (invContextLoeschen) {
        invContextLoeschen.addEventListener('click', function() {
            var row = getCurrentContextRow();
            if (!row) { hideInvContextMenu(); return; }
            var id = row.getAttribute('data-consumable-id');
            var label = (row.querySelector('td:nth-child(1) .font-medium') || row.querySelector('td:nth-child(1)')) || {};
            var bezeichnung = (label.textContent || '').trim();
            if (!bezeichnung && row.querySelector) {
                var h3 = row.querySelector('h3');
                if (h3) bezeichnung = (h3.textContent || '').trim();
            }
            if (!id) { hideInvContextMenu(); return; }
            if (!confirm('Verbrauchsmaterial wirklich löschen?\n\n' + (bezeichnung.trim() || 'Artikel') + '\n\nDer Eintrag wird ausgeblendet (Soft-Delete) und kann bei Bedarf in der Datenbank wiederhergestellt werden.')) {
                hideInvContextMenu();
                return;
            }
            fetch(consumablesApiUrl + '?id=' + encodeURIComponent(id), { method: 'DELETE' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        if (typeof showToast === 'function') showToast('Verbrauchsmaterial gelöscht', 'success');
                        loadConsumables();
                    } else {
                        if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Löschen', 'error');
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    if (typeof showToast === 'function') showToast('Netzwerkfehler', 'error');
                });
            hideInvContextMenu();
        });
    }

    function createOrder(row) {
        if (!row) return;
        
        var consumableId = row.getAttribute('data-consumable-id');
        
        if (!consumableId) {
            if (typeof showToast === 'function') showToast('Fehler: Artikel-ID nicht gefunden', 'error');
            return;
        }

        // Lade aktuellen Artikel
        fetch(consumablesApiUrl + '?id=' + consumableId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.consumable) {
                    if (typeof showToast === 'function') showToast('Fehler: Artikel nicht gefunden', 'error');
                    return;
                }
                
                var consumable = data.consumable;
                var bezeichnung = consumable.bezeichnung || 'Verbrauchsmaterial';
                var artikelnummer = consumable.artikelnummer || '';
                var beschreibung = 'Nachbestellung: ' + bezeichnung;
                if (artikelnummer) {
                    beschreibung += ' (Art. ' + artikelnummer + ')';
                }

                // Notiz hinzufügen, dass die Bestellung manuell über das Lager erstellt wurde
                var notizen = 'Diese Bestellung wurde manuell über das Lager erstellt.';
                var logBeschreibung = 'Diese Bestellung wurde manuell über das Lager erstellt.';

                // Bestellung über Orders API erstellen
                var ordersApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + 'orders/api/orders.php';
                
                return fetch(ordersApiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bestellnummer: '',
                        beschreibung: beschreibung,
                        notizen: notizen,
                        log_beschreibung: logBeschreibung,
                        tracking_nummer: null,
                        tracking_link: null,
                        status: 'Neu',
                        company_id: null,
                        customer_id: null,
                        consumable_id: parseInt(consumableId, 10) || null
                    })
                });
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (typeof showToast === 'function') {
                        showToast('Nachbestellung angelegt', 'success');
                    }
                    invRefreshConsumableInListById(consumableId);
                } else {
                    if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Erstellen der Bestellung', 'error');
                }
            })
            .catch(function(err) {
                console.error('Fehler:', err);
                if (typeof showToast === 'function') showToast('Netzwerkfehler beim Erstellen der Bestellung', 'error');
            });
    }

    window.invSwipeNachbestellenConsumable = function(consumableId) {
        invSwipeResetAllTracks(null);
        var row = document.createElement('div');
        row.setAttribute('data-consumable-id', String(consumableId));
        createOrder(row);
    };
    window.invSwipeDeleteConsumable = function(consumableId) {
        invSwipeResetAllTracks(null);
        var name = '';
        var idStr = String(consumableId);
        for (var i = 0; i < invConsumablesList.length; i++) {
            if (String(invConsumablesList[i].id) === idStr) {
                name = invConsumablesList[i].bezeichnung || '';
                break;
            }
        }
        deleteConsumable(consumableId, name || 'Artikel');
    };

    function adjustStock(delta, row) {
        if (!row) return;

        var code = (row.getAttribute('data-code') || '').trim();
        var consumableId = row.getAttribute('data-consumable-id');
        
        var body = { action: 'adjust_stock', delta: delta };
        if (code) {
            body.code = code;
        } else if (consumableId) {
            body.consumable_id = parseInt(consumableId, 10);
        }
        if (!body.code && !body.consumable_id) {
            if (typeof showToast === 'function') showToast('Fehler: Artikel konnte nicht zugeordnet werden', 'error');
            return;
        }

        fetch(consumablesApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
        .then(function(r) {
            return r.json();
        })
        .then(function(data) {
            if (data.success) {
                var action = delta > 0 ? 'eingelagert' : 'ausgelagert';
                var message = 'Artikel ' + action + '. Neuer Bestand: ' + data.lagerbestand;
                if (data.neu_angelegt) {
                    var q = (data.produkt_quelle || '').trim();
                    var ql = q === 'openfoodfacts' ? 'Open Food Facts' : (q === 'openbeautyfacts' ? 'Open Beauty Facts' : (q === 'openproductsfacts' ? 'Open Products Facts' : (q === 'upcitemdb' ? 'UPCitemdb' : (q === 'fallback' ? 'ohne Produktdaten' : q))));
                    message = 'Neu angelegt' + (data.bezeichnung ? ' („' + data.bezeichnung + '“)' : '') + ' – ' + message;
                    if (ql) {
                        message += ' [Quelle: ' + ql + ']';
                    }
                }
                if (data.lagerort && delta > 0) {
                    message += '\n' + data.lagerort;
                }
                if (data.unter_mindestbestand) {
                    message += '\nUnter Mindestbestand.';
                }
                if (data.bestellt && data.bestellnummer) {
                    message += '\nBestellung erstellt: ' + data.bestellnummer;
                }
                if (typeof showToast === 'function') showToast(message, data.unter_mindestbestand ? 'warning' : 'success');

                refreshInvStockAfterAdjust(row.getAttribute('data-consumable-id'), data, delta);
            } else {
                if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Anpassen des Bestands', 'error');
            }
        })
        .catch(function(err) {
            console.error('Fehler:', err);
            if (typeof showToast === 'function') showToast('Netzwerkfehler beim Anpassen des Bestands', 'error');
        });
    }
});
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php';
