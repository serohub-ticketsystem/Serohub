<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';

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

// Nur Admin kann Benutzer bearbeiten
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

    $targetUserId = (int)$data['user_id'];

    // Prüfen ob Zielbenutzer existiert
    $stmt = $pdo->prepare("SELECT id, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
        exit;
    }

    // Prüfen ob Benutzer gesperrt ist (dann keine Änderungen erlauben)
    if ($targetUser['status'] === 'gesperrt') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Gesperrte Benutzer können nicht bearbeitet werden']);
        exit;
    }

    // Update-Query aufbauen
    $updates = [];
    $params = [];

    // Status aktualisieren
    if (isset($data['status'])) {
        $allowedStatus = ['aktiv', 'inaktiv'];
        if (in_array($data['status'], $allowedStatus)) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
    }

    // Rolle aktualisieren
    if (isset($data['rolle'])) {
        $updates[] = "rolle = ?";
        $params[] = $data['rolle'];
    }

    // Passwort ändern erforderlich aktualisieren
    if (isset($data['passwort_aendern_erforderlich'])) {
        $updates[] = "passwort_aendern_erforderlich = ?";
        $params[] = (int)$data['passwort_aendern_erforderlich'];
    }

    if (isset($data['lager_bestand_anpassen'])) {
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'lager_bestand_anpassen'");
            if ($colStmt && $colStmt->rowCount() > 0) {
                $updates[] = "lager_bestand_anpassen = ?";
                $params[] = (int)$data['lager_bestand_anpassen'] ? 1 : 0;
            }
        } catch (PDOException $e) { /* Spalte fehlt */ }
    }

    if (isset($data['vorname'])) {
        $updates[] = 'vorname = ?';
        $params[] = trim((string) $data['vorname']);
    }
    if (isset($data['nachname'])) {
        $updates[] = 'nachname = ?';
        $params[] = trim((string) $data['nachname']);
    }
    if (isset($data['telefonnummer'])) {
        $updates[] = 'telefonnummer = ?';
        $params[] = trim((string) $data['telefonnummer']) ?: null;
    }
    if (array_key_exists('company_id', $data)) {
        $cid = $data['company_id'];
        $updates[] = 'company_id = ?';
        $params[] = ($cid === null || $cid === '' || (int) $cid <= 0) ? null : (int) $cid;
    }
    if (isset($data['email'])) {
        $email = trim((string) $data['email']);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ungültige E-Mail-Adresse']);
            exit;
        }
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $dup->execute([$email, $targetUserId]);
        if ($dup->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'E-Mail wird bereits verwendet']);
            exit;
        }
        $updates[] = 'email = ?';
        $params[] = $email;
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Keine Änderungen angegeben']);
        exit;
    }

    // Update ausführen
    $params[] = $targetUserId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Benutzer erfolgreich aktualisiert'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Admin User Update API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Admin User Update API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten']);
}
?>
