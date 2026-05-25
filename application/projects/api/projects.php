<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/service_log_helper.php';
require_once dirname(__DIR__, 2) . '/todos/helper/encryption.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
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

// Nur Admin und Techniker haben Zugriff auf Projekte
if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

$projectStatuses = ['Neu', 'In Planung', 'In Bearbeitung', 'Wartend', 'Abgeschlossen', 'Archiviert'];

function canAccessProject($pdo, $projectId, $userId, $userRole, $userCompanyId) {
    $stmt = $pdo->prepare("SELECT company_id FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$projectId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) return false;
    if ($userRole === 'Admin' || $userRole === 'Techniker') return true;
    return ($p['company_id'] && (int)$p['company_id'] === (int)$userCompanyId);
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] === 'search_tickets') {
                $q = isset($_GET['q']) ? trim($_GET['q']) : '';
                $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
                $excludeProjectId = isset($_GET['exclude_project_id']) ? (int)$_GET['exclude_project_id'] : 0;
                $where = ["t.status != 'Geschlossen'"];
                $params = [];
                if ($q !== '') {
                    $where[] = "(t.ticket_nummer LIKE :q OR t.titel LIKE :q2 OR t.beschreibung LIKE :q3)";
                    $params[':q'] = '%' . $q . '%';
                    $params[':q2'] = '%' . $q . '%';
                    $params[':q3'] = '%' . $q . '%';
                }
                if ($companyId) {
                    $where[] = "t.company_id = :company_id";
                    $params[':company_id'] = $companyId;
                }
                if ($excludeProjectId) {
                    $where[] = "NOT EXISTS (SELECT 1 FROM project_tickets pt WHERE pt.ticket_id = t.id AND pt.project_id = :exclude_project)";
                    $params[':exclude_project'] = $excludeProjectId;
                }
                $wc = implode(' AND ', $where);
                $sql = "SELECT t.id, t.ticket_nummer, t.titel, t.status, c.name as company_name
                    FROM tickets t LEFT JOIN companies c ON t.company_id = c.id
                    WHERE $wc ORDER BY t.erstellt_datum DESC LIMIT 20";
                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                $stmt->execute();
                echo json_encode(['success' => true, 'tickets' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                exit;
            }
            if (isset($_GET['action']) && $_GET['action'] === 'search_orders') {
                $q = isset($_GET['q']) ? trim($_GET['q']) : '';
                $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
                $excludeProjectId = isset($_GET['exclude_project_id']) ? (int)$_GET['exclude_project_id'] : 0;
                $hasProjectId = false;
                try { $c = $pdo->query("SHOW COLUMNS FROM orders LIKE 'project_id'"); $hasProjectId = $c && $c->rowCount() > 0; } catch (PDOException $e) {}
                $where = ["1=1"];
                $params = [];
                if ($q !== '') {
                    $where[] = "(o.bestellnummer LIKE :q OR o.beschreibung LIKE :q2)";
                    $params[':q'] = '%' . $q . '%';
                    $params[':q2'] = '%' . $q . '%';
                }
                if ($companyId) { $where[] = "o.company_id = :company_id"; $params[':company_id'] = $companyId; }
                if ($excludeProjectId && $hasProjectId) {
                    $where[] = "(o.project_id IS NULL OR o.project_id != :exclude_project)";
                    $params[':exclude_project'] = $excludeProjectId;
                }
                $wc = implode(' AND ', $where);
                $sql = "SELECT o.id, o.bestellnummer, o.beschreibung, o.status, o.erstellt_datum, c.name as company_name
                    FROM orders o LEFT JOIN companies c ON o.company_id = c.id WHERE $wc ORDER BY o.erstellt_datum DESC LIMIT 20";
                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                $stmt->execute();
                echo json_encode(['success' => true, 'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                exit;
            }

            if (isset($_GET['action']) && $_GET['action'] === 'assignable_users') {
                $stmt = $pdo->query("
                    SELECT u.id, u.vorname, u.nachname, u.email, u.rolle, c.name as company_name
                    FROM users u LEFT JOIN companies c ON u.company_id = c.id
                    WHERE u.status = 'aktiv' AND u.rolle IN ('Admin','Techniker')
                    ORDER BY u.nachname, u.vorname
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'users' => $users]);
                exit;
            }

            if (isset($_GET['id'])) {
                $projectId = (int)$_GET['id'];
                $hasDeletedAtCol = false;
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'deleted_at'");
                    $hasDeletedAtCol = $col && $col->rowCount() > 0;
                } catch (PDOException $e) {}
                $deletedFilter = $hasDeletedAtCol ? ' AND p.deleted_at IS NULL' : '';
                $hasProjektleiter = false;
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'projektleiter_user_id'");
                    $hasProjektleiter = $col && $col->rowCount() > 0;
                } catch (PDOException $e) {}
                $projektleiterJoin = $hasProjektleiter ? ' LEFT JOIN users pl ON p.projektleiter_user_id = pl.id' : '';
                $projektleiterSel = $hasProjektleiter ? ', pl.vorname as projektleiter_vorname, pl.nachname as projektleiter_nachname, pl.email as projektleiter_email, pl.telefonnummer as projektleiter_telefon' : '';
                $stmt = $pdo->prepare("
                    SELECT p.*,
                        c.name as company_name, c.adresse as company_adresse, c.plz as company_plz, c.ort as company_ort,
                        c.email as company_email, c.telefonnummer as company_telefon,
                        c.ansprechpartner_user_id as company_ansprechpartner_user_id,
                        c.ansprechpartner_manuell_name as company_ansprechpartner_manuell_name,
                        c.ansprechpartner_manuell_email as company_ansprechpartner_manuell_email,
                        c.ansprechpartner_manuell_telefon as company_ansprechpartner_manuell_telefon,
                        u_cap.vorname as company_ansprechpartner_vorname, u_cap.nachname as company_ansprechpartner_nachname,
                        u_cap.email as company_ansprechpartner_email, u_cap.telefonnummer as company_ansprechpartner_telefon,
                        cust.name as customer_name, cust.adresse as customer_adresse, cust.plz as customer_plz, cust.ort as customer_ort,
                        cust.email as customer_email, cust.telefon as customer_telefon,
                        cust.ansprechpartner_user_id as customer_ansprechpartner_user_id,
                        cust.ansprechpartner_manuell_name as customer_ansprechpartner_manuell_name,
                        cust.ansprechpartner_manuell_email as customer_ansprechpartner_manuell_email,
                        cust.ansprechpartner_manuell_telefon as customer_ansprechpartner_manuell_telefon,
                        u_cust_ap.vorname as customer_ansprechpartner_vorname, u_cust_ap.nachname as customer_ansprechpartner_nachname,
                        u_cust_ap.email as customer_ansprechpartner_email, u_cust_ap.telefonnummer as customer_ansprechpartner_telefon,
                        u.vorname as erstellt_von_vorname, u.nachname as erstellt_von_nachname,
                        beauftr.vorname as beauftragter_vorname, beauftr.nachname as beauftragter_nachname,
                        beauftr.email as beauftragter_email, beauftr.telefonnummer as beauftragter_telefon,
                        ap.vorname as ansprechpartner_vorname, ap.nachname as ansprechpartner_nachname,
                        ap.email as ansprechpartner_email, ap.telefonnummer as ansprechpartner_telefon
                        $projektleiterSel
                    FROM projects p
                    LEFT JOIN companies c ON p.company_id = c.id
                    LEFT JOIN users u_cap ON c.ansprechpartner_user_id = u_cap.id
                    LEFT JOIN customers cust ON p.customer_id = cust.id
                    LEFT JOIN users u_cust_ap ON cust.ansprechpartner_user_id = u_cust_ap.id
                    LEFT JOIN users u ON p.erstellt_von = u.id
                    LEFT JOIN users beauftr ON p.beauftragter_user_id = beauftr.id
                    LEFT JOIN users ap ON p.ansprechpartner_user_id = ap.id
                    $projektleiterJoin
                    WHERE p.id = ?$deletedFilter
                ");
                $stmt->execute([$projectId]);
                $project = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$project || !canAccessProject($pdo, $projectId, $userId, $userRole, $userCompanyId)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                    exit;
                }
                $companyFields = ['company_name','company_adresse','company_plz','company_ort','company_email','company_telefon','company_ansprechpartner_manuell_name','company_ansprechpartner_manuell_email','company_ansprechpartner_manuell_telefon','company_ansprechpartner_vorname','company_ansprechpartner_nachname','company_ansprechpartner_email','company_ansprechpartner_telefon'];
                $customerFields = ['customer_name','customer_adresse','customer_plz','customer_ort','customer_email','customer_telefon','customer_ansprechpartner_manuell_name','customer_ansprechpartner_manuell_email','customer_ansprechpartner_manuell_telefon','customer_ansprechpartner_vorname','customer_ansprechpartner_nachname','customer_ansprechpartner_email','customer_ansprechpartner_telefon'];
                foreach (array_merge($companyFields, $customerFields) as $f) {
                    if (isset($project[$f])) $project[$f] = decrypt_from_db($project[$f]);
                }
                // Notizen
                $noteStmt = $pdo->prepare("
                    SELECT pn.*, u.vorname, u.nachname
                    FROM project_notes pn
                    LEFT JOIN users u ON pn.erstellt_von = u.id
                    WHERE pn.project_id = ?
                    ORDER BY pn.erstellt_datum DESC
                ");
                $noteStmt->execute([$projectId]);
                $project['notes'] = $noteStmt->fetchAll(PDO::FETCH_ASSOC);
                // Verknüpfte Tickets
                $ticketStmt = $pdo->prepare("
                    SELECT t.id, t.ticket_nummer, t.titel, t.status
                    FROM project_tickets pt
                    JOIN tickets t ON pt.ticket_id = t.id
                    WHERE pt.project_id = ?
                ");
                $ticketStmt->execute([$projectId]);
                $project['tickets'] = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
                // Bestellungen (orders mit project_id)
                $hasProjectId = false;
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM orders LIKE 'project_id'");
                    $hasProjectId = $col && $col->rowCount() > 0;
                } catch (PDOException $e) {}
                $project['orders'] = [];
                if ($hasProjectId) {
                    $orderStmt = $pdo->prepare("
                        SELECT o.id, o.bestellnummer, o.beschreibung, o.status, o.erstellt_datum
                        FROM orders o WHERE o.project_id = ?
                        ORDER BY o.erstellt_datum DESC
                    ");
                    $orderStmt->execute([$projectId]);
                    $project['orders'] = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                // Todos (project_id) – Titel/Beschreibung entschlüsseln
                $hasTodoProjectId = false;
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM todos LIKE 'project_id'");
                    $hasTodoProjectId = $col && $col->rowCount() > 0;
                } catch (PDOException $e) {}
                $project['todos'] = [];
                if ($hasTodoProjectId) {
                    $todoStmt = $pdo->prepare("
                        SELECT t.id, t.titel, t.beschreibung, t.status, t.prioritaet, t.faellig_am
                        FROM todos t WHERE t.project_id = ?
                        ORDER BY t.faellig_am ASC, t.id ASC
                    ");
                    $todoStmt->execute([$projectId]);
                    $project['todos'] = $todoStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($project['todos'] as &$t) {
                        if (function_exists('decrypt_todo_row')) decrypt_todo_row($t);
                    }
                    unset($t);
                }
                // Beteiligte Mitarbeiter (project_observers)
                $project['beteiligte'] = [];
                try {
                    $obsStmt = $pdo->prepare("
                        SELECT u.id, u.vorname, u.nachname, u.email, u.telefonnummer
                        FROM project_observers po
                        JOIN users u ON po.user_id = u.id
                        WHERE po.project_id = ?
                        ORDER BY u.nachname, u.vorname
                    ");
                    $obsStmt->execute([$projectId]);
                    $project['beteiligte'] = $obsStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {}
                // Projekt-Dokumente (project_attachments)
                $project['attachments'] = [];
                try {
                    $attStmt = $pdo->prepare("
                        SELECT pa.id, pa.dateiname, pa.dateipfad, pa.dateigroesse, pa.mime_type, pa.erstellt_datum,
                               u.vorname as erstellt_von_vorname, u.nachname as erstellt_von_nachname
                        FROM project_attachments pa
                        LEFT JOIN users u ON pa.erstellt_von = u.id
                        WHERE pa.project_id = ?
                        ORDER BY pa.erstellt_datum DESC
                    ");
                    $attStmt->execute([$projectId]);
                    $project['attachments'] = $attStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {}
                echo json_encode(['success' => true, 'project' => $project]);
                exit;
            }

            // Liste
            $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : null;
            $search = isset($_GET['search']) ? trim($_GET['search']) : null;
            $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

            $where = ["1=1"];
            $params = [];
            $hasDeletedAt = false;
            try {
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'deleted_at'");
                $hasDeletedAt = $col && $col->rowCount() > 0;
            } catch (PDOException $e) {}
            if ($hasDeletedAt) {
                $where[] = "p.deleted_at IS NULL";
            }

            if ($companyFilter) {
                $where[] = "p.company_id = :company_filter";
                $params[':company_filter'] = $companyFilter;
            }
            if ($statusFilter) {
                $where[] = "p.status = :status_filter";
                $params[':status_filter'] = $statusFilter;
            }
            if ($search !== null && $search !== '') {
                $where[] = "(p.bezeichnung LIKE :search OR p.beschreibung LIKE :search2)";
                $params[':search'] = '%' . $search . '%';
                $params[':search2'] = '%' . $search . '%';
            }

            $whereClause = implode(' AND ', $where);
            $orderCol = isset($_GET['sort']) ? preg_replace('/[^a-z_]/', '', $_GET['sort']) : 'erstellt_datum';
            $orderDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
            $allowedSort = ['bezeichnung', 'status', 'erstellt_datum', 'geaendert_datum', 'company_name', 'beauftragter_nachname'];
            if (!in_array($orderCol, $allowedSort)) $orderCol = 'erstellt_datum';

            $sortMap = [
                'company_name' => 'c.name',
                'beauftragter_nachname' => 'beauftr.nachname'
            ];
            $orderBy = $sortMap[$orderCol] ?? ('p.' . $orderCol);

            $statusOrder = "FIELD(p.status, 'Neu', 'In Planung', 'In Bearbeitung', 'Wartend', 'Abgeschlossen', 'Archiviert')";
            $sql = "
                SELECT p.id, p.project_nummer, p.bezeichnung, p.beschreibung, p.status, p.sort_order, p.company_id, p.customer_id,
                       p.beauftragter_user_id, p.erstellt_von, p.erstellt_datum, p.geaendert_datum,
                       c.name as company_name,
                       cust.name as customer_name,
                       beauftr.vorname as beauftragter_vorname,
                       beauftr.nachname as beauftragter_nachname
                FROM projects p
                LEFT JOIN companies c ON p.company_id = c.id
                LEFT JOIN customers cust ON p.customer_id = cust.id
                LEFT JOIN users beauftr ON p.beauftragter_user_id = beauftr.id
                WHERE $whereClause
                ORDER BY $statusOrder ASC, p.sort_order ASC, p.id ASC
            ";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($projects as &$p) {
                if (array_key_exists('company_name', $p)) {
                    $p['company_name'] = decrypt_from_db($p['company_name']);
                }
                if (array_key_exists('customer_name', $p)) {
                    $p['customer_name'] = decrypt_from_db($p['customer_name']);
                }
            }
            unset($p);
            echo json_encode(['success' => true, 'projects' => $projects]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $action = isset($data['action']) ? trim($data['action']) : '';

            if ($action === 'link_ticket') {
                $projectId = isset($data['project_id']) ? (int)$data['project_id'] : 0;
                $ticketId = isset($data['ticket_id']) ? (int)$data['ticket_id'] : 0;
                if (!$projectId || !$ticketId || !canAccessProject($pdo, $projectId, $userId, $userRole, $userCompanyId)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']);
                    exit;
                }
                try {
                    // Ein Ticket kann nur mit einem Projekt verknüpft sein: bestehende Verknüpfung entfernen
                    $pdo->prepare("DELETE FROM project_tickets WHERE ticket_id = ?")->execute([$ticketId]);
                    $pdo->prepare("INSERT INTO project_tickets (project_id, ticket_id) VALUES (?, ?)")->execute([$projectId, $ticketId]);
                    echo json_encode(['success' => true]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => 'Verknüpfen fehlgeschlagen']);
                }
                exit;
            }
            if ($action === 'link_order') {
                $projectId = isset($data['project_id']) ? (int)$data['project_id'] : 0;
                $orderId = isset($data['order_id']) ? (int)$data['order_id'] : 0;
                if (!$projectId || !$orderId || !canAccessProject($pdo, $projectId, $userId, $userRole, $userCompanyId)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']);
                    exit;
                }
                $col = $pdo->query("SHOW COLUMNS FROM orders LIKE 'project_id'");
                if (!$col || $col->rowCount() === 0) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'project_id nicht vorhanden']);
                    exit;
                }
                $pdo->prepare("UPDATE orders SET project_id = ? WHERE id = ?")->execute([$projectId, $orderId]);
                echo json_encode(['success' => true]);
                exit;
            }

            $bezeichnung = isset($data['bezeichnung']) ? trim($data['bezeichnung']) : '';
            if ($bezeichnung === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bezeichnung erforderlich']);
                exit;
            }
            $status = isset($data['status']) ? trim($data['status']) : 'Neu';
            if (!in_array($status, $projectStatuses)) $status = 'Neu';
            $beschreibung = isset($data['beschreibung']) ? trim($data['beschreibung']) : null;
            $companyId = isset($data['company_id']) && $data['company_id'] ? (int)$data['company_id'] : null;
            $customerId = isset($data['customer_id']) && $data['customer_id'] ? (int)$data['customer_id'] : null;
            $beauftragterId = isset($data['beauftragter_user_id']) && $data['beauftragter_user_id'] ? (int)$data['beauftragter_user_id'] : null;
            $ansprechpartnerId = isset($data['ansprechpartner_user_id']) && $data['ansprechpartner_user_id'] ? (int)$data['ansprechpartner_user_id'] : null;
            $apName = isset($data['ansprechpartner_manuell_name']) ? trim($data['ansprechpartner_manuell_name']) : null;
            $apEmail = isset($data['ansprechpartner_manuell_email']) ? trim($data['ansprechpartner_manuell_email']) : null;
            $apTelefon = isset($data['ansprechpartner_manuell_telefon']) ? trim($data['ansprechpartner_manuell_telefon']) : null;

            // Projektnummer generieren (wie Ticket-Nummer: PRJ-YYYYMMDD-XXXX)
            $projectNummer = 'PRJ-' . date('Ymd') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $checkStmt = $pdo->prepare("SELECT id FROM projects WHERE project_nummer = ?");
            $checkStmt->execute([$projectNummer]);
            if ($checkStmt->fetch()) {
                $projectNummer = 'PRJ-' . date('Ymd') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }

            $hasProjectNummer = false;
            try {
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'project_nummer'");
                $hasProjectNummer = $col && $col->rowCount() > 0;
            } catch (PDOException $e) {}
            if ($hasProjectNummer) {
                $stmt = $pdo->prepare("
                    INSERT INTO projects (project_nummer, bezeichnung, beschreibung, status, company_id, customer_id,
                        beauftragter_user_id, ansprechpartner_user_id, ansprechpartner_manuell_name,
                        ansprechpartner_manuell_email, ansprechpartner_manuell_telefon, erstellt_von)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $projectNummer, $bezeichnung, $beschreibung ?: null, $status, $companyId, $customerId,
                    $beauftragterId, $ansprechpartnerId, $apName, $apEmail, $apTelefon, $userId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO projects (bezeichnung, beschreibung, status, company_id, customer_id,
                        beauftragter_user_id, ansprechpartner_user_id, ansprechpartner_manuell_name,
                        ansprechpartner_manuell_email, ansprechpartner_manuell_telefon, erstellt_von)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $bezeichnung, $beschreibung ?: null, $status, $companyId, $customerId,
                    $beauftragterId, $ansprechpartnerId, $apName, $apEmail, $apTelefon, $userId
                ]);
            }
            $newId = (int)$pdo->lastInsertId();
            if (function_exists('service_log')) service_log($pdo, $userId, 'sonstiges', $newId, 'created', 'project', null, (string)$newId, 'Projekt angelegt');
            echo json_encode(['success' => true, 'id' => $newId]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $action = isset($data['action']) ? trim($data['action']) : '';

            if ($action === 'abrechnen') {
                $projectId = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
                if (!$projectId || !canAccessProject($pdo, $projectId, $userId, $userRole, $userCompanyId)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                    exit;
                }
                $cur = $pdo->prepare("SELECT status FROM projects WHERE id = ? LIMIT 1");
                $cur->execute([$projectId]);
                $row = $cur->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['status'] !== 'Abgeschlossen') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nur abgeschlossene Projekte können abgerechnet werden.']);
                    exit;
                }
                $pdo->prepare("UPDATE projects SET status = 'Archiviert', geaendert_datum = NOW() WHERE id = ?")->execute([$projectId]);
                if (function_exists('service_log')) service_log($pdo, $userId, 'sonstiges', $projectId, 'updated', 'project', null, (string)$projectId, 'Projekt abgerechnet (Archiv)');
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'reorder') {
                $status = isset($data['status']) ? trim($data['status']) : '';
                $projectIds = isset($data['project_ids']) && is_array($data['project_ids']) ? array_map('intval', $data['project_ids']) : [];
                if (!in_array($status, $projectStatuses) || empty($projectIds)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']);
                    exit;
                }
                foreach ($projectIds as $idx => $pid) {
                    if (!$pid || !canAccessProject($pdo, $pid, $userId, $userRole, $userCompanyId)) continue;
                    $stmt = $pdo->prepare("UPDATE projects SET sort_order = ?, geaendert_datum = NOW() WHERE id = ? AND status = ?");
                    $stmt->execute([$idx, $pid, $status]);
                }
                echo json_encode(['success' => true]);
                break;
            }

            $projectId = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
            if (!$projectId || !canAccessProject($pdo, $projectId, $userId, $userRole, $userCompanyId)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }
            $bezeichnung = isset($data['bezeichnung']) ? trim($data['bezeichnung']) : '';
            if ($bezeichnung === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bezeichnung erforderlich']);
                exit;
            }
            $status = isset($data['status']) ? trim($data['status']) : null;
            if ($status !== null && !in_array($status, $projectStatuses)) $status = null;
            $beschreibung = isset($data['beschreibung']) ? trim($data['beschreibung']) : null;
            $companyId = isset($data['company_id']) ? ($data['company_id'] ? (int)$data['company_id'] : null) : null;
            $customerId = isset($data['customer_id']) ? ($data['customer_id'] ? (int)$data['customer_id'] : null) : null;
            $beauftragterId = isset($data['beauftragter_user_id']) ? ($data['beauftragter_user_id'] ? (int)$data['beauftragter_user_id'] : null) : null;
            $ansprechpartnerId = isset($data['ansprechpartner_user_id']) ? ($data['ansprechpartner_user_id'] ? (int)$data['ansprechpartner_user_id'] : null) : null;
            $apName = isset($data['ansprechpartner_manuell_name']) ? trim($data['ansprechpartner_manuell_name']) : null;
            $apEmail = isset($data['ansprechpartner_manuell_email']) ? trim($data['ansprechpartner_manuell_email']) : null;
            $apTelefon = isset($data['ansprechpartner_manuell_telefon']) ? trim($data['ansprechpartner_manuell_telefon']) : null;
            $startDatum = isset($data['start_datum']) ? (trim($data['start_datum']) ?: null) : null;
            $endDatum = isset($data['end_datum']) ? (trim($data['end_datum']) ?: null) : null;
            $geplantesEndDatum = isset($data['geplantes_end_datum']) ? (trim($data['geplantes_end_datum']) ?: null) : null;
            $budget = isset($data['budget']) ? (is_numeric($data['budget']) ? $data['budget'] : null) : null;
            $projektleiterId = isset($data['projektleiter_user_id']) ? ($data['projektleiter_user_id'] ? (int)$data['projektleiter_user_id'] : null) : null;

            $updates = ["bezeichnung = ?", "beschreibung = ?", "company_id = ?", "customer_id = ?",
                "beauftragter_user_id = ?", "ansprechpartner_user_id = ?", "ansprechpartner_manuell_name = ?",
                "ansprechpartner_manuell_email = ?", "ansprechpartner_manuell_telefon = ?"];
            $vals = [$bezeichnung, $beschreibung ?: null, $companyId, $customerId, $beauftragterId, $ansprechpartnerId, $apName, $apEmail, $apTelefon];
            try {
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'start_datum'");
                if ($col && $col->rowCount() > 0) {
                    $updates[] = "start_datum = ?"; $vals[] = $startDatum;
                }
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'end_datum'");
                if ($col && $col->rowCount() > 0) {
                    $updates[] = "end_datum = ?"; $vals[] = $endDatum;
                }
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'geplantes_end_datum'");
                if ($col && $col->rowCount() > 0) {
                    $updates[] = "geplantes_end_datum = ?"; $vals[] = $geplantesEndDatum;
                }
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'budget'");
                if ($col && $col->rowCount() > 0) {
                    $updates[] = "budget = ?"; $vals[] = $budget;
                }
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'projektleiter_user_id'");
                if ($col && $col->rowCount() > 0) {
                    $updates[] = "projektleiter_user_id = ?"; $vals[] = $projektleiterId;
                }
            } catch (PDOException $e) {}
            if ($status !== null) {
                $updates[] = "status = ?";
                $vals[] = $status;
                $updates[] = "sort_order = 999999";
            }
            $vals[] = $projectId;
            $stmt = $pdo->prepare("UPDATE projects SET " . implode(", ", $updates) . " WHERE id = ?");
            $stmt->execute($vals);
            if (function_exists('service_log')) service_log($pdo, $userId, 'sonstiges', $projectId, 'updated', 'project', null, (string)$projectId, null);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $unlinkTicket = isset($_GET['unlink_ticket']) ? (int)$_GET['unlink_ticket'] : 0;
            $unlinkOrder = isset($_GET['unlink_order']) ? (int)$_GET['unlink_order'] : 0;

            if ($unlinkTicket && $projectId) {
                if (!canAccessProject($pdo, $projectId, $userId, $userRole, $userCompanyId)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                $pdo->prepare("DELETE FROM project_tickets WHERE project_id = ? AND ticket_id = ?")->execute([$projectId, $unlinkTicket]);
                echo json_encode(['success' => true]);
                exit;
            }
            if ($unlinkOrder) {
                $col = $pdo->query("SHOW COLUMNS FROM orders LIKE 'project_id'");
                if ($col && $col->rowCount() > 0) {
                    $pdo->prepare("UPDATE orders SET project_id = NULL WHERE id = ?")->execute([$unlinkOrder]);
                }
                echo json_encode(['success' => true]);
                exit;
            }

            if (!$projectId || !canAccessProject($pdo, $projectId, $userId, $userRole, $userCompanyId)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }
            $hasDeletedAt = false;
            try {
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'deleted_at'");
                $hasDeletedAt = $col && $col->rowCount() > 0;
            } catch (PDOException $e) {}
            if ($hasDeletedAt) {
                $pdo->prepare("UPDATE projects SET deleted_at = NOW(), geaendert_datum = NOW() WHERE id = ?")->execute([$projectId]);
                if (function_exists('service_log')) service_log($pdo, $userId, 'sonstiges', $projectId, 'updated', 'project', null, null, 'Projekt soft-gelöscht');
            } else {
                $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$projectId]);
                if (function_exists('service_log')) service_log($pdo, $userId, 'sonstiges', $projectId, 'deleted', 'project', (string)$projectId, null, null);
            }
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
