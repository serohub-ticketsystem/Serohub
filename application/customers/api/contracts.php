<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__) . '/helper/encryption.php';

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

// Nur Admins haben Zugriff auf Rechnungen
if ($userRole !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nur Administratoren haben Zugriff auf Rechnungen']);
    exit;
}

// Upload-Verzeichnis
$uploadDir = dirname(__DIR__, 2) . '/uploads/kunden/rechnungen/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // Download einer Rechnung
            if (isset($_GET['download'])) {
                $contractId = (int)$_GET['download'];
                
                // Rechnung abrufen
                $stmt = $pdo->prepare("SELECT id, customer_id, dateiname, dateipfad FROM customer_contracts WHERE id = ?");
                $stmt->execute([$contractId]);
                $contract = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$contract) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Rechnung nicht gefunden']);
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
                        VALUES ('customer', ?, ?, 'updated', ?, NOW())
                    ");
                    $logStmt->execute([
                        $contract['customer_id'],
                        $userId,
                        "Rechnung heruntergeladen: " . $contract['dateiname']
                    ]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags (Download): " . $e->getMessage());
                }
                
                // Datei ausliefern
                $mimeType = mime_content_type($fullPath);
                header('Content-Type: ' . $mimeType);
                header('Content-Disposition: attachment; filename="' . basename($contract['dateiname']) . '"');
                header('Content-Length: ' . filesize($fullPath));
                readfile($fullPath);
                exit;
            }
            
            // Rechnungen eines Kunden abrufen
            if (!isset($_GET['customer_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'customer_id fehlt']);
                exit;
            }
            
            $customerId = (int)$_GET['customer_id'];
            
            $sql = "
                SELECT 
                    c.id,
                    c.customer_id,
                    c.dateiname,
                    c.dateipfad,
                    c.dateigroesse,
                    c.mime_type,
                    c.beschreibung,
                    c.erstellt_datum,
                    c.geaendert_datum,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname
                FROM customer_contracts c
                LEFT JOIN users u ON c.erstellt_von = u.id
                WHERE c.customer_id = :customer_id
                ORDER BY c.erstellt_datum DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $stmt->execute();
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'contracts' => $contracts
            ]);
            break;
            
        case 'POST':
            // Rechnung hochladen
            if (!isset($_POST['customer_id']) || !isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'customer_id und file sind erforderlich']);
                exit;
            }
            
            $customerId = (int)$_POST['customer_id'];
            
            // Prüfen ob Kunde existiert
            $checkStmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
            $checkStmt->execute([$customerId]);
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
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
            $fileName = 'customer_' . $customerId . '_' . $safeName . '_' . time() . '.' . $extension;
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
            $relativePath = 'uploads/kunden/rechnungen/' . $fileName;
            
            // MIME-Typ ermitteln
            $mimeType = $file['type'] ?: mime_content_type($filePath);
            
            // In Datenbank speichern
            $stmt = $pdo->prepare("
                INSERT INTO customer_contracts (customer_id, dateiname, dateipfad, dateigroesse, mime_type, beschreibung, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $customerId,
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
                    VALUES ('customer', ?, ?, 'created', ?, NOW())
                ");
                $logStmt->execute([
                    $customerId,
                    $userId,
                    "Rechnung hochgeladen: " . $originalName
                ]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags (Upload): " . $e->getMessage());
            }
            
            // Kundennamen und company_id für Benachrichtigung abrufen
            $customerStmt = $pdo->prepare("SELECT name, company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$customerId]);
            $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
            if ($customerData) { decrypt_customer_row($customerData); }
            $customerName = $customerData['name'] ?? 'Unbekannt';
            $customerCompanyId = $customerData['company_id'] ?? null;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $customerCompanyId,
                'customer_contract_uploaded',
                'Rechnung hochgeladen: ' . $originalName,
                'Die Rechnung "' . $originalName . '" wurde von ' . $userName . ' für den Kunden "' . $customerName . '" hochgeladen.',
                'hoch',
                'customers/detail.php?id=' . $customerId,
                'customer',
                $customerId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Rechnung erfolgreich hochgeladen',
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
            
            // Rechnung abrufen
            $stmt = $pdo->prepare("SELECT id, customer_id, dateiname, dateipfad FROM customer_contracts WHERE id = ?");
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contract) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Rechnung nicht gefunden']);
                exit;
            }
            
            // Dateiname für Log speichern
            $contractName = $contract['dateiname'];
            $contractCustomerId = $contract['customer_id'];
            
            // Datei löschen
            $fullPath = dirname(__DIR__, 2) . '/' . $contract['dateipfad'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            
            // Aus Datenbank löschen
            $stmt = $pdo->prepare("DELETE FROM customer_contracts WHERE id = ?");
            $stmt->execute([$contractId]);
            
            // Log-Eintrag für Löschen erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('customer', ?, ?, 'deleted', ?, NOW())
                ");
                $logStmt->execute([
                    $contractCustomerId,
                    $userId,
                    "Rechnung gelöscht: " . $contractName
                ]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags (Delete): " . $e->getMessage());
            }
            
            // Kundennamen und company_id für Benachrichtigung abrufen
            $customerStmt = $pdo->prepare("SELECT name, company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$contractCustomerId]);
            $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
            if ($customerData) { decrypt_customer_row($customerData); }
            $customerName = $customerData['name'] ?? 'Unbekannt';
            $customerCompanyId = $customerData['company_id'] ?? null;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $customerCompanyId,
                'customer_contract_deleted',
                'Rechnung gelöscht: ' . $contractName,
                'Die Rechnung "' . $contractName . '" wurde von ' . $userName . ' für den Kunden "' . $customerName . '" gelöscht.',
                'kritisch',
                'customers/detail.php?id=' . $contractCustomerId,
                'customer',
                $contractCustomerId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Rechnung erfolgreich gelöscht'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Customer Contracts API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
