<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/sidebar_counts.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$companyId = isset($input['company_id']) && $input['company_id'] !== '' && $input['company_id'] !== '0' ? (int)$input['company_id'] : null;

// Firma in Session speichern
$_SESSION['selected_company_id'] = $companyId;

if (isset($_SESSION['user_id'], $pdo) && $pdo instanceof PDO) {
    sidebarTicketsSyncCompanyFilterSnapshot($pdo, (int) $_SESSION['user_id'], $companyId);
}

echo json_encode([
    'success' => true,
    'company_id' => $companyId
]);
