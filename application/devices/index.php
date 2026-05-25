<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
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
$companies = [];
$customers = [];

if ($userRole === 'Admin' || $userRole === 'Techniker') {
    // Alle aktiven Firmen
    $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Alle aktiven Kunden mit Kundennummer und Firmenname
    $stmt = $pdo->query("SELECT c.id, c.name, c.kundennummer, c.email, c.company_id, comp.name as company_name FROM customers c LEFT JOIN companies comp ON c.company_id = comp.id WHERE c.status = 'aktiv' ORDER BY c.name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
    // Nur eigene Firma
    $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv'");
    $stmt->execute([$userCompanyId]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kunden der Firma und Kunden ohne Firma mit Kundennummer
    $stmt = $pdo->prepare("SELECT c.id, c.name, c.kundennummer, c.email, c.company_id, comp.name as company_name FROM customers c LEFT JOIN companies comp ON c.company_id = comp.id WHERE (c.company_id = ? OR c.company_id IS NULL) AND c.status = 'aktiv' ORDER BY c.name");
    $stmt->execute([$userCompanyId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
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
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Geräteverwaltung</span>
          </div>
        </li>
      </ol>
    </nav>

  <div class="relative col-span-full">
    <div class="">
    <div class="relative">
      <div class="flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0">
        <div class="flex flex-col w-full space-y-3 lg:w-2/3 md:space-y-0 md:flex-row md:items-center">
          <form class="flex-1 w-full md:max-w-sm md:mr-2">
            <label for="default-search"
                   class="text-sm font-medium text-gray-900 sr-only dark:text-white">Suche</label>
            <div class="relative" id="search-wrapper">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg aria-hidden="true" class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none"
                     stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
              </div>
              <input type="search" id="search"
       class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-820 focus:border-primary-820 dark:bg-primary-300 dark:border-primary-320 dark:placeholder-primary-210 dark:text-primary-200 dark:focus:ring-primary-820 dark:focus:border-primary-820 transition-colors"
                     placeholder="Name, Seriennummer, MAC, Standort …"> 
            </div>
          </form>
          <div class="w-full md:w-auto md:mr-2">
            <label for="typ-filter" class="text-sm font-medium text-gray-900 sr-only dark:text-white">Typ</label>
            <select id="typ-filter" class="block w-full px-4 py-2 text-sm font-medium text-gray-900 border border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100 focus:ring-primary-500 focus:border-primary-500 dark:bg-primary-700 dark:border-primary-320 dark:text-primary-210 dark:hover:bg-primary-760 dark:focus:ring-primary-500 dark:focus:border-primary-500">
              <option value="">Alle Typen</option>
              <option value="drucker">Drucker</option>
              <option value="computer">Computer</option>
              <option value="netzwerk">Netzwerk</option>
              <option value="smartphone">Smartphone</option>
              <option value="monitor">Monitor</option>
              <option value="divers">Divers</option>
            </select>
          </div>
          <div class="w-full md:w-auto md:mr-2" id="manufacturer-filter-container">
            <label for="manufacturer-filter" class="text-sm font-medium text-gray-900 sr-only dark:text-white">Hersteller</label>
            <select id="manufacturer-filter" class="block w-full px-4 py-2 text-sm font-medium text-gray-900 border border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100 focus:ring-primary-500 focus:border-primary-500 dark:bg-primary-700 dark:border-primary-320 dark:text-primary-210 dark:hover:bg-primary-760 dark:focus:ring-primary-500 dark:focus:border-primary-500">
              <option value="">Alle Hersteller</option>
            </select>
          </div>
          <div class="w-full md:w-auto md:mr-2" id="model-filter-container" style="display: none;">
            <label for="model-filter" class="text-sm font-medium text-gray-900 sr-only dark:text-white">Modell</label>
            <select id="model-filter" class="block w-full px-4 py-2 text-sm font-medium text-gray-900 border border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100 focus:ring-primary-500 focus:border-primary-500 dark:bg-primary-700 dark:border-primary-320 dark:text-primary-210 dark:hover:bg-primary-760 dark:focus:ring-primary-500 dark:focus:border-primary-500">
              <option value="">Alle Modelle</option>
            </select>
          </div>
          <?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') && !empty($customers)): ?>
          <div class="w-full md:w-auto md:mr-2" id="customer-filter-container">
            <button type="button" id="customer-filter-button" class="customer-filter-btn flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-gray-50 hover:bg-gray-100 focus:ring-primary-500 focus:border-primary-500 dark:bg-primary-700 dark:border-primary-320 dark:text-primary-210 dark:hover:bg-primary-760">
              <span id="customer-filter-text">Alle Kunden</span>
              <svg class="ml-2 w-4 h-4 text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <input type="hidden" id="customer-filter" value="">
          </div>
          <?php endif; ?>
          <button type="button" id="reset-devices-filters-btn" class="inline-flex items-center justify-center p-2 text-sm font-medium text-gray-600 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140 focus:outline-none ml-1" title="Filter zurücksetzen (Aktiv, Alle)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
          </button>
        </div>
        <div class="flex flex-col items-stretch justify-end flex-shrink-0 w-full pb-4 md:pb-0 md:w-auto md:flex-row md:items-center md:space-x-3">
          <div class="flex items-center gap-2 mr-3">
            <button type="button" id="viewTable" class="view-toggle p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700" title="Tabellenansicht">
              <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </button>
            <button type="button" id="viewCards" class="view-toggle p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700" title="Card-Ansicht">
              <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
              </svg>
            </button>
          </div>
          <a href="<?php echo BASE_URL; ?>devices/create.php"
                  class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
            <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewbox="0 0 20 20"
                 xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Geräte hinzufügen
          </a>
        </div>
      </div>
      <div class="flex flex-wrap pt-1 pb-4 border-t dark:border-gray-700">
        <div class="items-center hidden mt-3 mr-4 text-sm font-medium text-gray-900 md:flex dark:text-white">
          Status:
        </div>
        <div class="flex flex-wrap">
          <div class="flex items-center mt-3 mr-4">
            <input id="aktiv" type="radio" value="aktiv" checked name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="aktiv" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Aktiv
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="inaktiv" type="radio" value="inaktiv" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="inaktiv" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Inaktiv
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="wartung" type="radio" value="wartung" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="wartung" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Wartung
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="ausgemustert" type="radio" value="ausgemustert" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="ausgemustert" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Ausgemustert
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="all" type="radio" value="" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600  dark:bg-gray-700 dark:border-gray-600">
            <label for="all" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Alle
            </label>
          </div>
        </div>
      </div>     

    <!-- Tabellenansicht -->
    <div id="tableView" class="overflow-x-auto">
      <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
        <tr>
<th id="sort-name" data-sort="name" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Name
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-status" data-sort="status" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Status
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-seriennummer" data-sort="seriennummer" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Seriennummer
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-os_type" data-sort="betriebssystem" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Betriebssystem
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-ip_address" data-sort="ip_adresse" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Netzwerk
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-beschreibung" data-sort="beschreibung" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Standort
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-customer_name" data-sort="customer_name" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" <?php if ($userRole === 'Kunde'): ?>style="display: none;"<?php endif; ?>>
  <div class="flex items-center">
    Kunde
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-user_name" data-sort="user_name" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" <?php if ($userRole === 'Kunde'): ?>style="display: none;"<?php endif; ?>>
  <div class="flex items-center">
    Benutzer
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-company_name" data-sort="company_name" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" <?php if ($userRole !== 'Admin' && $userRole !== 'Techniker'): ?>style="display: none;"<?php endif; ?>>
  <div class="flex items-center">
    Firma
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
            <th scope="col" class="px-4 py-3 font-semibold">Aktionen</th>
        </tr>
    </thead>
    <tbody id="computerList">
        <tr>
            <td colspan="<?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? '10' : '9'; ?>" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">
                <i class="fas fa-spinner fa-spin mr-2"></i> Lade Geräte...
            </td>
        </tr>
    </tbody>
</table>     
    </div>
    
    <!-- Card-Ansicht -->
    <div id="cardsView" class="hidden">
      <div id="deviceCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">
            <i class="fas fa-spinner fa-spin mr-2"></i> Lade Geräte...
        </div>
      </div>
    </div>
    </div>
    </div>
  </div>
</div>
        </div>
      </main>
  </div>

<!-- Modal für Kundenauswahl -->
<?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') && !empty($customers)): ?>
<div id="customerModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="customer-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="customerModalOverlay"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
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
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
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

<style>
#customer-filter-button.customer-filter-btn--active {
    background-color: #10204A;
    border-color: #3b82f6;
    color: white;
}
#customer-filter-button.customer-filter-btn--active:hover {
    background-color: #bfdbfe;
}
.dark #customer-filter-button.customer-filter-btn--active {
    background-color: #10204A;
    border-color: #3b82f6;
    color: white;
}
.dark #customer-filter-button.customer-filter-btn--active:hover {
    background-color: #0b1938;
}
#search-wrapper.search-active input {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}
.dark #search-wrapper.search-active input {
    border-color: #60a5fa;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
}
</style>

