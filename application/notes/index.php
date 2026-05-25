<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        showPermissionDeniedPage();
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Datenbankfehler.');
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
<style>
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
#folderFilters { flex-shrink: 0; }
#folderFilters button { flex-shrink: 0; padding-top: 0.375rem; padding-bottom: 0.375rem; height: 2.25rem; }
.folder-filters-wrapper { position: relative; }
.folder-filters-wrapper::before, .folder-filters-wrapper::after {
  content: ''; position: absolute; top: 0; bottom: 0; width: 40px; pointer-events: none; z-index: 10; transition: opacity 0.3s ease; opacity: 0;
}
.folder-filters-wrapper::before { left: 0; background: linear-gradient(to right, rgba(249, 250, 251, 0.95), transparent); }
.dark .folder-filters-wrapper::before { background: linear-gradient(to right, rgba(17, 24, 39, 0.95), transparent); }
.folder-filters-wrapper::after { right: 0; background: linear-gradient(to left, rgba(249, 250, 251, 0.95), transparent); }
.dark .folder-filters-wrapper::after { background: linear-gradient(to left, rgba(17, 24, 39, 0.95), transparent); }
.folder-filters-wrapper.has-scroll-left::before { opacity: 1; }
.folder-filters-wrapper.has-scroll-right::after { opacity: 1; }
.folder-filters-scroll { cursor: grab; user-select: none; -webkit-user-select: none; -ms-overflow-style: none; scrollbar-width: none; }
.folder-filters-scroll:active { cursor: grabbing; }
.note-card { transition: box-shadow 0.2s, transform 0.2s; }
.note-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); }
.dark .note-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
.note-card.dragging { opacity: 0.6; transform: scale(0.98); }
.note-card.drag-over { border-color: var(--primary-500, #3b82f6); box-shadow: 0 0 0 2px rgba(59,130,246,0.3); }
.notes-grid { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 1rem; }
@media (min-width: 640px) { .notes-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 1024px) { .notes-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
#notes-search-wrapper.search-active input { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25); }
.dark #notes-search-wrapper.search-active input { border-color: #60a5fa; box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2); }
</style>

<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-hidden">
  <main class="pt-4 pr-4 pb-4 pl-1 flex flex-col overflow-hidden">
    <nav class="mb-4 flex-shrink-0" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
            <svg class="me-2.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd"/></svg>
            Startseite
          </a>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Notizen</span>
          </div>
        </li>
      </ol>
    </nav>

    <!-- Eine Zeile: Suche + Systemordner Kunden + Firmen + meine Ordner (wie Aufgaben-Seite) -->
    <div class="relative flex flex-col flex-1 min-h-0">
      <div class="flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0 md:gap-4">
        <div class="flex flex-col w-full space-y-3 md:space-y-0 md:flex-row md:items-center md:flex-1 md:min-w-0">
          <form class="flex-1 w-full md:max-w-sm md:mr-2" id="notes-search-form" role="search">
            <label for="notes-search" class="text-sm font-medium text-gray-900 sr-only dark:text-white">Suche</label>
            <div class="relative" id="notes-search-wrapper">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              </div>
              <input type="search" id="notes-search" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-820 focus:border-primary-820 dark:bg-primary-300 dark:border-primary-320 dark:placeholder-primary-210 dark:text-primary-200 dark:focus:ring-primary-820 dark:focus:border-primary-820 transition-colors" placeholder="Suchen..." autocomplete="off">
            </div>
          </form>
          <div class="folder-filters-wrapper flex items-center min-w-0 flex-1">
            <div id="folderFiltersScroll" class="folder-filters-scroll flex items-center overflow-x-auto scrollbar-hide min-w-0 flex-1" style="-webkit-overflow-scrolling: touch;">
              <div id="folderFilters" class="flex items-center gap-2 flex-nowrap">
                <!-- Kunden, Firmen + Benutzerordner werden hier dynamisch eingefügt -->
              </div>
            </div>
          </div>
        </div>
        <div class="flex flex-col items-stretch justify-end flex-shrink-0 w-full md:w-auto md:flex-row md:items-center md:gap-2">
          <button type="button" id="resetNotesSearchBtn" class="inline-flex items-center justify-center p-2 text-sm font-medium text-gray-600 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140 focus:outline-none" title="Suche zurücksetzen">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          </button>
          <button type="button" id="createFolderBtn" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 focus:outline-none dark:bg-primary-420 dark:hover:bg-primary-440 dark:focus:ring-primary-800">
            <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path fill-rule="evenodd" d="M5 4a2 2 0 0 0-2 2v1h10.968l-1.9-2.28A2 2 0 0 0 10.532 4H5ZM3 19V9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm9-8.5a1 1 0 0 1 1 1V13h1.5a1 1 0 1 1 0 2H13v1.5a1 1 0 1 1-2 0V15H9.5a1 1 0 1 1 0-2H11v-1.5a1 1 0 0 1 1-1Z" clip-rule="evenodd"/>
            </svg>
            Ordner erstellen
          </button>
          <button type="button" id="createNoteBtn" class="hidden flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 focus:outline-none dark:bg-primary-420 dark:hover:bg-primary-440 dark:focus:ring-primary-800">
            <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Neue Notiz
          </button>
        </div>
      </div>
      <div class="flex flex-wrap items-center justify-between pt-1 pb-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex flex-wrap items-center"></div>
        <div class="mt-3" id="deleteFolderContainer" style="display: none;">
          <button type="button" id="editFolderBtn" class="text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-primary-210 dark:hover:text-primary-200 mr-4">Ordner bearbeiten</button>
          <button type="button" id="deleteFolderBtn" class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
            <i class="fas fa-trash mr-1"></i>Ordner löschen
          </button>
        </div>
      </div>

      <!-- Inhalt: Kunden / Firmen / Meine Notizen -->
      <div id="contentKunden" class="hidden flex-1 overflow-y-auto pb-8">
        <div id="notesGridKunden" class="notes-grid"></div>
        <p id="kundenEmpty" class="text-center text-gray-500 dark:text-gray-400 py-12 hidden">Keine Kundennotizen.</p>
      </div>
      <div id="contentFirmen" class="hidden flex-1 overflow-y-auto pb-8">
        <div id="notesGridFirmen" class="notes-grid"></div>
        <p id="firmenEmpty" class="text-center text-gray-500 dark:text-gray-400 py-12 hidden">Keine Firmennotizen.</p>
      </div>
      <div id="contentPrivat" class="hidden flex-1 overflow-y-auto pb-8">
        <div id="notesGridPrivat" class="notes-grid"></div>
        <p id="privatEmpty" class="text-center text-gray-500 dark:text-gray-400 py-12">Ordner auswählen oder erstellen.</p>
      </div>
    </div>
  </main>
</div>

<!-- Modal Ordner -->
<div id="folderModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4">
  <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" id="folderModalOverlay"></div>
  <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
    <div class="pointer-events-auto w-full max-w-lg bg-white dark:bg-primary-100 rounded-xl shadow-xl border border-gray-200 dark:border-primary-120 p-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="folderModalTitle">Neuer Ordner</h3>
        <button type="button" id="closeFolderModalBtn" class="text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140">&times;</button>
      </div>
      <form id="folderForm">
        <input type="hidden" id="folder_id" name="folder_id">
        <div class="mb-4">
          <label for="folderName" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Ordnername *</label>
          <input type="text" name="folderName" id="folderName" class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" required>
        </div>
        <div class="mb-4">
          <span class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Mit Technikern/Admins teilen</span>
          <input type="text" id="folderMemberSearch" placeholder="Suchen..." class="w-full px-4 py-2 border border-gray-300 dark:border-primary-320 rounded-lg mb-2">
          <div id="folderCandidatesList" class="max-h-40 overflow-y-auto border border-gray-200 dark:border-primary-120 rounded-lg divide-y"></div>
        </div>
        <button type="submit" class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">Speichern</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal Notiz -->
<div id="noteModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4">
  <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" id="noteModalOverlay"></div>
  <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
    <div class="pointer-events-auto w-full max-w-2xl bg-white dark:bg-primary-100 rounded-xl shadow-xl border border-gray-200 dark:border-primary-120 p-6 max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="noteModalTitle">Neue Notiz</h3>
        <button type="button" id="closeNoteModalBtn" class="text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140">&times;</button>
      </div>
      <form id="noteForm">
        <input type="hidden" id="note_id" name="note_id">
        <div class="mb-4">
          <label for="noteTitel" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Titel *</label>
          <input type="text" name="noteTitel" id="noteTitel" class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" required>
        </div>
        <div class="mb-4">
          <label for="noteInhalt" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Inhalt</label>
          <textarea name="noteInhalt" id="noteInhalt" rows="10" class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"></textarea>
        </div>
        <div class="flex items-center justify-between gap-2">
          <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">Speichern</button>
          <button type="button" id="deleteNoteBtn" class="px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hidden">Löschen</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const notesBaseUrl = <?php echo json_encode(BASE_URL); ?>;
const notesApiUrl = notesBaseUrl + 'notes/api/notes.php';
const foldersApiUrl = notesBaseUrl + 'notes/api/folders.php';

let selectedFolderId = null; // 'kunden' | 'firmen' | number (user folder id) | null
let currentFolderId = null;  // when selectedFolderId is a number, equals that id
let folders = [];
let notesSearchTerm = '';
let notesSearchTimeout = null;
let notes = [];
let companyNotes = [];
let customerNotes = [];
let folderCandidates = [];
let folderSelectedMemberIds = [];
let draggedNoteId = null;

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function showToast(message, type) {
  if (typeof window.showToast === 'function') window.showToast(message, type || 'success');
}

function getSelectedCompanyId() {
  try {
    const saved = localStorage.getItem('selectedUserOption');
    if (!saved) return null;
    const data = JSON.parse(saved);
    if (data && data.id && data.id !== '0') return parseInt(data.id, 10);
  } catch (e) { }
  return null;
}

function applyNotesSearchAndSort(list, type) {
  let out = list;
  const q = notesSearchTerm.trim().toLowerCase();
  if (q) {
    if (type === 'company') out = out.filter(n => (n.titel || '').toLowerCase().includes(q) || (n.inhalt || '').toLowerCase().includes(q) || (n.company_name || '').toLowerCase().includes(q));
    else if (type === 'customer') out = out.filter(n => (n.titel || '').toLowerCase().includes(q) || (n.inhalt || '').toLowerCase().includes(q) || (n.customer_name || '').toLowerCase().includes(q) || (n.company_name || '').toLowerCase().includes(q));
    else out = out.filter(n => (n.titel || '').toLowerCase().includes(q) || (n.inhalt || '').toLowerCase().includes(q));
  }
  const companyId = getSelectedCompanyId();
  if (companyId != null && (type === 'company' || type === 'customer')) {
    out = [...out].sort((a, b) => {
      const aMatch = (type === 'company' ? a.company_id : a.company_id) == companyId;
      const bMatch = (type === 'company' ? b.company_id : b.company_id) == companyId;
      if (aMatch && !bMatch) return -1;
      if (!aMatch && bMatch) return 1;
      const dateA = new Date(a.geaendert_datum || a.erstellt_datum || 0).getTime();
      const dateB = new Date(b.geaendert_datum || b.erstellt_datum || 0).getTime();
      return dateB - dateA;
    });
  }
  return out;
}

function setSelectedFolder(id) {
  const idNum = id === 'kunden' || id === 'firmen' ? id : (id != null && id !== '' ? parseInt(id, 10) : null);
  selectedFolderId = idNum;
  currentFolderId = (typeof idNum === 'number') ? idNum : null;

  document.getElementById('contentKunden').classList.add('hidden');
  document.getElementById('contentFirmen').classList.add('hidden');
  document.getElementById('contentPrivat').classList.add('hidden');

  if (selectedFolderId === 'kunden') {
    document.getElementById('contentKunden').classList.remove('hidden');
    loadCustomerNotes();
  } else if (selectedFolderId === 'firmen') {
    document.getElementById('contentFirmen').classList.remove('hidden');
    loadCompanyNotes();
  } else if (typeof selectedFolderId === 'number') {
    document.getElementById('contentPrivat').classList.remove('hidden');
    loadNotes();
  } else {
    document.getElementById('contentPrivat').classList.remove('hidden');
    renderPrivatEmpty();
  }

  const deleteFolderContainer = document.getElementById('deleteFolderContainer');
  if (deleteFolderContainer) deleteFolderContainer.style.display = (typeof selectedFolderId === 'number') ? 'block' : 'none';
  document.getElementById('createNoteBtn').classList.toggle('hidden', typeof selectedFolderId !== 'number');
  renderAllFolderButtons();
}

function loadCompanyNotes() {
  fetch(notesApiUrl + '?action=company_customer')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      companyNotes = data.company_notes || [];
      renderCompanyCards();
    })
    .catch(() => {});
}

function loadCustomerNotes() {
  fetch(notesApiUrl + '?action=company_customer')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      customerNotes = data.customer_notes || [];
      renderCustomerCards();
    })
    .catch(() => {});
}

