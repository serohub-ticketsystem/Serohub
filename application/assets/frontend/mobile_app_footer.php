<?php
/**
 * Mobile App-Fußleiste (nur < lg): Suche (Sheet), Schnellzugriff, volles Menü (Sheet).
 * Voraussetzung: nach sidebar.php eingebunden (getLinkClasses etc.), nicht im Service-Vollbild.
 */
if (!empty($serviceMobileFullscreen)) {
    return;
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
if (!function_exists('getLinkClasses')) {
    return;
}
$userRoleFooter = $row['rolle'] ?? ($userRole ?? '');
$isAdminOrTechnikerFooter = ($userRoleFooter === 'Admin' || $userRoleFooter === 'Techniker');
$canSeeInventoryFooter = !empty($canSeeInventory);
if (!$canSeeInventoryFooter && isset($pdo) && $pdo instanceof PDO) {
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid > 0) {
        require_once dirname(__DIR__) . '/inventory_permissions.php';
        $canSeeInventoryFooter = inventory_user_can_view_inventory($pdo, $uid);
    }
}
$isNotKundeFooter = ($userRoleFooter !== 'Kunde');
$footerKbCompanyId = '';
if (isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null) {
    $cid = (int) $_SESSION['selected_company_id'];
    if ($cid > 0) {
        $footerKbCompanyId = (string) $cid;
    }
}
?>
<div id="appMobileFooterRoot" class="lg:hidden fixed left-0 right-0 bottom-0 z-[60] pointer-events-none flex justify-center pb-[env(safe-area-inset-bottom,0px)] px-3">
  <nav id="appMobileFooterNav" class="pointer-events-auto flex w-full max-w-md items-center justify-center gap-3" aria-label="Hauptnavigation mobil">
    <button type="button" id="appMobileFooterSearchBtn" class="app-mobile-footer-bubble flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-[0.5px] border-[#ffffff] bg-[#f5fafe] text-gray-800 shadow-[inset_0_1px_0_0_rgba(255,255,255,1),inset_0_3px_14px_-3px_rgba(255,255,255,0.55),inset_0_-1px_0_0_rgba(15,23,42,0.07),0_4px_24px_rgba(15,23,42,0.09)] backdrop-blur-2xl backdrop-saturate-200 transition-transform active:scale-95 dark:border-[0.5px] dark:border-[#ffffff] dark:bg-[#f5fafe] dark:text-primary-200 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.26),inset_0_3px_16px_-3px_rgba(255,255,255,0.1),inset_0_-1px_0_0_rgba(0,0,0,0.55),0_4px_30px_rgba(0,0,0,0.52)]" aria-expanded="false" aria-controls="appMobileSearchSheet" aria-label="Suche öffnen">
      <svg class="h-7 w-7 shrink-0 pointer-events-none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
      </svg>
    </button>

    <div class="app-mobile-footer-pill flex h-14 min-w-0 flex-1 items-center justify-evenly gap-0.5 rounded-full border-[0.5px] border-[#ffffff] bg-[#f5fafe] px-1.5 shadow-[inset_0_1px_0_0_rgba(255,255,255,1),inset_0_3px_14px_-3px_rgba(255,255,255,0.55),inset_0_-1px_0_0_rgba(15,23,42,0.07),0_4px_24px_rgba(15,23,42,0.09)] backdrop-blur-2xl backdrop-saturate-200 dark:border-[0.5px] dark:border-[#ffffff] dark:bg-[#f5fafe] dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.26),inset_0_3px_16px_-3px_rgba(255,255,255,0.1),inset_0_-1px_0_0_rgba(0,0,0,0.55),0_4px_30px_rgba(0,0,0,0.52)]">
      <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="app-mf-pill-link <?php echo isActiveLink(BASE_URL . 'dashboard/') ? 'app-mf-pill-link--active' : ''; ?>" title="Dashboard">
        <span class="sr-only">Dashboard</span>
        <svg class="h-7 w-7 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5"/>
        </svg>
      </a>
      <a href="<?php echo htmlspecialchars(BASE_URL); ?>tickets/" class="app-mf-pill-link <?php echo isActiveLink(BASE_URL . 'tickets/') ? 'app-mf-pill-link--active' : ''; ?>" title="Tickets">
        <span class="sr-only">Tickets</span>
        <svg class="h-7 w-7 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/>
        </svg>
      </a>
      <?php if ($isAdminOrTechnikerFooter): ?>
      <a href="<?php echo htmlspecialchars(BASE_URL); ?>todos/" class="app-mf-pill-link <?php echo isActiveLink(BASE_URL . 'todos/') ? 'app-mf-pill-link--active' : ''; ?>" title="Aufgaben">
        <span class="sr-only">Aufgaben</span>
        <svg class="h-7 w-7 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
      </a>
      <?php endif; ?>
      <?php if ($canSeeInventoryFooter): ?>
      <a href="<?php echo htmlspecialchars(BASE_URL); ?>inventory/" class="app-mf-pill-link <?php echo isActiveLink(BASE_URL . 'inventory/') ? 'app-mf-pill-link--active' : ''; ?>" title="Lager">
        <span class="sr-only">Lager</span>
        <svg class="h-7 w-7 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.143 4H4.857A.857.857 0 0 0 4 4.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 10 9.143V4.857A.857.857 0 0 0 9.143 4Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 20 9.143V4.857A.857.857 0 0 0 19.143 4Zm-10 10H4.857a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286A.857.857 0 0 0 9.143 14Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286a.857.857 0 0 0-.857-.857Z"/>
        </svg>
      </a>
      <?php endif; ?>
    </div>

    <button type="button" id="appMobileFooterMenuBtn" class="app-mobile-footer-bubble flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-[0.5px] border-[#ffffff] bg-[#f5fafe] text-gray-800 shadow-[inset_0_1px_0_0_rgba(255,255,255,1),inset_0_3px_14px_-3px_rgba(255,255,255,0.55),inset_0_-1px_0_0_rgba(15,23,42,0.07),0_4px_24px_rgba(15,23,42,0.09)] backdrop-blur-2xl backdrop-saturate-200 transition-transform active:scale-95 dark:border-[0.5px] dark:border-[#ffffff] dark:bg-[#f5fafe] dark:text-primary-200 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.26),inset_0_3px_16px_-3px_rgba(255,255,255,0.1),inset_0_-1px_0_0_rgba(0,0,0,0.55),0_4px_30px_rgba(0,0,0,0.52)]" aria-expanded="false" aria-controls="appMobileMenuSheet" aria-label="Menü öffnen">
      <svg class="h-7 w-7 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
      </svg>
    </button>
  </nav>
