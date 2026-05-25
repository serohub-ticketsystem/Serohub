<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/password_rules.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$userId = $_SESSION['user_id'];
$user = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.vorname, u.nachname, u.rolle, u.status, u.company_id, u.letztes_pw_change
        FROM users u
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Onboarding Step 1: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
    header('Location: ' . onboarding_step_url(1));
    exit;
}

$onboardingStatus = onboarding_status_from_user($user);
$passwordUserHints = onboarding_password_user_hints($user);

include dirname(__DIR__) . '/assets/frontend/head.php';
onboarding_layout_styles();
?>

<div id="main-content" class="onboarding-root relative w-full overflow-hidden bg-gray-50 dark:bg-primary-50">
<?php onboarding_layout_body_script(); ?>
<?php
onboarding_shell_open([
    'illustration' => 'step1',
    'current_step' => 1,
    'status' => $onboardingStatus,
]);
?>

          <div class="onboarding-step-header">
            <h1 class="text-gray-900 dark:text-white">Passwort ändern</h1>
            <p class="text-gray-600 dark:text-gray-400">Erstelle ein sicheres Passwort für dein Konto.</p>
          </div>

          <form id="passwordForm" class="onboarding-form-compact">
            <div id="passwordRequirementsBanner" class="onboarding-password-banner" role="status" aria-live="polite">
              <p class="onboarding-password-banner__title">Dein Passwort muss:</p>
              <ul class="onboarding-password-banner__list">
                <li class="onboarding-password-banner__item" data-req="length">
                  <span class="onboarding-password-banner__marker" aria-hidden="true"></span>
                  <span>Mindestens 8 Zeichen lang sein</span>
                </li>
                <li class="onboarding-password-banner__item" data-req="digit">
                  <span class="onboarding-password-banner__marker" aria-hidden="true"></span>
                  <span>Mindestens eine Zahl enthalten</span>
                </li>
                <li class="onboarding-password-banner__item" data-req="guessable">
                  <span class="onboarding-password-banner__marker" aria-hidden="true"></span>
                  <span>Kein leicht erratbares Passwort</span>
                </li>
                <li class="onboarding-password-banner__item" data-req="different">
                  <span class="onboarding-password-banner__marker" aria-hidden="true"></span>
                  <span>Sich vom bisherigen Passwort unterscheiden</span>
                </li>
                <li class="onboarding-password-banner__item" data-req="match">
                  <span class="onboarding-password-banner__marker" aria-hidden="true"></span>
                  <span>In beiden Feldern übereinstimmen</span>
                </li>
              </ul>
            </div>

            <div class="onboarding-form-fields">
              <?php onboarding_floating_input('new_password', 'Neues Passwort', [
                  'type' => 'password',
                  'required' => true,
                  'minlength' => 8,
                  'autocomplete' => 'new-password',
              ]); ?>
              <?php onboarding_floating_input('confirm_password', 'Passwort bestätigen', [
                  'type' => 'password',
                  'required' => true,
                  'autocomplete' => 'new-password',
              ]); ?>
            </div>

            <div class="onboarding-form-actions">
              <span></span>
              <?php onboarding_render_btn_next(); ?>
            </div>
          </form>

