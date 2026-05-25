<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
$userRole = null;
$userCompanyId = null;
$userName = '';

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.vorname, u.nachname, u.email, u.rolle, u.company_id
        FROM users u
        WHERE u.id = :user_id
        LIMIT 1
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $userRole = $user['rolle'];
        $userCompanyId = $user['company_id'];
        $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
        if (empty($userName)) {
            $userName = $user['email'] ?? 'Benutzer';
        }
    }
} catch (PDOException $e) {
    error_log("Dashboard: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
}

// Benachrichtigungen-Funktionen einbinden
require_once dirname(__DIR__) . '/assets/notifications.php';

// Footer-Links (gleiche Quelle wie Login-Seite: Admin > Erscheinungsbild > Login-Karten)
$dashboardFooterLinks = [
    ['label' => 'Datenschutz', 'url' => 'https://www.serohub.de/datenschutz/'],
    ['label' => 'Impressum', 'url' => 'https://www.serohub.de/impressum/']
];
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_footer_links' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty(trim($row['setting_value'] ?? ''))) {
        $decoded = json_decode($row['setting_value'], true);
        if (is_array($decoded) && count($decoded) > 0) {
            $dashboardFooterLinks = $decoded;
        }
    }
} catch (PDOException $e) {}

// Branding-Einstellungen für Footer (wie in nav.php)
$footerLogo = 'assets/images/Serohub_Icon.png';
$footerNamePart1 = 'Sero';
$footerNamePart2 = 'Hub';
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2')");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['setting_key'] === 'branding_logo' && !empty(trim($r['setting_value'] ?? ''))) $footerLogo = trim($r['setting_value']);
        if ($r['setting_key'] === 'branding_name_part1' && $r['setting_value'] !== null) $footerNamePart1 = $r['setting_value'];
        if ($r['setting_key'] === 'branding_name_part2' && $r['setting_value'] !== null) $footerNamePart2 = $r['setting_value'];
    }
} catch (PDOException $e) {}
$footerLogoUrl = BASE_URL . ltrim($footerLogo, '/');
$footerBrandName = trim($footerNamePart1 . $footerNamePart2);

// Statistiken sammeln
    $stats = [
        'service_tickets_open' => 0,
        'todos_open' => 0,
        'orders_open' => 0,
        'projects_open' => 0,
        'notifications_unread' => 0,
        'devices_count' => 0,
        'customers_count' => 0,
        'companies_count' => 0
    ];

try {
    if (function_exists('getUnreadNotificationCount')) {
        $stats['notifications_unread'] = getUnreadNotificationCount($userId);
    }
    
    // Tickets
    if ($userRole === 'Admin' || $userRole === 'Techniker') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE status NOT IN ('Geschlossen', 'Archiv')");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['service_tickets_open'] = (int)($result['count'] ?? 0);
    } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE (c.company_id = :company_id OR t.company_id = :company_id) AND t.status NOT IN ('Geschlossen', 'Archiv')");
            $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['service_tickets_open'] = (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Dashboard Firmen-Admin Tickets Fehler: " . $e->getMessage());
            $stats['service_tickets_open'] = 0;
        }
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE (erstellt_von = :user_id OR zugewiesen_an = :user_id) AND status NOT IN ('Geschlossen', 'Archiv')");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['service_tickets_open'] = (int)($result['count'] ?? 0);
    }
    
    // Todos
    if ($userRole === 'Admin' || $userRole === 'Techniker') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM todos WHERE status != 'erledigt'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['todos_open'] = (int)($result['count'] ?? 0);
    } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM todos WHERE company_id = :company_id AND status != 'erledigt'");
        $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['todos_open'] = (int)($result['count'] ?? 0);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM todos WHERE zugewiesen_an = :user_id AND status != 'erledigt'");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['todos_open'] = (int)($result['count'] ?? 0);
    }
    
    // Bestellungen
    if (function_exists('getOpenOrdersCount')) {
        $stats['orders_open'] = getOpenOrdersCount();
    } else {
        // Fallback: Direkt aus DB
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['orders_open'] = (int)($result['count'] ?? 0);
        } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM orders o
                    LEFT JOIN customers c ON o.customer_id = c.id
                    WHERE (o.company_id = :company_id OR c.company_id = :company_id) 
                    AND o.status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')
                ");
                $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['orders_open'] = (int)($result['count'] ?? 0);
            } catch (PDOException $e) {
                error_log("Dashboard Firmen-Admin Bestellungen Fehler: " . $e->getMessage());
                $stats['orders_open'] = 0;
            }
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE erstellt_von = :user_id AND status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['orders_open'] = (int)($result['count'] ?? 0);
        }
    }
    
    // Projekte (nur Admin/Techniker)
    if ($userRole === 'Admin' || $userRole === 'Techniker') {
        $selectedCompanyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null ? (int)$_SESSION['selected_company_id'] : null;
        if ($selectedCompanyId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM projects WHERE company_id = ? AND status NOT IN ('Abgeschlossen', 'Archiviert')");
            $stmt->execute([$selectedCompanyId]);
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status NOT IN ('Abgeschlossen', 'Archiviert')");
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['projects_open'] = (int)($result['count'] ?? 0);
    }
    
    // Geräteanzahl (für Firmen-Admin)
    if ($userRole === 'Firmen-Admin' && $userCompanyId) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM devices d
                LEFT JOIN customers c ON d.customer_id = c.id
                WHERE (d.company_id = :company_id OR c.company_id = :company_id) 
                AND d.status = 'aktiv'
            ");
            $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['devices_count'] = (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Dashboard Firmen-Admin Geräte Fehler: " . $e->getMessage());
            $stats['devices_count'] = 0;
        }
        
        // Kundenanzahl (für Firmen-Admin)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM customers 
                WHERE company_id = :company_id 
                AND status = 'aktiv'
            ");
            $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['customers_count'] = (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Dashboard Firmen-Admin Kunden Fehler: " . $e->getMessage());
            $stats['customers_count'] = 0;
        }
    } else {
        $stats['devices_count'] = 0;
        $stats['customers_count'] = 0;
    }
    
    // Kunden- und Firmenanzahl (für Admin/Techniker)
    if ($userRole === 'Admin' || $userRole === 'Techniker') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers WHERE status = 'aktiv'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['customers_count'] = (int)($result['count'] ?? 0);
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies WHERE status = 'aktiv'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['companies_count'] = (int)($result['count'] ?? 0);
    }
} catch (PDOException $e) {
    error_log("Dashboard: Fehler beim Laden der Statistiken: " . $e->getMessage());
}

$dashboardKeepMobileTopNav = true;
/** Serohub-Logo in der Top-Nav nur ab lg (Desktop); auf dem Handy ausblenden */
$dashboardHideMobileNavLogo = true;
/** Auf dem Dashboard mobil: Zeiterfassung aus Top-Nav ausblenden (eigene Kachel im Inhalt). */
$dashboardHideMobileTopTimeTracking = true;
include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

$greeting = 'Willkommen zurück';
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = 'Guten Morgen';
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = 'Guten Tag';
} elseif ($hour >= 18 && $hour < 22) {
    $greeting = 'Guten Abend';
} else {
    $greeting = 'Gute Nacht';
}
?>

