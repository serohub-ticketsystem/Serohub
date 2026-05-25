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

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'project_tasks_require_folder'");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // Standard: aktiviert, falls noch keine Benutzereinstellung gespeichert ist.
            $enabled = !$row || $row['setting_value'] === '1';
            echo json_encode(['success' => true, 'enabled' => $enabled]);
            break;

        case 'POST':
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $enabled = isset($data['enabled']) ? (bool) $data['enabled'] : false;
            $value = $enabled ? '1' : '0';

            if (!$enabled) {
                try {
                    $folderStmt = $pdo->prepare("
                        SELECT id FROM todo_folders
                        WHERE COALESCE(is_project_system_folder, 0) = 1 AND company_id IS NULL
                        LIMIT 1
                    ");
                    $folderStmt->execute();
                    $folderRow = $folderStmt->fetch(PDO::FETCH_ASSOC);
                    if ($folderRow) {
                        $tid = (int) $folderRow['id'];
                        $pdo->prepare("UPDATE todos SET folder_id = NULL WHERE folder_id = ?")->execute([$tid]);
                    }
                } catch (PDOException $e) {
                    // Spalte is_project_system_folder fehlt evtl.
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'project_tasks_require_folder', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $stmt->execute([$userId, $value]);
            echo json_encode(['success' => true, 'enabled' => $enabled]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Project tasks require folder API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
