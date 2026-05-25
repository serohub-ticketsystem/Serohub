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
$userName = '';

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.vorname, u.nachname, u.email
        FROM users u
        WHERE u.id = :user_id
        LIMIT 1
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
        if (empty($userName)) {
            $userName = $user['email'] ?? 'Benutzer';
        }
    }
} catch (PDOException $e) {
    error_log("Easy Mode: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
}

// Easy Mode Card-Einstellungen laden
$cardSettings = [
    'easy_problem_melden' => true,
    'easy_meine_probleme' => true,
    'easy_meine_geraete' => true,
    'easy_shop' => true
];

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'easy_mode_cards_enabled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['setting_value'])) {
        $enabledCards = json_decode($result['setting_value'], true);
        if (is_array($enabledCards)) {
            foreach ($cardSettings as $cardId => $defaultValue) {
                $cardSettings[$cardId] = isset($enabledCards[$cardId]) ? (bool)$enabledCards[$cardId] : $defaultValue;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Easy Mode: Fehler beim Laden der Card-Einstellungen: " . $e->getMessage());
}

// Logo vom Branding laden
$easyLogo = 'assets/images/Serohub_Icon.png'; // Standard-Logo
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'branding_logo'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty(trim($result['setting_value'] ?? ''))) {
        $easyLogo = trim($result['setting_value']);
    }
} catch (PDOException $e) {
    error_log("Easy Mode: Fehler beim Laden des Logos: " . $e->getMessage());
}
$easyLogoUrl = BASE_URL . ltrim($easyLogo, '/');

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

// Sidebar und Navigation für Easy Mode ausblenden
echo '<style>
.lg\\:ms-64 { margin-left: 0 !important; }
nav.fixed { display: none !important; }
nav.fixed + div { display: none !important; }
#sidebar { display: none !important; }
.fixed.top-16 { display: none !important; }
</style>';
?>

<style>
.easy-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

.easy-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.easy-card-illustration {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: linear-gradient(135deg, rgb(21, 93, 252) 0%, rgb(26, 110, 255) 100%);
}

.easy-card-illustration svg {
    width: 64px;
    height: 64px;
    color: white;
}

.easy-card.problem .easy-card-illustration {
    background: linear-gradient(135deg, rgb(239, 68, 68) 0%, rgb(245, 158, 11) 100%);
}

.easy-card.tickets .easy-card-illustration {
    background: linear-gradient(135deg, rgb(21, 93, 252) 0%, rgb(56, 189, 248) 100%);
}

.easy-card.devices .easy-card-illustration {
    background: linear-gradient(135deg, rgb(34, 197, 94) 0%, rgb(21, 93, 252) 100%);
}

.easy-card.shop .easy-card-illustration {
    background: linear-gradient(135deg, rgb(21, 93, 252) 0%, rgb(245, 158, 11) 100%);
}
</style>

<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 pt-8 overflow-hidden">
  <main class="pt-8 pb-8 px-4">
    <div class="max-w-6xl mx-auto">
      <!-- Header -->
      <div class="mb-8 text-center">
        <div class="mb-6 flex justify-center">
          <img src="<?php echo htmlspecialchars($easyLogoUrl); ?>" 
               alt="Logo" 
               class="h-16 md:h-20 object-contain"
               onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-avatar.png'">
        </div>
        <h1 class="text-4xl font-bold text-gray-900 dark:text-primary-200 mb-2">
          Willkommen, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>!
        </h1>
        <p class="text-lg text-gray-600 dark:text-primary-210">
          Wählen Sie eine Funktion aus
        </p>
      </div>

      <!-- Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Problem melden Card -->
        <?php if ($cardSettings['easy_problem_melden']): ?>
        <a href="<?php echo BASE_URL; ?>easy/problem-melden.php" class="easy-card problem block bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120">
          <div class="easy-card-illustration mb-6">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/>
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-primary-200 mb-3 text-center">
            Problem melden
          </h2>
          <p class="text-gray-600 dark:text-primary-210 text-center mb-6">
            Melden Sie ein neues Problem oder eine Störung. Wir helfen Ihnen gerne weiter.
          </p>
          <div class="text-center">
            <button class="bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-3 px-8 rounded-lg transition-colors">
              Problem melden
            </button>
          </div>
        </a>
        <?php endif; ?>

        <!-- Meine Probleme Card -->
        <?php if ($cardSettings['easy_meine_probleme']): ?>
        <a href="<?php echo BASE_URL; ?>easy/meine-probleme.php" class="easy-card tickets block bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120">
          <div class="easy-card-illustration mb-6">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-primary-200 mb-3 text-center">
            Meine Probleme
          </h2>
          <p class="text-gray-600 dark:text-primary-210 text-center mb-6">
            Sehen Sie alle Ihre gemeldeten Probleme und deren Status auf einen Blick.
          </p>
          <div class="text-center">
            <button class="bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-3 px-8 rounded-lg transition-colors">
              Probleme anzeigen
            </button>
          </div>
        </a>
        <?php endif; ?>

        <!-- Meine Geräte Card -->
        <?php if ($cardSettings['easy_meine_geraete']): ?>
        <a href="<?php echo BASE_URL; ?>easy/meine-geraete.php" class="easy-card devices block bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120">
          <div class="easy-card-illustration mb-6">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v5m-3 0h6M4 11h16M5 15h14a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1Z"/>
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-primary-200 mb-3 text-center">
            Meine Geräte
          </h2>
          <p class="text-gray-600 dark:text-primary-210 text-center mb-6">
            Verwalten Sie Ihre Geräte und sehen Sie alle wichtigen Informationen.
          </p>
          <div class="text-center">
            <button class="bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-3 px-8 rounded-lg transition-colors">
              Geräte anzeigen
            </button>
          </div>
        </a>
        <?php endif; ?>

        <!-- Shop Card -->
        <?php if ($cardSettings['easy_shop']): ?>
        <a href="<?php echo BASE_URL; ?>easy/shop.php" class="easy-card shop block bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120">
          <div class="easy-card-illustration mb-6">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/>
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-primary-200 mb-3 text-center">
            Shop
          </h2>
          <p class="text-gray-600 dark:text-primary-210 text-center mb-6">
            Bestellen Sie neue Geräte oder Zubehör einfach und bequem.
          </p>
          <div class="text-center">
            <button class="bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-3 px-8 rounded-lg transition-colors">
              Zum Shop
            </button>
          </div>
        </a>
        <?php endif; ?>
      </div>

      <!-- Zurück zur normalen Ansicht und Logout -->
      <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="<?php echo BASE_URL; ?>dashboard/" class="text-gray-500 hover:text-gray-700 dark:text-primary-220 dark:hover:text-primary-210 text-xs font-normal opacity-70 hover:opacity-100 transition-opacity">
          Zur normalen Ansicht wechseln
        </a>
        <span class="text-gray-300 dark:text-primary-230 text-xs">|</span>
        <a href="<?php echo BASE_URL; ?>logout.php" class="text-primary-1080 hover:text-primary-1080 dark:text-primary-1080 dark:hover:text-primary-1080 text-xs font-normal">
          Abmelden
        </a>
      </div>
    </div>
  </main>
</div>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
