<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customerId) {
    header('Location: ' . BASE_URL . 'customers/');
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

// Nur Firmen-Admin, Admin und Techniker können Kunden-Details sehen
if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
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
                                    <a href="<?php echo BASE_URL; ?>customers/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 md:ms-2 dark:text-gray-400 dark:hover:text-white">Kunden</a>
                                </li>
                                <li aria-current="page">
                                    <div class="flex items-center">
                                        <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Kunden-Details</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img id="customerLogo" class="w-12 h-12 rounded-full object-cover" src="" alt="" style="display: none;">
                                <div>
                                    <h1 id="customerTitle" class="text-2xl font-bold text-gray-900 dark:text-white">Kunden-Details</h1>
                                    <p id="customerSubtitle" class="text-sm text-gray-600 dark:text-gray-400 mt-1 flex flex-wrap items-center gap-2"></p>
                                </div>
                            </div>
                            <div id="editButtonContainer"></div>
                        </div>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <div id="customerContent" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
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

<script>
const customerId = <?php echo $customerId; ?>;
const detailBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>';
const customersApiUrl = detailBaseUrl + 'customers/api/customers.php';
const documentsApiUrl = detailBaseUrl + 'customers/api/documents.php';
const contractsApiUrl = detailBaseUrl + 'customers/api/contracts.php';
const notesApiUrl = detailBaseUrl + 'customers/api/notes.php';
const companiesApiUrl = detailBaseUrl + 'companies/api/companies.php';
const logsApiUrl = detailBaseUrl + 'logs/api/logs.php';
const currentUserRole = '<?php echo $userRole; ?>';

let currentTab = 'overview';

function getStatusBadge(status) {
    const badges = {
        'aktiv': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aktiv</span>',
        'inaktiv': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Inaktiv</span>',
        'gesperrt': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Gesperrt</span>'
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

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Avatar-Renderer (wie in der Nav): unterstützt preset:{color}:{initials}
function renderUserAvatarHtml(logopfad, vorname = '', nachname = '', email = '', classes = 'h-8 w-8 rounded-full object-cover', alt = 'Avatar') {
    const v = (vorname || '').trim();
    const n = (nachname || '').trim();
    const e = (email || '').trim();
    const initials = (v && n) ? (v[0] + n[0]).toUpperCase() : (e ? e[0].toUpperCase() : 'U');

    if (logopfad && typeof logopfad === 'string' && logopfad.startsWith('preset:')) {
        const parts = logopfad.split(':');
        let color = parts[1] || '#3b82f6';
        if (!color.startsWith('#')) color = '#' + color;
        const presetInitials = (parts[2] || initials).toUpperCase();
        return `<div class="${escapeHtml(classes)} flex items-center justify-center text-white text-sm font-bold shrink-0" style="background-color: ${escapeHtml(color)};" title="${escapeHtml(alt)}">${escapeHtml(presetInitials)}</div>`;
    }

    const fallbackUrl = `${detailBaseUrl}assets/images/default-avatar.png`;
    const url = logopfad
        ? (logopfad.startsWith('http://') || logopfad.startsWith('https://')
            ? logopfad
            : (detailBaseUrl + logopfad.replace(/^\//, '')))
        : fallbackUrl;

    return `<img src="${escapeHtml(url)}" alt="${escapeHtml(alt)}" class="${escapeHtml(classes)} shrink-0" onerror="this.onerror=null; this.src='${fallbackUrl}';">`;
}

document.addEventListener('DOMContentLoaded', function() {
    loadCustomer();
});

function loadCustomer() {
    fetch(customersApiUrl + '?id=' + customerId)
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Ungültige Antwort von API:', text);
                    throw new Error('Server hat keine gültige JSON-Antwort zurückgegeben.');
                });
            }
            
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'HTTP error! status: ' + response.status);
                }).catch(() => {
                    throw new Error('HTTP error! status: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data) {
                throw new Error('Keine Daten erhalten');
            }
            if (data.success && data.customer) {
                displayCustomer(data.customer);
                // Overview-Tab standardmäßig anzeigen
                setTimeout(() => {
                    showTab('overview');
                }, 150);
            } else {
                const errorMsg = data.error || 'Unbekannter Fehler';
                document.getElementById('customerContent').innerHTML = 
                    '<div class="p-6 text-red-500">Fehler beim Laden der Kundendaten: ' + escapeHtml(errorMsg) + '</div>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kundendaten:', error);
            const errorMsg = error.message || 'Unbekannter Fehler';
            document.getElementById('customerContent').innerHTML = 
                '<div class="p-6 text-red-500">Fehler beim Laden der Kundendaten: ' + escapeHtml(errorMsg) + '</div>';
        });
}

function showTab(tabName) {
    currentTab = tabName;
    
    // Funktion zum Ausführen der Tab-Logik
    const executeTabSwitch = () => {
        // Alle Tab-Links zurücksetzen
        document.querySelectorAll('.tab-link').forEach(link => {
            link.classList.remove('border-primary-600', 'dark:border-primary-400', 'text-primary-600', 'dark:text-primary-400', 'active');
            link.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            const icon = link.querySelector('svg');
            if (icon) {
                icon.classList.remove('text-primary-600', 'dark:text-primary-400');
                icon.classList.add('text-gray-500', 'dark:text-gray-400');
            }
        });
        
        // Aktiven Tab-Link markieren
        const activeLink = document.getElementById(`tab-link-${tabName}`);
        if (activeLink) {
            activeLink.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            activeLink.classList.add('border-primary-600', 'dark:border-primary-400', 'text-primary-600', 'dark:text-primary-400', 'active');
            const icon = activeLink.querySelector('svg');
            if (icon) {
                icon.classList.remove('text-gray-500', 'dark:text-gray-400');
                icon.classList.add('text-primary-600', 'dark:text-primary-400');
            }
        }
        
        // Alle Tab-Contents verstecken
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = 'none';
        });
        
        // Aktiven Tab-Content anzeigen
        const activeContent = document.getElementById(`tab-${tabName}`);
        if (activeContent) {
            activeContent.style.display = 'block';
        } else {
            // Retry nach kurzer Verzögerung
            setTimeout(executeTabSwitch, 50);
            return;
        }
        
        // Tab-spezifische Daten laden
        if (tabName === 'users') {
            loadUsers();
        } else if (tabName === 'documents') {
            loadDocuments();
        } else if (tabName === 'contracts') {
            loadContracts();
        } else if (tabName === 'notes') {
            loadNotes();
        } else if (tabName === 'logs') {
            loadLogs();
        }
    };
    
    // Sofort versuchen, dann mit Retry
    executeTabSwitch();
}

