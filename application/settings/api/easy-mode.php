<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/assets/config.php';
requireLogin();

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Easy Mode Einstellung abrufen
            $stmt = $pdo->prepare("
                SELECT setting_value 
                FROM user_settings 
                WHERE user_id = :user_id AND setting_key = 'easy_mode'
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $easyMode = false;
            if ($result && $result['setting_value'] === '1') {
                $easyMode = true;
            }
            
            echo json_encode([
                'success' => true,
                'easy_mode' => $easyMode
            ]);
            break;
            
        case 'POST':
            // Easy Mode Einstellung speichern
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['easy_mode'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'easy_mode ist erforderlich']);
                exit;
            }
            
            $easyMode = (bool)$data['easy_mode'] ? '1' : '0';
            
            $stmt = $pdo->prepare("
                INSERT INTO user_settings 
                (user_id, setting_key, setting_value, erstellt_datum, geaendert_datum)
                VALUES 
                (:user_id, 'easy_mode', :setting_value, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                setting_value = :setting_value_update,
                geaendert_datum = NOW()
            ");
            
            $success = $stmt->execute([
                ':user_id' => $userId,
                ':setting_value' => $easyMode,
                ':setting_value_update' => $easyMode
            ]);
            
            if ($success) {
                echo json_encode(['success' => true, 'easy_mode' => (bool)$easyMode]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
