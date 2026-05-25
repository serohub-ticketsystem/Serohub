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
    $userCompanyId = $user['company_id'];
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$isAdminOrTechniker = ($userRole === 'Admin' || $userRole === 'Techniker');

$companies = [];
if ($isAdminOrTechniker) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // ignorieren
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
$baseUrl = BASE_URL;

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-hidden">
  <main class="pt-4 pr-4 pb-4 pl-1 flex flex-col overflow-hidden">
    <nav class="mb-4 flex flex-shrink-0" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo htmlspecialchars($baseUrl); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
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
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Verknüpfungen</span>
          </div>
        </li>
      </ol>
    </nav>

    <div class="relative">
      <div class="flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0">
        <div class="flex flex-col w-full space-y-3 lg:w-2/3 md:space-y-0 md:flex-row md:items-center">
          <form class="flex-1 w-full md:max-w-sm md:mr-2" onsubmit="return false;">
            <label for="search" class="text-sm font-medium text-gray-900 sr-only dark:text-white">Suche</label>
            <div class="relative" id="search-wrapper">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg aria-hidden="true" class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
              </div>
              <input type="search" id="search" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-820 focus:border-primary-820 dark:bg-primary-300 dark:border-primary-320 dark:placeholder-primary-210 dark:text-primary-200 dark:focus:ring-primary-820 dark:focus:border-primary-820 transition-colors" placeholder="Suchen...">
            </div>
          </form>
          <button type="button" id="reset-links-filters-btn" class="inline-flex items-center justify-center p-2 text-sm font-medium text-gray-600 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140 focus:outline-none ml-1" title="Filter zurücksetzen (A nach Z)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
          </button>
        </div>
        <?php if ($isAdminOrTechniker): ?>
        <div class="flex flex-shrink-0 pb-4 md:pb-0">
          <button type="button" id="downloadAddBtn" onclick="openModal()" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
            <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Verknüpfung hinzufügen
          </button>
        </div>
        <?php endif; ?>
      </div>

      <div class="flex flex-wrap pt-1 pb-4 border-t border-gray-200 dark:border-gray-700 mt-2">
        <div class="items-center hidden mt-3 mr-4 text-sm font-medium text-gray-900 md:flex dark:text-white">Sortierung:</div>
        <div class="flex flex-wrap">
          <div class="flex items-center mt-3 mr-4">
            <input id="sort-a-z" type="radio" value="a-z" checked name="sortierung" class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="sort-a-z" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">A nach Z</label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="sort-z-a" type="radio" value="z-a" name="sortierung" class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="sort-z-a" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Z nach A</label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="sort-neueste" type="radio" value="neueste" name="sortierung" class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="sort-neueste" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Zuletzt geändert</label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="sort-aelteste" type="radio" value="aelteste" name="sortierung" class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="sort-aelteste" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Älteste</label>
          </div>
        </div>
      </div>

      <div id="downloadsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">
          <i class="fas fa-spinner fa-spin mr-2"></i> Lade Verknüpfungen...
        </div>
      </div>
    </div>
  </main>
</div>

