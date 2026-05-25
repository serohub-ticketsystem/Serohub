<?php
/**
 * Cron: CalDAV-Push-Sync
 * Führt periodisch den Sync für alle Benutzer mit aktivem CalDAV-Sync aus.
 * Empfohlen: alle 5–15 Minuten (Cron: alle 10 Min.)
 */

set_time_limit(300);
ini_set('memory_limit', '256M');

$logDir = dirname(__DIR__, 2) . '/logs';
if (!file_exists($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/caldav-sync.log';

function logCaldav($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

logCaldav('=== CalDAV-Sync gestartet ===');

try {
    require_once dirname(__DIR__, 2) . '/assets/config.php';
    require_once dirname(__DIR__, 2) . '/assets/caldav-sync.php';
    
    // SITE_URL für Ticket-Links im Cron (optional in config.php definieren)
    if (!defined('SITE_URL') && !empty($_SERVER['HTTP_HOST'])) {
        define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/');
    }
    if (!defined('SITE_URL')) {
        define('SITE_URL', 'https://localhost/');
    }
    
    $stmt = $pdo->query("
        SELECT s.id, s.user_id, s.caldav_username, s.caldav_password, s.calendar_name, s.export_sources,
               sr.url AS server_url
        FROM user_caldav_sync s
        JOIN caldav_servers sr ON s.caldav_server_id = sr.id
        WHERE s.is_active = 1 AND sr.is_active = 1
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $defaultExportSources = ['my_calendar' => true, 'vacation' => true, 'invitations' => true, 'service_tickets' => true, 'todos' => true];
    
    foreach ($configs as $cfg) {
        $syncId = (int) $cfg['id'];
        $userId = (int) $cfg['user_id'];
        $password = caldav_decrypt_password($cfg['caldav_password']);
        if (empty($password)) {
            logCaldav("User $userId: Kein Passwort, überspringe");
            continue;
        }
        
        $baseUrl = normalizeCalDAVUrl($cfg['server_url']);
        $calendarUrl = $baseUrl . 'calendars/' . rawurlencode($cfg['caldav_username']) . '/' . rawurlencode($cfg['calendar_name']) . '/';
        
        // Export-Quellen: pro Sync oder globale User-Einstellung
        $exportSources = $defaultExportSources;
        if (!empty($cfg['export_sources'])) {
            $dec = json_decode($cfg['export_sources'], true);
            if (is_array($dec)) $exportSources = array_merge($defaultExportSources, $dec);
        } else {
            $us = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'calendar_export_sources_caldav' LIMIT 1");
            $us->execute([$userId]);
            $row = $us->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['setting_value']) {
                $dec = json_decode($row['setting_value'], true);
                if (is_array($dec)) $exportSources = array_merge($defaultExportSources, $dec);
            } else {
                $us2 = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'calendar_export_sources' LIMIT 1");
                $us2->execute([$userId]);
                $row2 = $us2->fetch(PDO::FETCH_ASSOC);
                if ($row2 && $row2['setting_value']) {
                    $dec = json_decode($row2['setting_value'], true);
                    if (is_array($dec)) $exportSources = array_merge($defaultExportSources, $dec);
                }
            }
        }
        
        $roleStmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
        $roleStmt->execute([$userId]);
        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
        $isAdminOrTechniker = $roleRow && in_array($roleRow['rolle'] ?? '', ['Admin', 'Techniker'], true);
        $events = getEventsForUser($pdo, $userId, $exportSources, $isAdminOrTechniker);
        $result = pushEventsToCalDAV($calendarUrl, $cfg['caldav_username'], $password, $events, 'logCaldav');
        
        $status = empty($result['errors']) ? 'ok' : 'partial';
        if ($result['success'] === 0 && !empty($result['errors'])) $status = 'error';
        $message = $result['success'] . '/' . $result['total'] . ' Events synchronisiert';
        if (!empty($result['errors'])) {
            $message .= '; Fehler: ' . implode('; ', array_slice($result['errors'], 0, 3));
            if (count($result['errors']) > 3) $message .= ' ...';
        }
        
        $upd = $pdo->prepare("UPDATE user_caldav_sync SET last_sync = NOW(), last_sync_status = ?, last_sync_message = ? WHERE id = ?");
        $upd->execute([$status, $message, $syncId]);
        
        logCaldav("User $userId: $message");
    }
    
    logCaldav('=== CalDAV-Sync beendet ===');
} catch (Throwable $e) {
    logCaldav('FEHLER: ' . $e->getMessage());
    exit(1);
}
