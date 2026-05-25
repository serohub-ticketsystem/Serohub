<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
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

// Nur Admin und Techniker können Anrufe verwalten
if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
      <main>
        <div class="px-4">
<div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
  <div class="col-span-full mx-4 mt-4 items-center justify-between sm:flex">
    <div class="mb-4 sm:mb-0">
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
          <li aria-current="page">
            <div class="flex items-center">
              <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
              </svg>
              <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Anrufe</span>
            </div>
          </li>
        </ol>
      </nav>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Anrufprotokoll</h1>
    </div>
  </div>
  <div class="relative col-span-full">
    <div class="px-4">
    <div class="relative">
      <div class="flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0">
        <div class="flex flex-col w-full space-y-3 lg:w-2/3 md:space-y-0 md:flex-row md:items-center">
          <form class="flex-1 w-full md:max-w-sm md:mr-4">
            <label for="default-search"
                   class="text-sm font-medium text-gray-900 sr-only dark:text-white">Suche</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg aria-hidden="true" class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none"
                     stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
              </div>
              <input type="search" id="search"
       class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                     placeholder="Suchen..."> 
            </div>
          </form>
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Anruftyp:</label>
            <label class="inline-flex items-center">
              <input type="radio" name="anruftyp" value="" class="mr-1" checked>
              <span class="text-sm text-gray-700 dark:text-gray-300">Alle</span>
            </label>
            <label class="inline-flex items-center ml-2">
              <input type="radio" name="anruftyp" value="ausgehend" class="mr-1">
              <span class="text-sm text-gray-700 dark:text-gray-300">Ausgehend</span>
            </label>
            <label class="inline-flex items-center ml-2">
              <input type="radio" name="anruftyp" value="eingehend" class="mr-1">
              <span class="text-sm text-gray-700 dark:text-gray-300">Eingehend</span>
            </label>
            <label class="inline-flex items-center ml-2">
              <input type="radio" name="anruftyp" value="verpasst" class="mr-1">
              <span class="text-sm text-gray-700 dark:text-gray-300">Verpasst</span>
            </label>
          </div>
        </div>
        <div class="flex flex-col items-stretch justify-end flex-shrink-0 w-full pb-4 md:pb-0 md:w-auto md:flex-row md:items-center md:space-x-3">
          <button type="button" id="sipSettingsBtn"
                  class="flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
            <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            SIP-Einstellungen
          </button>
          <button type="button" id="newCallBtn"
                  class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
            <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewbox="0 0 20 20"
                 xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Neuer Anruf
          </button>
        </div>
      </div>
      
      <!-- Tabelle -->
      <div id="tableView" class="overflow-x-auto shadow-md sm:rounded-lg">
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" data-sort="erstellt_datum">
                <div class="flex items-center">
                  Datum/Zeit
                  <svg class="w-4 h-4 ml-1 sort-icon" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" data-sort="telefonnummer">
                <div class="flex items-center">
                  Telefonnummer
                  <svg class="w-4 h-4 ml-1 sort-icon" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" data-sort="empfaenger_name">
                <div class="flex items-center">
                  Empfänger
                  <svg class="w-4 h-4 ml-1 sort-icon" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" data-sort="anruftyp">
                <div class="flex items-center">
                  Typ
                  <svg class="w-4 h-4 ml-1 sort-icon" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" data-sort="status">
                <div class="flex items-center">
                  Status
                  <svg class="w-4 h-4 ml-1 sort-icon" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" data-sort="dauer_sekunden">
                <div class="flex items-center">
                  Dauer
                  <svg class="w-4 h-4 ml-1 sort-icon" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" data-sort="ersteller_vorname">
                <div class="flex items-center">
                  Anrufer
                  <svg class="w-4 h-4 ml-1 sort-icon" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </div>
              </th>
              <th scope="col" class="px-6 py-3">
                Aktionen
              </th>
            </tr>
          </thead>
          <tbody id="callsList">
            <tr>
              <td colspan="8" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">
                <i class="fas fa-spinner fa-spin mr-2"></i> Lade Anrufe...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      </div>
    </div>
    </div>
  </div>
</div>
</div>

