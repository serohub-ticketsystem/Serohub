<?php
/**
 * Wissensdatenbank: Keine eigene View mehr – Weiterleitung direkt zur Bearbeitung (edit.php).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/assets/config.php';
require_once __DIR__ . '/kb_helpers.php';
requireLogin();

$basePath = (defined('BASE_URL') && BASE_URL !== '') ? rtrim(BASE_URL, '/') . '/' : '/';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') {
    header('Location: ' . $basePath . 'knowledge/');
    exit;
}

$companyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null
    ? (int) $_SESSION['selected_company_id'] : null;

$pageId = null;
try {
    $companyCond = $companyId !== null ? ' AND company_id = :cid' : '';
    $stmt = $pdo->prepare("SELECT id FROM kb_pages WHERE slug = :slug AND deleted_at IS NULL" . $companyCond . " LIMIT 1");
    $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
    if ($companyId !== null) $stmt->bindValue(':cid', $companyId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $pageId = $row['id'];
} catch (PDOException $e) { /* ignore */ }

if ($pageId) {
    header('Location: ' . $basePath . 'knowledge/edit.php?id=' . urlencode($pageId));
} else {
    header('Location: ' . $basePath . 'knowledge/');
}
exit;