function displayCustomer(customer) {
    const statusBadge = getStatusBadge(customer.status || 'aktiv');
    const logoUrl = customer.logo 
        ? (customer.logo.startsWith('http') ? customer.logo : detailBaseUrl + customer.logo)
        : detailBaseUrl + 'assets/images/default-avatar.png';
    
    const titleElement = document.getElementById('customerTitle');
    if (titleElement) {
        titleElement.textContent = escapeHtml(customer.name);
    }
    const logoElement = document.getElementById('customerLogo');
    if (logoElement) {
        logoElement.src = logoUrl;
        logoElement.alt = escapeHtml(customer.name);
        logoElement.style.display = 'block';
    }
    
    const subtitleElement = document.getElementById('customerSubtitle');
    if (subtitleElement) {
        const subtitleParts = [];
        
        const statusText = (customer.status || 'aktiv').charAt(0).toUpperCase() + (customer.status || 'aktiv').slice(1);
        subtitleParts.push(`
            <span class="inline-flex items-center gap-1">
                <span class="text-gray-500 dark:text-gray-400">Status:</span>
                <span class="font-semibold text-gray-900 dark:text-white">${escapeHtml(statusText)}</span>
            </span>
        `);
        
        if (customer.kundennummer) {
            subtitleParts.push(`
                <span class="inline-flex items-center gap-1">
                    <span class="text-gray-500 dark:text-gray-400">Kundennummer:</span>
                    <span class="font-semibold text-gray-900 dark:text-white">${escapeHtml(customer.kundennummer)}</span>
                </span>
            `);
        }
        
        if (customer.company_name) {
            subtitleParts.push(`
                <span class="inline-flex items-center gap-1">
                    <span class="text-gray-500 dark:text-gray-400">Firma:</span>
                    <span class="font-semibold text-gray-900 dark:text-white">${escapeHtml(customer.company_name)}</span>
                </span>
            `);
        }
        
        // Ansprechpartner
        if (customer.ansprechpartner_vorname || customer.ansprechpartner_nachname) {
            const ansprechpartnerName = escapeHtml((customer.ansprechpartner_vorname || '') + ' ' + (customer.ansprechpartner_nachname || ''));
            subtitleParts.push(`
                <span class="inline-flex items-center gap-1">
                    <span class="text-gray-500 dark:text-gray-400">Ansprechpartner:</span>
                    <span class="font-semibold text-gray-900 dark:text-white">${ansprechpartnerName}</span>
                </span>
            `);
        } else if (customer.ansprechpartner_manuell_name) {
            subtitleParts.push(`
                <span class="inline-flex items-center gap-1">
                    <span class="text-gray-500 dark:text-gray-400">Ansprechpartner:</span>
                    <span class="font-semibold text-gray-900 dark:text-white">${escapeHtml(customer.ansprechpartner_manuell_name)}</span>
                </span>
            `);
        }
        
        subtitleElement.innerHTML = subtitleParts.join('<span class="text-gray-400 dark:text-gray-600 mx-2">|</span>');
    }
    
    // Buttons hinzufügen
    const editButtonContainer = document.getElementById('editButtonContainer');
    // Techniker können Kunden nicht bearbeiten
    if (editButtonContainer && (currentUserRole === 'Admin' || currentUserRole === 'Firmen-Admin')) {
        const currentStatus = customer.status || 'aktiv';
        
        // Status-Button Klassen basierend auf aktuellem Status
        const getStatusButtonClass = (status) => {
            const baseClass = 'text-sm px-3 py-2 font-medium leading-5 focus:ring-3 focus:outline-none border border-gray-300 dark:border-gray-600';
            if (status === currentStatus) {
                // Aktiver Status - farbig
                if (status === 'aktiv') {
                    return baseClass + ' bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 border-green-300 dark:border-green-700';
                } else if (status === 'inaktiv') {
                    return baseClass + ' bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600';
                } else if (status === 'gesperrt') {
                    return baseClass + ' bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 border-red-300 dark:border-red-700';
                }
            }
            // Inaktiver Status - Standard
            return baseClass + ' text-gray-900 bg-white dark:bg-gray-800 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-primary-500 dark:focus:ring-primary-400';
        };
        
        editButtonContainer.innerHTML = `
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Status-Buttons -->
                <div class="inline-flex rounded-base shadow-xs -space-x-px" role="group">
                    <button type="button" onclick="changeCustomerStatus('aktiv')" 
                            class="${getStatusButtonClass('aktiv')} rounded-s-base focus:ring-green-500 dark:focus:ring-green-400" 
                            title="Status auf Aktiv setzen">
                        Aktiv
                    </button>
                    <button type="button" onclick="changeCustomerStatus('inaktiv')" 
                            class="${getStatusButtonClass('inaktiv')} focus:ring-gray-500 dark:focus:ring-gray-400" 
                            title="Status auf Inaktiv setzen">
                        Inaktiv
                    </button>
                    <button type="button" onclick="changeCustomerStatus('gesperrt')" 
                            class="${getStatusButtonClass('gesperrt')} rounded-e-base focus:ring-red-500 dark:focus:ring-red-400" 
                            title="Status auf Gesperrt setzen">
                        Gesperrt
                    </button>
                </div>
                
                <!-- Bearbeiten- und Löschen-Buttons -->
                <div class="inline-flex rounded-base shadow-xs -space-x-px" role="group">
                    <a href="${detailBaseUrl}customers/edit.php?id=${customer.id}" 
                       class="text-sm px-3 py-2 font-medium leading-5 text-gray-900 bg-white dark:bg-gray-800 dark:text-white border border-gray-300 dark:border-gray-600 rounded-s-base hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-3 focus:ring-primary-500 dark:focus:ring-primary-400 focus:outline-none inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Bearbeiten
                    </a>
                    <button type="button" onclick="deleteCustomer(${customer.id})" 
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
    
    let adresseText = '';
    if (customer.adresse || customer.plz || customer.ort) {
        const parts = [];
        if (customer.adresse) parts.push(customer.adresse);
        if (customer.plz || customer.ort) {
            parts.push((customer.plz || '') + (customer.plz && customer.ort ? ' ' : '') + (customer.ort || ''));
        }
        adresseText = parts.join(', ');
    }
    
    // Lieferadresse zusammenbauen
    let lieferadresseText = '';
    if (customer.lieferadresse || customer.liefer_plz || customer.liefer_ort) {
        const parts = [];
        if (customer.lieferadresse) parts.push(customer.lieferadresse);
        if (customer.liefer_plz || customer.liefer_ort) {
            parts.push((customer.liefer_plz || '') + (customer.liefer_plz && customer.liefer_ort ? ' ' : '') + (customer.liefer_ort || ''));
        }
        lieferadresseText = parts.join(', ');
    }
    
    // Rechnungsadresse zusammenbauen
    let rechnungsadresseText = '';
    if (customer.rechnungs_adresse || customer.rechnungs_plz || customer.rechnungs_ort) {
        const parts = [];
        if (customer.rechnungs_adresse) parts.push(customer.rechnungs_adresse);
        if (customer.rechnungs_plz || customer.rechnungs_ort) {
            parts.push((customer.rechnungs_plz || '') + (customer.rechnungs_plz && customer.rechnungs_ort ? ' ' : '') + (customer.rechnungs_ort || ''));
        }
        rechnungsadresseText = parts.join(', ');
    }
    
    // Falls keine Rechnungsadresse vorhanden, Standard-Adresse verwenden
    if (!rechnungsadresseText && adresseText) {
        rechnungsadresseText = adresseText;
    }
    
    const googleMapsUrl = adresseText ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(adresseText)}` : '#';
    const lieferadresseGoogleMapsUrl = lieferadresseText ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(lieferadresseText)}` : '#';
    const rechnungsadresseGoogleMapsUrl = rechnungsadresseText ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(rechnungsadresseText)}` : '#';
    const telefon = customer.telefon || '';
    const email = customer.email || '';
    const rechnungsEmail = customer.rechnungs_email || '';
    
        // Ansprechpartner-Name bestimmen
    let ansprechpartnerName = '';
    if (customer.ansprechpartner_vorname || customer.ansprechpartner_nachname) {
        ansprechpartnerName = (customer.ansprechpartner_vorname || '') + ' ' + (customer.ansprechpartner_nachname || '');
    } else if (customer.ansprechpartner_manuell_name) {
        ansprechpartnerName = customer.ansprechpartner_manuell_name;
    }
    
    document.getElementById('customerContent').innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <!-- Tabs Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                    <li class="me-2">
                        <a href="#" onclick="showTab('overview'); return false;" id="tab-link-overview" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400 rounded-t-base active group">
                            <svg class="w-4 h-4 me-2 text-primary-600 dark:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.143 4H4.857A.857.857 0 0 0 4 4.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 10 9.143V4.857A.857.857 0 0 0 9.143 4Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 20 9.143V4.857A.857.857 0 0 0 19.143 4Zm-10 10H4.857a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286A.857.857 0 0 0 9.143 14Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286a.857.857 0 0 0-.857-.857Z"/>
                            </svg>
                            Übersicht
                        </a>
                    </li>
                    <li class="me-2">
                        <a href="#" onclick="showTab('users'); return false;" id="tab-link-users" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.948 8.948 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            Zugänge/Kontakte
                        </a>
                    </li>
                    <li class="me-2">
                        <a href="#" onclick="showTab('documents'); return false;" id="tab-link-documents" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Dokumente
                        </a>
                    </li>
                    ${currentUserRole === 'Admin' ? `
                    <li class="me-2">
                        <a href="#" onclick="showTab('contracts'); return false;" id="tab-link-contracts" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 4v10m0 0a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m0 0v2m6-16v2m0 0a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m0 0v10m6-16v10m0 0a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m0 0v2"/>
                            </svg>
                            Rechnungen
                        </a>
                    </li>
                    ` : ''}
                    <li class="me-2">
                        <a href="#" onclick="showTab('notes'); return false;" id="tab-link-notes" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/>
                            </svg>
                            Notizen
                        </a>
                    </li>
                    <li class="me-2">
                        <a href="#" onclick="showTab('logs'); return false;" id="tab-link-logs" class="tab-link inline-flex items-center justify-center p-4 border-b-2 border-transparent rounded-t-base hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500 group">
                            <svg class="w-4 h-4 me-2 text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Aktivitätsprotokoll
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Tab Contents -->
            <div class="p-6">
                <!-- Übersicht Tab -->
                <div id="tab-overview" class="tab-content">
                    <!-- Kontaktinformationen und Schnellaktionen -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        ${email || telefon || rechnungsEmail || customer.ansprechpartner_manuell_name || (customer.ansprechpartner_vorname || customer.ansprechpartner_nachname) ? `
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                Kontaktinformationen
                            </h3>
                            <div class="space-y-3">
                                ${customer.ansprechpartner_manuell_name ? `
                                <div class="mb-4 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-purple-700 dark:text-purple-300">Ansprechpartner (manuell)</span>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Name:</span>
                                            <span class="ml-2 font-medium text-gray-900 dark:text-white">${escapeHtml(customer.ansprechpartner_manuell_name)}</span>
                                        </div>
                                        ${customer.ansprechpartner_manuell_email ? `
                                        <div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">E-Mail:</span>
                                            <a href="mailto:${escapeHtml(customer.ansprechpartner_manuell_email)}" class="ml-2 text-primary-600 dark:text-primary-400 hover:underline">${escapeHtml(customer.ansprechpartner_manuell_email)}</a>
                                        </div>
                                        ` : ''}
                                        ${customer.ansprechpartner_manuell_telefon ? `
                                        <div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Telefon:</span>
                                            <a href="tel:${escapeHtml(customer.ansprechpartner_manuell_telefon)}" class="ml-2 text-primary-600 dark:text-primary-400 hover:underline">${escapeHtml(customer.ansprechpartner_manuell_telefon)}</a>
                                        </div>
                                        ` : ''}
                                        ${customer.ansprechpartner_manuell_notiz ? `
                                        <div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Notiz:</span>
                                            <span class="ml-2 text-gray-700 dark:text-gray-300">${escapeHtml(customer.ansprechpartner_manuell_notiz)}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}
                                ${(customer.ansprechpartner_vorname || customer.ansprechpartner_nachname) ? `
                                <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">Ansprechpartner (User)</span>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Name:</span>
                                            <span class="ml-2 font-medium text-gray-900 dark:text-white">${escapeHtml((customer.ansprechpartner_vorname || '') + ' ' + (customer.ansprechpartner_nachname || ''))}</span>
                                        </div>
                                        ${customer.ansprechpartner_email ? `
                                        <div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">E-Mail:</span>
                                            <a href="mailto:${escapeHtml(customer.ansprechpartner_email)}" class="ml-2 text-primary-600 dark:text-primary-400 hover:underline">${escapeHtml(customer.ansprechpartner_email)}</a>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}
                                ${email ? `
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">E-Mail</p>
                                        <a href="mailto:${escapeHtml(email)}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">${escapeHtml(email)}</a>
                                    </div>
                                </div>
                                ` : ''}
                                ${telefon ? `
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Telefon</p>
                                        <a href="tel:${escapeHtml(telefon)}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">${escapeHtml(telefon)}</a>
                                    </div>
                                </div>
                                ` : ''}
                                ${rechnungsEmail ? `
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Rechnungs-E-Mail</p>
                                        <a href="mailto:${escapeHtml(rechnungsEmail)}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">${escapeHtml(rechnungsEmail)}</a>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        ` : '<div></div>'}
                        
                        <!-- Schnellaktionen -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <div class="p-2 bg-primary-100 dark:bg-primary-900 rounded-lg">
                                    <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                Schnellaktionen
                            </h3>
                            <div class="flex flex-col gap-2">
                                ${telefon ? `
                                <a href="tel:${escapeHtml(telefon)}" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    Anrufen
                                </a>
                                ` : ''}
                                ${email ? `
                                <a href="mailto:${escapeHtml(email)}" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    E-Mail senden
                                </a>
                                ` : ''}
                                ${currentUserRole === 'Admin' ? `
                                <button onclick="window.location.href='${detailBaseUrl}invoices/create.php?customer_id=${customer.id}'" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Rechnung schreiben
                                </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Adress-Cards -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        ${adresseText ? `
                        <a href="${googleMapsUrl}" target="_blank" rel="noopener noreferrer" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-all cursor-pointer group">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg group-hover:bg-blue-200 dark:group-hover:bg-blue-800 transition-colors">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Adresse</h3>
                            </div>
                            <p class="text-gray-900 dark:text-white text-sm">${escapeHtml(adresseText)}</p>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">Auf Google Maps öffnen →</p>
                        </a>
                        ` : '<div></div>'}
                        ${lieferadresseText ? `
                        <a href="${lieferadresseGoogleMapsUrl}" target="_blank" rel="noopener noreferrer" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-all cursor-pointer group">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg group-hover:bg-green-200 dark:group-hover:bg-green-800 transition-colors">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Lieferadresse</h3>
                            </div>
                            <p class="text-gray-900 dark:text-white text-sm">${escapeHtml(lieferadresseText)}</p>
                            <p class="text-xs text-green-600 dark:text-green-400 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">Auf Google Maps öffnen →</p>
                        </a>
                        ` : '<div></div>'}
                        ${rechnungsadresseText ? `
                        <a href="${rechnungsadresseGoogleMapsUrl}" target="_blank" rel="noopener noreferrer" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-all cursor-pointer group">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg group-hover:bg-purple-200 dark:group-hover:bg-purple-800 transition-colors">
                                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Rechnungsadresse</h3>
                            </div>
                            <p class="text-gray-900 dark:text-white text-sm">${escapeHtml(rechnungsadresseText)}</p>
                            <p class="text-xs text-purple-600 dark:text-purple-400 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">Auf Google Maps öffnen →</p>
                        </a>
                        ` : '<div></div>'}
                    </div>
                </div>
                
                <!-- Zugänge/Kontakte Tab -->
                <div id="tab-users" class="tab-content" style="display: none;">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Zugänge/Kontakte</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Alle Benutzer der zugeordneten Firma</p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="userSearch" class="sr-only">Suche</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                                </svg>
                            </div>
                            <input type="text" id="userSearch" onkeyup="filterUsers()" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Nach Name oder E-Mail suchen...">
                        </div>
                    </div>
                    <div id="usersContainer">
                        <div class="text-center text-gray-500 dark:text-gray-400 py-4">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Lade Benutzer...
                        </div>
                    </div>
                </div>
                
                <!-- Dokumente Tab -->
                <div id="tab-documents" class="tab-content" style="display: none;">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Dokumente</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Dokumente für diesen Kunden verwalten</p>
                        </div>
                        <button onclick="showDocumentUpload()" class="px-4 py-2 bg-primary-900 hover:bg-primary-950 text-white rounded-lg text-sm font-medium flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Dokument hochladen
                        </button>
                    </div>
                    <div class="mb-4">
                        <label for="documentSearch" class="sr-only">Suche</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                                </svg>
                            </div>
                            <input type="text" id="documentSearch" onkeyup="filterDocuments()" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Nach Dokumentnamen suchen...">
                        </div>
                    </div>
                    <div id="documentsContainer">
                        <div class="text-center text-gray-500 dark:text-gray-400 py-4 flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Lade Dokumente...
                        </div>
                    </div>
                </div>
                
                <!-- Rechnungen Tab (nur für Admins) -->
                ${currentUserRole === 'Admin' ? `
                <div id="tab-contracts" class="tab-content" style="display: none;">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Rechnungen</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Rechnungen für diesen Kunden verwalten</p>
                        </div>
                        <button onclick="showContractUpload()" class="px-4 py-2 bg-primary-900 hover:bg-primary-950 text-white rounded-lg text-sm font-medium flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Rechnung hochladen
                        </button>
                    </div>
                    <div class="mb-4">
                        <label for="contractSearch" class="sr-only">Suche</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                                </svg>
                            </div>
                            <input type="text" id="contractSearch" onkeyup="filterContracts()" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Nach Rechnungsnamen suchen...">
                        </div>
                    </div>
                    <div id="contractsContainer">
                        <div class="text-center text-gray-500 dark:text-gray-400 py-4 flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Lade Rechnungen...
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Notizen Tab -->
                <div id="tab-notes" class="tab-content" style="display: none;">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notizen</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Notizen für diesen Kunden verwalten</p>
                        </div>
                        <button onclick="showNoteCreate()" class="px-4 py-2 bg-primary-900 hover:bg-primary-950 text-white rounded-lg text-sm font-medium flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Notiz erstellen
                        </button>
                    </div>
                    <div class="mb-4">
                        <label for="noteSearch" class="sr-only">Suche</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                                </svg>
                            </div>
                            <input type="text" id="noteSearch" onkeyup="filterNotes()" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Nach Notizen suchen...">
                        </div>
                    </div>
                    <div id="notesContainer">
                        <div class="text-center text-gray-500 dark:text-gray-400 py-4 flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Lade Notizen...
                        </div>
                    </div>
                </div>
                
                <!-- Aktivitätsprotokoll Tab -->
                <div id="tab-logs" class="tab-content" style="display: none;">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Aktivitätsprotokoll</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Alle Änderungen und Aktivitäten dieses Kunden</p>
                    </div>
                    <div id="logsContainer">
                        <div class="text-center text-gray-500 dark:text-gray-400 py-4 flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Lade Aktivitätsprotokoll...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function loadLogs() {
    const container = document.getElementById('logsContainer');
    if (!container) return;
    
    fetch(logsApiUrl + '?kategorie=customer&entity_id=' + customerId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.logs) {
                displayLogs(data.logs);
            } else {
                container.innerHTML = '<div class="text-red-500">Fehler beim Laden des Aktivitätsprotokolls</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            container.innerHTML = '<div class="text-red-500">Fehler beim Laden des Aktivitätsprotokolls</div>';
        });
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
                    changeDetails = `<p class="mb-4 text-gray-600 dark:text-gray-400">Kunde wurde ${actionTextLabel.toLowerCase()}.</p>`;
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

// Zugänge/Kontakte Tab - zeigt Benutzer mit customer_id des aktuellen Kunden
function loadUsers() {
    const container = document.getElementById('usersContainer');
    if (!container) return;
    
    // Benutzer direkt nach customer_id abrufen
    fetch(customersApiUrl + '?customer_id=' + customerId + '&users=1')
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'HTTP error! status: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success && data.users) {
                displayUsers(data.users);
            } else {
                container.innerHTML = '<div class="text-red-500">Fehler beim Laden der Benutzer: ' + (data.error || 'Unbekannter Fehler') + '</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            const container = document.getElementById('usersContainer');
            if (container) {
                container.innerHTML = '<div class="text-red-500">Fehler beim Laden der Benutzer: ' + escapeHtml(error.message || 'Unbekannter Fehler') + '</div>';
            }
        });
}

let allUsers = [];

function displayUsers(users) {
    const container = document.getElementById('usersContainer');
    if (!container) return;
    
    allUsers = users;
    
    if (users.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Benutzer vorhanden</div>';
        return;
    }
    
    renderUsersTable(users);
}

function renderUsersTable(users) {
    const container = document.getElementById('usersContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">E-Mail</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rolle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Letzte Anmeldung</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    ${users.map(user => {
                        const name = (user.vorname || '') + ' ' + (user.nachname || '').trim() || user.email;
                        const statusBadge = getStatusBadge(user.status || 'aktiv');
                        const avatarHtml = renderUserAvatarHtml(
                            user.logopfad || '',
                            user.vorname || '',
                            user.nachname || '',
                            user.email || '',
                            'h-8 w-8 rounded-full object-cover mr-3',
                            name
                        );
                        return `
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" onclick="window.location.href='${detailBaseUrl}admin/users.php?expand=${user.id}'">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        ${avatarHtml}
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(name)}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${escapeHtml(user.email)}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${escapeHtml(user.rolle || '-')}</td>
                                <td class="px-6 py-4 whitespace-nowrap">${statusBadge}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${formatDate(user.letzte_anmeldung)}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function filterUsers() {
    const searchTerm = document.getElementById('userSearch')?.value.toLowerCase();
    if (!searchTerm) {
        renderUsersTable(allUsers);
        return;
    }
    
    const filtered = allUsers.filter(user => {
        const name = ((user.vorname || '') + ' ' + (user.nachname || '')).trim().toLowerCase();
        const email = (user.email || '').toLowerCase();
        return name.includes(searchTerm) || email.includes(searchTerm);
    });
    
    renderUsersTable(filtered);
}

function loadDocuments() {
    const container = document.getElementById('documentsContainer');
    if (!container) return;
    
    fetch(documentsApiUrl + '?customer_id=' + customerId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.documents) {
                displayDocuments(data.documents);
            } else {
                container.innerHTML = '<div class="text-red-500">Fehler beim Laden der Dokumente</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            container.innerHTML = '<div class="text-red-500">Fehler beim Laden der Dokumente</div>';
        });
}

let allDocuments = [];

function displayDocuments(documents) {
    const container = document.getElementById('documentsContainer');
    if (!container) return;
    
    allDocuments = documents;
    
    if (documents.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Dokumente vorhanden</div>';
        return;
    }
    
    renderDocumentsGrid(documents);
}

function renderDocumentsGrid(documents) {
    const container = document.getElementById('documentsContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${documents.map(doc => {
                const fileSize = doc.dateigroesse ? (doc.dateigroesse / 1024).toFixed(2) + ' KB' : '-';
                const downloadUrl = detailBaseUrl + 'customers/api/documents.php?download=' + doc.id;
                const viewUrl = detailBaseUrl + doc.dateipfad;
                const mimeType = doc.mime_type || '';
                const canView = mimeType.startsWith('image/') || 
                               mimeType === 'application/pdf' || 
                               mimeType.startsWith('text/');
                
                return `
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="font-medium text-gray-900 dark:text-white text-sm truncate flex-1" title="${escapeHtml(doc.dateiname)}">${escapeHtml(doc.dateiname)}</h4>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        ${canView ? `
                                            <button onclick="viewDocument('${viewUrl}', '${escapeHtml(doc.dateiname)}', '${mimeType}', ${doc.id})" 
                                                    class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 p-1 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition-colors" 
                                                    title="Ansehen">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                        ` : ''}
                                        <a href="${downloadUrl}" 
                                           class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 p-1 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition-colors" 
                                           title="Herunterladen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">${fileSize} • ${formatDate(doc.erstellt_datum)}</p>
                            </div>
                            <button onclick="deleteDocument(${doc.id})" class="ml-2 text-red-600 hover:text-red-800 dark:text-red-400 flex-shrink-0 p-1 hover:bg-red-50 dark:hover:bg-red-900 rounded transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                        ${doc.beschreibung ? `<p class="text-xs text-gray-600 dark:text-gray-400 mt-2">${escapeHtml(doc.beschreibung)}</p>` : ''}
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function filterDocuments() {
    const searchTerm = document.getElementById('documentSearch')?.value.toLowerCase();
    if (!searchTerm) {
        renderDocumentsGrid(allDocuments);
        return;
    }
    
    const filtered = allDocuments.filter(doc => {
        const name = (doc.dateiname || '').toLowerCase();
        const beschreibung = (doc.beschreibung || '').toLowerCase();
        return name.includes(searchTerm) || beschreibung.includes(searchTerm);
    });
    
    renderDocumentsGrid(filtered);
}

function showDocumentUpload() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Dokument hochladen</h3>
            <form id="documentUploadForm" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Datei</label>
                    <div id="dropZone" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-primary-500 dark:hover:border-primary-400 transition-colors bg-gray-50 dark:bg-gray-700">
                        <div id="dropZoneContent">
                            <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                <span class="font-medium text-primary-600 dark:text-primary-400">Klicken Sie hier</span> oder ziehen Sie eine Datei hierher
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-500">PDF, Bilder, Dokumente (max. 10MB)</p>
                        </div>
                        <div id="dropZoneFile" class="hidden">
                            <svg class="w-10 h-10 mx-auto text-primary-600 dark:text-primary-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-sm font-medium text-gray-900 dark:text-white" id="dropZoneFileName"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Klicken Sie erneut, um eine andere Datei zu wählen</p>
                        </div>
                    </div>
                    <input type="file" id="documentFile" name="file" required class="hidden">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beschreibung (optional)</label>
                    <textarea id="documentDescription" name="beschreibung" rows="3"
                              class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Abbrechen
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-900 hover:bg-primary-950 text-white rounded-lg">
                        Hochladen
                    </button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    
    const dropZone = document.getElementById('dropZone');
    const dropZoneContent = document.getElementById('dropZoneContent');
    const dropZoneFile = document.getElementById('dropZoneFile');
    const dropZoneFileName = document.getElementById('dropZoneFileName');
    const fileInput = document.getElementById('documentFile');
    
    dropZone.addEventListener('click', () => {
        fileInput.click();
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            const file = e.target.files[0];
            dropZoneContent.classList.add('hidden');
            dropZoneFile.classList.remove('hidden');
            dropZoneFileName.textContent = file.name;
            dropZone.classList.remove('border-gray-300', 'dark:border-gray-600');
            dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
        }
    });
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!dropZoneFile.classList.contains('hidden')) {
            dropZone.classList.remove('border-gray-300', 'dark:border-gray-600');
            dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
        } else {
            dropZone.classList.remove('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
            dropZone.classList.add('border-gray-300', 'dark:border-gray-600');
        }
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            
            if (file.size > 10 * 1024 * 1024) {
                alert('Die Datei ist zu groß (max. 10MB)');
                return;
            }
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
            
            dropZoneContent.classList.add('hidden');
            dropZoneFile.classList.remove('hidden');
            dropZoneFileName.textContent = file.name;
            dropZone.classList.remove('border-gray-300', 'dark:border-gray-600');
            dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
        }
    });
    
    const form = document.getElementById('documentUploadForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        uploadDocument(modal);
    });
}

function uploadDocument(modal) {
    const formData = new FormData();
    const fileInput = document.getElementById('documentFile');
    const descriptionInput = document.getElementById('documentDescription');
    const description = descriptionInput ? descriptionInput.value : '';
    
    if (!fileInput || !fileInput.files[0]) {
        alert('Bitte wählen Sie eine Datei aus');
        return;
    }
    
    formData.append('file', fileInput.files[0]);
    formData.append('customer_id', customerId);
    if (description) {
        formData.append('beschreibung', description);
    }
    
    const submitButton = modal.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Wird hochgeladen...';
    }
    
    fetch(documentsApiUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'HTTP error! status: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Dokument erfolgreich hochgeladen', 'success');
            }
            if (modal) {
                modal.remove();
            }
            loadDocuments();
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Hochladen';
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        alert('Fehler beim Hochladen des Dokuments: ' + error.message);
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Hochladen';
        }
    });
}

function viewDocument(url, filename, mimeType, documentId = null) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
    const downloadUrl = documentId ? detailBaseUrl + 'customers/api/documents.php?download=' + documentId : url;
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-6xl w-full mx-4 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">${escapeHtml(filename)}</h3>
                <div class="flex space-x-2">
                    <a href="${downloadUrl}" download class="px-3 py-1.5 bg-primary-900 hover:bg-primary-950 text-white rounded-lg text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Herunterladen
                    </a>
                    <button onclick="this.closest('.fixed').remove()" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white rounded-lg text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Schließen
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-auto p-4">
                ${mimeType.startsWith('image/') ? `
                    <img src="${url}" alt="${escapeHtml(filename)}" class="max-w-full h-auto mx-auto">
                ` : mimeType === 'application/pdf' ? `
                    <iframe src="${url}" class="w-full h-full min-h-[600px] border-0"></iframe>
                ` : mimeType.startsWith('text/') ? `
                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                        <pre class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap font-mono">Lädt...</pre>
                    </div>
                ` : `
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-600 dark:text-gray-400">Diese Datei kann nicht in der Vorschau angezeigt werden.</p>
                        <a href="${url}" download class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-primary-900 hover:bg-primary-950 text-white rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Herunterladen
                        </a>
                    </div>
                `}
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    if (mimeType.startsWith('text/')) {
        fetch(url)
            .then(response => response.text())
            .then(text => {
                const pre = modal.querySelector('pre');
                if (pre) {
                    pre.textContent = text;
                }
            })
            .catch(error => {
                console.error('Fehler beim Laden der Textdatei:', error);
                const pre = modal.querySelector('pre');
                if (pre) {
                    pre.textContent = 'Fehler beim Laden der Datei.';
                }
            });
    }
    
    const closeOnEscape = (e) => {
        if (e.key === 'Escape') {
            modal.remove();
            document.removeEventListener('keydown', closeOnEscape);
        }
    };
    document.addEventListener('keydown', closeOnEscape);
}

function deleteDocument(documentId) {
    if (!confirm('Möchten Sie dieses Dokument wirklich löschen?')) {
        return;
    }
    
    fetch(documentsApiUrl + '?id=' + documentId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadDocuments();
            if (typeof showToast === 'function') {
                showToast('Dokument erfolgreich gelöscht', 'success');
            }
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
            showToast('Fehler beim Löschen des Dokuments', 'error');
        } else {
            alert('Fehler beim Löschen des Dokuments');
        }
    });
}

function loadContracts() {
    const container = document.getElementById('contractsContainer');
    if (!container) return;
    
    fetch(contractsApiUrl + '?customer_id=' + customerId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.contracts) {
                displayContracts(data.contracts);
            } else {
                container.innerHTML = '<div class="text-red-500">Fehler beim Laden der Rechnungen</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            container.innerHTML = '<div class="text-red-500">Fehler beim Laden der Rechnungen</div>';
        });
}

let allContracts = [];

function displayContracts(contracts) {
    const container = document.getElementById('contractsContainer');
    if (!container) return;
    
    allContracts = contracts;
    
    if (contracts.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Rechnungen vorhanden</div>';
        return;
    }
    
    renderContractsGrid(contracts);
}

function renderContractsGrid(contracts) {
    const container = document.getElementById('contractsContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${contracts.map(contract => {
                const fileSize = contract.dateigroesse ? (contract.dateigroesse / 1024).toFixed(2) + ' KB' : '-';
                const downloadUrl = detailBaseUrl + 'customers/api/contracts.php?download=' + contract.id;
                const viewUrl = detailBaseUrl + contract.dateipfad;
                const mimeType = contract.mime_type || '';
                const canView = mimeType.startsWith('image/') || 
                               mimeType === 'application/pdf' || 
                               mimeType.startsWith('text/');
                
                return `
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="font-medium text-gray-900 dark:text-white text-sm truncate flex-1" title="${escapeHtml(contract.dateiname)}">${escapeHtml(contract.dateiname)}</h4>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        ${canView ? `
                                            <button onclick="viewContract('${viewUrl}', '${escapeHtml(contract.dateiname)}', '${mimeType}', ${contract.id})" 
                                                    class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 p-1 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition-colors" 
                                                    title="Ansehen">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                        ` : ''}
                                        <a href="${downloadUrl}" 
                                           class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 p-1 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition-colors" 
                                           title="Herunterladen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">${fileSize} • ${formatDate(contract.erstellt_datum)}</p>
                            </div>
                            <button onclick="deleteContract(${contract.id})" class="ml-2 text-red-600 hover:text-red-800 dark:text-red-400 flex-shrink-0 p-1 hover:bg-red-50 dark:hover:bg-red-900 rounded transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                        ${contract.beschreibung ? `<p class="text-xs text-gray-600 dark:text-gray-400 mt-2">${escapeHtml(contract.beschreibung)}</p>` : ''}
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function filterContracts() {
    const searchTerm = document.getElementById('contractSearch')?.value.toLowerCase();
    if (!searchTerm) {
        renderContractsGrid(allContracts);
        return;
    }
    
    const filtered = allContracts.filter(contract => {
        const name = (contract.dateiname || '').toLowerCase();
        const beschreibung = (contract.beschreibung || '').toLowerCase();
        return name.includes(searchTerm) || beschreibung.includes(searchTerm);
    });
    
    renderContractsGrid(filtered);
}

function showContractUpload() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Rechnung hochladen</h3>
            <form id="contractUploadForm" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Datei</label>
                    <div id="contractDropZone" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-primary-500 dark:hover:border-primary-400 transition-colors bg-gray-50 dark:bg-gray-700">
                        <div id="contractDropZoneContent">
                            <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                <span class="font-medium text-primary-600 dark:text-primary-400">Klicken Sie hier</span> oder ziehen Sie eine Datei hierher
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-500">PDF, Bilder, Dokumente (max. 10MB)</p>
                        </div>
                        <div id="contractDropZoneFile" class="hidden">
                            <svg class="w-10 h-10 mx-auto text-primary-600 dark:text-primary-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-sm font-medium text-gray-900 dark:text-white" id="contractDropZoneFileName"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Klicken Sie erneut, um eine andere Datei zu wählen</p>
                        </div>
                    </div>
                    <input type="file" id="contractFile" name="file" required class="hidden">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beschreibung (optional)</label>
                    <textarea id="contractDescription" name="beschreibung" rows="3"
                              class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Abbrechen
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-900 hover:bg-primary-950 text-white rounded-lg">
                        Hochladen
                    </button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    
    const dropZone = document.getElementById('contractDropZone');
    const dropZoneContent = document.getElementById('contractDropZoneContent');
    const dropZoneFile = document.getElementById('contractDropZoneFile');
    const dropZoneFileName = document.getElementById('contractDropZoneFileName');
    const fileInput = document.getElementById('contractFile');
    
    dropZone.addEventListener('click', () => {
        fileInput.click();
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            const file = e.target.files[0];
            dropZoneContent.classList.add('hidden');
            dropZoneFile.classList.remove('hidden');
            dropZoneFileName.textContent = file.name;
            dropZone.classList.remove('border-gray-300', 'dark:border-gray-600');
            dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
        }
    });
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!dropZoneFile.classList.contains('hidden')) {
            dropZone.classList.remove('border-gray-300', 'dark:border-gray-600');
            dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
        } else {
            dropZone.classList.remove('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
            dropZone.classList.add('border-gray-300', 'dark:border-gray-600');
        }
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            
            if (file.size > 10 * 1024 * 1024) {
                alert('Die Datei ist zu groß (max. 10MB)');
                return;
            }
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
            
            dropZoneContent.classList.add('hidden');
            dropZoneFile.classList.remove('hidden');
            dropZoneFileName.textContent = file.name;
            dropZone.classList.remove('border-gray-300', 'dark:border-gray-600');
            dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
        }
    });
    
    const form = document.getElementById('contractUploadForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        uploadContract(modal);
    });
}

