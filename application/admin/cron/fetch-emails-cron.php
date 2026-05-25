<?php
/**
 * Cron: E-Mail-Empfang
 * Ruft automatisch E-Mails ab und wandelt sie in Tickets um.
 * Empfohlen: alle 5-15 Minuten (Cron: alle 10 Min.)
 */

// Absoluten Pfad zur Webapp bestimmen
// __FILE__ ist immer der absolute Pfad zur aktuellen Datei
$scriptDir = dirname(__FILE__);
$webappDir = dirname($scriptDir, 1); // Ein Verzeichnis nach oben (von cron zu admin)
$baseDir = dirname($webappDir, 1); // Noch ein Verzeichnis nach oben (von admin zu webapp)

// Arbeitsverzeichnis auf das Webapp-Verzeichnis setzen
// Das ist wichtig, damit relative Pfade in config.php funktionieren
chdir($baseDir);

set_time_limit(300);
ini_set('memory_limit', '256M');

$logDir = $baseDir . '/logs';
if (!file_exists($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/email-receive-cron.log';

function logEmailCron($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    // Im CLI-Modus auch auf stdout ausgeben
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}

logEmailCron('=== E-Mail-Empfang Cronjob gestartet ===');
logEmailCron('PHP Version: ' . PHP_VERSION);
logEmailCron('PHP SAPI: ' . php_sapi_name());
logEmailCron('Working Directory: ' . getcwd());
logEmailCron('Script Directory: ' . $scriptDir);
logEmailCron('Base Directory: ' . $baseDir);

try {
    $configFile = $baseDir . '/assets/config.php';
    if (!file_exists($configFile)) {
        logEmailCron('FEHLER: config.php nicht gefunden in: ' . $configFile);
        exit(1);
    }
    require_once $configFile;
    
    // Prüfen ob IMAP-Erweiterung verfügbar ist
    if (!function_exists('imap_open')) {
        logEmailCron('FEHLER: IMAP-Erweiterung ist nicht verfügbar');
        exit(1);
    }
    
    // E-Mail-Empfang-Einstellungen aus Datenbank laden
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'email_receive_%'");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $settings = [
        'enabled' => false,
        'protocol' => 'imap',
        'host' => '',
        'port' => 993,
        'secure' => 'ssl',
        'username' => '',
        'password' => '',
        'mailbox' => 'INBOX'
    ];
    
    foreach ($results as $row) {
        $key = str_replace('email_receive_', '', $row['setting_key']);
        if ($key === 'enabled') {
            $settings['enabled'] = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
        } elseif ($key === 'port') {
            $settings['port'] = (int)$row['setting_value'];
        } else {
            $settings[$key] = $row['setting_value'];
        }
    }
    
    if (!$settings['enabled']) {
        logEmailCron('E-Mail-Empfang ist nicht aktiviert - Cronjob beendet');
        exit(0);
    }
    
    if (empty($settings['host']) || empty($settings['username']) || empty($settings['password'])) {
        logEmailCron('FEHLER: E-Mail-Empfang-Einstellungen sind unvollständig');
        exit(1);
    }
    
    // Funktionen aus fetch-emails.php einbinden
    // Wir setzen EMAIL_CRON_MODE, damit fetch-emails.php weiß, dass es im Cronjob läuft
    // und die Session-Prüfungen überspringt
    define('EMAIL_CRON_MODE', true);
    
    $fetchEmailsFile = $baseDir . '/admin/api/fetch-emails.php';
    
    if (!file_exists($fetchEmailsFile)) {
        logEmailCron('FEHLER: fetch-emails.php nicht gefunden in: ' . $fetchEmailsFile);
        logEmailCron('Suche in: ' . dirname($scriptDir) . '/api/fetch-emails.php');
        exit(1);
    }
    
    logEmailCron('Lade fetch-emails.php von: ' . $fetchEmailsFile);
    
    // Datei einbinden - im Cronjob-Modus wird sie nur config.php laden
    // und dann return, damit die Funktionen verfügbar sind
    require_once $fetchEmailsFile;
    
    // Prüfen ob die Funktionen verfügbar sind
    if (!function_exists('fetchEmailsAndConvertToTickets')) {
        logEmailCron('FEHLER: Funktion fetchEmailsAndConvertToTickets nicht gefunden');
        exit(1);
    }
    
    // Limit aus Einstellungen oder Standard
    $limit = 50; // Standard-Limit für Cronjob (kann in Einstellungen gespeichert werden)
    
    // E-Mails abrufen und in Tickets umwandeln
    logEmailCron('Starte E-Mail-Abruf...');
    $result = fetchEmailsAndConvertToTickets($settings, $limit);
    
    // Ergebnis loggen
    $ticketsCreated = $result['tickets_created'] ?? 0;
    $emailsRejected = $result['emails_rejected'] ?? 0;
    $emailsProcessed = count($result['emails'] ?? []);
    
    logEmailCron("E-Mail-Abruf abgeschlossen: {$emailsProcessed} E-Mail(s) verarbeitet, {$ticketsCreated} Ticket(s) erstellt, {$emailsRejected} E-Mail(s) verworfen");
    
    // Detail-Logging pro E-Mail, damit im Admin-Cronjob-Test sichtbar ist,
    // was mit einzelnen Nachrichten passiert ist.
    if (!empty($result['emails']) && is_array($result['emails'])) {
        foreach ($result['emails'] as $mail) {
            $subject = trim((string)($mail['subject'] ?? '(Kein Betreff)'));
            $from = trim((string)($mail['from'] ?? 'Unbekannt'));
            $ticketId = isset($mail['ticket_id']) ? (string)$mail['ticket_id'] : '';
            $isConverted = isset($mail['converted']) && $mail['converted'] === true;
            $rejectionReason = trim((string)($mail['rejection_reason'] ?? ''));
            
            if ($isConverted && $ticketId !== '') {
                logEmailCron('MAIL: ERSTELLT | Ticket #' . $ticketId . ' | Von: ' . $from . ' | Betreff: ' . $subject);
            } elseif ($isConverted) {
                logEmailCron('MAIL: ERSTELLT | Ticket erstellt | Von: ' . $from . ' | Betreff: ' . $subject);
            } else {
                $reasonText = $rejectionReason !== '' ? $rejectionReason : 'Unbekannter Verwerfungsgrund';
                logEmailCron('MAIL: VERWORFEN | Von: ' . $from . ' | Betreff: ' . $subject . ' | Grund: ' . $reasonText);
            }
        }
    } else {
        logEmailCron('MAIL: Keine E-Mails zur Detailausgabe vorhanden.');
    }
    
    if ($ticketsCreated > 0) {
        logEmailCron("✓ {$ticketsCreated} Ticket(s) erfolgreich erstellt");
    }
    
    if ($emailsRejected > 0) {
        logEmailCron("✗ {$emailsRejected} E-Mail(s) verworfen");
    }
    
    logEmailCron('=== E-Mail-Empfang Cronjob beendet ===');
    
} catch (Throwable $e) {
    logEmailCron('FEHLER: ' . $e->getMessage());
    logEmailCron('Fehler-Typ: ' . get_class($e));
    logEmailCron('Datei: ' . $e->getFile());
    logEmailCron('Zeile: ' . $e->getLine());
    logEmailCron('Stack trace: ' . $e->getTraceAsString());
    
    // Auch auf stderr ausgeben (für Cron-Logs)
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, 'FEHLER: ' . $e->getMessage() . PHP_EOL);
    }
    
    exit(1);
}
