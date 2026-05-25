<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = null;

try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userRole = $user['rolle'];
    }
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
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">Startseite</a>
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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Gelöschte Verbrauchsmaterialien</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between gap-4">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Soft-gelöschte Verbrauchsmaterialien</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Artikel wiederherstellen, damit sie im Lager wieder sichtbar sind.</p>
              </div>
              <button id="btnReload" class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">Neu laden</button>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300">
                  <tr>
                    <th class="px-4 py-3">Bezeichnung</th>
                    <th class="px-4 py-3">Artikelnummer</th>
                    <th class="px-4 py-3">EAN</th>
                    <th class="px-4 py-3">Gelöscht am</th>
                    <th class="px-4 py-3">Aktionen</th>
                  </tr>
                </thead>
                <tbody id="deletedConsumablesBody">
                  <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Lade gelöschte Artikel...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
const consumablesApiUrl = '<?php echo BASE_URL; ?>inventory/api/consumables.php';
const deletedConsumablesBody = document.getElementById('deletedConsumablesBody');
const btnReload = document.getElementById('btnReload');

function showToastMessage(message, type) {
  if (typeof window.showToast === 'function') {
    window.showToast(message, type);
  } else {
    alert(message);
  }
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value || '';
  return div.innerHTML;
}

function formatDate(value) {
  if (!value) {
    return '-';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '-';
  }
  return date.toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
}

async function loadDeletedConsumables() {
  deletedConsumablesBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Lade gelöschte Artikel...</td></tr>';
  try {
    const response = await fetch(consumablesApiUrl + '?action=list_deleted');
    const data = await response.json();
    if (!data.success) {
      throw new Error(data.error || 'Unbekannter Fehler');
    }

    const consumables = Array.isArray(data.consumables) ? data.consumables : [];
    if (consumables.length === 0) {
      deletedConsumablesBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Keine soft-gelöschten Verbrauchsmaterialien gefunden.</td></tr>';
      return;
    }

    deletedConsumablesBody.innerHTML = consumables.map(function(c) {
      return '<tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">' +
        '<td class="px-4 py-3 text-gray-900 dark:text-white">' + escapeHtml(c.bezeichnung || '') + '</td>' +
        '<td class="px-4 py-3">' + escapeHtml(c.artikelnummer || '–') + '</td>' +
        '<td class="px-4 py-3 font-mono text-xs">' + escapeHtml(c.ean || '–') + '</td>' +
        '<td class="px-4 py-3">' + escapeHtml(formatDate(c.deleted_at)) + '</td>' +
        '<td class="px-4 py-3">' +
        '<div class="flex items-center gap-2">' +
        '<button type="button" data-action="restore" data-id="' + escapeHtml(String(c.id)) + '" class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700">Wiederherstellen</button>' +
        '<button type="button" data-action="purge" data-id="' + escapeHtml(String(c.id)) + '" class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700">Endgültig löschen</button>' +
        '</div></td></tr>';
    }).join('');
  } catch (error) {
    deletedConsumablesBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-red-600 dark:text-red-400">Fehler beim Laden: ' + escapeHtml(error.message || 'Unbekannter Fehler') + '</td></tr>';
  }
}

deletedConsumablesBody.addEventListener('click', async function(event) {
  const button = event.target.closest('button[data-action]');
  if (!button) {
    return;
  }

  const action = button.getAttribute('data-action');
  const id = parseInt(button.getAttribute('data-id') || '0', 10);
  if (!id) {
    return;
  }

  if (action === 'restore') {
    if (!confirm('Verbrauchsmaterial wirklich wiederherstellen? Es erscheint wieder im Lager.')) {
      return;
    }
    try {
      button.disabled = true;
      const response = await fetch(consumablesApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'restore', id: id })
      });
      const data = await response.json();
      if (!data.success) {
        throw new Error(data.error || 'Wiederherstellung fehlgeschlagen');
      }
      showToastMessage('Verbrauchsmaterial wurde wiederhergestellt.', 'success');
      await loadDeletedConsumables();
    } catch (error) {
      showToastMessage(error.message || 'Wiederherstellung fehlgeschlagen.', 'error');
      button.disabled = false;
    }
    return;
  }

  if (action === 'purge') {
    if (!confirm('Verbrauchsmaterial endgültig löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden. Der Eintrag wird dauerhaft aus der Datenbank entfernt.')) {
      return;
    }
    try {
      button.disabled = true;
      const response = await fetch(consumablesApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'purge', id: id })
      });
      const data = await response.json();
      if (!data.success) {
        throw new Error(data.error || 'Löschen fehlgeschlagen');
      }
      showToastMessage('Verbrauchsmaterial wurde endgültig gelöscht.', 'success');
      await loadDeletedConsumables();
    } catch (error) {
      showToastMessage(error.message || 'Löschen fehlgeschlagen.', 'error');
      button.disabled = false;
    }
  }
});

btnReload.addEventListener('click', loadDeletedConsumables);
document.addEventListener('DOMContentLoaded', loadDeletedConsumables);
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
