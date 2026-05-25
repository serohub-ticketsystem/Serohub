<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

requireLogin();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$sessionId = session_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

try {
    $now = date('Y-m-d H:i:s');
    // Alle anderen Sessions invalidieren: nur Sessions mit created_at >= $now gelten noch
    $pdo->prepare("
        INSERT INTO user_settings (user_id, setting_key, setting_value)
        VALUES (?, 'sessions_valid_after', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ")->execute([$userId, $now]);
    // Aktuelle Session behalten: created_at auf jetzt setzen
    $pdo->prepare("UPDATE user_sessions SET created_at = ? WHERE session_id = ? AND user_id = ?")
        ->execute([$now, $sessionId, $userId]);
    // Alle anderen Einträge aus user_sessions löschen (Übersicht aufräumen)
    $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?")
        ->execute([$userId, $sessionId]);
    // Alle „Angemeldet bleiben“-Token dieses Users löschen, damit andere Geräte nicht per Cookie wieder reinkommen
    $pdo->prepare("DELETE FROM remember_me_tokens WHERE user_id = ?")->execute([$userId]);
    echo json_encode(['success' => true, 'message' => 'Alle anderen Geräte wurden abgemeldet.']);
} catch (PDOException $e) {
    error_log("Logout everywhere: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Aktion fehlgeschlagen.']);
}
