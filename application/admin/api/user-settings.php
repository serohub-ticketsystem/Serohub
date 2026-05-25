<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/admin_user_profile.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$sessionUserId = (int) $_SESSION['user_id'];
if (admin_require_admin_role($pdo, $sessionUserId) === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || empty($data['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
    exit;
}

$targetUserId = (int) $data['user_id'];
$action = (string) ($data['action'] ?? '');

$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$targetUserId]);
if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
    exit;
}

try {
    if ($action === 'reset_dismissed_cards') {
        $pdo->prepare("DELETE FROM user_settings WHERE user_id = ? AND setting_key = 'dismissed_cards'")->execute([$targetUserId]);
        $pdo->prepare("DELETE FROM user_settings WHERE user_id = ? AND setting_key = 'tickets_assigned_card_dismissed_count'")->execute([$targetUserId]);
        echo json_encode(['success' => true, 'message' => 'Verworfene Cards zurückgesetzt']);
        exit;
    }

    if ($action === 'update_setting' && !empty($data['setting_key'])) {
        $key = (string) $data['setting_key'];
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ungültiger Schlüssel']);
            exit;
        }
        $blocked = ['2fa_secret'];
        if (in_array($key, $blocked, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dieser Schlüssel kann nicht direkt bearbeitet werden']);
            exit;
        }
        $value = isset($data['setting_value']) ? (string) $data['setting_value'] : '';
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum, geaendert_datum)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
        ");
        $stmt->execute([$targetUserId, $key, $value]);
        echo json_encode(['success' => true, 'message' => 'Einstellung gespeichert']);
        exit;
    }

    if ($action === 'delete_setting' && !empty($data['setting_key'])) {
        $key = (string) $data['setting_key'];
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ungültiger Schlüssel']);
            exit;
        }
        $sensitive = ['2fa_secret'];
        if (in_array($key, $sensitive, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dieser Schlüssel kann nicht per API gelöscht werden']);
            exit;
        }
        $pdo->prepare('DELETE FROM user_settings WHERE user_id = ? AND setting_key = ?')->execute([$targetUserId, $key]);
        echo json_encode(['success' => true, 'message' => 'Einstellung gelöscht']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
} catch (PDOException $e) {
    error_log('admin/api/user-settings.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
