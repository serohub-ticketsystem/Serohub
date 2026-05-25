<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$deviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$deviceId) {
    header('Location: ' . BASE_URL . 'devices/');
    exit;
}

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

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
    <main>
        <div class="px-4">
            <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
                <div class="col-span-full mx-4 mt-4 items-center justify-between sm:flex">
                    <div class="mb-4 sm:mb-0 flex-1">
                        <nav class="mb-4 flex" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                                <li class="inline-flex items-center">
                                    <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                                        <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                                        </svg>
                                        Startseite
                                    </a>
                                </li>
                                <li class="inline-flex items-center">
                                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                    </svg>
                                    <a href="<?php echo BASE_URL; ?>devices/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 md:ms-2 dark:text-gray-400 dark:hover:text-white">Geräte</a>
                                </li>
                                <li aria-current="page">
                                    <div class="flex items-center">
                                        <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Gerät-Details</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 id="deviceTitle" class="text-2xl font-bold text-gray-900 dark:text-white">Gerät-Details</h1>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Zeigt alle Informationen zum Gerät an</p>
                            </div>
                            <div id="editButtonContainer"></div>
                        </div>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <div id="deviceContent" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="p-6 flex items-center justify-center">
                                <div role="status">
                                    <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-500 animate-spin fill-primary-600 dark:fill-primary-400" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                    </svg>
                                    <span class="sr-only">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal für Anhänge hinzufügen -->
<div id="attachmentModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4" aria-labelledby="attachment-modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity cursor-pointer" aria-hidden="true" id="attachmentModalOverlay"></div>
    <!-- Zentrierungs-Container -->
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg max-h-[calc(100vh-2rem)] flex flex-col relative z-10">
            <!-- Modal panel -->
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden flex flex-col max-h-[calc(100vh-2rem)]">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 flex-shrink-0">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-primary-200" id="attachment-modal-title">
                            Anhang hinzufügen
                        </h3>
                        <button type="button" id="closeAttachmentModalBtn" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Tabs -->
                    <div class="flex border-b border-gray-200 dark:border-primary-230 mb-4">
                        <button type="button" id="tabFile" onclick="switchAttachmentTab('file')" 
                                class="flex-1 px-4 py-2 text-sm font-medium text-center border-b-2 border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400">
                            Datei hochladen
                        </button>
                        <button type="button" id="tabLink" onclick="switchAttachmentTab('link')" 
                                class="flex-1 px-4 py-2 text-sm font-medium text-center border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-primary-240 dark:hover:text-primary-200">
                            Link hinzufügen
                        </button>
                    </div>
                    
                    <!-- Datei-Upload Tab -->
                    <div id="tabContentFile" class="attachment-tab-content">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Datei auswählen
                            </label>
                            <div class="flex items-center justify-center w-full">
                                <label for="fileInput" class="flex flex-col items-center justify-center w-full h-64 bg-gray-50 dark:bg-gray-700 border border-dashed border-gray-300 dark:border-gray-600 rounded-base cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400 pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h3a3 3 0 0 0 0-6h-.025a5.56 5.56 0 0 0 .025-.5A5.5 5.5 0 0 0 7.207 9.021C7.137 9.017 7.071 9 7 9a4 4 0 1 0 0 8h2.167M12 19v-9m0 0-2 2m2-2 2 2"/>
                                        </svg>
                                        <p class="mb-2 text-sm"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                        <p class="text-xs">SVG, PNG, JPG, PDF oder GIF (MAX. 10MB)</p>
                                    </div>
                                    <input id="fileInput" name="file" type="file" class="hidden" onchange="handleFileSelect(event)" multiple>
                                </label>
                            </div>
                            <div id="selectedFilesList" class="mt-4 space-y-2"></div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="closeAttachmentModal()" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-200 bg-white dark:bg-primary-300 border border-gray-300 dark:border-primary-320 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Abbrechen
                            </button>
                            <button type="button" onclick="uploadSelectedFiles()" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary-600 dark:bg-primary-700 rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Hochladen
                            </button>
                        </div>
                    </div>
                    
                    <!-- Link-Tab -->
                    <div id="tabContentLink" class="attachment-tab-content hidden">
                        <div class="mb-4">
                            <label for="linkUrl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                URL *
                            </label>
                            <input type="url" id="linkUrl" 
                                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-base bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 dark:focus:ring-primary-250/30 dark:focus:border-primary-250 transition-colors"
                                   placeholder="https://example.com">
                        </div>
                        <div class="mb-4">
                            <label for="linkTitel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Titel (optional)
                            </label>
                            <input type="text" id="linkTitel" 
                                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-base bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 dark:focus:ring-primary-250/30 dark:focus:border-primary-250 transition-colors"
                                   placeholder="Beschreibung des Links">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="closeAttachmentModal()" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-primary-200 bg-white dark:bg-primary-300 border border-gray-300 dark:border-primary-320 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Abbrechen
                            </button>
                            <button type="button" onclick="addLink()" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary-600 dark:bg-primary-700 rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Hinzufügen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const deviceId = <?php echo $deviceId; ?>;
const devicesApiUrl = '<?php echo BASE_URL; ?>devices/api/devices.php';
const logsApiUrl = '<?php echo BASE_URL; ?>logs/api/logs.php';
const deviceAttachmentsApiUrl = '<?php echo BASE_URL; ?>devices/api/attachments.php';
const ticketAttachmentsApiUrl = '<?php echo BASE_URL; ?>tickets/api/attachments.php';
const consumablesApiUrl = '<?php echo BASE_URL; ?>inventory/api/consumables.php';
const explosionDrawingsApiUrl = '<?php echo BASE_URL; ?>devices/api/explosion_drawings.php';
const currentUserRole = '<?php echo addslashes($userRole); ?>';

// Vorschlagslisten für Hersteller/Modell bei Explosionszeichnungen
let explosionManufacturers = [];
let explosionModels = [];

// Funktion zur Ermittlung des Icons für den Gerätetyp
function getTypeIcon(typ) {
    const icons = {
        'drucker': '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>',
        'computer': '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
        'netzwerk': '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" /></svg>',
        'smartphone': '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
        'monitor': '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
        'divers': '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>'
    };
    return icons[typ] || '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>';
}

function getStatusBadge(status) {
    const badges = {
        'aktiv': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aktiv</span>',
        'inaktiv': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inaktiv</span>',
        'wartung': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Wartung</span>',
        'ausgemustert': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Ausgemustert</span>'
    };
    return badges[status] || badges['aktiv'];
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    loadDevice();
    // Logs und Anhänge werden beim Tab-Wechsel geladen
});

