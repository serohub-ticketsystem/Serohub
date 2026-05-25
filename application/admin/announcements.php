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
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Nur Admin und Techniker können Ankündigungen verwalten
if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Firmen für Dropdown laden
$companies = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fehler beim Laden der Firmen: " . $e->getMessage());
    $companies = [];
}

// BASE_URL definieren falls nicht vorhanden
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
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
                                <li>
                                    <div class="flex items-center">
                                        <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                        <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
                                    </div>
                                </li>
                                <li aria-current="page">
                                    <div class="flex items-center">
                                        <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Ankündigungen</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Ankündigungen & Dashboard-Cards</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="createAnnouncementBtn" class="tab-btn-create flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none" data-tab="banner" style="display:flex;">
                        <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        Neue Ankündigung
                    </button>
                        <button type="button" id="createSurveyBtn" class="tab-btn-create flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none" data-tab="survey" style="display:none;">
                        <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                        Neue Umfrage
                    </button>
                        <button type="button" id="createCardBtn" class="tab-btn-create flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none" data-tab="cards" style="display:none;">
                        <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        Neue Dashboard-Card
                    </button>
                    </div>
                </div>
            
                <!-- Tabs -->
                <div class="col-span-full mx-4 mb-2">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex gap-6" aria-label="Tabs">
                            <button type="button" class="tab-nav border-b-2 border-primary-600 px-1 py-3 text-sm font-medium text-primary-600 dark:text-primary-400" data-tab="banner">
                                Ankündigungs-Banner
                            </button>
                            <button type="button" class="tab-nav border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" data-tab="cards">
                                Dashboard-Cards
                            </button>
                            <button type="button" class="tab-nav border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" data-tab="survey">
                                Zufriedenheitsumfrage
                            </button>
                        </nav>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" id="tabDescription">
                        Banner oben im System bearbeiten
                    </p>
                </div>
            
                <div class="col-span-full mx-4 mb-4" id="tabContentBanner">
            <div id="announcementsContainer" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="col-span-full flex items-center justify-center py-8" role="status">
                    <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-600 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                    </svg>
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
                </div>
            
                <div class="col-span-full mx-4 mb-4 hidden" id="tabContentSurvey">
                    <div class="relative">
                        <div class="flex items-center justify-center py-12 text-gray-500 dark:text-gray-400" id="surveyListLoading">
                            <svg class="animate-spin h-8 w-8 text-primary-500 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Lade Umfragen…
                        </div>
                        <div id="surveyListContainer" class="space-y-3 hidden"></div>
                    </div>
                </div>
            
                <div class="col-span-full mx-4 mb-4 hidden" id="tabContentCards">
                    <div id="cardsContainer" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div class="col-span-full flex items-center justify-center py-8" role="status">
                            <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-gray-600 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                            </svg>
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal für Erstellen/Bearbeiten -->
<div id="announcementModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="modalOverlay"></div>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full dark:bg-gray-800">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                        Ankündigung erstellen
                    </h3>
                    <button type="button" id="closeModalBtn" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none" onclick="closeModal();">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <form id="announcementForm">
                    <input type="hidden" id="announcementId" name="id" value="">
                    
                    <div class="mb-4">
                        <label for="titel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Titel * <span class="text-xs text-gray-500 dark:text-gray-400">(nur für intern)</span>
                        </label>
                        <input type="text" id="titel" name="titel" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    
                    <div class="mb-4">
                        <label for="nachricht" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nachricht *
                        </label>
                        <textarea id="nachricht" name="nachricht" rows="4" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="companyId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Firma (optional)
                        </label>
                        <select id="companyId" name="company_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Alle Firmen</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leer lassen für alle Firmen, oder spezifische Firma auswählen</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="linkText" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Link-Text (optional)
                            </label>
                            <input type="text" id="linkText" name="link_text"
                                   placeholder="z.B. Mehr erfahren"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="linkUrl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Link-URL (optional)
                            </label>
                            <input type="url" id="linkUrl" name="link_url"
                                   placeholder="https://example.com"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <label for="showBanner" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Banner anzeigen</div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Banner im System anzeigen</p>
                            </div>
                            <div class="relative inline-flex items-center">
                                <input type="checkbox" id="showBanner" name="show_banner" value="1" checked
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                            </div>
                        </label>
                        
                        <label for="aktiv" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Aktiv</div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Ankündigung aktivieren</p>
                            </div>
                            <div class="relative inline-flex items-center">
                                <input type="checkbox" id="aktiv" name="aktiv" value="1" checked
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                            </div>
                        </label>
                        
                        <label for="anonym" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Anonym</div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Zeigt „Serohub“</p>
                            </div>
                            <div class="relative inline-flex items-center">
                                <input type="checkbox" id="anonym" name="anonym" value="1"
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <button type="button" id="cancelBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Dashboard-Card erstellen/bearbeiten -->
<div id="cardModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="card-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="cardModalOverlay"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full dark:bg-gray-800">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="card-modal-title">
                        Dashboard-Card erstellen
                    </h3>
                    <button type="button" id="closeCardModalBtn" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form id="cardForm">
                    <input type="hidden" id="cardId" name="id" value="">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bilder (optional) – Light & Dark Mode</label>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4">
                                <div id="cardImagePreview" class="w-24 h-24 shrink-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 text-center px-1">Light</span>
                                </div>
                                <div class="flex-1">
                                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Light Mode</label>
                                    <input type="file" id="cardImageInput" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" class="block w-full mt-0.5 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-900 dark:file:text-primary-300">
                                    <input type="hidden" id="cardBild" name="bild" value="">
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div id="cardImageDarkPreview" class="w-24 h-24 shrink-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-700 dark:bg-gray-800 flex items-center justify-center overflow-hidden">
                                    <span class="text-xs text-gray-400 text-center px-1">Dark</span>
                                </div>
                                <div class="flex-1">
                                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Dark Mode</label>
                                    <input type="file" id="cardImageDarkInput" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" class="block w-full mt-0.5 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-900 dark:file:text-primary-300">
                                    <input type="hidden" id="cardBildDark" name="bild_dark" value="">
                                </div>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG, WebP, GIF oder SVG, max. 2 MB pro Bild. Beide optional – wenn nur eines gesetzt ist, wird es in beiden Modi genutzt.</p>
                    </div>
                    <div class="mb-4">
                        <label for="cardTitel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Titel *</label>
                        <input type="text" id="cardTitel" name="titel" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="z.B. Neue Funktion">
                    </div>
                    <div class="mb-4">
                        <label for="cardNachricht" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nachricht *</label>
                        <textarea id="cardNachricht" name="nachricht" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Beschreibungstext der Card"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="cardButtonText" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button-Text (optional)</label>
                            <input type="text" id="cardButtonText" name="button_text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="z.B. Mehr erfahren">
                        </div>
                        <div>
                            <label for="cardButtonLink" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button-Link (optional)</label>
                            <input type="url" id="cardButtonLink" name="button_link" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="https://...">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="cardTyp" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Typ</label>
                            <select id="cardTyp" name="typ" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="info">Info (grüner Button)</option>
                                <option value="warning">Warnung (blauer Button)</option>
                            </select>
                        </div>
                        <div>
                            <label for="cardCompanyId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Firma</label>
                            <select id="cardCompanyId" name="company_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Alle Firmen</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="cardSortOrder" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sortierung</label>
                            <input type="number" id="cardSortOrder" name="sort_order" value="0" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Niedriger = weiter oben</p>
                        </div>
                        <label for="cardAktiv" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Aktiv</span>
                            <div class="relative inline-flex items-center">
                                <input type="checkbox" id="cardAktiv" name="aktiv" value="1" checked class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                            </div>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="cardCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Neue Umfrage -->