<!-- Chart.js einbinden -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div id="main-content" class="relative w-full min-h-0 overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 lg:h-full app-mobile-no-root-overscroll">
  <main>
    <div class="">
      <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
        
        <!-- Header -->
        <div class="app-content-header col-span-full max-lg:mx-4 lg:mx-0 mb-4 lg:mb-0">
          <div class="">
            <div class="flex items-center justify-between">
              <div>
                <h1 class="hidden lg:block text-2xl font-bold text-gray-900 dark:text-primary-200"><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>. Wie können wir helfen?</h1>
                <p class="hidden lg:block mt-1 text-sm text-gray-600 dark:text-primary-210">Hier ist eine Übersicht über deine Aktivitäten und wichtige Informationen.</p>
              </div>
            </div>
          </div>
        </div>

        <div id="dashboard-install-badge-wrap" class="col-span-full mx-4 mb-4 lg:hidden hidden">
          <button
            id="dashboard-install-badge-btn"
            type="button"
            class="w-full text-left rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm hover:shadow-md active:scale-[0.99] transition-all"
            aria-controls="dashboard-install-modal"
            aria-expanded="false"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">App-Tipp</p>
                <p class="mt-0.5 text-sm font-semibold text-gray-900">Zum Homescreen hinzufügen</p>
                <p class="mt-1 text-xs text-gray-600">Schneller Zugriff und Vollbild wie eine App.</p>
              </div>
              <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-blue-50 border border-blue-200 text-blue-700">+</span>
            </div>
          </button>
        </div>

        <div id="dashboard-install-modal" class="fixed inset-0 z-[80] hidden lg:hidden" aria-hidden="true">
          <div id="dashboard-install-modal-backdrop" class="absolute inset-0 bg-black/45"></div>
          <div class="absolute inset-x-0 bottom-[calc(4.75rem+env(safe-area-inset-bottom,0px))] max-h-[70vh] overflow-y-auto rounded-t-3xl border border-gray-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom,0px))] shadow-2xl">
            <div class="mx-auto mb-3 h-1.5 w-12 rounded-full bg-gray-300 dark:bg-primary-160"></div>
            <div class="flex items-start justify-between gap-4">
              <div>
                <h3 class="text-base font-semibold text-gray-900">Als Web-App installieren</h3>
                <p class="mt-1 text-sm text-gray-600">So legst du die Seite auf deinen Homescreen:</p>
              </div>
              <button id="dashboard-install-modal-close" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600">✕</button>
            </div>
            <div id="dashboard-install-cta-wrap" class="mt-3 hidden">
              <button id="dashboard-install-cta-btn" type="button" class="w-full inline-flex items-center justify-center rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 active:scale-[0.99] transition-all">
                Jetzt als App installieren
              </button>
            </div>
            <ol id="dashboard-install-steps" class="mt-3 space-y-2.5 text-sm text-gray-700"></ol>
          </div>
        </div>

        <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
        <!-- Zeiterfassung (nur Mobil im Dashboard-Content) -->
        <div class="col-span-full mx-4 mb-4 lg:hidden" id="dash-time-tracking-mobile-wrap">
          <div class="rounded-2xl border border-gray-200 bg-white/95 dark:bg-primary-100 dark:border-primary-120 px-3 py-3 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Zeiterfassung</p>
                <p id="time-status" class="mt-0.5 text-sm font-semibold text-gray-500 dark:text-primary-220">Status wird geladen...</p>
                <p id="start-time-info" class="hidden mt-1 text-xs text-gray-600 dark:text-primary-210">
                  Seit <span id="start-time-text" class="font-medium">--:--</span> aktiv
                </p>
              </div>
              <div class="rounded-lg bg-gray-100 dark:bg-primary-120 px-2.5 py-1.5 text-sm font-semibold tabular-nums text-gray-700 dark:text-primary-210" id="time-display">--:--:--</div>
            </div>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
              <button id="start-time-btn" type="button" onclick="startTimeTracking()" class="h-9 inline-flex items-center justify-center rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-3 transition-colors">
                Zeiterfassung starten
              </button>
              <button id="stop-time-btn" type="button" onclick="stopTimeTracking()" class="h-9 hidden inline-flex items-center justify-center rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-3 transition-colors">
                Ausstempeln
              </button>
            </div>
          </div>
        </div>

        <?php endif; ?>

        <!-- Cards-Bereich -->
        <div id="cards-section" class="col-span-full mb-4 max-lg:mx-4 lg:mx-0" style="display: none;">
         
          <div id="dashboard-cards-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-stretch">
            <!-- Cards werden hier dynamisch geladen -->
            <div class="text-center py-4 text-gray-500 dark:text-primary-220 text-sm col-span-full">Lade Nachrichten...</div>
          </div>
        </div>



        <!-- Statistiken-Bereich -->
        <style>
        /* Statistik: Zeitraum-Dropdown und Datumsfelder in der Card */
        #stats-custom-date-row .stats-date-input { padding-top: 0.375rem; padding-bottom: 0.375rem; }
        #stats-start-date::-webkit-calendar-picker-indicator,
        #stats-end-date::-webkit-calendar-picker-indicator {
            opacity: 0;
            position: absolute;
            width: 100%;
            height: 100%;
            cursor: pointer;
            left: 0;
        }
        #stats-custom-date-row > div { position: relative; }
        html.dark #stats-start-date,
        html.dark #stats-end-date { color-scheme: dark; }
        @media (max-width: 1023px) {
            .stats-mobile-controls {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 0.625rem;
                position: relative;
                z-index: 50;
            }
            .stats-mobile-controls #stats-quickfilter-select {
                flex: 1 1 auto;
                min-width: 0;
            }
            .stats-mobile-controls #stats-custom-date-row {
                width: 100%;
            }
            .stats-mobile-overview-nav {
                display: flex;
                gap: 0.5rem;
                overflow-x: auto;
                padding: 0.45rem;
                margin-bottom: 0.875rem;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                background: #e5e7eb;
                border-radius: 1rem;
                position: relative;
                z-index: 40;
            }
            .stats-mobile-overview-nav::-webkit-scrollbar { display: none; }
            .stats-mobile-overview-tab {
                flex: 0 0 auto;
                min-width: 10.5rem;
                border: 1px solid transparent;
                border-radius: 0.85rem;
                background: transparent;
                color: #4b5563;
                font-size: 0.8rem;
                line-height: 1.1rem;
                font-weight: 700;
                padding: 0.6rem 0.7rem;
                text-align: left;
                transition: all 0.2s ease;
            }
            .stats-mobile-overview-tab-title {
                display: block;
                white-space: nowrap;
            }
            .stats-mobile-overview-tab-sub {
                display: block;
                margin-top: 0.125rem;
                font-size: 0.675rem;
                font-weight: 500;
                opacity: 0.85;
                white-space: nowrap;
            }
            .dark .stats-mobile-overview-tab {
                color: rgb(var(--primary-220));
            }
            .dark .stats-mobile-overview-nav {
                background: rgb(var(--primary-120));
            }
            .stats-mobile-overview-tab.is-active {
                border-color: #d1d5db;
                background: #ffffff;
                color: #111827;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08), 0 6px 14px rgba(0, 0, 0, 0.06);
            }
            .dark .stats-mobile-overview-tab.is-active {
                border-color: rgb(var(--primary-320));
                background: rgb(var(--primary-100));
                color: rgb(var(--primary-200));
            }
            .stats-main-mobile-swipe {
                display: flex;
                gap: 0.75rem;
                overflow-x: auto;
                scroll-snap-type: none;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 0.25rem;
                scrollbar-width: none;
                touch-action: pan-x;
                position: relative;
                z-index: 10;
                margin-top: 0.25rem;
                scroll-behavior: auto;
            }
            .stats-main-mobile-swipe::-webkit-scrollbar { display: none; }
            .stats-main-mobile-swipe .stats-card {
                min-width: 92%;
                flex: 0 0 92%;
                scroll-snap-align: none;
                background: #f3f4f6 !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 1.5rem !important;
                padding: 0.95rem !important;
                box-shadow: none !important;
            }
            .dark .stats-main-mobile-swipe .stats-card {
                background: rgb(var(--primary-100)) !important;
                border-color: rgb(var(--primary-120)) !important;
            }
            .stats-main-mobile-swipe #stats-card-tickets .stats-top-kpi-item {
                background: rgba(255,255,255,0.95);
            }
            .dark .stats-main-mobile-swipe #stats-card-tickets .stats-top-kpi-item {
                background: rgb(var(--primary-140));
            }
            .stats-main-mobile-swipe #stats-card-tickets .stats-top-kpis-mobile {
                display: none !important;
            }
            .stats-main-mobile-swipe #stats-card-tickets > .flex.flex-wrap.items-center.justify-between {
                margin-bottom: 0;
            }
            .stats-main-mobile-swipe #stats-card-tickets .stats-header-control,
            .stats-main-mobile-swipe #stats-card-tickets #stats-custom-date-row {
                position: relative;
                z-index: 0;
            }
            .stats-main-mobile-swipe #stats-card-tickets #stats-quickfilter-select {
                flex: 1 1 auto;
                min-width: 0;
            }
            .stats-main-mobile-swipe #stats-card-tickets #compare-btn {
                flex: 0 0 auto;
            }
            .stats-main-mobile-swipe #stats-card-tickets h3,
            .stats-main-mobile-swipe #stats-card-status h3,
            .stats-main-mobile-swipe #stats-card-closers h3 {
                display: none;
            }
            .stats-main-mobile-swipe #stats-card-tickets h3 + *,
            .stats-main-mobile-swipe #stats-card-status h3 + *,
            .stats-main-mobile-swipe #stats-card-closers h3 + * {
                margin-top: 0;
            }
            .stats-main-mobile-swipe #stats-card-tickets h3,
            .stats-main-mobile-swipe #stats-card-status h3,
            .stats-main-mobile-swipe #stats-card-closers h3 {
                margin: 0;
            }
            .stats-main-mobile-swipe #stats-card-tickets .w-8.h-8.rounded-lg,
            .stats-main-mobile-swipe #stats-card-status .w-8.h-8.rounded-lg,
            .stats-main-mobile-swipe #stats-card-closers .w-8.h-8.rounded-lg {
                display: none;
            }
            .stats-mobile-swipe {
                display: flex;
                gap: 0.75rem;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 0.25rem;
            }
            .stats-mobile-swipe::-webkit-scrollbar { display: none; }
            .stats-mobile-swipe .stats-card {
                min-width: 85%;
                flex: 0 0 85%;
                scroll-snap-align: start;
            }
            .stats-priority-swipe {
                display: flex;
                gap: 0.75rem;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 0.25rem;
            }
            .stats-priority-swipe::-webkit-scrollbar { display: none; }
            .stats-priority-swipe .stats-card {
                min-width: 100%;
                flex: 0 0 100%;
                scroll-snap-align: start;
                height: 20vh;
                min-height: 140px;
                max-height: 220px;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
            .stats-priority-swipe .status-chart-wrapper {
                height: 100% !important;
                min-height: 0 !important;
            }
            .stats-priority-swipe #status-chart-legend {
                overflow-y: auto;
            }
            .stats-priority-swipe #avgClosingTimeChartWrapper,
            .stats-priority-swipe #top-closers {
                overflow-y: auto;
            }
            .stats-top-kpis-mobile {
                display: flex;
                gap: 0.5rem;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 0.25rem;
            }
            .stats-top-kpis-mobile::-webkit-scrollbar { display: none; }
            .stats-top-kpis-mobile .stats-top-kpi-item {
                min-width: 48%;
                flex: 0 0 48%;
                scroll-snap-align: start;
            }
        }
        </style>
        <div class="col-span-full max-lg:mx-4 lg:mx-0">
          <div class=" rounded-lg dark:bg-primary-50">
           
            
            <!-- Tickets im Zeitraum – Card an Vorlage (Total Clicks-Style), Farben aus Branding -->
            <div class="grid grid-cols-1 gap-4 w-full">
              <div id="stats-mobile-controls" class="lg:hidden hidden stats-mobile-controls"></div>
              <div id="stats-mobile-overview-nav" class="lg:hidden hidden stats-mobile-overview-nav">
                <button type="button" class="stats-mobile-overview-tab is-active" data-target-card="stats-card-tickets">
                  <span class="stats-mobile-overview-tab-title">Tickets im Zeitraum</span>
                  <span class="stats-mobile-overview-tab-sub">KPI + Verlauf</span>
                </button>
                <button type="button" class="stats-mobile-overview-tab" data-target-card="stats-card-status">
                  <span class="stats-mobile-overview-tab-title">Statusverteilung</span>
                  <span class="stats-mobile-overview-tab-sub">Donut + Legende</span>
                </button>
                <button type="button" class="stats-mobile-overview-tab" data-target-card="stats-card-closers">
                  <span class="stats-mobile-overview-tab-title">Zeiten im Überblick</span>
                  <span class="stats-mobile-overview-tab-sub">Schließ-/Reaktionszeit</span>
                </button>
              </div>
              <div id="stats-main-mobile-swipe" class="lg:hidden hidden stats-main-mobile-swipe"></div>
              <div id="stats-card-tickets" class="stats-card w-full p-0 bg-transparent border-0 rounded-none lg:bg-white lg:dark:bg-primary-100 lg:border lg:border-gray-200 lg:dark:border-primary-120 lg:rounded-2xl lg:p-4">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-gray-200 dark:bg-primary-120 text-gray-600 dark:text-primary-210">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                    </span>
                    <h3 class="text-sm font-medium text-gray-600 dark:text-primary-210 flex items-center gap-2 flex-wrap">
                      <span>Tickets im Zeitraum<span id="compare-info" class="ml-1" style="display: none;"></span></span>
                    </h3>
                  </div>
                  <div id="stats-ticket-controls-anchor" class="hidden"></div>
                  <div id="stats-ticket-controls" class="flex flex-wrap items-center gap-2">
                    <select id="stats-quickfilter-select" class="stats-header-control h-9 min-w-[140px] rounded-lg border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-700 text-sm font-medium text-gray-700 dark:text-primary-210 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 px-3 py-1.5" aria-label="Zeitraum">
                      <option value="today">Heute</option>
                      <option value="yesterday">Gestern</option>
                      <option value="week">Letzte Woche</option>
                      <option value="month" selected>Letzte 30 Tage</option>
                      <option value="90days">Letzte 90 Tage</option>
                      <option value="year">Letztes Jahr</option>
                      <option value="custom">Benutzerdefiniert</option>
                    </select>
                    <div id="stats-custom-date-row" class="flex flex-wrap items-center gap-2" style="display: none;">
                      <div class="flex items-center rounded-lg border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-700 overflow-hidden focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500 h-9">
                        <input type="date" id="stats-start-date" class="stats-date-input flex items-center px-3 py-1.5 text-sm font-medium border-0 bg-transparent text-gray-900 dark:text-primary-210 focus:ring-0 focus:outline-none w-[130px]" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <button type="button" onclick="window.openNativePicker('stats-start-date');" class="flex items-center justify-center p-2 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 focus:outline-none" aria-label="Kalender öffnen">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </button>
                      </div>
                      <span class="text-sm font-medium text-gray-600 dark:text-primary-240">–</span>
                      <div class="flex items-center rounded-lg border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-700 overflow-hidden focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500 h-9">
                        <input type="date" id="stats-end-date" class="stats-date-input flex items-center px-3 py-1.5 text-sm font-medium border-0 bg-transparent text-gray-900 dark:text-primary-210 focus:ring-0 focus:outline-none w-[130px]" value="<?php echo date('Y-m-d'); ?>">
                        <button type="button" onclick="window.openNativePicker('stats-end-date');" class="flex items-center justify-center p-2 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200 focus:outline-none" aria-label="Kalender öffnen">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </button>
                      </div>
                    </div>
                    <button type="button" id="compare-btn" onclick="toggleCompare()" class="stats-header-control stats-header-btn flex items-center justify-center w-9 h-9 rounded-lg border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-700 text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-760 transition-colors" aria-pressed="false" aria-label="Vergleichen" title="Vergleichen">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </button>
                    <input type="checkbox" id="compare-toggle" style="display: none;">
                  </div>
                </div>
                <div class="stats-no-data-info hidden text-center py-4 text-sm text-gray-500 dark:text-primary-220">Keine Daten im gewählten Zeitraum.</div>
                <div class="w-full h-[20vh] min-h-[140px] max-h-[220px] sm:h-[26vh] sm:min-h-[180px] lg:h-[400px] lg:min-h-[350px] lg:max-h-none" style="position: relative;">
                  <canvas id="ticketsChart"></canvas>
                </div>
              </div>
            </div>
            
            <!-- Zweite Zeile: Status-Verteilung + Häufigste Geräte (Liste) + Häufigste Kunden (Liste) -->
            <?php if ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin'): ?>
            <div id="stats-priority-swipe" class="mt-6 lg:hidden hidden stats-priority-swipe"></div>
            <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
              <!-- Status-Verteilung (Vorlage: Devices-Card mit Halb-Donut, Legende mit Kreis + Name + Anzahl | %) -->
              <div id="stats-card-status" class="stats-card bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-2xl p-4">
                <div class="flex items-center gap-2 mb-2.5">
                  <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-gray-200 dark:bg-primary-120 text-gray-600 dark:text-primary-210">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                  </span>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200">Status-Verteilung</h3>
                </div>
                <div class="stats-no-data-info hidden text-center py-5 text-sm text-gray-500 dark:text-primary-220">Keine Daten im gewählten Zeitraum.</div>
                <div class="status-chart-wrapper relative h-60 hidden">
                  <canvas id="statusChart"></canvas>
                </div>
                <ul id="status-chart-legend" class="mt-2 space-y-1.5 hidden"></ul>
              </div>
              <!-- Häufigste Geräte (Vorlage: Sources – Tabellenkopf + Zeilen mit Icon, Name, Anzahl | %) -->
              <div class="stats-card bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-2xl p-4">
                <div class="flex items-center gap-2 mb-3">
                  <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-gray-200 dark:bg-primary-120 text-gray-600 dark:text-primary-210">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                  </span>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200">Häufigste Geräte</h3>
                </div>
                <div class="stats-no-data-info stats-devices-no-data text-center py-6 text-sm text-gray-500 dark:text-primary-220">Keine Daten im gewählten Zeitraum.</div>
                <div id="stats-devices-table-wrap" class="hidden">
                  <table class="w-full text-sm" aria-label="Häufigste Geräte">
                    <tbody id="stats-devices-list" class="divide-y divide-gray-200 dark:divide-primary-230"></tbody>
                  </table>
                </div>
              </div>
              <!-- Häufigste Kunden (Vorlage: Sources – Tabellenkopf + Zeilen mit Icon, Name, Anzahl | %) -->
              <div class="stats-card bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-2xl p-4">
                <div class="flex items-center gap-2 mb-3">
                  <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-gray-200 dark:bg-primary-120 text-gray-600 dark:text-primary-210">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                  </span>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200">Häufigste Kunden</h3>
                </div>
                <div class="stats-no-data-info stats-customers-no-data text-center py-6 text-sm text-gray-500 dark:text-primary-220">Keine Daten im gewählten Zeitraum.</div>
                <div id="stats-customers-table-wrap" class="hidden">
                  <table class="w-full text-sm" aria-label="Häufigste Kunden">
                    <tbody id="stats-customers-list" class="divide-y divide-gray-200 dark:divide-primary-230"></tbody>
                  </table>
                </div>
              </div>
            </div>
            <?php endif; ?>
            
            <!-- Dritte Zeile: Firmen + Schließzeit & Bearbeitungszeit + Meiste Tickets geschlossen (3 nebeneinander) -->
            <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
            <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
              <!-- Häufigste Firmen -->
              <div id="stats-card-times" class="stats-card bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-2xl p-4 flex flex-col min-h-0 w-full">
                <div class="flex items-center gap-2 mb-2.5">
                  <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-gray-200 dark:bg-primary-120 text-gray-600 dark:text-primary-210">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>
                  </span>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200">Häufigste Firmen</h3>
                </div>
                <div class="stats-no-data-info hidden text-center py-5 text-sm text-gray-500 dark:text-primary-220 flex-shrink-0">Keine Daten im gewählten Zeitraum.</div>
                <div class="companies-chart-wrapper hidden flex-1 flex flex-col min-h-[180px]">
                  <canvas id="companiesChart"></canvas>
                </div>
                <div id="companies-chart-legend-wrap" class="mt-2 flex-shrink-0 hidden">
                  <ul id="companies-chart-legend" class="space-y-1.5"></ul>
                  <button type="button" id="companies-legend-more-btn" class="mt-2 text-sm text-primary-600 dark:text-primary-400 hover:underline hidden">Mehr anzeigen</button>
                </div>
              </div>
              <!-- Schließzeit, Bearbeitungszeit & Reaktionszeit (KPI-Card) -->
              <div id="stats-card-closers" class="stats-card bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-2xl p-4 flex flex-col min-h-0 w-full">
                <div class="flex items-center gap-2 mb-3 flex-shrink-0">
                  <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-gray-200 dark:bg-primary-120 text-gray-600 dark:text-primary-210">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </span>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200">Zeiten im Überblick</h3>
                </div>
                <div class="stats-no-data-info hidden text-center py-6 text-sm text-gray-500 dark:text-primary-220 flex-shrink-0">Keine Daten im gewählten Zeitraum.</div>
                <div id="avgClosingTimeChartWrapper" class="avg-closing-time-stats flex-1 flex flex-col hidden">
                  <div id="time-stat-closing" class="time-stat-row hidden flex items-center gap-3 py-3 px-3 rounded-t-2xl border-b border-gray-200/80 dark:border-primary-230">
                    <span class="time-stat-dot w-2 h-8 rounded-full flex-shrink-0" style="background: var(--time-color-closing, #3B82F6);"></span>
                    <div class="flex-1 min-w-0">
                      <div class="text-xs font-medium text-gray-500 dark:text-primary-220 uppercase tracking-wide">Schließzeit</div>
                      <div class="text-lg font-semibold text-gray-900 dark:text-primary-200 tabular-nums"><span class="time-stat-value">–</span> <span class="time-stat-unit text-sm font-normal text-gray-600 dark:text-primary-210">–</span></div>
                    </div>
                  </div>
                  <div id="time-stat-bearbeitung" class="time-stat-row hidden flex items-center gap-3 py-3 px-3 border-b border-gray-200/80 dark:border-primary-230">
                    <span class="time-stat-dot w-2 h-8 rounded-full flex-shrink-0" style="background: var(--time-color-bearbeitung, #22C55E);"></span>
                    <div class="flex-1 min-w-0">
                      <div class="text-xs font-medium text-gray-500 dark:text-primary-220 uppercase tracking-wide">Bearbeitungszeit</div>
                      <div class="text-lg font-semibold text-gray-900 dark:text-primary-200 tabular-nums"><span class="time-stat-value">–</span> <span class="time-stat-unit text-sm font-normal text-gray-600 dark:text-primary-210">–</span></div>
                    </div>
                  </div>
                  <div id="time-stat-reaktion" class="time-stat-row hidden flex items-center gap-3 py-3 px-3 rounded-b-2xl">
                    <span class="time-stat-dot w-2 h-8 rounded-full flex-shrink-0" style="background: var(--time-color-reaktion, #F59E0B);"></span>
                    <div class="flex-1 min-w-0">
                      <div class="text-xs font-medium text-gray-500 dark:text-primary-220 uppercase tracking-wide">Reaktionszeit</div>
                      <div class="text-lg font-semibold text-gray-900 dark:text-primary-200 tabular-nums"><span class="time-stat-value">–</span> <span class="time-stat-unit text-sm font-normal text-gray-600 dark:text-primary-210">–</span></div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Meiste Tickets geschlossen -->
              <div class="stats-card bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-2xl p-4 flex flex-col min-h-0 w-full">
                <div class="flex items-center gap-2 mb-2.5">
                  <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-gray-200 dark:bg-primary-120 text-gray-600 dark:text-primary-210">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </span>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200">Meiste Tickets geschlossen</h3>
                </div>
                <div class="stats-no-data-info hidden text-center py-5 text-sm text-gray-500 dark:text-primary-220 flex-shrink-0">Keine Daten im gewählten Zeitraum.</div>
                <div id="top-closers" class="flex-1 min-h-0 overflow-y-auto text-center py-4 text-gray-500 dark:text-primary-220 text-sm">Lade...</div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Benachrichtigungen-Bereich -->

        
       

        

      </div>

        <!-- Footer -->
        <footer class="col-span-full mt-4 max-lg:mx-4 lg:mx-0 rounded-lg dark:bg-primary-50 antialiased">
          <div class="w-full 2xl:px-0">

    <div class=" md:py-2">
      <div class="xl:flex xl:items-center">
      <a href="https://www.serohub.de" class="mr-4 flex items-center gap-2<?php echo !empty($dashboardHideMobileNavLogo) ? ' max-lg:hidden lg:flex' : ''; ?>">
  <img src="<?php echo htmlspecialchars($footerLogoUrl); ?>" class="h-12" alt="Logo" onerror="this.src='<?php echo BASE_URL; ?>assets/images/Serohub_Icon.png'">

  <span class="hidden md:inline whitespace-nowrap text-3xl font-bold leading-none flex items-center">
