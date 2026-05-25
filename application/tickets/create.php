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
    header('Location: ' . BASE_URL . 'tickets/');
    exit;
}

// Firmen und Kunden für Dropdown laden
$companies = [];
$customers = [];

if ($userRole === 'Admin' || $userRole === 'Techniker') {
    // Alle aktiven Firmen (mit Adresse und Kundennummer für Auswahl)
    $stmt = $pdo->query("SELECT id, name, kundennummer FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($userCompanyId) {
    // Nur eigene Firma
    $stmt = $pdo->prepare("SELECT id, name, kundennummer FROM companies WHERE id = ? AND status = 'aktiv'");
    $stmt->execute([$userCompanyId]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kunden der Firma (Adresse für Auswahl wird per API geladen)
    $stmt = $pdo->prepare("SELECT id, name, email FROM customers WHERE company_id = ? AND status = 'aktiv' ORDER BY name");
    $stmt->execute([$userCompanyId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
foreach ($companies as &$co) {
    decrypt_company_row($co);
}
unset($co);
usort($companies, function ($a, $b) {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});
foreach ($customers as &$c) {
    decrypt_customer_row($c);
}
unset($c);

service_log($pdo, $userId, 'sonstiges', 0, 'viewed', null, null, null, 'Tickets: Seite Ticket erstellen aufgerufen');
include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content"
     class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
      <main>
        <div class="pr-4">
<div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
  <div class="col-span-full mx-4 mt-4 items-center justify-between sm:flex">
    <div class="mb-4 sm:mb-0 flex-1">
      <nav class="mb-4 flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
          <li class="inline-flex items-center">
            <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-primary-210 dark:hover:text-primary-200 transition-colors">
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
            <a href="<?php echo BASE_URL; ?>tickets/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 md:ms-2 dark:text-primary-210 dark:hover:text-primary-200 transition-colors">Tickets</a>
          </li>
          <li aria-current="page">
            <div class="flex items-center">
              <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
              </svg>
              <span class="ms-1 text-sm font-medium text-gray-500 dark:text-primary-240 md:ms-2">Ticket</span>
            </div>
          </li>
        </ol>
      </nav>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-primary-200">Neuer Ticket</h1>
          <p class="text-sm text-gray-600 dark:text-primary-210 mt-1">Erstellen Sie einen neuen Ticket</p>
        </div>
        <a href="<?php echo BASE_URL; ?>tickets/" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-300 border border-gray-300 dark:border-primary-320 rounded-base hover:bg-gray-50 dark:hover:bg-primary-140 focus:ring-2 focus:ring-primary-250/30 transition-colors">
          Abbrechen
        </a>
      </div>
    </div>
  </div>
  <div class="relative col-span-full">
    <div class="px-4">
          <form id="ticketForm" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-10 gap-4">
              <!-- Linke Spalte: Hauptinhalt -->
              <div class="lg:col-span-6 space-y-6">
                <!-- Betreff Card -->
                <div class="bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-6">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-primary-100 dark:bg-primary-900/30 rounded-base">
                      <svg class="w-5 h-5 text-primary-600 dark:text-primary-250" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                      </svg>
                    </div>
                    <div>
                      <label for="titel" class="block text-sm font-semibold text-gray-900 dark:text-primary-200">
                        Betreff <span class="text-red-500">*</span>
                      </label>
                      <p class="text-xs text-gray-500 dark:text-primary-240 mt-0.5">Kurze Beschreibung des Problems</p>
                    </div>
                  </div>
                  <input type="text" id="titel" name="titel" required maxlength="50"
                         placeholder="z.B. Problem mit Drucker"
                         class="w-full bg-gray-50 dark:bg-primary-300 border border-gray-300 dark:border-primary-320 text-gray-900 dark:text-primary-200 text-sm rounded-base focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 block p-3 dark:placeholder-primary-240 transition-all">
                  <div class="mt-2 flex justify-end">
                    <span id="titel-counter" class="text-xs text-gray-500 dark:text-primary-240">0 / 50 Zeichen</span>
                  </div>
                </div>
                
                <!-- Weitere Infos Card -->
                <div class="bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-6">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-primary-100 dark:bg-primary-900/30 rounded-base">
                      <svg class="w-5 h-5 text-primary-600 dark:text-primary-250" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-gray-900 dark:text-primary-200">
                        Weitere Infos
                      </label>
                      <p class="text-xs text-gray-500 dark:text-primary-240 mt-0.5">Detaillierte Beschreibung und Anhänge</p>
                    </div>
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Nachricht -->
                    <div>
                      <label for="beschreibung" class="block text-xs font-medium text-gray-700 dark:text-primary-210 mb-2">
                        Nachricht
                      </label>
                      <textarea id="beschreibung" name="beschreibung"
                                placeholder="Beschreiben Sie das Problem oder Ihre Anfrage..."
                                class="w-full h-32 bg-gray-50 dark:bg-primary-300 border border-gray-300 dark:border-primary-320 text-gray-900 dark:text-primary-200 text-sm rounded-base focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 block p-3 dark:placeholder-primary-240 transition-all resize-none"></textarea>
                    </div>
                    
                    <!-- Anhänge -->
                    <div>
                      <label class="block text-xs font-medium text-gray-700 dark:text-primary-210 mb-2">
                        Anhänge
                      </label>
                      <div class="flex items-center justify-center w-full">
                        <label for="attachment_files" class="flex flex-col items-center justify-center w-full h-32 bg-gray-50 dark:bg-primary-300/50 border-2 border-dashed border-gray-300 dark:border-primary-320 rounded-base cursor-pointer hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors">
                          <div class="flex flex-col items-center justify-center text-center pt-5 pb-6">
                            <svg class="w-10 h-10 mb-3 text-gray-400 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h3a3 3 0 0 0 0-6h-.025a5.56 5.56 0 0 0 .025-.5A5.5 5.5 0 0 0 7.207 9.021C7.137 9.017 7.071 9 7 9a4 4 0 1 0 0 8h2.167M12 19v-9m0 0-2 2m2-2 2 2"/>
                            </svg>
                            <p class="mb-1 text-sm text-gray-700 dark:text-primary-210"><span class="font-semibold">Klicken zum Hochladen</span> oder per Drag & Drop</p>
                            <p class="text-xs text-gray-500 dark:text-primary-240">Max. Dateigröße: <span class="font-semibold">10MB</span></p>
                          </div>
                          <input id="attachment_files" type="file" name="attachments[]" multiple class="hidden" accept="*/*" />
                        </label>
                      </div>
                      <div id="attachmentsPreview" class="mt-4 space-y-2"></div>
                    </div>
                  </div>
                  
                  <!-- Action Buttons -->
                  <div class="mt-6 pt-6 border-t border-gray-200 dark:border-primary-120 flex justify-end">
                    <div class="inline-flex rounded-base shadow-sm -space-x-px" role="group">
                      <button type="button" id="createAndViewButton" class="inline-flex items-center text-white bg-primary-250 dark:bg-primary-280 hover:bg-primary-260 dark:hover:bg-primary-270 border border-primary-250 dark:border-primary-280 focus:ring-2 focus:ring-primary-250/30 font-medium leading-5 rounded-s-base text-sm px-4 py-2 focus:outline-none transition-colors">
                        <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Ticket erstellen
                      </button>
                      <button type="button" id="createButton" class="inline-flex items-center text-gray-900 dark:text-primary-200 bg-white dark:bg-primary-300 border border-gray-300 dark:border-primary-320 hover:bg-gray-50 dark:hover:bg-primary-140 focus:ring-2 focus:ring-primary-250/30 font-medium leading-5 rounded-e-base text-sm px-4 py-2 focus:outline-none transition-colors">
                        <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Weiteren Auftrag erstellen
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Rechte Spalte: Einstellungen (Firma, Kunde, Gerät) -->
              <div class="lg:col-span-4 lg:sticky lg:top-4 lg:self-start" style="max-height: calc(100vh - 12rem);">
                <div id="rightColumnScrollContainer" class="lg:overflow-y-auto lg:overflow-x-hidden h-full custom-scrollbar relative" style="max-height: inherit;">
                  <!-- Fade-Indikator unten -->
                  <div id="scrollFadeBottom" class="pointer-events-none absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-gray-50 to-transparent dark:from-primary-50 opacity-0 transition-opacity duration-300 z-10"></div>
                <!-- Kompakte Firma & Kunde & Gerät Card (wird angezeigt, wenn Firma ausgewählt ist) -->
                <div id="companyCustomerCompactContainer" style="display: none;" class="rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-4">
                  <div class="flex items-center justify-between">
                    <div class="flex-1 space-y-1.5">
                      <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z" />
                        </svg>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                          <span id="compactCompanyText" class="text-primary-600 dark:text-primary-250"></span>
                        </span>
                      </div>
                      <div id="compactCustomerRow" style="display: none;" class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-primary-210">
                          <span id="compactCustomerText" class="font-medium"></span>
                        </span>
                      </div>
                      <div id="compactDeviceRow" style="display: none;" class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-primary-210">
                          <span id="compactDeviceText" class="font-medium"></span>
                        </span>
                      </div>
                    </div>
                    <button type="button" id="editCompanyCustomerButton" onclick="editCompanyCustomerSelection()" class="ml-3 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-250 dark:hover:text-primary-200 transition-colors font-medium underline">
                      Anpassen
                    </button>
                  </div>
                </div>
                
                <!-- Firma / Kunde / Gerät Cards (Style wie view.php, mit Animation) -->
                <div class="company-customer-edit-cards-wrapper space-y-4">
                <?php if (count($companies) > 0): ?>
                <div id="companySelectContainer" class="edit-card edit-card-company rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden shadow-sm transition-all duration-300 ease-out">
                  <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-primary-120 border-b border-gray-100 dark:border-primary-140">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                      <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-200/30 text-primary-600 dark:text-primary-250 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z" /></svg>
                      </span>
                      <div class="min-w-0">
                        <span class="font-semibold text-gray-900 dark:text-primary-220 block">Firma <span class="text-red-500">*</span></span>
                        <span class="text-xs text-gray-500 dark:text-primary-240">Wählen Sie die zugehörige Firma</span>
                      </div>
                    </div>
                    <button type="button" onclick="clearCompanySelection()" class="flex items-center gap-1.5 px-2.5 py-1.5 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-primary-140 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                      Zurücksetzen
                    </button>
                  </div>
                  <div class="p-4 space-y-3">
                    <input type="hidden" id="company_id" name="company_id" value="<?php echo (($userRole === 'Admin' || $userRole === 'Techniker') ? '' : ($userCompanyId ? $userCompanyId : '')); ?>" required>
                    <span id="companySelectedText" class="text-sm font-medium text-primary-600 dark:text-primary-250">-- Keine Firma --</span>
                    <div class="relative">
                      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                      <input type="text" id="companySearch" placeholder="Firma suchen..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                      <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/80 sticky top-0">
                          <tr><th class="px-3 py-2">Firma</th><th class="px-3 py-2">Kundennummer</th></tr>
                        </thead>
                        <tbody id="companyTableBody" class="bg-white dark:bg-gray-800">
                          <?php foreach ($companies as $company): ?>
                            <?php
                              $cName = htmlspecialchars($company['name']);
                              $cKdnr = isset($company['kundennummer']) && $company['kundennummer'] !== '' ? htmlspecialchars($company['kundennummer']) : '–';
                              $cNameJs = htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="company-row border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer <?php echo ($userCompanyId == $company['id']) ? 'bg-primary-50 dark:bg-primary-900/20' : ''; ?>" 
                                data-id="<?php echo $company['id']; ?>" 
                                data-name="<?php echo $cName; ?>"
                                onclick="event.stopPropagation(); selectCompany(<?php echo $company['id']; ?>, '<?php echo $cNameJs; ?>')">
                              <td class="px-3 py-2 text-gray-900 dark:text-white font-medium"><?php echo $cName; ?></td>
                              <td class="px-3 py-2 text-gray-500 dark:text-gray-400 text-xs"><?php echo $cKdnr; ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
                
                <!-- Kunde Card -->
                <div id="customerSelectContainer" style="display: none;" class="edit-card edit-card-customer rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden shadow-sm transition-all duration-300 ease-out">
                  <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-primary-120 border-b border-gray-100 dark:border-primary-140">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                      <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 dark:bg-primary-200/20 text-gray-600 dark:text-white shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                      </span>
                      <div class="min-w-0">
                        <span class="font-semibold text-gray-900 dark:text-primary-220 block">Kunde</span>
                        <span class="text-xs text-gray-500 dark:text-primary-240">Optional: Wählen Sie einen Kunden</span>
                      </div>
                    </div>
                    <button type="button" onclick="clearCustomerSelection()" class="flex items-center gap-1.5 px-2.5 py-1.5 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-primary-140 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                      Zurücksetzen
                    </button>
                  </div>
                  <div class="p-4 space-y-3">
                    <span id="customerSelectedText" class="text-sm font-medium text-primary-600 dark:text-primary-250">-- Kein Kunde --</span>
                    <input type="hidden" id="customer_id" name="customer_id" value="">
                    <div class="relative">
                      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                      <input type="text" id="customerSearch" placeholder="Kunde suchen..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                      <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/80 sticky top-0">
                          <tr><th class="px-3 py-2">Kunde</th><th class="px-3 py-2">Adresse</th></tr>
                        </thead>
                        <tbody id="customerTableBody" class="bg-white dark:bg-gray-800">
                          <tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">Keine Kunden verfügbar</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                
                <!-- Gerät Card -->
                <div id="deviceSelectContainer" style="display: none;" class="edit-card edit-card-device rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden shadow-sm transition-all duration-300 ease-out">
                  <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-primary-120 border-b border-gray-100 dark:border-primary-140">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                      <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 dark:bg-primary-200/20 text-gray-600 dark:text-white shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                      </span>
                      <div class="min-w-0">
                        <span class="font-semibold text-gray-900 dark:text-primary-220 block">Gerät</span>
                        <span class="text-xs text-gray-500 dark:text-primary-240">Optional: Wählen Sie ein Gerät</span>
                      </div>
                    </div>
                    <button type="button" onclick="clearDeviceSelection()" class="flex items-center gap-1.5 px-2.5 py-1.5 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-primary-140 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                      Zurücksetzen
                    </button>
                  </div>
                  <div class="p-4 space-y-3">
                    <span id="deviceSelectedText" class="text-sm font-medium text-primary-600 dark:text-primary-250">-- Kein Gerät --</span>
                    <input type="hidden" id="device_id" name="device_id" value="">
                    <div class="relative">
                      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                      <input type="text" id="deviceSearch" placeholder="Gerät suchen..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                      <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/80 sticky top-0">
                          <tr><th class="px-2 py-1.5">Name</th><th class="px-2 py-1.5">Gerät</th><th class="px-2 py-1.5">Info</th></tr>
                        </thead>
                        <tbody id="deviceTableBody" class="bg-white dark:bg-gray-800">
                          <tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-gray-400">Keine Geräte verfügbar</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                </div>
                
                <!-- Kompakte Bearbeiter & Anforderer Übersicht (wird angezeigt, wenn ausgewählt) -->
                <div id="assigneeCompactContainer" style="display: none;" class="mt-4 rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-4">
                  <div class="flex items-center justify-between">
                    <div class="flex-1 space-y-1.5">
                      <div id="assigneeCompactRow" class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-primary-210">Bearbeiter:</span>
                        <span id="compactAssigneeText" class="text-sm font-medium text-gray-900 dark:text-white"></span>
                      </div>
                      <div id="requesterCompactRow" style="display: none;" class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-primary-210">Anforderer:</span>
                        <span id="compactRequesterText" class="text-sm font-medium text-gray-900 dark:text-white"></span>
                      </div>
                    </div>
                    <button type="button" id="editAssigneeButton" onclick="editAssigneeSelection()" class="ml-3 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-250 dark:hover:text-primary-200 transition-colors font-medium underline">
                      Anpassen
                    </button>
                  </div>
                </div>
                
                <?php if ($userRole !== 'Kunde'): ?>
                <!-- Beobachter Card -->
                <div class="mt-6 rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-5">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 dark:bg-primary-140 rounded-base">
                      <svg class="w-5 h-5 text-gray-600 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <label class="block text-sm font-semibold text-gray-900 dark:text-white">
                        Beobachter
                      </label>
                      <p class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">Optional: Benutzer, die informiert werden</p>
                    </div>
                  </div>
                  <details class="group">
                    <summary class="flex items-center justify-between cursor-pointer list-none p-3 bg-gray-50 dark:bg-primary-300 rounded-base border border-gray-300 dark:border-primary-320 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors">
                      <span class="text-sm font-medium text-gray-700 dark:text-primary-210">
                        <span id="observersSelectedText" class="text-primary-600 dark:text-primary-250 font-semibold">-- Keine Beobachter --</span>
                      </span>
                      <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 transform transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </summary>
                    <div class="mt-3 p-3 border border-gray-300 dark:border-primary-320 rounded-base">
                      <input type="hidden" id="observer_ids_input" name="observer_ids" value="">
                      <div class="mb-3 flex gap-2">
                        <input type="text" id="observerSearch" placeholder="Benutzer suchen..." 
                               class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-primary-320 rounded-base bg-gray-50 dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                        <button type="button" onclick="clearObserverSelection()" class="px-3 py-2 text-sm text-red-600 hover:text-red-800 dark:text-red-400 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                      </div>
                      <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-primary-230 rounded-base">
                        <table class="w-full text-sm text-left">
                          <thead class="text-xs text-gray-700 dark:text-primary-210 uppercase bg-gray-50 dark:bg-primary-140 sticky top-0">
                            <tr>
                              <th class="px-3 py-2 w-4">
                                <input type="checkbox" id="selectAllObservers" onchange="toggleAllObservers(this)" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-primary-320 dark:bg-primary-300">
                              </th>
                              <th class="px-3 py-2">Name</th>
                            </tr>
                          </thead>
                          <tbody id="observersTableBody" class="bg-white dark:bg-primary-100">
                            <tr>
                              <td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Keine Benutzer verfügbar</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </details>
                </div>
                <?php endif; ?>
                
                <!-- Datumsfelder Card -->
                <div class="mt-6 rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-5">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 dark:bg-primary-140 rounded-base">
                      <svg class="w-5 h-5 text-gray-600 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <label class="block text-sm font-semibold text-gray-900 dark:text-white">
                        <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'Termine' : 'Fällig'; ?>
                      </label>
                      <p class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">
                        <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'Optionale Datumsangaben' : 'Optional: Fälligkeitsdatum festlegen'; ?>
                      </p>
                    </div>
                  </div>
                  <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                  <details class="group">
                    <summary class="flex items-center justify-between cursor-pointer list-none p-3 bg-gray-50 dark:bg-primary-300 rounded-base border border-gray-300 dark:border-primary-320 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors">
                      <span class="text-sm font-medium text-gray-700 dark:text-primary-210">
                        <span id="datesSelectedText" class="text-primary-600 dark:text-primary-250 font-semibold">-- Keine Termine --</span>
                      </span>
                      <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 transform transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </summary>
                    <div class="mt-3 p-3 border border-gray-300 dark:border-primary-320 rounded-base space-y-3">
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                          <label for="geplant_datum" class="block text-xs font-medium text-gray-700 dark:text-primary-210 mb-1">
                            Geplant (von)
                          </label>
                          <input type="datetime-local" id="geplant_datum" name="geplant_datum" autocomplete="off"
                                 class="datetime-picker-only w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-base focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 block p-2.5 dark:bg-primary-300 dark:border-primary-320 dark:text-primary-200 transition-all">
                        </div>
                        <div>
                          <label for="geplant_datum_ende" class="block text-xs font-medium text-gray-700 dark:text-primary-210 mb-1">
                            Geplant (bis, optional)
                          </label>
                          <input type="datetime-local" id="geplant_datum_ende" name="geplant_datum_ende" autocomplete="off"
                                 class="datetime-picker-only w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-base focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 block p-2.5 dark:bg-primary-300 dark:border-primary-320 dark:text-primary-200 transition-all">
                        </div>
                      </div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                          <label for="faellig_datum" class="block text-xs font-medium text-gray-700 dark:text-primary-210 mb-1">
                            Fällig (von)
                          </label>
                          <input type="datetime-local" id="faellig_datum" name="faellig_datum" autocomplete="off"
                                 class="datetime-picker-only w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-base focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 block p-2.5 dark:bg-primary-300 dark:border-primary-320 dark:text-primary-200 transition-all">
                        </div>
                        <div>
                          <label for="faellig_datum_ende" class="block text-xs font-medium text-gray-700 dark:text-primary-210 mb-1">
                            Fällig (bis, optional)
                          </label>
                          <input type="datetime-local" id="faellig_datum_ende" name="faellig_datum_ende" autocomplete="off"
                                 class="datetime-picker-only w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-base focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 block p-2.5 dark:bg-primary-300 dark:border-primary-320 dark:text-primary-200 transition-all">
                        </div>
                      </div>
                    </div>
                  </details>
                  <?php else: ?>
                  <!-- Nicht Admin/Techniker: kompakt ohne Collapse, nur Fällig -->
                  <div class="p-3 bg-gray-50 dark:bg-primary-300 rounded-base border border-gray-300 dark:border-primary-320">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <label for="faellig_datum" class="block text-xs font-medium text-gray-700 dark:text-primary-210 mb-1">
                          Fällig (von)
                        </label>
                        <input type="datetime-local" id="faellig_datum" name="faellig_datum" autocomplete="off"
                               class="datetime-picker-only w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-base focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 block p-2 dark:bg-primary-300 dark:border-primary-320 dark:text-primary-200 transition-all">
                      </div>
                      <div>
                        <label for="faellig_datum_ende" class="block text-xs font-medium text-gray-700 dark:text-primary-210 mb-1">
                          Fällig (bis, optional)
                        </label>
                        <input type="datetime-local" id="faellig_datum_ende" name="faellig_datum_ende" autocomplete="off"
                               class="datetime-picker-only w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-base focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 block p-2 dark:bg-primary-300 dark:border-primary-320 dark:text-primary-200 transition-all">
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
                
                <?php if ($userRole === 'Techniker'): ?>
                <!-- Bearbeiter Card -->
                <div id="zugewiesenAnContainer" class="mt-6 rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-5">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 dark:bg-primary-140 rounded-base">
                      <svg class="w-5 h-5 text-gray-600 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <label class="block text-sm font-semibold text-gray-900 dark:text-white">
                        Bearbeiter
                      </label>
                      <p class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">Optional: Zuweisung an einen Mitarbeiter</p>
                    </div>
                  </div>
                  <input type="hidden" id="zugewiesen_an" name="zugewiesen_an" value="">
                  <details class="group">
                    <summary class="flex items-center justify-between cursor-pointer list-none p-3 bg-gray-50 dark:bg-primary-300 rounded-base border border-gray-300 dark:border-primary-320 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors">
                      <span class="text-sm font-medium text-gray-700 dark:text-primary-210">
                        <span id="assigneeSelectedText" class="text-primary-600 dark:text-primary-250 font-semibold">-- Nicht zugewiesen --</span>
                      </span>
                      <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 transform transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </summary>
                    <div class="mt-3 p-3 bg-white dark:bg-primary-300 border border-gray-300 dark:border-primary-320 rounded-base">
                      <div class="mb-3 flex gap-2">
                        <input type="text" id="assigneeSearch" placeholder="Bearbeiter suchen..." 
                               class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-primary-320 rounded-base bg-gray-50 dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                        <button type="button" onclick="clearAssigneeSelection()" class="px-3 py-2 text-sm text-red-600 hover:text-red-800 dark:text-red-400 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                      </div>
                      <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-primary-230 rounded-base">
                        <table class="w-full text-sm text-left">
                          <thead class="text-xs text-gray-700 dark:text-primary-210 uppercase bg-gray-50 dark:bg-primary-140 sticky top-0">
                            <tr>
                              <th class="px-3 py-2">Name</th>
                            </tr>
                          </thead>
                          <tbody id="assigneeTableBody" class="bg-white dark:bg-primary-100">
                            <tr>
                              <td class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Lade Bearbeiter...</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </details>
                </div>
                <?php endif; ?>
                
                <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                <!-- Anforderer Card -->
                <div id="anfordererContainer" style="display: none;" class="mt-6 rounded-base shadow-card border border-gray-200 dark:border-primary-120 p-5">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 dark:bg-primary-140 rounded-base">
                      <svg class="w-5 h-5 text-gray-600 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <label class="block text-sm font-semibold text-gray-900 dark:text-white">
                        Anforderer
                      </label>
                      <p class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">Optional: Wer hat den Auftrag angefordert</p>
                    </div>
                  </div>
                  <input type="hidden" id="anforderer_id" name="anforderer_id" value="">
                  <details class="group">
                    <summary class="flex items-center justify-between cursor-pointer list-none p-3 bg-gray-50 dark:bg-primary-300 rounded-base border border-gray-300 dark:border-primary-320 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors">
                      <span class="text-sm font-medium text-gray-700 dark:text-primary-210">
                        <span id="requesterSelectedText" class="text-primary-600 dark:text-primary-250 font-semibold">-- Kein Anforderer --</span>
                      </span>
                      <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 transform transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </summary>
                    <div class="mt-3 p-3 border border-gray-300 dark:border-primary-320 rounded-base">
                      <div class="mb-3 flex gap-2">
                        <input type="text" id="requesterSearch" placeholder="Anforderer suchen..." 
                               class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-primary-320 rounded-base bg-gray-50 dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250">
                        <button type="button" onclick="clearRequesterSelection()" class="px-3 py-2 text-sm text-red-600 hover:text-red-800 dark:text-red-400 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                      </div>
                      <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-primary-230 rounded-base">
                        <table class="w-full text-sm text-left">
                          <thead class="text-xs text-gray-700 dark:text-primary-210 uppercase bg-gray-50 dark:bg-primary-140 sticky top-0">
                            <tr>
                              <th class="px-3 py-2">Name</th>
                            </tr>
                          </thead>
                          <tbody id="requestersTableBody" class="bg-white dark:bg-primary-100">
                            <tr>
                              <td colspan="1" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Bitte Firma auswählen</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </details>
                </div>
                <?php endif; ?>
                </div>
              </div>
            </div>
          </form>
    </div>
  </div>
</div>
        </div>
      </main>
  </div>

<script>
const ticketsApiUrl = '<?php echo BASE_URL; ?>tickets/api/tickets.php';
const customersApiUrl = '<?php echo BASE_URL; ?>customers/api/customers.php';
const devicesApiUrl = '<?php echo BASE_URL; ?>devices/api/devices.php';
const todosApiUrl = '<?php echo BASE_URL; ?>todos/api/todos.php';
const userRole = '<?php echo $userRole; ?>';
const isAdminOrTech = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
const userCompanyId = <?php echo $userCompanyId ? (int)$userCompanyId : 'null'; ?>;
const currentUserId = <?php echo (int)$userId; ?>;
const canSetAssignee = (userRole === 'Techniker');
const isCustomer = (userRole === 'Kunde');
let selectedFiles = [];
let currentAction = 'create_and_view';

// Kunden- und Geräte-Tabellen anzeigen/verstecken und laden
document.addEventListener('DOMContentLoaded', function() {
    const customerContainer = document.getElementById('customerSelectContainer');
    const deviceContainer = document.getElementById('deviceSelectContainer');
    const companyContainer = document.getElementById('companySelectContainer');
    const editCompanyCustomerButton = document.getElementById('editCompanyCustomerButton');
    
    // Prüfe ob Firma in Nav gesetzt ist
    let selectedCompanyId = null;
    let isAllCompaniesSelected = false;
    
    if (isAdminOrTech) {
        const savedSelection = localStorage.getItem('selectedUserOption');
        if (savedSelection) {
            try {
                const data = JSON.parse(savedSelection);
                if (data.id === '0' || data.id === 0) {
                    // "Alle Firmen" ist ausgewählt
                    isAllCompaniesSelected = true;
                    selectedCompanyId = null;
                } else if (data.id && data.id !== '0') {
                    selectedCompanyId = parseInt(data.id);
                }
            } catch (e) {
                console.error('Fehler beim Laden der Firmenauswahl', e);
            }
        }
    } else {
        // Für alle anderen Rollen: Firma ist fix aus users.company_id
        isAllCompaniesSelected = false;
        selectedCompanyId = userCompanyId ? parseInt(userCompanyId) : null;
    }
    
    // Firmenauswahl ausblenden, wenn Firma fest ist oder in Nav gesetzt ist (aber nicht "Alle Firmen")
    if (selectedCompanyId && companyContainer && !isAllCompaniesSelected) {
        if (!isAdminOrTech) {
            // Nicht Admin/Techniker: Firma nicht änderbar -> Container immer ausblenden
            companyContainer.style.display = 'none';
        } else {
            // Admin/Techniker: Firma über Nav vorausgewählt -> Container ausblenden
            companyContainer.style.display = 'none';
            companyContainer.style.marginTop = '0';
            companyContainer.style.marginBottom = '0';
        }
        
        const companyHiddenInput = document.getElementById('company_id');
        if (companyHiddenInput) {
            companyHiddenInput.value = selectedCompanyId;
        }
        // Firmenname anzeigen
        const companyRow = document.querySelector(`.company-row[data-id="${selectedCompanyId}"]`);
        const companyName = companyRow?.getAttribute('data-name') || 'Firma';
        const companySelectedText = document.getElementById('companySelectedText');
        if (companySelectedText) {
            companySelectedText.textContent = companyName;
        }
        // Zeile markieren
        if (companyRow) {
            companyRow.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
        }
    } else if (isAllCompaniesSelected) {
        // "Alle Firmen" ist ausgewählt - keine Firma vorauswählen
        const companyHiddenInput = document.getElementById('company_id');
        if (companyHiddenInput) {
            companyHiddenInput.value = '';
        }
        // Firmenauswahl zurücksetzen
        const companySelectedText = document.getElementById('companySelectedText');
        if (companySelectedText) {
            companySelectedText.textContent = '-- Keine Firma --';
        }
        // Alle Markierungen entfernen
        document.querySelectorAll('.company-row').forEach(row => {
            row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
        });
    } else {
        // Prüfe ob bereits eine Firma im Hidden Input gesetzt ist (nur für Nicht-Admins)
        // Für Admins ohne spezifische Auswahl soll nichts vorausgewählt sein
        const companyHiddenInput = document.getElementById('company_id');
        if (companyHiddenInput && companyHiddenInput.value && <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'false' : 'true'; ?>) {
            const companyId = parseInt(companyHiddenInput.value);
            const companyRow = document.querySelector(`.company-row[data-id="${companyId}"]`);
            if (companyRow) {
                const companyName = companyRow.getAttribute('data-name');
                const companySelectedText = document.getElementById('companySelectedText');
                if (companySelectedText && companyName) {
                    companySelectedText.textContent = companyName;
                }
                companyRow.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
            }
        } else if (companyHiddenInput && (<?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>)) {
            // Für Admins/Techniker: Wenn keine Firma in Nav ausgewählt ist, leere das Feld
            companyHiddenInput.value = '';
        }
    }
    
    // Suchfunktionen für Tabellen
    const companySearch = document.getElementById('companySearch');
    if (companySearch) {
        companySearch.addEventListener('input', function() {
            filterTable('companyTableBody', this.value.toLowerCase(), 'company-row');
        });
    }
    
    const customerSearch = document.getElementById('customerSearch');
    if (customerSearch) {
        customerSearch.addEventListener('input', function() {
            filterTable('customerTableBody', this.value.toLowerCase(), 'customer-row');
        });
    }
    
    const deviceSearch = document.getElementById('deviceSearch');
    if (deviceSearch) {
        deviceSearch.addEventListener('input', function() {
            filterTable('deviceTableBody', this.value.toLowerCase(), 'device-row');
        });
    }
    
    const observerSearch = document.getElementById('observerSearch');
    if (observerSearch) {
        observerSearch.addEventListener('input', function() {
            filterTable('observersTableBody', this.value.toLowerCase(), 'observer-row');
        });
    }
    
    // Event Listener für Datumsfelder - Text aktualisieren
    const faelligDatum = document.getElementById('faellig_datum');
    const geplantDatum = document.getElementById('geplant_datum');
    const datesSelectedText = document.getElementById('datesSelectedText');
    
    function updateDatesText() {
        if (!datesSelectedText) return;
        
        const faellig = faelligDatum?.value || '';
        const geplant = geplantDatum?.value || '';
        
        const dates = [];
        if (faellig) {
            const date = new Date(faellig);
            dates.push('Fällig: ' + date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'}));
        }
        if (geplant) {
            const date = new Date(geplant);
            dates.push('Geplant: ' + date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'}));
        }
        
        if (dates.length > 0) {
            datesSelectedText.textContent = dates.join(', ');
        } else {
            datesSelectedText.textContent = '-- Keine Termine --';
        }
    }
    
    if (faelligDatum) {
        faelligDatum.addEventListener('change', updateDatesText);
    }
    
    if (geplantDatum) {
        geplantDatum.addEventListener('change', updateDatesText);
    }
    
    // Termin-Felder: Nur Picker, keine manuelle Tastatureingabe – Klick/Fokus öffnet Picker
    document.querySelectorAll('.datetime-picker-only').forEach(function(input) {
        input.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab' && e.key !== 'Escape') e.preventDefault();
        });
        input.addEventListener('focus', function() {
            if (typeof window.openNativePicker === 'function') window.openNativePicker(this);
        });
        input.addEventListener('click', function() {
            if (typeof window.openNativePicker === 'function') window.openNativePicker(this);
        });
    });
    
    // Initial: Kunden, Geräte und Benutzer laden
    if (selectedCompanyId && !isAllCompaniesSelected) {
        loadCustomersForCompany(selectedCompanyId);
        loadDevicesForCompany(selectedCompanyId);
    }
    
    // Bearbeiter und Beobachter laden
    // - Bearbeiter: nur Techniker/Firmen-Admin
    // - Beobachter: (außer Kunde) alle User der gesetzten Firma
    loadUsersForAssignment(selectedCompanyId);
    
    // Anforderer laden, wenn Firma ausgewählt ist (nur für Admins/Techniker)
    if (userRole === 'Admin' || userRole === 'Techniker') {
        if (selectedCompanyId && !isAllCompaniesSelected) {
            loadRequestersForCompany(selectedCompanyId);
        }
    }
    
    // Suchfunktion für Bearbeiter-Tabelle
    const assigneeSearch = document.getElementById('assigneeSearch');
    if (assigneeSearch) {
        assigneeSearch.addEventListener('input', function() {
            filterTable('assigneeTableBody', this.value.toLowerCase(), 'assignee-row');
        });
    }
    
    // Suchfunktion für Anforderer-Tabelle
    const requesterSearch = document.getElementById('requesterSearch');
    if (requesterSearch) {
        requesterSearch.addEventListener('input', function() {
            filterTable('requestersTableBody', this.value.toLowerCase(), 'requester-row');
        });
    }
    
    // Initial: Kompakte Cards prüfen
    checkAndShowCompactCard();
    checkAndShowAssigneeCompactCard();
    
    // Für Firmen-Admin: Kunden-Container immer anzeigen wenn Kunden vorhanden und keine Firma vorausgewählt
    if (customerContainer && <?php echo count($customers) > 0 ? 'true' : 'false'; ?> && !selectedCompanyId && !isAllCompaniesSelected) {
        customerContainer.style.display = 'block';
        setTimeout(function() { customerContainer.classList.add('is-visible'); }, 50);
    }
    
    // Firma-Card Einblend-Animation (wenn sichtbar)
    if (companyContainer && companyContainer.offsetParent) {
        setTimeout(function() { companyContainer.classList.add('is-visible'); }, 50);
    }
    
    // Scroll-Fade initial aktualisieren und bei Änderungen
    setTimeout(updateScrollFade, 300);
    
    // Observer für Änderungen im Scroll-Container
    const scrollContainer = document.getElementById('rightColumnScrollContainer');
    if (scrollContainer) {
        const contentObserver = new MutationObserver(() => {
            setTimeout(updateScrollFade, 150);
        });
        contentObserver.observe(scrollContainer, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }
});

function loadCustomersForCompany(companyId) {
    const customerContainer = document.getElementById('customerSelectContainer');
    const customerTableBody = document.getElementById('customerTableBody');
    
    if (!customerContainer || !customerTableBody) return;
    
    customerTableBody.innerHTML = '<tr><td colspan="2" class="px-2 py-1.5 text-center text-gray-500 dark:text-primary-210">Lade Kunden...</td></tr>';
    
    fetch(customersApiUrl + '?company_id=' + companyId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customers && data.customers.length > 0) {
                customerTableBody.innerHTML = data.customers.map(customer => {
                    const name = escapeHtml(customer.name);
                    const kdnr = customer.kundennummer ? escapeHtml(customer.kundennummer) : '';
                    const str = customer.adresse ? escapeHtml(customer.adresse) : '';
                    const plzOrt = [customer.plz, customer.ort].filter(Boolean).join(' ').trim();
                    const plzOrtEsc = plzOrt ? escapeHtml(plzOrt) : '';
                    const nameJs = (customer.name || '').replace(/'/g, "\\'").replace(/\\/g, '\\\\');
                    const adresseCell = (str || plzOrtEsc) ? `<div class="text-xs text-gray-500 dark:text-primary-210">${str ? `<div>${str}</div>` : ''}${plzOrtEsc ? `<div>${plzOrtEsc}</div>` : ''}</div>` : '–';
                    return `
                        <tr class="customer-row border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" 
                            data-id="${customer.id}" 
                            data-name="${name}"
                            onclick="event.stopPropagation(); selectCustomer(${customer.id}, '${nameJs}')">
                            <td class="px-3 py-2 text-gray-900 dark:text-white">
                                <div class="font-medium">${name}</div>
                                ${kdnr ? `<div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${kdnr}</div>` : ''}
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400 text-xs">${adresseCell}</td>
                        </tr>
                    `;
                }).join('');
                // Nur anzeigen, wenn Kunden vorhanden sind
                customerContainer.style.display = 'block';
                customerContainer.classList.remove('is-visible');
                setTimeout(function() { customerContainer.classList.add('is-visible'); }, 50);
            } else {
                // Keine Kunden vorhanden - Container verstecken
                customerContainer.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kunden:', error);
            // Bei Fehler auch verstecken
            customerContainer.style.display = 'none';
        });
}

function loadDevicesForCompany(companyId) {
    const deviceContainer = document.getElementById('deviceSelectContainer');
    const deviceTableBody = document.getElementById('deviceTableBody');
    const customerHiddenInput = document.getElementById('customer_id');
    
    if (!deviceContainer || !deviceTableBody) return;
    
    // Wenn ein Kunde ausgewählt ist, nicht überschreiben
    if (customerHiddenInput && customerHiddenInput.value) {
        return;
    }
    
    deviceTableBody.innerHTML = '<tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-primary-210">Lade Geräte...</td></tr>';
    
    fetch(devicesApiUrl + '?company_id=' + companyId + '&only_active=1')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.devices && data.devices.length > 0) {
                // Nur anzeigen, wenn Geräte vorhanden sind
                deviceContainer.style.display = 'block';
                deviceContainer.classList.remove('is-visible');
                setTimeout(function() { deviceContainer.classList.add('is-visible'); }, 50);
                deviceTableBody.innerHTML = data.devices.map(device => {
                    const deviceType = capitalizeFirst(device.typ || '-') || '-';
                    const manufacturerModel = [device.hersteller, device.modell].filter(Boolean).join(' / ') || '-';
                    const serial = device.seriennummer || '-';
                    const location = device.beschreibung || '-';
                    const userName = [device.user_vorname, device.user_nachname].filter(Boolean).join(' ').trim() || '-';
                    
                    return `
                        <tr class="device-row border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" 
                            data-id="${device.id}" 
                            data-name="${escapeHtml(device.name)}"
                            onclick="event.stopPropagation(); selectDevice(${device.id}, '${escapeHtml(device.name)}')">
                            <!-- Name: Name + Typ -->
                            <td class="px-2 py-1.5">
                                <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(device.name)}</div>
                                <div class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">${escapeHtml(deviceType)}</div>
                            </td>
                            <!-- Seriennummer: Hersteller/Modell + Seriennummer -->
                            <td class="px-2 py-1.5">
                                <div class="text-xs text-gray-700 dark:text-primary-210 font-medium">${escapeHtml(manufacturerModel)}</div>
                                <div class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">${escapeHtml(serial)}</div>
                            </td>
                            <!-- Standort: Standort + Benutzer -->
                            <td class="px-2 py-1.5">
                                <div class="text-xs text-gray-700 dark:text-primary-210 font-medium">${escapeHtml(location)}</div>
                                <div class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">${escapeHtml(userName)}</div>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                deviceTableBody.innerHTML = '<tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-primary-210">Keine Geräte verfügbar</td></tr>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Geräte:', error);
            deviceTableBody.innerHTML = '<tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-primary-210">Fehler beim Laden</td></tr>';
        });
}

function loadDevicesForCustomer(customerId) {
    const deviceContainer = document.getElementById('deviceSelectContainer');
    const deviceTableBody = document.getElementById('deviceTableBody');
    
    if (!deviceContainer || !deviceTableBody) return;
    
    deviceTableBody.innerHTML = '<tr><td colspan="3" class="px-2 py-1.5 text-center text-gray-500 dark:text-primary-210">Lade Geräte...</td></tr>';
    
    fetch(devicesApiUrl + '?customer_id=' + customerId + '&only_active=1')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.devices && data.devices.length > 0) {
                // Nur anzeigen, wenn Geräte vorhanden sind
                deviceContainer.style.display = 'block';
                deviceContainer.classList.remove('is-visible');
                setTimeout(function() { deviceContainer.classList.add('is-visible'); }, 50);
                deviceTableBody.innerHTML = data.devices.map(device => {
                    const deviceType = capitalizeFirst(device.typ || '-') || '-';
                    const manufacturerModel = [device.hersteller, device.modell].filter(Boolean).join(' / ') || '-';
                    const serial = device.seriennummer || '-';
                    const location = device.beschreibung || '-';
                    const userName = [device.user_vorname, device.user_nachname].filter(Boolean).join(' ').trim() || '-';
                    
                    return `
                        <tr class="device-row border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" 
                            data-id="${device.id}" 
                            data-name="${escapeHtml(device.name)}"
                            onclick="event.stopPropagation(); selectDevice(${device.id}, '${escapeHtml(device.name)}')">
                            <!-- Name: Name + Typ -->
                            <td class="px-2 py-1.5">
                                <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(device.name)}</div>
                                <div class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">${escapeHtml(deviceType)}</div>
                            </td>
                            <!-- Seriennummer: Hersteller/Modell + Seriennummer -->
                            <td class="px-2 py-1.5">
                                <div class="text-xs text-gray-700 dark:text-primary-210 font-medium">${escapeHtml(manufacturerModel)}</div>
                                <div class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">${escapeHtml(serial)}</div>
                            </td>
                            <!-- Standort: Standort + Benutzer -->
                            <td class="px-2 py-1.5">
                                <div class="text-xs text-gray-700 dark:text-primary-210 font-medium">${escapeHtml(location)}</div>
                                <div class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">${escapeHtml(userName)}</div>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                // Keine Geräte vorhanden - Container verstecken
                deviceContainer.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Geräte:', error);
            // Bei Fehler Container verstecken
            deviceContainer.style.display = 'none';
        });
}

// Globale Variable für ausgewählte Beobachter
let selectedObservers = [];

function loadUsersForAssignment(companyId) {
    const assigneeTableBody = document.getElementById('assigneeTableBody');
    
    // Beobachter laden (außer Kunde)
    if (!isCustomer) {
        const effectiveCompanyId = companyId || (document.getElementById('company_id')?.value ? parseInt(document.getElementById('company_id').value) : null);
        loadCompanyUsersForObservers(effectiveCompanyId);
    }
    
    // Bearbeiter nur laden, wenn erlaubt und UI vorhanden
    if (!canSetAssignee || !assigneeTableBody) {
        // Falls UI existiert (z.B. durch Cached DOM), sicherheitshalber leeren
        const assigneeHiddenInput = document.getElementById('zugewiesen_an');
        if (assigneeHiddenInput) assigneeHiddenInput.value = '';
        return;
    }
    
    // Bearbeiter: Admins und Techniker
    let url = ticketsApiUrl + '?action=assignees';
    
                assigneeTableBody.innerHTML = '<tr><td class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Lade Bearbeiter...</td></tr>';
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users) {
                const selectedAssigneeId = document.getElementById('zugewiesen_an')?.value || '';
                assigneeTableBody.innerHTML = data.users.map(user => {
                    if (user.rolle === 'Admin' || user.rolle === 'Techniker') {
                        const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim() || user.email;
                        const isSelected = selectedAssigneeId && parseInt(selectedAssigneeId) === user.id;
                        const companyName = user.company_name || '-';
                        return `
                            <tr class="assignee-row border-b border-gray-200 dark:border-primary-230 hover:bg-gray-50 dark:hover:bg-primary-140 cursor-pointer ${isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : ''}" 
                                data-id="${user.id}" 
                                data-name="${escapeHtml(fullName)}"
                                onclick="event.stopPropagation(); selectAssignee(${user.id}, '${escapeHtml(fullName)}')">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(fullName)}</div>
                                    <div class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">${escapeHtml(companyName)}</div>
                                </td>
                            </tr>
                        `;
                    }
                    return '';
                }).filter(Boolean).join('');
                updateAssigneeText();
            } else {
                assigneeTableBody.innerHTML = '<tr><td class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Keine Bearbeiter verfügbar</td></tr>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Bearbeiter:', error);
            assigneeTableBody.innerHTML = '<tr><td class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Fehler beim Laden</td></tr>';
        });
}

function loadCompanyUsersForObservers(companyId) {
    const observersTableBody = document.getElementById('observersTableBody');
    if (!observersTableBody) return;
    
    if (!companyId) {
        observersTableBody.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Bitte Firma auswählen</td></tr>';
        clearObserverSelection();
        return;
    }
    
    observersTableBody.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Lade Benutzer...</td></tr>';
    
    fetch(ticketsApiUrl + '?action=company_users&company_id=' + companyId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.users && data.users.length > 0) {
                // Nie den aktuellen Benutzer als Beobachter anbieten
                const users = data.users.filter(u => parseInt(u.id) !== parseInt(currentUserId));
                // Falls durch alte Daten/Inputs gesetzt: eigene ID entfernen
                selectedObservers = selectedObservers.filter(id => parseInt(id) !== parseInt(currentUserId));
                
                observersTableBody.innerHTML = users.map(user => {
                    const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim() || user.email;
                    const isSelected = selectedObservers.includes(user.id.toString());
                    return `
                        <tr class="observer-row border-b border-gray-200 dark:border-primary-230 hover:bg-gray-50 dark:hover:bg-primary-140 cursor-pointer" 
                            data-id="${user.id}" 
                            data-name="${escapeHtml(fullName)}"
                            onclick="toggleObserver(${user.id}, '${escapeHtml(fullName)}')">
                            <td class="px-3 py-2 text-center">
                                <input type="checkbox" class="observer-checkbox rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-primary-320 dark:bg-primary-300" 
                                       data-user-id="${user.id}" ${isSelected ? 'checked' : ''} 
                                       onclick="event.stopPropagation(); toggleObserver(${user.id}, '${escapeHtml(fullName)}')">
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(fullName)}</div>
                            </td>
                        </tr>
                    `;
                }).join('');
                updateObserversInput();
                updateObserversText();
            } else {
                observersTableBody.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Keine Benutzer verfügbar</td></tr>';
                clearObserverSelection();
            }
        })
        .catch(err => {
            console.error('Fehler beim Laden der Beobachter:', err);
            observersTableBody.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Fehler beim Laden</td></tr>';
        });
}

