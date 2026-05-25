<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, vorname, nachname, email, rolle
        FROM users
        WHERE rolle IN ('Admin', 'Techniker') AND status = 'aktiv'
        ORDER BY nachname, vorname
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $list = [];
    foreach ($rows as $r) {
        $list[] = [
            'id' => (int) $r['id'],
            'name' => trim(($r['vorname'] ?? '') . ' ' . ($r['nachname'] ?? '')) ?: $r['email'],
            'vorname' => $r['vorname'] ?? '',
            'nachname' => $r['nachname'] ?? '',
            'rolle' => $r['rolle']
        ];
    }
    echo json_encode(['success' => true, 'users' => $list]);
} catch (PDOException $e) {
    error_log('Kalender users API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
