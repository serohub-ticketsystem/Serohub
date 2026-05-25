<?php
/**
 * CalDAV-Sync: Events zum CalDAV-Server pushen
 * Verwendet cURL für HTTP PUT (WebDAV/CalDAV)
 */

if (!defined('CALDAV_ENCRYPTION_KEY')) {
    define('CALDAV_ENCRYPTION_KEY', hash('sha256', (defined('DB_PASS') ? DB_PASS : 'default') . 'caldav_sync_key', true));
}

function caldav_encrypt_password($plain) {
    if (empty($plain)) return null;
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($plain, 'aes-256-cbc', CALDAV_ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) return null;
    return base64_encode($iv . $encrypted);
}

function caldav_decrypt_password($encrypted) {
    if (empty($encrypted)) return '';
    $raw = base64_decode($encrypted, true);
    if (!$raw || strlen($raw) < 17) return '';
    $iv = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    $dec = openssl_decrypt($enc, 'aes-256-cbc', CALDAV_ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    return $dec !== false ? $dec : '';
}

function escapeICS($str) {
    $str = str_replace(['\\', "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\\;'], $str);
    if (strlen($str) > 70) {
        $str = wordwrap($str, 70, "\r\n ", true);
    }
    return $str;
}

/**
 * Erzeugt iCalendar-String für ein einzelnes Event
 */
function eventToIcs($event) {
    $lines = [];
    $lines[] = "BEGIN:VCALENDAR";
    $lines[] = "VERSION:2.0";
    $lines[] = "PRODID:-//Softwareverteilung//Kalender//DE";
    $lines[] = "CALSCALE:GREGORIAN";
    // METHOD darf bei CalDAV-Speicherung nicht gesetzt werden (RFC 4791)
    $lines[] = "BEGIN:VEVENT";
    $lines[] = "UID:" . $event['uid'];
    $lines[] = "DTSTAMP:" . gmdate('Ymd\THis\Z');
    
    if (!empty($event['allDay'])) {
        $startDate = date('Ymd', strtotime($event['start']));
        $endDate = date('Ymd', strtotime($event['end'] . ' +1 day'));
        $lines[] = "DTSTART;VALUE=DATE:" . $startDate;
        $lines[] = "DTEND;VALUE=DATE:" . $endDate;
    } else {
        $startDt = new DateTime($event['start'], new DateTimeZone('Europe/Berlin'));
        $endDt = new DateTime($event['end'], new DateTimeZone('Europe/Berlin'));
        $lines[] = "DTSTART:" . $startDt->format('Ymd\THis');
        $lines[] = "DTEND:" . $endDt->format('Ymd\THis');
    }
    
    $lines[] = "SUMMARY:" . escapeICS($event['summary']);
    if (!empty($event['description'])) {
        $lines[] = "DESCRIPTION:" . escapeICS($event['description']);
    }
    if (!empty($event['location'])) {
        $lines[] = "LOCATION:" . escapeICS($event['location']);
    }
    if (!empty($event['url'])) {
        $lines[] = "URL:" . escapeICS($event['url']);
    }
    
    $lines[] = "END:VEVENT";
    $lines[] = "END:VCALENDAR";
    return implode("\r\n", $lines);
}

/**
 * Sammelt alle Events für einen User (wie export-ics.php, aber als Array)
 * @param bool $isAdminOrTechniker Wenn true: bei Ticketsn alle Tickets (wie im Kalender)
 */
function getEventsForUser($pdo, $userId, $exportSources, $isAdminOrTechniker = false) {
    $events = [];
    
    if (!empty($exportSources['my_calendar'])) {
        $stmt = $pdo->prepare("SELECT id, title, description, meeting_link, start_at, end_at, all_day FROM calendar_events WHERE user_id = ? ORDER BY start_at ASC");
        $stmt->execute([$userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ev = [
                'uid' => 'event-' . $row['id'] . '@kalender.local',
                'summary' => $row['title'],
                'description' => ($row['description'] ?? '') . (!empty($row['meeting_link']) ? "\n\nMeeting: " . $row['meeting_link'] : ''),
                'location' => '', 'start' => $row['start_at'], 'end' => $row['end_at'], 'allDay' => (bool)$row['all_day']
            ];
            if (!empty($row['meeting_link'])) $ev['url'] = $row['meeting_link'];
            $events[] = $ev;
        }
    }
    
    if (!empty($exportSources['vacation'])) {
        $stmt = $pdo->prepare("SELECT id, date, hours, type FROM time_tracking_vacation WHERE user_id = ? ORDER BY date ASC");
        $stmt->execute([$userId]);
        $labels = ['vacation' => 'Urlaub', 'sick' => 'Krank', 'holiday' => 'Feiertag', 'other' => 'Sonstiges'];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = [
                'uid' => 'vacation-' . $row['id'] . '@kalender.local',
                'summary' => $labels[$row['type']] ?? $row['type'],
                'description' => $row['hours'] . ' Stunden', 'location' => '',
                'start' => $row['date'], 'end' => $row['date'], 'allDay' => true
            ];
        }
    }
    
    if (!empty($exportSources['invitations'])) {
        $stmt = $pdo->prepare("
            SELECT e.id, e.title, e.description, e.meeting_link, e.start_at, e.end_at, e.all_day, u.vorname, u.nachname
            FROM calendar_events e
            JOIN calendar_event_invitees i ON e.id = i.event_id
            JOIN users u ON e.user_id = u.id
            WHERE i.user_id = ?
            ORDER BY e.start_at ASC
        ");
        $stmt->execute([$userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $organizer = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? ''));
            $ev = [
                'uid' => 'invite-' . $row['id'] . '@kalender.local',
                'summary' => $row['title'] . ' (Einladung)',
                'description' => ($row['description'] ?? '') . "\n\nOrganisator: " . $organizer . (!empty($row['meeting_link']) ? "\n\nMeeting: " . $row['meeting_link'] : ''),
                'location' => '', 'start' => $row['start_at'], 'end' => $row['end_at'], 'allDay' => (bool)$row['all_day']
            ];
            if (!empty($row['meeting_link'])) $ev['url'] = $row['meeting_link'];
            $events[] = $ev;
        }
    }
    
    if (!empty($exportSources['service_tickets'])) {
        // Primär: neue Ticket-Termine aus ticket_appointments
        if ($isAdminOrTechniker) {
            $stmt = $pdo->prepare("
                SELECT ta.id AS appointment_id, ta.ticket_id, ta.titel AS appointment_titel, ta.typ, ta.start_datum, ta.ende_datum,
                       t.ticket_nummer, t.titel, t.beschreibung, t.status, t.prioritaet, c.name AS company_name,
                       cu.name AS customer_name, cu.adresse, cu.plz, cu.ort, u.vorname, u.nachname
                FROM ticket_appointments ta
                JOIN tickets t ON ta.ticket_id = t.id
                LEFT JOIN companies c ON t.company_id = c.id
                LEFT JOIN customers cu ON t.customer_id = cu.id
                LEFT JOIN users u ON t.zugewiesen_an = u.id
                WHERE t.status NOT IN ('Geschlossen', 'Archiv')
                ORDER BY ta.start_datum ASC
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT ta.id AS appointment_id, ta.ticket_id, ta.titel AS appointment_titel, ta.typ, ta.start_datum, ta.ende_datum,
                       t.ticket_nummer, t.titel, t.beschreibung, t.status, t.prioritaet, c.name AS company_name,
                       cu.name AS customer_name, cu.adresse, cu.plz, cu.ort, u.vorname, u.nachname
                FROM ticket_appointments ta
                JOIN tickets t ON ta.ticket_id = t.id
                LEFT JOIN companies c ON t.company_id = c.id
                LEFT JOIN customers cu ON t.customer_id = cu.id
                LEFT JOIN users u ON t.zugewiesen_an = u.id
                WHERE (t.zugewiesen_an = ? OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = ?))
                  AND t.status NOT IN ('Geschlossen', 'Archiv')
                ORDER BY ta.start_datum ASC
            ");
            $stmt->execute([$userId, $userId]);
        }
        $baseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        if (empty($baseUrl) && !empty($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $basePath = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')));
            $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim($basePath ?: '/', '/');
        }
        if (empty($baseUrl)) $baseUrl = '/';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $location = implode(', ', array_filter([$row['adresse'] ?? '', trim(($row['plz'] ?? '') . ' ' . ($row['ort'] ?? ''))]));
            $ticketId = (int) ($row['ticket_id'] ?? 0);
            $appointmentId = (int) ($row['appointment_id'] ?? 0);
            $ticketUrl = rtrim($baseUrl, '/') . '/tickets/view.php?id=' . $ticketId;
            $appointmentType = ($row['typ'] ?? '') === 'faellig' ? 'Fällig' : 'Geplant';
            $appointmentTitle = trim((string)($row['appointment_titel'] ?? ''));
            $summary = $row['titel'] . ($appointmentTitle !== '' ? ' - ' . $appointmentTitle : '');
            $desc = "Ticket-Nr.: " . $row['ticket_nummer']
                . "\nTermin-Typ: " . $appointmentType
                . ($appointmentTitle !== '' ? "\nTermin-Titel: " . $appointmentTitle : '')
                . "\nFirma: " . ($row['company_name'] ?? '-')
                . "\nStatus: " . $row['status']
                . "\nKunde: " . ($row['customer_name'] ?? $row['company_name'] ?? '')
                . "\n\n" . ($row['beschreibung'] ?? '')
                . "\n\nTicket: " . $ticketUrl;
            $startAt = $row['start_datum'];
            $endAt = !empty($row['ende_datum']) ? $row['ende_datum'] : (new DateTime($startAt))->modify('+1 hour')->format('Y-m-d H:i:s');
            $events[] = [
                'uid' => 'ticket-appointment-' . $appointmentId . '@kalender.local',
                'summary' => $summary,
                'description' => $desc,
                'location' => $location,
                'url' => $ticketUrl,
                'start' => $startAt,
                'end' => $endAt,
                'allDay' => false
            ];
        }
    }
    
    if (!empty($exportSources['todos'])) {
        $stmt = $pdo->prepare("
            SELECT t.id, t.titel, t.beschreibung, t.status, t.prioritaet, t.faellig_am, c.name AS company_name
            FROM todos t LEFT JOIN companies c ON t.company_id = c.id
            WHERE t.zugewiesen_an = ? AND t.status != 'erledigt' AND t.faellig_am IS NOT NULL
            ORDER BY t.faellig_am ASC
        ");
        $stmt->execute([$userId]);
        $priorityMap = ['niedrig' => '⬇️', 'normal' => '➡️', 'hoch' => '⬆️', 'kritisch' => '🔴'];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = [
                'uid' => 'todo-' . $row['id'] . '@kalender.local',
                'summary' => ($priorityMap[$row['prioritaet']] ?? '') . ' Aufgabe: ' . $row['titel'],
                'description' => "Status: " . $row['status'] . "\n" . ($row['company_name'] ? "Firma: " . $row['company_name'] . "\n" : '') . "\n" . ($row['beschreibung'] ?? ''),
                'location' => $row['company_name'] ?? '', 'start' => $row['faellig_am'], 'end' => $row['faellig_am'], 'allDay' => true
            ];
        }
    }
    
    return $events;
}

/**
 * Normalisiert und validiert eine CalDAV-URL
 * Korrigiert häufige Fehler bei Nextcloud-URLs
 */
function normalizeCalDAVUrl($url) {
    $url = trim($url);
    if (empty($url)) return $url;
    
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        return rtrim($url, '/') . '/';
    }
    
    // Problem: URL hat Query-Parameter ?url=/remote.php/dav/...
    // Das muss in einen normalen Pfad umgewandelt werden
    if (isset($parsed['query'])) {
        // Prüfe auf url= Parameter (kann auch mit & andere Parameter haben)
        if (preg_match('/[&?]url=([^&]+)/i', $parsed['query'], $matches)) {
            $davPath = urldecode($matches[1]);
            // Entferne führenden Schrägstrich, falls vorhanden
            $davPath = ltrim($davPath, '/');
            // Baue neue URL ohne Query-Parameter
            $base = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
            if (!empty($parsed['port'])) $base .= ':' . $parsed['port'];
            $url = $base . '/' . $davPath;
        } else {
            // Normale URL-Normalisierung
            $url = rtrim($url, '/') . '/';
        }
    } else {
        // Normale URL-Normalisierung
        $url = rtrim($url, '/') . '/';
    }
    
    // Bei Nextcloud: Stelle sicher, dass /remote.php/dav/ enthalten ist
    // Nur wenn die URL nextcloud/owncloud enthält UND noch kein /remote.php/dav/ vorhanden ist
    if ((stripos($url, 'nextcloud') !== false || stripos($url, 'owncloud') !== false || 
         stripos($parsed['host'] ?? '', 'nextcloud') !== false || 
         stripos($parsed['host'] ?? '', 'owncloud') !== false) && 
        stripos($url, '/remote.php/dav') === false) {
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['host'])) {
            $base = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
            if (!empty($parsed['port'])) $base .= ':' . $parsed['port'];
            $path = trim($parsed['path'] ?? '', '/');
            // Entferne bereits vorhandene dav/ oder ähnliches am Ende
            $path = preg_replace('#/(dav|caldav|remote\.php).*$#i', '', $path);
            // Füge /remote.php/dav/ hinzu
            $url = $base . '/' . $path . '/remote.php/dav/';
        }
    }
    
    // Stelle sicher, dass die URL mit / endet
    $url = rtrim($url, '/') . '/';
    
    return $url;
}