function uploadContract(modal) {
    const formData = new FormData();
    const fileInput = document.getElementById('contractFile');
    const descriptionInput = document.getElementById('contractDescription');
    const description = descriptionInput ? descriptionInput.value : '';
    
    if (!fileInput || !fileInput.files[0]) {
        alert('Bitte wählen Sie eine Datei aus');
        return;
    }
    
    formData.append('file', fileInput.files[0]);
    formData.append('customer_id', customerId);
    if (description) {
        formData.append('beschreibung', description);
    }
    
    const submitButton = modal.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Wird hochgeladen...';
    }
    
    fetch(contractsApiUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'HTTP error! status: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (modal) {
                modal.remove();
            }
            loadContracts();
            if (typeof showToast === 'function') {
                showToast('Rechnung erfolgreich hochgeladen', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Hochladen';
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Hochladen der Rechnung: ' + error.message, 'error');
        } else {
            alert('Fehler beim Hochladen der Rechnung: ' + error.message);
        }
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Hochladen';
        }
    });
}

function viewContract(url, filename, mimeType, contractId = null) {
    viewDocument(url, filename, mimeType, contractId); // Wiederverwendung der viewDocument-Funktion
}

function deleteContract(contractId) {
    if (!confirm('Möchten Sie diese Rechnung wirklich löschen?')) {
        return;
    }
    
    fetch(contractsApiUrl + '?id=' + contractId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadContracts();
            if (typeof showToast === 'function') {
                showToast('Rechnung erfolgreich gelöscht', 'success');
            }
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
            showToast('Fehler beim Löschen der Rechnung', 'error');
        } else {
            alert('Fehler beim Löschen der Rechnung');
        }
    });
}

