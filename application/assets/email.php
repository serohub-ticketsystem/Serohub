<?php
/**
 * E-Mail-Versand-Funktionen
 * Unterstützt SMTP und native PHP mail() Funktion
 */

require_once __DIR__ . '/config.php';

/**
 * Lädt SMTP-Einstellungen aus der Datenbank
 * Falls nicht verfügbar, werden Standardwerte verwendet
 */
function getSmtpSettings() {
    global $pdo;
    
    $defaultSettings = [
        'enabled' => false,
        'host' => 'smtp.example.com',
        'port' => 587,
        'secure' => 'tls',
        'username' => '',
        'password' => '',
        'from_email' => 'noreply@serviceportal.local',
        'from_name' => 'Serohub'
    ];
    
    try {
        if (!isset($pdo)) {
            return $defaultSettings;
        }
        
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = $defaultSettings;
        foreach ($results as $row) {
            $key = str_replace('smtp_', '', $row['setting_key']);
            if ($key === 'enabled') {
                $settings['enabled'] = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
            } elseif ($key === 'port') {
                $settings['port'] = (int)$row['setting_value'];
            } else {
                $settings[$key] = $row['setting_value'];
            }
        }
        
        return $settings;
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der SMTP-Einstellungen: " . $e->getMessage());
        return $defaultSettings;
    }
}

// Legacy-Konstanten für Rückwärtskompatibilität (werden aus DB geladen)
$smtpSettings = getSmtpSettings();
if (!defined('SMTP_ENABLED')) {
    define('SMTP_ENABLED', $smtpSettings['enabled']);
    define('SMTP_HOST', $smtpSettings['host']);
    define('SMTP_PORT', $smtpSettings['port']);
    define('SMTP_SECURE', $smtpSettings['secure']);
    define('SMTP_USERNAME', $smtpSettings['username']);
    define('SMTP_PASSWORD', $smtpSettings['password']);
    define('SMTP_FROM_EMAIL', $smtpSettings['from_email']);
    define('SMTP_FROM_NAME', $smtpSettings['from_name']);
}

/**
 * Kategorie für Benachrichtigungstypen (Anzeige im Mail-Log).
 */
function mailLogCategoryFromNotificationType($notificationType) {
    if ($notificationType === null || $notificationType === '') {
        return 'Benachrichtigung';
    }
    $map = [
        'todo_zugewiesen' => 'Benachrichtigung · Aufgaben',
        'todo_assigned' => 'Benachrichtigung · Aufgaben',
        'ticket_created' => 'Benachrichtigung · Tickets',
        'ticket_assigned' => 'Benachrichtigung · Tickets',
        'ticket_comment' => 'Benachrichtigung · Tickets',
        'ticket_status_changed' => 'Benachrichtigung · Tickets',
        'ticket_closed' => 'Benachrichtigung · Tickets',
        '2fa_enabled' => 'Benachrichtigung · Sicherheit',
        '2fa_disabled' => 'Benachrichtigung · Sicherheit',
    ];
    return $map[$notificationType] ?? ('Benachrichtigung · ' . $notificationType);
}

/**
 * Schreibt einen Eintrag in mail_log (Fehler beim Insert werden nur ins PHP-Log geschrieben).
 *
 * @param string|array $recipients Empfänger
 * @param string $subject Betreff
 * @param string|null $fromEmail Absender
 * @param string $category Bereich/Kategorie
 * @param bool $success Versand erfolgreich
 * @param string|null $errorMessage Fehlertext bei Misserfolg
 */
function logOutgoingMail($recipients, $subject, $fromEmail, $category, $success, $errorMessage = null) {
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    $recipientsStr = is_array($recipients) ? implode(', ', $recipients) : (string) $recipients;
    $trim = function ($s, $max) {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
        }
        return strlen($s) > $max ? substr($s, 0, $max - 1) . '…' : $s;
    };
    $recipientsStr = $trim($recipientsStr, 65000);
    $subject = $trim((string) $subject, 512);
    $category = $trim((string) ($category ?: 'Allgemein'), 128);
    $fromEmail = $fromEmail ? $trim((string) $fromEmail, 255) : null;
    $status = $success ? 'success' : 'failed';
    $err = $errorMessage ? $trim((string) $errorMessage, 2000) : null;
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO mail_log (sent_at, recipients, subject, from_email, category, status, error_message) VALUES (NOW(), ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$recipientsStr, $subject, $fromEmail, $category, $status, $err]);
    } catch (Throwable $e) {
        error_log('mail_log: ' . $e->getMessage());
    }
}

