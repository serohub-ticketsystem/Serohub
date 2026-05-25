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
        SELECT u.id, u.email, u.vorname, u.nachname, u.telefonnummer, u.letztes_pw_change{$extraSql}
        FROM users u
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['letztes_pw_change'])) {
        header('Location: ' . onboarding_step_url(1));
        exit();
    }
    if (empty($_SESSION['onboarding_profile_step_seen'])) {
        header('Location: ' . onboarding_step_url(2));
        exit();
    }
} catch (PDOException $e) {
    error_log('Onboarding Step 3: Fehler beim Laden der Benutzerdaten: ' . $e->getMessage());
    header('Location: ' . onboarding_step_url(1));
    exit;
}

$onboardingStatus = onboarding_status_from_user($user);
$kontaktkanalOptions = user_profile_fields_kontaktkanal_options();
$kontaktkanalValue = (string) ($user['kontaktkanal'] ?? 'email');
if (!array_key_exists($kontaktkanalValue, $kontaktkanalOptions)) {
    $kontaktkanalValue = 'email';
}

include dirname(__DIR__) . '/assets/frontend/head.php';
onboarding_layout_styles();
?>

<div id="main-content" class="onboarding-root relative w-full overflow-hidden bg-gray-50 dark:bg-primary-50">
<?php onboarding_layout_body_script(); ?>
<?php
onboarding_shell_open([
    'illustration' => 'step2',
    'current_step' => 3,
    'status' => $onboardingStatus,
    'shell_modifier' => 'onboarding-shell--contact',
    'step_class' => 'onboarding-step--contact',
]);
?>

          <div class="onboarding-step-header">
            <h1 class="text-gray-900 dark:text-white">Wie erreichen wir dich?</h1>
            <p class="text-gray-600 dark:text-gray-400">Alles optional – fülle nur aus, was für dich passt, oder überspringe den Schritt.</p>
          </div>

          <form id="contactForm" class="onboarding-form-compact">
            <div class="onboarding-form-fields">
              <section class="onboarding-form-section" aria-labelledby="contact-section-phone">
                <h2 class="onboarding-form-section-title" id="contact-section-phone">Telefonnummern</h2>
                <p class="onboarding-form-section__hint">Festnetz und Mobil – beides optional.</p>
                <div class="onboarding-form-fields onboarding-form-fields--grid onboarding-form-fields--grid-2">
                  <?php onboarding_floating_input('telefonnummer', 'Telefon (Festnetz)', ['type' => 'tel', 'value' => $user['telefonnummer'] ?? '']); ?>
                  <?php onboarding_floating_input('mobilnummer', 'Mobilnummer', ['type' => 'tel', 'value' => $user['mobilnummer'] ?? '']); ?>
                </div>
              </section>

              <section class="onboarding-form-section" aria-labelledby="contact-section-channel">
                <h2 class="onboarding-form-section-title" id="contact-section-channel">Bevorzugter Kontakt</h2>
                <p class="onboarding-form-section__hint">Wie sollen wir dich am liebsten erreichen?</p>
                <?php onboarding_render_choice_field('kontaktkanal', 'Kontaktkanal', $kontaktkanalOptions, [
                    'value' => $kontaktkanalValue,
                    'hide_label' => true,
                    'aria_label' => 'Bevorzugter Kontakt',
                ]); ?>
              </section>

              <section class="onboarding-form-section" aria-labelledby="contact-section-hours">
                <h2 class="onboarding-form-section-title" id="contact-section-hours">Erreichbarkeit</h2>
                <p class="onboarding-form-section__hint">Wähle Wochentage und deine erreichbaren Zeiten.</p>
                <?php onboarding_render_erreichbarkeit_field($user['erreichbarkeit'] ?? ''); ?>
              </section>
            </div>

            <div class="onboarding-form-actions">
              <a href="<?php echo onboarding_step_url(2); ?>" class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Zurück</a>
              <div class="flex items-center gap-3">
                <button type="button" id="skipContactBtn" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Überspringen</button>
                <?php onboarding_render_btn_next(); ?>
              </div>
            </div>
          </form>

<?php onboarding_shell_close(); ?>
<?php onboarding_render_notice(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    const skipBtn = document.getElementById('skipContactBtn');
    const submitBtn = form.querySelector('button[type="submit"]');
    const nextUrl = '<?php echo onboarding_step_url(4); ?>';
    const apiUrl = '<?php echo BASE_URL; ?>onboarding/api/step3.php';

    onboardingInitChoiceFields(form);
    if (window.initErreichbarkeitFields) initErreichbarkeitFields(form);

    const kontaktkanalInput = form.querySelector('#kontaktkanal');
    const emailSettingsUrl = '<?php echo BASE_URL; ?>settings/api/email.php';

    function enableEmailNotifications() {
        return fetch(emailSettingsUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled: true })
        }).catch(function(e) { console.error(e); });
    }

    if (kontaktkanalInput) {
        kontaktkanalInput.addEventListener('change', function() {
            if (kontaktkanalInput.value === 'email') {
                enableEmailNotifications();
            }
        });
    }

    function evaluateContactReady() {
        onboardingSetNextVisible(submitBtn, onboardingFormIsDirty(form));
    }

    onboardingInitFormSnapshot(form);
    form.addEventListener('input', evaluateContactReady);
    form.addEventListener('change', evaluateContactReady);
    evaluateContactReady();

    function goNext() { window.location.href = nextUrl; }

    if (skipBtn) {
        skipBtn.addEventListener('click', async function() {
            skipBtn.disabled = true;
            try {
                await fetch(apiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ skip: true }) });
            } catch (e) { console.error(e); }
            goNext();
        });
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        onboardingBtnSetLoading(submitBtn, true);
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Fehler beim Speichern');
            setTimeout(goNext, 400);
        } catch (error) {
            onboardingShowNotice(error.message);
            onboardingBtnSetLoading(submitBtn, false);
            evaluateContactReady();
        }
    });
});
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
