<?php
declare(strict_types=1);
/**
 * Alte Pfade unter /service/ → /tickets/ (permanent, POST-sicher für API).
 */
function legacy_service_redirect_to_tickets(string $ticketsSuffix, bool $force307 = false): void
{
    require_once dirname(__DIR__) . '/assets/config.php';
    if (!defined('BASE_URL')) {
        define('BASE_URL', '/');
    }
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
    $path = rtrim(BASE_URL, '/') . '/tickets/' . ltrim($ticketsSuffix, '/');
    $target = $path . $qs;
    if ($force307 || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        http_response_code(307);
    } else {
        http_response_code(308);
    }
    header('Location: ' . $target);
    exit;
}
