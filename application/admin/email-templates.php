<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// BASE_URL definieren falls nicht bereits definiert
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
$userRole = null;
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userRole = $user['rolle'];
    }
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Nur Admin kann auf diese Seite zugreifen
if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'admin/');
    exit;
}

// Vorlagen aus Datenbank laden
$templates = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, subject, body, variables, erstellt_datum, geaendert_datum FROM email_templates ORDER BY name ASC");
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Variablen dekodieren
    foreach ($templates as &$template) {
        if ($template['variables']) {
            $template['variables'] = json_decode($template['variables'], true) ?: [];
        } else {
            $template['variables'] = [];
        }
    }
    unset($template);
} catch (PDOException $e) {
    // Tabelle existiert möglicherweise noch nicht
    error_log("Fehler beim Laden der E-Mail-Vorlagen: " . $e->getMessage());
}

// Template-Zuordnungen laden
$templateMappings = [
    '2fa_enabled' => '',
    '2fa_disabled' => '',
    'ticket_created' => '',
    'ticket_assigned' => '',
    'ticket_comment' => '',
    'ticket_status_changed' => '',
    'ticket_closed' => '',
    'calendar_invite' => '',
    'calendar_update' => ''
];

// Mapping Keys für Datenbank-Abfrage
$mappingKeys = [
    'email_template_2fa_enabled' => '2fa_enabled',
    'email_template_2fa_disabled' => '2fa_disabled',
    'email_template_ticket_created' => 'ticket_created',
    'email_template_ticket_assigned' => 'ticket_assigned',
    'email_template_ticket_comment' => 'ticket_comment',
    'email_template_ticket_status_changed' => 'ticket_status_changed',
    'email_template_ticket_closed' => 'ticket_closed',
    'email_template_todo_assigned' => 'todo_assigned',
    'email_template_calendar_invite' => 'calendar_invite',
    'email_template_calendar_update' => 'calendar_update'
];

