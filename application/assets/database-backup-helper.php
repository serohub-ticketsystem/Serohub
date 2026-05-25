<?php
/**
 * Hilfsfunktionen für DB-Export/Import (Admin, Systemwechsel).
 * Export enthält die Rohdaten wie in MySQL gespeichert (inkl. ENC:-Verschlüsselung).
 */

/**
 * @return array{0:bool,1:string} [ok, message]
 */
function db_backup_require_admin(PDO $pdo): array {
    if (!isset($_SESSION['user_id'])) {
        return [false, 'Nicht angemeldet'];
    }
    try {
        $stmt = $pdo->prepare('SELECT rolle FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || ($user['rolle'] ?? '') !== 'Admin') {
            return [false, 'Nur Administratoren dürfen Datenbank-Exporte und -Importe ausführen.'];
        }
    } catch (PDOException $e) {
        return [false, 'Datenbankfehler bei der Berechtigungsprüfung.'];
    }
    return [true, ''];
}

function db_backup_find_cli(string $name): ?string {
    $out = [];
    $code = 0;
    @exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $out, $code);
    if ($code === 0 && !empty($out[0]) && is_executable($out[0])) {
        return $out[0];
    }
    $fixed = '/usr/bin/' . $name;
    if (is_file($fixed) && is_executable($fixed)) {
        return $fixed;
    }
    $fixed = '/usr/local/bin/' . $name;
    if (is_file($fixed) && is_executable($fixed)) {
        return $fixed;
    }
    return null;
}

/**
 * @param list<string> $dumpArgs Zusätzliche mysqldump-Optionen (z. B. --routines)
 * @return array{ok:bool, path:?string, error:string}
 */
function db_backup_mysqldump_run(array $dumpArgs, string &$outputSql): array {
    $bin = db_backup_find_cli('mysqldump');
    if (!$bin) {
        return ['ok' => false, 'path' => null, 'error' => 'mysqldump nicht gefunden'];
    }

    $cmd = array_merge(
        [$bin],
        [
            '--single-transaction',
            '--quick',
            '--default-character-set=utf8mb4',
            '--add-drop-table',
            '--set-charset',
        ],
        $dumpArgs,
        ['-h', DB_HOST, '-u', DB_USER, '-p' . DB_PASS, DB_NAME]
    );

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = @proc_open($cmd, $descriptors, $pipes, null, null);
    if (!is_resource($proc)) {
        return ['ok' => false, 'path' => $bin, 'error' => 'mysqldump konnte nicht gestartet werden'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    if ($code !== 0) {
        $err = trim($stderr) !== '' ? trim($stderr) : 'Exit-Code ' . $code;
        return ['ok' => false, 'path' => $bin, 'error' => $err];
    }

    $outputSql = $stdout !== false ? $stdout : '';
    return ['ok' => true, 'path' => $bin, 'error' => ''];
}

/**
 * @return array{ok:bool, path:?string, error:string}
 */
function db_backup_mysqldump_export(string &$outputSql): array {
    $r = db_backup_mysqldump_run(['--routines', '--triggers'], $outputSql);
    if ($r['ok']) {
        return $r;
    }
    $err = $r['error'];
    if (stripos($err, 'routines') !== false || stripos($err, 'DEFINER') !== false || stripos($err, 'PROCEDURE') !== false) {
        return db_backup_mysqldump_run(['--triggers'], $outputSql);
    }
    return $r;
}

/**
 * Vollständiger SQL-Dump per PDO (langsamer, mehr Speicher bei sehr großen Tabellen).
 *
 * @return array{ok:bool, error:string}
 */
function db_backup_pdo_export(PDO $pdo, string &$outputSql): array {
    try {
        $pdo->exec('SET NAMES utf8mb4');
        $dbName = DB_NAME;
        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME'
        );
        $stmt->execute([$dbName]);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $parts = [];
        $parts[] = '-- Datenbank-Export (PHP/PDO), ' . gmdate('Y-m-d H:i:s') . " UTC\n";
        $parts[] = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";

        foreach ($tables as $table) {
            $t = (string) $table;
            $tEsc = str_replace('`', '``', $t);
            $show = $pdo->query('SHOW CREATE TABLE `' . $tEsc . '`');
            if (!$show) {
                return ['ok' => false, 'error' => 'SHOW CREATE TABLE fehlgeschlagen: ' . $t];
            }
            $row = $show->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['Create Table'])) {
                return ['ok' => false, 'error' => 'Kein CREATE für Tabelle: ' . $t];
            }
            $parts[] = "\nDROP TABLE IF EXISTS `" . $tEsc . "`;\n";
            $parts[] = $row['Create Table'] . ";\n";

            $countStmt = $pdo->query('SELECT COUNT(*) FROM `' . $tEsc . '`');
            $total = (int) $countStmt->fetchColumn();
            $batch = 400;
            for ($offset = 0; $offset < $total; $offset += $batch) {
                $sel = $pdo->prepare('SELECT * FROM `' . $tEsc . '` LIMIT ' . (int) $batch . ' OFFSET ' . (int) $offset);
                $sel->execute();
                $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
                if ($rows === []) {
                    break;
                }
                $cols = array_keys($rows[0]);
                $colList = '`' . implode('`,`', array_map(static function ($c) {
                    return str_replace('`', '``', $c);
                }, $cols)) . '`';
                $valueGroups = [];
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($cols as $c) {
                        if (!array_key_exists($c, $r) || $r[$c] === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote($r[$c]);
                        }
                    }
                    $valueGroups[] = '(' . implode(',', $vals) . ')';
                }
                $parts[] = 'INSERT INTO `' . $tEsc . '` (' . $colList . ') VALUES ' . implode(",\n", $valueGroups) . ";\n";
            }
        }

        $parts[] = "\nSET FOREIGN_KEY_CHECKS=1;\n";
        $outputSql = implode('', $parts);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * @return array{ok:bool, error:string, stderr:string}
 */