/**
 * Sendet eine E-Mail
 * 
 * @param string|array $to E-Mail-Adresse(n) des Empfängers
 * @param string $subject Betreff
 * @param string $message Nachricht (kann HTML enthalten)
 * @param bool $isHtml Ist die Nachricht HTML?
 * @param string|null $fromEmail Absender-E-Mail (optional)
 * @param string|null $fromName Absender-Name (optional)
 * @param array|null $attachments Array von Dateipfaden für Anhänge
 * @param string $category Bereich für das Mail-Log (z. B. Kalender, Benachrichtigung)
 * @return bool Erfolg
 */
function sendEmail($to, $subject, $message, $isHtml = false, $fromEmail = null, $fromName = null, $attachments = null, $category = 'Allgemein') {
    $smtpSettings = getSmtpSettings();
    $fromForLog = $fromEmail ?: ($smtpSettings['from_email'] ?? null);
    $toForLog = is_array($to) ? implode(', ', $to) : $to;
    try {
        if ($smtpSettings['enabled']) {
            $ok = sendEmailViaSMTP($to, $subject, $message, $isHtml, $fromEmail, $fromName, $attachments, $smtpSettings);
        } else {
            $ok = sendEmailViaNative($to, $subject, $message, $isHtml, $fromEmail, $fromName, $smtpSettings);
        }
        logOutgoingMail($toForLog, $subject, $fromForLog, $category, (bool) $ok, $ok ? null : 'Versand fehlgeschlagen (z. B. mail() false)');
        return $ok;
    } catch (Exception $e) {
        logOutgoingMail($toForLog, $subject, $fromForLog, $category, false, $e->getMessage());
        error_log("sendEmail Fehler: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Sendet eine E-Mail über SMTP
 */
function sendEmailViaSMTP($to, $subject, $message, $isHtml = false, $fromEmail = null, $fromName = null, $attachments = null, $smtpSettings = null) {
    if ($smtpSettings === null) {
        $smtpSettings = getSmtpSettings();
    }
    
    // Prüfe ob PHPMailer verfügbar ist
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailViaPHPMailer($to, $subject, $message, $isHtml, $fromEmail, $fromName, $attachments, $smtpSettings);
    }
    
    // Fallback: Eigene SMTP-Implementierung
    return sendEmailViaCustomSMTP($to, $subject, $message, $isHtml, $fromEmail, $fromName, $smtpSettings);
}

/**
 * Sendet eine E-Mail über PHPMailer
 */
function sendEmailViaPHPMailer($to, $subject, $message, $isHtml = false, $fromEmail = null, $fromName = null, $attachments = null, $smtpSettings = null) {
    if ($smtpSettings === null) {
        $smtpSettings = getSmtpSettings();
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP-Einstellungen
        $mail->isSMTP();
        $mail->Host = $smtpSettings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpSettings['username'];
        $mail->Password = $smtpSettings['password'];
        $mail->SMTPSecure = $smtpSettings['secure'];
        $mail->Port = $smtpSettings['port'];
        $mail->CharSet = 'UTF-8';
        
        // Absender
        $fromEmail = $fromEmail ?: $smtpSettings['from_email'];
        $fromName = $fromName ?: $smtpSettings['from_name'];
        $mail->setFrom($fromEmail, $fromName);
        
        // Empfänger
        if (is_array($to)) {
            foreach ($to as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($to);
        }
        
        // Inhalt
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        if (!$isHtml) {
            $mail->AltBody = strip_tags($message);
        }
        
        // Anhänge
        if ($attachments && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Fehler: " . $e->getMessage());
        throw $e; // Fehler weiterwerfen damit er in der API behandelt werden kann
    }
}

/**
 * Sendet eine E-Mail über eigene SMTP-Implementierung (ohne PHPMailer)
 */
function sendEmailViaCustomSMTP($to, $subject, $message, $isHtml = false, $fromEmail = null, $fromName = null, $smtpSettings = null) {
    if ($smtpSettings === null) {
        $smtpSettings = getSmtpSettings();
    }
    
    // Prüfe ob SMTP aktiviert ist
    if (!$smtpSettings['enabled']) {
        error_log("SMTP ist nicht aktiviert");
        throw new Exception("SMTP ist nicht aktiviert. Bitte aktiviere SMTP in den Einstellungen.");
    }
    
    // Prüfe ob alle notwendigen Einstellungen vorhanden sind
    if (empty($smtpSettings['host'])) {
        throw new Exception("SMTP Host ist nicht konfiguriert");
    }
    if (empty($smtpSettings['username'])) {
        throw new Exception("SMTP Benutzername ist nicht konfiguriert");
    }
    if (empty($smtpSettings['password'])) {
        throw new Exception("SMTP Passwort ist nicht konfiguriert");
    }
    
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $socket = @stream_socket_client(
            ($smtpSettings['secure'] === 'ssl' ? 'ssl://' : '') . $smtpSettings['host'] . ':' . $smtpSettings['port'],
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            error_log("SMTP Verbindungsfehler: $errstr ($errno)");
            throw new Exception("Verbindung zum SMTP-Server fehlgeschlagen: $errstr ($errno)");
        }
        
        // SMTP-Handshake - warte auf Server-Begrüßung
        $response = '';
        $timeout = 10;
        $startTime = time();
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break; // Letzte Zeile der mehrzeiligen Antwort
            }
            if (time() - $startTime > $timeout) {
                throw new Exception("Timeout beim Warten auf SMTP-Server");
            }
        }
        
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            throw new Exception("SMTP-Server-Begrüßung fehlgeschlagen: " . trim($response));
        }
        
        // EHLO
        fputs($socket, "EHLO " . $smtpSettings['host'] . "\r\n");
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("EHLO fehlgeschlagen: " . trim($response));
        }
        
        // STARTTLS wenn TLS verwendet wird (aber nicht wenn bereits SSL-Verbindung)
        if ($smtpSettings['secure'] == 'tls' && !strpos($smtpSettings['host'], 'ssl://')) {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                throw new Exception("STARTTLS fehlgeschlagen: " . trim($response));
            }
            
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new Exception("TLS-Verschlüsselung konnte nicht aktiviert werden");
            }
            
            // Nach STARTTLS nochmal EHLO senden
            fputs($socket, "EHLO " . $smtpSettings['host'] . "\r\n");
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') {
                    break;
                }
            }
        }
        
        // Authentifizierung
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            throw new Exception("AUTH LOGIN fehlgeschlagen: " . trim($response));
        }
        
        fputs($socket, base64_encode($smtpSettings['username']) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            throw new Exception("Benutzername-Authentifizierung fehlgeschlagen: " . trim($response));
        }
        
        fputs($socket, base64_encode($smtpSettings['password']) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            throw new Exception("Passwort-Authentifizierung fehlgeschlagen: " . trim($response));
        }
        
        // MAIL FROM
        $fromEmail = $fromEmail ?: $smtpSettings['from_email'];
        fputs($socket, "MAIL FROM: <$fromEmail>\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("MAIL FROM fehlgeschlagen: " . trim($response));
        }
        
        // RCPT TO
        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $recipient) {
            fputs($socket, "RCPT TO: <$recipient>\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '250' && substr($response, 0, 3) != '251') {
                fclose($socket);
                throw new Exception("RCPT TO fehlgeschlagen: " . trim($response));
            }
        }
        
        // DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            throw new Exception("DATA fehlgeschlagen: " . trim($response));
        }
        
        // E-Mail-Header und Body
        $fromName = $fromName ?: $smtpSettings['from_name'];
        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "To: " . (is_array($to) ? implode(', ', $to) : $to) . "\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        fputs($socket, $headers . "\r\n" . $message . "\r\n.\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("E-Mail-Versand fehlgeschlagen: " . trim($response));
        }
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
    } catch (Exception $e) {
        error_log("SMTP Fehler: " . $e->getMessage());
        if (isset($socket) && is_resource($socket)) {
            @fclose($socket);
        }
        throw $e; // Fehler weiterwerfen damit er in der API behandelt werden kann
    }
}