function selectAssignee(id, name) {
    const assigneeHiddenInput = document.getElementById('zugewiesen_an');
    const assigneeSelectedText = document.getElementById('assigneeSelectedText');
    
    if (assigneeHiddenInput) {
        assigneeHiddenInput.value = id;
    }
    if (assigneeSelectedText) {
        assigneeSelectedText.textContent = name;
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.assignee-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Ausgewählte Zeile markieren
    const selectedRow = document.querySelector(`.assignee-row[data-id="${id}"]`);
    if (selectedRow) {
        selectedRow.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
    }
    
    // Details schließen
    setTimeout(() => {
        const zugewiesenAnContainer = document.getElementById('zugewiesenAnContainer');
        if (zugewiesenAnContainer) {
            const details = zugewiesenAnContainer.querySelector('details');
            if (details && details.hasAttribute('open')) {
                details.removeAttribute('open');
            }
        }
    }, 100);
    
    updateAssigneeText();
    checkAndShowAssigneeCompactCard();
}

function clearAssigneeSelection() {
    const assigneeHiddenInput = document.getElementById('zugewiesen_an');
    const assigneeSelectedText = document.getElementById('assigneeSelectedText');
    
    if (assigneeHiddenInput) {
        assigneeHiddenInput.value = '';
    }
    if (assigneeSelectedText) {
        assigneeSelectedText.textContent = '-- Nicht zugewiesen --';
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.assignee-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    updateAssigneeText();
    checkAndShowAssigneeCompactCard();
    
    // Scroll-Fade aktualisieren
    setTimeout(updateScrollFade, 150);
}

function updateAssigneeText() {
    const assigneeHiddenInput = document.getElementById('zugewiesen_an');
    const assigneeSelectedText = document.getElementById('assigneeSelectedText');
    
    if (!assigneeSelectedText) return;
    
    if (assigneeHiddenInput && assigneeHiddenInput.value) {
        const assigneeRow = document.querySelector(`.assignee-row[data-id="${assigneeHiddenInput.value}"]`);
        if (assigneeRow) {
            const assigneeName = assigneeRow.getAttribute('data-name');
            assigneeSelectedText.textContent = assigneeName;
        }
    } else {
        assigneeSelectedText.textContent = '-- Nicht zugewiesen --';
    }
    
    // Scroll-Fade aktualisieren
    setTimeout(updateScrollFade, 100);
}

// Hinweis: Beobachter werden firmenspezifisch über loadCompanyUsersForObservers() geladen.

function loadRequestersForCompany(companyId) {
    const requestersTableBody = document.getElementById('requestersTableBody');
    const anfordererContainer = document.getElementById('anfordererContainer');
    const anfordererHiddenInput = document.getElementById('anforderer_id');
    
    if (!requestersTableBody) return;
    
    // Anforderer: Alle aktiven Benutzer der Firma laden
    if (!companyId) {
        requestersTableBody.innerHTML = '<tr><td class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Bitte Firma auswählen</td></tr>';
        if (anfordererContainer) {
            anfordererContainer.style.display = 'none';
        }
        if (anfordererHiddenInput) {
            anfordererHiddenInput.value = '';
        }
        updateRequesterText();
        checkAndShowAssigneeCompactCard();
        return;
    }
    
    // Anforderer-Card anzeigen
    if (anfordererContainer) {
        anfordererContainer.style.display = 'block';
    }
    
        requestersTableBody.innerHTML = '<tr><td class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Lade Anforderer...</td></tr>';
    
    let url = todosApiUrl + '?action=assignable_users&company_id=' + companyId;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users && data.users.length > 0) {
                const selectedRequesterId = anfordererHiddenInput ? anfordererHiddenInput.value : '';
                requestersTableBody.innerHTML = data.users.map(user => {
                    const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim() || user.email;
                    const isSelected = selectedRequesterId && parseInt(selectedRequesterId) === parseInt(user.id);
                    const companyName = user.company_name || '-';
                    return `
                        <tr class="requester-row border-b border-gray-200 dark:border-primary-230 hover:bg-gray-50 dark:hover:bg-primary-140 cursor-pointer ${isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : ''}" 
                            data-id="${user.id}" 
                            data-name="${escapeHtml(fullName)}"
                            onclick="event.stopPropagation(); selectRequester(${user.id}, '${escapeHtml(fullName)}')">
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(fullName)}</div>
                                <div class="text-xs text-gray-500 dark:text-primary-210 mt-0.5">${escapeHtml(companyName)}</div>
                            </td>
                        </tr>
                    `;
                }).join('');
                updateRequesterText();
            } else {
                requestersTableBody.innerHTML = '<tr><td class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Keine Anforderer verfügbar</td></tr>';
                updateRequesterText();
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Anforderer:', error);
            requestersTableBody.innerHTML = '<tr><td class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Fehler beim Laden</td></tr>';
            updateRequesterText();
        });
}

