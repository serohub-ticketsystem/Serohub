<?php
session_start();
require_once dirname(__DIR__) . '/assets/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$surveyId = isset($input['survey_id']) ? (int)$input['survey_id'] : 1;
$rating = isset($input['rating']) ? (int)$input['rating'] : 0;
$feedback = isset($input['feedback']) ? trim($input['feedback']) : null;
if ($feedback !== null && mb_strlen($feedback) > 2000) {
    $feedback = mb_substr($feedback, 0, 2000);
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Bewertung']);
    exit;
}

try {
    // Bereits geantwortet für diese Umfrage?
    $check = $pdo->prepare("SELECT id FROM satisfaction_survey_responses WHERE user_id = ? AND survey_id = ? LIMIT 1");
    $check->execute([$_SESSION['user_id'], $surveyId]);
    if ($check->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Bereits abgegeben']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    $companyId = $u ? $u['company_id'] : null;
    
    $stmt = $pdo->prepare("INSERT INTO satisfaction_survey_responses (survey_id, user_id, company_id, rating, feedback) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$surveyId, $_SESSION['user_id'], $companyId, $rating, $feedback]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('satisfaction-survey-response: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern']);
}
