<?php
/**
 * API: CalDAV-Kalender eines Servers auflisten
 * Benötigt: server_id, username, password (oder sync_id für gespeichertes Passwort)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/caldav-sync.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$serverId = (int) ($input['server_id'] ?? $input['caldav_server_id'] ?? 0);
$username = trim($input['username'] ?? $input['caldav_username'] ?? '');
$password = $input['password'] ?? $input['caldav_password'] ?? '';

if ($serverId <= 0 || empty($username)) {
    echo json_encode(['success' => false, 'error' => 'Server und Benutzername erforderlich']);
    exit;
}

if (empty($password)) {
    $syncId = (int) ($input['sync_id'] ?? 0);
    if ($syncId) {
        $stmt = $pdo->prepare("SELECT caldav_password FROM user_caldav_sync WHERE user_id = ? AND id = ? LIMIT 1");
        $stmt->execute([$userId, $syncId]);
    } else {
        $stmt = $pdo->prepare("SELECT caldav_password FROM user_caldav_sync WHERE user_id = ? AND caldav_server_id = ? LIMIT 1");
        $stmt->execute([$userId, $serverId]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $password = $row ? caldav_decrypt_password($row['caldav_password']) : '';
}

if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Passwort erforderlich (zum Laden der Kalender bitte Passwort eingeben oder Sync zuerst speichern)']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT url FROM caldav_servers WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$serverId]);
    $server = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$server) {
        echo json_encode(['success' => false, 'error' => 'CalDAV-Server nicht gefunden']);
        exit;
    }
    
    $result = listCalDAVCalendars($server['url'], $username, $password);
    
    if (!$result['success']) {
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Fehler beim Abrufen der Kalender']);
        exit;
    }
    
    echo json_encode(['success' => true, 'calendars' => $result['calendars']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Fehler: ' . $e->getMessage()]);
}