/**
 * Sendet eine E-Mail über native PHP mail() Funktion
 */
function sendEmailViaNative($to, $subject, $message, $isHtml = false, $fromEmail = null, $fromName = null, $smtpSettings = null) {
    if ($smtpSettings === null) {
        $smtpSettings = getSmtpSettings();
    }
    
    $fromEmail = $fromEmail ?: $smtpSettings['from_email'];
    $fromName = $fromName ?: $smtpSettings['from_name'];
    
    $headers = "From: $fromName <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    if ($isHtml) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    
    $recipients = is_array($to) ? implode(', ', $to) : $to;
    
    return mail($recipients, $subject, $message, $headers);
}

/**
 * Sendet eine Benachrichtigungs-E-Mail (verbesserte Version)
 * 
 * @param int $userId Benutzer-ID
 * @param string $titel Titel der E-Mail
 * @param string $nachricht Nachrichtentext
 * @param string|null $link Link zur relevanten Seite
 * @param bool $isHtml Ist die Nachricht HTML?
 * @param string|null $notificationType Typ der Benachrichtigung (z.B. 'todo_zugewiesen', 'ticket_created')
 * @param string|null $referenzTyp Typ des referenzierten Objekts (z.B. 'ticket', 'todo')
 * @param int|null $referenzId ID des referenzierten Objekts
 * @return bool Erfolg
 */
