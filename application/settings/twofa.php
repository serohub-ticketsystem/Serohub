<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/totp.php';
requireLogin();

// BASE_URL definieren falls nicht bereits definiert
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
$navMobileCompactTitle = '2FA';
$navMobileCompactBackUrl = BASE_URL . 'settings/index.php?section=security';
$navMobileCompactBackLabel = 'Zurück zu Sicherheit';

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
$user = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.vorname, u.nachname
        FROM users u
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("2FA Settings: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
    header('Location: ' . BASE_URL . 'settings/');
    exit;
}

// 2FA-Status abrufen
$twoFaEnabled = false;
$twoFaSecret = null;
$trustedDevices = [];
$supportEmail = '';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_enabled'");
    $stmt->execute([$userId]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($setting && $setting['setting_value'] === '1') {
        $twoFaEnabled = true;
        
        // Secret nur laden wenn aktiviert
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_secret'");
        $stmt->execute([$userId]);
        $secretSetting = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($secretSetting) {
            $twoFaSecret = $secretSetting['setting_value'];
        }
    }
} catch (PDOException $e) {
    error_log("2FA Settings: Fehler beim Laden der 2FA-Einstellungen: " . $e->getMessage());
}

// Wenn 2FA aktiviert ist, aber kein Secret vorhanden, zurücksetzen
if ($twoFaEnabled && !$twoFaSecret) {
    try {
        $stmt = $pdo->prepare("UPDATE user_settings SET setting_value = '0' WHERE user_id = ? AND setting_key = '2fa_enabled'");
        $stmt->execute([$userId]);
        $twoFaEnabled = false;
    } catch (PDOException $e) {
        error_log("2FA Settings: Fehler beim Zurücksetzen: " . $e->getMessage());
    }
}

// Vertraute Geräte laden, wenn 2FA aktiviert ist
if ($twoFaEnabled) {
    try {
        $devicesStmt = $pdo->prepare("
            SELECT id, device_name, user_agent, ip_address, last_used, created_at
            FROM trusted_devices
            WHERE user_id = ?
            ORDER BY last_used DESC
        ");
        $devicesStmt->execute([$userId]);
        $trustedDevices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($trustedDevices as &$device) {
            $userAgent = $device['user_agent'] ?? '';
            $device['browser'] = 'Unbekannt';
            $device['os'] = 'Unbekannt';
            $device['is_mobile'] = false;

            if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera|MSIE)[\/\s](\d+\.\d+)/i', $userAgent, $matches)) {
                $device['browser'] = $matches[1];
            }

            if (preg_match('/(Windows NT|Windows|Mac OS X|Linux|iPhone|iPad|Android)/i', $userAgent, $matches)) {
                $os = $matches[1];
                if ($os === 'Windows NT') $os = 'Windows';
                if ($os === 'Mac OS X') $os = 'macOS';
                $device['os'] = $os;
            }

            $device['is_mobile'] = (bool) preg_match('/(Android|iPhone|iPad|Mobile)/i', $userAgent);

            if (empty($device['device_name'])) {
                if ($device['os'] !== 'Unbekannt' && $device['browser'] !== 'Unbekannt') {
                    $device['device_name'] = $device['os'] . ' - ' . $device['browser'];
                } else {
                    $device['device_name'] = 'Unbekanntes Gerät';
                }
            }
        }
        unset($device);

        // Deduplizieren: identische Geräte nur einmal anzeigen (jeweils mit neuester Aktivität)
        $dedupedTrustedDevices = [];
        foreach ($trustedDevices as $device) {
            $deviceFingerprint = strtolower(trim((string) ($device['device_name'] ?? ''))) . '|' .
                strtolower(trim((string) ($device['browser'] ?? ''))) . '|' .
                strtolower(trim((string) ($device['os'] ?? ''))) . '|' .
                strtolower(trim((string) ($device['user_agent'] ?? '')));

            $existing = $dedupedTrustedDevices[$deviceFingerprint] ?? null;
            if ($existing === null) {
                $dedupedTrustedDevices[$deviceFingerprint] = $device;
                continue;
            }

            $existingTs = strtotime((string) ($existing['last_used'] ?? '')) ?: 0;
            $newTs = strtotime((string) ($device['last_used'] ?? '')) ?: 0;
            if ($newTs > $existingTs) {
                $dedupedTrustedDevices[$deviceFingerprint] = $device;
            }
        }
        $trustedDevices = array_values($dedupedTrustedDevices);
    } catch (PDOException $e) {
        error_log("2FA Settings: Fehler beim Laden vertrauter Geräte: " . $e->getMessage());
    }
}

