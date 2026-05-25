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
$method = $_SERVER['REQUEST_METHOD'];
$allowedValues = ['all', 'company', 'filters'];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'sidebar_tickets_count'");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $value = ($row && $row['setting_value'] !== null && in_array($row['setting_value'], $allowedValues, true))
                ? $row['setting_value']
                : 'company';
            echo json_encode(['success' => true, 'sidebar_tickets_count' => $value]);
            break;

        case 'POST':
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $value = isset($data['sidebar_tickets_count']) ? trim((string) $data['sidebar_tickets_count']) : '';
            if (!in_array($value, $allowedValues, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültiger Wert. Erlaubt: all, company, filters']);
                exit;
            }
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'sidebar_tickets_count', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $stmt->execute([$userId, $value]);
            echo json_encode(['success' => true, 'sidebar_tickets_count' => $value]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Sidebar Tickets Count Settings API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