<script>
// Funktion zur Ermittlung des Icons für den Gerätetyp
function getTypeIcon(typ) {
    const icons = {
        'drucker': '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>',
        'computer': '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
        'netzwerk': '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" /></svg>',
        'smartphone': '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
        'monitor': '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
        'divers': '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>'
    };
    return icons[typ] || '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>';
}

// Funktion zur Formatierung der Details je nach Typ
function formatDeviceDetails(device) {
    if (!device.details || Object.keys(device.details).length === 0) {
        return '-';
    }
    
    const details = device.details;
    const parts = [];
    
    switch(device.typ) {
        case 'drucker':
            if (details.farbzaehler) parts.push(`Farb: ${details.farbzaehler}`);
            if (details.sw_zaehler) parts.push(`SW: ${details.sw_zaehler}`);
            if (details.papierformat) parts.push(`Format: ${details.papierformat}`);
            break;
        case 'computer':
            if (details.cpu) parts.push(`CPU: ${details.cpu}`);
            if (details.ram) parts.push(`RAM: ${details.ram}GB`);
            if (details.festplatte) parts.push(`HDD: ${details.festplatte}GB`);
            break;
        case 'netzwerk':
            if (details.ports) parts.push(`${details.ports} Ports`);
            if (details.port_typ) parts.push(details.port_typ);
            if (details.poe) parts.push(`PoE: ${details.poe}`);
            break;
        case 'smartphone':
            if (details.prozessor) parts.push(`CPU: ${details.prozessor}`);
            if (details.ram) parts.push(`RAM: ${details.ram}GB`);
            if (details.speicher) parts.push(`Speicher: ${details.speicher}GB`);
            break;
        case 'monitor':
            if (details.groesse) parts.push(`${details.groesse}"`);
            if (details.aufloesung) parts.push(details.aufloesung);
            if (details.panel_typ) parts.push(details.panel_typ);
            break;
        case 'divers':
            if (details.spezifikation1) parts.push(details.spezifikation1);
            if (details.spezifikation2) parts.push(details.spezifikation2);
            break;
    }
    
    return parts.length > 0 ? parts.join(', ') : '-';
}

// Funktion zur Bestimmung des Status - verwendet direkt den Status aus der Datenbank
function getDeviceStatus(device) {
    return device.status || 'aktiv';
}

// Funktion zur Formatierung des Datums
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
</script>

