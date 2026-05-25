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

// Nur Admins haben Zugriff auf Verträge
if ($userRole !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nur Administratoren haben Zugriff auf Verträge']);
    exit;
}

// Upload-Verzeichnis
$uploadDir = dirname(__DIR__, 2) . '/uploads/firmen/vertraege/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // Download eines Vertrags
            if (isset($_GET['download'])) {
                $contractId = (int)$_GET['download'];
                
                // Vertrag abrufen
                $stmt = $pdo->prepare("SELECT id, company_id, dateiname, dateipfad FROM company_contracts WHERE id = ?");
                $stmt->execute([$contractId]);
                $contract = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$contract) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Vertrag nicht gefunden']);
                    exit;
                }
                
                $fullPath = dirname(__DIR__, 2) . '/' . $contract['dateipfad'];
                
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
                        $contract['company_id'],
                        $userId,
                        "Vertrag heruntergeladen: " . $contract['dateiname']
                    ]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags (Download): " . $e->getMessage());
                    error_log("SQL Error Info: " . print_r($logStmt->errorInfo(), true));
                }
                
                // Datei ausliefern
                $mimeType = mime_content_type($fullPath);
                header('Content-Type: ' . $mimeType);
                header('Content-Disposition: attachment; filename="' . basename($contract['dateiname']) . '"');
                header('Content-Length: ' . filesize($fullPath));
                readfile($fullPath);
                exit;
            }
            
            // Verträge einer Firma abrufen
            if (!isset($_GET['company_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id fehlt']);
                exit;
            }
            
            $companyId = (int)$_GET['company_id'];
            
            $sql = "
                SELECT 
                    c.id,
                    c.company_id,
                    c.dateiname,
                    c.dateipfad,
                    c.dateigroesse,
                    c.mime_type,
                    c.beschreibung,
                    c.erstellt_datum,
                    c.geaendert_datum,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname
                FROM company_contracts c
                LEFT JOIN users u ON c.erstellt_von = u.id
                WHERE c.company_id = :company_id
                ORDER BY c.erstellt_datum DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->execute();
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'contracts' => $contracts
            ]);
            break;
            
        case 'POST':
            // Vertrag hochladen
            if (!isset($_POST['company_id']) || !isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id und file sind erforderlich']);
                exit;
            }
            
            $companyId = (int)$_POST['company_id'];
            
            // Prüfen ob Firma existiert
            $checkStmt = $pdo->prepare("SELECT id FROM companies WHERE id = ?");
            $checkStmt->execute([$companyId]);
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
                exit;
            }
            
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
            $relativePath = 'uploads/firmen/vertraege/' . $fileName;
            
            // MIME-Typ ermitteln
            $mimeType = $file['type'] ?: mime_content_type($filePath);
            
            // In Datenbank speichern
            $stmt = $pdo->prepare("
                INSERT INTO company_contracts (company_id, dateiname, dateipfad, dateigroesse, mime_type, beschreibung, erstellt_von, erstellt_datum)
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
            
            $contractId = $pdo->lastInsertId();
            
            // Log-Eintrag für Upload erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('company', ?, ?, 'created', ?, NOW())
                ");
                $logStmt->execute([
                    $companyId,
                    $userId,
                    "Vertrag hochgeladen: " . $originalName
                ]);
            } catch (PDOException $e) {
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
                'company_contract_uploaded',
                'Vertrag hochgeladen: ' . $originalName,
                'Der Vertrag "' . $originalName . '" wurde von ' . $userName . ' für die Firma "' . $companyName . '" hochgeladen.',
                'hoch',
                'companies/detail.php?id=' . $companyId,
                'company',
                $companyId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Vertrag erfolgreich hochgeladen',
                'contract_id' => $contractId
            ]);
            break;
            
        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $contractId = (int)$_GET['id'];
            
            // Vertrag abrufen (mit dateiname für Log)
            $stmt = $pdo->prepare("SELECT id, company_id, dateiname, dateipfad FROM company_contracts WHERE id = ?");
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contract) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Vertrag nicht gefunden']);
                exit;
            }
            
            // Dateiname für Log speichern
            $contractName = $contract['dateiname'];
            $contractCompanyId = $contract['company_id'];
            
            // Datei löschen
            $fullPath = dirname(__DIR__, 2) . '/' . $contract['dateipfad'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Aus Datenbank löschen
            $stmt = $pdo->prepare("DELETE FROM company_contracts WHERE id = ?");
            $stmt->execute([$contractId]);
            
            // Log-Eintrag für Löschen erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('company', ?, ?, 'deleted', ?, NOW())
                ");
                $logStmt->execute([
                    $contractCompanyId,
                    $userId,
                    "Vertrag gelöscht: " . $contractName
                ]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags (Delete): " . $e->getMessage());
                error_log("SQL Error Info: " . print_r($logStmt->errorInfo(), true));
            }
            
            // Firmennamen für Benachrichtigung abrufen
            $companyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $companyStmt->execute([$contractCompanyId]);
            $companyName = $companyStmt->fetchColumn() ?: 'Unbekannt';
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $contractCompanyId,
                'company_contract_deleted',
                'Vertrag gelöscht: ' . $contractName,
                'Der Vertrag "' . $contractName . '" wurde von ' . $userName . ' für die Firma "' . $companyName . '" gelöscht.',
                'kritisch',
                'companies/detail.php?id=' . $contractCompanyId,
                'company',
                $contractCompanyId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Vertrag erfolgreich gelöscht'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Company Contracts API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