try {
    $supportStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'support_email' LIMIT 1");
    $supportStmt->execute();
    $supportRow = $supportStmt->fetch(PDO::FETCH_ASSOC);
    $supportCandidate = trim((string) ($supportRow['setting_value'] ?? ''));
    if ($supportCandidate !== '' && filter_var($supportCandidate, FILTER_VALIDATE_EMAIL)) {
        $supportEmail = $supportCandidate;
    }
} catch (Throwable $e) {
    // Fallback ohne Support-E-Mail
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
        <!-- 2FA Content -->
        <div class="col-span-full mx-4 max-lg:mt-0 max-lg:mx-0">
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 max-lg:rounded-none max-lg:border-0 max-lg:bg-transparent max-lg:p-0 max-lg:shadow-none">
            <h3 class="mb-4 border-b border-gray-200 pb-4 text-xl font-semibold text-gray-900 dark:border-gray-700 dark:text-white md:pb-6 max-lg:hidden">2FA-Einstellungen</h3>
            
            <?php if (!$twoFaEnabled): ?>
              <!-- 2FA nicht aktiviert -->
              <div id="2fa-setup-section" class="space-y-6">
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                  <p class="text-sm text-blue-800 dark:text-blue-300">
                    Wenn du eine Schritt-für-Schritt-Anleitung brauchst, tippe auf
                    <button type="button" id="show-2fa-guide-btn" class="inline text-sm font-medium text-blue-800 hover:text-blue-900 focus:outline-none dark:text-blue-300 dark:hover:text-blue-200">
                      Anleitung anzeigen
                    </button>
                    und folge dann den drei Schritten.
                  </p>
                </div>
                <!-- Optionale Anleitung -->
                <div id="twofa-setup-guide" class="hidden space-y-6">
                  <div>
                    <h4 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Schritt 1: Authenticator-App installieren</h4>
                    <p class="mb-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                      Installiere eine Authenticator-App auf deinem Smartphone:
                    </p>
                    <ul class="mb-4 list-inside list-disc space-y-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                      <li><strong>Google Authenticator</strong> (iOS, Android)</li>
                      <li><strong>Microsoft Authenticator</strong> (iOS, Android)</li>
                      <li><strong>Authy</strong> (iOS, Android)</li>
                    </ul>
                  </div>

                  <div>
                    <h4 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Schritt 2: QR-Code scannen</h4>
                    <p class="mb-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                      Scanne den QR-Code mit deiner Authenticator-App.
                    </p>
                  </div>

                  <div>
                    <h4 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Schritt 3: 6-stelligen Code bestätigen</h4>
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                      Nach dem Scannen zeigt deine App einen laufend wechselnden 6-stelligen Code an. Trage diesen Code in die sechs Felder ein und tippe auf
                      <span class="font-medium text-gray-900 dark:text-white">„2FA aktivieren“</span>, um die Einrichtung abzuschließen.
                    </p>
                  </div>
                </div>

                <!-- Einrichtung (unabhängig von Anleitung) -->
                <div id="qr-code-section" class="space-y-4">
                  <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
                    <div class="mb-4 flex justify-center">
                      <img id="qr-code-image" src="" alt="QR Code" class="rounded-lg border border-gray-300 bg-white p-1 dark:border-gray-600">
                    </div>

                    <div class="mb-4 lg:hidden">
                      <a id="open-authenticator-link" href="#" class="inline-flex h-11 w-full items-center justify-center rounded-xl border !border-blue-200 !bg-blue-50 px-4 text-sm font-medium !text-blue-700 hover:!bg-blue-100 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/40">
                        In Authenticator-App öffnen
                      </a>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-700/50">
                      <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Manueller Code</p>
                      <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-800">
                        <code id="secret-code" class="truncate text-sm font-mono text-gray-900 dark:text-white"></code>
                        <button type="button" id="copy-secret-btn" class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-gray-700">
                          Kopieren
                        </button>
                      </div>
                    </div>
                  </div>
                  
                  <div>
                    <label for="verification-code" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                      Bestätigungscode aus Authenticator-App
                    </label>
                    <input type="hidden" id="verification-code" name="verification-code" value="">
                    <div class="grid grid-cols-6 gap-2">
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="enable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="enable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="enable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="enable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="enable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="enable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Gib den 6-stelligen Code aus deiner Authenticator-App ein, um die Aktivierung zu bestätigen.</p>
                  </div>
                  
                  <div class="flex justify-end">
                    <button type="button" id="confirm-2fa-btn" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-primary-700 px-5 text-center text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                      2FA aktivieren
                    </button>
                  </div>
                  <p class="mt-2 mb-8 text-center text-xs text-gray-500 dark:text-gray-400">
                    Wenn du Hilfe benötigst, kontaktiere den
                    <?php if (!empty($supportEmail)): ?>
                      <a href="mailto:<?php echo htmlspecialchars($supportEmail); ?>" class="text-primary-600 hover:underline dark:text-primary-500">Support</a>
                    <?php else: ?>
                      <a href="<?php echo htmlspecialchars(BASE_URL); ?>tickets/create.php" class="text-primary-600 hover:underline dark:text-primary-500">Support</a>
                    <?php endif; ?>.
                  </p>
                </div>
              </div>
            <?php else: ?>
              <!-- 2FA aktiviert -->
              <div id="2fa-enabled-section" class="space-y-4">
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                  <div class="flex items-center">
                    <svg class="me-2 h-5 w-5 text-green-600 dark:text-green-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">
                      2FA ist aktiviert. Dein Konto ist besser geschützt. Du kannst sie
                      <a href="<?php echo BASE_URL; ?>settings/twofa-disable.php" class="inline text-sm font-medium text-green-800 hover:text-green-900 focus:outline-none dark:text-green-300 dark:hover:text-green-200 lg:hidden">
                        hier deaktivieren
                      </a>
                      <button type="button" id="show-disable-2fa-btn" class="hidden text-sm font-medium text-green-800 hover:text-green-900 focus:outline-none dark:text-green-300 dark:hover:text-green-200 lg:inline">
                        hier deaktivieren
                      </button>
                      .
                    </p>
                  </div>
                </div>
                
                <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
                  <div class="mb-4">
                    <div>
                      <h4 class="text-base font-semibold text-gray-900 dark:text-white">Vertraute Geräte</h4>
                      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Diese Geräte benötigen 30 Tage lang keinen 2FA-Code.
                      </p>
                    </div>
                  </div>

                  <?php if (empty($trustedDevices)): ?>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-700/50">
                      <p class="text-sm text-gray-500 dark:text-gray-400">Du hast noch keine Geräte als vertraut markiert.</p>
                    </div>
                  <?php else: ?>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                      <?php foreach ($trustedDevices as $device): ?>
                        <?php
                          $lastUsed = new DateTime((string) ($device['last_used'] ?? 'now'));
                          $now = new DateTime();
                          $diff = $now->diff($lastUsed);
                          $trustedUntil = clone $lastUsed;
                          $trustedUntil->modify('+30 days');
                          $isTrustedActive = $trustedUntil > $now;
                        ?>
                        <a href="<?php echo BASE_URL; ?>settings/trusted-device-details.php?id=<?php echo (int) ($device['id'] ?? 0); ?>" class="flex items-center justify-between py-3 transition-colors hover:bg-gray-50/70 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:hover:bg-gray-700/40 dark:focus:ring-primary-400">
                          <div class="flex min-w-0 items-center flex-1">
                            <div class="flex-shrink-0 mr-3">
                              <div class="h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-700/70 flex items-center justify-center">
                              <?php if (!empty($device['is_mobile'])): ?>
                                <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19h4m-7 2h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                                </svg>
                              <?php else: ?>
                                <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/>
                                </svg>
                              <?php endif; ?>
                              </div>
                            </div>
                            <div class="flex-1 min-w-0">
                              <p class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars((string) $device['device_name']); ?>
                              </p>
                              <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <?php if (!empty($device['ip_address'])): ?>
                                  <span>IP: <?php echo htmlspecialchars((string) $device['ip_address']); ?></span>
                                <?php endif; ?>
                                <span class="<?php echo $isTrustedActive ? '' : 'text-red-600 dark:text-red-400'; ?>">
                                  <?php
                                    if ($diff->days === 0) {
                                        if ($diff->h === 0) {
                                            echo 'vor ' . max(1, (int) $diff->i) . ' Min.';
                                        } else {
                                            echo 'vor ' . (int) $diff->h . ' Std.';
                                        }
                                    } elseif ($diff->days === 1) {
                                        echo 'Gestern';
                                    } else {
                                        echo 'vor ' . (int) $diff->days . ' Tagen';
                                    }
                                  ?>
                                </span>
                              </div>
                            </div>
                          </div>
                          <svg class="ms-3 h-5 w-5 shrink-0 text-gray-400 lg:hidden" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                          </svg>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div id="disable-2fa-section" class="hidden max-lg:hidden">
                  <h4 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">2FA deaktivieren</h4>
                  <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Wenn du 2FA deaktivieren möchtest, gib bitte einen Code aus deiner Authenticator-App ein.
                  </p>
                  
                  <div class="mb-4">
                    <label for="disable-verification-code" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                      Aktueller Code aus Authenticator-App
                    </label>
                    <input type="text" 
                           id="disable-verification-code" 
                           name="disable-verification-code" 
                           placeholder="000000" 
                           maxlength="6"
                           pattern="[0-9]{6}"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500">
                  </div>
                  
                  <button type="button" id="disable-2fa-btn" class="inline-flex items-center rounded-lg bg-red-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800">
                    <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 0 0-8 0v4h8Z"/>
                    </svg>
                    2FA deaktivieren
                  </button>
                </div>
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
    const qrCodeSection = document.getElementById('qr-code-section');
    const qrCodeImage = document.getElementById('qr-code-image');
    const secretCode = document.getElementById('secret-code');
    const copySecretBtn = document.getElementById('copy-secret-btn');
    const openAuthenticatorLink = document.getElementById('open-authenticator-link');
    const cancelSetupBtn = document.getElementById('cancel-setup-btn');
    const confirm2FaBtn = document.getElementById('confirm-2fa-btn');
    const verificationCodeInput = document.getElementById('verification-code');
    const enableCodeDigits = Array.from(document.querySelectorAll('.enable-code-digit'));
    const disable2FaBtn = document.getElementById('disable-2fa-btn');
    const disableVerificationCodeInput = document.getElementById('disable-verification-code');
    const showDisable2FaBtn = document.getElementById('show-disable-2fa-btn');
    const disable2FaSection = document.getElementById('disable-2fa-section');
    const show2FaGuideBtn = document.getElementById('show-2fa-guide-btn');
    const twofaSetupGuide = document.getElementById('twofa-setup-guide');
    
    let currentSecret = null;
    let currentOtpAuthUrl = null;
    const getEnableCode = function() {
        if (enableCodeDigits.length === 6) {
            return enableCodeDigits.map(function(input) { return input.value.trim(); }).join('');
        }
        return verificationCodeInput ? verificationCodeInput.value.trim() : '';
    };

    if (enableCodeDigits.length) {
        enableCodeDigits.forEach(function(input, index) {
            input.addEventListener('input', function(e) {
                const cleaned = (e.target.value || '').replace(/[^0-9]/g, '');
                e.target.value = cleaned ? cleaned.charAt(cleaned.length - 1) : '';
                if (e.target.value && index < enableCodeDigits.length - 1) {
                    enableCodeDigits[index + 1].focus();
                }
                if (verificationCodeInput) verificationCodeInput.value = getEnableCode();
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    enableCodeDigits[index - 1].focus();
                }
            });

            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pasted = (e.clipboardData.getData('text') || '').replace(/[^0-9]/g, '').slice(0, 6);
                if (!pasted) return;
                for (let i = 0; i < enableCodeDigits.length; i++) {
                    enableCodeDigits[i].value = pasted[i] || '';
                }
                if (verificationCodeInput) verificationCodeInput.value = getEnableCode();
                enableCodeDigits[Math.min(pasted.length, 5)].focus();
            });
        });
    }

    if (showDisable2FaBtn && disable2FaSection) {
        showDisable2FaBtn.addEventListener('click', function() {
            disable2FaSection.classList.remove('hidden');
            showDisable2FaBtn.classList.add('hidden');
            if (disableVerificationCodeInput) {
                disableVerificationCodeInput.focus();
            }
        });
    }

    if (show2FaGuideBtn && twofaSetupGuide) {
        show2FaGuideBtn.addEventListener('click', function() {
            const isHidden = twofaSetupGuide.classList.contains('hidden');
            if (isHidden) {
                twofaSetupGuide.classList.remove('hidden');
                show2FaGuideBtn.textContent = 'Anleitung ausblenden';
            } else {
                twofaSetupGuide.classList.add('hidden');
                show2FaGuideBtn.textContent = 'Anleitung anzeigen';
            }
        });
    }

    const generateTwoFaSetup = async function() {
        try {
            if (secretCode) secretCode.textContent = 'Wird geladen...';

            const response = await fetch('<?php echo BASE_URL; ?>settings/api/twofa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'generate_secret' })
            });

            const text = await response.text();
            if (!text || text.trim() === '') {
                throw new Error('Leere Antwort vom Server');
            }

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Antwort vom Server:', text);
                throw new Error('Ungültige Antwort vom Server: ' + text.substring(0, 100));
            }

            if (response.ok && data.success) {
                currentSecret = data.secret;
                currentOtpAuthUrl = data.otpauth_url || null;
                if (qrCodeImage) qrCodeImage.src = data.qr_code_url;
                if (secretCode) secretCode.textContent = data.secret;
                if (openAuthenticatorLink && currentOtpAuthUrl) {
                    openAuthenticatorLink.href = currentOtpAuthUrl;
                    openAuthenticatorLink.classList.remove('hidden');
                }
            } else {
                throw new Error(data.error || 'Fehler beim Generieren des Secrets');
            }
        } catch (error) {
            console.error('Fehler:', error);
            if (typeof showToast === 'function') {
                showToast('Fehler beim Laden der 2FA-Einrichtung: ' + error.message, 'error');
            } else {
                alert('Fehler beim Laden der 2FA-Einrichtung: ' + error.message);
            }
            if (secretCode) secretCode.textContent = 'Fehler';
        }
    };

    generateTwoFaSetup();
    
    // Secret kopieren
    if (copySecretBtn) {
        copySecretBtn.addEventListener('click', function() {
            if (secretCode && secretCode.textContent) {
                navigator.clipboard.writeText(secretCode.textContent).then(function() {
                    copySecretBtn.textContent = 'Kopiert!';
                    setTimeout(function() {
                        copySecretBtn.textContent = 'Kopieren';
                    }, 2000);
                });
            }
        });
    }
    
    // Setup abbrechen
    if (cancelSetupBtn) {
        cancelSetupBtn.addEventListener('click', function() {
            qrCodeSection.classList.add('hidden');
            currentSecret = null;
            currentOtpAuthUrl = null;
            if (verificationCodeInput) verificationCodeInput.value = '';
            enableCodeDigits.forEach(function(input) { input.value = ''; });
            if (openAuthenticatorLink) {
                openAuthenticatorLink.href = '#';
            }
        });
    }
    
    // 2FA bestätigen und aktivieren
    if (confirm2FaBtn) {
        confirm2FaBtn.addEventListener('click', async function() {
            const code = getEnableCode();
            
            if (!code || code.length !== 6 || !/^\d{6}$/.test(code)) {
                if (typeof showToast === 'function') {
                    showToast('Bitte gib einen gültigen 6-stelligen Code ein', 'error');
                } else {
                    alert('Bitte gib einen gültigen 6-stelligen Code ein');
                }
                return;
            }
            
            if (!currentSecret) {
                if (typeof showToast === 'function') {
                    showToast('Bitte generiere zuerst einen QR-Code', 'error');
                } else {
                    alert('Bitte generiere zuerst einen QR-Code');
                }
                return;
            }
            
            confirm2FaBtn.disabled = true;
            confirm2FaBtn.textContent = 'Aktiviere...';
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/twofa.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'enable',
                        secret: currentSecret,
                        code: code
                    })
                });
                
                // Prüfen ob Response leer ist
                const text = await response.text();
                if (!text || text.trim() === '') {
                    throw new Error('Leere Antwort vom Server');
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Antwort vom Server:', text);
                    throw new Error('Ungültige Antwort vom Server: ' + text.substring(0, 100));
                }
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast('2FA wurde erfolgreich aktiviert!', 'success');
                    } else {
                        alert('2FA wurde erfolgreich aktiviert!');
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.error || 'Fehler beim Aktivieren von 2FA');
                }
            } catch (error) {
                console.error('Fehler:', error);
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Aktivieren von 2FA: ' + error.message, 'error');
                } else {
                    alert('Fehler beim Aktivieren von 2FA: ' + error.message);
                }
            } finally {
                confirm2FaBtn.disabled = false;
                confirm2FaBtn.textContent = '2FA aktivieren';
            }
        });
    }
    
    // 2FA deaktivieren
    if (disable2FaBtn) {
        disable2FaBtn.addEventListener('click', async function() {
            const code = disableVerificationCodeInput.value.trim();
            
            if (!code || code.length !== 6 || !/^\d{6}$/.test(code)) {
                if (typeof showToast === 'function') {
                    showToast('Bitte gib einen gültigen 6-stelligen Code ein', 'error');
                } else {
                    alert('Bitte gib einen gültigen 6-stelligen Code ein');
                }
                return;
            }
            
            if (!confirm('Möchtest du 2FA wirklich deaktivieren? Dein Konto wird dadurch weniger sicher.')) {
                return;
            }
            
            disable2FaBtn.disabled = true;
            disable2FaBtn.textContent = 'Deaktiviere...';
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>settings/api/twofa.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'disable',
                        code: code
                    })
                });
                
                // Prüfen ob Response leer ist
                const text = await response.text();
                if (!text || text.trim() === '') {
                    throw new Error('Leere Antwort vom Server');
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Antwort vom Server:', text);
                    throw new Error('Ungültige Antwort vom Server: ' + text.substring(0, 100));
                }
                
                if (response.ok && data.success) {
                    if (typeof showToast === 'function') {
                        showToast('2FA wurde erfolgreich deaktiviert', 'success');
                    } else {
                        alert('2FA wurde erfolgreich deaktiviert');
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.error || 'Fehler beim Deaktivieren von 2FA');
                }
            } catch (error) {
                console.error('Fehler:', error);
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Deaktivieren von 2FA: ' + error.message, 'error');
                } else {
                    alert('Fehler beim Deaktivieren von 2FA: ' + error.message);
                }
            } finally {
                disable2FaBtn.disabled = false;
                disable2FaBtn.textContent = '2FA deaktivieren';
            }
        });
    }
    
    // Nur Zahlen in Code-Eingabefeldern erlauben
    [verificationCodeInput, disableVerificationCodeInput].forEach(function(input) {
        if (input) {
            input.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
