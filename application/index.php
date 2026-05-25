<?php
// Session starten, falls noch nicht gestartet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// BASE_URL definieren falls nicht vorhanden
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Prüfen ob Benutzer noch eingeloggt ist
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Benutzer ist noch eingeloggt, zielgerichtet weiterleiten
    require_once __DIR__ . '/assets/config.php';

    // Funktion zur Erkennung mobiler Geräte
    function isMobileDevice() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|opera mini|windows phone|iemobile|webos)/i', $userAgent);
    }
    
    // Funktion zur Bestimmung des Zielordners basierend auf Gerätetyp und User-Einstellung
    function getRedirectPath(PDO $pdo, int $userId) {
        if (!isMobileDevice()) {
            return '/dashboard/';
        }

        $defaultMobilePath = '/tickets/';
        $mode = 'fixed';
        $page = 'tickets';

        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'mobile_start_page' LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && is_string($row['setting_value'] ?? null) && $row['setting_value'] !== '') {
                $decoded = json_decode($row['setting_value'], true);
                if (is_array($decoded)) {
                    $candidateMode = (string) ($decoded['mode'] ?? '');
                    $candidatePage = (string) ($decoded['page'] ?? '');
                    if (in_array($candidateMode, ['fixed', 'last'], true)) {
                        $mode = $candidateMode;
                    }
                    if (preg_match('/^[a-z0-9-]+$/i', $candidatePage)) {
                        $page = $candidatePage;
                    }
                }
            }
        } catch (Throwable $e) {
            // Fallback auf bisheriges Verhalten
            return $defaultMobilePath;
        }

        if ($mode === 'last') {
            $cookieName = 'mobile_last_path_user_' . $userId;
            $lastPathRaw = $_COOKIE[$cookieName] ?? '';
            $lastPath = rawurldecode((string) $lastPathRaw);
            if ($lastPath !== '' && str_starts_with($lastPath, '/')) {
                $isSafe = preg_match('#^/(dashboard|tickets|todos|inventory|service|knowledge|kalender|devices|orders|companies|customers|projects|notes)(/|$|\?)#', $lastPath) === 1;
                if ($isSafe) {
                    return $lastPath;
                }
            }
        }

        $allowedFixedPages = [
            'dashboard', 'tickets', 'todos', 'inventory', 'service', 'knowledge', 'kalender',
            'devices', 'orders', 'companies', 'customers', 'projects', 'notes'
        ];
        if (!in_array($page, $allowedFixedPages, true)) {
            return $defaultMobilePath;
        }

        return '/' . $page . '/';
    }
    
    header('Location: ' . getRedirectPath($pdo, (int) $_SESSION['user_id']));
    exit();
}

// Prüfen ob Benutzer sich gerade abgemeldet hat (über GET-Parameter)
$justLoggedOut = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';

// Wenn nicht eingeloggt und nicht gerade abgemeldet, zur Login-Seite weiterleiten
if (!$justLoggedOut) {
    header('Location: ' . BASE_URL . 'login/');
    exit();
}