</div>

<div id="appMobileSheetBackdrop" class="lg:hidden hidden fixed inset-0 z-[61] bg-black/40 backdrop-blur-[1px] transition-opacity duration-[380ms] ease-out opacity-0" aria-hidden="true"></div>

<div id="appMobileSearchSheet" class="lg:hidden hidden fixed inset-0 z-[62] flex flex-col bg-black translate-y-full transition-transform duration-[420ms] ease-[cubic-bezier(0.22,1,0.36,1)]" role="dialog" aria-modal="true" aria-labelledby="appMobileSearchSheetTitle" aria-hidden="true">
  <div class="app-mobile-sheet-header flex items-center justify-between gap-3 px-4 pt-[max(0.75rem,env(safe-area-inset-top,0px))] pb-3 shrink-0 bg-black text-gray-100 border-0 shadow-none">
    <h2 id="appMobileSearchSheetTitle" class="min-w-0 truncate text-lg font-semibold leading-tight tracking-tight text-white">Suche</h2>
    <button type="button" id="appMobileSearchSheetClose" class="app-mobile-sheet-header-close p-2 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/30" aria-label="Schließen">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="flex-1 min-h-0 flex flex-col overflow-hidden rounded-t-[1.75rem] pb-[env(safe-area-inset-bottom,0px)] bg-gray-50 dark:bg-primary-100">
    <div id="globalSearchWrapperMobile" class="relative w-full px-4 pt-3 flex flex-col min-h-0 flex-1" data-kb-company-id="<?php echo htmlspecialchars($footerKbCompanyId); ?>">
      <div class="relative w-full shrink-0">
        <input type="text" id="globalSearchInputMobile" placeholder="System durchsuchen…" autocomplete="off" class="global-search-input w-full pl-10 pr-3 py-2.5 text-sm border border-gray-200 dark:border-primary-320 rounded-xl bg-gray-50 dark:bg-primary-300/30 text-gray-900 dark:text-primary-200 placeholder-gray-400 dark:placeholder-primary-240 focus:outline-none focus:ring-0 focus:border-gray-200 dark:focus:border-primary-320 shadow-sm">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 dark:text-primary-240 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
      </div>
      <div id="globalSearchResultsMobile" class="hidden flex-1 min-h-0 mt-3 flex flex-col rounded-xl border border-gray-100 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden shadow-sm">
        <div id="globalSearchPillsMobile" class="flex gap-2 px-3 py-2 border-b border-gray-100 dark:border-primary-230 overflow-x-auto shrink-0" style="scrollbar-width: thin;">
          <div id="globalSearchPillsInnerMobile" class="flex gap-2 flex-nowrap"></div>
        </div>
        <div id="globalSearchResultsContentMobile" class="p-2 flex-1 min-h-0 flex flex-col overflow-hidden">
          <div id="globalSearchResultsListMobile" class="flex-1 min-h-0 overflow-y-auto global-search-scroll"></div>
          <div id="globalSearchMoreMobile" class="hidden px-3 py-2 border-t border-gray-100 dark:border-primary-230 shrink-0">
            <p id="globalSearchMoreLinkMobile" class="text-sm text-gray-500 dark:text-primary-240 text-center m-0" role="status"></p>
          </div>
        </div>
        <div id="globalSearchEmptyMobile" class="hidden px-4 py-8 text-center text-sm text-gray-500 dark:text-primary-240">Keine Ergebnisse gefunden.</div>
        <div id="globalSearchLoadingMobile" class="hidden px-4 py-8 text-center text-sm text-gray-500 dark:text-primary-240">
          <svg class="animate-spin h-6 w-6 mx-auto mb-3 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          Suche…
        </div>
      </div>
    </div>
  </div>