function loadDevice() {
    fetch(devicesApiUrl + '?id=' + deviceId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.device) {
                displayDevice(data.device);
            } else {
                document.getElementById('deviceContent').innerHTML = 
                    '<div class="p-6 text-red-500">Fehler beim Laden der Gerätedaten</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            document.getElementById('deviceContent').innerHTML = 
                '<div class="p-6 text-red-500">Fehler beim Laden der Gerätedaten</div>';
        });
}

function loadLogs() {
    fetch(logsApiUrl + '?kategorie=device&entity_id=' + deviceId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.logs) {
                displayLogs(data.logs);
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Logs:', error);
        });
}

function displayDevice(device) {
    const typLabels = {
        'drucker': 'Drucker',
        'computer': 'Computer',
        'netzwerk': 'Netzwerkgerät',
        'smartphone': 'Smartphone',
        'monitor': 'Monitor',
        'divers': 'Divers'
    };
    
    const typeIcon = getTypeIcon(device.typ);
    const typLabel = typLabels[device.typ] || device.typ || '-';
    const statusBadge = getStatusBadge(device.status || 'aktiv');
    
    let detailsHtml = '';
    if (device.details) {
        try {
            const details = typeof device.details === 'string' ? JSON.parse(device.details) : device.details;
            const detailKeys = Object.keys(details);
            if (detailKeys.length > 0) {
                detailsHtml = '<div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6"><h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2"><svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>Spezifikationen</h3><div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
                detailKeys.forEach(key => {
                    if (details[key]) {
                        const fieldLabel = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                        detailsHtml += `<div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">${escapeHtml(fieldLabel)}</label><p class="text-gray-900 dark:text-white">${escapeHtml(details[key])}</p></div>`;
                    }
                });
                detailsHtml += '</div></div>';
            }
        } catch (e) {
            console.error('Fehler beim Parsen der Details:', e);
        }
    } else {
        detailsHtml = '<div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6"><h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2"><svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>Spezifikationen</h3><div class="text-gray-500 dark:text-gray-400">Keine spezifischen Details vorhanden</div></div>';
    }
    
    // Titel aktualisieren
    const titleElement = document.getElementById('deviceTitle');
    if (titleElement) {
        titleElement.textContent = escapeHtml(device.name);
    }
    
    // Buttons hinzufügen
    const editButtonContainer = document.getElementById('editButtonContainer');
    if (editButtonContainer) {
        const baseUrl = '<?php echo BASE_URL; ?>';
        const currentStatus = device.status || 'aktiv';
        
        // Status-Button Klassen basierend auf aktuellem Status
        const getStatusButtonClass = (status) => {
            const baseClass = 'text-sm px-3 py-2 font-medium leading-5 focus:ring-3 focus:outline-none border border-gray-300 dark:border-gray-600';
            if (status === currentStatus) {
                // Aktiver Status - farbig
                if (status === 'aktiv') {
                    return baseClass + ' bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 border-green-300 dark:border-green-700';
                } else if (status === 'inaktiv') {
                    return baseClass + ' bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 border-red-300 dark:border-red-700';
                } else if (status === 'wartung') {
                    return baseClass + ' bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 border-yellow-300 dark:border-yellow-700';
                } else if (status === 'ausgemustert') {
                    return baseClass + ' bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600';
                }
            }
            // Inaktiver Status - Standard
            return baseClass + ' text-gray-900 bg-white dark:bg-gray-800 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-primary-500 dark:focus:ring-primary-400';
        };
        
        editButtonContainer.innerHTML = `
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Status-Buttons -->
                <div class="inline-flex rounded-base shadow-xs -space-x-px" role="group">
                    <button type="button" onclick="changeDeviceStatus('aktiv')" 
                            class="${getStatusButtonClass('aktiv')} rounded-s-base focus:ring-green-500 dark:focus:ring-green-400" 
                            title="Status auf Aktiv setzen">
                        Aktiv
                    </button>
                    <button type="button" onclick="changeDeviceStatus('inaktiv')" 
                            class="${getStatusButtonClass('inaktiv')} focus:ring-red-500 dark:focus:ring-red-400" 
                            title="Status auf Inaktiv setzen">
                        Inaktiv
                    </button>
                    <button type="button" onclick="changeDeviceStatus('wartung')" 
                            class="${getStatusButtonClass('wartung')} focus:ring-yellow-500 dark:focus:ring-yellow-400" 
                            title="Status auf Wartung setzen">
                        Wartung
                    </button>
                    <button type="button" onclick="changeDeviceStatus('ausgemustert')" 
                            class="${getStatusButtonClass('ausgemustert')} rounded-e-base focus:ring-gray-500 dark:focus:ring-gray-400" 
                            title="Status auf Ausgemustert setzen">
                        Ausgemustert
                    </button>
                </div>
                
                <!-- Bearbeiten- und Löschen-Buttons -->
                <div class="inline-flex rounded-base shadow-xs -space-x-px" role="group">
                    <a href="${baseUrl}devices/edit.php?id=${device.id}" 
                       class="text-sm px-3 py-2 font-medium leading-5 text-gray-900 bg-white dark:bg-gray-800 dark:text-white border border-gray-300 dark:border-gray-600 rounded-s-base hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-3 focus:ring-primary-500 dark:focus:ring-primary-400 focus:outline-none inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Bearbeiten
                    </a>
                    <button type="button" onclick="deleteDevice(${device.id})" 
                            class="text-sm px-3 py-2 font-medium leading-5 text-white bg-red-600 dark:bg-red-700 border border-red-600 dark:border-red-700 rounded-e-base hover:bg-red-700 dark:hover:bg-red-600 focus:ring-3 focus:ring-red-500 dark:focus:ring-red-400 focus:outline-none inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Löschen
                    </button>
                </div>
            </div>
        `;
    }
    
    document.getElementById('deviceContent').innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <!-- Tabs Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                    <li class="me-2">
                        <a href="#" onclick="switchDeviceTab('overview'); return false;" id="tab-overview" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400 rounded-t-base active group">
                            <svg class="w-4 h-4 me-2 text-primary-600 dark:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.143 4H4.857A.857.857 0 0 0 4 4.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 10 9.143V4.857A.857.857 0 0 0 9.143 4Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 20 9.143V4.857A.857.857 0 0 0 19.143 4Zm-10 10H4.857a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286A.857.857 0 0 0 9.143 14Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286a.857.857 0 0 0-.857-.857Z"/>
                            </svg>
                            Übersicht
                        </a>
                    </li>
                    <li class="me-2">
                        <a href="#" onclick="switchDeviceTab('attachments'); return false;" id="tab-attachments" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Anhänge
                        </a>
                    </li>
                    <li class="me-2">
                        <a href="#" onclick="switchDeviceTab('consumables'); return false;" id="tab-consumables" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m0 0l8-4 8 4m0-6v12l-8 4m8-4l8-4m-8 4l-8-4" />
                            </svg>
                            Verbrauchsmaterial
                        </a>
                    </li>
                    <li class="me-2">
                        <a href="#" onclick="switchDeviceTab('explosion-drawings'); return false;" id="tab-explosion-drawings" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Explosionszeichnungen
                        </a>
                    </li>
                    <li class="me-2">
                        <a href="#" onclick="switchDeviceTab('logs'); return false;" id="tab-logs" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Änderungsprotokoll
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Tab Content: Übersicht -->
            <div id="tab-content-overview" class="tab-content p-6">
                <form>
                    <!-- Gerätename und Beschreibung -->
                    <div class="mb-6 grid grid-cols-5 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gerätename</label>
                            <div class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                ${escapeHtml(device.name)}
                            </div>
                        </div>
                        <div class="col-span-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Standort / Notiz</label>
                            <div class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white min-h-[42px]">
                                ${device.beschreibung ? escapeHtml(device.beschreibung) : '-'}
                            </div>
                        </div>
                    </div>

                    <!-- Gerätetyp -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gerätetyp</label>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600">
                            <div class="flex items-center gap-2">
                                ${typeIcon}
                                <span class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(typLabel)}</span>
                            </div>
                            ${statusBadge}
                        </div>
                    </div>

                    <!-- Grundlegende Informationen -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Infos Card: Hersteller, Modell, Seriennummer, Betriebssystem -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Infos
                            </h3>
                            <div class="space-y-4">
                                <div class="grid grid-cols-4 gap-4">
                                    <div class="col-span-1">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hersteller</label>
                                        <p class="text-gray-900 dark:text-white">${escapeHtml(device.hersteller || '-')}</p>
                                    </div>
                                    <div class="col-span-1">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Modell</label>
                                        <p class="text-gray-900 dark:text-white">${escapeHtml(device.modell || '-')}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Seriennummer</label>
                                        <p class="text-gray-900 dark:text-white font-mono text-sm">${escapeHtml(device.seriennummer || '-')}</p>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Betriebssystem</label>
                                    <p class="text-gray-900 dark:text-white">${escapeHtml(device.betriebssystem || '-')}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Netzwerk Card: MAC- und IP-Adresse -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                </svg>
                                Netzwerk
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">MAC-Adresse</label>
                                    <p class="text-gray-900 dark:text-white font-mono text-sm">${escapeHtml(device.mac_adresse || '-')}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">IP-Adresse</label>
                                    <p class="text-gray-900 dark:text-white font-mono text-sm">${escapeHtml(device.ip_adresse || '-')}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zuordnung und Spezifikationen -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Zuordnung Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Zuordnung
                            </h3>
                            <div class="space-y-4">
                                ${device.company_name ? `
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Firma</label>
                                    <input type="text" value="${escapeHtml(device.company_name)}" readonly
                                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white cursor-not-allowed">
                                </div>
                                ` : ''}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kunde</label>
                                    <input type="text" value="${escapeHtml(device.customer_name || '-')}" readonly
                                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Benutzer</label>
                                    <input type="text" value="${device.user_vorname && device.user_nachname ? escapeHtml(device.user_vorname + ' ' + device.user_nachname) : '-'}" readonly
                                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white cursor-not-allowed">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Spezifikationen Card -->
                        ${detailsHtml}
                    </div>
                </form>
            </div>
            
            <!-- Tab Content: Anhänge -->
            <div id="tab-content-attachments" class="tab-content hidden p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Anhänge</h2>
                    <button type="button" onclick="openAttachmentModal()" 
                            class="text-sm px-3 py-2 font-medium text-white bg-primary-600 dark:bg-primary-700 border border-primary-600 dark:border-primary-700 rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 focus:ring-3 focus:ring-primary-500 dark:focus:ring-primary-400 focus:outline-none inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Anhang hinzufügen
                    </button>
                </div>
                <div id="attachmentsContainer" class="space-y-4">
                    <div class="flex items-center justify-center py-4">
                        <div role="status">
                            <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-500 animate-spin fill-primary-600 dark:fill-primary-400" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                            </svg>
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Content: Verbrauchsmaterial -->
            <div id="tab-content-consumables" class="tab-content hidden p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Verbrauchsmaterial / Ersatzteile</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Zu diesem Gerätemodell (Hersteller + Modell) zugeordnete Verbrauchsmaterialien. Zugeordnungen werden im Lager gepflegt.</p>
                <div id="consumablesContainer" class="space-y-4">
                    <div class="flex items-center justify-center py-4">
                        <div role="status">
                            <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-500 animate-spin fill-primary-600 dark:fill-primary-400" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                            </svg>
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Explosionszeichnungen -->
            <div id="tab-content-explosion-drawings" class="tab-content hidden p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Explosionszeichnungen</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Zu diesem Gerätemodell (Hersteller + Modell) zugeordnete Explosionszeichnungen. Eine Zeichnung kann mehreren Gerätemodellen zugeordnet werden.</p>
                    </div>
                    ${(currentUserRole === 'Admin' || currentUserRole === 'Techniker') ? `
                    <button type="button" onclick="openExplosionDrawingModal()" 
                            class="text-sm px-3 py-2 font-medium text-white bg-primary-600 dark:bg-primary-700 border border-primary-600 dark:border-primary-700 rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 focus:ring-3 focus:ring-primary-500 dark:focus:ring-primary-400 focus:outline-none inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Explosionszeichnung hinzufügen
                    </button>
                    ` : ''}
                </div>
                <div id="explosionDrawingsContainer" class="space-y-4">
                    <div class="flex items-center justify-center py-4">
                        <div role="status">
                            <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-500 animate-spin fill-primary-600 dark:fill-primary-400" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                            </svg>
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Logs -->
            <div id="tab-content-logs" class="tab-content hidden p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Änderungsprotokoll</h2>
                <div id="logsContainer" class="space-y-4">
                    <div class="flex items-center justify-center py-4">
                        <div role="status">
                            <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-500 animate-spin fill-primary-600 dark:fill-primary-400" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                            </svg>
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function formatDateForTimeline(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}. ${month} ${year}, ${hours}:${minutes} Uhr`;
}

function displayLogs(logs) {
    const logsContainer = document.getElementById('logsContainer');
    if (!logsContainer) return;
    
    if (logs.length === 0) {
        logsContainer.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Einträge vorhanden</div>';
        return;
    }
    
    const actionText = {
        'created': 'Erstellt',
        'updated': 'Aktualisiert',
        'deleted': 'Gelöscht'
    };
    
    const actionIcons = {
        'created': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>',
        'updated': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
        'deleted': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>'
    };
    
    logsContainer.innerHTML = `
        <ol class="relative border-s border-gray-200 dark:border-gray-700 ml-4">
            ${logs.map((log, index) => {
                // Bestimme den anzuzeigenden Namen basierend auf Rollen
                let userName;
                if (currentUserRole === 'Admin' || currentUserRole === 'Techniker') {
                    // Admin und Techniker sehen alle Namen
                    userName = log.user_vorname && log.user_nachname 
                        ? `${log.user_vorname} ${log.user_nachname}` 
                        : (log.user_email || 'Unbekannt');
                } else {
                    // Andere Benutzer sehen "Techniker" statt Namen von Admin/Techniker
                    if (log.user_rolle === 'Admin' || log.user_rolle === 'Techniker') {
                        userName = 'Techniker';
                    } else {
                        userName = log.user_vorname && log.user_nachname 
                            ? `${log.user_vorname} ${log.user_nachname}` 
                            : (log.user_email || 'Unbekannt');
                    }
                }
                const date = formatDateForTimeline(log.erstellt_datum);
                const actionTextLabel = actionText[log.action] || log.action;
                const actionIcon = actionIcons[log.action] || actionIcons['updated'];
                const isLatest = index === 0;
                
                let changeDetails = '';
                if (log.field_name) {
                    const fieldLabel = log.field_name.charAt(0).toUpperCase() + log.field_name.slice(1).replace(/_/g, ' ');
                    changeDetails = `<p class="mb-4 text-gray-600 dark:text-gray-400">Feld <strong>${escapeHtml(fieldLabel)}</strong> wurde geändert`;
                    if (log.old_value !== null && log.old_value !== '') {
                        changeDetails += ` von <span class="text-red-600 dark:text-red-400">"${escapeHtml(String(log.old_value))}"</span> zu <span class="text-green-600 dark:text-green-400">"${escapeHtml(String(log.new_value || ''))}"</span>`;
                    } else if (log.new_value) {
                        changeDetails += ` auf <span class="text-green-600 dark:text-green-400">"${escapeHtml(String(log.new_value))}"</span>`;
                    }
                    changeDetails += '.</p>';
                }
                
                if (log.beschreibung) {
                    changeDetails += `<p class="mb-4 text-gray-600 dark:text-gray-400">${escapeHtml(log.beschreibung)}</p>`;
                }
                
                if (!changeDetails && !log.beschreibung) {
                    changeDetails = `<p class="mb-4 text-gray-600 dark:text-gray-400">Gerät wurde ${actionTextLabel.toLowerCase()}.</p>`;
                }
                
                return `
                    <li class="mb-10 ms-6">
                        <span class="absolute flex items-center justify-center w-6 h-6 bg-primary-100 dark:bg-primary-900 rounded-full -start-3 ring-8 ring-white dark:ring-gray-800">
                            <svg class="w-3 h-3 text-primary-200 dark:text-primary-200" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                ${actionIcon}
                            </svg>
                        </span>
                        <time class="bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-xs font-medium px-1.5 py-0.5 rounded">
                            ${date}
                        </time>
                        <h3 class="flex items-center mb-1 text-lg font-semibold text-gray-900 dark:text-white my-2">
                            ${actionTextLabel}
                            ${isLatest ? '<span class="ms-2 bg-primary-100 dark:bg-primary-900 border border-primary-300 dark:border-primary-700 text-primary-600 dark:text-primary-400 text-xs font-medium px-1.5 py-0.5 rounded">Neueste</span>' : ''}
                        </h3>
                        <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">von <strong>${escapeHtml(userName)}</strong></p>
                        ${changeDetails}
                    </li>
                `;
            }).join('')}
        </ol>
    `;
}

function changeDeviceStatus(newStatus) {
    // Aktuelle Gerätedaten laden, um alle Felder zu haben
    fetch(devicesApiUrl + '?id=' + deviceId)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.device) {
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Laden der Gerätedaten', 'error');
                } else {
                    alert('Fehler beim Laden der Gerätedaten');
                }
                return;
            }
            
            const device = data.device;
            
            // Update mit allen Feldern, aber nur status wird geändert
            return fetch(devicesApiUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device_id: deviceId,
                    name: device.name || '',
                    typ: device.typ || '',
                    hersteller: device.hersteller || null,
                    modell: device.modell || null,
                    seriennummer: device.seriennummer || null,
                    mac_adresse: device.mac_adresse || null,
                    ip_adresse: device.ip_adresse || null,
                    betriebssystem: device.betriebssystem || null,
                    beschreibung: device.beschreibung || null,
                    company_id: device.company_id || null,
                    customer_id: device.customer_id || null,
                    user_id: device.user_id || null,
                    details: device.details || null,
                    status: newStatus
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') {
                    const statusLabels = {
                        'aktiv': 'Aktiv',
                        'inaktiv': 'Inaktiv',
                        'wartung': 'Wartung',
                        'ausgemustert': 'Ausgemustert'
                    };
                    showToast(`Status erfolgreich auf "${statusLabels[newStatus] || newStatus}" geändert`, 'success');
                }
                // Gerät neu laden
                loadDevice();
            } else {
                if (typeof showToast === 'function') {
                    showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            if (typeof showToast === 'function') {
                showToast('Fehler beim Ändern des Status', 'error');
            } else {
                alert('Fehler beim Ändern des Status');
            }
        });
}

function deleteDevice(deviceId) {
    const confirmMessage = currentUserRole === 'Admin' 
        ? 'Möchten Sie dieses Gerät wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.' 
        : 'Möchten Sie dieses Gerät wirklich auf inaktiv setzen?';
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    fetch(devicesApiUrl + '?id=' + deviceId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                const message = currentUserRole === 'Admin' 
                    ? 'Gerät erfolgreich gelöscht' 
                    : 'Gerät erfolgreich auf inaktiv gesetzt';
                showToast(message, 'success');
            }
            // Zur Geräte-Liste weiterleiten
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>devices/';
            }, 1000);
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        const errorMessage = currentUserRole === 'Admin' 
            ? 'Fehler beim Löschen des Geräts' 
            : 'Fehler beim Setzen des Geräts auf inaktiv';
        if (typeof showToast === 'function') {
            showToast(errorMessage, 'error');
        } else {
            alert(errorMessage);
        }
    });
}

// Anhänge-Funktionen
function loadAttachments() {
    const attachmentsContainer = document.getElementById('attachmentsContainer');
    if (!attachmentsContainer) return;
    
    // Geräte-Anhänge und Ticket-Anhänge in einem Request laden
    fetch(deviceAttachmentsApiUrl + '?device_id=' + deviceId + '&include_ticket_attachments=1')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const deviceAttachments = data.attachments || [];
                const ticketAttachments = data.ticket_attachments || [];
                displayAttachments(deviceAttachments, ticketAttachments);
            } else {
                console.error('Fehler beim Laden der Anhänge:', data.error);
                attachmentsContainer.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler beim Laden der Anhänge</div>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Anhänge:', error);
            attachmentsContainer.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler beim Laden der Anhänge</div>';
        });
}

function displayAttachments(deviceAttachments, ticketAttachments) {
    const attachmentsContainer = document.getElementById('attachmentsContainer');
    if (!attachmentsContainer) return;
    
    const allAttachments = [...deviceAttachments, ...ticketAttachments];
    
    if (allAttachments.length === 0) {
        attachmentsContainer.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Anhänge vorhanden</div>';
        return;
    }
    
    attachmentsContainer.innerHTML = allAttachments.map(attachment => {
        const isTicketAttachment = attachment.is_ticket_attachment || false;
        const isLink = attachment.anhang_typ === 'link' || (!attachment.dateiname && attachment.link_url);
        
        if (isLink) {
            // Link-Anhang
            const linkUrl = attachment.link_url || attachment.link_titel;
            const linkTitel = attachment.link_titel || linkUrl;
            const ticketInfo = isTicketAttachment ? `<span class="text-xs text-primary-600 dark:text-primary-400 font-medium">(aus Ticket #${attachment.ticket_nummer || attachment.ticket_id})</span>` : '';
            
            return `
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(linkTitel)}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">${escapeHtml(linkUrl)}</p>
                            <div class="mt-1 flex items-center gap-2">
                                ${ticketInfo}
                                <span class="text-xs text-gray-400 dark:text-gray-500">${formatDate(attachment.erstellt_datum)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="${escapeHtml(linkUrl)}" target="_blank" rel="noopener noreferrer"
                           class="text-sm px-3 py-2 font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 border border-primary-300 dark:border-primary-600 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900 focus:ring-3 focus:ring-primary-500 dark:focus:ring-primary-400 focus:outline-none inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Öffnen
                        </a>
                        ${!isTicketAttachment ? `
                        <button type="button" onclick="deleteAttachment(${attachment.id})" 
                                class="text-sm px-3 py-2 font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900 focus:ring-3 focus:ring-red-500 dark:focus:ring-red-400 focus:outline-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
        } else {
            // Datei-Anhang
            const fileName = attachment.dateiname || 'Unbekannte Datei';
            const fileSize = attachment.dateigroesse ? formatFileSize(attachment.dateigroesse) : '';
            const ticketInfo = isTicketAttachment ? `<span class="text-xs text-primary-600 dark:text-primary-400 font-medium">(aus Ticket #${attachment.ticket_nummer || attachment.ticket_id})</span>` : '';
            const downloadUrl = isTicketAttachment 
                ? ticketAttachmentsApiUrl + '?id=' + attachment.id
                : deviceAttachmentsApiUrl + '?id=' + attachment.id;
            
            return `
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(fileName)}</p>
                            <div class="mt-1 flex items-center gap-2">
                                ${ticketInfo}
                                ${fileSize ? `<span class="text-xs text-gray-500 dark:text-gray-400">${fileSize}</span>` : ''}
                                <span class="text-xs text-gray-400 dark:text-gray-500">${formatDate(attachment.erstellt_datum)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="${downloadUrl}" 
                           class="text-sm px-3 py-2 font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 border border-primary-300 dark:border-primary-600 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900 focus:ring-3 focus:ring-primary-500 dark:focus:ring-primary-400 focus:outline-none inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Herunterladen
                        </a>
                        ${!isTicketAttachment ? `
                        <button type="button" onclick="deleteAttachment(${attachment.id})" 
                                class="text-sm px-3 py-2 font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900 focus:ring-3 focus:ring-red-500 dark:focus:ring-red-400 focus:outline-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }
    }).join('');
}

function formatFileSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// Modal-Funktionen
let selectedFiles = [];

function openAttachmentModal() {
    const modal = document.getElementById('attachmentModal');
    if (modal) {
        modal.classList.remove('hidden');
        switchAttachmentTab('file');
        // Formular zurücksetzen
        document.getElementById('fileInput').value = '';
        document.getElementById('linkUrl').value = '';
        document.getElementById('linkTitel').value = '';
        selectedFiles = [];
        updateSelectedFilesList();
    }
}

function closeAttachmentModal() {
    const modal = document.getElementById('attachmentModal');
    if (modal) {
        modal.classList.add('hidden');
        // Formular zurücksetzen
        document.getElementById('fileInput').value = '';
        document.getElementById('linkUrl').value = '';
        document.getElementById('linkTitel').value = '';
        selectedFiles = [];
        updateSelectedFilesList();
    }
}

function switchAttachmentTab(tab) {
    const tabFile = document.getElementById('tabFile');
    const tabLink = document.getElementById('tabLink');
    const contentFile = document.getElementById('tabContentFile');
    const contentLink = document.getElementById('tabContentLink');
    
    if (tab === 'file') {
        tabFile.classList.add('border-primary-600', 'dark:border-primary-400', 'text-primary-600', 'dark:text-primary-400');
        tabFile.classList.remove('border-transparent', 'text-gray-500', 'dark:text-primary-240');
        tabLink.classList.remove('border-primary-600', 'dark:border-primary-400', 'text-primary-600', 'dark:text-primary-400');
        tabLink.classList.add('border-transparent', 'text-gray-500', 'dark:text-primary-240');
        contentFile.classList.remove('hidden');
        contentLink.classList.add('hidden');
    } else {
        tabLink.classList.add('border-primary-600', 'dark:border-primary-400', 'text-primary-600', 'dark:text-primary-400');
        tabLink.classList.remove('border-transparent', 'text-gray-500', 'dark:text-primary-240');
        tabFile.classList.remove('border-primary-600', 'dark:border-primary-400', 'text-primary-600', 'dark:text-primary-400');
        tabFile.classList.add('border-transparent', 'text-gray-500', 'dark:text-primary-240');
        contentLink.classList.remove('hidden');
        contentFile.classList.add('hidden');
    }
}

