<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Zugriff verweigert']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

$pageId = isset($_GET['page_id']) ? trim($_GET['page_id']) : null;
$versionId = isset($_GET['id']) ? (int) $_GET['id'] : null;
if (!$pageId && !$versionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'page_id oder id fehlt']);
    exit;
}

try {
    if ($versionId) {
        // Einzelversion mit vollem Inhalt (für Anzeige "was davor da stand")
        $sql = "SELECT v.id, v.page_id, v.title, v.content, v.content_type, v.created_at, v.created_by, u.vorname, u.nachname, u.email FROM kb_page_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.id = ? LIMIT 1";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$versionId]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $sql = "SELECT v.id, v.page_id, v.content, v.created_at, v.created_by, u.vorname, u.nachname, u.email FROM kb_page_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.id = ? LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$versionId]);
            } else {
                throw $e;
            }
        }
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Version nicht gefunden']);
            exit;
        }
        $item = [
            'id' => (int) $r['id'],
            'page_id' => $r['page_id'],
            'title' => $r['title'] ?? null,
            'content' => $r['content'] ?? '',
            'content_type' => $r['content_type'] ?? 'json',
            'created_at' => $r['created_at'],
            'created_by' => (int) $r['created_by'],
            'user_name' => trim(($r['vorname'] ?? '') . ' ' . ($r['nachname'] ?? '')) ?: ($r['email'] ?? 'Unbekannt'),
        ];
        echo json_encode(['success' => true, 'version' => $item]);
        exit;
    }

    if (!$pageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'page_id fehlt']);
        exit;
    }

    // Prüfen ob Seite existiert und Nutzer Zugriff hat
    $check = $pdo->prepare("SELECT id FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $check->execute([$pageId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Seite nicht gefunden']);
        exit;
    }

    $hasTitle = true;
    try {
        $pdo->query("SELECT title FROM kb_page_versions LIMIT 1");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $hasTitle = false;
        } else {
            throw $e;
        }
    }

    $cols = $hasTitle
        ? "v.id, v.page_id, v.title, v.content, v.content_type, v.created_at, v.created_by, u.vorname, u.nachname, u.email"
        : "v.id, v.page_id, v.content, v.created_at, v.created_by, u.vorname, u.nachname, u.email";
    $sql = "SELECT $cols FROM kb_page_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.page_id = ? ORDER BY v.created_at DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pageId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ($rows as $r) {
        $item = [
            'id' => (int) $r['id'],
            'page_id' => $r['page_id'],
            'created_at' => $r['created_at'],
            'created_by' => (int) $r['created_by'],
            'user_name' => trim(($r['vorname'] ?? '') . ' ' . ($r['nachname'] ?? '')) ?: ($r['email'] ?? 'Unbekannt'),
        ];
        if ($hasTitle && array_key_exists('title', $r)) {
            $item['title'] = $r['title'];
        } else {
            $item['title'] = null;
        }
        if (array_key_exists('content_type', $r)) {
            $item['content_type'] = $r['content_type'];
        } else {
            $item['content_type'] = 'json';
        }
        $content = $r['content'] ?? '';
        $item['content_preview'] = mb_substr(strip_tags($content), 0, 200);
        $item['content_length'] = mb_strlen($content);
        $list[] = $item;
    }

    echo json_encode(['success' => true, 'history' => $list]);
} catch (PDOException $e) {
    error_log('KB page-history: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Historie konnte nicht geladen werden']);
}