function selectRequester(id, name) {
    const anfordererHiddenInput = document.getElementById('anforderer_id');
    const requesterSelectedText = document.getElementById('requesterSelectedText');
    
    if (anfordererHiddenInput) {
        anfordererHiddenInput.value = id;
    }
    if (requesterSelectedText) {
        requesterSelectedText.textContent = name;
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.requester-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Ausgewählte Zeile markieren
    const selectedRow = document.querySelector(`.requester-row[data-id="${id}"]`);
    if (selectedRow) {
        selectedRow.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
    }
    
    // Details schließen
    setTimeout(() => {
        const anfordererContainer = document.getElementById('anfordererContainer');
        if (anfordererContainer) {
            const details = anfordererContainer.querySelector('details');
            if (details && details.hasAttribute('open')) {
                details.removeAttribute('open');
            }
        }
    }, 100);
    
    updateRequesterText();
    checkAndShowAssigneeCompactCard();
    
    // Scroll-Fade aktualisieren
    setTimeout(updateScrollFade, 150);
}

function clearRequesterSelection() {
    const anfordererHiddenInput = document.getElementById('anforderer_id');
    const requesterSelectedText = document.getElementById('requesterSelectedText');
    
    if (anfordererHiddenInput) {
        anfordererHiddenInput.value = '';
    }
    if (requesterSelectedText) {
        requesterSelectedText.textContent = '-- Kein Anforderer --';
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.requester-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    updateRequesterText();
    checkAndShowAssigneeCompactCard();
    
    // Scroll-Fade aktualisieren
    setTimeout(updateScrollFade, 150);
}

function updateRequesterText() {
    const requesterSelectedText = document.getElementById('requesterSelectedText');
    const anfordererHiddenInput = document.getElementById('anforderer_id');
    
    if (!requesterSelectedText) return;
    
    if (anfordererHiddenInput && anfordererHiddenInput.value) {
        const requesterRow = document.querySelector(`.requester-row[data-id="${anfordererHiddenInput.value}"]`);
        if (requesterRow) {
            const requesterName = requesterRow.getAttribute('data-name');
            requesterSelectedText.textContent = requesterName;
        } else {
            requesterSelectedText.textContent = '-- Kein Anforderer --';
        }
    } else {
        requesterSelectedText.textContent = '-- Kein Anforderer --';
    }
}

function checkAndShowAssigneeCompactCard() {
    const zugewiesenAnHiddenInput = document.getElementById('zugewiesen_an');
    const anfordererHiddenInput = document.getElementById('anforderer_id');
    const zugewiesenAnContainer = document.getElementById('zugewiesenAnContainer');
    const anfordererContainer = document.getElementById('anfordererContainer');
    const assigneeCompactContainer = document.getElementById('assigneeCompactContainer');
    
    if (!zugewiesenAnHiddenInput || !assigneeCompactContainer) return;
    
    const zugewiesenAnValue = zugewiesenAnHiddenInput.value;
    const anfordererValue = anfordererHiddenInput ? anfordererHiddenInput.value : '';
    
    // Kompakte Card anzeigen, wenn Bearbeiter oder Anforderer ausgewählt ist
    if (zugewiesenAnValue || anfordererValue) {
        // Kompakte Card anzeigen
        assigneeCompactContainer.style.display = 'block';
        
        // Abstand oben (mt-4 wird durch CSS gesetzt)
        assigneeCompactContainer.style.removeProperty('margin-top');
        
        // Texte in kompakter Card aktualisieren
        const compactAssigneeText = document.getElementById('compactAssigneeText');
        const compactRequesterText = document.getElementById('compactRequesterText');
        const assigneeCompactRow = document.getElementById('assigneeCompactRow');
        const requesterCompactRow = document.getElementById('requesterCompactRow');
        
        // Bearbeiter anzeigen
        if (zugewiesenAnValue && compactAssigneeText && assigneeCompactRow) {
            const assigneeSelectedText = document.getElementById('assigneeSelectedText');
            if (assigneeSelectedText) {
                compactAssigneeText.textContent = assigneeSelectedText.textContent;
            }
            assigneeCompactRow.style.display = 'flex';
        } else if (assigneeCompactRow) {
            assigneeCompactRow.style.display = 'none';
        }
        
        // Anforderer anzeigen
        if (anfordererValue && compactRequesterText && requesterCompactRow) {
            const requesterSelectedText = document.getElementById('requesterSelectedText');
            if (requesterSelectedText) {
                compactRequesterText.textContent = requesterSelectedText.textContent;
            }
            requesterCompactRow.style.display = 'flex';
        } else if (requesterCompactRow) {
            requesterCompactRow.style.display = 'none';
        }
        
        // Nur Bearbeiter-Card verstecken, wenn Bearbeiter ausgewählt
        if (zugewiesenAnValue && zugewiesenAnContainer) {
            zugewiesenAnContainer.style.display = 'none';
        } else if (!zugewiesenAnValue && zugewiesenAnContainer) {
            zugewiesenAnContainer.style.display = 'block';
        }
        
        // Anforderer-Card verstecken, wenn Anforderer ausgewählt
        if (anfordererValue && anfordererContainer) {
            anfordererContainer.style.display = 'none';
        } else if (!anfordererValue && anfordererContainer) {
            // Anforderer-Card anzeigen, wenn Firma ausgewählt ist
            const companyId = document.getElementById('company_id')?.value;
            if (companyId) {
                anfordererContainer.style.display = 'block';
            }
        }
    } else {
        // Kompakte Card verstecken
        assigneeCompactContainer.style.display = 'none';
        
        // Bearbeiter-Card wieder anzeigen
        if (zugewiesenAnContainer) {
            zugewiesenAnContainer.style.display = 'block';
        }
        // Anforderer-Card anzeigen, wenn Firma ausgewählt ist
        const companyId = document.getElementById('company_id')?.value;
        if (companyId && anfordererContainer) {
            anfordererContainer.style.display = 'block';
        }
    }
    
    // Scroll-Fade aktualisieren
    setTimeout(updateScrollFade, 100);
}

function editAssigneeSelection() {
    const zugewiesenAnContainer = document.getElementById('zugewiesenAnContainer');
    const anfordererContainer = document.getElementById('anfordererContainer');
    const assigneeCompactContainer = document.getElementById('assigneeCompactContainer');
    
    // Kompakte Card verstecken
    if (assigneeCompactContainer) {
        assigneeCompactContainer.style.display = 'none';
    }
    
    // Cards wieder anzeigen
    if (zugewiesenAnContainer) {
        zugewiesenAnContainer.style.display = 'block';
    }
    if (anfordererContainer) {
        anfordererContainer.style.display = 'block';
    }
    
    // Scroll-Fade aktualisieren
    setTimeout(updateScrollFade, 100);
}

function toggleObserver(userId, userName) {
    const userIdStr = userId.toString();
    const index = selectedObservers.indexOf(userIdStr);
    
    if (index > -1) {
        selectedObservers.splice(index, 1);
    } else {
        selectedObservers.push(userIdStr);
    }
    
    // Checkbox aktualisieren
    const checkbox = document.querySelector(`.observer-checkbox[data-user-id="${userId}"]`);
    if (checkbox) {
        checkbox.checked = selectedObservers.includes(userIdStr);
    }
    
    updateObserversInput();
    updateObserversText();
}

function toggleAllObservers(checkbox) {
    const checkboxes = document.querySelectorAll('.observer-checkbox');
    selectedObservers = [];
    
    if (checkbox.checked) {
        checkboxes.forEach(cb => {
            const userId = cb.getAttribute('data-user-id');
            selectedObservers.push(userId);
            cb.checked = true;
        });
    } else {
        checkboxes.forEach(cb => {
            cb.checked = false;
        });
    }
    
    updateObserversInput();
    updateObserversText();
}

function updateObserversInput() {
    const input = document.getElementById('observer_ids_input');
    if (input) {
        input.value = selectedObservers.join(',');
    }
}

function updateObserversText() {
    const textElement = document.getElementById('observersSelectedText');
    if (!textElement) return;
    
    if (selectedObservers.length === 0) {
        textElement.textContent = '-- Keine Beobachter --';
        return;
    }
    
    const selectedNames = [];
    document.querySelectorAll('.observer-checkbox:checked').forEach(checkbox => {
        const row = checkbox.closest('.observer-row');
        if (row) {
            const name = row.getAttribute('data-name');
            if (name) selectedNames.push(name);
        }
    });
    
    if (selectedNames.length === 0) {
        textElement.textContent = `${selectedObservers.length} Beobachter ausgewählt`;
    } else if (selectedNames.length <= 2) {
        textElement.textContent = selectedNames.join(', ');
    } else {
        textElement.textContent = `${selectedNames.slice(0, 2).join(', ')} und ${selectedNames.length - 2} weitere`;
    }
}

function clearObserverSelection() {
    selectedObservers = [];
    document.querySelectorAll('.observer-checkbox').forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllObservers').checked = false;
    updateObserversInput();
    updateObserversText();
}

// Anhänge-Verwaltung
document.getElementById('attachment_files')?.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    selectedFiles = files;
    displayAttachmentPreview();
});