function renderCompanyCards() {
  const grid = document.getElementById('notesGridFirmen');
  const empty = document.getElementById('firmenEmpty');
  const list = applyNotesSearchAndSort(companyNotes, 'company');
  if (list.length === 0) {
    grid.innerHTML = '';
    empty.textContent = companyNotes.length === 0 ? 'Keine Firmennotizen.' : 'Keine Treffer für die Suche.';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  grid.innerHTML = list.map(n => {
    const date = n.geaendert_datum || n.erstellt_datum;
    const content = (n.inhalt || '').trim();
    return `
      <article class="note-card bg-white dark:bg-primary-100 rounded-xl border border-gray-200 dark:border-primary-120 shadow-sm overflow-hidden flex flex-col">
        <div class="p-4 flex-1 flex flex-col min-h-0">
          <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">${escapeHtml(n.titel)}</h3>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Firma: ${escapeHtml(n.company_name || '')}</p>
          <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap flex-1 max-h-40 overflow-y-auto">${escapeHtml(content) || '<span class="text-gray-400">Kein Inhalt</span>'}</div>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">${date ? new Date(date).toLocaleString('de-DE') : ''}</p>
        </div>
        <div class="px-4 py-2 bg-gray-50 dark:bg-primary-140 border-t border-gray-100 dark:border-primary-120">
          <a href="${notesBaseUrl + n.detail_url}" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Firma öffnen →</a>
        </div>
      </article>
    `;
  }).join('');
}

function renderCustomerCards() {
  const grid = document.getElementById('notesGridKunden');
  const empty = document.getElementById('kundenEmpty');
  const list = applyNotesSearchAndSort(customerNotes, 'customer');
  if (list.length === 0) {
    grid.innerHTML = '';
    empty.textContent = customerNotes.length === 0 ? 'Keine Kundennotizen.' : 'Keine Treffer für die Suche.';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  grid.innerHTML = list.map(n => {
    const date = n.geaendert_datum || n.erstellt_datum;
    const content = (n.inhalt || '').trim();
    const sub = 'Kunde: ' + (n.customer_name || '') + (n.company_name ? ' (' + n.company_name + ')' : '');
    return `
      <article class="note-card bg-white dark:bg-primary-100 rounded-xl border border-gray-200 dark:border-primary-120 shadow-sm overflow-hidden flex flex-col">
        <div class="p-4 flex-1 flex flex-col min-h-0">
          <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">${escapeHtml(n.titel)}</h3>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">${escapeHtml(sub)}</p>
          <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap flex-1 max-h-40 overflow-y-auto">${escapeHtml(content) || '<span class="text-gray-400">Kein Inhalt</span>'}</div>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">${date ? new Date(date).toLocaleString('de-DE') : ''}</p>
        </div>
        <div class="px-4 py-2 bg-gray-50 dark:bg-primary-140 border-t border-gray-100 dark:border-primary-120">
          <a href="${notesBaseUrl + n.detail_url}" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Kunde öffnen →</a>
        </div>
      </article>
    `;
  }).join('');
}

function loadFolders() {
  fetch(foldersApiUrl)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        folders = data.folders || [];
        renderAllFolderButtons();
        if (typeof selectedFolderId === 'number' && !folders.find(f => f.id === selectedFolderId)) setSelectedFolder(null);
        else if (selectedFolderId === 'kunden') loadCustomerNotes();
        else if (selectedFolderId === 'firmen') loadCompanyNotes();
        else if (typeof selectedFolderId === 'number') loadNotes();
      }
    })
    .catch(() => {});
}

