<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
require_once dirname(__DIR__) . '/assets/user_profile_fields.php';
requireLogin();

user_profile_fields_ensure_columns($pdo);

// BASE_URL definieren falls nicht bereits definiert
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
$user = null;
$delegateCandidates = [];
$timezoneOptions = user_profile_fields_timezone_options();
$stats = [
    'favorites' => 0,
    'orders' => 0,
    'reviews' => 0,
    'returns' => 0
];

try {
    // Benutzerdaten abrufen
    $profileExtraSql = user_profile_fields_select_extra_sql($pdo);
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.vorname, u.nachname, u.telefonnummer, u.rolle, u.status, u.company_id, u.customer_id, u.logopfad{$profileExtraSql},
               c.name as company_name, c.logo as company_logo, c.email as company_email, 
               c.telefonnummer as company_telefon, c.adresse as company_adresse, 
               c.plz as company_plz, c.ort as company_ort, c.kundennummer as company_kundennummer,
               c.lieferadresse as company_lieferadresse, c.liefer_plz as company_liefer_plz, 
               c.liefer_ort as company_liefer_ort, c.rechnungs_adresse as company_rechnungs_adresse,
               c.rechnungs_plz as company_rechnungs_plz, c.rechnungs_ort as company_rechnungs_ort,
               c.zugewiesen_an as company_zugewiesen_an,
               assigned_user.vorname as assigned_user_vorname, assigned_user.nachname as assigned_user_nachname,
               cust.name as customer_name, cust.adresse as customer_adresse, 
               cust.plz as customer_plz, cust.ort as customer_ort,
               cust.lieferadresse as customer_lieferadresse, cust.liefer_plz as customer_liefer_plz,
               cust.liefer_ort as customer_liefer_ort, cust.rechnungs_adresse as customer_rechnungs_adresse,
               cust.rechnungs_plz as customer_rechnungs_plz, cust.rechnungs_ort as customer_rechnungs_ort
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN users assigned_user ON c.zugewiesen_an = assigned_user.id
        LEFT JOIN customers cust ON u.customer_id = cust.id
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $delegateCandidates = user_profile_fields_delegate_candidates(
            $pdo,
            $userId,
            !empty($user['company_id']) ? (int) $user['company_id'] : null
        );
        $companyRow = ['name' => $user['company_name'] ?? null, 'email' => $user['company_email'] ?? null, 'telefonnummer' => $user['company_telefon'] ?? null, 'adresse' => $user['company_adresse'] ?? null, 'plz' => $user['company_plz'] ?? null, 'ort' => $user['company_ort'] ?? null, 'kundennummer' => $user['company_kundennummer'] ?? null, 'lieferadresse' => $user['company_lieferadresse'] ?? null, 'liefer_plz' => $user['company_liefer_plz'] ?? null, 'liefer_ort' => $user['company_liefer_ort'] ?? null, 'rechnungs_adresse' => $user['company_rechnungs_adresse'] ?? null, 'rechnungs_plz' => $user['company_rechnungs_plz'] ?? null, 'rechnungs_ort' => $user['company_rechnungs_ort'] ?? null];
        decrypt_company_row($companyRow);
        $user['company_name'] = $companyRow['name'];
        $user['company_email'] = $companyRow['email'];
        $user['company_telefon'] = $companyRow['telefonnummer'];
        $user['company_adresse'] = $companyRow['adresse'];
        $user['company_plz'] = $companyRow['plz'];
        $user['company_ort'] = $companyRow['ort'];
        $user['company_kundennummer'] = $companyRow['kundennummer'];
        $user['company_lieferadresse'] = $companyRow['lieferadresse'];
        $user['company_liefer_plz'] = $companyRow['liefer_plz'];
        $user['company_liefer_ort'] = $companyRow['liefer_ort'];
        $user['company_rechnungs_adresse'] = $companyRow['rechnungs_adresse'];
        $user['company_rechnungs_plz'] = $companyRow['rechnungs_plz'];
        $user['company_rechnungs_ort'] = $companyRow['rechnungs_ort'];
    }
    if (!$user) {
        header('Location: ' . BASE_URL . 'dashboard/');
        exit;
    }
    
    // Statistiken abrufen
    // Tickets (todos)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM todos WHERE (erstellt_von = ? OR zugewiesen_an = ?)");
        $stmt->execute([$userId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['favorites'] = $result['count'] ?? 0;
    } catch (PDOException $e) {
        $stats['favorites'] = 0;
    }
    
    // Geräte (devices)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM devices WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['orders'] = $result['count'] ?? 0;
    } catch (PDOException $e) {
        $stats['orders'] = 0;
    }
    
    // Benachrichtigungen (notifications)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND ist_gelesen = 0");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['reviews'] = $result['count'] ?? 0;
    } catch (PDOException $e) {
        $stats['reviews'] = 0;
    }
    
    // Tickets
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE (erstellt_von = ? OR zugewiesen_an = ?)");
        $stmt->execute([$userId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['returns'] = $result['count'] ?? 0;
    } catch (PDOException $e) {
        $stats['returns'] = 0;
    }
    
    // Tickets/Bestellungen abrufen
    $tickets = [];
    try {
        $stmt = $pdo->prepare("
            SELECT id, titel, status, prioritaet, erstellt_datum, ticket_nummer 
            FROM tickets 
            WHERE (erstellt_von = ? OR zugewiesen_an = ?) 
            ORDER BY erstellt_datum DESC 
            LIMIT 3
        ");
        $stmt->execute([$userId, $userId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tickets = [];
    }
    
} catch (PDOException $e) {
    error_log("Account: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Avatar-Pfad bestimmen und Initialen erstellen
$fullName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
if (empty($fullName)) {
    $fullName = $user['email'] ?? 'Benutzer';
}

// Initialen für Avatar erstellen
$initials = '';
if (!empty($user['vorname']) && !empty($user['nachname'])) {
    $initials = strtoupper(substr($user['vorname'], 0, 1) . substr($user['nachname'], 0, 1));
} elseif (!empty($user['vorname'])) {
    $initials = strtoupper(substr($user['vorname'], 0, 1));
} elseif (!empty($user['email'])) {
    $initials = strtoupper(substr($user['email'], 0, 1));
} else {
    $initials = 'U';
}

// Avatar-Logik: Preset oder Bild oder Initialen
$avatarIsPreset = false;
$avatarPresetColor = '#6b7280';
$avatarPresetInitials = $initials;
$avatarImagePath = null;

if (!empty($user['logopfad'])) {
    if (strpos($user['logopfad'], 'preset:') === 0) {
        // Preset-Avatar: Format preset:{color}:{initials}
        $avatarIsPreset = true;
        $presetParts = explode(':', $user['logopfad']);
        $avatarPresetColor = $presetParts[1] ?? '#6b7280';
        if (strpos($avatarPresetColor, '#') !== 0) {
            $avatarPresetColor = '#' . $avatarPresetColor;
        }
        $avatarPresetInitials = $presetParts[2] ?? $initials;
    } else {
        // Normales Bild
        $avatarImagePath = $user['logopfad'];
    }
}

// Rolle-Badge Text bestimmen - Firmenname oder Kundename
$rolleBadgeText = $user['rolle'] ?? 'Benutzer';
if (!empty($user['customer_name'])) {
    $rolleBadgeText = htmlspecialchars($user['customer_name']);
} elseif (!empty($user['company_name'])) {
    $rolleBadgeText = htmlspecialchars($user['company_name']);
}
$displayName = !empty($user['customer_id'])
    ? ($user['customer_name'] ?? $fullName)
    : ($user['company_name'] ?? $fullName);

// Marketing-Emails Status abrufen
$marketingEmailsEnabled = false;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'marketing_emails_enabled'");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['setting_value'] !== null) {
        $marketingEmailsEnabled = filter_var($result['setting_value'], FILTER_VALIDATE_BOOLEAN);
    }
} catch (PDOException $e) {
    error_log("Account: Fehler beim Laden der Marketing-Emails-Einstellung: " . $e->getMessage());
    $marketingEmailsEnabled = false;
}

// 2FA Status abrufen
$twoFaEnabled = false;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_enabled'");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['setting_value'] !== null) {
        $twoFaEnabled = ($result['setting_value'] === '1' || $result['setting_value'] === 'true');
    }
} catch (PDOException $e) {
    error_log("Account: Fehler beim Laden der 2FA-Einstellung: " . $e->getMessage());
    $twoFaEnabled = false;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
?>
<script src="<?php echo htmlspecialchars(BASE_URL); ?>assets/js/nav-unsaved-changes.js"></script>
<?php
$navMobileCompactTitle = 'Serohub Account';
$navMobileHideCompactCreateButton = true;
$navMobileCompactShowLogoutButton = true;
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

// Firmenlogo-Pfad bestimmen - verwende getLogoUrl für korrekte URL-Generierung (nach nav.php include)
$companyLogoPath = getLogoUrl($user['company_logo'] ?? '');

?>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden lg:overflow-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 lg:h-screen app-mobile-no-root-overscroll">
  <main class="mx-4 mt-2 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 lg:flex lg:h-[calc(100vh-0.5rem)] lg:flex-col lg:overflow-hidden">
    <div class="lg:flex-1 lg:min-h-0">
      <div class="grid grid-cols-12 gap-x-4 gap-y-0 bg-gray-50 dark:bg-primary-50 lg:h-full lg:min-h-0">
        <!-- Header -->
        <div class="col-span-full lg:min-h-0">
          <div class="mb-4">
            <nav class="mb-3 flex flex-shrink-0 hidden lg:flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                    <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                      <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                    </svg>
                    Startseite
                  </a>
                </li>
                <li>
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <a href="<?php echo htmlspecialchars(BASE_URL); ?>account/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2">Mein Konto</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Persönliche Daten</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="lg:hidden"></div>
          </div>
        </div>

        <div class="col-span-full lg:hidden">
          <div class="space-y-3">
            <section class="pt-0.5">
              <div class="text-center">
                <button type="button" id="mobileAvatarTriggerBtn" class="group relative mx-auto inline-flex" aria-label="Profilbild ändern">
                  <?php if ($avatarIsPreset): ?>
                    <div class="h-24 w-24 rounded-full text-center text-2xl font-semibold leading-[6rem] text-white shadow-sm ring-2 ring-gray-200 transition-transform group-hover:scale-[1.02] dark:ring-gray-700" style="background-color: <?php echo htmlspecialchars($avatarPresetColor); ?>;">
                      <?php echo htmlspecialchars($avatarPresetInitials); ?>
                    </div>
                  <?php elseif ($avatarImagePath): ?>
                    <img class="h-24 w-24 rounded-full border border-gray-200 object-cover shadow-sm ring-2 ring-gray-200 transition-transform group-hover:scale-[1.02] dark:border-gray-700 dark:ring-gray-700" src="<?php echo htmlspecialchars(getUserAvatarUrl($avatarImagePath)); ?>" alt="Profilbild" />
                  <?php else: ?>
                    <div class="h-24 w-24 rounded-full bg-gray-500 text-center text-2xl font-semibold leading-[6rem] text-white shadow-sm ring-2 ring-gray-200 transition-transform group-hover:scale-[1.02] dark:ring-gray-700">
                      <?php echo htmlspecialchars($initials); ?>
                    </div>
                  <?php endif; ?>
                </button>
                <input type="file" id="mobileAvatarFileInput" class="hidden" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <p class="mt-3 truncate text-base font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($fullName); ?></p>
                <p class="mt-0.5 truncate text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></p>
              </div>
              <div class="mt-4 grid grid-cols-2 gap-3">
                <a href="<?php echo BASE_URL; ?>settings/resetpasswort.php" class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/80 p-3.5 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-900/40 dark:hover:bg-gray-700/60">
                  <div class="min-w-0">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Passwort</h4>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Ändern</p>
                  </div>
                  <svg class="ms-3 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                  </svg>
                </a>
                <a href="<?php echo BASE_URL; ?>settings/twofa.php" class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/80 p-3.5 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-900/40 dark:hover:bg-gray-700/60">
                  <div class="min-w-0">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Zwei-Faktor</h4>
                    <p class="mt-0.5 text-xs <?php echo $twoFaEnabled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                      <?php echo $twoFaEnabled ? 'Aktiviert' : 'Deaktiviert'; ?>
                    </p>
                  </div>
                  <svg class="ms-3 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                  </svg>
                </a>
              </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
              <h2 class="text-base font-semibold text-gray-900 dark:text-white">Kontakt & Profil</h2>
              <dl class="mt-3 space-y-3 text-sm">
                <div class="rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-900/40">
                  <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Telefon / Mobil</dt>
                  <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                    <?php
                    $telParts = array_filter([
                        !empty($user['telefonnummer']) ? 'Festnetz: ' . $user['telefonnummer'] : '',
                        !empty($user['mobilnummer']) ? 'Mobil: ' . $user['mobilnummer'] : '',
                    ]);
                    echo htmlspecialchars($telParts ? implode(' · ', $telParts) : '-');
                    ?>
                  </dd>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-900/40">
                  <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Firma/Konto</dt>
                  <dd class="mt-0.5 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($displayName ?? $rolleBadgeText); ?></dd>
                </div>
              </dl>
              <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/40">
                <div class="mb-2 flex items-center justify-between">
                  <p class="text-sm font-semibold text-gray-900 dark:text-white">Deine Firma</p>
                  <span class="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">Übersicht</span>
                </div>
                <div class="space-y-2 text-sm">
                  <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</p>
                    <p class="mt-0.5 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></p>
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <div>
                      <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Kundennr.</p>
                      <p class="mt-0.5 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['company_kundennummer'] ?? '-'); ?></p>
                    </div>
                    <div>
                      <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Telefon</p>
                      <p class="mt-0.5 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['company_telefon'] ?? '-'); ?></p>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">E-Mail</p>
                    <p class="mt-0.5 break-all font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['company_email'] ?? '-'); ?></p>
                  </div>
                </div>
              </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
              <h2 class="text-base font-semibold text-gray-900 dark:text-white">Persönliche Daten bearbeiten</h2>
              <form id="mobileProfileInlineForm" class="mt-3 space-y-3">
                <div class="grid grid-cols-2 gap-2">
                  <label class="block text-xs text-gray-600 dark:text-gray-300">
                    Vorname
                    <input type="text" name="vorname" value="<?php echo htmlspecialchars($user['vorname'] ?? ''); ?>" class="mt-1 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900/30 dark:text-white">
                  </label>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">
                    Nachname
                    <input type="text" name="nachname" value="<?php echo htmlspecialchars($user['nachname'] ?? ''); ?>" class="mt-1 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900/30 dark:text-white">
                  </label>
                </div>
                <label class="block text-xs text-gray-600 dark:text-gray-300">
                  E-Mail
                  <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="mt-1 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900/30 dark:text-white">
                </label>
                <label class="block text-xs text-gray-600 dark:text-gray-300">
                  Telefon (Festnetz)
                  <input type="tel" name="telefonnummer" value="<?php echo htmlspecialchars($user['telefonnummer'] ?? ''); ?>" class="mt-1 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900/30 dark:text-white">
                </label>
                <?php
                $inputClass = 'mt-1 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900/30 dark:text-white';
                $labelClass = 'mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300';
                include __DIR__ . '/partials/profile-stammdaten-fields.php';
                ?>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-800 dark:bg-primary-600 dark:hover:bg-primary-700">
                  Profil speichern
                </button>
              </form>
              <div class="mt-4 space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                <div class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-3 text-sm dark:border-gray-600">
                  <span class="font-medium text-gray-900 dark:text-white">2FA Sicherheit</span>
                  <span class="rounded-full px-2 py-0.5 text-xs <?php echo $twoFaEnabled ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'; ?>">
                    <?php echo $twoFaEnabled ? 'Aktiv' : 'Inaktiv'; ?>
                  </span>
                </div>
                <div class="rounded-xl border border-gray-200 px-3 py-3 text-sm dark:border-gray-600">
                  <p class="font-medium text-gray-900 dark:text-white">Mobile Startseite</p>
                  <div class="mt-2 inline-flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                    <button type="button" id="profileMobileStartFixedBtn" data-mobile-start-mode="fixed" class="border-r border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">Feste Seite</button>
                    <button type="button" id="profileMobileStartLastBtn" data-mobile-start-mode="last" class="bg-white px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Letzte Seite</button>
                  </div>
                  <select id="profileMobileStartPageSelect" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></select>
                </div>
                <div class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-3 text-sm dark:border-gray-600">
                  <div>
                    <p class="font-medium text-gray-900 dark:text-white">Umfrage-E-Mails</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Produktverbesserungen per Mail</p>
                  </div>
                  <button type="button" id="profileMarketingToggleBtn" class="inline-flex rounded-lg px-3 py-1.5 text-xs font-medium <?php echo $marketingEmailsEnabled ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'; ?>">
                    <?php echo $marketingEmailsEnabled ? 'Aktiv' : 'Inaktiv'; ?>
                  </button>
                </div>
              </div>
            </section>

          </div>
        </div>

        <!-- Content Area with Sidebar -->
        <div class="col-span-full hidden lg:block lg:min-h-0">
          <div class="lg:grid lg:grid-cols-12 lg:gap-4 lg:items-start">
            <!-- Left Sidebar -->
            <aside class="hidden rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 mb-4 lg:col-span-3 lg:mb-0 lg:block lg:self-start">
              <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
                <div class="flex items-center gap-3">
                  <a href="<?php echo BASE_URL; ?>account/index.php" class="shrink-0 rounded-full focus:outline-none">
                    <?php if ($avatarIsPreset): ?>
                      <div class="flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 text-sm font-semibold text-white dark:border-gray-600" style="background-color: <?php echo htmlspecialchars($avatarPresetColor); ?>;">
                        <?php echo htmlspecialchars($avatarPresetInitials); ?>
                      </div>
                    <?php elseif ($avatarImagePath): ?>
                      <img src="<?php echo htmlspecialchars(getUserAvatarUrl($avatarImagePath)); ?>" alt="Profilbild" class="h-12 w-12 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                    <?php else: ?>
                      <div class="flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-gray-500 text-sm font-semibold text-white dark:border-gray-600">
                        <?php echo htmlspecialchars($initials); ?>
                      </div>
                    <?php endif; ?>
                  </a>
                  <a href="<?php echo BASE_URL; ?>account/index.php" class="min-w-0 block w-full rounded-md p-1 -m-1 focus:outline-none">
                    <p class="truncate text-base font-semibold text-gray-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-300"><?php echo htmlspecialchars($fullName); ?></p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['rolle'] ?? 'Benutzer'); ?></p>
                  </a>
                </div>
              </div>

              <div class="mb-4 grid grid-cols-1 gap-1.5 border-b border-gray-200 pb-4 dark:border-gray-700">
                <a href="<?php echo BASE_URL; ?>account/index.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 px-4 py-3 text-left text-base font-semibold !border-primary-700 !bg-primary-100 !text-primary-800 hover:!bg-primary-100 hover:!text-primary-800 dark:!border-primary-300 dark:!bg-primary-800/40 dark:!text-primary-200 dark:hover:!bg-primary-800/40 dark:hover:!text-primary-200" style="background-color:#ede9fe !important;color:#5b21b6 !important;border-left-color:#7e22ce !important;">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z" />
                  </svg>
                  Persönliche Daten
                </a>
                <a href="<?php echo BASE_URL; ?>account/my-company.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/>
                  </svg>
                  Meine Firma
                </a>
                <a href="<?php echo BASE_URL; ?>notifications/" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                  <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
                  </svg>
                  Benachrichtigungen
                </a>
              </div>

              <ul class="-mb-px grid grid-cols-1 gap-1.5 text-base font-semibold" data-tabs-active-classes="!bg-primary-100 !text-primary-800 dark:!bg-primary-800/40 dark:!text-primary-200" data-tabs-inactive-classes="!bg-transparent !text-gray-700 hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                <li>
                  <a href="<?php echo BASE_URL; ?>settings/index.php#praeferenzen" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                    <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M20 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6h-2m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4"/>
                    </svg>
                    Präferenzen
                  </a>
                </li>
                <li>
                  <a href="<?php echo BASE_URL; ?>settings/index.php#benachrichtigungen" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                    <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z" />
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                    </svg>
                    Benachrichtigungen
                  </a>
                </li>
                <li>
                  <a href="<?php echo BASE_URL; ?>settings/index.php#sicherheit" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                    <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    Sicherheit
                  </a>
                </li>
                <li>
                  <button type="button" id="account-reset-all-settings-btn" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
                    <span id="account-reset-all-settings-spinner" class="me-2 hidden" role="status" aria-hidden="true">
                      <svg aria-hidden="true" class="h-4 w-4 animate-spin text-gray-300 fill-gray-700 dark:text-gray-600 dark:fill-gray-300" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                      </svg>
                    </span>
                    <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 9H8a5 5 0 0 0 0 10h9m4-10-4-4m4 4-4 4"/>
                    </svg>
                    <span id="account-reset-all-settings-label">Einstellungen zurücksetzen</span>
                  </button>
                </li>
              </ul>
              <ul class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                <li>
                  <a href="<?php echo BASE_URL; ?>logout.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left font-semibold text-red-700 transition-colors hover:bg-red-50 hover:text-red-800 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300">
                    <svg class="me-2 h-5 w-5 text-red-700 dark:text-red-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2" />
                    </svg>
                    Abmelden
                  </a>
                </li>
              </ul>
            </aside>
            
            <!-- Right Content -->
            <div class="w-full lg:col-span-9 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto lg:pr-1">
       
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="sticky top-0 z-10 -mx-6 mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white/95 px-6 py-3 backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
            <h3 class="text-xl font-semibold leading-none text-gray-900 dark:text-white">Persönliche Daten</h3>
            <button type="submit" form="desktopProfileForm" class="inline-flex items-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 dark:bg-primary-600 dark:hover:bg-primary-700">
              Änderungen speichern
            </button>
          </div>
          <form id="desktopProfileForm" class="space-y-6">
            <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 pb-6 dark:border-gray-700">
              <?php if ($avatarIsPreset): ?>
                <div class="flex h-16 w-16 items-center justify-center rounded-lg text-sm font-semibold text-white" style="background-color: <?php echo htmlspecialchars($avatarPresetColor); ?>;">
                  <?php echo htmlspecialchars($avatarPresetInitials); ?>
                </div>
              <?php elseif ($avatarImagePath): ?>
                <img class="h-16 w-16 rounded-lg object-cover" src="<?php echo htmlspecialchars(getUserAvatarUrl($avatarImagePath)); ?>" alt="Profilbild" />
              <?php else: ?>
                <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-gray-500 text-sm font-semibold text-white">
                  <?php echo htmlspecialchars($initials); ?>
                </div>
              <?php endif; ?>
              <div>
                <span class="me-2 rounded bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900 dark:text-primary-300"><?php echo htmlspecialchars($rolleBadgeText); ?></span>
                <p class="text-sm text-gray-500 dark:text-gray-400">Profilbild ändern: oben auf dem Smartphone per Tipp auf das Bild, am Desktop über die App-Einstellungen oder beim nächsten Login.</p>
              </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="desktop_vorname" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Vorname</label>
                <input type="text" id="desktop_vorname" name="vorname" value="<?php echo htmlspecialchars($user['vorname'] ?? ''); ?>" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
              </div>
              <div>
                <label for="desktop_nachname" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Nachname</label>
                <input type="text" id="desktop_nachname" name="nachname" value="<?php echo htmlspecialchars($user['nachname'] ?? ''); ?>" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
              </div>
              <div>
                <label for="desktop_email" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">E-Mail</label>
                <input type="email" id="desktop_email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
              </div>
              <div>
                <label for="desktop_telefonnummer" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Telefon (Festnetz)</label>
                <input type="tel" id="desktop_telefonnummer" name="telefonnummer" value="<?php echo htmlspecialchars($user['telefonnummer'] ?? ''); ?>" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
              </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-5 dark:border-gray-600 dark:bg-gray-900/30">
              <h4 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Kontakt &amp; Erreichbarkeit</h4>
              <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Zusätzliche Angaben für Support und Kommunikation.</p>
              <?php include __DIR__ . '/partials/profile-stammdaten-fields.php'; ?>
            </div>
            <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
              <button type="submit" class="inline-flex items-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 dark:bg-primary-600 dark:hover:bg-primary-700">
                Änderungen speichern
              </button>
            </div>
          </form>
          <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            <a href="<?php echo BASE_URL; ?>settings/profil-einstellungen.php" class="font-medium text-primary-600 hover:underline dark:text-primary-400">Alle gespeicherten Kontodaten anzeigen</a>
            (technische Übersicht)
          </p>
        </div>
        <div class="col-span-full rounded-lg py-6">
         
        
    
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <?php if (!$twoFaEnabled): ?>
        <div class="rounded-lg border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <svg class="w-auto max-w-[16rem] center mx-auto h-40 text-gray-800 dark:text-white" aria-hidden="true" width="524" height="540" viewBox="0 0 524 540" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M524 278C524 422.699 406.699 540 262 540C117.301 540 0 422.699 0 278C0 133.301 117.301 16 262 16C406.699 16 524 133.301 524 278Z" fill="url(#paint0_linear_383_573)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M519.795 325C497.649 447.269 390.653 540 261.999 540C133.345 540 26.349 447.269 4.20312 325H519.795Z" fill="url(#paint1_linear_383_573)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M351 81V240C351 252.15 341.15 262 329 262H211C198.85 262 189 252.15 189 240V81C189 36.2649 225.265 0 270 0C314.735 0 351 36.2649 351 81ZM270 18C235.206 18 207 46.2061 207 81V240C207 242.209 208.791 244 211 244H329C331.209 244 333 242.209 333 240V81C333 46.2061 304.794 18 270 18Z" fill="#374151"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M351 81V240C351 252.15 341.15 262 329 262H211C198.85 262 189 252.15 189 240V81C189 36.2649 225.265 0 270 0C314.735 0 351 36.2649 351 81ZM270 18C235.206 18 207 46.2061 207 81V240C207 242.209 208.791 244 211 244H329C331.209 244 333 242.209 333 240V81C333 46.2061 304.794 18 270 18Z" fill="url(#paint2_linear_383_573)" fill-opacity="0.7"/>
<path d="M195 165C195 162.791 193.209 161 191 161H140C137.791 161 136 162.791 136 165V377C136 379.209 137.791 381 140 381H191C193.209 381 195 379.209 195 377V165Z" fill="#111928"/>
<path d="M174 164C174 162.343 175.343 161 177 161H385C386.657 161 388 162.343 388 164V378C388 379.657 386.657 381 385 381H177C175.343 381 174 379.657 174 378V164Z" fill="#374151"/>
<path d="M174 164C174 162.343 175.343 161 177 161H385C386.657 161 388 162.343 388 164V378C388 379.657 386.657 381 385 381H177C175.343 381 174 379.657 174 378V164Z" fill="url(#paint3_linear_383_573)" fill-opacity="0.7"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M295.245 261.045C301.137 256.988 305 250.195 305 242.5C305 230.074 294.926 220 282.5 220C270.074 220 260 230.074 260 242.5C260 250.195 263.863 256.988 269.755 261.045L263.251 318.776C263.117 319.962 264.045 321 265.238 321H299.762C300.955 321 301.883 319.962 301.749 318.776L295.245 261.045Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2216 195.28C24.5045 171.843 48.6065 157.935 72.0548 164.215C95.5031 170.495 109.418 194.585 103.135 218.022C96.8525 241.459 72.7505 255.367 49.3022 249.087C25.8539 242.808 11.9386 218.717 18.2216 195.28ZM75.3443 151.944C45.1158 143.848 14.0446 161.778 5.94486 191.992C-2.15486 222.206 15.7841 253.262 46.0127 261.358C73.9906 268.851 102.69 254.049 113.231 227.848L200.083 251.109L190.626 286.388C190.323 287.517 190.994 288.678 192.124 288.981L200.308 291.173C201.438 291.475 202.6 290.805 202.903 289.675L212.36 254.397L229.496 258.986L223.603 280.972C223.3 282.101 223.97 283.262 225.101 283.565L233.285 285.757C234.415 286.059 235.577 285.389 235.879 284.26L241.773 262.274L258.909 266.864L249.452 302.142C249.149 303.272 249.82 304.433 250.95 304.735L259.134 306.927C260.264 307.23 261.426 306.56 261.729 305.43L273.927 259.926C274.23 258.797 273.559 257.636 272.429 257.333L116.634 215.608C121.208 187.282 103.673 159.531 75.3443 151.944Z" fill="#6B7280"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2216 195.28C24.5045 171.843 48.6065 157.935 72.0548 164.215C95.5031 170.495 109.418 194.585 103.135 218.022C96.8525 241.459 72.7505 255.367 49.3022 249.087C25.8539 242.808 11.9386 218.717 18.2216 195.28ZM75.3443 151.944C45.1158 143.848 14.0446 161.778 5.94486 191.992C-2.15486 222.206 15.7841 253.262 46.0127 261.358C73.9906 268.851 102.69 254.049 113.231 227.848L200.083 251.109L190.626 286.388C190.323 287.517 190.994 288.678 192.124 288.981L200.308 291.173C201.438 291.475 202.6 290.805 202.903 289.675L212.36 254.397L229.496 258.986L223.603 280.972C223.3 282.101 223.97 283.262 225.101 283.565L233.285 285.757C234.415 286.059 235.577 285.389 235.879 284.26L241.773 262.274L258.909 266.864L249.452 302.142C249.149 303.272 249.82 304.433 250.95 304.735L259.134 306.927C260.264 307.23 261.426 306.56 261.729 305.43L273.927 259.926C274.23 258.797 273.559 257.636 272.429 257.333L116.634 215.608C121.208 187.282 103.673 159.531 75.3443 151.944Z" fill="url(#paint4_linear_383_573)"/>
<path d="M136 399C136 397.895 136.895 397 138 397H174.444C175.549 397 176.444 397.895 176.444 399V435.444C176.444 436.549 175.549 437.444 174.444 437.444H138C136.895 437.444 136 436.549 136 435.444V399Z" fill="#c8d8fa"/>
<path d="M136 399C136 397.895 136.895 397 138 397H174.444C175.549 397 176.444 397.895 176.444 399V435.444C176.444 436.549 175.549 437.444 174.444 437.444H138C136.895 437.444 136 436.549 136 435.444V399Z" fill="url(#paint5_linear_383_573)"/>
<path d="M154.486 428.111L154.911 420.455L148.626 424.671L146.371 420.663L153.081 417.222L146.371 413.782L148.626 409.774L154.911 413.99L154.486 406.333H158.978L158.572 413.99L164.857 409.774L167.112 413.782L160.383 417.222L167.112 420.663L164.857 424.671L158.572 420.455L158.978 428.111H154.486Z" fill="#F9FAFB"/>
<path d="M189 399C189 397.895 189.895 397 191 397H227.444C228.549 397 229.444 397.895 229.444 399V435.444C229.444 436.549 228.549 437.444 227.444 437.444H191C189.895 437.444 189 436.549 189 435.444V399Z" fill="#c8d8fa"/>
<path d="M189 399C189 397.895 189.895 397 191 397H227.444C228.549 397 229.444 397.895 229.444 399V435.444C229.444 436.549 228.549 437.444 227.444 437.444H191C189.895 437.444 189 436.549 189 435.444V399Z" fill="url(#paint6_linear_383_573)"/>
<path d="M207.373 428.111L207.798 420.455L201.513 424.671L199.258 420.663L205.968 417.222L199.258 413.782L201.513 409.774L207.798 413.99L207.373 406.333H211.865L211.458 413.99L217.743 409.774L219.999 413.782L213.27 417.222L219.999 420.663L217.743 424.671L211.458 420.455L211.865 428.111H207.373Z" fill="#F9FAFB"/>
<path d="M242 399C242 397.895 242.895 397 244 397H280.444C281.549 397 282.444 397.895 282.444 399V435.444C282.444 436.549 281.549 437.444 280.444 437.444H244C242.895 437.444 242 436.549 242 435.444V399Z" fill="#c8d8fa"/>
<path d="M242 399C242 397.895 242.895 397 244 397H280.444C281.549 397 282.444 397.895 282.444 399V435.444C282.444 436.549 281.549 437.444 280.444 437.444H244C242.895 437.444 242 436.549 242 435.444V399Z" fill="url(#paint7_linear_383_573)"/>
<path d="M260.264 428.111L260.689 420.455L254.404 424.671L252.148 420.663L258.859 417.222L252.148 413.782L254.404 409.774L260.689 413.99L260.264 406.333H264.756L264.349 413.99L270.634 409.774L272.889 413.782L266.16 417.222L272.889 420.663L270.634 424.671L264.349 420.455L264.756 428.111H260.264Z" fill="#F9FAFB"/>
<path d="M295.002 399C295.002 397.895 295.897 397 297.002 397H333.446C334.551 397 335.446 397.895 335.446 399V435.444C335.446 436.549 334.551 437.444 333.446 437.444H297.002C295.897 437.444 295.002 436.549 295.002 435.444V399Z" fill="#c8d8fa"/>
<path d="M295.002 399C295.002 397.895 295.897 397 297.002 397H333.446C334.551 397 335.446 397.895 335.446 399V435.444C335.446 436.549 334.551 437.444 333.446 437.444H297.002C295.897 437.444 295.002 436.549 295.002 435.444V399Z" fill="url(#paint8_linear_383_573)"/>
<path d="M313.152 428.111L313.577 420.455L307.292 424.671L305.037 420.663L311.747 417.222L305.037 413.782L307.292 409.774L313.577 413.99L313.152 406.333H317.644L317.238 413.99L323.523 409.774L325.778 413.782L319.049 417.222L325.778 420.663L323.523 424.671L317.238 420.455L317.644 428.111H313.152Z" fill="#F9FAFB"/>
<path d="M348.002 399C348.002 397.895 348.897 397 350.002 397H386.446C387.551 397 388.446 397.895 388.446 399V435.444C388.446 436.549 387.551 437.444 386.446 437.444H350.002C348.897 437.444 348.002 436.549 348.002 435.444V399Z" fill="#c8d8fa"/>
<path d="M348.002 399C348.002 397.895 348.897 397 350.002 397H386.446C387.551 397 388.446 397.895 388.446 399V435.444C388.446 436.549 387.551 437.444 386.446 437.444H350.002C348.897 437.444 348.002 436.549 348.002 435.444V399Z" fill="url(#paint9_linear_383_573)"/>
<path d="M366.043 428.111L366.468 420.455L360.183 424.671L357.928 420.663L364.638 417.222L357.928 413.782L360.183 409.774L366.468 413.99L366.043 406.333H370.535L370.128 413.99L376.413 409.774L378.668 413.782L371.94 417.222L378.668 420.663L376.413 424.671L370.128 420.455L370.535 428.111H366.043Z" fill="#F9FAFB"/>
<defs>
<linearGradient id="paint0_linear_383_573" x1="262" y1="16" x2="262" y2="540" gradientUnits="userSpaceOnUse">
<stop stop-color="#1F2A37"/>
<stop offset="1" stop-color="#1F2A37" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint1_linear_383_573" x1="261.999" y1="325" x2="261.999" y2="499.549" gradientUnits="userSpaceOnUse">
<stop stop-color="#2F3948"/>
<stop offset="1" stop-color="#2F3948" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint2_linear_383_573" x1="270" y1="165.5" x2="270.072" y2="69.9682" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint3_linear_383_573" x1="274.434" y1="381" x2="235.605" y2="243.462" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint4_linear_383_573" x1="214" y1="247.5" x2="83.5" y2="41" gradientUnits="userSpaceOnUse">
<stop stop-color="#F9FAFB" stop-opacity="0"/>
<stop offset="1" stop-color="#F9FAFB"/>
</linearGradient>
<linearGradient id="paint5_linear_383_573" x1="156.222" y1="408.886" x2="156.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint6_linear_383_573" x1="209.222" y1="408.886" x2="209.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint7_linear_383_573" x1="262.222" y1="408.886" x2="262.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint8_linear_383_573" x1="315.224" y1="408.886" x2="315.224" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint9_linear_383_573" x1="368.224" y1="408.886" x2="368.224" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
</defs>
</svg><h3 class="mb-2 mt-4 text-xl font-semibold leading-none text-gray-900 dark:text-white">Erhöhe die Sicherheit deines Kontos</h3>
          <p class="mb-4 text-gray-500 dark:text-gray-400">Wir erhöhen die Sicherheit deines Kontos, indem wir zwei-faktorische Authentifizierung aktivieren</p>
          
            <button type="button"  class="inline-flex w-full items-center justify-center rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-300 dark:bg-green-700 dark:hover:bg-blue-800 dark:focus:ring-green-800 sm:w-auto">
         
              2FA aktivieren
            </button>
        </div>
        <?php endif; ?>

        <div class="rounded-lg border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <svg class="w-auto max-w-[16rem] center mx-auto h-40 text-gray-800 dark:text-white" aria-hidden="true" width="556" height="421" viewBox="0 0 556 421" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M517 189C517 293.382 432.382 378 328 378C223.618 378 139 293.382 139 189C139 84.6182 223.618 0 328 0C432.382 0 517 84.6182 517 189Z" fill="url(#paint0_linear_344_2578)"/>
<path d="M26.8808 402L72.986 400.491L62.1079 375.878C52.8414 373.828 47.462 371.977 41.3808 358.879C34.8808 344.879 32.4995 342 27.9995 340L26.8808 402Z" fill="#374151"/>
<path d="M28 340L28 402L22.2481 402.719C21.0544 402.868 20 401.937 20 400.734L20 342C20 340.895 20.8954 340 22 340L28 340Z" fill="#9ab7f6"/>
<path d="M213.135 331.449L61.8143 358.18C60.7892 358.361 60.0744 359.298 60.1708 360.334L63.8089 399.444C63.9135 400.569 64.9283 401.384 66.0491 401.243L227.5 381L374 402.5L295 333L241.056 329.827C231.717 329.278 222.347 329.822 213.135 331.449Z" fill="#2563eb"/>
<path d="M213.135 331.449L61.8143 358.18C60.7892 358.361 60.0744 359.298 60.1708 360.334L63.8089 399.444C63.9135 400.569 64.9283 401.384 66.0491 401.243L227.5 381L374 402.5L295 333L241.056 329.827C231.717 329.278 222.347 329.822 213.135 331.449Z" fill="url(#paint1_linear_344_2578)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M76.3644 399.95L72.4611 356.299L70.4844 356.648L74.3787 400.199L76.3644 399.95Z" fill="#c8d8fa"/>
<path d="M319 215L370.5 175.5L361.5 238.5L320.5 255L319 215Z" fill="#F9FAFB"/>
<path d="M319 215L370.5 175.5L361.5 238.5L320.5 255L319 215Z" fill="url(#paint2_linear_344_2578)"/>
<path d="M357.383 101.209C358.047 94.6136 363.992 81.4668 383.598 83.0129C386.732 83.6479 394 88.1762 398 101.209C403 117.5 420 108 426.5 122.5C433 137 418.5 144 422.5 153.5C426.5 163 427.5 173 424 178C420.333 171.833 410.6 156.2 397 151L380 132L359 105.5L357.383 101.209Z" fill="#111928"/>
<path d="M407.79 80.3947C400.683 89.3211 388.671 91.5807 380.96 85.4416C373.25 79.3025 372.76 67.0894 379.868 58.163C386.975 49.2366 398.987 46.977 406.698 53.1161C414.408 59.2552 414.897 71.4682 407.79 80.3947Z" fill="#111928"/>
<path d="M381.4 161.329L373.235 138.538L383.314 125.911L397.446 151.154C394.774 155.78 385.16 160.02 381.4 161.329Z" fill="#FDBA8C"/>
<path d="M381.4 161.329L373.235 138.538L383.314 125.911L397.446 151.154C394.774 155.78 385.16 160.02 381.4 161.329Z" fill="url(#paint3_linear_344_2578)"/>
<path d="M389.135 112.782C392.356 114.701 387.023 140.27 370.538 140.776C364.841 140.95 361.404 136.082 359.426 129.418C359.279 128.921 358.903 129.604 358.772 129.089C358.644 128.587 358.76 126.888 358.646 126.371C356.82 118.056 356.822 107.946 357.382 101.209C360.11 102.408 366.238 106.251 368.929 112.028C372.293 119.249 378.414 120.767 380.914 117.466C383.606 113.91 385.913 110.862 389.135 112.782Z" fill="#FDBA8C"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M358.301 118.572C358.675 118.576 358.975 118.883 358.971 119.257L358.867 128.576C358.865 128.742 359.017 128.868 359.18 128.836L363.095 128.051C363.462 127.978 363.819 128.215 363.892 128.582C363.966 128.949 363.728 129.306 363.361 129.379L359.446 130.164C358.439 130.366 357.501 129.589 357.512 128.561L357.616 119.242C357.62 118.868 357.927 118.568 358.301 118.572Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M365.249 133.273C366.053 132.961 366.864 132.49 368.092 131.736C368.411 131.54 368.828 131.64 369.023 131.959C369.219 132.278 369.119 132.695 368.801 132.891C367.583 133.638 366.674 134.173 365.74 134.536C364.788 134.906 363.837 135.087 362.533 135.178C362.16 135.204 361.837 134.922 361.811 134.549C361.785 134.176 362.066 133.852 362.439 133.826C363.656 133.742 364.462 133.579 365.249 133.273Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M386.616 119.564C386.874 118.602 386.688 117.709 386.325 116.813C386.184 116.466 386.352 116.071 386.698 115.931C387.045 115.79 387.44 115.957 387.581 116.304C388 117.34 388.289 118.554 387.925 119.914C387.563 121.267 386.584 122.65 384.712 124.101C384.417 124.33 383.991 124.276 383.762 123.981C383.533 123.685 383.587 123.259 383.882 123.03C385.616 121.687 386.357 120.533 386.616 119.564Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M366.941 125.1C367.301 123.712 367.361 121.77 366.959 119.631C366.557 117.491 365.795 115.704 364.955 114.542C364.039 113.274 363.387 113.18 363.208 113.213C363.029 113.247 362.456 113.572 362.063 115.086C361.703 116.473 361.643 118.415 362.045 120.555C362.447 122.694 363.209 124.482 364.049 125.644C364.965 126.911 365.616 127.006 365.796 126.972C365.975 126.939 366.548 126.614 366.941 125.1ZM366.073 128.446C368.244 128.038 369.301 123.967 368.433 119.354C367.565 114.74 365.102 111.331 362.931 111.739C360.76 112.148 359.703 116.219 360.571 120.832C361.438 125.446 363.902 128.855 366.073 128.446Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M356.941 125.1C357.301 123.712 357.361 121.77 356.959 119.631C356.557 117.491 355.795 115.704 354.955 114.542C354.039 113.274 353.387 113.18 353.208 113.213C353.029 113.247 352.456 113.572 352.063 115.086C351.703 116.473 351.643 118.415 352.045 120.555C352.447 122.694 353.209 124.482 354.049 125.644C354.965 126.911 355.616 127.006 355.796 126.972C355.975 126.939 356.548 126.614 356.941 125.1ZM356.073 128.446C358.244 128.038 359.301 123.967 358.433 119.354C357.565 114.74 355.102 111.331 352.931 111.739C350.76 112.148 349.703 116.219 350.571 120.832C351.438 125.446 353.902 128.855 356.073 128.446Z" fill="#111928"/>
<path d="M180 396L202 359L173.5 359C167.091 366 157.476 380.7 143.31 383.5C129.143 386.3 119.867 393 117 396H180Z" fill="#374151"/>
<path d="M117 396H180L176.576 401.992C176.22 402.615 175.557 403 174.839 403H116.446C114.911 403 113.948 401.341 114.71 400.008L117 396Z" fill="#9ab7f6"/>
<path d="M377.5 403C426.3 402.6 428.167 335 423 301.5L371.5 294.5C348.834 279.667 299.7 247.3 284.5 236.5C265.5 223 246.5 222 229.5 240.5C216.294 254.871 170.903 323.523 147.989 358.924C147.128 360.254 148.088 362 149.672 362H205.408C206.089 362 206.723 361.654 207.091 361.081L255 286.5C275.5 325.333 328.7 403.4 377.5 403Z" fill="#2563eb"/>
<path d="M377.5 403C426.3 402.6 428.167 335 423 301.5L371.5 294.5C348.834 279.667 299.7 247.3 284.5 236.5C265.5 223 246.5 222 229.5 240.5C216.294 254.871 170.903 323.523 147.989 358.924C147.128 360.254 148.088 362 149.672 362H205.408C206.089 362 206.723 361.654 207.091 361.081L255 286.5C275.5 325.333 328.7 403.4 377.5 403Z" fill="url(#paint4_linear_344_2578)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M214.209 350H153.784C153.345 350.674 152.911 351.341 152.482 352H212.925L214.209 350Z" fill="#c8d8fa"/>
<path d="M428.5 304.5L403 303L426.5 251.5L428.5 304.5Z" fill="#F9FAFB"/>
<path d="M428.5 304.5L403 303L426.5 251.5L428.5 304.5Z" fill="url(#paint5_linear_344_2578)"/>
<path d="M394.999 312.5C381.629 315.842 366.938 310.536 359.969 306.825C359.107 306.366 358.757 305.333 359.109 304.423L364.499 290.5L355.499 284C354.666 262.833 353.499 219.8 355.499 203C357.499 186.2 372.665 170 379.999 164L403.499 154C411.165 155 427.299 167.2 430.499 208C434.499 259 412.999 308 394.999 312.5Z" fill="#F9FAFB"/>
<path d="M374.986 270.317L373 268L354.5 254L365.5 219.5L391 239L376.653 270.085C376.348 270.747 375.46 270.87 374.986 270.317Z" fill="url(#paint6_linear_344_2578)"/>
<path d="M379.998 165.5C379.598 162.7 380.498 160.667 380.998 160C388.198 158.8 394.998 153.5 397.498 151C400.298 151.8 402.665 153.333 403.498 154C402.998 155 400.598 157.8 394.998 161C389.398 164.2 382.665 165.333 379.998 165.5Z" fill="#F9FAFB"/>
<path d="M379.998 165.5C379.598 162.7 380.498 160.667 380.998 160C388.198 158.8 394.998 153.5 397.498 151C400.298 151.8 402.665 153.333 403.498 154C402.998 155 400.598 157.8 394.998 161C389.398 164.2 382.665 165.333 379.998 165.5Z" fill="url(#paint7_linear_344_2578)"/>
<path d="M333.5 156.443C330 125 292.667 125.499 284 125C274.4 125 268.667 129.658 267 131.987C281.5 138.975 287.5 158.939 281 193.876C274.5 228.813 281 283.215 314 321.646C347 360.077 432 338.616 453.5 366.066C469.341 386.292 488.943 397.91 498.712 402.088C500.217 402.732 501.837 403 503.473 403H556C533.5 403 510 373.553 493 357.582C476 341.61 431.5 346.102 404 341.61C376.5 337.118 349 308.67 336 271.237C323 233.804 336.85 186.54 333.5 156.443Z" fill="#c8d8fa"/>
<path d="M333.5 156.443C330 125 292.667 125.499 284 125C274.4 125 268.667 129.658 267 131.987C281.5 138.975 287.5 158.939 281 193.876C274.5 228.813 281 283.215 314 321.646C347 360.077 432 338.616 453.5 366.066C469.341 386.292 488.943 397.91 498.712 402.088C500.217 402.732 501.837 403 503.473 403H556C533.5 403 510 373.553 493 357.582C476 341.61 431.5 346.102 404 341.61C376.5 337.118 349 308.67 336 271.237C323 233.804 336.85 186.54 333.5 156.443Z" fill="url(#paint8_linear_344_2578)"/>
<path d="M333.5 156.443C330 125 292.667 125.499 284 125C274.4 125 268.667 129.658 267 131.987C281.5 138.975 287.5 158.939 281 193.876C274.5 228.813 281 283.215 314 321.646C347 360.077 432 338.616 453.5 366.066C469.341 386.292 488.943 397.91 498.712 402.088C500.217 402.732 501.837 403 503.473 403H556C533.5 403 510 373.553 493 357.582C476 341.61 431.5 346.102 404 341.61C376.5 337.118 349 308.67 336 271.237C323 233.804 336.85 186.54 333.5 156.443Z" fill="url(#paint9_linear_344_2578)"/>
<path d="M308.5 199C311 212 336 240 364.5 291L373 268C360.667 251.666 335.3 217.6 332.5 212C329.99 206.981 326.758 201.319 324.674 198.398C324.286 197.854 323.575 197.669 322.955 197.918C322.159 198.236 321.784 199.15 322.128 199.935L325 206.5C319.333 199.666 306.535 188.779 308.5 199Z" fill="#FDBA8C"/>
<path d="M308.5 199C311 212 336 240 364.5 291L373 268C360.667 251.666 335.3 217.6 332.5 212C329.99 206.981 326.758 201.319 324.674 198.398C324.286 197.854 323.575 197.669 322.955 197.918C322.159 198.236 321.784 199.15 322.128 199.935L325 206.5C319.333 199.666 306.535 188.779 308.5 199Z" fill="url(#paint10_linear_344_2578)"/>
<path d="M296.5 154C296.1 157.2 287.667 166.667 283.5 171C283 186 281 195.167 279.5 205C277.167 194.333 273.2 171.2 276 164C279.5 155 289 150.5 291.5 150C294 149.5 297 150 296.5 154Z" fill="#FDBA8C"/>
<path d="M296.5 154C296.1 157.2 287.667 166.667 283.5 171C283 186 281 195.167 279.5 205C277.167 194.333 273.2 171.2 276 164C279.5 155 289 150.5 291.5 150C294 149.5 297 150 296.5 154Z" fill="url(#paint11_linear_344_2578)"/>
<path d="M133 65C133 63.8954 133.895 63 135 63H216.012C217.117 63 218.012 63.8954 218.012 65V95.3319C218.012 96.4365 217.117 97.332 216.012 97.332H135C133.895 97.332 133 96.4365 133 95.332V65Z" fill="#374151"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M157.356 73.6349C157.775 73.9943 157.824 74.6256 157.464 75.045L148.486 85.5196C147.641 86.5056 146.095 86.4372 145.34 85.3805L141.995 80.6974C141.674 80.248 141.778 79.6235 142.227 79.3024C142.677 78.9814 143.301 79.0855 143.622 79.5349L146.967 84.218L155.946 73.7434C156.305 73.3241 156.936 73.2755 157.356 73.6349Z" fill="#6B7280"/>
<path d="M164.062 80.0001C164.062 79.4478 164.51 79.0001 165.062 79.0001H208.838C209.391 79.0001 209.838 79.4478 209.838 80.0001C209.838 80.5524 209.391 81.0001 208.838 81.0001H165.063C164.51 81.0001 164.062 80.5524 164.062 80.0001Z" fill="#6B7280"/>
<path d="M441.988 65C441.988 63.8954 442.884 63 443.988 63H525.001C526.105 63 527.001 63.8954 527.001 65V95.3319C527.001 96.4365 526.105 97.332 525.001 97.332H443.988C442.884 97.332 441.988 96.4365 441.988 95.332V65Z" fill="#374151"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M466.344 73.6349C466.763 73.9943 466.812 74.6256 466.452 75.045L457.474 85.5196C456.629 86.5056 455.083 86.4372 454.328 85.3805L450.983 80.6974C450.662 80.248 450.766 79.6235 451.216 79.3024C451.665 78.9814 452.29 79.0855 452.611 79.5349L455.956 84.218L464.934 73.7434C465.293 73.3241 465.925 73.2755 466.344 73.6349Z" fill="#6B7280"/>
<path d="M473.051 80.0001C473.051 79.4478 473.498 79.0001 474.051 79.0001H517.827C518.379 79.0001 518.827 79.4478 518.827 80.0001C518.827 80.5524 518.379 81.0001 517.827 81.0001H474.051C473.498 81.0001 473.051 80.5524 473.051 80.0001Z" fill="#6B7280"/>
<path d="M175.506 10.6073C175.506 9.71959 176.225 9 177.113 9H242.217C243.105 9 243.824 9.71959 243.824 10.6073V34.9828C243.824 35.8705 243.105 36.5901 242.217 36.5901H177.113C176.225 36.5901 175.506 35.8705 175.506 34.9828V10.6073Z" fill="#374151"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M195.207 17.4375C195.626 17.797 195.675 18.4283 195.315 18.8476L188.1 27.2653C187.338 28.1544 185.944 28.0928 185.263 27.1398L182.575 23.3764C182.254 22.927 182.358 22.3024 182.808 21.9814C183.257 21.6604 183.881 21.7645 184.202 22.2139L186.745 25.7733L193.797 17.546C194.156 17.1267 194.788 17.0781 195.207 17.4375Z" fill="#6B7280"/>
<path d="M200.469 22.9998C200.469 22.4475 200.916 21.9998 201.469 21.9998H236.255C236.808 21.9998 237.255 22.4475 237.255 22.9998C237.255 23.552 236.808 23.9998 236.255 23.9998H201.469C200.916 23.9998 200.469 23.552 200.469 22.9998Z" fill="#6B7280"/>
<path d="M240.501 223.177C216.483 339.537 148.832 390.702 116.516 402.468C115.495 402.84 114.446 403 113.359 403H76.6508C75.6336 403 75.2329 401.646 76.092 401.102C96.6434 388.075 138.312 354.13 163.001 307.328C195.501 245.717 195.001 196.629 206.001 161.566C214.801 133.515 232.667 125.501 240.501 125H284.001C255.601 125 245.256 200.135 240.501 223.177Z" fill="#d6e2fb"/>
<path d="M240.501 223.177C216.483 339.537 148.832 390.702 116.516 402.468C115.495 402.84 114.446 403 113.359 403H76.6508C75.6336 403 75.2329 401.646 76.092 401.102C96.6434 388.075 138.312 354.13 163.001 307.328C195.501 245.717 195.001 196.629 206.001 161.566C214.801 133.515 232.667 125.501 240.501 125H284.001C255.601 125 245.256 200.135 240.501 223.177Z" fill="url(#paint12_linear_344_2578)" fill-opacity="0.5"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M246.217 149.066C245.945 148.834 245.914 148.425 246.147 148.154L250.817 142.705C251.31 142.13 252.212 142.169 252.653 142.786L254.393 145.222C254.601 145.513 254.533 145.917 254.243 146.125C253.952 146.333 253.547 146.266 253.34 145.975L251.694 143.671L247.129 148.996C246.897 149.268 246.488 149.299 246.217 149.066Z" fill="#9ab7f6"/>
<path d="M242.811 145.466C242.811 145.823 242.521 146.113 242.163 146.113L219.647 146.113C219.289 146.113 218.999 145.823 218.999 145.466C218.999 145.109 219.289 144.819 219.647 144.819L242.163 144.819C242.521 144.819 242.811 145.109 242.811 145.466Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M239.217 169.066C238.945 168.834 238.914 168.425 239.147 168.154L243.817 162.705C244.31 162.13 245.212 162.169 245.653 162.786L247.393 165.222C247.601 165.513 247.533 165.917 247.243 166.125C246.952 166.333 246.547 166.266 246.34 165.975L244.694 163.671L240.129 168.996C239.897 169.268 239.488 169.299 239.217 169.066Z" fill="#9ab7f6"/>
<path d="M235.811 165.466C235.811 165.823 235.521 166.113 235.163 166.113L212.647 166.113C212.289 166.113 211.999 165.823 211.999 165.466C211.999 165.109 212.289 164.819 212.647 164.819L235.163 164.819C235.521 164.819 235.811 165.109 235.811 165.466Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M234.217 189.066C233.945 188.834 233.914 188.425 234.147 188.154L238.817 182.705C239.31 182.13 240.212 182.169 240.653 182.786L242.393 185.222C242.601 185.513 242.533 185.917 242.243 186.125C241.952 186.333 241.547 186.266 241.34 185.975L239.694 183.671L235.129 188.996C234.897 189.268 234.488 189.299 234.217 189.066Z" fill="#9ab7f6"/>
<path d="M230.811 185.466C230.811 185.823 230.521 186.113 230.163 186.113L207.647 186.113C207.289 186.113 206.999 185.823 206.999 185.466C206.999 185.109 207.289 184.819 207.647 184.819L230.163 184.819C230.521 184.819 230.811 185.109 230.811 185.466Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M226.217 229.066C225.945 228.834 225.914 228.425 226.147 228.154L230.817 222.705C231.31 222.13 232.212 222.169 232.653 222.786L234.393 225.222C234.601 225.513 234.533 225.917 234.243 226.125C233.952 226.333 233.547 226.266 233.34 225.975L231.694 223.671L227.129 228.996C226.897 229.268 226.488 229.299 226.217 229.066Z" fill="#9ab7f6"/>
<path d="M222.811 225.466C222.811 225.823 222.521 226.113 222.163 226.113L199.647 226.113C199.289 226.113 198.999 225.823 198.999 225.466C198.999 225.109 199.289 224.819 199.647 224.819L222.163 224.819C222.521 224.819 222.811 225.109 222.811 225.466Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M222.217 249.066C221.945 248.834 221.914 248.425 222.147 248.154L226.817 242.705C227.31 242.13 228.212 242.169 228.653 242.786L230.393 245.222C230.601 245.513 230.533 245.917 230.243 246.125C229.952 246.333 229.547 246.266 229.34 245.975L227.694 243.671L223.129 248.996C222.897 249.268 222.488 249.299 222.217 249.066Z" fill="#9ab7f6"/>
<path d="M218.811 245.466C218.811 245.823 218.521 246.113 218.163 246.113L195.647 246.113C195.289 246.113 194.999 245.823 194.999 245.466C194.999 245.109 195.289 244.819 195.647 244.819L218.163 244.819C218.521 244.819 218.811 245.109 218.811 245.466Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M215.217 269.066C214.945 268.834 214.914 268.425 215.147 268.154L219.817 262.705C220.31 262.13 221.212 262.169 221.653 262.786L223.393 265.222C223.601 265.513 223.533 265.917 223.243 266.125C222.952 266.333 222.547 266.266 222.34 265.975L220.694 263.671L216.129 268.996C215.897 269.268 215.488 269.299 215.217 269.066Z" fill="#9ab7f6"/>
<path d="M211.811 265.466C211.811 265.823 211.521 266.113 211.163 266.113L188.647 266.113C188.289 266.113 187.999 265.823 187.999 265.466C187.999 265.109 188.289 264.819 188.647 264.819L211.163 264.819C211.521 264.819 211.811 265.109 211.811 265.466Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M197.217 309.066C196.945 308.834 196.914 308.425 197.147 308.154L201.817 302.705C202.31 302.13 203.212 302.169 203.653 302.786L205.393 305.222C205.601 305.513 205.533 305.917 205.243 306.125C204.952 306.333 204.547 306.266 204.34 305.975L202.694 303.671L198.129 308.996C197.897 309.268 197.488 309.299 197.217 309.066Z" fill="#9ab7f6"/>
<path d="M193.811 305.466C193.811 305.823 193.521 306.113 193.163 306.113L170.647 306.113C170.289 306.113 169.999 305.823 169.999 305.466C169.999 305.109 170.289 304.819 170.647 304.819L193.163 304.819C193.521 304.819 193.811 305.109 193.811 305.466Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M187.217 329.066C186.945 328.834 186.914 328.425 187.147 328.154L191.817 322.705C192.31 322.13 193.212 322.169 193.653 322.786L195.393 325.222C195.601 325.513 195.533 325.917 195.243 326.125C194.952 326.333 194.547 326.266 194.34 325.975L192.694 323.671L188.129 328.996C187.897 329.268 187.488 329.299 187.217 329.066Z" fill="#9ab7f6"/>
<path d="M183.811 325.466C183.811 325.823 183.521 326.113 183.163 326.113L160.647 326.113C160.289 326.113 159.999 325.823 159.999 325.466C159.999 325.109 160.289 324.819 160.647 324.819L183.163 324.819C183.521 324.819 183.811 325.109 183.811 325.466Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M173.217 349.066C172.945 348.834 172.914 348.425 173.147 348.154L177.817 342.705C178.31 342.13 179.212 342.169 179.653 342.786L181.393 345.222C181.601 345.513 181.533 345.917 181.243 346.125C180.952 346.333 180.547 346.266 180.34 345.975L178.694 343.671L174.129 348.996C173.897 349.268 173.488 349.299 173.217 349.066Z" fill="#9ab7f6"/>
<path d="M169.811 345.466C169.811 345.823 169.521 346.113 169.163 346.113L146.647 346.113C146.289 346.113 145.999 345.823 145.999 345.466C145.999 345.109 146.289 344.819 146.647 344.819L169.163 344.819C169.521 344.819 169.811 345.109 169.811 345.466Z" fill="#9ab7f6"/>
<defs>
<linearGradient id="paint0_linear_344_2578" x1="328" y1="0" x2="328" y2="378" gradientUnits="userSpaceOnUse">
<stop stop-color="#1F2A37"/>
<stop offset="1" stop-color="#1F2A37" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint1_linear_344_2578" x1="598" y1="-82.4999" x2="333.419" y2="-166.998" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint2_linear_344_2578" x1="385" y1="255" x2="330.615" y2="169.856" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint3_linear_344_2578" x1="384.784" y1="118.92" x2="389.359" y2="152.011" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint4_linear_344_2578" x1="393" y1="1.49999" x2="279.816" y2="20.445" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint5_linear_344_2578" x1="421.5" y1="239" x2="448.603" y2="298.071" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint6_linear_344_2578" x1="376.5" y1="281.5" x2="369.288" y2="243.828" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint7_linear_344_2578" x1="397.847" y1="170.172" x2="396.12" y2="156.768" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint8_linear_344_2578" x1="556" y1="458.5" x2="303.5" y2="257.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#d6e2fb"/>
<stop offset="1" stop-color="#d6e2fb" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint9_linear_344_2578" x1="329.5" y1="10" x2="387.5" y2="208" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb"/>
<stop offset="1" stop-color="#2563eb" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint10_linear_344_2578" x1="458" y1="383" x2="387.5" y2="234.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint11_linear_344_2578" x1="324.824" y1="257.763" x2="266.641" y2="189.301" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint12_linear_344_2578" x1="143.501" y1="-117.5" x2="225.755" y2="243.308" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb"/>
<stop offset="1" stop-color="#2563eb" stop-opacity="0"/>
</linearGradient>
</defs>
</svg> <h3 class="mb-2 mt-4 text-xl font-semibold leading-none text-gray-900 dark:text-white">Hilfe uns, besser zu werden!</h3>
          <p class="mb-4 text-gray-500 dark:text-gray-400">In regelmäßigen Abständen, werden wir Umfragen per Mail versenden, die uns helfen, das Kundenerlebniss zu verbessern</p>
          <?php if ($marketingEmailsEnabled): ?>
            <button type="button" id="marketing-emails-btn" class="inline-flex w-full items-center justify-center rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-300 dark:bg-green-700 dark:hover:bg-green-800 dark:focus:ring-green-800 sm:w-auto">
              <svg class="-ms-0.5 me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm13.707-1.293a1 1 0 0 0-1.414-1.414L11 12.586l-1.793-1.793a1 1 0 0 0-1.414 1.414l2.5 2.5a1 1 0 0 0 1.414 0l4-4Z" clip-rule="evenodd"/>
              </svg>
              Zustimmung erteilt
            </button>
            <button type="button" id="marketing-emails-remove-btn" class="mt-2 inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-700 sm:w-auto">Zustimmung widerrufen</button>
          <?php else: ?>
            <button type="button" id="marketing-emails-btn" class="inline-flex w-full items-center justify-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 sm:w-auto">An Umfragen teilnehmen</button>
          <?php endif; ?>
        </div>
        </div>
      </div>
    </div>
  </div>

 
  </main>
</div>

<script>
(function() {
    const profileForm = document.getElementById('mobileProfileInlineForm');
    const avatarTriggerBtn = document.getElementById('mobileAvatarTriggerBtn');
    const avatarFileInput = document.getElementById('mobileAvatarFileInput');
    const profileMarketingToggleBtn = document.getElementById('profileMarketingToggleBtn');
    const profileMobileStartFixedBtn = document.getElementById('profileMobileStartFixedBtn');
    const profileMobileStartLastBtn = document.getElementById('profileMobileStartLastBtn');
    const profileMobileStartPageSelect = document.getElementById('profileMobileStartPageSelect');
    const marketingEmailsBtn = document.getElementById('marketing-emails-btn');
    const marketingEmailsRemoveBtn = document.getElementById('marketing-emails-remove-btn');
    const accountResetAllSettingsBtn = document.getElementById('account-reset-all-settings-btn');
    const accountResetAllSettingsSpinner = document.getElementById('account-reset-all-settings-spinner');
    const accountResetAllSettingsLabel = document.getElementById('account-reset-all-settings-label');

    const getApiBaseUrl = () => (typeof baseUrl !== 'undefined' ? baseUrl : '<?php echo BASE_URL; ?>');

    function notify(message, type) {
        if (typeof showToast === 'function') {
            showToast(message, type || 'success');
            return;
        }
        alert(message);
    }

    async function updateMarketingEmailsStatus(enabled, { reload = false } = {}) {
        try {
            const response = await fetch(`${getApiBaseUrl()}account/api/marketing-emails.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled })
            });
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Fehler beim Speichern');
            if (profileMarketingToggleBtn) {
                profileMarketingToggleBtn.textContent = enabled ? 'Aktiv' : 'Inaktiv';
                profileMarketingToggleBtn.className = `inline-flex rounded-lg px-3 py-1.5 text-xs font-medium ${enabled ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'}`;
            }
            if (reload) {
                window.location.reload();
                return;
            }
            notify('Einstellung gespeichert', 'success');
        } catch (error) {
            notify(error.message || 'Fehler beim Speichern der Einstellung', 'error');
        }
    }

    if (marketingEmailsBtn) {
        marketingEmailsBtn.addEventListener('click', async function() {
            if (this.textContent.trim().includes('Zustimmung erteilt')) return;
            await updateMarketingEmailsStatus(true, { reload: true });
        });
    }
    if (marketingEmailsRemoveBtn) {
        marketingEmailsRemoveBtn.addEventListener('click', async function() {
            await updateMarketingEmailsStatus(false, { reload: true });
        });
    }
    if (profileMarketingToggleBtn) {
        profileMarketingToggleBtn.addEventListener('click', async function() {
            const isActive = this.textContent.trim().toLowerCase() === 'aktiv';
            await updateMarketingEmailsStatus(!isActive, { reload: false });
        });
    }

    function getActiveProfileForm() {
        const desktop = document.getElementById('desktopProfileForm');
        const mobile = document.getElementById('mobileProfileInlineForm');
        if (window.matchMedia('(min-width: 1024px)').matches && desktop) {
            return desktop;
        }
        return mobile || desktop;
    }

    async function saveProfileForm(form) {
        const target = form || getActiveProfileForm();
        if (!target) return;
        const submitBtns = document.querySelectorAll('button[type="submit"][form="desktopProfileForm"], #mobileProfileInlineForm button[type="submit"]');
        submitBtns.forEach(function(btn) {
            btn.disabled = true;
            btn.dataset.profileSaveLabel = btn.dataset.profileSaveLabel || btn.textContent;
            btn.textContent = 'Speichere…';
        });
        try {
            const res = await fetch(`${getApiBaseUrl()}settings/api/profile.php`, {
                method: 'POST',
                body: new FormData(target)
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || data.message || 'Profil konnte nicht gespeichert werden');
            notify('Profil erfolgreich gespeichert', 'success');
            window.location.reload();
        } catch (error) {
            notify(error.message || 'Fehler beim Speichern des Profils', 'error');
        } finally {
            submitBtns.forEach(function(btn) {
                btn.disabled = false;
                if (btn.dataset.profileSaveLabel) btn.textContent = btn.dataset.profileSaveLabel;
            });
        }
    }

    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveProfileForm(profileForm);
        });
    }

    const desktopProfileForm = document.getElementById('desktopProfileForm');
    if (desktopProfileForm) {
        desktopProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveProfileForm(desktopProfileForm);
        });
    }

    if (window.NavUnsavedChanges) {
        NavUnsavedChanges.init({
            forms: ['desktopProfileForm', 'mobileProfileInlineForm'],
            getActiveForm: getActiveProfileForm,
            discardUrl: getApiBaseUrl() + 'account/',
            onSave: function() { saveProfileForm(getActiveProfileForm()); }
        });
    }

    if (avatarTriggerBtn && avatarFileInput) {
        avatarTriggerBtn.addEventListener('click', function() {
            avatarFileInput.click();
        });
        avatarFileInput.addEventListener('change', async function() {
            const file = avatarFileInput.files && avatarFileInput.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('profile_image', file);
            try {
                const res = await fetch(`${getApiBaseUrl()}settings/api/profile.php`, {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Bild konnte nicht hochgeladen werden');
                notify('Profilbild aktualisiert', 'success');
                window.location.reload();
            } catch (error) {
                notify(error.message || 'Fehler beim Upload', 'error');
            } finally {
                avatarFileInput.value = '';
            }
        });
    }

    async function loadMobileStartSettings() {
        if (!profileMobileStartPageSelect || !profileMobileStartFixedBtn || !profileMobileStartLastBtn) return;
        try {
            const res = await fetch(`${getApiBaseUrl()}settings/api/mobile-start-page.php`, { method: 'GET' });
            const data = await res.json();
            if (!data.success) return;
            profileMobileStartPageSelect.innerHTML = '';
            Object.entries(data.pages || {}).forEach(function(entry) {
                const option = document.createElement('option');
                option.value = entry[0];
                option.textContent = entry[1];
                profileMobileStartPageSelect.appendChild(option);
            });
            profileMobileStartPageSelect.value = data.page || 'tickets';
            setMobileStartModeUi(data.mode || 'fixed');
        } catch (error) {}
    }

    function setMobileStartModeUi(mode) {
        const isFixed = mode === 'fixed';
        profileMobileStartFixedBtn.classList.toggle('bg-primary-700', isFixed);
        profileMobileStartFixedBtn.classList.toggle('text-white', isFixed);
        profileMobileStartLastBtn.classList.toggle('bg-primary-700', !isFixed);
        profileMobileStartLastBtn.classList.toggle('text-white', !isFixed);
        profileMobileStartPageSelect.disabled = !isFixed;
    }

    async function saveMobileStartSettings(mode, page) {
        try {
            const res = await fetch(`${getApiBaseUrl()}settings/api/mobile-start-page.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mode, page })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Speichern fehlgeschlagen');
            setMobileStartModeUi(data.mode || mode);
            notify('Mobile Startseite gespeichert', 'success');
        } catch (error) {
            notify(error.message || 'Fehler beim Speichern der mobilen Startseite', 'error');
        }
    }

    if (profileMobileStartFixedBtn && profileMobileStartLastBtn && profileMobileStartPageSelect) {
        loadMobileStartSettings();
        profileMobileStartFixedBtn.addEventListener('click', function() {
            saveMobileStartSettings('fixed', profileMobileStartPageSelect.value || 'tickets');
        });
        profileMobileStartLastBtn.addEventListener('click', function() {
            saveMobileStartSettings('last', profileMobileStartPageSelect.value || 'tickets');
        });
        profileMobileStartPageSelect.addEventListener('change', function() {
            const mode = profileMobileStartPageSelect.disabled ? 'last' : 'fixed';
            saveMobileStartSettings(mode, profileMobileStartPageSelect.value || 'tickets');
        });
    }

    if (accountResetAllSettingsBtn) {
        accountResetAllSettingsBtn.addEventListener('click', async function() {
            if (!window.confirm('Möchtest du wirklich alle Einstellungen zurücksetzen?')) return;
            const originalLabel = accountResetAllSettingsLabel ? accountResetAllSettingsLabel.textContent : '';
            accountResetAllSettingsBtn.disabled = true;
            if (accountResetAllSettingsSpinner) accountResetAllSettingsSpinner.classList.remove('hidden');
            if (accountResetAllSettingsLabel) accountResetAllSettingsLabel.textContent = 'Wird zurückgesetzt...';
            try {
                const res = await fetch(`${getApiBaseUrl()}settings/api/reset-all.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Zurücksetzen fehlgeschlagen');
                notify('Alle Einstellungen wurden zurückgesetzt', 'success');
                window.location.reload();
            } catch (error) {
                notify(error.message || 'Zurücksetzen fehlgeschlagen', 'error');
            } finally {
                accountResetAllSettingsBtn.disabled = false;
                if (accountResetAllSettingsSpinner) accountResetAllSettingsSpinner.classList.add('hidden');
                if (accountResetAllSettingsLabel) accountResetAllSettingsLabel.textContent = originalLabel;
            }
        });
    }
})();
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>