<script>
const devicesApiUrl = '<?php echo BASE_URL; ?>devices/api/devices.php';
const userRole = '<?php echo addslashes($userRole); ?>';
const userId = <?php echo (int)$userId; ?>;
let selectedCompanyId = null;
let allDevices = [];
let filteredDevices = [];
let currentView = 'table'; // 'table' oder 'cards'
let sortColumn = null;
let sortDirection = 'asc'; // 'asc' oder 'desc'

const DEVICES_FILTER_STORAGE_KEY = 'devicesIndexFilters';

function parseJsonResponse(response, endpointLabel = 'API') {
    return response.text().then(raw => {
        if (!raw || !raw.trim()) {
            throw new Error(`Leere Antwort von ${endpointLabel}`);
        }
        try {
            return JSON.parse(raw);
        } catch (parseError) {
            console.error(`Ungültige JSON-Antwort von ${endpointLabel} (erste 300 Zeichen):`, raw.slice(0, 300));
            throw parseError;
        }
    });
}

function getDevicesFiltersState() {
    const searchEl = document.getElementById('search');
    const typEl = document.getElementById('typ-filter');
    const manufacturerEl = document.getElementById('manufacturer-filter');
    const modelEl = document.getElementById('model-filter');
    const customerFilter = document.getElementById('customer-filter');
    const customerFilterText = document.getElementById('customer-filter-text');
    const statusRadio = document.querySelector('input[name="status"]:checked');
    return {
        search: searchEl ? searchEl.value : '',
        typ: typEl ? typEl.value : '',
        manufacturer: manufacturerEl ? manufacturerEl.value : '',
        model: modelEl ? modelEl.value : '',
        customer: customerFilter ? customerFilter.value : '',
        customerText: (customerFilterText && customerFilterText.textContent) ? customerFilterText.textContent.trim() : '',
        status: statusRadio ? statusRadio.value : 'aktiv'
    };
}

function saveDevicesFiltersState() {
    try {
        localStorage.setItem(DEVICES_FILTER_STORAGE_KEY, JSON.stringify(getDevicesFiltersState()));
    } catch (e) {
        console.error('Fehler beim Speichern der Geräte-Filter', e);
    }
}

