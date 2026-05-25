<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// BASE_URL definieren falls nicht vorhanden
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
// Config einbinden - prüfen ob bereits eingebunden
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config.php';
}

// Benachrichtigungen-Funktionen einbinden
require_once dirname(__DIR__) . '/notifications.php';
require_once dirname(__DIR__) . '/../companies/helper/encryption.php';

// Prüfen ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    // Ursprünglich aufgerufene URL speichern für Weiterleitung nach Login
    // REQUEST_URI enthält bereits den Query-String, falls vorhanden
    $returnUrl = $_SERVER['REQUEST_URI'] ?? '';
    // URL-encodieren für GET-Parameter
    $returnUrl = urlencode($returnUrl);
    header('Location: ' . BASE_URL . 'login/?return_url=' . $returnUrl);
    exit();
}

if (!isset($dashboardKeepMobileTopNav)) {
    $dashboardKeepMobileTopNav = false;
}

if (!isset($navMobileShowIntegratedFilter)) {
    $navMobileShowIntegratedFilter = false;
}
if (!isset($navMobileHideCompactCreateButton)) {
    $navMobileHideCompactCreateButton = false;
}
if (!isset($navMobileCompactShowLogoutButton)) {
    $navMobileCompactShowLogoutButton = false;
}
if (!isset($navMobileTodosQuickComposer)) {
    $navMobileTodosQuickComposer = false;
}
if (!isset($navMobileCompactTitle)) {
    $navMobileCompactTitle = null;
}
if (!isset($navMobileCompactBackUrl)) {
    $navMobileCompactBackUrl = null;
}
if (!isset($navMobileCompactBackLabel)) {
    $navMobileCompactBackLabel = 'Zurück';
}
if (!isset($navMobileInventorySearchToggle)) {
    $navMobileInventorySearchToggle = false;
}
if (!isset($navMobileTodosSearchToggle)) {
    $navMobileTodosSearchToggle = false;
}
if (!isset($navMobileTicketsSearchToggle)) {
    $navMobileTicketsSearchToggle = false;
}
if (!isset($ticketViewMobileTopNav)) {
    $ticketViewMobileTopNav = false;
}
if (!isset($navTicketViewDetailMobile)) {
    $navTicketViewDetailMobile = false;
}
if (!isset($navMobileInventoryDetailMobile)) {
    $navMobileInventoryDetailMobile = false;
}
if (!isset($navMobileInventoryDetailEditUrl)) {
    $navMobileInventoryDetailEditUrl = null;
}

$user_id = $_SESSION['user_id'] ?? null;
$row = null;

try {
    // Benutzerdaten abrufen inkl. Firmeninformationen
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.company_id, u.vorname, u.nachname, u.rolle, u.status, u.logopfad,
                   c.name as company_name, c.logo as company_logo
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            WHERE u.id = :user_id
            LIMIT 1
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Einzelne Zeile abrufen
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && array_key_exists('company_name', $row)) {
            $row['company_name'] = decrypt_from_db($row['company_name']);
        }
    }
    // Sidebar-Zustand aus Benutzereinstellungen (für Einstellung „Sidebar ein-/ausklappen“)
    $sidebarExpandedFromSettings = true;
    $sidebarExpandOnHoverFromSettings = false;
    try {
        $sStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'sidebar_expanded' LIMIT 1");
        $sStmt->execute([$user_id]);
        $sRow = $sStmt->fetch(PDO::FETCH_ASSOC);
        if ($sRow && $sRow['setting_value'] === 'false') {
            $sidebarExpandedFromSettings = false;
        }
        $sStmt2 = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'sidebar_expand_on_hover' LIMIT 1");
        $sStmt2->execute([$user_id]);
        $sRow2 = $sStmt2->fetch(PDO::FETCH_ASSOC);
        if ($sRow2 && $sRow2['setting_value'] === 'true') {
            $sidebarExpandOnHoverFromSettings = true;
        }
    } catch (PDOException $e) {}
} catch (PDOException $e) {
    // Tabelle existiert noch nicht oder Fehler - mit null weitermachen
    error_log("Nav Error: " . $e->getMessage());
    $row = null;
}
if (!isset($sidebarExpandedFromSettings)) {
    $sidebarExpandedFromSettings = true;
}
if (!isset($sidebarExpandOnHoverFromSettings)) {
    $sidebarExpandOnHoverFromSettings = false;
}

if (isset($_GET['logout'])) {
    header('Location: ' . BASE_URL . 'logout.php');
    exit();
}

// Hilfsfunktion für Logo-URLs
if (!function_exists('getLogoUrl')) {
    function getLogoUrl($logo) {
        if (empty($logo)) {
            return BASE_URL . 'assets/images/default-avatar.png';
        }
        // Wenn bereits absolute URL
        if (strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0) {
            return $logo;
        }
        // Relative Pfade mit BASE_URL kombinieren
        return BASE_URL . ltrim($logo, '/');
    }
}

// Hilfsfunktion für Benutzer-Profilbild
function getUserAvatarUrl($logopfad) {
    if (empty($logopfad)) {
        return BASE_URL . 'assets/images/default-avatar.png';
    }
    // Wenn bereits absolute URL
    if (strpos($logopfad, 'http://') === 0 || strpos($logopfad, 'https://') === 0) {
        return $logopfad;
    }
    // Relative Pfade mit BASE_URL kombinieren
    return BASE_URL . ltrim($logopfad, '/');
}

// Hilfsfunktion zum Rendern eines Avatar-Elements (unterstützt Preset-Avatare)
function renderUserAvatar($logopfad, $vorname = '', $nachname = '', $email = '', $classes = 'h-8 w-8 rounded-full object-cover', $alt = 'user photo') {
    // Initialen generieren
    $initials = '';
    if (!empty($vorname) && !empty($nachname)) {
        $initials = strtoupper(substr($vorname, 0, 1) . substr($nachname, 0, 1));
    } elseif (!empty($email)) {
        $initials = strtoupper(substr($email, 0, 1));
    } else {
        $initials = 'U';
    }
    
    // Prüfen ob es ein Preset-Avatar ist (Format: preset:color:initials)
    if (!empty($logopfad) && strpos($logopfad, 'preset:') === 0) {
        $parts = explode(':', $logopfad);
        $color = isset($parts[1]) ? '#' . ltrim($parts[1], '#') : '#3b82f6';
        $presetInitials = isset($parts[2]) ? $parts[2] : $initials;
        
        // Preset-Avatar als DIV mit Initialen rendern
        return sprintf(
            '<div class="%s flex items-center justify-center text-white text-sm font-bold shrink-0" style="background-color: %s;" title="%s">%s</div>',
            htmlspecialchars($classes),
            htmlspecialchars($color),
            htmlspecialchars($alt),
            htmlspecialchars($presetInitials)
        );
    }
    
    // Normales Bild-Avatar
    $avatarUrl = getUserAvatarUrl($logopfad);
    return sprintf(
        '<img class="%s shrink-0" src="%s" alt="%s" onerror="this.onerror=null; this.src=\'%sassets/images/default-avatar.png\';">',
        htmlspecialchars($classes),
        htmlspecialchars($avatarUrl),
        htmlspecialchars($alt),
        BASE_URL
    );
}

/** Badge-Label für Nav-Glocke (Vorlage: „9+“ ab 10). */
function formatNavNotificationBadgeLabel($count) {
    $n = (int) $count;
    if ($n <= 0) {
        return '';
    }
    if ($n > 99) {
        return '99+';
    }
    if ($n > 9) {
        return '9+';
    }
    return (string) $n;
}

// Hilfsfunktion zum Ermitteln des Seitennamens basierend auf dem aktuellen Pfad
function getCurrentPageName() {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $currentPath = str_replace(BASE_URL, '', $currentPath);
    $currentPath = trim($currentPath, '/');
    
    // Query-String entfernen
    if (strpos($currentPath, '?') !== false) {
        $currentPath = explode('?', $currentPath)[0];
    }
    
    // Pfad in Teile aufteilen
    $pathParts = explode('/', $currentPath);
    $firstPart = $pathParts[0] ?? '';
    
    // Mapping von Pfaden zu Seitennamen
    $pageNames = [
        '' => 'Dashboard',
        'dashboard' => 'Dashboard',
        'tickets' => 'Tickets',
        'service' => 'Tickets',
        'todos' => 'Aufgaben',
        'devices' => 'Geräte',
        'orders' => 'Bestellungen',
        'companies' => 'Firmen',
        'customers' => 'Kunden',
        'notifications' => 'Benachrichtigungen',
        'account' => 'Account',
        'settings' => 'Einstellungen',
        'admin' => 'Admin',
        'jobs' => 'Jobs',
        'packages' => 'Pakete',
        'stats' => 'Statistiken',
        'knowledge' => 'Wissensdatenbank',
        'projects' => 'Projekte',
        'inventory' => 'Lager',
        'kalender' => 'Kalender',
        'links' => 'Links',
        'help' => 'Hilfe',
        'search' => 'Suche',
        'notes' => 'Notizen',
        'calls' => 'Anrufe',
        'time-tracking' => 'Zeiterfassung',
        'gimmick' => 'Gimmick',
        'easy' => 'Easy',
    ];
    
    // Wenn es eine Unterseite gibt (z.B. tickets/view.php), versuche den Seitennamen zu bestimmen
    if (count($pathParts) > 1) {
        $secondPart = $pathParts[1] ?? '';
        // Entferne .php Extension
        $secondPart = str_replace('.php', '', $secondPart);
        
        // Spezielle Unterseiten
        if (($firstPart === 'tickets' || $firstPart === 'service') && $secondPart === 'view') {
            return 'Ticket Details';
        } elseif (($firstPart === 'tickets' || $firstPart === 'service') && $secondPart === 'create') {
            return 'Ticket erstellen';
        } elseif ($firstPart === 'todos' && $secondPart === 'view') {
            return 'Aufgabe Details';
        } elseif ($firstPart === 'orders' && $secondPart === 'detail') {
            return 'Bestellung Details';
        } elseif ($firstPart === 'devices' && $secondPart === 'view') {
            return 'Gerät Details';
        } elseif ($firstPart === 'customers' && $secondPart === 'view') {
            return 'Kunde Details';
        } elseif ($firstPart === 'companies' && $secondPart === 'view') {
            return 'Firma Details';
        } elseif ($firstPart === 'knowledge' && $secondPart === 'view') {
            return 'Artikel';
        } elseif ($firstPart === 'knowledge' && $secondPart === 'edit') {
            return 'Artikel bearbeiten';
        } elseif ($firstPart === 'projects' && $secondPart === 'view') {
            return 'Projekt Details';
        } elseif ($firstPart === 'inventory' && $secondPart === 'create') {
            return 'Artikel anlegen';
        } elseif ($firstPart === 'inventory' && $secondPart === 'edit') {
            return 'Artikel bearbeiten';
        } elseif ($firstPart === 'inventory' && $secondPart === 'detail') {
            return 'Artikeldetails';
        } elseif ($firstPart === 'inventory' && $secondPart === 'tablet') {
            return 'Lager Tablet';
        } elseif ($secondPart === 'create') {
            $base = $pageNames[$firstPart] ?? ucfirst(str_replace('-', ' ', $firstPart));
            return rtrim($base) . ' anlegen';
        } elseif ($secondPart === 'edit') {
            $base = $pageNames[$firstPart] ?? ucfirst(str_replace('-', ' ', $firstPart));
            return rtrim($base) . ' bearbeiten';
        } elseif ($secondPart === 'detail') {
            $base = $pageNames[$firstPart] ?? ucfirst(str_replace('-', ' ', $firstPart));
            return rtrim($base) . ' Details';
        }
    }
    
    // Standard-Seitennamen zurückgeben
    $label = $pageNames[$firstPart] ?? ucfirst(str_replace('-', ' ', $firstPart));
    return $label !== '' ? $label : 'Dashboard';
}

/** Mobile Top-Leiste (nicht Dashboard): Plus → passende create.php; null wenn keine. */
function getNavMobileCreateUrl($userRole) {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $currentPath = str_replace(BASE_URL, '', $currentPath);
    $currentPath = trim($currentPath, '/');
    if (strpos($currentPath, '?') !== false) {
        $currentPath = explode('?', $currentPath)[0];
    }
    $pathParts = explode('/', $currentPath);
    $firstPart = $pathParts[0] ?? '';
    $secondPart = isset($pathParts[1]) ? str_replace('.php', '', $pathParts[1]) : '';
    if ($secondPart === 'create') {
        return null;
    }
    switch ($firstPart) {
        case 'tickets':
            return BASE_URL . 'tickets/create.php';
        case 'service':
            return BASE_URL . 'service/create.php';
        case 'orders':
            if ($userRole === 'Kunde') {
                return null;
            }
            return BASE_URL . 'orders/create.php';
        case 'devices':
            return BASE_URL . 'devices/create.php';
        case 'customers':
            if (!in_array($userRole, ['Admin', 'Techniker', 'Firmen-Admin'], true)) {
                return null;
            }
            return BASE_URL . 'customers/create.php';
        case 'companies':
            if (!in_array($userRole, ['Admin', 'Techniker'], true)) {
                return null;
            }
            return BASE_URL . 'companies/create.php';
        case 'inventory':
            if (!in_array($userRole, ['Admin', 'Techniker'], true)) {
                return null;
            }
            if ($secondPart === 'tablet') {
                return null;
            }
            return BASE_URL . 'inventory/tablet.php';
        default:
            return null;
    }
}

$navMobileCreateUrl = getNavMobileCreateUrl($row['rolle'] ?? '');
$navMobileUseCompactTop = (empty($serviceMobileFullscreen) && empty($dashboardKeepMobileTopNav)) || !empty($ticketViewMobileTopNav);

$navShowTicketCreateButton = true;
$navTicketCreatePath = $_SERVER['REQUEST_URI'] ?? '';
$navTicketCreatePath = trim(str_replace(BASE_URL, '', strtok($navTicketCreatePath, '?')));
if (preg_match('#^(tickets|service)/create(\.php)?$#', $navTicketCreatePath)) {
    $navShowTicketCreateButton = false;
}