function displayAttachmentPreview() {
    const preview = document.getElementById('attachmentsPreview');
    if (!preview) return;
    
    if (selectedFiles.length === 0) {
        preview.innerHTML = '';
        return;
    }
    
    preview.innerHTML = selectedFiles.map((file, index) => {
        const fileSize = (file.size / 1024).toFixed(2) + ' KB';
        return `
            <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-primary-300 rounded-base">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-sm text-gray-700 dark:text-primary-210 truncate">${escapeHtml(file.name)}</span>
                    <span class="text-xs text-gray-500 dark:text-primary-210 flex-shrink-0">(${fileSize})</span>
                </div>
                <button type="button" onclick="removeAttachment(${index})" class="ml-2 p-1 text-red-600 hover:text-red-800 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors flex-shrink-0" title="Anhang entfernen">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        `;
    }).join('');
}

function removeAttachment(index) {
    selectedFiles.splice(index, 1);
    const fileInput = document.getElementById('attachment_files');
    if (fileInput) {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
    }
    displayAttachmentPreview();
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

// Auswahl-Funktionen
function selectCompany(id, name) {
    // Nur Admins/Techniker dürfen die Firma ändern
    if (!isAdminOrTech) {
        return;
    }
    const companyHiddenInput = document.getElementById('company_id');
    const companySelectedText = document.getElementById('companySelectedText');
    
    // Prüfe ob sich die Firma geändert hat
    const oldCompanyId = companyHiddenInput ? companyHiddenInput.value : null;
    const companyChanged = oldCompanyId && parseInt(oldCompanyId) !== id;
    
    if (companyHiddenInput) {
        companyHiddenInput.value = id;
    }
    if (companySelectedText) {
        companySelectedText.textContent = name;
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.company-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Ausgewählte Zeile markieren
    const selectedRow = document.querySelector(`.company-row[data-id="${id}"]`);
    if (selectedRow) {
        selectedRow.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
    }
    
    // Wenn sich die Firma geändert hat, Kunde und Gerät zurücksetzen
    if (companyChanged) {
        clearCustomerSelection();
        clearDeviceSelection();
    }
    
    // Kunden und Geräte laden
    loadCustomersForCompany(id);
    loadDevicesForCompany(id);
    
    // Anforderer für die Firma laden (nur für Admins/Techniker)
    if (userRole === 'Admin' || userRole === 'Techniker') {
        loadRequestersForCompany(id);
    }

    // Beobachter für die Firma laden (außer Kunde)
    if (!isCustomer) {
        loadCompanyUsersForObservers(id);
    }
    
    // Details schließen
    setTimeout(() => {
        // Firmenauswahl ist jetzt immer sichtbar, keine Aktion nötig
    }, 100);
    
    // Prüfen, ob beide ausgewählt sind, dann kompakte Card anzeigen
    checkAndShowCompactCard();
    
    // Scroll-Fade aktualisieren
    setTimeout(updateScrollFade, 150);
}

function checkAndShowCompactCard() {
    const companyHiddenInput = document.getElementById('company_id');
    const customerHiddenInput = document.getElementById('customer_id');
    const deviceHiddenInput = document.getElementById('device_id');
    const companyContainer = document.getElementById('companySelectContainer');
    const customerContainer = document.getElementById('customerSelectContainer');
    const deviceContainer = document.getElementById('deviceSelectContainer');
    const compactContainer = document.getElementById('companyCustomerCompactContainer');
    
    if (!companyHiddenInput || !compactContainer) return;
    
    const companyId = companyHiddenInput.value;
    const customerId = customerHiddenInput ? customerHiddenInput.value : '';
    const deviceId = deviceHiddenInput ? deviceHiddenInput.value : '';
    
    // Prüfen, ob Firma ausgewählt ist
    if (companyId) {
        // Firma Card verstecken
        if (companyContainer) {
            companyContainer.style.display = 'none';
        }
        
        // Kunde Card verstecken, wenn Kunde ausgewählt ist
        if (customerId && customerContainer) {
            customerContainer.style.display = 'none';
        }
        
        // Gerät Card verstecken, wenn Gerät ausgewählt ist
        if (deviceId && deviceContainer) {
            deviceContainer.style.display = 'none';
        }
        
        // Kompakte Card anzeigen (ohne margin-top, da sie das erste Element ist)
        compactContainer.style.display = 'block';
        compactContainer.style.setProperty('margin-top', '0', 'important');
        compactContainer.style.setProperty('margin-bottom', '0', 'important');
        
        // Abstand der Bearbeiter/Anforderer Card bleibt durch mt-4 CSS-Klasse
        
        // Texte in kompakter Card aktualisieren
        const companySelectedText = document.getElementById('companySelectedText');
        const customerSelectedText = document.getElementById('customerSelectedText');
        const deviceSelectedText = document.getElementById('deviceSelectedText');
        const compactCompanyText = document.getElementById('compactCompanyText');
        const compactCustomerText = document.getElementById('compactCustomerText');
        const compactDeviceText = document.getElementById('compactDeviceText');
        const compactCustomerRow = document.getElementById('compactCustomerRow');
        const compactDeviceRow = document.getElementById('compactDeviceRow');
        
        if (compactCompanyText) {
            if (companySelectedText) {
                compactCompanyText.textContent = companySelectedText.textContent;
            } else {
                // Fallback: Firmenname aus der ausgewählten Row holen
                const selectedRow = document.querySelector('.company-row.bg-primary-50, .company-row.dark\\:bg-primary-900\\/20');
                if (selectedRow) {
                    const companyName = selectedRow.getAttribute('data-name');
                    if (companyName) {
                        compactCompanyText.textContent = companyName;
                    }
                }
            }
        }
        
        // Kunde anzeigen, wenn ausgewählt
        if (customerId && customerSelectedText && compactCustomerText && compactCustomerRow) {
            compactCustomerText.textContent = customerSelectedText.textContent;
            compactCustomerRow.style.display = 'flex';
        } else if (compactCustomerRow) {
            compactCustomerRow.style.display = 'none';
        }
        
        // Gerät anzeigen, wenn ausgewählt
        if (deviceId && deviceSelectedText && compactDeviceText && compactDeviceRow) {
            compactDeviceText.textContent = deviceSelectedText.textContent;
            compactDeviceRow.style.display = 'flex';
        } else if (compactDeviceRow) {
            compactDeviceRow.style.display = 'none';
        }
        
        // "Anpassen"-Button ein-/ausblenden
        const editButton = document.getElementById('editCompanyCustomerButton');
        if (editButton) {
            // Nicht Admin/Techniker: Firma ist fix, aber Gerät (und ggf. Kunde) darf angepasst werden
            if (!isAdminOrTech) {
                // Für Firmen-Admin: Button anzeigen, wenn Kunde oder Gerät ausgewählt ist
                const isFirmenAdmin = (userRole === 'Firmen-Admin');
                if (isFirmenAdmin) {
                    editButton.style.display = (customerId || deviceId) ? '' : 'none';
                } else {
                    // Für andere Nicht-Admin/Techniker: Button nur anzeigen, wenn ein Gerät ausgewählt ist
                    editButton.style.display = deviceId ? '' : 'none';
                }
            } else {
            // Button nur ausblenden, wenn nur Firma ausgewählt ist und aus Nav kommt
            let isFromNav = false;
            const savedSelection = localStorage.getItem('selectedUserOption');
            if (savedSelection) {
                try {
                    const data = JSON.parse(savedSelection);
                    // Prüfen, ob die Firma aus Nav kommt und mit der aktuellen übereinstimmt
                    isFromNav = data.id && data.id !== '0' && parseInt(data.id) === parseInt(companyId);
                } catch (e) {}
            }
            
            // Button ausblenden, wenn nur Firma ausgewählt ist (kein Kunde, kein Gerät) und aus Nav kommt
            if (!customerId && !deviceId && isFromNav) {
                editButton.style.display = 'none';
            } else {
                editButton.style.display = '';
            }
            }
        }
    } else {
        // Kompakte Card verstecken
        compactContainer.style.display = 'none';
        compactContainer.style.marginTop = '';
        
        // Abstand der Bearbeiter/Anforderer Card bleibt durch mt-4 CSS-Klasse
        
        // Cards wieder anzeigen (falls vorhanden)
        const savedSelection = localStorage.getItem('selectedUserOption');
        let isFromNav = false;
        if (savedSelection) {
            try {
                const data = JSON.parse(savedSelection);
                isFromNav = data.id && data.id !== '0';
            } catch (e) {}
        }
        
        // Firma Card anzeigen, wenn nicht in Nav
        if (companyContainer && !isFromNav) {
            companyContainer.style.display = 'block';
            setTimeout(function() { companyContainer.classList.add('is-visible'); }, 50);
        }
        
        // Kunde Card anzeigen, wenn Kunde ausgewählt
        if (customerContainer && customerId) {
            customerContainer.style.display = 'block';
            setTimeout(function() { customerContainer.classList.add('is-visible'); }, 50);
        }
        
        // Gerät Card anzeigen, wenn Gerät ausgewählt
        if (deviceContainer && deviceId) {
            deviceContainer.style.display = 'block';
            setTimeout(function() { deviceContainer.classList.add('is-visible'); }, 50);
        }
    }
}

function editCompanyCustomerSelection() {
    const companyContainer = document.getElementById('companySelectContainer');
    const customerContainer = document.getElementById('customerSelectContainer');
    const deviceContainer = document.getElementById('deviceSelectContainer');
    const compactContainer = document.getElementById('companyCustomerCompactContainer');

    // Nicht Admin/Techniker: Firma darf NICHT bearbeitet werden,
    // aber das ausgewählte Gerät (und optional Kunde) darf angepasst werden.
    if (!isAdminOrTech) {
        if (compactContainer) {
            compactContainer.style.display = 'none';
            compactContainer.style.marginTop = '';
        }
        if (companyContainer) {
            companyContainer.style.display = 'none';
        }
        
        // Für Firmen-Admin: Kunden-Card anzeigen, wenn ein Kunde ausgewählt wurde
        const customerId = document.getElementById('customer_id')?.value;
        const isFirmenAdmin = (userRole === 'Firmen-Admin');
        
        if (customerContainer) {
            if (isFirmenAdmin && customerId) {
                customerContainer.style.display = 'block';
                if (userCompanyId) loadCustomersForCompany(userCompanyId);
                setTimeout(function() { customerContainer.classList.add('is-visible'); }, 50);
                const customerSearch = document.getElementById('customerSearch');
                if (customerSearch) setTimeout(() => customerSearch.focus(), 100);
            } else {
                customerContainer.style.display = 'none';
            }
        }
        
        if (deviceContainer) {
            deviceContainer.style.display = 'block';
            setTimeout(function() { deviceContainer.classList.add('is-visible'); }, 50);
            const search = document.getElementById('deviceSearch');
            if (search) setTimeout(() => search.focus(), 100);
        }
        // Scroll-Fade aktualisieren
        setTimeout(updateScrollFade, 150);
        return;
    }
    
    // Prüfen, ob Firma in Nav gesetzt ist
    const savedSelection = localStorage.getItem('selectedUserOption');
    let isFromNav = false;
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            const companyId = document.getElementById('company_id')?.value;
            isFromNav = data.id && data.id !== '0' && parseInt(data.id) === parseInt(companyId);
        } catch (e) {}
    }
    
    const customerId = document.getElementById('customer_id')?.value;
    const deviceId = document.getElementById('device_id')?.value;
    
    // Wenn Firma aus Nav kommt und nur Firma ausgewählt ist (kein Kunde, kein Gerät),
    // dann kompakte Card NICHT verstecken
    const shouldKeepCompactCard = isFromNav && !customerId && !deviceId;
    
    if (!shouldKeepCompactCard) {
        // Kompakte Card verstecken
        if (compactContainer) {
            compactContainer.style.display = 'none';
        }
        
        // Abstand der Bearbeiter/Anforderer Card bleibt durch mt-4 CSS-Klasse
        
        // Firma, Kunde und Gerät Cards anzeigen (mit Einblend-Animation)
        if (companyContainer && !isFromNav) {
            companyContainer.style.display = 'block';
            setTimeout(function() { companyContainer.classList.add('is-visible'); }, 50);
        }
        if (customerContainer && customerId) {
            customerContainer.style.display = 'block';
            setTimeout(function() { customerContainer.classList.add('is-visible'); }, 50);
        }
        if (deviceContainer && deviceId) {
            deviceContainer.style.display = 'block';
            setTimeout(function() { deviceContainer.classList.add('is-visible'); }, 50);
        }
    } else {
        if (customerContainer && customerId) {
            customerContainer.style.display = 'block';
            setTimeout(function() { customerContainer.classList.add('is-visible'); }, 50);
        }
        if (deviceContainer && deviceId) {
            deviceContainer.style.display = 'block';
            setTimeout(function() { deviceContainer.classList.add('is-visible'); }, 50);
        }
    }
}

