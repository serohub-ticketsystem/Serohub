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

// Benutzerdaten und Rolle abrufen
try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
        exit;
    }
    
    $userRole = $user['rolle'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
    exit;
}

// Nur Admin kann Passwörter ändern
if ($userRole !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

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

    // Validierung
    if (empty($data['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Benutzer-ID ist erforderlich']);
        exit;
    }

    if (empty($data['new_password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Neues Passwort ist erforderlich']);
        exit;
    }

    // Neues Passwort muss mindestens 8 Zeichen lang sein
    if (strlen($data['new_password']) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Das neue Passwort muss mindestens 8 Zeichen lang sein']);
        exit;
    }

    $targetUserId = (int)$data['user_id'];

    // Prüfen ob Zielbenutzer existiert
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
        exit;
    }

    // Neues Passwort hashen
    $newPasswordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);

    // Passwort in Datenbank aktualisieren
    $stmt = $pdo->prepare("UPDATE users SET passwort = ? WHERE id = ?");
    $stmt->execute([$newPasswordHash, $targetUserId]);

    echo json_encode([
        'success' => true,
        'message' => 'Passwort erfolgreich geändert'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Admin User Password API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Admin User Password API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten']);
}
?>
