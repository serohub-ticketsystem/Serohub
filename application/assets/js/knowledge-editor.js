/**
 * Wissensdatenbank-Editor (Notion-ähnlich): TipTap mit Slash-Menü, Auto-Save, Upload, Tabellen.
 * Lädt TipTap per ESM von CDN.
 */
(function() {
  const cfg = window.KB_CONFIG;
  if (!cfg || !cfg.apiSave) return;

  const DEBOUNCE_SAVE_MS = 1200;
  let editorInstance = null;
  let saveTimeout = null;
  let lastSavedJson = null;
  let currentPageId = cfg.pageId;
  let kbPageCreateMode = false;

  function showToast(msg, type) {
    if (typeof window.showToast === 'function') window.showToast(msg, type || 'success');
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function setSaveStatus(text, isError) {
    const el = document.getElementById('kb-save-status');
    if (el) {
      el.textContent = text;
      el.classList.toggle('text-green-600', !isError && text !== 'Gespeichert');
      el.classList.toggle('text-red-600', isError);
    }
  }

  function setUpdatedAt(dateStr) {
    const el = document.getElementById('kb-updated-at');
    if (el && dateStr) el.textContent = dateStr;
  }

  function initHistoryModal(cfg) {
    if (!cfg.pageId || !cfg.apiPageHistory) return;
    const btn = document.getElementById('kb-page-history-btn');
    const modal = document.getElementById('kb-history-modal');
    const backdrop = document.getElementById('kb-history-modal-backdrop');
    const closeBtn = document.getElementById('kb-history-modal-close');
    const listEl = document.getElementById('kb-history-list');
    const detailEl = document.getElementById('kb-history-detail');
    const loadingEl = document.getElementById('kb-history-loading');
    const emptyEl = document.getElementById('kb-history-empty');
    if (!btn || !modal) return;

    function openModal() {
      modal.classList.remove('hidden');
      listEl.classList.add('hidden');
      listEl.innerHTML = '';
      detailEl.classList.add('hidden');
      detailEl.innerHTML = '';
      emptyEl.classList.add('hidden');
      loadingEl.classList.remove('hidden');
      fetch(cfg.apiPageHistory + '?page_id=' + encodeURIComponent(cfg.pageId))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          loadingEl.classList.add('hidden');
          if (data.success && data.history && data.history.length > 0) {
            listEl.classList.remove('hidden');
            data.history.forEach(function(h) {
              var li = document.createElement('li');
              li.className = 'rounded-lg border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-3 hover:bg-gray-50 dark:hover:bg-primary-140 cursor-pointer transition-colors';
              li.dataset.versionId = h.id;
              var dateStr = h.created_at ? new Date(h.created_at).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' }) : '';
              li.innerHTML = '<div class="flex justify-between items-start gap-2"><span class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(h.user_name || 'Unbekannt') + '</span><span class="text-xs text-gray-500 dark:text-primary-210 shrink-0">' + escapeHtml(dateStr) + '</span></div>' +
                (h.title ? '<p class="text-sm text-gray-600 dark:text-primary-210 mt-1 truncate" title="' + escapeHtml(h.title) + '">Titel: ' + escapeHtml(h.title) + '</p>' : '') +
                '<p class="text-xs text-gray-500 dark:text-primary-240 mt-1 line-clamp-2">' + escapeHtml(h.content_preview || '') + '</p>';
              li.addEventListener('click', function() {
                var id = li.dataset.versionId;
                detailEl.classList.remove('hidden');
                detailEl.innerHTML = '<p class="text-sm text-gray-500 dark:text-primary-210">Lade Version …</p>';
                fetch(cfg.apiPageHistory + '?id=' + encodeURIComponent(id))
                  .then(function(r) { return r.json(); })
                  .then(function(res) {
                    if (res.success && res.version) {
                      var v = res.version;
                      var titleHtml = v.title ? '<p class="font-medium text-gray-900 dark:text-primary-200 mb-2">Titel (davor): ' + escapeHtml(v.title) + '</p>' : '';
                      var contentText = (v.content || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                      if (v.content_type === 'json') {
                        try {
                          var doc = typeof v.content === 'string' ? JSON.parse(v.content) : v.content;
                          if (doc && doc.content && Array.isArray(doc.content)) {
                            contentText = doc.content.map(function(n) {
                              if (n.content && Array.isArray(n.content)) return n.content.map(function(c) { return c.text || ''; }).join('');
                              return '';
                            }).join(' ');
                          }
                        } catch (e) {}
                      }
                      detailEl.innerHTML = titleHtml + '<p class="text-sm text-gray-700 dark:text-primary-210 whitespace-pre-wrap break-words">' + escapeHtml(contentText || '(leer)') + '</p>';
                    } else {
                      detailEl.innerHTML = '<p class="text-sm text-red-600 dark:text-red-400">Version konnte nicht geladen werden.</p>';
                    }
                  })
                  .catch(function() {
                    detailEl.innerHTML = '<p class="text-sm text-red-600 dark:text-red-400">Fehler beim Laden.</p>';
                  });
              });
              listEl.appendChild(li);
            });
          } else {
            emptyEl.classList.remove('hidden');
          }
        })
        .catch(function() {
          loadingEl.classList.add('hidden');
          emptyEl.textContent = 'Verlauf konnte nicht geladen werden.';
          emptyEl.classList.remove('hidden');
        });
    }

    function closeModal() {
      modal.classList.add('hidden');
    }

    btn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);
  }

  function saveContent() {
    if (!editorInstance) return;
    const json = editorInstance.getJSON();
    const titleEl = document.getElementById('kb-page-title');
    const title = titleEl ? titleEl.value.trim() : '';
    const isCompanyFolder = !!(cfg.isCompanyFolder);
    const isSystemFolder = !!(cfg.isSystemFolder);

    function doSave(id) {
      const body = { id: id, content: json, content_type: 'json' };
      if (!isCompanyFolder && !isSystemFolder) body.title = title || 'Neue Seite';
      fetch(cfg.apiSave, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).then(function(r) { return r.json();       }).then(function(data) {
        if (data.success) {
          lastSavedJson = JSON.stringify(json);
          setSaveStatus('Gespeichert');
          setUpdatedAt(data.updated_at || '');
          const newTitle = (cfg.isCompanyFolder || cfg.isSystemFolder) && cfg.companyPageTitle ? cfg.companyPageTitle : (title || 'Neue Seite');
          var wasNewPage = !!(data.id && !currentPageId);
          if (data.id && !currentPageId) {
            currentPageId = data.id;
            if (window.history && window.history.replaceState) {
              const url = cfg.baseUrl + 'knowledge/?id=' + encodeURIComponent(data.id);
              window.history.replaceState({}, '', url);
            }
          }
          try {
            if (window.parent && window.parent !== window) {
              window.parent.postMessage({ type: 'kb-page-title-changed', id: currentPageId, title: newTitle }, window.location.origin);
              if (wasNewPage) window.parent.postMessage({ type: 'kb-pages-updated' }, window.location.origin);
            }
          } catch (err) {}
        } else {
          setSaveStatus(data.error || 'Fehler', true);
        }
      }).catch(function() {
        setSaveStatus('Fehler beim Speichern', true);
      });
    }

    if (currentPageId) {
      doSave(currentPageId);
      return;
    }
    createPageThenSave();
  }

  function createPageThenSave() {
    fetch(cfg.baseUrl + 'knowledge/api/pages.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: document.getElementById('kb-page-title').value.trim() || 'Neue Seite', parent_id: cfg.parentId || null })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success && data.id) {
        currentPageId = data.id;
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, '', cfg.baseUrl + 'knowledge/?id=' + encodeURIComponent(data.id));
        }
        function notifyParent() {
          try {
            if (window.parent && window.parent !== window) {
              window.parent.postMessage({ type: 'kb-pages-updated', newPageId: data.id, parentId: cfg.parentId || null }, window.location.origin);
            }
          } catch (e) {}
        }
        notifyParent();
        saveContent();
        setTimeout(notifyParent, 400);
      }
    });
  }

  function debouncedSave() {
    if (!currentPageId) return;
    setSaveStatus('Wird gespeichert…');
    if (saveTimeout) clearTimeout(saveTimeout);
    saveTimeout = setTimeout(function() {
      saveTimeout = null;
      saveContent();
    }, DEBOUNCE_SAVE_MS);
  }

  function setupSlashMenu(editor) {
    const menu = document.getElementById('kb-slash-menu');
    if (!menu) return;

    const items = [
      { title: 'Überschrift 1', icon: 'H1', cmd: function() { editor.chain().focus().toggleHeading({ level: 1 }).run(); } },
      { title: 'Überschrift 2', icon: 'H2', cmd: function() { editor.chain().focus().toggleHeading({ level: 2 }).run(); } },
      { title: 'Überschrift 3', icon: 'H3', cmd: function() { editor.chain().focus().toggleHeading({ level: 3 }).run(); } },
      { title: 'Text', icon: '¶', cmd: function() { editor.chain().focus().setParagraph().run(); } },
      { title: 'Seite', icon: '📄', cmd: function() {
          kbPageCreateMode = true;
          editor.chain().focus().insertContent('<div class="kb-callout kb-callout-page kb-page-create" data-callout="page"><p>📄 </p></div>').run();
        } },
      { title: 'Link', icon: '🔗', cmd: function() {
          var url = window.prompt('URL eingeben:', 'https://');
          if (!url || !url.trim()) return;
          url = url.trim();
          if (url !== 'https://' && url !== 'http://') {
            if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
            var text = window.prompt('Link-Text (leer = URL anzeigen):', url);
            if (text === null) return;
            text = (text != null && String(text).trim() !== '') ? String(text).trim() : url;
            var linkNode = { type: 'paragraph', content: [{ type: 'text', text: text, marks: [{ type: 'link', attrs: { href: url, target: '_blank' } }] }] };
            editor.chain().focus().insertContent(linkNode).run();
            if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null; }
            saveContent();
          }
        } },
      { title: 'Zitat', icon: '„', cmd: function() { editor.chain().focus().toggleBlockquote().run(); } },
      { title: 'Aufzählung', icon: '•', cmd: function() { editor.chain().focus().toggleBulletList().run(); } },
      { title: 'Nummerierte Liste', icon: '1.', cmd: function() { editor.chain().focus().toggleOrderedList().run(); } },
      { title: 'To-Do-Liste', icon: '☐', cmd: function() { editor.chain().focus().toggleTaskList().run(); } },
      { title: 'Code', icon: '</>', cmd: function() { editor.chain().focus().toggleCodeBlock().run(); } },
      { title: 'Trennlinie', icon: '—', cmd: function() { editor.chain().focus().setHorizontalRule().run(); } },
      { title: 'Container (Standard)', icon: '▢', cmd: function(pos) { insertCallout(editor, 'default', pos); } },
      { title: 'Container (Hinweis)', icon: '▢', cmd: function(pos) { insertCallout(editor, 'warning', pos); } },
      { title: 'Container (Warnung)', icon: '▢', cmd: function(pos) { insertCallout(editor, 'error', pos); } },
      { title: 'Container (Erfolg)', icon: '▢', cmd: function(pos) { insertCallout(editor, 'success', pos); } },
      { title: 'Bild', icon: '🖼', cmd: function() { document.getElementById('kb-file-input').click(); } },
      { title: 'Datei hochladen', icon: '📎', cmd: function() { document.getElementById('kb-file-input').click(); } },
      { title: 'Tabelle', icon: '▦', cmd: function() { editor.chain().focus().insertTable({ rows: 2, cols: 2, withHeaderRow: true }).run(); } }
    ];

    let selectedIndex = 0;

    function renderList(filter) {
      const q = (filter || '').toLowerCase();
      const filtered = q ? items.filter(function(i) { return i.title.toLowerCase().includes(q); }) : items;
      menu.innerHTML = filtered.map(function(item, idx) {
        const sel = idx === selectedIndex ? ' bg-gray-100 dark:bg-primary-140' : '';
        return '<div class="kb-slash-item px-3 py-2 cursor-pointer text-sm text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-primary-140' + sel + '" data-index="' + idx + '" role="option">' +
          '<span class="font-medium">' + escapeHtml(item.title) + '</span></div>';
      }).join('');
      const options = menu.querySelectorAll('.kb-slash-item');
      if (options[selectedIndex]) options[selectedIndex].scrollIntoView({ block: 'nearest' });
      return filtered;
    }

    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    let filteredItems = items;
    let menuVisible = false;
    let slashFrom = 0;

    function hideMenu() {
      menu.classList.add('hidden');
      menuVisible = false;
    }

    function showMenu(rect, filter, fromPos) {
      slashFrom = fromPos != null ? fromPos : 0;
      filteredItems = renderList(filter);
      if (filteredItems.length === 0) { hideMenu(); return; }
      selectedIndex = 0;
      renderList(filter);
      menu.style.left = (rect.left || 0) + 'px';
      menu.style.top = (rect.bottom !== undefined ? rect.bottom + 4 : rect.top + 24) + 'px';
      menu.classList.remove('hidden');
      menuVisible = true;
    }

    function runCommand(item) {
      if (!item || !item.cmd) return;
      var slashTo = (editor && slashFrom >= 0) ? editor.state.selection.from : slashFrom;
      if (editor && slashFrom >= 0 && slashTo > slashFrom) {
        editor.chain().focus().deleteRange({ from: slashFrom, to: slashTo }).run();
      }
      item.cmd(slashFrom >= 0 ? slashFrom : null);
      hideMenu();
    }

    menu.addEventListener('click', function(e) {
      const itemEl = e.target.closest('.kb-slash-item');
      if (!itemEl) return;
      const idx = parseInt(itemEl.getAttribute('data-index'), 10);
      const item = filteredItems[idx];
      runCommand(item);
    });

    document.addEventListener('mousedown', function(e) {
      if (menuVisible && menu && !menu.contains(e.target)) hideMenu();
    });

    window.kbSlashMenu = {
      show: showMenu,
      hide: hideMenu,
      select: function() {
        const item = filteredItems[selectedIndex];
        runCommand(item);
      },
      next: function() {
        selectedIndex = (selectedIndex + 1) % filteredItems.length;
        renderList();
      },
      prev: function() {
        selectedIndex = (selectedIndex - 1 + filteredItems.length) % filteredItems.length;
        renderList();
      },
      getFilter: function() { return menu.getAttribute('data-filter') || ''; },
      setFilter: function(f) { menu.setAttribute('data-filter', f); renderList(f); filteredItems = renderList(f); }
    };
  }

  var tablePlusNear = 44;
  var tablePlusHideDelay = 220;

  function setupTableToolbar(editor) {
    var wrap = document.getElementById('kb-editor-wrap');
    var plusCol = document.getElementById('kb-table-plus-col');
    var plusRow = document.getElementById('kb-table-plus-row');
    if (!wrap || !plusCol || !plusRow) return;

    var currentTable = null;
    var pendingHide = null;

    function hidePlus() {
      if (pendingHide) {
        clearTimeout(pendingHide);
        pendingHide = null;
      }
      plusCol.classList.add('hidden');
      plusRow.classList.add('hidden');
      currentTable = null;
    }

    function scheduleHide() {
      if (pendingHide) clearTimeout(pendingHide);
      pendingHide = setTimeout(hidePlus, tablePlusHideDelay);
    }

    function cancelScheduleHide() {
      if (pendingHide) {
        clearTimeout(pendingHide);
        pendingHide = null;
      }
    }

    function posAtCell(editor, tableEl, rowIndex, cellIndex) {
      var row = tableEl.rows[rowIndex];
      if (!row) return null;
      var cell = row.cells[cellIndex];
      if (!cell) return null;
      try {
        var inner = cell.querySelector('p') || cell.firstElementChild || cell;
        var pos = editor.view.posAtDOM(inner, 0);
        if (typeof pos !== 'number' || pos < 0) return null;
        return pos;
      } catch (e) {
        return null;
      }
    }

    plusCol.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var table = currentTable;
      if (!table) return;
      hidePlus();
      var lastRow = table.rows.length - 1;
      var lastCol = table.rows[lastRow].cells.length - 1;
      var pos = posAtCell(editor, table, lastRow, lastCol);
      if (pos == null) return;
      editor.chain().focus().setTextSelection(pos).addColumnAfter().run();
    });

    plusRow.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var table = currentTable;
      if (!table) return;
      hidePlus();
      var lastRow = table.rows.length - 1;
      var pos = posAtCell(editor, table, lastRow, 0);
      if (pos == null) return;
      editor.chain().focus().setTextSelection(pos).addRowAfter().run();
    });

    plusCol.addEventListener('mouseenter', cancelScheduleHide);
    plusRow.addEventListener('mouseenter', cancelScheduleHide);
    plusCol.addEventListener('mouseleave', scheduleHide);
    plusRow.addEventListener('mouseleave', scheduleHide);

    wrap.addEventListener('mousemove', function(e) {
      var table = e.target.closest('table');
      if (!table || !wrap.contains(table)) {
        scheduleHide();
        return;
      }
      cancelScheduleHide();
      var rect = table.getBoundingClientRect();
      var x = e.clientX;
      var y = e.clientY;
      var nearRight = x >= rect.right - tablePlusNear && x <= rect.right + 12 && y >= rect.top - 4 && y <= rect.bottom + 4;
      var nearBottom = y >= rect.bottom - tablePlusNear && y <= rect.bottom + 12 && x >= rect.left - 4 && x <= rect.right + 4;
      currentTable = table;
      if (nearRight) {
        plusCol.style.left = (rect.right - 16) + 'px';
        plusCol.style.top = (rect.top + rect.height / 2 - 16) + 'px';
        plusCol.classList.remove('hidden');
      } else {
        plusCol.classList.add('hidden');
      }
      if (nearBottom) {
        plusRow.style.left = (rect.left + rect.width / 2 - 16) + 'px';
        plusRow.style.top = (rect.bottom - 16) + 'px';
        plusRow.classList.remove('hidden');
      } else {
        plusRow.classList.add('hidden');
      }
    });

    wrap.addEventListener('mouseleave', function(e) {
      var related = e.relatedTarget;
      if (related === plusCol || related === plusRow || (related && (plusCol.contains(related) || plusRow.contains(related)))) return;
      scheduleHide();
    });
  }

  function insertImage(url) {
    if (editorInstance && url) {
      editorInstance.chain().focus().setImage({ src: url }).run();
    }
  }

  function onFileSelect() {
    const input = document.getElementById('kb-file-input');
    if (!input || !input.files || !input.files[0]) return;
    const file = input.files[0];
    const fd = new FormData();
    fd.append('file', file);
    if (currentPageId) fd.append('page_id', currentPageId);
    else {
      showToast('Bitte zuerst die Seite speichern (Titel eingeben oder Inhalt tippen).', 'error');
      input.value = '';
      return;
    }
    fetch(cfg.apiUpload, { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(data) {
      input.value = '';
      if (data.success && data.url) {
        if (data.is_image) insertImage(data.url);
        else editorInstance.chain().focus().insertContent('<a href="' + data.url + '" target="_blank" rel="noopener">' + (data.file_name || 'Datei') + '</a>').run();
      } else showToast(data.error || 'Upload fehlgeschlagen', 'error');
    }).catch(function() {
      input.value = '';
      showToast('Upload fehlgeschlagen', 'error');
    });
  }

  document.getElementById('kb-file-input').addEventListener('change', onFileSelect);

  function initEditor(content) {
    const wrap = document.getElementById('kb-editor-wrap');
    if (!wrap) return;

    const Placeholder = { addOptions() { return { placeholder: 'Tippe \'/\' für Befehle…' }; }, addProseMirrorPlugins() { return []; } };

    Promise.all([
      import('https://esm.sh/@tiptap/core@2.10.4'),
      import('https://esm.sh/prosemirror-state@1.4.3'),
      import('https://esm.sh/prosemirror-model@1.22.0'),
      import('https://esm.sh/@tiptap/starter-kit@2.10.4'),
      import('https://esm.sh/@tiptap/extension-image@2.10.4'),
      import('https://esm.sh/@tiptap/extension-link@2.10.4'),
      import('https://esm.sh/@tiptap/extension-task-list@2.10.4'),
      import('https://esm.sh/@tiptap/extension-task-item@2.10.4'),
      import('https://esm.sh/@tiptap/extension-table@2.10.4'),
      import('https://esm.sh/@tiptap/extension-table-row@2.10.4'),
      import('https://esm.sh/@tiptap/extension-table-header@2.10.4'),
      import('https://esm.sh/@tiptap/extension-table-cell@2.10.4'),
      import('https://esm.sh/@tiptap/extension-gapcursor@2.10.4'),
      import('https://esm.sh/@tiptap/extension-dropcursor@2.10.4'),
      import('https://esm.sh/@tiptap/extension-placeholder@2.10.4')
    ]).then(function(mods) {
      const Core = mods[0];
      const Plugin = mods[1].Plugin;
      const PluginKey = mods[1].PluginKey;
      const TextSelection = mods[1].TextSelection;
      const Fragment = mods[2].Fragment;
      const Slice = mods[2].Slice;
      const StarterKit = mods[3].default;
      const Image = mods[4].default;
      const Link = mods[5].default;
      const TaskList = mods[6].default;
      const TaskItem = mods[7].default;
      const Table = mods[8].default;
      const TableRow = mods[9].default;
      const TableHeader = mods[10].default;
      const TableCell = mods[11].default;
      const Gapcursor = mods[12].default;
      const Dropcursor = mods[13].default;
      const PlaceholderExt = mods[14].default;

      var isDark = document.documentElement.classList.contains('dark');
      var calloutStyles = isDark
        ? { default: { backgroundColor: '#334155', borderLeft: '4px solid #94a3b8' }, warning: { backgroundColor: '#451a03', borderLeft: '4px solid #fbbf24' }, error: { backgroundColor: '#450a0a', borderLeft: '4px solid #f87171' }, success: { backgroundColor: '#052e16', borderLeft: '4px solid #4ade80' }, page: { backgroundColor: '#1e3a5f', borderLeft: '4px solid #60a5fa' } }
        : { default: { backgroundColor: '#f1f5f9', borderLeft: '4px solid #64748b' }, warning: { backgroundColor: '#fef3c7', borderLeft: '4px solid #f59e0b' }, error: { backgroundColor: '#fee2e2', borderLeft: '4px solid #ef4444' }, success: { backgroundColor: '#dcfce7', borderLeft: '4px solid #22c55e' }, page: { backgroundColor: '#eff6ff', borderLeft: '4px solid #3b82f6' } };
      var Callout = Core.Node.create({
        name: 'callout',
        group: 'block',
        content: 'block+',
        addAttributes: function() {
          return {
            type: {
              default: 'default',
              parseHTML: function(el) { return el.getAttribute('data-callout') || 'default'; },
              renderHTML: function(attrs) { return { 'data-callout': attrs.type }; }
            }
          };
        },
        parseHTML: function() {
          function getType(dom) {
            var t = dom.getAttribute && dom.getAttribute('data-callout');
            if (t) return t;
            var m = (dom.className && String(dom.className).match(/kb-callout-(default|warning|error|success|page)/));
            return (m && m[1]) || 'default';
          }
          return [
            { tag: 'div[data-callout]', getAttrs: function(dom) { return { type: getType(dom) }; } },
            { tag: 'div.kb-callout', getAttrs: function(dom) { return { type: getType(dom) }; } }
          ];
        },
        renderHTML: function(_ref) {
          var node = _ref.node;
          var HTMLAttributes = _ref.HTMLAttributes || {};
          return ['div', Object.assign({}, HTMLAttributes, { class: 'kb-callout kb-callout-' + (node.attrs.type || 'default'), 'data-callout': node.attrs.type || 'default' }), 0];
        },
        addNodeView: function() {
          return function(_ref2) {
            var node = _ref2.node;
            var type = node.attrs.type || 'default';
            var wrapper = document.createElement('div');
            wrapper.className = 'kb-callout kb-callout-' + type;
            wrapper.setAttribute('data-callout', type);
            var style = calloutStyles[type] || calloutStyles.default;
            wrapper.style.padding = '0.75rem 1rem';
            wrapper.style.borderRadius = '0.375rem';
            wrapper.style.margin = '0.75em 0';
            wrapper.style.boxSizing = 'border-box';
            wrapper.style.backgroundColor = style.backgroundColor;
            wrapper.style.borderLeft = style.borderLeft;
            var content = document.createElement('div');
            content.className = 'kb-callout-inner';
            wrapper.appendChild(content);
            return { dom: wrapper, contentDOM: content };
          };
        }
      });

      function insertCallout(ed, type, replaceAtPos) {
        var schema = ed.schema;
        var calloutType = schema.nodes.callout;
        if (!calloutType) {
          ed.chain().focus().insertContent('<div class="kb-callout kb-callout-' + type + '" data-callout="' + type + '"><p></p></div>').run();
          return;
        }
        var p = schema.nodes.paragraph.create();
        var calloutNode = calloutType.create({ type: type }, [p]);
        if (replaceAtPos != null && replaceAtPos >= 0) {
          var state = ed.state;
          var tr = state.tr;
          var $pos = state.doc.resolve(replaceAtPos);
          var depth = $pos.depth;
          var blockFrom = $pos.before(depth);
          var blockTo = $pos.after(depth);
          tr.replaceRange(blockFrom, blockTo, new Slice(Fragment.from([calloutNode]), 0, 0));
          tr.setSelection(TextSelection.create(tr.doc, blockFrom + 1));
          ed.view.dispatch(tr);
          return;
        }
        ed.chain().focus().insertContent(calloutNode).run();
      }

      var PageCreateExt = Core.Extension.create({
        name: 'kbPageCreate',
        priority: 1000,
        addKeyboardShortcuts: function() {
          var self = this;
          return {
            Enter: function() {
              var state = self.editor.state;
              var from = state.selection.from;
              var $pos = state.doc.resolve(from);
              var inPageCallout = false;
              for (var d = $pos.depth; d >= 0; d--) {
                var n = $pos.node(d);
                if (n.type && n.type.name === 'callout' && n.attrs && n.attrs.type === 'page') {
                  inPageCallout = true;
                  break;
                }
              }
              if (!inPageCallout) return false;
              var node = $pos.parent;
              var rawText = (node.textContent || '').trim();
              var title = rawText.replace(/^\u{1F4C4}\s*/u, '').replace(/^📄\s*/, '').trim();
              kbPageCreateMode = false;
              if (!title) {
                self.editor.chain().focus().splitBlock().run();
                return true;
              }
              var depth = $pos.depth;
              var delFrom = $pos.before(depth);
              var delTo = $pos.after(depth);
              var parentNode = depth >= 1 ? $pos.node(depth - 1) : null;
              if (parentNode && (parentNode.type.name === 'blockquote' || parentNode.type.name === 'callout')) {
                delFrom = $pos.before(depth - 1);
                delTo = $pos.after(depth - 1);
              }
              var pagesUrl = cfg.baseUrl + 'knowledge/api/pages.php';
              fetch(pagesUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title: title, parent_id: currentPageId || null }),
                credentials: 'same-origin'
              }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success && d.id) {
                  var href = cfg.baseUrl + 'knowledge/?id=' + encodeURIComponent(d.id);
                  var linkNode = { type: 'paragraph', content: [{ type: 'text', text: title, marks: [{ type: 'link', attrs: { href: href, target: '_self' } }] }] };
                  self.editor.chain().focus().deleteRange({ from: delFrom, to: delTo }).insertContent(linkNode).run();
                  showToast('Seite erstellt: ' + title);
                  if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null; }
                  saveContent();
                  try { if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'kb-pages-updated' }, window.location.origin); } catch (e) {}
                } else {
                  showToast(d.error || 'Fehler beim Erstellen', 'error');
                  self.editor.chain().focus().splitBlock().run();
                }
              }).catch(function() {
                showToast('Fehler beim Erstellen der Seite', 'error');
                self.editor.chain().focus().splitBlock().run();
              });
              return true;
            }
          };
        }
      });

      var ImageWithSize = Image.extend({
        addAttributes: function() {
          return {
            src: { default: null },
            alt: { default: null },
            title: { default: null },
            width: { default: null, parseHTML: function(el) { var w = el.getAttribute('width'); return w ? parseInt(w, 10) : null; }, renderHTML: function(attrs) { return attrs.width ? { width: attrs.width } : {}; } },
            height: { default: null, parseHTML: function(el) { var h = el.getAttribute('height'); return h ? parseInt(h, 10) : null; }, renderHTML: function(attrs) { return attrs.height ? { height: attrs.height } : {}; } }
          };
        }
      });

      var kbImageResizeKey = new PluginKey('kbImageResize');
      function createKbImageResizePlugin(editor, wrap, pluginKey) {
        var handle = document.createElement('div');
        handle.className = 'kb-img-resize-handle';
        handle.setAttribute('data-resize-handle', 'true');
        handle.title = 'Größe ändern – ziehen';
        handle.style.cssText = 'position:absolute;width:14px;height:14px;background:#3b82f6;border-radius:2px 0 0 0;cursor:se-resize;z-index:60;opacity:0;pointer-events:auto;bottom:0;right:0;transition:opacity 0.15s;';
        wrap.appendChild(handle);
        var imgContainer = null;
        var resizeStart = null;
        var lastHoveredImg = null;
        var lastHoveredPos = null;
        var MIN_W = 80; var MIN_H = 50;

        function updateHandlePosition() {
          var state = editor.state;
          var sel = state.selection;
          if (sel.node && sel.node.type && sel.node.type.name === 'image') {
            var view = editor.view;
            var pos = sel.from;
            var domPos = view.domAtPos(pos);
            var el = domPos.node.nodeType === 3 ? domPos.node.parentElement : domPos.node;
            if (el && el.tagName === 'IMG') {
              imgContainer = el.parentElement;
              var imgRect = el.getBoundingClientRect();
              var wrapRect = wrap.getBoundingClientRect();
              handle.style.left = (imgRect.right - wrapRect.left - 14) + 'px';
              handle.style.top = (imgRect.bottom - wrapRect.top - 14) + 'px';
              handle.style.opacity = '1';
              return;
            }
          }
          imgContainer = null;
          handle.style.opacity = '0';
          handle.style.pointerEvents = 'none';
        }

        function getImgFromSelection() {
          var state = editor.state;
          var sel = state.selection;
          if (!sel.node || !sel.node.type || sel.node.type.name !== 'image') return null;
          var view = editor.view;
          var domPos = view.domAtPos(sel.from);
          var el = domPos.node.nodeType === 3 ? domPos.node.parentElement : domPos.node;
          return el && el.tagName === 'IMG' ? el : null;
        }

        function getImgAndPosForResize() {
          var img = getImgFromSelection();
          var pos = img != null ? editor.state.selection.from : null;
          if (img != null && pos != null) return { img: img, pos: pos };
          if (lastHoveredImg != null && lastHoveredPos != null) return { img: lastHoveredImg, pos: lastHoveredPos };
          return null;
        }

        handle.addEventListener('mousedown', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var info = getImgAndPosForResize();
          if (!info) return;
          var img = info.img;
          var pos = info.pos;
          var rect = img.getBoundingClientRect();
          var startX = e.clientX;
          var startY = e.clientY;
          var startW = rect.width;
          var startH = rect.height;
          resizeStart = { startX: startX, startY: startY, startW: startW, startH: startH, pos: pos, img: img };
          handle.style.pointerEvents = 'none';
          function onMove(ev) {
            if (!resizeStart) return;
            var dx = ev.clientX - resizeStart.startX;
            var dy = ev.clientY - resizeStart.startY;
            var newW = Math.max(MIN_W, Math.round(resizeStart.startW + dx));
            var newH = Math.max(MIN_H, Math.round(resizeStart.startH + dy));
            var ratio = resizeStart.startH / resizeStart.startW;
            newH = Math.max(MIN_H, Math.round(newW * ratio));
            resizeStart.img.style.width = newW + 'px';
            resizeStart.img.style.height = newH + 'px';
          }
          function onUp(ev) {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            handle.style.pointerEvents = '';
            if (resizeStart) {
              var w = parseInt(resizeStart.img.style.width, 10);
              var h = parseInt(resizeStart.img.style.height, 10);
              if (w && h) {
                editor.chain().focus().setNodeSelection(resizeStart.pos).updateAttributes('image', { width: w, height: h }).run();
              }
              resizeStart = null;
            }
            updateHandlePosition();
          }
          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup', onUp);
        });

        wrap.addEventListener('mouseenter', function() { handle.style.pointerEvents = 'auto'; });
        wrap.addEventListener('mousemove', function(e) {
          if (resizeStart) return;
          var img = e.target && e.target.closest ? e.target.closest('img') : null;
          var sel = editor.state.selection;
          var isImgSelected = sel.node && sel.node.type && sel.node.type.name === 'image';
          if (img && wrap.contains(img) && !handle.contains(e.target)) {
            var view = editor.view;
            var pos = view.posAtDOM(img, 0);
            if (pos >= 0) {
              var node = view.state.doc.nodeAt(pos);
              if (node && node.type && node.type.name === 'image') {
                lastHoveredImg = img;
                lastHoveredPos = pos;
                var rect = img.getBoundingClientRect();
                var wrapRect = wrap.getBoundingClientRect();
                handle.style.left = (rect.right - wrapRect.left - 14) + 'px';
                handle.style.top = (rect.bottom - wrapRect.top - 14) + 'px';
                handle.style.opacity = '1';
                return;
              }
            }
          }
          if (!handle.contains(e.target)) {
            lastHoveredImg = null;
            lastHoveredPos = null;
          }
          if (isImgSelected) {
            updateHandlePosition();
          } else {
            handle.style.opacity = '0';
          }
        });
        wrap.addEventListener('click', function(e) {
          var img = e.target && e.target.tagName === 'IMG' ? e.target : null;
          if (img && wrap.contains(img)) {
            var view = editor.view;
            var pos = view.posAtDOM(img, 0);
            if (pos >= 0) {
              var node = view.state.doc.nodeAt(pos);
              if (node && node.type && node.type.name === 'image') {
                editor.chain().focus().setNodeSelection(pos).run();
                setTimeout(updateHandlePosition, 10);
              }
            }
          }
        });

        return new Plugin({
          key: pluginKey,
          view: function() {
            return {
              update: function() { updateHandlePosition(); },
              destroy: function() { if (handle.parentNode) handle.remove(); }
            };
          }
        });
      }

      var KbImageResizeExt = Core.Extension.create({
        name: 'kbImageResize',
        addProseMirrorPlugins: function() {
          var wrap = document.getElementById('kb-editor-wrap');
          if (!wrap) return [];
          return [createKbImageResizePlugin(this.editor, wrap, kbImageResizeKey)];
        }
      });

      var kbDragHandleKey = new PluginKey('kbDragHandle');
      var KbDragHandleExt = Core.Extension.create({
        name: 'kbDragHandle',
        addProseMirrorPlugins: function() {
          var editor = this.editor;
          var wrap = document.getElementById('kb-editor-wrap');
          if (!wrap) return [];
          return [createKbDragHandlePlugin(editor, wrap, kbDragHandleKey)];
        }
      });

      var BLOCK_NODE_NAMES = ['paragraph', 'heading', 'blockquote', 'codeBlock', 'horizontalRule', 'table', 'bulletList', 'orderedList', 'taskList', 'callout', 'image'];
      function isBlockNode(node) {
        if (!node || !node.type) return false;
        var name = node.type.name;
        if (BLOCK_NODE_NAMES.indexOf(name) >= 0) return true;
        var g = node.type.spec && node.type.spec.group;
        if (typeof g === 'string' && g.indexOf('block') >= 0) return true;
        if (Array.isArray(g) && g.indexOf('block') >= 0) return true;
        return false;
      }
      function createKbDragHandlePlugin(editor, wrap, pluginKey) {
        var handle = document.createElement('div');
        handle.className = 'kb-drag-handle';
        handle.setAttribute('aria-hidden', 'true');
        handle.title = 'Block verschieben';
        handle.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>';
        handle.style.cssText = 'position:absolute;left:4px;width:20px;height:24px;display:flex;align-items:center;justify-content:center;cursor:grab;opacity:0.5;z-index:50;border-radius:4px;pointer-events:auto;';
        wrap.style.position = 'relative';
        wrap.appendChild(handle);
        var dragFrom = null;
        var dropPos = null;
        var dropLine = document.createElement('div');
        dropLine.className = 'kb-drag-drop-line';
        dropLine.style.cssText = 'position:absolute;left:0;right:0;height:2px;background:#3b82f6;pointer-events:none;z-index:10;display:none;';
        wrap.appendChild(dropLine);
        function getBlockAtPos(view, pos) {
          var $pos = view.state.doc.resolve(pos);
          var best = null;
          for (var d = $pos.depth; d > 0; d--) {
            var node = $pos.node(d);
            if (node.type.name === 'doc') continue;
            if (!isBlockNode(node)) continue;
            best = { node: node, pos: $pos.before(d), depth: d };
            if (d === 1) return best;
          }
          return best;
        }
        function getBlockDomEl(view, block) {
          if (!block) return null;
          var domPos = view.domAtPos(block.pos);
          var el = domPos.node.nodeType === 3 ? domPos.node.parentElement : domPos.node;
          if (!el || !wrap.contains(el)) return null;
          var root = view.dom || wrap;
          while (el.parentElement && el.parentElement !== root) {
            el = el.parentElement;
          }
          return el.parentElement === root ? el : el;
        }
        function posAtCoords(view, clientX, clientY) {
          return (clientX != null && clientY != null) ? view.posAtCoords({ left: clientX, top: clientY }) : null;
        }
        var HANDLE_HALF_H = 12;
        function updateHandlePosition(view, clientX, clientY) {
          var result = posAtCoords(view, clientX, clientY);
          if (!result) return (clientX != null && clientY != null) ? currentBlock : null;
          var block = getBlockAtPos(view, result.pos);
          if (!block) return null;
          var blockEl = getBlockDomEl(view, block);
          if (!blockEl || !blockEl.getBoundingClientRect) return block || null;
          var blockRect = blockEl.getBoundingClientRect();
          var wrapRect = wrap.getBoundingClientRect();
          var blockCenterY = blockRect.top + blockRect.height / 2;
          var top = blockCenterY - wrapRect.top - HANDLE_HALF_H;
          var curTop = parseFloat(handle.style.top) || -999;
          if (Math.abs(curTop - top) < 1) return block;
          handle.style.display = 'flex';
          handle.style.top = Math.round(top) + 'px';
          return block;
        }
        function moveNode(fromPos, fromDepth, toPos) {
          var state = editor.state;
          var from = state.doc.resolve(fromPos);
          var fromStart = from.before(fromDepth);
          var fromEnd = from.after(fromDepth);
          if (toPos >= fromStart && toPos <= fromEnd) return;
          var slice = state.doc.slice(fromStart, fromEnd);
          if (!slice.content.size) return;
          var tr = state.tr;
          tr.delete(fromStart, fromEnd);
          var adj = tr.mapping.map(toPos, -1);
          if (adj < 0) adj = 0;
          if (adj > tr.doc.content.size) adj = tr.doc.content.size;
          try {
            tr.replaceRange(adj, adj, slice);
            editor.view.dispatch(tr);
          } catch (err) {
            console.error('KB drag: move failed', err);
          }
        }
        var currentBlock = null;
        var moveRaf = null;
        var lastMove = { x: 0, y: 0 };
        wrap.addEventListener('mousemove', function(e) {
          if (dragFrom) return;
          var view = editor.view;
          if (!view || !wrap.contains(e.target)) return;
          lastMove.x = e.clientX;
          lastMove.y = e.clientY;
          if (handle.contains(e.target)) return;
          if (moveRaf) return;
          moveRaf = requestAnimationFrame(function() {
            moveRaf = null;
            var b = updateHandlePosition(view, lastMove.x, lastMove.y);
            if (b) currentBlock = b;
          });
        });
        wrap.addEventListener('mouseleave', function() {
          if (!dragFrom) handle.style.display = 'none';
        });
        function onScrollForHandle() {
          if (currentBlock && !dragFrom && lastMove.x != null && lastMove.y != null) {
            var view = editor.view;
            if (view) updateHandlePosition(view, lastMove.x, lastMove.y);
          }
        }
        setTimeout(function() {
          var view = editor.view;
          if (view) {
            if (view.dom && view.dom !== wrap) {
              view.dom.addEventListener('scroll', onScrollForHandle, { passive: true });
            }
            var rect = wrap.getBoundingClientRect();
            var cy = rect.top + 40;
            var cx = rect.left + (rect.width / 2);
            updateHandlePosition(view, cx, cy);
          }
        }, 150);
        handle.addEventListener('mousedown', function(e) {
          e.preventDefault();
          if (!currentBlock) return;
          dragFrom = { pos: currentBlock.pos, depth: currentBlock.depth };
          handle.style.cursor = 'grabbing';
          handle.style.opacity = '0.8';
          dropLine.style.display = 'block';
          function onMove(ev) {
            var result = editor.view.posAtCoords({ left: ev.clientX, top: ev.clientY });
            if (result) {
              dropPos = result.pos;
              var coords = editor.view.coordsAtPos(dropPos);
              var wrect = wrap.getBoundingClientRect();
              dropLine.style.top = Math.round(coords.top - wrect.top - 1) + 'px';
            }
          }
          function onUp(ev) {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            handle.style.cursor = 'grab';
            handle.style.opacity = '0.4';
            dropLine.style.display = 'none';
            if (dragFrom && dropPos != null) {
              var state = editor.state;
              var $drop = state.doc.resolve(dropPos);
              var insertPos = $drop.depth >= 1 ? $drop.before(1) : 0;
              moveNode(dragFrom.pos, dragFrom.depth, insertPos);
            }
            dragFrom = null;
            dropPos = null;
          }
          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup', onUp);
        });
        return new Plugin({
          key: pluginKey,
          view: function() {
            return {
              update: function() {},
              destroy: function() {
                if (handle.parentNode) handle.remove();
                if (dropLine.parentNode) dropLine.remove();
              }
            };
          }
        });
      }

      const exts = [
        PageCreateExt,
        StarterKit.configure({ codeBlock: { HTMLAttributes: { class: 'kb-code-block' } } }),
        Callout,
        ImageWithSize.configure({ inline: false, allowBase64: true }),
        KbImageResizeExt,
        Link.configure({ openOnClick: true, HTMLAttributes: { target: null, rel: 'noopener' } }),
        TaskList,
        TaskItem.configure({ nested: true }),
        Table.configure({ resizable: true }),
        TableRow,
        TableHeader,
        TableCell,
        Gapcursor,
        Dropcursor.configure({ color: '#94a3b8', width: 1 }),
        PlaceholderExt.configure({ placeholder: 'Tippe \'/\' für Befehle…' }),
        KbDragHandleExt
      ];

      const editor = new Core.Editor({
        element: wrap,
        content: content,
        extensions: exts,
        editorProps: {
          attributes: { class: 'ProseMirror kb-editor-body focus:outline-none min-h-[200px]' },
          handleKeyDown(view, event) {
            if (event.key === 'Escape' && kbPageCreateMode) { kbPageCreateMode = false; return false; }
            if (event.key === 'Enter' && kbPageCreateMode) {
              event.preventDefault();
              var from = editor.state.selection.from;
              var $pos = editor.state.doc.resolve(from);
              var node = $pos.parent;
              var rawText = (node.textContent || '').trim();
              var title = rawText.replace(/^\u{1F4C4}\s*/u, '').replace(/^📄\s*/, '').trim();
              kbPageCreateMode = false;
              if (!title) { editor.chain().focus().splitBlock().run(); return true; }
              var depth = $pos.depth;
              var delFrom = $pos.before(depth);
              var delTo = $pos.after(depth);
              var parentNode = depth >= 1 ? $pos.node(depth - 1) : null;
              if (parentNode && (parentNode.type.name === 'blockquote' || parentNode.type.name === 'callout')) {
                delFrom = $pos.before(depth - 1);
                delTo = $pos.after(depth - 1);
              }
              var pagesUrl = cfg.baseUrl + 'knowledge/api/pages.php';
              fetch(pagesUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title: title, parent_id: currentPageId || null }),
                credentials: 'same-origin'
              }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success && d.id) {
                  var href = cfg.baseUrl + 'knowledge/?id=' + encodeURIComponent(d.id);
                  var linkNode = { type: 'paragraph', content: [{ type: 'text', text: title, marks: [{ type: 'link', attrs: { href: href, target: '_self' } }] }] };
                  editor.chain().focus().deleteRange({ from: delFrom, to: delTo }).insertContent(linkNode).run();
                  showToast('Seite erstellt: ' + title);
                  if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null; }
                  saveContent();
                  try { if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'kb-pages-updated' }, window.location.origin); } catch (e) {}
                } else {
                  showToast(d.error || 'Fehler beim Erstellen', 'error');
                  editor.chain().focus().splitBlock().run();
                }
              }).catch(function(err) {
                showToast('Fehler beim Erstellen der Seite', 'error');
                editor.chain().focus().splitBlock().run();
              });
              return true;
            }
            const menu = window.kbSlashMenu;
            if (!menu || !menu.show) return false;
            if (event.key === 'Escape') { menu.hide(); return true; }
            if (event.key === 'ArrowDown') { menu.next(); return true; }
            if (event.key === 'ArrowUp') { menu.prev(); return true; }
            if (event.key === 'Enter' && document.getElementById('kb-slash-menu') && !document.getElementById('kb-slash-menu').classList.contains('hidden')) {
              menu.select(); return true;
            }
            return false;
          }
        },
        onTransaction() {
          debouncedSave();
        }
      });

      editorInstance = editor;
      setupSlashMenu(editor);
      setupTableToolbar(editor);

      document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        try {
          var state = editor.state;
          var from = state.selection.from;
          var $pos = state.doc.resolve(from);
          var inPageCallout = false;
          for (var d = $pos.depth; d >= 0; d--) {
            var n = $pos.node(d);
            if (n.type && n.type.name === 'callout' && n.attrs && n.attrs.type === 'page') {
              inPageCallout = true;
              break;
            }
          }
          if (!inPageCallout) return;
          e.preventDefault();
          e.stopImmediatePropagation();
          kbPageCreateMode = false;
          var node = $pos.parent;
          var rawText = (node.textContent || '').trim();
          var title = rawText.replace(/^\u{1F4C4}\s*/u, '').replace(/^📄\s*/, '').trim();
          if (!title) { editor.chain().focus().splitBlock().run(); return; }
          var depth = $pos.depth;
          var delFrom = $pos.before(depth);
          var delTo = $pos.after(depth);
          var pn = depth >= 1 ? $pos.node(depth - 1) : null;
          if (pn && (pn.type.name === 'blockquote' || pn.type.name === 'callout')) {
            delFrom = $pos.before(depth - 1);
            delTo = $pos.after(depth - 1);
          }
          fetch(cfg.baseUrl + 'knowledge/api/pages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title: title, parent_id: currentPageId || null }),
            credentials: 'same-origin'
          }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success && d.id) {
              var href = cfg.baseUrl + 'knowledge/?id=' + encodeURIComponent(d.id);
              var linkNode = { type: 'paragraph', content: [{ type: 'text', text: title, marks: [{ type: 'link', attrs: { href: href, target: '_self' } }] }] };
              editor.chain().focus().deleteRange({ from: delFrom, to: delTo }).insertContent(linkNode).run();
              showToast('Seite erstellt: ' + title);
              if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null; }
              saveContent();
              try { if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'kb-pages-updated' }, window.location.origin); } catch (e) {}
            } else {
              showToast(d.error || 'Fehler beim Erstellen', 'error');
              editor.chain().focus().splitBlock().run();
            }
          }).catch(function() {
            showToast('Fehler beim Erstellen der Seite', 'error');
            editor.chain().focus().splitBlock().run();
          });
        } catch (err) {}
      }, true);

      editor.on('selectionUpdate', function() {
        if (kbPageCreateMode) return;
        const { from } = editor.state.selection;
        const $pos = editor.state.doc.resolve(from);
        const text = $pos.parent.textContent;
        const slashIdx = text.lastIndexOf('/');
        /* Slash-Menü nur, wenn "/" am echten Zeilenanfang steht – sonst "/" als normaler Text */
        if (slashIdx === 0) {
          const startOfBlock = $pos.start();
          const slashFromPos = startOfBlock + slashIdx;
          const rect = editor.view.coordsAtPos(from);
          window.kbSlashMenu.setFilter(text.slice(slashIdx + 1));
          window.kbSlashMenu.show(rect, text.slice(slashIdx + 1), slashFromPos);
        } else if (window.kbSlashMenu) window.kbSlashMenu.hide();
      });

      var titleEl = document.getElementById('kb-page-title');
      if (titleEl) titleEl.addEventListener('blur', debouncedSave);
      var createBtn = document.getElementById('kb-create-page-btn');
      if (createBtn) createBtn.addEventListener('click', function() { createPageThenSave(); });

      initHistoryModal(cfg);

      document.addEventListener('click', function(e) {
        if (window.parent === window) return;
        var a = e.target && e.target.closest ? e.target.closest('a') : null;
        if (!a || !a.href) return;
        try {
          var url = new URL(a.href, window.location.origin);
          if (url.pathname.indexOf('/knowledge') === -1) return;
          var id = url.searchParams.get('id');
          if (!id) return;
          e.preventDefault();
          e.stopPropagation();
          window.parent.postMessage({ type: 'kb-open-page', id: id, title: (a.textContent || '').trim().replace(/^\s*\u{1F4C4}\s*/, '') || null }, window.location.origin);
        } catch (err) {}
      }, true);
    }).catch(function(err) {
      console.error('TipTap load error', err);
      wrap.innerHTML = '<p class="text-red-600 dark:text-red-400">Editor konnte nicht geladen werden. Bitte Seite neu laden.</p>';
    });
  }

  if (cfg.initialContent) {
    initEditor(cfg.initialContent);
  } else {
    initEditor({ type: 'doc', content: [{ type: 'paragraph' }] });
  }
})();