function loadNotes() {
    const container = document.getElementById('notesContainer');
    if (!container) return;
    
    fetch(notesApiUrl + '?customer_id=' + customerId)
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'HTTP error! status: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayNotes(data.notes || []);
            } else {
                container.innerHTML = '<div class="text-red-500">Fehler beim Laden der Notizen: ' + (data.error || 'Unbekannter Fehler') + '</div>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Notizen:', error);
            container.innerHTML = '<div class="text-red-500">Fehler beim Laden der Notizen: ' + escapeHtml(error.message || 'Unbekannter Fehler') + '</div>';
        });
}

let allNotes = [];

function displayNotes(notes) {
    const container = document.getElementById('notesContainer');
    if (!container) return;
    
    allNotes = notes;
    
    if (notes.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Notizen vorhanden</div>';
        return;
    }
    
    renderNotesGrid(notes);
}

function renderNotesGrid(notes) {
    const container = document.getElementById('notesContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${notes.map(note => {
                const creatorName = note.ersteller_vorname && note.ersteller_nachname 
                    ? `${note.ersteller_vorname} ${note.ersteller_nachname}` 
                    : 'Unbekannt';
                const inhaltPreview = note.inhalt.length > 150 
                    ? note.inhalt.substring(0, 150) + '...' 
                    : note.inhalt;
                
                return `
                    <div id="note-${note.id}" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600 hover:shadow-md transition-shadow">
                        <div id="note-display-${note.id}">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 dark:text-white text-sm mb-1 truncate" title="${escapeHtml(note.titel)}">${escapeHtml(note.titel)}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">${formatDate(note.erstellt_datum)} • ${escapeHtml(creatorName)}</p>
                                </div>
                                <div class="flex gap-1 ml-2">
                                    ${(currentUserRole === 'Admin' || currentUserRole === 'Techniker' || currentUserRole === 'Firmen-Admin') ? `
                                    <button onclick="editNote(${note.id})" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 flex-shrink-0 p-1 hover:bg-blue-50 dark:hover:bg-blue-900 rounded transition-colors" title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    ` : ''}
                                    <button onclick="deleteNote(${note.id})" class="text-red-600 hover:text-red-800 dark:text-red-400 flex-shrink-0 p-1 hover:bg-red-50 dark:hover:bg-red-900 rounded transition-colors" title="Löschen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${escapeHtml(inhaltPreview)}</p>
                            </div>
                            ${note.inhalt.length > 150 ? `
                                <button onclick="viewNote(${note.id}, ${JSON.stringify(note.titel)}, ${JSON.stringify(note.inhalt)})" 
                                        class="mt-2 text-primary-600 hover:text-primary-800 dark:text-primary-400 text-xs">
                                    Mehr anzeigen...
                                </button>
                            ` : ''}
                        </div>
                        <div id="note-edit-${note.id}" class="hidden">
                            <input type="text" id="note-title-${note.id}" value="${escapeHtml(note.titel)}" 
                                   class="w-full mb-3 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                            <textarea id="note-content-${note.id}" rows="6" 
                                      class="w-full mb-3 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500">${escapeHtml(note.inhalt)}</textarea>
                            <div class="flex justify-end gap-2">
                                <button onclick="cancelEditNote(${note.id})" class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                    Abbrechen
                                </button>
                                <button onclick="saveNote(${note.id})" class="px-3 py-1.5 text-sm font-medium text-white bg-primary-900 hover:bg-primary-950 rounded-lg transition-colors">
                                    Speichern
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function filterNotes() {
    const searchTerm = document.getElementById('noteSearch')?.value.toLowerCase();
    if (!searchTerm) {
        renderNotesGrid(allNotes);
        return;
    }
    
    const filtered = allNotes.filter(note => {
        const titel = (note.titel || '').toLowerCase();
        const inhalt = (note.inhalt || '').toLowerCase();
        return titel.includes(searchTerm) || inhalt.includes(searchTerm);
    });
    
    renderNotesGrid(filtered);
}

function showNoteCreate() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] flex flex-col">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Notiz erstellen</h3>
            <form id="noteCreateForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Titel *</label>
                    <input type="text" id="noteTitle" required
                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                           placeholder="Titel der Notiz">
                </div>
                <div class="mb-4 flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Inhalt *</label>
                    <textarea id="noteContent" required rows="12"
                              class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                              placeholder="Notizinhalt..."></textarea>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Abbrechen
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-900 hover:bg-primary-950 text-white rounded-lg">
                        Erstellen
                    </button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    
    const form = document.getElementById('noteCreateForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        createNote(modal);
    });
}