<?php
onboarding_shell_close();
?>
<?php onboarding_render_notice(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('passwordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const submitBtn = form.querySelector('button[type="submit"]');
    const banner = document.getElementById('passwordRequirementsBanner');
    const reqItems = banner ? banner.querySelectorAll('[data-req]') : [];
    const userHints = <?php echo json_encode($passwordUserHints, JSON_UNESCAPED_UNICODE); ?>;
    const weakPasswords = [
        '12345678', '123456789', '1234567890', '87654321', '01234567', '1234567',
        '11111111', '00000000', 'password', 'passwort', 'passwort1', 'qwerty12',
        'qwertyui', 'admin123', 'letmein1', 'welcome1', 'iloveyou', 'sunshine',
        'abc12345', 'asdf1234', 'test1234', 'master12', 'changeme', 'serohub123'
    ];
    const checkSvg = '<svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
    const checkApiUrl = '<?php echo BASE_URL; ?>onboarding/api/step1.php';
    let differentOk = false;
    let differentCheckTimer = null;
    let differentCheckRequestId = 0;

    function hasDigit(value) {
        return /\d/.test(value);
    }

    function isSequentialDigits(value) {
        if (!/^\d+$/.test(value) || value.length < 4) return false;
        let ascending = true;
        let descending = true;
        for (let i = 1; i < value.length; i++) {
            if (Number(value[i]) !== Number(value[i - 1]) + 1) ascending = false;
            if (Number(value[i]) !== Number(value[i - 1]) - 1) descending = false;
        }
        return ascending || descending;
    }

    function isGuessable(value) {
        const normalized = value.trim().toLowerCase();
        if (!normalized) return true;
        if (weakPasswords.includes(normalized)) return true;
        if (isSequentialDigits(value)) return true;
        if (/^(.)\1+$/u.test(value)) return true;
        return userHints.some(function(hint) {
            return hint && normalized === hint;
        });
    }

    function evaluateRequirements() {
        const pwd = newPassword.value;
        const confirm = confirmPassword.value;
        const status = {
            length: pwd.length >= 8,
            digit: pwd.length >= 8 && hasDigit(pwd),
            guessable: pwd.length >= 8 && !isGuessable(pwd),
            different: pwd.length >= 8 && differentOk,
            match: pwd.length > 0 && confirm.length > 0 && pwd === confirm
        };

        reqItems.forEach(function(item) {
            const req = item.getAttribute('data-req');
            const met = !!status[req];
            item.classList.toggle('is-met', met);
            const marker = item.querySelector('.onboarding-password-banner__marker');
            if (marker) {
                marker.innerHTML = met ? checkSvg : '';
            }
        });

        const allMet = status.length && status.digit && status.guessable && status.different && status.match;
        if (banner) {
            banner.classList.toggle('is-hidden', allMet);
            banner.setAttribute('aria-hidden', allMet ? 'true' : 'false');
        }
        onboardingSetNextVisible(submitBtn, allMet);

        return allMet;
    }

    function checkPasswordMatch() {
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Die Passwörter stimmen nicht überein.');
            } else {
                confirmPassword.setCustomValidity('');
            }
        } else {
            confirmPassword.setCustomValidity('');
        }

        if (newPassword.value.length >= 8 && !hasDigit(newPassword.value)) {
            newPassword.setCustomValidity('Das Passwort muss mindestens eine Zahl enthalten.');
        } else if (newPassword.value.length >= 8 && isGuessable(newPassword.value)) {
            newPassword.setCustomValidity('Bitte wähle ein sichereres Passwort.');
        } else if (newPassword.value.length >= 8 && !differentOk) {
            newPassword.setCustomValidity('Das neue Passwort muss sich vom bisherigen Passwort unterscheiden.');
        } else {
            newPassword.setCustomValidity('');
        }

        evaluateRequirements();
    }

    function scheduleDifferentCheck() {
        clearTimeout(differentCheckTimer);
        const pwd = newPassword.value;

        if (pwd.length < 8) {
            differentOk = false;
            checkPasswordMatch();
            return;
        }

        differentCheckTimer = setTimeout(async function() {
            const requestId = ++differentCheckRequestId;
            try {
                const response = await fetch(checkApiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ check_only: true, new_password: pwd })
                });
                const data = await response.json();
                if (requestId !== differentCheckRequestId) return;
                differentOk = response.ok && !!data.different;
            } catch (error) {
                if (requestId !== differentCheckRequestId) return;
                differentOk = false;
            }
            checkPasswordMatch();
        }, 350);
    }

    async function refreshDifferentCheck() {
        const pwd = newPassword.value;
        if (pwd.length < 8) {
            differentOk = false;
            checkPasswordMatch();
            return false;
        }

        try {
            const response = await fetch(checkApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ check_only: true, new_password: pwd })
            });
            const data = await response.json();
            differentOk = response.ok && !!data.different;
        } catch (error) {
            differentOk = false;
        }

        checkPasswordMatch();
        return differentOk;
    }

    newPassword.addEventListener('input', function() {
        checkPasswordMatch();
        scheduleDifferentCheck();
    });
    confirmPassword.addEventListener('input', checkPasswordMatch);
    evaluateRequirements();

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        checkPasswordMatch();
        const isDifferent = await refreshDifferentCheck();

        if (!form.checkValidity() || !isDifferent) {
            if (!isDifferent) {
                onboardingShowNotice('Das neue Passwort muss sich vom bisherigen Passwort unterscheiden.');
            } else {
                form.reportValidity();
            }
            return;
        }

        onboardingBtnSetLoading(submitBtn, true);

        try {
            const response = await fetch('<?php echo BASE_URL; ?>onboarding/api/step1.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    new_password: newPassword.value,
                    confirm_password: confirmPassword.value
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Fehler beim Ändern des Passworts');
            }

            if (data.success) {
                setTimeout(() => {
                    window.location.href = '<?php echo onboarding_step_url(2); ?>';
                }, 400);
            } else {
                throw new Error(data.error || 'Fehler beim Ändern des Passworts');
            }
        } catch (error) {
            console.error('Fehler:', error);
            onboardingShowNotice(error.message || 'Fehler beim Ändern des Passworts');
        } finally {
            onboardingBtnSetLoading(submitBtn, false);
            evaluateRequirements();
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
