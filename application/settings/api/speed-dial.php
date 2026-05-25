<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Benutzerrolle abrufen
$userRole = '';
try {
    $roleStmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $roleStmt->execute([$userId]);
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
    if ($roleRow) {
        $userRole = $roleRow['rolle'] ?? '';
    }
} catch (PDOException $e) {
    error_log("Speed Dial API: Fehler beim Abrufen der Benutzerrolle: " . $e->getMessage());
}

// Prüfen, ob Benutzer Firmen-User oder Kunde ist
$isFirmenUserOrKunde = in_array($userRole, ['Firmen-User', 'Kunde'], true);

// Standard: Bestellung, Aufgabe, Firma, Projekt, Link deaktiviert – Rest aktiviert
$defaults = [
    'service' => true,
    'kunde' => true,
    'geraet' => true,
    'firma' => false,
    'inventar' => true,
    'projekt' => false,
    'aufgabe' => false,
    'bestellung' => false,
    'link' => false,
];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ? AND setting_key IN ('speed_dial_items', 'speed_dial_visible')");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $settingsMap = [];
            foreach ($rows as $row) {
                $settingsMap[(string)($row['setting_key'] ?? '')] = $row['setting_value'] ?? null;
            }
            $items = $defaults;
            if (!empty($settingsMap['speed_dial_items'])) {
                $saved = json_decode((string)$settingsMap['speed_dial_items'], true);
                if (is_array($saved)) {
                    foreach ($defaults as $key => $val) {
                        if (array_key_exists($key, $saved)) {
                            $items[$key] = (bool) $saved[$key];
                        }
                    }
                }
            }
            $visible = false;
            if (array_key_exists('speed_dial_visible', $settingsMap)) {
                $visible = ((string)$settingsMap['speed_dial_visible']) === '1';
            }
            // Verknüpfung für Firmen-User und Kunden immer deaktiviert halten
            if ($isFirmenUserOrKunde) {
                $items['link'] = false;
            }
            echo json_encode(['success' => true, 'items' => $items, 'visible' => $visible]);
            break;

        case 'POST':
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $items = $defaults;
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($defaults as $key => $defVal) {
                    if (array_key_exists($key, $data['items'])) {
                        $items[$key] = (bool) $data['items'][$key];
                    }
                }
            }
            $visible = false;
            if (array_key_exists('visible', $data)) {
                $visible = (bool) $data['visible'];
            }
            // Verknüpfung für Firmen-User und Kunden immer deaktiviert halten
            if ($isFirmenUserOrKunde) {
                $items['link'] = false;
            }
            $json = json_encode($items);
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'speed_dial_items', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $stmt->execute([$userId, $json]);
            $visibleStmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'speed_dial_visible', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $visibleStmt->execute([$userId, $visible ? '1' : '0']);
            echo json_encode(['success' => true, 'items' => $items, 'visible' => $visible]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Speed Dial Settings API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