function db_backup_mysql_import(string $sqlPath, bool $isGzip): array {
    $mysql = db_backup_find_cli('mysql');
    if (!$mysql) {
        return ['ok' => false, 'error' => 'Das Programm „mysql“ wurde nicht gefunden. Bitte auf dem Server installieren oder den Import per Kommandozeile ausführen.', 'stderr' => ''];
    }

    if (!is_readable($sqlPath)) {
        return ['ok' => false, 'error' => 'Die SQL-Datei ist nicht lesbar.', 'stderr' => ''];
    }

    if ($isGzip && !function_exists('gzopen')) {
        return ['ok' => false, 'error' => 'PHP zlib (gzopen) fehlt – .sql.gz kann nicht gelesen werden.', 'stderr' => ''];
    }

    $cmd = [
        $mysql,
        '-h', DB_HOST,
        '-u', DB_USER,
        '-p' . DB_PASS,
        '--default-character-set=utf8mb4',
        DB_NAME,
    ];

    $descriptors = [
        0 => ['pipe', 'w'],
        1 => ['pipe', 'r'],
        2 => ['pipe', 'r'],
    ];

    $proc = @proc_open($cmd, $descriptors, $pipes, null, null);
    if (!is_resource($proc)) {
        return ['ok' => false, 'error' => 'mysql konnte nicht gestartet werden.', 'stderr' => ''];
    }

    if ($isGzip) {
        $fh = @gzopen($sqlPath, 'rb');
        if (!$fh) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            return ['ok' => false, 'error' => 'Gzip-Datei konnte nicht geöffnet werden.', 'stderr' => ''];
        }
        stream_copy_to_stream($fh, $pipes[0]);
        gzclose($fh);
    } else {
        $fh = fopen($sqlPath, 'rb');
        if (!$fh) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            return ['ok' => false, 'error' => 'SQL-Datei konnte nicht geöffnet werden.', 'stderr' => ''];
        }
        stream_copy_to_stream($fh, $pipes[0]);
        fclose($fh);
    }
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    if ($code !== 0) {
        $err = trim($stderr) !== '' ? trim($stderr) : (trim($stdout) !== '' ? trim($stdout) : 'Exit-Code ' . $code);
        return ['ok' => false, 'error' => 'Import fehlgeschlagen: ' . $err, 'stderr' => $stderr];
    }

    return ['ok' => true, 'error' => '', 'stderr' => ''];
}
