<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$consumableId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$consumableId) {
    header('Location: ' . BASE_URL . 'inventory/');
    exit;
}

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

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-x-hidden">
  <main class="pt-4 pr-4 pb-4 pl-1">
    <div class="mx-4">
      <nav class="mb-4 flex" aria-label="Breadcrumb">
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
              <span id="edit-breadcrumb-title" class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Bearbeiten</span>
            </div>
          </li>
        </ol>
      </nav>

      <!-- Kopfzeile: Titel + Speichern/Abbrechen -->
      <div class="flex flex-col-reverse items-stretch justify-between gap-4 pb-4 md:flex-row md:items-center md:space-y-0">
        <div class="flex-1 min-w-0">
          <h1 id="edit-title" class="text-2xl font-bold text-gray-900 dark:text-white">Verbrauchsmaterial bearbeiten</h1>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Stammdaten, Bestand und zugeordnete Gerätemodelle</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <a id="edit-cancel-link" href="<?php echo BASE_URL; ?>inventory/" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 focus:ring-2 focus:ring-primary-500/30 focus:outline-none transition-colors">
            Abbrechen
          </a>
          <button type="submit" form="consumableForm" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-600 dark:bg-primary-500 hover:bg-primary-700 dark:hover:bg-primary-600 focus:ring-4 focus:ring-primary-500/30 focus:outline-none transition-colors">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Speichern
          </button>
        </div>
      </div>

      <div id="edit-loading" class="flex items-center justify-center py-16">
        <svg class="w-10 h-10 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>

      <form id="consumableForm" class="hidden bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
        <div class="p-6 space-y-6">
          <!-- Stammdaten -->
          <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
              <span class="w-1 h-4 rounded bg-primary-500"></span>
              Stammdaten
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <label for="consumableBezeichnung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Bezeichnung *</label>
                <input type="text" id="consumableBezeichnung" name="bezeichnung" required
                       placeholder="z.B. Toner schwarz"
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
              <div>
                <label for="consumableArtikelnummer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Artikelnummer</label>
                <input type="text" id="consumableArtikelnummer" name="artikelnummer"
                       placeholder="z.B. abc123"
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
              <div>
                <label for="consumableEan" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">EAN-Nummer</label>
                <input type="text" id="consumableEan" name="ean"
                       placeholder="z.B. 4006381333931"
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
              <div class="sm:col-span-2">
                <label for="consumableBeschreibung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Beschreibung</label>
                <input type="text" id="consumableBeschreibung" name="beschreibung"
                       placeholder="Optional"
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
              <div class="sm:col-span-2">
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Firmen</span>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Mehrfachauswahl möglich. Ohne Auswahl ist der Artikel keiner Firma zugeordnet.</p>
                <div id="consumableCompaniesContainer" class="flex flex-wrap gap-x-4 gap-y-2"></div>
              </div>
            </div>
          </div>

          <!-- Kategorien -->
          <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
              <span class="w-1 h-4 rounded bg-primary-500"></span>
              Kategorien
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Mehrfachauswahl möglich. Optional können Sie neue Kategorien anlegen.</p>
            <div id="consumableCategoriesContainer" class="flex flex-wrap gap-x-4 gap-y-2 mb-3"></div>
            <div class="flex flex-wrap items-center gap-2">
              <input type="text" id="newCategoryName" class="flex-1 min-w-[140px] px-3 py-2 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Neue Kategorie">
              <button type="button" id="addCategoryBtn" class="px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 border border-primary-300 dark:border-primary-600 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors">Hinzufügen</button>
            </div>
          </div>

          <!-- Bestand -->
          <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
              <span class="w-1 h-4 rounded bg-primary-500"></span>
              Bestand
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="consumableLagerbestand" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Auf Lager</label>
                <input type="number" id="consumableLagerbestand" name="lagerbestand" min="0" value="0"
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
              <div>
                <label for="consumableMindestbestand" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Mindestbestand</label>
                <input type="number" id="consumableMindestbestand" name="mindestbestand" min="0"
                       placeholder="Optional"
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
            </div>
            <div class="mt-3">
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="consumableAutoNachbestellen" name="auto_nachbestellen" class="w-4 h-4 rounded border-gray-300 dark:border-primary-320 text-primary-600 focus:ring-primary-500">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Automatisch nachbestellen (bei Unterschreitung vom Mindestbestand)</span>
              </label>
            </div>
            <div id="inv-edit-lagerort" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 scroll-mt-24 rounded-lg outline-none ring-offset-2 ring-offset-white dark:ring-offset-primary-100 focus-within:ring-2 focus-within:ring-primary-500/40" tabindex="-1">
              <div>
                <label for="consumableShelf" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Regal</label>
                <select id="consumableShelf" name="shelf_id" class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                  <option value="">— Kein Regal —</option>
                </select>
              </div>
              <div>
                <label for="consumableSpalte" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Spalte</label>
                <input type="number" id="consumableSpalte" name="spalte" min="1" placeholder="z.B. 1"
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
              <div>
                <label for="consumableFach" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Fach</label>
                <input type="number" id="consumableFach" name="fach" min="1" placeholder="z.B. 3"
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
              </div>
            </div>
          </div>

          <!-- Gerätemodelle -->
          <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
              <span class="w-1 h-4 rounded bg-primary-500"></span>
              Gerätemodelle
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Alle Geräte mit Hersteller + Modell erhalten dieses Material in der Geräte-Detailansicht. Optional.</p>
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 mb-3 p-3 rounded-lg bg-gray-50 dark:bg-primary-900/20 border border-gray-200 dark:border-primary-120">
              <span class="text-xs font-medium text-gray-600 dark:text-primary-220 shrink-0">Gerätemodell-Vorlagen</span>
              <select id="invDmPresetSelect" title="Gespeicherte Liste wählen" class="flex-1 min-w-[10rem] px-3 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200"></select>
              <div class="flex flex-wrap gap-2">
                <button type="button" id="invDmPresetApplyBtn" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-primary-300 dark:border-primary-500 text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/40">Übernehmen</button>
                <button type="button" id="invDmPresetSaveBtn" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-primary-320 text-gray-700 dark:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140">Als Vorlage speichern</button>
                <button type="button" id="invDmPresetDeleteBtn" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20">Löschen</button>
              </div>
            </div>
            <p class="text-[11px] text-gray-500 dark:text-primary-230 mb-3 -mt-1">Vorlagen gelten nur in diesem Browser (z.&nbsp;B. dieselbe Druckerliste wie auf einer Explosionszeichnung für viele Artikel).</p>
            <div id="deviceModelsContainer" class="space-y-2"></div>
            <button type="button" id="addDeviceModelBtn" class="mt-3 inline-flex items-center text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Gerätemodell hinzufügen
            </button>
          </div>
        </div>
      </form>

      <div id="edit-error" class="hidden py-16 text-center">
        <p class="text-red-600 dark:text-red-400 font-medium">Verbrauchsmaterial nicht gefunden.</p>
        <a href="<?php echo BASE_URL; ?>inventory/" class="mt-4 inline-block text-primary-600 dark:text-primary-400 hover:underline">Zurück zum Lager</a>
      </div>
    </div>
  </main>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/inventory-device-model-presets.js"></script>
