<?php
/**
 * API zum Abrufen von ICS/CalDAV-Kalender-Events
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$subscriptionId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if (!$subscriptionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Subscription ID erforderlich']);
    exit;
}

try {
    // Abonnement laden
    $stmt = $pdo->prepare("
        SELECT id, name, url, color, is_active
        FROM calendar_subscriptions
        WHERE id = :id AND user_id = :uid
    ");
    $stmt->bindValue(':id', $subscriptionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Abonnement nicht gefunden']);
        exit;
    }
    
    if (!$sub['is_active']) {
        echo json_encode(['success' => true, 'events' => []]);
        exit;
    }
    
    // ICS-Feed abrufen
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Softwareverteilung Calendar/1.0'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $icsContent = @file_get_contents($sub['url'], false, $context);
    
    if ($icsContent === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Kalender konnte nicht abgerufen werden']);
        exit;
    }
    
    // ICS parsen
    $events = parseICS($icsContent, $sub['color'], $sub['name'], $start, $end);
    
    // Last sync aktualisieren
    $stmt = $pdo->prepare("UPDATE calendar_subscriptions SET last_sync = NOW() WHERE id = :id");
    $stmt->bindValue(':id', $subscriptionId, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'events' => $events]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Einfacher ICS-Parser
 */
function parseICS($icsContent, $color, $calendarName, $rangeStart = null, $rangeEnd = null) {
    $events = [];
    
    // Zeitbereich
    $startTs = $rangeStart ? strtotime($rangeStart) : strtotime('-3 months');
    $endTs = $rangeEnd ? strtotime($rangeEnd) : strtotime('+3 months');
    
    // Events extrahieren
    preg_match_all('/BEGIN:VEVENT(.+?)END:VEVENT/s', $icsContent, $matches);
    
    foreach ($matches[1] as $eventData) {
        $event = [
            'title' => '',
            'start' => null,
            'end' => null,
            'allDay' => false,
            'description' => '',
            'location' => '',
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => '#ffffff',
            'editable' => false,
            'extendedProps' => [
                'source' => 'subscription',
                'sourceLabel' => $calendarName
            ]
        ];
        
        // SUMMARY (Titel)
        if (preg_match('/SUMMARY[^:]*:(.+)/i', $eventData, $m)) {
            $event['title'] = decodeICSValue(trim($m[1]));
        }
        
        // DTSTART
        if (preg_match('/DTSTART[^:]*:(\d{8}T?\d{0,6}Z?)/i', $eventData, $m)) {
            $event['start'] = parseICSDate($m[1]);
            // Ganztägig wenn nur Datum (8 Zeichen)
            if (strlen(preg_replace('/[^0-9]/', '', $m[1])) === 8) {
                $event['allDay'] = true;
            }
        } elseif (preg_match('/DTSTART;VALUE=DATE:(\d{8})/i', $eventData, $m)) {
            $event['start'] = parseICSDate($m[1]);
            $event['allDay'] = true;
        }
        
        // DTEND
        if (preg_match('/DTEND[^:]*:(\d{8}T?\d{0,6}Z?)/i', $eventData, $m)) {
            $event['end'] = parseICSDate($m[1]);
        } elseif (preg_match('/DTEND;VALUE=DATE:(\d{8})/i', $eventData, $m)) {
            $event['end'] = parseICSDate($m[1]);
        }
        
        // DESCRIPTION
        if (preg_match('/DESCRIPTION[^:]*:(.+?)(?=\r?\n[A-Z])/is', $eventData, $m)) {
            $event['description'] = decodeICSValue(trim($m[1]));
        }
        
        // LOCATION
        if (preg_match('/LOCATION[^:]*:(.+)/i', $eventData, $m)) {
            $event['location'] = decodeICSValue(trim($m[1]));
        }
        
        // UID für Identifikation
        if (preg_match('/UID[^:]*:(.+)/i', $eventData, $m)) {
            $event['id'] = 'sub_' . md5(trim($m[1]));
        }
        
        // Prüfen ob Event im Zeitbereich liegt
        if ($event['start']) {
            $eventTs = strtotime($event['start']);
            if ($eventTs >= $startTs && $eventTs <= $endTs) {
                // Falls kein Ende, setze Ende = Start
                if (!$event['end']) {
                    $event['end'] = $event['start'];
                }
                $events[] = $event;
            }
        }
    }
    
    return $events;
}

function parseICSDate($dateStr) {
    // Format: YYYYMMDD oder YYYYMMDDTHHmmss oder YYYYMMDDTHHmmssZ
    $dateStr = preg_replace('/[^0-9TZ]/', '', $dateStr);
    
    if (strlen($dateStr) === 8) {
        // Nur Datum
        return substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
    } elseif (strlen($dateStr) >= 15) {
        // Datum + Zeit
        $date = substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
        $time = substr($dateStr, 9, 2) . ':' . substr($dateStr, 11, 2) . ':' . substr($dateStr, 13, 2);
        
        // Wenn UTC (Z), in lokale Zeit konvertieren
        if (strpos($dateStr, 'Z') !== false) {
            $dt = new DateTime($date . 'T' . $time, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $dt->format('Y-m-d\TH:i:s');
        }
        
        return $date . 'T' . $time;
    }
    
    return null;
}

function decodeICSValue($value) {
    // Zeilenumbrüche entfernen (ICS-Folding)
    $value = preg_replace('/\r?\n\s/', '', $value);
    // Escape-Sequenzen
    $value = str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
    return $value;
}
