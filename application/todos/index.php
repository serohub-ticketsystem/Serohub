<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Prüfen ob Benutzer die richtige Rolle hat (nur Techniker und Admins)
try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        showPermissionDeniedPage();
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Datenbankfehler beim Prüfen der Berechtigung.');
}

// Ausgewählte Firma aus Session lesen
$selectedCompanyId = null;
$selectedCompanyName = null;
if (isset($_SESSION['selected_company_id'])) {
    $selectedCompanyId = (int)$_SESSION['selected_company_id'];
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv'");
        $stmt->execute([$selectedCompanyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($company) {
            $selectedCompanyName = $company['name'];
        }
    } catch (PDOException $e) {
        // Ignorieren, falls Firma nicht gefunden
    }
}

include dirname(__DIR__) . '/assets/frontend/head.php';
$navMobileShowIntegratedFilter = true;
$navMobileCompactTitle = 'Aufgaben';
$navMobileTodosQuickComposer = true;
$navMobileTodosSearchToggle = true;
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
<style>
input[type="checkbox"].todo-checkbox:checked {
    background-color: var(--primary-600, #2563eb);
    border-color: var(--primary-600, #2563eb);
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3ccircle cx='8' cy='8' r='3'/%3e%3c/svg%3e");
    background-size: 100% 100%;
    background-position: center;
    background-repeat: no-repeat;
}

/* Scrollbar verstecken aber scrollbar bleiben */
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

#folderFilters {
    flex-shrink: 0;
}

#folderFilters button {
    flex-shrink: 0;
    padding-top: 0.375rem;
    padding-bottom: 0.375rem;
    min-height: 2.25rem;
    height: auto;
    /* Horizontales Wischen auch auf dem Button: Scroll-Parent darf mitscrollen */
    touch-action: pan-x;
    /* Verhindert Flackern beim Neuladen durch Hardware-Beschleunigung */
    will-change: auto;
    backface-visibility: hidden;
    transform: translateZ(0);
}

/* Ordner-Filter Container mit visuellen Hinweisen */
.folder-filters-wrapper {
    position: relative;
}

.folder-filters-wrapper::before,
.folder-filters-wrapper::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 40px;
    pointer-events: none;
    z-index: 1;
    transition: opacity 0.3s ease;
    opacity: 0;
}

.folder-filters-wrapper::before {
    left: 0;
    background: linear-gradient(to right, color-mix(in srgb, var(--page-bg) 95%, transparent), transparent);
}

.folder-filters-wrapper::after {
    right: 0;
    background: linear-gradient(to left, color-mix(in srgb, var(--page-bg) 95%, transparent), transparent);
}

.folder-filters-wrapper.has-scroll-left::before {
    opacity: 1;
}

.folder-filters-wrapper.has-scroll-right::after {
    opacity: 1;
}

.folder-filters-scroll {
    cursor: grab;
    user-select: none;
    -webkit-user-select: none;
    touch-action: pan-x;
}

.folder-filters-scroll:active {
    cursor: grabbing;
}

/* Scrollbar verstecken für Ordner-Filter */
.folder-filters-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.folder-filters-scroll::-webkit-scrollbar {
    display: none;
}

/* Mobil: Ordner-Panel unter Top-Nav (gleiches Muster wie Tickets) */
@media (max-width: 1023px) {
  #mobileFilterSheet[aria-hidden="true"] {
    visibility: hidden !important;
    pointer-events: none !important;
  }
  #mobileFilterSheet[aria-hidden="false"] {
    visibility: visible !important;
  }
  /*
   * Scroll-Chaining: Ohne das folgt ein vertikaler Wisch (z. B. Panel schließen) dem
   * scrollenden #main-content unter dem Overlay — bei Tickets weniger auffällig, hier stört es.
   */
  #mobileFilterSheet[aria-hidden="false"] #mobileFilterSheetBackdrop {
    touch-action: none;
  }
  #mobileFilterSheet[aria-hidden="false"] #mobileFilterSheetHandle {
    touch-action: none;
  }
  #mobileFilterSheetScroll {
    overscroll-behavior-y: contain;
  }
  #mobileFilterSheetPanel {
    max-height: 0;
    transition: max-height 0.32s ease-out;
    border-left-color: rgb(209 213 219);
    border-right-color: rgb(209 213 219);
    border-bottom-color: rgb(203 213 225);
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255),
      inset 0 3px 14px -3px rgb(255 255 255 / 0.55),
      inset 0 -1px 0 0 rgb(15 23 42 / 0.07),
      0 4px 24px rgb(15 23 42 / 0.09),
      0 1px 0 0 rgb(15 23 42 / 0.1);
  }
  /* Genug Höhe für aufgeklapptes Formular + Kollegen + Buttons (28rem war zu wenig) */
  #mobileFilterSheetPanel.mobile-filter-sheet-open {
    max-height: min(92dvh, 92vh, 52rem);
    overscroll-behavior-y: contain;
  }
  /* Scroll-Inhalt nur so hoch wie nötig (flex-1 erzeugte riesigen Leerraum bis zum Griff) */
  #mobileFilterSheetPanel.mobile-filter-sheet-open #mobileFilterSheetScroll {
    flex: 0 1 auto;
    min-height: 0;
    max-height: min(calc(92dvh - 5rem), calc(92vh - 5rem), calc(52rem - 5rem));
  }
  #navMobileFilterToggleBtn[aria-expanded="true"] .nav-mobile-filter-chevron {
    transform: rotate(180deg);
  }
  .dark #mobileFilterSheetPanel {
    background-color: rgb(5 5 5 / 0.48) !important;
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.26),
      inset 0 3px 16px -3px rgb(255 255 255 / 0.1),
      inset 0 -1px 0 0 rgb(0 0 0 / 0.55),
      0 4px 30px rgb(0 0 0 / 0.52),
      0 18px 44px rgb(0 0 0 / 0.35);
  }
  #mobileFilterSheetScroll .mobile-filter-sheet-row {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }
  html:not(.dark) #mobileFilterSheetScroll .mobile-filter-sheet-row > label,
  html:not(.dark) #mobileFilterSheetScroll .mobile-filter-sheet-row > .mobile-filter-sheet-badge-label {
    color: #ffffff;
    background-color: #000000;
    padding: 0.35rem 0.65rem;
    border-radius: 0.5rem;
    display: inline-block;
    width: fit-content;
    max-width: 100%;
  }
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-row > label,
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-row > .mobile-filter-sheet-badge-label {
    color: #ffffff;
    background-color: transparent;
    padding: 0;
    display: inline-block;
    width: fit-content;
    max-width: 100%;
  }
  #mobileFilterSheetScroll .mobile-filter-sheet-field {
    width: 100%;
    min-height: 3.25rem;
    box-sizing: border-box;
    border-radius: 1.25rem;
    border-width: 1px;
    border-style: solid;
    border-color: rgb(229 229 229);
    background-color: #f6f6f6;
    padding: 0.75rem 1.125rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    font-weight: 500;
    color: rgb(17 24 39);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.65),
      0 1px 2px rgb(15 23 42 / 0.04),
      0 4px 14px rgb(15 23 42 / 0.05);
  }
  #mobileFilterSheetScroll select.mobile-filter-sheet-field {
    appearance: none;
    -webkit-appearance: none;
    padding-right: 2.75rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1.125rem 1.125rem;
  }
  #mobileFilterSheetScroll .mobile-filter-sheet-field:focus {
    outline: none;
    border-color: rgb(191 219 254);
    box-shadow:
      0 0 0 2px rgb(59 130 246 / 0.22),
      inset 0 1px 0 0 rgb(255 255 255 / 0.65),
      0 1px 2px rgb(15 23 42 / 0.04),
      0 4px 14px rgb(15 23 42 / 0.05);
  }
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-field {
    border-color: transparent;
    border-width: 0;
    background-color: #121212;
    color: rgb(245 245 245);
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.05),
      0 2px 8px rgb(0 0 0 / 0.45),
      0 1px 0 0 rgb(255 255 255 / 0.03);
  }
  .dark #mobileFilterSheetScroll select.mobile-filter-sheet-field {
    background-color: #121212;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a1a1aa'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  }
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-field:focus {
    border-color: transparent;
    box-shadow:
      0 0 0 2px rgb(59 130 246 / 0.45),
      inset 0 1px 0 0 rgb(255 255 255 / 0.06),
      0 2px 10px rgb(0 0 0 / 0.5);
  }
  #mobileFilterSheetScroll .mobile-filter-sheet-create-btn {
    width: 100%;
    min-height: 3.25rem;
    box-sizing: border-box;
    border-radius: 1.25rem;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    padding: 0.75rem 1.125rem;
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.12),
      0 1px 2px rgb(15 23 42 / 0.08),
      0 4px 14px rgb(15 23 42 / 0.06);
  }
  .dark #mobileFilterSheetScroll .mobile-filter-sheet-create-btn {
    box-shadow:
      inset 0 1px 0 0 rgb(255 255 255 / 0.08),
      0 2px 8px rgb(0 0 0 / 0.35);
  }
  /* „Ordner erstellen“: nur Höhen-Animation, ohne zweite Karte (Inhalt wie Rest des Sheets) */
  #mobile-sheet-create-folder-shell {
    max-height: 3.35rem;
    overflow: hidden;
    -webkit-tap-highlight-color: transparent;
    border: none;
    background: transparent;
    box-shadow: none;
    border-radius: 0;
    transition: max-height 0.38s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .dark #mobile-sheet-create-folder-shell {
    background: transparent;
    box-shadow: none;
  }
  /* Aufgeklappt: großzügige max-height für Transition; eigentliches Scrollen in #mobileFilterSheetScroll */
  #mobile-sheet-create-folder-shell.mobile-sheet-create-folder-expanded {
    max-height: min(200vh, 80rem);
    overflow: visible;
  }
  .mobile-sheet-create-folder-expanded #mobile-sheet-create-folder-btn {
    display: none;
  }
  #mobile-sheet-create-folder-form {
    display: none;
    flex-direction: column;
    gap: 0.875rem;
    padding: 0;
    padding-top: 0.875rem;
    min-height: 0;
  }
  .mobile-sheet-create-folder-expanded #mobile-sheet-create-folder-form {
    display: flex;
  }
  #mobile-sheet-create-folder-form .mobile-folder-vis-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem;
  }
  #mobile-sheet-create-folder-form .folder-visibility-card {
    padding: 0.5rem 0.25rem;
    gap: 0.25rem;
  }
  #mobile-sheet-create-folder-form .folder-visibility-card span {
    font-size: 0.7rem;
    line-height: 1.1;
  }
  #mobile-sheet-folder-invite-section:not(.hidden) {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }
  /* Kein eigenes Scroll-Fenster: gesamtes Sheet scrollt (vermeidet Abschneiden + Touch-Konflikt mit Wisch-Schließen) */
  #mobile-sheet-folder-candidates-list {
    min-height: 3rem;
    max-height: none;
    overflow: visible;
  }
}

/* Mobil: Schnell-Eingabe per Top-Nav-Plus; Footer-Nav weicht (nur diese Seite lädt dieses CSS) */
@media (max-width: 1023px) {
  /*
   * Nur transform animieren — visibility/opacity-Transition hält das Feld auf WebKit teils „unsichtbar“,
   * dann schlägt focus() fehl und die Tastatur öffnet nicht.
   */
  #quickTodoBar {
    transform: translateY(100%);
    visibility: hidden;
    pointer-events: none;
    transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
    z-index: 2;
    padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
  }
  body.todos-mobile-quick-compose #quickTodoBar {
    transform: translateY(0);
    visibility: visible;
    pointer-events: auto;
    /* Über Aufgabenliste/Scroll-Fläche; unter globalem Filter-Sheet (68) wenn das offen ist */
    z-index: 71;
    box-shadow: none;
  }
  .dark body.todos-mobile-quick-compose #quickTodoBar {
    box-shadow: none;
  }
  #appMobileFooterRoot {
    transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.22s ease, visibility 0.22s;
  }
  body.todos-mobile-quick-compose #appMobileFooterRoot {
    transform: translateY(calc(100% + 0.75rem));
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
  }
  /* Immer im Stack: geschlossen pointer-events:none → Klicks gehen zur Liste; offen auto → wie bisher schließen */
  #todosQuickComposeBackdrop {
    position: fixed;
    display: block;
    pointer-events: none;
    z-index: 70;
    top: calc(env(safe-area-inset-top, 0px) + 3.5rem);
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    touch-action: manipulation;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
  }
  body.todos-mobile-quick-compose #todosQuickComposeBackdrop {
    pointer-events: auto;
  }

  /* Aufgabenliste: größere Klickflächen, weniger Zitter-Treffer */
  #todosList .todo-item,
  #closedTodosList .todo-item {
    -webkit-tap-highlight-color: transparent;
  }

  /* Wisch-Aktionen (nur Mobil): eigenes Stacking, damit die Karte die farbigen Flächen zuverlässig überdeckt */
  .todo-item {
    isolation: isolate;
  }
  .todo-swipe-track {
    -webkit-tap-highlight-color: transparent;
    touch-action: pan-y;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
  }
  /* Gleiche Rundung wie die Karte: verhindert Subpixel-Farbsaum an unterer/oberer Kante */
  #todosList .todo-swipe-track,
  #closedTodosList .todo-swipe-track {
    border-radius: inherit;
    overflow: hidden;
  }
  /* Wisch-Farben (sichtbar beim Wischen): gleiche Rundung + Clip, sonst Saum an den Kanten */
  #todosList .todo-swipe-actions-layer,
  #closedTodosList .todo-swipe-actions-layer {
    border-radius: inherit;
    overflow: hidden;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
  }
  /* Farbige Wisch-Schicht nur sichtbar wenn wirklich gewischt — kein Durchscheinen im Ruhezustand */
  .todo-item--swipe-revealed .todo-swipe-actions-layer {
    opacity: 1;
    pointer-events: auto;
  }
  /* Kein horizontales „Mitwischen“ der ganzen Seite / kein Gummiband seitlich */
  #main-content {
    overflow-x: hidden;
    overscroll-behavior-x: none;
  }
  .todos-list-scroll-wrap {
    overflow-x: hidden;
    overscroll-behavior-x: contain;
  }
}

/* Desktop: Schnell-Eingabe über der scrollenden Liste (sonst können viele Karten optisch „über“ dem Feld liegen) */
@media (min-width: 1024px) {
  #quickTodoBar {
    z-index: 30;
    isolation: isolate;
  }
}

/* Schnell-Eingabe: Verlauf + leichter Schatten nach oben (wie Ordner-Kanten / Listenende bei Tickets) */
#quickTodoBar::before {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: 100%;
  height: 1.35rem;
  pointer-events: none;
  background: linear-gradient(
    to bottom,
    transparent,
    color-mix(in srgb, var(--page-bg, #f9fafb) 95%, transparent)
  );
  box-shadow: 0 -8px 22px -12px color-mix(in srgb, var(--page-bg, #f9fafb) 18%, rgba(15, 23, 42, 0.07));
}
.dark #quickTodoBar::before {
  background: linear-gradient(
    to bottom,
    transparent,
    color-mix(in srgb, var(--page-bg, #090909) 95%, transparent)
  );
  box-shadow: 0 -8px 22px -12px color-mix(in srgb, var(--page-bg, #090909) 18%, transparent);
}

/* Aufgaben: Skeletons im Dark-Mode an Listenpalette angleichen (wie Tickets) */
.dark #todosList .todo-skeleton-item .dark\:bg-primary-140 {
  background-color: #3a3d42 !important;
}
.dark #todosList .todo-skeleton-item .dark\:bg-primary-120 {
  background-color: #323438 !important;
}

/* Mobil-Aufgabensuche (Nav): wie Lager / Tickets */
.todo-mobile-search-anim {
  display: grid;
  grid-template-rows: 0fr;
  width: 100%;
  min-width: 0;
  transition: grid-template-rows 0.38s cubic-bezier(0.4, 0, 0.2, 1);
}
#todo-mobile-dashboard.todo-mobile-search-panel-open .todo-mobile-search-anim {
  grid-template-rows: 1fr;
}
#todo-mobile-dashboard:not(.todo-mobile-search-panel-open) .todo-mobile-search-anim {
  pointer-events: none;
}
#todo-mobile-dashboard.todo-mobile-search-panel-open .todo-mobile-search-anim__measure {
  overflow: visible;
}
@media (prefers-reduced-motion: reduce) {
  .todo-mobile-search-anim {
    transition-duration: 0.01ms;
  }
}

</style>
  
<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 max-lg:pt-[calc(env(safe-area-inset-top,0px)+3.5rem+1rem)] lg:pt-0 overflow-hidden max-lg:overflow-x-hidden max-lg:overflow-y-visible app-mobile-no-root-overscroll max-lg:min-h-[100dvh] max-lg:flex max-lg:flex-col">
  <main class="mx-4 mt-2 flex flex-col overflow-hidden min-h-0 max-lg:overflow-visible max-lg:min-h-0 max-lg:mt-0 max-lg:mx-0 max-lg:px-4 max-lg:flex-1">
    <nav class="mb-4 flex flex-shrink-0 hidden lg:flex" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
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
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Aufgaben</span>
          </div>
        </li>
      </ol>
    </nav>

        <div class="col-span-full">
          <div>
            <div class="relative">
                <!-- Mobil: Ordner nur im Top-Nav — Zeile wie Tickets komplett ausblenden, sonst leeres pb-4 über der Liste -->
                <div class="hidden lg:flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0 md:gap-4">
                <!-- Desktop: horizontale Ordner-Leiste (mobil: Auswahl im Top-Nav-Panel wie Tickets) -->
                <div class="flex flex-col w-full space-y-3 md:space-y-0 md:flex-row md:items-center md:flex-1 md:min-w-0">
                  <div class="folder-filters-wrapper flex items-center min-w-0 flex-1">
                    <div id="folderFiltersScroll" class="folder-filters-scroll flex items-center overflow-x-auto scrollbar-hide min-w-0 flex-1" style="-webkit-overflow-scrolling: touch;">
                      <div id="folderFilters" class="flex items-center gap-2 flex-nowrap">
                        <!-- Ordner-Buttons werden hier dynamisch eingefügt -->
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex flex-col items-stretch justify-end flex-shrink-0 md:flex-row md:items-center md:gap-2">
                  <button id="createFolderBtn" class="flex items-center justify-center p-2 text-white rounded-lg bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 focus:outline-none dark:bg-primary-420 dark:hover:bg-primary-440 dark:focus:ring-primary-800" aria-label="Ordner erstellen">
                    <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 8H4m8 3.5v5M9.5 14h5M4 6v13a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1h-5.032a1 1 0 0 1-.768-.36l-1.9-2.28a1 1 0 0 0-.768-.36H5a1 1 0 0 0-1 1Z"/>
                    </svg>
                  </button>
                </div>
                </div>
                    <!-- Kontextmenü für Aufgaben (Rechtsklick) -->
                    <div id="todoContextMenu" class="hidden fixed z-50 min-w-[180px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg">
                      <button type="button" data-todo-ctx="favorit" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <span id="todoCtxFavoritText">Als wichtig markieren</span>
                      </button>
                      <button type="button" data-todo-ctx="status" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span id="todoCtxStatusText">Als erledigt markieren</span>
                      </button>
                      <button type="button" data-todo-ctx="open-ticket" id="todoCtxOpenTicket" class="hidden w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Auftrag öffnen
                      </button>
                      <div class="border-t border-gray-200 dark:border-primary-120 my-1"></div>
                      <button type="button" id="todoCtxDueToday" data-todo-ctx="due-today" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Heute fällig
                      </button>
                      <button type="button" id="todoCtxDueTomorrow" data-todo-ctx="due-tomorrow" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Morgen fällig
                      </button>
                      <button type="button" id="todoCtxDueClear" data-todo-ctx="due-clear" class="hidden w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Fälligkeitsdatum entfernen
                      </button>
                      <div class="border-t border-gray-200 dark:border-primary-120 my-1"></div>
                      <div id="todoCtxAssignSection" class="relative">
                        <div id="todoCtxAssignTrigger" class="px-3 py-2 text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 cursor-default">
                          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                          <span>Zuweisen an</span>
                          <svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                        <div id="todoCtxAssignSubmenu" class="hidden absolute left-full top-0 ml-0.5 min-w-[160px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg max-h-[50vh] overflow-y-auto z-10">
                          <!-- Benutzer dynamisch eingefügt -->
                        </div>
                      </div>
                      <div class="border-t border-gray-200 dark:border-primary-120 my-1"></div>
                      <div id="todoCtxFolderSection" class="relative">
                        <div id="todoCtxFolderTrigger" class="px-3 py-2 text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 cursor-default">
                          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                          <span>In Ordner verschieben</span>
                          <svg class="w-3 h-3 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                        <div id="todoCtxFolderSubmenu" class="hidden absolute left-full top-0 ml-0.5 min-w-[140px] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg max-h-[50vh] overflow-y-auto z-10">
                          <!-- Ordner dynamisch eingefügt -->
                        </div>
                      </div>
                      <div class="border-t border-gray-200 dark:border-primary-120 my-1"></div>
                      <button type="button" data-todo-ctx="delete" class="w-full px-3 py-2 text-left text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-1 14H6L5 7h14zM10 11v6m4-6v6M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3M4 7h16"/></svg>
                        Aufgabe löschen
                      </button>
                    </div>
                    <!-- Kontextmenü für Ordner (Rechtsklick) -->
                    <div id="folderContextMenu" class="hidden fixed z-50 min-w-[180px] max-w-[min(20rem,calc(100vw-1rem))] py-1 bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg shadow-lg">
                      <div id="folderCtxPrivateInfo" class="hidden px-3 py-2 text-sm text-gray-700 dark:text-primary-210 border-b border-gray-200 dark:border-primary-120 flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
                        </svg>
                        <span>Privat</span>
                      </div>
                      <div id="folderCtxShareInfo" class="hidden px-3 py-2 text-sm text-gray-700 dark:text-primary-210 border-b border-gray-200 dark:border-primary-120 flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M7.926 10.898 15 7.727m-7.074 5.39L15 16.29M8 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm12 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm0-11a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                        </svg>
                        <span>Geteilt</span>
                      </div>
                      <div id="folderCtxPublicInfo" class="hidden px-3 py-2 text-sm text-gray-700 dark:text-primary-210 border-b border-gray-200 dark:border-primary-120 flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path stroke-linecap="round" stroke-width="2" d="M4.37 7.657c2.063.528 2.396 2.806 3.202 3.87 1.07 1.413 2.075 1.228 3.192 2.644 1.805 2.289 1.312 5.705 1.312 6.705M20 15h-1a4 4 0 0 0-4 4v1M8.587 3.992c0 .822.112 1.886 1.515 2.58 1.402.693 2.918.351 2.918 2.334 0 .276 0 2.008 1.972 2.008 2.026.031 2.026-1.678 2.026-2.008 0-.65.527-.9 1.177-.9H20M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <span>Öffentlich</span>
                      </div>
                      <button type="button" id="folderCtxRename" class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Bearbeiten
                      </button>
                      <button type="button" id="folderCtxDelete" class="w-full px-3 py-2 text-left text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-1 14H6L5 7h14zM10 11v6m4-6v6M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3M4 7h16"/></svg>
                        Löschen
                      </button>
                    </div>

              <!-- Mobil: Suche (Nav-Toggle), gleiches Verhalten wie Lager -->
              <div id="todo-mobile-dashboard" class="lg:hidden sticky top-0 z-[12] w-full min-w-0 pt-0">
                <div id="todo-mobile-search-anim" class="todo-mobile-search-anim w-full min-w-0" aria-hidden="true">
                  <div class="todo-mobile-search-anim__measure min-h-0 w-full min-w-0 overflow-hidden px-0.5 py-0">
                    <div id="todo-mobile-search-inner" class="todo-mobile-search-inner w-full min-w-0 pb-2">
                      <label for="todo-mobile-search" class="sr-only">Aufgaben durchsuchen</label>
                      <div class="relative mt-0 flex w-full min-w-0 items-center rounded-2xl bg-white pl-3 pr-1 shadow-[0_1px_3px_rgba(15,23,42,0.06)] ring-1 ring-inset ring-gray-200/90 transition-[box-shadow,ring-color] focus-within:ring-2 focus-within:ring-primary-500/25 dark:bg-primary-100 dark:ring-primary-120/70 dark:shadow-[0_1px_3px_rgba(0,0,0,0.2)] dark:focus-within:ring-primary-400/30">
                        <span class="pointer-events-none flex h-9 w-9 shrink-0 items-center justify-center text-gray-400 dark:text-primary-300" aria-hidden="true">
                          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="text" id="todo-mobile-search" enterkeyhint="search" inputmode="search" autocomplete="off" class="min-w-0 w-full flex-1 basis-0 border-0 bg-transparent py-2.5 pr-3 text-[0.9375rem] text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-primary-100 dark:placeholder-primary-240" placeholder="Aufgaben suchen …">
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Aufgaben -->
              <div class="todos-list-scroll-wrap overflow-x-auto max-lg:overflow-x-hidden pb-32 lg:pb-40">
                <ul id="todosList" class="max-lg:space-y-3 lg:space-y-1.5" aria-busy="true"></ul>
                <div id="closedTodosSection" class="mt-4 flex flex-col w-full">
                  <div id="toggleClosedBtnWrapper" class="hidden flex justify-end">
                    <button type="button" id="toggleClosedBtn" class="inline-flex items-center gap-1.5 text-xs max-lg:text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 focus:outline-none transition-colors max-lg:min-h-11 max-lg:min-w-0 max-lg:px-2 max-lg:py-2 max-lg:-mr-2 max-lg:rounded-xl touch-manipulation">
                      <svg id="toggleClosedIcon" class="w-3.5 h-3.5 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                      <span id="toggleClosedText">Geschlossene anzeigen</span>
                    </button>
                  </div>
                  <ul id="closedTodosList" class="hidden max-lg:space-y-3 lg:space-y-1.5 mt-3 w-full">
                    <!-- Geschlossene Aufgaben (eingeklappt) -->
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

<!-- Mobile Ordner/Filter-Sheet: unter Top-Nav, gleiches Verhalten wie Tickets -->
<div id="mobileFilterSheet" class="lg:hidden fixed inset-0 z-[68] pointer-events-none" aria-hidden="true">
  <div id="mobileFilterSheetBackdrop" class="fixed left-0 right-0 bottom-0 z-[68] bg-black/[0.05] opacity-0 transition-opacity duration-300 pointer-events-auto cursor-pointer dark:bg-black/22 dark:backdrop-blur-[3px]" style="top: calc(env(safe-area-inset-top, 0px) + 3.5rem); pointer-events: none;"></div>
  <div id="mobileFilterSheetPanel" class="fixed inset-x-0 z-[69] flex w-full flex-col min-h-0 overflow-hidden rounded-b-[1.75rem] border border-t-0 border-gray-200 bg-white/88 backdrop-blur-2xl backdrop-saturate-200 dark:border-0 dark:bg-transparent pointer-events-auto" style="top: calc(env(safe-area-inset-top, 0px) + 3.5rem);" role="dialog" aria-modal="true" aria-label="Aufgaben – Ordner">
    <div id="mobileFilterSheetScroll" class="w-full shrink-0 overflow-y-auto overflow-x-hidden space-y-5 px-4 pt-4 pb-4 sm:px-5 custom-scrollbar">
      <div class="mobile-filter-sheet-row">
        <label for="mobile-sheet-todos-folder-select" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220">Ordner</label>
        <select id="mobile-sheet-todos-folder-select" class="mobile-filter-sheet-field" autocomplete="off">
          <option value="all">Alle Aufgaben</option>
          <option value="0">Ohne Ordner</option>
        </select>
      </div>
      <div class="mobile-filter-sheet-row">
        <span class="mobile-filter-sheet-badge-label text-xs font-semibold uppercase tracking-wide">Neuer Ordner</span>
        <div id="mobile-sheet-create-folder-shell">
          <button type="button" id="mobile-sheet-create-folder-btn" class="mobile-filter-sheet-create-btn w-full rounded-[1.25rem] border-0 text-white bg-primary-700 hover:bg-primary-800 dark:bg-primary-420 dark:hover:bg-primary-440 focus:outline-none focus:ring-2 focus:ring-primary-400/40">
            <svg class="w-5 h-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 8H4m8 3.5v5M9.5 14h5M4 6v13a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1h-5.032a1 1 0 0 1-.768-.36l-1.9-2.28a1 1 0 0 0-.768-.36H5a1 1 0 0 0-1 1Z"/>
            </svg>
            Ordner erstellen
          </button>
          <div id="mobile-sheet-create-folder-form">
            <label for="mobile-sheet-folder-name" class="sr-only">Ordnername</label>
            <input type="text" id="mobile-sheet-folder-name" autocomplete="off" placeholder="Ordnername …" maxlength="120" class="mobile-filter-sheet-field touch-manipulation placeholder:text-gray-500 dark:placeholder:text-primary-250">
            <div role="radiogroup" aria-label="Sichtbarkeit des Ordners">
              <p class="text-xs font-medium text-gray-600 dark:text-primary-220 mb-1.5 px-0.5">Sichtbarkeit</p>
              <div class="mobile-folder-vis-grid">
                <label class="folder-visibility-card flex flex-col items-center gap-1 rounded-xl border-2 cursor-pointer transition-all duration-200 border-gray-200 dark:border-primary-230 bg-white/80 dark:bg-primary-300/20" data-value="public">
                  <input type="radio" name="mobileSheetFolderVisibility" value="public" id="mobile-sheet-folder-vis-public" class="sr-only peer" checked>
                  <svg class="w-4 h-4 shrink-0 text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0h.5a2.5 2.5 0 002.5-2.5V3.935M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <span class="text-sm font-medium text-gray-900 dark:text-primary-200 text-center">Öffentlich</span>
                </label>
                <label class="folder-visibility-card flex flex-col items-center gap-1 rounded-xl border-2 cursor-pointer transition-all duration-200 border-gray-200 dark:border-primary-230 bg-white/80 dark:bg-primary-300/20" data-value="private">
                  <input type="radio" name="mobileSheetFolderVisibility" value="private" id="mobile-sheet-folder-vis-private" class="sr-only peer">
                  <svg class="w-4 h-4 shrink-0 text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                  <span class="text-sm font-medium text-gray-900 dark:text-primary-200 text-center">Privat</span>
                </label>
                <label class="folder-visibility-card flex flex-col items-center gap-1 rounded-xl border-2 cursor-pointer transition-all duration-200 border-gray-200 dark:border-primary-230 bg-white/80 dark:bg-primary-300/20" data-value="invite">
                  <input type="radio" name="mobileSheetFolderVisibility" value="invite" id="mobile-sheet-folder-vis-invite" class="sr-only peer">
                  <svg class="w-4 h-4 shrink-0 text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M7.926 10.898 15 7.727m-7.074 5.39L15 16.29M8 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm12 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm0-11a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/></svg>
                  <span class="text-sm font-medium text-gray-900 dark:text-primary-200 text-center">Teilen</span>
                </label>
              </div>
            </div>
            <div id="mobile-sheet-folder-company-section" class="hidden mt-2">
              <label for="mobile-sheet-folder-company" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-primary-220 mb-1">Firma</label>
              <select id="mobile-sheet-folder-company" class="mobile-filter-sheet-field touch-manipulation text-gray-900 dark:text-primary-200" autocomplete="organization"></select>
              <p class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-primary-220">Optional: Ordner einer Firma zuordnen (nur bei „Öffentlich“).</p>
            </div>
            <div id="mobile-sheet-folder-invite-section" class="hidden">
              <label for="mobile-sheet-folder-member-search" class="text-xs font-medium text-gray-600 dark:text-primary-220">Kollegen</label>
              <input type="text" id="mobile-sheet-folder-member-search" placeholder="Namen suchen…" class="mobile-filter-sheet-field touch-manipulation placeholder:text-gray-500 dark:placeholder:text-primary-250">
              <div id="mobile-sheet-folder-candidates-list" class="rounded-lg border border-gray-200 dark:border-primary-120 divide-y divide-gray-100 dark:divide-primary-200 folder-modal-scroll bg-white dark:bg-primary-300/30"></div>
            </div>
            <button type="button" id="mobile-sheet-save-folder-btn" class="mobile-filter-sheet-create-btn text-white bg-primary-700 hover:bg-primary-800 dark:bg-primary-420 dark:hover:bg-primary-440 focus:outline-none focus:ring-2 focus:ring-primary-400/50">
              <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Ordner speichern
            </button>
            <button type="button" id="mobile-sheet-cancel-folder-btn" class="w-full py-2 text-sm font-medium text-gray-600 dark:text-primary-210 hover:text-gray-900 dark:hover:text-primary-100">
              Abbrechen
            </button>
          </div>
        </div>
      </div>
    </div>
    <div id="mobileFilterSheetHandle" class="flex flex-shrink-0 cursor-grab active:cursor-grabbing touch-none bg-transparent px-4 pt-3 pb-[env(safe-area-inset-bottom,0px)]" aria-label="Zum Schließen nach oben ziehen">
      <div class="flex w-full justify-center" aria-hidden="true">
        <div class="h-1.5 w-11 shrink-0 rounded-full bg-gray-300/90 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] dark:bg-white/30 dark:shadow-[inset_0_-1px_0_rgba(0,0,0,0.25)]"></div>
      </div>
    </div>
  </div>
</div>

  </main>
</div>

  <!-- Außerhalb #main-content: sonst liegen fixed-Layer unter der scrollenden Liste (Stacking) -->
  <button type="button" id="todosQuickComposeBackdrop" class="lg:hidden border-0 bg-transparent p-0 m-0 [-webkit-tap-highlight-color:transparent]" tabindex="-1" aria-label="Eingabe schließen" title=""></button>

  <!-- Mobil: Fällig = natives date-Input pro Zeile (über dem Icon), siehe createTodoItem -->

  <!-- Mobil: Bearbeiter = natives select pro Zeile (wie Fällig), siehe createTodoItem -->

  <!-- Input-Feld für neue Aufgabe (unten am Bildschirm) -->
  <div id="quickTodoBar" class="fixed bottom-0 left-0 right-0 z-2 px-4 pt-3 pb-3 bg-gray-50 dark:bg-primary-50">
    <form id="quickTodoForm" class="w-full">
      <div class="relative">
        <input 
          type="text" 
          id="quickTodoInput" 
          placeholder="Neue Aufgabe hinzufügen…" 
          title="Neue Aufgabe hinzufügen"
          class="w-full pl-4 pr-4 lg:pr-28 py-3 text-base text-gray-900 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 max-lg:rounded-xl rounded-lg shadow-none lg:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white dark:placeholder-gray-400"
          autocomplete="off"
          inputmode="text"
          enterkeyhint="done">
        <div id="quickTodoShortcutHint" class="absolute right-3 top-1/2 -translate-y-1/2 hidden lg:flex items-center gap-1 pointer-events-none" aria-label="Tastenkürzel zum Fokussieren"></div>
      </div>
    </form>
  </div>

<!-- Modal für Todo erstellen/bearbeiten -->
<div id="todoModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 w-full md:inset-0 h-modal md:h-full bg-gray-600 bg-opacity-50">
  <div class="flex items-center justify-center min-h-screen md:min-h-full p-4">
    <div class="relative w-full max-w-2xl h-full md:h-auto">
    <!-- Modal content -->
    <div class="relative p-4 bg-white rounded-lg shadow dark:bg-gray-800 sm:p-5">
      <!-- Modal header -->
      <div class="flex justify-between items-center pb-4 mb-4 rounded-t border-b sm:mb-5 dark:border-gray-600">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modalTitle">
          Neue Aufgabe erstellen
        </h3>
        <button type="button" id="closeTodoModalBtn" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white">
          <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
          <span class="sr-only">Modal schließen</span>
        </button>
      </div>
      <!-- Modal body -->
      <form id="todoForm">
        <input type="hidden" id="todo_id" name="todo_id">
        
        <div class="grid gap-4 mb-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="titel" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
              Titel *
            </label>
            <input type="text" name="titel" id="titel" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Aufgabentitel eingeben" required="">
          </div>
          
          <div class="sm:col-span-2">
            <label for="beschreibung" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
              Beschreibung
            </label>
            <textarea id="beschreibung" name="beschreibung" rows="4" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Beschreibung eingeben"></textarea>
          </div>
          
          <div class="sm:col-span-2">
            <label for="faellig_am" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
              Fällig am
            </label>
            <input type="datetime-local" name="faellig_am" id="faellig_am" autocomplete="off" class="datetime-picker-only bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
          </div>
        </div>
        
        <button type="submit" class="text-white inline-flex items-center bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
          <svg class="mr-1 -ml-1 w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
          </svg>
          Aufgabe speichern
        </button>
      </form>
    </div>
    </div>
  </div>
</div>

<!-- Modal: Ordner anlegen / bearbeiten -->
<div id="folderModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4" aria-labelledby="folderModalTitle" role="dialog" aria-modal="true">
  <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/70 backdrop-blur-sm transition-opacity cursor-pointer" aria-hidden="true" id="folderModalOverlay"></div>
  <div class="fixed inset-0 flex items-center justify-center min-h-full min-w-full p-4 pointer-events-none">
    <div class="pointer-events-auto w-full max-w-md flex flex-col relative z-10">
      <div class="relative bg-white dark:bg-primary-100 rounded-2xl shadow-xl border border-gray-200/80 dark:border-primary-120 overflow-hidden flex flex-col max-h-[calc(100vh-2rem)]">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 px-6 pt-6 pb-4">
          <div class="flex items-center gap-3 min-w-0">
            <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-200 flex items-center justify-center text-primary-600 dark:text-primary-400">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            </span>
            <div class="min-w-0">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200" id="folderModalTitle">Neuer Ordner</h3>
              <p class="text-sm text-gray-500 dark:text-primary-240 mt-0.5">Name und Sichtbarkeit festlegen</p>
            </div>
          </div>
          <button type="button" id="closeFolderModalBtn" class="flex-shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-primary-200 hover:bg-gray-100 dark:hover:bg-primary-140 transition-colors" aria-label="Schließen">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <form id="folderForm" class="flex flex-col flex-1 min-h-0">
          <input type="hidden" id="folder_id" name="folder_id">
          <div class="px-6 pb-5 space-y-5 overflow-y-auto flex-1">
            <!-- Ordnername -->
            <div>
              <input type="text" name="folderName" id="folderName" required
                     class="folder-modal-input w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-primary-320 bg-gray-50 dark:bg-primary-300/50 text-gray-900 dark:text-primary-200 placeholder-gray-400 dark:placeholder-primary-250 focus:ring-2 focus:ring-primary-500/25 focus:border-primary-500 dark:focus:ring-primary-400/30 dark:focus:border-primary-400 transition-colors"
                     placeholder="z. B. Projekt X, Persönlich, …">
            </div>

            <!-- Sichtbarkeit -->
            <div role="radiogroup" aria-label="Sichtbarkeit des Ordners">
              <p class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-3">Sichtbarkeit des Ordners</p>
              <div class="grid grid-cols-3 gap-3">
                <label class="folder-visibility-card flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 border-gray-200 dark:border-primary-230 bg-gray-50/50 dark:bg-primary-300/20 hover:border-primary-300 dark:hover:border-primary-400 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-primary-100" data-value="public">
                  <input type="radio" name="folderVisibility" value="public" id="folderVisibilityPublic" class="sr-only peer">
                  <svg class="w-5 h-5 shrink-0 text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0h.5a2.5 2.5 0 002.5-2.5V3.935M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <span class="text-sm font-medium text-gray-900 dark:text-primary-200 text-center">Öffentlich</span>
                </label>
                <label class="folder-visibility-card flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 border-gray-200 dark:border-primary-230 bg-gray-50/50 dark:bg-primary-300/20 hover:border-primary-300 dark:hover:border-primary-400 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-primary-100" data-value="private">
                  <input type="radio" name="folderVisibility" value="private" id="folderVisibilityPrivate" class="sr-only peer">
                  <svg class="w-5 h-5 shrink-0 text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                  <span class="text-sm font-medium text-gray-900 dark:text-primary-200 text-center">Privat</span>
                </label>
                <label class="folder-visibility-card flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 border-gray-200 dark:border-primary-230 bg-gray-50/50 dark:bg-primary-300/20 hover:border-primary-300 dark:hover:border-primary-400 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-primary-100" data-value="invite">
                  <input type="radio" name="folderVisibility" value="invite" id="folderVisibilityInvite" class="sr-only peer">
                  <svg class="w-5 h-5 shrink-0 text-gray-500 dark:text-primary-240 peer-checked:text-primary-600 dark:peer-checked:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M7.926 10.898 15 7.727m-7.074 5.39L15 16.29M8 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm12 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm0-11a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/></svg>
                  <span class="text-sm font-medium text-gray-900 dark:text-primary-200 text-center">Teilen</span>
                </label>
              </div>
            </div>

            <div id="folderCompanySection" class="hidden">
              <label for="folderCompanySelect" class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1.5">Firma</label>
              <select id="folderCompanySelect" class="folder-modal-input w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-primary-320 bg-gray-50 dark:bg-primary-300/50 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-500/25 focus:border-primary-500 dark:focus:ring-primary-400/30 dark:focus:border-primary-400 transition-colors">
              </select>
              <p class="mt-1.5 text-xs text-gray-500 dark:text-primary-220">Ohne Zuordnung: Ordner ohne Nav-Firmenfilter sichtbar. Mit Firma: erscheint vor allem, wenn diese Firma in der Navigation gewählt ist.</p>
            </div>

            <!-- Kollegen (nur bei Teilen) -->
            <div id="folderInviteSection" class="hidden">
              <label class="block text-sm font-medium text-gray-700 dark:text-primary-210 mb-1.5">Kollegen hinzufügen</label>
              <input type="text" id="folderMemberSearch" placeholder="Namen suchen…"
                     class="folder-modal-input w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-primary-320 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 placeholder-gray-400 dark:placeholder-primary-250 focus:ring-2 focus:ring-primary-500/25 focus:border-primary-500 mb-2 transition-colors">
              <div id="folderCandidatesList" class="min-h-0 max-h-[min(36vh,14rem)] overflow-y-auto overflow-x-hidden rounded-lg border border-gray-200 dark:border-primary-120 divide-y divide-gray-100 dark:divide-primary-200 folder-modal-scroll bg-white dark:bg-primary-300/30"></div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex-shrink-0 px-6 py-4 border-t border-gray-100 dark:border-primary-230 rounded-b-2xl">
            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary-700 hover:bg-primary-800 dark:bg-primary-420 dark:hover:bg-primary-440 text-white font-medium text-sm py-3 px-4 focus:ring-2 focus:ring-primary-300 focus:ring-offset-2 dark:focus:ring-primary-800 dark:focus:ring-offset-primary-100 transition-colors">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              <span>Ordner speichern</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<style>
.folder-modal-input:focus { outline: none; }
.folder-modal-scroll { scrollbar-width: thin; scrollbar-color: rgba(148, 163, 184, 0.5) transparent; }
.dark .folder-modal-scroll { scrollbar-color: rgba(100, 116, 139, 0.5) transparent; }
.folder-modal-scroll::-webkit-scrollbar { width: 6px; }
.folder-modal-scroll::-webkit-scrollbar-track { background: transparent; }
.folder-modal-scroll::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 3px; }
.dark .folder-modal-scroll::-webkit-scrollbar-thumb { background: rgba(100, 116, 139, 0.5); }
.folder-candidate-row { cursor: pointer; }
.folder-candidate-row.selected { background-color: rgba(79, 70, 229, 0.12); }
.dark .folder-candidate-row.selected { background-color: rgba(79, 70, 229, 0.2); }
.folder-visibility-card:has(input:checked) { border-color: rgb(79 70 229); background-color: rgba(79, 70, 229, 0.08); box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.25); }
.dark .folder-visibility-card:has(input:checked) { border-color: rgb(129 140 248); background-color: rgba(79, 70, 229, 0.15); box-shadow: 0 0 0 1px rgba(129, 140, 248, 0.3); }