/**
 * Prüft, ob SSL-Verifizierung für eine URL übersprungen werden soll
 * (bei HTTP oder selbstsignierten Zertifikaten)
 */
function shouldSkipSSLVerification($url) {
    $parsed = parse_url($url);
    // Bei HTTP (Port 80) oder wenn kein Schema vorhanden, SSL-Verifizierung überspringen
    if (empty($parsed['scheme']) || strtolower($parsed['scheme']) === 'http') {
        return true;
    }
    // Bei HTTPS im lokalen Netzwerk (localhost, private IPs) auch überspringen
    if (strtolower($parsed['scheme']) === 'https') {
        $host = $parsed['host'] ?? '';
        // Lokale Adressen oder private IPs
        if (in_array(strtolower($host), ['localhost', '127.0.0.1']) ||
            preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $host)) {
            return true;
        }
        // Prüfe auf Port 80 in der URL (auch bei HTTPS-Schema)
        $port = $parsed['port'] ?? null;
        if ($port === 80) {
            return true;
        }
    }
    return false;
}

/**
 * Führt einen cURL-Request mit Fallback bei SSL-Fehlern durch
 * Versucht zuerst mit SSL-Verifizierung, bei Fehlern ohne
 */
function curlExecWithSSLFallback($ch, $url, $skipSSL = false) {
    // Header in Response einschließen für Debugging
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Header und Body trennen
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Wenn SSL-Fehler und noch nicht ohne Verifizierung versucht, nochmal versuchen
    if ($err && !$skipSSL && (strpos($err, 'SSL') !== false || strpos($err, 'certificate') !== false || strpos($err, 'cert') !== false)) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
    }
    
    return [
        'response' => $body,
        'headers' => $headers,
        'error' => $err,
        'http_code' => $httpCode
    ];
}

