<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        header('Location: ' . BASE_URL . 'dashboard/');
        exit;
    }
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
$pageId = isset($_GET['id']) ? trim($_GET['id']) : null;
if ($pageId === '' || $pageId === 'new') $pageId = null;
$parentId = isset($_GET['parent_id']) ? trim($_GET['parent_id']) : null;
if ($parentId === '') $parentId = null;
$page = null;
$breadcrumbPath = [];
if ($pageId) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, slug, content, content_type, parent_id, company_id, is_system_folder, system_type, created_at, updated_at FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$pageId]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($page) {
            $currentParentId = $page['parent_id'];
            while ($currentParentId) {
                $stmt = $pdo->prepare("SELECT id, title, parent_id FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                $stmt->execute([$currentParentId]);
                $ancestor = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$ancestor) break;
                array_unshift($breadcrumbPath, ['id' => $ancestor['id'], 'title' => $ancestor['title']]);
                $currentParentId = $ancestor['parent_id'];
            }
        }
    } catch (PDOException $e) {}
}

$kbPageTitle = $page ? $page['title'] : 'Neue Seite';
$isCompanyFolder = $pageId && $page && empty($page['parent_id']) && !empty($page['company_id']);
$isSystemFolder = $pageId && $page && !empty($page['is_system_folder']);

$pathFromUrl = [];
if (!empty($_GET['path'])) {
    $raw = $_GET['path'];
    if (is_string($raw)) {
        $dec = json_decode($raw, true);
        $pathFromUrl = is_array($dec) ? $dec : [];
    }
}

$baseUrl = BASE_URL;
$apiSave = $baseUrl . 'knowledge/api/save.php';
$apiUpload = $baseUrl . 'knowledge/api/upload.php';

$initialTitle = $page ? $page['title'] : '';
$initialContent = $page ? $page['content'] : null;
$initialContentType = ($page && isset($page['content_type'])) ? $page['content_type'] : 'json';
if ($initialContent && $initialContentType === 'html') {
    $initialContent = null;
}
if ($initialContent && is_string($initialContent)) {
    $dec = json_decode($initialContent, true);
    $initialContent = $dec ? $dec : null;
}
if (!$initialContent) {
    $initialContent = array('type' => 'doc', 'content' => array(array('type' => 'paragraph')));
}