function handleFileSelect(event) {
    const files = event.target.files;
    if (!files || files.length === 0) return;
    
    Array.from(files).forEach(file => {
        // Prüfen ob Datei bereits ausgewählt
        const exists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
        if (!exists) {
            selectedFiles.push(file);
        }
    });
    
    updateSelectedFilesList();
}

function updateSelectedFilesList() {
    const listContainer = document.getElementById('selectedFilesList');
    if (!listContainer) return;
    
    if (selectedFiles.length === 0) {
        listContainer.innerHTML = '';
        return;
    }
    
    listContainer.innerHTML = selectedFiles.map((file, index) => `
        <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-primary-300 rounded-lg">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-primary-200 truncate">${escapeHtml(file.name)}</p>
                    <p class="text-xs text-gray-500 dark:text-primary-240">${formatFileSize(file.size)}</p>
                </div>
            </div>
            <button type="button" onclick="removeFile(${index})" 
                    class="ml-2 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `).join('');
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateSelectedFilesList();
}

function uploadSelectedFiles() {
    if (selectedFiles.length === 0) {
        if (typeof showToast === 'function') {
            showToast('Bitte wählen Sie mindestens eine Datei aus', 'error');
        } else {
            alert('Bitte wählen Sie mindestens eine Datei aus');
        }
        return;
    }
    
    // Alle Dateien hochladen
    const uploadPromises = selectedFiles.map(file => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('device_id', deviceId);
        formData.append('anhang_typ', 'datei');
        
        return fetch(deviceAttachmentsApiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Unbekannter Fehler');
            }
            return data;
        });
    });
    
    Promise.all(uploadPromises)
        .then(results => {
            if (typeof showToast === 'function') {
                showToast(`${results.length} Datei(en) erfolgreich hochgeladen`, 'success');
            }
            closeAttachmentModal();
            loadAttachments();
        })
        .catch(error => {
            console.error('Fehler:', error);
            if (typeof showToast === 'function') {
                showToast('Fehler beim Hochladen: ' + error.message, 'error');
            } else {
                alert('Fehler beim Hochladen: ' + error.message);
            }
        });
}

