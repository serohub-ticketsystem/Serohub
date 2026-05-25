<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/customers/helper/encryption.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
requireLogin();

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
    // Firmen-User kann nur für sich selbst erstellen, aber Kunden der Firma sehen
    if ($userCompanyId) {
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv'");
        $stmt->execute([$userCompanyId]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($companies as &$co) { decrypt_company_row($co); }
        unset($co);
        
        // Kunden der Firma und Kunden ohne Firma laden
        $stmt = $pdo->prepare("SELECT id, name, email, company_id FROM customers WHERE (company_id = ? OR company_id IS NULL) AND status = 'aktiv' ORDER BY name");
        $stmt->execute([$userCompanyId]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($customers as &$c) { decrypt_customer_row($c); }
        unset($c);
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
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Neues Gerät</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Neues Gerät</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Erstellen Sie ein neues Gerät für die Verwaltung</p>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <form id="deviceForm" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                            <!-- Gerätename und Beschreibung -->
                            <div class="mb-6 grid grid-cols-5 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gerätename *</label>
                                    <input type="text" id="name" name="name" required 
                                           placeholder="z.B. PC-001"
                                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Standort / Notiz</label>
                                    <textarea id="beschreibung" name="beschreibung" rows="1"
                                              
                                              class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white resize-none"
                                              style="min-height: 42px; height: 42px;"></textarea>
                                </div>
                            </div>

                            <!-- Gerätetyp-Auswahl in Card-Form -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gerätetyp *</label>
                                <div class="grid grid-cols-6 gap-4">
                                    <div class="device-type-card cursor-pointer transition-all border-2 border-gray-300 dark:border-gray-600 rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                                         data-type="drucker"
                                         onclick="toggleDeviceTypeCard(this, 'drucker')">
                                        <input type="radio" name="typ" value="drucker" class="device-type-radio hidden" required>
                                        <div class="flex flex-col items-center text-center">
                                            <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                            <span class="font-medium text-gray-900 dark:text-white">Drucker</span>
                                        </div>
                                    </div>
                                    <div class="device-type-card cursor-pointer transition-all border-2 border-gray-300 dark:border-gray-600 rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                                         data-type="computer"
                                         onclick="toggleDeviceTypeCard(this, 'computer')">
                                        <input type="radio" name="typ" value="computer" class="device-type-radio hidden" required>
                                        <div class="flex flex-col items-center text-center">
                                            <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <span class="font-medium text-gray-900 dark:text-white">Computer</span>
                                        </div>
                                    </div>
                                    <div class="device-type-card cursor-pointer transition-all border-2 border-gray-300 dark:border-gray-600 rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                                         data-type="netzwerk"
                                         onclick="toggleDeviceTypeCard(this, 'netzwerk')">
                                        <input type="radio" name="typ" value="netzwerk" class="device-type-radio hidden" required>
                                        <div class="flex flex-col items-center text-center">
                                            <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                            </svg>
                                            <span class="font-medium text-gray-900 dark:text-white">Netzwerkgerät</span>
                                        </div>
                                    </div>
                                    <div class="device-type-card cursor-pointer transition-all border-2 border-gray-300 dark:border-gray-600 rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                                         data-type="smartphone"
                                         onclick="toggleDeviceTypeCard(this, 'smartphone')">
                                        <input type="radio" name="typ" value="smartphone" class="device-type-radio hidden" required>
                                        <div class="flex flex-col items-center text-center">
                                            <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            <span class="font-medium text-gray-900 dark:text-white">Smartphone</span>
                                        </div>
                                    </div>
                                    <div class="device-type-card cursor-pointer transition-all border-2 border-gray-300 dark:border-gray-600 rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                                         data-type="monitor"
                                         onclick="toggleDeviceTypeCard(this, 'monitor')">
                                        <input type="radio" name="typ" value="monitor" class="device-type-radio hidden" required>
                                        <div class="flex flex-col items-center text-center">
                                            <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <span class="font-medium text-gray-900 dark:text-white">Monitor</span>
                                        </div>
                                    </div>
                                    <div class="device-type-card cursor-pointer transition-all border-2 border-gray-300 dark:border-gray-600 rounded-lg p-4 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md" 
                                         data-type="divers"
                                         onclick="toggleDeviceTypeCard(this, 'divers')">
                                        <input type="radio" name="typ" value="divers" class="device-type-radio hidden" required>
                                        <div class="flex flex-col items-center text-center">
                                            <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                            <span class="font-medium text-gray-900 dark:text-white">Divers</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Wählen Sie einen Gerätetyp aus.
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
                                                <input type="text" id="hersteller" name="hersteller" autocomplete="off"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                <div id="hersteller-suggestions" class="hidden absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto"></div>
                                            </div>
                                            <div class="relative col-span-1">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Modell</label>
                                                <input type="text" id="modell" name="modell" autocomplete="off"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                <div id="modell-suggestions" class="hidden absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto"></div>
                                            </div>
                                            <div class="col-span-2">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Seriennummer</label>
                                                <input type="text" id="seriennummer" name="seriennummer"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Betriebssystem</label>
                                            <input type="text" id="betriebssystem" name="betriebssystem"
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
                                    <div id="detailFieldsContainer" class="hidden border-t border-gray-300 dark:border-gray-600 p-4">
                                        <div id="detailFields" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <!-- Wird dynamisch gefüllt -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Firmen-Auswahl (nur für Admin und Techniker) -->
                            <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                            <div class="mb-6" id="companySelectContainer">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Firma *</label>
                                <select id="companySelect" name="company_id" required
                                        class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">-- Bitte wählen Sie eine Firma --</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo htmlspecialchars($company['id']); ?>" 
                                                <?php if (count($companies) === 1): ?>selected<?php endif; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Wählen Sie eine Firma aus, für die das Gerät erstellt werden soll.
                                </p>
                            </div>
                            <?php else: ?>
                            <!-- Verstecktes Feld für Firmen-Admin, Firmen-User und Kunde -->
                            <input type="hidden" id="companySelect" name="company_id" value="<?php echo $userCompanyId ? htmlspecialchars($userCompanyId) : ''; ?>">
                            <?php endif; ?>

                            <!-- Kunden-Auswahl in Tabelle -->
                            <div class="mb-6 <?php 
                                // Nur anzeigen, wenn Kunden vorhanden sind
                                $hasCustomers = false;
                                if ($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') {
                                    $hasCustomers = !empty($customers) && count($customers) > 0;
                                } elseif ($userRole === 'Admin' || $userRole === 'Techniker') {
                                    $hasCustomers = !empty($customers) && count($customers) > 0;
                                }
                                
                                // Für Firmen-Admin und Firmen-User anzeigen, wenn Kunden vorhanden, sonst versteckt
                                // Für Admin/Techniker versteckt (wird per JS angezeigt)
                                if (($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && $hasCustomers) {
                                    echo '';
                                } else {
                                    echo 'hidden';
                                }
                            ?>" id="customerSelectContainer">
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

                            <!-- Benutzer-Auswahl (für Admin, Techniker und Firmen-Admin, erscheint wenn Firma/Kunde ausgewählt) -->
                            <div class="mb-6 <?php 
                                // Für Admin, Techniker und Firmen-Admin sichtbar (wird per JS angezeigt), für alle anderen versteckt
                                if ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin') {
                                    echo 'hidden';
                                } else {
                                    echo 'hidden';
                                }
                            ?>" id="userSelectContainer">
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

                            <!-- Buttons -->
                            <div class="flex justify-end space-x-4">
                                <a href="<?php echo BASE_URL; ?>devices/" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Abbrechen
                                </a>
                                <button type="submit" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
                                    <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12 4a1 1 0 0 1 1v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6V5a1 1 0 0 1 1Z" clip-rule="evenodd"/>
                                    </svg>
                                    Gerät erstellen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const devicesApiUrl = '<?php echo BASE_URL; ?>devices/api/devices.php';
const customersApiUrl = '<?php echo BASE_URL; ?>customers/api/customers.php';
const userRole = '<?php echo $userRole; ?>';
const userCustomerId = <?php echo isset($userCustomerId) && $userCustomerId ? (int)$userCustomerId : 'null'; ?>;
let allUsers = <?php echo json_encode($users); ?>;
const allCompanies = <?php echo json_encode($companies); ?>;
// Für Firmen-Admin: Benutzer aus PHP initialisieren
<?php if ($userRole === 'Firmen-Admin' && !empty($users)): ?>
allUsers = <?php echo json_encode($users); ?>;
<?php endif; ?>

// Kunden aus PHP initialisieren (falls bereits geladen, z.B. für Firmen-Admin)
let allCustomers = <?php echo json_encode($customers); ?>;
let filteredCustomers = allCustomers ? [...allCustomers] : [];
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
        // Verzögern, damit Click-Events auf Vorschläge funktionieren
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
    document.getElementById(inputId).value = value;
    document.getElementById(suggestionsId).classList.add('hidden');
    
    // Wenn Hersteller ausgewählt wurde, Modelle für diesen Hersteller laden
    if (inputId === 'hersteller') {
        selectedManufacturer = value;
        loadModels(value).then(() => {
            // Modell-Autocomplete aktualisieren
            const modellInput = document.getElementById('modell');
            if (modellInput.value.length > 0) {
                modellInput.dispatchEvent(new Event('input'));
            }
        });
    }
}

// MAC-Adresse Formatierung
function setupMacAddressInputs() {
    const macInputs = ['mac_adresse_1', 'mac_adresse_2', 'mac_adresse_3', 'mac_adresse_4', 'mac_adresse_5', 'mac_adresse_6'];
    
    macInputs.forEach((inputId, index) => {
        const input = document.getElementById(inputId);
        
        // Nur Hex-Zeichen erlauben
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9A-Fa-f]/g, '').toUpperCase();
            
            // Automatisch zum nächsten Feld springen
            if (this.value.length === 2 && index < macInputs.length - 1) {
                document.getElementById(macInputs[index + 1]).focus();
            }
        });
        
        // Zurück zum vorherigen Feld bei Backspace
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                document.getElementById(macInputs[index - 1]).focus();
            }
        });
        
        // MAC-Adresse zusammenfügen
        input.addEventListener('input', updateMacAddress);
    });
}

