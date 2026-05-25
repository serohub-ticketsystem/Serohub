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

try {
    switch ($method) {
        case 'GET':
            // Notizen eines Kunden abrufen
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
                    n.id,
                    n.customer_id,
                    n.titel,
                    n.inhalt,
                    n.erstellt_datum,
                    n.geaendert_datum,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname
                FROM customer_notes n
                LEFT JOIN users u ON n.erstellt_von = u.id
                WHERE n.customer_id = :customer_id
                ORDER BY n.erstellt_datum DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $stmt->execute();
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notes' => $notes
            ]);
            break;
            
        case 'POST':
            // Notiz erstellen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['customer_id']) || !isset($data['titel']) || !isset($data['inhalt'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'customer_id, titel und inhalt sind erforderlich']);
                exit;
            }
            
            $customerId = (int)$data['customer_id'];
            $titel = trim($data['titel']);
            $inhalt = trim($data['inhalt']);
            
            if (empty($titel) || empty($inhalt)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Titel und Inhalt dürfen nicht leer sein']);
                exit;
            }
            
            // Kunde abrufen für Berechtigungsprüfung
            $customerStmt = $pdo->prepare("SELECT id, company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$customerId]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            if ($userRole === 'Firmen-Admin' && (!$customer['company_id'] || $userCompanyId != $customer['company_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diesen Kunden']);
                exit;
            }
            
            // Notiz speichern
            $stmt = $pdo->prepare("
                INSERT INTO customer_notes (customer_id, titel, inhalt, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $customerId,
                $titel,
                $inhalt,
                $userId
            ]);
            
            $noteId = $pdo->lastInsertId();
            
            // Log-Eintrag für Erstellung erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('customer', ?, ?, 'created', ?, NOW())
                ");
                $logStmt->execute([
                    $customerId,
                    $userId,
                    "Notiz erstellt: " . $titel
                ]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags (Note Create): " . $e->getMessage());
            }
            
            // Kundennamen für Benachrichtigung abrufen
            $customerName = $customer['name'] ?? 'Unbekannt';
            $customerCompanyId = $customer['company_id'] ?? null;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $customerCompanyId,
                'customer_note_created',
                'Neue Notiz erstellt: ' . $titel,
                'Eine neue Notiz "' . $titel . '" wurde von ' . $userName . ' für den Kunden "' . $customerName . '" erstellt.',
                'normal',
                'customers/detail.php?id=' . $customerId,
                'customer',
                $customerId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Notiz erfolgreich erstellt',
                'note_id' => $noteId
            ]);
            break;
            
        case 'DELETE':
            // Notiz löschen
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
            
            $noteId = (int)$_GET['id'];
            
            // Notiz abrufen
            $stmt = $pdo->prepare("SELECT id, customer_id, titel FROM customer_notes WHERE id = ?");
            $stmt->execute([$noteId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$note) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notiz nicht gefunden']);
                exit;
            }
            
            // Kunde abrufen für Berechtigungsprüfung
            $customerStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$note['customer_id']]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            
            // Berechtigung prüfen
            if ($userRole === 'Firmen-Admin' && (!$customer || !$customer['company_id'] || $userCompanyId != $customer['company_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Notiz']);
                exit;
            }
            
            // Aus Datenbank löschen
            $stmt = $pdo->prepare("DELETE FROM customer_notes WHERE id = ?");
            $stmt->execute([$noteId]);
            
            // Log-Eintrag für Löschen erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('customer', ?, ?, 'deleted', ?, NOW())
                ");
                $logStmt->execute([
                    $note['customer_id'],
                    $userId,
                    "Notiz gelöscht: " . $note['titel']
                ]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags (Note Delete): " . $e->getMessage());
            }
            
            // Kundennamen und company_id für Benachrichtigung abrufen
            $customerStmt = $pdo->prepare("SELECT name, company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$note['customer_id']]);
            $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
            $customerName = $customerData['name'] ?? 'Unbekannt';
            $customerCompanyId = $customerData['company_id'] ?? null;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $customerCompanyId,
                'customer_note_deleted',
                'Notiz gelöscht: ' . $note['titel'],
                'Die Notiz "' . $note['titel'] . '" wurde von ' . $userName . ' für den Kunden "' . $customerName . '" gelöscht.',
                'hoch',
                'customers/detail.php?id=' . $note['customer_id'],
                'customer',
                $note['customer_id']
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Notiz erfolgreich gelöscht'
            ]);
            break;
            
        case 'PUT':
            // Notiz aktualisieren
            if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id']) || !isset($data['titel']) || !isset($data['inhalt'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id, titel und inhalt sind erforderlich']);
                exit;
            }
            
            $noteId = (int)$data['id'];
            $titel = trim($data['titel']);
            $inhalt = trim($data['inhalt']);
            
            if (empty($titel) || empty($inhalt)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Titel und Inhalt dürfen nicht leer sein']);
                exit;
            }
            
            // Notiz abrufen
            $stmt = $pdo->prepare("SELECT id, customer_id, titel FROM customer_notes WHERE id = ?");
            $stmt->execute([$noteId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$note) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notiz nicht gefunden']);
                exit;
            }
            
            // Kunde abrufen für Berechtigungsprüfung
            $customerStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$note['customer_id']]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            
            // Berechtigung prüfen
            if ($userRole === 'Firmen-Admin' && (!$customer || !$customer['company_id'] || $userCompanyId != $customer['company_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Notiz']);
                exit;
            }
            
            // Notiz aktualisieren
            $stmt = $pdo->prepare("
                UPDATE customer_notes 
                SET titel = ?, inhalt = ?, geaendert_datum = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $titel,
                $inhalt,
                $noteId
            ]);
            
            // Log-Eintrag für Aktualisierung erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('customer', ?, ?, 'updated', ?, NOW())
                ");
                $logStmt->execute([
                    $note['customer_id'],
                    $userId,
                    "Notiz aktualisiert: " . $titel
                ]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags (Note Update): " . $e->getMessage());
            }
            
            // Kundennamen und company_id für Benachrichtigung abrufen
            $customerStmt = $pdo->prepare("SELECT name, company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$note['customer_id']]);
            $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
            if ($customerData) { decrypt_customer_row($customerData); }
            $customerName = $customerData['name'] ?? 'Unbekannt';
            $customerCompanyId = $customerData['company_id'] ?? null;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $customerCompanyId,
                'customer_note_updated',
                'Notiz aktualisiert: ' . $titel,
                'Die Notiz "' . $titel . '" wurde von ' . $userName . ' für den Kunden "' . $customerName . '" aktualisiert.',
                'normal',
                'customers/detail.php?id=' . $note['customer_id'],
                'customer',
                $note['customer_id']
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Notiz erfolgreich aktualisiert'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Customer Notes API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