/* Aktive Ordner-Buttons (Hauptfarbe #4f46e5) */
html:not(.dark) #folderFilters button.folder-btn-active {
    background-color: rgba(79, 70, 229, 0.12) !important;
    border-color: #4f46e5 !important;
    color: #1e293b !important;
    font-weight: 700;
}

.dark #folderFilters button.folder-btn-active {
    background-color: rgba(79, 70, 229, 0.32) !important;
    border-color: #818cf8 !important;
    color: rgb(224 231 255) !important;
    font-weight: 700;
}

/* Inaktive Ordner-Buttons im Dark-Mode: gleiche Farben wie Filter-Icons */
.dark #folderFilters button:not(.folder-btn-active) {
    background-color: rgb(16 16 17) !important;
    border-color: rgb(27 27 28) !important;
}
.dark #folderFilters button:not(.folder-btn-active):hover {
    background-color: rgb(16 16 17) !important;
    border-color: rgb(75 85 99) !important;
}

/* Zähler-Pill neben dem Ordnernamen */
#folderFilters button .folder-filter-label {
    min-width: 0;
}
#folderFilters button .folder-filter-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.25rem;
    padding: 0.125rem 0.4rem;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    line-height: 1.15;
    border-radius: 9999px;
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
    background-color: rgba(148, 163, 184, 0.35);
    color: rgb(51 65 85);
}
.dark #folderFilters button .folder-filter-count {
    background-color: rgba(255, 255, 255, 0.08);
    color: rgb(203 213 225);
}
html:not(.dark) #folderFilters button.folder-btn-active .folder-filter-count {
    background-color: rgba(255, 255, 255, 0.95);
    color: #4338ca;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}
.dark #folderFilters button.folder-btn-active .folder-filter-count {
    background-color: rgba(255, 255, 255, 0.14);
    color: rgb(199 210 254);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
}

/* Geschlossen: 100% Breite + right-3 (0.75rem), sonst bleibt ein Streifen sichtbar */
#todoDrawer.todo-drawer-hidden {
    transform: translateX(calc(100% + 0.75rem));
}
/* Mobil: Drawer & Backdrop über der Fußnav (z-60); Top-Nav mit Zurück + Titel-Input + Erledigt-Checkbox */
@media (max-width: 1023px) {
  /* Klare Trennung: sonst kann ein transformierter Drawer in WebKit kurz über #main-nav zeichnen */
  body.todos-drawer-detail-open #drawerBackdrop:not(.hidden) {
    z-index: 60 !important;
  }
  body.todos-drawer-detail-open #todoDrawer:not(.todo-drawer-hidden) {
    z-index: 61 !important;
    top: calc(env(safe-area-inset-top, 0px) + 3.5rem);
    left: 0;
    right: 0;
    width: 100% !important;
    max-width: none !important;
    bottom: 0;
    transform-origin: left top;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
    /* Wie #main-content / Filter-Sheet: große Rundung nur unten (Index-Look) */
    border-radius: 0;
    border-bottom-left-radius: 1.75rem;
    border-bottom-right-radius: 1.75rem;
    border-left: none;
    border-right: none;
    border-bottom: none;
  }
  /* Beim Schließen bleibt das Sheet kurz vollflächig (sonst springt es auf top-3/right-3 und die Slide-Animation bricht weg) */
  body.todos-drawer-detail-open #todoDrawer.todo-drawer-hidden {
    z-index: 61 !important;
    top: calc(env(safe-area-inset-top, 0px) + 3.5rem);
    left: 0;
    right: 0;
    width: 100% !important;
    max-width: none !important;
    bottom: 0;
    transform-origin: left top;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
    border-radius: 0;
    border-bottom-left-radius: 1.75rem;
    border-bottom-right-radius: 1.75rem;
    border-left: none;
    border-right: none;
    border-bottom: none;
  }
  /* 120: über Fußnav/Sheets (≤62) mit Abstand, damit WebKit beim Transform-Snap nicht kurz darüber malt */
  body.todos-drawer-detail-open #main-nav {
    z-index: 120 !important;
    background-color: #000000 !important;
    -webkit-transform: translate3d(0, 0, 0);
    transform: translate3d(0, 0, 0);
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
  }
  body.todo-drawer-swipe-active.todos-drawer-detail-open #main-nav {
    z-index: 120 !important;
    background-color: #000000 !important;
  }
  /* Eine Zeile: Titel bis an die Checkbox, rechter Block (Plus/…) nicht mehr 50 % breit */
  body.todos-drawer-detail-open .nav-mobile-bar-left {
    flex: 1 1 0% !important;
    min-width: 0;
  }
  body.todos-drawer-detail-open .nav-mobile-bar-right {
    flex: 0 0 auto !important;
    width: auto;
  }
  body.todos-drawer-detail-open #navMobileTodosListBar {
    display: none !important;
  }
  body.todos-drawer-detail-open #navMobileTodosDrawerBar {
    display: flex !important;
  }
  body.todos-drawer-detail-open #navMobileTodosQuickOpenLabel,
  body.todos-drawer-detail-open #navMobileTodosQuickCloseBtn {
    display: none !important;
  }
  body.todos-drawer-detail-open #navMobileTodosDrawerDoneWrap {
    display: flex !important;
  }
  body.todos-drawer-detail-open #navMobileFilterToggleBtn {
    display: none !important;
  }
  body.todos-drawer-detail-open #navMobileTodosSearchBtn {
    display: none !important;
  }
  body.todos-drawer-detail-open [data-nav-mobile-filter-title] {
    pointer-events: none;
    cursor: default;
  }
  body.todos-drawer-detail-open #navMobileTodoDrawerDoneCb {
    border-color: rgba(255, 255, 255, 0.38);
  }
  body.todos-drawer-detail-open #todoDrawer #drawerFooter {
    padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
  }
}
</style>

<!-- Drawer für Todo-Details -->
<div id="todoDrawer" class="todo-drawer-hidden fixed top-3 right-3 bottom-3 z-40 w-[min(28rem,calc(100vw-0.75rem))] flex flex-col transition-transform duration-500 ease-in-out overflow-hidden rounded-2xl border border-gray-200/80 dark:border-primary-230 bg-white dark:bg-primary-50 shadow-2xl shadow-gray-900/10 dark:shadow-black/40" tabindex="-1" aria-hidden="true">
    <div id="drawerContent" class="relative z-10 flex-1 min-h-0 overflow-y-auto overflow-x-hidden overscroll-contain p-4 space-y-6 touch-pan-y">
        <!-- Loading -->
        <div class="text-center py-8">
            <div role="status">
                <svg aria-hidden="true" class="w-8 h-8 text-gray-400 dark:text-primary-240 animate-spin fill-primary-600 dark:fill-primary-400 mx-auto" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                    <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                </svg>
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>
    
    <div id="drawerFooter" class="flex-shrink-0 border-t border-gray-200 dark:border-primary-230 bg-white dark:bg-primary-50 px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 flex-1">
                <button type="button" id="drawerCloseBtn" class="text-gray-400 hover:text-primary-600 focus:ring-4 focus:outline-none focus:ring-primary-300 p-1.5 rounded-lg dark:hover:text-primary-400 dark:focus:ring-primary-800" title="Schließen">
                    <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <span id="drawerAutosaveIndicator" class="text-xs text-red-600 dark:text-red-400 hidden max-w-[10rem] sm:max-w-xs truncate shrink-0" aria-live="assertive" role="alert"></span>
                <div class="flex-1 text-center" id="drawerCreatedDate">
                    <!-- Erstellungsdatum wird hier eingefügt -->
                </div>
            </div>
            <button type="button" id="drawerDeleteBtn" class="text-red-600 hover:text-red-700 focus:ring-4 focus:outline-none focus:ring-red-300 p-1.5 rounded-lg dark:text-red-400 dark:hover:text-red-300" title="Löschen">
                <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
const todosApiUrl = '<?php echo BASE_URL; ?>todos/api/todos.php';
const todosOpenCountUrl = '<?php echo BASE_URL; ?>todos/api/open-count.php';
const foldersApiUrl = '<?php echo BASE_URL; ?>todos/api/folders.php';
const attachmentsApiUrl = '<?php echo BASE_URL; ?>todos/api/attachments.php';
const companiesApiUrl = '<?php echo BASE_URL; ?>companies/api/companies.php';

/**
 * fetch + JSON parsen. Leerer oder ungültiger Body (z. B. PHP-Fatal, 502 ohne Body) löst keinen SyntaxError
 * bei response.json() aus, sondern einen nachvollziehbaren Fehler mit HTTP-Status.
 */
function todosFetchJson(url, init) {
    return fetch(url, init).then(function(response) {
        return response.text().then(function(text) {
            var raw = (text || '').trim();
            var data = null;
            if (raw) {
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    var bad = new Error('Ungültige Server-Antwort (kein JSON)');
                    bad.httpStatus = response.status;
                    bad.bodyPreview = raw.slice(0, 160);
                    throw bad;
                }
            }
            if (!response.ok) {
                var msg = (data && (data.error || data.message)) ? String(data.error || data.message) : ('HTTP ' + response.status);
                var err = new Error(msg);
                err.httpStatus = response.status;
                err.data = data;
                throw err;
            }
            // Erfolg, aber kein Body: 204/304 sind normal; sonst leeres 2xx (Gateway/PHP) – kein Throw, Callers werten success aus
            if (data === null) {
                if (response.status === 204 || response.status === 304) {
                    return { success: true };
                }
                return { success: false, error: 'Leere Antwort vom Server' };
            }
            return data;
        });
    });
}

let currentFolderId = 0; // 0 = Alle Aufgaben ohne Ordner, null = alle
let todos = [];
let closedTodos = [];
let folders = [];
let folderModalCompanies = [];
let folderModalCompaniesPromise = null;
let closedTodosExpanded = false;
let allUsers = [];
let highlightTodoId = null; /* ID der aus Suche geöffneten Aufgabe – Zeile hervorheben */ // Alle Benutzer für die Zuweisung

const TODOS_FILTER_STORAGE_KEY = 'todosIndexFilters';
var todoMobileSearchOpenedAt = 0;
var TODO_MOBILE_SEARCH_AUTOCLOSE_GUARD_MS = 450;
var todoIgnoreNavSearchClickUntil = 0;
var todoMobileSearchFocusTimer = 0;

/** Mobil-Aufgabensuche (Nav): wie Lager (inv-*). */
function todoMobileSearchIsEmpty() {
    var m = document.getElementById('todo-mobile-search');
    var mv = m ? (m.value || '').trim() : '';
    return !mv;
}
function todoCloseMobileSearchIfEmpty() {
    var dash = document.getElementById('todo-mobile-dashboard');
    if (!dash || !dash.classList.contains('todo-mobile-search-panel-open')) return;
    if (todoMobileSearchOpenedAt && (Date.now() - todoMobileSearchOpenedAt) < TODO_MOBILE_SEARCH_AUTOCLOSE_GUARD_MS) return;
    if (!todoMobileSearchIsEmpty()) return;
    todoSetMobileSearchPanelOpen(false, false);
}
function todoSetMobileSearchPanelOpen(open, focusInput) {
    var dash = document.getElementById('todo-mobile-dashboard');
    var anim = document.getElementById('todo-mobile-search-anim');
    var btn = document.getElementById('navMobileTodosSearchBtn');
    if (!dash) return;
    if (typeof focusInput === 'undefined') focusInput = !!open;
    if (open) {
        todoMobileSearchOpenedAt = Date.now();
        dash.classList.add('todo-mobile-search-panel-open');
        if (anim) anim.setAttribute('aria-hidden', 'false');
        if (btn) btn.setAttribute('aria-expanded', 'true');
        if (focusInput) {
            var mInp = document.getElementById('todo-mobile-search');
            if (mInp) {
                if (todoMobileSearchFocusTimer) window.clearTimeout(todoMobileSearchFocusTimer);
                try {
                    void dash.offsetHeight;
                    void mInp.offsetHeight;
                    try { mInp.focus({ preventScroll: true }); } catch (eFocusNow) { try { mInp.focus(); } catch (e2Now) {} }
                    todoMobileSearchFocusTimer = window.setTimeout(function() {
                        todoMobileSearchFocusTimer = 0;
                        if (typeof mInp.setSelectionRange === 'function') {
                            var len = (mInp.value && mInp.value.length) ? mInp.value.length : 0;
                            try { mInp.setSelectionRange(len, len); } catch (eSel) {}
                        }
                    }, 120);
                } catch (e) {
                    try { mInp.focus(); } catch (e2) {}
                }
            }
        }
    } else {
        todoMobileSearchOpenedAt = 0;
        if (todoMobileSearchFocusTimer) {
            window.clearTimeout(todoMobileSearchFocusTimer);
            todoMobileSearchFocusTimer = 0;
        }
        dash.classList.remove('todo-mobile-search-panel-open');
        if (anim) anim.setAttribute('aria-hidden', 'true');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        var mBlur = document.getElementById('todo-mobile-search');
        if (mBlur && document.activeElement === mBlur) {
            try { mBlur.blur(); } catch (eB) {}
        }
    }
}
function todoEnsureMobileSearchPanelIfQuery() {
    var dash = document.getElementById('todo-mobile-dashboard');
    var mob = document.getElementById('todo-mobile-search');
    var btn = document.getElementById('navMobileTodosSearchBtn');
    if (!dash || !btn) return;
    if (typeof window.matchMedia === 'function' && !window.matchMedia('(max-width: 1023px)').matches) return;
    if (mob && (mob.value || '').trim()) {
        todoSetMobileSearchPanelOpen(true, false);
    }
}

function getTodosFiltersState() {
    var mob = document.getElementById('todo-mobile-search');
    return {
        folderId: currentFolderId,
        closedExpanded: closedTodosExpanded,
        listSearch: mob ? (mob.value || '') : ''
    };
}

function saveTodosFiltersState() {
    try {
        const state = getTodosFiltersState();
        localStorage.setItem(TODOS_FILTER_STORAGE_KEY, JSON.stringify(state));
    } catch (e) {
        console.error('Fehler beim Speichern der Aufgaben-Filter', e);
    }
}

function restoreTodosFiltersState() {
    try {
        const raw = localStorage.getItem(TODOS_FILTER_STORAGE_KEY);
        if (!raw) return;
        const state = JSON.parse(raw);
        if (state.folderId !== undefined) {
            currentFolderId = state.folderId;
        }
        if (state.closedExpanded === true) {
            closedTodosExpanded = true;
        }
        if (state.listSearch !== undefined) {
            var mob = document.getElementById('todo-mobile-search');
            var v = state.listSearch || '';
            if (mob) mob.value = v;
        }
    } catch (e) {
        console.error('Fehler beim Wiederherstellen der Aufgaben-Filter', e);
    }
}

