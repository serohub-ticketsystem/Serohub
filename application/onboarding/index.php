<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once __DIR__ . '/includes/layout.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$userId = $_SESSION['user_id'];
$user = null;
$onboardingStatus = [
    'step1_completed' => false,
    'step2_completed' => false,
    'step3_completed' => false,
    'step4_completed' => false,
];

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.vorname, u.nachname, u.rolle, u.status, u.company_id, u.logopfad,
               u.onboarding_abgeschlossen, u.letztes_pw_change
        FROM users u
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['onboarding_abgeschlossen'] == 1) {
            header('Location: ' . BASE_URL . 'dashboard/');
            exit();
        }

        $onboardingStatus = onboarding_status_from_user($user);
        onboarding_redirect_to_current_step($onboardingStatus);
    }
} catch (PDOException $e) {
    error_log("Onboarding: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
onboarding_layout_styles();
?>

<div id="main-content" class="onboarding-root relative w-full overflow-hidden bg-gray-50 dark:bg-primary-50">
<?php onboarding_layout_body_script(); ?>
<?php
onboarding_shell_open([
    'illustration' => 'welcome',
    'current_step' => 0,
    'status' => $onboardingStatus,
]);
?>

          <div class="onboarding-step-header">
            <h1 class="text-gray-900 dark:text-white">Alles erledigt!</h1>
            <p class="text-gray-600 dark:text-gray-400">Dein Konto ist eingerichtet. Du kannst Serohub jetzt nutzen.</p>
          </div>

          <div class="rounded-xl border border-green-200 bg-green-50 p-5 dark:border-green-800 dark:bg-green-900/20">
            <p class="text-base text-green-800 dark:text-green-200">
              Klicke auf „Onboarding abschließen“, um zum Dashboard zu gelangen.
            </p>
          </div>

          <div class="mt-5">
            <button id="completeOnboarding" type="button" class="w-full sm:w-auto px-8 py-3 text-lg bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
              Onboarding abschließen
            </button>
          </div>

<?php
onboarding_shell_close();
?>
<?php onboarding_render_notice(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const completeBtn = document.getElementById('completeOnboarding');

    if (!completeBtn) {
        return;
    }

    completeBtn.addEventListener('click', async function() {
        onboardingBtnSetLoading(completeBtn, true);

        try {
            const response = await fetch('<?php echo BASE_URL; ?>onboarding/api/complete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                try { sessionStorage.removeItem('serohub_onboarding_tip_index'); } catch (e) {}
                setTimeout(() => {
                    window.location.href = '<?php echo BASE_URL; ?>dashboard/';
                }, 400);
            } else {
                throw new Error(data.error || 'Fehler beim Abschließen des Onboardings');
            }
        } catch (error) {
            console.error('Fehler:', error);
            onboardingShowNotice(error.message || 'Fehler beim Abschließen des Onboardings');
        } finally {
            onboardingBtnSetLoading(completeBtn, false);
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