?>

   
<body class="bg-gray-50 dark:bg-primary-50 antialiased sidebar-layout-pending<?php echo !empty($serviceMobileFullscreen) ? ' service-mobile-fullscreen' : ''; ?><?php echo (!empty($dashboardKeepMobileTopNav) && empty($serviceMobileFullscreen)) ? ' app-mobile-dashboard-shell' : ''; ?><?php echo empty($serviceMobileFullscreen) ? ' app-mobile-bottom-nav' : ''; ?><?php echo !empty($ticketViewMobileTopNav) ? ' ticket-view-mobile-shell' : ''; ?>" data-sidebar-expanded="<?php echo !empty($sidebarExpandedFromSettings) ? 'true' : 'false'; ?>" data-scroll-hide-mobile-topnav="0">
<style id="sidebar-initial-state">
:root {
  --sidebar-nav-item-width: 14rem;
  --sidebar-expanded-width: 15.5rem;
}
/* Mobile: Sidebar immer vollbreit mit allen Texten sichtbar, wenn geöffnet */
@media (max-width: 1023px) {
  /* FOUC-Schutz: Sidebar auf Mobile nur sichtbar, wenn sie explizit geöffnet ist */
  #sidebar.-translate-x-full {
    transform: translate3d(-100%, 0, 0) !important;
    visibility: hidden !important;
  }
  #sidebar:not(.-translate-x-full) {
    transform: translate3d(0, 0, 0) !important;
    visibility: visible !important;
  }
  #sidebar:not(.-translate-x-full) { width: var(--sidebar-expanded-width, 15.5rem) !important; }
  #sidebar:not(.-translate-x-full) [data-sidebar-collapse-hide] { display: revert !important; }
}
/* Verhindert Flackern: Sidebar- und Main-Content-Zustand schon beim ersten Paint setzen (lg+) */
@media (min-width: 1024px) {
  body.sidebar-layout-pending #sidebar {
    transition: none !important;
  }
  .sidebar-w-expanded {
    width: var(--sidebar-expanded-width) !important;
  }
  body[data-sidebar-expanded="false"] #sidebar { width: 4rem !important; }
  body[data-sidebar-expanded="true"] #sidebar { width: var(--sidebar-expanded-width) !important; }
  body[data-sidebar-expanded="false"] #main-content { margin-left: 4rem !important; }
  body[data-sidebar-expanded="true"] #main-content { margin-left: var(--sidebar-expanded-width) !important; }
  body[data-sidebar-expanded="false"] [data-sidebar-collapse-hide] { display: none !important; }
  body[data-sidebar-expanded="true"] [data-sidebar-collapse-hide] { display: revert !important; }
  /* Hover-Erweiterung: überschreibt data-sidebar-expanded wenn Maus über Sidebar (eingeklappt + Einstellung an) */
  body.sidebar-hover-expanded #sidebar { width: var(--sidebar-expanded-width) !important; }
  body.sidebar-hover-expanded #main-content { margin-left: var(--sidebar-expanded-width) !important; }
  /* Nav-Punkte = gleiche Breite wie Firmenfilter in der Top-Nav */
  /* :is([href], [data-sv-nav-href]) — href wird beim Hover von sidebar-nav-status.js kurz entfernt */
  body[data-sidebar-expanded="true"] #sidebar .sidebar-nav-scroll a:is([href], [data-sv-nav-href]),
  body.sidebar-hover-expanded #sidebar .sidebar-nav-scroll a:is([href], [data-sv-nav-href]) {
    width: var(--sidebar-nav-item-width);
    max-width: var(--sidebar-nav-item-width);
    box-sizing: border-box;
  }
  body.sidebar-hover-expanded [data-sidebar-collapse-hide] { display: revert !important; }
  /* Eingeklappt: Zähler als kleines Eck-Badge am Icon (kein Überlauf bei 4rem Breite) */
  body[data-sidebar-expanded="false"]:not(.sidebar-hover-expanded) #sidebar .sidebar-nav-scroll {
    overflow-x: hidden;
  }
  body[data-sidebar-expanded="false"]:not(.sidebar-hover-expanded) #sidebar a:has(.sidebar-count-badge) {
    position: relative;
    justify-content: center;
  }
  body[data-sidebar-expanded="false"]:not(.sidebar-hover-expanded) #sidebar a:has(.sidebar-count-badge) .sidebar-count-badge {
    position: absolute;
    top: 0;
    right: 0;
    margin: 0 !important;
    min-width: 0.875rem;
    max-width: 1.125rem;
    height: 0.875rem;
    padding: 0 0.15rem;
    font-size: 0.5625rem;
    line-height: 0.875rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #7c3aed;
    background: #ede9fe;
    border: none;
    border-radius: 9999px;
  }
  html.dark body[data-sidebar-expanded="false"]:not(.sidebar-hover-expanded) #sidebar a:has(.sidebar-count-badge) .sidebar-count-badge {
    color: #c4b5fd;
    background: rgb(76 29 149 / 0.4);
  }
}
/* Mobile: Nav beim Runterscrollen ausblenden, beim Hochscrollen einblenden (nicht auf Dashboard) */
@media (max-width: 1023px) {
  /*
   * viewport-fit=cover + Statusleiste: feste Top-Nav beginnt unter der Safe Area (sonst „verschwindet“
   * sie optisch unter der Notch bzw. ohne Abstand im Web-App-Modus).
   */
  /*
   * Handy-App: Inhalt immer Hellmodus (html ohne .dark), oberste Leiste immer schwarz.
   */
  #main-nav {
    top: env(safe-area-inset-top, 0px) !important;
    isolation: isolate;
    transition: transform 0.25s ease-out;
    border-top: none !important;
    border-bottom: none !important;
    background-color: #000000 !important;
    box-shadow: none !important;
    /* Unten eckig; die „nach unten“ öffnende Rundung sitzt am Inhalt (#main-content, border-top-*) */
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    color: #e5e7eb;
  }
  /*
   * Inhaltsfläche: obere Rundung sichtbar nur mit Kontrast zur Umgebung.
   * Vorlage: schwarzer „Rahmen“, helle Fläche mit nach unten geöffnetem Bogen (border-top-radius).
   */
  body.app-mobile-bottom-nav:not(.service-mobile-fullscreen) {
    background-color: #000000;
  }
  body.app-mobile-bottom-nav:not(.service-mobile-fullscreen) .app-layout-shell {
    background-color: transparent !important;
  }
  body.app-mobile-bottom-nav:not(.service-mobile-fullscreen) #main-content {
    border-top-left-radius: 1.75rem;
    border-top-right-radius: 1.75rem;
    background-color: var(--page-bg, #F8F9FC) !important;
    width: 100% !important;
    max-width: none;
    margin-left: 0 !important;
    margin-right: 0 !important;
    box-sizing: border-box;
  }
  /*
   * Nicht-Dashboard: Seite nicht am body scrollen — nur #main-content scrollt, damit die obere Rundung
   * unter der fixen Nav stehen bleibt (sonst scrollt die Kante mit).
   */
  body.app-mobile-bottom-nav:not(.app-mobile-dashboard-shell):not(.service-mobile-fullscreen) {
    min-height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  body.app-mobile-bottom-nav:not(.app-mobile-dashboard-shell):not(.service-mobile-fullscreen) .app-layout-shell {
    flex: 1 1 0%;
    min-height: 0;
    display: flex !important;
    flex-direction: row;
    align-items: stretch;
    overflow: hidden !important;
    padding-top: calc(3.5rem + env(safe-area-inset-top, 0px));
    box-sizing: border-box;
    width: 100%;
  }
  body.app-mobile-bottom-nav:not(.app-mobile-dashboard-shell):not(.service-mobile-fullscreen) #main-content {
    flex: 1 1 0%;
    min-width: 0;
    min-height: 0;
    overflow-x: hidden !important;
    /* Gegen max-lg:overflow-y-visible (z. B. Aufgaben) — sonst kein Scroll bei body: overflow hidden */
    overflow-y: auto !important;
    /* Gegen max-lg:flex auf #main-content: flex-Kind + overflow-hidden auf <main> verhindert sonst Scroll */
    display: block !important;
    -webkit-overflow-scrolling: touch;
    touch-action: pan-y;
    overscroll-behavior-y: contain;
    margin-top: 0 !important;
    padding-top: 1rem !important;
  }
  body.service-mobile-fullscreen #main-content {
    border-top-left-radius: 0 !important;
    border-top-right-radius: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    background-color: transparent !important;
  }
  #main-nav .text-gray-900,
  #main-nav .text-gray-800,
  #main-nav .text-gray-700,
  #main-nav .text-gray-600,
  #main-nav .text-gray-500 {
    color: #e5e7eb !important;
  }
  #main-nav .text-primary-600 {
    color: #ffffff !important;
  }
  #main-nav a:hover .text-gray-800,
  #main-nav a:hover .text-gray-500 {
    color: #ffffff !important;
  }
  #main-nav .hover\:bg-gray-100:hover,
  #main-nav .hover\:bg-gray-50:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
  }
  #main-nav .hover\:text-gray-900:hover {
    color: #ffffff !important;
  }
  /* Dashboard Mobil: natives <select> — optisch an schwarze Leiste angepasst, Auswahl = System-UI */
  #main-nav #navCompanySelectMobile {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    font-size: 1.0625rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    min-height: 2.75rem;
    padding: 0.52rem 0.55rem 0.52rem 2.9rem;
    color: #f3f4f6 !important;
    background-color: transparent !important;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background-image: none !important;
    transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
  }
  #main-nav #navCompanySelectMobile:hover {
    background-color: transparent !important;
  }
  #main-nav #nav-company-select-mobile-wrap .min-w-0.flex-1 {
    position: relative;
  }
  #main-nav #nav-company-select-mobile-wrap {
    margin-left: -0.35rem;
    margin-right: -0.35rem;
    flex: 1 1 auto;
  }
  body.app-mobile-dashboard-shell #main-nav > div {
    padding-left: 0.65rem;
    padding-right: 0.65rem;
    gap: 0.35rem;
  }
  body.app-mobile-dashboard-shell #main-nav .nav-mobile-bar-left {
    flex: 1 1 auto;
    min-width: 0;
  }
  body.app-mobile-dashboard-shell #main-nav .nav-mobile-bar-right {
    flex: 0 0 auto !important;
    min-width: auto;
    gap: 0.15rem;
  }
  body.app-mobile-dashboard-shell #main-nav #nav-company-select-mobile-wrap {
    margin-left: -0.15rem;
    margin-right: -0.15rem;
    width: 100%;
  }
  #main-nav #navCompanySelectMobileAvatar {
    left: 0.35rem;
    top: 50%;
    transform: translateY(-50%);
    border-radius: 0.5rem !important;
  }
  #main-nav #navCompanySelectMobile:focus {
    outline: none !important;
    box-shadow: none !important;
  }
  #main-nav #navCompanySelectMobile option {
    background-color: #262626;
    color: #f3f4f6;
  }
  #main-nav #navCompanySelectMobile,
  #main-nav #navCompanySelectMobileMoreBtn {
    touch-action: pan-y;
  }
  #main-nav #navCompanySelectMobileMoreBtn {
    border: 1px solid rgba(255, 255, 255, 0.16) !important;
    background-color: rgba(255, 255, 255, 0.08) !important;
    color: #f3f4f6 !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
    transition: background-color 0.15s ease, border-color 0.15s ease;
  }
  #main-nav #navCompanySelectMobileMoreBtn:hover {
    background-color: rgba(255, 255, 255, 0.14) !important;
    border-color: rgba(255, 255, 255, 0.28) !important;
  }
  #main-nav #navCompanySelectMobileMoreBtn:focus-visible {
    outline: none !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 0 0 2px rgba(255, 255, 255, 0.2) !important;
  }
  #navTicketViewStatusBadge {
    color: #4b5563 !important;
    background-color: #f3f4f6 !important;
  }
  /* Dropdowns/Modals in der Nav (heller Hintergrund): keine hellgraue „Bar“-Typografie */
  #main-nav .bg-white .text-gray-900 { color: #111827 !important; }
  #main-nav .bg-white .text-gray-700 { color: #374151 !important; }
  #main-nav .bg-white .text-gray-600 { color: #4b5563 !important; }
  #main-nav .bg-white .text-gray-500 { color: #6b7280 !important; }
  #main-nav .bg-white .text-gray-400 { color: #9ca3af !important; }
  /* Tickets: mobiler Filter ausgeklappt – kein unterer Nav-Rand; Nav über Filter-Panel (z), Panel schiebt beim Hochwischen darunter */
  #main-nav.main-nav-mobile-filter-open {
    z-index: 75 !important;
    border-bottom: none !important;
    box-shadow: none !important;
  }
  #main-nav.nav-hidden-mobile { transform: translateY(-100%); }
  /* Sidebar: Inhalt nicht unter die Statusleiste (Notch). */
  /* Offenes Sidebar-Menü: erster Inhalt nicht unter der Statusleiste */
  #sidebar .sidebar-nav-scroll {
    padding-top: calc(1rem + env(safe-area-inset-top, 0px));
  }
  /*
   * Opt-in: #main-content.app-mobile-no-root-overscroll — kein Scroll-Chaining zum Dokument,
   * natives Overscroll (Gummiband) bleibt auf dem Scroll-Container (contain).
   */
  html:has(#main-content.app-mobile-no-root-overscroll),
  body:has(#main-content.app-mobile-no-root-overscroll) {
    overscroll-behavior-y: contain;
  }
  body.app-mobile-bottom-nav:not(.app-mobile-dashboard-shell):not(.service-mobile-fullscreen) #main-content.app-mobile-no-root-overscroll {
    overscroll-behavior-y: contain;
  }
}
/* Top-Nav: Erstellen, Glocke, Profil — display per Tailwind (flex / max-lg:hidden lg:flex), sonst überschreibt flex max-lg:hidden */
.nav-top-actions {
  align-items: center;
  gap: 0.75rem;
}
@media (min-width: 768px) {
  .nav-top-actions {
    gap: 1rem;
  }
}
/* Zeiterfassung + Glocke enger gruppiert; Abstand zur Profilkugel bleibt über .nav-top-actions */
.nav-top-actions__cluster {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  gap: 0.25rem;
}
@media (min-width: 768px) {
  .nav-top-actions__cluster {
    gap: 0.375rem;
  }
}
.nav-top-actions__icon {
  -webkit-tap-highlight-color: transparent;
}
/* Laufzeit kompakt im Icon (feste 36×36px), kein Layout-Shift in der Nav-Zeile */
.nav-time-track-btn--active .nav-time-track-icon {
  width: 1.125rem;
  height: 1.125rem;
  transform: translateY(-2px);
}
.nav-time-track-elapsed {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 1px;
  text-align: center;
  font-size: 9px;
  line-height: 1;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  pointer-events: none;
}
.nav-top-actions__badge {
  position: absolute;
  top: -0.125rem;
  right: -0.125rem;
  display: inline-flex;
  min-height: 1.125rem;
  min-width: 1.125rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background-color: #dc2626;
  padding: 0 0.3rem;
  font-size: 0.625rem;
  font-weight: 700;
  line-height: 1;
  color: #fff;
}
.nav-top-actions__create {
  background-color: #f2f2f2;
}
.nav-top-actions__create:hover {
  background-color: #e8e8e8;
}
html.dark .nav-top-actions__create {
  background-color: var(--app-chrome-surface-muted, #2a2a2a);
}
html.dark .nav-top-actions__create:hover {
  background-color: var(--app-chrome-surface-hover, #363636);
}
/* Globale Suche (Vorlage: Pill + Lupe) */
.nav-global-search {
  width: 100%;
}
.nav-global-search__bar {
  border: 1px solid #e5e7eb;
  border-radius: 9999px;
  background: #fff;
  overflow: hidden;
}
html.dark .nav-global-search__bar {
  border-color: var(--app-chrome-border, #404040);
  background: var(--app-chrome-surface, #1f1f1f);
}
.nav-global-search__input {
  flex: 1 1 auto;
  min-width: 0;
  border: 0;
  background: transparent;
  padding: 0.5625rem 0.75rem 0.5625rem 1rem;
  font-size: 0.9375rem;
  line-height: 1.3125rem;
  font-weight: 500;
  color: #0f172a;
  outline: none;
  box-shadow: none;
}
.nav-global-search__input::placeholder {
  color: #64748b;
}
html.dark .nav-global-search__input {
  color: #e5e7eb;
}
html.dark .nav-global-search__input::placeholder {
  color: #94a3b8;
}
.nav-global-search__input:focus {
  outline: none;
  box-shadow: none;
}
.nav-global-search__input::-webkit-search-cancel-button,
.nav-global-search__input::-webkit-search-decoration {
  -webkit-appearance: none;
  appearance: none;
  display: none;
}
.nav-global-search__icon-stack {
  position: relative;
  width: 1.125rem;
  height: 1.125rem;
  flex-shrink: 0;
}
.nav-global-search__icon {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: opacity 0.24s cubic-bezier(0.4, 0, 0.2, 1),
    transform 0.24s cubic-bezier(0.34, 1.2, 0.64, 1);
  will-change: opacity, transform;
}
.nav-global-search__icon svg {
  width: 1.125rem;
  height: 1.125rem;
}
.nav-global-search__icon--search {
  opacity: 1;
  transform: scale(1) rotate(0deg);
}
.nav-global-search__icon--clear {
  opacity: 0;
  transform: scale(0.55) rotate(-72deg);
  pointer-events: none;
}
.nav-global-search__submit--has-value .nav-global-search__icon--search {
  opacity: 0;
  transform: scale(0.55) rotate(72deg);
  pointer-events: none;
}
.nav-global-search__submit--has-value .nav-global-search__icon--clear {
  opacity: 1;
  transform: scale(1) rotate(0deg);
  pointer-events: auto;
}
.nav-global-search__submit-wrap {
  display: flex;
  align-items: stretch;
  flex-shrink: 0;
  border-left: 1px solid #e5e7eb;
}
html.dark .nav-global-search__submit-wrap {
  border-left-color: var(--app-chrome-border, #404040);
}
.nav-global-search__submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding: 0 0.8125rem;
  border: 0;
  background-color: #f2f2f2;
  color: #111827;
  transition: background-color 0.15s ease;
}
.nav-global-search__submit:hover {
  background-color: #e8e8e8;
}
html.dark .nav-global-search__submit {
  background-color: var(--app-chrome-surface-muted, #2a2a2a);
  color: #fff;
}
html.dark .nav-global-search__submit:hover {
  background-color: var(--app-chrome-surface-hover, #363636);
}
/* Desktop: Benachrichtigungs-Dropdown (md+, außerhalb des Mobile-Blocks) */
@media (min-width: 768px) {
  #nav-notification-dropdown-wrap {
    position: relative;
    flex-shrink: 0;
  }
  #notification-dropdown.nav-notification-panel {
    position: absolute;
    top: 100%;
    right: -2rem;
    z-index: 60;
    width: 32rem;
    max-width: min(32rem, calc(100vw - 1.5rem));
    margin-top: 0.5rem !important;
    padding: 0;
    list-style: none;
    border: 1px solid var(--app-chrome-border, #e5e7eb);
    border-radius: 1rem;
    background: #ffffff;
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.07),
      0 20px 40px -12px rgba(0, 0, 0, 0.14);
    overflow: hidden;
    display: none;
    flex-direction: column;
    box-sizing: border-box;
  }
  #notification-dropdown.nav-notification-panel:not(.hidden) {
    display: flex;
  }
  html.dark #notification-dropdown.nav-notification-panel {
    background: rgb(var(--color-primary-100, 41 41 41) / 1);
    border-color: var(--app-chrome-border, #374151);
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.35),
      0 20px 40px -12px rgba(0, 0, 0, 0.5);
  }
  .nav-notification-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1rem 0.75rem;
    border-bottom: 1px solid var(--app-chrome-border, #e5e7eb);
    flex-shrink: 0;
  }
  html.dark .nav-notification-panel__header {
    border-bottom-color: var(--app-chrome-border, #374151);
  }
  .nav-notification-panel__title {
    font-size: 0.9375rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: #111827;
    line-height: 1.3;
  }
  html.dark .nav-notification-panel__title {
    color: rgb(var(--color-primary-200, 229 231 235) / 1);
  }
  .nav-notification-panel__count-wrap {
    position: relative;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    height: 1.5rem;
  }
  .nav-notification-panel__count-wrap[hidden] {
    display: none;
  }
  .nav-notification-panel__count {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    padding: 0 0.375rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #ffffff;
    background: #dc2626;
    border-radius: 9999px;
    box-sizing: border-box;
    transition: opacity 0.15s ease;
  }
  .nav-notification-panel__mark-all-read {
    position: absolute;
    inset: 0;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    padding: 0;
    border: none;
    border-radius: 9999px;
    background: #e5e7eb;
    color: #374151;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.15s ease, background-color 0.15s ease, color 0.15s ease;
  }
  .nav-notification-panel__mark-all-read svg {
    width: 0.875rem;
    height: 0.875rem;
    flex-shrink: 0;
  }
  .nav-notification-panel__mark-all-read:hover {
    background: #d1d5db;
    color: #111827;
  }
  html.dark .nav-notification-panel__mark-all-read {
    background: rgb(var(--color-primary-140, 55 65 81) / 1);
    color: rgb(var(--color-primary-200, 229 231 235) / 1);
  }
  html.dark .nav-notification-panel__mark-all-read:hover {
    background: rgb(var(--color-primary-150, 75 85 99) / 1);
    color: #ffffff;
  }
  .nav-notification-panel__count-wrap:hover .nav-notification-panel__count,
  .nav-notification-panel__count-wrap:focus-within .nav-notification-panel__count {
    opacity: 0;
  }
  .nav-notification-panel__count-wrap:hover .nav-notification-panel__mark-all-read,
  .nav-notification-panel__count-wrap:focus-within .nav-notification-panel__mark-all-read {
    opacity: 1;
  }
  .nav-notification-panel__list-wrap {
    position: relative;
    flex: 0 1 auto;
    min-height: 0;
    max-height: min(21rem, calc(100vh - 10rem));
    overflow: hidden;
  }
  .nav-notification-panel__list-fade-bottom {
    pointer-events: none;
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    height: 1.75rem;
    opacity: 0;
    transition: opacity 0.2s ease;
    background: linear-gradient(
      to top,
      #ffffff 0%,
      rgba(255, 255, 255, 0) 100%
    );
  }
  html.dark .nav-notification-panel__list-fade-bottom {
    background: linear-gradient(
      to top,
      rgb(var(--color-primary-100, 41 41 41) / 1) 0%,
      rgba(41, 41, 41, 0) 100%
    );
  }
  .nav-notification-panel__list-fade-bottom.nav-notification-panel__list-fade--visible {
    opacity: 1;
  }
  .nav-notification-panel__list {
    max-height: min(21rem, calc(100vh - 10rem));
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0.375rem;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
    min-height: 0;
    box-sizing: border-box;
    -webkit-overflow-scrolling: touch;
  }
  .nav-notification-panel__list::-webkit-scrollbar {
    width: 6px;
  }
  .nav-notification-panel__list::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 9999px;
  }
  .nav-notification-panel__state {
    padding: 2rem 1rem;
    text-align: center;
    font-size: 0.8125rem;
    color: #6b7280;
  }
  html.dark .nav-notification-panel__state {
    color: rgb(var(--color-primary-220, 156 163 175) / 1);
  }
  .nav-notification-panel__state--error {
    color: #dc2626;
  }
  #notification-dropdown .nav-notification-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    width: 100%;
    box-sizing: border-box;
    padding: 0.625rem 0.75rem;
    margin-bottom: 0.125rem;
    border: none;
    border-radius: 0.625rem;
    background: transparent;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.15s ease;
  }
  #notification-dropdown .nav-notification-item:last-child {
    margin-bottom: 0;
  }
  #notification-dropdown .nav-notification-item:hover {
    background: #f3f4f6;
  }
  html.dark #notification-dropdown .nav-notification-item:hover {
    background: rgb(var(--color-primary-140, 55 65 81) / 0.65);
  }
  #notification-dropdown .nav-notification-item--read {
    opacity: 0.72;
  }
  #notification-dropdown .nav-notification-item--read:hover {
    opacity: 1;
  }
  #notification-dropdown .nav-notification-item__avatar {
    position: relative;
    flex-shrink: 0;
    width: 2.5rem;
    height: 2.5rem;
  }
  #notification-dropdown .nav-notification-item__avatar-media {
    position: absolute;
    inset: 0;
    transition: opacity 0.15s ease;
  }
  #notification-dropdown .nav-notification-item__avatar-media img,
  #notification-dropdown .nav-notification-item__avatar-media > div {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 9999px;
    border-width: 0;
  }
  #notification-dropdown .nav-notification-item__dismiss {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: none;
    border-radius: 9999px;
    background: #e5e7eb;
    color: #374151;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.15s ease, background-color 0.15s ease, color 0.15s ease;
  }
  #notification-dropdown .nav-notification-item__dismiss svg {
    width: 1.125rem;
    height: 1.125rem;
    flex-shrink: 0;
  }
  #notification-dropdown .nav-notification-item__dismiss:hover {
    background: #d1d5db;
    color: #111827;
  }
  html.dark #notification-dropdown .nav-notification-item__dismiss {
    background: rgb(var(--color-primary-140, 55 65 81) / 1);
    color: rgb(var(--color-primary-200, 229 231 235) / 1);
  }
  html.dark #notification-dropdown .nav-notification-item__dismiss:hover {
    background: rgb(var(--color-primary-150, 75 85 99) / 1);
    color: #ffffff;
  }
  #notification-dropdown .nav-notification-item__avatar:hover .nav-notification-item__avatar-media,
  #notification-dropdown .nav-notification-item__avatar:focus-within .nav-notification-item__avatar-media {
    opacity: 0;
  }
  #notification-dropdown .nav-notification-item__avatar:hover .nav-notification-item__dismiss,
  #notification-dropdown .nav-notification-item__avatar:focus-within .nav-notification-item__dismiss {
    opacity: 1;
  }
  #notification-dropdown .nav-notification-item__body {
    min-width: 0;
    flex: 1;
    padding-top: 0.0625rem;
  }
  #notification-dropdown .nav-notification-item__line1 {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #111827;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  html.dark #notification-dropdown .nav-notification-item__line1 {
    color: rgb(var(--color-primary-200, 229 231 235) / 1);
  }
  #notification-dropdown .nav-notification-item__meta {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    margin-top: 0.25rem;
    min-width: 0;
    font-size: 0.75rem;
    line-height: 1.35;
    color: #9ca3af;
  }
  html.dark #notification-dropdown .nav-notification-item__meta {
    color: rgb(var(--color-primary-220, 156 163 175) / 1);
  }
  #notification-dropdown .nav-notification-item__time {
    flex-shrink: 0;
    font-weight: 500;
  }
  #notification-dropdown .nav-notification-item__sep {
    flex-shrink: 0;
    opacity: 0.65;
    user-select: none;
  }
  #notification-dropdown .nav-notification-item__author {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
    color: #6b7280;
  }
  html.dark #notification-dropdown .nav-notification-item__author {
    color: rgb(var(--color-primary-210, 117 120 124) / 1);
  }
  #nav-account-dropdown-wrap {
    position: relative;
    flex-shrink: 0;
  }
  #accountDropdown.nav-account-panel {
    position: absolute;
    top: 100%;
    right: -0.5rem;
    z-index: 60;
    width: 17.5rem;
    max-width: min(17.5rem, calc(100vw - 1.5rem));
    margin-top: 0.5rem !important;
    padding: 0;
    list-style: none;
    border: 1px solid var(--app-chrome-border, #e5e7eb);
    border-radius: 1rem;
    background: #ffffff;
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.07),
      0 20px 40px -12px rgba(0, 0, 0, 0.14);
    overflow: hidden;
    display: none;
    flex-direction: column;
    box-sizing: border-box;
  }
  #accountDropdown.nav-account-panel:not(.hidden) {
    display: flex;
  }
  html.dark #accountDropdown.nav-account-panel {
    background: rgb(var(--color-primary-100, 41 41 41) / 1);
    border-color: var(--app-chrome-border, #374151);
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.35),
      0 20px 40px -12px rgba(0, 0, 0, 0.5);
  }
  .nav-account-panel__header {
    padding: 1rem;
    border-bottom: 1px solid var(--app-chrome-border, #e5e7eb);
    flex-shrink: 0;
  }
  html.dark .nav-account-panel__header {
    border-bottom-color: var(--app-chrome-border, #374151);
  }
  .nav-account-panel__user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    text-decoration: none;
    color: inherit;
    border-radius: 0.625rem;
    transition: background-color 0.15s ease;
  }
  a.nav-account-panel__user:hover {
    background: #f3f4f6;
  }
  html.dark a.nav-account-panel__user:hover {
    background: rgb(var(--color-primary-140, 55 65 81) / 0.45);
  }
  .nav-account-panel__user img,
  .nav-account-panel__user > div {
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    border-radius: 9999px;
    object-fit: cover;
  }
  .nav-account-panel__name {
    font-size: 0.9375rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: #111827;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  html.dark .nav-account-panel__name {
    color: rgb(var(--color-primary-200, 229 231 235) / 1);
  }
  .nav-account-panel__company {
    margin-top: 0.125rem;
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  html.dark .nav-account-panel__company {
    color: rgb(var(--color-primary-220, 156 163 175) / 1);
  }
  .nav-account-panel__body {
    padding: 0.375rem;
    overflow-x: hidden;
    overflow-y: auto;
    max-height: min(24rem, calc(100vh - 8rem));
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
  }
  .nav-account-panel__body::-webkit-scrollbar {
    width: 6px;
  }
  .nav-account-panel__body::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 9999px;
  }
  .nav-account-panel__section + .nav-account-panel__section {
    margin-top: 0.25rem;
    padding-top: 0.25rem;
    border-top: 1px solid rgb(229 231 235);
  }
  html.dark .nav-account-panel__section + .nav-account-panel__section {
    border-top-color: rgb(var(--color-primary-230, 75 85 99) / 1);
  }
  .nav-account-panel__section-title {
    padding: 0.375rem 0.625rem 0.25rem;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9ca3af;
  }
  html.dark .nav-account-panel__section-title {
    color: rgb(var(--color-primary-220, 156 163 175) / 1);
  }
  .nav-account-panel__list {
    list-style: none;
    margin: 0;
    padding: 0;
  }
  .nav-account-panel__link,
  .nav-account-panel__row {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    box-sizing: border-box;
    padding: 0.5rem 0.625rem;
    border-radius: 0.625rem;
    text-decoration: none;
    transition: background-color 0.15s ease, color 0.15s ease;
  }
  .nav-account-panel__link {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #111827;
  }
  html.dark .nav-account-panel__link {
    color: rgb(var(--color-primary-200, 229 231 235) / 1);
  }
  .nav-account-panel__link:hover {
    background: #f3f4f6;
  }
  html.dark .nav-account-panel__link:hover {
    background: rgb(var(--color-primary-140, 55 65 81) / 0.65);
  }
  .nav-account-panel__link--active {
    background: #f3f4f6;
    font-weight: 600;
  }
  html.dark .nav-account-panel__link--active {
    background: rgb(var(--color-primary-140, 55 65 81) / 0.85);
  }
  .nav-account-panel__link--danger {
    color: #dc2626;
  }
  html.dark .nav-account-panel__link--danger {
    color: #f87171;
  }
  .nav-account-panel__link--danger:hover {
    background: #fef2f2;
  }
  html.dark .nav-account-panel__link--danger:hover {
    background: rgb(127 29 29 / 0.25);
  }
  .nav-account-panel__icon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    background: #f3f4f6;
    color: #4b5563;
  }
  html.dark .nav-account-panel__icon {
    background: rgb(var(--color-primary-140, 55 65 81) / 1);
    color: rgb(var(--color-primary-220, 156 163 175) / 1);
  }
  .nav-account-panel__link--active .nav-account-panel__icon,
  .nav-account-panel__link:hover .nav-account-panel__icon {
    background: #e5e7eb;
    color: #111827;
  }
  html.dark .nav-account-panel__link--active .nav-account-panel__icon,
  html.dark .nav-account-panel__link:hover .nav-account-panel__icon {
    background: rgb(var(--color-primary-150, 75 85 99) / 1);
    color: rgb(var(--color-primary-200, 229 231 235) / 1);
  }
  .nav-account-panel__link--danger .nav-account-panel__icon {
    background: #fef2f2;
    color: #dc2626;
  }
  html.dark .nav-account-panel__link--danger .nav-account-panel__icon {
    background: rgb(127 29 29 / 0.3);
    color: #f87171;
  }
  .nav-account-panel__icon svg {
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
  }
  .nav-account-panel__label {
    min-width: 0;
    flex: 1;
    line-height: 1.35;
  }
  .nav-account-panel__row {
    color: #111827;
    cursor: default;
  }
  html.dark .nav-account-panel__row {
    color: rgb(var(--color-primary-200, 229 231 235) / 1);
  }
  .nav-account-panel__row:hover {
    background: #f9fafb;
  }
  html.dark .nav-account-panel__row:hover {
    background: rgb(var(--color-primary-140, 55 65 81) / 0.4);
  }
  #navMobileProfilePopupPanel.nav-account-panel--mobile {
    padding: 0.375rem;
    border-radius: 1rem;
    border: 1px solid var(--app-chrome-border, #e5e7eb);
    background: #ffffff;
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.1),
      0 20px 40px -12px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  html.dark #navMobileProfilePopupPanel.nav-account-panel--mobile {
    background: rgb(var(--color-primary-100, 41 41 41) / 1);
    border-color: var(--app-chrome-border, #374151);
  }
  #navMobileProfilePopupPanel .nav-account-panel__header {
    padding: 0.375rem 0.375rem 0.5rem;
    border-bottom: none;
  }
  #navMobileProfilePopupPanel .nav-account-panel__link {
    padding: 0.625rem 0.75rem;
    font-size: 0.875rem;
  }
}
@media (min-width: 1024px) {
  #sidebar .sidebar-nav-scroll {
    position: relative;
    z-index: 1;
    padding-top: calc(var(--app-nav-sidebar-corner, 1.75rem) + 0.25rem);
  }
  #sidebar .sidebar-nav-bottom {
    position: sticky;
    bottom: 0;
    z-index: 2;
  }

  /*
   * Desktop: L-Chrome → Inhaltsfläche als Karte (große TL-Rundung + grauer Rand).
   */
  :root {
    --app-sidebar-width: 4rem;
  }
  body[data-sidebar-expanded="true"],
  body.sidebar-hover-expanded {
    --app-sidebar-width: var(--sidebar-expanded-width);
  }
  html {
    height: 100%;
  }
  body:not(.service-mobile-fullscreen) {
    height: 100%;
    overflow: hidden;
    background-color: var(--page-bg, #F8F9FC);
  }
  html.dark body:not(.service-mobile-fullscreen) {
    background-color: var(--page-bg, #090909);
  }
  #main-nav {
    z-index: 25;
    border-bottom-width: 0 !important;
    border-bottom-color: transparent !important;
    overflow: visible;
    box-shadow: none;
  }
  #sidebar::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: var(--app-nav-sidebar-corner, 1.75rem);
    height: var(--app-nav-sidebar-corner, 1.75rem);
    background-color: var(--app-chrome-bg, #ffffff);
    border-bottom-right-radius: var(--app-nav-sidebar-corner, 1.75rem);
    z-index: 0;
    pointer-events: none;
  }
  html.dark #sidebar::after {
    background-color: var(--app-chrome-bg, #1f1f1f);
  }
  .app-layout-shell {
    align-items: stretch;
    height: 100dvh;
    max-height: 100dvh;
    min-height: 0;
    overflow: hidden !important;
    box-sizing: border-box;
  }
  #main-content {
    position: relative;
    z-index: 1;
    box-sizing: border-box;
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0 !important;
    max-height: 100%;
    border: var(--app-content-border-width, 1px) solid var(--app-chrome-border, #e5e7eb) !important;
    border-top-left-radius: var(--app-nav-sidebar-corner, 1.75rem) !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
    background-color: var(--page-bg, #F8F9FC) !important;
    -webkit-background-clip: padding-box;
    background-clip: padding-box;
  }
  /* Tickets & Co.: Rahmen fix, Scroll im inneren main */
  #main-content:has(> main.service-main) {
    display: flex;
    flex-direction: column;
    overflow: hidden !important;
    overflow-y: hidden !important;
  }
  #main-content:has(> main.service-main) > main.service-main {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
    background-color: var(--page-bg, #F8F9FC);
  }
  html.dark #main-content:has(> main.service-main) > main.service-main {
    background-color: var(--page-bg, #090909);
  }
  body.service-chat-view-active #main-content.service-main-content {
    overflow: hidden !important;
    overflow-y: hidden !important;
  }
  /* Desktop: Inhaltsfläche mit Abstand zur Sidebar (links) und Navbar (oben) */
  #main-content > main {
    box-sizing: border-box;
    padding-top: var(--app-content-inset-top, 2.5rem);
    padding-left: var(--app-content-inset-start, 2.5rem);
    padding-right: var(--app-content-inset-end, 2.25rem);
  }
  #main-content > main.mx-4 {
    margin-left: 0 !important;
    margin-right: 0 !important;
  }
  #main-content > main.mt-2 {
    margin-top: 0 !important;
  }
  #main-content > main.pt-4 {
    padding-top: var(--app-content-inset-top, 2.5rem) !important;
  }
  #main-content > main.pl-0,
  #main-content > main.pl-1 {
    padding-left: var(--app-content-inset-start, 2.5rem) !important;
  }
  #main-content > main.pr-4 {
    padding-right: var(--app-content-inset-end, 2.25rem) !important;
  }
  #main-content nav[aria-label="Breadcrumb"],
  #main-content .app-content-header {
    margin-top: 0;
  }
  html:not(.dark) #sidebar .sidebar-nav-scroll {
    background-color: #ffffff;
  }
  /* Firmenfilter: schmale Nav (h-14), Hover mit Abstand oben/unten über Innenfläche */
  #main-nav:has(#nav-company-selector-desktop) {
    overflow: visible;
  }
  #main-nav:has(#nav-company-selector-desktop) > div.flex.w-full {
    padding-left: 0.75rem;
    overflow: visible;
  }
  #main-nav .nav-desktop-head {
    align-items: stretch;
    min-width: 0;
    max-width: 100%;
  }
  #nav-company-selector-desktop {
    width: var(--sidebar-nav-item-width);
    min-width: var(--sidebar-nav-item-width);
    max-width: var(--sidebar-nav-item-width);
    flex-shrink: 0;
    box-sizing: border-box;
  }
  #nav-company-selector-desktop .nav-company-filter {
    width: 100%;
    box-sizing: border-box;
    padding: 0;
    border: 0;
    background: transparent;
  }
  #nav-company-selector-desktop .nav-company-filter:hover,
  #nav-company-selector-desktop .nav-company-filter:focus {
    background: transparent;
  }
  #nav-company-selector-desktop .nav-company-filter-hit {
    box-sizing: border-box;
    gap: 0.5rem;
    padding: 0.375rem 0.5rem;
    margin-top: 0.5625rem;
    margin-bottom: 0.5625rem;
  }
  #nav-company-selector-desktop .nav-company-filter-icon {
    display: grid;
    place-items: center;
    width: 2.125rem;
    height: 2.125rem;
    font-size: 0.8125rem;
  }
  #nav-company-selector-desktop #selectedAvatar,
  #nav-company-selector-desktop #selectedAvatarFallback:not(.hidden) {
    grid-area: 1 / 1;
  }
  #nav-company-selector-desktop #selectedAvatarFallback.hidden {
    display: none !important;
  }
  #nav-company-selector-desktop #selectedAvatarFallback:not(.hidden) {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding-left: 0.125rem;
    line-height: 1;
  }
  #nav-company-selector-desktop #selectedAvatar.hidden {
    display: none !important;
  }
  #nav-company-selector-desktop #selectedOption {
    font-size: 0.8125rem;
    line-height: 1.15;
  }
  #nav-company-selector-desktop #selectedOptionSub {
    margin-top: 0.125rem;
    font-size: 0.6875rem;
    line-height: 1.15;
  }
  #nav-company-selector-desktop .nav-company-filter-avatar-wrap {
    overflow: visible;
  }
  #nav-company-selector-desktop #selectedCompanyStatusDot {
    z-index: 2;
    height: 0.75rem;
    width: 0.75rem;
  }
  #nav-company-selector-desktop #selectedCompanyStatusDot.hidden {
    display: none !important;
  }
  #nav-company-selector-desktop .nav-company-filter-chevron {
    width: 0.9375rem;
    height: 0.9375rem;
  }
}
/* Firmenfilter-Dropdown (Favoriten) */
#dropdownUserName.nav-company-dropdown {
  width: var(--sidebar-nav-item-width, 14rem);
  min-width: var(--sidebar-nav-item-width, 14rem);
  max-width: var(--sidebar-nav-item-width, 14rem);
  padding: 0.375rem;
  border-radius: 0.75rem;
  box-shadow: 0 10px 24px -4px rgb(0 0 0 / 0.12), 0 4px 8px -2px rgb(0 0 0 / 0.06);
}
.dark #dropdownUserName.nav-company-dropdown {
  box-shadow: 0 10px 24px -4px rgb(0 0 0 / 0.45), 0 4px 8px -2px rgb(0 0 0 / 0.25);
}
#dropdownUserName .nav-company-dropdown-list {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  max-height: min(18rem, calc(100vh - 6rem));
  overflow-x: hidden;
  overflow-y: auto;
}
#dropdownUserName .nav-company-dropdown-row {
  display: flex;
  align-items: stretch;
  gap: 0;
  min-width: 0;
  border-radius: 0.5rem;
  transition: background-color 0.15s ease;
}
#dropdownUserName .nav-company-dropdown-row:hover,
#dropdownUserName .nav-company-dropdown-row--active {
  background-color: rgb(243 244 246);
}
.dark #dropdownUserName .nav-company-dropdown-row:hover,
.dark #dropdownUserName .nav-company-dropdown-row--active {
  background-color: rgb(55 65 81);
}
#dropdownUserName .nav-company-dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  flex: 1;
  padding: 0.4375rem 0.25rem 0.4375rem 0.5rem;
  border-radius: 0.5rem;
  text-decoration: none;
  transition: color 0.15s ease;
}
#dropdownUserName .nav-company-dropdown-list > .nav-company-dropdown-item:hover,
#dropdownUserName .nav-company-dropdown-list > .nav-company-dropdown-item--active {
  background-color: rgb(243 244 246);
}
.dark #dropdownUserName .nav-company-dropdown-list > .nav-company-dropdown-item:hover,
.dark #dropdownUserName .nav-company-dropdown-list > .nav-company-dropdown-item--active {
  background-color: rgb(55 65 81);
}
#dropdownUserName .nav-company-dropdown-row .nav-company-dropdown-item:hover,
#dropdownUserName .nav-company-dropdown-row .nav-company-dropdown-item--active {
  background-color: transparent;
}
#dropdownUserName .nav-company-dropdown-logo-wrap {
  position: relative;
  flex-shrink: 0;
  width: 2.125rem;
  height: 2.125rem;
}
#dropdownUserName .nav-company-dropdown-logo {
  width: 100%;
  height: 100%;
  border-radius: 0.5rem;
  object-fit: cover;
}
#dropdownUserName .nav-company-dropdown-logo-hash {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border-radius: 0.5rem;
  background-color: rgb(229 231 235);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1;
  color: rgb(75 85 99);
  padding-left: 0.125rem;
}
.dark #dropdownUserName .nav-company-dropdown-logo-hash {
  background-color: rgb(55 65 81);
  color: rgb(229 231 235);
}
#dropdownUserName .nav-company-dropdown-logo-wrap {
  overflow: visible;
}
#dropdownUserName .nav-company-dropdown-status {
  z-index: 2;
  height: 0.75rem;
  width: 0.75rem;
}
#dropdownUserName .nav-company-dropdown-status.hidden {
  display: none !important;
}
#dropdownUserName .nav-company-dropdown-text {
  min-width: 0;
  flex: 1;
}
#dropdownUserName .nav-company-dropdown-name {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.2;
  color: rgb(17 24 39);
}
.dark #dropdownUserName .nav-company-dropdown-name {
  color: rgb(var(--color-primary-210, 243 244 246) / 1);
}
#dropdownUserName .nav-company-dropdown-sub {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-top: 0.125rem;
  font-size: 0.6875rem;
  line-height: 1.15;
  color: rgb(107 114 128);
}
.dark #dropdownUserName .nav-company-dropdown-sub {
  color: rgb(156 163 175);
}
#dropdownUserName .nav-company-dropdown-fav-btn {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  align-self: stretch;
  width: 2rem;
  min-height: 2.5rem;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: 0 0.5rem 0.5rem 0;
  background: transparent;
  color: rgb(234 179 8);
  opacity: 0;
  transition: opacity 0.15s ease, color 0.15s ease;
  cursor: pointer;
}
#dropdownUserName .nav-company-dropdown-fav-btn svg {
  transform-origin: center;
  transition: transform 0.2s cubic-bezier(0.34, 1.4, 0.64, 1), color 0.15s ease;
}
#dropdownUserName .nav-company-dropdown-row:hover .nav-company-dropdown-fav-btn,
#dropdownUserName .nav-company-dropdown-row--active .nav-company-dropdown-fav-btn,
#dropdownUserName .nav-company-dropdown-fav-btn:focus-visible {
  opacity: 1;
}
#dropdownUserName .nav-company-dropdown-row:hover .nav-company-dropdown-fav-btn svg {
  transform: scale(1.05);
}
#dropdownUserName .nav-company-dropdown-fav-btn:hover svg {
  transform: scale(1.2) rotate(-10deg);
  color: rgb(202 138 4);
}
.dark #dropdownUserName .nav-company-dropdown-fav-btn:hover svg {
  color: rgb(250 204 21);
}
#dropdownUserName .nav-company-dropdown-footer {
  margin-top: 0.25rem;
  padding-top: 0.25rem;
  border-top: 1px solid rgb(229 231 235);
}
.dark #dropdownUserName .nav-company-dropdown-footer {
  border-top-color: rgb(var(--color-primary-230, 75 85 99) / 1);
}
#dropdownUserName .nav-company-dropdown-more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  width: 100%;
  padding: 0.4375rem 0.5rem;
  border: 0;
  border-radius: 0.5rem;
  background: transparent;
  font-size: 0.8125rem;
  font-weight: 600;
  color: rgb(var(--color-primary-600, 37 99 235) / 1);
  transition: background-color 0.15s ease;
  cursor: pointer;
}
#dropdownUserName .nav-company-dropdown-more:hover {
  background-color: rgb(243 244 246);
}
.dark #dropdownUserName .nav-company-dropdown-more {
  color: rgb(var(--color-primary-250, 147 197 253) / 1);
}
.dark #dropdownUserName .nav-company-dropdown-more:hover {
  background-color: rgb(var(--color-primary-140, 55 65 81) / 1);
}
/* Dashboard-Mobile-Shell: siehe assets/frontend/head.php (eine zentrale Definition). */
</style>
<nav id="main-nav" class="fixed top-0 left-0 right-0 z-20 flex h-14 w-full items-center lg:border-0 lg:bg-white dark:lg:bg-primary-50">
    <div class="flex w-full items-center px-4 gap-2">
    <button type="button" id="app-mobile-sidebar-toggle" onclick="toggleMobileSidebar()" class="hidden shrink-0 -ms-1 inline-flex items-center justify-center rounded-lg p-2 text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-primary-200 dark:hover:bg-primary-140 dark:focus:ring-primary-320" aria-label="Menü öffnen" aria-expanded="false" aria-controls="sidebar">
      <span class="sr-only">Menü</span>
      <svg class="h-6 w-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
      </svg>
    </button>
    <div class="nav-mobile-bar-left flex items-center justify-start flex-1 min-w-0 gap-2">
<?php if (!empty($navMobileUseCompactTop)): ?>
    <?php if (!empty($navTicketViewDetailMobile)): ?>
    <div class="flex min-w-0 flex-1 items-center gap-1.5 lg:hidden">
      <a href="<?php echo htmlspecialchars(BASE_URL); ?>tickets/" class="shrink-0 -ms-1 inline-flex items-center justify-center rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-primary-200 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:focus:ring-primary-320" aria-label="Zurück zur Ticketliste">
        <svg class="h-6 w-6 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </a>
      <button type="button" id="ticketNavOpenInfoBtn" class="min-w-0 flex-1 flex flex-col items-stretch justify-center text-left rounded-lg py-0.5 px-1 -mx-0.5 cursor-pointer select-none touch-manipulation [-webkit-tap-highlight-color:transparent] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 dark:focus-visible:ring-primary-400/35" aria-label="Ticket-Infos anzeigen" aria-expanded="false" aria-controls="ticketInfoPanelRoot">
        <span id="navTicketViewTitle" class="truncate text-base font-semibold leading-tight tracking-tight text-gray-900 dark:text-primary-200">Lade Ticket…</span>
        <span id="navTicketViewNumber" class="truncate text-xs leading-tight text-gray-500 dark:text-primary-220"></span>
      </button>
    </div>
    <span id="navTicketViewStatusBadge" class="shrink-0 self-center max-w-[min(42vw,11rem)] truncate rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap bg-gray-100 text-gray-600 dark:bg-primary-700 dark:text-primary-210 lg:hidden -me-4">…</span>
    <?php elseif (!empty($navMobileInventoryDetailMobile)): ?>
    <div class="flex min-w-0 flex-1 items-center gap-1.5 lg:hidden">
      <a href="<?php echo htmlspecialchars(BASE_URL); ?>inventory/" class="shrink-0 -ms-1 inline-flex items-center justify-center rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-primary-200 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:focus:ring-primary-320" aria-label="Zurück zum Lager">
        <svg class="h-6 w-6 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </a>
      <h1 id="navInventoryDetailTitle" class="min-w-0 flex-1 truncate text-lg font-semibold leading-tight tracking-tight text-gray-900 dark:text-primary-200">Laden…</h1>
    </div>
    <?php else: ?>
    <?php if (!empty($navMobileTodosQuickComposer)): ?>
    <div id="navMobileTodosDrawerBar" class="hidden min-w-0 w-full flex-1 items-center gap-1.5 lg:hidden">
      <button type="button" id="navMobileTodosDrawerBackBtn" class="shrink-0 -ms-1 inline-flex items-center justify-center rounded-lg p-2 text-gray-200 hover:bg-white/10 active:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/30 touch-manipulation [-webkit-tap-highlight-color:transparent]" aria-label="Zurück zur Aufgabenliste">
        <svg class="h-6 w-6 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </button>
      <input type="text" id="navMobileTodoDrawerTitleInput" class="min-w-0 flex-1 w-0 bg-transparent border-0 text-base font-semibold tracking-tight text-gray-100 placeholder-gray-500 focus:ring-0 focus:outline-none dark:text-gray-100 truncate rounded-none shadow-none" autocomplete="off" enterkeyhint="done" aria-label="Aufgabentitel" />
      <div id="navMobileTodosDrawerDoneWrap" class="hidden shrink-0 items-center justify-center" aria-hidden="true">
        <input type="checkbox" id="navMobileTodoDrawerDoneCb" class="todo-checkbox w-6 h-6 text-neutral-primary border-default-medium bg-neutral-secondary-medium rounded-full checked:border-brand focus:ring-2 focus:outline-none focus:ring-brand-subtle border appearance-none touch-manipulation" title="Erledigt" aria-label="Als erledigt markieren" />
      </div>
    </div>
    <?php endif; ?>
    <div id="navMobileTodosListBar" class="flex min-w-0 flex-1 items-center gap-0.5 lg:hidden">
      <?php if (!empty($navMobileCompactBackUrl)): ?>
      <a href="<?php echo htmlspecialchars((string) $navMobileCompactBackUrl); ?>" class="shrink-0 -ms-1 inline-flex items-center justify-center rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-primary-200 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:focus:ring-primary-320" aria-label="<?php echo htmlspecialchars((string) $navMobileCompactBackLabel); ?>">
        <svg class="h-6 w-6 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </a>
      <?php endif; ?>
      <h1 class="min-w-0 shrink truncate text-lg font-semibold tracking-tight text-gray-900 dark:text-primary-200<?php echo !empty($navMobileShowIntegratedFilter) ? ' cursor-pointer select-none touch-manipulation rounded-md py-1 -my-1 pe-1 -ms-0.5 hover:bg-transparent active:bg-transparent dark:hover:bg-transparent dark:active:bg-transparent [-webkit-tap-highlight-color:transparent]' : ''; ?>"<?php echo $navMobileCompactTitle !== null ? ' id="navMobileCompactTitle"' : ''; ?><?php if (!empty($navMobileShowIntegratedFilter)): ?> role="button" tabindex="0" aria-expanded="false" aria-controls="mobileFilterSheetPanel" aria-label="Filter ein- und ausblenden" data-nav-mobile-filter-title<?php endif; ?>><?php echo htmlspecialchars($navMobileCompactTitle !== null ? $navMobileCompactTitle : getCurrentPageName()); ?></h1>
      <?php if (!empty($navMobileShowIntegratedFilter)): ?>
      <button type="button" id="navMobileFilterToggleBtn" class="shrink-0 inline-flex items-center justify-center rounded-lg p-2 text-gray-800 hover:bg-transparent active:bg-transparent dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus-visible:ring-0 [-webkit-tap-highlight-color:transparent]" title="Filter" aria-label="Filter öffnen" aria-expanded="false" aria-controls="mobileFilterSheetPanel" data-filter-label-open="Filter schließen" data-filter-label-closed="Filter öffnen">
        <svg class="nav-mobile-filter-chevron h-5 w-5 shrink-0 text-current transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
    <div class="flex items-center min-w-0 <?php echo !empty($navMobileUseCompactTop) ? 'flex-1 max-lg:hidden lg:flex' : 'flex flex-1'; ?>">
