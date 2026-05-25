<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/email.php';

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

    if (empty($data['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-Mail-Adresse ist erforderlich']);
        exit;
    }

    if (empty($data['subject'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Betreff ist erforderlich']);
        exit;
    }

    if (empty($data['message'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nachricht ist erforderlich']);
        exit;
    }

    // E-Mail senden
    try {
        $success = sendEmail(
            $data['email'],
            $data['subject'],
            $data['message'],
            true, // HTML
            null,
            null,
            null,
            'Administration · Test-E-Mail'
        );

        if ($success === true) {
            echo json_encode([
                'success' => true,
                'message' => 'Test-E-Mail erfolgreich gesendet'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Fehler beim Senden der E-Mail. Die Funktion hat false zurückgegeben. Bitte überprüfe die SMTP-Einstellungen und die Server-Logs.'
            ]);
        }
    } catch (Exception $emailException) {
        http_response_code(500);
        $errorMessage = $emailException->getMessage();
        error_log("Test Email Send Error: " . $errorMessage);
        error_log("Stack trace: " . $emailException->getTraceAsString());
        
        // Detaillierte Fehlermeldung zurückgeben
        echo json_encode([
            'success' => false,
            'message' => 'Fehler beim Senden: ' . $errorMessage,
            'error_type' => get_class($emailException)
        ]);
    } catch (Error $emailError) {
        // Auch PHP Errors abfangen
        http_response_code(500);
        $errorMessage = $emailError->getMessage();
        error_log("Test Email Send Error (PHP Error): " . $errorMessage);
        error_log("Stack trace: " . $emailError->getTraceAsString());
        
        echo json_encode([
            'success' => false,
            'message' => 'PHP Fehler beim Senden: ' . $errorMessage,
            'error_type' => get_class($emailError)
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Test Email API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
