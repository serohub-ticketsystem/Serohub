<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/user_profile_fields.php';
require_once __DIR__ . '/includes/layout.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

user_profile_fields_ensure_columns($pdo);

$userId = (int) $_SESSION['user_id'];
$user = null;
$extraSql = user_profile_fields_select_extra_sql($pdo);

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.vorname, u.nachname, u.letztes_pw_change{$extraSql}
        FROM users u
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['letztes_pw_change'])) {
        header('Location: ' . onboarding_step_url(1));
        exit();
    }
} catch (PDOException $e) {
    error_log('Onboarding Step 2: Fehler beim Laden der Benutzerdaten: ' . $e->getMessage());
    header('Location: ' . onboarding_step_url(1));
    exit;
}

$onboardingStatus = onboarding_status_from_user($user);

$profilePrefilled = !empty(trim((string) ($user['vorname'] ?? '')))
    && !empty(trim((string) ($user['nachname'] ?? '')))
    && !empty(trim((string) ($user['email'] ?? '')));

$anredeValue = (string) ($user['anrede'] ?? '');

include dirname(__DIR__) . '/assets/frontend/head.php';
onboarding_layout_styles();
?>

<div id="main-content" class="onboarding-root relative w-full overflow-hidden bg-gray-50 dark:bg-primary-50">
<?php onboarding_layout_body_script(); ?>
<?php
onboarding_shell_open([
    'illustration' => 'step2',
    'current_step' => 2,
    'status' => $onboardingStatus,
]);
?>

          <div class="onboarding-step-header">
            <h1 class="text-gray-900 dark:text-white"><?php echo $profilePrefilled ? 'Stimmen deine Angaben?' : 'Wer bist du?'; ?></h1>
            <p class="text-gray-600 dark:text-gray-400">
              <?php if ($profilePrefilled): ?>
              Prüfe dein Profil und passe bei Bedarf Name, Anrede oder E-Mail an.
              <?php else: ?>
              Bitte trage deinen Namen und deine E-Mail ein – Anrede ist optional.
              <?php endif; ?>
            </p>
          </div>

          <form id="profileForm" class="onboarding-form-compact">
            <div class="onboarding-form-fields">
              <?php onboarding_render_anrede_field($anredeValue); ?>
              <div class="onboarding-form-fields onboarding-form-fields--grid onboarding-form-fields--grid-2">
                <?php onboarding_floating_input('vorname', 'Vorname', ['value' => $user['vorname'] ?? '', 'required' => true]); ?>
                <?php onboarding_floating_input('nachname', 'Nachname', ['value' => $user['nachname'] ?? '', 'required' => true]); ?>
                <div class="sm:col-span-2">
                  <?php onboarding_floating_input('email', 'E-Mail-Adresse', ['type' => 'email', 'value' => $user['email'] ?? '', 'required' => true]); ?>
                </div>
              </div>
            </div>

            <div class="onboarding-form-actions">
              <?php if (onboarding_step_is_accessible(1, $onboardingStatus)): ?>
              <a href="<?php echo onboarding_step_url(1); ?>" class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Zurück</a>
              <?php else: ?><span></span><?php endif; ?>
              <?php onboarding_render_btn_next(); ?>
            </div>
          </form>

<?php onboarding_shell_close(); ?>
<?php onboarding_render_notice(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profileForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    const fields = ['vorname', 'nachname', 'email'].map(function(id) { return form.querySelector('#' + id); });

    onboardingInitChoiceFields(form);

    function evaluateProfileReady() {
        const ready = fields.every(function(el) { return el && el.value.trim() !== ''; })
            && fields[2] && fields[2].checkValidity();
        onboardingSetNextVisible(submitBtn, ready);
        return ready;
    }

    fields.forEach(function(el) {
        if (el) el.addEventListener('input', evaluateProfileReady);
    });
    evaluateProfileReady();

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        onboardingBtnSetLoading(submitBtn, true);
        try {
            const response = await fetch('<?php echo BASE_URL; ?>onboarding/api/step2.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Fehler beim Speichern');
            setTimeout(() => { window.location.href = '<?php echo onboarding_step_url(3); ?>'; }, 400);
        } catch (error) {
            onboardingShowNotice(error.message);
            onboardingBtnSetLoading(submitBtn, false);
            evaluateProfileReady();
        }
    });
});
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