try {
    $keys = array_keys($mappingKeys);
    $placeholders = str_repeat('?,', count($keys) - 1) . '?';
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($mappings as $mapping) {
        if (isset($mappingKeys[$mapping['setting_key']])) {
            $templateMappings[$mappingKeys[$mapping['setting_key']]] = $mapping['setting_value'];
        }
    }
} catch (PDOException $e) {
    error_log("Fehler beim Laden der Template-Zuordnungen: " . $e->getMessage());
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
        <!-- Header -->
        <div class="col-span-full mx-4 mt-4">
          <div class="mb-4">
            <nav class="mb-4 flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
                    <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                    </svg>
                    Startseite
                  </a>
                </li>
                <li>
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">E-Mail-Vorlagen</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">E-Mail-Vorlagen</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Erstelle und verwalte E-Mail-Vorlagen mit Variablen</p>
              </div>
              <button type="button" id="newTemplateBtn" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Neue Vorlage
              </button>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="col-span-full mx-4">
          <!-- Template-Zuordnungen -->
          <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">System-Zuordnungen</h3>
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Ordne E-Mail-Vorlagen System-Aktionen zu. Wenn keine Zuordnung vorhanden ist, werden Standard-E-Mails verwendet.</p>
            
            <!-- Sicherheit -->
            <div class="mb-6">
              <h4 class="mb-3 text-md font-semibold text-gray-900 dark:text-white">Sicherheit</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- 2FA Aktiviert -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_2fa_enabled" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 0 0-8 0v4h8Z"/>
                    </svg>
                    2FA Aktiviert
                  </label>
                  <select id="template_2fa_enabled" name="template_2fa_enabled" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo $templateMappings['2fa_enabled'] == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <!-- 2FA Deaktiviert -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_2fa_disabled" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 0 0-8 0v4h8Z"/>
                    </svg>
                    2FA Deaktiviert
                  </label>
                  <select id="template_2fa_disabled" name="template_2fa_disabled" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo $templateMappings['2fa_disabled'] == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            
            <!-- Tickets (Benachrichtigungen) -->
            <div class="mb-6">
              <h4 class="mb-3 text-md font-semibold text-gray-900 dark:text-white">Tickets</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Ticket erstellt -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_ticket_created" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 18h6m-6 0a3 3 0 0 1-3-3V9m3 3H4m0 0a3 3 0 0 1 3-3m0 0h6m-9 6h6m6 0h6"/>
                    </svg>
                    Ticket erstellt
                  </label>
                  <select id="template_ticket_created" name="template_ticket_created" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo ($templateMappings['ticket_created'] ?? '') == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <!-- Ticket zugewiesen -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_ticket_assigned" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z"/>
                    </svg>
                    Ticket zugewiesen
                  </label>
                  <select id="template_ticket_assigned" name="template_ticket_assigned" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo ($templateMappings['ticket_assigned'] ?? '') == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <!-- Ticket Kommentar -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_ticket_comment" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-5l-5 5v-5Z"/>
                    </svg>
                    Ticket Kommentar
                  </label>
                  <select id="template_ticket_comment" name="template_ticket_comment" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo ($templateMappings['ticket_comment'] ?? '') == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <!-- Ticket Status geändert -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_ticket_status_changed" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 1 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>
                    </svg>
                    Ticket Status geändert
                  </label>
                  <select id="template_ticket_status_changed" name="template_ticket_status_changed" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo ($templateMappings['ticket_status_changed'] ?? '') == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <!-- Ticket geschlossen -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_ticket_closed" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    Ticket geschlossen
                  </label>
                  <select id="template_ticket_closed" name="template_ticket_closed" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo ($templateMappings['ticket_closed'] ?? '') == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <!-- Aufgabe zugewiesen -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_todo_assigned" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    Aufgabe zugewiesen
                  </label>
                  <select id="template_todo_assigned" name="template_todo_assigned" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo ($templateMappings['todo_assigned'] ?? '') == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Kalender -->
            <div class="mb-6">
              <h4 class="mb-3 text-md font-semibold text-gray-900 dark:text-white">Kalender</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_calendar_invite" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/>
                    </svg>
                    Kalender-Einladung
                  </label>
                  <select id="template_calendar_invite" name="template_calendar_invite" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo ($templateMappings['calendar_invite'] ?? '') == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Variablen: {{titel}}, {{zeitStr}}, {{organisator}}, {{beschreibung}}, {{meeting_link}}, {{meeting_button_html}} (nur wenn Meeting vorhanden), {{link}} bzw. {{add_to_calendar_link}} (ICS-Link zum Termin in eigenen Kalender einfügen)</p>
                </div>
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                  <label for="template_calendar_update" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <svg class="inline me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 1 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>
                    </svg>
                    Kalender-Update (Termin geändert)
                  </label>
                  <select id="template_calendar_update" name="template_calendar_update" 
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">-- Keine Zuordnung (Standard-E-Mail) --</option>
                    <?php foreach ($templates as $template): ?>
                      <option value="<?php echo $template['id']; ?>" <?php echo ($templateMappings['calendar_update'] ?? '') == $template['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Wird beim Ändern/Verschieben eines Termins an externe E-Mail-Adressen gesendet. Variablen wie bei Kalender-Einladung.</p>
                </div>
              </div>
            </div>
            <div class="mt-4 flex justify-end">
              <button type="button" id="saveTemplateMappingsBtn" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                Zuordnungen speichern
              </button>
            </div>
          </div>
          
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Vorlagen-Liste -->
            <div class="lg:col-span-1">
              <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-4">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Vorlagen</h3>
                <div id="templates-list" class="space-y-2 max-h-[600px] overflow-y-auto">
                  <?php if (empty($templates)): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Keine Vorlagen vorhanden</p>
                  <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                      <div class="template-item p-3 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" data-template-id="<?php echo $template['id']; ?>">
                        <h4 class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($template['name']); ?></h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo htmlspecialchars($template['subject']); ?></p>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Vorlagen-Editor -->
            <div class="lg:col-span-2">
              <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
                <form id="templateForm" class="space-y-6">
                  <input type="hidden" id="template_id" name="template_id" value="">
                  
                  <!-- Vorlagen-Name -->
                  <div>
                    <label for="template_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Vorlagen-Name</label>
                    <input type="text" id="template_name" name="template_name" required
                           class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                           placeholder="z.B. Benachrichtigung, Willkommens-E-Mail">
                  </div>

                  <!-- Betreff -->
                  <div>
                    <label for="template_subject" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Betreff</label>
                    <input type="text" id="template_subject" name="template_subject" required
                           class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                           placeholder="z.B. {{titel}} - Benachrichtigung">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Verwende {{variable}} für dynamische Werte</p>
                  </div>

                  <!-- Verfügbare Variablen -->
                  <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Verfügbare Variablen</label>
                    <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                      <div class="mb-3">
                        <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Allgemeine Variablen</h5>
                        <div class="flex flex-wrap gap-2">
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{titel}}')">{{titel}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{nachricht}}')">{{nachricht}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{beschreibung}}')">{{beschreibung}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{name}}')">{{name}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{userName}}')">{{userName}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{vorname}}')">{{vorname}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{nachname}}')">{{nachname}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{email}}')">{{email}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{link}}')">{{link}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500" onclick="insertVariable('{{datum}}')">{{datum}}</span>
                        </div>
                      </div>
                      <div class="mt-3 pt-3 border-t border-gray-300 dark:border-gray-600">
                        <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Kalender-Variablen</h5>
                        <div class="flex flex-wrap gap-2">
                          <span class="px-2 py-1 text-xs font-mono bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded border border-purple-300 dark:border-purple-600 cursor-pointer hover:bg-purple-100 dark:hover:bg-purple-900/50" onclick="insertVariable('{{zeitStr}}')">{{zeitStr}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded border border-purple-300 dark:border-purple-600 cursor-pointer hover:bg-purple-100 dark:hover:bg-purple-900/50" onclick="insertVariable('{{organisator}}')">{{organisator}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded border border-purple-300 dark:border-purple-600 cursor-pointer hover:bg-purple-100 dark:hover:bg-purple-900/50" onclick="insertVariable('{{add_to_calendar_link}}')">{{add_to_calendar_link}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded border border-purple-300 dark:border-purple-600 cursor-pointer hover:bg-purple-100 dark:hover:bg-purple-900/50" onclick="insertVariable('{{meeting_button_html}}')">{{meeting_button_html}}</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic">{{add_to_calendar_link}} = ICS-Link zum Termin in eigenen Kalender einfügen</p>
                      </div>
                      <div class="mt-3 pt-3 border-t border-gray-300 dark:border-gray-600">
                        <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Ticket-Variablen</h5>
                        <div class="flex flex-wrap gap-2">
                          <span class="px-2 py-1 text-xs font-mono bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded border border-blue-300 dark:border-blue-600 cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/50" onclick="insertVariable('{{ticketnummer}}')">{{ticketnummer}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded border border-blue-300 dark:border-blue-600 cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/50" onclick="insertVariable('{{ticket_nummer}}')">{{ticket_nummer}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded border border-green-300 dark:border-green-600 cursor-pointer hover:bg-green-100 dark:hover:bg-green-900/50" onclick="insertVariable('{{bestellnummer}}')">{{bestellnummer}}</span>
                          <span class="px-2 py-1 text-xs font-mono bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded border border-green-300 dark:border-green-600 cursor-pointer hover:bg-green-100 dark:hover:bg-green-900/50" onclick="insertVariable('{{auftragsnummer}}')">{{auftragsnummer}}</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic">Ticket-Variablen sind nur bei Ticket-bezogenen E-Mails verfügbar</p>
                      </div>
                      <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Klicke auf eine Variable, um sie einzufügen</p>
                    </div>
                  </div>

                  <!-- E-Mail-Body (HTML-Editor) -->
                  <div>
                    <label for="template_body" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">E-Mail-Inhalt (HTML)</label>
                    <div class="mb-2 flex flex-wrap gap-2">
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{titel}}">Titel</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{nachricht}}">Nachricht</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{beschreibung}}">Beschreibung</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{name}}">Name</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{vorname}}">Vorname</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{nachname}}">Nachname</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{email}}">E-Mail</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{link}}">Link</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600" data-var="{{datum}}">Datum</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-900/50" data-var="{{ticketnummer}}">Ticketnummer</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded hover:bg-purple-200 dark:hover:bg-purple-900/50" data-var="{{zeitStr}}">zeitStr</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded hover:bg-purple-200 dark:hover:bg-purple-900/50" data-var="{{organisator}}">organisator</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded hover:bg-purple-200 dark:hover:bg-purple-900/50" data-var="{{add_to_calendar_link}}">Add-to-Calendar</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded hover:bg-purple-200 dark:hover:bg-purple-900/50" data-var="{{meeting_button_html}}">Meeting-Button</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded hover:bg-green-200 dark:hover:bg-green-900/50" data-var="{{bestellnummer}}">Bestellnummer</button>
                      <button type="button" class="template-var-btn px-3 py-1 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded hover:bg-green-200 dark:hover:bg-green-900/50" data-var="{{auftragsnummer}}">Auftragsnummer</button>
                    </div>
                    <textarea id="template_body" name="template_body" rows="15" required
                              class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 font-mono"
                              placeholder="<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
