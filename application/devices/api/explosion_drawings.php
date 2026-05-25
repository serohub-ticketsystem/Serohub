<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

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
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'] ?? null;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Nur Admin und Techniker haben Zugriff
$canEdit = in_array($userRole, ['Admin', 'Techniker'], true);
if (!$canEdit && $method !== 'GET') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung. Nur Techniker und Admins können Explosionszeichnungen verwalten.']);
    exit;
}

// Upload-Verzeichnis
$uploadBaseDir = dirname(__DIR__, 2) . '/uploads/explosion_drawings/';
if (!file_exists($uploadBaseDir)) {
    mkdir($uploadBaseDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // Explosionszeichnungen für ein Gerät abrufen (basierend auf Hersteller+Modell)
            if (isset($_GET['device_id'])) {
                $deviceId = (int)$_GET['device_id'];
                
                // Gerät abrufen
                $stmt = $pdo->prepare("SELECT hersteller, modell, company_id, customer_id, user_id FROM devices WHERE id = ? LIMIT 1");
                $stmt->execute([$deviceId]);
                $device = $stmt->fetch(PDO::FETCH_ASSOC);
                
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
                
                $hersteller = $device['hersteller'] ?? '';
                $modell = $device['modell'] ?? '';
                
                // Explosionszeichnungen für dieses Gerätemodell abrufen
                $stmt = $pdo->prepare("
                    SELECT 
                        ed.id,
                        ed.bezeichnung,
                        ed.beschreibung,
                        ed.dateiname,
                        ed.dateipfad,
                        ed.dateigroesse,
                        ed.mime_type,
                        ed.erstellt_von,
                        ed.erstellt_datum,
                        ed.geaendert_datum,
                        u.vorname as ersteller_vorname,
                        u.nachname as ersteller_nachname
                    FROM explosion_drawings ed
                    INNER JOIN explosion_drawing_device_models eddm ON eddm.explosion_drawing_id = ed.id
                    LEFT JOIN users u ON ed.erstellt_von = u.id
                    WHERE eddm.hersteller = ? AND eddm.modell = ?
                    ORDER BY ed.bezeichnung ASC, ed.erstellt_datum DESC
                ");
                $stmt->execute([$hersteller, $modell]);
                $drawings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'drawings' => $drawings
                ]);
                exit;
            }
            
            // Alle Explosionszeichnungen abrufen (nur für Admin/Techniker)
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $stmt = $pdo->query("
                SELECT 
                    ed.id,
                    ed.bezeichnung,
                    ed.beschreibung,
                    ed.dateiname,
                    ed.dateipfad,
                    ed.dateigroesse,
                    ed.mime_type,
                    ed.erstellt_von,
                    ed.erstellt_datum,
                    ed.geaendert_datum,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname,
                    GROUP_CONCAT(DISTINCT CONCAT(eddm.hersteller, ' ', eddm.modell) SEPARATOR ', ') as device_models
                FROM explosion_drawings ed
                LEFT JOIN users u ON ed.erstellt_von = u.id
                LEFT JOIN explosion_drawing_device_models eddm ON eddm.explosion_drawing_id = ed.id
                GROUP BY ed.id
                ORDER BY ed.bezeichnung ASC, ed.erstellt_datum DESC
            ");
            $drawings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'drawings' => $drawings
            ]);
            exit;
            
        case 'POST':
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
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
            
            // Maximale Dateigröße: 20MB (Explosionszeichnungen können größer sein)
            $maxFileSize = 20 * 1024 * 1024;
            if ($file['size'] > $maxFileSize) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datei ist zu groß (max. 20MB)']);
                exit;
            }
            
            // Eingabedaten
            $bezeichnung = trim($_POST['bezeichnung'] ?? '');
            if (empty($bezeichnung)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bezeichnung ist erforderlich']);
                exit;
            }
            
            $beschreibung = trim($_POST['beschreibung'] ?? '') ?: null;
            $deviceModels = json_decode($_POST['device_models'] ?? '[]', true) ?: [];
            
            if (empty($deviceModels) || !is_array($deviceModels)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Mindestens ein Gerätemodell muss zugeordnet werden']);
                exit;
            }
            
            // Dateiname sicher machen
            $originalName = $file['name'];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $fileName = 'explosion_' . time() . '_' . $safeName . '.' . $extension;
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
                error_log("Explosion Drawing Upload-Fehler: Konnte Datei nicht von " . $file['tmp_name'] . " nach " . $filePath . " verschieben");
                echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei. Bitte Berechtigungen prüfen.']);
                exit;
            }
            
            // Relativer Pfad für Datenbank
            $relativePath = 'uploads/explosion_drawings/' . $fileName;
            
            // MIME-Typ ermitteln
            $mimeType = $file['type'] ?: mime_content_type($filePath);
            
            // In Datenbank speichern
            $stmt = $pdo->prepare("
                INSERT INTO explosion_drawings (bezeichnung, beschreibung, dateiname, dateipfad, dateigroesse, mime_type, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $bezeichnung,
                $beschreibung,
                $originalName,
                $relativePath,
                $file['size'],
                $mimeType,
                $userId
            ]);
            
            $drawingId = (int)$pdo->lastInsertId();
            
            // Gerätemodelle zuordnen
            $insStmt = $pdo->prepare("INSERT IGNORE INTO explosion_drawing_device_models (explosion_drawing_id, hersteller, modell) VALUES (?, ?, ?)");
            foreach ($deviceModels as $dm) {
                $h = trim((string)($dm['hersteller'] ?? ''));
                $m = trim((string)($dm['modell'] ?? ''));
                if ($h !== '' || $m !== '') {
                    $insStmt->execute([$drawingId, $h ?: '', $m ?: '']);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Explosionszeichnung erfolgreich hochgeladen',
                'drawing' => [
                    'id' => $drawingId,
                    'bezeichnung' => $bezeichnung,
                    'dateiname' => $originalName,
                    'dateipfad' => $relativePath
                ]
            ]);
            break;
            
        case 'DELETE':
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $drawingId = (int)$_GET['id'];
            
            // Explosionszeichnung abrufen
            $stmt = $pdo->prepare("SELECT dateipfad FROM explosion_drawings WHERE id = ?");
            $stmt->execute([$drawingId]);
            $drawing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$drawing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Explosionszeichnung nicht gefunden']);
                exit;
            }
            
            // Datei löschen
            $filePath = dirname(__DIR__, 2) . '/' . $drawing['dateipfad'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            
            // Aus Datenbank löschen (CASCADE löscht auch die Zuordnungen)
            $deleteStmt = $pdo->prepare("DELETE FROM explosion_drawings WHERE id = ?");
            $deleteStmt->execute([$drawingId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Explosionszeichnung erfolgreich gelöscht'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    error_log("Explosion Drawings API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
