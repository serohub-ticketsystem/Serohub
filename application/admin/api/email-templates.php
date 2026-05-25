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
    // Prüfe ob email_templates Tabelle existiert, erstelle sie falls nicht
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                body TEXT NOT NULL,
                variables TEXT,
                erstellt_datum DATETIME DEFAULT CURRENT_TIMESTAMP,
                geaendert_datum DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        // Tabelle existiert bereits
        error_log("Email Templates Tabelle: " . $e->getMessage());
    }

    switch ($method) {
        case 'GET':
            // Vorlage abrufen
            if (isset($_GET['id'])) {
                $templateId = (int)$_GET['id'];
                $stmt = $pdo->prepare("SELECT id, name, subject, body, variables, erstellt_datum, geaendert_datum FROM email_templates WHERE id = ? LIMIT 1");
                $stmt->execute([$templateId]);
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($template) {
                    if ($template['variables']) {
                        $template['variables'] = json_decode($template['variables'], true) ?: [];
                    } else {
                        $template['variables'] = [];
                    }
                    echo json_encode(['success' => true, 'template' => $template]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Vorlage nicht gefunden']);
                }
            } else {
                // Alle Vorlagen abrufen
                $stmt = $pdo->prepare("SELECT id, name, subject, erstellt_datum, geaendert_datum FROM email_templates ORDER BY name ASC");
                $stmt->execute();
                $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'templates' => $templates]);
            }
            break;
            
        case 'POST':
            // Vorlage speichern
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
                exit;
            }
            
            if (empty($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Vorlagen-Name ist erforderlich']);
                exit;
            }
            
            if (empty($data['subject'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Betreff ist erforderlich']);
                exit;
            }
            
            if (empty($data['body'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'E-Mail-Inhalt ist erforderlich']);
                exit;
            }
            
            // Variablen aus Body extrahieren
            preg_match_all('/\{\{(\w+)\}\}/', $data['subject'] . ' ' . $data['body'], $matches);
            $variables = array_unique($matches[1]);
            $variablesJson = json_encode($variables);
            
            if ($data['id']) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE email_templates 
                    SET name = ?, subject = ?, body = ?, variables = ?, geaendert_datum = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$data['name'], $data['subject'], $data['body'], $variablesJson, $data['id']]);
                $templateId = $data['id'];
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO email_templates (name, subject, body, variables)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$data['name'], $data['subject'], $data['body'], $variablesJson]);
                $templateId = $pdo->lastInsertId();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Vorlage erfolgreich gespeichert',
                'template_id' => $templateId
            ]);
            break;
            
        case 'DELETE':
            // Vorlage löschen
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID ist erforderlich']);
                exit;
            }
            
            $templateId = (int)$_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Vorlage erfolgreich gelöscht']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Vorlage nicht gefunden']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Email Templates API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Email Templates API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten']);
}