// Benutzer ist ausgeloggt, Logout-Seite anzeigen – Titel und Logo aus Branding
$indexTitle = 'Serohub – Abmeldung';
$indexFavicon = BASE_URL . 'assets/images/Serohub_Icon.png';
$parts = ['Serohub', ''];
try {
    require_once __DIR__ . '/assets/config.php';
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2')");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['setting_key'] === 'branding_logo' && !empty(trim($r['setting_value'] ?? ''))) {
            $indexFavicon = BASE_URL . ltrim(trim($r['setting_value']), '/');
        }
        if ($r['setting_key'] === 'branding_name_part1' && $r['setting_value'] !== null) {
            $parts[0] = trim($r['setting_value']);
        }
        if ($r['setting_key'] === 'branding_name_part2' && $r['setting_value'] !== null) {
            $parts[1] = trim($r['setting_value']);
        }
    }
    $indexTitle = trim($parts[0] . ' ' . $parts[1]) . ' – Abmeldung';
} catch (Exception $e) {
    $indexTitle = trim($parts[0] . ' ' . $parts[1]) . ' – Abmeldung';
}
// Illustration Light-Mode: Bild unter assets/images/logout-illustration-light.svg (oder .png) einfügen – wird nur im Hellmodus angezeigt
$logoutIllustrationLight = BASE_URL . 'assets/images/logout-illustration-light.svg';
$logoutIllustrationDark  = true; // Dark-Mode nutzt die eingebettete SVG-Illustration
?>
<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='robots' content='noindex, nofollow'>
    <meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no'>
    <title><?php echo htmlspecialchars($indexTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: {
              gray: {
                50: '#f7fafc'
              },
              primary: {
                50: '#020617',
                100: '#171c2c',
                150: '#0f131e',
                200: ' #081426 ',
                250: '#0B1B33',
                300: '#93c5fd',
                350: '#cc9f88',
                400: '#60a5fa',
                450: '#cc9f88',
                500: '#d5a791',
                550: '#cc9f88',
                600: '#2563eb',
                650: '#cc9f88',
                700: '#1d4ed8',
                750: '#cc9f88',
                800: '#1e40af',
                850: '#0a1458',
                900: '#16b5bf',
                950: '#0E7F87',
              }
            }
          }
        }
      }
    </script>
    <link rel='icon' type='image/x-icon' href='<?php echo htmlspecialchars($indexFavicon); ?>'>
    <script>
      var darkModeEnabled = <?php echo (defined('DARK_MODE_ENABLED') && DARK_MODE_ENABLED) ? 'true' : 'false'; ?>;
      if (darkModeEnabled && (localStorage.getItem("color-theme") === "dark" || (!("color-theme" in localStorage) && window.matchMedia("(prefers-color-scheme: dark)").matches))) {
        document.documentElement.classList.add("dark");
      } else {
        document.documentElement.classList.remove("dark");
      }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 antialiased min-h-screen">
  <main class="min-h-screen">
    <section class="mx-auto flex min-h-screen flex-col items-center justify-center bg-white dark:bg-gray-900 px-4 py-12 sm:px-6 lg:px-8">
      <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div class="order-2 lg:order-1 text-center lg:text-left">
          <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl lg:text-5xl lg:leading-tight">
            Sie wurden erfolgreich abgemeldet.
          </h1>
          <p class="mt-4 text-lg text-gray-500 dark:text-gray-400 max-w-xl">
            Vielen Dank für die Nutzung unseres Systems. Wählen Sie einen der folgenden Links:
          </p>

          <!-- Quicklinks -->
          <div class="mt-8">
           
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <a href="<?php echo BASE_URL; ?>login/" class="group flex items-center gap-3 rounded-xl bg-primary-600 px-4 py-3 text-white font-medium shadow-lg shadow-primary-600/25 hover:bg-primary-700 hover:shadow-primary-700/30 transition-all">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20 text-white">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                </span>
                <span class="font-semibold">Erneut anmelden</span>
              </a>
              <a href="<?php echo BASE_URL; ?>tickets/" class="group flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-700 dark:text-gray-200 hover:bg-gray-100 hover:border-gray-300 dark:hover:bg-gray-700 dark:hover:border-gray-500 transition-all">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 group-hover:bg-gray-200 dark:group-hover:bg-gray-600">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/>
</svg>
</span>
                <span class="font-medium">Support &amp; Tickets</span>
              </a>
              <a href="https://serohub.de" class="group flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-700 dark:text-gray-200 hover:bg-gray-100 hover:border-gray-300 dark:hover:bg-gray-700 dark:hover:border-gray-500 transition-all">
                <img src="<?php echo BASE_URL; ?>assets/images/Serohub_Icon.png" class="h-10 w-10 object-contain shrink-0" alt="" aria-hidden="true">
                <span class="font-medium">Serohub</span>
              </a>
              <a href="https://google.de" class="group flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-gray-700 dark:text-gray-200 hover:bg-gray-100 hover:border-gray-300 dark:hover:bg-gray-700 dark:hover:border-gray-500 transition-all">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 group-hover:bg-gray-200 dark:group-hover:bg-gray-600">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path fill-rule="evenodd" d="M12.037 21.998a10.313 10.313 0 0 1-7.168-3.049 9.888 9.888 0 0 1-2.868-7.118 9.947 9.947 0 0 1 3.064-6.949A10.37 10.37 0 0 1 12.212 2h.176a9.935 9.935 0 0 1 6.614 2.564L16.457 6.88a6.187 6.187 0 0 0-4.131-1.566 6.9 6.9 0 0 0-4.794 1.913 6.618 6.618 0 0 0-2.045 4.657 6.608 6.608 0 0 0 1.882 4.723 6.891 6.891 0 0 0 4.725 2.07h.143c1.41.072 2.8-.354 3.917-1.2a5.77 5.77 0 0 0 2.172-3.41l.043-.117H12.22v-3.41h9.678c.075.617.109 1.238.1 1.859-.099 5.741-4.017 9.6-9.746 9.6l-.215-.002Z" clip-rule="evenodd"/>
</svg>
</span>
                <span class="font-medium">Google</span>
              </a>
            </div>
          </div>
        </div>

        <!-- Illustration: Light-Mode (eigenes Bild) / Dark-Mode (SVG) -->
        <div class="order-1 lg:order-2 hidden md:flex items-center justify-center">
        <svg class="w-auto  text-gray-800 dark:text-white" aria-hidden="true" width="524" height="540" viewBox="0 0 524 540" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M524 278C524 422.699 406.699 540 262 540C117.301 540 0 422.699 0 278C0 133.301 117.301 16 262 16C406.699 16 524 133.301 524 278Z" fill="#d6e2fb"/>
