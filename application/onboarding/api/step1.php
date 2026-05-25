<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__) . '/includes/password_rules.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?? [];

        $newPassword = (string) ($data['new_password'] ?? '');
        $confirmPassword = (string) ($data['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT passwort, vorname, nachname, email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
            exit;
        }

        if (!empty($data['check_only'])) {
            echo json_encode([
                'success' => true,
                'different' => !onboarding_password_is_same_as_stored($newPassword, (string) ($user['passwort'] ?? '')),
            ]);
            exit;
        }

        $userHints = onboarding_password_user_hints($user);
        $validationError = onboarding_password_validation_error($newPassword, $confirmPassword, $userHints, (string) ($user['passwort'] ?? ''));
        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $validationError]);
            exit;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare('UPDATE users SET passwort = ?, letztes_pw_change = NOW(), passwort_zuruecksetzen = 0 WHERE id = ?');
        $updateStmt->execute([$hashedPassword, $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'Passwort erfolgreich geändert',
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Onboarding Step 1 API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Onboarding Step 1 API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Serverfehler']);
}
