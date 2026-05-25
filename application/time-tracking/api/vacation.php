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
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || ($user['rolle'] !== 'Admin' && $user['rolle'] !== 'Techniker')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // Urlaubstage abrufen
            $dateFrom = $_GET['date_from'] ?? date('Y-01-01');
            $dateTo = $_GET['date_to'] ?? date('Y-12-31');
            
            $stmt = $pdo->prepare("
                SELECT id, user_id, date, hours, type
                FROM time_tracking_vacation
                WHERE user_id = :user_id 
                AND date BETWEEN :date_from AND :date_to
                ORDER BY date DESC
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':date_from', $dateFrom);
            $stmt->bindValue(':date_to', $dateTo);
            $stmt->execute();
            $vacations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'vacations' => $vacations]);
            break;
            
        case 'POST':
            // Urlaubstag hinzufügen
            $data = json_decode(file_get_contents('php://input'), true);
            $date = $data['date'] ?? null;
            $hours = $data['hours'] ?? 8.00;
            $type = $data['type'] ?? 'vacation';
            
            if (!$date) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datum ist erforderlich']);
                exit;
            }
            
            // Validierung (school = Berufsschule)
            $validTypes = ['vacation', 'sick', 'holiday', 'school', 'other'];
            if (!in_array($type, $validTypes)) {
                $type = 'vacation';
            }
            
            // Prüfen ob bereits ein Eintrag für dieses Datum existiert
            $checkStmt = $pdo->prepare("SELECT id FROM time_tracking_vacation WHERE user_id = :user_id AND date = :date");
            $checkStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->bindValue(':date', $date);
            $checkStmt->execute();
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Aktualisieren
                $stmt = $pdo->prepare("
                    UPDATE time_tracking_vacation
                    SET hours = :hours, type = :type
                    WHERE id = :id
                ");
                $stmt->bindValue(':id', $existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':hours', (float)$hours);
                $stmt->bindValue(':type', $type);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Urlaubstag aktualisiert']);
            } else {
                // Neu erstellen
                $stmt = $pdo->prepare("
                    INSERT INTO time_tracking_vacation (user_id, date, hours, type)
                    VALUES (:user_id, :date, :hours, :type)
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':date', $date);
                $stmt->bindValue(':hours', (float)$hours);
                $stmt->bindValue(':type', $type);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Urlaubstag hinzugefügt']);
            }
            break;
            
        case 'PATCH':
            // Urlaubstag verschieben (Datum ändern)
            $data = json_decode(file_get_contents('php://input'), true);
            $vacationId = isset($data['id']) ? (int)$data['id'] : 0;
            $newDate = $data['date'] ?? null;
            if (!$vacationId || !$newDate) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID und neues Datum erforderlich']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM time_tracking_vacation WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':id', $vacationId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $vacation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$vacation) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Eintrag nicht gefunden']);
                exit;
            }
            $newDate = preg_replace('/T.*$/', '', $newDate);
            $upd = $pdo->prepare("UPDATE time_tracking_vacation SET date = :date WHERE id = :id");
            $upd->bindValue(':date', $newDate, PDO::PARAM_STR);
            $upd->bindValue(':id', $vacationId, PDO::PARAM_INT);
            $upd->execute();
            echo json_encode(['success' => true, 'message' => 'Datum aktualisiert']);
            break;

        case 'DELETE':
            // Urlaubstag löschen
            $data = json_decode(file_get_contents('php://input'), true);
            $vacationId = $data['id'] ?? null;
            
            if (!$vacationId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            // Prüfen ob Eintrag dem Benutzer gehört
            $stmt = $pdo->prepare("SELECT id FROM time_tracking_vacation WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':id', $vacationId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $vacation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vacation) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Eintrag nicht gefunden']);
                exit;
            }
            
            $deleteStmt = $pdo->prepare("DELETE FROM time_tracking_vacation WHERE id = :id");
            $deleteStmt->bindValue(':id', $vacationId, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Urlaubstag gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
