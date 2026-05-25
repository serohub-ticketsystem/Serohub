<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__, 2) . '/customers/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';

header('Content-Type: application/json');

function json_response(array $payload, ?int $statusCode = null): void {
    if ($statusCode !== null) {
        http_response_code($statusCode);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        error_log('Devices API JSON encode failed: ' . json_last_error_msg());
        http_response_code(500);
        echo '{"success":false,"error":"Antwort konnte nicht serialisiert werden"}';
        return;
    }

    echo $json;
}

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
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, customer_id, email, vorname, nachname FROM users WHERE id = :user_id LIMIT 1");
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
    $userCustomerId = $user['customer_id'];
    $userEmail = $user['email'];
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
    if (empty($userName)) {
        $userName = 'Unbekannt';
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // Hersteller-Vorschläge abrufen
            if (isset($_GET['action']) && $_GET['action'] === 'get_manufacturers') {
                $stmt = $pdo->query("SELECT DISTINCT hersteller FROM devices WHERE hersteller IS NOT NULL AND hersteller != '' ORDER BY hersteller");
                $manufacturers = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo json_encode([
                    'success' => true,
                    'manufacturers' => $manufacturers
                ]);
                exit;
            }
            
            // Modell-Vorschläge abrufen
            if (isset($_GET['action']) && $_GET['action'] === 'get_models') {
                $manufacturer = isset($_GET['manufacturer']) ? trim($_GET['manufacturer']) : null;
                if ($manufacturer) {
                    $stmt = $pdo->prepare("SELECT DISTINCT modell FROM devices WHERE modell IS NOT NULL AND modell != '' AND hersteller = ? ORDER BY modell");
                    $stmt->execute([$manufacturer]);
                } else {
                    $stmt = $pdo->query("SELECT DISTINCT modell FROM devices WHERE modell IS NOT NULL AND modell != '' ORDER BY modell");
                }
                $models = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo json_encode([
                    'success' => true,
                    'models' => $models
                ]);
                exit;
            }
            
            // Benutzer-Vorschläge abrufen
            if (isset($_GET['action']) && $_GET['action'] === 'get_users') {
                $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
                
                if (!$companyId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'company_id fehlt']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $companyId == $userCompanyId) {
                    $hasPermission = true;
                }
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                $stmt = $pdo->prepare("SELECT id, vorname, nachname, email FROM users WHERE company_id = ? AND status = 'aktiv' ORDER BY nachname, vorname");
                $stmt->execute([$companyId]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'users' => $users
                ]);
                exit;
            }
            
            // Geräte abrufen
            if (isset($_GET['id'])) {
                // Einzelnes Gerät
                $deviceId = (int)$_GET['id'];
                
                $sql = "
                    SELECT 
                        d.id,
                        d.name,
                        d.typ,
                        d.hersteller,
                        d.modell,
                        d.seriennummer,
                        d.mac_adresse,
                        d.ip_adresse,
                        d.betriebssystem,
                        d.beschreibung,
                        d.status,
                        d.details,
                        d.company_id,
                        d.customer_id,
                        d.user_id,
                        d.erstellt_von,
                        d.erstellt_datum,
                        d.geaendert_datum,
                        c.name as company_name,
                        cust.name as customer_name,
                        cust.email as customer_email,
                        u.vorname as user_vorname,
                        u.nachname as user_nachname,
                        u.email as user_email,
                        u_erstellt.vorname as ersteller_vorname,
                        u_erstellt.nachname as ersteller_nachname
                    FROM devices d
                    LEFT JOIN companies c ON d.company_id = c.id
                    LEFT JOIN customers cust ON d.customer_id = cust.id
                    LEFT JOIN users u ON d.user_id = u.id
                    LEFT JOIN users u_erstellt ON d.erstellt_von = u_erstellt.id
                    WHERE d.id = :device_id
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':device_id', $deviceId, PDO::PARAM_INT);
                $stmt->execute();
                $device = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$device) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Gerät nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $device['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-User' && $device['user_id'] == $userId) {
                    $hasPermission = true;
                } elseif ($userRole === 'Kunde' && $device['customer_id'] && $userCustomerId) {
                    // Kunde darf nur Geräte sehen, die seinem customer_id zugeordnet sind
                    if ($device['customer_id'] == $userCustomerId) {
                        $hasPermission = true;
                    }
                }
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                // Entschlüsselung für company_name und customer_name
                if (isset($device['company_name'])) {
                    $device['company_name'] = decrypt_from_db($device['company_name']);
                }
                if (isset($device['customer_name'])) {
                    $device['customer_name'] = decrypt_from_db($device['customer_name']);
                }
                if (isset($device['customer_email'])) {
                    $device['customer_email'] = decrypt_from_db($device['customer_email']);
                }
                
                echo json_encode([
                    'success' => true,
                    'device' => $device
                ]);
                exit;
            }
            
            // Alle Geräte abrufen mit rollenbasierter Filterung
            $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $customerFilter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
            $statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
            $onlyActive = isset($_GET['only_active']) && ($_GET['only_active'] === '1' || $_GET['only_active'] === 'true');
            
            $whereConditions = [];
            $params = [];
            
            // Rollenbasierte Filterung
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // Admin und Techniker sehen alle Geräte
                if ($companyFilter) {
                    $whereConditions[] = "d.company_id = :company_filter";
                    $params[':company_filter'] = $companyFilter;
                }
                if ($customerFilter) {
                    $whereConditions[] = "d.customer_id = :customer_filter";
                    $params[':customer_filter'] = $customerFilter;
                }
            } elseif ($userRole === 'Firmen-Admin') {
                // Firmen-Admin sieht Geräte der eigenen Firma und deren Kunden
                if ($userCompanyId) {
                    if ($companyFilter) {
                        // Wenn explizit nach Firma gefiltert wird (z.B. Service-Auswahl), nur echte Firmen-Kunden berücksichtigen
                        $whereConditions[] = "(d.company_id = :user_company_id OR d.customer_id IN (SELECT id FROM customers WHERE company_id = :user_company_id2))";
                    } else {
                        // Standard-Ansicht (bestehendes Verhalten): auch firmenlose Kunden berücksichtigen
                        $whereConditions[] = "(d.company_id = :user_company_id OR d.customer_id IN (SELECT id FROM customers WHERE company_id = :user_company_id2 OR company_id IS NULL))";
                    }
                    $params[':user_company_id'] = $userCompanyId;
                    $params[':user_company_id2'] = $userCompanyId;
                } else {
                    $whereConditions[] = "1 = 0"; // Keine Firma = keine Geräte
                }
                if ($customerFilter) {
                    // Prüfen ob Kunde zur Firma gehört oder keine Firma hat
                    $whereConditions[] = "d.customer_id = :customer_filter";
                    $params[':customer_filter'] = $customerFilter;
                }
            } elseif ($userRole === 'Firmen-User') {
                // Firmen-User sieht alle Geräte der eigenen Firma (und deren Kunden)
                if ($userCompanyId) {
                    if ($companyFilter && (int)$companyFilter !== (int)$userCompanyId) {
                        $whereConditions[] = "1 = 0";
                    } else {
                        $whereConditions[] = "(d.company_id = :user_company_id OR d.customer_id IN (SELECT id FROM customers WHERE company_id = :user_company_id2 OR company_id IS NULL))";
                        $params[':user_company_id'] = $userCompanyId;
                        $params[':user_company_id2'] = $userCompanyId;
                        if ($customerFilter) {
                            $whereConditions[] = "d.customer_id = :customer_filter";
                            $params[':customer_filter'] = $customerFilter;
                        }
                    }
                } else {
                    // Keine Firma = keine Geräte
                    $whereConditions[] = "1 = 0";
                }
            } elseif ($userRole === 'Kunde') {
                // Kunde sieht nur Geräte, die seinem customer_id zugeordnet sind
                if ($userCustomerId) {
                    $whereConditions[] = "d.customer_id = :user_customer_id";
                    $params[':user_customer_id'] = $userCustomerId;
                } else {
                    // Kein customer_id = keine Geräte
                    $whereConditions[] = "1 = 0";
                }
            } else {
                // Andere Rollen sehen keine Geräte
                $whereConditions[] = "1 = 0";
            }
            
            // Servicebereich: nur aktive Geräte für alle Rollen außer Admin/Techniker.
            // Kunden sehen alle Geräte ihres Kunden (unabhängig vom Status).
            if ($onlyActive) {
                if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Kunde') {
                    $whereConditions[] = "d.status = 'aktiv'";
                }
            }

            if ($statusFilter) {
                $whereConditions[] = "d.status = :status_filter";
                $params[':status_filter'] = $statusFilter;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // Sortierung: Für Firmen-User, Firmen-Admin und Kunde Geräte mit eigenem user_id zuerst
            $orderByClause = "ORDER BY d.erstellt_datum DESC";
            if (in_array($userRole, ['Firmen-User', 'Firmen-Admin', 'Kunde'])) {
                $orderByClause = "ORDER BY CASE WHEN d.user_id = :sort_user_id THEN 0 ELSE 1 END, d.erstellt_datum DESC";
                $params[':sort_user_id'] = $userId;
            }
            
            $sql = "
                SELECT 
                    d.id,
                    d.name,
                    d.typ,
                    d.hersteller,
                    d.modell,
                    d.seriennummer,
                    d.mac_adresse,
                    d.ip_adresse,
                    d.betriebssystem,
                    d.beschreibung,
                    d.status,
                    d.details,
                    d.company_id,
                    d.customer_id,
                    d.user_id,
                    d.erstellt_datum,
                    d.geaendert_datum,
                    c.name as company_name,
                    cust.name as customer_name,
                    cust.email as customer_email,
                    u.vorname as user_vorname,
                    u.nachname as user_nachname,
                    u.email as user_email
                FROM devices d
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN customers cust ON d.customer_id = cust.id
                LEFT JOIN users u ON d.user_id = u.id
                $whereClause
                $orderByClause
            ";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // JSON-Details dekodieren und Entschlüsselung für company_name und customer_name
            foreach ($devices as &$device) {
                if (isset($device['details']) && $device['details']) {
                    $decoded = json_decode($device['details'], true);
                    $device['details'] = $decoded ? $decoded : [];
                } else {
                    $device['details'] = [];
                }
                
                // Entschlüsselung für company_name und customer_name
                if (isset($device['company_name'])) {
                    $device['company_name'] = decrypt_from_db($device['company_name']);
                }
                if (isset($device['customer_name'])) {
                    $device['customer_name'] = decrypt_from_db($device['customer_name']);
                }
                if (isset($device['customer_email'])) {
                    $device['customer_email'] = decrypt_from_db($device['customer_email']);
                }
            }
            unset($device);
            
            json_response([
                'success' => true,
                'devices' => $devices
            ]);
            break;
            
        case 'POST':
            // Neues Gerät erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                exit;
            }
            
            $name = trim($data['name']);
            $typ = isset($data['typ']) ? trim($data['typ']) : null;
            $hersteller = isset($data['hersteller']) ? trim($data['hersteller']) : null;
            $modell = isset($data['modell']) ? trim($data['modell']) : null;
            $seriennummer = isset($data['seriennummer']) ? trim($data['seriennummer']) : null;
            $macAdresse = isset($data['mac_adresse']) ? trim($data['mac_adresse']) : null;
            $ipAdresse = isset($data['ip_adresse']) ? trim($data['ip_adresse']) : null;
            $betriebssystem = isset($data['betriebssystem']) ? trim($data['betriebssystem']) : null;
            $beschreibung = isset($data['beschreibung']) ? trim($data['beschreibung']) : null;
            $status = isset($data['status']) ? $data['status'] : 'aktiv';
            $companyId = isset($data['company_id']) ? (int)$data['company_id'] : null;
            $customerId = isset($data['customer_id']) && $data['customer_id'] !== null ? (int)$data['customer_id'] : null;
            $deviceUserId = isset($data['user_id']) ? (int)$data['user_id'] : null;
            
            // Debug: Empfangene Daten loggen
            error_log("Device Create - customer_id: " . var_export($customerId, true) . ", data: " . json_encode($data));
            $details = isset($data['details']) ? json_encode($data['details']) : null;
            
            // Validierung
            $allowedStatus = ['aktiv', 'inaktiv', 'wartung', 'ausgemustert'];
            if (!in_array($status, $allowedStatus)) {
                $status = 'aktiv';
            }
            
            $allowedTypes = ['drucker', 'computer', 'netzwerk', 'smartphone', 'monitor', 'divers'];
            if ($typ && !in_array($typ, $allowedTypes)) {
                $typ = null;
            }
            
            // Rollenbasierte Validierung
            if ($userRole === 'Kunde') {
                // Kunde kann nur Geräte für seinen eigenen customer_id erstellen
                if (!$userCustomerId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Kein Kunde zugeordnet']);
                    exit;
                }
                // customer_id automatisch auf die des Users setzen
                $customerId = $userCustomerId;
                // company_id automatisch setzen, falls vorhanden
                if ($userCompanyId) {
                    $companyId = $userCompanyId;
                }
                // user_id auf den aktuellen User setzen
                $deviceUserId = $userId;
            } elseif ($userRole === 'Firmen-User') {
                // Firmen-User kann nur für sich selbst Geräte erstellen, aber Kunden auswählen
                $deviceUserId = $userId;
                $companyId = $userCompanyId;
                // customerId kann gesetzt werden, muss aber zur Firma gehören
                if ($customerId) {
                    // Prüfen ob Kunde zur Firma gehört oder keine Firma hat
                    $checkStmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND (company_id = ? OR company_id IS NULL)");
                    $checkStmt->execute([$customerId, $userCompanyId]);
                    if (!$checkStmt->fetch()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Kunde gehört nicht zur Firma']);
                        exit;
                    }
                }
            } elseif ($userRole === 'Firmen-Admin') {
                // Firmen-Admin kann nur für eigene Firma und deren Kunden erstellen
                if ($companyId && $companyId != $userCompanyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Firma']);
                    exit;
                }
                // Firma immer auf eigene Firma setzen (auch wenn nicht übergeben)
                if ($userCompanyId) {
                    $companyId = $userCompanyId;
                } else {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Firma zugeordnet']);
                    exit;
                }
                if ($customerId) {
                    // Prüfen ob Kunde zur Firma gehört oder keine Firma hat
                    $checkStmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND (company_id = ? OR company_id IS NULL)");
                    $checkStmt->execute([$customerId, $userCompanyId]);
                    if (!$checkStmt->fetch()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Kunde gehört nicht zur Firma']);
                        exit;
                    }
                }
                // user_id validieren, falls gesetzt
                if ($deviceUserId) {
                    // Prüfen ob Benutzer zur Firma gehört
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
                    $checkStmt->execute([$deviceUserId, $userCompanyId]);
                    if (!$checkStmt->fetch()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Benutzer gehört nicht zur Firma']);
                        exit;
                    }
                }
            }
            
            // Debug: Werte vor dem INSERT loggen
            error_log("Device INSERT - customer_id: " . var_export($customerId, true) . ", company_id: " . var_export($companyId, true));
            
            $stmt = $pdo->prepare("
                INSERT INTO devices (name, typ, hersteller, modell, seriennummer, mac_adresse, ip_adresse, betriebssystem, beschreibung, status, details, company_id, customer_id, user_id, erstellt_von, erstellt_datum)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $typ, $hersteller, $modell, $seriennummer, $macAdresse, $ipAdresse, $betriebssystem, $beschreibung, $status, $details, $companyId, $customerId, $deviceUserId, $userId]);
            
            $deviceId = $pdo->lastInsertId();
            
            // Log-Eintrag für Erstellung erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('device', ?, ?, 'created', 'Gerät erstellt', NOW())
                ");
                $logStmt->execute([$deviceId, $userId]);
            } catch (PDOException $e) {
                // Log-Fehler nicht kritisch, nur protokollieren
                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $companyId,
                'device_created',
                'Neues Gerät erstellt: ' . $name,
                'Ein neues Gerät "' . $name . '" wurde von ' . $userName . ' erstellt.',
                'normal',
                'devices/detail.php?id=' . $deviceId,
                'device',
                $deviceId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Gerät erfolgreich erstellt',
                'device_id' => $deviceId
            ]);
            break;
            
        case 'PUT':
            // Gerät aktualisieren
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['device_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'device_id fehlt']);
                exit;
            }
            
            $deviceId = (int)$data['device_id'];
            
            // Prüfen ob Gerät existiert und Berechtigung - alte Werte für Log abrufen
            $checkStmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
            $checkStmt->execute([$deviceId]);
            $oldDevice = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldDevice) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Gerät nicht gefunden']);
                exit;
            }
            
            $device = $oldDevice;
            $oldDeviceName = $oldDevice['name'];
            $oldDeviceCompanyId = $oldDevice['company_id'];
            
            if (!$device) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Gerät nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $device['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-User' && $device['user_id'] == $userId) {
                $hasPermission = true;
            } elseif ($userRole === 'Kunde' && $device['customer_id'] && $userCustomerId && $device['customer_id'] == $userCustomerId) {
                // Kunde darf nur Geräte bearbeiten, die seinem customer_id zugeordnet sind
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Update-Felder zusammenbauen
            $updateFields = [];
            $updateParams = [];
            
            if (isset($data['name'])) {
                $updateFields[] = "name = ?";
                $updateParams[] = trim($data['name']);
            }
            if (isset($data['typ'])) {
                $allowedTypes = ['drucker', 'computer', 'netzwerk', 'smartphone', 'monitor', 'divers'];
                if (in_array($data['typ'], $allowedTypes)) {
                    $updateFields[] = "typ = ?";
                    $updateParams[] = $data['typ'];
                }
            }
            if (isset($data['details'])) {
                $updateFields[] = "details = ?";
                $updateParams[] = json_encode($data['details']);
            }
            if (isset($data['hersteller'])) {
                $updateFields[] = "hersteller = ?";
                $updateParams[] = $data['hersteller'] ? trim($data['hersteller']) : null;
            }
            if (isset($data['modell'])) {
                $updateFields[] = "modell = ?";
                $updateParams[] = $data['modell'] ? trim($data['modell']) : null;
            }
            if (isset($data['seriennummer'])) {
                $updateFields[] = "seriennummer = ?";
                $updateParams[] = $data['seriennummer'] ? trim($data['seriennummer']) : null;
            }
            if (isset($data['mac_adresse'])) {
                $updateFields[] = "mac_adresse = ?";
                $updateParams[] = $data['mac_adresse'] ? trim($data['mac_adresse']) : null;
            }
            if (isset($data['ip_adresse'])) {
                $updateFields[] = "ip_adresse = ?";
                $updateParams[] = $data['ip_adresse'] ? trim($data['ip_adresse']) : null;
            }
            if (isset($data['betriebssystem'])) {
                $updateFields[] = "betriebssystem = ?";
                $updateParams[] = $data['betriebssystem'] ? trim($data['betriebssystem']) : null;
            }
            if (isset($data['beschreibung'])) {
                $updateFields[] = "beschreibung = ?";
                $updateParams[] = $data['beschreibung'] ? trim($data['beschreibung']) : null;
            }
            if (isset($data['status'])) {
                $allowedStatus = ['aktiv', 'inaktiv', 'wartung', 'ausgemustert'];
                if (in_array($data['status'], $allowedStatus)) {
                    $updateFields[] = "status = ?";
                    $updateParams[] = $data['status'];
                }
            }
            
            // Rollenbasierte Validierung für company_id, customer_id, user_id
            if (isset($data['company_id']) && ($userRole === 'Admin' || $userRole === 'Techniker')) {
                $updateFields[] = "company_id = ?";
                $updateParams[] = $data['company_id'] ? (int)$data['company_id'] : null;
            }
            if (isset($data['customer_id']) && ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin')) {
                $updateFields[] = "customer_id = ?";
                $updateParams[] = $data['customer_id'] ? (int)$data['customer_id'] : null;
            }
            if (isset($data['user_id']) && ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin')) {
                $updateFields[] = "user_id = ?";
                $updateParams[] = $data['user_id'] ? (int)$data['user_id'] : null;
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Felder zum Aktualisieren']);
                exit;
            }
            
            $updateFields[] = "geaendert_datum = NOW()";
            $updateParams[] = $deviceId;
            
            // Log-Einträge erstellen für geänderte Felder (vor dem Update)
            $fieldsToCheck = [
                'name' => 'name',
                'typ' => 'typ',
                'hersteller' => 'hersteller',
                'modell' => 'modell',
                'seriennummer' => 'seriennummer',
                'mac_adresse' => 'mac_adresse',
                'ip_adresse' => 'ip_adresse',
                'betriebssystem' => 'betriebssystem',
                'beschreibung' => 'beschreibung',
                'status' => 'status',
                'company_id' => 'company_id',
                'customer_id' => 'customer_id',
                'user_id' => 'user_id',
                'details' => 'details'
            ];
            
            foreach ($fieldsToCheck as $dataKey => $dbField) {
                if (isset($data[$dataKey])) {
                    $oldValue = $oldDevice[$dbField] ?? null;
                    $newValue = $data[$dataKey];
                    
                    // Für Details JSON-String vergleichen
                    if ($dbField === 'details') {
                        $oldValueDecoded = $oldDevice['details'] ?? null;
                        if ($oldValueDecoded) {
                            $oldValue = is_string($oldValueDecoded) ? $oldValueDecoded : json_encode($oldValueDecoded);
                        } else {
                            $oldValue = null;
                        }
                        $newValue = json_encode($data['details']);
                    } else {
                        $newValue = is_string($newValue) ? trim($newValue) : $newValue;
                        if ($newValue === '') $newValue = null;
                    }
                    
                    // Vergleich: NULL und leere Strings werden gleich behandelt
                    $oldValueForCompare = ($oldValue === null || $oldValue === '') ? null : (string)$oldValue;
                    $newValueForCompare = ($newValue === null || $newValue === '') ? null : (string)$newValue;
                    
                    // Nur loggen wenn sich der Wert geändert hat
                    if ($oldValueForCompare !== $newValueForCompare) {
                        $oldValueStr = $oldValue !== null ? (string)$oldValue : '';
                        $newValueStr = $newValue !== null ? (string)$newValue : '';
                        
                        // Log-Eintrag erstellen
                        try {
                            $logStmt = $pdo->prepare("
                                INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                                VALUES ('device', ?, ?, 'updated', ?, ?, ?, NOW())
                            ");
                            $logStmt->execute([
                                $deviceId,
                                $userId,
                                $dbField,
                                $oldValueStr,
                                $newValueStr
                            ]);
                        } catch (PDOException $e) {
                            // Log-Fehler nicht kritisch, nur protokollieren
                            error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                        }
                    }
                }
            }
            
            $sql = "UPDATE devices SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateParams);
            
            // Benachrichtigungen für Änderungen erstellen
            $deviceName = isset($data['name']) ? trim($data['name']) : $oldDeviceName;
            $deviceName = $deviceName ?: 'Unbekannt';
            $currentCompanyId = isset($data['company_id']) ? (int)$data['company_id'] : $oldDeviceCompanyId;
            
            // Statusänderungen benachrichtigen (offline/online)
            if (isset($data['status'])) {
                $newStatus = $data['status'];
                $oldStatus = $oldDevice['status'];
                
                // Nur benachrichtigen wenn Status sich geändert hat
                if ($newStatus !== $oldStatus) {
                    $notificationType = 'device_status_changed';
                    $notificationTitle = 'Gerät-Status geändert: ' . $deviceName;
                    $notificationMessage = 'Der Status des Geräts "' . $deviceName . '" wurde von ' . $userName . ' geändert.';
                    $relevanz = 'normal';
                    
                    if ($newStatus === 'inaktiv' && $oldStatus !== 'inaktiv') {
                        $notificationType = 'device_offline';
                        $notificationTitle = 'Gerät offline: ' . $deviceName;
                        $notificationMessage = 'Das Gerät "' . $deviceName . '" wurde von ' . $userName . ' als offline markiert.';
                        $relevanz = 'hoch';
                    } elseif ($newStatus === 'aktiv' && $oldStatus === 'inaktiv') {
                        $notificationType = 'device_online';
                        $notificationTitle = 'Gerät online: ' . $deviceName;
                        $notificationMessage = 'Das Gerät "' . $deviceName . '" wurde von ' . $userName . ' als online markiert.';
                    }
                    
                    // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                    createNotificationsForAction(
                        $userId,
                        $currentCompanyId,
                        $notificationType,
                        $notificationTitle,
                        $notificationMessage,
                        $relevanz,
                        'devices/detail.php?id=' . $deviceId,
                        'device',
                        $deviceId
                    );
                } else {
                    // Normale Änderung ohne Statuswechsel
                    createNotificationsForAction(
                        $userId,
                        $currentCompanyId,
                        'device_updated',
                        'Gerät aktualisiert: ' . $deviceName,
                        'Das Gerät "' . $deviceName . '" wurde von ' . $userName . ' aktualisiert.',
                        'normal',
                        'devices/detail.php?id=' . $deviceId,
                        'device',
                        $deviceId
                    );
                }
            } else {
                // Normale Änderung ohne Status
                createNotificationsForAction(
                    $userId,
                    $currentCompanyId,
                    'device_updated',
                    'Gerät aktualisiert: ' . $deviceName,
                    'Das Gerät "' . $deviceName . '" wurde von ' . $userName . ' aktualisiert.',
                    'normal',
                    'devices/detail.php?id=' . $deviceId,
                    'device',
                    $deviceId
                );
            }
            
            echo json_encode(['success' => true, 'message' => 'Gerät aktualisiert']);
            break;
            
        case 'DELETE':
            // Techniker, Firmen-Admin, Firmen-User und Kunde können den Löschvorgang ausführen (Status auf inaktiv setzen)
            // Nur Admin darf wirklich löschen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin' && $userRole !== 'Firmen-User' && $userRole !== 'Kunde') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $deviceId = (int)$_GET['id'];
            
            // Prüfen ob Gerät existiert
            $checkStmt = $pdo->prepare("SELECT id, company_id, customer_id, user_id, status FROM devices WHERE id = ?");
            $checkStmt->execute([$deviceId]);
            $device = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Gerät nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            if ($userRole === 'Firmen-Admin' && $device['company_id'] != $userCompanyId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            if ($userRole === 'Firmen-User' && $device['user_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            if ($userRole === 'Kunde') {
                if (!$userCustomerId || !$device['customer_id'] || $device['customer_id'] != $userCustomerId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
            }
            
            // Techniker, Firmen-Admin, Firmen-User und Kunde setzen Geräte auf "inaktiv" statt sie zu löschen
            if ($userRole !== 'Admin') {
                // Prüfen ob Gerät bereits inaktiv ist
                if ($device['status'] === 'inaktiv') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Gerät ist bereits inaktiv']);
                    exit;
                }
                
                // Log-Eintrag für Deaktivierung erstellen
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                        VALUES ('device', ?, ?, 'updated', 'status', ?, 'inaktiv', NOW())
                    ");
                    $logStmt->execute([$deviceId, $userId, $device['status']]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                }
                
                // Status auf inaktiv setzen
                $updateStmt = $pdo->prepare("UPDATE devices SET status = 'inaktiv', geaendert_datum = NOW() WHERE id = ?");
                $updateStmt->execute([$deviceId]);
                
                // Gerätnamen für Benachrichtigung abrufen
                $deviceStmt = $pdo->prepare("SELECT name FROM devices WHERE id = ?");
                $deviceStmt->execute([$deviceId]);
                $deviceName = $deviceStmt->fetchColumn() ?: 'Unbekannt';
                
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                createNotificationsForAction(
                    $userId,
                    $device['company_id'],
                    'device_status_changed',
                    'Gerät auf inaktiv gesetzt: ' . $deviceName,
                    'Das Gerät "' . $deviceName . '" wurde von ' . $userName . ' auf inaktiv gesetzt.',
                    'hoch',
                    'devices/detail.php?id=' . $deviceId,
                    'device',
                    $deviceId
                );
                
                echo json_encode(['success' => true, 'message' => 'Gerät wurde auf inaktiv gesetzt']);
                break;
            }
            
            // Nur Admins dürfen wirklich löschen (dieser Code wird nur erreicht, wenn $userRole === 'Admin')
            // Gerätnamen für Benachrichtigung abrufen (vor dem Löschen)
            $deviceStmt = $pdo->prepare("SELECT name FROM devices WHERE id = ?");
            $deviceStmt->execute([$deviceId]);
            $deviceName = $deviceStmt->fetchColumn() ?: 'Unbekannt';
            
            // Log-Eintrag für Löschung erstellen (vor dem Löschen)
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('device', ?, ?, 'deleted', 'Gerät gelöscht', NOW())
                ");
                $logStmt->execute([$deviceId, $userId]);
            } catch (PDOException $e) {
                // Log-Fehler nicht kritisch, nur protokollieren
                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $device['company_id'],
                'device_deleted',
                'Gerät gelöscht: ' . $deviceName,
                'Das Gerät "' . $deviceName . '" wurde von ' . $userName . ' gelöscht.',
                'kritisch',
                'devices/',
                'device',
                $deviceId
            );
            
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
            $stmt->execute([$deviceId]);
            
            echo json_encode(['success' => true, 'message' => 'Gerät gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Devices API Error: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Devices API Throwable: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler beim Verarbeiten der Anfrage']);
}