function populateTodosMobileFolderSelect() {
    const sel = document.getElementById('mobile-sheet-todos-folder-select');
    if (!sel) return;
    sel.innerHTML = '';
    function addOpt(val, label) {
        const o = document.createElement('option');
        o.value = val;
        o.textContent = label;
        sel.appendChild(o);
    }
    addOpt('all', 'Alle Aufgaben');
    addOpt('0', 'Ohne Ordner');
    folders.forEach(function(f) {
        var n = f.open_todo_count != null ? f.open_todo_count : 0;
        addOpt(String(f.id), (f.name != null ? String(f.name) : '') + ' \u00b7 ' + n);
    });
    syncTodosMobileFolderSelect();
}

function syncTodosMobileFolderSelect() {
    const sel = document.getElementById('mobile-sheet-todos-folder-select');
    if (!sel) return;
    const v = (currentFolderId === null || currentFolderId === undefined) ? 'all' : String(currentFolderId);
    sel.value = v;
    if (sel.value !== v) {
        sel.value = 'all';
        if (v !== 'all') {
            currentFolderId = null;
        }
    }
    updateTodosNavMobileTitle();
}

function updateTodosNavMobileTitle() {
    if (document.body.classList.contains('todos-drawer-detail-open')) return;
    const el = document.getElementById('navMobileCompactTitle');
    if (!el) return;
    let text;
    if (currentFolderId === null || currentFolderId === undefined) {
        text = 'Alle Aufgaben';
    } else if (currentFolderId === 0) {
        text = 'Ohne Ordner';
    } else {
        const f = folders.find(function(x) { return Number(x.id) === Number(currentFolderId); });
        text = f ? f.name : 'Ordner';
    }
    el.textContent = text;
}

/** Mobil: verstecktes drawer_titel aus Nav-Input füllen (Titel liegt nur in der Top-Nav) */
function syncTodosDrawerHiddenTitleFromNav() {
    if (!document.body.classList.contains('todos-drawer-detail-open')) return;
    const navInp = document.getElementById('navMobileTodoDrawerTitleInput');
    const hidden = document.getElementById('drawer_titel');
    if (navInp && hidden && window.matchMedia('(max-width: 1023px)').matches) {
        hidden.value = navInp.value;
    }
}

function applyTodosDrawerNavMode(todo) {
    if (!window.matchMedia('(max-width: 1023px)').matches) return;
    document.body.classList.add('todos-drawer-detail-open');
    const navInp = document.getElementById('navMobileTodoDrawerTitleInput');
    if (navInp) {
        var t = todo && todo.titel != null ? String(todo.titel) : '';
        navInp.value = t.trim();
    }
    var doneCb = document.getElementById('navMobileTodoDrawerDoneCb');
    if (doneCb && todo) {
        doneCb.checked = (todo.status === 'erledigt');
        doneCb.setAttribute('data-todo-id', String(todo.id));
    }
}

function clearTodosDrawerNavMode() {
    document.body.classList.remove('todos-drawer-detail-open');
    var navInp = document.getElementById('navMobileTodoDrawerTitleInput');
    if (navInp) navInp.value = '';
    var doneCb = document.getElementById('navMobileTodoDrawerDoneCb');
    if (doneCb) {
        doneCb.checked = false;
        doneCb.removeAttribute('data-todo-id');
    }
    updateTodosNavMobileTitle();
}

/** Mobil: Zurück-Button, Erledigt-Checkbox in der Top-Nav (nur Aufgaben-Seite) */
function initTodosDrawerNavChrome() {
    var back = document.getElementById('navMobileTodosDrawerBackBtn');
    if (back) {
        back.addEventListener('click', function(e) {
            e.preventDefault();
            closeTodoDrawer();
        });
    }
    var cb = document.getElementById('navMobileTodoDrawerDoneCb');
    if (cb) {
        cb.addEventListener('change', function() {
            if (!document.body.classList.contains('todos-drawer-detail-open')) return;
            var tid = currentDrawerTodoId;
            if (tid != null && tid !== '') {
                toggleTodoStatus(parseInt(String(tid), 10), cb.checked);
            }
        });
    }
}

/**
 * Mobil: Wisch-zum-Schließen ab der linken Drawer-Hälfte (Breite per Koordinate, kein Overlay).
 * So bleibt die große Wischfläche, ohne dass Links darunter blockiert werden. Nach rechts wie Zurück.
 */
function initTodoDrawerSwipeToClose() {
    var drawer = document.getElementById('todoDrawer');
    if (!drawer) return;
    /** Anteil der Drawer-Breite ab links, ab dem die Geste zählt (0–1). */
    var SWIPE_ZONE_X = 0.5;
    var CLOSE_DX = 64;
    var CLOSE_RATIO = 0.22;
    var snapEase = 'cubic-bezier(0.22, 1, 0.36, 1)';
    var state = null;

    function isMobileLayout() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function resetDrawerTransform() {
        if (drawer.classList.contains('todo-drawer-hidden')) return;
        drawer.style.transition = '';
        drawer.style.transform = 'translate3d(0,0,0)';
    }

    function touchStartsInSwipeZone(clientX) {
        var r = drawer.getBoundingClientRect();
        return clientX <= r.left + r.width * SWIPE_ZONE_X;
    }

    drawer.addEventListener('touchstart', function(e) {
        if (!isMobileLayout()) return;
        if (drawer.classList.contains('todo-drawer-hidden')) return;
        if (e.touches.length !== 1) return;
        var t = e.touches[0];
        if (!touchStartsInSwipeZone(t.clientX)) return;
        state = { x0: t.clientX, y0: t.clientY, active: true, dragging: false };
    }, { passive: true, capture: true });

    drawer.addEventListener('touchmove', function(e) {
        if (!state || !state.active) return;
        if (drawer.classList.contains('todo-drawer-hidden')) {
            state = null;
            return;
        }
        var t = e.touches[0];
        var dx = t.clientX - state.x0;
        var dy = t.clientY - state.y0;
        if (!state.dragging) {
            if (Math.abs(dy) > Math.abs(dx) + 12 && Math.abs(dy) > 18) {
                state = null;
                return;
            }
            if (dx < 6) return;
        }
        state.dragging = true;
        document.body.classList.add('todo-drawer-swipe-active');
        e.preventDefault();
        var w = drawer.getBoundingClientRect().width;
        var tx = Math.min(Math.max(0, dx), w);
        drawer.style.transition = 'none';
        drawer.style.transform = 'translate3d(' + tx + 'px,0,0)';
    }, { passive: false, capture: true });

    function endSwipe(e) {
        var hadSwipeChrome = document.body.classList.contains('todo-drawer-swipe-active');
        if (!state || !state.active) {
            state = null;
            if (hadSwipeChrome) document.body.classList.remove('todo-drawer-swipe-active');
            return;
        }
        var t = e.changedTouches[0];
        var dx = t.clientX - state.x0;
        var w = drawer.getBoundingClientRect().width;
        var threshold = Math.max(CLOSE_DX, w * CLOSE_RATIO);
        var dragging = state.dragging;
        state = null;
        if (drawer.classList.contains('todo-drawer-hidden')) {
            if (hadSwipeChrome) document.body.classList.remove('todo-drawer-swipe-active');
            return;
        }
        /* Kein horizontaler Drag: keinen Snap 0→0 — sonst ein Frame/Layer-Sprung unter WebKit/iOS */
        if (!dragging) {
            if (hadSwipeChrome) document.body.classList.remove('todo-drawer-swipe-active');
            resetDrawerTransform();
            return;
        }
        if (dx >= threshold) {
            if (hadSwipeChrome) document.body.classList.remove('todo-drawer-swipe-active');
            drawer.style.transition = '';
            drawer.style.transform = '';
            closeTodoDrawer();
            return;
        }
        /* Snap zurück: swipe-Klasse erst nach der Transition entfernen, damit nicht ein Repaint mit halbem Transform über der Nav liegt */
        drawer.style.transition = 'transform 0.28s ' + snapEase;
        drawer.style.transform = 'translate3d(0,0,0)';
        window.setTimeout(function() {
            document.body.classList.remove('todo-drawer-swipe-active');
            if (!drawer.classList.contains('todo-drawer-hidden')) {
                drawer.style.transition = '';
            }
        }, 340);
    }

    drawer.addEventListener('touchend', endSwipe, { passive: true, capture: true });
    drawer.addEventListener('touchcancel', function() {
        document.body.classList.remove('todo-drawer-swipe-active');
        if (!state || !state.active) {
            state = null;
            return;
        }
        state = null;
        resetDrawerTransform();
    }, { passive: true, capture: true });
}

function collapseMobileSheetFolderCreate() {
    const shell = document.getElementById('mobile-sheet-create-folder-shell');
    if (shell) shell.classList.remove('mobile-sheet-create-folder-expanded');
    const name = document.getElementById('mobile-sheet-folder-name');
    if (name) name.value = '';
    const pub = document.getElementById('mobile-sheet-folder-vis-public');
    const priv = document.getElementById('mobile-sheet-folder-vis-private');
    const inv = document.getElementById('mobile-sheet-folder-vis-invite');
    if (pub) pub.checked = true;
    if (priv) priv.checked = false;
    if (inv) inv.checked = false;
    mobileSheetFolderMemberIds = [];
    const inviteSec = document.getElementById('mobile-sheet-folder-invite-section');
    if (inviteSec) inviteSec.classList.add('hidden');
    const mSearch = document.getElementById('mobile-sheet-folder-member-search');
    if (mSearch) mSearch.value = '';
    const mobList = document.getElementById('mobile-sheet-folder-candidates-list');
    if (mobList) mobList.innerHTML = '';
    const mobCo = document.getElementById('mobile-sheet-folder-company');
    if (mobCo) mobCo.value = '';
    updateFolderCompanySectionsVisibility();
}

function expandMobileSheetFolderCreate() {
    if (!window.matchMedia('(max-width: 1023px)').matches) return;
    collapseMobileSheetFolderCreate();
    if (typeof window.__todosOpenMobileFilterSheet === 'function') {
        window.__todosOpenMobileFilterSheet();
    }
    const shell = document.getElementById('mobile-sheet-create-folder-shell');
    if (shell) shell.classList.add('mobile-sheet-create-folder-expanded');
    const inp = document.getElementById('mobile-sheet-folder-name');
    ensureFolderCompaniesForModal().then(function() {
        var nc = getNavSelectedCompanyId();
        setFolderCompanySelectValue('mobile', nc);
    }).finally(function() {
        updateFolderCompanySectionsVisibility();
    });
    if (inp) {
        /* Reflow erzwingen, damit das Feld aus display:none heraus fokussierbar ist; Fokus synchron
           im Klick-Kontext, sonst öffnet iOS Safari die Tastatur oft nicht (rAF = zu spät). */
        if (shell) void shell.offsetHeight;
        inp.focus({ preventScroll: true });
    }
}

/** Date/Time- und Date-Picker: Nur Picker öffnen (keine Tastatureingabe), bei Klick/Fokus Picker anzeigen. */
function initDatetimePickerOnly(container) {
    if (!container) return;
    const root = container instanceof Document ? document : container;
    root.querySelectorAll('.datetime-picker-only, input[type="datetime-local"], input[type="date"]').forEach(function(input) {
        if (input.dataset.pickerOnlyInit) return;
        input.dataset.pickerOnlyInit = '1';
        input.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab' && e.key !== 'Escape') e.preventDefault();
        });
        input.addEventListener('focus', function() {
            if (typeof window.openNativePicker === 'function') window.openNativePicker(this);
        });
        input.addEventListener('click', function() {
            if (typeof window.openNativePicker === 'function') window.openNativePicker(this);
        });
    });
}

/** Mobil: Plus in Top-Nav — Footer-Nav aus, Schnell-Eingabe ein (Viewport unter 1024px). */
window.__todosSetMobileQuickComposeOpen = function(open, syncFocus) {
    if (window.matchMedia('(min-width: 1024px)').matches) return;
    open = !!open;
    var bar = document.getElementById('quickTodoBar');
    if (open) {
        document.body.classList.add('todos-mobile-quick-compose');
        /* Sofort sichtbar layouten (auch für Label→Input-Fokus ohne programmatisches focus) */
        if (bar) {
            bar.style.setProperty('transition', 'none');
        }
    } else {
        document.body.classList.remove('todos-mobile-quick-compose');
        if (bar) {
            bar.style.removeProperty('transition');
        }
    }
    var openLbl = document.getElementById('navMobileTodosQuickOpenLabel');
    var closeBtn = document.getElementById('navMobileTodosQuickCloseBtn');
    var input = document.getElementById('quickTodoInput');
    if (openLbl) {
        openLbl.setAttribute('aria-hidden', open ? 'true' : 'false');
        openLbl.setAttribute('aria-expanded', 'false');
    }
    if (closeBtn) {
        closeBtn.setAttribute('aria-hidden', open ? 'false' : 'true');
        closeBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (!open && input && document.activeElement === input) {
        input.blur();
    }
    if (open && bar) {
        void bar.offsetHeight;
        bar.style.removeProperty('transition');
        void bar.offsetHeight;
    }
    if (open && input) {
        input.readOnly = false;
        input.removeAttribute('readonly');
    }
    /* Nur Fallback (z. B. ohne Label); sonst übernimmt das <label for> die Tastatur-sichere Fokussierung */
    if (open && input && syncFocus) {
        void input.offsetHeight;
        input.focus({ preventScroll: false });
        try {
            if (typeof input.setSelectionRange === 'function') {
                var n = input.value.length;
                input.setSelectionRange(n, n);
            }
        } catch (e2) {}
    }
    /* Plus/Minus tauschen erst nach dem aktuellen Klick-Task — sonst ist das Label schon unsichtbar, bevor WebKit den Fokus setzt */
    if (open) {
        queueMicrotask(function() {
            var ol = document.getElementById('navMobileTodosQuickOpenLabel');
            var cb = document.getElementById('navMobileTodosQuickCloseBtn');
            if (ol) ol.style.display = 'none';
            if (cb) cb.style.display = 'inline-flex';
        });
    } else {
        if (openLbl) openLbl.style.display = '';
        if (closeBtn) closeBtn.style.display = '';
    }
};

/**
 * Ersten Aufgaben-Request erst nach dem ersten Paint starten (wie Tickets): Nav/Skeleton sichtbar,
 * fetch blockiert das Rendering nicht am Ende eines langen DOMContentLoaded.
 */
function scheduleInitialTodosLoad() {
    function run() {
        loadTodos().then(onInitialTodosLoaded);
    }
    if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(function() {
            window.requestAnimationFrame(run);
        });
    } else {
        setTimeout(run, 0);
    }
}

