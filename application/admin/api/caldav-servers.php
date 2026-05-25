<?php
/**
 * API für CalDAV-Server-Verwaltung (Admin)
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
$stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['rolle'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("
                SELECT id, name, url, beschreibung, is_active, sort_order, erstellt_datum
                FROM caldav_servers
                ORDER BY sort_order ASC, name ASC
            ");
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'servers' => $servers]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $name = trim($input['name'] ?? '');
            $url = trim($input['url'] ?? '');
            $beschreibung = trim($input['beschreibung'] ?? '');
            $isActive = isset($input['is_active']) ? (bool) $input['is_active'] : true;
            $sortOrder = (int) ($input['sort_order'] ?? 0);

            if (empty($name) || empty($url)) {
                echo json_encode(['success' => false, 'error' => 'Name und URL sind erforderlich']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO caldav_servers (name, url, beschreibung, is_active, sort_order)
                VALUES (:name, :url, :beschreibung, :is_active, :sort_order)
            ");
            $stmt->execute([
                ':name' => $name,
                ':url' => $url,
                ':beschreibung' => $beschreibung ?: null,
                ':is_active' => $isActive ? 1 : 0,
                ':sort_order' => $sortOrder
            ]);
            $id = (int) $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'PATCH':
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ungültige ID']);
                exit;
            }

            $updates = [];
            $params = [':id' => $id];
            if (array_key_exists('name', $input)) {
                $updates[] = 'name = :name';
                $params[':name'] = trim($input['name']);
            }
            if (array_key_exists('url', $input)) {
                $updates[] = 'url = :url';
                $params[':url'] = trim($input['url']);
            }
            if (array_key_exists('beschreibung', $input)) {
                $updates[] = 'beschreibung = :beschreibung';
                $params[':beschreibung'] = trim($input['beschreibung']) ?: null;
            }
            if (array_key_exists('is_active', $input)) {
                $updates[] = 'is_active = :is_active';
                $params[':is_active'] = $input['is_active'] ? 1 : 0;
            }
            if (array_key_exists('sort_order', $input)) {
                $updates[] = 'sort_order = :sort_order';
                $params[':sort_order'] = (int) $input['sort_order'];
            }

            if (empty($updates)) {
                echo json_encode(['success' => false, 'error' => 'Keine Änderungen']);
                exit;
            }

            $sql = "UPDATE caldav_servers SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ungültige ID']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM caldav_servers WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
