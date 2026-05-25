<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__, 2) . '/assets/service_log_helper.php';

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
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
    if (empty($userName)) {
        $userName = 'Unbekannt';
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Upload-Verzeichnis
$uploadBaseDir = dirname(__DIR__, 2) . '/uploads/tickets/';
if (!file_exists($uploadBaseDir)) {
    mkdir($uploadBaseDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // Anhänge eines Tickets abrufen
            if (isset($_GET['ticket_id'])) {
                $ticketId = (int)$_GET['ticket_id'];
                
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
                
                // Beobachter dürfen Ticket-Anhänge sehen
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
                
                $stmt = $pdo->prepare("
                    SELECT 
                        id,
                        dateiname,
                        dateipfad,
                        dateigroesse,
                        mime_type,
                        erstellt_von,
                        erstellt_datum
                    FROM ticket_attachments
                    WHERE ticket_id = ?
                    ORDER BY erstellt_datum DESC
                ");
                $stmt->execute([$ticketId]);
                $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                service_log($pdo, $userId, 'ticket', $ticketId, 'viewed', null, null, null, 'Attachments API: Anhänge zu Ticket #' . $ticketId . ' abgerufen');
                echo json_encode([
                    'success' => true,
                    'attachments' => $attachments
                ]);
                exit;
            }
            
            // Einzelnen Anhang herunterladen
            if (isset($_GET['id'])) {
                $attachmentId = (int)$_GET['id'];
                
                // Anhang mit Ticket-Informationen abrufen
                $stmt = $pdo->prepare("
                    SELECT ta.*, t.erstellt_von as ticket_erstellt_von, t.company_id
                    FROM ticket_attachments ta
                    INNER JOIN tickets t ON ta.ticket_id = t.id
                    WHERE ta.id = ?
                ");
                $stmt->execute([$attachmentId]);
                $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$attachment) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Anhang nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $attachment['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($attachment['erstellt_von'] == $userId || $attachment['ticket_erstellt_von'] == $userId) {
                    $hasPermission = true;
                }
                
                // Beobachter dürfen Ticket-Anhänge herunterladen
                if (!$hasPermission) {
                    try {
                        $obsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                        $obsStmt->bindValue(':ticket_id', (int)$attachment['ticket_id'], PDO::PARAM_INT);
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
                
                $fullPath = dirname(__DIR__, 2) . '/' . $attachment['dateipfad'];
                
                if (!file_exists($fullPath)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Datei nicht gefunden']);
                    exit;
                }
                
                service_log($pdo, $userId, 'ticket', (int)$attachment['ticket_id'], 'viewed', null, null, null, 'Attachments API: Anhang #' . $attachmentId . ' heruntergeladen (Ticket #' . $attachment['ticket_id'] . ')');
                header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="' . basename($attachment['dateiname']) . '"');
                header('Content-Length: ' . filesize($fullPath));
                readfile($fullPath);
                exit;
            }
            
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ticket_id oder id erforderlich']);
            break;
            
        case 'POST':
            // Datei hochladen
            if (!isset($_POST['ticket_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ticket_id fehlt']);
                exit;
            }
            
            $ticketId = (int)$_POST['ticket_id'];
            
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
            
            // Beobachter dürfen Ticket-Anhänge hochladen
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
            
            if (!isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Datei hochgeladen']);
                exit;
            }
            
            $file = $_FILES['file'];
            
            // Datei-Validierung
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'Die Datei überschreitet die maximale Größe (php.ini)',
                    UPLOAD_ERR_FORM_SIZE => 'Die Datei überschreitet die maximale Größe (Formular)',
                    UPLOAD_ERR_PARTIAL => 'Die Datei wurde nur teilweise hochgeladen',
                    UPLOAD_ERR_NO_FILE => 'Keine Datei wurde hochgeladen',
                    UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
                    UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben der Datei',
                    UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt'
                ];
                $errorMsg = $errorMessages[$file['error']] ?? 'Unbekannter Upload-Fehler (Code: ' . $file['error'] . ')';
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                exit;
            }
            
            // Maximale Dateigröße: 10MB
            $maxFileSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxFileSize) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datei ist zu groß (max. 10MB)']);
                exit;
            }
            
            // Dateiname sicher machen
            $originalName = $file['name'];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $fileName = 'ticket_' . $ticketId . '_' . $safeName . '_' . time() . '.' . $extension;
            $filePath = $uploadBaseDir . $fileName;
            
            // Prüfen ob Verzeichnis beschreibbar ist
            if (!is_writable($uploadBaseDir)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Upload-Verzeichnis ist nicht beschreibbar']);
                exit;
            }
            
            // Datei speichern
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                http_response_code(500);
                error_log("Ticket Upload-Fehler: Konnte Datei nicht von " . $file['tmp_name'] . " nach " . $filePath . " verschieben");
                echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei. Bitte Berechtigungen prüfen.']);
                exit;
            }
            
            // Relativer Pfad für Datenbank
            $relativePath = 'uploads/tickets/' . $fileName;
            
            // MIME-Typ ermitteln
            $mimeType = $file['type'] ?: mime_content_type($filePath);
            
            // In Datenbank speichern
            $stmt = $pdo->prepare("
                INSERT INTO ticket_attachments (ticket_id, dateiname, dateipfad, dateigroesse, mime_type, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $ticketId,
                $originalName,
                $relativePath,
                $file['size'],
                $mimeType,
                $userId
            ]);
            
            $attachmentId = $pdo->lastInsertId();
            
            // Ticket geaendert_datum aktualisieren
            try {
                $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?")->execute([$ticketId]);
            } catch (PDOException $e) {
                error_log("Fehler beim Aktualisieren des Tickets nach Anhang-Upload: " . $e->getMessage());
            }
            
            // Ticket-Informationen für Benachrichtigung abrufen
            $ticketStmt = $pdo->prepare("SELECT titel, company_id FROM tickets WHERE id = ?");
            $ticketStmt->execute([$ticketId]);
            $ticketData = $ticketStmt->fetch(PDO::FETCH_ASSOC);
            $ticketTitel = $ticketData['titel'] ?? 'Unbekannt';
            $ticketCompanyId = $ticketData['company_id'] ?? null;
            
            // Benachrichtigungen nur an beteiligte Ticket-Nutzer
            $notifyUserIds = getTicketNotificationRecipients($ticketId, $userId);
            if (!empty($notifyUserIds)) {
                createNotificationsForUsers(
                    $notifyUserIds,
                    'ticket_attachment_uploaded',
                    'Anhang hochgeladen: ' . $originalName,
                    'Der Anhang "' . $originalName . '" wurde von ' . $userName . ' für den Ticket "' . $ticketTitel . '" hochgeladen.',
                    'niedrig',
                    'tickets/view.php?id=' . $ticketId,
                    'ticket',
                    $ticketId,
                    $userId
                );
            }
            
            service_log($pdo, $userId, 'ticket', $ticketId, 'created', null, null, null, 'Ticket-Anhang hochgeladen: ' . $originalName . ' (Ticket #' . $ticketId . ')');
            echo json_encode([
                'success' => true,
                'message' => 'Datei erfolgreich hochgeladen',
                'attachment' => [
                    'id' => $attachmentId,
                    'dateiname' => $originalName,
                    'dateipfad' => $relativePath,
                    'dateigroesse' => $file['size'],
                    'mime_type' => $mimeType
                ]
            ]);
            break;
            
        case 'DELETE':
            // Anhang löschen
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $attachmentId = (int)$_GET['id'];
            
            // Anhang mit Ticket-Informationen abrufen
            $stmt = $pdo->prepare("
                SELECT ta.*, t.titel, t.erstellt_von as ticket_erstellt_von, t.company_id
                FROM ticket_attachments ta
                INNER JOIN tickets t ON ta.ticket_id = t.id
                WHERE ta.id = ?
            ");
            $stmt->execute([$attachmentId]);
            $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attachment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Anhang nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $attachment['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($attachment['erstellt_von'] == $userId || $attachment['ticket_erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Datei löschen
            $fullPath = dirname(__DIR__, 2) . '/' . $attachment['dateipfad'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Aus Datenbank löschen
            $deleteStmt = $pdo->prepare("DELETE FROM ticket_attachments WHERE id = ?");
            $deleteStmt->execute([$attachmentId]);
            
            // Benachrichtigungen nur an beteiligte Ticket-Nutzer
            $notifyUserIds = getTicketNotificationRecipients((int)$attachment['ticket_id'], $userId);
            if (!empty($notifyUserIds)) {
                createNotificationsForUsers(
                    $notifyUserIds,
                    'ticket_attachment_deleted',
                    'Anhang gelöscht: ' . $attachment['dateiname'],
                    'Der Anhang "' . $attachment['dateiname'] . '" wurde von ' . $userName . ' für den Ticket "' . $attachment['titel'] . '" gelöscht.',
                    'normal',
                    'tickets/view.php?id=' . $attachment['ticket_id'],
                    'ticket',
                    $attachment['ticket_id'],
                    $userId
                );
            }
            
            service_log($pdo, $userId, 'ticket', (int)$attachment['ticket_id'], 'deleted', null, null, null, 'Ticket-Anhang #' . $attachmentId . ' gelöscht (Ticket #' . $attachment['ticket_id'] . ')');
            echo json_encode(['success' => true, 'message' => 'Anhang gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Ticket Attachments API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
