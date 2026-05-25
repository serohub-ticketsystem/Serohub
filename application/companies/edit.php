<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$companyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$companyId) {
    header('Location: ' . BASE_URL . 'companies/');
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

// Nur Admin kann Firmen bearbeiten
if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Mitarbeiter (Admin/Techniker) für Zuweisung laden
$mitarbeiter = [];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    $stmt = $pdo->query("SELECT id, vorname, nachname, email FROM users WHERE rolle IN ('Admin', 'Techniker') AND status = 'aktiv' ORDER BY nachname, vorname");
    $mitarbeiter = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// User werden dynamisch per JavaScript geladen, basierend auf der Firma

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
                                    <a href="<?php echo BASE_URL; ?>companies/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 md:ms-2 dark:text-gray-400 dark:hover:text-white">Firmen</a>
                                </li>
                                <li aria-current="page">
                                    <div class="flex items-center">
                                        <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Firma bearbeiten</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Firma bearbeiten</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Bearbeiten Sie die Informationen der Firma</p>
                        </div>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <div id="companyContent" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                                <svg class="animate-spin h-8 w-8 text-gray-400 dark:text-gray-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="mt-2">Lade Firmendaten...</p>
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
const companyId = <?php echo $companyId; ?>;
// baseUrl wird bereits in nav.php definiert, daher nicht erneut deklarieren
// Falls baseUrl nicht existiert (z.B. wenn nav.php nicht geladen wurde), verwenden wir einen Fallback
const editBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>';
const companiesApiUrl = editBaseUrl + 'companies/api/companies.php';

document.addEventListener('DOMContentLoaded', function() {
    loadCompany();
});

function loadCompany() {
    fetch(companiesApiUrl + '?id=' + companyId)
        .then(response => {
            // Prüfe Content-Type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Ungültige Antwort von API:', text);
                    throw new Error('Server hat keine gültige JSON-Antwort zurückgegeben. Bitte überprüfen Sie die Server-Logs.');
                });
            }
            
            if (!response.ok) {
                // Versuche JSON-Fehler zu lesen
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
            if (data.success && data.company) {
                // Stelle sicher, dass alle Felder vorhanden sind
                const company = data.company;
                // Fehlende Felder mit null oder leeren Strings initialisieren
                company.domain = company.domain !== null && company.domain !== undefined ? company.domain : '';
                company.kundennummer = company.kundennummer !== null && company.kundennummer !== undefined ? company.kundennummer : '';
                company.adresse = company.adresse !== null && company.adresse !== undefined ? company.adresse : '';
                company.plz = company.plz !== null && company.plz !== undefined ? company.plz : '';
                company.ort = company.ort !== null && company.ort !== undefined ? company.ort : '';
                company.lieferadresse = company.lieferadresse !== null && company.lieferadresse !== undefined ? company.lieferadresse : '';
                company.liefer_plz = company.liefer_plz !== null && company.liefer_plz !== undefined ? company.liefer_plz : '';
                company.liefer_ort = company.liefer_ort !== null && company.liefer_ort !== undefined ? company.liefer_ort : '';
                company.rechnungs_adresse = company.rechnungs_adresse !== null && company.rechnungs_adresse !== undefined ? company.rechnungs_adresse : '';
                company.rechnungs_plz = company.rechnungs_plz !== null && company.rechnungs_plz !== undefined ? company.rechnungs_plz : '';
                company.rechnungs_ort = company.rechnungs_ort !== null && company.rechnungs_ort !== undefined ? company.rechnungs_ort : '';
                company.email = company.email !== null && company.email !== undefined ? company.email : '';
                company.telefonnummer = company.telefonnummer !== null && company.telefonnummer !== undefined ? company.telefonnummer : '';
                company.rechnungs_email = company.rechnungs_email !== null && company.rechnungs_email !== undefined ? company.rechnungs_email : '';
                company.zugewiesen_an = company.zugewiesen_an !== null && company.zugewiesen_an !== undefined ? company.zugewiesen_an : null;
                company.ansprechpartner_user_id = company.ansprechpartner_user_id !== null && company.ansprechpartner_user_id !== undefined ? company.ansprechpartner_user_id : null;
                company.ansprechpartner_manuell_name = company.ansprechpartner_manuell_name !== null && company.ansprechpartner_manuell_name !== undefined ? company.ansprechpartner_manuell_name : '';
                company.ansprechpartner_manuell_email = company.ansprechpartner_manuell_email !== null && company.ansprechpartner_manuell_email !== undefined ? company.ansprechpartner_manuell_email : '';
                company.ansprechpartner_manuell_telefon = company.ansprechpartner_manuell_telefon !== null && company.ansprechpartner_manuell_telefon !== undefined ? company.ansprechpartner_manuell_telefon : '';
                company.ansprechpartner_manuell_notiz = company.ansprechpartner_manuell_notiz !== null && company.ansprechpartner_manuell_notiz !== undefined ? company.ansprechpartner_manuell_notiz : '';
                company.logo = company.logo !== null && company.logo !== undefined ? company.logo : '';
                company.status = company.status || 'aktiv';
                company.hat_wartungsvertrag = (company.hat_wartungsvertrag != null && company.hat_wartungsvertrag !== undefined) ? company.hat_wartungsvertrag : 0;
                company.wartung_zahlungsrhythmus = company.wartung_zahlungsrhythmus != null && company.wartung_zahlungsrhythmus !== undefined ? company.wartung_zahlungsrhythmus : '';
                company.wartung_zahlungstag = company.wartung_zahlungstag != null && company.wartung_zahlungstag !== undefined ? company.wartung_zahlungstag : '';
                company.lager_zugriff = (company.lager_zugriff != null && company.lager_zugriff !== undefined) ? company.lager_zugriff : 0;
                company.is_primary = (company.is_primary != null && company.is_primary !== undefined) ? company.is_primary : 0;
                
                displayCompanyForm(company);
            } else {
                const errorMsg = data.error || 'Unbekannter Fehler';
                document.getElementById('companyContent').innerHTML = 
                    '<div class="p-6 text-red-500">Fehler beim Laden der Firmendaten: ' + escapeHtml(errorMsg) + '</div>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Firmendaten:', error);
            const errorMsg = error.message || 'Unbekannter Fehler';
            document.getElementById('companyContent').innerHTML = 
                '<div class="p-6 text-red-500">Fehler beim Laden der Firmendaten: ' + escapeHtml(errorMsg) + '<br><br>Bitte überprüfen Sie die Browser-Konsole für weitere Details.</div>';
        });
}

