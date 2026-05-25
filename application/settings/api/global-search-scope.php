<?php
/**
 * API für Suchbereich der globalen Navigation-Suche (User-Einstellung).
 * Bestimmt, in welchen Entitätstypen bei der globalen Suche gesucht wird.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

$userRole = '';
try {
    $roleStmt = $pdo->prepare('SELECT rolle FROM users WHERE id = ? LIMIT 1');
    $roleStmt->execute([$userId]);
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $roleRow ? (string) ($roleRow['rolle'] ?? '') : '';
} catch (PDOException $e) {
    $userRole = '';
}

// Alle erlaubten Entitätstypen (Standard = alle aktiv); Benutzer nur für Admin
$allScopeKeys = [
    'ticket' => 'Tickets',
    'aufgabe' => 'Aufgaben',
    'geraet' => 'Geräte',
    'bestellung' => 'Bestellungen',
    'firma' => 'Firmen',
    'kunde' => 'Kunden',
    'artikel' => 'Wissensdatenbank',
    'projekt' => 'Projekte',
    'inventar' => 'Lager',
];
if ($userRole === 'Admin') {
    $allScopeKeys['benutzer'] = 'Benutzer';
}

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'global_search_scope'");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $scope = [];
            if ($row && $row['setting_value'] !== null && $row['setting_value'] !== '') {
                $decoded = json_decode($row['setting_value'], true);
                if (is_array($decoded)) {
                    // Sentinel "_none" = Benutzer hat "Keine" gewählt
                    if (in_array('_none', $decoded, true)) {
                        $scope = ['_none'];
                    } else {
                        $scope = array_values(array_intersect($decoded, array_keys($allScopeKeys)));
                        if (empty($scope)) {
                            $scope = array_keys($allScopeKeys);
                        }
                    }
                }
            }
            if (empty($scope)) {
                $scope = array_keys($allScopeKeys);
            }
            echo json_encode([
                'success' => true,
                'scope' => $scope,
                'all_keys' => $allScopeKeys,
            ]);
            break;

        case 'POST':
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $sent = isset($data['scope']) && is_array($data['scope']) ? $data['scope'] : null;
            // Explizit leeres Array = Benutzer hat "Keine" gewählt
            if ($sent !== null && count($sent) === 0) {
                $scope = ['_none'];
            } else {
                $scope = $sent ? array_values(array_intersect($sent, array_keys($allScopeKeys))) : array_keys($allScopeKeys);
                if (count($scope) === count($allScopeKeys)) {
                    $scope = [];
                }
            }
            $jsonValue = json_encode($scope);
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'global_search_scope', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $stmt->execute([$userId, $jsonValue]);
            $outScope = empty($scope) ? array_keys($allScopeKeys) : $scope;
            echo json_encode(['success' => true, 'scope' => $outScope]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Global Search Scope API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