<span class="dark:text-white text-primary-850"><?php echo htmlspecialchars($footerNamePart1); ?></span><span class="text-primary-420 inline-block"><?php echo htmlspecialchars($footerNamePart2); ?></span>
  </span>
</a>

        <div class="mt-4 xl:mt-0">
          <div class="flex flex-wrap items-center gap-4 text-sm">
            <p class="text-gray-500 dark:text-primary-220">Powered by <a href="https://www.serohub.de" class="hover:underline">Serohub</a> · © 2024-2026  · v0.1.5 (Beta) · All rights reserved.</p>

            <ul class="flex flex-wrap items-center gap-4 text-gray-900 dark:text-primary-200">
              <?php foreach ($dashboardFooterLinks as $fl): ?>
              <?php
                $label = trim($fl['label'] ?? '');
                $url = trim($fl['url'] ?? '');
                if ($label === '' && $url === '') continue;
              ?>
              <li><a href="<?php echo $url !== '' ? htmlspecialchars($url) : '#'; ?>" title="" class="font-medium hover:underline"><?php echo htmlspecialchars($label !== '' ? $label : $url); ?></a></li>
              <?php endforeach; ?>
            </ul>
   </div>
        </div>
      </div>
    </div>
          </div>
        </footer>
      </div>
    </div>
<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>

<script>
(function () {
    var badgeWrap = document.getElementById('dashboard-install-badge-wrap');
    var badgeBtn = document.getElementById('dashboard-install-badge-btn');
    var modal = document.getElementById('dashboard-install-modal');
    var modalClose = document.getElementById('dashboard-install-modal-close');
    var modalBackdrop = document.getElementById('dashboard-install-modal-backdrop');
    var stepsList = document.getElementById('dashboard-install-steps');
    var installCtaWrap = document.getElementById('dashboard-install-cta-wrap');
    var installCtaBtn = document.getElementById('dashboard-install-cta-btn');
    if (!badgeWrap || !badgeBtn || !modal || !stepsList) return;

    var DISMISS_KEY = 'dashboardInstallBadgeDismissed';
    var deferredInstallPrompt = null;

    function isMobileViewport() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function isStandaloneMode() {
        var standaloneMedia = false;
        try {
            standaloneMedia = window.matchMedia('(display-mode: standalone)').matches;
        } catch (e) {}
        var iosStandalone = window.navigator.standalone === true;
        return standaloneMedia || iosStandalone;
    }

    function getInstallSteps() {
        var ua = navigator.userAgent || '';
        var isIOS = /iPhone|iPad|iPod/i.test(ua);
        var isAndroid = /Android/i.test(ua);
        if (isIOS) {
            return [
                'Tippe unten im Browser auf das Teilen-Symbol.',
                'Wähle "Zum Home-Bildschirm".',
                'Bestätige mit "Hinzufügen".'
            ];
        }
        if (isAndroid) {
            return [
                'Tippe oben rechts auf das Browser-Menü (⋮).',
                'Wähle "App installieren" oder "Zum Startbildschirm hinzufügen".',
                'Bestätige die Installation mit "Installieren"/"Hinzufügen".'
            ];
        }
        return [
            'Öffne das Browser-Menü.',
            'Wähle die Option zum Installieren oder "Zum Startbildschirm hinzufügen".',
            'Bestätige den Dialog.'
        ];
    }

    function renderSteps() {
        stepsList.innerHTML = '';
        getInstallSteps().forEach(function (step) {
            var li = document.createElement('li');
            li.className = 'rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5';

            var row = document.createElement('div');
            row.className = 'flex items-start gap-2.5';

            var index = document.createElement('span');
            index.className = 'inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white';
            index.textContent = String(stepsList.children.length + 1);

            var text = document.createElement('p');
            text.className = 'text-sm leading-5 text-gray-700';
            text.textContent = step;

            row.appendChild(index);
            row.appendChild(text);
            li.appendChild(row);
            stepsList.appendChild(li);
        });
    }

    function openModal() {
        renderSteps();
        updateInstallButtonVisibility();
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        badgeBtn.setAttribute('aria-expanded', 'true');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        badgeBtn.setAttribute('aria-expanded', 'false');
    }

    function updateBadgeVisibility() {
        var dismissed = localStorage.getItem(DISMISS_KEY) === '1';
        var shouldShow = isMobileViewport() && !isStandaloneMode() && !dismissed;
        if (shouldShow) {
            badgeWrap.classList.remove('hidden');
        } else {
            badgeWrap.classList.add('hidden');
            closeModal();
        }
    }

    function updateInstallButtonVisibility() {
        if (!installCtaWrap) return;
        if (deferredInstallPrompt) {
            installCtaWrap.classList.remove('hidden');
        } else {
            installCtaWrap.classList.add('hidden');
        }
    }

    async function triggerInstallPrompt() {
        if (!deferredInstallPrompt) return;
        try {
            deferredInstallPrompt.prompt();
            var choiceResult = await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            updateInstallButtonVisibility();
            if (choiceResult && choiceResult.outcome === 'accepted') {
                localStorage.setItem(DISMISS_KEY, '1');
                closeModal();
                updateBadgeVisibility();
            }
        } catch (e) {}
    }

    badgeBtn.addEventListener('click', openModal);
    if (installCtaBtn) installCtaBtn.addEventListener('click', triggerInstallPrompt);
    if (modalClose) modalClose.addEventListener('click', closeModal);
    if (modalBackdrop) modalBackdrop.addEventListener('click', closeModal);
    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredInstallPrompt = e;
        updateInstallButtonVisibility();
    });
    window.addEventListener('resize', updateBadgeVisibility);
    window.addEventListener('appinstalled', function () {
        localStorage.setItem(DISMISS_KEY, '1');
        deferredInstallPrompt = null;
        updateInstallButtonVisibility();
        updateBadgeVisibility();
    });

    updateBadgeVisibility();
    updateInstallButtonVisibility();
})();
</script>

<?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
<script>
(function () {
    var consumablesListApi = <?php echo json_encode(rtrim(BASE_URL, '/') . '/inventory/api/consumables.php'); ?>;
    function dashInvStockStatuses(c) {
        var lager = c.lagerbestand != null ? Number(c.lagerbestand) : 0;
        var mindest = c.mindestbestand != null ? Number(c.mindestbestand) : null;
        var hasOpenOrder = c.has_open_order === 1 || c.has_open_order === true || c.has_open_order === '1';
        var pendingStockin = c.pending_stockin_after_delivery === 1 || c.pending_stockin_after_delivery === true || c.pending_stockin_after_delivery === '1';
        var statuses = [];
        if (hasOpenOrder) statuses.push('nachbestellt');
        if (pendingStockin) statuses.push('bestellung_angekommen');
        if (lager <= 0) statuses.push('leer');
        if (mindest != null && lager < mindest && statuses.indexOf('nachbestellt') === -1 && !pendingStockin) {
            statuses.push('muss_nachbestellen');
        }
        if (statuses.length === 0) statuses.push('bestand_vorhanden');
        return statuses;
    }
    function dashInvNeedsScanReview(c) {
        if (!c) return false;
        var v = c.scan_auto_review;
        return v === 1 || v === true || v === '1';
    }
    function updateDashInvLagerKpis() {
        var elTotal = document.getElementById('dash-inv-mobile-kpi-total');
        if (!elTotal) return;
        fetch(consumablesListApi, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.consumables) return;
                var list = data.consumables;
                var nLeer = 0;
                var nMelden = 0;
                var nLieferung = 0;
                var nScan = 0;
                list.forEach(function (c) {
                    var st = dashInvStockStatuses(c);
                    if (st.indexOf('leer') !== -1) nLeer++;
                    if (st.indexOf('muss_nachbestellen') !== -1) nMelden++;
                    if (st.indexOf('bestellung_angekommen') !== -1) nLieferung++;
                    if (dashInvNeedsScanReview(c)) nScan++;
                });
                elTotal.textContent = String(list.length);
                var elL = document.getElementById('dash-inv-mobile-kpi-leer');
                var elM = document.getElementById('dash-inv-mobile-kpi-melden');
                var elF = document.getElementById('dash-inv-mobile-kpi-lieferung');
                if (elL) elL.textContent = String(nLeer);
                if (elM) elM.textContent = String(nMelden);
                if (elF) elF.textContent = String(nLieferung);
                var scanP = document.getElementById('dash-inv-mobile-scan-hint');
                if (scanP) {
                    if (nScan > 0) {
                        scanP.textContent = nScan === 1
                            ? '1 Scan-Artikel: Im Lager unter Bearbeiten prüfen.'
                            : String(nScan) + ' Scan-Artikel: Im Lager unter Bearbeiten prüfen.';
                        scanP.classList.remove('hidden');
                    } else {
                        scanP.textContent = '';
                        scanP.classList.add('hidden');
                    }
                }
            })
            .catch(function () {});
    }
    updateDashInvLagerKpis();
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) updateDashInvLagerKpis();
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    var ticketsListApi = <?php echo json_encode(rtrim(BASE_URL, '/') . '/tickets/api/tickets.php'); ?>;
    function dashTicketCountOpenCombined(t) {
        var openStatuses = ['Neu', 'In Bearbeitung', 'Bestellung offen', 'Warteschlange', 'Geplant'];
        return openStatuses.indexOf(t.status) !== -1;
    }
    function dashTicketCountWartend(t) {
        return t.status === 'Warteschlange' || t.status === 'Geplant';
    }
    function updateDashTicketKpis() {
        var elOffen = document.getElementById('dash-ticket-kpi-offen');
        if (!elOffen) return;
        var selectedCompanyId = null;
        try {
            var sel = localStorage.getItem('selectedUserOption');
            if (sel) {
                var data = JSON.parse(sel);
                selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id, 10) : null;
            }
        } catch (e) {}
        var url = ticketsListApi;
        var q = [];
        if (selectedCompanyId) q.push('company_id=' + encodeURIComponent(String(selectedCompanyId)));
        if (q.length) url += '?' + q.join('&');
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !Array.isArray(data.tickets)) return;
                var list = data.tickets;
                var nOffen = 0;
                var nNeu = 0;
                var nBearb = 0;
                var nWartend = 0;
                list.forEach(function (t) {
                    if (dashTicketCountOpenCombined(t)) nOffen++;
                    if (t.status === 'Neu') nNeu++;
                    if (t.status === 'In Bearbeitung') nBearb++;
                    if (dashTicketCountWartend(t)) nWartend++;
                });
                elOffen.textContent = String(nOffen);
                var elN = document.getElementById('dash-ticket-kpi-neu');
                var elB = document.getElementById('dash-ticket-kpi-bearbeitung');
                var elW = document.getElementById('dash-ticket-kpi-wartend');
                if (elN) elN.textContent = String(nNeu);
                if (elB) elB.textContent = String(nBearb);
                if (elW) elW.textContent = String(nWartend);
            })
            .catch(function () {});
    }
    updateDashTicketKpis();
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) updateDashTicketKpis();
    });
    window.addEventListener('storage', function (e) {
        if (e.key === 'selectedUserOption') updateDashTicketKpis();
    });
    window.addEventListener('companyChanged', function () {
        updateDashTicketKpis();
    });
})();
</script>

<script>
const dashboardApiUrl = '<?php echo BASE_URL; ?>dashboard/api/dashboard.php';
const userRole = '<?php echo addslashes($userRole); ?>';
<?php
$chartDark = [
    'open' => $primaryColors[660] ?? '#3B82F6',
    'closed' => $primaryColors[1040] ?? '#22C55E',
    'compareOpen' => $primaryColors[1100] ?? '#38BDF8',
    'compareClosed' => $primaryColors[1060] ?? '#F59E0B',
    'company' => $primaryColors[250] ?? '#155dfc',
    'legendText' => $primaryColors[210] ?? '#94A3B8'
];
$chartLight = [
    'open' => $primaryLightColors[420] ?? '#155dfc',
    'closed' => $primaryLightColors[1040] ?? '#22C55E',
    'compareOpen' => $primaryLightColors[1100] ?? '#0EA5E9',
    'compareClosed' => $primaryLightColors[1060] ?? '#F59E0B',
    'company' => $primaryLightColors[250] ?? '#155dfc',
    'legendText' => $primaryLightColors[210] ?? '#6B7280'
];
?>
const dashboardChartColors = { dark: <?php echo json_encode($chartDark); ?>, light: <?php echo json_encode($chartLight); ?> };