function createNote(modal) {
    const title = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value.trim();
    
    if (!title || !content) {
        alert('Titel und Inhalt sind erforderlich');
        return;
    }
    
    const submitButton = modal.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Wird erstellt...';
    }
    
    fetch(notesApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            customer_id: customerId,
            titel: title,
            inhalt: content
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'HTTP error! status: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (modal) {
                modal.remove();
            }
            loadNotes();
            if (typeof showToast === 'function') {
                showToast('Notiz erfolgreich erstellt', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Erstellen';
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Erstellen der Notiz: ' + error.message, 'error');
        } else {
            alert('Fehler beim Erstellen der Notiz: ' + error.message);
        }
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Erstellen';
        }
    });
}

function viewNote(noteId, title, content) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">${escapeHtml(title)}</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-auto">
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${escapeHtml(content)}</p>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function deleteNote(noteId) {
    if (!confirm('Möchten Sie diese Notiz wirklich löschen?')) {
        return;
    }
    
    fetch(notesApiUrl + '?id=' + noteId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotes();
            if (typeof showToast === 'function') {
                showToast('Notiz erfolgreich gelöscht', 'success');
            }
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
            showToast('Fehler beim Löschen der Notiz', 'error');
        } else {
            alert('Fehler beim Löschen der Notiz');
        }
    });
}

