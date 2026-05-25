<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/assets/config.php';

// Nur für Admins
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

// Prüfe ob Benutzer Admin ist
try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
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

// Prüfe ob system_settings Tabelle existiert
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
    // Tabelle existiert bereits
    error_log("System Settings Tabelle: " . $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Template-Zuordnungen speichern
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES (:setting_key, :setting_value)
                ON DUPLICATE KEY UPDATE 
                    setting_value = :setting_value_update,
                    geaendert_datum = NOW()
            ");
            
            // Alle erlaubten Mapping-Keys
            $allowedKeys = [
                'email_template_2fa_enabled',
                'email_template_2fa_disabled',
                'email_template_ticket_created',
                'email_template_ticket_assigned',
                'email_template_ticket_comment',
                'email_template_ticket_status_changed',
                'email_template_ticket_closed',
                'email_template_todo_assigned',
                'email_template_calendar_invite',
                'email_template_calendar_update'
            ];
            
            // Prüfe Template-Existenz und speichere Zuordnungen
            $checkStmt = $pdo->prepare("SELECT id FROM email_templates WHERE id = ?");
            
            foreach ($allowedKeys as $key) {
                if (isset($data[$key])) {
                    $value = !empty($data[$key]) ? $data[$key] : null;
                    
                    // Wenn leer, Eintrag löschen
                    if (empty($value)) {
                        $deleteStmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
                        $deleteStmt->execute([$key]);
                    } else {
                        // Prüfe ob Template existiert
                        $checkStmt->execute([$value]);
                        if (!$checkStmt->fetch()) {
                            throw new Exception("Template mit ID $value nicht gefunden");
                        }
                        
                        $stmt->bindValue(':setting_key', $key);
                        $stmt->bindValue(':setting_value', $value);
                        $stmt->bindValue(':setting_value_update', $value);
                        $stmt->execute();
                    }
                }
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Template-Zuordnungen erfolgreich gespeichert'
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Email Template Mappings API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(400);
    error_log("Email Template Mappings API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
