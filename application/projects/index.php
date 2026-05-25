<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
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

if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$companies = [];
$stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($companies as &$co) { decrypt_company_row($co); }
unset($co);

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

$baseUrl = rtrim(BASE_URL, '/');
?>

<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-hidden flex flex-col h-screen max-h-[100dvh] projects-page-root">
  <main class="flex flex-col flex-1 min-h-0 overflow-hidden mx-4 mt-2  ">
    <nav class="mb-2 flex-shrink-0" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
            <svg class="me-2.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd"/></svg>
            Startseite
          </a>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Projekte</span>
          </div>
        </li>
      </ol>
    </nav>

    <div class="flex flex-col-reverse items-stretch justify-between pb-1 space-y-2 md:flex-row md:items-center md:space-y-0 flex-shrink-0">
      <div class="flex flex-col w-full space-y-3 md:space-y-0 md:flex-row md:items-center md:flex-1 md:gap-3">
        <form class="flex-1 w-full md:max-w-sm md:mr-0" id="search-form">
          <label for="project-search" class="text-sm font-medium text-gray-900 sr-only dark:text-white">Suche</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input type="search" id="project-search" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-820 focus:border-primary-820 dark:bg-primary-300 dark:border-primary-320 dark:text-primary-200 dark:focus:ring-primary-820" placeholder="Projekte suchen...">
          </div>
        </form>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" id="createProjectBtn" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 dark:bg-primary-420 dark:hover:bg-primary-440">
          <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
          Projekt anlegen
        </button>
      </div>
    </div>

    <!-- Kanban-Board (volle Höhe) -->
    <div id="kanbanView" class="flex-1 min-h-0 flex flex-col overflow-hidden">
      <div id="kanbanColumns" class="kanban-scroll-x flex flex-1 min-h-0 overflow-x-auto overflow-y-hidden pb-2">
        <!-- Spalten werden per JS gefüllt -->
      </div>
    </div>
  </main>
</div>

<style>
/* Seite auf Viewport begrenzen – nur Kanban-Bereiche scrollen */
html:has(.projects-page-root), body:has(.projects-page-root) {
    height: 100%;
    max-height: 100dvh;
    overflow: hidden;
}
.projects-page-root { min-height: 0; }

/* Kanban: schlanke Scrollbalken (horizontal + vertikal) */
.kanban-scroll-x,
.kanban-col-cards {
    scrollbar-width: thin;
    scrollbar-gutter: stable;
}
.kanban-scroll-x { scrollbar-color: rgba(148, 163, 184, 0.5) rgba(241, 245, 249, 0.4); }
.kanban-col-cards { scrollbar-color: rgba(148, 163, 184, 0.5) transparent; }
.dark .kanban-scroll-x { scrollbar-color: rgba(100, 116, 139, 0.5) rgba(15, 23, 42, 0.4); }
.dark .kanban-col-cards { scrollbar-color: rgba(100, 116, 139, 0.5) transparent; }

/* Horizontaler Scrollbalken (#kanbanColumns) */
.kanban-scroll-x::-webkit-scrollbar { height: 10px; }
.kanban-scroll-x::-webkit-scrollbar-track {
    background: rgba(241, 245, 249, 0.5);
    border-radius: 5px;
    margin: 0 4px;
}
.dark .kanban-scroll-x::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.5);
}
.kanban-scroll-x::-webkit-scrollbar-thumb {
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.55) 0%, rgba(148, 163, 184, 0.35) 100%);
    border-radius: 5px;
    transition: background 0.2s ease;
}
.kanban-scroll-x::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.75) 0%, rgba(148, 163, 184, 0.55) 100%);
}
.dark .kanban-scroll-x::-webkit-scrollbar-thumb {
    background: linear-gradient(90deg, rgba(100, 116, 139, 0.55) 0%, rgba(100, 116, 139, 0.35) 100%);
}
.dark .kanban-scroll-x::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(90deg, rgba(100, 116, 139, 0.75) 0%, rgba(100, 116, 139, 0.55) 100%);
}
.kanban-scroll-x::-webkit-scrollbar-corner { background: transparent; }

