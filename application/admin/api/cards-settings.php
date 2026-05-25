<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/assets/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// Prüfen ob Benutzer Admin ist
$userId = $_SESSION['user_id'];
$userRole = null;
try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userRole = $user['rolle'];
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

if ($userRole !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
        exit;
    }

    // Daten aus Request lesen
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['cards']) || !is_array($data['cards'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
        exit;
    }

    // Prüfe ob system_settings Tabelle existiert, erstelle sie falls nicht
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                erstellt_datum DATETIME DEFAULT CURRENT_TIMESTAMP,
                geaendert_datum DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        // Tabelle existiert bereits oder Fehler beim Erstellen
        error_log("System Settings Tabelle: " . $e->getMessage());
    }

    // Card-Einstellungen als JSON speichern
    $cardsJson = json_encode($data['cards']);
    
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES ('easy_mode_cards_enabled', :setting_value)
        ON DUPLICATE KEY UPDATE 
            setting_value = :setting_value_update,
            geaendert_datum = NOW()
    ");
    
    $stmt->execute([
        ':setting_value' => $cardsJson,
        ':setting_value_update' => $cardsJson
    ]);

    // Easy Mode Tickets klickbar Einstellung speichern
    if (isset($data['easy_mode_tickets_clickable'])) {
        $ticketsClickable = $data['easy_mode_tickets_clickable'] ? '1' : '0';
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('easy_mode_tickets_clickable', :setting_value)
            ON DUPLICATE KEY UPDATE 
                setting_value = :setting_value_update,
                geaendert_datum = NOW()
        ");
        
        $stmt->execute([
            ':setting_value' => $ticketsClickable,
            ':setting_value_update' => $ticketsClickable
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Easy Mode Card-Einstellungen erfolgreich gespeichert'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Cards Settings API: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