function renderAllFolderButtons() {
  const folderFiltersContainer = document.getElementById('folderFilters');
  if (!folderFiltersContainer) return;
  folderFiltersContainer.innerHTML = '';

  const baseClass = 'flex items-center px-4 py-1.5 text-sm font-medium rounded-lg border border-gray-300 transition-colors';
  const activeClass = 'bg-primary-820 text-white border-primary-700 dark:text-primary-840 dark:bg-primary-800 dark:border-primary-820';
  const inactiveClass = 'bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:border-primary-720 dark:text-primary-210 dark:hover:bg-primary-760';

  ['kunden', 'firmen'].forEach(sid => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = baseClass + ' ' + (selectedFolderId === sid ? activeClass : inactiveClass);
    btn.dataset.folderId = sid;
    btn.textContent = sid === 'kunden' ? 'Kunden' : 'Firmen';
    btn.onclick = function() {
      setSelectedFolder(selectedFolderId === this.dataset.folderId ? null : this.dataset.folderId);
    };
    btn.addEventListener('mousedown', function(e) { e.stopPropagation(); });
    folderFiltersContainer.appendChild(btn);
  });

  folders.forEach(folder => {
    const button = document.createElement('button');
    button.type = 'button';
    const isActive = selectedFolderId === folder.id;
    button.className = baseClass + ' ' + (isActive ? activeClass : inactiveClass);
    button.dataset.folderId = String(folder.id);
    button.textContent = `${folder.name} (${folder.note_count || 0})`;
    button.onclick = function() {
      const fid = parseInt(this.dataset.folderId, 10);
      setSelectedFolder(selectedFolderId === fid ? null : fid);
    };
    button.addEventListener('mousedown', function(e) { e.stopPropagation(); });
    folderFiltersContainer.appendChild(button);
  });

  updateNotesFolderScrollIndicators();
}

