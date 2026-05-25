<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Prüfen ob Benutzer die richtige Rolle hat (nur Techniker und Admins)
try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        showPermissionDeniedPage();
    }
    $isTimeTrackingAdmin = ($user['rolle'] === 'Admin');
    $currentUserId = (int) $_SESSION['user_id'];
} catch (PDOException $e) {
    http_response_code(500);
    die('Datenbankfehler beim Prüfen der Berechtigung.');
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

// BASE_URL für JavaScript
$baseUrl = BASE_URL;
?>

<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
  <main>
    <div class="px-4">
      <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
        <div class="col-span-full mx-4 mt-4 items-center justify-between sm:flex">
          <div class="mb-4 sm:mb-0">
            <nav class="mb-4 flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Zeiterfassung</span>
                  </div>
                </li>
              </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Zeiterfassung</h1>
          </div>
          
          <!-- Filterzeile: Mitarbeiter (nur Admin) + Datumsbereich -->
          <div class="mb-4 sm:mb-0 flex flex-wrap items-center gap-3">
            <?php if (!empty($isTimeTrackingAdmin)): ?>
            <div class="relative min-w-[200px] max-w-[260px]">
              <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none z-10">
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 0 1-8 0 4 4 0 0 1 8 0ZM6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>
                </svg>
              </div>
              <select id="view-user-select" class="block w-full ps-9 pe-8 py-2.5 bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 appearance-none cursor-pointer" title="Mitarbeiter auswählen">
                <option value="">Meine Zeiten</option>
              </select>
              <div class="absolute inset-y-0 end-0 flex items-center pe-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8 10 4 4 4-4"/>
                </svg>
              </div>
            </div>
            <?php endif; ?>
            <div class="relative flex-1 min-w-0 flex items-center gap-3 flex-wrap">
              <div class="relative flex-1 max-w-xs min-w-[140px]">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                  <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z"/>
                  </svg>
                </div>
                <input id="datepicker-range-start" name="start" type="date" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Von Datum">
              </div>
              <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0">bis</span>
              <div class="relative flex-1 max-w-xs min-w-[140px]">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                  <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z"/>
                  </svg>
                </div>
                <input id="datepicker-range-end" name="end" type="date" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Bis Datum">
              </div>
              <button id="export-pdf-button" type="button" class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800 shrink-0">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M5 20h14a2 2 0 002-2v-6a2 2 0 00-2-2h-3M5 20a2 2 0 01-2-2v-6a2 2 0 012-2h3m4-4V4m0 0H8m4 0h4"></path>
                </svg>
                PDF-Export
              </button>
            </div>
          </div>
        </div>

        <!-- Statistiken -->
        <div class="col-span-full px-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gesamtzeit</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white" id="total-time">0h 0m</p>
              </div>
              <div class="rounded-full bg-primary-100 p-3 dark:bg-primary-900">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sollzeit</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white" id="soll-time">0h 0m</p>
              </div>
              <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Saldo</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white" id="overtime-time">0h 0m</p>
              </div>
              <div id="overtime-icon" class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Urlaubstage</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white" id="vacation-days">0</p>
              </div>
              <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Zeiterfassungs-Info und Aktionen -->
        <div class="col-span-full px-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800" id="time-tracking-status-card">
            <div id="time-tracking-status-content">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Zeiterfassung</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400" id="status-text">Zeiterfassung nicht aktiv</p>
            <p class="text-sm font-medium text-gray-900 dark:text-white mt-2" id="running-time" style="display: none;">
              Laufende Zeit: <span id="running-time-display" class="font-mono">00:00:00</span>
            </p>
            <div class="mt-3 space-y-1 text-sm text-gray-500 dark:text-gray-400">
              <p id="daily-hours-info">
                Sollzeit pro Tag: <span id="daily-hours" class="font-medium text-gray-900 dark:text-white">0h 0m</span>
              </p>
              <p id="weekly-hours-info">
                Wochenstunden: <span id="weekly-hours-display" class="font-medium text-gray-900 dark:text-white">0h</span>
              </p>
              <p id="vacation-days-info">
                Urlaubstage: <span id="vacation-days-display" class="font-medium text-gray-900 dark:text-white">0</span>
              </p>
            </div>
            </div>
            <div id="time-tracking-view-colleague-notice" class="hidden">
              <p class="text-sm text-gray-600 dark:text-gray-300" id="view-colleague-notice-text">Sie sehen die Zeiten eines Kollegen. Wählen Sie „Meine Zeiten“, um eigene Zeiten zu erfassen.</p>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800" id="time-tracking-actions-card">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Aktionen</h3>
            <div class="grid grid-cols-2 gap-2">
              <button id="time-track-button" type="button" class="flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800" onclick="event.preventDefault(); event.stopPropagation(); if(typeof toggleTimeTracking === 'function') { toggleTimeTracking(); } else { console.error('toggleTimeTracking Funktion nicht gefunden'); }">
                <svg id="button-icon" class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span id="button-text">Zeit starten</span>
              </button>
              <button id="add-time-button" type="button" class="flex items-center justify-center rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-800">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Zeit nachtragen
              </button>
              <button id="add-vacation-button" type="button" class="flex items-center justify-center rounded-lg bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-4 focus:ring-yellow-300 dark:bg-yellow-600 dark:hover:bg-yellow-700 dark:focus:ring-yellow-800">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Abwesenheit eintragen
              </button>
              <button id="settings-button" type="button" class="flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Einstellungen
              </button>
            </div>
          </div>
        </div>

        <!-- Tab Navigation -->
        <div class="col-span-full px-4 pb-4">
          <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
              <button id="tab-time-entries" class="tab-button border-b-2 border-primary-500 py-4 px-1 text-sm font-medium text-primary-600 dark:text-primary-400" data-tab="time-entries">
                Zeiteinträge
              </button>
              <button id="tab-work-times" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" data-tab="work-times">
                Arbeitszeiten
              </button>
              <button id="tab-stats-year" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" data-tab="stats-year">
                Statistiken Jahr
              </button>
              <button id="tab-stats-total" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" data-tab="stats-total">
                Statistiken Gesamt
              </button>
            </nav>
          </div>
        </div>

        <!-- Tab Content: Zeiteinträge -->
        <div id="tab-content-time-entries" class="tab-content col-span-full px-4 pb-4">
          <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 p-4 dark:border-gray-700 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Zeiteinträge</h3>
              <div class="flex items-center gap-3">
                <label for="date-search" class="text-sm font-medium text-gray-700 dark:text-gray-300">Nach Datum suchen:</label>
                <input type="date" id="date-search" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
                <button type="button" id="date-search-clear" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
                  Zurücksetzen
                </button>
              </div>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                  <tr>
                    <th scope="col" class="px-6 py-3">Datum</th>
                    <th scope="col" class="px-6 py-3">Startzeit</th>
                    <th scope="col" class="px-6 py-3">Endzeit</th>
                    <th scope="col" class="px-6 py-3">Dauer</th>
                    <th scope="col" class="px-6 py-3">Beschreibung</th>
                    <th scope="col" class="px-6 py-3">Aktionen</th>
                  </tr>
                </thead>
                <tbody id="time-entries-body">
                  <tr>
                    <td colspan="6" class="px-6 py-4 text-center">
                      <div role="status" class="flex justify-center">
                        <svg aria-hidden="true" class="w-8 h-8 text-gray-400 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                          <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                        </svg>
                        <span class="sr-only">Loading...</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Tab Content: Arbeitszeiten -->
        <div id="tab-content-work-times" class="tab-content col-span-full px-4 pb-4 hidden">
          <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 p-4 dark:border-gray-700 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Arbeitszeiten</h3>
              <div class="inline-flex rounded-base shadow-xs -space-x-px" role="group">
                <button type="button" id="year-prev" class="text-body bg-neutral-primary-soft border border-default hover:bg-neutral-secondary-medium hover:text-heading focus:ring-3 focus:ring-neutral-tertiary-soft font-medium leading-5 rounded-s-base text-sm px-3 py-2 focus:outline-none">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                  </svg>
                </button>
                <button type="button" id="year-display" class="text-body bg-neutral-primary-soft border border-default hover:bg-neutral-secondary-medium hover:text-heading focus:ring-3 focus:ring-neutral-tertiary-soft font-medium leading-5 text-sm px-4 py-2 focus:outline-none min-w-[80px]">
                  <span id="current-year">2026</span>
                </button>
                <button type="button" id="year-next" class="text-body bg-neutral-primary-soft border border-default hover:bg-neutral-secondary-medium hover:text-heading focus:ring-3 focus:ring-neutral-tertiary-soft font-medium leading-5 rounded-e-base text-sm px-3 py-2 focus:outline-none">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                  </svg>
                </button>
              </div>
            </div>
            <div class="p-4">
              <div id="work-times-loading" class="flex justify-center py-8">
                <svg aria-hidden="true" class="w-8 h-8 text-gray-400 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                  <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                </svg>
              </div>
              <div id="work-times-table-container" class="hidden">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                  <div class="overflow-x-auto" style="max-width: 100%;">
                    <table id="work-times-table" class="text-sm text-gray-900 dark:text-gray-100 border-collapse" style="min-width: max-content;">
                      <thead id="work-times-thead" class="bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-800 dark:to-gray-700 text-xs font-bold uppercase text-gray-700 dark:text-gray-300 sticky top-0 z-20 shadow-sm">
                        <!-- Wird dynamisch gefüllt -->
                      </thead>
                      <tbody id="work-times-tbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <!-- Wird dynamisch gefüllt -->
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab Content: Statistiken Jahr -->
        <div id="tab-content-stats-year" class="tab-content col-span-full px-4 pb-4 hidden">
          <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 p-4 dark:border-gray-700 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Statistiken nach Jahr</h3>
              <div class="inline-flex rounded-base shadow-xs -space-x-px" role="group">
                <button type="button" id="stats-year-prev" class="text-body bg-neutral-primary-soft border border-default hover:bg-neutral-secondary-medium hover:text-heading focus:ring-3 focus:ring-neutral-tertiary-soft font-medium leading-5 rounded-s-base text-sm px-3 py-2 focus:outline-none">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                  </svg>
                </button>
                <button type="button" id="stats-year-display" class="text-body bg-neutral-primary-soft border border-default hover:bg-neutral-secondary-medium hover:text-heading focus:ring-3 focus:ring-neutral-tertiary-soft font-medium leading-5 text-sm px-4 py-2 focus:outline-none min-w-[80px]">
                  <span id="stats-current-year">2026</span>
                </button>
                <button type="button" id="stats-year-next" class="text-body bg-neutral-primary-soft border border-default hover:bg-neutral-secondary-medium hover:text-heading focus:ring-3 focus:ring-neutral-tertiary-soft font-medium leading-5 rounded-e-base text-sm px-3 py-2 focus:outline-none">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                  </svg>
                </button>
              </div>
            </div>
            <div id="stats-year-loading" class="flex justify-center py-8">
              <svg aria-hidden="true" class="w-8 h-8 text-gray-400 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
              </svg>
            </div>
            <div id="stats-year-content" class="p-6 hidden">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gesamtzeit</p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stats-year-total-time">0h 0m</p>
                    </div>
                    <div class="rounded-full bg-primary-100 p-3 dark:bg-primary-900">
                      <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                    </div>
                  </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sollzeit</p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stats-year-soll-time">0h 0m</p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                      <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                      </svg>
                    </div>
                  </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Saldo</p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stats-year-overtime-time">0h 0m</p>
                    </div>
                    <div id="stats-year-overtime-icon" class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                      <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                      </svg>
                    </div>
                  </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Urlaubstage</p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stats-year-vacation-days">0</p>
                    </div>
                    <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                      <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Visuelle Monatsstatistik -->
              <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 mt-6">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Monatliche Arbeitszeit</h4>
                <div id="monthly-chart-loading" class="flex justify-center py-8">
                  <svg aria-hidden="true" class="w-6 h-6 text-gray-400 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                    <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                  </svg>
                </div>
                <div id="monthly-chart-container" class="hidden">
                  <div id="monthly-chart" class="space-y-3">
                    <!-- Wird dynamisch gefüllt -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab Content: Statistiken Gesamt -->
        <div id="tab-content-stats-total" class="tab-content col-span-full px-4 pb-4 hidden">
          <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 p-4 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Statistiken insgesamt</h3>
            </div>
            <div id="stats-total-loading" class="flex justify-center py-8">
              <svg aria-hidden="true" class="w-8 h-8 text-gray-400 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
              </svg>
            </div>
            <div id="stats-total-content" class="p-6 hidden">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gesamtzeit</p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stats-total-total-time">0h 0m</p>
                    </div>
                    <div class="rounded-full bg-primary-100 p-3 dark:bg-primary-900">
                      <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                    </div>
                  </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sollzeit</p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stats-total-soll-time">0h 0m</p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                      <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                      </svg>
                    </div>
                  </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Saldo</p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stats-total-overtime-time">0h 0m</p>
                    </div>
                    <div id="stats-total-overtime-icon" class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                      <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                      </svg>
                    </div>
                  </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Urlaubstage</p>
                      <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stats-total-vacation-days">0</p>
                    </div>
                    <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                      <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal für Einstellungen -->
<div id="settings-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="settings-modal-title" role="dialog" aria-modal="true">
  <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="settings-modal-overlay"></div>
    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
      <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="settings-modal-title">Einstellungen</h3>
          <button type="button" id="settings-modal-close" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="space-y-4">
          <div>
            <label for="weekly-hours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Wöchentliche Arbeitsstunden</label>
            <input type="number" id="weekly-hours" step="0.5" min="0" max="80" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="vacation-days-setting" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Urlaubstage pro Jahr</label>
            <input type="number" id="vacation-days-setting" step="1" min="0" max="365" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="employment-start-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anstellungsdatum</label>
            <input type="date" id="employment-start-date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ab diesem Datum werden die Gesamtstatistiken berechnet</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="work-start-time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Arbeitsbeginn</label>
              <input type="time" id="work-start-time" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
            </div>
            <div>
              <label for="work-end-time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Arbeitsende</label>
              <input type="time" id="work-end-time" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
            </div>
          </div>
          <div class="rounded-lg bg-gray-50 dark:bg-gray-700 p-3">
            <p class="text-xs text-gray-600 dark:text-gray-300 mb-1">Berechnete Stunden pro Tag:</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-white" id="calculated-hours-per-day">-</p>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" id="settings-cancel" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
            Abbrechen
          </button>
          <button type="button" id="settings-save" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
            Speichern
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal für Zeit bearbeiten -->
<div id="edit-time-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="edit-time-modal-title" role="dialog" aria-modal="true">
  <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="edit-time-modal-overlay"></div>
    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
      <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="edit-time-modal-title">Zeiteintrag bearbeiten</h3>
          <button type="button" id="edit-time-modal-close" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="space-y-4">
          <input type="hidden" id="edit-time-id" value="">
          <div>
            <label for="edit-time-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Datum</label>
            <input type="date" id="edit-time-date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="edit-time-start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Startzeit</label>
            <input type="time" id="edit-time-start" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="edit-time-end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Endzeit</label>
            <input type="time" id="edit-time-end" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="edit-time-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Beschreibung (optional)</label>
            <textarea id="edit-time-description" rows="3" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500"></textarea>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" id="edit-time-cancel" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
            Abbrechen
          </button>
          <button type="button" id="edit-time-save" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
            Speichern
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal für Zeit nachtragen -->
<div id="add-time-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="add-time-modal-title" role="dialog" aria-modal="true">
  <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="add-time-modal-overlay"></div>
    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
      <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="add-time-modal-title">Zeit nachtragen</h3>
          <button type="button" id="add-time-modal-close" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="space-y-4">
          <div>
            <label for="add-time-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Datum</label>
            <input type="date" id="add-time-date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="add-time-start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Startzeit</label>
            <input type="time" id="add-time-start" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="add-time-end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Endzeit</label>
            <input type="time" id="add-time-end" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="add-time-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Beschreibung (optional)</label>
            <textarea id="add-time-description" rows="3" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500"></textarea>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" id="add-time-cancel" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
            Abbrechen
          </button>
          <button type="button" id="add-time-save" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
            Speichern
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal für Urlaub eintragen -->
<div id="add-vacation-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="add-vacation-modal-title" role="dialog" aria-modal="true">
  <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="add-vacation-modal-overlay"></div>
    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
      <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="add-vacation-modal-title">Abwesenheit eintragen</h3>
          <button type="button" id="add-vacation-modal-close" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="space-y-4">
          <div>
            <label for="add-vacation-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Datum</label>
            <input type="date" id="add-vacation-date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="add-vacation-hours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stunden</label>
            <input type="number" id="add-vacation-hours" step="0.5" min="0" max="24" value="8" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
          </div>
          <div>
            <label for="add-vacation-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Art</label>
            <select id="add-vacation-type" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
              <option value="vacation">Urlaub</option>
              <option value="sick">Krank</option>
              <option value="holiday">Feiertag</option>
              <option value="school">Berufsschule</option>
              <option value="other">Sonstiges</option>
            </select>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" id="add-vacation-cancel" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
            Abbrechen
          </button>
          <button type="button" id="add-vacation-save" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
            Speichern
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// baseUrl wird bereits in nav.php definiert
const timeTrackingApiUrl = baseUrl + 'time-tracking/api/time.php';
const isTimeTrackingAdmin = <?php echo $isTimeTrackingAdmin ? 'true' : 'false'; ?>;
const currentUserId = <?php echo (int)$currentUserId; ?>;
let viewUserId = ''; // leer = eigene Zeiten; gesetzt = Kollege (nur für Admins)
let colleagues = []; // { id, vorname, nachname }
let allTimeEntries = [];
let filteredTimeEntries = [];
let runningTimer = null;
let activeEntry = null;

// Query-String für API: view_user_id nur wenn Admin und Kollege ausgewählt
function getViewUserParam() {
    if (!isTimeTrackingAdmin || !viewUserId) return '';
    return '&view_user_id=' + encodeURIComponent(viewUserId);
}

document.addEventListener('DOMContentLoaded', function() {
    
    // Standard-Datum auf aktuellen Monat setzen
    function getCurrentMonthDates() {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth(); // 0-based (0 = Januar)
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0); // Tag 0 = letzter Tag des vorherigen Monats
        return { firstDay, lastDay, year, month };
    }
    
    // Formatiere Date für HTML5 date input (YYYY-MM-DD)
    function formatDateForDateInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    const { firstDay, year, month } = getCurrentMonthDates();
    const today = new Date();
    
    const startInput = document.getElementById('datepicker-range-start');
    const endInput = document.getElementById('datepicker-range-end');
    
    // Setze Standard-Werte: Von = 1. des Monats, Bis = heute
    const firstDayFormatted = formatDateForDateInput(firstDay);
    const todayFormatted = formatDateForDateInput(today);
    
    
    if (startInput) {
        if (!startInput.value) {
            startInput.value = firstDayFormatted;
        }
    }
    if (endInput) {
        if (!endInput.value) {
            endInput.value = todayFormatted;
        }
    }
    
    // Tab-Navigation
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Alle Tabs ausblenden
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Alle Tab-Buttons zurücksetzen
            tabButtons.forEach(btn => {
                btn.classList.remove('border-primary-500', 'text-primary-600', 'dark:text-primary-400');
                btn.classList.add('border-transparent', 'text-gray-500', 'hover:border-gray-300', 'hover:text-gray-700', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            });
            
            // Aktiven Tab anzeigen
            const targetContent = document.getElementById('tab-content-' + targetTab);
            if (targetContent) {
                targetContent.classList.remove('hidden');
            }
            
            // Aktiven Button markieren
            this.classList.remove('border-transparent', 'text-gray-500', 'hover:border-gray-300', 'hover:text-gray-700', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            this.classList.add('border-primary-500', 'text-primary-600', 'dark:text-primary-400');
            
            // Wenn Tab geöffnet wird, Daten laden
            if (targetTab === 'work-times') {
                loadWorkTimesYear();
            } else if (targetTab === 'stats-year') {
                loadStatsYear();
            } else if (targetTab === 'stats-total') {
                loadStatsTotal();
            }
        });
    });
    
    // Jahr-Navigation für Statistiken Jahr (Button-Gruppe)
    const statsYearPrev = document.getElementById('stats-year-prev');
    const statsYearNext = document.getElementById('stats-year-next');
    const statsYearDisplay = document.getElementById('stats-year-display');
    const statsCurrentYearSpan = document.getElementById('stats-current-year');
    
    // Aktuelles Jahr setzen
    let statsCurrentYear = new Date().getFullYear();
    const statsStartYear = 2025; // Von 2025
    const statsEndYear = 2036; // Bis 2036
    
    // Sicherstellen, dass statsCurrentYear im gültigen Bereich ist
    if (statsCurrentYear < statsStartYear) statsCurrentYear = statsStartYear;
    if (statsCurrentYear > statsEndYear) statsCurrentYear = statsEndYear;
    
    function updateStatsYearDisplay() {
        if (statsCurrentYearSpan) {
            statsCurrentYearSpan.textContent = statsCurrentYear;
        }
        // Buttons aktivieren/deaktivieren basierend auf Jahr
        if (statsYearPrev) {
            statsYearPrev.disabled = statsCurrentYear <= statsStartYear;
            if (statsCurrentYear <= statsStartYear) {
                statsYearPrev.classList.add('opacity-50', 'cursor-not-allowed');
                statsYearPrev.classList.remove('hover:bg-neutral-secondary-medium');
            } else {
                statsYearPrev.classList.remove('opacity-50', 'cursor-not-allowed');
                statsYearPrev.classList.add('hover:bg-neutral-secondary-medium');
            }
        }
        if (statsYearNext) {
            statsYearNext.disabled = statsCurrentYear >= statsEndYear;
            if (statsCurrentYear >= statsEndYear) {
                statsYearNext.classList.add('opacity-50', 'cursor-not-allowed');
                statsYearNext.classList.remove('hover:bg-neutral-secondary-medium');
            } else {
                statsYearNext.classList.remove('opacity-50', 'cursor-not-allowed');
                statsYearNext.classList.add('hover:bg-neutral-secondary-medium');
            }
        }
    }
    
    // Initiale Anzeige
    if (statsCurrentYearSpan) {
        updateStatsYearDisplay();
    }
    
    // Vorheriges Jahr
    if (statsYearPrev) {
        statsYearPrev.addEventListener('click', function() {
            if (statsCurrentYear > statsStartYear) {
                statsCurrentYear--;
                updateStatsYearDisplay();
                loadStatsYear();
            }
        });
    }
    
    // Nächstes Jahr
    if (statsYearNext) {
        statsYearNext.addEventListener('click', function() {
            if (statsCurrentYear < statsEndYear) {
                statsCurrentYear++;
                updateStatsYearDisplay();
                loadStatsYear();
            }
        });
    }
    
    // Jahr-Navigation für Arbeitszeiten (Button-Gruppe)
    const yearPrev = document.getElementById('year-prev');
    const yearNext = document.getElementById('year-next');
    const yearDisplay = document.getElementById('year-display');
    const currentYearSpan = document.getElementById('current-year');
    
    // Aktuelles Jahr setzen
    let currentYear = new Date().getFullYear();
    const startYear = 2025; // Von 2025
    const endYear = 2036; // Bis 2036
    
    // Sicherstellen, dass currentYear im gültigen Bereich ist
    if (currentYear < startYear) currentYear = startYear;
    if (currentYear > endYear) currentYear = endYear;
    
    function updateYearDisplay() {
        if (currentYearSpan) {
            currentYearSpan.textContent = currentYear;
        }
        // Buttons aktivieren/deaktivieren basierend auf Jahr
        if (yearPrev) {
            yearPrev.disabled = currentYear <= startYear;
            if (currentYear <= startYear) {
                yearPrev.classList.add('opacity-50', 'cursor-not-allowed');
                yearPrev.classList.remove('hover:bg-neutral-secondary-medium');
            } else {
                yearPrev.classList.remove('opacity-50', 'cursor-not-allowed');
                yearPrev.classList.add('hover:bg-neutral-secondary-medium');
            }
        }
        if (yearNext) {
            yearNext.disabled = currentYear >= endYear;
            if (currentYear >= endYear) {
                yearNext.classList.add('opacity-50', 'cursor-not-allowed');
                yearNext.classList.remove('hover:bg-neutral-secondary-medium');
            } else {
                yearNext.classList.remove('opacity-50', 'cursor-not-allowed');
                yearNext.classList.add('hover:bg-neutral-secondary-medium');
            }
        }
    }
    
    // Initiale Anzeige
    updateYearDisplay();
    
    // Vorheriges Jahr
    if (yearPrev) {
        yearPrev.addEventListener('click', function() {
            if (currentYear > startYear) {
                currentYear--;
                updateYearDisplay();
                loadWorkTimesYear();
            }
        });
    }
    
    // Nächstes Jahr
    if (yearNext) {
        yearNext.addEventListener('click', function() {
            if (currentYear < endYear) {
                currentYear++;
                updateYearDisplay();
                loadWorkTimesYear();
            }
        });
    }
    
    // Event Listener für Date Inputs
    if (startInput && endInput) {
        const handleDateChange = function() {
            
            // Stelle sicher, dass Werte gesetzt sind
            if (!startInput.value) {
                startInput.value = firstDayFormatted;
            }
            if (!endInput.value) {
                endInput.value = lastDayFormatted;
            }
            
            // Validierung: End-Datum muss >= Start-Datum sein
            if (startInput.value && endInput.value) {
                if (new Date(endInput.value) < new Date(startInput.value)) {
                    endInput.value = startInput.value;
                }
            }
            
            filterTimeEntries();
        };
        
        startInput.addEventListener('change', handleDateChange);
        endInput.addEventListener('change', handleDateChange);
        
        // Blur-Events als Fallback
        startInput.addEventListener('blur', handleDateChange);
        endInput.addEventListener('blur', handleDateChange);
    }
    
    // Zeiterfassungs-Button
    const timeTrackButton = document.getElementById('time-track-button');
    if (timeTrackButton) {
        timeTrackButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleTimeTracking();
        });
    } else {
        console.error('Zeiterfassungs-Button nicht gefunden!');
    }

    const exportPdfButton = document.getElementById('export-pdf-button');
    if (exportPdfButton) {
        exportPdfButton.addEventListener('click', function() {
            const startValue = startInput && startInput.value ? startInput.value : '';
            const endValue = endInput && endInput.value ? endInput.value : '';

            let exportUrl = baseUrl + 'time-tracking/export-pdf.php';
            const params = new URLSearchParams();
            if (startValue) {
                params.set('date_from', startValue);
            }
            if (endValue) {
                params.set('date_to', endValue);
            }
            if (viewUserId) {
                params.set('view_user_id', viewUserId);
            }
            params.set('print', '1');

            const queryString = params.toString();
            if (queryString) {
                exportUrl += '?' + queryString;
            }

            window.open(exportUrl, '_blank', 'noopener');
        });
    }
    
    // Modals einrichten
    setupModals();
    
    // Initiale Daten laden - Status zuerst, dann Einträge
    loadTimeStatus();
    
    // Datumssuche Event Listener
    const dateSearchInput = document.getElementById('date-search');
    const dateSearchClear = document.getElementById('date-search-clear');
    
    if (dateSearchInput) {
        dateSearchInput.addEventListener('change', function() {
            filterTimeEntries();
        });
    }
    
    if (dateSearchClear) {
        dateSearchClear.addEventListener('click', function() {
            if (dateSearchInput) {
                dateSearchInput.value = '';
                filterTimeEntries();
            }
        });
    }
    
    setTimeout(function() {
        // Stelle sicher, dass Werte immer korrekt sind (aktueller Monat)
        if (startInput && endInput) {
            const today = new Date();
            const year = today.getFullYear();
            const month = today.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            
            function formatDateForDateInputFinal(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return year + '-' + month + '-' + day;
            }
            
            const firstDayFormatted = formatDateForDateInputFinal(firstDay);
            const lastDayFormatted = formatDateForDateInputFinal(lastDay);
            
            
            // Prüfe ob die Werte korrekt sind, sonst setze sie
            const currentStart = startInput.value || '';
            const currentEnd = endInput.value || '';
            
            // Prüfe ob das Jahr falsch ist (z.B. 2028 statt 2026)
            if (currentEnd.includes('2028') || currentEnd.includes('2027') || !currentEnd.startsWith(String(year))) {
                endInput.value = lastDayFormatted;
            }
            
            if (!startInput.value || startInput.value.trim() === '' || !currentStart.startsWith(String(year))) {
                startInput.value = firstDayFormatted;
            }
            if (!endInput.value || endInput.value.trim() === '' || !currentEnd.startsWith(String(year))) {
                endInput.value = lastDayFormatted;
            }
            
        }
        
        // Admin: Kollegen für Dropdown laden
        if (isTimeTrackingAdmin) {
            fetch(timeTrackingApiUrl + '?list_colleagues=1')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.users) {
                        colleagues = data.users;
                        const sel = document.getElementById('view-user-select');
                        if (sel) {
                            colleagues.forEach(u => {
                                const opt = document.createElement('option');
                                opt.value = u.id;
                                opt.textContent = (u.vorname || '') + ' ' + (u.nachname || '');
                                sel.appendChild(opt);
                            });
                            sel.addEventListener('change', function() {
                                viewUserId = this.value || '';
                                document.getElementById('time-tracking-status-content').classList.toggle('hidden', !!viewUserId);
                                document.getElementById('time-tracking-view-colleague-notice').classList.toggle('hidden', !viewUserId);
                                document.getElementById('time-tracking-actions-card').classList.toggle('hidden', !!viewUserId);
                                if (viewUserId) {
                                    const u = colleagues.find(c => String(c.id) === viewUserId);
                                    document.getElementById('view-colleague-notice-text').textContent = 'Sie sehen die Zeiten von ' + (u ? (u.vorname || '') + ' ' + (u.nachname || '') : '') + '. Zum Erfassen eigener Zeiten wählen Sie „Meine Zeiten“.';
                                }
                                loadAllTimeEntries();
                                filterTimeEntries();
                            });
                        }
                    }
                })
                .catch(err => console.error('Kollegen laden:', err));
        }
        
        loadTimeStatus();
        loadAllTimeEntries();
        loadSettings();
    }, 100);
});

