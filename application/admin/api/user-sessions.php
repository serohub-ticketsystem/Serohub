<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/admin_user_profile.php';
require_once dirname(__DIR__, 2) . '/assets/admin_user_sessions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

if (admin_require_admin_role($pdo, (int) $_SESSION['user_id']) === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

function admin_sessions_target_id(): int
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return (int) ($_GET['user_id'] ?? 0);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    return (int) ($data['user_id'] ?? 0);
}

$targetUserId = admin_sessions_target_id();
if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Benutzer-ID fehlt']);
    exit;
}

$exists = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
$exists->execute([$targetUserId]);
if (!$exists->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
    exit;
}

try {
    if ($method === 'GET') {
        $sessions = admin_user_load_sessions($pdo, $targetUserId);
        echo json_encode([
            'success' => true,
            'sessions' => $sessions,
            'remember_me_active' => admin_user_remember_me_active($pdo, $targetUserId),
        ]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = (string) ($data['action'] ?? '');

    if ($action === 'logout_everywhere') {
        admin_user_logout_everywhere($pdo, $targetUserId);
        echo json_encode(['success' => true, 'message' => 'Benutzer wurde auf allen Geräten abgemeldet']);
        exit;
    }

    if ($action === 'logout_device') {
        $ok = admin_user_logout_device(
            $pdo,
            $targetUserId,
            (int) ($data['id'] ?? 0),
            trim((string) ($data['sid'] ?? ''))
        );
        if (!$ok) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Gerät nicht gefunden']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Gerät wurde abgemeldet']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
} catch (PDOException $e) {
    error_log('admin/api/user-sessions.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