function addLink() {
    const linkUrl = document.getElementById('linkUrl').value.trim();
    const linkTitel = document.getElementById('linkTitel').value.trim();
    
    if (!linkUrl) {
        if (typeof showToast === 'function') {
            showToast('Bitte geben Sie eine URL ein', 'error');
        } else {
            alert('Bitte geben Sie eine URL ein');
        }
        return;
    }
    
    // URL validieren
    if (!linkUrl.match(/^https?:\/\/.+/)) {
        if (typeof showToast === 'function') {
            showToast('Bitte geben Sie eine gültige URL ein (beginnt mit http:// oder https://)', 'error');
        } else {
            alert('Bitte geben Sie eine gültige URL ein (beginnt mit http:// oder https://)');
        }
        return;
    }
    
    const formData = new FormData();
    formData.append('device_id', deviceId);
    formData.append('anhang_typ', 'link');
    formData.append('link_url', linkUrl);
    formData.append('link_titel', linkTitel || linkUrl);
    
    fetch(deviceAttachmentsApiUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Link erfolgreich hinzugefügt', 'success');
            }
            closeAttachmentModal();
            loadAttachments();
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Hinzufügen des Links', 'error');
        } else {
            alert('Fehler beim Hinzufügen des Links');
        }
    });
}

// Modal Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('attachmentModal');
    const overlay = document.getElementById('attachmentModalOverlay');
    const closeBtn = document.getElementById('closeAttachmentModalBtn');
    
    if (overlay) {
        overlay.addEventListener('click', closeAttachmentModal);
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeAttachmentModal);
    }
    
    // ESC-Taste zum Schließen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeAttachmentModal();
        }
    });
    
    // Drag & Drop für Datei-Upload
    const fileInputContainer = document.querySelector('#tabContentFile label[for="fileInput"]');
    if (fileInputContainer) {
        fileInputContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('bg-gray-100', 'dark:bg-gray-600', 'border-primary-400', 'dark:border-primary-400');
        });
        
        fileInputContainer.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('bg-gray-100', 'dark:bg-gray-600', 'border-primary-400', 'dark:border-primary-400');
        });
        
        fileInputContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('bg-gray-100', 'dark:bg-gray-600', 'border-primary-400', 'dark:border-primary-400');
            
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                Array.from(files).forEach(file => {
                    const exists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
                    if (!exists) {
                        selectedFiles.push(file);
                    }
                });
                updateSelectedFilesList();
            }
        });
    }
});

