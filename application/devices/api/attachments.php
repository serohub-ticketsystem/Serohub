<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';

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
$uploadBaseDir = dirname(__DIR__, 2) . '/uploads/devices/';
if (!file_exists($uploadBaseDir)) {
    mkdir($uploadBaseDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // Anhänge eines Geräts abrufen
            if (isset($_GET['device_id'])) {
                $deviceId = (int)$_GET['device_id'];
                $includeTicketAttachments = isset($_GET['include_ticket_attachments']) && $_GET['include_ticket_attachments'] === '1';
                
                // Prüfen ob Gerät existiert und Berechtigung
                $checkStmt = $pdo->prepare("SELECT company_id, customer_id, user_id FROM devices WHERE id = ?");
                $checkStmt->execute([$deviceId]);
                $device = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$device) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Gerät nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $device['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-User' && $device['user_id'] == $userId) {
                    $hasPermission = true;
                }
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                // Geräte-Anhänge laden
                $stmt = $pdo->prepare("
                    SELECT 
                        id,
                        device_id,
                        dateiname,
                        dateipfad,
                        link_url,
                        link_titel,
                        anhang_typ,
                        dateigroesse,
                        mime_type,
                        erstellt_von,
                        erstellt_datum
                    FROM device_attachments
                    WHERE device_id = ?
                    ORDER BY erstellt_datum DESC
                ");
                $stmt->execute([$deviceId]);
                $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Ticket-Anhänge laden, wenn gewünscht
                $ticketAttachments = [];
                if ($includeTicketAttachments) {
                    // Tickets mit diesem Gerät finden
                    $ticketsStmt = $pdo->prepare("
                        SELECT t.id, t.ticket_nummer, t.titel
                        FROM tickets t
                        WHERE t.device_id = ?
                    ");
                    $ticketsStmt->execute([$deviceId]);
                    $tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($tickets)) {
                        $ticketIds = array_column($tickets, 'id');
                        $ticketMap = [];
                        foreach ($tickets as $ticket) {
                            $ticketMap[$ticket['id']] = $ticket;
                        }
                        
                        if (!empty($ticketIds)) {
                            $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
                            $ticketAttachmentsStmt = $pdo->prepare("
                                SELECT 
                                    ta.id,
                                    ta.ticket_id,
                                    ta.dateiname,
                                    ta.dateipfad,
                                    ta.dateigroesse,
                                    ta.mime_type,
                                    ta.erstellt_von,
                                    ta.erstellt_datum
                                FROM ticket_attachments ta
                                WHERE ta.ticket_id IN ($placeholders)
                                ORDER BY ta.erstellt_datum DESC
                            ");
                            $ticketAttachmentsStmt->execute($ticketIds);
                            $rawTicketAttachments = $ticketAttachmentsStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Ticket-Informationen hinzufügen
                            foreach ($rawTicketAttachments as $att) {
                                $ticket = $ticketMap[$att['ticket_id']] ?? null;
                                if ($ticket) {
                                    $ticketAttachments[] = [
                                        'id' => $att['id'],
                                        'dateiname' => $att['dateiname'],
                                        'dateipfad' => $att['dateipfad'],
                                        'dateigroesse' => $att['dateigroesse'],
                                        'mime_type' => $att['mime_type'],
                                        'erstellt_von' => $att['erstellt_von'],
                                        'erstellt_datum' => $att['erstellt_datum'],
                                        'ticket_id' => $att['ticket_id'],
                                        'ticket_nummer' => $ticket['ticket_nummer'],
                                        'ticket_titel' => $ticket['titel'],
                                        'is_ticket_attachment' => true
                                    ];
                                }
                            }
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'attachments' => $attachments,
                    'ticket_attachments' => $ticketAttachments
                ]);
                exit;
            }
            
            // Einzelnen Anhang herunterladen (nur für Dateien)
            if (isset($_GET['id'])) {
                $attachmentId = (int)$_GET['id'];
                
                // Anhang mit Geräte-Informationen abrufen
                $stmt = $pdo->prepare("
                    SELECT da.*, d.company_id, d.customer_id, d.user_id
                    FROM device_attachments da
                    INNER JOIN devices d ON da.device_id = d.id
                    WHERE da.id = ?
                ");
                $stmt->execute([$attachmentId]);
                $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$attachment) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Anhang nicht gefunden']);
                    exit;
                }
                
                // Nur Dateien können heruntergeladen werden
                if ($attachment['anhang_typ'] !== 'datei') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nur Dateien können heruntergeladen werden']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $attachment['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-User' && $attachment['user_id'] == $userId) {
                    $hasPermission = true;
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
                
                header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="' . basename($attachment['dateiname']) . '"');
                header('Content-Length: ' . filesize($fullPath));
                readfile($fullPath);
                exit;
            }
            
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'device_id oder id erforderlich']);
            break;
            
        case 'POST':
            // Anhang hinzufügen (Datei oder Link)
            if (!isset($_POST['device_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'device_id fehlt']);
                exit;
            }
            
            $deviceId = (int)$_POST['device_id'];
            $anhangTyp = isset($_POST['anhang_typ']) ? $_POST['anhang_typ'] : 'datei';
            
            // Prüfen ob Gerät existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT company_id, customer_id, user_id FROM devices WHERE id = ?");
            $checkStmt->execute([$deviceId]);
            $device = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Gerät nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $device['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-User' && $device['user_id'] == $userId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            if ($anhangTyp === 'link') {
                // Link hinzufügen
                if (!isset($_POST['link_url']) || empty(trim($_POST['link_url']))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'link_url fehlt']);
                    exit;
                }
                
                $linkUrl = trim($_POST['link_url']);
                $linkTitel = isset($_POST['link_titel']) ? trim($_POST['link_titel']) : null;
                
                // URL validieren
                if (!filter_var($linkUrl, FILTER_VALIDATE_URL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige URL']);
                    exit;
                }
                
                // In Datenbank speichern
                $stmt = $pdo->prepare("
                    INSERT INTO device_attachments (device_id, link_url, link_titel, anhang_typ, erstellt_von, erstellt_datum)
                    VALUES (?, ?, ?, 'link', ?, NOW())
                ");
                $stmt->execute([
                    $deviceId,
                    $linkUrl,
                    $linkTitel,
                    $userId
                ]);
                
                $attachmentId = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Link erfolgreich hinzugefügt',
                    'attachment' => [
                        'id' => $attachmentId,
                        'link_url' => $linkUrl,
                        'link_titel' => $linkTitel,
                        'anhang_typ' => 'link'
                    ]
                ]);
            } else {
                // Datei hochladen
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
                $fileName = 'device_' . $deviceId . '_' . $safeName . '_' . time() . '.' . $extension;
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
                    error_log("Device Upload-Fehler: Konnte Datei nicht von " . $file['tmp_name'] . " nach " . $filePath . " verschieben");
                    echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei. Bitte Berechtigungen prüfen.']);
                    exit;
                }
                
                // Relativer Pfad für Datenbank
                $relativePath = 'uploads/devices/' . $fileName;
                
                // MIME-Typ ermitteln
                $mimeType = $file['type'] ?: mime_content_type($filePath);
                
                // In Datenbank speichern
                $stmt = $pdo->prepare("
                    INSERT INTO device_attachments (device_id, dateiname, dateipfad, anhang_typ, dateigroesse, mime_type, erstellt_von, erstellt_datum)
                    VALUES (?, ?, ?, 'datei', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $deviceId,
                    $originalName,
                    $relativePath,
                    $file['size'],
                    $mimeType,
                    $userId
                ]);
                
                $attachmentId = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Datei erfolgreich hochgeladen',
                    'attachment' => [
                        'id' => $attachmentId,
                        'dateiname' => $originalName,
                        'dateipfad' => $relativePath,
                        'dateigroesse' => $file['size'],
                        'mime_type' => $mimeType,
                        'anhang_typ' => 'datei'
                    ]
                ]);
            }
            break;
            
        case 'DELETE':
            // Anhang löschen
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $attachmentId = (int)$_GET['id'];
            
            // Anhang mit Geräte-Informationen abrufen
            $stmt = $pdo->prepare("
                SELECT da.*, d.company_id, d.customer_id, d.user_id
                FROM device_attachments da
                INNER JOIN devices d ON da.device_id = d.id
                WHERE da.id = ?
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
            } elseif ($userRole === 'Firmen-User' && $attachment['user_id'] == $userId) {
                $hasPermission = true;
            } elseif ($attachment['erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Datei löschen (falls vorhanden)
            if ($attachment['anhang_typ'] === 'datei' && $attachment['dateipfad']) {
                $fullPath = dirname(__DIR__, 2) . '/' . $attachment['dateipfad'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            
            // Aus Datenbank löschen
            $deleteStmt = $pdo->prepare("DELETE FROM device_attachments WHERE id = ?");
            $deleteStmt->execute([$attachmentId]);
            
            echo json_encode(['success' => true, 'message' => 'Anhang gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Device Attachments API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
