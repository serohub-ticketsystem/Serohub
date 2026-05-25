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

// Upload-Verzeichnis
$uploadDir = dirname(__DIR__, 2) . '/uploads/kunden/dokumente/';
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
                $stmt = $pdo->prepare("SELECT id, customer_id, dateiname, dateipfad FROM customer_documents WHERE id = ?");
                $stmt->execute([$documentId]);
                $document = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$document) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Dokument nicht gefunden']);
                    exit;
                }
                
                // Kunde abrufen für Berechtigungsprüfung
                $customerStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ?");
                $customerStmt->execute([$document['customer_id']]);
                $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                
                // Berechtigung prüfen
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    if ($userRole === 'Firmen-Admin' && (!$customer || $userCompanyId != $customer['company_id'])) {
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
                        VALUES ('customer', ?, ?, 'updated', ?, NOW())
                    ");
                    $logStmt->execute([
                        $document['customer_id'],
                        $userId,
                        "Dokument heruntergeladen: " . $document['dateiname']
                    ]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags (Download): " . $e->getMessage());
                }
                
                // Datei ausliefern
                $mimeType = mime_content_type($fullPath);
                header('Content-Type: ' . $mimeType);
                header('Content-Disposition: attachment; filename="' . basename($document['dateiname']) . '"');
                header('Content-Length: ' . filesize($fullPath));
                readfile($fullPath);
                exit;
            }
            
            // Dokumente eines Kunden abrufen
            if (!isset($_GET['customer_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'customer_id fehlt']);
                exit;
            }
            
            $customerId = (int)$_GET['customer_id'];
            
            // Kunde abrufen für Berechtigungsprüfung
            $customerStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$customerId]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                if ($userRole === 'Firmen-Admin' && (!$customer['company_id'] || $userCompanyId != $customer['company_id'])) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
            }
            
            $sql = "
                SELECT 
                    d.id,
                    d.customer_id,
                    d.dateiname,
                    d.dateipfad,
                    d.dateigroesse,
                    d.mime_type,
                    d.beschreibung,
                    d.erstellt_datum,
                    d.geaendert_datum,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname
                FROM customer_documents d
                LEFT JOIN users u ON d.erstellt_von = u.id
                WHERE d.customer_id = :customer_id
                ORDER BY d.erstellt_datum DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
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
            
            if (!isset($_POST['customer_id']) || !isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'customer_id und file sind erforderlich']);
                exit;
            }
            
            $customerId = (int)$_POST['customer_id'];
            
            // Kunde abrufen für Berechtigungsprüfung
            $customerStmt = $pdo->prepare("SELECT id, name, company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$customerId]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            if ($customer) { decrypt_customer_row($customer); }
            
            if (!$customer) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
                exit;
            }
            $customerName = $customer['name'];
            
            // Berechtigung prüfen
            if ($userRole === 'Firmen-Admin' && (!$customer['company_id'] || $userCompanyId != $customer['company_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diesen Kunden']);
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
            $relativePath = 'uploads/kunden/dokumente/' . $fileName;
            
            // MIME-Typ ermitteln
            $mimeType = $file['type'] ?: mime_content_type($filePath);
            
            // In Datenbank speichern
            $stmt = $pdo->prepare("
                INSERT INTO customer_documents (customer_id, dateiname, dateipfad, dateigroesse, mime_type, beschreibung, erstellt_von, erstellt_datum)
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
            
            $documentId = $pdo->lastInsertId();
            
            // Log-Eintrag für Upload erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('customer', ?, ?, 'created', ?, NOW())
                ");
                $logStmt->execute([
                    $customerId,
                    $userId,
                    "Dokument hochgeladen: " . $originalName
                ]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags (Upload): " . $e->getMessage());
            }
            
            // Kundenname und company_id für Benachrichtigung abrufen
            $customerName = $customer['name'] ?? '';
            $customerCompanyId = $customer['company_id'] ?? null;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $customerCompanyId,
                'customer_document_uploaded',
                'Dokument hochgeladen: ' . $originalName,
                'Das Dokument "' . $originalName . '" wurde von ' . $userName . ' für den Kunden "' . $customerName . '" hochgeladen.',
                'normal',
                'customers/detail.php?id=' . $customerId,
                'customer',
                $customerId
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
            
            // Dokument abrufen
            $stmt = $pdo->prepare("SELECT id, customer_id, dateiname, dateipfad FROM customer_documents WHERE id = ?");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$document) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Dokument nicht gefunden']);
                exit;
            }
            
            // Kunde abrufen für Berechtigungsprüfung
            $customerStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$document['customer_id']]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            
            // Berechtigung prüfen
            if ($userRole === 'Firmen-Admin' && (!$customer || !$customer['company_id'] || $userCompanyId != $customer['company_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für dieses Dokument']);
                exit;
            }
            
            // Datei löschen
            $fullPath = dirname(__DIR__, 2) . '/' . $document['dateipfad'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            
            // Aus Datenbank löschen
            $stmt = $pdo->prepare("DELETE FROM customer_documents WHERE id = ?");
            $stmt->execute([$documentId]);
            
            // Log-Eintrag für Löschen erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('customer', ?, ?, 'deleted', ?, NOW())
                ");
                $logStmt->execute([
                    $document['customer_id'],
                    $userId,
                    "Dokument gelöscht: " . $document['dateiname']
                ]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags (Delete): " . $e->getMessage());
            }
            
            // Kundennamen für Benachrichtigung abrufen
            $customerStmt = $pdo->prepare("SELECT name, company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$document['customer_id']]);
            $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
            if ($customerData) { decrypt_customer_row($customerData); }
            $customerName = $customerData['name'] ?? 'Unbekannt';
            $customerCompanyId = $customerData['company_id'] ?? null;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $customerCompanyId,
                'customer_document_deleted',
                'Dokument gelöscht: ' . $document['dateiname'],
                'Das Dokument "' . $document['dateiname'] . '" wurde von ' . $userName . ' für den Kunden "' . $customerName . '" gelöscht.',
                'hoch',
                'customers/detail.php?id=' . $document['customer_id'],
                'customer',
                $document['customer_id']
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
    error_log("Customer Documents API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
