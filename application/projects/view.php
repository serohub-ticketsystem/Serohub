<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$projectId) {
    header('Location: ' . BASE_URL . 'projects/');
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

if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
  <main class="pt-4 flex flex-col flex-1 min-h-0">
    <div class="flex flex-col flex-1 min-h-0">
      <div class="grid grid-cols-12 gap-4 flex-1 min-h-0 bg-gray-50 dark:bg-primary-50 px-4">
        <!-- Header-Zeile wie Service: Breadcrumb + Status + Mehr Optionen -->
        <div id="projectHeaderRow" class="hidden col-span-full items-start justify-between md:flex mx-0 mb-2">
          <nav class="flex-shrink-0" aria-label="Breadcrumb">
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
                  <a href="<?php echo htmlspecialchars(BASE_URL); ?>projects/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2">Projekte</a>
                </div>
              </li>
              <li aria-current="page">
                <div class="flex items-center">
                  <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                  </svg>
                  <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2 truncate max-w-[12rem] sm:max-w-xs" id="projectBreadcrumb">Projekt</span>
                </div>
              </li>
            </ol>
          </nav>
          <div class="flex items-center gap-3 self-center flex-shrink-0">
            <div id="projectStatusButtonGroup" class="hidden inline-flex rounded-lg shadow-sm gap-px" role="group">
              <button type="button" data-status="Neu" class="project-status-btn flex items-center px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-l-lg">Neu</button>
              <button type="button" data-status="In Planung" class="project-status-btn flex items-center px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500">In Planung</button>
              <button type="button" data-status="In Bearbeitung" class="project-status-btn flex items-center px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500">In Bearbeitung</button>
              <button type="button" data-status="Wartend" class="project-status-btn flex items-center px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500">Wartend</button>
              <button type="button" data-status="Abgeschlossen" class="project-status-btn flex items-center px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500">Abgeschlossen</button>
              <button type="button" data-status="Archiviert" class="project-status-btn flex items-center px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-primary-720 transition-colors bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:text-primary-210 dark:hover:bg-primary-760 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-r-lg">Archiviert</button>
            </div>
            <div class="relative" id="project-more-options-container">
              <button type="button" id="project-more-options-btn" class="inline-flex items-center justify-center gap-1.5 px-4 py-1.5 text-sm font-medium text-gray-900 dark:text-primary-200 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors" aria-expanded="false" aria-haspopup="true">
                Mehr Optionen
                <svg class="w-4 h-4 text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <div id="project-more-options-menu" class="hidden absolute right-0 z-50 mt-1 min-w-[11rem] bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg overflow-hidden" role="menu">
                <div class="py-1">
                  <button type="button" id="project-option-copy-number" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                    <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 10h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Projektnummer kopieren
                  </button>
                  <button type="button" id="project-option-edit" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors flex items-center gap-2" role="menuitem">
                    <svg class="w-4 h-4 text-gray-500 dark:text-primary-210 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Bearbeiten
                  </button>
                  <button type="button" id="project-option-abrechnen" class="hidden w-full text-left px-3 py-2 text-sm text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors flex items-center gap-2" role="menuitem">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Projekt abrechnen (Archiv)
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div id="projectContent" class="grid grid-cols-1 lg:grid-cols-10 col-span-full gap-4 min-h-0">
          <div id="projectLeftColumn" class="lg:col-span-7 order-1 flex flex-col gap-0 min-h-0 overflow-x-hidden">
            <div class="flex items-center justify-center py-16" id="projectLoadingSpinner">
              <svg class="animate-spin h-10 w-10 text-primary-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>
          </div>
          <div id="projectRightColumn" class="lg:col-span-3 order-2 lg:sticky lg:top-4 lg:self-start overflow-y-auto max-h-[calc(100vh-6rem)] rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 space-y-4 custom-scrollbar">
            <div class="text-center text-gray-500 dark:text-primary-210 text-sm py-8">Lade Projekt…</div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal: Notiz hinzufügen -->
<div id="addNoteModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" id="addNoteModalOverlay"></div>
    <div class="relative bg-white dark:bg-primary-100 rounded-lg shadow-xl max-w-lg w-full p-6 z-10">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-4">Notiz hinzufügen</h3>
      <form id="addNoteForm">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Titel *</label>
            <input type="text" id="noteTitel" required placeholder="Titel der Notiz" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-720 dark:bg-primary-300 dark:text-primary-200 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Inhalt</label>
            <textarea id="noteInhalt" rows="4" placeholder="Inhalt (optional)" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-720 dark:bg-primary-300 dark:text-primary-200 px-3 py-2 text-sm"></textarea>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" id="addNoteModalCancel" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
          <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-700 dark:bg-primary-420 rounded-lg hover:bg-primary-800 dark:hover:bg-primary-440">Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Ticket verknüpfen -->
<div id="linkTicketModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" id="linkTicketModalOverlay"></div>
    <div class="relative bg-white dark:bg-primary-100 rounded-lg shadow-xl max-w-lg w-full p-6 z-10">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-4">Ticket verknüpfen</h3>
      <div class="mb-4">
        <input type="text" id="ticketSearchInput" placeholder="Ticket-Nummer oder Titel suchen..." class="w-full rounded-lg border border-gray-300 dark:border-primary-720 dark:bg-primary-300 dark:text-primary-200 px-3 py-2 text-sm">
      </div>
      <div id="ticketSearchResults" class="max-h-64 overflow-y-auto border border-gray-200 dark:border-primary-120 rounded-lg divide-y divide-gray-200 dark:divide-primary-120">
        <div class="p-4 text-center text-gray-500 dark:text-primary-210 text-sm">Suche starten oder Suchbegriff eingeben</div>
      </div>
      <div class="mt-4 flex justify-end">
        <button type="button" id="closeLinkTicketModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">Schließen</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Bestellung verknüpfen -->
<div id="linkOrderModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" id="linkOrderModalOverlay"></div>
    <div class="relative bg-white dark:bg-primary-100 rounded-lg shadow-xl max-w-lg w-full p-6 z-10">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-4">Bestellung verknüpfen</h3>
      <div class="mb-4">
        <input type="text" id="orderSearchInput" placeholder="Bestellnummer oder Beschreibung suchen..." class="w-full rounded-lg border border-gray-300 dark:border-primary-720 dark:bg-primary-300 dark:text-primary-200 px-3 py-2 text-sm">
      </div>
      <div id="orderSearchResults" class="max-h-64 overflow-y-auto border border-gray-200 dark:border-primary-120 rounded-lg divide-y divide-gray-200 dark:divide-primary-120">
        <div class="p-4 text-center text-gray-500 dark:text-primary-210 text-sm">Suche starten oder Suchbegriff eingeben</div>
      </div>
      <div class="mt-4 flex justify-end">
        <button type="button" id="closeLinkOrderModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">Schließen</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Projekt bearbeiten -->
<div id="editProjectModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" id="editProjectModalOverlay"></div>
    <div class="relative bg-white dark:bg-primary-100 rounded-lg shadow-xl max-w-lg w-full p-6 z-10 max-h-[90vh] overflow-y-auto">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-4">Projekt bearbeiten</h3>
      <form id="editProjectForm">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Bezeichnung *</label>
            <input type="text" id="editBezeichnung" required class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Beschreibung</label>
            <textarea id="editBeschreibung" rows="3" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm"></textarea>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Startdatum</label>
              <input type="date" id="editStartDatum" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Enddatum</label>
              <input type="date" id="editEndDatum" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Geplantes Enddatum</label>
              <input type="date" id="editGeplantesEndDatum" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Budget (€)</label>
              <input type="number" id="editBudget" step="0.01" min="0" placeholder="0.00" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Status</label>
            <select id="editStatus" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
              <option value="Neu">Neu</option>
              <option value="In Planung">In Planung</option>
              <option value="In Bearbeitung">In Bearbeitung</option>
              <option value="Wartend">Wartend</option>
              <option value="Abgeschlossen">Abgeschlossen</option>
              <option value="Archiviert">Archiviert</option>
            </select>
          </div>
          <div id="editProjektleiterWrap">
            <label class="block text-sm font-medium text-gray-700 dark:text-primary-210">Projektleiter</label>
            <select id="editProjektleiter" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
              <option value="">— Keiner —</option>
            </select>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" id="editModalCancel" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
          <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-700 dark:bg-primary-420 rounded-lg hover:bg-primary-800 dark:hover:bg-primary-440">Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const projectId = <?php echo $projectId; ?>;
const projectsApiUrl = '<?php echo BASE_URL; ?>projects/api/projects.php';
const projectNotesApiUrl = '<?php echo BASE_URL; ?>projects/api/notes.php';
const todosApiUrl = '<?php echo BASE_URL; ?>todos/api/todos.php';
const _base = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo addslashes(BASE_URL); ?>').replace(/\/$/, '') || '';
const serviceBase = _base + '/tickets/view.php?id=';
const ordersBase = _base + '/orders/detail.php?id=';

function escapeHtml(t) {
  if (!t) return '';
  const d = document.createElement('div');
  d.textContent = t;
  return d.innerHTML;
}

function formatDate(s) {
  if (!s) return '—';
  return new Date(s).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

const statusColors = {
  'Neu': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
  'In Planung': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  'In Bearbeitung': 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
  'Wartend': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  'Abgeschlossen': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  'Archiviert': 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
};

function loadProject() {
  fetch(projectsApiUrl + '?id=' + projectId)
    .then(r => r.json())
    .then(d => {
      if (!d.success || !d.project) {
        document.getElementById('projectHeaderRow').classList.add('hidden');
        document.getElementById('projectLeftColumn').innerHTML = '<div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 p-4 text-red-700 dark:text-red-300">Projekt nicht gefunden</div>';
        document.getElementById('projectRightColumn').innerHTML = '';
        return;
      }
      renderProject(d.project);
    })
    .catch(() => {
      document.getElementById('projectHeaderRow').classList.add('hidden');
      document.getElementById('projectLeftColumn').innerHTML = '<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">Fehler beim Laden</div>';
      document.getElementById('projectRightColumn').innerHTML = '';
    });
}

function renderRightSidebar(p) {
  const companiesBase = _base + '/companies/detail.php?id=';
  const customersBase = _base + '/customers/detail.php?id=';
  const detailCard = (title, rows) => `
    <div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden">
      <div class="px-4 py-2.5 bg-gray-50 dark:bg-primary-120 border-b border-gray-100 dark:border-primary-140">
        <span class="font-semibold text-gray-900 dark:text-primary-220 text-sm">${title}</span>
      </div>
      <div class="p-3 space-y-2 text-sm">${rows}</div>
    </div>`;
  const personRow = (label, name, email, phone) => {
    if (!name && !email && !phone) return '';
    let s = name ? `<div class="font-medium text-gray-900 dark:text-primary-200">${escapeHtml(name)}</div>` : '';
    if (email) s += `<a href="mailto:${escapeHtml(email)}" class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-250 hover:underline text-xs"><svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>${escapeHtml(email)}</a>`;
    if (phone) s += `<a href="tel:${escapeHtml(phone)}" class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-250 hover:underline text-xs block mt-0.5"><svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>${escapeHtml(phone)}</a>`;
    return s ? `<div class="pt-1.5 border-t border-gray-100 dark:border-primary-140 first:border-0 first:pt-0">${label ? '<span class="text-gray-500 dark:text-primary-240 text-xs block mb-0.5">' + escapeHtml(label) + '</span>' : ''}${s}</div>` : '';
  };
  let html = '';
  if (p.company_name || p.company_id) {
    const addr = [p.company_adresse, (p.company_plz && p.company_ort) ? (p.company_plz + ' ' + p.company_ort) : (p.company_plz || p.company_ort)].filter(Boolean).join(', ');
    const link = p.company_id ? `<a href="${companiesBase}${p.company_id}" class="text-primary-600 dark:text-primary-250 hover:underline text-xs">Firmen-Detail →</a>` : '';
    html += detailCard('Firma',
      (addr ? `<div class="flex items-start gap-2"><svg class="w-4 h-4 text-gray-400 dark:text-primary-210 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg><span class="text-gray-700 dark:text-primary-220">${escapeHtml(addr)}</span></div>` : '') +
      (p.company_email ? `<a href="mailto:${escapeHtml(p.company_email)}" class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-250 hover:underline text-xs">${escapeHtml(p.company_email)}</a>` : '') +
      (p.company_telefon ? `<a href="tel:${escapeHtml(p.company_telefon)}" class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-250 hover:underline text-xs block">${escapeHtml(p.company_telefon)}</a>` : '') +
      personRow('Ansprechpartner', (p.company_ansprechpartner_vorname || p.company_ansprechpartner_nachname) ? [p.company_ansprechpartner_vorname, p.company_ansprechpartner_nachname].filter(Boolean).join(' ') : (p.company_ansprechpartner_manuell_name || ''), p.company_ansprechpartner_email || p.company_ansprechpartner_manuell_email, p.company_ansprechpartner_telefon || p.company_ansprechpartner_manuell_telefon) +
      (link ? '<div class="pt-2">' + link + '</div>' : ''));
  }
  if (p.customer_name || p.customer_id) {
    const addr = [p.customer_adresse, (p.customer_plz && p.customer_ort) ? (p.customer_plz + ' ' + p.customer_ort) : (p.customer_plz || p.customer_ort)].filter(Boolean).join(', ');
    const link = p.customer_id ? `<a href="${customersBase}${p.customer_id}" class="text-primary-600 dark:text-primary-250 hover:underline text-xs">Kunden-Detail →</a>` : '';
    html += detailCard('Kunde',
      (addr ? `<div class="flex items-start gap-2"><svg class="w-4 h-4 text-gray-400 dark:text-primary-210 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg><span class="text-gray-700 dark:text-primary-220">${escapeHtml(addr)}</span></div>` : '') +
      (p.customer_email ? `<a href="mailto:${escapeHtml(p.customer_email)}" class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-250 hover:underline text-xs">${escapeHtml(p.customer_email)}</a>` : '') +
      (p.customer_telefon ? `<a href="tel:${escapeHtml(p.customer_telefon)}" class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-250 hover:underline text-xs block">${escapeHtml(p.customer_telefon)}</a>` : '') +
      personRow('Ansprechpartner', (p.customer_ansprechpartner_vorname || p.customer_ansprechpartner_nachname) ? [p.customer_ansprechpartner_vorname, p.customer_ansprechpartner_nachname].filter(Boolean).join(' ') : (p.customer_ansprechpartner_manuell_name || ''), p.customer_ansprechpartner_email || p.customer_ansprechpartner_manuell_email, p.customer_ansprechpartner_telefon || p.customer_ansprechpartner_manuell_telefon) +
      (link ? '<div class="pt-2">' + link + '</div>' : ''));
  }
  const beauftrName = (p.beauftragter_vorname || p.beauftragter_nachname) ? [p.beauftragter_vorname, p.beauftragter_nachname].filter(Boolean).join(' ') : '';
  if (beauftrName || p.beauftragter_email || p.beauftragter_telefon) {
    html += detailCard('Bearbeiter', personRow('', beauftrName || '—', p.beauftragter_email, p.beauftragter_telefon));
  }
  const plName = (p.projektleiter_vorname || p.projektleiter_nachname) ? [p.projektleiter_vorname, p.projektleiter_nachname].filter(Boolean).join(' ') : '';
  if (plName || p.projektleiter_email || p.projektleiter_telefon) {
    html += detailCard('Projektleiter', personRow('', plName || '—', p.projektleiter_email, p.projektleiter_telefon));
  }
  const apName = (p.ansprechpartner_vorname || p.ansprechpartner_nachname) ? [p.ansprechpartner_vorname, p.ansprechpartner_nachname].filter(Boolean).join(' ') : (p.ansprechpartner_manuell_name || '');
  if (apName || p.ansprechpartner_email || p.ansprechpartner_telefon || p.ansprechpartner_manuell_email || p.ansprechpartner_manuell_telefon) {
    html += detailCard('Ansprechpartner', personRow('', apName || '—', p.ansprechpartner_email || p.ansprechpartner_manuell_email, p.ansprechpartner_telefon || p.ansprechpartner_manuell_telefon));
  }
  if (!html) html = '<p class="text-sm text-gray-500 dark:text-primary-210">Keine Firmen- oder Kundendaten.</p>';
  return html;
}

function renderTermineCard(p) {
  const rows = [
    { label: 'Startdatum', value: formatDate(p.start_datum) },
    { label: 'Enddatum', value: formatDate(p.end_datum) },
    { label: 'Geplantes Ende', value: formatDate(p.geplantes_end_datum) },
    { label: 'Budget', value: (p.budget != null && p.budget !== '') ? (Number(p.budget).toLocaleString('de-DE') + ' €') : '—' }
  ].map(r => `<div class="flex justify-between items-baseline gap-2 py-1.5 border-b border-gray-100 dark:border-primary-140 last:border-0"><span class="text-gray-500 dark:text-primary-240 text-xs">${escapeHtml(r.label)}</span><span class="text-sm font-medium text-gray-900 dark:text-primary-200">${escapeHtml(r.value)}</span></div>`).join('');
  return `<div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 overflow-hidden">
    <div class="px-4 py-2.5 bg-gray-50 dark:bg-primary-120 border-b border-gray-100 dark:border-primary-140">
      <span class="font-semibold text-gray-900 dark:text-primary-220 text-sm">Termine & Budget</span>
    </div>
    <div class="p-3 space-y-0 text-sm">${rows}</div>
  </div>`;
}

function renderProject(p) {
  _currentProject = p;
  document.getElementById('projectBreadcrumb').textContent = (p.bezeichnung || 'Projekt').substring(0, 40) + ((p.bezeichnung || '').length > 40 ? '…' : '');
  document.getElementById('projectHeaderRow').classList.remove('hidden');

  const statusClass = statusColors[p.status] || statusColors['Neu'];
  const canAbrechnen = p.status === 'Abgeschlossen';
  const todos = p.todos || [];
  const todosOffen = todos.filter(t => t.status !== 'erledigt').length;
  const notesCount = (p.notes || []).length;
  const ticketsCount = (p.tickets || []).length;
  const ordersCount = (p.orders || []).length;
  const docsCount = (p.attachments || []).length;
  const beteiligteCount = (p.beteiligte || []).length;

  const overviewCards = `
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
      <div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 p-4">
        <div class="text-2xl font-bold text-amber-700 dark:text-amber-400">${todosOffen}</div>
        <div class="text-xs font-medium text-amber-800/80 dark:text-amber-300/80 mt-0.5">Offene Aufgaben</div>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-gray-50 dark:bg-primary-120/50 p-4">
        <div class="text-2xl font-bold text-gray-800 dark:text-primary-200">${notesCount}</div>
        <div class="text-xs font-medium text-gray-600 dark:text-primary-240 mt-0.5">Notizen</div>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-blue-50 dark:bg-blue-900/20 p-4">
        <div class="text-2xl font-bold text-blue-700 dark:text-blue-400">${ticketsCount}</div>
        <div class="text-xs font-medium text-blue-800/80 dark:text-blue-300/80 mt-0.5">Tickets</div>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-emerald-50 dark:bg-emerald-900/20 p-4">
        <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">${ordersCount}</div>
        <div class="text-xs font-medium text-emerald-800/80 dark:text-emerald-300/80 mt-0.5">Bestellungen</div>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-violet-50 dark:bg-violet-900/20 p-4">
        <div class="text-2xl font-bold text-violet-700 dark:text-violet-400">${docsCount}</div>
        <div class="text-xs font-medium text-violet-800/80 dark:text-violet-300/80 mt-0.5">Dokumente</div>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-primary-120 bg-slate-50 dark:bg-slate-800/30 p-4">
        <div class="text-2xl font-bold text-slate-700 dark:text-slate-300">${beteiligteCount}</div>
        <div class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-0.5">Beteiligte</div>
      </div>
    </div>
    ${p.beschreibung ? '<div class="mt-4 pt-4 border-t border-gray-200 dark:border-primary-120"><p class="text-sm text-gray-600 dark:text-primary-210 leading-relaxed">' + escapeHtml(p.beschreibung) + '</p></div>' : ''}
  `;

  const leftHtml = `
    <div class="flex flex-col flex-1 min-h-0 bg-white dark:bg-primary-100 rounded-xl shadow-sm border border-gray-200 dark:border-primary-120 overflow-hidden">
      <div class="flex border-b border-gray-200 dark:border-primary-120 overflow-x-auto custom-scrollbar">
        <button type="button" class="project-tab-btn active px-4 py-3 text-sm font-medium text-primary-600 dark:text-primary-250 border-b-2 border-primary-500 dark:border-primary-400 bg-primary-50/50 dark:bg-primary-120/30 whitespace-nowrap" data-tab="uebersicht">Übersicht</button>
        <button type="button" class="project-tab-btn px-4 py-3 text-sm font-medium text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 border-b-2 border-transparent hover:bg-gray-50 dark:hover:bg-primary-140/50 whitespace-nowrap" data-tab="notizen">Notizen</button>
        <button type="button" class="project-tab-btn px-4 py-3 text-sm font-medium text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 border-b-2 border-transparent hover:bg-gray-50 dark:hover:bg-primary-140/50 whitespace-nowrap" data-tab="aufgaben">Aufgaben</button>
        <button type="button" class="project-tab-btn px-4 py-3 text-sm font-medium text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 border-b-2 border-transparent hover:bg-gray-50 dark:hover:bg-primary-140/50 whitespace-nowrap" data-tab="tickets">Tickets</button>
        <button type="button" class="project-tab-btn px-4 py-3 text-sm font-medium text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 border-b-2 border-transparent hover:bg-gray-50 dark:hover:bg-primary-140/50 whitespace-nowrap" data-tab="bestellungen">Bestellungen</button>
        <button type="button" class="project-tab-btn px-4 py-3 text-sm font-medium text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 border-b-2 border-transparent hover:bg-gray-50 dark:hover:bg-primary-140/50 whitespace-nowrap" data-tab="dokumente">Dokumente</button>
        <button type="button" class="project-tab-btn px-4 py-3 text-sm font-medium text-gray-600 dark:text-primary-220 hover:text-primary-600 dark:hover:text-primary-250 border-b-2 border-transparent hover:bg-gray-50 dark:hover:bg-primary-140/50 whitespace-nowrap" data-tab="beteiligte">Beteiligte</button>
      </div>
      <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-5">
        <div id="tab-uebersicht" class="project-tab-pane">${overviewCards}</div>
        <div id="tab-notizen" class="project-tab-pane hidden">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Notizen</h3>
            <button type="button" id="addNoteBtn" class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">+ Notiz</button>
          </div>
          <div id="notesList" class="space-y-3">${renderNotes(p.notes || [])}</div>
        </div>
        <div id="tab-aufgaben" class="project-tab-pane hidden">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Aufgaben (Todos)</h3>
            <button type="button" id="addTodoBtn" class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">+ Todo</button>
          </div>
          <div id="todosList" class="space-y-2">${renderTodos(p.todos || [])}</div>
        </div>
        <div id="tab-tickets" class="project-tab-pane hidden">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Verknüpfte Tickets</h3>
            <button type="button" id="linkTicketBtn" class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">+ Verknüpfen</button>
          </div>
          <div id="ticketsList" class="space-y-2">${renderTickets(p.tickets || [])}</div>
        </div>
        <div id="tab-bestellungen" class="project-tab-pane hidden">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Bestellungen</h3>
            <button type="button" id="linkOrderBtn" class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">+ Verknüpfen</button>
          </div>
          <div id="ordersList" class="space-y-2">${renderOrders(p.orders || [])}</div>
        </div>
        <div id="tab-dokumente" class="project-tab-pane hidden">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200">Dokumente</h3>
            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 rounded-lg border border-primary-200 dark:border-primary-120 hover:bg-primary-50 dark:hover:bg-primary-140">
              <input type="file" id="projectDocInput" class="sr-only" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
              Hochladen
            </label>
          </div>
          <div id="projectDocsList" class="space-y-2">${renderProjectDocs(p.attachments || [])}</div>
        </div>
        <div id="tab-beteiligte" class="project-tab-pane hidden">
          <h3 class="text-base font-semibold text-gray-900 dark:text-primary-200 mb-4">Beteiligte Mitarbeiter</h3>
          ${(p.beteiligte && p.beteiligte.length) ? '<ul class="space-y-2 text-sm">' + p.beteiligte.map(b => {
            const name = [b.vorname, b.nachname].filter(Boolean).join(' ');
            return '<li class="flex items-center gap-2 py-2 border-b border-gray-100 dark:border-primary-140 last:border-0">' + (name ? '<span class="font-medium">' + escapeHtml(name) + '</span>' : '') + (b.email ? '<a href="mailto:' + escapeHtml(b.email) + '" class="text-primary-600 dark:text-primary-250 hover:underline text-xs">' + escapeHtml(b.email) + '</a>' : '') + (b.telefonnummer ? '<a href="tel:' + escapeHtml(b.telefonnummer) + '" class="text-primary-600 dark:text-primary-250 hover:underline text-xs">' + escapeHtml(b.telefonnummer) + '</a>' : '') + '</li>';
          }).join('') + '</ul>' : '<p class="text-sm text-gray-500 dark:text-primary-210">Keine Beteiligten eingetragen.</p>'}
        </div>
      </div>
    </div>
  `;

  document.getElementById('projectLeftColumn').innerHTML = leftHtml;
  document.getElementById('projectRightColumn').innerHTML = renderTermineCard(p) + renderRightSidebar(p);

  // Status-Buttons aktiv setzen
  document.querySelectorAll('.project-status-btn').forEach(btn => {
    btn.classList.remove('bg-primary-100', 'dark:bg-primary-600', 'border-primary-500');
    if (btn.dataset.status === p.status) btn.classList.add('bg-primary-100', 'dark:bg-primary-600', 'border-primary-500');
    btn.onclick = () => updateProjectStatus(btn.dataset.status);
  });
  document.getElementById('projectStatusButtonGroup').classList.remove('hidden');

  const optAbrechnen = document.getElementById('project-option-abrechnen');
  if (optAbrechnen) {
    if (canAbrechnen) { optAbrechnen.classList.remove('hidden'); optAbrechnen.onclick = () => doAbrechnen(); } else optAbrechnen.classList.add('hidden');
  }
  document.getElementById('project-option-edit').onclick = () => openEditModal(p);
  document.getElementById('project-option-copy-number').onclick = () => { if (p.project_nummer) { navigator.clipboard.writeText(p.project_nummer); if (typeof showToast === 'function') showToast('Projektnummer kopiert', 'success'); else alert('Kopiert'); } };
  document.getElementById('project-more-options-btn').onclick = () => document.getElementById('project-more-options-menu').classList.toggle('hidden');
  document.querySelectorAll('.project-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.project-tab-btn').forEach(b => { b.classList.remove('active', 'text-primary-600', 'dark:text-primary-250', 'border-primary-500', 'dark:border-primary-400', 'bg-primary-50/50', 'dark:bg-primary-120/30'); b.classList.add('text-gray-600', 'dark:text-primary-220'); });
      btn.classList.add('active', 'text-primary-600', 'dark:text-primary-250', 'border-primary-500', 'dark:border-primary-400', 'bg-primary-50/50', 'dark:bg-primary-120/30'); btn.classList.remove('text-gray-600', 'dark:text-primary-220');
      document.querySelectorAll('.project-tab-pane').forEach(pan => pan.classList.add('hidden'));
      const pan = document.getElementById('tab-' + btn.dataset.tab);
      if (pan) pan.classList.remove('hidden');
    });
  });

  document.getElementById('addNoteBtn').addEventListener('click', () => openNoteModal());
  document.getElementById('addTodoBtn').addEventListener('click', () => openTodoModal());
  document.getElementById('linkTicketBtn').addEventListener('click', () => openLinkTicketModal(p.company_id));
  document.getElementById('linkOrderBtn').addEventListener('click', () => openLinkOrderModal(p.company_id));
  document.getElementById('editProjectBtn') && document.getElementById('editProjectBtn').addEventListener('click', () => openEditModal(p));
  const docInput = document.getElementById('projectDocInput');
  if (docInput) docInput.addEventListener('change', (e) => { if (e.target.files && e.target.files[0]) uploadProjectDoc(e.target.files[0]); e.target.value = ''; });
  document.querySelectorAll('.unlink-ticket-btn').forEach(btn => btn.addEventListener('click', (e) => unlinkTicket(parseInt(e.currentTarget.dataset.ticketId, 10))));
  document.querySelectorAll('.unlink-order-btn').forEach(btn => btn.addEventListener('click', (e) => unlinkOrder(parseInt(e.currentTarget.dataset.orderId, 10))));
  document.querySelectorAll('.delete-project-doc-btn').forEach(btn => btn.addEventListener('click', (e) => deleteProjectDoc(parseInt(e.currentTarget.dataset.attId, 10))));
}

