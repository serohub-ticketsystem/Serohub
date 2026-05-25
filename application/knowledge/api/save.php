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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST erlaubt']);
    exit;
}

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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = isset($input['id']) ? trim($input['id']) : null;
$title = isset($input['title']) ? trim($input['title']) : null;
$content = isset($input['content']) ? $input['content'] : null;
$contentType = isset($input['content_type']) ? trim($input['content_type']) : 'json';

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Seiten-ID fehlt']);
    exit;
}

try {
    // Vor dem Update: aktuellen Zustand in Historie speichern (wer wann was geändert hat – alter Stand)
    $current = $pdo->prepare("SELECT title, content, content_type FROM kb_pages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $current->execute([$id]);
    $old = $current->fetch(PDO::FETCH_ASSOC);
    if ($old && ($title !== null || $content !== null)) {
        try {
            $ins = $pdo->prepare("INSERT INTO kb_page_versions (page_id, title, content, content_type, created_at, created_by) VALUES (?, ?, ?, ?, NOW(), ?)");
            $ins->execute([
                $id,
                $old['title'] ?? '',
                $old['content'] ?? '',
                isset($old['content_type']) ? $old['content_type'] : 'json',
                $userId
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                // Migration 095 noch nicht ausgeführt: nur page_id, content, created_by
                $ins = $pdo->prepare("INSERT INTO kb_page_versions (page_id, content, created_at, created_by) VALUES (?, ?, NOW(), ?)");
                $ins->execute([$id, $old['content'] ?? '', $userId]);
            } else {
                throw $e;
            }
        }
    }

    // Firmenordner und Systemordner: Titel nicht überschreiben
    $skipTitleUpdate = false;
    if ($title !== null) {
        $check = $pdo->prepare("SELECT parent_id, company_id, is_system_folder FROM kb_pages WHERE id = ? LIMIT 1");
        $check->execute([$id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if ($row && (( $row['parent_id'] === null && !empty($row['company_id']) ) || !empty($row['is_system_folder']))) {
            $skipTitleUpdate = true;
        }
        if (!$skipTitleUpdate) {
            $stmt = $pdo->prepare("UPDATE kb_pages SET title = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $id]);
        }
    }
    if ($content !== null) {
        $raw = is_string($content) ? $content : json_encode($content);
        $stmt = $pdo->prepare("UPDATE kb_pages SET content = ?, content_type = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$raw, $contentType, $id]);
    }
    $stmt = $pdo->prepare("SELECT updated_at FROM kb_pages WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'updated_at' => $row['updated_at'] ?? null]);
} catch (PDOException $e) {
    error_log('KB save: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Speichern fehlgeschlagen']);
}