/* Vertikaler Scrollbalken (Spalten-Kartenbereich) */
.kanban-col-cards::-webkit-scrollbar { width: 8px; }
.kanban-col-cards::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 4px;
    margin: 4px 0;
}
.kanban-col-cards::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(148, 163, 184, 0.5) 0%, rgba(148, 163, 184, 0.3) 100%);
    border-radius: 4px;
    transition: background 0.2s ease;
}
.kanban-col-cards::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, rgba(148, 163, 184, 0.7) 0%, rgba(148, 163, 184, 0.5) 100%);
}
.dark .kanban-col-cards::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(100, 116, 139, 0.5) 0%, rgba(100, 116, 139, 0.3) 100%);
}
.dark .kanban-col-cards::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, rgba(100, 116, 139, 0.7) 0%, rgba(100, 116, 139, 0.5) 100%);
}
</style>

<!-- Kontextmenü Projekt (Rechtsklick) -->
<div id="projectContextMenu" class="hidden fixed z-[100] min-w-[200px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg">
  <button type="button" data-project-ctx="open-new-tab" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
    Im neuen Tab öffnen
  </button>
  <div class="border-t border-gray-200 dark:border-primary-120 my-1"></div>
  <div id="projectCtxStatusSection" class="relative">
    <div id="projectCtxStatusTrigger" class="px-3 py-2 text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 cursor-default">
      <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span>Status ändern</span>
      <svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </div>
    <div id="projectCtxStatusSubmenu" class="hidden absolute left-full top-0 ml-0.5 min-w-[180px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg z-10">
      <!-- Status-Optionen per JS -->
    </div>
  </div>
  <div class="border-t border-gray-200 dark:border-primary-120 my-1"></div>
  <button type="button" data-project-ctx="edit-betreff" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
    Betreff ändern
  </button>
  <div class="border-t border-gray-200 dark:border-primary-120 my-1"></div>
  <button type="button" data-project-ctx="soft-delete" class="w-full px-3 py-2 text-left text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
    Löschen
  </button>
</div>

<!-- Modal: Betreff ändern (Inline) -->
<div id="betreffModal" class="hidden fixed inset-0 z-[60] overflow-y-auto" aria-modal="true">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" id="betreffModalOverlay"></div>
    <div class="relative bg-white dark:bg-primary-100 rounded-lg shadow-xl max-w-md w-full p-6 z-10">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-4">Betreff ändern</h3>
      <input type="text" id="betreffInput" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 rounded-lg" placeholder="Bezeichnung">
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" id="betreffModalCancel" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
        <button type="button" id="betreffModalSave" class="px-4 py-2 text-sm font-medium text-white bg-primary-700 dark:bg-primary-420 rounded-lg hover:bg-primary-800 dark:hover:bg-primary-440">Speichern</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Projekt anlegen/bearbeiten -->
