<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/assets/config.php';
require_once __DIR__ . '/kb_helpers.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$userRole = '';
$userName = '';
try {
    $stmt = $pdo->prepare("SELECT rolle, vorname, nachname, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $userRole = $u['rolle'];
        $userName = trim(($u['vorname'] ?? '') . ' ' . ($u['nachname'] ?? '')) ?: ($u['email'] ?? '');
    }
} catch (PDOException $e) { /* ignore */ }

$canCreate = kb_can_create($userRole);
$canEdit = kb_can_edit($userRole);
$canDelete = kb_can_delete($userRole);
if (!$canCreate && !$canEdit) {
    header('Location: ' . (defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '') . 'knowledge/');
    exit;
}

$companyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null
    ? (int) $_SESSION['selected_company_id'] : null;

$isEdit = isset($_GET['id']) && trim($_GET['id']) !== '';
$page = null;
if ($isEdit) {
    $id = trim($_GET['id']);
    try {
        $companyCond = $companyId !== null ? ' AND p.company_id = :cid' : '';
        $stmt = $pdo->prepare("
            SELECT p.id, p.title, p.slug, p.content, p.parent_id,
                   (SELECT GROUP_CONCAT(t.name ORDER BY t.name) FROM kb_page_tags pt JOIN kb_tags t ON pt.tag_id = t.id WHERE pt.page_id = p.id) AS tags
            FROM kb_pages p WHERE p.id = :id AND p.deleted_at IS NULL" . $companyCond . " LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        if ($companyId !== null) $stmt->bindValue(':cid', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* ignore */ }
    if (!$page) {
        header('Location: ' . (defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '') . 'knowledge/');
        exit;
    }
    $page['tags'] = !empty($page['tags']) ? array_map('trim', explode(',', $page['tags'])) : [];
}

$baseUrl = (defined('BASE_URL') && BASE_URL !== '') ? rtrim(BASE_URL, '/') : '';
$basePath = $baseUrl !== '' ? $baseUrl . '/' : '/';
$dashboardUrl = $basePath . 'dashboard/';
$kbUrl = $basePath . 'knowledge/';
$uploadUrl = $basePath . 'knowledge/api/upload-image.php';
$pagesApiUrl = $basePath . 'knowledge/api/pages.php';
$assetsBase = $basePath . 'assets/';

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
    <main>
        <div class="px-4 py-4">
            <nav class="mb-4 flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                    <li class="inline-flex items-center">
                        <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                            <svg class="me-2.5 h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd"/></svg>
                            Startseite
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                            <a href="<?php echo htmlspecialchars($kbUrl); ?>" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">Wissensdatenbank</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2"><?php echo $isEdit ? 'Bearbeiten' : 'Neue Seite'; ?></span>
                        </div>
                    </li>
                </ol>
            </nav>

            <div class="flex flex-col lg:flex-row gap-6">
                <?php include __DIR__ . '/partials/kb-sidebar.php'; ?>
                <div class="flex-1 min-w-0">
            <form id="kb-edit-form" class="space-y-6">
                <input type="hidden" name="id" value="<?php echo $isEdit && $page ? htmlspecialchars($page['id']) : ''; ?>" />

                <!-- Notion-Style: großer Seitentitel -->
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                    <div class="px-4 pt-6 pb-2">
                        <input type="text" id="kb-title" name="title" required maxlength="255" value="<?php echo $isEdit && $page ? htmlspecialchars($page['title']) : ''; ?>" placeholder="Titel der Seite" class="block w-full border-0 bg-transparent p-0 text-2xl font-bold text-gray-900 placeholder-gray-400 focus:ring-0 dark:text-white dark:placeholder-gray-500" />
                    </div>
                    <!-- Editor: Slash-Befehle (/) + Bubble-Menu bei Auswahl -->
                    <div class="kb-editor-wrap px-4 pb-6" id="kb-editor-wrap">
                        <div id="kb-editor-content" class="min-h-[320px] focus:outline-none kb-editor-root"></div>
                    </div>
                </div>

<style id="kb-editor-styles">
/* Editor-Blocktypen sichtbar: nur innerhalb des Wissensdatenbank-Editors */
#kb-editor-wrap h1,
#kb-editor-wrap .ProseMirror h1 {
  font-size: 1.875rem !important;
  font-weight: 700 !important;
  line-height: 1.2 !important;
  margin: 0.75em 0 0.35em !important;
  display: block !important;
}
#kb-editor-wrap h1:first-child { margin-top: 0 !important; }

#kb-editor-wrap h2,
#kb-editor-wrap .ProseMirror h2 {
  font-size: 1.5rem !important;
  font-weight: 600 !important;
  margin: 0.6em 0 0.3em !important;
  display: block !important;
}

