<?php
// BASE_URL definieren falls nicht vorhanden
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Default-Farben (aus head.php Kommentaren)
$primaryColors = [
    50 => '#1f1f1f', 100 => '#292a2d', 120 => '#1b1b1c', 140 => '#0B1938',
    200 => '#8c8f92', 210 => '#75787c', 220 => '#64748B', 230 => '#1E293B', 240 => '#64748B',
    250 => '#4f46e5', 260 => '#4338ca', 270 => '#3730a3', 280 => '#6366f1',
    300 => '#020617', 320 => '#111827', 340 => '#1E293B', 360 => '#6366f1', 380 => 'rgba(79,70,229,0.3)', 400 => '#475569',
    420 => '#4f46e5', 440 => '#4338ca', 460 => '#3730a3', 480 => '#020617', 500 => '#1E293B', 520 => '#64748B',
    540 => '#0B1226', 560 => '#111827', 580 => '#8c8f92', 600 => '#0B1938', 620 => '#10204A', 640 => '#6366f1',
    660 => '#4f46e5', 680 => '#818cf8',
    700 => '#020617', 720 => '#111827', 740 => '#75787c', 760 => '#0B1938', 780 => '#8c8f92', 800 => '#10204A', 820 => '#3B82F6', 840 => '#8c8f92',
    860 => '#0B1226', 880 => '#111827', 900 => '#0A1020', 920 => '#8c8f92', 940 => '#0B1938', 960 => '#0F1A33', 980 => 'rgba(255,255,255,0.02)', 1000 => 'rgba(255,255,255,0.03)', 1020 => '#10204A',
    1040 => '#22C55E', 1060 => '#F59E0B', 1080 => '#EF4444', 1100 => '#38BDF8'
];
$primaryLightColors = [
    50 => '#f7fafc', 100 => '#FFFFFF', 120 => '#E5E7EB', 140 => '#F3F4F6',
    200 => '#111827', 210 => '#6B7280', 220 => '#9CA3AF', 230 => '#E5E7EB', 240 => '#9CA3AF',
    250 => '#4f46e5', 260 => '#4338ca', 270 => '#3730a3', 280 => '#6366f1',
    300 => '#FFFFFF', 320 => '#D1D5DB', 340 => '#9CA3AF', 360 => '#6366f1', 380 => 'rgba(79,70,229,0.3)', 400 => '#9CA3AF',
    420 => '#4f46e5', 440 => '#4338ca', 460 => '#3730a3', 480 => '#FFFFFF', 500 => '#E5E7EB', 520 => '#9CA3AF',
    540 => '#FFFFFF', 560 => '#D1D5DB', 580 => '#374151', 600 => '#F3F4F6', 620 => '#E5E7EB', 640 => '#6366f1',
    660 => '#4f46e5', 680 => '#818cf8',
    700 => '#FFFFFF', 720 => '#D1D5DB', 740 => '#6B7280', 760 => '#F3F4F6', 780 => '#111827', 800 => '#E5E7EB', 820 => '#3B82F6', 840 => '#111827',
    860 => '#FFFFFF', 880 => '#E5E7EB', 900 => '#f7fafc', 920 => '#111827', 940 => '#F3F4F6', 960 => '#F3F4F6', 980 => 'rgba(0,0,0,0.02)', 1000 => 'rgba(0,0,0,0.05)', 1020 => '#DBEAFE',
    1040 => '#22C55E', 1060 => '#F59E0B', 1080 => '#EF4444', 1100 => '#38BDF8'
];
// Farben werden immer aus den Standardwerten verwendet - keine Anpassung über die Datenbank mehr möglich
// if (isset($pdo)) {
//     try {
//         $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_colors', 'branding_colors_light')");
//         $stmt->execute();
//         foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
//             $custom = $row['setting_value'] ? json_decode($row['setting_value'], true) : null;
//             if (is_array($custom)) {
//                 if ($row['setting_key'] === 'branding_colors') {
//                     $primaryColors = array_replace($primaryColors, $custom);
//                 } else {
//                     $primaryLightColors = array_replace($primaryLightColors, $custom);
//                 }
//             }
//         }
//     } catch (PDOException $e) {
//         // Fehler ignorieren, Defaults verwenden
//     }
// }

