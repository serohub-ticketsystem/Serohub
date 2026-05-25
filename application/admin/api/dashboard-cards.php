<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !in_array($user['rolle'], ['Admin', 'Techniker'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT dc.*, c.name as company_name
                    FROM dashboard_cards dc
                    LEFT JOIN companies c ON dc.company_id = c.id
                    WHERE dc.id = ?
                ");
                $stmt->execute([$id]);
                $card = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($card) {
                    echo json_encode(['success' => true, 'card' => $card]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Card nicht gefunden']);
                }
            } else {
                $stmt = $pdo->prepare("
                    SELECT dc.*, c.name as company_name
                    FROM dashboard_cards dc
                    LEFT JOIN companies c ON dc.company_id = c.id
                    ORDER BY dc.sort_order ASC, dc.id ASC
                ");
                $stmt->execute();
                $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'cards' => $cards]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['titel']) || !isset($data['nachricht'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Titel und Nachricht sind erforderlich']);
                exit;
            }
            $stmt = $pdo->prepare("
                INSERT INTO dashboard_cards (titel, nachricht, bild, bild_dark, button_text, button_link, typ, company_id, sort_order, aktiv)
                VALUES (:titel, :nachricht, :bild, :bild_dark, :button_text, :button_link, :typ, :company_id, :sort_order, :aktiv)
            ");
            $stmt->execute([
                ':titel' => trim($data['titel']),
                ':nachricht' => trim($data['nachricht']),
                ':bild' => !empty($data['bild']) ? trim($data['bild']) : null,
                ':bild_dark' => !empty($data['bild_dark']) ? trim($data['bild_dark']) : null,
                ':button_text' => !empty($data['button_text']) ? trim($data['button_text']) : null,
                ':button_link' => !empty($data['button_link']) ? trim($data['button_link']) : null,
                ':typ' => in_array($data['typ'] ?? '', ['info', 'warning'], true) ? ($data['typ']) : 'info',
                ':company_id' => !empty($data['company_id']) ? (int)$data['company_id'] : null,
                ':sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
                ':aktiv' => isset($data['aktiv']) ? (int)(bool)$data['aktiv'] : 1
            ]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            $id = (int)$data['id'];
            $stmt = $pdo->prepare("
                UPDATE dashboard_cards SET
                    titel = :titel,
                    nachricht = :nachricht,
                    bild = :bild,
                    bild_dark = :bild_dark,
                    button_text = :button_text,
                    button_link = :button_link,
                    typ = :typ,
                    company_id = :company_id,
                    sort_order = :sort_order,
                    aktiv = :aktiv
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':titel' => trim($data['titel'] ?? ''),
                ':nachricht' => trim($data['nachricht'] ?? ''),
                ':bild' => !empty($data['bild']) ? trim($data['bild']) : null,
                ':bild_dark' => !empty($data['bild_dark']) ? trim($data['bild_dark']) : null,
                ':button_text' => !empty($data['button_text']) ? trim($data['button_text']) : null,
                ':button_link' => !empty($data['button_link']) ? trim($data['button_link']) : null,
                ':typ' => in_array($data['typ'] ?? '', ['info', 'warning'], true) ? ($data['typ']) : 'info',
                ':company_id' => !empty($data['company_id']) ? (int)$data['company_id'] : null,
                ':sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
                ':aktiv' => isset($data['aktiv']) ? (int)(bool)$data['aktiv'] : 1
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            $id = (int)$data['id'];
            $stmt = $pdo->prepare("DELETE FROM dashboard_cards WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Dashboard Cards API: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