#kb-editor-wrap h3,
#kb-editor-wrap .ProseMirror h3 {
  font-size: 1.25rem !important;
  font-weight: 600 !important;
  margin: 0.5em 0 0.25em !important;
  display: block !important;
}

/* Aufzählungen (ul): sichtbare Bullets per ::before, da Tailwind list-style zurücksetzt */
#kb-editor-wrap ul,
#kb-editor-wrap .ProseMirror ul {
  list-style: none !important;
  padding-left: 0 !important;
  margin: 0.5em 0 0.5em 0 !important;
  display: block !important;
}
#kb-editor-wrap ul:not([data-type="taskList"]) li,
#kb-editor-wrap .ProseMirror ul:not([data-type="taskList"]) li {
  display: block !important;
  margin: 0.15em 0 !important;
  padding-left: 1.25rem !important;
  position: relative !important;
}
#kb-editor-wrap ul:not([data-type="taskList"]) li::before,
#kb-editor-wrap .ProseMirror ul:not([data-type="taskList"]) li::before {
  content: "•" !important;
  position: absolute !important;
  left: 0 !important;
  font-weight: 700 !important;
  color: inherit !important;
}

/* To-do-Listen: ohne Bullet, mit Checkbox-Optik */
#kb-editor-wrap ul[data-type="taskList"],
#kb-editor-wrap .ProseMirror ul[data-type="taskList"] {
  list-style: none !important;
  padding-left: 0 !important;
}
#kb-editor-wrap ul[data-type="taskList"] li,
#kb-editor-wrap .ProseMirror ul[data-type="taskList"] li {
  display: flex !important;
  align-items: flex-start !important;
  gap: 0.5rem !important;
  padding-left: 0 !important;
}
#kb-editor-wrap ul[data-type="taskList"] li::before {
  content: none !important;
}
#kb-editor-wrap ul[data-type="taskList"] li[data-checked="true"] {
  opacity: 0.7 !important;
  text-decoration: line-through !important;
}

/* Nummerierte Listen (ol): Zahlen explizit anzeigen */
#kb-editor-wrap ol,
#kb-editor-wrap .ProseMirror ol {
  list-style: none !important;
  counter-reset: kb-ol !important;
  padding-left: 0 !important;
  margin: 0.5em 0 !important;
  display: block !important;
}
#kb-editor-wrap ol li,
#kb-editor-wrap .ProseMirror ol li {
  display: block !important;
  margin: 0.15em 0 !important;
  padding-left: 2rem !important;
  position: relative !important;
  counter-increment: kb-ol !important;
}
#kb-editor-wrap ol li::before,
#kb-editor-wrap .ProseMirror ol li::before {
  content: counter(kb-ol) "." !important;
  position: absolute !important;
  left: 0 !important;
  font-weight: 600 !important;
  color: inherit !important;
}

#kb-editor-wrap blockquote,
#kb-editor-wrap .ProseMirror blockquote {
  border-left: 4px solid #3b82f6 !important;
  padding-left: 1rem !important;
  margin: 0.75em 0 !important;
  font-style: italic !important;
  display: block !important;
}
.dark #kb-editor-wrap blockquote,
.dark #kb-editor-wrap .ProseMirror blockquote {
  border-left-color: #60a5fa !important;
}

#kb-editor-wrap pre,
#kb-editor-wrap .ProseMirror pre {
  background: #f1f5f9 !important;
  padding: 1rem !important;
  border-radius: 0.375rem !important;
  font-size: 0.875rem !important;
  line-height: 1.5 !important;
  overflow-x: auto !important;
  margin: 0.75em 0 !important;
  display: block !important;
  white-space: pre-wrap !important;
}
.dark #kb-editor-wrap pre,
.dark #kb-editor-wrap .ProseMirror pre {
  background: #334155 !important;
}

#kb-editor-wrap hr,
#kb-editor-wrap .ProseMirror hr {
  border: none !important;
  border-top: 2px solid #cbd5e1 !important;
  margin: 1.25em 0 !important;
  display: block !important;
  height: 0 !important;
}
.dark #kb-editor-wrap hr,
.dark #kb-editor-wrap .ProseMirror hr {
  border-top-color: #475569 !important;
}

