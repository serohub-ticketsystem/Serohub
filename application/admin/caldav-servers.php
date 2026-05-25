<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
$caldavApiUrl = rtrim(BASE_URL, '/') . '/admin/api/caldav-servers.php';
$userId = $_SESSION['user_id'];
$userRole = null;
try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) $userRole = $user['rolle'];
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'admin/');
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
        <div class="col-span-full mx-4 mt-4">
          <div class="mb-4">
            <nav class="mb-4 flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
                    <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                    </svg>
                    Startseite
                  </a>
                </li>
                <li>
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">CalDAV-Server</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">CalDAV-Server</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">CalDAV/Nextcloud-Server hinzufügen, die Benutzer für den Kalender-Export nutzen können</p>
              </div>
              <button type="button" id="add-caldav-btn" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600">
                + Server hinzufügen
              </button>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
              <p class="text-sm text-gray-600 dark:text-gray-400">Benutzer können ihren ICS-Export-Link in diesen CalDAV-Servern (z.B. Nextcloud, ownCloud) als externen Kalender abonnieren. Die hier verwalteten Server dienen als Referenz und werden im Kalender-Bereich angezeigt.</p>
            </div>
            <div id="caldav-server-list" class="divide-y divide-gray-200 dark:divide-gray-700">
              <div class="p-8 text-center text-gray-500 dark:text-gray-400" id="caldav-empty-state">Lade...</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal: CalDAV-Server hinzufügen/bearbeiten -->