function updateProjectStatus(status) {
  if (!_currentProject) return;
  const payload = { id: projectId, bezeichnung: _currentProject.bezeichnung || '', beschreibung: _currentProject.beschreibung || null, status: status };
  fetch(projectsApiUrl, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
    .then(r => r.json())
    .then(d => { if (d.success) loadProject(); else { if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error'); else alert(d.error); } })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
}

function renderNotes(notes) {
  if (!notes.length) return '<p class="text-sm text-gray-500 dark:text-primary-210">Keine Notizen</p>';
  return notes.map(n => {
    const by = (n.vorname && n.nachname) ? (n.vorname + ' ' + n.nachname).trim() : '';
    return `<div class="p-3 rounded-lg bg-gray-50 dark:bg-primary-140 border border-gray-100 dark:border-primary-120">
      <div class="flex justify-between">
        <strong class="text-sm text-gray-900 dark:text-primary-200">${escapeHtml(n.titel || 'Notiz')}</strong>
        <span class="text-xs text-gray-500">${formatDate(n.erstellt_datum)}</span>
      </div>
      ${n.inhalt ? '<p class="mt-1 text-sm text-gray-600 dark:text-primary-210">' + escapeHtml(n.inhalt) + '</p>' : ''}
      ${by ? '<p class="text-xs text-gray-500 mt-1">' + escapeHtml(by) + '</p>' : ''}
    </div>`;
  }).join('');
}

function renderTodos(todos) {
  if (!todos.length) return '<p class="text-sm text-gray-500 dark:text-primary-210">Keine Aufgaben</p>';
  const statusLabels = { offen: 'Offen', in_bearbeitung: 'In Bearbeitung', erledigt: 'Erledigt' };
  return todos.map(t => {
    const statusClass = t.status === 'erledigt' ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400';
    return `<div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">
      <a href="${_base}/todos/?id=${t.id}" class="text-sm text-gray-900 dark:text-primary-200 hover:underline">${escapeHtml(t.titel)}</a>
      <span class="text-xs ${statusClass}">${statusLabels[t.status] || t.status}</span>
    </div>`;
  }).join('');
}

function renderTickets(tickets) {
  if (!tickets.length) return '<p class="text-sm text-gray-500 dark:text-primary-210">Keine verknüpften Tickets</p>';
  return tickets.map(t => `<div class="flex items-center justify-between gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">
    <a href="${serviceBase}${t.id}" class="text-sm text-primary-600 hover:underline flex-1 min-w-0 truncate">${escapeHtml(t.ticket_nummer || '#' + t.id)} – ${escapeHtml(t.titel || '')}</a>
    <span class="text-xs text-gray-500 flex-shrink-0">${escapeHtml(t.status || '')}</span>
    <button type="button" class="unlink-ticket-btn flex-shrink-0 p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400" data-ticket-id="${t.id}" title="Verknüpfung aufheben"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
  </div>`).join('');
}

function renderOrders(orders) {
  if (!orders.length) return '<p class="text-sm text-gray-500 dark:text-primary-210">Keine Bestellungen</p>';
  return orders.map(o => `<div class="flex items-center justify-between gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">
    <a href="${ordersBase}${o.id}" class="text-sm text-primary-600 hover:underline flex-1 min-w-0 truncate">${escapeHtml(o.bestellnummer || '#' + o.id)}</a>
    <span class="text-xs text-gray-500 flex-shrink-0">${escapeHtml(o.status || '')}</span>
    <button type="button" class="unlink-order-btn flex-shrink-0 p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400" data-order-id="${o.id}" title="Verknüpfung aufheben"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
  </div>`).join('');
}

function renderProjectDocs(attachments) {
  if (!attachments.length) return '<p class="text-sm text-gray-500 dark:text-primary-210">Keine Dokumente</p>';
  return attachments.map(a => {
    const by = (a.erstellt_von_vorname || a.erstellt_von_nachname) ? [a.erstellt_von_vorname, a.erstellt_von_nachname].filter(Boolean).join(' ') : '';
    return `<div class="flex items-center justify-between gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">
      <a href="${projectAttachmentsApiUrl}?id=${a.id}&download=1" class="text-sm text-primary-600 hover:underline flex-1 min-w-0 truncate" target="_blank" rel="noopener">${escapeHtml(a.dateiname || 'Dokument')}</a>
      <span class="text-xs text-gray-500 flex-shrink-0">${formatDate(a.erstellt_datum)}${by ? ' · ' + escapeHtml(by) : ''}</span>
      <button type="button" class="delete-project-doc-btn flex-shrink-0 p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400" data-att-id="${a.id}" title="Löschen"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
    </div>`;
  }).join('');
}

const projectAttachmentsApiUrl = '<?php echo BASE_URL; ?>projects/api/attachments.php';
let _currentProject = null;

function uploadProjectDoc(file) {
  const fd = new FormData();
  fd.append('project_id', projectId);
  fd.append('file', file);
  fetch(projectAttachmentsApiUrl, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { if (d.success) loadProject(); else { if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error'); else alert(d.error || 'Fehler'); } })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Hochladen', 'error'); else alert('Fehler'); });
}

function deleteProjectDoc(attId) {
  if (!confirm('Dokument wirklich löschen?')) return;
  fetch(projectAttachmentsApiUrl + '?id=' + attId, { method: 'DELETE' })
    .then(r => r.json())
    .then(d => { if (d.success) loadProject(); else { if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error'); else alert(d.error); } })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
}

function doAbrechnen() {
  if (!confirm('Projekt als abgerechnet (Archiv) markieren?')) return;
  fetch(projectsApiUrl, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'abrechnen', id: projectId })
  })
    .then(r => r.json())
    .then(d => { if (d.success) { loadProject(); if (typeof showToast === 'function') showToast('Projekt abgerechnet (Archiv)', 'success'); } else { if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error'); else alert(d.error); } })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
}

function openNoteModal() {
  document.getElementById('noteTitel').value = '';
  document.getElementById('noteInhalt').value = '';
  document.getElementById('addNoteModal').classList.remove('hidden');
}

function closeNoteModal() {
  document.getElementById('addNoteModal').classList.add('hidden');
}

function openTodoModal() {
  const titel = prompt('Titel der Aufgabe:');
  if (!titel) return;
  fetch(todosApiUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ titel: titel.trim(), project_id: projectId })
  })
    .then(r => r.json())
    .then(d => { if (d.success) loadProject(); else alert(d.error || 'Fehler'); })
    .catch(() => alert('Fehler'));
}

let _currentProjectCompanyId = null;

function openLinkTicketModal(companyId) {
  _currentProjectCompanyId = companyId || null;
  document.getElementById('ticketSearchInput').value = '';
  document.getElementById('ticketSearchResults').innerHTML = '<div class="p-4 text-center text-gray-500 dark:text-primary-210 text-sm">Lade…</div>';
  document.getElementById('linkTicketModal').classList.remove('hidden');
  document.getElementById('ticketSearchInput').focus();
  searchTickets('');
}

function closeLinkTicketModal() {
  document.getElementById('linkTicketModal').classList.add('hidden');
}

function searchTickets(q) {
  const params = new URLSearchParams({ action: 'search_tickets', exclude_project_id: projectId });
  if (q) params.set('q', q);
  if (_currentProjectCompanyId) params.set('company_id', _currentProjectCompanyId);
  fetch(projectsApiUrl + '?' + params)
    .then(r => r.json())
    .then(d => {
      const el = document.getElementById('ticketSearchResults');
      if (!d.success || !d.tickets || !d.tickets.length) {
        el.innerHTML = '<div class="p-4 text-center text-gray-500 dark:text-primary-210 text-sm">' + (q ? 'Keine Tickets gefunden' : 'Suche starten') + '</div>';
        return;
      }
      el.innerHTML = d.tickets.map(t => {
        const label = (t.ticket_nummer || '#' + t.id) + ' – ' + (t.titel || '') + (t.company_name ? ' (' + t.company_name + ')' : '');
        return '<button type="button" class="block w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-primary-140 text-sm" data-ticket-id="' + t.id + '">' + escapeHtml(label) + '<br><span class="text-xs text-gray-500">' + escapeHtml(t.status || '') + '</span></button>';
      }).join('');
      el.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
          const id = parseInt(btn.dataset.ticketId, 10);
          fetch(projectsApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'link_ticket', project_id: projectId, ticket_id: id })
          })
            .then(r => r.json())
            .then(data => { if (data.success) { closeLinkTicketModal(); loadProject(); } else { if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error'); else alert(data.error || 'Fehler'); } })
            .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
        });
      });
    })
    .catch(() => {
      document.getElementById('ticketSearchResults').innerHTML = '<div class="p-4 text-center text-red-600 dark:text-red-400 text-sm">Fehler bei der Suche</div>';
    });
}