function deleteAttachment(attachmentId) {
    if (!confirm('Möchten Sie diesen Anhang wirklich löschen?')) {
        return;
    }
    
    fetch(deviceAttachmentsApiUrl + '?id=' + attachmentId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Anhang erfolgreich gelöscht', 'success');
            }
            loadAttachments();
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Löschen des Anhangs', 'error');
        } else {
            alert('Fehler beim Löschen des Anhangs');
        }
    });
}

// Tab-Wechsel Funktion
function switchDeviceTab(tabName) {
    // Alle Tab-Links und Tab-Contents verstecken
    document.querySelectorAll('.tab-link').forEach(link => {
        link.classList.remove('border-primary-600', 'dark:border-primary-400', 'text-primary-600', 'dark:text-primary-400', 'active');
        link.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
        const icon = link.querySelector('svg');
        if (icon) {
            icon.classList.remove('text-primary-600', 'dark:text-primary-400');
            icon.classList.add('text-gray-500', 'dark:text-gray-400');
        }
    });
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Aktiven Tab anzeigen
    const activeTab = document.getElementById('tab-' + tabName);
    const activeContent = document.getElementById('tab-content-' + tabName);
    
    if (activeTab) {
        activeTab.classList.add('border-primary-600', 'dark:border-primary-400', 'text-primary-600', 'dark:text-primary-400', 'active');
        activeTab.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
        const icon = activeTab.querySelector('svg');
        if (icon) {
            icon.classList.add('text-primary-600', 'dark:text-primary-400');
            icon.classList.remove('text-gray-500', 'dark:text-gray-400');
        }
    }
    
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
    
    // Anhänge, Verbrauchsmaterial, Explosionszeichnungen oder Logs laden, wenn der Tab aktiviert wird
    if (tabName === 'attachments') {
        loadAttachments();
    } else if (tabName === 'consumables') {
        loadConsumables();
    } else if (tabName === 'explosion-drawings') {
        loadExplosionDrawings();
    } else if (tabName === 'logs') {
        loadLogs();
    }
}

function loadConsumables() {
    const container = document.getElementById('consumablesContainer');
    if (!container) return;
    fetch(consumablesApiUrl + '?action=by_device&device_id=' + deviceId)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                container.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler beim Laden der Verbrauchsmaterialien</div>';
                return;
            }
            const list = data.consumables || [];
            if (list.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Verbrauchsmaterialien diesem Gerätemodell zugeordnet. Zuweisung im <a href="' + consumablesApiUrl.replace(/inventory\/api\/consumables\.php$/, 'inventory/') + '" class="text-primary-600 dark:text-primary-400 hover:underline">Lager</a> vornehmen.</div>';
                return;
            }
            container.innerHTML = list.map(c => {
                const bezeichnung = escapeHtml(c.bezeichnung || '');
                const artikelnummer = c.artikelnummer ? escapeHtml(c.artikelnummer) : '-';
                const beschreibung = c.beschreibung ? escapeHtml(c.beschreibung) : '';
                const mindestbestand = c.mindestbestand != null ? c.mindestbestand : '-';
                return '<div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4"><div class="flex items-start justify-between"><div><p class="text-sm font-medium text-gray-900 dark:text-white">' + bezeichnung + '</p>' + (artikelnummer !== '-' ? '<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Artikelnummer: ' + artikelnummer + '</p>' : '') + (beschreibung ? '<p class="text-sm text-gray-600 dark:text-gray-400 mt-2">' + beschreibung + '</p>' : '') + (mindestbestand !== '-' ? '<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Mindestbestand: ' + mindestbestand + '</p>' : '') + '</div></div></div>';
            }).join('');
        })
        .catch(() => {
            container.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler beim Laden der Verbrauchsmaterialien</div>';
        });
}

