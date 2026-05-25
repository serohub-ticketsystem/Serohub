<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/inventory_permissions.php';
requireLogin();

$consumableId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$consumableId) {
    header('Location: ' . BASE_URL . 'inventory/');
    exit;
}

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

$navMobileInventoryDetailMobile = true;
$navMobileHideCompactCreateButton = true;
$navMobileInventoryDetailEditUrl = $canEditConsumables ? (BASE_URL . 'inventory/edit.php?id=' . $consumableId) : null;

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="kalender-page relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 max-lg:pt-[calc(env(safe-area-inset-top,0px)+3.5rem+1rem)] lg:pt-0 overflow-hidden max-lg:overflow-visible service-main-content app-mobile-no-root-overscroll">
  <main class="mx-4 mt-2 flex flex-col overflow-hidden min-h-0 pb-6 max-lg:overflow-visible max-lg:min-h-0 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 service-main">
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
        <li>
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
            <a href="<?php echo BASE_URL; ?>inventory/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 md:ms-2 dark:text-gray-400 dark:hover:text-white">Lager</a>
          </div>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
            <span id="detail-breadcrumb-title" class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Details</span>
          </div>
        </li>
      </ol>
    </nav>

    <div id="detail-loading" class="flex items-center justify-center py-16">
      <svg class="w-10 h-10 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <div id="detail-content" class="hidden">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3 lg:mb-4">
        <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden">
          <div class="px-3 py-3 sm:px-4">
            <p class="text-[11px] sm:text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-primary-220">Artikelnummer</p>
            <p id="detail-artikelnummer" class="mt-1 text-base font-semibold text-gray-900 dark:text-white break-words"></p>
          </div>
        </section>
        <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden">
          <div class="px-3 py-3 sm:px-4">
            <p class="text-[11px] sm:text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-primary-220">EAN</p>
            <p id="detail-ean" class="mt-1 text-base font-semibold font-mono text-gray-900 dark:text-white tracking-tight break-all"></p>
          </div>
        </section>
      </div>

      <div class="hidden lg:flex flex-col-reverse items-stretch justify-between gap-3 pb-3 md:flex-row md:items-center">
        <div>
          <h1 id="detail-title" class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight"></h1>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">Verbrauchsmaterial</p>
        </div>
        <?php if ($canEditConsumables): ?>
        <div>
          <a id="detail-edit-link" href="#" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-primary-320 dark:bg-primary-100 dark:text-primary-200 dark:hover:bg-primary-140">
            <svg class="w-4 h-4 text-gray-500 dark:text-primary-220" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            Bearbeiten
          </a>
        </div>
        <?php endif; ?>
      </div>

      <div class="flex flex-col gap-3 lg:gap-4">
        <!-- Kompakte Übersicht: Bestand -->
        <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden">
          <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-primary-120">
            <div class="px-3 py-3 sm:px-4 text-center sm:text-left">
              <p class="text-[11px] sm:text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-primary-220">Auf Lager</p>
              <p id="detail-lagerbestand" class="mt-0.5 text-xl sm:text-2xl font-semibold tabular-nums text-gray-900 dark:text-white leading-tight"></p>
            </div>
            <div class="px-3 py-3 sm:px-4 text-center sm:text-left">
              <p class="text-[11px] sm:text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-primary-220">Mindest</p>
              <p id="detail-mindestbestand" class="mt-0.5 text-lg sm:text-xl font-medium tabular-nums text-gray-900 dark:text-white"></p>
            </div>
          </div>
          <p id="detail-auto-nachbestell-hinweis" class="hidden border-t border-gray-100 dark:border-primary-120 px-3 py-2 sm:px-4 text-[11px] sm:text-xs text-gray-500 dark:text-primary-230 leading-snug flex items-start gap-2" role="status">
            <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 text-emerald-600/60 dark:text-emerald-400/55" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <span id="detail-auto-nachbestell-hinweis-text"></span>
          </p>
          <div id="detail-stock-badges" class="hidden"></div>
          <?php if ($canAdjustInventoryStock): ?>
          <div class="border-t border-gray-100 dark:border-primary-120 px-3 py-4 sm:px-4 bg-white dark:bg-primary-100">
            <div class="flex gap-2">
              <button type="button" id="detail-btn-auslagern" class="detail-stock-action-btn flex-1 inline-flex items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-3 text-sm font-semibold text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/30 dark:text-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40 transition-transform duration-150 ease-out active:scale-[0.96] active:duration-75 disabled:pointer-events-none disabled:cursor-wait disabled:opacity-100">
                Auslagern
              </button>
              <button type="button" id="detail-btn-einlagern" class="detail-stock-action-btn flex-1 inline-flex items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-3 text-sm font-semibold text-emerald-900 dark:border-emerald-700/60 dark:bg-emerald-900/30 dark:text-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 transition-transform duration-150 ease-out active:scale-[0.96] active:duration-75 disabled:pointer-events-none disabled:cursor-wait disabled:opacity-100">
                Einlagern
              </button>
            </div>
          </div>
          <?php endif; ?>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 lg:gap-4">
          <!-- Desktop: Artikel links (2 Spalten). Handy: per order nach Lagerort (s. zweite Spalte) -->
          <div class="order-3 lg:order-none lg:col-span-2 flex flex-col gap-3">
            <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden">
              <div class="px-3 py-2 border-b border-gray-200 dark:border-primary-120 bg-gray-50/80 dark:bg-primary-900/30 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Artikel</h2>
                <span id="detail-bezeichnung" class="lg:hidden text-sm font-medium text-gray-600 dark:text-primary-210 truncate max-w-[min(100%,12rem)] sm:max-w-md text-right" title=""></span>
              </div>
              <div class="p-3 sm:p-4">
                <dl class="grid grid-cols-1 gap-y-3 text-sm">
                  <div>
                    <dt class="text-xs font-medium text-gray-500 dark:text-primary-220">Beschreibung</dt>
                    <dd id="detail-beschreibung" class="mt-0.5 text-gray-600 dark:text-gray-300 text-sm leading-relaxed"></dd>
                  </div>
                </dl>
                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-primary-120">
                  <p class="text-xs font-medium text-gray-500 dark:text-primary-220 mb-1.5">Kategorien</p>
                  <div id="detail-categories" class="flex flex-wrap gap-1.5"></div>
                </div>
              </div>
            </section>

            <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden">
              <div class="px-3 py-2 border-b border-gray-200 dark:border-primary-120 bg-gray-50/80 dark:bg-primary-900/30">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Gerätemodelle</h2>
              </div>
              <div class="p-3 sm:p-4">
                <ul id="detail-device-models" class="text-sm divide-y divide-gray-100 dark:divide-primary-120 -mx-1"></ul>
              </div>
            </section>
          </div>

          <!-- Handy: vor Artikel (order-2); Desktop: rechte Spalte, sticky -->
          <div class="order-2 lg:order-none lg:col-span-1">
            <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden lg:sticky lg:top-4">
              <div class="px-3 py-2.5 border-b border-gray-200 dark:border-primary-120 bg-gray-50/80 dark:bg-primary-900/30">
                <h2 id="detail-lagerort-text" class="text-base font-semibold text-gray-900 dark:text-white leading-snug break-words"></h2>
              </div>
              <div class="p-3 sm:p-4">
                <div id="shelf-3d-container" class="rounded-lg overflow-hidden bg-gray-100 dark:bg-primary-900/50 border border-gray-200 dark:border-primary-120 max-lg:min-h-[200px] lg:min-h-[260px]" style="height: 260px;">
                  <div id="shelf-3d-placeholder" class="h-full flex flex-col items-center justify-center text-gray-500 dark:text-gray-400 text-xs sm:text-sm px-4 text-center leading-relaxed gap-3">
                    <?php if ($canEditConsumables): ?>
                    <span>Kein Regal zugewiesen.</span>
                    <a href="<?php echo htmlspecialchars(BASE_URL); ?>inventory/edit.php?id=<?php echo (int)$consumableId; ?>#inv-edit-lagerort" class="inline-flex items-center text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 underline underline-offset-2 decoration-primary-400/60 hover:decoration-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-1 py-0.5">Regal auswählen</a>
                    <?php else: ?>
                    <span>Kein Lagerort zugewiesen.</span>
                    <?php endif; ?>
                  </div>
                  <canvas id="shelf-3d-canvas" class="hidden w-full h-full"></canvas>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-primary-220 mt-2 leading-snug max-lg:hidden">Hervorgehobenes Fach = Position. Regal mit der Maus drehen.</p>
              </div>
            </section>
          </div>
        </div>
      </div>
    </div>

    <div id="detail-error" class="hidden py-16 text-center">
      <p class="text-red-600 dark:text-red-400 font-medium">Verbrauchsmaterial nicht gefunden.</p>
      <a href="<?php echo BASE_URL; ?>inventory/" class="mt-4 inline-block text-primary-600 dark:text-primary-400 hover:underline">Zurück zum Lager</a>
    </div>
  </main>