<?php
$navLogo = 'assets/images/Serohub_Icon.png';
$navNamePart1 = 'Serohub';
$navNamePart2 = '';
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2')");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['setting_key'] === 'branding_logo' && !empty(trim($r['setting_value'] ?? ''))) $navLogo = trim($r['setting_value']);
            if ($r['setting_key'] === 'branding_name_part1' && $r['setting_value'] !== null) $navNamePart1 = $r['setting_value'];
            if ($r['setting_key'] === 'branding_name_part2' && $r['setting_value'] !== null) $navNamePart2 = $r['setting_value'];
        }
    } catch (PDOException $e) {}
}
$navLogoUrl = BASE_URL . ltrim($navLogo, '/');
$navIsAdminOrTechniker = in_array($row['rolle'] ?? '', ['Admin', 'Techniker'], true);
?>
<a href="<?php echo BASE_URL; ?>dashboard/" class="mr-4 flex items-center<?php echo !empty($navMobileUseCompactTop) ? ' max-lg:hidden' : ''; ?><?php echo !empty($dashboardHideMobileNavLogo) ? ' max-lg:hidden lg:flex' : ''; ?><?php echo $navIsAdminOrTechniker ? ' lg:hidden' : ''; ?>">
  <img src="<?php echo htmlspecialchars($navLogoUrl); ?>" class="h-8 pr-2" alt="Logo" onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-avatar.png'">

  <span class="self-center hidden md:inline whitespace-nowrap text-2xl font-bold">
    <span class="dark:text-white text-primary-850"><?php echo htmlspecialchars($navNamePart1); ?></span><span class=" text-primary-420  inline-block"><?php echo htmlspecialchars($navNamePart2); ?></span>
  </span>
</a>



 <?php
        // Rolle direkt aus der Datenbank abrufen (nicht aus Session)
        $userRole = $row['rolle'] ?? '';
        ?>
        
        <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
   
<?php
if (!function_exists('nav_company_status_dot_class')) {
    function nav_company_status_dot_class(?string $status, bool $allCompanies = false): string {
        $base = 'absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white dark:border-gray-700 ';
        if ($allCompanies) {
            return $base . 'hidden';
        }
        $s = strtolower(trim((string) $status));
        if ($s === 'gesperrt') {
            return $base . 'bg-red-500';
        }
        if ($s === 'inaktiv') {
            return $base . 'bg-gray-400 dark:bg-gray-500';
        }
        return $base . 'bg-emerald-500';
    }
}

if (!function_exists('nav_company_filter_show_logo')) {
    function nav_company_filter_show_logo(string $avatarUrl, int $companyId): bool {
        if ($companyId <= 0) {
            return false;
        }
        $avatarUrl = trim($avatarUrl);
        if ($avatarUrl === '') {
            return false;
        }
        return stripos($avatarUrl, 'Serohub_Icon') === false;
    }
}

// Alle Firmen aus der DB holen
$allCompanies = [];
try {
    // Prüfen ob Benutzer Admin oder Techniker ist - dann alle Firmen, sonst nur aktive
    $userRole = $row['rolle'] ?? '';
    if ($userRole === 'Admin' || $userRole === 'Techniker') {
        $stmt = $pdo->prepare("SELECT id, name, logo, status, telefonnummer FROM companies ORDER BY name ASC");
    } else {
        $stmt = $pdo->prepare("SELECT id, name, logo, status, telefonnummer FROM companies WHERE status = 'aktiv' ORDER BY name ASC");
    }
    $stmt->execute();
    $allCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allCompanies as &$c) { decrypt_company_row($c); }
    unset($c);
    usort($allCompanies, function ($a, $b) {
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
} catch (PDOException $e) {
    // Tabelle existiert noch nicht oder Fehler - einfach mit leerem Array weitermachen
    error_log("Nav: Fehler beim Laden der Firmen: " . $e->getMessage());
    $allCompanies = [];
}

// Favoriten des Benutzers abrufen
$userId = $_SESSION['user_id'] ?? null;
$favoriteCompanies = [];
$favoriteIds = [];

if ($userId) {
    try {
        // Prüfen ob user_settings Tabelle existiert
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'company_favorites'");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            $favoriteIds = json_decode($result['setting_value'], true) ?? [];
            
            if (!empty($favoriteIds)) {
                $placeholders = implode(',', array_fill(0, count($favoriteIds), '?'));
                $stmt = $pdo->prepare("SELECT id, name, logo, status, telefonnummer FROM companies WHERE id IN ($placeholders) ORDER BY name ASC");
                $stmt->execute($favoriteIds);
                $favoriteCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($favoriteCompanies as &$c) { decrypt_company_row($c); }
                unset($c);
                usort($favoriteCompanies, function ($a, $b) {
                    return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                });
            }
        }
    } catch (PDOException $e) {
        // Tabelle existiert nicht oder Fehler - einfach ohne Favoriten weitermachen
        $favoriteCompanies = [];
        $favoriteIds = [];
    }
}

// Prüfen ob es mehr Firmen gibt als Favoriten
$hasMoreCompanies = count($allCompanies) > count($favoriteCompanies);

$navMobileSessionCompanyId = 0;
if (isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null) {
    $cid = (int) $_SESSION['selected_company_id'];
    if ($cid > 0) {
        $navMobileSessionCompanyId = $cid;
    }
}
$favoriteCompanyIds = array_map(static function ($c) {
    return (int) ($c['id'] ?? 0);
}, $favoriteCompanies);
$navMobileSelectedNotInFavorites = $navMobileSessionCompanyId > 0
    && !in_array($navMobileSessionCompanyId, $favoriteCompanyIds, true);
$navMobileExtraCompanyRow = null;
if ($navMobileSelectedNotInFavorites) {
    foreach ($allCompanies as $c) {
        if ((int) ($c['id'] ?? 0) === $navMobileSessionCompanyId) {
            $navMobileExtraCompanyRow = $c;
            break;
        }
    }
}

$navFilterInitialId = 0;
$navFilterInitialName = 'Alle Firmen';
$navFilterInitialSubline = '';
$navFilterInitialStatus = '';
$navFilterInitialAvatar = '';
$navFilterInitialStatusClass = nav_company_status_dot_class('', true);
if ($navMobileSessionCompanyId > 0) {
    foreach ($allCompanies as $c) {
        if ((int) ($c['id'] ?? 0) === $navMobileSessionCompanyId) {
            $navFilterInitialId = (int) ($c['id'] ?? 0);
            $navFilterInitialName = (string) ($c['name'] ?? 'Firma');
            $navFilterInitialSubline = trim((string) ($c['telefonnummer'] ?? ''));
            $navFilterInitialStatus = (string) ($c['status'] ?? 'aktiv');
            $navFilterInitialAvatar = getLogoUrl($c['logo'] ?? '');
            $navFilterInitialStatusClass = nav_company_status_dot_class($navFilterInitialStatus);
            break;
        }
    }
}
$navFilterShowLogo = nav_company_filter_show_logo($navFilterInitialAvatar, $navFilterInitialId);
$navFilterInitialAvatarEsc = $navFilterShowLogo ? htmlspecialchars($navFilterInitialAvatar, ENT_QUOTES, 'UTF-8') : '';
?>

<?php if (!empty($dashboardKeepMobileTopNav)): ?>
<div class="flex min-w-0 flex-1 items-stretch gap-2 lg:hidden" id="nav-company-select-mobile-wrap">
    <div class="min-w-0 flex-1">
        <label for="navCompanySelectMobile" class="sr-only">Firma filtern</label>
        <img id="navCompanySelectMobileAvatar" src="" class="pointer-events-none absolute z-[2] hidden h-8 w-8 rounded-md object-cover" alt="" />
        <select id="navCompanySelectMobile" name="nav_company_mobile" autocomplete="off"
            class="block w-full min-w-0 cursor-pointer text-sm font-semibold">
            <option value="0" data-name="Alle Firmen" data-status="" data-telefon="" data-avatar="<?php echo htmlspecialchars(BASE_URL . 'assets/images/Serohub_Icon.png', ENT_QUOTES, 'UTF-8'); ?>"<?php echo $navMobileSessionCompanyId === 0 ? ' selected' : ''; ?>>Alle Firmen</option>
            <?php if ($navMobileExtraCompanyRow !== null): ?>
            <option value="<?php echo (int) $navMobileExtraCompanyRow['id']; ?>"
                data-name="<?php echo htmlspecialchars((string) ($navMobileExtraCompanyRow['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-status="<?php echo htmlspecialchars((string) ($navMobileExtraCompanyRow['status'] ?? 'aktiv'), ENT_QUOTES, 'UTF-8'); ?>"
                data-telefon="<?php echo htmlspecialchars((string) ($navMobileExtraCompanyRow['telefonnummer'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-avatar="<?php echo htmlspecialchars(getLogoUrl($navMobileExtraCompanyRow['logo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                selected><?php echo htmlspecialchars((string) ($navMobileExtraCompanyRow['name'] ?? 'Firma')); ?></option>
            <?php endif; ?>
            <?php foreach ($favoriteCompanies as $company): ?>
            <option value="<?php echo (int) $company['id']; ?>"
                data-name="<?php echo htmlspecialchars((string) ($company['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-status="<?php echo htmlspecialchars((string) ($company['status'] ?? 'aktiv'), ENT_QUOTES, 'UTF-8'); ?>"
                data-telefon="<?php echo htmlspecialchars((string) ($company['telefonnummer'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-avatar="<?php echo htmlspecialchars(getLogoUrl($company['logo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo ($navMobileExtraCompanyRow === null && $navMobileSessionCompanyId === (int) $company['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($company['name'] ?? '')); ?></option>
            <?php endforeach; ?>
            <?php if ($hasMoreCompanies): ?>
            <option value="__more_companies__" data-is-more="1">Weitere Firmen</option>
            <?php endif; ?>
        </select>
    </div>
</div>
<?php endif; ?>

<div class="nav-desktop-head mr-3 hidden min-w-0 shrink-0 items-stretch gap-0 lg:flex">
<div id="nav-company-selector-desktop" class="flex shrink-0"
  data-company-id="<?php echo (int) $navFilterInitialId; ?>"
  data-company-name="<?php echo htmlspecialchars($navFilterInitialName, ENT_QUOTES, 'UTF-8'); ?>"
  data-company-avatar="<?php echo $navFilterInitialAvatarEsc; ?>"
  data-company-status="<?php echo htmlspecialchars($navFilterInitialStatus, ENT_QUOTES, 'UTF-8'); ?>"
  data-company-telefon="<?php echo htmlspecialchars($navFilterInitialSubline, ENT_QUOTES, 'UTF-8'); ?>">
  <button
    id="dropdownUserNameButton"
    type="button"
    data-dropdown-toggle="dropdownUserName"
    data-dropdown-placement="bottom-start"
    data-dropdown-offset-distance="0"
    data-dropdown-offset-skidding="0"
    class="nav-company-filter group flex w-full max-w-full items-stretch border-0 bg-transparent p-0 text-left focus:outline-none focus-visible:ring-0"
    aria-haspopup="listbox"
    aria-expanded="false"
  >
    <span class="sr-only">Firmenfilter öffnen</span>
    <span class="nav-company-filter-hit flex w-full min-w-0 items-center rounded-lg transition-colors hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 group-focus-visible:ring-2 group-focus-visible:ring-primary-500/40 dark:hover:bg-gray-700/50 dark:focus-visible:ring-primary-400/35 dark:group-focus-visible:ring-primary-400/35">
    <span class="nav-company-filter-avatar-wrap relative shrink-0">
      <span class="nav-company-filter-icon overflow-hidden rounded-lg bg-gray-200 font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
        <img
          id="selectedAvatar"
          src="<?php echo $navFilterInitialAvatarEsc; ?>"
          alt="<?php echo htmlspecialchars($navFilterInitialName, ENT_QUOTES, 'UTF-8'); ?>"
          class="company-selector-logo h-full w-full object-cover<?php echo $navFilterShowLogo ? '' : ' hidden'; ?>"
          decoding="async"
        />
        <span id="selectedAvatarFallback" class="select-none leading-none<?php echo $navFilterShowLogo ? ' hidden' : ''; ?>" aria-hidden="true">#</span>
      </span>
      <span id="selectedCompanyStatusDot" class="<?php echo htmlspecialchars($navFilterInitialStatusClass, ENT_QUOTES, 'UTF-8'); ?>" title="Firmenstatus" aria-hidden="true"></span>
    </span>
    <span class="min-w-0 flex-1">
      <span id="selectedOption" class="block truncate font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($navFilterInitialName, ENT_QUOTES, 'UTF-8'); ?></span>
      <span id="selectedOptionSub" class="block truncate text-gray-500 dark:text-gray-400<?php echo $navFilterInitialSubline === '' ? ' hidden' : ''; ?>"><?php echo $navFilterInitialSubline !== '' ? htmlspecialchars($navFilterInitialSubline, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
    </span>
    <svg class="nav-company-filter-chevron shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
    </svg>
    </span>
  </button>
</div>
</div>

<!-- Dropdown menu (außerhalb hidden-Wrapper: funktioniert mit Mobil-Trigger unter schwarzer Leiste) -->
<div id="dropdownUserName" class="nav-company-dropdown hidden z-[60] bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 overflow-hidden" data-popper-placement="bottom">
    <div class="nav-company-dropdown-list">
    <a href="#"
       class="nav-company-dropdown-item"
       data-id="0"
       data-name="Alle Firmen"
       data-status=""
       data-telefon=""
       data-avatar="<?php echo htmlspecialchars(BASE_URL . 'assets/images/Serohub_Icon.png', ENT_QUOTES, 'UTF-8'); ?>">
        <span class="nav-company-dropdown-logo-wrap">
            <span class="nav-company-dropdown-logo-hash" aria-hidden="true">#</span>
        </span>
        <span class="nav-company-dropdown-text">
            <span class="nav-company-dropdown-name">Alle Firmen</span>
        </span>
    </a>

<?php
foreach ($favoriteCompanies as $company):
    $navFavId = (int) ($company['id'] ?? 0);
    $navFavLogoUrl = getLogoUrl($company['logo'] ?? '');
    $navFavLogoUrlEsc = htmlspecialchars($navFavLogoUrl, ENT_QUOTES, 'UTF-8');
    $navFavShowLogo = nav_company_filter_show_logo($navFavLogoUrl, $navFavId);
    $navFavTelefon = trim((string) ($company['telefonnummer'] ?? ''));
    $navFavStatusDotClass = htmlspecialchars(nav_company_status_dot_class($company['status'] ?? 'aktiv') . ' nav-company-dropdown-status', ENT_QUOTES, 'UTF-8');
?>
        <div class="company-item-wrapper nav-company-dropdown-row group">
            <a href="#"
               class="nav-company-dropdown-item company-item"
               data-id="<?php echo $navFavId; ?>"
               data-name="<?php echo htmlspecialchars((string) ($company['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
               data-status="<?php echo htmlspecialchars((string) ($company['status'] ?? 'aktiv'), ENT_QUOTES, 'UTF-8'); ?>"
               data-telefon="<?php echo htmlspecialchars($navFavTelefon, ENT_QUOTES, 'UTF-8'); ?>"
               data-avatar="<?php echo $navFavLogoUrlEsc; ?>">
                <span class="nav-company-dropdown-logo-wrap">
                    <?php if ($navFavShowLogo): ?>
                    <img src="<?php echo $navFavLogoUrlEsc; ?>" width="34" height="34" class="nav-company-dropdown-logo nav-dropdown-company-logo-lazy" alt="<?php echo htmlspecialchars((string) ($company['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> Logo" decoding="async">
                    <?php else: ?>
                    <span class="nav-company-dropdown-logo-hash" aria-hidden="true">#</span>
                    <?php endif; ?>
                    <span class="<?php echo $navFavStatusDotClass; ?>" title="Firmenstatus" aria-hidden="true"></span>
                </span>
                <span class="nav-company-dropdown-text">
                    <span class="nav-company-dropdown-name"><?php echo htmlspecialchars((string) ($company['name'] ?? '')); ?></span>
                    <?php if ($navFavTelefon !== ''): ?>
                    <span class="nav-company-dropdown-sub"><?php echo htmlspecialchars($navFavTelefon, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </span>
            </a>
            <button type="button" class="toggle-favorite-dropdown-btn nav-company-dropdown-fav-btn"
                    data-company-id="<?php echo $navFavId; ?>"
                    title="Favorit entfernen"
                    aria-label="Favorit entfernen">
                <svg class="h-4 w-4 favorite-icon-dropdown" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                </svg>
            </button>
        </div>
<?php endforeach; ?>
    </div>

<?php if ($hasMoreCompanies): ?>
    <div class="nav-company-dropdown-footer">
        <button type="button" id="showAllCompaniesBtn" class="nav-company-dropdown-more">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
            </svg>
            Weitere Firmen
        </button>
    </div>
<?php endif; ?>
</div>

<!-- Modal für alle Firmen -->
<div id="allCompaniesModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay: Klick schließt Modal (Vollbild, zuerst im DOM = dahinter) -->
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity cursor-pointer" aria-hidden="true" id="modalOverlay"></div>
    <!-- Zentrierungs-Container: mittig vom Bildschirm -->
    <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg max-h-[calc(100vh-2rem)] flex flex-col relative z-10">
            <!-- Modal panel -->
            <div class="relative bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden flex flex-col max-h-[calc(100vh-2rem)]">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 flex-shrink-0">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-primary-200" id="modal-title">
                            Alle Firmen
                        </h3>
                        <button type="button" id="closeModalBtn" class="rounded-lg p-1.5 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Suchfeld -->
                    <div class="mb-4">
                        <input type="text" id="companySearchInput" placeholder="Firma suchen..." 
                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-primary-320 rounded-base bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 placeholder-gray-500 dark:placeholder-primary-240 focus:ring-2 focus:ring-primary-250/30 focus:border-primary-250 dark:focus:ring-primary-250/30 dark:focus:border-primary-250 transition-colors">
                    </div>
                </div>
                
                <!-- Scrollbare Liste: max-height sorgt für sichtbare Scrollbar bei vielen Einträgen -->
                <div class="flex-1 min-h-0 max-h-[min(60vh,32rem)] overflow-y-auto overflow-x-hidden border-t border-gray-200 dark:border-primary-120 px-4 pb-4 company-modal-scroll">
                    <div id="allCompaniesTableBody" class="divide-y divide-gray-200 dark:divide-primary-230">
                        <!-- Option "Alle Firmen" oben im Modal -->
                        <div class="company-row hover:bg-gray-50 dark:hover:bg-primary-140 cursor-pointer select-company-row flex items-center justify-between px-4 py-3 rounded-base transition-colors" 
                             data-company-id="0" 
                             data-company-name="alle firmen"
                             data-id="0"
                             data-name="Alle Firmen"
                             data-status=""
                             data-telefon=""
                             data-avatar="<?= htmlspecialchars(BASE_URL . 'assets/images/Serohub_Icon.png', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="flex items-center flex-1 min-w-0">
                                <img src="<?= htmlspecialchars(BASE_URL . 'assets/images/Serohub_Icon.png', ENT_QUOTES, 'UTF-8') ?>" class="w-8 h-8 rounded-full mr-3 flex-shrink-0 object-cover" alt="">
                                <span class="text-sm font-medium text-gray-900 dark:text-primary-200 truncate">Alle Firmen</span>
                            </div>
                            <div class="w-5 h-5 ml-3 flex-shrink-0"></div>
                        </div>
                        <?php
                        $navModalLogoPlaceholder = htmlspecialchars(getLogoUrl(''), ENT_QUOTES, 'UTF-8');
                        foreach ($allCompanies as $company) {
                            $navCompanyLogoUrl = htmlspecialchars(getLogoUrl($company['logo']), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="company-row hover:bg-gray-50 dark:hover:bg-primary-140 cursor-pointer select-company-row flex items-center justify-between px-4 py-3 rounded-base transition-colors" 
                             data-company-id="<?= (int)$company['id'] ?>" 
                             data-company-name="<?= htmlspecialchars(strtolower($company['name'])) ?>"
                             data-id="<?= (int)$company['id'] ?>"
                             data-name="<?= htmlspecialchars($company['name']) ?>"
                             data-status="<?= htmlspecialchars($company['status'] ?? 'aktiv', ENT_QUOTES, 'UTF-8') ?>"
                             data-telefon="<?= htmlspecialchars($company['telefonnummer'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                             data-avatar="<?= $navCompanyLogoUrl ?>">
                            <div class="flex items-center flex-1 min-w-0">
                                <img src="<?= $navModalLogoPlaceholder ?>" data-lazy-src="<?= $navCompanyLogoUrl ?>" width="32" height="32" class="company-modal-logo-lazy w-8 h-8 rounded-full mr-3 flex-shrink-0 object-cover" alt="<?= htmlspecialchars($company['name']) ?> Logo" decoding="async">
                                <span class="text-sm font-medium text-gray-900 dark:text-primary-200 truncate">
                                    <?= htmlspecialchars($company['name']) ?>
                                </span>
                            </div>
                            <button type="button" class="toggle-favorite-btn ml-3 flex-shrink-0 text-gray-400 hover:text-yellow-500 dark:hover:text-yellow-400" 
                                    data-company-id="<?= (int)$company['id'] ?>"
                                    title="<?= in_array($company['id'], $favoriteIds) ? 'Favorit entfernen' : 'Als Favorit markieren' ?>">
                                <svg class="w-5 h-5 favorite-icon" fill="<?= in_array($company['id'], $favoriteIds) ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                            </button>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Scrollbar Firmenauswahl-Modal */
.company-modal-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.6) rgba(241, 245, 249, 0.5);
}
.dark .company-modal-scroll {
    scrollbar-color: rgba(100, 116, 139, 0.6) rgba(15, 23, 42, 0.5);
}
.company-modal-scroll::-webkit-scrollbar {
    width: 8px;
}
.company-modal-scroll::-webkit-scrollbar-track {
    background: rgba(241, 245, 249, 0.5);
    border-radius: 4px;
    margin: 4px 0;
}
.dark .company-modal-scroll::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.5);
}
.company-modal-scroll::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(148, 163, 184, 0.6) 0%, rgba(148, 163, 184, 0.4) 100%);
    border-radius: 4px;
    transition: background 0.2s ease;
}
.company-modal-scroll::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, rgba(148, 163, 184, 0.8) 0%, rgba(148, 163, 184, 0.6) 100%);
}
.dark .company-modal-scroll::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(100, 116, 139, 0.6) 0%, rgba(100, 116, 139, 0.4) 100%);
}
.dark .company-modal-scroll::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, rgba(100, 116, 139, 0.8) 0%, rgba(100, 116, 139, 0.6) 100%);
}
.company-modal-scroll::-webkit-scrollbar-corner {
    background: transparent;
}

/* Globale Suche: Popup-Scrollbar */
.global-search-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.6) rgba(241, 245, 249, 0.5);
}
.dark .global-search-scroll {
    scrollbar-color: rgba(100, 116, 139, 0.6) rgba(15, 23, 42, 0.5);
}
.global-search-scroll::-webkit-scrollbar { width: 8px; }
.global-search-scroll::-webkit-scrollbar-track { background: rgba(241, 245, 249, 0.5); border-radius: 4px; margin: 4px 0; }
.dark .global-search-scroll::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.5); }
.global-search-scroll::-webkit-scrollbar-thumb { background: linear-gradient(180deg, rgba(148, 163, 184, 0.6) 0%, rgba(148, 163, 184, 0.4) 100%); border-radius: 4px; }
.global-search-scroll::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, rgba(148, 163, 184, 0.8) 0%, rgba(148, 163, 184, 0.6) 100%); }
.dark .global-search-scroll::-webkit-scrollbar-thumb { background: linear-gradient(180deg, rgba(100, 116, 139, 0.6) 0%, rgba(100, 116, 139, 0.4) 100%); }
.dark .global-search-scroll::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, rgba(100, 116, 139, 0.8) 0%, rgba(100, 116, 139, 0.6) 100%); }

/* Mobile: Long-Press-Auswahl/Callout deaktivieren (außer Eingabefelder) */
@media (max-width: 1023px) {
    body,
    body *:not(input):not(textarea):not([contenteditable="true"]) {
        -webkit-user-select: none;
        user-select: none;
        -webkit-touch-callout: none;
        -webkit-tap-highlight-color: transparent;
    }
}

/* Nav Suche ↔ Speichern-Bar: horizontaler Flip (rotateY) – overflow: visible damit Suchergebnis-Dropdown sichtbar bleibt */
#navSearchSaveFlipContainer {
    perspective: 1200px;
}
#navSearchSaveFlipContainer #globalSearchWrapper,
#navSearchSaveFlipContainer #navUnsavedChangesBar {
    transform-origin: center center;
    backface-visibility: hidden;
    transition: transform 0.4s ease-out;
}
#navSearchSaveFlipContainer #globalSearchWrapper {
    transform: rotateY(0deg);
}
#navSearchSaveFlipContainer.nav-showing-save-bar #globalSearchWrapper {
    transform: rotateY(-90deg);
    pointer-events: none;
}
#navSearchSaveFlipContainer #navUnsavedChangesBar {
    transform: rotateY(90deg);
    pointer-events: none;
}
#navSearchSaveFlipContainer #navUnsavedChangesBar:not(.nav-unsaved-changes-bar-hidden) {
    transform: rotateY(0deg);
    pointer-events: auto;
}
</style>

<script>
var baseUrl = '<?php echo BASE_URL; ?>';
(function () {
    if (!('serviceWorker' in navigator)) return;
    var h = typeof location !== 'undefined' ? location.hostname : '';
    if (location.protocol !== 'https:' && h !== 'localhost' && h !== '127.0.0.1') return;
    var scope = String(baseUrl).replace(/\/?$/, '/') || '/';
    navigator.serviceWorker.register(scope + 'sw.js', { scope: scope }).catch(function () {});
})();
const noFavoritesInNav = <?php echo (count($favoriteCompanies) === 0) ? 'true' : 'false'; ?>;
const allCompaniesModal = document.getElementById('allCompaniesModal');
const showAllCompaniesBtn = document.getElementById('showAllCompaniesBtn');
const closeModalBtn = document.getElementById('closeModalBtn');
const modalOverlay = document.getElementById('modalOverlay');
const companySearchInput = document.getElementById('companySearchInput');
const allCompaniesTableBody = document.getElementById('allCompaniesTableBody');
const favoritesApiUrl = baseUrl + 'settings/api/favorites.php';

/* Mobile: Long-Press-Menü und Textauswahl auf Touch-Geräten unterbinden */
(function () {
    var isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    if (!isTouchDevice) return;

    var isEditableTarget = function (el) {
        if (!el) return false;
        if (el.closest('input, textarea, [contenteditable="true"]')) return true;
        return false;
    };

    document.addEventListener('contextmenu', function (event) {
        if (isEditableTarget(event.target)) return;
        event.preventDefault();
    }, { passive: false });

    document.addEventListener('selectstart', function (event) {
        if (isEditableTarget(event.target)) return;
        event.preventDefault();
    }, { passive: false });
})();

/** Logos im Modal erst beim Öffnen laden (vermeidet N parallele Requests beim Seitenaufbau). */
function hydrateCompanyModalLogos() {
    var root = document.getElementById('allCompaniesTableBody');
    if (!root) return;
    root.querySelectorAll('img.company-modal-logo-lazy[data-lazy-src]').forEach(function(img) {
        var url = img.getAttribute('data-lazy-src');
        if (url) {
            img.src = url;
        }
        img.removeAttribute('data-lazy-src');
    });
}

/** Favoriten im Firmen-Dropdown: Logos erst beim ersten Öffnen laden. */
function hydrateNavDropdownCompanyLogos() {
    var root = document.getElementById('dropdownUserName');
    if (!root) return;
    root.querySelectorAll('img.nav-dropdown-company-logo-lazy[data-lazy-src]').forEach(function(img) {
        var url = img.getAttribute('data-lazy-src');
        if (url) {
            img.src = url;
        }
        img.removeAttribute('data-lazy-src');
    });
}

function openAllCompaniesModal() {
    if (!allCompaniesModal) return;
    allCompaniesModal.classList.remove('hidden');
    hydrateCompanyModalLogos();
    if (companySearchInput) {
        setTimeout(function() { companySearchInput.focus(); }, 100);
    }
}

function isCompanyFilterLogoAvatar(avatar, id) {
    if (!avatar || String(avatar).trim() === '') return false;
    if (id === '0' || id === 0 || id === '' || id === null || id === undefined) return false;
    return !String(avatar).includes('Serohub_Icon');
}

function syncCompanyFilterAvatar(avatar, id) {
    var showLogo = isCompanyFilterLogoAvatar(avatar, id);
    var selectedAvatar = document.getElementById('selectedAvatar');
    var selectedAvatarFallback = document.getElementById('selectedAvatarFallback');
    var selectedOption = document.getElementById('selectedOption');
    if (selectedAvatar) {
        if (showLogo) {
            var nextSrc = String(avatar || '');
            var currentSrc = selectedAvatar.getAttribute('src') || '';
            if (currentSrc !== nextSrc) {
                selectedAvatar.src = nextSrc;
            }
            selectedAvatar.alt = (selectedOption && selectedOption.textContent) ? selectedOption.textContent : '';
            selectedAvatar.classList.remove('hidden');
        } else {
            if (selectedAvatar.getAttribute('src')) {
                selectedAvatar.removeAttribute('src');
            }
            selectedAvatar.classList.add('hidden');
        }
    }
    if (selectedAvatarFallback) {
        selectedAvatarFallback.classList.toggle('hidden', showLogo);
    }
}

function getCompanyStatusDotClass(status, id) {
    var base = 'absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white dark:border-gray-700 ';
    if (id === '0' || id === 0 || id === '' || id === null || id === undefined) {
        return base + 'hidden';
    }
    var s = String(status || 'aktiv').toLowerCase();
    if (s === 'gesperrt') return base + 'bg-red-500';
    if (s === 'inaktiv') return base + 'bg-gray-400 dark:bg-gray-500';
    return base + 'bg-emerald-500';
}

function syncCompanyFilterMeta(status, telefon, id) {
    var dot = document.getElementById('selectedCompanyStatusDot');
    var sub = document.getElementById('selectedOptionSub');
    if (dot) {
        var isAll = (id === '0' || id === 0 || id === '' || id === null || id === undefined);
        dot.className = getCompanyStatusDotClass(status, id);
        if (isAll) {
            dot.removeAttribute('title');
        } else {
            var s = String(status || '').toLowerCase();
            var title = 'Aktiv';
            if (s === 'gesperrt') title = 'Gesperrt';
            else if (s === 'inaktiv') title = 'Inaktiv';
            dot.setAttribute('title', title);
        }
    }
    if (sub) {
        var isAll = (id === '0' || id === 0 || id === '' || id === null || id === undefined);
        if (isAll) {
            sub.textContent = '';
            sub.classList.add('hidden');
        } else {
            var t = String(telefon || '').trim();
            sub.textContent = t || '—';
            sub.classList.remove('hidden');
        }
    }
}

// Hilfsfunktion für Logo-URLs
function getLogoUrl(logo) {
    if (!logo || logo.trim() === '') {
        return baseUrl + 'assets/images/default-avatar.png';
    }
    // Wenn bereits absolute URL
    if (logo.startsWith('http://') || logo.startsWith('https://')) {
        return logo;
    }
    // Relative Pfade mit baseUrl kombinieren
    return baseUrl + logo.replace(/^\//, '');
}

const savedSelection = localStorage.getItem('selectedUserOption');

function initNavCompanyFilterDesktop() {
    var selectedOption = document.getElementById('selectedOption');
    var dropdownMenu = document.getElementById('dropdownUserName');
    var dropdownUserNameButton = document.getElementById('dropdownUserNameButton');
    var companyFilterWrap = document.getElementById('nav-company-selector-desktop');

    function serverCompanyId() {
        if (!companyFilterWrap) return '0';
        var sid = companyFilterWrap.getAttribute('data-company-id');
        return sid === null || sid === '' ? '0' : String(sid);
    }

    if (savedSelection) {
        try {
            var data = JSON.parse(savedSelection);
            var storedId = (data.id === '0' || data.id === 0 || data.id === '' || data.id === null || data.id === undefined) ? '0' : String(data.id);
            var differsFromServer = storedId !== serverCompanyId();
            if (selectedOption && (differsFromServer || selectedOption.textContent !== data.name)) {
                selectedOption.textContent = data.name;
            }
            if (differsFromServer) {
                syncCompanyFilterAvatar(data.avatar, data.id);
                syncCompanyFilterMeta(data.status, data.telefon, data.id);
            }
        } catch (e) {
            console.error('Fehler beim Laden der gespeicherten Auswahl', e);
        }
    }

    if (dropdownUserNameButton && noFavoritesInNav && allCompaniesModal) {
        dropdownUserNameButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (dropdownMenu) dropdownMenu.classList.add('hidden');
            openAllCompaniesModal();
        }, true);
    }

    if (dropdownUserNameButton && !noFavoritesInNav) {
        dropdownUserNameButton.addEventListener('click', function() {
            hydrateNavDropdownCompanyLogos();
            highlightNavCompanyDropdownSelection();
        });
    }

    highlightNavCompanyDropdownSelection();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavCompanyFilterDesktop);
} else {
    initNavCompanyFilterDesktop();
}

(function syncNavCompanySelectMobileFromStorage() {
    var mobSel = document.getElementById('navCompanySelectMobile');
    if (!mobSel || !savedSelection) return;
    try {
        var data = JSON.parse(savedSelection);
        var v = (data.id === '0' || data.id === 0 || data.id === undefined || data.id === null) ? '0' : String(data.id);
        var i, ok = false;
        for (i = 0; i < mobSel.options.length; i++) {
            if (mobSel.options[i].value === v) {
                ok = true;
                break;
            }
        }
        if (ok) mobSel.value = v;
    } catch (e) {}
})();

(function initSidebarTicketsCountRefresh() {
    var ticketsOpenCountUrl = '<?php echo BASE_URL; ?>tickets/api/open-count.php';

    if (typeof window.updateSidebarTicketsCount !== 'function') {
        window.updateSidebarTicketsCount = function() {
            fetch(ticketsOpenCountUrl, { headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var nodes = document.querySelectorAll('.sidebar-open-tickets-count-badge');
                    if (!nodes.length) return;
                    var count = data.success ? (data.open_count || 0) : 0;
                    var text = count > 99 ? '99' : String(count);
                    var title = count + ' offene Tickets';
                    nodes.forEach(function(el) {
                        el.textContent = text;
                        el.title = title;
                        el.classList.toggle('hidden', count <= 0);
                    });
                })
                .catch(function() {});
        };
    }

    function refreshSidebarTicketsCount() {
        if (typeof window.updateSidebarTicketsCount === 'function') {
            window.updateSidebarTicketsCount();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshSidebarTicketsCount);
    } else {
        refreshSidebarTicketsCount();
    }

    window.addEventListener('pageshow', function(e) {
        if (e.persisted) refreshSidebarTicketsCount();
    });

    window.addEventListener('storage', function(e) {
        if (e.key === 'selectedUserOption') refreshSidebarTicketsCount();
    });
})();

