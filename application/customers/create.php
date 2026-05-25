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
    header('Location: ' . BASE_URL . 'customers/');
    exit;
}

// Nur Firmen-Admin, Admin und Techniker können Kunden verwalten
if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
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

// User werden dynamisch per JavaScript geladen, basierend auf der ausgewählten Firma

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
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Neuer Kunde</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Neuer Kunde</h1>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Erstellen Sie einen neuen Kunden für die Verwaltung</p>
                            </div>
                            <a href="<?php echo BASE_URL; ?>customers/" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Abbrechen
                            </a>
                        </div>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <form id="customerForm" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="p-4">
                                <!-- Grunddaten: Name, Kundennummer, Status -->
                                <div class="mb-4 grid grid-cols-12 gap-3">
                                    <div class="col-span-12 md:col-span-6">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                                        <input type="text" id="name" name="name" required 
                                               placeholder="z.B. Max Mustermann"
                                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                    </div>
                                    <div class="col-span-12 md:col-span-3">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kundennummer</label>
                                        <input type="text" id="kundennummer" name="kundennummer" 
                                               placeholder="z.B. KND-12345"
                                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                    </div>
                                    <div class="col-span-12 md:col-span-3">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label>
                                        <select id="status" name="status" required
                                                class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="aktiv">Aktiv</option>
                                            <option value="inaktiv">Inaktiv</option>
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
                                            <img id="logoPreview" src="<?php echo BASE_URL; ?>assets/images/default-avatar.png" alt="Logo" class="h-24 w-24 rounded-full object-cover border border-gray-200 dark:border-gray-700 mb-3" style="display: none;">
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
                                            <input type="hidden" id="logo" name="logo" value="">
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
                                                    <option value="">-- Keine Firma --</option>
                                                    <?php if (count($companies) > 0): ?>
                                                        <?php foreach ($companies as $company): ?>
                                                            <option value="<?php echo $company['id']; ?>" <?php echo ($userCompanyId == $company['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($company['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Ansprechpartner</label>
                                                <div class="flex items-center gap-2 mb-2">
                                                    <label class="flex items-center">
                                                        <input type="radio" name="ansprechpartner_type" value="user" checked class="mr-2" onchange="toggleAnsprechpartnerType()">
                                                        <span class="text-xs text-gray-700 dark:text-gray-300">User auswählen</span>
                                                    </label>
                                                    <label class="flex items-center">
                                                        <input type="radio" name="ansprechpartner_type" value="manual" class="mr-2" onchange="toggleAnsprechpartnerType()">
                                                        <span class="text-xs text-gray-700 dark:text-gray-300">Manuell eingeben</span>
                                                    </label>
                                                </div>
                                                <div id="ansprechpartner_user_container">
                                                    <select id="ansprechpartner_user_id" name="ansprechpartner_user_id"
                                                            class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                        <option value="">Bitte zuerst eine Firma auswählen</option>
                                                    </select>
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nur User der ausgewählten Firma werden angezeigt</p>
                                                </div>
                                                <div id="ansprechpartner_manuell_container" style="display: none;">
                                                    <div class="space-y-3">
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                                                            <input type="text" id="ansprechpartner_manuell_name" name="ansprechpartner_manuell_name"
                                                                   placeholder="z.B. Max Mustermann"
                                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail</label>
                                                            <input type="email" id="ansprechpartner_manuell_email" name="ansprechpartner_manuell_email"
                                                                   placeholder="max.mustermann@beispiel.de"
                                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Telefon</label>
                                                            <input type="tel" id="ansprechpartner_manuell_telefon" name="ansprechpartner_manuell_telefon"
                                                                   placeholder="+49 123 456789"
                                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Notiz</label>
                                                            <textarea id="ansprechpartner_manuell_notiz" name="ansprechpartner_manuell_notiz" rows="2"
                                                                      placeholder="Zusätzliche Informationen..."
                                                                      class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail-Adresse</label>
                                                <input type="email" id="email" name="email"
                                                       placeholder="info@beispiel.de"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Telefonnummer</label>
                                                <input type="tel" id="telefon" name="telefon"
                                                       placeholder="+49 123 456789"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rechnungs-E-Mail</label>
                                                <input type="email" id="rechnungs_email" name="rechnungs_email"
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
                                            <input type="text" id="adresse" name="adresse" required
                                                   placeholder="Straße und Hausnummer"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="text" id="plz" name="plz" required
                                                       placeholder="PLZ"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                <input type="text" id="ort" name="ort" required
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
                                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Lieferadresse (optional)</h3>
                                        </div>
                                        <div class="space-y-2">
                                            <input type="text" id="lieferadresse" name="lieferadresse"
                                                   placeholder="Straße und Hausnummer"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="text" id="liefer_plz" name="liefer_plz"
                                                       placeholder="PLZ"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                <input type="text" id="liefer_ort" name="liefer_ort"
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
                                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Rechnungsadresse (optional)</h3>
                                        </div>
                                        <div class="space-y-2">
                                            <input type="text" id="rechnungs_adresse" name="rechnungs_adresse"
                                                   placeholder="Straße und Hausnummer"
                                                   class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="text" id="rechnungs_plz" name="rechnungs_plz"
                                                       placeholder="PLZ"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                                <input type="text" id="rechnungs_ort" name="rechnungs_ort"
                                                       placeholder="Ort"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Buttons -->
                                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <button type="submit" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12 4a1 1 0 0 1 1v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6V5a1 1 0 0 1 1Z" clip-rule="evenodd"/>
                                        </svg>
                                        Kunde erstellen
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/media-library-modal.js"></script>
<script>
const customersApiUrl = '<?php echo BASE_URL; ?>customers/api/customers.php';
const companiesApiUrl = '<?php echo BASE_URL; ?>companies/api/companies.php';
const createBaseUrl = '<?php echo BASE_URL; ?>';

// Logo-Upload Setup
let selectedLogoFile = null;

function applyLogoFromMediaLibrary(relativePath) {
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logoPreview');
    const logoRemoveBtn = document.getElementById('logoRemoveBtn');
    const fileInput = document.getElementById('logoFileInput');
    selectedLogoFile = null;
    if (fileInput) fileInput.value = '';
    if (logoInput) logoInput.value = relativePath;
    if (logoPreview) {
        logoPreview.src = createBaseUrl.replace(/\/?$/, '/') + String(relativePath).replace(/^\//, '');
        logoPreview.style.display = 'block';
    }
    if (logoRemoveBtn) logoRemoveBtn.classList.remove('hidden');
    if (typeof showToast === 'function') {
        showToast('Bild ausgewählt. Wird beim Speichern übernommen.', 'success');
    }
}

function setupLogoUpload() {
    const dropZone = document.getElementById('logoDropZone');
    const fileInput = document.getElementById('logoFileInput');
    const logoPreview = document.getElementById('logoPreview');
    const logoRemoveBtn = document.getElementById('logoRemoveBtn');
    
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
        
        // Datei speichern für späteren Upload
        selectedLogoFile = file;
        const logoHidden = document.getElementById('logo');
        if (logoHidden) logoHidden.value = '';
        
        // Vorschau anzeigen
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.src = e.target.result;
            logoPreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
        
        logoRemoveBtn.classList.remove('hidden');
    }
    
    // Logo entfernen
    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener('click', removeLogo);
    }
}

function removeLogo() {
    if (!confirm('Möchten Sie das Logo wirklich entfernen?')) {
        return;
    }
    
    const logoPreview = document.getElementById('logoPreview');
    const logoRemoveBtn = document.getElementById('logoRemoveBtn');
    const fileInput = document.getElementById('logoFileInput');
    
    selectedLogoFile = null;
    fileInput.value = '';
    const logoHidden = document.getElementById('logo');
    if (logoHidden) logoHidden.value = '';
    logoPreview.src = createBaseUrl + 'assets/images/default-avatar.png';
    logoPreview.style.display = 'none';
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
            userSelect.innerHTML = '<option value="">Bitte zuerst eine Firma auswählen</option>';
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

// Logo-Upload beim Laden initialisieren
document.addEventListener('DOMContentLoaded', function() {
    setupLogoUpload();
    
    const mlBtn = document.getElementById('openMediaLibraryBtn');
    if (mlBtn && typeof openMediaLibraryModal === 'function') {
        mlBtn.addEventListener('click', function() {
            openMediaLibraryModal({
                baseUrl: createBaseUrl,
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
                if (selectedCompanyId) {
                    companySelect.value = selectedCompanyId;
                    // Firmenauswahl ausblenden, wenn Firma in Nav gesetzt ist
                    if (companyContainer) {
                        companyContainer.style.display = 'none';
                    }
                }
            } catch (e) {
                console.error('Fehler beim Laden der Firmenauswahl', e);
            }
        }
        
        // Event Listener für Firmenwechsel (aus Nav)
        window.addEventListener('companyChanged', function(e) {
            if (companySelect) {
                selectedCompanyId = e.detail.companyId;
                companySelect.value = selectedCompanyId || '';
                // Firmenauswahl ausblenden, wenn Firma in Nav gesetzt ist
                if (selectedCompanyId && companyContainer) {
                    companyContainer.style.display = 'none';
                } else if (companyContainer) {
                    companyContainer.style.display = 'block';
                }
                // User für Ansprechpartner laden
                if (selectedCompanyId) {
                    loadAnsprechpartnerUsers(selectedCompanyId);
                }
            }
        });
        
        // Event Listener für Firmenauswahl im Dropdown
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
            
            // Wenn bereits eine Firma ausgewählt ist, User laden
            if (companySelect.value) {
                loadAnsprechpartnerUsers(parseInt(companySelect.value));
            }
        }
    }
});

document.getElementById('customerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Firma aus Nav oder Dropdown verwenden
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
    
    // Falls keine Firma in Nav gesetzt, aus Dropdown nehmen
    if (!companyId) {
        const companySelect = document.getElementById('company_id');
        if (companySelect && companySelect.value) {
            companyId = parseInt(companySelect.value);
        }
    }
    
    const adresse = document.getElementById('adresse').value.trim() || null;
    const plz = document.getElementById('plz').value.trim() || null;
    const ort = document.getElementById('ort').value.trim() || null;
    let lieferadresse = document.getElementById('lieferadresse')?.value.trim() || null;
    let lieferPlz = document.getElementById('liefer_plz')?.value.trim() || null;
    let lieferOrt = document.getElementById('liefer_ort')?.value.trim() || null;
    let rechnungsAdresse = document.getElementById('rechnungs_adresse')?.value.trim() || null;
    let rechnungsPlz = document.getElementById('rechnungs_plz')?.value.trim() || null;
    let rechnungsOrt = document.getElementById('rechnungs_ort')?.value.trim() || null;
    // Liefer- und Rechnungsadresse optional: wenn leer, Hauptadresse übernehmen
    if (!lieferadresse && !lieferPlz && !lieferOrt) {
        lieferadresse = adresse;
        lieferPlz = plz;
        lieferOrt = ort;
    } else {
        if (!lieferadresse) lieferadresse = adresse;
        if (!lieferPlz) lieferPlz = plz;
        if (!lieferOrt) lieferOrt = ort;
    }
    if (!rechnungsAdresse && !rechnungsPlz && !rechnungsOrt) {
        rechnungsAdresse = adresse;
        rechnungsPlz = plz;
        rechnungsOrt = ort;
    } else {
        if (!rechnungsAdresse) rechnungsAdresse = adresse;
        if (!rechnungsPlz) rechnungsPlz = plz;
        if (!rechnungsOrt) rechnungsOrt = ort;
    }
    const formData = {
        name: document.getElementById('name').value.trim(),
        kundennummer: document.getElementById('kundennummer')?.value.trim() || null,
        email: document.getElementById('email').value.trim() || null,
        telefon: document.getElementById('telefon').value.trim() || null,
        adresse: adresse,
        plz: plz,
        ort: ort,
        lieferadresse: lieferadresse,
        liefer_plz: lieferPlz,
        liefer_ort: lieferOrt,
        rechnungs_adresse: rechnungsAdresse,
        rechnungs_plz: rechnungsPlz,
        rechnungs_ort: rechnungsOrt,
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
        }
    } else if (ansprechpartnerType === 'manual') {
        const manuellName = document.getElementById('ansprechpartner_manuell_name').value.trim();
        if (manuellName) {
            formData.ansprechpartner_manuell_name = manuellName;
            formData.ansprechpartner_manuell_email = document.getElementById('ansprechpartner_manuell_email').value.trim() || null;
            formData.ansprechpartner_manuell_telefon = document.getElementById('ansprechpartner_manuell_telefon').value.trim() || null;
            formData.ansprechpartner_manuell_notiz = document.getElementById('ansprechpartner_manuell_notiz').value.trim() || null;
            formData.ansprechpartner_user_id = null;
        }
    }
    
    fetch(customersApiUrl, {
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
                showToast('Kunde erfolgreich erstellt', 'success');
            }
            const customerId = data.customer_id;
            
            // Wenn neue Datei gewählt wurde, nach Erstellung hochladen (Pfad aus Medienbibliothek ist bereits in logo gespeichert)
            if (selectedLogoFile && customerId) {
                const logoFormData = new FormData();
                logoFormData.append('logo', selectedLogoFile);
                logoFormData.append('customer_id', customerId);
                
                fetch(customersApiUrl, {
                    method: 'POST',
                    body: logoFormData
                })
                .then(response => response.json())
                .then(logoData => {
                    if (logoData.success) {
                        window.location.href = '<?php echo BASE_URL; ?>customers/';
                    } else {
                        console.error('Fehler beim Logo-Upload:', logoData.error);
                        if (typeof showToast === 'function') {
                            showToast('Kunde erstellt, aber Logo-Upload fehlgeschlagen: ' + (logoData.error || 'Unbekannter Fehler'), 'warning');
                        } else {
                            alert('Kunde wurde erstellt, aber Logo-Upload fehlgeschlagen: ' + (logoData.error || 'Unbekannter Fehler'));
                        }
                        window.location.href = '<?php echo BASE_URL; ?>customers/';
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Logo-Upload:', error);
                    if (typeof showToast === 'function') {
                        showToast('Kunde erstellt, aber Logo-Upload fehlgeschlagen', 'warning');
                    } else {
                        alert('Kunde wurde erstellt, aber Logo-Upload fehlgeschlagen.');
                    }
                    window.location.href = '<?php echo BASE_URL; ?>customers/';
                });
            } else {
                window.location.href = '<?php echo BASE_URL; ?>customers/';
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
            showToast('Fehler beim Erstellen des Kunden', 'error');
        } else {
            alert('Fehler beim Erstellen des Kunden');
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