function onInitialTodosLoaded() {
    // Prüfen ob ?id= in URL (z. B. von globaler Suche)
    const urlParams = new URLSearchParams(window.location.search);
    const todoIdParam = urlParams.get('id');
    if (todoIdParam) {
        const todoId = parseInt(todoIdParam, 10);
        if (todoId > 0) {
            highlightTodoId = todoId;
            const todo = todos.find(t => t.id === todoId) || closedTodos.find(t => t.id === todoId);
            if (todo) {
                displayTodos(todos);
                openTodoDrawer(todoId);
            } else {
                // Aufgabe in anderer Liste oder erledigt – Liste wechseln und dann öffnen
                let url = todosApiUrl + '?id=' + todoId;
                const companyId = (function() {
                    try {
                        const saved = localStorage.getItem('selectedUserOption');
                        if (saved) {
                            const d = JSON.parse(saved);
                            if (d.id && d.id !== '0') return parseInt(d.id, 10);
                        }
                    } catch (_) {}
                    return null;
                })();
                if (companyId) url += '&company_id=' + companyId;
                todosFetchJson(url).then(function(data) {
                    if (data.success && data.todos && data.todos.length > 0) {
                        const t = data.todos[0];
                        const taskFolderId = t.folder_id != null && t.folder_id !== '' ? parseInt(t.folder_id, 10) : 0;
                        currentFolderId = taskFolderId;
                        saveTodosFiltersState();
                        loadFolders();
                        updateFolderButtons();
                        const done = function() {
                            highlightTodoId = todoId;
                            displayTodos(todos);
                            if (t.status === 'erledigt' && closedTodos.length > 0) {
                                displayClosedTodos(closedTodos);
                                updateToggleClosedVisibility();
                            }
                            openTodoDrawer(todoId);
                            urlParams.delete('id');
                            const newSearch = urlParams.toString();
                            const newUrl = window.location.pathname + (newSearch ? '?' + newSearch : '') + window.location.hash;
                            window.history.replaceState(null, '', newUrl);
                        };
                        if (t.status === 'erledigt') {
                            Promise.all([loadTodos(), loadClosedTodos()]).then(done);
                        } else {
                            loadTodos().then(done);
                        }
                    }
                }).catch(function() {});
                return;
            }
            // id aus URL entfernen (ohne Reload)
            urlParams.delete('id');
            const newSearch = urlParams.toString();
            const newUrl = window.location.pathname + (newSearch ? '?' + newSearch : '') + window.location.hash;
            window.history.replaceState(null, '', newUrl);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Firma aus localStorage anzeigen
    updateCompanyBadge();

    // Gespeicherte Filter wiederherstellen (vor dem ersten Laden)
    restoreTodosFiltersState();
    todoEnsureMobileSearchPanelIfQuery();
    updateTodosNavMobileTitle();
    initTodosDrawerNavChrome();
    initTodoDrawerSwipeToClose();
    window.addEventListener('resize', function() {
        var d = document.getElementById('todoDrawer');
        if (!d || d.classList.contains('todo-drawer-hidden')) return;
        if (window.matchMedia('(max-width: 1023px)').matches) {
            portalTodoDrawerToBodyIfMobile();
        } else {
            restoreTodoDrawerFromBodyIfMobile();
        }
    });

    // Picker nur per Klick (Modal „Fällig am“)
    initDatetimePickerOnly(document);
    
    loadFolders();
    ensureFolderCompaniesForModal();
    /* Geschlossene sofort laden, damit „Geschlossene anzeigen“ ohne Neuladen erscheint */
    loadClosedTodos().then(function() {
        updateToggleClosedVisibility();
    });
    scheduleInitialTodosLoad();
    saveTodosFiltersState();

    var todoSearchDebounce = null;
    var todoMobileSearchInput = document.getElementById('todo-mobile-search');
    function applyTodoSearchFilterAndSave() {
        displayTodos(todos);
        if (typeof closedTodosExpanded !== 'undefined' && closedTodosExpanded && typeof closedTodos !== 'undefined') {
            displayClosedTodos(closedTodos);
        }
        saveTodosFiltersState();
    }
    if (todoMobileSearchInput) {
        todoMobileSearchInput.addEventListener('input', function() {
            if (todoSearchDebounce) clearTimeout(todoSearchDebounce);
            todoSearchDebounce = setTimeout(function() {
                todoSearchDebounce = null;
                applyTodoSearchFilterAndSave();
            }, 200);
        });
        todoMobileSearchInput.addEventListener('blur', function() {
            window.requestAnimationFrame(function() {
                todoCloseMobileSearchIfEmpty();
            });
        });
    }
    var navTodosSearchBtn = document.getElementById('navMobileTodosSearchBtn');
    var todoMobileDash = document.getElementById('todo-mobile-dashboard');
    if (navTodosSearchBtn && todoMobileDash) {
        function todoToggleMobileSearchBar() {
            var isOpen = todoMobileDash.classList.contains('todo-mobile-search-panel-open');
            if (!isOpen) {
                todoSetMobileSearchPanelOpen(true, true);
                return;
            }
            if (!todoMobileSearchIsEmpty()) {
                var mInp = document.getElementById('todo-mobile-search');
                if (mInp) {
                    try { mInp.focus({ preventScroll: true }); } catch (e) { try { mInp.focus(); } catch (e2) {} }
                }
                return;
            }
            todoSetMobileSearchPanelOpen(false, false);
        }
        navTodosSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (Date.now() < todoIgnoreNavSearchClickUntil) return;
            todoToggleMobileSearchBar();
        });
    }
    (function todoBindMobileSearchCloseOnScroll() {
        /* Scroll-basiertes Auto-Close deaktiviert: führte auf einigen Geräten zu sofortigem Schließen nach Pull-Open. */
    })();

    // Desktop: Fokus ins Schnellfeld; mobil öffnet man es über das Plus in der Top-Nav
    var quickInput = document.getElementById('quickTodoInput');
    if (quickInput && window.matchMedia('(min-width: 1024px)').matches) {
        setTimeout(function() { quickInput.focus(); }, 100);
    }
    // Tastenkürzel-Hinweis nur Desktop (lg+); auf dem Handy ausgeblendet und nicht befüllt
    var hintEl = document.getElementById('quickTodoShortcutHint');
    if (hintEl && window.matchMedia('(min-width: 1024px)').matches) {
        var kbdClass = 'px-2 py-1.5 text-xs font-semibold text-gray-900 dark:text-primary-200 bg-gray-100 dark:bg-primary-300/60 border border-gray-300 dark:border-primary-320 rounded-base shadow-sm';
        var isMac = /Mac|iPhone|iPad|iPod/.test(navigator.platform) || (navigator.userAgentData && navigator.userAgentData.platform === 'macOS');
        var kbd = function(label) { return '<kbd class="' + kbdClass + '">' + (label || '') + '</kbd>'; };
        if (isMac) {
            hintEl.innerHTML = kbd('⌘') + ' ' + kbd('⌥') + ' ' + kbd('A');
        } else {
            hintEl.innerHTML = kbd('Alt') + ' ' + kbd('A');
        }
    }

    // Toggle geschlossene Aufgaben
    const toggleClosedBtn = document.getElementById('toggleClosedBtn');
    if (toggleClosedBtn) {
        toggleClosedBtn.addEventListener('click', toggleClosedTodos);
    }
    
    // Ordner-Filter Event Listener wird dynamisch in displayFolders() hinzugefügt
    
    // Quick Todo Form
    document.getElementById('quickTodoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        createQuickTodo();
    });

    /* iOS: Tastatur schließen (Fertig/Wischen) entfernt meist den Fokus → Schnell-Leiste mit ausblenden */
    var quickTodoInputEl = document.getElementById('quickTodoInput');
    if (quickTodoInputEl && typeof window.__todosSetMobileQuickComposeOpen === 'function') {
        quickTodoInputEl.addEventListener('blur', function() {
            if (window.matchMedia('(min-width: 1024px)').matches) return;
            if (!document.body.classList.contains('todos-mobile-quick-compose')) return;
            var inputRef = quickTodoInputEl;
            window.requestAnimationFrame(function() {
                if (window.matchMedia('(min-width: 1024px)').matches) return;
                if (!document.body.classList.contains('todos-mobile-quick-compose')) return;
                if (document.activeElement === inputRef) return;
                window.__todosSetMobileQuickComposeOpen(false, false);
            });
        });
    }

    var navMobileTodosQuickOpenLabel = document.getElementById('navMobileTodosQuickOpenLabel');
    if (navMobileTodosQuickOpenLabel && typeof window.__todosSetMobileQuickComposeOpen === 'function') {
        /* Capture: Leiste sichtbar machen, bevor das Label sein Standardverhalten (Fokus auf #quickTodoInput) ausführt → iOS-Tastatur */
        navMobileTodosQuickOpenLabel.addEventListener('click', function() {
            if (document.body.classList.contains('todos-mobile-quick-compose')) return;
            window.__todosSetMobileQuickComposeOpen(true, false);
        }, true);
    }
    var navMobileTodosQuickCloseBtn = document.getElementById('navMobileTodosQuickCloseBtn');
    if (navMobileTodosQuickCloseBtn && typeof window.__todosSetMobileQuickComposeOpen === 'function') {
        navMobileTodosQuickCloseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.__todosSetMobileQuickComposeOpen(false, false);
        });
    }
    var todosQuickComposeBackdrop = document.getElementById('todosQuickComposeBackdrop');
    if (todosQuickComposeBackdrop && typeof window.__todosSetMobileQuickComposeOpen === 'function') {
        todosQuickComposeBackdrop.addEventListener('click', function() {
            var next = !document.body.classList.contains('todos-mobile-quick-compose');
            window.__todosSetMobileQuickComposeOpen(next, false);
        });
    }

    /* Tipp auf freien Bereich (nicht Aufgabenzeile/Buttons/Links): öffnen mit gleichem User-Gesture wie direkter Tap → Tastatur.
       #main-content hat mobil min-h:100dvh, damit Klicks unterhalb kurzer Listen nicht „ins Leere“ gehen. */
    var todosMainContent = document.getElementById('main-content');
    if (todosMainContent && typeof window.__todosSetMobileQuickComposeOpen === 'function') {
        function todosTryOpenQuickComposeFromMainArea(e) {
            if (window.matchMedia('(min-width: 1024px)').matches) return;
            if (document.body.classList.contains('todos-mobile-quick-compose')) return;
            var drawer = document.getElementById('todoDrawer');
            if (drawer && !drawer.classList.contains('todo-drawer-hidden')) return;
            var t = e.target;
            if (t.closest('.todo-item')) return;
            if (t.closest('a[href], button, input, select, textarea, label[for], [role="button"]')) return;
            if (t.closest('#mobileFilterSheet')) return;
            if (t.closest('#todoContextMenu, #folderContextMenu')) return;
            if (e.type === 'pointerup' && e.pointerType !== 'touch') return;
            e.preventDefault();
            e.stopPropagation();
            window.__todosSetMobileQuickComposeOpen(true, true);
        }
        todosMainContent.addEventListener('click', todosTryOpenQuickComposeFromMainArea, true);
        todosMainContent.addEventListener('pointerup', todosTryOpenQuickComposeFromMainArea, true);
    }
    
    // Modal Event Listener
    document.getElementById('closeTodoModalBtn').addEventListener('click', function() {
        closeTodoModal();
    });
    
    document.getElementById('todoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveTodo();
    });
    
    
    // Datei-Upload Handler
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'drawer_file_upload') {
            const files = e.target.files;
            const todoId = document.getElementById('drawer_todo_id')?.value;
            if (!todoId || files.length === 0) return;
            
            Array.from(files).forEach(file => {
                uploadTodoAttachment(todoId, file);
            });
            
            // Input zurücksetzen
            e.target.value = '';
        }
    });
    
    document.getElementById('drawerCloseBtn').addEventListener('click', function() {
        closeTodoDrawer();
    });
    
    document.getElementById('drawerDeleteBtn').addEventListener('click', function() {
        const drawerTodoId = document.getElementById('todoDrawer').dataset.todoId;
        if (drawerTodoId) {
            closeTodoDrawer();
            deleteTodo(parseInt(drawerTodoId));
        }
    });
    
    // Drawer schließen bei Klick außerhalb
    document.getElementById('todoDrawer').addEventListener('click', function(e) {
        if (e.target === this) {
            closeTodoDrawer();
        }
    });
    
    // Tastenkombination: Alt+A (A = Aufgabe) oder Cmd+Option+A (Mac) fokussiert das Eingabefeld für neue Aufgaben
    document.addEventListener('keydown', function(e) {
        const isNewTaskShortcut = (e.altKey && e.code === 'KeyA') || (e.metaKey && e.altKey && e.code === 'KeyA');
        if (isNewTaskShortcut) {
            if (window.matchMedia('(max-width: 1023px)').matches) {
                return;
            }
            const active = document.activeElement;
            const inInput = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable);
            if (!inInput) {
                e.preventDefault();
                const input = document.getElementById('quickTodoInput');
                if (input) {
                    input.focus();
                }
            }
            return;
        }
        if (e.key === 'Escape') {
            const createShell = document.getElementById('mobile-sheet-create-folder-shell');
            if (createShell && createShell.classList.contains('mobile-sheet-create-folder-expanded')) {
                collapseMobileSheetFolderCreate();
                e.preventDefault();
                return;
            }
            const mobileFs = document.getElementById('mobileFilterSheet');
            if (mobileFs && mobileFs.getAttribute('aria-hidden') === 'false' && typeof window.__todosCloseMobileFilter === 'function') {
                window.__todosCloseMobileFilter(true);
                e.preventDefault();
                return;
            }
            if (document.body.classList.contains('todos-mobile-quick-compose') && typeof window.__todosSetMobileQuickComposeOpen === 'function') {
                window.__todosSetMobileQuickComposeOpen(false);
                e.preventDefault();
                return;
            }
            const todoMenu = document.getElementById('todoContextMenu');
            if (todoMenu && !todoMenu.classList.contains('hidden')) {
                hideTodoContextMenu();
                return;
            }
            const folderMenu = document.getElementById('folderContextMenu');
            if (folderMenu && !folderMenu.classList.contains('hidden')) {
                hideFolderContextMenu();
                return;
            }
            const folderModal = document.getElementById('folderModal');
            if (folderModal && !folderModal.classList.contains('hidden')) {
                closeFolderModal();
                return;
            }
            const drawer = document.getElementById('todoDrawer');
            if (drawer && !drawer.classList.contains('todo-drawer-hidden')) {
                closeTodoDrawer();
                e.preventDefault();
            }
        }
    });
    
    // Ordner Modal Event Listener
    document.getElementById('createFolderBtn').addEventListener('click', function() {
        openFolderModal();
    });
    const mobileSheetCreateFolderBtn = document.getElementById('mobile-sheet-create-folder-btn');
    if (mobileSheetCreateFolderBtn) {
        mobileSheetCreateFolderBtn.addEventListener('click', function() {
            expandMobileSheetFolderCreate();
        });
    }
    const mobileSheetSaveFolderBtn = document.getElementById('mobile-sheet-save-folder-btn');
    if (mobileSheetSaveFolderBtn) {
        mobileSheetSaveFolderBtn.addEventListener('click', function() {
            saveFolder({ fromMobileSheet: true });
        });
    }
    const mobileSheetCancelFolderBtn = document.getElementById('mobile-sheet-cancel-folder-btn');
    if (mobileSheetCancelFolderBtn) {
        mobileSheetCancelFolderBtn.addEventListener('click', function() {
            collapseMobileSheetFolderCreate();
        });
    }
    document.querySelectorAll('input[name="mobileSheetFolderVisibility"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const inviteSection = document.getElementById('mobile-sheet-folder-invite-section');
            if (!inviteSection) return;
            if (this.value === 'invite') {
                inviteSection.classList.remove('hidden');
                loadFolderCandidates();
            } else {
                inviteSection.classList.add('hidden');
                mobileSheetFolderMemberIds = [];
            }
            updateFolderCompanySectionsVisibility();
        });
    });
    const mobileSheetMemberSearch = document.getElementById('mobile-sheet-folder-member-search');
    if (mobileSheetMemberSearch) {
        mobileSheetMemberSearch.addEventListener('input', function() {
            renderFolderCandidates(this.value, 'mobile');
        });
    }

    // Mobile: Filter-Sheet (Toggle in Top-Nav, gleiche IDs/Verhalten wie Tickets)
    (function initTodosMobileFilterSheet() {
        const navMobileFilterToggleBtn = document.getElementById('navMobileFilterToggleBtn');
        const navMobileFilterTitleEl = document.querySelector('[data-nav-mobile-filter-title]');
        const mobileFilterSheet = document.getElementById('mobileFilterSheet');
        const mobileFilterSheetBackdrop = document.getElementById('mobileFilterSheetBackdrop');
        const mobileFilterSheetPanel = document.getElementById('mobileFilterSheetPanel');
        const mainNavEl = document.getElementById('main-nav');
        let mobileFilterSheetCloseAnimCleanup = null;
        let mobileFilterSheetClosingAnimated = false;

        function finishCloseTodosMobileFilterSheet() {
            collapseMobileSheetFolderCreate();
            if (mobileFilterSheetCloseAnimCleanup) {
                mobileFilterSheetCloseAnimCleanup();
                mobileFilterSheetCloseAnimCleanup = null;
            }
            mobileFilterSheetClosingAnimated = false;
            if (!mobileFilterSheet || !mobileFilterSheetPanel) return;
            if (mainNavEl) mainNavEl.classList.remove('main-nav-mobile-filter-open');
            mobileFilterSheet.setAttribute('aria-hidden', 'true');
            if (mobileFilterSheetBackdrop) {
                mobileFilterSheetBackdrop.style.pointerEvents = 'none';
                mobileFilterSheetBackdrop.classList.remove('opacity-100');
                mobileFilterSheetBackdrop.style.transition = '';
            }
            mobileFilterSheetPanel.classList.remove('mobile-filter-sheet-open');
            mobileFilterSheetPanel.style.transform = '';
            mobileFilterSheetPanel.style.transition = '';
            if (navMobileFilterToggleBtn) {
                navMobileFilterToggleBtn.setAttribute('aria-expanded', 'false');
                const lc = navMobileFilterToggleBtn.getAttribute('data-filter-label-closed');
                if (lc) {
                    navMobileFilterToggleBtn.setAttribute('aria-label', lc);
                    navMobileFilterToggleBtn.title = lc;
                }
            }
            if (navMobileFilterTitleEl) navMobileFilterTitleEl.setAttribute('aria-expanded', 'false');
        }

        function openTodosMobileFilterSheet() {
            if (typeof window.__todosSetMobileQuickComposeOpen === 'function') {
                window.__todosSetMobileQuickComposeOpen(false);
            }
            if (!mobileFilterSheet || !mobileFilterSheetPanel) return;
            if (mobileFilterSheetCloseAnimCleanup) {
                mobileFilterSheetCloseAnimCleanup();
                mobileFilterSheetCloseAnimCleanup = null;
            }
            mobileFilterSheetClosingAnimated = false;
            if (mainNavEl) mainNavEl.classList.add('main-nav-mobile-filter-open');
            mobileFilterSheet.setAttribute('aria-hidden', 'false');
            if (mobileFilterSheetBackdrop) {
                mobileFilterSheetBackdrop.style.pointerEvents = 'auto';
                mobileFilterSheetBackdrop.style.transition = '';
                mobileFilterSheetBackdrop.classList.add('opacity-100');
            }
            mobileFilterSheetPanel.classList.add('mobile-filter-sheet-open');
            mobileFilterSheetPanel.style.transform = '';
            mobileFilterSheetPanel.style.transition = '';
            if (navMobileFilterToggleBtn) {
                navMobileFilterToggleBtn.setAttribute('aria-expanded', 'true');
                const lo = navMobileFilterToggleBtn.getAttribute('data-filter-label-open');
                if (lo) {
                    navMobileFilterToggleBtn.setAttribute('aria-label', lo);
                    navMobileFilterToggleBtn.title = lo;
                }
            }
            if (navMobileFilterTitleEl) navMobileFilterTitleEl.setAttribute('aria-expanded', 'true');
            syncTodosMobileFolderSelect();
        }

        function closeTodosMobileFilterSheet(animated) {
            if (!mobileFilterSheet || !mobileFilterSheetPanel) return;
            if (mobileFilterSheet.getAttribute('aria-hidden') === 'true') return;
            if (!animated) {
                finishCloseTodosMobileFilterSheet();
                return;
            }
            if (!mobileFilterSheetBackdrop) {
                finishCloseTodosMobileFilterSheet();
                return;
            }
            if (mobileFilterSheetClosingAnimated) return;
            mobileFilterSheetClosingAnimated = true;
            mobileFilterSheetBackdrop.style.pointerEvents = 'none';
            mobileFilterSheetBackdrop.style.transition = 'opacity 0.28s ease-out';
            mobileFilterSheetBackdrop.classList.remove('opacity-100');
            mobileFilterSheetPanel.style.transition = 'transform 0.32s cubic-bezier(0.32, 0.72, 0, 1)';
            mobileFilterSheetPanel.style.transform = 'translateY(-100%)';
            let done = false;
            function onTransitionEnd(e) {
                if (done) return;
                if (e && e.target !== mobileFilterSheetPanel) return;
                if (e && e.propertyName && e.propertyName !== 'transform') return;
                done = true;
                if (mobileFilterSheetCloseAnimCleanup) {
                    mobileFilterSheetCloseAnimCleanup();
                    mobileFilterSheetCloseAnimCleanup = null;
                }
                finishCloseTodosMobileFilterSheet();
            }
            const fallbackMs = 380;
            const tid = setTimeout(function() { onTransitionEnd(null); }, fallbackMs);
            mobileFilterSheetCloseAnimCleanup = function() {
                mobileFilterSheetPanel.removeEventListener('transitionend', onTransitionEnd);
                clearTimeout(tid);
            };
            mobileFilterSheetPanel.addEventListener('transitionend', onTransitionEnd);
        }

        window.__todosCloseMobileFilter = closeTodosMobileFilterSheet;
        window.__todosOpenMobileFilterSheet = openTodosMobileFilterSheet;

        (function bindTodosMobileFilterSwipe() {
            const handle = document.getElementById('mobileFilterSheetHandle');
            const panel = document.getElementById('mobileFilterSheetPanel');
            if (!panel) return;

            function resetPanelTransform() {
                panel.style.transition = '';
                panel.style.transform = '';
            }

            function bindVerticalDismiss(el, opts) {
                opts = opts || {};
                const requireScrollTopZero = !!opts.requireScrollTopZero;
                let startY = 0;
                let startTime = 0;
                let currentY = 0;
                let active = false;
                let scrollBlocked = false;

                el.addEventListener('touchstart', function(e) {
                    if (!e.touches || e.touches.length !== 1) return;
                    startY = e.touches[0].clientY;
                    startTime = Date.now();
                    currentY = startY;
                    active = true;
                    scrollBlocked = requireScrollTopZero && scrollEl && scrollEl.scrollTop > 0;
                    panel.style.transition = 'none';
                }, { passive: true });

                el.addEventListener('touchmove', function(e) {
                    if (!active || !e.touches || e.touches.length === 0) return;
                    currentY = e.touches[0].clientY;
                    const dy = currentY - startY;
                    if (requireScrollTopZero) {
                        if (scrollEl && scrollEl.scrollTop > 0) {
                            scrollBlocked = true;
                            panel.style.transform = '';
                            return;
                        }
                        if (scrollBlocked) return;
                    }
                    if (dy >= 0) return;
                    e.preventDefault();
                    panel.style.transform = 'translateY(' + dy + 'px)';
                }, { passive: false });

                el.addEventListener('touchend', function(e) {
                    if (!active) return;
                    active = false;
                    if (requireScrollTopZero && scrollBlocked) {
                        resetPanelTransform();
                        return;
                    }
                    const endY = e.changedTouches && e.changedTouches.length ? e.changedTouches[0].clientY : currentY;
                    const dy = endY - startY;
                    const dt = Date.now() - startTime;
                    const velocity = dt > 0 ? dy / dt : 0;
                    const closeUp = dy < -80 || velocity < -0.45;
                    if (closeUp) {
                        closeTodosMobileFilterSheet();
                    } else {
                        resetPanelTransform();
                    }
                }, { passive: true });

                el.addEventListener('touchcancel', function() {
                    active = false;
                    scrollBlocked = false;
                    resetPanelTransform();
                }, { passive: true });
            }

            if (handle) bindVerticalDismiss(handle);
            /* Kein Wisch-Schließen auf dem Scroll-Inhalt: sonst blockiert die erste Aufwärts-Geste das Scrollen
               bei langem Formular (Teilen / Abbrechen). Schließen: Griff, Backdrop, Nav. */
        })();

        function toggleTodosMobileFilterSheetFromNav(e) {
            if (document.body.classList.contains('todos-drawer-detail-open')) return;
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (mobileFilterSheet && mobileFilterSheet.getAttribute('aria-hidden') === 'false') {
                closeTodosMobileFilterSheet(true);
            } else {
                openTodosMobileFilterSheet();
            }
        }

        if (navMobileFilterToggleBtn) navMobileFilterToggleBtn.addEventListener('click', toggleTodosMobileFilterSheetFromNav);
        if (navMobileFilterTitleEl) {
            navMobileFilterTitleEl.addEventListener('click', toggleTodosMobileFilterSheetFromNav);
            navMobileFilterTitleEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    toggleTodosMobileFilterSheetFromNav(e);
                }
            });
        }
        if (mobileFilterSheetBackdrop) {
            mobileFilterSheetBackdrop.addEventListener('click', function() {
                closeTodosMobileFilterSheet(true);
            });
        }

        (function bindTodosMobileFilterPullDownOpen() {
            const wrap = document.querySelector('.todos-list-scroll-wrap');
            if (!wrap) return;
            const mq = window.matchMedia('(max-width: 1023px)');
            const THRESH = 76;
            const TOP_TOLERANCE = 20;
            const navMobileSearchBtn = document.getElementById('navMobileTodosSearchBtn');
            let startY = 0;
            let startX = 0;
            let tracking = false;
            let startAtTop = false;
            let pullReady = false;
            /* Mobil scrollt #main-content (fixe Nav), nicht window — gleiche Logik wie Tickets. */
            function pageScrollTop() {
                const mc = document.getElementById('main-content');
                if (mc) return mc.scrollTop;
                return window.pageYOffset || document.documentElement.scrollTop || 0;
            }
            function sheetIsOpen() {
                return mobileFilterSheet && mobileFilterSheet.getAttribute('aria-hidden') === 'false';
            }
            function quickComposeOpen() {
                return document.body.classList.contains('todos-mobile-quick-compose');
            }
            function clearNavSearchPullPreview() {
                if (!navMobileSearchBtn) return;
                navMobileSearchBtn.style.transform = '';
                navMobileSearchBtn.style.boxShadow = '';
                navMobileSearchBtn.style.transition = '';
                const icon = navMobileSearchBtn.querySelector('svg');
                if (icon) {
                    icon.style.transform = '';
                    icon.style.opacity = '';
                }
            }
            function setNavSearchPullPreview(dy) {
                return;
            }
            function triggerPullToSearchOpen() {
                todoIgnoreNavSearchClickUntil = Date.now() + 500;
                todoSetMobileSearchPanelOpen(true, true);
            }
            wrap.addEventListener('touchstart', function(e) {
                if (!mq.matches || sheetIsOpen() || quickComposeOpen() || document.body.classList.contains('todos-drawer-detail-open')) return;
                if (!e.touches || e.touches.length !== 1) return;
                tracking = true;
                startAtTop = pageScrollTop() <= TOP_TOLERANCE;
                pullReady = false;
                startY = e.touches[0].clientY;
                startX = e.touches[0].clientX;
                clearNavSearchPullPreview();
            }, { passive: true });
            wrap.addEventListener('touchmove', function(e) {
                if (!tracking || !startAtTop || quickComposeOpen()) return;
                if (pageScrollTop() > TOP_TOLERANCE) {
                    startAtTop = false;
                    clearNavSearchPullPreview();
                    return;
                }
                if (!e.touches || e.touches.length === 0) return;
                const dy = e.touches[0].clientY - startY;
                const dx = e.touches[0].clientX - startX;
                if (dy <= 0 || Math.abs(dx) * 1.25 >= Math.abs(dy)) {
                    clearNavSearchPullPreview();
                    return;
                }
                setNavSearchPullPreview(dy);
                if (dy >= THRESH) pullReady = true;
            }, { passive: true });
            wrap.addEventListener('touchend', function(e) {
                if (!tracking) return;
                tracking = false;
                var dash = document.getElementById('todo-mobile-dashboard');
                var panelOpen = !!(dash && dash.classList.contains('todo-mobile-search-panel-open'));
                var tClose = e.changedTouches && e.changedTouches[0];
                if (panelOpen && todoMobileSearchIsEmpty() && tClose) {
                    var dyClose = tClose.clientY - startY;
                    var dxClose = tClose.clientX - startX;
                    if (dyClose <= -THRESH && Math.abs(dxClose) * 1.25 < Math.abs(dyClose)) {
                        todoSetMobileSearchPanelOpen(false, false);
                        clearNavSearchPullPreview();
                        return;
                    }
                }
                if (!startAtTop || sheetIsOpen() || quickComposeOpen()) {
                    clearNavSearchPullPreview();
                    return;
                }
                if (pageScrollTop() > TOP_TOLERANCE) {
                    clearNavSearchPullPreview();
                    return;
                }
                const t = e.changedTouches && e.changedTouches[0];
                if (!t) {
                    clearNavSearchPullPreview();
                    return;
                }
                const dy = t.clientY - startY;
                const dx = t.clientX - startX;
                const shouldOpen = pullReady || dy >= THRESH;
                if (!shouldOpen || Math.abs(dx) * 1.25 >= Math.abs(dy)) {
                    clearNavSearchPullPreview();
                    return;
                }
                triggerPullToSearchOpen();
            }, { passive: true });
            wrap.addEventListener('touchcancel', function() {
                tracking = false;
                pullReady = false;
                clearNavSearchPullPreview();
            }, { passive: true });
        })();

        (function bindTodosMobileFilterSwipeUpClose() {
            const mq = window.matchMedia('(max-width: 1023px)');
            const THRESH = 76;
            const sheetScroll = document.getElementById('mobileFilterSheetScroll');
            let startY = 0;
            let startX = 0;
            let tracking = false;
            let startedInSheetScroll = false;
            function sheetIsOpen() {
                return mobileFilterSheet && mobileFilterSheet.getAttribute('aria-hidden') === 'false';
            }
            function quickComposeOpen() {
                return document.body.classList.contains('todos-mobile-quick-compose');
            }
            document.addEventListener('touchstart', function(e) {
                if (!mq.matches || !sheetIsOpen() || quickComposeOpen()) return;
                if (!e.touches || e.touches.length !== 1) return;
                tracking = true;
                startY = e.touches[0].clientY;
                startX = e.touches[0].clientX;
                startedInSheetScroll = !!(sheetScroll && sheetScroll.contains(e.target));
            }, { passive: true, capture: true });
            document.addEventListener('touchend', function(e) {
                if (!tracking) return;
                tracking = false;
                if (!mq.matches || !sheetIsOpen() || quickComposeOpen()) return;
                const t = e.changedTouches && e.changedTouches[0];
                if (!t) return;
                const dy = t.clientY - startY;
                const dx = t.clientX - startX;
                if (dy > -THRESH) return;
                if (Math.abs(dx) * 1.25 >= Math.abs(dy)) return;
                if (startedInSheetScroll && sheetScroll && sheetScroll.scrollTop > 2) return;
                closeTodosMobileFilterSheet(true);
            }, { passive: true, capture: true });
            document.addEventListener('touchcancel', function() {
                tracking = false;
            }, { passive: true, capture: true });
        })();

        const mobileFolderSelect = document.getElementById('mobile-sheet-todos-folder-select');
        if (mobileFolderSelect) {
            mobileFolderSelect.addEventListener('change', function() {
                const val = this.value;
                if (val === 'all') {
                    currentFolderId = null;
                } else if (val === '0') {
                    currentFolderId = 0;
                } else {
                    currentFolderId = parseInt(val, 10);
                }
                loadTodos();
                updateFolderButtons();
                saveTodosFiltersState();
                syncTodosMobileFolderSelect();
                if (typeof collapseMobileSheetFolderCreate === 'function') {
                    collapseMobileSheetFolderCreate();
                }
                if (typeof window.__todosCloseMobileFilter === 'function') {
                    window.__todosCloseMobileFilter(true);
                }
            });
        }
    })();

    let todosMobileFilterLastViewport = window.matchMedia('(max-width: 1023px)').matches;
    window.addEventListener('resize', function() {
        const isMobile = window.matchMedia('(max-width: 1023px)').matches;
        if (isMobile !== todosMobileFilterLastViewport) {
            todosMobileFilterLastViewport = isMobile;
            if (!isMobile && typeof window.__todosCloseMobileFilter === 'function') {
                window.__todosCloseMobileFilter(false);
                collapseMobileSheetFolderCreate();
            }
        }
    });
    
    document.getElementById('closeFolderModalBtn').addEventListener('click', function() {
        closeFolderModal();
    });
    
    document.getElementById('folderForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveFolder();
    });
    
    document.querySelectorAll('input[name="folderVisibility"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const inviteSection = document.getElementById('folderInviteSection');
            if (this.value === 'invite') {
                inviteSection.classList.remove('hidden');
                loadFolderCandidates();
            } else {
                inviteSection.classList.add('hidden');
                folderSelectedMemberIds = [];
            }
            updateFolderCompanySectionsVisibility();
        });
    });
    
    document.getElementById('folderMemberSearch').addEventListener('input', function() {
        renderFolderCandidates(this.value);
    });
    
    // Modal schließen bei Klick auf Overlay
    document.getElementById('todoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeTodoModal();
        }
    });
    
    document.getElementById('folderModalOverlay').addEventListener('click', function() {
        closeFolderModal();
    });
    
    // Ordner-Kontextmenü (Klicks innerhalb nicht schließen)
    const folderContextMenuEl = document.getElementById('folderContextMenu');
    if (folderContextMenuEl) {
        folderContextMenuEl.addEventListener('click', function(e) { e.stopPropagation(); });
    }
    // Aufgaben-Kontextmenü (Klicks innerhalb nicht schließen, Handler)
    const todoContextMenuEl = document.getElementById('todoContextMenu');
    if (todoContextMenuEl) {
        todoContextMenuEl.addEventListener('click', function(e) {
            e.stopPropagation();
            handleTodoContextMenuClick(e);
        });
    }
    // Rechtsklick auf Aufgabe: Kontextmenü zeigen
    document.addEventListener('contextmenu', function(e) {
        const todoItem = e.target.closest('.todo-item');
        if (todoItem) {
            e.preventDefault();
            e.stopPropagation();
            const todoId = parseInt(todoItem.dataset.todoId);
            const todo = todos.find(t => t.id == todoId) || closedTodos.find(t => t.id == todoId);
            if (todo) {
                showTodoContextMenu(e.clientX, e.clientY, todo);
            }
        }
    });
    document.getElementById('folderCtxRename').addEventListener('click', function(e) {
        e.stopPropagation();
        if (folderContextFolder) {
            openFolderModal(folderContextFolder);
        }
        hideFolderContextMenu();
    });
    document.getElementById('folderCtxDelete').addEventListener('click', function(e) {
        e.stopPropagation();
        if (folderContextFolder) {
            deleteFolder(folderContextFolder);
        }
        hideFolderContextMenu();
    });
    document.addEventListener('click', function() {
        hideFolderContextMenu();
        hideTodoContextMenu();
    });
    
    // Drag-to-Scroll für Ordner-Filter initialisieren
    initFolderFiltersDragScroll();
});

/** Entspricht der Firmenauswahl in der Nav (localStorage) — gleiche Quelle wie loadTodos. */
function getNavSelectedCompanyId() {
    try {
        const savedSelection = localStorage.getItem('selectedUserOption');
        if (savedSelection) {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0') {
                return parseInt(data.id, 10);
            }
        }
    } catch (e) {
        console.error('Fehler beim Lesen der company_id aus localStorage:', e);
    }
    return null;
}

function ensureFolderCompaniesForModal() {
    if (folderModalCompaniesPromise) {
        return folderModalCompaniesPromise;
    }
    folderModalCompaniesPromise = todosFetchJson(companiesApiUrl)
        .then(function(data) {
            if (data.success && Array.isArray(data.companies)) {
                folderModalCompanies = data.companies.filter(function(c) {
                    return c && (c.status === 'aktiv' || c.status === undefined);
                });
            } else {
                folderModalCompanies = [];
            }
            fillFolderCompanySelectOptions();
        })
        .catch(function(err) {
            console.error('Firmen für Ordner-Dialog:', err);
            folderModalCompanies = [];
            folderModalCompaniesPromise = null;
        });
    return folderModalCompaniesPromise;
}

function fillFolderCompanySelectOptions() {
    function fillSelect(sel) {
        if (!sel) {
            return;
        }
        while (sel.firstChild) {
            sel.removeChild(sel.firstChild);
        }
        var o0 = document.createElement('option');
        o0.value = '';
        o0.textContent = 'Keine Zuordnung';
        sel.appendChild(o0);
        folderModalCompanies.forEach(function(c) {
            var o = document.createElement('option');
            o.value = String(c.id);
            o.textContent = c.name != null ? String(c.name) : '';
            sel.appendChild(o);
        });
    }
    fillSelect(document.getElementById('folderCompanySelect'));
    fillSelect(document.getElementById('mobile-sheet-folder-company'));
}

function folderEditingLocksCompanySelect() {
    var folderIdVal = document.getElementById('folder_id') ? document.getElementById('folder_id').value : '';
    if (!folderIdVal) {
        return false;
    }
    var ef = folders.find(function(f) { return Number(f.id) === Number(folderIdVal); });
    if (!ef) {
        return false;
    }
    return (ef.is_ticket_system_folder == 1 || ef.is_ticket_system_folder === true ||
        ef.is_project_system_folder == 1 || ef.is_project_system_folder === true);
}

function updateFolderCompanySectionsVisibility() {
    var desktopSec = document.getElementById('folderCompanySection');
    var mobileSec = document.getElementById('mobile-sheet-folder-company-section');
    var pubDesk = document.getElementById('folderVisibilityPublic');
    var pubMob = document.getElementById('mobile-sheet-folder-vis-public');
    var systemLocked = folderEditingLocksCompanySelect();
    var showDesk = !!(pubDesk && pubDesk.checked && !systemLocked);
    var showMob = !!(pubMob && pubMob.checked);
    if (mobileSec && systemLocked) {
        showMob = false;
    }
    if (desktopSec) {
        desktopSec.classList.toggle('hidden', !showDesk);
    }
    if (mobileSec) {
        mobileSec.classList.toggle('hidden', !showMob);
    }
}

function setFolderCompanySelectValue(target, companyId) {
    var v = companyId != null && companyId !== '' && !isNaN(Number(companyId)) ? String(Number(companyId)) : '';
    function applyTo(sel) {
        if (!sel) {
            return;
        }
        if (v && !Array.prototype.some.call(sel.options, function(o) { return o.value === v; })) {
            sel.value = '';
            return;
        }
        sel.value = v;
    }
    if (target === 'desktop' || target === 'both') {
        applyTo(document.getElementById('folderCompanySelect'));
    }
    if (target === 'mobile' || target === 'both') {
        applyTo(document.getElementById('mobile-sheet-folder-company'));
    }
}

function loadFolders() {
    const cid = getNavSelectedCompanyId();
    let url = foldersApiUrl;
    if (cid) {
        url += '?company_id=' + encodeURIComponent(String(cid));
    }
    todosFetchJson(url)
        .then(data => {
            if (data.success) {
                folders = data.folders;
                // Gespeicherten Ordner-Filter prüfen: falls Ordner gelöscht wurde, auf "alle" zurücksetzen
                // Typ-sicher vergleichen (API kann id als Zahl oder String liefern)
                if (currentFolderId != null && currentFolderId !== 0 && !folders.find(f => Number(f.id) === Number(currentFolderId))) {
                    currentFolderId = null;
                }
                displayFolders();
                // updateFolderButtons() nicht aufrufen, da displayFolders() bereits die korrekten Styles setzt
                // Das verhindert das weiße Flackern beim Neuladen
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Ordner:', error);
        });
}

function displayFolders() {
    const folderFiltersContainer = document.getElementById('folderFilters');
    if (!folderFiltersContainer) return;
    
    // Alte Buttons sammeln, um sie später zu entfernen
    const oldButtons = Array.from(folderFiltersContainer.querySelectorAll('button'));
    
    // DocumentFragment verwenden, um alle Buttons zuerst zu erstellen, bevor sie eingefügt werden
    // Das verhindert das weiße Flackern beim Neuladen
    const fragment = document.createDocumentFragment();
    
    // Ordner-Buttons hinzufügen (Name + Zähler-Pill)
    folders.forEach(folder => {
        const button = document.createElement('button');
        button.type = 'button';
        const fid = Number(folder.id);
        const isActive = currentFolderId != null && Number(currentFolderId) === fid;
        // Buttons mit vollständigen Styles erstellen, inklusive transition-colors
        button.className = `flex items-center gap-2 max-w-[min(100%,18rem)] px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 transition-colors ${isActive ? 'folder-btn-active bg-primary-820 text-white border-primary-700 dark:text-primary-840 dark:bg-primary-800 dark:border-primary-820' : 'bg-gray-50 text-gray-900 hover:bg-gray-100 dark:bg-primary-700 dark:border-primary-720 dark:text-primary-210 dark:hover:bg-primary-760'}`;
        const labelEl = document.createElement('span');
        labelEl.className = 'folder-filter-label truncate';
        labelEl.textContent = folder.name != null ? String(folder.name) : '';
        const openN = folder.open_todo_count != null ? folder.open_todo_count : 0;
        const countEl = document.createElement('span');
        countEl.className = 'folder-filter-count';
        countEl.textContent = String(openN);
        countEl.title = openN === 1 ? '1 offene Aufgabe' : openN + ' offene Aufgaben';
        button.appendChild(labelEl);
        button.appendChild(countEl);
        button.setAttribute('aria-label', (folder.name != null ? String(folder.name) : 'Ordner') + ', ' + openN + ' offen');
        button.onclick = function() {
            // Toggle-Funktionalität: Wenn bereits aktiv, dann abwählen (0 setzen = nur ohne Ordner)
            if (currentFolderId != null && Number(currentFolderId) === fid) {
                currentFolderId = 0;
            } else {
                currentFolderId = fid;
            }
            loadTodos();
            updateFolderButtons();
            saveTodosFiltersState();
        };
        
        // Rechtsklick-Kontextmenü
        button.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showFolderContextMenu(this, folder);
        });
        // Drop-Ziel: Aufgabe per Drag & Drop zuweisen (Hintergrund wie bei Hover)
        button.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('!bg-gray-100', 'dark:!bg-gray-600');
        });
        button.addEventListener('dragleave', function(e) {
            this.classList.remove('!bg-gray-100', 'dark:!bg-gray-600');
        });
        button.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('!bg-gray-100', 'dark:!bg-gray-600');
            const todoId = e.dataTransfer.getData('text/plain');
            if (todoId) {
                assignTodoToFolder(parseInt(todoId), fid);
            }
        });
        button.dataset.folderId = fid;
        fragment.appendChild(button);
    });
    
    // Alte Buttons entfernen und neue auf einmal einfügen (verhindert Flackern)
    // Statt innerHTML zu verwenden, entfernen wir die alten Buttons einzeln
    oldButtons.forEach(btn => btn.remove());
    folderFiltersContainer.appendChild(fragment);
    
    // Scroll-Hinweise aktualisieren
    updateFolderFiltersScrollIndicators();
    populateTodosMobileFolderSelect();
}

function updateFolderButtons() {
    const folderFiltersContainer = document.getElementById('folderFilters');
    if (!folderFiltersContainer) return;
    
    const buttons = folderFiltersContainer.querySelectorAll('button');
    buttons.forEach((button, index) => {
        const folder = folders[index];
        if (!folder) return;
        const fid = Number(folder.id);
        const isActive = currentFolderId != null && Number(currentFolderId) === fid;
        
        // Nur die relevanten Klassen ändern, nicht die gesamte className überschreiben
        // Das verhindert Flackern, da die Basis-Styles erhalten bleiben
        // Sicherstellen, dass die border-Klasse immer vorhanden ist
        if (!button.classList.contains('border')) {
            button.classList.add('border');
        }
        
        if (isActive) {
            button.classList.remove('bg-gray-50', 'text-gray-900', 'hover:bg-gray-100', 'dark:bg-primary-700', 'dark:border-primary-720', 'dark:text-primary-210', 'dark:hover:bg-primary-760', 'border-gray-300');
            button.classList.add('folder-btn-active', 'bg-primary-820', 'text-white', 'border-primary-700', 'dark:text-primary-840', 'dark:bg-primary-800', 'dark:border-primary-820');
        } else {
            button.classList.remove('folder-btn-active', 'bg-primary-820', 'text-white', 'border-primary-700', 'dark:text-primary-840', 'dark:bg-primary-800', 'dark:border-primary-820');
            button.classList.add('bg-gray-50', 'text-gray-900', 'hover:bg-gray-100', 'dark:bg-primary-700', 'dark:border-primary-720', 'dark:text-primary-210', 'dark:hover:bg-primary-760', 'border-gray-300');
        }
    });
    syncTodosMobileFolderSelect();
}