function editNote(noteId) {
    const displayDiv = document.getElementById(`note-display-${noteId}`);
    const editDiv = document.getElementById(`note-edit-${noteId}`);
    
    if (!displayDiv) {
        console.error('Display div not found for note', noteId);
        return;
    }
    
    if (!editDiv) {
        console.error('Edit div not found for note', noteId);
        return;
    }
    
    displayDiv.classList.add('hidden');
    editDiv.classList.remove('hidden');
}

function cancelEditNote(noteId) {
    const displayDiv = document.getElementById(`note-display-${noteId}`);
    const editDiv = document.getElementById(`note-edit-${noteId}`);
    
    if (displayDiv && editDiv) {
        editDiv.classList.add('hidden');
        displayDiv.classList.remove('hidden');
        // Notizen neu laden, um Originalwerte wiederherzustellen
        loadNotes();
    }
}

function saveNote(noteId) {
    const titleInput = document.getElementById(`note-title-${noteId}`);
    const contentTextarea = document.getElementById(`note-content-${noteId}`);
    
    if (!titleInput || !contentTextarea) {
        alert('Fehler: Eingabefelder nicht gefunden');
        return;
    }
    
    const title = titleInput.value.trim();
    const content = contentTextarea.value.trim();
    
    if (!title) {
        alert('Bitte geben Sie einen Titel ein');
        return;
    }
    
    const saveButton = event.target;
    const originalText = saveButton.textContent;
    saveButton.disabled = true;
    saveButton.textContent = 'Wird gespeichert...';
    
    fetch(notesApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: noteId,
            titel: title,
            inhalt: content
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotes();
            if (typeof showToast === 'function') {
                showToast('Notiz erfolgreich gespeichert', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
            saveButton.disabled = false;
            saveButton.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Speichern der Notiz: ' + (error.message || 'Unbekannter Fehler'), 'error');
        } else {
            alert('Fehler beim Speichern der Notiz: ' + (error.message || 'Unbekannter Fehler'));
        }
        saveButton.disabled = false;
        saveButton.textContent = originalText;
    });
}