<!-- SIP Anruf-Panel -->
<div id="sipCallPanel" class="fixed bottom-4 right-4 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 hidden">
  <div class="p-4 border-b border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">SIP Telefon</h3>
      <button id="closeSipPanel" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
    <div id="sipStatus" class="mt-2 text-sm">
      <span id="sipConnectionStatus" class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Nicht verbunden</span>
    </div>
  </div>
  <div class="p-4">
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefonnummer</label>
      <input type="tel" id="sipPhoneNumber" placeholder="z.B. 0123456789" 
             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    </div>
    <div class="flex gap-2 mb-4">
      <button id="sipCallBtn" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
        </svg>
        Anrufen
      </button>
      <button id="sipHangupBtn" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 hidden">
        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M16 8v8a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2h-5l-2-2zM5 8a2 2 0 00-2 2v1a2 2 0 002 2h5l2 2H5a2 2 0 01-2-2v-3a2 2 0 012-2h5l2-2H5z" />
        </svg>
        Auflegen
      </button>
    </div>
    <div id="sipCallInfo" class="text-sm text-gray-600 dark:text-gray-400 hidden">
      <div>Anruf läuft...</div>
      <div id="sipCallDuration" class="font-mono text-lg font-bold text-gray-900 dark:text-white mt-2">00:00</div>
    </div>
  </div>
</div>

<!-- Modal für SIP-Einstellungen -->
<div id="sipSettingsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
  <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
    <div class="mt-3">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">SIP-Einstellungen</h3>
      <form id="sipSettingsForm">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SIP-Server (WebSocket URL) *</label>
          <input type="text" id="sip_server" name="server" required placeholder="wss://sip.example.com:8089/ws"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">z.B. wss://sip.example.com:8089/ws</p>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Benutzername *</label>
          <input type="text" id="sip_username" name="username" required
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Passwort *</label>
          <input type="password" id="sip_password" name="password" required
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anzeigename (optional)</label>
          <input type="text" id="sip_display_name" name="display_name" placeholder="Ihr Name"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" id="cancelSipSettingsBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500">
            Abbrechen
          </button>
          <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-900 rounded-lg hover:bg-primary-950">
            Speichern
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal für neuen Anruf -->
<div id="newCallModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
  <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
    <div class="mt-3">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Neuer Anruf</h3>
      <form id="newCallForm">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefonnummer *</label>
          <input type="tel" id="modal_telefonnummer" name="telefonnummer" required
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Empfänger Name</label>
          <input type="text" id="modal_empfaenger_name" name="empfaenger_name"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anruftyp</label>
          <select id="modal_anruftyp" name="anruftyp" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            <option value="ausgehend">Ausgehend</option>
            <option value="eingehend">Eingehend</option>
            <option value="verpasst">Verpasst</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
          <select id="modal_status" name="status"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            <option value="">-</option>
            <option value="verbunden">Verbunden</option>
            <option value="nicht_erreicht">Nicht erreicht</option>
            <option value="besetzt">Besetzt</option>
            <option value="abgelehnt">Abgelehnt</option>
            <option value="keine_antwort">Keine Antwort</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dauer (Sekunden)</label>
          <input type="number" id="modal_dauer_sekunden" name="dauer_sekunden" min="0"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notizen</label>
          <textarea id="modal_notizen" name="notizen" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" id="cancelCallBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500">
            Abbrechen
          </button>
          <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-900 rounded-lg hover:bg-primary-950">
            Speichern
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- JsSIP Bibliothek -->
<script src="https://cdn.jsdelivr.net/npm/jssip@3.10.1/dist/jssip.min.js"></script>

<script>
const callsApiUrl = '<?php echo BASE_URL; ?>calls/api/calls.php';
const sipSettingsApiUrl = '<?php echo BASE_URL; ?>calls/api/sip_settings.php';
const userRole = '<?php echo addslashes($userRole); ?>';
let allCalls = [];
let filteredCalls = [];
let sortColumn = null;
let sortDirection = 'asc';

// SIP Client Variablen
let sipUA = null;
let currentCall = null;
let callStartTime = null;
let callDurationInterval = null;
let sipSettings = null;

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