</head>
<body>
    <h1>{{titel}}</h1>
    <p>{{beschreibung}}</p>
    <p>Hallo {{name}},</p>
    <p>...</p>
</body>
</html>"></textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">HTML-Code mit Variablen in doppelten geschweiften Klammern: {{variable}}</p>
                  </div>

                  <!-- Vorschau -->
                  <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Vorschau</label>
                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                      <div id="template_preview" class="prose dark:prose-invert max-w-none">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Vorschau wird hier angezeigt...</p>
                      </div>
                    </div>
                    <button type="button" id="previewBtn" class="mt-2 px-4 py-2 text-xs text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                      Vorschau aktualisieren
                    </button>
                  </div>

                  <!-- Buttons -->
                  <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" id="deleteTemplateBtn" class="hidden px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 dark:focus:ring-gray-800">
                      Löschen
                    </button>
                    <button type="button" id="testTemplateBtn" class="px-6 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800">
                      Test-E-Mail senden
                    </button>
                    <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                      Speichern
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<!-- Test-E-Mail Modal -->
<div id="testEmailModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
  <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
    <div class="mt-3">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Test-E-Mail senden</h3>
      <form id="testEmailForm" class="space-y-4">
        <div>
          <label for="test_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">E-Mail-Adresse</label>
          <input type="email" id="test_email" name="test_email" required
                 class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                 placeholder="test@example.com">
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" id="closeTestModalBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
            Abbrechen
          </button>
          <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700">
            Senden
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Globale Funktion zum Einfügen von Variablen (auch von außerhalb aufrufbar)
function insertVariable(variable) {
    const textarea = document.getElementById('template_body');
    const subjectInput = document.getElementById('template_subject');
    
    // Prüfe welches Feld fokussiert ist
    if (document.activeElement === subjectInput) {
        insertAtCursor(subjectInput, variable);
    } else {
        insertAtCursor(textarea, variable);
        textarea.focus();
    }
}

