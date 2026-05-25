<?php
/**
 * API für Kalender-Export-Quellen
 * ICS/Webcal und CalDAV haben getrennte Einstellungen.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

$defaultSources = [
    'my_calendar' => true,
    'vacation' => true,
    'invitations' => true,
    'service_tickets' => true,
    'todos' => true
];

function loadSources($pdo, $userId, $key, $default) {
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ? LIMIT 1");
    $stmt->execute([$userId, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $sources = $default;
    if ($row && $row['setting_value']) {
        $decoded = json_decode($row['setting_value'], true);
        if (is_array($decoded)) {
            $sources = array_merge($default, $decoded);
        }
    }
    return $sources;
}

function saveSources($pdo, $userId, $key, $merged) {
    $json = json_encode($merged);
    $stmt = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = ? AND setting_key = ? LIMIT 1");
    $stmt->execute([$userId, $key]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        $stmt = $pdo->prepare("UPDATE user_settings SET setting_value = ?, geaendert_datum = NOW() WHERE user_id = ? AND setting_key = ?");
        $stmt->execute([$json, $userId, $key]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $key, $json]);
    }
}

try {
    switch ($method) {
        case 'GET':
            $icsSources = loadSources($pdo, $userId, 'calendar_export_sources', $defaultSources);
            $caldavSources = loadSources($pdo, $userId, 'calendar_export_sources_caldav', $icsSources);
            echo json_encode(['success' => true, 'ics_sources' => $icsSources, 'caldav_sources' => $caldavSources]);
            break;

        case 'POST':
        case 'PATCH':
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $allowed = ['my_calendar', 'vacation', 'invitations', 'service_tickets', 'todos'];

            if (isset($input['ics_sources']) && is_array($input['ics_sources'])) {
                $merged = $defaultSources;
                foreach ($allowed as $key) {
                    if (array_key_exists($key, $input['ics_sources'])) {
                        $merged[$key] = (bool) $input['ics_sources'][$key];
                    }
                }
                saveSources($pdo, $userId, 'calendar_export_sources', $merged);
            }
            if (isset($input['caldav_sources']) && is_array($input['caldav_sources'])) {
                $merged = $defaultSources;
                foreach ($allowed as $key) {
                    if (array_key_exists($key, $input['caldav_sources'])) {
                        $merged[$key] = (bool) $input['caldav_sources'][$key];
                    }
                }
                saveSources($pdo, $userId, 'calendar_export_sources_caldav', $merged);
            }
            // Rückwärtskompatibilität: einfaches "sources" wie bisher
            if (isset($input['sources']) && is_array($input['sources']) && !isset($input['ics_sources']) && !isset($input['caldav_sources'])) {
                $merged = $defaultSources;
                foreach ($allowed as $key) {
                    if (array_key_exists($key, $input['sources'])) {
                        $merged[$key] = (bool) $input['sources'][$key];
                    }
                }
                saveSources($pdo, $userId, 'calendar_export_sources', $merged);
            }

            $icsSources = loadSources($pdo, $userId, 'calendar_export_sources', $defaultSources);
            $caldavSources = loadSources($pdo, $userId, 'calendar_export_sources_caldav', $icsSources);
            echo json_encode(['success' => true, 'ics_sources' => $icsSources, 'caldav_sources' => $caldavSources]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