function selectCustomer(id, name) {
    const customerHiddenInput = document.getElementById('customer_id');
    const customerSelectedText = document.getElementById('customerSelectedText');
    
    // Prüfe ob sich der Kunde geändert hat
    const oldCustomerId = customerHiddenInput ? customerHiddenInput.value : null;
    const customerChanged = oldCustomerId && parseInt(oldCustomerId) !== id;
    
    if (customerHiddenInput) {
        customerHiddenInput.value = id;
    }
    if (customerSelectedText) {
        customerSelectedText.textContent = name;
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.customer-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Ausgewählte Zeile markieren
    const selectedRow = document.querySelector(`.customer-row[data-id="${id}"]`);
    if (selectedRow) {
        selectedRow.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
    }
    
    // Wenn sich der Kunde geändert hat, Gerät zurücksetzen
    if (customerChanged) {
        clearDeviceSelection();
    }
    
    // Geräte für diesen Kunden laden
    loadDevicesForCustomer(id);
    
    // Details schließen
    setTimeout(() => {
        const customerContainer = document.getElementById('customerSelectContainer');
        if (customerContainer) {
            const details = customerContainer.querySelector('details');
            if (details && details.hasAttribute('open')) {
                details.removeAttribute('open');
            }
        }
    }, 100);
    
    // Kompakte Card aktualisieren
    checkAndShowCompactCard();
}

// Funktionen zum Zurücksetzen der Auswahl
function clearCompanySelection() {
    // Nur Admins/Techniker dürfen die Firma ändern
    if (!isAdminOrTech) {
        return;
    }
    const companyHiddenInput = document.getElementById('company_id');
    const companySelectedText = document.getElementById('companySelectedText');
    
    if (companyHiddenInput) {
        companyHiddenInput.value = '';
    }
    if (companySelectedText) {
        companySelectedText.textContent = '-- Keine Firma --';
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.company-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Kunden und Geräte ausblenden
    const customerContainer = document.getElementById('customerSelectContainer');
    const deviceContainer = document.getElementById('deviceSelectContainer');
    if (customerContainer) customerContainer.style.display = 'none';
    if (deviceContainer) deviceContainer.style.display = 'none';
    
    // Kompakte Card ausblenden
    const compactContainer = document.getElementById('companyCustomerCompactContainer');
    if (compactContainer) compactContainer.style.display = 'none';
    
    // Auswahl zurücksetzen
    clearCustomerSelection();
    clearDeviceSelection();
    if (!isCustomer) {
        clearObserverSelection();
        const observersTableBody = document.getElementById('observersTableBody');
        if (observersTableBody) {
            observersTableBody.innerHTML = '<tr><td colspan="2" class="px-3 py-2 text-center text-gray-500 dark:text-primary-210">Bitte Firma auswählen</td></tr>';
        }
    }
    
    // Anforderer zurücksetzen (nur für Admins/Techniker)
    if (userRole === 'Admin' || userRole === 'Techniker') {
        clearRequesterSelection();
        const anfordererContainer = document.getElementById('anfordererContainer');
        if (anfordererContainer) {
            anfordererContainer.style.display = 'none';
        }
        checkAndShowAssigneeCompactCard();
    }
}

function clearCustomerSelection() {
    const customerHiddenInput = document.getElementById('customer_id');
    const customerSelectedText = document.getElementById('customerSelectedText');
    
    if (customerHiddenInput) {
        customerHiddenInput.value = '';
    }
    if (customerSelectedText) {
        customerSelectedText.textContent = '-- Kein Kunde --';
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.customer-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Geräte der Firma neu laden
    const companyHiddenInput = document.getElementById('company_id');
    if (companyHiddenInput && companyHiddenInput.value) {
        loadDevicesForCompany(parseInt(companyHiddenInput.value));
    }
    
    // Kompakte Card prüfen
    checkAndShowCompactCard();
}

function clearDeviceSelection() {
    const deviceHiddenInput = document.getElementById('device_id');
    const deviceSelectedText = document.getElementById('deviceSelectedText');
    
    if (deviceHiddenInput) {
        deviceHiddenInput.value = '';
    }
    if (deviceSelectedText) {
        deviceSelectedText.textContent = '-- Kein Gerät --';
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.device-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Kompakte Card aktualisieren
    checkAndShowCompactCard();
}

function selectDevice(id, name) {
    const deviceHiddenInput = document.getElementById('device_id');
    const deviceSelectedText = document.getElementById('deviceSelectedText');
    
    if (deviceHiddenInput) {
        deviceHiddenInput.value = id;
    }
    if (deviceSelectedText) {
        deviceSelectedText.textContent = name;
    }
    
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.device-row').forEach(row => {
        row.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Ausgewählte Zeile markieren
    const selectedRow = document.querySelector(`.device-row[data-id="${id}"]`);
    if (selectedRow) {
        selectedRow.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
    }
    
    // Details schließen
    setTimeout(() => {
        const deviceContainer = document.getElementById('deviceSelectContainer');
        if (deviceContainer) {
            const details = deviceContainer.querySelector('details');
            if (details && details.hasAttribute('open')) {
                details.removeAttribute('open');
            }
        }
    }, 100);
    
    // Kompakte Card aktualisieren
    checkAndShowCompactCard();
}

// Filter-Funktion für Tabellen
function filterTable(tableBodyId, searchTerm, rowClass) {
    const tableBody = document.getElementById(tableBodyId);
    if (!tableBody) return;
    
    const rows = tableBody.querySelectorAll(`.${rowClass}`);
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // "Alle auswählen" Checkbox aktualisieren
    if (tableBodyId === 'observersTableBody') {
        const visibleCheckboxes = Array.from(rows).filter(row => row.style.display !== 'none')
            .map(row => row.querySelector('.observer-checkbox'))
            .filter(cb => cb);
        const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
        const selectAllCheckbox = document.getElementById('selectAllObservers');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allChecked;
        }
    }
}

// Zeichenzähler für Betreff
const titelInput = document.getElementById('titel');
const titelCounter = document.getElementById('titel-counter');
if (titelInput && titelCounter) {
    const maxLength = 50;
    let lastLength = 0;
    
    function updateTitelCounter() {
        const length = titelInput.value.length;
        titelCounter.textContent = length + ' / ' + maxLength + ' Zeichen';
        
        // Farbe ändern wenn nahe am Limit
        if (length > maxLength * 0.9) {
            titelCounter.classList.add('text-red-500', 'dark:text-red-400');
            titelCounter.classList.remove('text-gray-500', 'dark:text-primary-240');
        } else {
            titelCounter.classList.remove('text-red-500', 'dark:text-red-400');
            titelCounter.classList.add('text-gray-500', 'dark:text-primary-240');
        }
        
        // Toast-Fehlermeldung wenn Limit überschritten wurde
        if (length > maxLength && lastLength <= maxLength) {
            if (typeof showToast === 'function') {
                showToast('Der Betreff darf maximal ' + maxLength + ' Zeichen lang sein. Der Text wurde automatisch gekürzt.', 'error', 5000);
            } else {
                alert('Der Betreff darf maximal ' + maxLength + ' Zeichen lang sein. Der Text wurde automatisch gekürzt.');
            }
        }
        
        lastLength = length;
    }
    
    // Initiale Anzeige
    updateTitelCounter();
    lastLength = titelInput.value.length;
    
    // Bei jeder Eingabe aktualisieren
    titelInput.addEventListener('input', updateTitelCounter);
    
    // Beim Einfügen prüfen
    titelInput.addEventListener('paste', function(e) {
        // Kurze Verzögerung, damit der Wert nach dem Einfügen geprüft werden kann
        setTimeout(function() {
            const pastedLength = titelInput.value.length;
            if (pastedLength > maxLength) {
                if (typeof showToast === 'function') {
                    showToast('Der Betreff darf maximal ' + maxLength + ' Zeichen lang sein. Der eingefügte Text wurde automatisch gekürzt.', 'error', 5000);
                } else {
                    alert('Der Betreff darf maximal ' + maxLength + ' Zeichen lang sein. Der eingefügte Text wurde automatisch gekürzt.');
                }
            }
            updateTitelCounter();
        }, 10);
    });
}

// Event Listener für beide Submit-Buttons
document.getElementById('createAndViewButton')?.addEventListener('click', function(e) {
    e.preventDefault();
    currentAction = 'create_and_view';
    // Formular absenden
    const form = document.getElementById('ticketForm');
    if (form) {
        form.requestSubmit();
    }
});

document.getElementById('createButton')?.addEventListener('click', function(e) {
    e.preventDefault();
    currentAction = 'create';
    // Formular absenden
    const form = document.getElementById('ticketForm');
    if (form) {
        form.requestSubmit();
    }
});

document.getElementById('ticketForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validierung: Pflichtfelder prüfen
    let validationErrors = [];
    
    // Betreff prüfen
    const titelInput = document.getElementById('titel');
    const titel = titelInput ? titelInput.value.trim() : '';
    const maxTitelLength = 50;
    
    if (!titel) {
        validationErrors.push('Betreff');
        if (titelInput) {
            titelInput.classList.add('border-red-500', 'dark:border-red-500');
            titelInput.classList.remove('border-gray-300', 'dark:border-primary-320');
            titelInput.focus();
            // Fehlerklasse nach kurzer Zeit entfernen
            setTimeout(() => {
                titelInput.classList.remove('border-red-500', 'dark:border-red-500');
                titelInput.classList.add('border-gray-300', 'dark:border-primary-320');
            }, 3000);
        }
    } else if (titel.length > maxTitelLength) {
        // Betreff zu lang
        if (typeof showToast === 'function') {
            showToast('Der Betreff darf maximal ' + maxTitelLength + ' Zeichen lang sein. Bitte kürzen Sie den Betreff.', 'error', 5000);
        } else {
            alert('Der Betreff darf maximal ' + maxTitelLength + ' Zeichen lang sein. Bitte kürzen Sie den Betreff.');
        }
        if (titelInput) {
            titelInput.classList.add('border-red-500', 'dark:border-red-500');
            titelInput.classList.remove('border-gray-300', 'dark:border-primary-320');
            titelInput.focus();
            // Fehlerklasse nach kurzer Zeit entfernen
            setTimeout(() => {
                titelInput.classList.remove('border-red-500', 'dark:border-red-500');
                titelInput.classList.add('border-gray-300', 'dark:border-primary-320');
            }, 3000);
        }
        return;
    } else {
        // Wenn Betreff vorhanden ist, Fehlerklasse entfernen
        if (titelInput) {
            titelInput.classList.remove('border-red-500', 'dark:border-red-500');
            titelInput.classList.add('border-gray-300', 'dark:border-primary-320');
        }
    }
    
    // Firma prüfen
    let companyId = null;
    const savedSelection = localStorage.getItem('selectedUserOption');
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            companyId = data.id && data.id !== '0' ? parseInt(data.id) : null;
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }
    
    // Falls keine Firma in Nav gesetzt, aus Dropdown nehmen
    if (!companyId) {
        const companySelect = document.getElementById('company_id');
        if (companySelect && companySelect.value) {
            companyId = parseInt(companySelect.value);
        }
    }
    
    if (!companyId) {
        validationErrors.push('Firma');
        // Firmenauswahl ist jetzt immer sichtbar
        const companyContainer = document.getElementById('companySelectContainer');
        if (companyContainer) {
            companyContainer.style.display = 'block';
        }
    }
    
    // Wenn Validierungsfehler vorhanden sind, Fehlermeldung anzeigen und abbrechen
    if (validationErrors.length > 0) {
        let errorMessage = 'Bitte füllen Sie die folgenden Pflichtfelder aus: ' + validationErrors.join(', ');
        
        // Toast-Funktion aufrufen (mit kurzem Delay, falls sie noch nicht geladen ist)
        setTimeout(() => {
            if (typeof showToast === 'function') {
                showToast(errorMessage, 'error', 5000);
            } else {
                alert(errorMessage);
            }
        }, 100);
        
        return;
    }
    
    // Verwende die gespeicherte Aktion
    const action = currentAction;
    
    // Dateien aus Input lesen, falls selectedFiles leer ist
    if (selectedFiles.length === 0) {
        const fileInput = document.getElementById('attachment_files');
        if (fileInput && fileInput.files && fileInput.files.length > 0) {
            selectedFiles = Array.from(fileInput.files);
        }
    }
    
    const formData = {
        titel: titel,
        beschreibung: document.getElementById('beschreibung').value.trim() || '',
        company_id: companyId
    };
    
    const customerId = document.getElementById('customer_id')?.value;
    if (customerId) {
        formData.customer_id = parseInt(customerId);
    }
    
    const deviceId = document.getElementById('device_id')?.value;
    if (deviceId) {
        formData.device_id = parseInt(deviceId);
    }
    
    const zugewiesenAn = document.getElementById('zugewiesen_an')?.value;
    if (canSetAssignee && zugewiesenAn) {
        formData.zugewiesen_an = parseInt(zugewiesenAn);
    }
    
    // Anforderer hinzufügen (nur für Admins/Techniker)
    const anfordererId = document.getElementById('anforderer_id')?.value;
    if (anfordererId) {
        formData.anforderer_id = parseInt(anfordererId);
    }
    
    // Datumsfelder hinzufügen (Reihenfolge: Geplant, dann Fällig)
    const geplantDatum = document.getElementById('geplant_datum')?.value;
    if (isAdminOrTech && geplantDatum) {
        formData.geplant_datum = geplantDatum;
    }
    const geplantDatumEnde = document.getElementById('geplant_datum_ende')?.value;
    if (isAdminOrTech && geplantDatumEnde) {
        formData.geplant_datum_ende = geplantDatumEnde;
    }
    
    const faelligDatum = document.getElementById('faellig_datum')?.value;
    if (faelligDatum) {
        formData.faellig_datum = faelligDatum;
    }
    const faelligDatumEnde = document.getElementById('faellig_datum_ende')?.value;
    if (faelligDatumEnde) {
        formData.faellig_datum_ende = faelligDatumEnde;
    }
    
    // Beobachter sammeln
    const observerInput = document.getElementById('observer_ids_input');
    if (!isCustomer && observerInput && observerInput.value) {
        const observerIds = observerInput.value.split(',').filter(id => id.trim() !== '').map(id => parseInt(id.trim()));
        if (observerIds.length > 0) {
            formData.observer_ids = observerIds;
        }
    }
    
    // Ticket erstellen
    fetch(ticketsApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const ticketId = data.ticket_id;
            
            // Dateien aus Input lesen, falls selectedFiles leer ist
            let filesToUpload = selectedFiles;
            if (filesToUpload.length === 0) {
                const fileInput = document.getElementById('attachment_files');
                if (fileInput && fileInput.files && fileInput.files.length > 0) {
                    filesToUpload = Array.from(fileInput.files);
                }
            }
            
            // Anhänge hochladen, falls vorhanden
            const uploadPromise = filesToUpload.length > 0 
                ? uploadAttachments(ticketId, filesToUpload)
                : Promise.resolve();
            
            uploadPromise
                .then(() => {
                    if (typeof showToast === 'function') {
                        showToast('Ticket erfolgreich erstellt', 'success');
                    }
                    
                    // Je nach Aktion unterschiedlich verhalten
                    if (action === 'create_and_view') {
                        // Zum erstellten Auftrag springen
                        window.location.href = '<?php echo BASE_URL; ?>tickets/view.php?id=' + ticketId;
                    } else {
                        // Auf der Seite bleiben und Formular zurücksetzen
                        document.getElementById('ticketForm').reset();
                        selectedFiles = [];
                        displayAttachmentPreview();
                        // Scroll nach oben
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Hochladen der Anhänge:', error);
                    if (typeof showToast === 'function') {
                        showToast('Ticket erstellt, aber Fehler beim Hochladen der Anhänge', 'warning');
                    }
                    
                    if (action === 'create_and_view') {
                        window.location.href = '<?php echo BASE_URL; ?>tickets/view.php?id=' + ticketId;
                    } else {
                        document.getElementById('ticketForm').reset();
                        selectedFiles = [];
                        displayAttachmentPreview();
                    }
                });
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Erstellen des Tickets', 'error');
        } else {
            alert('Fehler beim Erstellen des Tickets');
        }
    });
});