</div>

<script>
(function() {
    const baseUrl = typeof window.baseUrl !== 'undefined' ? window.baseUrl : '<?php echo BASE_URL; ?>';
    const consumablesApiUrl = baseUrl + 'inventory/api/consumables.php';
    const consumableId = <?php echo (int)$consumableId; ?>;

    function escapeHtml(s) {
        if (s == null || s === '') return '';
        const div = document.createElement('div');
        div.textContent = String(s);
        return div.innerHTML;
    }

    /** Erste Zeile „Regal …“, darunter „Spalte …, Fach …“ (mit <br>). */
    function buildDetailLagerortDisplayHtml(c) {
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
        var line2Wrapped = line2 ? '<span class="text-xs text-gray-500 dark:text-primary-220 leading-snug">' + line2 + '</span>' : '';
        if (!name && !line2) return '';
        if (!name) return line2Wrapped;
        if (!line2) return 'Regal ' + escapeHtml(name);
        return 'Regal ' + escapeHtml(name) + '<br>' + line2Wrapped;
    }

    var detailConsumableCache = null;

    function getDetailStockStatuses(c) {
        if (!c) return [];
        var lager = c.lagerbestand != null ? Number(c.lagerbestand) : 0;
        var mindest = c.mindestbestand != null ? Number(c.mindestbestand) : null;
        var hasOpenOrder = c.has_open_order === 1 || c.has_open_order === true || c.has_open_order === '1';
        var pendingStockin = c.pending_stockin_after_delivery === 1 || c.pending_stockin_after_delivery === true || c.pending_stockin_after_delivery === '1';
        var statuses = [];
        if (hasOpenOrder) statuses.push('nachbestellt');
        if (pendingStockin) statuses.push('bestellung_angekommen');
        if (lager <= 0) statuses.push('leer');
        if (mindest != null && lager < mindest) {
            statuses.push('unter_mindest');
        }
        if (statuses.length === 0) statuses.push('bestand_vorhanden');
        return statuses;
    }

    function buildDetailStockBadgesHtml(c) {
        return getDetailStockStatuses(c).filter(function(s) {
            return s !== 'leer' && s !== 'bestand_vorhanden';
        }).map(function(stockStatus) {
            if (stockStatus === 'nachbestellt') {
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Nachbestellt</span>';
            }
            if (stockStatus === 'bestellung_angekommen') {
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-violet-100 text-violet-900 dark:bg-violet-900/45 dark:text-violet-200">Bestellung angekommen</span>';
            }
            if (stockStatus === 'unter_mindest') {
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/45 dark:text-amber-200">Unter Mindestbestand</span>';
            }
            if (stockStatus === 'leer') {
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-400">Leer</span>';
            }
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-400">Bestand vorhanden</span>';
        }).join('');
    }

    var DETAIL_STOCK_BADGES_ROW_CLASS = 'px-3 py-2 border-t border-gray-100 dark:border-primary-120 flex flex-wrap gap-1.5 items-center min-h-[2rem]';

    function updateDetailStockBadges() {
        var el = document.getElementById('detail-stock-badges');
        if (!el || !detailConsumableCache) return;
        var html = buildDetailStockBadgesHtml(detailConsumableCache);
        el.innerHTML = html;
        el.className = html ? DETAIL_STOCK_BADGES_ROW_CLASS : 'hidden';
    }

    function updateDetailAutoNachbestellHinweis() {
        var autoNbEl = document.getElementById('detail-auto-nachbestell-hinweis');
        var autoNbTxt = document.getElementById('detail-auto-nachbestell-hinweis-text');
        if (!autoNbEl || !autoNbTxt || !detailConsumableCache) return;
        var c = detailConsumableCache;
        var autoNbOn = c.auto_nachbestellen === 1 || c.auto_nachbestellen === true || c.auto_nachbestellen === '1';
        autoNbEl.classList.toggle('hidden', !autoNbOn);
        if (!autoNbOn) return;
        var m = c.mindestbestand != null && c.mindestbestand !== '' ? Number(c.mindestbestand) : null;
        if (m !== null && isNaN(m)) m = null;
        var hatMindest = m != null;
        autoNbTxt.textContent = hatMindest
            ? 'Unterschreitet der Bestand den Meldebestand, wird automatisch nachbestellt.'
            : 'Automatische Nachbestellung ist aktiviert (ohne Meldebestand wird nichts ausgelöst).';
    }

    function setDetailLagerAnzeige(lager, mindest) {
        var lagerNum = lager != null ? Number(lager) : 0;
        if (isNaN(lagerNum)) lagerNum = 0;
        var mindestNum = mindest != null && mindest !== '' ? Number(mindest) : null;
        if (mindestNum !== null && isNaN(mindestNum)) mindestNum = null;
        var lowStock = mindestNum != null && lagerNum < mindestNum;
        var el = document.getElementById('detail-lagerbestand');
        var elM = document.getElementById('detail-mindestbestand');
        if (!el || !elM) return;
        el.innerHTML = lowStock
            ? '<span class="text-amber-600 dark:text-amber-400">' + escapeHtml(String(lagerNum)) + '</span>'
            : escapeHtml(String(lagerNum));
        elM.textContent = mindestNum != null ? String(mindestNum) : '–';
    }

    function renderDetail(data) {
        const c = data.consumable;
        detailConsumableCache = c;
        document.getElementById('detail-breadcrumb-title').textContent = c.bezeichnung || 'Details';
        document.getElementById('detail-title').textContent = c.bezeichnung || '–';
        var navInvTitle = document.getElementById('navInventoryDetailTitle');
        if (navInvTitle) navInvTitle.textContent = c.bezeichnung || 'Artikel';
        var editLink = document.getElementById('detail-edit-link');
        if (editLink) editLink.href = baseUrl + 'inventory/edit.php?id=' + c.id;

        var bezEl = document.getElementById('detail-bezeichnung');
        if (bezEl) {
            bezEl.textContent = c.bezeichnung || '–';
            bezEl.setAttribute('title', c.bezeichnung || '');
        }
        document.getElementById('detail-artikelnummer').textContent = c.artikelnummer || '–';
        document.getElementById('detail-ean').textContent = c.ean || '–';
        document.getElementById('detail-beschreibung').textContent = c.beschreibung || '–';

        const lager = c.lagerbestand != null ? Number(c.lagerbestand) : 0;
        const mindest = c.mindestbestand != null ? Number(c.mindestbestand) : null;
        setDetailLagerAnzeige(lager, mindest);
        updateDetailStockBadges();

        updateDetailAutoNachbestellHinweis();

        const catContainer = document.getElementById('detail-categories');
        if ((c.categories || []).length === 0) {
            catContainer.innerHTML = '<span class="text-gray-500 dark:text-gray-400">Keine Kategorien</span>';
        } else {
            catContainer.innerHTML = c.categories.map(function(cat) {
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-gray-100 text-gray-700 dark:bg-gray-600/90 dark:text-gray-300">' + escapeHtml(cat.name) + '</span>';
            }).join('');
        }

        const dmList = document.getElementById('detail-device-models');
        if (!(c.device_models || []).length) {
            dmList.innerHTML = '<li class="py-1 text-gray-500 dark:text-gray-400 text-sm">Keine Zuordnung</li>';
        } else {
            dmList.innerHTML = c.device_models.map(function(dm) {
                return '<li class="py-1.5 text-gray-900 dark:text-white leading-snug">' + escapeHtml((dm.hersteller || '') + ' ' + (dm.modell || '').trim()) + '</li>';
            }).join('');
        }

        var lagerortHeading = document.getElementById('detail-lagerort-text');
        if (lagerortHeading) {
            var loHtml = buildDetailLagerortDisplayHtml(c);
            if (loHtml) {
                lagerortHeading.innerHTML = loHtml;
            } else {
                lagerortHeading.textContent = 'Kein Lagerort zugewiesen';
            }
        }

        if (c.shelf_id && c.spalte != null && c.fach != null) {
            window.consumableDetailShelf = {
                columns: Math.max(1, parseInt(c.shelf_spalten_anzahl, 10) || 5),
                rows: Math.max(1, parseInt(c.shelf_faecher_anzahl, 10) || 6),
                highlightCol: Math.max(0, parseInt(c.spalte, 10) - 1),
                highlightRow: Math.max(0, parseInt(c.fach, 10) - 1)
            };
            document.getElementById('shelf-3d-placeholder').classList.add('hidden');
            document.getElementById('shelf-3d-canvas').classList.remove('hidden');
            initShelf3D(window.consumableDetailShelf);
        } else {
            document.getElementById('shelf-3d-placeholder').classList.remove('hidden');
            document.getElementById('shelf-3d-canvas').classList.add('hidden');
        }
    }

    window.initShelf3D = async function(config) {
        const container = document.getElementById('shelf-3d-container');
        const canvasEl = document.getElementById('shelf-3d-canvas');
        if (!container || !canvasEl || !config) return;
        const cols = config.columns;
        const rows = config.rows;
        const hiCol = Math.min(config.highlightCol, cols - 1);
        const hiRow = Math.min(config.highlightRow, rows - 1);

        const THREE = await import('https://unpkg.com/three@0.160.0/build/three.module.js');

        const viewH = Math.max(container.clientHeight || 280, 200);
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0xf1f5f9);
        const camera = new THREE.PerspectiveCamera(42, container.clientWidth / viewH, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({ canvas: canvasEl, antialias: true, alpha: false });
        renderer.setSize(container.clientWidth, viewH);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        const ambient = new THREE.AmbientLight(0xffffff, 0.5);
        scene.add(ambient);
        const dirLight = new THREE.DirectionalLight(0xffffff, 0.85);
        dirLight.position.set(12, 14, 10);
        dirLight.castShadow = true;
        dirLight.shadow.mapSize.width = 1024;
        dirLight.shadow.mapSize.height = 1024;
        scene.add(dirLight);
        const fillLight = new THREE.DirectionalLight(0xb8d4e8, 0.25);
        fillLight.position.set(-8, 6, -6);
        scene.add(fillLight);

        // Schwerlastregal-Maße
        const cellWidth = 1.15;
        const cellHeight = 0.95;
        const cellDepth = 0.85;
        const beamThick = 0.08;
        const postSize = 0.12;
        const deckThick = 0.04;
        const gap = 0.03;
        const totalW = cols * cellWidth + (cols - 1) * gap + 2 * postSize;
        const totalH = 2 * postSize + (rows + 1) * beamThick + rows * cellHeight;
        const totalD = cellDepth + 2 * beamThick;

        // Einfache Orbit-Steuerung: Drehen per Maus, Zoom per Rad – Start: von vorn, nicht zu nah
        const maxDim = Math.max(totalW, totalH, totalD);
        let orbitRadius = maxDim * 1.65;
        let orbitTheta = 1.02;
        let orbitPhi = -0.52;
        let isDragging = false;
        let prevMouseX = 0, prevMouseY = 0;
        function setCameraPosition() {
            const x = orbitRadius * Math.cos(orbitPhi) * Math.sin(orbitTheta);
            const z = orbitRadius * Math.sin(orbitPhi) * Math.sin(orbitTheta);
            const y = orbitRadius * Math.cos(orbitTheta);
            camera.position.set(x, y, z);
            camera.lookAt(0, 0, 0);
        }
        setCameraPosition();
        canvasEl.style.touchAction = 'none';
        canvasEl.style.userSelect = 'none';

        function applyOrbitDelta(dx, dy) {
            orbitTheta += dy * 0.004;
            orbitPhi += dx * 0.004;
            orbitTheta = Math.max(0.15, Math.min(Math.PI * 0.45, orbitTheta));
            setCameraPosition();
        }

        canvasEl.addEventListener('mousedown', function(e) { if (e.button === 0) { isDragging = true; prevMouseX = e.clientX; prevMouseY = e.clientY; } });
        canvasEl.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            applyOrbitDelta(e.clientX - prevMouseX, prevMouseY - e.clientY);
            prevMouseX = e.clientX;
            prevMouseY = e.clientY;
        });
        canvasEl.addEventListener('mouseup', function(e) { if (e.button === 0) isDragging = false; });
        canvasEl.addEventListener('mouseleave', function() { isDragging = false; });

        canvasEl.addEventListener('touchstart', function(e) {
            if (e.touches.length !== 1) {
                isDragging = false;
                return;
            }
            isDragging = true;
            prevMouseX = e.touches[0].clientX;
            prevMouseY = e.touches[0].clientY;
        }, { passive: true });
        canvasEl.addEventListener('touchmove', function(e) {
            if (!isDragging || e.touches.length !== 1) return;
            e.preventDefault();
            var t = e.touches[0];
            applyOrbitDelta(t.clientX - prevMouseX, prevMouseY - t.clientY);
            prevMouseX = t.clientX;
            prevMouseY = t.clientY;
        }, { passive: false });
        canvasEl.addEventListener('touchend', function(e) {
            if (e.touches.length === 0) isDragging = false;
        });
        canvasEl.addEventListener('touchcancel', function() { isDragging = false; });

        container.addEventListener('wheel', function(e) {
            e.preventDefault();
            orbitRadius = Math.max(maxDim * 0.9, Math.min(maxDim * 3.5, orbitRadius + e.deltaY * 0.15));
            setCameraPosition();
        }, { passive: false });

        // Materialien: Stahl-Optik – Ständer/Träger heller, damit Fächer erkennbar sind
        const matFrame = new THREE.MeshStandardMaterial({
            color: 0x94a3b8,
            metalness: 0.65,
            roughness: 0.4
        });
        const matBeam = new THREE.MeshStandardMaterial({
            color: 0xcbd5e1,
            metalness: 0.6,
            roughness: 0.45
        });
        const matDeckDefault = new THREE.MeshStandardMaterial({
            color: 0x64748b,
            metalness: 0.5,
            roughness: 0.5
        });
        const matDeckHighlight = new THREE.MeshStandardMaterial({
            color: 0x10b981,
            metalness: 0.2,
            roughness: 0.6,
            emissive: 0x059669,
            emissiveIntensity: 0.55
        });
        const matHighlightBorder = new THREE.MeshBasicMaterial({
            color: 0x34d399,
            transparent: true,
            opacity: 0.9
        });
        const matHighlightFill = new THREE.MeshBasicMaterial({
            color: 0x10b981,
            transparent: true,
            opacity: 0.35,
            depthWrite: false
        });

        const halfW = totalW / 2;
        const halfD = totalD / 2;
        const halfH = totalH / 2;

        // Vertikale Ständer (4 Ecken)
        const postGeo = new THREE.BoxGeometry(postSize, totalH + 0.02, postSize);
        const postPositions = [
            [-halfW + postSize/2, 0, -halfD + postSize/2],
            [halfW - postSize/2, 0, -halfD + postSize/2],
            [-halfW + postSize/2, 0, halfD - postSize/2],
            [halfW - postSize/2, 0, halfD - postSize/2]
        ];
        postPositions.forEach(function(p) {
            const post = new THREE.Mesh(postGeo, matFrame);
            post.position.set(p[0], p[1], p[2]);
            post.castShadow = true;
            post.receiveShadow = true;
            scene.add(post);
        });

        // Träger-Y: unterster Träger oben auf Ständer, dann gleichmäßig cellHeight + beamThick
        const beamY = function(r) {
            return -halfH + postSize + beamThick / 2 + r * (cellHeight + beamThick);
        };
        // Horizontale Träger pro Ebene (vorne und hinten)
        const beamLength = totalW - 2 * postSize;
        const beamGeo = new THREE.BoxGeometry(beamLength, beamThick, beamThick);
        for (let r = 0; r <= rows; r++) {
            const y = beamY(r);
            [-1, 1].forEach(function(sign) {
                const beam = new THREE.Mesh(beamGeo, matBeam);
                beam.position.set(0, y, sign * (halfD - postSize/2));
                beam.castShadow = true;
                beam.receiveShadow = true;
                scene.add(beam);
            });
        }

        // Querträger links/rechts pro Ebene (Tiefe)
        const beamDepthGeo = new THREE.BoxGeometry(beamThick, beamThick, totalD - 2 * postSize);
        for (let r = 0; r <= rows; r++) {
            const y = beamY(r);
            [-1, 1].forEach(function(sign) {
                const beam = new THREE.Mesh(beamDepthGeo, matBeam);
                beam.position.set(sign * (halfW - postSize/2), y, 0);
                beam.castShadow = true;
                beam.receiveShadow = true;
                scene.add(beam);
            });
        }

        // Deckbleche: liegen auf den Trägern, Mitte der Zelle in der Höhe
        const deckInset = 0.008;
        const deckW = cellWidth - deckInset;
        const deckD = cellDepth - deckInset;
        const deckGeo = new THREE.BoxGeometry(deckW, deckThick, deckD);
        const baseX = -halfW + postSize + cellWidth / 2;
        const baseY = -halfH + postSize + beamThick + deckThick / 2;
        let highlightX = 0, highlightY = 0;
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                const isHighlight = (c === hiCol && r === hiRow);
                const x = baseX + c * (cellWidth + gap);
                const y = baseY + r * (cellHeight + beamThick);
                if (isHighlight) { highlightX = x; highlightY = y; }
                const deck = new THREE.Mesh(deckGeo, isHighlight ? matDeckHighlight : matDeckDefault);
                deck.position.set(x, y, 0);
                deck.castShadow = true;
                deck.receiveShadow = true;
                scene.add(deck);
            }
        }

        // Lagerposition: Volumen auf dem Deckblech ausfüllen (Boden = Oberseite Deck)
        const fillInset = 0.02;
        const fillW = cellWidth - fillInset;
        const fillH = cellHeight - fillInset;
        const fillD = cellDepth - fillInset;
        const highlightFill = new THREE.Mesh(
            new THREE.BoxGeometry(fillW, fillH, fillD),
            matHighlightFill
        );
        const fillY = highlightY + deckThick / 2 + fillH / 2;
        highlightFill.position.set(highlightX, fillY, 0);
        scene.add(highlightFill);

        // Rahmen auf dem Deckblech: Rechteck um die Lagerposition (in Deck-Ebene)
        const barThick = 0.04;
        const margin = 0.01;
        const frameY = highlightY + deckThick / 2 + barThick / 2;
        const frameW = cellWidth + margin;
        const frameD = cellDepth + margin;
        const topBar = new THREE.Mesh(new THREE.BoxGeometry(frameW, barThick, barThick), matHighlightBorder);
        topBar.position.set(highlightX, frameY, frameD / 2);
        scene.add(topBar);
        const bottomBar = new THREE.Mesh(new THREE.BoxGeometry(frameW, barThick, barThick), matHighlightBorder);
        bottomBar.position.set(highlightX, frameY, -frameD / 2);
        scene.add(bottomBar);
        const leftBar = new THREE.Mesh(new THREE.BoxGeometry(barThick, barThick, frameD), matHighlightBorder);
        leftBar.position.set(highlightX - frameW / 2, frameY, 0);
        scene.add(leftBar);
        const rightBar = new THREE.Mesh(new THREE.BoxGeometry(barThick, barThick, frameD), matHighlightBorder);
        rightBar.position.set(highlightX + frameW / 2, frameY, 0);
        scene.add(rightBar);

        // Leichte Rückwand (optional, für Stabilitäts-Optik)
        const backPanelGeo = new THREE.BoxGeometry(totalW - 2 * postSize, totalH - 2 * postSize, 0.02);
        const matBack = new THREE.MeshStandardMaterial({ color: 0x94a3b8, metalness: 0.5, roughness: 0.5 });
        const backPanel = new THREE.Mesh(backPanelGeo, matBack);
        backPanel.position.set(0, 0, halfD - 0.01);
        backPanel.receiveShadow = true;
        scene.add(backPanel);

        function animate() {
            requestAnimationFrame(animate);
            renderer.render(scene, camera);
        }
        animate();

        var resizeHandler = function() {
            const w = container.clientWidth;
            const h = Math.max(container.clientHeight || 280, 200);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        };
        window.addEventListener('resize', resizeHandler);
    };

    function postDetailStockDelta(delta, onDone) {
        fetch(consumablesApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'adjust_stock', consumable_id: consumableId, delta: delta })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var msg = delta > 0 ? '1 eingelagert' : '1 ausgelagert';
                msg += '. Neuer Bestand: ' + data.lagerbestand;
                if (data.unter_mindestbestand) msg += '\nUnter Mindestbestand.';
                if (data.bestellt && data.bestellnummer) msg += '\nBestellung: ' + data.bestellnummer;
                var toastType = data.unter_mindestbestand ? 'warning' : 'success';
                if (typeof showToast === 'function') showToast(msg, toastType);
                var md = data.mindestbestand != null ? data.mindestbestand : null;
                if (md === null) {
                    var mTxt = (document.getElementById('detail-mindestbestand') || {}).textContent;
                    if (mTxt && mTxt !== '–') {
                        var mp = parseInt(String(mTxt).trim(), 10);
                        if (!isNaN(mp)) md = mp;
                    }
                }
                setDetailLagerAnzeige(data.lagerbestand, md);
                if (detailConsumableCache) {
                    detailConsumableCache.lagerbestand = data.lagerbestand;
                    if (data.mindestbestand != null) detailConsumableCache.mindestbestand = data.mindestbestand;
                    if (data.has_open_order !== undefined) detailConsumableCache.has_open_order = data.has_open_order;
                    if (data.pending_stockin_after_delivery !== undefined) detailConsumableCache.pending_stockin_after_delivery = data.pending_stockin_after_delivery;
                    updateDetailStockBadges();
                    updateDetailAutoNachbestellHinweis();
                }
            } else {
                if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Buchen', 'error');
            }
        })
        .catch(function() {
            if (typeof showToast === 'function') showToast('Netzwerkfehler beim Buchen', 'error');
        })
        .finally(function() {
            if (typeof onDone === 'function') onDone();
        });
    }

    (function bindDetailStockActions() {
        var btnEin = document.getElementById('detail-btn-einlagern');
        var btnAus = document.getElementById('detail-btn-auslagern');
        if (!btnEin || !btnAus) return;

        function setBusy(busy) {
            btnEin.disabled = !!busy;
            btnAus.disabled = !!busy;
        }

        var hapticLastMs = 0;
        function hapticStockTap() {
            var now = Date.now();
            if (now - hapticLastMs < 120) return;
            hapticLastMs = now;
            if (typeof window.hapticLightTap === 'function') {
                window.hapticLightTap();
            } else {
                try {
                    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
                        navigator.vibrate(40);
                    }
                } catch (e) { /* noop */ }
            }
        }

        btnEin.addEventListener('click', function() {
            if (btnEin.disabled) return;
            hapticStockTap();
            setBusy(true);
            postDetailStockDelta(1, function() { setBusy(false); });
        });
        btnAus.addEventListener('click', function() {
            if (btnAus.disabled) return;
            hapticStockTap();
            setBusy(true);
            postDetailStockDelta(-1, function() { setBusy(false); });
        });
    })();

    fetch(consumablesApiUrl + '?id=' + consumableId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('detail-loading').classList.add('hidden');
            if (data.success && data.consumable) {
                document.getElementById('detail-content').classList.remove('hidden');
                renderDetail(data);
            } else {
                document.getElementById('detail-error').classList.remove('hidden');
                var navErr = document.getElementById('navInventoryDetailTitle');
                if (navErr) navErr.textContent = 'Nicht gefunden';
            }
        })
        .catch(function() {
            document.getElementById('detail-loading').classList.add('hidden');
            document.getElementById('detail-error').classList.remove('hidden');
            var navErr = document.getElementById('navInventoryDetailTitle');
            if (navErr) navErr.textContent = 'Nicht gefunden';
        });
})();
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php';