// Alle Zeiteinträge einmal laden (ohne Filter)
function loadAllTimeEntries() {
    // Spinner anzeigen
    const tbody = document.getElementById('time-entries-body');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center"><div role="status" class="flex justify-center"><svg aria-hidden="true" class="w-8 h-8 text-gray-400 animate-spin fill-primary-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/><path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/></svg><span class="sr-only">Loading...</span></div></td></tr>';
    }
    
    // Lade ALLE Zeiteinträge (mit großem Datumsbereich)
    const startDate = new Date(2020, 0, 1); // Starte von 2020
    const endDate = new Date(2100, 11, 31); // Bis 2100
    
    const url = timeTrackingApiUrl + '?date_from=' + startDate.toISOString().split('T')[0] + '&date_to=' + endDate.toISOString().split('T')[0] + getViewUserParam();
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                allTimeEntries = data.times || [];
                // Nach dem Laden filter anwenden
                filterTimeEntries();
            } else {
                console.error('Fehler beim Laden:', data.error);
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-red-500 dark:text-red-400">Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler') + '</td></tr>';
                }
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-red-500 dark:text-red-400">Fehler beim Laden der Daten: ' + error.message + '</td></tr>';
            }
        });
}

// Hilfsfunktion: Datum konvertieren (HTML5 date input verwendet YYYY-MM-DD)
function convertDateToAPI(dateString) {
    if (!dateString) return null;
    
    // HTML5 date input gibt direkt YYYY-MM-DD zurück
    if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
        return dateString;
    }
    
    // Fallback für andere Formate (falls noch vorhanden)
    const partsDot = dateString.split('.');
    if (partsDot.length === 3 && partsDot[0].length <= 2 && partsDot[1].length <= 2) {
        const day = partsDot[0].padStart(2, '0');
        const month = partsDot[1].padStart(2, '0');
        const year = partsDot[2];
        return year + '-' + month + '-' + day;
    }
    
    return null;
}