// Funktion zum Setzen der Firma
function setCompany(id, name, avatar, reloadPage, status, telefon) {
    if (reloadPage === undefined) reloadPage = true;
    // Button aktualisieren
    var selectedOptionEl = document.getElementById('selectedOption');
    if (selectedOptionEl) selectedOptionEl.textContent = name;
    syncCompanyFilterAvatar(avatar, id);
    syncCompanyFilterMeta(status, telefon, id);

    // Speichern für Reload
    localStorage.setItem('selectedUserOption', JSON.stringify({
        id: id,
        name: name,
        avatar: avatar,
        status: status || '',
        telefon: telefon || ''
    }));

    var mobSelNav = document.getElementById('navCompanySelectMobile');
    var mobSelAvatar = document.getElementById('navCompanySelectMobileAvatar');
    if (mobSelNav) {
        var selVal = (id === '0' || id === 0 || id === '' || id === null || id === undefined) ? '0' : String(id);
        var j, okSel = false;
        var selectedAvatarUrl = '';
        for (j = 0; j < mobSelNav.options.length; j++) {
            if (mobSelNav.options[j].value === selVal) {
                okSel = true;
                selectedAvatarUrl = mobSelNav.options[j].getAttribute('data-avatar') || '';
                break;
            }
        }
        if (okSel) mobSelNav.value = selVal;
        if (mobSelAvatar) {
            if (selectedAvatarUrl) {
                mobSelAvatar.src = selectedAvatarUrl;
                mobSelAvatar.alt = (name || '') + ' Logo';
                mobSelAvatar.classList.remove('hidden');
            } else {
                mobSelAvatar.classList.add('hidden');
            }
        }
    }

    // Dropdown schließen
    var dropdownMenuEl = document.getElementById('dropdownUserName');
    if (dropdownMenuEl) {
        dropdownMenuEl.classList.add('hidden');
    }
    
    // Modal schließen
    if (allCompaniesModal) {
        allCompaniesModal.classList.add('hidden');
    }

    // Firma in Session synchronisieren
    const companyId = id && id !== '0' ? parseInt(id) : null;
    const currentPath = window.location.pathname;
    
    // Bestimme den set_company Endpoint basierend auf dem aktuellen Pfad (Fallback: admin, damit Sidebar-Zähler etc. überall angepasst werden)
    let setCompanyUrl = '<?php echo BASE_URL; ?>admin/set_company.php';
    if (currentPath.includes('/jobs/')) {
        setCompanyUrl = '<?php echo BASE_URL; ?>jobs/set_company.php';
    } else if (currentPath.includes('/packages/')) {
        setCompanyUrl = '<?php echo BASE_URL; ?>packages/set_company.php';
    } else if (currentPath.includes('/stats/')) {
        setCompanyUrl = '<?php echo BASE_URL; ?>stats/set_company.php';
    } else if (currentPath.includes('/admin/')) {
        setCompanyUrl = '<?php echo BASE_URL; ?>admin/set_company.php';
    } else if (currentPath.includes('/knowledge/')) {
        setCompanyUrl = '<?php echo BASE_URL; ?>knowledge/set_company.php';
    } else if (currentPath.includes('/projects/')) {
        setCompanyUrl = '<?php echo BASE_URL; ?>admin/set_company.php';
    }
    
    // Firma in Session setzen und Seite immer neu laden
    fetch(setCompanyUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            company_id: companyId
        })
    })
    .then(response => response.json())
    .then(data => {
        var companyFilterWrap = document.getElementById('nav-company-selector-desktop');
        if (companyFilterWrap) {
            companyFilterWrap.setAttribute('data-company-id', companyId ? String(companyId) : '0');
        }
        if (typeof window.updateSidebarTicketsCount === 'function') {
            window.updateSidebarTicketsCount();
        }
        window.dispatchEvent(new CustomEvent('companyChanged', { detail: { companyId: companyId } }));

        // In der Wissensdatenbank: auf Basis-URL wechseln, damit keine fremde Seite (id) angezeigt wird
        if (currentPath.includes('/knowledge/')) {
            location.href = '<?php echo BASE_URL; ?>knowledge/';
            return;
        }
        // Seite immer neu laden, auch bei Fehler
        location.reload();
    })
    .catch(error => {
        console.error('Fehler beim Synchronisieren der Firma:', error);
        if (typeof window.updateSidebarTicketsCount === 'function') {
            window.updateSidebarTicketsCount();
        }
        window.dispatchEvent(new CustomEvent('companyChanged', { detail: { companyId: companyId } }));
        // In der Wissensdatenbank: auf Basis-URL wechseln
        if (currentPath.includes('/knowledge/')) {
            location.href = '<?php echo BASE_URL; ?>knowledge/';
            return;
        }
        location.reload();
    });
}

(function initNavCompanySelectMobile() {
    var mobSel = document.getElementById('navCompanySelectMobile');
    function triggerCompanyFilterHaptic() {
        if (typeof window.hapticLightTap === 'function') {
            window.hapticLightTap();
        }
    }
    var mobSelAvatar = document.getElementById('navCompanySelectMobileAvatar');
    function syncMobileAvatar() {
        if (!mobSel || !mobSelAvatar) return;
        var current = mobSel.options[mobSel.selectedIndex];
        if (!current) {
            mobSelAvatar.classList.add('hidden');
            return;
        }
        var avatar = current.getAttribute('data-avatar') || '';
        var name = current.getAttribute('data-name') || '';
        if (avatar && current.getAttribute('data-is-more') !== '1') {
            mobSelAvatar.src = avatar;
            mobSelAvatar.alt = name ? (name + ' Logo') : '';
            mobSelAvatar.classList.remove('hidden');
            return;
        }
        mobSelAvatar.classList.add('hidden');
    }
    if (mobSel) {
        if (typeof noFavoritesInNav !== 'undefined' && noFavoritesInNav && allCompaniesModal) {
            var openModalDirectly = function(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                triggerCompanyFilterHaptic();
                var dmHide = document.getElementById('dropdownUserName');
                if (dmHide) dmHide.classList.add('hidden');
                openAllCompaniesModal();
            };
            mobSel.addEventListener('mousedown', openModalDirectly);
            mobSel.addEventListener('touchstart', openModalDirectly, { passive: false });
            mobSel.addEventListener('click', openModalDirectly);
        }
        mobSel.addEventListener('click', triggerCompanyFilterHaptic);
        mobSel.addEventListener('focus', function() {
            mobSel.setAttribute('data-prev-value', mobSel.value || '0');
        });
        mobSel.addEventListener('change', function() {
            var opt = mobSel.options[mobSel.selectedIndex];
            if (!opt) return;
            if (opt.getAttribute('data-is-more') === '1') {
                var prevValue = mobSel.getAttribute('data-prev-value') || '0';
                mobSel.value = prevValue;
                syncMobileAvatar();
                var dmHide2 = document.getElementById('dropdownUserName');
                if (dmHide2) dmHide2.classList.add('hidden');
                openAllCompaniesModal();
                return;
            }
            setCompany(
                opt.value,
                opt.getAttribute('data-name') || '',
                opt.getAttribute('data-avatar') || '',
                true,
                opt.getAttribute('data-status') || '',
                opt.getAttribute('data-telefon') || ''
            );
        });
        syncMobileAvatar();
    }
})();

function highlightNavCompanyDropdownSelection() {
    var menu = document.getElementById('dropdownUserName');
    if (!menu) return;
    var selectedId = '0';
    var wrap = document.getElementById('nav-company-selector-desktop');
    if (wrap) {
        var serverId = wrap.getAttribute('data-company-id');
        if (serverId !== null && serverId !== '') {
            selectedId = String(serverId);
        }
    } else {
        try {
            var raw = localStorage.getItem('selectedUserOption');
            if (raw) {
                var data = JSON.parse(raw);
                var id = data.id;
                if (id !== 0 && id !== '0' && id !== '' && id != null) {
                    selectedId = String(id);
                }
            }
        } catch (e) {}
    }
    menu.querySelectorAll('.nav-company-dropdown-item[data-id]').forEach(function(item) {
        var itemId = item.getAttribute('data-id') || '0';
        var match = itemId === selectedId || (itemId === '0' && selectedId === '0');
        var row = item.closest('.nav-company-dropdown-row');
        if (row) {
            row.classList.toggle('nav-company-dropdown-row--active', match);
            item.classList.remove('nav-company-dropdown-item--active');
        } else {
            item.classList.toggle('nav-company-dropdown-item--active', match);
        }
    });
}

function buildCompanyDropdownFavoriteRow(company) {
    var id = company.id;
    var name = company.name || '';
    var avatar = getLogoUrl(company.logo);
    var telefon = String(company.telefonnummer || '').trim();
    var status = company.status || 'aktiv';
    var showLogo = isCompanyFilterLogoAvatar(avatar, id);

    var wrapper = document.createElement('div');
    wrapper.className = 'company-item-wrapper nav-company-dropdown-row group';

    var link = document.createElement('a');
    link.href = '#';
    link.className = 'nav-company-dropdown-item company-item';
    link.setAttribute('data-id', id);
    link.setAttribute('data-name', name);
    link.setAttribute('data-status', status);
    link.setAttribute('data-telefon', telefon);
    link.setAttribute('data-avatar', avatar);

    var logoWrap = document.createElement('span');
    logoWrap.className = 'nav-company-dropdown-logo-wrap';

    if (showLogo) {
        var img = document.createElement('img');
        img.src = avatar;
        img.width = 34;
        img.height = 34;
        img.className = 'nav-company-dropdown-logo nav-dropdown-company-logo-lazy';
        img.alt = name + ' Logo';
        img.decoding = 'async';
        logoWrap.appendChild(img);
    } else {
        var hashEl = document.createElement('span');
        hashEl.className = 'nav-company-dropdown-logo-hash';
        hashEl.setAttribute('aria-hidden', 'true');
        hashEl.textContent = '#';
        logoWrap.appendChild(hashEl);
    }

    var dot = document.createElement('span');
    dot.className = getCompanyStatusDotClass(status, id) + ' nav-company-dropdown-status';
    dot.setAttribute('title', 'Firmenstatus');
    dot.setAttribute('aria-hidden', 'true');
    logoWrap.appendChild(dot);

    var textWrap = document.createElement('span');
    textWrap.className = 'nav-company-dropdown-text';
    var nameSpan = document.createElement('span');
    nameSpan.className = 'nav-company-dropdown-name';
    nameSpan.textContent = name;
    textWrap.appendChild(nameSpan);
    if (telefon) {
        var sub = document.createElement('span');
        sub.className = 'nav-company-dropdown-sub';
        sub.textContent = telefon;
        textWrap.appendChild(sub);
    }

    link.appendChild(logoWrap);
    link.appendChild(textWrap);

    var toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'toggle-favorite-dropdown-btn nav-company-dropdown-fav-btn';
    toggleBtn.setAttribute('data-company-id', id);
    toggleBtn.setAttribute('title', 'Favorit entfernen');
    toggleBtn.setAttribute('aria-label', 'Favorit entfernen');

    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'h-4 w-4 favorite-icon-dropdown');
    svg.setAttribute('fill', 'currentColor');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('d', 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z');
    svg.appendChild(path);
    toggleBtn.appendChild(svg);

    wrapper.appendChild(link);
    wrapper.appendChild(toggleBtn);
    return wrapper;
}

// Funktion zum Aktualisieren der Favoriten-Liste im Dropdown
function updateFavoritesList() {
    fetch(favoritesApiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const dropdownMenu = document.getElementById('dropdownUserName');
                const listEl = dropdownMenu ? dropdownMenu.querySelector('.nav-company-dropdown-list') : null;

                if (!listEl) return;

                const oldFavorites = listEl.querySelectorAll('.company-item-wrapper');
                oldFavorites.forEach(item => item.remove());

                if (data.favorites && data.favorites.length > 0) {
                    data.favorites.forEach(company => {
                        listEl.appendChild(buildCompanyDropdownFavoriteRow(company));
                    });

                    attachCompanyItemListeners();
                    highlightNavCompanyDropdownSelection();
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Favoriten:', error);
        });
}

// Event Listener für Favoriten-Items (wird nur einmal beim Laden gesetzt, Event Delegation)
function attachCompanyItemListeners() {
    // Event Delegation wird bereits über das Dropdown-Menü gehandhabt
    // Diese Funktion wird hauptsächlich für die Initialisierung verwendet
    const dropdownMenu = document.getElementById('dropdownUserName');
    if (dropdownMenu && !dropdownMenu.hasAttribute('data-listeners-attached')) {
        dropdownMenu.setAttribute('data-listeners-attached', 'true');
        dropdownMenu.addEventListener('click', (e) => {
            // Prüfe ob Klick auf Favoriten-Button war - dann nicht weiter
            if (e.target.closest('.toggle-favorite-dropdown-btn')) {
                return;
            }
            
            // Prüfe ob Klick auf "Alle Firmen" Link
            const allCompaniesLink = e.target.closest('a[data-id="0"]');
            if (allCompaniesLink) {
                e.preventDefault();
                setCompany(
                    allCompaniesLink.getAttribute('data-id'),
                    allCompaniesLink.getAttribute('data-name'),
                    allCompaniesLink.getAttribute('data-avatar'),
                    true,
                    allCompaniesLink.getAttribute('data-status') || '',
                    allCompaniesLink.getAttribute('data-telefon') || ''
                );
                return;
            }
            
            // Prüfe ob Klick auf Favoriten-Item
            const companyItem = e.target.closest('a.company-item');
            if (companyItem) {
                e.preventDefault();
                setCompany(
                    companyItem.getAttribute('data-id'),
                    companyItem.getAttribute('data-name'),
                    companyItem.getAttribute('data-avatar'),
                    true,
                    companyItem.getAttribute('data-status') || 'aktiv',
                    companyItem.getAttribute('data-telefon') || ''
                );
            }
        });
    }
}

// Initial Event Listener für Favoriten-Items
attachCompanyItemListeners();

// Modal öffnen
if (showAllCompaniesBtn) {
    showAllCompaniesBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        var dm = document.getElementById('dropdownUserName');
        if (dm) dm.classList.add('hidden');
        openAllCompaniesModal();
    });
}

// Modal schließen
function closeModal() {
    if (allCompaniesModal) {
        allCompaniesModal.classList.add('hidden');
    }
    if (companySearchInput) {
        companySearchInput.value = '';
        filterCompanies('');
    }
}

if (closeModalBtn) {
    closeModalBtn.addEventListener('click', closeModal);
}

if (modalOverlay) {
    modalOverlay.addEventListener('click', closeModal);
}

// ESC-Taste zum Schließen
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && allCompaniesModal && !allCompaniesModal.classList.contains('hidden')) {
        closeModal();
    }
});