<?php if ($isAdminOrTechniker): ?>
<!-- Modal Verknüpfung hinzufügen/bearbeiten -->
<div id="downloadModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4" aria-labelledby="modalTitle" role="dialog" aria-modal="true">
  <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity cursor-pointer" aria-hidden="true" id="downloadModalOverlay" onclick="closeModal()"></div>
  <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
    <div class="pointer-events-auto w-full max-w-2xl max-h-[calc(100vh-2rem)] flex flex-col relative z-10">
      <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden flex flex-col max-h-[calc(100vh-2rem)]">
        <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 flex-shrink-0">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-primary-200" id="modalTitle">Verknüpfung hinzufügen</h3>
            <button type="button" onclick="closeModal()" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <form id="downloadForm">
            <input type="hidden" id="downloadId" name="id" value="">
            <div class="mb-4">
                <label for="downloadTitel" class="block mb-2 text-sm font-medium text-gray-900 dark:text-primary-200">Titel *</label>
                <input type="text" id="downloadTitel" name="titel" required class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-base bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 dark:focus:ring-primary-250/30 dark:focus:border-primary-250 transition-colors" placeholder="z. B. Handbuch PDF">
              </div>
              <div class="mb-4" role="radiogroup" aria-label="Typ der Verknüpfung">
                <span class="block mb-3 text-sm font-medium text-gray-900 dark:text-primary-200">Typ</span>
                  <div id="typeCardsContainer" class="flex gap-3 transition-all duration-300">
                    <label class="download-type-card flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all duration-300 hover:border-primary-400 dark:hover:border-primary-500 hover:bg-gray-50 dark:hover:bg-primary-140/50 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-primary-100 border-gray-200 dark:border-primary-230 bg-white dark:bg-primary-300/30 flex-1" data-value="link" data-type="link">
                      <input type="radio" name="typ" value="link" id="typLink" class="sr-only peer" onchange="toggleTypFields()">
                      <div class="flex items-center gap-2 mb-1.5 text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400 type-card-header">
                        <svg class="w-5 h-5 flex-shrink-0 type-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <span class="text-sm font-medium type-card-title">Link</span>
                      </div>
                      <span class="text-xs text-gray-500 dark:text-primary-240 leading-snug mb-3 type-card-description">Externe URL oder Webseite</span>
                      <div id="urlInputContainer" class="hidden mt-auto">
                        <label for="downloadUrl" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">URL *</label>
                        <input type="url" id="downloadUrl" name="url" class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-500 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400" placeholder="https://...">
                      </div>
                    </label>
                    <label class="download-type-card flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all duration-300 hover:border-primary-400 dark:hover:border-primary-500 hover:bg-gray-50 dark:hover:bg-primary-140/50 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-primary-100 border-gray-200 dark:border-primary-230 bg-white dark:bg-primary-300/30 flex-1" data-value="datei" data-type="datei">
                      <input type="radio" name="typ" value="datei" id="typDatei" class="sr-only peer" onchange="toggleTypFields()">
                      <div class="flex items-center gap-2 mb-1.5 text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400 type-card-header">
                        <svg class="w-5 h-5 flex-shrink-0 type-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span class="text-sm font-medium type-card-title">Datei</span>
                      </div>
                      <span class="text-xs text-gray-500 dark:text-primary-240 leading-snug mb-3 type-card-description">Datei hochladen</span>
                      <div id="fileInputContainer" class="hidden mt-auto">
                        <label for="downloadDatei" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Datei</label>
                        <input type="file" id="downloadDatei" name="datei" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-200 dark:file:bg-primary-900/30 dark:file:text-primary-400">
                      </div>
                    </label>
              </div>
            </div>
            <div class="mb-6 pt-2 border-t border-gray-200 dark:border-primary-120">
              <span class="block mb-3 text-sm font-medium text-gray-900 dark:text-primary-200">Sichtbarkeit</span>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3" role="radiogroup" aria-label="Sichtbarkeit">
                <label class="download-visibility-card flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all duration-300 hover:border-primary-400 dark:hover:border-primary-500 hover:bg-gray-50 dark:hover:bg-primary-140/50 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-primary-100 border-gray-200 dark:border-primary-230 bg-white dark:bg-primary-300/30">
                  <input type="radio" name="visibility_mode" value="intern" id="visibilityIntern" class="sr-only peer" checked onchange="handleVisibilityModeChange()">
                  <div class="text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400">
                    <div class="text-sm font-medium text-gray-900 dark:text-primary-200">Intern</div>
                    <div class="text-xs mt-1">Nur für Admin und Techniker sichtbar.</div>
                  </div>
                </label>
                <label class="download-visibility-card flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all duration-300 hover:border-primary-400 dark:hover:border-primary-500 hover:bg-gray-50 dark:hover:bg-primary-140/50 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-primary-100 border-gray-200 dark:border-primary-230 bg-white dark:bg-primary-300/30">
                  <input type="radio" name="visibility_mode" value="firma" id="visibilityFirma" class="sr-only peer" onchange="handleVisibilityModeChange()">
                  <div class="text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400">
                    <div class="text-sm font-medium text-gray-900 dark:text-primary-200">Firmenweit</div>
                    <div class="text-xs mt-1">Sichtbar innerhalb einer ausgewählten Firma.</div>
                  </div>
                </label>
                <label class="download-visibility-card flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all duration-300 hover:border-primary-400 dark:hover:border-primary-500 hover:bg-gray-50 dark:hover:bg-primary-140/50 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-primary-100 border-gray-200 dark:border-primary-230 bg-white dark:bg-primary-300/30">
                  <input type="radio" name="visibility_mode" value="alle" id="visibilityAlle" class="sr-only peer" onchange="handleVisibilityModeChange()">
                  <div class="text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400">
                    <div class="text-sm font-medium text-gray-900 dark:text-primary-200">Für alle</div>
                    <div class="text-xs mt-1">Sichtbar für alle Benutzer (nicht intern).</div>
                  </div>
                </label>
              </div>
            </div>

            <div id="companyScopeBlock" class="mb-6">
              <div class="rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-3">
                <div class="flex items-center gap-2 mb-2">
                  <div class="p-1.5 bg-gray-100 dark:bg-primary-140 rounded-base">
                    <svg class="w-4 h-4 text-gray-600 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <label class="block text-sm font-semibold text-gray-900 dark:text-white">Firma (firmenweit)</label>
                    <p class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">Wählen Sie die Firma für diese Verknüpfung</p>
                  </div>
                </div>
                <div class="p-2 bg-white dark:bg-primary-300 border border-gray-300 dark:border-primary-320 rounded-base">
                  <select id="downloadCompanyId" name="company_id" class="hidden" onchange="handleCompanySelectChange()">
                  <option value="">— Bitte wählen (Pflicht bei firmenweit) —</option>
                  <?php foreach ($companies as $c): ?>
                  <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                  <?php endforeach; ?>
                  </select>
                  <div class="mb-2 flex gap-2">
                    <input type="text" id="downloadCompanySearch" autocomplete="off" placeholder="Firma suchen..." class="flex-1 min-w-0 px-2 py-1.5 text-sm border border-gray-300 dark:border-primary-320 rounded-base bg-gray-50 dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                    <button type="button" onclick="clearCompanySelectionForLink()" class="px-2 py-1.5 text-sm text-red-600 hover:text-red-800 dark:text-red-400 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors shrink-0" aria-label="Auswahl löschen">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                  <div class="max-h-72 overflow-y-auto border border-gray-200 dark:border-primary-230 rounded-base -mx-0.5">
                    <table class="w-full text-sm text-left min-w-0">
                      <thead class="text-xs text-gray-700 dark:text-primary-210 uppercase bg-gray-50 dark:bg-primary-140 sticky top-0">
                        <tr>
                          <th class="px-2 py-1.5">Firma</th>
                        </tr>
                      </thead>
                      <tbody id="downloadCompanyTableBody" class="bg-white dark:bg-primary-100">
                        <?php foreach ($companies as $c): ?>
                        <?php $cName = htmlspecialchars($c['name']); $cNameJs = htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <tr class="company-row-link border-b border-gray-200 dark:border-primary-230 hover:bg-gray-50 dark:hover:bg-primary-140 cursor-pointer" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo $cName; ?>" onclick="event.stopPropagation(); selectCompanyForLink(<?php echo (int)$c['id']; ?>, '<?php echo $cNameJs; ?>')">
                          <td class="px-2 py-1.5 text-gray-900 dark:text-white font-medium"><?php echo $cName; ?></td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <p id="companySearchHint" class="mt-2 text-xs text-amber-700 dark:text-amber-400 hidden">Keine passende Firma gefunden.</p>
                  <p id="companyAutoSetInfo" class="mt-2 text-xs text-gray-500 dark:text-primary-240 hidden flex items-center gap-1">
                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Firma wurde automatisch aus dem Nav-Filter übernommen
                  </p>
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="px-4 pb-4 sm:px-6 sm:pb-6 flex-shrink-0 border-t border-gray-200 dark:border-primary-120 pt-4">
          <button type="submit" form="downloadForm" class="w-full sm:w-auto text-white inline-flex items-center justify-center bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
            <svg class="mr-1 -ml-1 w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
            </svg>
            Speichern
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
#search-wrapper.search-active input {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}
.dark #search-wrapper.search-active input {
  border-color: #60a5fa;
  box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
}
.download-type-card:has(input:checked),
.download-visibility-card:has(input:checked) {
  border-color: rgb(59 130 246);
  background-color: rgba(59, 130, 246, 0.08);
  box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.2);
}
.dark .download-type-card:has(input:checked),
.dark .download-visibility-card:has(input:checked) {
  border-color: rgb(96 165 250);
  background-color: rgba(59, 130, 246, 0.18);
  box-shadow: 0 0 0 1px rgba(96, 165, 250, 0.25);
}
.download-type-card:has(input:checked) .peer-checked\:text-primary-600,
.download-visibility-card:has(input:checked) .peer-checked\:text-primary-600 {
  color: rgb(37 99 235);
}
.dark .download-type-card:has(input:checked) .peer-checked\:text-primary-400,
.dark .download-visibility-card:has(input:checked) .peer-checked\:text-primary-400 {
  color: rgb(96 165 250);
}
.download-type-card {
  flex: 1 1 50%;
  transition: flex 0.3s ease;
}
.download-type-card.type-selected {
  flex: 0 0 90%;
}
.download-type-card.type-unselected {
  flex: 0 0 10%;
  overflow: hidden;
  min-width: 0;
}
.download-type-card.type-unselected .type-card-title,
.download-type-card.type-unselected .type-card-description {
  display: none;
}
.download-type-card.type-unselected .type-card-header {
  justify-content: center;
  width: 100%;
  margin: 0;
  padding: 0;
}
.download-type-card.type-unselected .type-card-header .type-card-icon {
  opacity: 1;
  transition: opacity 0.2s;
  margin: 0;
  flex-shrink: 0;
}
.download-type-card.type-unselected .type-card-header .type-card-title {
  display: none;
}
.download-type-card.type-unselected {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 1rem;
  min-height: 0;
}
.download-type-card.type-unselected > *:not(.type-card-header):not(input) {
  display: none;
}
.download-type-card.type-unselected .type-card-header {
  display: flex;
  justify-content: center;
  align-items: center;
}
.download-type-card.type-unselected > input[type="radio"] {
  pointer-events: none;
}
.download-type-card.type-unselected #urlInputContainer,
.download-type-card.type-unselected #fileInputContainer {
  display: none !important;
}
</style>

