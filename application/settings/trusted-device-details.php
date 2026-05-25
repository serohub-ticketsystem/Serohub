<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$navMobileCompactTitle = 'Gerätedetail - 2FA';
$navMobileCompactBackUrl = BASE_URL . 'settings/twofa.php';
$navMobileCompactBackLabel = 'Zurück zu 2FA';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$deviceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($deviceId <= 0) {
    http_response_code(400);
    exit('Ungültige Geräte-ID');
}

$device = null;
try {
    $stmt = $pdo->prepare("
        SELECT id, device_name, user_agent, ip_address, last_used, created_at
        FROM trusted_devices
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$deviceId, $userId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    error_log("Trusted Device Details: Fehler beim Laden: " . $e->getMessage());
}

if (!$device) {
    http_response_code(404);
    exit('Gerät nicht gefunden');
}

$ua = (string) ($device['user_agent'] ?? '');
$browser = 'Unbekannt';
$os = 'Unbekannt';
$isMobile = (bool) preg_match('/(Android|iPhone|iPad|Mobile)/i', $ua);

if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera|MSIE)[\/\s](\d+\.\d+)/i', $ua, $m)) {
    $browser = $m[1];
}
if (preg_match('/(Windows NT|Windows|Mac OS X|Linux|iPhone|iPad|Android)/i', $ua, $m)) {
    $os = $m[1];
    if ($os === 'Windows NT') $os = 'Windows';
    if ($os === 'Mac OS X') $os = 'macOS';
}

$deviceName = trim((string) ($device['device_name'] ?? ''));
if ($deviceName === '') {
    $deviceName = ($os !== 'Unbekannt' && $browser !== 'Unbekannt') ? ($os . ' - ' . $browser) : 'Unbekanntes Gerät';
}
$lastUsedDt = new DateTime((string) ($device['last_used'] ?? 'now'));
$nowDt = new DateTime();
$trustedUntilDt = clone $lastUsedDt;
$trustedUntilDt->modify('+30 days');
$isTrustedActive = $trustedUntilDt > $nowDt;

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 lg:h-full app-mobile-no-root-overscroll">
  <main>
    <div class="px-4">
      <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
        <div class="col-span-full mx-4 max-lg:mx-0">
          <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
              <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700/70">
                  <?php if ($isMobile): ?>
                    <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19h4m-7 2h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                    </svg>
                  <?php else: ?>
                    <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/>
                    </svg>
                  <?php endif; ?>
                </div>
                <div class="min-w-0">
                  <p class="truncate text-base font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($deviceName); ?></p>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($browser); ?> auf <?php echo htmlspecialchars($os); ?></p>
                </div>
              </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
              <h4 class="text-base font-semibold text-gray-900 dark:text-white">Details</h4>
              <div class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
                <div class="flex items-center justify-between py-3 first:pt-0">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                  <?php if ($isTrustedActive): ?>
                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                      Aktiv
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                      Nicht aktiv
                    </span>
                  <?php endif; ?>
                </div>
                <div class="flex items-center justify-between py-3 first:pt-0">
                  <span class="text-sm text-gray-500 dark:text-gray-400">IP-Adresse</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($device['ip_address'] ?? 'Unbekannt')); ?></span>
                </div>
                <div class="flex items-center justify-between py-3">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Zuletzt verwendet</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">
                    <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string) ($device['last_used'] ?? 'now')))); ?>
                  </span>
                </div>
                <div class="flex items-center justify-between py-3 last:pb-0">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Hinzugefügt</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string) ($device['created_at'] ?? 'now')))); ?></span>
                </div>
              </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
              <h4 class="text-base font-semibold text-gray-900 dark:text-white">User-Agent</h4>
              <p class="mt-2 break-all text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($ua !== '' ? $ua : 'Nicht verfügbar'); ?></p>
            </div>

            <button type="button" id="remove-trusted-device-btn" class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-red-300 bg-white px-4 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-200 dark:border-red-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:focus:ring-red-900">
              <span id="remove-trusted-device-spinner" class="hidden" role="status" aria-hidden="true">
                <svg aria-hidden="true" class="h-4 w-4 animate-spin text-red-300 fill-red-700 dark:text-red-900 dark:fill-red-300" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858.59082 50 .59082C77.6142.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                  <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3691 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666.367541 46.6976.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7996 32.2913 88.1811 35.8758C89.083 38.2158 91.5422 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                </svg>
              </span>
              <span id="remove-trusted-device-label">Gerät entfernen</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const removeBtn = document.getElementById('remove-trusted-device-btn');
    const spinner = document.getElementById('remove-trusted-device-spinner');
    const label = document.getElementById('remove-trusted-device-label');
    const deviceId = <?php echo (int) $deviceId; ?>;

    if (!removeBtn || !deviceId) return;

    removeBtn.addEventListener('click', async function() {
        if (!confirm('Möchtest du dieses vertrauenswürdige Gerät wirklich entfernen?')) {
            return;
        }

        removeBtn.disabled = true;
        if (spinner) spinner.classList.remove('hidden');
        if (label) label.textContent = '';

        try {
            const response = await fetch('<?php echo BASE_URL; ?>settings/api/trusted-devices.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove', device_id: deviceId })
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Fehler beim Entfernen des Geräts');
            }
            if (typeof showToast === 'function') {
                showToast('Gerät wurde entfernt', 'success');
            }
            setTimeout(function() {
                window.location.href = '<?php echo BASE_URL; ?>settings/twofa.php';
            }, 500);
        } catch (error) {
            if (typeof showToast === 'function') {
                showToast(error.message || 'Fehler beim Entfernen des Geräts', 'error');
            } else {
                alert(error.message || 'Fehler beim Entfernen des Geräts');
            }
            removeBtn.disabled = false;
            if (spinner) spinner.classList.add('hidden');
            if (label) label.textContent = 'Gerät entfernen';
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
