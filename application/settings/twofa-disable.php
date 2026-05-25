<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$navMobileCompactTitle = '2FA deaktivieren';
$navMobileCompactBackUrl = BASE_URL . 'settings/twofa.php';
$navMobileCompactBackLabel = 'Zurück zu 2FA';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$twoFaEnabled = false;

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_enabled' LIMIT 1");
    $stmt->execute([$userId]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $twoFaEnabled = $setting && ($setting['setting_value'] ?? '') === '1';
} catch (PDOException $e) {
    error_log("2FA Disable Page: Fehler beim Laden des 2FA-Status: " . $e->getMessage());
}

if (!$twoFaEnabled) {
    header('Location: ' . BASE_URL . 'settings/twofa.php');
    exit;
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
        <div class="col-span-full mx-4 max-lg:mx-0">
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 max-lg:rounded-none max-lg:border-0 max-lg:bg-transparent max-lg:p-0 max-lg:shadow-none">
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-900/20">
              <p class="text-sm text-red-800 dark:text-red-300">
                <strong>Achtung:</strong> Wenn du 2FA deaktivierst, ist dein Konto deutlich schlechter geschützt. Ein gestohlenes Passwort reicht dann aus, um sich anzumelden.
              </p>
              <p class="mt-2 text-sm text-red-800 dark:text-red-300">
                Wir empfehlen dringend, 2FA aktiv zu lassen. Diese Deaktivierung funktioniert nur mit einem gültigen Code aus deiner Authenticator-App.
              </p>
              <p class="mt-2 text-sm text-red-800 dark:text-red-300">
                Falls du keinen Zugriff mehr auf deine App hast, wende dich bitte an den Administrator.
              </p>
              <button type="button" id="accept-disable-risk-btn" class="mt-3 inline text-sm font-medium text-red-800 hover:text-red-900 focus:outline-none dark:text-red-300 dark:hover:text-red-200">
                Ich akzeptiere das Risiko und möchte fortfahren.
              </button>
            </div>

            <div id="disable-2fa-form-wrap" class="mt-4 hidden">
              <label for="disable-verification-code" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                Aktueller Code aus Authenticator-App
              </label>
              <div id="disable-code-inputs" class="grid grid-cols-6 gap-2">
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="disable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="disable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="disable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="disable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="disable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="disable-code-digit block w-full rounded-lg border border-gray-300 bg-white p-3 text-center text-lg font-semibold text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500" autocomplete="one-time-code">
              </div>
              <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Zur Sicherheit muss der Code von deinem aktuell verknüpften Gerät kommen.</p>
            </div>

            <button type="button" id="disable-2fa-btn" class="mt-5 hidden inline-flex h-11 w-full items-center justify-center rounded-xl border border-red-300 bg-white px-4 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-200 dark:border-red-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:focus:ring-red-900">
              <span id="disable-2fa-btn-spinner" class="hidden" role="status" aria-hidden="true">
                <svg aria-hidden="true" class="h-4 w-4 animate-spin text-red-300 fill-red-700 dark:text-red-900 dark:fill-red-300" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858.59082 50 .59082C77.6142.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                  <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3691 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666.367541 46.6976.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7996 32.2913 88.1811 35.8758C89.083 38.2158 91.5422 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                </svg>
              </span>
              <span id="disable-2fa-btn-label">2FA jetzt deaktivieren</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const disable2FaBtn = document.getElementById('disable-2fa-btn');
    const disableDigits = Array.from(document.querySelectorAll('.disable-code-digit'));
    const disable2FaBtnSpinner = document.getElementById('disable-2fa-btn-spinner');
    const disable2FaBtnLabel = document.getElementById('disable-2fa-btn-label');
    const acceptDisableRiskBtn = document.getElementById('accept-disable-risk-btn');
    const disable2FaFormWrap = document.getElementById('disable-2fa-form-wrap');

    if (!disable2FaBtn || disableDigits.length !== 6) {
        return;
    }

    const getDisableCode = function() {
        return disableDigits.map(function(input) { return input.value.trim(); }).join('');
    };

    if (acceptDisableRiskBtn && disable2FaFormWrap) {
        acceptDisableRiskBtn.addEventListener('click', function() {
            disable2FaFormWrap.classList.remove('hidden');
            disable2FaBtn.classList.remove('hidden');
            acceptDisableRiskBtn.classList.add('hidden');
            disableDigits[0].focus();
        });
    }

    disableDigits.forEach(function(input, index) {
        input.addEventListener('input', function(e) {
            const cleaned = (e.target.value || '').replace(/[^0-9]/g, '');
            e.target.value = cleaned ? cleaned.charAt(cleaned.length - 1) : '';
            if (e.target.value && index < disableDigits.length - 1) {
                disableDigits[index + 1].focus();
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !input.value && index > 0) {
                disableDigits[index - 1].focus();
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData.getData('text') || '').replace(/[^0-9]/g, '').slice(0, 6);
            if (!pasted) return;
            for (let i = 0; i < disableDigits.length; i++) {
                disableDigits[i].value = pasted[i] || '';
            }
            const nextIndex = Math.min(pasted.length, 5);
            disableDigits[nextIndex].focus();
        });
    });

    disable2FaBtn.addEventListener('click', async function() {
        const code = getDisableCode();

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
        if (disable2FaBtnSpinner) disable2FaBtnSpinner.classList.remove('hidden');
        if (disable2FaBtnLabel) disable2FaBtnLabel.textContent = '';

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

            const text = await response.text();
            if (!text || text.trim() === '') {
                throw new Error('Leere Antwort vom Server');
            }

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Ungültige Antwort vom Server');
            }

            if (response.ok && data.success) {
                if (typeof showToast === 'function') {
                    showToast('2FA wurde erfolgreich deaktiviert', 'success');
                }
                setTimeout(function() {
                    window.location.href = '<?php echo BASE_URL; ?>settings/index.php?section=security';
                }, 600);
            } else {
                throw new Error(data.error || 'Fehler beim Deaktivieren von 2FA');
            }
        } catch (error) {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Deaktivieren von 2FA: ' + error.message, 'error');
            } else {
                alert('Fehler beim Deaktivieren von 2FA: ' + error.message);
            }
            disable2FaBtn.disabled = false;
            if (disable2FaBtnSpinner) disable2FaBtnSpinner.classList.add('hidden');
            if (disable2FaBtnLabel) disable2FaBtnLabel.textContent = '2FA jetzt deaktivieren';
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
