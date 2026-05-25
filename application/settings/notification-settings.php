<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// BASE_URL definieren falls nicht vorhanden
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
$navMobileCompactTitle = 'Benachr.-Präferenzen';
$navMobileCompactBackUrl = BASE_URL . 'settings/index.php?section=notifications';
$navMobileCompactBackLabel = 'Zurück';

// Benutzerrolle abrufen
$userId = $_SESSION['user_id'] ?? null;
$userRole = '';
$userData = [];
if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT rolle, vorname, nachname, email, logopfad, company_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $userRole = $row['rolle'] ?? '';
            $userData = $row;
        }
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Benutzerrolle: " . $e->getMessage());
    }
}

$displayName = trim((string)($userData['vorname'] ?? '') . ' ' . (string)($userData['nachname'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($userData['email'] ?? 'Benutzer');
}
$initials = 'U';
if (!empty($userData['vorname']) || !empty($userData['nachname'])) {
    $initials = strtoupper(substr((string)($userData['vorname'] ?? ''), 0, 1) . substr((string)($userData['nachname'] ?? ''), 0, 1));
} elseif (!empty($userData['email'])) {
    $initials = strtoupper(substr((string)$userData['email'], 0, 1));
}
$avatarPath = !empty($userData['logopfad']) ? (string)$userData['logopfad'] : '';
$isPresetAvatar = false;
$presetColor = null;
if ($avatarPath !== '') {
    if (str_starts_with($avatarPath, 'preset:')) {
        $isPresetAvatar = true;
        $parts = explode(':', $avatarPath);
        if (isset($parts[1]) && $parts[1] !== '') {
            $presetColor = str_starts_with($parts[1], '#') ? $parts[1] : '#' . $parts[1];
        }
    } elseif (!str_starts_with($avatarPath, 'http') && !str_starts_with($avatarPath, '/')) {
        $avatarPath = BASE_URL . ltrim($avatarPath, '/');
    }
}
$companyLink = BASE_URL . 'account/my-company.php';

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

// Kategorien und Benachrichtigungstypen
$allNotificationCategories = [
    'firmen' => [
        'name' => 'Firmen',
        'icon' => '🏢',
        'types' => [
            'company_created' => ['name' => 'Firma erstellt', 'relevanz' => 'normal', 'wichtig' => false],
            'company_updated' => ['name' => 'Firma aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'company_status_changed' => ['name' => 'Firma Status geändert', 'relevanz' => 'hoch', 'wichtig' => true],
            'company_deleted' => ['name' => 'Firma gelöscht', 'relevanz' => 'kritisch', 'wichtig' => true],
            'company_logo_upload' => ['name' => 'Logo hochgeladen', 'relevanz' => 'niedrig', 'wichtig' => false],
            'company_document_uploaded' => ['name' => 'Dokument hochgeladen', 'relevanz' => 'normal', 'wichtig' => false],
            'company_document_deleted' => ['name' => 'Dokument gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
            'company_contract_uploaded' => ['name' => 'Vertrag hochgeladen', 'relevanz' => 'hoch', 'wichtig' => true],
            'company_contract_deleted' => ['name' => 'Vertrag gelöscht', 'relevanz' => 'kritisch', 'wichtig' => true],
            'company_note_created' => ['name' => 'Notiz erstellt', 'relevanz' => 'normal', 'wichtig' => false],
            'company_note_updated' => ['name' => 'Notiz aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'company_note_deleted' => ['name' => 'Notiz gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
        ]
    ],
    'kunden' => [
        'name' => 'Kunden',
        'icon' => '👥',
        'types' => [
            'customer_created' => ['name' => 'Kunde erstellt', 'relevanz' => 'normal', 'wichtig' => false],
            'customer_updated' => ['name' => 'Kunde aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'customer_status_changed' => ['name' => 'Kunde Status geändert', 'relevanz' => 'hoch', 'wichtig' => true],
            'customer_deleted' => ['name' => 'Kunde gelöscht', 'relevanz' => 'kritisch', 'wichtig' => true],
            'customer_logo_upload' => ['name' => 'Logo hochgeladen', 'relevanz' => 'niedrig', 'wichtig' => false],
            'customer_document_uploaded' => ['name' => 'Dokument hochgeladen', 'relevanz' => 'normal', 'wichtig' => false],
            'customer_document_deleted' => ['name' => 'Dokument gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
            'customer_contract_uploaded' => ['name' => 'Rechnung hochgeladen', 'relevanz' => 'hoch', 'wichtig' => true],
            'customer_contract_deleted' => ['name' => 'Rechnung gelöscht', 'relevanz' => 'kritisch', 'wichtig' => true],
            'customer_note_created' => ['name' => 'Notiz erstellt', 'relevanz' => 'normal', 'wichtig' => false],
            'customer_note_updated' => ['name' => 'Notiz aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'customer_note_deleted' => ['name' => 'Notiz gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
        ]
    ],
    'geraete' => [
        'name' => 'Geräte',
        'icon' => '💻',
        'types' => [
            'device_created' => ['name' => 'Gerät erstellt', 'relevanz' => 'normal', 'wichtig' => false],
            'device_updated' => ['name' => 'Gerät aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'device_status_changed' => ['name' => 'Gerät Status geändert', 'relevanz' => 'hoch', 'wichtig' => true],
            'device_offline' => ['name' => 'Gerät offline', 'relevanz' => 'hoch', 'wichtig' => true],
            'device_online' => ['name' => 'Gerät online', 'relevanz' => 'normal', 'wichtig' => false],
            'device_deleted' => ['name' => 'Gerät gelöscht', 'relevanz' => 'kritisch', 'wichtig' => true],
        ]
    ],
    'aufgaben' => [
        'name' => 'Aufgaben',
        'icon' => '✅',
        'types' => [
            'todo_created' => ['name' => 'Aufgabe erstellt', 'relevanz' => 'normal', 'wichtig' => false],
            'todo_zugewiesen' => ['name' => 'Aufgabe zugewiesen', 'relevanz' => 'hoch', 'wichtig' => true],
            'todo_status_changed' => ['name' => 'Aufgabe Status geändert', 'relevanz' => 'hoch', 'wichtig' => true],
            'todo_updated' => ['name' => 'Aufgabe aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'todo_deleted' => ['name' => 'Aufgabe gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
            'todo_attachment_uploaded' => ['name' => 'Anhang hochgeladen', 'relevanz' => 'niedrig', 'wichtig' => false],
            'todo_attachment_deleted' => ['name' => 'Anhang gelöscht', 'relevanz' => 'normal', 'wichtig' => false],
            'todo_folder_created' => ['name' => 'Ordner erstellt', 'relevanz' => 'normal', 'wichtig' => false],
            'todo_folder_updated' => ['name' => 'Ordner aktualisiert', 'relevanz' => 'niedrig', 'wichtig' => false],
            'todo_folder_deleted' => ['name' => 'Ordner gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
        ]
    ],
    'tickets' => [
        'name' => 'Tickets',
        'icon' => '🎫',
        'types' => [
            'ticket_created' => ['name' => 'Ticket erstellt', 'relevanz' => 'hoch', 'wichtig' => true],
            'ticket_status_changed' => ['name' => 'Ticket Status geändert', 'relevanz' => 'hoch', 'wichtig' => true],
            'ticket_updated' => ['name' => 'Ticket aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'ticket_comment' => ['name' => 'Ticket Kommentar', 'relevanz' => 'normal', 'wichtig' => false],
        ]
    ],
    'anrufe' => [
        'name' => 'Anrufe',
        'icon' => '📞',
        'types' => [
            'call_created' => ['name' => 'Anruf erfasst', 'relevanz' => 'normal', 'wichtig' => false],
            'call_updated' => ['name' => 'Anruf aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'call_deleted' => ['name' => 'Anruf gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
        ]
    ],
    'ankuendigungen' => [
        'name' => 'Ankündigungen',
        'icon' => '📢',
        'types' => [
            'announcement_created' => ['name' => 'Ankündigung erstellt', 'relevanz' => 'normal', 'wichtig' => false],
            'announcement_updated' => ['name' => 'Ankündigung aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'announcement_deleted' => ['name' => 'Ankündigung gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
        ]
    ],
    'benutzer' => [
        'name' => 'Benutzer',
        'icon' => '👤',
        'types' => [
            'user_created' => ['name' => 'Benutzer erstellt', 'relevanz' => 'hoch', 'wichtig' => true],
            'account_gesperrt' => ['name' => 'Account gesperrt', 'relevanz' => 'kritisch', 'wichtig' => true],
        ]
    ],
    'bestellungen' => [
        'name' => 'Bestellungen',
        'icon' => '📦',
        'types' => [
            'order_created' => ['name' => 'Bestellung erstellt', 'relevanz' => 'hoch', 'wichtig' => true],
            'order_status_changed' => ['name' => 'Bestellung Status geändert', 'relevanz' => 'hoch', 'wichtig' => true],
            'order_updated' => ['name' => 'Bestellung aktualisiert', 'relevanz' => 'normal', 'wichtig' => false],
            'order_notizen_updated' => ['name' => 'Bestellung Notizen aktualisiert', 'relevanz' => 'niedrig', 'wichtig' => false],
        ]
    ],
    'kalender' => [
        'name' => 'Kalender',
        'icon' => '📅',
        'types' => [
            'calendar_invite' => ['name' => 'Termin-Einladung', 'relevanz' => 'hoch', 'wichtig' => true],
            'calendar_update' => ['name' => 'Termin geändert', 'relevanz' => 'normal', 'wichtig' => false],
            'calendar_event_deleted' => ['name' => 'Termin gelöscht', 'relevanz' => 'hoch', 'wichtig' => true],
        ]
    ],
];

// Kategorien basierend auf Benutzerrolle filtern
$notificationCategories = [];
$isAdminOrTechniker = in_array($userRole, ['Admin', 'Techniker'], true);
$isFirmenAdmin = ($userRole === 'Firmen-Admin');
$isFirmenUser = ($userRole === 'Firmen-User');
$isKunde = ($userRole === 'Kunde');
$isNotKunde = !$isKunde;

foreach ($allNotificationCategories as $key => $category) {
    $showCategory = false;
    
    switch ($key) {
        case 'firmen':
            // Nur für Admin und Techniker
            $showCategory = $isAdminOrTechniker;
            break;
        case 'kunden':
            // Für Admin, Techniker und Firmen-Admin (nicht für Firmen-User und Kunde)
            $showCategory = $isAdminOrTechniker || $isFirmenAdmin;
            break;
        case 'benutzer':
            // Nur für Admin und Techniker
            $showCategory = $isAdminOrTechniker;
            break;
        case 'bestellungen':
            // Für alle außer Kunde
            $showCategory = $isNotKunde;
            break;
        case 'anrufe':
            // Nicht für Firmen-User
            $showCategory = !$isFirmenUser;
            break;
        case 'ankuendigungen':
            // Nicht für Firmen-User
            $showCategory = !$isFirmenUser;
            break;
        case 'aufgaben':
            // Nicht für Firmen-User
            $showCategory = !$isFirmenUser;
            break;
        case 'geraete':
        case 'tickets':
        case 'kalender':
            // Für alle Benutzer
            $showCategory = true;
            break;
        default:
            // Standard: anzeigen
            $showCategory = true;
            break;
    }
    
    if ($showCategory) {
        $notificationCategories[$key] = $category;
    }
}
?>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-0 lg:pt-0 lg:h-screen lg:overflow-hidden app-mobile-no-root-overscroll">
    <main class="mx-4 mt-0 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 lg:h-full">
        <div>
            <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50 lg:h-full">
                <!-- Header -->
                <div class="hidden col-span-full lg:block">
                    <div class="mb-4">
                    <nav class="mb-4 hidden flex-shrink-0 lg:flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                    <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                      <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                    </svg>
                    Startseite
                  </a>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Einstellungen</span>
                  </div>
                </li>
              </ol>
            </nav>
                    </div>
                </div>

                <aside class="hidden lg:block lg:col-span-3 lg:mx-0 lg:self-start lg:sticky lg:top-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <a href="<?php echo BASE_URL; ?>account/index.php" class="shrink-0 rounded-full focus:outline-none">
                                    <?php if ($isPresetAvatar && $presetColor): ?>
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-gray-200 text-sm font-semibold text-white dark:border-gray-600" style="background-color: <?php echo htmlspecialchars($presetColor, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php elseif ($avatarPath !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profilbild" class="h-12 w-12 shrink-0 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                                    <?php else: ?>
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-primary-600 text-sm font-semibold text-white dark:border-gray-600"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </a>
                                <a href="<?php echo BASE_URL; ?>account/index.php" class="min-w-0 block w-full rounded-md p-1 -m-1 focus:outline-none">
                                    <p class="truncate text-base font-semibold text-gray-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-300"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($userRole ?: 'Benutzer', ENT_QUOTES, 'UTF-8'); ?></p>
                                </a>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 border-b border-gray-200 pb-4 dark:border-gray-700">
                            <a href="<?php echo BASE_URL; ?>account/index.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z"/></svg>Persönliche Daten</a>
                            <a href="<?php echo htmlspecialchars($companyLink, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>Meine Firma</a>
                            <a href="<?php echo BASE_URL; ?>settings/index.php#benachrichtigungen" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>Benachrichtigungen</a>
                        </div>
                        <ul class="-mb-px grid grid-cols-1 gap-1.5 text-base font-semibold">
                            <li><a href="<?php echo BASE_URL; ?>settings/index.php#praeferenzen" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M20 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6h-2m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4"/></svg>Präferenzen</a></li>
                            <li><a href="<?php echo BASE_URL; ?>settings/index.php#benachrichtigungen" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 px-4 py-3 text-left" style="background-color:#ede9fe !important;color:#5b21b6 !important;border-left-color:#7c3aed !important;"><svg class="me-2 h-5 w-5" style="color:#5b21b6 !important;" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>Benachrichtigungen</a></li>
                            <li><a href="<?php echo BASE_URL; ?>settings/index.php#sicherheit" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>Sicherheit</a></li>
                            <li><button type="button" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white"><svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 9H8a5 5 0 0 0 0 10h9m4-10-4-4m4 4-4 4"/></svg>Einstellungen zurücksetzen</button></li>
                        </ul>
                        <ul class="mt-4 m-0 list-none border-t border-gray-200 pt-4 p-0 dark:border-gray-700">
                            <li><a href="<?php echo BASE_URL; ?>logout.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left font-semibold text-red-700 transition-colors hover:bg-red-50 hover:text-red-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300"><svg class="me-2 h-5 w-5 text-red-700 dark:text-red-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2"/></svg>Abmelden</a></li>
                        </ul>
                    </div>
                </aside>
                

                <!-- Settings Container -->
                <div class="col-span-full lg:col-span-9 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:pe-2 lg:pb-8" id="settingsContainer">
                <div class="flex items-center justify-center py-8" role="status">
                    <svg aria-hidden="true" class="w-8 h-8 text-neutral-tertiary animate-spin fill-brand" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                    </svg>
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
                
            </div>
        </div>
    </main>
</div>

<script>
// baseUrl wird bereits in nav.php als const deklariert
// Definieren wir hier eine lokale Variable als Fallback
const notificationCategories = <?php echo json_encode($notificationCategories); ?>;
let settings = {};
let pendingChanges = {};
let categorySettings = {}; // Speichert pro Kategorie: 'all', 'important', 'none'
let hideOwnNotifications = false;
let autoSaveTimer = null;
let autoSaveInFlight = false;

// API Base URL (baseUrl ist bereits in nav.php definiert)
const getApiBaseUrl = () => {
    if (typeof baseUrl !== 'undefined') {
        return baseUrl;
    }
    return '<?php echo BASE_URL; ?>';
};

function renderSettings() {
    const container = document.getElementById('settingsContainer');
    const isDark = document.documentElement.classList.contains('dark');
    const sidebarCategoryIcons = {
        firmen: '<svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>',
        kunden: '<svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>',
        geraete: '<svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"/></svg>',
        aufgaben: '<svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
        tickets: '<svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/></svg>',
        anrufe: '<svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M7.978 4a2.553 2.553 0 0 0-1.926.877C4.233 6.7 3.699 8.751 4.153 10.814c.44 1.995 1.778 3.893 3.456 5.572 1.68 1.679 3.577 3.018 5.57 3.459 2.062.456 4.115-.073 5.94-1.885a2.556 2.556 0 0 0 .001-3.861l-1.21-1.21a2.689 2.689 0 0 0-3.802 0l-.617.618a.806.806 0 0 1-1.14 0l-1.854-1.855a.807.807 0 0 1 0-1.14l.618-.62a2.692 2.692 0 0 0 0-3.803l-1.21-1.211A2.555 2.555 0 0 0 7.978 4Z"/></svg>',
        bestellungen: '<svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/></svg>',
        kalender: '<svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z"/></svg>'
    };

    if (!notificationCategories || Object.keys(notificationCategories).length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500 dark:text-gray-400">Keine Kategorien verfügbar.</div>';
        return;
    }

    let html = '';
    Object.entries(notificationCategories).forEach(([categoryKey, category]) => {
        const currentSetting = categorySettings[categoryKey] || 'all';
        const allTypes = Object.keys(category.types);
        const importantTypes = allTypes.filter(type => category.types[type].wichtig);

        let activeTypes = [];
        if (currentSetting === 'all') {
            activeTypes = allTypes;
        } else if (currentSetting === 'important') {
            activeTypes = importantTypes;
        }

        html += `
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-3 lg:mb-4">
                <div class="p-4 sm:p-5 lg:p-6">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-200">${sidebarCategoryIcons[categoryKey] || category.icon}</span>
                            ${category.name}
                        </h2>
                        <button class="shrink-0 inline-flex h-10 items-center rounded-lg px-3 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 bg-transparent hover:bg-transparent active:bg-transparent focus:bg-transparent dark:bg-transparent dark:hover:bg-transparent dark:active:bg-transparent dark:focus:bg-transparent focus:outline-none" onclick="toggleCollapse('${categoryKey}')" id="collapse-btn-${categoryKey}">
                            <span class="collapse-text-${categoryKey}">Details anzeigen</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5" role="group" aria-label="System-Auswahl ${category.name}">
                        <button type="button" class="inline-flex w-full items-center justify-between rounded-lg border px-3.5 py-3 text-left transition-colors focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 ${currentSetting === 'all' ? 'border-primary-600 dark:border-primary-500' : 'border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700'}" style="${currentSetting === 'all' ? `background-color:${isDark ? '#334155' : '#e5e7eb'};color:${isDark ? '#f8fafc' : '#111827'};` : ''}" onclick="selectCategorySetting('${categoryKey}', 'all')" aria-pressed="${currentSetting === 'all' ? 'true' : 'false'}">
                            <span class="min-w-0"><span class="block text-sm font-semibold ${currentSetting === 'all' ? '' : 'text-gray-900 dark:text-gray-100'}">Alle</span></span>
                            <span class="ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${currentSetting === 'all' ? '!border-white !bg-white' : 'border-gray-300 text-transparent dark:border-gray-500'}" style="${currentSetting === 'all' ? `color:${isDark ? '#334155' : '#111827'};` : ''}">
                                <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/></svg>
                            </span>
                        </button>
                        <button type="button" class="inline-flex w-full items-center justify-between rounded-lg border px-3.5 py-3 text-left transition-colors focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 ${currentSetting === 'important' ? 'border-primary-600 dark:border-primary-500' : 'border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700'}" style="${currentSetting === 'important' ? `background-color:${isDark ? '#334155' : '#e5e7eb'};color:${isDark ? '#f8fafc' : '#111827'};` : ''}" onclick="selectCategorySetting('${categoryKey}', 'important')" aria-pressed="${currentSetting === 'important' ? 'true' : 'false'}">
                            <span class="min-w-0"><span class="block text-sm font-semibold ${currentSetting === 'important' ? '' : 'text-gray-900 dark:text-gray-100'}">Wichtige</span></span>
                            <span class="ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${currentSetting === 'important' ? '!border-white !bg-white' : 'border-gray-300 text-transparent dark:border-gray-500'}" style="${currentSetting === 'important' ? `color:${isDark ? '#334155' : '#111827'};` : ''}">
                                <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/></svg>
                            </span>
                        </button>
                        <button type="button" class="inline-flex w-full items-center justify-between rounded-lg border px-3.5 py-3 text-left transition-colors focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-500 ${currentSetting === 'none' ? 'border-primary-600 dark:border-primary-500' : 'border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700'}" style="${currentSetting === 'none' ? `background-color:${isDark ? '#334155' : '#e5e7eb'};color:${isDark ? '#f8fafc' : '#111827'};` : ''}" onclick="selectCategorySetting('${categoryKey}', 'none')" aria-pressed="${currentSetting === 'none' ? 'true' : 'false'}">
                            <span class="min-w-0"><span class="block text-sm font-semibold ${currentSetting === 'none' ? '' : 'text-gray-900 dark:text-gray-100'}">Keine</span></span>
                            <span class="ms-3 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${currentSetting === 'none' ? '!border-white !bg-white' : 'border-gray-300 text-transparent dark:border-gray-500'}" style="${currentSetting === 'none' ? `color:${isDark ? '#334155' : '#111827'};` : ''}">
                                <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 13 4 4L19 7"/></svg>
                            </span>
                        </button>
                    </div>
                </div>

                <div id="collapse-${categoryKey}" class="hidden border-t border-gray-200 dark:border-gray-700">
                    <div class="p-4 sm:p-5 lg:p-6 bg-gray-50 dark:bg-gray-900/50">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Aktive Benachrichtigungen (${activeTypes.length}):</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5 sm:gap-3">
                            ${activeTypes.length > 0 ? activeTypes.map(type => {
                                const typeInfo = category.types[type];
                                const relevanzColors = {
                                    'niedrig': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                    'normal': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    'hoch': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
                                    'kritisch': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
                                };
                                return `
                                    <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">${typeInfo.name}</div>
                                            <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded ${relevanzColors[typeInfo.relevanz] || relevanzColors.normal}">${typeInfo.relevanz}</span>
                                        </div>
                                    </div>
                                `;
                            }).join('') : '<p class="text-sm text-gray-500 dark:text-gray-400 col-span-full">Keine aktiven Benachrichtigungen</p>'}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function selectCategorySetting(categoryKey, setting) {
    // Update categorySettings
    const oldSetting = categorySettings[categoryKey] || 'all';
    if (oldSetting !== setting) {
        categorySettings[categoryKey] = setting;
        
        const category = notificationCategories[categoryKey];
        const allTypes = Object.keys(category.types);
        const importantTypes = allTypes.filter(type => category.types[type].wichtig);
        
        // Update alle Typen dieser Kategorie in pendingChanges
        let activeTypes = [];
        if (setting === 'all') {
            activeTypes = allTypes;
        } else if (setting === 'important') {
            activeTypes = importantTypes;
        }
        
        allTypes.forEach(type => {
            const shouldBeActive = activeTypes.includes(type);
            if (!pendingChanges[type]) {
                pendingChanges[type] = { ...(settings[type] || { system: true, email: true }) };
            }
            pendingChanges[type].system = shouldBeActive;
        });
        
        renderSettings();
        scheduleAutoSave();
    }
}

function toggleCollapse(categoryKey) {
    const collapse = document.getElementById(`collapse-${categoryKey}`);
    const btn = document.getElementById(`collapse-btn-${categoryKey}`);
    const text = btn.querySelector(`.collapse-text-${categoryKey}`);
    
    if (collapse.classList.contains('hidden')) {
        collapse.classList.remove('hidden');
        text.textContent = 'Details ausblenden';
    } else {
        collapse.classList.add('hidden');
        text.textContent = 'Details anzeigen';
    }
}

async function loadSettings() {
    try {
        // Initialisiere Standard-Werte
        settings = {};
        pendingChanges = {};
        categorySettings = {};
        
        try {
            const response = await fetch(`${getApiBaseUrl()}notifications/api/settings.php`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            
            if (data.success) {
                settings = data.settings || {};
                hideOwnNotifications = !!data.hide_own_notifications;
            }
        } catch (apiError) {
            console.error('API-Fehler (verwende Standardeinstellungen):', apiError);
            // Bei API-Fehler Standardeinstellungen verwenden
            settings = {};
        }
        
        // Initialisiere categorySettings basierend auf settings (oder Standard: 'all')
        Object.entries(notificationCategories).forEach(([categoryKey, category]) => {
            const allTypes = Object.keys(category.types);
            const importantTypes = allTypes.filter(type => category.types[type].wichtig);
            
            // Prüfe Einstellungen für alle Typen (Default: system !== false wenn nicht gesetzt)
            const allActive = allTypes.every(type => {
                const typeSetting = settings[type];
                return typeSetting?.system !== false;
            });
            
            const noneActive = allTypes.every(type => {
                const typeSetting = settings[type];
                return typeSetting?.system === false;
            });
            
            const importantActive = importantTypes.length > 0 && 
                importantTypes.every(type => {
                    const typeSetting = settings[type];
                    return typeSetting?.system !== false;
                }) &&
                allTypes.filter(type => !importantTypes.includes(type)).every(type => {
                    const typeSetting = settings[type];
                    return typeSetting?.system === false;
                });
            
            if (noneActive) {
                categorySettings[categoryKey] = 'none';
            } else if (importantActive) {
                categorySettings[categoryKey] = 'important';
            } else if (allActive) {
                categorySettings[categoryKey] = 'all';
            } else {
                // Gemischter Zustand: wie Mail-Settings auf "all" darstellen
                categorySettings[categoryKey] = 'all';
            }
        });
        
        // Toggle „Eigene ausblenden“ setzen
        const hideOwnToggle = document.getElementById('hideOwnNotificationsToggle');
        if (hideOwnToggle) {
            hideOwnToggle.checked = hideOwnNotifications;
        }
        
        renderSettings();
    } catch (error) {
        console.error('Fehler beim Laden der Einstellungen:', error);
        // Auch bei Fehler die Seite mit Standardwerten rendern
        Object.keys(notificationCategories).forEach(categoryKey => {
            categorySettings[categoryKey] = 'all';
        });
        renderSettings();
    }
}

async function saveHideOwnSetting(enabled) {
    try {
        const response = await fetch(`${getApiBaseUrl()}notifications/api/settings.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ hide_own_notifications: enabled })
        });
        const data = await response.json();
        if (data.success) {
            hideOwnNotifications = enabled;
            if (typeof showToast === 'function') {
                showToast('Einstellung gespeichert', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Speichern fehlgeschlagen', 'error');
            } else {
                alert('Speichern fehlgeschlagen');
            }
        }
    } catch (e) {
        console.error('saveHideOwnSetting:', e);
        if (typeof showToast === 'function') {
            showToast('Speichern fehlgeschlagen', 'error');
        } else {
            alert('Speichern fehlgeschlagen');
        }
    }
}

function scheduleAutoSave() {
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }
    autoSaveTimer = setTimeout(() => {
        savePendingChanges();
    }, 350);
}

async function savePendingChanges() {
    if (autoSaveInFlight) {
        return;
    }
    const entries = Object.entries(pendingChanges);
    if (!entries.length) {
        return;
    }
    const changesToSave = {};
    entries.forEach(([type, value]) => {
        changesToSave[type] = { ...value };
    });
    autoSaveInFlight = true;

    try {
        const promises = Object.entries(changesToSave).map(([type, values]) => {
            // E-Mail-Einstellung beibehalten (wird auf dieser Seite nicht geändert)
            const currentEmail = (settings[type] && typeof settings[type].email === 'boolean') ? settings[type].email : true;
            return fetch(`${getApiBaseUrl()}notifications/api/settings.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    benachrichtigungs_typ: type,
                    system: values.system,
                    email: currentEmail
                })
            });
        });
        
        const results = await Promise.all(promises);
        const dataResults = await Promise.all(results.map(r => r.json()));
        
        const allSuccess = dataResults.every(r => r.success);
        
        if (allSuccess) {
            Object.entries(changesToSave).forEach(([type, values]) => {
                if (!settings[type]) {
                    settings[type] = { system: true, email: true };
                }
                settings[type].system = values.system;
                if (pendingChanges[type] && pendingChanges[type].system === values.system) {
                    delete pendingChanges[type];
                }
            });
            if (typeof showToast === 'function') {
                showToast('Einstellungen gespeichert', 'success');
            }
        } else {
            const errors = dataResults.filter(r => !r.success);
            throw new Error(errors[0]?.error || 'Fehler beim Speichern einiger Einstellungen');
        }
    } catch (error) {
        console.error('Fehler beim Speichern:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Speichern der Einstellungen: ' + error.message, 'error');
        } else {
            alert('Fehler beim Speichern der Einstellungen: ' + error.message);
        }
    } finally {
        autoSaveInFlight = false;
        if (Object.keys(pendingChanges).length > 0) {
            scheduleAutoSave();
        } else {
            renderSettings();
        }
    }
}

function updateDesktopNotificationUi() {
    const statusEl = document.getElementById('desktopNotifPermissionText');
    const btn = document.getElementById('desktopNotifRequestBtn');
    const toggle = document.getElementById('desktopNotifEnabledToggle');
    if (!statusEl || !btn || !toggle) return;

    if (typeof Notification === 'undefined') {
        statusEl.textContent = 'Dein Browser unterstützt keine Desktop-Benachrichtigungen.';
        btn.classList.add('hidden');
        toggle.disabled = true;
        return;
    }

    var host = typeof location !== 'undefined' ? location.hostname : '';
    var secure = typeof location !== 'undefined' && (location.protocol === 'https:' || host === 'localhost' || host === '127.0.0.1');
    if (!secure) {
        statusEl.textContent = 'Desktop-Hinweise sind nur über HTTPS oder auf localhost möglich.';
        btn.classList.add('hidden');
        toggle.disabled = true;
        return;
    }

    btn.classList.remove('hidden');
    var perm = Notification.permission;
    if (perm === 'granted') {
        statusEl.textContent = 'Status: Erlaubnis erteilt. Du kannst den Schalter nutzen, um Hinweise ein- oder auszuschalten.';
        btn.classList.add('hidden');
        toggle.disabled = false;
        try {
            toggle.checked = localStorage.getItem('svDesktopNotifications') === '1';
        } catch (e) {
            toggle.checked = false;
        }
    } else if (perm === 'denied') {
        statusEl.textContent = 'Status: Abgelehnt. Ändere die Berechtigung in den Browser-Einstellungen für diese Website, um Hinweise zu erhalten.';
        btn.classList.add('hidden');
        toggle.disabled = true;
        toggle.checked = false;
    } else {
        statusEl.textContent = 'Status: Noch nicht festgelegt. Klicke auf „Im Browser erlauben“ oder aktiviere den Schalter (dann erscheint die Abfrage).';
        toggle.disabled = false;
        try {
            toggle.checked = localStorage.getItem('svDesktopNotifications') === '1';
        } catch (e) {
            toggle.checked = false;
        }
    }
}

function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = window.atob(base64);
    var out = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; ++i) {
        out[i] = rawData.charCodeAt(i);
    }
    return out;
}

async function refreshMobilePushUi() {
    var statusEl = document.getElementById('mobilePushStatus');
    var enableBtn = document.getElementById('mobilePushEnableBtn');
    var disableBtn = document.getElementById('mobilePushDisableBtn');
    if (!statusEl || !enableBtn || !disableBtn) return;

    var host = typeof location !== 'undefined' ? location.hostname : '';
    var secure = typeof location !== 'undefined' && (location.protocol === 'https:' || host === 'localhost' || host === '127.0.0.1');
    if (!secure) {
        statusEl.textContent = 'Push ist nur über HTTPS oder auf localhost möglich.';
        enableBtn.classList.add('hidden');
        disableBtn.classList.add('hidden');
        return;
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        statusEl.textContent = 'Dein Browser unterstützt keine Web-Push-Mitteilungen auf diesem Gerät.';
        enableBtn.classList.add('hidden');
        disableBtn.classList.add('hidden');
        return;
    }

    var cfg;
    try {
        var r = await fetch(getApiBaseUrl() + 'notifications/api/push-config.php', { credentials: 'same-origin' });
        cfg = await r.json();
    } catch (e) {
        statusEl.textContent = 'Push-Konfiguration konnte nicht geladen werden.';
        return;
    }

    if (!cfg.success || !cfg.push_available || !cfg.publicKey) {
        statusEl.textContent = 'Push ist auf dem Server noch nicht eingerichtet (VAPID-Schlüssel in der Konfiguration).';
        enableBtn.classList.add('hidden');
        disableBtn.classList.add('hidden');
        return;
    }

    enableBtn.classList.remove('hidden');

    try {
        var scope = String(typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>').replace(/\/?$/, '/') || '/';
        var reg = await navigator.serviceWorker.getRegistration();
        if (!reg) {
            reg = await navigator.serviceWorker.register(scope + 'sw.js', { scope: scope });
        }
        var sub = reg.pushManager ? await reg.pushManager.getSubscription() : null;
        if (sub && Notification.permission === 'granted') {
            statusEl.textContent = 'Status: Push ist auf diesem Gerät aktiv (Browser-Berechtigung erteilt).';
            enableBtn.classList.add('hidden');
            disableBtn.classList.remove('hidden');
        } else if (Notification.permission === 'denied') {
            statusEl.textContent = 'Status: Mitteilungen wurden in den Browser-Einstellungen abgelehnt. Bitte dort für diese Website erlauben.';
            enableBtn.classList.add('hidden');
            disableBtn.classList.add('hidden');
        } else {
            statusEl.textContent = 'Status: Push noch nicht aktiv. Tippe auf „Push auf diesem Gerät aktivieren“ und erlaube Mitteilungen.';
            disableBtn.classList.add('hidden');
        }
    } catch (e) {
        statusEl.textContent = 'Service-Worker konnte nicht geprüft werden: ' + (e && e.message ? e.message : 'Unbekannter Fehler');
    }
}

async function onMobilePushEnable() {
    var statusEl = document.getElementById('mobilePushStatus');
    try {
        var r = await fetch(getApiBaseUrl() + 'notifications/api/push-config.php', { credentials: 'same-origin' });
        var cfg = await r.json();
        if (!cfg.success || !cfg.push_available || !cfg.publicKey) {
            if (statusEl) statusEl.textContent = 'Push ist auf dem Server nicht verfügbar.';
            return;
        }
        var scope = String(typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>').replace(/\/?$/, '/') || '/';
        var reg = await navigator.serviceWorker.register(scope + 'sw.js', { scope: scope });
        var perm = await Notification.requestPermission();
        if (perm !== 'granted') {
            if (statusEl) statusEl.textContent = 'Ohne Mitteilungs-Berechtigung kann kein Push eingerichtet werden.';
            await refreshMobilePushUi();
            return;
        }
        var sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(cfg.publicKey)
        });
        var save = await fetch(getApiBaseUrl() + 'notifications/api/push-subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(sub.toJSON())
        });
        var out = await save.json();
        if (!out.success) {
            if (statusEl) statusEl.textContent = 'Speichern der Push-Anmeldung fehlgeschlagen.';
            return;
        }
        if (typeof showToast === 'function') {
            showToast('Push-Benachrichtigungen sind auf diesem Gerät aktiv.', 'success');
        }
    } catch (e) {
        console.error(e);
        if (statusEl) statusEl.textContent = 'Fehler: ' + (e && e.message ? e.message : 'Push konnte nicht aktiviert werden.');
    }
    await refreshMobilePushUi();
}

async function onMobilePushDisable() {
    var statusEl = document.getElementById('mobilePushStatus');
    try {
        var reg = await navigator.serviceWorker.getRegistration();
        if (reg && reg.pushManager) {
            var sub = await reg.pushManager.getSubscription();
            if (sub) {
                var ep = sub.endpoint;
                await fetch(getApiBaseUrl() + 'notifications/api/push-subscribe.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ endpoint: ep })
                });
                await sub.unsubscribe();
            }
        }
        if (typeof showToast === 'function') {
            showToast('Push auf diesem Gerät beendet.', 'success');
        }
    } catch (e) {
        console.error(e);
        if (statusEl) statusEl.textContent = 'Fehler beim Beenden: ' + (e && e.message ? e.message : '');
    }
    await refreshMobilePushUi();
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    loadSettings();
    updateDesktopNotificationUi();
    refreshMobilePushUi();

    const desktopBtn = document.getElementById('desktopNotifRequestBtn');
    if (desktopBtn) {
        desktopBtn.addEventListener('click', function() {
            if (typeof Notification === 'undefined') return;
            Notification.requestPermission().then(function() {
                updateDesktopNotificationUi();
                if (Notification.permission === 'granted' && typeof showToast === 'function') {
                    showToast('Desktop-Benachrichtigungen sind jetzt erlaubt. Aktiviere bei Bedarf den Schalter.', 'success');
                }
            }).catch(function() {
                updateDesktopNotificationUi();
            });
        });
    }

    const desktopToggle = document.getElementById('desktopNotifEnabledToggle');
    if (desktopToggle) {
        desktopToggle.addEventListener('change', function() {
            var on = this.checked;
            if (on && typeof Notification !== 'undefined') {
                if (Notification.permission === 'default') {
                    Notification.requestPermission().then(function(p) {
                        updateDesktopNotificationUi();
                        if (p !== 'granted') {
                            try { localStorage.setItem('svDesktopNotifications', '0'); } catch (e) {}
                            var t = document.getElementById('desktopNotifEnabledToggle');
                            if (t) t.checked = false;
                        } else {
                            try { localStorage.setItem('svDesktopNotifications', '1'); } catch (e) {}
                        }
                    });
                    return;
                }
                if (Notification.permission !== 'granted') {
                    this.checked = false;
                    try { localStorage.setItem('svDesktopNotifications', '0'); } catch (e) {}
                    updateDesktopNotificationUi();
                    return;
                }
            }
            try {
                localStorage.setItem('svDesktopNotifications', on ? '1' : '0');
            } catch (e) {}
            updateDesktopNotificationUi();
        });
    }
    
    const hideOwnToggle = document.getElementById('hideOwnNotificationsToggle');
    if (hideOwnToggle) {
        hideOwnToggle.addEventListener('change', function() {
            saveHideOwnSetting(this.checked);
        });
    }

    var mpEnable = document.getElementById('mobilePushEnableBtn');
    var mpDisable = document.getElementById('mobilePushDisableBtn');
    if (mpEnable) mpEnable.addEventListener('click', function() { onMobilePushEnable(); });
    if (mpDisable) mpDisable.addEventListener('click', function() { onMobilePushDisable(); });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
