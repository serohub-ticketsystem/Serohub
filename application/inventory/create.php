<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'];
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$canEditConsumables = in_array($userRole, ['Admin', 'Techniker'], true);
if (!$canEditConsumables) {
    header('Location: ' . BASE_URL . 'inventory/');
    exit;
}

// Flowbite-ähnliche Floating-Labels (peer + placeholder=" "), Farben wie im restlichen UI
$invFloatInput = 'block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 dark:text-primary-200 bg-transparent rounded-base border border-gray-300 dark:border-primary-320 appearance-none focus:outline-none focus:ring-0 focus:border-primary-500 dark:focus:border-primary-400 peer';
$invFloatLabel = 'absolute text-sm text-gray-500 dark:text-primary-220 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white dark:bg-primary-100 px-2 peer-focus:px-2 peer-focus:text-primary-600 dark:peer-focus:text-primary-400 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 start-2.5 pointer-events-none';
$invFloatSelect = 'block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 dark:text-primary-200 bg-white dark:bg-primary-100 rounded-base border border-gray-300 dark:border-primary-320 focus:outline-none focus:ring-0 focus:border-primary-500 dark:focus:border-primary-400';
$invFloatSelectLabel = 'absolute text-sm text-gray-500 dark:text-primary-220 -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white dark:bg-primary-100 px-2 start-2.5 pointer-events-none';
$invFloatInputDm = 'peer block px-2.5 pb-2.5 pt-4 w-full text-xs text-gray-900 dark:text-primary-200 bg-transparent rounded-base border border-gray-300 dark:border-primary-320 appearance-none focus:outline-none focus:ring-0 focus:border-primary-500 dark:focus:border-primary-400';
$invFloatLabelDm = 'absolute text-xs text-gray-500 dark:text-primary-220 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white dark:bg-primary-100 px-2 peer-focus:px-2 peer-focus:text-primary-600 dark:peer-focus:text-primary-400 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 start-2.5 pointer-events-none';

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="kalender-page relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 max-lg:pt-[calc(env(safe-area-inset-top,0px)+3.5rem+1rem)] lg:pt-0 overflow-hidden max-lg:overflow-visible service-main-content app-mobile-no-root-overscroll">
  <main class="mx-4 mt-2 flex flex-col overflow-hidden min-h-0 max-lg:min-h-0 lg:min-h-[calc(100dvh-3.5rem)] max-lg:overflow-visible max-lg:mt-0 max-lg:mx-0 max-lg:px-4 service-main pb-8">
    <nav class="mb-4 flex-shrink-0 hidden lg:flex" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
            <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
            </svg>
            Startseite
          </a>
        </li>
        <li class="inline-flex items-center">
          <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
          </svg>
          <a href="<?php echo BASE_URL; ?>inventory/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 md:ms-2 dark:text-gray-400 dark:hover:text-white">Lager</a>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Neuer Artikel</span>
          </div>
        </li>
      </ol>
    </nav>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6 flex-shrink-0">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Neuer Artikel</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Legen Sie ein neues Verbrauchsmaterial im Lager an</p>
      </div>
      <a href="<?php echo BASE_URL; ?>inventory/" class="shrink-0 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 self-start sm:self-auto">
        Abbrechen
      </a>
    </div>
    <form id="consumableForm" class="grid grid-cols-1 xl:grid-cols-10 gap-6 xl:gap-0 xl:items-stretch w-full min-w-0 flex-1 min-h-0" novalidate>

      <!-- Links ~70 %: Kern -->
      <div class="xl:col-span-7 xl:pr-6 xl:min-w-0 space-y-4">
        <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-sm p-3 sm:p-4" aria-labelledby="inv-create-h-stamm">
          <h2 id="inv-create-h-stamm" class="text-base font-semibold text-gray-900 dark:text-white mb-3">Artikel</h2>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-4 gap-y-4">
            <div class="lg:col-span-2">
              <div class="relative">
                <input type="text" id="consumableBezeichnung" name="bezeichnung" required placeholder=" "
                       class="<?php echo htmlspecialchars($invFloatInput, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="consumableBezeichnung" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">Bezeichnung <span class="text-red-500">*</span></label>
              </div>
            </div>
            <div>
              <div class="relative">
                <input type="text" id="consumableArtikelnummer" name="artikelnummer" placeholder=" "
                       class="<?php echo htmlspecialchars($invFloatInput, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="consumableArtikelnummer" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">Artikelnr.</label>
              </div>
            </div>
            <div>
              <div class="relative">
                <input type="text" id="consumableEan" name="ean" placeholder=" "
                       class="<?php echo htmlspecialchars($invFloatInput . ' font-mono', ENT_QUOTES, 'UTF-8'); ?>">
                <label for="consumableEan" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">EAN</label>
              </div>
            </div>
            <div class="lg:col-span-2">
              <div class="relative">
                <input type="text" id="consumableBeschreibung" name="beschreibung" placeholder=" "
                       class="<?php echo htmlspecialchars($invFloatInput, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="consumableBeschreibung" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">Beschreibung</label>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-sm p-3 sm:p-4" aria-labelledby="inv-create-h-bestand">
          <h2 id="inv-create-h-bestand" class="text-base font-semibold text-gray-900 dark:text-white mb-3">Bestand &amp; Lagerort</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <div class="relative">
                <input type="number" id="consumableLagerbestand" name="lagerbestand" min="0" value="0" placeholder=" "
                       class="<?php echo htmlspecialchars($invFloatInput . ' tabular-nums', ENT_QUOTES, 'UTF-8'); ?>">
                <label for="consumableLagerbestand" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">Auf Lager</label>
              </div>
            </div>
            <div>
              <div class="relative">
                <input type="number" id="consumableMindestbestand" name="mindestbestand" min="0" placeholder=" "
                       class="<?php echo htmlspecialchars($invFloatInput . ' tabular-nums', ENT_QUOTES, 'UTF-8'); ?>">
                <label for="consumableMindestbestand" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">Meldebest.</label>
              </div>
            </div>
            <div class="sm:col-span-2 lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="sm:col-span-3 lg:col-span-1">
                <div class="relative">
                  <select id="consumableShelf" name="shelf_id" class="<?php echo htmlspecialchars($invFloatSelect, ENT_QUOTES, 'UTF-8'); ?>">
                    <option value="">—</option>
                  </select>
                  <label for="consumableShelf" class="<?php echo htmlspecialchars($invFloatSelectLabel, ENT_QUOTES, 'UTF-8'); ?>">Regal</label>
                </div>
              </div>
              <div>
                <div class="relative">
                  <input type="number" id="consumableSpalte" name="spalte" min="1" placeholder=" " class="<?php echo htmlspecialchars($invFloatInput . ' tabular-nums', ENT_QUOTES, 'UTF-8'); ?>">
                  <label for="consumableSpalte" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">Spalte</label>
                </div>
              </div>
              <div>
                <div class="relative">
                  <input type="number" id="consumableFach" name="fach" min="1" placeholder=" " class="<?php echo htmlspecialchars($invFloatInput . ' tabular-nums', ENT_QUOTES, 'UTF-8'); ?>">
                  <label for="consumableFach" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">Fach</label>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-sm p-3 sm:p-4" aria-labelledby="inv-create-h-geraete">
          <h2 id="inv-create-h-geraete" class="text-base font-semibold text-gray-900 dark:text-white mb-2">Gerätemodelle</h2>
          <p class="text-xs text-gray-500 dark:text-primary-240 mb-3">Optional. Vorlagen nur in diesem Browser.</p>
          <div class="rounded-lg border border-gray-200 dark:border-primary-120 bg-gray-50/90 dark:bg-primary-200/15 p-2 sm:p-3 mb-3 space-y-3">
            <div class="relative">
              <select id="invDmPresetSelect" title="Vorlage wählen" class="<?php echo htmlspecialchars($invFloatSelect . ' text-xs', ENT_QUOTES, 'UTF-8'); ?>"></select>
              <label for="invDmPresetSelect" class="<?php echo htmlspecialchars($invFloatSelectLabel . ' text-xs', ENT_QUOTES, 'UTF-8'); ?>">Vorlage</label>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" id="invDmPresetApplyBtn" class="px-2 py-1.5 text-xs font-medium rounded-lg border border-primary-300 dark:border-primary-500 text-primary-700 dark:text-primary-300">Übernehmen</button>
              <button type="button" id="invDmPresetSaveBtn" class="px-2 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-primary-320 text-gray-700 dark:text-primary-200">Speichern</button>
              <button type="button" id="invDmPresetDeleteBtn" class="px-2 py-1.5 text-xs font-medium rounded-lg border border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-300" title="Vorlage löschen" aria-label="Vorlage löschen">✕</button>
            </div>
          </div>
          <div id="deviceModelsScroll" class="rounded-lg border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-2">
            <div id="deviceModelsContainer" class="space-y-2 w-full"></div>
          </div>
          <button type="button" id="addDeviceModelBtn" class="mt-2 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-dashed border-gray-300 dark:border-primary-400 text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/20">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Zeile hinzufügen
          </button>
        </section>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 xl:hidden border-t border-gray-200 dark:border-gray-700">
          <a href="<?php echo BASE_URL; ?>inventory/" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Abbrechen</a>
          <button type="submit" class="inv-create-submit inline-flex items-center justify-center gap-2 px-8 py-3 text-sm font-semibold rounded-lg bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480">Artikel speichern</button>
        </div>
      </div>

      <!-- Rechts ~30 %: Zuordnungen, scrollbar -->
      <aside id="inv-create-aside" class="xl:col-span-3 xl:border-l xl:border-gray-200 dark:xl:border-primary-120 xl:pl-6 xl:min-w-0 flex flex-col xl:max-h-[calc(100dvh-10rem)] xl:sticky xl:top-24 xl:self-start rounded-2xl xl:rounded-none xl:border-0 border border-gray-200 dark:border-primary-120 bg-gray-100/80 dark:bg-primary-200/20 xl:bg-gray-100/50 dark:xl:bg-primary-200/15 p-4 sm:p-5 overflow-hidden">
        <div class="shrink-0 mb-4 pb-4 border-b border-gray-200/80 dark:border-primary-120">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">Zuordnungen</h2>
          <p class="text-xs text-gray-500 dark:text-primary-240 mt-1">Anklicken – dieser Bereich scrollt bei vielen Einträgen.</p>
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain pr-1 space-y-6 -mr-1 pb-2">
          <div>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-240 mb-3">Nachbestellung</h3>
            <div class="space-y-2">
              <label class="flex items-center gap-3 cursor-pointer rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 px-3 py-2.5 hover:border-primary-300 dark:hover:border-primary-400 transition-colors">
                <input type="checkbox" id="consumableAutoNachbestellen" name="auto_nachbestellen" class="w-4 h-4 rounded border-gray-300 dark:border-primary-400 text-primary-600 focus:ring-primary-500">
                <span class="text-sm font-medium text-gray-900 dark:text-white">Auto-Nachbestellung</span>
              </label>
            </div>
          </div>

          <div>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-240 mb-2">Firmen</h3>
            <p class="text-[11px] text-gray-500 dark:text-primary-240 mb-2">Optional.</p>
            <div id="consumableCompaniesContainer" class="flex flex-wrap gap-2"></div>
          </div>

          <div>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-240 mb-2">Kategorien</h3>
            <div id="consumableCategoriesContainer" class="flex flex-wrap gap-2 mb-2"></div>
            <div class="flex gap-2 items-end">
              <div class="relative flex-1 min-w-0">
                <input type="text" id="newCategoryName" placeholder=" " autocomplete="off" class="<?php echo htmlspecialchars($invFloatInput, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="newCategoryName" class="<?php echo htmlspecialchars($invFloatLabel, ENT_QUOTES, 'UTF-8'); ?>">Neue Kategorie</label>
              </div>
              <button type="button" id="addCategoryBtn" class="shrink-0 px-3 py-2 text-xs font-medium rounded-lg border border-primary-300 dark:border-primary-500 text-primary-700 dark:text-primary-300 bg-white dark:bg-primary-100">+</button>
            </div>
          </div>
        </div>
      </aside>

      <div class="hidden xl:flex xl:col-span-10 flex-row items-center justify-between gap-4 pt-4 mt-2 border-t border-gray-200 dark:border-gray-700">
        <a href="<?php echo BASE_URL; ?>inventory/" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Abbrechen</a>
        <button type="submit" class="inv-create-submit inline-flex items-center justify-center gap-2 px-10 py-3 text-sm font-semibold rounded-lg bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480">Artikel speichern</button>
      </div>
    </form>
  </main>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/nav-unsaved-changes.js"></script>