<script>
// baseUrl wird von nav.php gesetzt
const downloadsApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo addslashes($baseUrl); ?>') + 'links/api/downloads.php';
const downloadFileUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo addslashes($baseUrl); ?>') + 'links/download.php';
const defaultLogoUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo addslashes($baseUrl); ?>') + 'assets/images/sp-logo.png';
const isAdminOrTechniker = <?php echo $isAdminOrTechniker ? 'true' : 'false'; ?>;
const companiesApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo addslashes($baseUrl); ?>') + 'companies/api/companies.php';

let allDownloads = [];
let filteredDownloads = [];
let selectedCompanyId = null;
let currentSort = 'a-z';

var LINKS_FILTER_STORAGE_KEY = 'linksIndexFilters';

function getLinksFiltersState() {
  var searchEl = document.getElementById('search');
  var sortRadio = document.querySelector('input[name="sortierung"]:checked');
  return {
    search: searchEl ? searchEl.value : '',
    sortierung: sortRadio ? sortRadio.value : 'a-z'
  };
}

function saveLinksFiltersState() {
  try {
    localStorage.setItem(LINKS_FILTER_STORAGE_KEY, JSON.stringify(getLinksFiltersState()));
  } catch (e) {
    console.error('Fehler beim Speichern der Links-Filter', e);
  }
}

