<?php
/**
 * ICS-Export für Kalender-Einträge
 * Kann in anderen Kalender-Apps abonniert werden
 */

require_once dirname(__DIR__, 2) . '/assets/config.php';

$token = $_GET['token'] ?? null;

if (!$token || strlen($token) < 32) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ungültiger oder fehlender Token';
    exit;
}

try {
    // User anhand Token finden
    $stmt = $pdo->prepare("SELECT id, vorname, nachname, email, rolle FROM users WHERE calendar_token = :token AND status = 'aktiv'");
    $stmt->bindValue(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Ungültiger Token';
        exit;
    }
    
    $userId = (int) $user['id'];
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? '')) ?: $user['email'];
    $isAdminOrTechniker = in_array($user['rolle'] ?? '', ['Admin', 'Techniker'], true);
    
    // Export-Quellen aus user_settings laden
    $exportSources = [
        'my_calendar' => true,
        'vacation' => true,
        'invitations' => true,
        'service_tickets' => true,
        'todos' => true
    ];
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'calendar_export_sources' LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['setting_value']) {
        $decoded = json_decode($row['setting_value'], true);
        if (is_array($decoded)) {
            $exportSources = array_merge($exportSources, $decoded);
        }
    }
    
    // Events laden
    $events = [];
    
    // 1. Eigene Kalender-Events
    if (!empty($exportSources['my_calendar'])) {
    $stmt = $pdo->prepare("
        SELECT id, title, description, meeting_link, start_at, end_at, all_day
        FROM calendar_events
        WHERE user_id = :uid
        ORDER BY start_at ASC
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $desc = $row['description'] ?? '';
        if (!empty($row['meeting_link'])) {
            $desc .= ($desc ? "\n\n" : '') . "Meeting: " . $row['meeting_link'];
        }
        $ev = [
            'uid' => 'event-' . $row['id'] . '@kalender.local',
            'summary' => $row['title'],
            'description' => $desc,
            'location' => '',
            'start' => $row['start_at'],
            'end' => $row['end_at'],
            'allDay' => (bool) $row['all_day']
        ];
        if (!empty($row['meeting_link'])) {
            $ev['url'] = $row['meeting_link'];
        }
        $events[] = $ev;
    }
    }
    
    // 2. Urlaub des Users
    if (!empty($exportSources['vacation'])) {
    $stmt = $pdo->prepare("
        SELECT id, date, hours, type
        FROM time_tracking_vacation
        WHERE user_id = :uid
        ORDER BY date ASC
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $typeLabels = [
            'vacation' => 'Urlaub',
            'sick' => 'Krank',
            'holiday' => 'Feiertag',
            'other' => 'Sonstiges'
        ];
        $label = $typeLabels[$row['type']] ?? $row['type'];
        $events[] = [
            'uid' => 'vacation-' . $row['id'] . '@kalender.local',
            'summary' => $label,
            'description' => $row['hours'] . ' Stunden',
            'location' => '',
            'start' => $row['date'],
            'end' => $row['date'],
            'allDay' => true
        ];
    }
    }
    
    // 3. Events zu denen der User eingeladen wurde
    if (!empty($exportSources['invitations'])) {
    $stmt = $pdo->prepare("
        SELECT e.id, e.title, e.description, e.meeting_link, e.start_at, e.end_at, e.all_day,
               u.vorname, u.nachname
        FROM calendar_events e
        JOIN calendar_event_invitees i ON e.id = i.event_id
        JOIN users u ON e.user_id = u.id
        WHERE i.user_id = :uid
        ORDER BY e.start_at ASC
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $organizer = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? ''));
        $desc = ($row['description'] ?? '') . "\n\nOrganisator: " . $organizer;
        if (!empty($row['meeting_link'])) {
            $desc .= "\n\nMeeting: " . $row['meeting_link'];
        }
        $ev = [
            'uid' => 'invite-' . $row['id'] . '@kalender.local',
            'summary' => $row['title'] . ' (Einladung)',
            'description' => $desc,
            'location' => '',
            'start' => $row['start_at'],
            'end' => $row['end_at'],
            'allDay' => (bool) $row['all_day']
        ];
        if (!empty($row['meeting_link'])) {
            $ev['url'] = $row['meeting_link'];
        }
        $events[] = $ev;
    }
    }
    
    // 4. Tickets (Tickets) – wie im Kalender: geplant UND fällig je eigenes Event
    $hasTicketEndCols = false;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'geplant_datum_ende'");
        $hasTicketEndCols = $chk && $chk->rowCount() > 0;
    } catch (Exception $e) {}
    $selEnd = $hasTicketEndCols ? ', t.geplant_datum_ende, t.faellig_datum_ende' : '';
    if (!empty($exportSources['service_tickets'])) {
    if ($isAdminOrTechniker) {
        $stmt = $pdo->prepare("
            SELECT t.id, t.ticket_nummer, t.titel, t.beschreibung, t.status, t.prioritaet,
                   t.faellig_datum, t.geplant_datum, t.erstellt_datum {$selEnd},
                   c.name AS company_name,
                   cu.name AS customer_name, cu.adresse AS customer_adresse,
                   cu.plz AS customer_plz, cu.ort AS customer_ort,
                   u.vorname, u.nachname
            FROM tickets t
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN customers cu ON t.customer_id = cu.id
            LEFT JOIN users u ON t.zugewiesen_an = u.id
            WHERE t.status NOT IN ('Geschlossen')
              AND (t.geplant_datum IS NOT NULL OR t.faellig_datum IS NOT NULL)
            ORDER BY COALESCE(t.geplant_datum, t.faellig_datum, t.erstellt_datum) ASC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT t.id, t.ticket_nummer, t.titel, t.beschreibung, t.status, t.prioritaet,
                   t.faellig_datum, t.geplant_datum, t.erstellt_datum {$selEnd},
                   c.name AS company_name,
                   cu.name AS customer_name, cu.adresse AS customer_adresse,
                   cu.plz AS customer_plz, cu.ort AS customer_ort,
                   u.vorname, u.nachname
            FROM tickets t
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN customers cu ON t.customer_id = cu.id
            LEFT JOIN users u ON t.zugewiesen_an = u.id
            WHERE (t.zugewiesen_an = :uid OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :uid2))
              AND t.status NOT IN ('Geschlossen')
              AND (t.geplant_datum IS NOT NULL OR t.faellig_datum IS NOT NULL)
            ORDER BY COALESCE(t.geplant_datum, t.faellig_datum, t.erstellt_datum) ASC
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customer = $row['customer_name'] ?? $row['company_name'] ?? '';
        $assignee = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? ''));
        $company = $row['company_name'] ?? '';
        $addressParts = array_filter([
            $row['customer_adresse'] ?? '',
            trim(($row['customer_plz'] ?? '') . ' ' . ($row['customer_ort'] ?? ''))
        ]);
        $location = implode(', ', $addressParts);
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $basePath = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') ?: '';
        $appRoot = $basePath ? rtrim($basePath, '/') . '/' : '/';
        $ticketUrl = $protocol . '://' . $host . $appRoot . 'tickets/view.php?id=' . $row['id'];
        $portalUrl = $protocol . '://' . $host . $appRoot;
        $desc = "Ticket-Nr.: " . $row['ticket_nummer'] . "\nFirma: " . ($company ?: '-') . "\nStatus: " . $row['status'];
        if ($assignee) $desc .= "\nVon: " . $assignee;
        $desc .= "\nKunde: " . $customer;
        $desc .= "\n\nTicket: " . $ticketUrl;
        $desc .= "\nPortal: " . $portalUrl;
        $desc .= "\n\n" . ($row['beschreibung'] ?? '');

        if (!empty($row['geplant_datum'])) {
            $startPlan = $row['geplant_datum'];
            $endPlan = !empty($row['geplant_datum_ende']) ? $row['geplant_datum_ende'] : (new DateTime($startPlan))->modify('+1 hour')->format('Y-m-d H:i:s');
            $events[] = [
                'uid' => 'ticket-plan-' . $row['id'] . '@kalender.local',
                'summary' => $row['titel'],
                'description' => trim($desc),
                'location' => $location,
                'url' => $ticketUrl,
                'start' => $startPlan,
                'end' => $endPlan,
                'allDay' => false
            ];
        }
        if (!empty($row['faellig_datum']) && $row['faellig_datum'] !== ($row['geplant_datum'] ?? null)) {
            $startFaellig = $row['faellig_datum'];
            $endFaellig = !empty($row['faellig_datum_ende']) ? $row['faellig_datum_ende'] : (new DateTime($startFaellig))->modify('+1 hour')->format('Y-m-d H:i:s');
            $events[] = [
                'uid' => 'ticket-faellig-' . $row['id'] . '@kalender.local',
                'summary' => '• ' . $row['titel'],
                'description' => trim($desc),
                'location' => $location,
                'url' => $ticketUrl,
                'start' => $startFaellig,
                'end' => $endFaellig,
                'allDay' => false
            ];
        }
    }
    }
    
    // 5. Aufgaben (Todos) die dem User zugewiesen sind
    if (!empty($exportSources['todos'])) {
    $stmt = $pdo->prepare("
        SELECT t.id, t.titel, t.beschreibung, t.status, t.prioritaet, t.faellig_am,
               c.name AS company_name
        FROM todos t
        LEFT JOIN companies c ON t.company_id = c.id
        WHERE t.zugewiesen_an = :uid 
          AND t.status != 'erledigt'
          AND t.faellig_am IS NOT NULL
        ORDER BY t.faellig_am ASC
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $priority = [
            'niedrig' => '⬇️',
            'normal' => '➡️',
            'hoch' => '⬆️',
            'kritisch' => '🔴'
        ][$row['prioritaet']] ?? '';
        
        $events[] = [
            'uid' => 'todo-' . $row['id'] . '@kalender.local',
            'summary' => $priority . ' Aufgabe: ' . $row['titel'],
            'description' => "Status: " . $row['status'] . "\n" . ($row['company_name'] ? "Firma: " . $row['company_name'] . "\n" : '') . "\n" . ($row['beschreibung'] ?? ''),
            'location' => $row['company_name'] ?? '',
            'start' => $row['faellig_am'],
            'end' => $row['faellig_am'],
            'allDay' => true
        ];
    }
    }
    
    // ICS generieren
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="kalender.ics"');
    
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//Softwareverteilung//Kalender//DE\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "METHOD:PUBLISH\r\n";
    echo "X-WR-CALNAME:" . escapeICS($userName . " - Kalender") . "\r\n";
    echo "X-WR-TIMEZONE:Europe/Berlin\r\n";
    
    foreach ($events as $event) {
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $event['uid'] . "\r\n";
        echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        
        if ($event['allDay']) {
            $startDate = date('Ymd', strtotime($event['start']));
            $endDate = date('Ymd', strtotime($event['end'] . ' +1 day'));
            echo "DTSTART;VALUE=DATE:" . $startDate . "\r\n";
            echo "DTEND;VALUE=DATE:" . $endDate . "\r\n";
        } else {
            $startDt = new DateTime($event['start'], new DateTimeZone('Europe/Berlin'));
            $endDt = new DateTime($event['end'], new DateTimeZone('Europe/Berlin'));
            echo "DTSTART:" . $startDt->format('Ymd\THis') . "\r\n";
            echo "DTEND:" . $endDt->format('Ymd\THis') . "\r\n";
        }
        
        echo "SUMMARY:" . escapeICS($event['summary']) . "\r\n";
        
        if (!empty($event['description'])) {
            echo "DESCRIPTION:" . escapeICS($event['description']) . "\r\n";
        }
        if (!empty($event['location'])) {
            echo "LOCATION:" . escapeICS($event['location']) . "\r\n";
        }
        if (!empty($event['url'])) {
            echo "URL:" . escapeICS($event['url']) . "\r\n";
        }
        
        echo "END:VEVENT\r\n";
    }
    
    echo "END:VCALENDAR\r\n";
    
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Serverfehler';
}

function escapeICS($str) {
    $str = str_replace(['\\', "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\\;'], $str);
    // Zeilenumbruch nach 75 Zeichen (RFC 5545)
    if (strlen($str) > 70) {
        $str = wordwrap($str, 70, "\r\n ", true);
    }
    return $str;
}
