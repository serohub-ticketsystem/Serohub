<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';

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
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, vorname, nachname FROM users WHERE id = :user_id LIMIT 1");
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
            // Einzelnen Anruf abrufen
            if (isset($_GET['id'])) {
                $callId = (int)$_GET['id'];
                
                $sql = "
                    SELECT 
                        c.id,
                        c.telefonnummer,
                        c.empfaenger_name,
                        c.anruftyp,
                        c.status,
                        c.dauer_sekunden,
                        c.notizen,
                        c.company_id,
                        c.customer_id,
                        c.ticket_id,
                        c.erstellt_von,
                        c.erstellt_datum,
                        c.geaendert_datum,
                        comp.name as company_name,
                        cust.name as customer_name,
                        t.ticket_nummer,
                        u.vorname as ersteller_vorname,
                        u.nachname as ersteller_nachname
                    FROM calls c
                    LEFT JOIN companies comp ON c.company_id = comp.id
                    LEFT JOIN customers cust ON c.customer_id = cust.id
                    LEFT JOIN tickets t ON c.ticket_id = t.id
                    LEFT JOIN users u ON c.erstellt_von = u.id
                    WHERE c.id = :call_id
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':call_id', $callId, PDO::PARAM_INT);
                $stmt->execute();
                $call = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$call) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Anruf nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $call['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($call['erstellt_von'] == $userId) {
                    $hasPermission = true;
                }
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                echo json_encode([
                    'success' => true,
                    'call' => $call
                ]);
                exit;
            }
            
            // Anrufe abrufen mit rollenbasierter Filterung
            $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $customerFilter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
            $ticketFilter = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null;
            $anruftypFilter = isset($_GET['anruftyp']) ? $_GET['anruftyp'] : null;
            
            // SQL-Query basierend auf Rolle aufbauen
            $whereConditions = [];
            $params = [];
            
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // Admin und Techniker sehen alle Anrufe
                if ($companyFilter) {
                    $whereConditions[] = "c.company_id = :company_filter";
                    $params[':company_filter'] = $companyFilter;
                }
            } elseif ($userRole === 'Firmen-Admin') {
                // Firmen-Admin sieht nur Anrufe der eigenen Firma
                if ($userCompanyId) {
                    $whereConditions[] = "c.company_id = :user_company_id";
                    $params[':user_company_id'] = $userCompanyId;
                } else {
                    // Keine Firma zugeordnet = keine Anrufe
                    $whereConditions[] = "1 = 0";
                }
            } else {
                // Firmen-User sieht nur eigene Anrufe
                $whereConditions[] = "c.erstellt_von = :user_id";
                $params[':user_id'] = $userId;
            }
            
            if ($customerFilter) {
                $whereConditions[] = "c.customer_id = :customer_filter";
                $params[':customer_filter'] = $customerFilter;
            }
            
            if ($ticketFilter) {
                $whereConditions[] = "c.ticket_id = :ticket_filter";
                $params[':ticket_filter'] = $ticketFilter;
            }
            
            if ($anruftypFilter && in_array($anruftypFilter, ['ausgehend', 'eingehend', 'verpasst'])) {
                $whereConditions[] = "c.anruftyp = :anruftyp_filter";
                $params[':anruftyp_filter'] = $anruftypFilter;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $sql = "
                SELECT 
                    c.id,
                    c.telefonnummer,
                    c.empfaenger_name,
                    c.anruftyp,
                    c.status,
                    c.dauer_sekunden,
                    c.notizen,
                    c.company_id,
                    c.customer_id,
                    c.ticket_id,
                    c.erstellt_von,
                    c.erstellt_datum,
                    c.geaendert_datum,
                    comp.name as company_name,
                    cust.name as customer_name,
                    t.ticket_nummer,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname
                FROM calls c
                LEFT JOIN companies comp ON c.company_id = comp.id
                LEFT JOIN customers cust ON c.customer_id = cust.id
                LEFT JOIN tickets t ON c.ticket_id = t.id
                LEFT JOIN users u ON c.erstellt_von = u.id
                $whereClause
                ORDER BY c.erstellt_datum DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'calls' => $calls
            ]);
            break;
            
        case 'POST':
            // Neuen Anruf erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
                exit;
            }
            
            $telefonnummer = isset($data['telefonnummer']) ? trim($data['telefonnummer']) : '';
            $empfaenger_name = isset($data['empfaenger_name']) ? trim($data['empfaenger_name']) : null;
            $anruftyp = isset($data['anruftyp']) && in_array($data['anruftyp'], ['ausgehend', 'eingehend', 'verpasst']) ? $data['anruftyp'] : 'ausgehend';
            $status = isset($data['status']) && in_array($data['status'], ['verbunden', 'nicht_erreicht', 'besetzt', 'abgelehnt', 'keine_antwort']) ? $data['status'] : null;
            $dauer_sekunden = isset($data['dauer_sekunden']) ? (int)$data['dauer_sekunden'] : null;
            $notizen = isset($data['notizen']) ? trim($data['notizen']) : null;
            $company_id = isset($data['company_id']) && $data['company_id'] ? (int)$data['company_id'] : null;
            $customer_id = isset($data['customer_id']) && $data['customer_id'] ? (int)$data['customer_id'] : null;
            $ticket_id = isset($data['ticket_id']) && $data['ticket_id'] ? (int)$data['ticket_id'] : null;
            
            if (empty($telefonnummer)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Telefonnummer ist erforderlich']);
                exit;
            }
            
            // Prüfen ob calls-Tabelle existiert
            try {
                $checkStmt = $pdo->query("SHOW TABLES LIKE 'calls'");
                if ($checkStmt->rowCount() === 0) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Calls-Tabelle existiert nicht. Bitte Migration ausführen.']);
                    exit;
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
                exit;
            }
            
            $sql = "
                INSERT INTO calls (
                    telefonnummer,
                    empfaenger_name,
                    anruftyp,
                    status,
                    dauer_sekunden,
                    notizen,
                    company_id,
                    customer_id,
                    ticket_id,
                    erstellt_von
                ) VALUES (
                    :telefonnummer,
                    :empfaenger_name,
                    :anruftyp,
                    :status,
                    :dauer_sekunden,
                    :notizen,
                    :company_id,
                    :customer_id,
                    :ticket_id,
                    :erstellt_von
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':telefonnummer', $telefonnummer, PDO::PARAM_STR);
            $stmt->bindValue(':empfaenger_name', $empfaenger_name, PDO::PARAM_STR);
            $stmt->bindValue(':anruftyp', $anruftyp, PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':dauer_sekunden', $dauer_sekunden, PDO::PARAM_INT);
            $stmt->bindValue(':notizen', $notizen, PDO::PARAM_STR);
            $stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindValue(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $stmt->bindValue(':erstellt_von', $userId, PDO::PARAM_INT);
            
            $stmt->execute();
            $callId = $pdo->lastInsertId();
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
            if (empty($userName)) {
                $userName = 'Unbekannt';
            }
            
            createNotificationsForAction(
                $userId,
                $company_id,
                'call_created',
                'Neuer Anruf erfasst: ' . $telefonnummer,
                'Ein neuer Anruf (' . $anruftyp . ') mit ' . ($empfaenger_name ?: $telefonnummer) . ' wurde von ' . $userName . ' erfasst.',
                'normal',
                'calls/index.php',
                'call',
                $callId
            );
            
            // Anruf mit allen Details zurückgeben
            $sql = "
                SELECT 
                    c.id,
                    c.telefonnummer,
                    c.empfaenger_name,
                    c.anruftyp,
                    c.status,
                    c.dauer_sekunden,
                    c.notizen,
                    c.company_id,
                    c.customer_id,
                    c.ticket_id,
                    c.erstellt_von,
                    c.erstellt_datum,
                    c.geaendert_datum,
                    comp.name as company_name,
                    cust.name as customer_name,
                    t.ticket_nummer,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname
                FROM calls c
                LEFT JOIN companies comp ON c.company_id = comp.id
                LEFT JOIN customers cust ON c.customer_id = cust.id
                LEFT JOIN tickets t ON c.ticket_id = t.id
                LEFT JOIN users u ON c.erstellt_von = u.id
                WHERE c.id = :call_id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':call_id', $callId, PDO::PARAM_INT);
            $stmt->execute();
            $call = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'call' => $call
            ]);
            break;
            
        case 'PUT':
            // Anruf aktualisieren
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Anruf-ID erforderlich']);
                exit;
            }
            
            $callId = (int)$_GET['id'];
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
                exit;
            }
            
            // Prüfen ob Anruf existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT id, erstellt_von, company_id FROM calls WHERE id = :call_id");
            $checkStmt->bindValue(':call_id', $callId, PDO::PARAM_INT);
            $checkStmt->execute();
            $existingCall = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingCall) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Anruf nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $existingCall['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($existingCall['erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $updateFields = [];
            $updateParams = [];
            
            if (isset($data['telefonnummer'])) {
                $updateFields[] = "telefonnummer = :telefonnummer";
                $updateParams[':telefonnummer'] = trim($data['telefonnummer']);
            }
            if (isset($data['empfaenger_name'])) {
                $updateFields[] = "empfaenger_name = :empfaenger_name";
                $updateParams[':empfaenger_name'] = trim($data['empfaenger_name']) ?: null;
            }
            if (isset($data['anruftyp']) && in_array($data['anruftyp'], ['ausgehend', 'eingehend', 'verpasst'])) {
                $updateFields[] = "anruftyp = :anruftyp";
                $updateParams[':anruftyp'] = $data['anruftyp'];
            }
            if (isset($data['status']) && in_array($data['status'], ['verbunden', 'nicht_erreicht', 'besetzt', 'abgelehnt', 'keine_antwort'])) {
                $updateFields[] = "status = :status";
                $updateParams[':status'] = $data['status'];
            }
            if (isset($data['dauer_sekunden'])) {
                $updateFields[] = "dauer_sekunden = :dauer_sekunden";
                $updateParams[':dauer_sekunden'] = (int)$data['dauer_sekunden'] ?: null;
            }
            if (isset($data['notizen'])) {
                $updateFields[] = "notizen = :notizen";
                $updateParams[':notizen'] = trim($data['notizen']) ?: null;
            }
            if (isset($data['company_id'])) {
                $updateFields[] = "company_id = :company_id";
                $updateParams[':company_id'] = $data['company_id'] ? (int)$data['company_id'] : null;
            }
            if (isset($data['customer_id'])) {
                $updateFields[] = "customer_id = :customer_id";
                $updateParams[':customer_id'] = $data['customer_id'] ? (int)$data['customer_id'] : null;
            }
            if (isset($data['ticket_id'])) {
                $updateFields[] = "ticket_id = :ticket_id";
                $updateParams[':ticket_id'] = $data['ticket_id'] ? (int)$data['ticket_id'] : null;
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Felder zum Aktualisieren']);
                exit;
            }
            
            $updateParams[':call_id'] = $callId;
            
            $sql = "UPDATE calls SET " . implode(', ', $updateFields) . " WHERE id = :call_id";
            $stmt = $pdo->prepare($sql);
            foreach ($updateParams as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            
            // Anruf-Informationen für Benachrichtigung abrufen
            $callInfoStmt = $pdo->prepare("SELECT telefonnummer, empfaenger_name, anruftyp, company_id FROM calls WHERE id = ?");
            $callInfoStmt->execute([$callId]);
            $callInfo = $callInfoStmt->fetch(PDO::FETCH_ASSOC);
            
            $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
            if (empty($userName)) {
                $userName = 'Unbekannt';
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            if ($callInfo) {
                createNotificationsForAction(
                    $userId,
                    $callInfo['company_id'],
                    'call_updated',
                    'Anruf aktualisiert: ' . $callInfo['telefonnummer'],
                    'Der Anruf mit ' . ($callInfo['empfaenger_name'] ?: $callInfo['telefonnummer']) . ' wurde von ' . $userName . ' aktualisiert.',
                    'normal',
                    'calls/index.php',
                    'call',
                    $callId
                );
            }
            
            // Aktualisierten Anruf zurückgeben
            $sql = "
                SELECT 
                    c.id,
                    c.telefonnummer,
                    c.empfaenger_name,
                    c.anruftyp,
                    c.status,
                    c.dauer_sekunden,
                    c.notizen,
                    c.company_id,
                    c.customer_id,
                    c.ticket_id,
                    c.erstellt_von,
                    c.erstellt_datum,
                    c.geaendert_datum,
                    comp.name as company_name,
                    cust.name as customer_name,
                    t.ticket_nummer,
                    u.vorname as ersteller_vorname,
                    u.nachname as ersteller_nachname
                FROM calls c
                LEFT JOIN companies comp ON c.company_id = comp.id
                LEFT JOIN customers cust ON c.customer_id = cust.id
                LEFT JOIN tickets t ON c.ticket_id = t.id
                LEFT JOIN users u ON c.erstellt_von = u.id
                WHERE c.id = :call_id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':call_id', $callId, PDO::PARAM_INT);
            $stmt->execute();
            $call = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'call' => $call
            ]);
            break;
            
        case 'DELETE':
            // Anruf löschen
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Anruf-ID erforderlich']);
                exit;
            }
            
            $callId = (int)$_GET['id'];
            
            // Prüfen ob Anruf existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT id, erstellt_von, company_id FROM calls WHERE id = :call_id");
            $checkStmt->bindValue(':call_id', $callId, PDO::PARAM_INT);
            $checkStmt->execute();
            $existingCall = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingCall) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Anruf nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen (nur Admin und Techniker können löschen)
            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung zum Löschen']);
                exit;
            }
            
            // Anruf-Informationen für Benachrichtigung abrufen (vor dem Löschen)
            $callInfoStmt = $pdo->prepare("SELECT telefonnummer, empfaenger_name, company_id FROM calls WHERE id = ?");
            $callInfoStmt->execute([$callId]);
            $callInfo = $callInfoStmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM calls WHERE id = :call_id");
            $stmt->bindValue(':call_id', $callId, PDO::PARAM_INT);
            $stmt->execute();
            
            $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
            if (empty($userName)) {
                $userName = 'Unbekannt';
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            if ($callInfo) {
                createNotificationsForAction(
                    $userId,
                    $callInfo['company_id'],
                    'call_deleted',
                    'Anruf gelöscht: ' . $callInfo['telefonnummer'],
                    'Der Anruf mit ' . ($callInfo['empfaenger_name'] ?: $callInfo['telefonnummer']) . ' wurde von ' . $userName . ' gelöscht.',
                    'hoch',
                    'calls/index.php',
                    'call',
                    $callId
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Anruf gelöscht'
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
