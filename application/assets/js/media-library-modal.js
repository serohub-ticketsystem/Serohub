/**
 * Medienbibliothek (Modal) – Bilder aus uploads/images/ auswählen (Kunden-/Firmenlogos).
 *
 * openMediaLibraryModal({
 *   baseUrl: string,           // z. B. baseUrl mit trailing slash
 *   onSelect: function(path), // relativer Pfad z. B. uploads/images/customer_1_123.png
 *   title: string (optional)
 * });
 */
(function () {
  'use strict';

  var modalEl = null;
  var backdropEl = null;
  var state = {
    baseUrl: '',
    onSelect: null,
    items: [],
    selectedPath: null,
    loadToken: 0
  };

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function formatBytes(n) {
    n = Number(n) || 0;
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function buildQuery(params) {
    var parts = [];
    Object.keys(params).forEach(function (k) {
      if (params[k] === '' || params[k] == null) return;
      parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(params[k])));
    });
    return parts.length ? ('?' + parts.join('&')) : '';
  }

  function getModal() {
    if (modalEl) return modalEl;
    backdropEl = document.createElement('div');
    backdropEl.id = 'mediaLibraryModalBackdrop';
    backdropEl.className = 'fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50';
    backdropEl.setAttribute('role', 'dialog');
    backdropEl.setAttribute('aria-modal', 'true');
    backdropEl.style.display = 'none';

    backdropEl.innerHTML =
      '<div id="mediaLibraryModalPanel" class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col border border-gray-200 dark:border-gray-700 overflow-hidden">' +
      '  <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">' +
      '    <h2 id="mediaLibraryModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Bild auswählen</h2>' +
      '    <button type="button" id="mediaLibraryModalClose" class="p-2.5 rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Schließen">' +
      '      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
      '    </button>' +
      '  </div>' +
      '  <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 space-y-3">' +
      '    <div class="relative">' +
      '      <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400">' +
      '        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>' +
      '      </span>' +
      '      <input type="search" id="mediaLibrarySearch" placeholder="Dateien suchen" class="w-full pl-11 pr-4 py-2.5 text-sm rounded-full border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500" />' +
      '    </div>' +
      '    <div class="flex flex-wrap gap-2 items-center">' +
      '      <select id="mediaLibraryKind" class="text-xs rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white py-2 pl-3.5 pr-8 cursor-pointer max-w-full">' +
      '        <option value="all">Alle Dateinamen</option>' +
      '        <option value="customer">Präfix Kunde (customer_)</option>' +
      '        <option value="company">Präfix Firma (company_)</option>' +
      '      </select>' +
      '      <select id="mediaLibraryUsage" class="text-xs rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white py-2 pl-3.5 pr-8 cursor-pointer max-w-full">' +
      '        <option value="all">Verwendung: alle</option>' +
      '        <option value="in_use">Verwendet in DB</option>' +
      '        <option value="unused">Nicht zugeordnet</option>' +
      '      </select>' +
      '      <select id="mediaLibrarySort" class="text-xs rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white py-2 pl-3.5 pr-8 cursor-pointer max-w-full">' +
      '        <option value="mtime_desc">Sortieren: neueste zuerst</option>' +
      '        <option value="mtime_asc">Sortieren: älteste zuerst</option>' +
      '        <option value="name_asc">Name A–Z</option>' +
      '        <option value="name_desc">Name Z–A</option>' +
      '        <option value="size_desc">Größe absteigend</option>' +
      '        <option value="size_asc">Größe aufsteigend</option>' +
      '      </select>' +
      '    </div>' +
      '  </div>' +
      '  <div id="mediaLibraryScroll" class="flex-1 min-h-[200px] overflow-y-auto px-5 py-4">' +
      '    <div id="mediaLibraryLoading" class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">Lade Medien…</div>' +
      '    <div id="mediaLibraryEmpty" class="hidden text-center text-sm text-gray-500 dark:text-gray-400 py-8">Keine Bilder gefunden.</div>' +
      '    <div id="mediaLibraryGrid" class="hidden grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"></div>' +
      '  </div>' +
      '  <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-200 dark:border-gray-700">' +
      '    <button type="button" id="mediaLibraryCancel" class="px-5 py-2.5 text-sm rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Abbrechen</button>' +
      '    <button type="button" id="mediaLibraryConfirm" disabled class="px-5 py-2.5 text-sm rounded-full bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">Fertig</button>' +
      '  </div>' +
      '</div>';

    document.body.appendChild(backdropEl);
    modalEl = backdropEl;

    backdropEl.addEventListener('click', function (e) {
      if (e.target === backdropEl) closeModal();
    });
    document.getElementById('mediaLibraryModalClose').addEventListener('click', closeModal);
    document.getElementById('mediaLibraryCancel').addEventListener('click', closeModal);
    document.getElementById('mediaLibraryConfirm').addEventListener('click', confirmSelection);

    var searchEl = document.getElementById('mediaLibrarySearch');
    var debounceTimer;
    searchEl.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        loadMedia();
      }, 280);
    });
    ['mediaLibraryKind', 'mediaLibraryUsage', 'mediaLibrarySort'].forEach(function (id) {
      document.getElementById(id).addEventListener('change', function () {
        loadMedia();
      });
    });

    return modalEl;
  }

  function closeModal() {
    if (backdropEl) backdropEl.style.display = 'none';
    state.onSelect = null;
    state.selectedPath = null;
  }

  function confirmSelection() {
    if (!state.selectedPath || typeof state.onSelect !== 'function') {
      closeModal();
      return;
    }
    var cb = state.onSelect;
    var path = state.selectedPath;
    closeModal();
    cb(path);
  }

  function setConfirmEnabled(on) {
    var btn = document.getElementById('mediaLibraryConfirm');
    if (btn) btn.disabled = !on;
  }

  function renderGrid() {
    var grid = document.getElementById('mediaLibraryGrid');
    var empty = document.getElementById('mediaLibraryEmpty');
    var loading = document.getElementById('mediaLibraryLoading');
    loading.classList.add('hidden');
    if (!state.items.length) {
      grid.classList.add('hidden');
      empty.classList.remove('hidden');
      return;
    }
    empty.classList.add('hidden');
    grid.classList.remove('hidden');
    var base = state.baseUrl.replace(/\/?$/, '/');
    grid.innerHTML = state.items
      .map(function (it) {
        var url = base + it.path.replace(/^\//, '');
        var sel = state.selectedPath === it.path;
        var usage = (it.used_in || [])
          .map(function (u) {
            var prefix = u.entity === 'company' ? 'Firma' : 'Kunde';
            return esc(prefix + ': ' + (u.label || ''));
          })
          .join(' · ');
        if (!usage) usage = '—';
        return (
          '<div role="button" tabindex="0" data-path="' +
          esc(it.path) +
          '" class="media-library-card rounded-2xl border overflow-hidden cursor-pointer transition shadow-sm ' +
          (sel
            ? 'ring-2 ring-primary-500 border-primary-500 dark:border-primary-400'
            : 'border-gray-200 dark:border-gray-600 hover:border-primary-400') +
          '">' +
          '  <div class="relative aspect-square bg-gray-100 dark:bg-gray-900 rounded-t-2xl overflow-hidden">' +
          '    <img src="' +
          esc(url) +
          '" alt="" class="w-full h-full object-cover" loading="lazy" />' +
          '    <div class="absolute top-2 left-2 w-7 h-7 rounded-lg border-2 flex items-center justify-center shadow-sm ' +
          (sel ? 'bg-primary-600 border-primary-600 text-white' : 'bg-white/90 dark:bg-gray-800/90 border-gray-300') +
          '">' +
          (sel
            ? '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>'
            : '') +
          '    </div>' +
          '  </div>' +
          '  <div class="p-2 border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">' +
          '    <p class="text-xs font-medium text-gray-900 dark:text-white truncate" title="' +
          esc(it.filename) +
          '">' +
          esc(it.filename) +
          '</p>' +
          '    <p class="text-[10px] text-gray-500 dark:text-gray-400">' +
          esc(it.ext) +
          ' · ' +
          esc(formatBytes(it.size)) +
          '</p>' +
          '    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate" title="' +
          esc(usage) +
          '">' +
          esc(usage) +
          '</p>' +
          '  </div>' +
          '</div>'
        );
      })
      .join('');

    grid.querySelectorAll('.media-library-card').forEach(function (card) {
      function pick() {
        var p = card.getAttribute('data-path');
        state.selectedPath = p;
        setConfirmEnabled(true);
        renderGrid();
      }
      card.addEventListener('click', pick);
      card.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          pick();
        }
      });
    });
  }

  function loadMedia() {
    var token = ++state.loadToken;
    var search = document.getElementById('mediaLibrarySearch');
    var kind = document.getElementById('mediaLibraryKind');
    var usage = document.getElementById('mediaLibraryUsage');
    var sort = document.getElementById('mediaLibrarySort');

    document.getElementById('mediaLibraryLoading').classList.remove('hidden');
    document.getElementById('mediaLibraryEmpty').classList.add('hidden');
    document.getElementById('mediaLibraryGrid').classList.add('hidden');

    var apiUrl =
      state.baseUrl.replace(/\/?$/, '/') +
      'api/media_library.php' +
      buildQuery({
        q: search ? search.value.trim() : '',
        kind: kind ? kind.value : 'all',
        usage: usage ? usage.value : 'all',
        sort: sort ? sort.value : 'mtime_desc'
      });

    fetch(apiUrl, { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (token !== state.loadToken) return;
        if (!data || !data.success) {
          state.items = [];
          renderGrid();
          return;
        }
        state.items = data.items || [];
        if (data.base_url) {
          state.baseUrl = data.base_url + '/';
        }
        renderGrid();
      })
      .catch(function () {
        if (token !== state.loadToken) return;
        state.items = [];
        renderGrid();
      });
  }

  window.openMediaLibraryModal = function (options) {
    if (!options || !options.baseUrl) return;
    state.baseUrl = options.baseUrl;
    state.onSelect = typeof options.onSelect === 'function' ? options.onSelect : null;
    state.selectedPath = null;
    setConfirmEnabled(false);

    getModal();
    var titleEl = document.getElementById('mediaLibraryModalTitle');
    if (titleEl) titleEl.textContent = options.title || 'Bild auswählen';

    var search = document.getElementById('mediaLibrarySearch');
    if (search) search.value = '';
    var kind = document.getElementById('mediaLibraryKind');
    if (kind) kind.value = 'all';
    var usage = document.getElementById('mediaLibraryUsage');
    if (usage) usage.value = 'all';
    var sort = document.getElementById('mediaLibrarySort');
    if (sort) sort.value = 'mtime_desc';

    backdropEl.style.display = 'flex';
    document.getElementById('mediaLibraryLoading').classList.remove('hidden');
    document.getElementById('mediaLibraryEmpty').classList.add('hidden');
    document.getElementById('mediaLibraryGrid').classList.add('hidden');

    loadMedia();
  };
})();