function restoreLinksFiltersState() {
  try {
    var raw = localStorage.getItem(LINKS_FILTER_STORAGE_KEY);
    if (!raw) return;
    var state = JSON.parse(raw);
    var searchEl = document.getElementById('search');
    if (state.search !== undefined && searchEl) searchEl.value = state.search || '';
    if (state.sortierung !== undefined) {
      var radio = document.querySelector('input[name="sortierung"][value="' + state.sortierung + '"]');
      if (radio) {
        radio.checked = true;
        currentSort = state.sortierung;
      }
    }
  } catch (e) {
    console.error('Fehler beim Wiederherstellen der Links-Filter', e);
  }
}

try {
  var companyOptionJson = localStorage.getItem('selectedUserOption');
  if (companyOptionJson) {
    var data = JSON.parse(companyOptionJson);
    if (data.id && data.id !== '0') selectedCompanyId = parseInt(data.id, 10);
  }
} catch (e) {}

document.addEventListener('DOMContentLoaded', function() {
  restoreLinksFiltersState();

  function updateSearchActiveState() {
    var wrapper = document.getElementById('search-wrapper');
    var searchEl = document.getElementById('search');
    if (!wrapper || !searchEl) return;
    wrapper.classList.toggle('search-active', (searchEl.value || '').trim() !== '');
  }
  updateSearchActiveState();

  document.getElementById('search').addEventListener('input', function() {
    updateSearchActiveState();
    filterDownloads();
    saveLinksFiltersState();
  });
  document.getElementById('downloadsContainer').addEventListener('click', function(e) {
    var card = e.target.closest('[data-href]');
    if (card && !e.target.closest('button') && !e.target.closest('a')) {
      window.open(card.getAttribute('data-href'), '_blank', 'noopener,noreferrer');
    }
  });
  document.getElementById('downloadsContainer').addEventListener('keydown', function(e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var card = e.target.closest('[data-href]');
    if (card && document.activeElement === card) {
      e.preventDefault();
      window.open(card.getAttribute('data-href'), '_blank', 'noopener,noreferrer');
    }
  });
  document.querySelectorAll('input[name="sortierung"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
      currentSort = this.value;
      filterDownloads();
      saveLinksFiltersState();
    });
  });

  var resetLinksFiltersBtn = document.getElementById('reset-links-filters-btn');
  if (resetLinksFiltersBtn) {
    resetLinksFiltersBtn.addEventListener('click', function() {
      var aZRadio = document.querySelector('input[name="sortierung"][value="a-z"]');
      if (aZRadio) {
        aZRadio.checked = true;
        currentSort = 'a-z';
      }
      var searchEl = document.getElementById('search');
      if (searchEl) searchEl.value = '';
      updateSearchActiveState();
      saveLinksFiltersState();
      filterDownloads();
    });
  }

  saveLinksFiltersState();

  window.addEventListener('storage', function(e) {
    if (e.key === 'selectedUserOption' && e.newValue) {
      try {
        var data = JSON.parse(e.newValue);
        selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id, 10) : null;
        filterDownloads();
      } catch (err) {}
    }
  });
  loadDownloads();
  var form = document.getElementById('downloadForm');
  if (form) form.addEventListener('submit', saveDownload);
  var companySearch = document.getElementById('downloadCompanySearch');
  if (companySearch) {
    companySearch.addEventListener('input', filterCompanyOptions);
  }
});

