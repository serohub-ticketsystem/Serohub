<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nicht autorisiert';
    exit;
}

$userId = (int)$_SESSION['user_id'];
$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(403);
        exit;
    }
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'] ? (int)$user['company_id'] : null;
} catch (PDOException $e) {
    http_response_code(500);
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.* FROM downloads d
    WHERE d.id = ? AND d.typ = 'datei' AND d.dateipfad IS NOT NULL
");
$stmt->execute([$id]);
$download = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$download) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Verknüpfung nicht gefunden';
    exit;
}

$canSee = false;
if ($download['sichtbar_fuer'] === 'alle') {
    $canSee = true;
} elseif ($download['sichtbar_fuer'] === 'firma' && $userCompanyId && (int)$download['company_id'] === $userCompanyId) {
    $canSee = true;
} elseif ($userRole === 'Admin' || $userRole === 'Techniker') {
    $canSee = true;
}
if ($canSee && (int)(isset($download['intern']) ? $download['intern'] : 0) === 1 && $userRole !== 'Admin' && $userRole !== 'Techniker') {
    $canSee = false;
}

if (!$canSee) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Keine Berechtigung';
    exit;
}

$baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$fullPath = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $download['dateipfad']);
// Unterstütze beide Pfade (uploads/downloads und uploads/links) für Übergangszeit
if (!file_exists($fullPath) || !is_readable($fullPath)) {
    $altPath = strpos($download['dateipfad'], 'uploads/links/') !== false
        ? str_replace('uploads/links/', 'uploads/downloads/', $download['dateipfad'])
        : str_replace('uploads/downloads/', 'uploads/links/', $download['dateipfad']);
    $fullPath = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $altPath);
}
if (!file_exists($fullPath) || !is_readable($fullPath)) {
    http_response_code(404);
    exit;
}

$filename = $download['dateiname'] ?: basename($download['dateipfad']);
$filename = preg_replace('/[^\w\s.-]/', '_', $filename);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: no-cache, must-revalidate');
readfile($fullPath);
exit;
