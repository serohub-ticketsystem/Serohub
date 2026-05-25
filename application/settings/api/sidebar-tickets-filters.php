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

if ($method !== 'POST' && $method !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
    exit;
}

$allowedStatus = [
    'offen_combined', 'neu', 'in_bearbeitung', 'warteschlange', 'bestellung_offen',
    'geschlossen', 'archiv', 'ohne_bearbeitungszeit', 'angeheftet', 'geplant',
];
$status = isset($data['status']) ? trim((string) $data['status']) : 'offen_combined';
if (!in_array($status, $allowedStatus, true)) {
    $status = 'offen_combined';
}

$customer = isset($data['customer']) ? trim((string) $data['customer']) : '';
if ($customer !== '' && !ctype_digit($customer)) {
    $customer = '';
}

$assignee = isset($data['assignee']) ? trim((string) $data['assignee']) : '';
if ($assignee !== '' && !ctype_digit($assignee)) {
    $assignee = '';
}

$companyId = null;
if (isset($data['company_id']) && $data['company_id'] !== '' && $data['company_id'] !== null) {
    $companyId = (int) $data['company_id'];
    if ($companyId <= 0) {
        $companyId = null;
    }
}

$payload = json_encode([
    'status' => $status,
    'customer' => $customer,
    'assignee' => $assignee,
    'company_id' => $companyId,
], JSON_UNESCAPED_UNICODE);

try {
    $stmt = $pdo->prepare("
        INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
        VALUES (?, 'sidebar_tickets_filters', ?, NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
    ");
    $stmt->execute([$userId, $payload]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Sidebar Tickets Filters API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
