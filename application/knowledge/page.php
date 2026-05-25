<?php
/**
 * Weiterleitung: Alte URLs knowledge/page.php?id=... bzw. ?embed=1&id=...
 * leiten auf knowledge/ (index.php) mit denselben Parametern um.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
$base = rtrim(BASE_URL, '/') . '/knowledge/';
$query = $_SERVER['QUERY_STRING'] ?? '';
$url = $base . ($query !== '' ? '?' . $query : '');
header('Location: ' . $url, true, 301);
exit;
