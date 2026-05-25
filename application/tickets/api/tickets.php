<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__, 2) . '/assets/service_log_helper.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/assets/ticket_search_plaintext_helpers.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Sicherheitsnetz: Falls ein fataler PHP-Fehler auftritt, trotzdem JSON liefern
register_shutdown_function(function () {
    $lastError = error_get_last();
    if (!$lastError) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($lastError['type'], $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $buffer = '';
    if (function_exists('ob_get_contents')) {
        $buffer = (string)ob_get_contents();
    }
    if (trim($buffer) === '') {
        echo json_encode(['success' => false, 'error' => 'Interner Serverfehler']);
    }
});

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

// User-bezogene angeheftete Tickets laden
$pinnedTicketIds = [];
try {
    $pinStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = 'service_pinned_tickets' LIMIT 1");
    $pinStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $pinStmt->execute();
    $pinRow = $pinStmt->fetch(PDO::FETCH_ASSOC);
    if ($pinRow && isset($pinRow['setting_value'])) {
        $decodedPins = json_decode((string)$pinRow['setting_value'], true);
        if (is_array($decodedPins)) {
            $pinnedTicketIds = array_values(array_unique(array_filter(array_map('intval', $decodedPins), fn($v) => $v > 0)));
        }
    }
} catch (PDOException $e) {
    $pinnedTicketIds = [];
}

// Optionale End-Spalten für Geplant/Fällig (Migration 062)
$hasTicketEndColumns = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'geplant_datum_ende'");
    $hasTicketEndColumns = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {
    // Spalten fehlen
}
$selTicketEnd = $hasTicketEndColumns ? ', t.geplant_datum_ende, t.faellig_datum_ende' : '';

// Optionale Tabelle: roter Punkt / Hervorhebung ohne echte Ungelesen-Markierung (Migration 116)
$unreadReminderSql = ', 0 as unread_reminder';
try {
    $turChk = $pdo->query("SHOW TABLES LIKE 'ticket_unread_reminder'");
    if ($turChk && $turChk->rowCount() > 0) {
        $unreadReminderSql = ', COALESCE((SELECT 1 FROM ticket_unread_reminder tur WHERE tur.ticket_id = t.id AND tur.user_id = ' . (int)$userId . ' LIMIT 1), 0) as unread_reminder';
    }
} catch (Exception $e) {
    $unreadReminderSql = ', 0 as unread_reminder';
}

try {
    switch ($method) {
        case 'GET':
            // Benutzerlisten für Frontend (z.B. Beobachter/Bearbeiter)
            if (isset($_GET['action']) && $_GET['action'] === 'company_users') {
                if ($userRole === 'Kunde') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
                if (!$companyId) {
                    echo json_encode(['success' => true, 'users' => []]);
                    exit;
                }
                
                // Nicht Admin/Techniker dürfen nur die eigene Firma abfragen
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    if (!$userCompanyId || (int)$companyId !== (int)$userCompanyId) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                        exit;
                    }
                }
                
                $stmt = $pdo->prepare("
                    SELECT u.id, u.vorname, u.nachname, u.email, u.rolle
                    FROM users u
                    WHERE u.company_id = :company_id AND u.status = 'aktiv'
                    ORDER BY u.nachname, u.vorname
                ");
                $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                service_log($pdo, $userId, 'sonstiges', 0, 'viewed', null, null, null, 'Tickets API: company_users (company_id=' . $companyId . ')');
                echo json_encode(['success' => true, 'users' => $users]);
                exit;
            }
            
            if (isset($_GET['action']) && $_GET['action'] === 'assignees') {
                // Nur Techniker und Firmen-Admins dürfen Bearbeiter festlegen -> nur diese dürfen Liste anfragen
                if ($userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    SELECT u.id, u.vorname, u.nachname, u.email, u.rolle
                    FROM users u
                    WHERE u.status = 'aktiv' AND u.rolle IN ('Admin', 'Techniker')
                    ORDER BY u.nachname, u.vorname
                ");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                service_log($pdo, $userId, 'sonstiges', 0, 'viewed', null, null, null, 'Tickets API: assignees');
                echo json_encode(['success' => true, 'users' => $users]);
                exit;
            }

            if (isset($_GET['action']) && $_GET['action'] === 'history') {
                $historyTicketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
                if (!$historyTicketId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'id fehlt']);
                    exit;
                }

                $checkStmt = $pdo->prepare("SELECT erstellt_von, company_id FROM tickets WHERE id = ? LIMIT 1");
                $checkStmt->execute([$historyTicketId]);
                $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (!$ticket) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                    exit;
                }

                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($ticket['erstellt_von'] == $userId) {
                    $hasPermission = true;
                } else {
                    try {
                        $obsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                        $obsStmt->bindValue(':ticket_id', $historyTicketId, PDO::PARAM_INT);
                        $obsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                        $obsStmt->execute();
                        if ($obsStmt->fetchColumn()) {
                            $hasPermission = true;
                        }
                    } catch (PDOException $e) {
                        // Ignorieren
                    }
                }

                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }

                $historyStmt = $pdo->prepare("
                    SELECT 
                        l.id,
                        l.kategorie,
                        l.entity_id,
                        l.user_id,
                        l.action,
                        l.field_name,
                        l.old_value,
                        l.new_value,
                        l.beschreibung,
                        l.erstellt_datum,
                        CONCAT(COALESCE(u.vorname, ''), ' ', COALESCE(u.nachname, '')) AS user_name
                    FROM logs l
                    LEFT JOIN users u ON l.user_id = u.id
                    WHERE l.kategorie = 'ticket'
                      AND l.entity_id = :ticket_id
                      AND COALESCE(l.action, '') <> 'viewed'
                    ORDER BY l.erstellt_datum DESC, l.id DESC
                    LIMIT 500
                ");
                $historyStmt->bindValue(':ticket_id', $historyTicketId, PDO::PARAM_INT);
                $historyStmt->execute();
                $logs = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($logs as &$logRow) {
                    $userName = trim((string)($logRow['user_name'] ?? ''));
                    $logRow['user_name'] = $userName !== '' ? $userName : 'Unbekannt';
                }
                unset($logRow);

                service_log($pdo, $userId, 'ticket', $historyTicketId, 'viewed', null, null, null, 'Tickets API: History abgerufen');
                echo json_encode(['success' => true, 'logs' => $logs]);
                exit;
            }

            // Einzelnes Ticket abrufen
            if (isset($_GET['id'])) {
                $ticketId = (int)$_GET['id'];
                
                $sql = "
                    SELECT 
                        t.id,
                        t.ticket_nummer,
                        t.titel,
                        t.beschreibung,
                        t.status,
                        t.prioritaet,
                        t.company_id,
                        t.customer_id,
                        t.device_id,
                        t.erstellt_von,
                        t.zugewiesen_an,
                        t.erstellt_datum,
                        t.geaendert_datum,
                        t.faellig_datum,
                        t.geplant_datum{$selTicketEnd},
                        t.abgeschlossen_datum,
                        t.abgerechnet,
                        t.bearbeitungszeit_minuten,
                        c.name as company_name,
                        c.logo as company_logo,
                        c.adresse as company_adresse,
                        c.plz as company_plz,
                        c.ort as company_ort,
                        c.email as company_email,
                        c.telefonnummer as company_telefon,
                        c.hat_wartungsvertrag as company_hat_wartungsvertrag,
                        c.ansprechpartner_user_id as company_ansprechpartner_user_id,
                        c.ansprechpartner_manuell_name as company_ansprechpartner_manuell_name,
                        c.ansprechpartner_manuell_email as company_ansprechpartner_manuell_email,
                        c.ansprechpartner_manuell_telefon as company_ansprechpartner_manuell_telefon,
                        u_company_ansprechpartner.vorname as company_ansprechpartner_vorname,
                        u_company_ansprechpartner.nachname as company_ansprechpartner_nachname,
                        u_company_ansprechpartner.email as company_ansprechpartner_email,
                        u_company_ansprechpartner.telefonnummer as company_ansprechpartner_telefon,
                        cust.name as customer_name,
                        cust.logo as customer_logo,
                        cust.email as customer_email,
                        cust.telefon as customer_telefon,
                        cust.adresse as customer_adresse,
                        cust.plz as customer_plz,
                        cust.ort as customer_ort,
                        cust.ansprechpartner_user_id as customer_ansprechpartner_user_id,
                        cust.ansprechpartner_manuell_name as customer_ansprechpartner_manuell_name,
                        cust.ansprechpartner_manuell_email as customer_ansprechpartner_manuell_email,
                        cust.ansprechpartner_manuell_telefon as customer_ansprechpartner_manuell_telefon,
                        u_ansprechpartner.vorname as customer_ansprechpartner_vorname,
                        u_ansprechpartner.nachname as customer_ansprechpartner_nachname,
                        u_ansprechpartner.email as customer_ansprechpartner_email,
                        u_ansprechpartner.telefonnummer as customer_ansprechpartner_telefon,
                        d.name as device_name,
                        d.typ as device_typ,
                        d.hersteller as device_hersteller,
                        d.modell as device_modell,
                        d.seriennummer as device_seriennummer,
                        d.mac_adresse as device_mac_adresse,
                        d.ip_adresse as device_ip_adresse,
                    d.betriebssystem as device_betriebssystem,
                    d.beschreibung as device_beschreibung,
                    u_erstellt.vorname as ersteller_vorname,
                    u_erstellt.nachname as ersteller_nachname,
                    u_erstellt.email as ersteller_email,
                    u_erstellt.logopfad as ersteller_logopfad,
                    u_zugewiesen.vorname as zugewiesen_vorname,
                    u_zugewiesen.nachname as zugewiesen_nachname,
                    u_zugewiesen.logopfad as zugewiesen_logopfad,
                    GROUP_CONCAT(DISTINCT CONCAT(u_observer.vorname, ' ', u_observer.nachname) SEPARATOR ', ') as observer_names,
                    GROUP_CONCAT(DISTINCT u_observer.id SEPARATOR ',') as observer_ids,
                    (SELECT COUNT(*) 
                     FROM ticket_comments tc 
                     LEFT JOIN ticket_comment_reads tcr ON tc.id = tcr.comment_id AND tcr.user_id = " . (int)$userId . "
                     WHERE tc.ticket_id = t.id 
                     AND tcr.id IS NULL 
                     AND tc.user_id != " . (int)$userId . "
                     AND tc.ist_intern = 0) as unread_comments_count" . $unreadReminderSql . "
                FROM tickets t
                LEFT JOIN companies c ON t.company_id = c.id
                LEFT JOIN users u_company_ansprechpartner ON c.ansprechpartner_user_id = u_company_ansprechpartner.id
                LEFT JOIN customers cust ON t.customer_id = cust.id
                LEFT JOIN users u_ansprechpartner ON cust.ansprechpartner_user_id = u_ansprechpartner.id
                LEFT JOIN devices d ON t.device_id = d.id
                LEFT JOIN users u_erstellt ON t.erstellt_von = u_erstellt.id
                LEFT JOIN users u_zugewiesen ON t.zugewiesen_an = u_zugewiesen.id
                LEFT JOIN ticket_observers to_obs ON t.id = to_obs.ticket_id
                LEFT JOIN users u_observer ON to_obs.user_id = u_observer.id
                WHERE t.id = :ticket_id
                AND t.titel NOT LIKE '[Gelöscht] %'
                GROUP BY t.id
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
                $stmt->execute();
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$ticket) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($ticket['erstellt_von'] == $userId) {
                    $hasPermission = true;
                } else {
                    // Beobachter dürfen Ticket sehen
                    try {
                        $obsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                        $obsStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
                        $obsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                        $obsStmt->execute();
                        if ($obsStmt->fetchColumn()) {
                            $hasPermission = true;
                        }
                    } catch (PDOException $e) {
                        // Ignorieren, Standard bleibt false
                    }
                }
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                service_log($pdo, $userId, 'ticket', $ticketId, 'viewed', null, null, null, 'Tickets API: Ticket #' . $ticketId . ' abgerufen');
                $companyFields = ['company_name','company_adresse','company_plz','company_ort','company_email','company_telefon','company_ansprechpartner_manuell_name','company_ansprechpartner_manuell_email','company_ansprechpartner_manuell_telefon','company_ansprechpartner_email','company_ansprechpartner_telefon'];
                $customerFields = ['customer_name','customer_email','customer_telefon','customer_adresse','customer_plz','customer_ort','customer_ansprechpartner_manuell_name','customer_ansprechpartner_manuell_email','customer_ansprechpartner_manuell_telefon','customer_ansprechpartner_email','customer_ansprechpartner_telefon'];
                foreach (array_merge($companyFields, $customerFields) as $cf) {
                    if (isset($ticket[$cf])) $ticket[$cf] = decrypt_from_db($ticket[$cf]);
                }
                $ticket['is_pinned'] = in_array((int)$ticketId, $pinnedTicketIds, true) ? 1 : 0;
                // view.php Kontextmenü: nur „Kunde/Gerät hinzufügen“, wenn Auswahl wie in customers/devices API nicht leer
                $ticket['company_customers_count'] = null;
                $ticket['customer_devices_count'] = 0;
                $tidComp = !empty($ticket['company_id']) ? (int)$ticket['company_id'] : 0;
                $tidCust = !empty($ticket['customer_id']) ? (int)$ticket['customer_id'] : 0;
                if ($tidComp > 0 && ($userRole === 'Admin' || $userRole === 'Techniker')) {
                    try {
                        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE company_id = ?');
                        $cntStmt->execute([$tidComp]);
                        $ticket['company_customers_count'] = (int)$cntStmt->fetchColumn();
                    } catch (PDOException $e) {
                        $ticket['company_customers_count'] = 0;
                    }
                }
                if ($tidCust > 0) {
                    try {
                        if ($userRole === 'Admin' || $userRole === 'Techniker') {
                            $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM devices d WHERE d.customer_id = ?');
                            $cntStmt->execute([$tidCust]);
                            $ticket['customer_devices_count'] = (int)$cntStmt->fetchColumn();
                        } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                            $cntStmt = $pdo->prepare('
                                SELECT COUNT(*) FROM devices d
                                WHERE d.customer_id = ?
                                AND (d.company_id = ? OR d.customer_id IN (SELECT id FROM customers WHERE company_id = ? OR company_id IS NULL))
                            ');
                            $uid = (int)$userCompanyId;
                            $cntStmt->execute([$tidCust, $uid, $uid]);
                            $ticket['customer_devices_count'] = (int)$cntStmt->fetchColumn();
                        }
                    } catch (PDOException $e) {
                        $ticket['customer_devices_count'] = 0;
                    }
                }
                $ticket['projects'] = [];
                try {
                    $hasProjectNummer = false;
                    try {
                        $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'project_nummer'");
                        $hasProjectNummer = $col && $col->rowCount() > 0;
                    } catch (PDOException $e) {}
                    
                    $projectFields = 'p.id, p.bezeichnung, p.status, p.beauftragter_user_id';
                    if ($hasProjectNummer) {
                        $projectFields .= ', p.project_nummer';
                    }
                    
                    $projStmt = $pdo->prepare("
                        SELECT $projectFields,
                               u_beauftragter.vorname as beauftragter_vorname,
                               u_beauftragter.nachname as beauftragter_nachname
                        FROM project_tickets pt
                        JOIN projects p ON pt.project_id = p.id
                        LEFT JOIN users u_beauftragter ON p.beauftragter_user_id = u_beauftragter.id
                        WHERE pt.ticket_id = ?
                    ");
                    $projStmt->execute([$ticketId]);
                    $ticket['projects'] = $projStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // project_tickets Tabelle ggf. nicht vorhanden
                }
                echo json_encode([
                    'success' => true,
                    'ticket' => $ticket
                ]);
                exit;
            }
            
            // Liste für „Zusammenführen“-Modal: Quell-Ticket, Berechtigung, Ziel-Firma
            $forMergeList = isset($_GET['for_merge']) && (string)$_GET['for_merge'] === '1';
            $mergeSourceTicketId = isset($_GET['merge_source_ticket_id']) ? (int)$_GET['merge_source_ticket_id'] : 0;
            $mergeSourceCompanyId = null;
            $mergeAllCompanies = false;
            $mergeIncludeClosed = false;
            if ($forMergeList) {
                if ($mergeSourceTicketId <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'merge_source_ticket_id fehlt oder ist ungültig']);
                    exit;
                }
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                $mergeSrcStmt = $pdo->prepare("SELECT id, company_id FROM tickets WHERE id = ? AND titel NOT LIKE '[Gelöscht] %' LIMIT 1");
                $mergeSrcStmt->execute([$mergeSourceTicketId]);
                $mergeSrcRow = $mergeSrcStmt->fetch(PDO::FETCH_ASSOC);
                if (!$mergeSrcRow) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Quell-Ticket nicht gefunden']);
                    exit;
                }
                $mergeSourceCompanyId = $mergeSrcRow['company_id'];
                $mergeAllCompanies = isset($_GET['merge_all_companies']) && (string)$_GET['merge_all_companies'] === '1';
                $mergeIncludeClosed = isset($_GET['merge_include_closed']) && (string)$_GET['merge_include_closed'] === '1';
            }
            
            // Tickets abrufen mit rollenbasierter Filterung
            $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $deviceFilter = isset($_GET['device_id']) ? (int)$_GET['device_id'] : null;
            if ($forMergeList) {
                $companyFilter = null;
            }
            
            // SQL-Query basierend auf Rolle aufbauen
            $whereConditions = [];
            $params = [];
            
            // Nur Tickets mit aktiver Firma anzeigen (ohne Firma oder Firma status = aktiv)
            $whereConditions[] = "(t.company_id IS NULL OR c.status = 'aktiv')";
            // Soft-delete: ausgeblendete Tickets nicht mehr listen
            $whereConditions[] = "t.titel NOT LIKE '[Gelöscht] %'";

            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // Admin und Techniker sehen alle Tickets
                if ($companyFilter) {
                    $whereConditions[] = "t.company_id = :company_filter";
                    $params[':company_filter'] = $companyFilter;
                }
                if ($deviceFilter) {
                    $whereConditions[] = "t.device_id = :device_filter";
                    $params[':device_filter'] = $deviceFilter;
                }
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                // Firmen-Admin sieht: eigene Tickets (Auftraggeber/Beauftragter/Beobachter) + alle Tickets seiner Kunden
                $whereConditions[] = "(t.erstellt_von = :user_id_erstellt OR t.zugewiesen_an = :user_id_zugewiesen OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_user_id) OR EXISTS (SELECT 1 FROM customers cust2 WHERE cust2.id = t.customer_id AND cust2.company_id = :user_company_id))";
                $params[':user_id_erstellt'] = $userId;
                $params[':user_id_zugewiesen'] = $userId;
                $params[':observer_user_id'] = $userId;
                $params[':user_company_id'] = $userCompanyId;
            } else {
                // Alle anderen Rollen: nur Tickets, bei denen sie Auftraggeber, Beauftragter oder Beobachter sind
                $whereConditions[] = "(t.erstellt_von = :user_id_erstellt OR t.zugewiesen_an = :user_id_zugewiesen OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_user_id))";
                $params[':user_id_erstellt'] = $userId;
                $params[':user_id_zugewiesen'] = $userId;
                $params[':observer_user_id'] = $userId;
            }
            
            // device_id Filter für alle Rollen
            if ($deviceFilter) {
                $whereConditions[] = "t.device_id = :device_filter";
                $params[':device_filter'] = $deviceFilter;
            }

            // Zusammenführen: standardmäßig gleiche Firma, ohne Geschlossen/Archiv; optional per GET erweiterbar
            if ($forMergeList) {
                if (!$mergeAllCompanies) {
                    if ($mergeSourceCompanyId !== null && $mergeSourceCompanyId !== '' && (int)$mergeSourceCompanyId > 0) {
                        $whereConditions[] = 't.company_id = :merge_src_company';
                        $params[':merge_src_company'] = (int)$mergeSourceCompanyId;
                    } else {
                        $whereConditions[] = 't.company_id IS NULL';
                    }
                }
                if (!$mergeIncludeClosed) {
                    $whereConditions[] = "t.status NOT IN ('Geschlossen', 'Archiv')";
                }
                $whereConditions[] = 't.id != :merge_exclude_self';
                $params[':merge_exclude_self'] = $mergeSourceTicketId;
            }

            // Text-Suche (Suchbereich aus User-Einstellung)
            $searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
            $searchScopeRaw = isset($_GET['search_scope']) ? (string) $_GET['search_scope'] : '';
            $allowedScopeKeys = ['ticket_nummer', 'titel', 'beschreibung', 'firma', 'kunde', 'anforderer', 'bearbeiter', 'beobachter', 'geraet', 'geraetestandort', 'nachrichten', 'anhange', 'status'];
            $searchScope = $searchScopeRaw !== '' ? array_intersect(array_map('trim', explode(',', $searchScopeRaw)), $allowedScopeKeys) : $allowedScopeKeys;
            if ($searchTerm !== '' && !empty($searchScope)) {
                @set_time_limit(180);
                @ini_set('max_execution_time', '180');
                $searchTermEscapedLike = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm);
                $searchLike = '%' . $searchTermEscapedLike . '%';
                $searchParts = [];
                $searchParamIndex = 0;
                $nextParam = function () use (&$searchParamIndex) {
                    return ':search_like_' . (++$searchParamIndex);
                };
                if (in_array('ticket_nummer', $searchScope, true)) {
                    $p = $nextParam();
                    $searchParts[] = "t.ticket_nummer LIKE $p";
                    $params[$p] = $searchLike;
                }
                if (in_array('titel', $searchScope, true)) {
                    $p = $nextParam();
                    $searchParts[] = "t.titel LIKE $p";
                    $params[$p] = $searchLike;
                }
                if (in_array('beschreibung', $searchScope, true)) {
                    $descIds = ticket_search_ticket_ids_from_tickets_beschreibung_like($pdo, $searchLike, 3500);
                    if (!empty($descIds)) {
                        $searchParts[] = 't.id IN (' . implode(',', $descIds) . ')';
                    } else {
                        $searchParts[] = '1=0';
                    }
                }
                if (in_array('firma', $searchScope, true)) {
                    $companyIds = ticket_search_company_ids_matching_plaintext($pdo, $searchTerm);
                    if (!empty($companyIds)) {
                        $searchParts[] = 't.company_id IN (' . implode(',', array_map('intval', $companyIds)) . ')';
                    } else {
                        $searchParts[] = '1=0';
                    }
                }
                if (in_array('kunde', $searchScope, true)) {
                    $customerIds = ticket_search_customer_ids_matching_plaintext($pdo, $searchTerm);
                    if (!empty($customerIds)) {
                        $searchParts[] = 't.customer_id IN (' . implode(',', array_map('intval', $customerIds)) . ')';
                    } else {
                        $searchParts[] = '1=0';
                    }
                }
                if (in_array('anforderer', $searchScope, true)) {
                    $p1 = $nextParam(); $p2 = $nextParam(); $p3 = $nextParam();
                    $searchParts[] = "(u_erstellt.vorname LIKE $p1 OR u_erstellt.nachname LIKE $p2 OR CONCAT(IFNULL(u_erstellt.vorname,''), ' ', IFNULL(u_erstellt.nachname,'')) LIKE $p3)";
                    $params[$p1] = $params[$p2] = $params[$p3] = $searchLike;
                }
                if (in_array('bearbeiter', $searchScope, true)) {
                    $p1 = $nextParam(); $p2 = $nextParam(); $p3 = $nextParam();
                    $searchParts[] = "(u_zugewiesen.vorname LIKE $p1 OR u_zugewiesen.nachname LIKE $p2 OR CONCAT(IFNULL(u_zugewiesen.vorname,''), ' ', IFNULL(u_zugewiesen.nachname,'')) LIKE $p3)";
                    $params[$p1] = $params[$p2] = $params[$p3] = $searchLike;
                }
                if (in_array('beobachter', $searchScope, true)) {
                    $p1 = $nextParam(); $p2 = $nextParam(); $p3 = $nextParam();
                    $searchParts[] = "EXISTS (SELECT 1 FROM ticket_observers to_b JOIN users u_b ON to_b.user_id = u_b.id WHERE to_b.ticket_id = t.id AND (u_b.vorname LIKE $p1 OR u_b.nachname LIKE $p2 OR CONCAT(IFNULL(u_b.vorname,''), ' ', IFNULL(u_b.nachname,'')) LIKE $p3))";
                    $params[$p1] = $params[$p2] = $params[$p3] = $searchLike;
                }
                if (in_array('geraet', $searchScope, true)) {
                    $p1 = $nextParam(); $p2 = $nextParam(); $p3 = $nextParam(); $p4 = $nextParam();
                    $p5 = $nextParam(); $p6 = $nextParam(); $p7 = $nextParam(); $p8 = $nextParam();
                    $searchParts[] = "(d.name LIKE $p1 OR d.typ LIKE $p2 OR d.hersteller LIKE $p3 OR d.modell LIKE $p4 OR d.seriennummer LIKE $p5 OR d.mac_adresse LIKE $p6 OR d.ip_adresse LIKE $p7 OR d.betriebssystem LIKE $p8)";
                    $params[$p1] = $params[$p2] = $params[$p3] = $params[$p4] = $params[$p5] = $params[$p6] = $params[$p7] = $params[$p8] = $searchLike;
                }
                if (in_array('geraetestandort', $searchScope, true)) {
                    $devLocIds = ticket_search_ticket_ids_from_devices_standort_like($pdo, $searchLike, 3500);
                    if (!empty($devLocIds)) {
                        $searchParts[] = 't.id IN (' . implode(',', $devLocIds) . ')';
                    } else {
                        $searchParts[] = '1=0';
                    }
                }
                if (in_array('nachrichten', $searchScope, true)) {
                    $msgIds = ticket_search_ticket_ids_from_comments_like($pdo, $searchLike, 4000);
                    if (!empty($msgIds)) {
                        $searchParts[] = 't.id IN (' . implode(',', $msgIds) . ')';
                    } else {
                        $searchParts[] = '1=0';
                    }
                }
                if (in_array('anhange', $searchScope, true)) {
                    $attIds = ticket_search_ticket_ids_from_attachments_like($pdo, $searchLike, 3500);
                    if (!empty($attIds)) {
                        $searchParts[] = 't.id IN (' . implode(',', $attIds) . ')';
                    } else {
                        $searchParts[] = '1=0';
                    }
                }
                if (in_array('status', $searchScope, true)) {
                    $p = $nextParam();
                    $searchParts[] = "t.status LIKE $p";
                    $params[$p] = $searchLike;
                }
                if (!empty($searchParts)) {
                    $whereConditions[] = "(" . implode(" OR ", $searchParts) . ")";
                }
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $sql = "
                SELECT 
                    t.id,
                    t.ticket_nummer,
                    t.titel,
                    t.beschreibung,
                    t.status,
                    t.prioritaet,
                    t.company_id,
                    t.customer_id,
                    t.device_id,
                    t.erstellt_von,
                    t.zugewiesen_an,
                    t.erstellt_datum,
                    t.geaendert_datum,
                    t.faellig_datum,
                    t.geplant_datum{$selTicketEnd},
                    t.abgeschlossen_datum,
                    t.abgerechnet,
                    t.bearbeitungszeit_minuten,
                    c.name as company_name,
                    c.logo as company_logo,
                    c.adresse as company_adresse,
                    c.plz as company_plz,
                    c.ort as company_ort,
                    c.email as company_email,
                    c.telefonnummer as company_telefon,
                    c.hat_wartungsvertrag as company_hat_wartungsvertrag,
                    c.ansprechpartner_user_id as company_ansprechpartner_user_id,
                    c.ansprechpartner_manuell_name as company_ansprechpartner_manuell_name,
                    u_company_ansprechpartner.vorname as company_ansprechpartner_vorname,
                    u_company_ansprechpartner.nachname as company_ansprechpartner_nachname,
                    u_company_ansprechpartner.email as company_ansprechpartner_email,
                    u_company_ansprechpartner.telefonnummer as company_ansprechpartner_telefon,
                    cust.name as customer_name,
                    cust.logo as customer_logo,
                    cust.email as customer_email,
                    cust.telefon as customer_telefon,
                    cust.adresse as customer_adresse,
                    cust.plz as customer_plz,
                    cust.ort as customer_ort,
                    cust.ansprechpartner_user_id as customer_ansprechpartner_user_id,
                    cust.ansprechpartner_manuell_name as customer_ansprechpartner_manuell_name,
                    u_ansprechpartner.vorname as customer_ansprechpartner_vorname,
                    u_ansprechpartner.nachname as customer_ansprechpartner_nachname,
                    u_ansprechpartner.email as customer_ansprechpartner_email,
                    u_ansprechpartner.telefonnummer as customer_ansprechpartner_telefon,
                    d.name as device_name,
                    d.typ as device_typ,
                    d.hersteller as device_hersteller,
                    d.modell as device_modell,
                    d.seriennummer as device_seriennummer,
                    d.mac_adresse as device_mac_adresse,
                    d.ip_adresse as device_ip_adresse,
                    d.betriebssystem as device_betriebssystem,
                    d.beschreibung as device_beschreibung,
                    u_erstellt.vorname as ersteller_vorname,
                    u_erstellt.nachname as ersteller_nachname,
                    u_erstellt.email as ersteller_email,
                    u_erstellt.logopfad as ersteller_logopfad,
                    u_zugewiesen.vorname as zugewiesen_vorname,
                    u_zugewiesen.nachname as zugewiesen_nachname,
                    u_zugewiesen.logopfad as zugewiesen_logopfad,
                    GROUP_CONCAT(DISTINCT CONCAT(u_observer.vorname, ' ', u_observer.nachname) SEPARATOR ', ') as observer_names,
                    GROUP_CONCAT(DISTINCT u_observer.id SEPARATOR ',') as observer_ids,
                    (SELECT COUNT(*) 
                     FROM ticket_comments tc 
                     LEFT JOIN ticket_comment_reads tcr ON tc.id = tcr.comment_id AND tcr.user_id = " . (int)$userId . "
                     WHERE tc.ticket_id = t.id 
                     AND tcr.id IS NULL 
                     AND tc.user_id != " . (int)$userId . "
                     AND tc.ist_intern = 0) as unread_comments_count" . $unreadReminderSql . "
                FROM tickets t
                LEFT JOIN companies c ON t.company_id = c.id
                LEFT JOIN users u_company_ansprechpartner ON c.ansprechpartner_user_id = u_company_ansprechpartner.id
                LEFT JOIN customers cust ON t.customer_id = cust.id
                LEFT JOIN users u_ansprechpartner ON cust.ansprechpartner_user_id = u_ansprechpartner.id
                LEFT JOIN devices d ON t.device_id = d.id
                LEFT JOIN users u_erstellt ON t.erstellt_von = u_erstellt.id
                LEFT JOIN users u_zugewiesen ON t.zugewiesen_an = u_zugewiesen.id
                LEFT JOIN ticket_observers to_obs ON t.id = to_obs.ticket_id
                LEFT JOIN users u_observer ON to_obs.user_id = u_observer.id
                $whereClause
                GROUP BY t.id
                ORDER BY COALESCE(t.geaendert_datum, t.erstellt_datum) DESC
            ";
            if ($searchTerm !== '') {
                $sql .= ' LIMIT 3501';
            }
            
            $tickets = [];
            $searchResultLimited = false;
            try {
                $stmt = $pdo->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmt->execute();
                $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log('Tickets API Liste (Suche): ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Liste konnte nicht geladen werden. Bitte Suchbegriff kürzen oder Filter setzen.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($searchTerm !== '' && count($tickets) > 3500) {
                $searchResultLimited = true;
                $tickets = array_slice($tickets, 0, 3500);
            }
            
            // Nächsten Termin für jedes Ticket: immer einen anzeigen (nächster zukünftiger, sonst letzter vergangener)
            $ticketIds = array_map(function($t) { return (int)($t['id'] ?? 0); }, $tickets);
            $nextAppointments = [];
            $appointmentCounts = [];
            if (!empty($ticketIds)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
                    // Alle Termine pro Ticket, chronologisch
                    $appointmentStmt = $pdo->prepare("
                        SELECT 
                            ticket_id,
                            titel,
                            typ,
                            start_datum,
                            ende_datum
                        FROM ticket_appointments
                        WHERE ticket_id IN ($placeholders)
                        ORDER BY ticket_id, start_datum ASC
                    ");
                    $appointmentStmt->execute($ticketIds);
                    $appointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);
                    $now = date('Y-m-d H:i:s');
                    $hadFuture = [];
                    foreach ($appointments as $appointment) {
                        $tid = (int)$appointment['ticket_id'];
                        $end = $appointment['ende_datum'] ?? $appointment['start_datum'];
                        $isFuture = ($end >= $now);
                        if ($isFuture) {
                            $hadFuture[$tid] = true;
                            if (!isset($appointmentCounts[$tid])) $appointmentCounts[$tid] = 0;
                            $appointmentCounts[$tid]++;
                        }
                        // Nächster anzuzeigender Termin: erster zukünftiger, oder (wenn keiner) der letzte vergangene
                        if ($isFuture && !isset($nextAppointments[$tid])) {
                            $nextAppointments[$tid] = $appointment;
                        } else if (!$isFuture && empty($hadFuture[$tid])) {
                            $nextAppointments[$tid] = $appointment;
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Fehler beim Abrufen der Termine: " . $e->getMessage());
                }
            }
            
            // Projekte für alle Tickets laden
            $ticketProjects = [];
            if (!empty($ticketIds)) {
                try {
                    $hasProjectNummer = false;
                    try {
                        $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'project_nummer'");
                        $hasProjectNummer = $col && $col->rowCount() > 0;
                    } catch (PDOException $e) {}
                    
                    $projectFields = 'p.id, p.bezeichnung, p.status, p.beauftragter_user_id';
                    if ($hasProjectNummer) {
                        $projectFields .= ', p.project_nummer';
                    }
                    
                    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
                    $projStmt = $pdo->prepare("
                        SELECT pt.ticket_id, $projectFields,
                               u_beauftragter.vorname as beauftragter_vorname,
                               u_beauftragter.nachname as beauftragter_nachname
                        FROM project_tickets pt
                        JOIN projects p ON pt.project_id = p.id
                        LEFT JOIN users u_beauftragter ON p.beauftragter_user_id = u_beauftragter.id
                        WHERE pt.ticket_id IN ($placeholders)
                    ");
                    $projStmt->execute($ticketIds);
                    $projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($projects as $project) {
                        $tid = (int)$project['ticket_id'];
                        if (!isset($ticketProjects[$tid])) {
                            $ticketProjects[$tid] = [];
                        }
                        $ticketProjects[$tid][] = [
                            'id' => (int)$project['id'],
                            'bezeichnung' => $project['bezeichnung'],
                            'status' => $project['status'],
                            'beauftragter_user_id' => $project['beauftragter_user_id'],
                            'beauftragter_vorname' => $project['beauftragter_vorname'],
                            'beauftragter_nachname' => $project['beauftragter_nachname']
                        ];
                        if ($hasProjectNummer && isset($project['project_nummer'])) {
                            $ticketProjects[$tid][count($ticketProjects[$tid]) - 1]['project_nummer'] = $project['project_nummer'];
                        }
                    }
                } catch (PDOException $e) {
                    // project_tickets Tabelle ggf. nicht vorhanden
                }
            }
            
            foreach ($tickets as &$ticketRow) {
                $ticketRow['is_pinned'] = in_array((int)($ticketRow['id'] ?? 0), $pinnedTicketIds, true) ? 1 : 0;
                $companyFields = ['company_name','company_adresse','company_plz','company_ort','company_email','company_telefon','company_ansprechpartner_manuell_name','company_ansprechpartner_manuell_email','company_ansprechpartner_manuell_telefon','company_ansprechpartner_email','company_ansprechpartner_telefon'];
                $customerFields = ['customer_name','customer_email','customer_telefon','customer_adresse','customer_plz','customer_ort','customer_ansprechpartner_manuell_name','customer_ansprechpartner_manuell_email','customer_ansprechpartner_manuell_telefon','customer_ansprechpartner_email','customer_ansprechpartner_telefon'];
                foreach (array_merge($companyFields, $customerFields) as $cf) {
                    if (isset($ticketRow[$cf])) $ticketRow[$cf] = decrypt_from_db($ticketRow[$cf]);
                }
                $deviceTextFields = ['device_name', 'device_hersteller', 'device_modell', 'device_beschreibung', 'device_seriennummer', 'device_mac_adresse', 'device_ip_adresse', 'device_betriebssystem'];
                foreach ($deviceTextFields as $df) {
                    if (isset($ticketRow[$df])) {
                        $ticketRow[$df] = decrypt_from_db($ticketRow[$df]);
                    }
                }
                
                $ticketId = (int)($ticketRow['id'] ?? 0);
                if (isset($nextAppointments[$ticketId])) {
                    $ticketRow['naechster_termin'] = $nextAppointments[$ticketId];
                    $ticketRow['anzahl_zukuenftige_termine'] = isset($appointmentCounts[$ticketId]) ? (int)$appointmentCounts[$ticketId] : 0;
                } else {
                    $ticketRow['naechster_termin'] = null;
                    $ticketRow['anzahl_zukuenftige_termine'] = 0;
                }
                
                // Projekte hinzufügen
                $ticketRow['projects'] = isset($ticketProjects[$ticketId]) ? $ticketProjects[$ticketId] : [];
            }
            unset($ticketRow);
            service_log($pdo, $userId, 'sonstiges', 0, 'viewed', null, null, null, 'Tickets API: Liste (' . count($tickets) . ' Tickets)');
            $listPayload = [
                'success' => true,
                'tickets' => $tickets,
            ];
            if ($searchTerm !== '' && $searchResultLimited) {
                $listPayload['search_result_limited'] = true;
                $listPayload['search_result_max'] = 3500;
            }
            $jsonFlags = JSON_UNESCAPED_UNICODE;
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
            }
            $jsonOut = json_encode($listPayload, $jsonFlags);
            if ($jsonOut === false) {
                $jsonOut = json_encode([
                    'success' => false,
                    'error' => 'Antwort konnte nicht erstellt werden (Datenkodierung).',
                ], JSON_UNESCAPED_UNICODE);
            }
            echo $jsonOut;
            break;
            
        case 'POST':
            // Neues Ticket erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Action: Projektverknüpfung lösen
            if (isset($data['action']) && $data['action'] === 'unlink_project') {
                $ticketId = isset($data['ticket_id']) ? (int)$data['ticket_id'] : 0;
                if (!$ticketId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ticket-ID erforderlich']);
                    exit;
                }
                
                // Berechtigung prüfen: User muss Zugriff auf das Ticket haben
                try {
                    $checkStmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ? LIMIT 1");
                    $checkStmt->execute([$ticketId]);
                    if (!$checkStmt->fetch()) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                        exit;
                    }
                    
                    // Prüfen ob User Zugriff auf Ticket hat
                    $accessStmt = $pdo->prepare("
                        SELECT id FROM tickets 
                        WHERE id = ? AND (
                            erstellt_von = ? OR 
                            zugewiesen_an = ? OR 
                            ? IN ('Admin', 'Techniker') OR
                            EXISTS (SELECT 1 FROM ticket_observers WHERE ticket_id = tickets.id AND user_id = ?)
                        )
                        LIMIT 1
                    ");
                    $accessStmt->execute([$ticketId, $userId, $userId, $userRole, $userId]);
                    if (!$accessStmt->fetch()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                        exit;
                    }
                    
                    // Projektverknüpfung löschen
                    try {
                        $pdo->prepare("DELETE FROM project_tickets WHERE ticket_id = ?")->execute([$ticketId]);
                        echo json_encode(['success' => true]);
                    } catch (PDOException $e) {
                        // project_tickets Tabelle kann fehlen
                        echo json_encode(['success' => true]);
                    }
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
                }
                exit;
            }
            
            if (!isset($data['titel']) || !isset($data['beschreibung'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Titel und Beschreibung sind erforderlich']);
                exit;
            }
            
            $titel = trim($data['titel']);
            $beschreibung = trim($data['beschreibung']);
            
            // Betreff-Länge prüfen (max. 50 Zeichen)
            if (mb_strlen($titel, 'UTF-8') > 50) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Der Betreff darf maximal 50 Zeichen lang sein. Bitte kürzen Sie den Betreff.']);
                exit;
            }
            $prioritaet = $data['prioritaet'] ?? 'normal';
            $companyId = isset($data['company_id']) ? (int)$data['company_id'] : $userCompanyId;
            $customerId = isset($data['customer_id']) ? (int)$data['customer_id'] : null;
            $deviceId = isset($data['device_id']) ? (int)$data['device_id'] : null;
            $zugewiesenAn = isset($data['zugewiesen_an']) ? (int)$data['zugewiesen_an'] : null;
            
            // Anforderer setzen (nur für Admins/Techniker, sonst aktueller User)
            $erstelltVon = $userId;
            if (($userRole === 'Admin' || $userRole === 'Techniker') && isset($data['anforderer_id']) && $data['anforderer_id']) {
                $erstelltVon = (int)$data['anforderer_id'];
            }
            
            // Rollenbasierte Validierung / Firma erzwingen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                // Alle anderen Rollen: Firma kommt immer aus users.company_id
                if (!$userCompanyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Firma zugeordnet']);
                    exit;
                }
                $companyId = (int)$userCompanyId;
            }

            // Bearbeiter nur für Admin/Techniker
            $canSetAssignee = ($userRole === 'Admin' || $userRole === 'Techniker');
            if (!$canSetAssignee) {
                $zugewiesenAn = null;
            } elseif ($zugewiesenAn) {
                $assigneeStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND status = 'aktiv' AND rolle IN ('Admin','Techniker') LIMIT 1");
                $assigneeStmt->bindValue(':id', (int)$zugewiesenAn, PDO::PARAM_INT);
                $assigneeStmt->execute();
                if (!$assigneeStmt->fetch(PDO::FETCH_ASSOC)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültiger Bearbeiter']);
                    exit;
                }
            }
            
            $duplicateFromTicketId = isset($data['duplicate_from_ticket_id']) ? (int)$data['duplicate_from_ticket_id'] : 0;
            if ($duplicateFromTicketId > 0) {
                $dupChk = $pdo->prepare("SELECT id, erstellt_von, company_id, customer_id, titel FROM tickets WHERE id = ? LIMIT 1");
                $dupChk->execute([$duplicateFromTicketId]);
                $dupSrcTicket = $dupChk->fetch(PDO::FETCH_ASSOC);
                if (!$dupSrcTicket || (isset($dupSrcTicket['titel']) && strpos((string)$dupSrcTicket['titel'], '[Gelöscht] ') === 0)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Quell-Ticket zum Duplizieren nicht gefunden']);
                    exit;
                }
                $dupPerm = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $dupPerm = true;
                } elseif ($userRole === 'Firmen-Admin' && $dupSrcTicket['company_id'] == $userCompanyId) {
                    $dupPerm = true;
                } elseif ((int)$dupSrcTicket['erstellt_von'] === (int)$userId) {
                    $dupPerm = true;
                } else {
                    try {
                        $dobs = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = ? AND user_id = ? LIMIT 1");
                        $dobs->execute([$duplicateFromTicketId, $userId]);
                        $dupPerm = (bool)$dobs->fetchColumn();
                    } catch (PDOException $e) {
                        $dupPerm = false;
                    }
                }
                if (
                    !$dupPerm
                    && ($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User')
                    && $userCompanyId
                    && empty($dupSrcTicket['company_id'])
                    && !empty($dupSrcTicket['customer_id'])
                ) {
                    try {
                        $custStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ? LIMIT 1");
                        $custStmt->execute([(int)$dupSrcTicket['customer_id']]);
                        $cust = $custStmt->fetch(PDO::FETCH_ASSOC);
                        if ($cust && (int)$cust['company_id'] === (int)$userCompanyId) {
                            $dupPerm = true;
                        }
                    } catch (PDOException $e) {
                        // Ignorieren
                    }
                }
                if (!$dupPerm) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für Anhänge des Quell-Tickets']);
                    exit;
                }
            }
            
            // Ticket-Nummer generieren
        $ticketNummer = 'SRV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Prüfen ob Ticket-Nummer bereits existiert
            $checkStmt = $pdo->prepare("SELECT id FROM tickets WHERE ticket_nummer = ?");
            $checkStmt->execute([$ticketNummer]);
            if ($checkStmt->fetch()) {
                // Falls existiert, neue generieren
                $ticketNummer = 'SRV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            // Datumsfelder aus Request extrahieren und konvertieren
            $faelligDatum = null;
            if (isset($data['faellig_datum']) && !empty($data['faellig_datum'])) {
                try {
                    $date = new DateTime($data['faellig_datum']);
                    $faelligDatum = $date->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    error_log("Fehler beim Konvertieren des Fälligkeitsdatums (POST): " . $e->getMessage());
                }
            }
            
            $geplantDatum = null;
            $geplantDatumEnde = null;
            // "Geplant" nur für Admin/Techniker
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                if (isset($data['geplant_datum']) && !empty($data['geplant_datum'])) {
                    try {
                        $date = new DateTime($data['geplant_datum']);
                        $geplantDatum = $date->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        error_log("Fehler beim Konvertieren des geplanten Datums (POST): " . $e->getMessage());
                    }
                }
                if ($hasTicketEndColumns && isset($data['geplant_datum_ende']) && !empty($data['geplant_datum_ende'])) {
                    try {
                        $date = new DateTime($data['geplant_datum_ende']);
                        $geplantDatumEnde = $date->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        error_log("Fehler beim Konvertieren des geplanten Enddatums (POST): " . $e->getMessage());
                    }
                }
            }
            
            $faelligDatumEnde = null;
            if ($hasTicketEndColumns && isset($data['faellig_datum_ende']) && !empty($data['faellig_datum_ende'])) {
                try {
                    $date = new DateTime($data['faellig_datum_ende']);
                    $faelligDatumEnde = $date->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    error_log("Fehler beim Konvertieren des Fälligkeits-Enddatums (POST): " . $e->getMessage());
                }
            }
            
            $abgeschlossenDatum = null;
            if (isset($data['abgeschlossen_datum']) && !empty($data['abgeschlossen_datum'])) {
                try {
                    $date = new DateTime($data['abgeschlossen_datum']);
                    $abgeschlossenDatum = $date->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    error_log("Fehler beim Konvertieren des abgeschlossenen Datums (POST): " . $e->getMessage());
                }
            }
            
            // Status bei Erstellung: mit geplantem Datum → "Geplant", sonst "Neu"
            $initialStatus = ($geplantDatum !== null) ? 'Geplant' : 'neu';
            
            if ($hasTicketEndColumns) {
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_nummer, titel, beschreibung, status, prioritaet, company_id, customer_id, device_id, zugewiesen_an, erstellt_von, erstellt_datum, geaendert_datum, faellig_datum, geplant_datum, faellig_datum_ende, geplant_datum_ende, abgeschlossen_datum)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$ticketNummer, $titel, $beschreibung, $initialStatus, $prioritaet, $companyId, $customerId, $deviceId, $zugewiesenAn, $erstelltVon, $faelligDatum, $geplantDatum, $faelligDatumEnde, $geplantDatumEnde, $abgeschlossenDatum]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_nummer, titel, beschreibung, status, prioritaet, company_id, customer_id, device_id, zugewiesen_an, erstellt_von, erstellt_datum, geaendert_datum, faellig_datum, geplant_datum, abgeschlossen_datum)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)
                ");
                $stmt->execute([$ticketNummer, $titel, $beschreibung, $initialStatus, $prioritaet, $companyId, $customerId, $deviceId, $zugewiesenAn, $erstelltVon, $faelligDatum, $geplantDatum, $abgeschlossenDatum]);
            }
            
            $ticketId = $pdo->lastInsertId();
            
            // Beobachter hinzufügen
            $observerIds = isset($data['observer_ids']) && is_array($data['observer_ids']) ? $data['observer_ids'] : [];
            
            // Kunden dürfen Beobachter nicht setzen
            if ($userRole === 'Kunde' && !empty($observerIds)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Beobachter müssen zur Ticket-Firma gehören
            if (!empty($observerIds)) {
                if (!$companyId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Firma erforderlich für Beobachter']);
                    exit;
                }
                
                $observerIds = array_values(array_unique(array_filter(array_map('intval', $observerIds), fn($v) => $v > 0)));
                
                if (!empty($observerIds)) {
                    $placeholders = implode(',', array_fill(0, count($observerIds), '?'));
                    $params = array_merge([(int)$companyId], $observerIds);
                    $validStmt = $pdo->prepare("
                        SELECT id FROM users
                        WHERE company_id = ? AND status = 'aktiv' AND id IN ($placeholders)
                    ");
                    $validStmt->execute($params);
                    $validIds = array_map('intval', array_column($validStmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
                    
                    // Wenn nicht alle gültig sind -> blockieren
                    sort($validIds);
                    $requested = $observerIds;
                    sort($requested);
                    if ($validIds !== $requested) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                        exit;
                    }
                }
            }
            
            if (!empty($observerIds)) {
                $observerStmt = $pdo->prepare("INSERT INTO ticket_observers (ticket_id, user_id, erstellt_datum) VALUES (?, ?, NOW())");
                foreach ($observerIds as $observerId) {
                    $observerId = (int)$observerId;
                    if ($observerId > 0) {
                        try {
                            $observerStmt->execute([$ticketId, $observerId]);
                        } catch (PDOException $e) {
                            // Duplikat ignorieren (UNIQUE KEY verhindert doppelte Einträge)
                            error_log("Observer bereits vorhanden: " . $e->getMessage());
                        }
                    }
                }
            }
            
            if ($duplicateFromTicketId > 0) {
                $attachmentsDuplicated = 0;
                try {
                    $attStmt = $pdo->prepare("SELECT dateiname, dateipfad, dateigroesse, mime_type FROM ticket_attachments WHERE ticket_id = ? ORDER BY erstellt_datum ASC, id ASC");
                    $attStmt->execute([$duplicateFromTicketId]);
                    $attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($attRows)) {
                        $webroot = dirname(__DIR__, 2);
                        $uploadBaseDir = $webroot . '/uploads/tickets/';
                        if (!is_dir($uploadBaseDir)) {
                            @mkdir($uploadBaseDir, 0755, true);
                        }
                        $insertAtt = $pdo->prepare("INSERT INTO ticket_attachments (ticket_id, dateiname, dateipfad, dateigroesse, mime_type, erstellt_von, erstellt_datum) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $newTid = (int)$ticketId;
                        foreach ($attRows as $row) {
                            $rel = ltrim(str_replace('\\', '/', (string)($row['dateipfad'] ?? '')), '/');
                            if ($rel === '' || strpos($rel, 'uploads/tickets/') !== 0) {
                                error_log('Duplikat Ticket-Anhang: übersprungen (Pfad): ' . $rel);
                                continue;
                            }
                            $srcPath = $webroot . '/' . $rel;
                            if (!is_file($srcPath) || !is_readable($srcPath)) {
                                error_log('Duplikat Ticket-Anhang: Quelldatei fehlt oder nicht lesbar: ' . $srcPath);
                                continue;
                            }
                            $origName = (string)($row['dateiname'] ?? 'Anhang');
                            $ext = pathinfo($origName, PATHINFO_EXTENSION);
                            $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                            if ($safeBase === '') {
                                $safeBase = 'datei';
                            }
                            $newFileName = 'ticket_' . $newTid . '_' . $safeBase . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . ($ext !== '' ? '.' . $ext : '');
                            $destPath = $uploadBaseDir . $newFileName;
                            if (!@copy($srcPath, $destPath)) {
                                error_log('Duplikat Ticket-Anhang: copy fehlgeschlagen: ' . $srcPath . ' -> ' . $destPath);
                                continue;
                            }
                            $relativePath = 'uploads/tickets/' . $newFileName;
                            $size = @filesize($destPath);
                            if ($size === false) {
                                $size = (int)($row['dateigroesse'] ?? 0);
                            }
                            $mime = trim((string)($row['mime_type'] ?? ''));
                            if ($mime === '' && function_exists('mime_content_type')) {
                                $mime = (string)mime_content_type($destPath);
                            }
                            if ($mime === '') {
                                $mime = 'application/octet-stream';
                            }
                            $insertAtt->execute([$newTid, $origName, $relativePath, $size, $mime, $userId]);
                            $attachmentsDuplicated++;
                        }
                        if ($attachmentsDuplicated > 0) {
                            $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?")->execute([$newTid]);
                            service_log($pdo, $userId, 'ticket', $newTid, 'created', null, null, null, $attachmentsDuplicated . ' Ticket-Anhänge von #' . $duplicateFromTicketId . ' dupliziert');
                        }
                    }
                } catch (Throwable $e) {
                    error_log('Duplikat Ticket-Anhänge: ' . $e->getMessage());
                }
            }
            
            // Relevanz basierend auf Priorität bestimmen
            $relevanz = 'normal';
            if ($prioritaet === 'kritisch') {
                $relevanz = 'kritisch';
            } elseif ($prioritaet === 'hoch') {
                $relevanz = 'hoch';
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
            if (empty($userName)) {
                $userName = 'Unbekannt';
            }
            
            // Benachrichtigungen an beteiligte Nutzer + immer Admin/Techniker
            $createNotifyUserIds = getTicketNotificationRecipients((int)$ticketId, (int)$userId);
            if (!empty($createNotifyUserIds)) {
                createNotificationsForUsers(
                    $createNotifyUserIds,
                    'ticket_created',
                    'Neues Ticket erstellt: ' . $titel,
                    'Ein neues Ticket "' . $titel . '" wurde von ' . $userName . ' erstellt.',
                    $relevanz,
                    'tickets/view.php?id=' . $ticketId,
                    'ticket',
                    $ticketId,
                    $userId
                );
            }
            
            service_log($pdo, $userId, 'ticket', $ticketId, 'created', null, null, null, 'Ticket erstellt: ' . $ticketNummer . ' – ' . $titel);
            echo json_encode([
                'success' => true,
                'message' => 'Ticket erfolgreich erstellt',
                'ticket_id' => $ticketId,
                'ticket_nummer' => $ticketNummer
            ]);
            break;

        case 'DELETE':
            $deleteTicketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$deleteTicketId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            $checkStmt = $pdo->prepare("SELECT erstellt_von, company_id, titel FROM tickets WHERE id = ?");
            $checkStmt->execute([$deleteTicketId]);
            $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                exit;
            }
            if (isset($ticket['titel']) && strpos((string)$ticket['titel'], '[Gelöscht] ') === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ticket wurde bereits gelöscht']);
                exit;
            }
            if ($userRole !== 'Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Nur Admins dürfen Tickets löschen']);
                exit;
            }
            try {
                // Soft-delete: Ticket bleibt in der Datenbank erhalten
                $pdo->prepare("UPDATE tickets SET titel = CONCAT('[Gelöscht] ', titel), status = 'Geschlossen', geaendert_datum = NOW(), abgeschlossen_datum = COALESCE(abgeschlossen_datum, NOW()) WHERE id = ?")->execute([$deleteTicketId]);
                service_log($pdo, $userId, 'ticket', $deleteTicketId, 'deleted', null, null, null, 'Ticket soft-gelöscht');
                echo json_encode(['success' => true, 'message' => 'Ticket gelöscht']);
            } catch (PDOException $e) {
                error_log("Fehler beim Löschen des Tickets: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Löschen fehlgeschlagen']);
            }
            break;
            
        case 'PUT':
            // Ticket aktualisieren
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['ticket_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ticket_id fehlt']);
                exit;
            }
            
            $ticketId = (int)$data['ticket_id'];
            
            // Prüfen ob Ticket existiert und Berechtigung
            $checkStmt = $pdo->prepare("
                SELECT
                    id,
                    erstellt_von,
                    company_id,
                    customer_id,
                    device_id,
                    titel,
                    beschreibung,
                    status,
                    prioritaet,
                    zugewiesen_an,
                    faellig_datum,
                    faellig_datum_ende,
                    geplant_datum,
                    geplant_datum_ende,
                    abgeschlossen_datum,
                    abgerechnet,
                    bearbeitungszeit_minuten
                FROM tickets
                WHERE id = ?
            ");
            $checkStmt->execute([$ticketId]);
            $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                exit;
            }
            if (isset($ticket['titel']) && strpos((string)$ticket['titel'], '[Gelöscht] ') === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-User' && $ticket['company_id'] == $userCompanyId && $userCompanyId) {
                // Firmen-User darf Tickets der eigenen Firma bearbeiten
                $hasPermission = true;
            } elseif ($ticket['erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            // Sonderfall: Ticket hat keine company_id, aber customer gehört zur Firma des Users
            if (
                !$hasPermission
                && ($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User')
                && $userCompanyId
                && empty($ticket['company_id'])
                && !empty($ticket['customer_id'])
            ) {
                try {
                    $custStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ? LIMIT 1");
                    $custStmt->execute([(int)$ticket['customer_id']]);
                    $cust = $custStmt->fetch(PDO::FETCH_ASSOC);
                    if ($cust && (int)$cust['company_id'] === (int)$userCompanyId) {
                        $hasPermission = true;
                    }
                } catch (PDOException $e) {
                    // Ignorieren
                }
            }
            
            // Beobachter-Only: Kunde/Firmen-User/Firmen-Admin dürfen als reine Beobachter nur ansehen.
            $isObserver = false;
            try {
                $obsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = :ticket_id AND user_id = :user_id LIMIT 1");
                $obsStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
                $obsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $obsStmt->execute();
                $isObserver = (bool)$obsStmt->fetchColumn();
            } catch (PDOException $e) {
                $isObserver = false;
            }
            
            if (
                $isObserver
                && ($userRole === 'Kunde' || $userRole === 'Firmen-User' || $userRole === 'Firmen-Admin')
                && (int)$ticket['erstellt_von'] !== (int)$userId
            ) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Nur Ansicht (Beobachter)']);
                exit;
            }

            // User-bezogenes Anheften (kein Ticket-Edit, sondern User-Setting)
            if (array_key_exists('pinned', $data)) {
                if (!$hasPermission && !$isObserver) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }

                $shouldPin = $data['pinned'] === 1 || $data['pinned'] === '1' || $data['pinned'] === true;
                try {
                    $userSettingsStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'service_pinned_tickets' LIMIT 1");
                    $userSettingsStmt->execute([$userId]);
                    $row = $userSettingsStmt->fetch(PDO::FETCH_ASSOC);
                    $ids = [];
                    if ($row && isset($row['setting_value'])) {
                        $decoded = json_decode((string)$row['setting_value'], true);
                        if (is_array($decoded)) {
                            $ids = array_values(array_unique(array_filter(array_map('intval', $decoded), fn($v) => $v > 0)));
                        }
                    }

                    if ($shouldPin) {
                        if (!in_array($ticketId, $ids, true)) {
                            $ids[] = $ticketId;
                        }
                    } else {
                        $ids = array_values(array_filter($ids, fn($id) => (int)$id !== (int)$ticketId));
                    }

                    $encoded = json_encode(array_values($ids));
                    $saveStmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, setting_key, setting_value)
                        VALUES (?, 'service_pinned_tickets', ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $saveStmt->execute([$userId, $encoded]);

                    service_log($pdo, $userId, 'ticket', $ticketId, 'updated', 'pinned', null, $shouldPin ? '1' : '0', $shouldPin ? 'Ticket angeheftet' : 'Anheftung entfernt');
                    echo json_encode([
                        'success' => true,
                        'message' => $shouldPin ? 'Ticket angeheftet' : 'Anheftung entfernt',
                        'pinned' => $shouldPin ? 1 : 0
                    ]);
                    exit;
                } catch (PDOException $e) {
                    error_log("Fehler beim Setzen der Anheftung: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Anheften fehlgeschlagen']);
                    exit;
                }
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }

            // Ticket zusammenführen: Inhalte auf ein anderes Ticket übertragen und aktuelles Ticket soft-löschen
            if (isset($data['merge_into_ticket_id'])) {
                if ($userRole !== 'Admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Nur Admins dürfen Tickets zusammenführen']);
                    exit;
                }

                $targetTicketId = (int)$data['merge_into_ticket_id'];
                if ($targetTicketId <= 0 || $targetTicketId === $ticketId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültiges Ziel-Ticket']);
                    exit;
                }

                $targetStmt = $pdo->prepare("SELECT id, titel, beschreibung FROM tickets WHERE id = ? LIMIT 1");
                $targetStmt->execute([$targetTicketId]);
                $targetTicket = $targetStmt->fetch(PDO::FETCH_ASSOC);
                if (!$targetTicket || (isset($targetTicket['titel']) && strpos((string)$targetTicket['titel'], '[Gelöscht] ') === 0)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Ziel-Ticket nicht gefunden']);
                    exit;
                }

                try {
                    $pdo->beginTransaction();

                    // Nachrichten (inkl. Kommentar-Anhänge über comment_id)
                    $moveCommentsStmt = $pdo->prepare("UPDATE ticket_comments SET ticket_id = ? WHERE ticket_id = ?");
                    $moveCommentsStmt->execute([$targetTicketId, $ticketId]);

                    // Ticket-Anhänge
                    $moveTicketAttachmentsStmt = $pdo->prepare("UPDATE ticket_attachments SET ticket_id = ? WHERE ticket_id = ?");
                    $moveTicketAttachmentsStmt->execute([$targetTicketId, $ticketId]);

                    // Bestellungen
                    $moveOrdersStmt = $pdo->prepare("UPDATE orders SET ticket_id = ? WHERE ticket_id = ?");
                    $moveOrdersStmt->execute([$targetTicketId, $ticketId]);

                    // Aufgaben
                    $moveTodosStmt = $pdo->prepare("UPDATE todos SET ticket_id = ? WHERE ticket_id = ?");
                    $moveTodosStmt->execute([$targetTicketId, $ticketId]);

                    // Beobachter zusammenführen (Duplikate tolerieren)
                    $moveObserversStmt = $pdo->prepare("
                        INSERT INTO ticket_observers (ticket_id, user_id, erstellt_datum)
                        SELECT ?, src.user_id, NOW()
                        FROM ticket_observers src
                        WHERE src.ticket_id = ?
                        AND NOT EXISTS (
                            SELECT 1 FROM ticket_observers dst
                            WHERE dst.ticket_id = ? AND dst.user_id = src.user_id
                        )
                    ");
                    $moveObserversStmt->execute([$targetTicketId, $ticketId, $targetTicketId]);

                    $deleteSourceObserversStmt = $pdo->prepare("DELETE FROM ticket_observers WHERE ticket_id = ?");
                    $deleteSourceObserversStmt->execute([$ticketId]);

                    // Beschreibung des Quell-Tickets an das Ziel anhängen (falls vorhanden und nicht identisch)
                    $srcDescStmt = $pdo->prepare("SELECT beschreibung, ticket_nummer FROM tickets WHERE id = ? LIMIT 1");
                    $srcDescStmt->execute([$ticketId]);
                    $sourceDescRow = $srcDescStmt->fetch(PDO::FETCH_ASSOC);
                    $srcDesc = isset($sourceDescRow['beschreibung']) ? trim((string)$sourceDescRow['beschreibung']) : '';
                    $tgtDesc = isset($targetTicket['beschreibung']) ? trim((string)$targetTicket['beschreibung']) : '';
                    if ($srcDesc !== '' && $srcDesc !== $tgtDesc) {
                        $srcNummer = (isset($sourceDescRow['ticket_nummer']) && (string)$sourceDescRow['ticket_nummer'] !== '')
                            ? (string)$sourceDescRow['ticket_nummer']
                            : ('#' . $ticketId);
                        $mergedBeschreibung = $tgtDesc === ''
                            ? $srcDesc
                            : $tgtDesc . "\n\n---\nBeschreibung aus zusammengeführtem Auftrag " . $srcNummer . ":\n\n" . $srcDesc;
                        $mergeDescStmt = $pdo->prepare("UPDATE tickets SET beschreibung = ?, geaendert_datum = NOW() WHERE id = ?");
                        $mergeDescStmt->execute([$mergedBeschreibung, $targetTicketId]);
                    }

                    // Altes Ticket soft-löschen
                    $softDeleteSourceStmt = $pdo->prepare("
                        UPDATE tickets
                        SET titel = CASE
                                WHEN titel LIKE '[Gelöscht] %' THEN titel
                                ELSE CONCAT('[Gelöscht] ', titel)
                            END,
                            status = 'Geschlossen',
                            geaendert_datum = NOW(),
                            abgeschlossen_datum = COALESCE(abgeschlossen_datum, NOW())
                        WHERE id = ?
                    ");
                    $softDeleteSourceStmt->execute([$ticketId]);

                    // Ziel-Ticket als aktualisiert markieren (falls Beschreibung nicht bereits gesetzt wurde)
                    if ($srcDesc === '' || $srcDesc === $tgtDesc) {
                        $touchTargetStmt = $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?");
                        $touchTargetStmt->execute([$targetTicketId]);
                    }

                    $pdo->commit();
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("Fehler beim Zusammenführen von Ticket #" . $ticketId . " in #" . $targetTicketId . ": " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Zusammenführen fehlgeschlagen']);
                    exit;
                }

                service_log($pdo, $userId, 'ticket', $ticketId, 'deleted', null, null, null, 'Ticket in #' . $targetTicketId . ' übertragen und soft-gelöscht');
                service_log($pdo, $userId, 'ticket', $targetTicketId, 'updated', null, null, null, 'Ticket-Inhalte aus #' . $ticketId . ' übernommen');
                echo json_encode([
                    'success' => true,
                    'message' => 'Ticket erfolgreich übertragen',
                    'target_ticket_id' => $targetTicketId
                ]);
                exit;
            }
            
            // Prüfen, ob End-Spalten existieren (Migration 062) – sonst nur Start-Datum speichern
            $hasTicketEndColumns = false;
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'geplant_datum_ende'");
                $hasTicketEndColumns = $chk && $chk->rowCount() > 0;
            } catch (Exception $e) {
                // Spalten fehlen
            }
            
            // Prüfen ob Ticket abgerechnet ist - dann keine Updates mehr erlaubt (außer abgerechnet-Feld und bearbeitungszeit_minuten)
            $isAbgerechnet = isset($ticket['abgerechnet']) && ($ticket['abgerechnet'] == 1 || $ticket['abgerechnet'] === '1');
            
            if ($isAbgerechnet) {
                // Prüfen welche Felder geändert werden sollen (ticket_id wird immer mitgesendet, ignorieren)
                $updateKeys = array_filter(array_keys($data), function($key) {
                    return $key !== 'ticket_id';
                });
                
                // Erlaubt sind: nur abgerechnet, nur bearbeitungszeit_minuten, oder beide zusammen
                $hasAbgerechnet = isset($data['abgerechnet']);
                $hasBearbeitungszeit = isset($data['bearbeitungszeit_minuten']);
                $hasOtherFields = false;
                
                foreach ($updateKeys as $key) {
                    if ($key !== 'abgerechnet' && $key !== 'bearbeitungszeit_minuten') {
                        $hasOtherFields = true;
                        break;
                    }
                }
                
                if ($hasOtherFields) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Abgerechnete Tickets können nicht mehr geändert werden']);
                    exit;
                }
            }
            
            $ticketBefore = $ticket;

            $observerIdsBefore = [];
            try {
                $observerBeforeStmt = $pdo->prepare("SELECT user_id FROM ticket_observers WHERE ticket_id = ?");
                $observerBeforeStmt->execute([$ticketId]);
                $observerIdsBefore = array_map('intval', array_column($observerBeforeStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
                sort($observerIdsBefore);
            } catch (PDOException $e) {
                $observerIdsBefore = [];
            }

            // Update-Felder zusammenbauen
            $updateFields = [];
            $updateParams = [];
            $geplantDatumSet = false; // Flag für automatisches Setzen des Status "Geplant"

            // Firma ändern (company_id)
            if (array_key_exists('company_id', $data)) {
                $newCompanyId = $data['company_id'];
                if ($newCompanyId === null || $newCompanyId === '' || $newCompanyId === 'null' || (int)$newCompanyId === 0) {
                    $newCompanyId = null;
                } else {
                    $newCompanyId = (int)$newCompanyId;
                }
                
                $canChangeCompany = ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin' || $userRole === 'Firmen-User');
                if (!$canChangeCompany) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung (Firma ändern)']);
                    exit;
                }
                
                // Firmenrollen dürfen nur ihre eigene Firma setzen
                if (($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User')) {
                    if (!$userCompanyId) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Firma zugeordnet']);
                        exit;
                    }
                    if ($newCompanyId === null || (int)$newCompanyId !== (int)$userCompanyId) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung (fremde Firma)']);
                        exit;
                    }
                }
                
                // Wenn Firma geändert wird: Kunde/Gerät zurücksetzen (sonst Inkonsistenzen)
                $currentCompanyId = $ticket['company_id'] ? (int)$ticket['company_id'] : null;
                if ($newCompanyId !== $currentCompanyId) {
                    if (!array_key_exists('customer_id', $data)) {
                        $updateFields[] = "customer_id = ?";
                        $updateParams[] = null;
                    }
                    if (!array_key_exists('device_id', $data)) {
                        $updateFields[] = "device_id = ?";
                        $updateParams[] = null;
                    }
                }
                
                $updateFields[] = "company_id = ?";
                $updateParams[] = $newCompanyId;
            }
            
            if (isset($data['status'])) {
                // Status darf nur von Admin/Techniker geändert werden
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung (Status ändern)']);
                    exit;
                }
                $updateFields[] = "status = ?";
                $updateParams[] = $data['status'];
            }
            if (isset($data['prioritaet'])) {
                $updateFields[] = "prioritaet = ?";
                $updateParams[] = $data['prioritaet'];
            }
            if (array_key_exists('zugewiesen_an', $data)) {
                // Bearbeiter nur für Admin/Techniker änderbar
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung (Bearbeiter ändern)']);
                    exit;
                }
                $updateFields[] = "zugewiesen_an = ?";
                $zugewiesenAn = $data['zugewiesen_an'];
                if (
                    $zugewiesenAn === null || $zugewiesenAn === '' || $zugewiesenAn === 'null'
                    || $zugewiesenAn === false
                    || (is_numeric($zugewiesenAn) && (int) $zugewiesenAn === 0)
                ) {
                    $updateParams[] = null;
                } else {
                    $zugewiesenAnInt = (int)$zugewiesenAn;
                    $assigneeStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND status = 'aktiv' AND rolle IN ('Admin','Techniker') LIMIT 1");
                    $assigneeStmt->bindValue(':id', $zugewiesenAnInt, PDO::PARAM_INT);
                    $assigneeStmt->execute();
                    if (!$assigneeStmt->fetch(PDO::FETCH_ASSOC)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Ungültiger Bearbeiter']);
                        exit;
                    }
                    $updateParams[] = $zugewiesenAnInt;
                }
            }
            if (isset($data['titel'])) {
                if ($userRole !== 'Admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Nur Admins dürfen den Betreff ändern']);
                    exit;
                }
                $updateFields[] = "titel = ?";
                $updateParams[] = trim($data['titel']);
            }
            if (isset($data['beschreibung'])) {
                $updateFields[] = "beschreibung = ?";
                $updateParams[] = trim($data['beschreibung']);
            }
            if (array_key_exists('customer_id', $data)) {
                $updateFields[] = "customer_id = ?";
                $updateParams[] = $data['customer_id'] ? (int)$data['customer_id'] : null;
            }
            if (array_key_exists('device_id', $data)) {
                $updateFields[] = "device_id = ?";
                $updateParams[] = $data['device_id'] ? (int)$data['device_id'] : null;
            }
            if (array_key_exists('faellig_datum', $data)) {
                $updateFields[] = "faellig_datum = ?";
                $faelligDatum = $data['faellig_datum'];
                if ($faelligDatum === null || $faelligDatum === '' || $faelligDatum === 'null') {
                    $updateParams[] = null;
                } else {
                    // ISO-Format (2026-01-20T19:27:00.000Z) zu MySQL-Format konvertieren
                    try {
                        $date = new DateTime($faelligDatum);
                        $updateParams[] = $date->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        error_log("Fehler beim Konvertieren des Fälligkeitsdatums: " . $e->getMessage());
                        $updateParams[] = null;
                    }
                }
            }
            if ($hasTicketEndColumns && array_key_exists('faellig_datum_ende', $data)) {
                $updateFields[] = "faellig_datum_ende = ?";
                $v = $data['faellig_datum_ende'];
                if ($v === null || $v === '' || $v === 'null') {
                    $updateParams[] = null;
                } else {
                    try {
                        $date = new DateTime($v);
                        $updateParams[] = $date->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        error_log("Fehler beim Konvertieren faellig_datum_ende: " . $e->getMessage());
                        $updateParams[] = null;
                    }
                }
            }
            if (array_key_exists('geplant_datum', $data)) {
                // "Geplant" nur für Admin/Techniker änderbar
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung (Geplant ändern)']);
                    exit;
                }
                $updateFields[] = "geplant_datum = ?";
                $geplantDatum = $data['geplant_datum'];
                if ($geplantDatum === null || $geplantDatum === '' || $geplantDatum === 'null') {
                    $updateParams[] = null;
                } else {
                    // ISO-Format (2026-01-20T19:27:00.000Z) zu MySQL-Format konvertieren
                    try {
                        $date = new DateTime($geplantDatum);
                        $updateParams[] = $date->format('Y-m-d H:i:s');
                        $geplantDatumSet = true; // Markieren dass geplant_datum erfolgreich gesetzt wurde
                    } catch (Exception $e) {
                        error_log("Fehler beim Konvertieren des geplanten Datums: " . $e->getMessage());
                        $updateParams[] = null;
                    }
                }
            }
            if ($hasTicketEndColumns && array_key_exists('geplant_datum_ende', $data)) {
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung (Geplant Ende ändern)']);
                    exit;
                }
                $updateFields[] = "geplant_datum_ende = ?";
                $v = $data['geplant_datum_ende'];
                if ($v === null || $v === '' || $v === 'null') {
                    $updateParams[] = null;
                } else {
                    try {
                        $date = new DateTime($v);
                        $updateParams[] = $date->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        error_log("Fehler beim Konvertieren geplant_datum_ende: " . $e->getMessage());
                        $updateParams[] = null;
                    }
                }
            }
            if (isset($data['abgeschlossen_datum'])) {
                $updateFields[] = "abgeschlossen_datum = ?";
                $updateParams[] = !empty($data['abgeschlossen_datum']) ? $data['abgeschlossen_datum'] : null;
            }
            if (isset($data['abgerechnet'])) {
                $wantAbgerechnet = $data['abgerechnet'] === 1 || $data['abgerechnet'] === '1' || $data['abgerechnet'] === true;
                if ($wantAbgerechnet) {
                    try {
                        $chkProj = $pdo->prepare("SELECT 1 FROM project_tickets WHERE ticket_id = ? LIMIT 1");
                        $chkProj->execute([$ticketId]);
                        if ($chkProj->fetch()) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'Ein einem Projekt zugeordneter Ticket kann nicht abgerechnet werden.']);
                            exit;
                        }
                    } catch (PDOException $e) { /* project_tickets kann fehlen */ }
                }
                $updateFields[] = "abgerechnet = ?";
                $updateParams[] = $wantAbgerechnet ? 1 : 0;
                // Abgerechnet → Status Archiv, nicht abgerechnet → Status Geschlossen
                $updateFields[] = "status = ?";
                $updateParams[] = $wantAbgerechnet ? 'Archiv' : 'Geschlossen';
            }
            if (array_key_exists('bearbeitungszeit_minuten', $data)) {
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung (Bearbeitungszeit)']);
                    exit;
                }
                $v = $data['bearbeitungszeit_minuten'];
                if ($v === null || $v === '' || $v === 'null') {
                    $updateFields[] = "bearbeitungszeit_minuten = ?";
                    $updateParams[] = null;
                } else {
                    $min = (int) $v;
                    $min = $min >= 0 ? $min : 0;
                    $updateFields[] = "bearbeitungszeit_minuten = ?";
                    $updateParams[] = $min;
                }
            }
            
            // Beobachter aktualisieren
            $updateObservers = isset($data['observer_ids']) && is_array($data['observer_ids']);
            if ($updateObservers && $userRole === 'Kunde') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung (Beobachter ändern)']);
                exit;
            }
            
            if (empty($updateFields) && !$updateObservers) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Felder zum Aktualisieren']);
                exit;
            }
            
            // Wenn geplant_datum gesetzt wird und Status nicht manuell geändert wird, Status automatisch auf "Geplant" setzen
            if ($geplantDatumSet && !isset($data['status'])) {
                $updateFields[] = "status = ?";
                $updateParams[] = 'Geplant';
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "geaendert_datum = NOW()";
                if (isset($data['status']) && $data['status'] === 'Geschlossen') {
                    $updateFields[] = "abgeschlossen_datum = NOW()";
                    // Firma mit Wartungsvertrag: geschlossene Tickets direkt auf abgerechnet setzen → Status Archiv (nur wenn Ticket keinem Projekt zugeordnet)
                    $companyIdForWartung = isset($data['company_id']) ? ($data['company_id'] ? (int)$data['company_id'] : null) : ($ticket['company_id'] ? (int)$ticket['company_id'] : null);
                    $setWartungAbgerechnet = false;
                    if ($companyIdForWartung) {
                        try {
                            $ptStmt = $pdo->prepare("SELECT 1 FROM project_tickets WHERE ticket_id = ? LIMIT 1");
                            $ptStmt->execute([$ticketId]);
                            $ticketHasProject = (bool) $ptStmt->fetch();
                            if (!$ticketHasProject) {
                                $wStmt = $pdo->prepare("SELECT hat_wartungsvertrag FROM companies WHERE id = ? LIMIT 1");
                                $wStmt->execute([$companyIdForWartung]);
                                $cRow = $wStmt->fetch(PDO::FETCH_ASSOC);
                                if ($cRow && ($cRow['hat_wartungsvertrag'] == 1 || $cRow['hat_wartungsvertrag'] === '1')) {
                                    $updateFields[] = "abgerechnet = ?";
                                    $updateParams[] = 1;
                                    $setWartungAbgerechnet = true;
                                }
                            }
                        } catch (PDOException $e) {
                            // Spalte hat_wartungsvertrag kann fehlen
                        }
                    }
                    if ($setWartungAbgerechnet) {
                        $updateFields[] = "status = ?";
                        $updateParams[] = 'Archiv';
                    }
                }
                
                $updateParams[] = $ticketId;
                
                $sql = "UPDATE tickets SET " . implode(", ", $updateFields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                /* Wie todos/api/todos.php: execute($params) — zuverlässige NULL-Ints; manuelles bindValue+execute() führte hier sporadisch zu fehlenden Updates. */
                try {
                    $stmt->execute($updateParams);
                } catch (PDOException $e) {
                    error_log("SQL Update Error: " . $e->getMessage());
                    error_log("SQL: " . $sql);
                    error_log("Params: " . print_r($updateParams, true));
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Datenbankfehler beim Aktualisieren: ' . $e->getMessage()]);
                    exit;
                }
                    // Bei Schließen des Tickets: alle zugehörigen Bestellungen auf "Angekommen" setzen
                    if (isset($data['status']) && $data['status'] === 'Geschlossen') {
                        try {
                            $selOrders = $pdo->prepare("SELECT id, status FROM orders WHERE ticket_id = ?");
                            $selOrders->execute([$ticketId]);
                            $ordersBefore = $selOrders->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($ordersBefore)) {
                                $updOrders = $pdo->prepare("UPDATE orders SET status = 'Angekommen' WHERE ticket_id = ?");
                                $updOrders->execute([$ticketId]);
                                $hasBemerkungCol = false;
                                try {
                                    $chk = $pdo->query("SHOW COLUMNS FROM order_status_history LIKE 'bemerkung'");
                                    $hasBemerkungCol = $chk && $chk->rowCount() > 0;
                                } catch (PDOException $e) { /* Spalte fehlt */ }
                                if ($hasBemerkungCol) {
                                    $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, geaendert_von, bemerkung) VALUES (?, 'Angekommen', ?, 'Ticket geschlossen')");
                                } else {
                                    $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, geaendert_von) VALUES (?, 'Angekommen', ?)");
                                }
                                $logStmt = $pdo->prepare("INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, beschreibung, erstellt_datum) VALUES ('order', ?, ?, 'updated', 'status', ?, 'Angekommen', 'Automatisch gesetzt (Ticket geschlossen)', NOW())");
                                foreach ($ordersBefore as $row) {
                                    $oid = (int)$row['id'];
                                    $histStmt->execute([$oid, $userId]);
                                    $logStmt->execute([$oid, $userId, $row['status'] ?? '']);
                                }
                            }
                        } catch (PDOException $e) {
                            error_log("Fehler beim Setzen der Bestellungen auf Angekommen (Ticket geschlossen): " . $e->getMessage());
                        }
                    }
            }
            
            // Beobachter aktualisieren
            if ($updateObservers) {
                // Alle bestehenden Beobachter löschen
                $deleteStmt = $pdo->prepare("DELETE FROM ticket_observers WHERE ticket_id = ?");
                $deleteStmt->execute([$ticketId]);
                
                // Neue Beobachter einfügen
                $observerIds = $data['observer_ids'];
                if (!empty($observerIds)) {
                    // Beobachter müssen zur Ticket-Firma gehören
                    $ticketCompanyId = (int)($ticket['company_id'] ?? 0);
                    if (!$ticketCompanyId) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Firma erforderlich für Beobachter']);
                        exit;
                    }
                    
                    $observerIds = array_values(array_unique(array_filter(array_map('intval', $observerIds), fn($v) => $v > 0)));
                    if (!empty($observerIds)) {
                        $placeholders = implode(',', array_fill(0, count($observerIds), '?'));
                        $params = array_merge([$ticketCompanyId], $observerIds);
                        $validStmt = $pdo->prepare("
                            SELECT id FROM users
                            WHERE company_id = ? AND status = 'aktiv' AND id IN ($placeholders)
                        ");
                        $validStmt->execute($params);
                        $validIds = array_map('intval', array_column($validStmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
                        
                        sort($validIds);
                        $requested = $observerIds;
                        sort($requested);
                        if ($validIds !== $requested) {
                            http_response_code(403);
                            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                            exit;
                        }
                    }
                    
                    $observerStmt = $pdo->prepare("INSERT INTO ticket_observers (ticket_id, user_id, erstellt_datum) VALUES (?, ?, NOW())");
                    foreach ($observerIds as $observerId) {
                        $observerId = (int)$observerId;
                        if ($observerId > 0) {
                            try {
                                $observerStmt->execute([$ticketId, $observerId]);
                            } catch (PDOException $e) {
                                // Fehler ignorieren
                                error_log("Fehler beim Hinzufügen des Beobachters: " . $e->getMessage());
                            }
                        }
                    }
                }
                
                // geaendert_datum aktualisieren wenn noch nicht geschehen
                if (empty($updateFields)) {
                    $updateStmt = $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?");
                    $updateStmt->execute([$ticketId]);
                }
            }
            
            $ticketAfterStmt = $pdo->prepare("
                SELECT
                    id,
                    erstellt_von,
                    company_id,
                    customer_id,
                    device_id,
                    titel,
                    beschreibung,
                    status,
                    prioritaet,
                    zugewiesen_an,
                    faellig_datum,
                    faellig_datum_ende,
                    geplant_datum,
                    geplant_datum_ende,
                    abgeschlossen_datum,
                    abgerechnet,
                    bearbeitungszeit_minuten
                FROM tickets
                WHERE id = ?
            ");
            $ticketAfterStmt->execute([$ticketId]);
            $ticketAfter = $ticketAfterStmt->fetch(PDO::FETCH_ASSOC);

            $observerIdsAfter = [];
            try {
                $observerAfterStmt = $pdo->prepare("SELECT user_id FROM ticket_observers WHERE ticket_id = ?");
                $observerAfterStmt->execute([$ticketId]);
                $observerIdsAfter = array_map('intval', array_column($observerAfterStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
                sort($observerIdsAfter);
            } catch (PDOException $e) {
                $observerIdsAfter = [];
            }

            $changeLogCount = 0;
            $normalizeValue = static function ($value) {
                if ($value === null) {
                    return null;
                }
                if (is_string($value)) {
                    $trimmed = trim($value);
                    return $trimmed === '' ? null : $trimmed;
                }
                return (string)$value;
            };
            $shortenValue = static function ($value, int $maxLen = 240): string {
                if ($value === null || $value === '') {
                    return 'leer';
                }
                $stringValue = (string)$value;
                if (mb_strlen($stringValue, 'UTF-8') <= $maxLen) {
                    return $stringValue;
                }
                return mb_substr($stringValue, 0, $maxLen - 1, 'UTF-8') . '…';
            };
            $formatDateForLog = static function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }
                try {
                    return (new DateTime((string)$value))->format('d.m.Y H:i');
                } catch (Exception $e) {
                    return (string)$value;
                }
            };
            $toDisplay = static function ($value): string {
                if ($value === null || $value === '') {
                    return 'leer';
                }
                return (string)$value;
            };
            $resolveUserName = static function (PDO $pdo, $id): string {
                if ($id === null || $id === '' || (int)$id <= 0) {
                    return 'Nicht zugewiesen';
                }
                try {
                    $stmt = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = ? LIMIT 1");
                    $stmt->execute([(int)$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $name = trim(((string)($row['vorname'] ?? '')) . ' ' . ((string)($row['nachname'] ?? '')));
                        if ($name !== '') {
                            return $name;
                        }
                    }
                } catch (PDOException $e) {
                    // Fallback auf ID
                }
                return 'User #' . (int)$id;
            };

            if ($ticketBefore && $ticketAfter) {
                $fieldMap = [
                    'status' => 'Status',
                    'prioritaet' => 'Priorität',
                    'titel' => 'Titel',
                    'beschreibung' => 'Beschreibung',
                    'company_id' => 'Firma',
                    'customer_id' => 'Kunde',
                    'device_id' => 'Gerät',
                    'faellig_datum' => 'Fällig ab',
                    'faellig_datum_ende' => 'Fällig bis',
                    'geplant_datum' => 'Geplant ab',
                    'geplant_datum_ende' => 'Geplant bis',
                    'abgeschlossen_datum' => 'Abgeschlossen am',
                    'abgerechnet' => 'Abgerechnet',
                    'bearbeitungszeit_minuten' => 'Bearbeitungszeit (Minuten)',
                ];

                foreach ($fieldMap as $fieldKey => $fieldLabel) {
                    $oldRaw = $ticketBefore[$fieldKey] ?? null;
                    $newRaw = $ticketAfter[$fieldKey] ?? null;

                    if (in_array($fieldKey, ['faellig_datum', 'faellig_datum_ende', 'geplant_datum', 'geplant_datum_ende', 'abgeschlossen_datum'], true)) {
                        $oldRaw = $formatDateForLog($oldRaw);
                        $newRaw = $formatDateForLog($newRaw);
                    }

                    if ($fieldKey === 'abgerechnet') {
                        $oldRaw = ((string)$oldRaw === '1' || (int)$oldRaw === 1) ? 'Ja' : 'Nein';
                        $newRaw = ((string)$newRaw === '1' || (int)$newRaw === 1) ? 'Ja' : 'Nein';
                    }

                    $oldNormalized = $normalizeValue($oldRaw);
                    $newNormalized = $normalizeValue($newRaw);
                    if ($oldNormalized === $newNormalized) {
                        continue;
                    }

                    $oldForStore = $toDisplay($oldRaw);
                    $newForStore = $toDisplay($newRaw);
                    service_log(
                        $pdo,
                        $userId,
                        'ticket',
                        $ticketId,
                        'updated',
                        $fieldKey,
                        $shortenValue($oldForStore),
                        $shortenValue($newForStore),
                        $fieldLabel . ' geändert: "' . $shortenValue($oldForStore, 120) . '" -> "' . $shortenValue($newForStore, 120) . '"'
                    );
                    $changeLogCount++;
                }

                $oldAssignee = $resolveUserName($pdo, $ticketBefore['zugewiesen_an'] ?? null);
                $newAssignee = $resolveUserName($pdo, $ticketAfter['zugewiesen_an'] ?? null);
                if ($oldAssignee !== $newAssignee) {
                    service_log(
                        $pdo,
                        $userId,
                        'ticket',
                        $ticketId,
                        'updated',
                        'zugewiesen_an',
                        $shortenValue($oldAssignee),
                        $shortenValue($newAssignee),
                        'Bearbeiter geändert: "' . $shortenValue($oldAssignee, 120) . '" -> "' . $shortenValue($newAssignee, 120) . '"'
                    );
                    $changeLogCount++;
                }
            }

            if ($observerIdsBefore !== $observerIdsAfter) {
                $oldObservers = empty($observerIdsBefore) ? 'keine' : implode(', ', $observerIdsBefore);
                $newObservers = empty($observerIdsAfter) ? 'keine' : implode(', ', $observerIdsAfter);
                service_log(
                    $pdo,
                    $userId,
                    'ticket',
                    $ticketId,
                    'updated',
                    'observer_ids',
                    $oldObservers,
                    $newObservers,
                    'Beobachter geändert'
                );
                $changeLogCount++;
            }

            // Benachrichtigungen für Statusänderungen und andere Updates
            $ticketInfoStmt = $pdo->prepare("
                SELECT titel, erstellt_von, zugewiesen_an, company_id, prioritaet 
                FROM tickets 
                WHERE id = ?
            ");
            $ticketInfoStmt->execute([$ticketId]);
            $ticketInfo = $ticketInfoStmt->fetch(PDO::FETCH_ASSOC);
            
            // Beobachter-IDs abrufen
            $observerIds = [];
            try {
                $observerStmt = $pdo->prepare("SELECT user_id FROM ticket_observers WHERE ticket_id = ?");
                $observerStmt->execute([$ticketId]);
                while ($row = $observerStmt->fetch(PDO::FETCH_ASSOC)) {
                    $observerIds[] = (int)$row['user_id'];
                }
            } catch (PDOException $e) {
                error_log("Fehler beim Abrufen der Beobachter: " . $e->getMessage());
            }
            
            if ($ticketInfo) {
                $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
                if (empty($userName)) {
                    $userName = 'Unbekannt';
                }
                
                // Relevanz basierend auf Priorität bestimmen
                $relevanz = 'normal';
                if ($ticketInfo['prioritaet'] === 'kritisch') {
                    $relevanz = 'kritisch';
                } elseif ($ticketInfo['prioritaet'] === 'hoch') {
                    $relevanz = 'hoch';
                }
                
                // Benachrichtigungen für Ersteller, Zugewiesenen und Beobachter
                $notifyUserIds = [];
                if ($ticketInfo['erstellt_von'] && $ticketInfo['erstellt_von'] != $userId) {
                    $notifyUserIds[] = $ticketInfo['erstellt_von'];
                }
                if ($ticketInfo['zugewiesen_an'] && $ticketInfo['zugewiesen_an'] != $userId) {
                    $notifyUserIds[] = $ticketInfo['zugewiesen_an'];
                }
                // Beobachter hinzufügen (ohne Duplikate)
                foreach ($observerIds as $observerId) {
                    if ($observerId != $userId && !in_array($observerId, $notifyUserIds)) {
                        $notifyUserIds[] = $observerId;
                    }
                }
                
                // Statusänderungen
                if (isset($data['status'])) {
                    // Status-Texte
                    $statusTexts = [
                        'Neu' => 'neu',
                        'In Bearbeitung' => 'in Bearbeitung genommen',
                        'Warteschlange' => 'in Warteschlange',
                        'Geplant' => 'geplant',
                        'Bestellung offen' => 'Bestellung offen',
                        'Geschlossen' => 'geschlossen',
                        'Archiv' => 'Archiv'
                    ];
                    $statusText = $statusTexts[$data['status']] ?? $data['status'];
                    
                    // Benachrichtigungen für Ersteller, Zugewiesenen und Beobachter
                    if (!empty($notifyUserIds)) {
                        createNotificationsForUsers(
                            $notifyUserIds,
                            'ticket_status_changed',
                            'Ticket-Status geändert: ' . $ticketInfo['titel'],
                            'Der Status des Tickets "' . $ticketInfo['titel'] . '" wurde von ' . $userName . ' auf "' . $statusText . '" gesetzt.',
                            $relevanz,
                            'tickets/view.php?id=' . $ticketId,
                            'ticket',
                            $ticketId,
                            $userId
                        );
                    }
                    
                } else {
                    // Normale Updates (außer Status) - Benachrichtigungen an alle Beteiligten
                    if (!empty($notifyUserIds)) {
                        createNotificationsForUsers(
                            $notifyUserIds,
                            'ticket_updated',
                            'Ticket aktualisiert: ' . $ticketInfo['titel'],
                            'Das Ticket "' . $ticketInfo['titel'] . '" wurde von ' . $userName . ' aktualisiert.',
                            $relevanz,
                            'tickets/view.php?id=' . $ticketId,
                            'ticket',
                            $ticketId,
                            $userId
                        );
                    }
                    
                }
            }
            
            if ($changeLogCount === 0) {
                service_log($pdo, $userId, 'ticket', $ticketId, 'updated', null, null, null, 'Ticket #' . $ticketId . ' aktualisiert');
            }
            echo json_encode(['success' => true, 'message' => 'Ticket aktualisiert']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Tickets API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