/**
 * Listet die Kalender eines Benutzers auf dem CalDAV-Server auf.
 * Führt PROPFIND auf der Kalender-Home-URL (calendars/username/) aus.
 * @return array Liste von Kalendernamen ['Personal', 'Arbeit', ...]
 */
function listCalDAVCalendars($serverUrl, $username, $password) {
    $baseUrl = normalizeCalDAVUrl($serverUrl);
    $calendarHomeUrl = $baseUrl . 'calendars/' . rawurlencode($username) . '/';
    $body = '<?xml version="1.0" encoding="utf-8"?><propfind xmlns="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav"><prop><resourcetype/><C:calendar-home-set/><displayname/></prop></propfind>';
    
    $skipSSL = shouldSkipSSLVerification($calendarHomeUrl);
    $ch = curl_init($calendarHomeUrl);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/xml; charset=utf-8',
            'Depth: 1'
        ],
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_SSL_VERIFYPEER => !$skipSSL,
        CURLOPT_SSL_VERIFYHOST => $skipSSL ? 0 : 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15
    ];
    curl_setopt_array($ch, $curlOpts);
    
    $result = curlExecWithSSLFallback($ch, $calendarHomeUrl, $skipSSL);
    $response = $result['response'];
    $headers = $result['headers'] ?? '';
    $httpCode = $result['http_code'];
    $err = $result['error'];
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'calendars' => [], 'error' => 'Verbindungsfehler: ' . $err];
    }
    if ($httpCode === 401 || $httpCode === 403) {
        $errorMsg = 'Zugriff verweigert (HTTP ' . $httpCode . '). ';
        $errorMsg .= 'Bitte prüfen Sie:';
        $errorMsg .= "\n- Benutzername und Passwort (bei Nextcloud: App-Passwort verwenden!)";
        $errorMsg .= "\n- CalDAV-URL (sollte enden mit: /remote.php/dav/)";
        $errorMsg .= "\n- Verwendete URL: " . $effectiveUrl;
        
        // Prüfe WWW-Authenticate Header für mehr Details
        if (preg_match('/WWW-Authenticate:\s*(.+)/i', $headers, $matches)) {
            $errorMsg .= "\n- Server erwartet: " . trim($matches[1]);
        }
        
        if (!empty($response) && strlen($response) < 500) {
            $cleanResponse = strip_tags($response);
            $cleanResponse = preg_replace('/\s+/', ' ', $cleanResponse);
            $errorMsg .= "\n- Server-Antwort: " . substr($cleanResponse, 0, 200);
        }
        return ['success' => false, 'calendars' => [], 'error' => $errorMsg];
    }
    if ($httpCode === 404) {
        return ['success' => false, 'calendars' => [], 'error' => 'Kalender-URL nicht gefunden.'];
    }
    if ($httpCode === 301 || $httpCode === 302 || $httpCode === 307 || $httpCode === 308) {
        return ['success' => false, 'calendars' => [], 'error' => 'Redirect-Fehler (HTTP ' . $httpCode . '). Bitte prüfen Sie die CalDAV-URL. Möglicherweise fehlt ein abschließender Schrägstrich oder die URL ist falsch.'];
    }
    if ($httpCode < 200 || $httpCode >= 300 || empty($response)) {
        return ['success' => false, 'calendars' => [], 'error' => 'Fehler: HTTP ' . $httpCode];
    }
    
    $calendars = [];
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($response);
    if (!$xml) return ['success' => true, 'calendars' => []];
    
    $xml->registerXPathNamespace('d', 'DAV:');
    $responses = $xml->xpath('//d:response');
    
    foreach ($responses as $r) {
        $r->registerXPathNamespace('d', 'DAV:');
        $hrefNodes = $r->xpath('d:href');
        if (empty($hrefNodes)) continue;
        $href = trim((string) $hrefNodes[0]);
        if (empty($href)) continue;
        
        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        $path = rtrim($path, '/');
        $basename = basename($path);
        if (empty($basename)) continue;
        
        // Kalender-Home-URL selbst überspringen (z.B. .../calendars/user/)
        $calendarHomePath = trim(parse_url($calendarHomeUrl, PHP_URL_PATH), '/');
        $currentPath = trim($path, '/');
        if ($currentPath === $calendarHomePath) continue;
        
        // Nur direkte Kinder der Kalender-Home (Depth 1)
        if (strpos($currentPath, $calendarHomePath . '/') === 0) {
            $relative = substr($currentPath, strlen($calendarHomePath) + 1);
            if (strpos($relative, '/') === false) {
                $calendars[] = $basename;
            }
        }
    }
    
    $calendars = array_unique(array_filter($calendars));
    sort($calendars);
    return ['success' => true, 'calendars' => $calendars];
}

