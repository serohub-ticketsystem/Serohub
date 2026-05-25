<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$allowedPages = [
    'dashboard' => 'Dashboard',
    'tickets' => 'Tickets',
    'todos' => 'Aufgaben',
    'inventory' => 'Lager',
    'service' => 'Service',
    'knowledge' => 'Wissensdatenbank',
    'kalender' => 'Kalender',
    'devices' => 'Geraete',
    'orders' => 'Bestellungen',
    'companies' => 'Firmen',
    'customers' => 'Kunden',
    'projects' => 'Projekte',
    'notes' => 'Notizen',
];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ? AND setting_key IN ('mobile_start_page', 'mobile_start_enabled')");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $settingsMap = [];
            foreach ($rows as $row) {
                $settingsMap[(string)($row['setting_key'] ?? '')] = $row['setting_value'] ?? null;
            }

            $mode = 'fixed';
            $page = 'dashboard';
            if (!empty($settingsMap['mobile_start_page']) && is_string($settingsMap['mobile_start_page'])) {
                $decoded = json_decode($settingsMap['mobile_start_page'], true);
                if (is_array($decoded)) {
                    $candidateMode = (string) ($decoded['mode'] ?? '');
                    $candidatePage = (string) ($decoded['page'] ?? '');
                    if (in_array($candidateMode, ['fixed', 'last'], true)) {
                        $mode = $candidateMode;
                    }
                    if (isset($allowedPages[$candidatePage])) {
                        $page = $candidatePage;
                    }
                }
            }
            $enabled = true;
            if (array_key_exists('mobile_start_enabled', $settingsMap)) {
                $enabled = ((string)$settingsMap['mobile_start_enabled']) === '1';
            } elseif ($mode === 'last') {
                // Rueckwaertskompatibel: ohne Toggle-Setting bedeutet "last" = deaktiviert.
                $enabled = false;
            }

            echo json_encode([
                'success' => true,
                'mode' => $mode,
                'page' => $page,
                'enabled' => $enabled,
                'pages' => $allowedPages,
            ]);
            break;

        case 'POST':
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $mode = (string) ($data['mode'] ?? 'fixed');
            $page = (string) ($data['page'] ?? 'dashboard');
            $enabled = array_key_exists('enabled', (array)$data) ? (bool)$data['enabled'] : true;
            $mode = $enabled ? 'fixed' : 'last';

            if (!in_array($mode, ['fixed', 'last'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungueltiger Modus']);
                exit;
            }
            if (!isset($allowedPages[$page])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungueltige Startseite']);
                exit;
            }

            $value = json_encode(['mode' => $mode, 'page' => $page], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'mobile_start_page', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $stmt->execute([$userId, $value]);
            $enabledStmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'mobile_start_enabled', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $enabledStmt->execute([$userId, $enabled ? '1' : '0']);

            echo json_encode([
                'success' => true,
                'mode' => $mode,
                'page' => $page,
                'enabled' => $enabled,
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Mobile Start Page Settings API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