<div id="projectModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" id="projectModalOverlay"></div>
    <div class="relative bg-white dark:bg-primary-100 rounded-lg shadow-xl max-w-lg w-full p-6 z-10">
      <h3 id="projectModalTitle" class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-4">Projekt anlegen</h3>
      <form id="projectForm">
        <input type="hidden" id="projectId" name="id" value="">
        <div class="space-y-4">
          <div>
            <label for="projectBezeichnung" class="block text-sm font-medium text-gray-700 dark:text-primary-210">Bezeichnung *</label>
            <input type="text" id="projectBezeichnung" name="bezeichnung" required class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
          </div>
          <div>
            <label for="projectBeschreibung" class="block text-sm font-medium text-gray-700 dark:text-primary-210">Beschreibung</label>
            <textarea id="projectBeschreibung" name="beschreibung" rows="3" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm"></textarea>
          </div>
          <div>
            <label for="projectStatus" class="block text-sm font-medium text-gray-700 dark:text-primary-210">Status</label>
            <select id="projectStatus" name="status" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
              <option value="Neu">Neu</option>
              <option value="In Planung">In Planung</option>
              <option value="In Bearbeitung">In Bearbeitung</option>
              <option value="Wartend">Wartend</option>
              <option value="Abgeschlossen">Abgeschlossen</option>
              <option value="Archiviert">Archiviert</option>
            </select>
          </div>
          <?php if (!empty($companies)): ?>
          <div>
            <label for="projectCompany" class="block text-sm font-medium text-gray-700 dark:text-primary-210">Firma</label>
            <select id="projectCompany" name="company_id" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
              <option value="">— Keine —</option>
              <?php foreach ($companies as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div>
            <label for="projectBeauftragter" class="block text-sm font-medium text-gray-700 dark:text-primary-210">Beauftragter</label>
            <select id="projectBeauftragter" name="beauftragter_user_id" class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-primary-120 dark:bg-primary-50 dark:text-primary-200 px-3 py-2 text-sm">
              <option value="">— Keiner —</option>
            </select>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" id="projectModalCancel" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
          <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-700 dark:bg-primary-420 rounded-lg hover:bg-primary-800 dark:hover:bg-primary-440">Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const projectsBaseUrl = '<?php echo $baseUrl; ?>';
const projectsApiUrl = projectsBaseUrl + '/projects/api/projects.php';
const KANBAN_STATUSES = ['Neu', 'In Planung', 'In Bearbeitung', 'Wartend', 'Abgeschlossen', 'Archiviert'];
let projects = [];
let projectContextProject = null;
let betreffModalProjectId = null;

// Linke Border der Karte je nach Status (angelehnt an Ticket-Status-Badges)
const statusBorderClasses = {
  'Neu': 'border-l-yellow-500 dark:border-l-yellow-400',
  'In Planung': 'border-l-sky-500 dark:border-l-sky-400',
  'In Bearbeitung': 'border-l-blue-500 dark:border-l-blue-400',
  'Wartend': 'border-l-orange-500 dark:border-l-orange-400',
  'Abgeschlossen': 'border-l-green-500 dark:border-l-green-400',
  'Archiviert': 'border-l-gray-400 dark:border-l-gray-500'
};

function getCompanyId() {
  try {
    const saved = localStorage.getItem('selectedUserOption');
    if (saved) {
      const data = JSON.parse(saved);
      return data.id && data.id !== '0' ? data.id : '';
    }
  } catch (e) {}
  return '';
}

function loadProjects() {
  const search = document.getElementById('project-search').value.trim();
  const companyId = getCompanyId();
  let url = projectsApiUrl + '?';
  if (search) url += 'search=' + encodeURIComponent(search) + '&';
  if (companyId) url += 'company_id=' + encodeURIComponent(companyId) + '&';

  fetch(url)
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        projects = d.projects || [];
        renderKanban();
      }
    })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Laden der Projekte', 'error'); });
}

function escapeHtml(t) {
  if (!t) return '';
  const d = document.createElement('div');
  d.textContent = t;
  return d.innerHTML;
}

function projectCardHtml(p) {
  const firma = p.company_name || p.customer_name || '—';
  const beauftragter = (p.beauftragter_vorname || p.beauftragter_nachname)
    ? [p.beauftragter_vorname, p.beauftragter_nachname].filter(Boolean).join(' ').trim()
    : '—';
  const borderClass = statusBorderClasses[p.status] || statusBorderClasses['Neu'];
  const iconBuilding = '<svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-primary-240" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>';
  const iconUser = '<svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-primary-240" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';
  return `
    <div class="project-card flex-shrink-0 p-3.5 bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 hover:shadow-lg hover:border-primary-300 dark:hover:border-primary-200 transition-all cursor-grab active:cursor-grabbing select-none border-l-4 ${borderClass}"
         data-project-id="${p.id}"
         draggable="true">
      <div class="min-w-0">
        <div class="font-semibold text-sm text-gray-900 dark:text-primary-200 break-words leading-snug">${escapeHtml(p.bezeichnung)}</div>
        ${p.project_nummer ? `<div class="text-xs font-mono text-primary-600 dark:text-primary-250 mt-0.5">${escapeHtml(p.project_nummer)}</div>` : ''}
        <div class="flex items-center gap-2 mt-2.5 text-xs text-gray-600 dark:text-primary-220">
          <span class="flex items-center gap-1.5 min-w-0">
            ${iconBuilding}
            <span class="truncate" title="${escapeHtml(firma)}">${escapeHtml(firma)}</span>
          </span>
        </div>
        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-primary-240">
          <span class="flex items-center gap-1.5 min-w-0">
            ${iconUser}
            <span class="truncate" title="${escapeHtml(beauftragter)}">${escapeHtml(beauftragter)}</span>
          </span>
        </div>
      </div>
    </div>`;
}