function loadExplosionDrawings() {
    const container = document.getElementById('explosionDrawingsContainer');
    if (!container) return;
    
    fetch(explosionDrawingsApiUrl + '?device_id=' + deviceId)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                container.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler beim Laden der Explosionszeichnungen</div>';
                return;
            }
            const drawings = data.drawings || [];
            if (drawings.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Explosionszeichnungen für dieses Gerätemodell vorhanden.</div>';
                return;
            }
            container.innerHTML = drawings.map(drawing => {
                const bezeichnung = escapeHtml(drawing.bezeichnung || '');
                const beschreibung = drawing.beschreibung ? escapeHtml(drawing.beschreibung) : '';
                const dateiname = escapeHtml(drawing.dateiname || '');
                const dateigroesse = drawing.dateigroesse ? formatFileSize(drawing.dateigroesse) : '';
                const erstelltDatum = formatDate(drawing.erstellt_datum);
                const ersteller = drawing.ersteller_vorname && drawing.ersteller_nachname 
                    ? escapeHtml(drawing.ersteller_vorname + ' ' + drawing.ersteller_nachname)
                    : 'Unbekannt';
                const downloadUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>') + drawing.dateipfad;
                const canDelete = (currentUserRole === 'Admin' || currentUserRole === 'Techniker');
                const mimeType = drawing.mime_type || '';
                const isImage = mimeType.startsWith('image/') || mimeType === 'application/pdf';
                
                return `
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="flex-shrink-0">
                                    ${isImage ? `
                                        <svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    ` : `
                                        <svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    `}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${bezeichnung}</p>
                                    ${beschreibung ? `<p class="text-sm text-gray-600 dark:text-gray-400 mt-1">${beschreibung}</p>` : ''}
                                    <div class="mt-2 flex items-center gap-3 flex-wrap">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">${dateiname}</span>
                                        ${dateigroesse ? `<span class="text-xs text-gray-500 dark:text-gray-400">${dateigroesse}</span>` : ''}
                                        <span class="text-xs text-gray-500 dark:text-gray-400">${erstelltDatum}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">von ${ersteller}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <a href="${downloadUrl}" target="_blank" download class="text-sm px-3 py-2 font-medium text-primary-600 dark:text-primary-400 border border-primary-600 dark:border-primary-400 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 inline-flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Herunterladen
                                </a>
                                ${canDelete ? `
                                    <button type="button" onclick="deleteExplosionDrawing(${drawing.id})" class="text-sm px-3 py-2 font-medium text-red-600 dark:text-red-400 border border-red-600 dark:border-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Löschen
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        })
        .catch(error => {
            console.error('Fehler beim Laden der Explosionszeichnungen:', error);
            container.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler beim Laden der Explosionszeichnungen</div>';
        });
}

async function loadExplosionManufacturers() {
    try {
        const r = await fetch(devicesApiUrl + '?action=get_manufacturers');
        const d = await r.json();
        if (d.success) explosionManufacturers = d.manufacturers || [];
    } catch (e) { console.error('Hersteller laden:', e); }
}

async function loadExplosionModels(manufacturer) {
    try {
        const url = manufacturer
            ? devicesApiUrl + '?action=get_models&manufacturer=' + encodeURIComponent(manufacturer)
            : devicesApiUrl + '?action=get_models';
        const r = await fetch(url);
        const d = await r.json();
        if (d.success) explosionModels = d.models || [];
    } catch (e) { console.error('Modelle laden:', e); }
}

function showExplosionSuggestions(inputEl, items, type) {
    const wrapper = inputEl.closest('.relative');
    if (!wrapper) return;
    const suggestionsDiv = wrapper.querySelector('.explosion-dm-suggestions[data-dm-type="' + type + '"]');
    if (!suggestionsDiv) return;
    const value = (inputEl.value || '').toLowerCase().trim();
    const filtered = items.filter(function(item) {
        return item && item.toLowerCase().includes(value) && item.toLowerCase() !== value;
    });
    if (filtered.length === 0 || value.length === 0) {
        suggestionsDiv.classList.add('hidden');
        suggestionsDiv.innerHTML = '';
        return;
    }
    suggestionsDiv.innerHTML = filtered.slice(0, 12).map(function(item) {
        return '<div class="explosion-dm-suggestion-item px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-gray-900 dark:text-white" data-value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</div>';
    }).join('');
    suggestionsDiv.classList.remove('hidden');
}

function hideAllExplosionSuggestions() {
    const container = document.getElementById('deviceModelsList');
    if (!container) return;
    container.querySelectorAll('.explosion-dm-suggestions').forEach(function(el) {
        el.classList.add('hidden');
    });
}

function setupExplosionDeviceModelAutocomplete() {
    const container = document.getElementById('deviceModelsList');
    if (!container) return;
    container.addEventListener('input', function(e) {
        const el = e.target;
        if (el.classList.contains('explosion-hersteller')) {
            showExplosionSuggestions(el, explosionManufacturers, 'hersteller');
        } else if (el.classList.contains('explosion-modell')) {
            const herstellerInput = el.closest('.flex').querySelector('.explosion-hersteller');
            const hersteller = herstellerInput ? herstellerInput.value.trim() : '';
            if (hersteller) {
                loadExplosionModels(hersteller).then(function() {
                    showExplosionSuggestions(el, explosionModels, 'modell');
                });
            } else {
                showExplosionSuggestions(el, explosionModels, 'modell');
            }
        }
    });
    container.addEventListener('focus', function(e) {
        const el = e.target;
        if (el.classList.contains('explosion-hersteller') && (el.value || '').trim()) {
            showExplosionSuggestions(el, explosionManufacturers, 'hersteller');
        } else if (el.classList.contains('explosion-modell') && (el.value || '').trim()) {
            const herstellerInput = el.closest('.flex').querySelector('.explosion-hersteller');
            const hersteller = herstellerInput ? herstellerInput.value.trim() : '';
            if (hersteller) {
                loadExplosionModels(hersteller).then(function() {
                    showExplosionSuggestions(el, explosionModels, 'modell');
                });
            } else {
                showExplosionSuggestions(el, explosionModels, 'modell');
            }
        }
    }, true);
    container.addEventListener('blur', function(e) {
        const el = e.target;
        if (el.classList.contains('explosion-hersteller') || el.classList.contains('explosion-modell')) {
            setTimeout(hideAllExplosionSuggestions, 200);
        }
    }, true);
    container.addEventListener('click', function(e) {
        const item = e.target.closest('.explosion-dm-suggestion-item');
        if (!item) return;
        e.preventDefault();
        const value = item.getAttribute('data-value');
        const wrapper = item.closest('.relative');
        if (!wrapper || !value) return;
        const type = item.closest('.explosion-dm-suggestions').getAttribute('data-dm-type');
        const input = wrapper.querySelector('.explosion-' + type);
        if (input) {
            input.value = value;
            hideAllExplosionSuggestions();
            // Wenn Hersteller geändert wurde, Modelle neu laden
            if (type === 'hersteller') {
                const modellInput = wrapper.parentElement.querySelector('.explosion-modell');
                if (modellInput) {
                    modellInput.value = '';
                    loadExplosionModels(value).then(function() {});
                }
            }
        }
    });
}

