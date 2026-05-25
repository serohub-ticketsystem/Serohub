<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Benutzerdaten abrufen
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

// Nur Admin kann Logs sehen
if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'dashboard/');
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
        <!-- Header -->
        <div class="col-span-full mx-4 mt-4">
          <div class="mb-4">
            <nav class="mb-4 flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
                    <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Logs</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">System-Logs</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Übersicht aller System-Änderungen und Aktivitäten</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter -->
        <div class="col-span-full mx-4">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <!-- Kategorie Filter -->
              <div>
                <label for="filter-kategorie" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kategorie</label>
                <select id="filter-kategorie" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-primary-500 focus:border-primary-500 px-3 py-2">
                  <option value="">Alle Kategorien</option>
                  <option value="device">Gerät</option>
                  <option value="customer">Kunde</option>
                  <option value="todo">Aufgabe</option>
                  <option value="ticket">Ticket</option>
                  <option value="job">Job</option>
                  <option value="software">Software</option>
                  <option value="package">Paket</option>
                  <option value="company">Firma</option>
                  <option value="user">Benutzer</option>
                  <option value="order">Bestellung</option>
                  <option value="sonstiges">Sonstiges</option>
                </select>
              </div>

              <!-- Aktion Filter -->
              <div>
                <label for="filter-action" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Aktion</label>
                <select id="filter-action" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-primary-500 focus:border-primary-500 px-3 py-2">
                  <option value="">Alle Aktionen</option>
                  <option value="created">Erstellt</option>
                  <option value="updated">Aktualisiert</option>
                  <option value="deleted">Gelöscht</option>
                </select>
              </div>

              <!-- Benutzer Filter -->
              <div>
                <label for="filter-user" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Benutzer</label>
                <select id="filter-user" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-primary-500 focus:border-primary-500 px-3 py-2">
                  <option value="">Alle Benutzer</option>
                </select>
              </div>

              <!-- Filter zurücksetzen Button -->
              <div class="flex items-end">
                <button id="reset-filters" class="w-full bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-medium py-2 px-4 rounded-lg transition-colors">
                  Filter zurücksetzen
                </button>
              </div>
            </div>
            
            <!-- Datum Filter -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <div>
                <label for="filter-date-from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Von Datum</label>
                <input type="date" id="filter-date-from" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-primary-500 focus:border-primary-500 px-3 py-2">
              </div>
              
              <div>
                <label for="filter-date-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bis Datum</label>
                <input type="date" id="filter-date-to" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-primary-500 focus:border-primary-500 px-3 py-2">
              </div>
            </div>
          </div>
        </div>

        <!-- Logs Tabelle -->
        <div class="col-span-full mx-4">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                  <tr>
                    <th scope="col" class="px-4 py-3">Datum & Zeit</th>
                    <th scope="col" class="px-4 py-3">Kategorie</th>
                    <th scope="col" class="px-4 py-3">Aktion</th>
                    <th scope="col" class="px-4 py-3">Benutzer</th>
                    <th scope="col" class="px-4 py-3">Entity ID</th>
                    <th scope="col" class="px-4 py-3">Feld</th>
                    <th scope="col" class="px-4 py-3">Alter Wert</th>
                    <th scope="col" class="px-4 py-3">Neuer Wert</th>
                    <th scope="col" class="px-4 py-3">Beschreibung</th>
                  </tr>
                </thead>
                <tbody id="logs-table-body">
                  <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                      <div class="flex items-center justify-center">
                        <svg class="animate-spin h-8 w-8 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="ml-2">Lade Logs...</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <!-- Pagination -->
            <div id="pagination" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
const logsApiUrl = '<?php echo BASE_URL; ?>logs/api/logs.php';
let currentPage = 1;
const itemsPerPage = 50;
let currentFilters = {
    kategorie: '',
    action: '',
    user_id: '',
    date_from: '',
    date_to: ''
};

