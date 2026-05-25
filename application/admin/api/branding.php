<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

// Prüfen ob eingeloggt und Admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['rolle'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
    exit;
}

// Default-Werte (aktuelle Werte aus head.php und nav.php)
$defaults = [
    'logo' => 'assets/images/Serohub_Icon.png',
    'name_part1' => 'Serohub',
    'name_part2' => '',
    'error_page_403' => '403.html',
    'error_page_404' => '404.html',
    'error_page_500' => '500.html',
    'colors' => [
        50 => '#020617',    // Background (BG)
        100 => '#0B1226',   // Card Background
        120 => '#111827',   // Card Border
        140 => '#0B1938',   // Card Hover
        200 => '#E5E7EB',   // Primary Text
        210 => '#94A3B8',   // Secondary Text
        220 => '#64748B',   // Muted / Disabled Text
        230 => '#1E293B',   // Divider Lines
        240 => '#64748B',   // Placeholder Text
        250 => '#155dfc',   // Primary Accent
        260 => '#1A6EFF',   // Accent Hover
        270 => '#104ACC',   // Accent Active
        280 => '#60A5FA',   // Link Hover
        300 => '#020617',   // Input Background
        320 => '#111827',   // Input Border
        340 => '#1E293B',   // Input Hover Border
        360 => '#3B82F6',   // Input Focus Border
        380 => 'rgba(59,130,246,0.3)',  // Focus Glow
        400 => '#475569',   // Disabled Input Text
        420 => '#155dfc',   // Primary Button BG Default
        440 => '#1A6EFF',   // Primary Button BG Hover
        460 => '#104ACC',   // Primary Button BG Active
        480 => '#020617',   // Primary Button Text
        500 => '#1E293B',   // Primary Button Disabled BG
        520 => '#64748B',   // Primary Button Disabled Text
        540 => '#0B1226',   // Secondary Button BG Default
        560 => '#111827',   // Secondary Button Border
        580 => '#E5E7EB',   // Secondary Button Text
        600 => '#0B1938',   // Secondary Button Hover BG
        620 => '#10204A',   // Secondary Button Active BG
        640 => '#3B82F6',   // Secondary Button Active Border
        660 => '#3B82F6',   // Tertiary Text Default
        680 => '#60A5FA',   // Tertiary Hover
        700 => '#020617',   // Filter Default BG
        720 => '#111827',   // Filter Default Border
        740 => '#94A3B8',   // Filter Default Text
        760 => '#0B1938',   // Filter Hover BG
        780 => '#E5E7EB',   // Filter Hover Text
        800 => '#10204A',   // Filter Active BG
        820 => '#3B82F6',   // Filter Active Border
        840 => '#E5E7EB',   // Filter Active Text
        860 => '#0B1226',   // Table Container BG
        880 => '#111827',   // Table Border
        900 => '#0A1020',   // Table Header BG
        920 => '#E5E7EB',   // Table Header Text
        940 => '#0B1938',   // Table Row Hover
        960 => '#0F1A33',   // Table Header Hover
        980 => 'rgba(255,255,255,0.02)', // Zebra Even Row
        1000 => 'rgba(255,255,255,0.03)', // Column Lines
        1020 => '#10204A',  // Selected Row BG
        1040 => '#22C55E',  // Success
        1060 => '#F59E0B',  // Warning
        1080 => '#EF4444',  // Error
        1100 => '#38BDF8'   // Info
    ],
    'colors_light' => [
        50 => '#f7fafc', 100 => '#FFFFFF', 120 => '#E5E7EB', 140 => '#F3F4F6',
        200 => '#111827', 210 => '#6B7280', 220 => '#9CA3AF', 230 => '#E5E7EB', 240 => '#9CA3AF',
        250 => '#155dfc', 260 => '#1A6EFF', 270 => '#104ACC', 280 => '#2563EB',
        300 => '#FFFFFF', 320 => '#D1D5DB', 340 => '#9CA3AF', 360 => '#3B82F6', 380 => 'rgba(59,130,246,0.3)', 400 => '#9CA3AF',
        420 => '#155dfc', 440 => '#1A6EFF', 460 => '#104ACC', 480 => '#FFFFFF', 500 => '#E5E7EB', 520 => '#9CA3AF',
        540 => '#FFFFFF', 560 => '#D1D5DB', 580 => '#374151', 600 => '#F3F4F6', 620 => '#E5E7EB', 640 => '#3B82F6',
        660 => '#2563EB', 680 => '#1D4ED8',
        700 => '#FFFFFF', 720 => '#D1D5DB', 740 => '#6B7280', 760 => '#F3F4F6', 780 => '#111827', 800 => '#E5E7EB', 820 => '#3B82F6', 840 => '#111827',
        860 => '#FFFFFF', 880 => '#E5E7EB', 900 => '#f7fafc', 920 => '#111827', 940 => '#F3F4F6', 960 => '#F3F4F6', 980 => 'rgba(0,0,0,0.02)', 1000 => 'rgba(0,0,0,0.05)', 1020 => '#DBEAFE',
        1040 => '#22C55E', 1060 => '#F59E0B', 1080 => '#EF4444', 1100 => '#38BDF8'
    ]
];

