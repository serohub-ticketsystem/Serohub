<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/inventory_permissions.php';
require_once dirname(__DIR__) . '/assets/admin_user_profile.php';
require_once dirname(__DIR__) . '/assets/admin_user_sessions.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
requireLogin();
inventory_permissions_ensure_columns($pdo);

$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
if (admin_require_admin_role($pdo, $sessionUserId) === null) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$targetUserId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($targetUserId <= 0) {
    header('Location: ' . BASE_URL . 'admin/users.php');
    exit;
}

$allRoles = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT rolle FROM users WHERE rolle IS NOT NULL AND rolle != '' ORDER BY rolle ASC");
    $allRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $allRoles = [];
}

$dbError = null;
$profile = null;
try {
    $profile = admin_user_profile_load($pdo, $targetUserId);
    if (empty($profile['user'])) {
        header('Location: ' . BASE_URL . 'admin/users.php');
        exit;
    }
    try {
        $optStmt = $pdo->prepare('SELECT passwort_geaendert, passwort_aendern_erforderlich FROM users WHERE id = ? LIMIT 1');
        $optStmt->execute([$targetUserId]);
        $optData = $optStmt->fetch(PDO::FETCH_ASSOC);
        if ($optData) {
            $profile['user']['passwort_geaendert'] = $optData['passwort_geaendert'] ?? null;
            $profile['user']['passwort_aendern_erforderlich'] = (int) ($optData['passwort_aendern_erforderlich'] ?? 0);
        }
    } catch (PDOException $e) {
        $profile['user']['passwort_geaendert'] = null;
        $profile['user']['passwort_aendern_erforderlich'] = 0;
    }
} catch (PDOException $e) {
    error_log('admin/user.php: ' . $e->getMessage());
    $dbError = 'Die Benutzerdaten konnten nicht geladen werden.';
}

$displayUser = $profile['user'] ?? [];
$fullName = trim(($displayUser['vorname'] ?? '') . ' ' . ($displayUser['nachname'] ?? ''));
if ($fullName === '') {
    $fullName = (string) ($displayUser['email'] ?? 'Benutzer');
}
$status = $displayUser['status'] ?? 'aktiv';
$pathIsSensitive = admin_user_profile_path_is_sensitive();

$initials = 'U';
if (!empty($displayUser['vorname']) && !empty($displayUser['nachname'])) {
    $initials = strtoupper(substr((string) $displayUser['vorname'], 0, 1) . substr((string) $displayUser['nachname'], 0, 1));
} elseif (!empty($displayUser['email'])) {
    $initials = strtoupper(substr((string) $displayUser['email'], 0, 1));
}