function loadDownloads() {
  try {
    var saved = localStorage.getItem('selectedUserOption');
    if (saved) {
      var data = JSON.parse(saved);
      selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id, 10) : null;
    } else {
      selectedCompanyId = null;
    }
  } catch (e) {
    selectedCompanyId = null;
  }
  fetch(downloadsApiUrl)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        allDownloads = data.downloads || [];
        filterDownloads();
      } else {
        document.getElementById('downloadsContainer').innerHTML = '<div class="col-span-full text-center text-red-500 py-8">' + (data.error || 'Fehler beim Laden') + '</div>';
      }
    })
    .catch(function() {
      document.getElementById('downloadsContainer').innerHTML = '<div class="col-span-full text-center text-red-500 py-8">Fehler beim Laden der Verknüpfungen</div>';
    });
}

function filterDownloads() {
  var term = (document.getElementById('search').value || '').toLowerCase();
  filteredDownloads = allDownloads.filter(function(d) {
    if (term) {
      var text = [d.titel, d.url, d.dateiname, d.company_name].filter(Boolean).join(' ').toLowerCase();
      if (text.indexOf(term) === -1) return false;
    }
    if (selectedCompanyId) {
      var cid = d.company_id ? parseInt(d.company_id, 10) : null;
      if (!cid || cid !== selectedCompanyId) return false;
    }
    return true;
  });
  filteredDownloads.sort(function(a, b) {
    switch (currentSort) {
      case 'z-a':
        var na = (a.titel || '').toLowerCase();
        var nb = (b.titel || '').toLowerCase();
        return nb.localeCompare(na);
      case 'neueste':
        return new Date(b.geaendert_datum || b.erstellt_datum) - new Date(a.geaendert_datum || a.erstellt_datum);
      case 'aelteste':
        return new Date(a.erstellt_datum) - new Date(b.erstellt_datum);
      default:
        var n1 = (a.titel || '').toLowerCase();
        var n2 = (b.titel || '').toLowerCase();
        return n1.localeCompare(n2);
    }
  });
  renderDownloads();
}

