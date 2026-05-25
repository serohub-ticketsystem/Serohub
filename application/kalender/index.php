<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/user_profile_fields.php';
requireLogin();

$userId = $_SESSION['user_id'] ?? null;
$calendarColleagues = [];
$calendarAssignees = [];
$calendarBusinessHours = user_profile_fields_erreichbarkeit_calendar_business_hours(null);
try {
    $stmt = $pdo->prepare("SELECT id, rolle, erreichbarkeit FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $calendarBusinessHours = user_profile_fields_erreichbarkeit_calendar_business_hours($user['erreichbarkeit'] ?? null);
    }
    $isAdminOrTechniker = $user && in_array($user['rolle'], ['Admin', 'Techniker'], true);
    if ($isAdminOrTechniker) {
        $stmt = $pdo->prepare("SELECT id, vorname, nachname FROM users WHERE rolle IN ('Admin', 'Techniker') AND id != :uid AND status = 'aktiv' ORDER BY nachname, vorname");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $calendarColleagues[] = [
                'id' => (int) $row['id'],
                'name' => trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'User #' . $row['id']
            ];
        }
        $stmt = $pdo->query("SELECT id, vorname, nachname, email FROM users WHERE status = 'aktiv' AND rolle IN ('Admin', 'Techniker') ORDER BY nachname, vorname");
        $calendarAssignees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $isAdminOrTechniker = false;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
?>

<div id="main-content" class="kalender-page relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-hidden">
  <main class="pt-4 pr-4 pb-4 pl-1 flex flex-col overflow-hidden">
    <nav class="mb-4 flex flex-shrink-0" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo htmlspecialchars($baseUrl); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
            <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
            </svg>
            Startseite
          </a>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Kalender</span>
          </div>
        </li>
      </ol>
    </nav>

    <div id="calendar-layout" class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-3 flex-1 overflow-hidden min-h-0">
      <!-- Sidebar: Mini-Kalender + kompakte Filter -->
      <aside id="calendar-sidebar" class="cal-sidebar flex flex-col gap-3 lg:order-1 overflow-y-auto min-h-0">
        <div class="cal-sidebar-card cal-sidebar-main-card">
          <?php if ($isAdminOrTechniker): ?>
          <div class="cal-sidebar-head">
            <button type="button" id="cal-new-event" class="cal-sidebar-head__create" title="Neuer Termin">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Neuer Termin
            </button>
          </div>
          <?php else: ?>
          <div class="cal-sidebar-head cal-sidebar-head--solo">
            <button type="button" id="cal-sidebar-toggle" class="cal-icon-btn cal-sidebar-head__toggle hidden lg:inline-flex" aria-expanded="true" aria-controls="calendar-sidebar" title="Seitenleiste ausblenden">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
          </div>
          <?php endif; ?>
          <div class="cal-sidebar-main-card__calendar">
          <div class="cal-mini-nav">
            <button type="button" id="cal-mini-prev" class="cal-icon-btn" aria-label="Vorheriger Monat">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <span id="cal-mini-month-label" class="cal-mini-nav__title"></span>
            <button type="button" id="cal-mini-next" class="cal-icon-btn" aria-label="Nächster Monat">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
          <div id="mini-calendar" class="cal-mini-cal">
            <div class="cal-mini-cal__weekdays">
              <span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span>
            </div>
            <div id="mini-calendar-days" class="cal-mini-cal__days"></div>
          </div>
          </div>
          <div class="cal-sidebar-controls">
            <div class="cal-sidebar-toolbar__views cal-view-segment" id="cal-view-segment" role="group" aria-label="Ansicht">
              <div class="cal-view-segment-track" role="tablist" aria-label="Kalenderansicht">
                <div class="cal-view-segment-thumb" aria-hidden="true"></div>
                <button type="button" data-view="dayGridMonth" class="cal-view-segment-item cal-view-btn" role="tab" aria-selected="true" tabindex="0">Monat</button>
                <button type="button" data-view="timeGridWeek" class="cal-view-segment-item cal-view-btn" role="tab" aria-selected="false" tabindex="-1">Woche</button>
                <button type="button" data-view="timeGridDay" class="cal-view-segment-item cal-view-btn" role="tab" aria-selected="false" tabindex="-1">Tag</button>
              </div>
            </div>
            <?php if ($isAdminOrTechniker): ?>
            <div class="cal-sidebar-controls__more">
              <div class="relative" id="cal-settings-dropdown-container">
                <button type="button" id="cal-settings-btn" class="cal-icon-btn cal-settings-btn" title="Kalender-Einstellungen" aria-haspopup="true" aria-expanded="false" aria-controls="cal-settings-dropdown-menu">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
                <div id="cal-settings-dropdown-menu" class="service-filter-dropdown-shadow hidden absolute z-[80] w-72 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-xl overflow-hidden" role="menu" aria-labelledby="cal-settings-btn">
                  <div class="px-3 py-2.5 border-b border-gray-100 dark:border-primary-120/60">
                    <p class="text-xs font-semibold text-gray-900 dark:text-primary-200">Kalender-Einstellungen</p>
                  </div>
                  <div class="p-3 space-y-3 max-h-[min(20rem,calc(100vh-8rem))] overflow-y-auto custom-scrollbar">
                    <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl border border-gray-200 dark:border-primary-320 bg-gray-50 dark:bg-primary-140">
                      <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-primary-200">Monatsansicht</div>
                        <div class="text-xs text-gray-500 dark:text-primary-220">Samstag &amp; Sonntag</div>
                      </div>
                      <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" id="setting-month-weekends" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-250 dark:peer-checked:bg-primary-420"></div>
                      </label>
                    </div>
                    <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl border border-gray-200 dark:border-primary-320 bg-gray-50 dark:bg-primary-140">
                      <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-primary-200">Arbeitswoche</div>
                        <div class="text-xs text-gray-500 dark:text-primary-220">Samstag &amp; Sonntag</div>
                      </div>
                      <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" id="setting-week-weekends" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-250 dark:peer-checked:bg-primary-420"></div>
                      </label>
                    </div>
                  </div>
                  <div class="px-3 py-2.5 border-t border-gray-100 dark:border-primary-120/60 hidden lg:block">
                    <button type="button" id="cal-sidebar-toggle" class="w-full flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-primary-210 dark:hover:text-primary-200 text-left" role="menuitem" aria-expanded="true" aria-controls="calendar-sidebar" title="Seitenleiste ausblenden">
                      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                      <span id="cal-sidebar-toggle-label">Seitenleiste ausblenden</span>
                    </button>
                  </div>
                  <div class="px-3 py-2.5 border-t border-gray-100 dark:border-primary-120/60">
                    <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>settings/calendar-export.php" class="flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-420 dark:hover:text-primary-440">
                      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                      Export &amp; Sync
                    </a>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($isAdminOrTechniker): ?>
        <div class="cal-sidebar-card cal-sidebar-card--grow">
          <div id="cal-filters-main" class="cal-filter-list">
            <label class="cal-filter-item" data-cal-filter-group="main">
              <span class="cal-color-btn cal-filter-swatch" role="button" tabindex="0" data-color-key="my_calendar" title="Farbe" style="background-color:#8b5cf6"></span>
              <span class="cal-filter-label">Mein Kalender</span>
              <input type="checkbox" name="cal_filter" value="my_calendar" checked class="cal-filter-toggle sr-only peer" data-filter-key="my_calendar">
              <span class="cal-toggle-track cal-toggle-track--sm"></span>
            </label>
            <label class="cal-filter-item" data-cal-filter-group="main">
              <span class="cal-color-btn cal-filter-swatch" role="button" tabindex="0" data-color-key="my_vacation" title="Farbe" style="background-color:#06b6d4"></span>
              <span class="cal-filter-label">Mein Urlaub</span>
              <input type="checkbox" name="cal_filter" value="my_vacation" class="cal-filter-toggle sr-only peer" data-filter-key="my_vacation">
              <span class="cal-toggle-track cal-toggle-track--sm"></span>
            </label>
            <label class="cal-filter-item" data-cal-filter-group="main">
              <span class="cal-color-btn cal-filter-swatch" role="button" tabindex="0" data-color-key="my_times" title="Farbe" style="background-color:#6366f1"></span>
              <span class="cal-filter-label">Meine Zeiten</span>
              <input type="checkbox" name="cal_filter" value="my_times" class="cal-filter-toggle sr-only peer" data-filter-key="my_times">
              <span class="cal-toggle-track cal-toggle-track--sm"></span>
            </label>
            <label class="cal-filter-item" data-cal-filter-group="main">
              <span class="cal-color-btn cal-filter-swatch" role="button" tabindex="0" data-color-key="service" title="Farbe" style="background-color:#3b82f6"></span>
              <span class="cal-filter-label">Tickets</span>
              <input type="checkbox" name="cal_filter" value="service" class="cal-filter-toggle sr-only peer" data-filter-key="service">
              <span class="cal-toggle-track cal-toggle-track--sm"></span>
            </label>
            <label class="cal-filter-item" data-cal-filter-group="main">
              <span class="cal-color-btn cal-filter-swatch" role="button" tabindex="0" data-color-key="todos" title="Farbe" style="background-color:#a855f7"></span>
              <span class="cal-filter-label">Aufgaben</span>
              <input type="checkbox" name="cal_filter" value="todos" class="cal-filter-toggle sr-only peer" data-filter-key="todos">
              <span class="cal-toggle-track cal-toggle-track--sm"></span>
            </label>
          </div>

          <details class="cal-sidebar-details">
            <summary class="cal-sidebar-details__summary">Kollegen <span class="cal-sidebar-details__count"><?php echo count($calendarColleagues) + 1; ?></span></summary>
            <div id="cal-other-users" class="cal-filter-list">
              <label class="cal-filter-item" data-cal-filter-group="colleagues">
                <span class="cal-color-btn cal-filter-swatch" role="button" tabindex="0" data-color-key="colleagues_vacation" title="Farbe" style="background-color:#64748b"></span>
                <span class="cal-filter-label">Urlaub Kollegen</span>
                <input type="checkbox" name="cal_filter" value="colleagues_vacation" class="cal-filter-toggle sr-only peer" data-filter-key="colleagues_vacation">
                <span class="cal-toggle-track cal-toggle-track--sm"></span>
              </label>
              <?php foreach ($calendarColleagues as $col): ?>
              <label class="cal-filter-item" data-cal-filter-group="colleagues">
                <span class="cal-color-btn cal-filter-swatch" role="button" tabindex="0" data-color-key="other_calendar" title="Farbe" style="background-color:#8b5cf6"></span>
                <span class="cal-filter-label truncate" title="<?php echo htmlspecialchars($col['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($col['name']); ?></span>
                <input type="checkbox" name="cal_other_user[]" value="<?php echo (int)$col['id']; ?>" class="cal-other-user-cb cal-filter-toggle sr-only peer" data-filter-key="other_user_<?php echo (int)$col['id']; ?>">
                <span class="cal-toggle-track cal-toggle-track--sm"></span>
              </label>
              <?php endforeach; ?>
            </div>
          </details>

          <details class="cal-sidebar-details">
            <summary class="cal-sidebar-details__summary">
              Abonnements
              <button type="button" id="add-subscription-btn" class="cal-sidebar-details__action" title="Kalender hinzufügen" aria-label="Kalender hinzufügen">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              </button>
            </summary>
            <div id="subscription-list" class="cal-filter-list">
              <p class="cal-sidebar-hint">Lade Kalender…</p>
            </div>
          </details>
        </div>
        <?php else: ?>
        <div class="cal-sidebar-card">
          <p class="cal-sidebar-heading">Hinweis</p>
          <p class="cal-sidebar-hint">Es werden nur Ihre zugewiesenen und beobachteten Tickets angezeigt.</p>
        </div>
        <?php endif; ?>
      </aside>

      <div id="calendar-sidebar-collapsed" class="hidden lg:flex lg:order-1 flex-col items-center gap-2 cal-sidebar-card p-2">
        <button type="button" id="cal-sidebar-expand" class="cal-icon-btn" aria-label="Leiste ausklappen">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
        <div id="cal-title-compact" class="text-[10px] leading-tight font-semibold text-center text-gray-700 dark:text-primary-210 min-h-[2.5rem] flex items-center justify-center px-0.5"></div>
        <button type="button" id="cal-prev-mini" class="cal-icon-btn" aria-label="Zurück">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button type="button" id="cal-next-mini" class="cal-icon-btn" aria-label="Weiter">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>

      <!-- Hauptbereich: Kalender -->
      <div class="cal-main flex flex-col lg:order-2 overflow-hidden min-h-0 min-w-0">
        <div class="cal-calendar-panel flex-1 min-h-0 overflow-hidden">
          <div id="calendar" class="h-full"></div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal: Neuer Termin / Termin bearbeiten -->
<div id="event-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
  <div class="flex min-h-full items-center justify-center p-4">
    <div id="event-modal-backdrop" class="fixed inset-0 bg-black/50 dark:bg-black/70"></div>
    <div class="relative rounded-2xl border border-gray-200 bg-white dark:border-primary-120 dark:bg-primary-100 shadow-xl w-full max-w-lg">
      <div class="p-4 border-b border-gray-200 dark:border-primary-120">
        <h3 id="event-modal-title" class="text-lg font-semibold text-gray-900 dark:text-primary-200">Neuer Termin</h3>
      </div>
      <form id="event-form" class="p-4 space-y-4">
        <input type="hidden" id="event-id" name="id" value="">
        <div id="event-type-wrap">
          <label class="block text-xs font-medium text-gray-500 dark:text-primary-220 mb-1">Art</label>
          <div class="flex flex-wrap gap-1.5">
            <label class="event-type-card relative cursor-pointer">
              <input type="radio" name="event_type" value="term" checked class="event-type-radio sr-only peer">
              <div class="flex items-center gap-1.5 px-2 py-1 rounded-xl border border-gray-200 dark:border-primary-320 bg-white dark:bg-primary-300 transition-all peer-checked:border-primary-250 peer-checked:bg-primary-50 dark:peer-checked:border-primary-420 dark:peer-checked:bg-primary-140 hover:border-gray-300 dark:hover:border-primary-310">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-gray-500 dark:text-primary-220 peer-checked:text-primary-250 dark:peer-checked:text-primary-420 shrink-0">
                  <path d="M12.75 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM7.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM8.25 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM9.75 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM10.5 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12.75 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM14.25 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 13.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                  <path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd" />
                </svg>
                <span class="text-xs font-medium text-gray-600 dark:text-primary-210 peer-checked:text-primary-250 dark:peer-checked:text-primary-420">Termin</span>
              </div>
            </label>
            <?php if ($isAdminOrTechniker): ?>
            <label class="event-type-card relative cursor-pointer">
              <input type="radio" name="event_type" value="vacation" class="event-type-radio sr-only peer">
              <div class="flex items-center gap-1.5 px-2 py-1 rounded-xl border border-gray-200 dark:border-primary-320 bg-white dark:bg-primary-300 transition-all peer-checked:border-cyan-500 peer-checked:bg-cyan-50 dark:peer-checked:border-cyan-400 dark:peer-checked:bg-cyan-950/30 hover:border-gray-300 dark:hover:border-primary-310">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-gray-500 dark:text-primary-220 peer-checked:text-cyan-500 dark:peer-checked:text-cyan-400 shrink-0">
                  <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM8.547 4.505a8.25 8.25 0 1 0 11.672 8.214l-.46-.46a2.252 2.252 0 0 1-.422-.586l-1.08-2.16a.414.414 0 0 0-.663-.107.827.827 0 0 1-.812.21l-1.273-.363a.89.89 0 0 0-.738 1.595l.587.39c.59.395.674 1.23.172 1.732l-.2.2c-.211.212-.33.498-.33.796v.41c0 .409-.11.809-.32 1.158l-1.315 2.191a2.11 2.11 0 0 1-1.81 1.025 1.055 1.055 0 0 1-1.055-1.055v-1.172c0-.92-.56-1.747-1.414-2.089l-.654-.261a2.25 2.25 0 0 1-1.384-2.46l.007-.042a2.25 2.25 0 0 1 .29-.787l.09-.15a2.25 2.25 0 0 1 2.37-1.048l1.178.236a1.125 1.125 0 0 0 1.302-.795l.208-.73a1.125 1.125 0 0 0-.578-1.315l-.665-.332-.091.091a2.25 2.25 0 0 1-1.591.659h-.18c-.249 0-.487.1-.662.274a.931.931 0 0 1-1.458-1.137l1.279-2.132Z" clip-rule="evenodd" />
                </svg>
                <span class="text-xs font-medium text-gray-600 dark:text-primary-210 peer-checked:text-cyan-500 dark:peer-checked:text-cyan-400">Urlaub</span>
              </div>
            </label>
            <label class="event-type-card relative cursor-pointer">
              <input type="radio" name="event_type" value="holiday" class="event-type-radio sr-only peer">
              <div class="flex items-center gap-1.5 px-2 py-1 rounded-xl border border-gray-200 dark:border-primary-320 bg-white dark:bg-primary-300 transition-all peer-checked:border-amber-500 peer-checked:bg-amber-50 dark:peer-checked:border-amber-400 dark:peer-checked:bg-amber-950/30 hover:border-gray-300 dark:hover:border-primary-310">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-gray-500 dark:text-primary-220 peer-checked:text-amber-500 dark:peer-checked:text-amber-400 shrink-0">
                  <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                </svg>
                <span class="text-xs font-medium text-gray-600 dark:text-primary-210 peer-checked:text-amber-500 dark:peer-checked:text-amber-400">Feiertag</span>
              </div>
            </label>
            <?php endif; ?>
          </div>
        </div>

        <div id="event-type-term-fields" class="space-y-3">
          <div>
            <label for="event-title" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Titel *</label>
            <input type="text" id="event-title" name="title" maxlength="255" class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360">
          </div>

          <!-- Ganztägig-Toggle (über Datum/Zeit) -->
          <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-primary-320 bg-gray-50 dark:bg-primary-140">
            <span class="text-sm font-medium text-gray-700 dark:text-primary-210">Ganztägig</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="event-allday-toggle" class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-primary-320 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-gray-600 peer-checked:bg-primary-250 dark:peer-checked:bg-primary-420"></div>
            </label>
          </div>

          <!-- Datum-Felder: bei Ganztägig Start+Ende (50%), sonst nur Datum -->
          <div id="event-date-fields" class="grid grid-cols-1 gap-3">
            <div>
              <label id="event-date-label" for="event-date" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Datum *</label>
              <input type="date" id="event-date" class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360">
            </div>
            <div id="event-date-end-wrap" class="hidden">
              <label for="event-date-end" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Enddatum *</label>
              <input type="date" id="event-date-end" class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360">
            </div>
          </div>

          <div id="event-time-fields" class="grid grid-cols-2 gap-3">
            <div>
              <label for="event-start-time" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-2">Startzeit *</label>
              <div class="space-y-2">
                <div class="flex items-center gap-2">
                  <span class="text-2xl font-semibold text-gray-900 dark:text-primary-200 w-16" id="event-start-time-display">09:00</span>
                  <span class="text-xs text-gray-500 dark:text-primary-220">Uhr</span>
                </div>
                <input type="range" id="event-start-time" min="0" max="1439" step="15" value="540" class="time-slider w-full h-2 bg-gray-200 dark:bg-primary-320 rounded-lg appearance-none cursor-pointer">
              </div>
            </div>
            <div>
              <label for="event-end-time" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-2">Endzeit *</label>
              <div class="space-y-2">
                <div class="flex items-center gap-2">
                  <span class="text-2xl font-semibold text-gray-900 dark:text-primary-200 w-16" id="event-end-time-display">09:30</span>
                  <span class="text-xs text-gray-500 dark:text-primary-220">Uhr</span>
                </div>
                <input type="range" id="event-end-time" min="0" max="1439" step="15" value="570" class="time-slider w-full h-2 bg-gray-200 dark:bg-primary-320 rounded-lg appearance-none cursor-pointer">
              </div>
            </div>
          </div>
          <input type="hidden" id="event-start" name="start_at">
          <input type="hidden" id="event-end" name="end_at">
          <input type="hidden" id="event-allday" name="all_day" value="0">

          <!-- Mehr einstellen Button -->
          <div class="pt-1">
            <button type="button" id="event-more-toggle" class="flex items-center gap-2 text-sm font-medium text-primary-250 dark:text-primary-420 hover:underline">
              <svg id="event-more-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
              </svg>
              <span id="event-more-label">Mehr einstellen</span>
            </button>
          </div>

          <!-- Mehr einstellen: ausgeklappter Bereich -->
          <div id="event-more-fields" class="hidden space-y-3 pt-2 border-t border-gray-200 dark:border-primary-320">
            <div>
              <label for="event-description" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Beschreibung</label>
              <textarea id="event-description" name="description" rows="2" class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360"></textarea>
            </div>
            <div id="event-meeting-link-wrap">
              <label for="event-meeting-link" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Meeting-Link (Teams / Google Meet)</label>
              <input type="url" id="event-meeting-link" name="meeting_link" placeholder="https://teams.microsoft.com/... oder https://meet.google.com/..." class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360">
            </div>
            <?php if ($isAdminOrTechniker && count($calendarColleagues) > 0): ?>
            <div id="event-invitees-wrap">
              <label class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-2">
                <span class="flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 01-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.308-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.026.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z" />
                  </svg>
                  Kollegen einladen
                </span>
              </label>
              <div id="event-invitees" class="flex flex-wrap gap-2 p-3 rounded-xl border border-gray-200 dark:border-primary-320 bg-gray-50 dark:bg-primary-140 min-h-[50px]"></div>
            </div>
            <?php endif; ?>
            <div>
              <label for="event-invite-emails" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Weitere per E-Mail einladen</label>
              <input type="text" id="event-invite-emails" name="invite_emails" placeholder="max@beispiel.de, anna@firma.com" class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360">
              <p class="mt-1 text-xs text-gray-500 dark:text-primary-220">Mehrere E-Mail-Adressen mit Komma trennen</p>
            </div>
          </div>
        </div>

        <?php if ($isAdminOrTechniker): ?>
        <div id="event-type-vacation-fields" class="space-y-4 hidden">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="vacation-date-from" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Von *</label>
              <input type="date" id="vacation-date-from" name="vacation_date_from" class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360">
            </div>
            <div>
              <label for="vacation-date-to" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Bis *</label>
              <input type="date" id="vacation-date-to" name="vacation_date_to" class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360">
            </div>
          </div>
          <div>
            <label for="vacation-hours" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Stunden pro Tag</label>
            <input type="number" id="vacation-hours" name="vacation_hours" value="8" min="0" max="24" step="0.5" class="block w-full rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 px-3 py-2 text-sm focus:ring-primary-360 focus:border-primary-360">
          </div>
        </div>
        <?php endif; ?>
      </form>
      <div class="p-4 border-t border-gray-200 dark:border-primary-120 flex justify-end gap-2">
        <button type="button" id="event-modal-cancel" class="px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 dark:border-primary-320 text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
        <button type="submit" form="event-form" id="event-modal-save" class="px-3 py-2 text-sm font-medium rounded-xl bg-primary-250 text-white hover:bg-primary-260 dark:bg-primary-420 dark:hover:bg-primary-440">Speichern</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Termin-Details (nur anzeigen) -->
<div id="event-detail-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
  <div class="flex min-h-full items-center justify-center p-4">
    <div id="event-detail-backdrop" class="fixed inset-0 bg-black/50 dark:bg-black/70"></div>
    <div class="relative rounded-2xl border border-gray-200 bg-white dark:border-primary-120 dark:bg-primary-100 shadow-xl w-full max-w-md">
      <div class="p-4 border-b border-gray-200 dark:border-primary-120 flex items-center justify-between">
        <h3 id="event-detail-title" class="text-lg font-semibold text-gray-900 dark:text-primary-200"></h3>
        <button type="button" id="event-detail-close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-primary-140 text-gray-500 dark:text-primary-220">&times;</button>
      </div>
      <div id="event-detail-body" class="p-4 space-y-2 text-sm text-gray-700 dark:text-primary-210"></div>
      <div id="event-detail-actions" class="p-4 border-t border-gray-200 dark:border-primary-120 flex justify-end gap-2"></div>
    </div>
  </div>
</div>

<!-- Kontextmenü Kalender-Einträge (Rechtsklick) -->
<div id="calEventContextBackdrop" class="hidden fixed inset-0 z-[80]" aria-hidden="true"></div>
<div id="calEventContextMenu" class="hidden fixed z-[81] min-w-[200px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg" role="menu" aria-label="Kalender-Eintrag Aktionen"></div>

<!-- Modal: Kalender-Abonnement hinzufügen/bearbeiten -->
<div id="subscription-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
  <div class="flex min-h-full items-center justify-center p-4">
    <div id="subscription-modal-backdrop" class="fixed inset-0 bg-black/50 dark:bg-black/70"></div>
    <div class="relative rounded-2xl border border-gray-200 bg-white dark:border-primary-120 dark:bg-primary-100 shadow-xl w-full max-w-md">
      <div class="p-4 border-b border-gray-200 dark:border-primary-120 flex items-center justify-between">
        <h3 id="subscription-modal-title" class="text-lg font-semibold text-gray-900 dark:text-primary-200">Kalender hinzufügen</h3>
        <button type="button" id="subscription-modal-close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-primary-140 text-gray-500 dark:text-primary-220 text-2xl leading-none">&times;</button>
      </div>
      <form id="subscription-form" class="p-4 space-y-4">
        <input type="hidden" id="subscription-id" value="">
        
        <div>
          <label for="subscription-name" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Name</label>
          <input type="text" id="subscription-name" class="w-full px-3 py-2 text-sm rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250 dark:focus:ring-primary-420" placeholder="z.B. Feiertage Deutschland" required>
        </div>
        
        <div>
          <label for="subscription-url" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1">Kalender-URL (ICS/CalDAV)</label>
          <input type="url" id="subscription-url" class="w-full px-3 py-2 text-sm rounded-xl border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-250 dark:focus:ring-primary-420" placeholder="https://..." required>
          <p class="mt-1 text-xs text-gray-500 dark:text-primary-220">ICS-URL von Google Calendar, Outlook, Apple Calendar, etc.</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-2">Farbe</label>
          <div id="subscription-colors" class="flex flex-wrap gap-2">
            <label class="cursor-pointer">
              <input type="radio" name="subscription_color" value="#6366f1" class="sr-only peer" checked>
              <span class="block w-8 h-8 rounded-full bg-[#6366f1] ring-2 ring-transparent peer-checked:ring-gray-900 dark:peer-checked:ring-white peer-checked:ring-offset-2"></span>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="subscription_color" value="#ec4899" class="sr-only peer">
              <span class="block w-8 h-8 rounded-full bg-[#ec4899] ring-2 ring-transparent peer-checked:ring-gray-900 dark:peer-checked:ring-white peer-checked:ring-offset-2"></span>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="subscription_color" value="#f97316" class="sr-only peer">
              <span class="block w-8 h-8 rounded-full bg-[#f97316] ring-2 ring-transparent peer-checked:ring-gray-900 dark:peer-checked:ring-white peer-checked:ring-offset-2"></span>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="subscription_color" value="#eab308" class="sr-only peer">
              <span class="block w-8 h-8 rounded-full bg-[#eab308] ring-2 ring-transparent peer-checked:ring-gray-900 dark:peer-checked:ring-white peer-checked:ring-offset-2"></span>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="subscription_color" value="#8b5cf6" class="sr-only peer">
              <span class="block w-8 h-8 rounded-full bg-[#8b5cf6] ring-2 ring-transparent peer-checked:ring-gray-900 dark:peer-checked:ring-white peer-checked:ring-offset-2"></span>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="subscription_color" value="#7c3aed" class="sr-only peer">
              <span class="block w-8 h-8 rounded-full bg-[#7c3aed] ring-2 ring-transparent peer-checked:ring-gray-900 dark:peer-checked:ring-white peer-checked:ring-offset-2"></span>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="subscription_color" value="#0ea5e9" class="sr-only peer">
              <span class="block w-8 h-8 rounded-full bg-[#0ea5e9] ring-2 ring-transparent peer-checked:ring-gray-900 dark:peer-checked:ring-white peer-checked:ring-offset-2"></span>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="subscription_color" value="#64748b" class="sr-only peer">
              <span class="block w-8 h-8 rounded-full bg-[#64748b] ring-2 ring-transparent peer-checked:ring-gray-900 dark:peer-checked:ring-white peer-checked:ring-offset-2"></span>
            </label>
          </div>
        </div>
      </form>
      <div class="p-4 border-t border-gray-200 dark:border-primary-120 flex justify-between">
        <button type="button" id="subscription-delete-btn" class="px-4 py-2 text-sm font-medium rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 hidden">Löschen</button>
        <div class="flex gap-2 ml-auto">
          <button type="button" id="subscription-modal-cancel" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-300 dark:border-primary-320 text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
          <button type="button" id="subscription-modal-save" class="px-4 py-2 text-sm font-medium rounded-xl bg-primary-250 text-white hover:bg-primary-260 dark:bg-primary-420 dark:hover:bg-primary-440">Speichern</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core/locales/de.global.min.js"></script>
<style>
  /* Fixiertes Layout für Kalender-Seite - kein Seiten-Scroll */
  html:has(.kalender-page), 
  body:has(.kalender-page) {
    overflow: hidden !important;
    height: 100vh !important;
    max-height: 100vh !important;
  }
  body.app-mobile-bottom-nav:not(.app-mobile-dashboard-shell):not(.service-mobile-fullscreen) #main-content.kalender-page {
    overflow: hidden !important;
    overflow-y: hidden !important;
  }
  .kalender-page {
    --cal-accent: #7c3aed;
    --cal-accent-hover: #6d28d9;
    --cal-accent-dark: #8b5cf6;
    --cal-accent-dark-hover: #a78bfa;
    --cal-accent-soft: rgba(124, 58, 237, 0.12);
    --cal-accent-soft-dark: rgba(139, 92, 246, 0.16);
    --cal-accent-border: rgba(124, 58, 237, 0.45);
    --cal-accent-border-dark: rgba(167, 139, 250, 0.55);
    --cal-radius-card: 1rem;
    --cal-radius-control: 0.75rem;
    --cal-radius-pill: 9999px;
    --cal-space-card: 1rem;
    --cal-space-section: 0.875rem;
    --cal-space-stack: 0.375rem;
    height: 100vh;
    max-height: 100vh;
    overflow: hidden !important;
  }
  .kalender-page > main {
    height: calc(100vh - 48px - 48px);
    max-height: calc(100vh - 48px - 48px);
    overflow: hidden !important;
  }
  @media (min-width: 1024px) {
    .kalender-page > main {
      height: calc(100vh - 48px);
      max-height: calc(100vh - 48px);
    }
  }
  
  /* Schöne Scrollbars */
  .kalender-page ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }
  .kalender-page ::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
  }
  .kalender-page ::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.15);
    border-radius: 4px;
    transition: background 0.2s;
  }
  .kalender-page ::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.25);
  }
  .kalender-page ::-webkit-scrollbar-corner {
    background: transparent;
  }
  
  /* Dark Mode Scrollbars */
  .dark .kalender-page ::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
  }
  .dark .kalender-page ::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
  }
  .dark .kalender-page ::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
  }
  
  /* Firefox Scrollbar Styling */
  .kalender-page * {
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.15) rgba(0, 0, 0, 0.05);
  }
  .dark .kalender-page * {
    scrollbar-color: rgba(255, 255, 255, 0.15) rgba(255, 255, 255, 0.05);
  }
  
  .fc { --fc-border-color: rgba(255,255,255,0.06); }
  .fc-theme-standard td, .fc-theme-standard th { border-color: rgba(255,255,255,0.06); }
  .fc .fc-toolbar-title { font-size: 1rem; }
  
  /* Time Slider Styling */
  .time-slider {
    -webkit-appearance: none;
    appearance: none;
  }
  .time-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--cal-accent);
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: all 0.15s ease;
  }
  .time-slider::-webkit-slider-thumb:hover {
    background: var(--cal-accent-hover);
    transform: scale(1.1);
  }
  .time-slider::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--cal-accent);
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: all 0.15s ease;
  }
  .time-slider::-moz-range-thumb:hover {
    background: var(--cal-accent-hover);
    transform: scale(1.1);
  }
  .time-slider::-webkit-slider-runnable-track {
    background: linear-gradient(to right, var(--cal-accent) 0%, var(--cal-accent) var(--slider-progress, 0%), #e5e7eb var(--slider-progress, 0%), #e5e7eb 100%);
    height: 8px;
    border-radius: 4px;
  }
  .time-slider::-moz-range-track {
    background: #e5e7eb;
    height: 8px;
    border-radius: 4px;
  }
  .time-slider::-moz-range-progress {
    background: var(--cal-accent);
    height: 8px;
    border-radius: 4px;
  }
  .dark .time-slider::-webkit-slider-thumb {
    background: var(--cal-accent-dark);
  }
  .dark .time-slider::-webkit-slider-thumb:hover {
    background: var(--cal-accent-dark-hover);
  }
  .dark .time-slider::-moz-range-thumb {
    background: var(--cal-accent-dark);
  }
  .dark .time-slider::-moz-range-thumb:hover {
    background: var(--cal-accent-dark-hover);
  }
  
  /* Monatsansicht: kompakt, konsistent, gut bei vielen Terminen */
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day {
    border: 1px solid rgba(148, 163, 184, 0.35) !important;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day {
    border-color: rgba(148, 163, 184, 0.22) !important;
  }
  /* Sidebar & Toolbar */
  .cal-sidebar-card {
    border-radius: var(--cal-radius-card);
    border: 1px solid rgb(229 231 235);
    background: #fff;
    padding: var(--cal-space-card);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    flex-shrink: 0;
  }
  .dark .cal-sidebar-card {
    border-color: var(--color-primary-120, #1e293b);
    background: var(--color-primary-100, #0f172a);
  }
  .cal-sidebar-card--grow {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: var(--cal-space-section);
    overflow-y: auto;
  }
  .cal-sidebar-heading {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgb(107 114 128);
    margin: 0 0 0.375rem;
  }
  .dark .cal-sidebar-heading { color: rgb(148 163 184); }
  .cal-sidebar-subheading {
    font-size: 0.6875rem;
    font-weight: 500;
    color: rgb(107 114 128);
    margin: 0.5rem 0 0.25rem;
    padding-top: 0.375rem;
    border-top: 1px solid rgb(243 244 246);
  }
  .dark .cal-sidebar-subheading {
    color: rgb(148 163 184);
    border-color: rgba(255,255,255,0.06);
  }
  .cal-sidebar-hint {
    font-size: 0.75rem;
    color: rgb(107 114 128);
    margin: 0;
  }
  .dark .cal-sidebar-hint { color: rgb(148 163 184); }
  .cal-sidebar-main-card {
    display: flex;
    flex-direction: column;
    gap: var(--cal-space-section);
  }
  .cal-sidebar-main-card__calendar {
    display: flex;
    flex-direction: column;
    gap: var(--cal-space-stack);
  }
  .cal-sidebar-head {
    display: flex;
    align-items: center;
    gap: 0.375rem;
  }
  .cal-sidebar-head__create {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    min-width: 0;
    padding: 0.4375rem 0.625rem;
    font-size: 0.8125rem;
    font-weight: 500;
    border-radius: var(--cal-radius-control);
    border: none;
    background: var(--cal-accent);
    color: #fff;
    cursor: pointer;
  }
  .cal-sidebar-head__create:hover {
    background: var(--cal-accent-hover);
  }
  .dark .cal-sidebar-head__create {
    background: var(--cal-accent-dark);
  }
  .dark .cal-sidebar-head__create:hover {
    background: var(--cal-accent-dark-hover);
  }
  .cal-sidebar-head__toggle {
    flex-shrink: 0;
  }
  .cal-sidebar-head--solo {
    justify-content: flex-end;
  }
  .cal-sidebar-controls {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
    padding-top: 0.125rem;
  }
  .cal-sidebar-controls__more {
    display: flex;
    align-items: stretch;
    flex-shrink: 0;
  }
  .cal-sidebar-controls__more #cal-settings-dropdown-container {
    display: flex;
    align-items: stretch;
  }
  .cal-mini-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.375rem;
    margin-bottom: 0;
  }
  .cal-mini-nav__title {
    flex: 1;
    text-align: center;
    font-size: 0.8125rem;
    font-weight: 600;
    color: rgb(17 24 39);
    line-height: 1.2;
    text-transform: capitalize;
  }
  .dark .cal-mini-nav__title { color: rgb(226 232 240); }
  .cal-sidebar-toolbar__views {
    flex: 1 1 auto;
    min-width: 0;
    width: 100%;
    display: flex;
    align-items: stretch;
  }
  .cal-view-segment {
    width: 100%;
    max-width: 100%;
  }
  .cal-view-segment-track {
    flex: 1;
    height: 100%;
    --cal-view-gap: 3px;
    position: relative;
    display: flex;
    align-items: stretch;
    width: 100%;
    min-height: 2rem;
    padding: var(--cal-view-gap);
    border-radius: 9999px;
    border: 1px solid rgb(209 213 219);
    background: #fff;
    touch-action: none;
    user-select: none;
    -webkit-user-select: none;
    cursor: grab;
    box-sizing: border-box;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
  }
  .cal-view-segment-track:active { cursor: grabbing; }
  .dark .cal-view-segment-track {
    border-color: rgb(58 61 66);
    background: rgb(41 42 46);
  }
  .cal-view-segment-thumb {
    position: absolute;
    top: var(--cal-view-gap);
    left: 0;
    height: calc(100% - 2 * var(--cal-view-gap));
    z-index: 0;
    margin: 0;
    border-radius: 9999px;
    background: #ede9fe;
    pointer-events: none;
    box-shadow: inset 0 0 0 1px rgb(124 58 237 / 0.12);
    box-sizing: border-box;
    transition: left 0.22s cubic-bezier(0.32, 0.72, 0, 1), width 0.22s cubic-bezier(0.32, 0.72, 0, 1);
    will-change: left, width;
  }
  .dark .cal-view-segment-thumb {
    background: rgb(79 70 229);
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.25);
  }
  .cal-view-segment-item {
    position: relative;
    z-index: 1;
    flex: 1 1 0;
    min-width: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.25rem;
    border: none;
    background: transparent;
    color: rgb(107 114 128);
    white-space: nowrap;
    border-radius: 9999px;
    cursor: inherit;
    transition: color 0.15s ease;
  }
  .dark .cal-view-segment-item { color: rgb(156 163 175); }
  .cal-view-segment-item[aria-selected="true"] {
    color: #5b21b6;
    font-weight: 700;
  }
  .dark .cal-view-segment-item[aria-selected="true"] {
    color: #fff;
    font-weight: 700;
  }
  .cal-icon-btn.cal-settings-btn {
    --cal-view-gap: 3px;
    width: auto;
    height: 100%;
    min-height: 2rem;
    aspect-ratio: 1;
    padding: var(--cal-view-gap);
    box-sizing: border-box;
    border-radius: 9999px;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
    color: rgb(107 114 128);
  }
  .cal-icon-btn.cal-settings-btn:hover {
    background: rgb(249 250 251);
  }
  .dark .cal-icon-btn.cal-settings-btn {
    border-color: rgb(58 61 66);
    background: rgb(41 42 46);
    color: rgb(156 163 175);
  }
  .dark .cal-icon-btn.cal-settings-btn:hover {
    background: rgb(48 50 55);
  }
  .cal-mini-cal__weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    text-align: center;
    font-size: 0.625rem;
    font-weight: 600;
    color: rgb(107 114 128);
    margin-bottom: 2px;
  }
  .dark .cal-mini-cal__weekdays { color: rgb(148 163 184); }
  .cal-mini-cal__days {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 0.6875rem;
    color: rgb(55 65 81);
  }
  .dark .cal-mini-cal__days { color: rgb(203 213 225); }
  .cal-mini-cal__row {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    position: relative;
    isolation: isolate;
  }
  .cal-mini-cal__row.is-week-active::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: var(--cal-radius-pill);
    background: rgba(156, 163, 175, 0.18);
    z-index: 0;
  }
  .dark .cal-mini-cal__row.is-week-active::before {
    background: rgba(156, 163, 175, 0.22);
  }
  .cal-mini-cal__row.is-week-active > span {
    position: relative;
    z-index: 1;
  }
  .cal-mini-cal__row > span {
    aspect-ratio: 1;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    display: grid;
    place-items: center;
    border-radius: 0.125rem;
    cursor: default;
    line-height: 1;
    text-align: center;
    position: relative;
    box-sizing: border-box;
  }
  .cal-mini-cal__day-num {
    display: block;
    line-height: 1;
    text-align: center;
    position: relative;
    z-index: 1;
  }
  .cal-mini-cal__row > span.has-events .cal-mini-cal__day-num {
    transform: translateY(-2px);
  }
  .cal-mini-cal__row > span.has-events::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 4px;
    margin: 0 auto;
    width: calc(3px + (var(--ec, 1) - 1) * 2.5px);
    height: 3px;
    border-radius: 1.5px;
    background: var(--cal-accent);
    pointer-events: none;
    z-index: 2;
    transition: width 0.2s ease;
  }
  .dark .cal-mini-cal__row > span.has-events::after {
    background: var(--cal-accent-dark);
  }
  .cal-mini-cal__row > span.is-today.has-events::after {
    background: #fff;
  }
  .cal-mini-cal__row > span.is-outside.has-events::after {
    opacity: 0.5;
  }
  .cal-mini-cal__row > span.is-outside { color: rgb(209 213 219); }
  .dark .cal-mini-cal__row > span.is-outside { color: rgb(71 85 105); }
  .cal-mini-cal__row > span.is-clickable { cursor: pointer; }
  .cal-mini-cal__row > span.is-clickable::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 88%;
    height: 88%;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    background: transparent;
    pointer-events: none;
    z-index: 0;
    transition: background 0.15s ease;
  }
  .cal-mini-cal__row > span.is-clickable:hover::before {
    background: rgba(0, 0, 0, 0.06);
  }
  .dark .cal-mini-cal__row > span.is-clickable:hover::before {
    background: rgba(255, 255, 255, 0.09);
  }
  .cal-mini-cal__row > span.is-clickable.is-today:hover::before,
  .cal-mini-cal__row > span.is-clickable.is-selected:hover::before {
    background: transparent;
  }
  .cal-mini-cal__row > span.is-today {
    border-radius: var(--cal-radius-pill);
    background: var(--cal-accent);
    color: #fff;
    font-weight: 600;
  }
  .dark .cal-mini-cal__row > span.is-today {
    background: var(--cal-accent-dark);
    color: #fff;
  }
  .cal-mini-cal__row > span.is-selected:not(.is-today) {
    border-radius: 9999px;
    background: rgb(229 231 235);
    color: rgb(17 24 39);
    font-weight: 600;
  }
  .dark .cal-mini-cal__row > span.is-selected:not(.is-today) {
    background: rgba(255, 255, 255, 0.18);
    color: rgb(241 245 249);
  }
  .cal-mini-cal__row > span.is-today.is-selected {
    background: var(--cal-accent);
    color: #fff;
  }
  .dark .cal-mini-cal__row > span.is-today.is-selected {
    background: var(--cal-accent-dark);
    color: #fff;
  }
  .cal-filter-list {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  .service-filter-dropdown-shadow {
    box-shadow: 0 10px 24px -4px rgb(0 0 0 / 0.12), 0 4px 8px -2px rgb(0 0 0 / 0.06);
  }
  .dark .service-filter-dropdown-shadow {
    box-shadow: 0 10px 24px -4px rgb(0 0 0 / 0.45), 0 4px 8px -2px rgb(0 0 0 / 0.25);
  }
  .cal-filter-item {
    display: grid;
    grid-template-columns: 0.75rem 1fr auto;
    align-items: center;
    gap: 0.4375rem 0.5625rem;
    padding: 0.4375rem 0.5rem;
    border-radius: var(--cal-radius-control);
    cursor: pointer;
    min-width: 0;
  }
  .cal-filter-item:hover {
    background: rgb(249 250 251);
  }
  .dark .cal-filter-item:hover {
    background: rgba(255,255,255,0.05);
  }
  .cal-filter-swatch {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 9999px;
    border: 1px solid rgba(0,0,0,0.12);
    padding: 0;
    flex-shrink: 0;
  }
  .cal-filter-label {
    font-size: 0.8125rem;
    color: rgb(55 65 81);
    min-width: 0;
  }
  .dark .cal-filter-label { color: rgb(203 213 225); }
  .cal-toggle-track--sm {
    position: relative;
    display: inline-flex;
    height: 1rem;
    width: 1.75rem;
    flex-shrink: 0;
    border-radius: 9999px;
    background: rgb(209 213 219);
    transition: background-color 0.15s;
    touch-action: none;
    user-select: none;
    -webkit-user-select: none;
    cursor: grab;
  }
  .cal-toggle-track--sm:active { cursor: grabbing; }
  .cal-toggle-track--sm.cal-toggle-track--dragging::after {
    transition: none;
    transform: translateX(var(--cal-thumb-offset, 0px)) !important;
  }
  .dark .cal-toggle-track--sm { background: rgb(51 65 85); }
  .cal-filter-item .peer:checked ~ .cal-toggle-track--sm {
    background: var(--cal-accent);
  }
  .dark .cal-filter-item .peer:checked ~ .cal-toggle-track--sm {
    background: var(--cal-accent-dark);
  }
  .cal-toggle-track--sm::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 9999px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0,0,0,0.15);
    transition: transform 0.15s;
  }
  .cal-filter-item .peer:checked ~ .cal-toggle-track--sm::after {
    transform: translateX(0.75rem);
  }
  .cal-sidebar-details {
    margin-top: 0;
    border-top: 1px solid rgb(243 244 246);
    padding-top: var(--cal-space-section);
  }
  .dark .cal-sidebar-details {
    border-color: rgba(255,255,255,0.06);
  }
  .cal-sidebar-details__summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(75 85 99);
    cursor: pointer;
    list-style: none;
    padding: 0.375rem 0.25rem;
    user-select: none;
  }
  .cal-sidebar-details__summary::-webkit-details-marker { display: none; }
  .dark .cal-sidebar-details__summary { color: rgb(148 163 184); }
  .cal-sidebar-details__count {
    font-size: 0.625rem;
    font-weight: 500;
    padding: 0.0625rem 0.375rem;
    border-radius: 9999px;
    background: rgb(243 244 246);
    color: rgb(107 114 128);
  }
  .dark .cal-sidebar-details__count {
    background: rgba(255,255,255,0.08);
    color: rgb(148 163 184);
  }
  .cal-sidebar-details__action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 0.5rem;
    color: rgb(107 114 128);
  }
  .cal-sidebar-details__action:hover {
    background: rgb(243 244 246);
    color: rgb(17 24 39);
  }
  .cal-sidebar-details[open] .cal-filter-list {
    max-height: 9rem;
    overflow-y: auto;
    margin-top: 0.375rem;
  }
  .cal-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--cal-radius-control);
    border: 1px solid rgb(209 213 219);
    background: #fff;
    color: rgb(55 65 81);
    flex-shrink: 0;
  }
  .cal-icon-btn:hover {
    background: rgb(249 250 251);
  }
  .dark .cal-icon-btn {
    border-color: rgba(255,255,255,0.12);
    background: rgb(30 41 59);
    color: rgb(203 213 225);
  }
  .dark .cal-icon-btn:hover {
    background: rgba(255,255,255,0.08);
  }
  .cal-calendar-panel {
    border-radius: var(--cal-radius-card);
    border: 1px solid rgb(229 231 235);
    background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
  }
  .dark .cal-calendar-panel {
    border-color: rgba(255,255,255,0.08);
    background: var(--color-primary-100, #0f172a);
  }

  @media (min-width: 1024px) {
    #calendar-layout {
      grid-template-columns: 260px minmax(0, 1fr) !important;
      transition: grid-template-columns 0.2s ease;
    }
    #calendar-sidebar {
      transition: opacity 0.2s ease, transform 0.2s ease;
    }
    #calendar-layout[data-sidebar-collapsed="true"] {
      grid-template-columns: 64px minmax(0, 1fr) !important;
    }
    #calendar-layout[data-sidebar-collapsed="true"] #calendar-sidebar {
      display: none;
    }
    #calendar-sidebar-collapsed {
      display: none;
    }
    #calendar-layout[data-sidebar-collapsed="true"] #calendar-sidebar-collapsed {
      display: flex;
    }
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day-top {
    justify-content: center;
    padding-top: 3px;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day-number {
    float: none !important;
    width: 1.75rem;
    height: 1.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    font-weight: 600;
    margin: 0 auto;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day-frame {
    min-height: 180px;
  }
  #calendar[data-current-view="dayGridMonth"] {
    --cal-month-event-height: 1.625rem;
    --cal-month-event-height-2l: 2.75rem;
    --cal-month-event-gap: 5px;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day-events {
    display: flex;
    flex-direction: column;
    gap: var(--cal-month-event-gap);
    position: relative;
    margin: 2px 4px 0;
    padding-bottom: 4px;
    overflow: visible;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event-harness::before,
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event-harness::after {
    display: none !important;
    content: none !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event-harness {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    overflow: visible !important;
    flex: 0 0 auto;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event-harness:not(.fc-daygrid-event-harness-abs) {
    position: relative !important;
    top: auto !important;
    left: auto !important;
    right: auto !important;
    height: auto !important;
    width: 100%;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event-harness + .fc-daygrid-event-harness {
    margin-top: 0 !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event,
  .fc-more-popover .fc-daygrid-event.cal-month-event {
    --cal-event-accent: var(--cal-accent);
    position: relative;
    isolation: isolate;
    margin: 0 !important;
    border: none !important;
    border-left: 3px solid var(--cal-event-accent) !important;
    border-radius: 8px !important;
    background: transparent !important;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
    min-height: var(--cal-month-event-height) !important;
    height: auto !important;
    max-height: none;
    flex-shrink: 0;
    width: calc(100% - 2px) !important;
    max-width: calc(100% - 2px) !important;
    align-self: stretch !important;
    cursor: pointer !important;
    opacity: 1 !important;
    transition: box-shadow 0.15s ease, transform 0.12s ease;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event::before,
  .fc-more-popover .fc-daygrid-event.cal-month-event::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 0;
    border-radius: inherit;
    background: var(--cal-event-accent);
    opacity: 0.15;
    pointer-events: none;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event::before,
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event::before {
    opacity: 0.28;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-start,
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-start {
    border-top-left-radius: 8px !important;
    border-bottom-left-radius: 8px !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-end,
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-end {
    border-top-right-radius: 8px !important;
    border-bottom-right-radius: 8px !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:not(.fc-event-start),
  .fc-more-popover .fc-daygrid-event.cal-month-event:not(.fc-event-start) {
    border-left-width: 0 !important;
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
    margin-left: 0 !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:not(.fc-event-end),
  .fc-more-popover .fc-daygrid-event.cal-month-event:not(.fc-event-end) {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-start:not(.fc-event-end),
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-start:not(.fc-event-end) {
    border-left-width: 0 !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-start:not(.fc-event-end)::before,
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:not(.fc-event-start)::before,
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-start:not(.fc-event-end)::before,
  .fc-more-popover .fc-daygrid-event.cal-month-event:not(.fc-event-start)::before {
    opacity: 0.2;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-start:not(.fc-event-end)::before,
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:not(.fc-event-start)::before,
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-start:not(.fc-event-end)::before,
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event:not(.fc-event-start)::before {
    opacity: 0.32;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event-harness:has(.cal-month-event:hover),
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event-harness:has(.cal-month-event:focus-visible) {
    z-index: 12;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:hover,
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:focus-visible,
  .fc-more-popover .fc-daygrid-event.cal-month-event:hover,
  .fc-more-popover .fc-daygrid-event.cal-month-event:focus-visible {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgb(15 23 42 / 0.14);
    z-index: 12;
    max-height: none !important;
    overflow: visible !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:hover::before,
  .fc-more-popover .fc-daygrid-event.cal-month-event:hover::before {
    opacity: 0.22;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:hover::before,
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event:hover::before {
    opacity: 0.36;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.cal-month-event--locked::before,
  .fc-more-popover .fc-daygrid-event.cal-month-event.cal-month-event--locked::before {
    opacity: 0.1;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.cal-month-event--locked::before,
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event.cal-month-event--locked::before {
    opacity: 0.18;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event .fc-event-main,
  .fc-more-popover .fc-daygrid-event.cal-month-event .fc-event-main {
    position: relative;
    z-index: 1;
    display: flex !important;
    align-items: center !important;
    height: auto !important;
    min-height: 0 !important;
    padding: 0.3125rem 0.5rem 0.3125rem 0.5625rem !important;
    overflow: visible !important;
    min-width: 0 !important;
    cursor: inherit !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:hover .fc-event-main,
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:focus-visible .fc-event-main,
  .fc-more-popover .fc-daygrid-event.cal-month-event:hover .fc-event-main {
    overflow: visible !important;
    align-items: center !important;
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__time,
  .fc-more-popover .cal-month-event__time {
    flex-shrink: 0;
    font-size: 0.6875rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
    line-height: 1.25;
    padding: 0;
    opacity: 1;
    color: rgb(30 41 59) !important;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .cal-month-event__time,
  .dark .fc-more-popover .cal-month-event__time {
    color: rgb(226 232 240) !important;
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__title,
  .fc-more-popover .cal-month-event__title {
    flex: 1;
    align-self: center;
    min-width: 0;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.35;
    overflow: hidden;
    white-space: normal;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    word-break: break-word;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:hover .cal-month-event__title,
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event:focus-visible .cal-month-event__title,
  .fc-more-popover .fc-daygrid-event.cal-month-event:hover .cal-month-event__title {
    display: block;
    -webkit-line-clamp: unset;
    overflow: visible;
    text-overflow: unset;
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__inner,
  .fc-more-popover .cal-month-event__inner {
    display: grid;
    grid-template-columns: auto auto minmax(0, 1fr);
    align-items: center;
    column-gap: 0.375rem;
    min-width: 0;
    width: 100%;
    line-height: 1.35;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-mein-kalender .cal-month-event__title,
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-invited .cal-month-event__title,
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-mein-kalender .cal-month-event__title,
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-invited .cal-month-event__title {
    font-size: 0.8125rem;
    line-height: 1.4;
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__inner:not(:has(.cal-todo-done)) {
    grid-template-columns: auto minmax(0, 1fr);
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__inner:not(:has(.cal-month-event__times)) {
    grid-template-columns: auto minmax(0, 1fr);
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__inner:not(:has(.cal-todo-done)):not(:has(.cal-month-event__times)) {
    grid-template-columns: minmax(0, 1fr);
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event .fc-event-main:has(.cal-month-event__times),
  .fc-more-popover .fc-daygrid-event.cal-month-event .fc-event-main:has(.cal-month-event__times) {
    padding-left: 0.625rem !important;
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__times,
  .fc-more-popover .cal-month-event__times {
    display: inline-flex;
    flex-direction: column;
    flex-shrink: 0;
    align-self: center;
    align-items: flex-start;
    justify-content: center;
    gap: 0;
    line-height: 1.25;
    padding: 0.125rem 0.1875rem 0.125rem 0.1875rem;
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__times--inline,
  .fc-more-popover .cal-month-event__times--inline {
    flex-direction: row;
    align-items: center;
    white-space: nowrap;
  }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__time--end,
  .fc-more-popover .cal-month-event__time--end {
    opacity: 0.55;
  }
  .cal-todo-done {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    align-self: center;
    cursor: pointer;
    line-height: 1;
  }
  .cal-todo-done__input {
    width: 1.125rem;
    height: 1.125rem;
    margin: 0;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    border-radius: 9999px;
    border: 2px solid rgb(100 116 139);
    background: #fff;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.14);
    flex-shrink: 0;
    touch-action: manipulation;
  }
  .cal-todo-done__input:checked {
    background-color: var(--cal-accent, #2563eb);
    border-color: var(--cal-accent, #2563eb);
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3ccircle cx='8' cy='8' r='3'/%3e%3c/svg%3e");
    background-size: 100% 100%;
    background-position: center;
    background-repeat: no-repeat;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.18);
  }
  .cal-todo-done__input:focus-visible {
    outline: 2px solid var(--cal-accent, #2563eb);
    outline-offset: 1px;
  }
  .dark .cal-todo-done__input {
    border-color: rgb(148 163 184);
    background: rgb(248 250 252);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.25);
  }
  .dark .cal-todo-done__input:checked {
    background-color: var(--cal-accent-dark, #7c8aff);
    border-color: var(--cal-accent-dark, #7c8aff);
  }
  .fc-timegrid-event .cal-todo-done__input,
  .fc-daygrid-event .cal-todo-done__input {
    width: 1.25rem;
    height: 1.25rem;
  }
  #calEventContextMenu button:hover,
  #calEventContextMenu .cal-event-ctx-goto-trigger:hover {
    background: rgb(243 244 246);
  }
  .dark #calEventContextMenu button:hover,
  .dark #calEventContextMenu .cal-event-ctx-goto-trigger:hover {
    background: rgb(55 56 60);
  }
  #calEventContextMenu .cal-event-ctx-submenu {
    display: none;
  }
  #calEventContextMenu .cal-event-ctx-submenu.cal-event-ctx-submenu--open {
    display: block;
  }
  /* Kein grauer Browser-Fokus-Rahmen bei Maus/Rechtsklick – nur Tastatur */
  #calendar .fc-event:focus {
    outline: none;
  }
  #calendar .fc-event:focus-visible {
    outline: 2px solid var(--cal-accent, #2563eb);
    outline-offset: 2px;
  }
  /* Rechtsklick-Hervorhebung (wie Ticket-Übersicht), statt Fokus-Rahmen */
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event-harness:has(.cal-month-event.cal-event-context-active) {
    z-index: 12;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.cal-event-context-active,
  .fc-more-popover .fc-daygrid-event.cal-month-event.cal-event-context-active {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgb(15 23 42 / 0.14);
    z-index: 12;
    max-height: none !important;
    overflow: visible !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.cal-event-context-active::before,
  .fc-more-popover .fc-daygrid-event.cal-month-event.cal-event-context-active::before {
    opacity: 0.22;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.cal-event-context-active::before,
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event.cal-event-context-active::before {
    opacity: 0.36;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.cal-event-context-active .fc-event-main,
  .fc-more-popover .fc-daygrid-event.cal-month-event.cal-event-context-active .fc-event-main {
    overflow: visible !important;
    align-items: center !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.cal-event-context-active .cal-month-event__title,
  .fc-more-popover .fc-daygrid-event.cal-month-event.cal-event-context-active .cal-month-event__title {
    display: block;
    -webkit-line-clamp: unset;
    overflow: visible;
    text-overflow: unset;
  }
  .fc-timegrid-event.cal-event-context-active,
  .fc-event-editable:not(.cal-month-event).cal-event-context-active {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25) !important;
    transform: translateY(-1px);
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day-bottom {
    display: flex;
    justify-content: center;
    margin-top: 2px;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-more-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 4px;
    padding: 0.25rem 0.625rem;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.2;
    border-radius: 9999px;
    color: var(--cal-accent) !important;
    background: color-mix(in srgb, var(--cal-accent) 10%, #fff);
    transition: background 0.15s ease, color 0.15s ease;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-more-link:hover {
    background: color-mix(in srgb, var(--cal-accent) 18%, #fff);
    color: var(--cal-accent-hover) !important;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-more-link {
    color: var(--cal-accent-dark-hover) !important;
    background: color-mix(in srgb, var(--cal-accent-dark) 22%, rgb(30 41 59));
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-more-link:hover {
    background: color-mix(in srgb, var(--cal-accent-dark) 32%, rgb(30 41 59));
  }
  @keyframes cal-popover-in {
    from { opacity: 0; transform: translateY(6px) scale(0.94); }
    to { opacity: 1; transform: translateY(0) scale(1); }
  }
  .fc-more-popover {
    border-radius: 14px !important;
    border: 1px solid rgb(226 232 240) !important;
    box-shadow: 0 25px 50px -12px rgb(15 23 42 / 0.2), 0 8px 20px -4px rgb(15 23 42 / 0.1), 0 0 0 1px rgb(15 23 42 / 0.03) !important;
    overflow: hidden !important;
    min-width: 260px !important;
    max-width: 320px !important;
    animation: cal-popover-in 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    background: #fff !important;
  }
  .dark .fc-more-popover {
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.6), 0 8px 20px -4px rgb(0 0 0 / 0.4) !important;
    background: rgb(30 32 38) !important;
  }
  .fc-more-popover .fc-popover-header {
    padding: 0.75rem 1rem !important;
    font-size: 0.875rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.01em;
    background: linear-gradient(to bottom, rgb(248 250 252), rgb(241 245 249)) !important;
    border-bottom: 1px solid rgb(226 232 240) !important;
    color: rgb(30 41 59) !important;
  }
  .dark .fc-more-popover .fc-popover-header {
    background: linear-gradient(to bottom, rgb(38 40 44), rgb(34 36 40)) !important;
    border-bottom-color: rgba(255, 255, 255, 0.06) !important;
    color: rgb(226 232 240) !important;
  }
  .fc-more-popover .fc-popover-header .fc-popover-close {
    width: 1.625rem !important;
    height: 1.625rem !important;
    border-radius: 7px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background 0.15s ease, opacity 0.15s ease !important;
    opacity: 0.5;
  }
  .fc-more-popover .fc-popover-header .fc-popover-close:hover {
    background: rgb(226 232 240) !important;
    opacity: 1 !important;
  }
  .dark .fc-more-popover .fc-popover-header .fc-popover-close:hover {
    background: rgba(255, 255, 255, 0.1) !important;
  }
  .fc-more-popover .fc-popover-body {
    padding: 0.625rem 0.75rem !important;
    max-height: 20rem !important;
    overflow-y: auto !important;
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
  }
  .dark .fc-more-popover .fc-popover-body {
    scrollbar-color: rgb(71 85 105) transparent;
  }
  .fc-more-popover .fc-popover-body::-webkit-scrollbar {
    width: 5px;
  }
  .fc-more-popover .fc-popover-body::-webkit-scrollbar-track {
    background: transparent;
  }
  .fc-more-popover .fc-popover-body::-webkit-scrollbar-thumb {
    background: rgb(203 213 225);
    border-radius: 9999px;
  }
  .dark .fc-more-popover .fc-popover-body::-webkit-scrollbar-thumb {
    background: rgb(71 85 105);
  }
  .fc-more-popover .fc-daygrid-event-harness {
    margin-top: 0 !important;
  }
  .fc-more-popover .fc-daygrid-event-harness + .fc-daygrid-event-harness {
    margin-top: 8px !important;
  }
  .fc-more-popover .fc-daygrid-event.cal-month-event {
    min-height: 2rem !important;
    height: auto !important;
    max-height: none !important;
    width: 100% !important;
    max-width: 100% !important;
    border-radius: 9px !important;
    padding: 0.25rem 0 !important;
    transition: transform 0.12s ease, box-shadow 0.15s ease !important;
  }
  .fc-more-popover .fc-daygrid-event.cal-month-event:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 16px rgb(15 23 42 / 0.14) !important;
    z-index: 12 !important;
  }
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event:hover {
    box-shadow: 0 6px 16px rgb(0 0 0 / 0.4) !important;
  }
  .fc-more-popover .fc-daygrid-event.cal-month-event:hover::before {
    opacity: 0.22 !important;
  }
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event:hover::before {
    opacity: 0.36 !important;
  }
  .fc-more-popover .cal-month-event__title {
    display: block;
    -webkit-line-clamp: unset;
    overflow: visible;
    font-size: 0.8125rem !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-body {
    min-height: 900px;
  }
  /* Kalender-Tooltip: Typografie/Farben wie Ticket-Tabelle (tickets/index.php) */
  .cal-month-event-tooltip {
    position: fixed;
    z-index: 250;
    max-width: min(22rem, calc(100vw - 1.5rem));
    padding: 0;
    font-size: 0.875rem;
    font-weight: 400;
    line-height: 1.25;
    color: rgb(107 114 128);
    background: #fff;
    border: 1px solid rgb(229 231 235);
    border-radius: 0.75rem;
    box-shadow: 0 10px 24px -4px rgb(0 0 0 / 0.12), 0 4px 8px -2px rgb(0 0 0 / 0.06);
    pointer-events: none;
    word-break: break-word;
    overflow: hidden;
  }
  .cal-month-event-tooltip.cal-event-tooltip--rich {
    padding: 0;
  }
  .cal-event-tooltip__inner {
    position: relative;
    padding: 0.75rem 0.875rem;
  }
  .cal-event-tooltip__inner--service::before,
  .cal-event-tooltip__inner--rich::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--cal-tooltip-accent, #3b82f6);
    border-radius: 0.75rem 0 0 0.75rem;
  }
  .cal-event-tooltip__header {
    margin-bottom: 0.375rem;
    padding-left: 0.125rem;
  }
  .cal-event-tooltip__header .cal-event-tooltip__ticket-title {
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1.35;
  }
  .cal-event-tooltip__ticket-title {
    display: block;
    font-size: 1rem;
    font-weight: 500;
    line-height: 1.375;
    color: rgb(17 24 39);
  }
  .dark .cal-event-tooltip__ticket-title {
    color: #d1d5db;
  }
  .cal-event-tooltip__ticket-block {
    margin-bottom: 0.625rem;
    padding: 0.5rem 0.625rem;
    background: rgb(249 250 251);
    border: 1px solid rgb(243 244 246);
    border-radius: 0.625rem;
  }
  .dark .cal-event-tooltip__ticket-block {
    background: rgb(50 51 55);
    border-color: rgba(58, 61, 66, 0.6);
  }
  .cal-event-tooltip__ticket-betreff {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.35;
    color: rgb(17 24 39);
    word-break: break-word;
  }
  .dark .cal-event-tooltip__ticket-betreff {
    color: #d1d5db;
  }
  .cal-event-tooltip__ticket-block .ticket-nummer-meta {
    margin-top: 0.125rem;
  }
  .cal-event-tooltip__details {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    padding-top: 0.625rem;
    border-top: 1px solid rgb(243 244 246);
  }
  .dark .cal-event-tooltip__details {
    border-top-color: rgba(58, 61, 66, 0.6);
  }
  .cal-event-tooltip__meta-stack {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
  }
  .cal-event-tooltip__meta-grid {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }
  .cal-event-tooltip__meta-card {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    padding: 0.5rem 0.625rem;
    background: rgb(249 250 251);
    border: 1px solid rgb(243 244 246);
    border-radius: 0.625rem;
  }
  .dark .cal-event-tooltip__meta-card {
    background: rgb(50 51 55);
    border-color: rgba(58, 61, 66, 0.6);
  }
  .cal-event-tooltip__meta-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    margin-top: 0.0625rem;
    border-radius: 0.5rem;
    background: #fff;
    color: rgb(107 114 128);
    border: 1px solid rgb(229 231 235);
    flex-shrink: 0;
  }
  .dark .cal-event-tooltip__meta-icon {
    background: rgb(58 61 66);
    border-color: rgba(255, 255, 255, 0.08);
    color: rgb(156 163 175);
  }
  .cal-event-tooltip__meta-icon svg {
    width: 0.875rem;
    height: 0.875rem;
  }
  .cal-event-tooltip__meta-card--company-solo {
    align-items: center;
  }
  .cal-event-tooltip__meta-card--company-solo .cal-event-tooltip__meta-icon {
    margin-top: 0;
  }
  .cal-event-tooltip__meta-card-body {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    min-width: 0;
    flex: 1;
  }
  .cal-event-tooltip__meta-card-body .ticket-nummer-meta {
    margin-top: 0.0625rem;
  }
  .cal-event-tooltip__meta-card-value {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.35;
    color: rgb(17 24 39);
    word-break: break-word;
  }
  .dark .cal-event-tooltip__meta-card-value {
    color: #d1d5db;
  }
  .cal-event-tooltip__meta-card-secondary {
    font-size: 0.75rem;
    font-weight: 400;
    line-height: 1.25;
    color: rgb(107 114 128);
    word-break: break-word;
  }
  .dark .cal-event-tooltip__meta-card-secondary {
    color: rgb(156 163 175);
  }
  .cal-event-tooltip__assignee {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4375rem 0.625rem;
    background: rgb(249 250 251);
    border: 1px solid rgb(243 244 246);
    border-radius: 0.625rem;
    min-width: 0;
  }
  .dark .cal-event-tooltip__assignee {
    background: rgb(50 51 55);
    border-color: rgba(58, 61, 66, 0.6);
  }
  .cal-event-tooltip__assignee svg {
    width: 0.875rem;
    height: 0.875rem;
    flex-shrink: 0;
    color: rgb(107 114 128);
  }
  .dark .cal-event-tooltip__assignee svg {
    color: rgb(156 163 175);
  }
  .cal-event-tooltip__assignee-label {
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgb(107 114 128);
    flex-shrink: 0;
  }
  .dark .cal-event-tooltip__assignee-label {
    color: rgb(156 163 175);
  }
  .cal-event-tooltip__assignee-name {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.25;
    color: rgb(17 24 39);
    min-width: 0;
    word-break: break-word;
  }
  .dark .cal-event-tooltip__assignee-name {
    color: #d1d5db;
  }
  .cal-event-tooltip__meta-group {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    min-width: 0;
  }
  .cal-event-tooltip__meta-primary {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.25;
    color: rgb(17 24 39);
    word-break: break-word;
  }
  .dark .cal-event-tooltip__meta-primary {
    color: #d1d5db;
  }
  .cal-event-tooltip__meta-secondary {
    font-size: 0.75rem;
    font-weight: 400;
    line-height: 1.25;
    color: rgb(107 114 128);
    word-break: break-word;
  }
  .dark .cal-event-tooltip__meta-secondary {
    color: rgb(156 163 175);
  }
  .cal-month-event-tooltip[hidden] {
    display: none !important;
  }
  .dark .cal-month-event-tooltip {
    color: rgb(156 163 175);
    background: rgb(41 42 46);
    border-color: rgb(58 61 66);
    box-shadow: 0 10px 24px -4px rgb(0 0 0 / 0.45), 0 4px 8px -2px rgb(0 0 0 / 0.25);
  }
  /* Ticket-Nummer wie tickets/index.php (.ticket-nummer-meta) */
  .cal-month-event-tooltip .ticket-nummer-meta {
    font-size: 0.625rem;
    font-weight: 400;
    line-height: 1.25;
    color: #888888;
  }
  .dark .cal-month-event-tooltip .ticket-nummer-meta {
    color: #9a9a9a;
  }
  
  /* Kalender-Container Höhe fixieren für internes Scrolling */
  #calendar {
    height: 100% !important;
  }
  #calendar .fc {
    height: 100% !important;
  }
  #calendar .fc-view-harness {
    height: 100% !important;
  }
  
  /* Andere Ansichten: 90% breit links, Text umbrechen */
  .fc-timegrid-event, .fc-list-event {
    cursor: pointer;
    border-radius: var(--cal-radius-control);
    padding: 2px 6px;
    width: 90% !important;
    max-width: 90% !important;
    left: 0 !important;
    right: auto !important;
    min-height: 2em !important;
  }
  .fc-timegrid-event .fc-event-main, .fc-list-event .fc-event-main {
    padding: 2px 4px;
    min-height: inherit !important;
  }
  .fc-event .fc-event-main-frame { min-height: 1.5em; overflow: hidden; min-width: 0; }
  #calendar[data-current-view="dayGridMonth"] .cal-month-event .fc-event-main-frame,
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__inner,
  .fc-more-popover .cal-month-event .fc-event-main-frame,
  .fc-more-popover .cal-month-event__inner {
    min-height: 0 !important;
    height: auto !important;
  }
  .fc-event .fc-event-main { overflow: hidden; min-width: 0; }
  .fc-event .fc-event-title {
    font-weight: 500;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    min-width: 0 !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event .fc-event-title,
  #calendar[data-current-view="dayGridMonth"] .cal-month-event__title {
    white-space: normal !important;
    text-overflow: unset !important;
  }
  .fc-event .fc-event-service-info {
    overflow: hidden;
    min-width: 0;
  }
  .fc-event .fc-event-service-info > div {
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
  }
  .fc-event .fc-event-badge { font-size: 10px; opacity: 0.9; }
  .fc-scrollgrid { border-color: rgba(255,255,255,0.06); }

  /* Erreichbarkeit: dezente Markierung, Rest des Tages unverändert sichtbar */
  #calendar[data-current-view="timeGridWeek"] .fc-non-business,
  #calendar[data-current-view="timeGridDay"] .fc-non-business {
    background: transparent !important;
    opacity: 1 !important;
  }
  #calendar[data-current-view="timeGridWeek"] .fc-timegrid-col-bg .fc-bg-event,
  #calendar[data-current-view="timeGridDay"] .fc-timegrid-col-bg .fc-bg-event {
    background-color: var(--cal-accent-soft) !important;
    opacity: 1 !important;
  }
  .dark #calendar[data-current-view="timeGridWeek"] .fc-timegrid-col-bg .fc-bg-event,
  .dark #calendar[data-current-view="timeGridDay"] .fc-timegrid-col-bg .fc-bg-event {
    background-color: var(--cal-accent-soft-dark) !important;
  }
  
  /* Mein Kalender: eigene Termine farblich hervorheben */
  .fc-event-mein-kalender {
    border-width: 1.5px !important;
    box-shadow: 0 1px 3px rgba(124, 58, 237, 0.28) !important;
    font-weight: 600 !important;
  }
  
  /* Nicht verschiebbare Termine: Cursor + optische Kennzeichnung (nicht in Monatsansicht) */
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-not-editable,
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-not-editable {
    border-style: none !important;
    border-left-style: solid !important;
    border-width: 0 0 0 3px !important;
    opacity: 1 !important;
    cursor: pointer !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-editable,
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-editable {
    cursor: pointer !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event.fc-event-editable:hover,
  .fc-more-popover .fc-daygrid-event.cal-month-event.fc-event-editable:hover {
    transform: translateY(-1px);
  }
  .fc-event-not-editable:not(.cal-month-event) {
    cursor: not-allowed !important;
    opacity: 0.75 !important;
    border-style: dashed !important;
    border-width: 1.5px !important;
  }
  .fc-event-not-editable .fc-event-main {
    cursor: not-allowed !important;
  }
  .fc-event-not-editable .fc-event-lock-icon {
    flex-shrink: 0;
    width: 11px;
    height: 11px;
    opacity: 0.9;
    margin-left: 3px;
    color: rgba(255,255,255,0.95);
    filter: drop-shadow(0 1px 1px rgba(0,0,0,0.3));
  }
  /* Editierbare Termine: deutlich hervorheben (nicht in Monatsansicht) */
  .fc-event-editable:not(.cal-month-event) {
    cursor: grab !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15) !important;
  }
  .fc-event-editable:not(.cal-month-event):hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.25) !important;
    transform: translateY(-1px);
    transition: all 0.15s ease;
  }
  .fc-event-editable:not(.cal-month-event) .fc-event-main {
    cursor: grab !important;
  }
  /* Resize-Griff unten bei editierbaren Terminen (Zeitslot) sichtbar halten */
  .fc-timegrid-event.fc-event-editable .fc-event-main {
    padding-bottom: 10px !important;
  }
  .fc-timegrid-event.fc-event-editable .fc-event-resizer {
    cursor: ns-resize !important;
    height: 10px !important;
    min-height: 10px !important;
  }
  /* Status-Icon (Schloss/Verschieben) */
  .fc-event-status-icon {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
  }
  .fc-event-status-icon svg {
    display: block;
  }
  .fc-daygrid-event .fc-event-status-icon {
    width: 11px;
    height: 11px;
  }
  
  /* Bessere Farbkontraste für Dark Mode (Wochen-/Tagesansicht) */
  .dark #calendar[data-current-view="timeGridWeek"] .fc-daygrid-event,
  .dark #calendar[data-current-view="timeGridDay"] .fc-daygrid-event,
  .dark .fc-timegrid-event,
  .dark .fc-list-event {
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
  }
  .dark #calendar[data-current-view="timeGridWeek"] .fc-event-title,
  .dark #calendar[data-current-view="timeGridDay"] .fc-event-title,
  .dark .fc-timegrid-event .fc-event-title,
  .dark .fc-list-event .fc-event-title {
    color: rgba(255,255,255,0.98) !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event,
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event .fc-event-title,
  .fc-more-popover .fc-daygrid-event.cal-month-event,
  .fc-more-popover .fc-daygrid-event.cal-month-event .fc-event-title,
  .fc-more-popover .cal-month-event__title,
  .fc-more-popover .cal-month-event__time {
    color: rgb(30 41 59) !important;
    text-shadow: none;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event,
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-event.cal-month-event .fc-event-title,
  .dark #calendar[data-current-view="dayGridMonth"] .cal-month-event__time,
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event,
  .dark .fc-more-popover .fc-daygrid-event.cal-month-event .fc-event-title,
  .dark .fc-more-popover .cal-month-event__title,
  .dark .fc-more-popover .cal-month-event__time {
    color: rgb(226 232 240) !important;
  }
  /* Kalender-Farbauswahl */
  .cal-color-btn {
    cursor: pointer;
    transition: transform 0.12s ease, box-shadow 0.12s ease;
  }
  .cal-color-btn:hover {
    transform: scale(1.25);
    box-shadow: 0 0 0 2px rgba(0,0,0,0.08);
    border-radius: 9999px;
  }
  #cal-color-dropdown {
    position: fixed;
    z-index: 60;
    background: #fff;
    border: 1px solid rgb(229 231 235);
    border-radius: 1rem;
    box-shadow: 0 20px 40px -8px rgb(0 0 0 / 0.12), 0 8px 16px -4px rgb(0 0 0 / 0.06);
    padding: 0.75rem;
    width: 210px;
  }
  .dark #cal-color-dropdown {
    background: rgb(30 41 59);
    border-color: rgb(51 65 85);
    box-shadow: 0 20px 40px -8px rgb(0 0 0 / 0.4), 0 8px 16px -4px rgb(0 0 0 / 0.25);
  }
  #cal-color-dropdown > p {
    margin: 0 0 0.5rem 0.125rem;
  }
  .cal-color-options-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 4px;
  }
  .cal-color-option {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    border: 2.5px solid transparent;
    cursor: pointer;
    transition: transform 0.1s ease, border-color 0.1s ease, box-shadow 0.1s ease;
    position: relative;
    padding: 0;
  }
  .cal-color-option:hover {
    transform: scale(1.15);
    border-color: rgba(255,255,255,0.5);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    z-index: 1;
  }
  .cal-color-option.cal-color-option--active {
    border-color: #fff;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.25);
    transform: scale(1.1);
  }
  .dark .cal-color-option.cal-color-option--active {
    border-color: rgba(255,255,255,0.85);
    box-shadow: 0 0 0 2px rgba(255,255,255,0.15);
  }
  .cal-color-option--active::after {
    content: '';
    width: 10px;
    height: 10px;
    background: #fff;
    border-radius: 9999px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
  }
  /* Ausgewählter Tag im Kalender */
  .fc-day-selected,
  #calendar .fc-daygrid-day.fc-day-selected {
    background: rgba(124, 58, 237, 0.04) !important;
    box-shadow: inset 0 0 0 1px rgba(124, 58, 237, 0.15);
  }
  .dark .fc-day-selected,
  .dark #calendar .fc-daygrid-day.fc-day-selected {
    background: rgba(139, 92, 246, 0.06) !important;
    box-shadow: inset 0 0 0 1px rgba(167, 139, 250, 0.2);
  }
  
  /* Zeitslot-Markierung in Zeit-Ansichten */
  .fc-timegrid-col.fc-col-selected {
    position: relative;
  }
  .fc-timegrid-col.fc-col-selected::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: var(--highlight-top, 0);
    height: var(--highlight-height, 0);
    background: var(--cal-accent-soft);
    border: 2px solid var(--cal-accent-border);
    border-radius: var(--cal-radius-control);
    pointer-events: none;
    z-index: 1;
  }
  .dark .fc-timegrid-col.fc-col-selected::before {
    background: var(--cal-accent-soft-dark);
    border-color: var(--cal-accent-border-dark);
  }
  
  /* Dark Mode: Wochentage und Datumszahlen sichtbar machen */
  .dark .fc-col-header-cell {
    color: rgb(226 232 240) !important; /* primary-210 / gray-200 */
  }
  .dark .fc-col-header-cell-cushion {
    color: rgb(226 232 240) !important;
  }
  .dark .fc-daygrid-day-number {
    color: rgb(226 232 240) !important; /* primary-210 / gray-200 */
  }
  .dark .fc-daygrid-day-top {
    color: rgb(226 232 240) !important;
  }
  
  /* Uhrzeiten in Tages- und Wochenansicht lesbar (nicht schwarz auf schwarz) */
  .fc .fc-timegrid-slot-label,
  .fc .fc-timegrid-slot-label-cushion {
    color: rgb(55 65 81) !important; /* gray-700 */
  }
  .dark .fc .fc-timegrid-slot-label,
  .dark .fc .fc-timegrid-slot-label-cushion {
    color: rgb(226 232 240) !important; /* primary-210 */
  }
  /* Tagesansicht: mehr Luft + bessere Orientierung, mit Scrollen */
  #calendar[data-current-view="timeGridDay"] .fc-timegrid-slot {
    border-top-color: rgba(148, 163, 184, 0.28);
  }
  .dark #calendar[data-current-view="timeGridDay"] .fc-timegrid-slot {
    border-top-color: rgba(148, 163, 184, 0.2);
  }
  #calendar[data-current-view="timeGridDay"] .fc .fc-timegrid-slot-label-cushion {
    font-size: 12px;
    font-weight: 600;
  }
  #calendar[data-current-view="timeGridDay"] .fc-timegrid-body {
    min-height: 1500px;
  }
  
  /* Aktuelle-Zeit-Linie in Tages- und Wochenansicht (feiner Strich) */
  .fc .fc-timegrid-now-indicator-line {
    border-color: var(--fc-now-indicator-color, #ef4444);
    border-width: 1px 0 0;
  }
  .fc .fc-timegrid-now-indicator-arrow {
    border-top-color: var(--fc-now-indicator-color, #ef4444);
    border-bottom-color: var(--fc-now-indicator-color, #ef4444);
  }
  .dark .fc .fc-timegrid-now-indicator-line {
    border-color: rgb(248 113 113);
  }
  .dark .fc .fc-timegrid-now-indicator-arrow {
    border-top-color: rgb(248 113 113);
    border-bottom-color: rgb(248 113 113);
  }
  
  /* Tagesansicht: keine Hintergrundfarbe für den aktuellen Tag */
  .fc-timegrid .fc-col-header-cell.fc-day-today,
  .fc-timegrid .fc-timegrid-col.fc-day-today,
  .fc-timegrid .fc-day-today {
    background: transparent !important;
  }
  /* Wochenansicht: aktuellen Tag dezent hervorheben */
  #calendar[data-current-view="timeGridWeek"] .fc-timegrid .fc-col-header-cell.fc-day-today,
  #calendar[data-current-view="timeGridWeek"] .fc-timegrid .fc-timegrid-col.fc-day-today {
    background: var(--cal-accent-soft) !important;
  }
  .dark #calendar[data-current-view="timeGridWeek"] .fc-timegrid .fc-col-header-cell.fc-day-today,
  .dark #calendar[data-current-view="timeGridWeek"] .fc-timegrid .fc-timegrid-col.fc-day-today {
    background: var(--cal-accent-soft-dark) !important;
  }
  /* Monatsansicht: keinen Hintergrund für den aktuellen Tag */
  #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day.fc-day-today {
    background: transparent !important;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-daygrid-day.fc-day-today {
    background: transparent !important;
  }
  #calendar[data-current-view="dayGridMonth"] .fc-day-today .fc-daygrid-day-number {
    background: var(--cal-accent) !important;
    color: #fff !important;
  }
  .dark #calendar[data-current-view="dayGridMonth"] .fc-day-today .fc-daygrid-day-number {
    background: var(--cal-accent-dark) !important;
    color: #fff !important;
  }
</style>
<!-- Farb-Dropdown (einmal pro Seite) -->
<div id="cal-color-dropdown" class="hidden" role="dialog" aria-label="Farbe wählen">
  <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Farbe wählen</p>
  <div id="cal-color-options" class="cal-color-options-grid"></div>
</div>
<script>
(function() {
  const baseUrl = <?php echo json_encode($baseUrl); ?>;
  const apiBase = (baseUrl.replace(/\/$/, '') || '') + '/kalender/api';
  const isAdminOrTechniker = <?php echo $isAdminOrTechniker ? 'true' : 'false'; ?>;
  const calendarColleagues = <?php echo json_encode($calendarColleagues); ?>;
  const calendarAssignees = <?php echo json_encode($calendarAssignees); ?>;
  const calendarBusinessHours = <?php echo json_encode($calendarBusinessHours); ?>;
  const CALENDAR_FULL_DAY = { slotMinTime: '00:00:00', slotMaxTime: '24:00:00' };

  let selectedDate = null;
  let lastClickedDate = null;
  let lastClickTime = 0;
  var subscriptionEventsCache = {}; // Cache für Subscription-Events
  var STORAGE_KEY = 'kalender_filters';
  var STORAGE_KEY_COLORS = 'kalender_colors';
  var STORAGE_KEY_SETTINGS = 'kalender_settings';
  var STORAGE_KEY_UI = 'kalender_ui';

  var DEFAULT_CAL_COLORS = {
    my_calendar: '#8b5cf6',
    other_calendar: '#6366f1',
    service: '#3b82f6',
    todos: '#a855f7',
    orders: '#7c3aed',
    my_vacation: '#06b6d4',
    my_times: '#6366f1',
    colleagues_vacation: '#64748b'
  };

  var PREDEFINED_COLORS = [
    '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e',
    '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1',
    '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e', '#be185d',
    '#78716c', '#64748b', '#475569', '#0891b2', '#059669', '#4f46e5',
    '#7c3aed', '#c026d3', '#db2777', '#dc2626', '#ea580c', '#ca8a04'
  ];

  function getCalendarColors() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY_COLORS);
      var saved = raw ? JSON.parse(raw) : {};
      var out = {};
      for (var k in DEFAULT_CAL_COLORS) {
        out[k] = (saved[k] && /^#[0-9a-fA-F]{6}$/.test(saved[k])) ? saved[k] : DEFAULT_CAL_COLORS[k];
      }
      return out;
    } catch (e) { return Object.assign({}, DEFAULT_CAL_COLORS); }
  }

  function getEventColorKey(ev) {
    var props = (ev.extendedProps || {});
    var src = props.source;
    if (src === 'custom') return (props.my_calendar || props.is_owner) ? 'my_calendar' : 'other_calendar';
    if (src === 'service') return 'service';
    if (src === 'todos') return 'todos';
    if (src === 'orders') return 'orders';
    if (src === 'my_vacation') return 'my_vacation';
    if (src === 'my_times') return 'my_times';
    if (src === 'colleagues_vacation') return 'colleagues_vacation';
    if (src === 'other_users') return 'other_calendar';
    return 'my_calendar';
  }

  function applyEventColors(events) {
    var colors = getCalendarColors();
    return (events || []).map(function(ev) {
      // Subscription-Events behalten ihre eigene Farbe
      if (ev.extendedProps && ev.extendedProps.source === 'subscription') {
        // Farbe ist bereits im Event gesetzt
        return ev;
      }
      
      var key = getEventColorKey(ev);
      var hex = colors[key] || DEFAULT_CAL_COLORS.my_calendar;
      ev.backgroundColor = hex;
      ev.borderColor = hex;
      ev.textColor = '#ffffff';
      return ev;
    });
  }

  function saveCalendarColor(key, hex) {
    try {
      var saved = {};
      var raw = localStorage.getItem(STORAGE_KEY_COLORS);
      if (raw) saved = JSON.parse(raw);
      saved[key] = hex;
      localStorage.setItem(STORAGE_KEY_COLORS, JSON.stringify(saved));
    } catch (e) {}
  }

  function applySavedColors() {
    var colors = getCalendarColors();
    document.querySelectorAll('.cal-color-btn').forEach(function(btn) {
      var key = btn.getAttribute('data-color-key');
      if (key && colors[key]) btn.style.backgroundColor = colors[key];
    });
  }

  function getNavCompanyId() {
    var el = document.getElementById('nav-company-selector-desktop');
    if (el) {
      var id = parseInt(el.getAttribute('data-company-id'), 10);
      if (id > 0) return id;
    }
    return 0;
  }

  function getFilters() {
    if (!isAdminOrTechniker) return { service: 1 };
    var f = {};
    document.querySelectorAll('input[name="cal_filter"]:checked').forEach(function(c) { f[c.value] = 1; });
    var otherChecks = document.querySelectorAll('input.cal-other-user-cb:checked, input[name="cal_other_user[]"]:checked');
    if (otherChecks.length) {
      f.other_user_ids = Array.from(otherChecks).map(function(c) { return parseInt(c.value, 10); });
    }
    var companyId = getNavCompanyId();
    if (companyId) f.company_id = companyId;
    return f;
  }

  function getFiltersForStorage() {
    var state = {};
    document.querySelectorAll('input[name="cal_filter"]').forEach(function(c) {
      state[c.value] = c.checked;
    });
    var otherChecks = document.querySelectorAll('input.cal-other-user-cb, input[name="cal_other_user[]"]');
    state.other_user_ids = Array.from(otherChecks).filter(function(c) { return c.checked; }).map(function(c) { return parseInt(c.value, 10); });
    return state;
  }

  function saveFiltersToStorage() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(getFiltersForStorage()));
    } catch (e) {}
  }

  function applySavedFilters() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      var state = JSON.parse(raw);
      if (!state || typeof state !== 'object') return;
      document.querySelectorAll('input[name="cal_filter"]').forEach(function(c) {
        if (state[c.value] === true) c.checked = true;
        else if (state[c.value] === false) c.checked = false;
      });
      document.querySelectorAll('input.cal-other-user-cb, input[name="cal_other_user[]"]').forEach(function(c) {
        var id = parseInt(c.value, 10);
        var ids = state.other_user_ids;
        c.checked = Array.isArray(ids) && ids.indexOf(id) !== -1;
      });
    } catch (e) {}
  }

  function positionCalFilterDropdown(menuEl, buttonEl, options) {
    if (!menuEl || !buttonEl || menuEl.classList.contains('hidden')) return;
    var rect = buttonEl.getBoundingClientRect();
    var vh = window.innerHeight;
    var vw = window.innerWidth;
    var gap = 4;
    var maxMenuH = 320;
    var spaceBelow = vh - rect.bottom - gap;
    var spaceAbove = rect.top - gap;
    var openAbove = spaceBelow < maxMenuH && spaceAbove > spaceBelow;
    menuEl.style.position = 'fixed';
    menuEl.style.marginTop = '0';
    menuEl.style.marginBottom = '0';
    var minW = (options && options.minWidth) ? options.minWidth : 0;
    var menuW = Math.max(rect.width, minW);
    menuEl.style.width = menuW + 'px';
    menuEl.style.minWidth = '';
    menuEl.style.maxWidth = '';
    var left;
    if (options && options.alignCenter) {
      left = rect.left + (rect.width - menuW) / 2;
    } else if (options && options.alignRight) {
      left = rect.right - menuW;
    } else {
      left = rect.left;
    }
    if (left + menuW > vw) left = vw - menuW;
    if (left < 0) left = 0;
    menuEl.style.left = left + 'px';
    if (openAbove) {
      menuEl.style.bottom = (vh - rect.top + gap) + 'px';
      menuEl.style.top = 'auto';
      menuEl.style.maxHeight = Math.min(maxMenuH, spaceAbove) + 'px';
    } else {
      menuEl.style.top = (rect.bottom + gap) + 'px';
      menuEl.style.bottom = 'auto';
      menuEl.style.maxHeight = Math.min(maxMenuH, spaceBelow) + 'px';
    }
  }

  function openCalFilterDropdownPortal(menuEl, buttonEl, options) {
    if (!menuEl || !buttonEl) return;
    if (!menuEl._dropdownRestore) {
      menuEl._dropdownRestore = { parent: menuEl.parentNode, nextSibling: menuEl.nextSibling };
      document.body.appendChild(menuEl);
    }
    menuEl.classList.remove('hidden');
    setTimeout(function() { positionCalFilterDropdown(menuEl, buttonEl, options); }, 10);
  }

  function closeCalFilterDropdownPortal(menuEl, containerEl) {
    if (!menuEl) return;
    menuEl.classList.add('hidden');
    if (menuEl._dropdownRestore) {
      var parent = menuEl._dropdownRestore.parent;
      var nextSibling = menuEl._dropdownRestore.nextSibling;
      if (parent) {
        if (nextSibling) parent.insertBefore(menuEl, nextSibling);
        else parent.appendChild(menuEl);
      }
      menuEl._dropdownRestore = null;
    }
  }

  function calToggleTrackTravel(track) {
    return Math.max(0, track.clientWidth - 12 - 4);
  }

  function calSetToggleThumbPosition(track, offsetPx) {
    track.style.setProperty('--cal-thumb-offset', offsetPx + 'px');
  }

  function calClearToggleThumbDrag(track) {
    track.classList.remove('cal-toggle-track--dragging');
    track.style.removeProperty('--cal-thumb-offset');
  }

  function calGetToggleCheckbox(track) {
    var item = track.closest('.cal-filter-item');
    return item ? item.querySelector('input[type="checkbox"]') : null;
  }

  function calInitToggleTrackDrag(track) {
    if (!track || track._calToggleDragInit) return;
    track._calToggleDragInit = true;
    var dragState = null;

    track.addEventListener('pointerdown', function(e) {
      if (e.button !== 0) return;
      var cb = calGetToggleCheckbox(track);
      if (!cb) return;
      e.preventDefault();
      e.stopPropagation();
      var rect = track.getBoundingClientRect();
      var travel = calToggleTrackTravel(track);
      var thumbLeft = cb.checked ? travel : 0;
      dragState = {
        pointerId: e.pointerId,
        startX: e.clientX,
        moved: false,
        travel: travel,
        cb: cb,
        startChecked: cb.checked
      };
      track.classList.add('cal-toggle-track--dragging');
      calSetToggleThumbPosition(track, thumbLeft);
      track.setPointerCapture(e.pointerId);
    });

    track.addEventListener('pointermove', function(e) {
      if (!dragState || dragState.pointerId !== e.pointerId) return;
      if (Math.abs(e.clientX - dragState.startX) > 3) dragState.moved = true;
      var rect = track.getBoundingClientRect();
      var x = e.clientX - rect.left - 2;
      x = Math.max(0, Math.min(dragState.travel, x));
      calSetToggleThumbPosition(track, x);
      dragState.cb.checked = x >= dragState.travel / 2;
    });

    function endDrag(e) {
      if (!dragState || dragState.pointerId !== e.pointerId) return;
      try { track.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }
      if (dragState.moved) {
        var rect = track.getBoundingClientRect();
        dragState.cb.checked = e.clientX >= rect.left + rect.width / 2;
      } else {
        dragState.cb.checked = !dragState.startChecked;
      }
      if (dragState.cb.checked !== dragState.startChecked) {
        dragState.cb.dispatchEvent(new Event('change', { bubbles: true }));
      }
      calClearToggleThumbDrag(track);
      var item = track.closest('.cal-filter-item');
      if (item) item._calToggleJustDragged = true;
      setTimeout(function() {
        if (item) item._calToggleJustDragged = false;
      }, 0);
      dragState = null;
    }

    track.addEventListener('pointerup', endDrag);
    track.addEventListener('pointercancel', function(e) {
      if (!dragState || dragState.pointerId !== e.pointerId) return;
      calClearToggleThumbDrag(track);
      dragState = null;
    });
  }

  function calInitAllToggleTrackDrags(root) {
    (root || document).querySelectorAll('.cal-toggle-track--sm').forEach(calInitToggleTrackDrag);
  }

  applySavedFilters();
  applySavedColors();

  function eventInRange(ev, rangeStartStr, rangeEndStr) {
    var start = ev.start;
    var end = ev.end || ev.start;
    if (!start) return false;
    var startStr = typeof start === 'string' ? start : (start.toISOString ? start.toISOString() : '');
    var endStr = typeof end === 'string' ? end : (end.toISOString ? end.toISOString() : startStr);
    return startStr < rangeEndStr && endStr > rangeStartStr;
  }

  function fetchEvents(info, successCallback, failureCallback) {
    const params = new URLSearchParams({
      start: info.startStr,
      end: info.endStr,
      filters: JSON.stringify(getFilters())
    });
    fetch(apiBase + '/events.php?' + params.toString(), { credentials: 'same-origin', cache: 'no-store' })
      .then(function(r) {
        if (!r.ok) {
          if (typeof failureCallback === 'function') failureCallback();
          else successCallback([]);
          return null;
        }
        return r.json();
      })
      .then(function(data) {
        if (data === null) return;
        var list = Array.isArray(data) ? data : [];
        
        // Subscription-Events aus Cache hinzufügen, nur wenn sie im aktuellen Datumsbereich liegen
        if (typeof subscriptionEventsCache !== 'undefined') {
          Object.keys(subscriptionEventsCache).forEach(function(subId) {
            var subEvents = subscriptionEventsCache[subId] || [];
            subEvents.forEach(function(ev) {
              if (eventInRange(ev, info.startStr, info.endStr)) list.push(ev);
            });
          });
        }
        
        successCallback(applyEventColors(list));
      })
      .catch(function() {
        if (typeof failureCallback === 'function') failureCallback();
        else successCallback([]);
      });
  }

  function formatEventTime(start, end, allDay) {
    if (allDay) return 'Ganztägig';
    var s = start;
    var e = end;
    if (s && e) {
      var ss = s.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
      var ee = e.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
      return ss + ' – ' + ee;
    }
    if (s) return s.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    return '';
  }

  function cleanCalendarLabelText(s) {
    s = s && String(s).trim();
    if (!s || s === '[Verschlüsselt]' || s.indexOf('ENC:') === 0) return '';
    return s;
  }

  function getCalendarAssigneeName(props) {
    var name = cleanCalendarLabelText(props && props.user);
    if (!name) return '';
    var lower = name.toLowerCase();
    if (lower === 'unbekannt' || lower === '–' || lower === '-') return '';
    return name;
  }

  function startOfCalendarDay(d) {
    var x = d instanceof Date ? new Date(d.getTime()) : new Date(d);
    return new Date(x.getFullYear(), x.getMonth(), x.getDate());
  }

  function formatMonthEventTimeRange(start, end, allDay) {
    var empty = { start: '', end: '', hasEnd: false };
    if (!start) return empty;
    var startDate = start instanceof Date ? start : new Date(start);
    if (allDay) {
      return empty;
    }
    var ss = startDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    if (!end) return { start: ss, end: '', hasEnd: false };
    var endDate = end instanceof Date ? end : new Date(end);
    var ee = endDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    var startDay = startOfCalendarDay(startDate);
    var endDay = startOfCalendarDay(endDate);
    if (startDay.getTime() !== endDay.getTime()) {
      return { start: ss, end: ee, hasEnd: true };
    }
    if (ss === ee) return { start: ss, end: '', hasEnd: false };
    return { start: ss, end: ee, hasEnd: true };
  }

  function appendMonthEventTimeRange(wrap, start, end, allDay) {
    var tr = formatMonthEventTimeRange(start, end, allDay);
    if (!tr.start) return;
    var timesWrap = document.createElement('span');
    var timeStart = document.createElement('span');
    timeStart.className = 'cal-month-event__time';
    if (tr.hasEnd && tr.end) {
      timesWrap.className = 'cal-month-event__times';
      timeStart.textContent = tr.start;
      timesWrap.appendChild(timeStart);
      var timeEnd = document.createElement('span');
      timeEnd.className = 'cal-month-event__time cal-month-event__time--end';
      timeEnd.textContent = tr.end;
      timesWrap.appendChild(timeEnd);
    } else {
      timesWrap.className = 'cal-month-event__times cal-month-event__times--inline';
      timeStart.textContent = tr.start;
      timesWrap.appendChild(timeStart);
    }
    wrap.appendChild(timesWrap);
  }

  function getTicketCalendarLabel(props) {
    if (!props) return 'Ticket';
    var customer = cleanCalendarLabelText(props.customerName);
    if (customer) return customer;
    var company = cleanCalendarLabelText(props.companyName);
    if (company) return company;
    var titel = cleanCalendarLabelText(props.titel);
    if (titel) return titel;
    return 'Ticket';
  }

  function getMonthEventAriaLabel(ev) {
    var parts = [];
    var tr = formatMonthEventTimeRange(ev.start, ev.end, ev.allDay);
    if (tr.start && tr.end && tr.hasEnd) parts.push(tr.start + ' - ' + tr.end);
    else if (tr.start) parts.push(tr.start);
    var props = ev.extendedProps || {};
    var label = props.source === 'service' && !props.appointment_id
      ? getTicketCalendarLabel(props)
      : (ev.title || '').trim();
    if (label) parts.push(label);
    return parts.join(', ') || 'Termin';
  }

  function calTooltipMetaIcon(type) {
    var icons = {
      company: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>',
      customer: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>',
      device: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"/></svg>',
      user: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z"/></svg>',
      note: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/></svg>',
      link: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 0 0-5.656 0l-4 4a4 4 0 1 0 5.656 5.656l1.102-1.101m-.758-4.899a4 4 0 0 0 5.656 0l4-4a4 4 0 0 0-5.656-5.656l-1.1 1.1"/></svg>'
    };
    return icons[type] || icons.customer;
  }

  function calTooltipMetaCard(type, value, secondary, meta) {
    var text = value != null ? String(value).trim() : '';
    if (!text) return '';
    var secondaryText = secondary != null ? String(secondary).trim() : '';
    var metaText = meta != null ? String(meta).trim() : '';
    var body = '<span class="cal-event-tooltip__meta-card-value">' + escapeHtml(text) + '</span>';
    if (secondaryText) {
      body += '<span class="cal-event-tooltip__meta-card-secondary">' + escapeHtml(secondaryText) + '</span>';
    }
    if (metaText) {
      body += '<span class="ticket-nummer-meta block">' + escapeHtml(metaText) + '</span>';
    }
    var cardClass = 'cal-event-tooltip__meta-card';
    if (type === 'company' && !secondaryText && !metaText) {
      cardClass += ' cal-event-tooltip__meta-card--company-solo';
    }
    return '<div class="' + cardClass + '">' +
      '<span class="cal-event-tooltip__meta-icon" aria-hidden="true">' + calTooltipMetaIcon(type) + '</span>' +
      '<div class="cal-event-tooltip__meta-card-body">' + body + '</div>' +
      '</div>';
  }

  function calTooltipMetaGrid(props) {
    var cards = '';
    var customer = cleanCalendarLabelText(props.customerName);
    var company = cleanCalendarLabelText(props.companyName);
    var device = cleanCalendarLabelText(props.deviceName);
    var standort = cleanCalendarLabelText(props.deviceStandort);
    if (customer) {
      cards += calTooltipMetaCard('customer', customer, company);
    } else if (company) {
      cards += calTooltipMetaCard('company', company);
    }
    if (device) cards += calTooltipMetaCard('device', device, null, standort);
    if (!cards) return '';
    return '<div class="cal-event-tooltip__meta-grid">' + cards + '</div>';
  }

  function getTicketTooltipHeadline(props) {
    if (!props) return '';
    if (props.appointment_id) {
      return cleanCalendarLabelText(props.appointment_titel);
    }
    return cleanCalendarLabelText(props.titel);
  }

  function getTicketTooltipBetreff(props) {
    if (!props) return '';
    return cleanCalendarLabelText(props.titel);
  }

  function calTooltipRow(label, value) {
    var text = value != null ? String(value).trim() : '';
    if (!text) return '';
    return '<div class="cal-event-tooltip__meta-group"><span class="cal-event-tooltip__meta-secondary">' + escapeHtml(label) + '</span><span class="cal-event-tooltip__meta-primary">' + escapeHtml(text) + '</span></div>';
  }

  function calTooltipDetailsRows(rowsHtml) {
    if (!rowsHtml) return '';
    return '<div class="cal-event-tooltip__details">' + rowsHtml + '</div>';
  }

  function calTooltipAssigneeRow(name, label) {
    if (!name) return '';
    label = label || 'Bearbeiter';
    return '<div class="cal-event-tooltip__assignee">' +
      '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/></svg>' +
      '<span class="cal-event-tooltip__assignee-label">' + escapeHtml(label) + '</span>' +
      '<span class="cal-event-tooltip__assignee-name">' + escapeHtml(name) + '</span></div>';
  }

  function wrapCalRichTooltip(accent, headline, blockInner, detailsHtml) {
    var html = '<div class="cal-event-tooltip__inner cal-event-tooltip__inner--rich" style="--cal-tooltip-accent:' + accent + '">';
    if (headline) {
      html += '<div class="cal-event-tooltip__header"><span class="cal-event-tooltip__ticket-title">' + escapeHtml(headline) + '</span></div>';
    }
    if (blockInner) {
      html += '<div class="cal-event-tooltip__ticket-block">' + blockInner + '</div>';
    }
    html += calTooltipDetailsRows(detailsHtml);
    html += '</div>';
    return html;
  }

  function getCalSourceDefaultLabel(source) {
    var labels = {
      custom: 'Termin',
      todos: 'Aufgabe',
      orders: 'Bestellung',
      other_users: 'Termin',
      my_vacation: 'Urlaub',
      colleagues_vacation: 'Urlaub',
      my_times: 'Zeiterfassung',
      service: 'Ticket'
    };
    return labels[source] || 'Termin';
  }

  function getCalGenericTooltipHeadline(fcEvent, props) {
    var title = (fcEvent.title || '').trim();
    if (props.source === 'orders' && title.indexOf('Bestellung: ') === 0) {
      return title.slice('Bestellung: '.length);
    }
    if (props.owner) {
      var ownerPrefix = props.owner + ': ';
      if (title.indexOf(ownerPrefix) === 0) return title.slice(ownerPrefix.length);
    }
    if (props.user) {
      var userPrefix = props.user + ': ';
      if (title.indexOf(userPrefix) === 0) return title.slice(userPrefix.length);
    }
    return title;
  }

  function formatCalEventTooltipTime(fcEvent) {
    if (!fcEvent || !fcEvent.start) return '';
    var start = fcEvent.start;
    var end = fcEvent.end;
    var allDay = fcEvent.allDay;
    if (allDay) {
      var tr = formatMonthEventTimeRange(start, end, true);
      if (tr.start && tr.end && tr.hasEnd) return tr.start + ' – ' + tr.end;
      if (tr.start) return 'Ganztägig · ' + tr.start;
      return 'Ganztägig';
    }
    return formatEventTime(start, end, false);
  }

  function truncateCalTooltipText(text, maxLen) {
    text = text != null ? String(text).trim() : '';
    if (!text) return '';
    maxLen = maxLen || 160;
    if (text.length > maxLen) return text.slice(0, maxLen - 1) + '…';
    return text;
  }

  function buildCalGenericTooltipBlock(fcEvent, props) {
    var parts = [];
    var label = cleanCalendarLabelText(props.sourceLabel) || getCalSourceDefaultLabel(props.source);
    if (label) parts.push('<span class="ticket-nummer-meta block">' + escapeHtml(label) + '</span>');
    var secondary = '';
    if (props.source === 'orders') {
      secondary = cleanCalendarLabelText(props.bestellnummer);
    }
    if (secondary) {
      parts.push('<span class="cal-event-tooltip__ticket-betreff">' + escapeHtml(secondary) + '</span>');
    }
    var timeStr = formatCalEventTooltipTime(fcEvent);
    if (timeStr) {
      var timeClass = secondary ? 'ticket-nummer-meta block' : 'cal-event-tooltip__ticket-betreff';
      parts.push('<span class="' + timeClass + '">' + escapeHtml(timeStr) + '</span>');
    }
    return parts.join('');
  }

  function buildCalGenericTooltipDetails(fcEvent, props) {
    var assigneeHtml = '';
    var cardsHtml = '';
    var source = props.source || '';
    var headline = getCalGenericTooltipHeadline(fcEvent, props);
    var ownerName = cleanCalendarLabelText(props.owner);
    if (ownerName && ownerName.toLowerCase() !== 'unbekannt') {
      if (source === 'custom' && props.invited) {
        assigneeHtml += calTooltipAssigneeRow(ownerName, 'Von');
      } else if (source === 'other_users') {
        cardsHtml += calTooltipMetaCard('user', ownerName);
      }
    }
    if (source === 'colleagues_vacation') {
      var colleagueName = cleanCalendarLabelText(props.user);
      if (colleagueName) cardsHtml += calTooltipMetaCard('user', colleagueName);
    }
    var personName = getCalendarAssigneeName(props);
    if (personName) {
      if (source === 'todos') {
        assigneeHtml += calTooltipAssigneeRow(personName, 'Zugewiesen');
      } else if (source === 'orders') {
        assigneeHtml += calTooltipAssigneeRow(personName, 'Erstellt von');
      }
    }
    var desc = truncateCalTooltipText(cleanCalendarLabelText(props.description));
    if (desc && desc !== headline) {
      cardsHtml += calTooltipMetaCard('note', desc);
    }
    var meetingLink = cleanCalendarLabelText(props.meeting_link);
    if (meetingLink) {
      var linkDisplay = truncateCalTooltipText(meetingLink, 52);
      cardsHtml += calTooltipMetaCard('link', 'Online-Meeting', null, linkDisplay);
    }
    if (props.invite_emails) {
      var emails = String(props.invite_emails).split(/[,;]/).map(function(e) { return e.trim(); }).filter(Boolean);
      if (emails.length) {
        var guestLabel = emails.length === 1 ? '1 Gast eingeladen' : emails.length + ' Gäste eingeladen';
        cardsHtml += calTooltipMetaCard('user', guestLabel);
      }
    }
    var html = '';
    if (cardsHtml) html += '<div class="cal-event-tooltip__meta-grid">' + cardsHtml + '</div>';
    html += assigneeHtml;
    return html;
  }

  function buildCalEventTooltipHtml(fcEvent) {
    if (!fcEvent) return '';
    var props = fcEvent.extendedProps || {};
    var accent = fcEvent.borderColor || fcEvent.backgroundColor || '#3b82f6';

    if (props.source === 'service') {
      var headline = getTicketTooltipHeadline(props);
      var ticketBetreff = getTicketTooltipBetreff(props);
      var metaGrid = calTooltipMetaGrid(props);
      var assigneeName = getCalendarAssigneeName(props);
      var assigneeHtml = assigneeName ? calTooltipAssigneeRow(assigneeName, 'Bearbeiter') : '';
      var details = metaGrid + assigneeHtml;
      var subjectInner = '';
      if (props.appointment_id && ticketBetreff) {
        subjectInner += '<span class="cal-event-tooltip__ticket-betreff">' + escapeHtml(ticketBetreff) + '</span>';
      }
      if (props.ticket_nummer) {
        subjectInner += '<span class="ticket-nummer-meta block">' + escapeHtml(props.ticket_nummer) + '</span>';
      }
      return wrapCalRichTooltip(accent, headline, subjectInner, details);
    }

    var genericHeadline = getCalGenericTooltipHeadline(fcEvent, props);
    var blockInner = buildCalGenericTooltipBlock(fcEvent, props);
    var genericDetails = buildCalGenericTooltipDetails(fcEvent, props);
    return wrapCalRichTooltip(accent, genericHeadline, blockInner, genericDetails);
  }

  var calMonthTooltipEl = null;
  var calMonthTooltipAnchor = null;

  function ensureCalMonthTooltipEl() {
    if (!calMonthTooltipEl) {
      calMonthTooltipEl = document.createElement('div');
      calMonthTooltipEl.id = 'cal-month-event-tooltip';
      calMonthTooltipEl.className = 'cal-month-event-tooltip';
      calMonthTooltipEl.setAttribute('role', 'tooltip');
      calMonthTooltipEl.hidden = true;
      document.body.appendChild(calMonthTooltipEl);
    }
    return calMonthTooltipEl;
  }

  function calMonthTooltipHide() {
    if (calMonthTooltipEl) {
      calMonthTooltipEl.hidden = true;
      calMonthTooltipEl.style.minWidth = '';
    }
    calMonthTooltipAnchor = null;
  }

  function calMonthTooltipShow(anchor, content, asHtml) {
    if (!content || !anchor) return;
    var tip = ensureCalMonthTooltipEl();
    if (asHtml) {
      tip.innerHTML = content;
      tip.classList.add('cal-event-tooltip--rich');
    } else {
      tip.textContent = content;
      tip.classList.remove('cal-event-tooltip--rich');
    }
    tip.hidden = false;
    tip.style.visibility = 'hidden';
    calMonthTooltipAnchor = anchor;
    var rect = anchor.getBoundingClientRect();
    var anchorWidth = Math.ceil(rect.width);
    tip.style.minWidth = anchorWidth > 0 ? anchorWidth + 'px' : '';
    var tipRect = tip.getBoundingClientRect();
    var left = rect.left;
    if (left + tipRect.width > window.innerWidth - 8) {
      left = Math.max(8, window.innerWidth - tipRect.width - 8);
    }
    if (left < 8) left = 8;
    var top = rect.bottom + 6;
    if (top + tipRect.height > window.innerHeight - 8) {
      top = Math.max(8, rect.top - tipRect.height - 6);
    }
    tip.style.left = left + 'px';
    tip.style.top = top + 'px';
    tip.style.visibility = 'visible';
  }

  function calMonthGetEventTooltipContent(el) {
    if (!el) return '';
    if (el._calFcEvent) {
      return buildCalEventTooltipHtml(el._calFcEvent);
    }
    var aria = el.getAttribute('aria-label');
    if (aria) return aria;
    var titleNode = el.querySelector('.cal-month-event__title, .fc-event-title');
    return titleNode ? titleNode.textContent.trim() : '';
  }

  function calMonthParseCssSize(raw, fallbackPx) {
    raw = raw && String(raw).trim();
    if (!raw) return fallbackPx;
    var num = parseFloat(raw);
    if (isNaN(num)) return fallbackPx;
    if (raw.indexOf('rem') !== -1) {
      var rootPx = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
      return Math.round(num * rootPx);
    }
    return Math.round(num);
  }

  function calMonthResetHarnessLayout(harness, ev) {
    var isAbs = harness.classList.contains('fc-daygrid-event-harness-abs');
    harness.style.setProperty('margin-top', '0', 'important');
    harness.style.setProperty('margin-bottom', '0', 'important');
    if (!isAbs) {
      harness.style.setProperty('position', 'relative', 'important');
      harness.style.setProperty('top', 'auto', 'important');
      harness.style.setProperty('left', 'auto', 'important');
      harness.style.setProperty('right', 'auto', 'important');
      harness.style.setProperty('height', 'auto', 'important');
      harness.style.bottom = 'auto';
      if (ev) {
        ev.style.setProperty('height', 'auto', 'important');
        ev.style.setProperty('min-height', '0', 'important');
      }
      return null;
    }
    return isAbs;
  }

  function calMonthMeasureEventHeight(ev) {
    var harness = ev.closest('.fc-daygrid-event-harness');
    if (harness) {
      harness.style.height = 'auto';
      harness.style.marginTop = '0';
      harness.style.marginBottom = '0';
    }
    ev.style.height = 'auto';
    ev.style.minHeight = '0';
    ev.style.maxHeight = 'none';
    var main = ev.querySelector('.fc-event-main');
    if (main) {
      main.style.height = 'auto';
      main.style.minHeight = '0';
      main.style.maxHeight = 'none';
      main.style.overflow = 'visible';
    }
    var minPx = 24;
    var calEl = document.getElementById('calendar');
    if (calEl) {
      minPx = calMonthParseCssSize(getComputedStyle(calEl).getPropertyValue('--cal-month-event-height'), 24);
    }
    return Math.max(minPx, Math.ceil(ev.getBoundingClientRect().height));
  }

  function calMonthReflowEventHarnesses() {
    if (!calendar || !calendar.view || calendar.view.type !== 'dayGridMonth') return;
    var calEl = document.getElementById('calendar');
    var gap = 1;
    if (calEl) {
      gap = calMonthParseCssSize(getComputedStyle(calEl).getPropertyValue('--cal-month-event-gap'), 1);
    }
    document.querySelectorAll('#calendar .fc-daygrid-day').forEach(function(dayEl) {
      var dayEvents = dayEl.querySelector('.fc-daygrid-day-events');
      if (!dayEvents) return;
      var harnesses = dayEvents.querySelectorAll('.fc-daygrid-event-harness');
      var maxAbsBottom = 0;
      harnesses.forEach(function(harness) {
        var ev = harness.querySelector('.fc-daygrid-event.cal-month-event');
        if (!ev) return;
        var isAbs = harness.classList.contains('fc-daygrid-event-harness-abs');
        ev.style.height = 'auto';
        ev.style.minHeight = '0';
        ev.style.maxHeight = 'none';
        var main = ev.querySelector('.fc-event-main');
        if (main) {
          main.style.height = 'auto';
          main.style.minHeight = '0';
          main.style.maxHeight = 'none';
          main.style.overflow = 'visible';
        }
        if (isAbs) {
          var top = parseFloat(harness.style.top) || 0;
          var h = harness.offsetHeight || 0;
          var bottom = top + h;
          if (bottom > maxAbsBottom) maxAbsBottom = bottom;
        } else {
          harness.style.setProperty('position', 'relative', 'important');
          harness.style.setProperty('top', 'auto', 'important');
          harness.style.setProperty('left', 'auto', 'important');
          harness.style.setProperty('right', 'auto', 'important');
          harness.style.setProperty('height', 'auto', 'important');
        }
      });
      dayEvents.style.paddingTop = maxAbsBottom > 0 ? (maxAbsBottom + gap) + 'px' : '';
    });
  }

  var calMonthReflowTimer = null;
  function scheduleCalMonthReflow() {
    clearTimeout(calMonthReflowTimer);
    calMonthReflowTimer = setTimeout(function() {
      requestAnimationFrame(function() {
        calMonthReflowEventHarnesses();
        requestAnimationFrame(calMonthReflowEventHarnesses);
      });
    }, 0);
  }

  function initCalMonthEventInteractions() {
    if (window._calMonthEventInteractionsInit) return;
    window._calMonthEventInteractionsInit = true;
    var calEl = document.getElementById('calendar');
    if (!calEl) return;
    calEl.addEventListener('pointerover', function(e) {
      var evNode = e.target.closest('.fc-event');
      if (!evNode || !calEl.contains(evNode)) return;
      var content = calMonthGetEventTooltipContent(evNode);
      if (!content) return;
      var asHtml = !!evNode._calFcEvent;
      calMonthTooltipShow(evNode, content, asHtml);
    });
    calEl.addEventListener('pointerout', function(e) {
      var evNode = e.target.closest('.fc-event');
      if (!evNode || calMonthTooltipAnchor !== evNode) return;
      var related = e.relatedTarget;
      if (related && evNode.contains(related)) return;
      calMonthTooltipHide();
    });
    document.addEventListener('pointerover', function(e) {
      var popover = e.target.closest('.fc-more-popover');
      if (!popover) return;
      var evNode = e.target.closest('.fc-event');
      if (!evNode || !popover.contains(evNode)) return;
      var content = calMonthGetEventTooltipContent(evNode);
      if (!content) return;
      var asHtml = !!evNode._calFcEvent;
      calMonthTooltipShow(evNode, content, asHtml);
    });
    document.addEventListener('pointerout', function(e) {
      var popover = e.target.closest('.fc-more-popover');
      if (!popover) return;
      var evNode = e.target.closest('.fc-event');
      if (!evNode || calMonthTooltipAnchor !== evNode) return;
      var related = e.relatedTarget;
      if (related && evNode.contains(related)) return;
      calMonthTooltipHide();
    });
    window.addEventListener('scroll', calMonthTooltipHide, true);
  }

  function applyMonthEventMount(el, ev) {
    var accent = ev.borderColor || ev.backgroundColor || '#7c3aed';
    el.classList.add('cal-month-event');
    if (ev.editable === false) el.classList.add('cal-month-event--locked');
    el.style.setProperty('--cal-event-accent', accent);
    el.style.backgroundColor = '';
    el.style.borderColor = '';
    el.style.color = '';
    var props = ev.extendedProps || {};
    var label = props.source === 'service' && !props.appointment_id
      ? getTicketCalendarLabel(props)
      : ((ev.title || '').trim() || 'Termin');
    el.setAttribute('aria-label', getMonthEventAriaLabel(ev));
    el.setAttribute('tabindex', '0');
    el.dataset.calFullTitle = label;
  }

  function completeCalendarTodo(todoId, isChecked, checkboxEl) {
    var api = (baseUrl && baseUrl !== '/' ? baseUrl.replace(/\/$/, '') : '') + '/todos/api/todos.php';
    fetch(api, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        todo_id: parseInt(todoId, 10),
        status: isChecked ? 'erledigt' : 'offen'
      })
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data && data.success) {
          if (calendar) calendar.refetchEvents();
          if (typeof showToast === 'function') {
            showToast(isChecked ? 'Aufgabe erledigt' : 'Aufgabe wieder offen', 'success');
          }
        } else {
          if (checkboxEl) checkboxEl.checked = !isChecked;
          if (typeof showToast === 'function') {
            showToast((data && data.error) ? data.error : 'Aufgabe konnte nicht aktualisiert werden', 'error');
          }
        }
      })
      .catch(function() {
        if (checkboxEl) checkboxEl.checked = !isChecked;
        if (typeof showToast === 'function') {
          showToast('Aufgabe konnte nicht aktualisiert werden', 'error');
        }
      });
  }

  function createCalTodoDoneCheckbox(todoId) {
    var label = document.createElement('label');
    label.className = 'cal-todo-done';
    label.title = 'Als erledigt markieren';
    var cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.className = 'cal-todo-done__input todo-checkbox';
    cb.setAttribute('aria-label', 'Aufgabe erledigen');
    ['click', 'mousedown', 'pointerdown'].forEach(function(evtName) {
      cb.addEventListener(evtName, function(e) { e.stopPropagation(); });
      label.addEventListener(evtName, function(e) { e.stopPropagation(); });
    });
    cb.addEventListener('change', function(e) {
      e.stopPropagation();
      completeCalendarTodo(todoId, cb.checked, cb);
    });
    label.appendChild(cb);
    return label;
  }

  function renderMonthEventContent(arg, title, props) {
    if (props.source === 'service') {
      title = props.appointment_id ? arg.event.title : getTicketCalendarLabel(props);
    }
    if (props.invited) {
      title = (title || '').replace(/^\s*📅\s*/, '');
    }
    var wrap = document.createElement('div');
    wrap.className = 'cal-month-event__inner fc-event-main-frame';
    if (props.source === 'todos' && props.todo_id && arg.event.editable !== false) {
      wrap.appendChild(createCalTodoDoneCheckbox(props.todo_id));
    }
    appendMonthEventTimeRange(wrap, arg.event.start, arg.event.end, arg.event.allDay);
    var titleEl = document.createElement('span');
    titleEl.className = 'cal-month-event__title fc-event-title';
    titleEl.textContent = title || 'Termin';
    wrap.appendChild(titleEl);
    return { domNodes: [wrap] };
  }

  var serviceApiBase = (baseUrl && baseUrl !== '/' ? baseUrl.replace(/\/$/, '') : '') + '/tickets/api';
  var todosApiBase = (baseUrl && baseUrl !== '/' ? baseUrl.replace(/\/$/, '') : '') + '/todos/api';

  function toISO(d) {
    if (!d) return null;
    if (typeof d.toISOString === 'function') {
      var y = d.getFullYear();
      var m = String(d.getMonth() + 1).padStart(2, '0');
      var day = String(d.getDate()).padStart(2, '0');
      var h = String(d.getHours()).padStart(2, '0');
      var min = String(d.getMinutes()).padStart(2, '0');
      var s = String(d.getSeconds()).padStart(2, '0');
      return y + '-' + m + '-' + day + ' ' + h + ':' + min + ':' + s;
    }
    return d;
  }

  function highlightSelectedDayInCalendar(clickInfo) {
    var el = document.getElementById('calendar');
    if (!el) return;
    
    // Entferne alle vorherigen Markierungen
    el.querySelectorAll('.fc-day-selected, .fc-col-selected').forEach(function(c) { 
      c.classList.remove('fc-day-selected', 'fc-col-selected'); 
      c.style.removeProperty('--highlight-top');
      c.style.removeProperty('--highlight-height');
    });
    
    var currentView = calendar.view.type;
    
    // In Monatsansicht: Ganzen Tag markieren
    if (currentView === 'dayGridMonth') {
      var dayEl = clickInfo;
      if (dayEl && dayEl.classList) {
        dayEl.classList.add('fc-day-selected');
      } else if (selectedDate) {
        var y = selectedDate.getFullYear();
        var m = String(selectedDate.getMonth() + 1).padStart(2, '0');
        var d = String(selectedDate.getDate()).padStart(2, '0');
        var dateStr = y + '-' + m + '-' + d;
        var cell = el.querySelector('.fc-daygrid-day[data-date="' + dateStr + '"]');
        if (cell) cell.classList.add('fc-day-selected');
      }
    } else {
      // In Zeit-Ansichten: Markiere die spezifische Stunde in der Spalte
      if (!selectedDate) return;
      
      var dateStr = selectedDate.getFullYear() + '-' + 
                    String(selectedDate.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(selectedDate.getDate()).padStart(2, '0');
      var timeStr = String(selectedDate.getHours()).padStart(2, '0') + ':00:00';
      
      // Finde die Spalte für den Tag
      var dayCol = el.querySelector('.fc-timegrid-col[data-date="' + dateStr + '"]');
      if (!dayCol) {
        return;
      }
      
      // Finde den Zeitslot
      var timeSlots = el.querySelectorAll('.fc-timegrid-slot[data-time="' + timeStr + '"]');
      if (timeSlots.length === 0) {
        return;
      }
      
      var timeSlot = timeSlots[0];
      
      // Berechne Position relativ zur Spalte
      var colRect = dayCol.getBoundingClientRect();
      var slotRect = timeSlot.getBoundingClientRect();
      
      var topOffset = slotRect.top - colRect.top;
      var height = slotRect.height;
      
      // Setze CSS-Variablen für das ::before Pseudo-Element
      dayCol.style.setProperty('--highlight-top', topOffset + 'px');
      dayCol.style.setProperty('--highlight-height', height + 'px');
      dayCol.classList.add('fc-col-selected');
    }
  }

  // Zeit-Slider Hilfsfunktionen
  function minutesToTime(minutes) {
    var h = Math.floor(minutes / 60);
    var m = minutes % 60;
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
  }

  function timeToMinutes(timeStr) {
    if (!timeStr) return 0;
    var parts = timeStr.split(':');
    return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
  }

  function updateTimeDisplay(sliderId, displayId) {
    var slider = document.getElementById(sliderId);
    var display = document.getElementById(displayId);
    if (slider && display) {
      var minutes = parseInt(slider.value, 10);
      display.textContent = minutesToTime(minutes);
    }
  }

  // Hilfsfunktion: Lokales Datum/Zeit im Format YYYY-MM-DDTHH:MM
  function toLocalDateTimeString(date) {
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    var h = String(date.getHours()).padStart(2, '0');
    var min = String(date.getMinutes()).padStart(2, '0');
    return y + '-' + m + '-' + d + 'T' + h + ':' + min;
  }
  
  function syncDateTimeFields() {
    var startHidden = document.getElementById('event-start');
    var endHidden = document.getElementById('event-end');
    var dateInput = document.getElementById('event-date');
    var dateEndInput = document.getElementById('event-date-end');
    var startSlider = document.getElementById('event-start-time');
    var endSlider = document.getElementById('event-end-time');
    var alldayToggle = document.getElementById('event-allday-toggle');
    var allday = alldayToggle && alldayToggle.checked;

    if (!startHidden || !endHidden || !dateInput) return;

    if (allday) {
      var startStr = dateInput.value;
      var endStr = (dateEndInput && dateEndInput.value) || startStr;
      if (!startStr) return;
      if (!endStr) endStr = startStr;
      if (endStr < startStr) endStr = startStr;
      startHidden.value = startStr + 'T00:00';
      var endDate = new Date(endStr);
      endDate.setDate(endDate.getDate() + 1);
      endHidden.value = endDate.getFullYear() + '-' + String(endDate.getMonth() + 1).padStart(2, '0') + '-' + String(endDate.getDate()).padStart(2, '0') + 'T00:00';
      document.getElementById('event-allday').value = '1';
    } else {
      var dateStr = dateInput.value;
      if (!dateStr || !startSlider || !endSlider) return;
      var startMinutes = parseInt(startSlider.value, 10);
      var endMinutes = parseInt(endSlider.value, 10);
      var startHours = Math.floor(startMinutes / 60);
      var startMins = startMinutes % 60;
      var endHours = Math.floor(endMinutes / 60);
      var endMins = endMinutes % 60;
      startHidden.value = dateStr + 'T' + String(startHours).padStart(2, '0') + ':' + String(startMins).padStart(2, '0');
      endHidden.value = dateStr + 'T' + String(endHours).padStart(2, '0') + ':' + String(endMins).padStart(2, '0');
      document.getElementById('event-allday').value = '0';
    }
  }

  function initTimeSliders() {
    var startSlider = document.getElementById('event-start-time');
    var endSlider = document.getElementById('event-end-time');
    var dateInput = document.getElementById('event-date');
    
    if (startSlider) {
      startSlider.addEventListener('input', function() {
        updateTimeDisplay('event-start-time', 'event-start-time-display');
        syncDateTimeFields();
      });
    }
    if (endSlider) {
      endSlider.addEventListener('input', function() {
        updateTimeDisplay('event-end-time', 'event-end-time-display');
        syncDateTimeFields();
      });
    }
    if (dateInput) {
      dateInput.addEventListener('change', syncDateTimeFields);
    }
    var dateEndInput = document.getElementById('event-date-end');
    if (dateEndInput) {
      dateEndInput.addEventListener('change', syncDateTimeFields);
    }
    var alldayToggle = document.getElementById('event-allday-toggle');
    if (alldayToggle) {
      alldayToggle.addEventListener('change', function() {
        toggleAllDayFields();
        syncDateTimeFields();
      });
    }
    syncDateTimeFields();
  }

  function toggleAllDayFields() {
    var alldayToggle = document.getElementById('event-allday-toggle');
    var isAllDay = alldayToggle && alldayToggle.checked;
    var timeFields = document.getElementById('event-time-fields');
    var dateEndWrap = document.getElementById('event-date-end-wrap');
    var dateFields = document.getElementById('event-date-fields');
    var dateLabel = document.getElementById('event-date-label');

    if (timeFields) timeFields.classList.toggle('hidden', isAllDay);
    if (dateEndWrap) dateEndWrap.classList.toggle('hidden', !isAllDay);
    if (dateFields) {
      dateFields.classList.remove('grid-cols-1', 'grid-cols-2');
      dateFields.classList.add(isAllDay ? 'grid-cols-2' : 'grid-cols-1');
    }
    if (dateLabel) dateLabel.textContent = isAllDay ? 'Startdatum *' : 'Datum *';
    var dateEndInput = document.getElementById('event-date-end');
    if (isAllDay && dateEndInput && !dateEndInput.value) {
      var dateInput = document.getElementById('event-date');
      if (dateInput && dateInput.value) dateEndInput.value = dateInput.value;
    }
  }

  // Gespeicherte Ansicht laden
  function getSavedView() {
    try {
      return localStorage.getItem('kalender_view') || 'dayGridMonth';
    } catch (e) {
      return 'dayGridMonth';
    }
  }
  
  function saveView(viewType) {
    try {
      localStorage.setItem('kalender_view', viewType);
    } catch (e) {}
  }
  
  // Gespeichertes Datum laden/speichern
  function getSavedDate() {
    try {
      var saved = localStorage.getItem('kalender_date');
      if (saved) {
        var d = new Date(saved);
        if (!isNaN(d.getTime())) return d;
      }
    } catch (e) {}
    return new Date();
  }
  
  function saveCurrentDate(date) {
    try {
      if (date && date.toISOString) {
        localStorage.setItem('kalender_date', date.toISOString().slice(0, 10));
      }
    } catch (e) {}
  }

  function getUiSettings() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY_UI);
      if (raw) {
        var parsed = JSON.parse(raw);
        return {
          sidebarCollapsed: parsed && parsed.sidebarCollapsed === true
        };
      }
    } catch (e) {}
    return { sidebarCollapsed: false };
  }

  function saveUiSettings(uiSettings) {
    try {
      localStorage.setItem(STORAGE_KEY_UI, JSON.stringify({
        sidebarCollapsed: !!(uiSettings && uiSettings.sidebarCollapsed)
      }));
    } catch (e) {}
  }
  
  function getAvailabilityBusinessHours() {
    return Array.isArray(calendarBusinessHours) && calendarBusinessHours.length
      ? calendarBusinessHours
      : false;
  }

  function applyTimeGridOptions(viewType) {
    calendar.setOption('slotMinTime', CALENDAR_FULL_DAY.slotMinTime);
    calendar.setOption('slotMaxTime', CALENDAR_FULL_DAY.slotMaxTime);
    calendar.setOption('slotHeight', calculateSlotHeight(
      CALENDAR_FULL_DAY.slotMinTime,
      CALENDAR_FULL_DAY.slotMaxTime,
      viewType
    ));
    calendar.setOption('businessHours', getAvailabilityBusinessHours());
  }

  // Einstellungen laden/speichern (Erreichbarkeit wird nur visuell markiert)
  function getSettings() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY_SETTINGS);
      if (raw) {
        var parsed = JSON.parse(raw);
        return {
          monthWeekends: parsed.monthWeekends !== false,
          weekWeekends: parsed.weekWeekends === true
        };
      }
    } catch (e) {}
    return {
      monthWeekends: true,
      weekWeekends: false
    };
  }
  
  function saveSettings(settings) {
    try {
      localStorage.setItem(STORAGE_KEY_SETTINGS, JSON.stringify({
        monthWeekends: settings.monthWeekends !== false,
        weekWeekends: settings.weekWeekends === true
      }));
    } catch (e) {}
  }
  
  function calculateSlotHeight(minTime, maxTime, viewType) {
    // Tagesansicht bewusst höher für bessere Lesbarkeit, Scrolling ist gewollt.
    if (viewType === 'timeGridDay') return '96px';
    if (viewType === 'timeGridWeek') return '62px';
    return '60px';
  }
  
  function applyWeekendSettings() {
    var settings = getSettings();
    var currentView = calendar.view.type;
    
    if (currentView === 'dayGridMonth') {
      if (settings.monthWeekends) {
        calendar.setOption('weekends', true);
        calendar.setOption('hiddenDays', []);
      } else {
        calendar.setOption('weekends', false);
        calendar.setOption('hiddenDays', [0, 6]);
      }
    } else if (currentView === 'timeGridWeek') {
      if (settings.weekWeekends) {
        calendar.setOption('weekends', true);
        calendar.setOption('hiddenDays', []);
      } else {
        calendar.setOption('weekends', false);
        calendar.setOption('hiddenDays', [0, 6]);
      }
      applyTimeGridOptions(currentView);
    } else if (currentView === 'timeGridDay') {
      // Tag-Ansicht: immer alle Tage anzeigen
      calendar.setOption('weekends', true);
      calendar.setOption('hiddenDays', []);
      applyTimeGridOptions(currentView);
    }
  }

  // Lade Einstellungen für initiale Konfiguration
  var initSettings = getSettings();
  var initView = getSavedView();
  var initWeekends = true;
  var initHiddenDays = [];
  
  // Wende Einstellungen je nach View an
  if (initView === 'dayGridMonth') {
    initWeekends = initSettings.monthWeekends;
    initHiddenDays = initSettings.monthWeekends ? [] : [0, 6];
  } else if (initView === 'timeGridWeek') {
    initWeekends = initSettings.weekWeekends;
    initHiddenDays = initSettings.weekWeekends ? [] : [0, 6];
  }
  
  var initialDate = getSavedDate();
  if (initView === 'timeGridDay' || initView === 'timeGridWeek') {
    initialDate = new Date();
  }

  var calEventContextEvent = null;
  var calEventContextTargetEl = null;

  function clearCalEventContextTargetHighlight() {
    if (calEventContextTargetEl) {
      calEventContextTargetEl.classList.remove('cal-event-context-active');
      calEventContextTargetEl = null;
    }
  }

  function calAppBase() {
    return (baseUrl && baseUrl !== '/' ? baseUrl.replace(/\/$/, '') : '');
  }

  function hideCalEventContextMenu() {
    var menu = document.getElementById('calEventContextMenu');
    var backdrop = document.getElementById('calEventContextBackdrop');
    closeCalEventGoToSubmenus();
    if (menu) menu.classList.add('hidden');
    if (backdrop) backdrop.classList.add('hidden');
    clearCalEventContextTargetHighlight();
    calEventContextEvent = null;
  }

  function calCtxIcon(type) {
    var icons = {
      detail: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 11h2v5m-2 0h4m-2.592-8.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
      tab: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>',
      goto: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>',
      assign: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/></svg>',
      edit: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
      check: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
      delete: '<svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
    };
    return icons[type] || '';
  }

  function calCtxGoToIcon(type) {
    var icons = {
      company: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg>',
      customer: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/></svg>',
      device: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"/></svg>',
      maps: '<svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
    };
    return icons[type] || '';
  }

  function calCtxMenuIcon(type) {
    return calCtxGoToIcon(type) || calCtxIcon(type) || '';
  }

  function calCtxBtn(label, iconType, onClick, danger, active) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'w-full px-3 py-2 text-left text-sm flex items-center gap-2 hover:bg-gray-100 dark:hover:bg-primary-140 ' +
      (danger ? 'text-red-600 dark:text-red-400' : (active ? 'font-medium bg-blue-50 text-blue-800 dark:bg-primary-800 dark:text-primary-200' : 'text-gray-700 dark:text-primary-210'));
    btn.innerHTML = calCtxMenuIcon(iconType) + '<span>' + label + '</span>';
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      hideCalEventContextMenu();
      onClick();
    });
    return btn;
  }

  function calCtxDivider() {
    var d = document.createElement('div');
    d.className = 'border-t border-gray-200 dark:border-primary-120 my-1';
    d.setAttribute('role', 'separator');
    return d;
  }

  function positionCalEventContextSubmenu(submenuEl, anchorEl) {
    if (!submenuEl || !anchorEl) return;
    var viewportPadding = 8;
    submenuEl.style.left = '100%';
    submenuEl.style.right = 'auto';
    submenuEl.style.marginLeft = '2px';
    submenuEl.style.marginRight = '0';
    submenuEl.style.top = '0px';
    submenuEl.style.maxWidth = '';
    submenuEl.style.maxHeight = Math.max(140, window.innerHeight - (viewportPadding * 2)) + 'px';
    submenuEl.style.overflowY = 'auto';

    var rect = submenuEl.getBoundingClientRect();
    if (rect.right > window.innerWidth - viewportPadding) {
      submenuEl.style.left = 'auto';
      submenuEl.style.right = '100%';
      submenuEl.style.marginLeft = '0';
      submenuEl.style.marginRight = '2px';
      rect = submenuEl.getBoundingClientRect();
    }

    var anchorRect = anchorEl.getBoundingClientRect();
    var topOffset = 0;
    if (rect.bottom > window.innerHeight - viewportPadding) {
      topOffset -= (rect.bottom - (window.innerHeight - viewportPadding));
    }
    if (anchorRect.top + topOffset < viewportPadding) {
      topOffset += (viewportPadding - (anchorRect.top + topOffset));
    }
    submenuEl.style.top = Math.round(topOffset) + 'px';

    rect = submenuEl.getBoundingClientRect();
    if (rect.left < viewportPadding) {
      submenuEl.style.maxWidth = Math.max(180, window.innerWidth - (viewportPadding * 2)) + 'px';
      if (submenuEl.style.right === '100%') {
        submenuEl.style.right = '0';
      } else {
        submenuEl.style.left = '0';
      }
    }
  }

  function openCalEventGoToSubmenu(submenuEl, anchorEl) {
    if (!submenuEl || !anchorEl) return;
    submenuEl.classList.add('cal-event-ctx-submenu--open');
    positionCalEventContextSubmenu(submenuEl, anchorEl);
  }

  function closeCalEventGoToSubmenus() {
    document.querySelectorAll('#calEventContextMenu .cal-event-ctx-submenu--open').forEach(function(el) {
      el.classList.remove('cal-event-ctx-submenu--open');
    });
  }

  function bindCalCtxSubmenu(wrap, trigger, submenu, onOpen) {
    trigger.addEventListener('mouseenter', function() {
      closeCalEventGoToSubmenus();
      if (typeof onOpen === 'function') onOpen(submenu);
      openCalEventGoToSubmenu(submenu, wrap);
    });
    trigger.addEventListener('mouseleave', function() {
      setTimeout(function() {
        if (!submenu.matches(':hover')) submenu.classList.remove('cal-event-ctx-submenu--open');
      }, 200);
    });
    submenu.addEventListener('mouseleave', function() {
      submenu.classList.remove('cal-event-ctx-submenu--open');
    });
  }

  function bindCalGoToSubmenu(gotoWrap, trigger, submenu) {
    bindCalCtxSubmenu(gotoWrap, trigger, submenu);
  }

  function appendCalGoToSubmenu(menu, goLinks, forceSubmenu) {
    if (!goLinks.length) return;
    menu.appendChild(calCtxDivider());
    if (!forceSubmenu && goLinks.length === 1) {
      var single = goLinks[0];
      menu.appendChild(calCtxBtn(single.label, single.icon || 'goto', function() {
        if (single.external) window.open(single.href, '_blank', 'noopener');
        else window.location.href = single.href;
      }));
      return;
    }
    var gotoWrap = document.createElement('div');
    gotoWrap.className = 'cal-event-ctx-goto relative';
    var trigger = document.createElement('div');
    trigger.className = 'cal-event-ctx-goto-trigger px-3 py-2 text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 cursor-default';
    trigger.innerHTML = calCtxIcon('goto') + '<span>Gehe zu</span><svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
    var sub = document.createElement('div');
    sub.className = 'cal-event-ctx-submenu absolute left-full top-0 ml-0.5 py-1 min-w-[200px] max-w-[min(320px,calc(100vw-1rem))] bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg z-10';
    goLinks.forEach(function(link) {
      sub.appendChild(calCtxBtn(link.label, link.icon || 'goto', function() {
        if (link.external) window.open(link.href, '_blank', 'noopener');
        else window.location.href = link.href;
      }));
    });
    gotoWrap.appendChild(trigger);
    gotoWrap.appendChild(sub);
    bindCalGoToSubmenu(gotoWrap, trigger, sub);
    menu.appendChild(gotoWrap);
  }

  function populateCalAssignSubmenu(submenu, ev) {
    submenu.innerHTML = '';
    var props = ev.extendedProps || {};
    var ticketId = props.ticket_id;
    if (!ticketId) {
      submenu.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Kein Ticket verfügbar</div>';
      return;
    }
    var hasAssignee = props.zugewiesen_an && parseInt(props.zugewiesen_an, 10) > 0;
    if (hasAssignee) {
      submenu.appendChild(calCtxBtn('Bearbeiter entfernen', null, function() {
        assignCalTicketToUser(ticketId, null);
      }));
    }
    (calendarAssignees || []).forEach(function(user) {
      if (!user || !user.id) return;
      var userName = [user.vorname, user.nachname].filter(Boolean).join(' ').trim() || user.email || 'Unbekannt';
      var isAssigned = hasAssignee && parseInt(props.zugewiesen_an, 10) === parseInt(user.id, 10);
      submenu.appendChild(calCtxBtn(userName, null, function() {
        assignCalTicketToUser(ticketId, user.id);
      }, false, isAssigned));
    });
    if (!submenu.children.length) {
      submenu.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Keine Bearbeiter verfügbar</div>';
    }
  }

  function appendCalAssigneeSubmenu(menu, ev) {
    if (!isAdminOrTechniker) return;
    var props = ev.extendedProps || {};
    if (props.source !== 'service' || !props.ticket_id) return;
    var wrap = document.createElement('div');
    wrap.className = 'cal-event-ctx-goto relative';
    var trigger = document.createElement('div');
    trigger.className = 'cal-event-ctx-goto-trigger px-3 py-2 text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 cursor-default';
    trigger.innerHTML = calCtxIcon('assign') + '<span>Bearbeiter hinzufügen</span><svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
    var sub = document.createElement('div');
    sub.className = 'cal-event-ctx-submenu absolute left-full top-0 ml-0.5 min-w-[160px] max-w-[min(320px,calc(100vw-1rem))] max-h-[50vh] overflow-y-auto py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg z-10';
    bindCalCtxSubmenu(wrap, trigger, sub, function(submenuEl) {
      populateCalAssignSubmenu(submenuEl, ev);
    });
    wrap.appendChild(trigger);
    wrap.appendChild(sub);
    menu.appendChild(wrap);
  }

  function assignCalTicketToUser(ticketId, userId) {
    var clearAssign = (userId === null || userId === undefined || userId === '' || userId === 0 || userId === '0');
    var zugPayload = clearAssign ? null : userId;
    fetch(serviceApiBase + '/tickets.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ ticket_id: ticketId, zugewiesen_an: zugPayload })
    })
      .then(function(r) {
        if (!r.ok) {
          return r.text().then(function(t) {
            try { return JSON.parse(t); } catch (e) { return { success: false, error: t || ('HTTP ' + r.status) }; }
          });
        }
        return r.json();
      })
      .then(function(data) {
        if (data.success) {
          calendar.refetchEvents();
          if (typeof showToast === 'function') {
            showToast(clearAssign ? 'Bearbeiter entfernt' : 'Bearbeiter erfolgreich zugewiesen', 'success');
          }
        } else if (typeof showToast === 'function') {
          showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
      })
      .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Zuweisen des Bearbeiters', 'error');
      });
  }

  function buildCalEventGoToLinks(ev) {
    var props = ev.extendedProps || {};
    var base = calAppBase();
    var links = [];
    function add(label, href, external, icon) {
      if (!href) return;
      links.push({ label: label, href: href, external: !!external, icon: icon || 'goto' });
    }
    if (props.source === 'service') {
      if (isAdminOrTechniker && props.companyUrl) {
        add(cleanCalendarLabelText(props.companyName) || 'Firma', base + props.companyUrl, false, 'company');
      }
      if (props.customerUrl) {
        add(cleanCalendarLabelText(props.customerName) || 'Kunde', base + props.customerUrl, false, 'customer');
      }
      if (props.deviceUrl) {
        add(cleanCalendarLabelText(props.deviceName) || 'Gerät', base + props.deviceUrl, false, 'device');
      }
      if (props.mapsUrl) add('Route (Google Maps)', props.mapsUrl, true, 'maps');
      return links;
    }
    if (props.customerUrl) {
      add(cleanCalendarLabelText(props.customerName) || 'Kunde', base + props.customerUrl, false, 'customer');
    }
    if (props.mapsUrl) add('Route (Google Maps)', props.mapsUrl, true, 'maps');
    if (props.deviceUrl) add(cleanCalendarLabelText(props.deviceName) || 'Gerät', base + props.deviceUrl, false, 'device');
    if (props.source === 'todos' && props.todo_id) {
      add('Aufgabe', base + '/todos/?id=' + props.todo_id);
    }
    if (props.source === 'orders' && props.detailUrl) {
      add('Bestellung', base + props.detailUrl);
    }
    if (props.detailUrl && props.source !== 'service' && props.source !== 'orders' && props.source !== 'todos') {
      add(props.sourceLabel || 'Eintrag', base + props.detailUrl);
    }
    return links;
  }

  function deleteCalCustomEvent(customId) {
    if (!confirm('Termin wirklich löschen?')) return;
    fetch(apiBase + '/custom-events.php?id=' + customId, { method: 'DELETE', credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          calendar.refetchEvents();
          if (typeof showToast === 'function') showToast('Termin gelöscht', 'success');
        } else if (typeof showToast === 'function') {
          showToast(data.error || 'Löschen fehlgeschlagen', 'error');
        }
      });
  }

  function deleteCalTodo(todoId) {
    if (!confirm('Aufgabe wirklich löschen?')) return;
    fetch(todosApiBase + '/todos.php?id=' + todoId, { method: 'DELETE', credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          calendar.refetchEvents();
          if (typeof showToast === 'function') showToast('Aufgabe gelöscht', 'success');
        } else if (typeof showToast === 'function') {
          showToast(data.error || 'Löschen fehlgeschlagen', 'error');
        }
      });
  }

  function deleteCalAppointment(appointmentId) {
    if (!confirm('Termin wirklich löschen?')) return;
    fetch(serviceApiBase + '/appointments.php?id=' + appointmentId, { method: 'DELETE', credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          calendar.refetchEvents();
          if (typeof showToast === 'function') showToast('Termin gelöscht', 'success');
        } else if (typeof showToast === 'function') {
          showToast(data.error || 'Löschen fehlgeschlagen', 'error');
        }
      });
  }

  function renderCalEventContextMenu(ev) {
    var menu = document.getElementById('calEventContextMenu');
    if (!menu) return;
    menu.innerHTML = '';
    var props = ev.extendedProps || {};
    var goLinks = buildCalEventGoToLinks(ev);
    var isTodo = props.source === 'todos' && props.todo_id;
    var todoHref = isTodo ? (calAppBase() + '/todos/?id=' + props.todo_id) : null;
    var primaryHref = isTodo ? todoHref
      : (props.source === 'service' && props.detailUrl)
        ? (calAppBase() + props.detailUrl)
        : (goLinks.length ? goLinks[0].href : null);

    if (isTodo) {
      menu.appendChild(calCtxBtn('Aufgabe öffnen', 'detail', function() {
        window.location.href = todoHref;
      }));
      menu.appendChild(calCtxBtn('Im neuen Tab öffnen', 'tab', function() {
        window.open(todoHref, '_blank', 'noopener');
      }));
      menu.appendChild(calCtxDivider());
      menu.appendChild(calCtxBtn('Als erledigt markieren', 'check', function() {
        completeCalendarTodo(props.todo_id, true, null);
      }));
      menu.appendChild(calCtxBtn('Löschen', 'delete', function() { deleteCalTodo(props.todo_id); }, true));
    } else {
      menu.appendChild(calCtxBtn('Details anzeigen', 'detail', function() {
        if (props.source === 'service' && props.detailUrl) {
          window.location.href = calAppBase() + props.detailUrl;
          return;
        }
        openEventDetailModal(ev);
      }));

      if (primaryHref) {
        menu.appendChild(calCtxBtn('Im neuen Tab öffnen', 'tab', function() {
          window.open(primaryHref, '_blank', 'noopener');
        }));
      }

      if (goLinks.length) {
        appendCalGoToSubmenu(menu, goLinks, props.source === 'service');
      }
      appendCalAssigneeSubmenu(menu, ev);

      var hasActions = false;
      if (props.source === 'custom' && props.is_owner && props.custom_id) {
        if (!hasActions) { menu.appendChild(calCtxDivider()); hasActions = true; }
        menu.appendChild(calCtxBtn('Bearbeiten', 'edit', function() { openEventModal(props.custom_id); }));
        menu.appendChild(calCtxBtn('Löschen', 'delete', function() { deleteCalCustomEvent(props.custom_id); }, true));
      }
      if (props.source === 'service' && props.appointment_id) {
        if (!hasActions) { menu.appendChild(calCtxDivider()); hasActions = true; }
        menu.appendChild(calCtxBtn('Termin löschen', 'delete', function() { deleteCalAppointment(props.appointment_id); }, true));
      }
    }
  }

  function showCalEventContextMenu(clientX, clientY, ev, targetEl) {
    var menu = document.getElementById('calEventContextMenu');
    var backdrop = document.getElementById('calEventContextBackdrop');
    if (!menu || !ev) return;
    clearCalEventContextTargetHighlight();
    calEventContextEvent = ev;
    if (targetEl) {
      calEventContextTargetEl = targetEl;
      targetEl.classList.add('cal-event-context-active');
      if (document.activeElement === targetEl) targetEl.blur();
    }
    renderCalEventContextMenu(ev);
    menu.classList.remove('hidden');
    if (backdrop) backdrop.classList.remove('hidden');
    menu.style.left = clientX + 'px';
    menu.style.top = clientY + 'px';
    var rect = menu.getBoundingClientRect();
    var pad = 8;
    var mainContent = document.getElementById('main-content');
    var sidebarOffset = (window.matchMedia('(min-width: 1024px)').matches && mainContent)
      ? Math.max(0, Math.round(mainContent.getBoundingClientRect().left)) : 0;
    var minLeft = Math.max(pad, sidebarOffset + pad);
    var left = Math.min(Math.max(clientX, minLeft), Math.max(minLeft, window.innerWidth - rect.width - pad));
    var top = Math.min(Math.max(clientY, pad), Math.max(pad, window.innerHeight - rect.height - pad));
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }

  function openEventDetailModal(ev) {
    var props = ev.extendedProps || {};
    var titleEl = document.getElementById('event-detail-title');
    var bodyEl = document.getElementById('event-detail-body');
    var actionsEl = document.getElementById('event-detail-actions');
    titleEl.textContent = ev.title;
    var html = '';
    var timeStr = formatEventTime(ev.start, ev.end, ev.allDay);
    if (timeStr) html += '<p><strong>Zeit:</strong> ' + timeStr + '</p>';
    if (props.user) html += '<p><strong>Zugewiesen:</strong> ' + props.user + '</p>';
    if (props.owner) html += '<p><strong>Von:</strong> ' + props.owner + '</p>';
    if (props.source === 'service') {
      if (props.companyName) html += '<p><strong>Firma:</strong> ' + props.companyName + '</p>';
      if (props.ticket_status) html += '<p><strong>Status:</strong> ' + props.ticket_status + '</p>';
      if (props.ticket_nummer) html += '<p><strong>Ticket-Nr.:</strong> ' + props.ticket_nummer + '</p>';
      var base = calAppBase();
      if (props.detailUrl) html += '<p class="mt-2"><a href="' + base + props.detailUrl + '" class="text-primary-250 dark:text-primary-280 hover:underline">Ticket anzeigen &rarr;</a></p>';
      if (props.customerUrl) html += '<p><a href="' + base + props.customerUrl + '" class="text-primary-250 dark:text-primary-280 hover:underline" target="_blank" rel="noopener">Zum Kunden (' + (props.customerName || 'Kunde') + ') &rarr;</a></p>';
      if (props.mapsUrl) html += '<p><a href="' + props.mapsUrl + '" class="text-primary-250 dark:text-primary-280 hover:underline" target="_blank" rel="noopener">Route (Google Maps) &rarr;</a></p>';
      if (props.deviceUrl) html += '<p><a href="' + base + props.deviceUrl + '" class="text-primary-250 dark:text-primary-280 hover:underline">' + (props.deviceName || 'Gerät') + ' &rarr;</a></p>';
    } else if (props.source === 'custom') {
      bodyEl.innerHTML = '<p class="text-gray-500 dark:text-primary-220">Laden…</p>';
      actionsEl.innerHTML = '';
      if (props.is_owner) {
        var editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 dark:border-primary-320 text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140';
        editBtn.textContent = 'Bearbeiten';
        editBtn.onclick = function() {
          document.getElementById('event-detail-modal').classList.add('hidden');
          openEventModal(props.custom_id);
        };
        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'px-3 py-2 text-sm font-medium rounded-xl bg-red-600 text-white hover:bg-red-700';
        delBtn.textContent = 'Löschen';
        delBtn.onclick = function() {
          if (confirm('Termin wirklich löschen?')) {
            fetch(apiBase + '/custom-events.php?id=' + props.custom_id, { method: 'DELETE', credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(data) { if (data.success) { calendar.refetchEvents(); document.getElementById('event-detail-modal').classList.add('hidden'); } });
          }
        };
        actionsEl.appendChild(editBtn);
        actionsEl.appendChild(delBtn);
      }
      document.getElementById('event-detail-modal').classList.remove('hidden');
      fetch(apiBase + '/custom-events.php?id=' + props.custom_id, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          var html = '';
          if (timeStr) html += '<p><strong>Zeit:</strong> ' + timeStr + '</p>';
          if (props.owner) html += '<p><strong>Von:</strong> ' + props.owner + '</p>';
          if (data.success && data.event) {
            var event = data.event;
            if (event.description) html += '<p class="mt-2">' + (event.description.replace(/\n/g, '<br>')) + '</p>';
            if (event.invitees && event.invitees.length) {
              var names = event.invitees.map(function(inv) { return (inv.vorname || '').trim() + ' ' + (inv.nachname || '').trim(); }).filter(Boolean);
              html += '<p class="mt-2"><strong>Eingeladene User:</strong></p><p class="text-gray-600 dark:text-primary-220">' + names.join(', ') + '</p>';
            }
            if (event.invite_emails && event.invite_emails.trim()) {
              var mails = event.invite_emails.split(/[\s,;]+/).map(function(s) { return s.trim(); }).filter(Boolean);
              html += '<p class="mt-2"><strong>Eingeladene E-Mails:</strong></p><p class="text-gray-600 dark:text-primary-220">' + mails.join(', ') + '</p>';
            }
            if (event.meeting_link) html += '<p class="mt-2"><strong>Meeting-Link:</strong><br><a href="' + event.meeting_link + '" target="_blank" rel="noopener" class="text-primary-250 dark:text-primary-280 hover:underline">Meeting beitreten &rarr;</a></p>';
          } else {
            if (props.description) html += '<p class="mt-2">' + (props.description.replace(/\n/g, '<br>')) + '</p>';
            if (props.invite_emails && props.invite_emails.trim()) html += '<p class="mt-2"><strong>Eingeladene E-Mails:</strong></p><p class="text-gray-600 dark:text-primary-220">' + props.invite_emails.trim().replace(/[\s,;]+/g, ', ') + '</p>';
            if (props.meeting_link) html += '<p class="mt-2"><strong>Meeting-Link:</strong><br><a href="' + props.meeting_link + '" target="_blank" rel="noopener" class="text-primary-250 dark:text-primary-280 hover:underline">Meeting beitreten &rarr;</a></p>';
          }
          bodyEl.innerHTML = html || '<p class="text-gray-500 dark:text-primary-220">Keine weiteren Angaben.</p>';
        })
        .catch(function() {
          var html = '';
          if (timeStr) html += '<p><strong>Zeit:</strong> ' + timeStr + '</p>';
          if (props.owner) html += '<p><strong>Von:</strong> ' + props.owner + '</p>';
          if (props.description) html += '<p class="mt-2">' + (props.description.replace(/\n/g, '<br>')) + '</p>';
          if (props.invite_emails && props.invite_emails.trim()) html += '<p class="mt-2"><strong>Eingeladene E-Mails:</strong></p><p class="text-gray-600 dark:text-primary-220">' + props.invite_emails.trim().replace(/[\s,;]+/g, ', ') + '</p>';
          if (props.meeting_link) html += '<p class="mt-2"><strong>Meeting-Link:</strong><br><a href="' + props.meeting_link + '" target="_blank" rel="noopener" class="text-primary-250 dark:text-primary-280 hover:underline">Meeting beitreten &rarr;</a></p>';
          bodyEl.innerHTML = html || '<p class="text-gray-500 dark:text-primary-220">Keine weiteren Angaben.</p>';
        });
      return;
    } else {
      if (props.description) html += '<p class="mt-2">' + (props.description.replace(/\n/g, '<br>')) + '</p>';
      if (props.meeting_link) html += '<p class="mt-2"><a href="' + props.meeting_link + '" target="_blank" rel="noopener" class="text-primary-250 dark:text-primary-280 hover:underline">Meeting beitreten &rarr;</a></p>';
      if (props.detailUrl) {
        var href = calAppBase() + props.detailUrl;
        html += '<p class="mt-2"><a href="' + href + '" class="text-primary-250 dark:text-primary-280 hover:underline">Zum Eintrag &rarr;</a></p>';
      }
    }
    bodyEl.innerHTML = html || '<p class="text-gray-500 dark:text-primary-220">Keine weiteren Angaben.</p>';
    actionsEl.innerHTML = '';
    if (props.source === 'custom' && props.is_owner) {
      var editBtn2 = document.createElement('button');
      editBtn2.type = 'button';
      editBtn2.className = 'px-3 py-2 text-sm font-medium rounded-xl border border-gray-300 dark:border-primary-320 text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140';
      editBtn2.textContent = 'Bearbeiten';
      editBtn2.onclick = function() {
        document.getElementById('event-detail-modal').classList.add('hidden');
        openEventModal(props.custom_id);
      };
      var delBtn2 = document.createElement('button');
      delBtn2.type = 'button';
      delBtn2.className = 'px-3 py-2 text-sm font-medium rounded-xl bg-red-600 text-white hover:bg-red-700';
      delBtn2.textContent = 'Löschen';
      delBtn2.onclick = function() {
        if (confirm('Termin wirklich löschen?')) {
          fetch(apiBase + '/custom-events.php?id=' + props.custom_id, { method: 'DELETE', credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) { calendar.refetchEvents(); document.getElementById('event-detail-modal').classList.add('hidden'); } });
        }
      };
      actionsEl.appendChild(editBtn2);
      actionsEl.appendChild(delBtn2);
    }
    document.getElementById('event-detail-modal').classList.remove('hidden');
  }

  const calendarEl = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: initView,
    initialDate: initialDate,
    locale: 'de',
    firstDay: 1,
    weekends: initWeekends,
    hiddenDays: initHiddenDays,
    slotMinTime: CALENDAR_FULL_DAY.slotMinTime,
    slotMaxTime: CALENDAR_FULL_DAY.slotMaxTime,
    slotHeight: calculateSlotHeight(
      CALENDAR_FULL_DAY.slotMinTime,
      CALENDAR_FULL_DAY.slotMaxTime,
      initView
    ),
    businessHours: getAvailabilityBusinessHours(),
    nowIndicator: true,
    headerToolbar: false,
    navLinks: true,
    navLinkDayClick: function(date) {
      if (calendar.view && calendar.view.type === 'dayGridMonth') {
        calendar.changeView('timeGridDay', date);
        switchCalendarView('timeGridDay', true);
      }
    },
    dayMaxEvents: true,
    eventDisplay: 'block',
    eventOrder: 'start,-duration,title',
    moreLinkText: function(n) { return '+' + n + ' weitere'; },
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    defaultTimedEventDuration: '00:30:00',
    events: fetchEvents,
    dateClick: function(info) {
      var d = info.date;
      var clickedDateStr = d.toISOString();
      var now = Date.now();
      var isAllDay = info.allDay || false;
      
      // Prüfen ob es ein Doppelklick auf dieselbe Stelle ist (innerhalb von 500ms)
      var isDoubleClick = (lastClickedDate === clickedDateStr && (now - lastClickTime) < 500);
      
      selectedDate = new Date(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes());
      
      var currentView = calendar.view.type;
      
      // Markiere den ausgewählten Bereich
      if (currentView === 'dayGridMonth') {
        highlightSelectedDayInCalendar(info.dayEl);
      } else {
        highlightSelectedDayInCalendar(null);
      }
      
      if (isDoubleClick) {
        if (isAdminOrTechniker) {
          var modalDate = new Date(d);
          if (currentView !== 'dayGridMonth' && !isAllDay) {
            modalDate.setMinutes(0, 0, 0);
          }
          openEventModal(null, modalDate, isAllDay);
        }
        lastClickedDate = null;
        lastClickTime = 0;
      } else {
        // Erster Klick: Nur markieren
        lastClickedDate = clickedDateStr;
        lastClickTime = now;
      }
    },
    eventClassNames: function(arg) {
      var classes = [];
      var isMonthView = arg.view && arg.view.type === 'dayGridMonth';
      if (isMonthView) {
        classes.push('cal-month-event');
        if (arg.event.editable === false) classes.push('cal-month-event--locked');
      }
      if (arg.event.editable === false) {
        classes.push('fc-event-not-editable');
      } else if (arg.event.editable === true) {
        classes.push('fc-event-editable');
      }
      return classes;
    },
    eventDrop: function(info) {
      var ev = info.event;
      var props = ev.extendedProps || {};
      var startStr = toISO(ev.start);
      // Wenn kein End-Datum vorhanden (z.B. bei Ganztägig-zu-Zeitslot), berechne End = Start + 1 Stunde
      var endDate = ev.end;
      if (!endDate && ev.start) {
        endDate = new Date(ev.start.getTime());
        if (ev.allDay) {
          // Ganztägig bleibt ganztägig: End = Start + 1 Tag
          endDate.setDate(endDate.getDate() + 1);
        } else {
          // Zeitslot: End = Start + 1 Stunde
          endDate.setHours(endDate.getHours() + 1);
        }
      }
      var endStr = toISO(endDate);
      // Beim Verschieben in Zeitslot: allDay wird false, Zeit kommt von ev.start/ev.end
      var allDay = ev.allDay === true;

      function revert() { info.revert(); }

      // Nicht editierbare Events sofort zurücksetzen
      if (ev.editable === false) {
        revert();
        return;
      }

      if (props.source === 'custom' && props.custom_id) {
        fetch(apiBase + '/custom-events.php', {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id: props.custom_id, start_at: startStr, end_at: endStr, all_day: allDay })
        })
          .then(function(r) { return r.json(); })
          .then(function(data) { 
            if (!data.success) revert(); 
            else calendar.refetchEvents();
          })
          .catch(revert);
        return;
      }
      if (props.source === 'service' && props.appointment_id) {
        fetch(serviceApiBase + '/appointments.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            id: props.appointment_id,
            typ: props.isFaellig ? 'faellig' : 'geplant',
            start_datum: startStr,
            ende_datum: endStr,
            titel: props.appointment_titel || null
          })
        })
          .then(function(r) { return r.json(); })
          .then(function(data) { if (!data.success) revert(); else calendar.refetchEvents(); })
          .catch(revert);
        return;
      }
      if (props.source === 'service' && props.ticket_id) {
        var payload = { ticket_id: props.ticket_id };
        if (ev.id.indexOf('ticket_plan_') === 0) {
          payload.geplant_datum = startStr;
          payload.geplant_datum_ende = endStr;
        } else if (ev.id.indexOf('ticket_faellig_') === 0) {
          payload.faellig_datum = startStr;
          payload.faellig_datum_ende = endStr;
        } else { revert(); return; }
        fetch(serviceApiBase + '/tickets.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        })
          .then(function(r) { return r.json(); })
          .then(function(data) { if (!data.success) revert(); else calendar.refetchEvents(); })
          .catch(revert);
        return;
      }
      if (props.source === 'todos' && props.todo_id) {
        var todoFaelligAm = startStr;
        if (allDay) {
          var dayPart = startStr.slice(0, 10);
          todoFaelligAm = dayPart + ' 12:00:00';
        }
        fetch(todosApiBase + '/todos.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ todo_id: props.todo_id, faellig_am: todoFaelligAm })
        })
          .then(function(r) { return r.json(); })
          .then(function(data) { 
            if (!data.success) revert(); 
            else calendar.refetchEvents();
          })
          .catch(revert);
        return;
      }
      if (props.source === 'my_vacation' && props.vacation_id) {
        var newDate = startStr.slice(0, 10);
        fetch(vacationApiBase + '/vacation.php', {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id: props.vacation_id, date: newDate })
        })
          .then(function(r) { return r.json(); })
          .then(function(data) { if (!data.success) revert(); })
          .catch(revert);
        return;
      }
      revert();
    },
    eventResize: function(info) {
      var ev = info.event;
      var props = ev.extendedProps || {};
      var startStr = toISO(ev.start);
      var endDate = ev.end;
      if (!endDate && ev.start) {
        endDate = new Date(ev.start.getTime());
        endDate.setHours(endDate.getHours() + 1);
      }
      var endStr = toISO(endDate);
      var allDay = ev.allDay;
      function revert() { info.revert(); }

      // Nicht editierbare Events sofort zurücksetzen
      if (ev.editable === false) {
        revert();
        return;
      }

      // Nur eigene Kalender-Termine können in der Länge geändert werden
      if (props.source === 'custom' && props.custom_id && props.is_owner) {
        fetch(apiBase + '/custom-events.php', {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id: props.custom_id, start_at: startStr, end_at: endStr, all_day: allDay })
        })
          .then(function(r) { return r.json(); })
          .then(function(data) { 
            if (!data.success) revert(); 
            else calendar.refetchEvents();
          })
          .catch(revert);
        return;
      }
      // Ticket-Termine (ticket_appointments): Dauer und Start speichern
      if (props.source === 'service' && props.appointment_id) {
        fetch(serviceApiBase + '/appointments.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            id: props.appointment_id,
            typ: props.isFaellig ? 'faellig' : 'geplant',
            start_datum: startStr,
            ende_datum: endStr,
            titel: props.appointment_titel || null
          })
        })
          .then(function(r) { return r.json(); })
          .then(function(data) { if (!data.success) revert(); else calendar.refetchEvents(); })
          .catch(revert);
        return;
      }
      // Tickets: Dauer (Endzeit) speichern
      if (props.source === 'service' && props.ticket_id) {
        var payload = { ticket_id: props.ticket_id };
        if (ev.id.indexOf('ticket_plan_') === 0) {
          payload.geplant_datum = startStr;
          payload.geplant_datum_ende = endStr;
        } else if (ev.id.indexOf('ticket_faellig_') === 0) {
          payload.faellig_datum = startStr;
          payload.faellig_datum_ende = endStr;
        } else { revert(); return; }
        fetch(serviceApiBase + '/tickets.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        })
          .then(function(r) { return r.json(); })
          .then(function(data) { if (!data.success) revert(); else calendar.refetchEvents(); })
          .catch(revert);
        return;
      }

      // Alle anderen Events können nicht in der Länge geändert werden
      revert();
    },
    eventDidMount: function(info) {
      var ev = info.event;
      var el = info.el;
      var isMonthView = info.view && info.view.type === 'dayGridMonth';
      el._calFcEvent = ev;
      if (isMonthView) {
        applyMonthEventMount(el, ev);
        scheduleCalMonthReflow();
      } else if (ev.backgroundColor) {
        el.style.backgroundColor = ev.backgroundColor;
        el.style.borderColor = ev.borderColor || ev.backgroundColor;
        if (ev.textColor) el.style.color = ev.textColor;
      } else if (ev.textColor) {
        el.style.color = ev.textColor;
      }
      if (!el._calCtxBound) {
        el._calCtxBound = true;
        el.addEventListener('mousedown', function(e) {
          if (e.button === 2) e.preventDefault();
        });
        el.addEventListener('contextmenu', function(e) {
          if (e.target.closest('.cal-todo-done')) return;
          e.preventDefault();
          e.stopPropagation();
          showCalEventContextMenu(e.clientX, e.clientY, ev, el);
        });
      }
    },
    eventContent: function(arg) {
      var props = arg.event.extendedProps || {};
      var title = arg.event.title;
      var isMonthView = arg.view && arg.view.type === 'dayGridMonth';
      if (props.source === 'service') {
        title = props.appointment_id ? arg.event.title : getTicketCalendarLabel(props);
      }
      var timeStr = formatEventTime(arg.event.start, arg.event.end, arg.event.allDay);
      if (isMonthView) {
        return renderMonthEventContent(arg, title, props);
      }
      var label = props.sourceLabel || (props.source === 'custom' ? 'Eigener Termin' : (props.source || ''));
      var isEditable = arg.event.editable === true;
      var notEditable = arg.event.editable === false;
      var wrap = document.createElement('div');
      wrap.className = 'fc-event-main-frame flex flex-col';
      var topRow = document.createElement('div');
      topRow.className = 'flex items-center gap-1 w-full min-w-0';

      var isTodoEditable = props.source === 'todos' && props.todo_id && isEditable;
      if (isTodoEditable) {
        topRow.appendChild(createCalTodoDoneCheckbox(props.todo_id));
      } else {
        // Icon links: Schloss oder Verschieben
        var iconWrap = document.createElement('span');
        iconWrap.className = 'fc-event-status-icon inline-flex items-center flex-shrink-0';
        if (notEditable) {
          iconWrap.title = 'Nicht verschiebbar';
          iconWrap.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="11" height="11" style="opacity:0.85"><path fill-rule="evenodd" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7V4.5A3.5 3.5 0 0 0 8 1Zm2 6V4.5a2 2 0 1 0-4 0V7h4Z" clip-rule="evenodd"/></svg>';
        } else if (isEditable) {
          var inTimeGrid = arg.view && (arg.view.type === 'timeGridWeek' || arg.view.type === 'timeGridDay');
          iconWrap.title = inTimeGrid ? 'Verschiebbar. Dauer anpassen: unten am Termin ziehen.' : 'Verschiebbar. Dauer anpassen: in Wochen- oder Tagesansicht unten ziehen.';
          iconWrap.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="11" height="11" style="opacity:0.7"><path fill-rule="evenodd" d="M8 2a.75.75 0 0 1 .75.75v3.69l1.72-1.72a.75.75 0 0 1 1.06 1.06l-3 3a.75.75 0 0 1-1.06 0l-3-3a.75.75 0 0 1 1.06-1.06l1.72 1.72V2.75A.75.75 0 0 1 8 2ZM8 14a.75.75 0 0 1-.75-.75v-3.69l-1.72 1.72a.75.75 0 0 1-1.06-1.06l3-3a.75.75 0 0 1 1.06 0l3 3a.75.75 0 0 1-1.06 1.06l-1.72-1.72v3.69A.75.75 0 0 1 8 14Z" clip-rule="evenodd"/></svg>';
        }
        topRow.appendChild(iconWrap);
      }

      var textCol = document.createElement('div');
      textCol.className = 'flex-1 min-w-0';
      var titleRow = document.createElement('div');
      titleRow.className = 'flex items-center gap-1';
      if (props.source === 'service' && props.isFaellig) {
        var redDot = document.createElement('span');
        redDot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0';
        redDot.title = 'Fällig';
        titleRow.appendChild(redDot);
      }
      if (props.invited) {
        var inviteIcon = document.createElement('span');
        inviteIcon.className = 'fc-event-invite-icon inline-flex flex-shrink-0 text-current';
        inviteIcon.setAttribute('aria-hidden', 'true');
        inviteIcon.innerHTML = '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M7.926 10.898 15 7.727m-7.074 5.39L15 16.29M8 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm12 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm0-11a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/></svg>';
        titleRow.appendChild(inviteIcon);
        title = (title || '').replace(/^\s*📅\s*/, '');
      }
      if (props.source === 'custom') {
        if (props.invite_emails && String(props.invite_emails).trim()) {
          var mailIcon = document.createElement('span');
          mailIcon.className = 'fc-event-mail-icon inline-flex flex-shrink-0 text-current';
          mailIcon.setAttribute('aria-hidden', 'true');
          mailIcon.title = 'Externe E-Mail-Einladung';
          mailIcon.innerHTML = '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.7141 15h4.268c.4043 0 .732-.3838.732-.8571V3.85714c0-.47338-.3277-.85714-.732-.85714H6.71411c-.55228 0-1 .44772-1 1v4m10.99999 7v-3h3v3h-3Zm-3 6H6.71411c-.55228 0-1-.4477-1-1 0-1.6569 1.34315-3 3-3h2.99999c1.6569 0 3 1.3431 3 3 0 .5523-.4477 1-1 1Zm-1-9.5c0 1.3807-1.1193 2.5-2.5 2.5s-2.49999-1.1193-2.49999-2.5S8.8334 9 10.2141 9s2.5 1.1193 2.5 2.5Z"/></svg>';
          titleRow.appendChild(mailIcon);
        }
        if (props.meeting_link && String(props.meeting_link).trim()) {
          var meetingIcon = document.createElement('span');
          meetingIcon.className = 'fc-event-meeting-icon inline-flex flex-shrink-0 text-current';
          meetingIcon.setAttribute('aria-hidden', 'true');
          meetingIcon.title = 'Meeting-Link';
          meetingIcon.innerHTML = '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/></svg>';
          titleRow.appendChild(meetingIcon);
        }
      }
      var titleEl = document.createElement('div');
      titleEl.className = 'fc-event-title font-medium';
      titleEl.textContent = title;
      titleRow.appendChild(titleEl);
      textCol.appendChild(titleRow);
      if (props.source === 'service') {
        var serviceInfo = document.createElement('div');
        serviceInfo.className = 'fc-event-service-info mt-0.5 space-y-0.5 text-[10px] opacity-95 leading-tight';
        
        var titelStr = cleanCalendarLabelText(props.titel) || (arg.event.title || '').replace(/^SRV\s*\[[^\]]*\]\s*/, '').trim() || '—';
        var line1 = document.createElement('div');
        line1.className = 'truncate';
        line1.title = titelStr;
        line1.textContent = 'Betreff: ' + titelStr;
        serviceInfo.appendChild(line1);
        if (props.ticket_nummer && !props.appointment_id) {
          var line2 = document.createElement('div');
          line2.textContent = 'Nr.: ' + props.ticket_nummer;
          serviceInfo.appendChild(line2);
        }
        var kundeFirma = (props.customerName && props.customerName.trim()) ? props.customerName : ((props.companyName && props.companyName.trim()) ? props.companyName : '');
        if (kundeFirma) {
          var line3 = document.createElement('div');
          line3.className = 'truncate';
          line3.title = kundeFirma;
          line3.textContent = (props.customerName && props.customerName.trim()) ? 'Kunde: ' + props.customerName : 'Firma: ' + props.companyName;
          serviceInfo.appendChild(line3);
        }
        var lineStatus = document.createElement('div');
        lineStatus.textContent = 'Status: ' + (props.ticket_status || '—');
        serviceInfo.appendChild(lineStatus);
        textCol.appendChild(serviceInfo);
      }
      if (timeStr && !arg.event.allDay) {
        var timeEl = document.createElement('div');
        timeEl.className = 'fc-event-time text-opacity-90 text-xs';
        timeEl.textContent = timeStr;
        textCol.appendChild(timeEl);
      }
      topRow.appendChild(textCol);
      wrap.appendChild(topRow);
      if (props.source !== 'service' && label) {
        var badge = document.createElement('span');
        badge.className = 'fc-event-badge text-[10px] opacity-80 mt-0.5';
        badge.textContent = label;
        wrap.appendChild(badge);
      }
      return { domNodes: [wrap] };
    },
    eventClick: function(info) {
      if (info.jsEvent.target && info.jsEvent.target.closest && info.jsEvent.target.closest('.cal-todo-done')) {
        return;
      }
      info.jsEvent.preventDefault();
      var props = info.event.extendedProps || {};
      if (props.source === 'service' && props.detailUrl) {
        window.location.href = calAppBase() + props.detailUrl;
        return;
      }
      if (props.source === 'todos' && props.todo_id) {
        window.location.href = calAppBase() + '/todos/?id=' + props.todo_id;
        return;
      }
      openEventDetailModal(info.event);
    }
  });
  calendar.render();
  initTimeSliders();
  initCalMonthEventInteractions();
  scheduleCalMonthReflow();

  var calEventCtxBackdrop = document.getElementById('calEventContextBackdrop');
  if (calEventCtxBackdrop) {
    calEventCtxBackdrop.addEventListener('click', hideCalEventContextMenu);
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideCalEventContextMenu();
  });
  document.addEventListener('scroll', hideCalEventContextMenu, true);

  calendar.on('eventsSet', function() {
    calMonthTooltipHide();
    scheduleCalMonthReflow();
  });

  function updateMiniMonthLabel() {
    var miniLabel = document.getElementById('cal-mini-month-label');
    if (!miniLabel) return;
    var focus = calendar.getDate();
    var labelText = new Date(focus.getFullYear(), focus.getMonth(), 1).toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
    miniLabel.textContent = labelText.charAt(0).toUpperCase() + labelText.slice(1);
  }

  function updateTitle() {
    const view = calendar.view;
    var compactTitleEl = document.getElementById('cal-title-compact');
    var periodText = '';

    if (view.type !== 'dayGridMonth') {
      const start = view.currentStart;
      const end = view.currentEnd;
      periodText = start.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' – ' + end.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    updateMiniMonthLabel();
    if (compactTitleEl) {
      compactTitleEl.textContent = view.type === 'dayGridMonth'
        ? (document.getElementById('cal-mini-month-label')?.textContent || '')
        : periodText;
    }
  }
  calendar.on('datesSet', function() {
    updateTitle();
    if (calendarEl) calendarEl.setAttribute('data-current-view', calendar.view.type || '');
    scheduleCalMonthReflow();
    // Speichere die Mitte des sichtbaren Bereichs statt currentStart
    var view = calendar.view;
    var start = view.currentStart;
    var end = view.currentEnd;
    var middle = new Date((start.getTime() + end.getTime()) / 2);
    saveCurrentDate(middle);
    // Subscription-Events für den neuen Bereich nachladen (verhindert veraltete/Beispieldaten)
    if (isAdminOrTechniker && typeof loadSubscriptionEvents === 'function' && Array.isArray(subscriptions)) {
      subscriptions.filter(function(s) { return s.is_active == 1; }).forEach(function(sub) {
        loadSubscriptionEvents(sub.id);
      });
    }
  });
  if (calendarEl) calendarEl.setAttribute('data-current-view', calendar.view ? calendar.view.type : '');
  updateTitle();

  function navigateMiniCalendarMonth(delta) {
    var d = calendar.getDate();
    var targetMonth = d.getMonth() + delta;
    var lastDay = new Date(d.getFullYear(), targetMonth + 1, 0).getDate();
    calendar.gotoDate(new Date(d.getFullYear(), targetMonth, Math.min(d.getDate(), lastDay)));
  }

  function onSidebarNavPrev() {
    if (calendar.view.type === 'dayGridMonth') {
      navigateMiniCalendarMonth(-1);
    } else {
      calendar.prev();
    }
  }

  function onSidebarNavNext() {
    if (calendar.view.type === 'dayGridMonth') {
      navigateMiniCalendarMonth(1);
    } else {
      calendar.next();
    }
  }

  ['cal-mini-prev', 'cal-mini-next', 'cal-prev-mini', 'cal-next-mini'].forEach(function(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', function() {
      if (id.indexOf('prev') !== -1) onSidebarNavPrev();
      else onSidebarNavNext();
    });
  });

  var calendarLayoutEl = document.getElementById('calendar-layout');
  var calendarSidebarToggleBtn = document.getElementById('cal-sidebar-toggle');
  function applySidebarCollapsedState(collapsed) {
    if (!calendarLayoutEl) return;
    calendarLayoutEl.setAttribute('data-sidebar-collapsed', collapsed ? 'true' : 'false');
    var toggleLabel = collapsed ? 'Seitenleiste einblenden' : 'Seitenleiste ausblenden';
    if (calendarSidebarToggleBtn) {
      calendarSidebarToggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      calendarSidebarToggleBtn.title = toggleLabel;
    }
    var calendarSidebarToggleLabel = document.getElementById('cal-sidebar-toggle-label');
    if (calendarSidebarToggleLabel) {
      calendarSidebarToggleLabel.textContent = toggleLabel;
    }
    if (calendar && typeof calendar.updateSize === 'function') {
      setTimeout(function() { calendar.updateSize(); }, 210);
    }
  }
  function closeCalSettingsDropdownIfOpen() {
    var calSettingsBtn = document.getElementById('cal-settings-btn');
    var calSettingsMenu = document.getElementById('cal-settings-dropdown-menu');
    var calSettingsContainer = document.getElementById('cal-settings-dropdown-container');
    if (!calSettingsBtn || !calSettingsMenu || !calSettingsContainer) return;
    if (calSettingsMenu.classList.contains('hidden')) return;
    closeCalFilterDropdownPortal(calSettingsMenu, calSettingsContainer);
    calSettingsBtn.setAttribute('aria-expanded', 'false');
  }
  if (calendarLayoutEl) {
    var uiSettings = getUiSettings();
    applySidebarCollapsedState(uiSettings.sidebarCollapsed === true);
    if (calendarSidebarToggleBtn) {
      calendarSidebarToggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        var isCollapsed = calendarLayoutEl.getAttribute('data-sidebar-collapsed') === 'true';
        var nextCollapsed = !isCollapsed;
        applySidebarCollapsedState(nextCollapsed);
        saveUiSettings({ sidebarCollapsed: nextCollapsed });
        closeCalSettingsDropdownIfOpen();
      });
    }
  }
  var calendarSidebarExpandBtn = document.getElementById('cal-sidebar-expand');
  if (calendarLayoutEl && calendarSidebarExpandBtn) {
    calendarSidebarExpandBtn.addEventListener('click', function() {
      applySidebarCollapsedState(false);
      saveUiSettings({ sidebarCollapsed: false });
    });
  }

  var calViewSegment = document.getElementById('cal-view-segment');

  function calViewGetSegmentGap(track) {
    if (!track) return 3;
    var gap = parseFloat(getComputedStyle(track).getPropertyValue('--cal-view-gap'));
    if (!isNaN(gap) && gap > 0) return gap;
    return parseFloat(getComputedStyle(track).paddingTop) || 3;
  }

  function calViewLayoutSegmentThumb(track, thumb, btn, animate) {
    if (!track || !thumb || !btn) return null;
    var trackRect = track.getBoundingClientRect();
    var btnRect = btn.getBoundingClientRect();
    var left = btnRect.left - trackRect.left;
    var width = btnRect.width;
    thumb.style.top = '';
    thumb.style.height = '';
    if (animate) {
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          thumb.style.transition = 'left 0.22s cubic-bezier(0.32, 0.72, 0, 1), width 0.22s cubic-bezier(0.32, 0.72, 0, 1)';
          thumb.style.left = left + 'px';
          thumb.style.width = width + 'px';
        });
      });
    } else {
      thumb.style.transition = 'none';
      thumb.style.left = left + 'px';
      thumb.style.width = width + 'px';
    }
    return btn.getAttribute('data-view');
  }

  function calViewPositionSegmentThumb(segment) {
    if (!segment) return;
    var track = segment.querySelector('.cal-view-segment-track');
    var thumb = segment.querySelector('.cal-view-segment-thumb');
    var active = segment.querySelector('.cal-view-segment-item[aria-selected="true"]');
    if (!track || !thumb || !active) return;
    calViewLayoutSegmentThumb(track, thumb, active, false);
  }

  function calViewSetSegmentSelection(segment, viewName) {
    if (!segment) return;
    segment.querySelectorAll('.cal-view-segment-item').forEach(function(btn) {
      var selected = btn.getAttribute('data-view') === viewName;
      btn.setAttribute('aria-selected', selected ? 'true' : 'false');
      btn.tabIndex = selected ? 0 : -1;
    });
  }

  function calViewSelectSegmentUi(segment, viewName) {
    if (!segment) return;
    calViewSetSegmentSelection(segment, viewName);
    calViewPositionSegmentThumb(segment);
  }

  function calViewSegmentHitFromX(track, clientX) {
    var items = Array.prototype.slice.call(track.querySelectorAll('.cal-view-segment-item'));
    if (!items.length) return null;
    var trackRect = track.getBoundingClientRect();
    var x = clientX - trackRect.left;
    var best = items[0];
    var bestDist = Infinity;
    items.forEach(function(btn) {
      var r = btn.getBoundingClientRect();
      var cx = r.left + r.width / 2 - trackRect.left;
      var d = Math.abs(cx - x);
      if (d < bestDist) {
        bestDist = d;
        best = btn;
      }
    });
    return { view: best.getAttribute('data-view'), btn: best };
  }

  function calViewSnapSegmentThumbToTarget(segment, targetBtn, animate) {
    var track = segment.querySelector('.cal-view-segment-track');
    var thumb = segment.querySelector('.cal-view-segment-thumb');
    if (!track || !thumb || !targetBtn) return null;
    return calViewLayoutSegmentThumb(track, thumb, targetBtn, animate);
  }

  function calViewDragSegmentThumbFree(segment, clientX, dragState) {
    var track = segment.querySelector('.cal-view-segment-track');
    var thumb = segment.querySelector('.cal-view-segment-thumb');
    if (!track || !thumb || !dragState) return null;
    var trackRect = track.getBoundingClientRect();
    var gap = calViewGetSegmentGap(track);
    var minLeft = gap;
    var maxLeft = Math.max(minLeft, track.clientWidth - dragState.thumbWidth - gap);
    var left = clientX - trackRect.left - dragState.grabOffsetX;
    left = Math.max(minLeft, Math.min(left, maxLeft));
    thumb.style.transition = 'none';
    thumb.style.top = '';
    thumb.style.height = '';
    thumb.style.left = left + 'px';
    thumb.style.width = dragState.thumbWidth + 'px';
    var hit = calViewSegmentHitFromX(track, clientX);
    return hit ? hit.view : null;
  }

  function calViewInitSegmentDrag(segment) {
    if (!segment || segment._calViewSegmentDragInit) return;
    segment._calViewSegmentDragInit = true;
    var track = segment.querySelector('.cal-view-segment-track');
    if (!track) return;
    var dragState = null;

    track.addEventListener('pointerdown', function(e) {
      if (e.button !== 0) return;
      var thumb = segment.querySelector('.cal-view-segment-thumb');
      if (!thumb) return;
      var thumbRect = thumb.getBoundingClientRect();
      var item = e.target.closest('.cal-view-segment-item');
      dragState = {
        pointerId: e.pointerId,
        startX: e.clientX,
        moved: false,
        grabOffsetX: e.clientX - thumbRect.left,
        thumbWidth: thumbRect.width,
        view: item ? item.getAttribute('data-view') : null
      };
      thumb.style.transition = 'none';
      track.setPointerCapture(e.pointerId);
      e.preventDefault();
    });

    track.addEventListener('pointermove', function(e) {
      if (!dragState || dragState.pointerId !== e.pointerId) return;
      if (Math.abs(e.clientX - dragState.startX) > 3) dragState.moved = true;
      dragState.view = calViewDragSegmentThumbFree(segment, e.clientX, dragState);
    });

    function endDrag(e) {
      if (!dragState || dragState.pointerId !== e.pointerId) return;
      try { track.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }
      var hit = calViewSegmentHitFromX(track, e.clientX);
      var view = (dragState.moved && dragState.view) ? dragState.view : (
        hit ? hit.view : (dragState.view || 'dayGridMonth')
      );
      if (!dragState.moved && e.target.closest('.cal-view-segment-item')) {
        view = e.target.closest('.cal-view-segment-item').getAttribute('data-view') || view;
      }
      var prevBtn = segment.querySelector('.cal-view-segment-item[aria-selected="true"]');
      var prevView = prevBtn ? prevBtn.getAttribute('data-view') : 'dayGridMonth';
      var targetBtn = hit ? hit.btn : null;
      if (!targetBtn) {
        segment.querySelectorAll('.cal-view-segment-item').forEach(function(btn) {
          if (btn.getAttribute('data-view') === view) targetBtn = btn;
        });
      }
      calViewSetSegmentSelection(segment, view);
      calViewSnapSegmentThumbToTarget(segment, targetBtn, !!dragState.moved);
      if (view && view !== prevView) {
        switchCalendarView(view, false);
      }
      dragState = null;
    }

    track.addEventListener('pointerup', endDrag);
    track.addEventListener('pointercancel', function(e) {
      if (!dragState || dragState.pointerId !== e.pointerId) return;
      dragState = null;
      calViewPositionSegmentThumb(segment);
    });
  }

  function switchCalendarView(viewName, skipCalendarChange) {
    if (!viewName) return;
    if (!skipCalendarChange) {
      calendar.changeView(viewName);
      if (viewName === 'timeGridDay' || viewName === 'timeGridWeek') {
        calendar.gotoDate(new Date());
      }
    }
    if (calendarEl) calendarEl.setAttribute('data-current-view', viewName);
    if (calViewSegment) calViewSelectSegmentUi(calViewSegment, viewName);
    saveView(viewName);
    setTimeout(applyWeekendSettings, 0);
  }

  if (calViewSegment) {
    calViewInitSegmentDrag(calViewSegment);
    switchCalendarView(getSavedView(), true);
    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        calViewPositionSegmentThumb(calViewSegment);
      });
    });
    window.addEventListener('resize', function() {
      calViewPositionSegmentThumb(calViewSegment);
    });
  }
  
  // Einstellungen beim Start anwenden
  setTimeout(applyWeekendSettings, 100);

  document.querySelectorAll('input[name="cal_filter"]').forEach(function(cb) {
    cb.addEventListener('change', function() {
      saveFiltersToStorage();
      calendar.refetchEvents();
      scheduleMiniCalEventDaysRefresh();
    });
  });
  document.querySelectorAll('.cal-other-user-cb, input[name="cal_other_user[]"]').forEach(function(cb) {
    cb.addEventListener('change', function() {
      saveFiltersToStorage();
      calendar.refetchEvents();
      scheduleMiniCalEventDaysRefresh();
    });
  });

  if (isAdminOrTechniker) {
    calInitAllToggleTrackDrags(document.getElementById('calendar-sidebar'));
    document.querySelectorAll('.cal-filter-item').forEach(function(item) {
      item.addEventListener('click', function(e) {
        if (item._calToggleJustDragged) {
          e.preventDefault();
          e.stopPropagation();
          return;
        }
        if (e.target.closest('.cal-color-btn')) {
          e.preventDefault();
        }
      });
    });
  }

  // Farbauswahl: Dropdown füllen und öffnen
  var colorDropdown = document.getElementById('cal-color-dropdown');
  var colorOptionsContainer = document.getElementById('cal-color-options');

  function calColorHighlightActive(currentHex) {
    if (!colorOptionsContainer) return;
    colorOptionsContainer.querySelectorAll('.cal-color-option').forEach(function(o) {
      o.classList.toggle('cal-color-option--active', o.getAttribute('data-hex') === currentHex);
    });
  }

  if (colorOptionsContainer) {
    PREDEFINED_COLORS.forEach(function(hex) {
      var opt = document.createElement('button');
      opt.type = 'button';
      opt.className = 'cal-color-option';
      opt.style.backgroundColor = hex;
      opt.setAttribute('data-hex', hex);
      opt.addEventListener('click', function() {
        var key = colorDropdown.getAttribute('data-current-key');
        if (key) {
          saveCalendarColor(key, hex);
          document.querySelectorAll('.cal-color-btn[data-color-key="' + key + '"]').forEach(function(b) { b.style.backgroundColor = hex; });
          colorDropdown.classList.add('hidden');
          colorDropdown.removeAttribute('data-current-key');
          calendar.refetchEvents();
        }
      });
      colorOptionsContainer.appendChild(opt);
    });
  }
  document.querySelectorAll('.cal-color-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var key = btn.getAttribute('data-color-key');
      if (!key || !colorDropdown) return;
      colorDropdown.setAttribute('data-current-key', key);
      calColorHighlightActive(btn.style.backgroundColor ? btn.style.backgroundColor : '');
      var colors = getCalendarColors();
      var currentHex = colors[key] || '';
      calColorHighlightActive(currentHex);
      var rect = btn.getBoundingClientRect();
      var dropW = 210;
      var dropH = 260;
      var left = rect.right + 8;
      var top = rect.top - 8;
      if (left + dropW > window.innerWidth - 8) left = rect.left - dropW - 8;
      if (top + dropH > window.innerHeight - 8) top = window.innerHeight - dropH - 8;
      if (top < 8) top = 8;
      colorDropdown.style.left = left + 'px';
      colorDropdown.style.top = top + 'px';
      colorDropdown.style.position = 'fixed';
      colorDropdown.classList.remove('hidden');
    });
  });
  document.addEventListener('click', function(e) {
    if (colorDropdown && !colorDropdown.classList.contains('hidden')) {
      if (!e.target.closest('#cal-color-dropdown') && !e.target.closest('.cal-color-btn')) {
        colorDropdown.classList.add('hidden');
        colorDropdown.removeAttribute('data-current-key');
      }
    }
  });
  if (colorDropdown) {
    colorDropdown.addEventListener('click', function(e) { e.stopPropagation(); });
  }

  // Mini-Kalender: aktueller Tag + ausgewählter Tag hervorheben; Klick springt zum Tag
  function stripTime(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function getVisibleWeekRange() {
    if (!calendar.view || calendar.view.type !== 'timeGridWeek') return null;
    var start = calendar.view.activeStart || calendar.view.currentStart;
    var end = calendar.view.activeEnd || calendar.view.currentEnd;
    if (!start || !end) return null;
    var endInclusive = stripTime(end);
    endInclusive.setDate(endInclusive.getDate() - 1);
    return { start: stripTime(start), end: endInclusive };
  }

  function isDateInVisibleWeek(date) {
    var range = getVisibleWeekRange();
    if (!range) return false;
    var day = stripTime(date);
    return day >= range.start && day <= range.end;
  }

  function buildMiniCalCellClasses(cellDate, isOutsideMonth) {
    var today = stripTime(new Date());
    var day = stripTime(cellDate);
    var cls = 'is-clickable';
    if (isOutsideMonth) cls += ' is-outside';
    if (day.getTime() === today.getTime()) cls += ' is-today';
    if (selectedDate && stripTime(selectedDate).getTime() === day.getTime()) cls += ' is-selected';
    return cls;
  }

  function weekRowShowsVisibleWeek(dates) {
    for (var i = 0; i < dates.length; i++) {
      if (isDateInVisibleWeek(dates[i])) return true;
    }
    return false;
  }

  var miniCalEventDays = new Map();
  var miniCalEventDaysToken = 0;

  function formatDateKey(date) {
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function buildMiniCalEntries(year, month) {
    var first = new Date(year, month, 1);
    var startDow = first.getDay() - 1;
    if (startDow < 0) startDow = 6;
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var prevDays = new Date(year, month, 0).getDate();
    var entries = [];
    for (var i = 0; i < startDow; i++) {
      var prevDay = prevDays - startDow + 1 + i;
      entries.push({ date: new Date(year, month - 1, prevDay), label: prevDay, outside: true });
    }
    for (var d = 1; d <= daysInMonth; d++) {
      entries.push({ date: new Date(year, month, d), label: d, outside: false });
    }
    var rest = entries.length % 7 === 0 ? 0 : 7 - (entries.length % 7);
    for (var j = 0; j < rest; j++) {
      entries.push({ date: new Date(year, month + 1, j + 1), label: j + 1, outside: true });
    }
    return entries;
  }

  function parseEventDateForMiniCal(val) {
    if (!val) return null;
    if (val instanceof Date) return stripTime(val);
    var s = String(val);
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return stripTime(new Date(s + 'T12:00:00'));
    var parsed = new Date(s);
    return isNaN(parsed.getTime()) ? null : stripTime(parsed);
  }

  function addEventToMiniCalDays(ev, map) {
    var start = parseEventDateForMiniCal(ev.start);
    if (!start) return;
    var end = parseEventDateForMiniCal(ev.end || ev.start) || start;
    if (ev.allDay && ev.end) {
      var endExclusive = new Date(end);
      endExclusive.setDate(endExclusive.getDate() - 1);
      if (endExclusive >= start) end = endExclusive;
    }
    var cursor = new Date(start);
    while (cursor.getTime() <= end.getTime()) {
      var key = formatDateKey(cursor);
      map.set(key, (map.get(key) || 0) + 1);
      cursor.setDate(cursor.getDate() + 1);
    }
  }

  function mergeSubscriptionEventsForMiniCal(list, rangeStartStr, rangeEndStr) {
    if (typeof subscriptionEventsCache === 'undefined' || !Array.isArray(subscriptions)) return list;
    subscriptions.forEach(function(sub) {
      if (sub.is_active != 1) return;
      (subscriptionEventsCache[sub.id] || []).forEach(function(ev) {
        if (eventInRange(ev, rangeStartStr, rangeEndStr)) list.push(ev);
      });
    });
    return list;
  }

  function refreshMiniCalEventDays(year, month, callback) {
    var token = ++miniCalEventDaysToken;
    var entries = buildMiniCalEntries(year, month);
    if (!entries.length) {
      miniCalEventDays = new Map();
      if (callback) callback();
      return;
    }
    var rangeStartStr = formatDateKey(entries[0].date);
    var rangeEndDate = new Date(entries[entries.length - 1].date);
    rangeEndDate.setDate(rangeEndDate.getDate() + 1);
    var rangeEndStr = formatDateKey(rangeEndDate);
    var params = new URLSearchParams({
      start: rangeStartStr,
      end: rangeEndStr,
      filters: JSON.stringify(getFilters())
    });
    fetch(apiBase + '/events.php?' + params.toString(), { credentials: 'same-origin', cache: 'no-store' })
      .then(function(r) { return r.ok ? r.json() : []; })
      .then(function(data) {
        if (token !== miniCalEventDaysToken) return;
        var list = Array.isArray(data) ? data : [];
        mergeSubscriptionEventsForMiniCal(list, rangeStartStr, rangeEndStr);
        var map = new Map();
        list.forEach(function(ev) { addEventToMiniCalDays(ev, map); });
        miniCalEventDays = map;
        if (callback) callback();
      })
      .catch(function() {
        if (token !== miniCalEventDaysToken) return;
        if (callback) callback();
      });
  }

  function applyMiniCalEventDots() {
    var container = document.getElementById('mini-calendar-days');
    if (!container) return;
    container.querySelectorAll('[data-date]').forEach(function(cell) {
      var key = cell.getAttribute('data-date');
      var count = miniCalEventDays.get(key) || 0;
      cell.classList.toggle('has-events', count > 0);
      cell.style.setProperty('--ec', Math.min(count, 6));
    });
  }

  function scheduleMiniCalEventDaysRefresh() {
    var focus = calendar.getDate();
    refreshMiniCalEventDays(focus.getFullYear(), focus.getMonth(), applyMiniCalEventDots);
  }

  function createMiniCalCell(cellDate, isOutsideMonth, dayLabel) {
    var cell = document.createElement('span');
    cell.className = buildMiniCalCellClasses(cellDate, isOutsideMonth);
    cell.setAttribute('data-date', formatDateKey(cellDate));
    var dateKey = formatDateKey(cellDate);
    var evCount = miniCalEventDays.get(dateKey) || 0;
    if (evCount > 0) {
      cell.classList.add('has-events');
      cell.style.setProperty('--ec', Math.min(evCount, 6));
    }
    var num = document.createElement('span');
    num.className = 'cal-mini-cal__day-num';
    num.textContent = dayLabel;
    cell.appendChild(num);
    cell.addEventListener('click', function() {
      selectedDate = cellDate;
      calendar.gotoDate(cellDate);
      renderMiniCalendar();
    });
    return cell;
  }

  function renderMiniCalendar() {
    updateMiniMonthLabel();
    var focus = calendar.getDate();
    var year = focus.getFullYear();
    var month = focus.getMonth();
    var container = document.getElementById('mini-calendar-days');
    if (!container) return;
    container.innerHTML = '';

    var entries = buildMiniCalEntries(year, month);

    for (var r = 0; r < entries.length; r += 7) {
      var rowEntries = entries.slice(r, r + 7);
      var rowDates = rowEntries.map(function(e) { return e.date; });
      var row = document.createElement('div');
      row.className = 'cal-mini-cal__row';
      if (weekRowShowsVisibleWeek(rowDates)) row.classList.add('is-week-active');
      rowEntries.forEach(function(entry) {
        row.appendChild(createMiniCalCell(entry.date, entry.outside, entry.label));
      });
      container.appendChild(row);
    }

    refreshMiniCalEventDays(year, month, applyMiniCalEventDots);
  }
  calendar.on('datesSet', function() {
    if (!selectedDate) selectedDate = new Date(calendar.view.currentStart);
    renderMiniCalendar();
    setTimeout(function() { highlightSelectedDayInCalendar(); }, 0);
  });
  renderMiniCalendar();

  window.addEventListener('companyChanged', function() {
    calendar.refetchEvents();
    renderMiniCalendar();
  });

  // Modal: Neuer Termin / Bearbeiten
  var eventModal = document.getElementById('event-modal');
  var eventDetailModal = document.getElementById('event-detail-modal');

  function getEventType() {
    var r = document.querySelector('input[name="event_type"]:checked');
    return r ? r.value : 'term';
  }

  function toggleEventTypeFields() {
    var type = getEventType();
    var termFields = document.getElementById('event-type-term-fields');
    var vacationFields = document.getElementById('event-type-vacation-fields');
    if (termFields) termFields.classList.toggle('hidden', type !== 'term');
    if (vacationFields) vacationFields.classList.toggle('hidden', type === 'term');
    var titleInput = document.getElementById('event-title');
    var startInput = document.getElementById('event-start');
    var endInput = document.getElementById('event-end');
    if (titleInput) titleInput.required = (type === 'term');
    if (startInput) startInput.required = (type === 'term');
    if (endInput) endInput.required = (type === 'term');
  }

  function openEventModal(customId, prefilledDate, prefilledAllDay) {
    document.getElementById('event-modal-title').textContent = customId ? 'Termin bearbeiten' : 'Neuer Termin';
    document.getElementById('event-id').value = customId || '';
    document.querySelectorAll('input[name="event_type"]').forEach(function(r) { r.checked = (r.value === 'term'); });
    toggleEventTypeFields();
    document.getElementById('event-title').value = '';
    document.getElementById('event-description').value = '';
    var meetingLinkEl = document.getElementById('event-meeting-link');
    if (meetingLinkEl) meetingLinkEl.value = '';
    var inviteEmailsEl = document.getElementById('event-invite-emails');
    if (inviteEmailsEl) inviteEmailsEl.value = '';
    var moreFields = document.getElementById('event-more-fields');
    if (moreFields) { moreFields.classList.add('hidden'); }
    var moreIcon = document.getElementById('event-more-icon');
    if (moreIcon) { moreIcon.classList.remove('rotate-180'); }
    var moreLabel = document.getElementById('event-more-label');
    if (moreLabel) { moreLabel.textContent = 'Mehr einstellen'; }
    var now = new Date();
    var start, end, dateStr;
    var currentView = calendar.view.type;
    
    if (prefilledDate && !customId) {
      var d = new Date(prefilledDate);
      dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
      if (currentView === 'dayGridMonth' || prefilledAllDay) {
        start = new Date(d);
        start.setHours(0, 0, 0, 0);
        end = new Date(d);
        end.setHours(23, 59, 0, 0);
      } else {
        start = new Date(d);
        start.setMinutes(0, 0, 0);
        end = new Date(start);
        end.setMinutes(start.getMinutes() + 30, 0, 0);
      }
    } else {
      start = new Date(now);
      start.setHours(now.getHours() + 1, 0, 0, 0);
      end = new Date(start);
      end.setMinutes(start.getMinutes() + 30, 0, 0);
      dateStr = now.toISOString().slice(0, 10);
    }
    
    var dateInput = document.getElementById('event-date');
    var startSlider = document.getElementById('event-start-time');
    var endSlider = document.getElementById('event-end-time');
    
    if (dateInput) dateInput.value = dateStr;

    var alldayToggle = document.getElementById('event-allday-toggle');
    if (alldayToggle) {
      alldayToggle.checked = !!prefilledAllDay;
      toggleAllDayFields();
    }
    var dateEndInput = document.getElementById('event-date-end');
    if (dateEndInput && prefilledAllDay) dateEndInput.value = dateStr;
    
    if (startSlider) {
      var startMinutes = start.getHours() * 60 + start.getMinutes();
      startSlider.value = startMinutes;
      updateTimeDisplay('event-start-time', 'event-start-time-display');
    }
    if (endSlider) {
      var endMinutes = end.getHours() * 60 + end.getMinutes();
      endSlider.value = Math.min(endMinutes, 1439);
      updateTimeDisplay('event-end-time', 'event-end-time-display');
    }
    
    syncDateTimeFields();
    var vacationFrom = document.getElementById('vacation-date-from');
    var vacationTo = document.getElementById('vacation-date-to');
    var vacationHours = document.getElementById('vacation-hours');
    if (vacationFrom) vacationFrom.value = dateStr;
    if (vacationTo) vacationTo.value = dateStr;
    if (vacationHours) vacationHours.value = '8';
    var invWrap = document.getElementById('event-invitees-wrap');
    if (invWrap) {
      var invContainer = document.getElementById('event-invitees');
      invContainer.innerHTML = '';
      (calendarColleagues || []).forEach(function(u) {
        var badge = document.createElement('label');
        badge.className = 'invitee-badge relative inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-all border-2 border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-700 dark:text-primary-210 hover:border-primary-250 dark:hover:border-primary-420';
        badge.setAttribute('data-user-id', u.id);
        
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.name = 'invitees[]';
        cb.value = u.id;
        cb.className = 'sr-only peer';
        
        var icon = document.createElement('span');
        icon.className = 'invitee-icon hidden peer-checked:inline-flex';
        icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>';
        
        var nameSpan = document.createElement('span');
        nameSpan.textContent = u.name;
        
        badge.appendChild(cb);
        badge.appendChild(icon);
        badge.appendChild(nameSpan);
        invContainer.appendChild(badge);
        
        // Toggle-Styling bei Klick
        badge.addEventListener('click', function() {
          setTimeout(function() {
            if (cb.checked) {
              badge.classList.remove('border-gray-300', 'dark:border-primary-320', 'bg-white', 'dark:bg-primary-300', 'text-gray-700', 'dark:text-primary-210');
              badge.classList.add('border-primary-250', 'dark:border-primary-420', 'bg-primary-100', 'dark:bg-primary-420/20', 'text-primary-250', 'dark:text-primary-420');
            } else {
              badge.classList.remove('border-primary-250', 'dark:border-primary-420', 'bg-primary-100', 'dark:bg-primary-420/20', 'text-primary-250', 'dark:text-primary-420');
              badge.classList.add('border-gray-300', 'dark:border-primary-320', 'bg-white', 'dark:bg-primary-300', 'text-gray-700', 'dark:text-primary-210');
            }
          }, 0);
        });
      });
    }
    var typeWrap = document.getElementById('event-type-wrap');
    if (typeWrap) typeWrap.style.display = customId ? 'none' : '';
    if (customId) {
      fetch(apiBase + '/custom-events.php?id=' + customId, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.success && data.event) {
            var e = data.event;
            document.getElementById('event-title').value = e.title || '';
            document.getElementById('event-description').value = e.description || '';
            var ml = document.getElementById('event-meeting-link');
            if (ml) ml.value = e.meeting_link || '';
            var ie = document.getElementById('event-invite-emails');
            if (ie) ie.value = e.invite_emails || '';
            var alldayToggle = document.getElementById('event-allday-toggle');
            if (alldayToggle) {
              alldayToggle.checked = !!(e.all_day);
              toggleAllDayFields();
            }
            if (e.start_at) {
              var startDt = new Date(e.start_at);
              var startDateStr = startDt.getFullYear() + '-' + String(startDt.getMonth() + 1).padStart(2, '0') + '-' + String(startDt.getDate()).padStart(2, '0');
              var dateInput = document.getElementById('event-date');
              var startSlider = document.getElementById('event-start-time');
              if (dateInput) dateInput.value = startDateStr;
              if (e.all_day && e.end_at) {
                var endDt = new Date(e.end_at);
                endDt.setDate(endDt.getDate() - 1);
                var endDateStr = endDt.getFullYear() + '-' + String(endDt.getMonth() + 1).padStart(2, '0') + '-' + String(endDt.getDate()).padStart(2, '0');
                var dateEndInput = document.getElementById('event-date-end');
                if (dateEndInput) dateEndInput.value = endDateStr;
              }
              if (startSlider && !e.all_day) {
                var startMinutes = startDt.getHours() * 60 + startDt.getMinutes();
                startSlider.value = startMinutes;
                updateTimeDisplay('event-start-time', 'event-start-time-display');
              }
            }
            if (e.end_at && !e.all_day) {
              var endDt = new Date(e.end_at);
              var endMinutes = endDt.getHours() * 60 + endDt.getMinutes();
              if (endMinutes === 0) endMinutes = 1439;
              var endSlider = document.getElementById('event-end-time');
              if (endSlider) {
                endSlider.value = Math.min(endMinutes, 1439);
                updateTimeDisplay('event-end-time', 'event-end-time-display');
              }
            }
            syncDateTimeFields();
            
            var invContainer = document.getElementById('event-invitees');
            if (invContainer) {
              var invIds = (e.invitees || []).map(function(i) { return i.user_id; });
              invContainer.querySelectorAll('input[name="invitees[]"]').forEach(function(cb) {
                var isChecked = invIds.indexOf(parseInt(cb.value, 10)) !== -1;
                cb.checked = isChecked;
                
                // Badge-Styling aktualisieren
                var badge = cb.closest('.invitee-badge');
                if (badge) {
                  if (isChecked) {
                    badge.classList.remove('border-gray-300', 'dark:border-primary-320', 'bg-white', 'dark:bg-primary-300', 'text-gray-700', 'dark:text-primary-210');
                    badge.classList.add('border-primary-250', 'dark:border-primary-420', 'bg-primary-100', 'dark:bg-primary-420/20', 'text-primary-250', 'dark:text-primary-420');
                  } else {
                    badge.classList.remove('border-primary-250', 'dark:border-primary-420', 'bg-primary-100', 'dark:bg-primary-420/20', 'text-primary-250', 'dark:text-primary-420');
                    badge.classList.add('border-gray-300', 'dark:border-primary-320', 'bg-white', 'dark:bg-primary-300', 'text-gray-700', 'dark:text-primary-210');
                  }
                }
              });
            }
          }
          doFocusEventTitle();
        });
    }
    eventModal.classList.remove('hidden');
    if (!customId) {
      doFocusEventTitle();
    }
  }

  function doFocusEventTitle() {
    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        var type = getEventType();
        var el = type === 'term' ? document.getElementById('event-title') : document.getElementById('vacation-date-from');
        if (el) {
          el.focus();
        }
      });
    });
  }

  document.querySelectorAll('input[name="event_type"]').forEach(function(r) {
    r.addEventListener('change', toggleEventTypeFields);
  });

  var eventMoreToggle = document.getElementById('event-more-toggle');
  if (eventMoreToggle) {
    eventMoreToggle.addEventListener('click', function() {
      var moreFields = document.getElementById('event-more-fields');
      var moreIcon = document.getElementById('event-more-icon');
      var moreLabel = document.getElementById('event-more-label');
      if (!moreFields) return;
      var isOpen = !moreFields.classList.contains('hidden');
      if (isOpen) {
        moreFields.classList.add('hidden');
        if (moreIcon) moreIcon.classList.remove('rotate-180');
        if (moreLabel) moreLabel.textContent = 'Mehr einstellen';
      } else {
        moreFields.classList.remove('hidden');
        if (moreIcon) moreIcon.classList.add('rotate-180');
        if (moreLabel) moreLabel.textContent = 'Weniger';
      }
    });
  }

  var calNewEventBtn = document.getElementById('cal-new-event');
  if (calNewEventBtn) calNewEventBtn.addEventListener('click', function() { openEventModal(null); });
  document.getElementById('event-modal-cancel').addEventListener('click', function() { eventModal.classList.add('hidden'); });
  document.getElementById('event-modal-backdrop').addEventListener('click', function() { eventModal.classList.add('hidden'); });
  document.getElementById('event-detail-close').addEventListener('click', function() { eventDetailModal.classList.add('hidden'); });
  document.getElementById('event-detail-backdrop').addEventListener('click', function() { eventDetailModal.classList.add('hidden'); });
  
  function syncCalSettingsDropdownFromStorage() {
    var settings = getSettings();
    var monthEl = document.getElementById('setting-month-weekends');
    var weekEl = document.getElementById('setting-week-weekends');
    if (monthEl) monthEl.checked = settings.monthWeekends;
    if (weekEl) weekEl.checked = settings.weekWeekends;
  }

  function persistCalSettingsFromDropdown() {
    var monthEl = document.getElementById('setting-month-weekends');
    var weekEl = document.getElementById('setting-week-weekends');
    if (!monthEl || !weekEl) return;
    saveSettings({
      monthWeekends: monthEl.checked,
      weekWeekends: weekEl.checked
    });
    applyWeekendSettings();
  }

  var calSettingsContainer = document.getElementById('cal-settings-dropdown-container');
  var calSettingsBtn = document.getElementById('cal-settings-btn');
  var calSettingsMenu = document.getElementById('cal-settings-dropdown-menu');
  if (calSettingsBtn && calSettingsMenu && calSettingsContainer) {
    function closeCalSettingsDropdown() {
      closeCalFilterDropdownPortal(calSettingsMenu, calSettingsContainer);
      calSettingsBtn.setAttribute('aria-expanded', 'false');
    }
    calSettingsBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      var isHidden = calSettingsMenu.classList.contains('hidden');
      if (isHidden) {
        syncCalSettingsDropdownFromStorage();
        openCalFilterDropdownPortal(calSettingsMenu, calSettingsBtn, { minWidth: 288, alignCenter: true });
        calSettingsBtn.setAttribute('aria-expanded', 'true');
      } else {
        closeCalSettingsDropdown();
      }
    });
    document.addEventListener('click', function(e) {
      if (calSettingsMenu.classList.contains('hidden')) return;
      if (calSettingsContainer.contains(e.target)) return;
      if (calSettingsMenu.contains(e.target)) return;
      closeCalSettingsDropdown();
    });
    window.addEventListener('resize', function() {
      if (!calSettingsMenu.classList.contains('hidden')) {
        positionCalFilterDropdown(calSettingsMenu, calSettingsBtn, { minWidth: 288, alignCenter: true });
      }
    });
    ['setting-month-weekends', 'setting-week-weekends'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener('change', function() {
          persistCalSettingsFromDropdown();
        });
      }
    });
  }

  // =====================
  // Kalender-Abonnements
  // =====================
  var subscriptionModal = document.getElementById('subscription-modal');
  var subscriptions = [];
  
  function loadSubscriptions() {
    if (!isAdminOrTechniker || !document.getElementById('subscription-list')) return;
    fetch(apiBase + '/subscriptions.php')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          subscriptions = data.subscriptions || [];
          renderSubscriptionList();
          // Events für aktive Subscriptions laden
          subscriptions.filter(function(s) { return s.is_active == 1; }).forEach(function(sub) {
            loadSubscriptionEvents(sub.id);
          });
        }
      })
      .catch(function(e) { console.error('Fehler beim Laden der Abonnements:', e); });
  }
  
  function renderSubscriptionList() {
    var container = document.getElementById('subscription-list');
    if (!container) return;
    
    if (subscriptions.length === 0) {
      container.innerHTML = '<p class="cal-sidebar-hint">Keine Kalender abonniert</p>';
      return;
    }
    
    container.innerHTML = subscriptions.map(function(sub) {
      return '<label class="cal-filter-item">' +
        '<span class="cal-filter-swatch cal-filter-swatch--static" style="background-color:' + escapeHtml(sub.color) + '"></span>' +
        '<span class="cal-filter-label truncate cursor-pointer" data-edit-sub="' + sub.id + '" title="' + escapeHtml(sub.name) + '">' + escapeHtml(sub.name) + '</span>' +
        '<input type="checkbox" class="sr-only peer sub-toggle" data-sub-id="' + sub.id + '" ' + (sub.is_active == 1 ? 'checked' : '') + '>' +
        '<span class="cal-toggle-track cal-toggle-track--sm"></span>' +
      '</label>';
    }).join('');

    calInitAllToggleTrackDrags(container);
    
    // Event-Listener für Toggle
    container.querySelectorAll('.sub-toggle').forEach(function(toggle) {
      toggle.addEventListener('change', function() {
        var subId = this.getAttribute('data-sub-id');
        var isActive = this.checked;
        toggleSubscription(subId, isActive);
      });
    });
    
    // Event-Listener für Edit
    container.querySelectorAll('[data-edit-sub]').forEach(function(el) {
      el.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var subId = this.getAttribute('data-edit-sub');
        openSubscriptionModal(subId);
      });
    });
  }
  
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  function toggleSubscription(subId, isActive) {
    fetch(apiBase + '/subscriptions.php', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: subId, is_active: isActive })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        if (isActive) {
          loadSubscriptionEvents(subId);
        } else {
          delete subscriptionEventsCache[subId];
          calendar.refetchEvents();
          scheduleMiniCalEventDaysRefresh();
        }
      }
    });
  }
  
  function loadSubscriptionEvents(subId) {
    var view = calendar.view;
    var start = view.activeStart.toISOString();
    var end = view.activeEnd.toISOString();
    
    fetch(apiBase + '/fetch-ics.php?id=' + subId + '&start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.events) {
          subscriptionEventsCache[subId] = data.events;
          calendar.refetchEvents();
          scheduleMiniCalEventDaysRefresh();
        }
      })
      .catch(function(e) { console.error('Fehler beim Laden der Events für Abo ' + subId + ':', e); });
  }
  
  function openSubscriptionModal(subId) {
    var isEdit = !!subId;
    document.getElementById('subscription-modal-title').textContent = isEdit ? 'Kalender bearbeiten' : 'Kalender hinzufügen';
    document.getElementById('subscription-id').value = subId || '';
    document.getElementById('subscription-name').value = '';
    document.getElementById('subscription-url').value = '';
    document.querySelector('input[name="subscription_color"][value="#6366f1"]').checked = true;
    document.getElementById('subscription-delete-btn').classList.toggle('hidden', !isEdit);
    
    if (isEdit) {
      var sub = subscriptions.find(function(s) { return s.id == subId; });
      if (sub) {
        document.getElementById('subscription-name').value = sub.name || '';
        document.getElementById('subscription-url').value = sub.url || '';
        var colorRadio = document.querySelector('input[name="subscription_color"][value="' + sub.color + '"]');
        if (colorRadio) colorRadio.checked = true;
      }
    }
    
    subscriptionModal.classList.remove('hidden');
  }
  
  var addSubBtn = document.getElementById('add-subscription-btn');
  if (addSubBtn) addSubBtn.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    openSubscriptionModal(null);
  });

  document.getElementById('subscription-modal-close').addEventListener('click', function() { subscriptionModal.classList.add('hidden'); });
  document.getElementById('subscription-modal-backdrop').addEventListener('click', function() { subscriptionModal.classList.add('hidden'); });
  document.getElementById('subscription-modal-cancel').addEventListener('click', function() { subscriptionModal.classList.add('hidden'); });
  
  document.getElementById('subscription-modal-save').addEventListener('click', function() {
    var id = document.getElementById('subscription-id').value;
    var name = document.getElementById('subscription-name').value.trim();
    var url = document.getElementById('subscription-url').value.trim();
    var color = document.querySelector('input[name="subscription_color"]:checked').value;
    
    if (!name || !url) {
      alert('Bitte Name und URL angeben.');
      return;
    }
    
    var method = id ? 'PATCH' : 'POST';
    var body = { name: name, url: url, color: color };
    if (id) body.id = id;
    
    fetch(apiBase + '/subscriptions.php', {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        subscriptionModal.classList.add('hidden');
        loadSubscriptions();
      } else {
        alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
      }
    })
    .catch(function(e) { alert('Netzwerkfehler'); });
  });
  
  document.getElementById('subscription-delete-btn').addEventListener('click', function() {
    var id = document.getElementById('subscription-id').value;
    if (!id) return;
    
    if (!confirm('Kalender-Abonnement wirklich löschen?')) return;
    
    fetch(apiBase + '/subscriptions.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        subscriptionModal.classList.add('hidden');
        delete subscriptionEventsCache[id];
        loadSubscriptions();
        calendar.refetchEvents();
      }
    });
  });
  
  // Abonnements beim Start laden
  loadSubscriptions();

  var vacationApiBase = (baseUrl && baseUrl !== '/' ? baseUrl.replace(/\/$/, '') : '') + '/time-tracking/api';

  document.getElementById('event-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var id = document.getElementById('event-id').value;
    var type = getEventType();

    if (!id && (type === 'vacation' || type === 'holiday')) {
      var fromStr = document.getElementById('vacation-date-from') && document.getElementById('vacation-date-from').value;
      var toStr = document.getElementById('vacation-date-to') && document.getElementById('vacation-date-to').value;
      var hours = parseFloat((document.getElementById('vacation-hours') && document.getElementById('vacation-hours').value) || 8, 10) || 8;
      if (!fromStr || !toStr) {
        alert('Bitte Von- und Bis-Datum angeben.');
        return;
      }
      var from = new Date(fromStr);
      var to = new Date(toStr);
      if (from > to) {
        alert('Von-Datum darf nicht nach Bis-Datum liegen.');
        return;
      }
      var vacationType = type === 'holiday' ? 'holiday' : 'vacation';
      var dates = [];
      var d = new Date(from);
      while (d <= to) {
        dates.push(d.toISOString().slice(0, 10));
        d.setDate(d.getDate() + 1);
      }
      var saveBtn = document.getElementById('event-modal-save');
      if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Speichere…'; }
      var done = 0;
      var failed = false;
      function checkDone() {
        done++;
        if (done === dates.length) {
          if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Speichern'; }
          if (!failed) {
            eventModal.classList.add('hidden');
            calendar.refetchEvents();
          } else {
            alert('Einige Tage konnten nicht gespeichert werden. Bitte prüfen Sie die Daten.');
          }
        }
      }
      if (dates.length === 0) {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Speichern'; }
        return;
      }
      dates.forEach(function(dateStr) {
        fetch(vacationApiBase + '/vacation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ date: dateStr, hours: hours, type: vacationType })
        })
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (!data.success) failed = true;
            checkDone();
          })
          .catch(function() { failed = true; checkDone(); });
      });
      return;
    }

    var alldayToggle = document.getElementById('event-allday-toggle');
    var isAllDay = alldayToggle && alldayToggle.checked;
    var dateEndInput = document.getElementById('event-date-end');
    if (isAllDay && dateEndInput && !dateEndInput.value) {
      var dateInput = document.getElementById('event-date');
      if (dateInput && dateInput.value) dateEndInput.value = dateInput.value;
    }
    if (isAllDay && dateEndInput && dateEndInput.value) {
      var dateInput = document.getElementById('event-date');
      if (dateInput && dateInput.value && dateEndInput.value < dateInput.value) {
        alert('Enddatum darf nicht vor Startdatum liegen.');
        return;
      }
    }
    syncDateTimeFields();
    var payload = {
      title: document.getElementById('event-title').value.trim(),
      description: document.getElementById('event-description').value.trim(),
      meeting_link: (document.getElementById('event-meeting-link') || {}).value || '',
      invite_emails: (document.getElementById('event-invite-emails') || {}).value || '',
      start_at: document.getElementById('event-start').value,
      end_at: document.getElementById('event-end').value,
      all_day: isAllDay,
      invitees: []
    };
    if (!payload.title && !id) {
      alert('Bitte einen Titel angeben.');
      return;
    }
    if (!payload.start_at && !id) {
      alert('Bitte Start angeben.');
      return;
    }
    if (!payload.end_at && !id) {
      alert('Bitte Ende angeben.');
      return;
    }
    var invChecks = document.querySelectorAll('#event-invitees input[name="invitees[]"]:checked');
    invChecks.forEach(function(c) { payload.invitees.push(parseInt(c.value, 10)); });
    var url = apiBase + '/custom-events.php';
    var method = id ? 'PUT' : 'POST';
    if (id) payload.id = parseInt(id, 10);
    fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          eventModal.classList.add('hidden');
          calendar.refetchEvents();
        } else {
          alert(data.error || 'Fehler beim Speichern');
        }
      })
      .catch(function() { alert('Fehler beim Speichern'); });
  });
})();
</script>


<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