<div id="surveyModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-black/50 dark:bg-black/70" aria-hidden="true" id="surveyModalOverlay"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4" id="surveyModalTitle">Neue Umfrage</h3>
            <div class="space-y-4">
                <div>
                    <label for="surveyModalTitel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Titel (intern)</label>
                    <input type="text" id="surveyModalTitel" placeholder="z.B. Q1 Serviceumfrage" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                <div>
                    <label for="surveyModalFrage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Frage *</label>
                    <input type="text" id="surveyModalFrage" placeholder="Wie zufrieden sind Sie mit unserem Service?" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                <div>
                    <label for="surveyModalCompanyId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Firma (optional)</label>
                    <select id="surveyModalCompanyId" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="">Alle Firmen</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer">
                    <input type="checkbox" id="surveyModalAktiv" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Aktiv</span>
                </label>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" id="surveyModalCancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 dark:text-gray-300">Abbrechen</button>
                <button type="button" id="surveyModalSave" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">Speichern</button>
            </div>
        </div>
    </div>
</div>

<script>
// baseUrl wird bereits in nav.php definiert
const surveyCompanies = <?php echo json_encode(array_values($companies)); ?>;

let currentTab = 'banner';

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function renderAnnouncement(announcement) {
    const statusBadge = announcement.aktiv 
        ? '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aktiv</span>'
        : '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Inaktiv</span>';
    
    const bannerBadge = announcement.show_banner
        ? '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Banner</span>'
        : '';
    
    const anonymBadge = announcement.anonym == 1
        ? '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Anonym</span>'
        : '';
    
    const companyBadge = announcement.company_id && announcement.company_name
        ? `<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">${announcement.company_name}</span>`
        : '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Alle Firmen</span>';
    
    const erstellerInfo = announcement.anonym == 1 
        ? '<span class="text-xs text-gray-500 dark:text-gray-400">Serohub</span>'
        : `<span class="text-xs text-gray-500 dark:text-gray-400">${announcement.ersteller_name || 'Unbekannt'}</span>`;
    
    return `
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-3" data-id="${announcement.id}">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                            ${announcement.titel}
                        </h3>
                        ${statusBadge}
                        ${bannerBadge}
                        ${anonymBadge}
                        ${companyBadge}
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2 line-clamp-2">
                        ${announcement.nachricht}
                    </p>
                    <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
                        <span>Von: ${erstellerInfo}</span>
                        ${announcement.link_text && announcement.link_url ? `<span>Link: <a href="${announcement.link_url}" target="_blank" class="text-primary-600 hover:underline">${announcement.link_text}</a></span>` : ''}
                        <span>${formatDate(announcement.erstellt_datum)}</span>
                    </div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <button onclick="editAnnouncement(${announcement.id})" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                        Bearbeiten
                    </button>
                    <button onclick="deleteAnnouncement(${announcement.id})" class="px-3 py-1.5 text-xs font-medium text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-50 dark:bg-gray-800 dark:text-red-300 dark:border-red-600 dark:hover:bg-red-700">
                        Löschen
                    </button>
                </div>
            </div>
        </div>
    `;
}

