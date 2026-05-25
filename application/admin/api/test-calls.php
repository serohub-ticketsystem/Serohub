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
    if ($method === 'GET') {
        // Aktuelle Einstellungen zurückgeben (inkl. Passwort für Test)
        $settings = [
            'enabled' => false,
            'server' => '',
            'username' => '',
            'password' => '',
            'display_name' => ''
        ];
        
        try {
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'calls_%'");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $key = str_replace('calls_', '', $row['setting_key']);
                if ($key === 'enabled') {
                    $settings['enabled'] = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
                } else {
                    $settings[$key] = $row['setting_value'];
                }
            }
        } catch (PDOException $e) {
            error_log("Fehler beim Laden der Anruf-Einstellungen: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Test Calls API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>
