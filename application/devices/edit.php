<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/customers/helper/encryption.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
requireLogin();

$deviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$deviceId) {
    header('Location: ' . BASE_URL . 'devices/');
    exit;
}

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, customer_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
    $userCustomerId = $user['customer_id'] ?? null;
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Firmen und Kunden für Dropdown laden
$companies = [];
$customers = [];
$users = [];

// Prüfen ob Firma in Navigation ausgewählt ist
$selectedCompanyId = null;
if (isset($_SESSION['selected_company_id'])) {
    $selectedCompanyId = (int)$_SESSION['selected_company_id'];
} elseif (isset($_GET['company_id'])) {
    $selectedCompanyId = (int)$_GET['company_id'];
}

if ($userRole === 'Admin' || $userRole === 'Techniker') {
    // Alle aktiven Firmen
    $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($companies as &$co) { decrypt_company_row($co); }
    unset($co);
    
    // Alle Kunden (mit und ohne Firma) für Admin/Techniker
    $stmt = $pdo->query("SELECT id, name, email, company_id FROM customers WHERE status = 'aktiv' ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($customers as &$c) { decrypt_customer_row($c); }
    unset($c);
    
    // Benutzer laden wenn Firma ausgewählt ist
    if ($selectedCompanyId) {
        $stmt = $pdo->prepare("SELECT id, vorname, nachname, email FROM users WHERE company_id = ? AND status = 'aktiv' ORDER BY nachname, vorname");
        $stmt->execute([$selectedCompanyId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
    // Nur eigene Firma
    $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv'");
    $stmt->execute([$userCompanyId]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($companies as &$co) { decrypt_company_row($co); }
    unset($co);
    
    // Kunden der Firma und Kunden ohne Firma
    $stmt = $pdo->prepare("SELECT id, name, email, company_id FROM customers WHERE (company_id = ? OR company_id IS NULL) AND status = 'aktiv' ORDER BY name");
    $stmt->execute([$userCompanyId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($customers as &$c) { decrypt_customer_row($c); }
    unset($c);
    
    // Benutzer der Firma
    $stmt = $pdo->prepare("SELECT id, vorname, nachname, email FROM users WHERE company_id = ? AND status = 'aktiv' ORDER BY nachname, vorname");
    $stmt->execute([$userCompanyId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($userRole === 'Firmen-User') {
    // Firmen-User kann nur für sich selbst erstellen
    if ($userCompanyId) {
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv'");
        $stmt->execute([$userCompanyId]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
                    <div class="mb-4 sm:mb-0">
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
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Gerät bearbeiten</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gerät bearbeiten</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Bearbeiten Sie die Geräteinformationen</p>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <div id="deviceContent" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-spinner fa-spin mr-2"></i> Lade Gerätedaten...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/nav-unsaved-changes.js"></script>
<script>
const deviceId = <?php echo $deviceId; ?>;
const devicesApiUrl = '<?php echo BASE_URL; ?>devices/api/devices.php';
const customersApiUrl = '<?php echo BASE_URL; ?>customers/api/customers.php';
const userRole = '<?php echo $userRole; ?>';
let allUsers = <?php echo json_encode($users); ?>;
const editBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>';
const allCompanies = <?php echo json_encode($companies); ?>;

let allCustomers = [];
let filteredCustomers = [];
let customerSortColumn = null;
let customerSortDirection = 'asc';

let allUsersList = [];
let filteredUsers = [];
let userSortColumn = null;
let userSortDirection = 'asc';

// Autocomplete für Hersteller und Modell
let manufacturers = [];
let models = [];
let selectedManufacturer = '';

// Hersteller und Modelle laden
async function loadManufacturers() {
    try {
        const response = await fetch(devicesApiUrl + '?action=get_manufacturers');
        const data = await response.json();
        if (data.success) {
            manufacturers = data.manufacturers || [];
        }
    } catch (error) {
        console.error('Fehler beim Laden der Hersteller:', error);
    }
}

async function loadModels(manufacturer = null) {
    try {
        const url = manufacturer 
            ? devicesApiUrl + '?action=get_models&manufacturer=' + encodeURIComponent(manufacturer)
            : devicesApiUrl + '?action=get_models';
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            models = data.models || [];
        }
    } catch (error) {
        console.error('Fehler beim Laden der Modelle:', error);
    }
}

// Autocomplete-Funktion
function setupAutocomplete(inputId, suggestionsId, items, onSelect) {
    const input = document.getElementById(inputId);
    const suggestionsDiv = document.getElementById(suggestionsId);
    
    if (!input || !suggestionsDiv) return;
    
    input.addEventListener('input', function() {
        const value = this.value.toLowerCase();
        const filtered = items.filter(item => 
            item.toLowerCase().includes(value) && item.toLowerCase() !== value
        );
        
        if (filtered.length > 0 && value.length > 0) {
            suggestionsDiv.innerHTML = filtered.slice(0, 10).map(item => 
                `<div class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer" onclick="selectSuggestion('${inputId}', '${suggestionsId}', '${escapeHtml(item)}')">${escapeHtml(item)}</div>`
            ).join('');
            suggestionsDiv.classList.remove('hidden');
        } else {
            suggestionsDiv.classList.add('hidden');
        }
    });
    
    input.addEventListener('blur', function() {
        setTimeout(() => {
            suggestionsDiv.classList.add('hidden');
        }, 200);
    });
    
    input.addEventListener('focus', function() {
        if (this.value.length > 0) {
            const value = this.value.toLowerCase();
            const filtered = items.filter(item => 
                item.toLowerCase().includes(value) && item.toLowerCase() !== value
            );
            if (filtered.length > 0) {
                suggestionsDiv.innerHTML = filtered.slice(0, 10).map(item => 
                    `<div class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer" onclick="selectSuggestion('${inputId}', '${suggestionsId}', '${escapeHtml(item)}')">${escapeHtml(item)}</div>`
                ).join('');
                suggestionsDiv.classList.remove('hidden');
            }
        }
    });
}

function selectSuggestion(inputId, suggestionsId, value) {
    const input = document.getElementById(inputId);
    const suggestionsDiv = document.getElementById(suggestionsId);
    if (input && suggestionsDiv) {
        input.value = value;
        suggestionsDiv.classList.add('hidden');
        
        if (inputId === 'hersteller') {
            selectedManufacturer = value;
            loadModels(value).then(() => {
                const modellInput = document.getElementById('modell');
                if (modellInput && modellInput.value.length > 0) {
                    modellInput.dispatchEvent(new Event('input'));
                }
            });
        }
    }
}

// MAC-Adresse Formatierung
function setupMacAddressInputs() {
    const macInputs = ['mac_adresse_1', 'mac_adresse_2', 'mac_adresse_3', 'mac_adresse_4', 'mac_adresse_5', 'mac_adresse_6'];
    
    macInputs.forEach((inputId, index) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9A-Fa-f]/g, '').toUpperCase();
            
            if (this.value.length === 2 && index < macInputs.length - 1) {
                const nextInput = document.getElementById(macInputs[index + 1]);
                if (nextInput) nextInput.focus();
            }
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                const prevInput = document.getElementById(macInputs[index - 1]);
                if (prevInput) prevInput.focus();
            }
        });
        
        input.addEventListener('input', updateMacAddress);
    });
}

function updateMacAddress() {
    const macParts = [
        document.getElementById('mac_adresse_1')?.value || '',
        document.getElementById('mac_adresse_2')?.value || '',
        document.getElementById('mac_adresse_3')?.value || '',
        document.getElementById('mac_adresse_4')?.value || '',
        document.getElementById('mac_adresse_5')?.value || '',
        document.getElementById('mac_adresse_6')?.value || ''
    ];
    
    const macAddress = macParts.filter(part => part.length > 0).join(':');
    const hiddenInput = document.getElementById('mac_adresse');
    if (hiddenInput) {
        hiddenInput.value = macAddress || '';
    }
}

// IP-Adresse Formatierung
function setupIpAddressInputs() {
    const ipInputs = ['ip_adresse_1', 'ip_adresse_2', 'ip_adresse_3', 'ip_adresse_4'];
    
    ipInputs.forEach((inputId, index) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            if (parseInt(this.value) > 255) {
                this.value = '255';
            }
            
            if (this.value.length === 3 && index < ipInputs.length - 1) {
                const nextInput = document.getElementById(ipInputs[index + 1]);
                if (nextInput) nextInput.focus();
            }
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                const prevInput = document.getElementById(ipInputs[index - 1]);
                if (prevInput) prevInput.focus();
            }
        });
        
        input.addEventListener('input', updateIpAddress);
    });
}

function updateIpAddress() {
    const ipParts = [
        document.getElementById('ip_adresse_1')?.value || '',
        document.getElementById('ip_adresse_2')?.value || '',
        document.getElementById('ip_adresse_3')?.value || '',
        document.getElementById('ip_adresse_4')?.value || ''
    ];
    
    const ipAddress = ipParts.filter(part => part.length > 0).join('.');
    const hiddenInput = document.getElementById('ip_adresse');
    if (hiddenInput) {
        hiddenInput.value = ipAddress || '';
    }
}

// MAC/IP aus String in Felder aufteilen
function populateMacAddress(macAddress) {
    if (!macAddress) return;
    const parts = macAddress.split(/[:.-]/);
    const macInputs = ['mac_adresse_1', 'mac_adresse_2', 'mac_adresse_3', 'mac_adresse_4', 'mac_adresse_5', 'mac_adresse_6'];
    parts.forEach((part, index) => {
        if (index < macInputs.length) {
            const input = document.getElementById(macInputs[index]);
            if (input) {
                input.value = part.replace(/[^0-9A-Fa-f]/g, '').toUpperCase().substring(0, 2);
            }
        }
    });
    updateMacAddress();
}

function populateIpAddress(ipAddress) {
    if (!ipAddress) return;
    const parts = ipAddress.split('.');
    const ipInputs = ['ip_adresse_1', 'ip_adresse_2', 'ip_adresse_3', 'ip_adresse_4'];
    parts.forEach((part, index) => {
        if (index < ipInputs.length) {
            const input = document.getElementById(ipInputs[index]);
            if (input) {
                input.value = part.replace(/[^0-9]/g, '').substring(0, 3);
            }
        }
    });
    updateIpAddress();
}

// Status-Card-Toggle
function toggleStatusCard(card, status) {
    // Alle Cards zurücksetzen
    document.querySelectorAll('.status-card').forEach(c => {
        c.classList.remove('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900/20');
        c.classList.add('border-gray-300', 'dark:border-gray-600');
    });
    
    // Ausgewählte Card markieren
    card.classList.remove('border-gray-300', 'dark:border-gray-600');
    card.classList.add('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900/20');
    
    // Radio-Button setzen
    const radio = card.querySelector('.status-radio');
    radio.checked = true;
}

// Detailfelder ein-/ausklappen
function toggleDetailFields() {
    const container = document.getElementById('detailFieldsContainer');
    const icon = document.getElementById('detailFieldsIcon');
    if (container && icon) {
        container.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }
}

// Detailfelder aktualisieren
const deviceTypeFields = {
    'drucker': [
        { key: 'farbzaehler', label: 'Farbzähler', type: 'number' },
        { key: 'sw_zaehler', label: 'SW-Zähler', type: 'number' },
        { key: 'papierformat', label: 'Papierformat', type: 'text' },
        { key: 'druckgeschwindigkeit', label: 'Druckgeschwindigkeit (Seiten/Min)', type: 'number' }
    ],
    'computer': [
        { key: 'cpu', label: 'CPU', type: 'text' },
        { key: 'ram', label: 'RAM (GB)', type: 'number' },
        { key: 'festplatte', label: 'Festplatte (GB)', type: 'number' },
        { key: 'grafikkarte', label: 'Grafikkarte', type: 'text' }
    ],
    'netzwerk': [
        { key: 'ports', label: 'Anzahl Ports', type: 'number' },
        { key: 'port_typ', label: 'Port-Typ (z.B. Gigabit, 10G)', type: 'text' },
        { key: 'poe', label: 'PoE (Power over Ethernet)', type: 'select', options: ['ja', 'nein'] },
        { key: 'wlan', label: 'WLAN', type: 'select', options: ['ja', 'nein'] }
    ],
    'smartphone': [
        { key: 'prozessor', label: 'Prozessor', type: 'text' },
        { key: 'ram', label: 'RAM (GB)', type: 'number' },
        { key: 'speicher', label: 'Interner Speicher (GB)', type: 'number' },
        { key: 'bildschirmgroesse', label: 'Bildschirmgröße (Zoll)', type: 'number', step: '0.1' }
    ],
    'monitor': [
        { key: 'groesse', label: 'Größe (Zoll)', type: 'number' },
        { key: 'aufloesung', label: 'Auflösung', type: 'text', placeholder: 'z.B. 1920x1080' },
        { key: 'anschluss', label: 'Anschlüsse', type: 'text', placeholder: 'z.B. HDMI, DisplayPort' },
        { key: 'panel_typ', label: 'Panel-Typ', type: 'select', options: ['IPS', 'VA', 'TN', 'OLED'] }
    ],
    'divers': [
        { key: 'spezifikation1', label: 'Spezifikation 1', type: 'text' },
        { key: 'spezifikation2', label: 'Spezifikation 2', type: 'text' },
        { key: 'spezifikation3', label: 'Spezifikation 3', type: 'text' },
        { key: 'spezifikation4', label: 'Spezifikation 4', type: 'text' }
    ]
};

function updateDetailFields(deviceType, existingDetails = {}) {
    const container = document.getElementById('detailFieldsContainer');
    const fieldsDiv = document.getElementById('detailFields');
    
    if (!container || !fieldsDiv) return;
    
    if (!deviceType || !deviceTypeFields[deviceType]) {
        container.classList.add('hidden');
        fieldsDiv.innerHTML = '';
        return;
    }
    
    container.classList.remove('hidden');
    fieldsDiv.innerHTML = deviceTypeFields[deviceType].map(field => {
        const value = existingDetails[field.key] || '';
        
        if (field.type === 'select') {
            return `
                <div>
                    <label for="detail_${field.key}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        ${field.label}
                    </label>
                    <select id="detail_${field.key}" name="detail_${field.key}"
                            class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                        <option value="">-- Bitte wählen --</option>
                        ${field.options.map(opt => `<option value="${opt}" ${value === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                    </select>
                </div>
            `;
        } else {
            return `
                <div>
                    <label for="detail_${field.key}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        ${field.label}
                    </label>
                    <input type="${field.type}" id="detail_${field.key}" name="detail_${field.key}" value="${escapeHtml(value)}"
                           ${field.placeholder ? `placeholder="${field.placeholder}"` : ''}
                           ${field.step ? `step="${field.step}"` : ''}
                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                </div>
            `;
        }
    }).join('');
}

// Kunden-Tabelle Funktionen
function loadCustomers() {
    // Firmenauswahl prüfen und Kunden laden
    checkCompanySelection();
}

function renderCustomerTable(customers, selectedCustomerId = null) {
    const tbody = document.getElementById('customerTableBody');
    if (!tbody) return;
    
    if (!selectedCustomerId && currentDevice) {
        selectedCustomerId = currentDevice.customer_id;
    }
    
    tbody.innerHTML = customers.map(customer => {
        const searchText = `${customer.name} ${customer.email || ''} ${customer.company_name || ''}`.toLowerCase();
        const isSelected = selectedCustomerId && customer.id == selectedCustomerId;
        return `
            <tr class="customer-row cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700 ${isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : ''}" 
                data-search="${searchText}"
                data-customer-id="${customer.id}"
                onclick="toggleCustomerRow(this)">
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    <input type="radio" name="customer_id" value="${customer.id}" 
                           class="customer-radio hidden" ${isSelected ? 'checked' : ''}>
                    ${escapeHtml(customer.name)}
                </td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                    ${customer.email ? escapeHtml(customer.email) : '-'}
                </td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                    ${customer.company_name ? escapeHtml(customer.company_name) : '<span class="text-gray-400">[Ohne Firma]</span>'}
                </td>
            </tr>
        `;
    }).join('');
}

function toggleCustomerRow(row) {
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.customer-row').forEach(r => {
        r.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Ausgewählte Zeile markieren
    row.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
    
    // Radio-Button setzen
    const radio = row.querySelector('.customer-radio');
    radio.checked = true;
    
    // Benutzer-Auswahl anzeigen
    const userContainer = document.getElementById('userSelectContainer');
    
    // Firma ermitteln (aus Nav oder Dropdown)
    const savedSelection = localStorage.getItem('selectedUserOption');
    let companyId = null;
    
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0' && data.type !== 'all' && data.name !== 'Alle Kunden') {
                companyId = parseInt(data.id);
            }
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }
    
    if (!companyId) {
        const companySelect = document.getElementById('companySelect');
        if (companySelect && companySelect.value) {
            companyId = parseInt(companySelect.value);
        } else if (currentDevice && currentDevice.company_id) {
            companyId = currentDevice.company_id;
        }
    }
    
    if (companyId && userContainer) {
        userContainer.classList.remove('hidden');
        loadUsersForCompany(companyId);
    } else {
        if (userContainer) {
            userContainer.classList.add('hidden');
        }
    }
}

function deselectCustomer() {
    document.querySelectorAll('.customer-row').forEach(r => {
        r.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    document.querySelectorAll('.customer-radio').forEach(r => {
        r.checked = false;
    });
    
    // Benutzer-Auswahl verstecken
    const userContainer = document.getElementById('userSelectContainer');
    if (userContainer) {
        userContainer.classList.add('hidden');
    }
    deselectUser();
}

// Benutzer-Funktionen
function loadUsersForCompany(companyId) {
    if (!companyId) {
        allUsersList = [];
        filteredUsers = [];
        renderUserTable([]);
        return;
    }
    
    // Benutzer über API laden
    fetch(devicesApiUrl + '?action=get_users&company_id=' + companyId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users) {
                allUsers = data.users;
                allUsersList = data.users;
                filteredUsers = [...allUsersList];
                renderUserTable(filteredUsers, currentDevice ? currentDevice.user_id : null);
            } else {
                // Fallback: Benutzer aus PHP-Variable verwenden
                if (allUsers && allUsers.length > 0) {
                    allUsersList = allUsers;
                    filteredUsers = [...allUsersList];
                    renderUserTable(filteredUsers, currentDevice ? currentDevice.user_id : null);
                } else {
                    allUsersList = [];
                    filteredUsers = [];
                    renderUserTable([]);
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Benutzer:', error);
            // Fallback: Benutzer aus PHP-Variable verwenden
            if (allUsers && allUsers.length > 0) {
                allUsersList = allUsers;
                filteredUsers = [...allUsersList];
                renderUserTable(filteredUsers, currentDevice ? currentDevice.user_id : null);
            } else {
                allUsersList = [];
                filteredUsers = [];
                renderUserTable([]);
            }
        });
}

function renderUserTable(users, selectedUserId = null) {
    const tbody = document.getElementById('userTableBody');
    if (!tbody) return;
    
    if (!selectedUserId && currentDevice) {
        selectedUserId = currentDevice.user_id;
    }
    
    tbody.innerHTML = users.map(user => {
        const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim();
        const searchText = `${fullName} ${user.email || ''}`.toLowerCase();
        const isSelected = selectedUserId && user.id == selectedUserId;
        return `
            <tr class="user-row cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700 ${isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : ''}" 
                data-search="${searchText}"
                data-user-id="${user.id}"
                onclick="toggleUserRow(this)">
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    <input type="radio" name="user_id" value="${user.id}" 
                           class="user-radio hidden" ${isSelected ? 'checked' : ''}>
                    ${escapeHtml(fullName)}
                </td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                    ${user.email ? escapeHtml(user.email) : '-'}
                </td>
            </tr>
        `;
    }).join('');
}

function toggleUserRow(row) {
    // Alle Zeilen zurücksetzen
    document.querySelectorAll('.user-row').forEach(r => {
        r.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    
    // Ausgewählte Zeile markieren
    row.classList.add('bg-primary-50', 'dark:bg-primary-900/20');
    
    // Radio-Button setzen
    const radio = row.querySelector('.user-radio');
    radio.checked = true;
}

function deselectUser() {
    document.querySelectorAll('.user-row').forEach(r => {
        r.classList.remove('bg-primary-50', 'dark:bg-primary-900/20');
    });
    document.querySelectorAll('.user-radio').forEach(r => {
        r.checked = false;
    });
}

function sortUserTable(column) {
    if (userSortColumn === column) {
        userSortDirection = userSortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        userSortColumn = column;
        userSortDirection = 'asc';
    }
    
    // Sortierungs-Indikatoren zurücksetzen
    document.querySelectorAll('[id^="sort-user-"]').forEach(ind => {
        ind.textContent = '';
    });
    
    // Sortierungs-Indikator setzen
    const indicator = document.getElementById(`sort-user-${column}-indicator`);
    if (indicator) {
        indicator.textContent = userSortDirection === 'asc' ? '↑' : '↓';
    }
    
    // Sortieren
    filteredUsers.sort((a, b) => {
        let aVal, bVal;
        
        if (column === 'name') {
            aVal = `${a.vorname || ''} ${a.nachname || ''}`.trim().toLowerCase();
            bVal = `${b.vorname || ''} ${b.nachname || ''}`.trim().toLowerCase();
        } else if (column === 'email') {
            aVal = (a.email || '').toLowerCase();
            bVal = (b.email || '').toLowerCase();
        } else {
            aVal = a[column] || '';
            bVal = b[column] || '';
        }
        
        if (userSortDirection === 'asc') {
            return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
        } else {
            return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
        }
    });
    
    renderUserTable(filteredUsers, currentDevice ? currentDevice.user_id : null);
}

// Funktion zum Prüfen und Anzeigen/Verstecken von Formular-Elementen basierend auf Firmenauswahl
function checkCompanySelection() {
    // Prüfen ob Firma in Navigation ausgewählt ist
    const savedSelection = localStorage.getItem('selectedUserOption');
    let navCompanyId = null;
    
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0' && data.type !== 'all' && data.name !== 'Alle Kunden') {
                navCompanyId = parseInt(data.id);
            }
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }
    
    const companySelect = document.getElementById('companySelect');
    const companySelectContainer = document.getElementById('companySelectContainer');
    const selectedCompanyId = navCompanyId || (companySelect ? parseInt(companySelect.value) : null);
    
    const customerContainer = document.getElementById('customerSelectContainer');
    
    // Firmenauswahl verstecken, wenn in Nav eine Firma ausgewählt ist
    if (navCompanyId && companySelectContainer) {
        companySelectContainer.classList.add('hidden');
        if (companySelect) {
            companySelect.value = navCompanyId;
        }
    } else if (companySelectContainer) {
        companySelectContainer.classList.remove('hidden');
    }
    
    // Wenn keine Firma ausgewählt ist
    if (!selectedCompanyId) {
        if (customerContainer) {
            customerContainer.classList.add('hidden');
        }
        return false;
    } else {
        // Kunden für die ausgewählte Firma laden
        loadCustomersForCompany(selectedCompanyId);
        // Benutzer für die ausgewählte Firma laden
        loadUsersForCompany(selectedCompanyId);
        if (customerContainer) {
            customerContainer.classList.remove('hidden');
        }
        // Benutzer-Auswahl anzeigen, wenn Firma ausgewählt ist
        const userContainer = document.getElementById('userSelectContainer');
        if (userContainer && currentDevice && currentDevice.customer_id) {
            userContainer.classList.remove('hidden');
        }
        return true;
    }
}

// Kunden für eine bestimmte Firma laden
function loadCustomersForCompany(companyId) {
    let url = customersApiUrl;
    if (companyId && (userRole === 'Admin' || userRole === 'Techniker')) {
        url += '?company_id=' + companyId;
    } else if (companyId) {
        url += '?company_id=' + companyId;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customers) {
                allCustomers = data.customers;
                filteredCustomers = [...allCustomers];
                renderCustomerTable(filteredCustomers, currentDevice ? currentDevice.customer_id : null);
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kunden:', error);
        });
}

function sortCustomerTable(column) {
    if (customerSortColumn === column) {
        customerSortDirection = customerSortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        customerSortColumn = column;
        customerSortDirection = 'asc';
    }
    
    // Sortierungs-Indikatoren zurücksetzen
    document.querySelectorAll('[id^="sort-customer-"]').forEach(ind => {
        ind.textContent = '';
    });
    
    // Sortierungs-Indikator setzen
    const indicator = document.getElementById(`sort-customer-${column}-indicator`);
    if (indicator) {
        indicator.textContent = customerSortDirection === 'asc' ? '↑' : '↓';
    }
    
    // Sortieren
    filteredCustomers.sort((a, b) => {
        let aVal = a[column] || '';
        let bVal = b[column] || '';
        
        if (column === 'company') {
            aVal = a.company_name || '';
            bVal = b.company_name || '';
        }
        
        if (typeof aVal === 'string') {
            aVal = aVal.toLowerCase();
            bVal = bVal.toLowerCase();
        }
        
        if (customerSortDirection === 'asc') {
            return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
        } else {
            return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
        }
    });
    
    renderCustomerTable(filteredCustomers, currentDevice ? currentDevice.customer_id : null);
}

let currentDevice = null;

document.addEventListener('DOMContentLoaded', function() {
    loadDevice();
});

function loadDevice() {
    fetch(devicesApiUrl + '?id=' + deviceId)
        .then(response => {
            if (response.status === 403) {
                // Keine Berechtigung
                document.getElementById('deviceContent').innerHTML = 
                    '<div class="p-6 text-red-500">Sie haben keine Berechtigung, dieses Gerät zu bearbeiten.</div>';
                return null;
            }
            return response.json();
        })
        .then(data => {
            if (!data) return; // Bereits behandelt (403)
            if (data.success && data.device) {
                currentDevice = data.device;
                displayDeviceForm(data.device);
                // Kunden werden in checkCompanySelection geladen
            } else {
                document.getElementById('deviceContent').innerHTML = 
                    '<div class="p-6 text-red-500">Fehler beim Laden der Gerätedaten: ' + (data.error || 'Unbekannter Fehler') + '</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            document.getElementById('deviceContent').innerHTML = 
                '<div class="p-6 text-red-500">Fehler beim Laden der Gerätedaten</div>';
        });
}

function displayDeviceForm(device) {
    const canEditCompany = userRole === 'Admin' || userRole === 'Techniker';
    const canEditCustomer = userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin';
    const canEditUser = userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin';
    
    // Details parsen
    let deviceDetails = {};
    if (device.details) {
        try {
            deviceDetails = typeof device.details === 'string' ? JSON.parse(device.details) : device.details;
        } catch (e) {
            console.error('Fehler beim Parsen der Details:', e);
        }
    }
    
    document.getElementById('deviceContent').innerHTML = `
        <form id="deviceForm" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
            <!-- Gerätename und Beschreibung -->
            <div class="mb-6 grid grid-cols-5 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gerätename *</label>
                    <input type="text" id="name" name="name" required value="${escapeHtml(device.name)}"
                           placeholder="z.B. PC-001"
                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                </div>
                <div class="col-span-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Standort / Notiz</label>
                    <textarea id="beschreibung" name="beschreibung" rows="1"
                              class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white resize-none"
                              style="min-height: 42px; height: 42px;">${escapeHtml(device.beschreibung || '')}</textarea>
                </div>
            </div>

            <!-- Gerätetyp (nur anzeigen, nicht editierbar) -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gerätetyp</label>
                <div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600">
                    ${device.typ ? getTypeDisplay(device.typ) : '<span class="text-sm text-gray-500 dark:text-gray-400">Kein Typ zugewiesen</span>'}
                </div>
                <input type="hidden" name="typ" value="${escapeHtml(device.typ || '')}">
            </div>

            <!-- Status-Auswahl in Card-Form -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status *</label>
                <div class="grid grid-cols-4 gap-4">
                    <div class="status-card cursor-pointer transition-all border-2 ${device.status === 'aktiv' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600'} rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                         data-status="aktiv"
                         onclick="toggleStatusCard(this, 'aktiv')">
                        <input type="radio" name="status" value="aktiv" class="status-radio hidden" required ${device.status === 'aktiv' ? 'checked' : ''}>
                        <div class="flex flex-col items-center text-center">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-medium text-gray-900 dark:text-white">Aktiv</span>
                        </div>
                    </div>
                    <div class="status-card cursor-pointer transition-all border-2 ${device.status === 'inaktiv' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600'} rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                         data-status="inaktiv"
                         onclick="toggleStatusCard(this, 'inaktiv')">
                        <input type="radio" name="status" value="inaktiv" class="status-radio hidden" required ${device.status === 'inaktiv' ? 'checked' : ''}>
                        <div class="flex flex-col items-center text-center">
                            <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-medium text-gray-900 dark:text-white">Inaktiv</span>
                        </div>
                    </div>
                    <div class="status-card cursor-pointer transition-all border-2 ${device.status === 'wartung' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600'} rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                         data-status="wartung"
                         onclick="toggleStatusCard(this, 'wartung')">
                        <input type="radio" name="status" value="wartung" class="status-radio hidden" required ${device.status === 'wartung' ? 'checked' : ''}>
                        <div class="flex flex-col items-center text-center">
                            <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="font-medium text-gray-900 dark:text-white">Wartung</span>
                        </div>
                    </div>
                    <div class="status-card cursor-pointer transition-all border-2 ${device.status === 'ausgemustert' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600'} rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                         data-status="ausgemustert"
                         onclick="toggleStatusCard(this, 'ausgemustert')">
                        <input type="radio" name="status" value="ausgemustert" class="status-radio hidden" required ${device.status === 'ausgemustert' ? 'checked' : ''}>
                        <div class="flex flex-col items-center text-center">
                            <svg class="w-8 h-8 text-red-600 dark:text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span class="font-medium text-gray-900 dark:text-white">Ausgemustert</span>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Wählen Sie einen Status aus.
                </p>
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
                            <div class="relative col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hersteller</label>
                                <input type="text" id="hersteller" name="hersteller" autocomplete="off" value="${escapeHtml(device.hersteller || '')}"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <div id="hersteller-suggestions" class="hidden absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto"></div>
                            </div>
                            <div class="relative col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Modell</label>
                                <input type="text" id="modell" name="modell" autocomplete="off" value="${escapeHtml(device.modell || '')}"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <div id="modell-suggestions" class="hidden absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto"></div>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Seriennummer</label>
                                <input type="text" id="seriennummer" name="seriennummer" value="${escapeHtml(device.seriennummer || '')}"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Betriebssystem</label>
                            <input type="text" id="betriebssystem" name="betriebssystem" value="${escapeHtml(device.betriebssystem || '')}"
                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
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
                            <div class="flex items-center gap-1">
                                <input type="text" id="mac_adresse_1" name="mac_adresse_1" maxlength="2" pattern="[0-9A-Fa-f]{2}"
                                       class="w-12 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center uppercase"
                                       placeholder="__">
                                <span class="text-gray-500 dark:text-gray-400">.</span>
                                <input type="text" id="mac_adresse_2" name="mac_adresse_2" maxlength="2" pattern="[0-9A-Fa-f]{2}"
                                       class="w-12 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center uppercase"
                                       placeholder="__">
                                <span class="text-gray-500 dark:text-gray-400">.</span>
                                <input type="text" id="mac_adresse_3" name="mac_adresse_3" maxlength="2" pattern="[0-9A-Fa-f]{2}"
                                       class="w-12 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center uppercase"
                                       placeholder="__">
                                <span class="text-gray-500 dark:text-gray-400">.</span>
                                <input type="text" id="mac_adresse_4" name="mac_adresse_4" maxlength="2" pattern="[0-9A-Fa-f]{2}"
                                       class="w-12 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center uppercase"
                                       placeholder="__">
                                <span class="text-gray-500 dark:text-gray-400">.</span>
                                <input type="text" id="mac_adresse_5" name="mac_adresse_5" maxlength="2" pattern="[0-9A-Fa-f]{2}"
                                       class="w-12 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center uppercase"
                                       placeholder="__">
                                <span class="text-gray-500 dark:text-gray-400">.</span>
                                <input type="text" id="mac_adresse_6" name="mac_adresse_6" maxlength="2" pattern="[0-9A-Fa-f]{2}"
                                       class="w-12 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center uppercase"
                                       placeholder="__">
                            </div>
                            <input type="hidden" id="mac_adresse" name="mac_adresse">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">IP-Adresse</label>
                            <div class="flex items-center gap-1">
                                <input type="text" id="ip_adresse_1" name="ip_adresse_1" maxlength="3" pattern="[0-9]{1,3}"
                                       class="w-16 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center"
                                       placeholder="___">
                                <span class="text-gray-500 dark:text-gray-400">.</span>
                                <input type="text" id="ip_adresse_2" name="ip_adresse_2" maxlength="3" pattern="[0-9]{1,3}"
                                       class="w-16 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center"
                                       placeholder="___">
                                <span class="text-gray-500 dark:text-gray-400">.</span>
                                <input type="text" id="ip_adresse_3" name="ip_adresse_3" maxlength="3" pattern="[0-9]{1,3}"
                                       class="w-16 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center"
                                       placeholder="___">
                                <span class="text-gray-500 dark:text-gray-400">.</span>
                                <input type="text" id="ip_adresse_4" name="ip_adresse_4" maxlength="3" pattern="[0-9]{1,3}"
                                       class="w-16 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white text-center"
                                       placeholder="___">
                            </div>
                            <input type="hidden" id="ip_adresse" name="ip_adresse">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spezifische Details in einklappbarer Reihe -->
            <div class="mb-6">
                <div class="border border-gray-300 dark:border-gray-600 rounded-lg">
                    <button type="button" id="detailFieldsToggle" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" onclick="toggleDetailFields()">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Gerätespezifische Details</span>
                        <svg id="detailFieldsIcon" class="w-5 h-5 text-gray-500 dark:text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="detailFieldsContainer" class="${device.typ && deviceTypeFields[device.typ] ? '' : 'hidden'} border-t border-gray-300 dark:border-gray-600 p-4">
                        <div id="detailFields" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Wird dynamisch gefüllt -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Firmen-Auswahl -->
            ${canEditCompany ? `
            <div class="mb-6" id="companySelectContainer">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Firma *</label>
                <select id="companySelect" name="company_id" required
                        class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">-- Bitte wählen Sie eine Firma --</option>
                    ${allCompanies.map(company => `
                        <option value="${company.id}" ${device.company_id == company.id ? 'selected' : ''}>
                            ${escapeHtml(company.name)}
                        </option>
                    `).join('')}
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Wählen Sie eine Firma aus, für die das Gerät erstellt werden soll.
                </p>
            </div>
            ` : ''}

            ${canEditCustomer ? `
            <!-- Kunden-Auswahl in Tabelle -->
            <div class="mb-6 hidden" id="customerSelectContainer">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kunde</label>
                
                <!-- Suchfeld und Abwählen-Button -->
                <div class="mb-3 flex justify-between items-center gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg aria-hidden="true" class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input type="text" id="customerSearch" placeholder="Kunden suchen..." 
                                   class="block w-full pl-10 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                        </div>
                    </div>
                    <button type="button" onclick="deselectCustomer()" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600">
                        Abwählen
                    </button>
                </div>
                
                <!-- Kunden-Tabelle -->
                <div class="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto max-h-96">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 select-none" onclick="sortCustomerTable('name')">
                                        Name <span id="sort-customer-name-indicator" class="text-gray-400"></span>
                                    </th>
                                    <th scope="col" class="px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 select-none" onclick="sortCustomerTable('email')">
                                        E-Mail <span id="sort-customer-email-indicator" class="text-gray-400"></span>
                                    </th>
                                    <th scope="col" class="px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 select-none" onclick="sortCustomerTable('company')">
                                        Firma <span id="sort-customer-company-indicator" class="text-gray-400"></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="customerTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Wird dynamisch gefüllt -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Wählen Sie optional einen Kunden aus, dem das Gerät zugeordnet werden soll.
                </p>
            </div>
            ` : ''}

            <!-- Benutzer-Auswahl (erscheint nur wenn Kunde ausgewählt) -->
            ${canEditUser ? `
            <div class="mb-6 hidden" id="userSelectContainer">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Benutzer</label>
                
                <!-- Suchfeld und Abwählen-Button -->
                <div class="mb-3 flex justify-between items-center gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg aria-hidden="true" class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input type="text" id="userSearch" placeholder="Benutzer suchen..." 
                                   class="block w-full pl-10 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                        </div>
                    </div>
                    <button type="button" onclick="deselectUser()" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600">
                        Abwählen
                    </button>
                </div>
                
                <!-- Benutzer-Tabelle -->
                <div class="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto max-h-96">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 select-none" onclick="sortUserTable('name')">
                                        Name <span id="sort-user-name-indicator" class="text-gray-400"></span>
                                    </th>
                                    <th scope="col" class="px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 select-none" onclick="sortUserTable('email')">
                                        E-Mail <span id="sort-user-email-indicator" class="text-gray-400"></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Wird dynamisch gefüllt -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Wählen Sie optional einen Benutzer aus, dem das Gerät zugeordnet werden soll.
                </p>
            </div>
            ` : ''}

        </form>
    `;
    
    // Detailfelder initial laden
    if (device.typ) {
        updateDetailFields(device.typ, deviceDetails);
    }
    
    // MAC- und IP-Adressen aus String in Felder aufteilen
    setTimeout(() => {
        if (device.mac_adresse) {
            populateMacAddress(device.mac_adresse);
        }
        if (device.ip_adresse) {
            populateIpAddress(device.ip_adresse);
        }
        
        // MAC- und IP-Adressen Formatierung initialisieren
        setupMacAddressInputs();
        setupIpAddressInputs();
        
        // Autocomplete initialisieren
        loadManufacturers().then(() => {
            setupAutocomplete('hersteller', 'hersteller-suggestions', manufacturers, function(value) {
                selectedManufacturer = value;
                loadModels(value);
            });
        });
        
        loadModels().then(() => {
            setupAutocomplete('modell', 'modell-suggestions', models);
        });
        
        // Firmenauswahl-Logik
        checkCompanySelection();
        
        // Event-Listener für Firmenauswahl im Formular
        const companySelect = document.getElementById('companySelect');
        if (companySelect) {
            companySelect.addEventListener('change', function() {
                checkCompanySelection();
            });
        }
        
        // Kunden-Suche Event Listener
        const customerSearch = document.getElementById('customerSearch');
        if (customerSearch) {
            customerSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filteredCustomers = allCustomers.filter(customer => {
                    const searchText = `${customer.name} ${customer.email || ''} ${customer.company_name || ''}`.toLowerCase();
                    return searchText.includes(searchTerm);
                });
                renderCustomerTable(filteredCustomers, currentDevice ? currentDevice.customer_id : null);
            });
        }
        
        // Benutzer-Suche
        const userSearch = document.getElementById('userSearch');
        if (userSearch) {
            userSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filteredUsers = allUsersList.filter(user => {
                    const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim();
                    const searchText = `${fullName} ${user.email || ''}`.toLowerCase();
                    return searchText.includes(searchTerm);
                });
                renderUserTable(filteredUsers, currentDevice ? currentDevice.user_id : null);
            });
        }
        
        // Benutzer laden wenn Kunde bereits ausgewählt ist
        if (device.customer_id) {
            const savedSelection = localStorage.getItem('selectedUserOption');
            let companyId = null;
            
            if (savedSelection) {
                try {
                    const data = JSON.parse(savedSelection);
                    if (data.id && data.id !== '0' && data.type !== 'all' && data.name !== 'Alle Kunden') {
                        companyId = parseInt(data.id);
                    }
                } catch (e) {
                    console.error('Fehler beim Laden der Firmenauswahl', e);
                }
            }
            
            if (!companyId) {
                const companySelect = document.getElementById('companySelect');
                if (companySelect && companySelect.value) {
                    companyId = parseInt(companySelect.value);
                } else if (device.company_id) {
                    companyId = device.company_id;
                }
            }
            
            if (companyId) {
                const userContainer = document.getElementById('userSelectContainer');
                if (userContainer) {
                    userContainer.classList.remove('hidden');
                    loadUsersForCompany(companyId);
                }
            }
        }
        
        // Formular-Submit Event Listener
        const deviceForm = document.getElementById('deviceForm');
        if (deviceForm) {
            deviceForm.addEventListener('submit', function(e) {
                e.preventDefault();
                saveDevice();
            });
            // Nav-Unsaved-Changes (modular): Banner bei Änderung, Speichern/Verwerfen in der Nav
            if (window.NavUnsavedChanges) {
                NavUnsavedChanges.init({
                    form: 'deviceForm',
                    discardUrl: editBaseUrl + 'devices/',
                    onSave: saveDevice
                });
            }
        }
    }, 100);
}

function saveDevice() {
    // Typ aus hidden input nehmen (nicht editierbar)
    const deviceType = document.querySelector('input[name="typ"]')?.value || currentDevice?.typ;
    const details = {};
    
    // Sammle alle Detailfelder
    if (deviceType && deviceTypeFields[deviceType]) {
        deviceTypeFields[deviceType].forEach(field => {
            const input = document.getElementById(`detail_${field.key}`);
            if (input && input.value) {
                if (field.type === 'number') {
                    details[field.key] = parseFloat(input.value) || null;
                } else {
                    details[field.key] = input.value.trim();
                }
            }
        });
    }
    
    // MAC- und IP-Adressen nochmal aktualisieren vor dem Submit
    updateMacAddress();
    updateIpAddress();
    
    const formData = {
        device_id: deviceId,
        name: document.getElementById('name').value,
        typ: deviceType || null,
        hersteller: document.getElementById('hersteller').value.trim() || null,
        modell: document.getElementById('modell').value.trim() || null,
        seriennummer: document.getElementById('seriennummer').value.trim() || null,
        mac_adresse: document.getElementById('mac_adresse').value || null,
        ip_adresse: document.getElementById('ip_adresse').value || null,
        betriebssystem: document.getElementById('betriebssystem').value.trim() || null,
        beschreibung: document.getElementById('beschreibung').value.trim() || null,
        status: document.querySelector('input[name="status"]:checked')?.value || 'aktiv',
        details: Object.keys(details).length > 0 ? details : null
    };
    
    // Firma ermitteln (aus Nav oder Dropdown)
    const savedSelection = localStorage.getItem('selectedUserOption');
    let companyId = null;
    
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0' && data.type !== 'all' && data.name !== 'Alle Kunden') {
                companyId = parseInt(data.id);
            }
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }
    
    if (!companyId) {
        const companySelect = document.getElementById('companySelect');
        if (companySelect && companySelect.value) {
            companyId = parseInt(companySelect.value);
        }
    }
    
    if (companyId) {
        formData.company_id = companyId;
    }
    
    const customerId = document.querySelector('input[name="customer_id"]:checked')?.value;
    if (customerId) {
        formData.customer_id = parseInt(customerId);
    }
    
    const userId = document.querySelector('input[name="user_id"]:checked')?.value;
    if (userId) {
        formData.user_id = parseInt(userId);
    }
    
    fetch(devicesApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Gerät erfolgreich aktualisiert', 'success');
            }
            window.location.href = '<?php echo BASE_URL; ?>devices/';
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
            showToast('Fehler beim Speichern der Änderungen', 'error');
        } else {
            alert('Fehler beim Speichern der Änderungen');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getTypeDisplay(type) {
    const typeIcons = {
        'drucker': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />',
        'computer': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />',
        'netzwerk': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />',
        'smartphone': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />',
        'monitor': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />',
        'divers': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />'
    };
    const typeLabels = {
        'drucker': 'Drucker',
        'computer': 'Computer',
        'netzwerk': 'Netzwerkgerät',
        'smartphone': 'Smartphone',
        'monitor': 'Monitor',
        'divers': 'Divers'
    };
    const icon = typeIcons[type] || '';
    const label = typeLabels[type] || type;
    return '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + icon + '</svg><span class="text-sm font-medium text-gray-900 dark:text-white">' + escapeHtml(label) + '</span>';
}
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