$headFavicon = 'assets/images/Serohub_Icon.png';
$headNamePart1 = 'Serohub';
$headNamePart2 = '';
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2')");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['setting_key'] === 'branding_logo' && !empty(trim($r['setting_value'] ?? ''))) $headFavicon = trim($r['setting_value']);
            if ($r['setting_key'] === 'branding_name_part1' && $r['setting_value'] !== null) $headNamePart1 = trim($r['setting_value']);
            if ($r['setting_key'] === 'branding_name_part2' && $r['setting_value'] !== null) $headNamePart2 = trim($r['setting_value']);
        }
    } catch (PDOException $e) {}
}
$headAppName = trim($headNamePart1 . ' ' . $headNamePart2);
$headPageTitle = isset($pageTitle) ? (htmlspecialchars($pageTitle) . ' | ' . htmlspecialchars($headAppName)) : (htmlspecialchars($headAppName) . ' ·  v0.1.5 (Beta)');
?>
<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='robots' content='noindex, nofollow'>
    <meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover, interactive-widget=resizes-content'>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <?php
    $headThemeLight = '#F8F9FC';
    $headThemeDark = $primaryColors[50] ?? '#090909';
    ?>
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="<?php echo htmlspecialchars($headThemeLight); ?>">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="<?php echo htmlspecialchars($headThemeDark); ?>">
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="manifest" href="<?php echo htmlspecialchars(BASE_URL . 'manifest.php'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(BASE_URL . ltrim($headFavicon, '/')); ?>">
    <title><?php echo $headPageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>



    <script>
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        colors: {
          primary: <?php echo json_encode($primaryColors); ?>,
          primaryLight: <?php echo json_encode($primaryLightColors); ?>,
          gray: {
            50: '#F8F9FC'
          }
        },

        borderRadius: {
          'base': '0.375rem',
          's-base': '0.375rem 0 0 0.375rem',
          'e-base': '0 0.375rem 0.375rem 0'
        },

        boxShadow: {
          'xs': '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
          'card': '0 0 0 1px rgba(255,255,255,0.04) inset',
          'focus': '0 0 0 2px rgba(59,130,246,0.3)'
        }
      }
    }
  }
