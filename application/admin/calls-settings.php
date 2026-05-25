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

// Aktuelle SIP/Anruf-Einstellungen aus Datenbank laden
$callsSettings = [
    'enabled' => false,
    'server' => '',
    'username' => '',
    'password' => '',
    'display_name' => ''
];

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'calls_%'");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $key = str_replace('calls_', '', $row['setting_key']);
        if ($key === 'enabled') {
            $callsSettings['enabled'] = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $callsSettings[$key] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    // Tabelle existiert möglicherweise noch nicht, das ist ok
    error_log("Fehler beim Laden der Anruf-Einstellungen: " . $e->getMessage());
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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Anrufe einstellen</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Anrufe einstellen</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">SIP-Konfiguration für Anrufe</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="col-span-full mx-4">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- SIP-Einstellungen -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
              <h3 class="mb-4 border-b border-gray-200 pb-4 text-xl font-semibold text-gray-900 dark:border-gray-700 dark:text-white">SIP-Konfiguration</h3>
              
              <form id="callsSettingsForm" class="space-y-6">
                <!-- Anrufe aktiviert -->
                <div class="flex items-center justify-between">
                  <div>
                    <label class="text-sm font-medium text-gray-900 dark:text-white">Anrufe aktivieren</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Aktiviere die SIP-Anruf-Funktion</p>
                  </div>
                  <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="calls_enabled" name="calls_enabled" <?php echo $callsSettings['enabled'] ? 'checked' : ''; ?> class="peer sr-only">
                    <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-primary-800"></div>
                  </label>
                </div>

                <!-- SIP-Server -->
                <div>
                  <label for="calls_server" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">SIP-Server (WebSocket URL)</label>
                  <input type="text" id="calls_server" name="calls_server" value="<?php echo htmlspecialchars($callsSettings['server']); ?>" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="wss://voip.easybell.de">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">z.B. wss://voip.easybell.de oder wss://pbx.easybell.de (Easybell)</p>
                </div>

                <!-- Benutzername -->
                <div>
                  <label for="calls_username" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Benutzername</label>
                  <input type="text" id="calls_username" name="calls_username" value="<?php echo htmlspecialchars($callsSettings['username']); ?>" required
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="nummer@sip.easybell.de">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bei Easybell: vollständige SIP-Adresse (z.B. 0123456789@sip.easybell.de)</p>
                </div>

                <!-- Passwort -->
                <div>
                  <label for="calls_password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Passwort</label>
                  <input type="password" id="calls_password" name="calls_password" 
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="••••••••">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leer lassen, um das aktuelle Passwort beizubehalten</p>
                </div>

                <!-- Anzeigename -->
                <div>
                  <label for="calls_display_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Anzeigename (optional)</label>
                  <input type="text" id="calls_display_name" name="calls_display_name" value="<?php echo htmlspecialchars($callsSettings['display_name']); ?>"
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                         placeholder="Ihr Name">
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                  <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                    Einstellungen speichern
                  </button>
                </div>
              </form>
            </div>

            <!-- Test-Funktion -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
              <h3 class="mb-4 border-b border-gray-200 pb-4 text-xl font-semibold text-gray-900 dark:border-gray-700 dark:text-white">Verbindung testen</h3>
              
              <div class="space-y-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Testen Sie die SIP-Verbindung mit den aktuellen Einstellungen. 
                  Die Verbindung wird für einige Sekunden aufgebaut und dann wieder getrennt.
                </p>

                <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                  <button type="button" id="testCallsBtn" class="px-6 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800">
                    Verbindung testen
                  </button>
                </div>

                <!-- Test-Ergebnis -->
                <div id="test-result-container" class="hidden">
                  <h4 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Test-Ergebnis</h4>
                  <div id="test-result" class="space-y-3 max-h-96 overflow-y-auto">
                    <!-- Test-Ergebnis wird hier dynamisch eingefügt -->
                  </div>
                </div>
              </div>
            </div>

            <!-- Informationen -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
              <h3 class="mb-4 border-b border-gray-200 pb-4 text-xl font-semibold text-gray-900 dark:border-gray-700 dark:text-white">Informationen</h3>
              
              <div class="space-y-4">
                <div>
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Anruf-Funktion</h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Diese Einstellungen konfigurieren die SIP-Anruf-Funktion für das System. 
                    Nach der Konfiguration können Benutzer über die Anruf-Seite (<a href="<?php echo BASE_URL; ?>calls/" class="text-primary-600 hover:text-primary-800 dark:text-primary-400"><?php echo BASE_URL; ?>calls/</a>) 
                    Anrufe tätigen und verwalten.
                  </p>
                </div>

                <div>
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">SIP-Server (WebSocket URL)</h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Der SIP-Server muss eine <strong>WebSocket-URL</strong> im Format <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">wss://server:port/ws</code> 
                    oder <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">ws://server:port/ws</code> sein.
                  </p>
                  <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">⚠ Wichtig: Easybell unterstützt kein WebSocket</p>
                    <p class="text-xs text-yellow-700 dark:text-yellow-300 mb-2">
                      Easybell unterstützt nur UDP/TCP SIP, nicht WebSocket SIP. Für Browser-basierte Anrufe mit JsSIP benötigen Sie:
                    </p>
                    <ul class="text-xs text-yellow-700 dark:text-yellow-300 list-disc list-inside space-y-1">
                      <li>Einen SIP-WebSocket-Gateway/Proxy (z.B. Asterisk mit WebSocket-Support)</li>
                      <li>Oder einen anderen VoIP-Anbieter mit WebSocket-Support</li>
                    </ul>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mt-3">
                    <strong>Beispiel WebSocket-Gateway:</strong> <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">wss://sip-gateway.example.com:8089/ws</code>
                  </p>
                </div>

                <div>
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Sicherheit</h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Das Passwort wird verschlüsselt in der Datenbank gespeichert. 
                    Lassen Sie das Passwort-Feld leer, um das aktuelle Passwort beizubehalten.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<!-- JsSIP Bibliothek lokal laden (keine CORS-Probleme) -->
<script src="<?php echo BASE_URL; ?>assets/js/jssip.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funktion zum Prüfen ob JsSIP geladen ist
    function checkJsSIP() {
        if (typeof JsSIP !== 'undefined') {
            const testBtn = document.getElementById('testCallsBtn');
            if (testBtn) {
                testBtn.disabled = false;
                testBtn.textContent = 'Verbindung testen';
                testBtn.title = '';
            }
            return true;
        }
        return false;
    }
    
    // Mehrfach prüfen mit zunehmenden Intervallen
    let attempts = 0;
    const maxAttempts = 30; // 15 Sekunden insgesamt (länger warten)
    
    function tryCheckJsSIP() {
        attempts++;
        
        if (checkJsSIP()) {
            return; // Erfolgreich geladen
        }
        
        if (attempts < maxAttempts) {
            // Nächste Prüfung in 500ms
            setTimeout(tryCheckJsSIP, 500);
        } else {
            // Nach mehreren Versuchen immer noch nicht geladen
            console.error('JsSIP wurde nach ' + (maxAttempts * 500 / 1000) + ' Sekunden nicht geladen.');
            console.error('Prüfe Proxy-URL:', '<?php echo BASE_URL; ?>admin/api/jssip-proxy.php');
            
            const testBtn = document.getElementById('testCallsBtn');
            if (testBtn) {
                testBtn.disabled = true;
                
                // Detaillierte Fehlermeldung
                let errorMsg = 'JsSIP nicht verfügbar';
                if (window.jsSIPLoadError) {
                    errorMsg = 'JsSIP konnte nicht geladen werden (CDN-Fehler)';
                } else {
                    errorMsg = 'JsSIP wurde nicht gefunden';
                }
                testBtn.textContent = errorMsg;
                testBtn.title = 'Die JsSIP-Bibliothek konnte nicht geladen werden. Mögliche Ursachen:\n' +
                    '- Proxy-Fehler (prüfen Sie die Browser-Konsole)\n' +
                    '- Keine Internetverbindung\n' +
                    '- CDN wird blockiert (Firewall, Ad-Blocker)\n' +
                    '- Content Security Policy blockiert externe Scripts\n' +
                    'Bitte laden Sie die Seite neu oder kontaktieren Sie den Administrator.';
            }
        }
    }
    
    // Starte Prüfung nach längerer Verzögerung (damit Script Zeit zum Laden hat)
    setTimeout(tryCheckJsSIP, 500);
    
    // Anruf-Einstellungen speichern
    const callsSettingsForm = document.getElementById('callsSettingsForm');
    if (callsSettingsForm) {
        callsSettingsForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                calls_enabled: document.getElementById('calls_enabled').checked,
                calls_server: document.getElementById('calls_server').value,
                calls_username: document.getElementById('calls_username').value,
                calls_password: document.getElementById('calls_password').value,
                calls_display_name: document.getElementById('calls_display_name').value
            };
            
            const submitBtn = callsSettingsForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Speichere...';
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>admin/api/calls-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast('Anruf-Einstellungen erfolgreich gespeichert', 'success');
                    } else {
                        alert('Anruf-Einstellungen erfolgreich gespeichert');
                    }
                    document.getElementById('calls_password').value = '';
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
    
    // Test-Funktion
    const testCallsBtn = document.getElementById('testCallsBtn');
    const testResultContainer = document.getElementById('test-result-container');
    const testResult = document.getElementById('test-result');
    let testUA = null;
    
    if (testCallsBtn) {
        testCallsBtn.addEventListener('click', async function() {
            // Prüfen ob JsSIP geladen ist
            if (typeof JsSIP === 'undefined') {
                if (typeof showToast === 'function') {
                    showToast('JsSIP-Bibliothek wurde nicht geladen. Bitte laden Sie die Seite neu oder prüfen Sie Ihre Internetverbindung.', 'error');
                } else {
                    alert('JsSIP-Bibliothek wurde nicht geladen. Bitte laden Sie die Seite neu oder prüfen Sie Ihre Internetverbindung.');
                }
                return;
            }
            // Einstellungen aus Formular holen
            const server = document.getElementById('calls_server').value.trim();
            const username = document.getElementById('calls_username').value.trim();
            const password = document.getElementById('calls_password').value.trim();
            const displayName = document.getElementById('calls_display_name').value.trim();
            
            // Wenn Passwort leer ist, versuche es aus der API zu holen
            let testPassword = password;
            if (!testPassword) {
                try {
                    const response = await fetch('<?php echo BASE_URL; ?>admin/api/test-calls.php', {
                        method: 'GET'
                    });
                    const data = await response.json();
                    if (data.success && data.settings && data.settings.password) {
                        testPassword = data.settings.password;
                    }
                } catch (e) {
                    console.error('Fehler beim Laden des Passworts:', e);
                }
            }
            
            if (!server || !username || !testPassword) {
                if (typeof showToast === 'function') {
                    showToast('Bitte füllen Sie alle Pflichtfelder aus (Server, Benutzername, Passwort)', 'error');
                } else {
                    alert('Bitte füllen Sie alle Pflichtfelder aus (Server, Benutzername, Passwort)');
                }
                return;
            }
            
            testCallsBtn.disabled = true;
            const originalText = testCallsBtn.textContent;
            testCallsBtn.textContent = 'Teste...';
            testResultContainer.classList.remove('hidden');
            testResult.innerHTML = '<div class="text-center py-4"><div role="status"><svg aria-hidden="true" class="w-8 h-8 text-neutral-tertiary animate-spin fill-brand mx-auto" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/><path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/></svg><span class="sr-only">Loading...</span></div></div>';
            
            // Log-Funktionen ZUERST definieren
            const logMessages = [];
            const addLog = (message, type = 'info') => {
                const timestamp = new Date().toLocaleTimeString('de-DE');
                logMessages.push({timestamp, message, type});
                updateTestResult();
            };
            
            const updateTestResult = () => {
                testResult.innerHTML = logMessages.map(log => {
                    const colorClass = {
                        'info': 'text-blue-600 dark:text-blue-400',
                        'success': 'text-green-600 dark:text-green-400',
                        'error': 'text-red-600 dark:text-red-400',
                        'warning': 'text-yellow-600 dark:text-yellow-400'
                    }[log.type] || 'text-gray-600 dark:text-gray-400';
                    
                    return `
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400">${log.timestamp}</span>
                                <span class="flex-1 ${colorClass}">${escapeHtml(log.message)}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            };
            
            // Prüfen ob Server-URL ein WebSocket-Protokoll hat
            if (!server.match(/^wss?:\/\//i)) {
                addLog('⚠ Wichtig: Easybell unterstützt kein WebSocket (wss://)', 'warning');
                addLog('JsSIP benötigt eine WebSocket-Verbindung für Browser-basierte Anrufe', 'warning');
                addLog('Für Easybell benötigen Sie einen SIP-WebSocket-Gateway/Proxy', 'warning');
                addLog('', 'info');
                addLog('Alternative Lösungen:', 'info');
                addLog('1. SIP-WebSocket-Gateway verwenden (z.B. Asterisk mit WebSocket-Support)', 'info');
                addLog('2. SIP-Softphone verwenden (nicht Browser-basiert)', 'info');
                addLog('3. Anderen VoIP-Anbieter mit WebSocket-Support verwenden', 'info');
                addLog('', 'info');
                addLog('Test abgebrochen - WebSocket-URL erforderlich', 'error');
                
                testCallsBtn.disabled = false;
                testCallsBtn.textContent = originalText;
                return;
            }
            
            try {
                addLog('Starte SIP-Verbindungstest...', 'info');
                addLog(`Server: ${server}`, 'info');
                addLog(`Benutzername: ${username}`, 'info');
                
                // Alte Verbindung trennen falls vorhanden
                if (testUA) {
                    try {
                        testUA.stop();
                    } catch (e) {
                        // Ignorieren
                    }
                }
                
                // Server-Domain extrahieren für SIP-URI
                // Bei Easybell ist der Benutzername bereits vollständig (z.B. nummer@sip.easybell.de)
                let sipUri = username;
                let serverDomain = '';
                
                // Prüfen ob Benutzername bereits vollständige SIP-URI ist
                if (username.includes('@')) {
                    // Benutzername ist bereits vollständig (z.B. nummer@sip.easybell.de)
                    sipUri = 'sip:' + username;
                    // Domain aus Benutzername extrahieren
                    serverDomain = username.split('@')[1];
                } else {
                    // Benutzername ist nur die Nummer, Domain aus Server-URL extrahieren
                    const serverUrl = server.replace(/^wss?:\/\//, '').split('/')[0];
                    const serverParts = serverUrl.split(':');
                    serverDomain = serverParts[0];
                    sipUri = 'sip:' + username + '@' + serverDomain;
                }
                
                addLog(`Verbinde mit Server: ${server}...`, 'info');
                addLog(`SIP-URI: ${sipUri}`, 'info');
                
                const socket = new JsSIP.WebSocketInterface(server);
                
                const configuration = {
                    sockets: [socket],
                    uri: sipUri,
                    password: testPassword,
                    display_name: displayName || username,
                    register: true,
                    // Zusätzliche Optionen für bessere Kompatibilität
                    connection_recovery_min_interval: 2,
                    connection_recovery_max_interval: 30
                };
                
                testUA = new JsSIP.UA(configuration);
                
                let registered = false;
                let testTimeout = null;
                
                testUA.on('registered', function() {
                    registered = true;
                    addLog('✓ Erfolgreich beim SIP-Server registriert', 'success');
                    
                    // Nach 3 Sekunden trennen
                    testTimeout = setTimeout(() => {
                        addLog('Trenne Test-Verbindung...', 'info');
                        testUA.stop();
                        addLog('✓ Test-Verbindung erfolgreich getrennt', 'success');
                        addLog('Test abgeschlossen - Verbindung funktioniert!', 'success');
                        
                        testCallsBtn.disabled = false;
                        testCallsBtn.textContent = originalText;
                    }, 3000);
                });
                
                testUA.on('registrationFailed', function(e) {
                    addLog('✗ Registrierung fehlgeschlagen', 'error');
                    if (e.cause) {
                        addLog(`Fehler: ${e.cause}`, 'error');
                    }
                    addLog('Bitte überprüfen Sie Benutzername, Passwort und Server-URL', 'warning');
                    
                    testCallsBtn.disabled = false;
                    testCallsBtn.textContent = originalText;
                });
                
                testUA.on('unregistered', function() {
                    if (!registered) {
                        addLog('Verbindung getrennt (vor Registrierung)', 'warning');
                    }
                });
                
                testUA.on('disconnected', function() {
                    if (testTimeout) {
                        clearTimeout(testTimeout);
                    }
                });
                
                testUA.on('connected', function() {
                    addLog('WebSocket-Verbindung hergestellt', 'success');
                });
                
                testUA.on('disconnected', function(e) {
                    if (e.error) {
                        addLog(`WebSocket-Fehler: ${e.error}`, 'error');
                    }
                });
                
                // Timeout nach 10 Sekunden
                setTimeout(() => {
                    if (!registered) {
                        addLog('✗ Timeout: Keine Antwort vom Server innerhalb von 10 Sekunden', 'error');
                        addLog('Bitte überprüfen Sie die Server-URL und die Netzwerkverbindung', 'warning');
                        if (testUA) {
                            try {
                                testUA.stop();
                            } catch (e) {
                                // Ignorieren
                            }
                        }
                        testCallsBtn.disabled = false;
                        testCallsBtn.textContent = originalText;
                    }
                }, 10000);
                
                testUA.start();
                addLog('Starte SIP-Client...', 'info');
                
            } catch (error) {
                console.error('Fehler:', error);
                addLog(`✗ Fehler beim Test: ${error.message}`, 'error');
                testCallsBtn.disabled = false;
                testCallsBtn.textContent = originalText;
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