function formatDuration(seconds) {
    if (!seconds) return '-';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    return `${minutes}:${String(secs).padStart(2, '0')}`;
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Event Listeners
    document.getElementById('search').addEventListener('input', filterCalls);
    document.querySelectorAll('input[name="anruftyp"]').forEach(radio => {
        radio.addEventListener('change', filterCalls);
    });
    
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            sortCalls(column);
        });
    });
    
    // Modal Event Listeners
    document.getElementById('newCallBtn').addEventListener('click', function() {
        document.getElementById('newCallModal').classList.remove('hidden');
        document.getElementById('newCallForm').reset();
    });
    
    document.getElementById('cancelCallBtn').addEventListener('click', function() {
        document.getElementById('newCallModal').classList.add('hidden');
    });
    
    document.getElementById('newCallForm').addEventListener('submit', function(e) {
        e.preventDefault();
        createCall();
    });
    
    // SIP Event Listeners
    document.getElementById('sipSettingsBtn').addEventListener('click', function() {
        loadSipSettings();
        document.getElementById('sipSettingsModal').classList.remove('hidden');
    });
    
    document.getElementById('cancelSipSettingsBtn').addEventListener('click', function() {
        document.getElementById('sipSettingsModal').classList.add('hidden');
    });
    
    document.getElementById('sipSettingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveSipSettings();
    });
    
    document.getElementById('closeSipPanel').addEventListener('click', function() {
        document.getElementById('sipCallPanel').classList.add('hidden');
    });
    
    document.getElementById('sipCallBtn').addEventListener('click', function() {
        const phoneNumber = document.getElementById('sipPhoneNumber').value.trim();
        if (phoneNumber) {
            makeSipCall(phoneNumber);
        }
    });
    
    document.getElementById('sipHangupBtn').addEventListener('click', function() {
        hangupSipCall();
    });
    
    // SIP-Panel anzeigen wenn Einstellungen vorhanden
    loadSipSettings().then(() => {
        if (sipSettings && sipSettings.server && sipSettings.username) {
            document.getElementById('sipCallPanel').classList.remove('hidden');
            // Passwort muss neu eingegeben werden, daher nicht automatisch verbinden
            // connectSip();
        }
    });
    
    loadCalls();
});

function loadCalls() {
    fetch(callsApiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allCalls = data.calls;
                filterCalls();
            } else {
                console.error('Fehler:', data.error);
                showError('Fehler beim Laden der Anrufe');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            showError('Fehler beim Laden der Anrufe');
        });
}

function filterCalls() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const anruftypRadio = document.querySelector('input[name="anruftyp"]:checked');
    const anruftypFilter = anruftypRadio ? anruftypRadio.value : '';
    
    filteredCalls = allCalls.filter(call => {
        if (searchTerm) {
            const searchableText = [
                call.telefonnummer,
                call.empfaenger_name || '',
                call.company_name || '',
                call.customer_name || '',
                call.ticket_nummer || '',
                call.ersteller_vorname || '',
                call.ersteller_nachname || ''
            ].join(' ').toLowerCase();
            
            if (!searchableText.includes(searchTerm)) {
                return false;
            }
        }
        
        if (anruftypFilter && call.anruftyp !== anruftypFilter) {
            return false;
        }
        
        return true;
    });
    
    if (sortColumn) {
        sortCalls(sortColumn, false);
    } else {
        displayCalls(filteredCalls);
    }
}