function updateNotesFolderScrollIndicators() {
  const wrapper = document.querySelector('.folder-filters-wrapper');
  const scrollEl = document.getElementById('folderFiltersScroll');
  if (!wrapper || !scrollEl) return;
  wrapper.classList.toggle('has-scroll-left', scrollEl.scrollLeft > 0);
  wrapper.classList.toggle('has-scroll-right', scrollEl.scrollLeft < scrollEl.scrollWidth - scrollEl.clientWidth - 1);
}

function renderPrivatEmpty() {
  document.getElementById('notesGridPrivat').innerHTML = '';
  document.getElementById('privatEmpty').textContent = (typeof selectedFolderId === 'number') ? 'Keine Notizen in diesem Ordner.' : 'Ordner auswählen oder erstellen.';
  document.getElementById('privatEmpty').classList.remove('hidden');
}

function loadNotes() {
  if (!currentFolderId) { renderPrivatEmpty(); return; }
  fetch(notesApiUrl + '?folder_id=' + currentFolderId)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        notes = data.notes || [];
        renderPrivatCards();
      }
    })
    .catch(() => { renderPrivatEmpty(); });
}

function renderPrivatCards() {
  const grid = document.getElementById('notesGridPrivat');
  const empty = document.getElementById('privatEmpty');
  const list = applyNotesSearchAndSort(notes, 'privat');
  if (list.length === 0) {
    grid.innerHTML = '';
    empty.textContent = notes.length === 0 ? 'Keine Notizen in diesem Ordner.' : 'Keine Treffer für die Suche.';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  grid.innerHTML = list.map(n => {
    const date = n.geaendert_datum || n.erstellt_datum;
    const content = (n.inhalt || '').trim();
    return `
      <article class="note-card bg-white dark:bg-primary-100 rounded-xl border border-gray-200 dark:border-primary-120 shadow-sm overflow-hidden flex flex-col cursor-grab active:cursor-grabbing" data-note-id="${n.id}" draggable="true">
        <div class="p-4 flex-1 flex flex-col min-h-0" onclick="event.target.closest('[data-note-id]') && !event.target.closest('.note-card-actions') && openNoteModal(${n.id})">
          <div class="flex items-start justify-between gap-2 mb-1">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white flex-1">${escapeHtml(n.titel)}</h3>
            <div class="note-card-actions flex-shrink-0 flex gap-1" onclick="event.stopPropagation()">
              <button type="button" onclick="openNoteModal(${n.id})" class="p-1.5 text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140" title="Bearbeiten"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
              <button type="button" onclick="event.stopPropagation(); deleteNoteConfirm(${n.id})" class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20" title="Löschen"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            </div>
          </div>
          <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap flex-1 max-h-40 overflow-y-auto">${escapeHtml(content) || '<span class="text-gray-400">Kein Inhalt</span>'}</div>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">${date ? new Date(date).toLocaleString('de-DE') : ''}</p>
        </div>
      </article>
    `;
  }).join('');
  initDragDrop();
}

function initDragDrop() {
  const grid = document.getElementById('notesGridPrivat');
  if (!grid) return;
  grid.querySelectorAll('[draggable="true"]').forEach(card => {
    card.addEventListener('dragstart', function(e) {
      draggedNoteId = this.dataset.noteId;
      this.classList.add('dragging');
      e.dataTransfer.setData('text/plain', this.dataset.noteId);
      e.dataTransfer.effectAllowed = 'move';
    });
    card.addEventListener('dragend', function() {
      this.classList.remove('dragging');
      grid.querySelectorAll('.note-card').forEach(c => c.classList.remove('drag-over'));
      draggedNoteId = null;
    });
    card.addEventListener('dragover', function(e) {
      e.preventDefault();
      if (draggedNoteId && this.dataset.noteId !== draggedNoteId) this.classList.add('drag-over');
      e.dataTransfer.dropEffect = 'move';
    });
    card.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
    card.addEventListener('drop', function(e) {
      e.preventDefault();
      this.classList.remove('drag-over');
      const targetId = parseInt(this.dataset.noteId);
      const sourceId = parseInt(draggedNoteId);
      if (!sourceId || sourceId === targetId) return;
      const idx = notes.findIndex(n => n.id === targetId);
      const fromIdx = notes.findIndex(n => n.id === sourceId);
      if (fromIdx === -1 || idx === -1) return;
      const note = notes.splice(fromIdx, 1)[0];
      notes.splice(idx, 0, note);
      const noteIds = notes.map(n => n.id);
      fetch(notesApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reorder', folder_id: currentFolderId, note_ids: noteIds })
      }).then(r => r.json()).then(data => {
        if (data.success) renderPrivatCards();
      });
    });
  });
}