<path d="M524 278C524 422.699 406.699 540 262 540C117.301 540 0 422.699 0 278C0 133.301 117.301 16 262 16C406.699 16 524 133.301 524 278Z" fill="url(#paint0_linear_383_361)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M519.795 325C497.649 447.269 390.653 540 261.999 540C133.345 540 26.349 447.269 4.20312 325H519.795Z" fill="url(#paint1_linear_383_361)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M351 81V240C351 252.15 341.15 262 329 262H211C198.85 262 189 252.15 189 240V81C189 36.2649 225.265 0 270 0C314.735 0 351 36.2649 351 81ZM270 18C235.206 18 207 46.2061 207 81V240C207 242.209 208.791 244 211 244H329C331.209 244 333 242.209 333 240V81C333 46.2061 304.794 18 270 18Z" fill="#c8d8fa"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M351 81V240C351 252.15 341.15 262 329 262H211C198.85 262 189 252.15 189 240V81C189 36.2649 225.265 0 270 0C314.735 0 351 36.2649 351 81ZM270 18C235.206 18 207 46.2061 207 81V240C207 242.209 208.791 244 211 244H329C331.209 244 333 242.209 333 240V81C333 46.2061 304.794 18 270 18Z" fill="url(#paint2_linear_383_361)"/>
<path d="M195 165C195 162.791 193.209 161 191 161H140C137.791 161 136 162.791 136 165V377C136 379.209 137.791 381 140 381H191C193.209 381 195 379.209 195 377V165Z" fill="#d6e2fb"/>
<path d="M195 165C195 162.791 193.209 161 191 161H140C137.791 161 136 162.791 136 165V377C136 379.209 137.791 381 140 381H191C193.209 381 195 379.209 195 377V165Z" fill="url(#paint3_linear_383_361)"/>
<path d="M195 165C195 162.791 193.209 161 191 161H140C137.791 161 136 162.791 136 165V377C136 379.209 137.791 381 140 381H191C193.209 381 195 379.209 195 377V165Z" fill="url(#paint4_linear_383_361)"/>
<path d="M195 165C195 162.791 193.209 161 191 161H140C137.791 161 136 162.791 136 165V377C136 379.209 137.791 381 140 381H191C193.209 381 195 379.209 195 377V165Z" fill="url(#paint5_linear_383_361)"/>
<path d="M195 165C195 162.791 193.209 161 191 161H140C137.791 161 136 162.791 136 165V377C136 379.209 137.791 381 140 381H191C193.209 381 195 379.209 195 377V165Z" fill="#c8d8fa"/>
<path d="M195 165C195 162.791 193.209 161 191 161H140C137.791 161 136 162.791 136 165V377C136 379.209 137.791 381 140 381H191C193.209 381 195 379.209 195 377V165Z" fill="url(#paint6_linear_383_361)"/>
<path d="M174 165C174 162.791 175.791 161 178 161H384C386.209 161 388 162.791 388 165V377C388 379.209 386.209 381 384 381H178C175.791 381 174 379.209 174 377V165Z" fill="#d6e2fb"/>
<path d="M174 165C174 162.791 175.791 161 178 161H384C386.209 161 388 162.791 388 165V377C388 379.209 386.209 381 384 381H178C175.791 381 174 379.209 174 377V165Z" fill="url(#paint7_linear_383_361)"/>
<path d="M174 165C174 162.791 175.791 161 178 161H384C386.209 161 388 162.791 388 165V377C388 379.209 386.209 381 384 381H178C175.791 381 174 379.209 174 377V165Z" fill="url(#paint8_linear_383_361)"/>
<path d="M174 165C174 162.791 175.791 161 178 161H384C386.209 161 388 162.791 388 165V377C388 379.209 386.209 381 384 381H178C175.791 381 174 379.209 174 377V165Z" fill="url(#paint9_linear_383_361)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M295.245 261.045C301.137 256.988 305 250.195 305 242.5C305 230.074 294.926 220 282.5 220C270.074 220 260 230.074 260 242.5C260 250.195 263.863 256.988 269.755 261.045L263.251 318.776C263.117 319.962 264.045 321 265.238 321H299.762C300.955 321 301.883 319.962 301.749 318.776L295.245 261.045Z" fill="#2563eb"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M295.245 261.045C301.137 256.988 305 250.195 305 242.5C305 230.074 294.926 220 282.5 220C270.074 220 260 230.074 260 242.5C260 250.195 263.863 256.988 269.755 261.045L263.251 318.776C263.117 319.962 264.045 321 265.238 321H299.762C300.955 321 301.883 319.962 301.749 318.776L295.245 261.045Z" fill="url(#paint10_linear_383_361)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2216 195.28C24.5045 171.843 48.6065 157.935 72.0548 164.215C95.5031 170.495 109.418 194.585 103.135 218.022C96.8525 241.459 72.7505 255.367 49.3022 249.087C25.8539 242.808 11.9386 218.717 18.2216 195.28ZM75.3443 151.944C45.1158 143.848 14.0446 161.778 5.94486 191.992C-2.15486 222.206 15.7841 253.262 46.0127 261.358C73.9906 268.851 102.69 254.049 113.231 227.848L200.083 251.109L190.626 286.388C190.323 287.517 190.994 288.678 192.124 288.981L200.308 291.173C201.438 291.475 202.6 290.805 202.903 289.675L212.36 254.397L229.496 258.986L223.603 280.972C223.3 282.101 223.97 283.262 225.101 283.565L233.285 285.757C234.415 286.059 235.577 285.389 235.879 284.26L241.773 262.274L258.909 266.864L249.452 302.142C249.149 303.272 249.82 304.433 250.95 304.735L259.134 306.927C260.264 307.23 261.426 306.56 261.729 305.43L273.927 259.926C274.23 258.797 273.56 257.636 272.429 257.333L116.634 215.608C121.208 187.282 103.673 159.531 75.3443 151.944Z" fill="#134cca"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2216 195.28C24.5045 171.843 48.6065 157.935 72.0548 164.215C95.5031 170.495 109.418 194.585 103.135 218.022C96.8525 241.459 72.7505 255.367 49.3022 249.087C25.8539 242.808 11.9386 218.717 18.2216 195.28ZM75.3443 151.944C45.1158 143.848 14.0446 161.778 5.94486 191.992C-2.15486 222.206 15.7841 253.262 46.0127 261.358C73.9906 268.851 102.69 254.049 113.231 227.848L200.083 251.109L190.626 286.388C190.323 287.517 190.994 288.678 192.124 288.981L200.308 291.173C201.438 291.475 202.6 290.805 202.903 289.675L212.36 254.397L229.496 258.986L223.603 280.972C223.3 282.101 223.97 283.262 225.101 283.565L233.285 285.757C234.415 286.059 235.577 285.389 235.879 284.26L241.773 262.274L258.909 266.864L249.452 302.142C249.149 303.272 249.82 304.433 250.95 304.735L259.134 306.927C260.264 307.23 261.426 306.56 261.729 305.43L273.927 259.926C274.23 258.797 273.56 257.636 272.429 257.333L116.634 215.608C121.208 187.282 103.673 159.531 75.3443 151.944Z" fill="url(#paint11_linear_383_361)"/>
<path d="M136 399C136 397.895 136.895 397 138 397H174.444C175.549 397 176.444 397.895 176.444 399V435.444C176.444 436.549 175.549 437.444 174.444 437.444H138C136.895 437.444 136 436.549 136 435.444V399Z" fill="#c8d8fa"/>
<path d="M136 399C136 397.895 136.895 397 138 397H174.444C175.549 397 176.444 397.895 176.444 399V435.444C176.444 436.549 175.549 437.444 174.444 437.444H138C136.895 437.444 136 436.549 136 435.444V399Z" fill="url(#paint12_linear_383_361)"/>
<path d="M154.486 428.111L154.911 420.455L148.626 424.671L146.371 420.663L153.081 417.222L146.371 413.782L148.626 409.774L154.911 413.99L154.486 406.333H158.978L158.572 413.99L164.857 409.774L167.112 413.782L160.383 417.222L167.112 420.663L164.857 424.671L158.572 420.455L158.978 428.111H154.486Z" fill="#d6e2fb"/>
<path d="M189 399C189 397.895 189.895 397 191 397H227.444C228.549 397 229.444 397.895 229.444 399V435.444C229.444 436.549 228.549 437.444 227.444 437.444H191C189.895 437.444 189 436.549 189 435.444V399Z" fill="#c8d8fa"/>
<path d="M189 399C189 397.895 189.895 397 191 397H227.444C228.549 397 229.444 397.895 229.444 399V435.444C229.444 436.549 228.549 437.444 227.444 437.444H191C189.895 437.444 189 436.549 189 435.444V399Z" fill="url(#paint13_linear_383_361)"/>
<path d="M207.373 428.111L207.798 420.455L201.513 424.671L199.258 420.663L205.968 417.222L199.258 413.782L201.513 409.774L207.798 413.99L207.373 406.333H211.865L211.458 413.99L217.743 409.774L219.999 413.782L213.27 417.222L219.999 420.663L217.743 424.671L211.458 420.455L211.865 428.111H207.373Z" fill="#d6e2fb"/>
<path d="M242 399C242 397.895 242.895 397 244 397H280.444C281.549 397 282.444 397.895 282.444 399V435.444C282.444 436.549 281.549 437.444 280.444 437.444H244C242.895 437.444 242 436.549 242 435.444V399Z" fill="#c8d8fa"/>
<path d="M242 399C242 397.895 242.895 397 244 397H280.444C281.549 397 282.444 397.895 282.444 399V435.444C282.444 436.549 281.549 437.444 280.444 437.444H244C242.895 437.444 242 436.549 242 435.444V399Z" fill="url(#paint14_linear_383_361)"/>
<path d="M260.264 428.111L260.689 420.455L254.404 424.671L252.148 420.663L258.859 417.222L252.148 413.782L254.404 409.774L260.689 413.99L260.264 406.333H264.756L264.349 413.99L270.634 409.774L272.889 413.782L266.16 417.222L272.889 420.663L270.634 424.671L264.349 420.455L264.756 428.111H260.264Z" fill="#d6e2fb"/>
<path d="M295.002 399C295.002 397.895 295.897 397 297.002 397H333.446C334.551 397 335.446 397.895 335.446 399V435.444C335.446 436.549 334.551 437.444 333.446 437.444H297.002C295.897 437.444 295.002 436.549 295.002 435.444V399Z" fill="#c8d8fa"/>
<path d="M295.002 399C295.002 397.895 295.897 397 297.002 397H333.446C334.551 397 335.446 397.895 335.446 399V435.444C335.446 436.549 334.551 437.444 333.446 437.444H297.002C295.897 437.444 295.002 436.549 295.002 435.444V399Z" fill="url(#paint15_linear_383_361)"/>
<path d="M313.152 428.111L313.577 420.455L307.292 424.671L305.037 420.663L311.747 417.222L305.037 413.782L307.292 409.774L313.577 413.99L313.152 406.333H317.644L317.238 413.99L323.523 409.774L325.778 413.782L319.049 417.222L325.778 420.663L323.523 424.671L317.238 420.455L317.644 428.111H313.152Z" fill="#d6e2fb"/>
<path d="M348.002 399C348.002 397.895 348.897 397 350.002 397H386.446C387.551 397 388.446 397.895 388.446 399V435.444C388.446 436.549 387.551 437.444 386.446 437.444H350.002C348.897 437.444 348.002 436.549 348.002 435.444V399Z" fill="#c8d8fa"/>
<path d="M348.002 399C348.002 397.895 348.897 397 350.002 397H386.446C387.551 397 388.446 397.895 388.446 399V435.444C388.446 436.549 387.551 437.444 386.446 437.444H350.002C348.897 437.444 348.002 436.549 348.002 435.444V399Z" fill="url(#paint16_linear_383_361)"/>
<path d="M366.043 428.111L366.468 420.455L360.183 424.671L357.928 420.663L364.638 417.222L357.928 413.782L360.183 409.774L366.468 413.99L366.043 406.333H370.535L370.128 413.99L376.413 409.774L378.668 413.782L371.94 417.222L378.668 420.663L376.413 424.671L370.128 420.455L370.535 428.111H366.043Z" fill="#d6e2fb"/>
<defs>
<linearGradient id="paint0_linear_383_361" x1="262.778" y1="246" x2="262.778" y2="747.5" gradientUnits="userSpaceOnUse">
<stop stop-color="white" stop-opacity="0"/>
<stop offset="1" stop-color="white"/>
</linearGradient>
<linearGradient id="paint1_linear_383_361" x1="270.5" y1="495" x2="270.5" y2="163" gradientUnits="userSpaceOnUse">
<stop offset="0.26" stop-color="#c8d8fa" stop-opacity="0"/>
<stop offset="1" stop-color="#c8d8fa"/>
</linearGradient>
<linearGradient id="paint2_linear_383_361" x1="270" y1="77" x2="270" y2="160.5" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint3_linear_383_361" x1="165.5" y1="352.499" x2="165.5" y2="144.523" gradientUnits="userSpaceOnUse">
<stop stop-color="white" stop-opacity="0"/>
<stop offset="1" stop-color="white"/>
</linearGradient>
<linearGradient id="paint4_linear_383_361" x1="165.5" y1="164.446" x2="165.5" y2="432.41" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa" stop-opacity="0"/>
<stop offset="1" stop-color="#c8d8fa"/>
</linearGradient>
<linearGradient id="paint5_linear_383_361" x1="165.5" y1="309.773" x2="165.5" y2="441.888" gradientUnits="userSpaceOnUse">
<stop stop-color="white" stop-opacity="0"/>
<stop offset="1" stop-color="white"/>
</linearGradient>
<linearGradient id="paint6_linear_383_361" x1="165.5" y1="225.656" x2="165.5" y2="295.771" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint7_linear_383_361" x1="281" y1="352.499" x2="281" y2="144.523" gradientUnits="userSpaceOnUse">
<stop stop-color="white" stop-opacity="0"/>
<stop offset="1" stop-color="white"/>
</linearGradient>
<linearGradient id="paint8_linear_383_361" x1="281" y1="164.446" x2="281" y2="432.41" gradientUnits="userSpaceOnUse">
<stop stop-color="#c8d8fa" stop-opacity="0"/>
<stop offset="1" stop-color="#c8d8fa"/>
</linearGradient>
<linearGradient id="paint9_linear_383_361" x1="281" y1="286.5" x2="281" y2="404" gradientUnits="userSpaceOnUse">
<stop stop-color="white" stop-opacity="0"/>
<stop offset="1" stop-color="white"/>
</linearGradient>
<linearGradient id="paint10_linear_383_361" x1="277.5" y1="304" x2="277.5" y2="190" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928" stop-opacity="0"/>
<stop offset="1" stop-color="#111928"/>
</linearGradient>
<linearGradient id="paint11_linear_383_361" x1="187" y1="240.5" x2="56.5" y2="46" gradientUnits="userSpaceOnUse">
<stop stop-color="#2563eb" stop-opacity="0"/>
<stop offset="1" stop-color="#2563eb"/>
</linearGradient>
<linearGradient id="paint12_linear_383_361" x1="156.222" y1="408.886" x2="156.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint13_linear_383_361" x1="209.222" y1="408.886" x2="209.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint14_linear_383_361" x1="262.222" y1="408.886" x2="262.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint15_linear_383_361" x1="315.224" y1="408.886" x2="315.224" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint16_linear_383_361" x1="368.224" y1="408.886" x2="368.224" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
</defs>
</svg>
        <div class="hidden dark:block">
          <svg class="w-auto text-gray-800 dark:text-white" aria-hidden="true" width="524" height="540" viewBox="0 0 524 540" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M524 278C524 422.699 406.699 540 262 540C117.301 540 0 422.699 0 278C0 133.301 117.301 16 262 16C406.699 16 524 133.301 524 278Z" fill="url(#paint0_linear_383_573)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M519.795 325C497.649 447.269 390.653 540 261.999 540C133.345 540 26.349 447.269 4.20312 325H519.795Z" fill="url(#paint1_linear_383_573)"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M351 81V240C351 252.15 341.15 262 329 262H211C198.85 262 189 252.15 189 240V81C189 36.2649 225.265 0 270 0C314.735 0 351 36.2649 351 81ZM270 18C235.206 18 207 46.2061 207 81V240C207 242.209 208.791 244 211 244H329C331.209 244 333 242.209 333 240V81C333 46.2061 304.794 18 270 18Z" fill="#374151"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M351 81V240C351 252.15 341.15 262 329 262H211C198.85 262 189 252.15 189 240V81C189 36.2649 225.265 0 270 0C314.735 0 351 36.2649 351 81ZM270 18C235.206 18 207 46.2061 207 81V240C207 242.209 208.791 244 211 244H329C331.209 244 333 242.209 333 240V81C333 46.2061 304.794 18 270 18Z" fill="url(#paint2_linear_383_573)" fill-opacity="0.7"/>
<path d="M195 165C195 162.791 193.209 161 191 161H140C137.791 161 136 162.791 136 165V377C136 379.209 137.791 381 140 381H191C193.209 381 195 379.209 195 377V165Z" fill="#111928"/>
<path d="M174 164C174 162.343 175.343 161 177 161H385C386.657 161 388 162.343 388 164V378C388 379.657 386.657 381 385 381H177C175.343 381 174 379.657 174 378V164Z" fill="#374151"/>
<path d="M174 164C174 162.343 175.343 161 177 161H385C386.657 161 388 162.343 388 164V378C388 379.657 386.657 381 385 381H177C175.343 381 174 379.657 174 378V164Z" fill="url(#paint3_linear_383_573)" fill-opacity="0.7"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M295.245 261.045C301.137 256.988 305 250.195 305 242.5C305 230.074 294.926 220 282.5 220C270.074 220 260 230.074 260 242.5C260 250.195 263.863 256.988 269.755 261.045L263.251 318.776C263.117 319.962 264.045 321 265.238 321H299.762C300.955 321 301.883 319.962 301.749 318.776L295.245 261.045Z" fill="#111928"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2216 195.28C24.5045 171.843 48.6065 157.935 72.0548 164.215C95.5031 170.495 109.418 194.585 103.135 218.022C96.8525 241.459 72.7505 255.367 49.3022 249.087C25.8539 242.808 11.9386 218.717 18.2216 195.28ZM75.3443 151.944C45.1158 143.848 14.0446 161.778 5.94486 191.992C-2.15486 222.206 15.7841 253.262 46.0127 261.358C73.9906 268.851 102.69 254.049 113.231 227.848L200.083 251.109L190.626 286.388C190.323 287.517 190.994 288.678 192.124 288.981L200.308 291.173C201.438 291.475 202.6 290.805 202.903 289.675L212.36 254.397L229.496 258.986L223.603 280.972C223.3 282.101 223.97 283.262 225.101 283.565L233.285 285.757C234.415 286.059 235.577 285.389 235.879 284.26L241.773 262.274L258.909 266.864L249.452 302.142C249.149 303.272 249.82 304.433 250.95 304.735L259.134 306.927C260.264 307.23 261.426 306.56 261.729 305.43L273.927 259.926C274.23 258.797 273.559 257.636 272.429 257.333L116.634 215.608C121.208 187.282 103.673 159.531 75.3443 151.944Z" fill="#6B7280"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2216 195.28C24.5045 171.843 48.6065 157.935 72.0548 164.215C95.5031 170.495 109.418 194.585 103.135 218.022C96.8525 241.459 72.7505 255.367 49.3022 249.087C25.8539 242.808 11.9386 218.717 18.2216 195.28ZM75.3443 151.944C45.1158 143.848 14.0446 161.778 5.94486 191.992C-2.15486 222.206 15.7841 253.262 46.0127 261.358C73.9906 268.851 102.69 254.049 113.231 227.848L200.083 251.109L190.626 286.388C190.323 287.517 190.994 288.678 192.124 288.981L200.308 291.173C201.438 291.475 202.6 290.805 202.903 289.675L212.36 254.397L229.496 258.986L223.603 280.972C223.3 282.101 223.97 283.262 225.101 283.565L233.285 285.757C234.415 286.059 235.577 285.389 235.879 284.26L241.773 262.274L258.909 266.864L249.452 302.142C249.149 303.272 249.82 304.433 250.95 304.735L259.134 306.927C260.264 307.23 261.426 306.56 261.729 305.43L273.927 259.926C274.23 258.797 273.559 257.636 272.429 257.333L116.634 215.608C121.208 187.282 103.673 159.531 75.3443 151.944Z" fill="url(#paint4_linear_383_573)"/>
<path d="M136 399C136 397.895 136.895 397 138 397H174.444C175.549 397 176.444 397.895 176.444 399V435.444C176.444 436.549 175.549 437.444 174.444 437.444H138C136.895 437.444 136 436.549 136 435.444V399Z" fill="#c8d8fa"/>
<path d="M136 399C136 397.895 136.895 397 138 397H174.444C175.549 397 176.444 397.895 176.444 399V435.444C176.444 436.549 175.549 437.444 174.444 437.444H138C136.895 437.444 136 436.549 136 435.444V399Z" fill="url(#paint5_linear_383_573)"/>
<path d="M154.486 428.111L154.911 420.455L148.626 424.671L146.371 420.663L153.081 417.222L146.371 413.782L148.626 409.774L154.911 413.99L154.486 406.333H158.978L158.572 413.99L164.857 409.774L167.112 413.782L160.383 417.222L167.112 420.663L164.857 424.671L158.572 420.455L158.978 428.111H154.486Z" fill="#F9FAFB"/>
<path d="M189 399C189 397.895 189.895 397 191 397H227.444C228.549 397 229.444 397.895 229.444 399V435.444C229.444 436.549 228.549 437.444 227.444 437.444H191C189.895 437.444 189 436.549 189 435.444V399Z" fill="#c8d8fa"/>
<path d="M189 399C189 397.895 189.895 397 191 397H227.444C228.549 397 229.444 397.895 229.444 399V435.444C229.444 436.549 228.549 437.444 227.444 437.444H191C189.895 437.444 189 436.549 189 435.444V399Z" fill="url(#paint6_linear_383_573)"/>
<path d="M207.373 428.111L207.798 420.455L201.513 424.671L199.258 420.663L205.968 417.222L199.258 413.782L201.513 409.774L207.798 413.99L207.373 406.333H211.865L211.458 413.99L217.743 409.774L219.999 413.782L213.27 417.222L219.999 420.663L217.743 424.671L211.458 420.455L211.865 428.111H207.373Z" fill="#F9FAFB"/>
<path d="M242 399C242 397.895 242.895 397 244 397H280.444C281.549 397 282.444 397.895 282.444 399V435.444C282.444 436.549 281.549 437.444 280.444 437.444H244C242.895 437.444 242 436.549 242 435.444V399Z" fill="#c8d8fa"/>
<path d="M242 399C242 397.895 242.895 397 244 397H280.444C281.549 397 282.444 397.895 282.444 399V435.444C282.444 436.549 281.549 437.444 280.444 437.444H244C242.895 437.444 242 436.549 242 435.444V399Z" fill="url(#paint7_linear_383_573)"/>
<path d="M260.264 428.111L260.689 420.455L254.404 424.671L252.148 420.663L258.859 417.222L252.148 413.782L254.404 409.774L260.689 413.99L260.264 406.333H264.756L264.349 413.99L270.634 409.774L272.889 413.782L266.16 417.222L272.889 420.663L270.634 424.671L264.349 420.455L264.756 428.111H260.264Z" fill="#F9FAFB"/>
<path d="M295.002 399C295.002 397.895 295.897 397 297.002 397H333.446C334.551 397 335.446 397.895 335.446 399V435.444C335.446 436.549 334.551 437.444 333.446 437.444H297.002C295.897 437.444 295.002 436.549 295.002 435.444V399Z" fill="#c8d8fa"/>
<path d="M295.002 399C295.002 397.895 295.897 397 297.002 397H333.446C334.551 397 335.446 397.895 335.446 399V435.444C335.446 436.549 334.551 437.444 333.446 437.444H297.002C295.897 437.444 295.002 436.549 295.002 435.444V399Z" fill="url(#paint8_linear_383_573)"/>
<path d="M313.152 428.111L313.577 420.455L307.292 424.671L305.037 420.663L311.747 417.222L305.037 413.782L307.292 409.774L313.577 413.99L313.152 406.333H317.644L317.238 413.99L323.523 409.774L325.778 413.782L319.049 417.222L325.778 420.663L323.523 424.671L317.238 420.455L317.644 428.111H313.152Z" fill="#F9FAFB"/>
<path d="M348.002 399C348.002 397.895 348.897 397 350.002 397H386.446C387.551 397 388.446 397.895 388.446 399V435.444C388.446 436.549 387.551 437.444 386.446 437.444H350.002C348.897 437.444 348.002 436.549 348.002 435.444V399Z" fill="#c8d8fa"/>
<path d="M348.002 399C348.002 397.895 348.897 397 350.002 397H386.446C387.551 397 388.446 397.895 388.446 399V435.444C388.446 436.549 387.551 437.444 386.446 437.444H350.002C348.897 437.444 348.002 436.549 348.002 435.444V399Z" fill="url(#paint9_linear_383_573)"/>
<path d="M366.043 428.111L366.468 420.455L360.183 424.671L357.928 420.663L364.638 417.222L357.928 413.782L360.183 409.774L366.468 413.99L366.043 406.333H370.535L370.128 413.99L376.413 409.774L378.668 413.782L371.94 417.222L378.668 420.663L376.413 424.671L370.128 420.455L370.535 428.111H366.043Z" fill="#F9FAFB"/>
<defs>
<linearGradient id="paint0_linear_383_573" x1="262" y1="16" x2="262" y2="540" gradientUnits="userSpaceOnUse">
<stop stop-color="#1F2A37"/>
<stop offset="1" stop-color="#1F2A37" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint1_linear_383_573" x1="261.999" y1="325" x2="261.999" y2="499.549" gradientUnits="userSpaceOnUse">
<stop stop-color="#2F3948"/>
<stop offset="1" stop-color="#2F3948" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint2_linear_383_573" x1="270" y1="165.5" x2="270.072" y2="69.9682" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint3_linear_383_573" x1="274.434" y1="381" x2="235.605" y2="243.462" gradientUnits="userSpaceOnUse">
<stop stop-color="#111928"/>
<stop offset="1" stop-color="#111928" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint4_linear_383_573" x1="214" y1="247.5" x2="83.5" y2="41" gradientUnits="userSpaceOnUse">
<stop stop-color="#F9FAFB" stop-opacity="0"/>
<stop offset="1" stop-color="#F9FAFB"/>
</linearGradient>
<linearGradient id="paint5_linear_383_573" x1="156.222" y1="408.886" x2="156.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint6_linear_383_573" x1="209.222" y1="408.886" x2="209.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint7_linear_383_573" x1="262.222" y1="408.886" x2="262.222" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint8_linear_383_573" x1="315.224" y1="408.886" x2="315.224" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
<linearGradient id="paint9_linear_383_573" x1="368.224" y1="408.886" x2="368.224" y2="421.776" gradientUnits="userSpaceOnUse">
<stop stop-color="#9ab7f6" stop-opacity="0"/>
<stop offset="1" stop-color="#9ab7f6"/>
</linearGradient>
</defs>
</svg>
        </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