/** Skeleton-UI beim Laden / Neuladen der offenen Aufgaben (wie Ticket-Liste) */
function setTodosLoadingSkeletons() {
    const list = document.getElementById('todosList');
    if (!list) return;
    var html = '';
    for (var i = 0; i < 7; i++) {
        html += '<li class="todo-skeleton-item animate-pulse rounded-xl border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 lg:shadow-sm pointer-events-none" aria-hidden="true">' +
            '<div class="flex items-center gap-4 lg:gap-2.5 py-2 max-lg:ps-4 max-lg:pe-4 lg:py-1.5 lg:ps-1.5 lg:pe-2.5">' +
            '<div class="hidden lg:flex lg:w-2.5 lg:shrink-0" aria-hidden="true"></div>' +
            '<div class="h-5 w-5 max-lg:h-6 max-lg:w-6 shrink-0 rounded-full bg-gray-200 dark:bg-primary-140" aria-hidden="true"></div>' +
            '<div class="flex-1 min-w-0 space-y-2 lg:ps-1.5">' +
            '<div class="h-4 rounded-md bg-gray-200 dark:bg-primary-140 max-w-[88%]"></div>' +
            '<div class="h-3 rounded-md bg-gray-100 dark:bg-primary-120 max-w-[55%]"></div>' +
            '</div>' +
            '<div class="flex shrink-0 items-center gap-2 max-lg:gap-2.5">' +
            '<div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-primary-140"></div>' +
            '<div class="h-5 w-5 max-lg:h-6 max-lg:h-6 rounded bg-gray-100 dark:bg-primary-120"></div>' +
            '</div></div></li>';
    }
    list.setAttribute('aria-busy', 'true');
    list.innerHTML = html;
}

let todosLoadingSkeletonTimer = null;
const todosLoadingSkeletonDelayMs = 300;

function clearTodosLoadingSkeletonTimer() {
    if (todosLoadingSkeletonTimer) {
        clearTimeout(todosLoadingSkeletonTimer);
        todosLoadingSkeletonTimer = null;
    }
}

function scheduleTodosLoadingSkeletons() {
    clearTodosLoadingSkeletonTimer();
    todosLoadingSkeletonTimer = setTimeout(function() {
        todosLoadingSkeletonTimer = null;
        setTodosLoadingSkeletons();
    }, todosLoadingSkeletonDelayMs);
}

function loadTodos() {
    scheduleTodosLoadingSkeletons();
    // company_id aus localStorage lesen
    let companyId = null;
    try {
        const savedSelection = localStorage.getItem('selectedUserOption');
        if (savedSelection) {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0') {
                companyId = parseInt(data.id);
            }
        }
    } catch (e) {
        console.error('Fehler beim Lesen der company_id aus localStorage:', e);
    }
    
    let url = todosApiUrl;
    const params = new URLSearchParams();
    
    if (currentFolderId !== null && currentFolderId !== undefined) {
        params.append('folder_id', currentFolderId);
    }
    
    params.append('status', 'offen');
    
    if (companyId) {
        params.append('company_id', companyId);
    }
    
    if (params.toString()) {
        url += '?' + params.toString();
    }
    
    return todosFetchJson(url)
        .then(data => {
            clearTodosLoadingSkeletonTimer();
            if (data.success) {
                todos = data.todos;
                const wasClosedExpanded = closedTodosExpanded;
                closedTodosExpanded = false;
                closedTodos = [];
                displayTodos(todos);
                prefetchAssignableUsersForTodos();
                updateClosedTodosSection();
                loadClosedTodos().then(() => {
                    updateToggleClosedVisibility();
                    if (wasClosedExpanded) {
                        closedTodosExpanded = true;
                        const closedList = document.getElementById('closedTodosList');
                        const toggleIcon = document.getElementById('toggleClosedIcon');
                        const toggleText = document.getElementById('toggleClosedText');
                        if (closedList) closedList.classList.remove('hidden');
                        if (toggleIcon) toggleIcon.style.transform = 'rotate(180deg)';
                        if (toggleText) toggleText.textContent = 'Geschlossene ausblenden';
                    }
                });
            } else {
                console.error('Fehler beim Laden der Todos:', data.error);
                var errList = document.getElementById('todosList');
                if (errList) {
                    errList.removeAttribute('aria-busy');
                    errList.innerHTML =
                    '<li class="text-center text-red-500 py-8">Fehler beim Laden der Aufgaben</li>';
                }
            }
        })
        .catch(error => {
            clearTodosLoadingSkeletonTimer();
            console.error('Fehler:', error);
            var errList2 = document.getElementById('todosList');
            if (errList2) {
                errList2.removeAttribute('aria-busy');
                errList2.innerHTML =
                '<li class="text-center text-red-500 py-8">Fehler beim Laden der Aufgaben</li>';
            }
        });
}

function loadClosedTodos() {
    let companyId = null;
    try {
        const savedSelection = localStorage.getItem('selectedUserOption');
        if (savedSelection) {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0') {
                companyId = parseInt(data.id);
            }
        }
    } catch (e) {
        console.error('Fehler beim Lesen der company_id aus localStorage:', e);
    }
    let url = todosApiUrl;
    const params = new URLSearchParams();
    if (currentFolderId !== null && currentFolderId !== undefined) {
        params.append('folder_id', currentFolderId);
    }
    params.append('status', 'erledigt');
    if (companyId) {
        params.append('company_id', companyId);
    }
    if (params.toString()) {
        url += '?' + params.toString();
    }
    return todosFetchJson(url)
        .then(data => {
            if (data.success) {
                closedTodos = data.todos;
                displayClosedTodos(closedTodos);
                prefetchAssignableUsersForTodos();
            } else {
                closedTodos = [];
                displayClosedTodos([]);
            }
            updateToggleClosedVisibility();
        })
        .catch(error => {
            console.error('Fehler beim Laden der geschlossenen Aufgaben:', error);
            closedTodos = [];
            displayClosedTodos([]);
            updateToggleClosedVisibility();
        });
}

function toggleClosedTodos() {
    closedTodosExpanded = !closedTodosExpanded;
    const closedList = document.getElementById('closedTodosList');
    const toggleIcon = document.getElementById('toggleClosedIcon');
    const toggleText = document.getElementById('toggleClosedText');
    if (!closedList || !toggleIcon || !toggleText) return;
    if (closedTodosExpanded) {
        if (closedTodos.length === 0) {
            loadClosedTodos().then(() => {
                closedList.classList.remove('hidden');
                toggleIcon.style.transform = 'rotate(180deg)';
                toggleText.textContent = 'Geschlossene ausblenden';
            });
        } else {
            displayClosedTodos(closedTodos);
            closedList.classList.remove('hidden');
            toggleIcon.style.transform = 'rotate(180deg)';
            toggleText.textContent = 'Geschlossene ausblenden';
        }
    } else {
        closedList.classList.add('hidden');
        toggleIcon.style.transform = '';
        toggleText.textContent = 'Geschlossene anzeigen';
    }
    saveTodosFiltersState();
}

function updateClosedTodosSection() {
    const closedList = document.getElementById('closedTodosList');
    const toggleIcon = document.getElementById('toggleClosedIcon');
    const toggleText = document.getElementById('toggleClosedText');
    if (!closedList || !toggleIcon || !toggleText) return;
    if (closedTodosExpanded) {
        closedTodosExpanded = false;
    }
    closedList.classList.add('hidden');
    closedList.innerHTML = '';
    toggleIcon.style.transform = '';
    toggleText.textContent = 'Geschlossene anzeigen';
}

function updateToggleClosedVisibility() {
    const wrapper = document.getElementById('toggleClosedBtnWrapper');
    if (!wrapper) return;
    if (closedTodos.length > 0) {
        wrapper.classList.remove('hidden');
    } else {
        wrapper.classList.add('hidden');
    }
}

function getTodoListSearchQuery() {
    if (typeof window.matchMedia === 'function' && window.matchMedia('(min-width: 1024px)').matches) {
        return '';
    }
    var m = document.getElementById('todo-mobile-search');
    var s = m && m.value ? m.value : '';
    return String(s).trim().toLowerCase();
}

function filterTodosArrayBySearch(arr, q) {
    if (!q || !arr || !arr.length) return arr || [];
    return arr.filter(function(t) {
        var titel = (t.titel || '').toLowerCase();
        var desc = (t.beschreibung || '').toLowerCase();
        return titel.indexOf(q) !== -1 || desc.indexOf(q) !== -1;
    });
}

function displayClosedTodos(list) {
    const closedList = document.getElementById('closedTodosList');
    if (!closedList) return;
    const q = getTodoListSearchQuery();
    const toShow = q ? filterTodosArrayBySearch(list, q) : list;
    if (toShow.length === 0) {
        closedList.innerHTML = '';
    } else {
        closedList.innerHTML = toShow.map((todo, index) => createTodoItem(todo, index)).join('');
    }
}

function displayTodos(todosList) {
    const list = document.getElementById('todosList');
    if (!list) return;
    list.removeAttribute('aria-busy');
    const q = getTodoListSearchQuery();
    const toShow = q ? filterTodosArrayBySearch(todosList, q) : todosList;

    if (toShow.length === 0) {
        list.innerHTML = '<li class="text-center text-gray-500 dark:text-gray-400 py-8">Keine Aufgaben gefunden</li>';
        return;
    }

    list.innerHTML = toShow.map((todo, index) => createTodoItem(todo, index)).join('');

    // Drag & Drop initialisieren
    initDragAndDrop();
}

function getInitials(vorname, nachname) {
    const v = (vorname || '').trim();
    const n = (nachname || '').trim();
    const vInitial = v ? v.charAt(0).toUpperCase() : '';
    const nInitial = n ? n.charAt(0).toUpperCase() : '';
    return (vInitial + nInitial) || '?';
}

function syncDrawerFolderCard() {
    var sel = document.getElementById('drawer_folder_id');
    var textEl = document.getElementById('drawer_folder_card_text');
    if (!sel || !textEl) return;
    var opt = sel.options[sel.selectedIndex];
    textEl.textContent = opt ? String(opt.textContent || '').trim() : 'Kein Ordner';
}

function getDrawerAssigneeDefaultIconHtml() {
    return '<svg class="h-6 w-6 text-gray-600 dark:text-primary-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 00-4.255.1M13 20h1.083A3.916 3.916 0 0018 16.083V9A6 6 0 106 9v7m7 4v-1a1 1 0 00-1-1h-1a1 1 0 00-1 1v1a1 1 0 001 1h1a1 1 0 001-1v-1Zm-7-4v-6H5a2 2 0 00-2 2v2a2 2 0 002 2h1Zm12-6h1a2 2 0 012 2v2a2 2 0 01-2 2h-1v-6Z"/></svg>';
}

function syncDrawerAssigneeCard() {
    var sel = document.getElementById('drawer_zugewiesen_an');
    var textEl = document.getElementById('drawer_assignee_card_text');
    var innerEl = document.getElementById('drawer_assignee_card_avatar_inner');
    if (!sel || !textEl || !innerEl) return;
    var val = sel.value;
    if (!val || val === '' || val === 'null') {
        textEl.textContent = 'Nicht zugewiesen';
        innerEl.innerHTML = getDrawerAssigneeDefaultIconHtml();
        return;
    }
    var opt = sel.options[sel.selectedIndex];
    textEl.textContent = opt ? String(opt.textContent || '').trim() : '—';
    var uid = parseInt(val, 10);
    var u = (typeof allUsers !== 'undefined' && allUsers && allUsers.length)
        ? allUsers.find(function(x) { return Number(x.id) === uid; })
        : null;
    if (u) {
        innerEl.innerHTML = getAssigneeAvatarInnerHtml(u.logopfad || null, u.vorname, u.nachname, 'h-11 w-11 rounded-full');
    } else {
        innerEl.innerHTML = getAssigneeAvatarInnerHtml(null, '', '', 'h-11 w-11 rounded-full');
    }
}

function getTodoAssigneeAvatarUrl(logopfad) {
    if (!logopfad) return null;
    if (String(logopfad).startsWith('preset:')) return null;
    if (String(logopfad).startsWith('http://') || String(logopfad).startsWith('https://')) return String(logopfad);
    return '<?php echo BASE_URL; ?>' + String(logopfad).replace(/^\//, '');
}

/** Profilbild, Preset-Avatar oder Initialen (wie Ticket-Tabellenansicht). */
function getAssigneeAvatarInnerHtml(logopfad, vorname, nachname, sizeClasses) {
    const fullName = [vorname || '', nachname || ''].filter(Boolean).join(' ').trim();
    const initials = getInitials(vorname, nachname);
    const defaultImg = '<?php echo BASE_URL; ?>assets/images/default-avatar.png';
    if (logopfad && String(logopfad).startsWith('preset:')) {
        const parts = String(logopfad).split(':');
        let presetColor = parts[1] || '6b7280';
        if (!presetColor.startsWith('#')) presetColor = '#' + presetColor.replace(/^#/, '');
        const presetInitials = parts[2] || initials || '?';
        return `<div class="${sizeClasses} flex items-center justify-center text-white text-xs max-lg:text-sm font-semibold shrink-0" style="background-color:${escapeHtml(presetColor)};">${escapeHtml(presetInitials)}</div>`;
    }
    const url = getTodoAssigneeAvatarUrl(logopfad);
    if (url) {
        return `<img class="${sizeClasses} object-cover shrink-0" src="${escapeHtml(url)}" alt="${escapeHtml(fullName || 'Zugewiesen an')}" onerror="this.onerror=null;this.src='${defaultImg}';">`;
    }
    return `<div class="${sizeClasses} flex items-center justify-center bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-300 text-xs max-lg:text-sm font-semibold shrink-0">${escapeHtml(initials)}</div>`;
}

function renderTodoAssigneeAvatar(todo) {
    return getAssigneeAvatarInnerHtml(todo.zugewiesen_logopfad || null, todo.zugewiesen_vorname, todo.zugewiesen_nachname, 'h-8 w-8 rounded-full');
}

function createTodoItem(todo, index) {
    const isCompleted = todo.status === 'erledigt';
    
    let faelligDate = '';
    if (todo.faellig_am) {
        const faellig = parseLocalDate(todo.faellig_am);
        if (faellig) {
            faelligDate = faellig.toLocaleDateString('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }
    }
    
    const isFavorit = todo.favorit == 1 || todo.favorit === true;
    const hasZugewiesen = todo.zugewiesen_an != null && todo.zugewiesen_an !== '' && Number(todo.zugewiesen_an) !== 0
        && (todo.zugewiesen_vorname || todo.zugewiesen_nachname || todo.zugewiesen_logopfad);
    
    const isHighlight = highlightTodoId !== null && todo.id == highlightTodoId;
    const hasMeta = !!(
        (todo.project_id && (todo.project_nummer != null && todo.project_nummer !== '')) ||
        (todo.beschreibung && String(todo.beschreibung).trim()) ||
        todo.ticket_nummer ||
        faelligDate ||
        todo.company_name ||
        ((todo.attachment_count || 0) > 0)
    );
    return `
        <li class="todo-item relative overflow-hidden rounded-xl border touch-manipulation lg:shadow-sm lg:hover:shadow-md lg:transition-shadow ${isHighlight ? 'ring-2 ring-indigo-400 dark:ring-indigo-500 ring-inset border-transparent bg-indigo-50 dark:bg-indigo-500/15 max-lg:bg-indigo-50 max-lg:dark:bg-indigo-500/15' : 'border-gray-200 dark:border-primary-120'}" 
            data-todo-id="${todo.id}" data-zugewiesen-an="${todoSwipeCurrentAssigneeId(todo)}">
            <div class="todo-swipe-actions-layer absolute inset-0 z-0 flex flex-row lg:hidden opacity-0 pointer-events-none transition-opacity duration-150" aria-hidden="true">
                <div class="flex h-full min-h-0 w-[7rem] shrink-0">
                    <label class="todo-swipe-action relative flex flex-1 h-full min-w-0 cursor-pointer touch-manipulation items-center justify-center bg-sky-600 hover:bg-sky-700 text-white dark:bg-sky-700 dark:hover:bg-sky-600 rounded-l-xl border-0 p-0 m-0" data-swipe-act="due" onclick="event.stopPropagation();" aria-label="Fällig am" title="Fällig am">
                        <input type="date" class="todo-swipe-due-input absolute inset-0 z-10 h-full w-full min-h-[2.75rem] cursor-pointer border-0 p-0 m-0 bg-transparent text-base opacity-[0.04]" style="-webkit-appearance:none;appearance:none" data-todo-id="${todo.id}" autocomplete="off" />
                        <span class="pointer-events-none relative z-0 flex h-full w-full items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </span>
                    </label>
                    <label class="todo-swipe-action relative flex flex-1 h-full min-w-0 cursor-pointer touch-manipulation items-center justify-center bg-violet-600 hover:bg-violet-700 text-white dark:bg-violet-700 dark:hover:bg-violet-600 border-0 p-0 m-0" data-swipe-act="assign" onclick="event.stopPropagation();" aria-label="Bearbeiter" title="Bearbeiter">
                        <select class="todo-swipe-assign-select absolute inset-0 z-10 h-full w-full min-h-[2.75rem] cursor-pointer border-0 p-0 m-0 bg-transparent text-base opacity-[0.04] text-gray-900" style="-webkit-appearance:none;appearance:none" data-todo-id="${todo.id}" data-company-id="${todo.company_id != null && todo.company_id !== '' ? String(todo.company_id) : ''}" autocomplete="off">
                            <option value="">Laden…</option>
                        </select>
                        <span class="pointer-events-none relative z-0 flex h-full w-full items-center justify-center">
                            <svg class="w-6 h-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.079 6.839a3 3 0 0 0-4.255.1M13 20h1.083A3.916 3.916 0 0 0 18 16.083V9A6 6 0 1 0 6 9v7m7 4v-1a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1Zm-7-4v-6H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h1Zm12-6h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v-6Z"/></svg>
                        </span>
                    </label>
                </div>
                <div class="min-w-0 flex-1" aria-hidden="true"></div>
                <div class="flex h-full w-14 shrink-0">
                    <button type="button" class="todo-swipe-action flex h-full w-full items-center justify-center bg-red-600 text-white rounded-r-xl border-0 p-0" data-swipe-act="delete" onclick="event.stopPropagation(); todoSwipeDelete(${todo.id});" aria-label="Löschen" title="Löschen">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-1 14H6L5 7h14zM10 11v6m4-6v6M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
            <div class="todo-swipe-track relative z-[1] w-full min-w-0 cursor-pointer py-2 max-lg:ps-4 max-lg:pe-4 lg:py-1.5 lg:ps-1.5 lg:pe-2.5 lg:rounded-xl lg:dark:hover:bg-primary-140 ${isHighlight ? 'bg-indigo-50 dark:bg-indigo-500/15 max-lg:bg-indigo-50 max-lg:dark:bg-indigo-500/15' : 'bg-white dark:bg-primary-100'}" data-swipe-x="0" style="transform:translateZ(0) translateX(0)" onclick="todoSwipeTrackClick(${todo.id}, event)">
            <div class="flex items-center gap-4 lg:gap-2.5">
                <div class="hidden lg:flex lg:w-2.5 lg:shrink-0 lg:items-center lg:justify-center lg:self-center lg:py-0 lg:-ms-px text-gray-400" onmousedown="event.stopPropagation();" style="cursor: grab;" aria-hidden="true">
                    <i class="fas fa-grip-vertical text-[0.6875rem] leading-none"></i>
                </div>
                <div class="flex-shrink-0 self-center flex items-center justify-center py-1 pl-0 -my-1 max-lg:py-2 lg:py-0 lg:my-0" onclick="event.stopPropagation();">
                    <input type="checkbox" 
                           class="todo-checkbox w-5 h-5 max-lg:w-6 max-lg:h-6 text-neutral-primary border-default-medium bg-neutral-secondary-medium rounded-full checked:border-brand focus:ring-2 focus:outline-none focus:ring-brand-subtle border appearance-none touch-manipulation ${isCompleted ? 'checked:border-brand' : ''}" 
                           ${isCompleted ? 'checked' : ''}
                           onchange="event.stopPropagation(); toggleTodoStatus(${todo.id}, this.checked)"
                           title="${isCompleted ? 'Als offen markieren' : 'Als erledigt markieren'}">
                </div>
                <div class="flex-1 min-w-0 min-h-0 lg:ps-1.5">
                    <div class="flex items-center justify-between gap-2 max-lg:gap-2.5">
                        ${hasMeta ? `
                        <div class="flex flex-col min-w-0 gap-1 justify-center">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm font-medium leading-snug text-gray-900 dark:text-white ${isCompleted ? 'line-through opacity-60' : ''}">
                                    ${escapeHtml(todo.titel)}
                                </h3>
                            </div>
                            <div class="flex items-center gap-3 max-lg:gap-2 text-xs max-lg:text-[0.8125rem] text-gray-500 dark:text-gray-400 flex-wrap min-h-[1.25rem]">
                                ${(todo.project_id && (todo.project_nummer != null && todo.project_nummer !== '')) ? `<span class="whitespace-nowrap flex items-center gap-1">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 flex-shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/>
                                    </svg>
                                    <a href="<?php echo BASE_URL; ?>projects/view.php?id=${todo.project_id}" onclick="event.stopPropagation();" class="text-primary-600 hover:text-primary-900 dark:text-primary-400" title="${escapeHtml(todo.project_name || '')}">#${escapeHtml(todo.project_nummer)}</a>
                                </span>` : ''}
                                ${((todo.project_id && (todo.project_nummer != null && todo.project_nummer !== '')) && (todo.beschreibung && todo.beschreibung.trim() || todo.ticket_nummer || faelligDate || todo.company_name || (todo.attachment_count || 0) > 0)) ? `<span class="text-gray-400 dark:text-gray-500">|</span>` : ''}
                                ${todo.beschreibung && todo.beschreibung.trim() ? `<span class="whitespace-nowrap flex items-center gap-1">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                                    </svg>
                                    <span>Notiz</span>
                                </span>` : ''}
                                ${(todo.beschreibung && todo.beschreibung.trim() && (todo.ticket_nummer || faelligDate || todo.company_name || (todo.attachment_count || 0) > 0)) ? `<span class="text-gray-400 dark:text-gray-500">|</span>` : ''}
                                ${todo.ticket_nummer ? `<span class="whitespace-nowrap flex items-center gap-1">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/>
                                    </svg>
                                    <a href="<?php echo BASE_URL; ?>tickets/view.php?id=${todo.ticket_id}" onclick="event.stopPropagation();" class="text-primary-600 hover:text-primary-900 dark:text-primary-400">#${todo.ticket_nummer}</a>
                                </span>` : ''}
                                ${(todo.ticket_nummer && faelligDate) ? `<span class="text-gray-400 dark:text-gray-500">|</span>` : ''}
                                ${faelligDate ? `<span class="whitespace-nowrap flex items-center gap-1">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z"/>
                                    </svg>
                                    ${faelligDate}
                                </span>` : ''}
                                ${(faelligDate && (todo.company_name || (todo.attachment_count || 0) > 0)) ? `<span class="text-gray-400 dark:text-gray-500">|</span>` : ''}
                                ${todo.company_name ? `<span class="whitespace-nowrap flex items-center gap-1">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/>
                                    </svg>
                                    ${escapeHtml(todo.company_name)}
                                </span>` : ''}
                                ${(todo.company_name && (todo.attachment_count || 0) > 0) ? `<span class="text-gray-400 dark:text-gray-500">|</span>` : ''}
                                ${(todo.attachment_count || 0) > 0 ? `<span class="whitespace-nowrap flex items-center gap-1 text-gray-500 dark:text-gray-400">
                                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8v8a5 5 0 1 0 10 0V6.5a3.5 3.5 0 1 0-7 0V15a2 2 0 0 0 4 0V8"/>
                                    </svg>
                                    <span>${todo.attachment_count || 0}</span>
                                </span>` : ''}
                            </div>
                        </div>
                        ` : `
                        <div class="flex min-w-0 min-h-[2.75rem] items-center">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm font-medium leading-snug text-gray-900 dark:text-white ${isCompleted ? 'line-through opacity-60' : ''}">
                                    ${escapeHtml(todo.titel)}
                                </h3>
                            </div>
                        </div>
                        `}
                        <div class="flex-shrink-0 flex items-center gap-2 max-lg:gap-2.5" onclick="event.stopPropagation();">
                            ${hasZugewiesen ? renderTodoAssigneeAvatar(todo) : ''}
                            <button type="button" 
                                    onclick="toggleFavorite(${todo.id}, !${isFavorit})"
                                    class="inline-flex shrink-0 items-center justify-center py-1 -my-1 max-lg:py-2 rounded-lg hover:text-yellow-500 transition-colors touch-manipulation"
                                    title="${isFavorit ? 'Aus Favoriten entfernen' : 'Als Favorit markieren'}">
                                ${isFavorit ? `
                                    <svg class="w-5 h-5 max-lg:w-6 max-lg:h-6 text-yellow-500 fill-yellow-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                        <path d="M11.083 5.104c.35-.8 1.485-.8 1.834 0l1.752 4.022a1 1 0 0 0 .84.597l4.463.342c.9.069 1.255 1.2.556 1.771l-3.33 2.723a1 1 0 0 0-.337 1.016l1.03 4.119c.214.858-.71 1.552-1.474 1.106l-3.913-2.281a1 1 0 0 0-1.008 0L7.583 20.8c-.764.446-1.688-.248-1.474-1.106l1.03-4.119A1 1 0 0 0 6.8 14.56l-3.33-2.723c-.698-.571-.342-1.702.557-1.771l4.462-.342a1 1 0 0 0 .84-.597l1.753-4.022Z"/>
                                    </svg>
                                ` : `
                                    <svg class="w-5 h-5 max-lg:w-6 max-lg:h-6 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-width="2" d="M11.083 5.104c.35-.8 1.485-.8 1.834 0l1.752 4.022a1 1 0 0 0 .84.597l4.463.342c.9.069 1.255 1.2.556 1.771l-3.33 2.723a1 1 0 0 0-.337 1.016l1.03 4.119c.214.858-.71 1.552-1.474 1.106l-3.913-2.281a1 1 0 0 0-1.008 0L7.583 20.8c-.764.446-1.688-.248-1.474-1.106l1.03-4.119A1 1 0 0 0 6.8 14.56l-3.33-2.723c-.698-.571-.342-1.702.557-1.771l4.462-.342a1 1 0 0 0 .84-.597l1.753-4.022Z"/>
                                    </svg>
                                `}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </li>
    `;
}

function rerenderTodoListItem(todoId) {
    if (!todoId) return;
    var todo = todos.find(function(t) { return t.id == todoId; }) || closedTodos.find(function(t) { return t.id == todoId; });
    if (!todo) return;

    var q = getTodoListSearchQuery();
    if (q) {
        var inSearch = filterTodosArrayBySearch([todo], q).length > 0;
        var openList = document.getElementById('todosList');
        var closedList = document.getElementById('closedTodosList');
        var inOpenDom = !!(openList && openList.querySelector('.todo-item[data-todo-id="' + String(todoId) + '"]'));
        var inClosedDom = !!(closedList && closedList.querySelector('.todo-item[data-todo-id="' + String(todoId) + '"]'));
        if (inSearch !== inOpenDom || inSearch !== inClosedDom) {
            displayTodos(todos);
            if (typeof closedTodosExpanded !== 'undefined' && closedTodosExpanded) {
                displayClosedTodos(closedTodos);
                updateToggleClosedVisibility();
            }
            return;
        }
    }

    var html = createTodoItem(todo, 0);
    ['todosList', 'closedTodosList'].forEach(function(listId) {
        var listEl = document.getElementById(listId);
        if (!listEl) return;
        var oldEl = listEl.querySelector('.todo-item[data-todo-id="' + String(todoId) + '"]');
        if (!oldEl) return;
        var tmp = document.createElement('div');
        tmp.innerHTML = html.trim();
        var newEl = tmp.firstElementChild;
        if (newEl) oldEl.replaceWith(newEl);
    });

    initDragAndDrop();
}

function initDragAndDrop() {
    const todosListEl = document.getElementById('todosList');
    const todoItems = todosListEl ? todosListEl.querySelectorAll('.todo-item') : [];
    let draggedElement = null;
    
    todoItems.forEach(item => {
        const gripIcon = item.querySelector('.fa-grip-vertical');
        if (gripIcon) {
            gripIcon.addEventListener('mousedown', function(e) {
                e.stopPropagation();
            });
        }
        
        item.draggable = window.matchMedia('(min-width: 1024px)').matches;
        item.addEventListener('dragstart', function(e) {
            draggedElement = this;
            this.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.todoId || '');
        });
        
        item.addEventListener('dragend', function() {
            this.style.opacity = '1';
        });
        
        item.addEventListener('dragover', function(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            
            if (this !== draggedElement && draggedElement) {
                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                
                if (e.clientY < midpoint) {
                    this.parentNode.insertBefore(draggedElement, this);
                } else {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                }
            }
            return false;
        });
        
        item.addEventListener('drop', function(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            if (draggedElement !== this && draggedElement) {
                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                
                if (e.clientY < midpoint) {
                    this.parentNode.insertBefore(draggedElement, this);
                } else {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                }
                
                // Sortierung speichern
                saveTodoOrder();
            }
            
            return false;
        });
    });
}

let saveOrderTimeout = null;

function saveTodoOrder() {
    // Debounce: Warte kurz, bevor gespeichert wird (um mehrere aufeinanderfolgende Drags zu vermeiden)
    clearTimeout(saveOrderTimeout);
    
    saveOrderTimeout = setTimeout(() => {
        const todosListEl = document.getElementById('todosList');
        const todoItems = todosListEl ? todosListEl.querySelectorAll('.todo-item') : [];
        const todoIds = Array.from(todoItems).map(item => ({
            todo_id: parseInt(item.dataset.todoId)
        }));
        
        // folder_id: 0 bedeutet "ohne Ordner" -> null
        const folderId = currentFolderId === 0 ? null : currentFolderId;
        
        
        todosFetchJson(todosApiUrl, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                todos: todoIds,
                folder_id: folderId
            })
        })
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast('Sortierung erfolgreich gespeichert', 'success');
                }
            } else {
                console.error('Fehler beim Speichern der Sortierung:', data.error);
                if (typeof showToast === 'function') {
                    showToast('Fehler beim Speichern der Sortierung', 'error');
                }
                loadTodos(); // Neu laden bei Fehler
            }
        })
        .catch(error => {
            console.error('Fehler beim Speichern der Sortierung:', error);
            if (typeof showToast === 'function') {
                showToast('Fehler beim Speichern der Sortierung', 'error');
            }
            loadTodos(); // Neu laden bei Fehler
        });
    }, 300); // 300ms Debounce
}