function setupMobilePriorityStatsSwipe() {
    const mainSwipeWrap = document.getElementById('stats-main-mobile-swipe');
    const mainSwipeNav = document.getElementById('stats-mobile-overview-nav');
    const mobileControlsWrap = document.getElementById('stats-mobile-controls');
    const ticketControls = document.getElementById('stats-ticket-controls');
    const ticketControlsAnchor = document.getElementById('stats-ticket-controls-anchor');
    const swipeWrap = document.getElementById('stats-priority-swipe');
    const ticketsCard = document.getElementById('stats-card-tickets');
    const statusCard = document.getElementById('stats-card-status');
    const closersCard = document.getElementById('stats-card-closers');
    const isMobile = window.matchMedia('(max-width: 1023px)').matches;
    if (!mainSwipeWrap || !mainSwipeNav || !ticketsCard || !mobileControlsWrap || !ticketControls || !ticketControlsAnchor) return;

    if (!isMobile) {
        if (ticketControls.parentElement !== ticketControlsAnchor.parentElement) {
            ticketControlsAnchor.insertAdjacentElement('afterend', ticketControls);
        }
        mobileControlsWrap.classList.add('hidden');
        return;
    }

    const availableCards = {
        'stats-card-tickets': ticketsCard,
        'stats-card-status': statusCard,
        'stats-card-closers': closersCard
    };
    const allNavButtons = Array.from(mainSwipeNav.querySelectorAll('[data-target-card]'));
    let navButtons = allNavButtons.filter(function(btn) {
        const targetId = btn.getAttribute('data-target-card');
        const exists = !!(targetId && availableCards[targetId]);
        btn.classList.toggle('hidden', !exists);
        return exists;
    });
    if (!navButtons.length) return;

    if (ticketControls.parentElement !== mobileControlsWrap) {
        mobileControlsWrap.appendChild(ticketControls);
    }
    mobileControlsWrap.classList.remove('hidden');
    mainSwipeNav.classList.remove('hidden');
    mainSwipeWrap.classList.remove('hidden');
    if (swipeWrap) swipeWrap.classList.add('hidden');
    navButtons.forEach(function(btn) {
        const targetId = btn.getAttribute('data-target-card');
        const cardEl = targetId ? availableCards[targetId] : null;
        if (cardEl) mainSwipeWrap.appendChild(cardEl);
    });
    const navButtonByCardId = {};
    navButtons.forEach(function(btn) {
        const targetId = btn.getAttribute('data-target-card');
        if (targetId) navButtonByCardId[targetId] = btn;
    });
    let cards = navButtons.map(function(btn) {
        const targetId = btn.getAttribute('data-target-card');
        return targetId ? availableCards[targetId] : null;
    }).filter(Boolean);
    let activeIndex = 0;

    function setActiveByCard(cardEl) {
        if (!cardEl || !cardEl.id) return;
        navButtons.forEach(function(btn) {
            const isActive = btn.getAttribute('data-target-card') === cardEl.id;
            btn.classList.toggle('is-active', isActive);
            if (isActive) {
                btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        });
    }
    function detectActiveSlide() {
        const wrapRect = mainSwipeWrap.getBoundingClientRect();
        let bestIndex = 0;
        let bestDistance = Number.MAX_SAFE_INTEGER;
        cards.forEach(function(card, index) {
            const cardRect = card.getBoundingClientRect();
            const distance = Math.abs(cardRect.left - wrapRect.left);
            if (distance < bestDistance) {
                bestDistance = distance;
                bestIndex = index;
            }
        });
        activeIndex = bestIndex;
        setActiveByCard(cards[bestIndex]);
    }
    function rotateForward() {
        if (cards.length <= 1) return;
        const currentCard = cards[activeIndex] || cards[0];
        const firstCard = cards[0];
        const beforeLeft = currentCard ? currentCard.offsetLeft : mainSwipeWrap.scrollLeft;
        cards = cards.slice(1).concat(firstCard);
        mainSwipeWrap.appendChild(firstCard);
        const movedBtn = firstCard.id ? navButtonByCardId[firstCard.id] : null;
        if (movedBtn) {
            navButtons = navButtons.slice(1).concat(movedBtn);
            mainSwipeNav.appendChild(movedBtn);
        }
        const afterLeft = currentCard ? currentCard.offsetLeft : mainSwipeWrap.scrollLeft;
        mainSwipeWrap.scrollLeft += (afterLeft - beforeLeft);
        activeIndex = cards.indexOf(currentCard);
        if (activeIndex < 0) activeIndex = 0;
    }
    function rotateBackward() {
        if (cards.length <= 1) return;
        const currentCard = cards[activeIndex] || cards[0];
        const lastCard = cards[cards.length - 1];
        const beforeLeft = currentCard ? currentCard.offsetLeft : mainSwipeWrap.scrollLeft;
        cards = [lastCard].concat(cards.slice(0, cards.length - 1));
        mainSwipeWrap.insertBefore(lastCard, mainSwipeWrap.firstChild);
        const movedBtn = lastCard.id ? navButtonByCardId[lastCard.id] : null;
        if (movedBtn) {
            navButtons = [movedBtn].concat(navButtons.slice(0, navButtons.length - 1));
            mainSwipeNav.insertBefore(movedBtn, mainSwipeNav.firstChild);
        }
        const afterLeft = currentCard ? currentCard.offsetLeft : mainSwipeWrap.scrollLeft;
        mainSwipeWrap.scrollLeft += (afterLeft - beforeLeft);
        activeIndex = cards.indexOf(currentCard);
        if (activeIndex < 0) activeIndex = 0;
    }
    function recycleAtBounds() {
        const maxScroll = mainSwipeWrap.scrollWidth - mainSwipeWrap.clientWidth;
        if (maxScroll <= 0) return;
        const edgeThreshold = 24;
        if (mainSwipeWrap.scrollLeft <= edgeThreshold) {
            rotateBackward();
        } else if (mainSwipeWrap.scrollLeft >= maxScroll - edgeThreshold) {
            rotateForward();
        }
    }
    let rafPending = false;
    if (mainSwipeWrap.dataset.scrollBound !== '1') {
        mainSwipeWrap.addEventListener('scroll', function() {
            if (rafPending) return;
            rafPending = true;
            requestAnimationFrame(function() {
                recycleAtBounds();
                detectActiveSlide();
                rafPending = false;
            });
        }, { passive: true });
        mainSwipeWrap.dataset.scrollBound = '1';
    }

    if (mainSwipeNav.dataset.clickBound !== '1') {
        mainSwipeNav.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-target-card]');
            if (!btn) return;
            const activeButtons = Array.from(mainSwipeNav.querySelectorAll('[data-target-card]:not(.hidden)'));
            const index = activeButtons.indexOf(btn);
            const cardId = btn.getAttribute('data-target-card');
            const targetCard = cardId ? document.getElementById(cardId) : null;
            if (!targetCard) return;
            mainSwipeWrap.scrollTo({
                left: targetCard.offsetLeft - mainSwipeWrap.offsetLeft,
                behavior: 'smooth'
            });
            if (index >= 0) {
                activeIndex = index;
                setActiveByCard(targetCard);
            }
        });
        mainSwipeNav.dataset.clickBound = '1';
    }
    detectActiveSlide();
    mainSwipeWrap.dataset.ready = '1';
}

