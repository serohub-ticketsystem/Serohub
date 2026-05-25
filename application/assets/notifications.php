<?php
/**
 * Helper-Funktionen für Benachrichtigungen
 */

// config.php wird bereits in den API-Dateien eingebunden, daher nur laden wenn noch nicht geladen
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/push_notifications.php';

/**
 * Erstellt eine Benachrichtigung für einen Benutzer
 * 
 * @param int $userId Benutzer-ID
 * @param string $typ Typ der Benachrichtigung (z.B. 'ticket_erstellt', 'ticket_nachricht', 'todo_erstellt')
 * @param string $titel Titel der Benachrichtigung
 * @param string $nachricht Nachrichtentext
 * @param string $relevanz Relevanz ('niedrig', 'normal', 'hoch', 'kritisch')
 * @param string|null $link Link zur relevanten Seite
 * @param string|null $referenzTyp Typ des referenzierten Objekts (z.B. 'ticket', 'todo')
 * @param int|null $referenzId ID des referenzierten Objekts
 * @param bool $sendEmail Soll E-Mail gesendet werden (prüft Einstellungen)
 * @param int|null $createdByUserId ID des Benutzers, der die Benachrichtigung ausgelöst hat
 * @return int|false ID der erstellten Benachrichtigung oder false bei Fehler
 */