function openExplosionDrawingModal() {
    // Gerätedaten abrufen für Vorausfüllung
    fetch(devicesApiUrl + '?id=' + deviceId)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.device) {
                alert('Fehler beim Laden der Gerätedaten');
                return;
            }
            const device = data.device;
            
            // Modal erstellen
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Explosionszeichnung hochladen</h3>
                            <button type="button" onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form id="explosionDrawingForm" enctype="multipart/form-data">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bezeichnung *</label>
                                    <input type="text" name="bezeichnung" required
                                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                           placeholder="z.B. Explosionszeichnung Modell XYZ">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beschreibung (optional)</label>
                                    <textarea name="beschreibung" rows="3"
                                              class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                              placeholder="Optionale Beschreibung"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Datei *</label>
                                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.gif"
                                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PDF, JPG, PNG, GIF (max. 20MB)</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gerätemodelle *</label>
                                    <div id="deviceModelsList" class="space-y-2 mb-2">
                                        <div class="flex items-center gap-2">
                                            <div class="relative flex-1">
                                                <input type="text" name="hersteller[]" value="${escapeHtml(device.hersteller || '')}" placeholder="Hersteller" autocomplete="off"
                                                       class="explosion-hersteller w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                <div class="explosion-dm-suggestions hidden absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto" data-dm-type="hersteller"></div>
                                            </div>
                                            <div class="relative flex-1">
                                                <input type="text" name="modell[]" value="${escapeHtml(device.modell || '')}" placeholder="Modell" autocomplete="off"
                                                       class="explosion-modell w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                <div class="explosion-dm-suggestions hidden absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto" data-dm-type="modell"></div>
                                            </div>
                                            <button type="button" onclick="this.closest('.flex').remove()" class="p-2 text-gray-500 hover:text-red-600 dark:hover:text-red-400 flex-shrink-0" title="Entfernen">×</button>
                                        </div>
                                    </div>
                                    <button type="button" onclick="addDeviceModelRow()" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">
                                        + Weitere Gerätemodelle hinzufügen
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-end gap-3 mt-6">
                                <button type="button" onclick="this.closest('.fixed').remove()" 
                                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                                    Abbrechen
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 dark:bg-primary-700 rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600">
                                    Hochladen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Hersteller und Modelle laden
            loadExplosionManufacturers().then(function() {});
            loadExplosionModels().then(function() {});
            
            // Autocomplete einrichten
            setupExplosionDeviceModelAutocomplete();
            
            // Formular-Handler
            document.getElementById('explosionDrawingForm').addEventListener('submit', function(e) {
                e.preventDefault();
                uploadExplosionDrawing(this);
            });
        })
        .catch(error => {
            console.error('Fehler:', error);
            alert('Fehler beim Laden der Gerätedaten');
        });
}

function addDeviceModelRow() {
    const container = document.getElementById('deviceModelsList');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2';
    row.innerHTML = `
        <div class="relative flex-1">
            <input type="text" name="hersteller[]" placeholder="Hersteller" autocomplete="off"
                   class="explosion-hersteller w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
            <div class="explosion-dm-suggestions hidden absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto" data-dm-type="hersteller"></div>
        </div>
        <div class="relative flex-1">
            <input type="text" name="modell[]" placeholder="Modell" autocomplete="off"
                   class="explosion-modell w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
            <div class="explosion-dm-suggestions hidden absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto" data-dm-type="modell"></div>
        </div>
        <button type="button" onclick="this.closest('.flex').remove()" class="p-2 text-gray-500 hover:text-red-600 dark:hover:text-red-400 flex-shrink-0" title="Entfernen">×</button>
    `;
    container.appendChild(row);
}

function uploadExplosionDrawing(form) {
    const formData = new FormData(form);
    const bezeichnung = formData.get('bezeichnung');
    const beschreibung = formData.get('beschreibung') || '';
    const file = formData.get('file');
    
    if (!bezeichnung || !file) {
        alert('Bezeichnung und Datei sind erforderlich');
        return;
    }
    
    // Gerätemodelle sammeln
    const deviceModels = [];
    const herstellerInputs = form.querySelectorAll('input[name="hersteller[]"]');
    const modellInputs = form.querySelectorAll('input[name="modell[]"]');
    
    for (let i = 0; i < herstellerInputs.length; i++) {
        const h = (herstellerInputs[i].value || '').trim();
        const m = (modellInputs[i].value || '').trim();
        if (h || m) {
            deviceModels.push({ hersteller: h, modell: m });
        }
    }
    
    if (deviceModels.length === 0) {
        alert('Mindestens ein Gerätemodell muss angegeben werden');
        return;
    }
    
    // Neues FormData für Upload
    const uploadData = new FormData();
    uploadData.append('bezeichnung', bezeichnung);
    uploadData.append('beschreibung', beschreibung);
    uploadData.append('file', file);
    uploadData.append('device_models', JSON.stringify(deviceModels));
    
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird hochgeladen...';
    
    fetch(explosionDrawingsApiUrl, {
        method: 'POST',
        body: uploadData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                form.closest('.fixed').remove();
                loadExplosionDrawings();
                alert('Explosionszeichnung erfolgreich hochgeladen');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                submitBtn.disabled = false;
                submitBtn.textContent = 'Hochladen';
            }
        })
        .catch(error => {
            console.error('Upload-Fehler:', error);
            alert('Fehler beim Hochladen der Datei');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Hochladen';
        });
}

function deleteExplosionDrawing(drawingId) {
    if (!confirm('Möchten Sie diese Explosionszeichnung wirklich löschen?')) {
        return;
    }
    
    fetch(explosionDrawingsApiUrl + '?id=' + drawingId, {
        method: 'DELETE'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadExplosionDrawings();
                alert('Explosionszeichnung erfolgreich gelöscht');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Lösch-Fehler:', error);
            alert('Fehler beim Löschen der Explosionszeichnung');
        });
}

</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
