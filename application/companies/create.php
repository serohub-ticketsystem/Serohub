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

// Nur Admin und Techniker können Firmen erstellen
if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Mitarbeiter (Admin/Techniker) für Zuweisung laden
$mitarbeiter = [];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    $stmt = $pdo->query("SELECT id, vorname, nachname, email FROM users WHERE rolle IN ('Admin', 'Techniker') AND status = 'aktiv' ORDER BY nachname, vorname");
    $mitarbeiter = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Alle aktiven User für Ansprechpartner-Auswahl laden (bei Erstellung noch keine Firma vorhanden)
// Die Validierung erfolgt in der API
$allUsers = [];
$stmt = $pdo->query("SELECT id, vorname, nachname, email, company_id FROM users WHERE status = 'aktiv' ORDER BY nachname, vorname");
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Neue Firma</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Neue Firma</h1>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Erstellen Sie eine neue Firma für die Verwaltung</p>
                            </div>
                            <a href="<?php echo BASE_URL; ?>companies/" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Abbrechen
                            </a>
                        </div>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <form id="companyForm" class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                            <div class="p-4">
                                <!-- Grunddaten: Firmenname, Kundennummer, Status, Zugewiesener Mitarbeiter -->
                                <div class="mb-4 flex flex-col md:flex-row gap-3" style="overflow-x: hidden;">
                                    <div style="flex: 0 0 38%; min-width: 0;">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Firmenname *</label>
                                        <input type="text" id="name" name="name" required 
                                               placeholder="z.B. Beispiel GmbH"
                                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                    </div>
                                    <div style="flex: 0 0 9%; min-width: 0;">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kundennummer</label>
                                        <input type="text" id="kundennummer" name="kundennummer"
                                               placeholder="z.B. KND-001"
                                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                    </div>
                                    <div style="flex: 0 0 19%; min-width: 0;">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label>
                                        <select id="status" name="status" required
                                                class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="aktiv">Aktiv</option>
                                            <option value="inaktiv">Inaktiv</option>
                                            <option value="gesperrt">Gesperrt</option>
                                        </select>
                                    </div>
                                    <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                                    <div style="flex: 0 0 28%; min-width: 0;">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Zugewiesener Mitarbeiter</label>
                                        <select id="zugewiesen_an" name="zugewiesen_an"
                                                class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">Keine Zuweisung</option>
                                            <?php foreach ($mitarbeiter as $ma): ?>
                                                <option value="<?php echo $ma['id']; ?>">
                                                    <?php echo htmlspecialchars(trim(($ma['vorname'] ?? '') . ' ' . ($ma['nachname'] ?? '')) ?: $ma['email']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
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
                                            <input type="checkbox" id="hat_wartungsvertrag" name="hat_wartungsvertrag" value="1" class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:checked:bg-emerald-600">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Hat Wartungsvertrag</span>
                                        </label>
                                        <div id="wartung_rhythmus_container" class="hidden">
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Zahlungsrhythmus</label>
                                            <select id="wartung_zahlungsrhythmus" name="wartung_zahlungsrhythmus" class="w-44 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="">— Bitte wählen —</option>
                                                <option value="woechentlich">Wöchentlich</option>
                                                <option value="monatlich">Monatlich</option>
                                                <option value="vierteljaehrlich">Vierteljährlich</option>
                                                <option value="halbjaehrlich">Halbjährlich</option>
                                                <option value="jaehrlich">Jährlich</option>
                                            </select>
                                        </div>
                                        <div id="wartung_tag_container" class="hidden">
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Zahlungstag</label>
                                            <select id="wartung_zahlungstag" name="wartung_zahlungstag" class="w-44 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="">— Bitte wählen —</option>
                                            </select>
                                        </div>
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
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Firmenlogo</span>
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
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Domain</label>
                                                <input type="text" id="domain" name="domain"
                                                       placeholder="z.B. beispiel.de"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail-Adresse</label>
                                                <input type="email" id="email" name="email"
                                                       placeholder="info@beispiel.de"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Telefonnummer</label>
                                                <input type="tel" id="telefonnummer" name="telefonnummer"
                                                       placeholder="+49 123 456789"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rechnungs-E-Mail</label>
                                                <input type="email" id="rechnungs_email" name="rechnungs_email"
                                                       placeholder="rechnung@beispiel.de"
                                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                            </div>
                                            
                                            <!-- Ansprechpartner -->
                                            <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Ansprechpartner</label>
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
                                                        <option value="">Kein Ansprechpartner</option>
                                                        <?php foreach ($allUsers as $user): ?>
                                                            <option value="<?php echo $user['id']; ?>">
                                                                <?php echo htmlspecialchars(trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? '')) ?: $user['email']); ?>
                                                                <?php if ($user['company_id']): ?> (Firma-ID: <?php echo htmlspecialchars($user['company_id']); ?>)<?php endif; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hinweis: Der User muss zur erstellten Firma gehören</p>
                                                </div>
                                                <div id="ansprechpartner_manuell_container" style="display: none;">
                                                    <div class="space-y-2">
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
                                            <div class="p-1.5 bg-orange-100 dark:bg-orange-900 rounded-lg">
                                                <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
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
                                        Firma erstellen
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

