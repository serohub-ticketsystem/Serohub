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
$trustedDevices = [];

try {
    // Vertraute Geräte abrufen
    $stmt = $pdo->prepare("
        SELECT id, device_name, user_agent, ip_address, last_used, created_at
        FROM trusted_devices
        WHERE user_id = ?
        ORDER BY last_used DESC
    ");
    $stmt->execute([$userId]);
    $trustedDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Browser-Name aus User-Agent extrahieren
    foreach ($trustedDevices as &$device) {
        $userAgent = $device['user_agent'] ?? '';
        $device['browser'] = 'Unbekannt';
        $device['os'] = 'Unbekannt';
        
        // Browser erkennen
        if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera|MSIE)[\/\s](\d+\.\d+)/i', $userAgent, $matches)) {
            $device['browser'] = $matches[1];
        }
        
        // OS erkennen
        if (preg_match('/(Windows NT|Windows|Mac OS X|Linux|iPhone|iPad|Android)/i', $userAgent, $matches)) {
            $os = $matches[1];
            if ($os === 'Windows NT') $os = 'Windows';
            if ($os === 'Mac OS X') $os = 'macOS';
            $device['os'] = $os;
        }
        
        // Falls kein Gerätename gesetzt, automatisch generieren
        if (empty($device['device_name'])) {
            if ($device['os'] !== 'Unbekannt' && $device['browser'] !== 'Unbekannt') {
                $device['device_name'] = $device['os'] . ' - ' . $device['browser'];
            } else {
                $device['device_name'] = 'Unbekanntes Gerät';
            }
        }
    }
    unset($device);
} catch (PDOException $e) {
    error_log("Trusted Devices: Fehler beim Laden der Geräte: " . $e->getMessage());
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 lg:h-full app-mobile-no-root-overscroll">
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
                    <a href="<?php echo BASE_URL; ?>settings/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Einstellungen</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Vertraute Geräte</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Vertraute Geräte</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Verwalte Geräte, die du als vertraut markiert hast. Diese Geräte benötigen 30 Tage lang keinen 2FA-Code beim Login.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="col-span-full mx-4">
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <?php if (empty($trustedDevices)): ?>
              <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Keine vertrauten Geräte</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Du hast noch keine Geräte als vertraut markiert.</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                  Du kannst ein Gerät beim nächsten 2FA-Login als vertraut markieren.
                </p>
              </div>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                  <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                      <th scope="col" class="px-6 py-3">Gerätename</th>
                      <th scope="col" class="px-6 py-3">Browser/OS</th>
                      <th scope="col" class="px-6 py-3">IP-Adresse</th>
                      <th scope="col" class="px-6 py-3">Zuletzt verwendet</th>
                      <th scope="col" class="px-6 py-3">Hinzugefügt</th>
                      <th scope="col" class="px-6 py-3 text-right">Aktion</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($trustedDevices as $device): ?>
                      <tr class="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                          <?php echo htmlspecialchars($device['device_name']); ?>
                        </td>
                        <td class="px-6 py-4">
                          <span class="text-xs"><?php echo htmlspecialchars($device['browser']); ?> auf <?php echo htmlspecialchars($device['os']); ?></span>
                        </td>
                        <td class="px-6 py-4">
                          <span class="text-xs font-mono"><?php echo htmlspecialchars($device['ip_address'] ?? 'Unbekannt'); ?></span>
                        </td>
                        <td class="px-6 py-4">
                          <?php 
                          $lastUsed = new DateTime($device['last_used']);
                          $now = new DateTime();
                          $diff = $now->diff($lastUsed);
                          if ($diff->days == 0) {
                              if ($diff->h == 0) {
                                  echo 'vor ' . $diff->i . ' Minuten';
                              } else {
                                  echo 'vor ' . $diff->h . ' Stunde' . ($diff->h > 1 ? 'n' : '');
                              }
                          } elseif ($diff->days == 1) {
                              echo 'Gestern';
                          } elseif ($diff->days < 30) {
                              echo 'vor ' . $diff->days . ' Tagen';
                          } else {
                              echo 'Abgelaufen';
                          }
                          ?>
                        </td>
                        <td class="px-6 py-4">
                          <span class="text-xs"><?php echo date('d.m.Y', strtotime($device['created_at'])); ?></span>
                        </td>
                        <td class="px-6 py-4 text-right">
                          <button type="button" 
                                  class="remove-device-btn text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                  data-device-id="<?php echo $device['id']; ?>"
                                  title="Gerät entfernen">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              
              <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                <p class="text-sm text-blue-800 dark:text-blue-300">
                  <strong>Hinweis:</strong> Vertraute Geräte bleiben 30 Tage vertraut. Danach musst du sie erneut als vertraut markieren.
                </p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const removeDeviceBtns = document.querySelectorAll('.remove-device-btn');
    
    removeDeviceBtns.forEach(btn => {
        btn.addEventListener('click', async function() {
            const deviceId = this.getAttribute('data-device-id');
            
            if (!confirm('Möchtest du dieses Gerät wirklich aus der Liste der vertrauten Geräte entfernen?')) {
                return;
            }
            
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/trusted-devices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'remove',
                        device_id: deviceId
                    })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast('Gerät erfolgreich entfernt', 'success');
                    }
                    
                    // Zeile entfernen
                    const row = this.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            
                            // Prüfen ob noch Geräte vorhanden sind
                            const tbody = row.parentElement;
                            if (tbody && tbody.children.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                } else {
                    throw new Error(data.error || 'Fehler beim Entfernen des Geräts');
                }
            } catch (error) {
                console.error('Fehler:', error);
                if (typeof showToast === 'function') {
                    showToast(error.message || 'Fehler beim Entfernen des Geräts', 'error');
                } else {
                    alert(error.message || 'Fehler beim Entfernen des Geräts');
                }
                this.disabled = false;
                this.innerHTML = originalHTML;
            }
        });
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