if ($embed) {
    $pageTitle = isset($kbPageTitle) ? $kbPageTitle : 'Seite';
    include dirname(__DIR__) . '/assets/frontend/head.php';
    ?>
<script>
(function(){ try { if (<?php echo (defined('DARK_MODE_ENABLED') && DARK_MODE_ENABLED) ? 'true' : 'false'; ?> && window.parent && window.parent.document && window.parent.document.documentElement.classList.contains('dark')) document.documentElement.classList.add('dark'); } catch(e) {} })();
</script>
<style>
.kb-editor-wrap { position: relative; }
.kb-editor-wrap .ProseMirror { min-height: 200px; padding-left: 2rem; color: #111827; line-height: 1.65; }
.kb-editor-wrap .ProseMirror > * + * { margin-top: 0.5em; }
.kb-editor-wrap .ProseMirror h1 { font-size: 1.75rem; margin-top: 1.25em; margin-bottom: 0.5em; }
.kb-editor-wrap .ProseMirror h2 { font-size: 1.375rem; margin-top: 1em; margin-bottom: 0.375em; }
.kb-editor-wrap .ProseMirror h3 { font-size: 1.125rem; margin-top: 0.875em; margin-bottom: 0.25em; }
.dark .kb-editor-wrap .ProseMirror { color: #e2e8f0; }
.kb-editor-wrap .kb-drag-handle { opacity: 0.5; cursor: grab; color: #64748b; transition: opacity 0.15s, background 0.15s; }
.kb-editor-wrap .kb-drag-handle:hover { opacity: 0.9; background: rgba(0,0,0,0.06); }
.kb-editor-wrap .kb-drag-handle:active { cursor: grabbing; }
.dark .kb-editor-wrap .kb-drag-handle { color: #94a3b8; }
.kb-editor-wrap .ProseMirror a { color: #2563eb; text-decoration: underline; cursor: pointer; }
.kb-editor-wrap .ProseMirror a:hover { color: #1d4ed8; }
.kb-editor-wrap .ProseMirror a[href*="knowledge/"]::before { content: "\01F4C4 "; }
.kb-editor-wrap .ProseMirror img { border-radius: 0.375rem; transition: box-shadow 0.15s; max-width: 100%; }
.kb-editor-wrap .ProseMirror img:hover { box-shadow: 0 0 0 2px #3b82f6; }
.dark .kb-editor-wrap .ProseMirror img:hover { box-shadow: 0 0 0 2px #60a5fa; }
.kb-img-resize-handle { background: #3b82f6 !important; }
.dark .kb-img-resize-handle { background: #60a5fa !important; }
/* Container/Callouts – deutlich sichtbar (Attribut + Klasse, mit !important) */
#kb-editor-wrap [data-callout],
#kb-editor-wrap .kb-callout,
.kb-editor-wrap [data-callout],
.kb-editor-wrap .kb-callout {
  display: block !important;
  padding: 0.75rem 1rem !important;
  margin: 0.75em 0 !important;
  border-radius: 0.5rem !important;
  border-left: 5px solid !important;
  box-sizing: border-box !important;
  min-height: 2.5rem !important;
}
#kb-editor-wrap [data-callout="default"],
#kb-editor-wrap .kb-callout-default,
.kb-editor-wrap [data-callout="default"],
.kb-editor-wrap .kb-callout-default { background-color: #e2e8f0 !important; border-left-color: #64748b !important; }
#kb-editor-wrap [data-callout="warning"],
#kb-editor-wrap .kb-callout-warning,
.kb-editor-wrap [data-callout="warning"],
.kb-editor-wrap .kb-callout-warning { background-color: #fde68a !important; border-left-color: #d97706 !important; }
#kb-editor-wrap [data-callout="error"],
#kb-editor-wrap .kb-callout-error,
.kb-editor-wrap [data-callout="error"],
.kb-editor-wrap .kb-callout-error { background-color: #fecaca !important; border-left-color: #dc2626 !important; }
#kb-editor-wrap [data-callout="success"],
#kb-editor-wrap .kb-callout-success,
.kb-editor-wrap [data-callout="success"],
.kb-editor-wrap .kb-callout-success { background-color: #bbf7d0 !important; border-left-color: #16a34a !important; }
#kb-editor-wrap [data-callout="page"],
#kb-editor-wrap .kb-callout-page,
.kb-editor-wrap [data-callout="page"],
.kb-editor-wrap .kb-callout-page { background-color: #bfdbfe !important; border-left-color: #2563eb !important; }
.dark #kb-editor-wrap [data-callout="default"],
.dark #kb-editor-wrap .kb-callout-default,
.dark .kb-editor-wrap [data-callout="default"],
.dark .kb-editor-wrap .kb-callout-default { background-color: #334155 !important; border-left-color: #94a3b8 !important; }
.dark #kb-editor-wrap [data-callout="warning"],
.dark #kb-editor-wrap .kb-callout-warning,
.dark .kb-editor-wrap [data-callout="warning"],
.dark .kb-editor-wrap .kb-callout-warning { background-color: #451a03 !important; border-left-color: #fbbf24 !important; }
.dark #kb-editor-wrap [data-callout="error"],
.dark #kb-editor-wrap .kb-callout-error,
.dark .kb-editor-wrap [data-callout="error"],
.dark .kb-editor-wrap .kb-callout-error { background-color: #450a0a !important; border-left-color: #f87171 !important; }
.dark #kb-editor-wrap [data-callout="success"],
.dark #kb-editor-wrap .kb-callout-success,
.dark .kb-editor-wrap [data-callout="success"],
.dark .kb-editor-wrap .kb-callout-success { background-color: #052e16 !important; border-left-color: #4ade80 !important; }
.dark #kb-editor-wrap [data-callout="page"],
.dark #kb-editor-wrap .kb-callout-page,
.dark .kb-editor-wrap [data-callout="page"],
.dark .kb-editor-wrap .kb-callout-page { background-color: #1e3a5f !important; border-left-color: #60a5fa !important; }
#kb-editor-wrap .kb-callout-inner,
.kb-editor-wrap .kb-callout-inner { background: transparent !important; }
.dark .kb-editor-wrap .ProseMirror a { color: #60a5fa; }
.dark .kb-editor-wrap .ProseMirror a:hover { color: #93c5fd; }
/* Embed: gesamtes Dokument scrollbar, Footer immer erreichbar */
html { width: 100% !important; max-width: none !important; height: auto !important; min-height: 100% !important; overflow-y: scroll !important; overflow-x: hidden !important; -webkit-overflow-scrolling: touch; }
body.kb-embed { margin: 0 !important; padding-bottom: 8rem !important; text-align: left !important; width: 100% !important; max-width: none !important; box-sizing: border-box !important; height: auto !important; min-height: 100% !important; overflow-y: visible !important; overflow-x: hidden !important; }
body.kb-embed > div:first-of-type { width: 100% !important; max-width: none !important; margin: 0 !important; box-sizing: border-box !important; display: block !important; box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04); border-radius: 0.75rem !important; overflow: visible !important; }
.dark body.kb-embed > div:first-of-type { box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
</style>
<body class="kb-embed bg-gray-50 dark:bg-primary-50 min-h-screen text-left">
  <div class="bg-white dark:bg-primary-100 rounded-xl border border-gray-200 dark:border-primary-120 shadow-sm" style="width:100%;max-width:none;margin:0;">
    <div class="px-5 py-3 md:px-6 md:py-4 border-b border-gray-100 dark:border-primary-120">
      <?php if ($isCompanyFolder || $isSystemFolder): ?>
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <p class="text-xl md:text-2xl font-bold text-gray-900 dark:text-primary-200 m-0 flex-1 min-w-0"><?php echo htmlspecialchars($initialTitle); ?></p>
        <?php if ($isCompanyFolder): ?>
        <a href="<?php echo htmlspecialchars($baseUrl . 'companies/detail.php?id=' . (int)$page['company_id']); ?>" class="inline-flex items-center gap-1.5 shrink-0 py-1.5 px-2.5 rounded-md text-sm text-gray-600 dark:text-primary-210 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" target="_blank" rel="noopener" title="Firmen-Details öffnen">
          <svg class="w-4 h-4 shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
          <span>Firmen-Details</span>
        </a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <input type="text" id="kb-page-title" class="w-full text-xl md:text-2xl font-bold bg-transparent border-0 focus:ring-0 focus:outline-none text-gray-900 dark:text-primary-200 placeholder-gray-400 dark:placeholder-primary-240" placeholder="Titel der Seite" value="<?php echo htmlspecialchars($initialTitle); ?>">
      <?php endif; ?>
    </div>
    <div class="px-5 py-5 md:px-6 md:py-6 min-h-[360px]">
      <div id="kb-editor-wrap" class="kb-editor-wrap"></div>
    </div>
    <div class="px-5 md:px-6 py-3 pb-6 flex items-center justify-between border-t border-gray-100 dark:border-primary-120 text-xs text-gray-500 dark:text-primary-210 bg-gray-50/50 dark:bg-primary-50/30" style="flex-shrink: 0;">
      <div class="flex items-center gap-3">
        <span id="kb-save-status">Gespeichert</span>
        <span id="kb-updated-at"><?php if ($page && !empty($page['updated_at'])) echo htmlspecialchars($page['updated_at']); ?></span>
        <?php if (!$pageId): ?>
        <button type="button" id="kb-create-page-btn" class="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium hover:underline" title="Seite anlegen">Seite anlegen</button>
        <?php endif; ?>
        <?php if ($pageId && !$isCompanyFolder && !$isSystemFolder): ?>
        <button type="button" id="kb-page-history-btn" class="inline-flex items-center gap-1.5 py-1.5 px-2 rounded-md text-gray-600 hover:text-gray-800 hover:bg-gray-100 dark:text-primary-210 dark:hover:text-primary-200 dark:hover:bg-primary-140 transition-colors text-xs font-medium" title="Verlauf anzeigen – wer wann was geändert hat" aria-label="Verlauf">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Verlauf
        </button>
        <?php endif; ?>
      </div>
      <?php if ($pageId && !$isCompanyFolder && !$isSystemFolder): ?>
      <button type="button" id="kb-page-delete-btn" class="ml-auto p-1.5 rounded-md text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-900/20 transition-colors" title="Seite löschen" aria-label="Seite löschen">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      </button>
      <?php endif; ?>
    </div>
  </div>
  <div id="kb-slash-menu" class="hidden fixed z-[100] min-w-[220px] max-h-[320px] overflow-y-auto py-1 rounded-lg border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-lg" role="listbox" style="left:0;top:0"></div>
  <button type="button" id="kb-table-plus-col" class="hidden fixed z-[100] w-8 h-8 flex items-center justify-center rounded-full border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-md hover:bg-gray-50 dark:hover:bg-primary-140 text-gray-600 dark:text-primary-200 transition-opacity" style="left:0;top:0" title="Spalte hinzufügen" aria-label="Spalte hinzufügen">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
  </button>
  <button type="button" id="kb-table-plus-row" class="hidden fixed z-[100] w-8 h-8 flex items-center justify-center rounded-full border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-md hover:bg-gray-50 dark:hover:bg-primary-140 text-gray-600 dark:text-primary-200 transition-opacity" style="left:0;top:0" title="Zeile hinzufügen" aria-label="Zeile hinzufügen">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
  </button>
  <input type="file" id="kb-file-input" class="hidden" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">

  <!-- Modal: Verlauf (wer wann was geändert hat) -->
  <div id="kb-history-modal" class="hidden fixed inset-0 z-[110] overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="kb-history-modal-title">
    <div class="flex min-h-full items-center justify-center p-4">
      <div class="fixed inset-0 bg-black/40 dark:bg-black/60" aria-hidden="true" id="kb-history-modal-backdrop"></div>
      <div class="relative w-full max-w-2xl rounded-xl bg-white dark:bg-primary-100 shadow-xl border border-gray-200 dark:border-primary-120 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-primary-120 flex-shrink-0">
          <h2 id="kb-history-modal-title" class="text-lg font-semibold text-gray-900 dark:text-primary-200">Verlauf – Wer wann was geändert hat</h2>
          <button type="button" id="kb-history-modal-close" class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-primary-210 dark:hover:text-primary-200 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div id="kb-history-modal-body" class="flex-1 overflow-y-auto p-4">
          <p id="kb-history-loading" class="text-sm text-gray-500 dark:text-primary-210">Lade Verlauf …</p>
          <ul id="kb-history-list" class="hidden space-y-2"></ul>
          <div id="kb-history-detail" class="hidden mt-4 p-4 rounded-lg bg-gray-50 dark:bg-primary-50 border border-gray-200 dark:border-primary-120"></div>
          <p id="kb-history-empty" class="hidden text-sm text-gray-500 dark:text-primary-210">Noch keine Änderungen an dieser Seite.</p>
        </div>
      </div>
    </div>
  </div>

  <script>
  window.KB_CONFIG = {
    pageId: <?php echo $pageId ? json_encode($pageId) : 'null'; ?>,
    parentId: <?php echo $parentId ? json_encode($parentId) : 'null'; ?>,
    baseUrl: <?php echo json_encode($baseUrl); ?>,
    apiSave: <?php echo json_encode($apiSave); ?>,
    apiUpload: <?php echo json_encode($apiUpload); ?>,
    apiPageHistory: <?php echo json_encode($baseUrl . 'knowledge/api/page-history.php'); ?>,
    initialContent: <?php echo json_encode($initialContent); ?>,
    isCompanyFolder: <?php echo $isCompanyFolder ? 'true' : 'false'; ?>,
    isSystemFolder: <?php echo $isSystemFolder ? 'true' : 'false'; ?>,
    companyDetailUrl: <?php echo $isCompanyFolder ? json_encode($baseUrl . 'companies/detail.php?id=' . (int)$page['company_id']) : 'null'; ?>,
    companyPageTitle: <?php echo ($isCompanyFolder || $isSystemFolder) ? json_encode($initialTitle) : 'null'; ?>
  };
  </script>
  <script type="module" src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/knowledge-editor.js"></script>
  <?php if ($pageId): ?>
  <script>
  (function() {
    var cfg = window.KB_CONFIG;
    if (!cfg || !cfg.pageId) return;
    var btn = document.getElementById('kb-page-delete-btn');
    if (!btn) return;
    btn.addEventListener('click', function() {
      if (!confirm('Seite wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;
      fetch(cfg.baseUrl + 'knowledge/api/pages.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: cfg.pageId }) })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.success) {
            try {
              if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'kb-page-deleted', id: cfg.pageId }, window.location.origin);
              }
              window.location.href = cfg.baseUrl + 'knowledge/';
            } catch (e) {
              window.location.href = cfg.baseUrl + 'knowledge/';
            }
          }
        });
    });
  })();
  </script>
  <?php endif; ?>
</body>
</html>
<?php
    exit;
}

unset($pageTitle);
include dirname(__DIR__) . '/assets/frontend/head.php';
$pageTitle = $kbPageTitle;
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
<style>
.kb-editor-wrap { position: relative; }
.kb-editor-wrap .ProseMirror { min-height: 200px; padding-left: 2rem; line-height: 1.65; }
.kb-editor-wrap .ProseMirror > * + * { margin-top: 0.5em; }
.kb-editor-wrap .ProseMirror h1 { font-size: 1.75rem; margin-top: 1.25em; margin-bottom: 0.5em; }
.kb-editor-wrap .ProseMirror h2 { font-size: 1.375rem; margin-top: 1em; margin-bottom: 0.375em; }
.kb-editor-wrap .ProseMirror h3 { font-size: 1.125rem; margin-top: 0.875em; margin-bottom: 0.25em; }
.kb-editor-wrap .kb-drag-handle { opacity: 0.5; cursor: grab; color: #64748b; transition: opacity 0.15s, background 0.15s; }
.kb-editor-wrap .kb-drag-handle:hover { opacity: 0.9; background: rgba(0,0,0,0.06); }
.kb-editor-wrap .kb-drag-handle:active { cursor: grabbing; }
.dark .kb-editor-wrap .kb-drag-handle { color: #64748b; }
.kb-editor-wrap .ProseMirror a { color: #2563eb; text-decoration: underline; cursor: pointer; }
.kb-editor-wrap .ProseMirror a:hover { color: #1d4ed8; }
.kb-editor-wrap .ProseMirror a[href*="knowledge/"]::before { content: "\01F4C4 "; }
.kb-editor-wrap .ProseMirror img { border-radius: 0.375rem; transition: box-shadow 0.15s; max-width: 100%; }
.kb-editor-wrap .ProseMirror img:hover { box-shadow: 0 0 0 2px #3b82f6; }
.dark .kb-editor-wrap .ProseMirror img:hover { box-shadow: 0 0 0 2px #60a5fa; }
.kb-img-resize-handle { background: #3b82f6 !important; }
.dark .kb-img-resize-handle { background: #60a5fa !important; }
/* Container/Callouts – deutlich sichtbar (Attribut + Klasse, mit !important) */
#kb-editor-wrap [data-callout],
#kb-editor-wrap .kb-callout,
.kb-editor-wrap [data-callout],
.kb-editor-wrap .kb-callout {
  display: block !important;
  padding: 0.75rem 1rem !important;
  margin: 0.75em 0 !important;
  border-radius: 0.5rem !important;
  border-left: 5px solid !important;
  box-sizing: border-box !important;
  min-height: 2.5rem !important;
}
#kb-editor-wrap [data-callout="default"],
#kb-editor-wrap .kb-callout-default,
.kb-editor-wrap [data-callout="default"],
.kb-editor-wrap .kb-callout-default { background-color: #e2e8f0 !important; border-left-color: #64748b !important; }
#kb-editor-wrap [data-callout="warning"],
#kb-editor-wrap .kb-callout-warning,
.kb-editor-wrap [data-callout="warning"],
.kb-editor-wrap .kb-callout-warning { background-color: #fde68a !important; border-left-color: #d97706 !important; }
#kb-editor-wrap [data-callout="error"],
#kb-editor-wrap .kb-callout-error,
.kb-editor-wrap [data-callout="error"],
.kb-editor-wrap .kb-callout-error { background-color: #fecaca !important; border-left-color: #dc2626 !important; }
#kb-editor-wrap [data-callout="success"],
#kb-editor-wrap .kb-callout-success,
.kb-editor-wrap [data-callout="success"],
.kb-editor-wrap .kb-callout-success { background-color: #bbf7d0 !important; border-left-color: #16a34a !important; }
#kb-editor-wrap [data-callout="page"],
#kb-editor-wrap .kb-callout-page,
.kb-editor-wrap [data-callout="page"],
.kb-editor-wrap .kb-callout-page { background-color: #bfdbfe !important; border-left-color: #2563eb !important; }
.dark #kb-editor-wrap [data-callout="default"],
.dark #kb-editor-wrap .kb-callout-default,
.dark .kb-editor-wrap [data-callout="default"],
.dark .kb-editor-wrap .kb-callout-default { background-color: #334155 !important; border-left-color: #94a3b8 !important; }
.dark #kb-editor-wrap [data-callout="warning"],
.dark #kb-editor-wrap .kb-callout-warning,
.dark .kb-editor-wrap [data-callout="warning"],
.dark .kb-editor-wrap .kb-callout-warning { background-color: #451a03 !important; border-left-color: #fbbf24 !important; }
.dark #kb-editor-wrap [data-callout="error"],
.dark #kb-editor-wrap .kb-callout-error,
.dark .kb-editor-wrap [data-callout="error"],
.dark .kb-editor-wrap .kb-callout-error { background-color: #450a0a !important; border-left-color: #f87171 !important; }
.dark #kb-editor-wrap [data-callout="success"],
.dark #kb-editor-wrap .kb-callout-success,
.dark .kb-editor-wrap [data-callout="success"],
.dark .kb-editor-wrap .kb-callout-success { background-color: #052e16 !important; border-left-color: #4ade80 !important; }
.dark #kb-editor-wrap [data-callout="page"],
.dark #kb-editor-wrap .kb-callout-page,
.dark .kb-editor-wrap [data-callout="page"],
.dark .kb-editor-wrap .kb-callout-page { background-color: #1e3a5f !important; border-left-color: #60a5fa !important; }
#kb-editor-wrap .kb-callout-inner,
.kb-editor-wrap .kb-callout-inner { background: transparent !important; }
.dark .kb-editor-wrap .ProseMirror a { color: #60a5fa; }
.dark .kb-editor-wrap .ProseMirror a:hover { color: #93c5fd; }
/* Kein Scrollen der Gesamtseite – nur Seitenleiste und Inhaltsbereich scrollen */
body:has(#main-content.kb-viewport-fill) { overflow: hidden; height: 100vh; }
#main-content.kb-viewport-fill { height: 100vh; max-height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
#main-content.kb-viewport-fill .kb-main { flex: 1; min-height: 0; overflow: hidden; display: flex; flex-direction: column; }
.kb-page-layout { display: flex; flex-direction: column; height: calc(100vh - 3rem); min-height: 0; overflow: hidden; }
.kb-body-row { flex: 1; min-height: 0; display: flex; flex-direction: row; overflow: hidden; align-items: stretch; position: relative; gap: 0 1.5rem; }
.kb-nav-sidebar { width: 22%; min-width: 260px; max-width: 340px; flex-shrink: 0; min-height: 0; max-height: 100%; border-right: 1px solid #e5e7eb; overflow-y: auto; overflow-x: hidden; background: #fff; align-self: stretch; display: flex; flex-direction: column; }
.dark .kb-nav-sidebar { border-color: var(--primary-120, #1e293b); background: var(--primary-100, #0f172a); }
/* Scrollbar der gesamten Seitenleiste */
.kb-nav-sidebar { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
.dark .kb-nav-sidebar { scrollbar-color: #475569 transparent; }
.kb-nav-sidebar::-webkit-scrollbar { width: 8px; }
.kb-nav-sidebar::-webkit-scrollbar-track { background: transparent; }
.kb-nav-sidebar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.kb-nav-sidebar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.dark .kb-nav-sidebar::-webkit-scrollbar-thumb { background: #475569; }
.dark .kb-nav-sidebar::-webkit-scrollbar-thumb:hover { background: #64748b; }
.kb-nav-sidebar .kb-nav-inner { display: flex; flex-direction: column; padding: 0.5rem; padding-bottom: 4rem; flex-shrink: 0; }
/* Seitenleiste: Baum wächst mit Inhalt, Sidebar scrollt – unten genug Abstand, wirkt nicht abgeschnitten */
#kb-nav-tree { display: flex; flex-direction: column; flex-wrap: nowrap; align-items: stretch; padding-bottom: 2.5rem; }
#kb-nav-tree > * { flex-shrink: 0; }
.kb-nav-item-wrap { display: flex; flex-direction: column; width: 100%; max-width: 100%; flex-shrink: 0; }
.kb-nav-item-row { display: flex; flex-direction: row; align-items: flex-start; width: 100%; min-width: 0; flex-shrink: 0; }
.kb-nav-children { display: flex; flex-direction: column; flex-wrap: nowrap; }
.kb-nav-sidebar .kb-nav-item { color: #374151; }
.kb-nav-company-logo { width: 1.25rem; height: 1.25rem; border-radius: 0.25rem; object-fit: cover; flex-shrink: 0; }
.dark .kb-nav-sidebar .kb-nav-item { color: #e2e8f0; }
.dark .kb-nav-sidebar .kb-nav-item:hover { color: #f8fafc; }
.kb-content-wrap { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
.kb-nav-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.75rem; width: 100%; text-align: left; border: none; background: transparent; color: inherit; font-size: 0.875rem; cursor: pointer; border-radius: 0.25rem; }
.kb-nav-item:hover { background: rgba(0,0,0,0.05); }
.dark .kb-nav-item:hover { background: rgba(255,255,255,0.08); }
.kb-nav-item.current { background: rgba(59, 130, 246, 0.12); }
.kb-nav-item.current:hover { background: rgba(59, 130, 246, 0.18); }
.dark .kb-nav-item.current { background: rgba(59, 130, 246, 0.18); }
.dark .kb-nav-item.current:hover { background: rgba(59, 130, 246, 0.25); }
.kb-nav-item .kb-nav-icon { flex-shrink: 0; width: 1rem; height: 1rem; }
.kb-nav-item .kb-nav-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kb-nav-children { padding-left: 0; margin-left: 1.25rem; border-left: 1px solid #e5e7eb; min-width: 0; width: calc(100% - 1.25rem); box-sizing: border-box; }
.dark .kb-nav-children { border-color: var(--primary-120); }
/* Drag & Drop Seitenleiste */
.kb-nav-item-wrap[draggable="true"] { cursor: grab; }
.kb-nav-item-wrap[draggable="true"]:active { cursor: grabbing; }
.kb-nav-item-wrap.kb-drag-over { background: rgba(59, 130, 246, 0.15); outline: 2px dashed #2563eb; outline-offset: -2px; border-radius: 0.25rem; }
.dark .kb-nav-item-wrap.kb-drag-over { background: rgba(96, 165, 250, 0.15); outline-color: #60a5fa; }
.kb-nav-drag-handle { cursor: grab; padding: 0.25rem; margin: -0.25rem 0.25rem -0.25rem -0.25rem; border-radius: 0.25rem; color: #9ca3af; flex-shrink: 0; align-self: center; }
.kb-nav-drag-handle:hover { color: #6b7280; background: rgba(0,0,0,0.05); }
.dark .kb-nav-drag-handle { color: #6b7280; }
.dark .kb-nav-drag-handle:hover { color: #9ca3af; background: rgba(255,255,255,0.08); }
.kb-nav-drag-handle:active { cursor: grabbing; }
.kb-nav-drag-handle svg { width: 1rem; height: 1rem; display: block; }
.kb-tab-panels-wrap { flex: 1; min-height: 0; position: relative; overflow: hidden; padding: 0.75rem 1rem 1rem 0; }
#kb-main-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: none; background: #f7fafc; border-radius: 0.75rem; overflow: auto; }
.dark #kb-main-frame { background: var(--primary-50); }
.dark #kb-main-frame { background: var(--primary-50); }
#kb-no-tab-placeholder { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; min-height: 0; flex: 1; text-align: center; }
#kb-breadcrumb .kb-breadcrumb-trail { display: contents; }
.kb-main { flex: 1; min-height: 0; }
/* Body-Row und Sidebar bis zum Bildschirmende – Scrollbar endet genau unten */
.kb-main-fill { padding-bottom: 0; }
.kb-main-fill .kb-body-row { margin-bottom: 0; flex: 1; min-height: 0; }
</style>
<div id="main-content" class="kb-viewport-fill relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-hidden">
  <main class="kb-main pt-4 pr-4 pb-0 pl-0 flex flex-col overflow-hidden min-h-0 kb-main-fill">
    <nav class="mb-4 flex-shrink-0 pl-4" aria-label="Breadcrumb">
      <ol id="kb-breadcrumb" class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
            <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd"/>
            </svg>
            Startseite
          </a>
        </li>
        <li id="kb-breadcrumb-wd" class="inline-flex items-center">
          <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
          </svg>
          <a href="<?php echo htmlspecialchars(BASE_URL); ?>knowledge/" class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2 hover:text-gray-700 dark:hover:text-white">Wissensdatenbank</a>
        </li>
        <?php foreach ($breadcrumbPath as $entry): $title = htmlspecialchars($entry['title']); ?>
        <li class="inline-flex items-center">
          <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
          </svg>
          <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2"><?php echo $title; ?></span>
        </li>
        <?php endforeach; ?>
        <?php if ($pageId): ?>
        <li aria-current="page" class="inline-flex items-center">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
            </svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2 truncate max-w-[12rem]" id="breadcrumb-current-title"><?php echo htmlspecialchars($pageTitle); ?></span>
          </div>
        </li>
        <?php endif; ?>
      </ol>
    </nav>

    <div class="kb-body-row">
      <aside class="kb-nav-sidebar" id="kb-nav-sidebar" aria-label="Seitennavigation">
        <div class="kb-nav-inner p-2">
          <button type="button" id="kb-nav-new-page" class="w-full flex items-center justify-center gap-2 py-2.5 px-3 mb-2 text-sm font-medium text-white rounded-lg bg-primary-700 hover:bg-primary-800 focus:ring-2 focus:ring-primary-300 dark:bg-primary-420 dark:hover:bg-primary-440 dark:focus:ring-primary-800" title="Seite im aktuell gewählten Ordner anlegen">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            Seite anlegen
          </button>
          <div id="kb-nav-tree"></div>
        </div>
      </aside>
      <div class="kb-tab-panels-wrap" id="kb-tab-panels">
        <iframe id="kb-main-frame" src="<?php echo $pageId ? htmlspecialchars(BASE_URL . 'knowledge/?embed=1&id=' . urlencode($pageId)) : ''; ?>" title="<?php echo $pageId ? htmlspecialchars($pageTitle) : ''; ?>" style="<?php echo $pageId ? '' : 'display:none'; ?>"></iframe>
        <div id="kb-no-tab-placeholder" class="flex items-center justify-center min-h-0 flex-1 text-gray-500 dark:text-primary-210 text-sm p-8 text-center" style="<?php echo $pageId ? 'display:none' : ''; ?>">Seite in der Seitenleiste öffnen oder <button type="button" class="text-primary-600 dark:text-primary-400 hover:underline font-medium" id="kb-open-new-from-placeholder">Neue Seite anlegen</button>.</div>
      </div>
    </div>
  </main>
</div>
<script>
(function() {
  const baseUrl = <?php echo json_encode($baseUrl); ?>;
  const apiUrl = baseUrl + 'knowledge/api/pages.php';

  let currentPageId = <?php echo $pageId ? json_encode($pageId) : 'null'; ?>;
  let currentTitle = <?php echo json_encode($pageTitle); ?>;
  let currentPath = <?php echo json_encode(!empty($pathFromUrl) ? $pathFromUrl : $breadcrumbPath); ?>;

  function getParentIdFromPath(path) {
    if (!path || !path.length) return null;
    var last = path[path.length - 1];
    return last && typeof last === 'object' && last !== null && 'id' in last ? last.id : null;
  }

  function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  var chevronSvg = '<svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>';
  function breadcrumbLabel(entry) {
    if (entry && typeof entry === 'object' && entry !== null && entry.title != null) return String(entry.title);
    return 'Ordner';
  }
  function loadBreadcrumb(pageId, path, currentTitle) {
    const wdLi = document.getElementById('kb-breadcrumb-wd');
    if (!wdLi) return;
    while (wdLi.nextElementSibling) wdLi.nextElementSibling.remove();
    const ol = wdLi.closest('ol');
    path = path || [];
    path.forEach(function(entry) {
      const li = document.createElement('li');
      li.className = 'inline-flex items-center';
      li.innerHTML = chevronSvg + '<span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">' + escapeHtml(breadcrumbLabel(entry)) + '</span>';
      ol.appendChild(li);
    });
    if (pageId != null || (currentTitle != null && String(currentTitle).trim() !== '')) {
      const last = document.createElement('li');
      last.className = 'inline-flex items-center';
      last.setAttribute('aria-current', 'page');
      last.innerHTML = '<div class="flex items-center">' + chevronSvg + '<span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2 truncate max-w-[12rem]" id="breadcrumb-current-title">' + escapeHtml(currentTitle || 'Seite') + '</span></div>';
      ol.appendChild(last);
    }
  }

  function showPage(id, title, path) {
    path = path || [];
    currentPageId = id;
    currentTitle = title || 'Seite';
    currentPath = path;
    var panels = document.getElementById('kb-tab-panels');
    var frame = document.getElementById('kb-main-frame');
    var placeholder = document.getElementById('kb-no-tab-placeholder');
    if (!panels || !frame) return;
    var parentId = getParentIdFromPath(path);
    if (id != null) {
      frame.src = baseUrl + 'knowledge/?embed=1&id=' + encodeURIComponent(id);
      frame.title = currentTitle;
    } else {
      frame.src = baseUrl + 'knowledge/?embed=1' + (parentId ? '&parent_id=' + encodeURIComponent(parentId) : '');
      frame.title = 'Neue Seite';
    }
    frame.style.display = '';
    if (placeholder) placeholder.style.display = 'none';
    loadBreadcrumb(id, path, title || 'Neue Seite');
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', id ? baseUrl + 'knowledge/?id=' + encodeURIComponent(id) : baseUrl + 'knowledge/');
    }
    refreshKbNav();
  }

  function showPlaceholder() {
    currentPageId = null;
    currentTitle = null;
    currentPath = [];
    var frame = document.getElementById('kb-main-frame');
    var placeholder = document.getElementById('kb-no-tab-placeholder');
    if (frame) { frame.src = ''; frame.style.display = 'none'; }
    if (placeholder) placeholder.style.display = '';
    loadBreadcrumb(null, [], null);
    if (window.history && window.history.replaceState) window.history.replaceState({}, '', baseUrl + 'knowledge/');
    refreshKbNav();
  }

  function getNavContext() {
    var pathIds = (currentPath || []).map(function(entry) {
      return entry && (typeof entry === 'object' && entry !== null && 'id' in entry ? entry.id : entry);
    });
    return { pathIds: pathIds, currentPageId: currentPageId };
  }

  function movePageInSidebar(pageId, newParentId, newParentPath, callback) {
    if (typeof newParentPath === 'function') { callback = newParentPath; newParentPath = []; }
    const body = { id: pageId, parent_id: newParentId };
    fetch(apiUrl, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
      .then(function(result) {
        if (result.ok && result.data.success) {
          if (currentPageId === pageId && newParentPath && Array.isArray(newParentPath)) {
            currentPath = newParentPath;
            loadBreadcrumb(pageId, currentPath, currentTitle);
          }
          refreshKbNav();
          try { window.postMessage({ type: 'kb-pages-updated' }, window.location.origin); } catch (e) {}
          if (typeof callback === 'function') callback();
        } else {
          if (typeof callback === 'function') callback(result.data && result.data.error ? new Error(result.data.error) : new Error('Verschieben fehlgeschlagen'));
        }
      })
      .catch(function(err) {
        if (typeof callback === 'function') callback(err);
      });
  }

  function loadNav(parentId, container, level, context, parentPathTitles) {
    level = level || 0;
    parentPathTitles = parentPathTitles || [];
    context = context || { pathIds: [], currentPageId: null };
    var pathIds = context.pathIds || [];
    var currentPageId = context.currentPageId || null;
    var expandFolderId = pathIds[level] || null;
    const url = parentId ? apiUrl + '?parent_id=' + encodeURIComponent(parentId) : apiUrl;
    fetch(url, { cache: 'no-store' }).then(function(r) { return r.json(); }).then(function(data) {
      if (!data.success || !data.pages) return;
      var pages = data.pages;
      if (parentId === null && container.id === 'kb-nav-tree' && !data.filtered_root) {
        pages = pages.slice().sort(function(a, b) {
          var na = (a.company_name != null && String(a.company_name).trim() !== '') ? String(a.company_name) : (a.title || '');
          var nb = (b.company_name != null && String(b.company_name).trim() !== '') ? String(b.company_name) : (b.title || '');
          var ca = na.toLowerCase();
          var cb = nb.toLowerCase();
          return ca < cb ? -1 : ca > cb ? 1 : 0;
        });
      }
      container.innerHTML = '';
      var isFilteredRoot = !!(parentId === null && data.filtered_root);
      pages.forEach(function(p) {
        const hasChildren = (parseInt(p.children_count, 10) || 0) > 0;
        const isSystemFolder = !!(p.is_system_folder);
        const isFolder = (isSystemFolder && (p.system_type === 'notes' || p.system_type === 'problems') && hasChildren) || (isSystemFolder && p.system_type === 'wiki') || (!isSystemFolder && (hasChildren || (parentId === null && !isFilteredRoot)));
        const sameId = function(a, b) { return a != null && b != null && String(a) === String(b); };
        const isCurrent = sameId(currentPageId, p.id);
        const shouldExpand = isFolder && (sameId(expandFolderId, p.id) || sameId(currentPageId, p.id));
        var pathToParent = parentPathTitles;
        var pathWithThis = parentPathTitles.concat([{ id: p.id, title: p.title || 'Seite' }]);
        const div = document.createElement('div');
        div.className = 'kb-nav-item-wrap kb-nav-tree-item flex items-start w-full min-w-0' + (isSystemFolder ? ' kb-nav-system-folder' : '');
        div.setAttribute('data-id', p.id);
        div.setAttribute('data-parent-id', parentId != null ? parentId : '');
        div.setAttribute('data-folder', isFolder ? '1' : '0');
        div.setAttribute('data-level', String(level));
        div.draggable = false;
        var isCompanyFolder = parentId === null && !isFilteredRoot && (p.company_id != null && p.company_id !== '');
        var row = document.createElement('div');
        row.className = 'kb-nav-item-row';
        var dragHandle = null;
        if (!isCompanyFolder && !isSystemFolder) {
          dragHandle = document.createElement('span');
          dragHandle.className = 'kb-nav-drag-handle';
          dragHandle.draggable = true;
          dragHandle.setAttribute('aria-label', 'Zum Verschieben ziehen');
          dragHandle.title = 'Seite/Ordner verschieben';
          dragHandle.innerHTML = '<svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>';
          dragHandle.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/kb-page-id', p.id);
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', p.id);
          });
          dragHandle.addEventListener('dragend', function() {
            document.querySelectorAll('.kb-nav-item-wrap.kb-drag-over').forEach(function(el) { el.classList.remove('kb-drag-over'); });
          });
        }
        div.addEventListener('dragover', function(e) {
          if (isSystemFolder && p.system_type !== 'wiki') return;
          e.preventDefault();
          e.stopPropagation();
          e.dataTransfer.dropEffect = 'move';
          if (e.dataTransfer.types.indexOf('text/kb-page-id') === -1) return;
          var sidebar = document.getElementById('kb-nav-sidebar');
          if (sidebar) sidebar.querySelectorAll('.kb-nav-item-wrap.kb-drag-over').forEach(function(el) { el.classList.remove('kb-drag-over'); });
          div.classList.add('kb-drag-over');
        });
        div.addEventListener('dragleave', function(e) {
          if (!div.contains(e.relatedTarget)) div.classList.remove('kb-drag-over');
        });
        div.addEventListener('drop', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var sidebar = document.getElementById('kb-nav-sidebar');
          if (sidebar) sidebar.querySelectorAll('.kb-nav-item-wrap.kb-drag-over').forEach(function(el) { el.classList.remove('kb-drag-over'); });
          if (isSystemFolder && p.system_type !== 'wiki') return;
          const id = e.dataTransfer.getData('text/kb-page-id');
          if (!id || id === p.id) return;
          var isDescendant = pathWithThis.some(function(entry) {
            var eid = entry && (typeof entry === 'object' && entry !== null && 'id' in entry) ? entry.id : entry;
            return String(eid) === String(id);
          });
          if (isDescendant) return;
          var newParentId = p.id;
          var newPath = pathWithThis;
          movePageInSidebar(id, newParentId, newPath, function(err) {
            if (err && typeof showToast === 'function') showToast(err.message || 'Verschieben fehlgeschlagen', 'error');
          });
        });
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'kb-nav-item flex-1 min-w-0 text-left' + (isCurrent ? ' current' : '');
        btn.setAttribute('data-id', p.id);
        btn.setAttribute('data-title', p.title || '');
        btn.setAttribute('data-folder', isFolder ? '1' : '0');
        function logoUrl(logo) {
          if (!logo || (typeof logo === 'string' && logo.trim() === '')) return baseUrl + 'assets/images/default-avatar.png';
          if (typeof logo === 'string' && (logo.indexOf('http://') === 0 || logo.indexOf('https://') === 0)) return logo;
          return baseUrl + (typeof logo === 'string' ? logo.replace(/^\//, '') : '');
        }
        var displayTitle = (parentId === null && !isFilteredRoot && p.company_name != null && String(p.company_name).trim() !== '') ? (p.company_name || p.title || '') : (p.title || '');
        var iconHtml = '';
        if (isSystemFolder && p.system_type === 'calls') {
          iconHtml = '<svg class="kb-nav-icon text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.427 14.768 17.2 13.542a1.733 1.733 0 0 0-2.45 0l-.613.613a1.732 1.732 0 0 1-2.45 0l-1.838-1.84a1.735 1.735 0 0 1 0-2.452l.612-.613a1.735 1.735 0 0 0 0-2.452L9.237 5.572a1.6 1.6 0 0 0-2.45 0c-3.223 3.2-1.702 6.896 1.519 10.117 3.22 3.221 6.914 4.745 10.12 1.535a1.601 1.601 0 0 0 0-2.456Z"/></svg>';
        } else if (isSystemFolder && p.system_type === 'notes') {
          iconHtml = '<svg class="kb-nav-icon text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h8m-8 4h12M6 14h8m-8 4h12"/></svg>';
        } else if (isSystemFolder && p.system_type === 'problems') {
          iconHtml = '<svg class="kb-nav-icon text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" stroke-width="2" d="M10 12v1h4v-1m4 7H6a1 1 0 0 1-1-1V9h14v9a1 1 0 0 1-1 1ZM4 5h16a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>';
        } else if (isSystemFolder && p.system_type === 'wiki') {
          iconHtml = '<svg class="kb-nav-icon text-gray-500 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.03v13m0-13c-2.819-.831-4.715-1.076-8.029-1.023A.99.99 0 0 0 3 6v11c0 .563.466 1.014 1.03 1.007 3.122-.043 5.018.212 7.97 1.023m0-13c2.819-.831 4.715-1.076 8.029-1.023A.99.99 0 0 1 21 6v11c0 .563-.466 1.014-1.03 1.007-3.122-.043-5.018.212-7.97 1.023"/></svg>';
        } else if (parentId === null && !isFilteredRoot && (p.company_logo != null || (p.company_name != null && String(p.company_name).trim() !== ''))) {
          iconHtml = '<img class="kb-nav-icon kb-nav-company-logo" src="' + escapeHtml(logoUrl(p.company_logo || '')) + '" alt="" aria-hidden="true">';
        } else if (isFolder) {
          iconHtml = '<svg class="kb-nav-icon text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>';
        } else {
          iconHtml = '<svg class="kb-nav-icon text-gray-400 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
        }
        btn.innerHTML = iconHtml + '<span class="kb-nav-name">' + escapeHtml(displayTitle) + '</span>';
        btn.addEventListener('click', function() {
          showPage(p.id, p.title || 'Seite', pathToParent);
          if (isFolder) {
            var child = div.querySelector('.kb-nav-children');
            if (child) {
              child.style.display = '';
              return;
            }
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'kb-nav-children mt-1';
            div.appendChild(childrenContainer);
            loadNav(p.id, childrenContainer, level + 1, context, pathWithThis);
          }
        });
        row.appendChild(btn);
        if (dragHandle) row.appendChild(dragHandle);
        div.appendChild(row);
        if (shouldExpand) {
          const childrenContainer = document.createElement('div');
          childrenContainer.className = 'kb-nav-children mt-1';
          div.appendChild(childrenContainer);
          loadNav(p.id, childrenContainer, level + 1, context, pathWithThis);
        }
        container.appendChild(div);
      });
    }).catch(function() {});
  }

  function refreshKbNav() {
    var treeEl = document.getElementById('kb-nav-tree');
    if (!treeEl) return;
    treeEl.innerHTML = '';
    loadNav(null, treeEl, 0, getNavContext(), []);
  }
  function pathToPathTitles(path) {
    if (!path || !path.length) return [];
    return path.map(function(entry) {
      if (entry && typeof entry === 'object' && entry !== null && 'id' in entry) return { id: entry.id, title: entry.title != null ? entry.title : 'Ordner' };
      return { id: entry, title: 'Ordner' };
    });
  }

  refreshKbNav();
  function getPathForNewPage() {
    if (currentPageId != null) {
      var path = (currentPath || []).slice();
      path.push({ id: currentPageId, title: currentTitle || 'Seite' });
      return path;
    }
    return currentPath || [];
  }

  function createPageAndOpen() {
    var pathToParent = getPathForNewPage();
    var parentId = getParentIdFromPath(pathToParent);
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: 'Neue Seite', parent_id: parentId }),
      credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(data) {
      if (data.success && data.id) {
        var newPath = pathToParent.slice();
        newPath.push({ id: data.id, title: 'Neue Seite' });
        showPage(data.id, 'Neue Seite', newPath);
        setTimeout(function() { refreshKbNav(); }, 100);
      }
    }).catch(function() {});
  }

  var newPageBtn = document.getElementById('kb-nav-new-page');
  if (newPageBtn) newPageBtn.addEventListener('click', function() { createPageAndOpen(); });
  loadBreadcrumb(currentPageId, currentPath, currentTitle);

  var placeholderBtn = document.getElementById('kb-open-new-from-placeholder');
  if (placeholderBtn) placeholderBtn.addEventListener('click', function() { createPageAndOpen(); });

  window.addEventListener('message', function(e) {
    if (e.origin !== window.location.origin) return;
    if (e.data && e.data.type === 'kb-pages-updated') {
      if (e.data.newPageId) {
        var parentId = e.data.parentId || null;
        var newPath;
        var lastPathId = getParentIdFromPath(currentPath);
        if (parentId && (lastPathId === parentId || String(lastPathId) === String(parentId))) {
          newPath = (currentPath || []).slice();
          newPath.push({ id: e.data.newPageId, title: 'Seite' });
        } else if (parentId) {
          newPath = [{ id: parentId, title: 'Ordner' }].concat([{ id: e.data.newPageId, title: 'Seite' }]);
        } else {
          newPath = [{ id: e.data.newPageId, title: 'Seite' }];
        }
        currentPageId = e.data.newPageId;
        currentTitle = 'Seite';
        currentPath = newPath;
        var frame = document.getElementById('kb-main-frame');
        if (frame) {
          frame.src = baseUrl + 'knowledge/?embed=1&id=' + encodeURIComponent(e.data.newPageId);
          frame.style.display = '';
          frame.title = 'Seite';
        }
        var placeholder = document.getElementById('kb-no-tab-placeholder');
        if (placeholder) placeholder.style.display = 'none';
        loadBreadcrumb(currentPageId, currentPath, currentTitle);
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, '', baseUrl + 'knowledge/?id=' + encodeURIComponent(e.data.newPageId));
        }
        refreshKbNav();
        setTimeout(function() { refreshKbNav(); }, 200);
      } else {
        refreshKbNav();
      }
    }
    if (e.data && e.data.type === 'kb-page-deleted' && e.data.id) {
      if (currentPageId === e.data.id) showPlaceholder();
      else refreshKbNav();
    }
    if (e.data && e.data.type === 'kb-open-page' && e.data.id) {
      fetch(apiUrl + '?id=' + encodeURIComponent(e.data.id), { cache: 'no-store' }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success && data.page) {
          var path = (data.path || []).map(function(entry) { return { id: entry.id, title: entry.title || 'Ordner' }; });
          showPage(e.data.id, data.page.title || e.data.title || 'Seite', path);
        } else {
          showPage(e.data.id, e.data.title || null, []);
        }
      }).catch(function() {
        showPage(e.data.id, e.data.title || null, []);
      });
    }
    if (e.data && e.data.type === 'kb-page-title-changed' && e.data.id && e.data.title != null) {
      if (currentPageId === e.data.id) {
        currentTitle = e.data.title;
        var bcTitle = document.getElementById('breadcrumb-current-title');
        if (bcTitle) bcTitle.textContent = e.data.title;
        var frame = document.getElementById('kb-main-frame');
        if (frame) frame.title = e.data.title;
      }
      var idStr = String(e.data.id);
      var sidebar = document.getElementById('kb-nav-sidebar');
      if (sidebar) {
        sidebar.querySelectorAll('.kb-nav-item-wrap[data-id]').forEach(function(wrap) {
          if (wrap.getAttribute('data-id') === idStr) {
            var nameEl = wrap.querySelector('.kb-nav-name');
            if (nameEl) nameEl.textContent = e.data.title;
          }
        });
      }
    }
  });

  window.kbOpenPage = showPage;
})();
</script>