function updateMacAddress() {
    const macParts = [
        document.getElementById('mac_adresse_1').value,
        document.getElementById('mac_adresse_2').value,
        document.getElementById('mac_adresse_3').value,
        document.getElementById('mac_adresse_4').value,
        document.getElementById('mac_adresse_5').value,
        document.getElementById('mac_adresse_6').value
    ];
    
    const macAddress = macParts.filter(part => part.length > 0).join(':');
    document.getElementById('mac_adresse').value = macAddress || null;
}

// IP-Adresse Formatierung
function setupIpAddressInputs() {
    const ipInputs = ['ip_adresse_1', 'ip_adresse_2', 'ip_adresse_3', 'ip_adresse_4'];
    
    ipInputs.forEach((inputId, index) => {
        const input = document.getElementById(inputId);
        
        // Nur Zahlen erlauben
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Validierung: Maximal 255
            if (parseInt(this.value) > 255) {
                this.value = '255';
            }
            
            // Automatisch zum nächsten Feld springen
            if (this.value.length === 3 && index < ipInputs.length - 1) {
                document.getElementById(ipInputs[index + 1]).focus();
            }
        });
        
        // Zurück zum vorherigen Feld bei Backspace
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                document.getElementById(ipInputs[index - 1]).focus();
            }
        });
        
        // IP-Adresse zusammenfügen
        input.addEventListener('input', updateIpAddress);
    });
}