function assignTodoToUser(todoId, userId) {
    var zNum = (userId === null || userId === undefined || userId === '') ? 0 : Number(userId);
    if (isNaN(zNum) || zNum < 0) zNum = 0;
    todosFetchJson(todosApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ todo_id: todoId, zugewiesen_an: (zNum === 0 ? null : zNum) })
    })
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(userId ? 'Aufgabe wurde zugewiesen' : 'Zuweisung entfernt', 'success');
            }
            loadTodos();
            loadFolders();
        } else if (typeof showToast === 'function') {
            showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(err => {
        if (typeof showToast === 'function') showToast('Fehler beim Zuweisen', 'error');
    });
}

function assignTodoToFolder(todoId, folderId) {
    todosFetchJson(todosApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ todo_id: todoId, folder_id: folderId })
    })
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                if (!folderId || folderId === 0) {
                    showToast('Ordnerzuordnung entfernt', 'success');
                } else {
                    const folder = folders.find(f => Number(f.id) === folderId);
                    showToast(`Aufgabe wurde in Ordner "${folder ? folder.name : ''}" verschoben`, 'success');
                }
            }
            loadFolders();
            loadTodos();
            updateFolderButtons();
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fehler beim Zuordnen zum Ordner:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Zuordnen zum Ordner', 'error');
        }
    });
}

var TODO_SWIPE_W_LEFT = 112;
var TODO_SWIPE_W_RIGHT = 56;

function todoSwipeGetTrackForTodo(todoId) {
    var li = document.querySelector('.todo-item[data-todo-id="' + String(todoId) + '"]');
    return li ? li.querySelector('.todo-swipe-track') : null;
}

var TODO_SWIPE_SNAP_EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';
var TODO_SWIPE_SNAP_MS = 340;

function todoSwipeSetTranslate(track, x, animate) {
    if (!track) return;
    var nx = Math.max(-TODO_SWIPE_W_RIGHT, Math.min(TODO_SWIPE_W_LEFT, x));
    track.dataset.swipeX = String(nx);
    if (animate) {
        track.style.transition = 'transform ' + TODO_SWIPE_SNAP_MS + 'ms ' + TODO_SWIPE_SNAP_EASE;
        track.style.willChange = 'transform';
    } else {
        track.style.transition = 'none';
        track.style.willChange = 'transform';
    }
    track.style.transform = 'translateZ(0) translateX(' + nx + 'px)';
    var li = track.closest('.todo-item');
    if (li) {
        var revealed = Math.abs(nx) > 0.01;
        li.classList.toggle('todo-item--swipe-revealed', revealed);
        var layer = li.querySelector('.todo-swipe-actions-layer');
        if (layer) layer.setAttribute('aria-hidden', revealed ? 'false' : 'true');
    }
    if (animate) {
        window.clearTimeout(track._todoSwipeSnapT);
        track._todoSwipeSnapT = window.setTimeout(function() {
            track.style.willChange = '';
            track._todoSwipeSnapT = null;
        }, TODO_SWIPE_SNAP_MS + 80);
    }
}

function todoSwipeResetAllTracks(exceptTrack) {
    document.querySelectorAll('.todo-swipe-track').forEach(function(tr) {
        if (exceptTrack && tr === exceptTrack) return;
        if (parseFloat(tr.dataset.swipeX || '0') !== 0) todoSwipeSetTranslate(tr, 0, true);
    });
}

function todoSwipeTrackClick(todoId, ev) {
    if (typeof window.__todoSwipeBlockClickUntil === 'number' && Date.now() < window.__todoSwipeBlockClickUntil) return;
    if (ev.target.closest('a[href]')) return;
    if (ev.target.closest('button')) return;
    if (ev.target.closest('input')) return;
    var track = ev.currentTarget;
    var off = parseFloat(track.dataset.swipeX || '0') || 0;
    if (off !== 0) {
        todoSwipeResetAllTracks(null);
        return;
    }
    openTodoDrawer(todoId);
}

function todoSwipeFindTodo(todoId) {
    return todos.find(function(t) { return t.id == todoId; }) || closedTodos.find(function(t) { return t.id == todoId; });
}

function todoSwipeAssignableCacheKey(companyId) {
    return companyId != null && companyId !== '' && !isNaN(Number(companyId)) ? String(companyId) : 'null';
}

function fetchAssignableUsersForCompany(companyId) {
    var key = todoSwipeAssignableCacheKey(companyId);
    if (!window.__todoAssignableUsersCache) window.__todoAssignableUsersCache = {};
    if (window.__todoAssignableUsersCache[key] !== undefined) {
        return Promise.resolve(window.__todoAssignableUsersCache[key]);
    }
    var url = todosApiUrl + '?action=assignable_users';
    if (companyId != null && companyId !== '' && !isNaN(Number(companyId))) {
        url += '&company_id=' + encodeURIComponent(String(companyId));
    }
    return todosFetchJson(url)
        .then(function(data) {
            var users = (data.success && data.users) ? data.users : [];
            window.__todoAssignableUsersCache[key] = users;
            return users;
        })
        .catch(function() {
            window.__todoAssignableUsersCache[key] = [];
            return [];
        });
}

function prefetchAssignableUsersForTodos() {
    var seen = {};
    function addFrom(list) {
        if (!list) return;
        list.forEach(function(t) {
            var k = todoSwipeAssignableCacheKey(t.company_id);
            seen[k] = t.company_id;
        });
    }
    addFrom(todos);
    addFrom(closedTodos);
    Object.keys(seen).forEach(function(k) {
        fetchAssignableUsersForCompany(seen[k]);
    });
}

function todoSwipeCurrentAssigneeId(todo) {
    if (!todo) return 0;
    var z = todo.zugewiesen_an;
    if (z == null || z === '' || z === false) return 0;
    if (String(z) === '0') return 0;
    var n = Number(z);
    if (isNaN(n) || n <= 0) return 0;
    return n;
}

/** Zuverlässige Quelle für „aktuell zugewiesen“: Todo-Objekt zuerst, bei 0 data-zugewiesen-an (veraltetes Objekt vs. Karte) */
function todoSwipeResolveCurrentAssigneeId(sel, todo) {
    var fromTodo = todoSwipeCurrentAssigneeId(todo);
    var fromDom = 0;
    var li = sel && sel.closest ? sel.closest('.todo-item') : null;
    if (li && li.hasAttribute('data-zugewiesen-an')) {
        var raw = li.getAttribute('data-zugewiesen-an');
        if (raw != null && raw !== '' && String(raw) !== '0') {
            var dn = parseInt(raw, 10);
            if (!isNaN(dn) && dn > 0) fromDom = dn;
        }
    }
    return fromTodo > 0 ? fromTodo : fromDom;
}

function todoSwipeWantAssignSelectValue(todo) {
    var id = todoSwipeCurrentAssigneeId(todo);
    return id === 0 ? '' : String(id);
}

function fillTodoSwipeAssignSelectOptions(sel) {
    if (!sel || !sel.classList.contains('todo-swipe-assign-select')) return;
    var tid = parseInt(sel.dataset.todoId, 10);
    if (!tid) return;
    var rawCid = sel.dataset.companyId;
    var cid = rawCid === '' || rawCid === undefined ? null : parseInt(rawCid, 10);
    var key = todoSwipeAssignableCacheKey(cid);
    var users = window.__todoAssignableUsersCache && window.__todoAssignableUsersCache[key];
    if (users === undefined) {
        sel.innerHTML = '<option value="">Laden…</option>';
        fetchAssignableUsersForCompany(cid).then(function() {
            if (sel.isConnected) fillTodoSwipeAssignSelectOptions(sel);
        });
        return;
    }
    var todo = todoSwipeFindTodo(tid);
    var want = todoSwipeWantAssignSelectValue(todo);
    sel.innerHTML = '';
    var opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = 'Nicht zugewiesen';
    sel.appendChild(opt0);
    users.forEach(function(u) {
        var opt = document.createElement('option');
        opt.value = String(u.id);
        opt.textContent = [u.vorname, u.nachname].filter(Boolean).join(' ') || u.email || ('ID ' + u.id);
        sel.appendChild(opt);
    });
    /* Aktueller Bearbeiter muss als Option existieren, sonst bleibt value oft "" und „Nicht zugewiesen“ löst kein change (iOS) */
    if (want !== '' && want !== '0') {
        var hasWant = false;
        for (var oi = 0; oi < sel.options.length; oi++) {
            if (sel.options[oi].value === want) { hasWant = true; break; }
        }
        if (!hasWant && todo) {
            var optMiss = document.createElement('option');
            optMiss.value = want;
            var label = [todo.zugewiesen_vorname, todo.zugewiesen_nachname].filter(Boolean).join(' ').trim();
            optMiss.textContent = label || ('Bearbeiter #' + want);
            sel.appendChild(optMiss);
        }
    }
    sel.value = want;
}

function todoSwipePrefillAssignSelect(el) {
    if (!el || !el.classList.contains('todo-swipe-assign-select')) return;
    var o0 = el.options[0];
    var looksReady = o0 && o0.textContent !== 'Laden…' && (el.options.length >= 1);
    if (looksReady) {
        var tid = parseInt(el.dataset.todoId, 10);
        var todo = todoSwipeFindTodo(tid);
        var want = todoSwipeWantAssignSelectValue(todo);
        if (el.value !== want) el.value = want;
        return;
    }
    fillTodoSwipeAssignSelectOptions(el);
}

var _todoSwipeAssignLastApply = { t: 0, tid: 0, uid: -1 };

/**
 * Select-Wert mit Todo (Server-Stand im Speicher) abgleichen.
 * Wichtig: Kein reiner Snapshot-Vergleich — wenn „Nicht zugewiesen“ und Bearbeiter-ID schon beide "",
 * feuert iOS/Android oft kein change; Vergleich zu todo erkennt trotzdem nötigen PUT.
 */
function todoSwipeApplyAssignFromSelect(sel) {
    if (!sel) return;
    if (!sel.isConnected) return;
    var tid = parseInt(sel.dataset.todoId, 10);
    if (!tid) return;
    var o0 = sel.options[0];
    if (o0 && o0.textContent === 'Laden…') return;
    var todo = todoSwipeFindTodo(tid);
    if (!todo) return;
    var rawVal = sel.value;
    var curUid = (rawVal === '' || rawVal === null || rawVal === undefined || rawVal === '0') ? 0 : parseInt(rawVal, 10);
    if (isNaN(curUid)) curUid = 0;
    var wantUid = todoSwipeResolveCurrentAssigneeId(sel, todo);
    if (curUid === wantUid) {
        todoSwipeResetAllTracks(null);
        return;
    }
    var now = Date.now();
    if (_todoSwipeAssignLastApply.tid === tid && _todoSwipeAssignLastApply.uid === curUid && now - _todoSwipeAssignLastApply.t < 700) {
        return;
    }
    _todoSwipeAssignLastApply = { t: now, tid: tid, uid: curUid };
    todoSwipeResetAllTracks(null);
    assignTodoToUser(tid, curUid === 0 ? null : curUid);
}

function todoSwipePrefillDueDateInput(el) {
    if (!el || !el.classList.contains('todo-swipe-due-input')) return;
    var tid = parseInt(el.dataset.todoId, 10);
    if (!tid) return;
    el._todoDueOpenedAt = Date.now();
    el._todoDueSilenceChange = true;
    var todo = todoSwipeFindTodo(tid);
    var want = '';
    if (todo && todo.faellig_am) {
        var dt = parseLocalDate(todo.faellig_am);
        want = dt ? formatDateForInput(dt) : '';
    }
    if (el.value !== want) el.value = want;
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            if (!el.isConnected) return;
            if (el.value !== want) el.value = want;
        });
    });
    window.setTimeout(function() {
        el._todoDueSilenceChange = false;
    }, 320);
}

function todoSwipeTodoHasFaellig(todo) {
    return !!(todo && todo.faellig_am && String(todo.faellig_am).trim() !== '');
}

function todoSwipeApplyDueDateFromSwipeInput(inp) {
    if (!inp) return;
    if (inp._todoDueSilenceChange) return;
    var tid = parseInt(inp.dataset.todoId, 10);
    var v = String(inp.value || '').trim();
    if (!tid) return;
    var todo = todoSwipeFindTodo(tid);
    /* Ohne bestehenden Termin: leeres change beim Öffnen ignorieren. */
    if (!v && !todoSwipeTodoHasFaellig(todo)) {
        return;
    }
    /* Bereits gespeichertes Kalendertag — kein erneutes Speichern (vermeidet Toast durch Prefill-change). */
    if (v && todoSwipeTodoHasFaellig(todo)) {
        var ex = parseLocalDate(todo.faellig_am);
        if (ex && formatDateForInput(ex) === v) {
            return;
        }
    }
    /* Aufgabe ohne Termin: „Zurücksetzen“/Picker-Quirks liefern oft kurz „heute“ statt leer — nicht als echtes Datum speichern. */
    if (v && !todoSwipeTodoHasFaellig(todo) && inp._todoDueOpenedAt != null) {
        var ms = Date.now() - inp._todoDueOpenedAt;
        var todayStr = formatDateForInput(new Date());
        if (v === todayStr && ms < 1800) {
            inp.value = '';
            return;
        }
    }
    todoSwipeResetAllTracks(null);
    if (!v) {
        todosFetchJson(todosApiUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ todo_id: tid, faellig_am: null })
        })
        .then(function(data) {
            if (data.success) {
                if (typeof showToast === 'function') showToast('Fälligkeit entfernt', 'success');
                loadTodos();
                loadFolders();
            } else if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || ''), 'error');
            }
        })
        .catch(function() {
            if (typeof showToast === 'function') showToast('Fehler beim Entfernen', 'error');
        });
    } else {
        var d = parseLocalDate(v + ' 12:00:00');
        if (d && !isNaN(d.getTime())) setTodoDueDate(tid, d);
    }
}

function todoSwipeDelete(todoId) {
    todoSwipeResetAllTracks(null);
    deleteTodo(todoId);
}