function renderKanban() {
  const container = document.getElementById('kanbanColumns');
  if (!container) return;
  container.innerHTML = KANBAN_STATUSES.map((status, index) => {
    const items = projects.filter(p => p.status === status);
    const colId = 'kanban-col-' + status.replace(/\s+/g, '-');
    const colHtml = `
    <div class="kanban-column flex-shrink-0 w-72 flex flex-col overflow-hidden"
         data-status="${escapeHtml(status)}"
         id="${colId}">
      <div class="flex justify-between items-center px-2 py-1.5 flex-shrink-0 bg-gray-100/80 dark:bg-primary-140/80 rounded-t-lg border-b border-gray-300 dark:border-primary-120">
        <h4 class="font-semibold text-sm text-gray-900 dark:text-primary-200">${escapeHtml(status)}</h4>
        <span class="text-xs text-gray-500 dark:text-primary-210">${items.length}</span>
      </div>
      <div class="kanban-col-cards flex-1 overflow-y-auto overflow-x-hidden px-2 pt-2 pb-6 space-y-2 min-h-[120px]"
           data-status="${escapeHtml(status)}">
        ${items.map(p => projectCardHtml(p)).join('')}
      </div>
    </div>`;
    const sepHtml = index < KANBAN_STATUSES.length - 1
      ? '<div class="kanban-col-sep flex-shrink-0 w-3 flex items-stretch justify-center pointer-events-none"><div class="w-px bg-gray-300 dark:bg-primary-120"></div></div>'
      : '';
    return colHtml + sepHtml;
  }).join('');

  bindKanbanDragDrop();
  bindProjectCardsContextMenu();
}

function bindKanbanDragDrop() {
  const cards = document.querySelectorAll('.project-card');
  cards.forEach(card => {
    card.addEventListener('dragstart', onCardDragStart);
    card.addEventListener('dragend', onCardDragEnd);
  });
  const colCards = document.querySelectorAll('.kanban-col-cards');
  colCards.forEach(zone => {
    zone.addEventListener('dragover', onColDragover);
    zone.addEventListener('dragleave', onColDragleave);
    zone.addEventListener('drop', onColDrop);
  });
}

let draggedCard = null;
let dragOverCol = null;
let draggedSourceStatus = null;

function onCardDragStart(e) {
  const card = e.target.closest('.project-card');
  if (!card) return;
  const zone = card.closest('.kanban-col-cards');
  draggedCard = card;
  draggedSourceStatus = zone ? zone.dataset.status : null;
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', card.dataset.projectId);
  e.dataTransfer.setData('application/json', JSON.stringify({ projectId: card.dataset.projectId }));
  card.classList.add('opacity-50', 'ring-2', 'ring-primary-500');
}

function onCardDragEnd(e) {
  const card = e.target.closest('.project-card');
  if (card) card.classList.remove('opacity-50', 'ring-2', 'ring-primary-500');
  draggedCard = null;
  draggedSourceStatus = null;
  document.querySelectorAll('.kanban-col-cards').forEach(z => z.classList.remove('ring-2', 'ring-primary-400', 'bg-primary-50', 'dark:bg-primary-200/30'));
}

function onColDragover(e) {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  const zone = e.target.closest('.kanban-col-cards');
  if (!zone || !draggedCard) return;
  document.querySelectorAll('.kanban-col-cards').forEach(z => z.classList.remove('ring-2', 'ring-primary-400', 'bg-primary-50', 'dark:bg-primary-200/30'));
  zone.classList.add('ring-2', 'ring-primary-400', 'bg-primary-50', 'dark:bg-primary-200/30');
  dragOverCol = zone;
}

