<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Werbeemails-Einstellung abrufen
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'marketing_emails_enabled'");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $marketingEnabled = false; // Standardwert: keine Werbeemails
            if ($result && $result['setting_value'] !== null) {
                $marketingEnabled = filter_var($result['setting_value'], FILTER_VALIDATE_BOOLEAN);
            }
            
            echo json_encode([
                'success' => true,
                'marketing_emails_enabled' => $marketingEnabled
            ]);
            break;
            
        case 'POST':
        case 'PUT':
            // Werbeemails-Einstellung speichern
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['enabled'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'enabled fehlt']);
                exit;
            }
            
            $enabled = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);
            $enabledValue = $enabled ? '1' : '0';
            
            // Speichern oder aktualisieren
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (:user_id, 'marketing_emails_enabled', :setting_value, NOW())
                ON DUPLICATE KEY UPDATE 
                    setting_value = :setting_value_update,
                    geaendert_datum = NOW()
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':setting_value', $enabledValue);
            $stmt->bindValue(':setting_value_update', $enabledValue);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'marketing_emails_enabled' => $enabled]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Marketing Emails API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
