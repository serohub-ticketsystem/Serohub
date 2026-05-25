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
            // E-Mail-Einstellung abrufen
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'email_enabled'");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Gleiche Logik wie in assets/email.php: standardmäßig deaktiviert; nur explizit "an" = aktiviert
            $emailEnabled = false;
            if ($result && $result['setting_value'] !== null && $result['setting_value'] !== '') {
                $v = is_string($result['setting_value']) ? strtolower(trim($result['setting_value'])) : $result['setting_value'];
                if (in_array($v, ['1', 'true', 'yes', 'on'], true) || $v === true) {
                    $emailEnabled = true;
                }
            }
            
            echo json_encode([
                'success' => true,
                'email_enabled' => $emailEnabled
            ]);
            break;
            
        case 'POST':
        case 'PUT':
            // E-Mail-Einstellung speichern
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
                VALUES (:user_id, 'email_enabled', :setting_value, NOW())
                ON DUPLICATE KEY UPDATE 
                    setting_value = :setting_value_update,
                    geaendert_datum = NOW()
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':setting_value', $enabledValue);
            $stmt->bindValue(':setting_value_update', $enabledValue);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'email_enabled' => $enabled]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Email Settings API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
