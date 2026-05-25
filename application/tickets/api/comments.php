<?php
// Output-Buffering aktivieren, um sicherzustellen, dass keine unerwartete Ausgabe die JSON-Antwort stört
ob_start();

session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__, 2) . '/assets/service_log_helper.php';

// Buffer leeren, falls etwas ausgegeben wurde
if (ob_get_level() > 0) {
    ob_clean();
}

header('Content-Type: application/json');

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Benutzerdaten und Rolle abrufen
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, vorname, nachname FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // Kommentare zu einem Ticket abrufen
            if (!isset($_GET['ticket_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ticket_id fehlt']);
                exit;
            }
            
            $ticketId = (int)$_GET['ticket_id'];
            
            // Debug-Logging (nur in Entwicklung)
            error_log("Comments API GET Request für Ticket ID: " . $ticketId . " von User ID: " . $userId);
            
            // Prüfen ob Ticket existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT erstellt_von, company_id FROM tickets WHERE id = ?");
            $checkStmt->execute([$ticketId]);
            $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($ticket['erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            // Beobachter dürfen Chat-Nachrichten sehen
            if (!$hasPermission) {
                try {
                    $obsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                    $obsStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
                    $obsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $obsStmt->execute();
                    if ($obsStmt->fetchColumn()) {
                        $hasPermission = true;
                    }
                } catch (PDOException $e) {
                    // Ignorieren
                }
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Roten-Punkt-Hervorhebung entfernen, sobald der Chat geladen wird (serverseitig, geräteübergreifend)
            try {
                $turTable = $pdo->query("SHOW TABLES LIKE 'ticket_unread_reminder'");
                if ($turTable && $turTable->rowCount() > 0) {
                    $pdo->prepare("DELETE FROM ticket_unread_reminder WHERE user_id = ? AND ticket_id = ?")->execute([$userId, $ticketId]);
                }
            } catch (PDOException $e) {
                // Ignorieren
            }
            
            // Todos-Verknüpfung: per comment_id (wenn Spalte vorhanden), sonst Fallback-Join
            $todoJoin = "LEFT JOIN todos t ON t.ticket_id = tc.ticket_id AND tc.nachrichtentyp = 'aufgabe'
                    AND t.beschreibung LIKE CONCAT('%', SUBSTRING(tc.kommentar, 1, 100), '%')
                    AND ABS(TIMESTAMPDIFF(MINUTE, t.erstellt_datum, tc.erstellt_datum)) <= 5";
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM todos LIKE 'comment_id'");
                if ($chk && $chk->rowCount() > 0) {
                    $todoJoin = "LEFT JOIN todos t ON t.comment_id = tc.id AND tc.nachrichtentyp = 'aufgabe'";
                }
            } catch (PDOException $e) { /* ignorieren */ }
            $sql = "
                SELECT 
                    tc.id,
                    tc.ticket_id,
                    tc.user_id,
                    tc.kommentar,
                    tc.nachrichtentyp,
                    tc.ist_intern,
                    tc.erstellt_datum,
                    u.vorname,
                    u.nachname,
                    u.email,
                    u.logopfad,
                    t.id as todo_id,
                    t.status as todo_status,
                    o.id as order_id,
                    o.bestellnummer,
                    o.status as order_status,
                    o.garantie as order_garantie,
                    o.beschreibung AS order_beschreibung,
                    o.notizen AS order_notizen
                FROM ticket_comments tc
                LEFT JOIN users u ON tc.user_id = u.id
                " . $todoJoin . "
                LEFT JOIN orders o ON o.comment_id = tc.id
                    AND tc.nachrichtentyp = 'bestellung'
                WHERE tc.ticket_id = :ticket_id
                ORDER BY tc.erstellt_datum ASC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($comments as &$comment) {
                $comment['order_inventar_consumable_id'] = null;
                if (!empty($comment['order_id'])) {
                    $orderBlob = trim((string)($comment['order_notizen'] ?? '') . "\n" . (string)($comment['order_beschreibung'] ?? ''));
                    if ($orderBlob !== '' && preg_match('/\[inventar_consumable_id=(\d+)\]/', $orderBlob, $invM)) {
                        $comment['order_inventar_consumable_id'] = (int)$invM[1];
                    }
                }
                unset($comment['order_beschreibung'], $comment['order_notizen']);
            }
            unset($comment);
            
            // Anhänge für jeden Kommentar holen
            foreach ($comments as &$comment) {
                try {
                    $attachmentStmt = $pdo->prepare("
                        SELECT id, dateiname, dateipfad, dateigroesse, mime_type, erstellt_datum
                        FROM comment_attachments
                        WHERE comment_id = ?
                        ORDER BY erstellt_datum ASC
                    ");
                    $attachmentStmt->execute([$comment['id']]);
                    $comment['attachments'] = $attachmentStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // Wenn die Tabelle nicht existiert oder ein anderer Fehler auftritt, leeres Array verwenden
                    $comment['attachments'] = [];
                }
            }
            
            // Alle Kommentare als gelesen markieren (nur wenn nicht vom aktuellen Benutzer)
            // Dies wird in einem separaten try-catch gemacht, damit es die Hauptantwort nicht blockiert
            try {
                // Zuerst prüfen, ob die Tabelle existiert
                $tableExists = false;
                try {
                    $tableCheck = $pdo->query("SHOW TABLES LIKE 'ticket_comment_reads'");
                    $tableExists = ($tableCheck && $tableCheck->rowCount() > 0);
                } catch (PDOException $e) {
                    // Tabelle existiert nicht
                    error_log("Tabelle ticket_comment_reads existiert nicht. Migration ausführen!");
                    $tableExists = false;
                }
                
                if ($tableExists) {
                    // Kommentare als gelesen markieren
                    // WICHTIG: :user_id wird mehrfach verwendet, daher müssen wir Platzhalter verwenden
                    $markReadStmt = $pdo->prepare("
                        INSERT IGNORE INTO ticket_comment_reads (comment_id, user_id, gelesen_datum)
                        SELECT tc.id, ?, NOW()
                        FROM ticket_comments tc
                        WHERE tc.ticket_id = ?
                        AND tc.user_id != ?
                        AND tc.ist_intern = 0
                        AND NOT EXISTS (
                            SELECT 1 FROM ticket_comment_reads tcr 
                            WHERE tcr.comment_id = tc.id AND tcr.user_id = ?
                        )
                    ");
                    $markReadStmt->execute([
                        $userId,      // 1. Platzhalter: user_id für INSERT
                        $ticketId,     // 2. Platzhalter: ticket_id für WHERE
                        $userId,       // 3. Platzhalter: user_id für != Vergleich
                        $userId        // 4. Platzhalter: user_id für NOT EXISTS
                    ]);
                    
                    // Prüfen, wie viele Kommentare tatsächlich markiert wurden
                    // (INSERT IGNORE gibt bei rowCount() nicht immer die richtige Anzahl zurück)
                    $checkStmt = $pdo->prepare("
                        SELECT COUNT(*) as count
                        FROM ticket_comment_reads tcr
                        INNER JOIN ticket_comments tc ON tcr.comment_id = tc.id
                        WHERE tc.ticket_id = ?
                        AND tcr.user_id = ?
                        AND tc.user_id != ?
                        AND tc.ist_intern = 0
                    ");
                    $checkStmt->execute([
                        $ticketId,
                        $userId,
                        $userId
                    ]);
                    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    $markedCount = $result['count'] ?? 0;
                    
                    error_log("Comments API: " . $markedCount . " Kommentare als gelesen markiert für Ticket " . $ticketId . " von User " . $userId);
                } else {
                    error_log("Comments API: Tabelle ticket_comment_reads existiert nicht. Kommentare können nicht als gelesen markiert werden.");
                }
            } catch (PDOException $e) {
                // Fehler beim Markieren loggen, aber nicht die Hauptantwort blockieren
                error_log("Fehler beim Markieren der Kommentare als gelesen (Ticket " . $ticketId . ", User " . $userId . "): " . $e->getMessage());
                error_log("SQL Error Code: " . $e->getCode());
                if (isset($e->errorInfo)) {
                    error_log("SQL State: " . ($e->errorInfo[0] ?? 'N/A'));
                    error_log("SQL Error Info: " . print_r($e->errorInfo, true));
                }
            }
            unset($comment);
            
            // Interne Kommentare für Kunden ausblenden
            if ($userRole === 'Kunde' || $userRole === 'Firmen-User') {
                $comments = array_filter($comments, function($comment) {
                    return $comment['ist_intern'] == 0;
                });
                $comments = array_values($comments);
            }
            
            // Debug-Logging (nur in Entwicklung)
            error_log("Comments API: " . count($comments) . " Kommentare gefunden für Ticket ID: " . $ticketId);
            
            service_log($pdo, $userId, 'ticket', $ticketId, 'viewed', null, null, null, 'Comments API: Kommentare zu Ticket #' . $ticketId . ' abgerufen');
            
            // JSON-Ausgabe mit Fehlerbehandlung
            $response = [
                'success' => true,
                'comments' => $comments
            ];
            
            // Sicherstellen, dass keine Ausgabe vorher stattgefunden hat
            if (ob_get_level() > 0) {
                ob_clean();
            }
            
            echo json_encode($response);
            exit; // Explizit beenden, um sicherzustellen, dass nichts mehr ausgegeben wird
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Roten Punkt anzeigen: serverseitige Hervorhebung (ohne ticket_comment_reads zu ändern)
            if (is_array($data) && isset($data['action']) && $data['action'] === 'mark_unread') {
                if (!isset($data['ticket_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ticket_id fehlt']);
                    exit;
                }
                $markUnreadTicketId = (int)$data['ticket_id'];
                if ($markUnreadTicketId <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige ticket_id']);
                    exit;
                }
                
                $muCheckStmt = $pdo->prepare("SELECT erstellt_von, company_id FROM tickets WHERE id = ?");
                $muCheckStmt->execute([$markUnreadTicketId]);
                $muTicket = $muCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$muTicket) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                    exit;
                }
                
                $muHasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $muHasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $muTicket['company_id'] == $userCompanyId) {
                    $muHasPermission = true;
                } elseif ($muTicket['erstellt_von'] == $userId) {
                    $muHasPermission = true;
                }
                
                if (!$muHasPermission) {
                    try {
                        $muObsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                        $muObsStmt->bindValue(':ticket_id', $markUnreadTicketId, PDO::PARAM_INT);
                        $muObsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                        $muObsStmt->execute();
                        if ($muObsStmt->fetchColumn()) {
                            $muHasPermission = true;
                        }
                    } catch (PDOException $e) {
                        // Ignorieren
                    }
                }
                
                if (!$muHasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                $muUnreadCount = 0;
                $muReminder = 0;
                try {
                    $muTurCheck = $pdo->query("SHOW TABLES LIKE 'ticket_unread_reminder'");
                    if (!$muTurCheck || $muTurCheck->rowCount() === 0) {
                        if (ob_get_level() > 0) {
                            ob_clean();
                        }
                        echo json_encode(['success' => true, 'unread_comments_count' => 0, 'unread_reminder' => 0]);
                        exit;
                    }
                    
                    $muIns = $pdo->prepare("
                        INSERT INTO ticket_unread_reminder (user_id, ticket_id, gesetzt_datum)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE gesetzt_datum = VALUES(gesetzt_datum)
                    ");
                    $muIns->execute([$userId, $markUnreadTicketId]);
                    $muReminder = 1;
                    
                    $muCountStmt = $pdo->prepare("
                        SELECT COUNT(*) AS cnt
                        FROM ticket_comments tc
                        LEFT JOIN ticket_comment_reads tcr ON tc.id = tcr.comment_id AND tcr.user_id = ?
                        WHERE tc.ticket_id = ?
                        AND tcr.id IS NULL
                        AND tc.user_id != ?
                        AND tc.ist_intern = 0
                    ");
                    $muTableReads = $pdo->query("SHOW TABLES LIKE 'ticket_comment_reads'");
                    if ($muTableReads && $muTableReads->rowCount() > 0) {
                        $muCountStmt->execute([$userId, $markUnreadTicketId, $userId]);
                        $muRow = $muCountStmt->fetch(PDO::FETCH_ASSOC);
                        $muUnreadCount = (int)($muRow['cnt'] ?? 0);
                    } else {
                        $muUnreadCount = 0;
                    }
                } catch (PDOException $e) {
                    error_log("mark_unread (reminder): " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
                    exit;
                }
                
                service_log($pdo, $userId, 'ticket', $markUnreadTicketId, 'updated', null, null, null, 'Roter-Punkt-Hervorhebung (Ticket #' . $markUnreadTicketId . ')');
                
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                echo json_encode([
                    'success' => true,
                    'unread_comments_count' => $muUnreadCount,
                    'unread_reminder' => $muReminder
                ]);
                exit;
            }
            
            // Hervorhebung entfernen + alle fremden, öffentlichen Kommentare als gelesen markieren (wie Chat öffnen)
            if (is_array($data) && isset($data['action']) && $data['action'] === 'clear_unread_reminder') {
                if (!isset($data['ticket_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ticket_id fehlt']);
                    exit;
                }
                $clrTicketId = (int)$data['ticket_id'];
                if ($clrTicketId <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige ticket_id']);
                    exit;
                }
                
                $clrCheckStmt = $pdo->prepare("SELECT erstellt_von, company_id FROM tickets WHERE id = ?");
                $clrCheckStmt->execute([$clrTicketId]);
                $clrTicket = $clrCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$clrTicket) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                    exit;
                }
                
                $clrHasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $clrHasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $clrTicket['company_id'] == $userCompanyId) {
                    $clrHasPermission = true;
                } elseif ($clrTicket['erstellt_von'] == $userId) {
                    $clrHasPermission = true;
                }
                
                if (!$clrHasPermission) {
                    try {
                        $clrObsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                        $clrObsStmt->bindValue(':ticket_id', $clrTicketId, PDO::PARAM_INT);
                        $clrObsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                        $clrObsStmt->execute();
                        if ($clrObsStmt->fetchColumn()) {
                            $clrHasPermission = true;
                        }
                    } catch (PDOException $e) {
                        // Ignorieren
                    }
                }
                
                if (!$clrHasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                $clrUnreadCount = 0;
                try {
                    $clrTurCheck = $pdo->query("SHOW TABLES LIKE 'ticket_unread_reminder'");
                    if ($clrTurCheck && $clrTurCheck->rowCount() > 0) {
                        $pdo->prepare("DELETE FROM ticket_unread_reminder WHERE user_id = ? AND ticket_id = ?")->execute([$userId, $clrTicketId]);
                    }
                    
                    $clrTableReads = $pdo->query("SHOW TABLES LIKE 'ticket_comment_reads'");
                    if ($clrTableReads && $clrTableReads->rowCount() > 0) {
                        $clrMarkReadStmt = $pdo->prepare("
                            INSERT IGNORE INTO ticket_comment_reads (comment_id, user_id, gelesen_datum)
                            SELECT tc.id, ?, NOW()
                            FROM ticket_comments tc
                            WHERE tc.ticket_id = ?
                            AND tc.user_id != ?
                            AND tc.ist_intern = 0
                            AND NOT EXISTS (
                                SELECT 1 FROM ticket_comment_reads tcr 
                                WHERE tcr.comment_id = tc.id AND tcr.user_id = ?
                            )
                        ");
                        $clrMarkReadStmt->execute([$userId, $clrTicketId, $userId, $userId]);
                    }
                    
                    $clrCountStmt = $pdo->prepare("
                        SELECT COUNT(*) AS cnt
                        FROM ticket_comments tc
                        LEFT JOIN ticket_comment_reads tcr ON tc.id = tcr.comment_id AND tcr.user_id = ?
                        WHERE tc.ticket_id = ?
                        AND tcr.id IS NULL
                        AND tc.user_id != ?
                        AND tc.ist_intern = 0
                    ");
                    if ($clrTableReads && $clrTableReads->rowCount() > 0) {
                        $clrCountStmt->execute([$userId, $clrTicketId, $userId]);
                        $clrRow = $clrCountStmt->fetch(PDO::FETCH_ASSOC);
                        $clrUnreadCount = (int)($clrRow['cnt'] ?? 0);
                    } else {
                        $clrUnreadCount = 0;
                    }
                } catch (PDOException $e) {
                    error_log("clear_unread_reminder: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
                    exit;
                }
                
                service_log($pdo, $userId, 'ticket', $clrTicketId, 'updated', null, null, null, 'Als gelesen (Kontextmenü, Ticket #' . $clrTicketId . ')');
                
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                echo json_encode([
                    'success' => true,
                    'unread_comments_count' => $clrUnreadCount,
                    'unread_reminder' => 0
                ]);
                exit;
            }
            
            // Neuen Kommentar erstellen
            if (!isset($data['ticket_id']) || !isset($data['kommentar'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ticket_id und kommentar sind erforderlich']);
                exit;
            }
            
            $ticketId = (int)$data['ticket_id'];
            $kommentar = trim($data['kommentar']);
            $nachrichtentyp = isset($data['nachrichtentyp']) ? $data['nachrichtentyp'] : 'nachricht';
            $istIntern = isset($data['ist_intern']) ? (int)$data['ist_intern'] : 0;
            $consumableId = isset($data['consumable_id']) ? (int)$data['consumable_id'] : null;
            
            // Validierung Nachrichtentyp
            $allowedTypes = ['nachricht', 'loesung', 'aufgabe', 'bestellung'];
            if (!in_array($nachrichtentyp, $allowedTypes)) {
                $nachrichtentyp = 'nachricht';
            }
            
            // Prüfen ob Ticket existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT erstellt_von, company_id, abgerechnet FROM tickets WHERE id = ?");
            $checkStmt->execute([$ticketId]);
            $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                exit;
            }
            
            // Prüfen ob Ticket abgerechnet ist - dann keine Kommentare mehr erlaubt
            if (isset($ticket['abgerechnet']) && ($ticket['abgerechnet'] == 1 || $ticket['abgerechnet'] === '1')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Zu abgerechneten Tickets können keine Kommentare mehr hinzugefügt werden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($ticket['erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            // Beobachter dürfen Chat-Nachrichten schreiben
            if (!$hasPermission) {
                try {
                    $obsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                    $obsStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
                    $obsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $obsStmt->execute();
                    if ($obsStmt->fetchColumn()) {
                        $hasPermission = true;
                    }
                } catch (PDOException $e) {
                    // Ignorieren
                }
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Kunden können keine internen Kommentare erstellen
            if (($userRole === 'Kunde' || $userRole === 'Firmen-User') && $istIntern) {
                $istIntern = 0;
            }
            
            // Ticket-Informationen für Benachrichtigungen abrufen
            $ticketInfoStmt = $pdo->prepare("
                SELECT titel, erstellt_von, zugewiesen_an, company_id, prioritaet 
                FROM tickets 
                WHERE id = ?
            ");
            $ticketInfoStmt->execute([$ticketId]);
            $ticketInfo = $ticketInfoStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kommentar einfügen
            $stmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, kommentar, nachrichtentyp, ist_intern, erstellt_datum)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$ticketId, $userId, $kommentar, $nachrichtentyp, $istIntern]);
            
            $commentId = $pdo->lastInsertId();
            
            // geaendert_datum beim Ticket aktualisieren
            try {
                $updateStmt = $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?");
                $updateStmt->execute([$ticketId]);
            } catch (PDOException $e) {
                error_log("Fehler beim Aktualisieren des geaendert_datum: " . $e->getMessage());
            }
            
            // Prüfen ob bereits eine Lösung existiert (vor diesem Kommentar)
            $hasSolution = false;
            try {
                $solutionCheckStmt = $pdo->prepare("SELECT id FROM ticket_comments WHERE ticket_id = ? AND nachrichtentyp = 'loesung' AND id != ? LIMIT 1");
                $solutionCheckStmt->execute([$ticketId, $commentId]);
                $hasSolution = $solutionCheckStmt->fetch() !== false;
            } catch (PDOException $e) {
                error_log("Fehler beim Prüfen auf vorhandene Lösung: " . $e->getMessage());
            }
            
            // Automatischer Status-Update basierend auf Nachrichtentyp
            if ($nachrichtentyp === 'loesung') {
                try {
                    // Firma mit Wartungsvertrag: geschlossene Tickets direkt auf abgerechnet setzen (nur wenn Ticket keinem Projekt zugeordnet)
                    $companyIdForWartung = $ticketInfo ? (int)($ticketInfo['company_id'] ?? 0) : 0;
                    $setAbgerechnet = '';
                    $ticketHasProject = false;
                    try {
                        $ptStmt = $pdo->prepare("SELECT 1 FROM project_tickets WHERE ticket_id = ? LIMIT 1");
                        $ptStmt->execute([$ticketId]);
                        $ticketHasProject = (bool) $ptStmt->fetch();
                    } catch (PDOException $e) { /* project_tickets kann fehlen */ }
                    if (!$ticketHasProject && $companyIdForWartung) {
                        $wStmt = $pdo->prepare("SELECT hat_wartungsvertrag FROM companies WHERE id = ? LIMIT 1");
                        $wStmt->execute([$companyIdForWartung]);
                        $cRow = $wStmt->fetch(PDO::FETCH_ASSOC);
                        if ($cRow && ($cRow['hat_wartungsvertrag'] == 1 || $cRow['hat_wartungsvertrag'] === '1')) {
                            $setAbgerechnet = ', abgerechnet = 1';
                        }
                    }
                    $closeStatus = $setAbgerechnet ? 'Archiv' : 'Geschlossen';
                    $statusUpdateStmt = $pdo->prepare("UPDATE tickets SET status = ?, geaendert_datum = NOW(), abgeschlossen_datum = NOW()" . $setAbgerechnet . " WHERE id = ?");
                    $statusUpdateStmt->execute([$closeStatus, $ticketId]);
                    // Alle Bestellungen des Tickets auf "Angekommen" setzen
                    try {
                        $selOrders = $pdo->prepare("SELECT id, status FROM orders WHERE ticket_id = ?");
                        $selOrders->execute([$ticketId]);
                        $ordersBefore = $selOrders->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($ordersBefore)) {
                            $updOrders = $pdo->prepare("UPDATE orders SET status = 'Angekommen' WHERE ticket_id = ?");
                            $updOrders->execute([$ticketId]);
                            $hasBemerkungCol = false;
                            try {
                                $chk = $pdo->query("SHOW COLUMNS FROM order_status_history LIKE 'bemerkung'");
                                $hasBemerkungCol = $chk && $chk->rowCount() > 0;
                            } catch (PDOException $e) { /* Spalte fehlt */ }
                            if ($hasBemerkungCol) {
                                $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, geaendert_von, bemerkung) VALUES (?, 'Angekommen', ?, 'Ticket geschlossen')");
                            } else {
                                $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, geaendert_von) VALUES (?, 'Angekommen', ?)");
                            }
                            $logStmt = $pdo->prepare("INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, beschreibung, erstellt_datum) VALUES ('order', ?, ?, 'updated', 'status', ?, 'Angekommen', 'Automatisch gesetzt (Ticket geschlossen)', NOW())");
                            foreach ($ordersBefore as $row) {
                                $oid = (int)$row['id'];
                                $histStmt->execute([$oid, $userId]);
                                $logStmt->execute([$oid, $userId, $row['status'] ?? '']);
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Fehler beim Setzen der Bestellungen auf Angekommen (Ticket geschlossen): " . $e->getMessage());
                    }
                } catch (PDOException $e) {
                    error_log("Fehler beim Aktualisieren des Status auf 'Geschlossen': " . $e->getMessage());
                }
            } elseif ($nachrichtentyp === 'bestellung') {
                // Wenn Bestellung geschrieben wird, Status auf "Bestellung offen" setzen
                try {
                    $statusUpdateStmt = $pdo->prepare("UPDATE tickets SET status = 'Bestellung offen', geaendert_datum = NOW() WHERE id = ?");
                    $statusUpdateStmt->execute([$ticketId]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Aktualisieren des Status auf 'Bestellung offen': " . $e->getMessage());
                }
            } elseif ($hasSolution && ($nachrichtentyp === 'nachricht' || $nachrichtentyp === 'aufgabe' || $nachrichtentyp === 'bestellung')) {
                // Wenn bereits eine Lösung existiert und ein neuer Kommentar (Nachricht, Aufgabe oder Bestellung) geschrieben wird, Status auf "Neu" setzen
                try {
                    $statusUpdateStmt = $pdo->prepare("UPDATE tickets SET status = 'Neu', geaendert_datum = NOW() WHERE id = ?");
                    $statusUpdateStmt->execute([$ticketId]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Aktualisieren des Status auf 'Neu' nach Lösung: " . $e->getMessage());
                }
            }
            
            // Kommentar-ID in Antwort zurückgeben
            $response = [
                'success' => true,
                'comment_id' => $commentId,
                'message' => 'Kommentar erfolgreich erstellt'
            ];
            
            // Wenn es eine Aufgabe ist, automatisch ein Todo erstellen (mit comment_id für Abhaken im Chat)
            if ($nachrichtentyp === 'aufgabe') {
                try {
                    $ticketInfoForTodo = $pdo->prepare("SELECT company_id FROM tickets WHERE id = ?");
                    $ticketInfoForTodo->execute([$ticketId]);
                    $ticketForTodo = $ticketInfoForTodo->fetch(PDO::FETCH_ASSOC);
                    $companyIdForTodo = $ticketForTodo ? $ticketForTodo['company_id'] : null;
                    $aufgabeText = trim(strip_tags($kommentar));
                    if ($aufgabeText === '') {
                        $aufgabeText = 'Aufgabe aus Ticket #' . $ticketId;
                    }
                    $todoTitel = $aufgabeText;
                    $hasCommentId = false;
                    try {
                        $chk = $pdo->query("SHOW COLUMNS FROM todos LIKE 'comment_id'");
                        $hasCommentId = $chk && $chk->rowCount() > 0;
                    } catch (PDOException $e) { /* Spalte fehlt */ }
                    if ($hasCommentId) {
                        $todoStmt = $pdo->prepare("
                            INSERT INTO todos (titel, beschreibung, status, prioritaet, company_id, ticket_id, comment_id, erstellt_von, erstellt_datum)
                            VALUES (?, ?, 'offen', 'normal', ?, ?, ?, ?, NOW())
                        ");
                        $todoStmt->execute([$todoTitel, null, $companyIdForTodo, $ticketId, $commentId, $userId]);
                    } else {
                        $todoStmt = $pdo->prepare("
                            INSERT INTO todos (titel, beschreibung, status, prioritaet, company_id, ticket_id, erstellt_von, erstellt_datum)
                            VALUES (?, ?, 'offen', 'normal', ?, ?, ?, NOW())
                        ");
                        $todoStmt->execute([$todoTitel, null, $companyIdForTodo, $ticketId, $userId]);
                    }
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Todos aus Aufgabe: " . $e->getMessage());
                }
            }
            
            // Wenn es eine Bestellung ist, automatisch eine Bestellung erstellen
            if ($nachrichtentyp === 'bestellung') {
                try {
                    // Ticket-Informationen für company_id und customer_id abrufen
                    $ticketInfoForOrder = $pdo->prepare("SELECT company_id, customer_id FROM tickets WHERE id = ?");
                    $ticketInfoForOrder->execute([$ticketId]);
                    $ticketForOrder = $ticketInfoForOrder->fetch(PDO::FETCH_ASSOC);
                    $companyIdForOrder = $ticketForOrder ? $ticketForOrder['company_id'] : null;
                    $customerIdForOrder = $ticketForOrder ? $ticketForOrder['customer_id'] : null;
                    
                    // Bestellnummer generieren
                    $bestellnummer = 'BEST-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Prüfen ob Bestellnummer bereits existiert
                    $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE bestellnummer = ?");
                    $checkStmt->execute([$bestellnummer]);
                    $counter = 1;
                    while ($checkStmt->fetch()) {
                        $bestellnummer = 'BEST-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $checkStmt->execute([$bestellnummer]);
                        $counter++;
                        if ($counter > 100) break; // Sicherheitsabfrage
                    }
                    
                    // Beschreibung: Kommentar-Text oder Link-Text aus Kommentar (ohne Präfix)
                    $beschreibung = $kommentar;
                    if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $kommentar, $matches)) {
                        $beschreibung = trim($matches[2]);
                    }
                    if ($beschreibung === '') {
                        $beschreibung = 'Bestellung aus Ticket #' . $ticketId;
                    }
                    
                    // Verbrauchsmaterial aus Lager: Metadaten für Status und erweiterte Beschreibung
                    $consumableMeta = null;
                    if ($consumableId > 0) {
                        $consumableSelectAttempts = [
                            'SELECT COALESCE(lagerbestand, 0) AS lagerbestand, artikelnummer, ean, bezeichnung FROM consumables WHERE id = ? LIMIT 1',
                            'SELECT COALESCE(lagerbestand, 0) AS lagerbestand, artikelnummer, bezeichnung FROM consumables WHERE id = ? LIMIT 1',
                            'SELECT artikelnummer, bezeichnung FROM consumables WHERE id = ? LIMIT 1',
                        ];
                        foreach ($consumableSelectAttempts as $cSql) {
                            try {
                                $cStmt = $pdo->prepare($cSql);
                                $cStmt->execute([$consumableId]);
                                $consumableMeta = $cStmt->fetch(PDO::FETCH_ASSOC);
                                if ($consumableMeta) {
                                    break;
                                }
                            } catch (PDOException $e) {
                                $consumableMeta = null;
                            }
                        }
                    }
                    
                    // Initialer Bestellstatus: bei Verbrauchsmaterial mit Lagerbestand → "Im Lager", sonst "Neu"
                    $initialOrderStatus = 'Neu';
                    if ($consumableMeta !== null && array_key_exists('lagerbestand', $consumableMeta) && (int)$consumableMeta['lagerbestand'] > 0) {
                        $initialOrderStatus = 'Im Lager';
                    }
                    
                    // Beschreibung um Lagerinfos ergänzen (Ticket-Bestellung aus dem Lager)
                    if ($consumableMeta !== null) {
                        $lagerZeilen = [];
                        if (array_key_exists('lagerbestand', $consumableMeta)) {
                            $lagerZeilen[] = 'Lagerbestand: ' . (int)$consumableMeta['lagerbestand'];
                        }
                        if (!empty($consumableMeta['ean'])) {
                            $lagerZeilen[] = 'EAN: ' . trim((string)$consumableMeta['ean']);
                        }
                        if (!empty($consumableMeta['artikelnummer'])) {
                            $lagerZeilen[] = 'Artikelnummer: ' . trim((string)$consumableMeta['artikelnummer']);
                        }
                        if (!empty($lagerZeilen)) {
                            $beschreibung = trim($beschreibung) . "\n\n" . implode("\n", $lagerZeilen);
                        }
                    }
                    if ($consumableId > 0) {
                        $beschreibung = trim($beschreibung) . "\n[inventar_consumable_id=" . (int)$consumableId . "]";
                    }
                    
                    // Sicherstellen, dass userId ein Integer ist
                    $userIdInt = (int)$userId;
                    if ($userIdInt <= 0) {
                        error_log("Fehler: Ungültige userId beim Erstellen der Bestellung: " . $userId);
                        throw new Exception("Ungültige Benutzer-ID");
                    }
                    
                    $orderGarantie = (!empty($data['garantie']) && $data['garantie'] !== '0' && $data['garantie'] !== 0 && $data['garantie'] !== 'false') ? 1 : 0;
                    $hasOrderGarantieCol = false;
                    try {
                        $ogc = $pdo->query("SHOW COLUMNS FROM orders LIKE 'garantie'");
                        $hasOrderGarantieCol = $ogc && $ogc->rowCount() > 0;
                    } catch (PDOException $e) { /* ignore */ }
                    if ($hasOrderGarantieCol) {
                        $orderStmt = $pdo->prepare("
                            INSERT INTO orders (bestellnummer, beschreibung, status, garantie, company_id, customer_id, ticket_id, comment_id, erstellt_von, erstellt_datum)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $orderStmt->execute([
                            $bestellnummer,
                            $beschreibung,
                            $initialOrderStatus,
                            $orderGarantie,
                            $companyIdForOrder,
                            $customerIdForOrder,
                            $ticketId,
                            $commentId,
                            $userIdInt
                        ]);
                    } else {
                        $orderStmt = $pdo->prepare("
                            INSERT INTO orders (bestellnummer, beschreibung, status, company_id, customer_id, ticket_id, comment_id, erstellt_von, erstellt_datum)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $orderStmt->execute([
                            $bestellnummer,
                            $beschreibung,
                            $initialOrderStatus,
                            $companyIdForOrder,
                            $customerIdForOrder,
                            $ticketId,
                            $commentId,
                            $userIdInt
                        ]);
                    }
                    $orderId = $pdo->lastInsertId();
                    
                    // Initialen Status in History speichern
                    try {
                        $historyStmt = $pdo->prepare("
                            INSERT INTO order_status_history (order_id, status, geaendert_von, geaendert_datum)
                            VALUES (?, ?, ?, NOW())
                        ");
                        $historyStmt->execute([$orderId, $initialOrderStatus, $userIdInt]);
                    } catch (PDOException $e) {
                        error_log("Fehler beim Erstellen der Status-Historie: " . $e->getMessage());
                    }
                    
                    // Bei Status "Im Lager": Ticket auf "Neu" setzen (wie bei Statusänderung in orders API)
                    if ($initialOrderStatus === 'Im Lager' && $ticketId) {
                        try {
                            $ticketUpdateStmt = $pdo->prepare("UPDATE tickets SET status = 'Neu', geaendert_datum = NOW() WHERE id = ?");
                            $ticketUpdateStmt->execute([$ticketId]);
                        } catch (PDOException $e) {
                            error_log("Fehler beim Aktualisieren des Ticket-Status auf 'Neu': " . $e->getMessage());
                        }
                    }
                    
                    // Log-Eintrag für Erstellung
                    try {
                        $logStmt = $pdo->prepare("
                            INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                            VALUES ('order', ?, ?, 'created', NULL, NULL, NULL, NOW())
                        ");
                        $logStmt->execute([$orderId, $userIdInt]);
                    } catch (PDOException $e) {
                        error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                    }
                    
                    // Benachrichtigungen erstellen
                    try {
                        $userName = ($user && $user['vorname'] && $user['nachname']) 
                            ? trim($user['vorname'] . ' ' . $user['nachname']) 
                            : 'Ein Benutzer';
                        
                        createNotificationsForAction(
                            $userIdInt,
                            $companyIdForOrder,
                            'order_created',
                            'Neue Bestellung erstellt: ' . $bestellnummer,
                            'Eine neue Bestellung "' . $bestellnummer . '" wurde von ' . $userName . ' aus Ticket #' . $ticketId . ' erstellt.',
                            'hoch',
                            'orders/detail.php?id=' . $orderId,
                            'order',
                            $orderId
                        );
                    } catch (Exception $e) {
                        error_log("Fehler beim Erstellen der Benachrichtigungen: " . $e->getMessage());
                    }
                    
                    // Bestellungs-ID zur Antwort hinzufügen
                    if (isset($response)) {
                        $response['order_id'] = $orderId;
                        $response['bestellnummer'] = $bestellnummer;
                    }
                } catch (PDOException $e) {
                    // Fehler beim Erstellen der Bestellung loggen, aber Kommentar trotzdem als erfolgreich behandeln
                    error_log("Fehler beim Erstellen der Bestellung aus Kommentar: " . $e->getMessage());
                }
            }
            
            // Benachrichtigungen für relevante Benutzer erstellen (nur wenn nicht intern)
            if (!$istIntern && $ticketInfo) {
                // Relevanz basierend auf Priorität bestimmen
                $relevanz = 'normal';
                if ($ticketInfo['prioritaet'] === 'kritisch') {
                    $relevanz = 'kritisch';
                } elseif ($ticketInfo['prioritaet'] === 'hoch') {
                    $relevanz = 'hoch';
                }
                
                $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
                if (empty($userName)) {
                    $userName = 'Unbekannt';
                }
                
                $previewText = substr($kommentar, 0, 100) . (strlen($kommentar) > 100 ? '...' : '');
                
                // Beobachter-IDs abrufen
                $observerIds = [];
                try {
                    $observerStmt = $pdo->prepare("SELECT user_id FROM ticket_observers WHERE ticket_id = ?");
                    $observerStmt->execute([$ticketId]);
                    while ($row = $observerStmt->fetch(PDO::FETCH_ASSOC)) {
                        $observerIds[] = (int)$row['user_id'];
                    }
                } catch (PDOException $e) {
                    error_log("Fehler beim Abrufen der Beobachter: " . $e->getMessage());
                }
                
                // Benachrichtigungen nur an beteiligte Ticket-Nutzer
                $notifyUserIds = getTicketNotificationRecipients($ticketId, $userId);
                
                if (!empty($notifyUserIds)) {
                    createNotificationsForUsers(
                        $notifyUserIds,
                        'ticket_comment',
                        'Neue Nachricht im Ticket: ' . $ticketInfo['titel'],
                        $userName . ' hat eine neue Nachricht im Ticket "' . $ticketInfo['titel'] . '" hinzugefügt: ' . $previewText,
                        $relevanz,
                        'tickets/view.php?id=' . $ticketId,
                        'ticket',
                        $ticketId,
                        $userId
                    );
                }
            }
            
            service_log($pdo, $userId, 'ticket', $ticketId, 'created', null, null, null, 'Kommentar erstellt (Typ: ' . $nachrichtentyp . ') zu Ticket #' . $ticketId);
            echo json_encode($response);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Comments API PDOException: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    // Sicherstellen, dass keine Ausgabe vorher stattgefunden hat
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Comments API Exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    // Sicherstellen, dass keine Ausgabe vorher stattgefunden hat
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'error' => 'Serverfehler: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    http_response_code(500);
    error_log("Comments API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    // Sicherstellen, dass keine Ausgabe vorher stattgefunden hat
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'error' => 'PHP-Fehler: ' . $e->getMessage()]);
    exit;
}