$statusBadgeClass = match ($status) {
    'aktiv' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'inaktiv' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    'gesperrt' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    default => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

$activeSessions = [];
$rememberMeActive = false;
if (!empty($profile['user'])) {
    $activeSessions = admin_user_load_sessions($pdo, $targetUserId);
    $rememberMeActive = admin_user_remember_me_active($pdo, $targetUserId);
}

$companiesList = [];
try {
    $cStmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companiesList = $cStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($companiesList as &$c) {
        if (!empty($c['name'])) {
            $c['name'] = decrypt_from_db($c['name']);
        }
    }
    unset($c);
} catch (PDOException $e) {
    $companiesList = [];
}

$blockedSettingKeys = ['2fa_secret'];
$settingsMapJson = json_encode($profile['settings_map'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$tabDescriptions = [
    'overview' => 'Stammdaten, Konto, Passwort und Sicherheit',
    'settings' => 'Benutzereinstellungen anzeigen und bearbeiten',
    'cards' => 'Dashboard-Cards und ausgeblendete Hinweise',
    'technical' => 'Gespeicherte Rohdaten (users und user_settings)',
];

$settingsSetCount = 0;
$settingsEvalSet = [];
$settingsEvalUnset = [];
if (!empty($profile['settings_evaluation'])) {
    foreach ($profile['settings_evaluation'] as $ev) {
        if ($ev['is_set']) {
            $settingsSetCount++;
            $settingsEvalSet[] = $ev;
        } else {
            $settingsEvalUnset[] = $ev;
        }
    }
}

$cardsDismissedCount = count($profile['dismissed_card_ids'] ?? []);
$easyModeOn = !empty($profile['settings_map']['easy_mode']) && $profile['settings_map']['easy_mode'] === '1';
$twoFaOn = !empty($profile['settings_map']['2fa_enabled']) && $profile['settings_map']['2fa_enabled'] === '1';
$companyId = !empty($displayUser['company_id']) ? (int) $displayUser['company_id'] : 0;

$userFieldLabels = [
    'id' => 'Benutzer-ID',
    'email' => 'E-Mail',
    'vorname' => 'Vorname',
    'nachname' => 'Nachname',
    'telefonnummer' => 'Telefon',
    'rolle' => 'Rolle',
    'status' => 'Status',
    'company_id' => 'Firmen-ID',
    'company_name' => 'Firma',
    'customer_id' => 'Kunden-ID',
    'logopfad' => 'Profilbild',
    'erstellt_datum' => 'Erstellt am',
    'geaendert_datum' => 'Geändert am',
    'letzte_anmeldung' => 'Letzte Anmeldung',
    'letztes_pw_change' => 'Letzte Passwortänderung',
    'passwort_geaendert' => 'Passwort geändert',
    'passwort_aendern_erforderlich' => 'Passwortwechsel nötig',
    'lager_bestand_anpassen' => 'Lagerbestand anpassen',
    'gesperrt' => 'Gesperrt',
    'gesperrt_bis' => 'Gesperrt bis',
    'fehlversuche' => 'Fehlversuche',
    'onboarding_abgeschlossen' => 'Onboarding abgeschlossen',
    'calendar_token' => 'Kalender-Token',
];

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
    <main>
        <div class="px-4">
            <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">

        <?php if ($dbError): ?>
                <div class="col-span-full mx-4 mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20"><?php echo htmlspecialchars($dbError); ?></div>
        <?php else: ?>

                <div class="col-span-full mx-4 mt-4">
                    <nav class="mb-4 flex" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                            <li class="inline-flex items-center">
                                <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                                    <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd"/></svg>
                                    Startseite
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                                    <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                                    <a href="<?php echo BASE_URL; ?>admin/users.php" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2">Benutzer</a>
                                </div>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2"><?php echo htmlspecialchars($fullName); ?></span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-800 dark:bg-primary-900/50 dark:text-primary-200">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($fullName); ?></h1>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($displayUser['email'] ?? ''); ?></p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusBadgeClass; ?>"><?php echo ucfirst($status); ?></span>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"><?php echo htmlspecialchars($displayUser['rolle'] ?? '—'); ?></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">ID <?php echo $targetUserId; ?></span>
                                </div>
                            </div>
                        </div>
                        <a href="<?php echo BASE_URL; ?>admin/users.php" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">← Zur Liste</a>
                    </div>
                </div>

                <!-- Kurzinfos -->
                <div class="col-span-full mx-4">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Firma</p>
                            <p class="mt-1 truncate text-sm font-medium text-gray-900 dark:text-white">
                                <?php if ($companyId > 0): ?>
                                    <a href="<?php echo BASE_URL; ?>companies/detail.php?id=<?php echo $companyId; ?>" class="text-primary-600 hover:text-primary-800 dark:text-primary-400"><?php echo htmlspecialchars($displayUser['company_name'] ?? '—'); ?></a>
                                <?php else: ?>
                                    <span class="text-gray-400">Keine Firma</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Letzte Anmeldung</p>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white"><?php echo !empty($displayUser['letzte_anmeldung']) ? (new DateTime($displayUser['letzte_anmeldung']))->format('d.m.Y H:i') : 'Nie'; ?></p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Einstellungen</p>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white"><?php echo (int) ($profile['stats']['settings_count'] ?? 0); ?> gespeichert · <?php echo $settingsSetCount; ?> aktiv</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Sicherheit / Cards</p>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white"><?php echo $twoFaOn ? '2FA an' : '2FA aus'; ?> · <?php echo $cardsDismissedCount; ?> Cards aus</p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="col-span-full mx-4 mb-2">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
                            <button type="button" class="user-tab-nav border-b-2 border-primary-600 px-1 py-3 text-sm font-medium text-primary-600 dark:text-primary-400" data-tab="overview">Übersicht</button>
                            <button type="button" class="user-tab-nav border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" data-tab="settings">Einstellungen (<?php echo $settingsSetCount; ?>)</button>
                            <button type="button" class="user-tab-nav border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" data-tab="cards">Dashboard-Cards</button>
                            <button type="button" class="user-tab-nav border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" data-tab="technical">Technisch</button>
                        </nav>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" id="userTabDescription"><?php echo htmlspecialchars($tabDescriptions['overview']); ?></p>
                </div>

                <div class="relative col-span-full w-full min-w-0 mx-4 mb-8">

        <!-- Tab: Übersicht -->
        <div id="tabContentOverview" class="user-tab-content w-full space-y-6">
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Stammdaten</h2>
                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Persönliche Angaben und Firmenzuordnung.</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="userVorname" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Vorname</label>
                                <input type="text" id="userVorname" value="<?php echo htmlspecialchars($displayUser['vorname'] ?? ''); ?>" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="userNachname" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nachname</label>
                                <input type="text" id="userNachname" value="<?php echo htmlspecialchars($displayUser['nachname'] ?? ''); ?>" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="userEmail" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">E-Mail</label>
                                <input type="email" id="userEmail" value="<?php echo htmlspecialchars($displayUser['email'] ?? ''); ?>" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="userTelefon" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefon</label>
                                <input type="text" id="userTelefon" value="<?php echo htmlspecialchars($displayUser['telefonnummer'] ?? ''); ?>" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="userCompany" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Firma</label>
                                <select id="userCompany" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="">— Keine Firma —</option>
                                    <?php foreach ($companiesList as $c): ?>
                                        <option value="<?php echo (int) $c['id']; ?>" <?php echo (int) ($displayUser['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php if ($status !== 'gesperrt'): ?>
                        <button type="button" onclick="saveProfileData()" class="mt-4 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 dark:bg-primary-500">Stammdaten speichern</button>
                        <?php endif; ?>
                    </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-6">
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Konto &amp; Berechtigungen</h2>
                        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">Status, Rolle und Berechtigungen – alle Änderungen mit einem Klick speichern.</p>

                        <?php if ($status !== 'gesperrt'): ?>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="userStatus" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <select id="userStatus" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="aktiv" <?php echo $status === 'aktiv' ? 'selected' : ''; ?>>Aktiv</option>
                                    <option value="inaktiv" <?php echo $status === 'inaktiv' ? 'selected' : ''; ?>>Inaktiv</option>
                                </select>
                            </div>
                            <div>
                                <label for="userRole" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Rolle</label>
                                <select id="userRole" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <?php foreach ($allRoles as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role); ?>" <?php echo ($displayUser['rolle'] ?? '') === $role ? 'selected' : ''; ?>><?php echo htmlspecialchars($role); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 space-y-3 rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/30">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="checkbox" id="passwordRequired" <?php echo !empty($displayUser['passwort_aendern_erforderlich']) ? 'checked' : ''; ?> class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Beim nächsten Login Passwort ändern erforderlich</span>
                            </label>
                            <?php if (!empty($displayUser['company_id'])): ?>
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="checkbox" id="lagerBestand" <?php echo !empty($displayUser['lager_bestand_anpassen']) ? 'checked' : ''; ?> class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Darf Lagerbestand anpassen (Ein-/Auslagern)</span>
                            </label>
                            <?php endif; ?>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <button type="button" onclick="saveAccountSettings()" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:ring-2 focus:ring-primary-300 dark:bg-primary-500 dark:hover:bg-primary-600">Änderungen speichern</button>
                            <button type="button" onclick="lockUser()" class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">Account sperren</button>
                        </div>
                        <?php else: ?>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                            <p class="text-sm text-amber-800 dark:text-amber-200">Dieser Account ist gesperrt. Bearbeitung ist eingeschränkt.</p>
                            <?php if (!empty($displayUser['gesperrt_bis'])): ?>
                                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Gesperrt bis: <?php echo (new DateTime($displayUser['gesperrt_bis']))->format('d.m.Y H:i'); ?></p>
                            <?php endif; ?>
                            <button type="button" onclick="unlockUser()" class="mt-3 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Account entsperren</button>
                        </div>
                        <?php endif; ?>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Passwort setzen</h2>
                        <form id="passwordForm" class="flex flex-wrap gap-3 items-end" onsubmit="changePassword(event)">
                            <div class="min-w-[180px] flex-1">
                                <label for="newPassword" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Neues Passwort</label>
                                <input type="password" id="newPassword" required minlength="8" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Mindestens 8 Zeichen">
                            </div>
                            <div class="min-w-[180px] flex-1">
                                <label for="confirmPassword" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Bestätigen</label>
                                <input type="password" id="confirmPassword" required minlength="8" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Wiederholen">
                            </div>
                            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 dark:bg-primary-500">Passwort setzen</button>
                        </form>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Zwei-Faktor-Authentifizierung</h2>
                            <?php if ($twoFaOn): ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aktiv</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Aus</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($twoFaOn): ?>
                            <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Als Admin kannst du 2FA für diesen Benutzer deaktivieren (ohne TOTP-Code des Benutzers).</p>
                            <button type="button" onclick="disable2fa()" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">2FA deaktivieren</button>
                        <?php else: ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Für diesen Benutzer ist keine 2FA aktiv.</p>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Aktive Anmeldungen (Geräte)</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Eingeloggte Geräte dieses Benutzers.</p>
                    </div>
                    <button type="button" onclick="logoutEverywhere()" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">Überall abmelden</button>
                </div>
                <?php if ($rememberMeActive): ?>
                    <p class="mb-3 rounded-lg bg-gray-100 p-3 text-sm text-gray-700 dark:bg-gray-700/80 dark:text-gray-200">„Angemeldet bleiben“ ist für mindestens ein Gerät aktiv.</p>
                <?php endif; ?>
                <?php if (empty($activeSessions)): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Keine aktiven Sessions in der Datenbank erfasst.</p>
                <?php else: ?>
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 text-xs uppercase dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3">Gerät</th>
                                    <th class="px-4 py-3">IP</th>
                                    <th class="px-4 py-3">Zuletzt aktiv</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($activeSessions as $sess):
                                    $lastActive = new DateTime($sess['last_activity']);
                                    $diff = (new DateTime())->diff($lastActive);
                                    $minutesAgo = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;
                                ?>
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($sess['os'] . ' – ' . $sess['browser']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($sess['ip_address'] ?? '—'); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap"><?php echo $lastActive->format('d.m.Y H:i'); ?><?php echo $minutesAgo < 30 ? ' · aktiv' : ''; ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" onclick="logoutDevice(<?php echo (int) ($sess['id'] ?? 0); ?>, '<?php echo htmlspecialchars($sess['session_id'] ?? '', ENT_QUOTES); ?>')" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">Abmelden</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Kurzübersicht Einstellungen (<?php echo $settingsSetCount; ?> aktiv)</h2>
                    <button type="button" class="user-goto-tab text-sm font-medium text-primary-600 hover:underline dark:text-primary-400" data-tab="settings">Alle bearbeiten →</button>
                </div>
                <?php if ($settingsSetCount === 0): ?>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Noch keine Einstellungen gespeichert.</p>
                <?php else: ?>
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach (array_slice($settingsEvalSet, 0, 9) as $ev): ?>
                        <li class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900/40">
                            <span class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($ev['label']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <!-- Tab: Einstellungen -->
        <div id="tabContentSettings" class="user-tab-content hidden w-full">
            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-100 p-5 dark:border-gray-700 sm:flex sm:items-center sm:justify-between sm:gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Einstellungs-Auswertung</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bekannte Optionen – bearbeiten oder neue Werte setzen.</p>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 sm:mt-0">
                        <input type="search" id="settingsSearch" placeholder="Suchen…" class="block w-full max-w-xs rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <div class="flex flex-wrap gap-3">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="radio" name="settingsFilter" value="all" checked class="text-primary-600"> Alle</label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="radio" name="settingsFilter" value="set" class="text-primary-600"> Gesetzt</label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="radio" name="settingsFilter" value="unset" class="text-primary-600"> Nicht gesetzt</label>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="sticky top-0 bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Einstellung</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Vorschau</th>
                                <th class="px-4 py-3 font-semibold text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody id="settingsTableBody" class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($profile['settings_evaluation'] as $ev):
                                $key = $ev['key'];
                                $isBlocked = in_array($key, $blockedSettingKeys, true);
                                $rawVal = $profile['settings_map'][$key] ?? '';
                            ?>
                            <tr class="settings-eval-item hover:bg-gray-50 dark:hover:bg-gray-700/50 <?php echo $ev['is_set'] ? '' : 'opacity-80'; ?>"
                                data-set="<?php echo $ev['is_set'] ? '1' : '0'; ?>"
                                data-search="<?php echo htmlspecialchars(strtolower($key . ' ' . $ev['label'] . ' ' . $ev['preview'])); ?>">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($ev['label']); ?></p>
                                    <p class="mt-0.5 font-mono text-xs text-gray-500"><?php echo htmlspecialchars($key); ?></p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($ev['is_set']): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Gesetzt</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="max-w-md px-4 py-3 text-xs break-all text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($ev['preview']); ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?php if (!$isBlocked): ?>
                                    <button type="button" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400" onclick="openSettingModal('<?php echo htmlspecialchars($key, ENT_QUOTES); ?>')"><?php echo $ev['is_set'] ? 'Bearbeiten' : 'Setzen'; ?></button>
                                    <?php if ($ev['is_set']): ?>
                                    <button type="button" class="ml-2 text-sm font-medium text-red-600 hover:underline dark:text-red-400" onclick="deleteSetting('<?php echo htmlspecialchars($key, ENT_QUOTES); ?>')">Löschen</button>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400">Geschützt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p id="settingsEmptyHint" class="hidden px-4 py-8 text-center text-sm text-gray-500">Keine Treffer für die Suche.</p>
                </div>
            </section>

            <section class="mt-6 rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-100 p-5 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Gespeicherte Daten (user_settings)</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo count($profile['settings_rows']); ?> Einträge in der Datenbank – Rohwerte bearbeiten oder löschen.</p>
                    <button type="button" onclick="openSettingModal('')" class="mt-3 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">+ Neue Einstellung</button>
                </div>
                <div class="overflow-x-auto max-h-[24rem] overflow-y-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="sticky top-0 bg-gray-50 text-xs uppercase dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3">Schlüssel</th>
                                <th class="px-4 py-3">Wert (Vorschau)</th>
                                <th class="px-4 py-3 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (count($profile['settings_rows']) === 0): ?>
                            <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Keine gespeicherten Einstellungen.</td></tr>
                            <?php else: foreach ($profile['settings_rows'] as $row):
                                $key = $row['setting_key'] ?? '';
                                $val = (string) ($row['setting_value'] ?? '');
                                $preview = strlen($val) > 120 ? substr($val, 0, 120) . '…' : $val;
                                if (in_array($key, $blockedSettingKeys, true)) {
                                    $preview = '•••••••• (geschützt)';
                                }
                                $isBlocked = in_array($key, $blockedSettingKeys, true);
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 font-mono text-xs text-gray-900 dark:text-white"><?php echo htmlspecialchars($key); ?></td>
                                <td class="max-w-lg px-4 py-3 break-all text-xs"><?php echo htmlspecialchars($preview); ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?php if (!$isBlocked): ?>
                                    <button type="button" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400" onclick="openSettingModal('<?php echo htmlspecialchars($key, ENT_QUOTES); ?>')">Bearbeiten</button>
                                    <button type="button" class="ml-2 text-sm font-medium text-red-600 hover:underline dark:text-red-400" onclick="deleteSetting('<?php echo htmlspecialchars($key, ENT_QUOTES); ?>')">Löschen</button>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400">Geschützt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Tab: Cards -->
        <div id="tabContentCards" class="user-tab-content hidden w-full">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Dashboard-Cards</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Welche Hinweis-Cards der Benutzer auf dem Dashboard sieht oder ausgeblendet hat.</p>
                    </div>
                    <?php if ($cardsDismissedCount > 0): ?>
                    <button type="button" onclick="resetDismissedCards()" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">Verworfene Cards zurücksetzen</button>
                    <?php endif; ?>
                </div>
                <?php if (empty($profile['dashboard_cards'])): ?>
                    <div class="rounded-lg border border-dashed border-gray-200 py-12 text-center dark:border-gray-600">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Keine Cards für diese Firma oder keine ausgeblendeten System-Cards.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 text-xs uppercase dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Titel</th>
                                    <th class="px-4 py-3 font-semibold">ID</th>
                                    <th class="px-4 py-3 font-semibold">Typ</th>
                                    <th class="px-4 py-3 font-semibold">Quelle</th>
                                    <th class="px-4 py-3 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($profile['dashboard_cards'] as $card): ?>
                                <tr class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($card['titel']); ?></td>
                                    <td class="px-4 py-3 font-mono text-xs"><?php echo htmlspecialchars($card['id']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($card['typ'] ?? ''); ?></td>
                                    <td class="px-4 py-3"><?php echo $card['source'] === 'system' ? 'System' : 'Dashboard'; ?></td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($card['dismissed'])): ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Ausgeblendet</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Sichtbar</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <p class="mt-4 text-xs text-gray-500">Easy-Mode-Cards: <a href="<?php echo BASE_URL; ?>admin/cards-settings.php" class="text-primary-600 hover:underline dark:text-primary-400">Systemweite Konfiguration</a></p>
            </section>
        </div>

        <!-- Tab: Technisch -->
        <div id="tabContentTechnical" class="user-tab-content hidden w-full space-y-4">
            <details class="group rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <summary class="cursor-pointer list-none px-6 py-4 font-semibold text-gray-900 marker:content-none dark:text-white [&::-webkit-details-marker]:hidden">
                    <span class="flex items-center justify-between">
                        Kontodaten (Datenbank)
                        <svg class="h-5 w-5 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </summary>
                <div class="border-t border-gray-100 px-4 pb-4 dark:border-gray-700">
                    <dl class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($displayUser as $col => $val):
                            $label = $userFieldLabels[$col] ?? $col;
                        ?>
                        <div class="grid gap-1 py-3 sm:grid-cols-3">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($label); ?></dt>
                            <dd class="break-all font-mono text-xs text-gray-900 sm:col-span-2 dark:text-gray-100"><?php echo htmlspecialchars($val === null || $val === '' ? '—' : (string) $val); ?></dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </details>

            <details class="group rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <summary class="cursor-pointer list-none px-6 py-4 font-semibold text-gray-900 marker:content-none dark:text-white [&::-webkit-details-marker]:hidden">
                    <span class="flex items-center justify-between">
                        user_settings (Rohdaten)
                        <span class="text-sm font-normal text-gray-500"><?php echo count($profile['settings_rows']); ?> Einträge</span>
                    </span>
                </summary>
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    <?php if (count($profile['settings_rows']) === 0): ?>
                        <p class="text-sm text-gray-500">Keine Einträge.</p>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($profile['settings_rows'] as $row):
                            $key = $row['setting_key'] ?? '';
                            $expanded = admin_user_profile_expand_row($key, $row['setting_value'] ?? null, $pathIsSensitive);
                        ?>
                        <details class="rounded-lg border border-gray-200 dark:border-gray-600">
                            <summary class="cursor-pointer px-4 py-3 font-mono text-sm text-primary-700 dark:text-primary-300"><?php echo htmlspecialchars($key); ?></summary>
                            <div class="border-t border-gray-50 px-4 py-2 dark:border-gray-700">
                                <?php foreach ($expanded as $line): ?>
                                <div class="grid gap-1 border-b border-gray-50 py-2 last:border-0 dark:border-gray-700 sm:grid-cols-2">
                                    <span class="font-mono text-xs text-gray-500"><?php echo htmlspecialchars($line['path']); ?></span>
                                    <span class="break-all font-mono text-xs text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($line['value']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </details>
        </div>

                </div>

        <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<div id="settingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
    <div class="w-full max-w-lg rounded-lg border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-800">
        <h3 id="settingModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Einstellung bearbeiten</h3>
        <div class="mt-4 space-y-3">
            <div>
                <label for="settingModalKey" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Schlüssel</label>
                <input type="text" id="settingModalKey" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" pattern="[a-zA-Z0-9_.-]+">
            </div>
            <div>
                <label for="settingModalValue" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Wert</label>
                <textarea id="settingModalValue" rows="6" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
                <p class="mt-1 text-xs text-gray-500">JSON oder einfacher Text – wird 1:1 in user_settings gespeichert.</p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" onclick="closeSettingModal()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">Abbrechen</button>
            <button type="button" onclick="saveSettingFromModal()" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 dark:bg-primary-500">Speichern</button>
        </div>
    </div>
</div>

<script>
const TARGET_USER_ID = <?php echo $targetUserId; ?>;
const API_BASE = '<?php echo BASE_URL; ?>admin/api/';
const SETTINGS_MAP = <?php echo $settingsMapJson ?: '{}'; ?>;
const BLOCKED_SETTING_KEYS = <?php echo json_encode($blockedSettingKeys); ?>;

function apiPost(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(r => r.json());
}

function toast(msg, type) {
    if (typeof showToast === 'function') showToast(msg, type || 'info');
    else alert(msg);
}

const userTabDescriptions = <?php echo json_encode($tabDescriptions, JSON_UNESCAPED_UNICODE); ?>;

function switchUserTab(tab) {
    const panels = {
        overview: 'tabContentOverview',
        settings: 'tabContentSettings',
        cards: 'tabContentCards',
        technical: 'tabContentTechnical'
    };
    document.querySelectorAll('.user-tab-nav').forEach(btn => {
        const isActive = btn.dataset.tab === tab;
        btn.classList.toggle('border-primary-600', isActive);
        btn.classList.toggle('text-primary-600', isActive);
        btn.classList.toggle('dark:text-primary-400', isActive);
        btn.classList.toggle('border-transparent', !isActive);
        btn.classList.toggle('text-gray-500', !isActive);
        btn.classList.toggle('dark:text-gray-400', !isActive);
    });
    Object.entries(panels).forEach(([key, id]) => {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('hidden', key !== tab);
    });
    const desc = document.getElementById('userTabDescription');
    if (desc && userTabDescriptions[tab]) desc.textContent = userTabDescriptions[tab];
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.user-tab-nav').forEach(btn => {
        btn.addEventListener('click', () => switchUserTab(btn.dataset.tab));
    });
    document.querySelectorAll('.user-goto-tab').forEach(btn => {
        btn.addEventListener('click', () => switchUserTab(btn.dataset.tab));
    });

    const searchEl = document.getElementById('settingsSearch');
    const emptyHint = document.getElementById('settingsEmptyHint');

    function applySettingsFilter() {
        const q = (searchEl?.value || '').toLowerCase().trim();
        const filter = document.querySelector('input[name="settingsFilter"]:checked')?.value || 'all';
        let visible = 0;
        document.querySelectorAll('.settings-eval-item').forEach(el => {
            const isSet = el.dataset.set === '1';
            const matchFilter = filter === 'all' || (filter === 'set' && isSet) || (filter === 'unset' && !isSet);
            const matchSearch = !q || (el.dataset.search || '').includes(q);
            const show = matchFilter && matchSearch;
            el.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (emptyHint) emptyHint.classList.toggle('hidden', visible > 0);
    }

    document.querySelectorAll('input[name="settingsFilter"]').forEach(r => r.addEventListener('change', applySettingsFilter));
    searchEl?.addEventListener('input', applySettingsFilter);

    const hash = window.location.hash.replace('#', '');
    if (['overview', 'settings', 'cards', 'technical'].includes(hash)) {
        switchUserTab(hash);
    }
});

function changePassword(e) {
    e.preventDefault();
    const a = document.getElementById('newPassword').value;
    const b = document.getElementById('confirmPassword').value;
    if (a !== b) { toast('Passwörter stimmen nicht überein', 'error'); return; }
    apiPost(API_BASE + 'user-password.php', { user_id: TARGET_USER_ID, new_password: a })
        .then(d => { if (d.success) { toast('Passwort geändert', 'success'); document.getElementById('passwordForm').reset(); } else toast(d.message || 'Fehler', 'error'); });
}

function saveAccountSettings() {
    const payload = {
        user_id: TARGET_USER_ID,
        status: document.getElementById('userStatus')?.value,
        rolle: document.getElementById('userRole')?.value,
        passwort_aendern_erforderlich: document.getElementById('passwordRequired')?.checked ? 1 : 0
    };
    const lager = document.getElementById('lagerBestand');
    if (lager) payload.lager_bestand_anpassen = lager.checked ? 1 : 0;
    apiPost(API_BASE + 'user-update.php', payload)
        .then(d => { if (d.success) { toast('Konto gespeichert', 'success'); setTimeout(() => location.reload(), 700); } else toast(d.message || 'Fehler', 'error'); });
}

function lockUser() {
    if (!confirm('Benutzer wirklich sperren?')) return;
    apiPost(API_BASE + 'user-lock.php', { user_id: TARGET_USER_ID })
        .then(d => { if (d.success) { toast('Gesperrt', 'success'); setTimeout(() => location.reload(), 700); } else toast(d.message || 'Fehler', 'error'); });
}

function unlockUser() {
    if (!confirm('Benutzer entsperren?')) return;
    apiPost(API_BASE + 'user-unlock.php', { user_id: TARGET_USER_ID })
        .then(d => { if (d.success) { toast('Entsperrt', 'success'); setTimeout(() => location.reload(), 700); } else toast(d.message || 'Fehler', 'error'); });
}

function resetDismissedCards() {
    if (!confirm('Alle ausgeblendeten Dashboard-Cards wieder einblenden?')) return;
    apiPost(API_BASE + 'user-settings.php', { user_id: TARGET_USER_ID, action: 'reset_dismissed_cards' })
        .then(d => { if (d.success) { toast('Cards zurückgesetzt', 'success'); setTimeout(() => location.reload(), 700); } else toast(d.message || 'Fehler', 'error'); });
}

function saveProfileData() {
    const payload = {
        user_id: TARGET_USER_ID,
        vorname: document.getElementById('userVorname')?.value?.trim() ?? '',
        nachname: document.getElementById('userNachname')?.value?.trim() ?? '',
        email: document.getElementById('userEmail')?.value?.trim() ?? '',
        telefonnummer: document.getElementById('userTelefon')?.value?.trim() ?? '',
        company_id: document.getElementById('userCompany')?.value ?? ''
    };
    apiPost(API_BASE + 'user-update.php', payload)
        .then(d => { if (d.success) { toast('Stammdaten gespeichert', 'success'); setTimeout(() => location.reload(), 700); } else toast(d.message || 'Fehler', 'error'); });
}

function disable2fa() {
    if (!confirm('2FA für diesen Benutzer wirklich deaktivieren?')) return;
    apiPost(API_BASE + 'user-twofa.php', { user_id: TARGET_USER_ID, action: 'disable' })
        .then(d => { if (d.success) { toast('2FA deaktiviert', 'success'); setTimeout(() => location.reload(), 700); } else toast(d.message || 'Fehler', 'error'); });
}

function logoutEverywhere() {
    if (!confirm('Benutzer auf allen Geräten abmelden?')) return;
    apiPost(API_BASE + 'user-sessions.php', { user_id: TARGET_USER_ID, action: 'logout_everywhere' })
        .then(d => { if (d.success) { toast('Überall abgemeldet', 'success'); setTimeout(() => location.reload(), 700); } else toast(d.message || 'Fehler', 'error'); });
}

function logoutDevice(id, sid) {
    if (!confirm('Dieses Gerät abmelden?')) return;
    apiPost(API_BASE + 'user-sessions.php', { user_id: TARGET_USER_ID, action: 'logout_device', id: id, sid: sid })
        .then(d => { if (d.success) { toast('Gerät abgemeldet', 'success'); setTimeout(() => location.reload(), 700); } else toast(d.message || 'Fehler', 'error'); });
}

function openSettingModal(key) {
    const modal = document.getElementById('settingModal');
    const keyEl = document.getElementById('settingModalKey');
    const valEl = document.getElementById('settingModalValue');
    const title = document.getElementById('settingModalTitle');
    if (!modal || !keyEl || !valEl) return;
    const k = key || '';
    keyEl.value = k;
    keyEl.readOnly = !!k;
    valEl.value = k && SETTINGS_MAP[k] !== undefined ? String(SETTINGS_MAP[k]) : '';
    title.textContent = k ? 'Einstellung bearbeiten' : 'Neue Einstellung';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeSettingModal() {
    const modal = document.getElementById('settingModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function saveSettingFromModal() {
    const key = (document.getElementById('settingModalKey')?.value || '').trim();
    const value = document.getElementById('settingModalValue')?.value ?? '';
    if (!key || !/^[a-zA-Z0-9_.-]+$/.test(key)) {
        toast('Ungültiger Schlüssel', 'error');
        return;
    }
    if (BLOCKED_SETTING_KEYS.includes(key)) {
        toast('Dieser Schlüssel ist geschützt', 'error');
        return;
    }
    apiPost(API_BASE + 'user-settings.php', {
        user_id: TARGET_USER_ID,
        action: 'update_setting',
        setting_key: key,
        setting_value: value
    }).then(d => {
        if (d.success) {
            toast('Einstellung gespeichert', 'success');
            setTimeout(() => location.reload(), 600);
        } else toast(d.message || 'Fehler', 'error');
    });
}

function deleteSetting(key) {
    if (!key || !confirm('Einstellung „' + key + '“ wirklich löschen?')) return;
    apiPost(API_BASE + 'user-settings.php', { user_id: TARGET_USER_ID, action: 'delete_setting', setting_key: key })
        .then(d => { if (d.success) { toast('Gelöscht', 'success'); setTimeout(() => location.reload(), 600); } else toast(d.message || 'Fehler', 'error'); });
}

document.getElementById('settingModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSettingModal();
});
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
