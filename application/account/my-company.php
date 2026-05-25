<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
requireLogin();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = null;

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.vorname, u.nachname, u.telefonnummer, u.rolle, u.status, u.company_id, u.logopfad,
               c.name as company_name, c.logo as company_logo, c.email as company_email,
               c.telefonnummer as company_telefon, c.adresse as company_adresse,
               c.plz as company_plz, c.ort as company_ort, c.kundennummer as company_kundennummer,
               c.lieferadresse as company_lieferadresse, c.liefer_plz as company_liefer_plz,
               c.liefer_ort as company_liefer_ort, c.rechnungs_adresse as company_rechnungs_adresse,
               c.rechnungs_plz as company_rechnungs_plz, c.rechnungs_ort as company_rechnungs_ort,
               assigned_user.vorname as assigned_user_vorname, assigned_user.nachname as assigned_user_nachname
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN users assigned_user ON c.zugewiesen_an = assigned_user.id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $companyRow = [
            'name' => $user['company_name'] ?? null,
            'email' => $user['company_email'] ?? null,
            'telefonnummer' => $user['company_telefon'] ?? null,
            'adresse' => $user['company_adresse'] ?? null,
            'plz' => $user['company_plz'] ?? null,
            'ort' => $user['company_ort'] ?? null,
            'kundennummer' => $user['company_kundennummer'] ?? null,
            'lieferadresse' => $user['company_lieferadresse'] ?? null,
            'liefer_plz' => $user['company_liefer_plz'] ?? null,
            'liefer_ort' => $user['company_liefer_ort'] ?? null,
            'rechnungs_adresse' => $user['company_rechnungs_adresse'] ?? null,
            'rechnungs_plz' => $user['company_rechnungs_plz'] ?? null,
            'rechnungs_ort' => $user['company_rechnungs_ort'] ?? null
        ];
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
} catch (PDOException $e) {
    error_log("Account/MyCompany: Fehler beim Laden der Firmendaten: " . $e->getMessage());
}