function hexToRgb(hex) {
    if (!hex || hex.startsWith('rgb')) return hex || 'rgb(59,130,246)';
    const m = String(hex).replace(/^#/, '').match(/^([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i);
    if (!m) return hex;
    return 'rgb(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ')';
}
function hexToRgba(hex, a) {
    if (!hex || hex.startsWith('rgba')) return hex || 'rgba(59,130,246,0.1)';
    if (hex.startsWith('rgb')) return hex.replace('rgb', 'rgba').replace(')', ',' + a + ')');
    const m = String(hex).replace(/^#/, '').match(/^([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i);
    if (!m) return hex;
    return 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')';
}

// Charts
let ticketsChart = null;
let companiesChart = null;
let statusChart = null;
let avgClosingTimeChart = null;

// Beim Laden
document.addEventListener('DOMContentLoaded', function() {
    loadCards();
    // Rechtsklick auf System-Card: Link in neuem Tab öffnen
    const cardsContainer = document.getElementById('dashboard-cards-container');
    if (cardsContainer) {
        cardsContainer.addEventListener('contextmenu', function(e) {
            const card = e.target.closest('[data-system-card-href]');
            if (card) {
                const href = card.getAttribute('data-system-card-href');
                if (href) {
                    e.preventDefault();
                    window.open(href, '_blank', 'noopener,noreferrer');
                }
            }
        });
    }
    // Standardfilter: Letzte 30 Tage
    setQuickFilter('month');
    updateCompareButton();
    loadStatistics();
    loadNotifications();
    // Zeitraum automatisch anwenden bei Änderung der Datumsfelder; Quickfilter alle normal (keiner aktiv)
    const startInput = document.getElementById('stats-start-date');
    const endInput = document.getElementById('stats-end-date');
    const quickfilterSelect = document.getElementById('stats-quickfilter-select');
    const customDateRow = document.getElementById('stats-custom-date-row');
    if (startInput) startInput.addEventListener('change', function() { setQuickFilterSelectCustom(); loadStatistics(); });
    if (endInput) endInput.addEventListener('change', function() { setQuickFilterSelectCustom(); loadStatistics(); });
    if (quickfilterSelect) quickfilterSelect.addEventListener('change', function() {
        const v = quickfilterSelect.value;
        if (v === 'custom') {
            if (customDateRow) customDateRow.style.display = 'flex';
        } else {
            if (customDateRow) customDateRow.style.display = 'none';
            setQuickFilter(v);
        }
    });
    <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
    loadTimeline();
    checkTimeTrackingStatus();
    <?php endif; ?>
});

<?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
function updateTimeUI(isRunning) {
    const statusEl = document.getElementById('time-status');
    const startBtn = document.getElementById('start-time-btn');
    const stopBtn = document.getElementById('stop-time-btn');
    const startTimeInfo = document.getElementById('start-time-info');
    const startTimeText = document.getElementById('start-time-text');
    const timeDisplay = document.getElementById('time-display');
    
    if (isRunning) {
        if (statusEl) {
            statusEl.textContent = 'Eingestempelt';
            statusEl.className = 'text-sm font-semibold text-green-600 dark:text-green-400';
        }
        if (startBtn) startBtn.classList.add('hidden');
        if (stopBtn) stopBtn.classList.remove('hidden');
        if (startTime) {
            const timeStr = startTime.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            if (startTimeText) startTimeText.textContent = timeStr;
            if (startTimeInfo) startTimeInfo.classList.remove('hidden');
        }
    } else {
        if (statusEl) {
            statusEl.textContent = 'Nicht eingestempelt';
            statusEl.className = 'text-sm font-semibold text-gray-500 dark:text-primary-220';
        }
        if (startBtn) startBtn.classList.remove('hidden');
        if (stopBtn) stopBtn.classList.add('hidden');
        if (startTimeInfo) startTimeInfo.classList.add('hidden');
        if (timeDisplay) timeDisplay.textContent = '--:--:--';
        if (timeTrackingInterval) {
            clearInterval(timeTrackingInterval);
            timeTrackingInterval = null;
        }
    }
}

function startTimeDisplay() {
    if (timeTrackingInterval) {
        clearInterval(timeTrackingInterval);
    }
    
    timeTrackingInterval = setInterval(() => {
        if (startTime) {
            const now = new Date();
            const diff = now - startTime;
            const hours = Math.floor(diff / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            const timeStr = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            const timeDisplay = document.getElementById('time-display');
            if (timeDisplay) timeDisplay.textContent = timeStr;
        }
    }, 1000);
    
    if (startTime) {
        const now = new Date();
        const diff = now - startTime;
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        const timeStr = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        const timeDisplay = document.getElementById('time-display');
        if (timeDisplay) timeDisplay.textContent = timeStr;
    }
}
<?php endif; ?>


// Cards laden (dynamisch aus DB)
async function loadCards() {
    try {
        const response = await fetch(dashboardApiUrl + '?action=get_cards');
        const data = await response.json();
        
        const cardsSection = document.getElementById('cards-section');
        const dashBaseUrl = '<?php echo BASE_URL; ?>';
        if (data.success && data.cards && data.cards.length > 0) {
            cardsSection.style.display = 'block';
            const container = document.getElementById('dashboard-cards-container');
            container.innerHTML = data.cards.map(card => {
                const isOrderArrived = card.id === 'system_order_arrived';
                const isOrderAtCustomer = card.id === 'system_order_at_customer';
                const isOrderInWarehouse = card.id === 'system_order_in_warehouse';
                const isTicketsAssigned = card.id === 'system_tickets_assigned';
                const isTicketsNoTime = card.id === 'system_tickets_closed_no_time';
                const isTicketsNichtAbgerechnet = card.id === 'system_tickets_nicht_abgerechnet';
                const isServicePinned = (typeof card.id === 'string' && card.id.startsWith('system_service_pinned_'));
                const isWartungZahlung = card.type === 'wartung_zahlung' || (card.id && card.id.startsWith('wartung_zahlung_'));
                const imgSrc = card.bild ? (card.bild.startsWith('http') ? card.bild : (dashBaseUrl + card.bild.replace(/^\//, ''))) : null;
                const imgDarkSrc = card.bild_dark ? (card.bild_dark.startsWith('http') ? card.bild_dark : (dashBaseUrl + card.bild_dark.replace(/^\//, ''))) : null;
                const hasImg = imgSrc || imgDarkSrc;
                let mediaHtml;
                if (imgSrc && imgDarkSrc) {
                    mediaHtml = `<img src="${escapeHtml(imgSrc)}" alt="" class="w-auto mx-auto h-40 object-contain dark:hidden"><img src="${escapeHtml(imgDarkSrc)}" alt="" class="w-auto mx-auto h-40 object-contain hidden dark:block">`;
                } else if (hasImg) {
                    const src = imgSrc || imgDarkSrc;
                    mediaHtml = `<img src="${escapeHtml(src)}" alt="" class="w-auto mx-auto h-40 object-contain text-gray-800 dark:text-white">`;
                } else if (isOrderArrived) {
                    mediaHtml = `<div class="w-auto mx-auto h-40 flex items-center justify-center text-green-600 dark:text-green-500"><svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></div>`;
                } else if (isOrderAtCustomer) {
                    mediaHtml = `<div class="w-auto mx-auto h-40 flex items-center justify-center text-amber-600 dark:text-amber-500"><svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1h-1m-1-1V6a1 1 0 011-1h2a1 1 0 011 1v10m-1 1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg></div>`;
                } else if (isOrderInWarehouse) {
                    mediaHtml = `<div class="w-auto mx-auto h-40 flex items-center justify-center text-blue-600 dark:text-blue-500"><svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg></div>`;
                } else if (isTicketsAssigned) {
                    mediaHtml = `<div class="w-auto mx-auto h-40 flex items-center justify-center text-primary-600 dark:text-primary-400"><svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>`;
                } else if (isTicketsNoTime) {
                    mediaHtml = `<div class="w-auto mx-auto h-40 flex items-center justify-center text-amber-600 dark:text-amber-500"><svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>`;
                } else if (isTicketsNichtAbgerechnet) {
                    mediaHtml = `<div class="w-auto mx-auto h-40 flex items-center justify-center text-amber-600 dark:text-amber-500"><svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg></div>`;
                } else if (isWartungZahlung) {
                    mediaHtml = `<div class="w-auto mx-auto h-40 flex items-center justify-center text-amber-500 dark:text-amber-400"><svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></div>`;
                } else {
                    mediaHtml = `<div class="w-auto mx-auto h-40 flex items-center justify-center text-gray-400 dark:text-primary-240"><svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg></div>`;
                }
                
                const buttonClass = card.type === 'warning' 
                    ? 'bg-primary-700 hover:bg-primary-800 dark:bg-primary-600 dark:hover:bg-primary-700'
                    : 'bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800';
                
                const isSystemCard = isOrderArrived || isOrderAtCustomer || isOrderInWarehouse || isTicketsAssigned || isTicketsNoTime || isTicketsNichtAbgerechnet || isServicePinned;
                const isDbCardWithImg = hasImg && !isSystemCard && !isWartungZahlung;
                const headerBg = 'bg-gray-50 dark:bg-primary-140 border-gray-200 dark:border-primary-120';
                const headerIconBg = 'bg-gray-200 dark:bg-primary-120 text-gray-600 dark:text-primary-210';
                const sidebarIconOrders = '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/>';
                const sidebarIconService = '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/>';
                const systemCardIconSvg = (isOrderArrived || isOrderAtCustomer || isOrderInWarehouse) ? sidebarIconOrders : (isTicketsAssigned || isTicketsNoTime || isTicketsNichtAbgerechnet || isServicePinned) ? sidebarIconService : '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>';
                const systemCardIconClasses = 'bg-gray-100 dark:bg-primary-140 text-gray-600 dark:text-primary-210';
                const systemCardLink = (card.link && card.link !== '#') ? card.link : null;
                const dismissTitle = isServicePinned ? 'Loslösen' : 'Verwerfen';
                const dismissIconSvg = isServicePinned
                    ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-5 1.5L12 19l-2-5.5L5 12V3z"/>'
                    : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
                if (isSystemCard) {
                    const cardClasses = 'h-full flex flex-col rounded-2xl border border-gray-100 dark:border-primary-120/80 bg-white dark:bg-primary-100 shadow-sm overflow-hidden relative hover:shadow transition-shadow';
                    const dismissBtn = `<button type="button" onclick="event.preventDefault(); event.stopPropagation(); dismissCard('${escapeHtml(card.id)}')" class="absolute top-2.5 right-2.5 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-primary-200 dark:hover:bg-primary-140 transition-colors z-10" title="${dismissTitle}" aria-label="${dismissTitle}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${dismissIconSvg}</svg>
                        </button>`;
                    const cardContent = `
                        <div class="p-4 flex gap-3 items-start flex-1 flex-col min-h-0">
                            <span class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center ${systemCardIconClasses}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">${systemCardIconSvg}</svg>
                            </span>
                            <div class="min-w-0 flex-1 flex flex-col">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200">${escapeHtml(card.title)}</h3>
                                <p class="text-sm text-gray-500 dark:text-primary-240 mt-1 flex-1">${escapeHtml(card.message)}</p>
                                ${systemCardLink ? `<span class="inline-flex items-center gap-1.5 mt-3 text-sm font-medium text-primary-250 dark:text-primary-280">${escapeHtml(card.action)}<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>` : ''}
                            </div>
                        </div>
                    `;
                    if (systemCardLink) {
                        return `<div class="${cardClasses}" data-system-card-href="${escapeHtml(systemCardLink)}">${dismissBtn}<a href="${escapeHtml(systemCardLink)}" class="h-full flex flex-col block min-h-0" title="${escapeHtml(card.action)}">${cardContent}</a></div>`;
                    }
                    return `<div class="${cardClasses}">${dismissBtn}${cardContent}</div>`;
                }
                
                if (isDbCardWithImg) {
                    return `
                    <div class="h-full rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-sm overflow-hidden relative">
                        <button onclick="dismissCard('${escapeHtml(card.id)}')" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 bg-white/80 dark:bg-primary-100/80 rounded-lg p-1" title="Verwerfen">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <div class="flex flex-col">
                            <div class="w-full flex items-center justify-center p-4 min-h-[180px]">
                                ${mediaHtml}
                            </div>
                            <div class="p-5 text-center flex flex-col gap-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">${escapeHtml(card.title)}</h3>
                                <p class="text-gray-700 dark:text-primary-210 text-sm leading-relaxed">${escapeHtml(card.message)}</p>
                                ${card.link && card.link !== '#' ? `<a href="${escapeHtml(card.link)}" class="inline-flex items-center justify-center rounded-lg ${buttonClass} px-5 py-2.5 text-sm font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-primary-100 sm:w-auto">${escapeHtml(card.action)}</a>` : ''}
                            </div>
                        </div>
                    </div>
                    `;
                }
                
                if (isWartungZahlung && card.company_id) {
                    const cid = card.company_id;
                    const cname = escapeHtml(card.company_name || '');
                    const mahnungAm = card.mahnung_gesendet_am || '';
                    let mahnungText = '';
                    if (mahnungAm) {
                        const d = mahnungAm.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{1,2}):(\d{2})/);
                        mahnungText = d ? `${d[3]}.${d[2]}.${d[1]}, ${d[4]}:${d[5]} Uhr` : mahnungAm;
                    }
                    const mahnungInfoVisible = mahnungText ? '' : ' hidden';
                    const mahnungInfoContent = mahnungText ? escapeHtml('Mahn-E-Mail wurde am ' + mahnungText + ' gesendet') : '';
                    return `
                    <div class="h-full rounded-xl border border-amber-200 dark:border-amber-800 bg-white dark:bg-primary-100 shadow-sm overflow-hidden relative" data-wartung-company-id="${cid}">
                        <div class="bg-amber-50 dark:bg-amber-900/20 px-4 py-3 border-b border-amber-200 dark:border-amber-800">
                            <div class="flex items-center justify-center gap-2">
                                <span class="flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                </span>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Wartungsvertrag Erinnerung</h3>
                            </div>
                        </div>
                        <div class="p-5">
                            <p class="text-center text-gray-700 dark:text-primary-210 font-medium mb-1">${escapeHtml(card.message)}</p>
                            <p class="text-center text-sm text-gray-500 dark:text-primary-240 mb-4">Bitte bestätigen Sie den Zahlungseingang oder wählen Sie eine Aktion.</p>
                            <div id="wartung-info-${cid}" class="wartung-card-info mb-4 rounded-lg px-3 py-2.5 text-sm text-green-800 dark:text-green-200 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 flex items-center justify-center gap-2${mahnungInfoVisible}" role="status">${mahnungText ? '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' : ''}${mahnungInfoContent}</div>
                            <div class="space-y-3">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <button type="button" onclick="wartungZahlungResponse(${cid}, 'ja')" class="inline-flex items-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 px-4 py-2.5 text-sm font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-primary-100">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Ja, gezahlt
                                    </button>
                                    <button type="button" onclick="wartungZahlungShowNein(${cid})" class="inline-flex items-center gap-2 rounded-lg bg-red-600 hover:bg-red-700 px-4 py-2.5 text-sm font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-primary-100">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Nein
                                    </button>
                                </div>
                                <div class="wartung-nein-options hidden rounded-lg bg-gray-50 dark:bg-primary-140 p-3 border border-gray-200 dark:border-primary-120">
                                    <p class="text-xs font-medium text-gray-600 dark:text-primary-210 mb-2">Was soll passieren?</p>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button type="button" onclick="wartungZahlungResponse(${cid}, 'nein_inaktiv')" class="rounded-lg bg-amber-600 hover:bg-amber-700 px-3 py-2 text-white text-xs font-medium">Inaktiv setzen</button>
                                        <button type="button" onclick="wartungZahlungResponse(${cid}, 'nein_5tage')" class="rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-2 text-white text-xs font-medium">In 5 Tagen nochmal fragen</button>
                                        <button type="button" onclick="wartungZahlungResponse(${cid}, 'nein_gesperrt')" class="rounded-lg bg-red-700 hover:bg-red-800 px-3 py-2 text-white text-xs font-medium">Sofort sperren</button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap justify-center gap-2 pt-2">
                                    <button type="button" onclick="wartungZahlungMahnung(${cid})" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-primary-120 bg-white dark:bg-primary-200 px-3 py-2 text-sm font-medium text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        Mahn-E-Mail senden
                                    </button>
                                </div>
                                <p class="text-center pt-1">
                                    <button type="button" onclick="wartungZahlungResponse(${cid}, 'ueberspringen')" class="text-xs text-gray-500 dark:text-primary-240 hover:text-gray-700 dark:hover:text-primary-200 underline focus:outline-none">Zahlung überspringen</button>
                                </p>
                            </div>
                        </div>
                    </div>
                    `;
                }
                
                const cardBorder = 'border-gray-200 dark:border-primary-120';
                const headerContent = hasImg && !isSystemCard
                    ? `<img src="${escapeHtml((imgSrc || imgDarkSrc || ''))}" alt="" class="w-10 h-10 rounded-full object-cover border-2 border-white dark:border-primary-100 shadow">`
                    : `<span class="flex items-center justify-center w-10 h-10 rounded-full ${headerIconBg}"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">${systemCardIconSvg}</svg></span>`;
                return `
                    <div class="h-full rounded-xl border ${cardBorder} bg-white dark:bg-primary-100 shadow-sm overflow-hidden relative">
                        <button onclick="dismissCard('${escapeHtml(card.id)}')" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Verwerfen">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <div class="${headerBg} px-4 py-3 border-b border-gray-200 dark:border-primary-230">
                            <div class="flex items-center justify-center gap-2">
                                ${headerContent}
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200">${escapeHtml(card.title)}</h3>
                            </div>
                        </div>
                        <div class="p-5 text-center">
                            <p class="text-gray-700 dark:text-primary-210 mb-4">${escapeHtml(card.message)}</p>
                            ${card.link && card.link !== '#' ? `<a href="${escapeHtml(card.link)}" class="inline-flex items-center justify-center rounded-lg ${buttonClass} px-5 py-2.5 text-sm font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-primary-100 sm:w-auto">${escapeHtml(card.action)}</a>` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            cardsSection.style.display = 'none';
        }
    } catch (error) {
        console.error('Fehler beim Laden der Cards:', error);
        const cardsSection = document.getElementById('cards-section');
        if (cardsSection) {
            cardsSection.style.display = 'none';
        }
    }
}

async function dismissCard(cardId) {
    try {
        const formData = new FormData();
        formData.append('card_id', cardId);
        
        const response = await fetch(dashboardApiUrl + '?action=dismiss_card', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            await loadCards();
        }
    } catch (error) {
        console.error('Fehler beim Verwerfen der Card:', error);
    }
}

async function wartungZahlungResponse(companyId, response) {
    try {
        const res = await fetch(dashboardApiUrl + '?action=wartung_zahlung_response', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ company_id: companyId, response: response })
        });
        const data = await res.json();
        if (data.success) {
            if (typeof showToast === 'function') showToast('Antwort gespeichert', 'success');
            await loadCards();
        } else {
            if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Speichern', 'error');
            else alert(data.error || 'Fehler beim Speichern');
        }
    } catch (error) {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') showToast('Fehler beim Senden der Antwort', 'error');
        else alert('Fehler beim Senden der Antwort');
    }
}

function wartungZahlungShowNein(companyId) {
    const card = document.querySelector('[data-wartung-company-id="' + companyId + '"]');
    if (card) {
        const opts = card.querySelector('.wartung-nein-options');
        if (opts) opts.classList.remove('hidden');
    }
}

function wartungZahlungShowCardInfo(companyId, message, isError) {
    const el = document.getElementById('wartung-info-' + companyId);
    if (!el) return;
    el.textContent = message;
    el.classList.remove('hidden');
    el.classList.remove('text-green-800', 'dark:text-green-200', 'bg-green-50', 'dark:bg-green-900/30', 'border-green-200', 'dark:border-green-800', 'text-red-800', 'dark:text-red-200', 'bg-red-50', 'dark:bg-red-900/30', 'border-red-200', 'dark:border-red-800');
    if (isError) {
        el.classList.add('text-red-800', 'dark:text-red-200', 'bg-red-50', 'dark:bg-red-900/30', 'border-red-200', 'dark:border-red-800');
    } else {
        el.classList.add('text-green-800', 'dark:text-green-200', 'bg-green-50', 'dark:bg-green-900/30', 'border-green-200', 'dark:border-green-800');
    }
    el.setAttribute('role', 'status');
}

async function wartungZahlungMahnung(companyId) {
    try {
        const res = await fetch(dashboardApiUrl + '?action=wartung_zahlung_mahnung', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ company_id: companyId })
        });
        const data = await res.json();
        if (data.success) {
            const now = new Date();
            const dd = String(now.getDate()).padStart(2, '0');
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const yy = now.getFullYear();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const persistentMsg = 'Mahn-E-Mail wurde am ' + dd + '.' + mm + '.' + yy + ', ' + h + ':' + m + ' Uhr gesendet';
            wartungZahlungShowCardInfo(companyId, '✓ ' + persistentMsg, false);
            if (typeof showToast === 'function') {
                showToast(data.message || persistentMsg, 'success', 5000);
            } else {
                alert(data.message || persistentMsg);
            }
        } else {
            const errMsg = data.error || 'E-Mail konnte nicht gesendet werden.';
            wartungZahlungShowCardInfo(companyId, '✗ ' + errMsg, true);
            if (typeof showToast === 'function') showToast(errMsg, 'error');
            else alert(errMsg);
        }
    } catch (error) {
        console.error('Fehler:', error);
        wartungZahlungShowCardInfo(companyId, '✗ Fehler beim Senden der Mahn-E-Mail.', true);
        if (typeof showToast === 'function') showToast('Fehler beim Senden der Mahn-E-Mail', 'error');
        else alert('Fehler beim Senden der Mahn-E-Mail');
    }
}

// Quicklinks laden
function loadQuicklinks() {
    const container = document.getElementById('quicklinks-container');
    const links = [];
    
    // Ticket erstellen - immer erste Position
    links.push({
        title: 'Ticket',
        icon: 'plus',
        url: '<?php echo BASE_URL; ?>tickets/create.php'
    });
    
    
    <?php if ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin'): ?>
    links.push({
        title: 'Gerät hinzufügen',
        icon: 'device',
        url: '<?php echo BASE_URL; ?>devices/create.php'
    });
    <?php endif; ?>
    
    links.push({
        title: 'Benachrichtigungen',
        icon: 'notification',
        url: '<?php echo BASE_URL; ?>notifications/'
    });
    
    <?php if ($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User'): ?>
    links.push({
        title: 'Fernzugriff download',
        icon: 'download',
        url: '<?php echo BASE_URL; ?>remote-access/download.php'
    });
    <?php endif; ?>
    
    <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
    links.push({
        title: 'Firmen',
        icon: 'company',
        url: '<?php echo BASE_URL; ?>companies/'
    });
    links.push({
        title: 'Kunden',
        icon: 'customer',
        url: '<?php echo BASE_URL; ?>customers/'
    });
    <?php endif; ?>
    
    <?php if ($userRole === 'Admin'): ?>
    links.push({
        title: 'Benutzer',
        icon: 'user',
        url: '<?php echo BASE_URL; ?>admin/users.php'
    });
    <?php endif; ?>
    
    const iconMap = {
        'plus': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
        'task': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
        'time': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'device': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>',
        'customer': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
        'notification': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
        'company': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>',
        'user': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
        'download': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>'
    };
    
    container.innerHTML = links.map(link => `
        <a href="${escapeHtml(link.url)}" class="flex flex-col items-center justify-center p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-primary-300 dark:border-primary-120 dark:hover:bg-primary-760 dark:hover:border-primary-360 transition-all group">
            <div class="text-gray-400 group-hover:text-primary-600 dark:text-gray-500 dark:group-hover:text-primary-400 mb-2">
                ${iconMap[link.icon] || iconMap['plus']}
            </div>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 text-center">${escapeHtml(link.title)}</span>
        </a>
    `).join('');
}

// Dropdown auf „Benutzerdefiniert“ setzen und Datumszeile anzeigen (bei manueller Datumsänderung)
function setQuickFilterSelectCustom() {
    const sel = document.getElementById('stats-quickfilter-select');
    const row = document.getElementById('stats-custom-date-row');
    if (sel) sel.value = 'custom';
    if (row) row.style.display = 'flex';
}

// Alle Quickfilter-Buttons auf „inaktiv“ setzen (Legacy, falls noch Referenzen existieren)
function resetQuickFilterButtons() {
    setQuickFilterSelectCustom();
}

// Quickfilter setzen (Zeitraum-Dropdown + Datumsfelder)
function setQuickFilter(period) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let startDate, endDate;
    
    const quickfilterSelect = document.getElementById('stats-quickfilter-select');
    const customDateRow = document.getElementById('stats-custom-date-row');
    if (quickfilterSelect) quickfilterSelect.value = period;
    if (customDateRow) customDateRow.style.display = 'none';
    
    if (period === 'today') {
        // Heute
        startDate = new Date(today);
        endDate = new Date(today);
    } else if (period === 'yesterday') {
        // Gestern
        startDate = new Date(today);
        startDate.setDate(today.getDate() - 1);
        endDate = new Date(startDate);
    } else if (period === 'week') {
        // Letzte Woche
        endDate = new Date(today);
        startDate = new Date(today);
        startDate.setDate(today.getDate() - 7);
    } else if (period === 'month') {
        // Letzte 30 Tage
        endDate = new Date(today);
        startDate = new Date(today);
        startDate.setDate(today.getDate() - 30);
    } else if (period === 'year') {
        // Letztes Jahr
        endDate = new Date(today);
        startDate = new Date(today);
        startDate.setFullYear(today.getFullYear() - 1);
    } else if (period === '90days') {
        // Letzte 90 Tage
        endDate = new Date(today);
        startDate = new Date(today);
        startDate.setDate(today.getDate() - 90);
    } else if (period === 'all') {
        // Insgesamt - sehr frühes Datum verwenden
        endDate = new Date(today);
        startDate = new Date('2000-01-01');
    }
    
    // Datum in lokaler Zeit formatieren (nicht toISOString = UTC, sonst wird „Heute“ zu „Gestern“)
    function toLocalDateString(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }
    document.getElementById('stats-start-date').value = toLocalDateString(startDate);
    document.getElementById('stats-end-date').value = toLocalDateString(endDate);
    
    loadStatistics();
}

// Vergleich umschalten
function toggleCompare() {
    const checkbox = document.getElementById('compare-toggle');
    const compareBtn = document.getElementById('compare-btn');
    
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
    }
    
    if (compareBtn) {
        const activeClasses = ['bg-primary-820', 'text-white', 'border-primary-700', 'dark:text-primary-840', 'dark:bg-primary-800', 'dark:border-primary-820'];
        const inactiveClasses = ['bg-gray-50', 'text-gray-900', 'hover:bg-gray-100', 'dark:bg-primary-700', 'dark:border-primary-720', 'dark:text-primary-210', 'dark:hover:bg-primary-760'];
        if (checkbox && checkbox.checked) {
            compareBtn.classList.remove(...inactiveClasses);
            compareBtn.classList.add(...activeClasses);
        } else {
            compareBtn.classList.remove(...activeClasses);
            compareBtn.classList.add(...inactiveClasses);
        }
    }
    
    loadStatistics();
}

// Vergleich-Button-Status beim Laden aktualisieren
function updateCompareButton() {
    const checkbox = document.getElementById('compare-toggle');
    const compareBtn = document.getElementById('compare-btn');
    
    if (checkbox && compareBtn) {
        const activeClasses = ['bg-primary-820', 'text-white', 'border-primary-700', 'dark:text-primary-840', 'dark:bg-primary-800', 'dark:border-primary-820'];
        const inactiveClasses = ['bg-gray-50', 'text-gray-900', 'hover:bg-gray-100', 'dark:bg-primary-700', 'dark:border-primary-720', 'dark:text-primary-210', 'dark:hover:bg-primary-760'];
        if (checkbox.checked) {
            compareBtn.classList.remove(...inactiveClasses);
            compareBtn.classList.add(...activeClasses);
        } else {
            compareBtn.classList.remove(...activeClasses);
            compareBtn.classList.add(...inactiveClasses);
        }
    }
}

// Statistiken laden
async function loadStatistics() {
    const startDate = document.getElementById('stats-start-date').value;
    const endDate = document.getElementById('stats-end-date').value;
    const checkbox = document.getElementById('compare-toggle');
    const compareEnabled = checkbox ? checkbox.checked : false;
    
    updateCompareButton();
    
    try {
        let url = dashboardApiUrl + '?action=get_statistics&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
        if (compareEnabled) {
            url += '&compare=1';
        }
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            console.error('Statistiken API Fehler:', data.error);
            throw new Error(data.error || 'Unbekannter Fehler');
        }
        
        if (data.statistics) {
            const stats = data.statistics;
            
            // Keine Daten im gewählten Zeitraum? (keine Tickets oder alle Werte 0)
            const hasNoData = !stats.tickets || stats.tickets.length === 0 ||
                stats.tickets.every(t => (parseInt(t.open_count)||0) === 0 && (parseInt(t.closed_count)||0) === 0);
            
            if (hasNoData) {
                if (ticketsChart) { ticketsChart.destroy(); ticketsChart = null; }
                if (companiesChart) { companiesChart.destroy(); companiesChart = null; }
                if (statusChart) { statusChart.destroy(); statusChart = null; }
                if (avgClosingTimeChart) { avgClosingTimeChart.destroy(); avgClosingTimeChart = null; }
                const avgClosingWrap = document.getElementById('avgClosingTimeChartWrapper');
                if (avgClosingWrap) avgClosingWrap.classList.add('hidden');
                const totalEl = document.getElementById('stats-total-number');
                if (totalEl) totalEl.textContent = '0';
                const closedEl = document.getElementById('stats-closed-number');
                if (closedEl) closedEl.textContent = '0';
                const avgEl = document.getElementById('stats-avg-number');
                if (avgEl) avgEl.textContent = '0,0';
                document.querySelectorAll('.stats-no-data-info').forEach(el => el.classList.remove('hidden'));
                const devicesListEl = document.getElementById('stats-devices-list');
                const customersListEl = document.getElementById('stats-customers-list');
                if (devicesListEl) { devicesListEl.innerHTML = ''; devicesListEl.classList.add('hidden'); }
                if (customersListEl) { customersListEl.innerHTML = ''; customersListEl.classList.add('hidden'); }
                const topClosersEl = document.getElementById('top-closers');
                if (topClosersEl) topClosersEl.innerHTML = '<div class="text-center py-4 text-gray-500 dark:text-primary-220 text-sm">Keine Daten im gewählten Zeitraum.</div>';
            } else {
                document.querySelectorAll('.stats-no-data-info').forEach(el => el.classList.add('hidden'));
            
            // Vergleichsinfo anzeigen
            const compareInfo = document.getElementById('compare-info');
            if (compareEnabled && data.compare_period) {
                const start = new Date(data.compare_period.start).toLocaleDateString('de-DE');
                const end = new Date(data.compare_period.end).toLocaleDateString('de-DE');
                if (compareInfo) {
                    compareInfo.textContent = `(Vergleich: ${start} - ${end})`;
                    compareInfo.style.display = 'inline';
                }
            } else {
                if (compareInfo) {
                    compareInfo.style.display = 'none';
                }
            }
            
            // Total-Zahl für Card-Header (nur erstellte Tickets im Zeitraum, nicht geschlossene)
            const totalEl = document.getElementById('stats-total-number');
            if (totalEl && stats.tickets && stats.tickets.length > 0) {
                const total = stats.tickets.reduce((sum, t) => sum + (parseInt(t.open_count) || 0), 0);
                totalEl.textContent = total.toLocaleString('de-DE');
            } else if (totalEl && (!stats.tickets || stats.tickets.length === 0)) {
                totalEl.textContent = '0';
            }
            const closedEl = document.getElementById('stats-closed-number');
            const avgEl = document.getElementById('stats-avg-number');
            if (stats.tickets && stats.tickets.length > 0) {
                const totalCreated = stats.tickets.reduce((sum, t) => sum + (parseInt(t.open_count) || 0), 0);
                const totalClosed = stats.tickets.reduce((sum, t) => sum + (parseInt(t.closed_count) || 0), 0);
                const avgPerBucket = totalCreated / Math.max(stats.tickets.length, 1);
                if (closedEl) closedEl.textContent = totalClosed.toLocaleString('de-DE');
                if (avgEl) avgEl.textContent = avgPerBucket.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
            } else {
                if (closedEl) closedEl.textContent = '0';
                if (avgEl) avgEl.textContent = '0,0';
            }
            
            // Tickets-Diagramm (offene und geschlossene, mit Vergleich) – Farben aus Branding
            if (stats.tickets && stats.tickets.length > 0) {
                const ctx = document.getElementById('ticketsChart');
                if (ctx) {
                    if (ticketsChart) ticketsChart.destroy();
                    const isDark = document.documentElement.classList.contains('dark');
                    const chartColors = (typeof dashboardChartColors !== 'undefined' && dashboardChartColors) ? (isDark ? dashboardChartColors.dark : dashboardChartColors.light) : { open: '#3B82F6', closed: '#22C55E', compareOpen: '#38BDF8', compareClosed: '#F59E0B' };
                    const openRgb = hexToRgb(chartColors.open);
                    const closedRgb = hexToRgb(chartColors.closed);
                    const compareOpenRgb = hexToRgb(chartColors.compareOpen || '#38BDF8');
                    const compareClosedRgb = hexToRgb(chartColors.compareClosed || '#F59E0B');
                    const openRgba = hexToRgba(chartColors.open, 0.1);
                    const closedRgba = hexToRgba(chartColors.closed, 0.1);
                    const datasets = [
                        {
                            label: 'Erstellte',
                            data: stats.tickets.map(t => parseInt(t.open_count) || 0),
                            borderColor: openRgb,
                            backgroundColor: openRgba,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointStyle: 'circle'
                        },
                        {
                            label: 'Geschlossene',
                            data: stats.tickets.map(t => parseInt(t.closed_count) || 0),
                            borderColor: closedRgb,
                            backgroundColor: closedRgba,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointStyle: 'circle'
                        }
                    ];
                    
                    // Vergleichsdaten hinzufügen wenn aktiviert (Branding-Farben, gestrichelt)
                    if (compareEnabled && stats.compare && stats.compare.tickets && stats.compare.tickets.length > 0) {
                        const compareTickets = stats.compare.tickets;
                        const currentTickets = stats.tickets;
                        datasets.push(
                            {
                                label: 'Erstellte (Vergleich)',
                                data: currentTickets.map((t, index) => index < compareTickets.length ? (parseInt(compareTickets[index].open_count) || 0) : null),
                                borderColor: compareOpenRgb,
                                backgroundColor: hexToRgba(chartColors.compareOpen || '#38BDF8', 0.05),
                                tension: 0.4,
                                borderWidth: 1,
                                borderDash: [5, 5],
                                pointRadius: 0,
                                pointStyle: 'circle'
                            },
                            {
                                label: 'Geschlossene (Vergleich)',
                                data: currentTickets.map((t, index) => index < compareTickets.length ? (parseInt(compareTickets[index].closed_count) || 0) : null),
                                borderColor: compareClosedRgb,
                                backgroundColor: hexToRgba(chartColors.compareClosed || '#F59E0B', 0.05),
                                tension: 0.4,
                                borderWidth: 1,
                                borderDash: [5, 5],
                                pointRadius: 0,
                                pointStyle: 'circle'
                            }
                        );
                    }
                    
                    const isHourly = stats.tickets_hourly === true;
                    const isMobileStatsView = window.matchMedia('(max-width: 1023px)').matches;
                    const chartLabels = isHourly
                        ? stats.tickets.map(t => {
                            const d = new Date(t.date);
                            return d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                          })
                        : stats.tickets.map(t => new Date(t.date).toLocaleDateString('de-DE'));
                    const cardEl = ctx.closest('.stats-card');
                    const cardBorderColor = cardEl ? getComputedStyle(cardEl).borderColor : (document.documentElement.classList.contains('dark') ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)');
                    ticketsChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartLabels,
                            datasets: datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    align: 'start',
                                    labels: {
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        boxWidth: 8,
                                        boxHeight: 8,
                                        color: chartColors.legendText || (document.documentElement.classList.contains('dark') ? '#94A3B8' : '#6B7280')
                                    }
                                },
                                tooltip: {
                                    enabled: !isMobileStatsView,
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        title: function(items) {
                                            const item = items[0];
                                            if (!item || !item.label) return '';
                                            if (isHourly) return item.label + ' Uhr';
                                            return item.label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { display: false }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        display: true,
                                        color: cardBorderColor
                                    },
                                    ticks: {
                                        color: chartColors.legendText || (document.documentElement.classList.contains('dark') ? '#94A3B8' : '#6B7280')
                                    }
                                }
                            }
                        }
                    });
                }
            }
            
            // Listen „Häufigste Geräte“ und „Häufigste Kunden“ (neben Status-Verteilung)
            const devicesListEl = document.getElementById('stats-devices-list');
            const customersListEl = document.getElementById('stats-customers-list');
            const devicesTableWrap = document.getElementById('stats-devices-table-wrap');
            const customersTableWrap = document.getElementById('stats-customers-table-wrap');
            const devicesNoDataEl = document.querySelector('.stats-devices-no-data');
            const customersNoDataEl = document.querySelector('.stats-customers-no-data');
            const deviceIconSvg = '<svg class="w-4 h-4 flex-shrink-0 text-gray-500 dark:text-primary-220" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
            const customerIconSvg = '<svg class="w-4 h-4 flex-shrink-0 text-gray-500 dark:text-primary-220" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>';
            if (devicesListEl) {
                if (stats.devices && stats.devices.length > 0) {
                    if (devicesNoDataEl) devicesNoDataEl.classList.add('hidden');
                    const totalDev = stats.devices.slice(0, 10).reduce(function(s, d) { return s + (parseInt(d.count) || 0); }, 0);
                    const baseUrl = '<?php echo addslashes(BASE_URL); ?>';
                    devicesListEl.innerHTML = stats.devices.slice(0, 10).map(function(d) {
                        const count = parseInt(d.count) || 0;
                        const pct = totalDev > 0 ? (100 * count / totalDev).toFixed(1).replace('.', ',') : '0';
                        const deviceId = (d.id != null && d.id !== '') ? parseInt(d.id, 10) : null;
                        const link = deviceId ? '<a href="' + baseUrl + 'devices/detail.php?id=' + deviceId + '" class="flex items-center gap-2 min-w-0 text-gray-900 dark:text-primary-200 hover:text-primary-600 dark:hover:text-primary-400">' + deviceIconSvg + '<span class="truncate">' + escapeHtml(d.name || '') + '</span></a>' : '<span class="flex items-center gap-2 min-w-0">' + deviceIconSvg + '<span class="text-gray-900 dark:text-primary-200 truncate">' + escapeHtml(d.name || '') + '</span></span>';
                        return '<tr class="hover:bg-gray-100 dark:hover:bg-primary-140"><td class="py-2 pr-2">' + link + '</td><td class="py-2 pl-2 text-right"><span class="flex items-center justify-end gap-1.5 flex-shrink-0 tabular-nums text-gray-700 dark:text-primary-200"><span>' + count.toLocaleString('de-DE') + '</span><span class="text-gray-400 dark:text-primary-220">|</span><span>' + pct + '%</span></span></td></tr>';
                    }).join('');
                    if (devicesTableWrap) devicesTableWrap.classList.remove('hidden');
                } else {
                    if (devicesNoDataEl) devicesNoDataEl.classList.remove('hidden');
                    devicesListEl.innerHTML = '';
                    if (devicesTableWrap) devicesTableWrap.classList.add('hidden');
                }
            }
            if (customersListEl) {
                if (stats.customers && stats.customers.length > 0) {
                    if (customersNoDataEl) customersNoDataEl.classList.add('hidden');
                    const totalCust = stats.customers.slice(0, 10).reduce(function(s, c) { return s + (parseInt(c.count) || 0); }, 0);
                    const baseUrl = '<?php echo addslashes(BASE_URL); ?>';
                    customersListEl.innerHTML = stats.customers.slice(0, 10).map(function(c) {
                        const count = parseInt(c.count) || 0;
                        const pct = totalCust > 0 ? (100 * count / totalCust).toFixed(1).replace('.', ',') : '0';
                        const customerId = (c.id != null && c.id !== '') ? parseInt(c.id, 10) : null;
                        const link = customerId ? '<a href="' + baseUrl + 'customers/detail.php?id=' + customerId + '" class="flex items-center gap-2 min-w-0 text-gray-900 dark:text-primary-200 hover:text-primary-600 dark:hover:text-primary-400">' + customerIconSvg + '<span class="truncate">' + escapeHtml(c.name || '') + '</span></a>' : '<span class="flex items-center gap-2 min-w-0">' + customerIconSvg + '<span class="text-gray-900 dark:text-primary-200 truncate">' + escapeHtml(c.name || '') + '</span></span>';
                        return '<tr class="hover:bg-gray-100 dark:hover:bg-primary-140"><td class="py-2 pr-2">' + link + '</td><td class="py-2 pl-2 text-right"><span class="flex items-center justify-end gap-1.5 flex-shrink-0 tabular-nums text-gray-700 dark:text-primary-200"><span>' + count.toLocaleString('de-DE') + '</span><span class="text-gray-400 dark:text-primary-220">|</span><span>' + pct + '%</span></span></td></tr>';
                    }).join('');
                    if (customersTableWrap) customersTableWrap.classList.remove('hidden');
                } else {
                    if (customersNoDataEl) customersNoDataEl.classList.remove('hidden');
                    customersListEl.innerHTML = '';
                    if (customersTableWrap) customersTableWrap.classList.add('hidden');
                }
            }
            
            // Firmen-Diagramm + Legende wie Status-Verteilung (Kreis, Name, Anzahl | %)
            <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
            const companiesChartWrapper = document.querySelector('.companies-chart-wrapper');
            const companiesChartLegendWrap = document.getElementById('companies-chart-legend-wrap');
            const companiesChartLegendEl = document.getElementById('companies-chart-legend');
            const companiesLegendMoreBtn = document.getElementById('companies-legend-more-btn');
            const companiesFiltered = stats.companies && stats.companies.length > 0
                ? stats.companies.filter(c => (parseInt(c.count) || 0) > 0)
                : [];
            if (companiesFiltered.length > 0) {
                const ctx = document.getElementById('companiesChart');
                if (ctx) {
                    if (companiesChart) companiesChart.destroy();
                    const isDark = document.documentElement.classList.contains('dark');
                    const chartColors = (typeof dashboardChartColors !== 'undefined' && dashboardChartColors) ? (isDark ? dashboardChartColors.dark : dashboardChartColors.light) : {};
                    const companyPalette = [
                        'rgba(59, 130, 246, 0.85)',   // blau
                        'rgba(16, 185, 129, 0.85)',   // grün
                        'rgba(245, 158, 11, 0.85)',   // amber
                        'rgba(168, 85, 247, 0.85)',   // lila
                        'rgba(249, 115, 22, 0.85)',   // orange
                        'rgba(6, 182, 212, 0.85)',    // cyan
                        'rgba(236, 72, 153, 0.85)',   // pink
                        'rgba(34, 197, 94, 0.85)',    // emerald
                        'rgba(139, 92, 246, 0.85)',   // violett
                        'rgba(234, 179, 8, 0.85)'     // gelb
                    ];
                    const companiesSlice = companiesFiltered.slice(0, 10);
                    const barColors = companiesSlice.map((_, i) => companyPalette[i % companyPalette.length].replace('0.85', '0.6'));
                    const legendColors = companiesSlice.map((_, i) => companyPalette[i % companyPalette.length]);
                    companiesChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: companiesSlice.map(() => ''),
                            datasets: [{
                                label: 'Anzahl',
                                data: companiesSlice.map(c => parseInt(c.count) || 0),
                                backgroundColor: barColors
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: function(items) {
                                            const i = items[0]?.dataIndex;
                                            if (i != null && companiesSlice[i]) return companiesSlice[i].name || '';
                                            return '';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    grid: { display: false },
                                    ticks: {
                                        display: true,
                                        color: chartColors.legendText || (isDark ? '#94A3B8' : '#6B7280')
                                    }
                                },
                                y: {
                                    display: false,
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                    if (companiesChartWrapper) companiesChartWrapper.classList.remove('hidden');
                    if (companiesChartLegendEl && companiesChartLegendWrap) {
                        const totalCompanies = companiesSlice.reduce((sum, c) => sum + (parseInt(c.count) || 0), 0);
                        const baseUrl = '<?php echo addslashes(BASE_URL); ?>';
                        const TOP_N = 3;
                        const makeLegendItem = (c, i) => {
                            const count = parseInt(c.count) || 0;
                            const pct = totalCompanies > 0 ? ((100 * count / totalCompanies).toFixed(1).replace('.', ',')) : '0';
                            const color = legendColors[i];
                            const companyId = (c.id != null && c.id !== '') ? parseInt(c.id, 10) : null;
                            const namePart = companyId
                                ? '<a href="' + baseUrl + 'companies/detail.php?id=' + companyId + '" class="flex items-center gap-2 min-w-0 text-gray-900 dark:text-primary-200 hover:text-primary-600 dark:hover:text-primary-400">' +
                                    '<span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:' + color + '"></span>' +
                                    '<span class="truncate">' + escapeHtml(c.name || '') + '</span></a>'
                                : '<span class="flex items-center gap-2 min-w-0">' +
                                    '<span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:' + color + '"></span>' +
                                    '<span class="text-gray-500 dark:text-primary-220 truncate">' + escapeHtml(c.name || '') + '</span></span>';
                            return '<li class="flex items-center justify-between gap-2 py-1 text-sm' + (i >= TOP_N ? ' companies-legend-more hidden' : '') + '">' +
                                namePart +
                                '<span class="flex items-center gap-1.5 flex-shrink-0 tabular-nums text-gray-700 dark:text-primary-200">' +
                                '<span>' + count.toLocaleString('de-DE') + '</span>' +
                                '<span class="text-gray-400 dark:text-primary-220">|</span>' +
                                '<span>' + pct + '%</span>' +
                                '</span>' +
                                '</li>';
                        };
                        companiesChartLegendEl.innerHTML = companiesSlice.map((c, i) => makeLegendItem(c, i)).join('');
                        if (companiesLegendMoreBtn) {
                            if (companiesSlice.length > TOP_N) {
                                companiesLegendMoreBtn.classList.remove('hidden');
                                companiesLegendMoreBtn.textContent = 'Mehr anzeigen';
                                companiesLegendMoreBtn.onclick = function() {
                                    const moreEls = companiesChartLegendEl.querySelectorAll('.companies-legend-more');
                                    const isExpanded = !moreEls[0]?.classList.contains('hidden');
                                    moreEls.forEach(el => el.classList.toggle('hidden', isExpanded));
                                    companiesLegendMoreBtn.textContent = isExpanded ? 'Mehr anzeigen' : 'Weniger anzeigen';
                                    if (isExpanded) {
                                        const card = companiesChartLegendWrap?.closest('.stats-card');
                                        const target = card || companiesChartLegendWrap;
                                        if (target) {
                                            requestAnimationFrame(function() {
                                                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                            });
                                        }
                                    }
                                };
                            } else {
                                companiesLegendMoreBtn.classList.add('hidden');
                                companiesLegendMoreBtn.onclick = null;
                            }
                        }
                        companiesChartLegendWrap.classList.remove('hidden');
                    }
                }
            } else {
                if (companiesChartWrapper) companiesChartWrapper.classList.add('hidden');
                if (companiesChartLegendWrap) companiesChartLegendWrap.classList.add('hidden');
                if (companiesChartLegendEl) companiesChartLegendEl.innerHTML = '';
                if (companiesLegendMoreBtn) { companiesLegendMoreBtn.classList.add('hidden'); companiesLegendMoreBtn.onclick = null; }
            }
            <?php endif; ?>
            
            // Status-Verteilung (Vorlage: Halb-Donut, Gesamtzahl in der Mitte, Legende mit Kreis + Name + Anzahl | %)
            <?php if ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin'): ?>
            const statusChartWrapper = document.querySelector('.status-chart-wrapper');
            const statusChartLegendEl = document.getElementById('status-chart-legend');
            if (stats.status_distribution && stats.status_distribution.length > 0) {
                const ctx = document.getElementById('statusChart');
                if (ctx) {
                    if (statusChart) statusChart.destroy();
                    const statusColors = {
                        'Neu': 'rgba(245, 158, 11, 0.85)',
                        'In Bearbeitung': 'rgba(59, 130, 246, 0.85)',
                        'Warteschlange': 'rgba(249, 115, 22, 0.85)',
                        'Geplant': 'rgba(16, 185, 129, 0.85)',
                        'Bestellung offen': 'rgba(168, 85, 247, 0.85)',
                        'Geschlossen': 'rgba(107, 114, 128, 0.85)'
                    };
                    const totalStatus = stats.status_distribution.reduce((sum, s) => sum + (parseInt(s.count) || 0), 0);
                    if (statusChartWrapper) statusChartWrapper.classList.remove('hidden');
                    statusChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: stats.status_distribution.map(s => s.status),
                            datasets: [{
                                label: 'Anzahl',
                                data: stats.status_distribution.map(s => s.count),
                                backgroundColor: stats.status_distribution.map(s => statusColors[s.status] || 'rgba(107, 114, 128, 0.85)'),
                                borderWidth: 0,
                                borderRadius: 8,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            circumference: 180,
                            rotation: 270,
                            spacing: 6,
                            cutout: '55%',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    enabled: true,
                                    padding: 12,
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    cornerRadius: 8,
                                    titleFont: { size: 14 },
                                    bodyFont: { size: 13 }
                                }
                            }
                        }
                    });
                    if (statusChartLegendEl) {
                        statusChartLegendEl.innerHTML = stats.status_distribution.map(s => {
                            const count = parseInt(s.count) || 0;
                            const pct = totalStatus > 0 ? ((100 * count / totalStatus).toFixed(1).replace('.', ',')) : '0';
                            const color = statusColors[s.status] || 'rgba(107, 114, 128, 0.85)';
                            return '<li class="flex items-center justify-between gap-2 py-1 text-sm">' +
                                '<span class="flex items-center gap-2 min-w-0">' +
                                '<span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:' + color + '"></span>' +
                                '<span class="text-gray-500 dark:text-primary-220 truncate">' + escapeHtml(s.status) + '</span>' +
                                '</span>' +
                                '<span class="flex items-center gap-1.5 flex-shrink-0 tabular-nums text-gray-700 dark:text-primary-200">' +
                                '<span>' + count.toLocaleString('de-DE') + '</span>' +
                                '<span class="text-gray-400 dark:text-primary-220">|</span>' +
                                '<span>' + pct + '%</span>' +
                                '</span>' +
                                '</li>';
                        }).join('');
                        statusChartLegendEl.classList.remove('hidden');
                    }
                }
            } else {
                if (statusChartWrapper) statusChartWrapper.classList.add('hidden');
                if (statusChartLegendEl) { statusChartLegendEl.innerHTML = ''; statusChartLegendEl.classList.add('hidden'); }
            }
            <?php endif; ?>
            
            // Zusätzliche Statistiken (nur für Admin/Techniker)
            <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
            // Schließzeit, Bearbeitungszeit & Reaktionszeit als KPI-Zeilen (ohne Chart)
            const avgClosingTimeWrapper = document.getElementById('avgClosingTimeChartWrapper');
            const timeStatClosing = document.getElementById('time-stat-closing');
            const timeStatBearbeitung = document.getElementById('time-stat-bearbeitung');
            const timeStatReaktion = document.getElementById('time-stat-reaktion');
            if (avgClosingTimeChart) { avgClosingTimeChart.destroy(); avgClosingTimeChart = null; }
            if (stats.avg_closing_time || stats.avg_bearbeitungszeit || stats.avg_reaktionszeit) {
                const closedCount = (stats.avg_closing_time && stats.avg_closing_time.closed_count) ? stats.avg_closing_time.closed_count : 0;
                const countWithTime = (stats.avg_bearbeitungszeit && stats.avg_bearbeitungszeit.count_with_time) ? stats.avg_bearbeitungszeit.count_with_time : 0;
                const countWithReaktion = (stats.avg_reaktionszeit && stats.avg_reaktionszeit.count_with_reaction) ? stats.avg_reaktionszeit.count_with_reaction : 0;
                const isDark = document.documentElement.classList.contains('dark');
                const chartColors = (typeof dashboardChartColors !== 'undefined' && dashboardChartColors) ? (isDark ? dashboardChartColors.dark : dashboardChartColors.light) : {};
                const colorClosing = chartColors.open || '#3B82F6';
                const colorBearbeitung = chartColors.closed || '#22C55E';
                const colorReaktion = chartColors.compareOpen || '#F59E0B';
                let hasAny = false;
                if (timeStatClosing) {
                    timeStatClosing.querySelector('.time-stat-dot').style.background = colorClosing;
                    if (stats.avg_closing_time && closedCount > 0) {
                        const h = stats.avg_closing_time.hours || 0;
                        const d = stats.avg_closing_time.days || 0;
                        let val, unit;
                        if (d >= 1) { val = (Math.round(d * 10) / 10).toLocaleString('de-DE'); unit = 'Tage'; }
                        else if (h >= 1) { val = (Math.round(h * 10) / 10).toLocaleString('de-DE'); unit = 'Stunden'; }
                        else { val = (Math.round(h * 60 * 10) / 10).toLocaleString('de-DE'); unit = 'Min'; }
                        timeStatClosing.querySelector('.time-stat-value').textContent = val;
                        timeStatClosing.querySelector('.time-stat-unit').textContent = unit;
                        timeStatClosing.classList.remove('hidden');
                        hasAny = true;
                    } else {
                        timeStatClosing.classList.add('hidden');
                    }
                }
                if (timeStatBearbeitung) {
                    timeStatBearbeitung.querySelector('.time-stat-dot').style.background = colorBearbeitung;
                    if (stats.avg_bearbeitungszeit && countWithTime > 0) {
                        const m = (Math.round((stats.avg_bearbeitungszeit.minutes || 0) * 10) / 10).toLocaleString('de-DE');
                        timeStatBearbeitung.querySelector('.time-stat-value').textContent = m;
                        timeStatBearbeitung.querySelector('.time-stat-unit').textContent = 'Min';
                        timeStatBearbeitung.classList.remove('hidden');
                        hasAny = true;
                    } else {
                        timeStatBearbeitung.classList.add('hidden');
                    }
                }
                if (timeStatReaktion) {
                    timeStatReaktion.querySelector('.time-stat-dot').style.background = colorReaktion;
                    if (stats.avg_reaktionszeit && countWithReaktion > 0) {
                        const rh = stats.avg_reaktionszeit.hours || 0;
                        const rd = stats.avg_reaktionszeit.days || 0;
                        let val, unit;
                        if (rd >= 1) { val = (Math.round(rd * 10) / 10).toLocaleString('de-DE'); unit = 'Tage'; }
                        else if (rh >= 1) { val = (Math.round(rh * 10) / 10).toLocaleString('de-DE'); unit = 'Stunden'; }
                        else { val = (Math.round(rh * 60 * 10) / 10).toLocaleString('de-DE'); unit = 'Min'; }
                        timeStatReaktion.querySelector('.time-stat-value').textContent = val;
                        timeStatReaktion.querySelector('.time-stat-unit').textContent = unit;
                        timeStatReaktion.classList.remove('hidden');
                        hasAny = true;
                    } else {
                        timeStatReaktion.classList.add('hidden');
                    }
                }
                if (avgClosingTimeWrapper) {
                    if (hasAny) {
                        avgClosingTimeWrapper.classList.remove('hidden');
                        const card = avgClosingTimeWrapper.closest('.stats-card');
                        if (card) {
                            const noDataEl = card.querySelector('.stats-no-data-info');
                            if (noDataEl) noDataEl.classList.add('hidden');
                        }
                    } else {
                        avgClosingTimeWrapper.classList.add('hidden');
                        const card = avgClosingTimeWrapper.closest('.stats-card');
                        if (card) {
                            const noDataEl = card.querySelector('.stats-no-data-info');
                            if (noDataEl) noDataEl.classList.remove('hidden');
                        }
                    }
                }
            } else {
                if (avgClosingTimeWrapper) avgClosingTimeWrapper.classList.add('hidden');
                [timeStatClosing, timeStatBearbeitung, timeStatReaktion].forEach(el => { if (el) el.classList.add('hidden'); });
                const card = document.getElementById('avgClosingTimeChartWrapper')?.closest('.stats-card');
                if (card) {
                    const noDataEl = card.querySelector('.stats-no-data-info');
                    if (noDataEl) noDataEl.classList.remove('hidden');
                }
            }
            
            // Top Ticket-Schließer (modernes Listen-Design wie Geräte/Kunden)
            const topClosersEl = document.getElementById('top-closers');
            if (topClosersEl) {
                if (stats.top_closers && stats.top_closers.length > 0) {
                    const personIconSvg = '<svg class="w-4 h-4 flex-shrink-0 text-gray-500 dark:text-primary-220" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';
                    const totalClosed = stats.top_closers.reduce(function(s, u) { return s + (parseInt(u.closed_count) || 0); }, 0);
                    topClosersEl.innerHTML = '<ul class="divide-y divide-gray-200 dark:divide-primary-230">' +
                        stats.top_closers.map((user) => {
                            const name = escapeHtml(((user.vorname || '') + ' ' + (user.nachname || '')).trim()) || 'Unbekannt';
                            const count = parseInt(user.closed_count) || 0;
                            const pct = totalClosed > 0 ? (100 * count / totalClosed).toFixed(1).replace('.', ',') : '0';
                            return '<li class="flex items-center justify-between py-2"><span class="flex items-center gap-2 min-w-0"><span class="flex-shrink-0">' + personIconSvg + '</span><span class="text-sm text-gray-900 dark:text-primary-200 truncate">' + name + '</span></span><span class="flex items-center gap-1.5 flex-shrink-0 text-sm tabular-nums text-gray-700 dark:text-primary-200"><span>' + count.toLocaleString('de-DE') + '</span><span class="text-gray-400 dark:text-primary-220">|</span><span>' + pct + '%</span></span></li>';
                        }).join('') +
                        '</ul>';
                    topClosersEl.classList.remove('text-center');
                } else {
                    topClosersEl.innerHTML = '<div class="text-center py-4 text-gray-500 dark:text-primary-220 text-sm">Keine Daten verfügbar</div>';
                    topClosersEl.classList.add('text-center');
                }
            }
            
            <?php endif; ?>
            }
        }
    } catch (error) {
        console.error('Fehler beim Laden der Statistiken:', error);
        // Fehlermeldung anzeigen
        const charts = ['ticketsChart', 'companiesChart', 'statusChart'];
        charts.forEach(chartId => {
            const ctx = document.getElementById(chartId);
            if (ctx && ctx.parentElement) {
                ctx.parentElement.innerHTML = '<div class="text-center py-4 text-red-500 dark:text-red-400 text-sm">Fehler beim Laden der Daten</div>';
            }
        });
    }
}

setupMobilePriorityStatsSwipe();
window.addEventListener('resize', setupMobilePriorityStatsSwipe);

// Benachrichtigungen laden
async function loadNotifications() {
    try {
        const response = await fetch(dashboardApiUrl + '?action=get_notifications');
        const data = await response.json();
        
        if (data.success && data.notifications && data.notifications.length > 0) {
            const container = document.getElementById('notifications-container');
            container.innerHTML = `
                <div class="space-y-3">
                    ${data.notifications.map(notif => `
                        <a href="<?php echo BASE_URL; ?>notifications/" class="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50 dark:border-primary-120 dark:hover:bg-primary-760 transition-all">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-primary-200">${escapeHtml(notif.titel || 'Benachrichtigung')}</p>
                                    ${notif.nachricht ? `<p class="mt-1 text-xs text-gray-500 dark:text-primary-220 line-clamp-2">${escapeHtml(notif.nachricht)}</p>` : ''}
                                    <p class="mt-1 text-xs text-gray-400 dark:text-primary-220">${formatDate(notif.erstellt_datum)}</p>
                                </div>
                                ${!notif.gelesen ? '<span class="ml-2 flex-shrink-0 w-2 h-2 bg-primary-600 rounded-full"></span>' : ''}
                            </div>
                        </a>
                    `).join('')}
                </div>
            `;
        } else {
            document.getElementById('notifications-container').innerHTML = '<div class="text-center py-4 text-gray-500 dark:text-primary-220 text-sm">Keine Benachrichtigungen</div>';
        }
    } catch (error) {
        console.error('Fehler beim Laden der Benachrichtigungen:', error);
    }
}

// Zeitstrahl laden
<?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
async function loadTimeline() {
    const tabsContainer = document.getElementById('timeline-tabs');
    const contentContainer = document.getElementById('timeline-tab-content');
    
    if (!tabsContainer || !contentContainer) {
        return;
    }
    
    try {
        const response = await fetch(dashboardApiUrl + '?action=get_timeline');
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Timeline API Fehler:', response.status, errorText);
            throw new Error('HTTP ' + response.status);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            console.error('Timeline API Fehler:', data.error);
            contentContainer.innerHTML = '<div class="text-center py-4 text-red-500 dark:text-red-400 text-sm">Fehler beim Laden: ' + escapeHtml(data.error || 'Unbekannter Fehler') + '</div>';
            return;
        }
        
        if (data.items && data.items.length > 0) {
            // Items nach Tagen gruppieren
            const itemsByDay = {};
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            data.items.forEach(item => {
                if (!item.date) return;
                const date = new Date(item.date);
                if (isNaN(date.getTime())) return;
                
                const dayStart = new Date(date);
                dayStart.setHours(0, 0, 0, 0);
                const dayKey = dayStart.toISOString().split('T')[0];
                
                if (!itemsByDay[dayKey]) {
                    itemsByDay[dayKey] = [];
                }
                itemsByDay[dayKey].push(item);
            });
            
            // Tage sortieren
            const sortedDays = Object.keys(itemsByDay).sort();
            
            if (sortedDays.length === 0) {
                contentContainer.innerHTML = '<div class="text-center py-4 text-gray-500 dark:text-primary-220 text-sm">Keine fälligen oder geplanten Termine</div>';
                return;
            }
            
            // Tabs generieren
            tabsContainer.innerHTML = sortedDays.map((dayKey, index) => {
                const day = new Date(dayKey);
                const dayName = day.toLocaleDateString('de-DE', { weekday: 'long' });
                const dayDate = day.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
                
                let label = '';
                const dayDiff = Math.floor((day - today) / (1000 * 60 * 60 * 24));
                if (dayDiff === 0) {
                    label = 'Heute';
                } else if (dayDiff === 1) {
                    label = 'Morgen';
                } else if (dayDiff === -1) {
                    label = 'Gestern';
                } else {
                    label = dayName;
                }
                
                return `
                    <li class="mr-3 mb-3 lg:mb-0" role="presentation">
                        <button class="inline-block px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 transition-colors ${index === 0 ? 'bg-primary-820 text-white border-primary-700 dark:bg-primary-800 dark:text-primary-840 dark:border-primary-820' : 'bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:border-primary-720 dark:text-primary-210 dark:hover:bg-primary-760'}" 
                                id="day-${dayKey}-tab" 
                                type="button" 
                                role="tab" 
                                aria-controls="day-${dayKey}" 
                                aria-selected="${index === 0 ? 'true' : 'false'}"
                                onclick="switchTimelineTab('${dayKey}')">
                            <span class="font-semibold">${label}:</span> ${dayDate}
                        </button>
                    </li>
                `;
            }).join('');
            
            // Tab-Content generieren
            contentContainer.innerHTML = sortedDays.map((dayKey, index) => {
                const items = itemsByDay[dayKey];
                const isFirst = index === 0;
                
                return `
                    <div class="${isFirst ? '' : 'hidden'}" id="day-${dayKey}" role="tabpanel" aria-labelledby="day-${dayKey}-tab">
                        <div class="grid max-w-5xl grid-cols-1 p-5 mx-auto border border-gray-100 rounded-lg bg-gray-50 sm:grid-cols-2 dark:bg-primary-100 dark:border-primary-120">
                            ${items.map((item, itemIndex) => {
                                if (!item.date) return '';
                                const date = new Date(item.date);
                                if (isNaN(date.getTime())) return '';
                                
                                const timeStr = date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                                const isPast = date < new Date();
                                const typeColor = item.type === 'todo' 
                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
                                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
                                
                                // Grid-Position bestimmen (abwechselnd links/rechts)
                                const isLeft = itemIndex % 2 === 0;
                                const borderClasses = isLeft 
                                    ? 'sm:pr-5' 
                                    : 'sm:pl-5 sm:border-l dark:border-primary-120';
                                const topBorder = itemIndex >= 2 ? 'sm:border-t dark:border-primary-120 pt-5' : '';
                                
                                const ticketNumber = item.ticket_number ? `#${escapeHtml(item.ticket_number)}` : '';
                                const titleWithNumber = item.type === 'ticket' && ticketNumber 
                                    ? `${ticketNumber} - ${escapeHtml(item.title || 'Unbekannt')}`
                                    : escapeHtml(item.title || 'Unbekannt');
                                
                                return `
                                    <div class="pb-5 space-y-4 ${borderClasses} ${topBorder}">
                                        <span class="bg-primary-100 text-primary-800 text-xs font-medium inline-flex items-center px-2.5 py-0.5 rounded dark:bg-primary-900 dark:text-primary-300">
                                            <svg aria-hidden="true" class="w-3 h-3 mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                            </svg>
                                            ${timeStr}
                                        </span>
                                        <h4 class="text-xl font-bold text-gray-900 sm:text-xl dark:text-white">
                                            <a href="${escapeHtml(item.link || '#')}" class="hover:underline">${titleWithNumber}</a>
                                        </h4>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-1 text-xs rounded ${typeColor}">
                                                ${item.type === 'todo' ? 'Aufgabe' : 'Ticket'}
                                            </span>
                                            ${item.status ? `<span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 dark:bg-primary-700 dark:text-primary-210">${escapeHtml(item.status)}</span>` : ''}
                                            ${isPast ? '<span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">Überfällig</span>' : ''}
                                        </div>
                                    </div>
                                `;
                            }).filter(html => html !== '').join('')}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            contentContainer.innerHTML = '<div class="text-center py-4 text-gray-500 dark:text-primary-220 text-sm">Keine fälligen oder geplanten Termine</div>';
        }
    } catch (error) {
        console.error('Fehler beim Laden des Zeitstrahls:', error);
        if (contentContainer) {
            contentContainer.innerHTML = '<div class="text-center py-4 text-red-500 dark:text-red-400 text-sm">Fehler beim Laden des Zeitstrahls: ' + escapeHtml(error.message || 'Unbekannter Fehler') + '</div>';
        }
    }
}

function switchTimelineTab(dayKey) {
    // Alle Tabs deaktivieren
    document.querySelectorAll('[role="tab"]').forEach(tab => {
        tab.setAttribute('aria-selected', 'false');
        tab.classList.remove('bg-primary-820', 'text-white', 'border-primary-700', 'dark:bg-primary-800', 'dark:text-primary-840', 'dark:border-primary-820');
        tab.classList.add('bg-gray-50', 'text-gray-900', 'hover:bg-gray-100', 'dark:bg-primary-700', 'dark:border-primary-720', 'dark:text-primary-210', 'dark:hover:bg-primary-760');
    });
    
    // Alle Tab-Panels verstecken
    document.querySelectorAll('[role="tabpanel"]').forEach(panel => {
        panel.classList.add('hidden');
    });
    
    // Aktiven Tab aktivieren
    const activeTab = document.getElementById(`day-${dayKey}-tab`);
    if (activeTab) {
        activeTab.setAttribute('aria-selected', 'true');
        activeTab.classList.remove('bg-gray-50', 'text-gray-900', 'hover:bg-gray-100', 'dark:bg-primary-700', 'dark:border-primary-720', 'dark:text-primary-210', 'dark:hover:bg-primary-760');
        activeTab.classList.add('bg-primary-820', 'text-white', 'border-primary-700', 'dark:bg-primary-800', 'dark:text-primary-840', 'dark:border-primary-820');
    }
    
    // Aktives Tab-Panel anzeigen
    const activePanel = document.getElementById(`day-${dayKey}`);
    if (activePanel) {
        activePanel.classList.remove('hidden');
    }
}
<?php endif; ?>

// Hilfsfunktionen
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    
    if (days === 0) return 'Heute';
    if (days === 1) return 'Gestern';
    if (days < 7) return `vor ${days} Tagen`;
    return date.toLocaleDateString('de-DE');
}
</script>

<?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
<script>
const TIME_API_URL = '<?php echo BASE_URL; ?>time-tracking/api/time.php';
let timeTrackingInterval = null;
let startTime = null;

async function checkTimeTrackingStatus() {
    try {
        const response = await fetch(TIME_API_URL + '?status=1');
        const data = await response.json();
        
        if (data.success) {
            if (data.isRunning && data.entry) {
                startTime = new Date(data.entry.start_time);
                updateTimeUI(true);
                startTimeDisplay();
            } else {
                updateTimeUI(false);
            }
        }
    } catch (error) {
        console.error('Fehler beim Laden des Status:', error);
        const statusEl = document.getElementById('time-status');
        if (statusEl) statusEl.textContent = 'Fehler';
    }
}

async function startTimeTracking() {
    const startBtn = document.getElementById('start-time-btn');
    if (startBtn) {
        startBtn.disabled = true;
        startBtn.textContent = 'Wird gestartet...';
    }
    
    try {
        const response = await fetch(TIME_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'start' })
        });
        
        const data = await response.json();
        if (data.success) {
            startTime = new Date();
            updateTimeUI(true);
            startTimeDisplay();
            if (typeof loadNavTimeTrackingStatus === 'function') loadNavTimeTrackingStatus();
            if (typeof showToast === 'function') {
                showToast('Zeiterfassung gestartet', 'success');
            }
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            if (startBtn) {
                startBtn.disabled = false;
                startBtn.innerHTML = '<svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Starten';
            }
        }
    } catch (error) {
        console.error('Fehler beim Starten:', error);
        alert('Fehler beim Starten der Zeiterfassung');
        if (startBtn) {
            startBtn.disabled = false;
            startBtn.innerHTML = '<svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Starten';
        }
    }
}

async function stopTimeTracking() {
    const stopBtn = document.getElementById('stop-time-btn');
    if (stopBtn) {
        stopBtn.disabled = true;
        stopBtn.textContent = 'Wird beendet...';
    }
    
    try {
        const response = await fetch(TIME_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'stop' })
        });
        
        const data = await response.json();
        if (data.success) {
            startTime = null;
            updateTimeUI(false);
            if (typeof loadNavTimeTrackingStatus === 'function') loadNavTimeTrackingStatus();
            if (typeof showToast === 'function') {
                showToast('Zeiterfassung beendet', 'success');
            }
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            if (stopBtn) {
                stopBtn.disabled = false;
                stopBtn.innerHTML = '<svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"/></svg>Beenden';
            }
        }
    } catch (error) {
        console.error('Fehler beim Beenden:', error);
        alert('Fehler beim Beenden der Zeiterfassung');
        if (stopBtn) {
            stopBtn.disabled = false;
            stopBtn.innerHTML = '<svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"/></svg>Beenden';
        }
    }
}
<?php endif; ?>
</script>