function onColDragleave(e) {
  const zone = e.target.closest('.kanban-col-cards');
  if (zone && !zone.contains(e.relatedTarget)) {
    zone.classList.remove('ring-2', 'ring-primary-400', 'bg-primary-50', 'dark:bg-primary-200/30');
    if (dragOverCol === zone) dragOverCol = null;
  }
}

function onColDrop(e) {
  e.preventDefault();
  const zone = e.target.closest('.kanban-col-cards');
  if (!zone) return;
  zone.classList.remove('ring-2', 'ring-primary-400', 'bg-primary-50', 'dark:bg-primary-200/30');
  dragOverCol = null;
  const projectId = e.dataTransfer.getData('text/plain');
  const newStatus = zone.dataset.status;
  if (!projectId || !newStatus) return;
  const p = projects.find(pr => String(pr.id) === String(projectId));
  if (!p) return;

  if (p.status === newStatus) {
    reorderProjectsInColumn(zone, projectId, newStatus, e.target);
  } else {
    updateProjectStatus(parseInt(projectId), newStatus, p);
  }
}

function reorderProjectsInColumn(zone, projectId, status, dropTarget) {
  const cards = Array.from(zone.querySelectorAll('.project-card'));
  const ids = cards.map(c => c.dataset.projectId);
  const others = ids.filter(id => id !== String(projectId));
  const dropCard = dropTarget.closest('.project-card');
  let insertAt = others.length;
  if (dropCard && dropCard.dataset.projectId !== String(projectId)) {
    const idx = others.indexOf(dropCard.dataset.projectId);
    if (idx >= 0) insertAt = idx;
  }
  const newOrder = [...others.slice(0, insertAt), projectId, ...others.slice(insertAt)].map(id => parseInt(id));

  fetch(projectsApiUrl, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'reorder', status, project_ids: newOrder })
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        loadProjects();
        if (typeof showToast === 'function') showToast('Reihenfolge aktualisiert', 'success');
      } else if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
    })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error'); });
}

function updateProjectStatus(projectId, newStatus, project) {
  const p = project || projects.find(pr => pr.id == projectId);
  if (!p) return;
  const body = {
    id: parseInt(projectId),
    bezeichnung: p.bezeichnung || '',
    beschreibung: p.beschreibung || null,
    status: newStatus,
    company_id: p.company_id || null,
    customer_id: p.customer_id || null,
    beauftragter_user_id: p.beauftragter_user_id || null
  };
  fetch(projectsApiUrl, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        p.status = newStatus;
        renderKanban();
        if (typeof showToast === 'function') showToast('Status aktualisiert', 'success');
      } else if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
    })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error'); });
}

function bindProjectCardsContextMenu() {
  document.querySelectorAll('.project-card').forEach(card => {
    card.addEventListener('contextmenu', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const id = parseInt(card.dataset.projectId);
      const p = projects.find(pr => pr.id == id);
      if (p) showProjectContextMenu(e.clientX, e.clientY, p);
    });
    card.addEventListener('click', function(e) {
      if (e.defaultPrevented || e.target.closest('button')) return;
      window.location.href = projectsBaseUrl + '/projects/view.php?id=' + card.dataset.projectId;
    });
  });
}

function showProjectContextMenu(clientX, clientY, project) {
  projectContextProject = project;
  const menu = document.getElementById('projectContextMenu');
  const submenu = document.getElementById('projectCtxStatusSubmenu');
  if (!menu || !submenu) return;
  submenu.innerHTML = KANBAN_STATUSES.map(s => {
    const active = project.status === s ? ' bg-primary-100 dark:bg-primary-200' : '';
    return `<button type="button" data-project-ctx="status" data-status="${escapeHtml(s)}" class="w-full px-3 py-1.5 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2${active}">${escapeHtml(s)}</button>`;
  }).join('');
  menu.classList.remove('hidden');
  let left = clientX;
  let top = clientY;
  const viewportPadding = 8;
  const rect = menu.getBoundingClientRect();
  if (rect.right > window.innerWidth - viewportPadding) {
    left = Math.max(viewportPadding, left - (rect.right - window.innerWidth + viewportPadding));
  }
  if (rect.bottom > window.innerHeight - viewportPadding) {
    top = Math.max(viewportPadding, top - (rect.bottom - window.innerHeight + viewportPadding));
  }
  if (rect.left < viewportPadding) left = viewportPadding;
  if (rect.top < viewportPadding) top = viewportPadding;
  menu.style.left = left + 'px';
  menu.style.top = top + 'px';
  const statusSection = document.getElementById('projectCtxStatusSection');
  const statusTrigger = document.getElementById('projectCtxStatusTrigger');
  let submenuHide = null;
  if (statusSection && statusTrigger) {
    statusSection.onmouseenter = function() {
      if (submenuHide) clearTimeout(submenuHide);
      submenu.classList.remove('hidden');
    };
    statusSection.onmouseleave = function() {
      submenuHide = setTimeout(() => submenu.classList.add('hidden'), 150);
    };
  }
}

