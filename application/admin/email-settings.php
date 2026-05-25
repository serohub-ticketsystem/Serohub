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

// Aktuelle SMTP-Einstellungen aus Datenbank laden
$smtpSettings = [
    'enabled' => false,
    'host' => '',
    'port' => 587,
    'secure' => 'tls',
    'username' => '',
    'from_email' => '',
    'from_name' => '',
    'support_email' => ''
];

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%' OR setting_key = 'support_email'");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $key = str_replace('smtp_', '', $row['setting_key']);
        if ($key === 'enabled') {
            $smtpSettings['enabled'] = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
        } elseif ($key === 'port') {
            $smtpSettings['port'] = (int)$row['setting_value'];
        } elseif ($row['setting_key'] === 'support_email') {
            $smtpSettings['support_email'] = trim((string)$row['setting_value']);
        } else {
            $smtpSettings[$key] = $row['setting_value'];
        }
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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">E-Mail-Einstellungen</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">E-Mail-Einstellungen</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">SMTP-Konfiguration und Test-E-Mails</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="col-span-full mx-4">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- SMTP-Einstellungen -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
              <h3 class="mb-4 border-b border-gray-200 pb-4 text-xl font-semibold text-gray-900 dark:border-gray-700 dark:text-white">SMTP-Konfiguration</h3>
              
              <form id="smtpSettingsForm" class="space-y-6">
                <!-- SMTP aktiviert -->
                <div class="flex items-center justify-between">
                  <div>
                    <label class="text-sm font-medium text-gray-900 dark:text-white">SMTP aktivieren</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Verwende SMTP statt native PHP mail() Funktion</p>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="smtp_enabled" name="smtp_enabled" <?php echo $smtpSettings['enabled'] ? 'checked' : ''; ?> class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>

                <!-- SMTP Host -->
                <div>
                  <label for="smtp_host" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">SMTP Host</label>
                  <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtpSettings['host']); ?>" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="smtp.example.com">
                </div>

                <!-- SMTP Port -->
                <div>
                  <label for="smtp_port" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">SMTP Port</label>
                  <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtpSettings['port']); ?>" required min="1" max="65535"
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="587">
                </div>

                <!-- SMTP Secure -->
                <div>
                  <label for="smtp_secure" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Verschlüsselung</label>
                  <select id="smtp_secure" name="smtp_secure" required
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="tls" <?php echo $smtpSettings['secure'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo $smtpSettings['secure'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                  </select>
                </div>

                <!-- SMTP Benutzername -->
                <div>
                  <label for="smtp_username" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Benutzername</label>
                  <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtpSettings['username']); ?>" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="benutzer@example.com">
                </div>

                <!-- SMTP Passwort -->
                <div>
                  <label for="smtp_password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Passwort</label>
                  <input type="password" id="smtp_password" name="smtp_password" 
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="••••••••">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leer lassen, um das aktuelle Passwort beizubehalten</p>
                </div>

                <!-- Absender E-Mail -->
                <div>
                  <label for="smtp_from_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Absender E-Mail</label>
                  <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($smtpSettings['from_email']); ?>" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="noreply@example.com">
                </div>

                <!-- Absender Name -->
                <div>
                  <label for="smtp_from_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Absender Name</label>
                  <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($smtpSettings['from_name']); ?>" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="Serohub">
                </div>

                <!-- Support E-Mail -->
                <div>
                  <label for="support_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Support E-Mail</label>
                  <input type="email" id="support_email" name="support_email" value="<?php echo htmlspecialchars($smtpSettings['support_email']); ?>"
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="support@example.com">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Diese Adresse wird im System als Kontakt für Hilfe und Support angezeigt.</p>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                  <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                    Einstellungen speichern
                  </button>
                </div>
              </form>
            </div>

            <!-- Test-E-Mail -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
              <h3 class="mb-4 border-b border-gray-200 pb-4 text-xl font-semibold text-gray-900 dark:border-gray-700 dark:text-white">Test-E-Mail senden</h3>
              
              <form id="testEmailForm" class="space-y-6">
                <div>
                  <label for="test_email_address" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">E-Mail-Adresse</label>
                  <input type="email" id="test_email_address" name="test_email_address" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="test@example.com">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">An diese Adresse wird eine Test-E-Mail gesendet</p>
                </div>

                <div>
                  <label for="test_email_subject" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Betreff</label>
                  <input type="text" id="test_email_subject" name="test_email_subject" value="Test-E-Mail vom Serohub" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="Test-E-Mail">
                </div>

                <div>
                  <label for="test_email_message" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Nachricht</label>
                  <textarea id="test_email_message" name="test_email_message" rows="4" required
                            class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                            placeholder="Dies ist eine Test-E-Mail...">Dies ist eine Test-E-Mail vom Serohub. Wenn Sie diese E-Mail erhalten haben, funktioniert die SMTP-Konfiguration korrekt.</textarea>
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                  <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800">
                    Test-E-Mail senden
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // SMTP-Einstellungen speichern
    const smtpForm = document.getElementById('smtpSettingsForm');
    if (smtpForm) {
        smtpForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                smtp_enabled: document.getElementById('smtp_enabled').checked,
                smtp_host: document.getElementById('smtp_host').value,
                smtp_port: parseInt(document.getElementById('smtp_port').value),
                smtp_secure: document.getElementById('smtp_secure').value,
                smtp_username: document.getElementById('smtp_username').value,
                smtp_password: document.getElementById('smtp_password').value,
                smtp_from_email: document.getElementById('smtp_from_email').value,
                smtp_from_name: document.getElementById('smtp_from_name').value,
                support_email: document.getElementById('support_email').value
            };
            
            const submitBtn = smtpForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Speichere...';
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>admin/api/email-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast('SMTP-Einstellungen erfolgreich gespeichert', 'success');
                    } else {
                        alert('SMTP-Einstellungen erfolgreich gespeichert');
                    }
                    // Passwort-Feld leeren
                    document.getElementById('smtp_password').value = '';
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
    
    // Test-E-Mail senden
    const testEmailForm = document.getElementById('testEmailForm');
    if (testEmailForm) {
        testEmailForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                email: document.getElementById('test_email_address').value,
                subject: document.getElementById('test_email_subject').value,
                message: document.getElementById('test_email_message').value
            };
            
            const submitBtn = testEmailForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sende...';
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>admin/api/test-email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    const text = await response.text();
                    console.error('JSON Parse Error:', text);
                    throw new Error('Ungültige Antwort vom Server: ' + text.substring(0, 100));
                }
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast(data.message || 'Test-E-Mail erfolgreich gesendet', 'success');
                    } else {
                        alert(data.message || 'Test-E-Mail erfolgreich gesendet');
                    }
                } else {
                    throw new Error(data.message || 'Fehler beim Senden der E-Mail');
                }
            } catch (error) {
                console.error('Fehler:', error);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack
                });
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Senden der Test-E-Mail. Bitte überprüfe die Browser-Konsole für Details.', 'error');
                } else {
                    alert(error.message || 'Fehler beim Senden der Test-E-Mail. Bitte überprüfe die Browser-Konsole für Details.');
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