(function initTodoMobileSwipeGestures() {
    var swipeState = null;

    function isMobileSwipe() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function onTouchStart(e) {
        if (!isMobileSwipe()) return;
        var track = e.target.closest('.todo-swipe-track');
        if (!track) return;
        if (e.target.closest('.todo-swipe-action')) return;
        if (e.target.closest('a[href], button, input, label')) return;
        var t = e.changedTouches[0];
        todoSwipeResetAllTracks(track);
        swipeState = {
            track: track,
            startX: t.clientX,
            startY: t.clientY,
            startOff: parseFloat(track.dataset.swipeX || '0') || 0,
            moved: false
        };
    }

    function onTouchMove(e) {
        if (!swipeState || !swipeState.track) return;
        var t = e.changedTouches[0];
        var dx = t.clientX - swipeState.startX;
        var dy = t.clientY - swipeState.startY;
        if (!swipeState.moved && Math.abs(dx) < 10 && Math.abs(dy) < 10) return;
        if (!swipeState.moved && Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 12) {
            swipeState = null;
            return;
        }
        if (Math.abs(dx) >= 10 || swipeState.moved) {
            if (!swipeState.moved) {
                if (Math.abs(dx) > Math.abs(dy)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            } else {
                e.preventDefault();
                e.stopPropagation();
            }
            swipeState.moved = true;
            var nx = swipeState.startOff + dx;
            if (nx > TODO_SWIPE_W_LEFT) nx = TODO_SWIPE_W_LEFT + (nx - TODO_SWIPE_W_LEFT) * 0.12;
            if (nx < -TODO_SWIPE_W_RIGHT) nx = -TODO_SWIPE_W_RIGHT + (nx + TODO_SWIPE_W_RIGHT) * 0.12;
            todoSwipeSetTranslate(swipeState.track, nx, false);
        }
    }

    function onTouchEnd() {
        if (!swipeState || !swipeState.track) {
            swipeState = null;
            return;
        }
        var tr = swipeState.track;
        var off = parseFloat(tr.dataset.swipeX || '0') || 0;
        var nx = 0;
        if (off < -TODO_SWIPE_W_RIGHT / 2) nx = -TODO_SWIPE_W_RIGHT;
        else if (off > TODO_SWIPE_W_LEFT / 2) nx = TODO_SWIPE_W_LEFT;
        else nx = 0;
        tr.style.willChange = '';
        todoSwipeSetTranslate(tr, nx, true);
        if (swipeState.moved) window.__todoSwipeBlockClickUntil = Date.now() + 320;
        swipeState = null;
    }

    document.addEventListener('touchstart', onTouchStart, { capture: true, passive: true });
    document.addEventListener('touchmove', onTouchMove, { capture: true, passive: false });
    document.addEventListener('touchend', onTouchEnd, { capture: true, passive: true });
    document.addEventListener('touchcancel', onTouchEnd, { capture: true, passive: true });
})();

document.addEventListener('focusin', function(e) {
    if (!e.target || !e.target.classList) return;
    if (e.target.classList.contains('todo-swipe-due-input')) {
        todoSwipePrefillDueDateInput(e.target);
    } else if (e.target.classList.contains('todo-swipe-assign-select')) {
        todoSwipePrefillAssignSelect(e.target);
    }
}, true);

document.addEventListener('change', function(e) {
    if (!e.target || !e.target.classList) return;
    if (e.target.classList.contains('todo-swipe-due-input')) {
        todoSwipeApplyDueDateFromSwipeInput(e.target);
    } else if (e.target.classList.contains('todo-swipe-assign-select')) {
        todoSwipeApplyAssignFromSelect(e.target);
    }
});

/* Mobil: Native Picker schließt oft ohne zuverlässiges change (v. a. wenn alter/new beide "" oder gleicher Index) */
document.addEventListener('blur', function(e) {
    if (!e.target || !e.target.classList || !e.target.classList.contains('todo-swipe-assign-select')) return;
    window.setTimeout(function() {
        if (!e.target.isConnected) return;
        todoSwipeApplyAssignFromSelect(e.target);
    }, 0);
}, true);

function openTodoModal(todo = null) {
    const modal = document.getElementById('todoModal');
    const form = document.getElementById('todoForm');
    const title = document.getElementById('modalTitle');
    
    if (todo) {
        title.textContent = 'Aufgabe bearbeiten';
        document.getElementById('todo_id').value = todo.id;
        document.getElementById('titel').value = todo.titel;
        document.getElementById('beschreibung').value = todo.beschreibung || '';
        if (todo.faellig_am) {
            const date = parseLocalDate(todo.faellig_am);
            if (date) {
                const y = date.getFullYear(), m = String(date.getMonth() + 1).padStart(2, '0'), d = String(date.getDate()).padStart(2, '0');
                const h = String(date.getHours()).padStart(2, '0'), min = String(date.getMinutes()).padStart(2, '0');
                document.getElementById('faellig_am').value = y + '-' + m + '-' + d + 'T' + h + ':' + min;
            } else {
                document.getElementById('faellig_am').value = '';
            }
        } else {
            document.getElementById('faellig_am').value = '';
        }
    } else {
        title.textContent = 'Neue Aufgabe erstellen';
        form.reset();
        document.getElementById('todo_id').value = '';
    }
    
    modal.classList.remove('hidden');
}

function hapticTodoMarkDone() {
    if (typeof window.hapticLightTap === 'function') {
        window.hapticLightTap();
    }
}

function toggleTodoStatus(todoId, isChecked) {
    const newStatus = isChecked ? 'erledigt' : 'offen';
    if (isChecked) {
        hapticTodoMarkDone();
    }

    todosFetchJson(todosApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            todo_id: parseInt(todoId),
            status: newStatus
        })
    })
    .then(data => {
        if (data.success) {
            // Todo in der Liste aktualisieren
            const todo = todos.find(t => t.id == todoId);
            if (todo) {
                todo.status = newStatus;
            }
            var navDone = document.getElementById('navMobileTodoDrawerDoneCb');
            if (navDone && typeof currentDrawerTodoId !== 'undefined' && currentDrawerTodoId == todoId) {
                navDone.checked = (newStatus === 'erledigt');
            }
            // UI aktualisieren (Ordner-Zähler + Aufgabenliste + Sidebar-Zähler)
            loadFolders();
            loadTodos();
            updateSidebarTodosCount();
            if (newStatus === 'erledigt' && typeof playTaskCompletedSound === 'function') {
                playTaskCompletedSound();
            }
            if (typeof showToast === 'function') {
                showToast(`Aufgabe wurde auf "${newStatus === 'erledigt' ? 'Erledigt' : 'Offen'}" gesetzt`, 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
            loadTodos(); // Zurücksetzen bei Fehler
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Ändern des Status', 'error');
        } else {
            alert('Fehler beim Ändern des Status');
        }
        loadTodos(); // Zurücksetzen bei Fehler
    });
}

function closeTodoModal() {
    document.getElementById('todoModal').classList.add('hidden');
    document.getElementById('todoForm').reset();
}

/** Verhindert doppeltes Absenden (z. B. mehrfaches Enter bei langsamer Verbindung). */
let todoFormSaveInProgress = false;

function saveTodo() {
    if (todoFormSaveInProgress) return;

    // company_id aus localStorage lesen
    let companyId = null;
    try {
        const savedSelection = localStorage.getItem('selectedUserOption');
        if (savedSelection) {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0') {
                companyId = parseInt(data.id);
            }
        }
    } catch (e) {
        console.error('Fehler beim Lesen der company_id aus localStorage:', e);
    }
    
    const formData = {
        titel: document.getElementById('titel').value,
        beschreibung: document.getElementById('beschreibung').value,
        status: 'offen', // Neue Todos sind immer offen
        faellig_am: document.getElementById('faellig_am').value || null,
        folder_id: currentFolderId === 0 ? null : currentFolderId,
        company_id: companyId
    };
    
    const todoId = document.getElementById('todo_id').value;
    
    if (todoId) {
        formData.todo_id = parseInt(todoId);
    }

    todoFormSaveInProgress = true;
    todosFetchJson(todosApiUrl, {
        method: todoId ? 'PUT' : 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            closeTodoModal();
            loadTodos();
            if (!todoId) {
                loadFolders(); // Ordneranzahl aktualisieren
                updateFolderButtons();
            }
            updateSidebarTodosCount();
            if (typeof showToast === 'function') {
                showToast(todoId ? 'Aufgabe erfolgreich aktualisiert' : 'Aufgabe erfolgreich erstellt', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Speichern der Aufgabe', 'error');
        } else {
            alert('Fehler beim Speichern der Aufgabe');
        }
    })
    .finally(function() {
        todoFormSaveInProgress = false;
    });
}

let currentDrawerTodoId = null;

/**
 * Mobil: #todoDrawer wie #drawerBackdrop direkt unter document.body halten.
 * Liegt der Drawer in .app-layout-shell und wird per transform verschoben, kann WebKit/iOS
 * beim Loslassen die Composite-Order verwerfen — dann wirkt kurz helles Drawer-Weiß über der schwarzen Top-Nav.
 */
function portalTodoDrawerToBodyIfMobile() {
    var drawer = document.getElementById('todoDrawer');
    if (!drawer || !window.matchMedia('(max-width: 1023px)').matches) return;
    if (drawer.parentNode === document.body) return;
    var anchor = document.getElementById('todoDrawerMountAnchor');
    if (!anchor) {
        anchor = document.createElement('div');
        anchor.id = 'todoDrawerMountAnchor';
        anchor.setAttribute('aria-hidden', 'true');
        anchor.className = 'hidden';
        drawer.parentNode.insertBefore(anchor, drawer);
    }
    document.body.appendChild(drawer);
}

function restoreTodoDrawerFromBodyIfMobile() {
    var drawer = document.getElementById('todoDrawer');
    var anchor = document.getElementById('todoDrawerMountAnchor');
    if (!drawer || !anchor || !anchor.parentNode) return;
    if (drawer.parentNode !== document.body) return;
    anchor.parentNode.insertBefore(drawer, anchor);
}

function openTodoDrawer(todoId) {
    const todo = todos.find(t => t.id == todoId) || closedTodos.find(t => t.id == todoId);
    if (!todo) {
        console.error('Todo nicht gefunden:', todoId);
        return;
    }
    
    currentDrawerTodoId = todoId;
    hideDrawerSaveIndicator();
    const drawer = document.getElementById('todoDrawer');
    const drawerContent = document.getElementById('drawerContent');
    
    drawer.setAttribute('data-todo-id', todoId);
    
    // Drawer sichtbar machen
    drawer.classList.remove('todo-drawer-hidden');
    drawer.style.transform = 'translate3d(0,0,0)';
    drawer.setAttribute('aria-hidden', 'false');
    portalTodoDrawerToBodyIfMobile();
    applyTodosDrawerNavMode(todo);

    // Drawer Content füllen
    const isDesktopDrawer = window.matchMedia('(min-width: 1024px)').matches;
    const statusColors = {
        'offen': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'erledigt': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    };
    
    const statusText = {
        'offen': 'Offen',
        'erledigt': 'Erledigt'
    };
    
    let faelligDate = '';
    let faelligTime = '';
    if (todo.faellig_am) {
        const faellig = parseLocalDate(todo.faellig_am);
        if (faellig) {
            faelligDate = faellig.toLocaleDateString('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            faelligTime = faellig.toLocaleTimeString('de-DE', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
    
    const erstelltDate = todo.erstellt_datum ? new Date(todo.erstellt_datum).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) : '';
    
    const erledigtDate = todo.erledigt_datum ? new Date(todo.erledigt_datum).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) : '';
    
    const faelligValue = todo.faellig_am ? formatDateForInput(parseLocalDate(todo.faellig_am)) : '';
    
    drawerContent.innerHTML = `
        <form id="drawerTodoForm">
            <input type="hidden" id="drawer_todo_id" value="${todo.id}">
            <div class="space-y-4">
                ${isDesktopDrawer ? `
                <div class="min-w-0">
                    <input type="hidden" id="drawer_titel" value="${escapeHtml(todo.titel)}">
                    <div id="drawer_titel_inline_wrap" class="min-w-0">
                        <button type="button" id="drawer_titel_display" aria-label="Titel bearbeiten"
                                class="w-full text-left text-lg font-bold tracking-tight text-gray-900 dark:text-primary-200 leading-snug rounded-xl px-1 -mx-1 py-1.5 hover:bg-gray-100/90 dark:hover:bg-primary-300/35 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/45 dark:focus-visible:ring-primary-400/35">
                            ${escapeHtml(todo.titel != null ? String(todo.titel) : '')}
                        </button>
                        <label for="drawer_titel_edit" class="sr-only">Titel</label>
                        <input type="text" id="drawer_titel_edit" value="${escapeHtml(todo.titel != null ? String(todo.titel) : '')}" autocomplete="off"
                               class="hidden w-full text-lg font-bold tracking-tight text-gray-900 dark:text-primary-200 bg-gray-50 dark:bg-primary-300/45 border border-primary-500/35 dark:border-primary-400/40 rounded-xl px-2.5 py-2 shadow-sm focus:ring-2 focus:ring-primary-500/35 focus:border-primary-500 dark:focus:ring-primary-400/30">
                    </div>
                </div>
                ` : `
                <input type="hidden" id="drawer_titel" value="${escapeHtml(todo.titel)}">
                `}
                <div>
                    <label for="drawer_beschreibung" class="block mb-2 text-sm font-medium text-gray-900 dark:text-primary-200">Notiz</label>
                    <textarea id="drawer_beschreibung" rows="6"
                              class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-primary-500 focus:border-primary-500 dark:bg-primary-300/50 dark:border-primary-320 dark:placeholder-primary-250 dark:text-primary-200 dark:focus:ring-primary-400/30 dark:focus:border-primary-400">${todo.beschreibung ? escapeHtml(todo.beschreibung) : ''}</textarea>
                </div>
                
                <div>
                    <label for="drawer_faellig_am" class="block mb-2 text-sm font-medium text-gray-900 dark:text-primary-200">Fällig am</label>
                    <input type="date" id="drawer_faellig_am" value="${faelligValue}" autocomplete="off"
                           class="datetime-picker-only bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-primary-300/50 dark:border-primary-320 dark:placeholder-primary-250 dark:text-primary-200 dark:focus:ring-primary-400/30 dark:focus:border-primary-400">
                </div>
                
                <div>
                    <label class="block mb-2.5 text-sm font-medium text-gray-900 dark:text-primary-200" for="drawer_file_upload">Dateien anhängen</label>
                    <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-primary-200 focus:outline-none dark:bg-primary-300/50 dark:border-primary-320 dark:placeholder-primary-250 file:mr-4 file:py-2.5 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-700 file:text-white hover:file:bg-primary-800 dark:file:bg-primary-420 dark:hover:file:bg-primary-440 file:cursor-pointer dark:focus:ring-2 dark:focus:ring-primary-400/30" aria-describedby="drawer_file_upload_help" id="drawer_file_upload" type="file" multiple>
                    <p class="mt-1 text-sm text-gray-500 dark:text-primary-240" id="drawer_file_upload_help">Beliebige Dateiformate (PDF, Bilder, Dokumente, etc.).</p>
                    <div id="drawer_attachments_list" class="mt-3 space-y-2">
                        <!-- Anhänge werden hier dynamisch eingefügt -->
                    </div>
                </div>
            
                ${todo.project_id ? `
                <div>
                    <div class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5 dark:bg-primary-300/50 dark:border-primary-320 dark:text-primary-200" role="group" aria-label="Projekt">
                        <a href="<?php echo BASE_URL; ?>projects/view.php?id=${todo.project_id}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 font-medium">
                            ${(todo.project_nummer != null && todo.project_nummer !== '') ? 'Projekt #' + escapeHtml(todo.project_nummer) : 'Projekt'}
                        </a>
                        ${todo.project_name ? `<div class="text-xs text-gray-500 dark:text-primary-240 mt-1">${escapeHtml(todo.project_name)}</div>` : ''}
                    </div>
                </div>
                ` : ''}
                ${todo.ticket_nummer ? `
                <div>
                    <div class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5 dark:bg-primary-300/50 dark:border-primary-320 dark:text-primary-200" role="group" aria-label="Ticket">
                        <a href="<?php echo BASE_URL; ?>tickets/view.php?id=${todo.ticket_id}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 font-medium">
                            Ticket #${todo.ticket_nummer}
                        </a>
                        ${todo.ticket_titel ? `<div class="text-xs text-gray-500 dark:text-primary-240 mt-1">${escapeHtml(todo.ticket_titel)}</div>` : ''}
                    </div>
                </div>
                ` : ''}
                ${(todo.company_id != null && todo.company_id !== '' && Number(todo.company_id) !== 0) || (todo.company_name && String(todo.company_name).trim()) ? `
                <div>
                    <div class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5 dark:bg-primary-300/50 dark:border-primary-320 dark:text-primary-200" role="group" aria-label="Firma">
                        <span class="inline-flex items-center gap-2 font-medium text-gray-900 dark:text-primary-200">
                            <svg class="w-4 h-4 shrink-0 text-gray-500 dark:text-primary-240" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            ${(todo.company_name && String(todo.company_name).trim()) ? escapeHtml(String(todo.company_name).trim()) : '—'}
                        </span>
                    </div>
                </div>
                ` : ''}
                
                <div class="grid grid-cols-2 gap-3">
                    <div class="relative min-h-[7.5rem] rounded-xl border border-gray-200/90 bg-gray-100 dark:bg-primary-300/35 dark:border-primary-120 overflow-hidden transition-colors hover:bg-gray-200/70 dark:hover:bg-primary-300/50">
                        <select id="drawer_folder_id"
                               class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0 text-base"
                               style="-webkit-appearance: menulist; appearance: menulist;"
                               aria-label="Ordner wählen">
                            <option value="">Kein Ordner</option>
                        </select>
                        <div class="pointer-events-none flex flex-col items-center justify-center gap-2 px-2 py-4 min-h-[7.5rem]">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/95 dark:bg-primary-100/50 text-primary-600 dark:text-primary-300 shadow-sm ring-1 ring-gray-200/80 dark:ring-primary-120">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            </span>
                            <span id="drawer_folder_card_text" class="text-center text-xs font-medium leading-snug text-gray-800 dark:text-primary-200 line-clamp-2 break-words w-full px-0.5">Kein Ordner</span>
                        </div>
                    </div>
                    <div class="relative min-h-[7.5rem] rounded-xl border border-gray-200/90 bg-gray-100 dark:bg-primary-300/35 dark:border-primary-120 overflow-hidden transition-colors hover:bg-gray-200/70 dark:hover:bg-primary-300/50">
                        <select id="drawer_zugewiesen_an"
                               class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0 text-base"
                               style="-webkit-appearance: menulist; appearance: menulist;"
                               aria-label="Bearbeiter wählen">
                            <option value="">Nicht zugewiesen</option>
                        </select>
                        <div class="pointer-events-none flex flex-col items-center justify-center gap-2 px-2 py-4 min-h-[7.5rem]">
                            <span id="drawer_assignee_card_icon_wrap" class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/95 dark:bg-primary-100/50 text-gray-600 dark:text-primary-200 shadow-sm ring-1 ring-gray-200/80 dark:ring-primary-120">
                                <span id="drawer_assignee_card_avatar_inner" class="flex h-full w-full min-h-[2.75rem] min-w-[2.75rem] items-center justify-center">${(todo.zugewiesen_an != null && todo.zugewiesen_an !== '' && Number(todo.zugewiesen_an) !== 0) ? getAssigneeAvatarInnerHtml(todo.zugewiesen_logopfad || null, todo.zugewiesen_vorname, todo.zugewiesen_nachname, 'h-11 w-11 rounded-full') : getDrawerAssigneeDefaultIconHtml()}</span>
                            </span>
                            <span id="drawer_assignee_card_text" class="text-center text-xs font-medium leading-snug text-gray-800 dark:text-primary-200 line-clamp-2 break-words w-full px-0.5">${escapeHtml([todo.zugewiesen_vorname, todo.zugewiesen_nachname].filter(Boolean).join(' ').trim() || 'Nicht zugewiesen')}</span>
                        </div>
                    </div>
                </div>
                
                ${erledigtDate ? `
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-primary-200">Erledigt am</label>
                    <div class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5 dark:bg-primary-300/50 dark:border-primary-320 dark:text-primary-200">
                        ${erledigtDate}
                    </div>
                </div>
                ` : ''}
            </div>
        </form>
    `;
    
    // Erstellungsdatum in Footer einfügen
    const drawerCreatedDate = document.getElementById('drawerCreatedDate');
    if (drawerCreatedDate && erstelltDate) {
        drawerCreatedDate.innerHTML = `<span class="text-xs text-gray-400 dark:text-primary-240">Erstellt am ${erstelltDate}</span>`;
    } else if (drawerCreatedDate) {
        drawerCreatedDate.innerHTML = '';
    }
    
    // Ordner-Select füllen
    const folderSelect = document.getElementById('drawer_folder_id');
    if (folderSelect) {
        // Aktuellen Wert setzen
        const currentFolderId = todo.folder_id || null;
        folderSelect.innerHTML = '<option value="">Kein Ordner</option>';
        folders.forEach(folder => {
            const option = document.createElement('option');
            option.value = folder.id;
            option.textContent = folder.name;
            if (folder.id == currentFolderId) {
                option.selected = true;
            }
            folderSelect.appendChild(option);
        });
        // Wenn keine Ordner-ID vorhanden ist, "Kein Ordner" auswählen
        if (!currentFolderId || currentFolderId === 0) {
            folderSelect.value = '';
        }
        syncDrawerFolderCard();
    }
    
    // Benutzer-Select füllen
    loadUsersForDrawer(todo.company_id, todo.zugewiesen_an);
    
    // Anhänge laden
    loadTodoAttachments(todo.id);
    
    // Date-Picker im Drawer: nur Picker öffnen, keine Tastatureingabe
    initDatetimePickerOnly(drawerContent);
    
    // Autosave: bei Änderungen im Drawer automatisch speichern
    setupDrawerAutosave();
    syncTodosDrawerHiddenTitleFromNav();

    // Backdrop hinzufügen
    let backdrop = document.getElementById('drawerBackdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'drawerBackdrop';
        backdrop.className = 'fixed inset-0 bg-gray-900 bg-opacity-50 z-30';
        backdrop.addEventListener('click', closeTodoDrawer);
        document.body.appendChild(backdrop);
    }
    backdrop.classList.remove('hidden');

    window._drawerOpenedAt = Date.now();
}

function closeTodoDrawer() {
    if (drawerAutosaveTimeout) {
        clearTimeout(drawerAutosaveTimeout);
        drawerAutosaveTimeout = null;
    }
    hideDrawerSaveIndicator();
    const drawer = document.getElementById('todoDrawer');
    if (!drawer) return;

    const backdrop = document.getElementById('drawerBackdrop');
    var isMobile = window.matchMedia('(max-width: 1023px)').matches;
    var portaled = drawer.parentNode === document.body;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function cleanupAfterCloseAnimation() {
        currentDrawerTodoId = null;
        clearTodosDrawerNavMode();
        restoreTodoDrawerFromBodyIfMobile();
        if (drawer.dataset && drawer.dataset.todoDrawerCloseAnim) {
            delete drawer.dataset.todoDrawerCloseAnim;
        }
        if (highlightTodoId !== null) {
            highlightTodoId = null;
            displayTodos(todos);
            const closedList = document.getElementById('closedTodosList');
            if (closedList && closedTodos.length > 0) {
                closedList.innerHTML = closedTodos.map((todo, index) => createTodoItem(todo, index)).join('');
            }
        }
    }

    if (drawer.dataset && drawer.dataset.todoDrawerCloseAnim === '1') {
        return;
    }

    drawer.classList.add('todo-drawer-hidden');
    drawer.style.transform = '';
    drawer.setAttribute('aria-hidden', 'true');

    /* Mobil + an body portaliert: Erst nach transform-Transition aufräumen — sonst bricht die Schließ-Animation (DOM-Move / Nav) */
    if (isMobile && portaled && !reduceMotion) {
        drawer.dataset.todoDrawerCloseAnim = '1';
        var finished = false;
        var fallbackTimer = null;
        function finish() {
            if (finished) return;
            finished = true;
            drawer.removeEventListener('transitionend', onTransEnd);
            if (fallbackTimer !== null) window.clearTimeout(fallbackTimer);
            if (backdrop) backdrop.classList.add('hidden');
            cleanupAfterCloseAnimation();
        }
        function onTransEnd(e) {
            if (e.target !== drawer || e.propertyName !== 'transform') return;
            finish();
        }
        drawer.addEventListener('transitionend', onTransEnd);
        fallbackTimer = window.setTimeout(finish, 560);
        return;
    }

    if (backdrop) backdrop.classList.add('hidden');
    cleanupAfterCloseAnimation();
}

function loadUsersForDrawer(companyId, selectedUserId = null) {
    const userSelect = document.getElementById('drawer_zugewiesen_an');
    if (!userSelect) return;
    
    // Zuweisbare Benutzer laden (Firmen-Benutzer + Techniker/Admins)
    let url = todosApiUrl + '?action=assignable_users';
    if (companyId) {
        url += '&company_id=' + companyId;
    }
    
    todosFetchJson(url)
        .then(data => {
            if (data.success && data.users) {
                allUsers = data.users;
                userSelect.innerHTML = '<option value="">Nicht zugewiesen</option>';
                data.users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    const fullName = `${user.vorname || ''} ${user.nachname || ''}`.trim() || user.email;
                    option.textContent = fullName;
                    if (selectedUserId && user.id == selectedUserId) {
                        option.selected = true;
                    }
                    userSelect.appendChild(option);
                });
                syncDrawerAssigneeCard();
            } else {
                userSelect.innerHTML = '<option value="">Nicht zugewiesen</option>';
                syncDrawerAssigneeCard();
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Benutzer:', error);
            userSelect.innerHTML = '<option value="">Nicht zugewiesen</option>';
            syncDrawerAssigneeCard();
        });
}

function loadTodoAttachments(todoId) {
    todosFetchJson(`${attachmentsApiUrl}?todo_id=${todoId}`)
    .then(data => {
        if (data.success && data.attachments) {
            displayTodoAttachments(data.attachments);
        }
    })
    .catch(error => {
        console.error('Fehler beim Laden der Anhänge:', error);
    });
}

function displayTodoAttachments(attachments) {
    const attachmentsList = document.getElementById('drawer_attachments_list');
    if (!attachmentsList) return;
    
    if (!attachments || attachments.length === 0) {
        attachmentsList.innerHTML = '<p class="text-xs text-gray-400 dark:text-primary-240">Keine Anhänge</p>';
        return;
    }
    
    attachmentsList.innerHTML = attachments.map(att => {
        const fileSize = formatFileSize(att.dateigroesse);
        const fileIcon = getFileIcon(att.mime_type || '', att.dateiname);
        const fileUrl = `<?php echo BASE_URL; ?>${att.dateipfad}`;
        
        return `
            <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-primary-300/50 rounded-lg">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <i class="${fileIcon} text-gray-500 dark:text-primary-240"></i>
                    <a href="${fileUrl}" target="_blank" class="text-sm text-gray-900 dark:text-primary-200 hover:text-primary-600 dark:hover:text-primary-400 truncate" title="${escapeHtml(att.dateiname)}">
                        ${escapeHtml(att.dateiname)}
                    </a>
                    <span class="text-xs text-gray-500 dark:text-primary-240">${fileSize}</span>
                </div>
                <button type="button" onclick="deleteTodoAttachment(${att.id}, ${att.todo_id})" 
                        class="p-1 hover:bg-red-50 dark:hover:bg-red-900/20 rounded" 
                        title="Löschen">
                    <svg class="w-5 h-5 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14m-9 3v8m4-8v8M10 3h4a1 1 0 0 1 1 1v3H9V4a1 1 0 0 1 1-1ZM6 7h12v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7Z"/>
                    </svg>
                </button>
            </div>
        `;
    }).join('');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function getFileIcon(mimeType, fileName) {
    if (mimeType.startsWith('image/')) return 'fas fa-image';
    if (mimeType.startsWith('video/')) return 'fas fa-video';
    if (mimeType.startsWith('audio/')) return 'fas fa-music';
    if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
    if (mimeType.includes('word') || fileName.endsWith('.doc') || fileName.endsWith('.docx')) return 'fas fa-file-word';
    if (mimeType.includes('excel') || fileName.endsWith('.xls') || fileName.endsWith('.xlsx')) return 'fas fa-file-excel';
    if (mimeType.includes('zip') || fileName.endsWith('.zip') || fileName.endsWith('.rar')) return 'fas fa-file-archive';
    return 'fas fa-file';
}

function uploadTodoAttachment(todoId, file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('todo_id', todoId);
    
    todosFetchJson(attachmentsApiUrl, {
        method: 'POST',
        body: formData
    })
    .then(data => {
        if (data.success) {
            loadTodoAttachments(todoId);
            if (typeof showToast === 'function') {
                showToast('Anhang erfolgreich hochgeladen', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler beim Hochladen: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler beim Hochladen: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Hochladen der Datei', 'error');
        } else {
            alert('Fehler beim Hochladen der Datei');
        }
    });
}

function deleteTodoAttachment(attachmentId, todoId) {
    if (!confirm('Möchten Sie diesen Anhang wirklich löschen?')) {
        return;
    }
    
    todosFetchJson(`${attachmentsApiUrl}?id=${attachmentId}`, {
        method: 'DELETE'
    })
    .then(data => {
        if (data.success) {
            loadTodoAttachments(todoId);
            if (typeof showToast === 'function') {
                showToast('Anhang erfolgreich gelöscht', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Löschen des Anhangs', 'error');
        } else {
            alert('Fehler beim Löschen des Anhangs');
        }
    });
}

let drawerAutosaveTimeout = null;
let drawerAutosaveDelayMs = 200;   // Kurzer Debounce für Textfelder (Titel, Beschreibung)
let drawerAutosaveDelayShortMs = 0; // Sofort bei Select/Date

function hideDrawerSaveIndicator() {
    const ind = document.getElementById('drawerAutosaveIndicator');
    if (!ind) return;
    ind.classList.add('hidden');
    ind.textContent = '';
    ind.removeAttribute('title');
    if (ind._hideTimeout) clearTimeout(ind._hideTimeout);
    ind._hideTimeout = null;
}

/** Nur bei Speicherfehler im Drawer-Footer anzeigen (kein Erfolgs-Hinweis). */
function showDrawerSaveError(message) {
    const ind = document.getElementById('drawerAutosaveIndicator');
    if (!ind) return;
    const msg = message || 'Speichern fehlgeschlagen';
    ind.textContent = msg;
    ind.title = msg;
    ind.classList.remove('hidden');
    if (ind._hideTimeout) clearTimeout(ind._hideTimeout);
    ind._hideTimeout = setTimeout(function() { hideDrawerSaveIndicator(); }, 6000);
}

/** Autosave auslösen (debounced). */
function scheduleDrawerAutosave(delayMs) {
    if (drawerAutosaveTimeout) clearTimeout(drawerAutosaveTimeout);
    drawerAutosaveTimeout = setTimeout(function() {
        drawerAutosaveTimeout = null;
        saveTodoFromDrawer(true);
    }, delayMs);
}

/** Desktop: Titel nur als Text; Klick → Eingabefeld, bis blur/Escape. */
function setupDrawerTitleInlineEdit() {
    const hidden = document.getElementById('drawer_titel');
    const displayBtn = document.getElementById('drawer_titel_display');
    const editInp = document.getElementById('drawer_titel_edit');
    if (!hidden || !displayBtn || !editInp) return;

    function syncDisplayText() {
        var v = (hidden.value || '').trim();
        displayBtn.textContent = v || '\u2014';
    }

    function openTitleEdit() {
        editInp.value = hidden.value || '';
        displayBtn.classList.add('hidden');
        editInp.classList.remove('hidden');
        editInp.focus();
        editInp.select();
    }

    function closeTitleEdit() {
        hidden.value = editInp.value;
        syncDisplayText();
        editInp.classList.add('hidden');
        displayBtn.classList.remove('hidden');
    }

    displayBtn.addEventListener('click', function() {
        openTitleEdit();
    });

    editInp.addEventListener('blur', function() {
        if (!editInp.classList.contains('hidden')) {
            closeTitleEdit();
        }
    });

    editInp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            editInp.blur();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            editInp.value = hidden.value || '';
            editInp.blur();
        }
    });

    editInp.addEventListener('input', function() {
        hidden.value = editInp.value;
        scheduleDrawerAutosave(drawerAutosaveDelayMs);
    });

    syncDisplayText();
}

/** Event-Listener für Autosave im Drawer an die Formularfelder hängen. */
function setupDrawerAutosave() {
    const titelEl = document.getElementById('drawer_titel');
    const navTit = document.getElementById('navMobileTodoDrawerTitleInput');
    const beschreibungEl = document.getElementById('drawer_beschreibung');
    const faelligEl = document.getElementById('drawer_faellig_am');
    const folderEl = document.getElementById('drawer_folder_id');
    const zugewiesenEl = document.getElementById('drawer_zugewiesen_an');
    if (!titelEl) return;
    setupDrawerTitleInlineEdit();
    if (navTit) {
        navTit.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        navTit.addEventListener('input', function() {
            syncTodosDrawerHiddenTitleFromNav();
            scheduleDrawerAutosave(drawerAutosaveDelayMs);
        });
    }
    if (beschreibungEl) {
        beschreibungEl.addEventListener('input', function() { scheduleDrawerAutosave(drawerAutosaveDelayMs); });
    }
    if (faelligEl) {
        faelligEl.addEventListener('change', function() { scheduleDrawerAutosave(drawerAutosaveDelayShortMs); });
    }
    if (folderEl) {
        folderEl.addEventListener('change', function() {
            syncDrawerFolderCard();
            scheduleDrawerAutosave(drawerAutosaveDelayShortMs);
        });
    }
    if (zugewiesenEl) {
        zugewiesenEl.addEventListener('change', function() {
            syncDrawerAssigneeCard();
            scheduleDrawerAutosave(drawerAutosaveDelayShortMs);
        });
    }
}

function saveTodoFromDrawer(skipSuccessToast) {
    const todoIdEl = document.getElementById('drawer_todo_id');
    if (!todoIdEl) return;
    const todoId = todoIdEl.value;
    syncTodosDrawerHiddenTitleFromNav();
    var titel = '';
    var navTit = document.getElementById('navMobileTodoDrawerTitleInput');
    if (navTit && window.matchMedia('(max-width: 1023px)').matches && document.body.classList.contains('todos-drawer-detail-open')) {
        titel = (navTit.value || '').trim();
    } else {
        var te = document.getElementById('drawer_titel');
        titel = te ? (te.value || '').trim() : '';
    }
    const beschreibung = document.getElementById('drawer_beschreibung').value.trim();
    const faelligInput = document.getElementById('drawer_faellig_am');
    let faelligAm = faelligInput ? (faelligInput.value || null) : null;
    const todoForDue = todos.find(function(t) { return t.id == todoId; }) || closedTodos.find(function(t) { return t.id == todoId; });
    if (faelligAm && todoForDue && !todoSwipeTodoHasFaellig(todoForDue) && window._drawerOpenedAt != null) {
        var msDrawerDue = Date.now() - window._drawerOpenedAt;
        var todayStrDrawer = formatDateForInput(new Date());
        if (faelligAm === todayStrDrawer && msDrawerDue < 1800) {
            faelligAm = null;
            if (faelligInput) faelligInput.value = '';
        }
    }
    const folderIdSelect = document.getElementById('drawer_folder_id');
    let folderId = null;
    if (folderIdSelect) {
        const selectedValue = folderIdSelect.value;
        if (selectedValue && selectedValue !== '' && selectedValue !== 'null') {
            folderId = parseInt(selectedValue);
        } else {
            folderId = null;
        }
    }
    
    const zugewiesenAnSelect = document.getElementById('drawer_zugewiesen_an');
    let zugewiesenAn = null;
    if (zugewiesenAnSelect) {
        const selectedValue = zugewiesenAnSelect.value;
        if (selectedValue && selectedValue !== '' && selectedValue !== 'null') {
            zugewiesenAn = parseInt(selectedValue);
        } else {
            zugewiesenAn = null;
        }
    }
    
    if (!titel) {
        alert('Bitte geben Sie einen Titel ein');
        return;
    }
    
    // company_id aus localStorage lesen
    let companyId = null;
    try {
        const savedSelection = localStorage.getItem('selectedUserOption');
        if (savedSelection) {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0') {
                companyId = parseInt(data.id);
            }
        }
    } catch (e) {
        console.error('Fehler beim Lesen der company_id aus localStorage:', e);
    }
    
    // Fälligkeitsdatum: Wenn nur Datum vorhanden, Zeit auf 00:00:00 setzen
    let faelligAmFormatted = null;
    if (faelligAm) {
        // date input gibt YYYY-MM-DD zurück, wir fügen die Zeit hinzu
        faelligAmFormatted = faelligAm + ' 00:00:00';
    }
    
    const formData = {
        todo_id: parseInt(todoId),
        titel: titel,
        beschreibung: beschreibung || null,
        faellig_am: faelligAmFormatted,
        folder_id: folderId,
        zugewiesen_an: zugewiesenAn,
        company_id: companyId,
        is_autosave: !!skipSuccessToast
    };
    
    todosFetchJson(todosApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            const oldFolderId = todoForDue && todoForDue.folder_id != null ? Number(todoForDue.folder_id) : 0;
            const newFolderId = folderId != null ? Number(folderId) : 0;
            const folderChanged = oldFolderId !== newFolderId;

            if (todoForDue) {
                todoForDue.titel = titel;
                todoForDue.beschreibung = beschreibung || null;
                todoForDue.faellig_am = faelligAmFormatted;
                todoForDue.folder_id = folderId;
                todoForDue.zugewiesen_an = zugewiesenAn;
                rerenderTodoListItem(todoId);
            }

            hideDrawerSaveIndicator();

            // Beim Autosave die komplette Liste nicht bei jeder Eingabe neu laden
            // (verhindert sichtbares Flackern im Hintergrund).
            const shouldReloadList = !skipSuccessToast || folderChanged;
            if (shouldReloadList) {
                // Ordner-Filter beibehalten: Nicht auf "Alle" wechseln, damit die Liste weiter
                // nach dem aktuellen Ordner gefiltert bleibt (Aufgabe verschwindet ggf. aus der Liste).
                loadFolders();
                loadTodos();
                updateSidebarTodosCount();
            }
            if (!skipSuccessToast && typeof showToast === 'function') {
                showToast('Aufgabe erfolgreich aktualisiert', 'success');
            }
        } else {
            const errMsg = 'Fehler: ' + (data.error || 'Unbekannter Fehler');
            if (skipSuccessToast) {
                showDrawerSaveError(errMsg);
            } else if (typeof showToast === 'function') {
                showToast(errMsg, 'error');
            } else {
                alert(errMsg);
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        const errMsg = 'Fehler beim Speichern der Aufgabe';
        if (skipSuccessToast) {
            showDrawerSaveError(errMsg);
        } else if (typeof showToast === 'function') {
            showToast(errMsg, 'error');
        } else {
            alert(errMsg);
        }
    });
}

function editTodo(todoId) {
    const todo = todos.find(t => t.id == todoId);
    if (todo) {
        openTodoDrawer(todoId);
    }
}

function deleteTodo(todoId) {
    if (!confirm('Möchten Sie diese Aufgabe wirklich löschen?')) {
        return;
    }
    
    todosFetchJson(todosApiUrl + '?id=' + todoId, {
        method: 'DELETE'
    })
    .then(data => {
        if (data.success) {
            loadTodos();
            loadFolders(); // Ordneranzahl aktualisieren
            updateSidebarTodosCount();
            if (typeof showToast === 'function') {
                showToast('Aufgabe erfolgreich gelöscht', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Löschen der Aufgabe', 'error');
        } else {
            alert('Fehler beim Löschen der Aufgabe');
        }
    });
}

let folderCandidates = [];
let folderSelectedMemberIds = [];
let mobileSheetFolderMemberIds = [];
let folderContextFolder = null; // Ordner für Rechtsklick-Kontextmenü
let todoContextTodo = null; // Aufgabe für Rechtsklick-Kontextmenü

function showTodoContextMenu(clientX, clientY, todo) {
    todoContextTodo = todo;
    const menu = document.getElementById('todoContextMenu');
    const favoritBtn = menu?.querySelector('[data-todo-ctx="favorit"]');
    const favoritText = document.getElementById('todoCtxFavoritText');
    const statusBtn = menu?.querySelector('[data-todo-ctx="status"]');
    const statusText = document.getElementById('todoCtxStatusText');
    const openTicketBtn = document.getElementById('todoCtxOpenTicket');
    if (!menu) return;
    const isFavorit = todo.favorit == 1 || todo.favorit === true;
    const isErledigt = todo.status === 'erledigt';
    const hasTicket = todo.ticket_id && parseInt(todo.ticket_id) > 0;
    if (favoritText) favoritText.textContent = isFavorit ? 'Aus Favoriten entfernen' : 'Als wichtig markieren';
    if (statusText) statusText.textContent = isErledigt ? 'Als offen markieren' : 'Als erledigt markieren';
    if (openTicketBtn) {
        openTicketBtn.classList.toggle('hidden', !hasTicket);
    }
    const hasDueDate = !!(todo.faellig_am && String(todo.faellig_am).trim() !== '');
    const dueTodayBtn = document.getElementById('todoCtxDueToday');
    const dueTomorrowBtn = document.getElementById('todoCtxDueTomorrow');
    const dueClearBtn = document.getElementById('todoCtxDueClear');
    if (dueTodayBtn) dueTodayBtn.classList.toggle('hidden', hasDueDate);
    if (dueTomorrowBtn) dueTomorrowBtn.classList.toggle('hidden', hasDueDate);
    if (dueClearBtn) dueClearBtn.classList.toggle('hidden', !hasDueDate);
    const assignSection = document.getElementById('todoCtxAssignSection');
    const assignSubmenu = document.getElementById('todoCtxAssignSubmenu');
    const assignTrigger = document.getElementById('todoCtxAssignTrigger');
    if (assignSubmenu && assignSection && assignTrigger) {
        assignSubmenu.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-primary-240">Laden...</div>';
        let assignHideTimer = null;
        assignSection.onmouseenter = () => {
            if (assignHideTimer) clearTimeout(assignHideTimer);
            assignSubmenu.classList.remove('hidden');
        };
        assignSection.onmouseleave = () => {
            assignHideTimer = setTimeout(() => assignSubmenu.classList.add('hidden'), 100);
        };
        const companyId = todo.company_id || null;
        let url = todosApiUrl + '?action=assignable_users';
        if (companyId) url += '&company_id=' + companyId;
        todosFetchJson(url)
            .then(data => {
                if (data.success && data.users) {
                    const noAssignBtn = `<button type="button" data-todo-ctx="assign" data-user-id="0" class="w-full px-3 py-1.5 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 border-b border-gray-100 dark:border-primary-120">Nicht zugewiesen</button>`;
                    const userItems = data.users.map(u => {
                        const fid = Number(u.id);
                        const name = [u.vorname, u.nachname].filter(Boolean).join(' ') || u.email || 'ID ' + fid;
                        return `<button type="button" data-todo-ctx="assign" data-user-id="${fid}" class="w-full px-3 py-1.5 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">${escapeHtml(name)}</button>`;
                    }).join('');
                    assignSubmenu.innerHTML = noAssignBtn + userItems;
                } else {
                    assignSubmenu.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-primary-240">Keine Benutzer</div>';
                }
            })
            .catch(() => {
                assignSubmenu.innerHTML = '<div class="px-3 py-2 text-sm text-red-500">Fehler beim Laden</div>';
            });
    }
    const folderSection = document.getElementById('todoCtxFolderSection');
    const folderSubmenu = document.getElementById('todoCtxFolderSubmenu');
    const folderTrigger = document.getElementById('todoCtxFolderTrigger');
    if (folderSubmenu && folderSection && folderTrigger) {
        const noFolderBtn = `<button type="button" data-todo-ctx="folder" data-folder-id="0" class="w-full px-3 py-1.5 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2 border-b border-gray-100 dark:border-primary-120">Ohne Ordner</button>`;
        const folderItems = folders.map(f => {
            const fid = Number(f.id);
            return `<button type="button" data-todo-ctx="folder" data-folder-id="${fid}" class="w-full px-3 py-1.5 text-left text-sm text-gray-700 dark:text-primary-210 hover:bg-gray-100 dark:hover:bg-primary-140 flex items-center gap-2">${escapeHtml(f.name)}</button>`;
        }).join('');
        folderSubmenu.innerHTML = noFolderBtn + folderItems;
        folderSection.style.display = 'block';
        let submenuHideTimer = null;
        folderSection.onmouseenter = () => {
            if (submenuHideTimer) clearTimeout(submenuHideTimer);
            folderSubmenu.classList.remove('hidden');
        };
        folderSection.onmouseleave = () => {
            submenuHideTimer = setTimeout(() => folderSubmenu.classList.add('hidden'), 100);
        };
    }
    menu.classList.remove('hidden');
    let left = clientX;
    let top = clientY;
    const viewportPadding = 8;
    const rect = menu.getBoundingClientRect();
    if (rect.right > window.innerWidth - viewportPadding) {
        left = Math.max(viewportPadding, left - (rect.right - window.innerWidth + viewportPadding));
    }
    if (rect.bottom > window.innerHeight - viewportPadding) {
        top = Math.max(viewportPadding, top - (rect.bottom - window.innerHeight + viewportPadding));
    }
    if (rect.left < viewportPadding) left = viewportPadding;
    if (rect.top < viewportPadding) top = viewportPadding;
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
}

function hideTodoContextMenu() {
    todoContextTodo = null;
    const menu = document.getElementById('todoContextMenu');
    const folderSubmenu = document.getElementById('todoCtxFolderSubmenu');
    const assignSubmenu = document.getElementById('todoCtxAssignSubmenu');
    if (menu) menu.classList.add('hidden');
    if (folderSubmenu) folderSubmenu.classList.add('hidden');
    if (assignSubmenu) assignSubmenu.classList.add('hidden');
}

function handleTodoContextMenuClick(e) {
    const btn = e.target.closest('[data-todo-ctx]');
    if (!btn || !todoContextTodo) return;
    const action = btn.dataset.todoCtx;
    const todoId = parseInt(todoContextTodo.id);
    if (action === 'favorit') {
        const isFavorit = todoContextTodo.favorit == 1 || todoContextTodo.favorit === true;
        toggleFavorite(todoId, !isFavorit);
    } else if (action === 'status') {
        const isErledigt = todoContextTodo.status === 'erledigt';
        toggleTodoStatus(todoId, !isErledigt);
    } else if (action === 'open-ticket' && todoContextTodo.ticket_id) {
        window.location.href = '<?php echo BASE_URL; ?>tickets/view.php?id=' + todoContextTodo.ticket_id;
    } else if (action === 'due-today') {
        setTodoDueDate(todoId, new Date());
    } else if (action === 'due-tomorrow') {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        setTodoDueDate(todoId, tomorrow);
    } else if (action === 'due-clear') {
        clearTodoDueDate(todoId);
    } else if (action === 'assign') {
        const userId = parseInt(btn.dataset.userId) || 0;
        assignTodoToUser(todoId, userId === 0 ? null : userId);
    } else if (action === 'folder') {
        const folderId = parseInt(btn.dataset.folderId);
        assignTodoToFolder(todoId, folderId);
    } else if (action === 'delete') {
        deleteTodo(todoId);
    }
    hideTodoContextMenu();
}

function setTodoDueDate(todoId, date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    const faelligAm = y + '-' + m + '-' + d + 'T12:00:00';
    todosFetchJson(todosApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ todo_id: todoId, faellig_am: faelligAm })
    })
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') showToast('Fälligkeitsdatum gesetzt', 'success');
            loadTodos();
            loadFolders();
        } else if (typeof showToast === 'function') {
            showToast('Fehler: ' + (data.error || ''), 'error');
        }
    })
    .catch(err => { if (typeof showToast === 'function') showToast('Fehler beim Setzen des Datums', 'error'); });
}