<div id="caldav-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
  <div class="flex min-h-full items-center justify-center p-4">
    <div id="caldav-modal-backdrop" class="fixed inset-0 bg-black/50 dark:bg-black/70"></div>
    <div class="relative rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 shadow-xl w-full max-w-lg">
      <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h3 id="caldav-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">CalDAV-Server hinzufügen</h3>
        <button type="button" id="caldav-modal-close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 text-2xl leading-none">&times;</button>
      </div>
      <form id="caldav-form" class="p-4 space-y-4">
        <input type="hidden" id="caldav-id" value="">
        <div>
          <label for="caldav-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
          <input type="text" id="caldav-name" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" placeholder="z.B. Firmen-Nextcloud" required>
        </div>
        <div>
          <label for="caldav-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CalDAV-URL</label>
          <input type="url" id="caldav-url" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" placeholder="https://nextcloud.example.com/remote.php/dav/" required>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Basis-URL des CalDAV-Servers (z.B. Nextcloud: .../remote.php/dav/)</p>
        </div>
        <div>
          <label for="caldav-beschreibung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hinweise für Benutzer</label>
          <textarea id="caldav-beschreibung" rows="3" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" placeholder="z.B. In Nextcloud: Kalender öffnen > Einstellungen > Externen Kalender hinzufügen > ICS-Link einfügen"></textarea>
        </div>
        <div class="flex items-center justify-between">
          <label for="caldav-active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Aktiv</label>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" id="caldav-active" checked class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
          </label>
        </div>
      </form>
      <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-between">
        <button type="button" id="caldav-delete-btn" class="px-4 py-2 text-sm font-medium rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 hidden">Löschen</button>
        <div class="flex gap-2 ml-auto">
          <button type="button" id="caldav-modal-cancel" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Abbrechen</button>
          <button type="button" id="caldav-modal-save" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700">Speichern</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var apiBase = '<?php echo htmlspecialchars($caldavApiUrl, ENT_QUOTES, 'UTF-8'); ?>';

  function loadServers() {
    fetch(apiBase)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var list = document.getElementById('caldav-server-list');
        var empty = document.getElementById('caldav-empty-state');
        if (!data.success || !data.servers || data.servers.length === 0) {
          empty.textContent = 'Keine CalDAV-Server vorhanden. Klicken Sie auf „Server hinzufügen“.';
          empty.classList.remove('hidden');
          list.querySelectorAll('.caldav-row').forEach(function(el) { el.remove(); });
          return;
        }
        empty.classList.add('hidden');
        list.querySelectorAll('.caldav-row').forEach(function(el) { el.remove(); });
        data.servers.forEach(function(s) {
          var row = document.createElement('div');
          row.className = 'caldav-row flex items-center justify-between gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50';
          row.innerHTML = '<div class="min-w-0 flex-1">' +
            '<div class="font-medium text-gray-900 dark:text-white">' + escapeHtml(s.name) + '</div>' +
            '<div class="text-sm text-gray-500 dark:text-gray-400 truncate">' + escapeHtml(s.url) + '</div>' +
            (s.beschreibung ? '<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">' + escapeHtml(s.beschreibung) + '</div>' : '') +
            '</div>' +
            '<div class="flex items-center gap-2 shrink-0">' +
            '<span class="px-2 py-1 text-xs rounded ' + (s.is_active == 1 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400') + '">' + (s.is_active == 1 ? 'Aktiv' : 'Inaktiv') + '</span>' +
            '<button type="button" class="caldav-edit px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" data-id="' + s.id + '">Bearbeiten</button>' +
            '</div>';
          list.insertBefore(row, empty);
          row.querySelector('.caldav-edit').addEventListener('click', function() {
            openModal(s.id);
          });
        });
      })
      .catch(function() {
        document.getElementById('caldav-empty-state').textContent = 'Fehler beim Laden.';
      });
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function openModal(id) {
    var isEdit = !!id;
    document.getElementById('caldav-modal-title').textContent = isEdit ? 'CalDAV-Server bearbeiten' : 'CalDAV-Server hinzufügen';
    document.getElementById('caldav-id').value = id || '';
    document.getElementById('caldav-name').value = '';
    document.getElementById('caldav-url').value = '';
    document.getElementById('caldav-beschreibung').value = '';
    document.getElementById('caldav-active').checked = true;
    document.getElementById('caldav-delete-btn').classList.toggle('hidden', !isEdit);

    if (isEdit) {
      fetch(apiBase)
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.success && data.servers) {
            var s = data.servers.find(function(x) { return x.id == id; });
            if (s) {
              document.getElementById('caldav-name').value = s.name || '';
              document.getElementById('caldav-url').value = s.url || '';
              document.getElementById('caldav-beschreibung').value = s.beschreibung || '';
              document.getElementById('caldav-active').checked = s.is_active == 1;
            }
          }
        });
    }
    document.getElementById('caldav-modal').classList.remove('hidden');
  }

  function closeModal() {
    document.getElementById('caldav-modal').classList.add('hidden');
  }

  document.getElementById('add-caldav-btn').addEventListener('click', function() { openModal(null); });
  document.getElementById('caldav-modal-close').addEventListener('click', closeModal);
  document.getElementById('caldav-modal-backdrop').addEventListener('click', closeModal);
  document.getElementById('caldav-modal-cancel').addEventListener('click', closeModal);

  document.getElementById('caldav-modal-save').addEventListener('click', function() {
    var id = document.getElementById('caldav-id').value;
    var payload = {
      name: document.getElementById('caldav-name').value.trim(),
      url: document.getElementById('caldav-url').value.trim(),
      beschreibung: document.getElementById('caldav-beschreibung').value.trim(),
      is_active: document.getElementById('caldav-active').checked
    };
    if (!payload.name || !payload.url) {
      alert('Name und URL sind erforderlich.');
      return;
    }
    var method = id ? 'PATCH' : 'POST';
    if (id) payload.id = parseInt(id, 10);

    fetch(apiBase, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          closeModal();
          loadServers();
          if (typeof showToast === 'function') showToast('CalDAV-Server gespeichert', 'success');
          else alert('Gespeichert.');
        } else {
          alert(data.error || 'Fehler beim Speichern');
        }
      })
      .catch(function() { alert('Netzwerkfehler'); });
  });

  document.getElementById('caldav-delete-btn').addEventListener('click', function() {
    var id = document.getElementById('caldav-id').value;
    if (!id || !confirm('CalDAV-Server wirklich löschen?')) return;
    fetch(apiBase, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          closeModal();
          loadServers();
          if (typeof showToast === 'function') showToast('CalDAV-Server gelöscht', 'success');
        } else {
          alert(data.error || 'Fehler beim Löschen');
        }
      });
  });

  loadServers();
})();
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