// Clientseitig filtern basierend auf Date Range Picker
function filterTimeEntries() {
    const startInput = document.getElementById('datepicker-range-start');
    const endInput = document.getElementById('datepicker-range-end');
    
    // Falls keine Werte gesetzt, aktuellen Monat verwenden
    if (!startInput || !endInput) {
        return;
    }
    
    // Wenn keine Werte gesetzt: Von = 1. des Monats, Bis = heute
    // type="date" erwartet YYYY-MM-DD – andernfalls bleiben die Felder leer und die Filterung schlägt fehl
    if (!startInput.value || !endInput.value) {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        
        function toISODateString(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }
        
        if (!startInput.value) {
            startInput.value = toISODateString(firstDay);
        }
        if (!endInput.value) {
            endInput.value = toISODateString(today);
        }
    }
    
    const dateFrom = convertDateToAPI(startInput.value);
    const dateTo = convertDateToAPI(endInput.value);
    
    if (!dateFrom || !dateTo) {
        return;
    }
    
    // Prüfe ob Datumssuche aktiv ist
    const dateSearchInput = document.getElementById('date-search');
    const searchDate = dateSearchInput && dateSearchInput.value ? dateSearchInput.value : '';
    
    
    const fromDate = new Date(dateFrom + 'T00:00:00');
    const toDate = new Date(dateTo + 'T23:59:59');
    
    // Filtere clientseitig
    filteredTimeEntries = allTimeEntries.filter(entry => {
        const entryDate = entry.date ? new Date(entry.date + 'T00:00:00') : new Date(entry.start_time);
        const inDateRange = entryDate >= fromDate && entryDate <= toDate;
        
        // Wenn Such-Datum gesetzt ist, zusätzlich nach diesem Datum filtern
        if (searchDate && searchDate !== '') {
            const searchDateObj = new Date(searchDate + 'T00:00:00');
            const entryDateOnly = new Date(entryDate.getFullYear(), entryDate.getMonth(), entryDate.getDate());
            const searchDateOnly = new Date(searchDateObj.getFullYear(), searchDateObj.getMonth(), searchDateObj.getDate());
            return inDateRange && entryDateOnly.getTime() === searchDateOnly.getTime();
        }
        
        return inDateRange;
    });
    
    
    // Statistiken für gefilterten Zeitraum berechnen
    calculateStatsForPeriod(filteredTimeEntries, dateFrom, dateTo);
    
    // Anzeigen
    displayTimeEntries(filteredTimeEntries);
}

