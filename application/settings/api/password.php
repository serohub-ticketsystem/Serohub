<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
        exit;
    }

    // Daten aus Request lesen
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
        exit;
    }

    $action = isset($data['action']) ? (string) $data['action'] : 'change';

    // Validierung
    if (empty($data['current_password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aktuelles Passwort ist erforderlich']);
        exit;
    }

    // Aktuelles Passwort aus Datenbank abrufen
    $stmt = $pdo->prepare("SELECT passwort FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
        exit;
    }

    // Aktuelles Passwort verifizieren
    if (!password_verify($data['current_password'], $user['passwort'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Das aktuelle Passwort ist falsch']);
        exit;
    }

    if ($action === 'verify_current') {
        echo json_encode([
            'success' => true,
            'message' => 'Aktuelles Passwort bestätigt'
        ]);
        exit;
    }

    if (empty($data['new_password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Neues Passwort ist erforderlich']);
        exit;
    }

    if (empty($data['confirm_password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwort-Bestätigung ist erforderlich']);
        exit;
    }

    // Passwörter müssen übereinstimmen
    if ($data['new_password'] !== $data['confirm_password']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Die Passwörter stimmen nicht überein']);
        exit;
    }

    // Neues Passwort muss mindestens 8 Zeichen lang sein
    if (strlen($data['new_password']) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Das neue Passwort muss mindestens 8 Zeichen lang sein']);
        exit;
    }

    // Prüfen ob neues Passwort sich vom alten unterscheidet
    if (password_verify($data['new_password'], $user['passwort'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Das neue Passwort darf nicht dasselbe wie das aktuelle Passwort sein']);
        exit;
    }

    // Neues Passwort hashen
    $newPasswordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);

    // Passwort in Datenbank aktualisieren und passwort_zuruecksetzen Flag auf 0 setzen
    $stmt = $pdo->prepare("UPDATE users SET passwort = ?, passwort_zuruecksetzen = 0 WHERE id = ?");
    $stmt->execute([$newPasswordHash, $userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Passwort erfolgreich geändert'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Password Reset API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Password Reset API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten']);
}
