<?php
// BASE_URL definieren falls nicht vorhanden
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Funktion zur Prüfung, ob ein Link aktiv ist
function isActiveLink($linkPath) {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    // Entferne Query-Parameter
    $currentPath = parse_url($currentPath, PHP_URL_PATH);
    
    // Normalisiere Pfade: entferne führende/trailing Slashes und BASE_URL
    $currentPath = trim($currentPath, '/');
    $linkPath = trim($linkPath, '/');
    
    // Entferne BASE_URL vom Link-Pfad falls vorhanden
    $baseUrl = trim(BASE_URL, '/');
    if (!empty($baseUrl) && strpos($linkPath, $baseUrl) === 0) {
        $linkPath = substr($linkPath, strlen($baseUrl));
        $linkPath = trim($linkPath, '/');
    }
    
    // Entferne BASE_URL vom aktuellen Pfad falls vorhanden
    if (!empty($baseUrl) && strpos($currentPath, $baseUrl) === 0) {
        $currentPath = substr($currentPath, strlen($baseUrl));
        $currentPath = trim($currentPath, '/');
    }
    
    // Prüfe ob der aktuelle Pfad mit dem Link-Pfad beginnt oder exakt übereinstimmt
    if (empty($linkPath)) {
        // Für Root-Link: nur aktiv wenn aktueller Pfad leer ist
        return empty($currentPath);
    }
    
    return $currentPath === $linkPath || strpos($currentPath, $linkPath . '/') === 0;
}

// Funktion zur Generierung der Klassen für aktive Links (mittlere Größe)
function getSidebarCountBadgeClasses($extraClasses = '') {
    $classes = 'sidebar-count-badge inline-flex shrink-0 items-center justify-center min-w-[1rem] h-[1rem] px-1 ms-2 tabular-nums text-[0.6875rem] leading-none font-normal text-violet-600 bg-violet-50 rounded-full dark:text-violet-300 dark:bg-violet-950/40';
    return $extraClasses !== '' ? $classes . ' ' . $extraClasses : $classes;
}

function getLinkClasses($linkPath) {
    $baseClasses = "group flex w-full h-10 cursor-pointer items-center rounded-lg border-s-2 border-transparent p-2 text-sm font-medium text-gray-900 hover:bg-gray-100 hover:text-gray-900 dark:text-white dark:hover:bg-gray-700/50 dark:hover:text-white";
    $activeClasses = "!border-[#7c3aed] !bg-[#ede9fe] !text-[#5b21b6] !font-semibold dark:!border-primary-300 dark:!bg-primary-800/40 dark:!text-primary-200";
    
    if (isActiveLink($linkPath)) {
        return $baseClasses . " " . $activeClasses;
    }
    return $baseClasses;
}

// Funktion zur Generierung der Icon-Klassen für aktive Links
function getIconClasses($linkPath) {
    $baseClasses = "h-[1.125rem] w-[1.125rem] shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-400 dark:group-hover:text-gray-200";
    $activeClasses = "!text-[#5b21b6] dark:!text-primary-300";
    
    if (isActiveLink($linkPath)) {
        return $baseClasses . " " . $activeClasses;
    }
    return $baseClasses;
}

// Zähler für Sidebar (Tickets, Aufgaben)
require_once dirname(__DIR__) . '/sidebar_counts.php';

// Funktion zur Zählung der offenen Bestellungen (Status: Neu, Bestellt, Unterwegs, Beim Kunden)
function getOpenOrdersCount() {
    try {
        // Prüfe ob Session vorhanden ist
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            return 0;
        }
        
        // Prüfe ob $pdo im globalen Scope verfügbar ist
        global $pdo;
        
        if (!isset($pdo)) {
            $configPath = dirname(__DIR__) . '/config.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            } else {
                return 0;
            }
        }
        
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return 0;
        }
        
        $userId = $_SESSION['user_id'];
        
        // Benutzerdaten und Rolle abrufen
        $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return 0;
        }
        
        $userRole = $user['rolle'];
        $userCompanyId = $user['company_id'];
        
        // SQL-Query basierend auf Rolle aufbauen
        $whereConditions = ["o.status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')"];
        $params = [];
        
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            // Admin und Techniker: bei ausgewählter Firma in der Nav nur diese Firma zählen
            $selectedCompanyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null ? (int)$_SESSION['selected_company_id'] : null;
            if ($selectedCompanyId) {
                $whereConditions[] = "o.company_id = :selected_company_id";
                $params[':selected_company_id'] = $selectedCompanyId;
            }
        } elseif ($userRole === 'Firmen-Admin') {
            // Firmen-Admin sieht nur Bestellungen der eigenen Firma
            if ($userCompanyId) {
                $whereConditions[] = "o.company_id = :user_company_id";
                $params[':user_company_id'] = $userCompanyId;
            } else {
                return 0;
            }
        } else {
            // Andere Benutzer sehen nur eigene Bestellungen
            $whereConditions[] = "o.erstellt_von = :user_id";
            $params[':user_id'] = $userId;
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        $sql = "SELECT COUNT(*) as count FROM orders o " . $whereClause;
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Fehler beim Zählen offener Bestellungen: " . $e->getMessage());
        return 0;
    } catch (Error $e) {
        error_log("Fehler beim Zählen offener Bestellungen: " . $e->getMessage());
        return 0;
    }
}

