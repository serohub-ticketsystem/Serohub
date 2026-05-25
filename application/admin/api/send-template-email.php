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

    if (empty($data['template_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vorlagen-ID ist erforderlich']);
        exit;
    }

    if (empty($data['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-Mail-Adresse ist erforderlich']);
        exit;
    }

    // Vorlage aus Datenbank laden
    $stmt = $pdo->prepare("SELECT name, subject, body FROM email_templates WHERE id = ? LIMIT 1");
    $stmt->execute([$data['template_id']]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Vorlage nicht gefunden']);
        exit;
    }

    // Variablen ersetzen
    $variables = $data['variables'] ?? [];
    
    $subject = $template['subject'];
    $body = $template['body'];
    
    foreach ($variables as $key => $value) {
        $subject = str_replace('{{' . $key . '}}', $value, $subject);
        $body = str_replace('{{' . $key . '}}', $value, $body);
    }
    
    // E-Mail senden
    $success = sendEmail(
        $data['email'],
        $subject,
        $body,
        true, // HTML
        null,
        null,
        null,
        'Administration · Vorlage senden'
    );

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'E-Mail erfolgreich gesendet'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fehler beim Senden der E-Mail. Bitte überprüfe die SMTP-Einstellungen.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Send Template Email API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
