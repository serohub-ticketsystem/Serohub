<?php
// Output-Buffering starten, um sicherzustellen, dass keine unerwarteten Ausgaben das JSON stören
if (!ob_get_level()) {
    ob_start();
}

// Error-Handler registrieren, um sicherzustellen, dass immer JSON zurückgegeben wird
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fataler PHP-Fehler: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        ob_end_flush();
    }
});

// Prüfen ob im Cronjob-Modus (Funktionen werden von Cronjob verwendet)
$isCronMode = defined('EMAIL_CRON_MODE') && EMAIL_CRON_MODE === true;

if (!$isCronMode) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    try {
        require_once dirname(__DIR__, 2) . '/assets/config.php';
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler beim Laden der Konfiguration: ' . $e->getMessage()]);
        ob_end_flush();
        exit;
    }

    header('Content-Type: application/json');

    // Prüfen ob IMAP-Erweiterung verfügbar ist
    if (!function_exists('imap_open')) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'IMAP-Erweiterung ist nicht verfügbar. Bitte installieren Sie die PHP IMAP-Erweiterung.'
        ]);
        ob_end_flush();
        exit;
    }

    // Prüfen ob eingeloggt und Admin
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
        ob_end_flush();
        exit;
    }

    $userId = $_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['rolle'] !== 'Admin') {
            ob_clean();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
            ob_end_flush();
            exit;
        }
    } catch (PDOException $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
        ob_end_flush();
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'GET') {
        ob_clean();
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
        ob_end_flush();
        exit;
    }
} else {
    // Cronjob-Modus: Nur config.php laden, keine Session-Prüfungen
    try {
        require_once dirname(__DIR__, 2) . '/assets/config.php';
    } catch (Exception $e) {
        // Im Cronjob-Modus können wir nicht JSON ausgeben, daher Exception werfen
        throw new Exception('Fehler beim Laden der Konfiguration: ' . $e->getMessage());
    }
    
    // Prüfen ob IMAP-Erweiterung verfügbar ist
    if (!function_exists('imap_open')) {
        throw new Exception('IMAP-Erweiterung ist nicht verfügbar. Bitte installieren Sie die PHP IMAP-Erweiterung.');
    }
    
    // Im Cronjob-Modus beenden wir hier, damit die Funktionen verfügbar sind
    // aber keine JSON-Ausgabe kommt
    return;
}

