<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/database-backup-helper.php';

[$ok, $msg] = db_backup_require_admin($pdo);
if (!$ok) {
    http_response_code($msg === 'Nicht angemeldet' ? 401 : 403);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nur GET erlaubt.';
    exit;
}

$safeDb = preg_replace('/[^a-zA-Z0-9_-]+/', '_', DB_NAME);
$filename = $safeDb . '_backup_' . date('Y-m-d_His') . '.sql';

$sql = '';
$mysqldumpPath = db_backup_find_cli('mysqldump');
if ($mysqldumpPath) {
    $res = db_backup_mysqldump_export($sql);
    if (!$res['ok']) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Export fehlgeschlagen: ' . $res['error'];
        exit;
    }
} else {
    $res = db_backup_pdo_export($pdo, $sql);
    if (!$res['ok']) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Export fehlgeschlagen (PDO): ' . $res['error'];
        exit;
    }
}

header('Content-Type: application/octet-stream; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $sql;
exit;