function deleteNoteConfirm(noteId) {
  if (!confirm('Notiz wirklich löschen?')) return;
  fetch(notesApiUrl + '?id=' + noteId, { method: 'DELETE' })
    .then(r => r.json())
    .then(data => {
      if (data.success) { showToast('Notiz gelöscht'); loadNotes(); loadFolders(); }
      else showToast(data.error || 'Fehler', 'error');
    })
    .catch(() => showToast('Fehler', 'error'));
}

function openNoteModal(noteId) {
  const note = notes.find(n => n.id === noteId);
  if (!note) return;
  document.getElementById('noteModalTitle').textContent = 'Notiz bearbeiten';
  document.getElementById('note_id').value = note.id;
  document.getElementById('noteTitel').value = note.titel || '';
  document.getElementById('noteInhalt').value = note.inhalt || '';
  document.getElementById('deleteNoteBtn').classList.remove('hidden');
  document.getElementById('noteModal').classList.remove('hidden');
}

function closeNoteModal() {
  document.getElementById('note_id').value = '';
  document.getElementById('noteModalTitle').textContent = 'Neue Notiz';
  document.getElementById('noteTitel').value = '';
  document.getElementById('noteInhalt').value = '';
  document.getElementById('deleteNoteBtn').classList.add('hidden');
  document.getElementById('noteModal').classList.add('hidden');
}

