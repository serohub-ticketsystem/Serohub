<?php
/**
 * API für Suchbereich der Ticket-Suche (User-Einstellung).
 * Bestimmt, in welchen Feldern bei der Ticket-Suche gesucht wird.
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

// Alle erlaubten Suchbereiche (Standard = alle aktiv)
$allScopeKeys = [
    'ticket_nummer' => 'Ticketnummer',
    'titel' => 'Betreff',
    'beschreibung' => 'Beschreibung',
    'firma' => 'Firma',
    'kunde' => 'Kunde',
    'anforderer' => 'Anforderer',
    'bearbeiter' => 'Bearbeiter',
    'beobachter' => 'Beobachter',
    'geraet' => 'Gerät',
    'geraetestandort' => 'Gerätestandort',
    'nachrichten' => 'Nachrichten (Kommentare)',
    'anhange' => 'Anhänge (Dateinamen)',
    'status' => 'Status',
];

try {
    switch ($method) {
        case 'GET':
            // Suche ist fest auf "alle Felder" gesetzt.
            $scope = array_keys($allScopeKeys);
            echo json_encode([
                'success' => true,
                'scope' => $scope,
                'all_keys' => $allScopeKeys,
            ]);
            break;

        case 'POST':
        case 'PUT':
            // Eingehende Scope-Auswahl wird ignoriert: immer alle Felder.
            $jsonValue = json_encode([]);
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'ticket_search_scope', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $stmt->execute([$userId, $jsonValue]);
            echo json_encode(['success' => true, 'scope' => array_keys($allScopeKeys)]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Ticket Search Scope API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
