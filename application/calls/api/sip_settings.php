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

// Benutzerdaten und Rolle abrufen
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    
    $userRole = $user['rolle'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // Zuerst systemweite SIP-Einstellungen aus system_settings laden
            $systemSettings = [];
            try {
                $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'calls_%'");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $row) {
                    $key = str_replace('calls_', '', $row['setting_key']);
                    if ($key === 'enabled') {
                        $systemSettings['enabled'] = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $systemSettings[$key] = $row['setting_value'];
                    }
                }
            } catch (PDOException $e) {
                // system_settings Tabelle existiert möglicherweise noch nicht
                error_log("Fehler beim Laden der systemweiten SIP-Einstellungen: " . $e->getMessage());
            }
            
            // Wenn systemweite Einstellungen aktiviert sind, diese verwenden
            if (!empty($systemSettings['enabled']) && !empty($systemSettings['server']) && !empty($systemSettings['username'])) {
                $settings = [
                    'server' => $systemSettings['server'],
                    'username' => $systemSettings['username'],
                    'display_name' => $systemSettings['display_name'] ?? '',
                    'password' => $systemSettings['password'] ?? '' // Passwort für Verbindung zurückgeben
                ];
                echo json_encode([
                    'success' => true,
                    'settings' => $settings,
                    'source' => 'system'
                ]);
            } else {
                // Fallback: Benutzerspezifische SIP-Einstellungen abrufen
                $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'sip_settings'");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && $result['setting_value']) {
                    $settings = json_decode($result['setting_value'], true);
                    // Passwort nicht zurückgeben
                    if (isset($settings['password'])) {
                        $settings['password'] = '***';
                    }
                    echo json_encode([
                        'success' => true,
                        'settings' => $settings,
                        'source' => 'user'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'settings' => null,
                        'source' => 'none'
                    ]);
                }
            }
            break;
            
        case 'POST':
        case 'PUT':
            // SIP-Einstellungen speichern
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
                exit;
            }
            
            // Prüfen ob bereits Einstellungen existieren
            $checkStmt = $pdo->prepare("SELECT id, setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'sip_settings'");
            $checkStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->execute();
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Wenn bereits Einstellungen existieren und Passwort nicht geändert wurde, altes Passwort behalten
            if ($existing && isset($data['password']) && $data['password'] === '***') {
                $oldSettings = json_decode($existing['setting_value'], true);
                if (isset($oldSettings['password'])) {
                    $data['password'] = $oldSettings['password'];
                }
            }
            
            $settingsJson = json_encode($data);
            
            if ($existing) {
                // Aktualisieren
                $stmt = $pdo->prepare("UPDATE user_settings SET setting_value = :setting_value, geaendert_datum = NOW() WHERE user_id = :user_id AND setting_key = 'sip_settings'");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':setting_value', $settingsJson, PDO::PARAM_STR);
                $stmt->execute();
            } else {
                // Einfügen
                $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (:user_id, 'sip_settings', :setting_value)");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':setting_value', $settingsJson, PDO::PARAM_STR);
                $stmt->execute();
            }
            
            // Einstellungen ohne Passwort zurückgeben
            $returnSettings = $data;
            if (isset($returnSettings['password'])) {
                $returnSettings['password'] = '***';
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $returnSettings,
                'message' => 'SIP-Einstellungen gespeichert'
            ]);
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