function unlinkTicket(ticketId) {
  if (!confirm('Verknüpfung wirklich aufheben?')) return;
  fetch(projectsApiUrl + '?id=' + projectId + '&unlink_ticket=' + ticketId)
    .then(r => r.json())
    .then(d => { if (d.success) loadProject(); else { if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error'); else alert(d.error || 'Fehler'); } })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
}

function openEditModal(p) {
  document.getElementById('editBezeichnung').value = p.bezeichnung || '';
  document.getElementById('editBeschreibung').value = p.beschreibung || '';
  document.getElementById('editStatus').value = p.status || 'Neu';
  document.getElementById('editStartDatum').value = (p.start_datum || '').toString().substring(0, 10);
  document.getElementById('editEndDatum').value = (p.end_datum || '').toString().substring(0, 10);
  document.getElementById('editGeplantesEndDatum').value = (p.geplantes_end_datum || '').toString().substring(0, 10);
  document.getElementById('editBudget').value = p.budget != null && p.budget !== '' ? p.budget : '';
  const plSelect = document.getElementById('editProjektleiter');
  if (plSelect) {
    if (plSelect.options.length <= 1) {
      fetch(projectsApiUrl + '?action=assignable_users').then(r => r.json()).then(d => {
        if (d.success && d.users) {
          plSelect.innerHTML = '<option value="">— Keiner —</option>' + d.users.map(u => '<option value="' + u.id + '">' + escapeHtml([u.vorname, u.nachname].filter(Boolean).join(' ') || u.email || '#' + u.id) + '</option>').join('');
          plSelect.value = p.projektleiter_user_id || '';
        }
      }).catch(() => {});
    } else plSelect.value = p.projektleiter_user_id || '';
  }
  document.getElementById('editProjectModal').classList.remove('hidden');
}
function closeEditModal() {
  document.getElementById('editProjectModal').classList.add('hidden');
}

function openLinkOrderModal(companyId) {
  _currentProjectCompanyId = companyId || null;
  document.getElementById('orderSearchInput').value = '';
  document.getElementById('orderSearchResults').innerHTML = '<div class="p-4 text-center text-gray-500 dark:text-primary-210 text-sm">Lade…</div>';
  document.getElementById('linkOrderModal').classList.remove('hidden');
  document.getElementById('orderSearchInput').focus();
  searchOrders('');
}

function closeLinkOrderModal() {
  document.getElementById('linkOrderModal').classList.add('hidden');
}

function searchOrders(q) {
  const params = new URLSearchParams({ action: 'search_orders', exclude_project_id: projectId });
  if (q) params.set('q', q);
  if (_currentProjectCompanyId) params.set('company_id', _currentProjectCompanyId);
  fetch(projectsApiUrl + '?' + params)
    .then(r => r.json())
    .then(d => {
      const el = document.getElementById('orderSearchResults');
      if (!d.success || !d.orders || !d.orders.length) {
        el.innerHTML = '<div class="p-4 text-center text-gray-500 dark:text-primary-210 text-sm">' + (q ? 'Keine Bestellungen gefunden' : 'Suche starten') + '</div>';
        return;
      }
      el.innerHTML = d.orders.map(o => {
        const label = (o.bestellnummer || '#' + o.id) + (o.beschreibung ? ' – ' + (o.beschreibung.length > 60 ? o.beschreibung.substring(0, 60) + '…' : o.beschreibung) : '') + (o.company_name ? ' (' + o.company_name + ')' : '');
        return '<button type="button" class="block w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-primary-140 text-sm" data-order-id="' + o.id + '">' + escapeHtml(label) + '<br><span class="text-xs text-gray-500">' + escapeHtml(o.status || '') + '</span></button>';
      }).join('');
      el.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
          const id = parseInt(btn.dataset.orderId, 10);
          fetch(projectsApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'link_order', project_id: projectId, order_id: id })
          })
            .then(r => r.json())
            .then(data => { if (data.success) { closeLinkOrderModal(); loadProject(); } else { if (typeof showToast === 'function') showToast(data.error || 'Fehler', 'error'); else alert(data.error || 'Fehler'); } })
            .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
        });
      });
    })
    .catch(() => {
      document.getElementById('orderSearchResults').innerHTML = '<div class="p-4 text-center text-red-600 dark:text-red-400 text-sm">Fehler bei der Suche</div>';
    });
}

