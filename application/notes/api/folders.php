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
    $stmt = $pdo->prepare("SELECT id, rolle, vorname, nachname FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Nur Techniker und Admins haben Zugriff auf Notizen.']);
        exit;
    }
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? '')) ?: 'Unbekannt';
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Prüfen ob Tabellen existieren
try {
    $pdo->query("SELECT 1 FROM note_folders LIMIT 1");
    $pdo->query("SELECT 1 FROM note_folder_members LIMIT 1");
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Notiz-Tabellen fehlen. Bitte Migration 080_notes_tables.sql ausführen.']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            if (!empty($_GET['candidates'])) {
                $stmt = $pdo->prepare("
                    SELECT id, vorname, nachname, email, rolle
                    FROM users
                    WHERE id != ? AND status = 'aktiv' AND rolle IN ('Techniker', 'Admin')
                    ORDER BY nachname, vorname
                ");
                $stmt->execute([$userId]);
                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'candidates' => $candidates]);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    nf.id,
                    nf.name,
                    nf.is_private,
                    nf.erstellt_von,
                    COUNT(n.id) as note_count
                FROM note_folders nf
                LEFT JOIN notes n ON n.folder_id = nf.id
                WHERE nf.erstellt_von = :user_id 
                   OR EXISTS (SELECT 1 FROM note_folder_members m WHERE m.folder_id = nf.id AND m.user_id = :user_id2)
                GROUP BY nf.id, nf.name, nf.is_private, nf.erstellt_von
                ORDER BY nf.name ASC
            ");
            $stmt->execute([':user_id' => $userId, ':user_id2' => $userId]);
            $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($folders as &$f) {
                $f['is_private'] = (int)($f['is_private'] ?? 1);
                $f['member_ids'] = [];
                $mStmt = $pdo->prepare("SELECT user_id FROM note_folder_members WHERE folder_id = ?");
                $mStmt->execute([$f['id']]);
                $f['member_ids'] = array_map('intval', array_column($mStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
            }
            unset($f);

            echo json_encode(['success' => true, 'folders' => $folders]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                exit;
            }
            $name = trim($data['name']);
            $memberIds = [];
            if (!empty($data['member_ids']) && is_array($data['member_ids'])) {
                $memberIds = array_values(array_unique(array_diff(array_map('intval', $data['member_ids']), [$userId])));
            }

            $stmt = $pdo->prepare("INSERT INTO note_folders (name, is_private, erstellt_von, erstellt_datum) VALUES (?, 1, ?, NOW())");
            $stmt->execute([$name, $userId]);
            $folderId = (int) $pdo->lastInsertId();

            foreach ($memberIds as $uid) {
                $ins = $pdo->prepare("INSERT INTO note_folder_members (folder_id, user_id) VALUES (?, ?)");
                $ins->execute([$folderId, $uid]);
            }

            echo json_encode(['success' => true, 'message' => 'Ordner erstellt', 'folder_id' => $folderId]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['folder_id']) || !isset($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'folder_id und name erforderlich']);
                exit;
            }
            $folderId = (int) $data['folder_id'];
            $name = trim($data['name']);
            $memberIds = [];
            if (isset($data['member_ids']) && is_array($data['member_ids'])) {
                $memberIds = array_values(array_unique(array_diff(array_map('intval', $data['member_ids']), [$userId])));
            }

            $check = $pdo->prepare("SELECT erstellt_von FROM note_folders WHERE id = ?");
            $check->execute([$folderId]);
            $folder = $check->fetch(PDO::FETCH_ASSOC);
            if (!$folder) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ordner nicht gefunden']);
                exit;
            }
            $isMember = false;
            $mCheck = $pdo->prepare("SELECT 1 FROM note_folder_members WHERE folder_id = ? AND user_id = ?");
            $mCheck->execute([$folderId, $userId]);
            $isMember = (bool) $mCheck->fetch();
            if ($folder['erstellt_von'] != $userId && !$isMember) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            if ($folder['erstellt_von'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Nur der Ersteller darf den Ordner bearbeiten']);
                exit;
            }

            $pdo->prepare("UPDATE note_folders SET name = ? WHERE id = ?")->execute([$name, $folderId]);
            $pdo->prepare("DELETE FROM note_folder_members WHERE folder_id = ?")->execute([$folderId]);
            foreach ($memberIds as $uid) {
                $ins = $pdo->prepare("INSERT INTO note_folder_members (folder_id, user_id) VALUES (?, ?)");
                $ins->execute([$folderId, $uid]);
            }
            echo json_encode(['success' => true, 'message' => 'Ordner aktualisiert']);
            break;

        case 'DELETE':
            $folderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if (!$folderId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            $check = $pdo->prepare("SELECT erstellt_von FROM note_folders WHERE id = ?");
            $check->execute([$folderId]);
            $folder = $check->fetch(PDO::FETCH_ASSOC);
            if (!$folder) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ordner nicht gefunden']);
                exit;
            }
            $mCheck = $pdo->prepare("SELECT 1 FROM note_folder_members WHERE folder_id = ? AND user_id = ?");
            $mCheck->execute([$folderId, $userId]);
            $isMember = (bool) $mCheck->fetch();
            if ($folder['erstellt_von'] != $userId && !$isMember) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            if ($folder['erstellt_von'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Nur der Ersteller darf den Ordner löschen']);
                exit;
            }
            $pdo->prepare("DELETE FROM note_folders WHERE id = ?")->execute([$folderId]);
            echo json_encode(['success' => true, 'message' => 'Ordner gelöscht']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Notes Folders API: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