// Statistiken für den gefilterten Zeitraum berechnen
function calculateStatsForPeriod(entries, dateFrom, dateTo) {
    // Hier müsste die API aufgerufen werden mit den Datumsfiltern, um korrekte Statistiken zu bekommen
    // Da die Statistik-Berechnung komplex ist, rufen wir die API mit den Filtern auf
    const url = timeTrackingApiUrl + '?date_from=' + dateFrom + '&date_to=' + dateTo + getViewUserParam();
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stats) {
                updateStatistics(data.stats);
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Statistiken:', error);
        });
}

// Zeiteinträge anzeigen
function displayTimeEntries(times) {
    const tbody = document.getElementById('time-entries-body');
    
    if (!times || times.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Keine Zeiteinträge gefunden</td></tr>';
        return;
    }
    
    let html = '';
    times.forEach(entry => {
        const isVacation = entry.entry_type === 'vacation';
        const entryDate = entry.date ? new Date(entry.date + 'T00:00:00') : new Date(entry.start_time);
        const startDate = entry.start_time ? new Date(entry.start_time) : entryDate;
        const endDate = entry.end_time ? new Date(entry.end_time) : null;
        
        let duration = '-';
        if (entry.duration_minutes) {
            duration = formatDuration(entry.duration_minutes);
        } else if (isVacation && entry.hours) {
            duration = formatHours(entry.hours);
        } else if (!entry.end_time) {
            duration = 'Läuft...';
        }
        
        let description = entry.description || '-';
        if (isVacation) {
            const typeBadge = entry.type === 'vacation' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' :
                              entry.type === 'school' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' :
                              entry.type === 'sick' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' :
                              entry.type === 'holiday' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' :
                              'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
            description = `<span class="px-2 py-1 text-xs font-medium rounded-full ${typeBadge}">${entry.description}</span>`;
        }
        
        html += `
            <tr class="border-b bg-white dark:border-gray-700 dark:bg-gray-800 ${isVacation ? 'bg-blue-50 dark:bg-blue-900/20' : ''}">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                    ${formatDate(entryDate)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    ${!isVacation ? formatTime(startDate) : '-'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    ${!isVacation && endDate ? formatTime(endDate) : '-'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                    ${duration}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                    ${description}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <div class="flex items-center gap-2">
                        ${viewUserId ? '-' : (
                          !isVacation && entry.end_time ? `<button onclick="editEntry(${entry.id})" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Bearbeiten</button>` : ''
                          + (isVacation ? `<button onclick="deleteVacationEntry(${entry.id})" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">Löschen</button>` :
                            entry.end_time ? `<button onclick="deleteEntry(${entry.id})" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">Löschen</button>` : '-')
                        )}
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Zeiterfassungsstatus laden
function loadTimeStatus() {
    fetch(timeTrackingApiUrl + '?status=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                activeEntry = data.entry;
                updateTimeButton(data.isRunning);
                if (data.isRunning && data.entry) {
                    startTimer(data.entry.start_time);
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden des Status:', error);
        });
}

// Button aktualisieren
function updateTimeButton(isRunning) {
    const button = document.getElementById('time-track-button');
    const buttonText = document.getElementById('button-text');
    const buttonIcon = document.getElementById('button-icon');
    const statusText = document.getElementById('status-text');
    const runningTimeDiv = document.getElementById('running-time');
    
    if (isRunning) {
        button.classList.remove('bg-primary-600', 'hover:bg-primary-700');
        button.classList.add('bg-red-600', 'hover:bg-red-700');
        buttonText.textContent = 'Zeit beenden';
        statusText.textContent = 'Zeiterfassung läuft';
        runningTimeDiv.style.display = 'block';
        buttonIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"/>';
    } else {
        button.classList.remove('bg-red-600', 'hover:bg-red-700');
        button.classList.add('bg-primary-600', 'hover:bg-primary-700');
        buttonText.textContent = 'Zeit starten';
        statusText.textContent = 'Zeiterfassung nicht aktiv';
        runningTimeDiv.style.display = 'none';
        buttonIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        if (runningTimer) {
            clearInterval(runningTimer);
            runningTimer = null;
        }
    }
}

// Timer starten
function startTimer(startTime) {
    if (runningTimer) {
        clearInterval(runningTimer);
    }
    
    runningTimer = setInterval(function() {
        const start = new Date(startTime);
        const now = new Date();
        const diff = now - start;
        
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        
        document.getElementById('running-time-display').textContent = 
            String(hours).padStart(2, '0') + ':' + 
            String(minutes).padStart(2, '0') + ':' + 
            String(seconds).padStart(2, '0');
    }, 1000);
}

