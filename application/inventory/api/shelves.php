<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/inventory_permissions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

inventory_permissions_ensure_columns($pdo);
$invUser = inventory_permissions_load_user($pdo, $userId);
if (!$invUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
    exit;
}
$userRole = $invUser['rolle'];

if (!inventory_user_can_view_from_row($invUser)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für das Lager.']);
    exit;
}

$canEdit = inventory_user_can_full_edit($userRole);

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $stmt = $pdo->prepare("SELECT id, name, beschreibung, sort_order, COALESCE(spalten_anzahl, 5) AS spalten_anzahl, COALESCE(faecher_anzahl, 6) AS faecher_anzahl, erstellt_datum, geaendert_datum FROM shelves WHERE id = ?");
                $stmt->execute([$id]);
                $shelf = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$shelf) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Regal nicht gefunden']);
                    exit;
                }
                echo json_encode(['success' => true, 'shelf' => $shelf]);
                exit;
            }

            $stmt = $pdo->query("SELECT id, name, beschreibung, sort_order, COALESCE(spalten_anzahl, 5) AS spalten_anzahl, COALESCE(faecher_anzahl, 6) AS faecher_anzahl FROM shelves ORDER BY sort_order, name");
            $shelves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'shelves' => $shelves]);
            exit;

        case 'POST':
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $name = trim((string)($input['name'] ?? ''));
            if ($name === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                exit;
            }
            $beschreibung = trim((string)($input['beschreibung'] ?? '')) ?: null;
            $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
            $spaltenAnzahl = isset($input['spalten_anzahl']) ? max(1, min(20, (int)$input['spalten_anzahl'])) : 5;
            $faecherAnzahl = isset($input['faecher_anzahl']) ? max(1, min(20, (int)$input['faecher_anzahl'])) : 6;

            $stmt = $pdo->prepare("INSERT INTO shelves (name, beschreibung, sort_order, spalten_anzahl, faecher_anzahl) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $beschreibung, $sortOrder, $spaltenAnzahl, $faecherAnzahl]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            exit;

        case 'PUT':
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            $name = trim((string)($input['name'] ?? ''));
            if ($name === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                exit;
            }
            $beschreibung = trim((string)($input['beschreibung'] ?? '')) ?: null;
            $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
            $spaltenAnzahl = isset($input['spalten_anzahl']) ? max(1, min(20, (int)$input['spalten_anzahl'])) : 5;
            $faecherAnzahl = isset($input['faecher_anzahl']) ? max(1, min(20, (int)$input['faecher_anzahl'])) : 6;

            $stmt = $pdo->prepare("UPDATE shelves SET name = ?, beschreibung = ?, sort_order = ?, spalten_anzahl = ?, faecher_anzahl = ? WHERE id = ?");
            $stmt->execute([$name, $beschreibung, $sortOrder, $spaltenAnzahl, $faecherAnzahl, $id]);
            if ($stmt->rowCount() === 0) {
                // rowCount() kann 0 sein, wenn Werte unverändert bleiben.
                // Deshalb separat prüfen, ob das Regal existiert.
                $existsStmt = $pdo->prepare("SELECT id FROM shelves WHERE id = ? LIMIT 1");
                $existsStmt->execute([$id]);
                if (!$existsStmt->fetchColumn()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Regal nicht gefunden']);
                    exit;
                }
            }
            echo json_encode(['success' => true]);
            exit;

        case 'DELETE':
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM shelves WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Regal nicht gefunden']);
                exit;
            }
            echo json_encode(['success' => true]);
            exit;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ein Regal mit diesem Namen existiert bereits.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    }
}