function hideProjectContextMenu() {
  projectContextProject = null;
  const menu = document.getElementById('projectContextMenu');
  const sub = document.getElementById('projectCtxStatusSubmenu');
  if (menu) menu.classList.add('hidden');
  if (sub) sub.classList.add('hidden');
}

function handleProjectContextMenuClick(e) {
  const btn = e.target.closest('[data-project-ctx]');
  if (!btn || !projectContextProject) return;
  const action = btn.dataset.projectCtx;
  const projectId = parseInt(projectContextProject.id);
  if (action === 'open-new-tab') {
    window.open(projectsBaseUrl + '/projects/view.php?id=' + projectId, '_blank', 'noopener');
  } else if (action === 'status') {
    const newStatus = btn.dataset.status;
    if (newStatus) updateProjectStatus(projectId, newStatus, projectContextProject);
  } else if (action === 'edit-betreff') {
    openBetreffModal(projectContextProject);
  } else if (action === 'soft-delete') {
    softDeleteProject(projectId);
  }
  hideProjectContextMenu();
}

function openBetreffModal(project) {
  betreffModalProjectId = project.id;
  document.getElementById('betreffInput').value = project.bezeichnung || '';
  document.getElementById('betreffModal').classList.remove('hidden');
  document.getElementById('betreffInput').focus();
}

function closeBetreffModal() {
  betreffModalProjectId = null;
  document.getElementById('betreffModal').classList.add('hidden');
}

function saveBetreff() {
  const id = betreffModalProjectId;
  const newBezeichnung = document.getElementById('betreffInput').value.trim();
  if (!id || !newBezeichnung) {
    closeBetreffModal();
    return;
  }
  const p = projects.find(pr => pr.id == id);
  if (!p) { closeBetreffModal(); return; }
  const body = {
    id: parseInt(id),
    bezeichnung: newBezeichnung,
    beschreibung: p.beschreibung || null,
    status: p.status || 'Neu',
    company_id: p.company_id || null,
    customer_id: p.customer_id || null,
    beauftragter_user_id: p.beauftragter_user_id || null
  };
  fetch(projectsApiUrl, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        p.bezeichnung = newBezeichnung;
        renderKanban();
        closeBetreffModal();
        if (typeof showToast === 'function') showToast('Betreff gespeichert', 'success');
      } else if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
    })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error'); });
}

function softDeleteProject(projectId) {
  if (!confirm('Möchten Sie dieses Projekt wirklich löschen?')) return;
  fetch(projectsApiUrl + '?id=' + projectId, { method: 'DELETE' })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        projects = projects.filter(p => p.id != projectId);
        renderKanban();
        if (typeof showToast === 'function') showToast('Projekt wurde gelöscht', 'success');
      } else if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
    })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Löschen', 'error'); });
}

function loadAssignableUsers() {
  fetch(projectsApiUrl + '?action=assignable_users')
    .then(r => r.json())
    .then(d => {
      if (d.success && d.users) {
        const sel = document.getElementById('projectBeauftragter');
        if (!sel) return;
        const opts = sel.querySelectorAll('option:not([value=""])');
        opts.forEach(o => o.remove());
        d.users.forEach(u => {
          const opt = document.createElement('option');
          opt.value = u.id;
          opt.textContent = (u.vorname + ' ' + u.nachname).trim() || u.email || 'User ' + u.id;
          sel.appendChild(opt);
        });
      }
    });
}

