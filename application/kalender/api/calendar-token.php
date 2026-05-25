<?php
/**
 * API zum Verwalten des Kalender-Export-Tokens
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Token abrufen
            $stmt = $pdo->prepare("SELECT calendar_token FROM users WHERE id = :uid");
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'token' => $row['calendar_token'] ?? null
            ]);
            break;
            
        case 'POST':
            // Neuen Token generieren
            $token = bin2hex(random_bytes(32));
            
            $stmt = $pdo->prepare("UPDATE users SET calendar_token = :token WHERE id = :uid");
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'token' => $token
            ]);
            break;
            
        case 'DELETE':
            // Token löschen (Link deaktivieren)
            $stmt = $pdo->prepare("UPDATE users SET calendar_token = NULL WHERE id = :uid");
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
