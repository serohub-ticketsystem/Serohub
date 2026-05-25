<?php
/**
 * ICS-Download für einen einzelnen Termin
 * Ermöglicht Empfängern, den Termin in ihren eigenen Kalender (Outlook, Google, Apple) hinzuzufügen
 */

require_once dirname(__DIR__, 2) . '/assets/config.php';

$token = $_GET['token'] ?? $_GET['t'] ?? '';

if (empty($token) || strlen($token) < 16) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ungültiger oder fehlender Link';
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.title, e.description, e.meeting_link, e.start_at, e.end_at, e.all_day,
               u.vorname, u.nachname
        FROM calendar_events e
        JOIN users u ON e.user_id = u.id
        WHERE e.ics_token = :token
        LIMIT 1
    ");
    $stmt->bindValue(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Termin nicht gefunden oder Link abgelaufen';
        exit;
    }

    $organizer = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? ''));
    $desc = $row['description'] ?? '';
    if ($organizer) $desc .= ($desc ? "\n\n" : '') . "Organisator: " . $organizer;
    if (!empty($row['meeting_link'])) $desc .= ($desc ? "\n\n" : '') . "Meeting: " . $row['meeting_link'];

    $event = [
        'uid' => 'event-' . $row['id'] . '@kalender.local',
        'summary' => $row['title'],
        'description' => $desc,
        'location' => '',
        'start' => $row['start_at'],
        'end' => $row['end_at'],
        'allDay' => (bool) $row['all_day']
    ];
    if (!empty($row['meeting_link'])) {
        $event['url'] = $row['meeting_link'];
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="termin-' . $row['id'] . '.ics"');

    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//Softwareverteilung//Kalender//DE\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "METHOD:PUBLISH\r\n";
    echo "X-WR-TIMEZONE:Europe/Berlin\r\n";

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
    if (!empty($event['description'])) echo "DESCRIPTION:" . escapeICS($event['description']) . "\r\n";
    if (!empty($event['url'])) echo "URL:" . escapeICS($event['url']) . "\r\n";

    echo "END:VEVENT\r\n";
    echo "END:VCALENDAR\r\n";

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Serverfehler';
}

function escapeICS($str) {
    $str = str_replace(['\\', "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\\;'], $str);
    if (strlen($str) > 70) $str = wordwrap($str, 70, "\r\n ", true);
    return $str;
}
