<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';

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
    $userCompanyId = $user['company_id'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Nur Admin und Techniker können Links verwalten
if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // Links abrufen
            if (isset($_GET['id'])) {
                // Einzelner Link
                $linkId = (int)$_GET['id'];
                
                $sql = "
                    SELECT l.*, 
                           c.name as company_name,
                           c.logo as company_logo,
                           u.vorname as ersteller_vorname,
                           u.nachname as ersteller_nachname
                    FROM links l
                    LEFT JOIN companies c ON l.company_id = c.id
                    LEFT JOIN users u ON l.erstellt_von = u.id
                    WHERE l.id = :link_id
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
                $stmt->execute();
                $link = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$link) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Link nicht gefunden']);
                    exit;
                }
                
                // Firma entschlüsseln
                if (isset($link['company_name'])) {
                    $link['company_name'] = decrypt_from_db($link['company_name']);
                }
                
                echo json_encode([
                    'success' => true,
                    'link' => $link
                ]);
                exit;
            }
            
            // Alle Links abrufen
            $sql = "
                SELECT l.*, 
                       c.name as company_name,
                       c.logo as company_logo,
                       u.vorname as ersteller_vorname,
                       u.nachname as ersteller_nachname
                FROM links l
                LEFT JOIN companies c ON l.company_id = c.id
                LEFT JOIN users u ON l.erstellt_von = u.id
                ORDER BY l.erstellt_datum DESC
            ";
            
            $stmt = $pdo->query($sql);
            $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Firma entschlüsseln
            foreach ($links as &$link) {
                if (isset($link['company_name'])) {
                    $link['company_name'] = decrypt_from_db($link['company_name']);
                }
            }
            unset($link);
            
            echo json_encode([
                'success' => true,
                'links' => $links
            ]);
            break;
            
        case 'POST':
            // Neuen Link erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || empty(trim($data['name']))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                exit;
            }
            
            if (!isset($data['url']) || empty(trim($data['url']))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'URL ist erforderlich']);
                exit;
            }
            
            // URL validieren
            $url = trim($data['url']);
            if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^https?:\/\//', $url)) {
                // Wenn kein Protokoll vorhanden, füge https:// hinzu
                $url = 'https://' . $url;
            }
            
            $name = trim($data['name']);
            $companyId = isset($data['company_id']) && !empty($data['company_id']) ? (int)$data['company_id'] : null;
            $notiz = isset($data['notiz']) ? trim($data['notiz']) : null;
            
            $sql = "
                INSERT INTO links (name, url, company_id, notiz, erstellt_von)
                VALUES (:name, :url, :company_id, :notiz, :erstellt_von)
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':url', $url, PDO::PARAM_STR);
            $stmt->bindValue(':company_id', $companyId, $companyId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':notiz', $notiz, $notiz ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':erstellt_von', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $linkId = $pdo->lastInsertId();
            
            // Link mit zusätzlichen Informationen abrufen
            $sql = "
                SELECT l.*, 
                       c.name as company_name,
                       c.logo as company_logo,
                       u.vorname as ersteller_vorname,
                       u.nachname as ersteller_nachname
                FROM links l
                LEFT JOIN companies c ON l.company_id = c.id
                LEFT JOIN users u ON l.erstellt_von = u.id
                WHERE l.id = :link_id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
            $stmt->execute();
            $link = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Firma entschlüsseln
            if (isset($link['company_name'])) {
                $link['company_name'] = decrypt_from_db($link['company_name']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Link erfolgreich erstellt',
                'link' => $link
            ]);
            break;
            
        case 'PUT':
            // Link aktualisieren
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Link-ID ist erforderlich']);
                exit;
            }
            
            $linkId = (int)$data['id'];
            
            // Prüfen ob Link existiert
            $stmt = $pdo->prepare("SELECT id FROM links WHERE id = :link_id");
            $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
            $stmt->execute();
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Link nicht gefunden']);
                exit;
            }
            
            if (!isset($data['name']) || empty(trim($data['name']))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                exit;
            }
            
            if (!isset($data['url']) || empty(trim($data['url']))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'URL ist erforderlich']);
                exit;
            }
            
            // URL validieren
            $url = trim($data['url']);
            if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^https?:\/\//', $url)) {
                // Wenn kein Protokoll vorhanden, füge https:// hinzu
                $url = 'https://' . $url;
            }
            
            $name = trim($data['name']);
            $companyId = isset($data['company_id']) && !empty($data['company_id']) ? (int)$data['company_id'] : null;
            $notiz = isset($data['notiz']) ? trim($data['notiz']) : null;
            
            $sql = "
                UPDATE links 
                SET name = :name, 
                    url = :url, 
                    company_id = :company_id, 
                    notiz = :notiz
                WHERE id = :link_id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':url', $url, PDO::PARAM_STR);
            $stmt->bindValue(':company_id', $companyId, $companyId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':notiz', $notiz, $notiz ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Aktualisierten Link abrufen
            $sql = "
                SELECT l.*, 
                       c.name as company_name,
                       c.logo as company_logo,
                       u.vorname as ersteller_vorname,
                       u.nachname as ersteller_nachname
                FROM links l
                LEFT JOIN companies c ON l.company_id = c.id
                LEFT JOIN users u ON l.erstellt_von = u.id
                WHERE l.id = :link_id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
            $stmt->execute();
            $link = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Firma entschlüsseln
            if (isset($link['company_name'])) {
                $link['company_name'] = decrypt_from_db($link['company_name']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Link erfolgreich aktualisiert',
                'link' => $link
            ]);
            break;
            
        case 'DELETE':
            // Link löschen
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Link-ID ist erforderlich']);
                exit;
            }
            
            $linkId = (int)$_GET['id'];
            
            // Prüfen ob Link existiert
            $stmt = $pdo->prepare("SELECT id FROM links WHERE id = :link_id");
            $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
            $stmt->execute();
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Link nicht gefunden']);
                exit;
            }
            
            $sql = "DELETE FROM links WHERE id = :link_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Link erfolgreich gelöscht'
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
