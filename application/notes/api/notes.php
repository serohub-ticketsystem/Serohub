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
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Nur Techniker und Admins haben Zugriff auf Notizen.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

try {
    $pdo->query("SELECT 1 FROM notes LIMIT 1");
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Notiz-Tabellen fehlen. Bitte Migration 080_notes_tables.sql ausführen.']);
    exit;
}

function canAccessNoteFolder($pdo, $folderId, $userId) {
    $stmt = $pdo->prepare("SELECT erstellt_von FROM note_folders WHERE id = ?");
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$folder) return false;
    if ($folder['erstellt_von'] == $userId) return true;
    $m = $pdo->prepare("SELECT 1 FROM note_folder_members WHERE folder_id = ? AND user_id = ?");
    $m->execute([$folderId, $userId]);
    return (bool) $m->fetch();
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';

            if ($action === 'company_customer') {
                $companyNotes = [];
                $stmt = $pdo->prepare("
                    SELECT p.id, p.company_id, p.title as titel, p.content as inhalt_raw, p.created_at as erstellt_datum, p.updated_at as geaendert_datum,
                           c.name as company_name
                    FROM kb_pages p
                    INNER JOIN kb_pages nf ON p.parent_id = nf.id AND nf.is_system_folder = 1 AND nf.system_type = 'notes' AND nf.deleted_at IS NULL
                    INNER JOIN companies c ON c.id = p.company_id
                    WHERE p.deleted_at IS NULL
                    ORDER BY p.updated_at DESC, p.created_at DESC
                ");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $inhalt = '';
                    if (!empty($row['inhalt_raw'])) {
                        $dec = json_decode($row['inhalt_raw'], true);
                        if (is_array($dec) && !empty($dec['content'])) {
                            foreach ($dec['content'] as $node) {
                                if (!empty($node['content'])) {
                                    foreach ($node['content'] as $leaf) {
                                        if (!empty($leaf['text'])) $inhalt .= $leaf['text'] . ' ';
                                    }
                                }
                            }
                        }
                    }
                    $row['inhalt'] = trim($inhalt);
                    unset($row['inhalt_raw']);
                    $row['type'] = 'company';
                    $row['detail_url'] = 'companies/detail.php?id=' . $row['company_id'];
                    $companyNotes[] = $row;
                }

                $customerNotes = [];
                $stmt = $pdo->prepare("
                    SELECT custn.id, custn.customer_id, custn.titel, custn.inhalt, custn.erstellt_datum, custn.geaendert_datum,
                           cust.name as customer_name, cust.company_id, c.name as company_name
                    FROM customer_notes custn
                    JOIN customers cust ON cust.id = custn.customer_id
                    LEFT JOIN companies c ON c.id = cust.company_id
                    ORDER BY custn.geaendert_datum DESC, custn.erstellt_datum DESC
                ");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['type'] = 'customer';
                    $row['detail_url'] = 'customers/detail.php?id=' . $row['customer_id'];
                    $customerNotes[] = $row;
                }

                echo json_encode([
                    'success' => true,
                    'company_notes' => $companyNotes,
                    'customer_notes' => $customerNotes
                ]);
                break;
            }

            $folderId = isset($_GET['folder_id']) ? (int) $_GET['folder_id'] : null;
            if ($folderId === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'folder_id erforderlich']);
                exit;
            }
            if (!canAccessNoteFolder($pdo, $folderId, $userId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diesen Ordner']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT n.id, n.folder_id, n.sort_order, n.titel, n.inhalt, n.erstellt_von, n.erstellt_datum, n.geaendert_datum,
                       u.vorname as ersteller_vorname, u.nachname as ersteller_nachname
                FROM notes n
                LEFT JOIN users u ON u.id = n.erstellt_von
                WHERE n.folder_id = ?
                ORDER BY COALESCE(n.sort_order, 999999) ASC, COALESCE(n.geaendert_datum, n.erstellt_datum) DESC
            ");
            $stmt->execute([$folderId]);
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'notes' => $notes]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['titel']) || empty($data['folder_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'titel und folder_id erforderlich']);
                exit;
            }
            $folderId = (int) $data['folder_id'];
            if (!canAccessNoteFolder($pdo, $folderId, $userId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diesen Ordner']);
                exit;
            }
            $titel = trim($data['titel']);
            $inhalt = isset($data['inhalt']) ? trim($data['inhalt']) : '';

            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM notes WHERE folder_id = ?");
            $maxOrder->execute([$folderId]);
            $nextOrder = (int) $maxOrder->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO notes (folder_id, sort_order, titel, inhalt, erstellt_von, erstellt_datum) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$folderId, $nextOrder, $titel, $inhalt, $userId]);
            $noteId = (int) $pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Notiz erstellt', 'note_id' => $noteId]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['action']) && $data['action'] === 'reorder') {
                $folderId = isset($data['folder_id']) ? (int) $data['folder_id'] : 0;
                $noteIds = isset($data['note_ids']) && is_array($data['note_ids']) ? array_map('intval', $data['note_ids']) : [];
                if (!$folderId || empty($noteIds) || !canAccessNoteFolder($pdo, $folderId, $userId)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE notes SET sort_order = ? WHERE id = ? AND folder_id = ?");
                foreach ($noteIds as $idx => $nid) {
                    $stmt->execute([$idx, $nid, $folderId]);
                }
                echo json_encode(['success' => true, 'message' => 'Reihenfolge gespeichert']);
                break;
            }
            if (empty($data['note_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'note_id erforderlich']);
                exit;
            }
            $noteId = (int) $data['note_id'];
            $stmt = $pdo->prepare("SELECT id, folder_id FROM notes WHERE id = ?");
            $stmt->execute([$noteId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$note || !canAccessNoteFolder($pdo, $note['folder_id'], $userId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Notiz nicht gefunden oder keine Berechtigung']);
                exit;
            }
            $titel = array_key_exists('titel', $data) ? trim($data['titel']) : null;
            $inhalt = array_key_exists('inhalt', $data) ? trim($data['inhalt']) : null;
            if ($titel !== null && $inhalt !== null) {
                $pdo->prepare("UPDATE notes SET titel = ?, inhalt = ?, geaendert_datum = NOW() WHERE id = ?")->execute([$titel, $inhalt, $noteId]);
            } elseif ($titel !== null) {
                $pdo->prepare("UPDATE notes SET titel = ?, geaendert_datum = NOW() WHERE id = ?")->execute([$titel, $noteId]);
            } elseif ($inhalt !== null) {
                $pdo->prepare("UPDATE notes SET inhalt = ?, geaendert_datum = NOW() WHERE id = ?")->execute([$inhalt, $noteId]);
            }
            echo json_encode(['success' => true, 'message' => 'Notiz aktualisiert']);
            break;

        case 'DELETE':
            $noteId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if (!$noteId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id, folder_id FROM notes WHERE id = ?");
            $stmt->execute([$noteId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$note || !canAccessNoteFolder($pdo, $note['folder_id'], $userId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Notiz nicht gefunden oder keine Berechtigung']);
                exit;
            }
            $pdo->prepare("DELETE FROM notes WHERE id = ?")->execute([$noteId]);
            echo json_encode(['success' => true, 'message' => 'Notiz gelöscht']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Notes API: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