// Variable in Cursor-Position einfügen
function insertAtCursor(element, text) {
    const start = element.selectionStart;
    const end = element.selectionEnd;
    const value = element.value;
    
    element.value = value.substring(0, start) + text + value.substring(end);
    element.selectionStart = element.selectionEnd = start + text.length;
    element.focus();
}

document.addEventListener('DOMContentLoaded', function() {
    let currentTemplateId = null;
    
    // Variable-Buttons - Variable in Textarea einfügen
    document.querySelectorAll('.template-var-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const variable = this.getAttribute('data-var');
            insertVariable(variable);
        });
    });
    
    // Vorlage aus Liste auswählen
    document.querySelectorAll('.template-item').forEach(function(item) {
        item.addEventListener('click', function() {
            const templateId = this.getAttribute('data-template-id');
            loadTemplate(templateId);
        });
    });
    
    // Vorlage laden
    async function loadTemplate(templateId) {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>admin/api/email-templates.php?id=' + templateId);
            const data = await response.json();
            
            if (data.success && data.template) {
                currentTemplateId = templateId;
                document.getElementById('template_id').value = templateId;
                document.getElementById('template_name').value = data.template.name;
                document.getElementById('template_subject').value = data.template.subject;
                document.getElementById('template_body').value = data.template.body;
                document.getElementById('deleteTemplateBtn').classList.remove('hidden');
                
                // Aktive Vorlage markieren
                document.querySelectorAll('.template-item').forEach(function(item) {
                    item.classList.remove('bg-primary-50', 'border-primary-300', 'dark:bg-primary-900/20', 'dark:border-primary-600');
                });
                document.querySelector(`[data-template-id="${templateId}"]`).classList.add('bg-primary-50', 'border-primary-300', 'dark:bg-primary-900/20', 'dark:border-primary-600');
                
                updatePreview();
            }
        } catch (error) {
            console.error('Fehler beim Laden der Vorlage:', error);
        }
    }
    
    // Neue Vorlage
    document.getElementById('newTemplateBtn').addEventListener('click', function() {
        currentTemplateId = null;
        document.getElementById('templateForm').reset();
        document.getElementById('template_id').value = '';
        document.getElementById('deleteTemplateBtn').classList.add('hidden');
        document.getElementById('template_preview').innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400">Vorschau wird hier angezeigt...</p>';
        
        // Aktive Markierung entfernen
        document.querySelectorAll('.template-item').forEach(function(item) {
            item.classList.remove('bg-primary-50', 'border-primary-300', 'dark:bg-primary-900/20', 'dark:border-primary-600');
        });
    });
    
    // Vorlage speichern
    document.getElementById('templateForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            id: document.getElementById('template_id').value || null,
            name: document.getElementById('template_name').value,
            subject: document.getElementById('template_subject').value,
            body: document.getElementById('template_body').value
        };
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Speichere...';
        
        try {
            const response = await fetch('<?php echo BASE_URL; ?>admin/api/email-templates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                if (typeof showToast === 'function') {
                    showToast('Vorlage erfolgreich gespeichert', 'success');
                } else {
                    alert('Vorlage erfolgreich gespeichert');
                }
                // Seite neu laden um aktualisierte Liste zu zeigen
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.message || 'Fehler beim Speichern');
            }
        } catch (error) {
            console.error('Fehler:', error);
            if (typeof showToast === 'function') {
                showToast(error.message || 'Fehler beim Speichern der Vorlage', 'error');
            } else {
                alert(error.message || 'Fehler beim Speichern der Vorlage');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    // Vorlage löschen
    document.getElementById('deleteTemplateBtn').addEventListener('click', async function() {
        if (!currentTemplateId) return;
        
        if (!confirm('Möchtest du diese Vorlage wirklich löschen?')) {
            return;
        }
        
        try {
            const response = await fetch('<?php echo BASE_URL; ?>admin/api/email-templates.php?id=' + currentTemplateId, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                if (typeof showToast === 'function') {
                    showToast('Vorlage erfolgreich gelöscht', 'success');
                } else {
                    alert('Vorlage erfolgreich gelöscht');
                }
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.message || 'Fehler beim Löschen');
            }
        } catch (error) {
            console.error('Fehler:', error);
            if (typeof showToast === 'function') {
                showToast(error.message || 'Fehler beim Löschen der Vorlage', 'error');
            } else {
                alert(error.message || 'Fehler beim Löschen der Vorlage');
            }
        }
    });
    
    // Vorschau aktualisieren
    function updatePreview() {
        const body = document.getElementById('template_body').value;
        const preview = document.getElementById('template_preview');
        
        // Variablen durch Beispielwerte ersetzen
        let previewHtml = body
            .replace(/\{\{titel\}\}/g, '<strong>Beispiel Titel</strong>')
            .replace(/\{\{nachricht\}\}/g, 'Dies ist eine Beispiel-Nachricht für die Vorschau.')
            .replace(/\{\{beschreibung\}\}/g, 'Dies ist eine Beispiel-Beschreibung für die Vorschau.')
            .replace(/\{\{name\}\}/g, 'Max Mustermann')
            .replace(/\{\{userName\}\}/g, 'Max Mustermann')
            .replace(/\{\{vorname\}\}/g, 'Max')
            .replace(/\{\{nachname\}\}/g, 'Mustermann')
            .replace(/\{\{email\}\}/g, 'max.mustermann@example.com')
            .replace(/\{\{link\}\}/g, '<a href="#">Beispiel-Link</a>')
            .replace(/\{\{datum\}\}/g, new Date().toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }))
            .replace(/\{\{ticketnummer\}\}/g, '<span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-sm font-mono">TKT-20260123-1234</span>')
            .replace(/\{\{ticket_nummer\}\}/g, '<span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-sm font-mono">TKT-20260123-1234</span>')
            .replace(/\{\{bestellnummer\}\}/g, '<span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded text-sm font-mono">BEST-20260123-5678</span>')
            .replace(/\{\{auftragsnummer\}\}/g, '<span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded text-sm font-mono">BEST-20260123-5678</span>');
        
        preview.innerHTML = previewHtml;
    }
    
    document.getElementById('previewBtn').addEventListener('click', updatePreview);
    document.getElementById('template_body').addEventListener('input', function() {
        // Auto-Update der Vorschau nach kurzer Pause
        clearTimeout(this.previewTimeout);
        this.previewTimeout = setTimeout(updatePreview, 1000);
    });
    
    // Test-E-Mail Modal
    const testModal = document.getElementById('testEmailModal');
    const closeTestModalBtn = document.getElementById('closeTestModalBtn');
    
    document.getElementById('testTemplateBtn').addEventListener('click', function() {
        if (!document.getElementById('template_name').value) {
            alert('Bitte erst eine Vorlage erstellen oder auswählen');
            return;
        }
        testModal.classList.remove('hidden');
    });
    
    closeTestModalBtn.addEventListener('click', function() {
        testModal.classList.add('hidden');
    });
    
    // Test-E-Mail senden
    document.getElementById('testEmailForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('test_email').value;
        const templateId = document.getElementById('template_id').value;
        
        if (!templateId) {
            alert('Bitte erst eine Vorlage speichern');
            return;
        }
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sende...';
        
        try {
            const response = await fetch('<?php echo BASE_URL; ?>admin/api/send-template-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    template_id: templateId,
                    email: email,
                    variables: {
                        titel: 'Test-E-Mail',
                        nachricht: 'Dies ist eine Test-E-Mail mit der Vorlage.',
                        beschreibung: 'Dies ist eine Test-E-Mail mit der Vorlage.',
                        name: 'Max Mustermann',
                        userName: 'Max Mustermann',
                        vorname: 'Max',
                        nachname: 'Mustermann',
                        email: email,
                        link: '<?php echo BASE_URL; ?>dashboard/',
                        datum: new Date().toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
                        ticketnummer: 'TKT-20260123-1234',
                        ticket_nummer: 'TKT-20260123-1234',
                        bestellnummer: 'BEST-20260123-5678',
                        auftragsnummer: 'BEST-20260123-5678'
                    }
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                if (typeof showToast === 'function') {
                    showToast('Test-E-Mail erfolgreich gesendet', 'success');
                } else {
                    alert('Test-E-Mail erfolgreich gesendet');
                }
                testModal.classList.add('hidden');
                document.getElementById('testEmailForm').reset();
            } else {
                throw new Error(data.message || 'Fehler beim Senden');
            }
        } catch (error) {
            console.error('Fehler:', error);
            if (typeof showToast === 'function') {
                showToast(error.message || 'Fehler beim Senden der Test-E-Mail', 'error');
            } else {
                alert(error.message || 'Fehler beim Senden der Test-E-Mail');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    // Initiale Vorschau
    updatePreview();
    
    // Template-Zuordnungen speichern
    document.getElementById('saveTemplateMappingsBtn').addEventListener('click', async function() {
        const btn = this;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Speichere...';
        
        try {
            const mappings = {
                email_template_2fa_enabled: document.getElementById('template_2fa_enabled').value || null,
                email_template_2fa_disabled: document.getElementById('template_2fa_disabled').value || null,
                email_template_ticket_created: document.getElementById('template_ticket_created').value || null,
                email_template_ticket_assigned: document.getElementById('template_ticket_assigned').value || null,
                email_template_ticket_comment: document.getElementById('template_ticket_comment').value || null,
                email_template_ticket_status_changed: document.getElementById('template_ticket_status_changed').value || null,
                email_template_ticket_closed: document.getElementById('template_ticket_closed').value || null,
                email_template_todo_assigned: document.getElementById('template_todo_assigned').value || null,
                email_template_calendar_invite: document.getElementById('template_calendar_invite').value || null,
                email_template_calendar_update: document.getElementById('template_calendar_update').value || null
            };
            
            const response = await fetch('<?php echo BASE_URL; ?>admin/api/email-template-mappings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(mappings)
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                if (typeof showToast === 'function') {
                    showToast('Template-Zuordnungen erfolgreich gespeichert', 'success');
                } else {
                    alert('Template-Zuordnungen erfolgreich gespeichert');
                }
            } else {
                throw new Error(data.message || 'Fehler beim Speichern');
            }
        } catch (error) {
            console.error('Fehler:', error);
            if (typeof showToast === 'function') {
                showToast(error.message || 'Fehler beim Speichern der Zuordnungen', 'error');
            } else {
                alert(error.message || 'Fehler beim Speichern der Zuordnungen');
            }
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