// Zeiterfassung starten/stoppen
function toggleTimeTracking() {
    
    // Status vor dem Toggle nochmal abrufen, um sicherzustellen, dass er aktuell ist
    fetch(timeTrackingApiUrl + '?status=1')
        .then(response => response.json())
        .then(statusData => {
            
            // Aktualisiere activeEntry basierend auf API-Antwort
            activeEntry = statusData.entry || null;
            const isRunning = statusData.isRunning || false;
            const action = isRunning ? 'stop' : 'start';
            
            
            fetch(timeTrackingApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action, description: null })
            })
            .then(async response => {
                const text = await response.text();
                
                if (!response.ok) {
                    try {
                        const data = JSON.parse(text);
                        throw new Error(data.error || 'HTTP error! status: ' + response.status);
                    } catch (e) {
                        if (e instanceof SyntaxError) {
                            throw new Error('HTTP error! status: ' + response.status + ' - ' + text.substring(0, 100));
                        }
                        throw e;
                    }
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON Parse Error:', e, 'Response:', text);
                    // Wenn Parse fehlschlägt, aber Status OK war, versuche trotzdem weiter
                    throw new Error('Ungültige Antwort vom Server');
                }
            })
            .then(data => {
                if (data.success) {
                    loadTimeStatus();
                    loadAllTimeEntries(); // Alle Daten neu laden
                    if (typeof showToast === 'function') {
                        showToast(data.message, 'success');
                    }
                } else {
                    console.error('API Error:', data.error);
                    if (typeof showToast === 'function') {
                        showToast(data.error || 'Fehler', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Fehler beim Starten/Stoppen der Zeiterfassung:', error);
                // Prüfe, ob es wirklich ein Fehler ist (nicht nur ein JSON-Parse-Fehler bei erfolgreicher Antwort)
                if (error.message && !error.message.includes('Unexpected token') && !error.message.includes('JSON')) {
                    if (typeof showToast === 'function') {
                        showToast('Fehler: ' + error.message, 'error');
                    }
                } else {
                    // Bei Parse-Fehlern, versuche trotzdem Status zu aktualisieren
                    loadTimeStatus();
                    loadAllTimeEntries();
                }
            });
        })
        .catch(error => {
            console.error('Fehler beim Laden des Status:', error);
            // Fallback: Versuche trotzdem zu starten
            const action = 'start';
            fetch(timeTrackingApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action, description: null })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'HTTP error! status: ' + response.status);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    loadTimeStatus();
                    loadAllTimeEntries();
                    if (typeof showToast === 'function') {
                        showToast(data.message, 'success');
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
                    showToast('Fehler: ' + error.message, 'error');
                }
            });
        });
}

// Statistiken aktualisieren
function updateStatistics(stats) {
    const totalHours = Math.floor(stats.total_minutes / 60);
    const totalMins = stats.total_minutes % 60;
    document.getElementById('total-time').textContent = totalHours + 'h ' + totalMins + 'm';
    
    const sollMinutes = stats.soll_minutes || (stats.soll_hours * 60);
    const sollHours = Math.floor(sollMinutes / 60);
    const sollMins = sollMinutes % 60;
    document.getElementById('soll-time').textContent = sollHours + 'h ' + sollMins + 'm';
    
    const overtimeMinutes = Math.round(stats.overtime_hours * 60);
    const overtimeAbs = Math.abs(overtimeMinutes);
    const overtimeHours = Math.floor(overtimeAbs / 60);
    const overtimeMins = overtimeAbs % 60;
    const overtimeSign = overtimeMinutes >= 0 ? '' : '-';
    const overtimeText = overtimeSign + overtimeHours + 'h ' + overtimeMins + 'm';
    const overtimeElement = document.getElementById('overtime-time');
    const overtimeIcon = document.getElementById('overtime-icon');
    const overtimeIconSvg = overtimeIcon?.querySelector('svg');
    
    overtimeElement.textContent = overtimeText;
    
    if (overtimeMinutes > 0) {
        overtimeElement.classList.add('text-green-600', 'dark:text-green-400');
        overtimeElement.classList.remove('text-red-600', 'dark:text-red-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-red-100', 'dark:bg-red-900', 'bg-yellow-100', 'dark:bg-yellow-900');
            overtimeIcon.classList.add('bg-green-100', 'dark:bg-green-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-red-600', 'dark:text-red-400', 'text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.classList.add('text-green-600', 'dark:text-green-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'); // trending-up
        }
    } else if (overtimeMinutes < 0) {
        overtimeElement.classList.add('text-red-600', 'dark:text-red-400');
        overtimeElement.classList.remove('text-green-600', 'dark:text-green-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-green-100', 'dark:bg-green-900', 'bg-yellow-100', 'dark:bg-yellow-900');
            overtimeIcon.classList.add('bg-red-100', 'dark:bg-red-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-green-600', 'dark:text-green-400', 'text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.classList.add('text-red-600', 'dark:text-red-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'); // trending-down
        }
    } else {
        overtimeElement.classList.remove('text-green-600', 'dark:text-green-400', 'text-red-600', 'dark:text-red-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-green-100', 'dark:bg-green-900', 'bg-red-100', 'dark:bg-red-900');
            overtimeIcon.classList.add('bg-yellow-100', 'dark:bg-yellow-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-green-600', 'dark:text-green-400', 'text-red-600', 'dark:text-red-400');
            overtimeIconSvg.classList.add('text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'); // trending-up (neutral: Plus)
        }
    }
    
    // Urlaubstage anzeigen (inkl. Krankheitstage und Feiertage in der Gesamtzahl, falls gewünscht)
    const vacationDays = stats.vacation_days || 0;
    const sickDays = stats.sick_days || 0;
    const holidayDays = stats.holiday_days || 0;
    
    // Nur Urlaubstage anzeigen (wie vorher)
    document.getElementById('vacation-days').textContent = vacationDays;
    
    // Optional: Gesamtzahl aller Sondertage in der Konsole loggen (für Debugging)
    if (vacationDays > 0 || sickDays > 0 || holidayDays > 0) {
    }
}

// Hilfsfunktionen
function formatDate(date) {
    return date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function formatTime(date) {
    return date.toLocaleTimeString('de-DE', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

function formatDuration(minutes) {
    if (!minutes || minutes === 0) return '0m';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0 && mins > 0) {
        return hours + 'h ' + mins + 'm';
    } else if (hours > 0) {
        return hours + 'h';
    } else {
        return mins + 'm';
    }
}

function formatHours(hours) {
    const hoursInt = Math.floor(hours);
    const minutes = Math.round((hours - hoursInt) * 60);
    if (minutes > 0) {
        return hoursInt + 'h ' + minutes + 'm';
    }
    return hoursInt + 'h';
}

// Bearbeiten-Funktionen
function editEntry(id) {
    const entry = filteredTimeEntries.find(e => e.id === id && e.entry_type !== 'vacation');
    
    if (!entry) {
        if (typeof showToast === 'function') {
            showToast('Eintrag nicht gefunden', 'error');
        }
        return;
    }
    
    const editModal = document.getElementById('edit-time-modal');
    const startTime = new Date(entry.start_time);
    const endTime = entry.end_time ? new Date(entry.end_time) : null;
    
    document.getElementById('edit-time-id').value = entry.id;
    document.getElementById('edit-time-date').value = startTime.toISOString().split('T')[0];
    document.getElementById('edit-time-start').value = startTime.toTimeString().substring(0, 5);
    document.getElementById('edit-time-end').value = endTime ? endTime.toTimeString().substring(0, 5) : '';
    document.getElementById('edit-time-description').value = entry.description || '';
    
    editModal.classList.remove('hidden');
}

function deleteEntry(id) {
    if (!confirm('Möchten Sie diesen Eintrag wirklich löschen?')) {
        return;
    }
    
    fetch(timeTrackingApiUrl, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAllTimeEntries();
            if (typeof showToast === 'function') {
                showToast('Eintrag gelöscht', 'success');
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
            showToast('Fehler beim Löschen', 'error');
        }
    });
}

function deleteVacationEntry(id) {
    if (!confirm('Möchten Sie diesen Urlaubstag wirklich löschen?')) {
        return;
    }
    
    fetch(timeTrackingApiUrl.replace('/api/time.php', '/api/vacation.php'), {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAllTimeEntries();
            if (typeof showToast === 'function') {
                showToast('Urlaubstag gelöscht', 'success');
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
            showToast('Fehler beim Löschen', 'error');
        }
    });
}

// Einstellungen laden
function loadSettings() {
    fetch(timeTrackingApiUrl.replace('/api/time.php', '/api/settings.php'))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const settings = data.settings;
                
                const weeklyHoursInput = document.getElementById('weekly-hours');
                const vacationDaysInput = document.getElementById('vacation-days-setting');
                const workStartTimeInput = document.getElementById('work-start-time');
                const workEndTimeInput = document.getElementById('work-end-time');
                
                if (weeklyHoursInput) weeklyHoursInput.value = settings.weekly_hours || 40;
                if (vacationDaysInput) vacationDaysInput.value = settings.vacation_days || 25;
                if (workStartTimeInput) workStartTimeInput.value = settings.work_start_time || '08:00';
                if (workEndTimeInput) workEndTimeInput.value = settings.work_end_time || '17:00';
                
                const employmentStartDateInput = document.getElementById('employment-start-date');
                if (employmentStartDateInput) {
                    employmentStartDateInput.value = settings.employment_start_date || '';
                }
                
                updateCalculatedHoursPerDay(settings);
                updateDailyHoursInCard(settings);
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Einstellungen:', error);
        });
}

function calculateHoursPerDay(weeklyHours, startTime, endTime) {
    if (!weeklyHours || !startTime || !endTime) return 0;
    
    const startParts = startTime.split(':');
    const endParts = endTime.split(':');
    const startHour = parseInt(startParts[0]) + (parseInt(startParts[1]) / 60);
    const endHour = parseInt(endParts[0]) + (parseInt(endParts[1]) / 60);
    const workHoursPerDay = endHour - startHour;
    const calculatedHoursPerDay = weeklyHours / 5;
    const hoursPerDay = Math.min(workHoursPerDay, calculatedHoursPerDay);
    
    return Math.round(hoursPerDay * 100) / 100;
}

function formatHoursPerDay(hours) {
    if (!hours || hours === 0) return '0h 0m';
    const hoursInt = Math.floor(hours);
    const minutes = Math.round((hours - hoursInt) * 60);
    if (minutes > 0) {
        return hoursInt + 'h ' + minutes + 'm';
    }
    return hoursInt + 'h';
}

