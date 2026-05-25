<?php
/**
 * API: CalDAV-Sync-Einstellungen des Benutzers (mehrere Syncs möglich)
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
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("SELECT id, name FROM caldav_servers WHERE is_active = 1 ORDER BY name");
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT s.id, s.caldav_server_id, s.caldav_username, s.calendar_name, s.export_sources, s.name AS sync_name, s.is_active,
                       s.last_sync, s.last_sync_status, s.last_sync_message,
                       sr.name AS server_name
                FROM user_caldav_sync s
                JOIN caldav_servers sr ON s.caldav_server_id = sr.id
                WHERE s.user_id = ? AND sr.is_active = 1
                ORDER BY s.id ASC
            ");
            $stmt->execute([$userId]);
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pro Server: Benutzername für Wiederverwendung bereitstellen (ohne Passwort)
            $serverCredentials = [];
            foreach ($configs as $c) {
                $sid = (int) $c['caldav_server_id'];
                if (!isset($serverCredentials[$sid])) {
                    $serverCredentials[$sid] = ['caldav_username' => $c['caldav_username'] ?? ''];
                }
            }

            echo json_encode(['success' => true, 'configs' => $configs, 'servers' => $servers, 'serverCredentials' => $serverCredentials]);
            break;

        case 'POST':
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $syncId = isset($input['id']) ? (int) $input['id'] : 0;
            $serverId = (int) ($input['caldav_server_id'] ?? 0);
            $username = trim($input['caldav_username'] ?? '');
            $password = $input['caldav_password'] ?? '';
            $calendarName = trim($input['calendar_name'] ?? 'Personal') ?: 'Personal';
            $syncName = trim($input['sync_name'] ?? $input['name'] ?? '') ?: null;
            $isActive = isset($input['is_active']) ? (bool) $input['is_active'] : true;

            if (!empty($input['action']) && $input['action'] === 'sync') {
                $targetId = !empty($input['sync_id']) ? (int) $input['sync_id'] : null;
                $stmt = $pdo->prepare("
                    SELECT s.id, s.caldav_username, s.caldav_password, s.calendar_name, s.export_sources, sr.url AS server_url
                    FROM user_caldav_sync s
                    JOIN caldav_servers sr ON s.caldav_server_id = sr.id
                    WHERE s.user_id = ? AND s.is_active = 1 AND sr.is_active = 1
                    " . ($targetId ? " AND s.id = ?" : "") . "
                    ORDER BY s.id
                ");
                $stmt->execute($targetId ? [$userId, $targetId] : [$userId]);
                $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($configs)) {
                    echo json_encode(['success' => false, 'message' => 'Keine aktive CalDAV-Sync-Konfiguration gefunden. Bitte zuerst hinzufügen.']);
                    exit;
                }

                $defaultExportSources = ['my_calendar' => true, 'vacation' => true, 'invitations' => true, 'service_tickets' => true, 'todos' => true];
                $us = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'calendar_export_sources_caldav' LIMIT 1");
                $us->execute([$userId]);
                $row = $us->fetch(PDO::FETCH_ASSOC);
                $userExportSources = $defaultExportSources;
                if ($row && $row['setting_value']) {
                    $dec = json_decode($row['setting_value'], true);
                    if (is_array($dec)) $userExportSources = array_merge($defaultExportSources, $dec);
                } else {
                    $us2 = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'calendar_export_sources' LIMIT 1");
                    $us2->execute([$userId]);
                    $row2 = $us2->fetch(PDO::FETCH_ASSOC);
                    if ($row2 && $row2['setting_value']) {
                        $dec = json_decode($row2['setting_value'], true);
                        if (is_array($dec)) $userExportSources = array_merge($defaultExportSources, $dec);
                    }
                }
                $roleStmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
                $roleStmt->execute([$userId]);
                $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
                $isAdminOrTechniker = $roleRow && in_array($roleRow['rolle'] ?? '', ['Admin', 'Techniker'], true);

                $messages = [];
                $totalSynced = 0;
                $totalEvents = 0;
                foreach ($configs as $cfg) {
                    $exportSources = $defaultExportSources;
                    if (!empty($cfg['export_sources'])) {
                        $dec = json_decode($cfg['export_sources'], true);
                        if (is_array($dec)) $exportSources = array_merge($defaultExportSources, $dec);
                    } else {
                        $exportSources = $userExportSources;
                    }
                    $events = getEventsForUser($pdo, $userId, $exportSources, $isAdminOrTechniker);
                    $totalEvents += count($events);
                    $pass = caldav_decrypt_password($cfg['caldav_password']);
                    if (empty($pass)) {
                        $messages[] = 'Sync #' . $cfg['id'] . ': Kein Passwort gespeichert.';
                        $pdo->prepare("UPDATE user_caldav_sync SET last_sync = NOW(), last_sync_status = 'error', last_sync_message = ? WHERE id = ?")
                            ->execute(['Kein Passwort', $cfg['id']]);
                        continue;
                    }
                    $baseUrl = normalizeCalDAVUrl($cfg['server_url']);
                    $calendarUrl = $baseUrl . 'calendars/' . rawurlencode($cfg['caldav_username']) . '/' . rawurlencode($cfg['calendar_name']) . '/';
                    $result = pushEventsToCalDAV($calendarUrl, $cfg['caldav_username'], $pass, $events);
                    $status = empty($result['errors']) ? 'ok' : 'partial';
                    if ($result['success'] === 0 && !empty($result['errors'])) $status = 'error';
                    $msg = $result['success'] . '/' . $result['total'] . ' Events';
                    if ($result['total'] === 0) {
                        $msg .= ' (keine Termine gefunden – prüfen Sie Ticket-Termine, Zuweisung und Export-Quellen)';
                    }
                    if (!empty($result['errors'])) {
                        $msg .= ' – Fehler: ' . implode('; ', array_slice($result['errors'], 0, 2));
                    }
                    $messages[] = $msg;
                    $totalSynced += $result['success'];
                    $pdo->prepare("UPDATE user_caldav_sync SET last_sync = NOW(), last_sync_status = ?, last_sync_message = ? WHERE id = ?")
                        ->execute([$status, $msg, $cfg['id']]);
                }

                echo json_encode([
                    'success' => true,
                    'message' => implode(' | ', $messages),
                    'synced' => $totalSynced,
                    'total' => $totalEvents
                ]);
                exit;
            }

            if (!empty($input['action']) && $input['action'] === 'test') {
                if ($serverId <= 0 || empty($username)) {
                    echo json_encode(['success' => false, 'error' => 'Server und Benutzername erforderlich für den Test.']);
                    exit;
                }
                if (empty($password)) {
                    $sid = (int) ($input['sync_id'] ?? 0);
                    if ($sid) {
                        $stmt = $pdo->prepare("SELECT caldav_password FROM user_caldav_sync WHERE user_id = ? AND id = ? LIMIT 1");
                        $stmt->execute([$userId, $sid]);
                    } else {
                        $stmt = $pdo->prepare("SELECT caldav_password FROM user_caldav_sync WHERE user_id = ? AND caldav_server_id = ? LIMIT 1");
                        $stmt->execute([$userId, $serverId]);
                    }
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $password = $row ? caldav_decrypt_password($row['caldav_password']) : '';
                }
                if (empty($password)) {
                    echo json_encode(['success' => false, 'error' => 'Passwort erforderlich für den Test (oder zuvor speichern).']);
                    exit;
                }
                $stmt = $pdo->prepare("SELECT url FROM caldav_servers WHERE id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$serverId]);
                $sr = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$sr) {
                    echo json_encode(['success' => false, 'error' => 'CalDAV-Server nicht gefunden.']);
                    exit;
                }
                $baseUrl = normalizeCalDAVUrl($sr['url']);
                $calendarUrl = $baseUrl . 'calendars/' . rawurlencode($username) . '/' . rawurlencode($calendarName) . '/';
                $result = testCalDAVConnection($calendarUrl, $username, $password);
                echo json_encode(['success' => $result['success'], 'message' => $result['message']]);
                exit;
            }

            if ($serverId <= 0 || empty($username)) {
                echo json_encode(['success' => false, 'error' => 'CalDAV-Server und Benutzername erforderlich']);
                exit;
            }

            $exportSourcesJson = null;
            if (isset($input['export_sources']) && is_array($input['export_sources'])) {
                $allowed = ['my_calendar', 'vacation', 'invitations', 'service_tickets', 'todos'];
                $sources = [];
                foreach ($allowed as $key) {
                    $sources[$key] = !empty($input['export_sources'][$key]);
                }
                $exportSourcesJson = json_encode($sources);
            }

            $encPassword = null;
            if ($password !== '') {
                $encPassword = caldav_encrypt_password($password);
            } elseif ($syncId === 0) {
                // Neuer Sync: Passwort von bestehendem Sync für gleichen Server übernehmen
                $stmt = $pdo->prepare("SELECT caldav_password FROM user_caldav_sync WHERE user_id = ? AND caldav_server_id = ? AND caldav_username = ? LIMIT 1");
                $stmt->execute([$userId, $serverId, $username]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['caldav_password'])) {
                    $encPassword = $row['caldav_password'];
                }
            }

            if ($syncId > 0) {
                $stmt = $pdo->prepare("SELECT id FROM user_caldav_sync WHERE user_id = ? AND id = ? LIMIT 1");
                $stmt->execute([$userId, $syncId]);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$exists) {
                    echo json_encode(['success' => false, 'error' => 'Sync-Konfiguration nicht gefunden']);
                    exit;
                }
                $sql = "UPDATE user_caldav_sync SET caldav_server_id = ?, caldav_username = ?, calendar_name = ?, name = ?, is_active = ?";
                $params = [$serverId, $username, $calendarName, $syncName, $isActive ? 1 : 0];
                if ($exportSourcesJson !== null) {
                    $sql .= ", export_sources = ?";
                    $params[] = $exportSourcesJson;
                }
                if ($encPassword !== null) {
                    $sql .= ", caldav_password = ?";
                    $params[] = $encPassword;
                }
                $sql .= " WHERE id = ?";
                $params[] = $syncId;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                if ($encPassword === null) {
                    echo json_encode(['success' => false, 'error' => 'Passwort erforderlich beim Hinzufügen (oder bestehenden Sync für diesen Server nutzen)']);
                    exit;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO user_caldav_sync (user_id, caldav_server_id, caldav_username, caldav_password, calendar_name, export_sources, name, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $serverId, $username, $encPassword, $calendarName, $exportSourcesJson, $syncName, $isActive ? 1 : 0]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_GET;
            $syncId = (int) ($input['id'] ?? $input['sync_id'] ?? 0);
            if ($syncId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID erforderlich']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM user_caldav_sync WHERE user_id = ? AND id = ?");
            $stmt->execute([$userId, $syncId]);
            echo json_encode(['success' => true]);
            break;

        case 'PATCH':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $syncId = (int) ($input['id'] ?? $input['sync_id'] ?? 0);
            $isActive = isset($input['is_active']) ? (bool) $input['is_active'] : null;
            if ($syncId <= 0 || $isActive === null) {
                echo json_encode(['success' => false, 'error' => 'id und is_active erforderlich']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE user_caldav_sync SET is_active = ? WHERE user_id = ? AND id = ?");
            $stmt->execute([$isActive ? 1 : 0, $userId, $syncId]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