function createNotification($userId, $typ, $titel, $nachricht, $relevanz = 'normal', $link = null, $referenzTyp = null, $referenzId = null, $sendEmail = true, $createdByUserId = null) {
    global $pdo;
    
    // Prüfe ob $pdo verfügbar ist
    if (!isset($pdo)) {
        error_log("Fehler: PDO-Verbindung nicht verfügbar in createNotification");
        return false;
    }
    
    try {
        $systemEnabledStmt = $pdo->prepare("
            SELECT setting_value
            FROM user_settings
            WHERE user_id = :user_id AND setting_key = 'system_notifications_enabled'
        ");
        $systemEnabledStmt->execute([':user_id' => $userId]);
        $systemEnabledRow = $systemEnabledStmt->fetch(PDO::FETCH_ASSOC);
        $globalSystemEnabled = true;
        if ($systemEnabledRow && $systemEnabledRow['setting_value'] !== null) {
            $globalSystemEnabled = in_array($systemEnabledRow['setting_value'], ['1', 'true'], true);
        }

        if (!$globalSystemEnabled) {
            return false;
        }

        // Prüfe Benachrichtigungseinstellungen des Benutzers aus user_settings
        $stmt = $pdo->prepare("
            SELECT setting_value 
            FROM user_settings 
            WHERE user_id = :user_id AND setting_key = 'notification_settings'
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Standardwerte: Präferenzen standardmäßig aktiv.
        // Der tatsächliche E-Mail-Versand wird weiterhin über `email_enabled` separat gesteuert.
        $defaultSettings = ['system' => true, 'email' => true];
        
        if ($result && $result['setting_value']) {
            $allSettings = json_decode($result['setting_value'], true) ?? [];
            // Typ-Aliase: UI/DB/versch. Seiten nutzen teils unterschiedliche Keys
            $typAliase = [
                'ticket_created' => 'ticket_erstellt',
                'ticket_erstellt' => 'ticket_created',
                'todo_created' => 'todo_erstellt',
                'todo_erstellt' => 'todo_created',
                'todo_status_changed' => 'todo_status',
                'todo_status' => 'todo_status_changed',
            ];
            $aliasTyp = $typAliase[$typ] ?? null;
            $typeSettings = $allSettings[$typ] ?? ($aliasTyp !== null ? ($allSettings[$aliasTyp] ?? null) : null) ?? $defaultSettings;
        } else {
            $typeSettings = $defaultSettings;
        }
        
        $systemEnabled = (bool)($typeSettings['system'] ?? true);
        $emailEnabled = (bool)($typeSettings['email'] ?? true);
        
        // Debug-Logging
        $logFile = __DIR__ . '/../logs/notifications.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logMessage = date('Y-m-d H:i:s') . " - createNotification: userId=$userId, typ=$typ, titel=$titel, systemEnabled=" . ($systemEnabled ? 'true' : 'false') . ", emailEnabled=" . ($emailEnabled ? 'true' : 'false') . PHP_EOL;
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log("createNotification: userId=$userId, typ=$typ, systemEnabled=" . ($systemEnabled ? 'true' : 'false') . ", emailEnabled=" . ($emailEnabled ? 'true' : 'false'));
        
        // Wenn System-Benachrichtigung deaktiviert ist, keine Benachrichtigung erstellen
        if (!$systemEnabled) {
            $logMessage = date('Y-m-d H:i:s') . " - createNotification: System-Benachrichtigung deaktiviert für userId=$userId, typ=$typ" . PHP_EOL;
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
            error_log("createNotification: System-Benachrichtigung deaktiviert für userId=$userId, typ=$typ");
            return false;
        }
        
        // Wenn Benutzer „eigene Benachrichtigungen ausblenden“ aktiviert hat und diese Benachrichtigung von ihm selbst ausgelöst wurde: nicht erstellen
        if ($createdByUserId !== null && (int) $createdByUserId === (int) $userId) {
            $stmtHideOwn = $pdo->prepare("
                SELECT setting_value FROM user_settings
                WHERE user_id = :user_id AND setting_key = 'notification_hide_own'
            ");
            $stmtHideOwn->execute([':user_id' => $userId]);
            $hideOwnRow = $stmtHideOwn->fetch(PDO::FETCH_ASSOC);
            if ($hideOwnRow && ($hideOwnRow['setting_value'] === '1' || $hideOwnRow['setting_value'] === 'true')) {
                $logMessage = date('Y-m-d H:i:s') . " - createNotification: Eigene Benachrichtigung ausgeblendet für userId=$userId, typ=$typ" . PHP_EOL;
                @file_put_contents($logFile, $logMessage, FILE_APPEND);
                return false;
            }
        }

        // Ticket-Aktivität drosseln: Verhindert Benachrichtigungsflut bei
        // "Ticket erstellt" + direkter Nachricht/Bild-Upload in kurzer Zeit.
        // Da Push/Desktop nur über createNotification ausgelöst werden, gilt
        // die Drosselung automatisch auch dafür.
        if ($referenzTyp === 'ticket' && !empty($referenzId)) {
            $dedupeWindowMinutes = 10;
            $ticketSpamTypes = [
                'ticket_nachricht',
                'ticket_comment',
                'ticket_created',
                'ticket_attachment_uploaded',
                'ticket_updated',
            ];
            if (in_array($typ, $ticketSpamTypes, true)) {
                $dedupePlaceholders = implode(',', array_fill(0, count($ticketSpamTypes), '?'));
                $dedupeSql = "
                    SELECT id, typ
                    FROM notifications
                    WHERE user_id = ?
                      AND referenz_typ = ?
                      AND referenz_id = ?
                      AND typ IN ($dedupePlaceholders)
                      AND erstellt_datum >= (NOW() - INTERVAL {$dedupeWindowMinutes} MINUTE)
                    ORDER BY erstellt_datum DESC
                    LIMIT 1
                ";
                $dedupeStmt = $pdo->prepare($dedupeSql);
                $dedupeParams = array_merge(
                    [$userId, $referenzTyp, (int)$referenzId],
                    $ticketSpamTypes
                );
                $dedupeStmt->execute($dedupeParams);
                $recentNotification = $dedupeStmt->fetch(PDO::FETCH_ASSOC);

                if ($recentNotification) {
                    $recentType = (string)($recentNotification['typ'] ?? 'unbekannt');
                    $logMessage = date('Y-m-d H:i:s') . " - createNotification gedrosselt: userId=$userId, typ=$typ, referenz_id=$referenzId, recent_typ=$recentType (innerhalb {$dedupeWindowMinutes} Min.)" . PHP_EOL;
                    @file_put_contents($logFile, $logMessage, FILE_APPEND);
                    return false;
                }
            }
        }
        
        // Prüfen ob created_by_user_id Spalte existiert
        $hasCreatedByUserId = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'created_by_user_id'");
            $hasCreatedByUserId = $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            $hasCreatedByUserId = false;
        }
        
        // System-Benachrichtigung erstellen
        if ($hasCreatedByUserId && $createdByUserId) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, created_by_user_id, typ, titel, nachricht, relevanz, link, referenz_typ, referenz_id, erstellt_datum)
                VALUES 
                (:user_id, :created_by_user_id, :typ, :titel, :nachricht, :relevanz, :link, :referenz_typ, :referenz_id, NOW())
            ");
            
            $success = $stmt->execute([
                ':user_id' => $userId,
                ':created_by_user_id' => $createdByUserId,
                ':typ' => $typ,
                ':titel' => $titel,
                ':nachricht' => $nachricht,
                ':relevanz' => $relevanz,
                ':link' => $link,
                ':referenz_typ' => $referenzTyp,
                ':referenz_id' => $referenzId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, typ, titel, nachricht, relevanz, link, referenz_typ, referenz_id, erstellt_datum)
                VALUES 
                (:user_id, :typ, :titel, :nachricht, :relevanz, :link, :referenz_typ, :referenz_id, NOW())
            ");
            
            $success = $stmt->execute([
                ':user_id' => $userId,
                ':typ' => $typ,
                ':titel' => $titel,
                ':nachricht' => $nachricht,
                ':relevanz' => $relevanz,
                ':link' => $link,
                ':referenz_typ' => $referenzTyp,
                ':referenz_id' => $referenzId
            ]);
        }
        
        if (!$success) {
            $errorInfo = $stmt->errorInfo();
            error_log("Fehler beim INSERT in notifications: " . print_r($errorInfo, true));
            return false;
        }
        
        $notificationId = $pdo->lastInsertId();
        
        if (!$notificationId) {
            $logMessage = date('Y-m-d H:i:s') . " - FEHLER: Keine Notification-ID erhalten nach INSERT. userId=$userId, typ=$typ" . PHP_EOL;
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
            error_log("Fehler: Keine Notification-ID erhalten nach INSERT. userId=$userId, typ=$typ");
            return false;
        }
        
        $logMessage = date('Y-m-d H:i:s') . " - SUCCESS: Benachrichtigung erstellt. ID=$notificationId, userId=$userId, typ=$typ, titel=$titel, emailEnabled=" . ($emailEnabled ? 'true' : 'false') . PHP_EOL;
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log("createNotification: Benachrichtigung erfolgreich erstellt. ID=$notificationId, userId=$userId, typ=$typ, emailEnabled=" . ($emailEnabled ? 'true' : 'false'));
        
        // E-Mail senden wenn aktiviert
        if ($sendEmail && $emailEnabled) {
            $logMessage = date('Y-m-d H:i:s') . " - Versende E-Mail für userId=$userId, typ=$typ, titel=$titel" . PHP_EOL;
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
            error_log("createNotification: Versende E-Mail für userId=$userId, typ=$typ");
            
            // Lade email.php falls noch nicht geladen
            if (!function_exists('sendEmail')) {
                require_once __DIR__ . '/email.php';
            }
            
            $emailSent = sendNotificationEmail($userId, $titel, $nachricht, $link, $typ, $referenzTyp, $referenzId);
            
            if ($emailSent) {
                $logMessage = date('Y-m-d H:i:s') . " - E-Mail erfolgreich versendet für userId=$userId, typ=$typ" . PHP_EOL;
                @file_put_contents($logFile, $logMessage, FILE_APPEND);
            } else {
                $logMessage = date('Y-m-d H:i:s') . " - FEHLER: E-Mail konnte nicht versendet werden für userId=$userId, typ=$typ" . PHP_EOL;
                @file_put_contents($logFile, $logMessage, FILE_APPEND);
                error_log("createNotification: E-Mail konnte nicht versendet werden für userId=$userId, typ=$typ");
            }
        } else {
            if (!$sendEmail) {
                $logMessage = date('Y-m-d H:i:s') . " - E-Mail nicht versendet: sendEmail=false für userId=$userId, typ=$typ" . PHP_EOL;
            } else {
                $logMessage = date('Y-m-d H:i:s') . " - E-Mail nicht versendet: emailEnabled=false für userId=$userId, typ=$typ" . PHP_EOL;
            }
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
        }

        if (function_exists('webpush_send_for_user')) {
            try {
                $pushEnabledStmt = $pdo->prepare("
                    SELECT setting_value
                    FROM user_settings
                    WHERE user_id = :user_id AND setting_key = 'push_notifications_enabled'
                ");
                $pushEnabledStmt->execute([':user_id' => $userId]);
                $pushEnabledRow = $pushEnabledStmt->fetch(PDO::FETCH_ASSOC);
                $pushEnabled = true;
                if ($pushEnabledRow && $pushEnabledRow['setting_value'] !== null) {
                    $pushEnabled = in_array($pushEnabledRow['setting_value'], ['1', 'true'], true);
                }
                if ($pushEnabled) {
                    webpush_send_for_user((int) $userId, $titel, $nachricht, $link);
                }
            } catch (Throwable $e) {
                error_log('webpush_send_for_user: ' . $e->getMessage());
            }
        }
        
        return $notificationId;
    } catch (PDOException $e) {
        error_log("Fehler beim Erstellen der Benachrichtigung: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    } catch (Exception $e) {
        error_log("Unerwarteter Fehler beim Erstellen der Benachrichtigung: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Sendet eine E-Mail-Benachrichtigung
 * Verwendet die verbesserte E-Mail-Funktion aus email.php
 * 
 * @param int $userId Benutzer-ID
 * @param string $titel Titel der E-Mail
 * @param string $nachricht Nachrichtentext
 * @param string|null $link Link zur relevanten Seite
 * @param string|null $notificationType Typ der Benachrichtigung
 * @param string|null $referenzTyp Typ des referenzierten Objekts (z.B. 'ticket', 'todo')
 * @param int|null $referenzId ID des referenzierten Objekts
 * @return bool Erfolg
 */
function sendNotificationEmail($userId, $titel, $nachricht, $link = null, $notificationType = null, $referenzTyp = null, $referenzId = null) {
    // Lade email.php falls noch nicht geladen
    if (!function_exists('sendEmail')) {
        require_once __DIR__ . '/email.php';
    }
    
    // Verwende die verbesserte sendNotificationEmailAdvanced Funktion aus email.php
    // Diese prüft auch die E-Mail-Einstellungen des Benutzers und unterstützt HTML
    if (function_exists('sendNotificationEmailAdvanced')) {
        return sendNotificationEmailAdvanced($userId, $titel, $nachricht, $link, true, $notificationType, $referenzTyp, $referenzId);
    }
    
    // Fallback auf alte Implementierung wenn email.php nicht verfügbar ist
    global $pdo;
    
    try {
        // Benutzer-E-Mail abrufen
        $stmt = $pdo->prepare("SELECT email, vorname, nachname FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['email'])) {
            return false;
        }
        
        // BASE_URL für Links
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
        $fullLink = $link ? $baseUrl . ltrim($link, '/') : null;
        
        // E-Mail-Inhalt erstellen
        $to = $user['email'];
        $subject = 'Benachrichtigung: ' . $titel;
        $name = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
        if (empty(trim($name))) {
            $name = $user['email'];
        }
        
        $message = "Hallo " . $name . ",\n\n";
        $message .= $nachricht . "\n\n";
        
        if ($fullLink) {
            $message .= "Link: " . $fullLink . "\n\n";
        }
        
        $message .= "Mit freundlichen Grüßen,\nIhr Serohub-Team";
        
        $headers = "From: Serohub <noreply@serviceportal.local>\r\n";
        $headers .= "Reply-To: noreply@serviceportal.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        if (!function_exists('logOutgoingMail')) {
            require_once __DIR__ . '/email.php';
        }
        $ok = mail($to, $subject, $message, $headers);
        if (function_exists('logOutgoingMail')) {
            logOutgoingMail($to, $subject, 'noreply@serviceportal.local', 'Benachrichtigung (Fallback)', $ok, $ok ? null : 'mail() false');
        }
        return $ok;
    } catch (PDOException $e) {
        error_log("Fehler beim Senden der E-Mail: " . $e->getMessage());
        return false;
    }
}

/**
 * Erstellt Benachrichtigungen für mehrere Benutzer
 * 
 * @param array $userIds Array von Benutzer-IDs
 * @param string $typ Typ der Benachrichtigung
 * @param string $titel Titel der Benachrichtigung
 * @param string $nachricht Nachrichtentext
 * @param string $relevanz Relevanz
 * @param string|null $link Link zur relevanten Seite
 * @param string|null $referenzTyp Typ des referenzierten Objekts
 * @param int|null $referenzId ID des referenzierten Objekts
 * @param int|null $createdByUserId ID des Benutzers, der die Benachrichtigung ausgelöst hat
 * @return array Array mit erstellten Benachrichtigungs-IDs
 */
function createNotificationsForUsers($userIds, $typ, $titel, $nachricht, $relevanz = 'normal', $link = null, $referenzTyp = null, $referenzId = null, $createdByUserId = null) {
    $notificationIds = [];
    
    $logFile = __DIR__ . '/../logs/notifications.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $userIdsStr = is_array($userIds) ? implode(',', $userIds) : 'KEIN_ARRAY';
    $logMessage = date('Y-m-d H:i:s') . " - createNotificationsForUsers aufgerufen: typ=$typ, titel=$titel, userIds=$userIdsStr" . PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    error_log("createNotificationsForUsers aufgerufen: typ=$typ, titel=$titel, userIds=$userIdsStr");
    
    if (empty($userIds) || !is_array($userIds)) {
        $logMessage = date('Y-m-d H:i:s') . " - WARNUNG: createNotificationsForUsers: Keine User-IDs oder kein Array übergeben" . PHP_EOL;
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log("createNotificationsForUsers: Keine User-IDs oder kein Array übergeben");
        return $notificationIds;
    }
    
    foreach ($userIds as $userId) {
        if (!is_numeric($userId) || $userId <= 0) {
            error_log("createNotificationsForUsers: Ungültige User-ID übersprungen: " . var_export($userId, true));
            continue;
        }
        
        $notificationId = createNotification($userId, $typ, $titel, $nachricht, $relevanz, $link, $referenzTyp, $referenzId, true, $createdByUserId);
        if ($notificationId) {
            $notificationIds[] = $notificationId;
        } else {
            error_log("createNotificationsForUsers: Fehler beim Erstellen der Benachrichtigung für userId=$userId");
        }
    }
    
    $logMessage = date('Y-m-d H:i:s') . " - createNotificationsForUsers: " . count($notificationIds) . " Benachrichtigungen erstellt von " . count($userIds) . " Usern" . PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    error_log("createNotificationsForUsers: " . count($notificationIds) . " Benachrichtigungen erstellt von " . count($userIds) . " Usern");
    
    return $notificationIds;
}

/**
 * Markiert eine Benachrichtigung als gelesen
 * 
 * @param int $notificationId Benachrichtigungs-ID
 * @param int $userId Benutzer-ID (zur Sicherheitsprüfung)
 * @return bool Erfolg
 */
function markNotificationAsRead($notificationId, $userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET ist_gelesen = 1, gelesen_datum = NOW() 
            WHERE id = :id AND user_id = :user_id
        ");
        
        return $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);
    } catch (PDOException $e) {
        error_log("Fehler beim Markieren der Benachrichtigung als gelesen: " . $e->getMessage());
        return false;
    }
}

/**
 * Markiert alle Benachrichtigungen eines Benutzers als gelesen
 * 
 * @param int $userId Benutzer-ID
 * @return bool Erfolg
 */
function markAllNotificationsAsRead($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET ist_gelesen = 1, gelesen_datum = NOW() 
            WHERE user_id = :user_id AND ist_gelesen = 0
        ");
        
        return $stmt->execute([':user_id' => $userId]);
    } catch (PDOException $e) {
        error_log("Fehler beim Markieren aller Benachrichtigungen als gelesen: " . $e->getMessage());
        return false;
    }
}

/**
 * Gibt die Anzahl ungelesener Benachrichtigungen eines Benutzers zurück
 * 
 * @param int $userId Benutzer-ID
 * @return int Anzahl ungelesener Benachrichtigungen
 */
function getUnreadNotificationCount($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = :user_id AND ist_gelesen = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['count'];
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der ungelesenen Benachrichtigungen: " . $e->getMessage());
        return 0;
    }
}

/**
 * Sammelt alle Benutzer-IDs, die eine Benachrichtigung erhalten sollen
 * 
 * @param int|null $createdByUserId ID des Benutzers, der die Aktion ausgeführt hat
 * @param int|null $companyId ID der Firma (optional, für Firmen-Admin Benachrichtigungen)
 * @return array Array von Benutzer-IDs (ohne Duplikate)
 */
function getNotificationRecipients($createdByUserId = null, $companyId = null) {
    global $pdo;
    
    $recipientIds = [];
    
    try {
        // 1. Benutzer, der die Aktion ausgeführt hat
        if ($createdByUserId && $createdByUserId > 0) {
            $recipientIds[] = (int)$createdByUserId;
        }
        
        // 2. Alle Admins
        $stmt = $pdo->query("SELECT id FROM users WHERE rolle = 'Admin' AND status = 'aktiv'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recipientIds[] = (int)$row['id'];
        }
        
        // 3. Alle Techniker
        $stmt = $pdo->query("SELECT id FROM users WHERE rolle = 'Techniker' AND status = 'aktiv'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recipientIds[] = (int)$row['id'];
        }
        
        // 4. Firmen-Admins (wenn companyId vorhanden)
        if ($companyId && $companyId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE rolle = 'Firmen-Admin' AND company_id = ? AND status = 'aktiv'");
            $stmt->execute([$companyId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recipientIds[] = (int)$row['id'];
            }
        }
        
        // Duplikate entfernen und Array neu indexieren
        $recipientIds = array_values(array_unique($recipientIds));
        
        return $recipientIds;
    } catch (PDOException $e) {
        error_log("Fehler beim Sammeln der Benachrichtigungsempfänger: " . $e->getMessage());
        // Fallback: nur createdByUserId wenn verfügbar
        return $createdByUserId ? [(int)$createdByUserId] : [];
    }
}

/**
 * Liefert Ticket-Empfänger (Ersteller, Zuweisung, Beobachter).
 * Wird verwendet, um Benachrichtigungen auf wirklich beteiligte Personen zu begrenzen.
 *
 * @param int $ticketId
 * @param int|null $excludeUserId Optional auszuschließender Benutzer (z. B. Auslöser)
 * @return array
 */
function getTicketNotificationRecipients($ticketId, $excludeUserId = null) {
    global $pdo;

    $ticketId = (int)$ticketId;
    if ($ticketId <= 0 || !isset($pdo)) {
        return [];
    }

    $recipientIds = [];
    try {
        // Admins und Techniker bekommen weiterhin alle Ticket-Benachrichtigungen.
        $allPrivilegedStmt = $pdo->query("
            SELECT id
            FROM users
            WHERE status = 'aktiv'
              AND rolle IN ('Admin', 'Techniker')
        ");
        while ($row = $allPrivilegedStmt->fetch(PDO::FETCH_ASSOC)) {
            $recipientIds[] = (int)$row['id'];
        }

        $ticketStmt = $pdo->prepare("
            SELECT erstellt_von, zugewiesen_an
            FROM tickets
            WHERE id = ?
            LIMIT 1
        ");
        $ticketStmt->execute([$ticketId]);
        $ticketRow = $ticketStmt->fetch(PDO::FETCH_ASSOC);
        if ($ticketRow) {
            if (!empty($ticketRow['erstellt_von'])) {
                $recipientIds[] = (int)$ticketRow['erstellt_von'];
            }
            if (!empty($ticketRow['zugewiesen_an'])) {
                $recipientIds[] = (int)$ticketRow['zugewiesen_an'];
            }
        }

        $obsStmt = $pdo->prepare("SELECT user_id FROM ticket_observers WHERE ticket_id = ?");
        $obsStmt->execute([$ticketId]);
        while ($row = $obsStmt->fetch(PDO::FETCH_ASSOC)) {
            $recipientIds[] = (int)$row['user_id'];
        }
    } catch (PDOException $e) {
        error_log("Fehler beim Sammeln der Ticket-Empfänger: " . $e->getMessage());
    }

    $recipientIds = array_values(array_unique(array_filter($recipientIds, function ($id) {
        return is_int($id) || ctype_digit((string)$id);
    })));
    if ($excludeUserId !== null) {
        $excludeUserId = (int)$excludeUserId;
        $recipientIds = array_values(array_filter($recipientIds, function ($id) use ($excludeUserId) {
            return (int)$id !== $excludeUserId;
        }));
    }
    return $recipientIds;
}

/**
 * Liefert bei privaten Todo-Ordnern die erlaubten Empfänger.
 * Gibt null zurück, wenn kein privater Kontext vorliegt.
 *
 * @param int|null $createdByUserId
 * @param string|null $referenzTyp
 * @param int|null $referenzId
 * @return array|null
 */
function getPrivateFolderRecipients($createdByUserId = null, $referenzTyp = null, $referenzId = null) {
    global $pdo;

    if (empty($referenzTyp) || empty($referenzId) || !is_numeric($referenzId)) {
        return null;
    }

    try {
        $folderId = null;

        if ($referenzTyp === 'todo') {
            $stmt = $pdo->prepare("
                SELECT t.folder_id
                FROM todos t
                WHERE t.id = :todo_id
                LIMIT 1
            ");
            $stmt->execute([':todo_id' => (int)$referenzId]);
            $folderId = $stmt->fetchColumn();
        } elseif ($referenzTyp === 'todo_folder') {
            $folderId = (int)$referenzId;
        } else {
            return null;
        }

        if (empty($folderId)) {
            return null;
        }

        $folderStmt = $pdo->prepare("
            SELECT id, erstellt_von, COALESCE(is_private, 0) as is_private
            FROM todo_folders
            WHERE id = :folder_id
            LIMIT 1
        ");
        $folderStmt->execute([':folder_id' => (int)$folderId]);
        $folder = $folderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$folder || (int)$folder['is_private'] !== 1) {
            return null;
        }

        $recipientIds = [];
        if (!empty($createdByUserId)) {
            $recipientIds[] = (int)$createdByUserId;
        }
        if (!empty($folder['erstellt_von'])) {
            $recipientIds[] = (int)$folder['erstellt_von'];
        }

        $memberStmt = $pdo->prepare("
            SELECT user_id
            FROM todo_folder_members
            WHERE folder_id = :folder_id
        ");
        $memberStmt->execute([':folder_id' => (int)$folderId]);
        while ($row = $memberStmt->fetch(PDO::FETCH_ASSOC)) {
            $recipientIds[] = (int)$row['user_id'];
        }

        return array_values(array_unique(array_filter($recipientIds)));
    } catch (PDOException $e) {
        error_log("Fehler beim Ermitteln privater Ordner-Empfänger: " . $e->getMessage());
        return null;
    }
}

/**
 * Erstellt Benachrichtigungen für alle relevanten Empfänger (Aktueller User, Admins, Techniker, Firmen-Admins)
 * 
 * @param int|null $createdByUserId ID des Benutzers, der die Aktion ausgeführt hat
 * @param int|null $companyId ID der Firma (optional)
 * @param string $typ Typ der Benachrichtigung
 * @param string $titel Titel der Benachrichtigung
 * @param string $nachricht Nachrichtentext
 * @param string $relevanz Relevanz ('niedrig', 'normal', 'hoch', 'kritisch')
 * @param string|null $link Link zur relevanten Seite
 * @param string|null $referenzTyp Typ des referenzierten Objekts
 * @param int|null $referenzId ID des referenzierten Objekts
 * @return array Array mit erstellten Benachrichtigungs-IDs
 */
function createNotificationsForAction($createdByUserId, $companyId, $typ, $titel, $nachricht, $relevanz = 'normal', $link = null, $referenzTyp = null, $referenzId = null) {
    $recipientIds = getPrivateFolderRecipients($createdByUserId, $referenzTyp, $referenzId);
    if ($recipientIds === null) {
        $recipientIds = getNotificationRecipients($createdByUserId, $companyId);
    }
    return createNotificationsForUsers($recipientIds, $typ, $titel, $nachricht, $relevanz, $link, $referenzTyp, $referenzId, $createdByUserId);
}