</script>
    <style>
      /* Hellmodus: Seitenfläche vs. Nav/Sidebar-Chrome */
      :root {
        --page-bg: #F8F9FC;
        --app-chrome-bg: #ffffff;
        --app-chrome-border: #e5e7eb;
        --app-nav-height: 3.5rem;
        --app-nav-sidebar-corner: 1.75rem;
        --app-content-border-width: 1px;
        --app-content-inset-top: 2.5rem;
        --app-content-inset-start: 2.5rem;
        --app-content-inset-end: 2.25rem;
      }
      .dark {
        --page-bg: <?php echo htmlspecialchars($primaryColors[50] ?? '#090909'); ?>;
        --app-chrome-bg: <?php echo htmlspecialchars($primaryColors[50] ?? '#1f1f1f'); ?>;
        --app-chrome-border: <?php echo htmlspecialchars($primaryColors[140] ?? '#374151'); ?>;
      }
      html {
        background-color: var(--page-bg);
      }
      html:not(.dark) body {
        background-color: var(--page-bg);
      }
      html:not(.dark) .app-layout-shell {
        background-color: var(--page-bg) !important;
      }
      html:not(.dark) #main-content {
        background-color: var(--page-bg) !important;
      }
      @media (min-width: 1024px) {
        /* Desktop: Shell = weißes L-Chrome, Inhalt = abgerundete Seitenfläche (sichtbare Ecke) */
        html:not(.dark) .app-layout-shell,
        html.dark .app-layout-shell {
          background-color: var(--app-chrome-bg) !important;
        }
        html:not(.dark) #main-nav {
          background-color: var(--app-chrome-bg) !important;
        }
        html.dark #main-content {
          background-color: <?php echo htmlspecialchars($primaryColors[100] ?? '#292a2d'); ?> !important;
        }
      }
      html:not(.dark) #sidebar .sidebar-nav-scroll {
        background-color: var(--app-chrome-bg) !important;
      }
      /*
       * Native UI (iOS/Android): Tastatur & Formular-Chrome folgen oft color-scheme.
       * Mobil ist die App absichtlich hell (html ohne .dark) — ohne explizites „light“ kann WebKit trotzdem Dark-Keyboard zeigen.
       */
      /* Leicht kleinere Basis-Schrift für die gesamte App */
      html {
        color-scheme: light;
        font-size: 15px;
      }
      @media (min-width: 1024px) {
        html.dark { color-scheme: dark; }
      }
      @media (max-width: 1023px) {
        html {
          color-scheme: only light;
          /* Overscroll/Bounce: kein weißer Browser-Hintergrund hinter der App */
          background-color: #000000;
        }
      }

      /*
       * Dashboard mobil: früher Flex + Scroll nur in #main-content — auf iOS/WebKit oft kaputt (Höhe/Overflow).
       * Stattdessen normales Dokument-Scrollen (wie andere Seiten), Abstand oben für fixe Nav.
       */
      @media (max-width: 1023px) {
        /*
         * Wie nav.php (nicht-Dashboard): Scroll nur in #main-content — obere Rundung bleibt unter der Nav.
         * Früher: body scrollte (iOS-Probleme mit Flex); mit min-h-0 + flex-1 ist die Kette jetzt explizit.
         */
        body.app-mobile-dashboard-shell {
          display: flex;
          flex-direction: column;
          box-sizing: border-box;
          min-height: 100dvh;
          max-height: 100dvh;
          overflow: hidden;
          overflow-x: hidden;
          padding-top: 0;
        }
        body.app-mobile-dashboard-shell::before {
          content: '';
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          z-index: 19;
          pointer-events: none;
          height: constant(safe-area-inset-top);
          height: env(safe-area-inset-top, 0px);
          /* Handy: zu schwarzer Top-Nav (nav.php); nicht grau — sonst Streifen über der Leiste */
          background: #000000;
        }
        body.app-mobile-dashboard-shell .app-layout-shell {
          flex: 1 1 0%;
          min-width: 0;
          min-height: 0;
          display: flex !important;
          flex-direction: row;
          align-items: stretch;
          overflow: hidden;
          padding-top: calc(3.5rem + env(safe-area-inset-top, 0px));
          box-sizing: border-box;
          width: 100%;
        }
        body.app-mobile-dashboard-shell #main-content {
          flex: 1 1 0%;
          width: 100%;
          min-width: 0;
          min-height: 0;
          overflow-x: hidden !important;
          overflow-y: auto !important;
          -webkit-overflow-scrolling: touch;
          touch-action: pan-y;
          display: block !important;
        }
        body.app-mobile-dashboard-shell #main-content.app-mobile-no-root-overscroll {
          overscroll-behavior-y: contain;
        }
      }
      /* Native Date/Time-Picker im Dark Mode dunkel darstellen */
      .dark input[type="datetime-local"],
      .dark input[type="date"],
      .dark input[type="time"] {
        color-scheme: dark;
      }
      /* Handy: immer Hellmodus-Inhalt — Picker nicht als Dark-UI */
      @media (max-width: 1023px) {
        input[type="datetime-local"],
        input[type="date"],
        input[type="time"] {
          color-scheme: light;
        }
      }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.5.1/flowbite.min.js" defer></script>
    <script src="<?php echo htmlspecialchars(BASE_URL . 'assets/js/system-sounds.js'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(BASE_URL . 'assets/js/global-shortcuts.js'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(BASE_URL . 'assets/js/ui-haptics.js'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(BASE_URL . 'assets/js/sidebar-nav-status.js'); ?>" defer></script>

    <link rel='icon' type='image/x-icon' href='<?php echo BASE_URL . ltrim($headFavicon, '/'); ?>'>
    <?php if (!empty($serviceMobileFullscreen)): ?>
    <style>
    /* Basis: Seite nicht scrollen, nur der Chat – für alle Auflösungen */
    html:has(body.service-mobile-fullscreen) {
      height: 100%;
    }
    body.service-mobile-fullscreen {
      overflow: hidden !important;
      display: flex;
      flex-direction: column;
      box-sizing: border-box;
      height: 100%;
      min-height: 100vh;
      min-height: 100dvh;
      min-height: -webkit-fill-available;
    }
    body.service-mobile-fullscreen > nav.fixed { flex-shrink: 0; }
    body.service-mobile-fullscreen .service-mobile-fullscreen-wrapper {
      flex: 1 1 0%;
      min-height: 0;
      overflow: hidden;
      height: 100%;
      display: flex;
      flex-direction: row;
      align-items: stretch;
    }
    body.service-mobile-fullscreen #main-content {
      overflow: hidden;
      flex: 1 1 0%;
      min-height: 0;
      height: 100%;
      max-height: 100%;
      display: flex;
      flex-direction: column;
    }
    body.service-mobile-fullscreen #main-content main {
      flex: 1 1 0%;
      min-height: 0;
      height: 100%;
      max-height: 100%;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    body.service-mobile-fullscreen #service-view-chat-grid { min-height: 0; }
    body.service-mobile-fullscreen #service-view-chat-column { min-height: 0; overflow: hidden; }
    body.service-mobile-fullscreen #chatTicketContent { overflow-y: auto !important; min-height: 0; flex: 1 1 0; -webkit-overflow-scrolling: touch; }
    @media (max-width: 1023px) {
      body.service-mobile-fullscreen #chatTicketContent { padding-bottom: env(safe-area-inset-bottom, 0px) !important; touch-action: pan-y !important; }
    }
    @media (max-width: 768px) {
      body.service-mobile-fullscreen:not(.ticket-view-mobile-shell) > nav.fixed { display: none !important; }
      body.service-mobile-fullscreen.ticket-view-mobile-shell > nav.fixed { display: flex !important; }
      body.service-mobile-fullscreen.ticket-view-mobile-shell #main-content {
        padding-top: calc(env(safe-area-inset-top, 0px) + 3.5rem) !important;
        margin-top: 0 !important;
      }
      body.service-mobile-fullscreen { padding-top: 0 !important; }
      body.service-mobile-fullscreen .service-mobile-fullscreen-wrapper { padding-top: 0 !important; }
      body.service-mobile-fullscreen #main-content { padding-top: 0 !important; margin-top: 0 !important; }
      body.service-mobile-fullscreen #main-content main { padding: 0 !important; margin: 0 !important; max-width: none !important; }
      body.service-mobile-fullscreen #main-content main > * { margin-top: 0 !important; padding-top: 0 !important; }
      body.service-mobile-fullscreen #main-content .pr-4 { padding-right: 0 !important; padding-left: 0 !important; }
      body.service-mobile-fullscreen #main-content .px-4 { padding-left: 0 !important; padding-right: 0 !important; }
      body.service-mobile-fullscreen #main-content .mx-4 { margin-left: 0 !important; margin-right: 0 !important; }
      body.service-mobile-fullscreen #main-content .mt-4 { margin-top: 0 !important; }
      body.service-mobile-fullscreen #main-content .gap-4 { gap: 0 !important; }
      body.service-mobile-fullscreen #service-view-chat-grid { flex: 1 1 0% !important; min-height: 0 !important; height: auto !important; gap: 0 !important; display: flex !important; flex-direction: column !important; }
      body.service-mobile-fullscreen #service-view-chat-column { border-radius: 0 !important; box-shadow: none !important; border: none !important; flex: 1 1 0% !important; min-height: 0 !important; height: auto !important; display: flex !important; flex-direction: column !important; }
      body.service-mobile-fullscreen #service-view-chat-column #chatTicketHeader { flex-shrink: 0 !important; }
      body.service-mobile-fullscreen #service-view-chat-column #chatTicketContent { flex: 1 1 0 !important; min-height: 0 !important; overflow-y: auto !important; -webkit-overflow-scrolling: touch; }
      body.service-mobile-fullscreen #service-view-chat-column #chatInputArea { flex-shrink: 0 !important; }
      body.service-mobile-fullscreen #chatView { flex: 1 1 0% !important; min-height: 0 !important; height: auto !important; border-radius: 0 !important; border: none !important; box-shadow: none !important; display: flex !important; flex-direction: column !important; }
      body.service-mobile-fullscreen #chatView .rounded-base { border-radius: 0 !important; }
      body.service-mobile-fullscreen #main-content main .p-4 { padding: 1rem !important; }
      /* Kompakter Chat-Header auf Mobile */
      body.service-mobile-fullscreen #chatTicketHeader { padding: 0.5rem 0.75rem !important; }
      body.service-mobile-fullscreen #chatTicketHeader h2 { font-size: 0.875rem !important; line-height: 1.25rem !important; }
      body.service-mobile-fullscreen #chatTicketHeader p { font-size: 0.6875rem !important; line-height: 1rem !important; }
      body.service-mobile-fullscreen #chatTicketHeader img { width: 2rem !important; height: 2rem !important; }
      body.service-mobile-fullscreen #chatTicketHeader > div { gap: 0.25rem !important; }
      body.service-mobile-fullscreen #chatTicketHeader .gap-2 { gap: 0.25rem !important; }
      body.service-mobile-fullscreen #chatTicketHeader .gap-3 { gap: 0.375rem !important; }
    }
    </style>
    <?php endif; ?>
