<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $userStmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || ($user['rolle'] ?? '') !== 'Admin') {
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
    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT
                p.id,
                p.title,
                p.slug,
                p.created_at,
                p.updated_at,
                p.deleted_at,
                p.parent_id,
                p.company_id,
                c.name AS company_name,
                CONCAT(COALESCE(u.vorname, ''), ' ', COALESCE(u.nachname, '')) AS author_name
            FROM kb_pages p
            LEFT JOIN companies c ON p.company_id = c.id
            LEFT JOIN users u ON p.author_id = u.id
            WHERE p.deleted_at IS NOT NULL
            AND COALESCE(p.is_system_folder, 0) = 0
            ORDER BY p.deleted_at DESC
            LIMIT 500
        ");
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'pages' => $pages]);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '[]', true);
    $pageId = isset($data['page_id']) ? trim($data['page_id']) : null;
    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'page_id fehlt']);
        exit;
    }

    $checkStmt = $pdo->prepare("SELECT id, title, parent_id, is_system_folder FROM kb_pages WHERE id = ? LIMIT 1");
    $checkStmt->execute([$pageId]);
    $page = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Seite nicht gefunden']);
        exit;
    }

    if (!empty($page['is_system_folder'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Systemordner können nicht bearbeitet werden']);
        exit;
    }

    if ($method === 'POST') {
        $restoreStmt = $pdo->prepare("UPDATE kb_pages SET deleted_at = NULL, updated_at = NOW() WHERE id = ?");
        $restoreStmt->execute([$pageId]);
        echo json_encode(['success' => true, 'message' => 'Seite wiederhergestellt']);
        exit;
    }

    if ($method === 'DELETE') {
        $parentId = $page['parent_id'];
        $moveStmt = $pdo->prepare("UPDATE kb_pages SET parent_id = ? WHERE parent_id = ?");
        $moveStmt->execute([$parentId, $pageId]);
        $deleteStmt = $pdo->prepare("DELETE FROM kb_pages WHERE id = ?");
        $deleteStmt->execute([$pageId]);
        echo json_encode(['success' => true, 'message' => 'Seite endgültig gelöscht']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Deleted pages API Fehler: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
