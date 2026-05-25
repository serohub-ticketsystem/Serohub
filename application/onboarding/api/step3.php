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

    if (!empty($data['skip'])) {
        $_SESSION['onboarding_contact_step_seen'] = true;
        echo json_encode(['success' => true, 'message' => 'Übersprungen']);
        exit;
    }

    $stepCheck = $pdo->prepare('SELECT letztes_pw_change, vorname, nachname FROM users WHERE id = ?');
    $stepCheck->execute([$userId]);
    $stepUser = $stepCheck->fetch(PDO::FETCH_ASSOC);

    if (!$stepUser || empty($stepUser['letztes_pw_change']) || empty($_SESSION['onboarding_profile_step_seen'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bitte schließen Sie zuerst die vorherigen Schritte ab.']);
        exit;
    }

    $telefonnummer = trim($data['telefonnummer'] ?? '');

    $profileData = [
        'mobilnummer' => $data['mobilnummer'] ?? '',
        'kontaktkanal' => $data['kontaktkanal'] ?? 'email',
        'erreichbarkeit' => $data['erreichbarkeit'] ?? '',
    ];

    $updates = [
        'telefonnummer = :telefonnummer',
        'geaendert_datum = NOW()',
    ];
    $params = [
        ':telefonnummer' => $telefonnummer === '' ? null : $telefonnummer,
        ':user_id' => $userId,
    ];

    $profileExtra = user_profile_fields_build_updates($profileData, $pdo, $userId);
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

    $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :user_id')->execute($params);

    $kontaktkanal = trim((string) ($profileData['kontaktkanal'] ?? 'email'));
    user_profile_fields_sync_email_notifications_for_kontaktkanal($pdo, $userId, $kontaktkanal);

    $_SESSION['onboarding_contact_step_seen'] = true;

    echo json_encode(['success' => true, 'message' => 'Kontaktdaten gespeichert']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Onboarding Step 3 API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Onboarding Step 3 API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Serverfehler']);
}