</head>



<script>
  // Device-Fingerprinting: Screen-Resolution und Timezone in Cookie speichern
  (function() {
    // Screen-Resolution
    if (screen.width && screen.height) {
      const resolution = screen.width + 'x' + screen.height;
      document.cookie = 'screen_resolution=' + resolution + '; path=/; max-age=2592000'; // 30 Tage
    }
    
    // Timezone
    try {
      const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
      document.cookie = 'timezone=' + timezone + '; path=/; max-age=2592000'; // 30 Tage
    } catch (e) {
      // Fallback für ältere Browser
      const offset = new Date().getTimezoneOffset();
      document.cookie = 'timezone=' + offset + '; path=/; max-age=2592000';
    }
  })();
  
  (function () {
    var darkModeEnabled = <?php echo (defined('DARK_MODE_ENABLED') && DARK_MODE_ENABLED) ? 'true' : 'false'; ?>;
    function prefersDarkDesktop() {
      if (!darkModeEnabled) return false;
      return localStorage.getItem('color-theme') === 'dark' ||
        (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }
    function isMobileLayout() {
      return window.matchMedia('(max-width: 1023px)').matches;
    }
    function syncHtmlDarkClass() {
      if (!darkModeEnabled || isMobileLayout()) {
        document.documentElement.classList.remove('dark');
      } else if (prefersDarkDesktop()) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    }
    syncHtmlDarkClass();
  if (darkModeEnabled) {
    window.addEventListener('resize', syncHtmlDarkClass);
    try {
      window.matchMedia('(max-width: 1023px)').addEventListener('change', syncHtmlDarkClass);
    } catch (e) {
      window.matchMedia('(max-width: 1023px)').addListener(syncHtmlDarkClass);
    }
    /* Mit darkMode: 'class' muss das OS-Theme nachgeladen werden (ohne manuelles color-theme) */
    function onSystemColorSchemeChange() {
      if (!isMobileLayout() && !('color-theme' in localStorage)) {
        syncHtmlDarkClass();
      }
    }
    try {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', onSystemColorSchemeChange);
    } catch (e) {
      window.matchMedia('(prefers-color-scheme: dark)').addListener(onSystemColorSchemeChange);
    }
  }
  })();

  // Browser-übergreifendes Öffnen nativer Date/Time-Picker (inkl. Firefox-Fallback).
  window.openNativePicker = function(inputOrId) {
    var input = inputOrId;
    if (typeof inputOrId === 'string') {
      input = document.getElementById(inputOrId);
    }
    if (!input) return;
    try {
      if (typeof input.showPicker === 'function') {
        input.showPicker();
        return;
      }
    } catch (e) {
      // Fallback unten.
    }

    // Firefox/ältere Browser: Fokus + synthetischer Klick.
    try {
      input.focus({ preventScroll: true });
    } catch (e) {
      input.focus();
    }
    try {
      input.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
      input.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
      input.click();
    } catch (e) {
      // Letzter Fallback.
      try { input.click(); } catch (err) {}
    }
  };
</script>



  <style
  
  data-tiptap-style=''>.ProseMirror {
  position: relative;
}

.ProseMirror {
  word-wrap: break-word;
  white-space: pre-wrap;
  white-space: break-spaces;
  -webkit-font-variant-ligatures: none;
  font-variant-ligatures: none;
  font-feature-settings: 'liga' 0; /* the above doesn't seem to work in Edge */
}

.ProseMirror [contenteditable='false'] {
  white-space: normal;
}

.ProseMirror [contenteditable='false'] [contenteditable='true'] {
  white-space: pre-wrap;
}

.ProseMirror pre {
  white-space: pre-wrap;
}

img.ProseMirror-separator {
  display: inline !important;
  border: none !important;
  margin: 0 !important;
  width: 0 !important;
  height: 0 !important;
}

.ProseMirror-gapcursor {
  display: none;
  pointer-events: none;
  position: absolute;
  margin: 0;
}

.ProseMirror-gapcursor:after {
  content: '';
  display: block;
  position: absolute;
  top: -2px;
  width: 20px;
  border-top: 1px solid black;
  animation: ProseMirror-cursor-blink 1.1s steps(2, start) infinite;
}

@keyframes ProseMirror-cursor-blink {
  to {
    visibility: hidden;
  }
}

.ProseMirror-hideselection *::selection {
  background: transparent;
}

.ProseMirror-hideselection *::-moz-selection {
  background: transparent;
}

.ProseMirror-hideselection * {
  caret-color: transparent;
}

.ProseMirror-focused .ProseMirror-gapcursor {
  display: block;
}

/* Wissensdatenbank-Editor: Blocktypen klar unterscheidbar (H1/H2/H3, Zitat, Code, Listen) */
.ProseMirror.kb-editor-body > * + * { margin-top: 0.75em; }
.ProseMirror.kb-editor-body h1 { font-size: 1.875rem; font-weight: 700; line-height: 1.2; margin-top: 1.5em; margin-bottom: 0.5em; color: inherit; }
.ProseMirror.kb-editor-body h1:first-child { margin-top: 0; }
.ProseMirror.kb-editor-body h2 { font-size: 1.5rem; font-weight: 600; line-height: 1.3; margin-top: 1.25em; margin-bottom: 0.5em; color: inherit; }
.ProseMirror.kb-editor-body h3 { font-size: 1.25rem; font-weight: 600; line-height: 1.4; margin-top: 1em; margin-bottom: 0.4em; color: inherit; }
.ProseMirror.kb-editor-body p { font-size: 1rem; line-height: 1.6; margin: 0.25em 0; }
.ProseMirror.kb-editor-body blockquote { border-left: 4px solid #3b82f6; padding-left: 1rem; margin: 0.75em 0; font-style: italic; opacity: 0.95; }
.dark .ProseMirror.kb-editor-body blockquote { border-left-color: #60a5fa; }
.ProseMirror.kb-editor-body pre { background: rgba(0,0,0,0.06); border-radius: 0.375rem; padding: 1rem; font-size: 0.875rem; line-height: 1.5; overflow-x: auto; margin: 0.75em 0; }
.dark .ProseMirror.kb-editor-body pre { background: rgba(255,255,255,0.08); }
.ProseMirror.kb-editor-body pre code { font-family: ui-monospace, monospace; background: none; padding: 0; }
.ProseMirror.kb-editor-body :not(pre) > code { font-size: 0.875em; font-family: ui-monospace, monospace; background: rgba(0,0,0,0.06); padding: 0.15em 0.4em; border-radius: 0.25rem; }
.dark .ProseMirror.kb-editor-body :not(pre) > code { background: rgba(255,255,255,0.12); }
.ProseMirror.kb-editor-body ul, .ProseMirror.kb-editor-body ol { padding-left: 1.75rem; margin: 0.5em 0; list-style-position: outside; }
.ProseMirror.kb-editor-body ul { list-style-type: disc; }
.ProseMirror.kb-editor-body ol { list-style-type: decimal; }
.ProseMirror.kb-editor-body li { margin: 0.25em 0; display: list-item; }
.ProseMirror.kb-editor-body ul[data-type="taskList"] { list-style: none; padding-left: 0; }
.ProseMirror.kb-editor-body ul[data-type="taskList"] li { display: flex; align-items: flex-start; gap: 0.5rem; }
.ProseMirror.kb-editor-body ul[data-type="taskList"] li[data-checked="true"] { opacity: 0.7; text-decoration: line-through; }
.ProseMirror.kb-editor-body hr { border: none; border-top: 1px solid rgba(0,0,0,0.15); margin: 1.25em 0; }
.dark .ProseMirror.kb-editor-body hr { border-top-color: rgba(255,255,255,0.2); }

/* Wissensdatenbank: Tabellen-Rahmen */
.ProseMirror.kb-editor-body table { border-collapse: collapse; width: 100%; margin: 0.75em 0; }
.ProseMirror.kb-editor-body table td,
.ProseMirror.kb-editor-body table th { border: 1px solid rgba(0,0,0,0.2); padding: 0.5rem 0.75rem; text-align: left; vertical-align: top; }
.ProseMirror.kb-editor-body table th { font-weight: 600; background: rgba(59,130,246,0.12); color: rgba(30,41,59,0.95); }
.dark .ProseMirror.kb-editor-body table td,
.dark .ProseMirror.kb-editor-body table th { border-color: rgba(255,255,255,0.25); }
.dark .ProseMirror.kb-editor-body table th { background: rgba(59,130,246,0.22); color: rgba(248,250,252,0.95); }

/* Wissensdatenbank: farbige Container (Callouts) – Klasse und data-callout */
.ProseMirror.kb-editor-body .kb-callout,
.ProseMirror.kb-editor-body [data-callout] { padding: 0.75rem 1rem; border-radius: 0.375rem; border-left: 4px solid; margin: 0.75em 0; box-sizing: border-box; }
.ProseMirror.kb-editor-body .kb-callout-default,
.ProseMirror.kb-editor-body [data-callout="default"] { background-color: #f1f5f9; border-left-color: #64748b; }
.ProseMirror.kb-editor-body .kb-callout-warning,
.ProseMirror.kb-editor-body [data-callout="warning"] { background-color: #fef3c7; border-left-color: #f59e0b; }
.ProseMirror.kb-editor-body .kb-callout-error,
.ProseMirror.kb-editor-body [data-callout="error"] { background-color: #fee2e2; border-left-color: #ef4444; }
.ProseMirror.kb-editor-body .kb-callout-success,
.ProseMirror.kb-editor-body [data-callout="success"] { background-color: #dcfce7; border-left-color: #22c55e; }
.dark .ProseMirror.kb-editor-body .kb-callout-default,
.dark .ProseMirror.kb-editor-body [data-callout="default"] { background-color: #334155; border-left-color: #94a3b8; }
.dark .ProseMirror.kb-editor-body .kb-callout-warning,
.dark .ProseMirror.kb-editor-body [data-callout="warning"] { background-color: #451a03; border-left-color: #fbbf24; }
.dark .ProseMirror.kb-editor-body .kb-callout-error,
.dark .ProseMirror.kb-editor-body [data-callout="error"] { background-color: #450a0a; border-left-color: #f87171; }
.dark .ProseMirror.kb-editor-body .kb-callout-success,
.dark .ProseMirror.kb-editor-body [data-callout="success"] { background-color: #052e16; border-left-color: #4ade80; }

/* Wissensdatenbank: Slash-Popup Scrollbar */
#kb-slash-menu {
  scrollbar-width: thin;
  scrollbar-color: rgba(100, 116, 139, 0.5) transparent;
}
.dark #kb-slash-menu {
  scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
}
#kb-slash-menu::-webkit-scrollbar {
  width: 6px;
}
#kb-slash-menu::-webkit-scrollbar-track {
  background: transparent;
  border-radius: 3px;
}
#kb-slash-menu::-webkit-scrollbar-thumb {
  background: rgba(100, 116, 139, 0.4);
  border-radius: 3px;
}
#kb-slash-menu::-webkit-scrollbar-thumb:hover {
  background: rgba(100, 116, 139, 0.6);
}
.dark #kb-slash-menu::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.35);
}
.dark #kb-slash-menu::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.55);
}

/* Sidebar: schlanker, abgerundeter Scrollbalken */
.sidebar-nav-scroll {
  scrollbar-width: thin;
  scrollbar-gutter: stable;
  scrollbar-color: rgba(148, 163, 184, 0.45) transparent;
}
.dark .sidebar-nav-scroll {
  scrollbar-color: rgba(148, 163, 184, 0.35) transparent;
}
.sidebar-nav-scroll::-webkit-scrollbar {
  width: 6px;
}
.sidebar-nav-scroll::-webkit-scrollbar-track {
  background: transparent;
  border-radius: 3px;
  margin: 4px 0;
}
.sidebar-nav-scroll::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.4);
  border-radius: 3px;
}
.sidebar-nav-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.6);
}
.sidebar-nav-scroll::-webkit-scrollbar-thumb:active {
  background: rgba(148, 163, 184, 0.7);
}
.dark .sidebar-nav-scroll::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.3);
}
.dark .sidebar-nav-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.5);
}
.dark .sidebar-nav-scroll::-webkit-scrollbar-thumb:active {
  background: rgba(148, 163, 184, 0.6);
}

.tippy-box[data-animation=fade][data-state=hidden] {
    opacity: 0
}

/* Benutzerdefinierte Rundungen für Button-Gruppen */
.rounded-base {
    border-radius: 0.375rem;
}

.rounded-s-base {
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
}

.rounded-e-base {
    border-top-right-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}

.shadow-xs {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

</style>