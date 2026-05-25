<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__) . '/helper/encryption.php';

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
$uploadBaseDir = dirname(__DIR__, 2) . '/uploads/todos/';
if (!is_dir($uploadBaseDir)) {
    mkdir($uploadBaseDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // Anhänge für ein Todo abrufen
            if (!isset($_GET['todo_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'todo_id fehlt']);
                exit;
            }
            
            $todoId = (int)$_GET['todo_id'];
            
            // Prüfen ob Todo existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT erstellt_von FROM todos WHERE id = ?");
            $checkStmt->execute([$todoId]);
            $todo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$todo) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Todo nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($todo['erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Anhänge abrufen
            $stmt = $pdo->prepare("
                SELECT id, todo_id, dateiname, dateipfad, dateigroesse, mime_type, erstellt_von, erstellt_datum
                FROM todo_attachments
                WHERE todo_id = ?
                ORDER BY erstellt_datum DESC
            ");
            $stmt->execute([$todoId]);
            $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'attachments' => $attachments
            ]);
            break;
            
        case 'POST':
            // Datei hochladen
            if (!isset($_POST['todo_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'todo_id fehlt']);
                exit;
            }
            
            $todoId = (int)$_POST['todo_id'];
            
            // Prüfen ob Todo existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT erstellt_von FROM todos WHERE id = ?");
            $checkStmt->execute([$todoId]);
            $todo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$todo) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Todo nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($todo['erstellt_von'] == $userId) {
                $hasPermission = true;
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
            $fileName = 'todo_' . $todoId . '_' . $safeName . '_' . time() . '.' . $extension;
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
                error_log("Todo Upload-Fehler: Konnte Datei nicht von " . $file['tmp_name'] . " nach " . $filePath . " verschieben");
                echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei. Bitte Berechtigungen prüfen.']);
                exit;
            }
            
            // Relativer Pfad für Datenbank
            $relativePath = 'uploads/todos/' . $fileName;
            
            // MIME-Typ ermitteln
            $mimeType = $file['type'] ?: mime_content_type($filePath);
            
            // In Datenbank speichern
            $stmt = $pdo->prepare("
                INSERT INTO todo_attachments (todo_id, dateiname, dateipfad, dateigroesse, mime_type, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $todoId,
                $originalName,
                $relativePath,
                $file['size'],
                $mimeType,
                $userId
            ]);
            
            $attachmentId = $pdo->lastInsertId();
            
            // Todo-Informationen für Benachrichtigung abrufen
            $todoStmt = $pdo->prepare("SELECT titel, company_id FROM todos WHERE id = ?");
            $todoStmt->execute([$todoId]);
            $todoData = $todoStmt->fetch(PDO::FETCH_ASSOC);
            if ($todoData) decrypt_todo_row($todoData);
            $todoTitel = $todoData['titel'] ?? 'Unbekannt';
            $todoCompanyId = $todoData['company_id'] ?? null;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $todoCompanyId,
                'todo_attachment_uploaded',
                'Anhang hochgeladen: ' . $originalName,
                'Der Anhang "' . $originalName . '" wurde von ' . $userName . ' für die Aufgabe "' . $todoTitel . '" hochgeladen.',
                'niedrig',
                'todos/index.php',
                'todo',
                $todoId
            );
            
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
            // Datei löschen
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $attachmentId = (int)$_GET['id'];
            
            // Anhang mit Todo-Informationen abrufen
            $stmt = $pdo->prepare("
                SELECT ta.*, t.erstellt_von as todo_erstellt_von
                FROM todo_attachments ta
                JOIN todos t ON ta.todo_id = t.id
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
            } elseif ($attachment['erstellt_von'] == $userId || $attachment['todo_erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Datei vom Dateisystem löschen
            $fullPath = dirname(__DIR__, 2) . '/' . $attachment['dateipfad'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Todo-Informationen für Benachrichtigung abrufen (vor dem Löschen)
            $todoStmt = $pdo->prepare("SELECT titel, company_id FROM todos WHERE id = ?");
            $todoStmt->execute([$attachment['todo_id']]);
            $todoData = $todoStmt->fetch(PDO::FETCH_ASSOC);
            if ($todoData) decrypt_todo_row($todoData);
            $todoTitel = $todoData['titel'] ?? 'Unbekannt';
            $todoCompanyId = $todoData['company_id'] ?? null;
            
            // Aus Datenbank löschen
            $deleteStmt = $pdo->prepare("DELETE FROM todo_attachments WHERE id = ?");
            $deleteStmt->execute([$attachmentId]);
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $todoCompanyId,
                'todo_attachment_deleted',
                'Anhang gelöscht: ' . $attachment['dateiname'],
                'Der Anhang "' . $attachment['dateiname'] . '" wurde von ' . $userName . ' für die Aufgabe "' . $todoTitel . '" gelöscht.',
                'normal',
                'todos/index.php',
                'todo',
                $attachment['todo_id']
            );
            
            echo json_encode(['success' => true, 'message' => 'Anhang gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Todo Attachments API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
