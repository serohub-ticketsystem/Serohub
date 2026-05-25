<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$settingKeysToReset = [
    'notification_settings',
    'notification_hide_own',
    'system_notifications_enabled',
    'push_notifications_enabled',
    'email_enabled',
    'toast_display',
    'sounds_enabled',
    'chat_display_name',
    'sidebar_expanded',
    'sidebar_expand_on_hover',
    'sidebar_todos_count',
    'sidebar_tickets_count',
    'sidebar_tickets_filters',
    'speed_dial_items',
    'speed_dial_visible',
    'ticket_tasks_require_folder',
    'project_tasks_require_folder',
    'ticket_search_scope',
    'order_search_scope',
    'global_search_scope',
    'mobile_start_page',
    'mobile_start_enabled',
    'easy_mode',
    'company_favorites',
];

try {
    $pdo->beginTransaction();

    $placeholders = implode(',', array_fill(0, count($settingKeysToReset), '?'));
    $sql = "DELETE FROM user_settings WHERE user_id = ? AND setting_key IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $settingKeysToReset));

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log('Reset all settings API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Zurücksetzen fehlgeschlagen']);
}