// Tippfehlertoleranz: Match wenn Suchbegriff exakt vorkommt oder mit einem Zeichen Abweichung
function fuzzyMatchSearch(term, text) {
    if (!term || term.length < 2) return text.toLowerCase().includes(term.toLowerCase());
    const lower = (text || '').toLowerCase();
    const t = term.toLowerCase();
    if (lower.includes(t)) return true;
    // Ein Zeichen als Platzhalter: z.B. "Servcie" findet "Tickets" über Pattern "Servi.e"
    const esc = (s) => (s || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    for (let i = 0; i < t.length; i++) {
        const pattern = esc(t.slice(0, i)) + '.' + esc(t.slice(i + 1));
        try {
            if (new RegExp(pattern).test(lower)) return true;
        } catch (_) {}
    }
    return false;
}

// Suchfunktion
function filterCompanies(searchTerm) {
    if (!allCompaniesTableBody) {
        console.error('allCompaniesTableBody nicht gefunden');
        return;
    }
    
    const rows = allCompaniesTableBody.querySelectorAll('.company-row');
    const term = searchTerm.toLowerCase().trim();
    
    if (term === '') {
        // Wenn leer, alle anzeigen
        rows.forEach(row => {
            row.style.display = 'flex';
        });
        return;
    }
    
    rows.forEach(row => {
        const companyName = row.getAttribute('data-company-name') || '';
        if (fuzzyMatchSearch(term, companyName)) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}

if (companySearchInput) {
    companySearchInput.addEventListener('input', (e) => {
        filterCompanies(e.target.value);
    });
    
    // Auch beim Keyup-Event, falls input nicht funktioniert
    companySearchInput.addEventListener('keyup', (e) => {
        filterCompanies(e.target.value);
    });
} else {
    console.error('companySearchInput nicht gefunden');
}

// Favoriten-Toggle (für Modal und Dropdown)
document.addEventListener('click', (e) => {
    // Prüfe ob Klick auf Button oder SVG innerhalb des Buttons
    let toggleBtn = e.target.closest('.toggle-favorite-btn, .toggle-favorite-dropdown-btn');
    
    // Falls direkt auf SVG oder Path geklickt wurde, finde den Button
    if (!toggleBtn) {
        if (e.target.tagName === 'svg' || e.target.tagName === 'path') {
            const svg = e.target.closest('svg') || (e.target.tagName === 'path' ? e.target.parentElement : null);
            if (svg) {
                toggleBtn = svg.closest('.toggle-favorite-btn, .toggle-favorite-dropdown-btn');
            }
        }
    }
    
    if (toggleBtn) {
        e.preventDefault();
        e.stopPropagation();
        
        const companyId = parseInt(toggleBtn.getAttribute('data-company-id'));
        if (!companyId || isNaN(companyId)) {
            console.error('Ungültige company_id:', toggleBtn.getAttribute('data-company-id'));
            return;
        }
        
        const icon = toggleBtn.querySelector('.favorite-icon, .favorite-icon-dropdown');
        const isFavorite = icon && icon.getAttribute('fill') === 'currentColor';
        
        // API-Aufruf
        fetch(favoritesApiUrl, {
            method: isFavorite ? 'DELETE' : 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                company_id: companyId
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Icon aktualisieren (Modal und Dropdown)
                if (icon) {
                    if (isFavorite) {
                        icon.setAttribute('fill', 'none');
                        toggleBtn.setAttribute('title', 'Als Favorit markieren');
                    } else {
                        icon.setAttribute('fill', 'currentColor');
                        toggleBtn.setAttribute('title', 'Favorit entfernen');
                    }
                }
                
                // Toast-Nachricht anzeigen
                if (typeof showToast === 'function') {
                    showToast(isFavorite ? 'Favorit entfernt' : 'Als Favorit hinzugefügt', 'success');
                }
                
                // Wenn aus Dropdown entfernt, Item sofort entfernen
                if (toggleBtn.classList.contains('toggle-favorite-dropdown-btn')) {
                    const wrapper = toggleBtn.closest('.company-item-wrapper');
                    if (wrapper) {
                        wrapper.style.transition = 'opacity 0.3s, transform 0.3s';
                        wrapper.style.opacity = '0';
                        wrapper.style.transform = 'translateX(-10px)';
                        setTimeout(() => {
                            wrapper.remove();
                            // Prüfen ob noch Favoriten vorhanden sind
                            const remainingFavorites = document.querySelectorAll('.company-item-wrapper');
                            if (remainingFavorites.length === 0) {
                                // "Alle Firmen" Button könnte jetzt sichtbar sein
                                const allCompaniesBtn = document.getElementById('showAllCompaniesBtn');
                                if (allCompaniesBtn && allCompaniesBtn.closest('div.border-t')) {
                                    allCompaniesBtn.closest('div.border-t').style.display = '';
                                }
                            }
                        }, 300);
                    }
                } else {
                    // Für Modal: Favoriten-Liste im Dropdown aktualisieren
                    updateFavoritesList();
                }
            } else {
                console.error('Fehler beim Aktualisieren der Favoriten:', data.error);
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Aktualisieren der Favoriten: ' + (data.error || 'Unbekannter Fehler'), 'error');
                } else {
                    alert('Fehler beim Aktualisieren der Favoriten: ' + (data.error || 'Unbekannter Fehler'));
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Aktualisieren der Favoriten:', error);
            if (typeof showToast === 'function') {
                showToast('Fehler beim Aktualisieren der Favoriten: ' + error.message, 'error');
            } else {
                alert('Fehler beim Aktualisieren der Favoriten: ' + error.message);
            }
        });
        
        return false; // Wichtig: Verhindere weitere Event-Propagation
    }
});

// Firmenauswahl im Modal (ganze Zeile anklickbar) - NACH dem Favoriten-Listener
document.addEventListener('click', (e) => {
    // Prüfe ob Klick auf Favoriten-Button war - dann nicht weiter
    if (e.target.closest('.toggle-favorite-btn, .toggle-favorite-dropdown-btn')) {
        return;
    }
    
    // Prüfe ob Klick auf Tabellenzeile im Modal
    const row = e.target.closest('.select-company-row');
    if (row && allCompaniesModal && !allCompaniesModal.classList.contains('hidden')) {
        setCompany(
            row.getAttribute('data-id'),
            row.getAttribute('data-name'),
            row.getAttribute('data-avatar'),
            true,
            row.getAttribute('data-status') || 'aktiv',
            row.getAttribute('data-telefon') || ''
        );
    }
});

</script>

      





     <?php endif; ?>













    </div>
    </div>

<?php
    $navRequestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isNavUnsavedChangesPage = (bool)preg_match('#((customers|companies|devices)/[^/]*edit|inventory/create)|/account#', $navRequestUri);
    $showNavCenterSlot = true; // Suche ist für alle Benutzer sichtbar
?>
    <?php if ($showNavCenterSlot): ?>
    <!-- Nav-Mitte: Globale Suche (initial); bei Bearbeitungsseiten (Kunde/Firma/Gerät) erscheint Banner bei Änderung -->
    <div class="<?php echo !empty($navTicketViewDetailMobile) ? 'hidden lg:flex' : 'hidden md:flex'; ?> shrink-0 items-center justify-center px-2 <?php echo $isNavUnsavedChangesPage ? 'min-h-[2.75rem]' : ''; ?>" style="width: 52%; min-width: 300px; max-width: 600px;">
      <div id="navSearchSaveFlipContainer" class="relative w-full <?php echo $isNavUnsavedChangesPage ? 'min-h-[2.75rem]' : ''; ?>">
      <!-- Globale Suchleiste (breiter, mittig) – bei Kunden-Edit zunächst sichtbar, weicht bei Änderung dem Banner (Flip-Animation) -->
    <?php
    $navKbCompanyId = '';
    if (isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null) {
        $cid = (int) $_SESSION['selected_company_id'];
        if ($cid > 0) $navKbCompanyId = (string) $cid;
    }
    ?>
    <div id="globalSearchWrapper" class="nav-global-search relative w-full" data-kb-company-id="<?php echo htmlspecialchars($navKbCompanyId); ?>">
      <div class="nav-global-search__bar relative flex min-w-0 flex-1 items-stretch">
        <input type="search"
               id="globalSearchInput"
               placeholder="Suchen"
               autocomplete="off"
               class="nav-global-search__input global-search-input">
        <div class="nav-global-search__submit-wrap">
          <button type="button" id="globalSearchSubmitBtn" class="nav-global-search__submit" title="Suchen" aria-label="Suchen">
            <span class="nav-global-search__icon-stack" aria-hidden="true">
              <span class="nav-global-search__icon nav-global-search__icon--search">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                </svg>
              </span>
              <span class="nav-global-search__icon nav-global-search__icon--clear">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
              </span>
            </span>
          </button>
        </div>
      </div>
      <div id="globalSearchResults" class="hidden absolute inset-x-0 top-full z-50 mt-2 max-w-[min(90vw,600px)] overflow-hidden rounded-xl border border-gray-100 bg-white shadow-2xl dark:border-primary-120 dark:bg-primary-100">
          <!-- Kategorie-Pills (oben) -->
          <div id="globalSearchPills" class="flex gap-2 px-4 py-3 border-b border-gray-100 dark:border-primary-230 overflow-x-auto shrink-0 pb-2" style="scrollbar-width: thin;">
            <div id="globalSearchPillsInner" class="flex gap-2 flex-nowrap"></div>
          </div>
          <!-- Haupt-Ergebnisliste -->
          <div id="globalSearchResultsContent" class="p-2">
            <div id="globalSearchResultsList" class="max-h-[min(60vh,24rem)] overflow-y-auto global-search-scroll"></div>
            <div id="globalSearchMore" class="hidden px-4 py-3 border-t border-gray-100 dark:border-primary-230">
              <p id="globalSearchMoreLink" class="text-sm text-gray-500 dark:text-primary-240 text-center m-0" role="status"></p>
            </div>
          </div>
          <div id="globalSearchEmpty" class="hidden px-4 py-8 text-center text-sm text-gray-500 dark:text-primary-240">
            Keine Ergebnisse gefunden.
          </div>
          <template id="globalSearchEmptyIllustrationTpl">
<svg class="w-auto max-w-[20rem] h-48 text-gray-800 dark:text-white mx-auto mb-4" aria-hidden="true" width="420" height="568" viewBox="0 0 420 568" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M420 210C420 325.98 325.98 420 210 420C94.0202 420 0 325.98 0 210C0 94.0202 94.0202 0 210 0C325.98 0 420 94.0202 420 210Z" fill="#d6e2fb"/>
<path d="M420 210C420 325.98 325.98 420 210 420C94.0202 420 0 325.98 0 210C0 94.0202 94.0202 0 210 0C325.98 0 420 94.0202 420 210Z" fill="url(#paint0_linear_411_1555)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M399.91 299.749C366.257 370.833 293.87 420 209.999 420C136.23 420 71.345 381.963 33.8867 324.432C57.2858 313.976 89.8328 305.109 122.499 311C124.597 311.378 126.668 311.754 128.714 312.124C186.191 322.54 224.613 329.503 297.999 301.5C328.754 289.765 366.007 292.119 399.91 299.749Z" fill="url(#paint1_linear_411_1555)"/>
<path d="M354.799 239.124C354.204 239.124 353.721 238.641 353.721 238.046L-nan -nanL353.721 238.046C353.749 233.042 354.224 229.061 355.145 226.103C356.095 223.145 357.435 220.767 359.167 218.97C360.899 217.146 363.007 215.473 365.493 213.953C367.197 212.902 368.719 211.755 370.059 210.511C371.428 209.239 372.503 207.829 373.285 206.281C374.067 204.705 374.458 202.95 374.458 201.015C374.458 198.831 373.941 196.937 372.908 195.334C371.875 193.73 370.478 192.486 368.719 191.602C366.987 190.717 365.046 190.275 362.896 190.275C360.913 190.275 359.027 190.703 357.24 191.56C355.48 192.389 354.014 193.661 352.841 195.375C351.986 196.635 351.411 198.15 351.116 199.919C350.942 200.967 350.082 201.803 349.019 201.803H338.054C336.929 201.803 336.018 200.873 336.117 199.752C336.502 195.396 337.748 191.684 339.854 188.616C342.312 185.077 345.552 182.423 349.574 180.654C353.623 178.885 358.092 178 362.979 178C368.314 178 373.006 178.926 377.055 180.778C381.133 182.631 384.303 185.271 386.565 188.699C388.855 192.099 390 196.135 390 200.807C390 203.959 389.484 206.779 388.45 209.267C387.445 211.755 386.006 213.967 384.135 215.902C382.264 217.837 380.044 219.565 377.474 221.085C375.212 222.468 373.355 223.905 371.903 225.398C370.478 226.891 369.417 228.646 368.719 230.664C368.048 232.655 367.699 235.115 367.671 238.046L-nan -nanL367.671 238.046C367.671 238.641 367.189 239.124 366.593 239.124H354.799ZM361.01 265C358.497 265 356.332 264.115 354.517 262.346C352.702 260.577 351.794 258.42 351.794 255.877C351.794 253.389 352.702 251.26 354.517 249.491C356.332 247.722 358.497 246.837 361.01 246.837C363.496 246.837 365.646 247.722 367.462 249.491C369.305 251.26 370.227 253.389 370.227 255.877C370.227 257.563 369.794 259.098 368.928 260.48C368.09 261.862 366.973 262.968 365.577 263.797C364.208 264.599 362.686 265 361.01 265Z" fill="#9ab7f6"/>
<path d="M61.0653 134.499C60.2714 134.499 59.6278 133.855 59.6278 133.061L-nan -nanL59.6278 133.061C59.665 126.389 60.2981 121.081 61.5269 117.137C62.793 113.193 64.5805 110.023 66.8892 107.627C69.198 105.194 72.0095 102.964 75.3237 100.937C77.5952 99.5364 79.6247 98.0067 81.4121 96.348C83.2368 94.6524 84.6704 92.7725 85.7131 90.7083C86.7558 88.6073 87.2771 86.2666 87.2771 83.6864C87.2771 80.7744 86.5882 78.2494 85.2104 76.1115C83.8326 73.9736 81.9707 72.3149 79.6247 71.1354C77.3159 69.9558 74.7279 69.3661 71.8605 69.3661C69.2166 69.3661 66.703 69.9374 64.3198 71.0801C61.9738 72.1859 60.0188 73.8815 58.4548 76.1668C57.2146 77.9933 56.4167 80.2211 56.061 82.8501C55.9186 83.9034 55.0528 84.7369 53.9899 84.7369H38.054C36.9287 84.7369 36.0197 83.8077 36.1007 82.6852C36.5414 76.5812 38.2209 71.4043 41.139 67.1544C44.416 62.4363 48.7356 58.8977 54.0979 56.5386C59.4975 54.1795 65.4556 53 71.9722 53C79.0847 53 85.3407 54.2348 90.7403 56.7045C96.177 59.1741 100.404 62.6943 103.42 67.265C106.473 71.7989 108 77.1805 108 83.4099C108 87.612 107.311 91.3718 105.933 94.6892C104.593 98.0067 102.675 100.956 100.18 103.536C97.6852 106.116 94.7248 108.42 91.2988 110.447C88.2826 112.29 85.8062 114.207 83.8698 116.197C81.9707 118.188 80.5556 120.528 79.6247 123.219C78.731 125.873 78.2655 129.154 78.2283 133.061L-nan -nanL78.2283 133.061C78.2283 133.855 77.5846 134.499 76.7907 134.499H61.0653ZM69.3469 169C65.9955 169 63.1096 167.82 60.6891 165.461C58.2686 163.102 57.0583 160.227 57.0583 156.836C57.0583 153.519 58.2686 150.68 60.6891 148.321C63.1096 145.962 65.9955 144.783 69.3469 144.783C72.6611 144.783 75.5285 145.962 77.949 148.321C80.4067 150.68 81.6355 153.519 81.6355 156.836C81.6355 159.085 81.0584 161.13 79.904 162.973C78.7868 164.816 77.2973 166.291 75.4354 167.397C73.6107 168.466 71.5812 169 69.3469 169Z" fill="#9ab7f6"/>
<path d="M143.5 551C133.9 555 130.167 559 129.5 560.5L130 565.5L137 566.5L155 564L185.5 558.5L186 553C184 542.6 184.833 529 185.5 523.5H163.5C163 539.5 155.5 546 143.5 551Z" fill="#F9FAFB"/>
<path d="M143.5 551C133.9 555 130.167 559 129.5 560.5L130 565.5L137 566.5L155 564L185.5 558.5L186 553C184 542.6 184.833 529 185.5 523.5H163.5C163 539.5 155.5 546 143.5 551Z" fill="url(#paint2_linear_411_1555)"/>
<path d="M182.5 568H130C125.2 568 127.667 562.667 129.5 560C129.1 564 133.667 565 136 565C146.5 565 153 559 166 559.5C179 560 187 546.5 188 557C188.8 565.4 184.667 567.833 182.5 568Z" fill="#111928"/>
<path d="M292.602 551C302.202 555 305.935 559 306.602 560.5L306.102 565.5L299.102 566.5L281.102 564L250.602 558.5L250.102 553C252.102 542.6 251.268 529 250.602 523.5H272.602C273.102 539.5 280.602 546 292.602 551Z" fill="#F9FAFB"/>
<path d="M292.602 551C302.202 555 305.935 559 306.602 560.5L306.102 565.5L299.102 566.5L281.102 564L250.602 558.5L250.102 553C252.102 542.6 251.268 529 250.602 523.5H272.602C273.102 539.5 280.602 546 292.602 551Z" fill="url(#paint3_linear_411_1555)"/>
<path d="M253.601 568H306.101C310.901 568 308.435 562.667 306.601 560C307.001 564 302.435 565 300.101 565C289.601 565 283.101 559 270.101 559.5C257.101 560 249.101 546.5 248.101 557C247.301 565.4 251.435 567.833 253.601 568Z" fill="#111928"/>
<path d="M203.5 46.5C203.5 50.5 207.833 53.5 210 54.5L211.5 61L224 91.5C227.833 89.1667 237.3 81.5 244.5 69.5C251.7 57.5 245.167 50.1667 241 48C241 42.5 232.5 36 223 36C213.5 36 203.5 41.5 203.5 46.5Z" fill="#111928"/>
<path d="M212.499 101.5V86L226.499 75.5C226.666 81.5 227.3 95.1 228.5 101.5C229.7 107.9 233.667 109.5 235.5 109.5C235.333 111.333 233.299 115.4 226.499 117C217.999 119 206.499 113 209.499 109.5C211.899 106.7 212.499 103 212.499 101.5Z" fill="#FDBA8C"/>
<path d="M212.499 101.5V86L226.499 75.5C226.666 81.5 227.3 95.1 228.5 101.5C229.7 107.9 233.667 109.5 235.5 109.5C235.333 111.333 233.299 115.4 226.499 117C217.999 119 206.499 113 209.499 109.5C211.899 106.7 212.499 103 212.499 101.5Z" fill="url(#paint4_linear_411_1555)"/>
<path d="M204.498 85.5C201.698 76.3 206.998 61 209.998 54.5C211.832 56 215.8 59.9 217 63.5C218.5 68 220.5 71.5 223 69.5C225.5 67.5 232 63.5 230 72.5C228 81.5 207.998 97 204.498 85.5Z" fill="#FDBA8C"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M206.016 68.348C206.319 68.4277 206.5 68.7379 206.42 69.0408L204.434 76.5873C204.399 76.722 204.496 76.8552 204.636 76.862L207.975 77.0238C208.288 77.0389 208.529 77.3048 208.514 77.6177C208.499 77.9306 208.233 78.172 207.92 78.1568L204.581 77.995C203.721 77.9534 203.118 77.1307 203.337 76.2985L205.323 68.7521C205.403 68.4492 205.713 68.2683 206.016 68.348Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M208.658 81.7042C209.375 81.6146 210.13 81.3974 211.281 81.0359C211.579 80.9421 211.898 81.1082 211.992 81.4071C212.085 81.706 211.919 82.0243 211.62 82.1182C210.479 82.4765 209.632 82.7257 208.799 82.8298C207.95 82.9358 207.142 82.8889 206.064 82.6967C205.756 82.6416 205.551 82.347 205.606 82.0386C205.661 81.7303 205.955 81.5249 206.264 81.58C207.269 81.7593 207.957 81.7918 208.658 81.7042Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M226.367 73.6716C226.709 73.0579 226.735 72.4156 226.64 71.7399C226.604 71.4786 226.786 71.2371 227.048 71.2007C227.309 71.1642 227.55 71.3465 227.587 71.6078C227.696 72.3883 227.685 73.2686 227.202 74.1363C226.721 74.9995 225.815 75.7775 224.285 76.4481C224.043 76.554 223.761 76.444 223.655 76.2023C223.549 75.9607 223.659 75.6789 223.901 75.573C225.318 74.9521 226.023 74.2899 226.367 73.6716Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M211.691 75.4112C212.267 74.3578 212.712 72.793 212.822 70.9736C212.932 69.1541 212.678 67.5471 212.233 66.4321C211.748 65.216 211.238 65.0062 211.086 64.997C210.933 64.9878 210.402 65.1347 209.774 66.2836C209.198 67.3369 208.753 68.9017 208.643 70.7212C208.533 72.5406 208.787 74.1477 209.232 75.2627C209.717 76.4788 210.227 76.6886 210.379 76.6978C210.531 76.707 211.063 76.5601 211.691 75.4112ZM210.303 77.9515C212.15 78.063 213.839 74.9728 214.075 71.0493C214.312 67.1258 213.008 63.8548 211.161 63.7433C209.315 63.6318 207.626 66.722 207.389 70.6455C207.152 74.5689 208.457 77.84 210.303 77.9515Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M203.572 73.3705C204.147 72.3172 204.593 70.7524 204.703 68.9329C204.813 67.1135 204.559 65.5064 204.114 64.3915C203.629 63.1754 203.119 62.9655 202.967 62.9563C202.814 62.9471 202.283 63.094 201.655 64.2429C201.079 65.2963 200.634 66.8611 200.524 68.6805C200.414 70.5 200.668 72.107 201.112 73.222C201.598 74.4381 202.108 74.648 202.26 74.6572C202.412 74.6664 202.944 74.5194 203.572 73.3705ZM202.184 75.9108C204.031 76.0223 205.719 72.9321 205.956 69.0086C206.193 65.0852 204.889 61.8142 203.042 61.7026C201.196 61.5911 199.507 64.6813 199.27 68.6048C199.033 72.5283 200.338 75.7993 202.184 75.9108Z" fill="#111928"/>
<path d="M234.432 73.1191C235.301 74.9647 238.819 80.4474 240.47 82.958L243.627 81.1476C241.894 79.0005 238.14 74.366 236.979 73.0047C235.529 71.303 233.347 70.8121 234.432 73.1191Z" fill="#FDBA8C"/>
<path d="M234.432 73.1191C235.301 74.9647 238.819 80.4474 240.47 82.958L243.627 81.1476C241.894 79.0005 238.14 74.366 236.979 73.0047C235.529 71.303 233.347 70.8121 234.432 73.1191Z" fill="url(#paint5_linear_411_1555)"/>
<path d="M253.998 106C248.498 93 242.997 91 239.997 87C237.597 83.8 241.33 80.3333 243.497 79C243.664 78 245.198 76 249.998 76C255.998 76 254.498 83 255.998 87C257.198 90.2 261.165 97.3333 262.998 100.5C261.831 106.667 258.398 116.4 253.998 106Z" fill="#FDBA8C"/>
<path d="M253.998 106C248.498 93 242.997 91 239.997 87C237.597 83.8 241.33 80.3333 243.497 79C243.664 78 245.198 76 249.998 76C255.998 76 254.498 83 255.998 87C257.198 90.2 261.165 97.3333 262.998 100.5C261.831 106.667 258.398 116.4 253.998 106Z" fill="url(#paint6_linear_411_1555)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M243.5 79C244.167 78.5 245.7 77.7 246.5 78.5C247.282 79.2823 243.029 84.9093 240.658 87.8073C241.367 88.6121 242.181 89.3673 243.077 90.1977C243.981 91.0361 244.968 91.9512 246.012 93.0717L256.659 88.5672C256.394 87.9813 256.169 87.4515 256 87C255.569 85.8508 255.386 84.454 255.201 83.0466C254.743 79.5556 254.276 76 250 76C245.203 76 243.667 77.9978 243.499 78.9984L243.5 79Z" fill="url(#paint7_linear_411_1555)"/>
<path d="M147.838 522.71L192.502 218.5H258.502L288.287 522.805C288.402 523.981 287.478 525 286.296 525H241.333C240.297 525 239.432 524.208 239.341 523.175L223.002 337.5L197.241 523.275C197.104 524.264 196.258 525 195.26 525H149.817C148.597 525 147.661 523.917 147.838 522.71Z" fill="#2563eb"/>
<path d="M147.838 522.71L192.502 218.5H258.502L288.287 522.805C288.402 523.981 287.478 525 286.296 525H241.333C240.297 525 239.432 524.208 239.341 523.175L223.002 337.5L197.241 523.275C197.104 524.264 196.258 525 195.26 525H149.817C148.597 525 147.661 523.917 147.838 522.71Z" fill="url(#paint8_linear_411_1555)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M286.788 507.5H237.961L237.785 505.5H286.592L286.788 507.5ZM150.07 507.5L150.364 505.5H199.705L199.428 507.5H150.07Z" fill="#9ab7f6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M215.73 227.687C208.717 235.112 199.093 237.648 195.089 237.996L194.916 236.004C198.579 235.685 207.689 233.288 214.276 226.313L215.73 227.687Z" fill="#2563eb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M235.274 227.687C242.287 235.112 251.911 237.648 255.915 237.996L256.088 236.004C252.425 235.685 243.315 233.288 236.728 226.313L235.274 227.687Z" fill="#2563eb"/>
<path d="M213.288 413.466C212.736 414.422 211.513 414.75 210.556 414.198L210.016 413.886C199.503 407.739 191.685 401.904 186.561 396.382C181.47 390.802 178.074 385.112 176.373 379.313C174.613 373.48 173.627 367.046 173.417 360.01C173.255 355.178 172.67 350.606 171.663 346.296C170.631 341.894 168.955 337.935 166.634 334.42C164.254 330.871 161.026 327.919 156.947 325.564C152.344 322.907 147.731 321.68 143.107 321.885C138.483 322.09 134.179 323.489 130.195 326.083C126.245 328.619 122.975 332.131 120.384 336.617C117.996 340.754 116.628 345.208 116.282 349.98C115.91 354.66 116.825 359.266 119.024 363.799C120.978 367.789 124.197 371.38 128.681 374.573C129.547 375.19 129.833 376.362 129.301 377.283L113.313 404.975C112.75 405.95 111.493 406.27 110.548 405.659C100.56 399.2 93.4227 391.536 89.1361 382.667C84.6385 373.234 82.9472 363.246 84.0623 352.703C85.211 342.102 88.7288 331.703 94.6157 321.506C101.041 310.378 108.644 301.716 117.426 295.522C126.241 289.269 135.623 285.868 145.573 285.32C155.498 284.68 165.383 287.202 175.23 292.887C181.872 296.722 187.193 301.231 191.192 306.415C195.225 311.54 198.154 317.232 199.979 323.49C201.803 329.749 202.771 336.483 202.88 343.694C203.069 350.095 203.862 355.719 205.259 360.565C206.689 365.353 209.111 369.703 212.523 373.616C215.911 377.436 220.676 381.158 226.819 384.782L227.359 385.094C228.315 385.647 228.643 386.87 228.091 387.826L213.288 413.466ZM275.604 431.477C272.576 436.721 268.105 440.16 262.189 441.794C256.274 443.428 250.636 442.698 245.275 439.603C240.032 436.576 236.639 432.092 235.096 426.152C233.554 420.212 234.296 414.62 237.324 409.376C240.318 404.19 244.772 400.78 250.688 399.146C256.637 397.453 262.234 398.121 267.477 401.148C271.032 403.2 273.744 405.97 275.614 409.459C277.518 412.889 278.503 416.565 278.569 420.487C278.611 424.318 277.622 427.981 275.604 431.477Z" fill="#2563eb"/>
<path d="M213.288 413.466C212.736 414.422 211.513 414.75 210.556 414.198L210.016 413.886C199.503 407.739 191.685 401.904 186.561 396.382C181.47 390.802 178.074 385.112 176.373 379.313C174.613 373.48 173.627 367.046 173.417 360.01C173.255 355.178 172.67 350.606 171.663 346.296C170.631 341.894 168.955 337.935 166.634 334.42C164.254 330.871 161.026 327.919 156.947 325.564C152.344 322.907 147.731 321.68 143.107 321.885C138.483 322.09 134.179 323.489 130.195 326.083C126.245 328.619 122.975 332.131 120.384 336.617C117.996 340.754 116.628 345.208 116.282 349.98C115.91 354.66 116.825 359.266 119.024 363.799C120.978 367.789 124.197 371.38 128.681 374.573C129.547 375.19 129.833 376.362 129.301 377.283L113.313 404.975C112.75 405.95 111.493 406.27 110.548 405.659C100.56 399.2 93.4227 391.536 89.1361 382.667C84.6385 373.234 82.9472 363.246 84.0623 352.703C85.211 342.102 88.7288 331.703 94.6157 321.506C101.041 310.378 108.644 301.716 117.426 295.522C126.241 289.269 135.623 285.868 145.573 285.32C155.498 284.68 165.383 287.202 175.23 292.887C181.872 296.722 187.193 301.231 191.192 306.415C195.225 311.54 198.154 317.232 199.979 323.49C201.803 329.749 202.771 336.483 202.88 343.694C203.069 350.095 203.862 355.719 205.259 360.565C206.689 365.353 209.111 369.703 212.523 373.616C215.911 377.436 220.676 381.158 226.819 384.782L227.359 385.094C228.315 385.647 228.643 386.87 228.091 387.826L213.288 413.466ZM275.604 431.477C272.576 436.721 268.105 440.16 262.189 441.794C256.274 443.428 250.636 442.698 245.275 439.603C240.032 436.576 236.639 432.092 235.096 426.152C233.554 420.212 234.296 414.62 237.324 409.376C240.318 404.19 244.772 400.78 250.688 399.146C256.637 397.453 262.234 398.121 267.477 401.148C271.032 403.2 273.744 405.97 275.614 409.459C277.518 412.889 278.503 416.565 278.569 420.487C278.611 424.318 277.622 427.981 275.604 431.477Z" fill="url(#paint9_linear_411_1555)"/>
<path d="M148.501 305.5C143.301 298.7 145.668 277.333 147.501 267.5L157.5 268C157.167 271.833 156.7 280.4 157.5 284C158.235 287.308 157.901 294.528 157.583 298.522C157.539 299.078 157.073 299.5 156.516 299.5C155.945 299.5 155.474 299.055 155.44 298.486L155 291C155 298.667 153.701 312.3 148.501 305.5Z" fill="#FDBA8C"/>
<path d="M148.501 305.5C143.301 298.7 145.668 277.333 147.501 267.5L157.5 268C157.167 271.833 156.7 280.4 157.5 284C158.235 287.308 157.901 294.528 157.583 298.522C157.539 299.078 157.073 299.5 156.516 299.5C155.945 299.5 155.474 299.055 155.44 298.486L155 291C155 298.667 153.701 312.3 148.501 305.5Z" fill="url(#paint10_linear_411_1555)"/>
<path d="M234.5 107.5C221.3 112.7 212.333 109 209.5 106.5C200.333 107.833 178.4 118.4 164 150C149.6 181.6 143 243.167 141.5 270L168 274C170 243.2 180.5 192.5 185.5 171L189.355 220.156C189.437 221.197 190.305 222 191.349 222H260C261.105 222 262 221.105 262 220V166C276 185 303 188 303.5 171C303.9 157.4 278.667 115.333 266 96L247 105.5L249.5 112.5L234.5 107.5Z" fill="#F9FAFB"/>
<path d="M234.5 107.5C221.3 112.7 212.333 109 209.5 106.5C200.333 107.833 178.4 118.4 164 150C149.6 181.6 143 243.167 141.5 270L168 274C170 243.2 180.5 192.5 185.5 171L189.355 220.156C189.437 221.197 190.305 222 191.349 222H260C261.105 222 262 221.105 262 220V166C276 185 303 188 303.5 171C303.9 157.4 278.667 115.333 266 96L247 105.5L249.5 112.5L234.5 107.5Z" fill="url(#paint11_linear_411_1555)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M155.504 174.877C156.31 171.835 157.154 168.876 158.037 166.024C158.462 166.927 158.699 167.936 158.699 169C158.699 171.463 157.427 173.629 155.504 174.877ZM150.222 198.523C150.963 194.605 151.755 190.716 152.598 186.899L158.699 193L151.699 200L150.222 198.523ZM146.429 221.607C146.925 218.116 147.459 214.582 148.033 211.036C149.099 210.379 150.355 210 151.699 210C155.565 210 158.699 213.134 158.699 217C158.699 220.866 155.565 224 151.699 224C149.597 224 147.712 223.074 146.429 221.607ZM153.389 271.795L148.081 270.993C146.054 269.767 144.699 267.542 144.699 265C144.699 261.134 147.833 258 151.699 258C155.565 258 158.699 261.134 158.699 265C158.699 268.283 156.439 271.039 153.389 271.795ZM168.766 264.933C168.762 264.974 168.758 265.014 168.754 265.055L168.699 265L168.766 264.933ZM172.928 234.57C172.283 238.513 171.673 242.436 171.112 246.287C169.634 245.004 168.699 243.111 168.699 241C168.699 238.118 170.441 235.643 172.928 234.57ZM177.05 211.351C176.314 215.226 175.584 219.186 174.875 223.176L168.699 217L175.699 210L177.05 211.351ZM181.51 189.095C180.856 192.201 180.179 195.479 179.492 198.884C178.399 199.59 177.097 200 175.699 200C171.833 200 168.699 196.866 168.699 193C168.699 189.134 171.833 186 175.699 186C178.119 186 180.253 187.228 181.51 189.095ZM204.598 222H194.8C193.504 220.729 192.699 218.959 192.699 217C192.699 213.134 195.833 210 199.699 210C203.565 210 206.699 213.134 206.699 217C206.699 218.959 205.895 220.729 204.598 222ZM225.699 222H221.699L216.699 217L223.699 210L230.699 217L225.699 222ZM252.598 222H242.8C241.504 220.729 240.699 218.959 240.699 217C240.699 213.134 243.833 210 247.699 210C251.565 210 254.699 213.134 254.699 217C254.699 218.959 253.895 220.729 252.598 222ZM272.001 175.698C271.206 175.149 270.42 174.565 269.647 173.948L264.699 169L271.699 162L278.699 169L272.001 175.698ZM292.539 141.16C294.2 144.401 295.746 147.564 297.123 150.576L295.699 152L288.699 145L292.539 141.16ZM271.113 103.976C267.521 103.678 264.699 100.669 264.699 97C264.699 96.8811 264.702 96.763 264.708 96.6456L265.999 96C267.541 98.3527 269.268 101.042 271.113 103.976ZM247.699 186L254.699 193L247.699 200L240.699 193L247.699 186ZM230.699 193C230.699 196.866 227.565 200 223.699 200C219.833 200 216.699 196.866 216.699 193C216.699 189.134 219.833 186 223.699 186C227.565 186 230.699 189.134 230.699 193ZM206.699 193L199.699 186L192.699 193L199.699 200L206.699 193ZM151.699 234L158.699 241L151.699 248L144.699 241L151.699 234ZM199.699 128C195.833 128 192.699 124.866 192.699 121C192.699 117.134 195.833 114 199.699 114C203.565 114 206.699 117.134 206.699 121C206.699 124.866 203.565 128 199.699 128ZM223.699 114L216.699 121L223.699 128L230.699 121L223.699 114ZM240.699 121C240.699 124.866 243.833 128 247.699 128C251.565 128 254.699 124.866 254.699 121C254.699 117.134 251.565 114 247.699 114C243.833 114 240.699 117.134 240.699 121ZM264.699 121L271.699 114L278.699 121L271.699 128L264.699 121ZM271.699 152C275.565 152 278.699 148.866 278.699 145C278.699 141.134 275.565 138 271.699 138C267.833 138 264.699 141.134 264.699 145C264.699 148.866 267.833 152 271.699 152ZM247.699 138L254.699 145L247.699 152L240.699 145L247.699 138ZM175.699 152C179.565 152 182.699 148.866 182.699 145C182.699 141.134 179.565 138 175.699 138C171.833 138 168.699 141.134 168.699 145C168.699 148.866 171.833 152 175.699 152ZM230.699 145C230.699 148.866 227.565 152 223.699 152C219.833 152 216.699 148.866 216.699 145C216.699 141.134 219.833 138 223.699 138C227.565 138 230.699 141.134 230.699 145ZM206.699 145L199.699 138L192.699 145L199.699 152L206.699 145ZM199.699 176C195.833 176 192.699 172.866 192.699 169C192.699 165.134 195.833 162 199.699 162C203.565 162 206.699 165.134 206.699 169C206.699 172.866 203.565 176 199.699 176ZM168.699 169L175.699 162L182.699 169L175.699 176L168.699 169ZM223.699 162L216.699 169L223.699 176L230.699 169L223.699 162ZM295.699 176C291.833 176 288.699 172.866 288.699 169C288.699 165.134 291.833 162 295.699 162C299.565 162 302.699 165.134 302.699 169C302.699 172.866 299.565 176 295.699 176ZM240.699 169C240.699 172.866 243.833 176 247.699 176C251.565 176 254.699 172.866 254.699 169C254.699 165.134 251.565 162 247.699 162C243.833 162 240.699 165.134 240.699 169Z" fill="url(#paint12_linear_411_1555)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M230.355 108.891C231.685 108.523 233.067 108.064 234.499 107.5L249.499 112.5L246.999 105.5L265.499 156.5L234.499 126.5L230.355 108.891Z" fill="url(#paint13_linear_411_1555)"/>
<defs>
<linearGradient id="paint0_linear_411_1555" x1="210.624" y1="184.351" x2="210.624" y2="586.317" gradientUnits="userSpaceOnUse">
<stop stop-color="white" stop-opacity="0"/>
<stop offset="1" stop-color="white"/>
</linearGradient>
<linearGradient id="paint1_linear_411_1555" x1="243.75" y1="414" x2="243.75" y2="138" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint2_linear_411_1555" x1="192" y1="494" x2="113.482" y2="524.556" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint3_linear_411_1555" x1="244.102" y1="494" x2="322.619" y2="524.556" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint4_linear_411_1555" x1="225.826" y1="67.4096" x2="225.826" y2="107.604" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint5_linear_411_1555" x1="255.5" y1="94" x2="238.5" y2="76.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint6_linear_411_1555" x1="263.998" y1="111" x2="252.998" y2="91.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint7_linear_411_1555" x1="239.002" y1="79.5" x2="243.466" y2="85.2022" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint8_linear_411_1555" x1="218.002" y1="372" x2="218.002" y2="99" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928" stop-opacity="0"/>
<stop offset="1" stop-color="#111928"/>
</linearGradient>
<linearGradient id="paint9_linear_411_1555" x1="96" y1="292" x2="237" y2="388" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928" stop-opacity="0"/>
<stop offset="1" stop-color="#111928"/>
</linearGradient>
<linearGradient id="paint10_linear_411_1555" x1="163" y1="221.5" x2="186.724" y2="267.957" gradientUnits="userSpaceOnUse">
<stop stop-color="#7F270F"/>
<stop offset="1" stop-color="#7F270F" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint11_linear_411_1555" x1="204.414" y1="329.5" x2="239.455" y2="208.115" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa"/>
<stop offset="1" stop-color="#c8d8fa" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint12_linear_411_1555" x1="223.699" y1="96" x2="223.699" y2="271.795" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa" stop-opacity="0"/>
<stop offset="1" stop-color="#c8d8fa"/>
</linearGradient>
<linearGradient id="paint13_linear_411_1555" x1="250" y1="133" x2="266" y2="124.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa" stop-opacity="0"/>
<stop offset="1" stop-color="#c8d8fa"/>
</linearGradient>
</defs>
</svg>
          </template>
          <div id="globalSearchLoading" class="hidden px-4 py-8 text-center text-sm text-gray-500 dark:text-primary-240">
            <svg class="animate-spin h-6 w-6 mx-auto mb-3 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Suche…
          </div>
        </div>
    </div>
      <?php if ($isNavUnsavedChangesPage): ?>
    <!-- Bearbeitungs-Banner (Kunde/Firma/Gerät): erscheint mit Animation, sobald eine Änderung gemacht wurde -->
    <div id="navUnsavedChangesBar" class="absolute inset-0 flex items-center justify-between gap-2 w-full px-3 py-1.5 rounded-lg border border-primary-120 dark:border-primary-320 bg-white dark:bg-primary-100 text-primary-850 dark:text-primary-210 nav-unsaved-changes-bar-hidden">
      <div class="flex items-center gap-2 min-w-0">
        <span class="shrink-0 flex items-center justify-center w-5 h-5 rounded bg-primary-120/80 dark:bg-primary-300/80 text-primary-420 dark:text-primary-250" aria-hidden="true">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </span>
        <span class="text-xs font-medium truncate">Nicht gespeicherte Änderungen</span>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <button type="button" id="navUnsavedChangesDiscard" class="px-2 py-1 text-xs font-medium rounded-md border border-primary-120 dark:border-primary-230 bg-white dark:bg-primary-100/90 text-primary-850 dark:text-primary-250 hover:bg-primary-50 dark:hover:bg-primary-140 transition-colors">
          Verwerfen
        </button>
        <button type="button" id="navUnsavedChangesSave" class="px-2 py-1 text-xs font-medium rounded-md bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480 hover:bg-primaryLight-440 dark:hover:bg-primary-440 transition-colors">
          Speichern
        </button>
      </div>
    </div>
      <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

<script>
(function(){
if (typeof baseUrl === 'undefined') { var baseUrl = '<?php echo BASE_URL; ?>'; }
var globalSearchScope = [];
fetch(baseUrl + 'settings/api/global-search-scope.php')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && Array.isArray(data.scope)) {
            globalSearchScope = data.scope;
        }
    })
    .catch(function() {});

var globalSearchInstances = [];
var globalSearchShortcutBound = false;

function setupGlobalSearch(isMobile) {
    var S = isMobile ? 'Mobile' : '';
    function gid(n) { return document.getElementById(n + S); }
    var searchInput = gid('globalSearchInput');
    var resultsContainer = gid('globalSearchResults');
    if (!searchInput || !resultsContainer) return;
    var resultsList = gid('globalSearchResultsList');
    var resultsContent = gid('globalSearchResultsContent');
    var pillsInner = gid('globalSearchPillsInner');
    var pillsContainer = gid('globalSearchPills');
    var emptyEl = gid('globalSearchEmpty');
    var loadingEl = gid('globalSearchLoading');
    var moreEl = gid('globalSearchMore');
    var moreLink = gid('globalSearchMoreLink');
    var emptyIllustrationTpl = gid('globalSearchEmptyIllustrationTpl');
    var searchWrapper = gid('globalSearchWrapper');
    var debounceTimer = null, lastSearchResults = [], selectedFilterType = null, activeResultIndex = -1;
    /* Icons wie assets/frontend/sidebar_nav_content.php (Benutzer: admin/index Benutzerverwaltung) */
    var typeConfig = { ticket: { label: 'Tickets', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z' }, aufgabe: { label: 'Aufgaben', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' }, geraet: { label: 'Geräte', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z' }, bestellung: { label: 'Bestellungen', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8' }, firma: { label: 'Firmen', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z' }, kunde: { label: 'Kunden', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z' }, artikel: { label: 'Wissensdatenbank', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5c-1.747 0-3.332.477-4.5 1.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' }, projekt: { label: 'Projekte', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z' }, benutzer: { label: 'Benutzer', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z' }, inventar: { label: 'Lager', pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200', icon: 'M9.143 4H4.857A.857.857 0 0 0 4 4.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 10 9.143V4.857A.857.857 0 0 0 9.143 4Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 20 9.143V4.857A.857.857 0 0 0 19.143 4Zm-10 10H4.857a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286A.857.857 0 0 0 9.143 14Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286a.857.857 0 0 0-.857-.857Z' } };
    var typeLabels = { ticket: 'Tickets', aufgabe: 'Aufgabe', geraet: 'Gerät', bestellung: 'Bestellung', firma: 'Firma', kunde: 'Kunde', artikel: 'Wissensdatenbank', projekt: 'Projekt', benutzer: 'Benutzer', inventar: 'Lager' };
    var typePillOrder = ['ticket', 'geraet', 'kunde', 'firma', 'inventar', 'artikel', 'aufgabe', 'bestellung', 'projekt', 'benutzer'];
    function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    /** Wie ticket_search_plaintext_helpers: Leerzeichen normalisieren, für Fuzzy-Highlighting */
    function globalSearchNormalizeWs(s) {
        return String(s).replace(/[\u200B-\u200D\uFEFF]/g, '').replace(/\s+/g, ' ').trim();
    }
    /** Gleiche Kompakt-Map wie serverseitige Suche (ohne Whitespace, Indexe im Originaltext) */
    function globalSearchBuildCompactMap(text) {
        var lower = String(text).toLocaleLowerCase('de-DE');
        var compact = '';
        var map = [];
        for (var i = 0; i < lower.length; i++) {
            var c = lower.charAt(i);
            if (/\s/.test(c)) continue;
            compact += c;
            map.push(i);
        }
        return { compact: compact, map: map };
    }
    /** Erster Tippfehler-Treffer (search.php / ticket_search_plaintext_fuzzy_matches_normalized) — gibt Start/Ende im Originaltext zurück */
    function globalSearchFuzzyMatchRange(text, rawQueryTrimmed) {
        var n = globalSearchNormalizeWs(String(rawQueryTrimmed).toLocaleLowerCase('de-DE'));
        var qf = n.replace(/\s/g, '');
        if (qf.length < 3) return null;
        if (qf.indexOf('%') !== -1 || qf.indexOf('_') !== -1) return null;
        var bm = globalSearchBuildCompactMap(text);
        var hayNorm = bm.compact;
        var map = bm.map;
        var hayLen = hayNorm.length;
        var len = qf.length;
        if (hayLen === 0) return null;
        var j;
        var sub;
        var max1 = Math.min(25, len);
        for (var i = 0; i < max1; i++) {
            var before = qf.substring(0, i);
            var after = qf.substring(i + 1);
            var bl = before.length;
            var al = after.length;
            var total = bl + 1 + al;
            if (hayLen < total) continue;
            var maxStart = hayLen - total;
            for (j = 0; j <= maxStart; j++) {
                sub = hayNorm.substring(j, j + total);
                if (sub.substring(0, bl) !== before) continue;
                if (sub.substring(bl + 1, bl + 1 + al) !== after) continue;
                return { start: map[j], end: map[j + total - 1] + 1 };
            }
        }
        var numPairs = Math.min(Math.pow(2, Math.min(4, Math.max(0, Math.floor((len - 2) / 2)))), len * (len - 1) / 2, 35);
        var count = 0;
        for (i = 0; i < len - 1 && count < numPairs; i++) {
            for (var jj = i + 1; jj < len && count < numPairs; jj++, count++) {
                before = qf.substring(0, i);
                var mid = qf.substring(i + 1, jj);
                after = qf.substring(jj + 1);
                bl = before.length;
                var ml = mid.length;
                al = after.length;
                total = bl + 1 + ml + 1 + al;
                if (hayLen < total) continue;
                maxStart = hayLen - total;
                for (j = 0; j <= maxStart; j++) {
                    sub = hayNorm.substring(j, j + total);
                    if (sub.substring(0, bl) !== before) continue;
                    if (sub.substring(bl + 1, bl + 1 + ml) !== mid) continue;
                    if (sub.substring(bl + 1 + ml + 1, bl + 1 + ml + 1 + al) !== after) continue;
                    return { start: map[j], end: map[j + total - 1] + 1 };
                }
            }
        }
        if (len >= 5) {
            var numTriples = Math.min(18, len * (len - 1) * (len - 2) / 6);
            var count3 = 0;
            for (i = 0; i < len - 2 && count3 < numTriples; i++) {
                for (var jj2 = i + 1; jj2 < len - 1 && count3 < numTriples; jj2++) {
                    for (var k = jj2 + 1; k < len && count3 < numTriples; k++, count3++) {
                        var p1 = qf.substring(0, i);
                        var p2 = qf.substring(i + 1, jj2);
                        var p3 = qf.substring(jj2 + 1, k);
                        var p4 = qf.substring(k + 1);
                        var l1 = p1.length;
                        var l2 = p2.length;
                        var l3 = p3.length;
                        var l4 = p4.length;
                        total = l1 + 1 + l2 + 1 + l3 + 1 + l4;
                        if (hayLen < total) continue;
                        maxStart = hayLen - total;
                        for (j = 0; j <= maxStart; j++) {
                            sub = hayNorm.substring(j, j + total);
                            if (sub.substring(0, l1) !== p1) continue;
                            if (sub.substring(l1 + 1, l1 + 1 + l2) !== p2) continue;
                            if (sub.substring(l1 + 1 + l2 + 1, l1 + 1 + l2 + 1 + l3) !== p3) continue;
                            if (sub.substring(l1 + 1 + l2 + 1 + l3 + 1) !== p4) continue;
                            return { start: map[j], end: map[j + total - 1] + 1 };
                        }
                    }
                }
            }
        }
        return null;
    }
    /** Exakte Treffer des Suchtexts (trim, Groß/Klein egal, de-DE) als fett — nur HTML-escapen, nie Roh-HTML */
    function highlightExactQuery(text, rawQuery) {
        if (!text) return '';
        var q = (rawQuery || '').trim();
        if (!q) return esc(text);
        var lt = text.toLocaleLowerCase('de-DE');
        var lq = q.toLocaleLowerCase('de-DE');
        var out = '';
        var pos = 0;
        var idx;
        while ((idx = lt.indexOf(lq, pos)) !== -1) {
            out += esc(text.substring(pos, idx));
            out += '<strong class="font-bold text-primary-600 dark:text-primary-400">' + esc(text.substring(idx, idx + q.length)) + '</strong>';
            pos = idx + q.length;
        }
        out += esc(text.substring(pos));
        return out;
    }
    /** Mehrwort-Highlight: markiert alle einzelnen Suchbegriffe im Text (z.B. "Papierstau Ameos"). */
    function highlightQueryTerms(text, rawQuery) {
        if (!text) return null;
        var terms = String(rawQuery || '')
            .split(/\s+/)
            .map(function(t) { return t.replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '').trim(); })
            .filter(function(t) { return t.length >= 2; });
        if (!terms.length) return null;
        var lt = text.toLocaleLowerCase('de-DE');
        var ranges = [];
        terms.forEach(function(term) {
            var lterm = term.toLocaleLowerCase('de-DE');
            var pos = 0;
            var idx;
            while ((idx = lt.indexOf(lterm, pos)) !== -1) {
                ranges.push({ start: idx, end: idx + term.length });
                pos = idx + lterm.length;
            }
        });
        if (!ranges.length) return null;
        ranges.sort(function(a, b) {
            if (a.start !== b.start) return a.start - b.start;
            return a.end - b.end;
        });
        var merged = [];
        ranges.forEach(function(r) {
            var last = merged.length ? merged[merged.length - 1] : null;
            if (!last || r.start > last.end) {
                merged.push({ start: r.start, end: r.end });
                return;
            }
            if (r.end > last.end) last.end = r.end;
        });
        var out = '';
        var cursor = 0;
        merged.forEach(function(r) {
            out += esc(text.substring(cursor, r.start));
            out += '<strong class="font-bold text-primary-600 dark:text-primary-400">' + esc(text.substring(r.start, r.end)) + '</strong>';
            cursor = r.end;
        });
        out += esc(text.substring(cursor));
        return out;
    }
    /** Wie highlightExactQuery, plus Markierung bei Tippfehler-Treffern (gleiche Logik wie serverseitige Fuzzy-Suche) */
    function highlightGlobalSearchQuery(text, rawQuery) {
        if (!text) return '';
        var q = (rawQuery || '').trim();
        if (!q) return esc(text);
        var lt = text.toLocaleLowerCase('de-DE');
        var lq = q.toLocaleLowerCase('de-DE');
        if (lq !== '' && lt.indexOf(lq) !== -1) {
            return highlightExactQuery(text, rawQuery);
        }
        var termHighlight = highlightQueryTerms(text, q);
        if (termHighlight !== null) {
            return termHighlight;
        }
        var fr = globalSearchFuzzyMatchRange(text, q);
        if (fr) {
            return esc(text.substring(0, fr.start)) + '<strong class="font-bold text-primary-600 dark:text-primary-400">' + esc(text.substring(fr.start, fr.end)) + '</strong>' + esc(text.substring(fr.end));
        }
        return esc(text);
    }
    /** Gleiche Farben wie tickets/index.php getStatusBadgeClass / getStatusBadge */
    function ticketStatusSearchBadgeClasses(status) {
        var map = {
            'Neu': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'In Bearbeitung': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'Warteschlange': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            'Geplant': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'Bestellung offen': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            'Geschlossen': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            'Archiv': 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
        };
        var k = (status || '').trim();
        return map[k] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }
    /** Wie todos/index.php Drawer – Status-Farben für Aufgaben */
    function todoStatusSearchBadgeClasses(raw) {
        var map = {
            offen: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            in_bearbeitung: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            erledigt: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
        };
        var k = (raw || '').trim();
        return map[k] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }
    function formatTodoSearchDue(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    /** Meta-Zeile wie Aufgabenliste (index.php): Projekt, Beschreibung, Ticket, Fällig, Firma, Anhänge */
    function buildTodoSearchMetaHtml(r, hq) {
        var parts = [];
        var sep = '<span class="text-gray-400 dark:text-primary-250">|</span>';
        var pid = r.todo_project_id;
        var pnum = r.todo_project_nummer != null && r.todo_project_nummer !== '' ? String(r.todo_project_nummer).trim() : '';
        if (pid && pnum) {
            parts.push('<span class="whitespace-nowrap inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-primary-240 flex-shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/></svg><span class="text-primary-600 dark:text-primary-400">#' + esc(pnum) + '</span></span>');
        }
        var hasDesc = r.todo_has_description === true || r.todo_has_description === 1;
        var hasTicket = r.todo_ticket_nummer && String(r.todo_ticket_nummer).trim();
        var due = formatTodoSearchDue(r.todo_faellig_am);
        var comp = (r.todo_company_name && String(r.todo_company_name).trim()) || '';
        var att = parseInt(r.todo_attachment_count, 10) || 0;
        if (hasDesc) {
            if (parts.length) parts.push(sep);
            parts.push('<span class="whitespace-nowrap inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-primary-240" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg><span>Beschreibung</span></span>');
        }
        if (hasTicket) {
            if (parts.length) parts.push(sep);
            parts.push('<span class="whitespace-nowrap inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-primary-240" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/></svg><span class="text-primary-600 dark:text-primary-400">#' + highlightGlobalSearchQuery(String(r.todo_ticket_nummer).trim(), hq) + '</span></span>');
        }
        if (due) {
            if (parts.length) parts.push(sep);
            parts.push('<span class="whitespace-nowrap inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-primary-240" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z"/></svg>' + esc(due) + '</span>');
        }
        if (comp) {
            if (parts.length) parts.push(sep);
            parts.push('<span class="inline-flex items-center gap-1 min-w-0 max-w-full"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-primary-240 flex-shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg><span class="truncate">' + highlightGlobalSearchQuery(comp, hq) + '</span></span>');
        }
        if (att > 0) {
            if (parts.length) parts.push(sep);
            parts.push('<span class="whitespace-nowrap inline-flex items-center gap-1 text-gray-500 dark:text-primary-240"><svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8v8a5 5 0 1 0 10 0V6.5a3.5 3.5 0 1 0-7 0V15a2 2 0 0 0 4 0V8"/></svg><span>' + att + '</span></span>');
        }
        if (!parts.length) return '';
        return '<div class="flex items-center gap-2 text-[11px] text-gray-500 dark:text-primary-240 mt-1 flex-wrap leading-snug">' + parts.join('') + '</div>';
    }
    function showLoading() { activeResultIndex = -1; resultsContainer.classList.remove('hidden'); if (resultsContent) resultsContent.classList.add('hidden'); if (pillsContainer) pillsContainer.classList.add('hidden'); emptyEl.classList.add('hidden'); if (moreEl) moreEl.classList.add('hidden'); loadingEl.classList.remove('hidden'); }
    function buildNoResultSuggestions(rawQuery) {
        var raw = String(rawQuery || '').trim();
        if (!raw) return [];
        var suggestions = [];
        var seen = {};
        function addSuggestion(s) {
            var candidate = String(s || '').trim();
            if (candidate.length < 2) return;
            var key = candidate.toLocaleLowerCase('de-DE');
            if (seen[key]) return;
            seen[key] = true;
            suggestions.push(candidate);
        }
        var cleaned = raw
            .replace(/[\u200B-\u200D\uFEFF]/g, '')
            .replace(/\s+/g, ' ')
            .replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '');
        addSuggestion(cleaned);
        var terms = cleaned.split(/\s+/).filter(function(t) { return t.trim() !== ''; });
        if (terms.length > 1) {
            var noShortTerms = terms.filter(function(t) { return t.length >= 3; });
            if (noShortTerms.length > 0 && noShortTerms.length < terms.length) {
                addSuggestion(noShortTerms.join(' '));
            }
            // Zusammenschreiben nur sehr zurückhaltend (max. 2 sinnvolle Wörter),
            // damit keine künstlichen/irreführenden Vorschläge entstehen.
            if (terms.length <= 2 && terms.every(function(t) { return t.length >= 3; })) {
                addSuggestion(terms.join(''));
            }
        }
        if (/[-_.]/.test(cleaned)) {
            addSuggestion(cleaned.replace(/[-_.]+/g, ' '));
        }
        var splitAlphaNum = cleaned
            .replace(/([A-Za-zÄÖÜäöüß])(\d)/g, '$1 $2')
            .replace(/(\d)([A-Za-zÄÖÜäöüß])/g, '$1 $2');
        addSuggestion(splitAlphaNum);
        return suggestions.filter(function(v) {
            return v.toLocaleLowerCase('de-DE') !== raw.toLocaleLowerCase('de-DE');
        }).slice(0, 5);
    }
    function showEmpty(message, suggestions) {
        activeResultIndex = -1;
        resultsContainer.classList.remove('hidden');
        if (resultsContent) resultsContent.classList.add('hidden');
        if (pillsContainer) pillsContainer.classList.add('hidden');
        loadingEl.classList.add('hidden');
        if (moreEl) moreEl.classList.add('hidden');
        if (emptyEl) {
            var text = message || 'Keine Ergebnisse gefunden.';
            var list = Array.isArray(suggestions) ? suggestions.filter(function(s) { return String(s || '').trim().length >= 2; }) : [];
            var html = '';
            if (emptyIllustrationTpl && emptyIllustrationTpl.innerHTML) {
                html += '<div class="flex justify-center">' + emptyIllustrationTpl.innerHTML + '</div>';
            }
            html += '<div id="globalSearchEmptyText" class="text-base text-gray-700 dark:text-primary-220">' + esc(text) + '</div>';
            if (list.length > 0) {
                html += '<div class="mt-3 text-sm text-gray-700 dark:text-primary-220">Meintest du vielleicht:</div>';
                html += '<div class="mt-2 flex flex-wrap justify-center gap-2">';
                html += list.map(function(s) {
                    return '<button type="button" class="global-search-suggestion-btn px-3 py-1.5 rounded-full text-sm font-medium border border-gray-300 dark:border-primary-320 bg-gray-100 hover:bg-gray-200 dark:bg-primary-200/50 dark:hover:bg-primary-180 text-gray-800 dark:text-primary-210 transition-colors" data-query="' + esc(s) + '">' + esc(s) + '</button>';
                }).join('');
                html += '</div>';
            }
            emptyEl.innerHTML = html;
        }
        emptyEl.classList.remove('hidden');
    }
    function getVisibleResultLinks() {
        if (!resultsList) return [];
        return Array.prototype.slice.call(resultsList.querySelectorAll('a[href]'));
    }
    function updateActiveResultVisualState() {
        var links = getVisibleResultLinks();
        links.forEach(function(link, idx) {
            var isActive = idx === activeResultIndex;
            link.classList.toggle('bg-gray-50', isActive);
            link.classList.toggle('dark:bg-primary-140', isActive);
        });
    }
    function setActiveResultIndex(nextIdx) {
        var links = getVisibleResultLinks();
        if (!links.length) {
            activeResultIndex = -1;
            return;
        }
        if (nextIdx < 0) nextIdx = 0;
        if (nextIdx >= links.length) nextIdx = links.length - 1;
        activeResultIndex = nextIdx;
        updateActiveResultVisualState();
        if (links[activeResultIndex]) {
            links[activeResultIndex].scrollIntoView({ block: 'nearest' });
        }
    }
    function navigateResults(direction) {
        var links = getVisibleResultLinks();
        if (!links.length) return false;
        if (activeResultIndex === -1) {
            activeResultIndex = direction > 0 ? 0 : links.length - 1;
        } else {
            activeResultIndex += direction;
            if (activeResultIndex < 0) activeResultIndex = links.length - 1;
            if (activeResultIndex >= links.length) activeResultIndex = 0;
        }
        updateActiveResultVisualState();
        if (links[activeResultIndex]) {
            links[activeResultIndex].scrollIntoView({ block: 'nearest' });
        }
        return true;
    }
    function navigatePills(direction) {
        if (!pillsInner) return false;
        var pills = Array.prototype.slice.call(pillsInner.querySelectorAll('button[data-filter-type]'));
        if (!pills.length) return false;
        var selected = selectedFilterType || '';
        var currentIdx = pills.findIndex(function(btn) { return (btn.getAttribute('data-filter-type') || '') === selected; });
        if (currentIdx < 0) currentIdx = 0;
        var nextIdx = currentIdx + direction;
        if (nextIdx < 0) nextIdx = pills.length - 1;
        if (nextIdx >= pills.length) nextIdx = 0;
        var nextType = pills[nextIdx].getAttribute('data-filter-type') || '';
        applyFilter(nextType === '' ? null : nextType);
        pills[nextIdx].scrollIntoView({ block: 'nearest', inline: 'nearest' });
        activeResultIndex = -1;
        updateActiveResultVisualState();
        return true;
    }
    function renderItems(items) {
        var hq = searchInput.value.trim();
        resultsList.innerHTML = items.map(function(r) {
            var tc = typeConfig[r.type] || { icon: 'M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z', label: typeLabels[r.type] || r.type };
            var sub = '';
            if (r.type === 'ticket') {
                var tL1 = r.ticket_subtitle_line1 ? String(r.ticket_subtitle_line1).trim() : '';
                var tL2 = r.ticket_subtitle_line2 ? String(r.ticket_subtitle_line2).trim() : '';
                if (tL1) sub += '<div class="text-[11px] text-gray-400 dark:text-primary-250 mt-0.5 leading-snug break-words">' + highlightGlobalSearchQuery(tL1, hq) + '</div>';
                if (tL2) sub += '<div class="text-[11px] text-gray-400 dark:text-primary-250 mt-0.5 leading-snug break-words">' + highlightGlobalSearchQuery(tL2, hq) + '</div>';
            } else if (r.type === 'aufgabe') {
                sub = buildTodoSearchMetaHtml(r, hq);
            } else if (r.subtitle) {
                sub = '<div class="text-xs text-gray-500 dark:text-primary-240 mt-0.5 leading-relaxed break-words" title="' + esc(r.subtitle) + '">' + highlightGlobalSearchQuery(r.subtitle, hq) + '</div>';
            }
            var rightBadge = '';
            if (r.type === 'inventar' && typeof r.lagerbestand !== 'undefined') {
                var stock = parseInt(r.lagerbestand, 10);
                var badgeCls = stock > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
                rightBadge = '<span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold ' + badgeCls + '">' + stock + '</span>';
            } else if (r.type === 'ticket') {
                var stRaw = (r.ticket_status && String(r.ticket_status).trim()) ? String(r.ticket_status).trim() : '—';
                var numRaw = r.ticket_nummer ? String(r.ticket_nummer).trim() : '';
                var afT = r.ticket_anforderer ? String(r.ticket_anforderer).trim() : '';
                var ticketStatusCls = 'inline-flex items-center max-w-full truncate px-2 py-1 text-xs font-semibold rounded-full ' + ticketStatusSearchBadgeClasses(stRaw === '—' ? '' : stRaw);
                rightBadge = '<div class="shrink-0 flex flex-col items-end gap-0.5 text-right max-w-[10rem]"><span class="' + ticketStatusCls + '">' + esc(stRaw) + '</span>' + (numRaw ? '<span class="text-[10px] text-gray-400 dark:text-primary-250 font-mono tabular-nums leading-tight opacity-90">' + highlightGlobalSearchQuery(numRaw, hq) + '</span>' : '') + (afT ? '<span class="text-[10px] text-gray-400 dark:text-primary-250 leading-tight line-clamp-2 max-w-full text-right" title="' + esc(afT) + '">' + highlightGlobalSearchQuery(afT, hq) + '</span>' : '') + '</div>';
            } else if (r.type === 'aufgabe') {
                var tsRaw = r.todo_status ? String(r.todo_status).trim() : 'offen';
                var tsLab = r.todo_status_label ? String(r.todo_status_label).trim() : '';
                var todoStCls = 'inline-flex items-center max-w-full truncate px-2 py-1 text-xs font-semibold rounded-full ' + todoStatusSearchBadgeClasses(tsRaw);
                rightBadge = '<span class="shrink-0 ' + todoStCls + '">' + highlightGlobalSearchQuery(tsLab || '—', hq) + '</span>';
            } else {
                rightBadge = '<span class="shrink-0 text-xs text-gray-400 dark:text-primary-250 mt-0.5">' + esc(typeLabels[r.type] || r.type_label || '') + '</span>';
            }
            return '<a href="' + esc(r.url) + '" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors text-left group" data-type="' + esc(r.type || '') + '"' + (r.type === 'aufgabe' && r.title ? ' data-search-term="' + esc(r.title || '') + '"' : '') + '><span class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center bg-gray-100 dark:bg-primary-200 text-gray-500 dark:text-primary-240 group-hover:bg-gray-200 dark:group-hover:bg-primary-160 mt-0.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' + (tc.icon || '') + '"/></svg></span><div class="min-w-0 flex-1"><div class="font-medium text-gray-900 dark:text-primary-200 break-words">' + highlightGlobalSearchQuery(r.title || '', hq) + '</div>' + sub + '</div>' + rightBadge + '</a>';
        }).join('');
    }
    function applyFilter(ft) { selectedFilterType = ft; activeResultIndex = -1; var f = ft ? lastSearchResults.filter(function(r) { return r.type === ft; }) : lastSearchResults; renderItems(f); if (pillsInner) { pillsInner.querySelectorAll('button[data-filter-type]').forEach(function(btn) { var a = (btn.getAttribute('data-filter-type') || '') === (ft || ''); btn.classList.toggle('bg-primary-600', a); btn.classList.toggle('text-white', a); btn.classList.toggle('dark:bg-primary-500', a); btn.classList.toggle('bg-gray-100', !a); btn.classList.toggle('text-gray-700', !a); btn.classList.toggle('dark:bg-primary-140', !a); btn.classList.toggle('dark:text-primary-200', !a); }); } if (moreEl && moreLink) { if (lastSearchResults.length > 0) { moreEl.classList.remove('hidden'); var cnt = lastSearchResults.length; moreLink.textContent = cnt === 1 ? '1 Ergebnis' : cnt + ' Ergebnisse'; } else moreEl.classList.add('hidden'); } }
    function showResults(items) { lastSearchResults = items; selectedFilterType = null; loadingEl.classList.add('hidden'); emptyEl.classList.add('hidden'); if (resultsContent) resultsContent.classList.remove('hidden'); if (pillsContainer) pillsContainer.classList.remove('hidden'); if (pillsInner) { var c = {}; items.forEach(function(r) { c[r.type] = (c[r.type] || 0) + 1; }); var rankMap = {}; typePillOrder.forEach(function(t, idx) { rankMap[t] = idx; }); var types = Object.keys(c).sort(function(a, b) { var ra = Object.prototype.hasOwnProperty.call(rankMap, a) ? rankMap[a] : 999; var rb = Object.prototype.hasOwnProperty.call(rankMap, b) ? rankMap[b] : 999; if (ra !== rb) return ra - rb; return String(a).localeCompare(String(b), 'de-DE'); }); pillsInner.innerHTML = '<button type="button" class="global-search-pill shrink-0 px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white dark:bg-primary-500 cursor-pointer hover:opacity-90 transition-opacity" data-filter-type="">Alle ' + items.length + '</button>' + types.map(function(t) { var x = typeConfig[t] || { label: typeLabels[t] || t, pill: 'bg-gray-100 text-gray-700 dark:bg-primary-140 dark:text-primary-200' }; return '<button type="button" class="global-search-pill shrink-0 px-3 py-1.5 text-xs font-medium rounded-full ' + x.pill + ' cursor-pointer hover:opacity-90 transition-opacity" data-filter-type="' + esc(t) + '">' + esc(x.label) + ' ' + c[t] + '</button>'; }).join(''); } applyFilter(null); resultsContainer.classList.remove('hidden'); }
    function hideResults() { activeResultIndex = -1; resultsContainer.classList.add('hidden'); }
    function buildGlobalSearchUrl(q) {
        var url = baseUrl + 'search/api/search.php?q=' + encodeURIComponent(q) + '&limit=15';
        var scopeNone = globalSearchScope && globalSearchScope.length === 1 && globalSearchScope[0] === '_none';
        if (globalSearchScope && globalSearchScope.length > 0 && !scopeNone) {
            url += '&search_scope=' + encodeURIComponent(globalSearchScope.join(','));
        }
        var kbCid = searchWrapper && searchWrapper.getAttribute('data-kb-company-id');
        if (kbCid && String(kbCid).trim() !== '') {
            url += '&kb_company_id=' + encodeURIComponent(String(kbCid).trim());
        }
        return url;
    }
    function fetchGlobalSearchResults(q) {
        return fetch(buildGlobalSearchUrl(q))
            .then(function(r) {
                return r.text().then(function(t) {
                    return { ok: r.ok, status: r.status, text: t };
                });
            })
            .then(function(resp) {
                var d = null;
                try {
                    d = JSON.parse(resp.text);
                } catch (parseErr) {
                    throw new Error('Ungueltige JSON-Antwort (HTTP ' + resp.status + '): ' + String(resp.text || '').slice(0, 180));
                }
                if (!resp.ok) {
                    throw new Error('HTTP ' + resp.status + ': ' + ((d && d.error) ? d.error : 'Fehlerhafte Antwort'));
                }
                if (!d || !d.success || !Array.isArray(d.results)) {
                    throw new Error((d && d.error) ? d.error : 'Ungueltige API-Antwort');
                }
                return {
                    results: d.results,
                    suggestions: Array.isArray(d.suggestions) ? d.suggestions : []
                };
            });
    }
    function mergeUniqueGlobalResults(groups) {
        var out = [];
        var seen = {};
        groups.forEach(function(list) {
            (list || []).forEach(function(r) {
                var key = String(r.type || '') + '|' + String(r.id || '') + '|' + String(r.url || '');
                if (seen[key]) return;
                seen[key] = true;
                out.push(r);
            });
        });
        return out;
    }
    function buildGlobalSearchQueryVariants(rawQuery) {
        var variants = [];
        var seen = {};
        function pushVariant(v) {
            var q = String(v || '').trim();
            if (q.length < 2) return;
            if (seen[q]) return;
            seen[q] = true;
            variants.push(q);
        }
        pushVariant(rawQuery);
        var terms = String(rawQuery || '').split(/\s+/).filter(function(t) { return t.trim() !== ''; });
        if (terms.length === 2) {
            pushVariant(terms.slice().reverse().join(' '));
        }
        if (terms.length > 1) {
            var cleaned = terms.map(function(t) {
                return String(t).replace(/^[\W_]+|[\W_]+$/g, '');
            }).filter(function(t) { return t !== ''; });
            if (cleaned.length > 0) {
                pushVariant(cleaned.join(' '));
                var uniqueCleaned = [];
                var seenClean = {};
                cleaned.forEach(function(t) {
                    var k = t.toLocaleLowerCase('de-DE');
                    if (seenClean[k]) return;
                    seenClean[k] = true;
                    uniqueCleaned.push(t);
                });
                if (uniqueCleaned.length > 0) {
                    pushVariant(uniqueCleaned.join(' '));
                }
            }
        }
        return variants;
    }
    function doSearch() {
        var q = searchInput.value.trim();
        if (q.length < 2) { hideResults(); return; }
        showLoading();
        var queries = buildGlobalSearchQueryVariants(q);

        Promise.all(queries.map(fetchGlobalSearchResults))
            .then(function(groups) {
                var merged = mergeUniqueGlobalResults(groups.map(function(g) { return g && Array.isArray(g.results) ? g.results : []; }));
                if (merged.length > 0) {
                    showResults(merged);
                    return;
                }
                var apiSuggestions = [];
                var apiSeen = {};
                groups.forEach(function(g) {
                    var s = g && Array.isArray(g.suggestions) ? g.suggestions : [];
                    s.forEach(function(v) {
                        var candidate = String(v || '').trim();
                        if (candidate.length < 2) return;
                        var k = candidate.toLocaleLowerCase('de-DE');
                        if (apiSeen[k]) return;
                        apiSeen[k] = true;
                        apiSuggestions.push(candidate);
                    });
                });
                showEmpty('Keine Ergebnisse gefunden.', apiSuggestions.length ? apiSuggestions.slice(0, 5) : buildNoResultSuggestions(q));
            })
            .catch(function(err) {
                console.error('Globale Suche fehlgeschlagen:', err);
                showEmpty('Suchfehler in der globalen Suche. Bitte Seite neu laden.');
            });
    }

    if (searchWrapper) {
        globalSearchInstances.push({ wrapper: searchWrapper, hideResults: hideResults, blurInput: function() { searchInput.blur(); } });
    }
    var searchShortcutLabel = '';
    if (!isMobile) {
        var isMac = typeof navigator !== 'undefined' && /Mac|iPod|iPhone|iPad/.test(navigator.platform || '') || (navigator.userAgent || '').indexOf('Mac') !== -1;
        searchShortcutLabel = isMac ? '⌘K' : 'Strg+K';
        searchInput.title = 'Suchen (' + searchShortcutLabel + ')';
    } else {
        searchInput.title = 'Suchen';
    }
    var searchSubmitBtn = document.getElementById(isMobile ? 'globalSearchSubmitBtnMobile' : 'globalSearchSubmitBtn');
    function updateGlobalSearchActionBtn() {
        if (!searchSubmitBtn) return;
        var hasValue = searchInput.value.length > 0;
        searchSubmitBtn.classList.toggle('nav-global-search__submit--has-value', hasValue);
        if (hasValue) {
            searchSubmitBtn.title = 'Eingabe löschen';
            searchSubmitBtn.setAttribute('aria-label', 'Eingabe löschen');
        } else {
            searchSubmitBtn.title = searchShortcutLabel ? 'Suchen (' + searchShortcutLabel + ')' : 'Suchen';
            searchSubmitBtn.setAttribute('aria-label', 'Suchen');
        }
    }
    if (searchSubmitBtn) {
        updateGlobalSearchActionBtn();
        searchSubmitBtn.addEventListener('click', function() {
            if (searchInput.value.length > 0) {
                searchInput.value = '';
                hideResults();
                updateGlobalSearchActionBtn();
                searchInput.focus();
                return;
            }
            var q = searchInput.value.trim();
            if (q.length >= 2) {
                doSearch();
            } else {
                searchInput.focus();
            }
        });
    }
    if (pillsContainer) pillsContainer.addEventListener('click', function(e) { var b = e.target.closest('button[data-filter-type]'); if (b) { e.preventDefault(); e.stopPropagation(); var t = b.getAttribute('data-filter-type'); applyFilter(t === '' || !t ? null : t); searchInput.focus(); } });
    searchInput.addEventListener('input', function() {
        updateGlobalSearchActionBtn();
        clearTimeout(debounceTimer);
        if (this.value.trim().length < 2) { hideResults(); return; }
        debounceTimer = setTimeout(doSearch, 280);
    });
    searchInput.addEventListener('focus', function() { if (this.value.trim().length >= 2 && resultsList.innerHTML) resultsContainer.classList.remove('hidden'); });
    if (emptyEl) {
        emptyEl.addEventListener('click', function(e) {
            var btn = e.target.closest('.global-search-suggestion-btn[data-query]');
            if (!btn) return;
            var nextQuery = btn.getAttribute('data-query') || '';
            if (String(nextQuery).trim().length < 2) return;
            searchInput.value = nextQuery;
            updateGlobalSearchActionBtn();
            doSearch();
            searchInput.focus();
        });
    }
    if (resultsList) {
        resultsList.addEventListener('mouseover', function(e) {
            var targetLink = e.target.closest('a[href]');
            if (!targetLink || !resultsList.contains(targetLink)) return;
            var links = getVisibleResultLinks();
            var idx = links.indexOf(targetLink);
            if (idx !== -1) {
                activeResultIndex = idx;
                updateActiveResultVisualState();
            }
        });
    }
    searchInput.addEventListener('keydown', function(e) {
        if (resultsContainer.classList.contains('hidden')) return;
        if (e.key === 'ArrowDown') {
            if (navigateResults(1)) {
                e.preventDefault();
                e.stopPropagation();
            }
            return;
        }
        if (e.key === 'ArrowUp') {
            if (navigateResults(-1)) {
                e.preventDefault();
                e.stopPropagation();
            }
            return;
        }
        if (e.key === 'ArrowLeft') {
            if (navigatePills(-1)) {
                e.preventDefault();
                e.stopPropagation();
            }
            return;
        }
        if (e.key === 'ArrowRight') {
            if (navigatePills(1)) {
                e.preventDefault();
                e.stopPropagation();
            }
            return;
        }
        if (e.key === 'Tab') {
            if (navigatePills(e.shiftKey ? -1 : 1)) {
                e.preventDefault();
                e.stopPropagation();
            }
            return;
        }
        if (e.key === 'Enter') {
            var links = getVisibleResultLinks();
            if (activeResultIndex >= 0 && links[activeResultIndex]) {
                e.preventDefault();
                e.stopPropagation();
                links[activeResultIndex].click();
            }
        }
    });
}

function bindGlobalSearchSharedHandlers() {
    if (globalSearchShortcutBound) return;
    globalSearchShortcutBound = true;
    function globalSearchDeskInputVisible(el) {
        if (!el || typeof el.getBoundingClientRect !== 'function') return false;
        var r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
    }
    document.addEventListener('keydown', function(e) {
        var keyIsK = e.code === 'KeyK' || (e.key && e.key.length === 1 && e.key.toLowerCase() === 'k');
        if (!(e.metaKey || e.ctrlKey) || !keyIsK || e.altKey) return;
        if (e.repeat) return;
        var desk = document.getElementById('globalSearchInput');
        var mob = document.getElementById('globalSearchInputMobile');
        if (globalSearchDeskInputVisible(desk)) {
            e.preventDefault();
            e.stopPropagation();
            desk.focus();
            desk.select();
            var rc = document.getElementById('globalSearchResults');
            if (desk.value.trim().length >= 2 && rc) rc.classList.remove('hidden');
        } else if (mob) {
            e.preventDefault();
            e.stopPropagation();
            var sb = document.getElementById('appMobileFooterSearchBtn');
            if (sb) sb.click();
            setTimeout(function() { mob.focus(); mob.select(); }, 350);
        }
    }, true);
    document.addEventListener('click', function(e) {
        globalSearchInstances.forEach(function(inst) {
            if (inst.wrapper && !inst.wrapper.contains(e.target)) inst.hideResults();
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            globalSearchInstances.forEach(function(inst) { inst.hideResults(); if (inst.blurInput) inst.blurInput(); });
        }
    });
}

function initGlobalSearchAll() {
    setupGlobalSearch(false);
    setupGlobalSearch(true);
    bindGlobalSearchSharedHandlers();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initGlobalSearchAll); else initGlobalSearchAll();
})();

