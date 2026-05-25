<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, vorname, nachname FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? '')) ?: 'Unbekannt';
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

function getNotesFolderId($pdo, $companyId) {
    $stmt = $pdo->prepare("SELECT nf.id FROM kb_pages nf
        INNER JOIN kb_pages cf ON nf.parent_id = cf.id AND cf.company_id = ? AND cf.parent_id IS NULL AND cf.deleted_at IS NULL
        WHERE nf.is_system_folder = 1 AND nf.system_type = 'notes' AND nf.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$companyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['id'] : null;
}

function extractTextFromTipTapJson($content) {
    if (empty($content)) return '';
    if (is_string($content)) {
        $dec = json_decode($content, true);
        $content = $dec ? $dec : null;
    }
    if (!is_array($content)) return '';
    $texts = [];
    if (!empty($content['content']) && is_array($content['content'])) {
        foreach ($content['content'] as $node) {
            if (!empty($node['content']) && is_array($node['content'])) {
                foreach ($node['content'] as $leaf) {
                    if (!empty($leaf['text'])) $texts[] = $leaf['text'];
                }
            }
            if (!empty($node['text'])) $texts[] = $node['text'];
        }
    }
    return implode(' ', $texts);
}

try {
    switch ($method) {
        case 'GET':
            if (!isset($_GET['company_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id fehlt']);
                exit;
            }
            $companyId = (int)$_GET['company_id'];

            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                if ($userRole !== 'Firmen-Admin' || $userCompanyId != $companyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
            }

            $folderId = getNotesFolderId($pdo, $companyId);
            if (!$folderId) {
                echo json_encode(['success' => true, 'notes' => []]);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT p.id, p.title, p.content, p.content_type, p.created_at, p.updated_at, p.author_id,
                       u.vorname as ersteller_vorname, u.nachname as ersteller_nachname
                FROM kb_pages p
                LEFT JOIN users u ON p.author_id = u.id
                WHERE p.parent_id = ? AND p.deleted_at IS NULL
                ORDER BY p.order_index ASC, p.created_at DESC
            ");
            $stmt->execute([$folderId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notes = [];
            foreach ($rows as $r) {
                $inhalt = extractTextFromTipTapJson($r['content']);
                $notes[] = [
                    'id' => $r['id'],
                    'company_id' => $companyId,
                    'titel' => $r['title'] ?? '',
                    'inhalt' => $inhalt,
                    'erstellt_datum' => $r['created_at'],
                    'geaendert_datum' => $r['updated_at'],
                    'ersteller_vorname' => $r['ersteller_vorname'],
                    'ersteller_nachname' => $r['ersteller_nachname']
                ];
            }
            echo json_encode(['success' => true, 'notes' => $notes]);
            break;

        case 'POST':
            if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['company_id']) || !isset($data['titel'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id und titel sind erforderlich']);
                exit;
            }
            $companyId = (int)$data['company_id'];
            $titel = trim($data['titel'] ?? 'Neue Notiz');
            $inhalt = isset($data['inhalt']) ? trim($data['inhalt']) : '';

            if ($userRole === 'Firmen-Admin' && $userCompanyId != $companyId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Firma']);
                exit;
            }

            $folderId = getNotesFolderId($pdo, $companyId);
            if (!$folderId) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notizen-Ordner für diese Firma nicht gefunden']);
                exit;
            }

            $slug = preg_replace('~[^\pL\d]+~u', '-', mb_strtolower($titel));
            $slug = trim($slug, '-') ?: 'notiz-' . uniqid();
            $check = $pdo->prepare("SELECT id FROM kb_pages WHERE slug = ? LIMIT 1");
            $check->execute([$slug]);
            if ($check->fetch()) $slug = $slug . '-' . substr(md5(uniqid()), 0, 8);

            $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x4000) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
            $defaultContent = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => $inhalt !== '' ? [['type' => 'text', 'text' => $inhalt]] : []]]]);
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), -1) + 1 FROM kb_pages WHERE parent_id = ? AND deleted_at IS NULL");
            $stmt->execute([$folderId]);
            $orderIndex = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO kb_pages (id, title, slug, content, content_type, parent_id, order_index, author_id, company_id, created_at, updated_at) VALUES (?, ?, ?, ?, 'json', ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$id, $titel, $slug, $defaultContent, $folderId, $orderIndex, $userId, $companyId]);

            try {
                $logStmt = $pdo->prepare("INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum) VALUES ('company', ?, ?, 'created', ?, NOW())");
                $logStmt->execute([$companyId, $userId, "Notiz erstellt: " . $titel]);
            } catch (PDOException $e) { error_log("Log: " . $e->getMessage()); }

            $companyName = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $companyName->execute([$companyId]);
            $companyName = $companyName->fetchColumn() ?: 'Unbekannt';
            createNotificationsForAction($userId, $companyId, 'company_note_created', 'Neue Notiz erstellt: ' . $titel,
                'Eine neue Notiz "' . $titel . '" wurde von ' . $userName . ' für die Firma "' . $companyName . '" erstellt.', 'normal', 'companies/detail.php?id=' . $companyId, 'company', $companyId);

            echo json_encode(['success' => true, 'message' => 'Notiz erfolgreich erstellt', 'note_id' => $id]);
            break;

        case 'DELETE':
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
            $noteId = trim($_GET['id']);

            $stmt = $pdo->prepare("SELECT p.id, p.title, p.company_id FROM kb_pages p WHERE p.id = ? AND p.deleted_at IS NULL LIMIT 1");
            $stmt->execute([$noteId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$note) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notiz nicht gefunden']);
                exit;
            }
            if ($userRole === 'Firmen-Admin' && $userCompanyId != $note['company_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE kb_pages SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$noteId]);

            try {
                $logStmt = $pdo->prepare("INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum) VALUES ('company', ?, ?, 'deleted', ?, NOW())");
                $logStmt->execute([$note['company_id'], $userId, "Notiz gelöscht: " . $note['title']]);
            } catch (PDOException $e) { error_log("Log: " . $e->getMessage()); }
            $companyName = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $companyName->execute([$note['company_id']]);
            $companyName = $companyName->fetchColumn() ?: 'Unbekannt';
            createNotificationsForAction($userId, $note['company_id'], 'company_note_deleted', 'Notiz gelöscht: ' . $note['title'],
                'Die Notiz "' . $note['title'] . '" wurde von ' . $userName . ' gelöscht.', 'hoch', 'companies/detail.php?id=' . $note['company_id'], 'company', $note['company_id']);

            echo json_encode(['success' => true, 'message' => 'Notiz erfolgreich gelöscht']);
            break;

        case 'PUT':
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
            $noteId = trim($data['id']);
            $titel = trim($data['titel']);
            $inhalt = trim($data['inhalt']);
            if (empty($titel)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Titel darf nicht leer sein']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, company_id, title FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$noteId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$note) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notiz nicht gefunden']);
                exit;
            }
            if ($userRole === 'Firmen-Admin' && $userCompanyId != $note['company_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }

            $content = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => $inhalt !== '' ? [['type' => 'text', 'text' => $inhalt]] : []]]]);
            $stmt = $pdo->prepare("UPDATE kb_pages SET title = ?, content = ?, content_type = 'json', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$titel, $content, $noteId]);

            try {
                $logStmt = $pdo->prepare("INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum) VALUES ('company', ?, ?, 'updated', ?, NOW())");
                $logStmt->execute([$note['company_id'], $userId, "Notiz aktualisiert: " . $titel]);
            } catch (PDOException $e) { error_log("Log: " . $e->getMessage()); }
            $companyName = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $companyName->execute([$note['company_id']]);
            $companyName = $companyName->fetchColumn() ?: 'Unbekannt';
            createNotificationsForAction($userId, $note['company_id'], 'company_note_updated', 'Notiz aktualisiert: ' . $titel,
                'Die Notiz "' . $titel . '" wurde von ' . $userName . ' aktualisiert.', 'normal', 'companies/detail.php?id=' . $note['company_id'], 'company', $note['company_id']);

            echo json_encode(['success' => true, 'message' => 'Notiz erfolgreich aktualisiert']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    error_log("Company Notes API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
