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

// Nur Firmen-Admin und Admin können Kunden bearbeiten (Techniker nicht)
if ($userRole !== 'Admin' && $userRole !== 'Firmen-Admin') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Firmen für Dropdown laden
$companies = [];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($userCompanyId) {
    $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv'");
    $stmt->execute([$userCompanyId]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// User werden dynamisch per JavaScript geladen, basierend auf der Firma des Kunden

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
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Kunde bearbeiten</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Kunde bearbeiten</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Bearbeiten Sie die Informationen des Kunden</p>
                        </div>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <div id="customerContent" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                                <svg class="animate-spin h-8 w-8 text-gray-400 dark:text-gray-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="mt-2">Lade Kundendaten...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/nav-unsaved-changes.js"></script>
<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/media-library-modal.js"></script>
<script>
const customerId = <?php echo $customerId; ?>;
const editBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>';
const customersApiUrl = editBaseUrl + 'customers/api/customers.php';
const companiesApiUrl = editBaseUrl + 'companies/api/companies.php';

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
                const customer = data.customer;
                customer.email = customer.email !== null && customer.email !== undefined ? customer.email : '';
                customer.telefon = customer.telefon !== null && customer.telefon !== undefined ? customer.telefon : '';
                customer.adresse = customer.adresse !== null && customer.adresse !== undefined ? customer.adresse : '';
                customer.plz = customer.plz !== null && customer.plz !== undefined ? customer.plz : '';
                customer.ort = customer.ort !== null && customer.ort !== undefined ? customer.ort : '';
                customer.logo = customer.logo !== null && customer.logo !== undefined ? customer.logo : '';
                customer.company_id = customer.company_id !== null && customer.company_id !== undefined ? customer.company_id : null;
                customer.ansprechpartner_user_id = customer.ansprechpartner_user_id !== null && customer.ansprechpartner_user_id !== undefined ? customer.ansprechpartner_user_id : null;
                customer.ansprechpartner_manuell_name = customer.ansprechpartner_manuell_name !== null && customer.ansprechpartner_manuell_name !== undefined ? customer.ansprechpartner_manuell_name : '';
                customer.ansprechpartner_manuell_email = customer.ansprechpartner_manuell_email !== null && customer.ansprechpartner_manuell_email !== undefined ? customer.ansprechpartner_manuell_email : '';
                customer.ansprechpartner_manuell_telefon = customer.ansprechpartner_manuell_telefon !== null && customer.ansprechpartner_manuell_telefon !== undefined ? customer.ansprechpartner_manuell_telefon : '';
                customer.ansprechpartner_manuell_notiz = customer.ansprechpartner_manuell_notiz !== null && customer.ansprechpartner_manuell_notiz !== undefined ? customer.ansprechpartner_manuell_notiz : '';
                customer.status = customer.status || 'aktiv';
                customer.kundennummer = customer.kundennummer !== null && customer.kundennummer !== undefined ? customer.kundennummer : '';
                customer.lieferadresse = customer.lieferadresse !== null && customer.lieferadresse !== undefined ? customer.lieferadresse : '';
                customer.liefer_plz = customer.liefer_plz !== null && customer.liefer_plz !== undefined ? customer.liefer_plz : '';
                customer.liefer_ort = customer.liefer_ort !== null && customer.liefer_ort !== undefined ? customer.liefer_ort : '';
                customer.rechnungs_adresse = customer.rechnungs_adresse !== null && customer.rechnungs_adresse !== undefined ? customer.rechnungs_adresse : '';
                customer.rechnungs_plz = customer.rechnungs_plz !== null && customer.rechnungs_plz !== undefined ? customer.rechnungs_plz : '';
                customer.rechnungs_ort = customer.rechnungs_ort !== null && customer.rechnungs_ort !== undefined ? customer.rechnungs_ort : '';
                customer.rechnungs_email = customer.rechnungs_email !== null && customer.rechnungs_email !== undefined ? customer.rechnungs_email : '';
                
                displayCustomerForm(customer);
                
                // User für Ansprechpartner laden (nachdem Formular erstellt wurde)
                setTimeout(() => {
                    if (customer.company_id) {
                        loadAnsprechpartnerUsers(customer.company_id, customer.ansprechpartner_user_id);
                    }
                }, 100);
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

function displayCustomerForm(customer) {
    const logoUrl = customer.logo 
        ? (customer.logo.startsWith('http') ? customer.logo : editBaseUrl + customer.logo)
        : editBaseUrl + 'assets/images/default-avatar.png';
    
    document.getElementById('customerContent').innerHTML = `
        <form id="customerForm" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="p-4">
                <!-- Grunddaten: Name, Kundennummer, Status -->
                <div class="mb-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12 md:col-span-6">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                        <input type="text" id="name" name="name" required value="${escapeHtml(customer.name)}"
                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kundennummer</label>
                        <input type="text" id="kundennummer" name="kundennummer" value="${escapeHtml(customer.kundennummer || '')}"
                               placeholder="z.B. KND-12345"
                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label>
                        <select id="status" name="status" required
                                class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="aktiv" ${customer.status === 'aktiv' ? 'selected' : ''}>Aktiv</option>
                            <option value="inaktiv" ${customer.status === 'inaktiv' ? 'selected' : ''}>Inaktiv</option>
                        </select>
                    </div>
                </div>
                
                <!-- Logo und Informationen -->
                <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Logo Upload -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Kundenlogo</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <img id="logoPreview" src="${logoUrl}" alt="${escapeHtml(customer.name)}" class="h-24 w-24 rounded-full object-cover border border-gray-200 dark:border-gray-700 mb-3">
                            <button type="button" id="logoRemoveBtn" onclick="removeLogo()" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 ${customer.logo ? '' : 'hidden'} mb-3">
                                Logo entfernen
                            </button>
                            <div id="logoDropZone" class="w-full border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-2xl p-4 text-center hover:border-primary-500 dark:hover:border-primary-500 transition-colors cursor-pointer bg-gray-50 dark:bg-gray-900">
                                <input type="file" id="logoFileInput" accept="image/*" class="hidden">
                                <div id="logoDropZoneContent">
                                    <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Klicken zum Hochladen oder Datei hierher ziehen</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">PNG, JPG, GIF, WebP, SVG (max. 5MB)</p>
                                </div>
                                <div id="logoUploadProgress" class="hidden mt-2">
                                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                        <div id="logoProgressBar" class="bg-primary-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                                    </div>
                                    <p id="logoUploadStatus" class="text-xs text-gray-600 dark:text-gray-400 mt-1">Wird hochgeladen...</p>
                                </div>
                            </div>
                            <button type="button" id="openMediaLibraryBtn" class="mt-2 w-full px-4 py-2.5 text-xs font-medium rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Aus Medienbibliothek wählen
                            </button>
                            <input type="hidden" id="logo" name="logo" value="${escapeHtml(customer.logo || '')}">
                        </div>
                    </div>
                    
                    <!-- Informationen -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Informationen</span>
                        </div>
                        <div class="space-y-3">
                            <div id="companySelectContainer">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Firma</label>
                                <select id="company_id" name="company_id"
                                        class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="" ${!customer.company_id ? 'selected' : ''}>-- Keine Firma --</option>
                                    ${<?php echo json_encode(array_map(function($company) {
                                        return [
                                            'id' => $company['id'],
                                            'name' => $company['name']
                                        ];
                                    }, $companies)); ?>.map(comp => 
                                        `<option value="${comp.id}" ${(customer.company_id != null && parseInt(customer.company_id) === parseInt(comp.id)) ? 'selected' : ''}>${escapeHtml(comp.name)}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Ansprechpartner</label>
                                <div class="flex items-center gap-2 mb-2">
                        <label class="flex items-center">
                            <input type="radio" name="ansprechpartner_type" value="user" ${customer.ansprechpartner_user_id ? 'checked' : (customer.ansprechpartner_manuell_name ? '' : 'checked')} class="mr-2" onchange="toggleAnsprechpartnerType()">
                            <span class="text-xs text-gray-700 dark:text-gray-300">User auswählen</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="ansprechpartner_type" value="manual" ${customer.ansprechpartner_manuell_name ? 'checked' : ''} class="mr-2" onchange="toggleAnsprechpartnerType()">
                            <span class="text-xs text-gray-700 dark:text-gray-300">Manuell eingeben</span>
                        </label>
                                </div>
                                <div id="ansprechpartner_user_container" style="display: ${customer.ansprechpartner_manuell_name ? 'none' : 'block'};">
                                    <select id="ansprechpartner_user_id" name="ansprechpartner_user_id"
                                            class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <option value="">Lade User...</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nur User der Firma werden angezeigt</p>
                                </div>
                                <div id="ansprechpartner_manuell_container" style="display: ${customer.ansprechpartner_manuell_name ? 'block' : 'none'};">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                                            <input type="text" id="ansprechpartner_manuell_name" name="ansprechpartner_manuell_name"
                                                   value="${escapeHtml(customer.ansprechpartner_manuell_name || '')}"
                                                   placeholder="z.B. Max Mustermann"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail</label>
                                            <input type="email" id="ansprechpartner_manuell_email" name="ansprechpartner_manuell_email"
                                                   value="${escapeHtml(customer.ansprechpartner_manuell_email || '')}"
                                                   placeholder="max.mustermann@beispiel.de"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Telefon</label>
                                            <input type="tel" id="ansprechpartner_manuell_telefon" name="ansprechpartner_manuell_telefon"
                                                   value="${escapeHtml(customer.ansprechpartner_manuell_telefon || '')}"
                                                   placeholder="+49 123 456789"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Notiz</label>
                                            <textarea id="ansprechpartner_manuell_notiz" name="ansprechpartner_manuell_notiz" rows="2"
                                                      placeholder="Zusätzliche Informationen..."
                                                      class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">${escapeHtml(customer.ansprechpartner_manuell_notiz || '')}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail-Adresse</label>
                                <input type="email" id="email" name="email" value="${escapeHtml(customer.email || '')}"
                                       placeholder="info@beispiel.de"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Telefonnummer</label>
                                <input type="tel" id="telefon" name="telefon" value="${escapeHtml(customer.telefon || '')}"
                                       placeholder="+49 123 456789"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rechnungs-E-Mail</label>
                                <input type="email" id="rechnungs_email" name="rechnungs_email" value="${escapeHtml(customer.rechnungs_email || '')}"
                                       placeholder="rechnung@beispiel.de"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Adressen: Adresse, Lieferadresse, Rechnungsadresse -->
                <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Adresse -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Adresse</h3>
                        </div>
                        <div class="space-y-2">
                            <input type="text" id="adresse" name="adresse" value="${escapeHtml(customer.adresse || '')}"
                                   placeholder="Straße und Hausnummer"
                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" id="plz" name="plz" value="${escapeHtml(customer.plz || '')}"
                                       placeholder="PLZ"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <input type="text" id="ort" name="ort" value="${escapeHtml(customer.ort || '')}"
                                       placeholder="Ort"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lieferadresse -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-green-100 dark:bg-green-900 rounded-lg">
                                <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Lieferadresse</h3>
                        </div>
                        <div class="space-y-2">
                            <input type="text" id="lieferadresse" name="lieferadresse" value="${escapeHtml(customer.lieferadresse || '')}"
                                   placeholder="Straße und Hausnummer"
                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" id="liefer_plz" name="liefer_plz" value="${escapeHtml(customer.liefer_plz || '')}"
                                       placeholder="PLZ"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <input type="text" id="liefer_ort" name="liefer_ort" value="${escapeHtml(customer.liefer_ort || '')}"
                                       placeholder="Ort"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rechnungsadresse -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-purple-100 dark:bg-purple-900 rounded-lg">
                                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Rechnungsadresse</h3>
                        </div>
                        <div class="space-y-2">
                            <input type="text" id="rechnungs_adresse" name="rechnungs_adresse" value="${escapeHtml(customer.rechnungs_adresse || '')}"
                                   placeholder="Straße und Hausnummer"
                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" id="rechnungs_plz" name="rechnungs_plz" value="${escapeHtml(customer.rechnungs_plz || '')}"
                                       placeholder="PLZ"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <input type="text" id="rechnungs_ort" name="rechnungs_ort" value="${escapeHtml(customer.rechnungs_ort || '')}"
                                       placeholder="Ort"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>
                    </div>
                </div>
                
        </form>
    `;
    
    if (window.setupPlzOrtAutofill) window.setupPlzOrtAutofill();
    
    // Event Listener für Formular
    document.getElementById('customerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveCustomer();
    });
    
    // Nav-Unsaved-Changes (modular): Banner erscheint bei Änderung, Speichern/Verwerfen in der Nav
    if (window.NavUnsavedChanges) {
        NavUnsavedChanges.init({
            form: 'customerForm',
            discardUrl: editBaseUrl + 'customers/',
            onSave: saveCustomer
        });
    }
    
    // Logo Drag & Drop Setup
    setupLogoUpload();
    
    const mlBtn = document.getElementById('openMediaLibraryBtn');
    if (mlBtn && typeof openMediaLibraryModal === 'function') {
        mlBtn.addEventListener('click', function() {
            openMediaLibraryModal({
                baseUrl: editBaseUrl,
                title: 'Kundenlogo aus Medienbibliothek',
                onSelect: applyLogoFromMediaLibrary
            });
        });
    }
    
    // Firmenauswahl aus localStorage/Nav setzen
    const companySelect = document.getElementById('company_id');
    const companyContainer = document.getElementById('companySelectContainer');
    
    if (companySelect) {
        const savedSelection = localStorage.getItem('selectedUserOption');
        let selectedCompanyId = null;
        
        if (savedSelection) {
            try {
                const data = JSON.parse(savedSelection);
                selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id) : null;
                if (selectedCompanyId && !customer.company_id) {
                    companySelect.value = selectedCompanyId;
                }
                if (selectedCompanyId && companyContainer) {
                    companyContainer.style.display = 'none';
                }
            } catch (e) {
                console.error('Fehler beim Laden der Firmenauswahl', e);
            }
        }
        
        window.addEventListener('companyChanged', function(e) {
            if (companySelect) {
                selectedCompanyId = e.detail.companyId;
                if (!customer.company_id) {
                    companySelect.value = selectedCompanyId || '';
                }
                if (selectedCompanyId && companyContainer) {
                    companyContainer.style.display = 'none';
                } else if (companyContainer) {
                    companyContainer.style.display = 'block';
                }
            }
        });
    }
}

function applyLogoFromMediaLibrary(relativePath) {
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logoPreview');
    const logoRemoveBtn = document.getElementById('logoRemoveBtn');
    if (!logoInput || !logoPreview) return;
    logoInput.value = relativePath;
    logoPreview.src = editBaseUrl.replace(/\/?$/, '/') + String(relativePath).replace(/^\//, '');
    if (logoRemoveBtn) logoRemoveBtn.classList.remove('hidden');
    logoInput.dispatchEvent(new Event('change', { bubbles: true }));
    if (typeof showToast === 'function') {
        showToast('Bild übernommen. Speichern Sie die Änderung.', 'success');
    }
}

function setupLogoUpload() {
    const dropZone = document.getElementById('logoDropZone');
    const dropZoneContent = document.getElementById('logoDropZoneContent');
    const fileInput = document.getElementById('logoFileInput');
    const logoPreview = document.getElementById('logoPreview');
    const logoInput = document.getElementById('logo');
    const logoRemoveBtn = document.getElementById('logoRemoveBtn');
    const uploadProgress = document.getElementById('logoUploadProgress');
    const progressBar = document.getElementById('logoProgressBar');
    const uploadStatus = document.getElementById('logoUploadStatus');
    
    if (!dropZone || !fileInput) return;
    
    dropZone.addEventListener('click', () => {
        fileInput.click();
    });
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary-500', 'dark:border-primary-400', 'bg-primary-50', 'dark:bg-primary-900');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleLogoFile(files[0]);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleLogoFile(e.target.files[0]);
        }
    });
    
    function handleLogoFile(file) {
        const maxSize = 5 * 1024 * 1024;
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        
        if (file.size > maxSize) {
            alert('Datei ist zu groß (max. 5MB)');
            return;
        }
        
        if (!allowedTypes.includes(file.type)) {
            alert('Nur Bildformate erlaubt (JPEG, PNG, GIF, WebP, SVG)');
            return;
        }
        
        if (logoInput) logoInput.value = '';
        
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        uploadLogo(file);
    }
    
    function uploadLogo(file) {
        const formData = new FormData();
        formData.append('logo', file);
        formData.append('customer_id', customerId);
        
        uploadProgress.classList.remove('hidden');
        progressBar.style.width = '0%';
        uploadStatus.textContent = 'Wird hochgeladen...';
        
        fetch(customersApiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                progressBar.style.width = '100%';
                uploadStatus.textContent = 'Logo erfolgreich hochgeladen!';
                logoInput.value = data.logo_path;
                logoRemoveBtn.classList.remove('hidden');
                
                logoPreview.src = editBaseUrl + data.logo_path;
                
                if (typeof showToast === 'function') {
                    showToast('Logo erfolgreich hochgeladen', 'success');
                }
                
                setTimeout(() => {
                    uploadProgress.classList.add('hidden');
                }, 2000);
            } else {
                uploadProgress.classList.add('hidden');
                if (typeof showToast === 'function') {
                    showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            uploadProgress.classList.add('hidden');
            if (typeof showToast === 'function') {
                showToast('Fehler beim Hochladen des Logos', 'error');
            } else {
                alert('Fehler beim Hochladen des Logos');
            }
        });
    }
    
    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener('click', removeLogo);
    }
    
    if (logoInput && logoInput.value) {
        logoRemoveBtn.classList.remove('hidden');
    }
}

function removeLogo() {
    if (!confirm('Möchten Sie das Logo wirklich entfernen?')) {
        return;
    }
    
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logoPreview');
    const logoRemoveBtn = document.getElementById('logoRemoveBtn');
    
    logoInput.value = '';
    logoPreview.src = editBaseUrl + 'assets/images/default-avatar.png';
    logoRemoveBtn.classList.add('hidden');
}

function toggleAnsprechpartnerType() {
    const type = document.querySelector('input[name="ansprechpartner_type"]:checked').value;
    const userContainer = document.getElementById('ansprechpartner_user_container');
    const manuellContainer = document.getElementById('ansprechpartner_manuell_container');
    const userSelect = document.getElementById('ansprechpartner_user_id');
    
    if (type === 'user') {
        userContainer.style.display = 'block';
        manuellContainer.style.display = 'none';
        // Manuelle Felder zurücksetzen
        if (document.getElementById('ansprechpartner_manuell_name')) {
            document.getElementById('ansprechpartner_manuell_name').value = '';
            document.getElementById('ansprechpartner_manuell_email').value = '';
            document.getElementById('ansprechpartner_manuell_telefon').value = '';
            document.getElementById('ansprechpartner_manuell_notiz').value = '';
        }
    } else {
        userContainer.style.display = 'none';
        manuellContainer.style.display = 'block';
        if (userSelect) userSelect.value = '';
    }
}

// User für Ansprechpartner basierend auf Firma laden
function loadAnsprechpartnerUsers(companyId, selectedUserId = null) {
    const userSelect = document.getElementById('ansprechpartner_user_id');
    if (!userSelect || !companyId) {
        if (userSelect) {
            userSelect.innerHTML = '<option value="">Keine Firma ausgewählt</option>';
        }
        return;
    }
    
    userSelect.innerHTML = '<option value="">Lade User...</option>';
    
    fetch(companiesApiUrl + '?company_id=' + companyId + '&users=1')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users) {
                userSelect.innerHTML = '<option value="">Kein Ansprechpartner</option>';
                data.users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim() || user.email;
                    option.textContent = fullName;
                    if (selectedUserId && parseInt(user.id) === parseInt(selectedUserId)) {
                        option.selected = true;
                    }
                    userSelect.appendChild(option);
                });
            } else {
                userSelect.innerHTML = '<option value="">Keine User verfügbar</option>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der User:', error);
            userSelect.innerHTML = '<option value="">Fehler beim Laden</option>';
        });
}

// Event Listener für Firmenauswahl
document.addEventListener('DOMContentLoaded', function() {
    const companySelect = document.getElementById('company_id');
    if (companySelect) {
        companySelect.addEventListener('change', function() {
            const selectedId = this.value ? parseInt(this.value) : null;
            if (selectedId) {
                loadAnsprechpartnerUsers(selectedId);
            } else {
                const userSelect = document.getElementById('ansprechpartner_user_id');
                if (userSelect) {
                    userSelect.innerHTML = '<option value="">Bitte zuerst eine Firma auswählen</option>';
                }
            }
        });
    }
});

function saveCustomer() {
    let companyId = null;
    const savedSelection = localStorage.getItem('selectedUserOption');
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            companyId = data.id && data.id !== '0' ? parseInt(data.id) : null;
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }
    
    if (!companyId) {
        const companySelect = document.getElementById('company_id');
        if (companySelect && companySelect.value) {
            companyId = parseInt(companySelect.value);
        }
    }
    
    const formData = {
        customer_id: customerId,
        name: document.getElementById('name').value.trim(),
        kundennummer: document.getElementById('kundennummer')?.value.trim() || null,
        email: document.getElementById('email').value.trim() || null,
        telefon: document.getElementById('telefon').value.trim() || null,
        adresse: document.getElementById('adresse').value.trim() || null,
        plz: document.getElementById('plz').value.trim() || null,
        ort: document.getElementById('ort').value.trim() || null,
        lieferadresse: document.getElementById('lieferadresse')?.value.trim() || null,
        liefer_plz: document.getElementById('liefer_plz')?.value.trim() || null,
        liefer_ort: document.getElementById('liefer_ort')?.value.trim() || null,
        rechnungs_adresse: document.getElementById('rechnungs_adresse')?.value.trim() || null,
        rechnungs_plz: document.getElementById('rechnungs_plz')?.value.trim() || null,
        rechnungs_ort: document.getElementById('rechnungs_ort')?.value.trim() || null,
        rechnungs_email: document.getElementById('rechnungs_email')?.value.trim() || null,
        company_id: companyId,
        logo: document.getElementById('logo').value.trim() || null,
        status: document.getElementById('status').value
    };
    
    // Ansprechpartner hinzufügen
    const ansprechpartnerType = document.querySelector('input[name="ansprechpartner_type"]:checked')?.value;
    if (ansprechpartnerType === 'user') {
        const userId = document.getElementById('ansprechpartner_user_id').value;
        if (userId) {
            formData.ansprechpartner_user_id = parseInt(userId);
            formData.ansprechpartner_manuell_name = null;
            formData.ansprechpartner_manuell_email = null;
            formData.ansprechpartner_manuell_telefon = null;
            formData.ansprechpartner_manuell_notiz = null;
        } else {
            formData.ansprechpartner_user_id = null;
            formData.ansprechpartner_manuell_name = null;
            formData.ansprechpartner_manuell_email = null;
            formData.ansprechpartner_manuell_telefon = null;
            formData.ansprechpartner_manuell_notiz = null;
        }
    } else if (ansprechpartnerType === 'manual') {
        const manuellName = document.getElementById('ansprechpartner_manuell_name').value.trim();
        if (manuellName) {
            formData.ansprechpartner_manuell_name = manuellName;
            formData.ansprechpartner_manuell_email = document.getElementById('ansprechpartner_manuell_email').value.trim() || null;
            formData.ansprechpartner_manuell_telefon = document.getElementById('ansprechpartner_manuell_telefon').value.trim() || null;
            formData.ansprechpartner_manuell_notiz = document.getElementById('ansprechpartner_manuell_notiz').value.trim() || null;
            formData.ansprechpartner_user_id = null;
        } else {
            formData.ansprechpartner_user_id = null;
            formData.ansprechpartner_manuell_name = null;
            formData.ansprechpartner_manuell_email = null;
            formData.ansprechpartner_manuell_telefon = null;
            formData.ansprechpartner_manuell_notiz = null;
        }
    }
    
    fetch(customersApiUrl, {
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
                showToast('Kunde erfolgreich aktualisiert', 'success');
            }
            window.location.reload();
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
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