// Benutzer für Filter laden
function loadUsers() {
    fetch('<?php echo BASE_URL; ?>admin/api/users.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users) {
                const userSelect = document.getElementById('filter-user');
                // Leere Optionen entfernen (außer der ersten "Alle Benutzer" Option)
                while (userSelect.children.length > 1) {
                    userSelect.removeChild(userSelect.lastChild);
                }
                data.users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    const name = (user.vorname || '') + ' ' + (user.nachname || '');
                    const displayName = name.trim() ? `${name.trim()} (${user.email})` : user.email;
                    option.textContent = displayName;
                    userSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Benutzer:', error);
        });
}

// Logs laden
function loadLogs(page = 1) {
    currentPage = page;
    const offset = (page - 1) * itemsPerPage;
    
    let url = logsApiUrl + '?limit=' + itemsPerPage + '&offset=' + offset;
    if (currentFilters.kategorie) {
        url += '&kategorie=' + encodeURIComponent(currentFilters.kategorie);
    }
    if (currentFilters.action) {
        url += '&action=' + encodeURIComponent(currentFilters.action);
    }
    if (currentFilters.user_id && currentFilters.user_id !== '') {
        url += '&user_id=' + encodeURIComponent(currentFilters.user_id);
    }
    if (currentFilters.date_from) {
        url += '&date_from=' + encodeURIComponent(currentFilters.date_from);
    }
    if (currentFilters.date_to) {
        url += '&date_to=' + encodeURIComponent(currentFilters.date_to);
    }
    
    const tbody = document.getElementById('logs-table-body');
    tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400"><div class="flex items-center justify-center"><svg class="animate-spin h-8 w-8 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span class="ml-2">Lade Logs...</span></div></td></tr>';
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLogs(data.logs);
                displayPagination(data.total, data.limit, data.offset);
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-red-500">Fehler beim Laden der Logs: ' + (data.error || 'Unbekannter Fehler') + '</td></tr>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-red-500">Fehler beim Laden der Logs</td></tr>';
        });
}

