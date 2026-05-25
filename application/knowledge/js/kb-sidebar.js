(function() {
    const cfg = window.KB;
    if (!cfg || !cfg.pagesUrl) return;

    const basePath = cfg.basePath || '/';
    const canEdit = !!cfg.canEdit;
    const canCreate = !!cfg.canCreate;
    const pagesUrl = cfg.pagesUrl;

    const $ = (id) => document.getElementById(id);
    const treeEl = $('kb-tree');
    if (!treeEl) return;

    const iconChevronDown = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
    const iconChevronRight = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
    const iconDotsVertical = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>';

    function renderTree(nodes, depth) {
        depth = depth || 0;
        if (!nodes || !nodes.length) return '<p class="text-gray-500 dark:text-gray-400 py-2">Keine Seiten.</p>';
        let html = '';
        nodes.forEach(function(n) {
            const hasChildren = (n.children && n.children.length) > 0;
            const pad = depth * 16;
            const hasParent = n.parent_id != null && n.parent_id !== '';
            const showMenu = canEdit || canCreate;
            html += '<div class="kb-tree-item flex flex-col w-full" data-id="' + (n.id || '') + '" data-slug="' + (n.slug || '') + '" data-parent-id="' + (n.parent_id || '') + '">';
            html += '<div class="flex w-full min-w-0 items-center gap-1 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700" style="padding-left: ' + pad + 'px">';
            if (hasChildren) {
                html += '<button type="button" class="kb-toggle shrink-0 w-6 h-6 flex items-center justify-center rounded text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-600 dark:text-gray-400" aria-label="Aufklappen">';
                html += '<span class="kb-toggle-expanded">' + iconChevronDown + '</span>';
                html += '<span class="kb-toggle-collapsed hidden">' + iconChevronRight + '</span>';
                html += '</button>';
            } else {
                html += '<span class="shrink-0 w-6" aria-hidden="true"></span>';
            }
            const vUrl = basePath + 'knowledge/edit.php?id=' + encodeURIComponent(n.id || '');
            html += '<a href="' + vUrl + '" class="truncate flex-1 min-w-0 text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400">' + (n.title || 'Ohne Titel') + '</a>';
            if (showMenu) {
                html += '<div class="relative shrink-0">';
                html += '<button type="button" class="kb-tree-menu-btn w-7 h-7 flex items-center justify-center rounded text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-600 dark:text-gray-400" title="Menü">' + iconDotsVertical + '</button>';
                html += '<div class="kb-tree-menu hidden absolute right-0 top-full mt-0.5 min-w-40 py-1 bg-white dark:bg-gray-700 rounded-lg shadow border border-gray-200 dark:border-gray-600 z-50">';
                html += '<a href="' + basePath + 'knowledge/edit.php?id=' + encodeURIComponent(n.id || '') + '" class="block px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">Bearbeiten</a>';
                if (canCreate) html += '<a href="' + basePath + 'knowledge/edit.php?parent=' + encodeURIComponent(n.id || '') + '" class="block px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">Neue Unterseite</a>';
                if (canEdit && hasParent) html += '<button type="button" class="kb-move-root w-full text-left px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" data-id="' + (n.id || '') + '">Zu Root verschieben</button>';
                if (canEdit) html += '<button type="button" class="kb-move-open w-full text-left px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" data-id="' + (n.id || '') + '">Verschieben nach…</button>';
                if (canEdit) html += '<button type="button" class="kb-delete-page w-full text-left px-3 py-1.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20" data-id="' + (n.id || '') + '" data-title="' + (n.title || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;') + '">Löschen</button>';
                html += '</div></div>';
            }
            html += '</div>';
            if (hasChildren) {
                html += '<div class="kb-tree-children flex flex-col gap-0.5 ml-1 pl-1 border-l border-gray-200 dark:border-gray-600">' + renderTree(n.children, depth + 1) + '</div>';
            }
            html += '</div>';
        });
        return html;
    }

    function toggleTree(ev) {
        const btn = ev.target.closest('.kb-toggle');
        if (!btn) return;
        ev.preventDefault();
        const item = btn.closest('.kb-tree-item');
        const children = item && item.querySelector('.kb-tree-children');
        if (!children) return;
        const hidden = children.classList.toggle('hidden');
        const exp = btn.querySelector('.kb-toggle-expanded');
        const col = btn.querySelector('.kb-toggle-collapsed');
        if (exp) exp.classList.toggle('hidden', hidden);
        if (col) col.classList.toggle('hidden', !hidden);
    }

    function closeAllMenus() {
        treeEl.querySelectorAll('.kb-tree-menu').forEach(function(m) { m.classList.add('hidden'); });
    }

    function setupTreeListeners() {
        treeEl.querySelectorAll('.kb-toggle').forEach(function(el) { el.addEventListener('click', toggleTree); });
        treeEl.querySelectorAll('.kb-tree-menu-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const menu = btn.closest('.relative').querySelector('.kb-tree-menu');
                const open = !menu.classList.contains('hidden');
                closeAllMenus();
                if (!open) menu.classList.remove('hidden');
            });
        });
        treeEl.querySelectorAll('.kb-tree-menu').forEach(function(menu) {
            menu.addEventListener('click', function(e) { e.stopPropagation(); });
        });
    }

    document.addEventListener('click', function() { closeAllMenus(); });

    function loadTree() {
        fetch(pagesUrl + '?tree=1', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && d.tree) {
                    treeEl.innerHTML = renderTree(d.tree);
                    setupTreeListeners();
                } else {
                    treeEl.innerHTML = '<p class="text-gray-500 dark:text-gray-400 py-2">Keine Seiten.</p>';
                }
            })
            .catch(function() {
                treeEl.innerHTML = '<p class="text-red-500 text-sm">Fehler beim Laden.</p>';
            });
    }

    var moveTargetId = null;
    var moveModal = $('kb-move-modal');
    var moveTargetSelect = $('kb-move-target');
    var moveCancelBtn = $('kb-move-cancel');
    var moveSubmitBtn = $('kb-move-submit');

    if (moveModal && moveTargetSelect && moveCancelBtn && moveSubmitBtn) {
        treeEl.addEventListener('click', function(e) {
            var delBtn = e.target.closest('.kb-delete-page');
            if (delBtn && canEdit) {
                e.preventDefault();
                closeAllMenus();
                var id = delBtn.dataset.id;
                var title = delBtn.dataset.title || '';
                if (!id) return;
                if (!confirm('Seite „' + title + '“ wirklich löschen?')) return;
                fetch(pagesUrl, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }), credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) {
                            if (typeof showToast === 'function') showToast('Seite gelöscht', 'success');
                            loadTree();
                            var currentId = (typeof window.location !== 'undefined' && window.location.search) ? new URLSearchParams(window.location.search).get('id') : null;
                            if (currentId === id) {
                                window.location.href = basePath + 'knowledge/';
                            }
                        } else {
                            if (typeof showToast === 'function') showToast(d.error || 'Löschen fehlgeschlagen', 'error');
                        }
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Löschen fehlgeschlagen', 'error');
                    });
                return;
            }
            var rootBtn = e.target.closest('.kb-move-root');
            var openBtn = e.target.closest('.kb-move-open');
            if (rootBtn && canEdit) {
                e.preventDefault();
                closeAllMenus();
                var id = rootBtn.dataset.id;
                if (!id) return;
                fetch(pagesUrl, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, parent_id: null }), credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) {
                            if (typeof showToast === 'function') showToast(d.message || 'Verschoben', 'success');
                            loadTree();
                        } else {
                            if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
                        }
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Netzwerkfehler', 'error');
                    });
                return;
            }
            if (openBtn && canEdit) {
                e.preventDefault();
                closeAllMenus();
                moveTargetId = openBtn.dataset.id;
                if (!moveTargetId) return;
                moveModal.classList.remove('hidden');
                moveTargetSelect.innerHTML = '<option value="">— Keine (Root / Hauptebene) —</option><option value="__loading">Lade…</option>';
                fetch(pagesUrl + '?flat=1&exclude=' + encodeURIComponent(moveTargetId), { credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        moveTargetSelect.innerHTML = '<option value="">— Keine (Root / Hauptebene) —</option>';
                        if (d.success && d.pages) d.pages.forEach(function(p) {
                            var o = document.createElement('option');
                            o.value = p.id;
                            o.textContent = (p.depth ? '  '.repeat(p.depth) + '↳ ' : '') + (p.title || 'Ohne Titel');
                            moveTargetSelect.appendChild(o);
                        });
                    })
                    .catch(function() {
                        moveTargetSelect.innerHTML = '<option value="">Fehler beim Laden</option>';
                    });
                return;
            }
        });

        moveCancelBtn.addEventListener('click', function() { moveModal.classList.add('hidden'); moveTargetId = null; });
        moveModal.addEventListener('click', function(e) { if (e.target === moveModal) { moveModal.classList.add('hidden'); moveTargetId = null; } });

        moveSubmitBtn.addEventListener('click', function() {
            if (!moveTargetId) return;
            var parentId = moveTargetSelect.value === '' ? null : moveTargetSelect.value;
            moveSubmitBtn.disabled = true;
            fetch(pagesUrl, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: moveTargetId, parent_id: parentId === '' ? null : parentId }), credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        if (typeof showToast === 'function') showToast(d.message || 'Verschoben', 'success');
                        moveModal.classList.add('hidden');
                        moveTargetId = null;
                        loadTree();
                    } else {
                        if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast('Netzwerkfehler', 'error');
                })
                .then(function() { moveSubmitBtn.disabled = false; });
        });
    }

    loadTree();

    // Bei neuer Seite (z. B. per Slash „Seite“) Sidebar aktualisieren
    document.addEventListener('kb-pages-updated', function() {
        loadTree();
    });
})();
