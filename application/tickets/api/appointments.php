<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

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
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = :user_id LIMIT 1");
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

/** Standard-Ende wie im Kalender: +1 Stunde, wenn keine Endzeit angegeben. */
function appointmentNormalizeEnd(?string $endeDatum, string $startDatum): string
{
    if ($endeDatum !== null && trim($endeDatum) !== '') {
        return trim($endeDatum);
    }
    $start = new DateTime($startDatum);
    $end = clone $start;
    $end->modify('+1 hour');
    return $end->format('Y-m-d H:i:s');
}

try {
    switch ($method) {
        case 'GET':
            // Termine für ein Ticket abrufen
            if (!isset($_GET['ticket_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ticket_id fehlt']);
                exit;
            }
            
            $ticketId = (int)$_GET['ticket_id'];
            
            // Prüfen ob Ticket existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT company_id FROM tickets WHERE id = ?");
            $checkStmt->execute([$ticketId]);
            $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
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
            } elseif ($userRole === 'Firmen-User' && $ticket['company_id'] == $userCompanyId && $userCompanyId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Termine abrufen
            $stmt = $pdo->prepare("
                SELECT 
                    ta.id,
                    ta.ticket_id,
                    ta.titel,
                    ta.typ,
                    ta.start_datum,
                    ta.ende_datum,
                    ta.erstellt_datum,
                    ta.erstellt_von,
                    t.ticket_nummer
                FROM ticket_appointments ta
                JOIN tickets t ON ta.ticket_id = t.id
                WHERE ta.ticket_id = ?
                ORDER BY ta.start_datum ASC
            ");
            $stmt->execute([$ticketId]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'appointments' => $appointments
            ]);
            break;
            
        case 'POST':
            // Neuen Termin erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['ticket_id']) || !isset($data['typ']) || !isset($data['start_datum'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ticket_id, typ und start_datum sind erforderlich']);
                exit;
            }
            
            $ticketId = (int)$data['ticket_id'];
            $typ = $data['typ'];
            $startDatum = $data['start_datum'];
            $endeDatum = isset($data['ende_datum']) && !empty($data['ende_datum']) ? $data['ende_datum'] : null;
            $titel = isset($data['titel']) && !empty($data['titel']) ? trim($data['titel']) : null;
            
            // Validierung
            if (!in_array($typ, ['geplant', 'faellig'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültiger Typ']);
                exit;
            }
            
            try {
                $startDate = new DateTime($startDatum);
                $endeDatum = appointmentNormalizeEnd($endeDatum, $startDatum);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültiges Datumsformat']);
                exit;
            }
            
            // Prüfen ob Ticket existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT company_id, status, abgerechnet FROM tickets WHERE id = ?");
            $checkStmt->execute([$ticketId]);
            $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
                exit;
            }
            
            if ($ticket['status'] === 'Geschlossen' || $ticket['status'] === 'Archiv') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Termine können nicht zu geschlossenen Tickets hinzugefügt werden']);
                exit;
            }
            
            if (isset($ticket['abgerechnet']) && ($ticket['abgerechnet'] == 1 || $ticket['abgerechnet'] === '1')) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Zu abgerechneten Tickets können keine Termine mehr hinzugefügt werden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-User' && $ticket['company_id'] == $userCompanyId && $userCompanyId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Enddatum validieren (nach Normalisierung)
            try {
                $endDate = new DateTime($endeDatum);
                if ($endDate < $startDate) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Enddatum muss nach Startdatum liegen']);
                    exit;
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültiges Datumsformat']);
                exit;
            }
            
            // Termin einfügen
            $stmt = $pdo->prepare("
                INSERT INTO ticket_appointments (ticket_id, titel, typ, start_datum, ende_datum, erstellt_von)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ticketId, $titel, $typ, $startDatum, $endeDatum, $userId]);
            
            $appointmentId = $pdo->lastInsertId();
            
            // Status auf "Geplant" setzen und "Zuletzt geändert" aktualisieren
            $updateStmt = $pdo->prepare("UPDATE tickets SET status = 'Geplant', geaendert_datum = NOW() WHERE id = ?");
            $updateStmt->execute([$ticketId]);
            
            echo json_encode([
                'success' => true,
                'appointment_id' => $appointmentId,
                'message' => 'Termin erfolgreich erstellt'
            ]);
            break;
            
        case 'PUT':
            // Termin aktualisieren
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id']) || !isset($data['typ']) || !isset($data['start_datum'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id, typ und start_datum sind erforderlich']);
                exit;
            }
            
            $appointmentId = (int)$data['id'];
            $typ = $data['typ'];
            $startDatum = $data['start_datum'];
            $endeDatum = isset($data['ende_datum']) && !empty($data['ende_datum']) ? $data['ende_datum'] : null;
            $titel = isset($data['titel']) && !empty($data['titel']) ? trim($data['titel']) : null;
            
            // Validierung
            if (!in_array($typ, ['geplant', 'faellig'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültiger Typ']);
                exit;
            }
            
            try {
                $startDate = new DateTime($startDatum);
                $endeDatum = appointmentNormalizeEnd($endeDatum, $startDatum);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültiges Datumsformat']);
                exit;
            }
            
            // Prüfen ob Termin existiert und Berechtigung
            $checkStmt = $pdo->prepare("
                SELECT ta.id, ta.ticket_id, t.company_id, t.status
                FROM ticket_appointments ta
                JOIN tickets t ON ta.ticket_id = t.id
                WHERE ta.id = ?
            ");
            $checkStmt->execute([$appointmentId]);
            $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Termin nicht gefunden']);
                exit;
            }
            
            if ($appointment['status'] === 'Geschlossen' || $appointment['status'] === 'Archiv') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Termine können nicht von geschlossenen Tickets bearbeitet werden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $appointment['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-User' && $appointment['company_id'] == $userCompanyId && $userCompanyId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            try {
                $endDate = new DateTime($endeDatum);
                if ($endDate < $startDate) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Enddatum muss nach Startdatum liegen']);
                    exit;
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültiges Datumsformat']);
                exit;
            }
            
            // Termin aktualisieren
            $stmt = $pdo->prepare("
                UPDATE ticket_appointments 
                SET titel = ?, typ = ?, start_datum = ?, ende_datum = ?
                WHERE id = ?
            ");
            $stmt->execute([$titel, $typ, $startDatum, $endeDatum, $appointmentId]);
            
            $ticketIdForUpdate = $appointment['ticket_id'];
            // Wenn der letzte Termin (nach Ende- oder Startdatum) in der Vergangenheit liegt → Status "Neu"
            $setStatusNeu = false;
            try {
                $lastStmt = $pdo->prepare("SELECT MAX(COALESCE(ende_datum, start_datum)) AS last_date FROM ticket_appointments WHERE ticket_id = ?");
                $lastStmt->execute([$ticketIdForUpdate]);
                $lastDate = $lastStmt->fetchColumn();
                if ($lastDate && (new DateTime($lastDate) < new DateTime())) {
                    $setStatusNeu = true;
                }
            } catch (Exception $e) {
                // bei Fehler nur geaendert_datum setzen
            }
            if ($setStatusNeu) {
                $updateStmt = $pdo->prepare("UPDATE tickets SET status = 'Neu', geaendert_datum = NOW() WHERE id = ?");
            } else {
                $updateStmt = $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?");
            }
            $updateStmt->execute([$ticketIdForUpdate]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Termin erfolgreich aktualisiert'
            ]);
            break;
            
        case 'DELETE':
            // Termin löschen
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $appointmentId = (int)$_GET['id'];
            
            // Prüfen ob Termin existiert und Berechtigung
            $checkStmt = $pdo->prepare("
                SELECT ta.id, ta.ticket_id, t.company_id, t.status
                FROM ticket_appointments ta
                JOIN tickets t ON ta.ticket_id = t.id
                WHERE ta.id = ?
            ");
            $checkStmt->execute([$appointmentId]);
            $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Termin nicht gefunden']);
                exit;
            }
            
            if ($appointment['status'] === 'Geschlossen' || $appointment['status'] === 'Archiv') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Termine können nicht von geschlossenen Tickets gelöscht werden']);
                exit;
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $appointment['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-User' && $appointment['company_id'] == $userCompanyId && $userCompanyId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Termin löschen
            $ticketIdForUpdate = $appointment['ticket_id'];
            $stmt = $pdo->prepare("DELETE FROM ticket_appointments WHERE id = ?");
            $stmt->execute([$appointmentId]);
            
            // Keine Termine mehr ODER letzter Termin in der Vergangenheit → Status "Neu"; sonst nur "Zuletzt geändert"
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ticket_appointments WHERE ticket_id = ?");
            $countStmt->execute([$ticketIdForUpdate]);
            $remaining = (int) $countStmt->fetchColumn();
            $setStatusNeu = ($remaining === 0);
            if (!$setStatusNeu) {
                $lastStmt = $pdo->prepare("SELECT MAX(COALESCE(ende_datum, start_datum)) AS last_date FROM ticket_appointments WHERE ticket_id = ?");
                $lastStmt->execute([$ticketIdForUpdate]);
                $lastDate = $lastStmt->fetchColumn();
                if ($lastDate) {
                    try {
                        $setStatusNeu = (new DateTime($lastDate) < new DateTime());
                    } catch (Exception $e) {
                        $setStatusNeu = false;
                    }
                }
            }
            if ($setStatusNeu) {
                $updateStmt = $pdo->prepare("UPDATE tickets SET status = 'Neu', geaendert_datum = NOW() WHERE id = ?");
            } else {
                $updateStmt = $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?");
            }
            $updateStmt->execute([$ticketIdForUpdate]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Termin erfolgreich gelöscht'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Appointments API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
