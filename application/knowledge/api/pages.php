<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Zugriff nur für Techniker und Admins.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

$companyId = !empty($_SESSION['selected_company_id']) ? (int) $_SESSION['selected_company_id'] : null;
if ($companyId === 0) $companyId = null;

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', strtolower($text));
    $text = trim($text, '-');
    return $text ?: 'seite';
}

try {
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? trim($_GET['id']) : null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT id, title, slug, content, content_type, parent_id, order_index, created_at, updated_at, author_id, company_id FROM kb_pages WHERE id = ? AND (deleted_at IS NULL) LIMIT 1");
                $stmt->execute([$id]);
                $page = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$page) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Seite nicht gefunden']);
                    exit;
                }
                $path = [];
                $currentId = $page['parent_id'];
                while ($currentId) {
                    $stmt = $pdo->prepare("SELECT id, title, parent_id FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$currentId]);
                    $ancestor = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$ancestor) break;
                    array_unshift($path, ['id' => $ancestor['id'], 'title' => $ancestor['title']]);
                    $currentId = $ancestor['parent_id'];
                }
                echo json_encode(['success' => true, 'page' => $page, 'path' => $path]);
                exit;
            }
            $parentId = isset($_GET['parent_id']) ? trim($_GET['parent_id']) : null;
            if ($parentId === '') $parentId = null;
            
            $filteredRoot = false;
            // Globaler Firmenfilter gesetzt: Root = nur Inhalt des Firmenordners (keine Firmenliste)
            if ($parentId === null && $companyId !== null) {
                $stmt = $pdo->prepare("SELECT id FROM kb_pages WHERE company_id = ? AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1");
                $stmt->execute([$companyId]);
                $companyFolder = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($companyFolder) {
                    $parentId = $companyFolder['id'];
                    $filteredRoot = true;
                } else {
                    $pages = [];
                    echo json_encode(['success' => true, 'pages' => $pages, 'filtered_root' => false]);
                    break;
                }
            }
            
            if ($parentId === null) {
                // Oberste Ebene: Firmenordner (mit Logo) + freie Seiten (parent_id + company_id NULL)
                $sql = "SELECT p.id, p.title, p.slug, p.parent_id, p.order_index, p.created_at, p.updated_at, p.author_id, p.company_id,
                        COALESCE(p.is_system_folder, 0) as is_system_folder, p.system_type,
                        (SELECT COUNT(*) FROM kb_pages c WHERE c.parent_id = p.id AND c.deleted_at IS NULL) as children_count,
                        (CASE WHEN p.content IS NOT NULL AND LENGTH(TRIM(p.content)) > 55 THEN 1 ELSE 0 END) as has_content,
                        co.name AS company_name, co.logo AS company_logo
                        FROM kb_pages p
                        LEFT JOIN companies co ON co.id = p.company_id
                        WHERE p.deleted_at IS NULL AND p.parent_id IS NULL
                        AND (p.company_id IS NULL OR co.id IS NOT NULL)
                        ORDER BY (CASE WHEN p.company_id IS NOT NULL THEN 0 ELSE 1 END) ASC, p.order_index ASC, p.created_at ASC";
                $stmt = $pdo->query($sql);
            } else {
                // Unterebene: Kinder des gewählten Ordners (bei Firmenfilter auch Seiten mit company_id NULL, die unter dem Firmenordner liegen)
                $sql = "SELECT p.id, p.title, p.slug, p.parent_id, p.order_index, p.created_at, p.updated_at, p.author_id, p.company_id,
                        COALESCE(p.is_system_folder, 0) as is_system_folder, p.system_type,
                        (SELECT COUNT(*) FROM kb_pages c WHERE c.parent_id = p.id AND c.deleted_at IS NULL) as children_count,
                        (CASE WHEN p.content IS NOT NULL AND LENGTH(TRIM(p.content)) > 55 THEN 1 ELSE 0 END) as has_content
                        FROM kb_pages p
                        WHERE p.deleted_at IS NULL 
                        AND p.parent_id = ?";
                if ($companyId !== null) {
                    $sql .= " AND (p.company_id = ? OR p.company_id IS NULL)";
                }
                $sql .= " ORDER BY (CASE WHEN COALESCE(p.is_system_folder, 0) = 1 AND p.system_type = 'calls' THEN 0 WHEN COALESCE(p.is_system_folder, 0) = 1 AND p.system_type = 'notes' THEN 1 WHEN COALESCE(p.is_system_folder, 0) = 1 AND p.system_type = 'problems' THEN 2 WHEN COALESCE(p.is_system_folder, 0) = 1 AND p.system_type = 'wiki' THEN 3 ELSE 4 END) ASC, p.order_index ASC, p.created_at ASC";
                $stmt = $pdo->prepare($sql);
                if ($companyId !== null) {
                    $stmt->execute([$parentId, $companyId]);
                } else {
                    $stmt->execute([$parentId]);
                }
            }
            $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pages as &$p) {
                if (array_key_exists('company_name', $p)) {
                    $p['company_name'] = decrypt_from_db($p['company_name']);
                }
            }
            unset($p);
            echo json_encode(['success' => true, 'pages' => $pages, 'filtered_root' => $filteredRoot]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $title = isset($input['title']) ? trim($input['title']) : 'Neue Seite';
            $parentId = isset($input['parent_id']) ? trim($input['parent_id']) : null;
            if ($parentId === '') $parentId = null;
            
            // Wenn keine parent_id angegeben und company_id gesetzt: Firmenordner als parent_id verwenden
            if ($parentId === null && $companyId !== null) {
                $stmt = $pdo->prepare("SELECT id FROM kb_pages WHERE company_id = ? AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1");
                $stmt->execute([$companyId]);
                $companyFolder = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($companyFolder) {
                    $parentId = $companyFolder['id'];
                }
            }
            
            $baseSlug = slugify($title);
            $slug = $baseSlug;
            $n = 0;
            while (true) {
                $check = $pdo->prepare("SELECT id FROM kb_pages WHERE slug = ? LIMIT 1");
                $check->execute([$slug]);
                if (!$check->fetch()) break;
                $n++;
                $slug = $baseSlug . ($n > 1 ? '-' . $n : '-1');
            }
            $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x4000) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
            $defaultContent = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph']]]);
            $contentType = 'json';
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), -1) + 1 AS next_pos FROM kb_pages WHERE (parent_id IS NULL AND ? IS NULL) OR (parent_id <=> ?)");
            $stmt->execute([$parentId, $parentId]);
            $nextOrder = (int) $stmt->fetch(PDO::FETCH_ASSOC)['next_pos'];
            $stmt = $pdo->prepare("INSERT INTO kb_pages (id, title, slug, content, content_type, parent_id, order_index, author_id, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $title, $slug, $defaultContent, $contentType, $parentId, $nextOrder, $userId, $companyId]);
            echo json_encode(['success' => true, 'id' => $id, 'slug' => $slug, 'title' => $title]);
            break;

        case 'PATCH':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = isset($input['id']) ? trim($input['id']) : null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            $newParentId = null;
            if (array_key_exists('parent_id', $input)) {
                $newParentId = (isset($input['parent_id']) && $input['parent_id'] !== '') ? trim($input['parent_id']) : null;
            } elseif (array_key_exists('parentId', $input)) {
                $newParentId = (isset($input['parentId']) && $input['parentId'] !== '') ? trim($input['parentId']) : null;
            }
            $newOrderIndex = array_key_exists('order_index', $input) ? (int) $input['order_index'] : null;

            $stmt = $pdo->prepare("SELECT id, parent_id, is_system_folder FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$page) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Seite nicht gefunden']);
                exit;
            }
            if (!empty($page['is_system_folder'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Systemordner können nicht verschoben werden']);
                exit;
            }
            $idStr = (string) $id;
            $currentParent = $page['parent_id'] !== null && $page['parent_id'] !== '' ? (string) $page['parent_id'] : null;
            $newParentStr = $newParentId !== null && $newParentId !== '' ? (string) $newParentId : null;
            if ($currentParent === $newParentStr) {
                echo json_encode(['success' => true]);
                break;
            }
            if ($newParentId !== null) {
                if ($newParentStr === $idStr) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Seite kann nicht in sich selbst verschoben werden']);
                    exit;
                }
                if ($newParentId !== '') {
                    $stmt = $pdo->prepare("SELECT id, is_system_folder, system_type FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$newParentId]);
                    $targetPage = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$targetPage) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Zielordner nicht gefunden']);
                        exit;
                    }
                    if (!empty($targetPage['is_system_folder']) && ($targetPage['system_type'] ?? '') !== 'wiki') {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'In Systemordner kann nicht verschoben werden']);
                        exit;
                    }
                }
            }

            $updates = [];
            $params = [];
            if (array_key_exists('parent_id', $input) || array_key_exists('parentId', $input)) {
                $updates[] = 'parent_id = ?';
                $params[] = $newParentId;
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), -1) + 1 AS next_pos FROM kb_pages WHERE (parent_id IS NULL AND ? IS NULL) OR (parent_id <=> ?)");
                $stmt->execute([$newParentId, $newParentId]);
                $updates[] = 'order_index = ?';
                $params[] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['next_pos'];
            }
            if ($newOrderIndex !== null && !array_key_exists('parent_id', $input) && !array_key_exists('parentId', $input)) {
                $updates[] = 'order_index = ?';
                $params[] = $newOrderIndex;
            }
            if (empty($updates)) {
                echo json_encode(['success' => true]);
                break;
            }
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE kb_pages SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?");
            $stmt->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = isset($input['id']) ? trim($input['id']) : null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT parent_id, is_system_folder FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($page && !empty($page['is_system_folder'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Systemordner können nicht gelöscht werden']);
                exit;
            }
            if ($page) {
                $newParentId = $page['parent_id'];
                $stmt = $pdo->prepare("UPDATE kb_pages SET parent_id = ?, updated_at = NOW() WHERE parent_id = ? AND deleted_at IS NULL");
                $stmt->execute([$newParentId, $id]);
            }
            $stmt = $pdo->prepare("UPDATE kb_pages SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    error_log('KB pages API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