// Logs anzeigen
function displayLogs(logs) {
    const tbody = document.getElementById('logs-table-body');
    
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Keine Logs gefunden</td></tr>';
        return;
    }
    
    tbody.innerHTML = logs.map(log => {
        const date = new Date(log.erstellt_datum);
        const dateStr = date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const timeStr = date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        
        const actionColors = {
            'created': 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'updated': 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
            'deleted': 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400'
        };
        
        const actionText = {
            'created': 'Erstellt',
            'updated': 'Aktualisiert',
            'deleted': 'Gelöscht'
        };
        
        const kategorieText = {
            'device': 'Gerät',
            'customer': 'Kunde',
            'todo': 'Aufgabe',
            'ticket': 'Ticket',
            'job': 'Job',
            'software': 'Software',
            'package': 'Paket',
            'company': 'Firma',
            'user': 'Benutzer',
            'order': 'Bestellung',
            'sonstiges': 'Sonstiges'
        };
        
        const userName = (log.user_vorname || log.user_nachname)
            ? `${log.user_vorname || ''} ${log.user_nachname || ''}`.trim()
            : (log.user_email || 'Unbekannt');
        
        const oldValue = log.old_value !== null && log.old_value !== '' ? String(log.old_value) : null;
        const newValue = log.new_value !== null && log.new_value !== '' ? String(log.new_value) : null;
        
        return `
            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-4 py-3">
                    <div class="text-sm">
                        <div class="font-medium text-gray-900 dark:text-white">${dateStr}</div>
                        <div class="text-gray-500 dark:text-gray-400">${timeStr}</div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                        ${escapeHtml(kategorieText[log.kategorie] || log.kategorie)}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${actionColors[log.action] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}">
                        ${escapeHtml(actionText[log.action] || log.action)}
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-900 dark:text-white">${escapeHtml(userName)}</td>
                <td class="px-4 py-3 text-gray-900 dark:text-white">${log.entity_id}</td>
                <td class="px-4 py-3 text-gray-900 dark:text-white">
                    ${log.field_name ? `<span class="font-medium">${escapeHtml(log.field_name)}</span>` : '-'}
                </td>
                <td class="px-4 py-3">
                    ${oldValue ? `
                        <div class="max-w-xs">
                            <div class="text-sm text-gray-600 dark:text-gray-400 line-through truncate" title="${escapeHtml(oldValue)}">${escapeHtml(oldValue.length > 50 ? oldValue.substring(0, 50) + '...' : oldValue)}</div>
                        </div>
                    ` : '-'}
                </td>
                <td class="px-4 py-3">
                    ${newValue ? `
                        <div class="max-w-xs">
                            <div class="text-sm text-gray-900 dark:text-white font-medium truncate" title="${escapeHtml(newValue)}">${escapeHtml(newValue.length > 50 ? newValue.substring(0, 50) + '...' : newValue)}</div>
                        </div>
                    ` : '-'}
                </td>
                <td class="px-4 py-3 text-gray-900 dark:text-white">
                    <div class="max-w-xs">
                        ${log.beschreibung ? `<div class="truncate" title="${escapeHtml(log.beschreibung)}">${escapeHtml(log.beschreibung.length > 50 ? log.beschreibung.substring(0, 50) + '...' : log.beschreibung)}</div>` : '-'}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Pagination anzeigen
function displayPagination(total, limit, offset) {
    const pagination = document.getElementById('pagination');
    const totalPages = Math.ceil(total / limit);
    
    if (totalPages <= 1) {
        pagination.innerHTML = `<div class="text-sm text-gray-700 dark:text-gray-300">Gesamt: ${total} Einträge</div>`;
        return;
    }
    
    let html = `<div class="text-sm text-gray-700 dark:text-gray-300">Zeige ${offset + 1} bis ${Math.min(offset + limit, total)} von ${total} Einträgen</div>`;
    html += '<div class="flex gap-2">';
    
    // Zurück Button
    if (currentPage > 1) {
        html += `<button onclick="loadLogs(${currentPage - 1})" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">Zurück</button>`;
    }
    
    // Seitenzahlen
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<button onclick="loadLogs(1)" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">1</button>`;
        if (startPage > 2) {
            html += '<span class="px-3 py-1 text-sm text-gray-500">...</span>';
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            html += `<button class="px-3 py-1 text-sm font-medium text-white bg-primary-600 rounded-lg">${i}</button>`;
        } else {
            html += `<button onclick="loadLogs(${i})" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">${i}</button>`;
        }
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += '<span class="px-3 py-1 text-sm text-gray-500">...</span>';
        }
        html += `<button onclick="loadLogs(${totalPages})" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">${totalPages}</button>`;
    }
    
    // Weiter Button
    if (currentPage < totalPages) {
        html += `<button onclick="loadLogs(${currentPage + 1})" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">Weiter</button>`;
    }
    
    html += '</div>';
    pagination.innerHTML = html;
}

// Log-Details anzeigen
function showLogDetails(logId) {
    // Hier könnte ein Modal mit den Details angezeigt werden
    // Für jetzt einfach ein Alert
    alert('Log-Details für ID: ' + logId);
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    loadLogs();
    
    // Filter-Funktion
    function applyFilters() {
        currentFilters.kategorie = document.getElementById('filter-kategorie').value;
        currentFilters.action = document.getElementById('filter-action').value;
        const userIdValue = document.getElementById('filter-user').value;
        currentFilters.user_id = userIdValue && userIdValue !== '' ? userIdValue : '';
        currentFilters.date_from = document.getElementById('filter-date-from').value;
        currentFilters.date_to = document.getElementById('filter-date-to').value;
        loadLogs(1);
    }
    
    // Alle Filter zurücksetzen
    document.getElementById('reset-filters').addEventListener('click', function() {
        document.getElementById('filter-kategorie').value = '';
        document.getElementById('filter-action').value = '';
        document.getElementById('filter-user').value = '';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';
        currentFilters = {
            kategorie: '',
            action: '',
            user_id: '',
            date_from: '',
            date_to: ''
        };
        loadLogs(1);
    });
    
    // Filter automatisch anwenden bei Änderung
    document.getElementById('filter-kategorie').addEventListener('change', applyFilters);
    document.getElementById('filter-action').addEventListener('change', applyFilters);
    document.getElementById('filter-user').addEventListener('change', applyFilters);
    document.getElementById('filter-date-from').addEventListener('change', applyFilters);
    document.getElementById('filter-date-to').addEventListener('change', applyFilters);
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
