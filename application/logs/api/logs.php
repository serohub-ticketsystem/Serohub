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
    $userCompanyId = $user['company_id'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // Logs abrufen
            // Wenn kategorie und entity_id vorhanden sind, spezifische Logs abrufen
            // Ansonsten alle Logs mit optionalen Filtern
            $kategorie = isset($_GET['kategorie']) && $_GET['kategorie'] !== '' ? $_GET['kategorie'] : null;
            $entityId = isset($_GET['entity_id']) && $_GET['entity_id'] !== '' ? (int)$_GET['entity_id'] : null;
            $action = isset($_GET['action']) && $_GET['action'] !== '' ? $_GET['action'] : null;
            $userId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
            $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
            $dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            // Nur Admin kann alle Logs sehen
            if (!$kategorie || !$entityId) {
                if ($userRole !== 'Admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Nur Administratoren können alle Logs einsehen']);
                    exit;
                }
            }
            
            $sql = "
                SELECT 
                    l.id,
                    l.kategorie,
                    l.entity_id,
                    l.user_id,
                    l.action,
                    l.field_name,
                    l.old_value,
                    l.new_value,
                    l.beschreibung,
                    l.erstellt_datum,
                    u.vorname as user_vorname,
                    u.nachname as user_nachname,
                    u.email as user_email,
                    u.rolle as user_rolle
                FROM logs l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($kategorie) {
                $sql .= " AND l.kategorie = :kategorie";
                $params[':kategorie'] = $kategorie;
            }
            
            if ($entityId) {
                $sql .= " AND l.entity_id = :entity_id";
                $params[':entity_id'] = $entityId;
            }
            
            if ($action) {
                $sql .= " AND l.action = :action";
                $params[':action'] = $action;
            }
            
            if ($userId) {
                $sql .= " AND l.user_id = :user_id";
                $params[':user_id'] = $userId;
            }
            
            if ($dateFrom) {
                $sql .= " AND DATE(l.erstellt_datum) >= :date_from";
                $params[':date_from'] = $dateFrom;
            }
            
            if ($dateTo) {
                $sql .= " AND DATE(l.erstellt_datum) <= :date_to";
                $params[':date_to'] = $dateTo;
            }
            
            $sql .= " ORDER BY l.erstellt_datum DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Gesamtanzahl für Pagination
            $countSql = "SELECT COUNT(*) as total FROM logs l WHERE 1=1";
            $countParams = [];
            
            if ($kategorie) {
                $countSql .= " AND l.kategorie = :kategorie";
                $countParams[':kategorie'] = $kategorie;
            }
            
            if ($entityId) {
                $countSql .= " AND l.entity_id = :entity_id";
                $countParams[':entity_id'] = $entityId;
            }
            
            if ($action) {
                $countSql .= " AND l.action = :action";
                $countParams[':action'] = $action;
            }
            
            if ($userId) {
                $countSql .= " AND l.user_id = :user_id";
                $countParams[':user_id'] = $userId;
            }
            
            if ($dateFrom) {
                $countSql .= " AND DATE(l.erstellt_datum) >= :date_from";
                $countParams[':date_from'] = $dateFrom;
            }
            
            if ($dateTo) {
                $countSql .= " AND DATE(l.erstellt_datum) <= :date_to";
                $countParams[':date_to'] = $dateTo;
            }
            
            $countStmt = $pdo->prepare($countSql);
            foreach ($countParams as $key => $value) {
                $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;
            
        case 'POST':
            // Neuen Log-Eintrag erstellen (wird normalerweise vom System erstellt)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['kategorie']) || !isset($data['entity_id']) || !isset($data['action'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Kategorie, entity_id und action sind erforderlich']);
                exit;
            }
            
            $sql = "
                INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, beschreibung, erstellt_datum)
                VALUES (:kategorie, :entity_id, :user_id, :action, :field_name, :old_value, :new_value, :beschreibung, NOW())
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':kategorie', $data['kategorie'], PDO::PARAM_STR);
            $stmt->bindValue(':entity_id', $data['entity_id'], PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':action', $data['action'], PDO::PARAM_STR);
            $stmt->bindValue(':field_name', $data['field_name'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':old_value', $data['old_value'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':new_value', $data['new_value'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':beschreibung', $data['beschreibung'] ?? null, PDO::PARAM_STR);
            $stmt->execute();
            
            $logId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'log_id' => $logId
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
