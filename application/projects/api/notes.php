<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    $userRole = $user['rolle'];
}
catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

function canAccessProject($pdo, $projectId, $userRole) {
    if ($userRole === 'Admin' || $userRole === 'Techniker') return true;
    return false;
}

try {
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $projectId = isset($data['project_id']) ? (int)$data['project_id'] : 0;
            $titel = isset($data['titel']) ? trim($data['titel']) : '';
            $inhalt = isset($data['inhalt']) ? trim($data['inhalt']) : null;
            if (!$projectId || !canAccessProject($pdo, $projectId, $userRole)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO project_notes (project_id, titel, inhalt, erstellt_von) VALUES (?, ?, ?, ?)");
            $stmt->execute([$projectId, $titel ?: 'Notiz', $inhalt ?: null, $userId]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $noteId = isset($data['id']) ? (int)$data['id'] : 0;
            $titel = isset($data['titel']) ? trim($data['titel']) : null;
            $inhalt = isset($data['inhalt']) ? trim($data['inhalt']) : null;
            if (!$noteId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            $row = $pdo->prepare("SELECT project_id FROM project_notes WHERE id = ?");
            $row->execute([$noteId]);
            $note = $row->fetch(PDO::FETCH_ASSOC);
            if (!$note || !canAccessProject($pdo, $note['project_id'], $userRole)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notiz nicht gefunden']);
                exit;
            }
            $updates = [];
            $vals = [];
            if ($titel !== null) { $updates[] = "titel = ?"; $vals[] = $titel; }
            if ($inhalt !== null) { $updates[] = "inhalt = ?"; $vals[] = $inhalt; }
            if (empty($updates)) {
                echo json_encode(['success' => true]);
                exit;
            }
            $vals[] = $noteId;
            $pdo->prepare("UPDATE project_notes SET " . implode(", ", $updates) . " WHERE id = ?")->execute($vals);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $noteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$noteId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            $row = $pdo->prepare("SELECT project_id FROM project_notes WHERE id = ?");
            $row->execute([$noteId]);
            $note = $row->fetch(PDO::FETCH_ASSOC);
            if (!$note || !canAccessProject($pdo, $note['project_id'], $userRole)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notiz nicht gefunden']);
                exit;
            }
            $pdo->prepare("DELETE FROM project_notes WHERE id = ?")->execute([$noteId]);
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