#kb-editor-wrap :not(pre) > code,
#kb-editor-wrap .ProseMirror :not(pre) > code {
  background: #e2e8f0 !important;
  padding: 0.15em 0.4em !important;
  border-radius: 0.25rem !important;
  font-size: 0.875em !important;
  font-family: ui-monospace, monospace !important;
}
.dark #kb-editor-wrap :not(pre) > code,
.dark #kb-editor-wrap .ProseMirror :not(pre) > code {
  background: #475569 !important;
}
</style>

                <!-- Seiteneinstellungen (aufklappbar) -->
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                    <button type="button" id="kb-settings-toggle" class="w-full flex items-center justify-between p-4 text-left text-sm font-medium text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <span>Seiteneinstellungen</span>
                        <svg id="kb-settings-chevron" class="w-5 h-5 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="kb-settings-body" class="hidden border-t border-gray-200 dark:border-gray-700 p-4 space-y-4">
                        <div>
                            <label for="kb-parent" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Übergeordnete Seite</label>
                            <select id="kb-parent" name="parentId" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">— Keine (Hauptebene) —</option>
                                <option value="__loading" disabled>Lade…</option>
                            </select>
                        </div>
                        <div>
                            <label for="kb-tags" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tags (kommagetrennt)</label>
                            <input type="text" id="kb-tags" name="tags" value="<?php echo $isEdit && $page ? htmlspecialchars(implode(', ', $page['tags'])) : ''; ?>" placeholder="z.B. Anleitung, Windows, VPN" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 items-center">
                    <button type="submit" id="kb-submit" class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                        <span class="kb-submit-text"><?php echo $isEdit ? 'Speichern' : 'Erstellen'; ?></span>
                        <span class="kb-submit-spin hidden ml-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </span>
                    </button>
                    <a href="<?php echo htmlspecialchars($kbUrl); ?>" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700 dark:focus:ring-gray-700">Abbrechen</a>
                    <?php if ($isEdit && $canDelete): ?>
                    <button type="button" id="kb-delete-btn" class="ml-auto inline-flex items-center rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50 focus:ring-4 focus:ring-red-200 dark:border-red-800 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:focus:ring-red-800">Löschen</button>
                    <?php endif; ?>
                </div>
            </form>
                </div>
            </div>
        </div>
    </main>
</div>

<input type="file" id="kb-image-input" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" />
<div id="kb-bubble-menu" class="hidden"></div>
<div id="kb-slash-menu" class="hidden fixed rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl overflow-hidden" style="z-index: 9999;"></div>

<script>
window.KB = {
    basePath: <?php echo json_encode($basePath); ?>,
    canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
    canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
    pagesUrl: <?php echo json_encode($basePath . 'knowledge/api/pages.php'); ?>,
    kbUrl: <?php echo json_encode($kbUrl); ?>
};
</script>
<script src="<?php echo htmlspecialchars($basePath); ?>knowledge/js/kb-sidebar.js"></script>

<script type="module">
import { createNotionEditor } from '<?php echo $basePath; ?>knowledge/js/kb-notion-editor.js';

const basePath = '<?php echo $basePath; ?>' || '/';
const uploadUrl = '<?php echo $uploadUrl; ?>';
const pagesApiUrl = '<?php echo $pagesApiUrl; ?>';
const initialContent = <?php echo json_encode($isEdit && $page && $page['content'] !== null ? $page['content'] : ''); ?>;
const initialParentId = <?php echo json_encode($isEdit && $page ? $page['parent_id'] : (isset($_GET['parent']) ? trim($_GET['parent']) : null)); ?>;
const flatExcludeId = <?php echo json_encode($isEdit && $page ? $page['id'] : null); ?>;

function openImagePicker(editor) {
    document.getElementById('kb-image-input').click();
}

const currentPageId = document.querySelector('input[name="id"]')?.value?.trim() || null;
const editor = await createNotionEditor({
    element: document.querySelector('#kb-editor-content'),
    initialContent: initialContent || '',
    uploadUrl,
    basePath,
    onImageSelect: () => openImagePicker(editor),
    pagesApiUrl,
    currentPageId,
});

const imageInput = document.getElementById('kb-image-input');
imageInput.addEventListener('change', async () => {
    const file = imageInput.files && imageInput.files[0];
    if (!file) return;
    imageInput.value = '';
    const fd = new FormData();
    fd.append('file', file);
    try {
        const r = await fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (d.success && d.url) {
            const imgUrl = basePath + (d.url.startsWith('/') ? d.url.slice(1) : d.url);
            editor.chain().focus().setImage({ src: imgUrl }).run();
            if (typeof showToast === 'function') showToast('Bild eingefügt', 'success');
        } else {
            if (typeof showToast === 'function') showToast(d.error || 'Upload fehlgeschlagen', 'error');
        }
    } catch (e) {
        if (typeof showToast === 'function') showToast('Upload fehlgeschlagen', 'error');
    }
});

// Seiteneinstellungen aufklappbar
document.getElementById('kb-settings-toggle').addEventListener('click', () => {
    const body = document.getElementById('kb-settings-body');
    const chevron = document.getElementById('kb-settings-chevron');
    body.classList.toggle('hidden');
    chevron.classList.toggle('rotate-180');
});

