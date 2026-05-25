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

// Aktuelle IMAP/POP3-Einstellungen aus Datenbank laden
$emailReceiveSettings = [
    'enabled' => false,
    'protocol' => 'imap',
    'host' => '',
    'port' => 993,
    'secure' => 'ssl',
    'username' => '',
    'password' => '',
    'mailbox' => 'INBOX'
];

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'email_receive_%'");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $key = str_replace('email_receive_', '', $row['setting_key']);
        if ($key === 'enabled') {
            $emailReceiveSettings['enabled'] = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
        } elseif ($key === 'port') {
            $emailReceiveSettings['port'] = (int)$row['setting_value'];
        } else {
            $emailReceiveSettings[$key] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    // Tabelle existiert möglicherweise noch nicht, das ist ok
    error_log("Fehler beim Laden der E-Mail-Empfang-Einstellungen: " . $e->getMessage());
}

// SMTP-Einstellungen für Übernahme laden
$smtpSettings = [
    'username' => '',
    'password' => ''
];

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_username', 'smtp_password')");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $key = str_replace('smtp_', '', $row['setting_key']);
        $smtpSettings[$key] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Tabelle existiert möglicherweise noch nicht, das ist ok
    error_log("Fehler beim Laden der SMTP-Einstellungen: " . $e->getMessage());
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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">E-Mail-Empfang</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">E-Mail-Empfang</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">IMAP/POP3-Konfiguration und E-Mail-Abruf</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="col-span-full mx-4">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- IMAP/POP3-Einstellungen -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
              <h3 class="mb-4 border-b border-gray-200 pb-4 text-xl font-semibold text-gray-900 dark:border-gray-700 dark:text-white">E-Mail-Empfang Konfiguration</h3>
              
              <form id="emailReceiveSettingsForm" class="space-y-6">
                <!-- E-Mail-Empfang aktiviert -->
                <div class="flex items-center justify-between">
                  <div>
                    <label class="text-sm font-medium text-gray-900 dark:text-white">E-Mail-Empfang aktivieren</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Aktiviere den E-Mail-Empfang über IMAP/POP3</p>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="email_receive_enabled" name="email_receive_enabled" <?php echo $emailReceiveSettings['enabled'] ? 'checked' : ''; ?> class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>

                <!-- Protokoll -->
                <div>
                  <label for="email_receive_protocol" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Protokoll</label>
                  <select id="email_receive_protocol" name="email_receive_protocol" required
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="imap" <?php echo $emailReceiveSettings['protocol'] === 'imap' ? 'selected' : ''; ?>>IMAP</option>
                    <option value="pop3" <?php echo $emailReceiveSettings['protocol'] === 'pop3' ? 'selected' : ''; ?>>POP3</option>
                  </select>
                </div>

                <!-- Host -->
                <div>
                  <label for="email_receive_host" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Host</label>
                  <input type="text" id="email_receive_host" name="email_receive_host" value="<?php echo htmlspecialchars($emailReceiveSettings['host']); ?>" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="imap.example.com">
                </div>

                <!-- Port -->
                <div>
                  <label for="email_receive_port" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Port</label>
                  <input type="number" id="email_receive_port" name="email_receive_port" value="<?php echo htmlspecialchars($emailReceiveSettings['port']); ?>" required min="1" max="65535"
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="993">
                </div>

                <!-- Verschlüsselung -->
                <div>
                  <label for="email_receive_secure" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Verschlüsselung</label>
                  <select id="email_receive_secure" name="email_receive_secure" required
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="ssl" <?php echo $emailReceiveSettings['secure'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo $emailReceiveSettings['secure'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="none" <?php echo $emailReceiveSettings['secure'] === 'none' ? 'selected' : ''; ?>>Keine</option>
                  </select>
                </div>

                <!-- SMTP-Einstellungen übernehmen -->
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                  <div>
                    <label class="text-sm font-medium text-gray-900 dark:text-white">E-Mail und Passwort von SMTP-Einstellungen übernehmen</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Verwendet die gleichen Zugangsdaten wie beim E-Mail-Versand</p>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="use_smtp_credentials" name="use_smtp_credentials" class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>

                <!-- Benutzername -->
                <div>
                  <label for="email_receive_username" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Benutzername</label>
                  <input type="text" id="email_receive_username" name="email_receive_username" value="<?php echo htmlspecialchars($emailReceiveSettings['username']); ?>" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="benutzer@example.com">
                </div>

                <!-- Passwort -->
                <div>
                  <label for="email_receive_password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Passwort</label>
                  <input type="password" id="email_receive_password" name="email_receive_password" 
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="••••••••">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leer lassen, um das aktuelle Passwort beizubehalten</p>
                </div>

                <!-- Mailbox (nur für IMAP) -->
                <div id="mailbox-container">
                  <label for="email_receive_mailbox" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Mailbox</label>
                  <input type="text" id="email_receive_mailbox" name="email_receive_mailbox" value="<?php echo htmlspecialchars($emailReceiveSettings['mailbox']); ?>"
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="INBOX">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nur für IMAP (Standard: INBOX)</p>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                  <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                    Einstellungen speichern
                  </button>
                </div>
              </form>
            </div>

            <!-- E-Mails abrufen -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
              <h3 class="mb-4 border-b border-gray-200 pb-4 text-xl font-semibold text-gray-900 dark:border-gray-700 dark:text-white">E-Mails abrufen</h3>
              
              <div class="space-y-6">
                <div>
                  <label for="fetch_limit" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Anzahl E-Mails</label>
                  <input type="number" id="fetch_limit" name="fetch_limit" value="10" min="1" max="100"
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="10">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Anzahl der abzurufenden E-Mails (1-100)</p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <button type="button" id="testCronjobBtn" class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    Cronjob testen
                  </button>
                  <button type="button" id="fetchEmailsBtn" class="px-6 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800">
                    E-Mails abrufen
                  </button>
                </div>

                <!-- Cronjob-Log -->
                <div id="cronjob-log-container" class="hidden border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30">
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Cronjob-Log (letzte Ausführung)</h4>
                  </div>
                  <pre id="cronjob-log-output" class="text-xs bg-white dark:bg-gray-800 p-3 rounded overflow-auto max-h-72 text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words"></pre>
                </div>

                <!-- E-Mail-Liste -->
                <div id="emails-container" class="hidden">
                  <h4 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Empfangene E-Mails</h4>
                  <div id="emails-list" class="space-y-3 max-h-96 overflow-y-auto">
                    <!-- E-Mails werden hier dynamisch eingefügt -->
                  </div>
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
// SMTP-Einstellungen für Übernahme
const smtpSettings = {
    username: <?php echo json_encode($smtpSettings['username']); ?>,
    password: <?php echo json_encode($smtpSettings['password']); ?>
};

document.addEventListener('DOMContentLoaded', function() {
    // Mailbox-Feld nur bei IMAP anzeigen
    const protocolSelect = document.getElementById('email_receive_protocol');
    const mailboxContainer = document.getElementById('mailbox-container');
    
    function toggleMailbox() {
        if (protocolSelect.value === 'imap') {
            mailboxContainer.style.display = 'block';
        } else {
            mailboxContainer.style.display = 'none';
        }
    }
    
    protocolSelect.addEventListener('change', toggleMailbox);
    toggleMailbox();
    
    // SMTP-Einstellungen übernehmen
    const useSmtpCredentialsCheckbox = document.getElementById('use_smtp_credentials');
    const emailReceiveUsername = document.getElementById('email_receive_username');
    const emailReceivePassword = document.getElementById('email_receive_password');
    
    if (useSmtpCredentialsCheckbox) {
        useSmtpCredentialsCheckbox.addEventListener('change', function() {
            if (this.checked) {
                // SMTP-Einstellungen laden und übernehmen
                if (smtpSettings.username) {
                    emailReceiveUsername.value = smtpSettings.username;
                }
                // Passwort kann nicht direkt geladen werden, da es verschlüsselt ist
                // Benutzer muss es manuell eingeben oder wir laden es über API
                if (smtpSettings.password) {
                    emailReceivePassword.value = smtpSettings.password;
                } else {
                    // Versuche Passwort über API zu laden
                    loadSmtpPassword();
                }
            }
        });
    }
    
    // Funktion zum Laden des SMTP-Passworts
    async function loadSmtpPassword() {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>admin/api/email-settings.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.password) {
                    emailReceivePassword.value = data.password;
                }
            }
        } catch (error) {
            console.error('Fehler beim Laden des SMTP-Passworts:', error);
        }
    }
    
    // E-Mail-Empfang-Einstellungen speichern
    const emailReceiveForm = document.getElementById('emailReceiveSettingsForm');
    if (emailReceiveForm) {
        emailReceiveForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                email_receive_enabled: document.getElementById('email_receive_enabled').checked,
                email_receive_protocol: document.getElementById('email_receive_protocol').value,
                email_receive_host: document.getElementById('email_receive_host').value,
                email_receive_port: parseInt(document.getElementById('email_receive_port').value),
                email_receive_secure: document.getElementById('email_receive_secure').value,
                email_receive_username: document.getElementById('email_receive_username').value,
                email_receive_password: document.getElementById('email_receive_password').value,
                email_receive_mailbox: document.getElementById('email_receive_mailbox').value
            };
            
            const submitBtn = emailReceiveForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Speichere...';
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>admin/api/email-receive-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast('E-Mail-Empfang-Einstellungen erfolgreich gespeichert', 'success');
                    } else {
                        alert('E-Mail-Empfang-Einstellungen erfolgreich gespeichert');
                    }
                    document.getElementById('email_receive_password').value = '';
                } else {
                    throw new Error(data.message || 'Fehler beim Speichern');
                }
            } catch (error) {
                console.error('Fehler:', error);
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Speichern der Einstellungen', 'error');
                } else {
                    alert(error.message || 'Fehler beim Speichern der Einstellungen');
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }
    
    // E-Mails abrufen
    const fetchEmailsBtn = document.getElementById('fetchEmailsBtn');
    if (fetchEmailsBtn) {
        fetchEmailsBtn.addEventListener('click', async function() {
            const limit = parseInt(document.getElementById('fetch_limit').value) || 10;
            
            fetchEmailsBtn.disabled = true;
            const originalText = fetchEmailsBtn.textContent;
            fetchEmailsBtn.textContent = 'Lade...';
            
            const emailsContainer = document.getElementById('emails-container');
            const emailsList = document.getElementById('emails-list');
            emailsList.innerHTML = '<div class="text-center py-4"><div role="status"><svg aria-hidden="true" class="w-8 h-8 text-neutral-tertiary animate-spin fill-brand mx-auto" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/><path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/></svg><span class="sr-only">Loading...</span></div></div>';
            emailsContainer.classList.remove('hidden');
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>admin/api/fetch-emails.php?limit=' + limit, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                // Response-Text zuerst lesen (kann nur einmal gelesen werden)
                const responseText = await response.text();
                
                // Prüfen ob Response leer ist
                if (!responseText || responseText.trim().length === 0) {
                    throw new Error('Server hat eine leere Antwort zurückgegeben (Status: ' + response.status + ' ' + response.statusText + ')');
                }
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    // Detaillierte Fehlerinformationen
                    const errorDetails = {
                        status: response.status,
                        statusText: response.statusText,
                        contentType: response.headers.get('content-type'),
                        responseText: responseText,
                        responseTextLength: responseText.length,
                        jsonError: jsonError.message,
                        jsonErrorStack: jsonError.stack
                    };
                    console.error('JSON Parse Fehler - Vollständige Details:', errorDetails);
                    
                    // Versuchen, den Anfang des Response-Texts zu analysieren
                    let errorMessage = 'Ungültige Antwort vom Server (Status: ' + response.status + ' ' + response.statusText + ')';
                    if (responseText.length > 0) {
                        errorMessage += '\n\nResponse-Text (erste 1000 Zeichen):\n' + responseText.substring(0, 1000);
                        if (responseText.length > 1000) {
                            errorMessage += '\n\n... (weitere ' + (responseText.length - 1000) + ' Zeichen)';
                        }
                    } else {
                        errorMessage += '\n\nResponse-Text ist leer!';
                    }
                    
                    throw new Error(errorMessage);
                }
                
                // Prüfen ob Response OK ist
                if (!response.ok) {
                    const errorMsg = data && data.message ? data.message : 'HTTP Fehler: ' + response.status + ' ' + response.statusText;
                    console.error('API Fehler:', {
                        status: response.status,
                        statusText: response.statusText,
                        data: data
                    });
                    throw new Error(errorMsg);
                }
                
                if (data && data.success) {
                    if (data.emails && data.emails.length > 0) {
                        emailsList.innerHTML = '';
                        
                        // Erfolgreich konvertierte E-Mails
                        const convertedEmails = data.emails.filter(e => e.converted === true);
                        // Verworfenen E-Mails
                        const rejectedEmails = data.emails.filter(e => e.converted === false);
                        
                        // Erfolgreich konvertierte E-Mails anzeigen
                        convertedEmails.forEach(function(email) {
                            const emailDiv = document.createElement('div');
                            emailDiv.className = 'border border-green-200 dark:border-green-700 rounded-lg p-4 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors';
                            emailDiv.innerHTML = `
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded dark:bg-green-900 dark:text-green-200">✓ Ticket erstellt</span>
                                            ${email.ticket_id ? '<a href="<?php echo BASE_URL; ?>tickets/view.php?id=' + email.ticket_id + '" class="text-xs text-primary-600 hover:underline dark:text-primary-400">Ticket #' + email.ticket_id + '</a>' : ''}
                                        </div>
                                        <h5 class="font-semibold text-gray-900 dark:text-white">${escapeHtml(email.subject || '(Kein Betreff)')}</h5>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Von: ${escapeHtml(email.from || 'Unbekannt')}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">${email.date || ''}</p>
                                    </div>
                                </div>
                                ${email.preview ? '<p class="text-sm text-gray-600 dark:text-gray-400 mt-2 line-clamp-2">' + escapeHtml(email.preview) + '</p>' : ''}
                            `;
                            emailsList.appendChild(emailDiv);
                        });
                        
                        // Verworfenen E-Mails anzeigen
                        rejectedEmails.forEach(function(email) {
                            const emailDiv = document.createElement('div');
                            emailDiv.className = 'border border-red-200 dark:border-red-700 rounded-lg p-4 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors';
                            emailDiv.innerHTML = `
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded dark:bg-red-900 dark:text-red-200">✗ Verworfen</span>
                                        </div>
                                        <h5 class="font-semibold text-gray-900 dark:text-white">${escapeHtml(email.subject || '(Kein Betreff)')}</h5>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Von: ${escapeHtml(email.from || 'Unbekannt')}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">${email.date || ''}</p>
                                        ${email.rejection_reason ? '<div class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded"><p class="text-xs text-red-700 dark:text-red-300"><strong>Grund:</strong> ' + escapeHtml(email.rejection_reason) + '</p></div>' : ''}
                                    </div>
                                </div>
                                ${email.preview ? '<p class="text-sm text-gray-600 dark:text-gray-400 mt-2 line-clamp-2">' + escapeHtml(email.preview) + '</p>' : ''}
                            `;
                            emailsList.appendChild(emailDiv);
                        });
                        
                        // Zusammenfassung anzeigen
                        if (convertedEmails.length > 0 || rejectedEmails.length > 0) {
                            const summaryDiv = document.createElement('div');
                            summaryDiv.className = 'mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700';
                            summaryDiv.innerHTML = `
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Zusammenfassung:</h4>
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <span class="text-green-600 dark:text-green-400">✓ ${convertedEmails.length} E-Mail(s) erfolgreich in Tickets umgewandelt</span>
                                    ${rejectedEmails.length > 0 ? '<span class="text-red-600 dark:text-red-400">✗ ' + rejectedEmails.length + ' E-Mail(s) verworfen</span>' : ''}
                                </div>
                            `;
                            emailsList.insertBefore(summaryDiv, emailsList.firstChild);
                        }
                    } else {
                        emailsList.innerHTML = '<div class="text-center py-4 text-gray-500 dark:text-gray-400">Keine E-Mails gefunden</div>';
                    }
                    
                    if (typeof showToast === 'function') {
                        const message = data.tickets_created > 0 
                            ? `${data.tickets_created} Ticket(s) erstellt${data.emails_rejected > 0 ? ', ' + data.emails_rejected + ' E-Mail(s) verworfen' : ''}`
                            : `${data.emails_rejected} E-Mail(s) verworfen`;
                        showToast(message, data.tickets_created > 0 ? 'success' : 'warning');
                    }
                } else {
                    const errorMsg = data && data.message ? data.message : 'Unbekannter Fehler beim Abrufen der E-Mails';
                    console.error('API Response Fehler:', data);
                    throw new Error(errorMsg);
                }
            } catch (error) {
                console.error('Fehler beim Abrufen der E-Mails:', {
                    message: error.message,
                    stack: error.stack,
                    name: error.name
                });
                
                // Detaillierte Fehleranzeige
                const errorDiv = document.createElement('div');
                errorDiv.className = 'border border-red-300 dark:border-red-700 rounded-lg p-4 bg-red-50 dark:bg-red-900/20';
                errorDiv.innerHTML = `
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Fehler beim Abrufen der E-Mails</h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                <p>${escapeHtml(error.message)}</p>
                                ${error.stack ? '<details class="mt-2"><summary class="cursor-pointer text-xs">Technische Details anzeigen</summary><pre class="mt-2 text-xs bg-red-100 dark:bg-red-900/40 p-2 rounded overflow-auto">' + escapeHtml(error.stack) + '</pre></details>' : ''}
                            </div>
                        </div>
                    </div>
                `;
                emailsList.innerHTML = '';
                emailsList.appendChild(errorDiv);
                
                if (typeof showToast === 'function') {
                    showToast('Fehler: ' + error.message, 'error');
                } else {
                    alert('Fehler beim Abrufen der E-Mails: ' + error.message);
                }
            } finally {
                fetchEmailsBtn.disabled = false;
                fetchEmailsBtn.textContent = originalText;
            }
        });
    }
    
    // Cronjob testen
    const testCronjobBtn = document.getElementById('testCronjobBtn');
    if (testCronjobBtn) {
        testCronjobBtn.addEventListener('click', async function() {
            testCronjobBtn.disabled = true;
            const originalText = testCronjobBtn.textContent;
            testCronjobBtn.textContent = 'Läuft...';
            const cronLogContainer = document.getElementById('cronjob-log-container');
            const cronLogOutput = document.getElementById('cronjob-log-output');
            
            const emailsContainer = document.getElementById('emails-container');
            const emailsList = document.getElementById('emails-list');
            emailsList.innerHTML = '<div class="text-center py-4"><div role="status"><svg aria-hidden="true" class="w-8 h-8 text-neutral-tertiary animate-spin fill-brand mx-auto" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/><path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/></svg><span class="sr-only">Loading...</span></div></div>';
            emailsContainer.classList.remove('hidden');
            if (cronLogContainer && cronLogOutput) {
                cronLogContainer.classList.remove('hidden');
                cronLogOutput.textContent = 'Cronjob wird ausgefuehrt...';
            }
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>admin/api/test-cronjob.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    throw new Error('Ungültige Antwort vom Server: ' + responseText.substring(0, 500));
                }
                
                if (response.ok && data.success) {
                    // Erfolgreich
                    emailsList.innerHTML = '';
                    if (cronLogContainer && cronLogOutput) {
                        const parts = [];
                        if (data.output) {
                            parts.push('=== AUSGABE ===\n' + data.output.trim());
                        }
                        if (data.log) {
                            parts.push('=== LOG-DATEI ===\n' + data.log.trim());
                        }
                        cronLogOutput.textContent = parts.length > 0
                            ? parts.join('\n\n')
                            : (data.message || 'Keine Log-Ausgabe vorhanden.');
                    }
                    
                    const resultDiv = document.createElement('div');
                    resultDiv.className = 'border border-green-200 dark:border-green-700 rounded-lg p-4 bg-green-50 dark:bg-green-900/20';
                    resultDiv.innerHTML = `
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <h3 class="text-sm font-medium text-green-800 dark:text-green-200">Cronjob erfolgreich ausgeführt</h3>
                                <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                                    <p class="mb-2">${escapeHtml(data.message || 'Cronjob wurde erfolgreich ausgeführt')}</p>
                                    ${data.output ? '<details class="mt-2"><summary class="cursor-pointer font-semibold">Ausgabe anzeigen</summary><pre class="mt-2 text-xs bg-white dark:bg-gray-800 p-3 rounded overflow-auto max-h-64">' + escapeHtml(data.output) + '</pre></details>' : ''}
                                    ${data.log ? '<details class="mt-2"><summary class="cursor-pointer font-semibold">Log-Datei anzeigen</summary><pre class="mt-2 text-xs bg-white dark:bg-gray-800 p-3 rounded overflow-auto max-h-64">' + escapeHtml(data.log) + '</pre></details>' : ''}
                                </div>
                            </div>
                        </div>
                    `;
                    emailsList.appendChild(resultDiv);
                    
                    if (typeof showToast === 'function') {
                        showToast('Cronjob erfolgreich ausgeführt', 'success');
                    }
                } else {
                    // Fehler
                    if (cronLogContainer && cronLogOutput) {
                        const parts = [];
                        if (data.message) {
                            parts.push('=== FEHLER ===\n' + data.message);
                        }
                        if (data.output) {
                            parts.push('=== AUSGABE ===\n' + data.output.trim());
                        }
                        if (data.log) {
                            parts.push('=== LOG-DATEI ===\n' + data.log.trim());
                        }
                        if (data.trace) {
                            parts.push('=== STACK TRACE ===\n' + data.trace.trim());
                        }
                        cronLogOutput.textContent = parts.join('\n\n');
                    }
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'border border-red-300 dark:border-red-700 rounded-lg p-4 bg-red-50 dark:bg-red-900/20';
                    errorDiv.innerHTML = `
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Fehler beim Ausführen des Cronjobs</h3>
                                <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                    <p>${escapeHtml(data.message || 'Unbekannter Fehler')}</p>
                                    ${data.trace ? '<details class="mt-2"><summary class="cursor-pointer text-xs">Stack Trace anzeigen</summary><pre class="mt-2 text-xs bg-red-100 dark:bg-red-900/40 p-2 rounded overflow-auto">' + escapeHtml(data.trace) + '</pre></details>' : ''}
                                </div>
                            </div>
                        </div>
                    `;
                    emailsList.innerHTML = '';
                    emailsList.appendChild(errorDiv);
                    
                    if (typeof showToast === 'function') {
                        showToast('Fehler: ' + (data.message || 'Unbekannter Fehler'), 'error');
                    }
                }
            } catch (error) {
                console.error('Fehler beim Testen des Cronjobs:', error);
                if (cronLogContainer && cronLogOutput) {
                    cronLogContainer.classList.remove('hidden');
                    cronLogOutput.textContent = 'Fehler beim Testen des Cronjobs:\n' + error.message;
                }
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'border border-red-300 dark:border-red-700 rounded-lg p-4 bg-red-50 dark:bg-red-900/20';
                errorDiv.innerHTML = `
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Fehler beim Ausführen des Cronjobs</h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                <p>${escapeHtml(error.message)}</p>
                            </div>
                        </div>
                    </div>
                `;
                emailsList.innerHTML = '';
                emailsList.appendChild(errorDiv);
                
                if (typeof showToast === 'function') {
                    showToast('Fehler: ' + error.message, 'error');
                }
            } finally {
                testCronjobBtn.disabled = false;
                testCronjobBtn.textContent = originalText;
            }
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