function openCreateModal() {
  document.getElementById('projectModalTitle').textContent = 'Projekt anlegen';
  document.getElementById('projectForm').reset();
  document.getElementById('projectId').value = '';
  document.getElementById('projectModal').classList.remove('hidden');
}

function openEditModal(p) {
  document.getElementById('projectModalTitle').textContent = 'Projekt bearbeiten';
  document.getElementById('projectId').value = p.id;
  document.getElementById('projectBezeichnung').value = p.bezeichnung || '';
  document.getElementById('projectBeschreibung').value = p.beschreibung || '';
  document.getElementById('projectStatus').value = p.status || 'Neu';
  if (document.getElementById('projectCompany')) document.getElementById('projectCompany').value = p.company_id || '';
  if (document.getElementById('projectBeauftragter')) document.getElementById('projectBeauftragter').value = p.beauftragter_user_id || '';
  document.getElementById('projectModal').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
  loadAssignableUsers();
  loadProjects();

  document.getElementById('project-search').addEventListener('input', loadProjects);
  document.getElementById('project-search').addEventListener('keyup', function(e) { if (e.key === 'Enter') loadProjects(); });
  window.addEventListener('storage', function(e) {
    if (e.key === 'selectedUserOption') loadProjects();
  });

  document.getElementById('createProjectBtn').addEventListener('click', openCreateModal);
  document.getElementById('projectModalOverlay').addEventListener('click', function() {
    document.getElementById('projectModal').classList.add('hidden');
  });
  document.getElementById('projectModalCancel').addEventListener('click', function() {
    document.getElementById('projectModal').classList.add('hidden');
  });

  const projectContextMenuEl = document.getElementById('projectContextMenu');
  if (projectContextMenuEl) {
    projectContextMenuEl.addEventListener('click', function(e) {
      e.stopPropagation();
      handleProjectContextMenuClick(e);
    });
  }
  document.addEventListener('contextmenu', function(e) {
    const menu = document.getElementById('projectContextMenu');
    if (!menu) return;
    const onProjectCard = e.target.closest('.project-card');
    const insideMenu = e.target.closest('#projectContextMenu');
    if (!onProjectCard && !insideMenu) hideProjectContextMenu();
  });
  document.addEventListener('click', function(e) {
    const menu = document.getElementById('projectContextMenu');
    if (!menu) return;
    if (!menu.contains(e.target)) hideProjectContextMenu();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      hideProjectContextMenu();
      if (document.getElementById('betreffModal') && !document.getElementById('betreffModal').classList.contains('hidden')) closeBetreffModal();
    }
  });

  document.getElementById('betreffModalOverlay').addEventListener('click', closeBetreffModal);
  document.getElementById('betreffModalCancel').addEventListener('click', closeBetreffModal);
  document.getElementById('betreffModalSave').addEventListener('click', saveBetreff);
  document.getElementById('betreffInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); saveBetreff(); }
  });

  document.getElementById('projectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const id = document.getElementById('projectId').value;
    const data = {
      bezeichnung: document.getElementById('projectBezeichnung').value.trim(),
      beschreibung: document.getElementById('projectBeschreibung').value.trim(),
      status: document.getElementById('projectStatus').value,
      company_id: document.getElementById('projectCompany') ? document.getElementById('projectCompany').value || null : null,
      beauftragter_user_id: document.getElementById('projectBeauftragter').value || null
    };
    if (!data.bezeichnung) {
      alert('Bitte Bezeichnung eingeben.');
      return;
    }
    const method = id ? 'PUT' : 'POST';
    const body = id ? { ...data, id: parseInt(id) } : data;

    fetch(projectsApiUrl, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          document.getElementById('projectModal').classList.add('hidden');
          if (d.id) window.location.href = projectsBaseUrl + '/projects/view.php?id=' + d.id;
          else loadProjects();
        } else {
          alert(d.error || 'Fehler beim Speichern');
        }
      })
      .catch(() => alert('Fehler beim Speichern'));
  });
});
</script>
