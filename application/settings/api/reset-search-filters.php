<?php
/**
 * Setzt gespeicherte Such- und Systemfilter eines Benutzers auf Standard zurück.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $keys = ['ticket_search_scope', 'order_search_scope', 'global_search_scope'];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $sql = "DELETE FROM user_settings WHERE user_id = ? AND setting_key IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $keys));
    unset($_SESSION['selected_company_id']);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Reset Search Filters API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