</div>

<div id="appMobileMenuSheet" class="lg:hidden hidden fixed inset-0 z-[62] flex flex-col bg-black translate-y-full transition-transform duration-[420ms] ease-[cubic-bezier(0.22,1,0.36,1)]" role="dialog" aria-modal="true" aria-labelledby="appMobileMenuSheetTitle" aria-hidden="true">
  <div class="app-mobile-sheet-header flex items-center justify-between gap-3 px-4 pt-[max(0.75rem,env(safe-area-inset-top,0px))] pb-3 shrink-0 bg-black text-gray-100 border-0 shadow-none">
    <h2 id="appMobileMenuSheetTitle" class="min-w-0 truncate text-lg font-semibold leading-tight tracking-tight text-white">Menü</h2>
    <button type="button" id="appMobileMenuSheetClose" class="app-mobile-sheet-header-close p-2 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/30" aria-label="Schließen">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="app-mobile-menu-sheet flex-1 min-h-0 overflow-y-auto rounded-t-[1.75rem] px-2 py-2 pb-[calc(1rem+env(safe-area-inset-bottom,0px))] bg-gray-50 dark:bg-primary-100">
    <?php include __DIR__ . '/sidebar_nav_content.php'; ?>

    <p class="ml-2 mt-4 mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Konto</p>
    <ul class="space-y-2 border-t border-gray-200 pt-2 dark:border-gray-700">
      <li>
        <a href="<?php echo htmlspecialchars(BASE_URL); ?>account/" class="<?php echo getLinkClasses(BASE_URL . 'account/'); ?>">
          <svg class="<?php echo getIconClasses(BASE_URL . 'account/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
            <path fill-rule="evenodd" d="M12 20a7.966 7.966 0 0 1-5.002-1.756l.002.001v-.683c0-1.794 1.492-3.25 3.333-3.25h3.334c1.84 0 3.333 1.456 3.333 3.25v.683A7.966 7.966 0 0 1 12 20ZM2 12C2 6.477 6.477 2 12 2s10 4.477 10 10c0 5.5-4.44 9.963-9.932 10h-.138C6.438 21.962 2 17.5 2 12Zm10-5c-1.84 0-3.333 1.455-3.333 3.25S10.159 13.5 12 13.5c1.84 0 3.333-1.455 3.333-3.25S13.841 7 12 7Z" clip-rule="evenodd"></path>
          </svg>
          <span class="ml-3">Profil</span>
        </a>
      </li>
      <li>
        <a href="<?php echo htmlspecialchars(BASE_URL); ?>settings/" class="<?php echo getLinkClasses(BASE_URL . 'settings/'); ?>">
          <svg class="<?php echo getIconClasses(BASE_URL . 'settings/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
            <path fill-rule="evenodd" d="M9.586 2.586A2 2 0 0 1 11 2h2a2 2 0 0 1 2 2v.089l.473.196.063-.063a2.002 2.002 0 0 1 2.828 0l1.414 1.414a2 2 0 0 1 0 2.827l-.063.064.196.473H20a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-.089l-.196.473.063.063a2.002 2.002 0 0 1 0 2.828l-1.414 1.414a2 2 0 0 1-2.828 0l-.063-.063-.473.196V20a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-.089l-.473-.196-.063.063a2.002 2.002 0 0 1-2.828 0l-1.414-1.414a2 2 0 0 1 0-2.827l.063-.064L4.089 15H4a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h.09l.195-.473-.063-.063a2 2 0 0 1 0-2.828l1.414-1.414a2 2 0 0 1 2.827 0l.064.063L9 4.089V4a2 2 0 0 1 .586-1.414ZM8 12a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd"></path>
          </svg>
          <span class="ml-3">Einstellungen</span>
        </a>
      </li>
      <?php if ($userRoleFooter === 'Admin'): ?>
      <li>
        <a href="<?php echo htmlspecialchars(BASE_URL); ?>admin/" class="<?php echo getLinkClasses(BASE_URL . 'admin/'); ?>">
          <svg class="<?php echo getIconClasses(BASE_URL . 'admin/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
            <path fill-rule="evenodd" d="M11.64 2.488a.75.75 0 0 1 .26.77l-.44 1.983a1.932 1.932 0 0 1 1.56 1.56l1.984.44a.75.75 0 0 1 .53.918l-.39 1.71a.75.75 0 0 1-.657.585l-2.127.272a1.932 1.932 0 0 1-1.037 1.037l-.272 2.127a.75.75 0 0 1-.585.657l-1.71.39a.75.75 0 0 1-.918-.53l-.44-1.984a1.932 1.932 0 0 1-1.56-1.56l-1.984-.44a.75.75 0 0 1-.53-.918l.39-1.71a.75.75 0 0 1 .657-.585l2.127-.272a1.932 1.932 0 0 1 1.037-1.037l.272-2.127a.75.75 0 0 1 .585-.657l1.71-.39a.75.75 0 0 1 .658.257ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd"></path>
          </svg>
          <span class="ml-3">Admin</span>
        </a>
      </li>
      <?php endif; ?>
      <li>
        <a href="<?php echo htmlspecialchars(BASE_URL); ?>logout.php" class="group flex h-9 min-h-[2.75rem] items-center rounded-lg p-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-primary-140">
          <svg class="h-[1.125rem] w-[1.125rem] shrink-0 text-red-500 dark:text-red-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2"></path>
          </svg>
          <span class="ml-3">Abmelden</span>
        </a>
      </li>
    </ul>
  </div>
