<?php
/**
 * Liefert den öffentlichen VAPID-Schlüssel für PushManager.subscribe (nur lesbar, kein Geheimnis).
 */
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/push_notifications.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$cred = webpush_get_vapid_credentials();

echo json_encode([
    'success'        => true,
    'push_available' => $cred !== null,
    'publicKey'      => $cred['publicKey'] ?? null,
], JSON_UNESCAPED_UNICODE);