// Nav-Unsaved-Changes: Banner nur bei Änderung anzeigen (modular für Kunde/Firma/Gerät)
(function(){
var searchWrapper = document.getElementById('globalSearchWrapper');
var unsavedBar = document.getElementById('navUnsavedChangesBar');
var saveBtn = document.getElementById('navUnsavedChangesSave');
var discardBtn = document.getElementById('navUnsavedChangesDiscard');
if (!unsavedBar) return;
var flipContainer = document.getElementById('navSearchSaveFlipContainer');
function setBarVisible(visible) {
    if (flipContainer) flipContainer.classList.toggle('nav-showing-save-bar', !!visible);
    if (visible) {
        unsavedBar.classList.remove('nav-unsaved-changes-bar-hidden');
    } else {
        unsavedBar.classList.add('nav-unsaved-changes-bar-hidden');
    }
}
document.addEventListener('navUnsavedChangesDirty', function(e) {
    setBarVisible(!!(e.detail && e.detail.dirty));
});
if (saveBtn) saveBtn.addEventListener('click', function() {
    if (typeof window.navUnsavedChangesSave === 'function') window.navUnsavedChangesSave();
});
if (discardBtn) discardBtn.addEventListener('click', function() {
    var url = window.navUnsavedChangesDiscardUrl;
    if (url && url !== '#') window.location.href = url;
});
})();
</script>

    <div class="nav-mobile-bar-right flex items-center justify-end min-w-0 shrink-0 lg:order-2 <?php echo (!empty($navTicketViewDetailMobile) || !empty($navMobileInventoryDetailMobile)) ? 'max-lg:flex-none lg:flex-1' : 'flex-1'; ?>">
<?php if (!empty($navMobileUseCompactTop) && !empty($navMobileCompactShowLogoutButton)): ?>
      <a href="<?php echo BASE_URL; ?>settings/" class="lg:hidden inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-primary-600 hover:bg-primary-50 dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus:ring-2 focus:ring-primary-250/40" title="Einstellungen" aria-label="Einstellungen">
        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M20 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6h-2m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4"/>
        </svg>
      </a>
      <a href="<?php echo BASE_URL; ?>logout.php" class="lg:hidden inline-flex h-11 w-11 max-lg:-me-[0.5rem] shrink-0 items-center justify-center rounded-lg text-red-600 hover:bg-transparent active:bg-transparent dark:text-red-400 dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus:ring-2 focus:ring-red-300 dark:focus:ring-red-800" title="Abmelden" aria-label="Abmelden">
        <svg class="h-8 w-8 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2"/>
        </svg>
      </a>
<?php elseif (!empty($navMobileUseCompactTop) && empty($navMobileHideCompactCreateButton)): ?>
      <?php if (!empty($navMobileTodosQuickComposer)): ?>
      <!-- Erledigt-Checkbox bei Aufgaben-Drawer: in #navMobileTodosDrawerBar (eine Zeile mit Titel) -->
      <!-- Reihenfolge wie Tickets: Suche → Neue Aufgabe → Schließen (justify-end: rechts außen zuerst im DOM) -->
      <?php if (!empty($navMobileTodosSearchToggle)): ?>
      <button type="button" id="navMobileTodosSearchBtn" class="lg:hidden inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-primary-600 hover:bg-transparent active:bg-transparent dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-250/40 touch-manipulation [-webkit-tap-highlight-color:transparent]" title="Suche" aria-label="Suche ein- oder ausblenden" aria-expanded="false" aria-controls="todo-mobile-dashboard">
        <svg class="h-6 w-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
        </svg>
      </button>
      <?php endif; ?>
      <!-- Label → natives Fokus-Ziel (iOS öffnet Tastatur); Plus-Button allein reicht mit programmatischem focus() oft nicht -->
      <label id="navMobileTodosQuickOpenLabel" for="quickTodoInput" class="lg:hidden inline-flex h-11 w-11 max-lg:-me-[0.5rem] shrink-0 cursor-pointer select-none items-center justify-center rounded-lg text-primary-600 hover:bg-transparent active:bg-transparent dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus-within:outline-none focus-visible:ring-2 focus-visible:ring-primary-250/40 touch-manipulation [-webkit-tap-highlight-color:transparent]" title="Neue Aufgabe" aria-label="Neue Aufgabe" aria-expanded="false" aria-controls="quickTodoBar">
        <svg class="h-8 w-8 pointer-events-none shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
      </label>
      <button type="button" id="navMobileTodosQuickCloseBtn" class="lg:hidden hidden inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-primary-600 hover:bg-transparent active:bg-transparent dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-250/40 touch-manipulation [-webkit-tap-highlight-color:transparent]" title="Eingabe schließen" aria-label="Eingabe schließen" aria-expanded="false" aria-controls="quickTodoBar">
        <svg class="h-7 w-7 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
        </svg>
      </button>
      <?php elseif (!empty($navMobileCreateUrl)): ?>
      <?php if (!empty($navMobileInventorySearchToggle)): ?>
      <button type="button" id="navMobileInvSearchBtn" class="lg:hidden inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-primary-600 hover:bg-transparent active:bg-transparent dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-250/40 touch-manipulation [-webkit-tap-highlight-color:transparent]" title="Suche" aria-label="Suche ein- oder ausblenden" aria-expanded="false" aria-controls="inv-mobile-dashboard">
        <svg class="h-6 w-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
        </svg>
      </button>
      <?php endif; ?>
      <?php if (!empty($navMobileTicketsSearchToggle)): ?>
      <button type="button" id="navMobileTicketsSearchBtn" class="lg:hidden inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-primary-600 hover:bg-transparent active:bg-transparent dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-250/40 touch-manipulation [-webkit-tap-highlight-color:transparent]" title="Suche" aria-label="Suche ein- oder ausblenden" aria-expanded="false" aria-controls="tickets-mobile-dashboard">
        <svg class="h-6 w-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
        </svg>
      </button>
      <?php endif; ?>
      <a href="<?php echo htmlspecialchars($navMobileCreateUrl); ?>" class="lg:hidden inline-flex h-11 w-11 max-lg:-me-[0.5rem] shrink-0 items-center justify-center rounded-lg text-primary-600 hover:bg-primary-50 dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus:ring-2 focus:ring-primary-250/40" title="Neu anlegen" aria-label="Neu anlegen">
        <svg class="h-8 w-8" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
      </a>
      <?php endif; ?>
<?php elseif (!empty($navMobileUseCompactTop) && !empty($navMobileInventoryDetailEditUrl)): ?>
      <a href="<?php echo htmlspecialchars((string) $navMobileInventoryDetailEditUrl); ?>" class="lg:hidden inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-primary-600 hover:bg-primary-50 dark:text-white dark:hover:bg-transparent dark:active:bg-transparent focus:outline-none focus:ring-2 focus:ring-primary-250/40 touch-manipulation [-webkit-tap-highlight-color:transparent]" title="Bearbeiten" aria-label="Artikel bearbeiten">
        <svg class="h-7 w-7" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
      </a>
<?php endif; ?>
      <div class="nav-top-actions <?php echo !empty($navMobileUseCompactTop) ? 'max-lg:hidden lg:flex' : 'flex'; ?>">