function unlinkOrder(orderId) {
  if (!confirm('Verknüpfung wirklich aufheben?')) return;
  fetch(projectsApiUrl + '?id=' + projectId + '&unlink_order=' + orderId)
    .then(r => r.json())
    .then(d => { if (d.success) loadProject(); else { if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error'); else alert(d.error || 'Fehler'); } })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
}

document.addEventListener('DOMContentLoaded', function() {
  loadProject();
  document.addEventListener('click', function(e) {
    const container = document.getElementById('project-more-options-container');
    const menu = document.getElementById('project-more-options-menu');
    if (container && menu && !container.contains(e.target)) menu.classList.add('hidden');
  });
  document.getElementById('editProjectModalOverlay').addEventListener('click', closeEditModal);
  document.getElementById('editModalCancel').addEventListener('click', closeEditModal);
  document.getElementById('addNoteModalOverlay').addEventListener('click', closeNoteModal);
  document.getElementById('addNoteModalCancel').addEventListener('click', closeNoteModal);
  document.getElementById('addNoteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const titel = document.getElementById('noteTitel').value.trim() || 'Notiz';
    const inhalt = document.getElementById('noteInhalt').value.trim() || '';
    fetch(projectNotesApiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ project_id: projectId, titel: titel, inhalt: inhalt })
    })
      .then(r => r.json())
      .then(d => {
        if (d.success) { closeNoteModal(); loadProject(); if (typeof showToast === 'function') showToast('Notiz hinzugefügt', 'success'); }
        else { if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error'); else alert(d.error || 'Fehler'); }
      })
      .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
  });
  document.getElementById('linkTicketModalOverlay').addEventListener('click', closeLinkTicketModal);
  document.getElementById('closeLinkTicketModal').addEventListener('click', closeLinkTicketModal);
  let ticketSearchTimeout;
  document.getElementById('ticketSearchInput').addEventListener('input', function() {
    clearTimeout(ticketSearchTimeout);
    const q = this.value.trim();
    ticketSearchTimeout = setTimeout(() => searchTickets(q), 300);
  });
  document.getElementById('linkOrderModalOverlay').addEventListener('click', closeLinkOrderModal);
  document.getElementById('closeLinkOrderModal').addEventListener('click', closeLinkOrderModal);
  let orderSearchTimeout;
  document.getElementById('orderSearchInput').addEventListener('input', function() {
    clearTimeout(orderSearchTimeout);
    const q = this.value.trim();
    orderSearchTimeout = setTimeout(() => searchOrders(q), 300);
  });
  document.getElementById('editProjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const payload = {
      id: projectId,
      bezeichnung: document.getElementById('editBezeichnung').value.trim(),
      beschreibung: document.getElementById('editBeschreibung').value.trim(),
      status: document.getElementById('editStatus').value
    };
    const startEl = document.getElementById('editStartDatum');
    const endEl = document.getElementById('editEndDatum');
    const geplantesEl = document.getElementById('editGeplantesEndDatum');
    const budgetEl = document.getElementById('editBudget');
    const plEl = document.getElementById('editProjektleiter');
    if (startEl) payload.start_datum = startEl.value || null;
    if (endEl) payload.end_datum = endEl.value || null;
    if (geplantesEl) payload.geplantes_end_datum = geplantesEl.value || null;
    if (budgetEl) payload.budget = budgetEl.value ? parseFloat(budgetEl.value) : null;
    if (plEl) payload.projektleiter_user_id = plEl.value ? parseInt(plEl.value, 10) : null;
    fetch(projectsApiUrl, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(d => { if (d.success) { closeEditModal(); loadProject(); if (typeof showToast === 'function') showToast('Gespeichert', 'success'); } else { if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error'); else alert(d.error || 'Fehler'); } })
      .catch(() => { if (typeof showToast === 'function') showToast('Fehler', 'error'); else alert('Fehler'); });
  });
});
</script>
