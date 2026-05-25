<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/user_profile_fields.php';

header('Content-Type: application/json');

user_profile_fields_ensure_columns($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $vorname = trim($data['vorname'] ?? '');
    $nachname = trim($data['nachname'] ?? '');
    $email = trim($data['email'] ?? '');

    if ($vorname === '' || $nachname === '' || $email === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bitte füllen Sie alle Pflichtfelder aus.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültige E-Mail-Adresse']);
        exit;
    }

    $emailCheckStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :user_id');
    $emailCheckStmt->execute([':email' => $email, ':user_id' => $userId]);
    if ($emailCheckStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Diese E-Mail-Adresse wird bereits von einem anderen Benutzer verwendet']);
        exit;
    }

    $step1Check = $pdo->prepare('SELECT letztes_pw_change FROM users WHERE id = ?');
    $step1Check->execute([$userId]);
    $step1User = $step1Check->fetch(PDO::FETCH_ASSOC);
    if (!$step1User || empty($step1User['letztes_pw_change'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bitte schließen Sie zuerst Schritt 1 ab.']);
        exit;
    }

    $updates = [
        'vorname = :vorname',
        'nachname = :nachname',
        'email = :email',
        'geaendert_datum = NOW()',
    ];
    $params = [
        ':vorname' => $vorname,
        ':nachname' => $nachname,
        ':email' => $email,
        ':user_id' => $userId,
    ];

    $profileExtra = user_profile_fields_build_updates(['anrede' => $data['anrede'] ?? ''], $pdo, $userId);
    if ($profileExtra['error'] !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $profileExtra['error']]);
        exit;
    }
    foreach ($profileExtra['updates'] as $sqlPart) {
        $updates[] = $sqlPart;
    }
    foreach ($profileExtra['params'] as $key => $value) {
        $params[$key] = $value;
    }

    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :user_id';
    $pdo->prepare($sql)->execute($params);

    $_SESSION['onboarding_profile_step_seen'] = true;

    echo json_encode(['success' => true, 'message' => 'Stammdaten gespeichert']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Onboarding Step 2 API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Onboarding Step 2 API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Serverfehler']);
}