document.getElementById('createNoteBtn').addEventListener('click', function() {
  document.getElementById('noteModalTitle').textContent = 'Neue Notiz';
  document.getElementById('note_id').value = '';
  document.getElementById('noteTitel').value = '';
  document.getElementById('noteInhalt').value = '';
  document.getElementById('deleteNoteBtn').classList.add('hidden');
  document.getElementById('noteModal').classList.remove('hidden');
});

document.getElementById('deleteNoteBtn').addEventListener('click', function() {
  const noteId = document.getElementById('note_id').value;
  if (!noteId) return;
  closeNoteModal();
  deleteNoteConfirm(parseInt(noteId));
});

document.getElementById('editFolderBtn').addEventListener('click', function() {
  if (typeof selectedFolderId === 'number') openFolderModal(selectedFolderId);
});

document.getElementById('deleteFolderBtn').addEventListener('click', function() {
  if (typeof selectedFolderId !== 'number' || !confirm('Ordner und alle Notizen darin wirklich löschen?')) return;
  fetch(foldersApiUrl + '?id=' + selectedFolderId, { method: 'DELETE' })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Ordner gelöscht');
        setSelectedFolder(null);
        loadFolders();
      } else showToast(data.error || 'Fehler', 'error');
    })
    .catch(() => showToast('Fehler', 'error'));
});

