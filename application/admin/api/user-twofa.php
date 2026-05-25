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

if (admin_require_admin_role($pdo, (int) $_SESSION['user_id']) === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$stmt = $pdo->prepare('SELECT id, email, vorname, nachname FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$targetUserId]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
    exit;
}

try {
    if ($action !== 'disable') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
        exit;
    }

    $enStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_enabled' LIMIT 1");
    $enStmt->execute([$targetUserId]);
    $en = $enStmt->fetch(PDO::FETCH_ASSOC);
    if (!$en || ($en['setting_value'] ?? '') !== '1') {
        echo json_encode(['success' => false, 'message' => '2FA ist für diesen Benutzer nicht aktiv']);
        exit;
    }

    $pdo->beginTransaction();
    $pdo->prepare("
        UPDATE user_settings SET setting_value = '0', geaendert_datum = NOW()
        WHERE user_id = ? AND setting_key = '2fa_enabled'
    ")->execute([$targetUserId]);
    $pdo->prepare("DELETE FROM user_settings WHERE user_id = ? AND setting_key = '2fa_secret'")->execute([$targetUserId]);
    try {
        $pdo->prepare('DELETE FROM trusted_devices WHERE user_id = ?')->execute([$targetUserId]);
    } catch (PDOException $e) {
        // Tabelle optional
    }
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Zwei-Faktor-Authentifizierung wurde deaktiviert']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('admin/api/user-twofa.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
