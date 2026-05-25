<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

// Prüfen ob eingeloggt und Admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['rolle'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
    exit;
}

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

    // E-Mail-Empfang-Einstellungen speichern
    $settings = [
        'email_receive_enabled' => $data['email_receive_enabled'] ? '1' : '0',
        'email_receive_protocol' => $data['email_receive_protocol'] ?? 'imap',
        'email_receive_host' => $data['email_receive_host'] ?? '',
        'email_receive_port' => (string)($data['email_receive_port'] ?? 993),
        'email_receive_secure' => $data['email_receive_secure'] ?? 'ssl',
        'email_receive_username' => $data['email_receive_username'] ?? '',
        'email_receive_mailbox' => $data['email_receive_mailbox'] ?? 'INBOX'
    ];
    
    // Passwort nur speichern wenn es angegeben wurde
    if (!empty($data['email_receive_password'])) {
        $settings['email_receive_password'] = $data['email_receive_password'];
    }

    // Einstellungen in Datenbank speichern
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (:setting_key, :setting_value)
        ON DUPLICATE KEY UPDATE 
            setting_value = :setting_value_update,
            geaendert_datum = NOW()
    ");

    foreach ($settings as $key => $value) {
        $stmt->bindValue(':setting_key', $key);
        $stmt->bindValue(':setting_value', $value);
        $stmt->bindValue(':setting_value_update', $value);
        $stmt->execute();
    }

    echo json_encode([
        'success' => true,
        'message' => 'E-Mail-Empfang-Einstellungen erfolgreich gespeichert'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Email Receive Settings API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Email Receive Settings API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten']);
}