async function loadAnnouncements() {
    const container = document.getElementById('announcementsContainer');
    
    try {
        const response = await fetch(baseUrl + 'admin/api/announcements.php');
        const data = await response.json();
        
        if (data.success) {
            if (data.announcements.length === 0) {
                container.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500 dark:text-gray-400">Keine Ankündigungen vorhanden.</div>';
            } else {
                container.innerHTML = '';
                data.announcements.forEach(announcement => {
                    container.insertAdjacentHTML('beforeend', renderAnnouncement(announcement));
                });
            }
        } else {
        container.innerHTML = '<div class="col-span-full text-center py-8 text-red-500">Fehler beim Laden der Ankündigungen.</div>';
    }
} catch (error) {
    console.error('Fehler:', error);
    container.innerHTML = '<div class="col-span-full text-center py-8 text-red-500">Fehler beim Laden der Ankündigungen.</div>';
}
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-nav').forEach(btn => {
        const isActive = btn.dataset.tab === tab;
        btn.classList.toggle('border-primary-600', isActive);
        btn.classList.toggle('text-primary-600', isActive);
        btn.classList.toggle('dark:text-primary-400', isActive);
        btn.classList.toggle('border-transparent', !isActive);
        btn.classList.toggle('text-gray-500', !isActive);
        btn.classList.toggle('dark:text-gray-400', !isActive);
    });
    document.querySelectorAll('.tab-btn-create').forEach(btn => {
        btn.style.display = (btn.dataset.tab === tab) ? 'flex' : 'none';
    });
    document.getElementById('tabContentBanner').classList.toggle('hidden', tab !== 'banner');
    document.getElementById('tabContentCards').classList.toggle('hidden', tab !== 'cards');
    document.getElementById('tabContentSurvey').classList.toggle('hidden', tab !== 'survey');
    const descs = { banner: 'Banner oben im System bearbeiten', cards: 'Cards im Dashboard-Aktuelles-Bereich bearbeiten', survey: 'Zufriedenheitsumfrage konfigurieren' };
    document.getElementById('tabDescription').textContent = descs[tab] || '';
    if (tab === 'cards') loadCards();
    if (tab === 'survey') loadSurvey();
}

function escapeHtmlCard(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function renderCard(card) {
    const statusBadge = card.aktiv == 1 
        ? '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aktiv</span>'
        : '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Inaktiv</span>';
    const typBadge = card.typ === 'warning' ? '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Warnung</span>' : '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200">Info</span>';
    const companyBadge = card.company_id && card.company_name
        ? `<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">${escapeHtmlCard(card.company_name)}</span>`
        : '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Alle</span>';
    const imgUrl = card.bild ? (baseUrl + escapeHtmlCard(card.bild)) : '';
    const imgDarkUrl = card.bild_dark ? (baseUrl + escapeHtmlCard(card.bild_dark)) : '';
    const thumbHtml = imgUrl || imgDarkUrl ? `<img src="${imgUrl || imgDarkUrl}" alt="" class="w-full h-full object-cover rounded">` : '<span class="text-xs text-gray-400">Kein Bild</span>';
    return `
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" data-id="${card.id}">
            <div class="flex gap-4 p-3">
                <div class="w-20 h-20 shrink-0 rounded bg-gray-100 dark:bg-gray-700 flex items-center justify-center overflow-hidden">${thumbHtml}</div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">${escapeHtmlCard(card.titel)}</h3>
                        ${statusBadge} ${typBadge} ${companyBadge}
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">${escapeHtmlCard(card.nachricht)}</p>
                    ${card.button_text && card.button_link ? `<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Button: ${escapeHtmlCard(card.button_text)} → ${escapeHtmlCard(card.button_link)}</p>` : ''}
                </div>
                <div class="flex gap-2 shrink-0 self-center">
                    <button onclick="editCard(${card.id})" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">Bearbeiten</button>
                    <button onclick="deleteCard(${card.id})" class="px-3 py-1.5 text-xs font-medium text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-50 dark:bg-gray-800 dark:text-red-300 dark:border-red-600 dark:hover:bg-red-700">Löschen</button>
                </div>
            </div>
        </div>
    `;
}

function formatSurveyDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function loadSurvey() {
    const container = document.getElementById('surveyListContainer');
    const loading = document.getElementById('surveyListLoading');
    try {
        const response = await fetch(baseUrl + 'admin/api/satisfaction-survey.php');
        const data = await response.json();
        document.getElementById('surveyListLoading').classList.add('hidden');
        document.getElementById('surveyListContainer').classList.remove('hidden');
        if (!data.success || !data.surveys || data.surveys.length === 0) {
            container.innerHTML = '<div class="text-center py-12 text-gray-500 dark:text-gray-400">Keine Umfragen vorhanden. Erstellen Sie eine neue Umfrage.</div>';
            return;
        }
        container.innerHTML = data.surveys.map(s => renderSurveyRow(s)).join('');
        container.querySelectorAll('.survey-toggle').forEach(btn => {
            btn.addEventListener('click', () => toggleSurveyRow(btn.closest('.survey-row')));
        });
        container.querySelectorAll('.survey-save-btn').forEach(btn => {
            btn.addEventListener('click', (e) => { e.stopPropagation(); saveSurveyRow(parseInt(btn.dataset.surveyId)); });
        });
        container.querySelectorAll('.survey-delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => { e.stopPropagation(); deleteSurveyRow(parseInt(btn.dataset.surveyId)); });
        });
    } catch (e) {
        document.getElementById('surveyListLoading').classList.add('hidden');
        document.getElementById('surveyListContainer').classList.remove('hidden');
        container.innerHTML = '<div class="text-center py-12 text-red-500">Fehler beim Laden.</div>';
    }
}