if (!$user) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$fullName = trim((string) (($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? '')));
if ($fullName === '') {
    $fullName = (string) ($user['email'] ?? 'Benutzer');
}
$initials = '';
if (!empty($user['vorname']) && !empty($user['nachname'])) {
    $initials = strtoupper(substr((string) $user['vorname'], 0, 1) . substr((string) $user['nachname'], 0, 1));
} elseif (!empty($user['email'])) {
    $initials = strtoupper(substr((string) $user['email'], 0, 1));
} else {
    $initials = 'U';
}
$avatarIsPreset = false;
$avatarPresetColor = '#6b7280';
$avatarPresetInitials = $initials;
$avatarImagePath = null;
if (!empty($user['logopfad'])) {
    if (strpos((string) $user['logopfad'], 'preset:') === 0) {
        $avatarIsPreset = true;
        $parts = explode(':', (string) $user['logopfad']);
        $avatarPresetColor = $parts[1] ?? '#6b7280';
        if (strpos($avatarPresetColor, '#') !== 0) {
            $avatarPresetColor = '#' . $avatarPresetColor;
        }
        $avatarPresetInitials = $parts[2] ?? $initials;
    } else {
        $avatarImagePath = (string) $user['logopfad'];
    }
}
$assignedName = trim((string) (($user['assigned_user_vorname'] ?? '') . ' ' . ($user['assigned_user_nachname'] ?? '')));

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden lg:overflow-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 lg:h-screen app-mobile-no-root-overscroll">
  <main class="mx-4 mt-2 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 lg:flex lg:h-[calc(100vh-0.5rem)] lg:flex-col lg:overflow-hidden">
    <div class="lg:flex-1 lg:min-h-0">
      <div class="grid grid-cols-12 gap-x-4 gap-y-0 bg-gray-50 dark:bg-primary-50">
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
                    <a href="<?php echo BASE_URL; ?>account/index.php" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2">Mein Konto</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">My Company</span>
                  </div>
                </li>
              </ol>
            </nav>
          </div>
        </div>

        <div class="col-span-full hidden lg:block lg:min-h-0">
          <div class="lg:grid lg:grid-cols-12 lg:gap-4 lg:items-start">
        <aside class="hidden rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 mb-4 lg:col-span-3 lg:mb-0 lg:block lg:self-start">
          <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
            <div class="flex items-center gap-3">
              <a href="<?php echo BASE_URL; ?>account/index.php" class="shrink-0 rounded-full focus:outline-none">
                <?php if ($avatarIsPreset): ?>
                  <div class="flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 text-sm font-semibold text-white dark:border-gray-600" style="background-color: <?php echo htmlspecialchars($avatarPresetColor); ?>;"><?php echo htmlspecialchars($avatarPresetInitials); ?></div>
                <?php elseif ($avatarImagePath): ?>
                  <img src="<?php echo htmlspecialchars(getUserAvatarUrl($avatarImagePath)); ?>" alt="Profilbild" class="h-12 w-12 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                <?php else: ?>
                  <div class="flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-gray-500 text-sm font-semibold text-white dark:border-gray-600"><?php echo htmlspecialchars($initials); ?></div>
                <?php endif; ?>
              </a>
              <a href="<?php echo BASE_URL; ?>account/index.php" class="min-w-0 block w-full rounded-md p-1 -m-1 focus:outline-none">
                <p class="truncate text-base font-semibold text-gray-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-300"><?php echo htmlspecialchars($fullName); ?></p>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['rolle'] ?? 'Benutzer'); ?></p>
              </a>
            </div>
          </div>
          <div class="mb-4 grid grid-cols-1 gap-1.5 border-b border-gray-200 pb-4 dark:border-gray-700">
            <a href="<?php echo BASE_URL; ?>account/index.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 border-transparent px-4 py-3 text-left text-base font-semibold !bg-transparent !text-gray-700 transition-colors hover:!bg-gray-100 hover:!text-gray-900 dark:!text-gray-300 dark:hover:!bg-gray-700 dark:hover:!text-white">
              <svg class="me-2 h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z" />
              </svg>
              Persönliche Daten
            </a>
            <a href="<?php echo BASE_URL; ?>account/my-company.php" class="inline-flex w-full items-center justify-start rounded-lg border-s-2 px-4 py-3 text-left text-base font-semibold !border-primary-700 !bg-primary-100 !text-primary-800 hover:!bg-primary-100 hover:!text-primary-800 dark:!border-primary-300 dark:!bg-primary-800/40 dark:!text-primary-200 dark:hover:!bg-primary-800/40 dark:hover:!text-primary-200" style="background-color:#ede9fe !important;color:#5b21b6 !important;border-left-color:#7e22ce !important;">
              <svg class="me-2 h-5 w-5 !text-[#5b21b6]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
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

        <div class="w-full lg:col-span-9">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700"><h3 class="text-xl font-semibold leading-none text-gray-900 dark:text-white">My Company</h3></div>
            <dl class="space-y-2">
              <div><dt class="mt-2 font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></dt></div>
              <div><dt class="text-sm font-medium text-gray-700 dark:text-gray-300">Kundennummer</dt><dd class="text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['company_kundennummer'] ?? '-'); ?></dd></div>
              <div><dt class="text-sm font-medium text-gray-700 dark:text-gray-300">Kontakt</dt><dd class="text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars(($user['company_email'] ?? '-') . ' | ' . ($user['company_telefon'] ?? '-')); ?></dd></div>
              <div><dt class="text-sm font-medium text-gray-700 dark:text-gray-300">Zugewiesen an</dt><dd class="text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($assignedName !== '' ? $assignedName : '-'); ?></dd></div>
            </dl>
          </div>
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700"><h3 class="text-xl font-semibold leading-none text-gray-900 dark:text-white">Firmenadresse</h3></div>
            <p class="text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars(trim((string) ($user['company_adresse'] ?? '')) ?: '-'); ?><br><?php echo htmlspecialchars(trim((string) (($user['company_plz'] ?? '') . ' ' . ($user['company_ort'] ?? ''))) ?: '-'); ?></p>
          </div>
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700"><h3 class="text-xl font-semibold leading-none text-gray-900 dark:text-white">Liefer-/Rechnungsadresse</h3></div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Lieferadresse</p>
            <p class="mb-3 text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars(trim((string) ($user['company_lieferadresse'] ?? '')) ?: '-'); ?><br><?php echo htmlspecialchars(trim((string) (($user['company_liefer_plz'] ?? '') . ' ' . ($user['company_liefer_ort'] ?? ''))) ?: '-'); ?></p>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Rechnungsadresse</p>
            <p class="text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars(trim((string) ($user['company_rechnungs_adresse'] ?? '')) ?: '-'); ?><br><?php echo htmlspecialchars(trim((string) (($user['company_rechnungs_plz'] ?? '') . ' ' . ($user['company_rechnungs_ort'] ?? ''))) ?: '-'); ?></p>
          </div>
          </div>
        </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
(function() {
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

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
