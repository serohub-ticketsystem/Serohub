<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
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

function readSafeFile(string $path): string {
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }
    $content = @file_get_contents($path);
    return $content === false ? '' : $content;
}

function getDefaultUserIniContent(): string {
    return implode(PHP_EOL, [
        '; .user.ini (wird nur angelegt, wenn du speicherst)',
        '; Typische Werte, bei Bedarf anpassen:',
        'memory_limit=256M',
        'upload_max_filesize=64M',
        'post_max_size=64M',
        'max_execution_time=120',
        ''
    ]);
}

function getDefaultHtaccessContent(): string {
    return implode(PHP_EOL, [
        '# .htaccess (wird nur angelegt, wenn du speicherst)',
        '# Basisregeln fuer PHP-Apps',
        '',
        '<IfModule mod_rewrite.c>',
        '    RewriteEngine On',
        '</IfModule>',
        '',
        '# Sicherheitsheader (optional)',
        '<IfModule mod_headers.c>',
        '    Header always set X-Frame-Options "SAMEORIGIN"',
        '    Header always set X-Content-Type-Options "nosniff"',
        '</IfModule>',
        ''
    ]);
}

function writeManagedErrorDocumentBlock(string $htaccessPath, string $doc403, string $doc404, string $doc500): bool {
    $begin = '# BEGIN WEBAPP ERRORDOCUMENT';
    $end = '# END WEBAPP ERRORDOCUMENT';
    $block = $begin . PHP_EOL
        . 'ErrorDocument 403 ' . $doc403 . PHP_EOL
        . 'ErrorDocument 404 ' . $doc404 . PHP_EOL
        . 'ErrorDocument 500 ' . $doc500 . PHP_EOL
        . $end;

    $current = readSafeFile($htaccessPath);
    if (!is_file($htaccessPath)) {
        $current = '';
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

function detectAllowOverrideHint(bool $hasManagedBlock): array {
    $software = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');
    $isApache = stripos($software, 'apache') !== false;
    if (!$isApache) {
        return [
            'status' => 'n/a',
            'message' => 'Kein Apache erkannt (laut SERVER_SOFTWARE). AllowOverride ist hier nicht relevant.'
        ];
    }
    if ($hasManagedBlock) {
        return [
            'status' => 'likely_enabled',
            'message' => 'Apache erkannt. Da der ErrorDocument-Block in .htaccess vorhanden ist, ist AllowOverride wahrscheinlich aktiv.'
        ];
    }
    return [
        'status' => 'unknown',
        'message' => 'Apache erkannt. Ob AllowOverride aktiv ist, kann aus PHP nicht sicher festgestellt werden.'
    ];
}

function toBytes(string $value): int {
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return -1;
    }
    $unit = strtolower(substr($value, -1));
    $num = (float) $value;
    switch ($unit) {
        case 'g':
            $num *= 1024;
            // no break
        case 'm':
            $num *= 1024;
            // no break
        case 'k':
            $num *= 1024;
            break;
    }
    return (int) round($num);
}

function parseProcMeminfo(): array {
    $raw = readSafeFile('/proc/meminfo');
    $data = [];
    if ($raw === '') {
        return $data;
    }
    foreach (explode("\n", $raw) as $line) {
        if (!preg_match('/^([A-Za-z_]+):\s+(\d+)\s+kB$/', trim($line), $m)) {
            continue;
        }
        $data[$m[1]] = (int) $m[2] * 1024;
    }
    return $data;
}

function getSystemUptimeSeconds(): ?int {
    $raw = readSafeFile('/proc/uptime');
    if ($raw === '') {
        return null;
    }
    $parts = preg_split('/\s+/', trim($raw));
    if (!isset($parts[0]) || !is_numeric($parts[0])) {
        return null;
    }
    return (int) floor((float) $parts[0]);
}

function countCpuCores(): int {
    $cpuInfo = readSafeFile('/proc/cpuinfo');
    if ($cpuInfo !== '') {
        preg_match_all('/^processor\s*:/m', $cpuInfo, $matches);
        $count = count($matches[0]);
        if ($count > 0) {
            return $count;
        }
    }
    return 1;
}

function getApacheModuleInfo(): array {
    if (!function_exists('apache_get_modules')) {
        return [
            'available' => false,
            'enabled' => [],
            'missing_important' => [],
            'message' => 'apache_get_modules() ist in dieser Laufzeitumgebung nicht verfügbar.'
        ];
    }
    $enabled = apache_get_modules();
    sort($enabled, SORT_NATURAL | SORT_FLAG_CASE);
    $important = ['mod_rewrite', 'mod_headers', 'mod_expires'];
    $missing = [];
    foreach ($important as $mod) {
        if (!in_array($mod, $enabled, true)) {
            $missing[] = $mod;
        }
    }
    return [
        'available' => true,
        'enabled' => $enabled,
        'missing_important' => $missing,
        'message' => empty($missing)
            ? 'Wichtige Apache-Module sind vorhanden.'
            : 'Einige wichtige Apache-Module fehlen.'
    ];
}

function getPhpExtensionInfo(): array {
    $loaded = get_loaded_extensions();
    sort($loaded, SORT_NATURAL | SORT_FLAG_CASE);

    $critical = ['PDO', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'session', 'fileinfo'];
    $recommended = ['curl', 'gd', 'intl', 'zip', 'opcache', 'xml', 'SimpleXML'];

    $missingCritical = [];
    foreach ($critical as $ext) {
        if (!extension_loaded($ext)) {
            $missingCritical[] = $ext;
        }
    }
    $missingRecommended = [];
    foreach ($recommended as $ext) {
        if (!extension_loaded($ext)) {
            $missingRecommended[] = $ext;
        }
    }
    return [
        'loaded' => $loaded,
        'missing_critical' => $missingCritical,
        'missing_recommended' => $missingRecommended
    ];
}

function getDbHealth(PDO $pdo): array {
    try {
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        return [
            'ok' => true,
            'version' => $version,
            'database' => $dbName
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage()
        ];
    }
}

function getPathChecks(string $rootDir): array {
    $paths = [
        'webapp_root' => $rootDir,
        'uploads_dir' => $rootDir . '/uploads',
        'logs_dir' => $rootDir . '/logs',
        'errors_dir' => $rootDir . '/errors',
        'config_file' => $rootDir . '/assets/config.php',
        'htaccess_file' => $rootDir . '/.htaccess',
        'user_ini_file' => $rootDir . '/.user.ini'
    ];
    $result = [];
    foreach ($paths as $key => $path) {
        $result[$key] = [
            'path' => $path,
            'exists' => file_exists($path),
            'readable' => file_exists($path) ? is_readable($path) : false,
            'writable' => file_exists($path) ? is_writable($path) : is_writable(dirname($path)),
            'is_dir' => is_dir($path)
        ];
    }
    return $result;
}

$rootDir = dirname(__DIR__, 2);
$userIniPath = $rootDir . '/.user.ini';
$htaccessPath = $rootDir . '/.htaccess';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $userIniExists = is_file($userIniPath);
    $htaccessExists = is_file($htaccessPath);
    $userIniContent = readSafeFile($userIniPath);
    $htaccessContent = readSafeFile($htaccessPath);
    $userIniSource = '.user.ini';
    $htaccessSource = '.htaccess (Webapp)';
    $userIniDisplayPath = $userIniPath;
    $htaccessDisplayPath = $htaccessPath;

    $loadedPhpIniPath = (string) (php_ini_loaded_file() ?: '');
    $loadedPhpIniContent = $loadedPhpIniPath !== '' ? readSafeFile($loadedPhpIniPath) : '';
    if (!$userIniExists && $loadedPhpIniContent !== '') {
        $userIniContent = $loadedPhpIniContent;
        $userIniSource = 'Server php.ini';
        $userIniDisplayPath = $loadedPhpIniPath;
    } elseif (!$userIniExists && $userIniContent === '') {
        $userIniContent = getDefaultUserIniContent();
        $userIniSource = 'Vorlage (Datei fehlt)';
    }

    $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    $docRootHtaccessPath = rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess';
    $docRootHtaccessReadable = $documentRoot !== '' && is_file($docRootHtaccessPath) && is_readable($docRootHtaccessPath);
    if (!$htaccessExists && $docRootHtaccessReadable) {
        $htaccessContent = readSafeFile($docRootHtaccessPath);
        $htaccessSource = '.htaccess (DocumentRoot)';
        $htaccessDisplayPath = $docRootHtaccessPath;
    } elseif (!$htaccessExists && $htaccessContent === '') {
        $htaccessContent = getDefaultHtaccessContent();
        $htaccessSource = 'Vorlage (Datei fehlt)';
    }

    $loadedPhpIniPath = (string) (php_ini_loaded_file() ?: '');
    $userIniGlobalWritable = $loadedPhpIniPath !== '' && (
        is_file($loadedPhpIniPath) ? is_writable($loadedPhpIniPath) : is_writable(dirname($loadedPhpIniPath))
    );
    $htaccessGlobalWritable = $documentRoot !== '' && (
        is_file($docRootHtaccessPath) ? is_writable($docRootHtaccessPath) : is_writable($documentRoot)
    );

    // Speichern erfolgt im UI aktuell global; deshalb steuern diese Flags die Button-aktivierung.
    $userIniWritableEffective = $userIniGlobalWritable;
    $htaccessWritableEffective = $htaccessGlobalWritable;

    $hasManagedBlock = strpos($htaccessContent, '# BEGIN WEBAPP ERRORDOCUMENT') !== false;
    $allowOverride = detectAllowOverrideHint($hasManagedBlock);
    $uptimeSeconds = getSystemUptimeSeconds();
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [];
    $cpuCores = countCpuCores();

    $mem = parseProcMeminfo();
    $memTotal = $mem['MemTotal'] ?? null;
    $memAvailable = $mem['MemAvailable'] ?? null;
    $memUsed = ($memTotal !== null && $memAvailable !== null) ? max(0, $memTotal - $memAvailable) : null;
    $swapTotal = $mem['SwapTotal'] ?? null;
    $swapFree = $mem['SwapFree'] ?? null;
    $swapUsed = ($swapTotal !== null && $swapFree !== null) ? max(0, $swapTotal - $swapFree) : null;

    $diskTotalRoot = @disk_total_space('/');
    $diskFreeRoot = @disk_free_space('/');
    $diskTotalApp = @disk_total_space($rootDir);
    $diskFreeApp = @disk_free_space($rootDir);

    $apacheInfo = getApacheModuleInfo();
    $phpExtInfo = getPhpExtensionInfo();
    $pathChecks = getPathChecks($rootDir);
    $dbHealth = getDbHealth($pdo);

    $phpLimits = [
        'memory_limit' => (string) ini_get('memory_limit'),
        'memory_limit_bytes' => toBytes((string) ini_get('memory_limit')),
        'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
        'upload_max_filesize_bytes' => toBytes((string) ini_get('upload_max_filesize')),
        'post_max_size' => (string) ini_get('post_max_size'),
        'post_max_size_bytes' => toBytes((string) ini_get('post_max_size')),
        'max_execution_time' => (string) ini_get('max_execution_time'),
        'max_input_time' => (string) ini_get('max_input_time'),
        'max_input_vars' => (string) ini_get('max_input_vars')
    ];

    $memoryUsage = [
        'current_bytes' => memory_get_usage(true),
        'peak_bytes' => memory_get_peak_usage(true)
    ];

    $disabledFunctions = array_values(array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions')))));

    echo json_encode([
        'success' => true,
        'server_software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''),
        'server_name' => (string) ($_SERVER['SERVER_NAME'] ?? ''),
        'server_addr' => (string) ($_SERVER['SERVER_ADDR'] ?? ''),
        'document_root' => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
        'system_uname' => php_uname(),
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'php_int_size' => PHP_INT_SIZE,
        'os_family' => PHP_OS_FAMILY,
        'default_timezone' => (string) date_default_timezone_get(),
        'server_time' => date('Y-m-d H:i:s'),
        'loaded_php_ini' => (string) (php_ini_loaded_file() ?: ''),
        'scanned_php_ini' => (string) (php_ini_scanned_files() ?: ''),
        'user_ini_filename' => (string) ini_get('user_ini.filename'),
        'user_ini_cache_ttl' => (string) ini_get('user_ini.cache_ttl'),
        'disable_functions' => $disabledFunctions,
        'open_basedir' => (string) ini_get('open_basedir'),
        'display_errors' => (string) ini_get('display_errors'),
        'log_errors' => (string) ini_get('log_errors'),
        'error_reporting' => (string) ini_get('error_reporting'),
        'opcache_enabled' => extension_loaded('Zend OPcache') && ((bool) ini_get('opcache.enable') || (bool) ini_get('opcache.enable_cli')),
        'php_limits' => $phpLimits,
        'memory_usage' => $memoryUsage,
        'uptime_seconds' => $uptimeSeconds,
        'loadavg' => $load,
        'cpu_cores' => $cpuCores,
        'memory' => [
            'total_bytes' => $memTotal,
            'available_bytes' => $memAvailable,
            'used_bytes' => $memUsed,
            'swap_total_bytes' => $swapTotal,
            'swap_free_bytes' => $swapFree,
            'swap_used_bytes' => $swapUsed
        ],
        'disk' => [
            'root_total_bytes' => is_numeric($diskTotalRoot) ? (float) $diskTotalRoot : null,
            'root_free_bytes' => is_numeric($diskFreeRoot) ? (float) $diskFreeRoot : null,
            'app_total_bytes' => is_numeric($diskTotalApp) ? (float) $diskTotalApp : null,
            'app_free_bytes' => is_numeric($diskFreeApp) ? (float) $diskFreeApp : null
        ],
        'apache_modules' => $apacheInfo,
        'php_extensions' => $phpExtInfo,
        'db_health' => $dbHealth,
        'path_checks' => $pathChecks,
        'user_ini_path' => $userIniPath,
        'user_ini_display_path' => $userIniDisplayPath,
        'user_ini_source' => $userIniSource,
        'user_ini_exists' => $userIniExists,
        'user_ini_writable' => $userIniWritableEffective,
        'user_ini_global_path' => $loadedPhpIniPath,
        'user_ini_global_writable' => $userIniGlobalWritable,
        'user_ini_content' => $userIniContent,
        'htaccess_path' => $htaccessPath,
        'htaccess_display_path' => $htaccessDisplayPath,
        'htaccess_source' => $htaccessSource,
        'htaccess_exists' => $htaccessExists,
        'htaccess_writable' => $htaccessWritableEffective,
        'htaccess_global_path' => $docRootHtaccessPath,
        'htaccess_global_writable' => $htaccessGlobalWritable,
        'htaccess_content' => $htaccessContent,
        'allow_override_status' => $allowOverride['status'],
        'allow_override_message' => $allowOverride['message']
    ]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
    exit;
}

$action = (string) ($data['action'] ?? '');

if ($action === 'save_user_ini') {
    $content = (string) ($data['content'] ?? '');
    $saveScope = (string) ($data['scope'] ?? 'local');
    $targetPath = $userIniPath;
    if ($saveScope === 'global') {
        $loadedPhpIniPath = (string) (php_ini_loaded_file() ?: '');
        if ($loadedPhpIniPath === '') {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Globale php.ini konnte nicht ermittelt werden']);
            exit;
        }
        $targetPath = $loadedPhpIniPath;
    }
    if (@file_put_contents($targetPath, $content) === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Konnte nicht speichern: ' . $targetPath . ' (fehlende Dateirechte des Webservers?)'
        ]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'message' => 'Gespeichert in: ' . $targetPath . '. Aenderungen werden je nach Server nach kurzer Zeit wirksam.'
    ]);
    exit;
}