function updateIpAddress() {
    const ipParts = [
        document.getElementById('ip_adresse_1').value,
        document.getElementById('ip_adresse_2').value,
        document.getElementById('ip_adresse_3').value,
        document.getElementById('ip_adresse_4').value
    ];
    
    const ipAddress = ipParts.filter(part => part.length > 0).join('.');
    document.getElementById('ip_adresse').value = ipAddress || null;
}

// Gerätetyp-Card-Toggle
function toggleDeviceTypeCard(card, type) {
    // Alle Cards zurücksetzen
    document.querySelectorAll('.device-type-card').forEach(c => {
        c.classList.remove('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900/20');
        c.classList.add('border-gray-300', 'dark:border-gray-600');
    });
    
    // Ausgewählte Card markieren
    card.classList.remove('border-gray-300', 'dark:border-gray-600');
    card.classList.add('border-primary-500', 'bg-primary-50', 'dark:bg-primary-900/20');
    
    // Radio-Button setzen
    const radio = card.querySelector('.device-type-radio');
    radio.checked = true;
    
    // Detailfelder aktualisieren
    updateDetailFields(type);
}

// Detailfelder ein-/ausklappen
function toggleDetailFields() {
    const container = document.getElementById('detailFieldsContainer');
    const icon = document.getElementById('detailFieldsIcon');
    container.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
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

function updateDetailFields(deviceType) {
    const fieldsDiv = document.getElementById('detailFields');
    
    if (!deviceType || !deviceTypeFields[deviceType]) {
        fieldsDiv.innerHTML = '';
        return;
    }
    
    fieldsDiv.innerHTML = deviceTypeFields[deviceType].map(field => {
        if (field.type === 'select') {
            return `
                <div>
                    <label for="detail_${field.key}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        ${field.label}
                    </label>
                    <select id="detail_${field.key}" name="detail_${field.key}"
                            class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                        <option value="">-- Bitte wählen --</option>
                        ${field.options.map(opt => `<option value="${opt}">${opt}</option>`).join('')}
                    </select>
                </div>
            `;
        } else {
            return `
                <div>
                    <label for="detail_${field.key}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        ${field.label}
                    </label>
                    <input type="${field.type}" id="detail_${field.key}" name="detail_${field.key}"
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
    let selectedCompanyId = null;
    const savedSelection = localStorage.getItem('selectedUserOption');
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id) : null;
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }
    
    let url = customersApiUrl;
    if (selectedCompanyId && (userRole === 'Admin' || userRole === 'Techniker')) {
        url += '?company_id=' + selectedCompanyId;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customers) {
                allCustomers = data.customers;
                filteredCustomers = [...allCustomers];
                renderCustomerTable(filteredCustomers);
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kunden:', error);
        });
}

function renderCustomerTable(customers) {
    const tbody = document.getElementById('customerTableBody');
    tbody.innerHTML = customers.map(customer => {
        const searchText = `${customer.name} ${customer.email || ''} ${customer.company_name || ''}`.toLowerCase();
        return `
            <tr class="customer-row cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700" 
                data-search="${searchText}"
                data-customer-id="${customer.id}"
                onclick="toggleCustomerRow(this)">
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    <input type="radio" name="customer_id" value="${customer.id}" 
                           class="customer-radio hidden">
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
    
    // Benutzer-Auswahl anzeigen (für Admin, Techniker und Firmen-Admin)
    const userContainer = document.getElementById('userSelectContainer');
    
    if (userRole === 'Admin' || userRole === 'Techniker' || userRole === 'Firmen-Admin') {
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
        
        if (companyId && userContainer) {
            userContainer.classList.remove('hidden');
            loadUsersForCompany(companyId);
        } else {
            if (userContainer) {
                userContainer.classList.add('hidden');
            }
        }
        } else {
            // Für Firmen-User und Kunde: Benutzer-Auswahl verstecken
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
    
    renderCustomerTable(filteredCustomers);
}

// Funktion zum Prüfen und Anzeigen/Verstecken von Formular-Elementen basierend auf Firmenauswahl
function checkCompanySelection() {
    // Prüfen ob Firma in Navigation ausgewählt ist
    const savedSelection = localStorage.getItem('selectedUserOption');
    let navCompanyId = null;
    
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            // Nur wenn eine spezifische Firma ausgewählt ist (nicht "Alle Kunden")
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
    
    const formDiv = document.getElementById('deviceForm');
    const customerContainer = document.getElementById('customerSelectContainer');
    const userContainer = document.getElementById('userSelectContainer');
    
    // Rollenbasierte Anzeige/Verstecken
    // Admin und Techniker: Firmenauswahl anzeigen/verstecken basierend auf Nav
    if (userRole === 'Admin' || userRole === 'Techniker') {
        // Firmenauswahl verstecken, wenn in Nav eine Firma ausgewählt ist
        if (navCompanyId && companySelectContainer) {
            companySelectContainer.classList.add('hidden');
            // Firma im Dropdown setzen (auch wenn versteckt)
            if (companySelect) {
                companySelect.value = navCompanyId;
            }
        } else if (companySelectContainer) {
            companySelectContainer.classList.remove('hidden');
        }
        
        // Wenn keine Firma ausgewählt ist
        if (!selectedCompanyId) {
            if (formDiv) {
                formDiv.style.opacity = '1';
                formDiv.style.pointerEvents = 'auto';
            }
            if (customerContainer) {
                customerContainer.classList.add('hidden');
            }
            if (userContainer) {
                userContainer.classList.add('hidden');
            }
            // Kunde und Benutzer zurücksetzen
            deselectCustomer();
            return false;
        } else {
            if (formDiv) {
                formDiv.style.opacity = '1';
                formDiv.style.pointerEvents = 'auto';
            }
            // Kunden für die ausgewählte Firma laden
            loadCustomersForCompany(selectedCompanyId, function(customers) {
                // Callback: Nur anzeigen, wenn Kunden vorhanden
                if (customers && customers.length > 0 && customerContainer) {
                    customerContainer.classList.remove('hidden');
                } else if (customerContainer) {
                    customerContainer.classList.add('hidden');
                }
            });
            // Benutzer für die ausgewählte Firma laden (sofort anzeigen)
            loadUsersForCompany(selectedCompanyId);
            // Benutzer-Auswahl anzeigen, wenn Firma ausgewählt ist
            if (userContainer) {
                userContainer.classList.remove('hidden');
            }
            return true;
        }
    } 
    // Firmen-Admin: Nur Kundenauswahl anzeigen, wenn Kunden vorhanden
    else if (userRole === 'Firmen-Admin') {
        if (selectedCompanyId) {
            if (formDiv) {
                formDiv.style.opacity = '1';
                formDiv.style.pointerEvents = 'auto';
            }
            // Wenn bereits Kunden aus PHP geladen wurden, diese verwenden
            if (allCustomers && allCustomers.length > 0) {
                filteredCustomers = [...allCustomers];
                renderCustomerTable(filteredCustomers);
                // Kundenauswahl anzeigen, da Kunden vorhanden
                if (customerContainer) {
                    customerContainer.classList.remove('hidden');
                }
            } else {
                // Ansonsten über API laden
                loadCustomersForCompany(selectedCompanyId, function(customers) {
                    // Callback: Nur anzeigen, wenn Kunden vorhanden
                    if (customers && customers.length > 0 && customerContainer) {
                        customerContainer.classList.remove('hidden');
                    } else if (customerContainer) {
                        customerContainer.classList.add('hidden');
                    }
                });
            }
            // Benutzer-Auswahl anzeigen für Firmen-Admin (wenn Firma vorhanden)
            if (selectedCompanyId && userContainer) {
                userContainer.classList.remove('hidden');
                loadUsersForCompany(selectedCompanyId);
            } else if (userContainer) {
                userContainer.classList.add('hidden');
            }
            return true;
        } else {
            if (customerContainer) {
                customerContainer.classList.add('hidden');
            }
            if (userContainer) {
                userContainer.classList.add('hidden');
            }
            return false;
        }
    }
    // Firmen-User: Nur Kundenauswahl anzeigen, wenn Kunden vorhanden
    else if (userRole === 'Firmen-User') {
        if (selectedCompanyId) {
            if (formDiv) {
                formDiv.style.opacity = '1';
                formDiv.style.pointerEvents = 'auto';
            }
            // Wenn bereits Kunden aus PHP geladen wurden, diese verwenden
            if (allCustomers && allCustomers.length > 0) {
                filteredCustomers = [...allCustomers];
                renderCustomerTable(filteredCustomers);
                // Kundenauswahl anzeigen, da Kunden vorhanden
                if (customerContainer) {
                    customerContainer.classList.remove('hidden');
                }
            } else {
                // Ansonsten über API laden
                loadCustomersForCompany(selectedCompanyId, function(customers) {
                    // Callback: Nur anzeigen, wenn Kunden vorhanden
                    if (customers && customers.length > 0 && customerContainer) {
                        customerContainer.classList.remove('hidden');
                    } else if (customerContainer) {
                        customerContainer.classList.add('hidden');
                    }
                });
            }
            // Benutzer-Auswahl verstecken für Firmen-User
            if (userContainer) {
                userContainer.classList.add('hidden');
            }
            return true;
        } else {
            if (customerContainer) {
                customerContainer.classList.add('hidden');
            }
            if (userContainer) {
                userContainer.classList.add('hidden');
            }
            return false;
        }
    }
    // Kunde: Alle Container verstecken
    else {
        if (customerContainer) {
            customerContainer.classList.add('hidden');
        }
        if (userContainer) {
            userContainer.classList.add('hidden');
        }
        return false;
    }
}

// Kunden für eine bestimmte Firma laden
function loadCustomersForCompany(companyId, callback) {
    let url = customersApiUrl;
    if (companyId && (userRole === 'Admin' || userRole === 'Techniker')) {
        url += '?company_id=' + companyId;
    } else if (userRole === 'Firmen-Admin' || userRole === 'Firmen-User') {
        // Für Firmen-Admin und Firmen-User: API verwendet automatisch user_company_id, company_id Parameter ist optional
        if (companyId) {
            url += '?company_id=' + companyId;
        }
        // Wenn kein companyId, lädt die API automatisch Kunden der eigenen Firma
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customers) {
                allCustomers = data.customers;
                filteredCustomers = [...allCustomers];
                renderCustomerTable(filteredCustomers);
                // Callback aufrufen, wenn vorhanden
                if (callback && typeof callback === 'function') {
                    callback(data.customers);
                }
            } else {
                console.error('Fehler beim Laden der Kunden:', data.error || 'Unbekannter Fehler');
                // Callback auch bei Fehler aufrufen
                if (callback && typeof callback === 'function') {
                    callback([]);
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kunden:', error);
            // Callback auch bei Fehler aufrufen
            if (callback && typeof callback === 'function') {
                callback([]);
            }
        });
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
                renderUserTable(filteredUsers);
            } else {
                // Fallback: Benutzer aus PHP-Variable verwenden (wenn verfügbar)
                if (allUsers && allUsers.length > 0) {
                    allUsersList = allUsers;
                    filteredUsers = [...allUsersList];
                    renderUserTable(filteredUsers);
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
                renderUserTable(filteredUsers);
            } else {
                allUsersList = [];
                filteredUsers = [];
                renderUserTable([]);
            }
        });
}

function loadUsers(companyId) {
    // Alias für loadUsersForCompany für Kompatibilität
    loadUsersForCompany(companyId);
}

function renderUserTable(users) {
    const tbody = document.getElementById('userTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = users.map(user => {
        const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim();
        const searchText = `${fullName} ${user.email || ''}`.toLowerCase();
        return `
            <tr class="user-row cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700" 
                data-search="${searchText}"
                data-user-id="${user.id}"
                onclick="toggleUserRow(this)">
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    <input type="radio" name="user_id" value="${user.id}" 
                           class="user-radio hidden">
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
    
    renderUserTable(filteredUsers);
}

// Kunden-Suche
document.addEventListener('DOMContentLoaded', function() {
    // Event-Listener für Firmenauswahl im Formular
    const companySelect = document.getElementById('companySelect');
    if (companySelect) {
        companySelect.addEventListener('change', function() {
            checkCompanySelection();
        });
        
        // Wenn nur eine Firma verfügbar ist und noch keine ausgewählt, automatisch auswählen
        // Nur wenn keine Firma in Nav ausgewählt ist
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
        
        if (!navCompanyId && companySelect.options.length === 2 && !companySelect.value) {
            companySelect.selectedIndex = 1;
        }
    }
    
    // Event-Listener für Firmenänderung in der Navigation
    window.addEventListener('storage', function(e) {
        if (e.key === 'selectedUserOption') {
            checkCompanySelection();
        }
    });
    
    // Für Firmen-Admin und Firmen-User: Kundenauswahl nur anzeigen, wenn Kunden vorhanden
    if (userRole === 'Firmen-Admin' || userRole === 'Firmen-User') {
        const companySelect = document.getElementById('companySelect');
        const companyId = companySelect ? parseInt(companySelect.value) : null;
        const customerContainer = document.getElementById('customerSelectContainer');
        
        // Benutzer-Auswahl für Firmen-Admin anzeigen, wenn Firma vorhanden
        const userContainer = document.getElementById('userSelectContainer');
        if (userRole === 'Firmen-Admin' && companyId && userContainer) {
            userContainer.classList.remove('hidden');
            loadUsersForCompany(companyId);
        } else if (userRole === 'Firmen-User' && userContainer) {
            userContainer.classList.add('hidden');
        }
        
        // Wenn bereits Kunden aus PHP geladen wurden, diese anzeigen
        if (allCustomers && allCustomers.length > 0) {
            filteredCustomers = [...allCustomers];
            renderCustomerTable(filteredCustomers);
            // Kundenauswahl anzeigen, da Kunden vorhanden
            if (customerContainer) {
                customerContainer.classList.remove('hidden');
            }
        } else if (companyId) {
            // Ansonsten über API laden
            loadCustomersForCompany(companyId, function(customers) {
                // Callback: Nur anzeigen, wenn Kunden vorhanden
                if (customers && customers.length > 0 && customerContainer) {
                    customerContainer.classList.remove('hidden');
                } else if (customerContainer) {
                    customerContainer.classList.add('hidden');
                }
            });
        } else {
            // Auch ohne companyId versuchen zu laden (API verwendet user_company_id)
            if (customerContainer) {
                loadCustomersForCompany(null, function(customers) {
                    // Callback: Nur anzeigen, wenn Kunden vorhanden
                    if (customers && customers.length > 0 && customerContainer) {
                        customerContainer.classList.remove('hidden');
                    } else if (customerContainer) {
                        customerContainer.classList.add('hidden');
                    }
                });
            }
        }
    }
    
    // Initiale Prüfung (nach Firmen-Admin Initialisierung)
    checkCompanySelection();
    
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
    
    // MAC- und IP-Adressen Formatierung initialisieren
    setupMacAddressInputs();
    setupIpAddressInputs();
    
    const customerSearch = document.getElementById('customerSearch');
    if (customerSearch) {
        customerSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            filteredCustomers = allCustomers.filter(customer => {
                const searchText = `${customer.name} ${customer.email || ''} ${customer.company_name || ''}`.toLowerCase();
                return searchText.includes(searchTerm);
            });
            renderCustomerTable(filteredCustomers);
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
            renderUserTable(filteredUsers);
        });
    }
    
    // Firmenauswahl ausblenden wenn Firma in Nav gesetzt ist
    const savedSelection = localStorage.getItem('selectedUserOption');
    let selectedCompanyId = null;
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id) : null;
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Formular-Submit
document.getElementById('deviceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const deviceType = document.querySelector('input[name="typ"]:checked')?.value;
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
        name: document.getElementById('name').value,
        typ: deviceType || null,
        hersteller: document.getElementById('hersteller').value.trim() || null,
        modell: document.getElementById('modell').value.trim() || null,
        seriennummer: document.getElementById('seriennummer').value.trim() || null,
        mac_adresse: document.getElementById('mac_adresse').value || null,
        ip_adresse: document.getElementById('ip_adresse').value || null,
        betriebssystem: document.getElementById('betriebssystem').value.trim() || null,
        beschreibung: document.getElementById('beschreibung').value.trim() || null,
        status: 'aktiv',
        details: Object.keys(details).length > 0 ? details : null
    };
    
    // Firma ermitteln (aus Nav oder Dropdown)
    const savedSelection = localStorage.getItem('selectedUserOption');
    let companyId = null;
    
    // Für Firmen-Admin: Firma immer aus verstecktem Feld lesen
    if (userRole === 'Firmen-Admin') {
        const companySelect = document.getElementById('companySelect');
        if (companySelect && companySelect.value) {
            companyId = parseInt(companySelect.value);
        }
    } else {
        // Für Admin/Techniker: Firma aus Nav oder Dropdown
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
    }
    
    // Firma immer übergeben (für Firmen-Admin ist sie erforderlich)
    if (companyId) {
        formData.company_id = companyId;
    } else if (userRole === 'Firmen-Admin') {
        // Fehler: Firmen-Admin muss eine Firma haben
        alert('Fehler: Keine Firma zugeordnet. Bitte kontaktieren Sie den Administrator.');
        return;
    }
    
    // customer_id auswählen
    // Für Kunden: customer_id automatisch aus userCustomerId setzen
    if (userRole === 'Kunde' && userCustomerId) {
        formData.customer_id = userCustomerId;
    } else {
        // Für andere Rollen: customer_id aus Formular lesen
        const customerRadio = document.querySelector('input[name="customer_id"]:checked');
        const customerId = customerRadio ? customerRadio.value : null;
        if (customerId && customerId !== '') {
            formData.customer_id = parseInt(customerId);
        } else {
            // Explizit null setzen, wenn kein Kunde ausgewählt
            formData.customer_id = null;
        }
    }
    
    const userId = document.querySelector('input[name="user_id"]:checked')?.value;
    if (userId) {
        formData.user_id = parseInt(userId);
    }
    
    fetch(devicesApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Gerät erfolgreich erstellt', 'success');
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
            showToast('Fehler beim Erstellen des Geräts', 'error');
        } else {
            alert('Fehler beim Erstellen des Geräts');
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