// Logo-Upload und Wartungsvertrag beim Laden initialisieren
document.addEventListener('DOMContentLoaded', function() {
    setupLogoUpload();
    setupWartungsvertrag();
    
    const mlBtn = document.getElementById('openMediaLibraryBtn');
    if (mlBtn && typeof openMediaLibraryModal === 'function') {
        mlBtn.addEventListener('click', function() {
            openMediaLibraryModal({
                baseUrl: createBaseUrl,
                title: 'Firmenlogo aus Medienbibliothek',
                onSelect: applyLogoFromMediaLibrary
            });
        });
    }
});

document.getElementById('companyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Firma erstellen
    createCompany();
});

function createCompany() {
    const adresse = document.getElementById('adresse').value.trim() || null;
    const plz = document.getElementById('plz').value.trim() || null;
    const ort = document.getElementById('ort').value.trim() || null;
    let lieferadresse = document.getElementById('lieferadresse').value.trim() || null;
    let lieferPlz = document.getElementById('liefer_plz').value.trim() || null;
    let lieferOrt = document.getElementById('liefer_ort').value.trim() || null;
    let rechnungsAdresse = document.getElementById('rechnungs_adresse').value.trim() || null;
    let rechnungsPlz = document.getElementById('rechnungs_plz').value.trim() || null;
    let rechnungsOrt = document.getElementById('rechnungs_ort').value.trim() || null;
    // Liefer- und Rechnungsadresse optional: wenn leer, Hauptadresse übernehmen
    if (!lieferadresse) lieferadresse = adresse;
    if (!lieferPlz) lieferPlz = plz;
    if (!lieferOrt) lieferOrt = ort;
    if (!rechnungsAdresse) rechnungsAdresse = adresse;
    if (!rechnungsPlz) rechnungsPlz = plz;
    if (!rechnungsOrt) rechnungsOrt = ort;
    const formData = {
        name: document.getElementById('name').value.trim(),
        domain: document.getElementById('domain').value.trim() || null,
        kundennummer: document.getElementById('kundennummer').value.trim() || null,
        adresse: adresse,
        plz: plz,
        ort: ort,
        lieferadresse: lieferadresse,
        liefer_plz: lieferPlz,
        liefer_ort: lieferOrt,
        rechnungs_adresse: rechnungsAdresse,
        rechnungs_plz: rechnungsPlz,
        rechnungs_ort: rechnungsOrt,
        email: document.getElementById('email').value.trim() || null,
        telefonnummer: document.getElementById('telefonnummer').value.trim() || null,
        rechnungs_email: document.getElementById('rechnungs_email').value.trim() || null,
        zugewiesen_an: document.getElementById('zugewiesen_an')?.value || null,
        logo: document.getElementById('logo').value.trim() || null,
        status: document.getElementById('status').value,
        hat_wartungsvertrag: document.getElementById('hat_wartungsvertrag').checked ? 1 : 0
    };
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
    
    fetch(companiesApiUrl, {
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
                showToast('Firma erfolgreich erstellt', 'success');
            }
            const companyId = data.company_id;
            
            // Wenn Logo ausgewählt wurde, jetzt hochladen
            if (selectedLogoFile && companyId) {
                const logoFormData = new FormData();
                logoFormData.append('logo', selectedLogoFile);
                logoFormData.append('company_id', companyId);
                
                fetch(companiesApiUrl, {
                    method: 'POST',
                    body: logoFormData
                })
                .then(response => response.json())
                .then(logoData => {
                    if (logoData.success) {
                        window.location.href = '<?php echo BASE_URL; ?>companies/';
                    } else {
                        console.error('Fehler beim Logo-Upload:', logoData.error);
                        if (typeof showToast === 'function') {
                            showToast('Firma erstellt, aber Logo-Upload fehlgeschlagen: ' + (logoData.error || 'Unbekannter Fehler'), 'warning');
                        } else {
                            alert('Firma wurde erstellt, aber Logo-Upload fehlgeschlagen: ' + (logoData.error || 'Unbekannter Fehler'));
                        }
                        window.location.href = '<?php echo BASE_URL; ?>companies/';
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Logo-Upload:', error);
                    if (typeof showToast === 'function') {
                        showToast('Firma erstellt, aber Logo-Upload fehlgeschlagen', 'warning');
                    } else {
                        alert('Firma wurde erstellt, aber Logo-Upload fehlgeschlagen.');
                    }
                    window.location.href = '<?php echo BASE_URL; ?>companies/';
                });
            } else {
                window.location.href = '<?php echo BASE_URL; ?>companies/';
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
        alert('Fehler beim Erstellen der Firma');
    });
}
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