function sortCalls(column, updateUI = true) {
    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'asc';
    }
    
    filteredCalls.sort((a, b) => {
        let aValue, bValue;
        
        switch (column) {
            case 'erstellt_datum':
                aValue = new Date(a.erstellt_datum || 0).getTime();
                bValue = new Date(b.erstellt_datum || 0).getTime();
                break;
            case 'telefonnummer':
                aValue = (a.telefonnummer || '').toLowerCase();
                bValue = (b.telefonnummer || '').toLowerCase();
                break;
            case 'empfaenger_name':
                aValue = (a.empfaenger_name || '').toLowerCase();
                bValue = (b.empfaenger_name || '').toLowerCase();
                break;
            case 'anruftyp':
                aValue = (a.anruftyp || '').toLowerCase();
                bValue = (b.anruftyp || '').toLowerCase();
                break;
            case 'status':
                aValue = (a.status || '').toLowerCase();
                bValue = (b.status || '').toLowerCase();
                break;
            case 'dauer_sekunden':
                aValue = parseInt(a.dauer_sekunden || 0);
                bValue = parseInt(b.dauer_sekunden || 0);
                break;
            case 'ersteller_vorname':
                aValue = ((a.ersteller_vorname || '') + ' ' + (a.ersteller_nachname || '')).toLowerCase();
                bValue = ((b.ersteller_vorname || '') + ' ' + (b.ersteller_nachname || '')).toLowerCase();
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
        displayCalls(filteredCalls);
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
}

function showError(message) {
    const tbody = document.getElementById('callsList');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-4 text-center text-red-500">${message}</td></tr>`;
    }
}

function displayCalls(calls) {
    const tbody = document.getElementById('callsList');
    
    if (calls.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Keine Anrufe gefunden</td></tr>';
        return;
    }
    
    tbody.innerHTML = calls.map(call => {
        const anruftypBadge = getAnruftypBadge(call.anruftyp);
        const statusBadge = getStatusBadge(call.status);
        const erstellerName = `${call.ersteller_vorname || ''} ${call.ersteller_nachname || ''}`.trim() || '-';
        
        return `
            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    ${formatDateTime(call.erstellt_datum)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    <div class="flex items-center gap-2">
                        <a href="tel:${escapeHtml(call.telefonnummer)}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            ${escapeHtml(call.telefonnummer)}
                        </a>
                        <button onclick="callSipNumber('${escapeHtml(call.telefonnummer)}')" class="text-green-600 hover:text-green-900 dark:text-green-400" title="SIP anrufen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </button>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    ${escapeHtml(call.empfaenger_name || call.customer_name || call.company_name || '-')}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    ${anruftypBadge}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    ${statusBadge}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    ${formatDuration(call.dauer_sekunden)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    ${escapeHtml(erstellerName)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm" onclick="event.stopPropagation()">
                    <div class="flex items-center gap-2">
                        <button onclick="editCall(${call.id})" class="text-primary-600 hover:text-primary-900 dark:text-primary-400" title="Bearbeiten">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        ${userRole === 'Admin' || userRole === 'Techniker' ? `
                            <button onclick="deleteCall(${call.id})" class="text-red-600 hover:text-red-900 dark:text-red-400" title="Löschen">
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

function getAnruftypBadge(anruftyp) {
    const badges = {
        'ausgehend': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Ausgehend</span>',
        'eingehend': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Eingehend</span>',
        'verpasst': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Verpasst</span>'
    };
    return badges[anruftyp] || badges['ausgehend'];
}

function getStatusBadge(status) {
    if (!status) return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">-</span>';
    
    const badges = {
        'verbunden': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Verbunden</span>',
        'nicht_erreicht': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Nicht erreicht</span>',
        'besetzt': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">Besetzt</span>',
        'abgelehnt': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Abgelehnt</span>',
        'keine_antwort': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Keine Antwort</span>'
    };
    return badges[status] || '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">-</span>';
}

function createCall() {
    const data = {
        telefonnummer: document.getElementById('modal_telefonnummer').value.trim(),
        empfaenger_name: document.getElementById('modal_empfaenger_name').value.trim() || null,
        anruftyp: document.getElementById('modal_anruftyp').value,
        status: document.getElementById('modal_status').value || null,
        dauer_sekunden: document.getElementById('modal_dauer_sekunden').value ? parseInt(document.getElementById('modal_dauer_sekunden').value) : null,
        notizen: document.getElementById('modal_notizen').value.trim() || null
    };
    
    if (!data.telefonnummer) {
        alert('Telefonnummer ist erforderlich');
        return;
    }
    
    fetch(callsApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('newCallModal').classList.add('hidden');
            document.getElementById('newCallForm').reset();
            loadCalls();
            if (typeof showToast === 'function') {
                showToast('Anruf erfolgreich erstellt', 'success');
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
        if (typeof showToast === 'function') {
            showToast('Fehler beim Erstellen des Anrufs', 'error');
        } else {
            alert('Fehler beim Erstellen des Anrufs');
        }
    });
}

function editCall(callId) {
    // TODO: Implementiere Bearbeitungs-Modal
    alert('Bearbeitung wird noch implementiert');
}

function deleteCall(callId) {
    if (!confirm('Möchten Sie diesen Anruf wirklich löschen?')) {
        return;
    }
    
    fetch(callsApiUrl + '?id=' + callId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCalls();
            if (typeof showToast === 'function') {
                showToast('Anruf erfolgreich gelöscht', 'success');
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
        if (typeof showToast === 'function') {
            showToast('Fehler beim Löschen des Anrufs', 'error');
        } else {
            alert('Fehler beim Löschen des Anrufs');
        }
    });
}

// SIP-Funktionen
async function loadSipSettings() {
    try {
        const response = await fetch(sipSettingsApiUrl);
        const data = await response.json();
        if (data.success && data.settings) {
            sipSettings = data.settings;
            // Passwort-Feld mit *** füllen wenn vorhanden
            if (sipSettings.password === '***') {
                // Passwort wurde nicht zurückgegeben, Feld leer lassen
            } else {
                document.getElementById('sip_server').value = sipSettings.server || '';
                document.getElementById('sip_username').value = sipSettings.username || '';
                document.getElementById('sip_password').value = '';
                document.getElementById('sip_display_name').value = sipSettings.display_name || '';
            }
            return sipSettings;
        }
        return null;
    } catch (error) {
        console.error('Fehler beim Laden der SIP-Einstellungen:', error);
        return null;
    }
}

function saveSipSettings() {
    const settings = {
        server: document.getElementById('sip_server').value.trim(),
        username: document.getElementById('sip_username').value.trim(),
        password: document.getElementById('sip_password').value,
        display_name: document.getElementById('sip_display_name').value.trim() || null
    };
    
    if (!settings.server || !settings.username || !settings.password) {
        alert('Bitte füllen Sie alle Pflichtfelder aus');
        return;
    }
    
    fetch(sipSettingsApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(settings)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('sipSettingsModal').classList.add('hidden');
            sipSettings = data.settings;
            sipSettings.password = settings.password; // Passwort lokal speichern
            document.getElementById('sipCallPanel').classList.remove('hidden');
            connectSip();
            if (typeof showToast === 'function') {
                showToast('SIP-Einstellungen gespeichert. Verbindung wird hergestellt...', 'success');
            } else {
                alert('SIP-Einstellungen gespeichert. Verbindung wird hergestellt...');
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
        if (typeof showToast === 'function') {
            showToast('Fehler beim Speichern der SIP-Einstellungen', 'error');
        } else {
            alert('Fehler beim Speichern der SIP-Einstellungen');
        }
    });
}

function connectSip() {
    if (!sipSettings || !sipSettings.server || !sipSettings.username || !sipSettings.password) {
        updateSipStatus('Nicht konfiguriert', 'gray');
        document.getElementById('sipCallBtn').disabled = true;
        return;
    }
    
    updateSipStatus('Verbinde...', 'yellow');
    document.getElementById('sipCallBtn').disabled = true;
    
    // Alte Verbindung trennen falls vorhanden
    if (sipUA) {
        try {
            sipUA.stop();
        } catch (e) {
            /* bewusst still: UA war evtl. schon gestoppt */
        }
    }
    
    try {
        const socket = new JsSIP.WebSocketInterface(sipSettings.server);
        
        // Server-Domain extrahieren für URI
        const serverUrl = sipSettings.server.replace(/^wss?:\/\//, '').split('/')[0];
        const serverParts = serverUrl.split(':');
        const serverDomain = serverParts[0];
        
        const configuration = {
            sockets: [socket],
            uri: 'sip:' + sipSettings.username + '@' + serverDomain,
            password: sipSettings.password,
            display_name: sipSettings.display_name || sipSettings.username,
            register: true
        };
        
        sipUA = new JsSIP.UA(configuration);
    } catch (error) {
        console.error('Fehler beim Erstellen der SIP-Verbindung:', error);
        updateSipStatus('Verbindungsfehler', 'red');
        alert('Fehler beim Verbinden: ' + error.message);
        return;
    }
    
    sipUA.on('registered', function() {
        updateSipStatus('Verbunden', 'green');
        document.getElementById('sipCallBtn').disabled = false;
    });
    
    sipUA.on('registrationFailed', function(e) {
        updateSipStatus('Registrierung fehlgeschlagen', 'red');
        console.error('SIP Registrierung fehlgeschlagen:', e);
        alert('SIP-Registrierung fehlgeschlagen. Bitte überprüfen Sie Ihre Einstellungen.');
    });
    
    sipUA.on('unregistered', function() {
        updateSipStatus('Nicht verbunden', 'gray');
        document.getElementById('sipCallBtn').disabled = true;
    });
    
    sipUA.on('disconnected', function() {
        updateSipStatus('Getrennt', 'gray');
        document.getElementById('sipCallBtn').disabled = true;
    });
    
    sipUA.start();
}

function updateSipStatus(text, color) {
    const statusEl = document.getElementById('sipConnectionStatus');
    statusEl.textContent = text;
    statusEl.className = 'px-2 py-1 rounded text-xs';
    
    const colorClasses = {
        'green': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'yellow': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'red': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        'gray': 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
    };
    
    statusEl.className += ' ' + (colorClasses[color] || colorClasses['gray']);
}

function callSipNumber(phoneNumber) {
    // SIP-Panel anzeigen falls versteckt
    document.getElementById('sipCallPanel').classList.remove('hidden');
    document.getElementById('sipPhoneNumber').value = phoneNumber;
    
    // Kurz warten, dann anrufen
    setTimeout(() => {
        makeSipCall(phoneNumber);
    }, 100);
}

function makeSipCall(phoneNumber) {
    if (!sipUA || !sipUA.isRegistered()) {
        alert('SIP-Verbindung nicht aktiv. Bitte konfigurieren Sie zuerst Ihre SIP-Einstellungen.');
        document.getElementById('sipSettingsModal').classList.remove('hidden');
        return;
    }
    
    if (currentCall) {
        alert('Ein Anruf läuft bereits');
        return;
    }
    
    // Telefonnummer bereinigen (nur Ziffern)
    const cleanNumber = phoneNumber.replace(/\D/g, '');
    if (!cleanNumber) {
        alert('Ungültige Telefonnummer');
        return;
    }
    
    // SIP-Server-Domain extrahieren
    const serverUrl = sipSettings.server.replace(/^wss?:\/\//, '').split('/')[0];
    const serverParts = serverUrl.split(':');
    const serverDomain = serverParts[0];
    
    const options = {
        eventHandlers: {
            'progress': function(e) {
                updateSipStatus('Rufe an...', 'yellow');
            },
            'accepted': function(e) {
                updateSipStatus('Verbunden', 'green');
                callStartTime = new Date();
                startCallTimer();
                document.getElementById('sipCallBtn').classList.add('hidden');
                document.getElementById('sipHangupBtn').classList.remove('hidden');
                document.getElementById('sipCallInfo').classList.remove('hidden');
            },
            'ended': function(e) {
                endSipCall();
            },
            'failed': function(e) {
                console.error('Anruf fehlgeschlagen:', e);
                updateSipStatus('Anruf fehlgeschlagen', 'red');
                endSipCall();
                
                // Anruf in Datenbank protokollieren
                logSipCall(phoneNumber, 'nicht_erreicht', 0);
            }
        }
    };
    
    try {
        // SIP-URI aufbauen - Format: sip:nummer@domain
        const sipUri = 'sip:' + cleanNumber + '@' + serverDomain;
        currentCall = sipUA.call(sipUri, options);
        updateSipStatus('Rufe an...', 'yellow');
    } catch (error) {
        console.error('Fehler beim Starten des Anrufs:', error);
        alert('Fehler beim Starten des Anrufs: ' + error.message);
    }
}

function hangupSipCall() {
    if (currentCall) {
        currentCall.terminate();
        endSipCall();
    }
}

function endSipCall() {
    if (currentCall) {
        const duration = callStartTime ? Math.floor((new Date() - callStartTime) / 1000) : 0;
        const phoneNumber = currentCall.remote_identity ? currentCall.remote_identity.uri.toString().split('@')[0].replace('sip:', '') : '';
        
        currentCall = null;
        callStartTime = null;
        
        if (callDurationInterval) {
            clearInterval(callDurationInterval);
            callDurationInterval = null;
        }
        
        document.getElementById('sipCallBtn').classList.remove('hidden');
        document.getElementById('sipHangupBtn').classList.add('hidden');
        document.getElementById('sipCallInfo').classList.add('hidden');
        document.getElementById('sipCallDuration').textContent = '00:00';
        document.getElementById('sipPhoneNumber').value = '';
        
        updateSipStatus('Verbunden', 'green');
        
        // Anruf in Datenbank protokollieren
        if (phoneNumber && duration > 0) {
            logSipCall(phoneNumber, 'verbunden', duration);
        }
    }
}

function startCallTimer() {
    callDurationInterval = setInterval(function() {
        if (callStartTime) {
            const duration = Math.floor((new Date() - callStartTime) / 1000);
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            document.getElementById('sipCallDuration').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }
    }, 1000);
}

function logSipCall(phoneNumber, status, duration) {
    const data = {
        telefonnummer: phoneNumber,
        anruftyp: 'ausgehend',
        status: status,
        dauer_sekunden: duration
    };
    
    fetch(callsApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCalls(); // Liste aktualisieren
            if (typeof showToast === 'function') {
                showToast('Anruf erfolgreich protokolliert', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Protokollieren des Anrufs', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Protokollieren des Anrufs:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Protokollieren des Anrufs', 'error');
        }
    });
}
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