const parentSelect = document.getElementById('kb-parent');
(async () => {
    let url = pagesApiUrl + '?flat=1';
    if (flatExcludeId) url += '&exclude=' + encodeURIComponent(flatExcludeId);
    try {
        const r = await fetch(url, { credentials: 'same-origin' });
        const d = await r.json();
        const loading = parentSelect.querySelector('option[value="__loading"]');
        if (loading) loading.remove();
        if (d.success && d.pages) {
            d.pages.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = (p.depth ? '  '.repeat(p.depth) + '↳ ' : '') + (p.title || 'Ohne Titel');
                parentSelect.appendChild(opt);
            });
        }
        if (initialParentId) parentSelect.value = initialParentId;
        else parentSelect.value = '';
    } catch (e) {
        const loading = parentSelect.querySelector('option[value="__loading"]');
        if (loading) loading.textContent = 'Fehler beim Laden';
    }
})();

const form = document.getElementById('kb-edit-form');
const submitBtn = document.getElementById('kb-submit');
const submitText = document.querySelector('.kb-submit-text');
const submitSpin = document.querySelector('.kb-submit-spin');

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = document.getElementById('kb-title').value.trim();
    const tagsRaw = document.getElementById('kb-tags').value.trim();
    const tags = tagsRaw ? tagsRaw.split(',').map(t => t.trim()).filter(Boolean) : [];
    const content = editor.getHTML();
    const pageId = form.querySelector('input[name="id"]').value;
    const parentId = parentSelect.value === '' ? null : parentSelect.value;

    submitBtn.disabled = true;
    submitText.classList.add('hidden');
    submitSpin.classList.remove('hidden');

    try {
        const url = pagesApiUrl;
        const opts = {
            method: pageId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: pageId || undefined, title, content, tags, parentId }),
            credentials: 'same-origin',
        };
        const r = await fetch(url, opts);
        let d;
        try {
            d = await r.json();
        } catch (_) {
            if (typeof showToast === 'function') showToast(r.status === 404 ? 'API nicht gefunden.' : 'Ungültige Serverantwort.', 'error');
            return;
        }
        if (d.success) {
            if (typeof showToast === 'function') showToast(d.message || 'Gespeichert', 'success');
            if (d.page && d.page.id) {
              if (!pageId) window.location.href = basePath + 'knowledge/edit.php?id=' + encodeURIComponent(d.page.id);
              else window.location.reload();
            }
        } else {
            if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
        }
    } catch (err) {
        if (typeof showToast === 'function') showToast('Netzwerkfehler: ' + (err.message || 'Verbindung fehlgeschlagen'), 'error');
    } finally {
        submitBtn.disabled = false;
        submitText.classList.remove('hidden');
        submitSpin.classList.add('hidden');
    }
});

// Nach Erstellen einer Seite per Slash: aktuellen Inhalt (inkl. neuer Link) still speichern, damit der Link erhalten bleibt
document.addEventListener('kb-editor-request-save', async () => {
    const pageId = form.querySelector('input[name="id"]').value;
    if (!pageId) return;
    const title = document.getElementById('kb-title').value.trim();
    const tagsRaw = document.getElementById('kb-tags').value.trim();
    const tags = tagsRaw ? tagsRaw.split(',').map(t => t.trim()).filter(Boolean) : [];
    const content = editor.getHTML();
    const parentId = parentSelect.value === '' ? null : parentSelect.value;
    try {
        const r = await fetch(pagesApiUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: pageId, title: title || 'Ohne Titel', content, tags, parentId }),
            credentials: 'same-origin',
        });
        await r.json();
    } catch (_) {}
});

const deleteBtn = document.getElementById('kb-delete-btn');
const pageId = document.querySelector('input[name="id"]') && document.querySelector('input[name="id"]').value;
if (deleteBtn && pageId && typeof window.confirm === 'function') {
    deleteBtn.addEventListener('click', async () => {
        if (!window.confirm('Seite wirklich löschen? Dies kann später ggf. wiederhergestellt werden.')) return;
        deleteBtn.disabled = true;
        try {
            const r = await fetch(pagesApiUrl + '?id=' + encodeURIComponent(pageId), { method: 'DELETE', credentials: 'same-origin' });
            const d = await r.json();
            if (d.success) {
                if (typeof showToast === 'function') showToast(d.message || 'Gelöscht', 'success');
                window.location.href = basePath + 'knowledge/';
            } else {
                if (typeof showToast === 'function') showToast(d.error || 'Fehler', 'error');
                deleteBtn.disabled = false;
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast('Fehler', 'error');
            deleteBtn.disabled = false;
        }
    });
}
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