function updateCalculatedHoursPerDay(settings) {
    const calculatedEl = document.getElementById('calculated-hours-per-day');
    if (calculatedEl && settings) {
        const hoursPerDay = calculateHoursPerDay(
            settings.weekly_hours || 40,
            settings.work_start_time || '08:00',
            settings.work_end_time || '17:00'
        );
        calculatedEl.textContent = formatHoursPerDay(hoursPerDay);
    }
}

function updateDailyHoursInCard(settings) {
    if (!settings) return;
    
    const dailyHoursEl = document.getElementById('daily-hours');
    const weeklyHoursEl = document.getElementById('weekly-hours-display');
    const vacationDaysEl = document.getElementById('vacation-days-display');
    
    if (dailyHoursEl) {
        const hoursPerDay = calculateHoursPerDay(
            settings.weekly_hours || 40,
            settings.work_start_time || '08:00',
            settings.work_end_time || '17:00'
        );
        dailyHoursEl.textContent = formatHoursPerDay(hoursPerDay);
    }
    
    if (weeklyHoursEl) {
        weeklyHoursEl.textContent = (settings.weekly_hours || 40) + 'h';
    }
    
    if (vacationDaysEl) {
        vacationDaysEl.textContent = settings.vacation_days || 25;
    }
}

// Modals einrichten
function setupModals() {
    // Einstellungen Modal
    const settingsButton = document.getElementById('settings-button');
    const settingsModal = document.getElementById('settings-modal');
    const settingsClose = document.getElementById('settings-modal-close');
    const settingsCancel = document.getElementById('settings-cancel');
    const settingsSave = document.getElementById('settings-save');
    const settingsOverlay = document.getElementById('settings-modal-overlay');
    
    function openSettingsModal() {
        loadSettings();
        if (settingsModal) settingsModal.classList.remove('hidden');
    }
    
    function closeSettingsModal() {
        if (settingsModal) settingsModal.classList.add('hidden');
    }
    
    if (settingsButton) settingsButton.addEventListener('click', openSettingsModal);
    if (settingsClose) settingsClose.addEventListener('click', closeSettingsModal);
    if (settingsCancel) settingsCancel.addEventListener('click', closeSettingsModal);
    if (settingsOverlay) settingsOverlay.addEventListener('click', closeSettingsModal);
    
    if (settingsSave) {
        settingsSave.addEventListener('click', function() {
            const weeklyHours = document.getElementById('weekly-hours').value;
            const vacationDays = document.getElementById('vacation-days-setting').value;
            const workStartTime = document.getElementById('work-start-time').value;
            const workEndTime = document.getElementById('work-end-time').value;
            const employmentStartDate = document.getElementById('employment-start-date').value;
            
            // Validierung
            if (!weeklyHours || weeklyHours === '') {
                if (typeof showToast === 'function') {
                    showToast('Bitte geben Sie wöchentliche Arbeitsstunden ein', 'error');
                }
                return;
            }
            
            if (!vacationDays || vacationDays === '') {
                if (typeof showToast === 'function') {
                    showToast('Bitte geben Sie Urlaubstage ein', 'error');
                }
                return;
            }
            
            if (!workStartTime || !workEndTime) {
                if (typeof showToast === 'function') {
                    showToast('Bitte geben Sie Arbeitsbeginn und -ende ein', 'error');
                }
                return;
            }
            
            const settingsUrl = timeTrackingApiUrl.replace('/api/time.php', '/api/settings.php');
            
            fetch(settingsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    weekly_hours: parseFloat(weeklyHours),
                    vacation_days: parseInt(vacationDays),
                    work_start_time: workStartTime,
                    work_end_time: workEndTime,
                    employment_start_date: employmentStartDate || null
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'HTTP error! status: ' + response.status);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeSettingsModal();
                    loadSettings();
                    filterTimeEntries(); // Statistiken neu berechnen
                    if (typeof showToast === 'function') {
                        showToast('Einstellungen gespeichert', 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.error || 'Fehler beim Speichern', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Fehler beim Speichern:', error);
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Speichern: ' + error.message, 'error');
                }
            });
        });
    }
    
    // Live-Berechnung der Stunden pro Tag
    const weeklyHoursInput = document.getElementById('weekly-hours');
    const workStartTimeInput = document.getElementById('work-start-time');
    const workEndTimeInput = document.getElementById('work-end-time');
    
    function updateCalculatedHoursLive() {
        const weeklyHours = parseFloat(weeklyHoursInput?.value || 40);
        const startTime = workStartTimeInput?.value || '08:00';
        const endTime = workEndTimeInput?.value || '17:00';
        
        const hoursPerDay = calculateHoursPerDay(weeklyHours, startTime, endTime);
        const calculatedEl = document.getElementById('calculated-hours-per-day');
        if (calculatedEl) {
            calculatedEl.textContent = formatHoursPerDay(hoursPerDay);
        }
    }
    
    if (weeklyHoursInput) {
        weeklyHoursInput.addEventListener('input', updateCalculatedHoursLive);
        weeklyHoursInput.addEventListener('change', updateCalculatedHoursLive);
    }
    if (workStartTimeInput) workStartTimeInput.addEventListener('change', updateCalculatedHoursLive);
    if (workEndTimeInput) workEndTimeInput.addEventListener('change', updateCalculatedHoursLive);
    
    // Zeit nachtragen Modal
    const addTimeButton = document.getElementById('add-time-button');
    const addTimeModal = document.getElementById('add-time-modal');
    const addTimeClose = document.getElementById('add-time-modal-close');
    const addTimeCancel = document.getElementById('add-time-cancel');
    const addTimeSave = document.getElementById('add-time-save');
    const addTimeOverlay = document.getElementById('add-time-modal-overlay');
    
    function openAddTimeModal() {
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('add-time-date');
        if (dateInput) dateInput.value = today;
        if (addTimeModal) addTimeModal.classList.remove('hidden');
    }
    
    function closeAddTimeModal() {
        if (addTimeModal) addTimeModal.classList.add('hidden');
    }
    
    if (addTimeButton) addTimeButton.addEventListener('click', openAddTimeModal);
    if (addTimeClose) addTimeClose.addEventListener('click', closeAddTimeModal);
    if (addTimeCancel) addTimeCancel.addEventListener('click', closeAddTimeModal);
    if (addTimeOverlay) addTimeOverlay.addEventListener('click', closeAddTimeModal);
    
    if (addTimeSave) {
        addTimeSave.addEventListener('click', function() {
            const date = document.getElementById('add-time-date').value;
            const startTime = document.getElementById('add-time-start').value;
            const endTime = document.getElementById('add-time-end').value;
            const description = document.getElementById('add-time-description').value;
            
            if (!date || !startTime || !endTime) {
                if (typeof showToast === 'function') {
                    showToast('Bitte füllen Sie alle Felder aus', 'error');
                }
                return;
            }
            
            const startDateTime = date + 'T' + startTime + ':00';
            const endDateTime = date + 'T' + endTime + ':00';
            
            fetch(timeTrackingApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add',
                    start_time: startDateTime,
                    end_time: endDateTime,
                    description: description || null
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddTimeModal();
                    loadAllTimeEntries();
                    if (typeof showToast === 'function') {
                        showToast('Zeiteintrag hinzugefügt', 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.error || 'Fehler beim Speichern', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Speichern', 'error');
                }
            });
        });
    }
    
    // Urlaub eintragen Modal
    const addVacationButton = document.getElementById('add-vacation-button');
    const addVacationModal = document.getElementById('add-vacation-modal');
    const addVacationClose = document.getElementById('add-vacation-modal-close');
    const addVacationCancel = document.getElementById('add-vacation-cancel');
    const addVacationSave = document.getElementById('add-vacation-save');
    const addVacationOverlay = document.getElementById('add-vacation-modal-overlay');
    
    function openAddVacationModal() {
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('add-vacation-date');
        if (dateInput) dateInput.value = today;
        if (addVacationModal) addVacationModal.classList.remove('hidden');
    }
    
    function closeAddVacationModal() {
        if (addVacationModal) addVacationModal.classList.add('hidden');
    }
    
    if (addVacationButton) addVacationButton.addEventListener('click', openAddVacationModal);
    if (addVacationClose) addVacationClose.addEventListener('click', closeAddVacationModal);
    if (addVacationCancel) addVacationCancel.addEventListener('click', closeAddVacationModal);
    if (addVacationOverlay) addVacationOverlay.addEventListener('click', closeAddVacationModal);
    
    if (addVacationSave) {
        addVacationSave.addEventListener('click', function() {
            const date = document.getElementById('add-vacation-date').value;
            const hours = document.getElementById('add-vacation-hours').value;
            const type = document.getElementById('add-vacation-type').value;
            
            if (!date) {
                if (typeof showToast === 'function') {
                    showToast('Bitte geben Sie ein Datum ein', 'error');
                }
                return;
            }
            
            fetch(timeTrackingApiUrl.replace('/api/time.php', '/api/vacation.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date: date,
                    hours: parseFloat(hours) || 8,
                    type: type
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddVacationModal();
                    loadAllTimeEntries();
                    if (typeof showToast === 'function') {
                        showToast('Urlaubstag hinzugefügt', 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.error || 'Fehler beim Speichern', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Speichern', 'error');
                }
            });
        });
    }
    
    // Zeit bearbeiten Modal
    const editTimeModal = document.getElementById('edit-time-modal');
    const editTimeClose = document.getElementById('edit-time-modal-close');
    const editTimeCancel = document.getElementById('edit-time-cancel');
    const editTimeSave = document.getElementById('edit-time-save');
    const editTimeOverlay = document.getElementById('edit-time-modal-overlay');
    
    function closeEditTimeModal() {
        if (editTimeModal) editTimeModal.classList.add('hidden');
    }
    
    if (editTimeClose) editTimeClose.addEventListener('click', closeEditTimeModal);
    if (editTimeCancel) editTimeCancel.addEventListener('click', closeEditTimeModal);
    if (editTimeOverlay) editTimeOverlay.addEventListener('click', closeEditTimeModal);
    
    if (editTimeSave) {
        editTimeSave.addEventListener('click', function() {
            const entryId = document.getElementById('edit-time-id').value;
            const date = document.getElementById('edit-time-date').value;
            const startTime = document.getElementById('edit-time-start').value;
            const endTime = document.getElementById('edit-time-end').value;
            const description = document.getElementById('edit-time-description').value;
            
            if (!entryId || !date || !startTime || !endTime) {
                if (typeof showToast === 'function') {
                    showToast('Bitte füllen Sie alle Felder aus', 'error');
                }
                return;
            }
            
            const startDateTime = date + 'T' + startTime + ':00';
            const endDateTime = date + 'T' + endTime + ':00';
            
            fetch(timeTrackingApiUrl, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: parseInt(entryId),
                    start_time: startDateTime,
                    end_time: endDateTime,
                    description: description || null
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'HTTP error! status: ' + response.status);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeEditTimeModal();
                    // Datumsfilter auf den bearbeiteten Eintrag setzen, damit er nach dem Neuladen sichtbar bleibt
                    const startInput = document.getElementById('datepicker-range-start');
                    const endInput = document.getElementById('datepicker-range-end');
                    const dateSearchInput = document.getElementById('date-search');
                    if (date && startInput && endInput) {
                        const editedDate = new Date(date + 'T12:00:00');
                        const firstOfMonth = new Date(editedDate.getFullYear(), editedDate.getMonth(), 1);
                        const lastOfMonth = new Date(editedDate.getFullYear(), editedDate.getMonth() + 1, 0);
                        startInput.value = firstOfMonth.getFullYear() + '-' + String(firstOfMonth.getMonth() + 1).padStart(2, '0') + '-' + String(firstOfMonth.getDate()).padStart(2, '0');
                        endInput.value = lastOfMonth.getFullYear() + '-' + String(lastOfMonth.getMonth() + 1).padStart(2, '0') + '-' + String(lastOfMonth.getDate()).padStart(2, '0');
                        if (dateSearchInput) dateSearchInput.value = '';
                    }
                    loadAllTimeEntries();
                    if (typeof showToast === 'function') {
                        showToast('Zeiteintrag aktualisiert', 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.error || 'Fehler beim Speichern', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Speichern: ' + error.message, 'error');
                }
            });
        });
    }
}

