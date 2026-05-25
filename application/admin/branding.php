<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

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

if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}
if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'admin/');
    exit;
}

// Farbbeschreibungen aus head.php (Kommentare)
$colorDescriptions = [
    50 => 'Hintergrund (BG)',
    100 => 'Karten-Hintergrund',
    120 => 'Karten-Rahmen',
    140 => 'Karten-Hover',
    200 => 'Primärtext',
    210 => 'Sekundärtext',
    220 => 'Gedämpfter / deaktivierter Text',
    230 => 'Trennlinien',
    240 => 'Platzhaltertext',
    250 => 'Primär-Akzent',
    260 => 'Akzent Hover',
    270 => 'Akzent Aktiv',
    280 => 'Link Hover',
    300 => 'Eingabefeld-Hintergrund',
    320 => 'Eingabefeld-Rahmen',
    340 => 'Eingabefeld Hover-Rahmen',
    360 => 'Eingabefeld Fokus-Rahmen',
    380 => 'Fokus-Glow',
    400 => 'Deaktivierter Eingabefeld-Text',
    420 => 'Primärer Button Hintergrund Standard',
    440 => 'Primärer Button Hintergrund Hover',
    460 => 'Primärer Button Hintergrund Aktiv',
    480 => 'Primärer Button Text',
    500 => 'Primärer Button deaktiviert Hintergrund',
    520 => 'Primärer Button deaktiviert Text',
    540 => 'Sekundärer Button Hintergrund Standard',
    560 => 'Sekundärer Button Rahmen',
    580 => 'Sekundärer Button Text',
    600 => 'Sekundärer Button Hover Hintergrund',
    620 => 'Sekundärer Button Aktiv Hintergrund',
    640 => 'Sekundärer Button Aktiv Rahmen',
    660 => 'Tertiärer Text Standard',
    680 => 'Tertiärer Text Hover',
    700 => 'Filter Standard Hintergrund',
    720 => 'Filter Standard Rahmen',
    740 => 'Filter Standard Text',
    760 => 'Filter Hover Hintergrund',
    780 => 'Filter Hover Text',
    800 => 'Filter Aktiv Hintergrund',
    820 => 'Filter Aktiv Rahmen',
    840 => 'Filter Aktiv Text',
    860 => 'Tabellen-Container Hintergrund',
    880 => 'Tabellen-Rahmen',
    900 => 'Tabellenkopf Hintergrund',
    920 => 'Tabellenkopf Text',
    940 => 'Tabellenzeile Hover',
    960 => 'Tabellenkopf Hover',
    980 => 'Zebrastreifen gerade Zeilen',
    1000 => 'Spaltenlinien',
    1020 => 'Ausgewählte Zeile Hintergrund',
    1040 => 'Erfolg (Success)',
    1060 => 'Warnung (Warning)',
    1080 => 'Fehler (Error)',
    1100 => 'Info'
];