document.getElementById('closeNoteModalBtn').addEventListener('click', closeNoteModal);
document.getElementById('noteModalOverlay').addEventListener('click', closeNoteModal);

document.getElementById('noteForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const noteId = document.getElementById('note_id').value;
  const titel = document.getElementById('noteTitel').value.trim();
  const inhalt = document.getElementById('noteInhalt').value.trim();
  if (!titel) return;
  if (noteId) {
    fetch(notesApiUrl, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ note_id: parseInt(noteId), titel: titel, inhalt: inhalt })
    }).then(r => r.json()).then(data => {
      if (data.success) { showToast('Notiz gespeichert'); closeNoteModal(); loadNotes(); loadFolders(); }
      else showToast(data.error || 'Fehler', 'error');
    }).catch(() => showToast('Fehler', 'error'));
  } else {
    if (typeof selectedFolderId !== 'number') { showToast('Bitte zuerst einen Ordner wählen.', 'error'); return; }
    fetch(notesApiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ folder_id: selectedFolderId, titel: titel, inhalt: inhalt })
    }).then(r => r.json()).then(data => {
      if (data.success) { showToast('Notiz erstellt'); closeNoteModal(); loadNotes(); loadFolders(); }
      else showToast(data.error || 'Fehler', 'error');
    }).catch(() => showToast('Fehler', 'error'));
  }
});

function openFolderModal(editFolderId) {
  folderSelectedMemberIds = [];
  document.getElementById('folder_id').value = editFolderId || '';
  document.getElementById('folderModalTitle').textContent = editFolderId ? 'Ordner bearbeiten' : 'Neuer Ordner';
  if (editFolderId) {
    const f = folders.find(x => x.id === parseInt(editFolderId));
    if (f) { document.getElementById('folderName').value = f.name; folderSelectedMemberIds = f.member_ids || []; }
  } else document.getElementById('folderName').value = '';
  renderFolderCandidates('');
  document.getElementById('folderModal').classList.remove('hidden');
  fetch(foldersApiUrl + '?candidates=1').then(r => r.json()).then(d => { if (d.success) folderCandidates = d.candidates || []; renderFolderCandidates(''); });
}