// Arbeitszeiten-Jahr laden
function loadWorkTimesYear() {
    const currentYearSpan = document.getElementById('current-year');
    const loadingDiv = document.getElementById('work-times-loading');
    const tableContainer = document.getElementById('work-times-table-container');
    const table = document.getElementById('work-times-table');
    const thead = document.getElementById('work-times-thead');
    const tbody = document.getElementById('work-times-tbody');
    
    if (!currentYearSpan || !loadingDiv || !tableContainer || !table || !thead || !tbody) {
        console.error('Arbeitszeiten-Elemente nicht gefunden');
        return;
    }
    
    const year = parseInt(currentYearSpan.textContent) || new Date().getFullYear();
    
    // Loading anzeigen
    loadingDiv.classList.remove('hidden');
    tableContainer.classList.add('hidden');
    
    const url = timeTrackingApiUrl + '?year_overview=1&year=' + year + getViewUserParam();
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                displayWorkTimesTable(data.data);
                loadingDiv.classList.add('hidden');
                tableContainer.classList.remove('hidden');
            } else {
                console.error('Fehler beim Laden:', data.error);
                loadingDiv.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-8">Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler') + '</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            loadingDiv.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-8">Fehler beim Laden: ' + error.message + '</div>';
        });
}

// Arbeitszeiten-Tabelle anzeigen
function displayWorkTimesTable(yearData) {
    const thead = document.getElementById('work-times-thead');
    const tbody = document.getElementById('work-times-tbody');
    
    if (!thead || !tbody || !yearData || !yearData.months) {
        console.error('Daten oder Elemente nicht gefunden');
        return;
    }
    
    const minutesPerDay = yearData.minutesPerDay || 480; // 8 Stunden Standard
    
    // Header erstellen (Monat + Tage 1-31)
    let headerHtml = '<tr>';
    headerHtml += '<th class="px-4 py-3 text-left font-bold bg-gray-200 dark:bg-gray-700 sticky left-0 z-20 min-w-[140px] border-r border-gray-300 dark:border-gray-600 shadow-md whitespace-nowrap">Monat</th>';
    for (let day = 1; day <= 31; day++) {
        headerHtml += `<th class="px-3 py-3 text-center font-bold min-w-[70px] border-l border-gray-200 dark:border-gray-600 whitespace-nowrap">${day}</th>`;
    }
    headerHtml += '</tr>';
    thead.innerHTML = headerHtml;
    
    // Body erstellen
    let bodyHtml = '';
    
    yearData.months.forEach((monthData, monthIndex) => {
        // Abwechselnde Zeilen-Hintergrundfarbe für bessere Lesbarkeit
        const rowBg = monthIndex % 2 === 0 
            ? 'bg-white dark:bg-gray-800' 
            : 'bg-gray-50 dark:bg-gray-900/50';
        
        bodyHtml += `<tr class="${rowBg} hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150">`;
        // Monat
        bodyHtml += `<td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100 sticky left-0 z-10 bg-inherit border-r border-gray-200 dark:border-gray-600 shadow-sm whitespace-nowrap">${monthData.monthName}</td>`;
        
        // Tage 1-31
        for (let day = 1; day <= 31; day++) {
            const dayData = monthData.days[day];
            
            if (!dayData) {
                // Tag existiert nicht in diesem Monat
                bodyHtml += '<td class="px-3 py-3 text-center bg-gray-100 dark:bg-gray-950 border-l border-gray-200 dark:border-gray-700"></td>';
            } else {
                const { minutes, type, status } = dayData;
                
                // Hintergrundfarbe basierend auf Status - verbesserte Farben und Kontraste
                let bgColor = '';
                let textColor = '';
                let borderColor = '';
                let hoverEffect = '';
                
                if (status === 'overtime') {
                    // Überstunden (grün) - kräftiger
                    bgColor = 'bg-green-200 dark:bg-green-800/50';
                    textColor = 'text-green-900 dark:text-green-100';
                    borderColor = 'border-green-300 dark:border-green-700';
                    hoverEffect = 'hover:bg-green-300 dark:hover:bg-green-800';
                } else if (status === 'minus') {
                    // Minus (rot) - kräftiger
                    bgColor = 'bg-red-200 dark:bg-red-800/50';
                    textColor = 'text-red-900 dark:text-red-100';
                    borderColor = 'border-red-300 dark:border-red-700';
                    hoverEffect = 'hover:bg-red-300 dark:hover:bg-red-800';
                } else if (status === 'special') {
                    // Urlaub/Krank/Feiertag (blau) - kräftiger
                    bgColor = 'bg-blue-200 dark:bg-blue-800/50';
                    textColor = 'text-blue-900 dark:text-blue-100';
                    borderColor = 'border-blue-300 dark:border-blue-700';
                    hoverEffect = 'hover:bg-blue-300 dark:hover:bg-blue-800';
                } else if (status === 'weekend') {
                    // Wochenende (grau)
                    bgColor = 'bg-gray-100 dark:bg-gray-950';
                    textColor = 'text-gray-400 dark:text-gray-600';
                    borderColor = 'border-gray-200 dark:border-gray-700';
                    hoverEffect = '';
                } else {
                    // Normal (gelb) - kräftiger
                    bgColor = 'bg-yellow-100 dark:bg-yellow-900/40';
                    textColor = 'text-yellow-900 dark:text-yellow-100';
                    borderColor = 'border-yellow-200 dark:border-yellow-800';
                    hoverEffect = 'hover:bg-yellow-200 dark:hover:bg-yellow-900/60';
                }
                
                // Formatierung der Zeit
                let timeDisplay = '-';
                if (minutes > 0) {
                    const hours = Math.floor(minutes / 60);
                    const mins = minutes % 60;
                    if (hours > 0 && mins > 0) {
                        timeDisplay = hours + 'h ' + mins + 'm';
                    } else if (hours > 0) {
                        timeDisplay = hours + 'h';
                    } else {
                        timeDisplay = mins + 'm';
                    }
                } else if (status === 'weekend') {
                    timeDisplay = '';
                }
                
                bodyHtml += `<td class="px-3 py-3 text-center text-sm font-medium ${bgColor} ${textColor} border-l ${borderColor} ${hoverEffect} transition-colors duration-150 cursor-default" title="${status === 'special' ? (type === 'vacation' ? 'Urlaub' : type === 'sick' ? 'Krank' : type === 'holiday' ? 'Feiertag' : type === 'school' ? 'Berufsschule' : 'Sonstiges') : status}">${timeDisplay}</td>`;
            }
        }
        
        bodyHtml += '</tr>';
    });
    
    tbody.innerHTML = bodyHtml;
}

// Statistiken für ein bestimmtes Jahr laden
function loadStatsYear() {
    const loadingDiv = document.getElementById('stats-year-loading');
    const contentDiv = document.getElementById('stats-year-content');
    const statsCurrentYearSpan = document.getElementById('stats-current-year');
    
    if (!loadingDiv || !contentDiv) {
        console.error('Statistiken-Jahr-Elemente nicht gefunden');
        return;
    }
    
    loadingDiv.classList.remove('hidden');
    contentDiv.classList.add('hidden');
    
    const year = statsCurrentYearSpan ? parseInt(statsCurrentYearSpan.textContent) || new Date().getFullYear() : new Date().getFullYear();
    const url = timeTrackingApiUrl + '?stats=year&year=' + year + getViewUserParam();
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.stats) {
                updateStatsYear(data.stats);
                loadMonthlyStats(year); // Lade monatliche Statistik
                loadingDiv.classList.add('hidden');
                contentDiv.classList.remove('hidden');
            } else {
                console.error('Fehler beim Laden:', data.error);
                loadingDiv.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-8">Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler') + '</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            loadingDiv.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-8">Fehler beim Laden: ' + error.message + '</div>';
        });
}

