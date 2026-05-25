<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__, 2) . '/assets/service_log_helper.php';

header('Content-Type: application/json');

// Prüfen ob Tabelle existiert, falls nicht erstellen
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'comment_attachments'");
    if ($checkTable->rowCount() === 0) {
        // Tabelle erstellen
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `comment_attachments` (
          `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `comment_id` INT(11) UNSIGNED NOT NULL COMMENT 'Kommentar-ID',
          `dateiname` VARCHAR(255) NOT NULL COMMENT 'Originaler Dateiname',
          `dateipfad` VARCHAR(500) NOT NULL COMMENT 'Relativer Pfad zur Datei',
          `dateigroesse` INT(11) UNSIGNED NOT NULL COMMENT 'Dateigröße in Bytes',
          `mime_type` VARCHAR(100) DEFAULT NULL COMMENT 'MIME-Typ der Datei',
          `erstellt_von` INT(11) UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
          `erstellt_datum` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
          PRIMARY KEY (`id`),
          KEY `idx_comment_id` (`comment_id`),
          KEY `idx_erstellt_von` (`erstellt_von`),
          CONSTRAINT `fk_comment_attachments_comment` FOREIGN KEY (`comment_id`) REFERENCES `ticket_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_comment_attachments_user` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anhänge für Kommentare'
        ";
        $pdo->exec($createTableSQL);
    }
} catch (PDOException $e) {
    error_log("Fehler beim Erstellen der comment_attachments Tabelle: " . $e->getMessage());
    // Weiter mit normalem Ablauf, Fehler wird später behandelt
}

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

// Upload-Verzeichnis
$uploadBaseDir = dirname(__DIR__, 2) . '/uploads/tickets/';
if (!is_dir($uploadBaseDir)) {
    mkdir($uploadBaseDir, 0755, true);
    // .htaccess Datei für Sicherheit
    file_put_contents($uploadBaseDir . '.htaccess', "Options -Indexes\nDeny from all\n");
}

try {
    switch ($method) {
        case 'POST':
            // Datei hochladen
            if (!isset($_POST['comment_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'comment_id fehlt']);
                exit;
            }
            
            $commentId = (int)$_POST['comment_id'];
            
            // Prüfen ob Kommentar existiert und Berechtigung
            $checkStmt = $pdo->prepare("
                SELECT tc.user_id, tc.ticket_id, t.company_id, t.erstellt_von 
                FROM ticket_comments tc
                LEFT JOIN tickets t ON tc.ticket_id = t.id
                WHERE tc.id = ?
            ");
            $checkStmt->execute([$commentId]);
            $comment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$comment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Kommentar nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $comment['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($comment['erstellt_von'] == $userId || $comment['user_id'] == $userId) {
                $hasPermission = true;
            }
            
            // Beobachter dürfen Kommentar-Anhänge hochladen
            if (!$hasPermission) {
                try {
                    $obsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                    $obsStmt->bindValue(':ticket_id', (int)$comment['ticket_id'], PDO::PARAM_INT);
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
            
            // Datei-Upload verarbeiten - Unterstützung für mehrere Dateien
            $uploadedFiles = [];
            $errors = [];
            
            // Prüfen ob Dateien hochgeladen wurden
            if (!isset($_FILES['file']) && !isset($_FILES['file[]'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Datei hochgeladen']);
                exit;
            }
            
            // Mehrere Dateien verarbeiten - Unterstützung für file[] und file
            // PHP konvertiert file[] automatisch zu file mit Array-Struktur
            if (isset($_FILES['file']) && is_array($_FILES['file']['name'])) {
                // Mehrere Dateien als Array
                $files = $_FILES['file'];
            } elseif (isset($_FILES['file'])) {
                // Einzelne Datei
                $files = [
                    'name' => [$_FILES['file']['name']],
                    'type' => [$_FILES['file']['type']],
                    'tmp_name' => [$_FILES['file']['tmp_name']],
                    'error' => [$_FILES['file']['error']],
                    'size' => [$_FILES['file']['size']]
                ];
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Datei hochgeladen']);
                exit;
            }
            
            $fileCount = count($files['name']);
            
            // Jede Datei verarbeiten
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Fehler bei Datei ' . ($i + 1) . ': ' . $files['name'][$i];
                    continue;
                }
                
                $fileName = $files['name'][$i];
                $fileSize = $files['size'][$i];
                $fileTmpPath = $files['tmp_name'][$i];
                $mimeType = $files['type'][$i];
                
                // Dateiname sicher machen
                $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileName));
                $fileExtension = pathinfo($safeFileName, PATHINFO_EXTENSION);
                $uniqueFileName = time() . '_' . uniqid() . '_' . $i . '.' . $fileExtension;
                $filePath = $uploadBaseDir . $uniqueFileName;
                
                // Datei speichern
                if (!move_uploaded_file($fileTmpPath, $filePath)) {
                    $errors[] = 'Fehler beim Speichern von ' . $fileName;
                    continue;
                }
                
                // Relative Pfad für Datenbank
                $relativePath = 'uploads/tickets/' . $uniqueFileName;
                
                // Anhang in Datenbank speichern
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO comment_attachments (comment_id, dateiname, dateipfad, dateigroesse, mime_type, erstellt_von, erstellt_datum)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$commentId, $fileName, $relativePath, $fileSize, $mimeType, $userId]);
                    
                    $attachmentId = $pdo->lastInsertId();
                    
                    $uploadedFiles[] = [
                        'id' => $attachmentId,
                        'dateiname' => $fileName,
                        'dateipfad' => $relativePath,
                        'dateigroesse' => $fileSize,
                        'mime_type' => $mimeType
                    ];
                } catch (PDOException $e) {
                    // Datei löschen wenn DB-Insert fehlschlägt
                    @unlink($filePath);
                    $errors[] = 'Datenbankfehler bei ' . $fileName . ': ' . $e->getMessage();
                }
            }
            
            if (count($uploadedFiles) === 0) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Keine Dateien konnten hochgeladen werden',
                    'errors' => $errors
                ]);
                exit;
            }
            
            $ticketIdForUpdate = (int)$comment['ticket_id'];
            try {
                $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?")->execute([$ticketIdForUpdate]);
            } catch (PDOException $e) {
                error_log("Fehler beim Aktualisieren des Tickets nach Kommentar-Anhang: " . $e->getMessage());
            }
            
            service_log($pdo, $userId, 'ticket', (int)$comment['ticket_id'], 'created', null, null, null, 'Kommentar-Anhang hochgeladen zu Kommentar #' . $commentId . ' (Ticket #' . $comment['ticket_id'] . ', ' . count($uploadedFiles) . ' Datei(en))');
            echo json_encode([
                'success' => true,
                'attachments' => $uploadedFiles,
                'uploaded_count' => count($uploadedFiles),
                'total_count' => $fileCount,
                'errors' => $errors
            ]);
            break;
            
        case 'DELETE':
            // Anhang löschen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $attachmentId = (int)$data['id'];
            
            // Prüfen ob Anhang existiert und Berechtigung
            $checkStmt = $pdo->prepare("
                SELECT ca.dateipfad, ca.erstellt_von, tc.user_id, tc.ticket_id, t.company_id, t.erstellt_von as ticket_erstellt_von
                FROM comment_attachments ca
                LEFT JOIN ticket_comments tc ON ca.comment_id = tc.id
                LEFT JOIN tickets t ON tc.ticket_id = t.id
                WHERE ca.id = ?
            ");
            $checkStmt->execute([$attachmentId]);
            $attachment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
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
            } elseif ($attachment['erstellt_von'] == $userId || $attachment['user_id'] == $userId || $attachment['ticket_erstellt_von'] == $userId) {
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
                @unlink($fullPath);
            }
            
            // Aus Datenbank löschen
            $deleteStmt = $pdo->prepare("DELETE FROM comment_attachments WHERE id = ?");
            $deleteStmt->execute([$attachmentId]);
            service_log($pdo, $userId, 'ticket', (int)$attachment['ticket_id'], 'deleted', null, null, null, 'Kommentar-Anhang #' . $attachmentId . ' gelöscht (Ticket #' . $attachment['ticket_id'] . ')');
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    error_log("Comment Attachments API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Comment Attachments API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Serverfehler: ' . $e->getMessage()]);
}