function restoreDevicesFiltersState() {
    try {
        const raw = localStorage.getItem(DEVICES_FILTER_STORAGE_KEY);
        if (!raw) return;
        const state = JSON.parse(raw);
        const searchEl = document.getElementById('search');
        const typEl = document.getElementById('typ-filter');
        const manufacturerEl = document.getElementById('manufacturer-filter');
        const modelEl = document.getElementById('model-filter');
        const customerFilter = document.getElementById('customer-filter');
        const customerFilterText = document.getElementById('customer-filter-text');
        if (state.search !== undefined && searchEl) searchEl.value = state.search || '';
        if (state.typ !== undefined && typEl) typEl.value = state.typ || '';
        if (state.manufacturer !== undefined && manufacturerEl) manufacturerEl.value = state.manufacturer || '';
        if (state.model !== undefined && modelEl) modelEl.value = state.model || '';
        if (state.customer !== undefined && customerFilter) customerFilter.value = state.customer || '';
        if (state.customerText !== undefined && customerFilterText) customerFilterText.textContent = state.customerText || 'Alle Kunden';
        if (state.status !== undefined) {
            const radio = document.querySelector(`input[name="status"][value="${state.status}"]`);
            if (radio) radio.checked = true;
        }
    } catch (e) {
        console.error('Fehler beim Wiederherstellen der Geräte-Filter', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    function updateCustomerFilterButtonState() {
        const customerFilterButton = document.getElementById('customer-filter-button');
        const customerFilter = document.getElementById('customer-filter');
        if (!customerFilterButton || !customerFilter) return;
        if (customerFilter.value && customerFilter.value.trim() !== '') {
            customerFilterButton.classList.add('customer-filter-btn--active');
        } else {
            customerFilterButton.classList.remove('customer-filter-btn--active');
        }
    }
    function updateSearchActiveState() {
        const wrapper = document.getElementById('search-wrapper');
        const searchEl = document.getElementById('search');
        if (!wrapper || !searchEl) return;
        wrapper.classList.toggle('search-active', searchEl.value.trim() !== '');
    }

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

    restoreDevicesFiltersState();
    
    // Gespeicherte Ansicht aus localStorage laden
    const savedView = localStorage.getItem('devicesView');
    if (savedView === 'table' || savedView === 'cards') {
        currentView = savedView;
    }
    
    // Initial Kunden-Filter basierend auf Firmenauswahl aktualisieren
    updateCustomerFilter();
    
    // View-Toggle Event Listener
    document.getElementById('viewTable').addEventListener('click', function() {
        currentView = 'table';
        localStorage.setItem('devicesView', 'table');
        document.getElementById('tableView').classList.remove('hidden');
        document.getElementById('cardsView').classList.add('hidden');
        this.classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewCards').classList.remove('bg-primary-100', 'dark:bg-primary-800');
        displayDevices(filteredDevices);
    });
    
    document.getElementById('viewCards').addEventListener('click', function() {
        currentView = 'cards';
        localStorage.setItem('devicesView', 'cards');
        document.getElementById('tableView').classList.add('hidden');
        document.getElementById('cardsView').classList.remove('hidden');
        this.classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewTable').classList.remove('bg-primary-100', 'dark:bg-primary-800');
        displayDevices(filteredDevices);
    });
    
    // Ansicht wiederherstellen
    if (currentView === 'cards') {
        document.getElementById('tableView').classList.add('hidden');
        document.getElementById('cardsView').classList.remove('hidden');
        document.getElementById('viewCards').classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewTable').classList.remove('bg-primary-100', 'dark:bg-primary-800');
    } else {
        document.getElementById('tableView').classList.remove('hidden');
        document.getElementById('cardsView').classList.add('hidden');
        document.getElementById('viewTable').classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewCards').classList.remove('bg-primary-100', 'dark:bg-primary-800');
    }
    
    // Suche Event Listener
    document.getElementById('search').addEventListener('input', function() {
        updateSearchActiveState();
        filterDevices();
        saveDevicesFiltersState();
    });
    
    // Typ-Filter Event Listener
    document.getElementById('typ-filter').addEventListener('change', function() {
        updateManufacturerFilter();
        filterDevices();
        saveDevicesFiltersState();
    });
    
    // Hersteller-Filter Event Listener
    document.getElementById('manufacturer-filter').addEventListener('change', function() {
        updateModelFilter();
        filterDevices();
        saveDevicesFiltersState();
    });
    
    // Modell-Filter Event Listener
    document.getElementById('model-filter').addEventListener('change', function() {
        filterDevices();
        saveDevicesFiltersState();
    });
    
    // Kunde-Filter Button Event Listener (nur wenn vorhanden)
    const customerFilterButton = document.getElementById('customer-filter-button');
    if (customerFilterButton) {
        customerFilterButton.addEventListener('click', function() {
            const customerModal = document.getElementById('customerModal');
            if (customerModal) {
                customerModal.classList.remove('hidden');
                // Suchfeld fokussieren
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
    
    // ESC-Taste zum Schließen
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && customerModal && !customerModal.classList.contains('hidden')) {
            closeCustomerModal();
        }
    });
    
    // Kundenauswahl im Modal
    document.addEventListener('click', (e) => {
        const row = e.target.closest('.select-customer-row');
        if (row && customerModal && !customerModal.classList.contains('hidden')) {
            const customerId = row.getAttribute('data-customer-id');
            const customerName = row.getAttribute('data-customer-display-name') || 'Alle Kunden';
            
            // Filter setzen
            const customerFilter = document.getElementById('customer-filter');
            const customerFilterText = document.getElementById('customer-filter-text');
            
            if (customerFilter) {
                customerFilter.value = customerId || '';
            }
            
            if (customerFilterText) {
                // Nur den Namen anzeigen, keine Email
                customerFilterText.textContent = customerName;
            }
            
            // Modal schließen
            closeCustomerModal();
            updateCustomerFilterButtonState();
            // Filter anwenden
            filterDevices();
            saveDevicesFiltersState();
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
        
        customerSearchInput.addEventListener('keyup', (e) => {
            filterCustomers(e.target.value);
        });
    }
    
    // Status Radio-Buttons Event Listener
    document.querySelectorAll('input[name="status"]').forEach(radio => {
        radio.addEventListener('change', function() {
            filterDevices();
            saveDevicesFiltersState();
        });
    });

    // Filter zurücksetzen: Aktiv, Alle Typen/Hersteller/Modelle/Kunden, Suche leer
    const resetDevicesFiltersBtn = document.getElementById('reset-devices-filters-btn');
    if (resetDevicesFiltersBtn) {
        resetDevicesFiltersBtn.addEventListener('click', function() {
            const aktivRadio = document.querySelector('input[name="status"][value="aktiv"]');
            if (aktivRadio) aktivRadio.checked = true;
            const searchEl = document.getElementById('search');
            if (searchEl) searchEl.value = '';
            const typEl = document.getElementById('typ-filter');
            if (typEl) typEl.value = '';
            const manufacturerEl = document.getElementById('manufacturer-filter');
            if (manufacturerEl) manufacturerEl.value = '';
            const modelEl = document.getElementById('model-filter');
            if (modelEl) modelEl.value = '';
            const customerFilter = document.getElementById('customer-filter');
            const customerFilterText = document.getElementById('customer-filter-text');
            if (customerFilter) customerFilter.value = '';
            if (customerFilterText) customerFilterText.textContent = 'Alle Kunden';
            updateManufacturerFilter();
            updateModelFilter();
            updateCustomerFilterButtonState();
            updateSearchActiveState();
            saveDevicesFiltersState();
            filterDevices();
        });
    }
    
    // Sortierung Event Listener für alle sortierbaren Spalten
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            sortDevices(column);
        });
    });
    
    // Event Listener für Firmenwechsel (aus Nav)
    window.addEventListener('companyChanged', function(e) {
        selectedCompanyId = e.detail.companyId;
        updateCustomerFilter();
        loadDevices();
    });

    updateCustomerFilterButtonState();
    updateSearchActiveState();
    saveDevicesFiltersState();

    loadDevices();
});