<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/inventory-device-model-auto-row.js"></script>
<script>
(function() {
    const baseUrl = typeof window.baseUrl !== 'undefined' ? window.baseUrl : '<?php echo BASE_URL; ?>';
    const consumablesApiUrl = baseUrl + 'inventory/api/consumables.php';
    const shelvesApiUrl = baseUrl + 'inventory/api/shelves.php';
    const devicesApiUrl = baseUrl + 'devices/api/devices.php';
    const companiesApiUrl = baseUrl + 'companies/api/companies.php';
    const consumableId = <?php echo (int)$consumableId; ?>;

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
            })
            .catch(function() { invCategories = []; });
    }

    function renderCategoryCheckboxes(selectedIds) {
        const container = document.getElementById('consumableCategoriesContainer');
        if (!container) return;
        const ids = selectedIds || [];
        container.innerHTML = invCategories.map(function(cat) {
            const checked = ids.indexOf(Number(cat.id)) >= 0 ? ' checked' : '';
            return '<label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" class="consumable-category-cb rounded border-gray-300 dark:border-primary-320 text-primary-600 focus:ring-primary-500" value="' + cat.id + '"' + checked + '><span class="text-sm text-gray-700 dark:text-gray-300">' + escapeHtml(cat.name) + '</span></label>';
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
            return '<label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" class="consumable-company-cb rounded border-gray-300 dark:border-primary-320 text-primary-600 focus:ring-primary-500" value="' + co.id + '"' + checked + '><span class="text-sm text-gray-700 dark:text-gray-300">' + escapeHtml(co.name || '') + '</span></label>';
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
        row.className = 'flex gap-2 items-center';
        row.dataset.rowId = id;
        row.innerHTML =
            '<div class="relative flex-1">' +
            '<input type="text" placeholder="Hersteller" autocomplete="off" class="consumable-hersteller w-full bg-gray-50 dark:bg-primary-300 border border-gray-300 dark:border-primary-320 text-gray-900 dark:text-primary-200 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5" value="' + escapeHtml(hersteller || '') + '">' +
            '<div class="inv-dm-suggestions hidden absolute z-20 mt-1 w-full bg-white dark:bg-primary-100 border border-gray-300 dark:border-primary-120 rounded-lg shadow-lg max-h-60 overflow-auto" data-dm-type="hersteller"></div>' +
            '</div>' +
            '<div class="relative flex-1">' +
            '<input type="text" placeholder="Modell" autocomplete="off" class="consumable-modell w-full bg-gray-50 dark:bg-primary-300 border border-gray-300 dark:border-primary-320 text-gray-900 dark:text-primary-200 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5" value="' + escapeHtml(modell || '') + '">' +
            '<div class="inv-dm-suggestions hidden absolute z-20 mt-1 w-full bg-white dark:bg-primary-100 border border-gray-300 dark:border-primary-120 rounded-lg shadow-lg max-h-60 overflow-auto" data-dm-type="modell"></div>' +
            '</div>' +
            '<button type="button" onclick="this.closest(\'[data-row-id]\').remove()" class="p-2 text-gray-500 hover:text-red-600 dark:hover:text-red-400 flex-shrink-0" title="Entfernen" aria-label="Zeile entfernen">×</button>';
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
                    sel.innerHTML = '<option value="">— Kein Regal —</option>' + invShelves.map(function(s) {
                        return '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>';
                    }).join('');
                }
            })
            .catch(function() { invShelves = []; });
    }

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
            return '<div class="inv-dm-suggestion-item px-4 py-2 hover:bg-gray-100 dark:hover:bg-primary-140 cursor-pointer text-gray-900 dark:text-primary-200 text-sm" data-value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</div>';
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

    function loadConsumable() {
        fetch(consumablesApiUrl + '?id=' + consumableId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('edit-loading').classList.add('hidden');
                if (data.success && data.consumable) {
                    const c = data.consumable;
                    document.getElementById('edit-breadcrumb-title').textContent = c.bezeichnung || 'Bearbeiten';
                    document.getElementById('edit-title').textContent = (c.bezeichnung || 'Verbrauchsmaterial') + ' bearbeiten';
                    document.getElementById('edit-cancel-link').href = baseUrl + 'inventory/detail.php?id=' + consumableId;
                    
                    document.getElementById('consumableBezeichnung').value = c.bezeichnung || '';
                    document.getElementById('consumableArtikelnummer').value = c.artikelnummer || '';
                    document.getElementById('consumableEan').value = c.ean || '';
                    document.getElementById('consumableBeschreibung').value = c.beschreibung || '';
                    document.getElementById('consumableMindestbestand').value = c.mindestbestand != null ? c.mindestbestand : '';
                    document.getElementById('consumableAutoNachbestellen').checked = c.auto_nachbestellen === 1 || c.auto_nachbestellen === true;
                    document.getElementById('consumableLagerbestand').value = c.lagerbestand != null ? c.lagerbestand : '0';
                    document.getElementById('consumableSpalte').value = c.spalte != null && c.spalte !== '' ? c.spalte : '';
                    document.getElementById('consumableFach').value = c.fach != null && c.fach !== '' ? c.fach : '';
                    
                    if (c.shelf_id) {
                        const sel = document.getElementById('consumableShelf');
                        if (sel) {
                            setTimeout(function() {
                                sel.value = c.shelf_id;
                            }, 100);
                        }
                    }
                    
                    const container = document.getElementById('deviceModelsContainer');
                    container.innerHTML = '';
                    if (c.device_models && c.device_models.length) {
                        c.device_models.forEach(function(dm) {
                            addDeviceModelRow(dm.hersteller || '', dm.modell || '');
                        });
                    } else {
                        addDeviceModelRow('', '');
                    }
                    if (window.InvDeviceModelAutoRow) InvDeviceModelAutoRow.ensure(container, addDeviceModelRow);
                    
                    renderCategoryCheckboxes(c.category_ids || []);
                    var companyIdsForEdit = [];
                    if (Array.isArray(c.company_ids) && c.company_ids.length) {
                        companyIdsForEdit = c.company_ids.map(function(x) { return parseInt(x, 10); }).filter(function(x) { return !isNaN(x) && x > 0; });
                    } else if (c.company_id) {
                        var sc = parseInt(c.company_id, 10);
                        if (!isNaN(sc) && sc > 0) companyIdsForEdit = [sc];
                    }
                    renderCompanyCheckboxes(companyIdsForEdit);
                    document.getElementById('consumableForm').classList.remove('hidden');
                    if (window.location.hash === '#inv-edit-lagerort') {
                        setTimeout(function() {
                            var lo = document.getElementById('inv-edit-lagerort');
                            var shelfSel = document.getElementById('consumableShelf');
                            if (lo) lo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            if (shelfSel) {
                                try { shelfSel.focus(); } catch (e) {}
                            }
                        }, 100);
                    }
                } else {
                    document.getElementById('edit-error').classList.remove('hidden');
                }
            })
            .catch(function() {
                document.getElementById('edit-loading').classList.add('hidden');
                document.getElementById('edit-error').classList.remove('hidden');
            });
    }

    document.getElementById('consumableForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.querySelector('button[form="consumableForm"]');
        if (btn) btn.disabled = true;
        var shelfVal = document.getElementById('consumableShelf').value;
        var spalteVal = document.getElementById('consumableSpalte').value;
        var fachVal = document.getElementById('consumableFach').value;
        var payload = {
            id: consumableId,
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
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (btn) btn.disabled = false;
                if (data.success) {
                    if (typeof showToast === 'function') showToast('Verbrauchsmaterial gespeichert.', 'success');
                    window.location.href = baseUrl + 'inventory/detail.php?id=' + consumableId;
                } else {
                    if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Speichern', 'error');
                }
            })
            .catch(function() {
                if (btn) btn.disabled = false;
                if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
            });
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
        loadCategories().then(function() {
            loadManufacturers();
            loadModels();
            loadShelves().then(function() {
                loadConsumable();
            });
        });
    });
})();
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php';
