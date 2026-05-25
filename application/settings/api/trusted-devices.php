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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?? [];
        
        $action = $data['action'] ?? '';
        
        if ($action === 'remove') {
            $deviceId = isset($data['device_id']) ? (int)$data['device_id'] : 0;
            
            if ($deviceId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige Geräte-ID']);
                exit;
            }
            
            // Prüfen ob Gerät dem Benutzer gehört
            $checkStmt = $pdo->prepare("SELECT id FROM trusted_devices WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$deviceId, $userId]);
            $device = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Gerät nicht gefunden']);
                exit;
            }
            
            // Gerät entfernen
            $deleteStmt = $pdo->prepare("DELETE FROM trusted_devices WHERE id = ? AND user_id = ?");
            $deleteStmt->execute([$deviceId, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Gerät erfolgreich entfernt'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ungültige Aktion']);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Liste aller vertrauten Geräte abrufen
        $stmt = $pdo->prepare("
            SELECT id, device_name, user_agent, ip_address, last_used, created_at
            FROM trusted_devices
            WHERE user_id = ?
            ORDER BY last_used DESC
        ");
        $stmt->execute([$userId]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'devices' => $devices
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Trusted Devices API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Trusted Devices API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Serverfehler']);
}