function closeFolderModal() {
  document.getElementById('folderModal').classList.add('hidden');
}

function renderFolderCandidates(search) {
  const q = (search || '').toLowerCase();
  const list = document.getElementById('folderCandidatesList');
  const filtered = folderCandidates.filter(c => {
    const name = ((c.vorname || '') + ' ' + (c.nachname || '') + ' ' + (c.email || '')).toLowerCase();
    return !q || name.includes(q);
  });
  list.innerHTML = filtered.map(c => {
    const name = [c.vorname, c.nachname].filter(Boolean).join(' ') || c.email || 'Unbekannt';
    const id = c.id;
    const checked = folderSelectedMemberIds.indexOf(id) !== -1;
    return `
      <label class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-primary-140 cursor-pointer">
        <input type="checkbox" class="folder-member-cb" value="${id}" ${checked ? 'checked' : ''}>
        <span class="text-sm text-gray-900 dark:text-white">${escapeHtml(name)}</span>
        <span class="text-xs text-gray-500">${escapeHtml(c.rolle || '')}</span>
      </label>
    `;
  }).join('') || '<p class="p-2 text-sm text-gray-500">Keine Kollegen gefunden.</p>';
  list.querySelectorAll('.folder-member-cb').forEach(cb => {
    cb.addEventListener('change', function() {
      if (this.checked) folderSelectedMemberIds.push(parseInt(this.value));
      else folderSelectedMemberIds = folderSelectedMemberIds.filter(id => id !== parseInt(this.value));
    });
  });
}

document.getElementById('createFolderBtn').addEventListener('click', () => openFolderModal());
document.getElementById('closeFolderModalBtn').addEventListener('click', closeFolderModal);
document.getElementById('folderModalOverlay').addEventListener('click', closeFolderModal);
document.getElementById('folderMemberSearch').addEventListener('input', function() { renderFolderCandidates(this.value); });

document.getElementById('folderForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const folderId = document.getElementById('folder_id').value;
  const name = document.getElementById('folderName').value.trim();
  if (!name) return;
  const payload = { name: name, member_ids: folderSelectedMemberIds };
  const method = folderId ? 'PUT' : 'POST';
  if (folderId) payload.folder_id = parseInt(folderId);
  fetch(foldersApiUrl, { method: method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(folderId ? 'Ordner aktualisiert' : 'Ordner erstellt');
        closeFolderModal();
        loadFolders();
        if (data.folder_id) setSelectedFolder(data.folder_id);
      } else showToast(data.error || 'Fehler', 'error');
    })
    .catch(() => showToast('Fehler', 'error'));
});

function refreshCurrentView() {
  if (selectedFolderId === 'kunden') renderCustomerCards();
  else if (selectedFolderId === 'firmen') renderCompanyCards();
  else if (typeof selectedFolderId === 'number') renderPrivatCards();
}

function updateNotesSearchActiveState() {
  const wrapper = document.getElementById('notes-search-wrapper');
  const el = document.getElementById('notes-search');
  if (wrapper && el) wrapper.classList.toggle('search-active', (el.value || '').trim() !== '');
}

document.addEventListener('DOMContentLoaded', function() {
  loadFolders();
  setSelectedFolder(null);
  const scrollEl = document.getElementById('folderFiltersScroll');
  if (scrollEl) scrollEl.addEventListener('scroll', updateNotesFolderScrollIndicators);

  const searchEl = document.getElementById('notes-search');
  if (searchEl) {
    searchEl.addEventListener('input', function() {
      updateNotesSearchActiveState();
      clearTimeout(notesSearchTimeout);
      notesSearchTimeout = setTimeout(function() {
        notesSearchTerm = searchEl.value || '';
        refreshCurrentView();
      }, 300);
    });
  }

  document.getElementById('resetNotesSearchBtn').addEventListener('click', function() {
    notesSearchTerm = '';
    const el = document.getElementById('notes-search');
    if (el) { el.value = ''; updateNotesSearchActiveState(); }
    refreshCurrentView();
  });
});
</script>
