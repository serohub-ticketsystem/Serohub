<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
$navMobileCompactTitle = 'Gerätedetail';
$navMobileCompactBackUrl = BASE_URL . 'settings/index.php?section=security';
$navMobileCompactBackLabel = 'Zurück zu Sicherheit';

$sessionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$sessionToken = trim((string) ($_GET['sid'] ?? ''));
$userId = (int) ($_SESSION['user_id'] ?? 0);
$sessionRow = null;
$errorText = '';

if ($sessionId <= 0 && $sessionToken === '') {
    $errorText = 'Ungültige Geräte-ID.';
} else {
    try {
        if ($sessionId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$sessionId, $userId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE session_id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$sessionToken, $userId]);
        }
        $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$sessionRow) {
            $errorText = 'Gerät nicht gefunden.';
        }
    } catch (Throwable $e) {
        $errorText = 'Fehler beim Laden der Gerätedetails.';
    }
}

if ($sessionRow) {
    $ua = (string) ($sessionRow['user_agent'] ?? '');
    $parsed = function_exists('parseUserAgentDetails') ? parseUserAgentDetails($ua) : [
        'browser_name' => 'Unbekannt',
        'browser_version' => null,
        'os_name' => 'Unbekannt',
        'device_type' => 'desktop',
    ];
    $browserName = trim((string) ($sessionRow['browser_name'] ?? '')) !== '' ? (string) $sessionRow['browser_name'] : (string) ($parsed['browser_name'] ?? 'Unbekannt');
    $browserVersion = trim((string) ($sessionRow['browser_version'] ?? '')) !== '' ? (string) $sessionRow['browser_version'] : (string) ($parsed['browser_version'] ?? '');
    $osName = trim((string) ($sessionRow['os_name'] ?? '')) !== '' ? (string) $sessionRow['os_name'] : (string) ($parsed['os_name'] ?? 'Unbekannt');
    $deviceType = trim((string) ($sessionRow['device_type'] ?? '')) !== '' ? (string) $sessionRow['device_type'] : (string) ($parsed['device_type'] ?? 'desktop');
    $isCurrent = ((string) ($sessionRow['session_id'] ?? '') === session_id());
    $loginMethodRaw = (string) ($sessionRow['login_method'] ?? '');
    $loginMethodMap = [
        'password' => 'Passwort',
        'password_2fa' => 'Passwort + 2FA',
        'password_trusted_device' => 'Passwort (vertrautes Gerät)',
        'passkey' => 'Passkey',
        'remember_me' => 'Remember-Me',
        'session' => 'Session',
    ];
    $loginMethod = $loginMethodMap[$loginMethodRaw] ?? ($loginMethodRaw !== '' ? $loginMethodRaw : 'Unbekannt');
    $deviceTypeLabelMap = [
        'mobile' => 'Handy',
        'tablet' => 'Tablet',
        'desktop' => 'PC',
        'bot' => 'Bot',
    ];
    $deviceTypeLabel = $deviceTypeLabelMap[$deviceType] ?? ucfirst($deviceType);
    $deviceDisplayName = trim($osName . ' - ' . $browserName . ($browserVersion !== '' ? (' ' . $browserVersion) : ''));
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
        <div class="col-span-full">
          <?php if ($errorText !== ''): ?>
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
              <p class="text-sm text-red-700 dark:text-red-300"><?php echo htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          <?php else: ?>
              <div class="space-y-3">
              <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
                <div class="flex items-center gap-3">
                  <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700/70">
                    <?php if ($deviceType === 'mobile' || $deviceType === 'tablet'): ?>
                      <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 4h8a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"></path>
                      </svg>
                    <?php else: ?>
                      <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"></path>
                      </svg>
                    <?php endif; ?>
                  </div>
                  <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($deviceDisplayName, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($deviceTypeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>
                </div>
              </div>

              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                <h4 class="text-base font-semibold text-gray-900 dark:text-white">Sicherheit und Login</h4>
                <div class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Login-Methode</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($loginMethod, ENT_QUOTES, 'UTF-8'); ?></span></div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Aktuelles Gerät</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo $isCurrent ? 'Ja' : 'Nein'; ?></span></div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">HTTPS</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo !empty($sessionRow['is_https']) ? 'Ja' : 'Nein'; ?></span></div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Remember-Me</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo !empty($sessionRow['remember_me_used']) ? 'Ja' : 'Nein'; ?></span></div>
                </div>
              </div>

              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                <h4 class="text-base font-semibold text-gray-900 dark:text-white">Netzwerk und Zeit</h4>
                <div class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">IP-Adresse</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($sessionRow['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                  <div class="py-2.5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Forwarded-For</p>
                    <p class="mt-1 break-all text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($sessionRow['forwarded_for'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Sprache</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($sessionRow['accept_language'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Letzte Aktivität</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($sessionRow['last_activity'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Erstellt am</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($sessionRow['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                </div>
              </div>

              <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                <h4 class="text-base font-semibold text-gray-900 dark:text-white">Technische Details</h4>
                <div class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                  <div class="py-2.5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">User-Agent</p>
                    <p class="mt-1 break-all text-xs text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars((string) ($sessionRow['user_agent'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>
                  <div class="py-2.5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sec-CH-UA</p>
                    <p class="mt-1 break-all text-xs text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars((string) ($sessionRow['sec_ch_ua'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Sec-CH-UA-Platform</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($sessionRow['sec_ch_ua_platform'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Sec-CH-UA-Mobile</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($sessionRow['sec_ch_ua_mobile'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                  <div class="flex items-center justify-between py-2.5"><span class="text-sm text-gray-500 dark:text-gray-400">Sec-CH-UA-Model</span><span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($sessionRow['sec_ch_ua_model'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                </div>
              </div>

              <?php if (empty($isCurrent)): ?>
                <button type="button" id="logout-device-btn" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-red-300 bg-white px-4 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-200 dark:border-red-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:focus:ring-red-900">
                  <span id="logout-device-btn-spinner" class="hidden" role="status" aria-hidden="true">
                    <svg class="h-4 w-4 animate-spin text-current" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"></path>
                    </svg>
                  </span>
                  <span id="logout-device-btn-label">Dieses Gerät abmelden</span>
                </button>
              <?php else: ?>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 shadow-sm dark:border-blue-800 dark:bg-blue-900/20">
                  <p class="text-sm text-blue-700 dark:text-blue-300">Das ist dein aktuelles Gerät und kann hier nicht abgemeldet werden.</p>
                </div>
              <?php endif; ?>
              </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<?php include_once dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
<?php if ($sessionRow && empty($isCurrent)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('logout-device-btn');
  const btnSpinner = document.getElementById('logout-device-btn-spinner');
  const btnLabel = document.getElementById('logout-device-btn-label');
  if (!btn) return;

  btn.addEventListener('click', async function () {
    if (!confirm('Möchtest du dieses Gerät wirklich abmelden?')) return;

    btn.disabled = true;
    if (btnSpinner) btnSpinner.classList.remove('hidden');
    if (btnLabel) btnLabel.classList.add('hidden');

    try {
      const resp = await fetch('<?php echo BASE_URL; ?>settings/api/logout-device.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: <?php echo (int) ($sessionRow['id'] ?? 0); ?>,
          sid: '<?php echo addslashes((string) ($sessionRow['session_id'] ?? '')); ?>'
        })
      });
      const data = await resp.json();
      if (resp.ok && data.success) {
        if (typeof showToast === 'function') showToast(data.message || 'Gerät wurde abgemeldet', 'success');
        setTimeout(function () {
          window.location.href = '<?php echo BASE_URL; ?>settings/index.php?section=security';
        }, 300);
      } else {
        if (typeof showToast === 'function') showToast(data.message || 'Aktion fehlgeschlagen', 'error');
        btn.disabled = false;
        if (btnSpinner) btnSpinner.classList.add('hidden');
        if (btnLabel) btnLabel.classList.remove('hidden');
      }
    } catch (e) {
      if (typeof showToast === 'function') showToast('Aktion fehlgeschlagen', 'error');
      btn.disabled = false;
      if (btnSpinner) btnSpinner.classList.add('hidden');
      if (btnLabel) btnLabel.classList.remove('hidden');
    }
  });
});
</script>
<?php endif; ?>