function renderSurveyRow(s) {
    const aktivBadge = s.aktiv == 1 ? '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aktiv</span>' : '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Inaktiv</span>';
    const frageShort = (s.frage || '').length > 60 ? (s.frage || '').substring(0, 57) + '…' : (s.frage || '');
    const lastAt = formatSurveyDate(s.last_response_at);
    return `
    <div class="survey-row bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden" data-id="${s.id}">
        <button type="button" class="survey-toggle w-full px-4 py-4 flex items-center gap-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
            <svg class="survey-chevron w-5 h-5 text-gray-500 dark:text-gray-400 shrink-0 transition-transform -rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-0.5">
                    <h4 class="font-semibold text-gray-900 dark:text-white">${escapeHtmlCard(s.titel || 'Umfrage #' + s.id)}</h4>
                    ${aktivBadge}
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 truncate">${escapeHtmlCard(frageShort)}</p>
                <div class="flex gap-4 mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <span><strong>${s.total || 0}</strong> Antworten</span>
                    <span>Ø <strong>${s.total > 0 ? s.avg_rating : '—'}</strong></span>
                    <span>Letzte: ${lastAt}</span>
                </div>
            </div>
        </button>
        <div class="survey-body hidden border-t border-gray-200 dark:border-gray-700">
            <div class="p-4 grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" data-survey-stats="${s.id}">
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center"><p class="text-xl font-bold text-primary-600 dark:text-primary-400" data-total>${s.total || 0}</p><p class="text-xs text-gray-500 dark:text-gray-400">Antworten</p></div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center"><p class="text-xl font-bold text-gray-900 dark:text-white" data-avg>${s.total > 0 ? s.avg_rating : '—'}</p><p class="text-xs text-gray-500 dark:text-gray-400">Durchschn.</p></div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center"><p class="text-xl font-bold" data-promoter>—</p><p class="text-xs text-gray-500 dark:text-gray-400">Promoter %</p></div>
                        <div class="col-span-2 sm:col-span-1"></div>
                    </div>
                    <div data-survey-bars="${s.id}"><p class="text-xs text-gray-500 dark:text-gray-400">Details laden beim Öffnen…</p></div>
                    <div data-survey-responses="${s.id}" class="max-h-96 overflow-x-auto overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600"><p class="text-sm text-gray-500 dark:text-gray-400 italic p-3">—</p></div>
                </div>
                <div class="space-y-4">
                    <div><label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Titel</label><input type="text" class="survey-input-titel w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="${(s.titel || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}" data-survey-id="${s.id}"></div>
                    <div><label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Frage</label><input type="text" class="survey-input-frage w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="${(s.frage || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}" data-survey-id="${s.id}"></div>
                    <div><label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Firma</label><select class="survey-input-company w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" data-survey-id="${s.id}"><option value="">Alle Firmen</option>${(surveyCompanies||[]).map(c=>'<option value="'+c.id+'"'+(c.id==s.company_id?' selected':'')+'>'+escapeHtmlCard(c.name)+'</option>').join('')}</select></div>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" class="survey-input-aktiv rounded" ${s.aktiv == 1 ? 'checked' : ''} data-survey-id="${s.id}"><span class="text-sm text-gray-900 dark:text-white">Aktiv</span></label>
                    <div class="flex gap-2">
                        <button type="button" class="survey-save-btn px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700" data-survey-id="${s.id}">Speichern</button>
                        <button type="button" class="survey-delete-btn px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40" data-survey-id="${s.id}">Löschen</button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

function toggleSurveyRow(row) {
    if (!row) return;
    const body = row.querySelector('.survey-body');
    const chevron = row.querySelector('.survey-chevron');
    const expanded = !body.classList.contains('hidden');
    body.classList.toggle('hidden', expanded);
    chevron.classList.toggle('-rotate-90', expanded);
    if (!expanded) loadSurveyDetails(parseInt(row.dataset.id));
}

async function loadSurveyDetails(id) {
    try {
        const res = await fetch(baseUrl + 'admin/api/satisfaction-survey.php?id=' + id);
        const data = await res.json();
        if (!data.success || !data.survey || !data.stats) return;
        const s = data.survey; const st = data.stats; const d = st.distribution || {};
        const total = st.total || 0; const maxVal = Math.max(1, ...Object.values(d)); const r4 = d[4] || 0; const r5 = d[5] || 0;
        const promoterPct = total > 0 ? Math.round(((r4 + r5) / total) * 100) : 0;
        const statsEl = document.querySelector(`[data-survey-stats="${id}"]`);
        if (statsEl) {
            statsEl.querySelector('[data-total]').textContent = total;
            statsEl.querySelector('[data-avg]').textContent = total > 0 ? st.avg_rating : '—';
            statsEl.querySelector('[data-promoter]').textContent = total > 0 ? promoterPct + '%' : '—';
        }
        const barsEl = document.querySelector(`[data-survey-bars="${id}"]`);
        if (barsEl) {
            barsEl.innerHTML = [1,2,3,4,5].map(i => { const cnt = d[i] || 0; const pct = maxVal > 0 ? (cnt/maxVal)*100 : 0; const colors = ['bg-red-400','bg-orange-400','bg-yellow-400','bg-lime-400','bg-green-500']; return `<div class="flex items-center gap-2 text-sm mb-1"><span class="w-4">${i}</span><div class="flex-1 h-4 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden"><div class="h-full ${colors[i-1]} rounded-full" style="width:${pct}%"></div></div><span class="w-6">${cnt}</span></div>`; }).join('');
        }
        const respEl = document.querySelector(`[data-survey-responses="${id}"]`);
        const rows = st.responses_list || [];
        if (respEl) {
            if (rows.length === 0) {
                respEl.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 italic p-3">Noch keine Antworten</p>';
            } else {
                const head = '<thead><tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600"><th class="py-2 pr-3 pl-3">Name</th><th class="py-2 pr-3">E-Mail</th><th class="py-2 pr-3">Firma</th><th class="py-2 pr-3 w-16">Note</th><th class="py-2 pr-3 min-w-[8rem]">Kommentar</th><th class="py-2 pr-3 whitespace-nowrap">Abgegeben</th></tr></thead>';
                const body = '<tbody>' + rows.map(r => {
                    const fb = (r.feedback || '').trim();
                    const fbCell = fb ? `<span class="text-gray-900 dark:text-white">${escapeHtmlCard(fb)}</span>` : '<span class="text-gray-400 dark:text-gray-500">—</span>';
                    return `<tr class="border-b border-gray-100 dark:border-gray-700/80 text-sm align-top"><td class="py-2 pr-3 pl-3 font-medium text-gray-900 dark:text-white">${escapeHtmlCard(r.user_name || '—')}</td><td class="py-2 pr-3 text-gray-600 dark:text-gray-300 break-all">${escapeHtmlCard(r.user_email || '—')}</td><td class="py-2 pr-3 text-gray-700 dark:text-gray-300">${escapeHtmlCard(r.company_name || '—')}</td><td class="py-2 pr-3"><span class="font-semibold">${r.rating}</span>/5</td><td class="py-2 pr-3">${fbCell}</td><td class="py-2 pr-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">${formatSurveyDate(r.created_at)}</td></tr>`;
                }).join('') + '</tbody>';
                respEl.innerHTML = '<h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 px-3 pt-3 pb-1">Wer hat was abgegeben</h5><table class="w-full min-w-[36rem]">' + head + body + '</table>';
            }
        }
    } catch (e) { console.error('loadSurveyDetails', e); }
}

async function saveSurveyRow(id) {
    const row = document.querySelector(`.survey-row[data-id="${id}"]`); if (!row) return;
    const titel = row.querySelector('.survey-input-titel')?.value?.trim() || 'Umfrage'; const frage = row.querySelector('.survey-input-frage')?.value?.trim() || 'Wie zufrieden sind Sie?'; const companyId = row.querySelector('.survey-input-company')?.value || ''; const aktiv = row.querySelector('.survey-input-aktiv')?.checked;
    try {
        const res = await fetch(baseUrl + 'admin/api/satisfaction-survey.php', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, titel, frage, company_id: companyId ? parseInt(companyId) : null, aktiv: aktiv ? 1 : 0 }) });
        const data = await res.json();
        if (data.success) { loadSurvey(); if (typeof showToast === 'function') showToast('Gespeichert', 'success'); }
        else alert(data.error || 'Fehler');
    } catch (e) { alert('Fehler beim Speichern'); }
}

async function deleteSurveyRow(id) {
    if (!confirm('Umfrage und alle Antworten wirklich löschen?')) return;
    try {
        const res = await fetch(baseUrl + 'admin/api/satisfaction-survey.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
        const data = await res.json();
        if (data.success) { loadSurvey(); if (typeof showToast === 'function') showToast('Gelöscht', 'success'); }
        else alert(data.error || 'Fehler');
    } catch (e) { alert('Fehler'); }
}

function openSurveyModal(editId) {
    const modal = document.getElementById('surveyModal'); const titel = document.getElementById('surveyModalTitle'); const t = document.getElementById('surveyModalTitel'); const f = document.getElementById('surveyModalFrage'); const c = document.getElementById('surveyModalCompanyId'); const a = document.getElementById('surveyModalAktiv');
    if (editId) { titel.textContent = 'Umfrage bearbeiten'; fetch(baseUrl + 'admin/api/satisfaction-survey.php?id=' + editId).then(r=>r.json()).then(d=>{ if(d.success&&d.survey){ t.value=d.survey.titel||''; f.value=d.survey.frage||''; c.value=d.survey.company_id||''; a.checked=d.survey.aktiv==1; modal.dataset.editId=editId; } }); }
    else { titel.textContent = 'Neue Umfrage'; t.value=''; f.value='Wie zufrieden sind Sie mit unserem Service?'; c.value=''; a.checked=false; delete modal.dataset.editId; }
    modal.classList.remove('hidden');
}

function closeSurveyModal() {
    document.getElementById('surveyModal').classList.add('hidden');
}

async function saveSurveyModal() {
    const modal = document.getElementById('surveyModal'); const editId = modal.dataset.editId;
    const titel = document.getElementById('surveyModalTitel').value.trim() || 'Neue Umfrage'; const frage = document.getElementById('surveyModalFrage').value.trim() || 'Wie zufrieden sind Sie?'; const companyId = document.getElementById('surveyModalCompanyId').value || ''; const aktiv = document.getElementById('surveyModalAktiv').checked;
    const body = { titel, frage, company_id: companyId ? parseInt(companyId) : null, aktiv: aktiv ? 1 : 0 };
    const method = editId ? 'PUT' : 'POST'; if (editId) body.id = parseInt(editId);
    try {
        const res = await fetch(baseUrl + 'admin/api/satisfaction-survey.php', { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        const data = await res.json();
        if (data.success) { closeSurveyModal(); loadSurvey(); if (typeof showToast === 'function') showToast(editId ? 'Gespeichert' : 'Erstellt', 'success'); }
        else alert(data.error || 'Fehler');
    } catch (e) { alert('Fehler'); }
}

async function loadCards() {
    const container = document.getElementById('cardsContainer');
    if (!container) return;
    try {
        const response = await fetch(baseUrl + 'admin/api/dashboard-cards.php');
        const data = await response.json();
        if (data.success) {
            if (data.cards.length === 0) {
                container.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500 dark:text-gray-400">Keine Dashboard-Cards vorhanden.</div>';
            } else {
                container.innerHTML = '';
                data.cards.forEach(card => container.insertAdjacentHTML('beforeend', renderCard(card)));
            }
        } else {
            container.innerHTML = '<div class="col-span-full text-center py-8 text-red-500">Fehler beim Laden der Cards.</div>';
        }
    } catch (error) {
        console.error('Fehler:', error);
        container.innerHTML = '<div class="col-span-full text-center py-8 text-red-500">Fehler beim Laden der Cards.</div>';
    }
}

function openCardModal(isEdit = false) {
    document.getElementById('card-modal-title').textContent = isEdit ? 'Dashboard-Card bearbeiten' : 'Dashboard-Card erstellen';
    const form = document.getElementById('cardForm');
    form.reset();
    document.getElementById('cardId').value = '';
    document.getElementById('cardBild').value = '';
    document.getElementById('cardBildDark').value = '';
    document.getElementById('cardAktiv').checked = true;
    document.getElementById('cardSortOrder').value = '0';
    document.getElementById('cardImagePreview').innerHTML = '<span class="text-xs text-gray-500 dark:text-gray-400 text-center px-1">Light</span>';
    document.getElementById('cardImageDarkPreview').innerHTML = '<span class="text-xs text-gray-400 text-center px-1">Dark</span>';
    document.getElementById('cardModal').classList.remove('hidden');
}

function closeCardModal() {
    document.getElementById('cardModal').classList.add('hidden');
}

async function editCard(id) {
    try {
        const response = await fetch(baseUrl + 'admin/api/dashboard-cards.php?id=' + id);
        const data = await response.json();
        if (data.success && data.card) {
            const c = data.card;
            document.getElementById('card-modal-title').textContent = 'Dashboard-Card bearbeiten';
            document.getElementById('cardId').value = c.id;
            document.getElementById('cardTitel').value = c.titel;
            document.getElementById('cardNachricht').value = c.nachricht;
            document.getElementById('cardButtonText').value = c.button_text || '';
            document.getElementById('cardButtonLink').value = c.button_link || '';
            document.getElementById('cardTyp').value = c.typ || 'info';
            document.getElementById('cardCompanyId').value = c.company_id || '';
            document.getElementById('cardSortOrder').value = c.sort_order ?? 0;
            document.getElementById('cardAktiv').checked = c.aktiv == 1;
            document.getElementById('cardBild').value = c.bild || '';
            document.getElementById('cardBildDark').value = c.bild_dark || '';
            const prev = document.getElementById('cardImagePreview');
            prev.innerHTML = c.bild ? '<img src="' + baseUrl + c.bild + '" alt="" class="w-full h-full object-cover">' : '<span class="text-xs text-gray-500 dark:text-gray-400 text-center px-1">Light</span>';
            const prevDark = document.getElementById('cardImageDarkPreview');
            prevDark.innerHTML = c.bild_dark ? '<img src="' + baseUrl + c.bild_dark + '" alt="" class="w-full h-full object-cover">' : '<span class="text-xs text-gray-400 text-center px-1">Dark</span>';
            document.getElementById('cardModal').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Fehler:', error);
        alert('Fehler beim Laden der Card');
    }
}

async function deleteCard(id) {
    if (!confirm('Dashboard-Card wirklich löschen?')) return;
    try {
        const response = await fetch(baseUrl + 'admin/api/dashboard-cards.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await response.json();
        if (data.success) {
            loadCards();
            if (typeof showToast === 'function') showToast('Card gelöscht', 'success');
        } else alert('Fehler beim Löschen');
    } catch (error) {
        console.error('Fehler:', error);
        alert('Fehler beim Löschen');
    }
}

function openModal(isEdit = false) {
    const modal = document.getElementById('announcementModal');
    const modalTitle = document.getElementById('modal-title');
    const form = document.getElementById('announcementForm');
    
    if (!isEdit) {
        modalTitle.textContent = 'Ankündigung erstellen';
        form.reset();
        document.getElementById('announcementId').value = '';
        document.getElementById('showBanner').checked = true;
        document.getElementById('aktiv').checked = true;
        document.getElementById('anonym').checked = false;
        
        // Prüfe ob eine Firma in der Nav ausgewählt ist
        const savedSelection = localStorage.getItem('selectedUserOption');
        let companyId = '';
        if (savedSelection) {
            try {
                const data = JSON.parse(savedSelection);
                // Nur wenn eine spezifische Firma ausgewählt ist (nicht "Alle Firmen" mit id "0")
                if (data.id && data.id !== '0' && data.id !== 0) {
                    companyId = data.id.toString();
                }
            } catch (e) {
                console.error('Fehler beim Laden der Firmenauswahl', e);
            }
        }
        document.getElementById('companyId').value = companyId;
    }
    
    modal.classList.remove('hidden');
}

function closeModal() {
    const modal = document.getElementById('announcementModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

async function editAnnouncement(id) {
    try {
        const response = await fetch(baseUrl + 'admin/api/announcements.php?id=' + id);
        const data = await response.json();
        
        if (data.success && data.announcement) {
            const announcement = data.announcement;
            document.getElementById('modal-title').textContent = 'Ankündigung bearbeiten';
            document.getElementById('announcementId').value = announcement.id;
            document.getElementById('titel').value = announcement.titel;
            document.getElementById('nachricht').value = announcement.nachricht;
            document.getElementById('linkText').value = announcement.link_text || '';
            document.getElementById('linkUrl').value = announcement.link_url || '';
            document.getElementById('companyId').value = announcement.company_id || '';
            document.getElementById('showBanner').checked = announcement.show_banner == 1;
            document.getElementById('aktiv').checked = announcement.aktiv == 1;
            document.getElementById('anonym').checked = announcement.anonym == 1;
            openModal(true);
        } else {
            alert('Fehler beim Laden der Ankündigung');
        }
    } catch (error) {
        console.error('Fehler:', error);
        alert('Fehler beim Laden der Ankündigung');
    }
}

async function deleteAnnouncement(id) {
    if (!confirm('Ankündigung wirklich löschen?')) return;
    
    try {
        const response = await fetch(baseUrl + 'admin/api/announcements.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadAnnouncements();
            if (typeof showToast === 'function') {
                showToast('Ankündigung erfolgreich gelöscht', 'success');
            }
        } else {
            alert('Fehler beim Löschen');
        }
    } catch (error) {
        console.error('Fehler:', error);
        alert('Fehler beim Löschen');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadAnnouncements();
    
    // Tab-Switching
    document.querySelectorAll('.tab-nav').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
    
    // Survey: Neue Umfrage, Modal
    document.getElementById('createSurveyBtn')?.addEventListener('click', () => openSurveyModal(null));
    document.getElementById('surveyModalCancel')?.addEventListener('click', closeSurveyModal);
    document.getElementById('surveyModalOverlay')?.addEventListener('click', closeSurveyModal);
    document.getElementById('surveyModalSave')?.addEventListener('click', saveSurveyModal);
    
    // Card-Buttons
    document.getElementById('createCardBtn')?.addEventListener('click', () => openCardModal(false));
    document.getElementById('closeCardModalBtn')?.addEventListener('click', closeCardModal);
    document.getElementById('cardCancelBtn')?.addEventListener('click', closeCardModal);
    document.getElementById('cardModalOverlay')?.addEventListener('click', closeCardModal);
    
    // Card-Bild-Upload (Light)
    document.getElementById('cardImageInput')?.addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('image', file);
        try {
            const resp = await fetch(baseUrl + 'admin/api/upload-dashboard-card-image.php', { method: 'POST', body: formData });
            const d = await resp.json();
            if (d.success) {
                document.getElementById('cardBild').value = d.path;
                document.getElementById('cardImagePreview').innerHTML = '<img src="' + baseUrl + d.path + '" alt="" class="w-full h-full object-cover">';
            } else {
                alert(d.error || 'Upload fehlgeschlagen');
            }
        } catch (e) {
            console.error(e);
            alert('Upload fehlgeschlagen');
        }
    });
    // Card-Bild-Upload (Dark)
    document.getElementById('cardImageDarkInput')?.addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('image', file);
        try {
            const resp = await fetch(baseUrl + 'admin/api/upload-dashboard-card-image.php', { method: 'POST', body: formData });
            const d = await resp.json();
            if (d.success) {
                document.getElementById('cardBildDark').value = d.path;
                document.getElementById('cardImageDarkPreview').innerHTML = '<img src="' + baseUrl + d.path + '" alt="" class="w-full h-full object-cover">';
            } else {
                alert(d.error || 'Upload fehlgeschlagen');
            }
        } catch (e) {
            console.error(e);
            alert('Upload fehlgeschlagen');
        }
    });
    
    // Card-Form Submit
    document.getElementById('cardForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const id = document.getElementById('cardId').value;
        const data = {
            id: id ? parseInt(id) : null,
            titel: document.getElementById('cardTitel').value.trim(),
            nachricht: document.getElementById('cardNachricht').value.trim(),
            button_text: document.getElementById('cardButtonText').value.trim() || null,
            button_link: document.getElementById('cardButtonLink').value.trim() || null,
            bild: document.getElementById('cardBild').value.trim() || null,
            bild_dark: document.getElementById('cardBildDark').value.trim() || null,
            typ: document.getElementById('cardTyp').value,
            company_id: document.getElementById('cardCompanyId').value ? parseInt(document.getElementById('cardCompanyId').value) : null,
            sort_order: parseInt(document.getElementById('cardSortOrder').value) || 0,
            aktiv: document.getElementById('cardAktiv').checked ? 1 : 0
        };
        const method = data.id ? 'PUT' : 'POST';
        try {
            const resp = await fetch(baseUrl + 'admin/api/dashboard-cards.php', {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await resp.json();
            if (result.success) {
                closeCardModal();
                loadCards();
                if (typeof showToast === 'function') showToast('Dashboard-Card gespeichert', 'success');
                else alert('Card gespeichert');
            } else {
                alert('Fehler: ' + (result.error || 'Unbekannt'));
            }
        } catch (err) {
            console.error(err);
            alert('Fehler beim Speichern');
        }
    });
    
    const createBtn = document.getElementById('createAnnouncementBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const overlay = document.getElementById('modalOverlay');
    
    if (createBtn) {
        createBtn.addEventListener('click', () => openModal(false));
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeModal();
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeModal);
    }
    
    
    document.getElementById('announcementForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            id: formData.get('id') || null,
            titel: formData.get('titel'),
            nachricht: formData.get('nachricht'),
            link_text: formData.get('link_text') || null,
            link_url: formData.get('link_url') || null,
            company_id: formData.get('company_id') ? parseInt(formData.get('company_id')) : null,
            show_banner: formData.get('show_banner') ? 1 : 0,
            aktiv: formData.get('aktiv') ? 1 : 0,
            anonym: formData.get('anonym') ? 1 : 0
        };
        
        const method = data.id ? 'PUT' : 'POST';
        
        try {
            const response = await fetch(baseUrl + 'admin/api/announcements.php', {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeModal();
                loadAnnouncements();
                if (typeof showToast === 'function') {
                    showToast('Ankündigung erfolgreich gespeichert', 'success');
                } else {
                    alert('Ankündigung erfolgreich gespeichert');
                }
            } else {
                alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
            }
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Speichern');
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