$colorGroups = [
    'Grundfarben' => [50, 100, 120, 140],
    'Textfarben' => [200, 210, 220, 230, 240],
    'Akzent / Marke' => [250, 260, 270, 280],
    'Eingabefelder' => [300, 320, 340, 360, 380, 400],
    'Buttons – Primär' => [420, 440, 460, 480, 500, 520],
    'Buttons – Sekundär' => [540, 560, 580, 600, 620, 640],
    'Buttons – Tertiär / Link' => [660, 680],
    'Filter / Toggle' => [700, 720, 740, 760, 780, 800, 820, 840],
    'Tabellen' => [860, 880, 900, 920, 940, 960, 980, 1000, 1020],
    'Statusfarben' => [1040, 1060, 1080, 1100]
];

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
  <main>
    <div class="px-4">
      <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
        <div class="col-span-full mx-4 mt-4">
          <nav class="mb-4 flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
              <li class="inline-flex items-center">
                <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">Startseite</a>
              </li>
              <li>
                <div class="flex items-center">
                  <svg class="mx-1 h-4 w-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                  <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">Administration</a>
                </div>
              </li>
              <li aria-current="page">
                <div class="flex items-center">
                  <svg class="mx-1 h-4 w-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                  <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400">Erscheinungsbild</span>
                </div>
              </li>
            </ol>
          </nav>
          <div class="flex items-center justify-between mb-4">
            <div>
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Erscheinungsbild</h1>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Logo, Name und Farben der Anwendung anpassen</p>
            </div>
            <button type="button" id="resetBrandingBtn" class="inline-flex items-center px-4 py-2 text-sm font-medium text-amber-700 bg-amber-100 border border-amber-300 rounded-lg hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-700 dark:hover:bg-amber-900/50">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              Auf Standard zurücksetzen
            </button>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <!-- Tabs Erscheinungsbild -->
          <div class="mb-6 border-b border-gray-200 dark:border-primary-120">
            <nav class="flex gap-4" aria-label="Erscheinungsbild Tabs">
              <button type="button" id="tabLogo" class="branding-main-tab py-3 px-1 border-b-2 font-medium text-sm border-primary-250 text-primary-250">Logo & Name</button>
              <!-- Farbpalette-Tab deaktiviert - System verwendet immer Standardfarben -->
              <!-- <button type="button" id="tabColors" class="branding-main-tab py-3 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 dark:text-primary-220 dark:hover:text-primary-200">Farbpalette</button> -->
              <button type="button" id="tabLoginCards" class="branding-main-tab py-3 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 dark:text-primary-220 dark:hover:text-primary-200">Login-Karten</button>
            </nav>
          </div>

          <form id="brandingForm" class="space-y-8">
            <!-- Logo & Name -->
            <div id="panelLogo" class="branding-panel bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Logo & Name</h2>
              <p class="text-sm text-gray-600 dark:text-primary-210 mb-4">Logo erscheint in der Navigation, als Favicon und im Seitentitel. Der Name wird im Titel und in der Nav angezeigt.</p>
              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Logo hochladen</label>
                  <div class="flex gap-2">
                    <input type="file" id="logo_file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-420 file:text-white hover:file:bg-primary-440">
                    <input type="hidden" id="branding_logo" name="logo" value="">
                  </div>
                  <p class="mt-1 text-xs text-gray-500 dark:text-primary-220">JPG, PNG, WebP, GIF oder SVG, max. 5 MB</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-2">Name (zwei Teile, zweiter optional)</label>
                  <div class="flex gap-2 items-center">
                    <input type="text" id="branding_name_part1" name="name_part1" value="" placeholder="Serohub" class="flex-1 px-4 py-2 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200">
                    <span class="text-gray-500">+</span>
                    <input type="text" id="branding_name_part2" name="name_part2" value="" placeholder="optional" class="flex-1 px-4 py-2 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200">
                  </div>
                  <p class="mt-1 text-xs text-gray-500 dark:text-primary-220">Ein oder zwei Teile – der zweite ist optional. Anzeige in Nav und Seitentitel.</p>
                </div>
              </div>
              <div class="mt-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-2">Fehlerseiten</h3>
                <p class="text-sm text-gray-600 dark:text-primary-210 mb-4">Hier legst du fest, welche HTML-Template-Datei unter `errors/` für 403, 404 und 500 verwendet wird.</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                  <div>
                    <label for="error_page_403" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">403 (Keine Berechtigung)</label>
                    <input id="error_page_403" list="error_template_options_403" placeholder="z. B. 403.php oder /errors/403.php" class="w-full px-4 py-2 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200">
                    <datalist id="error_template_options_403"></datalist>
                  </div>
                  <div>
                    <label for="error_page_404" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">404 (Nicht gefunden)</label>
                    <input id="error_page_404" list="error_template_options_404" placeholder="z. B. 404.php oder /errors/404.php" class="w-full px-4 py-2 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200">
                    <datalist id="error_template_options_404"></datalist>
                  </div>
                  <div>
                    <label for="error_page_500" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">500 (Serverfehler)</label>
                    <input id="error_page_500" list="error_template_options_500" placeholder="z. B. 500.php oder /errors/500.php" class="w-full px-4 py-2 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200">
                    <datalist id="error_template_options_500"></datalist>
                  </div>
                </div>
              </div>
              <div class="mt-4 flex items-center gap-4">
                <div class="text-sm text-gray-600 dark:text-primary-210">Vorschau:</div>
                <div id="logoPreview" class="flex items-center p-2 rounded bg-gray-100 dark:bg-primary-140">
                  <img id="logoPreviewImg" src="" alt="Logo" class="h-10" onerror="this.style.display='none'">
                  <span class="text-2xl font-bold"><span id="namePreview1" class="italic dark:text-white text-primary-850">Serohub</span><span id="namePreview2" class="italic text-primary-420 -skew-x-6 inline-block hidden"></span></span>
                </div>
              </div>
            </div>

            <!-- Farben - Deaktiviert: System verwendet immer Standardfarben -->
            <!-- <div id="panelColors" class="branding-panel hidden bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Farbpalette</h2>
              <p class="text-sm text-gray-600 dark:text-primary-210 mb-4">Passen Sie die Farben für Dark Mode und Light Mode getrennt an.</p>
              
              <!-- Tabs Dark / Light -->
              <!-- <div class="mb-6 border-b border-gray-200 dark:border-primary-120">
                <nav class="flex gap-4" aria-label="Tabs">
                  <button type="button" id="tabDark" class="color-tab py-3 px-1 border-b-2 font-medium text-sm border-primary-250 text-primary-250">Dark Mode</button>
                  <button type="button" id="tabLight" class="color-tab py-3 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 dark:text-primary-220 dark:hover:text-primary-200">Light Mode</button>
                </nav>
              </div>
              
              <div id="colorsDark" class="color-panel">
                <p class="text-xs text-gray-500 dark:text-primary-220 mb-4">Farben für den Dark Mode (dunkles Theme)</p>
                <?php foreach ($colorGroups as $groupName => $keys): ?>
                <div class="mb-8">
                  <h3 class="text-sm font-medium text-gray-700 dark:text-primary-200 mb-3"><?php echo htmlspecialchars($groupName); ?></h3>
                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <?php foreach ($keys as $key): ?>
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-primary-140/50">
                      <input type="color" id="color_<?php echo $key; ?>" data-key="<?php echo $key; ?>" data-mode="dark" class="h-10 w-14 rounded border border-gray-300 dark:border-primary-320 cursor-pointer" title="<?php echo htmlspecialchars($colorDescriptions[$key] ?? ''); ?>">
                      <input type="text" id="color_text_<?php echo $key; ?>" data-key="<?php echo $key; ?>" data-mode="dark" class="flex-1 min-w-0 px-2 py-1.5 text-sm font-mono border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200">
                      <div class="flex-1 min-w-0">
                        <label for="color_<?php echo $key; ?>" class="block text-xs font-medium text-gray-600 dark:text-primary-220 truncate" title="<?php echo htmlspecialchars($colorDescriptions[$key] ?? ''); ?>"><?php echo htmlspecialchars($colorDescriptions[$key] ?? 'Farbe ' . $key); ?></label>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              
              <div id="colorsLight" class="color-panel hidden">
                <p class="text-xs text-gray-500 dark:text-primary-220 mb-4">Farben für den Light Mode (helles Theme)</p>
                <?php foreach ($colorGroups as $groupName => $keys): ?>
                <div class="mb-8">
                  <h3 class="text-sm font-medium text-gray-700 dark:text-primary-200 mb-3"><?php echo htmlspecialchars($groupName); ?></h3>
                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <?php foreach ($keys as $key): ?>
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-primary-140/50">
                      <input type="color" id="color_light_<?php echo $key; ?>" data-key="<?php echo $key; ?>" data-mode="light" class="h-10 w-14 rounded border border-gray-300 dark:border-primary-320 cursor-pointer" title="<?php echo htmlspecialchars($colorDescriptions[$key] ?? ''); ?>">
                      <input type="text" id="color_light_text_<?php echo $key; ?>" data-key="<?php echo $key; ?>" data-mode="light" class="flex-1 min-w-0 px-2 py-1.5 text-sm font-mono border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200">
                      <div class="flex-1 min-w-0">
                        <label for="color_light_<?php echo $key; ?>" class="block text-xs font-medium text-gray-600 dark:text-primary-220 truncate" title="<?php echo htmlspecialchars($colorDescriptions[$key] ?? ''); ?>"><?php echo htmlspecialchars($colorDescriptions[$key] ?? 'Farbe ' . $key); ?></label>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div> -->

            <!-- Login-Karten (Tab) -->
            <div id="panelLoginCards" class="branding-panel hidden bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Login-Karten</h2>
              <p class="text-sm text-gray-600 dark:text-primary-210 mb-4">Die Karten links auf der Login-Seite. Icon: Font Awesome, Bild-Upload oder SVG-Code. Reihenfolge mit Pfeilen ändern.</p>
              <div id="loginCardsList" class="space-y-3 mb-6">
                <!-- Einträge per JS -->
              </div>
              <button type="button" id="addLoginCardBtn" class="inline-flex items-center px-3 py-2 text-sm font-medium text-primary-580 bg-primary-540 border border-primary-560 rounded-lg hover:bg-primary-600 dark:bg-primary-600 dark:hover:bg-primary-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Karte hinzufügen
              </button>

              <hr class="my-8 border-gray-200 dark:border-primary-120">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Rechtliche Verlinkungen</h3>
              <p class="text-sm text-gray-600 dark:text-primary-210 mb-4">Links im Footer der Login-Seite (z. B. Datenschutz, Impressum).</p>
              <div id="loginFooterLinksList" class="space-y-3 mb-4">
                <!-- Einträge per JS -->
              </div>
              <button type="button" id="addFooterLinkBtn" class="inline-flex items-center px-3 py-2 text-sm font-medium text-primary-580 bg-primary-540 border border-primary-560 rounded-lg hover:bg-primary-600 dark:bg-primary-600 dark:hover:bg-primary-700 mb-6">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Link hinzufügen
              </button>

              <div class="mt-6 flex justify-end gap-2">
                <button type="button" id="saveLoginCardsBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary-420 rounded-lg hover:bg-primary-440">Login-Karten &amp; Links speichern</button>
              </div>
            </div>

            <div id="brandingFormActions" class="flex justify-end gap-2">
              <a href="<?php echo BASE_URL; ?>admin/" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-primary-540 dark:border-primary-560 dark:text-primary-580 dark:hover:bg-primary-600">Abbrechen</a>
              <button type="submit" id="saveBrandingBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary-420 rounded-lg hover:bg-primary-440">Speichern</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
