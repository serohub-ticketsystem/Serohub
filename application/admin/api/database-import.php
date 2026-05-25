<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/database-backup-helper.php';

function db_import_redirect(string $query): void {
    header('Location: ' . BASE_URL . 'admin/database-backup.php?' . $query);
    exit;
}

[$ok, $msg] = db_backup_require_admin($pdo);
if (!$ok) {
    $_SESSION['db_import_flash'] = ['type' => 'error', 'text' => $msg];
    db_import_redirect('import=1');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nur POST erlaubt.';
    exit;
}

$confirm = isset($_POST['import_confirm']) ? trim((string) $_POST['import_confirm']) : '';
if ($confirm !== 'IMPORTIEREN') {
    $_SESSION['db_import_flash'] = [
        'type' => 'error',
        'text' => 'Zur Bestätigung müssen Sie exakt „IMPORTIEREN“ eintragen.',
    ];
    db_import_redirect('import=1');
}

if (!isset($_FILES['sql_file']) || !is_array($_FILES['sql_file'])) {
    $_SESSION['db_import_flash'] = ['type' => 'error', 'text' => 'Keine Datei übermittelt.'];
    db_import_redirect('import=1');
}

$file = $_FILES['sql_file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'Die Datei überschreitet upload_max_filesize oder post_max_size (PHP).',
        UPLOAD_ERR_FORM_SIZE  => 'Die Datei ist zu groß.',
        UPLOAD_ERR_PARTIAL    => 'Die Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE    => 'Bitte eine .sql- oder .sql.gz-Datei auswählen.',
        UPLOAD_ERR_NO_TMP_DIR => 'Kein temporäres Verzeichnis auf dem Server.',
        UPLOAD_ERR_CANT_WRITE => 'Schreibfehler beim Speichern der Upload-Datei.',
        UPLOAD_ERR_EXTENSION  => 'Eine PHP-Erweiterung hat den Upload blockiert.',
    ];
    $code = (int) ($file['error'] ?? 0);
    $_SESSION['db_import_flash'] = [
        'type' => 'error',
        'text' => $errMap[$code] ?? ('Upload-Fehler (Code ' . $code . ').'),
    ];
    db_import_redirect('import=1');
}

$origName = isset($file['name']) ? (string) $file['name'] : '';
$lower = strtolower($origName);
$isGzip = str_ends_with($lower, '.gz') || str_ends_with($lower, '.sql.gz');
$isSql = str_ends_with($lower, '.sql') || $isGzip;

if (!$isSql) {
    $_SESSION['db_import_flash'] = [
        'type' => 'error',
        'text' => 'Nur Dateien mit Endung .sql oder .sql.gz sind erlaubt.',
    ];
    db_import_redirect('import=1');
}

$tmp = (string) ($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    $_SESSION['db_import_flash'] = ['type' => 'error', 'text' => 'Ungültige Upload-Datei.'];
    db_import_redirect('import=1');
}

// Inhalt prüfen: Gzip-Magic oder Text/SQL
$peek = @file_get_contents($tmp, false, null, 0, 4);
if ($peek === false || $peek === '') {
    $_SESSION['db_import_flash'] = ['type' => 'error', 'text' => 'Die Datei ist leer oder nicht lesbar.'];
    db_import_redirect('import=1');
}

$magicGz = ($peek[0] === "\x1f" && $peek[1] === "\x8b");
if ($isGzip && !$magicGz) {
    $_SESSION['db_import_flash'] = [
        'type' => 'error',
        'text' => 'Die Datei endet auf .gz, ist aber kein gültiges Gzip-Archiv.',
    ];
    db_import_redirect('import=1');
}

$res = db_backup_mysql_import($tmp, $isGzip || $magicGz);
if (!$res['ok']) {
    $_SESSION['db_import_flash'] = ['type' => 'error', 'text' => $res['error']];
    db_import_redirect('import=1');
}

$_SESSION['db_import_flash'] = [
    'type' => 'success',
    'text' => 'Import abgeschlossen. Bitte prüfen Sie die Anwendung und melden Sie sich ggf. neu an.',
];
db_import_redirect('import=1');
