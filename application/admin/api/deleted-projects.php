<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/service_log_helper.php';
require_once dirname(__DIR__, 2) . '/todos/helper/encryption.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $userStmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = :user_id LIMIT 1");
    $userStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }

    if (($user['rolle'] ?? '') !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

$hasDeletedAt = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'deleted_at'");
    $hasDeletedAt = $col && $col->rowCount() > 0;
} catch (PDOException $e) {}

if (!$hasDeletedAt) {
    echo json_encode(['success' => true, 'projects' => []]);
    exit;
}

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT
                p.id,
                p.project_nummer,
                p.bezeichnung,
                p.status,
                p.deleted_at,
                c.name AS company_name,
                CONCAT(COALESCE(u.vorname, ''), ' ', COALESCE(u.nachname, '')) AS creator_name
            FROM projects p
            LEFT JOIN companies c ON p.company_id = c.id
            LEFT JOIN users u ON p.erstellt_von = u.id
            WHERE p.deleted_at IS NOT NULL
            ORDER BY p.deleted_at DESC
            LIMIT 1000
        ");
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($projects as &$p) {
            if (array_key_exists('company_name', $p)) {
                $p['company_name'] = decrypt_from_db($p['company_name']);
            }
        }
        unset($p);
        echo json_encode(['success' => true, 'projects' => $projects]);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '[]', true);
    $projectId = isset($data['project_id']) ? (int)$data['project_id'] : 0;
    if ($projectId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'project_id fehlt']);
        exit;
    }

    $checkStmt = $pdo->prepare("SELECT id, bezeichnung, deleted_at FROM projects WHERE id = :id LIMIT 1");
    $checkStmt->bindValue(':id', $projectId, PDO::PARAM_INT);
    $checkStmt->execute();
    $project = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
        exit;
    }

    if (empty($project['deleted_at'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Projekt ist nicht soft-gelöscht']);
        exit;
    }

    if ($method === 'POST') {
        $restoreStmt = $pdo->prepare("
            UPDATE projects
            SET deleted_at = NULL,
                geaendert_datum = NOW()
            WHERE id = :id
        ");
        $restoreStmt->bindValue(':id', $projectId, PDO::PARAM_INT);
        $restoreStmt->execute();
        if (function_exists('service_log')) {
            service_log($pdo, $userId, 'sonstiges', $projectId, 'updated', 'project', null, null, 'Projekt wiederhergestellt');
        }
        echo json_encode(['success' => true, 'message' => 'Projekt wiederhergestellt']);
        exit;
    }

    if ($method === 'DELETE') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM project_tickets WHERE project_id = ?")->execute([$projectId]);
            $pdo->prepare("DELETE FROM project_notes WHERE project_id = ?")->execute([$projectId]);
            $pdo->prepare("UPDATE orders SET project_id = NULL WHERE project_id = ?")->execute([$projectId]);
            $pdo->prepare("UPDATE todos SET project_id = NULL WHERE project_id = ?")->execute([$projectId]);
            $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$projectId]);
            $pdo->commit();

            if (function_exists('service_log')) {
                service_log($pdo, $userId, 'sonstiges', $projectId, 'deleted', 'project', (string)$projectId, null, 'Projekt endgültig gelöscht');
            }
            echo json_encode(['success' => true, 'message' => 'Projekt endgültig gelöscht']);
            exit;
        } catch (Throwable $inner) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $inner;
        }
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Deleted projects API Fehler: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Deleted projects API Fehler: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unerwarteter Fehler']);
}