(function() {
  const baseUrl = '<?php echo BASE_URL; ?>';
  const apiUrl = baseUrl + 'admin/api/branding.php';
  const uploadLogoUrl = baseUrl + 'admin/api/upload-logo.php';

  let currentData = { logo: '', name_part1: '', name_part2: '', error_page_403: '403.html', error_page_404: '404.html', error_page_500: '500.html', error_template_options: [], colors: {}, colors_light: {} };
  let defaults = { logo: '', name_part1: '', name_part2: '', error_page_403: '403.html', error_page_404: '404.html', error_page_500: '500.html', colors: {}, colors_light: {} };

  function updateErrorTemplateSuggestions(inputId, datalistId, options, selectedValue, fallbackValue) {
    const input = document.getElementById(inputId);
    const datalist = document.getElementById(datalistId);
    if (!input || !datalist) return;
    const safeOptions = Array.isArray(options) && options.length ? options : ['403.html', '404.html', '500.html'];
    datalist.innerHTML = safeOptions.map(function(option) {
      return '<option value="' + option.replace(/"/g, '&quot;') + '"></option>';
    }).join('');
    input.value = (selectedValue || fallbackValue || '').trim();
  }

  function toHexForColorInput(val) {
    if (!val) return '#000000';
    if (val.startsWith('#')) return val.length === 7 ? val : val + '0'.repeat(7 - val.length);
    const m = val.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (m) {
      const r = parseInt(m[1]).toString(16).padStart(2,'0');
      const g = parseInt(m[2]).toString(16).padStart(2,'0');
      const b = parseInt(m[3]).toString(16).padStart(2,'0');
      return '#' + r + g + b;
    }
    return '#000000';
  }

  function updatePreview() {
    const logo = document.getElementById('branding_logo');
    const img = document.getElementById('logoPreviewImg');
    const part1 = document.getElementById('branding_name_part1');
    const part2 = document.getElementById('branding_name_part2');
    if (logo && img) {
      img.src = baseUrl + (logo.value || 'assets/images/Serohub_Icon.png').replace(/^\//, '');
      img.style.display = '';
    }
    if (part1) document.getElementById('namePreview1').textContent = part1.value || 'Serohub';
    if (part2) {
      const p2 = document.getElementById('namePreview2');
      const raw2 = (part2.value || '').trim();
      p2.textContent = raw2;
      p2.classList.toggle('hidden', raw2 === '');
    }
  }

  function loadBranding() {
    fetch(apiUrl)
      .then(r => r.json())
      .then(data => {
        if (!data.success) return;
        currentData = {
          logo: data.logo,
          name_part1: data.name_part1,
          name_part2: data.name_part2,
          error_page_403: data.error_page_403 || '403.html',
          error_page_404: data.error_page_404 || '404.html',
          error_page_500: data.error_page_500 || '500.html',
          error_template_options: data.error_template_options || [],
          colors: data.colors || {},
          colors_light: data.colors_light || {}
        };
        defaults = data.defaults || currentData;

        document.getElementById('branding_logo').value = currentData.logo;
        document.getElementById('branding_name_part1').value = currentData.name_part1;
        document.getElementById('branding_name_part2').value = currentData.name_part2;
        updateErrorTemplateSuggestions('error_page_403', 'error_template_options_403', currentData.error_template_options, currentData.error_page_403, defaults.error_page_403 || '403.html');
        updateErrorTemplateSuggestions('error_page_404', 'error_template_options_404', currentData.error_template_options, currentData.error_page_404, defaults.error_page_404 || '404.html');
        updateErrorTemplateSuggestions('error_page_500', 'error_template_options_500', currentData.error_template_options, currentData.error_page_500, defaults.error_page_500 || '500.html');
        document.getElementById('logo_file').value = '';

        // Farben werden nicht mehr geladen - System verwendet immer Standardfarben
        // [currentData.colors, currentData.colors_light].forEach((colMap, idx) => {
        //   const prefix = idx === 0 ? '' : 'light_';
        //   const textPrefix = idx === 0 ? 'color_text_' : 'color_light_text_';
        //   Object.keys(colMap || {}).forEach(k => {
        //     const hex = toHexForColorInput(colMap[k]);
        //     const colorEl = document.getElementById('color_' + prefix + k);
        //     const textEl = document.getElementById(textPrefix + k);
        //     if (colorEl) colorEl.value = hex;
        //     if (textEl) textEl.value = colMap[k];
        //   });
        // });
        updatePreview();
      })
      .catch(e => console.error('Fehler beim Laden:', e));
  }

  // Event-Listener für Farben entfernt - Farben können nicht mehr geändert werden
  // document.querySelectorAll('input[type="color"]').forEach(el => {
  //   el.addEventListener('input', function() {
  //     const key = this.getAttribute('data-key');
  //     const mode = this.getAttribute('data-mode');
  //     const text = document.getElementById(mode === 'light' ? 'color_light_text_' + key : 'color_text_' + key);
  //     if (text) text.value = this.value;
  //   });
  // });
  // document.querySelectorAll('input[data-key][id^="color_text_"], input[data-key][id^="color_light_text_"]').forEach(el => {
  //   el.addEventListener('input', function() {
  //     const key = this.getAttribute('data-key');
  //     const mode = this.getAttribute('data-mode');
  //     const val = this.value.trim();
  //     const colorEl = document.getElementById(mode === 'light' ? 'color_light_' + key : 'color_' + key);
  //     if (colorEl && /^#[0-9a-fA-F]{6}$/.test(val)) colorEl.value = val;
  //   });
  // });

  document.getElementById('logo_file').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('logo', file);
    fetch(uploadLogoUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.path) {
          document.getElementById('branding_logo').value = data.path;
          updatePreview();
          if (typeof showToast === 'function') showToast('Logo hochgeladen', 'success');
        } else {
          if (typeof showToast === 'function') showToast(data.error || 'Upload fehlgeschlagen', 'error');
        }
      })
      .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Hochladen', 'error'); })
      .finally(() => { this.value = ''; });
  });

  document.getElementById('branding_name_part1').addEventListener('input', updatePreview);
  document.getElementById('branding_name_part2').addEventListener('input', updatePreview);

  // Event-Listener für Dark/Light Mode Tabs entfernt - Farbpalette deaktiviert
  // document.getElementById('tabDark').addEventListener('click', function() {
  //   document.getElementById('colorsDark').classList.remove('hidden');
  //   document.getElementById('colorsLight').classList.add('hidden');
  //   document.getElementById('tabDark').classList.add('border-primary-250', 'text-primary-250');
  //   document.getElementById('tabDark').classList.remove('border-transparent');
  //   document.getElementById('tabLight').classList.remove('border-primary-250', 'text-primary-250');
  //   document.getElementById('tabLight').classList.add('border-transparent', 'text-gray-500');
  // });
  // document.getElementById('tabLight').addEventListener('click', function() {
  //   document.getElementById('colorsLight').classList.remove('hidden');
  //   document.getElementById('colorsDark').classList.add('hidden');
  //   document.getElementById('tabLight').classList.add('border-primary-250', 'text-primary-250');
  //   document.getElementById('tabLight').classList.remove('border-transparent');
  //   document.getElementById('tabDark').classList.remove('border-primary-250', 'text-primary-250');
  //   document.getElementById('tabDark').classList.add('border-transparent', 'text-gray-500');
  // });

  // Haupt-Tabs: Logo | Login-Karten (Farbpalette deaktiviert)
  function setBrandingTab(activeId) {
    ['panelLogo', 'panelLoginCards'].forEach(function(id, i) {
      const panel = document.getElementById(id);
      const tabId = ['tabLogo', 'tabLoginCards'][i];
      const tab = document.getElementById(tabId);
      const isActive = (id === 'panelLogo' && activeId === 'tabLogo') || (id === 'panelLoginCards' && activeId === 'tabLoginCards');
      if (panel) panel.classList.toggle('hidden', !isActive);
      if (tab) {
        tab.classList.toggle('border-primary-250', isActive);
        tab.classList.toggle('text-primary-250', isActive);
        tab.classList.toggle('border-transparent', !isActive);
        tab.classList.toggle('text-gray-500', !isActive);
        tab.classList.toggle('text-gray-700', !isActive);
      }
    });
    document.getElementById('brandingFormActions').classList.toggle('hidden', activeId === 'tabLoginCards');
  }
  document.getElementById('tabLogo').addEventListener('click', function() { setBrandingTab('tabLogo'); });
  // Tab Farbpalette deaktiviert
  // document.getElementById('tabColors').addEventListener('click', function() { setBrandingTab('tabColors'); });
  document.getElementById('tabLoginCards').addEventListener('click', function() { setBrandingTab('tabLoginCards'); });

  // Login-Karten + Footer-Links
  const loginCardsApiUrl = baseUrl + 'admin/api/login-cards.php';
  const uploadLoginIconUrl = baseUrl + 'admin/api/upload-login-icon.php';
  let loginCardsData = [];
  let loginFooterLinksData = [];

  function escAttr(s) { return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function escSvgBody(s) { return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function renderLoginCards() {
    const container = document.getElementById('loginCardsList');
    if (!container) return;
    container.innerHTML = loginCardsData.map(function(card, index) {
      const it = card.icon_type || 'fa';
      const iconVal = card.icon || (it === 'fa' ? 'fas fa-link' : '');
      const label = escAttr(card.label);
      const value = escAttr(card.value);
      const href = escAttr(card.href);
      const iconFa = escAttr(it === 'fa' ? iconVal : '');
      const iconPath = escAttr(it === 'image' ? iconVal : '');
      const iconSvgBody = escSvgBody(it === 'svg' ? iconVal : '');
      const faDisplay = it === 'fa' ? '' : ' hidden';
      const imgDisplay = it === 'image' ? '' : ' hidden';
      const svgDisplay = it === 'svg' ? '' : ' hidden';
      const imgSrc = (it === 'image' && iconVal) ? (baseUrl + iconVal.replace(/^\//,'')) : '';
      return '<div class="login-card-row flex flex-wrap items-start gap-3 p-3 rounded-lg bg-gray-50 dark:bg-primary-140/50 border border-gray-200 dark:border-primary-120" data-index="' + index + '">' +
        '<div class="flex items-center gap-1">' +
        '<button type="button" class="login-card-move-up px-2 py-1 text-gray-500 hover:text-gray-700 dark:hover:text-primary-200" title="Nach oben" ' + (index === 0 ? 'disabled' : '') + '><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></button>' +
        '<button type="button" class="login-card-move-down px-2 py-1 text-gray-500 hover:text-gray-700 dark:hover:text-primary-200" title="Nach unten" ' + (index === loginCardsData.length - 1 ? 'disabled' : '') + '><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>' +
        '</div>' +
        '<input type="text" class="login-card-label w-28 px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200" placeholder="Label" value="' + label + '">' +
        '<input type="text" class="login-card-value flex-1 min-w-[120px] px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200" placeholder="Beschreibung" value="' + value + '">' +
        '<input type="text" class="login-card-href flex-1 min-w-[160px] px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200" placeholder="URL" value="' + href + '">' +
        '<div class="login-card-icon-cell flex flex-col gap-1 min-w-[200px]">' +
        '<select class="login-card-icon-type px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200">' +
        '<option value="fa"' + (it === 'fa' ? ' selected' : '') + '>Font Awesome</option>' +
        '<option value="image"' + (it === 'image' ? ' selected' : '') + '>Bild</option>' +
        '<option value="svg"' + (it === 'svg' ? ' selected' : '') + '>SVG-Code</option>' +
        '</select>' +
        '<div class="login-card-icon-fa' + faDisplay + '"><input type="text" class="login-card-icon-fa-input w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200" placeholder="z.B. fas fa-envelope" value="' + iconFa + '"></div>' +
        '<div class="login-card-icon-image' + imgDisplay + '"><input type="file" class="login-card-icon-file" accept="image/*"><input type="hidden" class="login-card-icon-path" value="' + iconPath + '"><span class="login-card-icon-preview">' + (imgSrc ? '<img src="' + escAttr(imgSrc) + '" alt="" class="h-8 w-8 object-contain">' : '') + '</span></div>' +
        '<div class="login-card-icon-svg' + svgDisplay + '"><textarea class="login-card-icon-svg-input w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 h-20 font-mono" placeholder="<svg>…</svg>">' + iconSvgBody + '</textarea></div>' +
        '</div>' +
        '<button type="button" class="login-card-remove px-2 py-1 text-red-600 hover:text-red-700 dark:text-red-400" title="Entfernen"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4m1 4h.01M17 20h5M2 20h5"/></svg></button>' +
        '</div>';
    }).join('');

    container.querySelectorAll('.login-card-move-up').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const row = btn.closest('.login-card-row');
        const i = parseInt(row.getAttribute('data-index'), 10);
        if (i <= 0) return;
        const t = loginCardsData[i];
        loginCardsData[i] = loginCardsData[i - 1];
        loginCardsData[i - 1] = t;
        renderLoginCards();
      });
    });
    container.querySelectorAll('.login-card-move-down').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const row = btn.closest('.login-card-row');
        const i = parseInt(row.getAttribute('data-index'), 10);
        if (i >= loginCardsData.length - 1) return;
        const t = loginCardsData[i];
        loginCardsData[i] = loginCardsData[i + 1];
        loginCardsData[i + 1] = t;
        renderLoginCards();
      });
    });
    container.querySelectorAll('.login-card-remove').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const row = btn.closest('.login-card-row');
        const i = parseInt(row.getAttribute('data-index'), 10);
        loginCardsData.splice(i, 1);
        renderLoginCards();
      });
    });

    container.querySelectorAll('.login-card-icon-type').forEach(function(sel) {
      sel.addEventListener('change', function() {
        const row = this.closest('.login-card-row');
        const fa = row.querySelector('.login-card-icon-fa');
        const img = row.querySelector('.login-card-icon-image');
        const svg = row.querySelector('.login-card-icon-svg');
        fa.classList.toggle('hidden', this.value !== 'fa');
        img.classList.toggle('hidden', this.value !== 'image');
        svg.classList.toggle('hidden', this.value !== 'svg');
      });
    });

    container.querySelectorAll('.login-card-icon-file').forEach(function(input) {
      input.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const row = this.closest('.login-card-row');
        const fd = new FormData();
        fd.append('icon', file);
        fetch(uploadLoginIconUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (data.success && data.path) {
              const pathInput = row.querySelector('.login-card-icon-path');
              const preview = row.querySelector('.login-card-icon-preview');
              if (pathInput) pathInput.value = data.path;
              if (preview) preview.innerHTML = '<img src="' + baseUrl + data.path.replace(/^\//,'') + '" alt="" class="h-8 w-8 object-contain">';
              if (typeof showToast === 'function') showToast('Bild hochgeladen', 'success');
            } else {
              if (typeof showToast === 'function') showToast(data.error || 'Upload fehlgeschlagen', 'error');
            }
          })
          .catch(function() { if (typeof showToast === 'function') showToast('Fehler beim Hochladen', 'error'); })
          .finally(function() { this.value = ''; }.bind(this));
      });
    });
  }

  function collectLoginCardsFromForm() {
    const rows = document.querySelectorAll('#loginCardsList .login-card-row');
    return Array.from(rows).map(function(row) {
      const iconType = (row.querySelector('.login-card-icon-type') || {}).value || 'fa';
      let icon = '';
      if (iconType === 'fa') {
        icon = (row.querySelector('.login-card-icon-fa-input') || {}).value || 'fas fa-link';
      } else if (iconType === 'image') {
        icon = (row.querySelector('.login-card-icon-path') || {}).value || '';
      } else {
        icon = (row.querySelector('.login-card-icon-svg-input') || {}).value || '';
      }
      return {
        label: (row.querySelector('.login-card-label') || {}).value || '',
        value: (row.querySelector('.login-card-value') || {}).value || '',
        href: (row.querySelector('.login-card-href') || {}).value || '',
        icon_type: iconType,
        icon: icon
      };
    });
  }

  function renderFooterLinks() {
    const container = document.getElementById('loginFooterLinksList');
    if (!container) return;
    container.innerHTML = loginFooterLinksData.map(function(link, index) {
      const label = escAttr(link.label);
      const url = escAttr(link.url);
      return '<div class="footer-link-row flex flex-wrap items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-primary-140/50 border border-gray-200 dark:border-primary-120" data-index="' + index + '">' +
        '<input type="text" class="footer-link-label w-32 px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200" placeholder="Label (z.B. Datenschutz)" value="' + label + '">' +
        '<input type="text" class="footer-link-url flex-1 min-w-[200px] px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200" placeholder="URL" value="' + url + '">' +
        '<button type="button" class="footer-link-remove px-2 py-1 text-red-600 hover:text-red-700 dark:text-red-400" title="Entfernen"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4m1 4h.01M17 20h5M2 20h5"/></svg></button>' +
        '</div>';
    }).join('');
    container.querySelectorAll('.footer-link-remove').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const row = btn.closest('.footer-link-row');
        const i = parseInt(row.getAttribute('data-index'), 10);
        loginFooterLinksData.splice(i, 1);
        renderFooterLinks();
      });
    });
  }

  function collectFooterLinksFromForm() {
    const rows = document.querySelectorAll('#loginFooterLinksList .footer-link-row');
    return Array.from(rows).map(function(row) {
      return {
        label: (row.querySelector('.footer-link-label') || {}).value || '',
        url: (row.querySelector('.footer-link-url') || {}).value || ''
      };
    });
  }

  fetch(loginCardsApiUrl)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        if (data.cards) {
          loginCardsData = data.cards;
          loginCardsData.forEach(function(c) {
            if (!c.icon_type) { c.icon_type = 'fa'; c.icon = c.icon || 'fas fa-link'; }
          });
        }
        if (data.footer_links) loginFooterLinksData = data.footer_links;
        renderLoginCards();
        renderFooterLinks();
      }
    })
    .catch(function() { loginCardsData = []; loginFooterLinksData = []; renderLoginCards(); renderFooterLinks(); });

  document.getElementById('addLoginCardBtn').addEventListener('click', function() {
    loginCardsData.push({ label: '', value: '', href: '', icon_type: 'fa', icon: 'fas fa-link' });
    renderLoginCards();
  });

  document.getElementById('addFooterLinkBtn').addEventListener('click', function() {
    loginFooterLinksData.push({ label: '', url: '' });
    renderFooterLinks();
  });

  document.getElementById('saveLoginCardsBtn').addEventListener('click', function() {
    const cards = collectLoginCardsFromForm();
    const footerLinks = collectFooterLinksFromForm();
    const btn = this;
    btn.disabled = true;
    fetch(loginCardsApiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cards: cards, footer_links: footerLinks })
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          loginCardsData = cards;
          loginFooterLinksData = footerLinks;
          renderLoginCards();
          renderFooterLinks();
          if (typeof showToast === 'function') showToast('Login-Karten & Links gespeichert', 'success');
        } else {
          if (typeof showToast === 'function') showToast(data.message || 'Fehler', 'error');
        }
      })
      .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
      })
      .finally(function() { btn.disabled = false; });
  });

  document.getElementById('brandingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Farben werden nicht mehr gespeichert - System verwendet immer Standardfarben
    // const colors = {};
    // const colorsLight = {};
    // document.querySelectorAll('input[data-key][id^="color_text_"]').forEach(el => {
    //   const key = el.getAttribute('data-key');
    //   colors[key] = el.value.trim() || (defaults.colors && defaults.colors[key]);
    // });
    // document.querySelectorAll('input[data-key][id^="color_light_text_"]').forEach(el => {
    //   const key = el.getAttribute('data-key');
    //   colorsLight[key] = el.value.trim() || (defaults.colors_light && defaults.colors_light[key]);
    // });
    const payload = {
      logo: document.getElementById('branding_logo').value.trim() || defaults.logo,
      name_part1: document.getElementById('branding_name_part1').value.trim() || defaults.name_part1,
      name_part2: document.getElementById('branding_name_part2').value.trim(),
      error_page_403: document.getElementById('error_page_403').value || defaults.error_page_403,
      error_page_404: document.getElementById('error_page_404').value || defaults.error_page_404,
      error_page_500: document.getElementById('error_page_500').value || defaults.error_page_500
      // colors: colors,
      // colors_light: colorsLight
    };
    const btn = document.getElementById('saveBrandingBtn');
    btn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          if (typeof showToast === 'function') showToast('Einstellungen gespeichert', 'success');
          loadBranding();
        } else {
          if (typeof showToast === 'function') showToast(data.message || 'Fehler', 'error');
        }
      })
      .catch(e => {
        if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
      })
      .finally(() => { btn.disabled = false; });
  });

  document.getElementById('resetBrandingBtn').addEventListener('click', function() {
    if (!confirm('Wirklich alle Einstellungen auf Standard zurücksetzen?')) return;
    const btn = this;
    btn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reset: true })
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          if (typeof showToast === 'function') showToast('Auf Standard zurückgesetzt', 'success');
          loadBranding();
        } else {
          if (typeof showToast === 'function') showToast(data.message || 'Fehler', 'error');
        }
      })
      .catch(e => {
        if (typeof showToast === 'function') showToast('Fehler beim Zurücksetzen', 'error');
      })
      .finally(() => { btn.disabled = false; });
  });

  loadBranding();
})();
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