</div>

<style>
@media (max-width: 1023px) {
  /* Sheet: wie #main-nav + #main-content — schwarzer Streifen unten eckig, helle Fläche mit border-top-radius */
  .app-mobile-sheet-header {
    box-shadow: none !important;
    border-bottom: none !important;
  }
  body.app-mobile-bottom-nav #main-content {
    padding-bottom: calc(3.75rem + env(safe-area-inset-bottom, 0px));
  }
  .app-mf-pill-link {
    display: flex;
    height: 2.75rem;
    width: 2.75rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    color: rgb(55 65 81);
    transition: background-color 0.15s ease, color 0.15s ease;
  }
  .dark .app-mf-pill-link {
    color: rgb(203 213 225);
  }
  .app-mf-pill-link:hover {
    background-color: rgb(243 244 246 / 0.95);
  }
  .dark .app-mf-pill-link:hover {
    background-color: rgb(30 41 59 / 0.6);
  }
  .app-mf-pill-link--active {
    background-color: rgb(229 231 235 / 0.95);
    color: rgb(17 24 39);
  }
  /* Tab-Wechsel per „Finger auf aktivem Icon halten und ziehen“: horizontale Geste nicht vom Browser als Scroll fressen lassen. */
  .app-mobile-footer-pill,
  .app-mobile-footer-pill .app-mf-pill-link {
    touch-action: pan-y;
  }
  .dark .app-mf-pill-link--active {
    background-color: rgb(51 65 85 / 0.95);
    color: rgb(248 250 252);
  }
  .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  .no-scrollbar::-webkit-scrollbar { display: none; }
  .app-mobile-menu-sheet [data-sidebar-collapse-hide] { display: inline !important; }
  .app-mobile-menu-sheet p[data-sidebar-collapse-hide] { display: block !important; margin-left: 0.5rem; }
  .app-mobile-menu-sheet ul { padding-bottom: 0.5rem; }
  .app-mobile-menu-sheet a.group { min-height: 2.75rem; }
}
</style>
<script>
(function() {
  function qs(id) { return document.getElementById(id); }
  /* ui-haptics.js ist defer — binden nach DOMContentLoaded. touchstart (iOS/WebKit) + pointerdown touch — reicht auch für die Suche-Taste (touchend+preventDefault kommt danach). */
  function bindAppMobileFooterHaptics() {
    var footerNav = qs('appMobileFooterNav');
    if (!footerNav || typeof window.hapticLightTap !== 'function') return;
    var lastHaptic = 0;
    function fireFooterHaptic() {
      var t = Date.now();
      if (t - lastHaptic < 120) return;
      lastHaptic = t;
      window.hapticLightTap();
    }
    function isFooterInteractiveTarget(e) {
      if (!window.matchMedia) return null;
      var coarse = window.matchMedia('(pointer: coarse)').matches;
      var touchCapable = typeof navigator !== 'undefined' && navigator.maxTouchPoints > 0;
      if (!coarse && !touchCapable) return null;
      var interactive = e.target.closest('a, button');
      if (!interactive || !footerNav.contains(interactive)) return null;
      return interactive;
    }
    footerNav.addEventListener('touchstart', function(e) {
      if (!isFooterInteractiveTarget(e)) return;
      fireFooterHaptic();
    }, { passive: true });
    footerNav.addEventListener('pointerdown', function(e) {
      if (e.pointerType !== 'touch' && e.pointerType !== 'pen') return;
      if (!isFooterInteractiveTarget(e)) return;
      fireFooterHaptic();
    }, { passive: true });
  }
  document.addEventListener('DOMContentLoaded', bindAppMobileFooterHaptics);
  var backdrop = qs('appMobileSheetBackdrop');
  var searchSheet = qs('appMobileSearchSheet');
  var menuSheet = qs('appMobileMenuSheet');
  var searchBtn = qs('appMobileFooterSearchBtn');
  var menuBtn = qs('appMobileFooterMenuBtn');
  var searchClose = qs('appMobileSearchSheetClose');
  var menuClose = qs('appMobileMenuSheetClose');
  if (!backdrop || !searchSheet || !menuSheet) return;

  var openSheet = null;

  function clearSheetInline(el) {
    if (!el) return;
    el.style.transform = '';
    el.style.transition = '';
  }

  function resetSheetsToHidden() {
    [searchSheet, menuSheet].forEach(function(el) {
      el.classList.add('hidden', 'translate-y-full');
      clearSheetInline(el);
      el.setAttribute('aria-hidden', 'true');
    });
    backdrop.classList.add('hidden', 'opacity-0');
    backdrop.style.opacity = '';
    backdrop.style.transition = '';
    backdrop.setAttribute('aria-hidden', 'true');
    openSheet = null;
    if (searchBtn) searchBtn.setAttribute('aria-expanded', 'false');
    if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  function closeSheets() {
    var sheet = openSheet;
    if (!sheet) {
      resetSheetsToHidden();
      return;
    }
    sheet.style.transition = 'transform 0.4s cubic-bezier(0.32, 0.72, 0, 1)';
    sheet.style.transform = 'translateY(100%)';
    backdrop.style.transition = 'opacity 0.36s ease';
    backdrop.style.opacity = '0';

    var closing = sheet;
    var finished = false;
    function done() {
      if (finished) return;
      finished = true;
      closing.removeEventListener('transitionend', onTransitionEnd);
      resetSheetsToHidden();
    }
    function onTransitionEnd(e) {
      if (e.target !== closing || e.propertyName !== 'transform') return;
      done();
    }
    closing.addEventListener('transitionend', onTransitionEnd);
    setTimeout(done, 480);
  }

  /**
   * Such-Sheet: sofort vollständig einblenden + focus im selben synchronen Handler
   * (iOS/WebKit öffnet die Tastatur nur so; rAF/async bricht die „User Activation“).
   */
  function openSearchSheetFromUserGesture() {
    resetSheetsToHidden();
    openSheet = searchSheet;
    clearSheetInline(searchSheet);
    searchSheet.classList.remove('hidden');
    backdrop.classList.remove('hidden');
    backdrop.classList.remove('opacity-0');
    searchSheet.classList.remove('translate-y-full');
    searchSheet.setAttribute('aria-hidden', 'false');
    backdrop.setAttribute('aria-hidden', 'false');
    if (searchBtn) searchBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    var inp = qs('globalSearchInputMobile');
    if (inp) {
      try {
        inp.focus({ preventScroll: true });
      } catch (e) {}
    }
  }

  function openOne(sheet, btn) {
    resetSheetsToHidden();
    openSheet = sheet;
    clearSheetInline(sheet);
    sheet.classList.remove('hidden');
    backdrop.classList.remove('hidden');
    requestAnimationFrame(function() {
      backdrop.classList.remove('opacity-0');
      sheet.classList.remove('translate-y-full');
    });
    sheet.setAttribute('aria-hidden', 'false');
    backdrop.setAttribute('aria-hidden', 'false');
    if (btn) btn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  var searchOpenSwallowClick = false;
  if (searchBtn) {
    searchBtn.addEventListener('touchend', function(e) {
      searchOpenSwallowClick = true;
      window.setTimeout(function() { searchOpenSwallowClick = false; }, 450);
      e.preventDefault();
      openSearchSheetFromUserGesture();
    }, { passive: false });
    searchBtn.addEventListener('click', function() {
      if (searchOpenSwallowClick) return;
      openSearchSheetFromUserGesture();
    });
  }
  if (menuBtn) menuBtn.addEventListener('click', function() {
    openOne(menuSheet, menuBtn);
  });
  if (searchClose) searchClose.addEventListener('click', closeSheets);
  if (menuClose) menuClose.addEventListener('click', closeSheets);
  backdrop.addEventListener('click', closeSheets);
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && openSheet) closeSheets();
  });
  var searchListMob = qs('globalSearchResultsListMobile');
  if (searchListMob) searchListMob.addEventListener('click', function(e) {
    if (e.target.closest('a[href]')) closeSheets();
  });
  if (menuSheet) menuSheet.addEventListener('click', function(e) {
    if (e.target.closest('a[href]')) closeSheets();
  });

  function bindSheetDragDismiss(sheet) {
    var header = sheet.querySelector('.app-mobile-sheet-header');
    if (!header) return;

    var startY = 0;
    var startX = 0;
    var active = false;
    var dragging = false;
    var currentDy = 0;

    function closeThreshold() {
      return Math.max(88, window.innerHeight * 0.17);
    }

    function endDragSnapBack() {
      sheet.style.transition = 'transform 0.48s cubic-bezier(0.34, 1.15, 0.52, 1)';
      sheet.style.transform = 'translateY(0)';
      backdrop.style.transition = 'opacity 0.38s ease';
      backdrop.style.opacity = '';

      var s = sheet;
      var b = backdrop;
      function cleanup() {
        s.removeEventListener('transitionend', te);
        clearSheetInline(s);
        b.style.transition = '';
        b.style.opacity = '';
      }
      function te(e) {
        if (e.target !== s || e.propertyName !== 'transform') return;
        cleanup();
      }
      s.addEventListener('transitionend', te);
      setTimeout(function() {
        s.removeEventListener('transitionend', te);
        cleanup();
      }, 520);
    }

    function endDragClose() {
      sheet.style.transition = 'transform 0.4s cubic-bezier(0.32, 0.72, 0, 1)';
      sheet.style.transform = 'translateY(100%)';
      backdrop.style.transition = 'opacity 0.34s ease';
      backdrop.style.opacity = '0';

      var closing = sheet;
      var finished = false;
      function done() {
        if (finished) return;
        finished = true;
        closing.removeEventListener('transitionend', te);
        resetSheetsToHidden();
      }
      function te(e) {
        if (e.target !== closing || e.propertyName !== 'transform') return;
        done();
      }
      closing.addEventListener('transitionend', te);
      setTimeout(done, 480);
    }

    function onCancel() {
      if (!active) return;
      active = false;
      dragging = false;
      sheet.style.transition = '';
      backdrop.style.transition = '';
      backdrop.style.opacity = '';
      clearSheetInline(sheet);
    }

    header.addEventListener('touchstart', function(e) {
      if (openSheet !== sheet || sheet.classList.contains('hidden')) return;
      if (e.touches.length !== 1) return;
      active = true;
      dragging = false;
      currentDy = 0;
      startY = e.touches[0].clientY;
      startX = e.touches[0].clientX;
      sheet.style.transition = 'none';
      backdrop.style.transition = 'none';
    }, { passive: true });

    header.addEventListener('touchmove', function(e) {
      if (!active || e.touches.length !== 1) return;
      var y = e.touches[0].clientY;
      var x = e.touches[0].clientX;
      var dy = y - startY;
      var dx = x - startX;
      if (!dragging) {
        if (dy <= 3) return;
        if (Math.abs(dx) > Math.abs(dy) + 10) {
          active = false;
          sheet.style.transition = '';
          backdrop.style.transition = '';
          return;
        }
        dragging = true;
      }
      e.preventDefault();
      var maxY = window.innerHeight * 0.94;
      currentDy = Math.min(Math.max(0, dy), maxY);
      sheet.style.transform = 'translateY(' + currentDy + 'px)';
      var p = Math.min(currentDy / (window.innerHeight * 0.4), 1);
      backdrop.style.opacity = String(Math.max(0, 1 - p * 0.96));
    }, { passive: false });

    header.addEventListener('touchend', function() {
      if (!active) return;
      active = false;
      if (!dragging) {
        sheet.style.transition = '';
        backdrop.style.transition = '';
        backdrop.style.opacity = '';
        return;
      }
      dragging = false;
      if (currentDy > closeThreshold()) {
        endDragClose();
      } else {
        endDragSnapBack();
      }
      currentDy = 0;
    }, { passive: true });

    header.addEventListener('touchcancel', onCancel, { passive: true });
  }

  bindSheetDragDismiss(menuSheet);
  bindSheetDragDismiss(searchSheet);

  /**
   * Pill: nur wenn der Finger auf dem **aktiven** Icon bleibt und dann horizontal zieht (iPhone-ähnlich),
   * beim Loslassen zur nächsten/vorherigen Hauptseite wechseln. Normale Tipps auf andere Icons unverändert.
   */
  function bindPillSwipeNavigation() {
    var pill = document.querySelector('.app-mobile-footer-pill');
    if (!pill) return;
    var links = Array.prototype.slice.call(pill.querySelectorAll('a.app-mf-pill-link'));
    if (links.length < 2) return;

    function normalizePath(pathname) {
      if (!pathname) return '';
      var p = pathname.replace(/\/+$/, '');
      return p === '' ? '/' : p;
    }

    function currentPillIndex() {
      for (var i = 0; i < links.length; i++) {
        if (links[i].classList.contains('app-mf-pill-link--active')) return i;
      }
      var cur = normalizePath(window.location.pathname);
      var bestIdx = 0;
      var bestLen = -1;
      for (var j = 0; j < links.length; j++) {
        try {
          var lp = normalizePath(new URL(links[j].href, window.location.origin).pathname);
          if (cur === lp) return j;
          if (lp !== '/' && lp.length > bestLen && (cur === lp || cur.indexOf(lp + '/') === 0)) {
            bestLen = lp.length;
            bestIdx = j;
          }
        } catch (err) {}
      }
      return bestIdx;
    }

    var dragState = null;
    var SWIPE_MIN = 40;
    var HORIZ_RATIO = 1.2;
    var CLAIM_HORIZ = 14;

    var touchOptsMove = { capture: true, passive: false };
    /* touchend: passive false, sonst greift kein preventDefault bei Navigation */
    var touchOptsEnd = { capture: true, passive: false };

    function removeDocListeners() {
      document.removeEventListener('touchmove', onDocTouchMove, touchOptsMove);
      document.removeEventListener('touchend', onDocTouchEnd, touchOptsEnd);
      document.removeEventListener('touchcancel', onDocTouchEnd, touchOptsEnd);
    }

    function onDocTouchMove(e) {
      if (!dragState || e.touches.length !== 1) return;
      var x = e.touches[0].clientX;
      var y = e.touches[0].clientY;
      var dx = x - dragState.startX;
      var dy = y - dragState.startY;
      if (!dragState.movedHoriz) {
        if (Math.abs(dx) > CLAIM_HORIZ && Math.abs(dx) > Math.abs(dy) * HORIZ_RATIO) {
          dragState.movedHoriz = true;
        }
      }
      if (dragState.movedHoriz) {
        e.preventDefault();
      }
    }

    function onDocTouchEnd(e) {
      if (!dragState) return;
      var t = e.changedTouches && e.changedTouches[0];
      var state = dragState;
      removeDocListeners();
      dragState = null;
      if (!t) return;
      var dx = t.clientX - state.startX;
      var dy = t.clientY - state.startY;
      if (!state.movedHoriz || Math.abs(dx) < SWIPE_MIN || Math.abs(dx) <= Math.abs(dy) * HORIZ_RATIO) {
        return;
      }
      var idx = currentPillIndex();
      var target = null;
      if (dx < 0 && idx < links.length - 1) target = links[idx + 1];
      else if (dx > 0 && idx > 0) target = links[idx - 1];
      if (!target) return;
      e.preventDefault();
      pill.setAttribute('data-app-mf-suppress-active-click', '1');
      window.setTimeout(function() {
        pill.removeAttribute('data-app-mf-suppress-active-click');
      }, 420);
      if (typeof window.hapticLightTap === 'function') window.hapticLightTap();
      window.location.href = target.href;
    }

    pill.addEventListener('touchstart', function(e) {
      if (e.touches.length !== 1) return;
      var link = e.target.closest('a.app-mf-pill-link');
      if (!link || !link.classList.contains('app-mf-pill-link--active')) return;
      if (dragState) {
        removeDocListeners();
        dragState = null;
      }
      dragState = {
        startX: e.touches[0].clientX,
        startY: e.touches[0].clientY,
        movedHoriz: false
      };
      document.addEventListener('touchmove', onDocTouchMove, touchOptsMove);
      document.addEventListener('touchend', onDocTouchEnd, touchOptsEnd);
      document.addEventListener('touchcancel', onDocTouchEnd, touchOptsEnd);
    }, { passive: true });

    pill.addEventListener('click', function(e) {
      if (pill.getAttribute('data-app-mf-suppress-active-click') !== '1') return;
      var a = e.target.closest('a.app-mf-pill-link');
      if (a && a.classList.contains('app-mf-pill-link--active')) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
  }

  bindPillSwipeNavigation();
})();
</script>