// Notizen bearbeiten
function toggleNotesEdit() {
    const notesDisplay = document.getElementById('notesDisplay');
    const notesEdit = document.getElementById('notesEdit');
    const editBtn = document.getElementById('editNotesBtn');
    
    if (notesDisplay && notesEdit) {
        notesDisplay.classList.add('hidden');
        notesEdit.classList.remove('hidden');
        if (editBtn) {
            editBtn.style.display = 'none';
        }
    }
}

function cancelNotesEdit() {
    const notesDisplay = document.getElementById('notesDisplay');
    const notesEdit = document.getElementById('notesEdit');
    const editBtn = document.getElementById('editNotesBtn');
    const textarea = document.getElementById('notesTextarea');
    
    if (notesDisplay && notesEdit) {
        notesEdit.classList.add('hidden');
        notesDisplay.classList.remove('hidden');
        if (editBtn) {
            editBtn.style.display = 'block';
        }
        // Textarea zurücksetzen
        if (textarea) {
            const originalNotes = document.querySelector('#notesDisplay p').textContent.trim();
            textarea.value = originalNotes === 'Keine Notizen vorhanden' ? '' : originalNotes;
        }
    }
}

function saveNotes() {
    const textarea = document.getElementById('notesTextarea');
    if (!textarea) return;
    
    const notes = textarea.value.trim();
    const saveBtn = event.target;
    const originalText = saveBtn.textContent;
    
    saveBtn.disabled = true;
    saveBtn.textContent = 'Wird gespeichert...';
    
    // Aktuelle Kundendaten laden, um alle Felder zu haben
    fetch(customersApiUrl + '?id=' + customerId)
        .then(response => response.json())
        .then(customerData => {
            if (!customerData.success || !customerData.customer) {
                throw new Error('Fehler beim Laden der Kundendaten');
            }
            
            const customer = customerData.customer;
            
            // Update mit allen Feldern, aber nur notizen wird geändert
            return fetch(customersApiUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    customer_id: customerId,
                    name: customer.name || '',
                    kundennummer: customer.kundennummer || null,
                    email: customer.email || null,
                    telefon: customer.telefon || null,
                    adresse: customer.adresse || null,
                    plz: customer.plz || null,
                    ort: customer.ort || null,
                    lieferadresse: customer.lieferadresse || null,
                    liefer_plz: customer.liefer_plz || null,
                    liefer_ort: customer.liefer_ort || null,
                    rechnungs_adresse: customer.rechnungs_adresse || null,
                    rechnungs_plz: customer.rechnungs_plz || null,
                    rechnungs_ort: customer.rechnungs_ort || null,
                    rechnungs_email: customer.rechnungs_email || null,
                    company_id: customer.company_id || null,
                    notizen: notes,
                    status: customer.status || 'aktiv'
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                saveBtn.textContent = 'Gespeichert!';
                saveBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                saveBtn.classList.remove('bg-primary-900', 'hover:bg-primary-950');
                if (typeof showToast === 'function') {
                    showToast('Notizen erfolgreich gespeichert', 'success');
                }
                
                setTimeout(() => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                    saveBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    saveBtn.classList.add('bg-primary-900', 'hover:bg-primary-950');
                }, 2000);
            } else {
                if (typeof showToast === 'function') {
                    showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            alert('Fehler beim Speichern der Notizen: ' + (error.message || 'Unbekannter Fehler'));
            saveBtn.disabled = false;
            saveBtn.textContent = originalText;
        });
}

function changeCustomerStatus(newStatus) {
    // Aktuelle Kundendaten laden, um alle Felder zu haben
    fetch(customersApiUrl + '?id=' + customerId)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.customer) {
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Laden der Kundendaten', 'error');
                } else {
                    alert('Fehler beim Laden der Kundendaten');
                }
                return;
            }
            
            const customer = data.customer;
            
            // Update mit allen Feldern, aber nur status wird geändert
            return fetch(customersApiUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    customer_id: customerId,
                    name: customer.name || '',
                    kundennummer: customer.kundennummer || null,
                    email: customer.email || null,
                    telefon: customer.telefon || null,
                    adresse: customer.adresse || null,
                    plz: customer.plz || null,
                    ort: customer.ort || null,
                    lieferadresse: customer.lieferadresse || null,
                    liefer_plz: customer.liefer_plz || null,
                    liefer_ort: customer.liefer_ort || null,
                    rechnungs_adresse: customer.rechnungs_adresse || null,
                    rechnungs_plz: customer.rechnungs_plz || null,
                    rechnungs_ort: customer.rechnungs_ort || null,
                    rechnungs_email: customer.rechnungs_email || null,
                    company_id: customer.company_id || null,
                    notizen: customer.notizen || null,
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
                        'gesperrt': 'Gesperrt'
                    };
                    showToast(`Status erfolgreich auf "${statusLabels[newStatus] || newStatus}" geändert`, 'success');
                }
                // Kunde neu laden
                loadCustomer();
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

function deleteCustomer(customerId) {
    const confirmMessage = currentUserRole === 'Admin' 
        ? 'Möchten Sie diesen Kunden wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.' 
        : 'Möchten Sie diesen Kunden wirklich auf inaktiv setzen?';
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    fetch(customersApiUrl + '?id=' + customerId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                const message = currentUserRole === 'Admin' 
                    ? 'Kunde erfolgreich gelöscht' 
                    : 'Kunde erfolgreich auf inaktiv gesetzt';
                showToast(message, 'success');
            }
            // Zur Kunden-Liste weiterleiten
            setTimeout(() => {
                window.location.href = detailBaseUrl + 'customers/';
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
        if (typeof showToast === 'function') {
            const errorMessage = currentUserRole === 'Admin' 
                ? 'Fehler beim Löschen des Kunden' 
                : 'Fehler beim Setzen des Kunden auf inaktiv';
            showToast(errorMessage, 'error');
        } else {
            const errorMessage = currentUserRole === 'Admin' 
                ? 'Fehler beim Löschen des Kunden' 
                : 'Fehler beim Setzen des Kunden auf inaktiv';
            alert(errorMessage);
        }
    });
}

</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
