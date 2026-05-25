<?php
/**
 * API für Kalender-Abonnements (CalDAV/ICS)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Alle Abonnements des Users laden
            $stmt = $pdo->prepare("
                SELECT id, name, url, color, is_active, last_sync, sync_interval, created_at
                FROM calendar_subscriptions
                WHERE user_id = :uid
                ORDER BY name ASC
            ");
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'subscriptions' => $subscriptions]);
            break;
            
        case 'POST':
            // Neues Abonnement erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['name']) || empty($data['url'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name und URL sind erforderlich']);
                exit;
            }
            
            // URL validieren
            $url = filter_var($data['url'], FILTER_VALIDATE_URL);
            if (!$url) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige URL']);
                exit;
            }
            
            $color = $data['color'] ?? '#6366f1';
            
            $stmt = $pdo->prepare("
                INSERT INTO calendar_subscriptions (user_id, name, url, color)
                VALUES (:uid, :name, :url, :color)
            ");
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':url', $url, PDO::PARAM_STR);
            $stmt->bindValue(':color', $color, PDO::PARAM_STR);
            $stmt->execute();
            
            $id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'id' => $id]);
            break;
            
        case 'PATCH':
            // Abonnement aktualisieren
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID erforderlich']);
                exit;
            }
            
            // Prüfen ob Abonnement dem User gehört
            $stmt = $pdo->prepare("SELECT id FROM calendar_subscriptions WHERE id = :id AND user_id = :uid");
            $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Kein Zugriff']);
                exit;
            }
            
            $updates = [];
            $params = [':id' => $data['id']];
            
            if (isset($data['name'])) {
                $updates[] = 'name = :name';
                $params[':name'] = $data['name'];
            }
            if (isset($data['url'])) {
                $url = filter_var($data['url'], FILTER_VALIDATE_URL);
                if ($url) {
                    $updates[] = 'url = :url';
                    $params[':url'] = $url;
                }
            }
            if (isset($data['color'])) {
                $updates[] = 'color = :color';
                $params[':color'] = $data['color'];
            }
            if (isset($data['is_active'])) {
                $updates[] = 'is_active = :active';
                $params[':active'] = $data['is_active'] ? 1 : 0;
            }
            
            if (!empty($updates)) {
                $sql = "UPDATE calendar_subscriptions SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                foreach ($params as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
                $stmt->execute();
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            // Abonnement löschen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID erforderlich']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM calendar_subscriptions WHERE id = :id AND user_id = :uid");
            $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
