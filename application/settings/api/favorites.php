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

try {
    switch ($method) {
        case 'GET':
            // Favoriten abrufen
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'company_favorites'");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $favoriteIds = [];
            if ($result && $result['setting_value']) {
                $favoriteIds = json_decode($result['setting_value'], true) ?? [];
            }
            
            // Firmen-Details abrufen
            $favorites = [];
            if (!empty($favoriteIds)) {
                $placeholders = implode(',', array_fill(0, count($favoriteIds), '?'));
                $stmt = $pdo->prepare("SELECT id, name, logo, status, telefonnummer FROM companies WHERE id IN ($placeholders) ORDER BY name ASC");
                $stmt->execute($favoriteIds);
                $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($favorites as &$favRow) {
                    decrypt_company_row($favRow);
                }
                unset($favRow);
            }
            
            echo json_encode([
                'success' => true,
                'favorites' => $favorites,
                'favoriteIds' => $favoriteIds
            ]);
            break;
            
        case 'POST':
            // Favorit hinzufügen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['company_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id fehlt']);
                exit;
            }
            
            $companyId = (int)$data['company_id'];
            
            // Prüfen ob Firma existiert
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE id = ?");
            $stmt->execute([$companyId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
                exit;
            }
            
            // Aktuelle Favoriten abrufen
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'company_favorites'");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $favoriteIds = [];
            if ($result && $result['setting_value']) {
                $favoriteIds = json_decode($result['setting_value'], true) ?? [];
            }
            
            // Prüfen ob bereits Favorit
            if (in_array($companyId, $favoriteIds)) {
                echo json_encode(['success' => true, 'message' => 'Bereits Favorit']);
                exit;
            }
            
            // Favorit hinzufügen
            $favoriteIds[] = $companyId;
            $favoriteIdsJson = json_encode($favoriteIds);
            
            // Speichern oder aktualisieren
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (:user_id, 'company_favorites', :setting_value, NOW())
                ON DUPLICATE KEY UPDATE 
                    setting_value = :setting_value_update,
                    geaendert_datum = NOW()
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':setting_value', $favoriteIdsJson);
            $stmt->bindValue(':setting_value_update', $favoriteIdsJson);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Favorit hinzugefügt']);
            break;
            
        case 'DELETE':
            // Favorit entfernen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['company_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id fehlt']);
                exit;
            }
            
            $companyId = (int)$data['company_id'];
            
            // Aktuelle Favoriten abrufen
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'company_favorites'");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || !$result['setting_value']) {
                echo json_encode(['success' => true, 'message' => 'Keine Favoriten vorhanden']);
                exit;
            }
            
            $favoriteIds = json_decode($result['setting_value'], true) ?? [];
            
            // Favorit entfernen
            $favoriteIds = array_values(array_filter($favoriteIds, function($id) use ($companyId) {
                return $id != $companyId;
            }));
            
            $favoriteIdsJson = json_encode($favoriteIds);
            
            // Aktualisieren oder löschen wenn leer
            if (empty($favoriteIds)) {
                $stmt = $pdo->prepare("DELETE FROM user_settings WHERE user_id = :user_id AND setting_key = 'company_favorites'");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("
                    UPDATE user_settings 
                    SET setting_value = :setting_value, geaendert_datum = NOW()
                    WHERE user_id = :user_id AND setting_key = 'company_favorites'
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':setting_value', $favoriteIdsJson);
                $stmt->execute();
            }
            
            echo json_encode(['success' => true, 'message' => 'Favorit entfernt']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Favorites API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