function uploadAttachments(ticketId, files) {
    const attachmentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/attachments.php';
    const uploadPromises = [];
    const totalBytes = files.reduce((sum, file) => sum + (file && file.size ? file.size : 0), 0);
    const loadedByFile = new Array(files.length).fill(0);

    const updateProgressToast = function() {
        const loadedBytes = loadedByFile.reduce((sum, value) => sum + value, 0);
        const progressPercent = totalBytes > 0 ? (loadedBytes / totalBytes) * 100 : 0;
        if (typeof updateUploadProgressToast === 'function') {
            updateUploadProgressToast(progressPercent, files.length > 1 ? 'Anhänge werden hochgeladen...' : 'Anhang wird hochgeladen...');
        }
    };

    if (typeof showUploadProgressToast === 'function') {
        showUploadProgressToast(files.length > 1 ? 'Anhänge werden hochgeladen...' : 'Anhang wird hochgeladen...', 0);
    }
    
    files.forEach((file, index) => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('ticket_id', ticketId.toString());
        
        uploadPromises.push(
            new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', attachmentsApiUrl, true);

                xhr.upload.onprogress = function(event) {
                    if (!event.lengthComputable) return;
                    loadedByFile[index] = event.loaded;
                    updateProgressToast();
                };

                xhr.onreadystatechange = function() {
                    if (xhr.readyState !== 4) return;
                    if (xhr.status < 200 || xhr.status >= 300) {
                        reject(new Error('HTTP error! status: ' + xhr.status));
                        return;
                    }

                    try {
                        const data = JSON.parse(xhr.responseText || '{}');
                        if (!data.success) {
                            console.error('Upload-Fehler für Datei:', file.name, data.error);
                            reject(new Error(data.error || 'Fehler beim Hochladen'));
                            return;
                        }
                        loadedByFile[index] = file && file.size ? file.size : loadedByFile[index];
                        updateProgressToast();
                        resolve(data);
                    } catch (parseError) {
                        reject(new Error('Ungültige Server-Antwort beim Upload'));
                    }
                };

                xhr.onerror = function() {
                    reject(new Error('Netzwerkfehler beim Hochladen'));
                };

                xhr.send(formData);
            })
            .catch(error => {
                console.error('Fehler beim Hochladen der Datei:', file.name, error);
                throw error;
            })
        );
    });
    
    return Promise.all(uploadPromises)
        .then(result => {
            if (typeof updateUploadProgressToast === 'function') {
                updateUploadProgressToast(100, 'Upload abgeschlossen');
            }
            if (typeof hideUploadProgressToast === 'function') {
                window.setTimeout(hideUploadProgressToast, 320);
            }
            return result;
        })
        .catch(error => {
            if (typeof hideUploadProgressToast === 'function') {
                hideUploadProgressToast();
            }
            throw error;
        });
}
// Scroll-Indikator für rechte Spalte
function updateScrollFade() {
    const scrollContainer = document.getElementById('rightColumnScrollContainer');
    const fadeBottom = document.getElementById('scrollFadeBottom');
    
    if (!scrollContainer || !fadeBottom) return;
    
    const hasScroll = scrollContainer.scrollHeight > scrollContainer.clientHeight;
    const isScrolledToBottom = scrollContainer.scrollHeight - scrollContainer.scrollTop <= scrollContainer.clientHeight + 5;
    const isAtTop = scrollContainer.scrollTop <= 5; // Nur oben (noch nicht gescrollt)
    
    // Fade am unteren Rand nur anzeigen, wenn:
    // 1. Es scrollbar ist
    // 2. Nicht ganz unten ist
    // 3. Noch nicht gescrollt wurde (oben)
    if (hasScroll && !isScrolledToBottom && isAtTop) {
        fadeBottom.style.opacity = '1';
    } else {
        fadeBottom.style.opacity = '0';
    }
}