// Monatliche Statistiken laden und visualisieren
function loadMonthlyStats(year) {
    const chartLoading = document.getElementById('monthly-chart-loading');
    const chartContainer = document.getElementById('monthly-chart-container');
    const chartDiv = document.getElementById('monthly-chart');
    
    if (!chartLoading || !chartContainer || !chartDiv) {
        console.error('Monatliche Chart-Elemente nicht gefunden');
        return;
    }
    
    chartLoading.classList.remove('hidden');
    chartContainer.classList.add('hidden');
    
    const url = timeTrackingApiUrl + '?stats=monthly&year=' + year + getViewUserParam();
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                displayMonthlyChart(data.data);
                chartLoading.classList.add('hidden');
                chartContainer.classList.remove('hidden');
            } else {
                console.error('Fehler beim Laden der monatlichen Statistiken:', data.error);
                chartLoading.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler beim Laden</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            chartLoading.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler: ' + error.message + '</div>';
        });
}

// Monatliche Chart anzeigen
function displayMonthlyChart(monthlyData) {
    const chartDiv = document.getElementById('monthly-chart');
    if (!chartDiv || !monthlyData || monthlyData.length === 0) {
        return;
    }
    
    // Finde das Maximum für die Skalierung
    const maxHours = Math.max(...monthlyData.map(m => m.total_hours || 0));
    const maxValue = Math.max(maxHours, 200); // Mindestens 200 Stunden für bessere Darstellung
    
    let html = '';
    
    monthlyData.forEach(month => {
        const hours = month.total_hours || 0;
        const percentage = maxValue > 0 ? (hours / maxValue) * 100 : 0;
        const hoursInt = Math.floor(hours);
        const minutes = Math.round((hours - hoursInt) * 60);
        const displayText = hoursInt > 0 ? `${hoursInt}h ${minutes}m` : `${minutes}m`;
        
        // Farbe basierend auf Stunden (mehr = grüner)
        let barColor = 'bg-blue-500';
        if (hours >= 150) {
            barColor = 'bg-green-500';
        } else if (hours >= 100) {
            barColor = 'bg-green-400';
        } else if (hours >= 50) {
            barColor = 'bg-yellow-500';
        } else if (hours > 0) {
            barColor = 'bg-orange-500';
        } else {
            barColor = 'bg-gray-300 dark:bg-gray-600';
        }
        
        html += `
            <div class="flex items-center gap-3">
                <div class="w-24 text-sm font-medium text-gray-700 dark:text-gray-300 min-w-[80px]">
                    ${month.monthName}
                </div>
                <div class="flex-1 relative">
                    <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden">
                        <div class="${barColor} h-full rounded-lg transition-all duration-500 flex items-center justify-end pr-2" style="width: ${percentage}%">
                            ${percentage > 10 ? `<span class="text-xs font-semibold text-white dark:text-white">${displayText}</span>` : ''}
                        </div>
                    </div>
                </div>
                <div class="w-20 text-right text-sm font-semibold text-gray-900 dark:text-white min-w-[70px]">
                    ${displayText}
                </div>
            </div>
        `;
    });
    
    chartDiv.innerHTML = html;
}

// Statistiken für aktuelles Jahr anzeigen
function updateStatsYear(stats) {
    const totalHours = Math.floor(stats.total_minutes / 60);
    const totalMins = stats.total_minutes % 60;
    document.getElementById('stats-year-total-time').textContent = totalHours + 'h ' + totalMins + 'm';
    
    const sollMinutes = stats.soll_minutes || (stats.soll_hours * 60);
    const sollHours = Math.floor(sollMinutes / 60);
    const sollMins = sollMinutes % 60;
    document.getElementById('stats-year-soll-time').textContent = sollHours + 'h ' + sollMins + 'm';
    
    const overtimeMinutes = Math.round(stats.overtime_hours * 60);
    const overtimeAbs = Math.abs(overtimeMinutes);
    const overtimeHours = Math.floor(overtimeAbs / 60);
    const overtimeMins = overtimeAbs % 60;
    const overtimeSign = overtimeMinutes >= 0 ? '' : '-';
    const overtimeText = overtimeSign + overtimeHours + 'h ' + overtimeMins + 'm';
    const overtimeElement = document.getElementById('stats-year-overtime-time');
    const overtimeIcon = document.getElementById('stats-year-overtime-icon');
    const overtimeIconSvg = overtimeIcon?.querySelector('svg');
    
    overtimeElement.textContent = overtimeText;
    
    if (overtimeMinutes > 0) {
        overtimeElement.classList.add('text-green-600', 'dark:text-green-400');
        overtimeElement.classList.remove('text-red-600', 'dark:text-red-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-red-100', 'dark:bg-red-900', 'bg-yellow-100', 'dark:bg-yellow-900');
            overtimeIcon.classList.add('bg-green-100', 'dark:bg-green-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-red-600', 'dark:text-red-400', 'text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.classList.add('text-green-600', 'dark:text-green-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'); // trending-up
        }
    } else if (overtimeMinutes < 0) {
        overtimeElement.classList.add('text-red-600', 'dark:text-red-400');
        overtimeElement.classList.remove('text-green-600', 'dark:text-green-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-green-100', 'dark:bg-green-900', 'bg-yellow-100', 'dark:bg-yellow-900');
            overtimeIcon.classList.add('bg-red-100', 'dark:bg-red-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-green-600', 'dark:text-green-400', 'text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.classList.add('text-red-600', 'dark:text-red-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'); // trending-down
        }
    } else {
        overtimeElement.classList.remove('text-green-600', 'dark:text-green-400', 'text-red-600', 'dark:text-red-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-green-100', 'dark:bg-green-900', 'bg-red-100', 'dark:bg-red-900');
            overtimeIcon.classList.add('bg-yellow-100', 'dark:bg-yellow-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-green-600', 'dark:text-green-400', 'text-red-600', 'dark:text-red-400');
            overtimeIconSvg.classList.add('text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'); // trending-up (neutral: Plus)
        }
    }
    
    document.getElementById('stats-year-vacation-days').textContent = stats.vacation_days || 0;
}

// Statistiken insgesamt laden
function loadStatsTotal() {
    const loadingDiv = document.getElementById('stats-total-loading');
    const contentDiv = document.getElementById('stats-total-content');
    
    if (!loadingDiv || !contentDiv) {
        console.error('Statistiken-Gesamt-Elemente nicht gefunden');
        return;
    }
    
    loadingDiv.classList.remove('hidden');
    contentDiv.classList.add('hidden');
    
    const url = timeTrackingApiUrl + '?stats=total' + getViewUserParam();
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.stats) {
                updateStatsTotal(data.stats);
                loadingDiv.classList.add('hidden');
                contentDiv.classList.remove('hidden');
            } else {
                console.error('Fehler beim Laden:', data.error);
                loadingDiv.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-8">Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler') + '</div>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            loadingDiv.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-8">Fehler beim Laden: ' + error.message + '</div>';
        });
}

// Statistiken insgesamt anzeigen
function updateStatsTotal(stats) {
    const totalHours = Math.floor(stats.total_minutes / 60);
    const totalMins = stats.total_minutes % 60;
    document.getElementById('stats-total-total-time').textContent = totalHours + 'h ' + totalMins + 'm';
    
    const sollMinutes = stats.soll_minutes || (stats.soll_hours * 60);
    const sollHours = Math.floor(sollMinutes / 60);
    const sollMins = sollMinutes % 60;
    document.getElementById('stats-total-soll-time').textContent = sollHours + 'h ' + sollMins + 'm';
    
    const overtimeMinutes = Math.round(stats.overtime_hours * 60);
    const overtimeAbs = Math.abs(overtimeMinutes);
    const overtimeHours = Math.floor(overtimeAbs / 60);
    const overtimeMins = overtimeAbs % 60;
    const overtimeSign = overtimeMinutes >= 0 ? '' : '-';
    const overtimeText = overtimeSign + overtimeHours + 'h ' + overtimeMins + 'm';
    const overtimeElement = document.getElementById('stats-total-overtime-time');
    const overtimeIcon = document.getElementById('stats-total-overtime-icon');
    const overtimeIconSvg = overtimeIcon?.querySelector('svg');
    
    overtimeElement.textContent = overtimeText;
    
    if (overtimeMinutes > 0) {
        overtimeElement.classList.add('text-green-600', 'dark:text-green-400');
        overtimeElement.classList.remove('text-red-600', 'dark:text-red-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-red-100', 'dark:bg-red-900', 'bg-yellow-100', 'dark:bg-yellow-900');
            overtimeIcon.classList.add('bg-green-100', 'dark:bg-green-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-red-600', 'dark:text-red-400', 'text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.classList.add('text-green-600', 'dark:text-green-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'); // trending-up
        }
    } else if (overtimeMinutes < 0) {
        overtimeElement.classList.add('text-red-600', 'dark:text-red-400');
        overtimeElement.classList.remove('text-green-600', 'dark:text-green-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-green-100', 'dark:bg-green-900', 'bg-yellow-100', 'dark:bg-yellow-900');
            overtimeIcon.classList.add('bg-red-100', 'dark:bg-red-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-green-600', 'dark:text-green-400', 'text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.classList.add('text-red-600', 'dark:text-red-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'); // trending-down
        }
    } else {
        overtimeElement.classList.remove('text-green-600', 'dark:text-green-400', 'text-red-600', 'dark:text-red-400');
        if (overtimeIcon) {
            overtimeIcon.classList.remove('bg-green-100', 'dark:bg-green-900', 'bg-red-100', 'dark:bg-red-900');
            overtimeIcon.classList.add('bg-yellow-100', 'dark:bg-yellow-900');
        }
        if (overtimeIconSvg) {
            overtimeIconSvg.classList.remove('text-green-600', 'dark:text-green-400', 'text-red-600', 'dark:text-red-400');
            overtimeIconSvg.classList.add('text-yellow-600', 'dark:text-yellow-400');
            overtimeIconSvg.querySelector('path')?.setAttribute('d', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'); // trending-up (neutral: Plus)
        }
    }
    
    document.getElementById('stats-total-vacation-days').textContent = stats.vacation_days || 0;
}
</script>
<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
