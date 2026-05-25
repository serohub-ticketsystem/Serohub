<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Admin' && $user['rolle'] !== 'Techniker')) {
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

if ($method === 'GET') {
    $settings = ['lagersystem_api_key' => '', 'lagersystem_user_id' => ''];
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('lagersystem_api_key', 'lagersystem_user_id')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    // API-Key nicht im Klartext zurückgeben, nur anzeigen ob gesetzt
    $settings['lagersystem_api_key_set'] = $settings['lagersystem_api_key'] !== '';
    $settings['lagersystem_api_key'] = ''; // Nie an Client senden
    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
        exit;
    }

    $apiKey = isset($data['lagersystem_api_key']) ? trim((string)$data['lagersystem_api_key']) : '';
    $userIdSetting = isset($data['lagersystem_user_id']) && $data['lagersystem_user_id'] !== '' && $data['lagersystem_user_id'] !== null
        ? (int)$data['lagersystem_user_id'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE setting_value = :v2, geaendert_datum = NOW()
    ");

    // User-ID speichern
    $stmt->execute([
        ':k' => 'lagersystem_user_id',
        ':v' => $userIdSetting !== null ? (string)$userIdSetting : '',
        ':v2' => $userIdSetting !== null ? (string)$userIdSetting : ''
    ]);

    // API-Key nur aktualisieren wenn ein neuer (nicht leerer) Schlüssel angegeben wurde
    if ($apiKey !== '') {
        $stmt->execute([
            ':k' => 'lagersystem_api_key',
            ':v' => $apiKey,
            ':v2' => $apiKey
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Einstellungen gespeichert']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