function sendNotificationEmailAdvanced($userId, $titel, $nachricht, $link = null, $isHtml = true, $notificationType = null, $referenzTyp = null, $referenzId = null) {
    global $pdo;
    
    try {
        // Prüfe ob E-Mail-Benachrichtigungen für diesen Benutzer aktiviert sind
        $stmt = $pdo->prepare("
            SELECT setting_value 
            FROM user_settings 
            WHERE user_id = :user_id AND setting_key = 'email_enabled'
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Standardmäßig deaktiviert; nur explizit "an" = aktiviert
        $emailEnabled = false;
        if ($result && $result['setting_value'] !== null && $result['setting_value'] !== '') {
            $v = is_string($result['setting_value']) ? strtolower(trim($result['setting_value'])) : $result['setting_value'];
            if (in_array($v, ['1', 'true', 'yes', 'on'], true) || $v === true) {
                $emailEnabled = true;
            }
        }
        
        if (!$emailEnabled) {
            error_log("E-Mail-Benachrichtigungen für User $userId deaktiviert (email_enabled aus)");
            return false;
        }
        
        // Benutzer-E-Mail abrufen
        $stmt = $pdo->prepare("SELECT email, vorname, nachname FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['email'])) {
            error_log("Keine E-Mail-Adresse für User $userId gefunden");
            return false;
        }
        
        // BASE_URL für Links
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
        $fullLink = $link ? $baseUrl . ltrim($link, '/') : null;
        
        // Name des Empfängers
        $name = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
        if (empty(trim($name))) {
            $name = $user['email'];
        }
        
        // Prüfe ob eine E-Mail-Vorlage für diesen Benachrichtigungstyp existiert
        $templateId = null;
        if ($notificationType) {
            // Mapping von Notification-Typ zu Setting-Key
            $notificationTypeMapping = [
                'todo_zugewiesen' => 'email_template_todo_assigned',
                'todo_assigned' => 'email_template_todo_assigned', // Alias
                'ticket_created' => 'email_template_ticket_created',
                'ticket_assigned' => 'email_template_ticket_assigned',
                'ticket_comment' => 'email_template_ticket_comment',
                'ticket_status_changed' => 'email_template_ticket_status_changed',
                'ticket_closed' => 'email_template_ticket_closed',
                '2fa_enabled' => 'email_template_2fa_enabled',
                '2fa_disabled' => 'email_template_2fa_disabled',
            ];
            
            $settingKey = $notificationTypeMapping[$notificationType] ?? null;
            if ($settingKey) {
                try {
                    $templateStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
                    $templateStmt->execute([$settingKey]);
                    $templateResult = $templateStmt->fetch(PDO::FETCH_ASSOC);
                    if ($templateResult && !empty($templateResult['setting_value'])) {
                        $templateId = (int)$templateResult['setting_value'];
                    }
                } catch (PDOException $e) {
                    error_log("Fehler beim Laden der E-Mail-Vorlage: " . $e->getMessage());
                }
            }
        }
        
        // Bestellnummer (Auftragsnummer), Ticketnummer und Ticket-Titel für Ticket-bezogene E-Mails ermitteln
        $bestellnummer = null;
        $ticketNummer = null;
        $ticketTitel = null;
        $ticketId = null;
        
        // Ticket-ID ermitteln
        if ($referenzTyp === 'ticket' && $referenzId) {
            $ticketId = (int)$referenzId;
        } elseif ($link && preg_match('#/(?:service|tickets)/view\.php\?id=(\d+)#', $link, $matches)) {
            // Ticket-ID aus Link extrahieren
            $ticketId = (int)$matches[1];
        } elseif ($notificationType && (strpos($notificationType, 'ticket_') === 0) && $link) {
            // Versuche Ticket-ID aus Link zu extrahieren
            if (preg_match('#/(?:service|tickets)/view\.php\?id=(\d+)#', $link, $matches)) {
                $ticketId = (int)$matches[1];
            }
        }
        
        // Ticketnummer, Ticket-Titel und Bestellnummer aus Datenbank abrufen, wenn Ticket-ID vorhanden
        if ($ticketId) {
            try {
                // Ticketnummer und Titel abrufen (für Betreff-Variable)
                $ticketStmt = $pdo->prepare("
                    SELECT ticket_nummer, titel 
                    FROM tickets 
                    WHERE id = ? 
                    LIMIT 1
                ");
                $ticketStmt->execute([$ticketId]);
                $ticketResult = $ticketStmt->fetch(PDO::FETCH_ASSOC);
                if ($ticketResult) {
                    if (!empty($ticketResult['ticket_nummer'])) {
                        $ticketNummer = $ticketResult['ticket_nummer'];
                    }
                    if (isset($ticketResult['titel'])) {
                        $ticketTitel = trim($ticketResult['titel']);
                    }
                }
                
                // Bestellnummer abrufen
                $orderStmt = $pdo->prepare("
                    SELECT bestellnummer 
                    FROM orders 
                    WHERE ticket_id = ? 
                    ORDER BY erstellt_datum DESC 
                    LIMIT 1
                ");
                $orderStmt->execute([$ticketId]);
                $orderResult = $orderStmt->fetch(PDO::FETCH_ASSOC);
                if ($orderResult && !empty($orderResult['bestellnummer'])) {
                    $bestellnummer = $orderResult['bestellnummer'];
                }
            } catch (PDOException $e) {
                error_log("Fehler beim Abrufen der Ticketnummer/Bestellnummer: " . $e->getMessage());
            }
        }
        
        // Wenn eine Vorlage gefunden wurde, verwende diese
        if ($templateId) {
            // Betreff-Zeile für E-Mail: Struktur mit #Ticketnummer für automatische Zuordnung bei Antwort
            $betreff = '';
            if ($ticketNummer) {
                $betreff = ($ticketTitel !== null && $ticketTitel !== '') ? ($ticketTitel . ' #' . $ticketNummer) : ('#' . $ticketNummer);
            }
            
            // Variablen für die Vorlage vorbereiten
            $variables = [
                'name' => $name,
                'titel' => $titel,
                'nachricht' => $nachricht,
                'beschreibung' => $nachricht, // Alias für nachricht
                'link' => $fullLink ?: '',
                'vorname' => $user['vorname'] ?? '',
                'nachname' => $user['nachname'] ?? '',
                'email' => $user['email'],
                'userName' => $name,
                'datum' => date('d.m.Y H:i'),
                'bestellnummer' => $bestellnummer ?: '',
                'auftragsnummer' => $bestellnummer ?: '', // Alias für bestellnummer
                'ticketnummer' => $ticketNummer ?: '',
                'ticket_nummer' => $ticketNummer ?: '', // Alias für ticketnummer
                'ticket_titel' => $ticketTitel ?: '',
                'betreff' => $betreff  // Empfohlene Betreffzeile (z. B. "Ticket-Titel #SRV-20260209-5976") für Erkennung bei Antwort
            ];
            
            // Bestellnummer in Betreff einfügen, wenn vorhanden
            $templateSubject = null;
            if ($bestellnummer) {
                try {
                    $templateStmt = $pdo->prepare("SELECT subject FROM email_templates WHERE id = ? LIMIT 1");
                    $templateStmt->execute([$templateId]);
                    $templateResult = $templateStmt->fetch(PDO::FETCH_ASSOC);
                    if ($templateResult && !empty($templateResult['subject'])) {
                        $templateSubject = '[' . $bestellnummer . '] ' . $templateResult['subject'];
                        // Variablen im Betreff ersetzen
                        foreach ($variables as $key => $value) {
                            $templateSubject = str_replace('{{' . $key . '}}', $value, $templateSubject);
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Fehler beim Laden des Vorlagen-Betreffs: " . $e->getMessage());
                }
            }
            
            $cat = mailLogCategoryFromNotificationType($notificationType);
            return sendEmailWithTemplate($templateId, $user['email'], $variables, $templateSubject, $cat);
        }
        
        // Standard-E-Mail-Vorlage verwenden
        $subject = 'Benachrichtigung: ' . $titel;
        
        // Bestellnummer in den Betreff einfügen, wenn vorhanden
        if ($bestellnummer) {
            $subject = '[' . $bestellnummer . '] ' . $subject;
        }
        
        // Warnung hinzufügen, wenn Bestellnummer vorhanden
        $warnung = '';
        if ($bestellnummer) {
            $warnung = "\n\n⚠️ WICHTIG: Die Auftragsnummer [" . $bestellnummer . "] im Betreff darf nicht entfernt werden, da das Ticket sonst nicht zugeordnet werden kann.";
        }
        
        if ($isHtml) {
            $message = createHtmlEmailTemplate($name, $nachricht . $warnung, $fullLink, $titel);
        } else {
            $message = "Hallo " . $name . ",\n\n";
            $message .= $nachricht . $warnung . "\n\n";
            if ($fullLink) {
                $message .= "Link: " . $fullLink . "\n\n";
            }
            $message .= "Mit freundlichen Grüßen,\nIhr Serohub-Team";
        }
        
        $cat = mailLogCategoryFromNotificationType($notificationType);
        return sendEmail($user['email'], $subject, $message, $isHtml, null, null, null, $cat);
    } catch (PDOException $e) {
        error_log("Fehler beim Senden der Benachrichtigungs-E-Mail: " . $e->getMessage());
        return false;
    }
}

/**
 * Erstellt eine HTML-E-Mail-Vorlage
 */
function createHtmlEmailTemplate($name, $nachricht, $link = null, $titel = null) {
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
    
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benachrichtigung</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h1 style="color: #2563eb; margin-top: 0;">Serohub</h1>
    </div>
    
    <div style="background-color: #ffffff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 5px;">
        <h2 style="color: #111827; margin-top: 0;">' . htmlspecialchars($titel ?: 'Benachrichtigung') . '</h2>
        
        <p>Hallo ' . htmlspecialchars($name) . ',</p>
        
        <div style="background-color: #f9fafb; padding: 15px; border-left: 4px solid #2563eb; margin: 20px 0;">
            <p style="margin: 0;">' . nl2br(htmlspecialchars($nachricht)) . '</p>
        </div>';
    
    if ($link) {
        $html .= '
        <div style="margin: 20px 0;">
            <a href="' . htmlspecialchars($link) . '" style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">Zur Benachrichtigung</a>
        </div>';
    }
    
    $html .= '
        <p style="margin-top: 30px;">Mit freundlichen Grüßen,<br>Ihr Serohub-Team</p>
    </div>
    
    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px;">
        <p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese E-Mail.</p>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Sendet eine E-Mail mit einer Vorlage
 * 
 * @param int $templateId ID der Vorlage
 * @param string|array $to E-Mail-Adresse(n) des Empfängers
 * @param array $variables Array mit Variablen-Werten (z.B. ['titel' => 'Test', 'name' => 'Max'])
 * @param string|null $overrideSubject Optionaler Betreff, der den Vorlagen-Betreff überschreibt
 * @param string $category Bereich für das Mail-Log
 * @return bool Erfolg
 */
function sendEmailWithTemplate($templateId, $to, $variables = [], $overrideSubject = null, $category = 'E-Mail-Vorlage') {
    global $pdo;
    
    try {
        // Vorlage aus Datenbank laden
        $stmt = $pdo->prepare("SELECT name, subject, body FROM email_templates WHERE id = ? LIMIT 1");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            error_log("E-Mail-Vorlage mit ID $templateId nicht gefunden");
            return false;
        }
        
        // Variablen ersetzen
        $subject = $overrideSubject ?: $template['subject'];
        $body = $template['body'];
        
        foreach ($variables as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        
        // Warnung hinzufügen, wenn Bestellnummer vorhanden
        if (isset($variables['bestellnummer']) && !empty($variables['bestellnummer'])) {
            $warnung = "\n\n⚠️ WICHTIG: Die Auftragsnummer [" . $variables['bestellnummer'] . "] im Betreff darf nicht entfernt werden, da das Ticket sonst nicht zugeordnet werden kann.";
            $body = str_replace('</body>', '<p style="color: #dc2626; font-weight: bold; margin-top: 20px;">⚠️ WICHTIG: Die Auftragsnummer [' . htmlspecialchars($variables['bestellnummer']) . '] im Betreff darf nicht entfernt werden, da das Ticket sonst nicht zugeordnet werden kann.</p></body>', $body);
        }
        
        // E-Mail senden
        return sendEmail($to, $subject, $body, true, null, null, null, $category);
    } catch (PDOException $e) {
        error_log("Fehler beim Senden der E-Mail mit Vorlage: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Fehler beim Senden der E-Mail mit Vorlage: " . $e->getMessage());
        return false;
    }
}