if ($action === 'save_htaccess') {
    $content = (string) ($data['content'] ?? '');
    $saveScope = (string) ($data['scope'] ?? 'local');
    $targetPath = $htaccessPath;
    if ($saveScope === 'global') {
        $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($documentRoot === '') {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'DocumentRoot konnte nicht ermittelt werden']);
            exit;
        }
        $targetPath = rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess';
    }
    if (@file_put_contents($targetPath, $content) === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Konnte nicht speichern: ' . $targetPath . ' (fehlende Dateirechte des Webservers?)'
        ]);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Gespeichert in: ' . $targetPath]);
    exit;
}

if ($action === 'save_error_documents') {
    $doc403 = trim((string) ($data['doc403'] ?? '/errors/403.php'));
    $doc404 = trim((string) ($data['doc404'] ?? '/errors/404.php'));
    $doc500 = trim((string) ($data['doc500'] ?? '/errors/500.php'));
    foreach ([$doc403, $doc404, $doc500] as $doc) {
        if ($doc === '' || $doc[0] !== '/' || preg_match('/\s/', $doc)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ErrorDocument-Pfade muessen mit / beginnen und duerfen keine Leerzeichen enthalten']);
            exit;
        }
    }
    if (!writeManagedErrorDocumentBlock($htaccessPath, $doc403, $doc404, $doc500)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'ErrorDocument-Block konnte nicht in .htaccess gespeichert werden']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'ErrorDocument-Block gespeichert']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
