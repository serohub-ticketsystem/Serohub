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
            // Alle verwendeten Benachrichtigungstypen aus der Datenbank abrufen
            $typesStmt = $pdo->query("
                SELECT DISTINCT typ 
                FROM notifications 
                WHERE typ IS NOT NULL AND typ != ''
                ORDER BY typ
            ");
            $availableTypes = $typesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Wenn keine Typen vorhanden, Standard-Typen verwenden
            if (empty($availableTypes)) {
                $availableTypes = [
                    'ticket_erstellt',
                    'ticket_nachricht',
                    'ticket_status',
                    'todo_erstellt',
                    'todo_zugewiesen',
                    'todo_kommentar',
                    'device_offline',
                    'device_online',
                    'system'
                ];
            }
            
            // Benachrichtigungseinstellungen aus user_settings abrufen
            $stmt = $pdo->prepare("
                SELECT setting_value 
                FROM user_settings 
                WHERE user_id = :user_id AND setting_key = 'notification_settings'
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $settings = [];
            if ($result && $result['setting_value']) {
                $settings = json_decode($result['setting_value'], true) ?? [];
            }
            
            // Einstellung „eigene Benachrichtigungen ausblenden“ abrufen
            $hideOwnStmt = $pdo->prepare("
                SELECT setting_value FROM user_settings
                WHERE user_id = :user_id AND setting_key = 'notification_hide_own'
            ");
            $hideOwnStmt->execute([':user_id' => $userId]);
            $hideOwnRow = $hideOwnStmt->fetch(PDO::FETCH_ASSOC);
            // Standard: eigene Benachrichtigungen sind ausgeblendet, solange kein expliziter Wert gespeichert wurde.
            $hideOwnNotifications = !$hideOwnRow || in_array($hideOwnRow['setting_value'], ['1', 'true'], true);

            $systemToggleStmt = $pdo->prepare("
                SELECT setting_value FROM user_settings
                WHERE user_id = :user_id AND setting_key = 'system_notifications_enabled'
            ");
            $systemToggleStmt->execute([':user_id' => $userId]);
            $systemToggleRow = $systemToggleStmt->fetch(PDO::FETCH_ASSOC);
            $systemNotificationsEnabled = true;
            if ($systemToggleRow && $systemToggleRow['setting_value'] !== null) {
                $systemNotificationsEnabled = in_array($systemToggleRow['setting_value'], ['1', 'true'], true);
            }

            $pushToggleStmt = $pdo->prepare("
                SELECT setting_value FROM user_settings
                WHERE user_id = :user_id AND setting_key = 'push_notifications_enabled'
            ");
            $pushToggleStmt->execute([':user_id' => $userId]);
            $pushToggleRow = $pushToggleStmt->fetch(PDO::FETCH_ASSOC);
            $pushNotificationsEnabled = true;
            if ($pushToggleRow && $pushToggleRow['setting_value'] !== null) {
                $pushNotificationsEnabled = in_array($pushToggleRow['setting_value'], ['1', 'true'], true);
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $settings,
                'hide_own_notifications' => $hideOwnNotifications,
                'system_notifications_enabled' => $systemNotificationsEnabled,
                'push_notifications_enabled' => $pushNotificationsEnabled,
                'available_types' => $availableTypes
            ]);
            break;
            
        case 'POST':
        case 'PUT':
            // Benachrichtigungseinstellungen speichern in user_settings
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Optionale Einstellung: Eigene Benachrichtigungen ausblenden
            if (array_key_exists('hide_own_notifications', $data)) {
                $hideOwn = (int)(bool)$data['hide_own_notifications'];
                $stmtHide = $pdo->prepare("
                    INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum, geaendert_datum)
                    VALUES (:user_id, 'notification_hide_own', :val, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE setting_value = :val2, geaendert_datum = NOW()
                ");
                $stmtHide->execute([':user_id' => $userId, ':val' => (string)$hideOwn, ':val2' => (string)$hideOwn]);
            }

            if (array_key_exists('system_notifications_enabled', $data)) {
                $systemEnabled = (int)(bool)$data['system_notifications_enabled'];
                $stmtSystem = $pdo->prepare("
                    INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum, geaendert_datum)
                    VALUES (:user_id, 'system_notifications_enabled', :val, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE setting_value = :val2, geaendert_datum = NOW()
                ");
                $stmtSystem->execute([':user_id' => $userId, ':val' => (string)$systemEnabled, ':val2' => (string)$systemEnabled]);
            }

            if (array_key_exists('push_notifications_enabled', $data)) {
                $pushEnabled = (int)(bool)$data['push_notifications_enabled'];
                $stmtPush = $pdo->prepare("
                    INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum, geaendert_datum)
                    VALUES (:user_id, 'push_notifications_enabled', :val, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE setting_value = :val2, geaendert_datum = NOW()
                ");
                $stmtPush->execute([':user_id' => $userId, ':val' => (string)$pushEnabled, ':val2' => (string)$pushEnabled]);
            }
            
            if (!isset($data['benachrichtigungs_typ']) || !isset($data['system']) || !isset($data['email'])) {
                // Nur hide_own_notifications gesetzt → Erfolg zurückgeben
                if (
                    array_key_exists('hide_own_notifications', $data) ||
                    array_key_exists('system_notifications_enabled', $data) ||
                    array_key_exists('push_notifications_enabled', $data)
                ) {
                    echo json_encode(['success' => true]);
                    break;
                }
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'benachrichtigungs_typ, system und email sind erforderlich']);
                exit;
            }
            
            $typ = $data['benachrichtigungs_typ'];
            $systemEnabled = (int)(bool)$data['system'];
            $emailEnabled = (int)(bool)$data['email'];
            
            // Bestehende Einstellungen abrufen
            $stmt = $pdo->prepare("
                SELECT setting_value 
                FROM user_settings 
                WHERE user_id = :user_id AND setting_key = 'notification_settings'
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $allSettings = [];
            if ($result && $result['setting_value']) {
                $allSettings = json_decode($result['setting_value'], true) ?? [];
            }
            
            // Typ-spezifische Einstellungen aktualisieren
            $allSettings[$typ] = [
                'system' => (bool)$systemEnabled,
                'email' => (bool)$emailEnabled
            ];
            
            // Speichern in user_settings
            $jsonValue = json_encode($allSettings);
            
            $stmt = $pdo->prepare("
                INSERT INTO user_settings 
                (user_id, setting_key, setting_value, erstellt_datum, geaendert_datum)
                VALUES 
                (:user_id, 'notification_settings', :setting_value, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                setting_value = :setting_value_update,
                geaendert_datum = NOW()
            ");
            
            $success = $stmt->execute([
                ':user_id' => $userId,
                ':setting_value' => $jsonValue,
                ':setting_value_update' => $jsonValue
            ]);
            
            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern']);
            }
            break;
            
        case 'DELETE':
            // Benachrichtigungseinstellung löschen (zurück auf Standard)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['benachrichtigungs_typ'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'benachrichtigungs_typ fehlt']);
                exit;
            }
            
            $typ = $data['benachrichtigungs_typ'];
            
            // Bestehende Einstellungen abrufen
            $stmt = $pdo->prepare("
                SELECT setting_value 
                FROM user_settings 
                WHERE user_id = :user_id AND setting_key = 'notification_settings'
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['setting_value']) {
                $allSettings = json_decode($result['setting_value'], true) ?? [];
                
                // Typ aus Einstellungen entfernen
                if (isset($allSettings[$typ])) {
                    unset($allSettings[$typ]);
                    
                    // Aktualisierte Einstellungen speichern
                    $jsonValue = json_encode($allSettings);
                    
                    $stmt = $pdo->prepare("
                        UPDATE user_settings 
                        SET setting_value = :setting_value,
                            geaendert_datum = NOW()
                        WHERE user_id = :user_id AND setting_key = 'notification_settings'
                    ");
                    
                    $stmt->execute([
                        ':user_id' => $userId,
                        ':setting_value' => $jsonValue
                    ]);
                }
            }
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    error_log("Notification Settings API Fehler: " . $e->getMessage());
}