/**
 * Listet vorhandene Kalender-Ressourcen auf (nur unsere: *@kalender.local)
 * Gibt Array von Ressourcen-URLs zurück.
 */
function listOurCalDAVResources($calendarUrl, $username, $password) {
    $baseUrl = normalizeCalDAVUrl($calendarUrl);
    $body = '<?xml version="1.0" encoding="utf-8"?><propfind xmlns="DAV:"><prop><resourcetype/><getcontenttype/></prop></propfind>';
    
    $skipSSL = shouldSkipSSLVerification($baseUrl);
    $ch = curl_init($baseUrl);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/xml; charset=utf-8',
            'Depth: 1'
        ],
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_SSL_VERIFYPEER => !$skipSSL,
        CURLOPT_SSL_VERIFYHOST => $skipSSL ? 0 : 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15
    ];
    curl_setopt_array($ch, $curlOpts);
    
    $result = curlExecWithSSLFallback($ch, $baseUrl, $skipSSL);
    $response = $result['response'];
    $httpCode = $result['http_code'];
    curl_close($ch);
    
    if ($httpCode < 200 || $httpCode >= 300 || empty($response)) {
        return [];
    }
    
    $resources = [];
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($response);
    if (!$xml) return [];
    
    $xml->registerXPathNamespace('d', 'DAV:');
    $responses = $xml->xpath('//d:response');
    
    foreach ($responses as $r) {
        $r->registerXPathNamespace('d', 'DAV:');
        $hrefNodes = $r->xpath('d:href');
        if (empty($hrefNodes)) continue;
        $href = trim((string) $hrefNodes[0]);
        if (empty($href)) continue;
        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        $basename = basename(rtrim($path, '/'));
        if ($basename === '' || strpos($basename, 'kalender.local.ics') === false) continue;
        $fullUrl = (strpos($href, 'http') === 0) ? rtrim($href, '/') : rtrim($baseUrl, '/') . '/' . $basename;
        $resources[] = $fullUrl;
    }
    return $resources;
}