// Funktion zur Zählung der offenen Projekte (nur Admin/Techniker, Status != Abgeschlossen/Archiviert)
function getOpenProjectsCount() {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            return 0;
        }
        global $pdo;
        if (!isset($pdo)) {
            $configPath = dirname(__DIR__) . '/config.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            } else {
                return 0;
            }
        }
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return 0;
        }
        $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !in_array($user['rolle'], ['Admin', 'Techniker'])) {
            return 0;
        }
        $selectedCompanyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null ? (int)$_SESSION['selected_company_id'] : null;
        if ($selectedCompanyId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM projects WHERE company_id = ? AND status NOT IN ('Abgeschlossen', 'Archiviert')");
            $stmt->execute([$selectedCompanyId]);
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status NOT IN ('Abgeschlossen', 'Archiviert')");
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Fehler beim Zählen offener Projekte: " . $e->getMessage());
        return 0;
    } catch (Error $e) {
        error_log("Fehler beim Zählen offener Projekte: " . $e->getMessage());
        return 0;
    }
}
?>
<?php
// Rolle einmalig für die gesamte Sidebar laden
if (!isset($row)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($pdo)) {
        require_once dirname(__DIR__) . '/config.php';
    }
    $user_id = $_SESSION['user_id'] ?? null;
    if ($user_id) {
        try {
            $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = :user_id LIMIT 1");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $row = null;
        }
    }
}
$userRole = $row['rolle'] ?? '';
$isAdminOrTechniker = ($userRole === 'Admin' || $userRole === 'Techniker');
$isNotKunde = ($userRole !== 'Kunde');
$canSeeInventory = $isAdminOrTechniker;
if (!$canSeeInventory && isset($pdo) && $pdo instanceof PDO) {
    $__invUid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($__invUid > 0) {
        require_once dirname(__DIR__) . '/inventory_permissions.php';
        $canSeeInventory = inventory_user_can_view_inventory($pdo, $__invUid);
    }
}
?>
<?php
/*
 * Dashboard Mobile: oberer Abstand liegt am body (app-mobile-dashboard-shell), sonst doppelter Offset + body-Scroll.
 * Desktop: Abstand = Nav-Höhe (3.5rem), bündig zur Innenecke Nav/Sidebar/Inhalt.
 */
$__mobNavPad = !empty($dashboardKeepMobileTopNav)
    ? 'max-lg:pt-0 lg:pt-14'
    : 'pt-0 lg:pt-14';
$__shellFlexGrow = !empty($dashboardKeepMobileTopNav) ? ' max-lg:flex-1 max-lg:min-h-0' : '';
/* Mobile: kein overflow-y hidden — sonst klebt der Seiten-Scroll (Flex + min-h-0). Desktop + Vollbild-Chat: weiterhin hidden. */
$__shellOverflow = !empty($serviceMobileFullscreen)
    ? 'overflow-hidden'
    : 'max-lg:overflow-x-hidden max-lg:overflow-y-visible lg:overflow-hidden';
$__sidebarExpanded = !isset($sidebarExpandedFromSettings) || !empty($sidebarExpandedFromSettings);
$__sidebarWidthClass = $__sidebarExpanded ? 'sidebar-w-expanded' : 'w-16';
?>
<div class="app-layout-shell flex <?php echo $__shellOverflow; ?> bg-gray-50 <?php echo $__mobNavPad . $__shellFlexGrow; ?> dark:bg-primary-50<?php echo !empty($serviceMobileFullscreen) ? ' service-mobile-fullscreen-wrapper' : ''; ?>">
  <aside id="sidebar" class="fixed left-0 top-0 z-40 h-screen -translate-x-full transition-transform duration-75 lg:z-0 lg:translate-x-0 lg:pt-14 lg:transition-[width,transform] duration-75 <?php echo $__sidebarWidthClass; ?>" aria-label="Sidebar">
    <div class="sidebar-nav-scroll flex h-full flex-col overflow-y-auto px-3 pt-3 pb-4 bg-white dark:bg-primary-50">
      <?php include __DIR__ . '/sidebar_nav_content.php'; ?>
    </div>
  </aside>
  <script>
  (function () {
    if (typeof finalizeSidebarLayoutPending === 'function') {
      finalizeSidebarLayoutPending();
    } else {
      document.body.classList.remove('sidebar-layout-pending');
    }
  })();
  </script>

  <?php include __DIR__ . '/speed-dial.php';
