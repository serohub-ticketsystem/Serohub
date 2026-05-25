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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Gelöschte Seiten</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between gap-4">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Soft-gelöschte Wissensdatenbank-Seiten</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Seiten aus der Wissensdatenbank wiederherstellen oder endgültig löschen.</p>
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
                    <th class="px-4 py-3">Titel</th>
                    <th class="px-4 py-3">Firma</th>
                    <th class="px-4 py-3">Erstellt von</th>
                    <th class="px-4 py-3">Gelöscht am</th>
                    <th class="px-4 py-3">Aktionen</th>
                  </tr>
                </thead>
                <tbody id="deletedPagesBody">
                  <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Lade gelöschte Seiten...</td>
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
const deletedPagesApiUrl = '<?php echo BASE_URL; ?>admin/api/deleted-pages.php';
const deletedPagesBody = document.getElementById('deletedPagesBody');
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
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
}

async function loadDeletedPages() {
  deletedPagesBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Lade gelöschte Seiten...</td></tr>';
  try {
    const response = await fetch(deletedPagesApiUrl);
    const data = await response.json();
    if (!data.success) {
      throw new Error(data.error || 'Unbekannter Fehler');
    }

    const pages = Array.isArray(data.pages) ? data.pages : [];
    if (pages.length === 0) {
      deletedPagesBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Keine soft-gelöschten Seiten gefunden.</td></tr>';
      return;
    }

    deletedPagesBody.innerHTML = pages.map((page) => `
      <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
        <td class="px-4 py-3 text-gray-900 dark:text-white">${escapeHtml(page.title || '')}</td>
        <td class="px-4 py-3">${escapeHtml(page.company_name || '-')}</td>
        <td class="px-4 py-3">${escapeHtml((page.author_name || '').trim() || '-')}</td>
        <td class="px-4 py-3">${escapeHtml(formatDate(page.deleted_at))}</td>
        <td class="px-4 py-3">
          <div class="flex items-center gap-2">
            <button data-action="restore" data-page-id="${escapeHtml(page.id)}" class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700">Wiederherstellen</button>
            <button data-action="purge" data-page-id="${escapeHtml(page.id)}" class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700">Endgültig löschen</button>
          </div>
        </td>
      </tr>
    `).join('');
  } catch (error) {
    deletedPagesBody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-red-600 dark:text-red-400">Fehler beim Laden: ${escapeHtml(error.message || 'Unbekannter Fehler')}</td></tr>`;
  }
}

async function pageAction(pageId, method) {
  const response = await fetch(deletedPagesApiUrl, {
    method: method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ page_id: pageId })
  });
  const data = await response.json();
  if (!data.success) {
    throw new Error(data.error || 'Aktion fehlgeschlagen');
  }
  return data;
}

deletedPagesBody.addEventListener('click', async (event) => {
  const button = event.target.closest('button[data-action]');
  if (!button) return;

  const action = button.getAttribute('data-action');
  const pageId = button.getAttribute('data-page-id') || '';
  if (!pageId) return;

  if (action === 'restore') {
    if (!confirm('Seite wirklich wiederherstellen?')) return;
    try {
      button.disabled = true;
      await pageAction(pageId, 'POST');
      showToastMessage('Seite wurde wiederhergestellt.', 'success');
      await loadDeletedPages();
    } catch (error) {
      showToastMessage(error.message || 'Wiederherstellung fehlgeschlagen.', 'error');
      button.disabled = false;
    }
  }

  if (action === 'purge') {
    if (!confirm('Seite endgültig löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;
    try {
      button.disabled = true;
      await pageAction(pageId, 'DELETE');
      showToastMessage('Seite wurde endgültig gelöscht.', 'success');
      await loadDeletedPages();
    } catch (error) {
      showToastMessage(error.message || 'Endgültiges Löschen fehlgeschlagen.', 'error');
      button.disabled = false;
    }
  }
});

btnReload.addEventListener('click', loadDeletedPages);
document.addEventListener('DOMContentLoaded', loadDeletedPages);
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