/**
 * UID zu CalDAV-Dateiname (wie bei pushEventsToCalDAV)
 */
function uidToCalDAVFilename($uid) {
    return preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $uid) . '.ics';
}

/**
 * Voll-Sync: Löscht obsolete Events in CalDAV, pusht aktuelle
 * Berücksichtigt: gelöschte Termine, geschlossene Tickets, verschobene Events
 */
function pushEventsToCalDAV($calendarUrl, $username, $password, $events, $logFn = null) {
    $baseUrl = normalizeCalDAVUrl($calendarUrl);
    $success = 0;
    $errors = [];
    
    $wantedFilenames = [];
    foreach ($events as $event) {
        $wantedFilenames[uidToCalDAVFilename($event['uid'])] = true;
    }
    
    $existing = listOurCalDAVResources($calendarUrl, $username, $password);
    foreach ($existing as $resourceUrl) {
        $basename = basename(parse_url($resourceUrl, PHP_URL_PATH) ?: $resourceUrl);
        if (!isset($wantedFilenames[$basename])) {
            $skipSSL = shouldSkipSSLVerification($resourceUrl);
            $ch = curl_init($resourceUrl);
            $curlOpts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_USERPWD => $username . ':' . $password,
                CURLOPT_SSL_VERIFYPEER => !$skipSSL,
                CURLOPT_SSL_VERIFYHOST => $skipSSL ? 0 : 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 10
            ];
            curl_setopt_array($ch, $curlOpts);
            $result = curlExecWithSSLFallback($ch, $resourceUrl, $skipSSL);
            $code = $result['http_code'];
            curl_close($ch);
            if ($code >= 200 && $code < 300) {
                if ($logFn) $logFn("CalDAV DELETE $resourceUrl: OK");
            }
        }
    }
    
    foreach ($events as $event) {
        $uid = $event['uid'];
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $uid) . '.ics';
        $url = $baseUrl . $filename;
        $ics = eventToIcs($event);
        
        $skipSSL = shouldSkipSSLVerification($url);
        $ch = curl_init($url);
        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $ics,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/calendar; charset=utf-8',
                'Content-Length: ' . strlen($ics)
            ],
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_SSL_VERIFYPEER => !$skipSSL,
            CURLOPT_SSL_VERIFYHOST => $skipSSL ? 0 : 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15
        ];
        curl_setopt_array($ch, $curlOpts);
        
        $result = curlExecWithSSLFallback($ch, $url, $skipSSL);
        $response = $result['response'];
        $httpCode = $result['http_code'];
        $err = $result['error'];
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $success++;
        } else {
            $msg = $err ?: "HTTP $httpCode" . (is_string($response) && strlen($response) < 500 ? ': ' . $response : '');
            $errors[] = $uid . ': ' . $msg;
            if ($logFn) $logFn("CalDAV PUT $url: $msg");
        }
    }
    
    return [
        'success' => $success,
        'total' => count($events),
        'errors' => $errors
    ];
}