function renderDownloads() {
  var container = document.getElementById('downloadsContainer');
  if (filteredDownloads.length === 0) {
    container.innerHTML = '<div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">Keine Verknüpfungen vorhanden</div>';
    return;
  }
  container.innerHTML = filteredDownloads.map(function(d) {
    var datum = d.erstellt_datum ? new Date(d.erstellt_datum).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
    var ersteller = [d.ersteller_vorname, d.ersteller_nachname].filter(Boolean).join(' ') || '–';
    var sichtbarLabel = 'Für alle';
    if (parseInt(d.intern, 10) === 1) {
      sichtbarLabel = 'Intern';
    } else if (d.sichtbar_fuer === 'firma') {
      sichtbarLabel = 'Firmenweit';
    }
    if (parseInt(d.intern, 10) !== 1 && d.sichtbar_fuer === 'firma' && d.company_name) {
      sichtbarLabel += ' (' + escapeHtml(d.company_name) + ')';
    }
    var internBadge = parseInt(d.intern, 10) === 1 ? ' <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Intern</span>' : '';

    var logoUrl = defaultLogoUrl;
    if (d.company_logo) {
      logoUrl = (d.company_logo.indexOf('http') === 0) ? d.company_logo : (baseUrl || '<?php echo addslashes($baseUrl); ?>') + d.company_logo.replace(/^\//, '');
    }

    var linkOrFileLine = '';
    if (d.typ === 'link' && d.url) {
      var displayUrl = d.url;
      try {
        var u = new URL(d.url);
        displayUrl = u.hostname + (u.pathname !== '/' ? u.pathname : '');
        if (displayUrl.length > 42) displayUrl = displayUrl.substring(0, 39) + '…';
      } catch (e) {
        if (displayUrl.length > 42) displayUrl = displayUrl.substring(0, 39) + '…';
      }
      linkOrFileLine = '<a href="' + escapeHtml(d.url) + '" target="_blank" rel="noopener noreferrer" class="text-sm text-primary-600 dark:text-primary-400 flex items-center gap-1 truncate hover:underline" onclick="event.stopPropagation()">' +
        '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>' +
        '<span class="truncate">' + escapeHtml(displayUrl) + '</span></a>';
    } else if (d.typ === 'datei' && d.dateipfad) {
      linkOrFileLine = '<a href="' + downloadFileUrl + '?id=' + d.id + '" class="text-sm text-primary-600 dark:text-primary-400 flex items-center gap-1 truncate hover:underline" onclick="event.stopPropagation()">' +
        '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>' +
        '<span class="truncate">' + escapeHtml(d.dateiname || 'Datei') + '</span></a>';
    }

    var cardAttr = '';
    if (d.typ === 'link' && d.url) {
      cardAttr = ' data-href="' + escapeHtml(d.url) + '" role="button" tabindex="0"';
    }
    var cardClass = 'bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow' + (d.typ === 'link' && d.url ? ' cursor-pointer' : '');

    var adminBtns = '';
    if (isAdminOrTechniker) {
      adminBtns = '<div class="flex items-center gap-2 flex-shrink-0" onclick="event.stopPropagation()">' +
        '<button type="button" onclick="editDownload(' + d.id + ')" class="text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400 p-1 rounded" title="Bearbeiten"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>' +
        '<button type="button" onclick="deleteDownload(' + d.id + ')" class="text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 p-1 rounded" title="Löschen"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>' +
        '</div>';
    }

    return '<div class="' + cardClass + '"' + cardAttr + '>' +
      '<div class="flex items-start justify-between gap-3 mb-3">' +
        '<div class="flex-1 min-w-0">' +
          '<div class="flex items-center gap-3 mb-1">' +
            '<img src="' + escapeHtml(logoUrl) + '" alt="" class="w-8 h-8 rounded object-cover flex-shrink-0" onerror="this.src=\'' + defaultLogoUrl.replace(/'/g, "\\'") + '\'">' +
            '<h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">' + escapeHtml(d.titel) + '</h3>' +
          '</div>' +
          (linkOrFileLine ? '<div class="ml-11">' + linkOrFileLine + '</div>' : '') +
        '</div>' +
        adminBtns +
      '</div>' +
      '<p class="text-sm text-gray-500 dark:text-gray-400 ml-11">' + escapeHtml(sichtbarLabel) + internBadge + '</p>' +
      '<div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-3 mt-3 border-t border-gray-200 dark:border-gray-700">' +
        '<span>Erstellt: ' + datum + '</span>' +
        '<span>von ' + escapeHtml(ersteller) + '</span>' +
      '</div>' +
    '</div>';
  }).join('');
}

function escapeHtml(text) {
  if (!text) return '';
  var div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function toggleTypFields() {
  var isLink = document.getElementById('typLink').checked;
  var isDatei = document.getElementById('typDatei').checked;
  var linkCard = document.querySelector('.download-type-card[data-type="link"]');
  var dateiCard = document.querySelector('.download-type-card[data-type="datei"]');
  var urlInputContainer = document.getElementById('urlInputContainer');
  var fileInputContainer = document.getElementById('fileInputContainer');
  
  // Wenn nichts ausgewählt ist, beide Cards auf 50/50 zurücksetzen
  if (!isLink && !isDatei) {
    linkCard.classList.remove('type-selected', 'type-unselected');
    dateiCard.classList.remove('type-selected', 'type-unselected');
    urlInputContainer.classList.add('hidden');
    fileInputContainer.classList.add('hidden');
    document.getElementById('downloadUrl').required = false;
    document.getElementById('downloadDatei').required = false;
    return;
  }
  
  // Input-Felder anzeigen/verstecken
  if (isLink) {
    urlInputContainer.classList.remove('hidden');
    fileInputContainer.classList.add('hidden');
    document.getElementById('downloadUrl').required = true;
    document.getElementById('downloadDatei').required = false;
    
    // Cards animieren
    linkCard.classList.add('type-selected');
    linkCard.classList.remove('type-unselected');
    dateiCard.classList.add('type-unselected');
    dateiCard.classList.remove('type-selected');
  } else if (isDatei) {
    fileInputContainer.classList.remove('hidden');
    urlInputContainer.classList.add('hidden');
    document.getElementById('downloadUrl').required = false;
    document.getElementById('downloadDatei').required = true;
    
    // Cards animieren
    dateiCard.classList.add('type-selected');
    dateiCard.classList.remove('type-unselected');
    linkCard.classList.add('type-unselected');
    linkCard.classList.remove('type-selected');
  }
}

function handleCompanySelectChange() {
  // Wenn Firma manuell geändert wird, Info verstecken
  var companyAutoSetInfo = document.getElementById('companyAutoSetInfo');
  if (companyAutoSetInfo) {
    companyAutoSetInfo.classList.add('hidden');
  }
  var companySelect = document.getElementById('downloadCompanyId');
  var visFirma = document.getElementById('visibilityFirma');
  syncSelectedCompanyRow();
  if (companySelect && companySelect.value && visFirma) {
    visFirma.checked = true;
    updateVisibilityMode();
  }
}

function filterCompanyOptions() {
  var searchEl = document.getElementById('downloadCompanySearch');
  var rows = document.querySelectorAll('#downloadCompanyTableBody .company-row-link');
  var companySearchHint = document.getElementById('companySearchHint');
  if (!searchEl) return;

  var query = (searchEl.value || '').trim().toLowerCase();
  var hasVisible = false;
  Array.prototype.forEach.call(rows, function(row) {
    var text = (row.textContent || '').toLowerCase();
    var visible = query === '' || text.indexOf(query) !== -1;
    row.style.display = visible ? '' : 'none';
    if (visible) hasVisible = true;
  });

  if (companySearchHint) {
    companySearchHint.classList.toggle('hidden', query === '' || hasVisible);
  }
}

function syncSelectedCompanyRow() {
  var companySelect = document.getElementById('downloadCompanyId');
  var selectedId = companySelect ? String(companySelect.value || '') : '';
  var rows = document.querySelectorAll('#downloadCompanyTableBody .company-row-link');
  rows.forEach(function(row) {
    var rowId = row.getAttribute('data-id') || '';
    row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    if (selectedId !== '' && rowId === selectedId) {
      row.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
    }
  });
}

function selectCompanyForLink(id, name) {
  var companySelect = document.getElementById('downloadCompanyId');
  if (companySelect) {
    companySelect.value = String(id);
  }
  syncSelectedCompanyRow();
  handleCompanySelectChange();
}

function clearCompanySelectionForLink() {
  var companySelect = document.getElementById('downloadCompanyId');
  var companySearch = document.getElementById('downloadCompanySearch');
  if (companySelect) companySelect.value = '';
  if (companySearch) companySearch.value = '';
  filterCompanyOptions();
  syncSelectedCompanyRow();
}

function handleVisibilityModeChange() {
  updateVisibilityMode();
}

function updateVisibilityMode() {
  var visFirma = document.getElementById('visibilityFirma');
  var companyScopeBlock = document.getElementById('companyScopeBlock');
  var companySelect = document.getElementById('downloadCompanyId');
  var companySearch = document.getElementById('downloadCompanySearch');
  var companyAutoSetInfo = document.getElementById('companyAutoSetInfo');
  var companySearchHint = document.getElementById('companySearchHint');
  var isFirma = !!(visFirma && visFirma.checked);

  if (companyScopeBlock) {
    companyScopeBlock.classList.toggle('hidden', !isFirma);
  }

  if (!isFirma) {
    if (companySelect) companySelect.value = '';
    if (companySearch) companySearch.value = '';
    if (companyAutoSetInfo) companyAutoSetInfo.classList.add('hidden');
    if (companySearchHint) companySearchHint.classList.add('hidden');
    filterCompanyOptions();
  }
  syncSelectedCompanyRow();
}


function openModal(editId) {
  document.getElementById('modalTitle').textContent = editId ? 'Verknüpfung bearbeiten' : 'Verknüpfung hinzufügen';
  document.getElementById('downloadId').value = editId || '';
  document.getElementById('downloadForm').reset();
  document.getElementById('downloadId').value = editId || '';
  
  // Typ-Cards zurücksetzen - beide gleich groß (50/50)
  var linkCard = document.querySelector('.download-type-card[data-type="link"]');
  var dateiCard = document.querySelector('.download-type-card[data-type="datei"]');
  if (linkCard && dateiCard) {
    linkCard.classList.remove('type-selected', 'type-unselected');
    dateiCard.classList.remove('type-selected', 'type-unselected');
  }
  document.getElementById('urlInputContainer').classList.add('hidden');
  document.getElementById('fileInputContainer').classList.add('hidden');
  document.getElementById('downloadDatei').value = '';
  
  // Keine Card standardmäßig ausgewählt - beide gleich groß
  document.getElementById('typLink').checked = false;
  document.getElementById('typDatei').checked = false;
  
  // Firma zurücksetzen
  document.getElementById('downloadCompanyId').value = '';
  document.getElementById('downloadCompanySearch').value = '';
  document.getElementById('visibilityFirma').checked = false;
  document.getElementById('visibilityAlle').checked = false;
  document.getElementById('visibilityIntern').checked = true;
  filterCompanyOptions();
  syncSelectedCompanyRow();
  updateVisibilityMode();
  
  // Firma automatisch setzen wenn im Nav-Filter aktiv (nur bei neuem Eintrag)
  if (!editId) {
    try {
      var savedSelection = localStorage.getItem('selectedUserOption');
      if (savedSelection) {
        var data = JSON.parse(savedSelection);
        if (data.id && data.id !== '0') {
          var companySelect = document.getElementById('downloadCompanyId');
          var companyId = parseInt(data.id, 10);
          companySelect.value = companyId;
          var companyAutoSetInfo = document.getElementById('companyAutoSetInfo');
          if (companyAutoSetInfo) {
            companyAutoSetInfo.classList.remove('hidden');
          }
          // Kunden laden
          handleCompanySelectChange();
        }
      }
    } catch (e) {
      console.error('Fehler beim Lesen der Firmenauswahl', e);
    }
  }
  
  toggleTypFields();
  
  if (editId) {
    fetch(downloadsApiUrl + '?id=' + editId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.download) {
          var d = data.download;
          document.getElementById('downloadTitel').value = d.titel || '';
          if (d.typ === 'datei') {
            document.getElementById('typDatei').checked = true;
          } else {
            document.getElementById('typLink').checked = true;
            document.getElementById('downloadUrl').value = d.url || '';
          }
          // Werte setzen
          document.getElementById('downloadCompanyId').value = d.company_id || '';
          document.getElementById('downloadCompanySearch').value = '';
          filterCompanyOptions();
          syncSelectedCompanyRow();
          
          // Info verstecken beim Bearbeiten
          var companyAutoSetInfo = document.getElementById('companyAutoSetInfo');
          if (companyAutoSetInfo) {
            companyAutoSetInfo.classList.add('hidden');
          }
          
          var isIntern = parseInt(d.intern, 10) === 1;
          var isFirma = !isIntern && d.sichtbar_fuer === 'firma';
          document.getElementById('visibilityIntern').checked = isIntern;
          document.getElementById('visibilityFirma').checked = isFirma;
          document.getElementById('visibilityAlle').checked = !isIntern && !isFirma;
          updateVisibilityMode();
          
          toggleTypFields();
        }
      });
  }
  
  var modal = document.getElementById('downloadModal');
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
}

function closeModal() {
  var modal = document.getElementById('downloadModal');
  modal.setAttribute('aria-hidden', 'true');
  modal.classList.add('hidden');
  var btn = document.getElementById('downloadAddBtn');
  if (btn) btn.focus();
}

function editDownload(id) {
  openModal(id);
}

function saveDownload(e) {
  e.preventDefault();
  var id = document.getElementById('downloadId').value;
  var titel = document.getElementById('downloadTitel').value.trim();
  var typ = document.getElementById('typLink').checked ? 'link' : 'datei';
  var url = document.getElementById('downloadUrl').value.trim();
  var companyId = document.getElementById('downloadCompanyId').value || null;
  var visibilityMode = (document.querySelector('input[name="visibility_mode"]:checked') || {}).value || 'intern';
  var intern = document.getElementById('visibilityIntern') && document.getElementById('visibilityIntern').checked;
  
  // Modi: intern, firmenweit oder für alle
  var sichtbarFuer = intern ? 'alle' : (visibilityMode === 'firma' ? 'firma' : 'alle');

  if (!titel) {
    if (typeof showToast === 'function') showToast('Titel eingeben', 'error');
    return;
  }
  if (typ === 'link' && !url) {
    if (typeof showToast === 'function') showToast('URL eingeben', 'error');
    return;
  }
  if (visibilityMode === 'firma' && !companyId) {
    if (typeof showToast === 'function') showToast('Bitte Firma auswählen oder anderen Sichtbarkeitsmodus wählen', 'error');
    return;
  }
  if (visibilityMode !== 'firma') {
    companyId = null;
  }

  if (id && typ === 'datei') {
    fetch(downloadsApiUrl, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: parseInt(id, 10),
        titel: titel,
        typ: typ,
        url: typ === 'link' ? url : null,
        sichtbar_fuer: sichtbarFuer,
        company_id: companyId,
        intern: !!intern
      })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        closeModal();
        loadDownloads();
        if (typeof showToast === 'function') showToast('Verknüpfung aktualisiert', 'success');
      } else {
        if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error');
      }
    })
    .catch(function() {
      if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
    });
    return;
  }

  if (!id && typ === 'datei') {
    var fileInput = document.getElementById('downloadDatei');
    if (!fileInput.files || !fileInput.files.length) {
      if (typeof showToast === 'function') showToast('Bitte Datei auswählen', 'error');
      return;
    }
    var formData = new FormData();
    formData.append('titel', titel);
    formData.append('typ', 'datei');
    formData.append('sichtbar_fuer', sichtbarFuer);
    if (companyId) formData.append('company_id', companyId);
    formData.append('intern', intern ? '1' : '0');
    formData.append('datei', fileInput.files[0]);
    fetch(downloadsApiUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(function(r) {
        if (!r.ok) return r.json().then(function(d) { throw new Error(d.error || 'Upload fehlgeschlagen'); });
        return r.json();
      })
      .then(function(data) {
        if (data.success) {
          closeModal();
          loadDownloads();
          if (typeof showToast === 'function') showToast('Verknüpfung angelegt', 'success');
        } else {
          if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error');
        }
      })
      .catch(function(err) {
        if (typeof showToast === 'function') showToast(err.message || 'Fehler beim Hochladen', 'error');
      });
    return;
  }

  var payload = {
    titel: titel,
    typ: 'link',
    url: url,
    sichtbar_fuer: sichtbarFuer,
    company_id: companyId,
    intern: !!intern
  };
  if (id) payload.id = parseInt(id, 10);
  fetch(downloadsApiUrl, {
    method: id ? 'PUT' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success) {
      closeModal();
      loadDownloads();
      if (typeof showToast === 'function') showToast(id ? 'Verknüpfung aktualisiert' : 'Verknüpfung angelegt', 'success');
    } else {
      if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error');
    }
  })
  .catch(function() {
    if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
  });
}

function deleteDownload(id) {
  if (!confirm('Diese Verknüpfung wirklich löschen?')) return;
  fetch(downloadsApiUrl + '?id=' + id, { method: 'DELETE' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        loadDownloads();
        if (typeof showToast === 'function') showToast('Verknüpfung gelöscht', 'success');
      } else {
        if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error');
      }
    })
    .catch(function() {
      if (typeof showToast === 'function') showToast('Fehler beim Löschen', 'error');
    });
}
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