if (!$isCronMode) {
    try {

    // Limit aus Query-Parameter
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = max(1, min(100, $limit)); // Zwischen 1 und 100

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
        $settingKey = str_replace('email_receive_', '', $row['setting_key']);
        if ($settingKey === 'enabled') {
            $settings['enabled'] = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
        } elseif ($settingKey === 'port') {
            $settings['port'] = (int)$row['setting_value'];
        } else {
            $settings[$settingKey] = $row['setting_value'];
        }
    }
    
    if (!$settings['enabled']) {
        ob_clean();
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'E-Mail-Empfang ist nicht aktiviert']);
        ob_end_flush();
        exit;
    }
    
    if (empty($settings['host']) || empty($settings['username']) || empty($settings['password'])) {
        ob_clean();
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'E-Mail-Empfang-Einstellungen sind unvollständig']);
        ob_end_flush();
        exit;
    }

    // E-Mails abrufen und in Tickets umwandeln
    try {
        $result = fetchEmailsAndConvertToTickets($settings, $limit);
        
        // Prüfen ob Ergebnis korrekt ist
        if (!isset($result['emails']) || !is_array($result['emails'])) {
            throw new Exception("Ungültiges Ergebnis von fetchEmailsAndConvertToTickets: " . print_r($result, true));
        }
        
        ob_clean(); // Sicherstellen, dass keine anderen Outputs vorhanden sind
        header('Content-Type: application/json; charset=utf-8');
        
        // E-Mail-Daten bereinigen und UTF-8 konvertieren
        $cleanedEmails = cleanEmailsForJson($result['emails']);
        
        $output = json_encode([
            'success' => true,
            'emails' => $cleanedEmails,
            'tickets_created' => $result['tickets_created'] ?? 0,
            'emails_rejected' => $result['emails_rejected'] ?? 0,
            'count' => count($cleanedEmails)
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        
        if ($output === false) {
            $jsonError = json_last_error_msg();
            error_log("JSON-Encoding-Fehler: " . $jsonError);
            // Versuche es nochmal mit bereinigten Daten
            $cleanedEmails = cleanEmailsForJson($result['emails'], true); // Aggressivere Bereinigung
            $output = json_encode([
                'success' => true,
                'emails' => $cleanedEmails,
                'tickets_created' => $result['tickets_created'] ?? 0,
                'emails_rejected' => $result['emails_rejected'] ?? 0,
                'count' => count($cleanedEmails)
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            if ($output === false) {
                throw new Exception("JSON-Encoding-Fehler: " . $jsonError);
            }
        }
        
        echo $output;
        ob_end_flush();
        exit;
    } catch (Exception $innerException) {
        ob_clean();
        http_response_code(500);
        error_log("Fehler in fetchEmailsAndConvertToTickets: " . $innerException->getMessage());
        error_log("Stack trace: " . $innerException->getTraceAsString());
        header('Content-Type: application/json');
        $errorOutput = json_encode([
            'success' => false,
            'message' => 'Fehler beim Abrufen der E-Mails: ' . $innerException->getMessage(),
            'error_type' => get_class($innerException),
            'file' => $innerException->getFile(),
            'line' => $innerException->getLine()
        ]);
        
        if ($errorOutput === false) {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Erstellen der Fehlerantwort']);
        } else {
            echo $errorOutput;
        }
        ob_end_flush();
        exit;
    }

} catch (Exception $e) {
    ob_clean(); // Alle bisherigen Outputs löschen
    http_response_code(500);
    error_log("Fetch Emails API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Detaillierte Fehlerinformationen zurückgeben
    $errorDetails = [
        'success' => false,
        'message' => 'Fehler beim Abrufen der E-Mails: ' . $e->getMessage(),
        'error_type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    // Nur in Development-Umgebung detaillierte Stack-Traces senden
    if (defined('DEBUG') && DEBUG) {
        $errorDetails['stack_trace'] = $e->getTraceAsString();
    }
    
    header('Content-Type: application/json');
    echo json_encode($errorDetails);
    ob_end_flush();
    exit;
    }
}

/**
 * Bereinigt einen String für die Datenbank (UTF-8 sicher)
 */
function cleanStringForDatabase($string) {
    if (!is_string($string)) {
        return '';
    }
    
    // Entferne NULL-Bytes und andere problematische Zeichen
    $string = str_replace(["\0", "\x1A"], '', $string);
    
    // Prüfe und konvertiere zu UTF-8
    if (!mb_check_encoding($string, 'UTF-8')) {
        // Versuche Encoding zu erkennen
        $detected = @mb_detect_encoding($string, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($detected && $detected !== 'UTF-8') {
            $string = @mb_convert_encoding($string, 'UTF-8', $detected);
        } else {
            // Fallback: Versuche mit auto
            $string = @mb_convert_encoding($string, 'UTF-8', 'auto');
        }
    }
    
    // Entferne ungültige UTF-8 Zeichen durch Konvertierung
    // Dies entfernt automatisch ungültige Zeichen
    $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    
    // Entferne Steuerzeichen außer Tabs (\x09), Newlines (\x0A) und Carriage Returns (\x0D)
    $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);
    
    // Zusätzliche Bereinigung: Entferne alle nicht-druckbaren Zeichen außer den erlaubten
    // Erlaubt: Tabs, Newlines, Carriage Returns und alle druckbaren Unicode-Zeichen
    // Dies entfernt auch problematische Zeichen wie \xFC (ü in ISO-8859-1) wenn nicht korrekt konvertiert
    $string = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]/u', '', $string);
    
    // Finale Prüfung: Wenn immer noch ungültige UTF-8 Zeichen vorhanden sind, entferne sie
    if (!mb_check_encoding($string, 'UTF-8')) {
        // Verwende iconv als Fallback, um ungültige Zeichen zu entfernen
        if (function_exists('iconv')) {
            $string = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        } else {
            // Fallback: Entferne alle nicht-UTF-8 Zeichen manuell
            $string = preg_replace('/[^\x00-\x7F]/', '', $string);
        }
    }
    
    return $string;
}

/**
 * Bereinigt E-Mail-Daten für JSON-Encoding
 */
function cleanEmailsForJson($emails, $aggressive = false) {
    if (!is_array($emails)) {
        return [];
    }
    
    $cleaned = [];
    foreach ($emails as $email) {
        $cleanedEmail = [];
        foreach ($email as $key => $value) {
            if (is_string($value)) {
                // UTF-8 Encoding sicherstellen
                if (!mb_check_encoding($value, 'UTF-8')) {
                    // Versuche zu konvertieren
                    $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
                
                if ($aggressive) {
                    // Aggressive Bereinigung: Entferne ungültige UTF-8 Zeichen
                    $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    // Entferne Steuerzeichen außer Tabs, Newlines und Carriage Returns
                    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
                } else {
                    // Sanfte Bereinigung: Nur offensichtlich fehlerhafte Zeichen entfernen
                    // Entferne NULL-Bytes und andere problematische Zeichen
                    $value = str_replace(["\0", "\x1A"], '', $value);
                    // Prüfe und konvertiere zu UTF-8
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        $detected = mb_detect_encoding($value, mb_detect_order(), true);
                        if ($detected) {
                            $value = mb_convert_encoding($value, 'UTF-8', $detected);
                        } else {
                            // Fallback: Versuche mit auto
                            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                        }
                    }
                }
                
                // Entferne NULL-Bytes
                $value = str_replace("\0", '', $value);
            } elseif (is_array($value)) {
                // Rekursiv für verschachtelte Arrays
                $value = cleanEmailsForJson($value, $aggressive);
            }
            
            $cleanedEmail[$key] = $value;
        }
        $cleaned[] = $cleanedEmail;
    }
    
    return $cleaned;
}

/**
 * Ruft E-Mails über IMAP oder POP3 ab und wandelt sie in Tickets um
 */
function fetchEmailsAndConvertToTickets($settings, $limit = 10) {
    global $pdo;
    
    $emails = [];
    $ticketsCreated = 0;
    $emailsRejected = 0;
    
    try {
        if ($settings['protocol'] === 'imap') {
            $rawEmails = fetchEmailsViaIMAP($settings, $limit);
        } elseif ($settings['protocol'] === 'pop3') {
            $rawEmails = fetchEmailsViaPOP3($settings, $limit);
        } else {
            throw new Exception("Unbekanntes Protokoll: " . $settings['protocol']);
        }
        
        // Sicherstellen, dass $rawEmails ein Array ist
        if (!is_array($rawEmails)) {
            error_log("fetchEmailsViaIMAP/POP3 hat kein Array zurückgegeben: " . gettype($rawEmails));
            $rawEmails = [];
        }
        
        // Jede E-Mail in ein Ticket umwandeln
        foreach ($rawEmails as $email) {
            try {
                $result = convertEmailToTicket($email, $settings);
                if ($result && isset($result['ticket_id']) && $result['ticket_id'] !== null) {
                    $ticketsCreated++;
                    $email['ticket_id'] = $result['ticket_id'];
                    $email['converted'] = true;
                } else {
                    $emailsRejected++;
                    $email['converted'] = false;
                    $email['rejection_reason'] = isset($result['reason']) ? $result['reason'] : 'Absender konnte nicht zugeordnet werden';
                    
                    // Verworfenen E-Mail in "Verworfen" Ordner verschieben (nur IMAP)
                    if ($settings['protocol'] === 'imap' && isset($email['id'])) {
                        try {
                            moveEmailToRejectedFolder($email['id'], $settings);
                        } catch (Exception $moveException) {
                            error_log("Fehler beim Verschieben der verworfenen E-Mail: " . $moveException->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Fehler beim Umwandeln der E-Mail in Ticket: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $emailsRejected++;
                $email['converted'] = false;
                $email['rejection_reason'] = $e->getMessage();
                
                // Verworfenen E-Mail in "Verworfen" Ordner verschieben (nur IMAP)
                if ($settings['protocol'] === 'imap' && isset($email['id'])) {
                    try {
                        moveEmailToRejectedFolder($email['id'], $settings);
                    } catch (Exception $moveException) {
                        error_log("Fehler beim Verschieben der verworfenen E-Mail: " . $moveException->getMessage());
                    }
                }
            }
            
            $emails[] = $email;
        }
    } catch (Exception $e) {
        error_log("Fehler beim Abrufen der E-Mails: " . $e->getMessage());
        throw $e;
    }
    
    // Sicherstellen, dass immer ein korrektes Array zurückgegeben wird
    return [
        'emails' => is_array($emails) ? $emails : [],
        'tickets_created' => (int)$ticketsCreated,
        'emails_rejected' => (int)$emailsRejected
    ];
}

/**
 * Ruft E-Mails über IMAP oder POP3 ab (nur für Anzeige)
 */
function fetchEmails($settings, $limit = 10) {
    $emails = [];
    
    try {
        if ($settings['protocol'] === 'imap') {
            $emails = fetchEmailsViaIMAP($settings, $limit);
        } elseif ($settings['protocol'] === 'pop3') {
            $emails = fetchEmailsViaPOP3($settings, $limit);
        } else {
            throw new Exception("Unbekanntes Protokoll: " . $settings['protocol']);
        }
    } catch (Exception $e) {
        error_log("Fehler beim Abrufen der E-Mails: " . $e->getMessage());
        throw $e;
    }
    
    return $emails;
}

/**
 * Ruft E-Mails über IMAP ab
 */
function fetchEmailsViaIMAP($settings, $limit) {
    $emails = [];
    
    // IMAP-Verbindungsstring erstellen
    $host = $settings['host'];
    $port = $settings['port'];
    $secure = $settings['secure'];
    $mailbox = $settings['mailbox'];
    
    // IMAP-Verbindungsstring
    if ($secure === 'ssl') {
        $connectionString = "{{$host}:{$port}/imap/ssl}";
    } elseif ($secure === 'tls') {
        $connectionString = "{{$host}:{$port}/imap/tls}";
    } else {
        $connectionString = "{{$host}:{$port}/imap/notls}";
    }
    
    $connectionString .= $mailbox;
    
    // Verbindung herstellen (ohne OP_READONLY, damit wir als gelesen markieren können)
    $mailbox = imap_open($connectionString, $settings['username'], $settings['password']);
    
    if (!$mailbox) {
        $errors = imap_errors();
        $errorMsg = $errors ? implode(', ', $errors) : 'Unbekannter Fehler';
        throw new Exception("IMAP-Verbindung fehlgeschlagen: " . $errorMsg);
    }
    
    // Nur ungelesene E-Mails abrufen
    $unreadMessages = imap_search($mailbox, 'UNSEEN');
    
    if (!$unreadMessages) {
        imap_close($mailbox);
        return [];
    }
    
    // Limit anwenden (neueste zuerst)
    $unreadMessages = array_slice(array_reverse($unreadMessages), 0, $limit);
    
    foreach ($unreadMessages as $msgNum) {
        $header = imap_headerinfo($mailbox, $msgNum);
        
        if (!$header) {
            continue;
        }
        
        // Nur ungelesene Mails verarbeiten
        if ($header->Unseen != 'U') {
            continue;
        }
        
        // Vollständige E-Mail-Daten abrufen
        $structure = imap_fetchstructure($mailbox, $msgNum);
        $body = extractBody($mailbox, $msgNum, $structure);
        
        // CC-Empfänger extrahieren (inkl. Einzel-Cc als Objekt, nicht Array)
        $ccEmails = imapAddressListToEmailStrings($header->cc ?? null);
        
        // Anhänge extrahieren
        $attachments = extractAttachments($mailbox, $msgNum, $structure);
        
        // Bilder direkt aus der Struktur extrahieren und zu Anhängen hinzufügen
        $images = extractImagesFromStructure($mailbox, $msgNum, $structure);
        if (!empty($images)) {
            // Bilder zu Anhängen hinzufügen (wenn noch nicht vorhanden)
            foreach ($images as $image) {
                $found = false;
                foreach ($attachments as $attachment) {
                    if (isset($attachment['filename']) && $attachment['filename'] === $image['filename']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $attachments[] = $image;
                }
            }
        }
        
        // E-Mail-Datum extrahieren (als Timestamp für Datenbank)
        $emailDate = null;
        $emailDateFormatted = '';
        if (isset($header->date)) {
            $emailTimestamp = strtotime($header->date);
            if ($emailTimestamp !== false) {
                $emailDate = date('Y-m-d H:i:s', $emailTimestamp);
                $emailDateFormatted = date('d.m.Y H:i', $emailTimestamp);
            }
        }
        
        $email = [
            'id' => $msgNum,
            'subject' => isset($header->subject) ? decodeMimeHeaderValue($header->subject) : '(Kein Betreff)',
            'from' => isset($header->from[0]) ? $header->from[0]->mailbox . '@' . $header->from[0]->host : 'Unbekannt',
            'from_name' => isset($header->from[0]) && isset($header->from[0]->personal) ? decodeMimeHeaderValue($header->from[0]->personal) : '',
            'date' => $emailDateFormatted,
            'date_timestamp' => $emailDate, // Für Datenbank
            'unread' => true,
            'size' => isset($header->Size) ? $header->Size : 0,
            'body' => $body,
            'cc' => $ccEmails,
            'attachments' => $attachments
        ];
        
        // Vorschau-Text abrufen
        if ($body) {
            // HTML-Tags entfernen und auf 200 Zeichen begrenzen
            $preview = strip_tags($body);
            $preview = mb_substr($preview, 0, 200);
            $email['preview'] = $preview;
        }
        
        $emails[] = $email;
        
        // Als gelesen markieren
        imap_setflag_full($mailbox, $msgNum, "\\Seen");
    }
    
    imap_close($mailbox);
    
    return $emails;
}

/**
 * Ruft E-Mails über POP3 ab
 */
function fetchEmailsViaPOP3($settings, $limit) {
    $emails = [];
    
    // POP3-Verbindungsstring erstellen
    $host = $settings['host'];
    $port = $settings['port'];
    $secure = $settings['secure'];
    
    // POP3-Verbindungsstring
    if ($secure === 'ssl') {
        $connectionString = "{{$host}:{$port}/pop3/ssl}";
    } elseif ($secure === 'tls') {
        $connectionString = "{{$host}:{$port}/pop3/tls}";
    } else {
        $connectionString = "{{$host}:{$port}/pop3/notls}";
    }
    
    // Verbindung herstellen (POP3 unterstützt kein UNSEEN-Flag, daher alle Mails prüfen)
    $mailbox = imap_open($connectionString, $settings['username'], $settings['password']);
    
    if (!$mailbox) {
        $errors = imap_errors();
        $errorMsg = $errors ? implode(', ', $errors) : 'Unbekannter Fehler';
        throw new Exception("POP3-Verbindung fehlgeschlagen: " . $errorMsg);
    }
    
    // Anzahl der E-Mails abrufen
    $numMessages = imap_num_msg($mailbox);
    
    if ($numMessages === false) {
        imap_close($mailbox);
        throw new Exception("Fehler beim Abrufen der E-Mail-Anzahl");
    }
    
    // Neueste E-Mails abrufen (von hinten nach vorne) - nur ungelesene
    $start = max(1, $numMessages - $limit + 1);
    $end = $numMessages;
    
    for ($i = $end; $i >= $start && $i > 0; $i--) {
        $header = imap_headerinfo($mailbox, $i);
        
        if (!$header) {
            continue;
        }
        
        // Nur ungelesene Mails verarbeiten
        if ($header->Unseen != 'U') {
            continue;
        }
        
        // Vollständige E-Mail-Daten abrufen
        $structure = imap_fetchstructure($mailbox, $i);
        $body = extractBody($mailbox, $i, $structure);
        
        // CC-Empfänger extrahieren (inkl. Einzel-Cc als Objekt, nicht Array)
        $ccEmails = imapAddressListToEmailStrings($header->cc ?? null);
        
        // Anhänge extrahieren
        $attachments = extractAttachments($mailbox, $i, $structure);
        
        // Bilder direkt aus der Struktur extrahieren und zu Anhängen hinzufügen
        $images = extractImagesFromStructure($mailbox, $i, $structure);
        if (!empty($images)) {
            // Bilder zu Anhängen hinzufügen (wenn noch nicht vorhanden)
            foreach ($images as $image) {
                $found = false;
                foreach ($attachments as $attachment) {
                    if (isset($attachment['filename']) && $attachment['filename'] === $image['filename']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $attachments[] = $image;
                }
            }
        }
        
        // E-Mail-Datum extrahieren (als Timestamp für Datenbank)
        $emailDate = null;
        $emailDateFormatted = '';
        if (isset($header->date)) {
            $emailTimestamp = strtotime($header->date);
            if ($emailTimestamp !== false) {
                $emailDate = date('Y-m-d H:i:s', $emailTimestamp);
                $emailDateFormatted = date('d.m.Y H:i', $emailTimestamp);
            }
        }
        
        $email = [
            'id' => $i,
            'subject' => isset($header->subject) ? decodeMimeHeaderValue($header->subject) : '(Kein Betreff)',
            'from' => isset($header->from[0]) ? $header->from[0]->mailbox . '@' . $header->from[0]->host : 'Unbekannt',
            'from_name' => isset($header->from[0]) && isset($header->from[0]->personal) ? decodeMimeHeaderValue($header->from[0]->personal) : '',
            'date' => $emailDateFormatted,
            'date_timestamp' => $emailDate, // Für Datenbank
            'unread' => true,
            'size' => isset($header->Size) ? $header->Size : 0,
            'body' => $body,
            'cc' => $ccEmails,
            'attachments' => $attachments
        ];
        
        // Vorschau-Text abrufen
        if ($body) {
            // HTML-Tags entfernen und auf 200 Zeichen begrenzen
            $preview = strip_tags($body);
            $preview = mb_substr($preview, 0, 200);
            $email['preview'] = $preview;
        }
        
        $emails[] = $email;
        
        // Als gelesen markieren (POP3 unterstützt dies möglicherweise nicht)
        @imap_setflag_full($mailbox, $i, "\\Seen");
    }
    
    imap_close($mailbox);
    
    return $emails;
}

/**
 * Extrahiert den Text-Body aus einer E-Mail
 */
function extractBody($mailbox, $msgNum, $structure, $partPrefix = '') {
    $body = '';
    $htmlBodyWithMarkers = '';
    $inlineImageNameMap = buildInlineImageNameMap($structure);
    
    // Wenn keine Parts vorhanden, direkt den Body abrufen
    if (!isset($structure->parts) || !is_array($structure->parts)) {
        $partNum = $partPrefix ?: '1';
        $body = imap_fetchbody($mailbox, $msgNum, $partNum);
        // Dekodieren
        if (isset($structure->encoding)) {
            $body = decodeBody($body, $structure->encoding);
        }
        return $body;
    }
    
    // Mehrteilige E-Mail - Text-Teil finden
    foreach ($structure->parts as $partNum => $part) {
        $currentPartNum = $partPrefix ? ($partPrefix . '.' . ($partNum + 1)) : ($partNum + 1);
        
        $mimeType = getMimeType($part->type, $part->subtype);
        
        // Text-Teil bevorzugen
        if ($mimeType === 'text/plain' && empty($body)) {
            $partBody = imap_fetchbody($mailbox, $msgNum, $currentPartNum);
            if (isset($part->encoding)) {
                $partBody = decodeBody($partBody, $part->encoding);
            }
            $body = $partBody;
        } elseif ($mimeType === 'text/html') {
            // HTML immer auswerten, damit Bild-Positionen als Marker verfügbar sind
            $partBody = imap_fetchbody($mailbox, $msgNum, $currentPartNum);
            if (isset($part->encoding)) {
                $partBody = decodeBody($partBody, $part->encoding);
            }
            $htmlCandidate = htmlToTextWithImageMarkers($partBody, $inlineImageNameMap);
            if (!empty($htmlCandidate) && empty($htmlBodyWithMarkers)) {
                $htmlBodyWithMarkers = $htmlCandidate;
            }
            // Falls kein Plain-Text vorhanden ist, HTML direkt verwenden
            if (empty($body) && !empty($htmlCandidate)) {
                $body = $htmlCandidate;
            }
        } elseif (isset($part->parts)) {
            // Rekursiv für verschachtelte Teile
            $nestedBody = extractBody($mailbox, $msgNum, $part, $currentPartNum);
            if (!empty($nestedBody) && empty($body)) {
                $body = $nestedBody;
            }
        }
    }

    // Wenn HTML Marker enthält, dieses Ergebnis bevorzugen (Marker bleiben an der richtigen Position)
    if (!empty($htmlBodyWithMarkers) && strpos($htmlBodyWithMarkers, '[Bild ') !== false) {
        $body = $htmlBodyWithMarkers;
    }

    return $body;
}

/**
 * Konvertiert HTML in Text und setzt Marker an die ursprünglichen Bild-Positionen.
 */
function htmlToTextWithImageMarkers($html, $inlineImageNameMap = []) {
    if (!is_string($html) || $html === '') {
        return '';
    }

    $imageIndex = 0;
    $html = preg_replace_callback('/<img\b[^>]*>/i', function ($matches) use (&$imageIndex) {
        $imageIndex++;
        $tag = $matches[0];

        $label = '';
        if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $srcMatch)) {
            $src = trim((string)$srcMatch[1]);
            if (stripos($src, 'cid:') === 0) {
                $cid = trim((string)substr($src, 4), "<> \t\n\r\0\x0B");
                $cidKey = strtolower($cid);
                if ($cidKey !== '' && isset($inlineImageNameMap[$cidKey]) && $inlineImageNameMap[$cidKey] !== '') {
                    $label = $inlineImageNameMap[$cidKey];
                } else {
                    $label = $cid;
                }
            } else {
                $label = $src;
            }
        }

        if ($label === '' && preg_match('/\balt\s*=\s*["\']([^"\']+)["\']/i', $tag, $altMatch)) {
            $label = trim((string)$altMatch[1]);
        }

        $marker = '[Bild ' . $imageIndex;
        if ($label !== '') {
            $marker .= ': ' . $label;
        }
        $marker .= ']';

        return "\n" . $marker . "\n";
    }, $html);

    // Für bessere Lesbarkeit Block-/Zeilenumbrüche beibehalten
    $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<\/\s*(p|div|li|tr|h[1-6])\s*>/i', "\n", $html);

    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text); // geschütztes Leerzeichen
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}

/**
 * Baut eine Zuordnung von Content-ID (cid) auf Dateinamen.
 */
function buildInlineImageNameMap($structure, $nameMap = []) {
    if (!is_object($structure) || !isset($structure->parts) || !is_array($structure->parts)) {
        return $nameMap;
    }

    foreach ($structure->parts as $part) {
        if (is_object($part) && isset($part->type, $part->subtype)) {
            $mimeType = getMimeType($part->type, $part->subtype);
            if (strpos($mimeType, 'image/') === 0 && isset($part->id)) {
                $cid = trim((string)$part->id, "<> \t\n\r\0\x0B");
                if ($cid !== '') {
                    $filename = '';
                    if (isset($part->dparameters) && is_array($part->dparameters)) {
                        foreach ($part->dparameters as $param) {
                            $attr = strtolower((string)($param->attribute ?? ''));
                            if ($attr === 'filename' || $attr === 'filename*') {
                                $filename = trim((string)($param->value ?? ''));
                                break;
                            }
                        }
                    }
                    if ($filename === '' && isset($part->parameters) && is_array($part->parameters)) {
                        foreach ($part->parameters as $param) {
                            $attr = strtolower((string)($param->attribute ?? ''));
                            if ($attr === 'name' || $attr === 'name*') {
                                $filename = trim((string)($param->value ?? ''));
                                break;
                            }
                        }
                    }
                    if ($filename !== '') {
                        $nameMap[strtolower($cid)] = $filename;
                    }
                }
            }
        }

        if (is_object($part) && isset($part->parts) && is_array($part->parts)) {
            $nameMap = buildInlineImageNameMap($part, $nameMap);
        }
    }

    return $nameMap;
}

/**
 * Extrahiert alle Bilder aus einer E-Mail-Struktur
 */
function extractImagesFromStructure($mailbox, $msgNum, $structure, $partPrefix = '') {
    $images = [];
    
    // Wenn keine Parts vorhanden, prüfe ob die Struktur selbst ein Bild ist
    if (!isset($structure->parts) || !is_array($structure->parts)) {
        $mimeType = getMimeType($structure->type, $structure->subtype);
        if (strpos($mimeType, 'image/') === 0) {
            try {
                $partNum = $partPrefix ?: '1';
                $imageData = imap_fetchbody($mailbox, $msgNum, $partNum);
                
                if ($imageData !== false && !empty($imageData)) {
                    if (isset($structure->encoding)) {
                        // Binäre Bilder: keine UTF-8-Konvertierung!
                        $imageData = decodeBody($imageData, $structure->encoding, true);
                    }
                    
                    $contentId = isset($structure->id) ? trim($structure->id, '<>') : '';
                    $filename = !empty($contentId) ? 'image_' . $contentId : 'image_1';
                    
                    $extension = '';
                    switch ($mimeType) {
                        case 'image/jpeg':
                        case 'image/jpg':
                            $extension = 'jpg';
                            break;
                        case 'image/png':
                            $extension = 'png';
                            break;
                        case 'image/gif':
                            $extension = 'gif';
                            break;
                        case 'image/webp':
                            $extension = 'webp';
                            break;
                        default:
                            $extension = 'img';
                    }
                    $filename .= '.' . $extension;
                    
                    $images[] = [
                        'filename' => $filename,
                        'data' => $imageData,
                        'size' => strlen($imageData),
                        'mime_type' => $mimeType
                    ];
                }
            } catch (Exception $e) {
                error_log("Fehler beim Extrahieren des Bildes ohne Parts: " . $e->getMessage());
            }
        }
        return $images;
    }
    
    // Durch alle Parts iterieren
    foreach ($structure->parts as $partNum => $part) {
        $currentPartNum = $partPrefix ? ($partPrefix . '.' . ($partNum + 1)) : ($partNum + 1);
        $mimeType = getMimeType($part->type, $part->subtype);
        
        // Rekursiv für verschachtelte Teile
        if (isset($part->parts) && is_array($part->parts)) {
            $nestedImages = extractImagesFromStructure($mailbox, $msgNum, $part, $currentPartNum);
            $images = array_merge($images, $nestedImages);
        }
        
        // Prüfen ob es ein Bild ist
        if (strpos($mimeType, 'image/') === 0) {
            try {
                $imageData = imap_fetchbody($mailbox, $msgNum, $currentPartNum);
                
                if ($imageData !== false && !empty($imageData)) {
                    if (isset($part->encoding)) {
                        // Binäre Bilder: keine UTF-8-Konvertierung!
                        $imageData = decodeBody($imageData, $part->encoding, true);
                    }
                    
                    $filename = '';
                    $contentId = isset($part->id) ? trim($part->id, '<>') : '';
                    
                    // Versuche Dateiname zu finden
                    if (isset($part->dparameters)) {
                        foreach ($part->dparameters as $param) {
                            if (strtolower($param->attribute) === 'filename' || strtolower($param->attribute) === 'filename*') {
                                $filename = $param->value;
                                // MIME-encoded Dateinamen dekodieren
                                if (strpos($filename, "''") !== false || strpos($filename, "=?") !== false) {
                                    $decoded = imap_mime_header_decode($filename);
                                    if ($decoded && is_array($decoded)) {
                                        $filename = '';
                                        foreach ($decoded as $d) {
                                            $filename .= $d->text;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }
                    
                    if (empty($filename)) {
                        $extension = '';
                        switch ($mimeType) {
                            case 'image/jpeg':
                            case 'image/jpg':
                                $extension = 'jpg';
                                break;
                            case 'image/png':
                                $extension = 'png';
                                break;
                            case 'image/gif':
                                $extension = 'gif';
                                break;
                            case 'image/webp':
                                $extension = 'webp';
                                break;
                            default:
                                $extension = 'img';
                        }
                        $filename = !empty($contentId) ? 'image_' . $contentId . '.' . $extension : 'image_' . $currentPartNum . '.' . $extension;
                    }
                    
                    $images[] = [
                        'filename' => $filename,
                        'data' => $imageData,
                        'size' => strlen($imageData),
                        'mime_type' => $mimeType
                    ];
                    
                    error_log("Bild extrahiert: " . $filename . " (" . $mimeType . ", " . strlen($imageData) . " Bytes)");
                }
            } catch (Exception $e) {
                error_log("Fehler beim Extrahieren des Bildes " . $currentPartNum . ": " . $e->getMessage());
            }
        }
    }
    
    return $images;
}

/**
 * Bettet Bilder als Base64-kodierte Daten-URIs in die Beschreibung ein
 * Begrenzt die Gesamtlänge auf max. 60.000 Zeichen (TEXT-Spalte hat max. ~65.535 Bytes)
 */
function embedImagesInDescription($body, $attachments) {
    if (empty($attachments) || !is_array($attachments)) {
        return $body;
    }
    
    $imageAttachments = [];
    foreach ($attachments as $attachment) {
        $mimeType = $attachment['mime_type'] ?? '';
        // Nur Bilder einbetten (keine PDFs oder andere Dateien)
        if (strpos($mimeType, 'image/') === 0) {
            $imageAttachments[] = $attachment;
        }
    }
    
    if (empty($imageAttachments)) {
        return $body;
    }
    
    // Maximale Länge für TEXT-Spalte: ~65.535 Bytes, wir nehmen 60.000 Zeichen als Sicherheit
    $maxLength = 60000;
    $currentLength = mb_strlen($body, 'UTF-8');
    $availableLength = $maxLength - $currentLength;
    
    // Wenn der Body bereits zu lang ist, keine Bilder hinzufügen
    if ($availableLength < 100) {
        error_log("Beschreibung bereits zu lang (" . $currentLength . " Zeichen), keine Bilder eingebettet");
        return $body;
    }
    
    // Bilder am Ende der Beschreibung hinzufügen
    $body = trim($body);
    if (!empty($body)) {
        $body .= "\n\n";
    }
    $body .= "--- Bilder aus E-Mail ---\n\n";
    
    $imagesAdded = 0;
    foreach ($imageAttachments as $index => $image) {
        $filename = $image['filename'] ?? 'Bild';
        $data = $image['data'] ?? '';
        $mimeType = $image['mime_type'] ?? 'image/jpeg';
        
        if (!empty($data)) {
            // Prüfe ob Bild zu groß ist (max. 500 KB Base64 = ~375 KB Original)
            $dataSize = strlen($data);
            $base64Size = strlen(base64_encode($data));
            
            // Berechne wie viel Platz noch verfügbar ist
            $currentLength = mb_strlen($body, 'UTF-8');
            $availableLength = $maxLength - $currentLength;
            
            // Header-Text für dieses Bild
            $imageHeader = "Bild " . ($index + 1) . ": " . $filename . " (" . round($dataSize / 1024, 2) . " KB)\n";
            $headerLength = mb_strlen($imageHeader, 'UTF-8');
            
            // Wenn nicht genug Platz für Header + Base64, überspringe dieses Bild
            if ($availableLength < $headerLength + 1000) {
                error_log("Nicht genug Platz für Bild " . ($index + 1) . " (" . $filename . ")");
                continue;
            }
            
            // Max. 500 KB Base64 (ca. 375 KB Original)
            if ($base64Size < 500000 && $dataSize < 375000) {
                $base64 = base64_encode($data);
                $dataUri = 'data:' . $mimeType . ';base64,' . $base64;
                
                // Prüfe ob die Daten-URI in den verfügbaren Platz passt
                $dataUriLength = mb_strlen($dataUri, 'UTF-8');
                if ($currentLength + $headerLength + $dataUriLength + 2 <= $maxLength) {
                    $body .= $imageHeader;
                    $body .= $dataUri . "\n\n";
                    $imagesAdded++;
                } else {
                    // Zu groß - nur Referenz
                    $body .= $imageHeader;
                    $body .= "[Bild zu groß zum Einbetten - als Anhang gespeichert]\n\n";
                    $imagesAdded++;
                }
            } else {
                // Zu groß - nur Referenz
                $body .= $imageHeader;
                $body .= "[Bild zu groß zum Einbetten - als Anhang gespeichert]\n\n";
                $imagesAdded++;
            }
            
            // Prüfe ob wir noch Platz haben für weitere Bilder
            $currentLength = mb_strlen($body, 'UTF-8');
            if ($currentLength >= $maxLength - 200) {
                error_log("Maximale Beschreibungslänge erreicht, " . ($imagesAdded) . " von " . count($imageAttachments) . " Bildern eingebettet");
                break;
            }
        }
    }
    
    // Finale Längenprüfung und Kürzung falls nötig
    $finalLength = mb_strlen($body, 'UTF-8');
    if ($finalLength > $maxLength) {
        error_log("Beschreibung zu lang (" . $finalLength . " Zeichen), wird auf " . $maxLength . " Zeichen gekürzt");
        $body = mb_substr($body, 0, $maxLength - 10, 'UTF-8');
        $body .= "\n[...]";
    }
    
    return $body;
}

/**
 * Dekodiert E-Mail-Body basierend auf Encoding
 * @param string $body Der zu dekodierende Body
 * @param int $encoding Das Encoding (0=7BIT, 1=8BIT, 2=BINARY, 3=BASE64, 4=QUOTED-PRINTABLE)
 * @param bool $isBinary Wenn true, wird keine UTF-8-Konvertierung durchgeführt (für binäre Anhänge)
 */
function decodeBody($body, $encoding, $isBinary = false) {
    $decoded = '';
    
    switch ($encoding) {
        case 3: // BASE64
            // Whitespace entfernen (kann in E-Mails vorkommen)
            $body = preg_replace('/\s+/', '', $body);
            $decoded = base64_decode($body, true); // strict mode
            if ($decoded === false) {
                // Fallback: ohne strict mode
                $decoded = base64_decode($body);
            }
            break;
        case 4: // QUOTED-PRINTABLE
            $decoded = quoted_printable_decode($body);
            break;
        case 1: // 8BIT
        case 2: // BINARY
        default:
            $decoded = $body;
            break;
    }
    
    // Nur für Text-Inhalte UTF-8-Konvertierung durchführen (nicht für binäre Anhänge!)
    if (!$isBinary) {
        // Konvertiere zu UTF-8, falls nötig
        if (!mb_check_encoding($decoded, 'UTF-8')) {
            // Versuche Encoding zu erkennen
            $detected = @mb_detect_encoding($decoded, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
            if ($detected && $detected !== 'UTF-8') {
                $decoded = @mb_convert_encoding($decoded, 'UTF-8', $detected);
            } else {
                // Fallback: Versuche mit auto
                $decoded = @mb_convert_encoding($decoded, 'UTF-8', 'auto');
            }
        }
    }
    
    return $decoded;
}

/**
 * Dekodiert MIME-Header-Werte (z. B. Subject/From-Name) nach UTF-8.
 */
function decodeMimeHeaderValue($value) {
    $value = (string)$value;
    if ($value === '') {
        return '';
    }

    if (function_exists('imap_mime_header_decode')) {
        $decodedParts = @imap_mime_header_decode($value);
        if (is_array($decodedParts) && !empty($decodedParts)) {
            $result = '';
            foreach ($decodedParts as $part) {
                $text = isset($part->text) ? (string)$part->text : '';
                $charset = isset($part->charset) ? strtoupper((string)$part->charset) : 'DEFAULT';
                if ($text === '') {
                    continue;
                }

                if ($charset !== 'DEFAULT' && $charset !== 'UTF-8') {
                    $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
                    $text = $converted !== false ? $converted : $text;
                } elseif (!mb_check_encoding($text, 'UTF-8')) {
                    $text = @mb_convert_encoding($text, 'UTF-8', 'auto');
                }
                $result .= $text;
            }

            if ($result !== '') {
                return $result;
            }
        }
    }

    // Fallback, falls kein MIME-Header erkannt wurde.
    if (!mb_check_encoding($value, 'UTF-8')) {
        $detected = @mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($detected && $detected !== 'UTF-8') {
            $converted = @mb_convert_encoding($value, 'UTF-8', $detected);
            if ($converted !== false) {
                return $converted;
            }
        }
        $autoConverted = @mb_convert_encoding($value, 'UTF-8', 'auto');
        if ($autoConverted !== false) {
            return $autoConverted;
        }
    }

    return $value;
}

/**
 * Extrahiert Anhänge aus einer E-Mail
 */
function extractAttachments($mailbox, $msgNum, $structure, $partPrefix = '') {
    $attachments = [];
    
    // Wenn keine Parts vorhanden, prüfe ob die Struktur selbst ein Anhang ist
    if (!isset($structure->parts) || !is_array($structure->parts)) {
        $mimeType = getMimeType($structure->type, $structure->subtype);
        // Wenn es ein Bild oder anderer Binär-Typ ist, behandle es als Anhang
        if (strpos($mimeType, 'image/') === 0 || 
            ($structure->type != 0 && $structure->type != 1 && 
             $mimeType !== 'text/plain' && $mimeType !== 'text/html')) {
            try {
                $partNum = $partPrefix ?: '1';
                $attachmentData = imap_fetchbody($mailbox, $msgNum, $partNum);
                
                if ($attachmentData !== false && !empty($attachmentData)) {
                    if (isset($structure->encoding)) {
                        // Binäre Anhänge: keine UTF-8-Konvertierung!
                        $attachmentData = decodeBody($attachmentData, $structure->encoding, true);
                    }
                    
                    $filename = 'attachment_1';
                    if (strpos($mimeType, 'image/') === 0) {
                        $extension = '';
                        switch ($mimeType) {
                            case 'image/jpeg':
                            case 'image/jpg':
                                $extension = 'jpg';
                                break;
                            case 'image/png':
                                $extension = 'png';
                                break;
                            case 'image/gif':
                                $extension = 'gif';
                                break;
                            default:
                                $extension = 'img';
                        }
                        $filename = 'image_1.' . $extension;
                    }
                    
                    $attachments[] = [
                        'filename' => cleanStringForDatabase($filename),
                        'data' => $attachmentData,
                        'size' => strlen($attachmentData),
                        'mime_type' => $mimeType
                    ];
                }
            } catch (Exception $e) {
                error_log("Fehler beim Extrahieren des Anhangs ohne Parts: " . $e->getMessage());
            }
        }
        return $attachments;
    }
    
    foreach ($structure->parts as $partNum => $part) {
        $currentPartNum = $partPrefix ? ($partPrefix . '.' . ($partNum + 1)) : ($partNum + 1);
        
        // Prüfen ob es ein Anhang ist
        $isAttachment = false;
        $filename = '';
        $disposition = '';
        
        // Content-Disposition prüfen
        if (isset($part->disposition)) {
            $disposition = strtolower($part->disposition);
            if ($disposition === 'attachment' || $disposition === 'inline') {
                $isAttachment = true;
            }
        }
        
        // Dateiname aus dparameters (Content-Disposition)
        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                $attr = strtolower($param->attribute);
                if ($attr === 'filename' || $attr === 'filename*') {
                    $filename = $param->value;
                    $isAttachment = true;
                    // MIME-encoded Dateinamen dekodieren
                    if (strpos($filename, "''") !== false || strpos($filename, "=?") !== false) {
                        $decoded = imap_mime_header_decode($filename);
                        if ($decoded && is_array($decoded)) {
                            $filename = '';
                            foreach ($decoded as $d) {
                                $filename .= $d->text;
                            }
                        }
                    }
                    break;
                }
            }
        }
        
        // Dateiname aus parameters (Content-Type)
        if (empty($filename) && isset($part->parameters)) {
            foreach ($part->parameters as $param) {
                $attr = strtolower($param->attribute);
                if ($attr === 'name' || $attr === 'name*') {
                    $filename = $param->value;
                    $isAttachment = true;
                    // MIME-encoded Dateinamen dekodieren
                    if (strpos($filename, "''") !== false || strpos($filename, "=?") !== false) {
                        $decoded = imap_mime_header_decode($filename);
                        if ($decoded && is_array($decoded)) {
                            $filename = '';
                            foreach ($decoded as $d) {
                                $filename .= $d->text;
                            }
                        }
                    }
                    break;
                }
            }
        }
        
        // MIME-Typ bestimmen
        $mimeType = getMimeType($part->type, $part->subtype);
        
        // Prüfen ob es ein Bild oder anderer Binär-Anhang ist
        // WICHTIG: Auch wenn es keine explizite Disposition gibt, können Bilder Anhänge sein
        if (!$isAttachment) {
            // Bilder IMMER als Anhang behandeln (auch wenn inline oder ohne Disposition)
            if (strpos($mimeType, 'image/') === 0) {
                $isAttachment = true;
                // Content-ID für Bilder prüfen
                $contentId = '';
                if (isset($part->id)) {
                    $contentId = trim($part->id, '<>');
                }
                
                // Versuche Dateiname zu generieren basierend auf MIME-Typ oder Content-ID
                if (empty($filename)) {
                    $extension = '';
                    switch ($mimeType) {
                        case 'image/jpeg':
                        case 'image/jpg':
                            $extension = 'jpg';
                            break;
                        case 'image/png':
                            $extension = 'png';
                            break;
                        case 'image/gif':
                            $extension = 'gif';
                            break;
                        case 'image/webp':
                            $extension = 'webp';
                            break;
                        case 'image/bmp':
                            $extension = 'bmp';
                            break;
                        case 'image/svg+xml':
                            $extension = 'svg';
                            break;
                        default:
                            $extension = 'img';
                    }
                    
                    if (!empty($contentId)) {
                        $filename = 'image_' . $contentId . '.' . $extension;
                    } else {
                        $filename = 'image_' . $currentPartNum . '.' . $extension;
                    }
                }
            }
            // Andere Binär-Anhänge (nicht text/plain oder text/html im Hauptteil)
            elseif ($mimeType !== 'text/plain' && $mimeType !== 'text/html' && 
                $mimeType !== 'multipart/alternative' && $mimeType !== 'multipart/related' &&
                $mimeType !== 'multipart/mixed' && $part->type != 1) {
                // Versuche Dateiname zu generieren basierend auf MIME-Typ
                if (empty($filename)) {
                    $extension = '';
                    switch ($mimeType) {
                        case 'application/pdf':
                            $extension = 'pdf';
                            break;
                        case 'application/zip':
                            $extension = 'zip';
                            break;
                        case 'application/x-zip-compressed':
                            $extension = 'zip';
                            break;
                        case 'application/msword':
                            $extension = 'doc';
                            break;
                        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                            $extension = 'docx';
                            break;
                        case 'application/vnd.ms-excel':
                            $extension = 'xls';
                            break;
                        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                            $extension = 'xlsx';
                            break;
                        default:
                            $extension = 'bin';
                    }
                    $filename = 'attachment_' . $currentPartNum . '.' . $extension;
                }
                $isAttachment = true;
            }
        }
        
        // Rekursiv für verschachtelte Teile ZUERST prüfen (wichtig für multipart/related mit Bildern)
        if (isset($part->parts) && is_array($part->parts)) {
            $nestedAttachments = extractAttachments($mailbox, $msgNum, $part, $currentPartNum);
            $attachments = array_merge($attachments, $nestedAttachments);
        }
        
        // Dann prüfen ob dieser Teil selbst ein Anhang ist
        // WICHTIG: Auch wenn es verschachtelte Parts gibt, kann dieser Teil selbst ein Anhang sein
        if ($isAttachment && !empty($filename)) {
            try {
                // Anhang-Daten abrufen
                $attachmentData = imap_fetchbody($mailbox, $msgNum, $currentPartNum);
                
                if ($attachmentData === false) {
                    error_log("Fehler beim Abrufen des Anhangs: " . $filename . " (Part: " . $currentPartNum . ")");
                    continue;
                }
                
                // Dekodieren (binäre Anhänge: keine UTF-8-Konvertierung!)
                if (isset($part->encoding)) {
                    $attachmentData = decodeBody($attachmentData, $part->encoding, true);
                }
                
                // Prüfen ob Daten vorhanden sind
                if (empty($attachmentData)) {
                    error_log("Anhang hat keine Daten: " . $filename);
                    continue;
                }
                
                // Dateiname für Datenbank bereinigen
                $cleanFilename = cleanStringForDatabase($filename);
                if (empty($cleanFilename)) {
                    $cleanFilename = 'attachment_' . $currentPartNum;
                }
                
                $attachments[] = [
                    'filename' => $cleanFilename,
                    'data' => $attachmentData,
                    'size' => strlen($attachmentData),
                    'mime_type' => $mimeType
                ];
                
                error_log("Anhang erfolgreich extrahiert: " . $cleanFilename . " (" . $mimeType . ", " . strlen($attachmentData) . " Bytes)");
            } catch (Exception $e) {
                error_log("Fehler beim Extrahieren des Anhangs " . $filename . ": " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                continue;
            }
        }
    }
    
    return $attachments;
}

/**
 * Ermittelt den MIME-Typ
 */
function getMimeType($type, $subtype) {
    $types = [
        0 => 'text',
        1 => 'multipart',
        2 => 'message',
        3 => 'application',
        4 => 'audio',
        5 => 'image',
        6 => 'video',
        7 => 'other'
    ];
    
    $typeName = isset($types[$type]) ? $types[$type] : 'application';
    return $typeName . '/' . strtolower($subtype);
}

/**
 * Normalisiert eine Domain für Vergleiche.
 */
function normalizeDomainValue($domain) {
    $domain = trim((string)$domain);
    $domain = strtolower($domain);
    $domain = trim($domain, " \t\n\r\0\x0B<>\"'()[]");
    $domain = preg_replace('/^mailto:/', '', $domain);
    if (strpos($domain, '@') !== false) {
        $parts = explode('@', $domain);
        $domain = end($parts);
    }
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = preg_replace('/:\d+$/', '', $domain);
    $domain = preg_replace('/^www\./', '', $domain);
    $domain = preg_replace('/^@+/', '', $domain);
    $domain = preg_replace('/\.+$/', '', $domain);
    $domain = preg_replace('/[^a-z0-9.\-]/', '', $domain);
    return $domain;
}

/**
 * Extrahiert und normalisiert die Domain aus einer E-Mail-Adresse.
 */
function getDomainFromEmailAddress($email) {
    $email = trim((string)$email);
    if ($email === '' || strpos($email, '@') === false) {
        return '';
    }

    $parts = explode('@', $email);
    $domain = end($parts);
    return normalizeDomainValue($domain);
}

/**
 * Prüft, ob eine gespeicherte Domain (auch mit Trennzeichen) zur gesuchten Domain passt.
 */
function domainMatches($storedDomain, $wantedDomain) {
    $storedDomain = trim((string)$storedDomain);
    $wantedDomain = normalizeDomainValue($wantedDomain);
    if ($storedDomain === '' || $wantedDomain === '') {
        return false;
    }

    $candidates = preg_split('/[\s,;|]+/', $storedDomain);
    if (!is_array($candidates) || empty($candidates)) {
        $candidates = [$storedDomain];
    }

    foreach ($candidates as $candidate) {
        $normalizedCandidate = normalizeDomainValue($candidate);
        if ($normalizedCandidate === '') {
            continue;
        }
        if ($normalizedCandidate === $wantedDomain) {
            return true;
        }
        // Subdomain-Fallback: "mail.metawell.com" passt zu "metawell.com" und umgekehrt.
        if (str_ends_with($normalizedCandidate, '.' . $wantedDomain) || str_ends_with($wantedDomain, '.' . $normalizedCandidate)) {
            return true;
        }
    }

    return false;
}

/**
 * Lädt die Verschlüsselungs-Helper genau einmal.
 */
function ensureEncryptionHelpersLoaded() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    // Wichtig: erst nach config.php aufrufen, damit ENCRYPTION_KEY/DB_PASS verfügbar ist.
    require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';
    // Wenn der Helper innerhalb einer Funktion geladen wird, landen $key/$keyFallback
    // im lokalen Funktions-Scope. decrypt_from_db() erwartet sie jedoch global.
    if (!array_key_exists('key', $GLOBALS)) {
        if (!defined('ENCRYPTION_KEY') || ENCRYPTION_KEY === '') {
            $GLOBALS['key'] = defined('DB_PASS') ? hash('sha256', DB_PASS . 'todos_folders_enc_v1', true) : null;
        } else {
            $GLOBALS['key'] = is_string(ENCRYPTION_KEY) ? hash('sha256', ENCRYPTION_KEY, true) : ENCRYPTION_KEY;
        }
    }
    if (!array_key_exists('keyFallback', $GLOBALS)) {
        $GLOBALS['keyFallback'] = defined('DB_PASS') ? hash('sha256', DB_PASS . 'todos_folders_enc_v1', true) : null;
    }
    $loaded = true;
}

/**
 * Schreibt Debug-Informationen für den E-Mail-Import in eine eigene Logdatei.
 */
function emailImportDebugLog($message) {
    $logFile = dirname(__DIR__, 2) . '/logs/email-import.log';
    $line = date('Y-m-d H:i:s') . ' - ' . (string)$message . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

/**
 * Findet eine Firma zur Domain. Berücksichtigt auch verschlüsselt gespeicherte Domains.
 */
function findCompanyByDomain($domain) {
    global $pdo;
    ensureEncryptionHelpersLoaded();

    $normalizedDomain = normalizeDomainValue($domain);
    if ($normalizedDomain === '') {
        emailImportDebugLog('findCompanyByDomain: Leere normalisierte Domain. Input="' . (string)$domain . '"');
        return null;
    }

    // Schnellpfad: direkter Treffer (funktioniert bei Klartext-Domains)
    $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE domain = ? LIMIT 1");
    $stmt->execute([$normalizedDomain]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        return $company;
    }

    // Fallback: Domains entschlüsseln und in PHP vergleichen
    $scanStmt = $pdo->query("SELECT id, name, domain FROM companies WHERE domain IS NOT NULL AND domain <> ''");
    $companies = $scanStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($companies as $row) {
        $decryptedDomain = decrypt_from_db($row['domain']);
        if (domainMatches($decryptedDomain, $normalizedDomain)) {
            emailImportDebugLog('findCompanyByDomain: Treffer company_id=' . $row['id'] . ' domain="' . $normalizedDomain . '"');
            return [
                'id' => $row['id'],
                'name' => decrypt_from_db($row['name'])
            ];
        }
    }

    emailImportDebugLog('findCompanyByDomain: Kein Treffer fuer domain="' . $normalizedDomain . '"');
    return null;
}

/**
 * Wandelt IMAP/POP3-Adresslisten (From/Cc/To) in normierte E-Mail-Strings um.
 * Wichtig: Bei genau einem Cc-Empfänger liefert imap_headerinfo oft ein einzelnes Objekt statt eines Arrays.
 *
 * @param mixed $addresses Wert von $header->cc (oder ähnlich)
 * @return string[] Liste mailbox@host
 */
function imapAddressListToEmailStrings($addresses) {
    $out = [];
    if ($addresses === null || $addresses === '') {
        return $out;
    }
    if (is_object($addresses) && isset($addresses->mailbox, $addresses->host)) {
        $mb = (string)$addresses->mailbox;
        $host = (string)$addresses->host;
        if ($mb !== '' && strcasecmp($host, 'MISSING-HOST-NAME') !== 0) {
            $out[] = $mb . '@' . $host;
        }
        return $out;
    }
    if (!is_array($addresses)) {
        return $out;
    }
    foreach ($addresses as $addr) {
        if (is_object($addr) && isset($addr->mailbox, $addr->host)) {
            $mb = (string)$addr->mailbox;
            $host = (string)$addr->host;
            if ($mb !== '' && strcasecmp($host, 'MISSING-HOST-NAME') !== 0) {
                $out[] = $mb . '@' . $host;
            }
        }
    }
    return $out;
}

/**
 * Normalisiert E-Mail-Adressen für Vergleiche.
 */
function normalizeEmailValue($email) {
    return strtolower(trim((string)$email));
}

/**
 * Findet einen Benutzer per E-Mail. Unterstützt auch verschlüsselte E-Mails in users.email.
 */
function findUserByEmailAddress($email, $fields = ['id']) {
    global $pdo;

    ensureEncryptionHelpersLoaded();

    $normalizedEmail = normalizeEmailValue($email);
    if ($normalizedEmail === '') {
        return null;
    }

    // Nur erlaubte Felder ausgeben
    $allowedFields = ['id', 'vorname', 'nachname', 'customer_id', 'email'];
    $selectedFields = array_values(array_intersect($fields, $allowedFields));
    if (empty($selectedFields)) {
        $selectedFields = ['id'];
    }

    $selectList = implode(', ', $selectedFields);

    // Schnellpfad für Klartext-Einträge
    $stmt = $pdo->prepare("SELECT {$selectList} FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$normalizedEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        return $user;
    }

    // Fallback für verschlüsselte E-Mails
    $scanStmt = $pdo->query("SELECT id, vorname, nachname, customer_id, email FROM users WHERE email IS NOT NULL AND email <> ''");
    $users = $scanStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $row) {
        $decryptedEmail = normalizeEmailValue(decrypt_from_db($row['email']));
        if ($decryptedEmail === $normalizedEmail) {
            $result = [];
            foreach ($selectedFields as $field) {
                if ($field === 'email') {
                    $result[$field] = decrypt_from_db($row['email']);
                } else {
                    $result[$field] = $row[$field] ?? null;
                }
            }
            return $result;
        }
    }

    return null;
}

/**
 * Wandelt eine E-Mail in ein Ticket um
 */
function convertEmailToTicket($email, $settings) {
    global $pdo;
    
    require_once dirname(__DIR__, 2) . '/assets/notifications.php';
    
    // Sicherstellen, dass die Datenbankverbindung UTF-8 verwendet
    try {
        $pdo->exec("SET NAMES 'utf8mb4'");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        $pdo->exec("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");
    } catch (PDOException $e) {
        // Fehler ignorieren, falls bereits gesetzt
        error_log("Fehler beim Setzen der UTF-8 Einstellungen: " . $e->getMessage());
    }
    
    // Domain aus E-Mail-Adresse extrahieren und normalisieren
    $fromEmail = normalizeEmailValue($email['from'] ?? '');
    $domain = getDomainFromEmailAddress($fromEmail);
    
    if (empty($domain)) {
        throw new Exception("Keine Domain in E-Mail-Adresse gefunden");
    }
    
    // Firma anhand der Domain finden (inkl. Fallback für verschlüsselte Domains)
    $company = findCompanyByDomain($domain);
    
    if (!$company) {
        // Mail verwerfen - Firma nicht gefunden
        $reason = 'Firma mit Domain "' . $domain . '" nicht gefunden. Bitte die Domain in den Firmeneinstellungen hinterlegen.';
        logEmailAction('VERWORFEN', $fromEmail, $email['subject'] ?? '(Kein Betreff)', $reason, null, null);
        return [
            'ticket_id' => null,
            'reason' => $reason
        ];
    }
    
    $companyId = $company['id'];
    
    // User anhand der E-Mail-Adresse finden (inkl. customer_id)
    $user = findUserByEmailAddress($fromEmail, ['id', 'vorname', 'nachname', 'customer_id']);
    
    if (!$user) {
        // Mail verwerfen - User nicht gefunden
        $reason = 'Benutzer mit E-Mail-Adresse "' . $fromEmail . '" nicht gefunden. Bitte den Benutzer im System anlegen.';
        logEmailAction('VERWORFEN', $fromEmail, $email['subject'] ?? '(Kein Betreff)', $reason, null, null);
        return [
            'ticket_id' => null,
            'reason' => $reason
        ];
    }
    
    $userId = $user['id'];
    $customerId = isset($user['customer_id']) && !empty($user['customer_id']) ? (int)$user['customer_id'] : null;
    
    // Ticketnummer im Betreff suchen (Format: TKT-YYYYMMDD-XXXX oder ähnlich)
    $subject = $email['subject'] ?? '';
    // Subject für Datenbank bereinigen
    $subject = cleanStringForDatabase($subject);
    $ticketNumber = null;
    $ticketId = null;
    
    if (preg_match('/TKT-[\d]+-[\d]+/', $subject, $matches)) {
        $ticketNumber = $matches[0];
        // Bestehendes Ticket finden
        $stmt = $pdo->prepare("SELECT id FROM tickets WHERE ticket_nummer = ? LIMIT 1");
        $stmt->execute([$ticketNumber]);
        $existingTicket = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existingTicket) {
            $ticketId = $existingTicket['id'];
        }
    }
    
    // E-Mail-Text als Beschreibung verwenden
    $body = $email['body'] ?? '';
    
    // HTML-Tags entfernen, aber Formatierung beibehalten
    $body = strip_tags($body);
    $body = trim($body);
    
    // Finale Längenprüfung (TEXT-Spalte max. ~65.535 Bytes)
    $maxLength = 60000; // Sicherheitspuffer
    if (mb_strlen($body, 'UTF-8') > $maxLength) {
        error_log("Beschreibung zu lang (" . mb_strlen($body, 'UTF-8') . " Zeichen), wird gekürzt");
        $body = mb_substr($body, 0, $maxLength - 10, 'UTF-8');
        $body .= "\n[...]";
    }
    
    // Für Datenbank bereinigen
    $body = cleanStringForDatabase($body);
    
    if ($ticketId) {
        // Bestehendes Ticket aktualisieren - Kommentar hinzufügen
        $comment = "E-Mail von {$fromEmail}:\n\n{$body}";
        
        // Längenprüfung für Kommentar (TEXT-Spalte max. ~65.535 Bytes)
        $maxLength = 60000; // Sicherheitspuffer
        if (mb_strlen($comment, 'UTF-8') > $maxLength) {
            error_log("Kommentar zu lang (" . mb_strlen($comment, 'UTF-8') . " Zeichen), wird gekürzt");
            $comment = mb_substr($comment, 0, $maxLength - 10, 'UTF-8');
            $comment .= "\n[...]";
        }
        
        // Kommentar für Datenbank bereinigen
        $comment = cleanStringForDatabase($comment);
        
        // E-Mail-Datum verwenden (falls verfügbar), sonst aktuelles Datum
        $commentDate = isset($email['date_timestamp']) && !empty($email['date_timestamp']) 
            ? $email['date_timestamp'] 
            : date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("
            INSERT INTO ticket_comments (ticket_id, user_id, kommentar, nachrichtentyp, ist_intern, erstellt_datum)
            VALUES (?, ?, ?, 'nachricht', 0, ?)
        ");
        $stmt->execute([$ticketId, $userId, $comment, $commentDate]);
        
        // Ticket "zuletzt geändert" aktualisieren
        $updateStmt = $pdo->prepare("
            UPDATE tickets 
            SET geaendert_datum = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([$commentDate, $ticketId]);
        
        // Bei Aktivität (E-Mail-Kommentar): Status von "Neu" auf "In Bearbeitung" setzen, außer bei geplantem Datum
        try {
            $pdo->prepare("UPDATE tickets SET status = 'In Bearbeitung' WHERE id = ? AND status IN ('Neu', 'Warteschlange') AND geplant_datum IS NULL")->execute([$ticketId]);
        } catch (PDOException $e) {
            error_log("Fehler beim automatischen Status-Update (E-Mail): " . $e->getMessage());
        }
        
        // Anhänge zum Ticket hinzufügen
        if (isset($email['attachments']) && is_array($email['attachments'])) {
            addAttachmentsToTicket($ticketId, $email['attachments'], $userId);
        }
        
        // CC-Empfänger als Beobachter hinzufügen (Firma wie beim Ticket)
        if (!empty($email['cc']) && is_array($email['cc'])) {
            addCCObserversToTicket($ticketId, $email['cc']);
        }
        
        // Logging: Kommentar zu bestehendem Ticket hinzugefügt
        logEmailAction('KOMMENTAR', $fromEmail, $email['subject'] ?? '(Kein Betreff)', 'Kommentar zu Ticket ' . $ticketNumber . ' (ID: ' . $ticketId . ') hinzugefügt', $ticketId, $userId);
        
        return [
            'ticket_id' => $ticketId,
            'reason' => null
        ];
    } else {
        // Neues Ticket erstellen
        $titel = cleanStringForDatabase($subject);
        $beschreibung = $body; // Bereits oben bereinigt
        
        // Finale Längenprüfung für Beschreibung (TEXT-Spalte max. ~65.535 Bytes)
        $maxLength = 60000; // Sicherheitspuffer
        if (mb_strlen($beschreibung, 'UTF-8') > $maxLength) {
            error_log("Beschreibung zu lang (" . mb_strlen($beschreibung, 'UTF-8') . " Zeichen), wird gekürzt");
            $beschreibung = mb_substr($beschreibung, 0, $maxLength - 10, 'UTF-8');
            $beschreibung .= "\n[...]";
        }
        
        // Ticket-Nummer generieren
        $ticketNummer = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Prüfen ob Ticket-Nummer bereits existiert
        $checkStmt = $pdo->prepare("SELECT id FROM tickets WHERE ticket_nummer = ?");
        $checkStmt->execute([$ticketNummer]);
        if ($checkStmt->fetch()) {
            // Falls existiert, neue generieren
            $ticketNummer = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        
        // SQL-Query mit optionaler customer_id
        if ($customerId) {
            $stmt = $pdo->prepare("
                INSERT INTO tickets (ticket_nummer, titel, beschreibung, status, prioritaet, company_id, customer_id, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, 'neu', 'normal', ?, ?, ?, NOW())
            ");
            $stmt->execute([$ticketNummer, $titel, $beschreibung, $companyId, $customerId, $userId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO tickets (ticket_nummer, titel, beschreibung, status, prioritaet, company_id, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, 'neu', 'normal', ?, ?, NOW())
            ");
            $stmt->execute([$ticketNummer, $titel, $beschreibung, $companyId, $userId]);
        }
        
        $ticketId = $pdo->lastInsertId();
        
        // Anhänge zum Ticket hinzufügen
        if (isset($email['attachments']) && is_array($email['attachments'])) {
            addAttachmentsToTicket($ticketId, $email['attachments'], $userId);
        }
        
        // CC-Empfänger als Beobachter hinzufügen (nur User der Ticket-Firma, wie in tickets.php)
        if (!empty($email['cc']) && is_array($email['cc'])) {
            addCCObserversToTicket($ticketId, $email['cc'], $companyId);
        }
        
        // Benachrichtigungen erstellen
        $userStmt = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userName = trim(($userData['vorname'] ?? '') . ' ' . ($userData['nachname'] ?? ''));
        if (empty($userName)) {
            $userName = 'Unbekannt';
        }
        
        createNotificationsForAction(
            $userId,
            $companyId,
            'ticket_created',
            'Neues Ticket erstellt: ' . $titel,
            'Ein neues Ticket "' . $titel . '" wurde von ' . $userName . ' erstellt.',
            'normal',
            'tickets/view.php?id=' . $ticketId,
            'ticket',
            $ticketId
        );
        
        // Logging: Neues Ticket aus E-Mail erstellt
        logEmailAction('TICKET_ERSTELLT', $fromEmail, $email['subject'] ?? '(Kein Betreff)', 'Ticket ' . $ticketNummer . ' (ID: ' . $ticketId . ') erstellt', $ticketId, $userId);
        
        return [
            'ticket_id' => $ticketId,
            'reason' => null
        ];
    }
}

/**
 * Verschiebt eine E-Mail in den "Verworfen" Ordner (nur IMAP)
 */
function moveEmailToRejectedFolder($msgNum, $settings) {
    if ($settings['protocol'] !== 'imap') {
        // POP3 unterstützt keine Ordner
        return false;
    }
    
    $imapConnection = null;
    
    try {
        // IMAP-Verbindungsstring erstellen
        $host = $settings['host'];
        $port = $settings['port'];
        $secure = $settings['secure'];
        $mailbox = $settings['mailbox'];
        
        // IMAP-Verbindungsstring
        if ($secure === 'ssl') {
            $connectionString = "{{$host}:{$port}/imap/ssl}";
        } elseif ($secure === 'tls') {
            $connectionString = "{{$host}:{$port}/imap/tls}";
        } else {
            $connectionString = "{{$host}:{$port}/imap/notls}";
        }
        
        $baseConnectionString = $connectionString;
        $connectionString .= $mailbox;
        
        // Verbindung herstellen
        $imapConnection = imap_open($connectionString, $settings['username'], $settings['password']);
        
        if (!$imapConnection) {
            $errors = imap_errors();
            $errorMsg = $errors ? implode(', ', $errors) : 'Unbekannter Fehler';
            throw new Exception("IMAP-Verbindung fehlgeschlagen: " . $errorMsg);
        }
        
        // "Verworfen" Ordner erstellen, falls nicht vorhanden
        $rejectedFolder = 'Verworfen';
        $mailboxes = @imap_list($imapConnection, $baseConnectionString, '*');
        
        $rejectedFolderExists = false;
        if ($mailboxes) {
            foreach ($mailboxes as $mb) {
                // IMAP-Liste gibt vollständige Pfade zurück, z.B. "{host:port/imap/ssl}Verworfen"
                $mbName = str_replace($baseConnectionString, '', $mb);
                $mbName = ltrim($mbName, '/');
                
                // UTF-7 dekodieren falls nötig
                if (function_exists('mb_convert_encoding')) {
                    try {
                        $mbName = imap_utf7_decode($mb);
                        $mbName = str_replace($baseConnectionString, '', $mbName);
                        $mbName = ltrim($mbName, '/');
                    } catch (Exception $e) {
                        // Ignoriere UTF-7 Fehler
                    }
                }
                
                if (strcasecmp(trim($mbName), $rejectedFolder) === 0) {
                    $rejectedFolderExists = true;
                    break;
                }
            }
        }
        
        // Ordner erstellen, falls nicht vorhanden
        if (!$rejectedFolderExists) {
            $fullRejectedPath = $baseConnectionString . $rejectedFolder;
            // UTF-7 kodieren für IMAP
            $encodedPath = imap_utf7_encode($fullRejectedPath);
            $created = @imap_createmailbox($imapConnection, $encodedPath);
            if (!$created) {
                // Versuche ohne UTF-7 Kodierung
                $created = @imap_createmailbox($imapConnection, $fullRejectedPath);
            }
            if (!$created) {
                error_log("Konnte 'Verworfen' Ordner nicht erstellen. Versuche trotzdem zu verschieben.");
            } else {
                error_log("'Verworfen' Ordner erfolgreich erstellt.");
            }
        }
        
        // E-Mail in "Verworfen" Ordner verschieben
        // Verwende Sequenznummer (nicht UID), da imap_mail_move standardmäßig Sequenznummern verwendet
        $targetMailbox = $baseConnectionString . $rejectedFolder;
        $moved = @imap_mail_move($imapConnection, (string)$msgNum, $targetMailbox);
        
        if ($moved) {
            // Änderungen übernehmen
            @imap_expunge($imapConnection);
            error_log("E-Mail #{$msgNum} erfolgreich in 'Verworfen' Ordner verschoben.");
        } else {
            $errors = imap_errors();
            $errorMsg = $errors ? implode(', ', $errors) : 'Unbekannter Fehler';
            error_log("Fehler beim Verschieben der E-Mail #{$msgNum} in 'Verworfen' Ordner: " . $errorMsg);
            // Nicht als Exception werfen, da dies nicht kritisch ist
        }
        
        @imap_close($imapConnection);
        return true;
    } catch (Exception $e) {
        error_log("Fehler beim Verschieben der E-Mail in 'Verworfen' Ordner: " . $e->getMessage());
        if ($imapConnection) {
            @imap_close($imapConnection);
        }
        return false;
    }
}

/**
 * Ruft die System-User-ID ab (verwendet ersten Admin als System-User)
 */
function getSystemUserId() {
    global $pdo;
    
    static $systemUserId = null;
    
    if ($systemUserId !== null) {
        return $systemUserId;
    }
    
    try {
        // Verwende den ersten Admin als System-User
        $stmt = $pdo->prepare("SELECT id FROM users WHERE rolle = 'Admin' ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($adminUser && isset($adminUser['id'])) {
            $systemUserId = (int)$adminUser['id'];
            return $systemUserId;
        }
        
        // Falls kein Admin existiert, verwende den ersten User
        $stmt = $pdo->prepare("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $firstUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($firstUser && isset($firstUser['id'])) {
            $systemUserId = (int)$firstUser['id'];
            return $systemUserId;
        }
        
        // Als letzter Fallback: User-ID 1 (sollte immer existieren)
        error_log("Warnung: Kein User gefunden, verwende User-ID 1 als System-User");
        $systemUserId = 1;
        return $systemUserId;
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der System-User-ID: " . $e->getMessage());
        // Fallback: User-ID 1
        $systemUserId = 1;
        return $systemUserId;
    }
}

/**
 * Loggt E-Mail-Aktionen (Ticket erstellt, Mail verworfen, etc.)
 */
function logEmailAction($action, $fromEmail, $subject, $details = '', $ticketId = null, $userId = null) {
    global $pdo;
    
    $logDir = dirname(__DIR__, 2) . '/logs';
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/email-receive.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$action}] Von: {$fromEmail} | Betreff: {$subject}";
    
    if (!empty($details)) {
        $logMessage .= " | {$details}";
    }
    
    $logMessage .= PHP_EOL;
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Auch in error_log schreiben
    error_log("E-Mail-Verarbeitung: {$action} - {$fromEmail} - {$subject} - {$details}");
    
    // In Datenbank-Log schreiben mit Kategorie "systemmailer"
    try {
        // Action für Datenbank anpassen (muss eines der ENUM-Werte sein: 'created', 'updated', 'deleted')
        $dbAction = '';
        $beschreibung = "E-Mail von: {$fromEmail} | Betreff: {$subject}";
        
        if ($action === 'TICKET_ERSTELLT') {
            $dbAction = 'created'; // Ticket wurde erstellt
            $beschreibung .= " | Ticket erstellt";
            if ($ticketId) {
                $beschreibung .= " (Ticket-ID: {$ticketId})";
            }
        } elseif ($action === 'VERWORFEN') {
            $dbAction = 'deleted'; // E-Mail wurde verworfen
            $beschreibung .= " | E-Mail verworfen";
            if (!empty($details)) {
                $beschreibung .= " | Grund: {$details}";
            }
        } elseif ($action === 'KOMMENTAR') {
            $dbAction = 'updated'; // Ticket wurde aktualisiert (Kommentar hinzugefügt)
            $beschreibung .= " | Kommentar zu bestehendem Ticket hinzugefügt";
            if ($ticketId) {
                $beschreibung .= " (Ticket-ID: {$ticketId})";
            }
        } else {
            $dbAction = 'updated'; // Standard für andere Aktionen
            if (!empty($details)) {
                $beschreibung .= " | {$details}";
            }
        }
        
        // Entity-ID: Ticket-ID falls vorhanden, sonst 0
        $entityId = $ticketId ? (int)$ticketId : 0;
        
        // User-ID: Falls nicht vorhanden, System-User verwenden
        $logUserId = $userId ? (int)$userId : getSystemUserId();
        
        $logStmt = $pdo->prepare("
            INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
            VALUES ('systemmailer', ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$entityId, $logUserId, $dbAction, $beschreibung]);
        
        error_log("E-Mail-Log erfolgreich in Datenbank geschrieben: kategorie=systemmailer, entity_id={$entityId}, user_id={$logUserId}, action={$dbAction}");
    } catch (PDOException $e) {
        error_log("Fehler beim Schreiben des E-Mail-Logs in die Datenbank: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        error_log("SQL Error Info: " . print_r($e->errorInfo, true));
        error_log("Versuchte Werte: kategorie=systemmailer, entity_id={$entityId}, user_id={$logUserId}, action={$dbAction}");
        error_log("Stack trace: " . $e->getTraceAsString());
    }
}

/**
 * Fügt Anhänge zu einem Ticket hinzu
 */
function addAttachmentsToTicket($ticketId, $attachments, $userId) {
    global $pdo;
    
    if (empty($attachments) || !is_array($attachments)) {
        return;
    }
    
    // Speichere E-Mail-Anhänge im Ordner uploads/tickets/mail
    $uploadBaseDir = dirname(__DIR__, 2) . '/uploads/tickets/mail/';
    if (!file_exists($uploadBaseDir)) {
        if (!mkdir($uploadBaseDir, 0755, true)) {
            error_log("Fehler beim Erstellen des Upload-Verzeichnisses: " . $uploadBaseDir);
            return;
        }
    }
    
    // Prüfen ob Verzeichnis beschreibbar ist
    if (!is_writable($uploadBaseDir)) {
        error_log("Upload-Verzeichnis ist nicht beschreibbar: " . $uploadBaseDir);
        return;
    }
    
    foreach ($attachments as $attachment) {
        try {
            if (!isset($attachment['filename']) || !isset($attachment['data'])) {
                error_log("Ungültiger Anhang: Fehlende Daten");
                continue;
            }
            
            $filename = $attachment['filename'];
            $data = $attachment['data'];
            $mimeType = $attachment['mime_type'] ?? 'application/octet-stream';
            
            // Prüfen ob Daten vorhanden sind
            if (empty($data)) {
                error_log("Anhang hat keine Daten: " . $filename);
                continue;
            }
            
            // Dateiname sicher machen
            $originalFilename = $filename;
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            
            // Dateiname bereinigen (UTF-8 sicher)
            $baseName = cleanStringForDatabase($baseName);
            if (empty($baseName)) {
                $baseName = 'attachment';
            }
            
            // Sicherer Dateiname für Dateisystem
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName);
            $safeName = mb_substr($safeName, 0, 100); // Maximal 100 Zeichen
            
            // Eindeutigen Dateinamen erstellen
            $fileName = 'ticket_' . $ticketId . '_' . $safeName . '_' . time() . '_' . rand(1000, 9999);
            if (!empty($extension)) {
                $fileName .= '.' . preg_replace('/[^a-zA-Z0-9]/', '', $extension);
            }
            $filePath = $uploadBaseDir . $fileName;
            
            // Prüfen ob Datei bereits existiert (sehr unwahrscheinlich, aber sicherheitshalber)
            $counter = 1;
            while (file_exists($filePath)) {
                $fileName = 'ticket_' . $ticketId . '_' . $safeName . '_' . time() . '_' . rand(1000, 9999) . '_' . $counter;
                if (!empty($extension)) {
                    $fileName .= '.' . preg_replace('/[^a-zA-Z0-9]/', '', $extension);
                }
                $filePath = $uploadBaseDir . $fileName;
                $counter++;
            }
            
            // Datei speichern
            $bytesWritten = file_put_contents($filePath, $data);
            if ($bytesWritten === false) {
                error_log("Fehler beim Speichern des Anhangs: " . $originalFilename . " nach " . $filePath);
                continue;
            }
            
            // Prüfen ob Datei korrekt geschrieben wurde
            if (!file_exists($filePath) || filesize($filePath) !== strlen($data)) {
                error_log("Anhang wurde nicht korrekt gespeichert: " . $originalFilename);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                continue;
            }
            
            // Relativer Pfad für Datenbank
            $relativePath = 'uploads/tickets/mail/' . $fileName;
            
            // Dateiname für Datenbank bereinigen
            $dbFilename = cleanStringForDatabase($originalFilename);
            if (empty($dbFilename)) {
                $dbFilename = $fileName;
            }
            
            // In Datenbank speichern
            $stmt = $pdo->prepare("
                INSERT INTO ticket_attachments (ticket_id, dateiname, dateipfad, dateigroesse, mime_type, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $ticketId,
                $dbFilename,
                $relativePath,
                strlen($data),
                $mimeType,
                $userId
            ]);
            
            error_log("Anhang erfolgreich gespeichert: " . $originalFilename . " -> " . $filePath);
        } catch (Exception $e) {
            error_log("Fehler beim Hinzufügen des Anhangs " . ($attachment['filename'] ?? 'unbekannt') . ": " . $e->getMessage());
            continue;
        }
    }
}

/**
 * Fügt CC-Empfänger als Beobachter zu einem Ticket hinzu, sofern sie im System existieren
 * und zur Firma des Tickets gehören (status aktiv), analog zu tickets/api/tickets.php.
 *
 * @param int $ticketId
 * @param string[] $ccEmails
 * @param int|null $ticketCompanyId Firma des Tickets; null = aus DB laden
 */
function addCCObserversToTicket($ticketId, $ccEmails, $ticketCompanyId = null) {
    global $pdo;
    
    $ticketId = (int)$ticketId;
    if ($ticketId <= 0 || empty($ccEmails) || !is_array($ccEmails)) {
        return;
    }
    
    if ($ticketCompanyId === null) {
        $stmt = $pdo->prepare("SELECT company_id FROM tickets WHERE id = ? LIMIT 1");
        $stmt->execute([$ticketId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $ticketCompanyId = isset($row['company_id']) ? (int)$row['company_id'] : 0;
    } else {
        $ticketCompanyId = (int)$ticketCompanyId;
    }
    
    if ($ticketCompanyId <= 0) {
        return;
    }
    
    $seen = [];
    foreach ($ccEmails as $ccEmail) {
        $ccEmail = normalizeEmailValue($ccEmail);
        if ($ccEmail === '' || isset($seen[$ccEmail])) {
            continue;
        }
        $seen[$ccEmail] = true;
        
        $user = findUserByEmailAddress($ccEmail, ['id']);
        if (!$user) {
            continue;
        }
        
        $observerId = (int)$user['id'];
        if ($observerId <= 0) {
            continue;
        }
        
        // Nur aktive Nutzer der Ticket-Firma (wie API bei observer_ids)
        $validStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND company_id = ? AND status = 'aktiv' LIMIT 1");
        $validStmt->execute([$observerId, $ticketCompanyId]);
        if (!$validStmt->fetch(PDO::FETCH_ASSOC)) {
            continue;
        }
        
        try {
            $observerStmt = $pdo->prepare("INSERT INTO ticket_observers (ticket_id, user_id, erstellt_datum) VALUES (?, ?, NOW())");
            $observerStmt->execute([$ticketId, $observerId]);
        } catch (PDOException $e) {
            // Duplikat ignorieren (UNIQUE KEY verhindert doppelte Einträge)
            error_log("Observer bereits vorhanden: " . $e->getMessage());
        }
    }
}