/**
 * Testet die CalDAV-Verbindung (PROPFIND auf Kalender-URL)
 */
function testCalDAVConnection($calendarUrl, $username, $password) {
    $url = normalizeCalDAVUrl($calendarUrl);
    $body = '<?xml version="1.0" encoding="utf-8"?><propfind xmlns="DAV:"><prop><resourcetype/><displayname/></prop></propfind>';
    
    $skipSSL = shouldSkipSSLVerification($url);
    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/xml; charset=utf-8',
            'Depth: 0'
        ],
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_SSL_VERIFYPEER => !$skipSSL,
        CURLOPT_SSL_VERIFYHOST => $skipSSL ? 0 : 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 10
    ];
    curl_setopt_array($ch, $curlOpts);
    
    $result = curlExecWithSSLFallback($ch, $url, $skipSSL);
    $response = $result['response'];
    $headers = $result['headers'] ?? '';
    $httpCode = $result['http_code'];
    $err = $result['error'];
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'message' => 'Verbindungsfehler: ' . $err];
    }
    if ($httpCode === 401 || $httpCode === 403) {
        $errorMsg = 'Zugriff verweigert (HTTP ' . $httpCode . '). ';
        $errorMsg .= 'Bitte prüfen Sie:';
        $errorMsg .= "\n- Benutzername und Passwort (bei Nextcloud: App-Passwort verwenden!)";
        $errorMsg .= "\n- CalDAV-URL (sollte enden mit: /remote.php/dav/)";
        $errorMsg .= "\n- Verwendete URL: " . $effectiveUrl;
        
        // Prüfe WWW-Authenticate Header für mehr Details
        if (preg_match('/WWW-Authenticate:\s*(.+)/i', $headers, $matches)) {
            $errorMsg .= "\n- Server erwartet: " . trim($matches[1]);
        }
        
        if (!empty($response) && strlen($response) < 500) {
            $cleanResponse = strip_tags($response);
            $cleanResponse = preg_replace('/\s+/', ' ', $cleanResponse);
            $errorMsg .= "\n- Server-Antwort: " . substr($cleanResponse, 0, 200);
        }
        return ['success' => false, 'message' => $errorMsg];
    }
    if ($httpCode === 404) {
        return ['success' => false, 'message' => 'Kalender nicht gefunden. Prüfen Sie den Kalendernamen (z.B. "Personal").'];
    }
    if ($httpCode === 301 || $httpCode === 302 || $httpCode === 307 || $httpCode === 308) {
        return ['success' => false, 'message' => 'Redirect-Fehler (HTTP ' . $httpCode . '). Bitte prüfen Sie die CalDAV-URL. Möglicherweise fehlt ein abschließender Schrägstrich oder die URL ist falsch.'];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'Verbindung erfolgreich.'];
    }
    return ['success' => false, 'message' => 'Fehler: HTTP ' . $httpCode];
}
