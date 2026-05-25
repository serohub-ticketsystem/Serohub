<?php
/**
 * Speed Dial – Schnellzugriff zum Hinzufügen von Ticketsn, Kunden, Geräten, etc.
 * Wird systemweit rechts unten angezeigt. Unter md-Breite ausgeblendet (Handy). Sichtbarkeit: Rolle + user_settings.
 * Nutzt Flowbite data-dial-init für das Öffnen/Schließen.
 * Erwartet: $userRole, $isAdminOrTechniker, $isNotKunde (aus Sidebar).
 */
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
$canSeeCustomers = isset($userRole) && in_array($userRole, ['Admin', 'Techniker', 'Firmen-Admin'], true);
$canSeeLink = isset($userRole) && !in_array($userRole, ['Firmen-User', 'Kunde'], true);

// Benutzereinstellungen für Speed-Dial laden (Standard: Bestellung, Aufgabe, Firma, Link aus)
$speedDialDefaults = ['service' => true, 'kunde' => true, 'geraet' => true, 'firma' => false, 'inventar' => true, 'projekt' => false, 'aufgabe' => false, 'bestellung' => false, 'link' => false];
$speedDialItems = $speedDialDefaults;
$speedDialUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($speedDialUserId && isset($pdo) && $pdo instanceof PDO) {
    try {
        $sdStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'speed_dial_items' LIMIT 1");
        $sdStmt->execute([$speedDialUserId]);
        $sdRow = $sdStmt->fetch(PDO::FETCH_ASSOC);
        if ($sdRow && !empty($sdRow['setting_value'])) {
            $saved = json_decode($sdRow['setting_value'], true);
            if (is_array($saved)) {
                foreach ($speedDialDefaults as $k => $v) {
                    if (array_key_exists($k, $saved)) $speedDialItems[$k] = (bool)$saved[$k];
                }
            }
        }
    } catch (PDOException $e) { /* Defaults verwenden */ }
}

$speedDialVisible = false;
if ($speedDialUserId && isset($pdo) && $pdo instanceof PDO) {
    try {
        $sdVisibleStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'speed_dial_visible' LIMIT 1");
        $sdVisibleStmt->execute([$speedDialUserId]);
        $sdVisibleRow = $sdVisibleStmt->fetch(PDO::FETCH_ASSOC);
        if ($sdVisibleRow && array_key_exists('setting_value', $sdVisibleRow)) {
            $speedDialVisible = ((string)$sdVisibleRow['setting_value']) === '1';
        }
    } catch (PDOException $e) { /* Standard ausgeblendet */ }
}
// Verknüpfung für Firmen-User und Kunden immer deaktiviert halten
if (!$canSeeLink) {
    $speedDialItems['link'] = false;
}

$sdShow = function($key) use ($speedDialItems) { return !empty($speedDialItems[$key]); };

// Dial nur anzeigen, wenn mindestens ein Eintrag sichtbar wäre
$speedDialHasItems = $sdShow('service')
    || ($canSeeCustomers && $sdShow('kunde'))
    || $sdShow('geraet')
    || (isset($isAdminOrTechniker) && $isAdminOrTechniker && ($sdShow('firma') || $sdShow('inventar') || $sdShow('projekt') || $sdShow('aufgabe')))
    || (isset($isNotKunde) && $isNotKunde && $sdShow('bestellung'))
    || ($canSeeLink && $sdShow('link'));

// Branding: gleiche Flächen/Text/Focus wie Nav & Service (primary-* aus system_settings)
$dialBtnClass = 'flex flex-col items-center justify-center w-14 h-14 rounded-full border shadow-md focus:ring-4 focus:outline-none transition-colors ' .
  'text-gray-700 hover:text-gray-900 bg-white border-gray-200 hover:bg-gray-50 hover:border-gray-300 focus:ring-primary-250/30 ' .
  'dark:text-primary-200 dark:hover:text-primary-780 dark:bg-primary-100 dark:border-primary-120 dark:hover:bg-primary-140 dark:hover:border-primary-120 dark:focus:ring-primary-250/30';