function displayCompanyForm(company) {
    const logoUrl = company.logo 
        ? (company.logo.startsWith('http') ? company.logo : editBaseUrl + company.logo)
        : editBaseUrl + 'assets/images/default-avatar.png';
    
    // User für Ansprechpartner laden (nachdem Formular erstellt wurde)
    setTimeout(() => {
        if (company.id) {
            loadAnsprechpartnerUsers(company.id, company.ansprechpartner_user_id);
        }
        fillWartungZahlungstag(company.wartung_zahlungsrhythmus || '');
        const tagSel = document.getElementById('wartung_zahlungstag');
        if (tagSel && company.wartung_zahlungstag !== '' && company.wartung_zahlungstag != null) {
            tagSel.value = String(company.wartung_zahlungstag);
        }
        setupWartungsvertrag();
    }, 100);
    
    document.getElementById('companyContent').innerHTML = `
        <form id="companyForm" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="p-4">
                <!-- Grunddaten: Firmenname, Kundennummer, Status, Zugewiesener Mitarbeiter -->
                <div class="mb-4 flex flex-col md:flex-row gap-3" style="overflow-x: hidden;">
                    <div style="flex: 0 0 38%; min-width: 0;">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Firmenname *</label>
                        <input type="text" id="name" name="name" required value="${escapeHtml(company.name)}"
                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div style="flex: 0 0 9%; min-width: 0;">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kundennummer</label>
                        <input type="text" id="kundennummer" name="kundennummer" value="${escapeHtml(company.kundennummer || '')}"
                               placeholder="z.B. KND-001"
                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                    </div>
                    <div style="flex: 0 0 19%; min-width: 0;">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label>
                        <select id="status" name="status" required
                                class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="aktiv" ${company.status === 'aktiv' ? 'selected' : ''}>Aktiv</option>
                            <option value="inaktiv" ${company.status === 'inaktiv' ? 'selected' : ''}>Inaktiv</option>
                            <option value="gesperrt" ${company.status === 'gesperrt' ? 'selected' : ''}>Gesperrt</option>
                        </select>
                    </div>
                    <div style="flex: 0 0 28%; min-width: 0;">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Zugewiesener Mitarbeiter</label>
                        <select id="zugewiesen_an" name="zugewiesen_an"
                                class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Keine Zuweisung</option>
                            ${<?php echo json_encode(array_map(function($ma) {
                                return [
                                    'id' => $ma['id'],
                                    'name' => trim(($ma['vorname'] ?? '') . ' ' . ($ma['nachname'] ?? '')) ?: $ma['email']
                                ];
                            }, $mitarbeiter)); ?>.map(ma => 
                                `<option value="${ma.id}" ${(company.zugewiesen_an != null && parseInt(company.zugewiesen_an) === parseInt(ma.id)) ? 'selected' : ''}>${escapeHtml(ma.name)}</option>`
                            ).join('')}
                        </select>
                    </div>
                </div>

                <div class="mb-4 border border-amber-200 dark:border-amber-800 rounded-lg p-4 bg-amber-50/50 dark:bg-amber-900/10">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                        </svg>
                        <span class="text-sm font-semibold text-gray-900 dark:text-primary-200">Primärfirma</span>
                    </div>
                    <label class="inline-flex items-start gap-2 cursor-pointer rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2.5 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <input type="checkbox" id="is_primary" name="is_primary" value="1" ${(company.is_primary == 1 || company.is_primary === true) ? 'checked' : ''}
                               class="w-4 h-4 mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-700">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Diese Firma ist die Primärfirma</span>
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Die Primärfirma kann nicht auf inaktiv/gesperrt gesetzt und nicht gelöscht werden.</p>
                </div>
                
                <!-- Wartungsvertrag (eigener Bereich) -->
                <div class="mb-4 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4 bg-emerald-50/50 dark:bg-emerald-900/10">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span class="text-sm font-semibold text-gray-900 dark:text-primary-200">Wartungsvertrag</span>
                    </div>
                    <div class="flex flex-wrap items-end gap-4">
                        <label class="inline-flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2.5 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors" title="Hat Wartungsvertrag">
                            <input type="checkbox" id="hat_wartungsvertrag" name="hat_wartungsvertrag" value="1" ${(company.hat_wartungsvertrag == 1 || company.hat_wartungsvertrag === true) ? 'checked' : ''}
                                   class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:checked:bg-emerald-600">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Hat Wartungsvertrag</span>
                        </label>
                        <div id="wartung_rhythmus_container" class="${(company.hat_wartungsvertrag == 1 || company.hat_wartungsvertrag === true) ? '' : 'hidden'}">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Zahlungsrhythmus</label>
                            <select id="wartung_zahlungsrhythmus" name="wartung_zahlungsrhythmus" class="w-44 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">— Bitte wählen —</option>
                                <option value="woechentlich" ${(company.wartung_zahlungsrhythmus === 'woechentlich') ? 'selected' : ''}>Wöchentlich</option>
                                <option value="monatlich" ${(company.wartung_zahlungsrhythmus === 'monatlich') ? 'selected' : ''}>Monatlich</option>
                                <option value="vierteljaehrlich" ${(company.wartung_zahlungsrhythmus === 'vierteljaehrlich') ? 'selected' : ''}>Vierteljährlich</option>
                                <option value="halbjaehrlich" ${(company.wartung_zahlungsrhythmus === 'halbjaehrlich') ? 'selected' : ''}>Halbjährlich</option>
                                <option value="jaehrlich" ${(company.wartung_zahlungsrhythmus === 'jaehrlich') ? 'selected' : ''}>Jährlich</option>
                            </select>
                        </div>
                        <div id="wartung_tag_container" class="${(company.hat_wartungsvertrag == 1 || company.hat_wartungsvertrag === true) ? '' : 'hidden'}">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Zahlungstag</label>
                            <select id="wartung_zahlungstag" name="wartung_zahlungstag" class="w-44 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">— Bitte wählen —</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Lager: Firmenzugriff -->
                <div class="mb-4 border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/20">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.005 11.19V12l6.998 4.042L19 12v-.81M5 16.15v.81L11.997 21l6.998-4.042v-.81M12.003 3 5.005 7.042l6.998 4.042L19 7.042 12.003 3Z"/>
                        </svg>
                        <span class="text-sm font-semibold text-gray-900 dark:text-primary-200">Lager</span>
                    </div>
                    <label class="inline-flex items-start gap-2 cursor-pointer rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2.5 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <input type="checkbox" id="lager_zugriff" name="lager_zugriff" value="1" ${(company.lager_zugriff == 1 || company.lager_zugriff === true) ? 'checked' : ''}
                               class="w-4 h-4 mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Benutzer dieser Firma dürfen das Lager einsehen (nur die der Firma zugeordneten Artikel)</span>
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Techniker und Admins sehen das Lager unabhängig davon immer. Wer den Bestand ein- oder auslagern darf, legen Sie pro Benutzer unter <strong>Administration → Benutzer</strong> fest.</p>
                </div>
                
                <!-- Logo und Informationen -->
                <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Logo Upload -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Firmenlogo</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <img id="logoPreview" src="${logoUrl}" alt="${escapeHtml(company.name)}" class="h-24 w-24 rounded-full object-cover border border-gray-200 dark:border-gray-700 mb-3">
                            <button type="button" id="logoRemoveBtn" onclick="removeLogo()" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 hidden mb-3">
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
                            <input type="hidden" id="logo" name="logo" value="${escapeHtml(company.logo || '')}">
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
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Domain</label>
                                <input type="text" id="domain" name="domain" value="${escapeHtml(company.domain || '')}"
                                       placeholder="z.B. beispiel.de"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail-Adresse</label>
                                <input type="email" id="email" name="email" value="${escapeHtml(company.email || '')}"
                                       placeholder="info@beispiel.de"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Telefonnummer</label>
                                <input type="tel" id="telefonnummer" name="telefonnummer" value="${escapeHtml(company.telefonnummer || '')}"
                                       placeholder="+49 123 456789"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rechnungs-E-Mail</label>
                                <input type="email" id="rechnungs_email" name="rechnungs_email" value="${escapeHtml(company.rechnungs_email || '')}"
                                       placeholder="rechnung@beispiel.de"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                            
                            <!-- Ansprechpartner -->
                            <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Ansprechpartner</label>
                                <div class="flex items-center gap-2 mb-2">
                                    <label class="flex items-center">
                                        <input type="radio" name="ansprechpartner_type" value="user" ${company.ansprechpartner_user_id ? 'checked' : (company.ansprechpartner_manuell_name ? '' : 'checked')} class="mr-2" onchange="toggleAnsprechpartnerType()">
                                        <span class="text-xs text-gray-700 dark:text-gray-300">User auswählen</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="ansprechpartner_type" value="manual" ${company.ansprechpartner_manuell_name ? 'checked' : ''} class="mr-2" onchange="toggleAnsprechpartnerType()">
                                        <span class="text-xs text-gray-700 dark:text-gray-300">Manuell eingeben</span>
                                    </label>
                                </div>
                                <div id="ansprechpartner_user_container" style="display: ${company.ansprechpartner_manuell_name ? 'none' : 'block'};">
                                    <select id="ansprechpartner_user_id" name="ansprechpartner_user_id"
                                            class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <option value="">Lade User...</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nur User dieser Firma werden angezeigt</p>
                                </div>
                                <div id="ansprechpartner_manuell_container" style="display: ${company.ansprechpartner_manuell_name ? 'block' : 'none'};">
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                                            <input type="text" id="ansprechpartner_manuell_name" name="ansprechpartner_manuell_name"
                                                   value="${escapeHtml(company.ansprechpartner_manuell_name || '')}"
                                                   placeholder="z.B. Max Mustermann"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail</label>
                                            <input type="email" id="ansprechpartner_manuell_email" name="ansprechpartner_manuell_email"
                                                   value="${escapeHtml(company.ansprechpartner_manuell_email || '')}"
                                                   placeholder="max.mustermann@beispiel.de"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Telefon</label>
                                            <input type="tel" id="ansprechpartner_manuell_telefon" name="ansprechpartner_manuell_telefon"
                                                   value="${escapeHtml(company.ansprechpartner_manuell_telefon || '')}"
                                                   placeholder="+49 123 456789"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Notiz</label>
                                            <textarea id="ansprechpartner_manuell_notiz" name="ansprechpartner_manuell_notiz" rows="2"
                                                      placeholder="Zusätzliche Informationen..."
                                                      class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">${escapeHtml(company.ansprechpartner_manuell_notiz || '')}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Adress-Cards: Adresse, Lieferung, Rechnung -->
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
                            <input type="text" id="adresse" name="adresse" value="${escapeHtml(company.adresse || '')}"
                                   placeholder="Straße und Hausnummer"
                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" id="plz" name="plz" value="${escapeHtml(company.plz || '')}"
                                       placeholder="PLZ"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <input type="text" id="ort" name="ort" value="${escapeHtml(company.ort || '')}"
                                       placeholder="Ort"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lieferadresse -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-orange-100 dark:bg-orange-900 rounded-lg">
                                <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Lieferadresse</h3>
                        </div>
                        <div class="space-y-2">
                            <input type="text" id="lieferadresse" name="lieferadresse" value="${escapeHtml(company.lieferadresse || '')}"
                                   placeholder="Straße und Hausnummer"
                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" id="liefer_plz" name="liefer_plz" value="${escapeHtml(company.liefer_plz || '')}"
                                       placeholder="PLZ"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <input type="text" id="liefer_ort" name="liefer_ort" value="${escapeHtml(company.liefer_ort || '')}"
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
                            <input type="text" id="rechnungs_adresse" name="rechnungs_adresse" value="${escapeHtml(company.rechnungs_adresse || '')}"
                                   placeholder="Straße und Hausnummer"
                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" id="rechnungs_plz" name="rechnungs_plz" value="${escapeHtml(company.rechnungs_plz || '')}"
                                       placeholder="PLZ"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <input type="text" id="rechnungs_ort" name="rechnungs_ort" value="${escapeHtml(company.rechnungs_ort || '')}"
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
    document.getElementById('companyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveCompany();
    });
    
    // Nav-Unsaved-Changes (modular): Banner bei Änderung, Speichern/Verwerfen in der Nav
    if (window.NavUnsavedChanges) {
        NavUnsavedChanges.init({
            form: 'companyForm',
            discardUrl: editBaseUrl + 'companies/',
            onSave: saveCompany
        });
    }
    
    // Logo Drag & Drop Setup
    setupLogoUpload();
    
    const mlBtn = document.getElementById('openMediaLibraryBtn');
    if (mlBtn && typeof openMediaLibraryModal === 'function') {
        mlBtn.addEventListener('click', function() {
            openMediaLibraryModal({
                baseUrl: editBaseUrl,
                title: 'Firmenlogo aus Medienbibliothek',
                onSelect: applyLogoFromMediaLibrary
            });
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
    
    // Klick auf Drop-Zone
    dropZone.addEventListener('click', () => {
        fileInput.click();
    });
    
    // Drag & Drop Events
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
    
    // Datei ausgewählt
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleLogoFile(e.target.files[0]);
        }
    });
    
    function handleLogoFile(file) {
        // Validierung
        const maxSize = 5 * 1024 * 1024; // 5MB
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
        
        // Vorschau anzeigen
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        // Upload starten
        uploadLogo(file);
    }
    
    function uploadLogo(file) {
        const formData = new FormData();
        formData.append('logo', file);
        formData.append('company_id', companyId);
        
        uploadProgress.classList.remove('hidden');
        progressBar.style.width = '0%';
        uploadStatus.textContent = 'Wird hochgeladen...';
        
        fetch(companiesApiUrl, {
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
                
                // Vorschau aktualisieren
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
    
    // Logo entfernen
    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener('click', removeLogo);
    }
    
    // Entfernen-Button anzeigen wenn Logo vorhanden
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
        document.getElementById('ansprechpartner_manuell_name').value = '';
        document.getElementById('ansprechpartner_manuell_email').value = '';
        document.getElementById('ansprechpartner_manuell_telefon').value = '';
        document.getElementById('ansprechpartner_manuell_notiz').value = '';
    } else {
        userContainer.style.display = 'none';
        manuellContainer.style.display = 'block';
        userSelect.value = '';
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

function fillWartungZahlungstag(rhythm) {
    const sel = document.getElementById('wartung_zahlungstag');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Bitte wählen —</option>';
    if (rhythm === 'woechentlich') {
        const tage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        tage.forEach((label, i) => {
            const opt = document.createElement('option');
            opt.value = String(i + 1);
            opt.textContent = label;
            sel.appendChild(opt);
        });
    } else if (rhythm && rhythm !== '') {
        for (let i = 1; i <= 31; i++) {
            const opt = document.createElement('option');
            opt.value = String(i);
            opt.textContent = i + '.';
            sel.appendChild(opt);
        }
    }
}

function setupWartungsvertrag() {
    const cb = document.getElementById('hat_wartungsvertrag');
    const rhythmContainer = document.getElementById('wartung_rhythmus_container');
    const tagContainer = document.getElementById('wartung_tag_container');
    const rhythmSelect = document.getElementById('wartung_zahlungsrhythmus');
    const tagSelect = document.getElementById('wartung_zahlungstag');
    if (!cb || !rhythmContainer || !tagContainer) return;
    function updateVisibility() {
        const show = cb.checked;
        rhythmContainer.classList.toggle('hidden', !show);
        tagContainer.classList.toggle('hidden', !show);
        if (!show) {
            rhythmSelect.value = '';
            tagSelect.innerHTML = '<option value="">— Bitte wählen —</option>';
        } else {
            fillWartungZahlungstag(rhythmSelect.value);
        }
    }
    cb.addEventListener('change', updateVisibility);
    rhythmSelect.addEventListener('change', function() {
        fillWartungZahlungstag(this.value);
        tagSelect.value = '';
    });
    updateVisibility();
}

function saveCompany() {
    const formData = {
        company_id: companyId,
        name: document.getElementById('name').value.trim(),
        domain: document.getElementById('domain').value.trim() || null,
        kundennummer: document.getElementById('kundennummer').value.trim() || null,
        adresse: document.getElementById('adresse').value.trim() || null,
        plz: document.getElementById('plz').value.trim() || null,
        ort: document.getElementById('ort').value.trim() || null,
        lieferadresse: document.getElementById('lieferadresse').value.trim() || null,
        liefer_plz: document.getElementById('liefer_plz').value.trim() || null,
        liefer_ort: document.getElementById('liefer_ort').value.trim() || null,
        rechnungs_adresse: document.getElementById('rechnungs_adresse').value.trim() || null,
        rechnungs_plz: document.getElementById('rechnungs_plz').value.trim() || null,
        rechnungs_ort: document.getElementById('rechnungs_ort').value.trim() || null,
        email: document.getElementById('email').value.trim() || null,
        telefonnummer: document.getElementById('telefonnummer').value.trim() || null,
        rechnungs_email: document.getElementById('rechnungs_email').value.trim() || null,
        zugewiesen_an: document.getElementById('zugewiesen_an').value || null,
        logo: document.getElementById('logo').value.trim() || null,
        status: document.getElementById('status').value,
        is_primary: document.getElementById('is_primary')?.checked ? 1 : 0,
        hat_wartungsvertrag: document.getElementById('hat_wartungsvertrag').checked ? 1 : 0
    };
    const lagerZugriffEl = document.getElementById('lager_zugriff');
    if (lagerZugriffEl) {
        formData.lager_zugriff = lagerZugriffEl.checked ? 1 : 0;
    }
    const wartungRhythm = document.getElementById('wartung_zahlungsrhythmus')?.value || null;
    const wartungTag = document.getElementById('wartung_zahlungstag')?.value || null;
    if (wartungRhythm) formData.wartung_zahlungsrhythmus = wartungRhythm;
    if (wartungTag) formData.wartung_zahlungstag = parseInt(wartungTag, 10);
    
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
    
    fetch(companiesApiUrl, {
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
                showToast('Firma erfolgreich aktualisiert', 'success');
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