function clearTodoDueDate(todoId) {
    todosFetchJson(todosApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ todo_id: todoId, faellig_am: null })
    })
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') showToast('Fälligkeit entfernt', 'success');
            loadTodos();
            loadFolders();
        } else if (typeof showToast === 'function') {
            showToast('Fehler: ' + (data.error || ''), 'error');
        }
    })
    .catch(() => { if (typeof showToast === 'function') showToast('Fehler beim Entfernen', 'error'); });
}

function folderIsPrivateForContext(folder) {
    return !!(folder && (folder.is_private === 1 || folder.is_private === true));
}

function folderInviteShareMemberCount(folder) {
    if (!folder || !(folder.is_private === 1 || folder.is_private === true)) return 0;
    const ids = folder.member_ids;
    return Array.isArray(ids) ? ids.length : 0;
}

function showFolderContextMenu(buttonEl, folder) {
    folderContextFolder = folder;
    const menu = document.getElementById('folderContextMenu');
    const deleteBtn = document.getElementById('folderCtxDelete');
    const privateInfo = document.getElementById('folderCtxPrivateInfo');
    const shareInfo = document.getElementById('folderCtxShareInfo');
    const publicInfo = document.getElementById('folderCtxPublicInfo');
    if (!menu || !deleteBtn || !buttonEl) return;
    const isPrivate = folderIsPrivateForContext(folder);
    if (privateInfo) {
        privateInfo.classList.toggle('hidden', !isPrivate);
    }
    if (publicInfo) {
        publicInfo.classList.toggle('hidden', !folder || isPrivate);
    }
    if (shareInfo) {
        const showShared = !!(folder && folderInviteShareMemberCount(folder) > 0);
        shareInfo.classList.toggle('hidden', !showShared);
    }
    const canDelete = folder && !(folder.is_ticket_system_folder === 1 || folder.is_ticket_system_folder === true) && !(folder.is_project_system_folder === 1 || folder.is_project_system_folder === true);
    deleteBtn.style.display = canDelete ? 'flex' : 'none';
    menu.classList.remove('hidden');
    const rect = buttonEl.getBoundingClientRect();
    menu.style.left = rect.left + 'px';
    menu.style.top = (rect.bottom + 4) + 'px';
}

function hideFolderContextMenu() {
    folderContextFolder = null;
    const menu = document.getElementById('folderContextMenu');
    if (menu) menu.classList.add('hidden');
}

function openFolderModal(folder = null) {
    if (!folder && window.matchMedia('(max-width: 1023px)').matches) {
        expandMobileSheetFolderCreate();
        return;
    }
    const modal = document.getElementById('folderModal');
    const form = document.getElementById('folderForm');
    const title = document.getElementById('folderModalTitle');
    const inviteSection = document.getElementById('folderInviteSection');
    const visibilityPublic = document.getElementById('folderVisibilityPublic');
    const visibilityPrivate = document.getElementById('folderVisibilityPrivate');
    const visibilityInvite = document.getElementById('folderVisibilityInvite');
    
    if (folder) {
        title.textContent = 'Ordner bearbeiten';
        document.getElementById('folder_id').value = folder.id;
        document.getElementById('folderName').value = folder.name;
        const isPrivate = folder.is_private === 1 || folder.is_private === true;
        const memberIds = folder.member_ids || [];
        visibilityPublic.checked = !isPrivate;
        visibilityPrivate.checked = isPrivate && memberIds.length === 0;
        visibilityInvite.checked = isPrivate && memberIds.length > 0;
        folderSelectedMemberIds = memberIds.slice();
        inviteSection.classList.toggle('hidden', !visibilityInvite.checked);
        if (visibilityInvite.checked) loadFolderCandidates();
    } else {
        title.textContent = 'Neuer Ordner';
        form.reset();
        document.getElementById('folder_id').value = '';
        visibilityPublic.checked = true;
        visibilityPrivate.checked = false;
        visibilityInvite.checked = false;
        folderSelectedMemberIds = [];
        inviteSection.classList.add('hidden');
        document.getElementById('folderMemberSearch').value = '';
    }
    
    modal.classList.remove('hidden');
    const nameInput = document.getElementById('folderName');
    if (nameInput) {
        requestAnimationFrame(function() { nameInput.focus(); });
    }

    ensureFolderCompaniesForModal().then(function() {
        if (folder) {
            var isPriv = folder.is_private === 1 || folder.is_private === true;
            var cid = '';
            if (!isPriv && folder.company_id != null && folder.company_id !== '') {
                cid = folder.company_id;
            }
            setFolderCompanySelectValue('desktop', cid);
        } else {
            setFolderCompanySelectValue('desktop', getNavSelectedCompanyId());
        }
    }).finally(function() {
        updateFolderCompanySectionsVisibility();
    });
}

function closeFolderModal() {
    document.getElementById('folderModal').classList.add('hidden');
    document.getElementById('folderForm').reset();
    document.getElementById('folderInviteSection').classList.add('hidden');
    document.getElementById('folderVisibilityPublic').checked = true;
    document.getElementById('folderMemberSearch').value = '';
    folderSelectedMemberIds = [];
    updateFolderCompanySectionsVisibility();
}

function loadFolderCandidates() {
    todosFetchJson(foldersApiUrl + '?candidates=1')
        .then(data => {
            if (data.success && data.candidates) {
                folderCandidates = data.candidates;
                const ms = document.getElementById('folderMemberSearch');
                renderFolderCandidates(ms ? ms.value : '');
                const mobInv = document.getElementById('mobile-sheet-folder-invite-section');
                if (mobInv && !mobInv.classList.contains('hidden')) {
                    const mms = document.getElementById('mobile-sheet-folder-member-search');
                    renderFolderCandidates(mms ? mms.value : '', 'mobile');
                }
            }
        })
        .catch(err => console.error('Kollegen laden:', err));
}

function renderFolderCandidates(filter, target) {
    target = target || 'modal';
    const listId = target === 'mobile' ? 'mobile-sheet-folder-candidates-list' : 'folderCandidatesList';
    const list = document.getElementById(listId);
    if (!list) return;
    const selectedIds = target === 'mobile' ? mobileSheetFolderMemberIds : folderSelectedMemberIds;
    const q = (filter || '').toLowerCase().trim();
    const filtered = folderCandidates.filter(c => {
        const name = ((c.vorname || '') + ' ' + (c.nachname || '') + ' ' + (c.email || '')).toLowerCase();
        return !q || name.indexOf(q) !== -1;
    });
    list.innerHTML = filtered.map(c => {
        const id = parseInt(c.id);
        const selected = selectedIds.indexOf(id) !== -1;
        const label = [c.vorname, c.nachname].filter(Boolean).join(' ') || c.email || 'ID ' + id;
        return '<div class="folder-candidate-row flex items-center justify-between px-4 py-3 rounded-base transition-colors ' + (selected ? 'selected' : '') + ' hover:bg-gray-50 dark:hover:bg-primary-140" data-user-id="' + id + '" data-label="' + (label.toLowerCase()) + '"><span class="text-sm font-medium text-gray-900 dark:text-primary-200">' + (label || '') + '</span><span class="text-xs text-gray-500 dark:text-primary-240">' + (c.rolle || '') + '</span></div>';
    }).join('') || '<p class="px-4 py-3 text-sm text-gray-500 dark:text-primary-240">Keine Kollegen gefunden</p>';
    const searchInputId = target === 'mobile' ? 'mobile-sheet-folder-member-search' : 'folderMemberSearch';
    list.querySelectorAll('.folder-candidate-row').forEach(row => {
        row.addEventListener('click', function() {
            const uid = parseInt(this.getAttribute('data-user-id'));
            const arr = target === 'mobile' ? mobileSheetFolderMemberIds : folderSelectedMemberIds;
            const idx = arr.indexOf(uid);
            if (idx === -1) {
                arr.push(uid);
            } else {
                arr.splice(idx, 1);
            }
            const searchEl = document.getElementById(searchInputId);
            renderFolderCandidates(searchEl ? searchEl.value : '', target);
        });
    });
}

function saveFolder(options) {
    options = options || {};
    const fromMobileSheet = !!options.fromMobileSheet;
    let folderId = '';
    let name = '';
    let isPublic = true;
    let isInvite = false;
    let memberIds = [];

    if (fromMobileSheet) {
        folderId = '';
        const nEl = document.getElementById('mobile-sheet-folder-name');
        name = nEl ? nEl.value.trim() : '';
        const pub = document.getElementById('mobile-sheet-folder-vis-public');
        const inv = document.getElementById('mobile-sheet-folder-vis-invite');
        isPublic = !!(pub && pub.checked);
        isInvite = !!(inv && inv.checked);
        memberIds = isInvite ? mobileSheetFolderMemberIds.slice() : [];
    } else {
        folderId = document.getElementById('folder_id').value;
        name = document.getElementById('folderName').value.trim();
        isPublic = document.getElementById('folderVisibilityPublic').checked;
        isInvite = document.getElementById('folderVisibilityInvite').checked;
        memberIds = isInvite ? folderSelectedMemberIds : [];
    }

    if (!name) {
        if (typeof showToast === 'function') showToast('Bitte Ordnernamen eingeben', 'error');
        else alert('Bitte geben Sie einen Ordnernamen ein');
        return;
    }

    const url = foldersApiUrl;
    const method = folderId ? 'PUT' : 'POST';
    const isPrivateFolder = !isPublic;
    const editingFolder = folderId ? folders.find(function(f) { return Number(f.id) === Number(folderId); }) : null;
    const systemLocksCompany = editingFolder && ((editingFolder.is_ticket_system_folder == 1 || editingFolder.is_ticket_system_folder === true) ||
        (editingFolder.is_project_system_folder == 1 || editingFolder.is_project_system_folder === true));

    const data = folderId ? {
        folder_id: parseInt(folderId, 10),
        name: name,
        is_private: isPrivateFolder,
        member_ids: memberIds
    } : {
        name: name,
        is_private: isPrivateFolder,
        member_ids: memberIds
    };

    if (!isPrivateFolder && (!folderId || !systemLocksCompany)) {
        var selCo = fromMobileSheet ? document.getElementById('mobile-sheet-folder-company') : document.getElementById('folderCompanySelect');
        var cidOut = null;
        if (selCo && selCo.value) {
            var pnc = parseInt(selCo.value, 10);
            if (!isNaN(pnc) && pnc > 0) {
                cidOut = pnc;
            }
        }
        data.company_id = cidOut;
    }
    
    todosFetchJson(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(result => {
        if (result.success) {
            if (fromMobileSheet) {
                collapseMobileSheetFolderCreate();
            } else {
                closeFolderModal();
            }
            loadFolders();
            if (!folderId) {
                currentFolderId = result.folder_id || 0;
                updateFolderButtons();
                loadTodos();
            }
            if (typeof showToast === 'function') {
                showToast(folderId ? 'Ordner erfolgreich aktualisiert' : 'Ordner erfolgreich erstellt', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (result.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Speichern des Ordners', 'error');
        } else {
            alert('Fehler beim Speichern des Ordners');
        }
    });
}

function deleteFolder(folderOrId) {
    const folder = (folderOrId && typeof folderOrId === 'object')
        ? folderOrId
        : folders.find(f => Number(f.id) === Number(folderOrId || currentFolderId));
    if (!folder) {
        return;
    }
    if (folder.is_ticket_system_folder) {
        if (typeof showToast === 'function') showToast('Dieser Ordner ist der Systemordner für Ticketaufgaben und kann nicht gelöscht werden.', 'error');
        return;
    }
    if (folder.is_project_system_folder) {
        if (typeof showToast === 'function') showToast('Dieser Ordner ist der Systemordner für Projektaufgaben und kann nicht gelöscht werden.', 'error');
        return;
    }
    
    if (!confirm(`Möchten Sie den Ordner "${folder.name}" wirklich löschen?\n\nAlle Aufgaben in diesem Ordner werden nicht gelöscht, sondern bleiben ohne Ordnerzuordnung.`)) {
        return;
    }
    
    const folderId = Number(folder.id);
    todosFetchJson(`${foldersApiUrl}?id=${folderId}`, {
        method: 'DELETE'
    })
    .then(data => {
        if (data.success) {
            // Wenn gelöschter Ordner war aktuell ausgewählt, auf „ohne Ordner“ wechseln
            if (Number(currentFolderId) === folderId) {
                currentFolderId = 0;
            }
            loadFolders();
            loadTodos();
            updateFolderButtons();
            if (typeof showToast === 'function') {
                showToast('Ordner erfolgreich gelöscht', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Löschen des Ordners', 'error');
        } else {
            alert('Fehler beim Löschen des Ordners');
        }
    });
}

/** Verhindert doppelte Schnell-Eingabe (mehrfaches Enter bei langsamer Verbindung). */
let quickTodoCreateInProgress = false;

function createQuickTodo() {
    if (quickTodoCreateInProgress) return;

    const input = document.getElementById('quickTodoInput');
    const titel = input.value.trim();
    
    if (!titel) {
        return;
    }
    
    // company_id aus localStorage lesen
    let companyId = null;
    try {
        const savedSelection = localStorage.getItem('selectedUserOption');
        if (savedSelection) {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0') {
                companyId = parseInt(data.id);
            }
        }
    } catch (e) {
        console.error('Fehler beim Lesen der company_id aus localStorage:', e);
    }
    
    const formData = {
        titel: titel,
        status: 'offen',
        folder_id: currentFolderId === 0 ? null : currentFolderId,
        company_id: companyId
    };

    quickTodoCreateInProgress = true;
    todosFetchJson(todosApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            input.value = ''; // Input leeren
            loadTodos();
            loadFolders(); // Ordneranzahl aktualisieren
            updateSidebarTodosCount();
            if (typeof showToast === 'function') {
                showToast('Aufgabe erfolgreich erstellt', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Erstellen der Aufgabe', 'error');
        } else {
            alert('Fehler beim Erstellen der Aufgabe');
        }
    })
    .finally(function() {
        quickTodoCreateInProgress = false;
    });
}

function toggleFavorite(todoId, isFavorit) {
    todosFetchJson(todosApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            todo_id: parseInt(todoId),
            favorit: isFavorit ? 1 : 0
        })
    })
    .then(data => {
        if (data.success) {
            // Todo-Liste neu laden, um die Sortierung zu aktualisieren
            loadTodos();
            if (typeof showToast === 'function') {
                showToast(isFavorit ? 'Aufgabe zu Favoriten hinzugefügt' : 'Aufgabe aus Favoriten entfernt', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Aktualisieren des Favoriten-Status', 'error');
        } else {
            alert('Fehler beim Aktualisieren des Favoriten-Status');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/** Sidebar-Zähler für offene Aufgaben nach API-Abfrage aktualisieren. */
function updateSidebarTodosCount() {
    if (!todosOpenCountUrl) return;
    todosFetchJson(todosOpenCountUrl)
        .then(function(data) {
            var nodes = document.querySelectorAll('.sidebar-open-todos-count-badge');
            if (!nodes.length) return;
            var count = data.success ? (data.open_count || 0) : 0;
            var text = count > 99 ? '99' : String(count);
            var title = count + ' offene Aufgaben';
            nodes.forEach(function(el) {
                el.textContent = text;
                el.title = title;
                el.classList.toggle('hidden', count <= 0);
            });
        })
        .catch(function() {});
}

/**
 * Parst ein Server-Datum (YYYY-MM-DD oder YYYY-MM-DD HH:mm:ss) als lokales Datum.
 * new Date("YYYY-MM-DD") wird in JS als UTC-Mitternacht interpretiert und führt
 * in Zeitzonen wie Europe/Berlin zu einem Tag weniger.
 */
function parseLocalDate(dateStr) {
    if (!dateStr) return null;
    const s = String(dateStr).trim();
    const match = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2}):(\d{2}))?/);
    if (!match) return new Date(s);
    const y = parseInt(match[1], 10), m = parseInt(match[2], 10) - 1, d = parseInt(match[3], 10);
    const h = match[4] != null ? parseInt(match[4], 10) : 0;
    const min = match[5] != null ? parseInt(match[5], 10) : 0;
    const sec = match[6] != null ? parseInt(match[6], 10) : 0;
    return new Date(y, m, d, h, min, sec);
}

/** YYYY-MM-DD für input type="date" aus lokalem Datum. */
function formatDateForInput(date) {
    if (!date) return '';
    const d = date.getDate(), m = date.getMonth() + 1;
    return date.getFullYear() + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
}

// Initial: Aufgaben-Ordner auswählen
currentFolderId = 0;

function updateCompanyBadge() {
    try {
        const savedSelection = localStorage.getItem('selectedUserOption');
        if (savedSelection) {
            const data = JSON.parse(savedSelection);
            if (data.id && data.id !== '0' && data.name) {
                const badge = document.getElementById('selectedCompanyBadge');
                const nameSpan = document.getElementById('selectedCompanyName');
                if (badge && nameSpan) {
                    nameSpan.textContent = data.name;
                    badge.classList.remove('hidden');
                }
            } else {
                const badge = document.getElementById('selectedCompanyBadge');
                if (badge) {
                    badge.classList.add('hidden');
                }
            }
        } else {
            const badge = document.getElementById('selectedCompanyBadge');
            if (badge) {
                badge.classList.add('hidden');
            }
        }
    } catch (e) {
        console.error('Fehler beim Lesen der Firma aus localStorage:', e);
    }
}

// Firma-Badge aktualisieren wenn localStorage sich ändert
window.addEventListener('storage', function(e) {
    if (e.key === 'selectedUserOption') {
        updateCompanyBadge();
        loadTodos(); // Todos neu laden wenn Firma geändert wird
        loadFolders(); // Ordner-Zähler an gewählte Firma anpassen
    }
});

// Auch beim Fokus der Seite aktualisieren (falls in anderem Tab geändert)
window.addEventListener('focus', function() {
    updateCompanyBadge();
});

// Drag-to-Scroll für Ordner-Filter (auch wenn der Zeiger auf einem Ordner-Button startet)
function initFolderFiltersDragScroll() {
    const scrollContainer = document.getElementById('folderFiltersScroll');
    const folderFiltersEl = document.getElementById('folderFilters');
    if (!scrollContainer) return;

    const DRAG_THRESHOLD_PX = 6;
    const SCROLL_SPEED = 2;
    let isDown = false;
    let dragStarted = false;
    let startedOnButton = false;
    let startX = 0;
    let scrollLeftStart = 0;
    let suppressFolderButtonClick = false;

    function pointerXInContainer(clientX) {
        return clientX - scrollContainer.getBoundingClientRect().left;
    }

    if (folderFiltersEl) {
        folderFiltersEl.addEventListener('click', function(e) {
            if (!suppressFolderButtonClick) return;
            const onButton = e.target.closest('button');
            if (onButton) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
            // Immer zurücksetzen: bei Klick auf Lücke/Padding sonst „klebendes“ suppress
            // und der nächste Ordner-Klick wird fälschlich geschluckt.
            suppressFolderButtonClick = false;
        }, true);
    }

    function endMouseDrag() {
        if (!isDown) return;
        // Nur unterdrücken, wenn wirklich gescrollt wurde — sonst frisst der erste Klick
        // (z. B. nach Drag am Scroll-Ende oder mit minimem Bewegungsraum) den Ordner-Klick.
        if (dragStarted && startedOnButton) {
            const didScrollHorizontally =
                Math.abs(scrollContainer.scrollLeft - scrollLeftStart) >= 1;
            if (didScrollHorizontally) {
                suppressFolderButtonClick = true;
                // Fallback: endet das Loslassen außerhalb der Leiste / ohne Button-click,
                // läuft der Capture-Handler oben nicht — sonst bleibt suppress dauerhaft true.
                setTimeout(function() {
                    suppressFolderButtonClick = false;
                }, 0);
            }
        }
        isDown = false;
        dragStarted = false;
        startedOnButton = false;
        scrollContainer.style.cursor = 'grab';
    }

    scrollContainer.addEventListener('mousedown', function(e) {
        if (e.button !== 0) return;
        isDown = true;
        dragStarted = false;
        startedOnButton = !!e.target.closest('button');
        startX = pointerXInContainer(e.clientX);
        scrollLeftStart = scrollContainer.scrollLeft;
    });

    scrollContainer.addEventListener('mouseleave', function() {
        if (!dragStarted) {
            isDown = false;
            startedOnButton = false;
        }
        scrollContainer.style.cursor = 'grab';
    });

    scrollContainer.addEventListener('mousemove', function(e) {
        if (!isDown) return;
        const x = pointerXInContainer(e.clientX);
        const dx = x - startX;
        if (!dragStarted) {
            if (Math.abs(dx) < DRAG_THRESHOLD_PX) return;
            dragStarted = true;
            scrollContainer.style.cursor = 'grabbing';
        }
        e.preventDefault();
        scrollContainer.scrollLeft = scrollLeftStart - dx * SCROLL_SPEED;
        updateFolderFiltersScrollIndicators();
    });

    document.addEventListener('mouseup', endMouseDrag);

    // Touch: natives horizontales Scrollen (touch-action: pan-x auf Leiste + Buttons)
    scrollContainer.addEventListener('scroll', function() {
        updateFolderFiltersScrollIndicators();
    });

    updateFolderFiltersScrollIndicators();
    window.addEventListener('resize', function() {
        updateFolderFiltersScrollIndicators();
    });
}


function updateFolderFiltersScrollIndicators() {
    const scrollContainer = document.getElementById('folderFiltersScroll');
    const wrapper = scrollContainer?.parentElement;
    if (!scrollContainer || !wrapper) return;
    
    const hasScroll = scrollContainer.scrollWidth > scrollContainer.clientWidth;
    const scrollLeft = scrollContainer.scrollLeft;
    const scrollRight = scrollLeft + scrollContainer.clientWidth;
    const maxScroll = scrollContainer.scrollWidth;
    
    if (hasScroll) {
        if (scrollLeft > 0) {
            wrapper.classList.add('has-scroll-left');
        } else {
            wrapper.classList.remove('has-scroll-left');
        }
        
        if (scrollRight < maxScroll - 1) { // -1 wegen Rundungsfehlern
            wrapper.classList.add('has-scroll-right');
        } else {
            wrapper.classList.remove('has-scroll-right');
        }
    } else {
        wrapper.classList.remove('has-scroll-left', 'has-scroll-right');
    }
}

// Funktion zum Anpassen der "Aufgaben hinzufügen" Leiste an die Sidebar-Breite
function updateQuickTodoBarPosition() {
    const quickTodoBar = document.getElementById('quickTodoBar');
    const sidebar = document.getElementById('sidebar');
    
    if (!quickTodoBar || !sidebar) return;
    
    // Prüfe ob wir auf einem großen Bildschirm sind
    if (window.innerWidth >= 1024) {
        // Hole die aktuelle Breite der Sidebar
        const sidebarWidth = sidebar.offsetWidth;
        // Setze die linke Position der Leiste entsprechend
        quickTodoBar.style.left = sidebarWidth + 'px';
    } else {
        // Auf mobilen Geräten: Leiste über die volle Breite
        quickTodoBar.style.left = '0px';
    }
}

// Initial beim Laden und bei Sidebar-Änderungen
document.addEventListener('DOMContentLoaded', function() {
    updateQuickTodoBarPosition();
    
    // Überwache Änderungen der Sidebar-Breite
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        // MutationObserver um Änderungen an der Sidebar zu überwachen
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    // Kurze Verzögerung, damit die CSS-Transition abgeschlossen ist
                    setTimeout(updateQuickTodoBarPosition, 1);
                }
            });
        });
        
        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });
        
        // Auch bei Resize-Events aktualisieren
        window.addEventListener('resize', function() {
            updateQuickTodoBarPosition();
            if (window.innerWidth >= 1024 && typeof window.__todosSetMobileQuickComposeOpen === 'function') {
                document.body.classList.remove('todos-mobile-quick-compose');
                var qbar = document.getElementById('quickTodoBar');
                if (qbar) qbar.style.removeProperty('transition');
                var ol = document.getElementById('navMobileTodosQuickOpenLabel');
                var cb = document.getElementById('navMobileTodosQuickCloseBtn');
                if (ol) {
                    ol.style.display = '';
                    ol.removeAttribute('aria-hidden');
                    ol.setAttribute('aria-expanded', 'false');
                }
                if (cb) {
                    cb.style.display = '';
                    cb.setAttribute('aria-hidden', 'true');
                    cb.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }
    
    // Event-Listener für Sidebar-Toggle-Button
    const toggleButton = document.getElementById('togglSidebarButton');
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            // Kurze Verzögerung, damit die CSS-Transition abgeschlossen ist
            setTimeout(updateQuickTodoBarPosition, 350);
        });
    }
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