?>
<?php if ($speedDialHasItems && $speedDialVisible): ?>
<div data-dial-init class="hidden md:block fixed bottom-6 end-6 z-[65] max-lg:bottom-[calc(5.25rem+env(safe-area-inset-bottom,0px))] group">
  <div id="speed-dial-menu-quick-add" class="flex flex-col-reverse items-center gap-3 mb-4 hidden">
    <?php if ($sdShow('service')): ?>
    <a href="<?php echo BASE_URL; ?>tickets/create.php" class="<?php echo $dialBtnClass; ?>" title="Ticket erstellen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/>
</svg>

      <span class="text-[10px] font-medium leading-tight">Ticket
      </span>
    </a>
    <?php endif; ?>
    <?php if ($canSeeCustomers && $sdShow('kunde')): ?>
    <a href="<?php echo BASE_URL; ?>customers/create.php" class="<?php echo $dialBtnClass; ?>" title="Kunde anlegen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>
      <span class="text-[10px] font-medium leading-tight">Kunde</span>
    </a>
    <?php endif; ?>
    <?php if ($sdShow('geraet')): ?>
    <a href="<?php echo BASE_URL; ?>devices/create.php" class="<?php echo $dialBtnClass; ?>" title="Gerät anlegen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"/>
</svg>

      <span class="text-[10px] font-medium leading-tight">Gerät</span>
    </a>
    <?php endif; ?>
    <?php if ($isAdminOrTechniker && $sdShow('firma')): ?>
    <a href="<?php echo BASE_URL; ?>companies/create.php" class="<?php echo $dialBtnClass; ?>" title="Firma anlegen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>
      <span class="text-[10px] font-medium leading-tight">Firma</span>
    </a>
    <?php endif; ?>
    <?php if ($isAdminOrTechniker && $sdShow('inventar')): ?>
    <a href="<?php echo BASE_URL; ?>inventory/create.php" class="<?php echo $dialBtnClass; ?>" title="Lager anlegen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.005 11.19V12l6.998 4.042L19 12v-.81M5 16.15v.81L11.997 21l6.998-4.042v-.81M12.003 3 5.005 7.042l6.998 4.042L19 7.042 12.003 3Z"/></svg>
      <span class="text-[10px] font-medium leading-tight">Lager</span>
    </a>
    <?php endif; ?>
    <?php if ($isAdminOrTechniker && $sdShow('projekt')): ?>
    <a href="<?php echo BASE_URL; ?>projects/" class="<?php echo $dialBtnClass; ?>" title="Projekt anlegen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/></svg>
      <span class="text-[10px] font-medium leading-tight">Projekt</span>
    </a>
    <?php endif; ?>
    <?php if ($isAdminOrTechniker && $sdShow('aufgabe')): ?>
    <a href="<?php echo BASE_URL; ?>todos/" class="<?php echo $dialBtnClass; ?>" title="Aufgabe anlegen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
      <span class="text-[10px] font-medium leading-tight">Aufgabe</span>
    </a>
    <?php endif; ?>
    <?php if ($isNotKunde && $sdShow('bestellung')): ?>
    <a href="<?php echo BASE_URL; ?>orders/create.php" class="<?php echo $dialBtnClass; ?>" title="Bestellung anlegen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/></svg>
      <span class="text-[10px] font-medium leading-tight">Bestellung</span>
    </a>
    <?php endif; ?>
    <?php if ($canSeeLink && $sdShow('link')): ?>
    <a href="<?php echo BASE_URL; ?>links/" class="<?php echo $dialBtnClass; ?>" title="Verknüpfung hinzufügen">
      <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
      <span class="text-[10px] font-medium leading-tight">Link</span>
    </a>
    <?php endif; ?>
  </div>
  <button type="button" data-dial-toggle="speed-dial-menu-quick-add" aria-controls="speed-dial-menu-quick-add" aria-expanded="false" class="flex items-center justify-center text-white rounded-full w-14 h-14 bg-primary-250 hover:bg-primary-260 dark:bg-primary-280 dark:hover:bg-primary-270 focus:ring-4 focus:ring-primary-250/30 dark:focus:ring-primary-250/30 focus:outline-none shadow-lg transition-all group-hover:rotate-90" title="Schnell hinzufügen">
    <svg class="w-6 h-6 transition-transform" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7 7V5"/></svg>
    <span class="sr-only">Schnell hinzufügen öffnen</span>
  </button>
</div>
<?php endif;