<?php
// Ungelesene Benachrichtigungen laden
$unreadNotificationCount = 0;
if ($user_id) {
    try {
        $unreadNotificationCount = getUnreadNotificationCount($user_id);
    } catch (Exception $e) {
        error_log("Fehler beim Laden der Benachrichtigungen: " . $e->getMessage());
    }
}
$navNotificationBadgeLabel = formatNavNotificationBadgeLabel($unreadNotificationCount);
$navTopActionsHideMobile = (!empty($navTicketViewDetailMobile) || !empty($dashboardHideMobileTopTimeTracking)) ? ' max-lg:hidden' : '';
$navTopIconBtnClass = 'nav-top-actions__icon relative inline-flex shrink-0 items-center justify-center h-9 w-9 text-gray-900 hover:opacity-70 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300 dark:text-white dark:focus-visible:ring-gray-600 transition-opacity';
$navTopAvatarClass = 'h-8 w-8 rounded-full object-cover';
?>

        <?php if (!empty($navShowTicketCreateButton)): ?>
        <a href="<?php echo BASE_URL; ?>tickets/create.php" class="nav-top-actions__create inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300 dark:text-white dark:focus-visible:ring-primary-320<?php echo $navTopActionsHideMobile; ?>" title="Ticket erstellen">
          <svg class="h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
          </svg>
          <span class="whitespace-nowrap">Ticket erstellen</span>
        </a>
        <?php endif; ?><div class="nav-top-actions__cluster"><?php if (isset($row) && ($row['rolle'] === 'Admin' || $row['rolle'] === 'Techniker')): ?>
        <button type="button" id="nav-time-track-button" class="<?php echo $navTopIconBtnClass; ?> nav-time-track-btn<?php echo $navTopActionsHideMobile; ?>" title="Zeiterfassung starten/stoppen">
          <span class="sr-only">Zeiterfassung</span>
          <svg id="nav-time-track-icon" class="nav-time-track-icon h-6 w-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span id="nav-time-track-elapsed" class="nav-time-track-elapsed hidden" aria-hidden="true">00:00</span>
        </button>
        <?php endif; ?><button type="button" id="nav-mobile-notification-trigger" class="<?php echo $navTopIconBtnClass; ?> md:hidden">
          <span class="sr-only">Benachrichtigungen</span>
          <svg class="h-6 w-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
          </svg>
          <?php if ($unreadNotificationCount > 0): ?>
          <span class="notification-badge nav-top-actions__badge" id="notification-badge-mobile"><?php echo htmlspecialchars($navNotificationBadgeLabel); ?></span>
          <?php endif; ?>
         </button><div id="nav-notification-dropdown-wrap" class="relative hidden md:block shrink-0">
         <?php if ($unreadNotificationCount > 0): ?>
        <button type="button" data-dropdown-toggle="notification-dropdown" data-dropdown-placement="bottom-end" data-dropdown-offset-distance="10" class="<?php echo $navTopIconBtnClass; ?>" id="notification-button">
          <span class="sr-only">Benachrichtigungen</span>
          <svg class="h-6 w-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
          </svg>
          <span class="notification-badge nav-top-actions__badge" id="notification-badge"><?php echo htmlspecialchars($navNotificationBadgeLabel); ?></span>
        </button>
        <?php else: ?>
        <a href="<?php echo BASE_URL; ?>notifications/" class="<?php echo $navTopIconBtnClass; ?>" id="notification-link">
          <span class="sr-only">Benachrichtigungen</span>
          <svg class="h-6 w-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
          </svg>
        </a>
        <?php endif; ?>
              <div class="nav-notification-panel hidden list-none" id="notification-dropdown" aria-label="Benachrichtigungen">
                  <div class="nav-notification-panel__header">
                      <p class="nav-notification-panel__title">Benachrichtigungen</p>
                      <div class="nav-notification-panel__count-wrap" id="notification-dropdown-count-wrap"<?php echo $unreadNotificationCount > 0 ? '' : ' hidden'; ?>>
                          <span class="nav-notification-panel__count" id="notification-dropdown-count" aria-live="polite"><?php echo $unreadNotificationCount > 0 ? htmlspecialchars($navNotificationBadgeLabel) : ''; ?></span>
                          <button type="button" class="nav-notification-panel__mark-all-read" id="notification-mark-all-read-btn" aria-label="Alle als gelesen markieren" title="Alle als gelesen markieren">
                              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                              </svg>
                          </button>
                      </div>
                  </div>
                  <div class="nav-notification-panel__list-wrap">
                      <div id="notificationsList" class="nav-notification-panel__list">
                          <div class="nav-notification-panel__state flex items-center justify-center gap-2">
                              <svg class="animate-spin h-4 w-4 shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                              </svg>
                              Wird geladen…
                          </div>
                      </div>
                      <div class="nav-notification-panel__list-fade-bottom" aria-hidden="true"></div>
                  </div>
              </div>
        </div></div><?php
      $navMobileCompanyName = htmlspecialchars($row['company_name'] ?? 'Keine Firma');
      $navMobileCompanyLogo = getLogoUrl($row['company_logo'] ?? '');
      $navMobileUserName = trim(htmlspecialchars(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')));
      if ($navMobileUserName === '') {
          $navMobileUserName = htmlspecialchars($row['email'] ?? 'Unbekannt');
      }
      $navUri = $_SERVER['REQUEST_URI'] ?? '';
      $navIsAccount = strpos($navUri, '/account') !== false;
      $navIsSettings = strpos($navUri, '/settings') !== false;
      $navIsNotifications = strpos($navUri, '/notifications') !== false;
      $navIsAdmin = strpos($navUri, '/admin') !== false && strpos($navUri, '/admin/announcements') === false;
      $navIsAnnouncements = strpos($navUri, '/admin/announcements') !== false;
      $navAccountLinkClass = function ($active) {
          return 'nav-account-panel__link' . ($active ? ' nav-account-panel__link--active' : '');
      };
      ?><div id="nav-account-dropdown-wrap" class="relative hidden md:block shrink-0">
      <button type="button" class="inline-flex shrink-0 overflow-hidden rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300 dark:focus-visible:ring-gray-600<?php echo !empty($navMobileUseCompactTop) ? ' max-lg:hidden' : ''; ?>" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="accountDropdown" data-dropdown-placement="bottom-end" data-dropdown-offset-distance="10">
        <span class="sr-only">Benutzermenü</span>
        <?php echo renderUserAvatar(
          $row['logopfad'] ?? '',
          $row['vorname'] ?? '',
          $row['nachname'] ?? '',
          $row['email'] ?? '',
          $navTopAvatarClass,
          'Profilbild'
        ); ?>
      </button>
      <div id="accountDropdown" class="nav-account-panel hidden list-none" aria-label="Benutzermenü" data-popper-placement="bottom-end">
        <div class="nav-account-panel__header">
          <div class="nav-account-panel__user">
            <?php echo renderUserAvatar(
              $row['logopfad'] ?? '',
              $row['vorname'] ?? '',
              $row['nachname'] ?? '',
              $row['email'] ?? '',
              '',
              'Profilbild'
            ); ?>
            <div class="min-w-0 flex-1">
              <p class="nav-account-panel__name"><?php echo $navMobileUserName; ?></p>
              <p class="nav-account-panel__company"><?php echo $navMobileCompanyName; ?></p>
            </div>
          </div>
        </div>
        <div class="nav-account-panel__body">
          <div class="nav-account-panel__section">
            <ul class="nav-account-panel__list">
              <li>
                <a href="<?php echo BASE_URL; ?>account/" class="<?php echo $navAccountLinkClass($navIsAccount); ?>">
                  <span class="nav-account-panel__icon" aria-hidden="true">
                   
                    
                    <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.948 8.948 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
</svg>

                    </span>
                  <span class="nav-account-panel__label">Account</span>
                </a>
              </li>
              <li>
                <a href="<?php echo BASE_URL; ?>settings/" class="<?php echo $navAccountLinkClass($navIsSettings); ?>">
                  <span class="nav-account-panel__icon" aria-hidden="true">
                    
             <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
  <path stroke="currentColor" stroke-linecap="square" stroke-linejoin="round" stroke-width="2" d="M10 19H5a1 1 0 0 1-1-1v-1a3 3 0 0 1 3-3h2m10 1a3 3 0 0 1-3 3m3-3a3 3 0 0 0-3-3m3 3h1m-4 3a3 3 0 0 1-3-3m3 3v1m-3-4a3 3 0 0 1 3-3m-3 3h-1m4-3v-1m-2.121 1.879-.707-.707m5.656 5.656-.707-.707m-4.242 0-.707.707m5.656-5.656-.707.707M12 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
</svg>

                </span>
                  <span class="nav-account-panel__label">Einstellungen</span>
                </a>
              </li>
              <li>
                <a href="<?php echo BASE_URL; ?>notifications/" class="<?php echo $navAccountLinkClass($navIsNotifications); ?>">
                  <span class="nav-account-panel__icon" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
</svg>

                    
                    </span>
                  <span class="nav-account-panel__label">Benachrichtigungen</span>

                </a>
              </li>
            </ul>
          </div>
          <?php if (isset($row) && ($row['rolle'] === 'Admin' || $row['rolle'] === 'Techniker')): ?>
          <div class="nav-account-panel__section">
            
            <ul class="nav-account-panel__list">
              <?php if ($row['rolle'] === 'Admin'): ?>
              <li>
                <a href="<?php echo BASE_URL; ?>admin/" class="<?php echo $navAccountLinkClass($navIsAdmin); ?>">
                  <span class="nav-account-panel__icon" aria-hidden="true">
              
                    
                        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z"/>
  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
</svg>

                    
                    </span>
                  <span class="nav-account-panel__label">Admin</span>
                </a>
              </li>
              <?php endif; ?>
              <li>
                <a href="<?php echo BASE_URL; ?>admin/announcements.php" class="<?php echo $navAccountLinkClass($navIsAnnouncements); ?>">
                  <span class="nav-account-panel__icon" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 9H5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h6m0-6v6m0-6 5.419-3.87A1 1 0 0 1 18 5.942v12.114a1 1 0 0 1-1.581.814L11 15m7 0a3 3 0 0 0 0-6M6 15h3v5H6v-5Z"/>
</svg>

                    </span>
                  
                        <span class="nav-account-panel__label">Ankündigungen</span>
                </a>
              </li>
            </ul>
          </div>
          <?php endif; ?>
          <?php
          $emailEnabled = false;
          if ($user_id) {
              try {
                  $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'email_enabled'");
                  $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                  $stmt->execute();
                  $result = $stmt->fetch(PDO::FETCH_ASSOC);
                  if ($result && $result['setting_value'] !== null && $result['setting_value'] !== '') {
                      $v = is_string($result['setting_value']) ? strtolower(trim($result['setting_value'])) : $result['setting_value'];
                      if (in_array($v, ['1', 'true', 'yes', 'on'], true) || $v === true) {
                          $emailEnabled = true;
                      }
                  }
              } catch (PDOException $e) {
                  error_log("Nav: Fehler beim Laden der E-Mail-Einstellung: " . $e->getMessage());
              }
          }
          ?>
          <div class="nav-account-panel__section">
            <ul class="nav-account-panel__list">
              <li>
                <div class="nav-account-panel__row">
                  <span class="nav-account-panel__icon" aria-hidden="true"><svg fill="currentColor" viewBox="0 0 24 24"><path d="m3.62 6.389 8.396 6.724 8.638-6.572-7.69-4.29a1.975 1.975 0 0 0-1.928 0L3.62 6.39Z"/><path d="m22 8.053-8.784 6.683a1.978 1.978 0 0 1-2.44-.031L2.02 7.693a1.091 1.091 0 0 0-.019.199v11.065C2 20.637 3.343 22 5 22h14c1.657 0 3-1.362 3-3.043V8.053Z"/></svg></span>
                  <span class="nav-account-panel__label">E-Mail</span>
                  <label class="ml-auto inline-flex shrink-0 cursor-pointer items-center">
                    <input type="checkbox" value="" class="peer sr-only" name="email-enabled-toggle" id="email-enabled-toggle" <?php echo $emailEnabled ? 'checked' : ''; ?>>
                    <div class="peer relative h-5 w-9 rounded-full bg-gray-200 after:absolute after:start-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:border-gray-500 dark:bg-gray-600 dark:peer-focus:ring-primary-800 rtl:peer-checked:after:-translate-x-full"></div>
                    <span class="sr-only">E-Mail-Benachrichtigungen</span>
                  </label>
                </div>
              </li>
            </ul>
          </div>
          <div class="nav-account-panel__section">
            <ul class="nav-account-panel__list">
              <li>
                <a href="<?php echo BASE_URL; ?>logout.php" class="nav-account-panel__link nav-account-panel__link--danger">
                  <span class="nav-account-panel__icon" aria-hidden="true"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2"/></svg></span>
                  <span class="nav-account-panel__label">Abmelden</span>
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
      </div><button type="button" id="nav-mobile-profile-trigger" class="md:hidden inline-flex shrink-0 overflow-hidden rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300 dark:focus-visible:ring-gray-600<?php echo !empty($navMobileUseCompactTop) ? ' max-lg:hidden' : ''; ?>" aria-expanded="false" aria-controls="navMobileProfilePopup">
        <span class="sr-only">Benutzermenü</span>
        <?php echo renderUserAvatar(
          $row['logopfad'] ?? '',
          $row['vorname'] ?? '',
          $row['nachname'] ?? '',
          $row['email'] ?? '',
          $navTopAvatarClass,
          'Profilbild'
        ); ?>
      </button>

      </div>

      <div id="notification-mobile-sheet-legacy" class="hidden" aria-hidden="true">
                <div id="notification-mobile-overlay-legacy" class="absolute inset-0 bg-black/70 opacity-0 transition-opacity duration-200"></div>
                <div id="notification-mobile-panel-legacy" class="fixed top-0 left-0 right-0 bottom-0 translate-y-full bg-gray-50 dark:bg-primary-50 transition-transform duration-250 ease-out overflow-hidden flex flex-col rounded-none border-0 shadow-none">
                  <div id="notification-mobile-header-legacy" class="shrink-0 h-14 px-4 flex items-center justify-between bg-gray-900 text-white">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="text-base font-semibold truncate">Benachrichtigungen</span>
                    </div>
                    <button type="button" id="notification-mobile-close-legacy" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-white/90 hover:bg-white/10" aria-label="Schließen">
                      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                  <div id="notificationsListMobileLegacy" class="min-h-0 flex-1 overflow-y-auto">
                    <div class="flex items-center justify-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                      <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Lade...
                    </div>
                  </div>
                  <a href="<?php echo BASE_URL; ?>notifications/" class="block border-t border-gray-200 dark:border-primary-120 py-3 text-base font-medium text-center text-gray-900 bg-white hover:bg-gray-100 dark:bg-primary-100 dark:text-primary-120 dark:hover:bg-primary-160">
                    Alle anzeigen
                  </a>
                </div>
              </div>

    </div>

  </div>

</nav>

<!-- Mobile Notifications Overlay (1:1 wie Bottom-Nav Menü-Sheet) -->
<div id="notification-mobile-sheet" class="lg:hidden hidden fixed inset-0 z-[62] flex flex-col bg-black translate-y-full transition-transform duration-[420ms] ease-[cubic-bezier(0.22,1,0.36,1)]" role="dialog" aria-modal="true" aria-labelledby="notificationMobileSheetTitle" aria-hidden="true">
  <div id="notification-mobile-header" class="app-mobile-sheet-header h-14 flex items-center justify-between gap-3 px-4 shrink-0 bg-black text-gray-100 border-0 shadow-none">
      <h2 id="notificationMobileSheetTitle" class="min-w-0 truncate text-lg font-semibold leading-tight tracking-tight text-white">Benachrichtigungen</h2>
      <button type="button" id="notification-mobile-close" class="app-mobile-sheet-header-close p-2 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/30" aria-label="Schließen">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
  </div>
  <div class="flex-1 min-h-0 flex flex-col overflow-hidden rounded-t-[1.75rem] bg-gray-50 dark:bg-primary-100">
    <div id="notificationsListMobile" class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden px-3 pt-4 pb-[calc(2.5rem+env(safe-area-inset-bottom,0px))] space-y-1.5 custom-scrollbar">
      <div class="flex items-center justify-center py-4 text-gray-500 dark:text-gray-400 text-sm">
        <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Lade...
      </div>
    </div>
  </div>
</div>

<?php
// Ankündigungs-Banner einbinden (nach Navigation)
if (file_exists(dirname(__FILE__) . '/announcement-banner.php')) {
    include dirname(__FILE__) . '/announcement-banner.php';
}
// Zufriedenheitsumfrage-Popup einbinden
if (file_exists(dirname(__FILE__) . '/satisfaction-survey-popup.php')) {
    include dirname(__FILE__) . '/satisfaction-survey-popup.php';
}
?>

<div id="navMobileProfilePopup" class="fixed inset-0 z-[95] hidden md:hidden" aria-hidden="true">
  <button type="button" id="navMobileProfilePopupOverlay" class="absolute inset-0 bg-black/60 opacity-0 transition-opacity duration-220 ease-out" aria-label="Schließen"></button>
  <div id="navMobileProfilePopupPanel" class="nav-account-panel--mobile absolute left-3 right-3 top-[calc(env(safe-area-inset-top,0px)+0.75rem)] opacity-0 translate-y-2 scale-[0.985] transition-all duration-220 ease-out">
    <div class="nav-account-panel__header">
      <a href="<?php echo BASE_URL; ?>account/" class="nav-account-panel__user no-underline">
        <?php echo renderUserAvatar(
          $row['logopfad'] ?? '',
          $row['vorname'] ?? '',
          $row['nachname'] ?? '',
          $row['email'] ?? '',
          '',
          'Profilbild'
        ); ?>
        <div class="min-w-0 flex-1">
          <p class="nav-account-panel__name"><?php echo $navMobileUserName; ?></p>
          <p class="nav-account-panel__company"><?php echo $navMobileCompanyName; ?></p>
        </div>
      </a>
    </div>
    <div class="nav-account-panel__body">
      <div class="nav-account-panel__section">
        <ul class="nav-account-panel__list">
          <li>
            <a href="<?php echo BASE_URL; ?>settings/" class="nav-account-panel__link">
              <span class="nav-account-panel__icon" aria-hidden="true"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg></span>
              <span class="nav-account-panel__label">Einstellungen</span>
            </a>
          </li>
          <li>
            <a href="<?php echo BASE_URL; ?>logout.php" class="nav-account-panel__link nav-account-panel__link--danger">
              <span class="nav-account-panel__icon" aria-hidden="true"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2"/></svg></span>
              <span class="nav-account-panel__label">Abmelden</span>
            </a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Mobile Sidebar Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden" onclick="closeMobileSidebar()"></div>

<script>
// baseUrl sicherstellen (falls nicht bereits definiert)
if (typeof baseUrl === 'undefined') {
    var baseUrl = '<?php echo BASE_URL; ?>';
}
// Sidebar-Zustand aus Benutzereinstellungen (Einstellung „Sidebar ein-/ausklappen“)
window.sidebarExpandedFromSettings = <?php echo !empty($sidebarExpandedFromSettings) ? 'true' : 'false'; ?>;
// Sidebar bei Hover erweitern
window.sidebarExpandOnHoverFromSettings = <?php echo !empty($sidebarExpandOnHoverFromSettings) ? 'true' : 'false'; ?>;

function syncMobileSidebarToggleA11y(isOpen) {
    var btn = document.getElementById('app-mobile-sidebar-toggle');
    if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

// Mobile Sidebar Toggle
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        
        if (isOpen) {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
            syncMobileSidebarToggleA11y(false);
        } else {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            syncMobileSidebarToggleA11y(true);
        }
    }
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
        syncMobileSidebarToggleA11y(false);
    }
}

// Sidebar: ausgeklappten Zustand anwenden (aus Einstellungen bzw. localStorage). temporary=true: nur Anzeige, kein Speichern (z. B. Hover).
function setSidebarExpanded(expanded, temporary) {
    var sidebar = document.getElementById('sidebar');
    var mainContent = document.getElementById('main-content');
    if (!sidebar || window.innerWidth < 1024) return;
    document.body.setAttribute('data-sidebar-expanded', expanded ? 'true' : 'false');
    if (expanded) {
        sidebar.classList.remove('w-16');
        sidebar.classList.add('sidebar-w-expanded');
        if (mainContent) {
            mainContent.classList.remove('lg:ml-16');
        }
    } else {
        sidebar.classList.remove('sidebar-w-expanded');
        sidebar.classList.add('w-16');
        if (mainContent) {
            mainContent.classList.add('lg:ml-16');
        }
    }
    if (!temporary) {
        localStorage.setItem('sidebarExpanded', expanded ? 'true' : 'false');
    }
}

function finalizeSidebarLayoutPending() {
    document.body.classList.remove('sidebar-layout-pending');
}

function isSidebarExpanded() {
    var sidebar = document.getElementById('sidebar');
    return sidebar && sidebar.classList.contains('sidebar-w-expanded');
}

document.addEventListener('DOMContentLoaded', function() {
    // Mobile: Nav beim Runterscrollen ausblenden, beim Hochscrollen einblenden
    (function() {
        var nav = document.getElementById('main-nav');
        if (!nav) return;
        var lastScrollY = window.scrollY || 0;
        var ticking = false;
        var scrollThreshold = 10;

        function updateNavVisibility() {
            if (window.innerWidth >= 1024) {
                nav.classList.remove('nav-hidden-mobile');
                lastScrollY = window.scrollY;
                ticking = false;
                return;
            }
            /* Dashboard & Co.: Top-Nav soll nicht beim Scrollen ausgeblendet werden */
            if (document.body.getAttribute('data-scroll-hide-mobile-topnav') === '0') {
                nav.classList.remove('nav-hidden-mobile');
                lastScrollY = window.scrollY || 0;
                ticking = false;
                return;
            }
            var currentScrollY = window.scrollY || 0;
            if (currentScrollY <= scrollThreshold) {
                nav.classList.remove('nav-hidden-mobile');
            } else if (currentScrollY > lastScrollY) {
                nav.classList.add('nav-hidden-mobile');
            } else {
                nav.classList.remove('nav-hidden-mobile');
            }
            lastScrollY = currentScrollY;
            ticking = false;
        }

        function onScroll() {
            if (!ticking) {
                requestAnimationFrame(updateNavVisibility);
                ticking = true;
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) nav.classList.remove('nav-hidden-mobile');
        });
    })();

    // Schließe Sidebar bei Klick außerhalb (nur mobile) — Hamburger/Toggle nicht schließen (sonst öffnet und schließt in einem Klick)
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 1024) {
            if (e.target.closest('#app-mobile-sidebar-toggle')) return;
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay && !sidebar.contains(e.target)) {
                if (!sidebar.classList.contains('-translate-x-full')) {
                    closeMobileSidebar();
                }
            }
        }
    });
    
    // Schließe Sidebar bei Window Resize (wenn zu Desktop wechselt)
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
            var expanded = localStorage.getItem('sidebarExpanded') === 'true';
            setSidebarExpanded(expanded);
        }
    });
    
    // Bei Navigation (mobile): Sidebar schließen; gespeicherten Zustand nicht zurücksetzen
    const sidebarLinks = document.querySelectorAll('#sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 1024) {
                closeMobileSidebar();
            }
            sessionStorage.setItem('sidebarHoverState', 'false');
        });
    });

    /* Sidebar: Zustand kommt vom Server (data-sidebar-expanded + PHP-Klassen) — kein erneutes Umschalten beim Load (verhindert Flackern) */
    var sidebar = document.getElementById('sidebar');
    if (sidebar && window.innerWidth >= 1024) {
        sessionStorage.setItem('sidebarHoverState', 'false');
        var expandedFromDom = document.body.getAttribute('data-sidebar-expanded') === 'true';
        localStorage.setItem('sidebarExpanded', expandedFromDom ? 'true' : 'false');
    }
    finalizeSidebarLayoutPending();

    // Sidebar bei Hover erweitern (nur Desktop, nur wenn eingeklappt und Einstellung aktiv)
    // Per Body-Klasse, da das Inline-CSS (data-sidebar-expanded) mit !important die Breite sonst überschreibt
    var sidebarHoverExpanded = false;
    var expandOnHover = (typeof window.sidebarExpandOnHoverFromSettings !== 'undefined') ? window.sidebarExpandOnHoverFromSettings : false;
    if (sidebar) {
        sidebar.addEventListener('mouseenter', function() {
            if (window.innerWidth < 1024 || !expandOnHover) return;
            if (!isSidebarExpanded()) {
                document.body.classList.add('sidebar-hover-expanded');
                sidebarHoverExpanded = true;
            }
        });
        sidebar.addEventListener('mouseleave', function() {
            if (window.innerWidth < 1024) return;
            if (sidebarHoverExpanded) {
                document.body.classList.remove('sidebar-hover-expanded');
                sidebarHoverExpanded = false;
            }
        });
    }
});

// Backdrop für mobile Sheets (wie Bottom-Nav Menü/Suche)
if (!document.getElementById('appMobileSheetBackdrop')) {
    var notificationBackdropEl = document.createElement('div');
    notificationBackdropEl.id = 'appMobileSheetBackdrop';
    notificationBackdropEl.className = 'lg:hidden hidden fixed inset-0 z-[61] bg-black/40 backdrop-blur-[1px] transition-opacity duration-[380ms] ease-out opacity-0';
    notificationBackdropEl.setAttribute('aria-hidden', 'true');
    document.body.appendChild(notificationBackdropEl);
}

// Letzter bekannter Ungelesen-Stand (für Sound bei neuer Benachrichtigung)
var lastUnreadNotificationCount = null;

function svStripHtmlForNotify(html) {
    if (!html) return '';
    var d = document.createElement('div');
    d.innerHTML = html;
    return (d.textContent || d.innerText || '').replace(/\s+/g, ' ').trim();
}

function svDesktopNotifyIconUrl() {
    if (typeof baseUrl === 'undefined') return '';
    return String(baseUrl).replace(/\/?$/, '/') + 'assets/images/Serohub_Icon.png';
}

/** Desktop-Hinweis (Browser/OS), wenn in den Einstellungen aktiviert und Berechtigung erteilt */
function svShowDesktopNotificationsIfEnabled(notifications, delta) {
    if (typeof Notification === 'undefined' || delta < 1) return;
    var enabled = false;
    try {
        enabled = localStorage.getItem('svDesktopNotifications') === '1';
    } catch (e) {
        return;
    }
    if (!enabled || Notification.permission !== 'granted') return;
    if (!notifications || notifications.length === 0) return;
    var n = notifications[0];
    var title = (n.titel && String(n.titel).trim()) ? String(n.titel).trim() : 'Neue Benachrichtigung';
    var body = svStripHtmlForNotify(n.nachricht || '');
    if (body.length > 220) body = body.substring(0, 217) + '\u2026';
    if (delta > 1) {
        body = body ? (body + '\n+' + (delta - 1) + ' weitere ungelesene') : ('+' + (delta - 1) + ' neue Benachrichtigungen');
    }
    var icon = svDesktopNotifyIconUrl();
    var opts = { body: body, tag: 'sv-app-notify' };
    if (icon) opts.icon = icon;
    try {
        var note = new Notification(title, opts);
        note.onclick = function() {
            try {
                window.focus();
            } catch (e) {}
            var lk = n.link ? (baseUrl + String(n.link).replace(/^\//, '')) : baseUrl + 'notifications/';
            window.location.href = lk;
            note.close();
        };
    } catch (e) {}
}

// Benachrichtigungen für Nav-Dropdown laden (eigener Name, damit auf /notifications/ keine Kollision)
function loadNavNotifications() {
    const notificationsList = document.getElementById('notificationsList');
    if (!notificationsList) return;
    
    fetch(baseUrl + 'notifications/api/notifications.php?limit=100&offset=0&read_state=unread&sort=relevanz&sort_dir=asc')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var newCount = data.unread_count || 0;
                var prevCount = lastUnreadNotificationCount;
                if (prevCount !== null && newCount > prevCount) {
                    if (typeof playNewNotificationSound === 'function') {
                        playNewNotificationSound();
                    }
                    svShowDesktopNotificationsIfEnabled(data.notifications, newCount - prevCount);
                }
                lastUnreadNotificationCount = newCount;
                updateNotificationsList(data.notifications);
                updateNotificationBadge(data.unread_count || 0);
                updateNotificationIcon(data.unread_count || 0);
            } else {
                if (notificationsList) notificationsList.innerHTML = getDesktopNotificationsEmptyHtml();
                updateNotificationBadge(0);
                updateNotificationIcon(0);
                requestAnimationFrame(updateNotificationPanelScrollFades);
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Benachrichtigungen:', error);
            if (notificationsList) notificationsList.innerHTML = '<div class="nav-notification-panel__state nav-notification-panel__state--error">Benachrichtigungen konnten nicht geladen werden.</div>';
            requestAnimationFrame(updateNotificationPanelScrollFades);
        });
}

function loadNavNotificationsMobileAll() {
    const notificationsListMobile = document.getElementById('notificationsListMobile');
    if (!notificationsListMobile) return;
    fetch(baseUrl + 'notifications/api/notifications.php?limit=100&offset=0&only_unread=true')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (Array.isArray(data.notifications) && data.notifications.length === 0) {
                    notificationsListMobile.innerHTML = getMobileNotificationsEmptyHtml();
                } else {
                    notificationsListMobile.innerHTML = buildNotificationsListHtml(data.notifications || []);
                }
            } else {
                notificationsListMobile.innerHTML = getMobileNotificationsEmptyHtml();
            }
        })
        .catch(() => {
            notificationsListMobile.innerHTML = '<div class="text-center py-4 text-red-500 text-sm">Fehler beim Laden</div>';
        });
}

function getMobileNotificationsEmptyHtml() {
    return `
        <div class="h-full min-h-[20rem] flex flex-col items-center justify-center py-6 text-center">
            <svg class="w-auto max-w-[20rem] h-48 text-gray-800 dark:text-white" aria-hidden="true" width="433" height="559" viewBox="0 0 433 559" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M278 411H376.905H394C394 399.367 373.853 396.918 365.305 392.633C356.758 388.347 351.874 381 340.884 381C329.895 381 317.074 395.694 302.421 396.918C290.699 397.898 281.256 406.714 278 411Z" fill="#d6e2fb" fill-opacity="0.6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M168.025 452.826L184.978 389.558L186.909 390.076L169.957 453.344L168.025 452.826Z" fill="#c8d8fa"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M168.025 452.826L184.978 389.558L186.909 390.076L169.957 453.344L168.025 452.826Z" fill="url(#paint0_linear_275_1035)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M214.384 396.738L215.459 398.425L178.777 421.802L198.856 460.388L197.082 461.311L176.157 421.1L214.384 396.738Z" fill="#c8d8fa"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M214.384 396.738L215.459 398.425L178.777 421.802L198.856 460.388L197.082 461.311L176.157 421.1L214.384 396.738Z" fill="url(#paint1_linear_275_1035)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M157.853 381.591L156.079 382.514L176.158 421.1L139.476 444.477L140.551 446.164L178.778 421.802L157.853 381.591Z" fill="#c8d8fa"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M157.853 381.591L156.079 382.514L176.158 421.1L139.476 444.477L140.551 446.164L178.778 421.802L157.853 381.591Z" fill="url(#paint2_linear_275_1035)"/>
<rect x="175.032" y="411.222" width="10" height="19" rx="2" transform="rotate(15 175.032 411.222)" fill="#2563eb"/>
<rect x="175.032" y="411.222" width="10" height="19" rx="2" transform="rotate(15 175.032 411.222)" fill="url(#paint3_linear_275_1035)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M185.134 362.165L149.567 352.635L149.38 352.585L149.276 352.422C137.81 334.549 121.304 303.062 108.32 266.461C95.3374 229.866 86.0931 188.176 89.6644 150.043C94.1343 102.315 121.143 69.4602 157.325 51.3142C193.49 33.1765 238.816 29.7315 279.99 40.764C321.164 51.7965 358.694 77.4428 380.945 111.233C403.207 145.039 410.17 186.996 390.177 230.565C374.203 265.375 345.353 296.857 315.812 322.058C286.267 347.263 256.353 366.313 237.488 376.058L237.316 376.147L237.129 376.097L195.764 365.013L185.134 362.165Z" fill="#c8d8fa"/>
<path d="M149.336 352.573C179.995 353.968 209.986 362.004 237.235 376.125L234.864 378.144C227.673 384.265 222.514 392.428 220.07 401.549L151.489 383.173C153.933 374.052 153.546 364.403 150.38 355.506L149.336 352.573Z" fill="#c8d8fa"/>
<rect width="44" height="69" transform="matrix(-0.965926 -0.258819 -0.258819 0.965926 232.294 467.976)" fill="#2563eb"/>
<rect x="112.52" y="435.883" width="80" height="69" transform="rotate(15 112.52 435.883)" fill="#2563eb"/>
<rect x="111.826" y="477.108" width="29" height="19" rx="2" transform="rotate(15 111.826 477.108)" fill="#d6e2fb"/>
<rect x="112.395" y="486.578" width="16" height="2" rx="1" transform="rotate(15 112.395 486.578)" fill="#2563eb"/>
<rect x="111.359" y="490.441" width="12" height="2" rx="1" transform="rotate(15 111.359 490.441)" fill="#2563eb"/>
<path d="M116.5 39.5H35.5H21.5C21.5 30 38 28 45 24.5C52 21 56 15 65 15C74 15 84.5 27 96.5 28C106.1 28.8 113.833 36 116.5 39.5Z" fill="#d6e2fb"/>
<defs>
<linearGradient id="paint0_linear_275_1035" x1="185.943" y1="389.817" x2="168.991" y2="453.085" gradientUnits="userSpaceOnUse"><stop stop-color="#9ab7f6"/><stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/></linearGradient>
<linearGradient id="paint1_linear_275_1035" x1="200.309" y1="392.967" x2="183.006" y2="457.54" gradientUnits="userSpaceOnUse"><stop stop-color="#9ab7f6"/><stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/></linearGradient>
<linearGradient id="paint2_linear_275_1035" x1="171.929" y1="385.362" x2="154.626" y2="449.935" gradientUnits="userSpaceOnUse"><stop stop-color="#9ab7f6"/><stop offset="1" stop-color="#9ab7f6" stop-opacity="0"/></linearGradient>
<linearGradient id="paint3_linear_275_1035" x1="180.032" y1="403.222" x2="180.032" y2="430.222" gradientUnits="userSpaceOnUse"><stop stop-color="#111928"/><stop offset="1" stop-color="#111928" stop-opacity="0"/></linearGradient>
</defs>
</svg>
            <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-primary-120">Der Heißluftballon fliegt gerade leer</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-primary-220">Aktuell gibt es keine ungelesenen Benachrichtigungen.</p>
        </div>
    `;
}

function formatNotificationDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Gerade eben';
    if (diffMins < 60) return `vor ${diffMins} Min.`;
    if (diffHours < 24) return `vor ${diffHours} Std.`;
    if (diffDays < 7) return `vor ${diffDays} Tag(en)`;
    
    return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

const typeIcons = {
    'ticket_erstellt': '📝',
    'ticket_nachricht': '💬',
    'ticket_status': '🔄',
    'todo_erstellt': '✅',
    'todo_zugewiesen': '👤',
    'todo_kommentar': '💭',
    'device_offline': '🔴',
    'device_online': '🟢',
    'system': '🔔'
};

