<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';

// Content-Type nur setzen, wenn es kein Download ist
if (!isset($_GET['download'])) {
    header('Content-Type: application/json');
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
$uploadDir = dirname(__DIR__, 2) . '/uploads/firmen/dokumente/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // Download eines Dokuments
            if (isset($_GET['download'])) {
                $documentId = (int)$_GET['download'];
                
                // Dokument abrufen
                $stmt = $pdo->prepare("SELECT id, company_id, dateiname, dateipfad FROM company_documents WHERE id = ?");
                $stmt->execute([$documentId]);
                $document = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$document) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Dokument nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    if ($userRole === 'Firmen-Admin' && $userCompanyId != $document['company_id']) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                        exit;
                    }
                }
                
                $fullPath = dirname(__DIR__, 2) . '/' . $document['dateipfad'];
                
                if (!file_exists($fullPath)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Datei nicht gefunden']);
                    exit;
                }
                
            // Log-Eintrag für Download erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('company', ?, ?, 'updated', ?, NOW())
                ");
                $logStmt->execute([
                    $document['company_id'],
                    $userId,
                    "Dokument heruntergeladen: " . $document['dateiname']
                ]);
            } catch (PDOException $e) {
                // Log-Fehler protokollieren
                error_log("Fehler beim Erstellen des Log-Eintrags (Download): " . $e->getMessage());
                error_log("SQL Error Info: " . print_r($logStmt->errorInfo(), true));
            }
                
                // Datei ausliefern
                $mimeType = mime_content_type($fullPath);
                header('Content-Type: ' . $mimeType);
                header('Content-Disposition: attachment; filename="' . basename($document['dateiname']) . '"');
                header('Content-Length: ' . filesize($fullPath));
                readfile($fullPath);
                exit;
            }
            
            // Dokumente einer Firma abrufen
            if (!isset($_GET['company_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id fehlt']);
                exit;
            }
            
            $companyId = (int)$_GET['company_id'];
            
            // Berechtigung prüfen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                if ($userRole === 'Firmen-Admin' && $userCompanyId != $companyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
            }
            
            $sql = "
                SELECT 
                    d.id,
                    d.company_id,
                    d.dateiname,
                    d.dateipfad,
                    d.dateigroesse,
                    d.mime_type,
                    d.beschreibung,
                    d.erstellt_datum,
                    d.geaendert_datum,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname
                FROM company_documents d
                LEFT JOIN users u ON d.erstellt_von = u.id
                WHERE d.company_id = :company_id
                ORDER BY d.erstellt_datum DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->execute();
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'documents' => $documents
            ]);
            break;
            
        case 'POST':
            // Dokument hochladen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            if (!isset($_POST['company_id']) || !isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id und file sind erforderlich']);
                exit;
            }
            
            $companyId = (int)$_POST['company_id'];
            
            // Berechtigung prüfen
            if ($userRole === 'Firmen-Admin' && $userCompanyId != $companyId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Firma']);
                exit;
            }
            
            // Prüfen ob Firma existiert
            $checkStmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ?");
            $checkStmt->execute([$companyId]);
            $company = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$company) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
                exit;
            }
            $companyName = $company['name'];
            
            $file = $_FILES['file'];
            $beschreibung = isset($_POST['beschreibung']) ? trim($_POST['beschreibung']) : null;
            
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
            $fileName = $safeName . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $fileName;
            
            // Prüfen ob Verzeichnis beschreibbar ist
            if (!is_writable($uploadDir)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Upload-Verzeichnis ist nicht beschreibbar']);
                exit;
            }
            
            // Datei speichern
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                http_response_code(500);
                error_log("Upload-Fehler: Konnte Datei nicht von " . $file['tmp_name'] . " nach " . $filePath . " verschieben");
                echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei. Bitte Berechtigungen prüfen.']);
                exit;
            }
            
            // Relativer Pfad für Datenbank
            $relativePath = 'uploads/firmen/dokumente/' . $fileName;
            
            // MIME-Typ ermitteln
            $mimeType = $file['type'] ?: mime_content_type($filePath);
            
            // In Datenbank speichern
            $stmt = $pdo->prepare("
                INSERT INTO company_documents (company_id, dateiname, dateipfad, dateigroesse, mime_type, beschreibung, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $companyId,
                $originalName,
                $relativePath,
                $file['size'],
                $mimeType,
                $beschreibung,
                $userId
            ]);
            
            $documentId = $pdo->lastInsertId();
            
            // Log-Eintrag für Upload erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('company', ?, ?, 'created', ?, NOW())
                ");
                $logStmt->execute([
                    $companyId,
                    $userId,
                    "Dokument hochgeladen: " . $originalName
                ]);
            } catch (PDOException $e) {
                // Log-Fehler protokollieren
                error_log("Fehler beim Erstellen des Log-Eintrags (Upload): " . $e->getMessage());
                error_log("SQL Error Info: " . print_r($logStmt->errorInfo(), true));
            }
            
            // Firmennamen für Benachrichtigung abrufen
            $companyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $companyStmt->execute([$companyId]);
            $companyName = $companyStmt->fetchColumn() ?: 'Unbekannt';
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $companyId,
                'company_document_uploaded',
                'Dokument hochgeladen: ' . $originalName,
                'Das Dokument "' . $originalName . '" wurde von ' . $userName . ' für die Firma "' . $companyName . '" hochgeladen.',
                'normal',
                'companies/detail.php?id=' . $companyId,
                'company',
                $companyId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Dokument erfolgreich hochgeladen',
                'document_id' => $documentId
            ]);
            break;
            
        case 'DELETE':
            // Dokument löschen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $documentId = (int)$_GET['id'];
            
            // Dokument abrufen (mit dateiname für Log)
            $stmt = $pdo->prepare("SELECT id, company_id, dateiname, dateipfad FROM company_documents WHERE id = ?");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$document) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Dokument nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            if ($userRole === 'Firmen-Admin' && $userCompanyId != $document['company_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für dieses Dokument']);
                exit;
            }
            
            // Dateiname für Log speichern
            $documentName = $document['dateiname'];
            $documentCompanyId = $document['company_id'];
            
            // Datei löschen
            $fullPath = dirname(__DIR__, 2) . '/' . $document['dateipfad'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Aus Datenbank löschen
            $stmt = $pdo->prepare("DELETE FROM company_documents WHERE id = ?");
            $stmt->execute([$documentId]);
            
            // Log-Eintrag für Löschen erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('company', ?, ?, 'deleted', ?, NOW())
                ");
                $logStmt->execute([
                    $documentCompanyId,
                    $userId,
                    "Dokument gelöscht: " . $documentName
                ]);
            } catch (PDOException $e) {
                // Log-Fehler protokollieren
                error_log("Fehler beim Erstellen des Log-Eintrags (Delete): " . $e->getMessage());
                error_log("SQL Error Info: " . print_r($logStmt->errorInfo(), true));
            }
            
            // Firmennamen für Benachrichtigung abrufen
            $companyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $companyStmt->execute([$documentCompanyId]);
            $companyName = $companyStmt->fetchColumn() ?: 'Unbekannt';
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $documentCompanyId,
                'company_document_deleted',
                'Dokument gelöscht: ' . $documentName,
                'Das Dokument "' . $documentName . '" wurde von ' . $userName . ' für die Firma "' . $companyName . '" gelöscht.',
                'hoch',
                'companies/detail.php?id=' . $documentCompanyId,
                'company',
                $documentCompanyId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Dokument erfolgreich gelöscht'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Company Documents API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