function updateCustomerFilter() {
    const customerContainer = document.getElementById('customer-filter-container');
    
    // Nur aktualisieren, wenn der Filter vorhanden ist (für Admin, Techniker, Firmen-Admin)
    if (!customerContainer) {
        return;
    }
    
    // Wenn keine Firma ausgewählt ist, prüfen ob initial Kunden vorhanden sind
    if (!selectedCompanyId) {
        // Für Admin und Techniker: Alle Kunden anzeigen
        // Für Firmen-Admin: Kunden der eigenen Firma anzeigen
        // Die initialen Kunden aus PHP bleiben erhalten
        // Button wird bereits durch PHP-Logik angezeigt/versteckt basierend auf $customers Array
        return;
    }
    
    // Kunden für die ausgewählte Firma laden und Modal aktualisieren
    fetch('<?php echo BASE_URL; ?>customers/api/customers.php?company_id=' + selectedCompanyId)
        .then(response => response.json())
        .then(data => {
            const customersTableBody = document.getElementById('customersTableBody');
            if (customersTableBody) {
                // "Alle Kunden" Option behalten
                let html = '<div class="customer-row hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer select-customer-row px-4 py-3" data-customer-id="" data-customer-name="" data-customer-display-name=""><div class="flex items-center"><span class="text-sm font-medium text-gray-900 dark:text-white">Alle Kunden</span></div></div>';
                
                // Prüfen ob Kunden vorhanden sind
                const hasCustomers = data.success && data.customers && data.customers.length > 0;
                
                if (hasCustomers) {
                    data.customers.forEach(customer => {
                        const customerName = (customer.name || '').toLowerCase();
                        const customerKundennummer = (customer.kundennummer || '').toLowerCase();
                        const customerCompanyName = (customer.company_name || '').toLowerCase();
                        const searchText = customerName + ' ' + customerKundennummer + ' ' + customerCompanyName;
                        const showCompany = userRole === 'Admin' || userRole === 'Techniker';
                        html += `
                            <div class="customer-row hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer select-customer-row px-4 py-3" 
                                 data-customer-id="${customer.id}"
                                 data-customer-name="${searchText}"
                                 data-customer-display-name="${escapeHtml(customer.name || '')}"
                                 data-customer-kundennummer="${escapeHtml(customer.kundennummer || '')}"
                                 data-customer-company-name="${escapeHtml(customer.company_name || '')}">
                                <div class="flex items-center">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white block truncate">
                                                ${escapeHtml(customer.name || '')}
                                            </span>
                                            ${showCompany && customer.company_name ? `<span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">${escapeHtml(customer.company_name)}</span>` : ''}
                                        </div>
                                        ${customer.kundennummer ? `<span class="text-xs text-gray-500 dark:text-gray-400 block truncate">Knd-Nr.: ${escapeHtml(customer.kundennummer)}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                customersTableBody.innerHTML = html;
                
                // Button nur anzeigen, wenn Kunden vorhanden sind
                if (hasCustomers) {
                    customerContainer.style.display = 'block';
                } else {
                    customerContainer.style.display = 'none';
                    // Filter zurücksetzen, wenn keine Kunden vorhanden
                    const customerFilter = document.getElementById('customer-filter');
                    const customerFilterText = document.getElementById('customer-filter-text');
                    if (customerFilter) {
                        customerFilter.value = '';
                    }
                    if (customerFilterText) {
                        customerFilterText.textContent = 'Alle Kunden';
                    }
                    // Filter neu anwenden
                    filterDevices();
                }
            } else {
                // Wenn kein TableBody vorhanden, Button ausblenden
                customerContainer.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kunden:', error);
            // Bei Fehler Button ausblenden
            customerContainer.style.display = 'none';
        });
}

function loadDevices() {
    let url = devicesApiUrl;
    const params = new URLSearchParams();
    
    // Firmenfilter hat Priorität (aus Nav)
    if (selectedCompanyId) {
        params.append('company_id', selectedCompanyId);
    }
    
    if (params.toString()) {
        url += '?' + params.toString();
    }
    
    fetch(url)
        .then(response => parseJsonResponse(response, 'devices/api/devices.php'))
        .then(data => {
            if (data.success) {
                allDevices = data.devices;
                updateManufacturerFilter();
                try {
                    const raw = localStorage.getItem(DEVICES_FILTER_STORAGE_KEY);
                    if (raw) {
                        const state = JSON.parse(raw);
                        const mEl = document.getElementById('manufacturer-filter');
                        const modelEl = document.getElementById('model-filter');
                        if (state.manufacturer && mEl) { mEl.value = state.manufacturer; updateModelFilter(); }
                        if (state.model && modelEl) modelEl.value = state.model;
                    }
                } catch (e) {}
                filterDevices();
            } else {
                console.error('Fehler beim Laden der Geräte:', data.error);
                showError('Fehler beim Laden der Geräte');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            showError('Fehler beim Laden der Geräte');
        });
}

function updateManufacturerFilter() {
    const typFilter = document.getElementById('typ-filter');
    const manufacturerFilter = document.getElementById('manufacturer-filter');
    const manufacturerContainer = document.getElementById('manufacturer-filter-container');
    const selectedTyp = typFilter.value;
    
    // Hersteller basierend auf Typ-Filter sammeln
    let filteredDevices = allDevices;
    if (selectedTyp) {
        filteredDevices = allDevices.filter(device => device.typ === selectedTyp);
    }
    
    // Alle eindeutigen Hersteller aus den gefilterten Geräten sammeln
    const manufacturers = [...new Set(filteredDevices
        .map(device => device.hersteller)
        .filter(hersteller => hersteller && hersteller.trim() !== ''))].sort();
    
    if (manufacturers.length > 0) {
        manufacturerContainer.style.display = 'block';
        manufacturerFilter.innerHTML = '<option value="">Alle Hersteller</option>';
        manufacturers.forEach(manufacturer => {
            const option = document.createElement('option');
            option.value = manufacturer;
            option.textContent = manufacturer;
            manufacturerFilter.appendChild(option);
        });
        // Modell-Filter zurücksetzen, wenn Hersteller-Filter geändert wird
        updateModelFilter();
    } else {
        manufacturerContainer.style.display = 'none';
        manufacturerFilter.value = '';
        // Modell-Filter auch ausblenden
        const modelContainer = document.getElementById('model-filter-container');
        if (modelContainer) {
            modelContainer.style.display = 'none';
            document.getElementById('model-filter').value = '';
        }
    }
}

function updateModelFilter() {
    const manufacturerFilter = document.getElementById('manufacturer-filter');
    const modelFilter = document.getElementById('model-filter');
    const modelContainer = document.getElementById('model-filter-container');
    const selectedManufacturer = manufacturerFilter.value;
    
    if (selectedManufacturer) {
        // Alle Modelle für den ausgewählten Hersteller sammeln
        const models = [...new Set(allDevices
            .filter(device => device.hersteller === selectedManufacturer)
            .map(device => device.modell)
            .filter(modell => modell && modell.trim() !== ''))].sort();
        
        if (models.length > 0) {
            modelContainer.style.display = 'block';
            modelFilter.innerHTML = '<option value="">Alle Modelle</option>';
            models.forEach(model => {
                const option = document.createElement('option');
                option.value = model;
                option.textContent = model;
                modelFilter.appendChild(option);
            });
        } else {
            modelContainer.style.display = 'none';
        }
    } else {
        modelContainer.style.display = 'none';
        modelFilter.value = '';
    }
}

function normalizeDeviceSearchWs(s) {
    return String(s).replace(/\s+/g, ' ').trim();
}

function deviceSearchMatches(device, needleRaw) {
    const needle = normalizeDeviceSearchWs(String(needleRaw).toLowerCase());
    if (!needle) return true;
    const userFullName = device.user_vorname && device.user_nachname
        ? `${device.user_vorname} ${device.user_nachname}`
        : (device.user_vorname || device.user_nachname || '');
    const parts = [
        device.name,
        device.typ,
        device.hersteller,
        device.modell,
        device.seriennummer,
        device.mac_adresse,
        device.ip_adresse,
        device.betriebssystem,
        device.beschreibung,
        device.company_name,
        device.customer_name,
        device.customer_email,
        userFullName,
        device.user_email
    ].filter(Boolean);
    const hay = normalizeDeviceSearchWs(parts.join(' ').toLowerCase());
    if (hay.includes(needle)) return true;
    const hNo = hay.replace(/\s+/g, '');
    const nNo = needle.replace(/\s+/g, '');
    if (nNo.length >= 2 && hNo.includes(nNo)) return true;
    const hAlnum = hNo.replace(/[^a-z0-9]/g, '');
    const nAlnum = nNo.replace(/[^a-z0-9]/g, '');
    if (nAlnum.length >= 3 && hAlnum.includes(nAlnum)) return true;
    return false;
}

function filterDevices() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const typFilter = document.getElementById('typ-filter').value;
    const manufacturerFilter = document.getElementById('manufacturer-filter').value;
    const modelFilter = document.getElementById('model-filter').value;
    const customerFilter = document.getElementById('customer-filter');
    const customerFilterValue = customerFilter ? customerFilter.value : '';
    const statusRadio = document.querySelector('input[name="status"]:checked');
    const statusFilter = statusRadio ? statusRadio.value : '';
    
    filteredDevices = allDevices.filter(device => {
        // Suchfilter
        if (searchTerm) {
            if (!deviceSearchMatches(device, searchTerm)) {
                return false;
            }
        }
        
        // Typ-Filter
        if (typFilter && device.typ !== typFilter) {
            return false;
        }
        
        // Hersteller-Filter
        if (manufacturerFilter && device.hersteller !== manufacturerFilter) {
            return false;
        }
        
        // Modell-Filter
        if (modelFilter && device.modell !== modelFilter) {
            return false;
        }
        
        // Kunde-Filter
        if (customerFilterValue) {
            const deviceCustomerId = device.customer_id ? device.customer_id.toString() : '';
            if (deviceCustomerId !== customerFilterValue) {
                return false;
            }
        } else {
            // Wenn "Alle Kunden" ausgewählt ist, alle Geräte anzeigen
        }
        
        // Status-Filter (aktiv, inaktiv, wartung, ausgemustert)
        if (statusFilter) {
            if (device.status !== statusFilter) {
                return false;
            }
        }
        
        return true;
    });
    
    // Sortierung anwenden, falls gesetzt
    if (sortColumn) {
        sortDevices(sortColumn, false); // false = keine UI-Aktualisierung
    } else if (userRole === 'Firmen-Admin') {
        // Für Firmen-Admins standardmäßig alphabetisch nach Gerätename anzeigen
        filteredDevices.sort((a, b) => (a.name || '').localeCompare((b.name || ''), 'de', { sensitivity: 'base' }));
    }
    
    displayDevices(filteredDevices);
}

function sortDevices(column, updateUI = true) {
    // Sortierrichtung umschalten, wenn bereits nach dieser Spalte sortiert wird
    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'asc';
    }
    
    filteredDevices.sort((a, b) => {
        let aValue, bValue;
        
        // Werte für Sortierung extrahieren
        switch(column) {
            case 'name':
                aValue = (a.name || '').toLowerCase();
                bValue = (b.name || '').toLowerCase();
                break;
            case 'typ':
                aValue = (a.typ || '').toLowerCase();
                bValue = (b.typ || '').toLowerCase();
                break;
            case 'seriennummer':
                aValue = (a.seriennummer || '').toLowerCase();
                bValue = (b.seriennummer || '').toLowerCase();
                break;
            case 'status':
                aValue = (a.status || '').toLowerCase();
                bValue = (b.status || '').toLowerCase();
                break;
            case 'betriebssystem':
                aValue = (a.betriebssystem || '').toLowerCase();
                bValue = (b.betriebssystem || '').toLowerCase();
                break;
            case 'ip_adresse':
                aValue = (a.ip_adresse || '').toLowerCase();
                bValue = (b.ip_adresse || '').toLowerCase();
                break;
            case 'beschreibung':
                aValue = (a.beschreibung || '').toLowerCase();
                bValue = (b.beschreibung || '').toLowerCase();
                break;
            case 'geaendert_datum':
                aValue = new Date(a.geaendert_datum || a.erstellt_datum || 0);
                bValue = new Date(b.geaendert_datum || b.erstellt_datum || 0);
                break;
            case 'company_name':
                aValue = (a.company_name || '').toLowerCase();
                bValue = (b.company_name || '').toLowerCase();
                break;
            case 'customer_name':
                aValue = (a.customer_name || '').toLowerCase();
                bValue = (b.customer_name || '').toLowerCase();
                break;
            case 'user_name':
                const aUser = a.user_vorname && a.user_nachname 
                    ? `${a.user_vorname} ${a.user_nachname}`.toLowerCase()
                    : (a.user_vorname || a.user_nachname || '').toLowerCase();
                const bUser = b.user_vorname && b.user_nachname 
                    ? `${b.user_vorname} ${b.user_nachname}`.toLowerCase()
                    : (b.user_vorname || b.user_nachname || '').toLowerCase();
                aValue = aUser;
                bValue = bUser;
                break;
            default:
                return 0;
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
        displayDevices(filteredDevices);
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
}

function showError(message) {
    const tbody = document.getElementById('computerList');
    const cardsContainer = document.getElementById('deviceCards');
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
    const showCustomer = <?php echo ($userRole !== 'Kunde') ? 'true' : 'false'; ?>;
    const showUser = <?php echo ($userRole !== 'Kunde') ? 'true' : 'false'; ?>;
    const colspan = showCompany ? (showCustomer && showUser ? 10 : 8) : (showCustomer && showUser ? 9 : 7); // Name, Status, Seriennummer, OS, Netzwerk, Standort, Kunde (optional), Benutzer (optional), Company (optional), Aktionen
    
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-4 py-4 text-center text-red-500">${message}</td></tr>`;
    }
    if (cardsContainer) {
        cardsContainer.innerHTML = `<div class="col-span-full text-center text-red-500 py-8">${message}</div>`;
    }
}

function canEditDevice(device) {
    // Admin und Techniker können alle Geräte bearbeiten
    if (userRole === 'Admin' || userRole === 'Techniker') {
        return true;
    }
    // Firmen-Admin kann alle Geräte der eigenen Firma bearbeiten
    if (userRole === 'Firmen-Admin') {
        return true;
    }
    // Firmen-User kann nur eigene Geräte bearbeiten
    if (userRole === 'Firmen-User') {
        return device.user_id && parseInt(device.user_id) === userId;
    }
    // Kunde kann Geräte seines Kunden bearbeiten
    if (userRole === 'Kunde') {
        return true;
    }
    return false;
}

function displayDevices(devices) {
    if (currentView === 'table') {
        displayTableView(devices);
    } else {
        displayCardsView(devices);
    }
}

function displayTableView(devices) {
    const tbody = document.getElementById('computerList');
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
    const showCustomer = <?php echo ($userRole !== 'Kunde') ? 'true' : 'false'; ?>;
    const showUser = <?php echo ($userRole !== 'Kunde') ? 'true' : 'false'; ?>;
    const colspan = showCompany ? (showCustomer && showUser ? 10 : 8) : (showCustomer && showUser ? 9 : 7); // Name, Status, Seriennummer, OS, Netzwerk, Standort, Kunde (optional), Benutzer (optional), Company (optional), Aktionen
    
    if (devices.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Keine Geräte gefunden</td></tr>`;
        return;
    }
    
    const typLabels = {
        'drucker': 'Drucker',
        'computer': 'Computer',
        'netzwerk': 'Netzwerk',
        'smartphone': 'Smartphone',
        'monitor': 'Monitor',
        'divers': 'Divers'
    };
    
    tbody.innerHTML = devices.map(device => {
        const statusBadge = getStatusBadge(device.status || 'aktiv');
        const companyCell = showCompany ? `<td class="px-4 py-3">${escapeHtml(device.company_name || '-')}</td>` : '';
        const typLabel = typLabels[device.typ] || device.typ || '-';
        
        // Benutzer-Name zusammenstellen
        let userName = '-';
        if (device.user_vorname && device.user_nachname) {
            userName = `${device.user_vorname} ${device.user_nachname}`;
        } else if (device.user_vorname) {
            userName = device.user_vorname;
        } else if (device.user_nachname) {
            userName = device.user_nachname;
        }
        
        const typeIcon = getTypeIcon(device.typ);
        const manufacturerModel = [device.hersteller, device.modell].filter(Boolean).join(' / ') || '-';
        
        return `
            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" onclick="window.location.href='<?php echo BASE_URL; ?>devices/detail.php?id=${device.id}'">
                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">
                    <div class="flex items-center">
                        <div class="mr-3 h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                            ${typeIcon}
                        </div>
                        <div class="flex flex-col">
                            <span class="text-primary-600 dark:text-primary-400 font-medium">${escapeHtml(device.name)}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(manufacturerModel)}</span>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    ${statusBadge}
                </td>
                <td class="px-4 py-3">
                    ${escapeHtml(device.seriennummer || '-')}
                </td>
                <td class="px-4 py-3">
                    ${escapeHtml(device.betriebssystem || '-')}
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-col text-xs">
                        ${device.ip_adresse ? `<span class="text-gray-900 dark:text-white font-mono">${escapeHtml(device.ip_adresse)}</span>` : ''}
                        ${device.mac_adresse ? `<span class="text-gray-500 dark:text-gray-400 font-mono">${escapeHtml(device.mac_adresse)}</span>` : ''}
                        ${!device.ip_adresse && !device.mac_adresse ? '<span class="text-gray-500 dark:text-gray-400">-</span>' : ''}
                    </div>
                </td>
                <td class="px-4 py-3 max-w-[180px]">
                    <span class="truncate block" title="${escapeHtml(device.beschreibung || '')}">${escapeHtml(device.beschreibung || '-')}</span>
                </td>
                ${showCustomer ? `<td class="px-4 py-3">
                    ${escapeHtml(device.customer_name || '-')}
                </td>` : ''}
                ${showUser ? `<td class="px-4 py-3">
                    ${escapeHtml(userName)}
                </td>` : ''}
                ${companyCell}
                <td class="px-4 py-3" onclick="event.stopPropagation()">
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo BASE_URL; ?>devices/detail.php?id=${device.id}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="Details anzeigen">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        ${canEditDevice(device) ? `
                        <a href="<?php echo BASE_URL; ?>devices/edit.php?id=${device.id}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400" title="Bearbeiten">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                        <button onclick="deleteDevice(${device.id})" class="text-red-600 hover:text-red-900 dark:text-red-400" title="${userRole === 'Admin' ? 'Löschen' : 'Auf inaktiv setzen'}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function displayCardsView(devices) {
    const cardsContainer = document.getElementById('deviceCards');
    
    if (devices.length === 0) {
        cardsContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">Keine Geräte gefunden</div>';
        return;
    }
    
    const typLabels = {
        'drucker': 'Drucker',
        'computer': 'Computer',
        'netzwerk': 'Netzwerk',
        'smartphone': 'Smartphone',
        'monitor': 'Monitor',
        'divers': 'Divers'
    };
    
    cardsContainer.innerHTML = devices.map(device => {
        const statusBadge = getStatusBadge(device.status || 'aktiv');
        const typeIcon = getTypeIcon(device.typ);
        const typLabel = typLabels[device.typ] || device.typ || '-';
        const manufacturerModel = [device.hersteller, device.modell].filter(Boolean).join(' / ') || '-';
        
        // Benutzer-Name zusammenstellen
        let userName = '-';
        if (device.user_vorname && device.user_nachname) {
            userName = `${device.user_vorname} ${device.user_nachname}`;
        } else if (device.user_vorname) {
            userName = device.user_vorname;
        } else if (device.user_nachname) {
            userName = device.user_nachname;
        }
        
        const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
        const showCustomer = <?php echo ($userRole !== 'Kunde') ? 'true' : 'false'; ?>;
        const showUser = <?php echo ($userRole !== 'Kunde') ? 'true' : 'false'; ?>;
        
        return `
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='<?php echo BASE_URL; ?>devices/detail.php?id=${device.id}'">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center flex-1">
                            <div class="mr-3 h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center flex-shrink-0">
                                ${typeIcon}
                            </div>
                            <div class="flex flex-col min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                                    ${escapeHtml(device.name)}
                                </h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400 truncate">${escapeHtml(manufacturerModel)}</span>
                            </div>
                        </div>
                        ${statusBadge}
                    </div>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Seriennummer</label>
                            <div class="text-gray-900 dark:text-white font-mono text-xs">${escapeHtml(device.seriennummer || '-')}</div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Betriebssystem</label>
                            <div class="text-gray-900 dark:text-white">${escapeHtml(device.betriebssystem || '-')}</div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Netzwerk</label>
                            <div class="text-xs">
                                ${device.ip_adresse ? `<div class="text-gray-900 dark:text-white font-mono">${escapeHtml(device.ip_adresse)}</div>` : ''}
                                ${device.mac_adresse ? `<div class="text-gray-500 dark:text-gray-400 font-mono">${escapeHtml(device.mac_adresse)}</div>` : ''}
                                ${!device.ip_adresse && !device.mac_adresse ? '<div class="text-gray-500 dark:text-gray-400">-</div>' : ''}
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Standort</label>
                            <div class="text-gray-900 dark:text-white text-xs truncate" title="${escapeHtml(device.beschreibung || '')}">${escapeHtml(device.beschreibung || '-')}</div>
                        </div>
                        ${showCustomer ? `<div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Kunde</label>
                            <div class="text-gray-900 dark:text-white">${escapeHtml(device.customer_name || '-')}</div>
                        </div>` : ''}
                        ${showUser ? `<div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Benutzer</label>
                            <div class="text-gray-900 dark:text-white">${escapeHtml(userName)}</div>
                        </div>` : ''}
                        ${showCompany ? `
                        <div class="col-span-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Firma</label>
                            <div class="text-gray-900 dark:text-white">${escapeHtml(device.company_name || '-')}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 flex items-center justify-end space-x-2" onclick="event.stopPropagation()">
                    <a href="<?php echo BASE_URL; ?>devices/detail.php?id=${device.id}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="Details anzeigen">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </a>
                    ${canEditDevice(device) ? `
                    <a href="<?php echo BASE_URL; ?>devices/edit.php?id=${device.id}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400" title="Bearbeiten">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                    <button onclick="deleteDevice(${device.id})" class="text-red-600 hover:text-red-900 dark:text-red-400" title="${userRole === 'Admin' ? 'Löschen' : 'Auf inaktiv setzen'}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function getStatusBadge(status) {
    const badges = {
        'aktiv': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aktiv</span>',
        'inaktiv': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inaktiv</span>',
        'wartung': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Wartung</span>',
        'ausgemustert': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Ausgemustert</span>'
    };
    return badges[status] || badges['aktiv'];
}

function deleteDevice(deviceId) {
    const confirmMessage = userRole === 'Admin' 
        ? 'Möchten Sie dieses Gerät wirklich löschen?' 
        : 'Möchten Sie dieses Gerät wirklich auf inaktiv setzen?';
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    fetch(devicesApiUrl + '?id=' + deviceId, {
        method: 'DELETE'
    })
    .then(response => parseJsonResponse(response, 'devices/api/devices.php DELETE'))
    .then(data => {
        if (data.success) {
            loadDevices();
            const message = userRole === 'Admin' 
                ? 'Gerät erfolgreich gelöscht' 
                : 'Gerät erfolgreich auf inaktiv gesetzt';
            if (typeof showToast === 'function') {
                showToast(message, 'success');
            }
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
        const errorMessage = userRole === 'Admin' 
            ? 'Fehler beim Löschen des Geräts' 
            : 'Fehler beim Setzen des Geräts auf inaktiv';
        if (typeof showToast === 'function') {
            showToast(errorMessage, 'error');
        } else {
            alert(errorMessage);
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
