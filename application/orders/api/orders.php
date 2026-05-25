<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__, 2) . '/customers/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/inventory/api/consumables_pending_stockin.php';

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
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, customer_id FROM users WHERE id = :user_id LIMIT 1");
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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

/** Früherer Bestellstatus „Offen“ → „Neu“. */
function orders_api_normalize_status(?string $s): string
{
    if ($s === null || $s === '') {
        return 'Neu';
    }
    return $s === 'Offen' ? 'Neu' : $s;
}

try {
    /**
     * Liefert genau eine zugeordnete Firma eines Verbrauchsmaterials, sonst null.
     * Berücksichtigt Mehrfachzuordnung (consumable_company_link) und Legacy company_id.
     */
    $resolveSingleCompanyForConsumable = static function (PDO $pdo, int $consumableId): ?int {
        if ($consumableId <= 0) {
            return null;
        }

        $companyIds = [];
        $seen = [];

        try {
            $pdo->query('SELECT 1 FROM consumable_company_link LIMIT 0');
            $stmtLinks = $pdo->prepare('SELECT company_id FROM consumable_company_link WHERE consumable_id = ?');
            $stmtLinks->execute([$consumableId]);
            foreach ($stmtLinks->fetchAll(PDO::FETCH_COLUMN) as $cidRaw) {
                $cid = (int)$cidRaw;
                if ($cid > 0 && !isset($seen[$cid])) {
                    $seen[$cid] = true;
                    $companyIds[] = $cid;
                }
            }
        } catch (PDOException $e) {
            // Tabelle kann in älteren Installationen fehlen -> Fallback auf consumables.company_id.
        }

        if ($companyIds === []) {
            try {
                $stmtLegacy = $pdo->prepare('SELECT company_id FROM consumables WHERE id = ? LIMIT 1');
                $stmtLegacy->execute([$consumableId]);
                $legacyCompanyId = (int)$stmtLegacy->fetchColumn();
                if ($legacyCompanyId > 0) {
                    $companyIds[] = $legacyCompanyId;
                }
            } catch (PDOException $e) {
                return null;
            }
        }

        return count($companyIds) === 1 ? (int)$companyIds[0] : null;
    };

    switch ($method) {
        case 'GET':
            // Prüfen ob für ein Verbrauchsmaterial (Lager) eine Bestellung existiert, die noch nicht „Im Lager“ oder „Angekommen“ ist
            if (isset($_GET['consumable_id'])) {
                $consumableId = (int)$_GET['consumable_id'];
                if ($consumableId > 0) {
                    $marker = '[inventar_consumable_id=' . $consumableId . ']';
                    $checkStmt = $pdo->prepare("SELECT 1 FROM orders WHERE status NOT IN ('Im Lager', 'Angekommen') AND (notizen LIKE :marker OR beschreibung LIKE :marker) LIMIT 1");
                    $checkStmt->bindValue(':marker', '%' . $marker . '%', PDO::PARAM_STR);
                    $checkStmt->execute();
                    $hasOpen = $checkStmt->fetchColumn() !== false;
                    echo json_encode(['success' => true, 'has_open' => (bool)$hasOpen]);
                    exit;
                }
            }

            // Einzelne Bestellung abrufen
            if (isset($_GET['id'])) {
                $orderId = (int)$_GET['id'];
                
                $hasBestellungDurchCol = false;
                try {
                    $colCheck = $pdo->query("SHOW COLUMNS FROM orders LIKE 'bestellung_durch'");
                    $hasBestellungDurchCol = $colCheck && $colCheck->rowCount() > 0;
                } catch (PDOException $e) { /* ignore */ }
                
                $bestellungDurchSelect = $hasBestellungDurchCol ? "o.bestellung_durch,\n                        " : "";
                $hasProjectId = false;
                try { $pc = $pdo->query("SHOW COLUMNS FROM orders LIKE 'project_id'"); $hasProjectId = $pc && $pc->rowCount() > 0; } catch (PDOException $e) {}
                $projectSelect = $hasProjectId ? "o.project_id,\n                        proj.bezeichnung as project_name," : "";
                $projectJoin = $hasProjectId ? "LEFT JOIN projects proj ON o.project_id = proj.id" : "";
                $hasGarantieCol = false;
                try {
                    $gCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'garantie'");
                    $hasGarantieCol = $gCol && $gCol->rowCount() > 0;
                } catch (PDOException $e) { /* ignore */ }
                $garantieSelect = $hasGarantieCol ? "o.garantie,\n                        " : "";
                $sql = "
                    SELECT 
                        o.id,
                        o.bestellnummer,
                        o.beschreibung,
                        o.notizen,
                        o.tracking_nummer,
                        o.tracking_link,
                        o.status,
                        " . $garantieSelect . "
                        o.company_id,
                        o.customer_id,
                        o.ticket_id,
                        " . $bestellungDurchSelect . "
                        " . $projectSelect . "
                        o.erstellt_von,
                        o.erstellt_datum,
                        o.geaendert_datum,
                        c.name as company_name,
                        cust.name as customer_name,
                        cust.email as customer_email,
                        cust.telefon as customer_telefon,
                        cust.adresse as customer_adresse,
                        cust.plz as customer_plz,
                        cust.ort as customer_ort,
                        cust.lieferadresse as customer_lieferadresse,
                        cust.liefer_plz as customer_liefer_plz,
                        cust.liefer_ort as customer_liefer_ort,
                        u.vorname as erstellt_von_vorname,
                        u.nachname as erstellt_von_nachname,
                        u.email as erstellt_von_email,
                        d.id as device_id,
                        d.name as device_name,
                        d.typ as device_typ,
                        d.hersteller as device_hersteller,
                        d.modell as device_modell,
                        d.seriennummer as device_seriennummer,
                        d.beschreibung as device_beschreibung,
                        d.mac_adresse as device_mac_adresse,
                        d.ip_adresse as device_ip_adresse,
                        d.betriebssystem as device_betriebssystem,
                        t.ticket_nummer as ticket_nummer
                    FROM orders o
                    LEFT JOIN companies c ON o.company_id = c.id
                    LEFT JOIN customers cust ON o.customer_id = cust.id
                    LEFT JOIN users u ON o.erstellt_von = u.id
                    LEFT JOIN tickets t ON o.ticket_id = t.id
                    LEFT JOIN devices d ON t.device_id = d.id
                    $projectJoin
                    WHERE o.id = :order_id
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $stmt->execute();
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($order && isset($order['status'])) {
                    $order['status'] = orders_api_normalize_status($order['status']);
                }
                
                if (!$order) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Bestellung nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $order['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-User') {
                    // Firmen-User: nur eigene Bestellungen
                    $hasPermission = ((int)$order['erstellt_von'] === (int)$userId);
                } elseif ($userRole === 'Kunde') {
                    // Kunde: nur eigene Bestellungen
                    $hasPermission = ((int)$order['erstellt_von'] === (int)$userId);
                }
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                // Status-Historie laden (bemerkung nur wenn Spalte existiert, Migration 073)
                $hasBemerkungCol = false;
                try {
                    $bemerkungCheck = $pdo->query("SHOW COLUMNS FROM order_status_history LIKE 'bemerkung'");
                    $hasBemerkungCol = $bemerkungCheck && $bemerkungCheck->rowCount() > 0;
                } catch (PDOException $e) { /* Spalte fehlt */ }
                $bemerkungSel = $hasBemerkungCol ? ', osh.bemerkung' : '';
                $historySql = "
                    SELECT 
                        osh.status,
                        osh.geaendert_datum,
                        osh.geaendert_von
                        {$bemerkungSel},
                        u.vorname,
                        u.nachname
                    FROM order_status_history osh
                    LEFT JOIN users u ON osh.geaendert_von = u.id
                    WHERE osh.order_id = :order_id
                    ORDER BY osh.geaendert_datum ASC
                ";
                $historyStmt = $pdo->prepare($historySql);
                $historyStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $historyStmt->execute();
                $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
                if (!$hasBemerkungCol) {
                    foreach ($statusHistory as &$row) {
                        $row['bemerkung'] = null;
                    }
                    unset($row);
                }
                foreach ($statusHistory as &$hRow) {
                    if (isset($hRow['status'])) {
                        $hRow['status'] = orders_api_normalize_status($hRow['status']);
                    }
                }
                unset($hRow);
                
                $order['status_history'] = $statusHistory;
                
                // Anhänge des zugehörigen Tickets laden (alle Kommentar-Anhänge des Tickets)
                if (!empty($order['ticket_id'])) {
                    try {
                        $attStmt = $pdo->prepare("
                            SELECT ca.id, ca.dateiname, ca.dateipfad, ca.dateigroesse, ca.mime_type
                            FROM comment_attachments ca
                            INNER JOIN ticket_comments tc ON ca.comment_id = tc.id
                            WHERE tc.ticket_id = ?
                            ORDER BY ca.erstellt_datum ASC
                        ");
                        $attStmt->execute([$order['ticket_id']]);
                        $order['ticket_attachments'] = $attStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $order['ticket_attachments'] = [];
                    }
                } else {
                    $order['ticket_attachments'] = [];
                }
                
                // Kunde und Firma entschlüsseln
                if (isset($order['company_name'])) {
                    $order['company_name'] = decrypt_from_db($order['company_name']);
                }
                if (isset($order['customer_name'])) {
                    $order['customer_name'] = decrypt_from_db($order['customer_name']);
                }
                if (isset($order['customer_email'])) {
                    $order['customer_email'] = decrypt_from_db($order['customer_email']);
                }
                if (isset($order['customer_telefon'])) {
                    $order['customer_telefon'] = decrypt_from_db($order['customer_telefon']);
                }
                if (isset($order['customer_adresse'])) {
                    $order['customer_adresse'] = decrypt_from_db($order['customer_adresse']);
                }
                if (isset($order['customer_plz'])) {
                    $order['customer_plz'] = decrypt_from_db($order['customer_plz']);
                }
                if (isset($order['customer_ort'])) {
                    $order['customer_ort'] = decrypt_from_db($order['customer_ort']);
                }
                if (isset($order['customer_lieferadresse'])) {
                    $order['customer_lieferadresse'] = decrypt_from_db($order['customer_lieferadresse']);
                }
                if (isset($order['customer_liefer_plz'])) {
                    $order['customer_liefer_plz'] = decrypt_from_db($order['customer_liefer_plz']);
                }
                if (isset($order['customer_liefer_ort'])) {
                    $order['customer_liefer_ort'] = decrypt_from_db($order['customer_liefer_ort']);
                }
                if (isset($order['project_name'])) {
                    $order['project_name'] = decrypt_from_db($order['project_name']);
                }
                if ($hasGarantieCol) {
                    $order['garantie'] = !empty($order['garantie']) ? 1 : 0;
                } else {
                    $order['garantie'] = 0;
                }
                
                echo json_encode([
                    'success' => true,
                    'order' => $order
                ]);
                exit;
            }
            
            // Alle Bestellungen abrufen mit rollenbasierter Filterung
            $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $customerFilter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
            $statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
            
            $whereConditions = [];
            $params = [];
            
            // Rollenbasierte Filterung
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // Admin und Techniker sehen alle Bestellungen
                if ($companyFilter) {
                    $whereConditions[] = "o.company_id = :company_filter";
                    $params[':company_filter'] = $companyFilter;
                }
                if ($customerFilter) {
                    $whereConditions[] = "o.customer_id = :customer_filter";
                    $params[':customer_filter'] = $customerFilter;
                }
            } elseif ($userRole === 'Firmen-Admin') {
                // Firmen-Admin sieht Bestellungen der eigenen Firma
                if ($userCompanyId) {
                    $whereConditions[] = "(o.company_id = :user_company_id OR o.customer_id IN (SELECT id FROM customers WHERE company_id = :user_company_id2 OR company_id IS NULL))";
                    $params[':user_company_id'] = $userCompanyId;
                    $params[':user_company_id2'] = $userCompanyId;
                } else {
                    $whereConditions[] = "1 = 0";
                }
                if ($customerFilter) {
                    $whereConditions[] = "o.customer_id = :customer_filter";
                    $params[':customer_filter'] = $customerFilter;
                }
            } elseif ($userRole === 'Firmen-User') {
                // Firmen-User sieht nur eigene Bestellungen
                $whereConditions[] = "o.erstellt_von = :user_id";
                $params[':user_id'] = $userId;
                if ($customerFilter) {
                    $whereConditions[] = "o.customer_id = :customer_filter";
                    $params[':customer_filter'] = $customerFilter;
                }
            } elseif ($userRole === 'Kunde') {
                // Kunde sieht nur eigene Bestellungen
                $whereConditions[] = "o.erstellt_von = :user_id";
                $params[':user_id'] = $userId;
            } else {
                $whereConditions[] = "1 = 0";
            }
            
            if ($statusFilter) {
                $whereConditions[] = "o.status = :status_filter";
                $params[':status_filter'] = orders_api_normalize_status(trim((string) $statusFilter));
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $hasGarantieListCol = false;
            try {
                $gListCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'garantie'");
                $hasGarantieListCol = $gListCol && $gListCol->rowCount() > 0;
            } catch (PDOException $e) { /* ignore */ }
            $garantieListSelect = $hasGarantieListCol ? "o.garantie,\n                    " : "";
            
            $sql = "
                SELECT 
                    o.id,
                    o.bestellnummer,
                    o.beschreibung,
                    o.tracking_nummer,
                    o.tracking_link,
                    o.status,
                    " . $garantieListSelect . "
                    o.company_id,
                    o.customer_id,
                    o.ticket_id,
                    o.bestellung_durch,
                    o.erstellt_von,
                    o.erstellt_datum,
                    o.geaendert_datum,
                    c.name as company_name,
                    cust.name as customer_name,
                    u.vorname as erstellt_von_vorname,
                    u.nachname as erstellt_von_nachname,
                    u.email as erstellt_von_email,
                    d.id as device_id,
                    d.name as device_name,
                    d.hersteller as device_hersteller,
                    d.modell as device_modell,
                    d.seriennummer as device_seriennummer,
                    d.beschreibung as device_standort,
                    t.ticket_nummer
                FROM orders o
                LEFT JOIN companies c ON o.company_id = c.id
                LEFT JOIN customers cust ON o.customer_id = cust.id
                LEFT JOIN users u ON o.erstellt_von = u.id
                LEFT JOIN tickets t ON o.ticket_id = t.id
                LEFT JOIN devices d ON t.device_id = d.id
                $whereClause
                ORDER BY o.erstellt_datum DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Kunde und Firma entschlüsseln
            foreach ($orders as &$order) {
                if (isset($order['company_name'])) {
                    $order['company_name'] = decrypt_from_db($order['company_name']);
                }
                if (isset($order['customer_name'])) {
                    $order['customer_name'] = decrypt_from_db($order['customer_name']);
                }
                if ($hasGarantieListCol) {
                    $order['garantie'] = !empty($order['garantie']) ? 1 : 0;
                } else {
                    $order['garantie'] = 0;
                }
                if (isset($order['status'])) {
                    $order['status'] = orders_api_normalize_status($order['status']);
                }
            }
            unset($order);
            
            echo json_encode([
                'success' => true,
                'orders' => $orders
            ]);
            break;
            
        case 'POST':
            // Neue Bestellung erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
                exit;
            }
            
            $bestellnummer = isset($data['bestellnummer']) ? trim($data['bestellnummer']) : null;
            $beschreibung = isset($data['beschreibung']) ? trim($data['beschreibung']) : null;
            $notizen = null;
            if (isset($data['notiz'])) {
                $notizen = trim((string)$data['notiz']);
            } elseif (isset($data['notizen'])) {
                $notizen = trim((string)$data['notizen']);
            }
            $consumableId = isset($data['consumable_id']) && $data['consumable_id'] ? (int)$data['consumable_id'] : null;
            if ($consumableId > 0) {
                $marker = '[inventar_consumable_id=' . $consumableId . ']';
                if ($notizen === null || $notizen === '') {
                    $notizen = $marker;
                } elseif (strpos($notizen, $marker) === false) {
                    $notizen .= "\n" . $marker;
                }
            }
            if ($notizen === '') {
                $notizen = null;
            }
            $trackingNummer = isset($data['tracking_nummer']) ? trim($data['tracking_nummer']) : null;
            $trackingLink = isset($data['tracking_link']) ? trim($data['tracking_link']) : null;
            $status = orders_api_normalize_status(isset($data['status']) ? trim((string) $data['status']) : null);
            $companyId = isset($data['company_id']) && $data['company_id'] ? (int)$data['company_id'] : null;
            $customerId = isset($data['customer_id']) && $data['customer_id'] ? (int)$data['customer_id'] : null;
            $projectId = isset($data['project_id']) && $data['project_id'] ? (int)$data['project_id'] : null;
            $logBeschreibung = isset($data['log_beschreibung']) ? trim($data['log_beschreibung']) : null;
            if ($logBeschreibung === '') {
                $logBeschreibung = null;
            }
            if (!$companyId && $consumableId > 0) {
                $companyIdFromConsumable = $resolveSingleCompanyForConsumable($pdo, $consumableId);
                if ($companyIdFromConsumable !== null) {
                    $companyId = $companyIdFromConsumable;
                }
            }
            
            // Status validieren
            $validStatuses = ['Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager', 'Angekommen'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'Neu';
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') {
                if ($companyId && $companyId == $userCompanyId) {
                    $hasPermission = true;
                } elseif (!$companyId) {
                    // Wenn keine Firma angegeben, Firma des Users verwenden
                    if ($userCompanyId) {
                        $companyId = $userCompanyId;
                        $hasPermission = true;
                    } else {
                        // Firmen-User/Firmen-Admin ohne company_id: Bestellung ohne Firma zulassen
                        $hasPermission = true;
                    }
                }
            } elseif ($userRole === 'Kunde' && $userCustomerId) {
                // Kunde kann nur Bestellungen für sich selbst erstellen
                if ($customerId == $userCustomerId || !$customerId) {
                    $customerId = $userCustomerId;
                    $hasPermission = true;
                }
            }

            // Firmen-Bezug von customer_id absichern (Firmen-Admin / Firmen-User)
            if ($hasPermission && ($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && $customerId) {
                if (!$userCompanyId) {
                    // Ohne company_id kann keine Zuordnung geprüft werden -> customer_id entfernen
                    $customerId = null;
                } else {
                    $custStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = :id LIMIT 1");
                    $custStmt->bindValue(':id', (int)$customerId, PDO::PARAM_INT);
                    $custStmt->execute();
                    $cust = $custStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$cust) {
                        $customerId = null;
                    } else {
                        $custCompanyId = $cust['company_id'];
                        // Erlaubt: Kunde gehört zur eigenen Firma oder ist firmenlos (company_id NULL)
                        if (!($custCompanyId === null || (int)$custCompanyId === (int)$userCompanyId)) {
                            http_response_code(403);
                            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                            exit;
                        }
                    }
                }
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Bestellnummer generieren falls nicht vorhanden
            if (!$bestellnummer) {
                $prefix = ($consumableId > 0) ? 'Lager-' : 'BEST-';
                $bestellnummer = $prefix . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            $hasProjectIdCol = false;
            try { $pc = $pdo->query("SHOW COLUMNS FROM orders LIKE 'project_id'"); $hasProjectIdCol = $pc && $pc->rowCount() > 0; } catch (PDOException $e) {}
            $projectIdCol = $hasProjectIdCol ? ', project_id' : '';
            $projectIdVal = $hasProjectIdCol ? ', :project_id' : '';
            $hasGarantieInsertCol = false;
            try {
                $gIns = $pdo->query("SHOW COLUMNS FROM orders LIKE 'garantie'");
                $hasGarantieInsertCol = $gIns && $gIns->rowCount() > 0;
            } catch (PDOException $e) { /* ignore */ }
            $garantieVal = (!empty($data['garantie']) && $data['garantie'] !== '0' && $data['garantie'] !== 0) ? 1 : 0;
            $garantieInsertCol = $hasGarantieInsertCol ? ', garantie' : '';
            $garantieInsertVal = $hasGarantieInsertCol ? ', :garantie' : '';
            
            $sql = "INSERT INTO orders (bestellnummer, beschreibung, notizen, tracking_nummer, tracking_link, status{$garantieInsertCol}, company_id, customer_id{$projectIdCol}, erstellt_von, geaendert_datum) 
                    VALUES (:bestellnummer, :beschreibung, :notizen, :tracking_nummer, :tracking_link, :status{$garantieInsertVal}, :company_id, :customer_id{$projectIdVal}, :erstellt_von, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':bestellnummer', $bestellnummer, PDO::PARAM_STR);
            $stmt->bindValue(':beschreibung', $beschreibung, PDO::PARAM_STR);
            $stmt->bindValue(':notizen', $notizen, $notizen ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':tracking_nummer', $trackingNummer, $trackingNummer ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':tracking_link', $trackingLink, $trackingLink ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            if ($hasGarantieInsertCol) {
                $stmt->bindValue(':garantie', $garantieVal, PDO::PARAM_INT);
            }
            $stmt->bindValue(':company_id', $companyId, $companyId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':customer_id', $customerId, $customerId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            if ($hasProjectIdCol) {
                $stmt->bindValue(':project_id', $projectId, $projectId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            }
            $stmt->bindValue(':erstellt_von', $userId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $orderId = $pdo->lastInsertId();
                
                // Initialen Status in History speichern
                $historySql = "INSERT INTO order_status_history (order_id, status, geaendert_von) 
                              VALUES (:order_id, :status, :geaendert_von)";
                $historyStmt = $pdo->prepare($historySql);
                $historyStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $historyStmt->bindValue(':status', $status, PDO::PARAM_STR);
                $historyStmt->bindValue(':geaendert_von', $userId, PDO::PARAM_INT);
                $historyStmt->execute();
                
                // Log-Eintrag für Erstellung
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, beschreibung, erstellt_datum)
                        VALUES ('order', ?, ?, 'created', NULL, NULL, NULL, ?, NOW())
                    ");
                    $logStmt->execute([$orderId, $userId, $logBeschreibung]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                }
                
                // Benutzername für Benachrichtigung abrufen
                try {
                    $userStmt = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = ?");
                    $userStmt->execute([$userId]);
                    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                    $userName = ($user && $user['vorname'] && $user['nachname']) 
                        ? trim($user['vorname'] . ' ' . $user['nachname']) 
                        : 'Ein Benutzer';
                } catch (PDOException $e) {
                    $userName = 'Ein Benutzer';
                }
                
                // Relevanz ist immer hoch bei Bestellung erstellt
                $relevanz = 'hoch';
                
                // Benachrichtigungen erstellen (Betreff = Beschreibung, sonst Bestellnummer)
                $orderBetreffNeu = ($beschreibung !== null && trim($beschreibung) !== '') ? trim($beschreibung) : $bestellnummer;
                createNotificationsForAction(
                    $userId,
                    $companyId,
                    'order_created',
                    'Neue Bestellung erstellt: ' . $orderBetreffNeu,
                    'Eine neue Bestellung "' . $orderBetreffNeu . '" wurde von ' . $userName . ' erstellt.',
                    $relevanz,
                    'orders/detail.php?id=' . $orderId,
                    'order',
                    $orderId
                );

                if (in_array($status, ['Im Lager', 'Angekommen'], true)) {
                    consumableApplyPendingStockinFlagFromOrderNotizen($pdo, $notizen ?? '', true, $beschreibung ?? null);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Bestellung erfolgreich erstellt',
                    'order_id' => $orderId
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Erstellen der Bestellung']);
            }
            break;
            
        case 'PUT':
            // Bestellung aktualisieren
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id']) || !$data['id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            $orderId = (int)$data['id'];
            
            // Bestellung laden
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Bestellung nicht gefunden']);
                exit;
            }
            if (isset($order['status'])) {
                $order['status'] = orders_api_normalize_status($order['status']);
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif (($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && $order['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-User' && (int)$order['erstellt_von'] === (int)$userId) {
                $hasPermission = true;
            } elseif ($userRole === 'Kunde' && $order['customer_id'] && $userCustomerId) {
                if ($order['customer_id'] == $userCustomerId) {
                    $hasPermission = true;
                }
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $bestellnummer = isset($data['bestellnummer']) ? trim($data['bestellnummer']) : $order['bestellnummer'];
            $beschreibung = isset($data['beschreibung']) ? trim($data['beschreibung']) : $order['beschreibung'];
            if (isset($data['notizen'])) {
                $notizen = trim((string)$data['notizen']);
            } elseif (isset($data['notiz'])) {
                $notizen = trim((string)$data['notiz']);
            } else {
                $notizen = $order['notizen'] ?? null;
            }
            if ($notizen === '') {
                $notizen = null;
            }
            $trackingNummer = isset($data['tracking_nummer']) ? trim($data['tracking_nummer']) : ($order['tracking_nummer'] ?? null);
            $trackingLink = isset($data['tracking_link']) ? trim($data['tracking_link']) : ($order['tracking_link'] ?? null);
            if (isset($data['status'])) {
                $status = orders_api_normalize_status(trim((string) $data['status']));
            } else {
                $status = orders_api_normalize_status($order['status'] ?? null);
            }
            $companyId = isset($data['company_id']) && $data['company_id'] ? (int)$data['company_id'] : $order['company_id'];
            $customerId = isset($data['customer_id']) && $data['customer_id'] ? (int)$data['customer_id'] : $order['customer_id'];
            $bestellungDurch = isset($data['bestellung_durch']) ? trim($data['bestellung_durch']) : ($order['bestellung_durch'] ?? null);
            if ($bestellungDurch !== null && $bestellungDurch !== '' && !in_array($bestellungDurch, ['intern', 'kunde_firma', 'firma', 'kunde', 'lagersystem'], true)) {
                $bestellungDurch = $order['bestellung_durch'] ?? null;
            }
            
            $hasGarantiePutCol = false;
            try {
                $gPut = $pdo->query("SHOW COLUMNS FROM orders LIKE 'garantie'");
                $hasGarantiePutCol = $gPut && $gPut->rowCount() > 0;
            } catch (PDOException $e) { /* ignore */ }
            if ($hasGarantiePutCol) {
                if (isset($data['garantie'])) {
                    $garantie = (!empty($data['garantie']) && $data['garantie'] !== '0' && $data['garantie'] !== 0 && $data['garantie'] !== 'false') ? 1 : 0;
                } else {
                    $garantie = (int)($order['garantie'] ?? 0);
                }
            } else {
                $garantie = 0;
            }
            
            // Status validieren
            $validStatuses = ['Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager', 'Angekommen'];
            if (!in_array($status, $validStatuses, true)) {
                $status = orders_api_normalize_status($order['status'] ?? null);
            }

            // Kunden und Firmen-User dürfen den Status nicht ändern
            if (($userRole === 'Kunde' || $userRole === 'Firmen-User') && $status !== $order['status']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Felder prüfen und Logs erstellen
            $fieldsToCheck = [
                'bestellnummer' => ['old' => $order['bestellnummer'] ?? null, 'new' => $bestellnummer],
                'beschreibung' => ['old' => $order['beschreibung'] ?? null, 'new' => $beschreibung],
                'notizen' => ['old' => $order['notizen'] ?? null, 'new' => $notizen],
                'tracking_nummer' => ['old' => $order['tracking_nummer'] ?? null, 'new' => $trackingNummer],
                'tracking_link' => ['old' => $order['tracking_link'] ?? null, 'new' => $trackingLink],
                'status' => ['old' => $order['status'] ?? null, 'new' => $status],
                'company_id' => ['old' => $order['company_id'] ?? null, 'new' => $companyId],
                'customer_id' => ['old' => $order['customer_id'] ?? null, 'new' => $customerId],
                'bestellung_durch' => ['old' => $order['bestellung_durch'] ?? null, 'new' => $bestellungDurch]
            ];
            if ($hasGarantiePutCol) {
                $fieldsToCheck['garantie'] = ['old' => (int)($order['garantie'] ?? 0), 'new' => $garantie];
            }
            
            foreach ($fieldsToCheck as $fieldName => $values) {
                $oldValue = $values['old'];
                $newValue = $values['new'];
                
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
                            VALUES ('order', ?, ?, 'updated', ?, ?, ?, NOW())
                        ");
                        $logStmt->execute([
                            $orderId,
                            $userId,
                            $fieldName,
                            $oldValueStr,
                            $newValueStr
                        ]);
                    } catch (PDOException $e) {
                        error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                    }
                }
            }
            
            $hasBestellungDurch = false;
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM orders LIKE 'bestellung_durch'");
                $hasBestellungDurch = $colCheck && $colCheck->rowCount() > 0;
            } catch (PDOException $e) { /* ignore */ }
            
            $sql = "UPDATE orders SET 
                    bestellnummer = :bestellnummer,
                    beschreibung = :beschreibung,
                    notizen = :notizen,
                    tracking_nummer = :tracking_nummer,
                    tracking_link = :tracking_link,
                    status = :status"
                    . ($hasGarantiePutCol ? ",\n                    garantie = :garantie" : "")
                    . ",
                    company_id = :company_id,
                    customer_id = :customer_id"
                    . ($hasBestellungDurch ? ",\n                    bestellung_durch = :bestellung_durch" : "")
                    . "
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':bestellnummer', $bestellnummer, PDO::PARAM_STR);
            $stmt->bindValue(':beschreibung', $beschreibung, PDO::PARAM_STR);
            $stmt->bindValue(':notizen', $notizen, $notizen ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':tracking_nummer', $trackingNummer, $trackingNummer ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':tracking_link', $trackingLink, $trackingLink ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            if ($hasGarantiePutCol) {
                $stmt->bindValue(':garantie', $garantie, PDO::PARAM_INT);
            }
            $stmt->bindValue(':company_id', $companyId, $companyId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':customer_id', $customerId, $customerId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            if ($hasBestellungDurch) {
                $stmt->bindValue(':bestellung_durch', $bestellungDurch ?: null, $bestellungDurch ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }
            $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
            
                if ($stmt->execute()) {
                $statusChanged = ($status != $order['status']);
                // Lager: „Bestellung angekommen“ bis erster Einlagerungsvorgang
                if ($statusChanged) {
                    if (in_array($status, ['Im Lager', 'Angekommen'], true)) {
                        consumableApplyPendingStockinFlagFromOrderNotizen($pdo, $notizen ?? '', true, $beschreibung ?? null);
                    } elseif (in_array($order['status'] ?? '', ['Im Lager', 'Angekommen'], true)) {
                        consumableApplyPendingStockinFlagFromOrderNotizen($pdo, $order['notizen'] ?? '', false, $order['beschreibung'] ?? null);
                    }
                }
                // Benutzername für Benachrichtigung abrufen
                try {
                    $userStmt = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = ?");
                    $userStmt->execute([$userId]);
                    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                    $userName = ($user && $user['vorname'] && $user['nachname']) 
                        ? trim($user['vorname'] . ' ' . $user['nachname']) 
                        : 'Ein Benutzer';
                } catch (PDOException $e) {
                    $userName = 'Ein Benutzer';
                }
                // Betreff für Meldungen: Beschreibung der Bestellung, sonst Bestellnummer
                $orderBetreff = (isset($beschreibung) && trim((string)$beschreibung) !== '') ? trim($beschreibung) : $bestellnummer;

                // Statusänderung in History speichern und Benachrichtigung senden
                $notizenChanged = ($notizen !== ($order['notizen'] ?? null));
                $otherFieldsChanged = false;
                
                // Prüfen ob andere Felder als Status oder Notizen geändert wurden
                if ($bestellnummer !== $order['bestellnummer'] || 
                    $beschreibung !== $order['beschreibung'] ||
                    $companyId != $order['company_id'] ||
                    $customerId != $order['customer_id'] ||
                    ($hasGarantiePutCol && (int)($order['garantie'] ?? 0) !== (int)$garantie)) {
                    $otherFieldsChanged = true;
                }
                
                if ($statusChanged) {
                    $historySql = "INSERT INTO order_status_history (order_id, status, geaendert_von) 
                                  VALUES (:order_id, :status, :geaendert_von)";
                    $historyStmt = $pdo->prepare($historySql);
                    $historyStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                    $historyStmt->bindValue(':status', $status, PDO::PARAM_STR);
                    $historyStmt->bindValue(':geaendert_von', $userId, PDO::PARAM_INT);
                    $historyStmt->execute();
                    
                    // Bei Status "Im Lager", "Beim Kunden" oder "Angekommen": Ticket-Status auf "Neu" setzen
                    if (in_array($status, ['Im Lager', 'Beim Kunden', 'Angekommen'], true) && isset($order['ticket_id']) && $order['ticket_id']) {
                        $ticketId = (int) $order['ticket_id'];
                        try {
                            $ticketUpdateStmt = $pdo->prepare("UPDATE tickets SET status = 'Neu', geaendert_datum = NOW() WHERE id = ?");
                            $ticketUpdateStmt->execute([$ticketId]);
                        } catch (PDOException $e) {
                            error_log("Fehler beim Aktualisieren des Ticket-Status auf 'Neu': " . $e->getMessage());
                        }
                    }
                    // Bei Status "Bestellt", "Unterwegs", "Im Lager", "Beim Kunden" oder "Angekommen": Nachricht ins Ticket schreiben (mit Tracking-Link falls vorhanden)
                    if (in_array($status, ['Bestellt', 'Unterwegs', 'Im Lager', 'Beim Kunden', 'Angekommen'], true) && isset($order['ticket_id']) && $order['ticket_id']) {
                        $ticketId = (int) $order['ticket_id'];
                        $betreff = $orderBetreff;
                        $statusNachricht = [
                            'Bestellt' => 'Die Bestellung' . ($betreff !== '' ? ' "' . $betreff . '"' : '') . ' wurde bestellt.',
                            'Unterwegs' => 'Die Bestellung' . ($betreff !== '' ? ' "' . $betreff . '"' : '') . ' ist unterwegs.',
                            'Im Lager' => 'Die Bestellung' . ($betreff !== '' ? ' "' . $betreff . '"' : '') . ' ist im Lager.',
                            'Beim Kunden' => 'Die Bestellung' . ($betreff !== '' ? ' "' . $betreff . '"' : '') . ' ist beim Kunden.',
                            'Angekommen' => 'Die Bestellung' . ($betreff !== '' ? ' "' . $betreff . '"' : '') . ' ist angekommen.',
                        ];
                        $kommentar = $statusNachricht[$status] ?? '';
                        if ($kommentar !== '') {
                            // Tracking-URL als Plain-Text anhängen – wird in der Ticket-Ansicht von formatMessageWithLinks automatisch als klickbarer Link gerendert
                            if (!empty($trackingLink)) {
                                $kommentar .= ' Sendung verfolgen: ' . $trackingLink;
                                if (!empty($trackingNummer)) {
                                    $kommentar .= ' (Tracking-Nr. ' . $trackingNummer . ')';
                                }
                            }
                            try {
                                $commentStmt = $pdo->prepare("
                                    INSERT INTO ticket_comments (ticket_id, user_id, kommentar, nachrichtentyp, ist_intern, erstellt_datum)
                                    VALUES (?, ?, ?, 'nachricht', 0, NOW())
                                ");
                                $commentStmt->execute([$ticketId, $userId, $kommentar]);
                                $ticketGeaendertStmt = $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?");
                                $ticketGeaendertStmt->execute([$ticketId]);
                            } catch (PDOException $e) {
                                error_log("Fehler beim Schreiben der Bestellstatus-Nachricht ins Ticket: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // Relevanz basierend auf Status bestimmen
                    $relevanz = 'hoch';
                    if ($status === 'Neu' || $status === 'Bestellt') {
                        $relevanz = 'normal';
                    }
                    
                    // Benachrichtigung für Statusänderung
                    createNotificationsForAction(
                        $userId,
                        $order['company_id'],
                        'order_status_changed',
                        'Bestellung Status geändert: ' . $orderBetreff,
                        'Der Status der Bestellung "' . $orderBetreff . '" wurde von ' . $userName . ' von "' . $order['status'] . '" auf "' . $status . '" geändert.',
                        $relevanz,
                        'orders/detail.php?id=' . $orderId,
                        'order',
                        $orderId
                    );
                }
                
                // Benachrichtigung für Notizen-Update (nur wenn nur Notizen geändert wurden)
                if ($notizenChanged && !$statusChanged && !$otherFieldsChanged) {
                    createNotificationsForAction(
                        $userId,
                        $order['company_id'],
                        'order_notizen_updated',
                        'Bestellung Notizen aktualisiert: ' . $orderBetreff,
                        'Die Notizen der Bestellung "' . $orderBetreff . '" wurden von ' . $userName . ' aktualisiert.',
                        'niedrig',
                        'orders/detail.php?id=' . $orderId,
                        'order',
                        $orderId
                    );
                }
                
                // Benachrichtigung für allgemeine Updates (wenn andere Felder geändert wurden, aber nicht Status)
                if ($otherFieldsChanged && !$statusChanged && !$notizenChanged) {
                    createNotificationsForAction(
                        $userId,
                        $order['company_id'],
                        'order_updated',
                        'Bestellung aktualisiert: ' . $orderBetreff,
                        'Die Bestellung "' . $orderBetreff . '" wurde von ' . $userName . ' aktualisiert.',
                        'normal',
                        'orders/detail.php?id=' . $orderId,
                        'order',
                        $orderId
                    );
                }
                
                // Wenn mehrere Änderungen (aber nicht nur Status oder nur Notizen)
                if ($otherFieldsChanged && $statusChanged) {
                    // Statusänderung wurde bereits benachrichtigt, Update zusätzlich
                    createNotificationsForAction(
                        $userId,
                        $order['company_id'],
                        'order_updated',
                        'Bestellung aktualisiert: ' . $orderBetreff,
                        'Die Bestellung "' . $orderBetreff . '" wurde von ' . $userName . ' aktualisiert.',
                        'normal',
                        'orders/detail.php?id=' . $orderId,
                        'order',
                        $orderId
                    );
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Bestellung erfolgreich aktualisiert'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Aktualisieren der Bestellung']);
            }
            break;
            
        case 'DELETE':
            // Bestellung löschen
            if (!isset($_GET['id']) || !$_GET['id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            $orderId = (int)$_GET['id'];
            
            // Bestellung laden
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Bestellung nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen (nur Admin und Techniker können löschen)
            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung zum Löschen']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            if ($stmt->execute([$orderId])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Bestellung erfolgreich gelöscht'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen der Bestellung']);
            }
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