function normalizeErrorPageSetting($value, string $fallback): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return $fallback;
    }
    if (preg_match('#^https?://#i', $raw)) {
        return $fallback;
    }
    if ($raw[0] === '/') {
        $path = preg_replace('/\?.*$/', '', $raw);
        $base = basename($path);
        if ($base !== '' && preg_match('/\.(html|php)$/i', $base)) {
            return $path;
        }
        return $fallback;
    }
    $base = basename($raw);
    if ($base !== '' && preg_match('/\.(html|php)$/i', $base)) {
        return $base;
    }
    return $fallback;
}

function toErrorDocumentPath(string $value): string {
    $raw = trim($value);
    if ($raw === '') {
        return '/errors/404.php';
    }
    if ($raw[0] === '/') {
        return $raw;
    }
    return '/errors/' . basename($raw);
}

function writeRootHtaccessErrorDocuments(string $doc403, string $doc404, string $doc500): bool {
    $rootDir = dirname(__DIR__, 2);
    $htaccessPath = $rootDir . '/.htaccess';
    $begin = '# BEGIN WEBAPP ERRORDOCUMENT';
    $end = '# END WEBAPP ERRORDOCUMENT';
    $block = $begin . PHP_EOL
        . 'ErrorDocument 403 ' . $doc403 . PHP_EOL
        . 'ErrorDocument 404 ' . $doc404 . PHP_EOL
        . 'ErrorDocument 500 ' . $doc500 . PHP_EOL
        . $end;

    $current = '';
    if (is_file($htaccessPath)) {
        $read = @file_get_contents($htaccessPath);
        if ($read === false) {
            return false;
        }
        $current = $read;
    }

    $pattern = '/^\# BEGIN WEBAPP ERRORDOCUMENT.*?\# END WEBAPP ERRORDOCUMENT\s*$/ms';
    if (preg_match($pattern, $current)) {
        $updated = preg_replace($pattern, $block, $current, 1);
    } else {
        $trimmed = rtrim($current);
        $updated = $trimmed === '' ? $block . PHP_EOL : $trimmed . PHP_EOL . PHP_EOL . $block . PHP_EOL;
    }

    return @file_put_contents($htaccessPath, $updated) !== false;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $errorTemplateOptions = [];
    $errorsDir = dirname(__DIR__, 2) . '/errors';
    if (is_dir($errorsDir)) {
        $entries = scandir($errorsDir);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if (preg_match('/\.(html|php)$/i', (string) $entry)) {
                    $errorTemplateOptions[] = $entry;
                }
            }
        }
    }
    if (empty($errorTemplateOptions)) {
        $errorTemplateOptions = ['403.html', '404.html', '500.html', '403.php', '404.php', '500.php'];
    }
    sort($errorTemplateOptions, SORT_NATURAL | SORT_FLAG_CASE);

    // GET: Branding-Einstellungen laden
    if ($method === 'GET') {
        $logo = $defaults['logo'];
        $namePart1 = $defaults['name_part1'];
        $namePart2 = $defaults['name_part2'];
        $errorPage403 = $defaults['error_page_403'];
        $errorPage404 = $defaults['error_page_404'];
        $errorPage500 = $defaults['error_page_500'];
        // Farben werden immer aus den Standardwerten verwendet
        $colors = $defaults['colors'];
        $colorsLight = $defaults['colors_light'];
        
        try {
            // Farben werden nicht mehr aus der DB geladen - nur Logo und Name
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2', 'error_page_403', 'error_page_404', 'error_page_500')");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                switch ($row['setting_key']) {
                    case 'branding_logo':
                        $logo = $row['setting_value'] ?: $logo;
                        break;
                    case 'branding_name_part1':
                        $namePart1 = $row['setting_value'] !== null ? $row['setting_value'] : $namePart1;
                        break;
                    case 'branding_name_part2':
                        $namePart2 = $row['setting_value'] !== null ? trim((string) $row['setting_value']) : $defaults['name_part2'];
                        break;
                    case 'error_page_403':
                        $errorPage403 = normalizeErrorPageSetting($row['setting_value'] ?? '', $defaults['error_page_403']);
                        break;
                    case 'error_page_404':
                        $errorPage404 = normalizeErrorPageSetting($row['setting_value'] ?? '', $defaults['error_page_404']);
                        break;
                    case 'error_page_500':
                        $errorPage500 = normalizeErrorPageSetting($row['setting_value'] ?? '', $defaults['error_page_500']);
                        break;
                    // Farben werden nicht mehr aus der DB geladen
                    // case 'branding_colors':
                    //     $decoded = json_decode($row['setting_value'], true);
                    //     if (is_array($decoded)) {
                    //         $colors = array_replace($defaults['colors'], $decoded);
                    //     }
                    //     break;
                    // case 'branding_colors_light':
                    //     $decoded = json_decode($row['setting_value'], true);
                    //     if (is_array($decoded)) {
                    //         $colorsLight = array_replace($defaults['colors_light'], $decoded);
                    //     }
                    //     break;
                }
            }
        } catch (PDOException $e) {
            error_log("Branding API GET: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'logo' => $logo,
            'name_part1' => $namePart1,
            'name_part2' => $namePart2,
            'error_page_403' => $errorPage403,
            'error_page_404' => $errorPage404,
            'error_page_500' => $errorPage500,
            'error_template_options' => $errorTemplateOptions,
            'colors' => $colors,
            'colors_light' => $colorsLight,
            'defaults' => $defaults
        ]);
        exit;
    }
    
    // POST: Speichern oder Zurücksetzen
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
        exit;
    }

    // Reset auf Default-Werte
    if (!empty($data['reset'])) {
        try {
            // Farben werden nicht mehr zurückgesetzt, da sie nicht mehr gespeichert werden
            $stmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2', 'error_page_403', 'error_page_404', 'error_page_500')");
            $stmt->execute();
            $htaccessOk = writeRootHtaccessErrorDocuments('/errors/403.php', '/errors/404.php', '/errors/500.php');
            $message = $htaccessOk
                ? 'Einstellungen auf Standard zurückgesetzt'
                : 'Einstellungen zurückgesetzt, aber .htaccess konnte nicht aktualisiert werden';
            echo json_encode(['success' => true, 'message' => $message]);
        } catch (PDOException $e) {
            error_log("Branding Reset: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Fehler beim Zurücksetzen']);
        }
        exit;
    }

    // Speichern
    $logo = isset($data['logo']) ? trim($data['logo']) : $defaults['logo'];
    $namePart1 = isset($data['name_part1']) ? trim($data['name_part1']) : $defaults['name_part1'];
    $namePart2 = isset($data['name_part2']) ? trim($data['name_part2']) : $defaults['name_part2'];
    $errorPage403 = normalizeErrorPageSetting($data['error_page_403'] ?? '', $defaults['error_page_403']);
    $errorPage404 = normalizeErrorPageSetting($data['error_page_404'] ?? '', $defaults['error_page_404']);
    $errorPage500 = normalizeErrorPageSetting($data['error_page_500'] ?? '', $defaults['error_page_500']);
    // Farben werden nicht mehr gespeichert - System verwendet immer Standardfarben
    // $colors = isset($data['colors']) && is_array($data['colors']) ? $data['colors'] : [];
    // $colorsLight = isset($data['colors_light']) && is_array($data['colors_light']) ? $data['colors_light'] : [];

    // $validateColors = function($input, $defaultsArr) {
    //     $valid = [];
    //     foreach ($defaultsArr as $key => $defaultVal) {
    //         $val = isset($input[$key]) ? trim((string)$input[$key]) : '';
    //         $valid[$key] = ($val !== '' && (preg_match('/^#[0-9a-fA-F]{6}$/', $val) || preg_match('/^rgba?\([^)]+\)$/', $val))) ? $val : $defaultVal;
    //     }
    //     return $valid;
    // };
    // $validColors = $validateColors($colors, $defaults['colors']);
    // $validColorsLight = $validateColors($colorsLight, $defaults['colors_light']);

    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (:setting_key, :setting_value)
        ON DUPLICATE KEY UPDATE 
            setting_value = :setting_value_update,
            geaendert_datum = NOW()
    ");

    $saveStmt = function($key, $value) use ($pdo, $stmt) {
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
            ':setting_value_update' => $value
        ]);
    };

    $saveStmt('branding_logo', $logo);
    $saveStmt('branding_name_part1', $namePart1);
    $saveStmt('branding_name_part2', $namePart2);
    $saveStmt('error_page_403', $errorPage403);
    $saveStmt('error_page_404', $errorPage404);
    $saveStmt('error_page_500', $errorPage500);
    // Farben werden nicht mehr gespeichert
    // $saveStmt('branding_colors', json_encode($validColors));
    // $saveStmt('branding_colors_light', json_encode($validColorsLight));

    $doc403 = toErrorDocumentPath($errorPage403);
    $doc404 = toErrorDocumentPath($errorPage404);
    $doc500 = toErrorDocumentPath($errorPage500);
    $htaccessOk = writeRootHtaccessErrorDocuments($doc403, $doc404, $doc500);

    $message = $htaccessOk
        ? 'Einstellungen gespeichert'
        : 'Einstellungen gespeichert, aber .htaccess konnte nicht aktualisiert werden';
    echo json_encode(['success' => true, 'message' => $message]);

} catch (PDOException $e) {
    error_log("Branding API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