<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/inventory-device-model-presets.js"></script>
<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/inventory-device-model-auto-row.js"></script>
<script>
(function() {
    var invFloatDmIn = <?php echo json_encode($invFloatInputDm, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var invFloatDmLbl = <?php echo json_encode($invFloatLabelDm, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const baseUrl = typeof window.baseUrl !== 'undefined' ? window.baseUrl : '<?php echo BASE_URL; ?>';
    const consumablesApiUrl = baseUrl + 'inventory/api/consumables.php';
    const shelvesApiUrl = baseUrl + 'inventory/api/shelves.php';
    const devicesApiUrl = baseUrl + 'devices/api/devices.php';
    const companiesApiUrl = baseUrl + 'companies/api/companies.php';

    let invCategories = [];
    let invCompanies = [];
    let invShelves = [];
    let invManufacturers = [];
    let invModels = [];
    let deviceModelRowIndex = 0;

    function escapeHtml(s) {
        if (s == null) return '';
        const div = document.createElement('div');
        div.textContent = String(s);
        return div.innerHTML;
    }

    function loadCategories() {
        return fetch(consumablesApiUrl + '?action=get_categories')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                invCategories = d.success ? (d.categories || []) : [];
                renderCategoryCheckboxes([]);
            })
            .catch(function() { invCategories = []; renderCategoryCheckboxes([]); });
    }

    function renderCategoryCheckboxes(selectedIds) {
        const container = document.getElementById('consumableCategoriesContainer');
        if (!container) return;
        const ids = selectedIds || [];
        container.innerHTML = invCategories.map(function(cat) {
            const checked = ids.indexOf(Number(cat.id)) >= 0 ? ' checked' : '';
            return '<label class="inline-flex items-center gap-1.5 cursor-pointer rounded-md border border-gray-200 dark:border-primary-320 bg-white dark:bg-primary-100 px-2 py-1 text-xs text-gray-800 dark:text-primary-200 hover:border-primary-400 transition-colors"><input type="checkbox" class="consumable-category-cb rounded border-gray-300 dark:border-primary-400 text-primary-600 focus:ring-primary-500 w-3.5 h-3.5" value="' + cat.id + '"' + checked + '><span class="leading-tight">' + escapeHtml(cat.name) + '</span></label>';
        }).join('');
    }

    function getCategoryIds() {
        const cbs = document.querySelectorAll('#consumableCategoriesContainer .consumable-category-cb:checked');
        return Array.from(cbs).map(function(cb) { return parseInt(cb.value, 10); });
    }

    function renderCompanyCheckboxes(selectedIds) {
        const container = document.getElementById('consumableCompaniesContainer');
        if (!container) return;
        const ids = (selectedIds || []).map(function(x) { return Number(x); });
        container.innerHTML = (invCompanies || []).map(function(co) {
            const id = Number(co.id);
            const checked = ids.indexOf(id) >= 0 ? ' checked' : '';
            return '<label class="inline-flex items-center gap-1.5 cursor-pointer rounded-md border border-gray-200 dark:border-primary-320 bg-white dark:bg-primary-100 px-2 py-1 text-xs text-gray-800 dark:text-primary-200 hover:border-primary-400 transition-colors"><input type="checkbox" class="consumable-company-cb rounded border-gray-300 dark:border-primary-400 text-primary-600 focus:ring-primary-500 w-3.5 h-3.5" value="' + co.id + '"' + checked + '><span class="leading-tight">' + escapeHtml(co.name || '') + '</span></label>';
        }).join('');
    }

    function getCompanyIds() {
        const cbs = document.querySelectorAll('#consumableCompaniesContainer .consumable-company-cb:checked');
        return Array.from(cbs).map(function(cb) { return parseInt(cb.value, 10); }).filter(function(x) { return !isNaN(x) && x > 0; });
    }

    function loadCompanies() {
        return fetch(companiesApiUrl)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                invCompanies = (d.success && Array.isArray(d.companies)) ? d.companies : [];
                renderCompanyCheckboxes([]);
            })
            .catch(function() { invCompanies = []; renderCompanyCheckboxes([]); });
    }

    document.getElementById('addCategoryBtn').addEventListener('click', function() {
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
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    invCategories.push({ id: d.id, name: d.name });
                    renderCategoryCheckboxes(getCategoryIds());
                    if (input) input.value = '';
                    if (typeof showToast === 'function') showToast('Kategorie angelegt.', 'success');
                } else {
                    if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
                }
            })
            .catch(function() { if (typeof showToast === 'function') showToast('Fehler beim Anlegen.', 'error'); });
    });

    function addDeviceModelRow(hersteller, modell) {
        const container = document.getElementById('deviceModelsContainer');
        if (!container) return;
        const id = 'dm-' + (deviceModelRowIndex++);
        const row = document.createElement('div');
        row.className = 'grid grid-cols-[1fr_1fr_auto] gap-2 items-center w-full';
        row.dataset.rowId = id;
        row.innerHTML =
            '<div class="relative min-w-0">' +
            '<input type="text" id="' + id + '-h" name="dm_hersteller_' + id + '" placeholder=" " autocomplete="off" class="consumable-hersteller ' + invFloatDmIn + '" value="' + escapeHtml(hersteller || '') + '">' +
            '<label for="' + id + '-h" class="' + invFloatDmLbl + '">Hersteller</label>' +
            '<div class="inv-dm-suggestions hidden absolute z-30 top-full left-0 right-0 mt-0.5 w-full bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-230 rounded-lg shadow-lg max-h-40 overflow-auto text-xs" data-dm-type="hersteller"></div>' +
            '</div>' +
            '<div class="relative min-w-0">' +
            '<input type="text" id="' + id + '-m" name="dm_modell_' + id + '" placeholder=" " autocomplete="off" class="consumable-modell ' + invFloatDmIn + '" value="' + escapeHtml(modell || '') + '">' +
            '<label for="' + id + '-m" class="' + invFloatDmLbl + '">Modell</label>' +
            '<div class="inv-dm-suggestions hidden absolute z-30 top-full left-0 right-0 mt-0.5 w-full bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-230 rounded-lg shadow-lg max-h-40 overflow-auto text-xs" data-dm-type="modell"></div>' +
            '</div>' +
            '<button type="button" onclick="this.closest(\'[data-row-id]\').remove()" class="shrink-0 px-1.5 py-1 text-xs text-gray-500 hover:text-red-600 dark:text-primary-240 dark:hover:text-red-400 rounded-md border border-transparent hover:border-red-200 dark:hover:border-red-800/40" title="Entfernen" aria-label="Zeile entfernen">×</button>';
        container.appendChild(row);
    }

    function getDeviceModels() {
        const rows = document.querySelectorAll('#deviceModelsContainer [data-row-id]');
        const out = [];
        rows.forEach(function(row) {
            const h = (row.querySelector('.consumable-hersteller') || {}).value || '';
            const m = (row.querySelector('.consumable-modell') || {}).value || '';
            if (h.trim() || m.trim()) out.push({ hersteller: h.trim(), modell: m.trim() });
        });
        return out;
    }

    document.getElementById('addDeviceModelBtn').addEventListener('click', function() {
        addDeviceModelRow('', '');
    });

    addDeviceModelRow('', '');

    function loadManufacturers() {
        fetch(devicesApiUrl + '?action=get_manufacturers')
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.success) invManufacturers = d.manufacturers || []; })
            .catch(function() {});
    }
    function loadModels(manufacturer) {
        const url = manufacturer ? devicesApiUrl + '?action=get_models&manufacturer=' + encodeURIComponent(manufacturer) : devicesApiUrl + '?action=get_models';
        fetch(url).then(function(r) { return r.json(); }).then(function(d) { if (d.success) invModels = d.models || []; }).catch(function() {});
    }
    function loadShelves() {
        return fetch(shelvesApiUrl)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                invShelves = (d.success && d.shelves) ? d.shelves : [];
                var sel = document.getElementById('consumableShelf');
                if (sel) {
                    sel.innerHTML = '<option value="">Kein Regal</option>' + invShelves.map(function(s) {
                        return '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>';
                    }).join('');
                }
            })
            .catch(function() { invShelves = []; });
    }
    loadManufacturers();
    loadModels();
    loadShelves();

    function showSuggestions(inputEl, items, type) {
        const wrapper = inputEl.closest('.relative');
        if (!wrapper) return;
        const suggestionsDiv = wrapper.querySelector('.inv-dm-suggestions[data-dm-type="' + type + '"]');
        if (!suggestionsDiv) return;
        const value = (inputEl.value || '').toLowerCase().trim();
        const filtered = items.filter(function(item) { return item && item.toLowerCase().includes(value) && item.toLowerCase() !== value; });
        if (filtered.length === 0 || value.length === 0) {
            suggestionsDiv.classList.add('hidden');
            suggestionsDiv.innerHTML = '';
            return;
        }
        suggestionsDiv.innerHTML = filtered.slice(0, 12).map(function(item) {
            return '<div class="inv-dm-suggestion-item px-4 py-2 hover:bg-gray-100 dark:hover:bg-primary-140 cursor-pointer text-sm text-gray-900 dark:text-primary-200" data-value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</div>';
        }).join('');
        suggestionsDiv.classList.remove('hidden');
        suggestionsDiv.querySelectorAll('.inv-dm-suggestion-item').forEach(function(el) {
            el.addEventListener('click', function() {
                inputEl.value = this.getAttribute('data-value') || '';
                document.querySelectorAll('#deviceModelsContainer .inv-dm-suggestions').forEach(function(s) { s.classList.add('hidden'); });
                if (type === 'hersteller') loadModels(inputEl.value);
                try { inputEl.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
            });
        });
    }

    document.getElementById('deviceModelsContainer').addEventListener('input', function(e) {
        var el = e.target;
        if (el.classList.contains('consumable-hersteller')) showSuggestions(el, invManufacturers, 'hersteller');
        else if (el.classList.contains('consumable-modell')) showSuggestions(el, invModels, 'modell');
    });
    document.getElementById('deviceModelsContainer').addEventListener('focusout', function() {
        setTimeout(function() {
            document.querySelectorAll('#deviceModelsContainer .inv-dm-suggestions').forEach(function(s) { s.classList.add('hidden'); });
        }, 200);
    });

    function saveConsumable() {
        document.querySelectorAll('.inv-create-submit').forEach(function(b) { b.disabled = true; });
        var shelfVal = document.getElementById('consumableShelf').value;
        var spalteVal = document.getElementById('consumableSpalte').value;
        var fachVal = document.getElementById('consumableFach').value;
        var payload = {
            bezeichnung: document.getElementById('consumableBezeichnung').value.trim(),
            artikelnummer: document.getElementById('consumableArtikelnummer').value.trim() || null,
            ean: document.getElementById('consumableEan').value.trim() || null,
            beschreibung: document.getElementById('consumableBeschreibung').value.trim() || null,
            mindestbestand: (function() { var v = document.getElementById('consumableMindestbestand').value; return v === '' ? null : parseInt(v, 10); })(),
            auto_nachbestellen: document.getElementById('consumableAutoNachbestellen').checked,
            lagerbestand: parseInt(document.getElementById('consumableLagerbestand').value, 10) || 0,
            shelf_id: shelfVal === '' ? null : parseInt(shelfVal, 10),
            spalte: spalteVal === '' ? null : parseInt(spalteVal, 10),
            fach: fachVal === '' ? null : parseInt(fachVal, 10),
            category_ids: getCategoryIds(),
            company_ids: getCompanyIds(),
            device_models: getDeviceModels()
        };
        fetch(consumablesApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.querySelectorAll('.inv-create-submit').forEach(function(b) { b.disabled = false; });
                if (data.success) {
                    if (typeof showToast === 'function') showToast('Verbrauchsmaterial angelegt.', 'success');
                    window.location.href = baseUrl + 'inventory/';
                } else {
                    if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Speichern', 'error');
                }
            })
            .catch(function() {
                document.querySelectorAll('.inv-create-submit').forEach(function(b) { b.disabled = false; });
                if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
            });
    }

    document.getElementById('consumableForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveConsumable();
    });

    if (window.InvDeviceModelPresets) {
        InvDeviceModelPresets.bindUi({
            selectId: 'invDmPresetSelect',
            applyBtnId: 'invDmPresetApplyBtn',
            saveBtnId: 'invDmPresetSaveBtn',
            deleteBtnId: 'invDmPresetDeleteBtn',
            getModels: getDeviceModels,
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

    loadCompanies().then(function() {
        loadCategories();
    }).then(function() {
        if (window.NavUnsavedChanges) {
            NavUnsavedChanges.init({
                form: 'consumableForm',
                discardUrl: baseUrl + 'inventory/',
                onSave: saveConsumable
            });
        }
    });
})();
</script>