// Event-Listener für Scroll
document.addEventListener('DOMContentLoaded', function() {
    const scrollContainer = document.getElementById('rightColumnScrollContainer');
    if (scrollContainer) {
        scrollContainer.addEventListener('scroll', updateScrollFade);
        // Initial prüfen
        setTimeout(updateScrollFade, 100);
        // Bei Größenänderungen erneut prüfen
        const resizeObserver = new ResizeObserver(() => {
            updateScrollFade();
        });
        resizeObserver.observe(scrollContainer);
    }
});
</script>

<style>
/* Custom Scrollbar für rechte Spalte */
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

/* Edit-Cards (Firma/Kunde/Gerät): Einblend-Animation wie in view.php */
.company-customer-edit-cards-wrapper {
    display: block;
}
.edit-card {
    opacity: 0;
    transform: translateY(14px);
    max-height: 600px;
    transition: opacity 0.35s ease-out, transform 0.35s ease-out, max-height 0.4s ease-out, margin 0.35s ease-out, padding 0.35s ease-out;
}
.edit-card.edit-card-company { transition-delay: 0s; }
.edit-card.edit-card-customer { transition-delay: 0.08s; }
.edit-card.edit-card-device { transition-delay: 0.16s; }
.edit-card.is-visible {
    opacity: 1;
    transform: translateY(0);
}
.edit-card.is-visible.edit-card-company { transition-delay: 0s; }
.edit-card.is-visible.edit-card-customer { transition-delay: 0.22s; }
.edit-card.is-visible.edit-card-device { transition-delay: 0.44s; }
</style>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
