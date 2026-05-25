<?php
/**
 * Liste der aktiven CalDAV-Server (für Benutzer im Kalender-Bereich)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'servers' => []]);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT id, name, url, beschreibung
        FROM caldav_servers
        WHERE is_active = 1
        ORDER BY sort_order ASC, name ASC
    ");
    $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'servers' => $servers]);
} catch (PDOException $e) {
    echo json_encode(['success' => true, 'servers' => []]);
}