function getNotificationAvatarHtml(creatorAvatar, creatorName, compact) {
    const sizeClass = compact ? '' : 'w-11 h-11';
    const borderClass = compact ? '' : ' border-2 border-gray-300 dark:border-gray-600';
    if (creatorAvatar && typeof creatorAvatar === 'string' && creatorAvatar.startsWith('preset:')) {
        const parts = creatorAvatar.split(':');
        let color = (parts[1] || '#3b82f6');
        if (!color.startsWith('#')) color = '#' + color;
        const initials = (parts[2] || 'U').toUpperCase();
        return `
            <div class="${sizeClass} rounded-full flex items-center justify-center text-white text-sm font-bold${borderClass}"
                 style="background-color: ${escapeHtml(color)};"
                 title="${escapeHtml(creatorName)}">
                ${escapeHtml(initials)}
            </div>
        `;
    }
    const fallbackUrl = `${baseUrl}assets/images/default-avatar.png`;
    const url = creatorAvatar
        ? (creatorAvatar.startsWith('http://') || creatorAvatar.startsWith('https://')
            ? creatorAvatar
            : (baseUrl + creatorAvatar.replace(/^\//, '')))
        : fallbackUrl;
    return `
        <img src="${escapeHtml(url)}" alt="" class="${sizeClass} rounded-full object-cover${borderClass}" onerror="this.onerror=null; this.src='${fallbackUrl}';">
    `;
}

function getDesktopNotificationsEmptyHtml() {
    return '<div class="nav-notification-panel__state">Keine ungelesenen Benachrichtigungen</div>';
}

function updateNotificationDropdownHeader(count) {
    const countEl = document.getElementById('notification-dropdown-count');
    const countWrap = document.getElementById('notification-dropdown-count-wrap');
    const n = parseInt(count, 10) || 0;
    if (!countEl) return;
    if (n > 0) {
        countEl.textContent = n > 99 ? '99+' : String(n);
        if (countWrap) {
            countWrap.hidden = false;
            countWrap.removeAttribute('aria-hidden');
        }
    } else {
        countEl.textContent = '';
        if (countWrap) {
            countWrap.hidden = true;
            countWrap.setAttribute('aria-hidden', 'true');
        }
    }
}

async function markAllNavNotificationsAsRead() {
    const btn = document.getElementById('notification-mark-all-read-btn');
    if (btn && btn.disabled) return;
    if (btn) btn.disabled = true;
    try {
        const response = await fetch(baseUrl + 'notifications/api/notifications.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json().catch(function() { return {}; });
        if (response.ok && data.success) {
            loadNavNotifications();
        } else {
            console.error('Fehler beim Markieren aller als gelesen:', data.error || response.status);
        }
    } catch (error) {
        console.error('Fehler beim Markieren aller als gelesen:', error);
    } finally {
        if (btn) btn.disabled = false;
    }
}

function buildNotificationsListHtml(notifications, opts) {
    opts = opts || {};
    const desktop = !!opts.desktop;
    if (!notifications || notifications.length === 0) {
        return desktop
            ? getDesktopNotificationsEmptyHtml()
            : '<div class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">Keine Benachrichtigungen</div>';
    }
    let html = '';
    notifications.forEach(notification => {
        const link = notification.link ? (baseUrl + notification.link.replace(/^\//, '')) : baseUrl + 'notifications/';
        const creatorAvatar = notification.creator_avatar || '';
        const creatorName = notification.creator_name || 'Unbekannt';
        const titel = notification.titel || '';
        const time = formatNotificationDate(notification.erstellt_datum);
        const safeLink = link.replace(/'/g, "\\'");
        const safeLinkAttr = escapeHtml(link);

        if (desktop) {
            const isRead = !!notification.ist_gelesen;
            const readClass = isRead ? ' nav-notification-item--read' : '';
            const dismissHtml = isRead ? '' : `
                    <button type="button" class="nav-notification-item__dismiss" data-notification-id="${notification.id}" aria-label="Als gelesen markieren" title="Als gelesen markieren">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>`;
            html += `
            <div class="nav-notification-item${readClass}" data-notification-id="${notification.id}" data-link="${safeLinkAttr}" role="button" tabindex="0">
                <div class="nav-notification-item__avatar">
                    <div class="nav-notification-item__avatar-media">
                        ${getNotificationAvatarHtml(creatorAvatar, creatorName, true)}
                    </div>${dismissHtml}
                </div>
                <div class="nav-notification-item__body">
                    <p class="nav-notification-item__line1">${escapeHtml(titel || 'Benachrichtigung')}</p>
                    <div class="nav-notification-item__meta">
                        <span class="nav-notification-item__time">${escapeHtml(time)}</span>
                        <span class="nav-notification-item__sep" aria-hidden="true">•</span>
                        <span class="nav-notification-item__author">${escapeHtml(creatorName)}</span>
                    </div>
                </div>
            </div>`;
            return;
        }

        html += `
            <a href="${link}" onclick="event.preventDefault(); markNotificationAsReadAndNavigate(${notification.id}, '${safeLink}');" data-notification-id="${notification.id}" class="nav-mobile-notification-item flex items-start gap-3 py-3 px-3 rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 hover:bg-gray-50 dark:hover:bg-primary-140 touch-pan-y will-change-transform transition-colors">
                <div class="flex-shrink-0 pt-0.5">
                    ${getNotificationAvatarHtml(creatorAvatar, creatorName, false)}
                </div>
                <div class="min-w-0 w-full">
                    <div class="text-gray-500 font-normal text-sm mb-1 dark:text-gray-400">
                        <span class="font-semibold text-gray-900 dark:text-white">${escapeHtml(creatorName)}</span><br>
                        <span class="text-gray-700 dark:text-gray-300">${escapeHtml(titel)}</span>
                    </div>
                    <div class="text-xs font-medium text-gray-500 dark:text-primary-220">${escapeHtml(time)}</div>
                </div>
            </a>
        `;
    });
    return html;
}

function updateNotificationPanelScrollFades() {
    const list = document.getElementById('notificationsList');
    const panel = document.getElementById('notification-dropdown');
    const wrap = panel ? panel.querySelector('.nav-notification-panel__list-wrap') : null;
    const fadeBottom = wrap ? wrap.querySelector('.nav-notification-panel__list-fade-bottom') : null;
    const eps = 2;
    if (!list || !fadeBottom) return;
    const moreBelow = list.scrollHeight - list.scrollTop - list.clientHeight > eps;
    fadeBottom.classList.toggle('nav-notification-panel__list-fade--visible', moreBelow);
}

function bindNotificationPanelMarkAllRead() {
    const btn = document.getElementById('notification-mark-all-read-btn');
    if (!btn || btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        markAllNavNotificationsAsRead();
    });
}

function bindNotificationPanelItemActions() {
    const list = document.getElementById('notificationsList');
    if (!list || list.dataset.itemActionsBound === '1') return;
    list.dataset.itemActionsBound = '1';
    bindNotificationPanelMarkAllRead();
    list.addEventListener('click', function(e) {
        const dismissBtn = e.target.closest('.nav-notification-item__dismiss');
        if (dismissBtn) {
            e.preventDefault();
            e.stopPropagation();
            const dismissId = dismissBtn.getAttribute('data-notification-id');
            if (dismissId) markNotificationAsRead(Number(dismissId));
            return;
        }
        const item = e.target.closest('.nav-notification-item');
        if (!item) return;
        const id = item.getAttribute('data-notification-id');
        const itemLink = item.getAttribute('data-link');
        if (id && itemLink) {
            e.preventDefault();
            markNotificationAsReadAndNavigate(Number(id), itemLink);
        }
    });
    list.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        if (e.target.closest('.nav-notification-item__dismiss')) return;
        const item = e.target.closest('.nav-notification-item');
        if (!item) return;
        e.preventDefault();
        const id = item.getAttribute('data-notification-id');
        const itemLink = item.getAttribute('data-link');
        if (id && itemLink) markNotificationAsReadAndNavigate(Number(id), itemLink);
    });
}

function bindNotificationPanelScrollFades() {
    const list = document.getElementById('notificationsList');
    const panel = document.getElementById('notification-dropdown');
    if (!list || list.dataset.scrollFadeBound === '1') return;
    list.dataset.scrollFadeBound = '1';
    bindNotificationPanelItemActions();
    list.addEventListener('scroll', updateNotificationPanelScrollFades, { passive: true });
    window.addEventListener('resize', updateNotificationPanelScrollFades, { passive: true });
    if (panel && panel.dataset.scrollFadeObs !== '1') {
        panel.dataset.scrollFadeObs = '1';
        const obs = new MutationObserver(function() {
            if (!panel.classList.contains('hidden')) {
                requestAnimationFrame(updateNotificationPanelScrollFades);
            }
        });
        obs.observe(panel, { attributes: true, attributeFilter: ['class'] });
    }
}

function updateNotificationsList(notifications) {
    const notificationsList = document.getElementById('notificationsList');
    if (!notificationsList) return;
    notificationsList.innerHTML = buildNotificationsListHtml(notifications, { desktop: true });
    requestAnimationFrame(function() {
        updateNotificationPanelScrollFades();
        setTimeout(updateNotificationPanelScrollFades, 50);
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function markNotificationAsReadAndNavigate(notificationId, link) {
    try {
        // Benachrichtigung als gelesen markieren
        const response = await fetch(baseUrl + 'notifications/api/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: notificationId
            })
        });
        
        // Auch bei Fehler zur Seite navigieren
        window.location.href = link;
    } catch (error) {
        console.error('Fehler beim Markieren der Benachrichtigung als gelesen:', error);
        // Trotzdem zur Seite navigieren
        window.location.href = link;
    }
}

async function markNotificationAsRead(notificationId) {
    try {
        const response = await fetch(baseUrl + 'notifications/api/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: notificationId
            })
        });
        
        if (response.ok) {
            // Benachrichtigungen neu laden
            loadNavNotifications();
        }
    } catch (error) {
        console.error('Fehler beim Markieren der Benachrichtigung als gelesen:', error);
    }
}

async function deleteNotificationFromNav(notificationId, itemEl) {
    const itemId = String(notificationId || '');
    if (!itemId) return;
    if (!window._navMobileNotificationDeleteInFlight) {
        window._navMobileNotificationDeleteInFlight = new Set();
    }
    const inFlight = window._navMobileNotificationDeleteInFlight;
    if (inFlight.has(itemId)) return;
    inFlight.add(itemId);

    if (itemEl) {
        itemEl.dataset.deleting = '1';
        itemEl.style.pointerEvents = 'none';
    }

    try {
        const response = await fetch(baseUrl + 'notifications/api/notifications.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: Number(notificationId) })
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Delete fehlgeschlagen');
        removeMobileNotificationItemAnimated(itemEl);
        loadNavNotifications();
    } catch (error) {
        console.error('Fehler beim Löschen der Benachrichtigung:', error);
        if (itemEl) {
            itemEl.dataset.deleting = '0';
            itemEl.style.pointerEvents = '';
            itemEl.style.transition = 'transform 0.16s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.14s ease-out';
            itemEl.style.transform = 'translateX(0)';
            itemEl.style.opacity = '1';
        }
    } finally {
        inFlight.delete(itemId);
    }
}

function removeMobileNotificationItemAnimated(itemEl) {
    if (!itemEl || !itemEl.parentNode) return;
    const list = itemEl.parentNode;
    const items = Array.from(list.querySelectorAll('.nav-mobile-notification-item'));
    const beforeTop = new Map();
    items.forEach(function(el) {
        beforeTop.set(el, el.getBoundingClientRect().top);
    });
    // Kurzer Sync mit der Swipe-Ausblendung, dann per FLIP nur die Nachbar-Items animieren.
    window.setTimeout(function() {
        if (!itemEl.parentNode) return;
        itemEl.parentNode.removeChild(itemEl);

        const remaining = Array.from(list.querySelectorAll('.nav-mobile-notification-item'));
        remaining.forEach(function(el) {
            const oldTop = beforeTop.get(el);
            if (oldTop == null) return;
            const newTop = el.getBoundingClientRect().top;
            const deltaY = oldTop - newTop;
            if (!deltaY) return;

            if (typeof el.animate === 'function') {
                el.animate(
                    [
                        { transform: 'translateY(' + deltaY + 'px)' },
                        { transform: 'translateY(0)' }
                    ],
                    {
                        duration: 150,
                        easing: 'cubic-bezier(0.22, 1, 0.36, 1)'
                    }
                );
            } else {
                el.style.transition = 'none';
                el.style.transform = 'translateY(' + deltaY + 'px)';
                requestAnimationFrame(function() {
                    el.style.transition = 'transform 0.15s cubic-bezier(0.22, 1, 0.36, 1)';
                    el.style.transform = 'translateY(0)';
                    window.setTimeout(function() {
                        el.style.transform = '';
                        el.style.transition = '';
                    }, 170);
                });
            }
        });

        if (remaining.length === 0) {
            list.innerHTML = getMobileNotificationsEmptyHtml();
        }
    }, 90);
}

function bindMobileNotificationSwipeDelete() {
    const list = document.getElementById('notificationsListMobile');
    if (!list || list.getAttribute('data-swipe-bound') === '1') return;
    list.setAttribute('data-swipe-bound', '1');
    list.style.touchAction = 'pan-y';

    let activeItem = null;
    let startX = 0;
    let startY = 0;
    let currentX = 0;
    let startTime = 0;
    let swiping = false;
    let lockHorizontal = false;
    let cancelledByVertical = false;
    let suppressClickUntil = 0;

    list.addEventListener('touchstart', function(e) {
        if (!e.touches || e.touches.length !== 1) return;
        const item = e.target.closest('.nav-mobile-notification-item');
        if (!item) return;
        if (item.dataset.deleting === '1') return;
        activeItem = item;
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        currentX = startX;
        startTime = Date.now();
        swiping = false;
        lockHorizontal = false;
        cancelledByVertical = false;
        activeItem.style.transition = 'none';
    }, { passive: true });

    list.addEventListener('touchmove', function(e) {
        if (!activeItem || !e.touches || e.touches.length !== 1) return;
        currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const dx = currentX - startX;
        const dy = currentY - startY;

        if (!lockHorizontal) {
            if (Math.abs(dx) < 8) return;
            // Deutlich strenger: horizontales Löschen nur bei klarer Seitwärts-Geste
            if (Math.abs(dx) < Math.abs(dy) * 1.15) {
                // Nicht hart abbrechen: sonst muss man häufig ein zweites Mal wischen
                cancelledByVertical = true;
                return;
            }
            lockHorizontal = true;
            cancelledByVertical = false;
        }

        if (cancelledByVertical && Math.abs(dx) < Math.abs(dy) * 1.05) return;

        swiping = true;
        e.preventDefault();
        activeItem.style.transform = `translateX(${dx}px)`;
        activeItem.style.opacity = String(Math.max(0.4, 1 - Math.min(Math.abs(dx) / 220, 0.6)));
    }, { passive: false });

    list.addEventListener('touchend', function() {
        if (!activeItem) return;
        const dx = currentX - startX;
        const elapsed = Date.now() - startTime;
        const threshold = 72;
        const item = activeItem;
        activeItem = null;

        const fastFlick = Math.abs(dx) > 44 && elapsed < 210;
        if (swiping && (Math.abs(dx) > threshold || fastFlick)) {
            suppressClickUntil = Date.now() + 350;
            item.style.transition = 'transform 0.16s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.14s ease-out';
            item.style.transform = `translateX(${dx > 0 ? 420 : -420}px)`;
            item.style.opacity = '0';
            const notificationId = item.getAttribute('data-notification-id');
            item.dataset.deleting = '1';
            item.style.pointerEvents = 'none';
            window.setTimeout(() => {
                if (notificationId) deleteNotificationFromNav(notificationId, item);
            }, 110);
            return;
        }

        item.style.transition = 'transform 0.16s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.14s ease-out';
        item.style.transform = 'translateX(0)';
        item.style.opacity = '1';
    }, { passive: true });

    list.addEventListener('click', function(e) {
        if (Date.now() < suppressClickUntil) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
}

function formatNavNotificationBadgeLabel(n) {
    const count = parseInt(n, 10) || 0;
    if (count <= 0) return '';
    if (count > 99) return '99+';
    if (count > 9) return '9+';
    return String(count);
}

function updateNotificationBadge(count) {
    const n = parseInt(count, 10) || 0;
    const label = formatNavNotificationBadgeLabel(n);
    const badges = document.querySelectorAll('.notification-badge');
    badges.forEach(badge => {
        if (n > 0) {
            badge.textContent = label;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    });
    updateNotificationDropdownHeader(n);
}

function updateNotificationIcon(count) {
    // Icon mit Dropdown (wenn Benachrichtigungen vorhanden)
    const buttonHtml = `
        <span class="sr-only">Benachrichtigungen</span>
        <svg class="h-6 w-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
        </svg>
        ${count > 0 ? `<span class="notification-badge nav-top-actions__badge" id="notification-badge">${formatNavNotificationBadgeLabel(count)}</span>` : ''}
    `;
    
    // Link ohne Dropdown (wenn keine Benachrichtigungen vorhanden)
    const linkHtml = `
        <span class="sr-only">Benachrichtigungen</span>
        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.365V3m0 2.365a5.338 5.338 0 0 1 5.133 5.368v1.8c0 2.386 1.867 2.982 1.867 4.175 0 .593 0 1.193-.538 1.193H5.538c-.538 0-.538-.6-.538-1.193 0-1.193 1.867-1.789 1.867-4.175v-1.8A5.338 5.338 0 0 1 12 5.365Zm-8.134 5.368a8.458 8.458 0 0 1 2.252-5.714m14.016 5.714a8.458 8.458 0 0 0-2.252-5.714M8.54 17.901a3.48 3.48 0 0 0 6.92 0H8.54Z"/>
        </svg>
    `;
    
    // Desktop-Version
    const notificationButton = document.getElementById('notification-button');
    const notificationLink = document.getElementById('notification-link');
    
    if (count > 0) {
        // Button mit Dropdown anzeigen
        if (notificationLink) {
            const button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('data-dropdown-toggle', 'notification-dropdown');
            button.setAttribute('data-dropdown-placement', 'bottom-end');
            button.setAttribute('data-dropdown-offset-distance', '10');
            button.className = 'relative inline-flex rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:ring-2 focus:ring-gray-300 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-600';
            button.id = 'notification-button';
            button.innerHTML = buttonHtml;
            notificationLink.parentNode.replaceChild(button, notificationLink);
        } else if (notificationButton) {
            notificationButton.innerHTML = buttonHtml;
        }
    } else {
        // Link ohne Dropdown anzeigen
        if (notificationButton) {
            const link = document.createElement('a');
            link.href = baseUrl + 'notifications/';
            link.className = 'inline-flex rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:ring-2 focus:ring-gray-300 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-600';
            link.id = 'notification-link';
            link.innerHTML = linkHtml;
            notificationButton.parentNode.replaceChild(link, notificationButton);
            // Dropdown verstecken
            const dropdown = document.getElementById('notification-dropdown');
            if (dropdown) {
                dropdown.classList.add('hidden');
            }
        }
    }
}

(function initMobileNotificationSheet() {
    const trigger = document.getElementById('nav-mobile-notification-trigger');
    const sheet = document.getElementById('notification-mobile-sheet');
    const backdrop = document.getElementById('appMobileSheetBackdrop');
    const closeBtn = document.getElementById('notification-mobile-close');
    if (!trigger || !sheet || !backdrop) return;

    let startY = null;
    let currentY = null;
    let isDragging = false;
    let dragAllowed = true;

    function openSheet() {
        sheet.classList.remove('hidden');
        backdrop.classList.remove('hidden');
        requestAnimationFrame(function() {
            backdrop.classList.remove('opacity-0');
            sheet.classList.remove('translate-y-full');
        });
        sheet.setAttribute('aria-hidden', 'false');
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        loadNavNotificationsMobileAll();
        bindMobileNotificationSwipeDelete();
    }

    function closeSheet() {
        sheet.style.transition = 'transform 0.4s cubic-bezier(0.32, 0.72, 0, 1)';
        sheet.style.transform = 'translateY(100%)';
        backdrop.style.transition = 'opacity 0.36s ease';
        backdrop.style.opacity = '0';
        sheet.setAttribute('aria-hidden', 'true');
        var done = false;
        function finishClose() {
            if (done) return;
            done = true;
            sheet.classList.add('hidden');
            sheet.classList.add('translate-y-full');
            sheet.style.transform = '';
            sheet.style.transition = '';
            backdrop.classList.add('hidden');
            backdrop.classList.add('opacity-0');
            backdrop.style.opacity = '';
            backdrop.style.transition = '';
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
        setTimeout(finishClose, 480);
    }

    trigger.addEventListener('click', function(e) {
        e.preventDefault();
        openSheet();
    });
    if (closeBtn) closeBtn.addEventListener('click', closeSheet);
    backdrop.addEventListener('click', closeSheet);

    function onTouchStart(e) {
        if (!e.touches || e.touches.length !== 1) return;
        const listEl = document.getElementById('notificationsListMobile');
        // In der Liste nur im Empty-State per Vollflächen-Wisch schließen.
        // Wenn Benachrichtigungen vorhanden sind: kein Drag-to-close aus der Liste,
        // damit Scrollen/seitliches Wischen nicht versehentlich schließt.
        if (listEl && e.currentTarget === listEl) {
            const hasNotificationItems = !!listEl.querySelector('.nav-mobile-notification-item');
            if (hasNotificationItems || listEl.scrollTop > 0) {
                dragAllowed = false;
                return;
            }
        }
        dragAllowed = true;
        startY = e.touches[0].clientY;
        currentY = startY;
        isDragging = false;
        sheet.style.transition = 'none';
        backdrop.style.transition = 'none';
    }
    function onTouchMove(e) {
        if (!dragAllowed) return;
        if (startY === null || !e.touches || e.touches.length !== 1) return;
        currentY = e.touches[0].clientY;
        const deltaY = Math.max(0, currentY - startY);
        if (deltaY < 12) return;
        isDragging = true;
        e.preventDefault();
        sheet.style.transform = 'translateY(' + deltaY + 'px)';
        const progress = Math.min(deltaY / (window.innerHeight * 0.45), 1);
        backdrop.style.opacity = String(Math.max(0, 1 - progress * 0.95));
    }
    function onTouchEnd() {
        if (!dragAllowed) {
            dragAllowed = true;
            startY = null;
            currentY = null;
            isDragging = false;
            return;
        }
        if (startY === null || currentY === null) return;
        const deltaY = currentY - startY;
        if (isDragging && deltaY > Math.max(110, window.innerHeight * 0.2)) {
            closeSheet();
        } else if (isDragging) {
            sheet.style.transition = 'transform 0.48s cubic-bezier(0.34, 1.15, 0.52, 1)';
            sheet.style.transform = 'translateY(0)';
            backdrop.style.transition = 'opacity 0.38s ease';
            backdrop.style.opacity = '';
            setTimeout(function() {
                sheet.style.transition = '';
                sheet.style.transform = '';
                backdrop.style.transition = '';
                backdrop.style.opacity = '';
            }, 520);
        }
        startY = null;
        currentY = null;
        isDragging = false;
        dragAllowed = true;
    }

    const header = sheet.querySelector('.app-mobile-sheet-header');
    const list = document.getElementById('notificationsListMobile');
    if (header) {
        header.addEventListener('touchstart', onTouchStart, { passive: true });
        header.addEventListener('touchmove', onTouchMove, { passive: false });
        header.addEventListener('touchend', onTouchEnd, { passive: true });
    }
    if (list) {
        list.addEventListener('touchstart', onTouchStart, { passive: true });
        list.addEventListener('touchmove', onTouchMove, { passive: false });
        list.addEventListener('touchend', onTouchEnd, { passive: true });
    }
})();

(function initMobileProfilePopup() {
    const trigger = document.getElementById('nav-mobile-profile-trigger');
    const popup = document.getElementById('navMobileProfilePopup');
    const overlay = document.getElementById('navMobileProfilePopupOverlay');
    const panel = document.getElementById('navMobileProfilePopupPanel');
    if (!trigger || !popup || !overlay || !panel) return;

    function openPopup() {
        popup.classList.remove('hidden');
        requestAnimationFrame(function() {
            overlay.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'translate-y-2', 'scale-[0.985]');
        });
        popup.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
    }

    function closePopup() {
        overlay.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'translate-y-2', 'scale-[0.985]');
        popup.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        window.setTimeout(function() {
            popup.classList.add('hidden');
        }, 220);
    }

    trigger.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof window.hapticLightTap === 'function') {
            window.hapticLightTap();
        }
        if (popup.classList.contains('hidden')) openPopup();
        else closePopup();
    });
    overlay.addEventListener('click', closePopup);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePopup();
    });
    popup.addEventListener('click', function(e) {
        const panelClicked = e.target.closest('#navMobileProfilePopupPanel');
        if (!panelClicked) closePopup();
    });
})();

// Zeiterfassung Nav: Server nur gelegentlich, Minuten-Anzeige clientseitig (kein häufiges time.php-Polling)
var navTimeTrackStartMs = null;
var navTimeTrackDisplayIntervalId = null;
var navTimeTrackTodayBaseMinutes = 0;
var navTimeTrackSollMinutes = 0;
/** Intervall für time.php?status=1 (ms) — Sync z. B. bei anderem Tab / Gerät */
var NAV_TIME_TRACK_SERVER_SYNC_MS = 60000;
var NAV_TIME_TRACK_MAX_MINUTES = 600;

function parseMysqlDateTimeNav(dt) {
    if (!dt || typeof dt !== 'string') return null;
    return new Date(dt.replace(' ', 'T'));
}
function formatHmNav(totalMinutes) {
    const mins = Math.max(0, Math.floor(totalMinutes || 0));
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return h + ':' + String(m).padStart(2, '0');
}
function getNavActiveSessionTodayMinutes() {
    if (navTimeTrackStartMs == null) return 0;
    var start = new Date(navTimeTrackStartMs);
    var now = new Date();
    var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var effectiveStartMs = Math.max(start.getTime(), todayStart.getTime());
    if (effectiveStartMs > now.getTime()) return 0;
    return Math.floor((now.getTime() - effectiveStartMs) / 60000);
}
function getNavTimeTrackTodayTotalMinutes() {
    return (navTimeTrackTodayBaseMinutes || 0) + getNavActiveSessionTodayMinutes();
}
function applyNavTimeTrackColor() {
    var btn = document.getElementById('nav-time-track-button');
    var elapsed = document.getElementById('nav-time-track-elapsed');
    if (!btn) return;

    var defaultClasses = ['text-gray-900', 'dark:text-white'];
    var colorClasses = defaultClasses;

    // Farbstatus nur während aktiver Zeiterfassung anzeigen
    if (navTimeTrackStartMs != null) {
        var total = getNavTimeTrackTodayTotalMinutes();
        var soll = navTimeTrackSollMinutes || 0;

        if (total > NAV_TIME_TRACK_MAX_MINUTES) {
            colorClasses = ['text-red-600', 'dark:text-red-400'];
        } else if (total > soll) {
            colorClasses = ['text-green-600', 'dark:text-green-400'];
        }
    }

    btn.classList.remove('text-red-600', 'dark:text-red-400', 'text-green-600', 'dark:text-green-400', 'text-gray-900', 'dark:text-white');
    colorClasses.forEach(function(cls) { btn.classList.add(cls); });

    if (elapsed) {
        elapsed.classList.remove('text-red-600', 'dark:text-red-400', 'text-green-600', 'dark:text-green-400', 'text-gray-900', 'dark:text-white');
        colorClasses.forEach(function(cls) { elapsed.classList.add(cls); });
    }
}
function updateNavTimeTrackElapsedOnly() {
    var el = document.getElementById('nav-time-track-elapsed');
    if (!el || navTimeTrackStartMs == null) return;
    el.textContent = formatHmNav(getNavActiveSessionTodayMinutes());
    applyNavTimeTrackColor();
}
function stopNavTimeTrackDisplayTick() {
    if (navTimeTrackDisplayIntervalId) {
        clearInterval(navTimeTrackDisplayIntervalId);
        navTimeTrackDisplayIntervalId = null;
    }
}
function startNavTimeTrackDisplayTick() {
    stopNavTimeTrackDisplayTick();
    updateNavTimeTrackElapsedOnly();
    navTimeTrackDisplayIntervalId = setInterval(updateNavTimeTrackElapsedOnly, 1000);
}

// Benachrichtigungen beim Laden laden
document.addEventListener('DOMContentLoaded', function() {
    bindNotificationPanelScrollFades();
    loadNavNotifications();
    // Töne-Einstellung vom Server übernehmen (für system-sounds.js)
    fetch(baseUrl + 'settings/api/sounds-enabled.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.success) try { localStorage.setItem('sounds_enabled', d.enabled ? '1' : '0'); } catch (e) {} })
        .catch(function() {});
    // Alle 30 Sekunden aktualisieren
    setInterval(loadNavNotifications, 30000);
    
    // E-Mail-Toggle Event Listener
    const emailToggle = document.getElementById('email-enabled-toggle');
    if (emailToggle) {
        emailToggle.addEventListener('change', function(e) {
            const enabled = e.target.checked;
            
            fetch(baseUrl + 'settings/api/email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    enabled: enabled
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof showToast === 'function') {
                        showToast(enabled ? 'E-Mail-Benachrichtigungen aktiviert' : 'E-Mail-Benachrichtigungen deaktiviert', 'success');
                    }
                } else {
                    // Bei Fehler Toggle zurücksetzen
                    e.target.checked = !enabled;
                    if (typeof showToast === 'function') {
                        showToast('Fehler beim Speichern der Einstellung', 'error');
                    } else {
                        alert('Fehler beim Speichern der Einstellung');
                    }
                }
            })
            .catch(error => {
                console.error('Fehler beim Speichern der E-Mail-Einstellung:', error);
                // Bei Fehler Toggle zurücksetzen
                e.target.checked = !enabled;
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Speichern der Einstellung', 'error');
                } else {
                    alert('Fehler beim Speichern der Einstellung');
                }
            });
        });
    }
    
    // Zeiterfassungs-Button in Nav
    const navTimeTrackButton = document.getElementById('nav-time-track-button');
    if (navTimeTrackButton) {
        // Status laden
        loadNavTimeTrackingStatus();
        
        // Event Listener
        navTimeTrackButton.addEventListener('click', function() {
            toggleNavTimeTracking();
        });
        
        // Server-Status seltener abfragen; Laufzeit-Anzeige läuft lokal (startNavTimeTrackDisplayTick)
        setInterval(loadNavTimeTrackingStatus, NAV_TIME_TRACK_SERVER_SYNC_MS);
    }
});

// Zeiterfassungs-Status für Nav-Button laden
function loadNavTimeTrackingStatus() {
    const navTimeTrackButton = document.getElementById('nav-time-track-button');
    const navTimeTrackIcon = document.getElementById('nav-time-track-icon');
    const navTimeTrackElapsed = document.getElementById('nav-time-track-elapsed');
    
    if (!navTimeTrackButton || !navTimeTrackIcon) return;

    fetch(baseUrl + 'time-tracking/api/time.php?status=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                navTimeTrackTodayBaseMinutes = parseInt(data.today_completed_minutes, 10) || 0;
                navTimeTrackSollMinutes = parseInt(data.minutes_per_day, 10) || 0;

                if (data.isRunning) {
                    navTimeTrackButton.classList.add('nav-time-track-btn--active');
                    navTimeTrackButton.setAttribute('title', 'Zeiterfassung stoppen');
                    
                    // Icon ändern zu Stop-Symbol
                    navTimeTrackIcon.innerHTML = '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>';


                    // Laufzeit anzeigen (HH:MM)
                    if (navTimeTrackElapsed) {
                        const start = parseMysqlDateTimeNav(data.entry?.start_time || data.entry?.startTime || data.entry?.start || null);
                        if (start && !isNaN(start.getTime())) {
                            navTimeTrackStartMs = start.getTime();
                            navTimeTrackElapsed.classList.remove('hidden');
                            navTimeTrackElapsed.setAttribute('aria-hidden', 'false');
                            startNavTimeTrackDisplayTick();
                        } else {
                            navTimeTrackStartMs = null;
                            stopNavTimeTrackDisplayTick();
                            navTimeTrackElapsed.textContent = '0:00';
                            navTimeTrackElapsed.classList.remove('hidden');
                            navTimeTrackElapsed.setAttribute('aria-hidden', 'false');
                            applyNavTimeTrackColor();
                        }
                    } else {
                        applyNavTimeTrackColor();
                    }
                } else {
                    navTimeTrackStartMs = null;
                    stopNavTimeTrackDisplayTick();
                    navTimeTrackButton.classList.remove('nav-time-track-btn--active');
                    navTimeTrackButton.setAttribute('title', 'Zeiterfassung starten');
                    
                    // Icon ändern zu Play-Symbol
                    navTimeTrackIcon.innerHTML = '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>';

                    // Laufzeit ausblenden
                    if (navTimeTrackElapsed) {
                        navTimeTrackElapsed.textContent = '00:00';
                        navTimeTrackElapsed.classList.add('hidden');
                        navTimeTrackElapsed.setAttribute('aria-hidden', 'true');
                    }
                    applyNavTimeTrackColor();
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden des Zeiterfassungs-Status:', error);
        });
}

// Zeiterfassung über Nav-Button starten/stoppen
function toggleNavTimeTracking() {
    const navTimeTrackButton = document.getElementById('nav-time-track-button');
    if (!navTimeTrackButton) return;
    
    // Status prüfen
    fetch(baseUrl + 'time-tracking/api/time.php?status=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const isRunning = data.isRunning;
                const action = isRunning ? 'stop' : 'start';
                
                fetch(baseUrl + 'time-tracking/api/time.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: action,
                        description: null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNavTimeTrackingStatus();
                        if (typeof showToast === 'function') {
                            showToast(data.message, 'success');
                        }
                        // Wenn auf Zeiterfassungsseite, diese aktualisieren
                        if (window.location.pathname.includes('/time-tracking/')) {
                            if (typeof loadTimeStatus === 'function') {
                                loadTimeStatus();
                                loadTimeEntries();
                            }
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(data.error || 'Fehler', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Fehler:', error);
                    if (typeof showToast === 'function') {
                        showToast('Fehler beim Speichern', 'error');
                    }
                });
            }
        })
        .catch(error => {
            console.error('Fehler beim Prüfen des Status:', error);
        });
}
</script>

 